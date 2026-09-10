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
        @can('reports.customer_wise')
            <li class="nav-item"><a class="nav-link {{ $active === 'customer_wise' ? 'active' : '' }}" href="{{ route('reports.customer_wise') }}">Customer-wise</a></li>
        @endcan
        @can('reports.agent_profitability')
            <li class="nav-item"><a class="nav-link {{ $active === 'agent_profitability' ? 'active' : '' }}" href="{{ route('reports.agent_profitability') }}">Agent Profitability</a></li>
        @endcan
        @can('reports.customer_profitability')
            <li class="nav-item"><a class="nav-link {{ $active === 'customer_profitability' ? 'active' : '' }}" href="{{ route('reports.customer_profitability') }}">Customer Profitability</a></li>
        @endcan
        @can('reports.vehicle_profitability')
            <li class="nav-item"><a class="nav-link {{ $active === 'vehicle_profitability' ? 'active' : '' }}" href="{{ route('reports.vehicle_profitability') }}">Vehicle Profitability</a></li>
        @endcan
        @can('reports.auction_house_performance')
            <li class="nav-item"><a class="nav-link {{ $active === 'auction_house_performance' ? 'active' : '' }}" href="{{ route('reports.auction_house_performance') }}">Auction House Performance</a></li>
        @endcan
        @can('reports.shipment')
            <li class="nav-item"><a class="nav-link {{ $active === 'shipment' ? 'active' : '' }}" href="{{ route('reports.shipment') }}">Shipment</a></li>
        @endcan
    </ul>
</div>