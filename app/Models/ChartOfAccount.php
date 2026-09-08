<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class ChartOfAccount extends Model
{
    protected $fillable = [
        'code', 'account_code', 'name', 'type', 'parent_id',
        'is_system', 'is_active', 'opening_balance', 'opening_date',
        'customer_id', 'vendor_id',
    ];

    protected function casts(): array
    {
        return [
            'is_system'       => 'boolean',
            'is_active'       => 'boolean',
            'opening_balance' => 'integer',
            'opening_date'    => 'date',
        ];
    }

    public function parent(): BelongsTo   { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany   { return $this->hasMany(self::class, 'parent_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vendor(): BelongsTo   { return $this->belongsTo(Vendor::class); }

    /** True for account types whose natural/increasing side is a debit. */
    public function isDebitNature(): bool
    {
        return in_array($this->type, ['asset', 'expense', 'customer'], true);
    }

    /** Current running balance for this one account, all-time. */
    public function balance(): int
    {
        $debit  = (int) $this->journalLines()->sum('debit');
        $credit = (int) $this->journalLines()->sum('credit');
        return $this->isDebitNature() ? ($debit - $credit) : ($credit - $debit);
    }

    public function journalLines(): HasMany { return $this->hasMany(JournalLine::class, 'account_id'); }

    public function scopeType($q, string $type) { return $q->where('type', $type); }
}