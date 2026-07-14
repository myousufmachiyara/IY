@extends('layouts.app')

@section('title', 'Vehicles | Vehicle Requirements')

@section('content')

@php
        $isPrivileged = auth()->user()->can('data.view_all');    
        $statusColors = [
        'requirement' => 'secondary', 'bidding' => 'info', 'won' => 'success', 'lost' => 'danger',
        'invoiced' => 'primary', 'dispatched' => 'warning', 'arrived' => 'warning', 'delivered' => 'success',
    ];
@endphp

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
                    <h2 class="card-title">Vehicle Requirements</h2>
                    @can('vehicles.create')
                        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                            <i class="fas fa-plus"></i> Add Vehicle Requirement
                        </button>
                    @endcan
                </div>
            </header>

            <div class="card-body">
                @if(request('customer_id'))
                    <a href="{{ route('vehicles.index') }}" class="btn btn-sm btn-light mb-3"><i class="fa fa-times"></i> Clear customer filter</a>
                @endif

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Vehicle</th>
                                <th>Customer</th>
                                @if($isPrivileged)<th>Agent</th>@endif
                                <th>Budget</th>
                                <th>Buying Price</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vehicles as $v)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <a href="{{ route('vehicles.show', $v) }}"><strong>{{ $v->label() }}</strong></a>
                                    @if($v->grade)<br><small class="text-muted">{{ $v->grade }}</small>@endif
                                </td>
                                <td><a href="{{ route('customers.show', $v->customer) }}">{{ $v->customer->name }}</a></td>
                                @if($isPrivileged)<td>{{ $v->agent->name ?? '—' }}</td>@endif
                                <td>¥{{ number_format($v->budget) }}</td>
                                <td>{{ $v->buying_price ? '¥'.number_format($v->buying_price) : '—' }}</td>
                                <td><span class="badge bg-{{ $statusColors[$v->status] ?? 'secondary' }} text-uppercase">{{ $v->status }}</span></td>
                                <td class="text-nowrap">
                                    <a href="{{ route('vehicles.show', $v) }}" class="text-secondary me-1" title="View">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    @can('vehicles.edit')
                                        @if(!$v->isWon())
                                            <a href="#" class="text-primary me-1" title="Edit" onclick="editVehicle({{ $v->id }})">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                    @endcan
                                    @can('vehicles.delete')
                                        @if(!$v->isWon())
                                            <form action="{{ route('vehicles.destroy', $v) }}" method="POST" style="display:inline;"
                                                  onsubmit="return confirm('Delete this vehicle requirement?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-link p-0 text-danger">
                                                    <i class="fa fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- ================= ADD MODAL ================= --}}
        @can('vehicles.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('vehicles.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Add Vehicle Requirement</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2">
                                <label>Customer <span class="text-danger">*</span></label>
                                <select data-plugin-selecttwo class="form-control select2-js" name="customer_id" required>
                                    <option value="" disabled {{ request('customer_id') ? '' : 'selected' }}>Select Customer</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Make</label>
                                <input type="text" class="form-control" name="make">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Model</label>
                                <input type="text" class="form-control" name="model">
                            </div>
                            <div class="col-lg-4 mb-2">
                                <label>Year</label>
                                <input type="text" class="form-control" name="year">
                            </div>
                            <div class="col-lg-4 mb-2">
                                <label>Grade</label>
                                <input type="text" class="form-control" name="grade">
                            </div>
                            <div class="col-lg-4 mb-2">
                                <label>Chassis No.</label>
                                <input type="text" class="form-control" name="chassis_no">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Budget (¥) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="budget" min="0" required>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Add Vehicle</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan

        {{-- ================= EDIT MODAL ================= --}}
        @can('vehicles.edit')
        <div id="editModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="editForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Edit Vehicle Requirement</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2">
                                <label>Customer <span class="text-danger">*</span></label>
                                <select id="edit_customer_id" class="form-control select2-js" name="customer_id" required>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Make</label>
                                <input type="text" id="edit_make" class="form-control" name="make">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Model</label>
                                <input type="text" id="edit_model" class="form-control" name="model">
                            </div>
                            <div class="col-lg-4 mb-2">
                                <label>Year</label>
                                <input type="text" id="edit_year" class="form-control" name="year">
                            </div>
                            <div class="col-lg-4 mb-2">
                                <label>Grade</label>
                                <input type="text" id="edit_grade" class="form-control" name="grade">
                            </div>
                            <div class="col-lg-4 mb-2">
                                <label>Chassis No.</label>
                                <input type="text" id="edit_chassis_no" class="form-control" name="chassis_no">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Budget (¥) <span class="text-danger">*</span></label>
                                <input type="number" id="edit_budget" class="form-control" name="budget" min="0" required>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Update Vehicle</button>
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
function editVehicle(id) {
    fetch('/vehicles/' + id + '/edit')
        .then(res => res.json())
        .then(data => {
            $('#editForm').attr('action', '/vehicles/' + id);
            $('#edit_make').val(data.make);
            $('#edit_model').val(data.model);
            $('#edit_year').val(data.year);
            $('#edit_grade').val(data.grade);
            $('#edit_chassis_no').val(data.chassis_no);
            $('#edit_budget').val(data.budget);
            $('#edit_customer_id').val(data.customer_id).trigger('change');

            $.magnificPopup.open({ items: { src: '#editModal' }, type: 'inline' });
        })
        .catch(err => {
            console.error('Failed to load vehicle:', err);
            alert('Could not load vehicle data. Please try again.');
        });
}
</script>

@endsection