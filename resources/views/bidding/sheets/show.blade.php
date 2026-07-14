@extends('layouts.app')

@section('title', 'Bid Sheet | ' . $sheet->title)

@section('content')

@php
    $resultColors = ['pending' => 'warning text-dark', 'won' => 'success', 'lost' => 'danger'];
@endphp

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">{{ $sheet->title }}</h2>
                <a href="{{ route('bid-sheets.index') }}" class="btn btn-sm btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Bid Sheets
                </a>
            </header>

            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Agent:</strong> {{ $sheet->agent->name ?? '—' }}</div>
                    <div class="col-md-4"><strong>Auction Date:</strong> {{ optional($sheet->auction_date)->format('d-m-Y') ?? '—' }}</div>
                    <div class="col-md-4"><strong>Rows Imported:</strong> {{ $sheet->rows_count }}</div>
                </div>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Lot</th>
                                <th>Auction House</th>
                                <th>Vehicle</th>
                                <th>Chassis</th>
                                <th>Customer</th>
                                <th>Max Bid</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sheet->bids as $b)
                            <tr>
                                <td>{{ $b->lot_no ?? '—' }}</td>
                                <td>{{ $b->auction_house ?? '—' }}</td>
                                <td>{{ trim("{$b->year} {$b->make} {$b->model}") ?: '—' }}</td>
                                <td>{{ $b->chassis_no ?? '—' }}</td>
                                <td>{{ $b->customer->name ?? '—' }}</td>
                                <td>¥{{ number_format($b->max_bid) }}</td>
                                <td><span class="badge bg-{{ $resultColors[$b->result] ?? 'secondary' }} text-uppercase">{{ $b->result }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No rows imported from this sheet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can('results.index')
                    <p class="text-muted small mt-3 mb-0">
                        <i class="fa fa-info-circle"></i> Pending bids are marked won or lost from the
                        <a href="{{ route('results.index') }}">Bidding Results</a> screen.
                    </p>
                @endcan
            </div>
        </section>
    </div>
</div>
@endsection