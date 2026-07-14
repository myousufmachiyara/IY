@extends('layouts.app')

@section('title', 'Prepare Shipment | ' . $customer->name)

@section('content')

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Prepare Shipment — {{ $customer->name }}</h2>
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Customer
                </a>
            </header>

            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif

                <form method="POST" action="{{ route('shipments.store') }}">
                    @csrf
                    <input type="hidden" name="customer_id" value="{{ $customer->id }}">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Shipment Method <span class="text-danger">*</span></label>
                            <select class="form-control select2-js" name="method" required>
                                <option value="RORO">RORO</option>
                                <option value="Container">Container</option>
                            </select>
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase small mb-2">Select Vehicles to Dispatch</h6>
                    <p class="text-muted small">Only vehicles with at least 50% paid are eligible. Select one or more to batch them into this shipment.</p>

                    <div class="table-scroll mb-3">
                        <table class="table table-bordered table-striped mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>Vehicle</th>
                                    <th>Invoice</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($eligible as $v)
                                <tr>
                                    <td><input type="checkbox" name="vehicle_ids[]" value="{{ $v->id }}"></td>
                                    <td>{{ $v->label() }}</td>
                                    <td>{{ $v->invoice->invoice_no }}</td>
                                    <td>{{ $v->invoice->paidPercent() }}%</td>
                                    <td>¥{{ number_format($v->invoice->balance()) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="btn btn-primary">Create Shipment</button>
                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-light">Cancel</a>
                </form>
            </div>
        </section>
    </div>
</div>
@endsection