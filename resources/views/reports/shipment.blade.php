@extends('layouts.app')

@section('title', 'Reports | Shipment')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Reports</h2>
                <div>
                    <a href="{{ route('reports.shipment', ['export' => 'excel']) }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="{{ route('reports.shipment', ['export' => 'pdf']) }}" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </header>

            @include('reports._tabs', ['active' => 'shipment'])

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Method</th>
                                <th>Container/BL No</th>
                                <th>Shipping Line</th>
                                <th class="text-end">Vehicles</th>
                                <th class="text-end">Freight Total</th>
                                <th>Shipment Date</th>
                                <th>ETA</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                            <tr>
                                <td>{{ $r['customer'] }}</td>
                                <td>{{ $r['method'] }}</td>
                                <td>{{ $r['reference'] }}</td>
                                <td>{{ $r['shipping_line'] ?? '—' }}</td>
                                <td class="text-end">{{ $r['vehicles'] }}</td>
                                <td class="text-end">¥{{ number_format($r['freight_total']) }}</td>
                                <td>{{ $r['shipment_date'] }}</td>
                                <td>{{ $r['eta'] }}</td>
                                <td><span class="badge bg-secondary text-uppercase">{{ $r['status'] }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No shipments found.</td></tr>
                            @endforelse
                        </tbody>
                        @if($rows->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4">Total</td>
                                <td class="text-end">{{ $rows->sum('vehicles') }}</td>
                                <td class="text-end">¥{{ number_format($rows->sum('freight_total')) }}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
