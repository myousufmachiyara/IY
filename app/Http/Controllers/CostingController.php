<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;

class CostingController extends Controller
{
    public function edit(Vehicle $vehicle)
    {
        abort_unless($vehicle->isWon(), 422, 'Costing is only available for won vehicles.');
        $vehicle->load('costing', 'customer', 'vendor', 'agent');

        return view('costings.edit', compact('vehicle'));
    }

    /** Cost inputs — restricted to Super Admin / Accountant. */
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

        $costing = $vehicle->costing;
        $costing->fill($data);
        $costing->buying_price = $vehicle->buying_price;
        $costing->prepared_by  = $request->user()->id;

        // Recompute totals/profit using the agent's commission % and fixed bonus.
        $costing->recalculate(
            $vehicle->agent->sales_commission_percent ?? 15,
            (int) ($vehicle->agent->sales_fixed_bonus ?? 0),
        )->save();

        return back()->with('success', 'Costing updated. Profit and agent earning recalculated.');
    }

    /** Selling price — set by the owning sales agent (or admin). */
    public function updateSellingPrice(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate(['selling_price' => ['required', 'integer', 'min:1']]);

        $vehicle->update(['selling_price' => $data['selling_price']]);

        // Keep the costing's sale_price aligned to the agent-set figure, then re-derive profit.
        $costing = $vehicle->costing;
        $costing->sale_price = $data['selling_price'];
        $costing->profit     = $costing->sale_price - $costing->total_costing;
        $costing->agent_commission_amount =
            (int) round(max($costing->profit, 0) * (($vehicle->agent->sales_commission_percent ?? 15) / 100));
        $costing->save();

        return back()->with('success', 'Selling price saved.');
    }
}