<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Change Duedate' }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,sans-serif;font-size:13px;background:#f0f2f5;display:flex;justify-content:center;padding-top:40px}
.card{background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.12);padding:24px 32px;width:520px}
h3{margin-bottom:16px;color:#2c3e50;font-size:16px;border-bottom:2px solid #3498db;padding-bottom:6px}
.row{display:flex;align-items:center;margin-bottom:12px;gap:8px}
label{font-weight:600;min-width:120px;text-align:right;font-size:12px;color:#555}
input{border:1px solid #bbb;border-radius:4px;padding:6px 10px;font-size:13px;outline:none;flex:1}
input:focus{border-color:#e67e22;background:#fff5eb}
input[readonly]{background:#f5f5f5;color:#333}
.btn{padding:8px 24px;border:none;border-radius:4px;font-size:13px;font-weight:600;cursor:pointer;color:#fff}
.btn-ok{background:#27ae60}.btn-ok:hover{background:#219a52}
.btn-exit{background:#e74c3c;margin-left:8px}.btn-exit:hover{background:#c0392b}
.btn-row{display:flex;justify-content:center;gap:10px;margin-top:16px}
.hint{color:#888;font-size:11px}

/* Search Modal */
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
.modal-body tr:hover{background:#d5f5e3}
</style>
</head>
<body>

<div class="card">
    <h3>Duedate Change</h3>

    <div class="row">
        <label>Party :</label>
        <input type="text" id="code" style="flex:0 0 140px;text-transform:uppercase" placeholder="F1 search">
        <span class="hint">Enter + F1</span>
    </div>

    <div class="row">
        <label>Name :</label>
        <input type="text" id="name" readonly>
    </div>

    <div class="row">
        <label>Old Duedate :</label>
        <input type="date" id="oldDuedate" readonly>
    </div>

    <div class="row">
        <label>Next Duedate :</label>
        <input type="date" id="newDuedate">
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
function api(url, opts={}){
    opts.headers = opts.headers || {};
    opts.headers['X-CSRF-TOKEN'] = CSRF;
    opts.credentials = 'same-origin';
    return fetch(url, opts).then(r=>r.json());
}
function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }

/* ── Load party on Enter ── */
$('#code').addEventListener('keydown', async e => {
    if(e.key === 'Enter'){
        e.preventDefault();
        await loadParty();
        $('#newDuedate').focus();
    }
    if(e.key === 'F1'){
        e.preventDefault();
        openModal();
    }
});

async function loadParty(){
    const code = $('#code').value.trim().toUpperCase();
    if(!code){ return; }
    const d = await api(BASE+'/api/change-duedate/load-party?code='+encodeURIComponent(code));
    if(!d.ok){
        alert(d.message || 'Error');
        $('#name').value = '';
        $('#oldDuedate').value = '';
        return;
    }
    $('#name').value = d.name;
    $('#oldDuedate').value = d.duedate || '';
}

/* ── OK / Save ── */
$('#btnOk').onclick = doSave;
async function doSave(){
    const code = $('#code').value.trim().toUpperCase();
    const newDate = $('#newDuedate').value;
    if(!code){ alert('Enter a party code'); $('#code').focus(); return; }
    if(!newDate){ alert('Enter the new duedate'); $('#newDuedate').focus(); return; }

    const d = await api(BASE+'/api/change-duedate/save', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ code, duedate: newDate })
    });

    if(d.ok){
        alert('Updated');
        $('#oldDuedate').value = newDate;
    } else {
        alert(d.message || 'Error');
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
    const d = await api(BASE+'/api/change-duedate/search-party?q='+encodeURIComponent(q));
    if(!d.ok) return;
    const tb = $('#modalBody');
    tb.innerHTML = '';
    d.rows.forEach(r=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${esc(r.code)}</td><td>${esc(r.name)}</td>`;
        tr.onclick = ()=>{
            $('#code').value = r.code;
            closeModal();
            loadParty().then(()=> $('#newDuedate').focus());
        };
        tb.appendChild(tr);
    });
}
</script>
</body>
</html>
