<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Denomination Master</title>
    <style>
        :root {
            --bg: #f0ede8;
            --surface: #faf9f7;
            --surface2: #f4f2ee;
            --border: #ddd9d2;
            --border-dark: #c8c4bc;
            --accent: #2563eb;
            --accent-dim: #1d4ed8;
            --danger: #dc2626;
            --success: #16a34a;
            --warn: #d97706;
            --text: #1c1917;
            --text-muted: #78716c;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 20px;
        }
        .window {
            max-width: 740px;
            margin: 0 auto;
            background: var(--surface);
            border: 1px solid var(--border-dark);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .titlebar {
            background: #1c1917;
            color: #a8a29e;
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
            padding: 11px 16px;
            font-weight: 700;
        }
        .warn {
            margin: 10px 12px 0;
            padding: 9px 10px;
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #7f1d1d;
            font-size: 12px;
            font-weight: 700;
        }
        .toolbar {
            display: flex;
            gap: 8px;
            padding: 10px 12px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            background: var(--surface2);
            flex-wrap: wrap;
        }
        .toolbar .spacer { flex: 1; }
        .btn {
            border: 1px solid var(--border-dark);
            background: #fff;
            color: var(--text);
            padding: 6px 13px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-primary { background: var(--accent); border-color: var(--accent-dim); color: #fff; }
        .btn-danger { border-color: #fca5a5; color: var(--danger); background: #fff; }
        .btn-ghost { background: transparent; color: var(--text-muted); }
        .grid-wrap { overflow: auto; max-height: 520px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th {
            position: sticky;
            top: 0;
            background: #f0ede8;
            border-bottom: 2px solid var(--border-dark);
            border-right: 1px solid var(--border);
            padding: 8px 10px;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            white-space: nowrap;
            text-align: left;
        }
        thead th:last-child, td:last-child { border-right: none; }
        tbody tr:nth-child(odd) { background: #faf9f7; }
        tbody tr:nth-child(even) { background: #f5f3ef; }
        tbody tr.is-selected td { outline: 1px inset var(--accent); }
        tbody tr.is-new { background: #eff6ff !important; }
        tbody tr.is-dirty { background: #fffbeb !important; }
        td {
            border-right: 1px solid var(--border);
            border-bottom: 1px solid rgba(0,0,0,.04);
            padding: 3px 4px;
        }
        td.col-rownum {
            width: 40px;
            text-align: center;
            color: #a8a29e;
            font-size: 11px;
        }
        input {
            width: 100%;
            border: 1px solid transparent;
            background: transparent;
            padding: 5px 8px;
            font-size: 12px;
            outline: none;
        }
        input:focus { border-color: var(--accent); background: #fff; }
        input.upper { text-transform: uppercase; }
        input.center { text-align: center; }
        .status {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            border-top: 1px solid var(--border);
            background: var(--surface2);
            color: var(--text-muted);
            font-size: 12px;
        }
        .toast {
            position: fixed;
            right: 18px;
            top: 18px;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            display: none;
        }
        .toast.show { display: block; }
        .toast.ok { background: #14532d; }
        .toast.err { background: #7f1d1d; }
        .empty td {
            text-align: center;
            color: var(--text-muted);
            padding: 24px;
            font-style: italic;
        }
    </style>
</head>
<body>
<div class="window">
    <div class="titlebar">w_denom_master - Denomination Master</div>
    @if($denomTableMissing)
        <div class="warn">`denom_master` table not found.</div>
    @endif
    @if($salesmMissing)
        <div class="warn">`salesm` table not found. Delete usage guard will be skipped.</div>
    @endif

    <div class="toolbar">
        <button class="btn btn-primary" id="btnAdd">+ Add</button>
        <button class="btn btn-danger" id="btnDelete">Delete</button>
        <span class="spacer"></span>
        <button class="btn" id="btnCancel">Cancel</button>
        <button class="btn btn-primary" id="btnSave">Save</button>
        <button class="btn btn-ghost" id="btnExit">Exit</button>
    </div>

    <div class="grid-wrap">
        <table>
            <thead>
            <tr>
                <th style="width:40px;text-align:center">#</th>
                <th style="min-width:120px">Code</th>
                <th style="min-width:220px">Name</th>
                <th style="min-width:120px;text-align:center">Value</th>
            </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
    </div>

    <div class="status">
        <span id="statusText">Retrieving data...</span>
        <span id="rowCount">0 rows</span>
    </div>
</div>
<div class="toast" id="toast"></div>

<script>
(() => {
    const disabled = @json($denomTableMissing);
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const tableBody = document.getElementById('tableBody');
    const statusText = document.getElementById('statusText');
    const rowCount = document.getElementById('rowCount');
    const keys = ['code', 'name', 'cvalue'];
    const state = { rows: [], deleted: [], dirty: new Set(), created: new Set(), selected: -1 };

    if (disabled) {
        document.getElementById('btnAdd').disabled = true;
        document.getElementById('btnDelete').disabled = true;
        document.getElementById('btnSave').disabled = true;
    }

    function showToast(msg, ok = true) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast show ' + (ok ? 'ok' : 'err');
        setTimeout(() => t.className = 'toast', 3000);
    }

    function setStatus(msg) {
        statusText.textContent = msg;
        rowCount.textContent = state.rows.length + ' rows';
    }

    async function postJson(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload || {})
        });
        return await res.json();
    }

    function selectRow(i) {
        state.selected = i;
        tableBody.querySelectorAll('tr').forEach((tr, idx) => tr.classList.toggle('is-selected', idx === i));
    }

    function markDirty(i) {
        if (!state.created.has(i)) state.dirty.add(i);
        const tr = tableBody.querySelectorAll('tr')[i];
        if (tr && !tr.classList.contains('is-new')) tr.classList.add('is-dirty');
        setStatus('Unsaved changes.');
    }

    function focusCell(rowIndex, key) {
        setTimeout(() => {
            const input = tableBody.querySelector(`[data-row="${rowIndex}"][data-key="${key}"]`);
            if (!input) return;
            input.focus();
            input.select();
        }, 30);
    }

    function handleKey(e, rowIndex, key) {
        const col = keys.indexOf(key);
        if (e.key === 'Enter') {
            e.preventDefault();
            collect();
            if (col < keys.length - 1) {
                focusCell(rowIndex, keys[col + 1]);
            } else if (rowIndex < state.rows.length - 1) {
                focusCell(rowIndex + 1, keys[0]);
            } else {
                addRow();
            }
        }
        if (e.shiftKey && e.key === 'ArrowRight' && col < keys.length - 1) {
            e.preventDefault();
            focusCell(rowIndex, keys[col + 1]);
        }
        if (e.shiftKey && e.key === 'ArrowLeft' && col > 0) {
            e.preventDefault();
            focusCell(rowIndex, keys[col - 1]);
        }
        if (e.key === 'F9') {
            e.preventDefault();
            saveRows();
        }
    }

    function collect() {
        tableBody.querySelectorAll('input').forEach((input) => {
            const i = Number(input.dataset.row);
            const key = input.dataset.key;
            if (!state.rows[i]) return;
            state.rows[i][key] = input.type === 'number' ? (parseFloat(input.value) || 0) : input.value;
        });
    }

    function render() {
        tableBody.innerHTML = '';
        if (state.rows.length === 0) {
            tableBody.innerHTML = '<tr class="empty"><td colspan="4">No records found.</td></tr>';
            setStatus('0 rows loaded.');
            return;
        }

        state.rows.forEach((row, i) => {
            const tr = document.createElement('tr');
            if (state.created.has(i)) tr.classList.add('is-new');
            if (state.dirty.has(i)) tr.classList.add('is-dirty');

            const tdNum = document.createElement('td');
            tdNum.className = 'col-rownum';
            tdNum.textContent = String(i + 1);
            tr.appendChild(tdNum);

            const mk = (key, opts = {}) => {
                const td = document.createElement('td');
                const input = document.createElement('input');
                input.type = opts.numeric ? 'number' : 'text';
                input.value = row[key] ?? (opts.numeric ? 0 : '');
                input.dataset.row = String(i);
                input.dataset.key = key;
                if (opts.numeric) input.step = '0.01';
                if (opts.upper) input.classList.add('upper');
                if (opts.center) input.classList.add('center');
                input.addEventListener('focus', () => { selectRow(i); input.select(); });
                input.addEventListener('change', () => {
                    if (opts.upper) input.value = input.value.toUpperCase();
                    row[key] = opts.numeric ? (parseFloat(input.value) || 0) : input.value;
                    markDirty(i);
                });
                input.addEventListener('keydown', (e) => handleKey(e, i, key));
                td.appendChild(input);
                return td;
            };

            tr.appendChild(mk('code', { upper: true }));
            tr.appendChild(mk('name'));
            tr.appendChild(mk('cvalue', { numeric: true, center: true }));
            tr.addEventListener('click', () => selectRow(i));
            tableBody.appendChild(tr);
        });
        setStatus(state.rows.length + ' rows loaded.');
    }

    async function retrieve() {
        setStatus('Retrieving data...');
        const res = await postJson(@json(url('/denomination-master/retrieve')), {});
        if (!res.success) {
            tableBody.innerHTML = '<tr class="empty"><td colspan="4">Error: ' + (res.message || 'Failed') + '</td></tr>';
            setStatus(res.message || 'Failed to load.');
            showToast(res.message || 'Failed to load.', false);
            return;
        }
        state.rows = res.data || [];
        state.deleted = [];
        state.dirty = new Set();
        state.created = new Set();
        state.selected = -1;
        render();
    }

    function addRow() {
        collect();
        const last = state.rows[state.rows.length - 1];
        if (last && String(last.code || '').trim() === '') {
            focusCell(state.rows.length - 1, 'code');
            return;
        }
        state.rows.push({ code: '', name: '', cvalue: 0 });
        const idx = state.rows.length - 1;
        state.created.add(idx);
        render();
        setStatus('New row added.');
        focusCell(idx, 'code');
    }

    function reindexAfterDelete(idx) {
        const nd = new Set();
        const nn = new Set();
        state.dirty.forEach((i) => { if (i < idx) nd.add(i); else if (i > idx) nd.add(i - 1); });
        state.created.forEach((i) => { if (i < idx) nn.add(i); else if (i > idx) nn.add(i - 1); });
        state.dirty = nd;
        state.created = nn;
    }

    async function deleteRow() {
        const idx = state.selected;
        if (idx < 0 || idx >= state.rows.length) {
            showToast('Select a row to delete.', false);
            return;
        }
        collect();
        const row = state.rows[idx];
        const code = String(row.code || '').trim().toUpperCase();

        if (!state.created.has(idx) && code !== '') {
            const ref = await postJson(@json(url('/denomination-master/check-ref')), { code: code });
            if (!ref.success) {
                showToast(ref.message || 'Reference check failed.', false);
                return;
            }
            if ((ref.count || 0) > 0) {
                alert("You can't delete this entry - it is referenced in sales.");
                return;
            }
        }

        if (!confirm('Delete this row?')) return;
        if (!state.created.has(idx) && code !== '') state.deleted.push(code);
        state.rows.splice(idx, 1);
        reindexAfterDelete(idx);
        state.selected = -1;
        render();
        setStatus('Row deleted. Save to commit.');
    }

    async function saveRows() {
        collect();
        const changed = state.rows.filter((_, i) => state.created.has(i) || state.dirty.has(i));
        if (changed.length === 0 && state.deleted.length === 0) {
            showToast('No changes to save.', false);
            return;
        }
        setStatus('Saving...');
        const res = await postJson(@json(url('/denomination-master/save')), { rows: changed, deleted: state.deleted });
        if (!res.success) {
            setStatus(res.message || 'Save failed.');
            showToast(res.message || 'Save failed.', false);
            return;
        }
        showToast(res.message || 'Saved successfully.');
        await retrieve();
    }

    document.getElementById('btnAdd').addEventListener('click', addRow);
    document.getElementById('btnDelete').addEventListener('click', deleteRow);
    document.getElementById('btnCancel').addEventListener('click', retrieve);
    document.getElementById('btnSave').addEventListener('click', saveRows);
    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
            return;
        }
        window.close();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            document.getElementById('btnExit').click();
        }
        if (e.key === 'F9') {
            e.preventDefault();
            saveRows();
        }
    });

    retrieve();
})();
</script>
</body>
</html>

