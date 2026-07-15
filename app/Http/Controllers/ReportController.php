<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\{Bid, User, Vehicle, Vendor};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function agentWise(Request $request)
    {
        $rows = User::permission('scope.by_agent')->get()->map(function ($agent) {
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

    public function vendorWise(Request $request)
    {
        $rows = Vendor::get()->map(fn ($v) => [
            'vendor'   => $v->name,
            'location' => $v->location,
            'vehicles' => $v->vehicles()->count(),
            'payable'  => $v->vehicles()->sum('buying_price'),
            'paid'     => $v->payments()->sum('amount'),
        ]);

        return $this->respond($request, 'reports.vendor_wise', $rows, ['Vendor', 'Location', 'Vehicles', 'Payable (¥)', 'Paid (¥)']);
    }

    public function bidWise(Request $request)
    {
        $rows = Bid::allAgents()->selectRaw('auction_date, COUNT(*) as total, SUM(result = "won") as won')
            ->whereNotNull('auction_date')->groupBy('auction_date')->orderBy('auction_date')->get()
            ->map(fn ($r) => ['date' => (string) $r->auction_date, 'total_bids' => $r->total, 'won' => (int) $r->won]);

        return $this->respond($request, 'reports.bid_wise', $rows, ['Date', 'Total Bids', 'Won']);
    }

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

    private function respond(Request $request, string $view, $rows, array $headings)
    {
        if ($request->export === 'excel') {
            return Excel::download(new ArrayExport($rows->map(fn ($r) => array_values($r))->toArray(), $headings), 'report.xlsx');
        }

        if ($request->export === 'pdf') {
            return Pdf::loadView('reports.pdf', [
                'rows'     => $rows,
                'headings' => $headings,
                'title'    => \Illuminate\Support\Str::headline(str($view)->afterLast('.')) . ' Report',
            ])->download('report.pdf');
        }

        return view($view, compact('rows', 'headings'));
    }
}