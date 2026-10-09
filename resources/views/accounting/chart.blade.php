@extends('layouts.app')

@section('title', 'Accounting | Chart of Accounts')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="card-title">Accounting</h2>
                <div class="d-flex gap-2">
                    @can('accounting.edit')
                        <a href="{{ route('accounting.mappings') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-link"></i> Account Mapping</a>
                    @endcan
                    @can('accounting.create')
                        <button type="button" class="modal-with-form btn btn-outline-secondary btn-sm" href="#addHeadModal"><i class="fas fa-sitemap"></i> Add Head</button>
                        <button type="button" class="modal-with-form btn btn-outline-primary btn-sm" href="#addSubheadModal"><i class="fas fa-layer-group"></i> Add Sub-head</button>
                        <button type="button" class="modal-with-form btn btn-primary btn-sm" href="#addAccountModal"><i class="fas fa-plus"></i> Add Account</button>
                    @endcan
                </div>
            </header>

            @include('accounting._tabs', ['active' => 'chart'])

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
                @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

                @foreach($heads as $head)
                <div class="mb-4">
                    <h5 class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span><span class="text-muted">{{ $head->code }}</span> &nbsp;{{ strtoupper($head->name) }}</span>
                        <span class="d-flex align-items-center gap-3">
                            <span class="fw-bold">¥{{ number_format($head->total) }}</span>
                            @can('accounting.edit')
                                <a href="#" class="text-primary fs-6" title="Rename head" onclick="editHead({{ $head->id }}, {{ \Illuminate\Support\Js::from($head->name) }}); return false;"><i class="fa fa-edit"></i></a>
                            @endcan
                            @can('accounting.delete')
                                @if($head->subheads->isEmpty() && ! in_array($head->code, ['1','2','3','4','5'], true))
                                    <form action="{{ route('accounting.heads.destroy', $head) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this head?');">
                                        @csrf @method('DELETE')<button class="btn btn-link p-0 text-danger fs-6" title="Delete head"><i class="fa fa-trash-alt"></i></button>
                                    </form>
                                @endif
                            @endcan
                        </span>
                    </h5>

                    @forelse($head->subheads as $sub)
                    <div class="ms-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center bg-light border rounded px-3 py-2">
                            <div>
                                <strong><span class="text-muted">{{ $sub->code }}</span> &nbsp;{{ $sub->name }}</strong>
                                @if($sub->isParty())
                                    <span class="badge bg-info text-dark ms-2">{{ $sub->accounts->count() }} {{ $sub->kind === 'customer' ? 'customer' : 'vendor' }} accounts</span>
                                @elseif($sub->isMoney())
                                    <span class="badge bg-success ms-2">{{ $sub->kind }} — appears in payment dropdowns</span>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="fw-bold">¥{{ number_format($sub->total) }}</span>
                                @can('accounting.edit')
                                    <a href="#" class="text-primary" title="Edit sub-head" onclick="editSubhead({{ $sub->id }}, {{ \Illuminate\Support\Js::from($sub->name) }}); return false;"><i class="fa fa-edit"></i></a>
                                @endcan
                                @can('accounting.delete')
                                    @if(! $sub->kind && $sub->accounts->isEmpty())
                                        <form action="{{ route('accounting.subheads.destroy', $sub) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this sub-head?');">
                                            @csrf @method('DELETE')<button class="btn btn-link p-0 text-danger" title="Delete sub-head"><i class="fa fa-trash-alt"></i></button>
                                        </form>
                                    @endif
                                @endcan
                                @if($sub->isParty() && $sub->accounts->isNotEmpty())
                                    <a class="small" data-bs-toggle="collapse" href="#party-{{ $sub->id }}" role="button">Show accounts</a>
                                @endif
                            </div>
                        </div>

                        @if($sub->accounts->isNotEmpty())
                        <div class="{{ $sub->isParty() ? 'collapse' : '' }}" id="party-{{ $sub->id }}">
                            <table class="table table-sm table-bordered mb-0 mt-1">
                                <thead><tr class="text-muted small"><th style="width:140px;">Code</th><th>Account</th><th class="text-end" style="width:170px;">Balance</th><th style="width:230px;">Action</th></tr></thead>
                                <tbody>
                                    @foreach($sub->accounts as $a)
                                    <tr class="{{ $a->is_active ? '' : 'text-muted' }}">
                                        <td><code>{{ $a->code }}</code></td>
                                        <td>
                                            {{ $a->name }}
                                            @if($a->is_system)<span class="badge bg-secondary ms-1">System</span>@endif
                                            @if($a->is_mapped)<span class="badge bg-primary ms-1" title="Used by the Account Mapping page">Mapped</span>@endif
                                            @unless($a->is_active)<span class="badge bg-warning text-dark ms-1">Inactive</span>@endunless
                                        </td>
                                        <td class="text-end fw-bold {{ $a->current_balance < 0 ? 'text-danger' : '' }}">¥{{ number_format($a->current_balance) }}</td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('accounting.ledger', $a) }}" class="btn btn-sm btn-outline-secondary">Ledger</a>
                                            @can('accounting.edit')
                                                <a href="#" class="btn btn-sm btn-outline-primary"
                                                   onclick="editAccount({{ $a->id }}, {{ \Illuminate\Support\Js::from($a->name) }}, {{ $a->subhead_id }}, {{ $a->is_active ? 'true' : 'false' }}, {{ $a->isParty() ? 'true' : 'false' }}); return false;">Edit</a>
                                            @endcan
                                            @can('accounting.delete')
                                                @if(! $a->has_lines && ! $a->is_mapped)
                                                    <form action="{{ route('accounting.chart.destroy', $a) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete account {{ addslashes($a->name) }}?');">
                                                        @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </form>
                                                @else
                                                    <span class="text-muted ms-1" title="{{ $a->has_lines ? 'Has posted transactions — deactivate instead' : 'Used by Account Mapping — re-map first' }}"><i class="fa fa-lock"></i></span>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                    @empty
                        <p class="ms-3 text-muted">No sub-heads.</p>
                    @endforelse
                </div>
                @endforeach

                @if($unclassified->isNotEmpty())
                <div class="alert alert-warning">
                    <strong>Unclassified accounts</strong> — run <code>php artisan migrate</code> or edit each one and choose a sub-head:
                    @foreach($unclassified as $a)
                        <div><code>{{ $a->code }}</code> {{ $a->name }}
                            @can('accounting.edit')<a href="#" onclick="editAccount({{ $a->id }}, {{ \Illuminate\Support\Js::from($a->name) }}, null, {{ $a->is_active ? 'true' : 'false' }}, false); return false;">Classify</a>@endcan
                        </div>
                    @endforeach
                </div>
                @endif
            </div>
        </section>

        {{-- ================= ADD ACCOUNT ================= --}}
        @can('accounting.create')
        <div id="addAccountModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('accounting.chart.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Add Account</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2"><label>Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" required></div>
                            <div class="col-lg-12 mb-2">
                                <label>Sub-head <span class="text-danger">*</span></label>
                                <select class="form-control" name="subhead_id" required>
                                    @foreach($subheadOptions->filter(fn ($s) => ! $s->isParty())->groupBy(fn ($s) => $s->head->name) as $headName => $subs)
                                        <optgroup label="{{ $headName }}">
                                            @foreach($subs as $s)<option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>@endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <small class="text-muted">The code is generated automatically. The sub-head decides the account's type. To add a bank or cash account, choose "Bank Accounts" or "Cash in Hand" — it then appears in every payment dropdown.</small>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer"><div class="col-md-12 text-end"><button type="submit" class="btn btn-primary">Add Account</button><button type="button" class="btn btn-default modal-dismiss">Cancel</button></div></footer>
                </form>
            </section>
        </div>

        <div id="addHeadModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('accounting.heads.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Add Head</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2"><label>Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" required></div>
                            <div class="col-lg-12 mb-2">
                                <label>Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="nature" required>
                                    <option value="asset">Asset</option>
                                    <option value="liability">Liability</option>
                                    <option value="equity">Equity</option>
                                    <option value="income">Income</option>
                                    <option value="expense">Expense</option>
                                </select>
                                <small class="text-muted">The code is generated automatically. The type decides whether balances grow with debits (asset, expense) or credits (liability, equity, income) and cannot be changed later.</small>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer"><div class="col-md-12 text-end"><button type="submit" class="btn btn-primary">Add Head</button><button type="button" class="btn btn-default modal-dismiss">Cancel</button></div></footer>
                </form>
            </section>
        </div>

        <div id="addSubheadModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('accounting.subheads.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Add Sub-head</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2">
                                <label>Head <span class="text-danger">*</span></label>
                                <select class="form-control" name="head_id" required>
                                    @foreach($heads as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-lg-12 mb-2"><label>Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" required><small class="text-muted">The code is generated automatically from the head.</small></div>
                        </div>
                    </div>
                    <footer class="card-footer"><div class="col-md-12 text-end"><button type="submit" class="btn btn-primary">Add Sub-head</button><button type="button" class="btn btn-default modal-dismiss">Cancel</button></div></footer>
                </form>
            </section>
        </div>
        @endcan

        {{-- ================= EDIT ACCOUNT / SUB-HEAD ================= --}}
        @can('accounting.edit')
        <div id="editAccountModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="editAccountForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Edit Account</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2"><label>Name <span class="text-danger">*</span></label><input type="text" id="ea_name" class="form-control" name="name" required></div>
                            <div class="col-lg-12 mb-2">
                                <label>Sub-head <span class="text-danger">*</span></label>
                                <select id="ea_subhead" class="form-control" name="subhead_id" required>
                                    @foreach($subheadOptions->groupBy(fn ($s) => $s->head->name) as $headName => $subs)
                                        <optgroup label="{{ $headName }}">
                                            @foreach($subs as $s)<option value="{{ $s->id }}" data-party="{{ $s->isParty() ? 1 : 0 }}">{{ $s->code }} — {{ $s->name }}</option>@endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <small class="text-muted" id="ea_party_note" style="display:none;">Customer / vendor accounts stay in their own sub-head.</small>
                                <small class="text-muted d-block">The code never changes — except when an account moves to a head of a different type, which assigns a new code from that head's range.</small>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" id="ea_active" value="1"><label class="form-check-label" for="ea_active">Active</label></div>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer"><div class="col-md-12 text-end"><button type="submit" class="btn btn-primary">Update Account</button><button type="button" class="btn btn-default modal-dismiss">Cancel</button></div></footer>
                </form>
            </section>
        </div>

        <div id="editSubheadModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="editSubheadForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Edit Sub-head</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2"><label>Name <span class="text-danger">*</span></label><input type="text" id="es_name" class="form-control" name="name" required></div>
                        </div>
                    </div>
                    <footer class="card-footer"><div class="col-md-12 text-end"><button type="submit" class="btn btn-primary">Update Sub-head</button><button type="button" class="btn btn-default modal-dismiss">Cancel</button></div></footer>
                </form>
            </section>
        </div>

        <div id="editHeadModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="editHeadForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Rename Head</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2"><label>Name <span class="text-danger">*</span></label><input type="text" id="eh_name" class="form-control" name="name" required><small class="text-muted">Codes are fixed once generated; only the name can change.</small></div>
                        </div>
                    </div>
                    <footer class="card-footer"><div class="col-md-12 text-end"><button type="submit" class="btn btn-primary">Update Head</button><button type="button" class="btn btn-default modal-dismiss">Cancel</button></div></footer>
                </form>
            </section>
        </div>
        @endcan
    </div>
</div>

<script>
function editHead(id, name) {
    document.getElementById('editHeadForm').action = '/accounting/heads/' + id;
    document.getElementById('eh_name').value = name;
    $.magnificPopup.open({ items: { src: '#editHeadModal' }, type: 'inline' });
}
function editAccount(id, name, subheadId, isActive, isParty) {
    document.getElementById('editAccountForm').action = '/accounting/chart/' + id;
    document.getElementById('ea_name').value = name;
    const sel = document.getElementById('ea_subhead');
    sel.value = subheadId;
    sel.style.pointerEvents = isParty ? 'none' : '';
    sel.style.background = isParty ? '#eee' : '';
    document.getElementById('ea_party_note').style.display = isParty ? '' : 'none';
    document.getElementById('ea_active').checked = isActive;
    $.magnificPopup.open({ items: { src: '#editAccountModal' }, type: 'inline' });
}
function editSubhead(id, name) {
    document.getElementById('editSubheadForm').action = '/accounting/subheads/' + id;
    document.getElementById('es_name').value = name;
    $.magnificPopup.open({ items: { src: '#editSubheadModal' }, type: 'inline' });
}
</script>
@endsection
