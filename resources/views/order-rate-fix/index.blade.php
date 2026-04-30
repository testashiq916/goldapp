<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#eef2f7;color:#1f2937}
.wrap{max-width:700px;margin:28px auto;background:#fff;border:1px solid #cbd5e1;border-radius:10px;box-shadow:0 8px 24px rgba(2,6,23,.08)}
.head{padding:10px 14px;background:#0a246a;color:#fff;font-weight:700;border-radius:10px 10px 0 0}
.body{padding:18px}
.row{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.row label{width:170px;font-weight:700}
.row input{flex:1;height:36px;border:1px solid #cbd5e1;border-radius:6px;padding:0 10px;font-size:14px}
.btn{height:36px;padding:0 14px;border:0;border-radius:6px;cursor:pointer;font-weight:700}
.btn-help{background:#e2e8f0}
.btn-ok{background:#16a34a;color:#fff}
.btn-exit{background:#dc2626;color:#fff}
.actions{display:flex;gap:10px;justify-content:center;margin-top:10px}
.msg{min-height:20px;margin-top:8px;font-size:13px;color:#b91c1c;font-weight:700}
.modal{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center}
.modal.show{display:flex}
.panel{width:min(720px,94vw);max-height:82vh;background:#fff;border-radius:8px;border:1px solid #cbd5e1;overflow:hidden}
.panel-h{background:#0a246a;color:#fff;padding:8px 10px;font-weight:700;display:flex;justify-content:space-between}
.panel-b{padding:10px}
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
      <input id="ordno" maxlength="13" style="text-transform:uppercase">
      <button class="btn btn-help" id="help">Help</button>
    </div>
    <div class="row">
      <label>Rate Fixed :</label>
      <input id="rate" type="number" step="0.01" min="0">
    </div>
    <div class="actions">
      <button class="btn btn-ok" id="ok">Ok</button>
      <button class="btn btn-exit" id="exit">Exit</button>
    </div>
    <div class="msg" id="msg"></div>
  </div>
</div>

<div class="modal" id="helpModal">
  <div class="panel">
    <div class="panel-h">Order Help <button class="btn" id="closeHelp">X</button></div>
    <div class="panel-b">
      <div class="search">
        <input id="q" placeholder="Order no / Customer">
        <button class="btn btn-help" id="find">Find</button>
      </div>
      <div style="overflow:auto;max-height:62vh">
        <table class="tbl">
          <thead><tr><th>Order No</th><th>Date</th><th>Customer</th><th>Status</th></tr></thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
const $ = id => document.getElementById(id);

async function api(url, method='GET', body=null){
  const opts = { method, headers: {} };
  if (body) {
    opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
    opts.headers['X-CSRF-TOKEN'] = csrf();
    opts.body = new URLSearchParams(body).toString();
  }
  const r = await fetch(url, opts);
  return await r.json();
}

function msg(t){ $('msg').textContent = t || ''; }

async function doApply(){
  msg('');
  const ordno = $('ordno').value.trim().toUpperCase();
  const rate = Number($('rate').value || 0);
  if (!ordno) { msg('Enter order no'); $('ordno').focus(); return; }
  if (rate <= 0) { msg('Enter valid rate'); $('rate').focus(); return; }

  const d = await api('/api/order-rate-fix/apply', 'POST', { ordno, rate: String(rate) });
  if (!d.ok) { msg(d.message || 'Failed'); return; }
  alert(d.message || 'Updated....');
}

async function doSearch(){
  const q = $('q').value.trim();
  const d = await api('/api/order-rate-fix/search?q=' + encodeURIComponent(q));
  const body = $('rows');
  body.innerHTML = '';
  (d.rows || []).forEach(r => {
    const tr = document.createElement('tr');
    const st = Number(r.status || 0) === 1 ? 'Pending' : (Number(r.status || 0) === 2 ? 'Returned' : String(r.status || ''));
    tr.innerHTML = `<td>${r.ordno || ''}</td><td>${(r.tdate || '').toString().slice(0,10)}</td><td>${r.custname || ''}</td><td>${st}</td>`;
    tr.addEventListener('click', () => {
      $('ordno').value = (r.ordno || '').toUpperCase();
      $('helpModal').classList.remove('show');
      $('ordno').focus();
    });
    body.appendChild(tr);
  });
}

$('ok').addEventListener('click', doApply);
$('exit').addEventListener('click', () => {
  if (window.parent && window.parent !== window) {
    window.parent.postMessage({ type: 'close-module' }, '*');
  }
});
$('help').addEventListener('click', () => { $('helpModal').classList.add('show'); $('q').focus(); doSearch(); });
$('closeHelp').addEventListener('click', () => $('helpModal').classList.remove('show'));
$('find').addEventListener('click', doSearch);
$('q').addEventListener('keydown', e => { if (e.key === 'Enter') doSearch(); });
$('ordno').addEventListener('keydown', e => { if (e.key === 'Enter') $('rate').focus(); if (e.key === 'F1') { e.preventDefault(); $('help').click(); } });
$('rate').addEventListener('keydown', e => { if (e.key === 'Enter') doApply(); });

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    if ($('helpModal').classList.contains('show')) $('helpModal').classList.remove('show');
  }
  if (e.key === 'F1') { e.preventDefault(); $('help').click(); }
});

$('ordno').focus();
</script>
</body>
</html>
