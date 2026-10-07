<?php

namespace App\Http\Controllers;

use App\Models\{AccountHead, AccountMapping, AccountSubhead, ChartOfAccount, Customer, Expense, Invoice, JournalEntry, JournalLine, Payment, Vendor, VendorPayment};
use App\Services\LedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountingController extends Controller
{
    public function __construct(private LedgerService $ledger) {}

    private function refuse(string $message)
    {
        return back()->with('error', $message);
    }

    // ═══════════════════════════ Chart of accounts: Head → Sub-head → Account ═══════════════════════════

    public function chartOfAccounts()
    {
        $totals  = $this->ledger->totals();
        $mapped  = AccountMapping::pluck('account_id')->flip()->all();
        $heads   = AccountHead::with('subheads.accounts')->orderBy('sort_order')->get();

        foreach ($heads as $head) {
            $head->total = 0;
            foreach ($head->subheads as $sub) {
                $sub->total = 0;
                foreach ($sub->accounts as $a) {
                    $t = $totals[$a->id] ?? ['debit' => 0, 'credit' => 0];
                    $a->current_balance = $a->isDebitNature() ? $t['debit'] - $t['credit'] : $t['credit'] - $t['debit'];
                    $a->has_lines = isset($totals[$a->id]);
                    $a->is_mapped = isset($mapped[$a->id]);
                    $sub->total += $a->current_balance;
                }
                $head->total += $sub->total;
            }
        }

        $unclassified = ChartOfAccount::whereNull('subhead_id')->orderBy('code')->get();
        $subheadOptions = AccountSubhead::with('head')->orderBy('sort_order')->get();

        return view('accounting.chart', compact('heads', 'unclassified', 'subheadOptions'));
    }

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'code'       => ['required', 'string', 'max:30', 'unique:chart_of_accounts,code'],
            'name'       => ['required', 'string', 'max:255'],
            'subhead_id' => ['required', 'exists:account_subheads,id'],
        ]);

        $sub = AccountSubhead::with('head')->findOrFail($data['subhead_id']);

        if ($sub->isParty()) {
            return $this->refuse('Customer and vendor accounts are created automatically with each customer / vendor — add other accounts under a different sub-head.');
        }

        ChartOfAccount::create([
            'code' => $data['code'], 'account_code' => $data['code'], 'name' => $data['name'],
            'subhead_id' => $sub->id, 'type' => $sub->head->nature,
            'is_system' => false, 'is_active' => true,
        ]);

        return back()->with('success', 'Account created.');
    }

    public function updateAccount(Request $request, ChartOfAccount $account)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'code'       => ['required', 'string', 'max:30', Rule::unique('chart_of_accounts', 'code')->ignore($account->id)],
            'subhead_id' => ['required', 'exists:account_subheads,id'],
        ]);

        $sub = AccountSubhead::with('head')->findOrFail($data['subhead_id']);
        $active = $request->boolean('is_active');

        if ($sub->id !== $account->subhead_id) {
            if ($account->isParty()) {
                return $this->refuse('Customer and vendor accounts stay in their own sub-head.');
            }
            if ($sub->isParty()) {
                return $this->refuse('Only customer / vendor accounts can sit in a customer / vendor sub-head.');
            }
            if ($account->hasTransactions() && $sub->head->nature !== $account->type) {
                return $this->refuse('This account has transactions, so it cannot move to a different head — that would flip the sign of its history. Move it within the same head, or create a new account.');
            }
        }

        if (! $active && $account->is_active && ($roles = $account->mappedRoles())) {
            return $this->refuse('Cannot deactivate — this account is used for: ' . implode(', ', $roles) . '. Re-map those roles first.');
        }

        $account->update([
            'name' => $data['name'], 'code' => $data['code'], 'account_code' => $data['code'],
            'subhead_id' => $sub->id, 'type' => $sub->head->nature, 'is_active' => $active,
        ]);

        return back()->with('success', 'Account updated.');
    }

    public function destroyAccount(ChartOfAccount $account)
    {
        if ($account->hasTransactions()) {
            return $this->refuse("\"{$account->name}\" has posted transactions and cannot be deleted. Deactivate it instead, or reverse its entries first.");
        }

        if ($roles = $account->mappedRoles()) {
            return $this->refuse("\"{$account->name}\" is used for: " . implode(', ', $roles) . '. Re-map those roles on the Account Mapping page, then delete it.');
        }

        $uses = Payment::where('account_id', $account->id)->exists()
            || VendorPayment::where('account_id', $account->id)->exists()
            || Expense::where('paid_from_account_id', $account->id)->exists()
            || Customer::where('security_deposit_account_id', $account->id)->exists();

        if ($uses) {
            return $this->refuse("\"{$account->name}\" is still selected on existing payments, expenses or deposits. Deactivate it instead.");
        }

        $account->delete();

        return back()->with('success', "\"{$account->name}\" deleted.");
    }

    public function storeSubhead(Request $request)
    {
        $data = $request->validate([
            'head_id' => ['required', 'exists:account_heads,id'],
            'code'    => ['required', 'string', 'max:20', 'unique:account_subheads,code'],
            'name'    => ['required', 'string', 'max:255'],
        ]);

        AccountSubhead::create($data + ['kind' => null, 'sort_order' => (int) preg_replace('/\D/', '', $data['code']) ?: 99]);

        return back()->with('success', 'Sub-head created.');
    }

    public function updateSubhead(Request $request, AccountSubhead $subhead)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('account_subheads', 'code')->ignore($subhead->id)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $subhead->update($data);

        return back()->with('success', 'Sub-head updated.');
    }

    public function destroySubhead(AccountSubhead $subhead)
    {
        if ($subhead->kind) {
            return $this->refuse("\"{$subhead->name}\" drives cash/bank dropdowns or customer/vendor ledgers and cannot be deleted.");
        }
        if ($subhead->accounts()->exists()) {
            return $this->refuse('Move or delete the accounts inside this sub-head first.');
        }

        $subhead->delete();

        return back()->with('success', 'Sub-head deleted.');
    }

    // ═══════════════════════════ Account mapping ═══════════════════════════

    private function mappingAllows(ChartOfAccount $a, array $meta): bool
    {
        $nature = $a->subhead?->head->nature ?? $a->type;

        return $a->is_active && ! $a->isParty()
            && in_array($nature, $meta['natures'], true)
            && (empty($meta['money']) || $a->isMoneyAccount());
    }

    public function mappings()
    {
        $accounts = ChartOfAccount::with('subhead.head')->where('is_active', true)->orderBy('code')->get();
        $current  = AccountMapping::pluck('account_id', 'key')->all();

        $groups = collect(AccountMapping::KEYS)->map(fn ($meta, $key) => $meta + [
            'key'        => $key,
            'account_id' => $current[$key] ?? null,
            'options'    => $accounts->filter(fn ($a) => $this->mappingAllows($a, $meta))->values(),
        ])->groupBy('group');

        return view('accounting.mappings', compact('groups'));
    }

    public function updateMappings(Request $request)
    {
        $input = $request->input('map', []);
        $new = [];

        foreach (AccountMapping::KEYS as $key => $meta) {
            $account = ChartOfAccount::with('subhead.head')->find($input[$key] ?? null);

            if (! $account) {
                return $this->refuse("Select an account for \"{$meta['label']}\".");
            }
            if (! $this->mappingAllows($account, $meta)) {
                return $this->refuse("\"{$account->name}\" cannot be used for \"{$meta['label']}\" — wrong type of account.");
            }
            $new[$key] = $account->id;
        }

        DB::transaction(function () use ($new) {
            foreach ($new as $key => $id) {
                AccountMapping::updateOrCreate(['key' => $key], ['account_id' => $id]);
            }
        });

        AccountMapping::flush();

        return back()->with('success', 'Account mapping saved.');
    }

    // ═══════════════════════════ Journal / ledgers ═══════════════════════════

    public function journal(Request $request)
    {
        $entries = JournalEntry::with('lines.account')
            ->when($request->from, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->latest('date')->latest('id')
            ->get();

        return view('accounting.journal', compact('entries'));
    }

    /** Opening balance (everything before $from) plus the lines inside the window, with a running balance. */
    private function ledgerLines(array $accountIds, ?string $from, ?string $to, bool $debitNature): array
    {
        $opening = 0;

        if ($from) {
            $r = JournalLine::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->whereIn('journal_lines.account_id', $accountIds)
                ->whereDate('journal_entries.date', '<', $from)
                ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')
                ->first();
            $opening = $debitNature ? (int) $r->d - (int) $r->c : (int) $r->c - (int) $r->d;
        }

        $lines = JournalLine::with('entry', 'account')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $accountIds)
            ->when($from, fn ($q) => $q->whereDate('journal_entries.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('journal_entries.date', '<=', $to))
            ->orderBy('journal_entries.date')->orderBy('journal_lines.id')
            ->select('journal_lines.*')
            ->get();

        $running = $opening;
        $lines = $lines->map(function ($l) use (&$running, $debitNature) {
            $running += $debitNature ? $l->debit - $l->credit : $l->credit - $l->debit;
            $l->running_balance = $running;
            return $l;
        });

        return [$opening, $lines];
    }

    public function ledger(Request $request, ChartOfAccount $account)
    {
        [$opening, $lines] = $this->ledgerLines([$account->id], $request->from, $request->to, $account->isDebitNature());

        return view('accounting.ledger', compact('account', 'lines', 'opening'));
    }

    public function partyLedger(Request $request)
    {
        $type = $request->party_type === 'vendor' ? 'vendor' : 'customer';
        $accounts = ChartOfAccount::whereNotNull($type . '_id')->orderBy('name')->get();

        $selected = null;
        $lines = collect();
        $opening = 0;

        if ($request->account_id) {
            $selected = $accounts->firstWhere('id', (int) $request->account_id);
            abort_unless($selected, 422, 'Selected account does not match the chosen party type.');
            [$opening, $lines] = $this->ledgerLines([$selected->id], $request->from, $request->to, $selected->isDebitNature());
        }

        return view('accounting.party_ledger', compact('accounts', 'selected', 'lines', 'type', 'opening'));
    }

    private function moneyBook(Request $request, array $kinds, string $view, string $title)
    {
        $accounts = ChartOfAccount::with('subhead')->whereHas('subhead', fn ($q) => $q->whereIn('kind', $kinds))->orderBy('code')->get();
        $selected = $request->account_id ? $accounts->firstWhere('id', (int) $request->account_id) : null;
        $ids = $selected ? [$selected->id] : $accounts->pluck('id')->all();

        [$opening, $lines] = $this->ledgerLines($ids, $request->from, $request->to, true);
        $closing = $lines->isNotEmpty() ? $lines->last()->running_balance : $opening;

        return view($view, compact('accounts', 'selected', 'lines', 'opening', 'closing', 'title'));
    }

    public function cashBankBook(Request $request) { return $this->moneyBook($request, ['cash', 'bank'], 'accounting.cash_bank_book', 'Cash & Bank Book'); }
    public function cashBook(Request $request)     { return $this->moneyBook($request, ['cash'], 'accounting.cash_book', 'Cash Book'); }
    public function bankBook(Request $request)     { return $this->moneyBook($request, ['bank'], 'accounting.bank_book', 'Bank Book'); }

    public function dayBook(Request $request)
    {
        $entries = JournalEntry::with('lines.account')
            ->when($request->from, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($request->voucher_type, fn ($q, $v) => $q->where('voucher_type', $v))
            ->orderBy('date')->orderBy('id')
            ->get();

        $voucherTypes = JournalEntry::VOUCHER_TYPES;

        return view('accounting.day_book', compact('entries', 'voucherTypes'));
    }

    public function printVoucher(JournalEntry $journalEntry)
    {
        $journalEntry->load('lines.account', 'creator');

        return Pdf::loadView('accounting.voucher_print', compact('journalEntry'))
            ->download("{$journalEntry->entry_no}.pdf");
    }

    // ═══════════════════════════ Receivables / payables (from the party accounts) ═══════════════════════════

    public function receivables()
    {
        $totals = $this->ledger->totals();

        $customers = ChartOfAccount::with('customer')
            ->whereHas('subhead', fn ($q) => $q->where('kind', 'customer'))->get()
            ->map(function ($a) use ($totals) {
                $t = $totals[$a->id] ?? ['debit' => 0, 'credit' => 0];
                return ['account' => $a, 'customer' => $a->customer, 'name' => $a->customer?->name ?? $a->name,
                        'invoiced' => $t['debit'], 'paid' => $t['credit'], 'balance' => $t['debit'] - $t['credit']];
            })
            ->filter(fn ($r) => $r['balance'] !== 0)->sortByDesc('balance')->values();

        return view('accounting.receivables', compact('customers'));
    }

    public function payables()
    {
        $totals = $this->ledger->totals();

        $vendors = ChartOfAccount::with('vendor')
            ->whereHas('subhead', fn ($q) => $q->where('kind', 'vendor'))->get()
            ->map(function ($a) use ($totals) {
                $t = $totals[$a->id] ?? ['debit' => 0, 'credit' => 0];
                return ['account' => $a, 'vendor' => $a->vendor, 'name' => $a->vendor?->name ?? $a->name,
                        'payable' => $t['credit'], 'paid' => $t['debit'], 'balance' => $t['credit'] - $t['debit']];
            })
            ->filter(fn ($r) => $r['balance'] !== 0)->sortByDesc('balance')->values();

        return view('accounting.payables', compact('vendors'));
    }

    public function receivablesAging()
    {
        $rows = Invoice::whereIn('status', ['issued', 'partial'])
            ->with('customer')->get()
            ->filter(fn ($i) => $i->balance() > 0)
            ->map(function ($i) {
                $dueDate = $i->due_final ?? $i->due_first ?? $i->issued_at;
                $daysOverdue = $dueDate ? (int) (now()->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false) * -1) : 0;
                return [
                    'invoice'      => $i,
                    'balance'      => $i->balance(),
                    'days_overdue' => max($daysOverdue, 0),
                    'bucket'       => match (true) {
                        $daysOverdue <= 0   => 'current',
                        $daysOverdue <= 30  => '0_30',
                        $daysOverdue <= 60  => '31_60',
                        $daysOverdue <= 90  => '61_90',
                        default             => 'over_90',
                    },
                ];
            });

        $buckets = [
            'current' => $rows->where('bucket', 'current')->sum('balance'),
            '0_30'    => $rows->where('bucket', '0_30')->sum('balance'),
            '31_60'   => $rows->where('bucket', '31_60')->sum('balance'),
            '61_90'   => $rows->where('bucket', '61_90')->sum('balance'),
            'over_90' => $rows->where('bucket', 'over_90')->sum('balance'),
        ];

        return view('accounting.receivables_aging', compact('rows', 'buckets'));
    }

    // ═══════════════════════════ Financial statements ═══════════════════════════

    public function profitLoss(Request $request)
    {
        $from = $request->from ?: now()->startOfYear()->toDateString();
        $to   = $request->to   ?: now()->toDateString();

        $totals = $this->ledger->totals($to, $from);
        $income = $expense = [];
        $totalIncome = $totalExpense = 0;

        foreach (AccountHead::with('subheads.accounts')->whereIn('nature', ['income', 'expense'])->orderBy('sort_order')->get() as $head) {
            foreach ($head->subheads as $sub) {
                $rows = [];
                $sum = 0;
                foreach ($sub->accounts as $a) {
                    $t = $totals[$a->id] ?? null;
                    if (! $t) continue;
                    $amount = $head->nature === 'income' ? $t['credit'] - $t['debit'] : $t['debit'] - $t['credit'];
                    if ($amount === 0) continue;
                    $rows[] = ['name' => $a->name, 'code' => $a->code, 'amount' => $amount];
                    $sum += $amount;
                }
                if (! $rows) continue;

                if ($head->nature === 'income') { $income[] = ['name' => $sub->name, 'amount' => $sum, 'rows' => $rows]; $totalIncome += $sum; }
                else                            { $expense[] = ['name' => $sub->name, 'amount' => $sum, 'rows' => $rows]; $totalExpense += $sum; }
            }
        }

        $netProfit = $totalIncome - $totalExpense;

        return view('accounting.profit_loss', compact('income', 'expense', 'totalIncome', 'totalExpense', 'netProfit', 'from', 'to'));
    }

    public function trialBalance(Request $request)
    {
        $asOf   = $request->as_of ?: today()->toDateString();
        $totals = $this->ledger->totals($asOf);
        $rows = [];
        $totalDebit = $totalCredit = 0;

        foreach (AccountHead::with('subheads.accounts')->orderBy('sort_order')->get() as $head) {
            $headRows = [];

            foreach ($head->subheads as $sub) {
                $subRows = [];

                if ($sub->isParty()) {
                    $net = 0; $n = 0;
                    foreach ($sub->accounts as $a) {
                        if (! isset($totals[$a->id])) continue;
                        $net += $totals[$a->id]['debit'] - $totals[$a->id]['credit'];
                        $n++;
                    }
                    if ($net !== 0) {
                        $subRows[] = ['kind' => 'party', 'code' => $sub->code, 'name' => "{$sub->name} — {$n} accounts", 'debit' => max($net, 0), 'credit' => max(-$net, 0)];
                    }
                } else {
                    foreach ($sub->accounts as $a) {
                        if (! isset($totals[$a->id])) continue;
                        $net = $totals[$a->id]['debit'] - $totals[$a->id]['credit'];
                        if ($net === 0) continue;
                        $subRows[] = ['kind' => 'account', 'code' => $a->code, 'name' => $a->name, 'debit' => max($net, 0), 'credit' => max(-$net, 0)];
                    }
                }

                if ($subRows) {
                    $headRows[] = ['kind' => 'subhead', 'name' => $sub->name];
                    array_push($headRows, ...$subRows);
                }
            }

            if ($headRows) {
                $rows[] = ['kind' => 'head', 'name' => $head->name];
                array_push($rows, ...$headRows);
            }
        }

        // Accounts that somehow have no sub-head still count — the report must always balance.
        $loose = [];
        foreach (ChartOfAccount::whereNull('subhead_id')->orderBy('code')->get() as $a) {
            if (! isset($totals[$a->id])) continue;
            $net = $totals[$a->id]['debit'] - $totals[$a->id]['credit'];
            if ($net === 0) continue;
            $loose[] = ['kind' => 'account', 'code' => $a->code, 'name' => $a->name, 'debit' => max($net, 0), 'credit' => max(-$net, 0)];
        }
        if ($loose) {
            $rows[] = ['kind' => 'head', 'name' => 'Unclassified'];
            array_push($rows, ...$loose);
        }

        foreach ($rows as $r) {
            $totalDebit  += $r['debit'] ?? 0;
            $totalCredit += $r['credit'] ?? 0;
        }

        return view('accounting.trial_balance', compact('rows', 'totalDebit', 'totalCredit', 'asOf'));
    }

    public function balanceSheet(Request $request)
    {
        $asOf   = $request->as_of ?: today()->toDateString();
        $totals = $this->ledger->totals($asOf);

        $sections = ['asset' => [], 'liability' => [], 'equity' => []];
        $sums     = ['asset' => 0, 'liability' => 0, 'equity' => 0];
        $income = $expense = 0;

        foreach (AccountHead::with('subheads.accounts')->orderBy('sort_order')->get() as $head) {
            foreach ($head->subheads as $sub) {
                $amount = 0;
                $rows = [];
                $n = 0;

                foreach ($sub->accounts as $a) {
                    $t = $totals[$a->id] ?? null;
                    if (! $t) continue;
                    $net = $t['debit'] - $t['credit'];

                    if ($head->nature === 'income')  { $income  += -$net; continue; }
                    if ($head->nature === 'expense') { $expense += $net;  continue; }

                    $signed = $head->nature === 'asset' ? $net : -$net;
                    $amount += $signed;
                    $n++;
                    if (! $sub->isParty() && $signed !== 0) {
                        $rows[] = ['name' => $a->name, 'amount' => $signed];
                    }
                }

                if (isset($sections[$head->nature]) && ($amount !== 0 || $rows)) {
                    $sections[$head->nature][] = ['name' => $sub->name . ($sub->isParty() ? " ({$n} accounts)" : ''), 'amount' => $amount, 'accounts' => $rows];
                    $sums[$head->nature] += $amount;
                }
            }
        }

        $assets = $sections['asset'];
        $liabilities = $sections['liability'];
        $equity = $sections['equity'];
        $totalAssets = $sums['asset'];
        $totalLiabilities = $sums['liability'];
        $totalEquityBase = $sums['equity'];
        $netProfit = $income - $expense;

        return view('accounting.balance_sheet', compact('assets', 'liabilities', 'equity', 'totalAssets', 'totalLiabilities', 'totalEquityBase', 'netProfit', 'asOf'));
    }

    public function expenseAnalysis(Request $request)
    {
        $from = $request->from ?: now()->startOfYear()->toDateString();
        $to   = $request->to   ?: now()->toDateString();

        $totals = $this->ledger->totals($to, $from);
        $accounts = ChartOfAccount::whereHas('subhead.head', fn ($q) => $q->where('nature', 'expense'))->get();

        $counts = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $accounts->pluck('id'))
            ->whereDate('journal_entries.date', '>=', $from)->whereDate('journal_entries.date', '<=', $to)
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id as account_id, COUNT(*) as n')
            ->pluck('n', 'account_id');

        $byCategory = $accounts->map(function ($a) use ($totals, $counts) {
            $t = $totals[$a->id] ?? ['debit' => 0, 'credit' => 0];
            return (object) ['category' => $a->name, 'count' => (int) ($counts[$a->id] ?? 0), 'total' => $t['debit'] - $t['credit']];
        })->filter(fn ($r) => $r->total !== 0)->sortByDesc('total')->values();

        $totalExpense = $byCategory->sum('total');

        return view('accounting.expense_analysis', compact('byCategory', 'totalExpense', 'from', 'to'));
    }

    public function cashFlow(Request $request)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to   = $request->to   ?: now()->toDateString();

        $moneyIds = ChartOfAccount::whereHas('subhead', fn ($q) => $q->whereIn('kind', ['cash', 'bank']))->pluck('id');

        $before = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $moneyIds)
            ->whereDate('journal_entries.date', '<', $from)
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')->first();
        $openingBalance = (int) $before->d - (int) $before->c;

        $lines = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $moneyIds)
            ->whereDate('journal_entries.date', '>=', $from)->whereDate('journal_entries.date', '<=', $to)
            ->select('journal_lines.*', 'journal_entries.voucher_type')
            ->get();

        $operatingIn  = (int) $lines->whereIn('voucher_type', ['receipt', 'deposit_receipt'])->sum('debit');
        $operatingOut = (int) $lines->whereIn('voucher_type', ['payment', 'expense'])->sum('credit');
        $netChange    = (int) $lines->sum('debit') - (int) $lines->sum('credit');
        $other        = $netChange - ($operatingIn - $operatingOut);
        $closingBalance = $openingBalance + $netChange;

        return view('accounting.cash_flow', compact('operatingIn', 'operatingOut', 'other', 'netChange', 'openingBalance', 'closingBalance', 'from', 'to'));
    }
}
