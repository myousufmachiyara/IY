@extends('layouts.app')

@section('title', 'Bidding Results | Pending')

@section('content')

@php $isPrivileged = auth()->user()->can('data.view_all'); @endphp

<div class="row">
    <div class="col">
        <section class="card">
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
            @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

            <header class="card-header"><h2 class="card-title">Bidding Results</h2></header>
            @include('results._tabs', ['active' => 'pending'])

            <div class="card-body">
                @if($isPrivileged)
                <form method="GET" action="{{ route('results.index') }}" class="mb-3">
                    <select name="agent_ids[]" class="form-control select2-js" multiple style="max-width:400px;" onchange="this.form.submit()">
                        @foreach($agents as $a)<option value="{{ $a->id }}" @selected(in_array($a->id, request('agent_ids', [])))>{{ $a->name }}</option>@endforeach
                    </select>
                </form>
                @endif

                @can('bid_results.edit')
                <form method="POST" action="{{ route('results.bulk_lost') }}" id="bulkLostForm" class="mb-2">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger mb-0" onclick="return confirm('Mark all selected bids as lost? Each will be dated to its own auction date.');">
                        <i class="fa fa-times"></i> Mark Selected Lost
                    </button>
                    <small class="text-muted ms-2">Each bid is dated to its own auction date automatically.</small>
                </form>
                @endcan

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                @can('bid_results.edit')<th style="width:40px;"><input type="checkbox" id="checkAll"></th>@endcan
                                <th>Lot</th><th>Customer</th><th>Vehicle</th><th>Priority</th><th>Chassis</th>
                                @if($isPrivileged)<th>Agent</th>@endif
                                <th>Max Bid</th><th>Auction Date</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bids as $b)
                            <tr>
                                @can('bid_results.edit')
                                    <td><input type="checkbox" name="bid_ids[]" value="{{ $b->id }}" form="bulkLostForm"></td>
                                @endcan
                                <td>{{ $b->lot_no ?? '—' }}</td>
                                <td>@if($b->customer){{ $b->customer->name }}@else<span class="badge bg-warning text-dark">Unassigned</span>@endif</td>
                                <td>{{ trim("{$b->year} {$b->make} {$b->model}") ?: '—' }}</td>
                                <td>{{ $b->priority ?? '—' }}</td>
                                <td>{{ $b->chassis_no ?? '—' }}</td>
                                @if($isPrivileged)<td>{{ $b->agent->name ?? '—' }}</td>@endif
                                <td>¥{{ number_format($b->max_bid) }}</td>
                                <td>{{ optional($b->auction_date)->format('d-m-Y') ?? '—' }}</td>
                                <td class="text-nowrap">
                                    @if(!$b->customer_id)
                                        @can('bid_sheets.edit')<a href="#" class="btn btn-sm btn-warning text-dark me-1" onclick="openAssignCustomer({{ $b->id }}, '{{ $b->lot_no }}')">Assign Customer</a>@endcan
                                    @elseif(!$b->auction_date)
                                        <span class="badge bg-danger" title="Set an auction date on this bid before it can be won or lost">No auction date</span>
                                    @else
                                        @can('bid_results.edit')<a href="#" class="btn btn-sm btn-success me-1" onclick="openWon({{ $b->id }}, '{{ $b->customer->name }}', {{ $b->max_bid }}, '{{ optional($b->auction_date)->format('d-m-Y') }}')">Mark Won</a>@endcan
                                        @can('bid_results.edit')<a href="#" class="btn btn-sm btn-outline-danger" onclick="openLost({{ $b->id }}, '{{ $b->lot_no }}', '{{ optional($b->auction_date)->format('d-m-Y') }}')">Mark Lost</a>@endcan
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No pending bids awaiting a result.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @can('bid_results.edit')
        <div id="wonModal" class="modal-block modal-block-success mfp-hide">
            <section class="card">
                <form method="POST" id="wonForm" action="" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Mark Won — <span id="won_customer_name"></span></h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Vendor <span class="text-danger">*</span></label>
                                <select class="form-control select2-js" name="vendor_id" required>
                                    <option value="" disabled selected>Select Vendor</option>
                                    @foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }} @if($v->location)({{ $v->location }})@endif</option>@endforeach
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2"><label>Buying Price (¥) <span class="text-danger">*</span></label><input type="number" id="won_buying_price" class="form-control" name="buying_price" min="1" required></div>
                            <div class="col-lg-6 mb-2">
                                <label>Won Date</label>
                                <p class="form-control-plaintext"><span id="won_date_display" class="fw-bold"></span> <small class="text-muted">(from Auction Date)</small></p>
                                @if(auth()->user()->isSuperAdmin())
                                    <input type="date" id="won_date" class="form-control" name="won_date" placeholder="Leave blank to use auction date">
                                    <small class="text-muted">Super Admin override — leave blank to use the auction date.</small>
                                @endif
                            </div>
                            <div class="col-lg-12 mb-2"><label>Winning Screenshot <span class="text-danger">*</span></label><input type="file" class="form-control" name="screenshot" accept="image/*" required></div>
                        </div>
                    </div>
                    <footer class="card-footer"><div class="col-md-12 text-end"><button type="submit" class="btn btn-success">Confirm Won</button><button type="button" class="btn btn-default modal-dismiss">Cancel</button></div></footer>
                </form>
            </section>
        </div>

        <div id="lostModal" class="modal-block modal-block-danger mfp-hide">
            <section class="card">
                <form method="POST" id="lostForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Mark Lost — Lot <span id="lost_lot_display"></span></h2></header>
                    <div class="card-body">
                        <label>Lost Date</label>
                        <p class="form-control-plaintext"><span id="lost_date_display" class="fw-bold"></span> <small class="text-muted">(from Auction Date)</small></p>
                        @if(auth()->user()->isSuperAdmin())
                            <input type="date" id="lost_date" class="form-control" name="lost_date" placeholder="Leave blank to use auction date">
                            <small class="text-muted">Super Admin override — leave blank to use the auction date.</small>
                        @endif
                    </div>
                    <footer class="card-footer"><div class="col-md-12 text-end"><button type="submit" class="btn btn-danger">Confirm Lost</button><button type="button" class="btn btn-default modal-dismiss">Cancel</button></div></footer>
                </form>
            </section>
        </div>
        @endcan

        @can('bid_sheets.edit')@include('bidding._assign_customer_modal')@endcan
    </div>
</div>

<script>
document.getElementById('checkAll')?.addEventListener('change', function () {
    document.querySelectorAll('input[name="bid_ids[]"]').forEach(cb => cb.checked = this.checked);
});
function openWon(bidId, customerName, maxBid, auctionDate) {
    $('#wonForm').attr('action', '/bids/' + bidId + '/won');
    $('#won_customer_name').text(customerName); $('#won_buying_price').val(maxBid);
    $('#won_date_display').text(auctionDate);
    $.magnificPopup.open({ items: { src: '#wonModal' }, type: 'inline' });
}
function openLost(bidId, lotNo, auctionDate) {
    $('#lostForm').attr('action', '/bids/' + bidId + '/lost');
    $('#lost_lot_display').text(lotNo || bidId);
    $('#lost_date_display').text(auctionDate);
    $.magnificPopup.open({ items: { src: '#lostModal' }, type: 'inline' });
}
</script>
@endsection