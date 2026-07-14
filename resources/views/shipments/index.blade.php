@extends('layouts.app')

@section('title', 'Shipments | All Shipments')

@section('content')

@php $statusColors = ['preparing' => 'warning text-dark', 'dispatched' => 'info', 'arrived' => 'success']; @endphp

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
                <h2 class="card-title">All Shipments</h2>
            </header>

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Method</th>
                                <th>Vehicles</th>
                                <th>Shipment Date</th>
                                <th>Expected Arrival</th>
                                <th>Freight Total</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shipments as $s)
                            <tr>
                                <td><a href="{{ route('customers.show', $s->customer) }}">{{ $s->customer->name }}</a></td>
                                <td>{{ $s->method }}</td>
                                <td>{{ $s->vehicles->count() }}</td>
                                <td>{{ optional($s->shipment_date)->format('d-m-Y') ?? '—' }}</td>
                                <td>{{ optional($s->expected_arrival)->format('d-m-Y') ?? '—' }}</td>
                                <td>¥{{ number_format($s->freight_total) }}</td>
                                <td><span class="badge bg-{{ $statusColors[$s->status] ?? 'secondary' }} text-uppercase">{{ $s->status }}</span></td>
                                <td class="text-nowrap">
                                <a href="{{ route('shipments.show', $s) }}" class="text-secondary me-1" title="View">
                                    <i class="fa fa-eye"></i>
                                </a>
                                @can('shipments.edit')
                                    @if($s->status === 'preparing')
                                        <a href="{{ route('shipments.edit', $s) }}" class="text-primary" title="Edit">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                @endcan
                            </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No shipments yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <p class="text-muted small mt-3 mb-0">
                    <i class="fa fa-info-circle"></i> New shipments are prepared from a customer's page once their vehicle(s) reach 50% payment —
                    look for "Prepare Shipment" on the vehicle detail page.
                </p>
            </div>
        </section>
    </div>
</div>
@endsection