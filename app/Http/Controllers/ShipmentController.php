<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Shipment, Vehicle};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    public function index()
    {
        $shipments = Shipment::with('customer', 'vehicles')->latest()->get();
        return view('shipments.index', compact('shipments'));
    }

    public function create(Customer $customer)
    {
        $eligible = $this->eligibleVehicles($customer);
        abort_if($eligible->isEmpty(), 422, 'This customer has no vehicles eligible for shipment yet.');
        return view('shipments.create', compact('customer', 'eligible'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'      => ['required', 'exists:customers,id'],
            'method'           => ['required', Rule::in(['RORO', 'Container'])],
            'expected_arrival' => ['required', 'date'],
            'container_no'     => ['nullable', 'string', 'max:100'],
            'bl_no'            => ['nullable', 'string', 'max:100'],
            'shipping_company' => ['nullable', 'string', 'max:255'],
            'vehicle_ids'      => ['required', 'array', 'min:1'],
            'vehicle_ids.*'    => ['exists:vehicles,id'],
        ]);

        $bypass = $request->user()->isSuperAdmin();

        $shipment = Shipment::create([
            'customer_id'      => $data['customer_id'],
            'method'           => $data['method'],
            'container_no'     => $data['container_no'] ?? null,
            'bl_no'            => $data['bl_no'] ?? null,
            'shipping_company' => $data['shipping_company'] ?? null,
            'expected_arrival' => $data['expected_arrival'],
            'status'           => 'preparing',
            'created_by'       => $request->user()->id,
        ]);

        $dueFinal = \Carbon\Carbon::parse($data['expected_arrival'])->subDays(22);

        foreach ($data['vehicle_ids'] as $vehicleId) {
            $vehicle = Vehicle::findOrFail($vehicleId);
            abort_unless($bypass || $vehicle->invoice?->isHalfPaid(), 422, "Vehicle #{$vehicle->id}: 50% must be paid before dispatch prep.");
            $vehicle->update(['shipment_id' => $shipment->id]);
            $vehicle->invoice?->update(['due_final' => $dueFinal]);
        }

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment created.');
    }

    public function show(Shipment $shipment)
    {
        $shipment->load('vehicles.invoice', 'vehicles.customer', 'customer');
        return view('shipments.show', compact('shipment'));
    }

    public function edit(Shipment $shipment)
    {
        abort_unless($shipment->status === 'preparing', 422, 'Only shipments still in "preparing" status can be edited.');
        $shipment->load('vehicles.invoice');
        $additional = $this->eligibleVehicles($shipment->customer)->whereNotIn('id', $shipment->vehicles->pluck('id'));
        return view('shipments.edit', compact('shipment', 'additional'));
    }

    public function update(Request $request, Shipment $shipment)
    {
        abort_unless($shipment->status === 'preparing', 422, 'Only shipments still in "preparing" status can be edited.');

        $data = $request->validate([
            'method'           => ['required', Rule::in(['RORO', 'Container'])],
            'expected_arrival' => ['required', 'date'],
            'container_no'     => ['nullable', 'string', 'max:100'],
            'bl_no'            => ['nullable', 'string', 'max:100'],
            'shipping_company' => ['nullable', 'string', 'max:255'],
            'vehicle_ids'      => ['required', 'array', 'min:1'],
            'vehicle_ids.*'    => ['exists:vehicles,id'],
        ]);

        $bypass = $request->user()->isSuperAdmin();

        $shipment->update([
            'method'           => $data['method'],
            'expected_arrival' => $data['expected_arrival'],
            'container_no'     => $data['container_no'] ?? null,
            'bl_no'            => $data['bl_no'] ?? null,
            'shipping_company' => $data['shipping_company'] ?? null,
        ]);

        $currentIds  = $shipment->vehicles()->pluck('vehicles.id')->toArray();
        $selectedIds = array_map('intval', $data['vehicle_ids']);

        Vehicle::where('shipment_id', $shipment->id)
            ->whereNotIn('id', $selectedIds)
            ->update(['shipment_id' => null]);

        foreach (array_diff($selectedIds, $currentIds) as $vehicleId) {
            $vehicle = Vehicle::findOrFail($vehicleId);
            abort_unless($vehicle->customer_id === $shipment->customer_id, 422, "Vehicle #{$vehicleId} does not belong to this shipment's customer.");
            abort_unless($bypass || $vehicle->invoice?->isHalfPaid(), 422, "Vehicle #{$vehicleId}: 50% must be paid before it can be added.");
            $vehicle->update(['shipment_id' => $shipment->id]);
        }

        $dueFinal = \Carbon\Carbon::parse($data['expected_arrival'])->subDays(22);
        foreach ($shipment->vehicles()->get() as $vehicle) {
            $vehicle->invoice?->update(['due_final' => $dueFinal]);
        }

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment updated.');
    }

    public function setSchedule(Request $request, Shipment $shipment)
    {
        $data = $request->validate([
            'shipment_date' => ['required', 'date'],
            'freight_total' => ['required', 'integer', 'min:0'],
        ]);

        $shipment->update($data);

        // Shipment's freight is authoritative once confirmed here — push it into
        // each vehicle's Costing and recompute. FIXED: must pass $sellingPrice as
        // the first argument to recalculate() — the old 2-arg call here would have
        // silently corrupted the costing the first time freight was ever set.
        foreach ($shipment->vehicles as $vehicle) {
            if ($costing = $vehicle->costing) {
                $costing->freight_charges = $data['freight_total'];
                $costing->recalculate(
                    $vehicle->selling_price,
                    $vehicle->agent->sales_commission_percent ?? 15,
                    (int) ($vehicle->agent->sales_fixed_bonus ?? 0)
                )->save();
            }
        }

        return back()->with('success', 'Shipment date and freight saved.');
    }

    public function dispatch(Shipment $shipment)
    {
        abort_unless($shipment->shipment_date, 422, 'Set the shipment date before dispatching.');
        $shipment->update(['status' => 'dispatched']);
        $shipment->vehicles()->update(['status' => 'dispatched']);
        return back()->with('success', 'Shipment marked as dispatched.');
    }

    public function arrive(Shipment $shipment)
    {
        $shipment->update(['status' => 'arrived']);
        $shipment->vehicles()->update(['status' => 'arrived']);
        return back()->with('success', 'Shipment marked as arrived.');
    }

    private function eligibleVehicles(Customer $customer)
    {
        $bypass = auth()->user()->isSuperAdmin();

        return $customer->vehicles()
            ->where('status', 'invoiced')
            ->whereNull('shipment_id')
            ->with('invoice')
            ->get()
            ->filter(fn ($v) => $bypass || $v->invoice?->isHalfPaid())
            ->values();
    }
}