@extends('layouts.app')

@section('title', 'Customer | ' . $customer->name)

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">{{ $customer->name }}</h2>
                <a href="{{ route('customers.index') }}" class="btn btn-sm btn-default">
                    <i class="fa fa-arrow-left"></i> Back to All Customers
                </a>
            </header>

            @include('customers._tabs', ['customer' => $customer, 'active' => 'overview'])

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr><th width="160">Phone</th><td>{{ $customer->phone ?? '—' }}</td></tr>
                            <tr><th>Email</th><td>{{ $customer->email ?? '—' }}</td></tr>
                            <tr><th>Country</th><td>{{ $customer->country ?? '—' }}</td></tr>
                            <tr><th>Address</th><td>{{ $customer->address ?? '—' }}</td></tr>
                            <tr><th>Assigned Agent</th><td>{{ $customer->agent->name ?? '—' }}</td></tr>
                            <tr><th>Type</th><td>{{ $customer->is_new_customer ? 'New Customer' : 'Existing Customer' }}</td></tr>
                            <tr><th>Status</th><td><span class="badge bg-{{ $customer->status==='active'?'success':'danger' }}">{{ $customer->status }}</span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light border">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Profile &amp; Deposit</h5>
                                <p class="mb-2">
                                    Security Deposit:
                                    @if(!$customer->is_new_customer)
                                        <span class="text-muted">N/A (existing customer)</span>
                                    @elseif($customer->security_deposit_paid)
                                        <span class="badge bg-success">¥{{ number_format($customer->security_deposit) }} Paid</span>
                                        @if($customer->security_deposit_refunded)
                                            <span class="badge bg-secondary">Refunded</span>
                                        @endif
                                    @else
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @endif
                                </p>
                                <p class="mb-3">
                                    Profile:
                                    @if($customer->profile_completed_at)
                                        <span class="badge bg-success">Complete ({{ $customer->profile_completed_at->format('d-m-Y') }})</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Incomplete</span>
                                        <form action="{{ route('customers.complete', $customer) }}" method="POST" class="d-inline" onsubmit="return confirm('Mark profile complete?');">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-success ms-1">Complete Now</button>
                                        </form>
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
    </div>
</div>
@endsection