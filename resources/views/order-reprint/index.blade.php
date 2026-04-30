<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }}</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#eef2f7;color:#111827}
.wrap{max-width:700px;margin:32px auto;background:#fff;border:1px solid #cbd5e1;border-radius:10px;box-shadow:0 8px 24px rgba(15,23,42,.08)}
.head{background:#0a246a;color:#fff;padding:10px 14px;border-radius:10px 10px 0 0;font-weight:700}
.body{padding:18px}
.row{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.row label{width:180px;font-weight:700}
.row input{flex:1;height:36px;border:1px solid #cbd5e1;border-radius:6px;padding:0 10px;text-transform:uppercase}
.btn{height:36px;padding:0 14px;border:0;border-radius:6px;cursor:pointer;font-weight:700}
.btn-help{background:#e2e8f0}
.btn-ok{background:#16a34a;color:#fff}
.btn-exit{background:#dc2626;color:#fff}
.actions{display:flex;justify-content:center;gap:10px;margin-top:12px}
.msg{min-height:20px;margin-top:8px;color:#b91c1c;font-size:13px;font-weight:700}
.modal{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center}
.modal.show{display:flex}
.panel{width:min(760px,95vw);max-height:82vh;background:#fff;border-radius:8px;border:1px solid #cbd5e1;overflow:hidden}
.ph{background:#0a246a;color:#fff;padding:8px 10px;font-weight:700;display:flex;justify-content:space-between}
.pb{padding:10px}
.search{display:flex;gap:8px;margin-bottom:8px}
.search input{flex:1;height:34px;border:1px solid #cbd5e1;border-radius:6px;padding:0 10px}
.tbl{width:100%;border-collapse:collapse;font-size:13px}
.tbl th,.tbl td{border:1px solid #e2e8f0;padding:6px}
.tbl th{background:#334155;color:#fff;text-align:left}
.tbl tr{cursor:pointer}
.tbl tr:hover td{background:#e2e8f0}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">{{ $title }}</div>
  <div class="body">
    <div class="row">
      <label>Enter Order no :</label>
      <input id="doc" maxlength="20">
      <button class="btn btn-help" id="help">Help</button>
    </div>
    <div class="actions">
      <button class="btn btn-ok" id="ok">OK</button>
      <button class="btn btn-exit" id="exit">Exit</button>
    </div>
    <div class="msg" id="msg"></div>
  </div>
</div>

<div class="modal" id="helpModal">
  <div class="panel">
    <div class="ph">Order Help <button class="btn" id="closeHelp">X</button></div>
    <div class="pb">
      <div class="search">
        <input id="q" placeholder="Order no / Customer">
        <button class="btn btn-help" id="find">Find</button>
      </div>
      <div style="max-height:62vh;overflow:auto">
        <table class="tbl">
          <thead><tr><th>Order No</th><th>Date</th><th>Customer</th></tr></thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
const $ = id => document.getElementById(id);

async function api(url){ const r = await fetch(url); return await r.json(); }
function msg(t){ $('msg').textContent = t || ''; }

async function doOpen(){
  msg('');
  const doc = $('doc').value.trim().toUpperCase();
  if (!doc) { msg('Enter order no'); $('doc').focus(); return; }
  const d = await api('/api/order-reprint/resolve?doc_no=' + encodeURIComponent(doc));
  if (!d.ok) { msg(d.message || 'Not found'); return; }
  window.location.href = d.url;
}

async function doSearch(){
  const q = $('q').value.trim();
  const d = await api('/api/order-reprint/search?q=' + encodeURIComponent(q));
  const b = $('rows'); b.innerHTML = '';
  (d.rows || []).forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${r.ordno || ''}</td><td>${(r.tdate || '').toString().slice(0,10)}</td><td>${r.custname || ''}</td>`;
    tr.onclick = () => { $('doc').value = (r.ordno || '').toUpperCase(); $('helpModal').classList.remove('show'); doOpen(); };
    b.appendChild(tr);
  });
}

$('ok').onclick = doOpen;
$('exit').onclick = () => {
  if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'close-module' }, '*');
};
$('help').onclick = () => { $('helpModal').classList.add('show'); $('q').focus(); doSearch(); };
$('closeHelp').onclick = () => $('helpModal').classList.remove('show');
$('find').onclick = doSearch;
$('q').addEventListener('keydown', e => { if (e.key === 'Enter') doSearch(); });
$('doc').addEventListener('keydown', e => { if (e.key === 'Enter') doOpen(); if (e.key === 'F1') { e.preventDefault(); $('help').click(); } });
document.addEventListener('keydown', e => {
  if (e.key === 'F1') { e.preventDefault(); $('help').click(); }
  if (e.key === 'Escape' && $('helpModal').classList.contains('show')) $('helpModal').classList.remove('show');
});
$('doc').focus();
</script>
</body>
</html>
