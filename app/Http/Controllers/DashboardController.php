<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Vehicle, Invoice, Vendor, Bid};
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isPrivileged = $user->can('data.view_all');

        $stats = [
            'customers'         => Customer::count(),
            'requirements'      => Vehicle::where('status', 'requirement')->count(),
            'pending_bids'      => Bid::where('result', 'pending')->count(),
            'won_this_month'    => Vehicle::whereNotNull('won_at')
                ->whereMonth('won_at', now()->month)->whereYear('won_at', now()->year)->count(),
            'outstanding'       => Invoice::whereIn('status', ['issued', 'partial'])->get()->sum(fn ($i) => $i->balance()),
            'overdue_invoices'  => Invoice::whereIn('status', ['issued', 'partial'])->get()
                ->filter(fn ($i) => ($i->due_first && !$i->isHalfPaid() && now()->gt($i->due_first)) ||
                                     ($i->due_final && !$i->isFullyPaid() && now()->gt($i->due_final)))->count(),
        ];

        if ($isPrivileged) {
            $stats['pending_deposits'] = Customer::where('security_deposit_status', 'pending')->count();
            $stats['vendor_payable']   = Vendor::get()->sum(fn ($v) => $v->balance());
        }

        return view('home', compact('stats', 'isPrivileged'));
    }
}