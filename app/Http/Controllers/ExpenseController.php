<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->latest('expense_date')
            ->get();

        return view('expenses.index', compact('expenses'));
    }

    public function store(Request $request, LedgerService $ledger)
    {
        $data = $request->validate([
            'category'     => ['required', Rule::in(['salary', 'office', 'utilities', 'misc'])],
            'description'  => ['nullable', 'string', 'max:255'],
            'amount'       => ['required', 'integer', 'min:1'],
            'expense_date' => ['required', 'date'],
            'method'       => ['required', Rule::in(['cash', 'bank'])],
            'is_backdated' => ['boolean'],
        ]);

        $backdated = $request->boolean('is_backdated');
        if ($backdated) {
            abort_unless($request->user()->canBackdate(), 403);
        }

        DB::transaction(function () use ($data, $backdated, $request, $ledger) {
            $expense = Expense::create($data + [
                'is_backdated' => $backdated,
                'recorded_by'  => $request->user()->id,
            ]);

            $ledger->expense($expense, $data['method'] === 'cash' ? LedgerService::CASH : LedgerService::BANK);
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
            'category'     => ['required', Rule::in(['salary', 'office', 'utilities', 'misc'])],
            'description'  => ['nullable', 'string', 'max:255'],
            'amount'       => ['required', 'integer', 'min:1'],
            'expense_date' => ['required', 'date'],
            'method'       => ['required', Rule::in(['cash', 'bank'])],
        ]);

        DB::transaction(function () use ($expense, $data, $ledger) {
            foreach ($expense->journalEntries as $entry) {
                $ledger->reverseEntry($entry, now()->toDateString(), "Correction to expense #{$expense->id}");
            }

            $expense->update($data);

            $ledger->expense($expense->fresh(), $data['method'] === 'cash' ? LedgerService::CASH : LedgerService::BANK);
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