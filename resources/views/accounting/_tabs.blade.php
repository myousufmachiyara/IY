@php $active = $active ?? 'chart'; @endphp
<div class="tabs pb-0 pt-2">
    <ul class="nav nav-tabs flex-wrap">
        <li class="nav-item"><a class="nav-link {{ $active === 'chart' ? 'active' : '' }}" href="{{ route('accounting.chart') }}">Chart of Accounts</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'journal' ? 'active' : '' }}" href="{{ route('accounting.journal') }}">Journal</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'day_book' ? 'active' : '' }}" href="{{ route('accounting.day_book') }}">Day Book</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'party_ledger' ? 'active' : '' }}" href="{{ route('accounting.party_ledger') }}">Party Ledger</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'cash_book' ? 'active' : '' }}" href="{{ route('accounting.cash_book') }}">Cash Book</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'bank_book' ? 'active' : '' }}" href="{{ route('accounting.bank_book') }}">Bank Book</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'trial_balance' ? 'active' : '' }}" href="{{ route('accounting.trial_balance') }}">Trial Balance</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'balance_sheet' ? 'active' : '' }}" href="{{ route('accounting.balance_sheet') }}">Balance Sheet</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'receivables' ? 'active' : '' }}" href="{{ route('accounting.receivables') }}">Receivables</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'receivables_aging' ? 'active' : '' }}" href="{{ route('accounting.receivables_aging') }}">Receivables Aging</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'payables' ? 'active' : '' }}" href="{{ route('accounting.payables') }}">Payables</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'profit_loss' ? 'active' : '' }}" href="{{ route('accounting.profit_loss') }}">Profit &amp; Loss</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'expense_analysis' ? 'active' : '' }}" href="{{ route('accounting.expense_analysis') }}">Expense Analysis</a></li>
        <li class="nav-item"><a class="nav-link {{ $active === 'cash_flow' ? 'active' : '' }}" href="{{ route('accounting.cash_flow') }}">Cash Flow</a></li>
    </ul>
</div>