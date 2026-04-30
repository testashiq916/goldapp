<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Item Sub Groups</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #eef2f7; color: #1b2c3d; }
        .wrap { max-width: 1100px; margin: 16px auto; background: #fff; border: 1px solid #d3ddeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 12px; font-size: 24px; color: #173e67; }
        .status { padding: 9px 11px; border-radius: 8px; margin-bottom: 10px; font-size: 13px; }
        .status.ok { border: 1px solid #9fd3af; background: #e8f8ed; color: #12532a; }
        .status.err { border: 1px solid #f0b2b2; background: #fdeaea; color: #8e2020; }
        .list-wrap { border: 1px solid #d8e2ef; border-radius: 8px; max-height: 64vh; overflow: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e4ebf5; padding: 7px; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2b4f73; }
        input[type=text] { border: 1px solid #bfd0e4; border-radius: 6px; height: 32px; padding: 0 8px; font-size: 12px; width: 100%; }
        button { border: 1px solid #2a679f; border-radius: 7px; background: #eaf3ff; color: #1b4f7d; font-weight: 700; font-size: 12px; height: 34px; padding: 0 12px; cursor: pointer; }
        button.primary { border-color: #2f7d47; background: #e6f8ec; color: #18552f; }
        button.warn { border-color: #ad3b3b; background: #fdeeee; color: #902424; }
        .toolbar { margin: 12px 0; display: flex; gap: 8px; flex-wrap: wrap; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Item Sub Groups</h1>

    @if($tableMissing)
        <div class="status err">`itemsubgrp` table not found. Please create this table before using Sub Groups module.</div>
    @endif
    @if(session('success'))
        <div class="status ok">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="status err">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ url('/sub-groups/save') }}" id="saveForm">
        @csrf
        <div class="toolbar">
            <button type="button" onclick="addRow()">Add</button>
            <button type="submit" class="primary">Save</button>
            <button type="button" onclick="closeModule()">Exit</button>
        </div>

        <div class="list-wrap">
            <table>
                <thead>
                <tr>
                    <th style="width: 180px;">Code</th>
                    <th>Name</th>
                    <th style="width: 120px;">Action</th>
                </tr>
                </thead>
                <tbody id="tbody">
                @php $row = 0; @endphp
                @foreach($subgroups as $sg)
                    <tr>
                        <td><input type="text" name="code[{{ $row }}]" value="{{ $sg->code }}" readonly></td>
                        <td><input type="text" name="name[{{ $row }}]" value="{{ $sg->name }}"></td>
                        <td><button type="button" class="warn" onclick="deleteEntry('{{ addslashes((string) $sg->code) }}')">Delete</button></td>
                    </tr>
                    @php $row++; @endphp
                @endforeach
                </tbody>
            </table>
        </div>
    </form>

    <form method="POST" action="{{ url('/sub-groups/delete') }}" id="deleteForm" style="display:none;">
        @csrf
        <input type="hidden" name="code" id="deleteCode">
    </form>
</div>

<script>
let rowIndex = {{ (int) $row }};

function addRow() {
    const tbody = document.getElementById('tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="code[${rowIndex}]" value=""></td>
        <td><input type="text" name="name[${rowIndex}]" value=""></td>
        <td><button type="button" class="warn" onclick="this.closest('tr').remove()">Remove</button></td>
    `;
    tbody.appendChild(tr);
    rowIndex++;
}

function deleteEntry(code) {
    if (!confirm('Delete this entry?')) {
        return;
    }
    document.getElementById('deleteCode').value = code;
    document.getElementById('deleteForm').submit();
}

function closeModule() {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
    } else {
        window.close();
    }
}
</script>
</body>
</html>

