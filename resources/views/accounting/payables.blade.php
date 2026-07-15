@extends('layouts.app')

@section('title', 'Accounting | Payables')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Accounting</h2></header>

            @include('accounting._tabs', ['active' => 'payables'])

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr><th>Vendor</th><th class="text-end">Payable</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($vendors as $row)
                            <tr>
                                <td>{{ $row['vendor']->name }}</td>
                                <td class="text-end">¥{{ number_format($row['payable']) }}</td>
                                <td class="text-end text-success">¥{{ number_format($row['paid']) }}</td>
                                <td class="text-end fw-bold text-danger">¥{{ number_format($row['balance']) }}</td>
                                <td><a href="{{ route('vendor-payments.index', ['vendor_id' => $row['vendor']->id]) }}" class="btn btn-sm btn-outline-primary">View Payments</a></td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No outstanding payables.</td></tr>
                            @endforelse
                        </tbody>
                        @if($vendors->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold">
                                <td>Total</td>
                                <td class="text-end">¥{{ number_format($vendors->sum('payable')) }}</td>
                                <td class="text-end">¥{{ number_format($vendors->sum('paid')) }}</td>
                                <td class="text-end text-danger">¥{{ number_format($vendors->sum('balance')) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection