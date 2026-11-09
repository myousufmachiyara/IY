@php
    $type = $col['type'] ?? 'text';
    $v = $row[$col['key']] ?? null;
    $url = isset($col['link']) ? ($row[$col['link']] ?? null) : null;
    $neg = in_array($type, ['money', 'int', 'pct']) && is_numeric($v) && $v < 0;
@endphp
@switch($type)
    @case('money')
        @php $out = $v === null ? '—' : '¥' . number_format((float) $v); @endphp
        @break
    @case('int')
        @php $out = $v === null ? '—' : number_format((float) $v); @endphp
        @break
    @case('pct')
        @php $out = $v === null ? '—' : number_format((float) $v, 1) . '%'; @endphp
        @break
    @case('date')
        @php $out = $v ? ($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v) : '—'; @endphp
        @break
    @case('bool')
        @php $out = $v === null ? '—' : ($v ? '✓' : '✗'); @endphp
        @break
    @default
        @php $out = $v; @endphp
@endswitch
@if($type === 'badge' && is_array($v))
    <span class="badge bg-{{ $v['variant'] ?? 'secondary' }}">{{ $v['text'] }}</span>
@elseif($url)
    <a href="{{ $url }}" class="{{ $neg ? 'text-danger' : '' }}">{{ $out === null || $out === '' ? '—' : $out }}</a>
@else
    <span class="{{ $neg ? 'text-danger' : '' }}">{{ $out === null || $out === '' ? '—' : $out }}</span>
@endif
