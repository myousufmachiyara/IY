<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Vehicle, Invoice};
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
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

        // #26: system-recorded creation dates, surfaced as a cross-module activity feed.
        $stats['recent_activity'] = collect()
            ->merge(Customer::latest()->take(5)->get()->map(fn ($c) => [
                'type' => 'Customer', 'label' => $c->name, 'at' => $c->created_at, 'url' => route('customers.show', $c),
            ]))
            ->merge(Vehicle::latest()->take(5)->get()->map(fn ($v) => [
                'type' => 'Vehicle', 'label' => $v->label(), 'at' => $v->created_at, 'url' => route('vehicles.show', $v),
            ]))
            ->sortByDesc('at')
            ->take(8);

        return view('home', compact('stats'));
    }
}