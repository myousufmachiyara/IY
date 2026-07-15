@extends('layouts.app')

@section('title', 'Accounting | Chart of Accounts')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Accounting</h2></header>

            @include('accounting._tabs', ['active' => 'chart'])

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr><th>Code</th><th>Name</th><th>Type</th><th class="text-end">Current Balance</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $a)
                            <tr>
                                <td><code>{{ $a->code }}</code></td>
                                <td>{{ $a->name }}</td>
                                <td><span class="badge bg-secondary text-uppercase">{{ $a->type }}</span></td>
                                <td class="text-end fw-bold {{ $a->current_balance < 0 ? 'text-danger' : '' }}">¥{{ number_format($a->current_balance) }}</td>
                                <td><a href="{{ route('accounting.ledger', $a) }}" class="btn btn-sm btn-outline-secondary">View Ledger</a></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection