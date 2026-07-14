<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Vehicle, Invoice};
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Customer/Vehicle/Invoice all use the ScopedToAgent trait, so these counts
        // automatically narrow to "my own" for a sales agent, and stay unrestricted
        // for super admin / accountant — no extra filtering needed here.
        $stats = [
            'customers'      => Customer::count(),
            'in_bidding'     => Vehicle::whereIn('status', ['requirement', 'bidding'])->count(),
            'won_this_month' => Vehicle::whereNotNull('won_at')
                ->whereMonth('won_at', now()->month)
                ->whereYear('won_at', now()->year)
                ->count(),
            'outstanding'    => Invoice::whereIn('status', ['issued', 'partial'])
                ->get()
                ->sum(fn ($i) => $i->balance()),
        ];

        return view('home', compact('stats'));
    }
}