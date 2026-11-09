@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Reports</h2>
                <div class="small text-muted">Every report exports to Excel and PDF with the filters you apply.</div>
            </header>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($categories as $catKey => $cat)
                    <div class="col-md-6 col-xl-4" id="cat-{{ $catKey }}">
                        <div class="report-hub-card h-100">
                            <div class="report-hub-head">
                                <span class="report-hub-icon"><i class="fas {{ $cat['icon'] }}"></i></span>
                                <div>
                                    <div class="fw-semibold">{{ $cat['label'] }}</div>
                                    <div class="small text-muted">{{ count($cat['reports']) }} {{ \Illuminate\Support\Str::plural('report', count($cat['reports'])) }}</div>
                                </div>
                            </div>
                            <ul class="list-unstyled mb-0">
                                @foreach($cat['reports'] as $key => $r)
                                <li>
                                    <a href="{{ route($r['route']) }}" class="report-hub-link">
                                        <span class="d-block fw-semibold">{{ $r['label'] }}</span>
                                        <span class="d-block small text-muted">{{ $r['description'] }}</span>
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</div>
<style>
    .report-hub-card { border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
    .report-hub-head { display: flex; gap: 10px; align-items: center; padding: 12px 14px; border-bottom: 1px solid #f0f0f0; }
    .report-hub-icon { width: 34px; height: 34px; border-radius: 8px; background: #eef2ff; color: #4338ca; display: inline-flex; align-items: center; justify-content: center; }
    .report-hub-link { display: block; padding: 8px 14px; color: inherit; text-decoration: none; border-bottom: 1px solid #f7f7f7; }
    .report-hub-link:hover { background: #f9fafb; }
    .report-hub-link .fw-semibold { color: #1d4ed8; font-size: 13px; }
</style>
@endsection
