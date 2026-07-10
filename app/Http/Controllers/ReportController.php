<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\{Bid, User, Vehicle};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /** Sales-agent performance: bids, wins, profit generated, earnings. */
    public function agentWise(Request $request)
    {
        $rows = User::role('sales_agent')->get()->map(function ($agent) {
            $vehicles = Vehicle::allAgents()->where('agent_id', $agent->id);
            return [
                'agent'        => $agent->name,
                'total_bids'   => Bid::allAgents()->where('agent_id', $agent->id)->count(),
                'bids_won'     => Bid::allAgents()->where('agent_id', $agent->id)->won()->count(),
                'vehicles_won' => (clone $vehicles)->won()->count(),
                'profit'       => (clone $vehicles)->won()->with('costing')->get()->sum(fn ($v) => $v->costing?->profit ?? 0),
                'earnings'     => (clone $vehicles)->won()->with('costing')->get()->sum(fn ($v) => $v->costing?->agentEarning() ?? 0),
            ];
        });

        return $this->respond($request, 'reports.agent_wise', $rows, ['Agent', 'Total Bids', 'Bids Won', 'Vehicles Won', 'Profit (¥)', 'Earnings (¥)']);
    }

    /** Vendor performance: vehicles supplied, amount payable/paid. */
    public function vendorWise(Request $request)
    {
        $rows = User::role('vendor_agent')->get()->map(fn ($v) => [
            'vendor'   => $v->name,
            'location' => $v->vendor_location,
            'vehicles' => $v->vendorVehicles()->count(),
            'payable'  => $v->vendorVehicles()->sum('buying_price'),
            'paid'     => $v->vendorPayments()->sum('amount'),
        ]);

        return $this->respond($request, 'reports.vendor_wise', $rows, ['Vendor', 'Location', 'Vehicles', 'Payable (¥)', 'Paid (¥)']);
    }

    /** Total bid activity per auction date. */
    public function bidWise(Request $request)
    {
        $rows = Bid::allAgents()->selectRaw('auction_date, COUNT(*) as total, SUM(result = "won") as won')
            ->whereNotNull('auction_date')->groupBy('auction_date')->orderBy('auction_date')->get()
            ->map(fn ($r) => ['date' => (string) $r->auction_date, 'total_bids' => $r->total, 'won' => (int) $r->won]);

        return $this->respond($request, 'reports.bid_wise', $rows, ['Date', 'Total Bids', 'Won']);
    }

    /** Bid-won detail list. */
    public function bidWon(Request $request)
    {
        $rows = Bid::allAgents()->won()->with('agent', 'customer')->get()->map(fn ($b) => [
            'lot'      => $b->lot_no,
            'agent'    => $b->agent?->name,
            'customer' => $b->customer?->name,
            'vehicle'  => trim("{$b->year} {$b->make} {$b->model}"),
            'amount'   => $b->won_amount,
        ]);

        return $this->respond($request, 'reports.bid_won', $rows, ['Lot', 'Agent', 'Customer', 'Vehicle', 'Won Amount (¥)']);
    }

    /** Shared responder: renders Blade, or streams PDF/Excel when ?export= is present. */
    private function respond(Request $request, string $view, $rows, array $headings)
    {
        if ($request->export === 'excel') {
            return Excel::download(new ArrayExport($rows->map(fn ($r) => array_values($r))->toArray(), $headings), 'report.xlsx');
        }

        if ($request->export === 'pdf') {
            return Pdf::loadView('reports.pdf', ['rows' => $rows, 'headings' => $headings, 'title' => \Illuminate\Support\Str::headline(str($view)->afterLast('.'))])
                ->download('report.pdf');
        }

        return view($view, compact('rows', 'headings'));
    }
}