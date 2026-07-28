<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Vehicle};
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::with('customer', 'agent', 'invoice')
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->make, fn ($q) => $q->where('make', 'like', "%{$request->make}%"))
            ->when($request->model, fn ($q) => $q->where('model', 'like', "%{$request->model}%"))
            ->when($request->from, fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when(! $request->boolean('show_won'), fn ($q) => $q->where('status', 'requirement'))
            ->latest()
            ->get();

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

    /** #40 — Agent flags a won vehicle as ready to invoice; accountant/admin still does the actual generation. */
    public function requestInvoice(Vehicle $vehicle)
    {
        abort_unless($vehicle->isWon() && ! $vehicle->invoice, 422, 'Not eligible for an invoice request.');
        $vehicle->update(['invoice_requested_at' => now()]);

        return back()->with('success', 'Invoice requested — accountant/admin will be notified.');
    }

    private function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'make'        => ['required', 'string', 'max:120'],
            'model'       => ['required', 'string', 'max:120'],
            'year'        => ['required', 'string', 'max:10'],
            'grade'       => ['required', 'string', 'max:60'],
            'budget'      => ['required', 'integer', 'min:1'],
        ];
    }
}