@extends('layouts.app')

@section('title', 'Vehicle | ' . $vehicle->label())

@section('content')

    @php
        $statusColors = [
            'requirement' => 'secondary', 'bidding' => 'info', 'won' => 'success', 'lost' => 'danger',
            'invoiced' => 'primary', 'dispatched' => 'warning', 'arrived' => 'warning', 'delivered' => 'success',
        ];
        $backRoute = $vehicle->isWon() ? route('results.won') : route('vehicles.index');
        $backLabel = $vehicle->isWon() ? 'Back to Won Results' : 'Back to All Vehicles';
    @endphp

    <div class="row">
        <div class="col">
            <section class="card">
                <header class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="card-title">
                        {{ $vehicle->label() }}
                        <span class="badge bg-{{ $statusColors[$vehicle->status] ?? 'secondary' }} text-uppercase ms-1">{{ $vehicle->status }}</span>
                    </h2>
                    <div>
                        <a href="{{ $backRoute }}" class="btn btn-sm btn-default">
                            <i class="fa fa-arrow-left"></i> {{ $backLabel }}
                        </a>
                        @can('vehicle_requirement.edit')
                            @if($vehicle->isWon())
                                <button type="button" class="btn btn-sm btn-outline-warning modal-with-form" href="#reassignCustomerModal"><i class="fa fa-user-edit"></i> Reassign Customer</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary modal-with-form" href="#reassignAgentModal"><i class="fa fa-user-tie"></i> Reassign Agent</button>
                            @endif
                        @endcan
                    </div>
                </header>

                @include('vehicles._tabs', ['vehicle' => $vehicle, 'active' => 'overview'])

                <div class="card-body">
                    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
                    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted text-uppercase small mb-2">Requirement</h6>
                            <table class="table table-borderless mb-3">
                                <tr><th width="160">Make / Model</th><td>{{ $vehicle->make }} {{ $vehicle->model }}</td></tr>
                                <tr><th>Year</th><td>{{ $vehicle->year ?? '—' }}</td></tr>
                                <tr><th>Grade</th><td>{{ $vehicle->grade ?? '—' }}</td></tr>
                                <tr><th>Chassis No.</th><td>{{ $vehicle->chassis_no ?? '—' }}</td></tr>
                                <tr><th>Budget</th><td>¥{{ number_format($vehicle->budget) }}</td></tr>
                                <tr><th>Requirement Date</th><td>{{ optional($vehicle->requirement_date)->format('d-m-Y') ?? $vehicle->created_at->format('d-m-Y') }}</td></tr>
                            </table>

                            <h6 class="text-muted text-uppercase small mb-2">Parties</h6>
                            <table class="table table-borderless mb-0">
                                <tr><th width="160">Customer</th><td><a href="{{ route('customers.show', $vehicle->customer) }}">{{ $vehicle->customer->name }}</a></td></tr>
                                <tr><th>Sales Agent</th><td>{{ $vehicle->agent->name ?? '—' }}</td></tr>
                                <tr><th>Vendor</th><td>{{ $vehicle->vendor->name ?? '—' }}</td></tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            @if($vehicle->isWon())
                                <div class="card bg-light border mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">Winning Bid</h6>
                                        <table class="table table-sm table-borderless mb-2">
                                            <tr><td>Buying Price</td><td class="text-end fw-bold">¥{{ number_format($vehicle->buying_price) }}</td></tr>
                                            <tr><td>Won On</td><td class="text-end">{{ optional($vehicle->won_at)->format('d-m-Y') }}</td></tr>
                                            @if($vehicle->selling_price)
                                                <tr><td>Selling Price</td><td class="text-end fw-bold">¥{{ number_format($vehicle->selling_price) }}</td></tr>
                                            @endif
                                            @if($vehicle->costing)
                                                <tr><td>Profit</td><td class="text-end fw-bold {{ $vehicle->costing->profit >= 0 ? 'text-success' : 'text-danger' }}">¥{{ number_format($vehicle->costing->profit) }}</td></tr>
                                            @endif
                                        </table>
                                        @if($vehicle->winning_screenshot_path)
                                            <a href="{{ \App\Services\PublicStorage::url($vehicle->winning_screenshot_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa fa-image"></i> View Winning Screenshot</a>
                                        @endif
                                        @if($vehicle->customer->security_deposit_status === 'approved')
                                            <a href="{{ route('vehicles.deposit_invoice_pdf', $vehicle) }}" class="btn btn-sm btn-outline-secondary">
                                                <i class="fa fa-file-pdf"></i> Auction Deposit Invoice
                                            </a>
                                        @endif
                                    </div>
                                </div>

                                {{-- Contextual next-step actions --}}
                                @if(!$vehicle->costing || $vehicle->costing->total_costing == 0)
                                    <div class="alert alert-warning">
                                        <i class="fa fa-exclamation-triangle"></i> Costing breakdown not yet prepared.
                                        <a href="{{ route('costings.show', $vehicle) }}" class="alert-link">Complete it now &rarr;</a>
                                    </div>
                                @elseif(!$vehicle->invoice)
                                    <div class="alert alert-info">
                                        <i class="fa fa-info-circle"></i> Costing complete — ready to invoice.
                                        @can('invoices.create')
                                            <form action="{{ route('invoices.store', $vehicle) }}" method="POST" class="d-inline-flex align-items-end gap-2 mt-2" onsubmit="return confirm('Generate the official invoice for this vehicle?');">
                                                @csrf
                                                <div>
                                                    <label class="small mb-1 d-block">Invoice Date</label>
                                                    <input type="date" name="issued_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}"
                                                        @unless(auth()->user()->isSuperAdmin()) readonly @endunless required>
                                                </div>
                                                <button class="btn btn-sm btn-primary">Generate Invoice</button>
                                            </form>
                                        @else
                                            @if($vehicle->invoice_requested_at)
                                                <span class="badge bg-info ms-2">Invoice requested {{ $vehicle->invoice_requested_at->diffForHumans() }}</span>
                                                <form action="{{ route('vehicles.cancel_invoice_request', $vehicle) }}" method="POST" class="d-inline ms-1" onsubmit="return confirm('Cancel this invoice request?');">
                                                    @csrf<button class="btn btn-sm btn-outline-warning">Cancel Request</button>
                                                </form>
                                            @else
                                                <form action="{{ route('vehicles.request_invoice', $vehicle) }}" method="POST" class="d-inline"><button class="btn btn-sm btn-outline-primary ms-2">@csrf Request Invoice</button></form>
                                            @endif
                                        @endcan
                                    </div>
                                @elseif(($vehicle->invoice->isHalfPaid() || auth()->user()->isSuperAdmin()) && !$vehicle->shipment)
                                    <div class="alert alert-success">
                                        <i class="fa fa-check-circle"></i>
                                        @if($vehicle->invoice->isHalfPaid())
                                            50% or more paid — ready for shipment.
                                        @else
                                            Not yet 50% paid, but you can bypass this as Super Admin.
                                        @endif
                                        @can('invoices.request')
                                            <a href="{{ route('shipments.create', $vehicle->customer) }}" class="alert-link ms-1">Prepare Shipment &rarr;</a>
                                        @endcan
                                    </div>
                                @endif
                            @else
                                <div class="alert alert-light border">
                                    <i class="fa fa-gavel text-muted"></i>
                                    This vehicle requirement hasn't been won at auction yet.
                                    @can('bid_sheets.index')
                                        Track its bid via <a href="{{ route('bid-sheets.index') }}">Bid Sheets</a>.
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
    @can('vehicle_requirement.edit')
        <div id="reassignCustomerModal" class="modal-block modal-block-danger mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('vehicles.reassign', $vehicle) }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Reassign to Another Customer</h2></header>
                    <div class="card-body">
                        <div class="alert alert-warning py-2">This cancels any existing invoice on this vehicle and reverses its ledger entry.</div>
                        <label>New Customer <span class="text-danger">*</span></label>
                        <select data-plugin-selecttwo class="form-control select2-js" name="customer_id" required>
                            <option value="" disabled selected>Select customer</option>
                            @foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                        <label class="mt-2">Reason</label>
                        <textarea class="form-control" name="reason" rows="2"></textarea>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-danger">Confirm Reassign</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>

        <div id="reassignAgentModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('vehicles.reassign_agent', $vehicle) }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Reassign Agent</h2></header>
                    <div class="card-body">
                        <label>New Agent <span class="text-danger">*</span></label>
                        <select data-plugin-selecttwo class="form-control select2-js" name="agent_id" required>
                            <option value="" disabled selected>Select agent</option>
                            @foreach($agents as $a)<option value="{{ $a->id }}" {{ $vehicle->agent_id == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>@endforeach
                        </select>
                        <small class="text-muted">Changes only this vehicle's agent — the customer's own assigned agent is unaffected.</small>
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
@endsection