<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 16px; color: #1d4ed8; margin-bottom: 4px; }
    .muted { color: #666; margin-bottom: 15px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
    th { background: #f3f4f6; }
    td.num { text-align: right; }
</style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="muted">Generated {{ now()->format('d-m-Y H:i') }}</div>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
            <tr>
                @foreach (array_values($row) as $cell)
                    <td class="{{ is_numeric($cell) ? 'num' : '' }}">{{ is_numeric($cell) ? number_format($cell) : ($cell ?? '—') }}</td>
                @endforeach
            </tr>
            @empty
            <tr><td colspan="{{ count($headings) }}" style="text-align:center;color:#888;">No data available.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>