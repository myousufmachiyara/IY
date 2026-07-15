@extends('layouts.app')

@section('title', 'Customer Ledger | ' . $customer->name)

@section('content')

@php $statusColors = ['draft'=>'secondary','issued'=>'info','partial'=>'warning text-dark','paid'=>'success','cancelled'=>'danger']; @endphp

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Ledger — {{ $customer->name }}</h2>
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Customer
                </a>
            </header>

            @include('customers._tabs', ['customer' => $customer, 'active' => 'ledger'])

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-light border"><div class="card-body text-center">
                            <div class="text-muted small text-uppercase">Total Invoiced</div>
                            <h4 class="mb-0">¥{{ number_format($customer->totalInvoiced()) }}</h4>
                        </div></div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light border"><div class="card-body text-center">
                            <div class="text-muted small text-uppercase">Total Paid</div>
                            <h4 class="mb-0 text-success">¥{{ number_format($customer->totalPaid()) }}</h4>
                        </div></div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light border"><div class="card-body text-center">
                            <div class="text-muted small text-uppercase">Balance Due</div>
                            <h4 class="mb-0 text-danger">¥{{ number_format($customer->balance()) }}</h4>
                        </div></div>
                    </div>
                </div>

                @can('payments.create')
                <button type="button" class="btn btn-primary mb-3 modal-with-form" href="#paymentModal" onclick="setPaymentInvoice('')">
                    <i class="fa fa-plus"></i> Record Payment (Against Total Balance)
                </button>
                @endcan

                <h6 class="text-muted text-uppercase small mb-2">Invoices</h6>
                <div class="table-scroll mb-4">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr><th>Invoice #</th><th>Sale Price</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($customer->invoices as $inv)
                            <tr>
                                <td><a href="{{ route('invoices.show', $inv) }}">{{ $inv->invoice_no }}</a></td>
                                <td>¥{{ number_format($inv->sale_price) }}</td>
                                <td>¥{{ number_format($inv->amount_paid) }}</td>
                                <td class="{{ $inv->balance() > 0 ? 'text-danger' : 'text-success' }}">¥{{ number_format($inv->balance()) }}</td>
                                <td><span class="badge bg-{{ $statusColors[$inv->status] ?? 'secondary' }} text-uppercase">{{ $inv->status }}</span></td>
                                <td>
                                    @can('payments.create')
                                        @if($inv->balance() > 0)
                                        <a href="#" class="btn btn-sm btn-outline-primary modal-with-form" href="#paymentModal"
                                           onclick="setPaymentInvoice({{ $inv->id }}, {{ $inv->balance() }})">Pay</a>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No invoices yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h6 class="text-muted text-uppercase small mb-2">Payment History</h6>
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr><th>Date</th><th>Invoice</th><th>Amount</th><th>Method</th><th>Reference</th><th>Backdated</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($customer->payments as $p)
                            <tr>
                                <td>{{ $p->paid_at->format('d-m-Y') }}</td>
                                <td>{{ $p->invoice->invoice_no ?? 'General / Total Balance' }}</td>
                                <td>¥{{ number_format($p->amount) }}</td>
                                <td class="text-capitalize">{{ $p->method }}</td>
                                <td>{{ $p->reference ?? '—' }}</td>
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
                    <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                    <input type="hidden" id="ledger_invoice_id" name="invoice_id" value="">
                    <header class="card-header"><h2 class="card-title" id="ledger_payment_title">Record Payment</h2></header>
                    <div class="card-body">
                        <div class="row form-group">
                            <div class="col-lg-6 mb-2">
                                <label>Amount (¥) <span class="text-danger">*</span></label>
                                <input type="number" id="ledger_amount" class="form-control" name="amount" min="1" required>
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
                                <input type="text" class="form-control" name="reference">
                            </div>
                            @if(auth()->user()->canBackdate())
                            <div class="col-lg-12 mb-2">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_backdated" id="ledger_is_backdated" value="1">
                                    <label class="form-check-label" for="ledger_is_backdated">This is a back-dated entry</label>
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

        @include('payments._edit_modal')
    </div>
</div>

<script>
function setPaymentInvoice(invoiceId, balance) {
    document.getElementById('ledger_invoice_id').value = invoiceId || '';
    document.getElementById('ledger_payment_title').textContent = invoiceId ? 'Record Payment' : 'Record Payment (Against Total Balance)';
    if (balance) document.getElementById('ledger_amount').value = balance;
}
</script>
@endsection