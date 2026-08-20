@extends('layouts.app')

@section('title', 'Payments | All Payments')

@section('content')

@php $statusColors = ['pending'=>'warning text-dark','approved'=>'success','rejected'=>'danger']; @endphp

<div class="row">
    <div class="col">
        <section class="card">
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

            <header class="card-header"><h2 class="card-title">All Payments</h2></header>

            <div class="card-body">
                <div class="alert alert-light border py-2 mb-3">
                    <i class="fa fa-info-circle text-muted"></i>
                    <span class="text-muted">Payments are recorded against an invoice or from a customer's Ledger tab, not created here — this is the full audit list.</span>
                </div>

                <form method="GET" action="{{ route('payments.index') }}" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <select name="customer_id" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Customers</option>
                            @foreach($customers as $c)<option value="{{ $c->id }}" @selected(request('customer_id')==$c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="method" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Methods</option>
                            <option value="cash" @selected(request('method')==='cash')>Cash</option>
                            <option value="bank" @selected(request('method')==='bank')>Bank</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="pending" @selected(request('status')==='pending')>Pending</option>
                            <option value="approved" @selected(request('status')==='approved')>Approved</option>
                            <option value="rejected" @selected(request('status')==='rejected')>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2"><input type="date" name="from" class="form-control" value="{{ request('from') }}" title="Paid from"></div>
                    <div class="col-md-2"><input type="date" name="to" class="form-control" value="{{ request('to') }}" title="Paid to"></div>
                    <div class="col-md-1"><button class="btn btn-outline-secondary w-100">Filter</button></div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Date</th><th>Customer</th><th>Invoice</th><th>Amount</th><th>Method</th>
                                <th>Status</th><th>Attachment</th><th>Recorded By</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $p)
                            <tr>
                                <td>{{ $p->paid_at->format('d-m-Y') }}</td>
                                <td><a href="{{ route('payments.customer_ledger', $p->customer) }}">{{ $p->customer->name }}</a></td>
                                <td>
                                    @if($p->invoice)<a href="{{ route('invoices.show', $p->invoice) }}">{{ $p->invoice->invoice_no }}</a>
                                    @else <span class="text-muted">General / Total Balance</span> @endif
                                </td>
                                <td>¥{{ number_format($p->amount) }}</td>
                                <td class="text-capitalize">{{ $p->method }}</td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$p->status] ?? 'secondary' }}">{{ ucfirst($p->status) }}</span>
                                    @if($p->status === 'rejected' && $p->rejection_reason)
                                        <br><small class="text-danger" title="{{ $p->rejection_reason }}">{{ \Illuminate\Support\Str::limit($p->rejection_reason, 40) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($p->attachment_path)
                                        <a href="{{ \App\Services\PublicStorage::url($p->attachment_path) }}" target="_blank"><i class="fa fa-paperclip"></i> View</a>
                                    @else <span class="text-muted">—</span> @endif
                                </td>
                                <td>{{ $p->recorder->name ?? '—' }}</td>
                                <td class="text-nowrap">
                                    @if($p->status === 'pending')
                                        @can('pending_approvals.edit')
                                            <form action="{{ route('payments.approve', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve this payment?');">
                                                @csrf<button class="btn btn-link p-0 text-success me-1" title="Approve"><i class="fa fa-check-circle"></i></button>
                                            </form>
                                        @endcan
                                    @elseif($p->status === 'approved' && auth()->user()->isSuperAdmin())
                                        <form action="{{ route('payments.undo_approval', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Undo this approval? The ledger entry will be reversed.');">
                                            @csrf<button class="btn btn-link p-0 text-warning me-1" title="Undo Approval"><i class="fa fa-undo"></i></button>
                                        </form>
                                    @endif
                                    @can('payments.edit')
                                        <a href="#" class="text-primary me-1" title="Edit" onclick="editPayment({{ $p->id }}, {{ $p->amount }}, '{{ $p->method }}', '{{ $p->paid_at->format('Y-m-d') }}', '{{ $p->reference }}')"><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('payments.delete')
                                        <form action="{{ route('payments.destroy', $p) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this payment?');">
                                            @csrf @method('DELETE')<button type="submit" class="btn btn-link p-0 text-danger"><i class="fa fa-trash-alt"></i></button>
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