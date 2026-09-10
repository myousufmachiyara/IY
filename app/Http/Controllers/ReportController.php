<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\{Bid, Customer, Shipment, User, Vehicle, Vendor};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function agentWise(Request $request)
    {
        $agentsQuery = User::permission('scope.by_agent');
        if (! $request->user()->can('data.view_all')) {
            $agentsQuery->where('id', $request->user()->id);
        } elseif ($request->agent_id) {
            $agentsQuery->where('id', $request->agent_id);
        }

        $rows = $agentsQuery->get()->map(function ($agent) use ($request) {
            $vehicles = Vehicle::allAgents()->where('agent_id', $agent->id)
                ->when($request->from, fn ($q, $v) => $q->whereDate('won_at', '>=', $v))
                ->when($request->to, fn ($q, $v) => $q->whereDate('won_at', '<=', $v));
            $bids = Bid::allAgents()->where('agent_id', $agent->id)
                ->when($request->from, fn ($q, $v) => $q->whereDate('auction_date', '>=', $v))
                ->when($request->to, fn ($q, $v) => $q->whereDate('auction_date', '<=', $v));

            return [
                'agent'        => $agent->name,
                'total_bids'   => (clone $bids)->count(),
                'bids_won'     => (clone $bids)->won()->count(),
                'vehicles_won' => (clone $vehicles)->won()->count(),
                'profit'       => (clone $vehicles)->won()->with('costing')->get()->sum(fn ($v) => $v->costing?->profit ?? 0),
                'earnings'     => (clone $vehicles)->won()->with('costing')->get()->sum(fn ($v) => $v->costing?->agentEarning() ?? 0),
            ];
        });

        $agents = User::permission('scope.by_agent')->orderBy('name')->get();
        return $this->respond($request, 'reports.agent_wise', $rows, ['Agent', 'Total Bids', 'Bids Won', 'Vehicles Won', 'Profit (¥)', 'Earnings (¥)'], compact('agents'));
    }

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

    public function bidWon(Request $request)
    {
        $rows = Bid::allAgents()->won()->with('agent', 'customer')
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('auction_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('auction_date', '<=', $v))
            ->get()->map(fn ($b) => [
                'lot' => $b->lot_no, 'agent' => $b->agent?->name, 'customer' => $b->customer?->name,
                'vehicle' => trim("{$b->year} {$b->make} {$b->model}"), 'amount' => $b->won_amount,
            ]);

        $agents = User::permission('scope.by_agent')->orderBy('name')->get();
        return $this->respond($request, 'reports.bid_won', $rows, ['Lot', 'Agent', 'Customer', 'Vehicle', 'Won Amount (¥)'], compact('agents'));
    }

    public function customerWise(Request $request)
    {
        $rows = Customer::complete()->with('agent')
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->get()->map(fn ($c) => [
                'customer' => $c->name, 'agent' => $c->agent->name ?? '—',
                'vehicles' => $c->vehicles()->count(), 'invoiced' => $c->totalInvoiced(),
                'paid' => $c->totalPaid(), 'balance' => $c->balance(),
            ]);

        $agents = User::permission('scope.by_agent')->orderBy('name')->get();
        return $this->respond($request, 'reports.customer_wise', $rows, ['Customer', 'Agent', 'Vehicles', 'Invoiced (¥)', 'Paid (¥)', 'Balance (¥)'], compact('agents'));
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

    /** "Access profitability by customer." */
    public function customerProfitability(Request $request)
    {
        $rows = Customer::with('agent')
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->get()->map(function ($c) {
                $vehicles = $c->vehicles()->won()->with('costing')->get();
                return [
                    'customer'       => $c->name,
                    'agent'          => $c->agent->name ?? '—',
                    'vehicles_won'   => $vehicles->count(),
                    'gross_profit'   => $vehicles->sum(fn ($v) => $v->costing?->profit ?? 0),
                    'company_profit' => $vehicles->sum(fn ($v) => $v->costing?->finalProfit() ?? 0),
                ];
            })
            ->filter(fn ($r) => $r['vehicles_won'] > 0)->values();

        $agents = User::permission('scope.by_agent')->orderBy('name')->get();
        return $this->respond($request, 'reports.customer_profitability', $rows,
            ['Customer', 'Agent', 'Vehicles Won', 'Gross Profit (¥)', 'Company Profit (¥)'], compact('agents'));
    }

    /** "Access vehicle-level profit/margin reporting" — one row per won vehicle. */
    public function vehicleProfitability(Request $request)
    {
        $rows = Vehicle::won()->with('customer', 'agent', 'costing')
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('won_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('won_at', '<=', $v))
            ->get()->map(fn ($v) => [
                'vehicle'        => $v->label(),
                'customer'       => $v->customer->name ?? '—',
                'agent'          => $v->agent->name ?? '—',
                'buying_price'   => $v->buying_price,
                'total_costing'  => $v->costing?->total_costing ?? 0,
                'selling_price'  => $v->selling_price ?: ($v->costing?->sale_price ?? 0),
                'profit'         => $v->costing?->profit ?? 0,
                'company_profit' => $v->costing?->finalProfit() ?? 0,
            ]);

        $agents = User::permission('scope.by_agent')->orderBy('name')->get();
        return $this->respond($request, 'reports.vehicle_profitability', $rows,
            ['Vehicle', 'Customer', 'Agent', 'Buying Price (¥)', 'Total Costing (¥)', 'Selling Price (¥)', 'Profit (¥)', 'Company Profit (¥)'], compact('agents'));
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

    /** "Access shipment/freight reporting." */
    public function shipmentReport(Request $request)
    {
        $rows = Shipment::with('customer')->withCount('vehicles')
            ->when($request->method, fn ($q, $v) => $q->where('method', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('shipment_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('shipment_date', '<=', $v))
            ->latest('shipment_date')->get()
            ->map(fn ($s) => [
                'customer'      => $s->customer->name ?? '—',
                'method'        => $s->method,
                'reference'     => $s->container_no ?: ($s->bl_no ?: '—'),
                'shipping_line' => $s->shipping_company,
                'vehicles'      => $s->vehicles_count,
                'freight_total' => $s->freight_total,
                'shipment_date' => optional($s->shipment_date)->format('Y-m-d') ?? '—',
                'eta'           => optional($s->expected_arrival)->format('Y-m-d') ?? '—',
                'status'        => $s->status,
            ]);

        return $this->respond($request, 'reports.shipment', $rows,
            ['Customer', 'Method', 'Container/BL No', 'Shipping Line', 'Vehicles', 'Freight Total (¥)', 'Shipment Date', 'ETA', 'Status']);
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