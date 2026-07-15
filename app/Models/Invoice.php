<?php

namespace App\Models;

use App\Models\Concerns\ScopedToAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};

class Invoice extends Model
{
    use ScopedToAgent;

    protected $fillable = [
        'invoice_no', 'vehicle_id', 'customer_id', 'agent_id',
        'sale_price', 'settled_amount', 'total_payable', 'amount_paid',
        'due_first', 'due_final', 'status', 'issued_by', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_price'    => 'integer',
            'settled_amount'=> 'integer',
            'total_payable' => 'integer',
            'amount_paid'   => 'integer',
            'due_first'     => 'date',
            'due_final'     => 'date',
            'issued_at'     => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo  { return $this->belongsTo(Vehicle::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function agent(): BelongsTo    { return $this->belongsTo(User::class, 'agent_id'); }
    public function payments(): HasMany   { return $this->hasMany(Payment::class); }

    // total_payable = sale_price - settled_amount
    public function computedPayable(): int { return max($this->sale_price - $this->settled_amount, 0); }
    public function balance(): int         { return max($this->total_payable - $this->amount_paid, 0); }
    public function paidPercent(): float
    {
        return $this->total_payable > 0 ? round($this->amount_paid / $this->total_payable * 100, 1) : 0;
    }

    public function isFullyPaid(): bool { return $this->amount_paid >= $this->total_payable && $this->total_payable > 0; }
    public function isHalfPaid(): bool  { return $this->paidPercent() >= 50; }

    /** Recompute status/amount_paid from actual payments. Call after recording a payment. */
    public function refreshTotals(): static
    {
        $this->total_payable = $this->computedPayable();
        $this->amount_paid   = (int) $this->payments()->sum('amount');

        $this->status = match (true) {
            $this->status === 'cancelled' => 'cancelled',
            $this->isFullyPaid()          => 'paid',
            $this->amount_paid > 0        => 'partial',
            $this->issued_at              => 'issued',
            default                       => 'draft',
        };

        return $this;
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'reference');
    }
}