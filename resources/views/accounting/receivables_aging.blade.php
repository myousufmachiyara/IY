@extends('layouts.app')
@section('title', 'Accounting | Receivables Aging')
@section('content')
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Accounting</h2></header>
    @include('accounting._tabs', ['active' => 'receivables_aging'])
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-2"><div class="card bg-light border text-center p-2"><small class="text-muted">Current</small><h5 class="mb-0">¥{{ number_format($buckets['current']) }}</h5></div></div>
            <div class="col-md-2"><div class="card bg-light border text-center p-2"><small class="text-muted">0-30 Days</small><h5 class="mb-0 text-warning">¥{{ number_format($buckets['0_30']) }}</h5></div></div>
            <div class="col-md-2"><div class="card bg-light border text-center p-2"><small class="text-muted">31-60 Days</small><h5 class="mb-0 text-warning">¥{{ number_format($buckets['31_60']) }}</h5></div></div>
            <div class="col-md-2"><div class="card bg-light border text-center p-2"><small class="text-muted">61-90 Days</small><h5 class="mb-0 text-danger">¥{{ number_format($buckets['61_90']) }}</h5></div></div>
            <div class="col-md-2"><div class="card bg-light border text-center p-2"><small class="text-muted">90+ Days</small><h5 class="mb-0 text-danger">¥{{ number_format($buckets['over_90']) }}</h5></div></div>
        </div>
        <div class="table-scroll">
            <table class="table table-bordered table-striped mb-0">
                <thead><tr><th>Invoice</th><th>Customer</th><th class="text-end">Balance</th><th>Days Overdue</th><th>Bucket</th></tr></thead>
                <tbody>
                    @forelse($rows as $r)
                    @php $bucketColors = ['current'=>'secondary','0_30'=>'warning text-dark','31_60'=>'warning text-dark','61_90'=>'danger','over_90'=>'danger']; @endphp
                    <tr>
                        <td><a href="{{ route('invoices.show', $r['invoice']) }}">{{ $r['invoice']->invoice_no }}</a></td>
                        <td>{{ $r['invoice']->customer->name }}</td>
                        <td class="text-end">¥{{ number_format($r['balance']) }}</td>
                        <td>{{ $r['days_overdue'] }}</td>
                        <td><span class="badge bg-{{ $bucketColors[$r['bucket']] }}">{{ str_replace('_', '-', $r['bucket']) }}</span></td>
                    </tr>
                    @empty<tr><td colspan="5" class="text-center text-muted py-4">No outstanding receivables.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</section></div></div>
@endsection
