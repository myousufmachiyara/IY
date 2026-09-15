@extends('layouts.app')

@section('title', 'Pending Approvals')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Pending Approvals</h2></header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <h6 class="text-muted text-uppercase small mb-2">Security Deposits ({{ $pendingDeposits->count() }})</h6>
                <div class="table-scroll mb-4">
                    <table class="table table-bordered table-striped mb-0">
                        <thead><tr><th>Customer</th><th>Amount</th><th>Received By</th><th>Received At</th><th>Evidence</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse($pendingDeposits as $c)
                            <tr>
                                <td><a href="{{ route('customers.show', $c) }}">{{ $c->name }}</a></td>
                                <td>¥{{ number_format($c->security_deposit) }}</td>
                                <td>{{ $c->depositReceivedBy->name ?? '—' }}</td>
                                <td>{{ optional($c->security_deposit_received_at)->format('d-m-Y H:i') ?? '—' }}</td>
                                <td>@if($c->security_deposit_evidence_path)<a href="{{ \App\Services\PublicStorage::url($c->security_deposit_evidence_path) }}" target="_blank"><i class="fa fa-paperclip"></i></a>@else<span class="text-muted">—</span>@endif</td>
                                <td>
                                    <form action="{{ route('customers.deposit.approve', $c) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve this deposit?');">
                                        @csrf<button class="btn btn-sm btn-success">Approve</button>
                                    </form>
                                    <a href="#" class="btn btn-sm btn-outline-danger" onclick="openRejectDeposit({{ $c->id }}, '{{ $c->name }}')">Reject</a>
                                </td>
                            </tr>
                            @empty<tr><td colspan="6" class="text-center text-muted py-3">No deposits awaiting approval.</td></tr>@endforelse
                        </tbody>
                    </table>
                </div>

                <h6 class="text-muted text-uppercase small mb-2">Payments ({{ $pendingPayments->count() }})</h6>
                <div class="table-scroll mb-4">
                    <table class="table table-bordered table-striped mb-0">
                        <thead><tr><th>Customer</th><th>Invoice</th><th>Amount</th><th>Recorded By</th><th>Date</th><th>Attachment</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse($pendingPayments as $p)
                            <tr>
                                <td>{{ $p->customer->name }}</td>
                                <td>@if($p->invoice)<a href="{{ route('invoices.show', $p->invoice) }}">{{ $p->invoice->invoice_no }}</a>@else<span class="text-muted">—</span>@endif</td>
                                <td>¥{{ number_format($p->amount) }}</td>
                                <td>{{ $p->recorder->name ?? '—' }}</td>
                                <td>{{ $p->paid_at->format('d-m-Y') }}</td>
                                <td>@if($p->attachment_path)<a href="{{ \App\Services\PublicStorage::url($p->attachment_path) }}" target="_blank"><i class="fa fa-paperclip"></i></a>@else<span class="text-muted">—</span>@endif</td>
                                <td>
                                    <form action="{{ route('payments.approve', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve this payment?');">
                                        @csrf<button class="btn btn-sm btn-success">Approve</button>
                                    </form>
                                    <a href="#" class="btn btn-sm btn-outline-danger" onclick="openRejectPayment({{ $p->id }})">Reject</a>
                                </td>
                            </tr>
                            @empty<tr><td colspan="7" class="text-center text-muted py-3">No payments awaiting approval.</td></tr>@endforelse
                        </tbody>
                    </table>
                </div>

                <h6 class="text-muted text-uppercase small mb-2">Invoice Requests ({{ $pendingInvoiceRequests->count() }})</h6>
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead><tr><th>Vehicle</th><th>Customer</th><th>Agent</th><th>Requested</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse($pendingInvoiceRequests as $v)
                            <tr>
                                <td>{{ $v->label() }}</td>
                                <td>{{ $v->customer->name }}</td>
                                <td>{{ $v->agent->name ?? '—' }}</td>
                                <td>{{ $v->invoice_requested_at->diffForHumans() }}</td>
                                <td>
                                    <a href="{{ route('vehicles.show', $v) }}" class="btn btn-sm btn-primary">Review &amp; Generate Invoice</a>
                                    <a href="#" class="btn btn-sm btn-outline-danger" onclick="openRejectInvoiceRequest({{ $v->id }}, '{{ $v->label() }}')">Reject</a>
                                </td>
                            </tr>
                            @empty<tr><td colspan="5" class="text-center text-muted py-3">No invoice requests awaiting approval.</td></tr>@endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @include('customers._deposit_modals')

        <div id="rejectPaymentModal" class="modal-block modal-block-danger mfp-hide">
            <section class="card">
                <form method="POST" id="rejectPaymentForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Reject Payment</h2></header>
                    <div class="card-body">
                        <label>Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="rejection_reason" rows="3" required></textarea>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-danger">Reject</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>

        <div id="rejectInvoiceRequestModal" class="modal-block modal-block-danger mfp-hide">
            <section class="card">
                <form method="POST" id="rejectInvoiceRequestForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Reject Invoice Request — <span id="reject_iv_vehicle_label"></span></h2></header>
                    <div class="card-body">
                        <label>Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="invoice_request_rejection_reason" rows="3" required></textarea>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-danger">Reject</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
    </div>
</div>
<script>
function openRejectPayment(id) {
    document.getElementById('rejectPaymentForm').action = '/payments/' + id + '/reject';
    $.magnificPopup.open({ items: { src: '#rejectPaymentModal' }, type: 'inline' });
}
function openRejectInvoiceRequest(id, label) {
    document.getElementById('rejectInvoiceRequestForm').action = '/vehicles/' + id + '/reject-invoice-request';
    document.getElementById('reject_iv_vehicle_label').textContent = label;
    $.magnificPopup.open({ items: { src: '#rejectInvoiceRequestModal' }, type: 'inline' });
}
</script>
@endsection