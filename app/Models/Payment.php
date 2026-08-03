<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany};

class Payment extends Model
{
    protected $fillable = [
        'customer_id', 'invoice_id', 'vehicle_id', 'amount', 'method',
        'account_id', 'paid_at', 'reference', 'attachment_path',
        'is_backdated', 'recorded_by', 'status', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'integer',
            'paid_at'      => 'date',
            'is_backdated' => 'boolean',
            'approved_at'  => 'datetime',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function invoice(): BelongsTo  { return $this->belongsTo(Invoice::class); }
    public function vehicle(): BelongsTo  { return $this->belongsTo(Vehicle::class); }
    public function account(): BelongsTo  { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'reference');
    }

    public function scopeApproved($q) { return $q->where('status', 'approved'); }
    public function scopePending($q)  { return $q->where('status', 'pending'); }
}