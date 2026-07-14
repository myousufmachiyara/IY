@extends('layouts.app')

@section('title', 'Bidding | My Bid Sheets')

@section('content')

@php $isPrivileged = auth()->user()->isSuperAdmin() || auth()->user()->isAccountant(); @endphp

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
                    <h2 class="card-title">Bid Sheets</h2>
                    <div>
                        <a href="{{ route('bid-sheets.template') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-download"></i> Download Template
                        </a>
                        @can('bid_sheets.create')
                            <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                                <i class="fas fa-upload"></i> Upload Bid Sheet
                            </button>
                        @endcan
                    </div>
                </div>
            </header>

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Title</th>
                                @if($isPrivileged)<th>Agent</th>@endif
                                <th>Auction Date</th>
                                <th>Rows</th>
                                <th>Won</th>
                                <th>Lost</th>
                                <th>Pending</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sheets as $s)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><a href="{{ route('bid-sheets.show', $s) }}"><strong>{{ $s->title }}</strong></a></td>
                                @if($isPrivileged)<td>{{ $s->agent->name ?? '—' }}</td>@endif
                                <td>{{ optional($s->auction_date)->format('d-m-Y') ?? '—' }}</td>
                                <td>{{ $s->bids_count }}</td>
                                <td><span class="badge bg-success">{{ $s->won_count }}</span></td>
                                <td><span class="badge bg-danger">{{ $s->lost_count }}</span></td>
                                <td><span class="badge bg-warning text-dark">{{ $s->bids_count - $s->won_count - $s->lost_count }}</span></td>
                                <td class="text-nowrap">
                                    <a href="{{ route('bid-sheets.show', $s) }}" class="text-secondary me-1" title="View">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    @can('bid_sheets.delete')
                                        <form action="{{ route('bid-sheets.destroy', $s) }}" method="POST" style="display:inline;"
                                              onsubmit="return confirm('Delete this bid sheet? Its bids will remain but lose the sheet reference.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-link p-0 text-danger">
                                                <i class="fa fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- ================= UPLOAD MODAL ================= --}}
        @can('bid_sheets.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('bid-sheets.store') }}" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Upload Bid Sheet</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-12 mb-2">
                                <label>Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" placeholder="e.g. USS Tokyo — 1 Aug Auction" required>
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Auction Date</label>
                                <input type="date" class="form-control" name="auction_date">
                            </div>
                            <div class="col-lg-12 mb-2">
                                <label>Excel File (.xlsx, .xls, .csv) <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="file" accept=".xlsx,.xls,.csv" required>
                                <small class="text-muted">
                                    Columns required: lot_no, auction_house, auction_date, make, model, year, grade, chassis_no, max_bid, customer_id.
                                    <a href="{{ route('bid-sheets.template') }}">Download the template</a> to get the exact format.
                                </small>
                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Upload &amp; Import</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan
    </div>
</div>

@endsection