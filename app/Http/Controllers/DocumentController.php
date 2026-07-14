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
            'title'               => ['required', 'string', 'max:255'],
            'file'                => ['required', 'file', 'max:10240'],
            'is_final_clearance'  => ['boolean'],
        ]);

        $vehicle->documents()->create([
            'type'                => $data['type'] ?? null,
            'title'               => $data['title'],
            'file_path'           => $request->file('file')->store('documents', 'public'),
            'is_final_clearance'  => $request->boolean('is_final_clearance'),
            'visible_to_customer' => ! $request->boolean('is_final_clearance'),
            'uploaded_by'         => $request->user()->id,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    /** Modal edit form fetches this as JSON. */
    public function edit(Document $document)
    {
        return response()->json($document);
    }

    public function update(Request $request, Document $document)
    {
        $data = $request->validate([
            'type'               => ['nullable', 'string', 'max:60'],
            'title'              => ['required', 'string', 'max:255'],
            'file'               => ['nullable', 'file', 'max:10240'],
            'is_final_clearance' => ['boolean'],
        ]);

        $wasFinal = $document->is_final_clearance;
        $isFinal  = $request->boolean('is_final_clearance');

        $document->title = $data['title'];
        $document->type  = $data['type'] ?? null;
        $document->is_final_clearance = $isFinal;

        // Flipping final-clearance status re-locks visibility, so it always has
        // to go through the explicit Release action again rather than staying
        // exposed (or hidden) based on the previous state.
        if ($isFinal !== $wasFinal) {
            $document->visible_to_customer = ! $isFinal;
        }

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($document->file_path);
            $document->file_path = $request->file('file')->store('documents', 'public');
        }

        $document->save();

        return back()->with('success', 'Document updated.');
    }

    public function release(Vehicle $vehicle)
    {
        $invoice = $vehicle->invoice;
        abort_unless($invoice && $invoice->isFullyPaid(), 403, 'Cannot release: invoice is not 100% paid yet.');

        $released = $vehicle->documents()->where('is_final_clearance', true)->update(['visible_to_customer' => true]);
        abort_if($released === 0, 404, 'No final clearance document uploaded yet.');

        return back()->with('success', 'Final clearance document released to customer.');
    }

    public function destroy(Document $document)
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }
}   