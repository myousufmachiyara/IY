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
        $eligible = $customer->vehicles()
            ->where('status', 'invoiced')
            ->whereNull('shipment_id')
            ->with('invoice')
            ->get()
            ->filter(fn ($v) => $v->invoice?->isHalfPaid());

        abort_if($eligible->isEmpty(), 422, 'This customer has no vehicles eligible for shipment yet (50% payment required).');

        return view('shipments.create', compact('customer', 'eligible'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'   => ['required', 'exists:customers,id'],
            'method'        => ['required', Rule::in(['RORO', 'Container'])],
            'vehicle_ids'   => ['required', 'array', 'min:1'],
            'vehicle_ids.*' => ['exists:vehicles,id'],
        ]);

        $shipment = Shipment::create([
            'customer_id' => $data['customer_id'],
            'method'      => $data['method'],
            'status'      => 'preparing',
            'created_by'  => $request->user()->id,
        ]);

        foreach ($data['vehicle_ids'] as $vehicleId) {
            $vehicle = Vehicle::findOrFail($vehicleId);
            abort_unless($vehicle->invoice?->isHalfPaid(), 422, "Vehicle #{$vehicle->id}: 50% must be paid before dispatch prep.");
            $vehicle->update(['shipment_id' => $shipment->id]);
        }

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment created. Awaiting freight & dates from Super Admin.');
    }

    public function show(Shipment $shipment)
    {
        $shipment->load('vehicles.invoice', 'vehicles.customer', 'customer');
        return view('shipments.show', compact('shipment'));
    }

    /** Vehicle list / method — only while still "preparing". */
    public function edit(Shipment $shipment)
    {
        abort_unless($shipment->status === 'preparing', 422, 'Only shipments still in "preparing" status can be edited.');

        $shipment->load('vehicles.invoice');

        $additional = $shipment->customer->vehicles()
            ->where('status', 'invoiced')
            ->whereNull('shipment_id')
            ->with('invoice')
            ->get()
            ->filter(fn ($v) => $v->invoice?->isHalfPaid());

        return view('shipments.edit', compact('shipment', 'additional'));
    }

    public function update(Request $request, Shipment $shipment)
    {
        abort_unless($shipment->status === 'preparing', 422, 'Only shipments still in "preparing" status can be edited.');

        $data = $request->validate([
            'method'        => ['required', Rule::in(['RORO', 'Container'])],
            'vehicle_ids'   => ['required', 'array', 'min:1'],
            'vehicle_ids.*' => ['exists:vehicles,id'],
        ]);

        $shipment->update(['method' => $data['method']]);

        $currentIds  = $shipment->vehicles()->pluck('vehicles.id')->toArray();
        $selectedIds = array_map('intval', $data['vehicle_ids']);

        // Deselected vehicles free up back to unassigned.
        Vehicle::where('shipment_id', $shipment->id)
            ->whereNotIn('id', $selectedIds)
            ->update(['shipment_id' => null]);

        // Newly selected vehicles — re-validate eligibility before attaching.
        foreach (array_diff($selectedIds, $currentIds) as $vehicleId) {
            $vehicle = Vehicle::findOrFail($vehicleId);
            abort_unless($vehicle->customer_id === $shipment->customer_id, 422, "Vehicle #{$vehicleId} does not belong to this shipment's customer.");
            abort_unless($vehicle->invoice?->isHalfPaid(), 422, "Vehicle #{$vehicleId}: 50% must be paid before it can be added.");
            $vehicle->update(['shipment_id' => $shipment->id]);
        }

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment updated.');
    }

    public function setSchedule(Request $request, Shipment $shipment)
    {
        $data = $request->validate([
            'shipment_date'    => ['required', 'date'],
            'expected_arrival' => ['required', 'date', 'after:shipment_date'],
            'freight_total'    => ['required', 'integer', 'min:0'],
        ]);

        $shipment->update($data);

        $dueFinal = \Carbon\Carbon::parse($data['expected_arrival'])->subDays(22);
        foreach ($shipment->vehicles as $vehicle) {
            $vehicle->invoice?->update(['due_final' => $dueFinal]);
        }

        return back()->with('success', 'Freight and schedule set. Final payment due date calculated.');
    }

    public function dispatch(Shipment $shipment)
    {
        abort_unless($shipment->shipment_date, 422, 'Set freight & dates before dispatching.');

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
}