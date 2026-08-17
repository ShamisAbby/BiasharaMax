<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin-bottom: 0; }
        p.meta { color: #6b7280; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background-color: #f9fafb; font-weight: 600; }
        .summary { margin-top: 16px; }
        .summary span { display: inline-block; margin-right: 24px; font-weight: 600; }
    </style>
</head>
<body>
    <h1>BiasharaMax — {{ $title }}</h1>
    <p class="meta">Generated {{ $generatedAt }}</p>

    @if ($summary)
        <div class="summary">
            @foreach ($summary as $label => $value)
                <span>{{ ucwords(str_replace('_', ' ', $label)) }}: {{ is_numeric($value) ? number_format($value, 2) : $value }}</span>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ is_numeric($cell) ? number_format((float) $cell, 2) : $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">No data for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
