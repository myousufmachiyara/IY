<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Invoice, Port, User};
use App\Services\{InvoiceNumber, LedgerService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::with('agent', 'ports', 'depositInvoice')->withCount('vehicles')
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

        if (empty($data['account_date'])) {
            $data['account_date'] = now()->toDateString();
        } elseif (! $request->user()->canBackdate() && ! \Carbon\Carbon::parse($data['account_date'])->isToday()) {
            return back()->withErrors(['account_date' => 'You do not have permission to set an account date other than today.'])->withInput();
        }

        $customer = Customer::create($data);
        $customer->ports()->sync($ports);

        app(LedgerService::class)->ensureCustomerAccount($customer);

        return back()->with('success', 'Customer created. Generate their deposit invoice to begin completing the profile.');
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
        $customer->load('agent', 'ports', 'depositReceivedBy', 'depositApprovedBy', 'depositInvoice', 'vehicles');
        return view('customers.show', compact('customer'));
    }

    public function destroy(Customer $customer)
    {
        abort_if($customer->vehicles()->exists(), 422, 'Cannot delete a customer with vehicle records. Remove their vehicles first.');
        $customer->delete();

        return back()->with('success', 'Customer removed.');
    }

    /** New workflow entry point — generates an unpaid invoice for the deposit; payment is recorded against it via the normal invoice payment flow. */
    public function generateDepositInvoice(Request $request, Customer $customer, LedgerService $ledger)
    {
        abort_if($customer->deposit_invoice_id, 422, 'A deposit invoice already exists for this customer.');

        $data = $request->validate([
            'amount'       => ['required', 'integer', 'min:1'],
            'invoice_date' => ['nullable', 'date'],
        ]);

        $date = $data['invoice_date'] ?? now()->toDateString();
        if ($date !== now()->toDateString() && ! $request->user()->canBackdate()) {
            return back()->withErrors(['invoice_date' => 'You do not have permission to set a date other than today.']);
        }

        $invoice = DB::transaction(function () use ($customer, $data, $date, $request, $ledger) {
            $inv = Invoice::create([
                'invoice_no'     => InvoiceNumber::next(),
                'invoice_type'   => 'deposit',
                'vehicle_id'     => null,
                'customer_id'    => $customer->id,
                'agent_id'       => $customer->agent_id,
                'sale_price'     => $data['amount'],
                'settled_amount' => 0,
                'total_payable'  => $data['amount'],
                'status'         => 'issued',
                'issued_by'      => $request->user()->id,
                'issued_at'      => $date,
            ]);

            $ledger->depositInvoiceReceivable($inv);
            $customer->update(['deposit_invoice_id' => $inv->id]);

            return $inv;
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Deposit invoice generated — record the customer\'s payment against it to complete their profile.');
    }

    // ================= Legacy deposit flow — preserved untouched for customers who went through it before the invoice-first workflow existed =================

    public function receiveDeposit(Request $request, Customer $customer)
    {
        abort_if($customer->security_deposit_status === 'approved', 422, 'Deposit already approved for this customer.');

        $data = $request->validate([
            'security_deposit' => ['required', 'integer', 'min:1'],
            'account'           => ['required', Rule::in([LedgerService::CASH, LedgerService::BANK])],
            'evidence'          => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'received_date'     => ['required', 'date'],
        ]);

        if (! $request->user()->canBackdate() && ! \Carbon\Carbon::parse($data['received_date'])->isToday()) {
            return back()->withErrors(['received_date' => 'You do not have permission to set a deposit-received date other than today.']);
        }

        $customer->update([
            'security_deposit'                  => $data['security_deposit'],
            'security_deposit_account'          => $data['account'],
            'security_deposit_evidence_path'    => $request->file('evidence')->store('deposit_evidence', 'public'),
            'security_deposit_status'           => 'pending',
            'security_deposit_received_by'      => $request->user()->id,
            'security_deposit_received_at'      => $data['received_date'],
            'security_deposit_rejection_reason' => null,
        ]);

        return back()->with('success', 'Deposit recorded as received — awaiting accountant approval.');
    }

    public function editDeposit(Customer $customer)
    {
        abort_if($customer->security_deposit_status === 'approved', 422, 'Approved deposits cannot be edited.');
        return response()->json($customer->only(['security_deposit', 'security_deposit_account', 'security_deposit_received_at']));
    }

    public function updateDeposit(Request $request, Customer $customer)
    {
        abort_if($customer->security_deposit_status === 'approved', 422, 'Approved deposits cannot be edited.');

        $data = $request->validate([
            'security_deposit' => ['required', 'integer', 'min:1'],
            'account'           => ['required', Rule::in([LedgerService::CASH, LedgerService::BANK])],
            'evidence'          => [$customer->security_deposit_evidence_path ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'received_date'     => ['required', 'date'],
        ]);

        if (! $request->user()->canBackdate() && ! \Carbon\Carbon::parse($data['received_date'])->isToday()) {
            return back()->withErrors(['received_date' => 'You do not have permission to set a deposit-received date other than today.']);
        }

        if ($request->hasFile('evidence')) {
            if ($customer->security_deposit_evidence_path) {
                \Storage::disk('public')->delete($customer->security_deposit_evidence_path);
            }
            $data['security_deposit_evidence_path'] = $request->file('evidence')->store('deposit_evidence', 'public');
        }

        $customer->update([
            'security_deposit'             => $data['security_deposit'],
            'security_deposit_account'     => $data['account'],
            'security_deposit_received_at' => $data['received_date'],
            'security_deposit_status'      => 'pending',
        ] + array_intersect_key($data, ['security_deposit_evidence_path' => true]));

        return back()->with('success', 'Deposit updated — pending approval again.');
    }

    public function approveDeposit(Customer $customer, LedgerService $ledger)
    {
        abort_unless(request()->user()->canApproveDeposits(), 403, 'You do not have permission to approve deposits.');
        abort_unless($customer->security_deposit_status === 'pending', 422, 'No pending deposit to approve.');
        abort_unless($customer->security_deposit_evidence_path, 422, 'Cannot approve — no evidence attached to this deposit. Edit the deposit and attach a file first.');

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
        abort_unless($request->user()->canApproveDeposits(), 403, 'You do not have permission to reject deposits.');
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
            'account_date'    => ['nullable', 'date'],
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