@extends('layouts.app')

@section('title', 'Bidding Results | Pending')

@section('content')

@php $isPrivileged = auth()->user()->can('data.view_all'); @endphp

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
                <h2 class="card-title">Pending Bidding Results</h2>
            </header>

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Lot</th>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                <th>Chassis</th>
                                @if($isPrivileged)<th>Agent</th>@endif
                                <th>Max Bid</th>
                                <th>Auction Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bids as $b)
                            <tr>
                                <td>{{ $b->lot_no ?? '—' }}</td>
                                <td>
                                    @if($b->customer)
                                        {{ $b->customer->name }}
                                    @else
                                        <span class="badge bg-warning text-dark">Unassigned</span>
                                    @endif
                                </td>
                                <td>{{ trim("{$b->year} {$b->make} {$b->model}") ?: '—' }}</td>
                                <td>{{ $b->chassis_no ?? '—' }}</td>
                                @if($isPrivileged)<td>{{ $b->agent->name ?? '—' }}</td>@endif
                                <td>¥{{ number_format($b->max_bid) }}</td>
                                <td>{{ optional($b->auction_date)->format('d-m-Y') ?? '—' }}</td>
                                <td class="text-nowrap">
                                    @if(!$b->customer_id)
                                        @can('bid_sheets.edit')
                                            <a href="#" class="btn btn-sm btn-warning text-dark me-1"
                                               onclick="openAssignCustomer({{ $b->id }}, '{{ $b->lot_no }}')">
                                                Assign Customer
                                            </a>
                                        @endcan
                                    @else
                                        @can('results.edit')
                                            <a href="#" class="btn btn-sm btn-success me-1"
                                               onclick="openWon({{ $b->id }}, '{{ $b->customer->name }}', {{ $b->max_bid }})">
                                                Mark Won
                                            </a>
                                        @endcan
                                    @endif
                                    @can('results.edit')
                                        <form action="{{ route('bids.lost', $b) }}" method="POST" style="display:inline;"
                                              onsubmit="return confirm('Mark this bid as lost?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Mark Lost</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No pending bids awaiting a result.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- ================= MARK WON MODAL ================= --}}
        @can('results.edit')
        <div id="wonModal" class="modal-block modal-block-success mfp-hide">
            <section class="card">
                <form method="POST" id="wonForm" action="" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Mark Won — <span id="won_customer_name"></span></h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Vendor <span class="text-danger">*</span></label>
                                <select data-plugin-selecttwo class="form-control select2-js" name="vendor_id" required>
                                    <option value="" disabled selected>Select Vendor</option>
                                    @foreach($vendors as $v)
                                        <option value="{{ $v->id }}">{{ $v->name }} @if($v->location) ({{ $v->location }}) @endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Buying Price (¥) <span class="text-danger">*</span></label>
                                <input type="number" id="won_buying_price" class="form-control" name="buying_price" min="1" required>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Winning Screenshot <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="screenshot" accept="image/*" required>
                                <small class="text-muted">Screenshot received from the vendor agent confirming the win.</small>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-success">Confirm Won &amp; Post to Vendor Ledger</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan

        @can('bid_sheets.edit')
            @include('bidding._assign_customer_modal')
        @endcan
    </div>
</div>

<script>
function openWon(bidId, customerName, maxBid) {
    $('#wonForm').attr('action', '/bids/' + bidId + '/won');
    $('#won_customer_name').text(customerName);
    $('#won_buying_price').val(maxBid);

    $.magnificPopup.open({ items: { src: '#wonModal' }, type: 'inline' });
}
</script>

@endsection