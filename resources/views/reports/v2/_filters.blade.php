@if(!empty($filters))
<form method="GET" class="report-filters row g-2 align-items-end mb-3">
    @foreach($filters as $f)
        @switch($f['type'])
            @case('date_range')
                <div class="col-sm-6 col-md-4 col-xl-3">
                    <label class="form-label small mb-1">{{ $f['label'] }}</label>
                    <div class="input-group input-group-sm">
                        <input type="date" class="form-control" name="{{ $f['from'] }}" value="{{ request($f['from'], $f['default_from'] ?? null) }}">
                        <span class="input-group-text">to</span>
                        <input type="date" class="form-control" name="{{ $f['to'] }}" value="{{ request($f['to'], $f['default_to'] ?? null) }}">
                    </div>
                </div>
                @break
            @case('date')
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">{{ $f['label'] }}</label>
                    <input type="date" class="form-control form-control-sm" name="{{ $f['name'] }}" value="{{ request($f['name'], $f['default'] ?? null) }}">
                </div>
                @break
            @case('number')
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">{{ $f['label'] }}</label>
                    <input type="number" min="0" class="form-control form-control-sm" name="{{ $f['name'] }}" value="{{ request($f['name'], $f['default'] ?? null) }}">
                </div>
                @break
            @case('text')
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">{{ $f['label'] }}</label>
                    <input type="text" class="form-control form-control-sm" name="{{ $f['name'] }}" value="{{ request($f['name']) }}">
                </div>
                @break
            @case('select')
                @php $current = (string) request($f['name'], $f['default'] ?? ''); @endphp
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">{{ $f['label'] }}</label>
                    <select name="{{ $f['name'] }}" class="form-select form-select-sm">
                        @if(($f['all'] ?? 'All') !== '')
                            <option value="">{{ $f['all'] ?? 'All' }}</option>
                        @endif
                        @foreach($f['options'] as $val => $label)
                            <option value="{{ $val }}" {{ $current === (string) $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @break
        @endswitch
    @endforeach
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Apply</button>
        <a href="{{ url()->current() }}" class="btn btn-sm btn-light">Reset</a>
    </div>
</form>
@endif
