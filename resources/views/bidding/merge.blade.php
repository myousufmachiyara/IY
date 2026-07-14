@extends('layouts.app')

@section('title', 'Bidding | Merge & Export')

@section('content')

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Merge &amp; Export — All Agents</h2>
            </header>

            <div class="card-body">
                <form method="GET" action="{{ route('bids.index') }}" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label>Sales Agent</label>
                        <select name="agent_id" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Agents</option>
                            @foreach($agents as $a)
                                <option value="{{ $a->id }}" {{ request('agent_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from" class="form-control" value="{{ request('from') }}">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to" class="form-control" value="{{ request('to') }}">
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button class="btn btn-outline-secondary">Filter</button>
                        @can('bids.print')
                            <a href="{{ route('bids.export', request()->query()) }}" class="btn btn-success">
                                <i class="fas fa-file-excel"></i> Export
                            </a>
                        @endcan
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Lot</th>
                                <th>Auction House</th>
                                <th>Date</th>
                                <th>Agent</th>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                <th>Chassis</th>
                                <th>Max Bid</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bids as $b)
                            <tr>
                                <td>{{ $b->lot_no ?? '—' }}</td>
                                <td>{{ $b->auction_house ?? '—' }}</td>
                                <td>{{ optional($b->auction_date)->format('d-m-Y') ?? '—' }}</td>
                                <td>{{ $b->agent->name ?? '—' }}</td>
                                <td>{{ $b->customer->name ?? '—' }}</td>
                                <td>{{ trim("{$b->year} {$b->make} {$b->model}") ?: '—' }}</td>
                                <td>{{ $b->chassis_no ?? '—' }}</td>
                                <td>¥{{ number_format($b->max_bid) }}</td>
                                <td>
                                    @php $c = ['pending'=>'warning text-dark','won'=>'success','lost'=>'danger'][$b->result] ?? 'secondary'; @endphp
                                    <span class="badge bg-{{ $c }} text-uppercase">{{ $b->result }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No bids match this filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection