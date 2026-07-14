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

        $customer = Customer::create($data);

        // Existing customers have no deposit gate, so their profile is complete
        // immediately. New customers complete automatically once the deposit is
        // recorded — see payDeposit() below.
        if (! $customer->is_new_customer) {
            $customer->update(['profile_completed_at' => now()]);
        }

        return back()->with('success', 'Customer created.');
    }

    public function payDeposit(Request $request, Customer $customer, LedgerService $ledger)
    {
        abort_unless($customer->is_new_customer, 422, 'Security deposit applies to new customers only.');
        abort_if($customer->security_deposit_paid, 422, 'Deposit already recorded for this customer.');

        $data = $request->validate([
            'security_deposit' => ['required', 'integer', 'min:1'],
            'account'           => ['required', Rule::in([LedgerService::CASH, LedgerService::BANK])],
        ]);

        $customer->update([
            'security_deposit'      => $data['security_deposit'],
            'security_deposit_paid' => true,
            'profile_completed_at'  => now(), // deposit paid completes a new customer's profile immediately
        ]);

        $ledger->securityDeposit($customer, $data['account']);

        return back()->with('success', 'Security deposit recorded — profile is now complete and bidding is enabled.');
    }

    /** Modal edit form fetches this as JSON. */
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

    public function destroy(Customer $customer)
    {
        abort_if($customer->vehicles()->exists(), 422, 'Cannot delete a customer with vehicle records. Remove their vehicles first.');
        $customer->delete();

        return back()->with('success', 'Customer removed.');
    }

    public function show(Customer $customer)
    {
        $customer->load('agent');
        return view('customers.show', compact('customer'));
    }

    /** The bidding gate: mark a profile complete once its prerequisites are met. */
    public function completeProfile(Customer $customer)
    {
        if ($customer->is_new_customer && ! $customer->security_deposit_paid) {
            return back()->with('error', 'Security deposit must be paid before completing a new customer profile.');
        }
        if (! $customer->vehicles()->exists()) {
            return back()->with('error', 'Add at least one vehicle requirement before completing the profile.');
        }

        $customer->update(['profile_completed_at' => now()]);

        return back()->with('success', 'Profile completed — bidding is now enabled for this customer.');
    }

    // ---------- helpers ----------

    private function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:40'],
            'email'           => ['nullable', 'email', 'max:255'],
            'country'         => ['nullable', 'string', 'max:120'],
            'address'         => ['nullable', 'string'],
            'is_new_customer' => ['required', 'boolean'],
            'agent_id'        => ['nullable', 'exists:users,id'],
            'status'          => ['required', Rule::in(['active', 'inactive'])],
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