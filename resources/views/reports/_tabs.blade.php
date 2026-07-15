@php $active = $active ?? 'agent_wise'; @endphp
<div class="tabs pb-0 pt-2">
    <ul class="nav nav-tabs">
        @can('reports.agent_wise')
            <li class="nav-item"><a class="nav-link {{ $active === 'agent_wise' ? 'active' : '' }}" href="{{ route('reports.agent_wise') }}">Agent-wise</a></li>
        @endcan
        @can('reports.vendor_wise')
            <li class="nav-item"><a class="nav-link {{ $active === 'vendor_wise' ? 'active' : '' }}" href="{{ route('reports.vendor_wise') }}">Vendor-wise</a></li>
        @endcan
        @can('reports.bid_wise')
            <li class="nav-item"><a class="nav-link {{ $active === 'bid_wise' ? 'active' : '' }}" href="{{ route('reports.bid_wise') }}">Bid-wise</a></li>
        @endcan
        @can('reports.bid_won')
            <li class="nav-item"><a class="nav-link {{ $active === 'bid_won' ? 'active' : '' }}" href="{{ route('reports.bid_won') }}">Bid Won</a></li>
        @endcan
    </ul>
</div>