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
            ->whereNotNull('customer_id')
            ->when($request->agent_ids, fn ($q, $v) => $q->whereIn('agent_id', $v))
            ->when($request->result, fn ($q, $v) => $q->where('result', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('auction_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('auction_date', '<=', $v))
            ->orderBy('auction_date')->get();

        $agents = User::permission('scope.by_agent')->orderBy('name')->get();

        return view('bidding.merge', compact('bids', 'agents'));
    }

    public function export(Request $request)
    {
        $filters = $request->only('agent_ids', 'from', 'to', 'result');
        return Excel::download(new BidsExport($filters, $request->columns ?: []), 'final-bidding-sheet.xlsx');
    }
}