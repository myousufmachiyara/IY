<?php

namespace App\Http\Controllers;

use App\Models\{Bid, Vehicle, VehicleCosting};
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiddingResultController extends Controller
{
    /** Vehicles/bids awaiting a result. */
    public function index()
    {
        $bids = Bid::with(['customer', 'vehicle'])
            ->where('result', 'pending')
            ->latest()->paginate(20);

        return view('results.index', compact('bids'));
    }

    /**
     * Mark a bid WON: attach/convert to a vehicle, store the winning screenshot,
     * post the amount to the vendor ledger as payable, and open a costing row.
     */
    public function won(Request $request, Bid $bid, LedgerService $ledger)
    {
        $data = $request->validate([
            'vendor_id'    => ['required', 'exists:users,id'],
            'buying_price' => ['required', 'integer', 'min:1'],
            'screenshot'   => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        DB::transaction(function () use ($bid, $data, $request, $ledger) {
            // Reuse a linked vehicle or create one from the bid.
            $vehicle = $bid->vehicle ?: Vehicle::create([
                'customer_id' => $bid->customer_id,
                'agent_id'    => $bid->agent_id,
                'make'        => $bid->make, 'model' => $bid->model, 'year' => $bid->year,
                'grade'       => $bid->grade, 'chassis_no' => $bid->chassis_no,
                'budget'      => $bid->max_bid, 'created_by' => $request->user()->id,
            ]);

            $vehicle->update([
                'vendor_id'               => $data['vendor_id'],
                'buying_price'            => $data['buying_price'],
                'winning_screenshot_path' => $request->file('screenshot')->store('winning_screenshots', 'public'),
                'won_at'                  => now(),
                'status'                  => 'won',
            ]);

            $bid->update(['result' => 'won', 'won_amount' => $data['buying_price'], 'vehicle_id' => $vehicle->id]);

            // Amount hits the vendor ledger as PAYABLE (company owes vendor).
            $ledger->vendorPayable($vehicle->fresh());

            // Open a costing row pre-filled with vendor's default commission %.
            VehicleCosting::firstOrCreate(
                ['vehicle_id' => $vehicle->id],
                [
                    'buying_price'              => $vehicle->buying_price,
                    'vendor_commission_percent' => $vehicle->vendor->vendor_commission_percent ?? 7,
                ]
            );
        });

        return redirect()->route('costings.edit', $bid->vehicle_id ?? $bid->fresh()->vehicle_id)
            ->with('success', 'Bid marked won. Vendor payable posted — complete the costing next.');
    }

    public function lost(Bid $bid)
    {
        $bid->update(['result' => 'lost']);
        $bid->vehicle?->update(['status' => 'lost']);

        return back()->with('success', 'Bid marked as lost.');
    }
}