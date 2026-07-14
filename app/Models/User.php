<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'username', 'email', 'phone', 'password', 'status',
        'sales_commission_percent', 'sales_fixed_bonus',
        'vendor_commission_percent', 'vendor_location', 'created_by',
    ];

    protected $hidden = ['password', 'remember_token'];

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

    public function username()
    {
        return 'username';
    }

    // ── Permission-driven business-role helpers ─────────────────────────────
    // These read PERMISSIONS, not role names — any role, built-in or custom,
    // behaves correctly the moment it's granted the matching permission.

    /** Scoped to own customers/vehicles/bids (AgentScope); also shows commission fields on Team form. */
    public function isSalesAgent(): bool { return $this->can('scope.by_agent'); }

    /** Scoped to vehicles they supply (AgentScope); also shows vendor fields on Team form. */
    public function isVendorAgent(): bool { return $this->can('scope.by_vendor'); }

    /** Allowed to record back-dated payments/expenses/vendor payments. */
    public function canBackdate(): bool { return $this->can('finance.backdate'); }

    /** Holds the most sensitive permission in the system — used only to protect the last such user from deletion. */
    public function isSuperAdmin(): bool { return $this->can('user_roles.edit'); }

    public function creator(): BelongsTo { return $this->belongsTo(self::class, 'created_by'); }

    public function customers(): HasMany { return $this->hasMany(Customer::class, 'agent_id'); }
    public function vehicles(): HasMany { return $this->hasMany(Vehicle::class, 'agent_id'); }
    public function vendorVehicles(): HasMany { return $this->hasMany(Vehicle::class, 'vendor_id'); }
    public function bidSheets(): HasMany { return $this->hasMany(BidSheet::class, 'agent_id'); }
    public function bids(): HasMany { return $this->hasMany(Bid::class, 'agent_id'); }
    public function vendorPayments(): HasMany { return $this->hasMany(VendorPayment::class, 'vendor_id'); }
}