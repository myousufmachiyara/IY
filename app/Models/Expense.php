<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany};

class Expense extends Model
{
    /** category => label and the Account Mapping role its debit posts to. */
    public const CATEGORIES = [
        'salary'       => ['label' => 'Salary',        'key' => 'expense_salary'],
        'office'       => ['label' => 'Office',        'key' => 'expense_office'],
        'utilities'    => ['label' => 'Utilities',     'key' => 'expense_utilities'],
        'rent'         => ['label' => 'Rent',          'key' => 'expense_rent'],
        'bank_charges' => ['label' => 'Bank Charges',  'key' => 'expense_bank_charges'],
        'misc'         => ['label' => 'Miscellaneous', 'key' => 'expense_misc'],
    ];

    protected $fillable = [
        'category', 'description', 'amount', 'expense_date',
        'paid_from_account_id', 'is_backdated', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'integer',
            'expense_date' => 'date',
            'is_backdated' => 'boolean',
        ];
    }

    public function account(): BelongsTo  { return $this->belongsTo(ChartOfAccount::class, 'paid_from_account_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'reference');
    }
}