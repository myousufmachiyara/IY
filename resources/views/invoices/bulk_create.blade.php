@extends('layouts.app')

@section('title', 'Bulk Generate Invoices | ' . $customer->name)

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Bulk Generate Invoices — {{ $customer->name }}</h2>
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Back to Customer</a>
            </header>
            <div class="card-body">
                @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
                <form method="POST" action="{{ route('invoices.bulk_store', $customer) }}">
                    @csrf
                    <div class="mb-3" style="max-width:220px;">
                        <label>Invoice Date <span class="text-danger">*</span></label>
                        <input type="date" name="issued_date" class="form-control" value="{{ date('Y-m-d') }}"
                            @unless(auth()->user()->isSuperAdmin()) readonly @endunless required>
                    </div>
                    <div class="table-scroll mb-3">
                        <table class="table table-bordered table-striped mb-0">
                            <thead><tr><th style="width:40px;"><input type="checkbox" id="checkAll"></th><th>Vehicle</th><th>Buying Price</th><th>Sale Price</th></tr></thead>
                            <tbody>
                                @foreach($eligible as $v)
                                @php $sale = $v->selling_price ?: $v->costing?->sale_price; @endphp
                                <tr>
                                    <td><input type="checkbox" class="bulk-check" name="vehicle_ids[]" value="{{ $v->id }}"></td>
                                    <td>{{ $v->label() }}</td>
                                    <td>¥{{ number_format($v->buying_price) }}</td>
                                    <td>¥{{ number_format($sale) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary">Generate Selected Invoices</button>
                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-light">Cancel</a>
                </form>
            </div>
        </section>
    </div>
</div>
<script>
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.bulk-check').forEach(cb => cb.checked = this.checked);
});
</script>
@endsection