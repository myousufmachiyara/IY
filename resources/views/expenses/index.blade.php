@extends('layouts.app')

@section('title', 'Expenses | All Expenses')

@section('content')

@php $categoryLabels = ['salary' => 'Salary', 'office' => 'Office', 'utilities' => 'Utilities', 'misc' => 'Miscellaneous']; @endphp

<div class="row">
    <div class="col">
        <section class="card">

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <header class="card-header">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <h2 class="card-title">Expenses</h2>
                    @can('expenses.create')
                        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                            <i class="fas fa-plus"></i> Add Expense
                        </button>
                    @endcan
                </div>
            </header>

            <div class="card-body">
                <form method="GET" action="{{ route('expenses.index') }}" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <select name="category" class="form-control select2-js" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            @foreach($categoryLabels as $k => $v)
                                <option value="{{ $k }}" {{ request('category') === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Backdated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($expenses as $e)
                            <tr>
                                <td>{{ $e->expense_date->format('d-m-Y') }}</td>
                                <td><span class="badge bg-secondary">{{ $categoryLabels[$e->category] ?? ucfirst($e->category) }}</span></td>
                                <td>{{ $e->description ?? '—' }}</td>
                                <td>¥{{ number_format($e->amount) }}</td>
                                <td>{{ $e->is_backdated ? 'Yes' : '—' }}</td>
                                <td class="text-nowrap">
                                    @can('expenses.edit')
                                        <a href="#" class="text-primary me-1" title="Edit"
                                           onclick="editExpense({{ $e->id }}, '{{ $e->category }}', '{{ addslashes($e->description) }}', {{ $e->amount }}, '{{ $e->expense_date->format('Y-m-d') }}')">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endcan
                                    @can('expenses.delete')
                                        <form action="{{ route('expenses.destroy', $e) }}" method="POST" style="display:inline;"
                                              onsubmit="return confirm('Delete this expense? The ledger entry will be reversed.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-link p-0 text-danger"><i class="fa fa-trash-alt"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No expenses recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- ================= ADD MODAL ================= --}}
        @can('expenses.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('expenses.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Add Expense</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Category <span class="text-danger">*</span></label>
                                <select class="form-control select2-js" name="category" required>
                                    @foreach($categoryLabels as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Amount (¥) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="amount" min="1" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="expense_date" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Paid From <span class="text-danger">*</span></label>
                                <select class="form-control select2-js" name="method" required>
                                    <option value="bank" selected>Bank</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Description</label>
                                <input type="text" class="form-control" name="description">
                            </div>
                            @if(auth()->user()->canBackdate())
                            <div class="col-lg-12 mb-2">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_backdated" id="exp_is_backdated" value="1">
                                    <label class="form-check-label" for="exp_is_backdated">This is a back-dated entry</label>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Add Expense</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan

        {{-- ================= EDIT MODAL ================= --}}
        @can('expenses.edit')
        <div id="editExpModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="editExpForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Edit Expense</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Category <span class="text-danger">*</span></label>
                                <select id="edit_exp_category" class="form-control select2-js" name="category" required>
                                    @foreach($categoryLabels as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Amount (¥) <span class="text-danger">*</span></label>
                                <input type="number" id="edit_exp_amount" class="form-control" name="amount" min="1" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Date <span class="text-danger">*</span></label>
                                <input type="date" id="edit_exp_date" class="form-control" name="expense_date" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Paid From <span class="text-danger">*</span></label>
                                <select class="form-control select2-js" name="method" required>
                                    <option value="bank">Bank</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Description</label>
                                <input type="text" id="edit_exp_description" class="form-control" name="description">
                            </div>
                        </div>
                        <p class="text-muted small mb-0"><i class="fa fa-info-circle"></i> Editing reverses the original ledger entry and posts a fresh one.</p>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Update Expense</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan
    </div>
</div>

<script>
function editExpense(id, category, description, amount, date) {
    document.getElementById('editExpForm').action = '/expenses/' + id;
    document.getElementById('edit_exp_amount').value = amount;
    document.getElementById('edit_exp_date').value = date;
    document.getElementById('edit_exp_description').value = description || '';
    $('#edit_exp_category').val(category).trigger('change');
    $.magnificPopup.open({ items: { src: '#editExpModal' }, type: 'inline' });
}
</script>

@endsection