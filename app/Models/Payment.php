<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany};

class Payment extends Model
{
    protected $fillable = [
        'customer_id', 'invoice_id', 'vehicle_id', 'amount', 'method',
        'account_id', 'paid_at', 'reference', 'is_backdated', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'integer',
            'paid_at'      => 'date',
            'is_backdated' => 'boolean',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function invoice(): BelongsTo  { return $this->belongsTo(Invoice::class); }
    public function vehicle(): BelongsTo  { return $this->belongsTo(Vehicle::class); }
    public function account(): BelongsTo  { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    /** The ledger entry (or entries, after an edit) this payment originally posted. */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'reference');
    }
}