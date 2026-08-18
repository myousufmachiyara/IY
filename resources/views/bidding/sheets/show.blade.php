@extends('layouts.app')

@section('title', 'Bid Sheet | ' . $sheet->title)

@section('content')

@php
    $resultColors = ['pending' => 'warning text-dark', 'won' => 'success', 'lost' => 'danger'];
@endphp

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">{{ $sheet->title }}</h2>
                <div>
                    @can('bid_sheets.edit')
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editSheet({{ $sheet->id }})">
                            <i class="fa fa-edit"></i> Edit Sheet
                        </button>
                    @endcan
                    <a href="{{ route('bid-sheets.index') }}" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Back to Bid Sheets</a>
                </div>
            </header>

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
                @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
                @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

                <div class="row mb-3">
                    <div class="col-md-4"><strong>Agent:</strong> {{ $sheet->agent->name ?? '—' }}</div>
                    <div class="col-md-4"><strong>Auction Date:</strong> {{ optional($sheet->auction_date)->format('d-m-Y') ?? '—' }}</div>
                    <div class="col-md-4"><strong>Rows Imported:</strong> {{ $sheet->live_count }}</div>
                </div>

                @can('bid_sheets.edit')
                <form method="POST" action="{{ route('bid-sheets.bulk_assign') }}" id="bulkAssignForm">
                    @csrf @method('PUT')
                    <div class="d-flex gap-2 mb-2 align-items-end flex-wrap">
                        <div style="min-width:280px;">
                            <label class="small mb-1">Bulk assign selected bids to</label>
                            <select name="customer_id" class="form-control select2-js" required>
                                <option value="" disabled selected>Select customer</option>
                                @forelse($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @empty
                                    <option value="" disabled>No customers with a completed profile yet</option>
                                @endforelse
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fa fa-users"></i> Assign Selected
                        </button>
                        <small class="text-muted">Check the boxes on pending rows below, then choose a customer here.</small>
                    </div>
                </form>
                @endcan

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                @can('bid_sheets.edit')<th style="width:40px;"><input type="checkbox" id="checkAll" title="Select all pending rows"></th>@endcan
                                <th>Lot</th>
                                <th>Auction House</th>
                                <th>Vehicle</th>
                                <th>Chassis</th>
                                <th>Priority</th>
                                <th>Customer</th>
                                <th>Max Bid</th>
                                <th>Result</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sheet->bids as $b)
                            <tr>
                                @can('bid_sheets.edit')
                                    <td>@if($b->result === 'pending')<input type="checkbox" class="bid-check" name="bid_ids[]" value="{{ $b->id }}" form="bulkAssignForm">@endif</td>
                                @endcan
                                <td>{{ $b->lot_no ?? '—' }}</td>
                                <td>{{ $b->auction_house ?? '—' }}</td>
                                <td>{{ trim("{$b->year} {$b->make} {$b->model}") ?: '—' }}</td>
                                <td>{{ $b->chassis_no ?? '—' }}</td>
                                <td>{{ $b->priority ?? '—' }}</td>
                                <td>
                                    @if($b->customer)
                                        {{ $b->customer->name }}
                                    @elseif($b->result === 'pending')
                                        <span class="badge bg-warning text-dark me-1">Unassigned</span>
                                        @can('bid_sheets.edit')
                                            <a href="#" class="small" onclick="openAssignCustomer({{ $b->id }}, '{{ $b->lot_no }}')">Assign</a>
                                        @endcan
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>¥{{ number_format($b->max_bid) }}</td>
                                <td><span class="badge bg-{{ $resultColors[$b->result] ?? 'secondary' }} text-uppercase">{{ $b->result }}</span></td>
                                <td class="text-nowrap">
                                    @if($b->result === 'pending')
                                        @can('bid_sheets.edit')
                                            <a href="#" class="text-primary me-1" title="Edit" onclick="editBid({{ $b->id }})">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <form action="{{ route('bids.destroy', $b) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this bid row?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-link p-0 text-danger" title="Delete"><i class="fa fa-trash-alt"></i></button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No rows imported from this sheet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can('bid_results.index')
                    <p class="text-muted small mt-3 mb-0"><i class="fa fa-info-circle"></i> Pending bids are marked won or lost from the <a href="{{ route('results.index') }}">Bidding Results</a> screen.</p>
                @endcan
            </div>
        </section>

        @can('bid_sheets.edit')
            @include('bidding._assign_customer_modal')

            <div id="editSheetModal" class="modal-block modal-block-primary mfp-hide">
                <section class="card">
                    <form method="POST" id="editSheetForm" action="" onkeydown="return event.key != 'Enter';">
                        @csrf @method('PUT')
                        <header class="card-header"><h2 class="card-title">Edit Bid Sheet</h2></header>
                        <div class="card-body">
                            <div class="row form-group">
                                <div class="col-lg-12 mb-2">
                                    <label>Title <span class="text-danger">*</span></label>
                                    <input type="text" id="edit_sheet_title" class="form-control" name="title" required>
                                </div>
                                <div class="col-lg-12 mb-2">
                                    <label>Auction Date</label>
                                    <input type="date" id="edit_sheet_date" class="form-control" name="auction_date">
                                </div>
                            </div>
                        </div>
                        <footer class="card-footer">
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-primary">Save</button>
                                <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                            </div>
                        </footer>
                    </form>
                </section>
            </div>

            <div id="editBidModal" class="modal-block modal-block-primary mfp-hide">
                <section class="card">
                    <form method="POST" id="editBidForm" action="" onkeydown="return event.key != 'Enter';">
                        @csrf @method('PUT')
                        <header class="card-header"><h2 class="card-title">Edit Bid — Lot <span id="edit_bid_lot_display"></span></h2></header>
                        <div class="card-body">
                            <div class="row form-group">
                                <div class="col-lg-4 mb-2"><label>Lot No</label><input type="text" id="eb_lot_no" class="form-control" name="lot_no"></div>
                                <div class="col-lg-4 mb-2"><label>Auction House</label><input type="text" id="eb_auction_house" class="form-control" name="auction_house"></div>
                                <div class="col-lg-4 mb-2"><label>Max Bid (¥) <span class="text-danger">*</span></label><input type="number" id="eb_max_bid" class="form-control" name="max_bid" min="0" required></div>
                                <div class="col-lg-4 mb-2"><label>Make</label><input type="text" id="eb_make" class="form-control" name="make"></div>
                                <div class="col-lg-4 mb-2"><label>Model</label><input type="text" id="eb_model" class="form-control" name="model"></div>
                                <div class="col-lg-4 mb-2"><label>Year</label><input type="text" id="eb_year" class="form-control" name="year"></div>
                                <div class="col-lg-4 mb-2"><label>Grade</label><input type="text" id="eb_grade" class="form-control" name="grade"></div>
                                <div class="col-lg-4 mb-2"><label>Fuel Type</label><input type="text" id="eb_fuel_type" class="form-control" name="fuel_type"></div>
                                <div class="col-lg-4 mb-2"><label>Color</label><input type="text" id="eb_color" class="form-control" name="color"></div>
                                <div class="col-lg-6 mb-2"><label>Engine</label><input type="text" id="eb_engine" class="form-control" name="engine"></div>
                                <div class="col-lg-6 mb-2"><label>Chassis No</label><input type="text" id="eb_chassis_no" class="form-control" name="chassis_no"></div>
                                <div class="col-lg-6 mb-2"><label>Priority</label><input type="number" id="eb_priority" class="form-control" name="priority" min="1" max="9"></div>
                            </div>
                        </div>
                        <footer class="card-footer">
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-primary">Save</button>
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
document.getElementById('checkAll')?.addEventListener('change', function () {
    document.querySelectorAll('.bid-check').forEach(cb => cb.checked = this.checked);
});

function editSheet(id) {
    fetch('/bid-sheets/' + id + '/edit').then(r => r.json()).then(data => {
        $('#editSheetForm').attr('action', '/bid-sheets/' + id);
        $('#edit_sheet_title').val(data.title);
        $('#edit_sheet_date').val(data.auction_date);
        $.magnificPopup.open({ items: { src: '#editSheetModal' }, type: 'inline' });
    }).catch(() => alert('Could not load bid sheet data.'));
}

function editBid(id) {
    fetch('/bids/' + id + '/edit').then(r => r.json()).then(data => {
        $('#editBidForm').attr('action', '/bids/' + id);
        $('#edit_bid_lot_display').text(data.lot_no || id);
        $('#eb_lot_no').val(data.lot_no);
        $('#eb_auction_house').val(data.auction_house);
        $('#eb_max_bid').val(data.max_bid);
        $('#eb_make').val(data.make);
        $('#eb_model').val(data.model);
        $('#eb_year').val(data.year);
        $('#eb_grade').val(data.grade);
        $('#eb_fuel_type').val(data.fuel_type);
        $('#eb_color').val(data.color);
        $('#eb_engine').val(data.engine);
        $('#eb_chassis_no').val(data.chassis_no);
        $('#eb_priority').val(data.priority);
        $.magnificPopup.open({ items: { src: '#editBidModal' }, type: 'inline' });
    }).catch(() => alert('Could not load bid data.'));
}
</script>
@endsection