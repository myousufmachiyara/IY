<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Invoice, Vehicle};
use App\Services\{InvoiceNumber, LedgerService};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with(['customer', 'vehicle', 'agent'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $v) => $q->where(fn ($w) =>
                $w->where('invoice_no', 'like', "%{$v}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$v}%"))))
            ->when($request->from, fn ($q, $v) => $q->whereDate('issued_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('issued_at', '<=', $v))
            ->latest()->get();

        return view('invoices.index', compact('invoices'));
    }

    public function store(Request $request, Vehicle $vehicle, LedgerService $ledger)
    {
        abort_unless($vehicle->isWon(), 422, 'Vehicle is not won yet.');
        abort_if($vehicle->invoice, 422, 'An invoice already exists for this vehicle.');

        $salePrice = $vehicle->selling_price ?: $vehicle->costing?->sale_price;
        abort_unless($salePrice, 422, 'Set a selling price before invoicing.');

        $invoice = DB::transaction(function () use ($vehicle, $salePrice, $request, $ledger) {
            $inv = Invoice::create([
                'invoice_no' => InvoiceNumber::next(), 'vehicle_id' => $vehicle->id,
                'customer_id' => $vehicle->customer_id, 'agent_id' => $vehicle->agent_id,
                'sale_price' => $salePrice, 'settled_amount' => 0, 'total_payable' => $salePrice,
                'status' => 'issued', 'issued_by' => $request->user()->id, 'issued_at' => now(),
                'due_first' => now()->addDays(15)->toDateString(),
            ]);
            $vehicle->update(['status' => 'invoiced']);
            $ledger->invoiceReceivable($inv);
            return $inv;
        });

        return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->invoice_no} generated.");
    }

    /** #9 — bulk-generate invoices for every eligible won-but-uninvoiced vehicle belonging to one customer. */
    public function bulkCreateForm(Customer $customer)
    {
        $eligible = $customer->vehicles()
            ->where('status', 'won')->whereDoesntHave('invoice')
            ->with('costing')->get()
            ->filter(fn ($v) => $v->selling_price || $v->costing?->sale_price);

        abort_if($eligible->isEmpty(), 422, 'This customer has no vehicles eligible for invoicing.');

        return view('invoices.bulk_create', compact('customer', 'eligible'));
    }

    public function bulkStore(Request $request, Customer $customer, LedgerService $ledger)
    {
        $data = $request->validate(['vehicle_ids' => ['required', 'array', 'min:1'], 'vehicle_ids.*' => ['exists:vehicles,id']]);

        $created = 0;
        DB::transaction(function () use ($data, $customer, $request, $ledger, &$created) {
            foreach ($data['vehicle_ids'] as $vehicleId) {
                $vehicle = Vehicle::where('customer_id', $customer->id)->findOrFail($vehicleId);
                if ($vehicle->invoice || $vehicle->status !== 'won') continue;

                $salePrice = $vehicle->selling_price ?: $vehicle->costing?->sale_price;
                if (! $salePrice) continue;

                $inv = Invoice::create([
                    'invoice_no' => InvoiceNumber::next(), 'vehicle_id' => $vehicle->id,
                    'customer_id' => $vehicle->customer_id, 'agent_id' => $vehicle->agent_id,
                    'sale_price' => $salePrice, 'settled_amount' => 0, 'total_payable' => $salePrice,
                    'status' => 'issued', 'issued_by' => $request->user()->id, 'issued_at' => now(),
                    'due_first' => now()->addDays(15)->toDateString(),
                ]);
                $vehicle->update(['status' => 'invoiced']);
                $ledger->invoiceReceivable($inv);
                $created++;
            }
        });

        return redirect()->route('customers.show', $customer)->with('success', "{$created} invoice(s) generated.");
    }

    public function show(Request $request, Invoice $invoice)
    {
        $invoice->load('vehicle.costing', 'vehicle.shipment', 'customer', 'payments.recorder', 'agent');
        $customers = Customer::where('id', '!=', $invoice->customer_id)->orderBy('name')->get();
        return view('invoices.show', compact('invoice', 'customers'));
    }

    public function settle(Request $request, Invoice $invoice)
    {
        abort_unless($request->user()->canBackdate(), 403);
        $data = $request->validate(['settled_amount' => ['required', 'integer', 'min:0', "max:{$invoice->sale_price}"]]);
        $invoice->settled_amount = $data['settled_amount'];
        $invoice->refreshTotals()->save();
        return back()->with('success', 'Settled amount updated.');
    }

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

    /** #8 — hard delete a genuinely mistaken invoice. Only when nothing has been paid on it. */
    public function destroy(Invoice $invoice, LedgerService $ledger)
    {
        abort_unless(auth()->user()->canBackdate(), 403);
        abort_if($invoice->amount_paid > 0, 422, 'Cannot delete an invoice with payments already recorded — use Reassign Vehicle or contact Super Admin.');

        DB::transaction(function () use ($invoice, $ledger) {
            foreach ($invoice->journalEntries as $entry) {
                $ledger->reverseEntry($entry, now()->toDateString(), "Reversal — invoice {$invoice->invoice_no} deleted");
            }
            $vehicle = $invoice->vehicle;
            $invoice->delete();
            $vehicle?->update(['status' => 'won']);
        });

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted and receivable entry reversed.');
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->load('vehicle', 'customer', 'agent');

        return Pdf::loadView('invoices.print', [
            'type'         => 'cnf',
            'invoice_no'   => $invoice->invoice_no,
            'date'         => optional($invoice->issued_at)->format('d/m/Y') ?? now()->format('d/m/Y'),
            'customer'     => $invoice->customer,
            'vehicle'      => $invoice->vehicle,
            'amount_label' => '100% CNF PRICE',
            'total_label'  => '100% C&F AMOUNT',
            'amount'       => $invoice->total_payable,
        ])->download("{$invoice->invoice_no}.pdf");
    }

    public function mergeSelectForm(Customer $customer)
    {
        $invoices = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['issued', 'partial', 'paid'])
            ->with('vehicle')->latest()->get();

        abort_if($invoices->isEmpty(), 422, 'This customer has no issued invoices yet.');

        return view('invoices.merge_select', compact('customer', 'invoices'));
    }

    /** Merge any selection of existing invoices into one PDF — not tied to a single customer. */
    public function mergeSelectedPdf(Request $request)
    {
        $data = $request->validate([
            'invoice_ids'   => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['exists:invoices,id'],
        ]);

        $invoices = Invoice::whereIn('id', $data['invoice_ids'])
            ->with('vehicle.bid', 'customer')
            ->orderBy('issued_at')
            ->get();

        abort_if($invoices->isEmpty(), 422, 'No matching invoices found.');

        return Pdf::loadView('invoices.merged', compact('invoices'))
            ->download('IY-Merged-Invoices-' . now()->format('Y-m-d') . '.pdf');
    }

    public function mergePdf(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'invoice_ids'   => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['exists:invoices,id'],
        ]);

        $invoices = Invoice::whereIn('id', $data['invoice_ids'])
            ->where('customer_id', $customer->id)
            ->with('vehicle', 'customer')
            ->orderBy('issued_at')
            ->get();

        abort_if($invoices->isEmpty(), 422, 'No matching invoices found for this customer.');

        return Pdf::loadView('invoices.merged', compact('invoices'))
            ->download('IY-Merged-Invoices-' . \Illuminate\Support\Str::slug($customer->name) . '.pdf');
    }
}