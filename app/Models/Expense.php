<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
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