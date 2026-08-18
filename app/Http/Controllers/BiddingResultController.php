<?php

namespace App\Http\Controllers;

use App\Models\{Bid, Customer, JournalEntry, User, Vehicle, VehicleCosting, Vendor};
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiddingResultController extends Controller
{
    public function index(Request $request)
    {
        $bids = Bid::with(['customer', 'vehicle', 'agent'])
            ->where('result', 'pending')
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->agent_ids, fn ($q, $v) => $q->whereIn('agent_id', $v))
            ->latest()->get();

        $vendors   = Vendor::active()->orderBy('name')->get();
        $customers = Customer::complete()->orderBy('name')->get();
        $agents    = User::permission('scope.by_agent')->orderBy('name')->get();

        return view('results.index', compact('bids', 'vendors', 'customers', 'agents'));
    }

    public function wonList(Request $request)
    {
        $bids = Bid::with(['customer', 'vehicle', 'agent'])
            ->where('result', 'won')
            ->when($request->agent_ids, fn ($q, $v) => $q->whereIn('agent_id', $v))
            ->latest()->get();
        $agents = User::permission('scope.by_agent')->orderBy('name')->get();

        return view('results.won', compact('bids', 'agents'));
    }

    public function lostList(Request $request)
    {
        $bids = Bid::with(['customer', 'vehicle', 'agent'])
            ->where('result', 'lost')
            ->when($request->agent_ids, fn ($q, $v) => $q->whereIn('agent_id', $v))
            ->latest()->get();
        $agents = User::permission('scope.by_agent')->orderBy('name')->get();

        return view('results.lost', compact('bids', 'agents'));
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
                'customer_id' => $bid->customer_id, 'agent_id' => $bid->agent_id,
                'make' => $bid->make, 'model' => $bid->model, 'year' => $bid->year,
                'grade' => $bid->grade, 'chassis_no' => $bid->chassis_no,
                'budget' => $bid->max_bid, 'created_by' => $request->user()->id,
            ]);

            $vehicle->update([
                'vendor_id'               => $data['vendor_id'],
                'buying_price'            => $data['buying_price'],
                'winning_screenshot_path' => $request->file('screenshot')->store('winning_screenshots', 'public'),
                'won_at'                  => now(),
                'status'                  => 'won',
            ]);

            $bid->update(['result' => 'won', 'won_amount' => $data['buying_price'], 'vehicle_id' => $vehicle->id]);

            // Costing must exist BEFORE the payable is posted — adjustVendorPayable()
            // reads total_costing, which only exists once this row is created.
            $costing = VehicleCosting::firstOrCreate(
                ['vehicle_id' => $vehicle->id],
                ['buying_price' => $vehicle->buying_price, 'vendor_commission_percent' => $vehicle->vendor->commission_percent ?? 7]
            );
            $costing->recalculate(
                $vehicle->selling_price,
                $vehicle->agent->sales_commission_percent ?? 15,
                (int) ($vehicle->agent->sales_fixed_bonus ?? 0)
            )->save();

            $ledger->adjustVendorPayable($vehicle->fresh());
        });

        return redirect()->route('costings.show', $bid->fresh()->vehicle_id)
            ->with('success', 'Bid marked won. Vendor payable posted — complete the costing next.');
    }

    public function lost(Bid $bid)
    {
        $bid->update(['result' => 'lost']);
        $bid->vehicle?->update(['status' => 'lost']);
        return back()->with('success', 'Bid marked as lost.');
    }

    public function bulkLost(Request $request)
    {
        $data = $request->validate(['bid_ids' => ['required', 'array', 'min:1'], 'bid_ids.*' => ['exists:bids,id']]);

        $bids = Bid::whereIn('id', $data['bid_ids'])->where('result', 'pending')->get();
        foreach ($bids as $bid) {
            $bid->update(['result' => 'lost']);
            $bid->vehicle?->update(['status' => 'lost']);
        }

        return back()->with('success', count($bids) . ' bid(s) marked lost.');
    }

    public function undoWon(Bid $bid, LedgerService $ledger)
    {
        abort_unless(auth()->user()->canBackdate(), 403);
        abort_unless($bid->result === 'won', 422, 'This bid is not currently marked won.');

        $vehicle = $bid->vehicle;
        abort_if($vehicle?->invoice, 422, 'Cannot undo — an invoice already exists for this vehicle.');

        DB::transaction(function () use ($bid, $vehicle, $ledger) {
            if ($vehicle) {
                foreach (JournalEntry::where('reference_type', $vehicle->getMorphClass())->where('reference_id', $vehicle->id)->get() as $entry) {
                    $ledger->reverseEntry($entry, now()->toDateString(), "Reversal — bid #{$bid->id} won by mistake, reverted");
                }
                $vehicle->costing()->delete();
                $vehicle->update([
                    'vendor_id' => null, 'buying_price' => null,
                    'winning_screenshot_path' => null, 'won_at' => null, 'status' => 'requirement',
                ]);
            }
            $bid->update(['result' => 'pending', 'won_amount' => null]);
        });

        return back()->with('success', 'Bid reverted to pending — vendor payable reversed.');
    }

    public function undoLost(Bid $bid)
    {
        abort_unless(auth()->user()->can('bid_results.edit'), 403);
        abort_unless($bid->result === 'lost', 422, 'This bid is not currently marked lost.');

        $bid->update(['result' => 'pending']);
        $bid->vehicle?->update(['status' => 'requirement']);

        return back()->with('success', 'Bid reverted to pending.');
    }
}