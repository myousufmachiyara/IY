<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountHead extends Model
{
    protected $fillable = ['code', 'name', 'nature', 'sort_order'];

    public function subheads(): HasMany { return $this->hasMany(AccountSubhead::class, 'head_id')->orderBy('sort_order')->orderBy('code'); }

    public function isDebitNature(): bool { return in_array($this->nature, ['asset', 'expense'], true); }
}
