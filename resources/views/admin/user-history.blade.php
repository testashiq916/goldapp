<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login History: {{ $user->code }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 980px; margin: 18px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 4px; font-size: 24px; color: #1f3f66; }
        .sub { font-size: 13px; color: #64748b; margin-bottom: 12px; }
        .toolbar { display: flex; gap: 8px; margin-bottom: 12px; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        .table-wrap { max-height: 72vh; overflow: auto; border: 1px solid #d8e2ef; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 7px 10px; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2d4f74; z-index: 2; }
        tr:hover { background: #f0f6ff; }
        .empty { text-align: center; padding: 30px; color: #94a3b8; }
    </style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="wrap">
    <h1>Login History</h1>
    <div class="sub">User: <strong>{{ $user->code }}</strong> - {{ $user->name }}</div>

    <div class="toolbar">
        <button type="button" onclick="window.location.href='{{ url('/admin/users') }}'">Back to Users</button>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Login Time</th>
                <th>Logout Time</th>
                <th>IP Address</th>
                <th>Browser / Device</th>
            </tr>
            </thead>
            <tbody>
            @forelse($history as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ isset($row->tdate) ? date('d/m/Y', strtotime($row->tdate)) : '-' }}</td>
                    <td>{{ $row->ttime ?? '-' }}</td>
                    <td>{{ $row->ltime ?? '-' }}</td>
                    <td>{{ $row->ip ?? '-' }}</td>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $row->useragent ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No login history found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
