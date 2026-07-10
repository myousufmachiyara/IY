<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'username', 'email', 'phone', 'password', 'status',
        'sales_commission_percent', 'sales_fixed_bonus',
        'vendor_commission_percent', 'vendor_location', 'created_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'         => 'datetime',
            'password'                  => 'hashed',
            'sales_commission_percent'  => 'decimal:2',
            'sales_fixed_bonus'         => 'integer',
            'vendor_commission_percent' => 'decimal:2',
        ];
    }

    /**
     * Laravel auth looks up users by this column instead of 'email'.
     * Make sure your LoginRequest/Auth::attempt() call uses 'username' as the credential key.
     */
    public function username(): string
    {
        return 'username';
    }

    // ── Role helpers ─────────────────────────────────────────────────────────

    public function isSuperAdmin(): bool { return $this->hasRole('super_admin'); }
    public function isAccountant(): bool { return $this->hasRole('accountant'); }
    public function isSalesAgent(): bool { return $this->hasRole('sales_agent'); }
    public function isVendorAgent(): bool { return $this->hasRole('vendor_agent'); }

    /** Only super admin or accountant may make back-dated ledger entries. */
    public function canBackdate(): bool { return $this->isSuperAdmin() || $this->isAccountant(); }

    public function scopeRole($q, string $role) { return $q->whereHas('roles', fn ($r) => $r->where('name', $role)); }

    // ── Relationships ────────────────────────────────────────────────────────

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    /** Customers owned by this user when acting as a sales agent. */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'agent_id');
    }

    /** Vehicles owned by this user when acting as a sales agent. */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'agent_id');
    }

    /** Vehicles supplied by this user when acting as a vendor agent. */
    public function vendorVehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'vendor_id');
    }

    public function bidSheets(): HasMany
    {
        return $this->hasMany(BidSheet::class, 'agent_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'agent_id');
    }

    public function vendorPayments(): HasMany
    {
        return $this->hasMany(VendorPayment::class, 'vendor_id');
    }
}