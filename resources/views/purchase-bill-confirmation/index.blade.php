<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Purchase Confirmation Entry</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#c0c0c0;color:#1a1a2e;font-size:12px;height:100vh;overflow:hidden;display:flex;flex-direction:column}

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:8px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;flex-shrink:0}
.toolbar h1{color:#fff;font-size:14px;font-weight:700;white-space:nowrap}
.btn{padding:5px 14px;border:none;border-radius:4px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.btn:disabled{opacity:.5;cursor:not-allowed}
.btn-primary{background:#3b82f6;color:#fff}.btn-primary:hover{background:#2563eb}
.btn-danger{background:#ef4444;color:#fff}.btn-danger:hover{background:#dc2626}
.btn-green{background:#16a34a;color:#fff}.btn-green:hover{background:#15803d}
.btn-gray{background:#64748b;color:#fff}.btn-gray:hover{background:#475569}
.spacer{flex:1}

.grid-wrap{flex:1;overflow:auto;padding:4px}
table{width:100%;border-collapse:collapse;background:#fff;font-size:11px}
thead{position:sticky;top:0;z-index:2}
th{background:#e8e8e8;border:1px solid #999;padding:5px 8px;font-weight:700;text-align:center;font-size:10px;white-space:nowrap;color:#333}
td{border:1px solid #ccc;padding:3px 6px;font-variant-numeric:tabular-nums}
td.r{text-align:right}
td.c{text-align:center}
tr.selected{background:#cde4ff !important}
tr:hover{background:#f0f4ff;cursor:pointer}
tr.even{background:#f8f8f8}

.bottom-bar{background:#e0e0e0;border-top:2px solid #999;padding:6px 12px;display:flex;align-items:center;gap:8px;flex-shrink:0}
.status{color:#64748b;font-size:11px}

/* Modal */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:100}
.modal-bg.show{display:flex}
.modal{background:#fff;border:2px solid #666;border-radius:6px;box-shadow:0 8px 32px rgba(0,0,0,.3);width:min(700px,95vw);max-height:80vh;overflow:auto}
.modal-head{background:linear-gradient(135deg,#1e3a5f,#2c5282);color:#fff;padding:8px 14px;font-weight:700;font-size:13px;display:flex;justify-content:space-between;align-items:center}
.modal-head .close{cursor:pointer;font-size:16px;color:#fff;background:none;border:none}
.modal-body{padding:12px}
.modal-body table{font-size:11px}
.modal-body th{background:#f1f5f9;font-size:10px}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:6px;font-size:12px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.3)}
.toast.ok{background:#16a34a}
.toast.err{background:#dc2626}
</style>
</head>
<body>

<div class="toolbar">
    <h1>Purchase Confirmation Entry</h1>
    <button class="btn btn-primary" onclick="doRefresh()">Refresh</button>
    <button class="btn btn-gray" onclick="doView()">View</button>
    <button class="btn btn-green" onclick="doConfirm()">Confirm</button>
    <button class="btn btn-danger" onclick="doDelete()">Delete</button>
    <div class="spacer"></div>
</div>

<div class="grid-wrap">
    <table>
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th>Doc. No</th>
                <th>Date</th>
                <th>Bill No</th>
                <th>Party Name</th>
                <th>Bill Amt</th>
                <th>Net Amt</th>
                <th>Paid Amt</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody id="tbody"></tbody>
    </table>
</div>

<div class="bottom-bar">
    <span class="status" id="statusBar">Ready</span>
    <div class="spacer"></div>
    <span class="status" id="rowCount">0 bills</span>
</div>

<!-- View Modal -->
<div class="modal-bg" id="viewModal">
    <div class="modal">
        <div class="modal-head">
            <span id="viewTitle">Purchase Bill Details</span>
            <button class="close" onclick="closeView()">&times;</button>
        </div>
        <div class="modal-body" id="viewBody"></div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
let bills = [];
let selIdx = -1;

const $ = id => document.getElementById(id);
const fmt2 = v => Number(v||0).toFixed(2);
const fmtD = v => { if(!v) return ''; const d=new Date(v); return isNaN(d)?v:d.toLocaleDateString('en-IN',{day:'2-digit',month:'2-digit',year:'numeric'}); };

setTimeout(() => doRefresh(), 200);

function toast(msg, ok=true) {
    const t = $('toast');
    t.textContent = msg;
    t.className = 'toast ' + (ok ? 'ok' : 'err');
    t.style.display = 'block';
    setTimeout(() => t.style.display='none', 3000);
}

async function api(url, method='GET', body=null) {
    const opts = { method, headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'} };
    if (body) {
        opts.headers['Content-Type'] = 'application/json';
        opts.headers['X-CSRF-TOKEN'] = document.querySelector('meta[name=csrf-token]').content;
        opts.body = JSON.stringify(body);
    }
    const r = await fetch(url, opts);
    return r.json();
}

async function doRefresh() {
    $('statusBar').textContent = 'Loading...';
    const d = await api(`${SITE}/purchase-bill-confirmation/load`);
    if (!d.ok) { toast(d.message||'Error', false); return; }

    bills = d.bills || [];
    renderGrid();
    $('statusBar').textContent = `Loaded ${bills.length} pending purchase bills`;
    $('rowCount').textContent = bills.length + ' bills';
    if (bills.length > 0) selectRow(0);
}

function renderGrid() {
    const tbody = $('tbody');
    tbody.innerHTML = '';

    bills.forEach((b, i) => {
        const tr = document.createElement('tr');
        tr.className = i % 2 ? 'even' : '';
        tr.onclick = () => selectRow(i);
        tr.ondblclick = () => doView();

        const bal = Number(b.netamt||0) - Number(b.pamt||0);

        tr.innerHTML = `
            <td class="c">${i+1}</td>
            <td>${b.docno||''}</td>
            <td class="c">${fmtD(b.tdate)}</td>
            <td>${b.billno||''}</td>
            <td>${b.name||''}</td>
            <td class="r">${fmt2(b.billamt)}</td>
            <td class="r">${fmt2(b.netamt)}</td>
            <td class="r">${fmt2(b.pamt)}</td>
            <td class="r" style="font-weight:700;color:${bal>0?'#dc2626':'#16a34a'}">${fmt2(bal)}</td>
        `;
        tbody.appendChild(tr);
    });
}

function selectRow(idx) {
    selIdx = idx;
    const rows = $('tbody').children;
    for (let r of rows) r.classList.remove('selected');
    if (rows[idx]) {
        rows[idx].classList.add('selected');
        rows[idx].scrollIntoView({ block: 'nearest' });
    }
}

async function doConfirm() {
    if (selIdx < 0 || !bills[selIdx]) { toast('Select a bill first', false); return; }
    const b = bills[selIdx];

    if (!confirm(`Confirm entry ${b.docno}?`)) return;

    const d = await api(`${SITE}/purchase-bill-confirmation/confirm`, 'POST', { slno: b.slno });

    if (d.ok) {
        toast(d.message);
        bills.splice(selIdx, 1);
        renderGrid();
        if (bills.length > 0) selectRow(Math.min(selIdx, bills.length - 1));
        $('rowCount').textContent = bills.length + ' bills';
        $('statusBar').textContent = d.message;
    } else {
        toast(d.message || 'Error', false);
    }
}

async function doDelete() {
    if (selIdx < 0 || !bills[selIdx]) { toast('Select a bill first', false); return; }
    const b = bills[selIdx];

    if (!confirm(`WARNING: Cancel purchase bill ${b.docno}?\nThis will reverse stock and delete all records.\nAre you sure?`)) return;

    const d = await api(`${SITE}/purchase-bill-confirmation/delete`, 'POST', { slno: b.slno });

    if (d.ok) {
        toast(d.message);
        bills.splice(selIdx, 1);
        renderGrid();
        if (bills.length > 0) selectRow(Math.min(selIdx, bills.length - 1));
        $('rowCount').textContent = bills.length + ' bills';
        $('statusBar').textContent = d.message;
    } else {
        toast(d.message || 'Error', false);
    }
}

async function doView() {
    if (selIdx < 0 || !bills[selIdx]) { toast('Select a bill first', false); return; }
    const b = bills[selIdx];

    const d = await api(`${SITE}/purchase-bill-confirmation/view?slno=${b.slno}`);
    if (!d.ok) { toast(d.message||'Error', false); return; }

    $('viewTitle').textContent = `Purchase: ${b.docno} — ${b.name||''}`;

    let html = '<table><thead><tr><th>Code</th><th>Name</th><th>Qty</th><th>Weight</th><th>Rate</th><th>Amount</th></tr></thead><tbody>';
    (d.items||[]).forEach(it => {
        html += `<tr><td>${it.code}</td><td>${it.name||it.itemname||''}</td><td class="r">${it.qty||0}</td><td class="r">${Number(it.weight||0).toFixed(3)}</td><td class="r">${Number(it.rate||0).toFixed(2)}</td><td class="r">${Number(it.amount||0).toFixed(2)}</td></tr>`;
    });
    html += '</tbody></table>';

    const bill = d.bill;
    html += `<div style="margin-top:12px;padding:8px;background:#f8fafc;border-radius:4px;font-size:11px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px">
            <div><b>Bill Amount:</b> ${fmt2(bill.billamt)}</div>
            <div><b>Net Amount:</b> ${fmt2(bill.netamt)}</div>
            <div><b>Paid Amount:</b> ${fmt2(bill.pamt)}</div>
            <div><b>Balance:</b> ${fmt2(bill.netamt - bill.pamt)}</div>
            <div><b>Supplier:</b> ${bill.suppcode||''} — ${bill.name||''}</div>
            <div><b>Date:</b> ${fmtD(bill.tdate)}</div>
        </div>
    </div>`;

    $('viewBody').innerHTML = html;
    $('viewModal').classList.add('show');
}

function closeView() { $('viewModal').classList.remove('show'); }

// Keyboard shortcuts
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeView();
    if (e.key === 'ArrowDown' && selIdx < bills.length - 1) { e.preventDefault(); selectRow(selIdx + 1); }
    if (e.key === 'ArrowUp' && selIdx > 0) { e.preventDefault(); selectRow(selIdx - 1); }
    if (e.key === 'Enter' && selIdx >= 0) doView();
});
</script>
</body>
</html>
