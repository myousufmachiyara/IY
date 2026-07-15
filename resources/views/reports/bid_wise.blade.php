@extends('layouts.app')

@section('title', 'Reports | Bid-wise')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Reports</h2>
                <div>
                    <a href="{{ route('reports.bid_wise', ['export' => 'excel']) }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="{{ route('reports.bid_wise', ['export' => 'pdf']) }}" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </header>

            @include('reports._tabs', ['active' => 'bid_wise'])

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Auction Date</th>
                                <th class="text-end">Total Bids</th>
                                <th class="text-end">Won</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($r['date'])->format('d-m-Y') }}</td>
                                <td class="text-end">{{ $r['total_bids'] }}</td>
                                <td class="text-end text-success">{{ $r['won'] }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No bid activity recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                        @if($rows->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold">
                                <td>Total</td>
                                <td class="text-end">{{ $rows->sum('total_bids') }}</td>
                                <td class="text-end">{{ $rows->sum('won') }}</td>
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