<?php

namespace App\Http\Controllers;

use App\Models\{Vehicle, Vendor, VendorPayment};
use App\Rules\MoneyAccount;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorPaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = VendorPayment::with('vendor', 'vehicle', 'account')
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->latest('paid_at')->get();

        $vendors = Vendor::orderBy('name')->get();

        $vehicles = Vehicle::whereNotNull('vendor_id')->whereNotNull('buying_price')
            ->with('vendor', 'customer', 'costing')
            ->get()
            ->map(function ($v) {
                // Outstanding = TOTAL COSTING FOR COMPANY, not just buying_price.
                $totalOwed = $v->costing?->total_costing ?? $v->buying_price;
                $v->outstanding = $totalOwed - $v->vendorPayments()->sum('amount');
                return $v;
            })
            ->filter(fn ($v) => $v->outstanding > 0)->values();

        return view('vendor_payments.index', compact('payments', 'vendors', 'vehicles'));
    }

    public function store(Request $request, LedgerService $ledger)
    {
        $vehicle = Vehicle::findOrFail($request->vehicle_id);
        abort_unless($vehicle->vendor_id, 422, 'This vehicle has no vendor assigned.');

        $outstanding = ($vehicle->costing?->total_costing ?? $vehicle->buying_price ?? 0) - $vehicle->vendorPayments()->sum('amount');

        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'amount'     => ['required', 'integer', 'min:1', "max:{$outstanding}"],
            'account_id' => ['required', new MoneyAccount],
            'paid_at'    => ['required', 'date'],
            'reference'  => ['nullable', 'string', 'max:255'],
        ], [
            'amount.max' => "Amount cannot exceed the outstanding balance of ¥" . number_format($outstanding) . ".",
        ]);

        if (! $request->user()->canBackdate() && ! \Carbon\Carbon::parse($data['paid_at'])->isToday()) {
            return back()->withErrors(['paid_at' => 'You do not have permission to record a vendor payment with a date other than today.'])->withInput();
        }

        $backdated = \Carbon\Carbon::parse($data['paid_at'])->lt(today());
        $account = $ledger->moneyAccount($data['account_id']);

        DB::transaction(function () use ($data, $backdated, $request, $ledger, $vehicle, $account) {
            // Make sure the vendor's payable already reflects the full costing before it is paid down.
            $ledger->syncVehicleCost($vehicle);

            $payment = VendorPayment::create([
                'vendor_id'    => $vehicle->vendor_id,
                'vehicle_id'   => $vehicle->id,
                'amount'       => $data['amount'],
                'account_id'   => $account->id,
                'paid_at'      => $data['paid_at'],
                'reference'    => $data['reference'] ?? null,
                'is_backdated' => $backdated,
                'recorded_by'  => $request->user()->id,
            ]);
            $ledger->vendorPayment($payment);
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
            'amount'     => ['required', 'integer', 'min:1'],
            'account_id' => ['required', new MoneyAccount],
            'paid_at'    => ['required', 'date'],
            'reference'  => ['nullable', 'string', 'max:255'],
        ]);

        if (! $request->user()->canBackdate() && ! \Carbon\Carbon::parse($data['paid_at'])->isToday()) {
            return back()->withErrors(['paid_at' => 'You do not have permission to set a vendor payment date other than today.'])->withInput();
        }

        // Editing a vendor payment always reverses and reposts its ledger entry.
        abort_unless($request->user()->can('vendor_payments.reverse'), 403, 'You do not have permission to reverse/void a vendor payment.');

        $account = $ledger->moneyAccount($data['account_id']);

        DB::transaction(function () use ($vendorPayment, $data, $ledger, $account) {
            foreach ($vendorPayment->journalEntries as $entry) {
                $ledger->reverseEntry($entry, now()->toDateString(), "Correction to vendor payment #{$vendorPayment->id}");
            }
            $vendorPayment->update(['amount' => $data['amount'], 'paid_at' => $data['paid_at'], 'reference' => $data['reference'] ?? null, 'account_id' => $account->id]);
            $ledger->vendorPayment($vendorPayment->fresh());
        });

        return back()->with('success', 'Vendor payment updated — original ledger entry reversed and reposted.');
    }

    public function destroy(VendorPayment $vendorPayment, LedgerService $ledger)
    {
        // Deleting a posted vendor payment reverses its ledger entry — same
        // permission as an explicit reversal, on top of the route's vendor_payments.delete.
        abort_unless(auth()->user()->can('vendor_payments.reverse'), 403, 'You do not have permission to reverse/void a vendor payment.');

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
        return max(($vehicle->costing?->total_costing ?? $vehicle->buying_price) - $vehicle->vendorPayments()->sum('amount'), 0);
    }
}
