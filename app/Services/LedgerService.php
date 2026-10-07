<?php

namespace App\Services;

use App\Models\{AccountMapping, AccountSubhead, ChartOfAccount, Customer, Expense, Invoice, JournalEntry, JournalLine, Payment, Vehicle, Vendor, VendorPayment};
use Illuminate\Support\Facades\{Auth, DB};
use InvalidArgumentException;
use RuntimeException;

/**
 * Double-entry posting engine. Every account is resolved through the Account Mapping page
 * (or an explicit account id) — no posting method depends on a hard-coded account code.
 */
class LedgerService
{
    /** Default codes only ever used by AccountStructure to seed the first mapping. */
    public const CASH = '1000', BANK = '1010';

    // ──────────────────────────── account resolution ────────────────────────────

    public function mapped(string $key): ChartOfAccount
    {
        return AccountMapping::accountFor($key);
    }

    /** Legacy helper: look an account up by code. Prefer mapped() in new code. */
    public function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('code', $code)->firstOrFail();
    }

    /** Accepts an account, or an account id; the account must sit under a Cash/Bank sub-head. */
    public function moneyAccount(ChartOfAccount|int|string|null $value): ChartOfAccount
    {
        $account = $value instanceof ChartOfAccount ? $value : ChartOfAccount::with('subhead')->find((int) $value);

        if (! $account || ! $account->isMoneyAccount()) {
            throw new InvalidArgumentException('The selected account is not a cash or bank account.');
        }

        return $account;
    }

    /** Old records stored the cash/bank account as a code ("1000"/"1010"), not an id. */
    public function legacyMoneyAccount(?string $code): ChartOfAccount
    {
        $account = $code ? ChartOfAccount::with('subhead')->where('code', $code)->first() : null;

        return $this->moneyAccount($account ?? $this->mapped('default_money_account'));
    }

    protected function partySubhead(string $kind): AccountSubhead
    {
        $sub = AccountSubhead::where('kind', $kind)->orderBy('sort_order')->first();

        if (! $sub) {
            throw new RuntimeException("No \"{$kind}\" sub-head exists. Run: php artisan migrate");
        }

        return $sub;
    }

    public function ensureCustomerAccount(Customer $customer): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'code' => 'CUS-' . $customer->id, 'account_code' => 'CUS-' . $customer->id,
                'name' => 'Receivable — ' . $customer->name, 'type' => 'asset',
                'subhead_id' => $this->partySubhead('customer')->id,
                'is_system' => false, 'is_active' => true,
            ]
        );
    }

    public function ensureVendorAccount(Vendor $vendor): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'code' => 'VEN-' . $vendor->id, 'account_code' => 'VEN-' . $vendor->id,
                'name' => 'Payable — ' . $vendor->name, 'type' => 'liability',
                'subhead_id' => $this->partySubhead('vendor')->id,
                'is_system' => false, 'is_active' => true,
            ]
        );
    }

    // ──────────────────────────── core posting ────────────────────────────

    /**
     * Each line: ['account_id' => int] | ['account' => code] | ['key' => mappingKey],
     * plus debit/credit, optional party and memo.
     */
    public function post(string $date, string $description, array $lines, ?object $reference = null, bool $backdated = false, string $voucherType = 'journal'): JournalEntry
    {
        $debit  = array_sum(array_column($lines, 'debit'));
        $credit = array_sum(array_column($lines, 'credit'));

        if ($debit !== $credit || $debit === 0) {
            throw new InvalidArgumentException("Unbalanced journal entry: debit {$debit} ≠ credit {$credit}");
        }

        return DB::transaction(function () use ($date, $description, $lines, $reference, $backdated, $voucherType) {
            $entry = JournalEntry::create([
                'entry_no'       => $this->nextNo(),
                'voucher_type'   => $voucherType,
                'date'           => $date,
                'description'    => $description,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id'   => $reference?->getKey(),
                'is_backdated'   => $backdated,
                'created_by'     => Auth::id(),
            ]);

            foreach ($lines as $l) {
                $party = $l['party'] ?? null;
                $accountId = $l['account_id']
                    ?? (isset($l['key']) ? $this->mapped($l['key'])->id : $this->account($l['account'])->id);

                $entry->lines()->create([
                    'account_id' => $accountId,
                    'debit'      => $l['debit'] ?? 0,
                    'credit'     => $l['credit'] ?? 0,
                    'party_type' => $party?->getMorphClass(),
                    'party_id'   => $party?->getKey(),
                    'memo'       => $l['memo'] ?? null,
                ]);
            }

            return $entry;
        });
    }

    protected function nextNo(): string
    {
        return 'JE' . str_pad((int) JournalEntry::max('id') + 1, 6, '0', STR_PAD_LEFT);
    }

    public function reverseEntry(JournalEntry $original, string $date, ?string $description = null): JournalEntry
    {
        return DB::transaction(function () use ($original, $date, $description) {
            $entry = JournalEntry::create([
                'entry_no'       => $this->nextNo(),
                'voucher_type'   => 'reversal',
                'date'           => $date,
                'description'    => $description ?? "Reversal of {$original->entry_no}",
                'reference_type' => $original->reference_type,
                'reference_id'   => $original->reference_id,
                'is_backdated'   => false,
                'created_by'     => Auth::id(),
            ]);

            foreach ($original->lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line->account_id,
                    'debit'      => $line->credit,
                    'credit'     => $line->debit,
                    'party_type' => $line->party_type,
                    'party_id'   => $line->party_id,
                    'memo'       => 'Reversal',
                ]);
            }

            return $entry;
        });
    }

    // ──────────────────────────── balances ────────────────────────────

    /** account_id => ['debit' => int, 'credit' => int], optionally limited to a date window. */
    public function totals(?string $asOf = null, ?string $from = null): array
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($asOf, fn ($q) => $q->whereDate('journal_entries.date', '<=', $asOf))
            ->when($from, fn ($q) => $q->whereDate('journal_entries.date', '>=', $from))
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id as account_id, SUM(journal_lines.debit) as d, SUM(journal_lines.credit) as c')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->account_id => ['debit' => (int) $r->d, 'credit' => (int) $r->c]])
            ->all();
    }

    /** Combined balance (in each account's natural direction) of every account under the given sub-head kinds. */
    public function kindBalance(array $kinds, ?string $asOf = null): int
    {
        $totals = $this->totals($asOf);
        $sum = 0;

        ChartOfAccount::with('subhead.head')
            ->whereHas('subhead', fn ($q) => $q->whereIn('kind', $kinds))
            ->get()
            ->each(function (ChartOfAccount $a) use ($totals, &$sum) {
                $t = $totals[$a->id] ?? ['debit' => 0, 'credit' => 0];
                $sum += $a->isDebitNature() ? $t['debit'] - $t['credit'] : $t['credit'] - $t['debit'];
            });

        return $sum;
    }

    /** account_id => net debit (debit − credit) of every line tied to a record, optionally by voucher type. */
    protected function netByReference(object $reference, ?array $voucherTypes = null, ?int $accountId = null): array
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.reference_type', $reference->getMorphClass())
            ->where('journal_entries.reference_id', $reference->getKey())
            ->when($voucherTypes, fn ($q) => $q->whereIn('journal_entries.voucher_type', $voucherTypes))
            ->when($accountId, fn ($q) => $q->where('journal_lines.account_id', $accountId))
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id as account_id, SUM(journal_lines.debit) - SUM(journal_lines.credit) as net')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->account_id => (int) $r->net])
            ->all();
    }

    // ──────────────────────────── vehicle cost / vendor payable ────────────────────────────

    /**
     * Keeps the books equal to the vehicle's costing: each component (purchase price, vendor
     * commission, inland, auction, freight, misc) is debited to its own expense account and the
     * vendor's payable carries the total. Only the DIFFERENCE since the last posting is entered,
     * so it is safe to call after every costing, freight or shipment change.
     */
    public function syncVehicleCost(Vehicle $vehicle): ?JournalEntry
    {
        $vehicle->loadMissing('costing', 'vendor');

        if (! $vehicle->vendor || ! $vehicle->buying_price) {
            return null;
        }

        $c = $vehicle->costing;
        $components = [
            'vehicle_cost'           => (int) $vehicle->buying_price,
            'vendor_commission_cost' => (int) ($c?->vendor_commission_amount ?? 0),
            'inland_cost'            => (int) ($c?->inland_charges ?? 0),
            'auction_cost'           => (int) ($c?->auction_commission ?? 0),
            'freight_cost'           => (int) ($c?->freight_charges ?? 0),
            'misc_cost'              => (int) ($c?->misc_expenses ?? 0),
        ];

        $targets = [];
        foreach ($components as $key => $amount) {
            if ($amount > 0) {
                $id = $this->mapped($key)->id;
                $targets[$id] = ($targets[$id] ?? 0) + $amount;
            }
        }

        $vendorAccount = $this->ensureVendorAccount($vehicle->vendor);
        $current = $this->netByReference($vehicle);
        $plan = AccountingMath::costSyncLines($targets, $current, $vendorAccount->id, array_sum($targets));

        if (! $plan['lines']) {
            return null;
        }

        if ($plan['debit'] !== $plan['credit']) {
            throw new RuntimeException("Vehicle cost sync for {$vehicle->label()} would not balance (Dr {$plan['debit']} / Cr {$plan['credit']}).");
        }

        $lines = array_map(function (array $l) use ($vendorAccount, $vehicle) {
            if ($l['account_id'] === $vendorAccount->id) {
                $l['party'] = $vehicle->vendor;
            }
            return $l;
        }, $plan['lines']);

        // First posting is dated the day the vehicle was won; later corrections are dated today.
        $date = $current ? today()->toDateString() : ($vehicle->won_at?->toDateString() ?? today()->toDateString());

        return $this->post($date, "Vehicle cost — {$vehicle->label()}", $lines, $vehicle, false, 'payable');
    }

    /** Kept so existing callers (costing screen, bid won) keep working. */
    public function adjustVendorPayable(Vehicle $vehicle): ?JournalEntry
    {
        return $this->syncVehicleCost($vehicle);
    }

    // ──────────────────────────── sale invoices ────────────────────────────

    public function invoiceCreditAccount(Invoice $inv): ChartOfAccount
    {
        return $inv->isDepositInvoice() ? $this->mapped('customer_deposits') : $this->mapped('invoice_credit');
    }

    protected function invoiceDate(Invoice $inv): string
    {
        return $inv->issued_at?->toDateString() ?? today()->toDateString();
    }

    /** Dr customer receivable / Cr invoice-credit account. Income is not touched (default mapping). */
    public function invoiceReceivable(Invoice $inv): JournalEntry
    {
        $customerAccount = $this->ensureCustomerAccount($inv->customer);

        return $this->post($this->invoiceDate($inv), "Invoice {$inv->invoice_no} — {$inv->customer->name}", [
            ['account_id' => $customerAccount->id, 'debit' => $inv->total_payable, 'party' => $inv->customer],
            ['account_id' => $this->invoiceCreditAccount($inv)->id, 'credit' => $inv->total_payable],
        ], $inv, false, 'sale_invoice');
    }

    public function depositInvoiceReceivable(Invoice $inv): JournalEntry
    {
        $customerAccount = $this->ensureCustomerAccount($inv->customer);

        return $this->post($this->invoiceDate($inv), "Deposit invoice {$inv->invoice_no} — {$inv->customer->name}", [
            ['account_id' => $customerAccount->id, 'debit' => $inv->total_payable, 'party' => $inv->customer],
            ['account_id' => $this->mapped('customer_deposits')->id, 'credit' => $inv->total_payable, 'party' => $inv->customer],
        ], $inv, false, 'deposit_invoice');
    }

    /** After a discount / settled-amount change: post only the difference on the customer's receivable. */
    public function syncInvoiceReceivable(Invoice $inv): ?JournalEntry
    {
        $customerAccount = $this->ensureCustomerAccount($inv->customer);
        $posted = $this->netByReference($inv, ['sale_invoice', 'deposit_invoice', 'invoice_adjustment', 'reversal'], $customerAccount->id)[$customerAccount->id] ?? 0;
        $delta = AccountingMath::receivableDelta($posted, (int) $inv->total_payable);

        if ($delta === 0) {
            return null;
        }

        $credit = $this->invoiceCreditAccount($inv);
        $abs = abs($delta);

        return $this->post(today()->toDateString(), "Invoice {$inv->invoice_no} adjusted", [
            ['account_id' => $customerAccount->id, 'debit' => $delta > 0 ? $abs : 0, 'credit' => $delta < 0 ? $abs : 0, 'party' => $inv->customer],
            ['account_id' => $credit->id, 'debit' => $delta < 0 ? $abs : 0, 'credit' => $delta > 0 ? $abs : 0],
        ], $inv, false, 'invoice_adjustment');
    }

    /**
     * Sales income is recognised only as money is received. Moves income between the
     * invoice-credit (unearned) account and Sales Income until income equals the approved
     * amount received — in either direction, so reversals correct themselves.
     * Does nothing when both roles point at the same account (standard accrual mode).
     */
    public function syncRevenueRecognition(Invoice $inv, ?string $date = null): ?JournalEntry
    {
        if ($inv->isDepositInvoice() || $inv->status === 'cancelled') {
            return null;
        }

        $unearned = $this->mapped('invoice_credit');
        $sales    = $this->mapped('sales_income');

        if ($unearned->id === $sales->id) {
            return null;
        }

        $recognised = -($this->netByReference($inv, null, $sales->id)[$sales->id] ?? 0);
        $delta = AccountingMath::recognitionDelta($recognised, (int) $inv->amount_paid);

        if ($delta === 0) {
            return null;
        }

        $abs = abs($delta);

        return $this->post($date ?? today()->toDateString(), "Revenue recognised — invoice {$inv->invoice_no}", [
            ['account_id' => $unearned->id, 'debit' => $delta > 0 ? $abs : 0, 'credit' => $delta < 0 ? $abs : 0],
            ['account_id' => $sales->id, 'debit' => $delta < 0 ? $abs : 0, 'credit' => $delta > 0 ? $abs : 0],
        ], $inv, false, 'revenue_recognition');
    }

    // ──────────────────────────── money in / out ────────────────────────────

    public function customerPayment(Payment $p): JournalEntry
    {
        $customerAccount = $this->ensureCustomerAccount($p->customer);
        $money = $this->moneyAccount($p->account_id);

        return $this->post($p->paid_at->toDateString(), "Payment received — {$p->customer->name}", [
            ['account_id' => $money->id, 'debit' => $p->amount],
            ['account_id' => $customerAccount->id, 'credit' => $p->amount, 'party' => $p->customer],
        ], $p, $p->is_backdated, 'receipt');
    }

    /** Settles part of an invoice from the customer's held deposit. Tied to the Payment row so undo can find it. */
    public function applyDepositToInvoice(Invoice $invoice, int $amount, Payment $payment): JournalEntry
    {
        $customerAccount = $this->ensureCustomerAccount($invoice->customer);

        return $this->post(today()->toDateString(), "Security deposit applied to invoice {$invoice->invoice_no}", [
            ['account_id' => $this->mapped('customer_deposits')->id, 'debit' => $amount, 'party' => $invoice->customer],
            ['account_id' => $customerAccount->id, 'credit' => $amount, 'party' => $invoice->customer],
        ], $payment, false, 'deposit_adjustment');
    }

    public function vendorPayment(VendorPayment $vp): JournalEntry
    {
        $vendorAccount = $this->ensureVendorAccount($vp->vendor);
        $money = $this->moneyAccount($vp->account_id);

        return $this->post($vp->paid_at->toDateString(), "Vendor payment — vehicle #{$vp->vehicle_id}", [
            ['account_id' => $vendorAccount->id, 'debit' => $vp->amount, 'party' => $vp->vendor],
            ['account_id' => $money->id, 'credit' => $vp->amount],
        ], $vp, $vp->is_backdated, 'payment');
    }

    public function expense(Expense $e): JournalEntry
    {
        $key = Expense::CATEGORIES[$e->category]['key'] ?? 'expense_misc';
        $money = $this->moneyAccount($e->paid_from_account_id);

        return $this->post($e->expense_date->toDateString(), "Expense: {$e->category}", [
            ['account_id' => $this->mapped($key)->id, 'debit' => $e->amount],
            ['account_id' => $money->id, 'credit' => $e->amount],
        ], $e, $e->is_backdated, 'expense');
    }

    /** Legacy deposit flow: money straight into the deposit liability. */
    public function securityDeposit(Customer $c, ChartOfAccount $money): JournalEntry
    {
        return $this->post(today()->toDateString(), "Security deposit received — {$c->name}", [
            ['account_id' => $this->moneyAccount($money)->id, 'debit' => $c->security_deposit],
            ['account_id' => $this->mapped('customer_deposits')->id, 'credit' => $c->security_deposit, 'party' => $c],
        ], $c, false, 'deposit_receipt');
    }
}
