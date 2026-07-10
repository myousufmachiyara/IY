<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Vehicle};
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::with('customer')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->latest()->paginate(15)->withQueryString();

        return view('vehicles.index', compact('vehicles'));
    }

    public function create()
    {
        return view('vehicles.create', ['vehicle' => new Vehicle, 'customers' => Customer::active()->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        // Scoped find: an agent can only attach vehicles to their own customers.
        $customer = Customer::findOrFail($data['customer_id']);

        Vehicle::create($data + [
            'agent_id'   => $customer->agent_id,
            'created_by' => $request->user()->id,
            'status'     => 'requirement',
        ]);

        return redirect()->route('vehicles.index')->with('success', 'Vehicle requirement added.');
    }

    public function edit(Vehicle $vehicle)
    {
        return view('vehicles.edit', ['vehicle' => $vehicle, 'customers' => Customer::active()->get()]);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $vehicle->update($request->validate($this->rules()));
        return redirect()->route('vehicles.index')->with('success', 'Vehicle updated.');
    }

    public function destroy(Vehicle $vehicle)
    {
        abort_if($vehicle->isWon(), 422, 'A won vehicle cannot be deleted.');
        $vehicle->delete();
        return back()->with('success', 'Vehicle removed.');
    }

    public function show(Vehicle $vehicle)
    {
        $vehicle->load('customer', 'agent', 'vendor', 'costing', 'invoice.payments', 'documents', 'shipment', 'bid');
        return view('vehicles.show', compact('vehicle'));
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