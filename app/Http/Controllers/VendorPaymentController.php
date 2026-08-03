<?php

namespace App\Http\Controllers;

use App\Models\{Vehicle, Vendor, VendorPayment};
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VendorPaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = VendorPayment::with('vendor', 'vehicle')
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->latest('paid_at')->get();

        $vendors = Vendor::orderBy('name')->get();

        $vehicles = Vehicle::whereNotNull('vendor_id')->whereNotNull('buying_price')
            ->with('vendor', 'customer')->get()
            ->map(function ($v) {
                $v->outstanding = $v->buying_price - $v->vendorPayments()->sum('amount');
                return $v;
            })
            ->filter(fn ($v) => $v->outstanding > 0)->values();

        return view('vendor_payments.index', compact('payments', 'vendors', 'vehicles'));
    }

    public function store(Request $request, LedgerService $ledger)
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'amount'     => ['required', 'integer', 'min:1'],
            'method'     => ['required', Rule::in(['cash', 'bank'])],
            'paid_at'    => ['required', 'date'],
            'reference'  => ['nullable', 'string', 'max:255'],
        ]);

        if (! $request->user()->isSuperAdmin() && ! \Carbon\Carbon::parse($data['paid_at'])->isToday()) {
            return back()->withErrors(['paid_at' => 'Only Super Admin may record a vendor payment with a date other than today.'])->withInput();
        }

        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        abort_unless($vehicle->vendor_id, 422, 'This vehicle has no vendor assigned.');

        $backdated = \Carbon\Carbon::parse($data['paid_at'])->lt(today());
        $account = $data['method'] === 'cash' ? LedgerService::CASH : LedgerService::BANK;

        DB::transaction(function () use ($data, $backdated, $request, $ledger, $vehicle, $account) {
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
        });

        return back()->with('success', 'Vendor payment recorded.');
    }

    public function edit(VendorPayment $vendorPayment)
    {
        return response()->json($vendorPayment);
    }

    public function update(Request $request, VendorPayment $vendorPayment, LedgerService $ledger)
    {
        $data = $request->validate([
            'amount'    => ['required', 'integer', 'min:1'],
            'method'    => ['required', Rule::in(['cash', 'bank'])],
            'paid_at'   => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $request->user()->isSuperAdmin() && ! \Carbon\Carbon::parse($data['paid_at'])->isToday()) {
            return back()->withErrors(['paid_at' => 'Only Super Admin may set a vendor payment date other than today.'])->withInput();
        }

        DB::transaction(function () use ($vendorPayment, $data, $ledger) {
            foreach ($vendorPayment->journalEntries as $entry) {
                $ledger->reverseEntry($entry, now()->toDateString(), "Correction to vendor payment #{$vendorPayment->id}");
            }
            $account = $data['method'] === 'cash' ? LedgerService::CASH : LedgerService::BANK;
            $vendorPayment->update($data + ['account_id' => $ledger->account($account)->id]);
            $ledger->vendorPayment($vendorPayment->fresh(), $account);
        });

        return back()->with('success', 'Vendor payment updated — original ledger entry reversed and reposted.');
    }

    public function destroy(VendorPayment $vendorPayment, LedgerService $ledger)
    {
        DB::transaction(function () use ($vendorPayment, $ledger) {
            foreach ($vendorPayment->journalEntries as $entry) {
                $ledger->reverseEntry($entry, now()->toDateString(), "Reversal of deleted vendor payment #{$vendorPayment->id}");
            }
            $vendorPayment->delete();
        });

        return back()->with('success', 'Vendor payment deleted and ledger entry reversed.');
    }

    public function outstanding(Vehicle $vehicle): int
    {
        return max($vehicle->buying_price - $vehicle->vendorPayments()->sum('amount'), 0);
    }
}