@extends('layouts.app')

@section('title', 'Reports | Agent-wise')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Reports</h2>
                <div>
                    <a href="{{ route('reports.agent_wise', ['export' => 'excel']) }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="{{ route('reports.agent_wise', ['export' => 'pdf']) }}" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </header>

            @include('reports._tabs', ['active' => 'agent_wise'])

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th class="text-end">Total Bids</th>
                                <th class="text-end">Bids Won</th>
                                <th class="text-end">Vehicles Won</th>
                                <th class="text-end">Profit</th>
                                <th class="text-end">Earnings</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                            <tr>
                                <td>{{ $r['agent'] }}</td>
                                <td class="text-end">{{ $r['total_bids'] }}</td>
                                <td class="text-end">{{ $r['bids_won'] }}</td>
                                <td class="text-end">{{ $r['vehicles_won'] }}</td>
                                <td class="text-end {{ $r['profit'] < 0 ? 'text-danger' : '' }}">¥{{ number_format($r['profit']) }}</td>
                                <td class="text-end text-success">¥{{ number_format($r['earnings']) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No sales agents found.</td></tr>
                            @endforelse
                        </tbody>
                        @if($rows->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold">
                                <td>Total</td>
                                <td class="text-end">{{ $rows->sum('total_bids') }}</td>
                                <td class="text-end">{{ $rows->sum('bids_won') }}</td>
                                <td class="text-end">{{ $rows->sum('vehicles_won') }}</td>
                                <td class="text-end">¥{{ number_format($rows->sum('profit')) }}</td>
                                <td class="text-end">¥{{ number_format($rows->sum('earnings')) }}</td>
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