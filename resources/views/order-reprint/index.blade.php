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
const SEARCH_URL = @json(url('/api/order-reprint/search'));
const RESOLVE_URL = @json(url('/api/order-reprint/resolve'));
const ORDER_SALE_MODE = @json(!empty($orderSaleMode));
const SEARCH_MODE = ORDER_SALE_MODE ? 'order-sale' : 'order';

document.querySelector('.row label').textContent = ORDER_SALE_MODE ? 'Enter Bill no :' : 'Enter Order no :';
document.querySelector('.ph').childNodes[0].textContent = ORDER_SALE_MODE ? 'Order Sale Help ' : 'Order Help ';
document.querySelector('#q').placeholder = ORDER_SALE_MODE ? 'Bill no / Order no / Customer' : 'Order no / Customer';
document.querySelector('.tbl thead tr').innerHTML = ORDER_SALE_MODE
  ? '<th>Bill No</th><th>Date</th><th>Customer</th><th>Order No</th>'
  : '<th>Order No</th><th>Date</th><th>Customer</th>';

async function api(url){
  const r = await fetch(url, {headers: {'Accept': 'application/json'}});
  if (!r.ok) throw new Error('Request failed');
  return await r.json();
}
function msg(t){ $('msg').textContent = t || ''; }

async function doOpen(){
  msg('');
  const doc = $('doc').value.trim().toUpperCase();
  if (!doc) { msg(ORDER_SALE_MODE ? 'Enter bill no' : 'Enter order no'); $('doc').focus(); return; }
  try {
    const d = await api(RESOLVE_URL + '?doc_no=' + encodeURIComponent(doc));
    if (!d.ok) { msg(d.message || 'Not found'); return; }
    window.location.href = d.url;
  } catch (e) {
    msg('Unable to load order');
  }
}

async function doSearch(){
  const q = $('q').value.trim();
  const b = $('rows'); b.innerHTML = '';
  try {
    const d = await api(SEARCH_URL + '?mode=' + encodeURIComponent(SEARCH_MODE) + '&q=' + encodeURIComponent(q));
    const rows = d.rows || [];
    if (rows.length === 0) {
      b.innerHTML = '<tr><td colspan="' + (ORDER_SALE_MODE ? '4' : '3') + '">' + (ORDER_SALE_MODE ? 'No order sale bills found' : 'No orders found') + '</td></tr>';
      return;
    }
    rows.forEach(r => {
      const tr = document.createElement('tr');
      const orderCell = document.createElement('td');
      const dateCell = document.createElement('td');
      const customerCell = document.createElement('td');
      orderCell.textContent = ORDER_SALE_MODE ? (r.docno || '') : (r.ordno || '');
      dateCell.textContent = (r.tdate || '').toString().slice(0,10);
      customerCell.textContent = r.custname || '';
      tr.append(orderCell, dateCell, customerCell);
      if (ORDER_SALE_MODE) {
        const orderNoCell = document.createElement('td');
        orderNoCell.textContent = r.ordno || '';
        tr.append(orderNoCell);
      }
      tr.onclick = () => { $('doc').value = (ORDER_SALE_MODE ? (r.docno || '') : (r.ordno || '')).toUpperCase(); $('helpModal').classList.remove('show'); doOpen(); };
      b.appendChild(tr);
    });
  } catch (e) {
    b.innerHTML = '<tr><td colspan="' + (ORDER_SALE_MODE ? '4' : '3') + '">Unable to load list</td></tr>';
  }
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
