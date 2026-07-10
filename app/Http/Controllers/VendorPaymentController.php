<?php

namespace App\Http\Controllers;

use App\Models\{Vehicle, VendorPayment};
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorPaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = VendorPayment::with('vendor', 'vehicle')
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->latest()->paginate(20);

        return view('vendor_payments.index', compact('payments'));
    }

    public function store(Request $request, LedgerService $ledger)
    {
        $data = $request->validate([
            'vehicle_id'   => ['required', 'exists:vehicles,id'],
            'amount'       => ['required', 'integer', 'min:1'],
            'method'       => ['required', Rule::in(['cash', 'bank'])],
            'paid_at'      => ['required', 'date'],
            'reference'    => ['nullable', 'string', 'max:255'],
            'is_backdated' => ['boolean'],
        ]);

        $backdated = $request->boolean('is_backdated');
        if ($backdated) {
            abort_unless($request->user()->canBackdate(), 403);
        }

        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        $account = $data['method'] === 'cash' ? LedgerService::CASH : LedgerService::BANK;

        $payment = VendorPayment::create([
            'vendor_id'    => $vehicle->vendor_id,
            'vehicle_id'   => $vehicle->id,
            'amount'       => $data['amount'],
            'account_id'   => $ledger->account($account)->id,
            'paid_at'      => $data['paid_at'],
            'reference'    => $data['reference'] ?? null,
            'is_backdated' => $backdated,
            'recorded_by'  => $request->user()->id,
        ]);

        $ledger->vendorPayment($payment, $account);

        return back()->with('success', 'Vendor payment recorded.');
    }

    /** Outstanding payable for one vehicle (buying price minus what's already been paid to the vendor). */
    public function outstanding(Vehicle $vehicle): int
    {
        return max($vehicle->buying_price - $vehicle->vendorPayments()->sum('amount'), 0);
    }
}