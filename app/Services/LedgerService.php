<?php

namespace App\Services;

use App\Models\{ChartOfAccount, Customer, Expense, Invoice, JournalEntry, JournalLine, Payment, Vehicle, Vendor, VendorPayment};
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

    /**
     * Every customer gets their own real sub-account under Accounts Receivable —
     * not a shared control account with party tagging. Idempotent: safe to call
     * on every posting, only creates the row the first time.
     */
    public function ensureCustomerAccount(Customer $customer): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'code'         => self::AR . '-' . $customer->id,
                'account_code' => self::AR . '-' . $customer->id,
                'name'         => 'Receivable — ' . $customer->name,
                'type'         => 'customer',
                'parent_id'    => $this->account(self::AR)->id,
                'is_system'    => false,
                'is_active'    => true,
            ]
        );
    }

    /** Same idea, for vendors under Accounts Payable. */
    public function ensureVendorAccount(Vendor $vendor): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'code'         => self::AP_VENDOR . '-' . $vendor->id,
                'account_code' => self::AP_VENDOR . '-' . $vendor->id,
                'name'         => 'Payable — ' . $vendor->name,
                'type'         => 'vendor',
                'parent_id'    => $this->account(self::AP_VENDOR)->id,
                'is_system'    => false,
                'is_active'    => true,
            ]
        );
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
                $accountId = $l['account_id'] ?? $this->account($l['account'])->id;
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

    public function securityDeposit(Customer $c, string $cashAccount = self::BANK): JournalEntry
    {
        return $this->post(today()->toDateString(), "Security deposit received — {$c->name}", [
            ['account' => $cashAccount,        'debit'  => $c->security_deposit],
            ['account' => self::CUST_DEPOSIT,  'credit' => $c->security_deposit, 'party' => $c],
        ], $c);
    }

    public function adjustVendorPayable(Vehicle $vehicle): ?JournalEntry
    {
        $vehicle->loadMissing('costing', 'vendor');
        $targetPayable = $vehicle->costing?->total_costing ?? $vehicle->buying_price;

        $vendorAccount = $this->ensureVendorAccount($vehicle->vendor);

        $currentlyPosted = (int) JournalLine::whereHas('entry', fn ($q) => $q
                ->where('reference_type', $vehicle->getMorphClass())
                ->where('reference_id', $vehicle->id))
            ->where('account_id', $vendorAccount->id)
            ->get()
            ->sum(fn ($l) => $l->credit - $l->debit);

        $delta = $targetPayable - $currentlyPosted;

        if ($delta === 0) {
            return null;
        }

        if ($delta > 0) {
            return $this->post(now()->toDateString(), "Vendor payable — {$vehicle->label()} (total costing)", [
                ['account' => self::COST_VEHICLES, 'debit'  => $delta],
                ['account_id' => $vendorAccount->id, 'credit' => $delta, 'party' => $vehicle->vendor],
            ], $vehicle);
        }

        $delta = abs($delta);
        return $this->post(now()->toDateString(), "Vendor payable correction — {$vehicle->label()} (total costing decreased)", [
            ['account_id' => $vendorAccount->id, 'debit'  => $delta, 'party' => $vehicle->vendor],
            ['account' => self::COST_VEHICLES, 'credit' => $delta],
        ], $vehicle);
    }

    public function invoiceReceivable(Invoice $inv): JournalEntry
    {
        $customerAccount = $this->ensureCustomerAccount($inv->customer);

        return $this->post(today()->toDateString(), "Invoice {$inv->invoice_no} — {$inv->customer->name}", [
            ['account_id' => $customerAccount->id, 'debit'  => $inv->total_payable, 'party' => $inv->customer],
            ['account' => self::SALES_INCOME,       'credit' => $inv->total_payable],
        ], $inv);
    }

    public function depositInvoiceReceivable(Invoice $inv): JournalEntry
    {
        $customerAccount = $this->ensureCustomerAccount($inv->customer);

        return $this->post(today()->toDateString(), "Deposit invoice {$inv->invoice_no} — {$inv->customer->name}", [
            ['account_id' => $customerAccount->id, 'debit'  => $inv->total_payable, 'party' => $inv->customer],
            ['account' => self::CUST_DEPOSIT,       'credit' => $inv->total_payable, 'party' => $inv->customer],
        ], $inv);
    }

    public function customerPayment(Payment $p, string $cashAccount = self::BANK): JournalEntry
    {
        $customerAccount = $this->ensureCustomerAccount($p->customer);

        return $this->post($p->paid_at->toDateString(), "Payment received — {$p->customer->name}", [
            ['account' => $cashAccount,          'debit'  => $p->amount],
            ['account_id' => $customerAccount->id, 'credit' => $p->amount, 'party' => $p->customer],
        ], $p, $p->is_backdated);
    }

    public function applyDepositToInvoice(Invoice $invoice, int $amount): JournalEntry
    {
        $customerAccount = $this->ensureCustomerAccount($invoice->customer);

        return $this->post(now()->toDateString(), "Security deposit applied to invoice {$invoice->invoice_no}", [
            ['account' => self::CUST_DEPOSIT,       'debit'  => $amount, 'party' => $invoice->customer],
            ['account_id' => $customerAccount->id,  'credit' => $amount, 'party' => $invoice->customer],
        ], $invoice);
    }

    public function vendorPayment(VendorPayment $vp, string $cashAccount = self::BANK): JournalEntry
    {
        $vendorAccount = $this->ensureVendorAccount($vp->vendor);

        return $this->post($vp->paid_at->toDateString(), "Vendor payment — vehicle #{$vp->vehicle_id}", [
            ['account_id' => $vendorAccount->id, 'debit'  => $vp->amount, 'party' => $vp->vendor],
            ['account' => $cashAccount,          'credit' => $vp->amount],
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
}