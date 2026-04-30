<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Prev. Card Table</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg: #1e2228;
    --surface: #272c34;
    --surface2: #2e343e;
    --border: #3a4050;
    --accent: #3b82f6;
    --accent-dim: #1d4ed8;
    --danger: #ef4444;
    --danger-dim: #b91c1c;
    --success: #22c55e;
    --warn: #f59e0b;
    --text: #e2e8f0;
    --text-muted: #8b95a8;
    --header-bg: #1a1f27;
    --row-odd: #272c34;
    --row-even: #2a2f38;
    --row-hover: #323844;
    --row-new: rgba(59,130,246,.08);
    --row-dirty: rgba(245,158,11,.06);
    --input-bg: #1e2228;
}
body {
    font-family: "Segoe UI", Tahoma, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    padding: 24px 16px;
}
.window {
    max-width: 1100px;
    margin: 0 auto;
    border: 1px solid var(--border);
    background: var(--surface);
}
.titlebar {
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    background: var(--header-bg);
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}
.warn {
    margin: 12px 16px 0;
    padding: 9px 10px;
    border: 1px solid #8f3636;
    background: #451515;
    color: #ffb8b8;
    font-size: 12px;
    font-weight: 700;
}
.toolbar {
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
    padding: 10px 16px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.btn {
    font-size: 13px;
    font-weight: 700;
    padding: 7px 14px;
    border: 1px solid transparent;
    cursor: pointer;
}
.btn-primary { background: var(--accent); color: #fff; border-color: var(--accent-dim); }
.btn-primary:hover { background: var(--accent-dim); }
.btn-secondary { background: var(--surface); color: var(--text); border-color: var(--border); }
.btn-secondary:hover { background: var(--bg); }
.btn-danger { background: var(--danger); color: #fff; border-color: var(--danger-dim); }
.btn-danger:hover { background: var(--danger-dim); }
.grid-wrap { overflow: auto; }
table { width: 100%; border-collapse: collapse; min-width: 900px; font-size: 13px; }
thead tr { background: var(--header-bg); position: sticky; top: 0; }
thead th {
    padding: 8px 10px;
    border-right: 1px solid var(--border);
    border-bottom: 2px solid var(--border);
    color: var(--text-muted);
    font-size: 11px;
    text-transform: uppercase;
    text-align: left;
    white-space: nowrap;
}
thead th:last-child { border-right: none; }
tbody tr:nth-child(odd) { background: var(--row-odd); }
tbody tr:nth-child(even) { background: var(--row-even); }
tbody tr:hover { background: var(--row-hover); }
tbody tr.is-new { background: var(--row-new); }
tbody tr.is-dirty { background: var(--row-dirty); }
tbody tr.selected td { outline: 1px inset var(--accent); }
td {
    padding: 3px 4px;
    border-right: 1px solid var(--border);
    border-bottom: 1px solid rgba(255,255,255,.04);
}
td:last-child { border-right: none; }
input, select {
    width: 100%;
    border: 1px solid transparent;
    background: transparent;
    color: var(--text);
    font-size: 12px;
    padding: 4px 6px;
    outline: none;
}
input:focus, select:focus { border-color: var(--accent); background: var(--input-bg); }
input[type="number"] { text-align: right; }
.col-rownum { width: 38px; text-align: center; color: var(--text-muted); font-size: 11px; }
.statusbar {
    font-size: 12px;
    color: var(--text-muted);
    background: var(--header-bg);
    border-top: 1px solid var(--border);
    padding: 7px 16px;
    display: flex;
    gap: 12px;
}
.statusbar .right { margin-left: auto; }
.toast {
    position: fixed;
    right: 18px;
    bottom: 18px;
    color: #fff;
    padding: 10px 12px;
    font-size: 13px;
    font-weight: 700;
    display: none;
}
.toast.success { background: #14532d; border-left: 3px solid var(--success); }
.toast.error { background: #450a0a; border-left: 3px solid var(--danger); }
.toast.show { display: block; }
</style>
</head>
<body>
<div class="window">
    <div class="titlebar">w_pcardtable - Prev. Card Table</div>
    @if($pcardTableMissing)
        <div class="warn">`pcardtable` table not found. Run migration first.</div>
    @endif

    <div class="toolbar">
        <button class="btn btn-primary" id="btnAdd">+ Add</button>
        <button class="btn btn-danger" id="btnDelete">Delete</button>
        <button class="btn btn-primary" id="btnSave">Save</button>
        <button class="btn btn-secondary" id="btnCancel">Cancel</button>
        <button class="btn btn-secondary" id="btnExit">Exit</button>
    </div>

    <div class="grid-wrap">
        <table>
            <thead>
            <tr>
                <th class="col-rownum">#</th>
                <th>Prev. Card</th>
                <th>Item Sub Grp</th>
                <th>Point Based On</th>
                <th>Value for 1 Point</th>
                <th>RS Value / Point</th>
                <th>Min Sales Amt</th>
                <th>Round Down</th>
            </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
    </div>

    <div class="statusbar">
        <span id="statusText">Retrieving data...</span>
        <span class="right" id="rowCount">0 rows</span>
    </div>
</div>
<div class="toast" id="toast"></div>

<script>
(() => {
    const disabled = @json($pcardTableMissing);
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const tableBody = document.getElementById('tableBody');
    const statusText = document.getElementById('statusText');
    const rowCount = document.getElementById('rowCount');
    const toastEl = document.getElementById('toast');
    const keys = ['pcard', 'isubgrp', 'pointbasedon', 'valuefor1point', 'valueperpoint', 'minsalesamt', 'rounddown'];
    const pointOpts = ['Wgt', 'Wgt/Amt', 'Amt'];

    const state = {
        rows: [],
        deleted: [],
        dirty: new Set(),
        created: new Set(),
        selectedRow: -1
    };

    if (disabled) {
        document.getElementById('btnAdd').disabled = true;
        document.getElementById('btnDelete').disabled = true;
        document.getElementById('btnSave').disabled = true;
    }

    function showToast(msg, type = 'success') {
        toastEl.textContent = msg;
        toastEl.className = 'toast show ' + type;
        setTimeout(() => { toastEl.className = 'toast'; }, 2800);
    }

    function setStatus(text) {
        statusText.textContent = text;
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

    function focusCell(rowIndex, key) {
        setTimeout(() => {
            const input = tableBody.querySelector(`[data-row="${rowIndex}"][data-key="${key}"]`);
            if (input) {
                input.focus();
                if (input.select) input.select();
            }
        }, 30);
    }

    function selectRow(index) {
        state.selectedRow = index;
        tableBody.querySelectorAll('tr').forEach((tr, i) => tr.classList.toggle('selected', i === index));
    }

    function markDirty(index) {
        if (!state.created.has(index)) state.dirty.add(index);
        const tr = tableBody.querySelectorAll('tr')[index];
        if (tr && !tr.classList.contains('is-new')) tr.classList.add('is-dirty');
        setStatus('Unsaved changes present.');
    }

    function collectDom() {
        tableBody.querySelectorAll('input, select').forEach((el) => {
            const rowIndex = parseInt(el.dataset.row, 10);
            const key = el.dataset.key;
            if (!state.rows[rowIndex]) return;
            state.rows[rowIndex][key] = el.tagName === 'SELECT'
                ? el.value
                : (el.type === 'number' ? (parseFloat(el.value) || 0) : el.value);
        });
    }

    function render() {
        tableBody.innerHTML = '';
        state.rows.forEach((row, i) => {
            const tr = document.createElement('tr');
            if (state.created.has(i)) tr.classList.add('is-new');
            if (state.dirty.has(i)) tr.classList.add('is-dirty');

            const tdNum = document.createElement('td');
            tdNum.className = 'col-rownum';
            tdNum.textContent = String(i + 1);
            tr.appendChild(tdNum);

            const makeInput = (key, numeric = false, step = '0.01') => {
                const td = document.createElement('td');
                const input = document.createElement('input');
                input.type = numeric ? 'number' : 'text';
                input.step = step;
                input.value = row[key] ?? (numeric ? 0 : '');
                input.dataset.row = String(i);
                input.dataset.key = key;
                input.addEventListener('focus', () => {
                    selectRow(i);
                    if (input.select) input.select();
                });
                input.addEventListener('change', () => {
                    state.rows[i][key] = numeric ? (parseFloat(input.value) || 0) : input.value;
                    markDirty(i);
                });
                input.addEventListener('keydown', (e) => handleNav(e, i, key));
                td.appendChild(input);
                return td;
            };

            const makeSelect = () => {
                const td = document.createElement('td');
                const select = document.createElement('select');
                pointOpts.forEach((opt) => {
                    const option = document.createElement('option');
                    option.value = opt;
                    option.textContent = opt;
                    if ((row.pointbasedon || 'Wgt') === opt) option.selected = true;
                    select.appendChild(option);
                });
                select.dataset.row = String(i);
                select.dataset.key = 'pointbasedon';
                select.addEventListener('focus', () => selectRow(i));
                select.addEventListener('change', () => {
                    state.rows[i].pointbasedon = select.value;
                    markDirty(i);
                });
                select.addEventListener('keydown', (e) => handleNav(e, i, 'pointbasedon'));
                td.appendChild(select);
                return td;
            };

            tr.appendChild(makeInput('pcard'));
            tr.appendChild(makeInput('isubgrp'));
            tr.appendChild(makeSelect());
            tr.appendChild(makeInput('valuefor1point', true));
            tr.appendChild(makeInput('valueperpoint', true));
            tr.appendChild(makeInput('minsalesamt', true));
            tr.appendChild(makeInput('rounddown', true, '0.001'));
            tr.addEventListener('click', () => selectRow(i));
            tableBody.appendChild(tr);
        });

        selectRow(-1);
        rowCount.textContent = state.rows.length + ' rows';
    }

    function reindexSetsAfterDelete(index) {
        const nextDirty = new Set();
        const nextCreated = new Set();
        state.dirty.forEach((i) => {
            if (i < index) nextDirty.add(i);
            if (i > index) nextDirty.add(i - 1);
        });
        state.created.forEach((i) => {
            if (i < index) nextCreated.add(i);
            if (i > index) nextCreated.add(i - 1);
        });
        state.dirty = nextDirty;
        state.created = nextCreated;
    }

    function addRow() {
        collectDom();
        const last = state.rows[state.rows.length - 1];
        if (last && String(last.pcard || '').trim() === '') {
            focusCell(state.rows.length - 1, 'pcard');
            return;
        }
        state.rows.push({
            pcard: '',
            isubgrp: '',
            pointbasedon: 'Wgt',
            valuefor1point: 0,
            valueperpoint: 0,
            minsalesamt: 0,
            rounddown: 0
        });
        const newIndex = state.rows.length - 1;
        state.created.add(newIndex);
        render();
        setStatus('New row added.');
        focusCell(newIndex, 'pcard');
    }

    async function deleteSelected() {
        const idx = state.selectedRow;
        if (idx < 0 || idx >= state.rows.length) {
            showToast('Select a row first.', 'error');
            return;
        }
        collectDom();
        const row = state.rows[idx];
        if (!confirm('Delete this row?')) return;

        if (!state.created.has(idx) && String(row.pcard || '').trim() !== '' && String(row.isubgrp || '').trim() !== '') {
            state.deleted.push([row.pcard, row.isubgrp]);
        }

        state.rows.splice(idx, 1);
        reindexSetsAfterDelete(idx);
        state.selectedRow = -1;
        render();
        setStatus('Row removed. Save to commit.');
    }

    async function saveAll() {
        collectDom();
        const changedRows = [];
        state.rows.forEach((row, i) => {
            if (state.created.has(i) || state.dirty.has(i)) changedRows.push(row);
        });
        if (changedRows.length === 0 && state.deleted.length === 0) {
            showToast('No changes to save.', 'error');
            return;
        }

        setStatus('Saving...');
        const json = await postJson(@json(url('/point-card/save')), {
            rows: changedRows,
            deleted: state.deleted
        });
        if (!json.success) {
            setStatus(json.message || 'Save failed.');
            showToast(json.message || 'Save failed.', 'error');
            return;
        }
        showToast(json.message || 'Saved successfully.', 'success');
        await retrieve();
    }

    async function retrieve() {
        setStatus('Retrieving data...');
        const json = await postJson(@json(url('/point-card/retrieve')), {});
        if (!json.success) {
            tableBody.innerHTML = '<tr><td colspan="8" style="padding:18px;color:#ffb8b8;">' + (json.message || 'Failed to load') + '</td></tr>';
            setStatus(json.message || 'Failed to load');
            showToast(json.message || 'Failed to load.', 'error');
            return;
        }
        state.rows = json.data || [];
        state.deleted = [];
        state.dirty = new Set();
        state.created = new Set();
        state.selectedRow = -1;
        render();
        setStatus(state.rows.length + ' rows retrieved.');
    }

    function handleNav(e, rowIdx, key) {
        const colIndex = keys.indexOf(key);
        if (e.key === 'Enter') {
            e.preventDefault();
            collectDom();
            if (colIndex < keys.length - 1) {
                focusCell(rowIdx, keys[colIndex + 1]);
                return;
            }
            if (rowIdx < state.rows.length - 1) {
                focusCell(rowIdx + 1, keys[0]);
                return;
            }
            addRow();
            return;
        }
        if (e.shiftKey && e.key === 'ArrowRight' && colIndex < keys.length - 1) {
            e.preventDefault();
            focusCell(rowIdx, keys[colIndex + 1]);
        }
        if (e.shiftKey && e.key === 'ArrowLeft' && colIndex > 0) {
            e.preventDefault();
            focusCell(rowIdx, keys[colIndex - 1]);
        }
        if (e.key === 'F9') {
            e.preventDefault();
            saveAll();
        }
    }

    document.getElementById('btnAdd').addEventListener('click', addRow);
    document.getElementById('btnDelete').addEventListener('click', deleteSelected);
    document.getElementById('btnSave').addEventListener('click', saveAll);
    document.getElementById('btnCancel').addEventListener('click', retrieve);
    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else {
            window.close();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            document.getElementById('btnExit').click();
        }
        if (e.key === 'F9') {
            e.preventDefault();
            saveAll();
        }
    });

    retrieve();
})();
</script>
</body>
</html>

