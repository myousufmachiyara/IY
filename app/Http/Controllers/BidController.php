<?php

namespace App\Http\Controllers;

use App\Exports\BidsExport;
use App\Models\{Bid, User};
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class BidController extends Controller
{
    public function index(Request $request)
    {
        // Super admin bypasses the agent scope automatically, so this spans all agents.
        $bids = Bid::with(['agent', 'customer'])
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('auction_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('auction_date', '<=', $v))
            ->orderBy('auction_date')
            ->paginate(25)->withQueryString();

        $agents = User::role('sales_agent')->orderBy('name')->get();

        return view('bidding.merge', compact('bids', 'agents'));
    }

    public function export(Request $request)
    {
        $filters = $request->only('agent_id', 'from', 'to');
        return Excel::download(new BidsExport($filters), 'final-bidding-sheet.xlsx');
    }
}