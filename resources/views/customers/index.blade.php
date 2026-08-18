@extends('layouts.app')

@section('title', 'Customers | All Customers')

@section('content')

@php
    $isPrivileged = auth()->user()->can('data.view_all');
    $canApprove = auth()->user()->canBackdate();
    $depositBadges = [
        'none'     => ['secondary', 'Not Submitted'],
        'pending'  => ['warning text-dark', 'Pending Approval'],
        'approved' => ['success', 'Approved'],
        'rejected' => ['danger', 'Rejected'],
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
                    <h2 class="card-title">All Customers</h2>
                    @can('customers.create')
                        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                            <i class="fas fa-plus"></i> Add Customer
                        </button>
                    @endcan
                </div>
            </header>

            <div class="card-body">
                <form method="GET" action="{{ route('customers.index') }}" class="row g-2 mb-3">
                    <div class="col-md-2">
                        <select name="status" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="active" @selected(request('status')==='active')>Active</option>
                            <option value="inactive" @selected(request('status')==='inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="deposit_status" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Deposit States</option>
                            @foreach(['none'=>'Not Submitted','pending'=>'Pending Approval','approved'=>'Approved','rejected'=>'Rejected'] as $k=>$v)
                                <option value="{{ $k }}" @selected(request('deposit_status')===$k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($isPrivileged)
                    <div class="col-md-3">
                        <select name="agent_id" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Agents</option>
                            @foreach($agents as $a)<option value="{{ $a->id }}" @selected(request('agent_id')==$a->id)>{{ $a->name }}</option>@endforeach
                        </select>
                    </div>
                    @endif
                    <div class="col-md-2"><input type="text" name="country" class="form-control" placeholder="Country" value="{{ request('country') }}"></div>
                    <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
                </form>
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Name</th>
                                <th>Consignee</th>
                                <th>Contact</th>
                                <th>Country</th>
                                @if($isPrivileged)<th>Agent</th>@endif
                                <th>Deposit</th>
                                <th>Profile</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customers as $c)
                            @php [$badgeClass, $badgeLabel] = $depositBadges[$c->security_deposit_status] ?? $depositBadges['none']; @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><a href="{{ route('customers.show', $c) }}"><strong>{{ $c->name }}</strong></a></td>
                                <td>{{ $c->consignee_name ?? '—' }}</td>
                                <td>{{ $c->phone }}<br><small class="text-muted">{{ $c->email }}</small></td>
                                <td>{{ $c->country ?? '—' }}</td>
                                @if($isPrivileged)<td>{{ $c->agent->name ?? '—' }}</td>@endif
                                <td>
                                    <span class="badge bg-{{ $badgeClass }}">{{ $badgeLabel }}</span>
                                    @if($c->security_deposit_status === 'approved')
                                        <br><small class="text-muted">¥{{ number_format($c->security_deposit) }}</small>
                                    @elseif($c->security_deposit_status === 'rejected' && $c->security_deposit_rejection_reason)
                                        <br><small class="text-danger" title="{{ $c->security_deposit_rejection_reason }}">
                                            {{ \Illuminate\Support\Str::limit($c->security_deposit_rejection_reason, 40) }}
                                        </small>
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
                                <td>{{ $c->created_at->format('d-m-Y') }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('vehicles.index', ['customer_id' => $c->id]) }}" class="text-secondary me-1" title="View Vehicles">
                                        <i class="fa fa-car"></i>
                                    </a>
                                    @can('customers.edit')
                                        <a href="#" class="text-primary me-1" title="Edit" onclick="editCustomer({{ $c->id }})">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endcan

                                    @if(in_array($c->security_deposit_status, ['none', 'rejected']))
                                        @can('customers.edit')
                                            <a href="#" class="text-success me-1" title="Record Deposit Received" onclick="openReceiveDeposit({{ $c->id }}, '{{ $c->name }}')">
                                                <i class="fa fa-hand-holding-usd"></i>
                                            </a>
                                        @endcan
                                    @elseif($c->security_deposit_status === 'pending' && $canApprove)
                                        <form action="{{ route('customers.deposit.approve', $c) }}" method="POST" style="display:inline;" onsubmit="return confirm('Approve this deposit? This posts it to the ledger and completes the profile.');">
                                            @csrf
                                            <button type="submit" class="btn btn-link p-0 text-success me-1" title="Approve Deposit"><i class="fa fa-check-circle"></i></button>
                                        </form>
                                        <a href="#" class="text-danger me-1" title="Reject Deposit" onclick="openRejectDeposit({{ $c->id }}, '{{ $c->name }}')">
                                            <i class="fa fa-times-circle"></i>
                                        </a>
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
                                <label>Consignee Name (Company) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="consignee_name" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="phone" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Country <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="country" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Postal Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="postal_code" required>
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
                                <label>Assign to Agent <span class="text-danger">*</span></label>
                                <select class="form-control select2-js" name="agent_id" required>
                                    <option value="" disabled selected>Select Agent</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-lg-12 mb-2">
                                <label>Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" rows="2" name="address" required></textarea>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Destination Port(s) <span class="text-danger">*</span></label>
                                <select data-plugin-selecttwo class="form-control select2-js" name="ports[]" multiple required>
                                    @foreach($ports as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
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
                                <label>Consignee Name (Company) <span class="text-danger">*</span></label>
                                <input type="text" id="edit_consignee_name" class="form-control" name="consignee_name" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Phone <span class="text-danger">*</span></label>
                                <input type="text" id="edit_phone" class="form-control" name="phone" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Email <span class="text-danger">*</span></label>
                                <input type="email" id="edit_email" class="form-control" name="email" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Country <span class="text-danger">*</span></label>
                                <input type="text" id="edit_country" class="form-control" name="country" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Postal Code <span class="text-danger">*</span></label>
                                <input type="text" id="edit_postal_code" class="form-control" name="postal_code" required>
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
                                <label>Assigned Agent <span class="text-danger">*</span></label>
                                <select id="edit_agent_id" class="form-control select2-js" name="agent_id" required>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-lg-12 mb-2">
                                <label>Address <span class="text-danger">*</span></label>
                                <textarea id="edit_address" class="form-control" rows="2" name="address" required></textarea>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Destination Port(s) <span class="text-danger">*</span></label>
                                <select data-plugin-selecttwo id="edit_ports" class="form-control select2-js" name="ports[]" multiple required>
                                    @foreach($ports as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
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

        @include('customers._deposit_modals')
    </div>
</div>

<script>
function editCustomer(id) {
    fetch('/customers/' + id + '/edit')
        .then(res => res.json())
        .then(data => {
            $('#editForm').attr('action', '/customers/' + id);
            $('#edit_name').val(data.name);
            $('#edit_consignee_name').val(data.consignee_name);
            $('#edit_phone').val(data.phone);
            $('#edit_email').val(data.email);
            $('#edit_country').val(data.country);
            $('#edit_postal_code').val(data.postal_code);
            $('#edit_address').val(data.address);
            $('#edit_status').val(data.status).trigger('change');
            $('#edit_agent_id').val(data.agent_id).trigger('change');
            $('#edit_ports').val(data.port_ids).trigger('change');

            $.magnificPopup.open({ items: { src: '#editModal' }, type: 'inline' });
        })
        .catch(err => {
            console.error('Failed to load customer:', err);
            alert('Could not load customer data. Please try again.');
        });
}
</script>

@endsection