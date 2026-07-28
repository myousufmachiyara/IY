@extends('layouts.app')

@section('title', 'Accounting | Chart of Accounts')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Accounting</h2>
                @can('accounting.index')
                    <button type="button" class="modal-with-form btn btn-primary btn-sm" href="#addAccountModal">
                        <i class="fas fa-plus"></i> Add Account
                    </button>
                @endcan
            </header>

            @include('accounting._tabs', ['active' => 'chart'])

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr><th>Code</th><th>Name</th><th>Type</th><th class="text-end">Current Balance</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $a)
                            <tr>
                                <td><code>{{ $a->code }}</code></td>
                                <td>{{ $a->name }} @if($a->is_system)<span class="badge bg-secondary ms-1">System</span>@endif</td>
                                <td><span class="badge bg-secondary text-uppercase">{{ $a->type }}</span></td>
                                <td class="text-end fw-bold {{ $a->current_balance < 0 ? 'text-danger' : '' }}">¥{{ number_format($a->current_balance) }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('accounting.ledger', $a) }}" class="btn btn-sm btn-outline-secondary">View Ledger</a>
                                    @if(!$a->is_system)
                                        <a href="#" class="btn btn-sm btn-outline-primary" onclick="editAccount({{ $a->id }}, '{{ $a->name }}', {{ $a->is_active ? 'true' : 'false' }})">Edit</a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- ================= ADD ACCOUNT MODAL ================= --}}
        <div id="addAccountModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('accounting.chart.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Add Account</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="code" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Type <span class="text-danger">*</span></label>
                                <select class="form-control select2-js" name="type" required>
                                    <option value="asset">Asset</option>
                                    <option value="liability">Liability</option>
                                    <option value="equity">Equity</option>
                                    <option value="income">Income</option>
                                    <option value="expense">Expense</option>
                                </select>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Add Account</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>

        {{-- ================= EDIT ACCOUNT MODAL ================= --}}
        <div id="editAccountModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="editAccountForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Edit Account</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" id="edit_account_name" class="form-control" name="name" required>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_active" id="edit_account_active" value="1">
                                    <label class="form-check-label" for="edit_account_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Update Account</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
    </div>
</div>

<script>
function editAccount(id, name, isActive) {
    document.getElementById('editAccountForm').action = '/accounting/chart/' + id;
    document.getElementById('edit_account_name').value = name;
    document.getElementById('edit_account_active').checked = isActive;
    $.magnificPopup.open({ items: { src: '#editAccountModal' }, type: 'inline' });
}
</script>
@endsection