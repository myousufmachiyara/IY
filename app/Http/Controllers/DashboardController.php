<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Vehicle, Invoice, Vendor, Bid, User};
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isPrivileged = $user->can('data.view_all');

        $stats = [
            'customers'        => Customer::count(),
            'requirements'     => Vehicle::where('status', 'requirement')->count(),
            'pending_bids'     => Bid::where('result', 'pending')->count(),
            'won_this_month'   => Vehicle::whereNotNull('won_at')
                ->whereMonth('won_at', now()->month)->whereYear('won_at', now()->year)->count(),
            'outstanding'      => Invoice::whereIn('status', ['issued', 'partial'])->get()->sum(fn ($i) => $i->balance()),
            'overdue_invoices' => Invoice::whereIn('status', ['issued', 'partial'])->get()
                ->filter(fn ($i) => ($i->due_first && !$i->isHalfPaid() && now()->gt($i->due_first)) ||
                                     ($i->due_final && !$i->isFullyPaid() && now()->gt($i->due_final)))->count(),
        ];

        if ($isPrivileged) {
            $stats['pending_deposits'] = Customer::where('security_deposit_status', 'pending')->count();
            $stats['vendor_payable']   = Vendor::get()->sum(fn ($v) => $v->balance());
        }

        // Last 6 months, oldest first — reused as labels for both trend charts.
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i));
        $monthLabels = $months->map(fn ($m) => $m->format('M Y'))->all();

        $revenueChart = [
            'labels' => $monthLabels,
            'data'   => $months->map(fn ($m) => (int) Invoice::whereMonth('issued_at', $m->month)->whereYear('issued_at', $m->year)->sum('sale_price'))->all(),
        ];

        // updated_at is used as a proxy for "when this bid was resolved" — Bid rows
        // are only ever updated via won()/lost()/assignCustomer(), so this stays accurate.
        $biddingChart = [
            'labels' => $monthLabels,
            'won'    => $months->map(fn ($m) => Bid::where('result', 'won')->whereMonth('updated_at', $m->month)->whereYear('updated_at', $m->year)->count())->all(),
            'lost'   => $months->map(fn ($m) => Bid::where('result', 'lost')->whereMonth('updated_at', $m->month)->whereYear('updated_at', $m->year)->count())->all(),
        ];

        $statusLabels = ['draft' => 'Draft', 'issued' => 'Issued', 'partial' => 'Partial', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];
        $invoiceStatusChart = [
            'labels' => array_values($statusLabels),
            'data'   => collect($statusLabels)->keys()->map(fn ($s) => Invoice::where('status', $s)->count())->all(),
        ];
        $invoiceStatusChart['total'] = array_sum($invoiceStatusChart['data']);

        $agentChart  = null;
        $vendorChart = null;

        if ($isPrivileged) {
            $agentRows = User::permission('scope.by_agent')->get()
                ->map(fn ($a) => ['name' => $a->name, 'count' => Vehicle::allAgents()->where('agent_id', $a->id)->won()->count()])
                ->sortByDesc('count')->take(8);

            $agentChart = ['labels' => $agentRows->pluck('name')->all(), 'data' => $agentRows->pluck('count')->all()];

            $vendorRows = Vendor::get()->map(fn ($v) => ['name' => $v->name, 'balance' => $v->balance()])
                ->filter(fn ($r) => $r['balance'] > 0)->sortByDesc('balance')->take(8);

            $vendorChart = ['labels' => $vendorRows->pluck('name')->all(), 'data' => $vendorRows->pluck('balance')->all()];
        }

        return view('home', compact('stats', 'isPrivileged', 'revenueChart', 'biddingChart', 'invoiceStatusChart', 'agentChart', 'vendorChart'));
    }
}