<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kuri/Scheme Type Details</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;color:#1e293b;font-size:12px;height:100vh;overflow:hidden;display:flex;flex-direction:column}

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:10px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0}
.toolbar h1{color:#fff;font-size:15px;font-weight:700}
.spacer{flex:1}
.btn{padding:6px 16px;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s}
.btn:disabled{opacity:.5;cursor:not-allowed}
.btn-blue{background:#3b82f6;color:#fff}.btn-blue:hover:not(:disabled){background:#2563eb}
.btn-green{background:#16a34a;color:#fff}.btn-green:hover:not(:disabled){background:#15803d}
.btn-red{background:#ef4444;color:#fff}.btn-red:hover:not(:disabled){background:#dc2626}
.btn-gray{background:#64748b;color:#fff}.btn-gray:hover:not(:disabled){background:#475569}
.btn-outline{background:#fff;border:1.5px solid #e2e8f0;color:#475569}.btn-outline:hover{background:#f1f5f9}

.grid-wrap{flex:1;overflow:auto;padding:12px 20px}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06)}
thead{position:sticky;top:0;z-index:2}
th{background:#f8fafc;padding:8px 10px;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;text-align:left;border-bottom:2px solid #e2e8f0}
th.r{text-align:right}
th.c{text-align:center}
td{border-bottom:1px solid #f1f5f9;padding:0}
td input,td select{width:100%;height:34px;border:none;padding:0 8px;font-size:12px;color:#1e293b;background:transparent;outline:none}
td input:focus,td select:focus{background:#eff6ff;box-shadow:inset 0 0 0 2px #3b82f6}
td input.r{text-align:right}
td input.c{text-align:center}
td.c{text-align:center}
tr:hover td{background:#fafbff}
tr.selected td{background:#eff6ff}
.row-num{color:#94a3b8;font-size:11px;text-align:center;padding:0 8px;width:36px}
.del-btn{background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px;padding:4px 8px;border-radius:4px}
.del-btn:hover{background:#fee2e2}

.bottom-bar{background:#fff;border-top:1px solid #e2e8f0;padding:8px 20px;display:flex;align-items:center;gap:8px;flex-shrink:0}
.status{color:#64748b;font-size:12px;flex:1}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:12px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}
.toast.err{background:#dc2626}
</style>
</head>
<body>

<div class="toolbar">
    <h1>Kuri/Scheme Type Details</h1>
    <div class="spacer"></div>
    <button class="btn btn-blue" id="btnAdd">Add Row</button>
    <button class="btn btn-red" id="btnDelete">Delete Row</button>
    <button class="btn btn-green" id="btnSave">Save (F9)</button>
    <button class="btn btn-outline" id="btnCancel">Cancel</button>
    <button class="btn btn-gray" id="btnExit">Exit</button>
</div>

<div class="grid-wrap">
    <table>
        <thead>
            <tr>
                <th style="width:36px">#</th>
                <th style="width:90px">Code</th>
                <th style="width:200px">Name</th>
                <th class="r" style="width:70px">Inst Nos</th>
                <th class="r" style="width:90px">Inst Amt</th>
                <th class="r" style="width:90px">Tot Amt</th>
                <th class="r" style="width:80px">Bonus</th>
                <th style="width:100px">Coln Type</th>
                <th class="r" style="width:80px">Coln Min</th>
                <th class="r" style="width:80px">Coln Max</th>
                <th style="width:70px">Prefix</th>
                <th class="r" style="width:70px">Last No</th>
                <th class="r" style="width:80px">Comn Rate</th>
                <th style="width:36px"></th>
            </tr>
        </thead>
        <tbody id="tbody"></tbody>
    </table>
</div>

<div class="bottom-bar">
    <span class="status" id="statusBar">Ready</span>
    <span class="status" id="rowCount">0 rows</span>
</div>

<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const csrf = document.querySelector('meta[name="csrf-token"]').content;
let rows = [];
let selIdx = -1;

function toast(msg, ok=true) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast ' + (ok ? 'ok' : 'err');
    t.style.display = 'block';
    setTimeout(() => t.style.display='none', 3000);
}

async function api(url, method='GET', body=null) {
    const opts = { method, headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} };
    if (body) {
        opts.headers['Content-Type'] = 'application/json';
        opts.headers['X-CSRF-TOKEN'] = csrf;
        opts.body = JSON.stringify(body);
    }
    const r = await fetch(url, opts);
    return r.json();
}

function collectRows() {
    const trs = document.querySelectorAll('#tbody tr');
    const result = [];
    trs.forEach(tr => {
        const get = name => {
            const el = tr.querySelector(`[data-col="${name}"]`);
            return el ? el.value : '';
        };
        result.push({
            code:       get('code'),
            name:       get('name'),
            instnos:    parseInt(get('instnos')) || 0,
            instamt:    parseFloat(get('instamt')) || 0,
            totamt:     parseFloat(get('totamt')) || 0,
            bonus:      parseFloat(get('bonus')) || 0,
            colntype:   get('colntype'),
            collnmin:   parseFloat(get('collnmin')) || 0,
            collnlimit: parseFloat(get('collnlimit')) || 0,
            prefix:     get('prefix'),
            lastno:     parseInt(get('lastno')) || 0,
            comnrate:   parseFloat(get('comnrate')) || 0,
        });
    });
    return result;
}

function renderGrid() {
    const tbody = document.getElementById('tbody');
    tbody.innerHTML = '';

    rows.forEach((r, i) => {
        const tr = document.createElement('tr');
        if (i === selIdx) tr.className = 'selected';
        tr.addEventListener('click', () => { selIdx = i; highlightRow(); });

        const colnTypeOpts = `<option value="M"${r.colntype==='M'?' selected':''}>Monthly</option><option value="W"${r.colntype==='W'?' selected':''}>Weekly</option><option value="D"${r.colntype==='D'?' selected':''}>Daily</option>`;

        tr.innerHTML = `
            <td class="row-num">${i+1}</td>
            <td><input data-col="code" value="${esc(r.code||'')}" maxlength="10" style="text-transform:uppercase"></td>
            <td><input data-col="name" value="${esc(r.name||'')}"></td>
            <td><input data-col="instnos" class="r" type="number" value="${r.instnos||0}" min="0"></td>
            <td><input data-col="instamt" class="r" type="number" step="0.01" value="${fmt2(r.instamt)}"></td>
            <td><input data-col="totamt" class="r" type="number" step="0.01" value="${fmt2(r.totamt)}"></td>
            <td><input data-col="bonus" class="r" type="number" step="0.01" value="${fmt2(r.bonus)}"></td>
            <td><select data-col="colntype">${colnTypeOpts}</select></td>
            <td><input data-col="collnmin" class="r" type="number" step="0.01" value="${fmt2(r.collnmin)}"></td>
            <td><input data-col="collnlimit" class="r" type="number" step="0.01" value="${fmt2(r.collnlimit)}"></td>
            <td><input data-col="prefix" value="${esc(r.prefix||'')}" maxlength="6" style="text-transform:uppercase"></td>
            <td><input data-col="lastno" class="r" type="number" value="${r.lastno||0}" min="0"></td>
            <td><input data-col="comnrate" class="r" type="number" step="0.01" value="${fmt2(r.comnrate)}"></td>
            <td class="c"><button class="del-btn" data-idx="${i}" title="Remove">&times;</button></td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('rowCount').textContent = rows.length + ' rows';

    // Handle Enter key navigation within grid
    tbody.querySelectorAll('input, select').forEach(el => {
        el.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const td = el.closest('td');
                const nextTd = td.nextElementSibling;
                if (nextTd) {
                    const nextInput = nextTd.querySelector('input, select');
                    if (nextInput) nextInput.focus();
                }
            }
        });
    });
}

function highlightRow() {
    const trs = document.querySelectorAll('#tbody tr');
    trs.forEach((tr, i) => tr.className = i === selIdx ? 'selected' : '');
}

function esc(v) { return String(v).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
function fmt2(v) { return Number(v||0).toFixed(2); }

// ─── Load ───
async function doLoad() {
    document.getElementById('statusBar').textContent = 'Loading...';
    const d = await api(`${SITE}/api/kuri-type-master/load`);
    if (!d.ok) { toast(d.message || 'Error', false); return; }
    rows = d.rows || [];
    selIdx = rows.length > 0 ? 0 : -1;
    renderGrid();
    document.getElementById('statusBar').textContent = `Loaded ${rows.length} types`;
}

// ─── Add Row ───
document.getElementById('btnAdd').addEventListener('click', () => {
    rows = collectRows();
    rows.push({ code:'', name:'', instnos:0, instamt:0, totamt:0, bonus:0, colntype:'M', collnmin:0, collnlimit:0, prefix:'', lastno:0, comnrate:0 });
    selIdx = rows.length - 1;
    renderGrid();
    // Focus the new row's code input
    const lastRow = document.querySelector('#tbody tr:last-child');
    if (lastRow) {
        const codeInput = lastRow.querySelector('[data-col="code"]');
        if (codeInput) codeInput.focus();
    }
});

// ─── Delete Row ───
document.getElementById('btnDelete').addEventListener('click', () => {
    rows = collectRows();
    if (selIdx < 0 || selIdx >= rows.length) { toast('Select a row first', false); return; }
    const code = rows[selIdx].code;

    // Check usage if code is not empty
    if (code) {
        api(`${SITE}/api/kuri-type-master/check-usage`, 'POST', { code })
            .then(d => {
                if (d.count > 0) {
                    toast(`Can't delete — ${d.count} customer(s) use this type`, false);
                } else {
                    if (confirm(`Delete row "${code}"?`)) {
                        rows.splice(selIdx, 1);
                        if (selIdx >= rows.length) selIdx = rows.length - 1;
                        renderGrid();
                    }
                }
            });
    } else {
        rows.splice(selIdx, 1);
        if (selIdx >= rows.length) selIdx = rows.length - 1;
        renderGrid();
    }
});

// Delete via × button
document.getElementById('tbody').addEventListener('click', e => {
    const btn = e.target.closest('.del-btn');
    if (!btn) return;
    const idx = parseInt(btn.dataset.idx);
    rows = collectRows();
    const code = rows[idx]?.code || '';

    if (code) {
        api(`${SITE}/api/kuri-type-master/check-usage`, 'POST', { code })
            .then(d => {
                if (d.count > 0) {
                    toast(`Can't delete — ${d.count} customer(s) use this type`, false);
                } else {
                    rows.splice(idx, 1);
                    selIdx = Math.min(selIdx, rows.length - 1);
                    renderGrid();
                }
            });
    } else {
        rows.splice(idx, 1);
        selIdx = Math.min(selIdx, rows.length - 1);
        renderGrid();
    }
});

// ─── Save ───
document.getElementById('btnSave').addEventListener('click', async () => {
    rows = collectRows();
    // Validate
    for (let i = 0; i < rows.length; i++) {
        if (!rows[i].code.trim()) {
            toast(`Row ${i+1}: Code is required`, false);
            return;
        }
        if (!rows[i].name.trim()) {
            toast(`Row ${i+1}: Name is required`, false);
            return;
        }
    }

    // Check for duplicate codes
    const codes = rows.map(r => r.code.trim().toUpperCase());
    const dupes = codes.filter((c, i) => codes.indexOf(c) !== i);
    if (dupes.length > 0) {
        toast(`Duplicate code: ${dupes[0]}`, false);
        return;
    }

    document.getElementById('statusBar').textContent = 'Saving...';
    const d = await api(`${SITE}/api/kuri-type-master/save`, 'POST', { rows });
    if (d.ok) {
        toast(d.message);
        document.getElementById('statusBar').textContent = d.message;
        doLoad(); // Refresh
    } else {
        toast(d.message || 'Save error', false);
    }
});

// ─── Cancel ───
document.getElementById('btnCancel').addEventListener('click', () => { doLoad(); });

// ─── Exit ───
document.getElementById('btnExit').addEventListener('click', () => {
    window.parent.postMessage({ type:'goldapp:close-module-frame' }, '*');
});

// ─── Keyboard ───
document.addEventListener('keydown', e => {
    if (e.key === 'F9') { e.preventDefault(); document.getElementById('btnSave').click(); }
    if (e.key === 'Escape') document.getElementById('btnExit').click();
});

// Init
doLoad();
</script>
</body>
</html>
