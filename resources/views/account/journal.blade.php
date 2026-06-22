<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Journal</title>
    <style>
        :root{
            --bg:#ebf2fb; --card:#ffffff; --line:#c9d7ea; --line2:#b8cce5; --text:#1f354d; --muted:#5d7592;
            --h1:#155f95; --h2:#1d7fbe; --ok:#157a42; --err:#a43131;
        }
        body{
            margin:0; font-family:"Segoe UI",Tahoma,Arial,sans-serif; color:var(--text);
            background:
                radial-gradient(circle at 12% -4%, #f4f8ff 0, transparent 34%),
                radial-gradient(circle at 100% -5%, #e8f1ff 0, transparent 33%),
                linear-gradient(180deg,#edf4fd,#e6eef9);
            padding:14px 10px; font-size:12px;
        }
        .win{
            width:min(1100px, calc(100vw - 24px)); margin:0 auto; background:var(--card);
            border:1px solid var(--line); border-radius:12px; overflow:hidden;
            box-shadow:0 16px 36px rgba(11,31,56,.14);
        }
        .head{
            margin:0; padding:14px 16px; color:#fff; font-size:30px; font-weight:800;
            background:linear-gradient(102deg,var(--h1),var(--h2));
            letter-spacing:.2px;
        }
        .panel{ padding:12px 14px; background:linear-gradient(180deg,#fbfdff,#f5f9ff); }
        .top{ display:grid; grid-template-columns: 130px 1fr 80px 180px auto auto auto; gap:8px; align-items:center; }
        .lbl{ text-align:right; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; }
        input,textarea{
            height:32px; border:1px solid var(--line2); border-radius:8px; padding:0 8px; font-size:13px;
            font-family:"Segoe UI",Tahoma,Arial,sans-serif; color:#1a3047; background:#fff;
        }
        input:focus,textarea:focus{ outline:none; border-color:#4f96da; box-shadow:0 0 0 3px rgba(79,150,218,.15); }
        .readonly{ background:#ecf2fa; color:#45607f; }
        .checks{ display:flex; align-items:center; gap:12px; justify-content:flex-end; }
        .checks label{ display:inline-flex; gap:6px; align-items:center; font-weight:700; color:#304c69; }
        .gridbox{ margin-top:10px; border:1px solid #d7e3f3; border-radius:10px; overflow:hidden; background:#fff; }
        table{ width:100%; border-collapse:collapse; }
        thead th{
            position:sticky; top:0; z-index:2; padding:8px 8px; background:#eaf2fc; color:#2c4f74; font-size:12px; text-align:left;
            border-bottom:1px solid #d4e1f1;
        }
        tbody td{ border-bottom:1px solid #edf2fb; padding:4px 6px; }
        .cell{ width:100%; box-sizing:border-box; height:30px; }
        .name-cell{ background:#f4f8ff; color:#1a3a5c; font-weight:600; cursor:default; }
        .name-cell.not-found{ color:#c53030; font-style:italic; }
        .num{ text-align:right; }
        .toolbar{ margin-top:8px; display:flex; gap:8px; }
        .toolbar .small{ min-width:82px; }
        .balancebox{
            margin-top:8px; display:grid; grid-template-columns:1.5fr 1fr 1fr; gap:8px;
            border:1px solid #d7e3f3; border-radius:10px; background:linear-gradient(180deg,#f8fbff,#eef5ff); padding:10px 12px;
        }
        .metric{
            min-height:50px; border:1px solid #d7e3f3; border-radius:8px; background:#fff; padding:8px 10px;
        }
        .metric .cap{ font-size:11px; font-weight:700; color:#67809b; text-transform:uppercase; letter-spacing:.35px; }
        .metric .val{ margin-top:4px; font-size:16px; font-weight:800; color:#213c5c; }
        .totals{ margin-top:8px; display:flex; gap:20px; justify-content:flex-end; font-weight:800; color:#355274; }
        .narr{ margin-top:10px; display:grid; grid-template-columns:130px 1fr; gap:8px; align-items:center; }
        textarea{ height:68px; padding:8px; resize:vertical; text-transform:uppercase; }
        .btns{
            margin-top:12px; border-top:1px solid #dde7f5; padding-top:12px;
            display:flex; gap:8px; justify-content:center; flex-wrap:wrap;
        }
        button{
            height:34px; min-width:96px; border:1px solid var(--line2); border-radius:8px; background:#fff;
            color:#203a56; font-weight:800; cursor:pointer; font-family:"Segoe UI",Tahoma,Arial,sans-serif;
        }
        button:hover{ background:#f6fbff; border-color:#9dbce0; }
        button:active{ transform:translateY(1px); }
        button:disabled{ opacity:.5; cursor:not-allowed; }
        #btnSave{ background:linear-gradient(100deg,#209b58,#188546); color:#fff; border-color:#14743e; }
        #btnExit{ background:linear-gradient(100deg,#b04646,#9c2f2f); color:#fff; border-color:#8e2b2b; }
        .status{ margin-top:10px; min-height:18px; text-align:center; font-weight:800; }
        .ok{ color:var(--ok); } .err{ color:var(--err); }

        .overlay{ position:fixed; inset:0; background:rgba(5,16,29,.44); display:none; align-items:center; justify-content:center; z-index:9999; }
        .modal{
            width:min(860px, calc(100vw - 24px)); background:#fff; border:1px solid #c8d8eb; border-radius:10px; overflow:hidden;
            box-shadow:0 16px 36px rgba(7,25,46,.32);
        }
        .mh{ display:flex; align-items:center; gap:8px; padding:10px 12px; background:linear-gradient(100deg,#155f95,#1d7fbe); color:#fff; font-weight:800; }
        .mh .sp{ flex:1; }
        .mh input{ height:28px; width:220px; border:1px solid rgba(255,255,255,.6); }
        .mb{ max-height:390px; overflow:auto; }
        .mb td,.mb th{ padding:7px 8px; border-bottom:1px solid #e8eef8; }
        .mb tr{ cursor:pointer; } .mb tr:hover td{ background:#f3f9ff; }

        @media (max-width: 960px){
            .top{ grid-template-columns:1fr; }
            .lbl{ text-align:left; }
            .checks{ justify-content:flex-start; flex-wrap:wrap; }
            .narr{ grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<div class="win">
    <h1 class="head">Journal</h1>
    <div class="panel">
        <div class="top">
            <div class="lbl">Voucher No</div>
            <input id="vchno" class="readonly" readonly>
            <div class="lbl">Date</div>
            <input id="tdate" maxlength="10" placeholder="dd/mm/yyyy">
            <div class="checks">
                <label><input id="updtfr" type="checkbox"> Fr</label>
                <label><input id="autoAmt" type="checkbox" checked> Auto Amt Aloc</label>
                <label><input id="print" type="checkbox"> Print</label>
            </div>
        </div>

        <div class="gridbox">
            <table>
                <thead>
                <tr>
                    <th style="width:14%;">A/C Code</th>
                    <th style="width:20%;">Ledger Name</th>
                    <th style="width:32%;">Narration</th>
                    <th style="width:17%;text-align:right;">Debit</th>
                    <th style="width:17%;text-align:right;">Credit</th>
                </tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
        <datalist id="acList"></datalist>

        <div class="toolbar">
            <button id="btnAddRow" class="small" type="button">Add</button>
            <button id="btnDeleteRow" class="small" type="button">Delete</button>
        </div>
        <div class="balancebox">
            <div class="metric">
                <div class="cap">Selected A/C</div>
                <div id="selectedAccount" class="val">-</div>
            </div>
            <div class="metric">
                <div class="cap">Opening Balance</div>
                <div id="openingBalance" class="val">0.00</div>
            </div>
            <div class="metric">
                <div class="cap">Closing Balance</div>
                <div id="closingBalance" class="val">0.00</div>
            </div>
        </div>
        <div class="totals">
            <div>Debit Total: <span id="debitTotal">0.00</span></div>
            <div>Credit Total: <span id="creditTotal">0.00</span></div>
        </div>

        <div class="narr">
            <div class="lbl">Narration</div>
            <textarea id="narration" maxlength="100"></textarea>
        </div>

        <div class="btns">
            <button id="btnAdd" type="button">&Add</button>
            <button id="btnEdit" type="button">&Edit</button>
            <button id="btnDelete" type="button">&Delete</button>
            <button id="btnSave" type="button">&Save</button>
            <button id="btnCancel" type="button">&Cancel</button>
            <button id="btnExit" type="button">E&xit</button>
        </div>
        <div id="status" class="status"></div>
    </div>
</div>

<div id="help" class="overlay">
    <div class="modal">
        <div class="mh">
            <span class="sp">Journal Entry Help</span>
            <input id="helpSearch" placeholder="Search voucher / narration">
            <button id="helpClose" type="button">Close</button>
        </div>
        <div class="mb">
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr><th style="width:140px;">Voucher</th><th style="width:90px;">Slno</th><th>Narration</th></tr></thead>
                <tbody id="helpRows"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const API = @json(url('/api/accounts/journal'));
    const $ = id => document.getElementById(id);

    let mode = 'C';
    let action = 'A';
    let editSlno = 0;
    const balanceCache = new Map();
    const prefillSlno = @json($prefillSlno ?? 0);
    const prefillVchno = @json($prefillVchno ?? '');

    function setStatus(msg, ok) {
        const el = $('status');
        el.textContent = msg || '';
        el.className = 'status ' + (ok ? 'ok' : 'err');
    }

    async function api(payload) {
        const res = await fetch(API, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        const d = await res.json();
        if (!res.ok || d.success === false) throw new Error(d.error || 'Request failed');
        return d;
    }

    function setMode(m) {
        mode = m;
        const edit = (m === 'A' || m === 'E');
        $('btnAdd').disabled = edit;
        $('btnEdit').disabled = false;
        $('btnDelete').disabled = false;
        $('btnExit').disabled = false;
        $('btnSave').disabled = !edit;
        $('btnCancel').disabled = !edit;
        $('btnAddRow').disabled = !edit;
        $('btnDeleteRow').disabled = !edit;
        document.querySelectorAll('#rows input').forEach(i => i.disabled = !edit);
        $('tdate').disabled = !edit;
        $('narration').disabled = !edit;
    }

    let accountMap = {};   // accode (upper) → name

    function rowHtml(r = {}) {
        const p = (r.particulars || '').replace(/"/g, '&quot;');
        const n = (r.name || accountMap[(r.particulars || '').toUpperCase()] || '').replace(/"/g, '&quot;');
        const rn = (r.rownote || '').replace(/"/g, '&quot;');
        const d = (Number(r.amountd || 0) || 0).toFixed(2);
        const c = (Number(r.amountc || 0) || 0).toFixed(2);
        return `<tr>
            <td><input class="cell p" list="acList" maxlength="20" value="${p}" style="text-transform:uppercase"></td>
            <td><input class="cell n name-cell" readonly tabindex="-1" value="${n}" placeholder="—"></td>
            <td><input class="cell rn" maxlength="100" value="${rn}" placeholder="note…" style="text-transform:uppercase"></td>
            <td><input class="cell d num" type="number" step="0.01" value="${d}"></td>
            <td><input class="cell c num" type="number" step="0.01" value="${c}"></td>
        </tr>`;
    }

    function parseNum(v) {
        const n = parseFloat(v);
        return Number.isFinite(n) ? n : 0;
    }

    function fmtAmt(v) {
        const n = Math.abs(Number(v) || 0);
        return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function balanceText(v, side) {
        const n = Number(v) || 0;
        if (Math.abs(n) < 0.00001) return '0.00';
        return `${fmtAmt(n)} ${side || (n < 0 ? 'Dr' : 'Cr')}`;
    }

    function setBalancePanel(data = null) {
        $('selectedAccount').textContent = data ? `${data.accode || ''}${data.name ? ' - ' + data.name : ''}` : '-';
        $('openingBalance').textContent = data ? balanceText(data.opening_balance, data.opening_side) : '0.00';
        $('closingBalance').textContent = data ? balanceText(data.closing_balance, data.closing_side) : '0.00';
    }

    async function refreshBalanceForCode(code, nameCell = null) {
        const accode = (code || '').trim().toUpperCase();
        if (!accode) {
            setBalancePanel(null);
            if (nameCell) { nameCell.classList.remove('not-found'); nameCell.value = ''; }
            return;
        }
        const tdate = ($('tdate').value || '').trim();
        const cacheKey = `${accode}|${tdate}`;
        if (balanceCache.has(cacheKey)) {
            const cached = balanceCache.get(cacheKey);
            setBalancePanel(cached);
            if (nameCell) { nameCell.classList.remove('not-found'); if (cached && cached.name) nameCell.value = cached.name; }
            return;
        }
        try {
            const res = await api({ action: 'account_balance', code: accode, tdate });
            const info = res.data || null;
            if (info) {
                balanceCache.set(cacheKey, info);
                accountMap[accode] = info.name || '';
            }
            setBalancePanel(info);
            if (nameCell) {
                nameCell.classList.remove('not-found');
                if (info && info.name) nameCell.value = info.name;
            }
        } catch (e) {
            setBalancePanel(null);
            if (nameCell) {
                nameCell.value = 'No code found';
                nameCell.classList.add('not-found');
            }
        }
    }

    function updateTotals() {
        let dt = 0, ct = 0;
        document.querySelectorAll('#rows tr').forEach(tr => {
            dt += parseNum(tr.querySelector('.d').value);
            ct += parseNum(tr.querySelector('.c').value);
        });
        $('debitTotal').textContent = dt.toFixed(2);
        $('creditTotal').textContent = ct.toFixed(2);
    }

    function addRow(data = null, focusPart = false, autoBalance = false) {
        let rowData = data;
        if (!rowData && autoBalance && $('autoAmt').checked) {
            let dt = 0, ct = 0;
            document.querySelectorAll('#rows tr').forEach(tr => {
                dt += parseNum(tr.querySelector('.d').value);
                ct += parseNum(tr.querySelector('.c').value);
            });
            const diff = Math.round((dt - ct) * 100) / 100;
            if (Math.abs(diff) > 0.001) {
                rowData = diff > 0
                    ? { amountc: diff, amountd: 0 }
                    : { amountd: Math.abs(diff), amountc: 0 };
            }
        }
        $('rows').insertAdjacentHTML('beforeend', rowHtml(rowData || {}));
        bindRowEvents();
        updateTotals();
        if (focusPart) {
            const trs = $('rows').querySelectorAll('tr');
            if (trs.length) trs[trs.length - 1].querySelector('.p').focus();
        }
    }

    function deleteCurrentRow() {
        const ae = document.activeElement;
        const tr = ae ? ae.closest('#rows tr') : null;
        if (tr && $('rows').querySelectorAll('tr').length > 1) {
            tr.remove();
        } else if (!tr && $('rows').querySelectorAll('tr').length > 1) {
            $('rows').lastElementChild.remove();
        } else {
            const r = $('rows').querySelector('tr');
            if (r) {
                r.querySelector('.p').value = '';
                const nEl = r.querySelector('.n');
                if (nEl) { nEl.value = ''; nEl.classList.remove('not-found'); }
                r.querySelector('.rn').value = '';
                r.querySelector('.d').value = '0.00';
                r.querySelector('.c').value = '0.00';
            }
        }
        updateTotals();
    }

    function autoAdjustForRow(tr, changedCol) {
        if (!$('autoAmt').checked) {
            return;
        }
        const dEl = tr.querySelector('.d');
        const cEl = tr.querySelector('.c');
        const d = parseNum(dEl.value);
        const c = parseNum(cEl.value);

        if (changedCol === 'd' && d > 0) cEl.value = '0.00';
        if (changedCol === 'c' && c > 0) dEl.value = '0.00';

        updateTotals();
    }

    function bindRowEvents() {
        const rows = document.querySelectorAll('#rows tr');
        rows.forEach((tr, idx) => {
            const p = tr.querySelector('.p');
            const n = tr.querySelector('.n');
            const rn = tr.querySelector('.rn');
            const d = tr.querySelector('.d');
            const c = tr.querySelector('.c');

            d.oninput = () => autoAdjustForRow(tr, 'd');
            c.oninput = () => autoAdjustForRow(tr, 'c');
            p.onchange = () => {
                p.value = (p.value || '').trim().toUpperCase();
                if (n) { n.classList.remove('not-found'); n.value = accountMap[p.value] || ''; }
                refreshBalanceForCode(p.value, n);
            };
            p.onfocus = () => {
                if (n && n.classList.contains('not-found')) { n.classList.remove('not-found'); n.value = ''; }
                refreshBalanceForCode(p.value, n && !n.value ? n : null);
            };
            p.onblur = () => {
                p.value = (p.value || '').trim().toUpperCase();
                if (n) { n.classList.remove('not-found'); n.value = accountMap[p.value] || ''; }
                if (p.value) refreshBalanceForCode(p.value, n);
                else { setBalancePanel(null); if (n) n.value = ''; }
            };

            // Helper: which amount field to go to after narration (smart side)
            const smartAmtField = () => (parseNum(c.value) > 0 && parseNum(d.value) === 0) ? c : d;

            [p, rn, d, c].forEach(inp => {
                inp.onkeydown = (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (inp === p) rn.focus();
                        else if (inp === rn) smartAmtField().focus();
                        else if (inp === d) c.focus();
                        else {
                            const isLast = idx === rows.length - 1;
                            if (isLast) addRow(null, true, true);
                            else rows[idx + 1].querySelector('.p').focus();
                        }
                    }
                    if (e.key === 'Tab' && !e.shiftKey) {
                        if (inp === c) {
                            const isLast = idx === rows.length - 1;
                            if (isLast) { e.preventDefault(); addRow(null, true, true); }
                        }
                        if (inp === p) { e.preventDefault(); rn.focus(); }
                        if (inp === rn) { e.preventDefault(); smartAmtField().focus(); }
                    }
                    if (e.key === 'Tab' && e.shiftKey) {
                        if (inp === rn) { e.preventDefault(); p.focus(); }
                        if (inp === d) { e.preventDefault(); rn.focus(); }
                    }
                    if (e.key === 'F1' && inp === p) {
                        e.preventDefault();
                        openHelpList(inp);
                    }
                };
            });
        });
    }

    function collectRows() {
        const out = [];
        document.querySelectorAll('#rows tr').forEach(tr => {
            out.push({
                particulars: (tr.querySelector('.p').value || '').trim().toUpperCase(),
                rownote: (tr.querySelector('.rn').value || '').trim(),
                amountd: parseNum(tr.querySelector('.d').value),
                amountc: parseNum(tr.querySelector('.c').value),
            });
        });
        return out;
    }

    async function initData() {
        const [d, acc] = await Promise.all([
            api({ action: 'init' }),
            api({ action: 'account_list' }),
        ]);
        $('tdate').value = d.today || '';
        $('vchno').value = d.nextVchno || '';
        $('autoAmt').checked = !!d.autoAmtDefault;
        (acc.data || []).forEach(r => {
            if (r.accode) accountMap[String(r.accode).toUpperCase()] = r.name || '';
        });
        $('acList').innerHTML = (acc.data || []).map(r => `<option value="${String(r.accode || '').replace(/"/g,'&quot;')}">${String(r.name || '').replace(/</g,'&lt;')}</option>`).join('');
        setBalancePanel(null);
    }

    async function resetForAdd() {
        action = 'A';
        editSlno = 0;
        $('narration').value = '';
        $('rows').innerHTML = '';
        balanceCache.clear();
        const d = await api({ action: 'next_vchno' });
        $('vchno').value = d.nextVchno || '';
        addRow(null, false);
        setBalancePanel(null);
        setMode('A');
        $('tdate').focus();
        setStatus('', true);
    }

    async function saveEntry() {
        try {
            const res = await api({
                action: 'save',
                mode: action,
                slno: editSlno,
                vchno: $('vchno').value,
                tdate: $('tdate').value,
                narration: $('narration').value,
                rows: collectRows(),
                print: $('print').checked,
                updtfr: $('updtfr').checked,
            });
            setStatus('Saved: ' + (res.vchno || ''), true);
            await resetForAdd();
        } catch (e) {
            setStatus(e.message, false);
        }
    }

    function openHelp(actionType) {
        $('help').dataset.action = actionType;
        $('help').dataset.targetInput = '';
        $('help').style.display = 'flex';
        loadHelp('');
    }

    function openHelpList(targetInput) {
        $('help').dataset.action = 'pick_account';
        $('help').dataset.targetInput = '1';
        $('help')._targetInputRef = targetInput || null;
        $('help').style.display = 'flex';
        loadHelp('', true);
    }

    async function loadHelp(search, accountMode = false) {
        try {
            if (accountMode || $('help').dataset.action === 'pick_account') {
                const d = await api({ action: 'account_list', search: search || '' });
                $('helpRows').innerHTML = (d.data || []).map(r => `<tr data-code="${String(r.accode || '').replace(/"/g,'&quot;')}"><td>${String(r.accode || '').replace(/</g,'&lt;')}</td><td></td><td>${String(r.name || '').replace(/</g,'&lt;')}</td></tr>`).join('');
            } else {
                const d = await api({ action: 'entry_list', search: search || '' });
                $('helpRows').innerHTML = (d.data || []).map(r => `<tr data-slno="${r.slno}" data-vch="${String(r.vchno || '').replace(/"/g,'&quot;')}"><td>${String(r.vchno || '').replace(/</g,'&lt;')}</td><td>${r.slno || ''}</td><td>${String(r.particular || '').replace(/</g,'&lt;')}</td></tr>`).join('');
            }
        } catch (e) {
            setStatus(e.message, false);
        }
    }

    async function loadForEdit(slno, vchno) {
        const d = await api({ action: 'load', slno, vchno });
        const x = d.data || {};
        action = 'E';
        editSlno = Number(x.slno || 0);
        $('vchno').value = x.vchno || '';
        $('tdate').value = x.tdate || '';
        $('narration').value = x.narration || '';
        $('rows').innerHTML = '';
        balanceCache.clear();
        (x.rows || []).forEach(r => addRow(r, false));
        if ((x.rows || []).length === 0) addRow(null, false);
        const firstCode = (((x.rows || [])[0] || {}).particulars || '').trim().toUpperCase();
        await refreshBalanceForCode(firstCode);
        setMode('E');
        $('tdate').focus();
    }

    async function deleteEntry(slno, vchno) {
        if (!confirm('Delete this journal entry?')) return;
        try {
            await api({ action: 'delete', slno, vchno });
            setStatus('Deleted', true);
            await resetForAdd();
        } catch (e) {
            setStatus(e.message, false);
        }
    }

    function closeHelp() {
        $('help').style.display = 'none';
    }

    $('btnAdd').addEventListener('click', () => { resetForAdd(); });
    $('btnEdit').addEventListener('click', () => { openHelp('edit'); });
    $('btnDelete').addEventListener('click', () => { openHelp('delete'); });
    $('btnSave').addEventListener('click', saveEntry);
    $('btnCancel').addEventListener('click', () => { setMode('C'); resetForAdd(); });
    $('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        else window.close();
    });
    $('btnAddRow').addEventListener('click', () => addRow(null, true, true));
    $('btnDeleteRow').addEventListener('click', deleteCurrentRow);

    $('helpClose').addEventListener('click', closeHelp);
    $('help').addEventListener('click', e => { if (e.target === $('help')) closeHelp(); });
    $('helpSearch').addEventListener('input', () => loadHelp($('helpSearch').value.trim()));
    $('helpRows').addEventListener('click', async (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        const act = $('help').dataset.action || '';
        if (act === 'pick_account') {
            const code = (tr.dataset.code || '').trim().toUpperCase();
            if ($('help')._targetInputRef) {
                $('help')._targetInputRef.value = code;
                const rowEl = $('help')._targetInputRef.closest('#rows tr');
                const nc = rowEl ? rowEl.querySelector('.n') : null;
                if (nc) { nc.classList.remove('not-found'); nc.value = accountMap[code] || ''; }
                refreshBalanceForCode(code, nc);
            }
            closeHelp();
            return;
        }
        const slno = Number(tr.dataset.slno || 0);
        const vch = (tr.dataset.vch || '').trim().toUpperCase();
        closeHelp();
        if (!slno && !vch) return;
        try {
            if (act === 'edit') await loadForEdit(slno, vch);
            if (act === 'delete') await deleteEntry(slno, vch);
        } catch (err) {
            setStatus(err.message, false);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') {
            e.preventDefault();
            if (!$('btnSave').disabled) saveEntry();
        }
        if (e.key === 'Escape') {
            if ($('help').style.display === 'flex') closeHelp();
        }
    });

    $('tdate').addEventListener('change', () => {
        balanceCache.clear();
        const active = document.activeElement && document.activeElement.classList.contains('p')
            ? document.activeElement
            : $('rows').querySelector('.p');
        refreshBalanceForCode(active ? active.value : '');
    });

    (async function boot() {
        try {
            await initData();
            await resetForAdd();
            if (prefillSlno || prefillVchno) {
                await loadForEdit(prefillSlno, prefillVchno);
            } else {
                setMode('A');
            }
        } catch (e) {
            setStatus(e.message, false);
        }
    })();
})();
</script>
</body>
</html>
