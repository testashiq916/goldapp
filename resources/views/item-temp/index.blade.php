<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Temporary Item Details</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; padding: 16px; background: #eef2f7; color: #1b2c3d; }
        .wrap { max-width: 1260px; margin: 0 auto; background: #fff; border: 1px solid #d3ddeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 12px; font-size: 24px; color: #173e67; }
        .status { padding: 9px 11px; border-radius: 8px; margin-bottom: 10px; font-size: 13px; }
        .status.ok { border: 1px solid #9fd3af; background: #e8f8ed; color: #12532a; }
        .status.err { border: 1px solid #f0b2b2; background: #fdeaea; color: #8e2020; }
        .toolbar { margin: 10px 0 12px; display: flex; gap: 8px; flex-wrap: wrap; }
        button { border: 1px solid #2a679f; border-radius: 7px; background: #eaf3ff; color: #1b4f7d; font-weight: 700; font-size: 12px; height: 34px; padding: 0 12px; cursor: pointer; }
        button.primary { border-color: #2f7d47; background: #e6f8ec; color: #18552f; }
        button.warn { border-color: #ad3b3b; background: #fdeeee; color: #902424; padding: 0 10px; }
        button.help { border-color: #5a3aa0; background: #efe9ff; color: #452980; width: 28px; padding: 0; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 70vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e4ebf5; border-right: 1px solid #e4ebf5; padding: 6px; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2b4f73; }
        input[type=text], select { width: 100%; border: 1px solid #bfd0e4; border-radius: 6px; height: 32px; padding: 0 8px; font-size: 12px; }
        .in-group { display: flex; gap: 6px; align-items: center; }
        .mal { font-family: "ML-Karthika", Arial, sans-serif; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Temporary Item Details</h1>

    @if($tableMissing)
        <div class="status err">`itemstmp` table not found.</div>
    @endif
    @if(session('success'))
        <div class="status ok">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="status err">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ url('/item-temp/save') }}" id="mainForm">
        @csrf
        <div class="toolbar">
            <button type="button" onclick="addRow()">Add</button>
            <button type="submit" class="primary">Save</button>
            <button type="button" onclick="window.location='{{ url('/item-temp') }}'">Cancel</button>
            <button type="button" onclick="closeModule()">Exit</button>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th style="width: 190px;">Code</th>
                    <th style="width: 240px;">Name</th>
                    <th>Malayalam Name</th>
                    <th style="width: 190px;">Quality</th>
                    <th style="width: 70px;">Action</th>
                </tr>
                </thead>
                <tbody id="tableBody">
                @foreach($records as $i => $r)
                    <tr data-row-id="{{ $i }}" data-original-code="{{ $r->code }}" data-original-name="{{ $r->name }}">
                        <td>
                            <input type="hidden" name="original_code[]" value="{{ $r->code }}">
                            <input type="hidden" name="original_name[]" value="{{ $r->name }}">
                            <div class="in-group">
                                <input type="text" name="code[]" value="{{ $r->code }}" class="code-input" onkeydown="keyNav(event,this)" onchange="onCodeChange(this)">
                                <button type="button" class="help" onclick="showItemHelp(this)">?</button>
                            </div>
                        </td>
                        <td><input type="text" name="name[]" value="{{ $r->name }}" onkeydown="keyNav(event,this)" onchange="checkDuplicate(this)"></td>
                        <td>
                            <div class="in-group">
                                <input type="text" name="mname[]" value="{{ $r->mname }}" class="mal" onkeydown="keyNav(event,this)">
                                <button type="button" class="help" onclick="showRegionalHelp(this)">?</button>
                            </div>
                        </td>
                        <td>
                            <select name="iqtype[]" onkeydown="keyNav(event,this)">
                                <option value="">Select Quality</option>
                                @foreach($qtypes as $qt)
                                    <option value="{{ $qt }}" {{ (string)$r->iqtype === (string)$qt ? 'selected' : '' }}>{{ $qt }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><button type="button" class="warn" onclick="deleteRow(this, '{{ addslashes((string)$r->code) }}', '{{ addslashes((string)$r->name) }}')">x</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </form>

    <form method="POST" action="{{ url('/item-temp/delete') }}" id="deleteForm" style="display:none;">
        @csrf
        <input type="hidden" name="delete_code" id="delete_code">
        <input type="hidden" name="delete_name" id="delete_name">
    </form>
</div>

<script>
let rowCounter = {{ count($records) }};
const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function keyNav(event, el) {
    if (event.key === 'F9') {
        event.preventDefault();
        document.getElementById('mainForm').submit();
        return;
    }
    if (event.key === 'F1') {
        event.preventDefault();
        if (el.classList.contains('code-input')) {
            showItemHelp(el.nextElementSibling);
            return;
        }
        if (el.name === 'mname[]') {
            showRegionalHelp(el.nextElementSibling);
            return;
        }
    }
    if (event.key !== 'Enter') return;
    event.preventDefault();
    const inputs = Array.from(document.querySelectorAll('input[name="code[]"],input[name="name[]"],input[name="mname[]"],select[name="iqtype[]"]'));
    const i = inputs.indexOf(el);
    if (i >= 0 && i < inputs.length - 1) inputs[i + 1].focus(); else addRow();
}

function closeModule() {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
    } else {
        window.close();
    }
}

function addRow() {
    const tbody = document.getElementById('tableBody');
    const tr = document.createElement('tr');
    tr.dataset.rowId = String(rowCounter++);
    tr.dataset.isNew = '1';
    tr.innerHTML = `
        <td>
            <input type="hidden" name="original_code[]" value="">
            <input type="hidden" name="original_name[]" value="">
            <div class="in-group">
                <input type="text" name="code[]" value="" class="code-input" onkeydown="keyNav(event,this)" onchange="onCodeChange(this)">
                <button type="button" class="help" onclick="showItemHelp(this)">?</button>
            </div>
        </td>
        <td><input type="text" name="name[]" value="" onkeydown="keyNav(event,this)" onchange="checkDuplicate(this)"></td>
        <td>
            <div class="in-group">
                <input type="text" name="mname[]" value="" class="mal" onkeydown="keyNav(event,this)">
                <button type="button" class="help" onclick="showRegionalHelp(this)">?</button>
            </div>
        </td>
        <td>
            <select name="iqtype[]" onkeydown="keyNav(event,this)">
                <option value="">Select Quality</option>
                @foreach($qtypes as $qt)
                <option value="{{ $qt }}">{{ $qt }}</option>
                @endforeach
            </select>
        </td>
        <td><button type="button" class="warn" onclick="deleteRow(this)">x</button></td>
    `;
    tbody.appendChild(tr);
    tr.querySelector('input[name="code[]"]').focus();
}

async function apiPost(url, payload) {
    const resp = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload || {})
    });
    return await resp.json();
}

async function onCodeChange(input) {
    const code = (input.value || '').trim();
    if (!code) return;
    const row = input.closest('tr');
    const data = await apiPost(@json(url('/item-temp/item-details')), { code });
    const nameInput = row.querySelector('input[name="name[]"]');
    const mnameInput = row.querySelector('input[name="mname[]"]');
    if (data.name) {
        nameInput.value = data.name;
        mnameInput.value = data.mname || '';
    }
    checkDuplicate(nameInput);
}

async function checkDuplicate(input) {
    const row = input.closest('tr');
    const code = (row.querySelector('input[name="code[]"]').value || '').trim();
    const name = (row.querySelector('input[name="name[]"]').value || '').trim();
    if (!code || !name) return;

    const oc = row.dataset.originalCode || '';
    const on = row.dataset.originalName || '';
    if (code.toUpperCase() === oc.toUpperCase() && name === on) return;

    const data = await apiPost(@json(url('/item-temp/check-duplicate')), { code, name });
    if (data.is_duplicate) {
        alert('Duplicate entry - this combination already exists');
        input.value = '';
        input.focus();
    }
}

async function deleteRow(btn, code = '', name = '') {
    const row = btn.closest('tr');
    if (!code || !name || row.dataset.isNew === '1') {
        row.remove();
        return;
    }

    const check = await apiPost(@json(url('/item-temp/check-delete')), { code });
    if (check.in_use) {
        alert('Cannot delete - this item is used in ' + (check.count || 0) + ' order(s).');
        return;
    }
    if (!confirm('Delete this entry?')) return;
    document.getElementById('delete_code').value = code;
    document.getElementById('delete_name').value = name;
    document.getElementById('deleteForm').submit();
}

function showItemHelp(button) {
    const codeInput = button.previousElementSibling;
    const current = encodeURIComponent(codeInput.value || '');
    const w = window.open(@json(url('/item-help')) + '?search=' + current, 'ItemHelp', 'width=900,height=620,scrollbars=yes');
    window.receiveItemCode = function(code) {
        if (!code) return;
        codeInput.value = code;
        onCodeChange(codeInput);
    };
    return w;
}

function showRegionalHelp(button) {
    const mInput = button.previousElementSibling;
    const current = encodeURIComponent(mInput.value || '');
    const w = window.open(@json(url('/regional-help')) + '?text=' + current, 'RegionalHelp', 'width=700,height=500');
    window.receiveRegionalText = function(text) {
        mInput.value = text || '';
    };
    return w;
}
</script>
</body>
</html>

