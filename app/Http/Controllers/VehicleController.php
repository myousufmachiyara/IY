<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Vehicle};
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::with('customer', 'agent', 'vendor')
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->get();

        // Customer model is already agent-scoped, so this naturally narrows for sales agents.
        $customers = Customer::orderBy('name')->get();

        return view('vehicles.index', compact('vehicles', 'customers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $customer = Customer::findOrFail($data['customer_id']);

        Vehicle::create($data + [
            'agent_id'   => $customer->agent_id,
            'created_by' => $request->user()->id,
            'status'     => 'requirement',
        ]);

        return back()->with('success', 'Vehicle requirement added.');
    }

    /** Modal edit form fetches this as JSON. */
    public function edit(Vehicle $vehicle)
    {
        return response()->json($vehicle);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        abort_if($vehicle->isWon(), 422, 'Won vehicles cannot have their requirement edited here — use Costing instead.');

        $vehicle->update($request->validate($this->rules()));

        return back()->with('success', 'Vehicle updated.');
    }

    public function show(Vehicle $vehicle)
    {
        $vehicle->load('customer', 'agent', 'vendor', 'costing', 'invoice.payments', 'documents', 'shipment', 'bid');
        return view('vehicles.show', compact('vehicle'));
    }

    public function destroy(Vehicle $vehicle)
    {
        abort_if($vehicle->isWon(), 422, 'A won vehicle cannot be deleted.');
        $vehicle->delete();

        return back()->with('success', 'Vehicle removed.');
    }

    private function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'make'        => ['nullable', 'string', 'max:120'],
            'model'       => ['nullable', 'string', 'max:120'],
            'year'        => ['nullable', 'string', 'max:10'],
            'grade'       => ['nullable', 'string', 'max:60'],
            'chassis_no'  => ['nullable', 'string', 'max:100'],
            'budget'      => ['required', 'integer', 'min:0'],
        ];
    }
}