<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Amt, Wgt Transfer Entry' }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,sans-serif;font-size:13px;background:#f0f2f5;display:flex;justify-content:center;padding-top:24px}
.card{background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.12);padding:24px 32px;width:580px}
h3{margin-bottom:16px;color:#2c3e50;font-size:16px;border-bottom:2px solid #e67e22;padding-bottom:6px}
.row{display:flex;align-items:center;margin-bottom:10px;gap:8px}
label{font-weight:600;min-width:140px;text-align:right;font-size:12px;color:#555}
input,select{border:1px solid #bbb;border-radius:4px;padding:6px 10px;font-size:13px;outline:none;flex:1}
input:focus,select:focus{border-color:#e67e22;background:#fff5eb}
input[readonly]{background:#f5f5f5;color:#333}
.r{text-align:right}
.btn{padding:8px 24px;border:none;border-radius:4px;font-size:13px;font-weight:600;cursor:pointer;color:#fff}
.btn-ok{background:#27ae60}.btn-ok:hover{background:#219a52}
.btn-exit{background:#e74c3c;margin-left:8px}.btn-exit:hover{background:#c0392b}
.btn-row{display:flex;justify-content:center;gap:10px;margin-top:18px}
.hint{color:#888;font-size:11px}
.sep{border:none;border-top:1px solid #eee;margin:12px 0}
.result-box{background:#ecf0f1;border-radius:6px;padding:10px 14px;margin-top:4px}
.result-box .row{margin-bottom:6px}
.result-box label{color:#7f8c8d}
.result-box input{background:#ecf0f1;border:none;font-weight:700;color:#2c3e50}

/* Modal */
.modal-bg{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.35);z-index:100;justify-content:center;align-items:center}
.modal-bg.active{display:flex}
.modal{background:#fff;border-radius:8px;width:420px;max-height:60vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.25)}
.modal-head{padding:10px 14px;background:#2c3e50;color:#fff;border-radius:8px 8px 0 0;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.modal-head .close{cursor:pointer;font-size:18px}
.modal-body{padding:10px;overflow:auto;flex:1}
.modal-body input{width:100%;margin-bottom:8px;padding:6px 10px;border:1px solid #ccc;border-radius:4px}
.modal-body table{width:100%;border-collapse:collapse}
.modal-body th{background:#ecf0f1;color:#2c3e50;padding:4px 6px;font-size:11px;text-align:left}
.modal-body td{padding:4px 6px;cursor:pointer;font-size:12px;border-bottom:1px solid #eee}
.modal-body tr:hover{background:#fdebd0}
</style>
</head>
<body>

<div class="card">
    <h3>Amt to Wgt Transfer Entry</h3>

    <div class="row">
        <label>Date :</label>
        <input type="date" id="tdate" data-nav="1">
    </div>

    <div class="row">
        <label>Party :</label>
        <input type="text" id="code" style="flex:0 0 140px;text-transform:uppercase" data-nav="2" placeholder="F1 search">
        <span class="hint">Enter + F1</span>
    </div>

    <div class="row">
        <label>Name :</label>
        <input type="text" id="name" readonly>
    </div>

    <div class="row">
        <label>Amt Balance :</label>
        <input type="text" id="balance" class="r" readonly value="0.00">
        <label style="min-width:80px">Wgt Bal :</label>
        <input type="text" id="wgtbal" class="r" readonly value="0.000" style="flex:0 0 120px">
    </div>

    <hr class="sep">

    <div class="row">
        <label>Transfer Type :</label>
        <select id="ttype" data-nav="3">
            <option value="Amt To Wgt">Amt To Wgt</option>
            <option value="Wgt To Amt">Wgt To Amt</option>
        </select>
    </div>

    <div class="row">
        <label>Amt To Convert :</label>
        <input type="text" id="amt" class="r" value="0.00" data-nav="4">
    </div>

    <div class="row">
        <label>Rate :</label>
        <input type="text" id="rate" class="r" value="0.00" data-nav="5">
    </div>

    <div class="row">
        <label>Weight :</label>
        <input type="text" id="weight" class="r" value="0.000" data-nav="6">
    </div>

    <div class="result-box">
        <div class="row">
            <label>A/c Cl.Balance :</label>
            <input type="text" id="clbalance" class="r" readonly value="0.00">
        </div>
        <div class="row" style="margin-bottom:0">
            <label>Wgt Cl.Balance :</label>
            <input type="text" id="clwgtbal" class="r" readonly value="0.000">
        </div>
    </div>

    <div class="btn-row">
        <button class="btn btn-ok" id="btnOk">OK (F9)</button>
        <button class="btn btn-exit" id="btnExit">Exit (Esc)</button>
    </div>
</div>

{{-- Party Search Modal --}}
<div class="modal-bg" id="searchModal">
    <div class="modal">
        <div class="modal-head">
            <span>Search Party</span>
            <span class="close" id="modalClose">&times;</span>
        </div>
        <div class="modal-body">
            <input type="text" id="modalSearch" placeholder="Type code or name...">
            <table>
                <thead><tr><th>Code</th><th>Name</th></tr></thead>
                <tbody id="modalBody"></tbody>
            </table>
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
function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }

let partyBalance = 0, partyWgtBal = 0;

/* ── Init ── */
document.addEventListener('DOMContentLoaded', async ()=>{
    $('#tdate').value = new Date().toISOString().slice(0,10);
    const d = await api(BASE+'/api/amt-wgt-transfer/init');
    if(d.ok) $('#rate').value = fmt(d.grate);
});

/* ── Party load ── */
$('#code').addEventListener('keydown', async e => {
    if(e.key === 'Enter'){
        e.preventDefault();
        await loadParty();
        $('#ttype').focus();
    }
    if(e.key === 'F1'){
        e.preventDefault();
        openModal();
    }
});

async function loadParty(){
    const code = $('#code').value.trim().toUpperCase();
    if(!code) return;
    const d = await api(BASE+'/api/amt-wgt-transfer/load-party?code='+encodeURIComponent(code));
    if(!d.ok){
        alert(d.message||'Error');
        $('#name').value=''; $('#balance').value='0.00'; $('#wgtbal').value='0.000';
        partyBalance=0; partyWgtBal=0;
        return;
    }
    $('#name').value = d.name;
    partyBalance = d.balance;
    partyWgtBal = d.wgtbal;
    $('#balance').value = fmt(partyBalance);
    $('#wgtbal').value = fmt(partyWgtBal,3);
    calcDiff();
}

/* ── Calculation ── */
function calcDiff(){
    const ttype = $('#ttype').value;
    const rate = parseFloat($('#rate').value)||0;
    let amt = parseFloat($('#amt').value)||0;
    let wgt = parseFloat($('#weight').value)||0;

    if(rate > 0){
        if(ttype === 'Amt To Wgt'){
            wgt = amt / rate;
            $('#weight').value = fmt(wgt,3);
            $('#clbalance').value = fmt(partyBalance - amt);
            $('#clwgtbal').value = fmt(partyWgtBal + wgt, 3);
        } else {
            amt = wgt * rate;
            $('#amt').value = fmt(amt);
            $('#clbalance').value = fmt(partyBalance + amt);
            $('#clwgtbal').value = fmt(partyWgtBal - wgt, 3);
        }
    }
}

$('#amt').addEventListener('change', calcDiff);
$('#rate').addEventListener('change', calcDiff);
$('#weight').addEventListener('change', calcDiff);
$('#ttype').addEventListener('change', calcDiff);

/* ── Save ── */
$('#btnOk').onclick = doSave;
async function doSave(){
    const code = $('#code').value.trim().toUpperCase();
    const amt = parseFloat($('#amt').value)||0;
    const wgt = parseFloat($('#weight').value)||0;
    if(!code){ alert('Enter a party code'); $('#code').focus(); return; }
    if(amt <= 0 || wgt <= 0){ alert('Nothing to do'); return; }
    if(!confirm('Do you want to update this adjustment?')) return;

    const d = await api(BASE+'/api/amt-wgt-transfer/save', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            tdate: $('#tdate').value,
            code: code,
            ttype: $('#ttype').value,
            amt: amt,
            rate: parseFloat($('#rate').value)||0,
            weight: wgt
        })
    });

    if(d.ok){
        alert(d.message||'Saved');
        // Reset
        $('#code').value=''; $('#name').value='';
        $('#balance').value='0.00'; $('#wgtbal').value='0.000';
        $('#amt').value='0.00'; $('#weight').value='0.000';
        $('#clbalance').value='0.00'; $('#clwgtbal').value='0.000';
        partyBalance=0; partyWgtBal=0;
    } else {
        alert(d.message||'Error');
    }
}

/* ── Exit ── */
$('#btnExit').onclick = ()=> window.parent.postMessage({type:'module-close'}, '*');

/* ── Global keys ── */
document.addEventListener('keydown', e=>{
    if(e.key === 'F9'){ e.preventDefault(); doSave(); }
    if(e.key === 'Escape'){
        if($('#searchModal').classList.contains('active')) closeModal();
        else window.parent.postMessage({type:'module-close'}, '*');
    }
});

/* ── Enter navigation ── */
document.addEventListener('keydown', e=>{
    if(e.key !== 'Enter') return;
    const el = document.activeElement;
    if(!el || el.tagName === 'BUTTON') return;
    const nav = el.dataset && el.dataset.nav;
    if(!nav) return;
    e.preventDefault();
    const next = parseInt(nav)+1;
    const nxt = document.querySelector('[data-nav="'+next+'"]');
    if(nxt) nxt.focus();
});

/* ── Search Modal ── */
function openModal(){
    $('#searchModal').classList.add('active');
    $('#modalSearch').value = $('#code').value;
    $('#modalBody').innerHTML = '';
    setTimeout(()=>$('#modalSearch').focus(), 50);
    if($('#code').value.trim()) doSearch();
}
function closeModal(){ $('#searchModal').classList.remove('active'); }
$('#modalClose').onclick = closeModal;
$('#searchModal').onclick = e=>{ if(e.target.id==='searchModal') closeModal(); };

let searchTimer = null;
$('#modalSearch').oninput = ()=>{
    clearTimeout(searchTimer);
    searchTimer = setTimeout(doSearch, 300);
};

async function doSearch(){
    const q = $('#modalSearch').value.trim();
    if(!q){ $('#modalBody').innerHTML=''; return; }
    const d = await api(BASE+'/api/amt-wgt-transfer/search-party?q='+encodeURIComponent(q));
    if(!d.ok) return;
    const tb = $('#modalBody');
    tb.innerHTML = '';
    d.rows.forEach(r=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${esc(r.code)}</td><td>${esc(r.name)}</td>`;
        tr.onclick = ()=>{
            $('#code').value = r.code;
            closeModal();
            loadParty().then(()=> $('#ttype').focus());
        };
        tb.appendChild(tr);
    });
}
</script>
</body>
</html>
