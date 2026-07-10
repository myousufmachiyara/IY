<?php

namespace App\Http\Controllers;

use App\Imports\BidsImport;
use App\Models\BidSheet;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class BidSheetController extends Controller
{
    public function index()
    {
        // Scoped: agents see their own sheets; super admin/accountant see all.
        $sheets = BidSheet::with('agent')->withCount('bids')->latest()->paginate(15);
        return view('bidding.sheets.index', compact('sheets'));
    }

    public function create()
    {
        return view('bidding.sheets.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'auction_date' => ['nullable', 'date'],
            'file'         => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $sheet = BidSheet::create([
            'agent_id'     => $request->user()->id,
            'title'        => $request->title,
            'auction_date' => $request->auction_date,
            'file_path'    => $request->file('file')->store('bid_sheets'),
            'status'       => 'uploaded',
        ]);

        Excel::import(new BidsImport($sheet), $request->file('file'));
        $sheet->update(['rows_count' => $sheet->bids()->count()]);

        return redirect()->route('bid-sheets.index')->with('success', "Uploaded {$sheet->rows_count} bids.");
    }

    public function show(BidSheet $bidSheet)
    {
        $bidSheet->load('bids', 'agent');
        return view('bidding.sheets.show', ['sheet' => $bidSheet]);
    }

    public function destroy(BidSheet $bidSheet)
    {
        $bidSheet->delete(); // bids cascade via FK
        return back()->with('success', 'Bid sheet removed.');
    }
}