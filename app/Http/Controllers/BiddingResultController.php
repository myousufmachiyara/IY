<?php

namespace App\Http\Controllers;

use App\Models\{Bid, Customer, Vehicle, VehicleCosting, Vendor};
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiddingResultController extends Controller
{
    public function index()
    {
        $bids = Bid::with(['customer', 'vehicle', 'agent'])
            ->where('result', 'pending')
            ->latest()
            ->get();

        $vendors   = Vendor::active()->orderBy('name')->get();
        $customers = Customer::complete()->orderBy('name')->get();

        return view('results.index', compact('bids', 'vendors', 'customers'));
    }

    public function won(Request $request, Bid $bid, LedgerService $ledger)
    {
        abort_if(is_null($bid->customer_id), 422, 'Assign a customer to this bid before marking it won.');

        $data = $request->validate([
            'vendor_id'    => ['required', 'exists:vendors,id'],
            'buying_price' => ['required', 'integer', 'min:1'],
            'screenshot'   => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        DB::transaction(function () use ($bid, $data, $request, $ledger) {
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

            $ledger->vendorPayable($vehicle->fresh());

            VehicleCosting::firstOrCreate(
                ['vehicle_id' => $vehicle->id],
                [
                    'buying_price'              => $vehicle->buying_price,
                    'vendor_commission_percent' => $vehicle->vendor->commission_percent ?? 7,
                ]
            );
        });

        return redirect()->route('costings.edit', $bid->fresh()->vehicle_id)
            ->with('success', 'Bid marked won. Vendor payable posted — complete the costing next.');
    }

    public function lost(Bid $bid)
    {
        $bid->update(['result' => 'lost']);
        $bid->vehicle?->update(['status' => 'lost']);

        return back()->with('success', 'Bid marked as lost.');
    }
}