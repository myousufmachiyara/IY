@php $active = $active ?? 'overview'; @endphp
<div class="tabs pb-0 pt-2">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link {{ $active === 'overview' ? 'active' : '' }}" href="{{ route('vehicles.show', $vehicle) }}">
                <i class="fa fa-car me-1"></i> Overview
            </a>
        </li>
        @if($vehicle->isWon())
            <li class="nav-item">
                <a class="nav-link {{ $active === 'costing' ? 'active' : '' }}" href="{{ route('costings.show', $vehicle) }}">
                    <i class="fa fa-calculator me-1"></i> Costing
                </a>
            </li>
            @if($vehicle->invoice)
                <li class="nav-item">
                    <a class="nav-link {{ $active === 'invoice' ? 'active' : '' }}" href="{{ route('invoices.show', $vehicle->invoice) }}">
                        <i class="fa fa-file-invoice-dollar me-1"></i> Invoice
                    </a>
                </li>
            @endif
            <li class="nav-item">
                <a class="nav-link {{ $active === 'documents' ? 'active' : '' }}" href="{{ route('documents.index', $vehicle) }}">
                    <i class="fa fa-folder-open me-1"></i> Documents
                </a>
            </li>
            @if($vehicle->shipment)
                <li class="nav-item">
                    <a class="nav-link {{ $active === 'shipment' ? 'active' : '' }}" href="{{ route('shipments.show', $vehicle->shipment) }}">
                        <i class="fa fa-ship me-1"></i> Shipment
                    </a>
                </li>
            @elseif($vehicle->status === 'invoiced' && $vehicle->invoice?->isHalfPaid())
                @can('shipments.create')
                <li class="nav-item">
                    <a class="nav-link {{ $active === 'shipment' ? 'active' : '' }}" href="{{ route('shipments.create', $vehicle->customer) }}">
                        <i class="fa fa-ship me-1"></i> Prepare Shipment
                    </a>
                </li>
                @endcan
            @endif
        @endif
    </ul>
</div>