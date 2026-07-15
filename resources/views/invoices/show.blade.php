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