@extends('layouts.app')
@section('title', 'Bidding Results | Lost')
@section('content')
@php $isPrivileged = auth()->user()->can('data.view_all'); @endphp
<div class="row"><div class="col"><section class="card">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <header class="card-header"><h2 class="card-title">Bidding Results</h2></header>
    @include('results._tabs', ['active' => 'lost'])

    <div class="card-body">
        @if($isPrivileged && $agents->isNotEmpty())
        <form method="GET" class="row g-2 mb-3">
            <div class="col-auto">
                <select name="agent_ids[]" class="form-select select2-js" multiple>
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
                    <a href="{{ route('bids.lost') }}" class="btn btn-link btn-sm">Reset</a>
                @endif
            </div>
        </form>
        @endif

        <div class="table-scroll">
            <table class="table table-bordered table-striped mb-0" id="datatable-default">
                <thead><tr><th>Lot</th><th>Customer</th><th>Vehicle</th><th>Bid Amount</th>@if($isPrivileged)<th>Agent</th>@endif<th>Lost On</th></tr></thead>
                <tbody>
                    @forelse($bids as $b)
                    <tr>
                        <td>{{ $b->lot_no ?? '—' }}</td>
                        <td>{{ $b->customer->name ?? '—' }}</td>
                        <td>{{ trim("{$b->year} {$b->make} {$b->model}") ?: '—' }}</a></td>
                        {{-- <td><a href="{{ route('vehicles.show', $b->vehicle) }}">{{ trim("{$b->year} {$b->make} {$b->model}") ?: '—' }}</a></td> --}}
                        <td>¥{{ number_format($b->amount) }}</td>
                        @if($isPrivileged)<td>{{ $b->agent->name ?? '—' }}</td>@endif
                        <td>{{ optional($b->updated_at)->format('d-m-Y') ?? '—' }}</td>
                    </tr>
                    @empty<tr><td colspan="{{ $isPrivileged ? 6 : 5 }}" class="text-center text-muted py-4">No lost bids yet.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</section></div></div>
@endsection