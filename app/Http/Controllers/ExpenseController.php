<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Rules\MoneyAccount;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::with('account')
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->latest('expense_date')
            ->get();

        return view('expenses.index', compact('expenses'));
    }

    public function store(Request $request, LedgerService $ledger)
    {
        $data = $request->validate([
            'category'     => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'description'  => ['nullable', 'string', 'max:255'],
            'amount'       => ['required', 'integer', 'min:1'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'account_id'   => ['required', new MoneyAccount],
            'is_backdated' => ['boolean'],
        ]);

        $backdated = $request->boolean('is_backdated');
        if ($backdated) {
            abort_unless($request->user()->canBackdate(), 403);
        }

        $account = $ledger->moneyAccount($data['account_id']);

        DB::transaction(function () use ($data, $backdated, $request, $ledger, $account) {
            $expense = Expense::create([
                'category'             => $data['category'],
                'description'          => $data['description'] ?? null,
                'amount'               => $data['amount'],
                'expense_date'         => $data['expense_date'],
                'paid_from_account_id' => $account->id,
                'is_backdated'         => $backdated,
                'recorded_by'          => $request->user()->id,
            ]);

            $ledger->expense($expense);
        });

        return back()->with('success', 'Expense recorded.');
    }

    /** Modal edit form fetches this as JSON. */
    public function edit(Expense $expense)
    {
        return response()->json($expense);
    }

    public function update(Request $request, Expense $expense, LedgerService $ledger)
    {
        $data = $request->validate([
            'category'     => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'description'  => ['nullable', 'string', 'max:255'],
            'amount'       => ['required', 'integer', 'min:1'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'account_id'   => ['required', new MoneyAccount],
        ]);

        $account = $ledger->moneyAccount($data['account_id']);

        DB::transaction(function () use ($expense, $data, $ledger, $account) {
            foreach ($expense->journalEntries as $entry) {
                $ledger->reverseEntry($entry, now()->toDateString(), "Correction to expense #{$expense->id}");
            }

            $expense->update([
                'category'             => $data['category'],
                'description'          => $data['description'] ?? null,
                'amount'               => $data['amount'],
                'expense_date'         => $data['expense_date'],
                'paid_from_account_id' => $account->id,
            ]);

            $ledger->expense($expense->fresh());
        });

        return back()->with('success', 'Expense updated — original ledger entry reversed and reposted.');
    }

    public function destroy(Expense $expense, LedgerService $ledger)
    {
        DB::transaction(function () use ($expense, $ledger) {
            foreach ($expense->journalEntries as $entry) {
                $ledger->reverseEntry($entry, now()->toDateString(), "Reversal of deleted expense #{$expense->id}");
            }

            $expense->delete();
        });

        return back()->with('success', 'Expense deleted and ledger entry reversed.');
    }
}
