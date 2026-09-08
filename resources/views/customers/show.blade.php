@extends('layouts.app')

@section('title', 'Customer | ' . $customer->name)

@section('content')

@php
    $depositBadges = [
        'none'     => ['secondary', 'Not Submitted'],
        'pending'  => ['warning text-dark', 'Pending Approval'],
        'approved' => ['success', 'Approved'],
        'rejected' => ['danger', 'Rejected'],
    ];
    [$badgeClass, $badgeLabel] = $depositBadges[$customer->security_deposit_status] ?? $depositBadges['none'];
    $canApprove = auth()->user()->canBackdate();
    $vehicleStatusColors = [
        'requirement' => 'secondary', 'bidding' => 'info', 'won' => 'success', 'lost' => 'danger',
        'invoiced' => 'primary', 'dispatched' => 'warning', 'arrived' => 'warning', 'delivered' => 'success',
    ];
@endphp

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">{{ $customer->name }}</h2>
                <div>
                    <a href="{{ route('customers.index') }}" class="btn btn-sm btn-default">
                        <i class="fa fa-arrow-left"></i> Back to All Customers
                    </a>
                    @can('invoices.create')
                        <a href="{{ route('invoices.bulk_create_form', $customer) }}" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-file-invoice"></i> Bulk Generate Invoices
                        </a>
                    @endcan
                    @can('invoices.print')
                        <a href="{{ route('invoices.merge_select', $customer) }}" class="btn btn-sm btn-outline-secondary mb-2">
                            <i class="fa fa-file-pdf"></i> Merge Invoices to PDF
                        </a>
                    @endcan
                </div>
            </header>

            @include('customers._tabs', ['customer' => $customer, 'active' => 'overview'])

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr><th width="160">Consignee</th><td>{{ $customer->consignee_name ?? '—' }}</td></tr>
                            <tr><th>Phone</th><td>{{ $customer->phone ?? '—' }}</td></tr>
                            <tr><th>Email</th><td>{{ $customer->email ?? '—' }}</td></tr>
                            <tr><th>Country</th><td>{{ $customer->country ?? '—' }}</td></tr>
                            <tr><th>Postal Code</th><td>{{ $customer->postal_code ?? '—' }}</td></tr>
                            <tr><th>Address</th><td>{{ $customer->address ?? '—' }}</td></tr>
                            <tr><th>Destination Ports</th><td>{{ $customer->ports->pluck('name')->join(', ') ?: '—' }}</td></tr>
                            <tr><th>Assigned Agent</th><td>{{ $customer->agent->name ?? '—' }}</td></tr>
                            <tr><th>Account Date</th><td>{{ optional($customer->account_date)->format('d-m-Y') ?? $customer->created_at->format('d-m-Y') }}</td></tr>
                            <tr><th>Status</th><td><span class="badge bg-{{ $customer->status==='active'?'success':'danger' }}">{{ $customer->status }}</span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light border">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Profile &amp; Deposit</h5>

                                @if($customer->deposit_invoice_id && $customer->depositInvoice)
                                    <p class="mb-2">
                                        Deposit Invoice:
                                        <a href="{{ route('invoices.show', $customer->depositInvoice) }}">{{ $customer->depositInvoice->invoice_no }}</a>
                                        @if($customer->depositInvoice->isFullyPaid())
                                            <span class="badge bg-success ms-1">Paid</span>
                                        @else
                                            <span class="badge bg-warning text-dark ms-1">Awaiting Payment</span>
                                        @endif
                                        <br><small class="text-muted">
                                            Amount: ¥{{ number_format($customer->depositInvoice->total_payable) }} —
                                            Paid: ¥{{ number_format($customer->depositInvoice->amount_paid) }} —
                                            Balance: ¥{{ number_format($customer->depositInvoice->balance()) }}
                                        </small>
                                    </p>
                                @elseif($customer->security_deposit_status !== 'none')
                                    <p class="mb-2">
                                        Security Deposit (legacy): <span class="badge bg-{{ $badgeClass }}">{{ $badgeLabel }}</span>
                                        @if($customer->security_deposit_status === 'approved')
                                            <br><small class="text-muted">¥{{ number_format($customer->security_deposit) }} — received by {{ $customer->depositReceivedBy->name ?? '—' }}, approved by {{ $customer->depositApprovedBy->name ?? '—' }} on {{ $customer->security_deposit_approved_at->format('d-m-Y') }}</small>
                                        @elseif($customer->security_deposit_status === 'pending')
                                            <br><small class="text-muted">¥{{ number_format($customer->security_deposit) }} — received by {{ $customer->depositReceivedBy->name ?? '—' }} on {{ $customer->security_deposit_received_at->format('d-m-Y') }}, awaiting approval</small>
                                            @if($canApprove)
                                                <div class="mt-1">
                                                    <form action="{{ route('customers.deposit.approve', $customer) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve this deposit?');">
                                                        @csrf
                                                        <button class="btn btn-sm btn-success">Approve</button>
                                                    </form>
                                                    <a href="#" class="btn btn-sm btn-outline-danger" onclick="openRejectDeposit({{ $customer->id }}, '{{ $customer->name }}')">Reject</a>
                                                </div>
                                            @endif
                                        @elseif($customer->security_deposit_status === 'rejected')
                                            <br><small class="text-danger">Reason: {{ $customer->security_deposit_rejection_reason }}</small>
                                        @endif
                                    </p>
                                @else
                                    <p class="mb-2">
                                        Deposit Invoice: <span class="badge bg-secondary">Not Generated</span>
                                        @can('customers.edit')
                                            <div class="mt-1">
                                                <a href="#" class="btn btn-sm btn-outline-success" onclick="openGenerateDepositInvoice({{ $customer->id }}, '{{ $customer->name }}')">
                                                    <i class="fa fa-file-invoice-dollar"></i> Generate Deposit Invoice
                                                </a>
                                            </div>
                                        @endcan
                                    </p>
                                @endif

                                <p class="mb-3">
                                    Profile:
                                    @if($customer->profile_completed_at)
                                        <span class="badge bg-success">Complete ({{ $customer->profile_completed_at->format('d-m-Y') }})</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Incomplete</span>
                                        <div class="small text-muted mt-1">Completes automatically once the deposit invoice is fully paid.</div>
                                    @endif
                                </p>

                                <h6 class="mb-2">Financial Summary</h6>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr><td>Total Invoiced</td><td class="text-end">¥{{ number_format($customer->totalInvoiced()) }}</td></tr>
                                    <tr><td>Total Paid</td><td class="text-end">¥{{ number_format($customer->totalPaid()) }}</td></tr>
                                    <tr class="fw-bold"><td>Balance Due</td><td class="text-end">¥{{ number_format($customer->balance()) }}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="text-muted text-uppercase small mt-4 mb-2">All Vehicles</h6>
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead><tr><th>Vehicle</th><th>Status</th><th>Buying Price</th><th>Selling Price</th><th>Won On</th></tr></thead>
                        <tbody>
                            @forelse($customer->vehicles as $v)
                            <tr>
                                <td><a href="{{ route('vehicles.show', $v) }}">{{ $v->label() }}</a>@if($v->grade)<br><small class="text-muted">{{ $v->grade }}</small>@endif</td>
                                <td><span class="badge bg-{{ $vehicleStatusColors[$v->status] ?? 'secondary' }} text-uppercase">{{ $v->status }}</span></td>
                                <td>{{ $v->buying_price ? '¥'.number_format($v->buying_price) : '—' }}</td>
                                <td>{{ $v->selling_price ? '¥'.number_format($v->selling_price) : '—' }}</td>
                                <td>{{ optional($v->won_at)->format('d-m-Y') ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No vehicles recorded for this customer yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @can('customers.edit')
        <div id="generateDepositInvoiceModal" class="modal-block modal-block-success mfp-hide">
            <section class="card">
                <form method="POST" id="generateDepositInvoiceForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Generate Deposit Invoice — <span id="gdi_customer_name"></span></h2></header>
                    <div class="card-body">
                        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Deposit Amount (¥) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="amount" min="1" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Invoice Date</label>
                                <input type="date" class="form-control" name="invoice_date" value="{{ date('Y-m-d') }}"
                                    @unless(auth()->user()->isSuperAdmin()) readonly @endunless>
                            </div>
                        </div>
                        <p class="text-muted small mb-0">This creates an unpaid invoice for the deposit. Record the customer's payment against it (same as any invoice) to complete their profile.</p>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-success">Generate Invoice</button>
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
function openGenerateDepositInvoice(id, name) {
    document.getElementById('generateDepositInvoiceForm').action = '/customers/' + id + '/generate-deposit-invoice';
    document.getElementById('gdi_customer_name').textContent = name;
    $.magnificPopup.open({ items: { src: '#generateDepositInvoiceModal' }, type: 'inline' });
}
</script>
@endsection