@extends('layouts.app')

@section('title', 'Reports | Vendor-wise')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Reports</h2>
                <div>
                    <a href="{{ route('reports.vendor_wise', ['export' => 'excel']) }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Excel</a>
                    <a href="{{ route('reports.vendor_wise', ['export' => 'pdf']) }}" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </header>

            @include('reports._tabs', ['active' => 'vendor_wise'])

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th>Location</th>
                                <th class="text-end">Vehicles</th>
                                <th class="text-end">Payable</th>
                                <th class="text-end">Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                            <tr>
                                <td>{{ $r['vendor'] }}</td>
                                <td>{{ $r['location'] ?? '—' }}</td>
                                <td class="text-end">{{ $r['vehicles'] }}</td>
                                <td class="text-end">¥{{ number_format($r['payable']) }}</td>
                                <td class="text-end text-success">¥{{ number_format($r['paid']) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No vendor agents found.</td></tr>
                            @endforelse
                        </tbody>
                        @if($rows->isNotEmpty())
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="2">Total</td>
                                <td class="text-end">{{ $rows->sum('vehicles') }}</td>
                                <td class="text-end">¥{{ number_format($rows->sum('payable')) }}</td>
                                <td class="text-end">¥{{ number_format($rows->sum('paid')) }}</td>
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