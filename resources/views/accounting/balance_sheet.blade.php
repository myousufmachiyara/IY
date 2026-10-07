@extends('layouts.app')
@section('title', 'Accounting | Balance Sheet')
@section('content')
@php
    $totalEquity = $totalEquityBase + $netProfit;
    $balanced = $totalAssets == ($totalLiabilities + $totalEquity);
@endphp
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Accounting</h2></header>
    @include('accounting._tabs', ['active' => 'balance_sheet'])
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-3"><label class="small text-muted mb-1">As of</label><input type="date" name="as_of" class="form-control" value="{{ $asOf }}"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-secondary w-100">Update</button></div>
        </form>

        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small mb-2">Assets</h6>
                <table class="table table-sm table-borderless mb-3">
                    @foreach($assets as $g)
                        <tr class="fw-semibold"><td>{{ $g['name'] }}</td><td class="text-end">¥{{ number_format($g['amount']) }}</td></tr>
                        @foreach($g['accounts'] as $a)<tr class="text-muted"><td class="ps-4">{{ $a['name'] }}</td><td class="text-end">¥{{ number_format($a['amount']) }}</td></tr>@endforeach
                    @endforeach
                    <tr class="fw-bold border-top"><td>Total Assets</td><td class="text-end">¥{{ number_format($totalAssets) }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small mb-2">Liabilities</h6>
                <table class="table table-sm table-borderless mb-3">
                    @foreach($liabilities as $g)
                        <tr class="fw-semibold"><td>{{ $g['name'] }}</td><td class="text-end">¥{{ number_format($g['amount']) }}</td></tr>
                        @foreach($g['accounts'] as $a)<tr class="text-muted"><td class="ps-4">{{ $a['name'] }}</td><td class="text-end">¥{{ number_format($a['amount']) }}</td></tr>@endforeach
                    @endforeach
                    <tr class="fw-bold border-top"><td>Total Liabilities</td><td class="text-end">¥{{ number_format($totalLiabilities) }}</td></tr>
                </table>

                <h6 class="text-muted text-uppercase small mb-2">Equity</h6>
                <table class="table table-sm table-borderless mb-3">
                    @foreach($equity as $g)
                        <tr class="fw-semibold"><td>{{ $g['name'] }}</td><td class="text-end">¥{{ number_format($g['amount']) }}</td></tr>
                        @foreach($g['accounts'] as $a)<tr class="text-muted"><td class="ps-4">{{ $a['name'] }}</td><td class="text-end">¥{{ number_format($a['amount']) }}</td></tr>@endforeach
                    @endforeach
                    <tr><td>Net Profit (current, undistributed)</td><td class="text-end">¥{{ number_format($netProfit) }}</td></tr>
                    <tr class="fw-bold border-top"><td>Total Equity</td><td class="text-end">¥{{ number_format($totalEquity) }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card bg-light border">
            <div class="card-body text-center">
                <h5 class="mb-0">
                    Assets (¥{{ number_format($totalAssets) }}) {{ $balanced ? '=' : '≠' }} Liabilities + Equity (¥{{ number_format($totalLiabilities + $totalEquity) }})
                    <span class="{{ $balanced ? 'text-success' : 'text-danger' }} ms-2">{{ $balanced ? '✓ Balanced' : '✗ Out of Balance' }}</span>
                </h5>
            </div>
        </div>
    </div>
</section></div></div>
@endsection
