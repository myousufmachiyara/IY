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
        'sales_commission_percent', 'sales_fixed_bonus', 'created_by',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'password'                 => 'hashed',
            'sales_commission_percent' => 'decimal:2',
            'sales_fixed_bonus'        => 'integer',
        ];
    }

    public function username()
    {
        return 'username';
    }

    public function isSalesAgent(): bool { return $this->can('scope.by_agent'); }
    public function canBackdate(): bool  { return $this->can('finance.backdate'); }
    public function isSuperAdmin(): bool { return $this->can('user_roles.edit'); }

    public function creator(): BelongsTo { return $this->belongsTo(self::class, 'created_by'); }

    public function customers(): HasMany { return $this->hasMany(Customer::class, 'agent_id'); }
    public function vehicles(): HasMany  { return $this->hasMany(Vehicle::class, 'agent_id'); }
    public function bidSheets(): HasMany { return $this->hasMany(BidSheet::class, 'agent_id'); }
    public function bids(): HasMany      { return $this->hasMany(Bid::class, 'agent_id'); }
}