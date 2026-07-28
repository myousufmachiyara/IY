<?php

namespace App\Http\Controllers;

use App\Models\{Customer, User};
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::with('agent')->withCount('vehicles')->latest()->get();
        $agents    = $this->agents($request);

        return view('customers.index', compact('customers', 'agents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['agent_id']   = $this->resolveAgent($request);
        $data['created_by'] = $request->user()->id;
        $data['customer_no'] = \App\Services\CustomerNumber::next();
        Customer::create($data);

        return back()->with('success', 'Customer created. Record their security deposit to complete the profile.');
    }

    public function edit(Customer $customer)
    {
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate($this->rules());

        if (! $request->user()->can('customers.assign_any_agent')) {
            unset($data['agent_id']);
        }

        $customer->update($data);

        return back()->with('success', 'Customer updated.');
    }

    public function show(Customer $customer)
    {
        $customer->load('agent', 'depositReceivedBy', 'depositApprovedBy');
        return view('customers.show', compact('customer'));
    }

    public function destroy(Customer $customer)
    {
        abort_if($customer->vehicles()->exists(), 422, 'Cannot delete a customer with vehicle records. Remove their vehicles first.');
        $customer->delete();

        return back()->with('success', 'Customer removed.');
    }

    /** Step 1 — Sales Agent records that they've physically received the deposit. */
    public function receiveDeposit(Request $request, Customer $customer)
    {
        abort_if($customer->security_deposit_status === 'approved', 422, 'Deposit already approved for this customer.');

        $data = $request->validate([
            'security_deposit' => ['required', 'integer', 'min:1'],
            'account'           => ['required', Rule::in([LedgerService::CASH, LedgerService::BANK])],
        ]);

        $customer->update([
            'security_deposit'                  => $data['security_deposit'],
            'security_deposit_account'          => $data['account'],
            'security_deposit_status'           => 'pending',
            'security_deposit_received_by'      => $request->user()->id,
            'security_deposit_received_at'      => now(),
            'security_deposit_rejection_reason' => null,
        ]);

        return back()->with('success', 'Deposit recorded as received — awaiting accountant approval.');
    }

    /** Step 2 — Accountant / Super Admin confirms the deposit and posts it to the ledger. */
    public function approveDeposit(Customer $customer, LedgerService $ledger)
    {
        abort_unless(request()->user()->canBackdate(), 403, 'Only accountant or super admin may approve deposits.');
        abort_unless($customer->security_deposit_status === 'pending', 422, 'No pending deposit to approve.');

        $customer->update([
            'security_deposit_status'      => 'approved',
            'security_deposit_paid'        => true,
            'security_deposit_approved_by' => request()->user()->id,
            'security_deposit_approved_at' => now(),
            'profile_completed_at'         => now(),
        ]);

        $ledger->securityDeposit($customer, $customer->security_deposit_account ?? LedgerService::BANK);

        return back()->with('success', 'Deposit approved — profile is now complete and bidding is enabled.');
    }

    /** Step 2 (alternate) — reject an incorrectly recorded deposit; agent can resubmit. */
    public function rejectDeposit(Request $request, Customer $customer)
    {
        abort_unless($request->user()->canBackdate(), 403, 'Only accountant or super admin may reject deposits.');
        abort_unless($customer->security_deposit_status === 'pending', 422, 'No pending deposit to reject.');

        $data = $request->validate(['security_deposit_rejection_reason' => ['required', 'string', 'max:500']]);

        $customer->update([
            'security_deposit_status'           => 'rejected',
            'security_deposit_rejection_reason' => $data['security_deposit_rejection_reason'],
        ]);

        return back()->with('success', 'Deposit rejected — the agent can resubmit.');
    }

    private function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:40'],
            'email'    => ['nullable', 'email', 'max:255'],
            'country'  => ['nullable', 'string', 'max:120'],
            'address'  => ['nullable', 'string'],
            'agent_id' => ['nullable', 'exists:users,id'],
            'status'   => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    private function resolveAgent(Request $request): int
    {
        $user = $request->user();
        return $user->can('customers.assign_any_agent')
            ? ($request->integer('agent_id') ?: $user->id)
            : $user->id;
    }

    private function agents(Request $request)
    {
        return $request->user()->can('customers.assign_any_agent')
            ? User::permission('scope.by_agent')->orderBy('name')->get()
            : collect();
    }
}