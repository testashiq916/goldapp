<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Party Op. Weight Balance Entry</title>
<style>
  :root{--bg:#c0c0c0;--panel:#d7d7d7;--field:#fff;--line:#7e7e7e;--ring:#c7ba82}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:680px;margin:12px auto;background:var(--panel);border:1px solid #8a8a8a;box-shadow:inset 0 0 0 1px #efefef}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dedede,#c9c9c9);font-weight:700}
  .frame{padding:16px}
  .fields{display:grid;grid-template-columns:120px 1fr;gap:10px 12px;align-items:center}
  .label{font-weight:700;text-align:right;padding-right:4px}
  input,button{font:inherit}
  input[type="text"],input[type="number"]{width:100%;height:34px;padding:6px 8px;border:1px solid var(--line);background:var(--field)}
  input[readonly]{background:#d8d8d8}
  input:focus{outline:none;background:#fff5e8;border-color:#9b6a18;box-shadow:0 0 0 1px #ffd08a inset}
  .party-row{display:flex;gap:6px}
  .party-row input{flex:1}
  .lookup-btn{height:34px;width:36px;padding:0;border:1px solid var(--line);background:#efefef;font-weight:700;cursor:pointer}
  .actions-ring{margin:20px auto 0;max-width:360px;padding:16px 22px;border:4px solid #2748ff;border-radius:999px;background:var(--ring);display:flex;justify-content:center;gap:16px}
  .actions-ring button{min-width:90px;height:38px;border:1px solid #666;background:#f3f0de;font-weight:700;cursor:pointer}
  .actions-ring button:hover{background:#e8e4c8}
  .status{min-height:20px;margin-top:10px;font-weight:700;color:#9b0000;text-align:center}
  /* Modal */
  .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;justify-content:center;align-items:center}
  .modal-bg.show{display:flex}
  .modal{background:#e8e8e8;border:2px solid #666;width:520px;max-width:96vw;max-height:70vh;display:flex;flex-direction:column}
  .modal-head{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:linear-gradient(#dedede,#c0c0c0);border-bottom:1px solid #999;font-weight:700}
  .modal-head button{height:28px;padding:0 10px;font-weight:700;cursor:pointer}
  .modal-search{padding:8px 12px}
  .modal-search input{width:100%;height:30px;padding:4px 8px;border:1px solid var(--line)}
  .modal-body{overflow:auto;flex:1;padding:0 12px 12px}
  .modal-body table{width:100%;border-collapse:collapse;font-size:12px}
  .modal-body th,.modal-body td{border:1px solid #bbb;padding:5px 6px;text-align:left}
  .modal-body th{position:sticky;top:0;background:#d0d0d0;z-index:2}
  .modal-body tbody tr{cursor:pointer}
  .modal-body tbody tr:hover{background:#e0eaff}
</style>
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div>Party Op. Weight Balance Entry</div>
    <button type="button" id="btnClose">Close</button>
  </div>
  <div class="frame">
    <div class="fields">
      <div class="label">Party :</div>
      <div class="party-row">
        <input type="text" id="pcode" autocomplete="off" placeholder="Account code">
        <button type="button" class="lookup-btn" id="btnHelp">^</button>
      </div>

      <div class="label">Name :</div>
      <input type="text" id="pname" readonly>

      <div class="label">Op.Weight :</div>
      <input type="number" id="opwgt" step="0.001" value="0">

      <div class="label">Op.WeightB :</div>
      <input type="number" id="opwgtb" step="0.001" value="0">
    </div>

    <div class="actions-ring">
      <button type="button" id="btnSave">Save</button>
      <button type="button" id="btnNew">New</button>
      <button type="button" id="btnExit">Exit</button>
    </div>
    <div class="status" id="statusText"></div>
  </div>
</div>

<!-- Party search modal -->
<div class="modal-bg" id="partyModal">
  <div class="modal">
    <div class="modal-head">
      <span>Party Search</span>
      <button type="button" id="modalClose">X</button>
    </div>
    <div class="modal-search">
      <input type="text" id="modalSearch" placeholder="Search code or name...">
    </div>
    <div class="modal-body">
      <table>
        <thead><tr><th>Code</th><th>Name</th></tr></thead>
        <tbody id="modalRows"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
const API = @json(url('/api/party-op-weight'));
function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function esc(v){ return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function setStatus(msg='', ok=false){
  $('statusText').textContent = msg;
  $('statusText').style.color = ok ? '#0b5a18' : '#9b0000';
}

async function api(payload){
  const res = await fetch(API, {
    method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify(payload)
  });
  const data = await res.json();
  if (!res.ok && !data.success) throw new Error(data.error||'Request failed');
  return data;
}

function closeFrame(){ window.parent.postMessage({type:'goldapp:close-module-frame'},'*'); }

// Load account on blur
$('pcode').addEventListener('blur', async () => {
  const code = $('pcode').value.trim().toUpperCase();
  if (!code) { $('pname').value=''; return; }
  try {
    const d = await api({action:'load', code});
    if (d.success && d.account) {
      $('pcode').value = d.account.accode;
      $('pname').value = d.account.name;
      $('opwgt').value = d.account.opwgt;
      $('opwgtb').value = d.account.opwgtb;
      setStatus('Loaded: ' + d.account.accode, true);
    } else {
      $('pname').value = '';
      setStatus(d.error || 'Not found');
    }
  } catch(e){ $('pname').value=''; setStatus(e.message); }
});

$('pcode').addEventListener('keydown', e => {
  if (e.key === 'F1') { e.preventDefault(); $('btnHelp').click(); }
  if (e.key === 'Enter') { e.preventDefault(); $('opwgt').focus(); }
});

// Party modal
$('btnHelp').addEventListener('click', async () => {
  $('partyModal').classList.add('show');
  $('modalSearch').value = '';
  $('modalSearch').focus();
  await loadPartyList('');
});
$('modalClose').addEventListener('click', () => $('partyModal').classList.remove('show'));

let searchTimer;
$('modalSearch').addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadPartyList($('modalSearch').value.trim()), 300);
});

async function loadPartyList(search){
  try {
    const d = await api({action:'account_search', search});
    const body = $('modalRows');
    body.innerHTML = '';
    (d.data||[]).forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>'+esc(r.accode)+'</td><td>'+esc(r.name)+'</td>';
      tr.addEventListener('dblclick', () => {
        $('pcode').value = r.accode;
        $('partyModal').classList.remove('show');
        $('pcode').dispatchEvent(new Event('blur'));
      });
      body.appendChild(tr);
    });
  } catch(e){}
}

// Save
$('btnSave').addEventListener('click', async () => {
  const code = $('pcode').value.trim().toUpperCase();
  if (!code){ setStatus('Party code is required'); $('pcode').focus(); return; }
  if (!confirm('Save Op. Weight for ' + code + '?')) return;
  try {
    const d = await api({
      action:'save',
      code,
      opwgt: parseFloat($('opwgt').value)||0,
      opwgtb: parseFloat($('opwgtb').value)||0
    });
    setStatus(d.message||'Saved', d.success);
    if (d.success) resetForm();
  } catch(e){ setStatus('Save error: '+e.message); }
});

// New
$('btnNew').addEventListener('click', resetForm);
function resetForm(){
  $('pcode').value=''; $('pname').value='';
  $('opwgt').value=0; $('opwgtb').value=0;
  $('pcode').focus();
}

// Exit
$('btnExit').addEventListener('click', closeFrame);
$('btnClose').addEventListener('click', closeFrame);

// F9 save shortcut
document.addEventListener('keydown', e => {
  if (e.key==='F9'){ e.preventDefault(); $('btnSave').click(); }
  if (e.key==='Escape' && $('partyModal').classList.contains('show')){ $('partyModal').classList.remove('show'); }
});

$('pcode').focus();
</script>
</body>
</html>
