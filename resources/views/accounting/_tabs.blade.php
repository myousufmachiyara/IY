@php $active = $active ?? 'chart'; @endphp
<div class="tabs pb-0 pt-2">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link {{ $active === 'chart' ? 'active' : '' }}" href="{{ route('accounting.chart') }}">Chart of Accounts</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'journal' ? 'active' : '' }}" href="{{ route('accounting.journal') }}">Journal</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'cash_bank' ? 'active' : '' }}" href="{{ route('accounting.cash_bank') }}">Cash &amp; Bank Book</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'receivables' ? 'active' : '' }}" href="{{ route('accounting.receivables') }}">Receivables</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'payables' ? 'active' : '' }}" href="{{ route('accounting.payables') }}">Payables</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'profit_loss' ? 'active' : '' }}" href="{{ route('accounting.profit_loss') }}">Profit &amp; Loss</a></li>
    </ul>
</div>