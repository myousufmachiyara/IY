<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Vehicle};
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleReassignController extends Controller
{
    public function reassign(Request $request, Vehicle $vehicle, LedgerService $ledger)
    {
        abort_unless($request->user()->canBackdate(), 403, 'Only accountant or super admin may reassign a vehicle.');

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id', 'different:' . $vehicle->customer_id],
            'reason'      => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($vehicle, $data, $ledger) {
            if ($invoice = $vehicle->invoice) {
                // Reverse the receivable posted when this invoice was issued — otherwise
                // a phantom AR/Sales entry survives the cancellation forever.
                foreach ($invoice->journalEntries as $entry) {
                    $ledger->reverseEntry($entry, now()->toDateString(), "Reversal — invoice {$invoice->invoice_no} cancelled on reassignment");
                }
                $invoice->update(['status' => 'cancelled']);
            }

            $newCustomer = Customer::findOrFail($data['customer_id']);

            $vehicle->update([
                'customer_id'   => $newCustomer->id,
                'agent_id'      => $newCustomer->agent_id,
                'shipment_id'   => null,
                'selling_price' => null,
                'status'        => 'won',
            ]);
        });

        return back()->with('success', 'Vehicle reassigned to new customer. Generate a fresh invoice to continue.');
    }
}