<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Invoice, Payment};
use App\Rules\MoneyAccount;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $payments = Payment::with('customer', 'invoice', 'recorder', 'account')
            ->when(! $user->can('data.view_all'), fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('agent_id', $user->id)))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->method, fn ($q, $v) => $q->where('method', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('paid_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('paid_at', '<=', $v))
            ->latest('paid_at')->get();

        $customers = $user->can('data.view_all')
            ? Customer::orderBy('name')->get()
            : Customer::where('agent_id', $user->id)->orderBy('name')->get();

        return view('payments.index', compact('payments', 'customers'));
    }

    public function store(Request $request, LedgerService $ledger)
    {
        // Whoever holds payments.approve has their own entry posted straight away;
        // everyone else's payment waits as 'pending' for an approver.
        $autoApprove = $request->user()->can('payments.approve');

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_id'  => ['nullable', 'exists:invoices,id'],
            'vehicle_id'  => ['nullable', 'exists:vehicles,id'],
            'amount'      => ['required', 'integer', 'min:1'],
            'account_id'  => ['required', new MoneyAccount],
            'paid_at'     => ['required', 'date'],
            'reference'   => ['nullable', 'string', 'max:255'],
            'attachment'  => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if (! $request->user()->canBackdate() && ! \Carbon\Carbon::parse($data['paid_at'])->isToday()) {
            return back()->withErrors(['paid_at' => 'You do not have permission to record a payment with a date other than today.'])->withInput();
        }

        $account = $ledger->moneyAccount($data['account_id']);

        DB::transaction(function () use ($data, $autoApprove, $request, $ledger, $account) {
            $payment = Payment::create([
                'customer_id'     => $data['customer_id'],
                'invoice_id'      => $data['invoice_id'] ?? null,
                'vehicle_id'      => $data['vehicle_id'] ?? null,
                'amount'          => $data['amount'],
                'method'          => $account->moneyKind(),
                'account_id'      => $account->id,
                'paid_at'         => $data['paid_at'],
                'reference'       => $data['reference'] ?? null,
                'attachment_path' => $request->file('attachment')->store('payment_attachments', 'public'),
                'is_backdated'    => ! \Carbon\Carbon::parse($data['paid_at'])->isToday(),
                'recorded_by'     => $request->user()->id,
                'status'          => $autoApprove ? 'approved' : 'pending',
                'approved_by'     => $autoApprove ? $request->user()->id : null,
                'approved_at'     => $autoApprove ? now() : null,
            ]);

            if ($autoApprove) {
                $ledger->customerPayment($payment);
                $this->settleInvoice($payment->invoice_id, $ledger, $payment->paid_at->toDateString());
            }
        });

        return back()->with('success', $autoApprove ? 'Payment recorded and posted.' : 'Payment submitted — awaiting approval.');
    }

    public function approve(Payment $payment, LedgerService $ledger)
    {
        abort_unless(auth()->user()->can('payments.approve'), 403);
        abort_unless($payment->status === 'pending', 422, 'This payment is not pending.');

        DB::transaction(function () use ($payment, $ledger) {
            $payment->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $ledger->customerPayment($payment->fresh());
            $this->settleInvoice($payment->invoice_id, $ledger, $payment->paid_at->toDateString());
        });

        return back()->with('success', 'Payment approved and posted.');
    }

    /** Reject a pending payment with a required reason — visible on the invoice/payment list afterward. */
    public function reject(Request $request, Payment $payment)
    {
        abort_unless(auth()->user()->can('payments.approve'), 403);
        abort_unless($payment->status === 'pending', 422, 'This payment is not pending.');

        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);
        $payment->update(['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']]);

        return back()->with('success', 'Payment rejected.');
    }

    public function undoApproval(Payment $payment, LedgerService $ledger)
    {
        abort_unless(auth()->user()->can('payments.reverse'), 403, 'You do not have permission to reverse an approved payment.');
        abort_unless($payment->status === 'approved', 422, 'This payment is not currently approved.');
        abort_if($payment->method === 'deposit', 422, 'A deposit adjustment is undone from the invoice page, not here.');

        DB::transaction(function () use ($payment, $ledger) {
            foreach ($payment->journalEntries as $entry) {
                $ledger->reverseEntry($entry, now()->toDateString(), "Reversal — payment #{$payment->id} approval undone");
            }
            $payment->update(['status' => 'pending', 'approved_by' => null, 'approved_at' => null]);
            $this->settleInvoice($payment->invoice_id, $ledger);
        });

        return back()->with('success', 'Payment approval undone — reverted to pending.');
    }

    public function edit(Payment $payment) { return response()->json($payment); }

    public function update(Request $request, Payment $payment, LedgerService $ledger)
    {
        abort_if($payment->method === 'deposit', 422, 'A deposit adjustment cannot be edited — undo it from the invoice page instead.');
        abort_if(
            $payment->invoice?->vehicle?->documents()->where('is_final_clearance', true)->where('visible_to_customer', true)->exists(),
            422, 'Payments cannot be changed after the final clearance document has been released.'
        );

        $data = $request->validate([
            'amount'     => ['required', 'integer', 'min:1'],
            'account_id' => ['required', new MoneyAccount],
            'paid_at'    => ['required', 'date'],
            'reference'  => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if (! $request->user()->canBackdate() && ! \Carbon\Carbon::parse($data['paid_at'])->isToday()) {
            return back()->withErrors(['paid_at' => 'You do not have permission to set a payment date other than today.'])->withInput();
        }

        if ($request->hasFile('attachment')) {
            abort_if($payment->status === 'approved', 422, 'Cannot replace the attachment on an already-approved payment.');
            if ($payment->attachment_path) {
                \Storage::disk('public')->delete($payment->attachment_path);
            }
            $data['attachment_path'] = $request->file('attachment')->store('payment_attachments', 'public');
        }
        unset($data['attachment']);

        if ($payment->status === 'approved') {
            abort_unless(auth()->user()->can('payments.reverse'), 403, 'Correcting an already-approved payment reverses and reposts its ledger entry — you need the "Reverse/Void Customer Payment" permission for that.');
        }

        $account = $ledger->moneyAccount($data['account_id']);
        $data['account_id'] = $account->id;
        $data['method'] = $account->moneyKind();

        DB::transaction(function () use ($payment, $data, $ledger) {
            if ($payment->status === 'approved') {
                foreach ($payment->journalEntries as $entry) {
                    $ledger->reverseEntry($entry, now()->toDateString(), "Correction to payment #{$payment->id}");
                }
                $payment->update($data);
                $ledger->customerPayment($payment->fresh());
                $this->settleInvoice($payment->invoice_id, $ledger, $payment->fresh()->paid_at->toDateString());
            } else {
                $payment->update($data + ['status' => 'pending', 'rejection_reason' => null]);
            }
        });

        return back()->with('success', 'Payment updated.');
    }

    public function destroy(Payment $payment, LedgerService $ledger)
    {
        abort_if($payment->method === 'deposit', 422, 'A deposit adjustment cannot be deleted — undo it from the invoice page instead.');

        // Deleting an approved (posted) payment reverses its ledger entry, so it needs the
        // reversal permission on top of the route's plain payments.delete.
        if ($payment->status === 'approved') {
            abort_unless(auth()->user()->can('payments.reverse'), 403, 'You do not have permission to reverse/void a customer payment.');
        }

        DB::transaction(function () use ($payment, $ledger) {
            if ($payment->status === 'approved') {
                foreach ($payment->journalEntries as $entry) {
                    $ledger->reverseEntry($entry, now()->toDateString(), "Reversal of deleted payment #{$payment->id}");
                }
            }
            $invoiceId = $payment->invoice_id;
            $payment->delete();
            $this->settleInvoice($invoiceId, $ledger);
        });

        return back()->with('success', 'Payment deleted.');
    }

    public function customerLedger(Customer $customer)
    {
        $payments = $customer->payments()->with('invoice', 'recorder', 'account')->latest('paid_at')->get();
        return view('payments.customer_ledger', compact('customer', 'payments'));
    }

    /** Refresh an invoice's paid totals and bring recognised revenue in line with money actually received. */
    private function settleInvoice(?int $invoiceId, LedgerService $ledger, ?string $date = null): void
    {
        if (! $invoiceId || ! ($invoice = Invoice::find($invoiceId))) {
            return;
        }

        $invoice->refreshTotals()->save();
        $ledger->syncRevenueRecognition($invoice->fresh(), $date);
    }
}
