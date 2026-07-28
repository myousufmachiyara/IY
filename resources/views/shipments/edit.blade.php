@extends('layouts.app')

@section('title', 'Edit Shipment | ' . $shipment->customer->name)

@section('content')

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Edit Shipment — {{ $shipment->customer->name }}</h2>
                <a href="{{ route('shipments.show', $shipment) }}" class="btn btn-sm btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Shipment
                </a>
            </header>

            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif

                <form method="POST" action="{{ route('shipments.update', $shipment) }}">
                    @csrf @method('PUT')

                    <div class="row mb-3">
                        <div class="col-md-4 mb-2">
                            <label>Shipment Method <span class="text-danger">*</span></label>
                            <select class="form-control select2-js" name="method" required>
                                <option value="RORO" {{ $shipment->method === 'RORO' ? 'selected' : '' }}>RORO</option>
                                <option value="Container" {{ $shipment->method === 'Container' ? 'selected' : '' }}>Container</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>Expected Arrival <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="expected_arrival" value="{{ optional($shipment->expected_arrival)->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>Container #</label>
                            <input type="text" class="form-control" name="container_no" value="{{ $shipment->container_no }}">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>BL #</label>
                            <input type="text" class="form-control" name="bl_no" value="{{ $shipment->bl_no }}">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label>Shipping Company <small class="text-muted">(optional)</small></label>
                            <input type="text" class="form-control" name="shipping_company" value="{{ $shipment->shipping_company }}">
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase small mb-2">Vehicles in this Shipment</h6>

                    <div class="table-scroll mb-3">
                        <table class="table table-bordered table-striped mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>Vehicle</th>
                                    <th>Invoice</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($shipment->vehicles as $v)
                                <tr>
                                    <td><input type="checkbox" name="vehicle_ids[]" value="{{ $v->id }}" checked></td>
                                    <td>{{ $v->label() }}</td>
                                    <td>{{ $v->invoice->invoice_no ?? '—' }}</td>
                                    <td>{{ $v->invoice ? $v->invoice->paidPercent() . '%' : '—' }}</td>
                                    <td>¥{{ number_format($v->invoice?->balance() ?? 0) }}</td>
                                    <td><span class="badge bg-info">In shipment</span></td>
                                </tr>
                                @endforeach
                                @foreach ($additional as $v)
                                <tr>
                                    <td><input type="checkbox" name="vehicle_ids[]" value="{{ $v->id }}"></td>
                                    <td>{{ $v->label() }}</td>
                                    <td>{{ $v->invoice->invoice_no }}</td>
                                    <td>{{ $v->invoice->paidPercent() }}%</td>
                                    <td>¥{{ number_format($v->invoice->balance()) }}</td>
                                    <td><span class="badge bg-secondary">Available</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="{{ route('shipments.show', $shipment) }}" class="btn btn-light">Cancel</a>
                </form>
            </div>
        </section>
    </div>
</div>
@endsection