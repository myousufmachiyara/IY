<?php

namespace App\Http\Controllers\Reports;

use App\Models\{Bid, Customer, Invoice, Payment, User, Vehicle};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AgentReportController extends BaseReportController
{
    /** Core per-agent figures for a period — each metric on its own event date. */
    private function agentStats(User $agent, ?string $from, ?string $to): array
    {
        $bids = $this->range(Bid::allAgents()->where('agent_id', $agent->id), 'auction_date', $from, $to);
        $bidCount = (clone $bids)->count();
        $wins = $this->range(Vehicle::allAgents()->where('agent_id', $agent->id)->whereIn('status', self::WON_STATUSES), 'won_at', $from, $to)->count();

        $invoices = $this->range($this->saleInvoices()->withoutGlobalScopes()->where('agent_id', $agent->id)->whereNotNull('vehicle_id'), 'issued_at', $from, $to)
            ->with(['vehicle' => fn ($q) => $q->allAgents()->with('costing')])->get();

        $collections = $this->range(Payment::approved()->where('method', '!=', 'deposit')
            ->whereHas('invoice', fn ($i) => $i->withoutGlobalScopes()->where('agent_id', $agent->id)), 'approved_at', $from, $to)->sum('amount');

        return [
            'bids' => $bidCount,
            'wins' => $wins,
            'win_rate' => $this->pct($wins, $bidCount),
            'invoiced_vehicles' => $invoices->count(),
            'sales' => (int) $invoices->sum('total_payable'),
            'gross' => (int) $invoices->sum(fn ($i) => $i->vehicle?->costing?->profit ?? 0),
            'earnings' => (int) $invoices->sum(fn ($i) => $i->vehicle?->costing?->agentEarning() ?? 0),
            'collections' => (int) $collections,
        ];
    }

    // ───────────────────────── 26 Agent Performance ─────────────────────────

    public function performance(Request $request)
    {
        $rows = $this->agents($request)->map(fn (User $a) => ['agent' => $a->name,
            'url' => $this->reportLink('agent_commission', ['agent_id' => $a->id, 'from' => $request->from, 'to' => $request->to])]
            + $this->agentStats($a, $request->from, $request->to));

        return $this->render($request, 'agent_wise', [
            ['key' => 'agent', 'label' => 'Agent', 'link' => 'url'],
            ['key' => 'bids', 'label' => 'Bids', 'type' => 'int', 'total' => true],
            ['key' => 'wins', 'label' => 'Wins', 'type' => 'int', 'total' => true],
            ['key' => 'win_rate', 'label' => 'Win Rate', 'type' => 'pct'],
            ['key' => 'invoiced_vehicles', 'label' => 'Invoiced Vehicles', 'type' => 'int', 'total' => true],
            ['key' => 'sales', 'label' => 'Sales', 'type' => 'money', 'total' => true],
            ['key' => 'gross', 'label' => 'Gross Profit', 'type' => 'money', 'total' => true, 'sensitive' => true],
            ['key' => 'earnings', 'label' => 'Earnings', 'type' => 'money', 'total' => true],
        ], $rows, $this->filters(
            $this->fDateRange('Date range'),
            $this->fAgent(),
        ), [
            'totals' => ['win_rate' => $this->pct($rows->sum('wins'), $rows->sum('bids'))],
            'notes' => ['Bids by auction date, wins by won date (kept after the vehicle moves on), sales/profit/earnings by invoice date.'],
            'empty' => 'No sales agents found.',
        ]);
    }

    // ───────────────────────── 27 Agent Commission ─────────────────────────

    public function commission(Request $request)
    {
        $agents = $this->agents($request)->keyBy('id');
        $q = Vehicle::allAgents()->whereIn('agent_id', $agents->keys())->whereIn('status', self::WON_STATUSES)
            ->with('costing', 'invoice', 'customer');
        $this->range($q, 'won_at', $request->from, $request->to);

        $rows = $q->orderByDesc('won_at')->get()->map(function (Vehicle $v) use ($agents) {
            $c = $v->costing;
            $agent = $agents[$v->agent_id];
            $floor = $c?->sale_price ?? 0;
            $selling = (int) $v->selling_price;
            $invoiced = $v->invoice && $v->invoice->status !== 'cancelled';
            $earned = $c?->agentEarning() ?? 0;
            $status = ! $selling ? 'no_price' : ($invoiced ? 'earned' : 'provisional');
            return [
                'agent' => $agent->name,
                'vehicle' => $v->label() . ($v->chassis_no ? " · {$v->chassis_no}" : ''), 'vehicle_url' => $this->link('costings.show', 'costings.show', $v),
                'customer' => $v->customer->name ?? '—',
                'won_date' => $v->won_at?->toDateString(),
                'selling' => $selling ?: null,
                'floor' => $floor,
                'upsell' => $selling ? max($selling - $floor, 0) : 0,
                'pct' => (float) ($agent->sales_commission_percent ?? 15),
                'bonus' => $c?->agent_bonus ?? 0,
                'commission' => $c?->agent_commission_amount ?? 0,
                'earned' => $earned,
                'paid' => 0,
                'due' => $status === 'earned' ? $earned : 0,
                'status' => match ($status) {
                    'earned' => $this->badge('Earned (invoiced)', 'success'),
                    'provisional' => $this->badge('Provisional', 'warning'),
                    default => $this->badge('No selling price', 'secondary'),
                },
                'status_key' => $status,
            ];
        })->when($request->commission_status, fn ($c, $s) => $c->where('status_key', $s));

        return $this->render($request, 'agent_commission', [
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'vehicle', 'label' => 'Vehicle', 'link' => 'vehicle_url'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'won_date', 'label' => 'Won Date', 'type' => 'date'],
            ['key' => 'selling', 'label' => 'Selling Price', 'type' => 'money', 'total' => true],
            ['key' => 'floor', 'label' => 'Floor', 'type' => 'money', 'total' => true],
            ['key' => 'upsell', 'label' => 'Upsell', 'type' => 'money', 'total' => true],
            ['key' => 'pct', 'label' => 'Commission %', 'type' => 'pct'],
            ['key' => 'commission', 'label' => 'Commission', 'type' => 'money', 'total' => true],
            ['key' => 'bonus', 'label' => 'Bonus', 'type' => 'money', 'total' => true],
            ['key' => 'earned', 'label' => 'Earned', 'type' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'type' => 'money', 'total' => true],
            ['key' => 'due', 'label' => 'Due', 'type' => 'money', 'total' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ], $rows, $this->filters(
            $this->fDateRange('Won date'),
            $this->fAgent(),
            $this->fSelect('commission_status', 'Commission status', ['earned' => 'Earned (invoiced)', 'provisional' => 'Provisional (not invoiced)', 'no_price' => 'No selling price yet']),
        ), [
            'cards' => [
                ['label' => 'Earned (invoiced)', 'value' => $rows->where('status_key', 'earned')->sum('earned'), 'variant' => 'success'],
                ['label' => 'Provisional', 'value' => $rows->where('status_key', 'provisional')->sum('earned'), 'variant' => 'warning'],
            ],
            'notes' => ['Commission = (Selling Price − Floor) × Commission % + fixed bonus — the same formula as the Costing screen. Floor is the "Cost Price for Agent".',
                        'There is no commission payout ledger in the system yet, so Paid shows 0 and Due equals the earned commission on invoiced vehicles.'],
        ]);
    }

    // ───────────────────────── 28 Agent Monthly Comparison ─────────────────────────

    public function monthly(Request $request)
    {
        $from = $request->input('from', now()->subMonths(5)->startOfMonth()->toDateString());
        $to   = $request->input('to', today()->toDateString());
        $periods = $this->periods($from, $to, 'month');
        $agents = $this->agents($request);

        $rows = collect();
        $chartTotals = array_fill_keys(array_keys($periods), 0);
        foreach ($periods as $k => $p) {
            foreach ($agents as $a) {
                $s = $this->agentStats($a, $p['from'], $p['to']);
                $rows->push(['month' => $p['label'], 'agent' => $a->name,
                    'url' => $this->reportLink('agent_commission', ['agent_id' => $a->id, 'from' => $p['from'], 'to' => $p['to']]),
                    'bids' => $s['bids'], 'wins' => $s['wins'], 'win_rate' => $s['win_rate'],
                    'sales' => $s['sales'], 'earnings' => $s['earnings']]);
                $chartTotals[$k] += $s['sales'];
            }
        }

        $chartSeries = $agents->count() <= 5
            ? $agents->map(fn ($a) => ['label' => $a->name, 'data' => $rows->where('agent', $a->name)->pluck('sales')->values()])->values()
            : [['label' => 'Invoiced sales (all agents)', 'data' => array_values($chartTotals)]];

        return $this->render($request, 'agent_monthly', [
            ['key' => 'month', 'label' => 'Month'],
            ['key' => 'agent', 'label' => 'Agent', 'link' => 'url'],
            ['key' => 'bids', 'label' => 'Bids', 'type' => 'int', 'total' => true],
            ['key' => 'wins', 'label' => 'Wins', 'type' => 'int', 'total' => true],
            ['key' => 'win_rate', 'label' => 'Win Rate', 'type' => 'pct'],
            ['key' => 'sales', 'label' => 'Invoiced Sales', 'type' => 'money', 'total' => true],
            ['key' => 'earnings', 'label' => 'Commission Earned', 'type' => 'money', 'total' => true],
        ], $rows, $this->filters(
            $this->fDateRange('Period range', 'from', 'to', $from, $to),
            $this->fAgent(),
        ), [
            'totals' => ['win_rate' => $this->pct($rows->sum('wins'), $rows->sum('bids'))],
            'chart' => ['type' => 'line', 'labels' => array_column($periods, 'label'), 'series' => $chartSeries],
            'notes' => ['Bids by auction date, wins by won date, sales and commission by invoice date — all attributed to the agent on the record.'],
        ]);
    }

    // ───────────────────────── 29 Agent Sales Conversion ─────────────────────────

    public function conversion(Request $request)
    {
        $rows = $this->agents($request)->map(function (User $a) use ($request) {
            // Cohort: bids placed in the period, followed through to win → invoice → fully paid.
            $bids = $this->range(Bid::allAgents()->where('agent_id', $a->id), 'auction_date', $request->from, $request->to)
                ->with(['vehicle' => fn ($q) => $q->allAgents()->with(['invoice' => fn ($i) => $i->withoutGlobalScopes()])])->get();
            $won = $bids->where('result', 'won');
            $invoiced = $won->filter(fn ($b) => $b->vehicle?->invoice && $b->vehicle->invoice->status !== 'cancelled');
            $paid = $invoiced->filter(fn ($b) => $b->vehicle->invoice->isFullyPaid());
            return [
                'agent' => $a->name,
                'bids' => $bids->count(), 'won' => $won->count(), 'invoiced' => $invoiced->count(), 'paid' => $paid->count(),
                'bid_win' => $this->pct($won->count(), $bids->count()),
                'win_invoice' => $this->pct($invoiced->count(), $won->count()),
                'invoice_paid' => $this->pct($paid->count(), $invoiced->count()),
                'overall' => $this->pct($paid->count(), $bids->count()),
            ];
        });

        return $this->render($request, 'agent_conversion', [
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'bids', 'label' => 'Bids', 'type' => 'int', 'total' => true],
            ['key' => 'won', 'label' => 'Won', 'type' => 'int', 'total' => true],
            ['key' => 'invoiced', 'label' => 'Invoiced', 'type' => 'int', 'total' => true],
            ['key' => 'paid', 'label' => 'Fully Paid', 'type' => 'int', 'total' => true],
            ['key' => 'bid_win', 'label' => 'Bid → Win %', 'type' => 'pct'],
            ['key' => 'win_invoice', 'label' => 'Win → Invoice %', 'type' => 'pct'],
            ['key' => 'invoice_paid', 'label' => 'Invoice → Paid %', 'type' => 'pct'],
            ['key' => 'overall', 'label' => 'Bid → Paid %', 'type' => 'pct'],
        ], $rows, $this->filters(
            $this->fDateRange('Bid date (cohort)'),
            $this->fAgent(),
        ), [
            'totals' => [
                'bid_win' => $this->pct($rows->sum('won'), $rows->sum('bids')),
                'win_invoice' => $this->pct($rows->sum('invoiced'), $rows->sum('won')),
                'invoice_paid' => $this->pct($rows->sum('paid'), $rows->sum('invoiced')),
                'overall' => $this->pct($rows->sum('paid'), $rows->sum('bids')),
            ],
            'notes' => ['Cohort-based: takes the bids placed in the date range and follows those same bids through win, invoice and full payment. A "completed sale" = invoice 100% paid.'],
        ]);
    }

    // ───────────────────────── 30 Agent Customer Portfolio ─────────────────────────

    public function portfolio(Request $request)
    {
        $asOf = $this->asOf($request);
        $window = max((int) $request->input('active_days', 180), 1);
        $since = Carbon::parse($asOf)->subDays($window)->toDateString();

        $rows = $this->agents($request)->map(function (User $a) use ($asOf, $since) {
            $customers = Customer::allAgents()->where('agent_id', $a->id)->whereDate('created_at', '<=', $asOf)->pluck('id');
            $activeBid = Bid::allAgents()->whereIn('customer_id', $customers)->whereBetween('auction_date', [$since, $asOf])->distinct()->pluck('customer_id');
            $activeWon = Vehicle::allAgents()->whereIn('customer_id', $customers)->whereIn('status', self::WON_STATUSES)->whereDate('won_at', '>=', $since)->whereDate('won_at', '<=', $asOf)->distinct()->pluck('customer_id');
            $activePay = Payment::approved()->whereIn('customer_id', $customers)->whereBetween('paid_at', [$since, $asOf])->distinct()->pluck('customer_id');
            $active = $activeBid->merge($activeWon)->merge($activePay)->unique()->count();

            $inv = $this->saleInvoices()->withoutGlobalScopes()->whereIn('customer_id', $customers)->whereDate('issued_at', '<=', $asOf)->get();
            return [
                'agent' => $a->name, 'url' => $this->reportLink('customer_wise', ['agent_id' => $a->id]),
                'customers' => $customers->count(),
                'active' => $active,
                'active_pct' => $this->pct($active, $customers->count()),
                'won' => Vehicle::allAgents()->whereIn('customer_id', $customers)->whereIn('status', self::WON_STATUSES)->whereDate('won_at', '<=', $asOf)->count(),
                'sales' => (int) $inv->sum('total_payable'),
                'receivables' => (int) $inv->sum(fn ($i) => $i->balance()),
            ];
        });

        return $this->render($request, 'agent_portfolio', [
            ['key' => 'agent', 'label' => 'Agent', 'link' => 'url'],
            ['key' => 'customers', 'label' => 'Customers', 'type' => 'int', 'total' => true],
            ['key' => 'active', 'label' => 'Active', 'type' => 'int', 'total' => true],
            ['key' => 'active_pct', 'label' => 'Active %', 'type' => 'pct'],
            ['key' => 'won', 'label' => 'Won Vehicles', 'type' => 'int', 'total' => true],
            ['key' => 'sales', 'label' => 'Invoiced Sales', 'type' => 'money', 'total' => true],
            ['key' => 'receivables', 'label' => 'Receivables', 'type' => 'money', 'total' => true],
        ], $rows, $this->filters(
            $this->fDate('as_of', 'As of', $asOf),
            $this->fAgent(),
            $this->fNumber('active_days', 'Active = activity in last (days)', $window),
        ), [
            'totals' => ['active_pct' => $this->pct($rows->sum('active'), $rows->sum('customers'))],
            'notes' => ["Customers are counted under their currently assigned agent. Active = a bid, won vehicle or approved payment in the {$window} days up to the as-of date.",
                        'Reassignment history is not tracked, so a customer moved between agents counts fully under the current agent.'],
        ]);
    }

    // ───────────────────────── 31 Agent Leaderboard ─────────────────────────

    public function leaderboard(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', today()->toDateString());
        $metric = in_array($request->metric, ['wins', 'sales', 'collections', 'earnings', 'score']) ? $request->metric : 'score';

        // Ranking is always computed company-wide; own-record users then only see their own row.
        $all = $this->agentUsers()
            ->map(fn (User $a) => ['id' => $a->id, 'agent' => $a->name] + $this->agentStats($a, $from, $to));

        $max = fn ($k) => max($all->max($k) ?: 0, 1);
        $all = $all->map(function ($r) use ($max) {
            $r['score'] = round(($r['wins'] / $max('wins') + $r['sales'] / $max('sales') + $r['collections'] / $max('collections') + $r['earnings'] / $max('earnings')) * 25, 1);
            return $r;
        })->sortByDesc($metric)->values()->map(fn ($r, $i) => ['rank' => $i + 1] + $r);

        $rows = $all->when($this->ownOnly(), fn ($c) => $c->where('id', auth()->id()))
            ->when(! $this->ownOnly() && $request->agent_id, fn ($c) => $c->where('id', (int) $request->agent_id));

        $metrics = ['score' => 'Overall score', 'wins' => 'Wins', 'sales' => 'Sales', 'collections' => 'Collections', 'earnings' => 'Commission'];

        return $this->render($request, 'agent_leaderboard', [
            ['key' => 'rank', 'label' => 'Rank', 'type' => 'int'],
            ['key' => 'agent', 'label' => 'Agent', 'class' => 'fw-semibold'],
            ['key' => 'wins', 'label' => 'Wins', 'type' => 'int'],
            ['key' => 'sales', 'label' => 'Sales', 'type' => 'money'],
            ['key' => 'collections', 'label' => 'Collections', 'type' => 'money'],
            ['key' => 'earnings', 'label' => 'Commission', 'type' => 'money'],
            ['key' => 'score', 'label' => 'Score', 'type' => 'pct'],
        ], $rows, $this->filters(
            $this->fDateRange('Period', 'from', 'to', $from, $to),
            $this->fSelect('metric', 'Rank by', $metrics, 'score', ''),
        ), [
            'notes' => ['Score (0–100) = equal 25% weights of wins, sales, collections and commission, each relative to the top agent in the period.',
                        'Agents limited to their own records see only their own rank, never their peers\' figures.'],
            'empty' => 'No sales agents found.',
        ]);
    }
}
