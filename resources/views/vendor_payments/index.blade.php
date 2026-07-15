@extends('layouts.app')

@section('title', 'Vendor Payments | All Vendor Payments')

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
                    <h2 class="card-title">Vendor Payments</h2>
                    @can('vendor_payments.create')
                        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                            <i class="fas fa-plus"></i> Record Vendor Payment
                        </button>
                    @endcan
                </div>
            </header>

            <div class="card-body">
                <form method="GET" action="{{ route('vendor-payments.index') }}" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <select name="vendor_id" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Vendors</option>
                            @foreach($vendors as $v)
                                <option value="{{ $v->id }}" {{ request('vendor_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Vendor</th>
                                <th>Vehicle</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Backdated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $p)
                            <tr>
                                <td>{{ $p->paid_at->format('d-m-Y') }}</td>
                                <td>{{ $p->vendor->name ?? '—' }}</td>
                                <td><a href="{{ route('vehicles.show', $p->vehicle) }}">{{ $p->vehicle->label() }}</a></td>
                                <td>¥{{ number_format($p->amount) }}</td>
                                <td class="text-capitalize">{{ $p->method }}</td>
                                <td>{{ $p->reference ?? '—' }}</td>
                                <td>{{ $p->is_backdated ? 'Yes' : '—' }}</td>
                                <td class="text-nowrap">
                                    @can('vendor_payments.edit')
                                        <a href="#" class="text-primary me-1" title="Edit"
                                           onclick="editVendorPayment({{ $p->id }}, {{ $p->amount }}, '{{ $p->method }}', '{{ $p->paid_at->format('Y-m-d') }}', '{{ $p->reference }}')">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endcan
                                    @can('vendor_payments.delete')
                                        <form action="{{ route('vendor-payments.destroy', $p) }}" method="POST" style="display:inline;"
                                              onsubmit="return confirm('Delete this vendor payment? The ledger entry will be reversed.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-link p-0 text-danger"><i class="fa fa-trash-alt"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No vendor payments recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- ================= ADD MODAL ================= --}}
        @can('vendor_payments.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('vendor-payments.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Record Vendor Payment</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2">
                                <label>Vehicle <span class="text-danger">*</span></label>
                                <select data-plugin-selecttwo class="form-control select2-js" name="vehicle_id" id="add_vp_vehicle" required onchange="updateOutstanding(this)">
                                    <option value="" disabled selected>Select vehicle</option>
                                    @foreach($vehicles as $v)
                                        <option value="{{ $v->id }}" data-outstanding="{{ $v->outstanding }}">
                                            {{ $v->label() }} — {{ $v->vendor->name }} (Owes ¥{{ number_format($v->outstanding) }})
                                        </option>
                                    @endforeach
                                </select>
                                @if($vehicles->isEmpty())
                                    <small class="text-muted">No vehicles currently have an outstanding vendor balance.</small>
                                @endif
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Amount (¥) <span class="text-danger">*</span></label>
                                <input type="number" id="add_vp_amount" class="form-control" name="amount" min="1" required>
                                <small class="text-muted" id="add_vp_outstanding_hint"></small>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Method <span class="text-danger">*</span></label>
                                <select class="form-control select2-js" name="method" required>
                                    <option value="bank" selected>Bank</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Date Paid <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="paid_at" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Reference</label>
                                <input type="text" class="form-control" name="reference">
                            </div>
                            @if(auth()->user()->canBackdate())
                            <div class="col-lg-12 mb-2">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_backdated" id="vp_is_backdated" value="1">
                                    <label class="form-check-label" for="vp_is_backdated">This is a back-dated entry</label>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Record Payment</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan

        {{-- ================= EDIT MODAL ================= --}}
        @can('vendor_payments.edit')
        <div id="editVpModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="editVpForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Edit Vendor Payment</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Amount (¥) <span class="text-danger">*</span></label>
                                <input type="number" id="edit_vp_amount" class="form-control" name="amount" min="1" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Method <span class="text-danger">*</span></label>
                                <select id="edit_vp_method" class="form-control select2-js" name="method" required>
                                    <option value="bank">Bank</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Date Paid <span class="text-danger">*</span></label>
                                <input type="date" id="edit_vp_date" class="form-control" name="paid_at" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Reference</label>
                                <input type="text" id="edit_vp_reference" class="form-control" name="reference">
                            </div>
                        </div>
                        <p class="text-muted small mb-0"><i class="fa fa-info-circle"></i> Editing reverses the original ledger entry and posts a fresh one.</p>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Update Payment</button>
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
function updateOutstanding(select) {
    const opt = select.options[select.selectedIndex];
    const outstanding = opt.dataset.outstanding || 0;
    document.getElementById('add_vp_amount').value = outstanding;
    document.getElementById('add_vp_outstanding_hint').textContent = 'Outstanding: ¥' + Number(outstanding).toLocaleString();
}

function editVendorPayment(id, amount, method, paidAt, reference) {
    document.getElementById('editVpForm').action = '/vendor-payments/' + id;
    document.getElementById('edit_vp_amount').value = amount;
    document.getElementById('edit_vp_date').value = paidAt;
    document.getElementById('edit_vp_reference').value = reference || '';
    $('#edit_vp_method').val(method).trigger('change');
    $.magnificPopup.open({ items: { src: '#editVpModal' }, type: 'inline' });
}
</script>

@endsection