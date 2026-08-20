<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Vehicle, Invoice, Vendor, Bid, User, Payment, Expense, VendorPayment, ChartOfAccount, JournalLine, Shipment};
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isPrivileged = $user->can('data.view_all');

        $from = $request->from ? Carbon::parse($request->from)->startOfDay() : now()->startOfMonth();
        $to   = $request->to   ? Carbon::parse($request->to)->endOfDay()   : now()->endOfDay();

        // ===================== OVERVIEW (activity within range, except backlog items) =====================
        $stats = [
            'customers'      => Customer::whereBetween('created_at', [$from, $to])->count(),
            'vehicles_bid'   => Bid::whereBetween('created_at', [$from, $to])->count(),
            'vehicles_won'   => Vehicle::whereNotNull('won_at')->whereBetween('won_at', [$from, $to])->count(),
            'sales'          => Invoice::whereIn('status', ['issued', 'partial', 'paid'])->whereBetween('issued_at', [$from, $to])->sum('total_payable'),
            'profit'         => Vehicle::whereNotNull('won_at')->whereBetween('won_at', [$from, $to])->with('costing')->get()->sum(fn ($v) => $v->costing?->profit ?? 0),
            'pending_bids'   => Bid::where('result', 'pending')->count(), // current backlog — not date-filtered
        ];

        $stats['receivables'] = $this->accountBalanceAsOf(LedgerService::AR, $to);

        if ($isPrivileged) {
            $stats['pending_approvals'] = Payment::where('status', 'pending')->count() + Customer::where('security_deposit_status', 'pending')->count();
            $stats['vendor_payable']    = $this->accountBalanceAsOf(LedgerService::AP_VENDOR, $to);
        }

        // ===================== FINANCIAL POSITION — balances AS OF the "To" date =====================
        $cashBank = $this->accountBalanceAsOf(LedgerService::CASH, $to) + $this->accountBalanceAsOf(LedgerService::BANK, $to);
        $receivables = $stats['receivables'];
        $payablesTotal = $stats['vendor_payable'] ?? $this->accountBalanceAsOf(LedgerService::AP_VENDOR, $to);

        $opExpensesInRange = (int) Expense::whereBetween('expense_date', [$from, $to])->sum('amount');

        $depositsHeld = (int) Customer::where('security_deposit_status', 'approved')
            ->where('security_deposit_approved_at', '<=', $to)->sum('security_deposit');

        // Net Cash Exposure = Cash + Expected Customer Collections − Vendor/Other Immediate Payables
        $netCashExposure = $cashBank + $receivables - $payablesTotal;

        $financial = [
            'cash_bank'         => $cashBank,
            'receivables'       => $receivables,
            'payables'          => $payablesTotal,
            'op_expenses_month' => $opExpensesInRange,
            'deposits_held'     => $depositsHeld,
            'net_cash_exposure' => $netCashExposure,
        ];

        // ===================== ACTION CENTRE (current backlog — not date-filtered) =====================
        $overdueInvoices = Invoice::whereIn('status', ['issued', 'partial'])->get()
            ->filter(fn ($i) => ($i->due_first && !$i->isHalfPaid() && now()->gt($i->due_first)) ||
                                 ($i->due_final && !$i->isFullyPaid() && now()->gt($i->due_final)))->count();

        $vehiclesAtYardOver30 = Vehicle::whereIn('status', ['won', 'invoiced'])
            ->whereNotNull('won_at')->where('won_at', '<', now()->subDays(30))->count();

        $shipmentsPastEta = Shipment::where('status', 'dispatched')
            ->whereNotNull('expected_arrival')->where('expected_arrival', '<', now()->toDateString())->count();

        $vendorPaymentsDue = Vehicle::whereNotNull('won_at')->with('costing')->get()
            ->filter(function ($v) {
                $owed = $v->costing?->total_costing ?? $v->buying_price ?? 0;
                $paid = $v->vendorPayments()->sum('amount');
                return ($owed - $paid) > 0;
            })->count();

        $vehiclesMissingDocs = Vehicle::whereNotNull('won_at')->whereDoesntHave('documents')->count();
        $wonNotInvoiced = Vehicle::where('status', 'won')->whereDoesntHave('invoice')->count();

        // Assumption: "target margin" = 10% of total costing. No stored setting for this exists yet.
        $belowTargetMargin = Vehicle::whereNotNull('won_at')->with('costing')->get()
            ->filter(fn ($v) => $v->costing && $v->costing->total_costing > 0 && ($v->costing->profit / $v->costing->total_costing) < 0.10)->count();

        $actionCentre = [
            ['label' => 'Customer Payments Overdue',   'count' => $overdueInvoices,       'severity' => 'danger',  'route' => route('invoices.index')],
            ['label' => 'Vehicles at Yard > 30 Days',   'count' => $vehiclesAtYardOver30,  'severity' => 'danger',  'route' => route('results.won')],
            ['label' => 'Shipments Past ETA',           'count' => $shipmentsPastEta,      'severity' => 'danger',  'route' => route('shipments.index')],
            ['label' => 'Vendor Payments Due',          'count' => $vendorPaymentsDue,     'severity' => 'warning', 'route' => route('vendor-payments.index')],
            ['label' => 'Vehicles Missing Documents',   'count' => $vehiclesMissingDocs,   'severity' => 'warning', 'route' => route('results.won')],
            ['label' => 'Payment Approvals Pending',    'count' => $stats['pending_approvals'] ?? 0, 'severity' => 'warning', 'route' => route('approvals.index')],
            ['label' => 'Won Vehicles Not Invoiced',    'count' => $wonNotInvoiced,        'severity' => 'warning', 'route' => route('results.won')],
            ['label' => 'Vehicles Below Target Margin', 'count' => $belowTargetMargin,     'severity' => 'muted',   'route' => route('results.won')],
            ['label' => 'Bid Results Still Pending',    'count' => $stats['pending_bids'], 'severity' => 'muted',   'route' => route('results.index')],
        ];

        // ===================== VEHICLE PIPELINE (current state, not date-filtered) =====================
        $pipeline = [
            'labels' => ['Requirement', 'Won', 'Invoiced', 'In Transit', 'Arrived', 'Delivered'],
            'data'   => [
                Vehicle::where('status', 'requirement')->count(),
                Vehicle::where('status', 'won')->count(),
                Vehicle::where('status', 'invoiced')->count(),
                Vehicle::where('status', 'dispatched')->count(),
                Vehicle::where('status', 'arrived')->count(),
                Vehicle::where('status', 'delivered')->count(),
            ],
        ];

        // ===================== REVENUE & PROFIT (within range) =====================
        $revenueProfit = ['revenue' => $stats['sales'], 'profit' => $stats['profit']];

        // ===================== SHIPMENT / LOGISTICS PANEL (current state, not date-filtered) =====================
        $shipLogistics = [
            'labels' => ['Delayed', 'Arriving Next 7 Days', 'In Transit', 'Shipment Prepared', 'Ready for Shipment'],
            'data'   => [
                Shipment::where('status', 'dispatched')->whereNotNull('expected_arrival')->where('expected_arrival', '<', now()->toDateString())->count(),
                Shipment::where('status', 'dispatched')->whereBetween('expected_arrival', [now()->toDateString(), now()->addDays(7)->toDateString()])->count(),
                Shipment::where('status', 'dispatched')->count(),
                Shipment::where('status', 'preparing')->count(),
                Vehicle::where('status', 'invoiced')->whereNull('shipment_id')->count(),
            ],
        ];

        // ===================== AUCTION PERFORMANCE (within range) =====================
        $auctionPerf = [
            'labels' => ['Bids', 'Won', 'Lost', 'Pending'],
            'data'   => [
                Bid::whereBetween('created_at', [$from, $to])->count(),
                Bid::where('result', 'won')->whereBetween('updated_at', [$from, $to])->count(),
                Bid::where('result', 'lost')->whereBetween('updated_at', [$from, $to])->count(),
                Bid::where('result', 'pending')->count(), // current backlog
            ],
        ];

        // ===================== TOP AUCTION HOUSES (within range) =====================
        $topAuctionHouses = Bid::whereNotNull('auction_house')->whereBetween('created_at', [$from, $to])
            ->selectRaw('auction_house, COUNT(*) as bids, SUM(result = "won") as won')
            ->groupBy('auction_house')->orderByDesc('bids')->take(8)->get()
            ->map(fn ($r) => ['house' => $r->auction_house, 'bids' => $r->bids, 'won' => (int) $r->won, 'rate' => $r->bids > 0 ? round($r->won / $r->bids * 100) : 0]);

        // ===================== TOP AGENTS (bids/won/sales/profit within range) =====================
        $topAgents = User::permission('scope.by_agent')->get()->map(function ($a) use ($from, $to) {
            $bids = Bid::allAgents()->where('agent_id', $a->id)->whereBetween('created_at', [$from, $to]);
            $won  = (clone $bids)->won()->count();
            $vehicles = Vehicle::allAgents()->where('agent_id', $a->id)->whereNotNull('won_at')->whereBetween('won_at', [$from, $to])->with('costing')->get();
            return [
                'agent'  => $a->name,
                'bids'   => (clone $bids)->count(),
                'won'    => $won,
                'rate'   => (clone $bids)->count() > 0 ? round($won / (clone $bids)->count() * 100) : 0,
                'sales'  => $vehicles->sum('selling_price'),
                'profit' => $vehicles->sum(fn ($v) => $v->costing?->profit ?? 0),
            ];
        })->sortByDesc('won')->take(8)->values();

        // ===================== TOP CUSTOMERS (Sales/Profit within range; Vehicles/Outstanding = current state) =====================
        $topCustomers = Customer::complete()->get()->map(function ($c) use ($from, $to) {
            $wonInRange = $c->vehicles()->whereNotNull('won_at')->whereBetween('won_at', [$from, $to])->with('costing')->get();
            return [
                'customer'    => $c->name,
                'vehicles'    => $c->vehicles()->count(),
                'sales'       => Invoice::where('customer_id', $c->id)->whereBetween('issued_at', [$from, $to])->sum('total_payable'),
                'profit'      => $wonInRange->sum(fn ($v) => $v->costing?->profit ?? 0),
                'outstanding' => $c->balance(),
            ];
        })->sortByDesc('sales')->take(8)->values();

        // ===================== VENDOR LEDGER (Paid = within range; To Pay/Outstanding = current running total) =====================
        $vendorToPay = Vehicle::whereNotNull('won_at')->with('costing')->get()->sum(fn ($v) => $v->costing?->total_costing ?? $v->buying_price ?? 0);
        $vendorPaidInRange = VendorPayment::whereBetween('paid_at', [$from, $to])->sum('amount');
        $vendorLedger = ['to_pay' => (int) $vendorToPay, 'paid' => (int) $vendorPaidInRange, 'outstanding' => (int) ($vendorToPay - VendorPayment::sum('amount'))];

        return view('home', compact(
            'stats', 'isPrivileged', 'financial', 'actionCentre', 'pipeline', 'revenueProfit',
            'shipLogistics', 'auctionPerf', 'topAuctionHouses', 'topAgents', 'topCustomers', 'vendorLedger',
            'from', 'to'
        ));
    }

    /** True point-in-time ledger balance for one account, as of a given date (inclusive). */
    private function accountBalanceAsOf(string $code, $asOf): int
    {
        $account = ChartOfAccount::where('code', $code)->first();
        if (! $account) return 0;

        $lines = JournalLine::where('account_id', $account->id)
            ->whereHas('entry', fn ($q) => $q->whereDate('date', '<=', $asOf));

        $debit  = (clone $lines)->sum('debit');
        $credit = (clone $lines)->sum('credit');

        $debitNormal = in_array($account->type, ['asset', 'expense']);
        return (int) ($debitNormal ? ($debit - $credit) : ($credit - $debit));
    }
}