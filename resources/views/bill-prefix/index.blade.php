<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bill Type Details</title>
    <style>
        :root {
            --bg: #f0f1f5;
            --surface: #ffffff;
            --surface-alt: #f7f8fa;
            --border: #dfe1e6;
            --primary: #4a6cf7;
            --primary-hover: #3b5de7;
            --danger: #e5484d;
            --success: #30a46c;
            --warning: #f5a623;
            --text: #1a1d26;
            --text-muted: #6b7280;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --radius: 10px;
            --radius-sm: 6px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 20px;
        }
        .app-container { width: 100%; max-width: 1280px; margin: 0 auto; }
        .warn {
            margin-bottom: 10px;
            padding: 8px 10px;
            border: 1px solid #f2b5b5;
            background: #fbe6e6;
            color: #8a1f1f;
            font-size: 12px;
            font-weight: 700;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            gap: 12px;
            flex-wrap: wrap;
        }
        .header h1 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .toolbar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
        }
        .btn:hover { background: var(--surface-alt); }
        .btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-danger { color: var(--danger); border-color: #f6c8c8; background: #fff5f5; }
        .table-wrapper {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .table-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th {
            background: #f7f8fa;
            border-bottom: 1px solid var(--border);
            padding: 10px 8px;
            text-align: left;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        thead th.group-header { text-align: center; text-transform: none; font-size: 12px; }
        .th-sales { background: #eef2ff; }
        .th-purch { background: #fef3e2; }
        .th-sret { background: #e8faf0; }
        .th-pret { background: #fde8e8; }
        tbody tr { border-bottom: 1px solid #f0f1f5; }
        tbody tr.selected { background: #eef2ff; }
        tbody td { padding: 2px 4px; vertical-align: middle; }
        td input {
            width: 100%;
            border: 1px solid transparent;
            background: transparent;
            padding: 7px;
            font-size: 13px;
            border-radius: 4px;
            outline: none;
        }
        td input:hover { background: var(--surface-alt); }
        td input:focus { border-color: var(--primary); background: #fff; }
        td input[type="number"] { text-align: right; }
        td input.code-input { font-family: Consolas, monospace; text-transform: uppercase; }
        .status-bar {
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-muted);
        }
        .toast {
            position: fixed;
            bottom: 18px;
            right: 18px;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            display: none;
        }
        .toast.show { display: block; }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }
        .toast.warning { background: var(--warning); color: #2d1b00; }
    </style>
</head>
<body>
<div class="app-container">
    @if($salesTypeMissing)
        <div class="warn">`salestype` table not found.</div>
    @endif
    @if($generaliMissing)
        <div class="warn">`generali` table not found. Start numbers cannot be saved.</div>
    @endif
    @if($salesmMissing)
        <div class="warn">`salesm` table not found. Delete usage check is disabled.</div>
    @endif

    <div class="header">
        <h1>Bill Type Details</h1>
        <div class="toolbar">
            <button class="btn" id="btnAdd">Add</button>
            <button class="btn btn-danger" id="btnDelete">Delete</button>
            <button class="btn" id="btnCancel">Cancel</button>
            <button class="btn btn-primary" id="btnSave">Save</button>
            <button class="btn" id="btnExit">Exit</button>
        </div>
    </div>

    <div class="table-wrapper">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th rowspan="2" style="width:48px">#</th>
                    <th rowspan="2">Code</th>
                    <th rowspan="2">Name</th>
                    <th rowspan="2">Tax %</th>
                    <th rowspan="2">Form No</th>
                    <th colspan="2" class="group-header th-sales">Sales</th>
                    <th colspan="2" class="group-header th-purch">Purchase</th>
                    <th colspan="2" class="group-header th-sret">Sales Return</th>
                    <th colspan="2" class="group-header th-pret">Purchase Return</th>
                </tr>
                <tr>
                    <th class="th-sales">Prefix</th>
                    <th class="th-sales">Start No</th>
                    <th class="th-purch">Prefix</th>
                    <th class="th-purch">Start No</th>
                    <th class="th-sret">Prefix</th>
                    <th class="th-sret">Start No</th>
                    <th class="th-pret">Prefix</th>
                    <th class="th-pret">Start No</th>
                </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>
    </div>

    <div class="status-bar">
        <span id="rowCount">0 rows</span>
        <span>Enter = Next Field, F9 = Save</span>
    </div>
</div>
<div class="toast" id="toast"></div>

<script>
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const disabled = @json($salesTypeMissing || $generaliMissing);

    const fields = [
        { key: 'code', type: 'text', cls: 'code-input', maxLength: 10 },
        { key: 'name', type: 'text', cls: '', maxLength: 30 },
        { key: 'taxperc', type: 'number', cls: '' },
        { key: 'formno', type: 'text', cls: 'code-input', maxLength: 20 },
        { key: 'prefix', type: 'text', cls: 'code-input', maxLength: 20 },
        { key: 'startno', type: 'number', cls: '' },
        { key: 'pprefix', type: 'text', cls: 'code-input', maxLength: 20 },
        { key: 'pstartno', type: 'number', cls: '' },
        { key: 'srprefix', type: 'text', cls: 'code-input', maxLength: 20 },
        { key: 'srstartno', type: 'number', cls: '' },
        { key: 'prprefix', type: 'text', cls: 'code-input', maxLength: 20 },
        { key: 'prstartno', type: 'number', cls: '' }
    ];

    let rows = [];
    let selectedRow = -1;

    const btnAdd = document.getElementById('btnAdd');
    const btnDelete = document.getElementById('btnDelete');
    const btnCancel = document.getElementById('btnCancel');
    const btnSave = document.getElementById('btnSave');
    const btnExit = document.getElementById('btnExit');

    if (disabled) {
        btnAdd.disabled = true;
        btnDelete.disabled = true;
        btnSave.disabled = true;
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload || {})
        });
        let json = null;
        try {
            json = await response.json();
        } catch (e) {
            return { success: false, message: 'Server returned invalid response.' };
        }
        if (!response.ok) {
            return { success: false, message: json.message || ('Request failed: ' + response.status) };
        }
        return json;
    }

    async function loadData() {
        const json = await postJson(@json(url('/bill-prefix/retrieve')), {});
        if (!json.success) {
            showToast(json.message || 'Failed to load data.', 'error');
            return;
        }
        rows = json.data || [];
        selectedRow = -1;
        renderTable();
        updateStatus();
    }

    async function saveAll() {
        collectFromDOM();
        const json = await postJson(@json(url('/bill-prefix/save')), { rows: rows });
        if (!json.success) {
            showToast(json.message || 'Save failed.', 'error');
            return;
        }
        showToast(json.message || 'Saved successfully.', 'success');
        loadData();
    }

    async function deleteRow() {
        if (selectedRow < 0 || selectedRow >= rows.length) {
            showToast('Select a row to delete', 'warning');
            return;
        }

        collectFromDOM();
        const code = String(rows[selectedRow].code || '').trim();
        if (code !== '') {
            const json = await postJson(@json(url('/bill-prefix/check-delete')), { code: code });
            if (!json.success) {
                showToast(json.message || 'Delete check failed.', 'error');
                return;
            }
            if (json.in_use) {
                showToast("Can't delete. This type is in use (" + json.count + " records).", 'error');
                return;
            }
        }

        if (!confirm('Delete this row?')) {
            return;
        }
        rows.splice(selectedRow, 1);
        selectedRow = -1;
        renderTable();
        updateStatus();
    }

    function addRow() {
        collectFromDOM();
        if (rows.length > 0) {
            const lastCode = String(rows[rows.length - 1].code || '').trim();
            if (lastCode === '') {
                selectedRow = rows.length - 1;
                renderSelection();
                focusCell(rows.length - 1, 'code');
                showToast('Enter code for current row first.', 'warning');
                return;
            }
        }

        rows.push({
            code: '', name: '', taxperc: 0, formno: '', prefix: '', startno: 0,
            srprefix: '', srstartno: 0, pprefix: '', pstartno: 0, prprefix: '', prstartno: 0
        });
        renderTable();
        updateStatus();
        selectedRow = rows.length - 1;
        renderSelection();
        focusCell(selectedRow, 'code');
    }

    function cancelChanges() {
        loadData();
    }

    function renderTable() {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';

        rows.forEach((row, i) => {
            const tr = document.createElement('tr');
            tr.addEventListener('click', () => {
                selectedRow = i;
                renderSelection();
            });

            const tdNum = document.createElement('td');
            tdNum.style.textAlign = 'center';
            tdNum.style.color = '#6b7280';
            tdNum.textContent = String(i + 1);
            tr.appendChild(tdNum);

            fields.forEach((field) => {
                const td = document.createElement('td');
                const input = document.createElement('input');
                input.type = field.type;
                input.className = field.cls;
                input.dataset.row = String(i);
                input.dataset.field = field.key;
                input.value = row[field.key] ?? (field.type === 'number' ? 0 : '');
                input.step = field.key === 'taxperc' ? '0.01' : '1';
                if (field.maxLength) {
                    input.maxLength = field.maxLength;
                }
                input.addEventListener('focus', () => {
                    selectedRow = i;
                    renderSelection();
                    input.select();
                });
                input.addEventListener('keydown', (e) => handleKeyNav(e, i, field.key));
                td.appendChild(input);
                tr.appendChild(td);
            });

            tbody.appendChild(tr);
        });

        renderSelection();
    }

    function renderSelection() {
        document.querySelectorAll('#tableBody tr').forEach((tr, i) => {
            tr.classList.toggle('selected', i === selectedRow);
        });
    }

    function collectFromDOM() {
        document.querySelectorAll('#tableBody input').forEach((input) => {
            const rowIndex = parseInt(input.dataset.row, 10);
            const field = input.dataset.field;
            if (!rows[rowIndex]) {
                return;
            }
            rows[rowIndex][field] = input.type === 'number' ? (parseFloat(input.value) || 0) : String(input.value || '').toUpperCase();
            rows[rowIndex]._row_no = rowIndex + 1;
        });
    }

    function focusCell(rowIndex, fieldKey) {
        setTimeout(() => {
            const input = document.querySelector('input[data-row="' + rowIndex + '"][data-field="' + fieldKey + '"]');
            if (input) {
                input.focus();
                input.select();
            }
        }, 50);
    }

    function handleKeyNav(e, rowIndex, fieldKey) {
        const keys = fields.map((f) => f.key);
        const colIndex = keys.indexOf(fieldKey);

        if (e.key === 'Enter') {
            e.preventDefault();
            collectFromDOM();
            if (colIndex === keys.length - 1 && rowIndex === rows.length - 1) {
                addRow();
                return;
            }
            if (colIndex === keys.length - 1) {
                focusCell(rowIndex + 1, keys[0]);
                return;
            }
            focusCell(rowIndex, keys[colIndex + 1]);
            return;
        }

        if (e.key === 'F9') {
            e.preventDefault();
            saveAll();
            return;
        }

        if (e.key === 'ArrowDown' && rowIndex < rows.length - 1) {
            e.preventDefault();
            focusCell(rowIndex + 1, fieldKey);
        }

        if (e.key === 'ArrowUp' && rowIndex > 0) {
            e.preventDefault();
            focusCell(rowIndex - 1, fieldKey);
        }
    }

    function updateStatus() {
        const label = rows.length + ' row' + (rows.length === 1 ? '' : 's');
        document.getElementById('rowCount').textContent = label;
    }

    function showToast(text, type) {
        const toast = document.getElementById('toast');
        toast.textContent = text;
        toast.className = 'toast show ' + type;
        setTimeout(() => {
            toast.className = 'toast';
        }, 3000);
    }

    btnAdd.addEventListener('click', addRow);
    btnDelete.addEventListener('click', deleteRow);
    btnCancel.addEventListener('click', cancelChanges);
    btnSave.addEventListener('click', saveAll);
    btnExit.addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else {
            window.close();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key.toLowerCase() === 'n') {
            e.preventDefault();
            addRow();
        }
        if (e.key === 'F9') {
            e.preventDefault();
            saveAll();
        }
    });

    loadData();
})();
</script>
</body>
</html>
