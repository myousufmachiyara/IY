@extends('layouts.app')
@section('title', 'Accounting | Trial Balance')
@section('content')
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Accounting</h2></header>
    @include('accounting._tabs', ['active' => 'trial_balance'])
    <div class="card-body">
        <div class="table-scroll">
            <table class="table table-bordered table-striped mb-0">
                <thead><tr><th>Code</th><th>Account</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                <tbody>
                    @foreach($rows as $r)
                    <tr>
                        <td><code>{{ $r['account']->code }}</code></td>
                        <td>{{ $r['account']->name }}</td>
                        <td class="text-end">{{ $r['debit'] > 0 ? '¥'.number_format($r['debit']) : '' }}</td>
                        <td class="text-end">{{ $r['credit'] > 0 ? '¥'.number_format($r['credit']) : '' }}</td>
                    </tr>
                    @endforeach
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
            <div class="alert alert-danger mt-3"><i class="fa fa-exclamation-triangle"></i> Totals do not match — books are out of balance. Check for an unbalanced journal entry.</div>
        @else
            <div class="alert alert-success mt-3"><i class="fa fa-check-circle"></i> Debits equal credits — books are balanced.</div>
        @endif
    </div>
</section></div></div>
@endsection