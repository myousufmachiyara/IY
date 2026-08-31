<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Vehicle};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::with('customer', 'agent')
            ->where('status', 'requirement')
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->make, fn ($q) => $q->where('make', 'like', "%{$request->make}%"))
            ->when($request->model, fn ($q) => $q->where('model', 'like', "%{$request->model}%"))
            ->when($request->from, fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest()->get();

        $customers = Customer::orderBy('name')->get();
        return view('vehicles.index', compact('vehicles', 'customers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request));
        $customer = Customer::findOrFail($data['customer_id']);

        if (empty($data['requirement_date'])) {
            $data['requirement_date'] = now()->toDateString();
        } elseif (! $request->user()->isSuperAdmin() && ! \Carbon\Carbon::parse($data['requirement_date'])->isToday()) {
            return back()->withErrors(['requirement_date' => 'Only Super Admin may set a requirement date other than today.'])->withInput();
        }

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

        $data = $request->validate($this->rules($request));

        if (! empty($data['requirement_date']) && ! $request->user()->isSuperAdmin() && ! \Carbon\Carbon::parse($data['requirement_date'])->isToday()) {
            return back()->withErrors(['requirement_date' => 'Only Super Admin may set a requirement date other than today.'])->withInput();
        }

        $vehicle->update($data);
        return back()->with('success', 'Vehicle updated.');
    }

    public function show(Vehicle $vehicle)
    {
        $vehicle->load('customer', 'agent', 'vendor', 'costing', 'invoice.payments', 'documents', 'shipment', 'bid');

        $customers = Customer::where('id', '!=', $vehicle->customer_id)->orderBy('name')->get();
        $agents = \App\Models\User::permission('scope.by_agent')->orderBy('name')->get();

        return view('vehicles.show', compact('vehicle', 'customers', 'agents'));
    }

    public function destroy(Vehicle $vehicle)
    {
        abort_if($vehicle->isWon(), 422, 'A won vehicle cannot be deleted.');
        $vehicle->delete();
        return back()->with('success', 'Vehicle removed.');
    }

    public function requestInvoice(Vehicle $vehicle)
    {
        abort_unless($vehicle->isWon() && ! $vehicle->invoice, 422, 'Not eligible for an invoice request.');
        $vehicle->update(['invoice_requested_at' => now()]);
        return back()->with('success', 'Invoice requested — accountant/admin will be notified.');
    }

    public function cancelInvoiceRequest(Request $request, Vehicle $vehicle)
    {
        abort_unless($request->user()->can('invoices.request') || $request->user()->canBackdate(), 403);
        abort_unless($vehicle->invoice_requested_at, 422, 'No pending invoice request to cancel.');

        $vehicle->update(['invoice_requested_at' => null]);

        return back()->with('success', 'Invoice request cancelled.');
    }

    public function depositInvoicePdf(Vehicle $vehicle)
    {
        $vehicle->load('customer');
        abort_unless($vehicle->customer->security_deposit_status === 'approved', 422, "This customer's security deposit has not been approved yet.");

        $ref = 'IY/DEP/' . str_pad($vehicle->id, 3, '0', STR_PAD_LEFT);

        return Pdf::loadView('invoices.print', [
            'type'         => 'auction',
            'invoice_no'   => $ref,
            'date'         => now()->format('d/m/Y'),
            'customer'     => $vehicle->customer,
            'vehicle'      => $vehicle,
            'amount_label' => 'AUCTION DEPOSIT',
            'total_label'  => 'AUCTION DEPOSIT',
            'amount'       => $vehicle->customer->security_deposit,
        ])->download("{$ref}.pdf");
    }

    private function rules(Request $request): array
    {
        return [
            'customer_id'      => ['required', 'exists:customers,id'],
            'make'             => ['required', 'string', 'max:120'],
            'model'            => ['required', 'string', 'max:120'],
            'year'             => ['required', 'string', 'max:10'],
            'grade'            => ['required', 'string', 'max:60'],
            'budget'           => ['required', 'integer', 'min:1'],
            'requirement_date' => ['nullable', 'date'],
        ];
    }
}