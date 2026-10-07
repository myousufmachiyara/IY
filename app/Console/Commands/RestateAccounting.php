<?php

namespace App\Console\Commands;

use App\Models\{AccountMapping, ChartOfAccount, Customer, Invoice, JournalEntry, JournalLine, Payment, Vehicle, Vendor};
use App\Services\{AccountStructure, LedgerService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Schema};
use Throwable;

/**
 * Brings historical postings in line with the new accounting rules. Nothing is edited or
 * deleted — every correction is a normal, balanced journal entry. Safe to run repeatedly.
 */
class RestateAccounting extends Command
{
    protected $signature = 'accounting:restate {--apply : Write the changes. Without this flag everything is rolled back (dry run).}';

    protected $description = 'Restate existing ledger data: classify accounts, split vehicle costs, move unearned sales out of income, repoint party balances.';

    public function handle(LedgerService $ledger): int
    {
        if (! Schema::hasTable('account_mappings')) {
            $this->error('Run "php artisan migrate" first — the new account tables do not exist yet.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'APPLYING changes…' : 'DRY RUN — nothing will be saved. Re-run with --apply to write it.');

        $startId = (int) JournalEntry::max('id');
        [$dr0, $cr0] = $this->ledgerTotals();

        DB::beginTransaction();

        try {
            AccountStructure::install();
            AccountMapping::flush();

            $relinked   = $this->relinkDepositAdjustments();
            $repointed  = $this->repointPartyLines($ledger);
            $vehicles   = $this->restateVehicleCosts($ledger);
            $invoices   = $this->restateRevenue($ledger);

            [$dr1, $cr1] = $this->ledgerTotals();

            if ($dr1 !== $cr1) {
                throw new \RuntimeException("Ledger would be out of balance (Dr {$dr1} / Cr {$cr1}). Nothing was saved.");
            }

            $entries = JournalEntry::with('lines')->where('id', '>', $startId)->get();

            $this->newLine();
            $this->line("Legacy deposit adjustments re-linked to their payment : {$relinked}");
            $this->line("Customer / vendor ledger lines repointed to own account: {$repointed}");
            $this->line("Vehicles whose cost was re-split across accounts       : {$vehicles}");
            $this->line("Sale invoices whose revenue was restated               : {$invoices}");
            $this->newLine();

            if ($entries->isEmpty()) {
                $this->info('No journal entries needed — the books already follow the new rules.');
            } else {
                $this->table(['Voucher type', 'Entries', 'Total debited (¥)'],
                    $entries->groupBy('voucher_type')->map(fn ($g, $type) => [
                        JournalEntry::VOUCHER_TYPES[$type] ?? $type, $g->count(), number_format($g->sum(fn ($e) => $e->totalDebit())),
                    ])->values()->all());
            }

            $this->line('Ledger before: Dr ' . number_format($dr0) . ' / Cr ' . number_format($cr0));
            $this->line('Ledger after : Dr ' . number_format($dr1) . ' / Cr ' . number_format($cr1) . '  → balanced');

            if ($apply) {
                DB::commit();
                $this->info('Done. Changes saved.');
            } else {
                DB::rollBack();
                $this->warn('Dry run complete — nothing was saved. Review the above, then run: php artisan accounting:restate --apply');
            }
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        } finally {
            AccountMapping::flush();
        }

        return self::SUCCESS;
    }

    private function ledgerTotals(): array
    {
        return [(int) JournalLine::sum('debit'), (int) JournalLine::sum('credit')];
    }

    /** Older deposit adjustments were tied to the invoice instead of the Payment, so "undo" could not find them. */
    private function relinkDepositAdjustments(): int
    {
        $n = 0;
        $invoiceClass = (new Invoice)->getMorphClass();

        Payment::where('method', 'deposit')->whereNotNull('invoice_id')->get()->each(function (Payment $p) use (&$n, $invoiceClass) {
            if ($p->journalEntries()->exists()) {
                return;
            }

            $entry = JournalEntry::where('reference_type', $invoiceClass)
                ->where('reference_id', $p->invoice_id)
                ->where('voucher_type', 'deposit_adjustment')
                ->get()
                ->first(fn ($e) => $e->totalDebit() === (int) $p->amount);

            if ($entry) {
                $entry->update(['reference_type' => $p->getMorphClass(), 'reference_id' => $p->id]);
                $n++;
            }
        });

        return $n;
    }

    /** Lines still sitting on a shared control account (e.g. 1100 / 2000) move to the party's own account. */
    private function repointPartyLines(LedgerService $ledger): int
    {
        $n = 0;

        $customerControl = ChartOfAccount::whereNull('customer_id')->whereHas('subhead', fn ($q) => $q->where('kind', 'customer'))->pluck('id');
        JournalLine::whereIn('account_id', $customerControl)->where('party_type', (new Customer)->getMorphClass())->get()
            ->each(function (JournalLine $l) use ($ledger, &$n) {
                if ($c = Customer::find($l->party_id)) {
                    $l->update(['account_id' => $ledger->ensureCustomerAccount($c)->id]);
                    $n++;
                }
            });

        $vendorControl = ChartOfAccount::whereNull('vendor_id')->whereHas('subhead', fn ($q) => $q->where('kind', 'vendor'))->pluck('id');
        JournalLine::whereIn('account_id', $vendorControl)->where('party_type', (new Vendor)->getMorphClass())->get()
            ->each(function (JournalLine $l) use ($ledger, &$n) {
                if ($v = Vendor::find($l->party_id)) {
                    $l->update(['account_id' => $ledger->ensureVendorAccount($v)->id]);
                    $n++;
                }
            });

        return $n;
    }

    /** Debit each cost component to its own expense account instead of one lump on Cost of Vehicles. */
    private function restateVehicleCosts(LedgerService $ledger): int
    {
        $n = 0;

        Vehicle::with('costing', 'vendor')
            ->whereNotNull('vendor_id')->whereNotNull('buying_price')
            ->whereIn('status', ['won', 'invoiced', 'dispatched', 'arrived', 'delivered'])
            ->get()
            ->each(function (Vehicle $v) use ($ledger, &$n) {
                if ($ledger->syncVehicleCost($v)) {
                    $n++;
                }
            });

        return $n;
    }

    /** Income only for money actually received; the rest of each invoice moves to the unearned account. */
    private function restateRevenue(LedgerService $ledger): int
    {
        $n = 0;

        Invoice::with('customer')
            ->where(fn ($q) => $q->where('invoice_type', '!=', 'deposit')->orWhereNull('invoice_type'))
            ->where('status', '!=', 'cancelled')
            ->get()
            ->each(function (Invoice $inv) use ($ledger, &$n) {
                if ($ledger->syncRevenueRecognition($inv, $inv->issued_at?->toDateString())) {
                    $n++;
                }
            });

        return $n;
    }
}
