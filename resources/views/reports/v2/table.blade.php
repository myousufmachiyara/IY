@extends('layouts.app')

@section('title', 'Reports | ' . $title)

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @include('reports.v2._header')

            <div class="card-body">
                @include('reports.v2._filters')

                @if(!empty($cards))
                <div class="row g-2 mb-3">
                    @foreach($cards as $card)
                    <div class="col-6 col-md">
                        <div class="report-stat {{ !empty($card['variant']) ? 'report-stat-' . $card['variant'] : '' }}">
                            <div class="report-stat-label">{{ $card['label'] }}</div>
                            <div class="report-stat-value">
                                @if(($card['type'] ?? 'money') === 'money')¥{{ number_format($card['value']) }}
                                @elseif(($card['type'] ?? '') === 'pct'){{ number_format($card['value'], 1) }}%
                                @else{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}@endif
                            </div>
                            @if(!empty($card['hint']))<div class="report-stat-hint">{{ $card['hint'] }}</div>@endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                @if($chart && $rows->isNotEmpty())
                <div class="mb-3" style="height:260px"><canvas id="reportChart"></canvas></div>
                @endif

                <div class="table-scroll">
                    <table class="table table-bordered table-striped table-sm mb-0 report-table">
                        <thead>
                            <tr>
                                @foreach($columns as $col)
                                    <th class="{{ in_array($col['type'] ?? 'text', ['money','int','pct']) ? 'text-end' : '' }} {{ ($col['type'] ?? '') === 'bool' ? 'text-center' : '' }}">{{ $col['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                            <tr class="{{ $row['_class'] ?? '' }}">
                                @foreach($columns as $col)
                                    <td class="{{ in_array($col['type'] ?? 'text', ['money','int','pct']) ? 'text-end text-nowrap' : '' }} {{ ($col['type'] ?? '') === 'bool' ? 'text-center' : '' }} {{ $col['class'] ?? '' }}">@include('reports.v2._cell', ['col' => $col, 'row' => $row])</td>
                                @endforeach
                            </tr>
                            @empty
                            <tr><td colspan="{{ count($columns) }}" class="text-center text-muted py-4">{{ $empty }}</td></tr>
                            @endforelse
                        </tbody>
                        @if($hasTotals)
                        <tfoot>
                            <tr class="fw-bold">
                                @foreach($columns as $i => $col)
                                    <td class="{{ in_array($col['type'] ?? 'text', ['money','int','pct']) ? 'text-end text-nowrap' : '' }}">
                                        @if($i === 0 && !array_key_exists($col['key'], $totals)) Total
                                        @elseif(array_key_exists($col['key'], $totals))
                                            @include('reports.v2._cell', ['col' => array_diff_key($col, ['link' => 1]), 'row' => [$col['key'] => $totals[$col['key']]]])
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>

                @if(!empty($notes))
                <div class="mt-3 small text-muted">
                    @foreach($notes as $note)<div><i class="fas fa-info-circle me-1"></i>{{ $note }}</div>@endforeach
                </div>
                @endif
            </div>
        </section>
    </div>
</div>

@include('reports.v2._styles')

@if($chart && $rows->isNotEmpty())
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    const chart = @json($chart);
    const palette = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed'];
    new Chart(document.getElementById('reportChart'), {
        type: chart.type || 'bar',
        data: {
            labels: chart.labels,
            datasets: chart.series.map((s, i) => ({ label: s.label, data: s.data, backgroundColor: palette[i % palette.length], borderColor: palette[i % palette.length], tension: .3 }))
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
                   scales: { y: { ticks: { callback: v => (chart.money === false ? '' : '¥') + Number(v).toLocaleString() } } } }
    });
})();
</script>
@endif
@endsection
