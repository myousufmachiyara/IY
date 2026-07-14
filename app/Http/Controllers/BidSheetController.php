<?php

namespace App\Http\Controllers;

use App\Exports\BidTemplateExport;
use App\Imports\BidsImport;
use App\Models\BidSheet;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class BidSheetController extends Controller
{
    public function index()
    {
        // ScopedToAgent narrows this to "my sheets" for a sales agent automatically.
        $sheets = BidSheet::with('agent')
            ->withCount([
                'bids',
                'bids as won_count'  => fn ($q) => $q->where('result', 'won'),
                'bids as lost_count' => fn ($q) => $q->where('result', 'lost'),
            ])
            ->latest()
            ->get();

        return view('bidding.sheets.index', compact('sheets'));
    }

    /** Route stays registered by Route::resource; nothing links here since upload is a modal. */
    public function create()
    {
        return redirect()->route('bid-sheets.index');
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

        $import = new BidsImport($sheet);
        Excel::import($import, $request->file('file'));
        $sheet->update(['rows_count' => $sheet->bids()->count()]);

        $redirect = redirect()->route('bid-sheets.show', $sheet)
            ->with('success', "Uploaded {$sheet->rows_count} bids.");

        if (count($import->skipped) > 0) {
            $preview = array_slice($import->skipped, 0, 5);
            $more = count($import->skipped) > 5 ? ' (+' . (count($import->skipped) - 5) . ' more)' : '';
            $redirect->with('warning', count($import->skipped) . ' row(s) skipped — ' . implode(' ', $preview) . $more);
        }

        return $redirect;
    }

    public function show(BidSheet $bidSheet)
    {
        $bidSheet->load(['bids' => fn ($q) => $q->latest(), 'agent']);
        return view('bidding.sheets.show', ['sheet' => $bidSheet]);
    }

    public function destroy(BidSheet $bidSheet)
    {
        // Bids survive (bid_sheet_id nulls out via the FK), only the sheet record itself is removed.
        $bidSheet->delete();
        return back()->with('success', 'Bid sheet removed.');
    }

    public function template()
    {
        return Excel::download(new BidTemplateExport, 'bid-sheet-template.xlsx');
    }
}