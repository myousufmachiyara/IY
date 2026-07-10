<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    public const METHODS = ['RORO', 'Container'];

    protected $fillable = [
        'customer_id', 'method', 'shipment_date', 'expected_arrival',
        'freight_total', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'shipment_date'    => 'date',
            'expected_arrival' => 'date',
            'freight_total'    => 'integer',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function vehicles(): HasMany   { return $this->hasMany(Vehicle::class); }
}