@extends('layouts.app')

@section('title', 'Reports | Customer Profitability')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Reports</h2>
                <div>
                    <a href="{{ route('reports.customer_profitability', ['export' => 'excel']) }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="{{ route('reports.customer_profitability', ['export' => 'pdf']) }}" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </header>

            @include('reports._tabs', ['active' => 'customer_profitability'])

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Agent</th>
                                <th class="text-end">Vehicles Won</th>
                                <th class="text-end">Gross Profit</th>
                                <th class="text-end">Company Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                            <tr>
                                <td>{{ $r['customer'] }}</td>
                                <td>{{ $r['agent'] }}</td>
                                <td class="text-end">{{ $r['vehicles_won'] }}</td>
                                <td class="text-end {{ $r['gross_profit'] < 0 ? 'text-danger' : '' }}">¥{{ number_format($r['gross_profit']) }}</td>
                                <td class="text-end fw-bold {{ $r['company_profit'] < 0 ? 'text-danger' : '' }}">¥{{ number_format($r['company_profit']) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No customers with won vehicles yet.</td></tr>
                            @endforelse
                        </tbody>
                        @if($rows->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="2">Total</td>
                                <td class="text-end">{{ $rows->sum('vehicles_won') }}</td>
                                <td class="text-end">¥{{ number_format($rows->sum('gross_profit')) }}</td>
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
