@extends('layouts.app')
@section('title', 'Accounting | Balance Sheet')
@section('content')
@php $totalEquity = $totalEquityBase + $netProfit; @endphp
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Accounting</h2></header>
    @include('accounting._tabs', ['active' => 'balance_sheet'])
    <div class="card-body">
        <p class="text-muted small">As of {{ now()->format('d-m-Y') }}. Net profit is shown as an unposted equity line since no formal period-close process runs yet.</p>
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small mb-2">Assets</h6>
                <table class="table table-sm table-borderless mb-3">
                    @foreach($assets as $r)<tr><td>{{ $r['account']->name }}</td><td class="text-end">¥{{ number_format($r['amount']) }}</td></tr>@endforeach
                    <tr class="fw-bold border-top"><td>Total Assets</td><td class="text-end">¥{{ number_format($totalAssets) }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small mb-2">Liabilities</h6>
                <table class="table table-sm table-borderless mb-3">
                    @foreach($liabilities as $r)<tr><td>{{ $r['account']->name }}</td><td class="text-end">¥{{ number_format($r['amount']) }}</td></tr>@endforeach
                    <tr class="fw-bold border-top"><td>Total Liabilities</td><td class="text-end">¥{{ number_format($totalLiabilities) }}</td></tr>
                </table>

                <h6 class="text-muted text-uppercase small mb-2">Equity</h6>
                <table class="table table-sm table-borderless mb-3">
                    @foreach($equity as $r)<tr><td>{{ $r['account']->name }}</td><td class="text-end">¥{{ number_format($r['amount']) }}</td></tr>@endforeach
                    <tr><td>Net Profit (undistributed)</td><td class="text-end">¥{{ number_format($netProfit) }}</td></tr>
                    <tr class="fw-bold border-top"><td>Total Equity</td><td class="text-end">¥{{ number_format($totalEquity) }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card bg-light border">
            <div class="card-body text-center">
                @php $balanced = $totalAssets == ($totalLiabilities + $totalEquity); @endphp
                <h5 class="mb-0">
                    Assets (¥{{ number_format($totalAssets) }}) {{ $balanced ? '=' : '≠' }} Liabilities + Equity (¥{{ number_format($totalLiabilities + $totalEquity) }})
                    <span class="{{ $balanced ? 'text-success' : 'text-danger' }} ms-2">{{ $balanced ? '✓ Balanced' : '✗ Out of Balance' }}</span>
                </h5>
            </div>
        </div>
    </div>
</section></div></div>
@endsection