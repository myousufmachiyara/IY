<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, Vehicle};
use App\Services\{InvoiceNumber, LedgerService};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with(['customer', 'vehicle'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()->paginate(15)->withQueryString();

        return view('invoices.index', compact('invoices'));
    }

    /**
     * Generate the official invoice for a won, costed vehicle.
     * Agent requests it; accountant/admin (or the agent, per your flow) issues it.
     */
    public function store(Request $request, Vehicle $vehicle, LedgerService $ledger)
    {
        abort_unless($vehicle->isWon(), 422, 'Vehicle is not won yet.');
        abort_if($vehicle->invoice, 422, 'An invoice already exists for this vehicle.');

        $salePrice = $vehicle->selling_price ?: $vehicle->costing?->sale_price;
        abort_unless($salePrice, 422, 'Set a selling price before invoicing.');

        $invoice = DB::transaction(function () use ($vehicle, $salePrice, $request, $ledger) {
            $inv = Invoice::create([
                'invoice_no'    => InvoiceNumber::next(),
                'vehicle_id'    => $vehicle->id,
                'customer_id'   => $vehicle->customer_id,
                'agent_id'      => $vehicle->agent_id,
                'sale_price'    => $salePrice,
                'settled_amount'=> 0,
                'total_payable' => $salePrice,
                'status'        => 'issued',
                'issued_by'     => $request->user()->id,
                'issued_at'     => now(),
                // 50% due in 15 days (7 + 8 grace); final 50% set later, before arrival.
                'due_first'     => now()->addDays(15)->toDateString(),
            ]);

            $vehicle->update(['status' => 'invoiced']);
            $ledger->invoiceReceivable($inv);   // AR debit / Sales income credit

            return $inv;
        });

        return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->invoice_no} generated.");
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('vehicle.costing', 'customer', 'payments', 'agent');
        return view('invoices.show', compact('invoice'));
    }

    /** Adjust the settled amount (small discount) — admin/accountant only. */
    public function settle(Request $request, Invoice $invoice)
    {
        abort_unless($request->user()->canBackdate(), 403);

        $data = $request->validate([
            'settled_amount' => ['required', 'integer', 'min:0', "max:{$invoice->sale_price}"],
        ]);

        $invoice->settled_amount = $data['settled_amount'];
        $invoice->refreshTotals()->save();

        return back()->with('success', 'Settled amount updated.');
    }

    /** Downloadable PDF the agent shares with the customer. */
    public function pdf(Invoice $invoice)
    {
        $invoice->load('vehicle', 'customer', 'agent');
        return Pdf::loadView('invoices.pdf', compact('invoice'))
            ->download("{$invoice->invoice_no}.pdf");
    }

    /** Void an invoice that hasn't received any payments — reverses its receivable entry. */
    public function cancel(Invoice $invoice, LedgerService $ledger)
    {
        abort_unless(auth()->user()->canBackdate(), 403);
        abort_if($invoice->amount_paid > 0, 422, 'Cannot cancel an invoice with payments already recorded — use Reassign Vehicle instead.');
        abort_if($invoice->status === 'cancelled', 422, 'Invoice is already cancelled.');

        DB::transaction(function () use ($invoice, $ledger) {
            foreach ($invoice->journalEntries as $entry) {
                $ledger->reverseEntry($entry, now()->toDateString(), "Reversal — invoice {$invoice->invoice_no} cancelled");
            }

            $invoice->update(['status' => 'cancelled']);
            $invoice->vehicle->update(['status' => 'won']);
        });

        return back()->with('success', 'Invoice cancelled and receivable entry reversed.');
    }
}