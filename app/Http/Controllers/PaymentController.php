<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Invoice, Payment};
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::with(['customer', 'invoice', 'vehicle', 'recorder', 'approver'])
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->method, fn ($q, $v) => $q->where('method', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('paid_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('paid_at', '<=', $v))
            ->latest('paid_at')->get();

        $customers = Customer::orderBy('name')->get();
        return view('payments.index', compact('payments', 'customers'));
    }

    public function store(Request $request, LedgerService $ledger)
    {
        $autoApprove = $request->user()->canBackdate(); // approval authority — unrelated to the date-entry rule below

        $data = $request->validate([
            'customer_id'  => ['required', 'exists:customers,id'],
            'invoice_id'   => ['nullable', 'exists:invoices,id'],
            'vehicle_id'   => ['nullable', 'exists:vehicles,id'],
            'amount'       => ['required', 'integer', 'min:1'],
            'method'       => ['required', Rule::in(['cash', 'bank'])],
            'paid_at'      => ['required', 'date'],
            'reference'    => ['nullable', 'string', 'max:255'],
            'attachment'   => [$autoApprove ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        // #3 — only Super Admin may enter a payment date other than today. Accountant and
        // sales agent are locked to today regardless of approval authority elsewhere.
        if (! $request->user()->isSuperAdmin() && ! \Carbon\Carbon::parse($data['paid_at'])->isToday()) {
            return back()->withErrors(['paid_at' => 'Only Super Admin may record a payment with a date other than today.'])->withInput();
        }

        if (! empty($data['invoice_id'])) {
            $invoice = Invoice::findOrFail($data['invoice_id']);
            abort_unless($invoice->customer_id == $data['customer_id'], 422, 'Invoice does not belong to this customer.');
            $data['vehicle_id'] = $data['vehicle_id'] ?: $invoice->vehicle_id;
        }

        $backdated = \Carbon\Carbon::parse($data['paid_at'])->lt(today());

        DB::transaction(function () use ($data, $backdated, $request, $ledger, $autoApprove) {
            $account = $data['method'] === 'cash' ? LedgerService::CASH : LedgerService::BANK;

            $payment = Payment::create([
                'customer_id'     => $data['customer_id'],
                'invoice_id'      => $data['invoice_id'] ?? null,
                'vehicle_id'      => $data['vehicle_id'] ?? null,
                'amount'          => $data['amount'],
                'method'          => $data['method'],
                'paid_at'         => $data['paid_at'],
                'reference'       => $data['reference'] ?? null,
                'attachment_path' => $request->hasFile('attachment') ? $request->file('attachment')->store('payment_attachments', 'public') : null,
                'is_backdated'    => $backdated,
                'account_id'      => $ledger->account($account)->id,
                'recorded_by'     => $request->user()->id,
                'status'          => $autoApprove ? 'approved' : 'pending',
                'approved_by'     => $autoApprove ? $request->user()->id : null,
                'approved_at'     => $autoApprove ? now() : null,
            ]);

            if ($autoApprove) {
                $ledger->customerPayment($payment, $account);
                if ($payment->invoice_id) {
                    Invoice::find($payment->invoice_id)?->refreshTotals()->save();
                }
            }
        });

        return back()->with('success', $autoApprove
            ? 'Payment recorded.'
            : 'Payment submitted — awaiting accountant approval before it counts toward the invoice balance.');
    }

    public function approve(Payment $payment, LedgerService $ledger)
    {
        abort_unless(auth()->user()->canBackdate(), 403);
        abort_unless($payment->status === 'pending', 422, 'This payment is not awaiting approval.');

        DB::transaction(function () use ($payment, $ledger) {
            $account = $payment->method === 'cash' ? LedgerService::CASH : LedgerService::BANK;
            $payment->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $ledger->customerPayment($payment->fresh(), $account);
            if ($payment->invoice_id) {
                Invoice::find($payment->invoice_id)?->refreshTotals()->save();
            }
        });

        return back()->with('success', 'Payment approved and posted to the ledger.');
    }

    public function reject(Request $request, Payment $payment)
    {
        abort_unless(auth()->user()->canBackdate(), 403);
        abort_unless($payment->status === 'pending', 422, 'This payment is not awaiting approval.');

        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);
        $payment->update(['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']]);

        return back()->with('success', 'Payment rejected — the agent can correct and resubmit.');
    }

    public function edit(Payment $payment)
    {
        return response()->json($payment);
    }

    public function update(Request $request, Payment $payment, LedgerService $ledger)
    {
        abort_if(
            $payment->invoice?->vehicle?->documents()->where('is_final_clearance', true)->where('visible_to_customer', true)->exists(),
            422, 'Payments cannot be changed after the final clearance document has been released.'
        );

        $data = $request->validate([
            'amount'    => ['required', 'integer', 'min:1'],
            'method'    => ['required', Rule::in(['cash', 'bank'])],
            'paid_at'   => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $request->user()->isSuperAdmin() && ! \Carbon\Carbon::parse($data['paid_at'])->isToday()) {
            return back()->withErrors(['paid_at' => 'Only Super Admin may set a payment date other than today.'])->withInput();
        }

        DB::transaction(function () use ($payment, $data, $ledger) {
            $account = $data['method'] === 'cash' ? LedgerService::CASH : LedgerService::BANK;

            if ($payment->status === 'approved') {
                foreach ($payment->journalEntries as $entry) {
                    $ledger->reverseEntry($entry, now()->toDateString(), "Correction to payment #{$payment->id}");
                }
                $payment->update($data + ['account_id' => $ledger->account($account)->id]);
                $ledger->customerPayment($payment->fresh(), $account);
                if ($payment->invoice_id) {
                    Invoice::find($payment->invoice_id)?->refreshTotals()->save();
                }
            } else {
                $payment->update($data + ['account_id' => $ledger->account($account)->id, 'status' => 'pending', 'rejection_reason' => null]);
            }
        });

        return back()->with('success', 'Payment updated.');
    }

    public function destroy(Payment $payment, LedgerService $ledger)
    {
        abort_if(
            $payment->invoice?->vehicle?->documents()->where('is_final_clearance', true)->where('visible_to_customer', true)->exists(),
            422, 'Payments cannot be changed after the final clearance document has been released.'
        );

        DB::transaction(function () use ($payment, $ledger) {
            if ($payment->status === 'approved') {
                foreach ($payment->journalEntries as $entry) {
                    $ledger->reverseEntry($entry, now()->toDateString(), "Reversal of deleted payment #{$payment->id}");
                }
            }
            $invoiceId = $payment->invoice_id;
            if ($payment->attachment_path) {
                \Storage::disk('public')->delete($payment->attachment_path);
            }
            $payment->delete();
            if ($invoiceId) {
                Invoice::find($invoiceId)?->refreshTotals()->save();
            }
        });

        return back()->with('success', 'Payment deleted.');
    }

    public function customerLedger(Customer $customer)
    {
        $customer->load(['invoices' => fn ($q) => $q->latest(), 'payments' => fn ($q) => $q->latest()->with('invoice', 'approver')]);
        return view('payments.customer_ledger', compact('customer'));
    }

    /** Super Admin can revert an already-approved payment back to pending, reversing its ledger post. */
    public function undoApproval(Payment $payment, LedgerService $ledger)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403, 'Only Super Admin may undo an approved payment.');
        abort_unless($payment->status === 'approved', 422, 'This payment is not currently approved.');

        DB::transaction(function () use ($payment, $ledger) {
            foreach ($payment->journalEntries as $entry) {
                $ledger->reverseEntry($entry, now()->toDateString(), "Reversal — payment #{$payment->id} approval undone");
            }
            $payment->update(['status' => 'pending', 'approved_by' => null, 'approved_at' => null]);
            if ($payment->invoice_id) {
                Invoice::find($payment->invoice_id)?->refreshTotals()->save();
            }
        });

        return back()->with('success', 'Payment approval undone — reverted to pending.');
    }
}