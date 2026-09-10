@extends('layouts.app')

@section('title', 'Reports | Vehicle Profitability')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Reports</h2>
                <div>
                    <a href="{{ route('reports.vehicle_profitability', ['export' => 'excel']) }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="{{ route('reports.vehicle_profitability', ['export' => 'pdf']) }}" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </header>

            @include('reports._tabs', ['active' => 'vehicle_profitability'])

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Customer</th>
                                <th>Agent</th>
                                <th class="text-end">Buying Price</th>
                                <th class="text-end">Total Costing</th>
                                <th class="text-end">Selling Price</th>
                                <th class="text-end">Profit</th>
                                <th class="text-end">Company Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                            <tr>
                                <td>{{ $r['vehicle'] }}</td>
                                <td>{{ $r['customer'] }}</td>
                                <td>{{ $r['agent'] }}</td>
                                <td class="text-end">¥{{ number_format($r['buying_price']) }}</td>
                                <td class="text-end">¥{{ number_format($r['total_costing']) }}</td>
                                <td class="text-end">¥{{ number_format($r['selling_price']) }}</td>
                                <td class="text-end {{ $r['profit'] < 0 ? 'text-danger' : '' }}">¥{{ number_format($r['profit']) }}</td>
                                <td class="text-end fw-bold {{ $r['company_profit'] < 0 ? 'text-danger' : '' }}">¥{{ number_format($r['company_profit']) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No won vehicles found.</td></tr>
                            @endforelse
                        </tbody>
                        @if($rows->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="3">Total</td>
                                <td class="text-end">¥{{ number_format($rows->sum('buying_price')) }}</td>
                                <td class="text-end">¥{{ number_format($rows->sum('total_costing')) }}</td>
                                <td class="text-end">¥{{ number_format($rows->sum('selling_price')) }}</td>
                                <td class="text-end">¥{{ number_format($rows->sum('profit')) }}</td>
                                <td class="text-end">¥{{ number_format($rows->sum('company_profit')) }}</td>
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
