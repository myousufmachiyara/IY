@extends('layouts.app')

@section('title', 'Accounting | Receivables')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Accounting</h2></header>

            @include('accounting._tabs', ['active' => 'receivables'])

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr><th>Customer</th><th class="text-end">Invoiced</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($customers as $row)
                            <tr>
                                <td><a href="{{ route('customers.show', $row['customer']) }}">{{ $row['customer']->name }}</a></td>
                                <td class="text-end">¥{{ number_format($row['invoiced']) }}</td>
                                <td class="text-end text-success">¥{{ number_format($row['paid']) }}</td>
                                <td class="text-end fw-bold text-danger">¥{{ number_format($row['balance']) }}</td>
                                <td><a href="{{ route('payments.customer_ledger', $row['customer']) }}" class="btn btn-sm btn-outline-primary">View Ledger</a></td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No outstanding receivables.</td></tr>
                            @endforelse
                        </tbody>
                        @if($customers->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold">
                                <td>Total</td>
                                <td class="text-end">¥{{ number_format($customers->sum('invoiced')) }}</td>
                                <td class="text-end">¥{{ number_format($customers->sum('paid')) }}</td>
                                <td class="text-end text-danger">¥{{ number_format($customers->sum('balance')) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection