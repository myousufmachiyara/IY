@extends('layouts.app')
@section('title', 'Sold Vehicles')
@section('content')
@php $statusColors = ['invoiced'=>'primary','dispatched'=>'warning text-dark','arrived'=>'warning text-dark','delivered'=>'success']; @endphp
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Sold Vehicles</h2></header>
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-3">
                <select name="customer_id" class="form-control select2-js" onchange="this.form.submit()">
                    <option value="">All Customers</option>
                    @foreach($customers as $c)<option value="{{ $c->id }}" @selected(request('customer_id')==$c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="from" class="form-control" value="{{ request('from') }}"></div>
            <div class="col-md-2"><input type="date" name="to" class="form-control" value="{{ request('to') }}"></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
        </form>
        <div class="table-scroll">
            <table class="table table-bordered table-striped mb-0" id="datatable-default">
                <thead><tr><th>Vehicle</th><th>Customer</th><th>Won On</th><th>Status</th><th>Invoice</th></tr></thead>
                <tbody>
                    @forelse($vehicles as $v)
                    <tr>
                        <td><a href="{{ route('vehicles.show', $v) }}">{{ $v->label() }}</a></td>
                        <td><a href="{{ route('customers.show', $v->customer) }}">{{ $v->customer->name }}</a></td>
                        <td>{{ optional($v->won_at)->format('d-m-Y') ?? '—' }}</td>
                        <td><span class="badge bg-{{ $statusColors[$v->status] ?? 'secondary' }} text-uppercase">{{ $v->status }}</span></td>
                        <td>@if($v->invoice)<a href="{{ route('invoices.show', $v->invoice) }}">{{ $v->invoice->invoice_no }}</a>@else<span class="text-muted">—</span>@endif</td>
                    </tr>
                    @empty<tr><td colspan="5" class="text-center text-muted py-4">No sold vehicles yet.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</section></div></div>
@endsection