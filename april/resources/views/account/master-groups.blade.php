<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Account Groups</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 900px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 12px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .status { margin: 8px 0; padding: 8px 10px; border-radius: 7px; font-size: 12px; display: none; }
        .status.show { display: block; }
        .status.ok { border: 1px solid #9fd5af; background: #e6f6ec; color: #12522d; }
        .status.err { border: 1px solid #f2b1b1; background: #fdeaea; color: #8e1f1f; }
        .sec { border: 1px solid #d8e2ef; border-radius: 8px; padding: 10px; margin-bottom: 10px; background: #f8fbff; }
        .sec h3 { margin: 0 0 8px; font-size: 14px; color: #2d4f74; }
        .grid { display: grid; grid-template-columns: 130px 1fr; gap: 8px 12px; align-items: center; max-width: 600px; }
        label { font-size: 12px; font-weight: 700; color: #375b84; }
        input, select { height: 32px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; width: 100%; box-sizing: border-box; }
        input[type="radio"], input[type="checkbox"] { width: auto; height: auto; }
        .radio-group { display: flex; gap: 12px; align-items: center; height: 32px; font-size: 12px; }
        .radio-group label { display: flex; gap: 4px; align-items: center; margin: 0; font-weight: 600; }
        .chk-line { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #1f3f66; height: 32px; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 6px; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        button.warn { border-color: #a23b3b; background: #fdeaea; color: #8e1f1f; }
        button.active { border-color: #2470b0; background: #dfefff; color: #0f3e67; }
        .list-wrap { max-height: 300px; overflow: auto; border: 1px solid #d8e2ef; border-radius: 8px; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 6px; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; z-index: 2; }
        tbody tr { cursor: pointer; }
        tbody tr:hover { background: #f5faff; }
        tbody tr.active { background: #e9f3ff; }
    </style>
</head>
<body>
<div class="wrap">
    <h1 id="title">Account Groups</h1>
    <div id="status" class="status"></div>

    <div class="sec">
        <h3>Group Details</h3>
        <div class="grid">
            <label>Group Code</label>
            <input id="grcode" maxlength="10">

            <label>Name</label>
            <input id="grname" maxlength="50">

            <label>Account Type</label>
            <div class="radio-group">
                <label><input type="radio" name="actype" value="R"> Revenue</label>
                <label><input type="radio" name="actype" value="E"> Expense</label>
                <label><input type="radio" name="actype" value="A" checked> Asset</label>
                <label><input type="radio" name="actype" value="L"> Liability</label>
            </div>

            <label>Position</label>
            <input id="pos" type="number" step="1" value="0">

            <label>Group</label>
            <input id="grp" maxlength="10">

            <label>Main Group</label>
            <input id="mgrp" maxlength="10">

            <label>Reserved</label>
            <div class="chk-line">
                <input id="chkReserve" type="checkbox"> Mark as reserved
            </div>
        </div>
    </div>

    <div class="toolbar">
        <button id="btnAdd" type="button" class="active">Add</button>
        <button id="btnEdit" type="button">Edit</button>
        <button id="btnDelete" type="button" class="warn">Delete</button>
        <button id="btnSave" type="button" class="primary">Save (F9)</button>
        <button id="btnClear" type="button">Clear</button>
        <button id="btnExit" type="button">Exit (Esc)</button>
    </div>

    <div class="sec" style="margin-top: 10px;">
        <h3>Existing Groups</h3>
        <div class="list-wrap">
            <table>
                <thead><tr><th style="width:100px;">Code</th><th>Name</th><th style="width:60px;">Type</th><th style="width:50px;">Pos</th></tr></thead>
                <tbody id="listBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const statusEl = document.getElementById('status');
    const BASE = @json(url('/api/accounts/master/groups'));

    let mode = 'A';

    function status(msg, ok) {
        statusEl.textContent = msg;
        statusEl.className = 'status show ' + (ok ? 'ok' : 'err');
    }

    async function api(payload) {
        const res = await fetch(BASE, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (e) { throw new Error('Invalid server response'); }
        if (!res.ok) throw new Error(data.message || 'Request failed');
        return data;
    }

    function escapeHtml(v) {
        return String(v).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
    }

    function getRadio(name) {
        const el = document.querySelector(`input[name="${name}"]:checked`);
        return el ? el.value : '';
    }

    function setRadio(name, val) {
        const el = document.querySelector(`input[name="${name}"][value="${val}"]`);
        if (el) el.checked = true;
    }

    function resetForm() {
        document.getElementById('grcode').value = '';
        document.getElementById('grname').value = '';
        setRadio('actype', 'A');
        document.getElementById('pos').value = '0';
        document.getElementById('grp').value = '';
        document.getElementById('mgrp').value = '';
        document.getElementById('chkReserve').checked = false;
        document.getElementById('grcode').disabled = false;
    }

    function setMode(m) {
        mode = m;
        document.querySelectorAll('#btnAdd,#btnEdit,#btnDelete').forEach(b => b.classList.remove('active'));
        if (m === 'A') {
            document.getElementById('btnAdd').classList.add('active');
            document.getElementById('title').textContent = 'Account Groups - Add';
            document.getElementById('grcode').disabled = false;
        } else if (m === 'E') {
            document.getElementById('btnEdit').classList.add('active');
            document.getElementById('title').textContent = 'Account Groups - Edit';
        } else if (m === 'D') {
            document.getElementById('btnDelete').classList.add('active');
            document.getElementById('title').textContent = 'Account Groups - Delete';
        }
    }

    function collectPayload() {
        return {
            grcode: (document.getElementById('grcode').value || '').toUpperCase().trim(),
            name: (document.getElementById('grname').value || '').toUpperCase().trim(),
            actype: getRadio('actype'),
            pos: Number(document.getElementById('pos').value || 0),
            grp: (document.getElementById('grp').value || '').toUpperCase().trim(),
            mgrp: (document.getElementById('mgrp').value || '').toUpperCase().trim(),
            reserve: document.getElementById('chkReserve').checked ? 'Y' : 'N'
        };
    }

    function applyGroup(item) {
        document.getElementById('grcode').value = item.grcode || item.code || '';
        document.getElementById('grname').value = item.name || item.grname || '';
        setRadio('actype', item.actype || 'A');
        document.getElementById('pos').value = item.pos || 0;
        document.getElementById('grp').value = item.grp || '';
        document.getElementById('mgrp').value = item.mgrp || '';
        document.getElementById('chkReserve').checked = String(item.reserve || 'N').toUpperCase() === 'Y';
    }

    async function loadList() {
        try {
            const d = await api({ action: 'list' });
            const body = document.getElementById('listBody');
            body.innerHTML = '';
            (d.data || []).forEach(r => {
                const tr = document.createElement('tr');
                tr.dataset.code = r.grcode || r.code || '';
                tr.innerHTML = `<td>${escapeHtml(r.grcode || r.code || '')}</td><td>${escapeHtml(r.name || r.grname || '')}</td><td>${escapeHtml(r.actype || '')}</td><td>${escapeHtml(r.pos || '')}</td>`;
                body.appendChild(tr);
            });
        } catch (e) { status(e.message, false); }
    }

    async function loadGroup(code) {
        try {
            const d = await api({ action: 'load', grcode: code });
            if (d.data) {
                applyGroup(d.data);
                if (mode === 'E' || mode === 'D') document.getElementById('grcode').disabled = true;
                status('Group loaded: ' + code, true);
            } else {
                status('Group not found', false);
            }
        } catch (e) { status(e.message, false); }
    }

    async function doSave() {
        const p = collectPayload();
        if (!p.grcode) { status('Group code is required', false); return; }
        if (!p.name && mode !== 'D') { status('Group name is required', false); return; }

        if (mode === 'D') {
            if (!confirm('Are you sure you want to delete group ' + p.grcode + '?')) return;
            try {
                const d = await api({ action: 'delete', grcode: p.grcode });
                status(d.message || 'Group deleted', true);
                resetForm(); setMode('A'); await loadList();
            } catch (e) { status(e.message, false); }
            return;
        }

        try {
            if (mode === 'A') {
                const v = await api({ action: 'validate', grcode: p.grcode });
                if (v.exists) { status('Group code already exists', false); return; }
            }
            const d = await api({ action: 'save', mode: mode, ...p });
            status(d.message || 'Group saved', true);
            resetForm(); setMode('A'); await loadList();
        } catch (e) { status(e.message, false); }
    }

    // List click
    document.getElementById('listBody').addEventListener('click', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        document.querySelectorAll('#listBody tr').forEach(r => r.classList.remove('active'));
        tr.classList.add('active');
    });

    document.getElementById('listBody').addEventListener('dblclick', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        if (mode === 'A') setMode('E');
        loadGroup(tr.dataset.code);
    });

    // Mode buttons
    document.getElementById('btnAdd').addEventListener('click', () => { resetForm(); setMode('A'); document.getElementById('grcode').focus(); });
    document.getElementById('btnEdit').addEventListener('click', () => { resetForm(); setMode('E'); document.getElementById('grcode').focus(); });
    document.getElementById('btnDelete').addEventListener('click', () => { resetForm(); setMode('D'); document.getElementById('grcode').focus(); });
    document.getElementById('btnSave').addEventListener('click', () => doSave());
    document.getElementById('btnClear').addEventListener('click', () => { resetForm(); setMode(mode); });

    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else { window.close(); }
    });

    // Uppercase
    document.getElementById('grcode').addEventListener('input', () => {
        document.getElementById('grcode').value = document.getElementById('grcode').value.toUpperCase();
    });

    // Enter on grcode loads
    document.getElementById('grcode').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); loadGroup(document.getElementById('grcode').value.trim().toUpperCase()); }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') { e.preventDefault(); doSave(); }
        else if (e.key === 'Escape') { e.preventDefault(); document.getElementById('btnExit').click(); }
    });

    // Init
    loadList().then(() => setMode('A'));
})();
</script>
</body>
</html>
