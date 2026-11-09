<?php

namespace App\Http\Controllers\Reports;

use App\Models\{AccountMapping, ChartOfAccount, Customer, JournalLine, Payment, Vehicle, Vendor};
use App\Services\LedgerService;
use Illuminate\Http\Request;

class ExecutiveReportController extends BaseReportController
{
    public function __construct(private LedgerService $ledger) {}

    /** Customer ids matching agent / destination filters, or null when unfiltered (and not own-record scoped). */
    private function customerIds(Request $request): ?array
    {
        $agentId = $this->agentId($request);
        if (! $agentId && ! $request->filled('country') && ! $request->filled('customer_id')) {
            return null;
        }
        return Customer::query()
            ->when($agentId, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->country, fn ($q, $v) => $q->where('country', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('id', $v))
            ->pluck('id')->all();
    }

    /** Ledger balance (natural direction) of the party accounts of these customers / vendors as of a date. */
    private function partyBalance(string $field, ?array $ids, string $asOf): int
    {
        $totals = $this->ledger->totals($asOf);
        return (int) ChartOfAccount::whereNotNull($field)
            ->when($ids !== null, fn ($q) => $q->whereIn($field, $ids ?: [0]))
            ->get()
            ->sum(function ($a) use ($totals) {
                $t = $totals[$a->id] ?? ['debit' => 0, 'credit' => 0];
                return $a->isDebitNature() ? $t['debit'] - $t['credit'] : $t['credit'] - $t['debit'];
            });
    }

    // ───────────────────────── 01 Executive Business Summary ─────────────────────────

    /** One row per month in the period; the metrics from the spec are the columns. */
    public function summary(Request $request)
    {
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to   = $request->input('to', today()->toDateString());
        $ids  = $this->customerIds($request);
        $byCustomer = fn ($q) => $q->when($ids !== null, fn ($w) => $w->whereIn('customer_id', $ids ?: [0]));
        $companyWide = ! $this->ownOnly() && ! $request->filled('agent_id') && ! $request->filled('country');

        $periods = $this->periods($from, $to, 'month');
        $data = array_map(fn () => ['purchases' => 0, 'won' => 0, 'invoiced' => 0, 'collections' => 0, 'gross' => 0, 'company' => 0], $periods);

        foreach ($this->range($byCustomer(Vehicle::whereIn('status', self::WON_STATUSES)), 'won_at', $from, $to)->get(['won_at', 'buying_price']) as $v) {
            if (isset($data[$k = $this->periodKey($v->won_at)])) { $data[$k]['purchases'] += (int) $v->buying_price; $data[$k]['won']++; }
        }
        foreach ($this->range($byCustomer($this->saleInvoices()), 'issued_at', $from, $to)->with('vehicle.costing')->get() as $i) {
            if (! isset($data[$k = $this->periodKey($i->issued_at)])) continue;
            $data[$k]['invoiced'] += $i->total_payable;
            $data[$k]['gross']    += $i->vehicle?->costing?->profit ?? 0;
            $data[$k]['company']  += $i->vehicle?->costing?->finalProfit() ?? 0;
        }
        foreach ($this->range($byCustomer(Payment::approved()->where('method', '!=', 'deposit')), 'approved_at', $from, $to)->get(['approved_at', 'amount']) as $p) {
            if (isset($data[$k = $this->periodKey($p->approved_at)])) $data[$k]['collections'] += $p->amount;
        }

        $agentParam = array_filter(['agent_id' => $request->agent_id]);
        $rows = collect($periods)->map(function ($p, $k) use ($data, $ids, $to, $companyWide, $agentParam) {
            $asOf = min($p['to'], $to);
            return [
                'reference' => $p['label'], 'url' => $this->reportLink('invoice_summary', ['from' => $p['from'], 'to' => $p['to']] + $agentParam),
                'won' => $data[$k]['won'],
                'purchases' => $data[$k]['purchases'],
                'invoiced' => $data[$k]['invoiced'],
                'collections' => $data[$k]['collections'],
                'gross' => $data[$k]['gross'],
                'company' => $data[$k]['company'],
                'receivables' => $this->partyBalance('customer_id', $ids, $asOf),
                'payables' => $companyWide ? $this->partyBalance('vendor_id', null, $asOf) : null,
                'event_date' => $p['from'] . ' → ' . $asOf,
            ];
        })->values();

        $last = $rows->last() ?? [];
        $cash = $companyWide ? $this->ledger->kindBalance(['cash', 'bank'], $to) : null;
        $sens = $this->canSensitive();
        $cards = array_values(array_filter([
            $sens ? ['label' => 'Purchases', 'value' => $rows->sum('purchases')] : null,
            ['label' => 'Invoiced Sales', 'value' => $rows->sum('invoiced')],
            ['label' => 'Collections', 'value' => $rows->sum('collections'), 'variant' => 'success'],
            $sens ? ['label' => 'Gross Profit', 'value' => $rows->sum('gross')] : null,
            $sens ? ['label' => 'Company Profit', 'value' => $rows->sum('company')] : null,
            ['label' => 'Receivables', 'value' => $last['receivables'] ?? 0, 'hint' => "as of $to", 'variant' => 'warning'],
            $sens && $companyWide ? ['label' => 'Payables', 'value' => $last['payables'] ?? 0, 'hint' => "as of $to"] : null,
            $sens && $cash !== null ? ['label' => 'Cash & Bank', 'value' => $cash, 'hint' => "as of $to"] : null,
        ]));

        return $this->render($request, 'executive_summary', [
            ['key' => 'reference', 'label' => 'Record / Reference', 'link' => 'url', 'class' => 'fw-semibold'],
            ['key' => 'won', 'label' => 'Won Vehicles', 'type' => 'int', 'total' => true],
            ['key' => 'purchases', 'label' => 'Purchases', 'type' => 'money', 'total' => true, 'sensitive' => true],
            ['key' => 'invoiced', 'label' => 'Invoiced Sales', 'type' => 'money', 'total' => true],
            ['key' => 'collections', 'label' => 'Collections', 'type' => 'money', 'total' => true],
            ['key' => 'gross', 'label' => 'Gross Profit', 'type' => 'money', 'total' => true, 'sensitive' => true],
            ['key' => 'company', 'label' => 'Company Profit', 'type' => 'money', 'total' => true, 'sensitive' => true],
            ['key' => 'receivables', 'label' => 'Receivables', 'type' => 'money'],
            ['key' => 'payables', 'label' => 'Payables', 'type' => 'money', 'sensitive' => true],
            ['key' => 'event_date', 'label' => 'Relevant Event Date', 'class' => 'small text-muted'],
        ], $rows, $this->filters(
            $this->fDateRange('Period', 'from', 'to', $from, $to),
            $this->fAgent(),
            $this->fCountry('Destination'),
        ), [
            'cards' => $cards,
            'totals' => ['receivables' => $last['receivables'] ?? 0, 'payables' => $last['payables'] ?? null],
            'notes' => ['Purchases by won date; sales and profit by invoice date; collections (approved, excluding deposit adjustments) by approval date; receivables and payables are ledger balances at each month end.',
                        'Payables and cash are company-wide and are hidden when an agent or destination filter is applied. Click a month to drill into its invoices.'],
        ]);
    }

    // ───────────────────────── 02 Business Performance Trends ─────────────────────────

    public function trends(Request $request)
    {
        $from = $request->input('from', now()->subMonths(11)->startOfMonth()->toDateString());
        $to   = $request->input('to', today()->toDateString());
        $g    = $request->input('granularity', 'month') === 'quarter' ? 'quarter' : 'month';
        $agentId = $this->agentId($request);

        $periods = $this->periods($from, $to, $g);
        $blank = fn () => ['won' => 0, 'invoiced' => 0, 'collections' => 0, 'gross' => 0, 'company' => 0];
        $data = array_map(fn () => $blank(), $periods);

        $won = Vehicle::whereIn('status', self::WON_STATUSES)->when($agentId, fn ($q, $v) => $q->where('agent_id', $v));
        foreach ($this->range($won, 'won_at', $from, $to)->get(['won_at']) as $v) {
            if (isset($data[$k = $this->periodKey($v->won_at, $g)])) $data[$k]['won']++;
        }

        $inv = $this->saleInvoices()->with('vehicle.costing')->when($agentId, fn ($q, $v) => $q->where('agent_id', $v));
        foreach ($this->range($inv, 'issued_at', $from, $to)->get() as $i) {
            if (! isset($data[$k = $this->periodKey($i->issued_at, $g)])) continue;
            $data[$k]['invoiced'] += $i->total_payable;
            $data[$k]['gross']    += $i->vehicle?->costing?->profit ?? 0;
            $data[$k]['company']  += $i->vehicle?->costing?->finalProfit() ?? 0;
        }

        $pay = Payment::approved()->where('method', '!=', 'deposit')
            ->when($agentId, fn ($q, $v) => $q->whereHas('customer', fn ($c) => $c->where('agent_id', $v)));
        foreach ($this->range($pay, 'approved_at', $from, $to)->get(['approved_at', 'amount']) as $p) {
            if (isset($data[$k = $this->periodKey($p->approved_at, $g)])) $data[$k]['collections'] += $p->amount;
        }

        $prev = null;
        $rows = collect();
        foreach ($periods as $k => $p) {
            $d = $data[$k];
            $rows->push([
                'period' => $p['label'], 'url' => $this->reportLink('invoice_summary', ['from' => $p['from'], 'to' => $p['to']]),
                'won' => $d['won'], 'invoiced' => $d['invoiced'], 'collections' => $d['collections'],
                'gross' => $d['gross'], 'company' => $d['company'],
                'growth' => $prev ? round(($d['invoiced'] - $prev) / $prev * 100, 1) : null,
                'range' => $p['from'] . ' → ' . $p['to'],
            ]);
            $prev = $d['invoiced'];
        }

        return $this->render($request, 'business_trends', [
            ['key' => 'period', 'label' => 'Period (Record / Reference)', 'link' => 'url'],
            ['key' => 'won', 'label' => 'Won Vehicles', 'type' => 'int', 'total' => true],
            ['key' => 'invoiced', 'label' => 'Invoiced Sales', 'type' => 'money', 'total' => true],
            ['key' => 'collections', 'label' => 'Collections', 'type' => 'money', 'total' => true],
            ['key' => 'gross', 'label' => 'Gross Profit', 'type' => 'money', 'total' => true, 'sensitive' => true],
            ['key' => 'company', 'label' => 'Company Profit', 'type' => 'money', 'total' => true, 'sensitive' => true],
            ['key' => 'growth', 'label' => 'Growth %', 'type' => 'pct'],
            ['key' => 'range', 'label' => 'Relevant Event Date', 'class' => 'small text-muted'],
        ], $rows, $this->filters(
            $this->fDateRange('Date range', 'from', 'to', $from, $to),
            $this->fGranularity(),
            $this->fAgent(),
        ), [
            'chart' => ['type' => 'bar', 'labels' => $rows->pluck('period'), 'series' => [
                ['label' => 'Invoiced Sales', 'data' => $rows->pluck('invoiced')],
                ['label' => 'Collections', 'data' => $rows->pluck('collections')],
            ]],
            'notes' => ['Won vehicles by won date, sales and profit by invoice date, collections by approval date.',
                        'Growth % = (current − prior) ÷ prior invoiced sales; blank when the prior period is zero.'],
        ]);
    }

    // ───────────────────────── 04 Business Exposure ─────────────────────────

    /** Exposure components at one date. Vehicle costs already posted to a vendor are inside payables, so only unposted ones are added. */
    private function exposureAt(string $asOf, ?array $customerIds, ?array $vendorIds): array
    {
        $cash = $customerIds || $vendorIds ? 0 : $this->ledger->kindBalance(['cash', 'bank'], $asOf);
        $receivables = $vendorIds && ! $customerIds ? 0 : $this->partyBalance('customer_id', $customerIds, $asOf);
        $payables    = $customerIds && ! $vendorIds ? 0 : $this->partyBalance('vendor_id', $vendorIds, $asOf);

        $deposits = 0;
        if (! ($vendorIds && ! $customerIds)) {
            try {
                $depAcc = AccountMapping::accountFor('customer_deposits');
                $q = JournalLine::query()->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                    ->where('journal_lines.account_id', $depAcc->id)->whereDate('journal_entries.date', '<=', $asOf)
                    ->when($customerIds, fn ($q) => $q->where('journal_lines.party_type', (new Customer)->getMorphClass())->whereIn('journal_lines.party_id', $customerIds));
                $deposits = (int) (clone $q)->sum('journal_lines.credit') - (int) (clone $q)->sum('journal_lines.debit');
            } catch (\RuntimeException) {
                $deposits = 0;
            }
        }

        $unposted = $vendorIds ? collect() : Vehicle::allAgents()->whereIn('status', self::WON_STATUSES)->whereNull('vendor_id')
            ->whereDate('won_at', '<=', $asOf)
            ->when($customerIds, fn ($q) => $q->whereIn('customer_id', $customerIds))
            ->with('costing')->get();
        $unpaid = (int) $unposted->sum(fn ($v) => $v->costing?->total_costing ?: (int) $v->buying_price);

        return ['cash' => $cash, 'receivables' => $receivables, 'payables' => $payables, 'deposits' => $deposits,
                'unpaid' => $unpaid, 'unposted_count' => $unposted->count(),
                'net' => $cash + $receivables - $payables - $deposits - $unpaid];
    }

    public function exposure(Request $request)
    {
        $asOf = $this->asOf($request);
        $customerIds = $request->filled('customer_id') ? [(int) $request->customer_id] : null;
        $vendorIds   = $request->filled('vendor_id') ? [(int) $request->vendor_id] : null;

        // Chosen as-of date first, then the five previous month ends for trend.
        $dates = [$asOf];
        $d = \Carbon\Carbon::parse($asOf)->startOfMonth()->subDay();
        for ($i = 0; $i < 5; $i++) { $dates[] = $d->toDateString(); $d = $d->startOfMonth()->subDay(); }

        $rows = collect($dates)->map(function ($date, $i) use ($customerIds, $vendorIds) {
            $e = $this->exposureAt($date, $customerIds, $vendorIds);
            return [
                'reference' => $i === 0 ? "As of $date" : "Month end $date",
                'url' => $this->link('reports.financial_reports', 'accounting.balance_sheet', ['as_of' => $date]),
                'cash' => $e['cash'], 'receivables' => $e['receivables'], 'payables' => $e['payables'],
                'deposits' => $e['deposits'], 'unpaid' => $e['unpaid'], 'net' => $e['net'], 'date' => $date,
                '_class' => $i === 0 ? 'fw-bold' : '', '_e' => $e,
            ];
        });
        $now = $rows->first()['_e'];

        return $this->render($request, 'business_exposure', [
            ['key' => 'reference', 'label' => 'Record / Reference', 'link' => 'url'],
            ['key' => 'cash', 'label' => 'Cash/Bank', 'type' => 'money'],
            ['key' => 'receivables', 'label' => 'Receivables', 'type' => 'money'],
            ['key' => 'payables', 'label' => 'Vendor Payables', 'type' => 'money'],
            ['key' => 'deposits', 'label' => 'Deposits Held', 'type' => 'money'],
            ['key' => 'unpaid', 'label' => 'Unpaid Vehicle Costs', 'type' => 'money'],
            ['key' => 'net', 'label' => 'Net Exposure', 'type' => 'money'],
            ['key' => 'date', 'label' => 'Relevant Event Date', 'type' => 'date'],
        ], $rows, $this->filters(
            $this->fDate('as_of', 'As-of date', $asOf),
            $this->fVendor(),
            $this->fCustomer(),
        ), [
            'cards' => [
                ['label' => 'Cash / Bank', 'value' => $now['cash']],
                ['label' => 'Receivables', 'value' => $now['receivables']],
                ['label' => 'Vendor Payables', 'value' => $now['payables'], 'variant' => 'warning'],
                ['label' => 'Deposits Held', 'value' => $now['deposits'], 'variant' => 'warning'],
                ['label' => 'Unpaid Vehicle Costs', 'value' => $now['unpaid'], 'hint' => $now['unposted_count'] . ' vehicle(s) without vendor', 'variant' => 'warning'],
                ['label' => 'Net Exposure', 'value' => $now['net'], 'variant' => $now['net'] < 0 ? 'danger' : 'success'],
            ],
            'notes' => ['Net Exposure = Cash/Bank + Receivables − Vendor Payables − Deposits Held − Unpaid Vehicle Costs, all from ledger balances at each date.',
                        'Vehicle costs are posted to the vendor payable once a vehicle has a vendor and buying price, so "Unpaid Vehicle Costs" only counts won vehicles with no vendor yet — nothing is counted twice.',
                        'Deposits Held is the deposit liability from issued deposit invoices; any unpaid part also sits in receivables, so it nets out. Filtering by vendor or customer shows only that party\'s components.'],
        ]);
    }
}
