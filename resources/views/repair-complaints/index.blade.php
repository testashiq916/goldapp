<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Remake Complaint Master</title>
    <style>
        :root {
            --bg: #eef0f4;
            --surface: #ffffff;
            --surface-alt: #f7f8fa;
            --border: #dce0e8;
            --text: #1c2030;
            --text-muted: #6c7489;
            --primary: #5b7fff;
            --primary-hover: #4a6cf0;
            --primary-light: #eef2ff;
            --danger: #e54e53;
            --danger-light: #fff0f0;
            --success: #2da664;
            --warning: #e8a030;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.07);
            --shadow-lg: 0 12px 40px rgba(0,0,0,0.10);
            --radius: 12px;
            --radius-sm: 7px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            padding: 20px;
        }
        .app { width: 100%; max-width: 620px; }
        .warn {
            margin-bottom: 8px;
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
            margin-bottom: 10px;
        }
        .header h1 { font-size: 20px; font-weight: 700; }
        .row-badge {
            font-size: 12px;
            color: var(--text-muted);
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 3px 10px;
            border-radius: 20px;
        }
        .toolbar { display: flex; gap: 7px; margin-bottom: 10px; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 14px;
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
        .btn-danger { color: var(--danger); border-color: #fcc; background: var(--danger-light); }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .table-scroll { max-height: 520px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th {
            background: var(--surface-alt);
            border-bottom: 2px solid var(--border);
            padding: 10px 14px;
            text-align: left;
            font-weight: 700;
            font-size: 11px;
        }
        tbody tr { border-bottom: 1px solid #f0f1f5; cursor: pointer; }
        tbody tr.selected { background: var(--primary-light); }
        tbody td { padding: 2px 6px; }
        .row-num {
            text-align: center;
            color: var(--text-muted);
            font-size: 11px;
            width: 40px;
            padding: 9px 6px;
        }
        td input {
            width: 100%;
            border: 1px solid transparent;
            background: transparent;
            padding: 9px 8px;
            font-size: 13px;
            text-transform: uppercase;
            border-radius: 4px;
            outline: none;
        }
        td input:focus { border-color: var(--primary); background: #fff; }
        .footer {
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-muted);
        }
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 440px;
            max-width: 95vw;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .modal-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            font-weight: 700;
            font-size: 15px;
        }
        .modal-body { flex: 1; overflow-y: auto; }
        .modal-list { list-style: none; }
        .modal-list li {
            padding: 10px 14px;
            cursor: pointer;
            font-size: 13px;
            border-bottom: 1px solid #f4f5f7;
        }
        .modal-list li.selected { background: var(--primary-light); font-weight: 700; }
        .modal-footer {
            padding: 10px 16px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .toast {
            position: fixed;
            bottom: 16px;
            right: 16px;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 700;
            z-index: 200;
            display: none;
        }
        .toast.show { display: block; }
        .toast.success { background: var(--success); color: #fff; }
        .toast.error { background: var(--danger); color: #fff; }
        .toast.warning { background: var(--warning); color: #2d1b00; }
    </style>
</head>
<body>
<div class="app">
    @if($repcomplMissing)
        <div class="warn">`repcompl` table not found.</div>
    @endif
    @if($itemsMissing)
        <div class="warn">`items` table not found. Item lookup API will return empty values.</div>
    @endif

    <div class="header">
        <h1>Remake Complaint Master</h1>
        <span class="row-badge" id="rowCount">0 rows</span>
    </div>

    <div class="toolbar">
        <button class="btn" id="btnAdd">Add</button>
        <button class="btn btn-danger" id="btnDelete">Delete</button>
        <button class="btn" id="btnCancel">Cancel</button>
        <button class="btn btn-primary" id="btnSave">Save</button>
        <button class="btn" id="btnExit">Exit</button>
    </div>

    <div class="card">
        <div class="table-scroll">
            <table>
                <thead><tr><th style="width:40px">#</th><th>Complaint</th></tr></thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>
    </div>

    <div class="footer">
        <span id="statusText"></span>
        <span>Enter = Next, F1 = Help, F9 = Save</span>
    </div>
</div>

<div class="modal-overlay" id="helpModal">
    <div class="modal">
        <div class="modal-header">Remake Complaint Help</div>
        <div class="modal-body">
            <ul class="modal-list" id="helpList"></ul>
        </div>
        <div class="modal-footer">
            <button class="btn" id="btnHelpExit">Exit</button>
            <button class="btn btn-primary" id="btnHelpOk">Ok</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const disabled = @json($repcomplMissing);

    let rows = [];
    let deletedParts = [];
    let selectedRow = -1;
    let helpSelectedIdx = -1;
    let helpData = [];
    let helpResolve = null;

    const tableBody = document.getElementById('tableBody');
    const rowCount = document.getElementById('rowCount');
    const statusText = document.getElementById('statusText');
    const helpModal = document.getElementById('helpModal');
    const helpList = document.getElementById('helpList');

    const btnAdd = document.getElementById('btnAdd');
    const btnDelete = document.getElementById('btnDelete');
    const btnCancel = document.getElementById('btnCancel');
    const btnSave = document.getElementById('btnSave');
    const btnExit = document.getElementById('btnExit');
    const btnHelpExit = document.getElementById('btnHelpExit');
    const btnHelpOk = document.getElementById('btnHelpOk');

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
        return await response.json();
    }

    async function loadData() {
        const json = await postJson(@json(url('/repair-complaints/retrieve')), {});
        if (!json.success) {
            showToast(json.message || 'Failed to load data.', 'error');
            return;
        }
        rows = json.data || [];
        deletedParts = [];
        selectedRow = -1;
        renderTable();
        updateStatus();
    }

    async function saveAll() {
        collectFromDOM();
        const json = await postJson(@json(url('/repair-complaints/save')), {
            rows: rows,
            deleted: deletedParts
        });
        if (!json.success) {
            showToast(json.message || 'Save failed.', 'error');
            return;
        }
        showToast(json.message || 'Saved successfully', 'success');
        loadData();
    }

    function addRow() {
        collectFromDOM();
        if (rows.length > 0) {
            const lastPart = String(rows[rows.length - 1].part || '').trim();
            if (lastPart === '') {
                selectRowByIndex(rows.length - 1);
                focusCell(rows.length - 1);
                showToast('Enter a complaint for the current row first', 'warning');
                return;
            }
        }
        rows.push({ part: '' });
        renderTable();
        updateStatus();
        selectRowByIndex(rows.length - 1);
        focusCell(rows.length - 1);
    }

    function deleteRow() {
        if (selectedRow < 0 || selectedRow >= rows.length) {
            showToast('Select a row to delete', 'warning');
            return;
        }
        if (!confirm('Delete?')) {
            return;
        }
        collectFromDOM();
        const removed = rows.splice(selectedRow, 1)[0];
        const part = String((removed && removed.part) || '').trim();
        if (part !== '') {
            deletedParts.push(part);
        }
        selectedRow = -1;
        renderTable();
        updateStatus();
    }

    function cancelChanges() {
        loadData();
    }

    function renderTable() {
        tableBody.innerHTML = '';
        rows.forEach((row, i) => {
            const tr = document.createElement('tr');
            tr.addEventListener('click', () => selectRowByIndex(i));
            if (i === selectedRow) {
                tr.classList.add('selected');
            }

            const tdNum = document.createElement('td');
            tdNum.className = 'row-num';
            tdNum.textContent = String(i + 1);
            tr.appendChild(tdNum);

            const td = document.createElement('td');
            const input = document.createElement('input');
            input.type = 'text';
            input.maxLength = 30;
            input.value = row.part || '';
            input.dataset.row = String(i);
            input.addEventListener('focus', () => selectRowByIndex(i));
            input.addEventListener('keydown', (e) => handleKey(e, i));
            td.appendChild(input);
            tr.appendChild(td);
            tableBody.appendChild(tr);
        });
    }

    function collectFromDOM() {
        document.querySelectorAll('#tableBody input').forEach((input) => {
            const rowIndex = parseInt(input.dataset.row, 10);
            if (!rows[rowIndex]) {
                return;
            }
            rows[rowIndex].part = input.value.toUpperCase();
        });
    }

    function selectRowByIndex(i) {
        selectedRow = i;
        document.querySelectorAll('#tableBody tr').forEach((tr, idx) => {
            tr.classList.toggle('selected', idx === i);
        });
    }

    function focusCell(rowIndex) {
        setTimeout(() => {
            const input = document.querySelector('input[data-row="' + rowIndex + '"]');
            if (input) {
                input.focus();
                input.select();
            }
        }, 50);
    }

    function updateStatus() {
        const count = rows.length;
        rowCount.textContent = count + ' row' + (count === 1 ? '' : 's');
        statusText.textContent = count === 0 ? 'No complaints.' : '';
    }

    function handleKey(e, rowIndex) {
        if (e.key === 'Enter') {
            e.preventDefault();
            collectFromDOM();
            if (rowIndex >= rows.length - 1) {
                addRow();
            } else {
                focusCell(rowIndex + 1);
            }
            return;
        }
        if (e.key === 'ArrowDown' && rowIndex < rows.length - 1) {
            e.preventDefault();
            focusCell(rowIndex + 1);
            return;
        }
        if (e.key === 'ArrowUp' && rowIndex > 0) {
            e.preventDefault();
            focusCell(rowIndex - 1);
            return;
        }
        if (e.key === 'F9') {
            e.preventDefault();
            saveAll();
            return;
        }
        if (e.key === 'F1') {
            e.preventDefault();
            openHelp(rowIndex);
        }
    }

    async function openHelp(rowIndex) {
        const json = await postJson(@json(url('/repair-complaints/help-list')), {});
        helpData = (json && json.success) ? (json.data || []) : [];
        helpSelectedIdx = helpData.length > 0 ? 0 : -1;
        renderHelpList();
        helpModal.classList.add('active');

        const selected = await new Promise((resolve) => {
            helpResolve = resolve;
        });
        helpModal.classList.remove('active');

        if (!selected) {
            return;
        }

        const input = document.querySelector('input[data-row="' + rowIndex + '"]');
        if (input) {
            input.value = selected;
            rows[rowIndex].part = selected;
        }
    }

    function renderHelpList() {
        helpList.innerHTML = '';
        helpData.forEach((item, index) => {
            const li = document.createElement('li');
            li.textContent = item.part || '';
            if (index === helpSelectedIdx) {
                li.classList.add('selected');
            }
            li.addEventListener('click', () => {
                helpSelectedIdx = index;
                renderHelpList();
            });
            li.addEventListener('dblclick', () => {
                helpSelectedIdx = index;
                selectHelp();
            });
            helpList.appendChild(li);
        });
    }

    function selectHelp() {
        const value = helpSelectedIdx >= 0 && helpData[helpSelectedIdx] ? (helpData[helpSelectedIdx].part || '') : '';
        if (helpResolve) {
            helpResolve(value);
            helpResolve = null;
        }
    }

    function closeHelp() {
        if (helpResolve) {
            helpResolve('');
            helpResolve = null;
        }
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

    btnHelpExit.addEventListener('click', closeHelp);
    btnHelpOk.addEventListener('click', selectHelp);
    helpModal.addEventListener('click', (e) => {
        if (e.target === helpModal) {
            closeHelp();
        }
    });
    helpModal.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' && helpSelectedIdx < helpData.length - 1) {
            e.preventDefault();
            helpSelectedIdx++;
            renderHelpList();
        } else if (e.key === 'ArrowUp' && helpSelectedIdx > 0) {
            e.preventDefault();
            helpSelectedIdx--;
            renderHelpList();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            selectHelp();
        } else if (e.key === 'Escape') {
            closeHelp();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (helpModal.classList.contains('active')) {
            return;
        }
        if (e.ctrlKey && e.key.toLowerCase() === 'n') {
            e.preventDefault();
            addRow();
        } else if (e.key === 'F9') {
            e.preventDefault();
            saveAll();
        }
    });

    loadData();
})();
</script>
</body>
</html>

