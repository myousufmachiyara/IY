<?php

namespace App\Http\Controllers;

use App\Exports\BidTemplateExport;
use App\Imports\BidsImport;
use App\Models\{Bid, BidSheet, Customer};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class BidSheetController extends Controller
{
    public function index(Request $request)
    {
        $sheets = BidSheet::with('agent')
            ->when($request->from, fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->withCount(['bids', 'bids as won_count' => fn ($q) => $q->where('result', 'won'), 'bids as lost_count' => fn ($q) => $q->where('result', 'lost')])
            ->latest()->get();
        return view('bidding.sheets.index', compact('sheets'));
    }

    public function create() { return redirect()->route('bid-sheets.index'); }

    public function store(Request $request)
    {
        $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'auction_date' => $request->user()->can('dates.future')
                ? ['nullable', 'date']
                : ['required', 'date', 'date_equals:tomorrow'],
            'file'         => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $sheet = BidSheet::create([
            'agent_id' => $request->user()->id, 'title' => $request->title,
            'auction_date' => $request->auction_date, 'file_path' => $request->file('file')->store('bid_sheets'), 'status' => 'uploaded',
        ]);

        $import = new BidsImport($sheet);
        Excel::import($import, $request->file('file'));
        $sheet->update(['rows_count' => $sheet->bids()->count()]);

        $redirect = redirect()->route('bid-sheets.show', $sheet)->with('success', "Uploaded {$sheet->rows_count} bids.");
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
        $bidSheet->live_count = $bidSheet->bids->count();
        $customers = Customer::complete()->orderBy('name')->get();

        return view('bidding.sheets.show', ['sheet' => $bidSheet, 'customers' => $customers]);
    }

    /**
     * Deleting a sheet must never silently destroy bids that already have real
     * financial consequences (a won bid can have a live Vehicle, vendor payable,
     * invoice, and payments attached). Pending bids carry no such history and are
     * safe to remove outright; won/lost bids are only detached from the sheet,
     * mirroring the same rule destroyBid() already enforces on single deletes.
     */
    public function destroy(BidSheet $bidSheet)
    {
        DB::transaction(function () use ($bidSheet) {
            $bidSheet->bids()->where('result', 'pending')->delete();
            $bidSheet->bids()->update(['bid_sheet_id' => null]);
            $bidSheet->delete();
        });

        return back()->with('success', 'Bid sheet removed. Pending bids were deleted; won/lost bids were kept and detached from this sheet.');
    }

    public function template() { return Excel::download(new BidTemplateExport, 'bid-sheet-template.xlsx'); }

    /** Delete a single bid row; only while still pending, and only from within its sheet context. */
    public function destroyBid(Bid $bid)
    {
        abort_unless($bid->result === 'pending', 422, 'Only pending bids can be deleted.');
        $sheet = $bid->sheet;
        $bid->delete();
        if ($sheet) {
            $sheet->update(['rows_count' => $sheet->bids()->count()]);
        }
        return back()->with('success', 'Bid removed.');
    }

    public function assignCustomer(Request $request, Bid $bid)
    {
        abort_unless($bid->result === 'pending', 422, 'Customer can only be assigned while the bid is still pending.');
        $data = $request->validate(['customer_id' => ['required', 'exists:customers,id']]);
        $customer = Customer::findOrFail($data['customer_id']);
        abort_unless($customer->isProfileComplete(), 422, "Customer '{$customer->name}' has an incomplete profile.");
        $bid->update(['customer_id' => $customer->id]);
        return back()->with('success', "Customer '{$customer->name}' assigned to lot {$bid->lot_no}.");
    }

    public function bulkAssignCustomer(Request $request)
    {
        $data = $request->validate(['bid_ids' => ['required', 'array', 'min:1'], 'bid_ids.*' => ['exists:bids,id'], 'customer_id' => ['required', 'exists:customers,id']]);
        $customer = Customer::findOrFail($data['customer_id']);
        abort_unless($customer->isProfileComplete(), 422, "Customer '{$customer->name}' has an incomplete profile.");
        Bid::whereIn('id', $data['bid_ids'])->where('result', 'pending')->update(['customer_id' => $customer->id]);
        return back()->with('success', count($data['bid_ids']) . ' bid(s) assigned.');
    }
}