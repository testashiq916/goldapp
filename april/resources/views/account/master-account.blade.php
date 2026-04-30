<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Account Master</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1280px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 12px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .status { margin: 8px 0; padding: 8px 10px; border-radius: 7px; font-size: 12px; display: none; }
        .status.show { display: block; }
        .status.ok { border: 1px solid #9fd5af; background: #e6f6ec; color: #12522d; }
        .status.err { border: 1px solid #f2b1b1; background: #fdeaea; color: #8e1f1f; }
        .sec { border: 1px solid #d8e2ef; border-radius: 8px; padding: 10px; margin-bottom: 10px; background: #f8fbff; }
        .sec h3 { margin: 0 0 8px; font-size: 14px; color: #2d4f74; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap: 8px; }
        .grid2 { display: grid; grid-template-columns: repeat(2, minmax(200px, 1fr)); gap: 8px; }
        .grid3 { display: grid; grid-template-columns: repeat(3, minmax(160px, 1fr)); gap: 8px; }
        textarea { border: 1px solid #bfd0e6; border-radius: 6px; padding: 6px 8px; font-size: 12px; width: 100%; box-sizing: border-box; min-height: 60px; resize: vertical; font-family: inherit; }
        label { font-size: 12px; font-weight: 700; color: #375b84; display: block; margin-bottom: 3px; }
        input, select { height: 32px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; width: 100%; box-sizing: border-box; }
        input[type="radio"] { width: auto; height: auto; }
        .radio-group { display: flex; gap: 12px; align-items: center; height: 32px; font-size: 12px; }
        .radio-group label { display: flex; gap: 4px; align-items: center; margin: 0; font-weight: 600; }
        .chkgrid { display: grid; grid-template-columns: repeat(3, minmax(160px, 1fr)); gap: 4px 8px; }
        .chkgrid label { display: flex; gap: 6px; align-items: center; margin: 0; font-weight: 600; color: #1f3f66; }
        .chkgrid input[type="checkbox"] { width: 16px; height: 16px; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 6px; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        button.warn { border-color: #a23b3b; background: #fdeaea; color: #8e1f1f; }
        button.active { border-color: #2470b0; background: #dfefff; color: #0f3e67; }
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,.35); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .modal.show { display: flex; }
        .modal-card { width: 800px; background: #fff; border-radius: 10px; border: 1px solid #d7dfeb; padding: 12px; }
        .table-wrap { max-height: 360px; overflow: auto; border: 1px solid #d8e2ef; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 6px; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; z-index: 2; }
        #grpRows tr.active, #acListBody tr.active { background: #e9f3ff; }
        #acListBody tr:hover, #grpRows tr:hover { background: #f5faff; }
        .code-wrap { display: flex; gap: 6px; }
        .code-wrap input { flex: 1; }
        .code-wrap button { flex-shrink: 0; }
        @media (max-width: 1024px) { .grid { grid-template-columns: repeat(2, minmax(180px, 1fr)); } .chkgrid { grid-template-columns: repeat(2, minmax(160px, 1fr)); } }
    </style>
</head>
<body>
<div class="wrap">
    <h1 id="title">Account Master</h1>
    <div id="status" class="status"></div>

    <div class="sec">
        <h3>Account Details</h3>
        <div class="grid">
            <div>
                <label>Account Code</label>
                <div class="code-wrap">
                    <input id="accode" list="accodeList" maxlength="20">
                    <datalist id="accodeList"></datalist>
                    <button id="btnLoad" type="button">Load</button>
                </div>
            </div>
            <div>
                <label>Description</label>
                <input id="desc" maxlength="50">
            </div>
            <div>
                <label>Group</label>
                <select id="grcode">
                    <option value=""></option>
                </select>
            </div>
            <div>
                <label>BS Head</label>
                <select id="bshead">
                    <option value=""></option>
                </select>
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>Notes</label>
            <textarea id="note" maxlength="200" placeholder="Additional notes..."></textarea>
        </div>
    </div>

    <div class="sec">
        <h3>Opening Balance Configuration</h3>
        <div class="grid2">
            <div>
                <label>Opening Balance</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input id="opbal" type="number" step="0.01" min="0" value="0.00" style="flex:1;">
                    <div class="radio-group" style="flex-shrink:0;">
                        <label><input type="radio" name="opbal_type" value="D" checked> Debit</label>
                        <label><input type="radio" name="opbal_type" value="C"> Credit</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sec">
        <h3>Account Type &amp; Classification</h3>
        <div class="grid2">
            <div>
                <label>Account Type</label>
                <div class="radio-group">
                    <label><input type="radio" name="actype" value="R"> Revenue</label>
                    <label><input type="radio" name="actype" value="E"> Expense</label>
                    <label><input type="radio" name="actype" value="A" checked> Asset</label>
                    <label><input type="radio" name="actype" value="L"> Liability</label>
                </div>
            </div>
            <div>
                <label>Cash/Bank Classification</label>
                <div class="radio-group">
                    <label><input id="chkCash" type="checkbox" style="width:16px;height:16px;"> Cash Account</label>
                    <label><input id="chkBank" type="checkbox" style="width:16px;height:16px;"> Bank Account</label>
                </div>
            </div>
        </div>
    </div>

    <div class="sec">
        <h3>Positioning</h3>
        <div class="grid3">
            <div>
                <label>Trading P&amp;L Position</label>
                <input id="pos" type="number" value="0">
            </div>
            <div>
                <label>BS Schedule Position</label>
                <input id="shedpos" type="number" value="0">
            </div>
            <div>
                <label>Schedule Group</label>
                <select id="shedgrp">
                    <option value="">Select group</option>
                </select>
            </div>
        </div>
    </div>

    <div class="sec">
        <h3>Options</h3>
        <div class="chkgrid">
            <label><input id="chkDisplay" type="checkbox" checked> Display</label>
            <label><input id="chkHlp" type="checkbox"> Help (HLP)</label>
            <label><input id="chkSp" type="checkbox"> Special (SP)</label>
            <label><input id="chkReserve" type="checkbox"> Reserved</label>
            <label><input id="chkRemoved" type="checkbox"> Removed</label>
            <label><input id="chkBlocked" type="checkbox"> Blocked</label>
        </div>
    </div>

    <div class="toolbar">
        <button id="btnList" type="button">List</button>
        <button id="btnSave" type="button" class="primary">Save (F9)</button>
        <button id="btnClear" type="button">Clear</button>
        <button id="btnGrpHelp" type="button">Group Help</button>
        <button id="btnNewGrp" type="button">New Group</button>
        <button id="btnNewSubGrp" type="button">New Subgroup</button>
        <button id="btnExit" type="button">Exit (Esc)</button>
    </div>
</div>

<div id="newGrpModal" class="modal">
    <div class="modal-card" style="width:500px;">
        <div style="display:flex;align-items:center;margin-bottom:10px;">
            <strong style="flex:1;">Create New Group</strong>
            <button id="newGrpClose" type="button">Close</button>
        </div>
        <div id="newGrpStatus" class="status" style="margin-bottom:8px;"></div>
        <div style="display:grid;grid-template-columns:120px 1fr;gap:8px 12px;align-items:center;">
            <label>Group Code</label>
            <input id="newGrpCode" maxlength="10">
            <label>Name</label>
            <input id="newGrpName" maxlength="50">
            <label>Account Type</label>
            <div class="radio-group">
                <label><input type="radio" name="newGrpType" value="R"> Revenue</label>
                <label><input type="radio" name="newGrpType" value="E"> Expense</label>
                <label><input type="radio" name="newGrpType" value="A" checked> Asset</label>
                <label><input type="radio" name="newGrpType" value="L"> Liability</label>
            </div>
            <label>Position</label>
            <input id="newGrpPos" type="number" step="1" value="0">
        </div>
        <div style="margin-top:10px;text-align:right;">
            <button id="newGrpSave" type="button" class="primary">Save Group</button>
        </div>
    </div>
</div>

<div id="newSubGrpModal" class="modal">
    <div class="modal-card" style="width:500px;">
        <div style="display:flex;align-items:center;margin-bottom:10px;">
            <strong style="flex:1;">Create New Subgroup</strong>
            <button id="newSubGrpClose" type="button">Close</button>
        </div>
        <div id="newSubGrpStatus" class="status" style="margin-bottom:8px;"></div>
        <div style="display:grid;grid-template-columns:120px 1fr;gap:8px 12px;align-items:center;">
            <label>Subgroup Code</label>
            <input id="newSubGrpCode" maxlength="10">
            <label>Name</label>
            <input id="newSubGrpName" maxlength="50">
            <label>Parent Group</label>
            <select id="newSubGrpParent"></select>
            <label>Account Type</label>
            <div class="radio-group">
                <label><input type="radio" name="newSubGrpType" value="R"> Revenue</label>
                <label><input type="radio" name="newSubGrpType" value="E"> Expense</label>
                <label><input type="radio" name="newSubGrpType" value="A" checked> Asset</label>
                <label><input type="radio" name="newSubGrpType" value="L"> Liability</label>
            </div>
            <label>Position</label>
            <input id="newSubGrpPos" type="number" step="1" value="0">
        </div>
        <div style="margin-top:10px;text-align:right;">
            <button id="newSubGrpSave" type="button" class="primary">Save Subgroup</button>
        </div>
    </div>
</div>

<div id="grpModal" class="modal">
    <div class="modal-card">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
            <strong style="flex:1;">Select Group</strong>
            <input id="grpSearch" placeholder="Search code / name..." style="width:260px;">
            <button id="grpClose" type="button">Close</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th style="width:120px;">Code</th><th>Name</th><th style="width:80px;">Type</th></tr></thead>
                <tbody id="grpRows"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="acListModal" class="modal">
    <div class="modal-card">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
            <strong style="flex:1;">Accounts List</strong>
            <input id="acListSearch" placeholder="Search code / name..." style="width:260px;">
            <button id="acListClose" type="button">Close</button>
        </div>
        <div id="acListStatus" class="status" style="margin-bottom:8px;"></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th style="width:120px;">Code</th><th>Name</th><th style="width:100px;">Group</th><th style="width:80px;">Type</th><th style="width:120px;">Actions</th></tr></thead>
                <tbody id="acListBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const statusEl = document.getElementById('status');
    const acListStatusEl = document.getElementById('acListStatus');
    const BASE = @json(url('/api/accounts/master/account'));

    let mode = 'A';
    let originalAccode = '';
    let allCodes = [];
    let codesLoaded = false;

    function status(msg, ok) {
        statusEl.textContent = msg;
        statusEl.className = 'status show ' + (ok ? 'ok' : 'err');
    }

    function clearStatus() {
        statusEl.className = 'status';
        statusEl.textContent = '';
    }

    function acListStatus(msg, ok) {
        acListStatusEl.textContent = msg;
        acListStatusEl.className = 'status show ' + (ok ? 'ok' : 'err');
    }

    function clearAcListStatus() {
        acListStatusEl.className = 'status';
        acListStatusEl.textContent = '';
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
        if (data.success === false) throw new Error(data.error || data.message || 'Request failed');
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
        document.getElementById('accode').value = '';
        document.getElementById('desc').value = '';
        document.getElementById('grcode').value = '';
        document.getElementById('bshead').value = '';
        document.getElementById('note').value = '';
        setRadio('actype', 'A');
        document.getElementById('opbal').value = '0.00';
        setRadio('opbal_type', 'D');
        document.getElementById('chkCash').checked = false;
        document.getElementById('chkBank').checked = false;
        document.getElementById('pos').value = '0';
        document.getElementById('shedpos').value = '0';
        document.getElementById('shedgrp').value = '';
        document.getElementById('chkDisplay').checked = true;
        document.getElementById('chkHlp').checked = false;
        document.getElementById('chkSp').checked = false;
        document.getElementById('chkReserve').checked = false;
        document.getElementById('chkRemoved').checked = false;
        document.getElementById('chkBlocked').checked = false;
        document.getElementById('accode').disabled = false;
        originalAccode = '';
        clearStatus();
    }

    function setMode(m) {
        mode = m;
        if (m === 'A') {
            document.getElementById('title').textContent = 'Account Master - Add';
            document.getElementById('accode').disabled = false;
        } else if (m === 'E') {
            document.getElementById('title').textContent = 'Account Master - Edit';
        } else if (m === 'D') {
            document.getElementById('title').textContent = 'Account Master - Delete';
        }
    }

    function collectPayload() {
        const cashChk = document.getElementById('chkCash').checked;
        const bankChk = document.getElementById('chkBank').checked;
        return {
            accode: (document.getElementById('accode').value || '').toUpperCase().trim(),
            original_accode: originalAccode,
            desc: (document.getElementById('desc').value || '').toUpperCase().trim(),
            grcode: document.getElementById('grcode').value,
            bshead: document.getElementById('bshead').value,
            note: document.getElementById('note').value.trim(),
            actype1: getRadio('actype'),
            balance: Number(parseFloat(document.getElementById('opbal').value || '0').toFixed(2)),
            balance_type: getRadio('opbal_type') === 'D' ? 'debit' : 'credit',
            actype2: cashChk ? 'H' : (bankChk ? 'B' : ''),
            pos: Number(document.getElementById('pos').value || 0),
            shedpos: Number(document.getElementById('shedpos').value || 0),
            shedgrp: document.getElementById('shedgrp').value,
            display: document.getElementById('chkDisplay').checked,
            hlp: document.getElementById('chkHlp').checked,
            sp: document.getElementById('chkSp').checked,
            reserve: document.getElementById('chkReserve').checked,
            removed: document.getElementById('chkRemoved').checked,
            blocked: document.getElementById('chkBlocked').checked
        };
    }

    function applyAccount(item) {
        document.getElementById('accode').value = item.accode || '';
        originalAccode = item.accode || '';
        document.getElementById('desc').value = item.desc || '';
        document.getElementById('grcode').value = item.grcode || '';
        document.getElementById('bshead').value = item.bshead || '';
        document.getElementById('note').value = item.note || '';

        // Account type from _checked booleans
        if (item.revenue_checked) setRadio('actype', 'R');
        else if (item.expense_checked) setRadio('actype', 'E');
        else if (item.liability_checked) setRadio('actype', 'L');
        else setRadio('actype', 'A');

        // Balance A (API returns abs value + debit_checked boolean)
        document.getElementById('opbal').value = Number(item.balance || 0).toFixed(2);
        setRadio('opbal_type', item.debit_checked ? 'D' : 'C');

        // Cash/Bank
        document.getElementById('chkCash').checked = !!item.cash_checked;
        document.getElementById('chkBank').checked = !!item.bank_checked;

        // Positioning
        document.getElementById('pos').value = Number(item.pos || 0);
        document.getElementById('shedpos').value = Number(item.shedpos || 0);
        document.getElementById('shedgrp').value = item.shedgrp || '';

        // Options (API returns _checked booleans)
        document.getElementById('chkDisplay').checked = item.display_checked !== false;
        document.getElementById('chkHlp').checked = !!item.hlp_checked;
        document.getElementById('chkSp').checked = !!item.sp_checked;
        document.getElementById('chkReserve').checked = !!item.reserve_checked;
        document.getElementById('chkRemoved').checked = !!item.removed_checked;
        document.getElementById('chkBlocked').checked = !!item.blocked_checked;
    }

    async function loadDropdowns() {
        try {
            const [grpData, bsData] = await Promise.all([
                api({ action: 'list', type: 'groups' }),
                api({ action: 'list', type: 'bs_heads' })
            ]);

            const grSel = document.getElementById('grcode');
            grSel.innerHTML = '<option value=""></option>';
            const shedSel = document.getElementById('shedgrp');
            shedSel.innerHTML = '<option value="">Select group</option>';
            (grpData.data || []).forEach(r => {
                const code = r.grcode || r.code || '';
                const name = (r.name || r.grname || '');
                const o = document.createElement('option');
                o.value = code;
                o.textContent = code + ' - ' + name;
                grSel.appendChild(o);
                const o2 = document.createElement('option');
                o2.value = code;
                o2.textContent = code + ' - ' + name;
                shedSel.appendChild(o2);
            });

            const bsSel = document.getElementById('bshead');
            bsSel.innerHTML = '<option value=""></option>';
            (bsData.data || []).forEach(r => {
                const o = document.createElement('option');
                o.value = r.hcode || r.code || '';
                o.textContent = (r.hcode || r.code || '') + ' - ' + (r.hname || r.name || '');
                bsSel.appendChild(o);
            });
        } catch (e) {
            status('Failed to load dropdowns: ' + e.message, false);
        }
    }

    // Cash/Bank mutual exclusion
    document.getElementById('chkCash').addEventListener('change', function() {
        if (this.checked) document.getElementById('chkBank').checked = false;
    });
    document.getElementById('chkBank').addEventListener('change', function() {
        if (this.checked) document.getElementById('chkCash').checked = false;
    });

    async function loadCodesList() {
        try {
            const d = await api({ action: 'list_codes' });
            allCodes = d.data || [];
            const dl = document.getElementById('accodeList');
            dl.innerHTML = '';
            allCodes.forEach(r => {
                const opt = document.createElement('option');
                opt.value = r.accode || r.code || '';
                opt.textContent = r.desc || r.acname || '';
                dl.appendChild(opt);
            });
        } catch (e) { /* silent */ }
    }

    async function loadAccount() {
        const code = (document.getElementById('accode').value || '').toUpperCase().trim();
        if (!code) { status('Enter account code to load', false); return; }
        try {
            const d = await api({ action: 'load', accode: code });
            if (d.data) {
                if (mode === 'A') {
                    setMode('E');
                }
                applyAccount(d.data);
                if (mode === 'E') document.getElementById('accode').disabled = true;
                status('Account loaded: ' + code, true);
            } else {
                status('Account not found: ' + code, false);
            }
        } catch (e) { status(e.message, false); }
    }

    async function doSave() {
        const p = collectPayload();
        if (!p.accode) { status('Account code is required', false); return; }
        if (!p.desc) { status('Description is required', false); return; }

        if (mode === 'D') {
            if (!confirm('Are you sure you want to delete account ' + p.accode + '?')) return;
            try {
                const d = await api({ action: 'delete', accode: p.accode });
                status(d.message || 'Account deleted', true);
                resetForm();
                setMode('A');
                if (codesLoaded) loadCodesList();
            } catch (e) { status(e.message, false); }
            return;
        }

        try {
            if (mode === 'A') {
                try {
                    await api({ action: 'validate', accode: p.accode, mode: 'A' });
                } catch (err) {
                    if ((err.message || '').toLowerCase().includes('already exists')) {
                        setMode('E');
                        originalAccode = p.accode;
                        document.getElementById('accode').disabled = true;
                        const d = await api({ action: 'save', mode: 'E', ...p, original_accode: p.accode });
                        status(d.message || 'Account updated successfully', true);
                        resetForm();
                        setMode('A');
                        if (codesLoaded) loadCodesList();
                        return;
                    }
                    throw err;
                }
            }
            const d = await api({ action: 'save', mode: mode, ...p });
            status(d.message || 'Account saved successfully', true);
            resetForm();
            setMode('A');
            if (codesLoaded) loadCodesList();
        } catch (e) { status(e.message, false); }
    }

    async function loadGroupHelp(search) {
        try {
            const d = await api({ action: 'list', type: 'groups', search: search || '' });
            const body = document.getElementById('grpRows');
            body.innerHTML = '';
            (d.data || []).forEach(r => {
                const tr = document.createElement('tr');
                tr.dataset.code = r.grcode || r.code || '';
                tr.innerHTML = `<td>${escapeHtml(r.grcode || r.code || '')}</td><td>${escapeHtml(r.name || r.grname || '')}</td><td>${escapeHtml(r.actype || '')}</td>`;
                body.appendChild(tr);
            });
        } catch (e) { status(e.message, false); }
    }

    // Load button
    document.getElementById('btnLoad').addEventListener('click', () => loadAccount());

    // Save button
    document.getElementById('btnSave').addEventListener('click', () => doSave());

    // Clear
    document.getElementById('btnClear').addEventListener('click', () => { resetForm(); setMode(mode); });

    // Exit
    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else {
            window.close();
        }
    });

    // Group help modal
    document.getElementById('btnGrpHelp').addEventListener('click', async () => {
        await loadGroupHelp('');
        document.getElementById('grpModal').classList.add('show');
        document.getElementById('grpSearch').value = '';
        document.getElementById('grpSearch').focus();
    });

    document.getElementById('grpClose').addEventListener('click', () => document.getElementById('grpModal').classList.remove('show'));

    document.getElementById('grpSearch').addEventListener('input', () => {
        loadGroupHelp(document.getElementById('grpSearch').value);
    });

    document.getElementById('grpRows').addEventListener('click', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        document.querySelectorAll('#grpRows tr').forEach(r => r.classList.remove('active'));
        tr.classList.add('active');
    });

    document.getElementById('grpRows').addEventListener('dblclick', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        document.getElementById('grcode').value = tr.dataset.code || '';
        document.getElementById('grpModal').classList.remove('show');
    });

    // Uppercase input for accode
    document.getElementById('accode').addEventListener('input', () => {
        document.getElementById('accode').value = document.getElementById('accode').value.toUpperCase();
    });

    // Enter on accode triggers load
    document.getElementById('accode').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadAccount();
        }
    });

    // ── Account List ──
    const GRPBASE = @json(url('/api/accounts/master/groups'));

    async function grpApi(payload) {
        const res = await fetch(GRPBASE, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (e) { throw new Error('Invalid server response'); }
        if (!res.ok) throw new Error(data.message || data.error || 'Request failed');
        if (data.success === false) throw new Error(data.error || data.message || 'Request failed');
        return data;
    }

    const typeMap = { R: 'Revenue', E: 'Expense', A: 'Asset', L: 'Liability' };

    async function loadAccountList(search) {
        try {
            const d = await api({ action: 'list_all', search: search || '', limit: search ? 300 : 120 });
            const body = document.getElementById('acListBody');
            body.innerHTML = '';
            (d.data || []).forEach(r => {
                const tr = document.createElement('tr');
                const code = String(r.accode || '');
                tr.dataset.code = code;
                const t1 = (r.actype1 || '').toUpperCase();

                const codeTd = document.createElement('td');
                codeTd.textContent = code;

                const nameTd = document.createElement('td');
                nameTd.textContent = String(r.name || '');

                const groupTd = document.createElement('td');
                groupTd.textContent = String(r.grcode || '');

                const typeTd = document.createElement('td');
                typeTd.textContent = String(typeMap[t1] || t1);

                const actionsTd = document.createElement('td');
                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.textContent = 'Edit';
                editBtn.addEventListener('click', (ev) => {
                    ev.stopPropagation();
                    chooseAccountFromList(code, 'E');
                });

                const spacer = document.createTextNode(' ');

                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'warn';
                deleteBtn.textContent = 'Delete';
                deleteBtn.addEventListener('click', async (ev) => {
                    ev.stopPropagation();
                    await deleteAccountFromList(code);
                });

                actionsTd.appendChild(editBtn);
                actionsTd.appendChild(spacer);
                actionsTd.appendChild(deleteBtn);

                tr.appendChild(codeTd);
                tr.appendChild(nameTd);
                tr.appendChild(groupTd);
                tr.appendChild(typeTd);
                tr.appendChild(actionsTd);
                body.appendChild(tr);
            });
        } catch (e) { status(e.message, false); }
    }

    async function openAccountList() {
        document.getElementById('acListModal').classList.add('show');
        document.getElementById('acListSearch').value = '';
        clearAcListStatus();
        await loadAccountList('');
        document.getElementById('acListSearch').focus();
    }

    function chooseAccountFromList(code, targetMode = mode) {
        document.getElementById('accode').value = code || '';
        setMode(targetMode);
        document.getElementById('acListModal').classList.remove('show');
        loadAccount();
    }

    async function deleteAccountFromList(code) {
        if (!code) return;
        clearAcListStatus();
        if (!confirm('Do you want delete?')) return;
        acListStatus('Deleting...', true);
        try {
            await api({ action: 'delete', accode: code });
            resetForm();
            setMode('A');
            if (codesLoaded) loadCodesList();
            await loadAccountList(document.getElementById('acListSearch').value.trim());
            const stillExists = !!document.querySelector(`#acListBody tr[data-code="${CSS.escape(String(code))}"]`);
            if (stillExists) {
                status('Delete did not remove the row from list', false);
                acListStatus('Delete did not remove the row from list', false);
                return;
            }
            status('Account deleted successfully', true);
            acListStatus('Account deleted successfully', true);
        } catch (err) {
            status(err.message, false);
            acListStatus(err.message || 'Delete failed', false);
        }
    }

    let searchTimer = null;
    document.getElementById('acListSearch').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadAccountList(document.getElementById('acListSearch').value.trim()), 250);
    });

    document.getElementById('btnList').addEventListener('click', () => {
        openAccountList();
    });

    document.getElementById('acListClose').addEventListener('click', () => {
        document.getElementById('acListModal').classList.remove('show');
        clearAcListStatus();
    });

    document.getElementById('acListBody').addEventListener('click', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        document.querySelectorAll('#acListBody tr').forEach(r => r.classList.remove('active'));
        tr.classList.add('active');
    });

    // ── New Group Modal ──
    function newGrpStatus(msg, ok) {
        const el = document.getElementById('newGrpStatus');
        el.textContent = msg;
        el.className = 'status show ' + (ok ? 'ok' : 'err');
    }

    document.getElementById('btnNewGrp').addEventListener('click', () => {
        document.getElementById('newGrpCode').value = '';
        document.getElementById('newGrpName').value = '';
        document.getElementById('newGrpPos').value = '0';
        document.querySelector('input[name="newGrpType"][value="A"]').checked = true;
        document.getElementById('newGrpStatus').className = 'status';
        document.getElementById('newGrpModal').classList.add('show');
        document.getElementById('newGrpCode').focus();
    });

    document.getElementById('newGrpClose').addEventListener('click', () => {
        document.getElementById('newGrpModal').classList.remove('show');
    });

    document.getElementById('newGrpCode').addEventListener('input', () => {
        document.getElementById('newGrpCode').value = document.getElementById('newGrpCode').value.toUpperCase();
    });

    document.getElementById('newGrpSave').addEventListener('click', async () => {
        const code = document.getElementById('newGrpCode').value.trim().toUpperCase();
        const name = document.getElementById('newGrpName').value.trim().toUpperCase();
        const actype = (document.querySelector('input[name="newGrpType"]:checked') || {}).value || 'A';
        const pos = Number(document.getElementById('newGrpPos').value || 0);
        if (!code) { newGrpStatus('Group code is required', false); return; }
        if (!name) { newGrpStatus('Group name is required', false); return; }
        try {
            await grpApi({ action: 'save', mode: 'A', grcode: code, name: name, actype1: actype, pos: pos, grp: '', mgrp: 'MGRP', reserve: 'N' });
            newGrpStatus('Group created: ' + code, true);
            await loadDropdowns();
            setTimeout(() => document.getElementById('newGrpModal').classList.remove('show'), 600);
        } catch (e) { newGrpStatus(e.message, false); }
    });

    // ── New Subgroup Modal ──
    function newSubGrpStatus(msg, ok) {
        const el = document.getElementById('newSubGrpStatus');
        el.textContent = msg;
        el.className = 'status show ' + (ok ? 'ok' : 'err');
    }

    async function loadSubGrpParentDropdown() {
        try {
            const d = await grpApi({ action: 'list' });
            const sel = document.getElementById('newSubGrpParent');
            sel.innerHTML = '<option value="">Select parent group</option>';
            (d.data || []).forEach(r => {
                const o = document.createElement('option');
                o.value = r.grcode || r.code || '';
                o.textContent = (r.grcode || r.code || '') + ' - ' + (r.name || '');
                sel.appendChild(o);
            });
        } catch (e) { /* silent */ }
    }

    document.getElementById('btnNewSubGrp').addEventListener('click', async () => {
        document.getElementById('newSubGrpCode').value = '';
        document.getElementById('newSubGrpName').value = '';
        document.getElementById('newSubGrpPos').value = '0';
        document.querySelector('input[name="newSubGrpType"][value="A"]').checked = true;
        document.getElementById('newSubGrpStatus').className = 'status';
        await loadSubGrpParentDropdown();
        document.getElementById('newSubGrpModal').classList.add('show');
        document.getElementById('newSubGrpCode').focus();
    });

    document.getElementById('newSubGrpClose').addEventListener('click', () => {
        document.getElementById('newSubGrpModal').classList.remove('show');
    });

    document.getElementById('newSubGrpCode').addEventListener('input', () => {
        document.getElementById('newSubGrpCode').value = document.getElementById('newSubGrpCode').value.toUpperCase();
    });

    document.getElementById('newSubGrpSave').addEventListener('click', async () => {
        const code = document.getElementById('newSubGrpCode').value.trim().toUpperCase();
        const name = document.getElementById('newSubGrpName').value.trim().toUpperCase();
        const parent = document.getElementById('newSubGrpParent').value.trim();
        const actype = (document.querySelector('input[name="newSubGrpType"]:checked') || {}).value || 'A';
        const pos = Number(document.getElementById('newSubGrpPos').value || 0);
        if (!code) { newSubGrpStatus('Subgroup code is required', false); return; }
        if (!name) { newSubGrpStatus('Subgroup name is required', false); return; }
        if (!parent) { newSubGrpStatus('Parent group is required', false); return; }
        try {
            await grpApi({ action: 'save', mode: 'A', grcode: code, name: name, actype1: actype, pos: pos, grp: parent, mgrp: 'MGRP', reserve: 'N' });
            newSubGrpStatus('Subgroup created: ' + code, true);
            await loadDropdowns();
            setTimeout(() => document.getElementById('newSubGrpModal').classList.remove('show'), 600);
        } catch (e) { newSubGrpStatus(e.message, false); }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') {
            e.preventDefault();
            doSave();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            const modals = ['newGrpModal', 'newSubGrpModal', 'grpModal'];
            for (const id of modals) {
                const m = document.getElementById(id);
                if (m.classList.contains('show')) { m.classList.remove('show'); return; }
            }
            document.getElementById('btnExit').click();
        }
    });

    // Lazy-load codes on first focus of accode field (avoids blocking page init)
    document.getElementById('accode').addEventListener('focus', () => {
        if (!codesLoaded) { codesLoaded = true; loadCodesList(); }
    });

    // Init — only load dropdowns upfront; list + codes load on demand
    loadDropdowns().then(() => { resetForm(); setMode('A'); });
})();
</script>
</body>
</html>
