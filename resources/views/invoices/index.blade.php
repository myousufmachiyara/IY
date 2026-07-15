@extends('layouts.app')

@section('title', 'Invoices | All Invoices')

@section('content')

@php
    $isPrivileged = auth()->user()->can('data.view_all');
    $statusColors = ['draft'=>'secondary','issued'=>'info','partial'=>'warning text-dark','paid'=>'success','cancelled'=>'danger'];
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
                <h2 class="card-title">All Invoices</h2>
            </header>

            <div class="card-body">
                <div class="alert alert-light border py-2 mb-3">
                    <i class="fa fa-info-circle text-muted"></i>
                    <span class="text-muted">Invoices are generated from a won vehicle's page, not created here — open the vehicle's Overview tab and use "Generate Invoice" once costing is complete.</span>
                </div>

                <form method="GET" action="{{ route('invoices.index') }}" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Invoice # or customer name" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            @foreach(['draft','issued','partial','paid','cancelled'] as $s)
                                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="from" class="form-control" value="{{ request('from') }}" title="Issued from">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="to" class="form-control" value="{{ request('to') }}" title="Issued to">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary">Filter</button>
                        @if(request()->anyFilled(['search','status','from','to']))
                            <a href="{{ route('invoices.index') }}" class="btn btn-light">Clear</a>
                        @endif
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                @if($isPrivileged)<th>Agent</th>@endif
                                <th>Sale Price</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoices as $inv)
                            @php
                                $overdue = $inv->status !== 'cancelled' && $inv->status !== 'paid' &&
                                    (($inv->due_first && !$inv->isHalfPaid() && now()->gt($inv->due_first)) ||
                                     ($inv->due_final && !$inv->isFullyPaid() && now()->gt($inv->due_final)));
                            @endphp
                            <tr class="{{ $overdue ? 'table-danger' : '' }}">
                                <td>
                                    <a href="{{ route('invoices.show', $inv) }}"><strong>{{ $inv->invoice_no }}</strong></a>
                                    @if($overdue)<i class="fa fa-exclamation-triangle text-danger ms-1" title="Overdue"></i>@endif
                                </td>
                                <td><a href="{{ route('customers.show', $inv->customer) }}">{{ $inv->customer->name }}</a></td>
                                <td><a href="{{ route('vehicles.show', $inv->vehicle) }}">{{ $inv->vehicle->label() }}</a></td>
                                @if($isPrivileged)<td>{{ $inv->agent->name ?? '—' }}</td>@endif
                                <td>¥{{ number_format($inv->sale_price) }}</td>
                                <td>¥{{ number_format($inv->amount_paid) }}</td>
                                <td class="fw-bold {{ $inv->balance() > 0 ? 'text-danger' : 'text-success' }}">¥{{ number_format($inv->balance()) }}</td>
                                <td><span class="badge bg-{{ $statusColors[$inv->status] ?? 'secondary' }} text-uppercase">{{ $inv->status }}</span></td>
                                <td class="text-nowrap">
                                    <a href="{{ route('invoices.show', $inv) }}" class="text-secondary me-1" title="View">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <a href="{{ route('invoices.pdf', $inv) }}" class="text-success" title="Download PDF">
                                        <i class="fa fa-file-pdf"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="{{ $isPrivileged ? 9 : 8 }}" class="text-center text-muted py-4">No invoices match this filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection