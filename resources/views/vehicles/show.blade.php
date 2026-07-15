@extends('layouts.app')

@section('title', 'Vehicle | ' . $vehicle->label())

@section('content')

@php
    $statusColors = [
        'requirement' => 'secondary', 'bidding' => 'info', 'won' => 'success', 'lost' => 'danger',
        'invoiced' => 'primary', 'dispatched' => 'warning', 'arrived' => 'warning', 'delivered' => 'success',
    ];
@endphp

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">
                    {{ $vehicle->label() }}
                    <span class="badge bg-{{ $statusColors[$vehicle->status] ?? 'secondary' }} text-uppercase ms-1">{{ $vehicle->status }}</span>
                </h2>
                <a href="{{ route('vehicles.index') }}" class="btn btn-sm btn-default">
                    <i class="fa fa-arrow-left"></i> Back to All Vehicles
                </a>
            </header>

            @include('vehicles._tabs', ['vehicle' => $vehicle, 'active' => 'overview'])

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small mb-2">Requirement</h6>
                        <table class="table table-borderless mb-3">
                            <tr><th width="160">Make / Model</th><td>{{ $vehicle->make }} {{ $vehicle->model }}</td></tr>
                            <tr><th>Year</th><td>{{ $vehicle->year ?? '—' }}</td></tr>
                            <tr><th>Grade</th><td>{{ $vehicle->grade ?? '—' }}</td></tr>
                            <tr><th>Chassis No.</th><td>{{ $vehicle->chassis_no ?? '—' }}</td></tr>
                            <tr><th>Budget</th><td>¥{{ number_format($vehicle->budget) }}</td></tr>
                        </table>

                        <h6 class="text-muted text-uppercase small mb-2">Parties</h6>
                        <table class="table table-borderless mb-0">
                            <tr><th width="160">Customer</th><td><a href="{{ route('customers.show', $vehicle->customer) }}">{{ $vehicle->customer->name }}</a></td></tr>
                            <tr><th>Sales Agent</th><td>{{ $vehicle->agent->name ?? '—' }}</td></tr>
                            <tr><th>Vendor</th><td>{{ $vehicle->vendor->name ?? '—' }}</td></tr>
                        </table>
                    </div>

                    <div class="col-md-6">
                        @if($vehicle->isWon())
                            <div class="card bg-light border mb-3">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">Winning Bid</h6>
                                    <table class="table table-sm table-borderless mb-2">
                                        <tr><td>Buying Price</td><td class="text-end fw-bold">¥{{ number_format($vehicle->buying_price) }}</td></tr>
                                        <tr><td>Won On</td><td class="text-end">{{ optional($vehicle->won_at)->format('d-m-Y') }}</td></tr>
                                        @if($vehicle->selling_price)
                                            <tr><td>Selling Price</td><td class="text-end fw-bold">¥{{ number_format($vehicle->selling_price) }}</td></tr>
                                        @endif
                                        @if($vehicle->costing)
                                            <tr><td>Profit</td><td class="text-end fw-bold {{ $vehicle->costing->profit >= 0 ? 'text-success' : 'text-danger' }}">¥{{ number_format($vehicle->costing->profit) }}</td></tr>
                                        @endif
                                    </table>
                                    @if($vehicle->winning_screenshot_path)
                                        <a href="{{ \Storage::url($vehicle->winning_screenshot_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="fa fa-image"></i> View Winning Screenshot
                                        </a>
                                    @endif
                                </div>
                            </div>

                            {{-- Contextual next-step actions --}}
                            @if(!$vehicle->costing || $vehicle->costing->total_costing == 0)
                                <div class="alert alert-warning">
                                    <i class="fa fa-exclamation-triangle"></i> Costing breakdown not yet prepared.
                                    <a href="{{ route('costings.show', $vehicle) }}" class="alert-link">Complete it now &rarr;</a>
                                </div>
                            @elseif(!$vehicle->invoice)
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i> Costing complete — ready to invoice.
                                    @can('invoices.create')
                                        <form action="{{ route('invoices.store', $vehicle) }}" method="POST" class="d-inline" onsubmit="return confirm('Generate the official invoice for this vehicle?');">
                                            @csrf
                                            <button class="btn btn-sm btn-primary ms-2">Generate Invoice</button>
                                        </form>
                                    @endcan
                                </div>
                            @elseif($vehicle->invoice->isHalfPaid() && !$vehicle->shipment)
                            <div class="alert alert-success">
                                <i class="fa fa-check-circle"></i> 50% or more paid — ready for shipment.
                                <div class="alert alert-success">
                                    <i class="fa fa-check-circle"></i> Invoice fully paid — ready for shipment.
                                    @can('shipments.create')
                                        <a href="{{ route('shipments.create', $vehicle->customer) }}" class="alert-link ms-1">Prepare Shipment &rarr;</a>
                                    @endcan
                                </div>
                            @endif
                        @else
                            <div class="alert alert-light border">
                                <i class="fa fa-gavel text-muted"></i>
                                This vehicle requirement hasn't been won at auction yet.
                                @can('bid_sheets.index')
                                    Track its bid via <a href="{{ route('bid-sheets.index') }}">Bid Sheets</a>.
                                @endcan
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection