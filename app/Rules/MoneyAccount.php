<?php

namespace App\Rules;

use App\Models\ChartOfAccount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** The value must be an active account that sits under a Cash or Bank sub-head. */
class MoneyAccount implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $account = ChartOfAccount::with('subhead')->find($value);

        if (! $account || ! $account->is_active || ! $account->isMoneyAccount()) {
            $fail('Select a valid cash or bank account.');
        }
    }
}
