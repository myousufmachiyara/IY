<?php

namespace App\Models;

use App\Models\Concerns\ScopedToAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use ScopedToAgent;

    protected $fillable = [
        'name', 'phone', 'email', 'country', 'address', 'agent_id',
        'is_new_customer', 'security_deposit', 'security_deposit_paid',
        'security_deposit_refunded', 'profile_completed_at', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_new_customer'           => 'boolean',
            'security_deposit'          => 'integer',
            'security_deposit_paid'     => 'boolean',
            'security_deposit_refunded' => 'boolean',
            'profile_completed_at'      => 'datetime',
        ];
    }

    // ---- relationships ----
    public function agent(): BelongsTo   { return $this->belongsTo(User::class, 'agent_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function vehicles(): HasMany  { return $this->hasMany(Vehicle::class); }
    public function invoices(): HasMany  { return $this->hasMany(Invoice::class); }
    public function payments(): HasMany  { return $this->hasMany(Payment::class); }
    public function shipments(): HasMany { return $this->hasMany(Shipment::class); }

    // ---- profile-completion gate (bidding requires this) ----
    public function isProfileComplete(): bool
    {
        // A new customer must have paid the refundable deposit to be complete.
        if ($this->is_new_customer && ! $this->security_deposit_paid) {
            return false;
        }

        return ! is_null($this->profile_completed_at);
    }

    // ---- derived balances ----
    public function totalInvoiced(): int { return (int) $this->invoices()->sum('total_payable'); }
    public function totalPaid(): int     { return (int) $this->payments()->sum('amount'); }
    public function balance(): int       { return $this->totalInvoiced() - $this->totalPaid(); }

    public function scopeComplete($q)    { return $q->whereNotNull('profile_completed_at'); }
    public function scopeActive($q)      { return $q->where('status', 'active'); }
}