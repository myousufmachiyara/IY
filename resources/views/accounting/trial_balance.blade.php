@extends('layouts.app')
@section('title', 'Accounting | Trial Balance')
@section('content')
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Accounting</h2></header>
    @include('accounting._tabs', ['active' => 'trial_balance'])
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-3"><label class="small text-muted mb-1">As of</label><input type="date" name="as_of" class="form-control" value="{{ $asOf }}"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-secondary w-100">Update</button></div>
        </form>

        <div class="table-scroll">
            <table class="table table-bordered mb-0">
                <thead><tr><th style="width:130px;">Code</th><th>Account</th><th class="text-end" style="width:170px;">Debit</th><th class="text-end" style="width:170px;">Credit</th></tr></thead>
                <tbody>
                    @forelse($rows as $r)
                        @if($r['kind'] === 'head')
                            <tr class="table-secondary"><td colspan="4" class="fw-bold text-uppercase">{{ $r['name'] }}</td></tr>
                        @elseif($r['kind'] === 'subhead')
                            <tr class="bg-light"><td></td><td colspan="3" class="fw-semibold">{{ $r['name'] }}</td></tr>
                        @else
                            <tr>
                                <td><code>{{ $r['code'] }}</code></td>
                                <td class="ps-4">{{ $r['name'] }}</td>
                                <td class="text-end">{{ $r['debit'] > 0 ? '¥'.number_format($r['debit']) : '' }}</td>
                                <td class="text-end">{{ $r['credit'] > 0 ? '¥'.number_format($r['credit']) : '' }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No postings up to this date.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="fw-bold border-top">
                        <td colspan="2">Total</td>
                        <td class="text-end">¥{{ number_format($totalDebit) }}</td>
                        <td class="text-end">¥{{ number_format($totalCredit) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @if($totalDebit !== $totalCredit)
            <div class="alert alert-danger mt-3"><i class="fa fa-exclamation-triangle"></i> Debits ¥{{ number_format($totalDebit) }} ≠ credits ¥{{ number_format($totalCredit) }} — books are out of balance by ¥{{ number_format(abs($totalDebit - $totalCredit)) }}.</div>
        @else
            <div class="alert alert-success mt-3"><i class="fa fa-check-circle"></i> Debits equal credits — books are balanced.</div>
        @endif
    </div>
</section></div></div>
@endsection
