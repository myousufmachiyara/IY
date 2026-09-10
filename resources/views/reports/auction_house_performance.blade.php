@extends('layouts.app')

@section('title', 'Reports | Auction House Performance')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Reports</h2>
                <div>
                    <a href="{{ route('reports.auction_house_performance', ['export' => 'excel']) }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="{{ route('reports.auction_house_performance', ['export' => 'pdf']) }}" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </header>

            @include('reports._tabs', ['active' => 'auction_house_performance'])

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Auction House / Vendor</th>
                                <th>Location</th>
                                <th class="text-end">Vehicles Won</th>
                                <th class="text-end">Avg Buying Price</th>
                                <th class="text-end">Total Costing</th>
                                <th class="text-end">Avg Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                            <tr>
                                <td>{{ $r['vendor'] }}</td>
                                <td>{{ $r['location'] ?? '—' }}</td>
                                <td class="text-end">{{ $r['vehicles_won'] }}</td>
                                <td class="text-end">¥{{ number_format($r['avg_buying_price']) }}</td>
                                <td class="text-end">¥{{ number_format($r['total_costing']) }}</td>
                                <td class="text-end {{ $r['avg_profit'] < 0 ? 'text-danger' : '' }}">¥{{ number_format($r['avg_profit']) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No vendors found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
