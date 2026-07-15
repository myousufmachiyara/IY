@extends('layouts.app')

@section('title', 'Reports | Bid Won')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Reports</h2>
                <div>
                    <a href="{{ route('reports.bid_won', ['export' => 'excel']) }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="{{ route('reports.bid_won', ['export' => 'pdf']) }}" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </header>

            @include('reports._tabs', ['active' => 'bid_won'])

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Lot</th>
                                <th>Agent</th>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                <th class="text-end">Won Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                            <tr>
                                <td>{{ $r['lot'] ?? '—' }}</td>
                                <td>{{ $r['agent'] ?? '—' }}</td>
                                <td>{{ $r['customer'] ?? '—' }}</td>
                                <td>{{ $r['vehicle'] ?: '—' }}</td>
                                <td class="text-end">¥{{ number_format($r['amount']) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No won bids yet.</td></tr>
                            @endforelse
                        </tbody>
                        @if($rows->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4">Total</td>
                                <td class="text-end">¥{{ number_format($rows->sum('amount')) }}</td>
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