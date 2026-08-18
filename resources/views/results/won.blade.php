@extends('layouts.app')
@section('title', 'Bidding Results | Won')
@section('content')
@php
    $isPrivileged = auth()->user()->can('data.view_all');
    $yardColors = ['At Yard' => 'warning text-dark', 'In Transit' => 'info', 'Delivered' => 'success'];
@endphp
<div class="row"><div class="col"><section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header"><h2 class="card-title">Bidding Results</h2></header>
    @include('results._tabs', ['active' => 'won'])
    <div class="card-body">
        @if($isPrivileged)
        <form method="GET" action="{{ route('results.won') }}" class="mb-3">
            <select data-plugin-selecttwo name="agent_ids[]" class="form-control select2-js" multiple style="max-width:400px;" onchange="this.form.submit()">
                @foreach($agents as $a)<option value="{{ $a->id }}" @selected(in_array($a->id, request('agent_ids', [])))>{{ $a->name }}</option>@endforeach
            </select>
        </form>
        @endif
        <div class="table-scroll">
            <table class="table table-bordered table-striped mb-0" id="datatable-default">
                <thead><tr><th>Lot</th><th>Customer</th><th>Vehicle</th><th>Status</th><th>Buying Price</th>@if($isPrivileged)<th>Agent</th>@endif<th>Won On</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($bids as $b)
                    @php $yard = $b->vehicle?->yardStatus(); @endphp
                    <tr>
                        <td>{{ $b->lot_no ?? '—' }}</td>
                        <td>{{ $b->customer->name ?? '—' }}</td>
                        <td><a href="{{ route('vehicles.show', $b->vehicle) }}">{{ trim("{$b->year} {$b->make} {$b->model}") ?: '—' }}</a></td>
                        <td>@if($yard)<span class="badge bg-{{ $yardColors[$yard] ?? 'secondary' }}">{{ $yard }}</span>@else<span class="text-muted">—</span>@endif</td>
                        <td>¥{{ number_format($b->won_amount) }}</td>
                        @if($isPrivileged)<td>{{ $b->agent->name ?? '—' }}</td>@endif
                        <td>{{ optional($b->vehicle?->won_at)->format('d-m-Y') ?? '—' }}</td>
                        <td>
                            @can('bid_results.edit')
                                @if(!$b->vehicle?->invoice)
                                <form action="{{ route('bids.undo_won', $b) }}" method="POST" onsubmit="return confirm('Revert this bid to pending? The vendor payable will be reversed.');">
                                    @csrf<button class="btn btn-sm btn-outline-warning">Undo Won</button>
                                </form>
                                @else
                                <span class="text-muted small" title="Cannot undo — invoice already generated"><i class="fa fa-lock"></i></span>
                                @endif
                            @endcan
                        </td>
                    </tr>
                    @empty<tr><td colspan="8" class="text-center text-muted py-4">No won bids yet.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</section></div></div>
@endsection