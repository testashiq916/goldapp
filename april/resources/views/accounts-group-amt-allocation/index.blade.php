<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Group Amt Allocation' }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,sans-serif;font-size:13px;background:#f0f2f5;padding:6px}
.card{background:#fff;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,.1);padding:10px 14px;margin-bottom:6px}
.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:6px}
label{font-weight:600;white-space:nowrap;min-width:100px;text-align:right;font-size:12px}
input,select{border:1px solid #bbb;border-radius:4px;padding:4px 8px;font-size:13px;outline:none}
input:focus,select:focus{border-color:#e67e22;background:#fff5eb}
.inp-sm{width:120px}
.inp-md{width:200px}
.inp-lg{width:300px}
.btn{padding:5px 16px;border:none;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer;color:#fff}
.btn-show{background:#3498db}.btn-show:hover{background:#2980b9}
.btn-alloc{background:#e67e22}.btn-alloc:hover{background:#d35400}
.btn-save{background:#27ae60}.btn-save:hover{background:#219a52}
.btn-def{background:#8e44ad}.btn-def:hover{background:#7d3c98}
.btn-exit{background:#e74c3c}.btn-exit:hover{background:#c0392b}
.btn-del{background:#95a5a6}.btn-del:hover{background:#7f8c8d}
.btn:disabled{opacity:.5;cursor:not-allowed}

/* Grid */
.grid-wrap{border:1px solid #ccc;border-radius:4px;overflow:auto;max-height:420px;margin:6px 0}
table{width:100%;border-collapse:collapse;min-width:700px}
thead{background:#2c3e50;color:#fff;position:sticky;top:0;z-index:2}
th{padding:6px 8px;font-size:11px;text-align:left;white-space:nowrap;font-weight:600}
td{padding:4px 6px;border-bottom:1px solid #eee;font-size:12px}
tr:hover{background:#fef9e7}
tr.selected{background:#d5f5e3}
.r{text-align:right}
.c{text-align:center}
td input{width:100%;border:1px solid #ddd;border-radius:3px;padding:3px 6px;font-size:12px}
td input:focus{border-color:#e67e22;background:#fff5eb}
td input.r{text-align:right}
td select{width:100%;border:1px solid #ddd;border-radius:3px;padding:2px 4px;font-size:12px}

.footer-row{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.footer-totals{display:flex;gap:16px;align-items:center}
.footer-totals span{font-weight:600;font-size:13px}
.footer-totals .val{color:#c0392b;font-size:14px;font-weight:700}

/* Checkbox */
.chk-label{display:flex;align-items:center;gap:4px;font-weight:600;cursor:pointer}
.chk-label input{width:16px;height:16px}

/* Search Modal */
.modal-bg{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.35);z-index:100;justify-content:center;align-items:center}
.modal-bg.active{display:flex}
.modal{background:#fff;border-radius:8px;width:460px;max-height:70vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.25)}
.modal-head{padding:10px 14px;background:#2c3e50;color:#fff;border-radius:8px 8px 0 0;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.modal-head .close{cursor:pointer;font-size:18px}
.modal-body{padding:10px;overflow:auto;flex:1}
.modal-body input{width:100%;margin-bottom:8px;padding:6px 10px;border:1px solid #ccc;border-radius:4px}
.modal-body table{width:100%}
.modal-body th{background:#ecf0f1;color:#2c3e50;padding:4px 6px;font-size:11px}
.modal-body td{padding:4px 6px;cursor:pointer;font-size:12px}
.modal-body tr:hover{background:#d5f5e3}

.hint{color:#888;font-size:11px;margin-left:6px}
</style>
</head>
<body>

{{-- Header --}}
<div class="card">
    <div class="row">
        <label>A/c Group :</label>
        <select id="grp" class="inp-md" data-nav="1"></select>

        <label>Date :</label>
        <input type="date" id="tdate" class="inp-sm" data-nav="2">

        <button class="btn btn-show" id="btnShow" data-nav="3">Show</button>
    </div>
    <div class="row">
        <label>Total Amount :</label>
        <input type="text" id="totamt" class="inp-md r" value="0.00" data-nav="4">

        <label>Amt % :</label>
        <input type="text" id="amtperc" class="inp-sm r" value="0.00" data-nav="5">

        <button class="btn btn-alloc" id="btnAlloc" data-nav="6">Allocate</button>
    </div>
</div>

{{-- Grid --}}
<div class="card">
    <div class="grid-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:30px">#</th>
                    <th style="width:240px">Name</th>
                    <th style="width:200px">Allocn Account</th>
                    <th style="width:80px" class="r">Amt%</th>
                    <th style="width:120px" class="r">Balance</th>
                    <th style="width:120px" class="r">Amount</th>
                </tr>
            </thead>
            <tbody id="gridBody"></tbody>
            <tfoot>
                <tr style="background:#ecf0f1;font-weight:700">
                    <td colspan="4" class="r">Total :</td>
                    <td class="r" id="totalBal">0.00</td>
                    <td class="r" id="totalAmt">0.00</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="row" style="margin-top:4px">
        <button class="btn btn-del" id="btnDelete">Delete Row</button>
        <span class="hint">Select a row and click Delete to remove it</span>
    </div>
</div>

{{-- Footer --}}
<div class="card">
    <div class="row">
        <label>Particulars :</label>
        <input type="text" id="particular" class="inp-lg" maxlength="40" data-nav="7" style="text-transform:uppercase">
    </div>
    <div class="row">
        <label id="lblDbCr">Debit A/c :</label>
        <input type="text" id="opac" class="inp-md" style="text-transform:uppercase" data-nav="8" placeholder="F1 to search">
        <span id="opacName" style="font-size:12px;color:#555"></span>

        <label class="chk-label" style="min-width:auto;margin-left:20px">
            <input type="checkbox" id="credited" checked>
            Credited
        </label>
    </div>
    <div class="row" style="margin-top:8px;justify-content:flex-end;gap:8px">
        <button class="btn btn-def" id="btnSetDef">Set Def</button>
        <button class="btn btn-save" id="btnSave">Save (F9)</button>
        <button class="btn btn-exit" id="btnExit">Exit (Esc)</button>
    </div>
</div>

{{-- Account Search Modal --}}
<div class="modal-bg" id="acModal">
    <div class="modal">
        <div class="modal-head">
            <span>Search Account</span>
            <span class="close" id="acModalClose">&times;</span>
        </div>
        <div class="modal-body">
            <input type="text" id="acModalSearch" placeholder="Type code or name...">
            <table>
                <thead><tr><th>Code</th><th>Name</th></tr></thead>
                <tbody id="acModalBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
const BASE = '{{ url("/") }}';
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let gridData = [];
let selectedRow = -1;
let acModalTarget = null; // 'opac' or row index for aclink

/* ── Helpers ── */
function $(s){ return document.querySelector(s) }
function $$(s){ return document.querySelectorAll(s) }
function fmt(v,d=2){ return parseFloat(v||0).toFixed(d) }
function api(url, opts={}){
    opts.headers = opts.headers || {};
    opts.headers['X-CSRF-TOKEN'] = CSRF;
    opts.credentials = 'same-origin';
    return fetch(url, opts).then(r=>r.json());
}

/* ── Init ── */
document.addEventListener('DOMContentLoaded', async ()=>{
    $('#tdate').value = new Date().toISOString().slice(0,10);

    // Load group codes
    const d = await api(BASE+'/api/group-amt-allocation/groups');
    if(d.ok){
        const sel = $('#grp');
        sel.innerHTML = '<option value="">-- Select --</option>';
        d.rows.forEach(g => {
            const o = document.createElement('option');
            o.value = g; o.textContent = g;
            sel.appendChild(o);
        });
    }
});

/* ── Show button ── */
$('#btnShow').onclick = async ()=>{
    const grp = $('#grp').value;
    const tdate = $('#tdate').value;
    if(!grp){ alert('Select a group'); return; }
    const d = await api(BASE+'/api/group-amt-allocation/show?grp='+encodeURIComponent(grp)+'&tdate='+tdate);
    if(!d.ok){ alert(d.message||'Error'); return; }
    gridData = d.rows.map(r=>({
        accode: r.accode,
        name: r.name,
        aclink: r.aclink||'',
        acperc: r.acperc||0,
        balance: r.balance||0,
        amount: 0
    }));
    selectedRow = -1;
    renderGrid();
};

/* ── Allocate button ── */
$('#btnAlloc').onclick = ()=>{
    const totamt = parseFloat($('#totamt').value)||0;
    const perc = parseFloat($('#amtperc').value)||0;
    const totalBal = gridData.reduce((s,r)=>s+r.balance, 0);

    gridData.forEach(r=>{
        let alloc = 0;
        if(r.acperc > 0){
            alloc = Math.round(r.balance * r.acperc / 100);
        } else if(perc > 0 && totamt === 0){
            alloc = Math.round(r.balance * perc / 100);
        } else if(totamt > 0 && perc === 0 && totalBal > 0){
            alloc = (totamt * r.balance) / totalBal;
        }
        r.amount = Math.round(alloc);
    });
    renderGrid();
};

/* ── Render grid ── */
function renderGrid(){
    const tb = $('#gridBody');
    tb.innerHTML = '';
    gridData.forEach((r,i)=>{
        const tr = document.createElement('tr');
        if(i === selectedRow) tr.classList.add('selected');
        tr.onclick = ()=>{ selectedRow = i; renderGrid(); };
        tr.innerHTML = `
            <td class="c">${i+1}</td>
            <td>${esc(r.name)}</td>
            <td><input value="${esc(r.aclink)}" data-field="aclink" data-idx="${i}"
                 onfocus="this.style.background='#fff5eb'"
                 onblur="this.style.background=''" placeholder="F1 search"></td>
            <td><input class="r" value="${fmt(r.acperc)}" data-field="acperc" data-idx="${i}"></td>
            <td class="r">${fmt(r.balance)}</td>
            <td><input class="r" value="${fmt(r.amount)}" data-field="amount" data-idx="${i}"></td>
        `;
        tb.appendChild(tr);
    });

    // Bind input changes
    tb.querySelectorAll('input').forEach(inp=>{
        inp.addEventListener('change', e=>{
            const idx = parseInt(e.target.dataset.idx);
            const field = e.target.dataset.field;
            if(field === 'aclink'){
                gridData[idx].aclink = e.target.value.toUpperCase().trim();
            } else {
                gridData[idx][field] = parseFloat(e.target.value)||0;
            }
            updateTotals();
        });
        // F1 on aclink field
        if(inp.dataset.field === 'aclink'){
            inp.addEventListener('keydown', e=>{
                if(e.key === 'F1'){
                    e.preventDefault();
                    acModalTarget = parseInt(inp.dataset.idx);
                    openAcModal();
                }
            });
        }
    });
    updateTotals();
}

function updateTotals(){
    const totalBal = gridData.reduce((s,r)=>s+r.balance, 0);
    const totalAmt = gridData.reduce((s,r)=>s+r.amount, 0);
    $('#totalBal').textContent = fmt(totalBal);
    $('#totalAmt').textContent = fmt(totalAmt);
}

function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }

/* ── Delete row ── */
$('#btnDelete').onclick = ()=>{
    if(selectedRow < 0 || selectedRow >= gridData.length){ alert('Select a row first'); return; }
    gridData.splice(selectedRow, 1);
    selectedRow = -1;
    renderGrid();
};

/* ── Credited checkbox → label toggle ── */
$('#credited').onchange = ()=>{
    $('#lblDbCr').textContent = $('#credited').checked ? 'Debit A/c :' : 'Credit A/c :';
};

/* ── Set Def ── */
$('#btnSetDef').onclick = async ()=>{
    if(gridData.length === 0){ alert('No data'); return; }
    const items = gridData.map(r=>({ accode: r.accode, aclink: r.aclink, acperc: r.acperc }));
    const d = await api(BASE+'/api/group-amt-allocation/set-def', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ items })
    });
    alert(d.message || (d.ok ? 'Saved' : 'Error'));
};

/* ── Save ── */
$('#btnSave').onclick = doSave;
async function doSave(){
    if(gridData.length === 0){ alert('No data to save'); return; }
    const totalAmt = gridData.reduce((s,r)=>s+r.amount, 0);
    if(totalAmt <= 0){ alert('Total amount must be > 0'); return; }
    const opac = $('#opac').value.trim().toUpperCase();
    if(!opac){ alert('Enter the Debit/Credit A/c'); $('#opac').focus(); return; }

    if(!confirm('Do you want to save?')) return;

    const payload = {
        tdate: $('#tdate').value,
        particular: $('#particular').value.trim(),
        opac: opac,
        credited: $('#credited').checked ? 1 : 0,
        items: gridData.map(r=>({
            accode: r.accode,
            aclink: r.aclink,
            acperc: r.acperc,
            amount: r.amount
        }))
    };

    const d = await api(BASE+'/api/group-amt-allocation/save', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    });

    if(d.ok){
        alert(d.message || 'Saved');
        gridData = [];
        renderGrid();
        $('#totamt').value = '0.00';
        $('#amtperc').value = '0.00';
        $('#particular').value = '';
    } else {
        alert(d.message || 'Error saving');
    }
}

/* ── Account Search Modal ── */
function openAcModal(){
    $('#acModal').classList.add('active');
    $('#acModalSearch').value = '';
    $('#acModalBody').innerHTML = '';
    setTimeout(()=>$('#acModalSearch').focus(), 50);
}
function closeAcModal(){
    $('#acModal').classList.remove('active');
    acModalTarget = null;
}
$('#acModalClose').onclick = closeAcModal;
$('#acModal').onclick = e=>{ if(e.target.id==='acModal') closeAcModal(); };

let acModalTimer = null;
$('#acModalSearch').oninput = ()=>{
    clearTimeout(acModalTimer);
    acModalTimer = setTimeout(doAcSearch, 300);
};
async function doAcSearch(){
    const q = $('#acModalSearch').value.trim();
    if(!q){ $('#acModalBody').innerHTML=''; return; }
    const d = await api(BASE+'/api/group-amt-allocation/search-account?q='+encodeURIComponent(q));
    if(!d.ok) return;
    const tb = $('#acModalBody');
    tb.innerHTML = '';
    d.rows.forEach(r=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${esc(r.code)}</td><td>${esc(r.name)}</td>`;
        tr.onclick = ()=> pickAccount(r.code, r.name);
        tb.appendChild(tr);
    });
}

function pickAccount(code, name){
    if(acModalTarget === 'opac'){
        $('#opac').value = code;
        $('#opacName').textContent = name;
    } else if(typeof acModalTarget === 'number'){
        gridData[acModalTarget].aclink = code;
        renderGrid();
    }
    closeAcModal();
}

/* ── Opac field F1 ── */
$('#opac').addEventListener('keydown', e=>{
    if(e.key === 'F1'){
        e.preventDefault();
        acModalTarget = 'opac';
        openAcModal();
    }
});

/* ── Global keys ── */
document.addEventListener('keydown', e=>{
    if(e.key === 'F9'){
        e.preventDefault();
        doSave();
    }
    if(e.key === 'Escape'){
        if($('#acModal').classList.contains('active')){
            closeAcModal();
        } else {
            window.parent.postMessage({type:'module-close'}, '*');
        }
    }
});

/* ── Enter key navigation ── */
document.addEventListener('keydown', e=>{
    if(e.key !== 'Enter') return;
    const el = document.activeElement;
    if(!el || el.tagName === 'BUTTON') return;
    const nav = el.dataset && el.dataset.nav;
    if(!nav) return;
    e.preventDefault();
    const next = parseInt(nav) + 1;
    const nxt = document.querySelector('[data-nav="'+next+'"]');
    if(nxt) nxt.focus();
});
</script>
</body>
</html>
