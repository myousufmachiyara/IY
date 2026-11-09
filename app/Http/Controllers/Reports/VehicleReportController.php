<?php

namespace App\Http\Controllers\Reports;

use App\Models\{Vehicle, Vendor};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VehicleReportController extends BaseReportController
{
    private function vehicleUrl(Vehicle $v): ?string
    {
        return $this->link('vehicle_requirement.show', 'vehicles.show', $v);
    }

    private function customerUrl($c): ?string
    {
        return $c ? $this->reportLink('customer_360', ['customer_id' => $c->id]) : null;
    }

    // ───────────────────────── 11 Won Vehicles ─────────────────────────

    public function won(Request $request)
    {
        $q = Vehicle::whereIn('status', self::WON_STATUSES)->with('customer', 'agent', 'vendor', 'bid')
            ->when($this->agentId($request), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v));
        $this->range($q, 'won_at', $request->from, $request->to);

        $rows = $q->orderByDesc('won_at')->get()->map(fn (Vehicle $v) => [
            'won_date' => $v->won_at?->toDateString(),
            'lot' => $v->bid?->lot_no,
            'chassis' => $v->chassis_no,
            'vehicle' => $v->label(), 'vehicle_url' => $this->vehicleUrl($v),
            'customer' => $v->customer->name ?? '—', 'customer_url' => $this->customerUrl($v->customer),
            'agent' => $v->agent->name ?? '—',
            'vendor' => $v->vendor->name ?? '—',
            'buy_price' => $v->buying_price,
            'status' => $this->statusBadge($v->status),
        ]);

        return $this->render($request, 'bid_won', [
            ['key' => 'won_date', 'label' => 'Won Date', 'type' => 'date'],
            ['key' => 'lot', 'label' => 'Lot'],
            ['key' => 'chassis', 'label' => 'Chassis'],
            ['key' => 'vehicle', 'label' => 'Vehicle', 'link' => 'vehicle_url'],
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'vendor', 'label' => 'Vendor'],
            ['key' => 'buy_price', 'label' => 'Buy Price', 'type' => 'money', 'total' => true, 'sensitive' => true],
            ['key' => 'status', 'label' => 'Current Status', 'type' => 'badge'],
        ], $rows, $this->filters(
            $this->fDateRange('Won date'),
            $this->fAgent(),
            $this->fVendor(),
            $this->fCustomer(),
        ), [
            'cards' => [['label' => 'Vehicles Won', 'value' => $rows->count(), 'type' => 'int']],
            'notes' => ['A vehicle stays on this list after it moves on to invoiced, dispatched, arrived or delivered. Undone wins drop off automatically.'],
        ]);
    }

    // ───────────────────────── 12 Vehicle Pipeline ─────────────────────────

    public function pipeline(Request $request)
    {
        $stages = ['requirement' => 'Requirement', 'bidding' => 'Bidding', 'won' => 'Won (at yard)', 'invoiced' => 'Invoiced',
                   'dispatched' => 'Dispatched', 'arrived' => 'Arrived', 'delivered' => 'Delivered'];

        $q = Vehicle::where('status', '!=', 'lost')->with('customer', 'agent', 'invoice', 'shipment')
            ->when($this->agentId($request), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->stage, fn ($q, $v) => $q->where('status', $v));
        if ($request->from || $request->to) {
            $this->range($q, 'won_at', $request->from, $request->to);
        }

        $all = $q->get();
        $rows = $all->map(function (Vehicle $v) use ($stages) {
            $inv = $v->invoice && $v->invoice->status !== 'cancelled' ? $v->invoice : null;
            // Best available timestamp for when the vehicle entered its current stage.
            $since = match ($v->status) {
                'won'        => $v->won_at,
                'invoiced'   => $inv?->issued_at ?? $v->updated_at,
                'dispatched' => $v->shipment?->shipment_date ?? $v->updated_at,
                'arrived'    => $v->shipment?->arrived_at ?? $v->updated_at,
                'delivered'  => $v->updated_at,
                default      => $v->created_at,
            };
            return [
                'vehicle' => $v->label() . ($v->chassis_no ? " · {$v->chassis_no}" : ''), 'vehicle_url' => $this->vehicleUrl($v),
                'customer' => $v->customer->name ?? '—', 'customer_url' => $this->customerUrl($v->customer),
                'agent' => $v->agent->name ?? '—',
                'won_date' => $v->won_at?->toDateString(),
                'invoice' => $inv?->invoice_no, 'invoice_url' => $inv ? $this->link('invoices.show', 'invoices.show', $inv) : null,
                'paid_pct' => $inv ? $inv->paidPercent() : null,
                'shipment' => $v->shipment ? "#{$v->shipment->id} · " . Str::headline($v->shipment->status) : null,
                'shipment_url' => $v->shipment ? $this->link('shipments.show', 'shipments.show', $v->shipment) : null,
                'stage' => $this->statusBadge($v->status), 'stage_key' => $v->status,
                'days' => $since ? (int) $since->copy()->startOfDay()->diffInDays(today()) : null,
            ];
        })->sortBy(fn ($r) => array_search($r['stage_key'], array_keys($stages)) * 100000 - ($r['days'] ?? 0))->values();

        $cards = collect($stages)->map(fn ($label, $k) => ['label' => $label, 'value' => $all->where('status', $k)->count(), 'type' => 'int'])
            ->filter(fn ($c) => $c['value'] > 0)->values()->all();

        return $this->render($request, 'vehicle_pipeline', [
            ['key' => 'vehicle', 'label' => 'Vehicle', 'link' => 'vehicle_url'],
            ['key' => 'customer', 'label' => 'Customer', 'link' => 'customer_url'],
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'won_date', 'label' => 'Won Date', 'type' => 'date'],
            ['key' => 'invoice', 'label' => 'Invoice', 'link' => 'invoice_url'],
            ['key' => 'paid_pct', 'label' => 'Paid %', 'type' => 'pct'],
            ['key' => 'shipment', 'label' => 'Shipment', 'link' => 'shipment_url'],
            ['key' => 'stage', 'label' => 'Stage', 'type' => 'badge'],
            ['key' => 'days', 'label' => 'Days in Stage', 'type' => 'int'],
        ], $rows, $this->filters(
            $this->fDateRange('Won date'),
            $this->fAgent(),
            $this->fCustomer(),
            $this->fSelect('stage', 'Stage', $stages),
        ), [
            'cards' => $cards,
            'notes' => ['Paid % = approved payments ÷ settled payable.',
                        'Days in stage is measured from the won date, invoice date, shipment date or arrival date; the app does not yet store a timestamp for every status change, so requirement/bidding/delivered use the record\'s created/updated time.'],
        ]);
    }

    // ───────────────────────── 13 Vehicle Profitability ─────────────────────────

    public function profitability(Request $request)
    {
        $q = Vehicle::whereIn('status', self::WON_STATUSES)->with('customer', 'agent', 'costing', 'invoice')
            ->when($this->agentId($request), fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v));
        $this->range($q, 'won_at', $request->from, $request->to);
        if ($request->inv_from || $request->inv_to) {
            $q->whereHas('invoice', fn ($i) => $this->range($i->where('status', '!=', 'cancelled'), 'issued_at', $request->inv_from, $request->inv_to));
        }

        $rows = $q->orderByDesc('won_at')->get()->map(function (Vehicle $v) {
            $c = $v->costing;
            $sale = $v->selling_price ?: ($c?->sale_price ?? 0);
            $gross = $c?->profit ?? 0;
            return [
                'vehicle' => $v->label(), 'vehicle_url' => $this->link('costings.show', 'costings.show', $v) ?? $this->vehicleUrl($v),
                'chassis' => $v->chassis_no,
                'customer' => $v->customer->name ?? '—', 'agent' => $v->agent->name ?? '—',
                'won_date' => $v->won_at?->toDateString(),
                'buy' => $c?->buying_price ?? (int) $v->buying_price,
                'vendor_comm' => $c?->vendor_commission_amount ?? 0,
                'inland' => $c?->inland_charges ?? 0,
                'auction' => $c?->auction_commission ?? 0,
                'freight' => $c?->freight_charges ?? 0,
                'misc' => $c?->misc_expenses ?? 0,
                'total_cost' => $c?->total_costing ?? (int) $v->buying_price,
                'sale' => $sale,
                'gross' => $gross,
                'agent_earning' => $c?->agentEarning() ?? 0,
                'company' => $c?->finalProfit() ?? 0,
                'margin' => $this->pct($gross, $sale),
                'status' => $this->statusBadge($v->status),
            ];
        });

        return $this->render($request, 'vehicle_profitability', [
            ['key' => 'vehicle', 'label' => 'Vehicle', 'link' => 'vehicle_url'],
            ['key' => 'chassis', 'label' => 'Chassis'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'agent', 'label' => 'Agent'],
            ['key' => 'won_date', 'label' => 'Won Date', 'type' => 'date'],
            ['key' => 'buy', 'label' => 'Buy Price', 'type' => 'money', 'total' => true],
            ['key' => 'vendor_comm', 'label' => 'Vendor Comm.', 'type' => 'money', 'total' => true],
            ['key' => 'inland', 'label' => 'Inland', 'type' => 'money', 'total' => true],
            ['key' => 'auction', 'label' => 'Auction', 'type' => 'money', 'total' => true],
            ['key' => 'freight', 'label' => 'Freight', 'type' => 'money', 'total' => true],
            ['key' => 'misc', 'label' => 'Misc', 'type' => 'money', 'total' => true],
            ['key' => 'total_cost', 'label' => 'Total Cost', 'type' => 'money', 'total' => true],
            ['key' => 'sale', 'label' => 'Sale', 'type' => 'money', 'total' => true],
            ['key' => 'gross', 'label' => 'Gross Profit', 'type' => 'money', 'total' => true],
            ['key' => 'agent_earning', 'label' => 'Agent Earnings', 'type' => 'money', 'total' => true],
            ['key' => 'company', 'label' => 'Company Profit', 'type' => 'money', 'total' => true],
            ['key' => 'margin', 'label' => 'Margin %', 'type' => 'pct'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ], $rows, $this->filters(
            $this->fDateRange('Won date'),
            $this->fDateRange('Invoice date', 'inv_from', 'inv_to'),
            $this->fAgent(),
            $this->fCustomer(),
            $this->fSelect('status', 'Status', array_combine(self::WON_STATUSES, array_map(fn ($s) => Str::headline($s), self::WON_STATUSES))),
        ), [
            'totals' => ['margin' => $this->pct($rows->sum('gross'), $rows->sum('sale'))],
            'notes' => ['Gross = Sale − Total Cost; Company = Gross − Agent Earnings (same formulas as the Costing screen).',
                        'Includes every post-win status, not just vehicles still sitting at "won".'],
        ]);
    }
}
