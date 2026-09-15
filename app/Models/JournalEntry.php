<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    public const VOUCHER_TYPES = [
        'sale_invoice'       => 'Sale Invoice Voucher',
        'deposit_invoice'    => 'Deposit Invoice Voucher',
        'receipt'            => 'Receipt Voucher',
        'deposit_receipt'    => 'Deposit Receipt Voucher',
        'deposit_adjustment' => 'Deposit Adjustment Voucher',
        'payment'            => 'Vendor Payment Voucher',
        'payable'            => 'Vendor Payable Voucher',
        'expense'            => 'Expense Voucher',
        'reversal'           => 'Reversal Voucher',
        'journal'            => 'Journal Voucher',
    ];

    protected $fillable = [
        'entry_no', 'voucher_type', 'date', 'description', 'reference_type', 'reference_id',
        'is_backdated', 'created_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_backdated' => 'boolean'];
    }

    public function lines(): HasMany   { return $this->hasMany(JournalLine::class); }
    public function reference(): MorphTo { return $this->morphTo(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function voucherLabel(): string
    {
        return self::VOUCHER_TYPES[$this->voucher_type] ?? 'Journal Voucher';
    }

    public function totalDebit(): int  { return (int) $this->lines->sum('debit'); }
    public function totalCredit(): int { return (int) $this->lines->sum('credit'); }
    public function isBalanced(): bool { return $this->totalDebit() === $this->totalCredit(); }
}