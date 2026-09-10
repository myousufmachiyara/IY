@extends('layouts.app')
@section('title', 'Accounting | Day Book')
@section('content')
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Accounting</h2></header>
    @include('accounting._tabs', ['active' => 'day_book'])
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-3">
                <select name="voucher_type" class="form-control select2-js" onchange="this.form.submit()">
                    <option value="">All Voucher Types</option>
                    @foreach($voucherTypes as $key => $label)<option value="{{ $key }}" @selected(request('voucher_type')===$key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="from" class="form-control" value="{{ request('from') }}"></div>
            <div class="col-md-2"><input type="date" name="to" class="form-control" value="{{ request('to') }}"></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
        </form>
        <div class="table-scroll">
            <table class="table table-bordered table-striped mb-0" id="datatable-default">
                <thead><tr><th>#</th><th>Voucher No</th><th>Type</th><th>Date</th><th>Description</th><th class="text-end">Amount</th><th>Print</th></tr></thead>
                <tbody>
                    @forelse($entries as $e)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $e->entry_no }}</td>
                        <td><span class="badge bg-secondary">{{ $e->voucherLabel() }}</span></td>
                        <td>{{ $e->date->format('d-m-Y') }}</td>
                        <td>{{ $e->description }}</td>
                        <td class="text-end">¥{{ number_format($e->totalDebit()) }}</td>
                        <td><a href="{{ route('accounting.voucher_print', $e) }}" class="text-success"><i class="fa fa-print"></i></a></td>
                    </tr>
                    @empty<tr><td colspan="7" class="text-center text-muted py-4">No vouchers in this range.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</section></div></div>
@endsection