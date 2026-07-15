<?php

namespace App\Models;

use App\Models\Concerns\ScopedToAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    use ScopedToAgent;

    // Lifecycle
    public const STATUS = [
        'requirement', 'bidding', 'won', 'lost',
        'invoiced', 'dispatched', 'arrived', 'delivered',
    ];

    protected $fillable = [
        'customer_id', 'agent_id', 'vendor_id', 'shipment_id',
        'make', 'model', 'year', 'grade', 'chassis_no', 'budget',
        'buying_price', 'selling_price', 'winning_screenshot_path',
        'won_at', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'budget'        => 'integer',
            'buying_price'  => 'integer',
            'selling_price' => 'integer',
            'won_at'        => 'datetime',
        ];
    }

    // ---- relationships ----
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function agent(): BelongsTo    { return $this->belongsTo(User::class, 'agent_id'); }
    public function vendor(): BelongsTo   { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function costing(): HasOne     { return $this->hasOne(VehicleCosting::class); }
    public function invoice(): HasOne     { return $this->hasOne(Invoice::class); }
    public function documents(): HasMany  { return $this->hasMany(Document::class); }
    public function payments(): HasMany   { return $this->hasMany(Payment::class); }
    public function vendorPayments(): HasMany { return $this->hasMany(VendorPayment::class); }
    public function bid(): HasOne         { return $this->hasOne(Bid::class); }

    // ---- helpers ----
    public function isWon(): bool  { return in_array($this->status, ['won','invoiced','dispatched','arrived','delivered'], true); }
    public function label(): string
    {
        return trim("{$this->year} {$this->make} {$this->model}") ?: "Vehicle #{$this->id}";
    }

    public function scopeWon($q)          { return $q->where('status', 'won'); }
    public function scopeForCustomer($q, int $id) { return $q->where('customer_id', $id); }
}