@extends('layouts.app')

@section('title', 'Shipments | All Shipments')

@section('content')

@php $statusColors = ['preparing' => 'warning text-dark', 'dispatched' => 'info', 'arrived' => 'success']; @endphp

<div class="row">
    <div class="col">
        <section class="card">

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">All Shipments</h2>
                @can('shipments.create')
                    <button type="button" class="btn btn-primary btn-sm" onclick="openNewShipment()">
                        <i class="fa fa-plus"></i> New Shipment
                    </button>
                @endcan
            </header>

            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Method</th>
                                <th>Vehicles</th>
                                <th>Shipment Date</th>
                                <th>Expected Arrival</th>
                                <th>Arrival Date</th>
                                <th>Freight Total</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shipments as $s)
                            <tr>
                                <td><a href="{{ route('customers.show', $s->customer) }}">{{ $s->customer->name }}</a></td>
                                <td>{{ $s->method }}</td>
                                <td>{{ $s->vehicles->count() }}</td>
                                <td>{{ optional($s->shipment_date)->format('d-m-Y') ?? '—' }}</td>
                                <td>{{ optional($s->expected_arrival)->format('d-m-Y') ?? '—' }}</td>
                                <td>{{ optional($s->arrived_at)->format('d-m-Y') ?? '—' }}</td>
                                <td>¥{{ number_format($s->freight_total) }}</td>
                                <td><span class="badge bg-{{ $statusColors[$s->status] ?? 'secondary' }} text-uppercase">{{ $s->status }}</span></td>
                                <td class="text-nowrap">
                                <a href="{{ route('shipments.show', $s) }}" class="text-secondary me-1" title="View">
                                    <i class="fa fa-eye"></i>
                                </a>
                                @can('shipments.edit')
                                    @if($s->status === 'preparing')
                                        <a href="{{ route('shipments.edit', $s) }}" class="text-primary" title="Edit">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                @endcan
                            </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No shipments yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <p class="text-muted small mt-3 mb-0">
                    <i class="fa fa-info-circle"></i> A shipment can carry several vehicles for one customer at once —
                    click "New Shipment" above, or use "Prepare Shipment" on a specific vehicle's detail page.
                </p>
            </div>
        </section>

        @can('shipments.create')
        <div id="newShipmentModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="GET" id="newShipmentForm" action="" onkeydown="return event.key != 'Enter';">
                    <header class="card-header"><h2 class="card-title">New Shipment</h2></header>
                    <div class="card-body">
                        <label>Customer <span class="text-danger">*</span></label>
                        <select id="ns_customer_select" class="form-control select2-js" required>
                            <option value="" disabled selected>Loading customers…</option>
                        </select>
                        <small class="text-muted d-block mt-1">Only customers with at least one shipment-eligible vehicle (invoiced, 50%+ paid, not already on a shipment) are listed. You'll pick which vehicle(s) to include on the next step.</small>
                    </div>
                    <footer class="card-footer">
                        <div class="col-md-12 text-end">
                            <button type="button" class="btn btn-primary" onclick="goToShipmentCreate()">Continue</button>
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
function openNewShipment() {
    $.magnificPopup.open({ items: { src: '#newShipmentModal' }, type: 'inline' });
    fetch('/shipments/new-options')
        .then(r => r.json())
        .then(customers => {
            const select = $('#ns_customer_select');
            select.empty();
            if (customers.length === 0) {
                select.append('<option value="" disabled selected>No customers currently eligible</option>');
            } else {
                select.append('<option value="" disabled selected>Select customer</option>');
                customers.forEach(c => {
                    select.append(`<option value="${c.id}">${c.name} (${c.vehicle_count} vehicle${c.vehicle_count === 1 ? '' : 's'} eligible)</option>`);
                });
            }
            select.trigger('change');
        })
        .catch(() => {
            $('#ns_customer_select').empty().append('<option value="" disabled selected>Failed to load — try again</option>').trigger('change');
        });
}
function goToShipmentCreate() {
    const customerId = $('#ns_customer_select').val();
    if (!customerId) { alert('Select a customer first.'); return; }
    window.location.href = '/customers/' + customerId + '/shipments/create';
}
</script>
@endsection