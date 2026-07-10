<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Vehicle $vehicle)
    {
        $vehicle->load('documents', 'invoice');
        return view('documents.index', compact('vehicle'));
    }

    public function store(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'type'                => ['nullable', 'string', 'max:60'],
            'title'                => ['required', 'string', 'max:255'],
            'file'                 => ['required', 'file', 'max:10240'],
            'is_final_clearance'   => ['boolean'],
        ]);

        // The final clearance document can be UPLOADED any time, but stays hidden
        // from the customer until the invoice is 100% paid — enforced in release().
        $vehicle->documents()->create([
            'type'                 => $data['type'] ?? null,
            'title'                => $data['title'],
            'file_path'            => $request->file('file')->store('documents'),
            'is_final_clearance'   => $request->boolean('is_final_clearance'),
            'visible_to_customer'  => ! $request->boolean('is_final_clearance'), // regular docs visible immediately
            'uploaded_by'          => $request->user()->id,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    /** Release the final clearance document — ONLY after 100% of the invoice is cleared. */
    public function release(Vehicle $vehicle)
    {
        $invoice = $vehicle->invoice;
        abort_unless($invoice && $invoice->isFullyPaid(), 403, 'Cannot release: invoice is not 100% paid yet.');

        $released = $vehicle->documents()->where('is_final_clearance', true)->update(['visible_to_customer' => true]);
        abort_if($released === 0, 404, 'No final clearance document uploaded yet.');

        return back()->with('success', 'Final clearance document released to customer.');
    }

    public function destroy(\App\Models\Document $document)
    {
        $document->delete();
        return back()->with('success', 'Document removed.');
    }
}