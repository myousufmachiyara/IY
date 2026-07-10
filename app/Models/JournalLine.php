<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalLine extends Model
{
    protected $fillable = [
        'journal_entry_id', 'account_id', 'debit', 'credit',
        'party_type', 'party_id', 'memo',
    ];

    protected function casts(): array
    {
        return ['debit' => 'integer', 'credit' => 'integer'];
    }

    public function entry(): BelongsTo   { return $this->belongsTo(JournalEntry::class, 'journal_entry_id'); }
    public function account(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }
    public function party(): MorphTo     { return $this->morphTo(); } // Customer or Vendor subledger
}