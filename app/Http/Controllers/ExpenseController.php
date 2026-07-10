<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->latest('expense_date')->paginate(20);

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

        $expense = Expense::create($data + [
            'is_backdated' => $backdated,
            'recorded_by'  => $request->user()->id,
        ]);

        $ledger->expense($expense, $data['method'] === 'cash' ? LedgerService::CASH : LedgerService::BANK);

        return back()->with('success', 'Expense recorded.');
    }

    public function destroy(Expense $expense)
    {
        abort_if($expense->created_at->diffInDays(now()) > 0, 422, 'Reversal requires a counter-entry — use accounting adjustments instead of deleting posted expenses.');
        $expense->delete();
        return back()->with('success', 'Expense removed.');
    }
}   