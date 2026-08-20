@extends('layouts.app')

@section('title', 'Merge Invoices | ' . $customer->name)

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Merge Invoices to PDF — {{ $customer->name }}</h2>
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Back to Customer</a>
            </header>
            <div class="card-body">
                <p class="text-muted">Select which of this customer's invoices to combine into one downloadable PDF. Each car's invoice appears in full, on its own page, within the merged document — nothing is combined or summed.</p>
                <form method="POST" action="{{ route('invoices.merge_pdf', $customer) }}">
                    @csrf
                    <div class="table-scroll mb-3">
                        <table class="table table-bordered table-striped mb-0">
                            <thead><tr><th style="width:40px;"><input type="checkbox" id="checkAll"></th><th>Invoice #</th><th>Vehicle</th><th>Status</th><th>Total Payable</th></tr></thead>
                            <tbody>
                                @foreach($invoices as $inv)
                                <tr>
                                    <td><input type="checkbox" class="bulk-check" name="invoice_ids[]" value="{{ $inv->id }}" checked></td>
                                    <td>{{ $inv->invoice_no }}</td>
                                    <td>{{ $inv->vehicle->label() }}</td>
                                    <td><span class="badge bg-secondary text-uppercase">{{ $inv->status }}</span></td>
                                    <td>¥{{ number_format($inv->total_payable) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-file-pdf"></i> Download Merged PDF</button>
                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-light">Cancel</a>
                </form>
            </div>
        </section>
    </div>
</div>
<script>
document.getElementById('checkAll')?.addEventListener('change', function () {
    document.querySelectorAll('.bulk-check').forEach(cb => cb.checked = this.checked);
});
</script>
@endsection