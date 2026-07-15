@extends('layouts.app')

@section('title', 'Ledger | ' . $account->name)

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">{{ $account->name }} <span class="text-muted">({{ $account->code }})</span></h2>
                <a href="{{ route('accounting.chart') }}" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Back to Chart of Accounts</a>
            </header>

            <div class="card-body">
                <form method="GET" action="{{ route('accounting.ledger', $account) }}" class="row g-2 mb-3">
                    <div class="col-md-3"><input type="date" name="from" class="form-control" value="{{ request('from') }}" title="From"></div>
                    <div class="col-md-3"><input type="date" name="to" class="form-control" value="{{ request('to') }}" title="To"></div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary">Filter</button>
                        @if(request()->anyFilled(['from','to']))
                            <a href="{{ route('accounting.ledger', $account) }}" class="btn btn-light">Clear</a>
                        @endif
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr><th>Date</th><th>Entry #</th><th>Memo</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Running Balance</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $l)
                            <tr>
                                <td>{{ $l->entry->date->format('d-m-Y') }}</td>
                                <td>{{ $l->entry->entry_no }}</td>
                                <td>{{ $l->memo ?? $l->entry->description }}</td>
                                <td class="text-end">{{ $l->debit > 0 ? '¥'.number_format($l->debit) : '' }}</td>
                                <td class="text-end">{{ $l->credit > 0 ? '¥'.number_format($l->credit) : '' }}</td>
                                <td class="text-end fw-bold {{ $l->running_balance < 0 ? 'text-danger' : '' }}">¥{{ number_format($l->running_balance) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No activity in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection