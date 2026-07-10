<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayment extends Model
{
    protected $fillable = [
        'vendor_id', 'vehicle_id', 'amount', 'account_id',
        'paid_at', 'reference', 'is_backdated', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'integer',
            'paid_at'      => 'date',
            'is_backdated' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo  { return $this->belongsTo(User::class, 'vendor_id'); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function account(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }
    public function recorder(): BelongsTo{ return $this->belongsTo(User::class, 'recorded_by'); }
}