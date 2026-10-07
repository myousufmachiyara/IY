@extends('layouts.app')

@section('title', 'Accounting | Profit & Loss')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Accounting</h2></header>

            @include('accounting._tabs', ['active' => 'profit_loss'])

            <div class="card-body">
                <form method="GET" action="{{ route('accounting.profit_loss') }}" class="row g-2 mb-4">
                    <div class="col-md-3"><label class="small text-muted mb-1">From</label><input type="date" name="from" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label class="small text-muted mb-1">To</label><input type="date" name="to" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-secondary">Update Range</button></div>
                </form>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small mb-2">Income</h6>
                        <table class="table table-sm table-borderless mb-3">
                            @forelse ($income as $g)
                                <tr class="fw-semibold"><td>{{ $g['name'] }}</td><td class="text-end">¥{{ number_format($g['amount']) }}</td></tr>
                                @foreach($g['rows'] as $r)<tr class="text-muted"><td class="ps-4">{{ $r['name'] }}</td><td class="text-end">¥{{ number_format($r['amount']) }}</td></tr>@endforeach
                            @empty
                                <tr><td colspan="2" class="text-muted">No income recognised in this period.</td></tr>
                            @endforelse
                            <tr class="fw-bold border-top"><td>Total Income</td><td class="text-end">¥{{ number_format($totalIncome) }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small mb-2">Expenses</h6>
                        <table class="table table-sm table-borderless mb-3">
                            @forelse ($expense as $g)
                                <tr class="fw-semibold"><td>{{ $g['name'] }}</td><td class="text-end">¥{{ number_format($g['amount']) }}</td></tr>
                                @foreach($g['rows'] as $r)<tr class="text-muted"><td class="ps-4">{{ $r['name'] }}</td><td class="text-end">¥{{ number_format($r['amount']) }}</td></tr>@endforeach
                            @empty
                                <tr><td colspan="2" class="text-muted">No expenses in this period.</td></tr>
                            @endforelse
                            <tr class="fw-bold border-top"><td>Total Expenses</td><td class="text-end">¥{{ number_format($totalExpense) }}</td></tr>
                        </table>
                    </div>
                </div>

                <div class="card bg-light border">
                    <div class="card-body text-center">
                        <h5 class="mb-0">
                            Net Profit:
                            <span class="{{ $netProfit >= 0 ? 'text-success' : 'text-danger' }}">¥{{ number_format($netProfit) }}</span>
                        </h5>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0">Income is recognised as customer money is received, not when an invoice is issued (see Account Mapping → Sale invoice credit side).</p>
            </div>
        </section>
    </div>
</div>
@endsection
