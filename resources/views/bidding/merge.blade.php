@extends('layouts.app')

@section('title', 'Bidding | Merge & Export')

@section('content')

@php
    $exportColumns = [
        'lot_no' => 'Lot', 'auction_house' => 'Auction House', 'auction_date' => 'Date',
        'agent' => 'Agent', 'customer' => 'Customer', 'make' => 'Make', 'model' => 'Model',
        'year' => 'Year', 'fuel_type' => 'Fuel', 'color' => 'Color', 'engine' => 'Engine',
        'chassis_no' => 'Chassis', 'max_bid' => 'Max Bid', 'priority' => 'Priority', 'result' => 'Result',
    ];
@endphp

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Merge &amp; Export — All Agents</h2>
            </header>

            <div class="card-body">
                <form method="GET" action="{{ route('bids.index') }}" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label>Sales Agent(s)</label>
                        <select name="agent_ids[]" class="form-control select2-js" multiple>
                            @foreach($agents as $a)
                                <option value="{{ $a->id }}" @selected(in_array($a->id, request('agent_ids', [])))>{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Status</label>
                        <select name="result" class="form-control select2-js">
                            <option value="">All</option>
                            <option value="pending" @selected(request('result')==='pending')>Pending</option>
                            <option value="won" @selected(request('result')==='won')>Won</option>
                            <option value="lost" @selected(request('result')==='lost')>Lost</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>From Date</label>
                        <input type="date" name="from" class="form-control" value="{{ request('from') }}">
                    </div>
                    <div class="col-md-2">
                        <label>To Date</label>
                        <input type="date" name="to" class="form-control" value="{{ request('to') }}">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-outline-secondary">Filter</button>
                    </div>
                </form>

                @can('merge_bids.print')
                <form method="GET" action="{{ route('bids.export') }}" class="mb-3 border rounded p-3 bg-light">
                    <input type="hidden" name="agent_id" value="{{ request('agent_id') }}">
                    <input type="hidden" name="from" value="{{ request('from') }}">
                    <input type="hidden" name="to" value="{{ request('to') }}">
                    <input type="hidden" name="result" value="{{ request('result') }}">

                    <label class="fw-bold small text-uppercase mb-2 d-block">Columns to Export</label>
                    <div class="row">
                        @foreach($exportColumns as $key => $label)
                        <div class="col-md-2 mb-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="columns[]" value="{{ $key }}" id="col_{{ $key }}" checked>
                                <label class="form-check-label" for="col_{{ $key }}">{{ $label }}</label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-success mt-2">
                        <i class="fas fa-file-excel"></i> Export Selected Columns
                    </button>
                </form>
                @endcan

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
                                <th>Priority</th>
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
                                <td>{{ $b->priority ?? '—' }}</td>
                                <td>¥{{ number_format($b->max_bid) }}</td>
                                <td>
                                    @php $c = ['pending'=>'warning text-dark','won'=>'success','lost'=>'danger'][$b->result] ?? 'secondary'; @endphp
                                    <span class="badge bg-{{ $c }} text-uppercase">{{ $b->result }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No bids match this filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection