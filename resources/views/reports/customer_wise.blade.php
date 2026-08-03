@extends('layouts.app')
@section('title', 'Reports | Customer-wise')
@section('content')
<div class="row"><div class="col"><section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">Reports</h2>
        <div>
            <a href="{{ route('reports.customer_wise', ['export'=>'excel']) }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="{{ route('reports.customer_wise', ['export'=>'pdf']) }}" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
    </header>
    @include('reports._tabs', ['active' => 'customer_wise'])
    <div class="card-body">
        <div class="table-scroll">
            <table class="table table-bordered table-striped mb-0" id="datatable-default">
                <thead><tr><th>Customer</th><th>Agent</th><th class="text-end">Vehicles</th><th class="text-end">Invoiced</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr>
                        <td>{{ $r['customer'] }}</td><td>{{ $r['agent'] }}</td><td class="text-end">{{ $r['vehicles'] }}</td>
                        <td class="text-end">¥{{ number_format($r['invoiced']) }}</td>
                        <td class="text-end text-success">¥{{ number_format($r['paid']) }}</td>
                        <td class="text-end fw-bold {{ $r['balance']>0?'text-danger':'text-success' }}">¥{{ number_format($r['balance']) }}</td>
                    </tr>
                    @empty<tr><td colspan="6" class="text-center text-muted py-4">No customers found.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</section></div></div>
@endsection