@php
    // Tabs = the other reports in the same category as the one being viewed (App\Support\ReportCatalog).
    $active   = $active ?? null;
    $current  = $active ? \App\Support\ReportCatalog::find($active) : null;
    $visible  = \App\Support\ReportCatalog::visibleTo(auth()->user());
    $category = $current ? ($visible[$current['category']] ?? null) : null;
@endphp
@if($category)
<div class="tabs pb-0 pt-2 report-tabs">
    <div class="px-3 small text-muted mb-1"><i class="fas {{ $category['icon'] }} me-1"></i>{{ $category['label'] }}</div>
    <ul class="nav nav-tabs flex-nowrap overflow-auto">
        @foreach($category['reports'] as $key => $r)
            <li class="nav-item"><a class="nav-link text-nowrap {{ $active === $key ? 'active' : '' }}" href="{{ route($r['route']) }}">{{ $r['label'] }}</a></li>
        @endforeach
    </ul>
</div>
@endif
