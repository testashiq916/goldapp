<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Update Details</title>
<style>
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f3f5f8;color:#172033;font-size:13px}
.page{max-width:1180px;margin:0 auto;padding:22px 18px 30px}
.panel{background:#fff;border:1px solid #dbe2ea;border-radius:16px;padding:18px;box-shadow:0 8px 22px rgba(15,23,42,.04)}
.panel + .panel{margin-top:16px}
.head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:16px}
.title{font-size:20px;font-weight:700}
.subtitle{color:#667085;font-size:12px;margin-top:3px}
.toolbar{display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:14px}
.field{display:flex;flex-direction:column;gap:4px}
.field label{font-size:11px;font-weight:700;color:#475467}
.field input,.field select{height:34px;padding:0 10px;border:1px solid #cfd8e3;border-radius:10px;background:#fff}
.btn{appearance:none;border:none;border-radius:10px;padding:10px 14px;background:#173a63;color:#fff;font-weight:600;cursor:pointer;text-decoration:none}
.btn.secondary{background:#fff;color:#173a63;border:1px solid #c8d4e0}
.btn.warn{background:#b54708}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.tab{border:1px solid #cfd8e3;background:#fff;color:#173a63;border-radius:999px;padding:8px 12px;font-weight:700;cursor:pointer}
.tab.active{background:#173a63;color:#fff;border-color:#173a63}
.tabpane{display:none}
.tabpane.active{display:block}
.table-wrap{overflow:auto;border:1px solid #dbe2ea;border-radius:12px}
table{width:100%;border-collapse:collapse;font-size:12px}
th{background:#f8fafc;text-align:left;padding:8px;border-bottom:1px solid #dbe2ea;white-space:nowrap}
td{padding:8px;border-bottom:1px solid #edf2f7;vertical-align:top}
tr:last-child td{border-bottom:none}
.num{text-align:right;white-space:nowrap}
.muted{color:#667085}
.status{margin-top:10px;padding:10px 12px;border-radius:10px;display:none}
.status.show{display:block}
.status.ok{background:#ecfdf3;color:#027a48;border:1px solid #abefc6}
.status.err{background:#fef3f2;color:#b42318;border:1px solid #fecdca}
.empty{padding:24px;text-align:center;color:#667085}
@media print{
  .no-print{display:none!important}
  body{background:#fff}
  .page{max-width:none;padding:0}
  .panel{box-shadow:none;border:none;padding:0}
}
</style>
@include('partials.print-layout-head')
</head>
<body>
<main class="page">
  <section class="panel">
    <div class="head">
      <div>
        <div class="title">Update Details</div>
        <div class="subtitle">Port of the legacy update history and audit update report window.</div>
      </div>
      <div class="no-print">
        <button class="btn secondary" type="button" id="exitBtn">Exit</button>
      </div>
    </div>

    <div class="toolbar no-print">
      <div class="field">
        <label>Date From</label>
        <input type="date" id="dateFrom" value="{{ $today }}">
      </div>
      <div class="field">
        <label>Date To</label>
        <input type="date" id="dateTo" value="{{ $today }}">
      </div>
      <div class="field">
        <label>Sort</label>
        <select id="sortBy">
          <option value="date">Date, Time</option>
          <option value="user">User</option>
        </select>
      </div>
      <button class="btn" type="button" id="showBtn">Show</button>
      <button class="btn secondary" type="button" id="printBtn">Print</button>
      <button class="btn warn" type="button" id="clearBtn">Empty</button>
    </div>

    <div class="tabs">
      <button class="tab active" data-tab="history">User History</button>
      <button class="tab" data-tab="updates">Update Log</button>
    </div>

    <div class="tabpane active" id="pane-history">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Date</th>
              <th>Login Time</th>
              <th>Logout Time</th>
              <th>IP Address</th>
              <th>Device</th>
            </tr>
          </thead>
          <tbody id="historyBody">
            <tr><td colspan="6" class="empty">Click Show to load user history.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="tabpane" id="pane-updates">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Updt Date</th>
              <th>Updt Time</th>
              <th>Doc Date</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Weight</th>
              <th>Customer</th>
              <th>Staff</th>
              <th>User</th>
              <th>IC</th>
            </tr>
          </thead>
          <tbody id="updateBody">
            <tr><td colspan="10" class="empty">Click Show to load update log.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="status" id="statusBox"></div>
  </section>
</main>

<script>
const API_DATA = @json(url('/api/administration/update-log'));
const API_CLEAR = @json(url('/api/administration/update-log/clear'));

document.querySelectorAll('.tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
    document.querySelectorAll('.tabpane').forEach(x => x.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('pane-' + btn.dataset.tab).classList.add('active');
  });
});

document.getElementById('showBtn').addEventListener('click', loadData);
document.getElementById('printBtn').addEventListener('click', () => window.print());
document.getElementById('clearBtn').addEventListener('click', clearLogs);
document.getElementById('exitBtn').addEventListener('click', () => {
  if (window.parent && window.parent !== window) {
    window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
    return;
  }
  window.location.href = @json(url('/administration'));
});

function showStatus(kind, text){
  const box = document.getElementById('statusBox');
  box.className = 'status show ' + kind;
  box.textContent = text;
}

function parseDevice(ua){
  if(!ua) return '';
  const s = ua.toLowerCase();
  let device = '';
  if(/ipad/.test(s)) device = 'iPad';
  else if(/iphone/.test(s)) device = 'iPhone';
  else if(/android.*mobile/.test(s)) device = 'Android Phone';
  else if(/android/.test(s)) device = 'Android Tablet';
  else if(/windows phone/.test(s)) device = 'Windows Phone';
  else if(/macintosh|mac os x/.test(s)) device = 'Mac';
  else if(/windows/.test(s)) device = 'Windows PC';
  else if(/linux/.test(s)) device = 'Linux';
  let browser = '';
  if(/edg\/|edge\//.test(s)) browser = 'Edge';
  else if(/opr\/|opera/.test(s)) browser = 'Opera';
  else if(/chrome\//.test(s)) browser = 'Chrome';
  else if(/safari\//.test(s) && /version\//.test(s)) browser = 'Safari';
  else if(/firefox\//.test(s)) browser = 'Firefox';
  else if(/msie|trident/.test(s)) browser = 'IE';
  return [device, browser].filter(Boolean).join(' / ');
}

function renderHistory(rows){
  const body = document.getElementById('historyBody');
  if(!rows.length){
    body.innerHTML = '<tr><td colspan="6" class="empty">No user history found for the selected period.</td></tr>';
    return;
  }
  body.innerHTML = rows.map(r => `
    <tr>
      <td><strong>${escapeHtml(r.user_name || r.user_code)}</strong><div class="muted">${escapeHtml(r.user_code || '')}</div></td>
      <td>${fmtDate(r.tdate)}</td>
      <td>${fmtTime(r.time1)}</td>
      <td>${fmtTime(r.time2)}</td>
      <td class="muted">${escapeHtml(r.ip || '')}</td>
      <td class="muted">${escapeHtml(parseDevice(r.useragent))}</td>
    </tr>
  `).join('');
}

function renderUpdates(rows){
  const body = document.getElementById('updateBody');
  if(!rows.length){
    body.innerHTML = '<tr><td colspan="10" class="empty">No update log found for the selected period.</td></tr>';
    return;
  }
  body.innerHTML = rows.map(r => `
    <tr>
      <td>${fmtDate(r.updtdate)}</td>
      <td>${fmtTime(r.updttime)}</td>
      <td>${fmtDate(r.tdate)}</td>
      <td><strong>${escapeHtml(r.part || '')}</strong><div class="muted">${escapeHtml([r.ttype, r.utype, r.slno].filter(Boolean).join(' | '))}</div></td>
      <td class="num">${fmtMoney(r.amount)}</td>
      <td class="num">${fmtWeight(r.weight)}</td>
      <td>${escapeHtml(r.customer || '')}</td>
      <td>${escapeHtml(r.staff || '')}</td>
      <td>${escapeHtml(r.uid || '')}</td>
      <td>${escapeHtml(r.ic || '')}</td>
    </tr>
  `).join('');
}

async function loadData(){
  const params = new URLSearchParams({
    date_from: document.getElementById('dateFrom').value,
    date_to: document.getElementById('dateTo').value,
    sort: document.getElementById('sortBy').value
  });
  document.getElementById('historyBody').innerHTML = '<tr><td colspan="6" class="empty">Loading...</td></tr>';
  document.getElementById('updateBody').innerHTML = '<tr><td colspan="10" class="empty">Loading...</td></tr>';
  try{
    const res = await fetch(`${API_DATA}?${params}`, {headers:{Accept:'application/json'}});
    const data = await res.json();
    if(!data.ok) throw new Error(data.message || 'Failed to load');
    renderHistory(data.user_history || []);
    renderUpdates(data.update_log || []);
    showStatus('ok', 'Loaded update details successfully.');
  }catch(err){
    renderHistory([]);
    renderUpdates([]);
    showStatus('err', err.message || 'Failed to load update details.');
  }
}

async function clearLogs(){
  if(!confirm('Delete all entries from delpart and userhist?')) return;
  try{
    const body = new FormData();
    body.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    const res = await fetch(API_CLEAR, {method:'POST', headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').getAttribute('content')}, body});
    const data = await res.json();
    if(!data.ok) throw new Error(data.message || 'Clear failed');
    showStatus('ok', data.message || 'Logs cleared.');
    loadData();
  }catch(err){
    showStatus('err', err.message || 'Clear failed.');
  }
}

function fmtDate(value){
  if(!value) return '';
  const d = new Date(value + 'T00:00:00');
  if(Number.isNaN(d.getTime())) return value;
  return `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()}`;
}

function fmtTime(value){
  if(!value) return '';
  const [h='00',m='00',s='00'] = String(value).split(':');
  const d = new Date();
  d.setHours(Number(h), Number(m), Number(s), 0);
  return d.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit', hour12:true});
}

function fmtMoney(value){
  const n = Number(value || 0);
  if(!Number.isFinite(n) || n === 0) return '';
  return n.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function fmtWeight(value){
  const n = Number(value || 0);
  if(!Number.isFinite(n) || n === 0) return '';
  return n.toFixed(3);
}

function escapeHtml(value){
  return String(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}

loadData();
</script>
</body>
</html>
