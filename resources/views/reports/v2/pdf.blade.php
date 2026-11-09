<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: {{ count($columns) > 10 ? '8px' : '10px' }}; color: #222; }
    h1 { font-size: 16px; color: #1d4ed8; margin: 0 0 4px; }
    .muted { color: #666; margin-bottom: 8px; }
    .filters { margin-bottom: 10px; font-size: 9px; color: #444; }
    .filters span { display: inline-block; margin-right: 12px; }
    .cards { margin-bottom: 10px; }
    .cards td { border: 1px solid #ddd; padding: 4px 6px; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
    table.data th { background: #f3f4f6; }
    td.num, th.num { text-align: right; }
    tfoot td { font-weight: bold; background: #f9fafb; }
    .neg { color: #b91c1c; }
    .notes { margin-top: 8px; font-size: 8px; color: #666; }
</style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="muted">Generated {{ now()->format('d-m-Y H:i') }} by {{ auth()->user()->name ?? '' }}</div>

    @if(!empty($filterSummary))
    <div class="filters">
        @foreach($filterSummary as $label => $value)<span><strong>{{ $label }}:</strong> {{ $value }}</span>@endforeach
    </div>
    @endif

    @if(!empty($sections))
    <table class="cards" style="width:100%"><tr>
        @foreach($sections as $heading => $items)
        <td style="vertical-align:top">
            <strong>{{ $heading }}</strong>
            @foreach($items as $label => $value)<div><span style="color:#666">{{ $label }}:</span> {{ $value ?: '—' }}</div>@endforeach
        </td>
        @endforeach
    </tr></table>
    @endif

    @if(!empty($cards))
    <table class="cards"><tr>
        @foreach($cards as $card)
            <td><div style="color:#666;font-size:8px">{{ $card['label'] }}</div>
                <strong>@if(($card['type'] ?? 'money') === 'money')¥{{ number_format($card['value']) }}@elseif(($card['type'] ?? '') === 'pct'){{ number_format($card['value'], 1) }}%@else{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}@endif</strong></td>
        @endforeach
    </tr></table>
    @endif

    @php
        $fmt = function ($v, $col) {
            $type = $col['type'] ?? 'text';
            if ($v === null || $v === '') return '—';
            return match ($type) {
                'money' => '¥' . number_format((float) $v),
                'int'   => number_format((float) $v),
                'pct'   => number_format((float) $v, 1) . '%',
                'bool'  => $v ? 'Yes' : 'No',
                'date'  => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v,
                'badge' => is_array($v) ? $v['text'] : $v,
                default => is_array($v) ? ($v['text'] ?? '') : $v,
            };
        };
        $isNum = fn ($col) => in_array($col['type'] ?? 'text', ['money', 'int', 'pct']);
    @endphp

    <table class="data">
        <thead><tr>@foreach($columns as $col)<th class="{{ $isNum($col) ? 'num' : '' }}">{{ $col['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                @foreach($columns as $col)
                    @php $v = $row[$col['key']] ?? null; @endphp
                    <td class="{{ $isNum($col) ? 'num' : '' }} {{ $isNum($col) && is_numeric($v) && $v < 0 ? 'neg' : '' }}">{{ $fmt($v, $col) }}</td>
                @endforeach
            </tr>
            @empty
            <tr><td colspan="{{ count($columns) }}" style="text-align:center;color:#888;">{{ $empty }}</td></tr>
            @endforelse
        </tbody>
        @if($hasTotals)
        <tfoot><tr>
            @foreach($columns as $i => $col)
                <td class="{{ $isNum($col) ? 'num' : '' }}">@if(array_key_exists($col['key'], $totals)){{ $fmt($totals[$col['key']], $col) }}@elseif($i === 0) Total @endif</td>
            @endforeach
        </tr></tfoot>
        @endif
    </table>

    @if(!empty($notes))
    <div class="notes">@foreach($notes as $n)<div>• {{ $n }}</div>@endforeach</div>
    @endif
</body>
</html>
