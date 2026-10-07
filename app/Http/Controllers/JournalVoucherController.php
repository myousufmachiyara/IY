<?php

namespace App\Http\Controllers;

use App\Models\{ChartOfAccount, Customer, JournalEntry, Vendor};
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class JournalVoucherController extends Controller
{
    public function create()
    {
        abort_unless(auth()->user()->can('accounting.create'), 403);

        $accounts = ChartOfAccount::with('subhead.head')->where('is_active', true)->orderBy('code')->get();

        return view('accounting.journal_voucher_create', compact('accounts'));
    }

    public function store(Request $request, LedgerService $ledger)
    {
        abort_unless($request->user()->can('accounting.create'), 403);

        $request->validate([
            'date'        => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        if (! $request->user()->isSuperAdmin() && ! Carbon::parse($request->date)->isToday()) {
            return back()->withErrors(['date' => 'Only Super Admin may use a date other than today.'])->withInput();
        }

        // Keep only rows that actually have an account and an amount.
        $rows = collect($request->input('lines', []))
            ->map(fn ($l) => [
                'account_id' => $l['account_id'] ?? null,
                'debit'      => (int) ($l['debit'] ?? 0),
                'credit'     => (int) ($l['credit'] ?? 0),
                'memo'       => $l['memo'] ?? null,
            ])
            ->filter(fn ($l) => $l['account_id'] && ($l['debit'] > 0 || $l['credit'] > 0))
            ->values();

        if ($rows->count() < 2) {
            return back()->withErrors(['lines' => 'A journal voucher needs at least two lines with an amount.'])->withInput();
        }

        if ($rows->contains(fn ($r) => $r['debit'] > 0 && $r['credit'] > 0)) {
            return back()->withErrors(['lines' => 'Each line must be either a debit or a credit, not both.'])->withInput();
        }

        $accounts = ChartOfAccount::where('is_active', true)
            ->whereIn('id', $rows->pluck('account_id')->unique())
            ->get()->keyBy('id');

        if ($accounts->count() !== $rows->pluck('account_id')->unique()->count()) {
            return back()->withErrors(['lines' => 'One or more selected accounts is invalid or inactive.'])->withInput();
        }

        $debit  = $rows->sum('debit');
        $credit = $rows->sum('credit');

        if ($debit !== $credit) {
            return back()->withErrors(['lines' => 'Voucher is out of balance: debits ¥' . number_format($debit) . ' vs credits ¥' . number_format($credit) . '.'])->withInput();
        }

        $lines = $rows->map(function ($r) use ($accounts) {
            $account = $accounts[$r['account_id']];
            $line = ['account_id' => $account->id, 'debit' => $r['debit'], 'credit' => $r['credit'], 'memo' => $r['memo']];

            // Posting to a customer/vendor sub-account tags the party, same as auto-posted entries.
            if ($account->customer_id && ($customer = Customer::find($account->customer_id))) {
                $line['party'] = $customer;
            } elseif ($account->vendor_id && ($vendor = Vendor::find($account->vendor_id))) {
                $line['party'] = $vendor;
            }

            return $line;
        })->all();

        $entry = $ledger->post(
            $request->date,
            $request->description,
            $lines,
            null,
            ! Carbon::parse($request->date)->isToday(),
            'journal'
        );

        return redirect()->route('accounting.journal')->with('success', "Journal voucher {$entry->entry_no} posted.");
    }

    /** Reverse a manually created voucher by posting an opposite entry; the original is never edited or deleted. */
    public function reverse(JournalEntry $journalEntry, LedgerService $ledger)
    {
        abort_unless(auth()->user()->can('accounting.delete'), 403);

        abort_unless(
            $journalEntry->reference_type === null && ! str_starts_with($journalEntry->description, 'Reversal of '),
            422,
            'Only manually created journal vouchers can be reversed here. Entries from invoices, payments and expenses are reversed from their own screens.'
        );

        abort_if(
            JournalEntry::where('description', "Reversal of {$journalEntry->entry_no}")->exists(),
            422,
            'This voucher has already been reversed.'
        );

        $reversal = $ledger->reverseEntry($journalEntry, now()->toDateString());

        return back()->with('success', "{$journalEntry->entry_no} reversed — {$reversal->entry_no} posted.");
    }
}
