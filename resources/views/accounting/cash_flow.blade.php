@extends('layouts.app')
@section('title', 'Accounting | Cash Flow')
@section('content')
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Accounting</h2></header>
    @include('accounting._tabs', ['active' => 'cash_flow'])
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-2"><input type="date" name="from" class="form-control" value="{{ $from }}"></div>
            <div class="col-md-2"><input type="date" name="to" class="form-control" value="{{ $to }}"></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
        </form>
        <table class="table table-sm table-borderless" style="max-width:500px;">
            <tr><td>Opening Balance</td><td class="text-end">¥{{ number_format($openingBalance) }}</td></tr>
            <tr><td>Cash In (Receipts &amp; Deposits)</td><td class="text-end text-success">+¥{{ number_format($operatingIn) }}</td></tr>
            <tr><td>Cash Out (Payments &amp; Expenses)</td><td class="text-end text-danger">−¥{{ number_format($operatingOut) }}</td></tr>
            <tr class="fw-bold border-top"><td>Net Change</td><td class="text-end">¥{{ number_format($netChange) }}</td></tr>
            <tr class="fw-bold"><td>Closing Balance</td><td class="text-end">¥{{ number_format($closingBalance) }}</td></tr>
        </table>
        <p class="text-muted small mt-2">Note: this reflects direct cash/bank movement only, categorized by voucher type — not a full indirect-method cash flow statement.</p>
    </div>
</section></div></div>
@endsection