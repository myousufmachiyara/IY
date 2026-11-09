<?php

namespace App\Http\Controllers\Reports;

use App\Models\{AccountMapping, ChartOfAccount, Customer, Document, Expense, Invoice, JournalLine, Payment, Vehicle, Vendor, VendorPayment};
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FinanceReportController extends BaseReportController
{
    public function __construct(private LedgerService $ledger) {}

    private function customerUrl($c): ?string
    {
        return $c ? $this->reportLink('customer_360', ['customer_id' => $c->id]) : null;
    }

    /** Approved payments on an invoice up to a date. */
    private function paidAsOf(Invoice $i, string $asOf): int
    {
        return (int) $i->payments->where('status', 'approved')
            ->filter(fn ($p) => $p->paid_at && $p->paid_at->toDateString() <= $asOf)->sum('amount');
    }

    // ───────────────────────── 14 Customer Receivables & Aging ─────────────────────────

    public function receivablesAging(Request $request)
    {
        $asOf = $this->asOf($request);
        $asOfDate = Carbon::parse($asOf)->startOfDay();
        $buckets = ['current' => 'Current', 'b30' => '1-30', 'b60' => '31-60', 'b90' => '61-90', 'b90p' => '90+'];

        $rows = Invoice::where('status', '!=', 'cancelled')->whereNotNull('issued_at')->whereDate('issued_at', '<=', $asOf)
            ->with('customer', 'agent', 'payments')
            ->when($this->agentId($request), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->get()
            ->map(function (Invoice $i) use ($asOf, $asOfDate) {
                $outstanding = max($i->total_payable - $this->paidAsOf($i, $asOf), 0);
                $due = $this->dueDate($i);
                $age = $due ? max(0, (int) $due->copy()->startOfDay()->diffInDays($asOfDate, false)) : 0;
                $bucket = match (true) { $age <= 0 => 'current', $age <= 30 => 'b30', $age <= 60 => 'b60', $age <= 90 => 'b90', default => 'b90p' };
                $row = [
                    'invoice' => $i->invoice_no, 'invoice_url' => $this->link('invoices.show', 'invoices.show', $i),
                    'customer' => $i->customer->name ?? '—', 'customer_url' => $this->customerUrl($i->customer),
                    'agent' => $i->agent->name ?? '—',
                    'due' => $due?->toDateString(), 'age' => $age, 'outstanding' => $outstanding, 'bucket' => $bucket,
                    'current' => 0, 'b30' => 0, 'b60' => 0, 'b90' => 0, 'b90p' => 0,
                ];
                $row[$bucket] = $outstanding;
                return $row;
            })
            ->filter(fn ($r) => $r['outstanding'] > 0)
            ->when($request->bucket, fn ($c, $b) => $c->where('bucket', $b))
            ->sortByDesc('age');

        return $this->render($request, 'receivables_aging', [
            ['key' => 'invoice', 'label' => 'Invoice', 'link' => 'invoice_url'],
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
            ['key' => 'age', 'label' => 'Days Overdue', 'type' => 'int'],
            ['key' => 'outstanding', 'label' => 'Outstanding', 'type' => 'money', 'total' => true],
            ['key' => 'current', 'label' => 'Current', 'type' => 'money', 'total' => true],
            ['key' => 'b30', 'label' => '1-30', 'type' => 'money', 'total' => true],
            ['key' => 'b60', 'label' => '31-60', 'type' => 'money', 'total' => true],
            ['key' => 'b90', 'label' => '61-90', 'type' => 'money', 'total' => true],
            ['key' => 'b90p', 'label' => '90+', 'type' => 'money', 'total' => true],
        ], $rows, $this->filters(
            $this->fDate('as_of', 'As of', $asOf),
            $this->fCustomer(),
            $this->fAgent(),
            $this->fSelect('bucket', 'Aging bucket', $buckets),
        ), [
            'cards' => collect($buckets)->map(fn ($l, $k) => ['label' => $l, 'value' => $rows->sum($k), 'variant' => $k === 'current' ? null : ($k === 'b90p' ? 'danger' : 'warning')])->values()->all(),
            'notes' => ['Outstanding as of the chosen date = settled payable − approved payments dated on/before it. Age = days past the final due date (50% due date or invoice date when no final due is set).'],
        ]);
    }

    // ───────────────────────── 15 Customer Payment Collections ─────────────────────────

    public function collections(Request $request)
    {
        $status = $request->input('status', 'approved');
        $basis  = $request->input('basis', 'approved_at') === 'paid_at' ? 'paid_at' : 'approved_at';
        $agentId = $this->agentId($request);

        $q = Payment::with('customer', 'invoice', 'account', 'approver')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->account_id, fn ($q, $v) => $q->where('account_id', $v))
            ->when($request->method, fn ($q, $v) => $q->where('method', $v))
            ->when($agentId, fn ($q, $v) => $q->whereHas('customer', fn ($c) => $c->where('agent_id', $v)))
            ->when($this->ownOnly(), fn ($q) => $q->whereHas('customer'));
        $this->range($q, $status === 'approved' ? $basis : 'paid_at', $request->from, $request->to);

        $all = $q->orderByDesc('paid_at')->get();
        $rows = $all->map(fn (Payment $p) => [
            'paid_at' => $p->paid_at?->toDateString(), 'paid_url' => $p->customer ? $this->link('payments.index', 'payments.customer_ledger', $p->customer) : null,
            'approved_at' => $p->approved_at?->toDateString(),
            'customer' => $p->customer->name ?? '—', 'customer_url' => $this->customerUrl($p->customer),
            'invoice' => $p->invoice?->invoice_no, 'invoice_url' => $p->invoice ? $this->link('invoices.show', 'invoices.show', $p->invoice) : null,
            'method' => $p->method === 'deposit' ? 'Deposit applied' : Str::headline((string) $p->method),
            'account' => $p->account ? "{$p->account->code} — {$p->account->name}" : '—',
            'amount' => $p->amount,
            'reference' => $p->reference,
            'status' => $this->statusBadge($p->status),
            'status_key' => $p->status,
            '_class' => $p->status === 'approved' ? '' : 'row-muted',
        ]);
        $approved = $rows->where('status_key', 'approved');

        return $this->render($request, 'payment_collections', [
            ['key' => 'paid_at', 'label' => 'Payment Date', 'type' => 'date', 'link' => 'paid_url'],
            ['key' => 'approved_at', 'label' => 'Approved Date', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'invoice', 'label' => 'Invoice', 'link' => 'invoice_url'],
            ['key' => 'method', 'label' => 'Method'],
            ['key' => 'account', 'label' => 'Account', 'sensitive' => true],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'total' => true],
            ['key' => 'reference', 'label' => 'Reference'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ], $rows, $this->filters(
            $this->fDateRange('Date'),
            $this->fSelect('basis', 'Date basis', ['approved_at' => 'Approval date', 'paid_at' => 'Payment date'], 'approved_at', ''),
            $this->fCustomer(),
            $this->fAgent(),
            $this->canSensitive() ? $this->fMoneyAccount() : null,
            $this->fSelect('method', 'Method', ['cash' => 'Cash', 'bank' => 'Bank', 'deposit' => 'Deposit applied']),
            $this->fSelect('status', 'Status', ['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected', 'all' => 'All statuses'], 'approved', ''),
        ), [
            'total_rows' => $approved,
            'cards' => [
                ['label' => 'Approved', 'value' => $approved->sum('amount'), 'variant' => 'success'],
                ['label' => 'Approved Payments', 'value' => $approved->count(), 'type' => 'int'],
                ['label' => 'Pending (in list)', 'value' => $rows->where('status_key', 'pending')->sum('amount'), 'variant' => 'warning'],
            ],
            'notes' => ['Totals include approved payments only; pending and rejected payments are listed but never counted. A reversed approval returns the payment to pending.',
                        'Pending/rejected payments are filtered by payment date.'],
        ]);
    }

    // ───────────────────────── 16 Payment Stage Report ─────────────────────────

    public function stages(Request $request)
    {
        $asOf = $this->asOf($request);
        $stageFilter = $request->stage;

        $invoices = $this->saleInvoices()->whereNotNull('vehicle_id')->whereDate('issued_at', '<=', $asOf)
            ->with('vehicle.shipment', 'customer.depositInvoice', 'payments')
            ->when($this->agentId($request), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->get();

        $released = Document::whereIn('vehicle_id', $invoices->pluck('vehicle_id'))->where('is_final_clearance', true)
            ->where('visible_to_customer', true)->pluck('vehicle_id')->flip();

        $rows = $invoices->map(function (Invoice $i) use ($asOf, $released) {
            $paid = $this->paidAsOf($i, $asOf);
            $pct = $this->pct($paid, $i->total_payable);
            $v = $i->vehicle;
            $depositOk = (bool) $i->customer?->profile_completed_at;
            $shipped = $v && ($v->shipment_id || in_array($v->status, ['dispatched', 'arrived', 'delivered']));
            $gate50 = $pct >= 50 ? $this->badge('Passed', 'success') : ($shipped ? $this->badge('Override', 'warning') : $this->badge('Pending', 'secondary'));
            $gate100 = $pct >= 100 ? $this->badge('Passed', 'success') : (isset($released[$i->vehicle_id]) ? $this->badge('Override', 'warning') : $this->badge('Pending', 'secondary'));
            $stage = $pct >= 100 ? 'full' : ($pct >= 50 ? 'half' : 'below');
            return [
                'vehicle' => $v?->label() . ($v?->chassis_no ? " · {$v->chassis_no}" : ''), 'vehicle_url' => $v ? $this->link('vehicle_requirement.show', 'vehicles.show', $v) : null,
                'customer' => $i->customer->name ?? '—', 'customer_url' => $this->customerUrl($i->customer),
                'invoice' => $i->invoice_no, 'invoice_url' => $this->link('invoices.show', 'invoices.show', $i),
                'total' => $i->total_payable, 'paid' => $paid, 'pct' => $pct,
                'deposit' => $depositOk ? $this->badge('Passed', 'success') : $this->badge('Missing', 'danger'),
                'gate50' => $gate50, 'gate100' => $gate100, 'stage' => $stage,
                'date' => $i->issued_at?->toDateString(),
            ];
        })->when($stageFilter, fn ($c, $s) => $c->where('stage', $s))->sortBy('pct');

        return $this->render($request, 'payment_stages', [
            ['key' => 'vehicle', 'label' => 'Vehicle', 'link' => 'vehicle_url'],
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'invoice', 'label' => 'Invoice', 'link' => 'invoice_url'],
            ['key' => 'date', 'label' => 'Relevant Event Date (invoice)', 'type' => 'date'],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'type' => 'money', 'total' => true],
            ['key' => 'pct', 'label' => 'Paid %', 'type' => 'pct'],
            ['key' => 'deposit', 'label' => 'Deposit Gate', 'type' => 'badge'],
            ['key' => 'gate50', 'label' => '50% Ship Gate', 'type' => 'badge'],
            ['key' => 'gate100', 'label' => '100% Release Gate', 'type' => 'badge'],
        ], $rows, $this->filters(
            $this->fDate('as_of', 'As of', $asOf),
            $this->fAgent(),
            $this->fCustomer(),
            $this->fSelect('stage', 'Stage', ['below' => 'Below 50%', 'half' => '50% – 99%', 'full' => 'Fully paid']),
        ), [
            'totals' => ['pct' => $this->pct($rows->sum('paid'), $rows->sum('total'))],
            'cards' => [
                ['label' => 'Below 50%', 'value' => $rows->where('stage', 'below')->count(), 'type' => 'int', 'variant' => 'danger'],
                ['label' => '50% – 99%', 'value' => $rows->where('stage', 'half')->count(), 'type' => 'int', 'variant' => 'warning'],
                ['label' => 'Fully Paid', 'value' => $rows->where('stage', 'full')->count(), 'type' => 'int', 'variant' => 'success'],
            ],
            'notes' => ['Paid = approved payments plus applied deposits dated on/before the as-of date, compared against the settled payable.',
                        '"Override" = the vehicle was shipped or its final documents released before the gate was reached (Super Admin bypass).'],
        ]);
    }

    // ───────────────────────── 17 Security Deposit Report ─────────────────────────

    public function deposits(Request $request)
    {
        $asOf = $this->asOf($request);
        $statuses = ['complete' => 'Complete (held)', 'pending_approval' => 'Pending approval', 'awaiting_payment' => 'Awaiting payment', 'incomplete' => 'Incomplete / used'];

        $rows = Customer::whereNotNull('deposit_invoice_id')->with('depositInvoice.payments', 'agent')
            ->when($this->agentId($request), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('id', $v))
            ->orderBy('name')->get()
            ->map(function (Customer $c) use ($asOf) {
                $inv = $c->depositInvoice;
                $invoiced = $inv && $inv->issued_at && $inv->issued_at->toDateString() <= $asOf ? $inv->total_payable : 0;
                $paid = $inv ? $this->paidAsOf($inv, $asOf) : 0;
                $applied = (int) Payment::where('customer_id', $c->id)->where('method', 'deposit')->approved()
                    ->whereDate('paid_at', '<=', $asOf)->sum('amount');
                $status = $c->depositWorkflowStatus();
                return [
                    'customer' => $c->name, 'customer_url' => $this->customerUrl($c), 'agent' => $c->agent->name ?? '—',
                    'invoice' => $inv?->invoice_no, 'invoice_url' => $inv ? $this->link('invoices.show', 'invoices.show', $inv) : null,
                    'invoiced' => $invoiced, 'paid' => $paid, 'held' => max($paid - $applied, 0), 'applied' => $applied,
                    'balance' => max($invoiced - $paid, 0),
                    'status' => $this->statusBadge($status), 'status_key' => $status,
                    'date' => $inv?->issued_at?->toDateString(),
                ];
            })
            ->when($request->status, fn ($c, $s) => $c->where('status_key', $s));

        return $this->render($request, 'security_deposits', [
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'invoice', 'label' => 'Deposit Invoice', 'link' => 'invoice_url'],
            ['key' => 'date', 'label' => 'Relevant Event Date (issued)', 'type' => 'date'],
            ['key' => 'invoiced', 'label' => 'Invoiced', 'type' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'type' => 'money', 'total' => true],
            ['key' => 'applied', 'label' => 'Applied', 'type' => 'money', 'total' => true],
            ['key' => 'held', 'label' => 'Held', 'type' => 'money', 'total' => true],
            ['key' => 'balance', 'label' => 'Balance (unpaid)', 'type' => 'money', 'total' => true],
            ['key' => 'status', 'label' => 'Approval Status', 'type' => 'badge'],
        ], $rows, $this->filters(
            $this->fDate('as_of', 'As of', $asOf),
            $this->fAgent(),
            $this->fCustomer(),
            $this->fSelect('status', 'Status', $statuses),
        ), ['notes' => ['Invoiced = deposit issued; Collected = approved deposit payments; Applied = deposit used against sale invoices; Held = Collected − Applied (the liability still owed to the customer).']]);
    }

    // ───────────────────────── 18 Vendor Payables ─────────────────────────

    public function vendorPayables(Request $request)
    {
        $asOf = $this->asOf($request);
        $vehicles = Vehicle::allAgents()->whereNotNull('vendor_id')->whereNotNull('buying_price')
            ->whereIn('status', self::WON_STATUSES)
            ->with('vendor', 'costing', 'vendorPayments', 'agent')
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($this->agentId($request), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->chassis, fn ($q, $v) => $q->where('chassis_no', 'like', "%$v%"))
            ->where(fn ($q) => $q->whereNull('won_at')->orWhereDate('won_at', '<=', $asOf))
            ->get();

        $rows = $vehicles->map(function (Vehicle $v) use ($asOf) {
            $payments = $v->vendorPayments->filter(fn ($p) => $p->paid_at && $p->paid_at->toDateString() <= $asOf);
            $payable = $v->costing?->total_costing ?: (int) $v->buying_price;
            $paid = (int) $payments->sum('amount');
            return [
                'vendor' => $v->vendor->name ?? '—',
                'vehicle' => $v->label() . ($v->chassis_no ? " · {$v->chassis_no}" : ''), 'vehicle_url' => $this->link('costings.show', 'costings.show', $v),
                'agent' => $v->agent->name ?? '—',
                'payable' => $payable, 'paid' => $paid, 'outstanding' => $payable - $paid,
                'last_payment' => optional($payments->max('paid_at'), fn ($d) => $d->toDateString()),
                'won_date' => $v->won_at?->toDateString(),
            ];
        });

        // Payments not tied to a vehicle (on-account) still reduce what is owed to the vendor.
        if (! $request->chassis && ! $this->agentId($request)) {
            VendorPayment::whereNull('vehicle_id')->whereDate('paid_at', '<=', $asOf)->with('vendor')
                ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
                ->get()->groupBy('vendor_id')
                ->each(function ($ps) use (&$rows) {
                    $rows->push(['vendor' => $ps->first()->vendor->name ?? '—', 'vehicle' => 'On-account payments (no vehicle)', 'vehicle_url' => null, 'agent' => '—',
                        'payable' => 0, 'paid' => (int) $ps->sum('amount'), 'outstanding' => -(int) $ps->sum('amount'),
                        'last_payment' => $ps->max('paid_at')?->toDateString(), 'won_date' => null, '_class' => 'row-muted']);
                });
        }
        $rows = $rows->sortBy([['vendor', 'asc'], ['outstanding', 'desc']]);

        $ledgerTotals = $this->ledger->totals($asOf);
        $ledger = (int) ChartOfAccount::whereNotNull('vendor_id')->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))->get()
            ->sum(fn ($a) => ($ledgerTotals[$a->id]['credit'] ?? 0) - ($ledgerTotals[$a->id]['debit'] ?? 0));

        $hideOpen = $request->input('open_only', '1') === '1';
        if ($hideOpen) {
            $rows = $rows->filter(fn ($r) => $r['outstanding'] != 0);
        }

        return $this->render($request, 'vendor_payables', [
            ['key' => 'vendor', 'label' => 'Vendor', 'class' => 'fw-semibold'],
            ['key' => 'vehicle', 'label' => 'Vehicle', 'link' => 'vehicle_url'],
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'won_date', 'label' => 'Relevant Event Date (won)', 'type' => 'date'],
            ['key' => 'payable', 'label' => 'Costed Payable', 'type' => 'money', 'total' => true],
            ['key' => 'paid', 'label' => 'Paid', 'type' => 'money', 'total' => true],
            ['key' => 'outstanding', 'label' => 'Outstanding', 'type' => 'money', 'total' => true],
            ['key' => 'last_payment', 'label' => 'Last Payment', 'type' => 'date'],
        ], $rows, $this->filters(
            $this->fDate('as_of', 'As of', $asOf),
            $this->fVendor(),
            $this->fText('chassis', 'Vehicle / chassis'),
            $this->fAgent(),
            $this->fSelect('open_only', 'Show', ['1' => 'Open balances only', '0' => 'All vehicles'], '1', ''),
        ), [
            'cards' => [
                ['label' => 'Outstanding (this report)', 'value' => $rows->sum('outstanding'), 'variant' => 'warning'],
                ['label' => 'Vendor ledger balance', 'value' => $ledger, 'hint' => 'Accounting → Payables, as of ' . $asOf],
                ['label' => 'Difference', 'value' => $rows->sum('outstanding') - $ledger, 'variant' => ($rows->sum('outstanding') - $ledger) == 0 ? 'success' : 'danger',
                 'hint' => $hideOpen ? 'Compare with "All vehicles"' : 'Should be zero when books reconcile'],
            ],
            'notes' => ['Costed payable = the vehicle\'s total costing (falls back to buying price when not costed) — the same figure posted to the vendor ledger. A non-zero difference points at a vehicle whose costing has not been re-posted.'],
        ]);
    }

    // ───────────────────────── 19 Vendor Payment History ─────────────────────────

    public function vendorPaymentHistory(Request $request)
    {
        $q = VendorPayment::with('vendor', 'vehicle', 'account', 'recorder', 'journalEntries')
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($request->account_id, fn ($q, $v) => $q->where('account_id', $v))
            ->when($request->chassis, fn ($q, $v) => $q->whereHas('vehicle', fn ($w) => $w->allAgents()->where('chassis_no', 'like', "%$v%")));
        $this->range($q, 'paid_at', $request->from, $request->to);

        $rows = $q->orderByDesc('paid_at')->get()->map(function (VendorPayment $p) {
            $reversals = $p->journalEntries->where('voucher_type', 'reversal')->count();
            return [
                'date' => $p->paid_at?->toDateString(), 'url' => $this->link('vendor_payments.index', 'vendor-payments.index', ['vendor_id' => $p->vendor_id]),
                'vendor' => $p->vendor->name ?? '—',
                'chassis' => $p->vehicle ? ($p->vehicle->chassis_no ?: $p->vehicle->label()) : 'On account',
                'amount' => $p->amount,
                'account' => $p->account ? "{$p->account->code} — {$p->account->name}" : '—',
                'reference' => $p->reference,
                'entered_by' => $p->recorder->name ?? '—',
                'reversal' => $reversals ? $this->badge("Edited · {$reversals} reversal(s)", 'warning') : $this->badge('Original', 'success'),
            ];
        });

        return $this->render($request, 'vendor_payment_history', [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date', 'link' => 'url'],
            ['key' => 'vendor', 'label' => 'Vendor'],
            ['key' => 'chassis', 'label' => 'Chassis'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'total' => true],
            ['key' => 'account', 'label' => 'Account', 'sensitive' => true],
            ['key' => 'reference', 'label' => 'Reference'],
            ['key' => 'entered_by', 'label' => 'Entered By'],
            ['key' => 'reversal', 'label' => 'Reversal Status', 'type' => 'badge'],
        ], $rows, $this->filters(
            $this->fDateRange('Payment date'),
            $this->fVendor(),
            $this->fText('chassis', 'Vehicle / chassis'),
            $this->canSensitive() ? $this->fMoneyAccount() : null,
        ), ['notes' => ['Amounts are the current posted amounts (net of reversals). An edited payment is reversed in the ledger and re-posted, shown as "Edited". Deleted payments are reversed in the ledger and no longer appear in this register.']]);
    }

    // ───────────────────────── 20 Expense Analysis ─────────────────────────

    public function expenses(Request $request)
    {
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to   = $request->input('to', today()->toDateString());
        $g    = $request->input('granularity', 'month') === 'quarter' ? 'quarter' : 'month';
        $categories = collect(Expense::CATEGORIES)->map(fn ($c) => $c['label'])->all();

        $q = Expense::with('account', 'recorder')
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->when($request->account_id, fn ($q, $v) => $q->where('paid_from_account_id', $v));
        $expenses = $this->range($q, 'expense_date', $from, $to)->orderBy('expense_date')->get();

        $periodTotals = $expenses->groupBy(fn ($e) => $this->periodKey($e->expense_date, $g))->map->sum('amount');

        $rows = $expenses->map(fn (Expense $e) => [
            'date' => $e->expense_date?->toDateString(),
            'period' => $this->periodLabel($e->expense_date, $g),
            'category' => $categories[$e->category] ?? Str::headline((string) $e->category),
            'description' => $e->description,
            'account' => $e->account ? "{$e->account->code} — {$e->account->name}" : '—',
            'amount' => $e->amount,
            'period_total' => $periodTotals[$this->periodKey($e->expense_date, $g)] ?? 0,
            'recorded_by' => $e->recorder->name ?? '—',
        ]);

        $byCategory = $expenses->groupBy('category')->map->sum('amount')->sortDesc();
        $cards = $byCategory->take(5)->map(fn ($sum, $cat) => ['label' => $categories[$cat] ?? Str::headline($cat), 'value' => $sum])->values()->all();
        array_unshift($cards, ['label' => 'Total Operating Expenses', 'value' => $expenses->sum('amount'), 'variant' => 'danger']);

        return $this->render($request, 'expense_analysis', [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date', 'link' => 'url'],
            ['key' => 'period', 'label' => 'Period'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'account', 'label' => 'Paying Account', 'sensitive' => true],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'total' => true],
            ['key' => 'period_total', 'label' => 'Period Total', 'type' => 'money'],
            ['key' => 'recorded_by', 'label' => 'Recorded By'],
        ], $rows->map(fn ($r) => $r + ['url' => $this->link('expenses.index', 'expenses.index')]), $this->filters(
            $this->fDateRange('Expense date', 'from', 'to', $from, $to),
            $this->fSelect('category', 'Category', $categories),
            $this->canSensitive() ? $this->fMoneyAccount('account_id', 'Paying account') : null,
            $this->fGranularity(),
        ), [
            'cards' => $cards,
            'chart' => ['type' => 'bar', 'labels' => $expenses->groupBy(fn ($e) => $this->periodKey($e->expense_date, $g))->map(fn ($g2) => $this->periodLabel($g2->first()->expense_date, $g))->values(), 'series' => [['label' => 'Operating expenses', 'data' => $periodTotals->values()]]],
            'notes' => ['Operating expenses only (Expenses module). Vehicle costs from the Costing screen are excluded so nothing is double counted — see Revenue vs Expense for both side by side.'],
        ]);
    }

    // ───────────────────────── 21 Revenue vs Expense ─────────────────────────

    public function revenueVsExpense(Request $request)
    {
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to   = $request->input('to', today()->toDateString());
        $g    = $request->input('granularity', 'month') === 'quarter' ? 'quarter' : 'month';
        $costCat = $request->cost_category;

        $vehicleKeys = collect(AccountMapping::KEYS)->filter(fn ($m) => $m['group'] === 'Vehicle costing')->keys();
        $vehicleAccountIds = AccountMapping::whereIn('key', $vehicleKeys)->pluck('account_id')->filter()->unique()->all();

        $accounts = ChartOfAccount::with('subhead.head')->get()->keyBy('id');
        $periods = $this->periods($from, $to, $g);
        $data = array_map(fn () => ['revenue' => 0, 'vehicle' => 0, 'opex' => 0], $periods);

        $lines = JournalLine::query()->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereDate('journal_entries.date', '>=', $from)->whereDate('journal_entries.date', '<=', $to)
            ->get(['journal_lines.account_id', 'journal_lines.debit', 'journal_lines.credit', 'journal_entries.date']);

        foreach ($lines as $l) {
            $a = $accounts[$l->account_id] ?? null;
            $nature = $a?->subhead?->head?->nature ?? $a?->type;
            if (! isset($data[$k = $this->periodKey($l->date, $g)])) continue;
            if ($nature === 'income') {
                $data[$k]['revenue'] += $l->credit - $l->debit;
            } elseif ($nature === 'expense') {
                $bucket = in_array($l->account_id, $vehicleAccountIds) ? 'vehicle' : 'opex';
                $data[$k][$bucket] += $l->debit - $l->credit;
            }
        }

        $rows = collect($periods)->map(function ($p, $k) use ($data, $costCat) {
            $d = $data[$k];
            $vehicle = $costCat === 'opex' ? 0 : $d['vehicle'];
            $opex = $costCat === 'vehicle' ? 0 : $d['opex'];
            $result = $d['revenue'] - $vehicle - $opex;
            return [
                'period' => $p['label'], 'url' => $this->link('reports.financial_reports', 'accounting.profit_loss', ['from' => $p['from'], 'to' => $p['to']]),
                'revenue' => $d['revenue'], 'vehicle' => $vehicle, 'opex' => $opex,
                'result' => $result, 'margin' => $d['revenue'] ? round($result / $d['revenue'] * 100, 1) : null,
                'range' => $p['from'] . ' → ' . $p['to'],
            ];
        })->values();

        $rev = $rows->sum('revenue'); $res = $rows->sum('result');

        return $this->render($request, 'revenue_vs_expense', [
            ['key' => 'period', 'label' => 'Period', 'link' => 'url'],
            ['key' => 'revenue', 'label' => 'Recognized Revenue', 'type' => 'money', 'total' => true],
            ['key' => 'vehicle', 'label' => 'Vehicle Costs', 'type' => 'money', 'total' => true],
            ['key' => 'opex', 'label' => 'Operating Expenses', 'type' => 'money', 'total' => true],
            ['key' => 'result', 'label' => 'Result', 'type' => 'money', 'total' => true],
            ['key' => 'margin', 'label' => 'Margin', 'type' => 'pct'],
            ['key' => 'range', 'label' => 'Relevant Event Date', 'class' => 'small text-muted'],
        ], $rows, $this->filters(
            $this->fDateRange('Date range', 'from', 'to', $from, $to),
            $this->fGranularity(),
            $this->fSelect('cost_category', 'Cost category', ['vehicle' => 'Vehicle costs only', 'opex' => 'Operating expenses only'], null, 'All costs'),
        ), [
            'totals' => ['margin' => $rev ? round($res / $rev * 100, 1) : null],
            'chart' => ['type' => 'bar', 'labels' => $rows->pluck('period'), 'series' => [
                ['label' => 'Revenue', 'data' => $rows->pluck('revenue')],
                ['label' => 'Vehicle costs', 'data' => $rows->pluck('vehicle')],
                ['label' => 'Operating expenses', 'data' => $rows->pluck('opex')],
            ]],
            'notes' => ['Straight from the ledger, so it reconciles with Profit & Loss. Revenue follows the "Sales income" mapping (by default recognised as customer money is received).',
                        'Vehicle costs = accounts mapped to the Vehicle costing roles in Accounting → Account Mapping; every other expense account is operating.'],
        ]);
    }
}
