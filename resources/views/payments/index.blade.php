@extends('layouts.app')

@section('title', 'Payments | All Payments')

@section('content')

<div class="row">
    <div class="col">
        <section class="card">

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <header class="card-header">
                <h2 class="card-title">All Payments</h2>
            </header>

            <div class="card-body">
                <div class="alert alert-light border py-2 mb-3">
                    <i class="fa fa-info-circle text-muted"></i>
                    <span class="text-muted">Payments are recorded against an invoice or from a customer's Ledger tab, not created here — this is the full audit list across every customer.</span>
                </div>

                <form method="GET" action="{{ route('payments.index') }}" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <select name="customer_id" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Customers</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="method" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Methods</option>
                            <option value="cash" {{ request('method') === 'cash' ? 'selected' : '' }}>Cash</option>
                            <option value="bank" {{ request('method') === 'bank' ? 'selected' : '' }}>Bank</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="from" class="form-control" value="{{ request('from') }}" title="Paid from">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="to" class="form-control" value="{{ request('to') }}" title="Paid to">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary">Filter</button>
                        @if(request()->anyFilled(['customer_id','method','from','to']))
                            <a href="{{ route('payments.index') }}" class="btn btn-light">Clear</a>
                        @endif
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Invoice</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Recorded By</th>
                                <th>Backdated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $p)
                            <tr>
                                <td>{{ $p->paid_at->format('d-m-Y') }}</td>
                                <td><a href="{{ route('payments.customer_ledger', $p->customer) }}">{{ $p->customer->name }}</a></td>
                                <td>
                                    @if($p->invoice)
                                        <a href="{{ route('invoices.show', $p->invoice) }}">{{ $p->invoice->invoice_no }}</a>
                                    @else
                                        <span class="text-muted">General / Total Balance</span>
                                    @endif
                                </td>
                                <td>¥{{ number_format($p->amount) }}</td>
                                <td class="text-capitalize">{{ $p->method }}</td>
                                <td>{{ $p->reference ?? '—' }}</td>
                                <td>{{ $p->recorder->name ?? '—' }}</td>
                                <td>{{ $p->is_backdated ? 'Yes' : '—' }}</td>
                                <td class="text-nowrap">
                                    @can('payments.edit')
                                        <a href="#" class="text-primary me-1" title="Edit"
                                           onclick="editPayment({{ $p->id }}, {{ $p->amount }}, '{{ $p->method }}', '{{ $p->paid_at->format('Y-m-d') }}', '{{ $p->reference }}')">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endcan
                                    @can('payments.delete')
                                        <form action="{{ route('payments.destroy', $p) }}" method="POST" style="display:inline;"
                                              onsubmit="return confirm('Delete this payment? The ledger entry will be reversed.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-link p-0 text-danger"><i class="fa fa-trash-alt"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No payments match this filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @include('payments._edit_modal')
    </div>
</div>
@endsection