<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rate History</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 980px; margin: 18px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .toolbar { display: flex; gap: 8px; margin-bottom: 12px; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        .table-wrap { max-height: 72vh; overflow: auto; border: 1px solid #d8e2ef; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 7px 8px; text-align: right; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2d4f74; z-index: 2; text-align: center; }
        td:first-child, th:first-child { text-align: left; }
        tr:hover { background: #f0f6ff; }
        .empty { text-align: center; padding: 30px; color: #94a3b8; font-size: 14px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Rate History (Last 30 Days)</h1>

    <div class="toolbar">
        <button type="button" onclick="window.location.href='{{ url('/rate/update') }}'">Back to Rates</button>
        <button type="button" onclick="window.location.href='{{ url('/dashboard') }}'">Exit</button>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Gold</th>
                <th>Silver</th>
                <th>3rd Rate</th>
                <th>Platinum</th>
                <th>Bullion</th>
                <th>Touch</th>
            </tr>
            </thead>
            <tbody>
            @forelse($history as $row)
                <tr>
                    <td>{{ date('d/m/Y', strtotime($row->tdate)) }}</td>
                    <td style="text-align:center">{{ $row->ttime ?? '' }}</td>
                    <td>{{ number_format($row->grate ?? 0, 2) }}</td>
                    <td>{{ number_format($row->srate ?? 0, 2) }}</td>
                    <td>{{ number_format($row->thrate ?? 0, 3) }}</td>
                    <td>{{ number_format($row->prate ?? 0, 2) }}</td>
                    <td>{{ number_format($row->bulrate ?? 0, 2) }}</td>
                    <td>{{ number_format($row->bultouch ?? 0, 3) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No rate history found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
