@php $query = request()->except('export'); @endphp
<header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <div>
        <h2 class="card-title mb-0">{{ $title }}</h2>
        @if(!empty($report['description']))<div class="small text-muted">{{ $report['description'] }}</div>@endif
    </div>
    <div class="d-flex gap-1">
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-light"><i class="fas fa-th-large"></i> All Reports</a>
        <a href="{{ url()->current() . '?' . http_build_query($query + ['export' => 'excel']) }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Excel</a>
        <a href="{{ url()->current() . '?' . http_build_query($query + ['export' => 'pdf']) }}" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
    </div>
</header>

@include('reports._tabs', ['active' => $reportKey])
