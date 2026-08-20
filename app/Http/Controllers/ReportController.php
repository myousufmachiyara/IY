<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\{Bid, Customer, User, Vehicle, Vendor};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function agentWise(Request $request)
    {
        $agentsQuery = User::permission('scope.by_agent');
        if (! $request->user()->can('data.view_all')) {
            $agentsQuery->where('id', $request->user()->id);
        } elseif ($request->agent_id) {
            $agentsQuery->where('id', $request->agent_id);
        }

        $rows = $agentsQuery->get()->map(function ($agent) use ($request) {
            $vehicles = Vehicle::allAgents()->where('agent_id', $agent->id)
                ->when($request->from, fn ($q, $v) => $q->whereDate('won_at', '>=', $v))
                ->when($request->to, fn ($q, $v) => $q->whereDate('won_at', '<=', $v));
            $bids = Bid::allAgents()->where('agent_id', $agent->id)
                ->when($request->from, fn ($q, $v) => $q->whereDate('auction_date', '>=', $v))
                ->when($request->to, fn ($q, $v) => $q->whereDate('auction_date', '<=', $v));

            return [
                'agent'        => $agent->name,
                'total_bids'   => (clone $bids)->count(),
                'bids_won'     => (clone $bids)->won()->count(),
                'vehicles_won' => (clone $vehicles)->won()->count(),
                'profit'       => (clone $vehicles)->won()->with('costing')->get()->sum(fn ($v) => $v->costing?->profit ?? 0),
                'earnings'     => (clone $vehicles)->won()->with('costing')->get()->sum(fn ($v) => $v->costing?->agentEarning() ?? 0),
            ];
        });

        $agents = User::permission('scope.by_agent')->orderBy('name')->get();
        return $this->respond($request, 'reports.agent_wise', $rows, ['Agent', 'Total Bids', 'Bids Won', 'Vehicles Won', 'Profit (¥)', 'Earnings (¥)'], compact('agents'));
    }

    public function vendorWise(Request $request)
    {
        $rows = Vendor::when($request->vendor_id, fn ($q, $v) => $q->where('id', $v))->get()->map(function ($v) use ($request) {
            $vehicles = $v->vehicles()
                ->when($request->from, fn ($q, $f) => $q->whereDate('won_at', '>=', $f))
                ->when($request->to, fn ($q, $t) => $q->whereDate('won_at', '<=', $t));
            return [
                'vendor'   => $v->name,
                'location' => $v->location,
                'vehicles' => (clone $vehicles)->count(),
                'payable'  => (clone $vehicles)->sum('buying_price'),
                'paid'     => $v->payments()->sum('amount'),
            ];
        });

        $vendors = Vendor::orderBy('name')->get();
        return $this->respond($request, 'reports.vendor_wise', $rows, ['Vendor', 'Location', 'Vehicles', 'Payable (¥)', 'Paid (¥)'], compact('vendors'));
    }

    public function bidWise(Request $request)
    {
        $rows = Bid::allAgents()->selectRaw('auction_date, COUNT(*) as total, SUM(result = "won") as won')
            ->whereNotNull('auction_date')
            ->when($request->from, fn ($q, $v) => $q->whereDate('auction_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('auction_date', '<=', $v))
            ->groupBy('auction_date')->orderBy('auction_date')->get()
            ->map(fn ($r) => ['date' => (string) $r->auction_date, 'total_bids' => $r->total, 'won' => (int) $r->won]);

        return $this->respond($request, 'reports.bid_wise', $rows, ['Date', 'Total Bids', 'Won']);
    }

    public function bidWon(Request $request)
    {
        $rows = Bid::allAgents()->won()->with('agent', 'customer')
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('auction_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('auction_date', '<=', $v))
            ->get()->map(fn ($b) => [
                'lot' => $b->lot_no, 'agent' => $b->agent?->name, 'customer' => $b->customer?->name,
                'vehicle' => trim("{$b->year} {$b->make} {$b->model}"), 'amount' => $b->won_amount,
            ]);

        $agents = User::permission('scope.by_agent')->orderBy('name')->get();
        return $this->respond($request, 'reports.bid_won', $rows, ['Lot', 'Agent', 'Customer', 'Vehicle', 'Won Amount (¥)'], compact('agents'));
    }

    public function customerWise(Request $request)
    {
        $rows = Customer::complete()->with('agent')
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->get()->map(fn ($c) => [
                'customer' => $c->name, 'agent' => $c->agent->name ?? '—',
                'vehicles' => $c->vehicles()->count(), 'invoiced' => $c->totalInvoiced(),
                'paid' => $c->totalPaid(), 'balance' => $c->balance(),
            ]);

        $agents = User::permission('scope.by_agent')->orderBy('name')->get();
        return $this->respond($request, 'reports.customer_wise', $rows, ['Customer', 'Agent', 'Vehicles', 'Invoiced (¥)', 'Paid (¥)', 'Balance (¥)'], compact('agents'));
    }

    private function respond(Request $request, string $view, $rows, array $headings, array $extra = [])
    {
        if ($request->export === 'excel') {
            return Excel::download(new ArrayExport($rows->map(fn ($r) => array_values($r))->toArray(), $headings), 'report.xlsx');
        }
        if ($request->export === 'pdf') {
            return Pdf::loadView('reports.pdf', [
                'rows' => $rows, 'headings' => $headings,
                'title' => \Illuminate\Support\Str::headline(str($view)->afterLast('.')) . ' Report',
            ])->download('report.pdf');
        }
        return view($view, array_merge(compact('rows', 'headings'), $extra));
    }
}