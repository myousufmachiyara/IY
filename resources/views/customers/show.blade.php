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
                        <a href="{{ route('invoices.bulk_create_form', $customer) }}" class="btn btn-sm btn-outline-primary mb-2">
                            <i class="fa fa-file-invoice"></i> Bulk Generate Invoices
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
                            <tr><th>Created</th><td>{{ $customer->created_at->format('d-m-Y') }}</td></tr>
                            <tr><th>Status</th><td><span class="badge bg-{{ $customer->status==='active'?'success':'danger' }}">{{ $customer->status }}</span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light border">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Profile &amp; Deposit</h5>

                                <p class="mb-2">
                                    Security Deposit: <span class="badge bg-{{ $badgeClass }}">{{ $badgeLabel }}</span>
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

                                    @if(in_array($customer->security_deposit_status, ['none', 'rejected']))
                                        @can('customers.edit')
                                            <div class="mt-1">
                                                <a href="#" class="btn btn-sm btn-outline-success" onclick="openReceiveDeposit({{ $customer->id }}, '{{ $customer->name }}')">
                                                    <i class="fa fa-hand-holding-usd"></i> Record Deposit Received
                                                </a>
                                            </div>
                                        @endcan
                                    @endif
                                </p>

                                <p class="mb-3">
                                    Profile:
                                    @if($customer->profile_completed_at)
                                        <span class="badge bg-success">Complete ({{ $customer->profile_completed_at->format('d-m-Y') }})</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Incomplete</span>
                                        <div class="small text-muted mt-1">Completes automatically once the deposit is approved.</div>
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
            </div>
        </section>

        @include('customers._deposit_modals')
    </div>
</div>
@endsection