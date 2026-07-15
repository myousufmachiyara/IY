@extends('layouts.app')

@section('title', 'Invoice | ' . $invoice->invoice_no)

@section('content')

@php
    $statusColors = ['draft'=>'secondary','issued'=>'info','partial'=>'warning text-dark','paid'=>'success','cancelled'=>'danger'];
    $firstOverdue = $invoice->due_first && !$invoice->isHalfPaid() && now()->gt($invoice->due_first) && $invoice->status !== 'cancelled';
    $finalOverdue = $invoice->due_final && !$invoice->isFullyPaid() && now()->gt($invoice->due_final) && $invoice->status !== 'cancelled';
@endphp

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">
                    Invoice {{ $invoice->invoice_no }}
                    <span class="badge bg-{{ $statusColors[$invoice->status] ?? 'secondary' }} text-uppercase ms-1">{{ $invoice->status }}</span>
                </h2>
                <div>
                    <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-sm btn-success">
                        <i class="fa fa-file-pdf"></i> Download / Share PDF
                    </a>
                    @can('invoices.edit')
                        @if($invoice->amount_paid == 0 && $invoice->status !== 'cancelled')
                            <form action="{{ route('invoices.cancel', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this invoice? This reverses its receivable entry.');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">Cancel Invoice</button>
                            </form>
                        @endif
                    @endcan
                    <a href="{{ route('vehicles.show', $invoice->vehicle) }}" class="btn btn-sm btn-default">
                        <i class="fa fa-arrow-left"></i> Back to Vehicle
                    </a>
                </div>
            </header>

            @include('vehicles._tabs', ['vehicle' => $invoice->vehicle, 'active' => 'invoice'])

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                @if($firstOverdue || $finalOverdue)
                <div class="alert alert-danger d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fa fa-exclamation-triangle"></i>
                        @if($firstOverdue)
                            First 50% payment is overdue (was due {{ $invoice->due_first->format('d-m-Y') }}).
                        @else
                            Final payment is overdue (was due {{ $invoice->due_final->format('d-m-Y') }}, before arrival).
                        @endif
                        This vehicle can be resold to another customer.
                    </div>
                    @can('vehicles.edit')
                        <button type="button" class="btn btn-sm btn-outline-danger modal-with-form" href="#reassignModal">
                            Reassign Vehicle
                        </button>
                    @endcan
                </div>
                @endif

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small mb-2">Bill To</h6>
                        <table class="table table-borderless mb-3">
                            <tr><th width="140">Customer</th><td><a href="{{ route('customers.show', $invoice->customer) }}">{{ $invoice->customer->name }}</a></td></tr>
                            <tr><th>Vehicle</th><td>{{ $invoice->vehicle->label() }}</td></tr>
                            <tr><th>Sales Agent</th><td>{{ $invoice->agent->name ?? '—' }}</td></tr>
                            <tr><th>Issued</th><td>{{ optional($invoice->issued_at)->format('d-m-Y') }}</td></tr>
                        </table>

                        <h6 class="text-muted text-uppercase small mb-2">Payment Schedule</h6>
                        <table class="table table-borderless mb-0">
                            <tr>
                                <th width="140">First 50%</th>
                                <td>
                                    {{ optional($invoice->due_first)->format('d-m-Y') ?? '—' }}
                                    @if($invoice->isHalfPaid())<span class="badge bg-success ms-1">Cleared</span>@elseif($firstOverdue)<span class="badge bg-danger ms-1">Overdue</span>@endif
                                </td>
                            </tr>
                            <tr>
                                <th>Final 50%</th>
                                <td>
                                    {{ optional($invoice->due_final)->format('d-m-Y') ?? 'Set once shipment is scheduled' }}
                                    @if($invoice->isFullyPaid())<span class="badge bg-success ms-1">Cleared</span>@elseif($finalOverdue)<span class="badge bg-danger ms-1">Overdue</span>@endif
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="col-md-6">
                        <div class="card bg-light border">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Amount Summary</h5>
                                <table class="table table-sm table-borderless mb-2">
                                    <tr><td>Sale Price</td><td class="text-end">¥{{ number_format($invoice->sale_price) }}</td></tr>
                                    <tr><td>Settled / Discount</td><td class="text-end">−¥{{ number_format($invoice->settled_amount) }}</td></tr>
                                    <tr class="fw-bold border-top"><td>Total Payable</td><td class="text-end">¥{{ number_format($invoice->total_payable) }}</td></tr>
                                    <tr><td>Amount Paid</td><td class="text-end text-success">¥{{ number_format($invoice->amount_paid) }}</td></tr>
                                    <tr class="fw-bold"><td>Balance Due</td><td class="text-end text-danger">¥{{ number_format($invoice->balance()) }}</td></tr>
                                </table>

                                <div class="progress mb-3" style="height:8px;">
                                    <div class="progress-bar bg-success" style="width: {{ min($invoice->paidPercent(), 100) }}%"></div>
                                </div>

                                <div class="d-flex gap-2">
                                    @can('payments.create')
                                        <button type="button" class="btn btn-sm btn-primary modal-with-form" href="#paymentModal">
                                            <i class="fa fa-plus"></i> Record Payment
                                        </button>
                                    @endcan
                                    @can('invoices.edit')
                                        <button type="button" class="btn btn-sm btn-outline-secondary modal-with-form" href="#settleModal">
                                            <i class="fa fa-percentage"></i> Adjust Settled Amount
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="text-muted text-uppercase small mt-4 mb-2">Payment History</h6>
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Recorded By</th>
                                <th>Backdated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoice->payments as $p)
                            <tr>
                                <td>{{ $p->paid_at->format('d-m-Y') }}</td>
                                <td>¥{{ number_format($p->amount) }}</td>
                                <td class="text-capitalize">{{ $p->method }}</td>
                                <td>{{ $p->reference ?? '—' }}</td>
                                <td>{{ $p->recorder->name ?? '—' }}</td>
                                <td>{{ $p->is_backdated ? 'Yes' : '—' }}</td>
                                <td class="text-nowrap">
                                    @can('payments.edit')
                                        <a href="#" class="text-primary me-1" title="Edit"
                                           onclick="editPayment({{ $p->id }}, {{ $p->amount }}, '{{ $p->method }}', '{{ $p->paid_at->format('Y-m-d') }}', '{{ $p->reference }}')">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endcan
                                    @can('payments.delete')
                                        <form action="{{ route('payments.destroy', $p) }}" method="POST" style="display:inline;"
                                              onsubmit="return confirm('Delete this payment? The ledger entry will be reversed.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-link p-0 text-danger"><i class="fa fa-trash-alt"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No payments recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- ================= RECORD PAYMENT MODAL ================= --}}
        @can('payments.create')
        <div id="paymentModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('payments.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <input type="hidden" name="customer_id" value="{{ $invoice->customer_id }}">
                    <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                    <input type="hidden" name="vehicle_id" value="{{ $invoice->vehicle_id }}">
                    <header class="card-header"><h2 class="card-title">Record Payment — {{ $invoice->invoice_no }}</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Amount (¥) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="amount" min="1" max="{{ $invoice->balance() }}" value="{{ $invoice->balance() }}" required>
                                <small class="text-muted">Balance due: ¥{{ number_format($invoice->balance()) }}</small>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Method <span class="text-danger">*</span></label>
                                <select class="form-control select2-js" name="method" required>
                                    <option value="bank" selected>Bank</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Date Paid <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="paid_at" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label>Reference</label>
                                <input type="text" class="form-control" name="reference" placeholder="Transaction ID / cheque no.">
                            </div>
                            @if(auth()->user()->canBackdate())
                            <div class="col-lg-12 mb-2">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_backdated" id="pay_is_backdated" value="1">
                                    <label class="form-check-label" for="pay_is_backdated">This is a back-dated entry</label>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Record Payment</button>
                            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan

        {{-- ================= SETTLE AMOUNT MODAL ================= --}}
        @can('invoices.edit')
        <div id="settleModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('invoices.settle', $invoice) }}" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Adjust Settled Amount</h2></header>
                    <div class="card-body">
                        <label>Settled Amount (¥)</label>
                        <input type="number" class="form-control" name="settled_amount" min="0" max="{{ $invoice->sale_price }}" value="{{ $invoice->settled_amount }}" required>
                        <small class="text-muted">Use this for small negotiated discounts. Total payable = Sale Price − Settled Amount.</small>
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

        {{-- ================= REASSIGN MODAL ================= --}}
        @can('vehicles.edit')
        <div id="reassignModal" class="modal-block modal-block-danger mfp-hide">
            <section class="card">
                <form method="POST" action="{{ route('vehicles.reassign', $invoice->vehicle) }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">Reassign Vehicle to Another Customer</h2></header>
                    <div class="card-body">
                        <div class="alert alert-warning py-2">This will cancel invoice {{ $invoice->invoice_no }} and free the vehicle for a new customer.</div>
                        <label>New Customer <span class="text-danger">*</span></label>
                        <select data-plugin-selecttwo class="form-control select2-js" name="customer_id" required>
                            <option value="" disabled selected>Select customer</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <label class="mt-2">Reason</label>
                        <textarea class="form-control" name="reason" rows="2" placeholder="e.g. Payment not received within grace period"></textarea>
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
        @endcan

        @include('payments._edit_modal')
    </div>
</div>
@endsection