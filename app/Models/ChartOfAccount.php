<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    protected $table = 'chart_of_accounts';

    public const TYPES = ['asset', 'liability', 'equity', 'income', 'expense'];

    protected $fillable = ['code', 'name', 'type', 'parent_id', 'is_system', 'is_active'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function parent(): BelongsTo  { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany  { return $this->hasMany(self::class, 'parent_id'); }
    public function lines(): HasMany     { return $this->hasMany(JournalLine::class, 'account_id'); }

    // Running balance in yen. Debit-normal for asset/expense, credit-normal otherwise.
    public function balance(): int
    {
        $debit  = (int) $this->lines()->sum('debit');
        $credit = (int) $this->lines()->sum('credit');

        return in_array($this->type, ['asset', 'expense'], true)
            ? $debit - $credit
            : $credit - $debit;
    }

    public function scopeType($q, string $type) { return $q->where('type', $type); }
}