@extends('layouts.app')
@section('title', 'Accounting | Expense Analysis')
@section('content')
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Accounting</h2></header>
    @include('accounting._tabs', ['active' => 'expense_analysis'])
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-2"><input type="date" name="from" class="form-control" value="{{ $from }}"></div>
            <div class="col-md-2"><input type="date" name="to" class="form-control" value="{{ $to }}"></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
        </form>
        <h5 class="mb-3">Total Expenses: ¥{{ number_format($totalExpense) }}</h5>
        <div class="table-scroll">
            <table class="table table-bordered table-striped mb-0">
                <thead><tr><th>Category</th><th class="text-end">Count</th><th class="text-end">Total</th><th class="text-end">% of Total</th></tr></thead>
                <tbody>
                    @forelse($byCategory as $c)
                    <tr>
                        <td class="text-capitalize">{{ $c->category }}</td>
                        <td class="text-end">{{ $c->count }}</td>
                        <td class="text-end">¥{{ number_format($c->total) }}</td>
                        <td class="text-end">{{ $totalExpense > 0 ? round($c->total / $totalExpense * 100, 1) : 0 }}%</td>
                    </tr>
                    @empty<tr><td colspan="4" class="text-center text-muted py-4">No expenses in this range.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</section></div></div>
@endsection
