<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class AccountSubhead extends Model
{
    public const KINDS = ['cash', 'bank', 'customer', 'vendor'];

    protected $fillable = ['head_id', 'code', 'name', 'kind', 'sort_order'];

    public function head(): BelongsTo     { return $this->belongsTo(AccountHead::class, 'head_id'); }
    public function accounts(): HasMany   { return $this->hasMany(ChartOfAccount::class, 'subhead_id')->orderBy('code'); }

    /** Customer / vendor sub-heads hold one account per party and are shown rolled-up. */
    public function isParty(): bool { return in_array($this->kind, ['customer', 'vendor'], true); }
    public function isMoney(): bool { return in_array($this->kind, ['cash', 'bank'], true); }
}
