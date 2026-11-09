<?php

namespace App\Http\Controllers\Reports;

use App\Models\{Bid, Customer, Document, Invoice, Payment, Shipment, Vehicle};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SalesReportController extends BaseReportController
{
    private function customers(Request $request)
    {
        return Customer::query()->with('agent')
            ->when($this->agentId($request), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('id', $v))
            ->when($request->country, fn ($q, $v) => $q->where('country', $v))
            ->orderBy('name');
    }

    private function customerUrl(Customer $c): ?string
    {
        return $this->reportLink('customer_360', ['customer_id' => $c->id]) ?? $this->link('customers.show', 'customers.show', $c);
    }

    // ───────────────────────── 05 Customer-wise Sales ─────────────────────────

    public function customerWise(Request $request)
    {
        $from = $request->from; $to = $request->to;

        $rows = $this->customers($request)->get()->map(function (Customer $c) use ($from, $to) {
            $inv = $this->range($this->saleInvoices()->where('customer_id', $c->id), 'issued_at', $from, $to)->get();
            return [
                'customer' => $c->name, 'customer_url' => $this->customerUrl($c),
                'country'  => $c->country,
                'agent'    => $c->agent->name ?? '—',
                'vehicles' => $inv->whereNotNull('vehicle_id')->count(),
                'invoiced' => (int) $inv->sum('total_payable'),
                'paid'     => (int) $inv->sum('amount_paid'),
                'outstanding' => (int) $inv->sum(fn ($i) => $i->balance()),
                'last_purchase' => optional(Vehicle::where('customer_id', $c->id)->whereIn('status', self::WON_STATUSES)->max('won_at'), fn ($d) => Carbon::parse($d)->toDateString()),
                'last_invoice'  => optional($inv->max('issued_at'), fn ($d) => $d->toDateString()),
            ];
        })->filter(fn ($r) => $r['invoiced'] > 0 || $r['last_purchase'])->sortByDesc('invoiced');

        return $this->render($request, 'customer_wise', [
            ['key' => 'customer', 'label' => 'Customer (Record / Reference)', 'link' => 'customer_url'],
            ['key' => 'country', 'label' => 'Country'],
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'vehicles', 'label' => 'Vehicles', 'type' => 'int', 'total' => true],
            ['key' => 'invoiced', 'label' => 'Invoiced Sales', 'type' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Approved Payments', 'type' => 'money', 'total' => true],
            ['key' => 'outstanding', 'label' => 'Outstanding', 'type' => 'money', 'total' => true],
            ['key' => 'last_purchase', 'label' => 'Last Purchase', 'type' => 'date'],
            ['key' => 'last_invoice', 'label' => 'Relevant Event Date (last invoice)', 'type' => 'date'],
        ], $rows, $this->filters(
            $this->fDateRange('Invoice date'),
            $this->fCustomer(),
            $this->fAgent(),
            $this->fCountry(),
        ), ['notes' => ['Sales from issued sale invoices (cancelled and deposit invoices excluded); payments are approved payments only.']]);
    }

    // ───────────────────────── 06 Customer 360° ─────────────────────────

    private const C360_COLUMNS = [
        ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
        ['key' => 'type', 'label' => 'Type', 'class' => 'fw-semibold'],
        ['key' => 'reference', 'label' => 'Reference', 'link' => 'url'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
        ['key' => 'detail', 'label' => 'Detail', 'class' => 'small text-muted'],
    ];

    public function customer360(Request $request)
    {
        $customers = $this->customers(new Request(array_filter(['agent_id' => $request->agent_id])))->pluck('name', 'id')->all();
        $filters = $this->filters(
            $this->fSelect('customer_id', 'Customer', $customers, null, '— choose a customer —'),
            $this->fDateRange('Date range'),
        );

        $customer = $request->filled('customer_id') ? Customer::with('agent', 'ports', 'depositInvoice')->find($request->customer_id) : null;
        if (! $customer) {
            return $this->render($request, 'customer_360', self::C360_COLUMNS, collect(), $filters,
                ['view' => 'reports.v2.customer_360', 'empty' => 'Choose a customer above to see their full history.']);
        }

        $from = $request->from; $to = $request->to;
        $sensitive = $this->canSensitive();
        $within = fn ($d) => $d && (! $from || Carbon::parse($d)->toDateString() >= $from) && (! $to || Carbon::parse($d)->toDateString() <= $to);
        $timeline = collect();

        $vehicles = Vehicle::where('customer_id', $customer->id)->with('invoice', 'shipment', 'costing', 'bid')->get();
        foreach ($vehicles as $v) {
            if (in_array($v->status, ['requirement', 'bidding']) && $within($v->created_at)) {
                $timeline->push(['date' => $v->created_at, 'type' => 'Requirement', 'reference' => $v->label(), 'url' => $this->link('vehicle_requirement.show', 'vehicles.show', $v),
                    'status' => $this->statusBadge($v->status), 'amount' => $v->budget ?: null, 'detail' => 'Budget']);
            }
            if ($v->won_at && $within($v->won_at)) {
                $timeline->push(['date' => $v->won_at, 'type' => 'Won Vehicle', 'reference' => $v->label() . ($v->chassis_no ? " ({$v->chassis_no})" : ''), 'url' => $this->link('vehicle_requirement.show', 'vehicles.show', $v),
                    'status' => $this->statusBadge($v->status), 'amount' => $sensitive ? $v->buying_price : null, 'detail' => $sensitive ? 'Buying price' : '']);
            }
        }

        foreach (Bid::where('customer_id', $customer->id)->get() as $b) {
            if (! $within($b->auction_date)) continue;
            $timeline->push(['date' => $b->auction_date, 'type' => 'Bid', 'reference' => trim("Lot {$b->lot_no} · {$b->year} {$b->make} {$b->model}"), 'url' => null,
                'status' => $this->statusBadge($b->result ?: 'pending'), 'amount' => $b->result === 'won' ? $b->won_amount : $b->max_bid, 'detail' => $b->result === 'won' ? 'Won amount' : 'Max bid']);
        }

        foreach (Invoice::where('customer_id', $customer->id)->with('vehicle')->get() as $i) {
            if (! $within($i->issued_at)) continue;
            $timeline->push(['date' => $i->issued_at, 'type' => $i->isDepositInvoice() ? 'Deposit Invoice' : 'Invoice', 'reference' => $i->invoice_no . ($i->vehicle ? ' · ' . $i->vehicle->label() : ''),
                'url' => $this->link('invoices.show', 'invoices.show', $i), 'status' => $this->statusBadge($i->status), 'amount' => $i->total_payable, 'detail' => 'Outstanding ¥' . number_format($i->balance())]);
        }

        foreach (Payment::where('customer_id', $customer->id)->with('invoice')->get() as $p) {
            if (! $within($p->paid_at)) continue;
            $timeline->push(['date' => $p->paid_at, 'type' => $p->method === 'deposit' ? 'Deposit Applied' : 'Payment', 'reference' => ($p->invoice->invoice_no ?? '—') . ($p->reference ? " · {$p->reference}" : ''),
                'url' => $this->link('payments.index', 'payments.customer_ledger', $customer), 'status' => $this->statusBadge($p->status), 'amount' => $p->amount, 'detail' => Str::headline((string) $p->method)]);
        }

        foreach (Shipment::where('customer_id', $customer->id)->withCount('vehicles')->get() as $s) {
            $d = $s->shipment_date ?? $s->created_at;
            if (! $within($d)) continue;
            $timeline->push(['date' => $d, 'type' => 'Shipment', 'reference' => "#{$s->id} · {$s->method} · " . ($s->container_no ?: ($s->bl_no ?: '—')),
                'url' => $this->link('shipments.show', 'shipments.show', $s), 'status' => $this->statusBadge($s->status), 'amount' => null, 'detail' => $s->vehicles_count . ' vehicle(s)']);
        }

        foreach (Document::whereIn('vehicle_id', $vehicles->pluck('id'))->with('vehicle')->get() as $doc) {
            if (! $within($doc->created_at)) continue;
            $timeline->push(['date' => $doc->created_at, 'type' => 'Document', 'reference' => ($doc->title ?: Str::headline((string) $doc->type)) . ' · ' . $doc->vehicle?->label(),
                'url' => $this->link('documents.index', 'documents.index', $doc->vehicle_id), 'status' => $this->badge($doc->visible_to_customer ? 'Released' : 'Internal', $doc->visible_to_customer ? 'success' : 'secondary'), 'amount' => null, 'detail' => $doc->is_final_clearance ? 'Final clearance' : '']);
        }

        $timeline = $timeline->sortByDesc(fn ($r) => Carbon::parse($r['date'])->timestamp)->values()
            ->map(fn ($r) => ['date' => Carbon::parse($r['date'])->toDateString()] + $r);

        $saleInv = $this->saleInvoices()->where('customer_id', $customer->id)->get();
        $dep = $customer->depositInvoice;
        $applied = (int) Payment::where('customer_id', $customer->id)->where('method', 'deposit')->approved()->sum('amount');

        $sections = [
            'Customer' => [
                'Customer No' => $customer->customer_no, 'Name' => $customer->name, 'Agent' => $customer->agent->name ?? '—',
                'Country' => $customer->country, 'Phone' => $customer->phone, 'Email' => $customer->email,
                'Ports' => $customer->ports->pluck('name')->join(', ') ?: '—', 'Status' => Str::headline((string) $customer->status),
                'Profile' => $customer->profile_completed_at ? 'Complete' : 'Incomplete',
            ],
            'Deposit' => [
                'Deposit Invoice' => $dep?->invoice_no ?? '—', 'Invoiced' => $dep ? '¥' . number_format($dep->total_payable) : '—',
                'Collected' => $dep ? '¥' . number_format($dep->amount_paid) : '—', 'Applied to Invoices' => '¥' . number_format($applied),
                'Status' => Str::headline($customer->depositWorkflowStatus()),
            ],
            'Totals' => [
                'Requirements Open' => $vehicles->whereIn('status', ['requirement', 'bidding'])->count(),
                'Bids' => Bid::where('customer_id', $customer->id)->count(),
                'Won Vehicles' => $vehicles->whereIn('status', self::WON_STATUSES)->count(),
                'Invoiced' => '¥' . number_format($saleInv->sum('total_payable')),
                'Paid' => '¥' . number_format($saleInv->sum('amount_paid')),
                'Outstanding' => '¥' . number_format($saleInv->sum(fn ($i) => $i->balance())),
                'Shipments' => Shipment::where('customer_id', $customer->id)->count(),
            ],
        ];

        return $this->render($request, 'customer_360', self::C360_COLUMNS, $timeline, $filters, [
            'view' => 'reports.v2.customer_360',
            'sections' => $sections,
            'notes' => ['Vehicle costing and profit are never shown here; buying prices appear only for users allowed to see confidential report fields.'],
        ]);
    }

    // ───────────────────────── 07 Customer Profitability ─────────────────────────

    public function customerProfitability(Request $request)
    {
        $from = $request->from; $to = $request->to;

        $rows = $this->customers($request)->get()->map(function (Customer $c) use ($from, $to) {
            $vehicles = $this->range(Vehicle::where('customer_id', $c->id)->whereIn('status', self::WON_STATUSES), 'won_at', $from, $to)->with('costing')->get();
            $sales = (int) $vehicles->sum(fn ($v) => $v->selling_price ?: ($v->costing?->sale_price ?? 0));
            $gross = (int) $vehicles->sum(fn ($v) => $v->costing?->profit ?? 0);
            return [
                'customer' => $c->name, 'customer_url' => $this->customerUrl($c), 'agent' => $c->agent->name ?? '—',
                'vehicles' => $vehicles->count(), 'sales' => $sales,
                'cost' => (int) $vehicles->sum(fn ($v) => $v->costing?->total_costing ?? (int) $v->buying_price),
                'gross' => $gross,
                'agent_earning' => (int) $vehicles->sum(fn ($v) => $v->costing?->agentEarning() ?? 0),
                'company' => (int) $vehicles->sum(fn ($v) => $v->costing?->finalProfit() ?? 0),
                'margin' => $this->pct($gross, $sales),
                'event_date' => optional($vehicles->max('won_at'), fn ($d) => $d->toDateString()),
            ];
        })->filter(fn ($r) => $r['vehicles'] > 0)->sortByDesc('gross');

        return $this->render($request, 'customer_profitability', [
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'vehicles', 'label' => 'Vehicles', 'type' => 'int', 'total' => true],
            ['key' => 'sales', 'label' => 'Sales Value', 'type' => 'money', 'total' => true],
            ['key' => 'cost', 'label' => 'Total Cost', 'type' => 'money', 'total' => true],
            ['key' => 'gross', 'label' => 'Gross Profit', 'type' => 'money', 'total' => true],
            ['key' => 'agent_earning', 'label' => 'Agent Earnings', 'type' => 'money', 'total' => true],
            ['key' => 'company', 'label' => 'Company Profit', 'type' => 'money', 'total' => true],
            ['key' => 'margin', 'label' => 'Margin %', 'type' => 'pct'],
            ['key' => 'event_date', 'label' => 'Relevant Event Date (last won)', 'type' => 'date'],
        ], $rows, $this->filters(
            $this->fDateRange('Won date'),
            $this->fCustomer(),
            $this->fAgent(),
        ), [
            'totals' => ['margin' => $this->pct($rows->sum('gross'), $rows->sum('sales'))],
            'notes' => ['Uses the vehicle costing calculation; includes every post-win status (won, invoiced, dispatched, arrived, delivered). Margin % = Gross Profit ÷ Sales Value.'],
        ]);
    }

    // ───────────────────────── 08 Customer Purchase History ─────────────────────────

    public function purchaseHistory(Request $request)
    {
        $agentId = $this->agentId($request);
        $q = Bid::won()->with('customer', 'vehicle.invoice', 'vehicle.shipment', 'agent')
            ->when($agentId, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->make, fn ($q, $v) => $q->where('make', 'like', "%$v%"));
        $bids = $q->get()->filter(function ($b) use ($request) {
            $d = $b->vehicle?->won_at ?? $b->auction_date;
            $d = $d ? Carbon::parse($d)->toDateString() : null;
            return (! $request->from || ($d && $d >= $request->from)) && (! $request->to || ($d && $d <= $request->to));
        });

        $rows = $bids->map(function ($b) {
            $v = $b->vehicle;
            $inv = $v?->invoice;
            $shipStatus = $v?->shipment ? Str::headline($v->shipment->status) : ($v ? ($v->yardStatus() ?? Str::headline($v->status)) : '—');
            return [
                'won_date' => ($v?->won_at ?? $b->auction_date)?->toDateString(),
                'customer' => $b->customer->name ?? '—', 'customer_url' => $b->customer ? $this->customerUrl($b->customer) : null,
                'agent' => $b->agent->name ?? '—',
                'chassis' => $v?->chassis_no ?: $b->chassis_no, 'vehicle_url' => $v ? $this->link('vehicle_requirement.show', 'vehicles.show', $v) : null,
                'make' => $v?->make ?: $b->make, 'model' => $v?->model ?: $b->model, 'year' => $v?->year ?: $b->year,
                'buy_price' => $v?->buying_price ?: $b->won_amount,
                'invoice' => $inv?->invoice_no, 'invoice_url' => $inv ? $this->link('invoices.show', 'invoices.show', $inv) : null,
                'invoice_status' => $inv ? $this->statusBadge($inv->status) : $this->badge('Not invoiced'),
                'shipment' => $shipStatus,
            ];
        })->sortByDesc('won_date');

        return $this->render($request, 'customer_purchase_history', [
            ['key' => 'won_date', 'label' => 'Won Date', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'chassis', 'label' => 'Chassis', 'link' => 'vehicle_url'],
            ['key' => 'make', 'label' => 'Make'],
            ['key' => 'model', 'label' => 'Model'],
            ['key' => 'year', 'label' => 'Year'],
            ['key' => 'buy_price', 'label' => 'Buy Price', 'type' => 'money', 'total' => true, 'sensitive' => true],
            ['key' => 'invoice', 'label' => 'Invoice', 'link' => 'invoice_url'],
            ['key' => 'invoice_status', 'label' => 'Invoice Status', 'type' => 'badge'],
            ['key' => 'shipment', 'label' => 'Shipment Status'],
        ], $rows, $this->filters(
            $this->fDateRange('Won date'),
            $this->fCustomer(),
            $this->fAgent(),
            $this->fText('make', 'Make'),
        ), ['notes' => ['Won bids joined to their vehicles — history stays even after the vehicle moves on to invoiced, shipped or delivered.']]);
    }

    // ───────────────────────── 09 Invoice Summary ─────────────────────────

    public function invoiceSummary(Request $request)
    {
        $status = $request->status;
        $q = Invoice::with('customer', 'vehicle')
            ->when($this->agentId($request), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->type === 'deposit', fn ($q) => $q->where('invoice_type', 'deposit'))
            ->when($request->type === 'sale', fn ($q) => $q->where(fn ($w) => $w->where('invoice_type', '!=', 'deposit')->orWhereNull('invoice_type')))
            ->when($status, fn ($q, $v) => $q->where('status', $v), fn ($q) => $q->where('status', '!=', 'cancelled'));
        $this->range($q, 'issued_at', $request->from, $request->to);

        $rows = $q->get()->filter(function ($i) use ($request) {
            $due = $this->dueDate($i)?->toDateString();
            return (! $request->due_from || ($due && $due >= $request->due_from)) && (! $request->due_to || ($due && $due <= $request->due_to));
        })->map(function ($i) {
            $due = $this->dueDate($i);
            $overdue = $due && $i->balance() > 0 && $i->status !== 'cancelled' && $due->lt(today());
            return [
                'invoice' => $i->invoice_no, 'invoice_url' => $this->link('invoices.show', 'invoices.show', $i),
                'type' => $i->isDepositInvoice() ? 'Deposit' : 'Sale',
                'customer' => $i->customer->name ?? '—', 'customer_url' => $i->customer ? $this->customerUrl($i->customer) : null,
                'vehicle' => $i->vehicle?->label() ?? '—',
                'date' => $i->issued_at?->toDateString(),
                'payable' => $i->total_payable, 'paid' => $i->amount_paid, 'outstanding' => $i->status === 'cancelled' ? 0 : $i->balance(),
                'first_due' => $i->due_first?->toDateString(), 'due' => $due?->toDateString(),
                'status' => $overdue ? $this->badge(Str::headline($i->status) . ' · Overdue', 'danger') : $this->statusBadge($i->status),
                '_class' => $i->status === 'cancelled' ? 'row-muted' : '',
            ];
        })->sortByDesc('date');

        return $this->render($request, 'invoice_summary', [
            ['key' => 'invoice', 'label' => 'Invoice No', 'link' => 'invoice_url'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'vehicle', 'label' => 'Vehicle'],
            ['key' => 'date', 'label' => 'Invoice Date', 'type' => 'date'],
            ['key' => 'payable', 'label' => 'Payable', 'type' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'type' => 'money', 'total' => true],
            ['key' => 'outstanding', 'label' => 'Outstanding', 'type' => 'money', 'total' => true],
            ['key' => 'first_due', 'label' => '50% Due', 'type' => 'date'],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ], $rows, $this->filters(
            $this->fDateRange('Invoice date'),
            $this->fDateRange('Due date', 'due_from', 'due_to'),
            $this->fAgent(),
            $this->fCustomer(),
            $this->fSelect('status', 'Status', ['draft' => 'Draft', 'issued' => 'Issued', 'partial' => 'Partial', 'paid' => 'Paid', 'cancelled' => 'Cancelled'], null, 'All (excl. cancelled)'),
            $this->fSelect('type', 'Type', ['sale' => 'Sale', 'deposit' => 'Deposit']),
        ), ['notes' => ['Payable is the settled amount; paid counts approved payments only. Cancelled invoices are listed separately — choose Status: Cancelled.']]);
    }

    // ───────────────────────── 10 Inactive Customers ─────────────────────────

    public function inactive(Request $request)
    {
        $days = max((int) $request->input('days', 90), 1);
        $today = today();

        $rows = $this->customers($request)->get()->map(function (Customer $c) use ($today) {
            $lastBid      = Bid::allAgents()->where('customer_id', $c->id)->max('auction_date');
            $lastPurchase = Vehicle::allAgents()->where('customer_id', $c->id)->whereIn('status', self::WON_STATUSES)->max('won_at');
            $lastPayment  = Payment::where('customer_id', $c->id)->approved()->max('paid_at');
            $dates = array_filter([$lastBid, $lastPurchase, $lastPayment]);
            $last = $dates ? max(array_map(fn ($d) => Carbon::parse($d), $dates)) : null;
            $basis = $last ?? $c->created_at;
            return [
                'customer' => $c->name, 'customer_url' => $this->customerUrl($c),
                'agent' => $c->agent->name ?? '—', 'country' => $c->country,
                'last_bid' => $lastBid ? Carbon::parse($lastBid)->toDateString() : null,
                'last_purchase' => $lastPurchase ? Carbon::parse($lastPurchase)->toDateString() : null,
                'last_payment' => $lastPayment ? Carbon::parse($lastPayment)->toDateString() : null,
                'last_activity' => $last?->toDateString() ?? 'Never (created ' . $c->created_at?->toDateString() . ')',
                'inactive_days' => (int) $basis->copy()->startOfDay()->diffInDays($today),
            ];
        })->filter(fn ($r) => $r['inactive_days'] >= $days)->sortByDesc('inactive_days');

        return $this->render($request, 'inactive_customers', [
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'country', 'label' => 'Country'],
            ['key' => 'last_bid', 'label' => 'Last Bid', 'type' => 'date'],
            ['key' => 'last_purchase', 'label' => 'Last Purchase', 'type' => 'date'],
            ['key' => 'last_payment', 'label' => 'Last Payment', 'type' => 'date'],
            ['key' => 'last_activity', 'label' => 'Relevant Event Date (last activity)'],
            ['key' => 'inactive_days', 'label' => 'Inactive Days', 'type' => 'int'],
        ], $rows, $this->filters(
            $this->fNumber('days', 'Inactive for (days) ≥', $days),
            $this->fAgent(),
            $this->fCountry(),
        ), ['notes' => ["Inactivity counts from the latest bid, won vehicle or approved payment; customers with no activity count from their creation date, so customers newer than {$days} days are excluded."]]);
    }
}
