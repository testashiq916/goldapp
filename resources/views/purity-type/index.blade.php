<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Item Purity Type</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #eef2f7; color: #1b2c3d; }
        .wrap { max-width: 980px; margin: 16px auto; background: #fff; border: 1px solid #d3ddeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 12px; font-size: 24px; color: #173e67; }
        .status { padding: 9px 11px; border-radius: 8px; margin-bottom: 10px; font-size: 13px; }
        .status.ok { border: 1px solid #9fd3af; background: #e8f8ed; color: #12532a; }
        .status.err { border: 1px solid #f0b2b2; background: #fdeaea; color: #8e2020; }
        .toolbar { margin: 12px 0; display: flex; gap: 8px; flex-wrap: wrap; }
        button { border: 1px solid #2a679f; border-radius: 7px; background: #eaf3ff; color: #1b4f7d; font-weight: 700; font-size: 12px; height: 34px; padding: 0 12px; cursor: pointer; }
        button.primary { border-color: #2f7d47; background: #e6f8ec; color: #18552f; }
        button.warn { border-color: #ad3b3b; background: #fdeeee; color: #902424; padding: 0 10px; }
        .list-wrap { border: 1px solid #d8e2ef; border-radius: 8px; max-height: 66vh; overflow: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e4ebf5; padding: 7px; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2b4f73; }
        input[type=text] { border: 1px solid #bfd0e4; border-radius: 6px; height: 32px; padding: 0 8px; font-size: 12px; width: 100%; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Item Purity Type</h1>

    @if($tableMissing)
        <div class="status err">`itemsqtype` table not found. Please create this table before using Purity Type module.</div>
    @endif
    @if(session('success'))
        <div class="status ok">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="status err">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ url('/purity-type/save') }}" id="mainForm">
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
                    <th style="width: 220px;">Purity</th>
                    <th style="width: 220px;">Touch</th>
                    <th style="width: 90px;">Action</th>
                </tr>
                </thead>
                <tbody id="tableBody">
                @foreach($records as $r)
                    <tr data-original-code="{{ $r->code }}">
                        <td>
                            <input type="hidden" name="original_code[]" value="{{ $r->code }}">
                            <input type="text" name="code[]" value="{{ $r->code }}">
                        </td>
                        <td><input type="text" name="touch[]" value="{{ $r->touch }}" oninput="sanitizeTouch(this)"></td>
                        <td><button type="button" class="warn" onclick="deleteRow(this, '{{ addslashes((string) $r->code) }}')">x</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <input type="hidden" name="deleted_rows" id="deletedRows" value="[]">
    </form>
</div>

<script>
let deletedRows = [];
const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function addRow() {
    const tbody = document.getElementById('tableBody');
    const tr = document.createElement('tr');
    tr.dataset.isNew = '1';
    tr.innerHTML = `
        <td>
            <input type="hidden" name="original_code[]" value="">
            <input type="text" name="code[]" value="">
        </td>
        <td><input type="text" name="touch[]" value="" oninput="sanitizeTouch(this)"></td>
        <td><button type="button" class="warn" onclick="deleteRow(this)">x</button></td>
    `;
    tbody.appendChild(tr);
    const input = tr.querySelector('input[name="code[]"]');
    if (input) input.focus();
}

async function canDelete(code) {
    const resp = await fetch(@json(url('/purity-type/check-delete')), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ code: code })
    });
    if (!resp.ok) {
        return { can_delete: false, usage_count: 0 };
    }
    return await resp.json();
}

async function deleteRow(btn, code = '') {
    const row = btn.closest('tr');
    if (!row) return;

    if (code && !row.dataset.isNew) {
        const check = await canDelete(code);
        if (!check.can_delete) {
            alert('Cannot delete - this code is used in ' + (check.usage_count || 0) + ' record(s).');
            return;
        }
        if (!confirm('Delete this entry?')) {
            return;
        }
        deletedRows.push(code);
        document.getElementById('deletedRows').value = JSON.stringify(deletedRows);
        row.remove();
        return;
    }

    row.remove();
}

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const el = document.activeElement;
    if (!el || el.tagName !== 'INPUT') return;
    if (el.name !== 'code[]' && el.name !== 'touch[]') return;
    e.preventDefault();

    const inputs = Array.from(document.querySelectorAll('input[name="code[]"], input[name="touch[]"]'));
    const i = inputs.indexOf(el);
    if (i >= 0 && i < inputs.length - 1) {
        inputs[i + 1].focus();
    } else {
        addRow();
    }
});

function closeModule() {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
    } else {
        window.close();
    }
}

function sanitizeTouch(input) {
    const cleaned = String(input.value || '')
        .replace(',', '.')
        .replace(/[^0-9.]/g, '');
    const firstDot = cleaned.indexOf('.');
    if (firstDot === -1) {
        input.value = cleaned;
        return;
    }
    input.value = cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');
}
</script>
</body>
</html>
