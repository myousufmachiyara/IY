@extends('layouts.app')
@section('title', 'Accounting | New Journal Voucher')
@section('content')

@php
    // Grouped Head › Sub-head so a long customer / vendor list never buries the other accounts.
    $optionsHtml = '<option value="">Select account</option>';
    foreach ($accounts->sortBy(fn ($a) => ($a->subhead?->head->sort_order ?? 99) . '-' . ($a->subhead?->sort_order ?? 99))->groupBy(fn ($a) => ($a->subhead ? $a->subhead->head->name . ' › ' . $a->subhead->name : 'Unclassified')) as $label => $items) {
        $optionsHtml .= '<optgroup label="' . e($label) . '">';
        foreach ($items as $a) {
            $optionsHtml .= '<option value="' . $a->id . '">' . e($a->code . ' — ' . $a->name) . '</option>';
        }
        $optionsHtml .= '</optgroup>';
    }
@endphp

<div class="row"><div class="col"><section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">New Journal Voucher</h2>
        <a href="{{ route('accounting.journal') }}" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Back to Journal</a>
    </header>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ route('accounting.journal_voucher.store') }}" id="jvForm" onkeydown="return event.key != 'Enter' || event.target.tagName === 'TEXTAREA';">
            @csrf
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label>Voucher Date <span class="text-danger">*</span></label>
                    <input type="date" name="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}"
                        @unless(auth()->user()->isSuperAdmin()) readonly @endunless required>
                </div>
                <div class="col-md-9 mb-3">
                    <label>Narration <span class="text-danger">*</span></label>
                    <input type="text" name="description" class="form-control" maxlength="255" value="{{ old('description') }}" placeholder="What is this entry for?" required>
                </div>
            </div>

            <div class="table-scroll">
                <table class="table table-bordered mb-2">
                    <thead>
                        <tr>
                            <th style="width:38%;">Account</th>
                            <th style="width:17%;" class="text-end">Debit (¥)</th>
                            <th style="width:17%;" class="text-end">Credit (¥)</th>
                            <th>Memo</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="jvRows"></tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td class="text-end">Total</td>
                            <td class="text-end" id="tDebit">0</td>
                            <td class="text-end" id="tCredit">0</td>
                            <td colspan="2" id="tStatus" class="text-muted">Enter amounts</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="addRow()"><i class="fa fa-plus"></i> Add Line</button>
            <p class="text-muted small">Debits must equal credits. Choosing a customer or vendor account posts straight to that party's own ledger.</p>

            <button type="submit" id="jvSubmit" class="btn btn-primary" disabled>Post Voucher</button>
            <a href="{{ route('accounting.journal') }}" class="btn btn-light">Cancel</a>
        </form>
    </div>
</section></div></div>

<script>
const optionsHtml = @json($optionsHtml);
const oldLines = Object.values(@json(old('lines', [])));
let rowIndex = 0;

function addRow(v = {}) {
    const i = rowIndex++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select name="lines[${i}][account_id]" class="form-control jv-account">${optionsHtml}</select></td>
        <td><input type="number" min="0" step="1" name="lines[${i}][debit]" class="form-control text-end jv-debit"></td>
        <td><input type="number" min="0" step="1" name="lines[${i}][credit]" class="form-control text-end jv-credit"></td>
        <td><input type="text" maxlength="255" name="lines[${i}][memo]" class="form-control"></td>
        <td class="text-center"><button type="button" class="btn btn-link text-danger p-0 jv-remove" title="Remove line"><i class="fa fa-times"></i></button></td>`;
    document.getElementById('jvRows').appendChild(tr);
    tr.querySelector('.jv-account').value = v.account_id || '';
    tr.querySelector('.jv-debit').value  = v.debit || '';
    tr.querySelector('.jv-credit').value = v.credit || '';
    tr.querySelector('[name$="[memo]"]').value = v.memo || '';
    recalc();
}

function recalc() {
    let d = 0, c = 0;
    document.querySelectorAll('.jv-debit').forEach(el => d += parseInt(el.value) || 0);
    document.querySelectorAll('.jv-credit').forEach(el => c += parseInt(el.value) || 0);
    document.getElementById('tDebit').textContent = d.toLocaleString();
    document.getElementById('tCredit').textContent = c.toLocaleString();

    const status = document.getElementById('tStatus');
    const balanced = d > 0 && d === c;
    if (balanced) {
        status.textContent = 'Balanced';
        status.className = 'text-success';
    } else if (d === 0 && c === 0) {
        status.textContent = 'Enter amounts';
        status.className = 'text-muted';
    } else {
        status.textContent = 'Out of balance by ¥' + Math.abs(d - c).toLocaleString();
        status.className = 'text-danger';
    }
    document.getElementById('jvSubmit').disabled = !balanced;
}

document.getElementById('jvRows').addEventListener('input', function (e) {
    const row = e.target.closest('tr');
    if (e.target.classList.contains('jv-debit') && e.target.value) row.querySelector('.jv-credit').value = '';
    if (e.target.classList.contains('jv-credit') && e.target.value) row.querySelector('.jv-debit').value = '';
    recalc();
});

document.getElementById('jvRows').addEventListener('click', function (e) {
    const btn = e.target.closest('.jv-remove');
    if (!btn) return;
    if (document.querySelectorAll('#jvRows tr').length <= 2) { alert('A voucher needs at least two lines.'); return; }
    btn.closest('tr').remove();
    recalc();
});

(oldLines.length ? oldLines : [{}, {}]).forEach(v => addRow(v));
while (document.querySelectorAll('#jvRows tr').length < 2) addRow();
</script>
@endsection