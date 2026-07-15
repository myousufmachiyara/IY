<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::latest()->get()->map(function ($v) {
            $v->vehicles_supplied = $v->vehicles()->count();
            $v->total_payable     = $v->totalPayable();
            $v->total_paid        = $v->totalPaid();
            $v->balance           = $v->balance();
            return $v;
        });

        return view('vendors.index', compact('vendors'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['created_by'] = $request->user()->id;

        Vendor::create($data);

        return back()->with('success', 'Vendor created.');
    }

    public function edit(Vendor $vendor)
    {
        return response()->json($vendor);
    }

    public function update(Request $request, Vendor $vendor)
    {
        $vendor->update($request->validate($this->rules()));
        return back()->with('success', 'Vendor updated.');
    }

    public function destroy(Vendor $vendor)
    {
        abort_if($vendor->vehicles()->exists(), 422, 'Cannot delete a vendor with vehicle history — their records must be preserved for accounting purposes.');
        $vendor->delete();

        return back()->with('success', 'Vendor removed.');
    }

    private function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:255'],
            'contact_person'     => ['nullable', 'string', 'max:255'],
            'phone'              => ['nullable', 'string', 'max:40'],
            'email'              => ['nullable', 'email', 'max:255'],
            'location'           => ['nullable', 'string', 'max:255'],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'address'            => ['nullable', 'string'],
            'notes'              => ['nullable', 'string'],
            'status'             => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}