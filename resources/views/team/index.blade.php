@extends('layouts.app')

@section('title', 'Team | All Members')

@section('content')

<div class="row">
    <div class="col">
        <section class="card">
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

            <header class="card-header">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <h2 class="card-title">Team Members</h2>
                    @can('team.create')
                        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                            <i class="fas fa-plus"></i> Add Member
                        </button>
                    @endcan
                </div>
            </header>

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr><th>S.No</th><th>Name</th><th>Username</th><th>Role</th><th>Contact</th><th>Economics</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $u)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $u->name }}</strong></td>
                                <td>{{ $u->username }}</td>
                                <td><span class="badge bg-secondary text-uppercase">{{ \Illuminate\Support\Str::headline($u->roles->first()?->name ?? '—') }}</span></td>
                                <td>{{ $u->email }}<br><small class="text-muted">{{ $u->phone }}</small></td>
                                <td>
                                    @if(!is_null($u->sales_commission_percent))
                                        {{ $u->sales_commission_percent }}% + ¥{{ number_format($u->sales_fixed_bonus) }}
                                    @elseif(!is_null($u->vendor_commission_percent))
                                        {{ $u->vendor_commission_percent }}% · {{ $u->vendor_location }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-{{ $u->status==='active'?'success':'danger' }}">{{ $u->status }}</span></td>
                                <td>
                                    @can('team.edit')
                                        <a href="#" class="text-primary me-1" onclick="editUser({{ $u->id }})"><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('team.delete')
                                        <form action="{{ route('team.destroy', $u) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this member?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-link p-0 text-danger"><i class="fa fa-trash-alt"></i></button>
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

        @can('team.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('team.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Add Team Member</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2"><label>Full Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" required></div>
                            <div class="col-lg-6 mb-2"><label>Username <span class="text-danger">*</span></label><input type="text" class="form-control" name="username" required></div>
                            <div class="col-lg-6 mb-2">
                                <label>Role <span class="text-danger">*</span></label>
                                <select data-plugin-selecttwo id="add_role" class="form-control select2-js" name="role" required onchange="toggleEconomics('add')">
                                    <option value="" disabled selected>Select role</option>
                                    @foreach($roles as $r)<option value="{{ $r->name }}">{{ \Illuminate\Support\Str::headline($r->name) }}</option>@endforeach
                                </select>
                                <small class="text-muted">Manage available roles under <a href="{{ route('roles.index') }}" target="_blank">Roles &amp; Permissions</a>.</small>
                            </div>
                            <div class="col-lg-6 mb-2"><label>Status <span class="text-danger">*</span></label>
                                <select class="form-control select2-js" name="status" required><option value="active" selected>Active</option><option value="inactive">Inactive</option></select>
                            </div>
                            <div class="col-lg-6 mb-2"><label>Email</label><input type="email" class="form-control" name="email"></div>
                            <div class="col-lg-6 mb-2"><label>Phone</label><input type="text" class="form-control" name="phone"></div>
                            <div class="col-lg-6 mb-2"><label>Password <span class="text-danger">*</span></label><input type="password" class="form-control" name="password" required></div>
                            <div class="col-lg-6 mb-2"><label>Confirm Password</label><input type="password" class="form-control" name="password_confirmation" required></div>

                            <div class="col-lg-6 mb-2 add-economics add-economics-sales_agent d-none"><label>Sales Commission %</label><input type="number" step="0.01" class="form-control" name="sales_commission_percent" value="15"></div>
                            <div class="col-lg-6 mb-2 add-economics add-economics-sales_agent d-none"><label>Fixed Bonus per Won Bid (¥)</label><input type="number" class="form-control" name="sales_fixed_bonus" value="0"></div>
                            <div class="col-lg-6 mb-2 add-economics add-economics-vendor_agent d-none"><label>Vendor Commission %</label><input type="number" step="0.01" class="form-control" name="vendor_commission_percent" value="7"></div>
                            <div class="col-lg-6 mb-2 add-economics add-economics-vendor_agent d-none"><label>Yard / Auction Location</label><input type="text" class="form-control" name="vendor_location"></div>
                        </div>
                    </div>
                    <footer class="card-footer"><div class="col-md-12 text-end"><button type="submit" class="btn btn-primary">Add Member</button><button type="button" class="btn btn-default modal-dismiss">Cancel</button></div></footer>
                </form>
            </section>
        </div>
        @endcan

        @can('team.edit')
        <div id="editModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="editForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Edit Team Member</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2"><label>Full Name <span class="text-danger">*</span></label><input type="text" id="edit_name" class="form-control" name="name" required></div>
                            <div class="col-lg-6 mb-2"><label>Username <span class="text-danger">*</span></label><input type="text" id="edit_username" class="form-control" name="username" required></div>
                            <div class="col-lg-6 mb-2">
                                <label>Role <span class="text-danger">*</span></label>
                                <select id="edit_role" class="form-control select2-js" name="role" required onchange="toggleEconomics('edit')">
                                    <option value="" disabled>Select role</option>
                                    @foreach($roles as $r)<option value="{{ $r->name }}">{{ \Illuminate\Support\Str::headline($r->name) }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2"><label>Status <span class="text-danger">*</span></label>
                                <select id="edit_status" class="form-control select2-js" name="status" required><option value="active">Active</option><option value="inactive">Inactive</option></select>
                            </div>
                            <div class="col-lg-6 mb-2"><label>Email</label><input type="email" id="edit_email" class="form-control" name="email"></div>
                            <div class="col-lg-6 mb-2"><label>Phone</label><input type="text" id="edit_phone" class="form-control" name="phone"></div>
                            <div class="col-lg-6 mb-2"><label>Password <small class="text-muted">(leave blank to keep)</small></label><input type="password" class="form-control" name="password"></div>
                            <div class="col-lg-6 mb-2"><label>Confirm Password</label><input type="password" class="form-control" name="password_confirmation"></div>

                            <div class="col-lg-6 mb-2 edit-economics edit-economics-sales_agent d-none"><label>Sales Commission %</label><input type="number" step="0.01" id="edit_sales_commission_percent" class="form-control" name="sales_commission_percent"></div>
                            <div class="col-lg-6 mb-2 edit-economics edit-economics-sales_agent d-none"><label>Fixed Bonus per Won Bid (¥)</label><input type="number" id="edit_sales_fixed_bonus" class="form-control" name="sales_fixed_bonus"></div>
                            <div class="col-lg-6 mb-2 edit-economics edit-economics-vendor_agent d-none"><label>Vendor Commission %</label><input type="number" step="0.01" id="edit_vendor_commission_percent" class="form-control" name="vendor_commission_percent"></div>
                            <div class="col-lg-6 mb-2 edit-economics edit-economics-vendor_agent d-none"><label>Yard / Auction Location</label><input type="text" id="edit_vendor_location" class="form-control" name="vendor_location"></div>
                        </div>
                    </div>
                    <footer class="card-footer"><div class="col-md-12 text-end"><button type="submit" class="btn btn-primary">Update Member</button><button type="button" class="btn btn-default modal-dismiss">Cancel</button></div></footer>
                </form>
            </section>
        </div>
        @endcan
    </div>
</div>

<script>
const rolePermMap = @json($rolePermMap);

function toggleEconomics(prefix) {
    const perms = rolePermMap[$('#' + prefix + '_role').val()] || { agent: false, vendor: false };
    $('.' + prefix + '-economics').addClass('d-none');
    if (perms.agent)  $('.' + prefix + '-economics-sales_agent').removeClass('d-none');
    if (perms.vendor) $('.' + prefix + '-economics-vendor_agent').removeClass('d-none');
}

function editUser(id) {
    fetch('/team/' + id + '/edit')
        .then(res => res.json())
        .then(data => {
            $('#editForm').attr('action', '/team/' + id);
            $('#edit_name').val(data.name);
            $('#edit_username').val(data.username);
            $('#edit_email').val(data.email);
            $('#edit_phone').val(data.phone);
            $('#edit_sales_commission_percent').val(data.sales_commission_percent);
            $('#edit_sales_fixed_bonus').val(data.sales_fixed_bonus);
            $('#edit_vendor_commission_percent').val(data.vendor_commission_percent);
            $('#edit_vendor_location').val(data.vendor_location);
            $('#edit_role').val(data.role).trigger('change');
            $('#edit_status').val(data.status).trigger('change');
            toggleEconomics('edit');
            $.magnificPopup.open({ items: { src: '#editModal' }, type: 'inline' });
        })
        .catch(() => alert('Could not load member data. Please try again.'));
}
</script>
@endsection