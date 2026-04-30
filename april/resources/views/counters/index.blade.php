<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Counters</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #c0c0c0; padding: 20px; }
        .window { background: #c0c0c0; border: 2px outset #fff; max-width: 700px; margin: 0 auto; }
        .titlebar { background: #000080; color: #fff; padding: 4px 8px; font-weight: 700; font-size: 14px; }
        .content { padding: 10px; }
        .warn {
            margin-bottom: 8px;
            padding: 6px 10px;
            border: 1px solid #f2b5b5;
            background: #fbe6e6;
            color: #8a1f1f;
            font-size: 12px;
            font-weight: 700;
        }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-bottom: 10px; }
        th {
            background: #c0a000;
            color: #000;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 6px;
            text-align: left;
            border: 1px solid #808080;
        }
        td { padding: 2px; border: 1px solid #808080; }
        td input { width: 100%; border: 1px solid #808080; padding: 2px 4px; font-size: 12px; font-family: Arial, Helvetica, sans-serif; }
        td input.code-input,
        td input.name-input { text-transform: uppercase; }
        td input.bill-input { text-align: right; }
        .btn-row { display: flex; gap: 8px; justify-content: center; padding: 8px; }
        .btn {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 16px;
            border: 2px outset #fff;
            background: #c0c0c0;
            cursor: pointer;
            min-width: 70px;
        }
        .btn:active { border-style: inset; }
        .btn:disabled { cursor: default; opacity: 0.6; }
        .msg { padding: 6px 10px; margin-bottom: 8px; font-size: 12px; border-radius: 2px; }
        .msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .table-scroll { max-height: 400px; overflow-y: auto; border: 2px inset #808080; }
    </style>
</head>
<body>
<div class="window">
    <div class="titlebar">Counters</div>
    <div class="content">
        @if($tableMissing)
            <div class="warn">`counter` table not found.</div>
        @endif
        <div id="message"></div>
        <div class="table-scroll">
            <table id="counterTable">
                <thead>
                    <tr>
                        <th style="width:20%">Code</th>
                        <th style="width:50%">Name</th>
                        <th style="width:30%">Start Billno</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($counters as $row)
                    <tr>
                        <td><input type="text" class="code-input" name="code" maxlength="5" value="{{ $row->code }}"></td>
                        <td><input type="text" class="name-input" name="name" maxlength="15" value="{{ $row->name }}"></td>
                        <td><input type="text" class="bill-input" name="startbillno" value="{{ (int) $row->startbillno }}"></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="btn-row">
            <button class="btn" id="btnAdd">&Add</button>
            <button class="btn" id="btnDelete">&Delete</button>
            <button class="btn" id="btnSave">&Save</button>
            <button class="btn" id="btnExit">E&xit</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const table = document.getElementById('counterTable');
    const tbody = table.querySelector('tbody');
    const tableMissing = @json($tableMissing);

    const btnAdd = document.getElementById('btnAdd');
    const btnDelete = document.getElementById('btnDelete');
    const btnSave = document.getElementById('btnSave');
    const btnExit = document.getElementById('btnExit');

    if (tableMissing) {
        btnAdd.disabled = true;
        btnDelete.disabled = true;
        btnSave.disabled = true;
    }

    function showMsg(text, type) {
        const el = document.getElementById('message');
        el.className = 'msg ' + (type || 'success');
        el.textContent = text;
        setTimeout(() => {
            el.textContent = '';
            el.className = '';
        }, 4000);
    }

    function addRow() {
        if (tableMissing) {
            return;
        }

        const rows = tbody.querySelectorAll('tr');
        if (rows.length > 0) {
            const lastCode = rows[rows.length - 1].querySelector('input[name="code"]').value.trim();
            if (lastCode === '') {
                rows[rows.length - 1].querySelector('input[name="code"]').focus();
                return;
            }
        }

        const tr = document.createElement('tr');
        tr.innerHTML = [
            '<td><input type="text" class="code-input" name="code" maxlength="5"></td>',
            '<td><input type="text" class="name-input" name="name" maxlength="15"></td>',
            '<td><input type="text" class="bill-input" name="startbillno" value="0"></td>'
        ].join('');
        tbody.appendChild(tr);
        tr.querySelector('input[name="code"]').focus();
        tr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    async function deleteRow() {
        if (tableMissing) {
            return;
        }

        const rows = tbody.querySelectorAll('tr');
        const active = document.activeElement;
        let targetRow = null;
        rows.forEach((row) => {
            if (row.contains(active)) {
                targetRow = row;
            }
        });
        if (!targetRow && rows.length > 0) {
            targetRow = rows[rows.length - 1];
        }
        if (!targetRow) {
            return;
        }

        const code = targetRow.querySelector('input[name="code"]').value.trim().toUpperCase();
        if (code === '') {
            targetRow.remove();
            return;
        }

        const resp = await fetch(@json(url('/counters/delete')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ code: code })
        });
        const data = await resp.json();
        showMsg(data.message || 'Delete failed.', data.success ? 'success' : 'error');
        if (data.success) {
            targetRow.remove();
        }
    }

    async function saveAll() {
        if (tableMissing) {
            return;
        }

        const rows = [];
        tbody.querySelectorAll('tr').forEach((tr) => {
            rows.push({
                code: tr.querySelector('input[name="code"]').value,
                name: tr.querySelector('input[name="name"]').value,
                startbillno: tr.querySelector('input[name="startbillno"]').value
            });
        });

        const resp = await fetch(@json(url('/counters/save')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ rows: rows })
        });

        const data = await resp.json();
        showMsg(data.message || 'Save failed.', data.success ? 'success' : 'error');
    }

    function focusNextFrom(current) {
        const td = current.closest('td');
        if (!td) {
            return;
        }
        const tr = td.closest('tr');
        if (!tr) {
            return;
        }

        const nextTd = td.nextElementSibling;
        if (nextTd) {
            const input = nextTd.querySelector('input');
            if (input) {
                input.focus();
            }
            return;
        }

        const nextTr = tr.nextElementSibling;
        if (nextTr) {
            const nextCode = nextTr.querySelector('input[name="code"]');
            if (nextCode) {
                nextCode.focus();
            }
        } else {
            addRow();
        }
    }

    btnAdd.addEventListener('click', addRow);
    btnDelete.addEventListener('click', () => { deleteRow(); });
    btnSave.addEventListener('click', () => { saveAll(); });
    btnExit.addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else {
            window.close();
        }
    });

    table.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            focusNextFrom(e.target);
            return;
        }
        if (e.key === 'F9') {
            e.preventDefault();
            saveAll();
        }
    });
})();
</script>
</body>
</html>

