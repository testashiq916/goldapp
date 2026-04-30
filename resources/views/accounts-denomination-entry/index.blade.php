<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Denomination Entry' }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,sans-serif;font-size:13px;background:#f0f2f5;display:flex;justify-content:center;padding-top:16px}
.wrap{width:560px}
.card{background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.12);padding:16px 20px;margin-bottom:8px}
h3{color:#2c3e50;font-size:15px;margin-bottom:10px;border-bottom:2px solid #2c3e50;padding-bottom:4px}
.row{display:flex;align-items:center;gap:10px;margin-bottom:8px}
label{font-weight:600;min-width:50px;font-size:12px;color:#555}
input{border:1px solid #bbb;border-radius:4px;padding:5px 8px;font-size:13px;outline:none}
input:focus{border-color:#e67e22;background:#fff5eb}
input[readonly]{background:#f5f5f5;color:#333;font-weight:700}
.r{text-align:right}

/* Grid */
.grid-wrap{border:1px solid #ccc;border-radius:4px;overflow:auto;max-height:400px}
table{width:100%;border-collapse:collapse}
thead{background:#2c3e50;color:#fff;position:sticky;top:0;z-index:2}
th{padding:6px 10px;font-size:11px;text-align:left;font-weight:600}
td{padding:4px 8px;border-bottom:1px solid #eee;font-size:12px}
tr:hover{background:#fef9e7}
td input{width:80px;border:1px solid #ddd;border-radius:3px;padding:3px 6px;font-size:12px;text-align:right}
td input:focus{border-color:#e67e22;background:#fff5eb}

/* Summary */
.summary{background:#ecf0f1;border-radius:6px;padding:10px 16px;margin-top:8px}
.summary .srow{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
.summary .srow:last-child{margin-bottom:0}
.summary .slbl{font-weight:700;font-size:13px;color:#2c3e50}
.summary .sval{font-weight:700;font-size:14px;color:#c0392b}
.summary .diff-pos{color:#27ae60}
.summary .diff-neg{color:#e74c3c}

.btn{padding:7px 20px;border:none;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer;color:#fff}
.btn-save{background:#27ae60}.btn-save:hover{background:#219a52}
.btn-cancel{background:#e67e22}.btn-cancel:hover{background:#d35400}
.btn-exit{background:#95a5a6}.btn-exit:hover{background:#7f8c8d}
.btn-row{display:flex;justify-content:center;gap:8px;margin-top:12px}
</style>
</head>
<body>

<div class="wrap">
    <div class="card">
        <h3>Denomination Daily Entry</h3>
        <div class="row">
            <label>Date</label>
            <input type="date" id="tdate" style="width:150px">
            <label style="margin-left:16px">Cash</label>
            <input type="text" id="cashBal" class="r" readonly value="0.00" style="width:130px">
            <label style="margin-left:10px">Diff</label>
            <input type="text" id="diffAmt" class="r" readonly value="0.00" style="width:120px">
        </div>
    </div>

    <div class="card">
        <div class="grid-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:200px">Denomination</th>
                        <th class="r" style="width:100px">Nos</th>
                        <th class="r" style="width:140px">Total Value</th>
                    </tr>
                </thead>
                <tbody id="gridBody"></tbody>
            </table>
        </div>

        <div class="summary">
            <div class="srow">
                <span class="slbl">TOTAL</span>
                <span class="sval" id="totalVal">0.00</span>
            </div>
            <div class="srow">
                <span class="slbl">Cash</span>
                <span class="sval" id="summCash">0.00</span>
            </div>
            <div class="srow">
                <span class="slbl">Difference</span>
                <span class="sval" id="summDiff">0.00</span>
            </div>
        </div>

        <div class="btn-row">
            <button class="btn btn-cancel" id="btnCancel">Cancel</button>
            <button class="btn btn-save" id="btnSave">Save (F9)</button>
            <button class="btn btn-exit" id="btnExit">Exit (Esc)</button>
        </div>
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

let denomRows = [];
let cashBalance = 0;

/* ── Init ── */
document.addEventListener('DOMContentLoaded', ()=>{
    $('#tdate').value = new Date().toISOString().slice(0,10);
    loadData();
});

$('#tdate').addEventListener('change', loadData);

async function loadData(){
    const tdate = $('#tdate').value;
    const d = await api(BASE+'/api/denomination-entry/load?tdate='+tdate);
    if(!d.ok) return;
    denomRows = d.rows || [];
    cashBalance = d.cash || 0;
    $('#cashBal').value = fmt(cashBalance);
    renderGrid();
}

/* ── Render grid ── */
function renderGrid(){
    const tb = $('#gridBody');
    tb.innerHTML = '';
    denomRows.forEach((r,i)=>{
        const amt = r.cvalue * r.nos;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${r.name} (${fmt(r.cvalue,0)})</td>
            <td><input value="${r.nos||''}" data-idx="${i}" type="number" min="0"></td>
            <td class="r">${fmt(amt)}</td>
        `;
        tb.appendChild(tr);
    });

    // Bind inputs
    tb.querySelectorAll('input').forEach(inp=>{
        inp.addEventListener('change', e=>{
            const idx = parseInt(e.target.dataset.idx);
            denomRows[idx].nos = parseInt(e.target.value)||0;
            renderGrid();
        });
        inp.addEventListener('input', e=>{
            const idx = parseInt(e.target.dataset.idx);
            denomRows[idx].nos = parseInt(e.target.value)||0;
            updateTotals();
        });
    });
    updateTotals();
}

function updateTotals(){
    const total = denomRows.reduce((s,r)=> s + (r.cvalue * r.nos), 0);
    const diff = cashBalance - total;
    $('#totalVal').textContent = fmt(total);
    $('#summCash').textContent = fmt(cashBalance);
    $('#summDiff').textContent = fmt(diff);
    $('#diffAmt').value = fmt(diff);

    // Color the diff
    const diffEl = $('#summDiff');
    diffEl.className = 'sval ' + (diff >= 0 ? 'diff-pos' : 'diff-neg');
}

/* ── Cancel (reset counts) ── */
$('#btnCancel').onclick = ()=>{
    denomRows.forEach(r => r.nos = 0);
    renderGrid();
};

/* ── Save ── */
$('#btnSave').onclick = doSave;
async function doSave(){
    const d = await api(BASE+'/api/denomination-entry/save', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            tdate: $('#tdate').value,
            items: denomRows.map(r=>({ code: r.code, cvalue: r.cvalue, nos: r.nos }))
        })
    });
    alert(d.message || (d.ok ? 'Saved' : 'Error'));
}

/* ── Exit ── */
$('#btnExit').onclick = ()=> window.parent.postMessage({type:'module-close'}, '*');

/* ── Global keys ── */
document.addEventListener('keydown', e=>{
    if(e.key === 'F9'){ e.preventDefault(); doSave(); }
    if(e.key === 'Escape') window.parent.postMessage({type:'module-close'}, '*');
});
</script>
</body>
</html>
