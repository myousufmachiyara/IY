<?php

namespace App\Models;

use App\Models\Concerns\ScopedToAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

class Customer extends Model
{
    use ScopedToAgent;

    protected $fillable = [
        'customer_no', 'name', 'phone', 'email', 'country', 'postal_code', 'address',
        'consignee_name', 'eori_vat_number', 'agent_id', 'account_date',
        'security_deposit', 'security_deposit_paid', 'security_deposit_refunded',
        'security_deposit_status', 'security_deposit_account', 'security_deposit_evidence_path',
        'security_deposit_received_by', 'security_deposit_received_at',
        'security_deposit_approved_by', 'security_deposit_approved_at',
        'security_deposit_rejection_reason', 'deposit_invoice_id',
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
            'account_date'                 => 'date',
            'profile_completed_at'         => 'datetime',
        ];
    }

    public function agent(): BelongsTo   { return $this->belongsTo(User::class, 'agent_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function depositReceivedBy(): BelongsTo { return $this->belongsTo(User::class, 'security_deposit_received_by'); }
    public function depositApprovedBy(): BelongsTo { return $this->belongsTo(User::class, 'security_deposit_approved_by'); }
    public function depositInvoice(): BelongsTo { return $this->belongsTo(Invoice::class, 'deposit_invoice_id'); }

    public function ports(): BelongsToMany { return $this->belongsToMany(Port::class, 'customer_port'); }

    public function vehicles(): HasMany  { return $this->hasMany(Vehicle::class); }
    public function invoices(): HasMany  { return $this->hasMany(Invoice::class); }
    public function payments(): HasMany  { return $this->hasMany(Payment::class); }
    public function shipments(): HasMany { return $this->hasMany(Shipment::class); }

    public function totalInvoiced(): int { return (int) $this->invoices()->sum('total_payable'); }
    public function totalPaid(): int { return (int) $this->payments()->where('status', 'approved')->sum('amount'); }
    public function balance(): int       { return $this->totalInvoiced() - $this->totalPaid(); }

    /** Profile is complete once the deposit invoice is fully paid — the deposit-invoice workflow is the sole source of truth going forward. */
    public function isProfileComplete(): bool
    {
        return $this->depositInvoice && $this->depositInvoice->isFullyPaid();
    }

    public function canCompleteProfile(): bool
    {
        return $this->depositInvoice && $this->depositInvoice->isFullyPaid();
    }

    public function scopeComplete($q) { return $q->whereNotNull('profile_completed_at'); }
    public function scopeActive($q)   { return $q->where('status', 'active'); }
}