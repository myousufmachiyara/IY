<?php

namespace App\Models;

use App\Models\Concerns\ScopedToAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bid extends Model
{
    use ScopedToAgent;

    protected $fillable = [
        'bid_sheet_id', 'agent_id', 'customer_id', 'vehicle_id',
        'lot_no', 'auction_house', 'auction_date', 'make', 'model', 'year',
        'grade', 'chassis_no', 'max_bid', 'result', 'won_amount',
    ];

    protected function casts(): array
    {
        return [
            'auction_date' => 'date',
            'max_bid'      => 'integer',
            'won_amount'   => 'integer',
        ];
    }

    public function sheet(): BelongsTo    { return $this->belongsTo(BidSheet::class, 'bid_sheet_id'); }
    public function agent(): BelongsTo    { return $this->belongsTo(User::class, 'agent_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vehicle(): BelongsTo  { return $this->belongsTo(Vehicle::class); }

    public function scopeWon($q)  { return $q->where('result', 'won'); }
    public function scopeLost($q) { return $q->where('result', 'lost'); }
}