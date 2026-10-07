<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Facades\DB;

class ChartOfAccount extends Model
{
    protected $fillable = [
        'code', 'account_code', 'name', 'type', 'subhead_id', 'parent_id',
        'is_system', 'is_active', 'customer_id', 'vendor_id',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subhead(): BelongsTo  { return $this->belongsTo(AccountSubhead::class, 'subhead_id'); }
    public function parent(): BelongsTo   { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany   { return $this->hasMany(self::class, 'parent_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vendor(): BelongsTo   { return $this->belongsTo(Vendor::class); }
    public function journalLines(): HasMany { return $this->hasMany(JournalLine::class, 'account_id'); }

    /** Assets and expenses grow on the debit side. ('customer' is a legacy type value.) */
    public function isDebitNature(): bool
    {
        return in_array($this->type, ['asset', 'expense', 'customer'], true);
    }

    public function isParty(): bool        { return $this->customer_id !== null || $this->vendor_id !== null; }
    public function isMoneyAccount(): bool { return (bool) $this->subhead?->isMoney(); }
    public function moneyKind(): ?string   { return $this->isMoneyAccount() ? $this->subhead->kind : null; }

    public function hasTransactions(): bool { return $this->journalLines()->exists(); }

    /** Labels of every accounting role currently pointing at this account. */
    public function mappedRoles(): array
    {
        return AccountMapping::where('account_id', $this->id)->pluck('key')
            ->map(fn ($k) => AccountMapping::KEYS[$k]['label'] ?? $k)->all();
    }

    /** Balance in the account's natural direction, optionally as of a date. */
    public function balance(?string $asOf = null): int
    {
        $q = JournalLine::where('account_id', $this->id)
            ->when($asOf, fn ($q) => $q->whereHas('entry', fn ($e) => $e->whereDate('date', '<=', $asOf)));

        $debit  = (int) (clone $q)->sum('debit');
        $credit = (int) (clone $q)->sum('credit');

        return $this->isDebitNature() ? $debit - $credit : $credit - $debit;
    }

    /** All active cash and bank accounts — what every payment dropdown offers. */
    public static function moneyAccounts()
    {
        return static::with('subhead')
            ->where('is_active', true)
            ->whereHas('subhead', fn ($q) => $q->whereIn('kind', ['cash', 'bank']))
            ->orderBy('code')->get();
    }

    public function scopeType($q, string $type) { return $q->where('type', $type); }
}
