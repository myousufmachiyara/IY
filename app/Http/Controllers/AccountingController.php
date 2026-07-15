<?php

namespace App\Http\Controllers;

use App\Models\{ChartOfAccount, Customer, JournalEntry, JournalLine, Vendor};
use App\Services\LedgerService;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function chartOfAccounts()
    {
        $accounts = ChartOfAccount::orderBy('code')->get()
            ->map(fn ($a) => tap($a, fn ($a) => $a->current_balance = $a->balance()));

        return view('accounting.chart', compact('accounts'));
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
        $debitNormal = in_array($account->type, ['asset', 'expense'], true);
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
}