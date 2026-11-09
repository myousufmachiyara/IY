<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\{Bid, Customer, Shipment, User, Vehicle, Vendor};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Pre-V2 reports kept as-is (Bid Activity, Vendor Summary, Agent Profitability, Auction House
 * Performance). Every other report lives in App\Http\Controllers\Reports\*.
 */
class ReportController extends Controller
{
    public function vendorWise(Request $request)
    {
        $rows = Vendor::when($request->vendor_id, fn ($q, $v) => $q->where('id', $v))->get()->map(function ($v) use ($request) {
            $vehicles = $v->vehicles()
                ->when($request->from, fn ($q, $f) => $q->whereDate('won_at', '>=', $f))
                ->when($request->to, fn ($q, $t) => $q->whereDate('won_at', '<=', $t));
            return [
                'vendor'   => $v->name,
                'location' => $v->location,
                'vehicles' => (clone $vehicles)->count(),
                'payable'  => (clone $vehicles)->sum('buying_price'),
                'paid'     => $v->payments()->sum('amount'),
            ];
        });

        $vendors = Vendor::orderBy('name')->get();
        return $this->respond($request, 'reports.vendor_wise', $rows, ['Vendor', 'Location', 'Vehicles', 'Payable (¥)', 'Paid (¥)'], compact('vendors'));
    }

    public function bidWise(Request $request)
    {
        $rows = Bid::allAgents()->selectRaw('auction_date, COUNT(*) as total, SUM(result = "won") as won')
            ->whereNotNull('auction_date')
            ->when($request->from, fn ($q, $v) => $q->whereDate('auction_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('auction_date', '<=', $v))
            ->groupBy('auction_date')->orderBy('auction_date')->get()
            ->map(fn ($r) => ['date' => (string) $r->auction_date, 'total_bids' => $r->total, 'won' => (int) $r->won]);

        return $this->respond($request, 'reports.bid_wise', $rows, ['Date', 'Total Bids', 'Won']);
    }

    /** "Access profit contribution by agent" — company/agent split, unlike Agent-wise's bid activity counts. */
    public function agentProfitability(Request $request)
    {
        $agentsQuery = User::permission('scope.by_agent');
        if (! $request->user()->can('data.view_all')) {
            $agentsQuery->where('id', $request->user()->id);
        } elseif ($request->agent_id) {
            $agentsQuery->where('id', $request->agent_id);
        }

        $rows = $agentsQuery->get()->map(function ($agent) use ($request) {
            $vehicles = Vehicle::allAgents()->where('agent_id', $agent->id)->won()->with('costing')
                ->when($request->from, fn ($q, $v) => $q->whereDate('won_at', '>=', $v))
                ->when($request->to, fn ($q, $v) => $q->whereDate('won_at', '<=', $v))
                ->get();

            return [
                'agent'          => $agent->name,
                'vehicles_won'   => $vehicles->count(),
                'gross_profit'   => $vehicles->sum(fn ($v) => $v->costing?->profit ?? 0),
                'agent_earning'  => $vehicles->sum(fn ($v) => $v->costing?->agentEarning() ?? 0),
                'company_profit' => $vehicles->sum(fn ($v) => $v->costing?->finalProfit() ?? 0),
            ];
        });

        $agents = User::permission('scope.by_agent')->orderBy('name')->get();
        return $this->respond($request, 'reports.agent_profitability', $rows,
            ['Agent', 'Vehicles Won', 'Gross Profit (¥)', 'Agent Earning (¥)', 'Company Profit (¥)'], compact('agents'));
    }

    /** "Access won-vehicle/performance reporting by auction house" — vendor = auction house/yard here. */
    public function auctionHousePerformance(Request $request)
    {
        $rows = Vendor::when($request->vendor_id, fn ($q, $v) => $q->where('id', $v))->get()->map(function ($vendor) use ($request) {
            $vehicles = $vendor->vehicles()->won()->with('costing')
                ->when($request->from, fn ($q, $f) => $q->whereDate('won_at', '>=', $f))
                ->when($request->to, fn ($q, $t) => $q->whereDate('won_at', '<=', $t))
                ->get();

            return [
                'vendor'           => $vendor->name,
                'location'         => $vendor->location,
                'vehicles_won'     => $vehicles->count(),
                'avg_buying_price' => $vehicles->isNotEmpty() ? (int) round($vehicles->avg('buying_price')) : 0,
                'total_costing'    => $vehicles->sum(fn ($v) => $v->costing?->total_costing ?? 0),
                'avg_profit'       => $vehicles->isNotEmpty() ? (int) round($vehicles->avg(fn ($v) => $v->costing?->profit ?? 0)) : 0,
            ];
        });

        $vendors = Vendor::orderBy('name')->get();
        return $this->respond($request, 'reports.auction_house_performance', $rows,
            ['Auction House / Vendor', 'Location', 'Vehicles Won', 'Avg Buying Price (¥)', 'Total Costing (¥)', 'Avg Profit (¥)'], compact('vendors'));
    }

    private function respond(Request $request, string $view, $rows, array $headings, array $extra = [])
    {
        if ($request->export === 'excel') {
            return Excel::download(new ArrayExport($rows->map(fn ($r) => array_values($r))->toArray(), $headings), 'report.xlsx');
        }
        if ($request->export === 'pdf') {
            return Pdf::loadView('reports.pdf', [
                'rows' => $rows, 'headings' => $headings,
                'title' => \Illuminate\Support\Str::headline(str($view)->afterLast('.')) . ' Report',
            ])->download('report.pdf');
        }
        return view($view, array_merge(compact('rows', 'headings'), $extra));
    }
}