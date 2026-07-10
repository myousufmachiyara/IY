<?php

namespace App\Models;

use App\Models\Concerns\ScopedToAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BidSheet extends Model
{
    use ScopedToAgent;

    protected $fillable = [
        'agent_id', 'title', 'file_path', 'auction_date', 'rows_count', 'status',
    ];

    protected function casts(): array
    {
        return ['auction_date' => 'date', 'rows_count' => 'integer'];
    }

    public function agent(): BelongsTo { return $this->belongsTo(User::class, 'agent_id'); }
    public function bids(): HasMany    { return $this->hasMany(Bid::class); }
}