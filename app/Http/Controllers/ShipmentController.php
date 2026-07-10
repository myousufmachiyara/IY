<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Shipment, Vehicle};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    public function index()
    {
        $shipments = Shipment::with('customer', 'vehicles')->latest()->paginate(15);
        return view('shipments.index', compact('shipments'));
    }

    public function create(Customer $customer)
    {
        // Only vehicles with a first-payment (50%) cleared can be batched for dispatch.
        $eligible = $customer->vehicles()
            ->whereHas('invoice', fn ($q) => $q->whereColumn('amount_paid', '>=', 'total_payable')
                ->orWhereRaw('amount_paid * 2 >= total_payable'))
            ->where('status', 'invoiced')
            ->get();

        return view('shipments.create', compact('customer', 'eligible'));
    }

    /** Customer go-ahead recorded + method chosen; vehicles stay at vendor yard until dispatched. */
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
        $shipment->load('vehicles.invoice', 'customer');
        return view('shipments.show', compact('shipment'));
    }

    /** Freight charges, shipment date & expected arrival — Super Admin only. */
    public function setSchedule(Request $request, Shipment $shipment)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'shipment_date'    => ['required', 'date'],
            'expected_arrival' => ['required', 'date', 'after:shipment_date'],
            'freight_total'    => ['required', 'integer', 'min:0'],
        ]);

        $shipment->update($data);

        // Final 50% due 15 days + 7 days grace BEFORE arrival.
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