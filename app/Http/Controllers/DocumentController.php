<?php

namespace App\Http\Controllers;

use App\Models\{Document, Vehicle};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'type_other'          => ['nullable', 'string', 'max:60'],
            'title'               => ['required', 'string', 'max:255'],
            'file'                => ['required', 'file', 'max:10240'],
            'is_final_clearance'  => ['boolean'],
        ]);

        $vehicle->documents()->create([
            'type'                => $data['type'] === 'other' ? $data['type_other'] : ($data['type'] ?? null),
            'title'               => $data['title'],
            'file_path'           => $request->file('file')->store('documents', 'public'),
            'is_final_clearance'  => $request->boolean('is_final_clearance'),
            'visible_to_customer' => ! $request->boolean('is_final_clearance'),
            'uploaded_by'         => $request->user()->id,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function update(Request $request, Document $document)
    {
        $data = $request->validate([
            'type'               => ['nullable', 'string', 'max:60'],
            'type_other'         => ['nullable', 'string', 'max:60'],
            'title'              => ['required', 'string', 'max:255'],
            'file'               => ['nullable', 'file', 'max:10240'],
            'is_final_clearance' => ['boolean'],
        ]);

        $wasFinal = $document->is_final_clearance;
        $isFinal  = $request->boolean('is_final_clearance');

        $document->title = $data['title'];
        $document->type  = $data['type'] === 'other' ? $data['type_other'] : ($data['type'] ?? null);
        $document->is_final_clearance = $isFinal;

        if ($isFinal !== $wasFinal) {
            $document->visible_to_customer = ! $isFinal;
        }

        if ($request->hasFile('file')) {
            \Storage::disk('public')->delete($document->file_path);
            $document->file_path = $request->file('file')->store('documents', 'public');
        }

        $document->save();

        return back()->with('success', 'Document updated.');
    }

    /** Modal edit form fetches this as JSON. */
    public function edit(Document $document)
    {
        return response()->json($document);
    }

    public function release(Vehicle $vehicle)
    {
        $invoice = $vehicle->invoice;
        $bypass = auth()->user()->isSuperAdmin();
        abort_unless($bypass || ($invoice && $invoice->isFullyPaid()), 403, 'Cannot release: invoice is not 100% paid yet.');

        $released = $vehicle->documents()->where('is_final_clearance', true)->update(['visible_to_customer' => true]);
        abort_if($released === 0, 404, 'No final clearance document uploaded yet.');

        return back()->with('success', $bypass && !$invoice?->isFullyPaid() ? 'Released by Super Admin override (invoice not fully paid).' : 'Final clearance document released to customer.');
    }

    public function destroy(Document $document)
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    /** Super Admin can re-lock a final clearance document after it was released. */
    public function undoRelease(Vehicle $vehicle)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403, 'Only Super Admin may undo a document release.');

        $updated = $vehicle->documents()->where('is_final_clearance', true)->update(['visible_to_customer' => false]);
        abort_if($updated === 0, 404, 'No released final clearance document found.');

        return back()->with('success', 'Final clearance document re-locked.');
    }
}   