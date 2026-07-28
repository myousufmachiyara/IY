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
        'security_deposit', 'security_deposit_paid', 'security_deposit_refunded',
        'security_deposit_status', 'security_deposit_account',
        'security_deposit_received_by', 'security_deposit_received_at',
        'security_deposit_approved_by', 'security_deposit_approved_at',
        'security_deposit_rejection_reason',
        'profile_completed_at', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'security_deposit'             => 'integer',
            'security_deposit_paid'        => 'boolean',
            'security_deposit_refunded'    => 'boolean',
            'security_deposit_received_at' => 'datetime',
            'security_deposit_approved_at' => 'datetime',
            'profile_completed_at'         => 'datetime',
        ];
    }

    public function agent(): BelongsTo   { return $this->belongsTo(User::class, 'agent_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function depositReceivedBy(): BelongsTo { return $this->belongsTo(User::class, 'security_deposit_received_by'); }
    public function depositApprovedBy(): BelongsTo { return $this->belongsTo(User::class, 'security_deposit_approved_by'); }

    public function vehicles(): HasMany  { return $this->hasMany(Vehicle::class); }
    public function invoices(): HasMany  { return $this->hasMany(Invoice::class); }
    public function payments(): HasMany  { return $this->hasMany(Payment::class); }
    public function shipments(): HasMany { return $this->hasMany(Shipment::class); }

    public function isProfileComplete(): bool
    {
        return ! is_null($this->profile_completed_at);
    }

    public function canCompleteProfile(): bool
    {
        return $this->security_deposit_status === 'approved';
    }

    public function totalInvoiced(): int { return (int) $this->invoices()->sum('total_payable'); }
    public function totalPaid(): int     { return (int) $this->payments()->sum('amount'); }
    public function balance(): int       { return $this->totalInvoiced() - $this->totalPaid(); }

    public function scopeComplete($q) { return $q->whereNotNull('profile_completed_at'); }
    public function scopeActive($q)   { return $q->where('status', 'active'); }
}