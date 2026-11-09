@extends('layouts.app')

@section('title', 'Reports | ' . $title)

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @include('reports.v2._header')

            <div class="card-body">
                @include('reports.v2._filters')

                @if(!empty($sections))
                <div class="row g-3 mb-3">
                    @foreach($sections as $heading => $items)
                    <div class="col-md-4">
                        <div class="report-stat">
                            <div class="fw-semibold mb-2">{{ $heading }}</div>
                            <table class="table table-sm mb-0">
                                @foreach($items as $label => $value)
                                <tr><td class="text-muted small border-0 py-1">{{ $label }}</td><td class="text-end border-0 py-1">{{ $value ?: '—' }}</td></tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                    @endforeach
                </div>

                <h5 class="mb-2">History</h5>
                @endif

                <div class="table-scroll">
                    <table class="table table-bordered table-striped table-sm mb-0 report-table">
                        <thead><tr>
                            @foreach($columns as $col)
                                <th class="{{ in_array($col['type'] ?? 'text', ['money','int','pct']) ? 'text-end' : '' }}">{{ $col['label'] }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                            @forelse($rows as $row)
                            <tr>
                                @foreach($columns as $col)
                                    <td class="{{ in_array($col['type'] ?? 'text', ['money','int','pct']) ? 'text-end text-nowrap' : '' }} {{ $col['class'] ?? '' }}">@include('reports.v2._cell', ['col' => $col, 'row' => $row])</td>
                                @endforeach
                            </tr>
                            @empty
                            <tr><td colspan="{{ count($columns) }}" class="text-center text-muted py-4">{{ $empty }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(!empty($notes))
                <div class="mt-3 small text-muted">@foreach($notes as $note)<div><i class="fas fa-info-circle me-1"></i>{{ $note }}</div>@endforeach</div>
                @endif
            </div>
        </section>
    </div>
</div>
@include('reports.v2._styles')
@endsection
