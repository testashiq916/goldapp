<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Item Help</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; padding: 16px; background: #eef2f7; color: #1b2c3d; }
        .wrap { max-width: 1000px; margin: 0 auto; background: #fff; border: 1px solid #d3ddeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 12px; font-size: 24px; color: #173e67; }
        .top { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; }
        input[type=text] { width: 360px; border: 1px solid #bfd0e4; border-radius: 6px; height: 32px; padding: 0 8px; font-size: 12px; }
        button { border: 1px solid #2a679f; border-radius: 7px; background: #eaf3ff; color: #1b4f7d; font-weight: 700; font-size: 12px; height: 34px; padding: 0 12px; cursor: pointer; }
        .warn { border-color: #ad3b3b; background: #fdeeee; color: #902424; }
        .status { padding: 9px 11px; border-radius: 8px; margin-bottom: 10px; font-size: 13px; border: 1px solid #f0b2b2; background: #fdeaea; color: #8e2020; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 68vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e4ebf5; padding: 7px; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2b4f73; }
        tbody tr { cursor: pointer; }
        tbody tr:hover { background: #f5faff; }
        tbody tr.selected { background: #e8f2ff; }
    </style>
</head>
<body onkeyup="handleKeys(event)">
<div class="wrap">
    <h1>Select Item</h1>

    @if($tableMissing)
        <div class="status">`items` table not found.</div>
    @endif

    <form class="top" method="GET" action="{{ url('/item-help') }}">
        <input type="text" id="search" name="search" value="{{ $search }}" placeholder="Search by code or name..." autofocus>
        <button type="submit">Search</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th style="width: 120px;">Code</th>
                <th>Name</th>
                <th>Malayalam Name</th>
            </tr>
            </thead>
            <tbody id="rows">
            @foreach($items as $it)
                <tr onclick="selectRow('{{ addslashes((string)$it->code) }}', this)" ondblclick="confirmSelection()">
                    <td>{{ $it->code }}</td>
                    <td>{{ $it->name }}</td>
                    <td>{{ $it->mname }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div style="margin-top:10px; display:flex; gap:8px;">
        <button type="button" onclick="confirmSelection()">OK</button>
        <button type="button" class="warn" onclick="window.close()">Exit</button>
    </div>
</div>

<script>
let selectedCode = '';

function selectRow(code, tr) {
    selectedCode = code || '';
    document.querySelectorAll('#rows tr').forEach(r => r.classList.remove('selected'));
    tr.classList.add('selected');
}

function confirmSelection() {
    if (!selectedCode) return;
    if (window.opener && !window.opener.closed && typeof window.opener.receiveItemCode === 'function') {
        window.opener.receiveItemCode(selectedCode);
    }
    window.close();
}

function handleKeys(e) {
    if (e.key === 'Escape') window.close();
}
</script>
</body>
</html>

