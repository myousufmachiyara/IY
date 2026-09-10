@extends('layouts.app')
@section('title', 'Accounting | Bank Book')
@section('content')
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Accounting</h2></header>
    @include('accounting._tabs', ['active' => 'bank_book'])
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-2"><input type="date" name="from" class="form-control" value="{{ request('from') }}"></div>
            <div class="col-md-2"><input type="date" name="to" class="form-control" value="{{ request('to') }}"></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
        </form>
        <div class="table-scroll">
            <table class="table table-bordered table-striped mb-0">
                <thead><tr><th>Date</th><th>Description</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                    @forelse($lines as $l)
                    <tr>
                        <td>{{ $l->entry->date->format('d-m-Y') }}</td>
                        <td>{{ $l->entry->description }}</td>
                        <td class="text-end">{{ $l->debit > 0 ? '¥'.number_format($l->debit) : '' }}</td>
                        <td class="text-end">{{ $l->credit > 0 ? '¥'.number_format($l->credit) : '' }}</td>
                        <td class="text-end fw-bold">¥{{ number_format($l->running_balance) }}</td>
                    </tr>
                    @empty<tr><td colspan="5" class="text-center text-muted py-3">No bank transactions in this range.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</section></div></div>
@endsection