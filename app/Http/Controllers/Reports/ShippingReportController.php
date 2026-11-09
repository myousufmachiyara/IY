<?php

namespace App\Http\Controllers\Reports;

use App\Models\{Invoice, Shipment, Vehicle};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShippingReportController extends BaseReportController
{
    private function customerUrl($c): ?string
    {
        return $c ? $this->reportLink('customer_360', ['customer_id' => $c->id]) : null;
    }

    private function vehicleUrl(Vehicle $v): ?string
    {
        return $this->link('vehicle_requirement.show', 'vehicles.show', $v);
    }

    /** Paid % of a vehicle's current sale invoice, using approved payments dated on/before $asOf. */
    private function paidPct(?Invoice $inv, string $asOf): ?float
    {
        if (! $inv || $inv->status === 'cancelled') {
            return null;
        }
        $paid = (int) $inv->payments->where('status', 'approved')->filter(fn ($p) => $p->paid_at && $p->paid_at->toDateString() <= $asOf)->sum('amount');
        return $this->pct($paid, $inv->total_payable);
    }

    // ───────────────────────── 22 Shipment Overview ─────────────────────────

    public function overview(Request $request)
    {
        $agentId = $this->agentId($request);
        // vehicles relation carries the Vehicle agent scope, so agents only see — and count — their own cars.
        $vehicleFilter = fn ($q) => $q->when($agentId, fn ($w) => $w->where('agent_id', $agentId));

        $q = Shipment::with('customer')->withCount(['vehicles' => $vehicleFilter])
            ->when($agentId, fn ($q) => $q->whereHas('vehicles', $vehicleFilter))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->method, fn ($q, $v) => $q->where('method', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v));
        $this->range($q, 'shipment_date', $request->from, $request->to);
        $this->range($q, 'expected_arrival', $request->eta_from, $request->eta_to);

        $rows = $q->orderByDesc('shipment_date')->orderByDesc('id')->get()->map(fn (Shipment $s) => [
            'shipment' => "#{$s->id}", 'url' => $this->link('shipments.show', 'shipments.show', $s),
            'customer' => $s->customer->name ?? '—', 'customer_url' => $this->customerUrl($s->customer),
            'method' => $s->method,
            'reference' => $s->container_no ?: ($s->bl_no ?: '—'),
            'line' => $s->shipping_company,
            'vehicles' => $s->vehicles_count,
            'freight' => $s->freight_total,
            'ship_date' => $s->shipment_date?->toDateString(),
            'eta' => $s->expected_arrival?->toDateString(),
            'arrival' => $s->arrived_at?->toDateString(),
            'status' => $this->statusBadge($s->status),
        ]);

        return $this->render($request, 'shipment', [
            ['key' => 'shipment', 'label' => 'Shipment', 'link' => 'url'],
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'method', 'label' => 'Method'],
            ['key' => 'reference', 'label' => 'Container / BL'],
            ['key' => 'line', 'label' => 'Shipping Line'],
            ['key' => 'vehicles', 'label' => 'Vehicles', 'type' => 'int', 'total' => true],
            ['key' => 'freight', 'label' => 'Freight', 'type' => 'money', 'total' => true, 'sensitive' => true],
            ['key' => 'ship_date', 'label' => 'Ship Date', 'type' => 'date'],
            ['key' => 'eta', 'label' => 'ETA', 'type' => 'date'],
            ['key' => 'arrival', 'label' => 'Arrival', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ], $rows, $this->filters(
            $this->fDateRange('Ship date'),
            $this->fDateRange('ETA', 'eta_from', 'eta_to'),
            $this->fCustomer(),
            $this->fAgent(),
            $this->fSelect('method', 'Method', array_combine(Shipment::METHODS, Shipment::METHODS)),
            $this->fSelect('status', 'Status', ['preparing' => 'Preparing', 'dispatched' => 'Dispatched', 'arrived' => 'Arrived']),
        ), ['notes' => ['Sales agents see only shipments carrying their vehicles, and the vehicle count shows only their own vehicles.']]);
    }

    // ───────────────────────── 23 Ready for Shipment ─────────────────────────

    public function ready(Request $request)
    {
        $asOf = $this->asOf($request);
        $show = $request->input('show', 'eligible');

        $q = Vehicle::where('status', 'invoiced')->with('customer.ports', 'invoice.payments', 'shipment')
            ->when($this->agentId($request), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->port_id, fn ($q, $v) => $q->whereHas('customer.ports', fn ($p) => $p->where('ports.id', $v)));

        $rows = $q->get()->map(function (Vehicle $v) use ($asOf) {
            $inv = $v->invoice;
            $pct = $this->paidPct($inv, $asOf);
            $eligible = $pct !== null && $pct >= 50;
            $override = ! $eligible && $v->shipment_id;
            return [
                'vehicle' => $v->label() . ($v->chassis_no ? " · {$v->chassis_no}" : ''), 'vehicle_url' => $this->vehicleUrl($v),
                'customer' => $v->customer->name ?? '—', 'customer_url' => $this->customerUrl($v->customer),
                'port' => $v->customer?->ports->pluck('name')->join(', ') ?: '—',
                'invoice' => $inv?->invoice_no, 'invoice_url' => $inv ? $this->link('invoices.show', 'invoices.show', $inv) : null,
                'invoice_date' => $inv?->issued_at?->toDateString(),
                'pct' => $pct,
                'eligible' => $eligible,
                'override' => $override ? $this->badge('Gate overridden', 'warning') : null,
                'assignment' => $v->shipment ? "#{$v->shipment->id} · " . Str::headline($v->shipment->status) : 'Unassigned',
                'assignment_url' => $v->shipment ? $this->link('shipments.show', 'shipments.show', $v->shipment) : null,
                '_keep' => $eligible || $override,
            ];
        })->when($show === 'eligible', fn ($c) => $c->where('_keep', true))->sortByDesc('pct');

        return $this->render($request, 'ready_for_shipment', [
            ['key' => 'vehicle', 'label' => 'Vehicle', 'link' => 'vehicle_url'],
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'port', 'label' => 'Port'],
            ['key' => 'invoice', 'label' => 'Invoice', 'link' => 'invoice_url'],
            ['key' => 'invoice_date', 'label' => 'Invoice Date', 'type' => 'date'],
            ['key' => 'pct', 'label' => 'Paid %', 'type' => 'pct'],
            ['key' => 'eligible', 'label' => 'Eligible', 'type' => 'bool'],
            ['key' => 'override', 'label' => 'Override', 'type' => 'badge'],
            ['key' => 'assignment', 'label' => 'Shipment Assignment', 'link' => 'assignment_url'],
        ], $rows, $this->filters(
            $this->fDate('as_of', 'As of', $asOf),
            $this->fAgent(),
            $this->fCustomer(),
            $this->fPort(),
            $this->fSelect('show', 'Show', ['eligible' => 'Eligible + overrides', 'all' => 'All invoiced, not dispatched'], 'eligible', ''),
        ), [
            'cards' => [
                ['label' => 'Eligible', 'value' => $rows->where('eligible', true)->count(), 'type' => 'int', 'variant' => 'success'],
                ['label' => 'Unassigned & eligible', 'value' => $rows->where('eligible', true)->where('assignment', 'Unassigned')->count(), 'type' => 'int', 'variant' => 'warning'],
            ],
            'notes' => ['Eligible = invoiced, not yet dispatched, and ≥ 50% paid by approved payments on/before the as-of date. "Gate overridden" = already on a shipment below 50% (Super Admin bypass).'],
        ]);
    }

    // ───────────────────────── 24 Awaiting Shipment & Yard Aging ─────────────────────────

    public function awaiting(Request $request)
    {
        $asOf = $this->asOf($request);
        $asOfDate = Carbon::parse($asOf)->endOfDay();
        $minDays = (int) $request->input('days', 0);

        $q = Vehicle::whereIn('status', ['won', 'invoiced'])->with('customer', 'vendor', 'agent', 'invoice.payments', 'shipment')
            ->when($this->agentId($request), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->where(fn ($q) => $q->whereNull('won_at')->orWhere('won_at', '<=', $asOfDate));

        $rows = $q->get()->map(function (Vehicle $v) use ($asOf, $asOfDate) {
            $inv = $v->invoice && $v->invoice->status !== 'cancelled' ? $v->invoice : null;
            $pct = $this->paidPct($inv, $asOf);
            [$blocker, $variant] = match (true) {
                ! $inv && $v->invoice_requested_at => ['Invoice requested', 'info'],
                ! $inv                              => ['Not invoiced', 'danger'],
                $pct < 50                           => ['Payment below 50%', 'warning'],
                ! $v->shipment_id                   => ['Ready — needs shipment', 'primary'],
                default                             => ['On shipment — awaiting dispatch', 'success'],
            };
            return [
                'vehicle' => $v->label() . ($v->chassis_no ? " · {$v->chassis_no}" : ''), 'vehicle_url' => $this->vehicleUrl($v),
                'won_date' => $v->won_at?->toDateString(),
                'customer' => $v->customer->name ?? '—', 'customer_url' => $this->customerUrl($v->customer),
                'agent' => $v->agent->name ?? '—',
                'vendor' => $v->vendor->name ?? '—',
                'stage' => $this->statusBadge($v->status),
                'days' => $v->won_at ? (int) $v->won_at->copy()->startOfDay()->diffInDays($asOfDate->copy()->startOfDay()) : null,
                'pct' => $pct,
                'blocker' => $this->badge($blocker, $variant), 'blocker_key' => $blocker,
            ];
        })->filter(fn ($r) => ($r['days'] ?? 0) >= $minDays)->sortByDesc('days');

        $byBlocker = $rows->groupBy('blocker_key')->map->count();

        return $this->render($request, 'awaiting_shipment', [
            ['key' => 'vehicle', 'label' => 'Vehicle', 'link' => 'vehicle_url'],
            ['key' => 'won_date', 'label' => 'Won Date', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'vendor', 'label' => 'Vendor / Yard'],
            ['key' => 'stage', 'label' => 'Current Stage', 'type' => 'badge'],
            ['key' => 'days', 'label' => 'Days Waiting', 'type' => 'int'],
            ['key' => 'pct', 'label' => 'Payment %', 'type' => 'pct'],
            ['key' => 'blocker', 'label' => 'Blocker', 'type' => 'badge'],
        ], $rows, $this->filters(
            $this->fDate('as_of', 'As of', $asOf),
            $this->fAgent(),
            $this->fVendor(),
            $this->fNumber('days', 'Days waiting ≥', $minDays ?: null),
        ), [
            'cards' => array_merge(
                [['label' => 'Vehicles waiting', 'value' => $rows->count(), 'type' => 'int'],
                 ['label' => 'Avg days waiting', 'value' => (int) round($rows->avg('days') ?? 0), 'type' => 'int']],
                $byBlocker->map(fn ($n, $b) => ['label' => $b, 'value' => $n, 'type' => 'int'])->values()->all()
            ),
            'notes' => ['Won and not yet dispatched. Days waiting counts from the won date. The vendor is shown as the yard; exact yard location is not stored.'],
        ]);
    }

    // ───────────────────────── 25 Shipping Cost Analysis ─────────────────────────

    public function cost(Request $request)
    {
        $q = Shipment::with('customer', 'vehicles.costing')->withCount('vehicles')
            ->where('status', '!=', 'cancelled')
            ->when($request->method, fn ($q, $v) => $q->where('method', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->country, fn ($q, $v) => $q->whereHas('customer', fn ($c) => $c->where('country', $v)));
        $this->range($q, 'shipment_date', $request->from, $request->to);
        $shipments = $q->orderBy('shipment_date')->get();

        // Benchmark: average freight per vehicle per method across all (unfiltered) shipments.
        $avgByMethod = Shipment::withCount('vehicles')->where('status', '!=', 'cancelled')->where('freight_total', '>', 0)->get()
            ->filter(fn ($s) => $s->vehicles_count > 0)->groupBy('method')
            ->map(fn ($g) => $g->sum('freight_total') / max($g->sum('vehicles_count'), 1));

        $rows = $shipments->map(function (Shipment $s) use ($avgByMethod) {
            $perVehicle = $s->vehicles_count ? (int) round($s->freight_total / $s->vehicles_count) : 0;
            $avg = $avgByMethod[$s->method] ?? 0;
            $allocated = (int) $s->vehicles->sum(fn ($v) => $v->costing?->freight_charges ?? 0);
            return [
                'shipment' => "#{$s->id}", 'url' => $this->link('shipments.show', 'shipments.show', $s),
                'customer' => $s->customer->name ?? '—', 'route' => $s->customer->country ?? '—',
                'method' => $s->method,
                'vehicles' => $s->vehicles_count,
                'freight' => $s->freight_total,
                'per_vehicle' => $perVehicle,
                'allocated' => $allocated,
                'allocation' => $allocated === (int) $s->freight_total ? $this->badge('Matches', 'success') : $this->badge('Diff ¥' . number_format($allocated - $s->freight_total), 'warning'),
                'trend' => $avg && $perVehicle ? round(($perVehicle - $avg) / $avg * 100, 1) : null,
                'date' => $s->shipment_date?->toDateString(),
            ];
        });

        return $this->render($request, 'shipping_cost', [
            ['key' => 'shipment', 'label' => 'Shipment', 'link' => 'url'],
            ['key' => 'date', 'label' => 'Ship Date', 'type' => 'date'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'route', 'label' => 'Destination'],
            ['key' => 'method', 'label' => 'Method'],
            ['key' => 'vehicles', 'label' => 'Vehicles', 'type' => 'int', 'total' => true],
            ['key' => 'freight', 'label' => 'Total Freight', 'type' => 'money', 'total' => true],
            ['key' => 'per_vehicle', 'label' => 'Freight / Vehicle', 'type' => 'money'],
            ['key' => 'allocated', 'label' => 'Allocated to Costings', 'type' => 'money', 'total' => true],
            ['key' => 'allocation', 'label' => 'Allocation Check', 'type' => 'badge'],
            ['key' => 'trend', 'label' => 'vs Method Avg %', 'type' => 'pct'],
        ], $rows, $this->filters(
            $this->fDateRange('Ship date'),
            $this->fSelect('method', 'Method', array_combine(Shipment::METHODS, Shipment::METHODS)),
            $this->fCountry('Route / destination'),
            $this->fCustomer(),
        ), [
            'totals' => ['per_vehicle' => $rows->sum('vehicles') ? (int) round($rows->sum('freight') / $rows->sum('vehicles')) : 0],
            'chart' => ['type' => 'line', 'labels' => $rows->map(fn ($r) => $r['shipment'] . ' ' . ($r['date'] ?? '')), 'series' => [['label' => 'Freight / vehicle', 'data' => $rows->pluck('per_vehicle')]]],
            'notes' => ['Freight is split evenly across the vehicles on a shipment (current rule); "Allocated to Costings" must equal Total Freight — rounding can leave a few yen.',
                        '"vs Method Avg %" compares freight per vehicle with the average for the same method across all shipments.'],
        ]);
    }
}
