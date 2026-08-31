<?php

namespace App\Services;

use App\Models\{ChartOfAccount, Customer, Expense, Invoice, JournalEntry, JournalLine, Payment, Vehicle, VendorPayment};
use Illuminate\Support\Facades\{Auth, DB};
use InvalidArgumentException;

class LedgerService
{
    public const CASH = '1000', BANK = '1010', AR = '1100', AP_VENDOR = '2000',
        CUST_DEPOSIT = '2100', SALES_INCOME = '4000', COST_VEHICLES = '5000',
        FREIGHT = '5100', INLAND = '5200', AUCTION = '5300', VENDOR_COMM = '5400',
        SALARY = '5500', OFFICE = '5600', MISC = '5900';

    protected array $cache = [];

    public function account(string $code): ChartOfAccount
    {
        return $this->cache[$code] ??= ChartOfAccount::where('code', $code)->firstOrFail();
    }

    public function post(string $date, string $description, array $lines, ?object $reference = null, bool $backdated = false): JournalEntry
    {
        $debit  = array_sum(array_column($lines, 'debit'));
        $credit = array_sum(array_column($lines, 'credit'));

        if ($debit !== $credit || $debit === 0) {
            throw new InvalidArgumentException("Unbalanced journal entry: debit {$debit} ≠ credit {$credit}");
        }

        return DB::transaction(function () use ($date, $description, $lines, $reference, $backdated) {
            $entry = JournalEntry::create([
                'entry_no'       => $this->nextNo(),
                'date'           => $date,
                'description'    => $description,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id'   => $reference?->getKey(),
                'is_backdated'   => $backdated,
                'created_by'     => Auth::id(),
            ]);

            foreach ($lines as $l) {
                $party = $l['party'] ?? null;
                $entry->lines()->create([
                    'account_id' => $this->account($l['account'])->id,
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

    public function securityDeposit(Customer $c, string $cashAccount = self::BANK): JournalEntry
    {
        return $this->post(today()->toDateString(), "Security deposit received — {$c->name}", [
            ['account' => $cashAccount,        'debit'  => $c->security_deposit],
            ['account' => self::CUST_DEPOSIT,  'credit' => $c->security_deposit, 'party' => $c],
        ], $c);
    }

    /**
     * Post (or top-up) the vendor payable for a vehicle so it always matches the
     * CURRENT total costing (buying price + vendor commission + inland + auction +
     * freight + misc) — not just the raw buying price. Safe to call repeatedly:
     * only the DIFFERENCE between what's already posted and what should now be
     * posted gets entered, so calling this after every costing edit keeps the
     * ledger exactly in sync without ever reversing or double-posting.
     */
    public function adjustVendorPayable(Vehicle $vehicle): ?JournalEntry
    {
        $vehicle->loadMissing('costing', 'vendor');
        $targetPayable = $vehicle->costing?->total_costing ?? $vehicle->buying_price;

        $apAccount = $this->account(self::AP_VENDOR);

        $currentlyPosted = (int) JournalLine::whereHas('entry', fn ($q) => $q
                ->where('reference_type', $vehicle->getMorphClass())
                ->where('reference_id', $vehicle->id))
            ->where('account_id', $apAccount->id)
            ->get()
            ->sum(fn ($l) => $l->credit - $l->debit);

        $delta = $targetPayable - $currentlyPosted;

        if ($delta === 0) {
            return null;
        }

        if ($delta > 0) {
            return $this->post(now()->toDateString(), "Vendor payable — {$vehicle->label()} (total costing)", [
                ['account' => self::COST_VEHICLES, 'debit'  => $delta],
                ['account' => self::AP_VENDOR,      'credit' => $delta, 'party' => $vehicle->vendor],
            ], $vehicle);
        }

        $delta = abs($delta);
        return $this->post(now()->toDateString(), "Vendor payable correction — {$vehicle->label()} (total costing decreased)", [
            ['account' => self::AP_VENDOR,      'debit'  => $delta, 'party' => $vehicle->vendor],
            ['account' => self::COST_VEHICLES, 'credit' => $delta],
        ], $vehicle);
    }

    public function invoiceReceivable(Invoice $inv): JournalEntry
    {
        return $this->post(today()->toDateString(), "Invoice {$inv->invoice_no} — {$inv->customer->name}", [
            ['account' => self::AR,           'debit'  => $inv->total_payable, 'party' => $inv->customer],
            ['account' => self::SALES_INCOME, 'credit' => $inv->total_payable],
        ], $inv);
    }

    public function customerPayment(Payment $p, string $cashAccount = self::BANK): JournalEntry
    {
        return $this->post($p->paid_at->toDateString(), "Payment received — {$p->customer->name}", [
            ['account' => $cashAccount, 'debit'  => $p->amount],
            ['account' => self::AR,     'credit' => $p->amount, 'party' => $p->customer],
        ], $p, $p->is_backdated);
    }

    public function vendorPayment(VendorPayment $vp, string $cashAccount = self::BANK): JournalEntry
    {
        return $this->post($vp->paid_at->toDateString(), "Vendor payment — vehicle #{$vp->vehicle_id}", [
            ['account' => self::AP_VENDOR, 'debit'  => $vp->amount, 'party' => $vp->vendor],
            ['account' => $cashAccount,    'credit' => $vp->amount],
        ], $vp, $vp->is_backdated);
    }

    public function expense(Expense $e, string $cashAccount = self::BANK): JournalEntry
    {
        $account = ['salary' => self::SALARY, 'office' => self::OFFICE][$e->category] ?? self::MISC;

        return $this->post($e->expense_date->toDateString(), "Expense: {$e->category}", [
            ['account' => $account,     'debit'  => $e->amount],
            ['account' => $cashAccount, 'credit' => $e->amount],
        ], $e, $e->is_backdated);
    }

    public function reverseEntry(JournalEntry $original, string $date, ?string $description = null): JournalEntry
    {
        return DB::transaction(function () use ($original, $date, $description) {
            $entry = JournalEntry::create([
                'entry_no'       => $this->nextNo(),
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

    public function applyDepositToInvoice(Invoice $invoice, int $amount): JournalEntry
    {
        return $this->post(now()->toDateString(), "Security deposit applied to invoice {$invoice->invoice_no}", [
            ['account' => self::CUST_DEPOSIT, 'debit'  => $amount, 'party' => $invoice->customer],
            ['account' => self::AR,           'credit' => $amount, 'party' => $invoice->customer],
        ], $invoice);
    }
}