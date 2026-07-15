@extends('layouts.app')

@section('title', 'Accounting | Cash & Bank Book')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Accounting</h2></header>

            @include('accounting._tabs', ['active' => 'cash_bank'])

            <div class="card-body">
                <form method="GET" action="{{ route('accounting.cash_bank') }}" class="row g-2 mb-3">
                    <div class="col-md-3"><input type="date" name="from" class="form-control" value="{{ request('from') }}" title="From"></div>
                    <div class="col-md-3"><input type="date" name="to" class="form-control" value="{{ request('to') }}" title="To"></div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary">Filter</button>
                        @if(request()->anyFilled(['from','to']))
                            <a href="{{ route('accounting.cash_bank') }}" class="btn btn-light">Clear</a>
                        @endif
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr><th>Date</th><th>Account</th><th>Memo</th><th class="text-end">Debit (In)</th><th class="text-end">Credit (Out)</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $l)
                            <tr>
                                <td>{{ $l->entry->date->format('d-m-Y') }}</td>
                                <td>{{ $l->account->name }}</td>
                                <td>{{ $l->memo ?? $l->entry->description }}</td>
                                <td class="text-end text-success">{{ $l->debit > 0 ? '¥'.number_format($l->debit) : '' }}</td>
                                <td class="text-end text-danger">{{ $l->credit > 0 ? '¥'.number_format($l->credit) : '' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No cash/bank activity in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection