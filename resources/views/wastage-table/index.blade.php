<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Set Wastages</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #c0c0c0; padding: 20px; }
        .window { background: #c0c0c0; border: 2px outset #fff; max-width: 750px; margin: 0 auto; }
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
        .form-row { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
        .form-row label { font-weight: 700; font-size: 12px; min-width: 40px; text-align: right; }
        .form-row input, .form-row select { font-size: 12px; padding: 3px 6px; border: 2px inset #fff; }
        .form-row input:focus { background: rgb(255,150,0); outline: none; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th {
            background: #c0a000;
            color: #000;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 6px;
            text-align: center;
            border: 1px solid #808080;
        }
        td { padding: 1px; border: 1px solid #808080; }
        td input { width: 100%; border: none; padding: 2px 4px; font-size: 12px; text-align: center; }
        td input:focus { background: #ffffcc; outline: none; }
        .table-scroll { max-height: 400px; overflow-y: auto; border: 2px inset #808080; margin-bottom: 10px; }
        .btn-row { display: flex; gap: 8px; justify-content: center; padding: 8px; background: #c0a000; border-radius: 8px; margin: 8px 40px; }
        .btn {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 20px;
            border: 2px outset #fff;
            background: #c0c0c0;
            cursor: pointer;
        }
        .btn:active { border-style: inset; }
        .btn:disabled { cursor: default; opacity: 0.6; }
        .msg { padding: 6px 10px; margin-bottom: 8px; font-size: 12px; }
        .msg.success { background: #d4edda; color: #155724; }
        .msg.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<div class="window">
    <div class="titlebar">Set Wastages</div>
    <div class="content">
        @if($wstgTableMissing)
            <div class="warn">`wstgtable` table not found.</div>
        @endif
        @if($itemsTableMissing)
            <div class="warn">`items` table not found.</div>
        @endif

        <div class="form-row">
            <label>Item :</label>
            <input type="text" id="itemCode" maxlength="10" style="text-transform:uppercase; width:160px" placeholder="Enter item code">
            <button class="btn" id="btnHelp" style="font-size:11px">&Help</button>
            <label style="margin-left:20px">Type :</label>
            <select id="itemType" style="width:140px">
                <option value=""></option>
                @foreach($types as $t)
                    <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
            </select>
            <button class="btn" id="btnShow" style="font-size:11px">Show</button>
        </div>

        <div id="message"></div>

        <div class="table-scroll">
            <table id="wstgTable">
                <thead>
                    <tr>
                        <th style="width:25%">From Wgt</th>
                        <th style="width:25%">To Wgt</th>
                        <th style="width:25%">Wastage</th>
                        <th style="width:25%">%</th>
                    </tr>
                </thead>
                <tbody></tbody>
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
    const tableMissing = @json($wstgTableMissing || $itemsTableMissing);

    const itemCode = document.getElementById('itemCode');
    const itemType = document.getElementById('itemType');
    const tbody = document.querySelector('#wstgTable tbody');
    const table = document.getElementById('wstgTable');

    const btnHelp = document.getElementById('btnHelp');
    const btnShow = document.getElementById('btnShow');
    const btnAdd = document.getElementById('btnAdd');
    const btnDelete = document.getElementById('btnDelete');
    const btnSave = document.getElementById('btnSave');
    const btnExit = document.getElementById('btnExit');

    if (tableMissing) {
        btnHelp.disabled = true;
        btnShow.disabled = true;
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

    function appendRow(w1, w2, wastage, perc) {
        const tr = document.createElement('tr');
        tr.innerHTML = [
            '<td><input type="text" name="weight1"></td>',
            '<td><input type="text" name="weight2"></td>',
            '<td><input type="text" name="wastage"></td>',
            '<td><input type="text" name="perc"></td>'
        ].join('');
        tr.querySelector('input[name="weight1"]').value = w1 ?? '';
        tr.querySelector('input[name="weight2"]').value = w2 ?? '';
        tr.querySelector('input[name="wastage"]').value = wastage ?? '';
        tr.querySelector('input[name="perc"]').value = perc ?? '';
        tbody.appendChild(tr);
        return tr;
    }

    async function validateItemCode() {
        const code = itemCode.value.trim().toUpperCase();
        if (code === '') {
            return true;
        }

        const data = await postJson(@json(url('/wastage-table/validate-item')), { code: code });
        if (!data.success) {
            showMsg(data.message || 'This code does not exist', 'error');
            itemCode.value = '';
            itemCode.focus();
            return false;
        }
        return true;
    }

    async function showData() {
        const code = itemCode.value.trim().toUpperCase();
        const type = itemType.value;

        const data = await postJson(@json(url('/wastage-table/show')), {
            code: code,
            type: type
        });

        tbody.innerHTML = '';
        if (!data.success) {
            showMsg(data.message || 'Unable to load rows.', 'error');
            return;
        }

        (data.rows || []).forEach((row) => {
            appendRow(row.weight1, row.weight2, row.wastage, row.perc);
        });
    }

    function addRow() {
        const rows = tbody.querySelectorAll('tr');
        if (rows.length > 0) {
            const last = rows[rows.length - 1];
            const w1 = parseFloat(last.querySelector('input[name="weight1"]').value) || 0;
            const w2 = parseFloat(last.querySelector('input[name="weight2"]').value) || 0;
            if (w1 <= 0 && w2 <= 0) {
                last.querySelector('input[name="weight1"]').focus();
                return;
            }
        }
        const tr = appendRow('', '', '', '');
        tr.querySelector('input[name="weight1"]').focus();
        tr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function deleteRow() {
        const active = document.activeElement;
        let target = null;
        tbody.querySelectorAll('tr').forEach((tr) => {
            if (tr.contains(active)) {
                target = tr;
            }
        });
        if (!target) {
            target = tbody.lastElementChild;
        }
        if (target) {
            target.remove();
        }
    }

    async function saveData() {
        if (!confirm('Do you want to update table?')) {
            return;
        }

        const code = itemCode.value.trim().toUpperCase();
        if (code === '') {
            showMsg('Item code is required.', 'error');
            itemCode.focus();
            return;
        }

        const rows = [];
        tbody.querySelectorAll('tr').forEach((tr) => {
            rows.push({
                weight1: tr.querySelector('input[name="weight1"]').value,
                weight2: tr.querySelector('input[name="weight2"]').value,
                wastage: tr.querySelector('input[name="wastage"]').value,
                perc: tr.querySelector('input[name="perc"]').value
            });
        });

        const data = await postJson(@json(url('/wastage-table/save')), {
            code: code,
            type: itemType.value,
            rows: rows
        });

        showMsg(data.message || 'Save failed.', data.success ? 'success' : 'error');
        if (data.success) {
            itemCode.value = '';
            tbody.innerHTML = '';
            itemCode.focus();
        }
    }

    function openItemHelp() {
        const code = prompt('Enter item code:');
        if (!code) {
            return;
        }
        itemCode.value = code.toUpperCase();
        itemCode.dispatchEvent(new Event('change'));
    }

    function focusNextFrom(currentInput) {
        const td = currentInput.closest('td');
        if (!td) {
            return;
        }
        const tr = td.closest('tr');
        if (!tr) {
            return;
        }

        const nextTd = td.nextElementSibling;
        if (nextTd) {
            nextTd.querySelector('input')?.focus();
            return;
        }

        const nextTr = tr.nextElementSibling;
        if (nextTr) {
            nextTr.querySelector('input[name="weight1"]')?.focus();
        } else {
            addRow();
        }
    }

    itemCode.addEventListener('change', async () => {
        const valid = await validateItemCode();
        if (valid) {
            showData();
        }
    });

    btnHelp.addEventListener('click', openItemHelp);
    btnShow.addEventListener('click', showData);
    btnAdd.addEventListener('click', addRow);
    btnDelete.addEventListener('click', deleteRow);
    btnSave.addEventListener('click', saveData);
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
        } else if (e.key === 'F9') {
            e.preventDefault();
            saveData();
        }
    });

    table.addEventListener('focusin', (e) => {
        if (e.target.tagName === 'INPUT') {
            e.target.select();
        }
    });
})();
</script>
</body>
</html>

