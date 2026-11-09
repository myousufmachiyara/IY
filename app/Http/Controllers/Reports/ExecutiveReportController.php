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

    public function summary(Request $request)
    {
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to   = $request->input('to', today()->toDateString());
        $ids  = $this->customerIds($request);
        $agentId = $this->agentId($request);

        $purchases = Vehicle::whereIn('status', self::WON_STATUSES)
            ->when($ids !== null, fn ($q) => $q->whereIn('customer_id', $ids ?: [0]));
        $this->range($purchases, 'won_at', $from, $to);
        $purchaseCount = (clone $purchases)->count();
        $purchaseSum   = (int) (clone $purchases)->sum('buying_price');

        $invoices = $this->saleInvoices()->when($ids !== null, fn ($q) => $q->whereIn('customer_id', $ids ?: [0]));
        $this->range($invoices, 'issued_at', $from, $to);
        $invoiced = (clone $invoices)->with('vehicle.costing')->get();

        $collections = Payment::approved()->where('method', '!=', 'deposit')
            ->when($ids !== null, fn ($q) => $q->whereIn('customer_id', $ids ?: [0]));
        $this->range($collections, 'approved_at', $from, $to);
        $collected = (int) $collections->sum('amount');

        $gross   = (int) $invoiced->sum(fn ($i) => $i->vehicle?->costing?->profit ?? 0);
        $company = (int) $invoiced->sum(fn ($i) => $i->vehicle?->costing?->finalProfit() ?? 0);

        $receivables = $this->partyBalance('customer_id', $ids, $to);

        $range = ['from' => $from, 'to' => $to] + array_filter(['agent_id' => $request->agent_id]);
        $rows = collect([
            ['metric' => 'Purchases (won vehicles)', 'count' => $purchaseCount, 'amount' => $purchaseSum, 'basis' => 'Buying price, by won date',
             'url' => $this->reportLink('bid_won', $range), 'sensitive' => true],
            ['metric' => 'Invoiced Sales', 'count' => $invoiced->count(), 'amount' => (int) $invoiced->sum('total_payable'), 'basis' => 'Issued sale invoices, by invoice date',
             'url' => $this->reportLink('invoice_summary', $range)],
            ['metric' => 'Collections', 'count' => null, 'amount' => $collected, 'basis' => 'Approved customer payments (excl. deposit adjustments), by approval date',
             'url' => $this->reportLink('payment_collections', $range)],
            ['metric' => 'Gross Profit', 'count' => null, 'amount' => $gross, 'basis' => 'Sale − total vehicle cost, vehicles invoiced in period',
             'url' => $this->reportLink('vehicle_profitability', ['inv_from' => $from, 'inv_to' => $to] + array_filter(['agent_id' => $request->agent_id])), 'sensitive' => true],
            ['metric' => 'Company Profit', 'count' => null, 'amount' => $company, 'basis' => 'Gross profit − agent earnings',
             'url' => $this->reportLink('vehicle_profitability', ['inv_from' => $from, 'inv_to' => $to]), 'sensitive' => true],
            ['metric' => 'Receivables', 'count' => null, 'amount' => $receivables, 'basis' => "Customer ledger balance as of $to",
             'url' => $this->reportLink('receivables_aging', ['as_of' => $to])],
        ]);

        // Company-wide balances — never shown to own-record users.
        if (! $this->ownOnly() && ! $request->filled('agent_id') && ! $request->filled('country')) {
            $rows->push(['metric' => 'Vendor Payables', 'count' => null, 'amount' => $this->partyBalance('vendor_id', null, $to),
                'basis' => "Vendor ledger balance as of $to", 'url' => $this->reportLink('vendor_payables', ['as_of' => $to]), 'sensitive' => true]);
            $rows->push(['metric' => 'Cash & Bank', 'count' => null, 'amount' => $this->ledger->kindBalance(['cash', 'bank'], $to),
                'basis' => "Cash and bank accounts as of $to", 'url' => $this->link('accounting.index', 'accounting.cash_bank', ['to' => $to]), 'sensitive' => true]);
        }

        if (! $this->canSensitive()) {
            $rows = $rows->reject(fn ($r) => $r['sensitive'] ?? false);
        }

        $cards = $rows->take(6)->map(fn ($r) => ['label' => $r['metric'], 'value' => $r['amount']])->values()->all();

        return $this->render($request, 'executive_summary', [
            ['key' => 'metric', 'label' => 'Metric', 'link' => 'url', 'class' => 'fw-semibold'],
            ['key' => 'count', 'label' => 'Records', 'type' => 'int'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
            ['key' => 'basis', 'label' => 'Basis / event date', 'class' => 'small text-muted'],
        ], $rows, $this->filters(
            $this->fDateRange('Period', 'from', 'to', $from, $to),
            $this->fAgent(),
            $this->fCountry('Destination'),
        ), [
            'cards' => $cards,
            'notes' => ['Click a metric to drill down into the report that produces it.',
                        'Payables and cash are company-wide and are hidden when an agent or destination filter is applied.'],
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
            ['key' => 'period', 'label' => 'Period', 'link' => 'url'],
            ['key' => 'won', 'label' => 'Won Vehicles', 'type' => 'int', 'total' => true],
            ['key' => 'invoiced', 'label' => 'Invoiced Sales', 'type' => 'money', 'total' => true],
            ['key' => 'collections', 'label' => 'Collections', 'type' => 'money', 'total' => true],
            ['key' => 'gross', 'label' => 'Gross Profit', 'type' => 'money', 'total' => true, 'sensitive' => true],
            ['key' => 'company', 'label' => 'Company Profit', 'type' => 'money', 'total' => true, 'sensitive' => true],
            ['key' => 'growth', 'label' => 'Sales Growth %', 'type' => 'pct'],
            ['key' => 'range', 'label' => 'Dates', 'class' => 'small text-muted'],
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

    public function exposure(Request $request)
    {
        $asOf = $this->asOf($request);
        $customerIds = $request->filled('customer_id') ? [(int) $request->customer_id] : null;
        $vendorIds   = $request->filled('vendor_id') ? [(int) $request->vendor_id] : null;
        $totals = $this->ledger->totals($asOf);

        $cash = $customerIds || $vendorIds ? 0 : $this->ledger->kindBalance(['cash', 'bank'], $asOf);
        $receivables = $vendorIds && ! $customerIds ? 0 : $this->partyBalance('customer_id', $customerIds, $asOf);
        $payables    = $customerIds && ! $vendorIds ? 0 : $this->partyBalance('vendor_id', $vendorIds, $asOf);

        // Deposits held = customer-deposit liability (credit − debit), per customer when filtered.
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

        // Won vehicles whose cost is NOT yet in the vendor payable (no vendor or no buying price posted) —
        // anything already posted is inside Vendor Payables, so it is never counted twice.
        $unposted = Vehicle::allAgents()->whereIn('status', self::WON_STATUSES)->whereNull('vendor_id')
            ->whereDate('won_at', '<=', $asOf)
            ->when($customerIds, fn ($q) => $q->whereIn('customer_id', $customerIds))
            ->when($vendorIds, fn ($q) => $q->whereRaw('1 = 0'))
            ->with('costing')->get();
        $unpaidVehicleCost = (int) $unposted->sum(fn ($v) => $v->costing?->total_costing ?: (int) $v->buying_price);

        $net = $cash + $receivables - $payables - $deposits - $unpaidVehicleCost;

        $rows = collect([
            ['component' => 'Cash / Bank', 'sign' => '+', 'amount' => $cash, 'calc' => 'Cash + bank ledger balances', 'url' => $this->link('accounting.index', 'accounting.cash_bank', ['to' => $asOf])],
            ['component' => 'Customer Receivables', 'sign' => '+', 'amount' => $receivables, 'calc' => 'Customer party-account ledger balances', 'url' => $this->reportLink('receivables_aging', ['as_of' => $asOf] + array_filter(['customer_id' => $request->customer_id]))],
            ['component' => 'Vendor Payables', 'sign' => '−', 'amount' => -$payables, 'calc' => 'Vendor party-account ledger balances (includes every posted vehicle cost)', 'url' => $this->reportLink('vendor_payables', ['as_of' => $asOf] + array_filter(['vendor_id' => $request->vendor_id]))],
            ['component' => 'Customer Deposits Held', 'sign' => '−', 'amount' => -$deposits, 'calc' => 'Customer security-deposit liability (issued deposit invoices; any unpaid part is also inside receivables, so it nets out)', 'url' => $this->reportLink('security_deposits', ['as_of' => $asOf])],
            ['component' => 'Unposted Vehicle Costs', 'sign' => '−', 'amount' => -$unpaidVehicleCost, 'calc' => $unposted->count() . ' won vehicle(s) with no vendor yet — not in vendor payables', 'url' => $this->reportLink('awaiting_shipment')],
        ]);

        return $this->render($request, 'business_exposure', [
            ['key' => 'component', 'label' => 'Component', 'link' => 'url', 'class' => 'fw-semibold'],
            ['key' => 'sign', 'label' => '±'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'total' => true],
            ['key' => 'calc', 'label' => 'Calculation', 'class' => 'small text-muted'],
        ], $rows, $this->filters(
            $this->fDate('as_of', 'As of', $asOf),
            $this->fVendor(),
            $this->fCustomer(),
        ), [
            'cards' => [
                ['label' => 'Cash / Bank', 'value' => $cash],
                ['label' => 'Receivables', 'value' => $receivables],
                ['label' => 'Payables + Deposits + Unposted', 'value' => $payables + $deposits + $unpaidVehicleCost, 'variant' => 'warning'],
                ['label' => 'Net Exposure', 'value' => $net, 'variant' => $net < 0 ? 'danger' : 'success'],
            ],
            'notes' => ['Net Exposure = Cash/Bank + Receivables − Vendor Payables − Deposits Held − Unposted Vehicle Costs.',
                        'Vehicle costs are posted to the vendor payable as soon as a vehicle has a vendor and a buying price; only vehicles without a vendor are added separately, so nothing is counted twice.',
                        'Filtering by vendor or customer shows only that party\'s components; cash is shown only when unfiltered.'],
        ]);
    }
}
