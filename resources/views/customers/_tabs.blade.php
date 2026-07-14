@php $active = $active ?? 'overview'; @endphp
<div class="tabs pb-0 pt-2">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link {{ $active === 'overview' ? 'active' : '' }}" href="{{ route('customers.show', $customer) }}">
                <i class="fa fa-id-card me-1"></i> Overview
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $active === 'vehicles' ? 'active' : '' }}" href="{{ route('vehicles.index', ['customer_id' => $customer->id]) }}">
                <i class="fa fa-car me-1"></i> Vehicles
                <span class="badge bg-secondary">{{ $customer->vehicles()->count() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $active === 'ledger' ? 'active' : '' }}" href="{{ route('payments.customer_ledger', $customer) }}">
                <i class="fa fa-book me-1"></i> Ledger
            </a>
        </li>
    </ul>
</div>