@extends('layouts.app')

@section('title', 'Customers | All Customers')

@section('content')

@php $isPrivileged = auth()->user()->can('data.view_all'); @endphp

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
                    <h2 class="card-title">All Customers</h2>
                    @can('customers.create')
                        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                            <i class="fas fa-plus"></i> Add Customer
                        </button>
                    @endcan
                </div>
            </header>

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Country</th>
                                @if($isPrivileged)<th>Agent</th>@endif
                                <th>Type</th>
                                <th>Deposit</th>
                                <th>Profile</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customers as $c)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><a href="{{ route('customers.show', $c) }}"><strong>{{ $c->name }}</strong></a></td>
                                <td>{{ $c->phone }}<br><small class="text-muted">{{ $c->email }}</small></td>
                                <td>{{ $c->country ?? '—' }}</td>
                                @if($isPrivileged)<td>{{ $c->agent->name ?? '—' }}</td>@endif
                                <td>
                                    @if($c->is_new_customer)
                                        <span class="badge bg-info text-dark">New</span>
                                    @else
                                        <span class="badge bg-secondary">Existing</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$c->is_new_customer)
                                        <span class="text-muted">N/A</span>
                                    @elseif($c->security_deposit_paid)
                                        <span class="badge bg-success">Paid (¥{{ number_format($c->security_deposit) }})</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if($c->profile_completed_at)
                                        <span class="badge bg-success">Complete</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Incomplete</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-{{ $c->status==='active'?'success':'danger' }}">{{ $c->status }}</span></td>
                                <td class="text-nowrap">
                                    <a href="{{ route('vehicles.index', ['customer_id' => $c->id]) }}" class="text-secondary me-1" title="View Vehicles">
                                        <i class="fa fa-car"></i>
                                    </a>
                                    @can('customers.edit')
                                        <a href="#" class="text-primary me-1" title="Edit" onclick="editCustomer({{ $c->id }})">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endcan
                                    @if($c->is_new_customer && !$c->security_deposit_paid)
                                        <a href="#" class="text-success me-1" title="Pay Security Deposit" onclick="openDeposit({{ $c->id }}, '{{ $c->name }}')">
                                            <i class="fa fa-hand-holding-usd"></i>
                                        </a>
                                    @endif
                                    @if(!$c->profile_completed_at)
                                        @if($c->canCompleteProfile())
                                            <form action="{{ route('customers.complete', $c) }}" method="POST" style="display:inline;"
                                                onsubmit="return confirm('Mark this profile complete? This enables bidding.');">
                                                @csrf
                                                <button type="submit" class="btn btn-link p-0 text-info me-1" title="Complete Profile">
                                                    <i class="fa fa-check-circle"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                    @can('customers.delete')
                                        <form action="{{ route('customers.destroy', $c) }}" method="POST" style="display:inline;"
                                              onsubmit="return confirm('Delete this customer?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-link p-0 text-danger">
                                                <i class="fa fa-trash-alt"></i>
                                            </button>
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

        {{-- ================= ADD MODAL ================= --}}
        @can('customers.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('customers.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Add Customer</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required>
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
                                <label>Country</label>
                                <input type="text" class="form-control" name="country">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Customer Type <span class="text-danger">*</span></label>
                                <select class="form-control select2-js" name="is_new_customer" required>
                                    <option value="1" selected>New Customer</option>
                                    <option value="0">Existing Customer</option>
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Status <span class="text-danger">*</span></label>
                                <select class="form-control select2-js" name="status" required>
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            @if($isPrivileged)
                            <div class="col-lg-6 mb-2">
                                <label>Assign to Agent</label>
                                <select class="form-control select2-js" name="agent_id">
                                    <option value="">Assign to me</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-lg-12 mb-2">
                                <label>Address</label>
                                <textarea class="form-control" rows="2" name="address"></textarea>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Add Customer</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan

        {{-- ================= EDIT MODAL ================= --}}
        @can('customers.edit')
        <div id="editModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="editForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Edit Customer</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" id="edit_name" class="form-control" name="name" required>
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
                                <label>Country</label>
                                <input type="text" id="edit_country" class="form-control" name="country">
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Customer Type <span class="text-danger">*</span></label>
                                <select id="edit_is_new_customer" class="form-control select2-js" name="is_new_customer" required>
                                    <option value="1">New Customer</option>
                                    <option value="0">Existing Customer</option>
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Status <span class="text-danger">*</span></label>
                                <select id="edit_status" class="form-control select2-js" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            @if($isPrivileged)
                            <div class="col-lg-6 mb-2">
                                <label>Assigned Agent</label>
                                <select id="edit_agent_id" class="form-control select2-js" name="agent_id">
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-lg-12 mb-2">
                                <label>Address</label>
                                <textarea id="edit_address" class="form-control" rows="2" name="address"></textarea>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Update Customer</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan

        @include('customers._deposit_modal')
    </div>
</div>

<script>
function editCustomer(id) {
    fetch('/customers/' + id + '/edit')
        .then(res => res.json())
        .then(data => {
            $('#editForm').attr('action', '/customers/' + id);
            $('#edit_name').val(data.name);
            $('#edit_phone').val(data.phone);
            $('#edit_email').val(data.email);
            $('#edit_country').val(data.country);
            $('#edit_address').val(data.address);
            $('#edit_is_new_customer').val(data.is_new_customer ? '1' : '0').trigger('change');
            $('#edit_status').val(data.status).trigger('change');
            $('#edit_agent_id').val(data.agent_id).trigger('change');

            $.magnificPopup.open({ items: { src: '#editModal' }, type: 'inline' });
        })
        .catch(err => {
            console.error('Failed to load customer:', err);
            alert('Could not load customer data. Please try again.');
        });
}
</script>

@endsection