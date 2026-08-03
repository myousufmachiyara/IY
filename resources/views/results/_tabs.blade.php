@php $active = $active ?? 'pending'; @endphp
<div class="tabs pb-0 pt-2">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link {{ $active==='pending' ? 'active' : '' }}" href="{{ route('results.index') }}">Pending</a></li>
        <li class="nav-item"><a class="nav-link {{ $active==='won' ? 'active' : '' }}" href="{{ route('results.won') }}">Won</a></li>
        <li class="nav-item"><a class="nav-link {{ $active==='lost' ? 'active' : '' }}" href="{{ route('results.lost') }}">Lost</a></li>

    </ul>
</div>