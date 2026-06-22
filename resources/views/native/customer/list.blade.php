<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $typeInfo['plural'] }}</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:16px}
        .card{max-width:1200px;margin:0 auto;background:#fff;border:1px solid #d1d5db}
        .head{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:#111827;color:#fff}
        .row{display:flex;gap:8px;align-items:center;padding:12px 16px;border-bottom:1px solid #e5e7eb}
        .row input{padding:6px 8px;border:1px solid #cbd5e1}
        .btn{padding:7px 10px;border:1px solid #6b7280;background:#f8fafc;color:#111827;text-decoration:none;cursor:pointer}
        .import-box{margin:12px 16px;padding:12px;border:1px solid #d1d5db;background:#f8fafc}
        .hint{font-size:12px;color:#6b7280}
        table{width:100%;border-collapse:collapse}
        th,td{padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:13px}
        th{background:#f8fafc}
        .msg{margin:12px 16px;padding:10px;border:1px solid #d1d5db}
        .ok{background:#ecfdf5;border-color:#86efac}.err{background:#fef2f2;border-color:#fca5a5}
    </style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="card">
    <div class="head">
        <div>{{ $typeInfo['plural'] }} List</div>
        <div>
            <a class="btn" href="{{ url('/customer/add') }}?type={{ urlencode($type) }}">Add New</a>
            @if(in_array($type, ['C', 'S'], true))
                <form method="post" action="{{ url('/customer/sync-list') }}" style="display:inline-block;margin:0 4px 0 0">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="noremoved" value="{{ $noRemoved ? '1' : '0' }}">
                    <button class="btn" type="submit" onclick="return confirm('Sync all filtered {{ strtolower($typeInfo['plural']) }} to secondary database?')">Sync All</button>
                </form>
            @endif
            <a class="btn" href="{{ url('/dashboard') }}">Dashboard</a>
        </div>
    </div>

    @if($message !== '')
        <div class="msg {{ $messageType === 'error' ? 'err' : 'ok' }}">{{ $message }}</div>
    @endif

    <form class="row" method="get" action="{{ url('/customer') }}">
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="list" value="1">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search code/name/mobile">
        <label><input type="checkbox" name="noremoved" value="1" {{ $noRemoved ? 'checked' : '' }}> No Removed</label>
        <button class="btn" type="submit">Search</button>
    </form>

    @if(in_array($type, ['C', 'S', 'G'], true))
        <form class="row import-box" method="post" action="{{ url('/customer/import') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <strong>Import {{ $typeInfo['plural'] }}</strong>
            <input type="file" name="csv_file" accept=".csv,.txt" required>
            <button class="btn" type="submit">Upload CSV</button>
            <span class="hint">Supported headers: `CODE`, `NAME`, `ADDRESS 1`, `ADDRESS 2`, `ADDRESS 3`, `MOBILE NUMBER`, `OPENING BALANCE`, `CUS CREATION DATE`.</span>
        </form>
    @endif

    @php $count = count($customers); @endphp
    @if($count > 0)
        <div style="padding:6px 16px;font-size:12px;color:#6b7280;border-bottom:1px solid #e5e7eb">
            Showing {{ $count }} record(s){{ $count >= 300 ? ' — showing first 300, use search to narrow results' : '' }}
        </div>
    @endif
    <table>
        <thead>
        <tr>
            <th>Code</th><th>Name</th><th>Mobile</th><th>Phone</th><th>City</th><th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse($customers as $c)
            <tr>
                <td>{{ $c['code'] ?? '' }}</td>
                <td>{{ $c['name'] ?? '' }}</td>
                <td>{{ $c['mobile'] ?? '' }}</td>
                <td>{{ $c['telephone'] ?? '' }}</td>
                <td>{{ $c['city'] ?? '' }}</td>
                <td>
                    <a class="btn" href="{{ url('/customer/edit') }}?type={{ urlencode($type) }}&code={{ urlencode($c['code'] ?? '') }}">Edit</a>
                    <a class="btn" href="{{ url('/customer/delete') }}?type={{ urlencode($type) }}&code={{ urlencode($c['code'] ?? '') }}" onclick="return confirm('Delete this record?')">Delete</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6">No records found</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
