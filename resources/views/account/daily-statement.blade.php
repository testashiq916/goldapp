<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Daily Statement Entry</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1200px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 12px; }
        h1 { margin: 0 0 10px; font-size: 22px; color: #1f3f66; display: flex; align-items: center; gap: 12px; }
        .status { margin: 8px 0; padding: 8px 10px; border-radius: 7px; font-size: 12px; display: none; }
        .status.show { display: block; }
        .status.ok { border: 1px solid #9fd5af; background: #e6f6ec; color: #12522d; }
        .status.err { border: 1px solid #f2b1b1; background: #fdeaea; color: #8e1f1f; }
        .sec { border: 1px solid #d8e2ef; border-radius: 8px; padding: 10px; margin-bottom: 10px; background: #f8fbff; }
        .hdr-grid { display: grid; grid-template-columns: auto 1fr auto 1fr auto 1fr; gap: 8px; align-items: center; }
        label { font-size: 11px; font-weight: 700; color: #375b84; white-space: nowrap; }
        input, select { height: 32px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; width: 100%; box-sizing: border-box; }
        input:focus { outline: none; border-color: #4a90d9; background: #fff8e6; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 6px; align-items: center; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 14px; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        button.warn { border-color: #a23b3b; background: #fdeaea; color: #8e1f1f; }
        button.active { border-color: #2470b0; background: #dfefff; color: #0f3e67; }
        .bal-box { display: inline-flex; align-items: center; gap: 6px; background: #edf4fc; border: 1px solid #c5d9ef; border-radius: 6px; padding: 4px 10px; font-size: 13px; font-weight: 700; }
        .bal-box .val { color: #1f3f66; }
        .bal-box .lbl { font-size: 11px; color: #6b7f9a; }

        /* Grid table */
        .grid-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 460px; }
        table.grid { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.grid th { position: sticky; top: 0; background: #edf4fc; border-bottom: 2px solid #c5d9ef; padding: 6px 4px; text-align: left; font-size: 11px; font-weight: 700; color: #2d4f74; z-index: 2; }
        table.grid td { border-bottom: 1px solid #e3eaf4; padding: 2px; }
        table.grid tr:hover td { background: #f5faff; }
        table.grid tr.active td { background: #e9f3ff; }
        table.grid input { height: 28px; border: 1px solid transparent; border-radius: 4px; background: transparent; font-size: 12px; padding: 0 6px; width: 100%; box-sizing: border-box; }
        table.grid input:focus { border-color: #4a90d9; background: #fff8e6; }
        table.grid input.right { text-align: right; }
        table.grid .ro { background: #f3f6fb; color: #555; cursor: default; }
        table.grid .row-num { text-align: center; color: #8899b0; font-size: 11px; width: 30px; }
        table.grid .del-btn { width: 24px; height: 24px; border: none; background: none; color: #c44; cursor: pointer; font-size: 14px; padding: 0; border-radius: 4px; }
        table.grid .del-btn:hover { background: #fdeaea; }

        /* Footer totals */
        .totals-row { display: flex; gap: 16px; align-items: center; padding: 8px 4px; font-size: 13px; font-weight: 700; color: #1f3f66; }
        .totals-row .t-label { color: #6b7f9a; font-weight: 600; font-size: 12px; }

        @media (max-width: 900px) { .hdr-grid { grid-template-columns: auto 1fr; } }
    </style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="wrap">
    <h1>
        <span>Daily Statement Entry</span>
        <span style="flex:1;"></span>
        <div class="bal-box">
            <span class="lbl">Cash Bal:</span>
            <span id="cashBalVal" class="val">0.00</span>
        </div>
    </h1>
    <div id="status" class="status"></div>

    <!-- Header: Date, Cash/Bank, Salesman -->
    <div class="sec">
        <div class="hdr-grid">
            <label>Date</label>
            <input id="tdate" maxlength="10" placeholder="dd/mm/yyyy" style="max-width:150px;">
            <label>Cash/Bank Code</label>
            <select id="cbcode"></select>
            <label>SMan</label>
            <select id="staff"><option value="">--</option></select>
        </div>
    </div>

    <!-- Grid -->
    <div class="sec" style="padding:6px;">
        <div class="grid-wrap">
            <table class="grid" id="gridTable">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th style="width:120px;">Ac Code</th>
                        <th style="width:200px;">Ac Name</th>
                        <th>Description</th>
                        <th style="width:110px;">Chq.No.</th>
                        <th style="width:110px;">Receipt</th>
                        <th style="width:110px;">Payment</th>
                        <th style="width:100px;">Vch No</th>
                        <th style="width:30px;"></th>
                    </tr>
                </thead>
                <tbody id="gridBody"></tbody>
            </table>
        </div>
        <div class="totals-row">
            <span class="t-label">Entries:</span> <span id="totCount">0</span>
            <span style="flex:1;"></span>
            <span class="t-label">Total Receipt:</span> <span id="totReceipt">0.00</span>
            <span style="margin-left:16px;" class="t-label">Total Payment:</span> <span id="totPayment">0.00</span>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <button id="btnAdd" type="button">Add Row</button>
        <button id="btnEdit" type="button">Edit (Load Date)</button>
        <button id="btnSave" type="button" class="primary">Save (F9)</button>
        <button id="btnClear" type="button">Clear All</button>
        <button id="btnExit" type="button">Exit (Esc)</button>
    </div>
</div>

<!-- Account Help Modal -->
<div id="acHelpModal" style="position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center;z-index:9999;">
    <div style="width:650px;background:#fff;border-radius:10px;border:1px solid #d7dfeb;padding:12px;">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
            <strong style="flex:1;">Select Account</strong>
            <input id="acHelpSearch" placeholder="Search..." style="width:220px;">
            <button id="acHelpClose" type="button">Close</button>
        </div>
        <div class="grid-wrap" style="max-height:360px;">
            <table class="grid">
                <thead><tr><th style="width:120px;">Code</th><th>Name</th></tr></thead>
                <tbody id="acHelpRows"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const BASE = @json(url('/api/accounts/daily-statement'));
    const statusEl = document.getElementById('status');

    let initData = {};
    let gridRows = []; // [{accode, acname, description, chqno, receipt, payment, vchno, slno}]
    let activeRowIdx = -1;
    let acHelpTarget = null; // which row's accode field triggered help

    function showStatus(msg, ok) {
        statusEl.textContent = msg;
        statusEl.className = 'status show ' + (ok ? 'ok' : 'err');
    }
    function clearStatus() { statusEl.className = 'status'; }

    async function api(payload) {
        const res = await fetch(BASE, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (e) { throw new Error('Invalid server response'); }
        if (!res.ok) throw new Error(data.error || data.message || 'Request failed');
        if (data.success === false) throw new Error(data.error || data.message || 'Request failed');
        return data;
    }

    function esc(v) { return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    // ── Init ──
    async function init() {
        try {
            initData = await api({ action: 'init' });

            const cbSel = document.getElementById('cbcode');
            cbSel.innerHTML = '';
            (initData.cbAccounts || []).forEach(r => {
                const o = document.createElement('option');
                o.value = r.accode;
                o.textContent = r.accode + ' - ' + r.name;
                cbSel.appendChild(o);
            });

            const stSel = document.getElementById('staff');
            stSel.innerHTML = '<option value="">--</option>';
            (initData.salesmen || []).forEach(r => {
                const o = document.createElement('option');
                o.value = r.code;
                o.textContent = r.code + ' - ' + r.name;
                stSel.appendChild(o);
            });

            document.getElementById('tdate').value = initData.today || '';
            document.getElementById('cashBalVal').textContent = Number(initData.cashBalance || 0).toFixed(2);

            clearGrid();
            addEmptyRow();
        } catch (e) { showStatus('Init failed: ' + e.message, false); }
    }

    // ── Grid management ──
    function emptyRow() {
        return { accode: '', acname: '', description: '', chqno: '', receipt: 0, payment: 0, vchno: '', slno: 0 };
    }

    function clearGrid() {
        gridRows = [];
        activeRowIdx = -1;
        renderGrid();
    }

    function addEmptyRow() {
        gridRows.push(emptyRow());
        renderGrid();
        // Focus the new row's accode
        const idx = gridRows.length - 1;
        setTimeout(() => {
            const inp = document.querySelector(`#gridBody tr[data-idx="${idx}"] input[data-col="accode"]`);
            if (inp) inp.focus();
        }, 50);
    }

    function renderGrid() {
        const body = document.getElementById('gridBody');
        body.innerHTML = '';

        gridRows.forEach((row, idx) => {
            const tr = document.createElement('tr');
            tr.dataset.idx = idx;
            if (idx === activeRowIdx) tr.classList.add('active');

            tr.innerHTML = `
                <td class="row-num">${idx + 1}</td>
                <td><div style="display:flex;gap:2px;"><input data-col="accode" value="${esc(row.accode)}" maxlength="20" style="flex:1;text-transform:uppercase;"><button class="del-btn ac-help-btn" type="button" title="Search">^</button></div></td>
                <td><input data-col="acname" value="${esc(row.acname)}" class="ro" readonly tabindex="-1"></td>
                <td><input data-col="description" value="${esc(row.description)}" maxlength="200" style="text-transform:uppercase;"></td>
                <td><input data-col="chqno" value="${esc(row.chqno)}" maxlength="15" style="text-transform:uppercase;"></td>
                <td><input data-col="receipt" value="${row.receipt > 0 ? row.receipt.toFixed(2) : ''}" class="right" type="text" inputmode="decimal"></td>
                <td><input data-col="payment" value="${row.payment > 0 ? row.payment.toFixed(2) : ''}" class="right" type="text" inputmode="decimal"></td>
                <td><input data-col="vchno" value="${esc(row.vchno)}" class="ro" readonly tabindex="-1"></td>
                <td><button class="del-btn row-del-btn" type="button" title="Delete row">&times;</button></td>
            `;
            body.appendChild(tr);
        });

        updateTotals();
    }

    function syncRowFromDOM(idx) {
        const tr = document.querySelector(`#gridBody tr[data-idx="${idx}"]`);
        if (!tr) return;
        const row = gridRows[idx];
        row.accode = (tr.querySelector('[data-col="accode"]').value || '').trim().toUpperCase();
        row.description = (tr.querySelector('[data-col="description"]').value || '').trim().toUpperCase();
        row.chqno = (tr.querySelector('[data-col="chqno"]').value || '').trim().toUpperCase();
        row.receipt = parseFloat(tr.querySelector('[data-col="receipt"]').value) || 0;
        row.payment = parseFloat(tr.querySelector('[data-col="payment"]').value) || 0;
    }

    function syncAllFromDOM() {
        gridRows.forEach((_, i) => syncRowFromDOM(i));
    }

    function updateTotals() {
        let totR = 0, totP = 0, cnt = 0;
        gridRows.forEach(r => {
            if (r.accode && (r.receipt > 0 || r.payment > 0)) cnt++;
            totR += r.receipt || 0;
            totP += r.payment || 0;
        });
        document.getElementById('totCount').textContent = cnt;
        document.getElementById('totReceipt').textContent = totR.toFixed(2);
        document.getElementById('totPayment').textContent = totP.toFixed(2);
    }

    // ── Grid events ──
    document.getElementById('gridBody').addEventListener('click', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        const idx = parseInt(tr.dataset.idx);

        // Delete row button
        if (e.target.closest('.row-del-btn')) {
            if (gridRows.length > 1) {
                syncAllFromDOM();
                gridRows.splice(idx, 1);
                renderGrid();
            }
            return;
        }

        // Account help button
        if (e.target.closest('.ac-help-btn')) {
            acHelpTarget = idx;
            openAccountHelp();
            return;
        }

        activeRowIdx = idx;
        document.querySelectorAll('#gridBody tr').forEach(r => r.classList.remove('active'));
        tr.classList.add('active');
    });

    // Handle input changes
    document.getElementById('gridBody').addEventListener('input', (e) => {
        const inp = e.target;
        if (!inp.dataset || !inp.dataset.col) return;
        const tr = inp.closest('tr');
        if (!tr) return;
        const idx = parseInt(tr.dataset.idx);
        const col = inp.dataset.col;

        if (col === 'receipt' || col === 'payment') {
            syncRowFromDOM(idx);
            updateTotals();
        }
    });

    // Handle Enter key / accode change
    document.getElementById('gridBody').addEventListener('keydown', (e) => {
        const inp = e.target;
        if (!inp.dataset || !inp.dataset.col) return;
        const tr = inp.closest('tr');
        if (!tr) return;
        const idx = parseInt(tr.dataset.idx);
        const col = inp.dataset.col;

        if (e.key === 'Enter') {
            e.preventDefault();
            syncRowFromDOM(idx);

            if (col === 'accode') {
                // Lookup account name
                lookupAccount(idx);
                // Move to description
                const next = tr.querySelector('[data-col="description"]');
                if (next) next.focus();
            } else if (col === 'payment') {
                // Last column — add new row if this row has data
                syncRowFromDOM(idx);
                if (gridRows[idx].accode) {
                    if (idx === gridRows.length - 1) {
                        addEmptyRow();
                    } else {
                        // Move to next row
                        const nextTr = document.querySelector(`#gridBody tr[data-idx="${idx + 1}"]`);
                        if (nextTr) nextTr.querySelector('[data-col="accode"]').focus();
                    }
                }
            } else {
                // Move to next column
                const cols = ['accode', 'description', 'chqno', 'receipt', 'payment'];
                const ci = cols.indexOf(col);
                if (ci >= 0 && ci < cols.length - 1) {
                    const next = tr.querySelector(`[data-col="${cols[ci + 1]}"]`);
                    if (next) next.focus();
                }
            }
        } else if (e.key === 'F1' && col === 'accode') {
            e.preventDefault();
            acHelpTarget = idx;
            openAccountHelp();
        }
    });

    // Blur on accode to auto-lookup
    document.getElementById('gridBody').addEventListener('focusout', (e) => {
        const inp = e.target;
        if (!inp.dataset || inp.dataset.col !== 'accode') return;
        const tr = inp.closest('tr');
        if (!tr) return;
        const idx = parseInt(tr.dataset.idx);
        syncRowFromDOM(idx);
        const code = gridRows[idx].accode;
        if (code && !gridRows[idx].acname) {
            lookupAccount(idx);
        }
    });

    async function lookupAccount(idx) {
        const code = gridRows[idx].accode;
        if (!code) return;
        try {
            const d = await api({ action: 'lookup_account', accode: code });
            gridRows[idx].acname = d.name || '';
            const tr = document.querySelector(`#gridBody tr[data-idx="${idx}"]`);
            if (tr) tr.querySelector('[data-col="acname"]').value = d.name || '';
        } catch (e) {
            gridRows[idx].acname = '';
            gridRows[idx].accode = '';
            const tr = document.querySelector(`#gridBody tr[data-idx="${idx}"]`);
            if (tr) {
                tr.querySelector('[data-col="acname"]').value = '';
                tr.querySelector('[data-col="accode"]').value = '';
                tr.querySelector('[data-col="accode"]').focus();
            }
            showStatus('Account not found: ' + code, false);
        }
    }

    // ── Account Help Modal ──
    let acHelpData = [];

    async function openAccountHelp() {
        try {
            const d = await api({ action: 'account_search', search: '' });
            acHelpData = d.data || [];
            renderAcHelp('');
        } catch (e) { return; }
        document.getElementById('acHelpModal').style.display = 'flex';
        document.getElementById('acHelpSearch').value = '';
        document.getElementById('acHelpSearch').focus();
    }

    function renderAcHelp(filter) {
        const body = document.getElementById('acHelpRows');
        body.innerHTML = '';
        const f = filter.toUpperCase();
        acHelpData.filter(r => !f || (r.accode || '').toUpperCase().includes(f) || (r.name || '').toUpperCase().includes(f)).forEach(r => {
            const tr = document.createElement('tr');
            tr.dataset.code = r.accode || '';
            tr.dataset.name = r.name || '';
            tr.innerHTML = `<td>${esc(r.accode || '')}</td><td>${esc(r.name || '')}</td>`;
            tr.style.cursor = 'pointer';
            body.appendChild(tr);
        });
    }

    document.getElementById('acHelpSearch').addEventListener('input', () => {
        renderAcHelp(document.getElementById('acHelpSearch').value.trim());
    });

    document.getElementById('acHelpRows').addEventListener('dblclick', (e) => {
        const tr = e.target.closest('tr');
        if (!tr || acHelpTarget === null) return;
        const code = tr.dataset.code || '';
        const name = tr.dataset.name || '';
        gridRows[acHelpTarget].accode = code;
        gridRows[acHelpTarget].acname = name;
        const gridTr = document.querySelector(`#gridBody tr[data-idx="${acHelpTarget}"]`);
        if (gridTr) {
            gridTr.querySelector('[data-col="accode"]').value = code;
            gridTr.querySelector('[data-col="acname"]').value = name;
        }
        document.getElementById('acHelpModal').style.display = 'none';
        acHelpTarget = null;
        // Focus description
        if (gridTr) gridTr.querySelector('[data-col="description"]').focus();
    });

    document.getElementById('acHelpClose').addEventListener('click', () => {
        document.getElementById('acHelpModal').style.display = 'none';
    });

    // ── Cash/Bank change ──
    document.getElementById('cbcode').addEventListener('change', async () => {
        const code = document.getElementById('cbcode').value;
        if (!code) return;
        try {
            const d = await api({ action: 'lookup_account', accode: code });
            document.getElementById('cashBalVal').textContent = '...';
            // Re-init to get fresh balance
            const d2 = await api({ action: 'init' });
            document.getElementById('cashBalVal').textContent = Number(d2.cashBalance || 0).toFixed(2);
        } catch (e) { /* silent */ }
    });

    // ── Edit (Load) ──
    document.getElementById('btnEdit').addEventListener('click', async () => {
        const tdate = document.getElementById('tdate').value;
        const cbcode = document.getElementById('cbcode').value;
        if (!tdate) { showStatus('Enter a date first', false); return; }

        try {
            const d = await api({ action: 'load', tdate: tdate, cbcode: cbcode });
            gridRows = (d.rows || []).map(r => ({
                accode: r.accode || '',
                acname: r.acname || '',
                description: r.description || '',
                chqno: r.chqno || '',
                receipt: r.receipt || 0,
                payment: r.payment || 0,
                vchno: r.vchno || '',
                slno: r.slno || 0,
            }));

            if (d.staff) {
                document.getElementById('staff').value = d.staff;
            }

            document.getElementById('cashBalVal').textContent = Number(d.cashBalance || 0).toFixed(2);

            if (gridRows.length === 0) {
                gridRows.push(emptyRow());
                showStatus('No entries found for this date', false);
            } else {
                showStatus('Loaded ' + gridRows.length + ' entries', true);
            }
            renderGrid();
        } catch (e) { showStatus(e.message, false); }
    });

    // ── Save ──
    async function doSave() {
        syncAllFromDOM();

        const tdate = document.getElementById('tdate').value;
        const cbcode = document.getElementById('cbcode').value;
        const staff = document.getElementById('staff').value;

        if (!tdate) { showStatus('Date is required', false); return; }
        if (!staff) { showStatus('Salesman is required', false); document.getElementById('staff').focus(); return; }

        // Check at least one valid row
        const validCount = gridRows.filter(r => r.accode && (r.receipt > 0 || r.payment > 0)).length;
        if (validCount === 0) { showStatus('Enter at least one entry with account code and amount', false); return; }

        if (!confirm('Save this Daily Statement Entry? (' + validCount + ' entries)')) return;

        try {
            const d = await api({
                action: 'save',
                tdate: tdate,
                cbcode: cbcode,
                staff: staff,
                rows: gridRows,
            });
            showStatus(d.message || 'Saved', true);

            // Refresh: reload the saved data
            if (confirm('Entry saved. Do you want to close?')) {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
                } else { window.close(); }
            } else {
                // Reload entries for the date
                document.getElementById('btnEdit').click();
            }
        } catch (e) { showStatus(e.message, false); }
    }

    // ── Buttons ──
    document.getElementById('btnAdd').addEventListener('click', () => {
        syncAllFromDOM();
        addEmptyRow();
    });

    document.getElementById('btnSave').addEventListener('click', doSave);

    document.getElementById('btnClear').addEventListener('click', () => {
        if (gridRows.some(r => r.accode) && !confirm('Clear all entries?')) return;
        clearGrid();
        addEmptyRow();
        clearStatus();
    });

    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else { window.close(); }
    });

    // ── Keyboard shortcuts ──
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') {
            e.preventDefault();
            doSave();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            const modal = document.getElementById('acHelpModal');
            if (modal.style.display === 'flex') { modal.style.display = 'none'; return; }
            document.getElementById('btnExit').click();
        }
    });

    // ── Start ──
    init();
})();
</script>
</body>
</html>
