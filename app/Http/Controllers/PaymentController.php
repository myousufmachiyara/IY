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
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_id'  => ['nullable', 'exists:invoices,id'],
            'vehicle_id'  => ['nullable', 'exists:vehicles,id'],
            'amount'      => ['required', 'integer', 'min:1'],
            'method'      => ['required', Rule::in(['cash', 'bank'])],
            'paid_at'     => ['required', 'date'],
            'reference'   => ['nullable', 'string', 'max:255'],
            'is_backdated'=> ['boolean'],
        ]);

        // Back-dating is restricted to super admin / accountant.
        $backdated = $request->boolean('is_backdated');
        if ($backdated) {
            abort_unless($request->user()->canBackdate(), 403, 'You are not allowed to back-date entries.');
        }

        DB::transaction(function () use ($data, $backdated, $request, $ledger) {
            $account = $data['method'] === 'cash' ? LedgerService::CASH : LedgerService::BANK;

            $payment = Payment::create($data + [
                'is_backdated' => $backdated,
                'account_id'   => $ledger->account($account)->id,
                'recorded_by'  => $request->user()->id,
            ]);

            $ledger->customerPayment($payment, $account);

            // Keep the linked invoice's paid total / status current.
            if ($payment->invoice_id) {
                Invoice::find($payment->invoice_id)?->refreshTotals()->save();
            }
        });

        return back()->with('success', 'Payment recorded.');
    }

    /** Ledger-style history for a customer. */
    public function customerLedger(Customer $customer)
    {
        $customer->load(['invoices', 'payments.invoice']);
        return view('payments.customer_ledger', compact('customer'));
    }
}