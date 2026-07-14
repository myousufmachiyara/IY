@extends('layouts.app')

@section('title', 'Documents | ' . $vehicle->label())

@section('content')

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Documents — {{ $vehicle->label() }}</h2>
                <a href="{{ route('vehicles.show', $vehicle) }}" class="btn btn-sm btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Vehicle
                </a>
            </header>

            @include('vehicles._tabs', ['vehicle' => $vehicle, 'active' => 'documents'])

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="text-muted mb-0">{{ $vehicle->documents->count() }} of the usual 7–8 official documents uploaded.</p>
                    @can('documents.create')
                        <button type="button" class="modal-with-form btn btn-primary btn-sm" href="#uploadModal">
                            <i class="fas fa-upload"></i> Upload Document
                        </button>
                    @endcan
                </div>

                @php $finalDoc = $vehicle->documents->firstWhere('is_final_clearance', true); @endphp

                @if($finalDoc)
                <div class="card border-{{ $finalDoc->visible_to_customer ? 'success' : 'warning' }} mb-3">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fa fa-file-shield"></i> Final Port Clearance Document
                            @if($finalDoc->visible_to_customer)
                                <span class="badge bg-success ms-1">Released to Customer</span>
                            @else
                                <span class="badge bg-warning text-dark ms-1">Locked</span>
                            @endif
                        </h6>
                        <p class="mb-2">{{ $finalDoc->title }}</p>
                        @if(!$finalDoc->visible_to_customer)
                            @if($vehicle->invoice?->isFullyPaid())
                                @can('documents.edit')
                                    <form action="{{ route('documents.release', $vehicle) }}" method="POST" onsubmit="return confirm('Release the final clearance document to the customer?');">
                                        @csrf
                                        <button class="btn btn-sm btn-success"><i class="fa fa-unlock"></i> Release to Customer</button>
                                    </form>
                                @endcan
                            @else
                                <p class="small text-danger mb-0">
                                    <i class="fa fa-lock"></i> Locked until invoice is 100% paid.
                                    Current balance: ¥{{ number_format($vehicle->invoice?->balance() ?? 0) }}.
                                </p>
                            @endif
                        @endif
                    </div>
                </div>
                @endif

                <h6 class="text-muted text-uppercase small mb-2">All Documents</h6>
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr><th>Title</th><th>Type</th><th>Visible to Customer</th><th>Uploaded</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($vehicle->documents as $d)
                            <tr>
                                <td>{{ $d->title }} @if($d->is_final_clearance)<span class="badge bg-secondary ms-1">Final Clearance</span>@endif</td>
                                <td>{{ $d->type ?? '—' }}</td>
                                <td>
                                    @if($d->visible_to_customer)
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td>{{ $d->created_at->format('d-m-Y') }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ \Storage::url($d->file_path) }}" target="_blank" class="text-secondary me-1" title="View">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    @can('documents.edit')
                                        <a href="#" class="text-primary me-1" title="Edit" onclick="editDocument({{ $d->id }})">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endcan
                                    @can('documents.delete')
                                        <form action="{{ route('documents.destroy', $d) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this document?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-link p-0 text-danger"><i class="fa fa-trash-alt"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No documents uploaded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- ================= UPLOAD MODAL ================= --}}
        @can('documents.create')
        <div id="uploadModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('documents.store', $vehicle) }}" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Upload Document</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2">
                                <label>Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" placeholder="e.g. Export Certificate" required>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Type</label>
                                <input type="text" class="form-control" name="type" placeholder="e.g. bill_of_lading, inspection, export_cert">
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>File <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="file" required>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_final_clearance" id="is_final_clearance" value="1">
                                    <label class="form-check-label" for="is_final_clearance">
                                        This is the FINAL PORT CLEARANCE document
                                        <small class="text-muted d-block">Stays hidden from the customer until the invoice is 100% paid, regardless of when it's uploaded.</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Upload</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan

        {{-- ================= EDIT MODAL ================= --}}
        @can('documents.edit')
        <div id="editDocModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="editDocForm" action="" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Edit Document</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2">
                                <label>Title <span class="text-danger">*</span></label>
                                <input type="text" id="edit_doc_title" class="form-control" name="title" required>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Type</label>
                                <input type="text" id="edit_doc_type" class="form-control" name="type">
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Replace File <small class="text-muted">(optional — leave blank to keep current file)</small></label>
                                <input type="file" class="form-control" name="file">
                            </div>
                            <div class="col-lg-12 mb-2">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_doc_is_final" name="is_final_clearance" value="1">
                                    <label class="form-check-label" for="edit_doc_is_final">
                                        This is the FINAL PORT CLEARANCE document
                                        <small class="text-muted d-block">Toggling this re-locks visibility — you'll need to Release it again from the list above.</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Update Document</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan
    </div>
</div>

<script>
function editDocument(id) {
    fetch('/documents/' + id + '/edit')
        .then(res => res.json())
        .then(data => {
            $('#editDocForm').attr('action', '/documents/' + id);
            $('#edit_doc_title').val(data.title);
            $('#edit_doc_type').val(data.type);
            $('#edit_doc_is_final').prop('checked', !!data.is_final_clearance);

            $.magnificPopup.open({ items: { src: '#editDocModal' }, type: 'inline' });
        })
        .catch(err => {
            console.error('Failed to load document:', err);
            alert('Could not load document data. Please try again.');
        });
}
</script>

@endsection