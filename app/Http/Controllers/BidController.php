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
        $bids = Bid::with(['agent', 'customer'])
            ->whereNotNull('customer_id') // #17 — unassigned bids never appear in the merge/export list
            ->when($request->agent_ids, fn ($q) => $q->whereIn('agent_id', $request->agent_ids))
            ->when($request->from, fn ($q) => $q->whereDate('auction_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('auction_date', '<=', $request->to))
            ->orderBy('auction_date')->get();

        $agents = User::permission('scope.by_agent')->orderBy('name')->get();

        return view('bidding.merge', compact('bids', 'agents'));
    }

    public function export(Request $request)
    {
        $filters = $request->only('agent_ids', 'from', 'to');
        return Excel::download(new BidsExport($filters, $request->columns ?: []), 'final-bidding-sheet.xlsx');
    }
}