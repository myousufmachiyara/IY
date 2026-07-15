<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Invoice, Payment};
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, LedgerService $ledger)
    {
        $data = $request->validate([
            'customer_id'  => ['required', 'exists:customers,id'],
            'invoice_id'   => ['nullable', 'exists:invoices,id'],
            'vehicle_id'   => ['nullable', 'exists:vehicles,id'],
            'amount'       => ['required', 'integer', 'min:1'],
            'method'       => ['required', Rule::in(['cash', 'bank'])],
            'paid_at'      => ['required', 'date'],
            'reference'    => ['nullable', 'string', 'max:255'],
            'is_backdated' => ['boolean'],
        ]);

        $backdated = $request->boolean('is_backdated');
        if ($backdated) {
            abort_unless($request->user()->canBackdate(), 403, 'You are not allowed to back-date entries.');
        }

        if (! empty($data['invoice_id'])) {
            $invoice = Invoice::findOrFail($data['invoice_id']);
            abort_unless($invoice->customer_id == $data['customer_id'], 422, 'Invoice does not belong to this customer.');
            $data['vehicle_id'] = $data['vehicle_id'] ?: $invoice->vehicle_id;
        }

        DB::transaction(function () use ($data, $backdated, $request, $ledger) {
            $account = $data['method'] === 'cash' ? LedgerService::CASH : LedgerService::BANK;

            $payment = Payment::create($data + [
                'is_backdated' => $backdated,
                'account_id'   => $ledger->account($account)->id,
                'recorded_by'  => $request->user()->id,
            ]);

            $ledger->customerPayment($payment, $account);

            if ($payment->invoice_id) {
                Invoice::find($payment->invoice_id)?->refreshTotals()->save();
            }
        });

        return back()->with('success', 'Payment recorded.');
    }

    /** Modal edit form fetches this as JSON. */
    public function edit(Payment $payment)
    {
        return response()->json($payment);
    }

    /** Correcting a payment reverses its original ledger entry and posts a fresh one — never edits a posted entry in place. */
    public function update(Request $request, Payment $payment, LedgerService $ledger)
    {
        $data = $request->validate([
            'amount'    => ['required', 'integer', 'min:1'],
            'method'    => ['required', Rule::in(['cash', 'bank'])],
            'paid_at'   => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($payment, $data, $ledger) {
            foreach ($payment->journalEntries as $entry) {
                $ledger->reverseEntry($entry, now()->toDateString(), "Correction to payment #{$payment->id}");
            }

            $account = $data['method'] === 'cash' ? LedgerService::CASH : LedgerService::BANK;

            $payment->update($data + ['account_id' => $ledger->account($account)->id]);

            $ledger->customerPayment($payment->fresh(), $account);

            if ($payment->invoice_id) {
                Invoice::find($payment->invoice_id)?->refreshTotals()->save();
            }
        });

        return back()->with('success', 'Payment updated — original ledger entry reversed and reposted.');
    }

    public function destroy(Payment $payment, LedgerService $ledger)
    {
        DB::transaction(function () use ($payment, $ledger) {
            foreach ($payment->journalEntries as $entry) {
                $ledger->reverseEntry($entry, now()->toDateString(), "Reversal of deleted payment #{$payment->id}");
            }

            $invoiceId = $payment->invoice_id;
            $payment->delete();

            if ($invoiceId) {
                Invoice::find($invoiceId)?->refreshTotals()->save();
            }
        });

        return back()->with('success', 'Payment deleted and ledger entry reversed.');
    }

    public function customerLedger(Customer $customer)
    {
        $customer->load([
            'invoices' => fn ($q) => $q->latest(),
            'payments' => fn ($q) => $q->latest()->with('invoice'),
        ]);

        return view('payments.customer_ledger', compact('customer'));
    }
}