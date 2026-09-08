<?php

namespace App\Models;

use App\Models\Concerns\ScopedToAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};

class Invoice extends Model
{
    use ScopedToAgent;

    protected $fillable = [
        'invoice_no', 'invoice_type', 'vehicle_id', 'customer_id', 'agent_id',
        'sale_price', 'settled_amount', 'total_payable', 'amount_paid',
        'due_first', 'due_final', 'status', 'issued_by', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_price'     => 'integer',
            'settled_amount' => 'integer',
            'total_payable'  => 'integer',
            'amount_paid'    => 'integer',
            'due_first'      => 'date',
            'due_final'      => 'date',
            'issued_at'      => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo  { return $this->belongsTo(Vehicle::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function agent(): BelongsTo    { return $this->belongsTo(User::class, 'agent_id'); }
    public function payments(): HasMany   { return $this->hasMany(Payment::class); }

    public function computedPayable(): int { return max($this->sale_price - $this->settled_amount, 0); }
    public function balance(): int         { return max($this->total_payable - $this->amount_paid, 0); }
    public function paidPercent(): float
    {
        return $this->total_payable > 0 ? round($this->amount_paid / $this->total_payable * 100, 1) : 0;
    }

    public function isFullyPaid(): bool { return $this->amount_paid >= $this->total_payable && $this->total_payable > 0; }
    public function isHalfPaid(): bool  { return $this->paidPercent() >= 50; }
    public function isDepositInvoice(): bool { return $this->invoice_type === 'deposit'; }

    /**
     * Recompute status/amount_paid from actual payments. Call after recording a payment.
     * NOTE: this only ever SETS profile completion when a deposit invoice first
     * becomes fully paid — it does not automatically un-set it if a payment is
     * later deleted/reversed through the normal payment flow. undoDepositAdjustment()
     * on InvoiceController explicitly handles the one reversal path this feature
     * currently supports; a payment deleted through PaymentController::destroy()
     * against a deposit invoice will NOT re-flip the profile back to incomplete —
     * flagging this as a known gap rather than silently leaving it undocumented.
     */
    public function refreshTotals(): static
    {
        $wasFullyPaid = $this->isFullyPaid();

        $this->total_payable = $this->computedPayable();
        $this->amount_paid   = (int) $this->payments()->where('status', 'approved')->sum('amount');

        $this->status = match (true) {
            $this->status === 'cancelled' => 'cancelled',
            $this->isFullyPaid()          => 'paid',
            $this->amount_paid > 0        => 'partial',
            $this->issued_at              => 'issued',
            default                       => 'draft',
        };

        if (! $wasFullyPaid && $this->isDepositInvoice() && $this->isFullyPaid() && $this->customer && ! $this->customer->profile_completed_at) {
            $this->customer->update([
                'profile_completed_at'    => now(),
                'security_deposit'        => $this->total_payable,
                'security_deposit_status' => 'approved',
            ]);
        }

        return $this;
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'reference');
    }
}