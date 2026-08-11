@extends('layouts.app')
@section('title', 'Bidding Results | Lost')
@section('content')
@php
    $isPrivileged = auth()->user()->can('data.view_all');
    $canEdit = auth()->user()->can('bid_results.edit');
    $colCount = 5 + ($isPrivileged ? 1 : 0) + ($canEdit ? 1 : 0);
@endphp
<div class="row"><div class="col"><section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <header class="card-header"><h2 class="card-title">Bidding Results</h2></header>
    @include('results._tabs', ['active' => 'lost'])

    <div class="card-body">
        @if($isPrivileged && $agents->isNotEmpty())
        <form method="GET" action="{{ route('results.lost') }}" class="row g-2 mb-3">
            <div class="col-auto">
                <select name="agent_ids[]" data-plugin-selecttwo class="form-select select2-js" multiple>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" @selected(in_array($agent->id, (array) request('agent_ids')))>
                            {{ $agent->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-secondary btn-sm">Filter</button>
                @if(request('agent_ids'))
                    <a href="{{ route('results.lost') }}" class="btn btn-link btn-sm">Reset</a>
                @endif
            </div>
        </form>
        @endif

        <div class="table-scroll">
            <table class="table table-bordered table-striped mb-0" id="datatable-default">
                <thead>
                    <tr>
                        <th>Lot</th><th>Customer</th><th>Vehicle</th><th>Bid Amount</th>
                        @if($isPrivileged)<th>Agent</th>@endif
                        <th>Lost On</th>
                        @if($canEdit)<th>Action</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($bids as $b)
                    <tr>
                        <td>{{ $b->lot_no ?? '—' }}</td>
                        <td>{{ $b->customer->name ?? '—' }}</td>
                        <td>
                            @if($b->vehicle)
                                <a href="{{ route('vehicles.show', $b->vehicle) }}">{{ trim("{$b->year} {$b->make} {$b->model}") ?: '—' }}</a>
                            @else
                                {{ trim("{$b->year} {$b->make} {$b->model}") ?: '—' }}
                            @endif
                        </td>
                        <td>¥{{ number_format($b->max_bid) }}</td>
                        @if($isPrivileged)<td>{{ $b->agent->name ?? '—' }}</td>@endif
                        <td>{{ optional($b->updated_at)->format('d-m-Y') ?? '—' }}</td>
                        @if($canEdit)
                        <td>
                            <form action="{{ route('bids.undo_lost', $b) }}" method="POST" onsubmit="return confirm('Revert this bid back to pending?');">
                                @csrf<button class="btn btn-sm btn-outline-warning">Undo Lost</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty<tr><td colspan="{{ $colCount }}" class="text-center text-muted py-4">No lost bids yet.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</section></div></div>
@endsection