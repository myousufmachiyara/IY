<?php

namespace App\Http\Controllers;

use App\Models\{Vehicle, VehicleCosting};
use Illuminate\Http\Request;

class CostingController extends Controller
{
    public function show(Vehicle $vehicle)
    {
        abort_unless($vehicle->isWon(), 422, 'Costing is only available for won vehicles.');
        $vehicle->load('customer', 'vendor', 'agent');

        $costing = $vehicle->costing ?: $this->ensureCosting($vehicle);

        return view('costings.edit', compact('vehicle', 'costing'));
    }

    public function updateCosting(Request $request, Vehicle $vehicle)
    {
        abort_unless($request->user()->canBackdate(), 403, 'Only accountant or super admin may edit costing.');

        $data = $request->validate([
            'vendor_commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'inland_charges'            => ['required', 'integer', 'min:0'],
            'auction_commission'        => ['required', 'integer', 'min:0'],
            'freight_charges'           => ['required', 'integer', 'min:0'],
            'misc_expenses'             => ['required', 'integer', 'min:0'],
        ]);

        $costing = $vehicle->costing ?: $this->ensureCosting($vehicle);
        $costing->fill($data);
        $costing->buying_price = $vehicle->buying_price;
        $costing->prepared_by  = $request->user()->id;

        $costing->recalculate(
            $vehicle->selling_price,
            $vehicle->agent->sales_commission_percent ?? 15,
            (int) ($vehicle->agent->sales_fixed_bonus ?? 0)
        )->save();

        return back()->with('success', 'Costing updated. Profit and agent earning recalculated.');
    }

    public function updateSellingPrice(Request $request, Vehicle $vehicle)
    {
        $costing = $vehicle->costing ?: $this->ensureCosting($vehicle);

        // Floor is the Cost Price for Agent — except Super Admin, who may override
        // and set a selling price below it (e.g. a deliberate loss-leader sale).
        $minPrice = $request->user()->isSuperAdmin() ? 1 : $costing->sale_price;

        $data = $request->validate([
            'selling_price' => ['required', 'integer', "min:{$minPrice}"],
        ]);

        $vehicle->update(['selling_price' => $data['selling_price']]);

        $costing->recalculate(
            $data['selling_price'],
            $vehicle->agent->sales_commission_percent ?? 15,
            (int) ($vehicle->agent->sales_fixed_bonus ?? 0)
        )->save();

        return back()->with('success', 'Selling price saved.');
    }

    private function ensureCosting(Vehicle $vehicle): VehicleCosting
    {
        $costing = VehicleCosting::create([
            'vehicle_id'                => $vehicle->id,
            'buying_price'              => $vehicle->buying_price,
            'vendor_commission_percent' => $vehicle->vendor->commission_percent ?? 7,
        ]);

        $costing->recalculate(
            $vehicle->selling_price,
            $vehicle->agent->sales_commission_percent ?? 15,
            (int) ($vehicle->agent->sales_fixed_bonus ?? 0)
        );
        $costing->save();

        return $costing;
    }
}