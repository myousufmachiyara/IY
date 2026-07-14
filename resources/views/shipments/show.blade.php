@extends('layouts.app')

@section('title', 'Shipment | ' . $shipment->customer->name)

@section('content')

@php $statusColors = ['preparing' => 'warning text-dark', 'dispatched' => 'info', 'arrived' => 'success']; @endphp

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">
                    Shipment — {{ $shipment->customer->name }}
                    <span class="badge bg-{{ $statusColors[$shipment->status] ?? 'secondary' }} text-uppercase ms-1">{{ $shipment->status }}</span>
                </h2>
                <div>
                    @can('shipments.edit')
                        @if($shipment->status === 'preparing')
                            <a href="{{ route('shipments.edit', $shipment) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-edit"></i> Edit Shipment
                            </a>
                        @endif
                    @endcan
                    <a href="{{ route('shipments.index') }}" class="btn btn-sm btn-default">
                        <i class="fa fa-arrow-left"></i> Back to Shipments
                    </a>
                </div>
            </header>

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <div class="row mb-3">
                    <div class="col-md-3"><strong>Method:</strong> {{ $shipment->method }}</div>
                    <div class="col-md-3"><strong>Shipment Date:</strong> {{ optional($shipment->shipment_date)->format('d-m-Y') ?? 'Not set' }}</div>
                    <div class="col-md-3"><strong>Expected Arrival:</strong> {{ optional($shipment->expected_arrival)->format('d-m-Y') ?? 'Not set' }}</div>
                    <div class="col-md-3"><strong>Freight Total:</strong> ¥{{ number_format($shipment->freight_total) }}</div>
                </div>

                <h6 class="text-muted text-uppercase small mb-2">Vehicles in this Shipment</h6>
                <div class="table-scroll mb-4">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr><th>Vehicle</th><th>Invoice</th><th>Paid</th><th>Balance</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($shipment->vehicles as $v)
                            <tr>
                                <td><a href="{{ route('vehicles.show', $v) }}">{{ $v->label() }}</a></td>
                                <td>{{ $v->invoice->invoice_no ?? '—' }}</td>
                                <td>{{ $v->invoice ? $v->invoice->paidPercent() . '%' : '—' }}</td>
                                <td>¥{{ number_format($v->invoice?->balance() ?? 0) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @can('shipments.edit')
                <div class="row">
                    <div class="col-md-6">
                        <div class="card bg-light border">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Freight &amp; Schedule</h5>
                                <form method="POST" action="{{ route('shipments.schedule', $shipment) }}">
                                    @csrf @method('PUT')
                                    <div class="mb-2">
                                        <label>Shipment Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="shipment_date" value="{{ optional($shipment->shipment_date)->format('Y-m-d') }}" required>
                                    </div>
                                    <div class="mb-2">
                                        <label>Expected Arrival <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="expected_arrival" value="{{ optional($shipment->expected_arrival)->format('Y-m-d') }}" required>
                                    </div>
                                    <div class="mb-2">
                                        <label>Freight Total (¥) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="freight_total" min="0" value="{{ $shipment->freight_total }}" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Save Schedule</button>
                                </form>
                                <small class="text-muted d-block mt-2">Final 50% payment due date = Expected Arrival − 22 days (15 + 7 grace), applied automatically to every invoice in this shipment.</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light border">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Status Actions</h5>

                                @if($shipment->status === 'preparing')
                                    <form action="{{ route('shipments.dispatch', $shipment) }}" method="POST" onsubmit="return confirm('Mark this shipment as dispatched?');">
                                        @csrf
                                        <button type="submit" class="btn btn-info w-100 mb-2" @if(!$shipment->shipment_date) disabled title="Set the schedule first" @endif>
                                            <i class="fa fa-ship"></i> Mark Dispatched
                                        </button>
                                    </form>
                                    @if(!$shipment->shipment_date)
                                        <small class="text-danger d-block">Set freight &amp; schedule before dispatching.</small>
                                    @endif
                                @elseif($shipment->status === 'dispatched')
                                    <form action="{{ route('shipments.arrive', $shipment) }}" method="POST" onsubmit="return confirm('Mark this shipment as arrived?');">
                                        @csrf
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fa fa-anchor"></i> Mark Arrived
                                        </button>
                                    </form>
                                @else
                                    <p class="text-muted mb-0">This shipment has arrived. Manage final document release from each vehicle's Documents tab.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endcan
            </div>
        </section>
    </div>
</div>
@endsection