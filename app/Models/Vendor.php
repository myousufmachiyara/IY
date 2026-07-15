<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Vendor extends Model
{
    protected $fillable = [
        'name', 'contact_person', 'phone', 'email', 'location',
        'commission_percent', 'address', 'notes', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['commission_percent' => 'decimal:2'];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function vehicles(): HasMany  { return $this->hasMany(Vehicle::class, 'vendor_id'); }
    public function payments(): HasMany  { return $this->hasMany(VendorPayment::class, 'vendor_id'); }

    public function totalPayable(): int { return (int) $this->vehicles()->sum('buying_price'); }
    public function totalPaid(): int    { return (int) $this->payments()->sum('amount'); }
    public function balance(): int      { return $this->totalPayable() - $this->totalPaid(); }

    public function scopeActive($q) { return $q->where('status', 'active'); }
}