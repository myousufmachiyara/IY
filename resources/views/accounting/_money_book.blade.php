<div class="card-body">
    <form method="GET" action="{{ route($route) }}" class="row g-2 mb-3">
        <div class="col-md-3">
            <label class="small text-muted mb-1">Account</label>
            <select name="account_id" class="form-control" onchange="this.form.submit()">
                <option value="">All {{ strtolower($title) === 'cash book' ? 'cash' : (strtolower($title) === 'bank book' ? 'bank' : 'cash & bank') }} accounts</option>
                @foreach($accounts as $a)<option value="{{ $a->id }}" @selected($selected?->id == $a->id)>{{ $a->name }} ({{ $a->code }})</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="small text-muted mb-1">From</label><input type="date" name="from" class="form-control" value="{{ request('from') }}"></div>
        <div class="col-md-2"><label class="small text-muted mb-1">To</label><input type="date" name="to" class="form-control" value="{{ request('to') }}"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-secondary w-100">Filter</button></div>
    </form>

    <div class="table-scroll">
        <table class="table table-bordered table-striped mb-0">
            <thead><tr><th>Date</th><th>Account</th><th>Voucher</th><th>Description</th><th class="text-end">Receipts (Dr)</th><th class="text-end">Payments (Cr)</th><th class="text-end">Balance</th></tr></thead>
            <tbody>
                <tr class="table-light"><td colspan="6" class="fw-semibold">Opening balance{{ request('from') ? ' — before ' . \Carbon\Carbon::parse(request('from'))->format('d-m-Y') : '' }}</td><td class="text-end fw-bold">¥{{ number_format($opening) }}</td></tr>
                @forelse($lines as $l)
                <tr>
                    <td>{{ $l->entry->date->format('d-m-Y') }}</td>
                    <td>{{ $l->account->name }}</td>
                    <td><a href="{{ route('accounting.voucher_print', $l->entry) }}">{{ $l->entry->entry_no }}</a></td>
                    <td>{{ $l->memo && $l->memo !== 'Reversal' ? $l->memo : $l->entry->description }}</td>
                    <td class="text-end text-success">{{ $l->debit > 0 ? '¥'.number_format($l->debit) : '' }}</td>
                    <td class="text-end text-danger">{{ $l->credit > 0 ? '¥'.number_format($l->credit) : '' }}</td>
                    <td class="text-end fw-bold {{ $l->running_balance < 0 ? 'text-danger' : '' }}">¥{{ number_format($l->running_balance) }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-3">No transactions in this range.</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr class="fw-bold"><td colspan="6">Closing balance</td><td class="text-end">¥{{ number_format($closing) }}</td></tr></tfoot>
        </table>
    </div>
</div>
