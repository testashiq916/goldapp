<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Payment Confirmation' }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,sans-serif;font-size:13px;background:#f0f2f5;padding:8px}
.card{background:#fff;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,.1);padding:10px 14px;margin-bottom:6px}
h3{color:#2c3e50;font-size:15px;margin-bottom:10px}
.toolbar{display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
.btn{padding:6px 18px;border:none;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer;color:#fff}
.btn-confirm{background:#27ae60}.btn-confirm:hover{background:#219a52}
.btn-delete{background:#e74c3c}.btn-delete:hover{background:#c0392b}
.btn-refresh{background:#3498db}.btn-refresh:hover{background:#2980b9}
.btn-exit{background:#95a5a6}.btn-exit:hover{background:#7f8c8d}
.btn:disabled{opacity:.5;cursor:not-allowed}
.info{color:#888;font-size:11px;margin-left:auto}

/* Grid */
.grid-wrap{border:1px solid #ccc;border-radius:4px;overflow:auto;max-height:520px}
table{width:100%;border-collapse:collapse;min-width:800px}
thead{background:#2c3e50;color:#fff;position:sticky;top:0;z-index:2}
th{padding:7px 8px;font-size:11px;text-align:left;white-space:nowrap;font-weight:600}
td{padding:5px 8px;border-bottom:1px solid #eee;font-size:12px}
tr:hover{background:#fef9e7}
tr.selected{background:#d5f5e3}
.r{text-align:right}

/* Footer totals */
tfoot td{background:#ecf0f1;font-weight:700;font-size:12px}

.no-data{text-align:center;padding:30px;color:#888;font-size:14px}
</style>
</head>
<body>

<div class="card">
    <h3>Payment Confirmation Entry</h3>

    <div class="toolbar">
        <button class="btn btn-delete" id="btnDelete" disabled>Delete</button>
        <button class="btn btn-refresh" id="btnRefresh">Refresh</button>
        <button class="btn btn-confirm" id="btnConfirm" disabled>Confirm</button>
        <button class="btn btn-exit" id="btnExit">Exit</button>
        <span class="info" id="statusInfo"></span>
    </div>

    <div class="grid-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:100px">Doc. No.</th>
                    <th style="width:90px">Date</th>
                    <th>Account</th>
                    <th>Cash/Bank Account</th>
                    <th class="r" style="width:110px">Paid Amt</th>
                    <th style="width:100px">Cheque No</th>
                </tr>
            </thead>
            <tbody id="gridBody">
                <tr><td colspan="6" class="no-data">Loading...</td></tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="r">Total :</td>
                    <td class="r" id="totalAmt">0.00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
const BASE = '{{ url("/") }}';
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

function $(s){ return document.querySelector(s) }
function fmt(v,d=2){ return parseFloat(v||0).toFixed(d) }
function api(url, opts={}){
    opts.headers = opts.headers || {};
    opts.headers['X-CSRF-TOKEN'] = CSRF;
    opts.credentials = 'same-origin';
    return fetch(url, opts).then(r=>r.json());
}
function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }

let rows = [];
let selectedIdx = -1;
let userAllowed = false;

/* ── Load data ── */
document.addEventListener('DOMContentLoaded', loadData);

async function loadData(){
    selectedIdx = -1;
    const d = await api(BASE+'/api/payment-confirmation/load');
    if(!d.ok){ $('#gridBody').innerHTML='<tr><td colspan="6" class="no-data">Error loading</td></tr>'; return; }
    rows = d.rows || [];
    userAllowed = d.allowed || false;
    renderGrid();
    updateButtons();
    $('#statusInfo').textContent = rows.length + ' pending entries';
}

function renderGrid(){
    const tb = $('#gridBody');
    if(rows.length === 0){
        tb.innerHTML = '<tr><td colspan="6" class="no-data">No pending payment entries</td></tr>';
        $('#totalAmt').textContent = '0.00';
        return;
    }
    tb.innerHTML = '';
    let total = 0;
    rows.forEach((r,i)=>{
        const tr = document.createElement('tr');
        if(i === selectedIdx) tr.classList.add('selected');
        tr.onclick = ()=>{ selectedIdx = i; renderGrid(); updateButtons(); };
        const dateStr = r.tdate ? new Date(r.tdate).toLocaleDateString('en-GB') : '';
        tr.innerHTML = `
            <td>${esc(r.vchno)}</td>
            <td>${dateStr}</td>
            <td>${esc(r.acname)}</td>
            <td>${esc(r.cbacname)}</td>
            <td class="r">${fmt(r.amount)}</td>
            <td>${esc(r.chequeno)}</td>
        `;
        tb.appendChild(tr);
        total += r.amount;
    });
    $('#totalAmt').textContent = fmt(total);
}

function updateButtons(){
    const hasSelection = selectedIdx >= 0 && selectedIdx < rows.length;
    $('#btnConfirm').disabled = !hasSelection || !userAllowed;
    $('#btnDelete').disabled = !hasSelection || !userAllowed;
}

/* ── Confirm ── */
$('#btnConfirm').onclick = async ()=>{
    if(selectedIdx < 0) return;
    const r = rows[selectedIdx];
    if(!confirm('Do you want to confirm the entry (' + r.vchno + ')?')) return;

    const d = await api(BASE+'/api/payment-confirmation/confirm', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ slno: r.slno, vchno: r.vchno })
    });
    alert(d.message || (d.ok ? 'Confirmed' : 'Error'));
    if(d.ok){
        rows.splice(selectedIdx, 1);
        selectedIdx = -1;
        renderGrid();
        updateButtons();
        $('#statusInfo').textContent = rows.length + ' pending entries';
    }
};

/* ── Delete ── */
$('#btnDelete').onclick = async ()=>{
    if(selectedIdx < 0) return;
    const r = rows[selectedIdx];
    if(!confirm('Do you want to cancel this entry (' + r.vchno + ')?')) return;

    const d = await api(BASE+'/api/payment-confirmation/cancel', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ slno: r.slno, vchno: r.vchno })
    });
    alert(d.message || (d.ok ? 'Cancelled' : 'Error'));
    if(d.ok){
        rows.splice(selectedIdx, 1);
        selectedIdx = -1;
        renderGrid();
        updateButtons();
        $('#statusInfo').textContent = rows.length + ' pending entries';
    }
};

/* ── Refresh ── */
$('#btnRefresh').onclick = loadData;

/* ── Exit ── */
$('#btnExit').onclick = ()=> window.parent.postMessage({type:'module-close'}, '*');

/* ── Global keys ── */
document.addEventListener('keydown', e=>{
    if(e.key === 'Escape') window.parent.postMessage({type:'module-close'}, '*');
});
</script>
</body>
</html>
