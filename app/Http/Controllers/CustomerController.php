<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Port, User};
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::with('agent', 'ports')->withCount('vehicles')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->deposit_status, fn ($q) => $q->where('security_deposit_status', $request->deposit_status))
            ->when($request->agent_id, fn ($q) => $q->where('agent_id', $request->agent_id))
            ->when($request->country, fn ($q) => $q->where('country', 'like', "%{$request->country}%"))
            ->latest()->get();

        $agents = $this->agents($request);
        $ports  = Port::active()->orderBy('name')->get();

        return view('customers.index', compact('customers', 'agents', 'ports'));
    }

    public function store(Request $request)
    {
        $data  = $request->validate($this->rules());
        $ports = $data['ports'];
        unset($data['ports']);

        $data['agent_id']   = $this->resolveAgent($request);
        $data['created_by'] = $request->user()->id;

        $customer = Customer::create($data);
        $customer->ports()->sync($ports);

        return back()->with('success', 'Customer created. Record their security deposit to complete the profile.');
    }

    public function edit(Customer $customer)
    {
        return response()->json([
            ...$customer->toArray(),
            'port_ids' => $customer->ports()->pluck('ports.id'),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $data  = $request->validate($this->rules());
        $ports = $data['ports'];
        unset($data['ports']);

        if (! $request->user()->can('customers.assign_any_agent')) {
            unset($data['agent_id']);
        }

        $customer->update($data);
        $customer->ports()->sync($ports);

        return back()->with('success', 'Customer updated.');
    }

    public function show(Customer $customer)
    {
        $customer->load('agent', 'ports', 'depositReceivedBy', 'depositApprovedBy');
        return view('customers.show', compact('customer'));
    }

    public function destroy(Customer $customer)
    {
        abort_if($customer->vehicles()->exists(), 422, 'Cannot delete a customer with vehicle records. Remove their vehicles first.');
        $customer->delete();

        return back()->with('success', 'Customer removed.');
    }
    
    public function receiveDeposit(Request $request, Customer $customer)
    {
        abort_if($customer->security_deposit_status === 'approved', 422, 'Deposit already approved for this customer.');

        $data = $request->validate([
            'security_deposit' => ['required', 'integer', 'min:1'],
            'account'           => ['required', Rule::in([LedgerService::CASH, LedgerService::BANK])],
            'evidence'          => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $customer->update([
            'security_deposit'                  => $data['security_deposit'],
            'security_deposit_account'          => $data['account'],
            'security_deposit_evidence_path'    => $request->file('evidence')->store('deposit_evidence', 'public'),
            'security_deposit_status'           => 'pending',
            'security_deposit_received_by'      => $request->user()->id,
            'security_deposit_received_at'      => now(),
            'security_deposit_rejection_reason' => null,
        ]);

        return back()->with('success', 'Deposit recorded as received — awaiting accountant approval.');
    }

    /** #11 — agent can correct a not-yet-approved deposit. */
    public function editDeposit(Customer $customer)
    {
        abort_if($customer->security_deposit_status === 'approved', 422, 'Approved deposits cannot be edited.');
        return response()->json($customer->only(['security_deposit', 'security_deposit_account']));
    }

    public function updateDeposit(Request $request, Customer $customer)
    {
        abort_if($customer->security_deposit_status === 'approved', 422, 'Approved deposits cannot be edited.');

        $data = $request->validate([
            'security_deposit' => ['required', 'integer', 'min:1'],
            'account'           => ['required', Rule::in([LedgerService::CASH, LedgerService::BANK])],
            'evidence'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($request->hasFile('evidence')) {
            if ($customer->security_deposit_evidence_path) {
                \Storage::disk('public')->delete($customer->security_deposit_evidence_path);
            }
            $data['security_deposit_evidence_path'] = $request->file('evidence')->store('deposit_evidence', 'public');
        }

        $customer->update([
            'security_deposit'         => $data['security_deposit'],
            'security_deposit_account' => $data['account'],
            'security_deposit_status'  => 'pending', // resets to pending — a correction needs re-approval
        ] + array_intersect_key($data, ['security_deposit_evidence_path' => true]));

        return back()->with('success', 'Deposit updated — pending approval again.');
    }
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
            'name'            => ['required', 'string', 'max:255'],
            'phone'           => ['required', 'string', 'max:40'],
            'email'           => ['required', 'email', 'max:255'],
            'country'         => ['required', 'string', 'max:120'],
            'postal_code'     => ['required', 'string', 'max:20'],
            'address'         => ['required', 'string'],
            'consignee_name'  => ['required', 'string', 'max:255'],
            'agent_id'        => ['nullable', 'exists:users,id'],
            'status'          => ['required', Rule::in(['active', 'inactive'])],
            'ports'           => ['required', 'array', 'min:1'],
            'ports.*'         => ['exists:ports,id'],
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