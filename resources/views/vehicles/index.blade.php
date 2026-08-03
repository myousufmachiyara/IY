@extends('layouts.app')

@section('title', 'Vehicles | Vehicle Requirements')

@section('content')

@php $isPrivileged = auth()->user()->can('data.view_all'); @endphp

<div class="row">
    <div class="col">
        <section class="card">
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

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
                <form method="GET" action="{{ route('vehicles.index') }}" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <select name="customer_id" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Customers</option>
                            @foreach($customers as $c)<option value="{{ $c->id }}" @selected(request('customer_id')==$c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><input type="text" name="make" class="form-control" placeholder="Make" value="{{ request('make') }}"></div>
                    <div class="col-md-2"><input type="text" name="model" class="form-control" placeholder="Model" value="{{ request('model') }}"></div>
                    <div class="col-md-2"><input type="date" name="from" class="form-control" value="{{ request('from') }}"></div>
                    <div class="col-md-2"><input type="date" name="to" class="form-control" value="{{ request('to') }}"></div>
                    <div class="col-md-1"><button class="btn btn-outline-secondary w-100">Filter</button></div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>S.No</th><th>Vehicle</th><th>Customer</th>
                                @if($isPrivileged)<th>Agent</th>@endif
                                <th>Budget</th><th>Created</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vehicles as $v)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><a href="{{ route('vehicles.show', $v) }}"><strong>{{ $v->label() }}</strong></a>@if($v->grade)<br><small class="text-muted">{{ $v->grade }}</small>@endif</td>
                                <td><a href="{{ route('customers.show', $v->customer) }}">{{ $v->customer->name }}</a></td>
                                @if($isPrivileged)<td>{{ $v->agent->name ?? '—' }}</td>@endif
                                <td>¥{{ number_format($v->budget) }}</td>
                                <td>{{ $v->created_at->format('d-m-Y') }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('vehicles.show', $v) }}" class="text-secondary me-1"><i class="fa fa-eye"></i></a>
                                    @can('vehicles.edit')<a href="#" class="text-primary me-1" onclick="editVehicle({{ $v->id }})"><i class="fa fa-edit"></i></a>@endcan
                                    @can('vehicles.delete')
                                        <form action="{{ route('vehicles.destroy', $v) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this vehicle requirement?');">
                                            @csrf @method('DELETE')<button type="submit" class="btn btn-link p-0 text-danger"><i class="fa fa-trash-alt"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

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
                                <select class="form-control select2-js" name="customer_id" required>
                                    <option value="" disabled @selected(!request('customer_id')) selected>Select Customer</option>
                                    @foreach($customers as $c)<option value="{{ $c->id }}" @selected(request('customer_id')==$c->id)>{{ $c->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2"><label>Make <span class="text-danger">*</span></label><input type="text" class="form-control" name="make" required></div>
                            <div class="col-lg-6 mb-2"><label>Model <span class="text-danger">*</span></label><input type="text" class="form-control" name="model" required></div>
                            <div class="col-lg-6 mb-2"><label>Year <span class="text-danger">*</span></label><input type="text" class="form-control" name="year" required></div>
                            <div class="col-lg-6 mb-2"><label>Grade <span class="text-danger">*</span></label><input type="text" class="form-control" name="grade" required></div>
                            <div class="col-lg-6 mb-2"><label>Budget (¥) <span class="text-danger">*</span></label><input type="number" class="form-control" name="budget" min="1" required></div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end"><button type="submit" class="btn btn-primary">Add Vehicle</button><button type="button" class="btn btn-default modal-dismiss">Cancel</button></div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan

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
                                    @foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2"><label>Make <span class="text-danger">*</span></label><input type="text" id="edit_make" class="form-control" name="make" required></div>
                            <div class="col-lg-6 mb-2"><label>Model <span class="text-danger">*</span></label><input type="text" id="edit_model" class="form-control" name="model" required></div>
                            <div class="col-lg-6 mb-2"><label>Year <span class="text-danger">*</span></label><input type="text" id="edit_year" class="form-control" name="year" required></div>
                            <div class="col-lg-6 mb-2"><label>Grade <span class="text-danger">*</span></label><input type="text" id="edit_grade" class="form-control" name="grade" required></div>
                            <div class="col-lg-6 mb-2"><label>Budget (¥) <span class="text-danger">*</span></label><input type="number" id="edit_budget" class="form-control" name="budget" min="1" required></div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end"><button type="submit" class="btn btn-primary">Update Vehicle</button><button type="button" class="btn btn-default modal-dismiss">Cancel</button></div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan
    </div>
</div>

<script>
function editVehicle(id) {
    fetch('/vehicles/' + id + '/edit').then(r => r.json()).then(data => {
        $('#editForm').attr('action', '/vehicles/' + id);
        $('#edit_make').val(data.make); $('#edit_model').val(data.model);
        $('#edit_year').val(data.year); $('#edit_grade').val(data.grade);
        $('#edit_budget').val(data.budget);
        $('#edit_customer_id').val(data.customer_id).trigger('change');
        $.magnificPopup.open({ items: { src: '#editModal' }, type: 'inline' });
    });
}
</script>
@endsection