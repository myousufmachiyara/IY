@php
    $query = request()->except('export');
    $cat = !empty($report['category']) ? (\App\Support\ReportCatalog::CATEGORIES[$report['category']] ?? null) : null;
@endphp
<header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <div>
        @if($cat)
            <h2 class="card-title mb-0"><i class="fas {{ $cat['icon'] }} me-1"></i>{{ $cat['label'] }}</h2>
            <div class="small text-muted"><strong>{{ $title }}</strong>@if(!empty($report['description'])) — {{ $report['description'] }}@endif</div>
        @else
            <h2 class="card-title mb-0">{{ $title }}</h2>
            @if(!empty($report['description']))<div class="small text-muted">{{ $report['description'] }}</div>@endif
        @endif
    </div>
    <div class="d-flex gap-1">
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-light"><i class="fas fa-th-large"></i> All Reports</a>
        <a href="{{ url()->current() . '?' . http_build_query($query + ['export' => 'excel']) }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Excel</a>
        <a href="{{ url()->current() . '?' . http_build_query($query + ['export' => 'pdf']) }}" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
    </div>
</header>

@include('reports._tabs', ['active' => $reportKey, 'hideCategory' => true])
