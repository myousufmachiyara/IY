<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Vehicle, Invoice, Vendor, Bid, User, Payment, Expense, Document, VendorPayment, ChartOfAccount};
use App\Services\LedgerService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isPrivileged = $user->can('data.view_all');

        // ===================== OVERVIEW =====================
        $stats = [
            'customers'      => Customer::count(),
            'vehicles_bid'   => Bid::count(),
            'vehicles_won'   => Vehicle::whereNotNull('won_at')->count(),
            'sales'          => Invoice::whereIn('status', ['issued', 'partial', 'paid'])->sum('total_payable'),
            'receivables'    => Invoice::whereIn('status', ['issued', 'partial'])->get()->sum(fn ($i) => $i->balance()),
            'profit'         => Vehicle::whereNotNull('won_at')->with('costing')->get()->sum(fn ($v) => $v->costing?->profit ?? 0),
            'pending_bids'   => Bid::where('result', 'pending')->count(),
        ];

        if ($isPrivileged) {
            $stats['pending_approvals'] = Payment::where('status', 'pending')->count() + Customer::where('security_deposit_status', 'pending')->count();
            $stats['vendor_payable']    = Vendor::get()->sum(fn ($v) => $v->balance());
        }

        // ===================== FINANCIAL POSITION =====================
        $cashBank = (int) (ChartOfAccount::where('code', LedgerService::CASH)->first()?->balance() ?? 0)
                  + (int) (ChartOfAccount::where('code', LedgerService::BANK)->first()?->balance() ?? 0);

        $customerDeposits = (int) Customer::where('security_deposit_status', 'approved')->sum('security_deposit');

        $opExpensesThisMonth = (int) Expense::whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount');

        $vendorPayableTotal = (int) ($stats['vendor_payable'] ?? Vendor::get()->sum(fn ($v) => $v->balance()));

        // Net Cash Exposure = Cash + Expected Customer Collections − Vendor/Other Immediate Payables
        $netCashExposure = $cashBank + $stats['receivables'] - $vendorPayableTotal;

        $financial = [
            'cash_bank'         => $cashBank,
            'receivables'       => $stats['receivables'],
            'payables'          => $vendorPayableTotal,
            'op_expenses_month' => $opExpensesThisMonth,
            'deposits_held'     => $customerDeposits,
            'net_cash_exposure' => $netCashExposure,
        ];

        // ===================== ACTION CENTRE =====================
        $overdueInvoices = Invoice::whereIn('status', ['issued', 'partial'])->get()
            ->filter(fn ($i) => ($i->due_first && !$i->isHalfPaid() && now()->gt($i->due_first)) ||
                                 ($i->due_final && !$i->isFullyPaid() && now()->gt($i->due_final)))->count();

        $vehiclesAtYardOver30 = Vehicle::whereIn('status', ['won', 'invoiced'])
            ->whereNotNull('won_at')->where('won_at', '<', now()->subDays(30))->count();

        $shipmentsPastEta = \App\Models\Shipment::where('status', 'dispatched')
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
            ->filter(function ($v) {
                if (!$v->costing || $v->costing->total_costing <= 0) return false;
                return ($v->costing->profit / $v->costing->total_costing) < 0.10;
            })->count();

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

        // ===================== VEHICLE PIPELINE (simplified to real statuses) =====================
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

        // ===================== REVENUE & PROFIT =====================
        $revenueProfit = [
            'revenue' => $stats['sales'],
            'profit'  => $stats['profit'],
        ];

        // ===================== SHIPMENT / LOGISTICS PANEL =====================
        $shipLogistics = [
            'labels' => ['Delayed', 'Arriving Next 7 Days', 'In Transit', 'Shipment Prepared', 'Ready for Shipment'],
            'data'   => [
                \App\Models\Shipment::where('status', 'dispatched')->whereNotNull('expected_arrival')->where('expected_arrival', '<', now()->toDateString())->count(),
                \App\Models\Shipment::where('status', 'dispatched')->whereBetween('expected_arrival', [now()->toDateString(), now()->addDays(7)->toDateString()])->count(),
                \App\Models\Shipment::where('status', 'dispatched')->count(),
                \App\Models\Shipment::where('status', 'preparing')->count(),
                Vehicle::where('status', 'invoiced')->whereNull('shipment_id')->count(),
            ],
        ];

        // ===================== AUCTION PERFORMANCE =====================
        $auctionPerf = [
            'labels' => ['Bids', 'Won', 'Lost', 'Pending'],
            'data'   => [Bid::count(), Bid::where('result', 'won')->count(), Bid::where('result', 'lost')->count(), Bid::where('result', 'pending')->count()],
        ];

        // ===================== TOP AUCTION HOUSES =====================
        $topAuctionHouses = Bid::whereNotNull('auction_house')
            ->selectRaw('auction_house, COUNT(*) as bids, SUM(result = "won") as won')
            ->groupBy('auction_house')->orderByDesc('bids')->take(8)->get()
            ->map(fn ($r) => ['house' => $r->auction_house, 'bids' => $r->bids, 'won' => (int) $r->won, 'rate' => $r->bids > 0 ? round($r->won / $r->bids * 100) : 0]);

        // ===================== TOP AGENTS =====================
        $topAgents = User::permission('scope.by_agent')->get()->map(function ($a) {
            $bids = Bid::allAgents()->where('agent_id', $a->id);
            $won  = (clone $bids)->won()->count();
            $vehicles = Vehicle::allAgents()->where('agent_id', $a->id)->whereNotNull('won_at')->with('costing');
            return [
                'agent'  => $a->name,
                'bids'   => (clone $bids)->count(),
                'won'    => $won,
                'rate'   => (clone $bids)->count() > 0 ? round($won / (clone $bids)->count() * 100) : 0,
                'sales'  => $vehicles->get()->sum('selling_price'),
                'profit' => $vehicles->get()->sum(fn ($v) => $v->costing?->profit ?? 0),
            ];
        })->sortByDesc('won')->take(8)->values();

        // ===================== TOP CUSTOMERS =====================
        $topCustomers = Customer::complete()->get()->map(fn ($c) => [
            'customer'    => $c->name,
            'vehicles'    => $c->vehicles()->count(),
            'sales'       => $c->totalInvoiced(),
            'profit'      => $c->vehicles()->whereNotNull('won_at')->with('costing')->get()->sum(fn ($v) => $v->costing?->profit ?? 0),
            'outstanding' => $c->balance(),
        ])->sortByDesc('sales')->take(8)->values();

        // ===================== VENDOR LEDGER (company-wide totals) =====================
        $vendorToPay = Vehicle::whereNotNull('won_at')->with('costing')->get()->sum(fn ($v) => $v->costing?->total_costing ?? $v->buying_price ?? 0);
        $vendorPaid  = VendorPayment::sum('amount');
        $vendorLedger = ['to_pay' => (int) $vendorToPay, 'paid' => (int) $vendorPaid, 'outstanding' => (int) ($vendorToPay - $vendorPaid)];

        return view('home', compact(
            'stats', 'isPrivileged', 'financial', 'actionCentre', 'pipeline', 'revenueProfit',
            'shipLogistics', 'auctionPerf', 'topAuctionHouses', 'topAgents', 'topCustomers', 'vendorLedger'
        ));
    }
}