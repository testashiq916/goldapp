<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Print Confirm</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#c0c0c0;color:#1a1a2e;font-size:12px;height:100vh;overflow:hidden;display:flex;flex-direction:column}

/* Toolbar */
.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:8px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;flex-shrink:0}
.toolbar h1{color:#fff;font-size:14px;font-weight:700;white-space:nowrap}
.toolbar label{color:#94a3b8;font-size:11px;font-weight:600}
.toolbar input[type=date]{padding:4px 8px;border:1px solid #334155;border-radius:4px;background:#0f172a;color:#e2e8f0;font-size:12px}
.toolbar input[type=date]:focus{outline:none;border-color:#60a5fa}
.btn{padding:5px 14px;border:none;border-radius:4px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.btn:disabled{opacity:.5;cursor:not-allowed}
.btn-primary{background:#3b82f6;color:#fff}.btn-primary:hover{background:#2563eb}
.btn-danger{background:#ef4444;color:#fff}.btn-danger:hover{background:#dc2626}
.btn-green{background:#16a34a;color:#fff}.btn-green:hover{background:#15803d}
.btn-purple{background:#7c3aed;color:#fff}.btn-purple:hover{background:#6d28d9}
.btn-gray{background:#64748b;color:#fff}.btn-gray:hover{background:#475569}
.cbx-lbl{color:#cbd5e1;font-size:11px;cursor:pointer;display:flex;align-items:center;gap:4px}
.cbx-lbl input{accent-color:#60a5fa}
.spacer{flex:1}

/* Grid area */
.grid-wrap{flex:1;overflow:auto;padding:4px}
table{width:100%;border-collapse:collapse;background:#fff;font-size:11px}
thead{position:sticky;top:0;z-index:2}
th{background:#e8e8e8;border:1px solid #999;padding:4px 6px;font-weight:700;text-align:center;font-size:10px;white-space:nowrap;color:#333}
td{border:1px solid #ccc;padding:2px 4px;font-variant-numeric:tabular-nums}
td.r{text-align:right}
td.c{text-align:center}
tr.selected{background:#cde4ff !important}
tr:hover{background:#f0f4ff}
tr.even{background:#f8f8f8}

/* Inline editable cells */
td.editable{background:#fffff0;cursor:text}
td.editable:focus{outline:2px solid #3b82f6;outline-offset:-2px;background:#fff}
td input{width:100%;border:none;background:transparent;font-size:11px;font-family:inherit;padding:0;text-align:right}
td input:focus{outline:none;background:#fff8dc}
td input.left{text-align:left}
td select{width:100%;border:none;background:transparent;font-size:11px;font-family:inherit;padding:0}

/* Bottom bar */
.bottom-bar{background:#e0e0e0;border-top:2px solid #999;padding:6px 12px;display:flex;align-items:center;gap:8px;flex-shrink:0}

/* Modal */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:100}
.modal-bg.show{display:flex}
.modal{background:#fff;border:2px solid #666;border-radius:6px;box-shadow:0 8px 32px rgba(0,0,0,.3);width:min(800px,95vw);max-height:80vh;overflow:auto}
.modal-head{background:linear-gradient(135deg,#1e3a5f,#2c5282);color:#fff;padding:8px 14px;font-weight:700;font-size:13px;display:flex;justify-content:space-between;align-items:center}
.modal-head .close{cursor:pointer;font-size:16px;color:#fff;background:none;border:none}
.modal-body{padding:12px}
.modal-body table{font-size:11px}
.modal-body th{background:#f1f5f9;font-size:10px}

/* Status */
.status{color:#64748b;font-size:11px}
.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:6px;font-size:12px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.3)}
.toast.ok{background:#16a34a}
.toast.err{background:#dc2626}
</style>
</head>
<body>

<div class="toolbar">
    <h1>Print Confirm</h1>
    <div>
        <label>Date</label>
        <input type="date" id="fDate">
    </div>
    <button class="btn btn-primary" onclick="doRefresh()" title="F8">Refresh</button>
    <button class="btn btn-gray" onclick="doView()" title="View">View</button>
    <button class="btn btn-green" id="btnConfirm" onclick="doConfirm()" title="F9">Update &amp; Print</button>
    <button class="btn btn-danger" id="btnDelete" onclick="doDelete()">Delete</button>
    <div class="spacer"></div>
    <label class="cbx-lbl"><input type="checkbox" id="cbxEstToBill"> Est To Bill</label>
</div>

<div class="grid-wrap" id="gridWrap">
    <table id="tbl">
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th>Date</th>
                <th>Bill No</th>
                <th>Customer</th>
                <th>Name</th>
                <th>SM</th>
                <th>Bill Amt</th>
                <th>Discount</th>
                <th>Tax</th>
                <th>Net Amt</th>
                <th>Rcvd Amt</th>
                <th>Balance</th>
                <th>CC Amt</th>
                <th>Bank</th>
                <th>Duedate</th>
                <th>C/O</th>
                <th>Order</th>
                <th>Ctrl</th>
            </tr>
        </thead>
        <tbody id="tbody"></tbody>
    </table>
</div>

<div class="bottom-bar">
    <span class="status" id="statusBar">Ready. Press F8 to refresh.</span>
    <div class="spacer"></div>
    <span class="status" id="rowCount">0 bills</span>
</div>

<!-- View Modal -->
<div class="modal-bg" id="viewModal">
    <div class="modal">
        <div class="modal-head">
            <span id="viewTitle">Bill Details</span>
            <button class="close" onclick="closeView()">&times;</button>
        </div>
        <div class="modal-body" id="viewBody"></div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const CASH_BANKS = @json($cashBanks);
let bills = [];
let selIdx = -1;

const $ = id => document.getElementById(id);
const fmt2 = v => Number(v||0).toFixed(2);
const fmtD = v => { if(!v) return ''; const d=new Date(v); return isNaN(d)?v:d.toLocaleDateString('en-IN',{day:'2-digit',month:'2-digit',year:'numeric'}); };

// Default date = today
$('fDate').value = new Date().toISOString().slice(0,10);

// Auto-load on page open
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
    const date = $('fDate').value;
    if (!date) { toast('Select date', false); return; }
    $('statusBar').textContent = 'Loading...';

    const d = await api(`${SITE}/sales-bill-confirmation/load?date=${date}`);
    if (!d.ok) { toast(d.message||'Error', false); return; }

    bills = d.bills || [];
    renderGrid();
    $('statusBar').textContent = `Loaded ${bills.length} bills for ${fmtD(date)}`;
    $('rowCount').textContent = bills.length + ' bills';

    if (bills.length > 0) selectRow(0);
}

function renderGrid() {
    const tbody = $('tbody');
    tbody.innerHTML = '';

    bills.forEach((b, i) => {
        const tr = document.createElement('tr');
        tr.className = i % 2 ? 'even' : '';
        tr.dataset.idx = i;
        tr.onclick = () => selectRow(i);
        tr.ondblclick = () => doView();

        const bal = Number(b.netamt||0) - Number(b.ramt||0);

        tr.innerHTML = `
            <td class="c">${i+1}</td>
            <td class="c">${fmtD(b.tdate)}</td>
            <td>${b.billno||''}</td>
            <td><input class="left" data-field="custcode" value="${esc(b.custcode||'')}" onchange="cellChange(${i},this)"></td>
            <td><input class="left" data-field="custname" value="${esc(b.custname||'')}" onchange="cellChange(${i},this)"></td>
            <td class="c">${b.smcode||''}</td>
            <td class="r">${fmt2(b.billamt)}</td>
            <td class="r"><input data-field="discount" value="${fmt2(b.discount)}" onchange="cellChange(${i},this)" onfocus="this.select()"></td>
            <td class="r"><input data-field="staxamt" value="${fmt2(b.staxamt)}" onchange="cellChange(${i},this)" onfocus="this.select()"></td>
            <td class="r">${fmt2(b.netamt)}</td>
            <td class="r"><input data-field="ramt" value="${fmt2(b.ramt)}" onchange="cellChange(${i},this)" onfocus="this.select()"></td>
            <td class="r" style="font-weight:700;color:${bal>0?'#dc2626':'#16a34a'}">${fmt2(bal)}</td>
            <td class="r"><input data-field="ccamt" value="${fmt2(b.ccamt)}" onchange="cellChange(${i},this)" onfocus="this.select()"></td>
            <td><select data-field="cbcode" onchange="cellChange(${i},this)">${bankOptions(b.cbcode)}</select></td>
            <td class="c"><input type="date" data-field="duedate" value="${b.duedate||''}" onchange="cellChange(${i},this)" style="font-size:10px;width:110px"></td>
            <td><input class="left" data-field="cocode" value="${esc(b.cocode||'')}" onchange="cellChange(${i},this)" style="width:60px"></td>
            <td class="c">${b.orderno||''}</td>
            <td class="c">${b.control}</td>
        `;
        tbody.appendChild(tr);
    });
}

function esc(s) { return (s||'').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

function bankOptions(sel) {
    let html = '<option value="CASH">CASH</option>';
    CASH_BANKS.forEach(b => {
        html += `<option value="${b.code}" ${b.code===sel?'selected':''}>${b.name||b.code}</option>`;
    });
    return html;
}

function cellChange(idx, el) {
    const field = el.dataset.field;
    let val = el.value;
    const b = bills[idx];

    if (['discount','ramt','staxamt','ccamt'].includes(field)) {
        val = parseFloat(val) || 0;
    }

    b[field] = val;

    // Auto-calc balance when ramt changes
    if (field === 'ramt') {
        const net = Number(b.netamt||0);
        const disc = net - val;
        if (disc >= 0) b.discount = disc;
        // Re-render just this row's discount and balance
        const row = $('tbody').children[idx];
        if (row) {
            const discInput = row.querySelector('[data-field="discount"]');
            if (discInput) discInput.value = fmt2(b.discount);
            updateBalanceCell(row, b);
        }
    }

    if (field === 'discount') {
        const row = $('tbody').children[idx];
        if (row) updateBalanceCell(row, b);
    }
}

function updateBalanceCell(row, b) {
    const bal = Number(b.netamt||0) - Number(b.ramt||0);
    const balCell = row.children[11]; // balance column index
    balCell.innerHTML = fmt2(bal);
    balCell.style.color = bal > 0 ? '#dc2626' : '#16a34a';
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

    if (!confirm(`Update & Print bill ${b.billno}?`)) return;

    $('btnConfirm').disabled = true;
    $('statusBar').textContent = 'Updating...';

    const d = await api(`${SITE}/sales-bill-confirmation/confirm`, 'POST', {
        slno: b.slno,
        discount: parseFloat(b.discount) || 0,
        ramt: parseFloat(b.ramt) || 0,
        custcode: b.custcode || '',
        custname: b.custname || '',
        duedate: b.duedate || null,
        staxamt: parseFloat(b.staxamt) || 0,
        ccamt: parseFloat(b.ccamt) || 0,
        chqamt: parseFloat(b.chqamt) || 0,
        cbcode: b.cbcode || 'CASH',
        cocode: b.cocode || '',
        estToBill: $('cbxEstToBill').checked,
    });

    $('btnConfirm').disabled = false;

    if (d.ok) {
        toast(d.message);
        // Remove from list
        bills.splice(selIdx, 1);
        renderGrid();
        if (bills.length > 0) selectRow(Math.min(selIdx, bills.length - 1));
        $('rowCount').textContent = bills.length + ' bills';
        $('statusBar').textContent = d.message;
    } else {
        toast(d.message || 'Error', false);
        $('statusBar').textContent = d.message || 'Error';
    }
}

async function doDelete() {
    if (selIdx < 0 || !bills[selIdx]) { toast('Select a bill first', false); return; }
    const b = bills[selIdx];

    if (!confirm(`WARNING: Cancel bill ${b.billno}?\nThis will reverse stock and delete all records.\nAre you sure?`)) return;

    $('btnDelete').disabled = true;
    $('statusBar').textContent = 'Cancelling...';

    const d = await api(`${SITE}/sales-bill-confirmation/delete`, 'POST', { slno: b.slno });

    $('btnDelete').disabled = false;

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

    const d = await api(`${SITE}/sales-bill-confirmation/view?slno=${b.slno}`);
    if (!d.ok) { toast(d.message||'Error', false); return; }

    $('viewTitle').textContent = `Bill: ${b.billno} — ${b.custname||'Walk-in'}`;

    let html = '<h4 style="margin:0 0 8px;font-size:12px">Sales Items</h4>';
    html += '<table><thead><tr><th>Code</th><th>Name</th><th>Qty</th><th>Wgt</th><th>Rate</th><th>Amount</th></tr></thead><tbody>';
    (d.items||[]).forEach(it => {
        html += `<tr><td>${it.code}</td><td>${it.name||it.itemname||''}</td><td class="r">${it.qty||0}</td><td class="r">${Number(it.weight||0).toFixed(3)}</td><td class="r">${Number(it.rate||0).toFixed(2)}</td><td class="r">${Number(it.amount||0).toFixed(2)}</td></tr>`;
    });
    html += '</tbody></table>';

    if ((d.srItems||[]).length) {
        html += '<h4 style="margin:12px 0 8px;font-size:12px">Sales Return Items</h4>';
        html += '<table><thead><tr><th>Code</th><th>Name</th><th>Qty</th><th>Wgt</th><th>Amount</th></tr></thead><tbody>';
        d.srItems.forEach(it => {
            html += `<tr><td>${it.code}</td><td>${it.name||it.itemname||''}</td><td class="r">${it.qty||0}</td><td class="r">${Number(it.weight||0).toFixed(3)}</td><td class="r">${Number(it.amount||0).toFixed(2)}</td></tr>`;
        });
        html += '</tbody></table>';
    }

    if ((d.exchItems||[]).length) {
        html += '<h4 style="margin:12px 0 8px;font-size:12px">Exchange Items</h4>';
        html += '<table><thead><tr><th>Code</th><th>Name</th><th>Qty</th><th>Wgt</th><th>Amount</th></tr></thead><tbody>';
        d.exchItems.forEach(it => {
            html += `<tr><td>${it.code}</td><td>${it.name||it.itemname||''}</td><td class="r">${it.qty||0}</td><td class="r">${Number(it.weight||0).toFixed(3)}</td><td class="r">${Number(it.amount||0).toFixed(2)}</td></tr>`;
        });
        html += '</tbody></table>';
    }

    // Bill summary
    const bill = d.bill;
    html += `<div style="margin-top:12px;padding:8px;background:#f8fafc;border-radius:4px;font-size:11px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px">
            <div><b>Bill Amount:</b> ${fmt2(bill.billamt)}</div>
            <div><b>Discount:</b> ${fmt2(bill.discount)}</div>
            <div><b>Tax:</b> ${fmt2(bill.staxamt)}</div>
            <div><b>Net Amount:</b> ${fmt2(bill.netamt)}</div>
            <div><b>Received:</b> ${fmt2(bill.ramt)}</div>
            <div><b>Balance:</b> ${fmt2(bill.netamt - bill.ramt)}</div>
            <div><b>Exchange:</b> ${fmt2(bill.eamt)}</div>
            <div><b>S.Return:</b> ${fmt2(bill.sretamt)}</div>
        </div>
    </div>`;

    $('viewBody').innerHTML = html;
    $('viewModal').classList.add('show');
}

function closeView() { $('viewModal').classList.remove('show'); }

// Keyboard shortcuts
document.addEventListener('keydown', e => {
    if (e.key === 'F8') { e.preventDefault(); doRefresh(); }
    if (e.key === 'F9') { e.preventDefault(); doConfirm(); }
    if (e.key === 'Escape') closeView();

    // Arrow keys for row navigation
    if (e.key === 'ArrowDown' && selIdx < bills.length - 1) { e.preventDefault(); selectRow(selIdx + 1); }
    if (e.key === 'ArrowUp' && selIdx > 0) { e.preventDefault(); selectRow(selIdx - 1); }
});
</script>
</body>
</html>
