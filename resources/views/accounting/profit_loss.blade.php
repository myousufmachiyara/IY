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
                    <div class="col-md-3"><input type="date" name="from" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><input type="date" name="to" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-3"><button class="btn btn-outline-secondary">Update Range</button></div>
                </form>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small mb-2">Income</h6>
                        <table class="table table-sm table-borderless mb-3">
                            @foreach ($income as $row)
                            <tr><td>{{ $row['account']->name }}</td><td class="text-end">¥{{ number_format($row['amount']) }}</td></tr>
                            @endforeach
                            <tr class="fw-bold border-top"><td>Total Income</td><td class="text-end">¥{{ number_format($totalIncome) }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small mb-2">Expenses</h6>
                        <table class="table table-sm table-borderless mb-3">
                            @foreach ($expense as $row)
                            <tr><td>{{ $row['account']->name }}</td><td class="text-end">¥{{ number_format($row['amount']) }}</td></tr>
                            @endforeach
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
            </div>
        </section>
    </div>
</div>
@endsection