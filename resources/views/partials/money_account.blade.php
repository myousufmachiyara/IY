{{--
    Cash / bank account dropdown — lists EVERY active account under a Cash or Bank sub-head.
    Usage: @include('partials.money_account', ['name' => 'account_id', 'id' => 'my_id', 'selected' => $account_id])
--}}
@php
    $moneyAccounts = \App\Models\ChartOfAccount::moneyAccounts();
    try { $defaultMoneyId = \App\Models\AccountMapping::accountFor('default_money_account')->id; } catch (\Throwable $e) { $defaultMoneyId = null; }
    $chosen = old($name ?? 'account_id', $selected ?? $defaultMoneyId);
@endphp
<select name="{{ $name ?? 'account_id' }}" @isset($id) id="{{ $id }}" @endisset class="form-control" required>
    @forelse($moneyAccounts->groupBy(fn ($a) => $a->subhead->name) as $group => $items)
        <optgroup label="{{ $group }}">
            @foreach($items as $a)
                <option value="{{ $a->id }}" @selected((string) $chosen === (string) $a->id)>{{ $a->name }} ({{ $a->code }})</option>
            @endforeach
        </optgroup>
    @empty
        <option value="" disabled selected>No cash / bank accounts — add one in Chart of Accounts</option>
    @endforelse
</select>
