@extends('layouts.app')

@section('title', 'Vendors | All Vendors')

@section('content')

<div class="row">
    <div class="col">
        <section class="card">

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <header class="card-header">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <h2 class="card-title">Vendors</h2>
                    @can('vendors.create')
                        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                            <i class="fas fa-plus"></i> Add Vendor
                        </button>
                    @endcan
                </div>
            </header>

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact Person</th>
                                <th>Contact</th>
                                <th>Commission %</th>
                                <th>Location</th>
                                <th>Vehicles Supplied</th>
                                <th>Payable</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vendors as $v)
                            <tr>
                                <td><strong>{{ $v->name }}</strong></td>
                                <td>{{ $v->contact_person ?? '—' }}</td>
                                <td>{{ $v->phone }}<br><small class="text-muted">{{ $v->email }}</small></td>
                                <td>{{ $v->commission_percent }}%</td>
                                <td>{{ $v->location ?? '—' }}</td>
                                <td>{{ $v->vehicles_supplied }}</td>
                                <td>¥{{ number_format($v->total_payable) }}</td>
                                <td class="text-success">¥{{ number_format($v->total_paid) }}</td>
                                <td class="fw-bold {{ $v->balance > 0 ? 'text-danger' : 'text-success' }}">¥{{ number_format($v->balance) }}</td>
                                <td><span class="badge bg-{{ $v->status==='active'?'success':'danger' }}">{{ $v->status }}</span></td>
                                <td class="text-nowrap">
                                    @if($v->vehicles_supplied > 0)
                                        <a href="{{ route('vendor-payments.index', ['vendor_id' => $v->id]) }}" class="text-secondary me-1" title="View Payments">
                                            <i class="fa fa-money-bill-wave"></i>
                                        </a>
                                    @endif
                                    @can('vendors.edit')
                                        <a href="#" class="text-primary me-1" title="Edit" onclick="editVendor({{ $v->id }})">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endcan
                                    @can('vendors.delete')
                                        <form action="{{ route('vendors.destroy', $v) }}" method="POST" style="display:inline;"
                                              onsubmit="return confirm('Delete this vendor?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-link p-0 text-danger">
                                                <i class="fa fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="11" class="text-center text-muted py-4">No vendors found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- ================= ADD MODAL ================= --}}
        @can('vendors.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('vendors.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Add Vendor</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Vendor / Company Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Contact Person</label>
                                <input type="text" class="form-control" name="contact_person">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Phone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Commission % <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" name="commission_percent" value="7" required>
                                <small class="text-muted">Editable per-vehicle later in the Costing screen.</small>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Yard / Auction Location</label>
                                <input type="text" class="form-control" name="location" placeholder="e.g. USS Tokyo, Japan">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Status <span class="text-danger">*</span></label>
                                <select class="form-control select2-js" name="status" required>
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Address</label>
                                <textarea class="form-control" rows="2" name="address"></textarea>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Notes</label>
                                <textarea class="form-control" rows="2" name="notes"></textarea>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Add Vendor</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan

        {{-- ================= EDIT MODAL ================= --}}
        @can('vendors.edit')
        <div id="editModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="editForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Edit Vendor</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Vendor / Company Name <span class="text-danger">*</span></label>
                                <input type="text" id="edit_name" class="form-control" name="name" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Contact Person</label>
                                <input type="text" id="edit_contact_person" class="form-control" name="contact_person">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Phone</label>
                                <input type="text" id="edit_phone" class="form-control" name="phone">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Email</label>
                                <input type="email" id="edit_email" class="form-control" name="email">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Commission % <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" id="edit_commission_percent" class="form-control" name="commission_percent" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Yard / Auction Location</label>
                                <input type="text" id="edit_location" class="form-control" name="location">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Status <span class="text-danger">*</span></label>
                                <select id="edit_status" class="form-control select2-js" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Address</label>
                                <textarea id="edit_address" class="form-control" rows="2" name="address"></textarea>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Notes</label>
                                <textarea id="edit_notes" class="form-control" rows="2" name="notes"></textarea>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Update Vendor</button>
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
function editVendor(id) {
    fetch('/vendors/' + id + '/edit')
        .then(res => res.json())
        .then(data => {
            $('#editForm').attr('action', '/vendors/' + id);
            $('#edit_name').val(data.name);
            $('#edit_contact_person').val(data.contact_person);
            $('#edit_phone').val(data.phone);
            $('#edit_email').val(data.email);
            $('#edit_commission_percent').val(data.commission_percent);
            $('#edit_location').val(data.location);
            $('#edit_address').val(data.address);
            $('#edit_notes').val(data.notes);
            $('#edit_status').val(data.status).trigger('change');

            $.magnificPopup.open({ items: { src: '#editModal' }, type: 'inline' });
        })
        .catch(err => {
            console.error('Failed to load vendor:', err);
            alert('Could not load vendor data. Please try again.');
        });
}
</script>

@endsection