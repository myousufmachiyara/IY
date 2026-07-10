<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleReassignController extends Controller
{
    public function reassign(Request $request, Vehicle $vehicle)
    {
        abort_unless($request->user()->canBackdate(), 403, 'Only accountant or super admin may reassign a vehicle.');

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id', 'different:' . $vehicle->customer_id],
            'reason'      => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($vehicle, $data) {
            // Cancel the stale invoice — it belonged to the previous customer.
            $vehicle->invoice?->update(['status' => 'cancelled']);

            $newCustomer = \App\Models\Customer::findOrFail($data['customer_id']);

            $vehicle->update([
                'customer_id'   => $newCustomer->id,
                'agent_id'      => $newCustomer->agent_id,
                'shipment_id'   => null,
                'selling_price' => null,
                'status'        => 'won', // back to "won, needs invoice" for the new customer
            ]);
        });

        return back()->with('success', 'Vehicle reassigned to new customer. Generate a fresh invoice to continue.');
    }
}