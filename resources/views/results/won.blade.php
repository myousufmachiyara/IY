@extends('layouts.app')
@section('title', 'Bidding Results | Won')
@section('content')
@php $isPrivileged = auth()->user()->can('data.view_all'); @endphp
<div class="row"><div class="col"><section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <header class="card-header"><h2 class="card-title">Bidding Results</h2></header>
    @include('results._tabs', ['active' => 'won'])
    <div class="card-body">
        <div class="table-scroll">
            <table class="table table-bordered table-striped mb-0" id="datatable-default">
                <thead><tr><th>Lot</th><th>Customer</th><th>Vehicle</th><th>Buying Price</th>@if($isPrivileged)<th>Agent</th>@endif<th>Won On</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($bids as $b)
                    <tr>
                        <td>{{ $b->lot_no ?? '—' }}</td>
                        <td>{{ $b->customer->name ?? '—' }}</td>
                        <td><a href="{{ route('vehicles.show', $b->vehicle) }}">{{ trim("{$b->year} {$b->make} {$b->model}") ?: '—' }}</a></td>
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
                    @empty<tr><td colspan="7" class="text-center text-muted py-4">No won bids yet.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</section></div></div>
@endsection