<?php

namespace App\Http\Controllers;

use App\Models\{ChartOfAccount, Customer, JournalEntry, JournalLine, Vendor};
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountingController extends Controller
{
    /** System accounts only — customer/vendor sub-accounts are viewed via the Ledger drill-down, not this admin list. */
    public function chartOfAccounts()
    {
        $accounts = ChartOfAccount::whereNotIn('type', ['customer', 'vendor'])->orderBy('code')->get()
            ->map(fn ($a) => tap($a, fn ($a) => $a->current_balance = $a->balance()));

        return view('accounting.chart', compact('accounts'));
    }

    public function storeAccount(Request $request)
    {
        $data = $request->validate(['code'=>['required','unique:chart_of_accounts,code'],'name'=>['required'],'type'=>['required', Rule::in(['asset','liability','equity','income','expense'])]]);
        ChartOfAccount::create($data + ['is_system' => false, 'is_active' => true]);
        return back()->with('success', 'Account created.');
    }
    public function updateAccount(Request $request, ChartOfAccount $account)
    {
        abort_if($account->is_system, 422, 'System accounts cannot be edited.');
        $account->update($request->validate(['name'=>['required'],'is_active'=>['boolean']]));
        return back()->with('success', 'Account updated.');
    }

    public function journal(Request $request)
    {
        $entries = JournalEntry::with('lines.account')
            ->when($request->from, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->latest('date')
            ->get();

        return view('accounting.journal', compact('entries'));
    }

    public function ledger(Request $request, ChartOfAccount $account)
    {
        $lines = JournalLine::with('entry')
            ->where('account_id', $account->id)
            ->when($request->from, fn ($q, $v) => $q->whereHas('entry', fn ($e) => $e->whereDate('date', '>=', $v)))
            ->when($request->to, fn ($q, $v) => $q->whereHas('entry', fn ($e) => $e->whereDate('date', '<=', $v)))
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->orderBy('journal_entries.date')
            ->select('journal_lines.*')
            ->get();

        $running = 0;
        $debitNormal = $account->isDebitNature();
        $lines = $lines->map(function ($l) use (&$running, $debitNormal) {
            $running += $debitNormal ? ($l->debit - $l->credit) : ($l->credit - $l->debit);
            $l->running_balance = $running;
            return $l;
        });

        return view('accounting.ledger', compact('account', 'lines'));
    }

    public function cashBankBook(Request $request)
    {
        $accounts = [LedgerService::CASH, LedgerService::BANK];

        $lines = JournalLine::with('entry', 'account')
            ->whereHas('account', fn ($q) => $q->whereIn('code', $accounts))
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($request->from, fn ($q, $v) => $q->whereDate('journal_entries.date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('journal_entries.date', '<=', $v))
            ->orderBy('journal_entries.date')
            ->select('journal_lines.*')
            ->get();

        return view('accounting.cash_bank_book', compact('lines'));
    }

    public function receivables()
    {
        $customers = Customer::get()->map(fn ($c) => [
            'customer' => $c,
            'invoiced' => $c->totalInvoiced(),
            'paid'     => $c->totalPaid(),
            'balance'  => $c->balance(),
        ])->filter(fn ($r) => $r['balance'] > 0)->values();

        return view('accounting.receivables', compact('customers'));
    }

    public function payables()
    {
        $vendors = Vendor::get()->map(fn ($v) => [
            'vendor'  => $v,
            'payable' => $v->totalPayable(),
            'paid'    => $v->totalPaid(),
            'balance' => $v->balance(),
        ])->filter(fn ($r) => $r['balance'] > 0)->values();

        return view('accounting.payables', compact('vendors'));
    }

    public function profitLoss(Request $request)
    {
        $from = $request->from ?? now()->startOfYear()->toDateString();
        $to   = $request->to   ?? now()->toDateString();

        $income  = ChartOfAccount::type('income')->get()->map(fn ($a) => ['account' => $a, 'amount' => $a->balance()]);
        $expense = ChartOfAccount::type('expense')->get()->map(fn ($a) => ['account' => $a, 'amount' => $a->balance()]);

        $totalIncome  = $income->sum('amount');
        $totalExpense = $expense->sum('amount');
        $netProfit    = $totalIncome - $totalExpense;

        return view('accounting.profit_loss', compact('income', 'expense', 'totalIncome', 'totalExpense', 'netProfit', 'from', 'to'));
    }

    /** Individual customer/vendor sub-accounts roll up into one control-account line each — not listed one-by-one. */
    public function trialBalance(Request $request)
    {
        $accounts = ChartOfAccount::orderBy('code')->get();
        $customerSubs = $accounts->where('type', 'customer');
        $vendorSubs   = $accounts->where('type', 'vendor');
        $regular      = $accounts->whereNotIn('type', ['customer', 'vendor']);

        $rows = $regular->map(function ($a) {
            $balance = $a->balance();
            $debitNormal = $a->isDebitNature();
            return ['account' => $a, 'debit' => $debitNormal ? max($balance, 0) : 0, 'credit' => !$debitNormal ? max($balance, 0) : 0];
        })->filter(fn ($r) => $r['debit'] != 0 || $r['credit'] != 0)->values();

        if ($customerSubs->isNotEmpty()) {
            $total = $customerSubs->sum(fn ($a) => $a->balance());
            if ($total != 0) {
                $rows->push(['account' => (object) ['code' => LedgerService::AR, 'name' => "Accounts Receivable (Control — {$customerSubs->count()} customers)"], 'debit' => max($total, 0), 'credit' => 0]);
            }
        }

        if ($vendorSubs->isNotEmpty()) {
            $total = $vendorSubs->sum(fn ($a) => $a->balance());
            if ($total != 0) {
                $rows->push(['account' => (object) ['code' => LedgerService::AP_VENDOR, 'name' => "Accounts Payable (Control — {$vendorSubs->count()} vendors)"], 'debit' => 0, 'credit' => max($total, 0)]);
            }
        }

        return view('accounting.trial_balance', [
            'rows' => $rows, 'totalDebit' => $rows->sum('debit'), 'totalCredit' => $rows->sum('credit'),
        ]);
    }

    public function balanceSheet(Request $request)
    {
        $assets      = ChartOfAccount::type('asset')->get()->map(fn ($a) => ['account' => $a, 'amount' => $a->balance()]);
        $liabilities = ChartOfAccount::type('liability')->get()->map(fn ($a) => ['account' => $a, 'amount' => $a->balance()]);
        $equity      = ChartOfAccount::type('equity')->get()->map(fn ($a) => ['account' => $a, 'amount' => $a->balance()]);

        $customerSubs = ChartOfAccount::type('customer')->get();
        $vendorSubs   = ChartOfAccount::type('vendor')->get();

        if ($customerSubs->isNotEmpty()) {
            $assets->push(['account' => (object) ['name' => "Accounts Receivable (Control — {$customerSubs->count()} customers)"], 'amount' => $customerSubs->sum(fn ($a) => $a->balance())]);
        }
        if ($vendorSubs->isNotEmpty()) {
            $liabilities->push(['account' => (object) ['name' => "Accounts Payable (Control — {$vendorSubs->count()} vendors)"], 'amount' => $vendorSubs->sum(fn ($a) => $a->balance())]);
        }

        $totalAssets      = $assets->sum('amount');
        $totalLiabilities = $liabilities->sum('amount');
        $totalEquityBase  = $equity->sum('amount');

        $income  = ChartOfAccount::type('income')->get()->sum(fn ($a) => $a->balance());
        $expense = ChartOfAccount::type('expense')->get()->sum(fn ($a) => $a->balance());
        $netProfit = $income - $expense;

        return view('accounting.balance_sheet', compact(
            'assets', 'liabilities', 'equity', 'totalAssets', 'totalLiabilities', 'totalEquityBase', 'netProfit'
        ));
    }
}