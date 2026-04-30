<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:#f0f2f5;font-family:'Segoe UI',system-ui,-apple-system,sans-serif;color:#1e293b;display:flex;align-items:center;justify-content:center;min-height:100vh}

  .card{width:min(480px,calc(100vw - 32px));background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08),0 1px 3px rgba(0,0,0,.06);overflow:hidden}
  .card-head{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:16px 24px;display:flex;align-items:center;justify-content:space-between}
  .card-head h2{color:#fff;font-size:15px;font-weight:700;letter-spacing:.3px}
  .card-head .close-btn{background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:8px;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s}
  .card-head .close-btn:hover{background:rgba(255,255,255,.25)}

  .card-body{padding:28px 24px}
  .field-group{margin-bottom:20px}
  .field-group label{display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
  .input-row{display:flex;gap:8px}
  .input-row input{flex:1}
  input[type="text"],input[type="date"]{width:100%;height:44px;border:1.5px solid #e2e8f0;border-radius:10px;padding:0 14px;font-size:14px;color:#1e293b;background:#f8fafc;transition:all .15s;outline:none}
  input[type="text"]:focus,input[type="date"]:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
  input[type="text"]::placeholder{color:#94a3b8}

  .btn{height:44px;padding:0 18px;border:none;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;justify-content:center;gap:6px}
  .btn-outline{background:#f8fafc;border:1.5px solid #e2e8f0;color:#475569}
  .btn-outline:hover{background:#f1f5f9;border-color:#cbd5e1}
  .btn-primary{background:#3b82f6;color:#fff;min-width:120px}
  .btn-primary:hover{background:#2563eb;box-shadow:0 4px 12px rgba(59,130,246,.3)}
  .btn-secondary{background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0}
  .btn-secondary:hover{background:#e2e8f0}

  .view-check{display:flex;align-items:center;gap:8px;margin-top:6px;font-size:13px;color:#64748b}
  .view-check input{width:18px;height:18px;accent-color:#3b82f6;cursor:pointer}

  .status{min-height:20px;text-align:center;font-size:12px;font-weight:600;margin-top:4px}
  .status.err{color:#dc2626}
  .status.ok{color:#16a34a}

  .card-footer{display:flex;gap:10px;justify-content:center;padding:0 24px 24px}

  /* Help Modal */
  .modal-bg{position:fixed;inset:0;background:rgba(15,23,42,.4);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:100;padding:16px}
  .modal-bg.show{display:flex}
  .modal{width:min(640px,100%);max-height:80vh;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden;display:flex;flex-direction:column}
  .modal-head{display:flex;gap:8px;align-items:center;padding:16px;border-bottom:1px solid #e2e8f0;background:#f8fafc}
  .modal-head input{flex:1;height:40px;border:1.5px solid #e2e8f0;border-radius:8px;padding:0 12px;font-size:13px;outline:none}
  .modal-head input:focus{border-color:#3b82f6}
  .modal-body{overflow:auto;flex:1}
  table{width:100%;border-collapse:collapse}
  th{background:#f8fafc;padding:10px 14px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;text-align:left;border-bottom:2px solid #e2e8f0;position:sticky;top:0}
  td{padding:10px 14px;font-size:13px;border-bottom:1px solid #f1f5f9}
  tbody tr{cursor:pointer;transition:background .1s}
  tbody tr:hover{background:#eff6ff}
  tbody tr:active{background:#dbeafe}
</style>
</head>
<body>
<div class="card">
  <div class="card-head">
    <h2>{{ $title }}</h2>
    <button type="button" class="close-btn" id="topClose">&times;</button>
  </div>
  <div class="card-body">
    <div class="field-group">
      <label>Document Number</label>
      <div class="input-row">
        <input type="text" id="docNo" maxlength="30" autocomplete="off" placeholder="Enter doc no..." style="text-transform:uppercase">
        <button type="button" class="btn btn-outline" id="helpBtn" title="F1">Help</button>
      </div>
    </div>

    <div class="field-group">
      <label>Date</label>
      <input type="date" id="docDate">
    </div>

    @if($showViewOption)
    <label class="view-check">
      <input type="checkbox" id="viewOnly">
      <span>View only</span>
    </label>
    @endif

    <div class="status" id="statusText"></div>
  </div>

  <div class="card-footer">
    <button type="button" class="btn btn-primary" id="okBtn">Open</button>
    <button type="button" class="btn btn-secondary" id="exitBtn">Exit</button>
  </div>
</div>

<div class="modal-bg" id="helpModal">
  <div class="modal">
    <div class="modal-head">
      <input type="text" id="helpQuery" placeholder="Search doc no or party...">
      <button type="button" class="btn btn-outline" id="helpSearchBtn">Search</button>
      <button type="button" class="btn btn-outline" id="helpCloseBtn">&times;</button>
    </div>
    <div class="modal-body">
      <table>
        <thead>
          <tr>
            <th style="width:20%">Doc No</th>
            <th style="width:18%">Date</th>
            <th>Party</th>
          </tr>
        </thead>
        <tbody id="helpRows"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
const SEARCH_URL = @json($searchUrl);
const RESOLVE_URL = @json($resolveUrl);
const ACTION_MODE = @json($actionMode);
const TARGET_BASE_URL = @json($targetBaseUrl);
const csrf = document.querySelector('meta[name="csrf-token"]').content;

function $(id){ return document.getElementById(id); }
function setStatus(msg='', ok=false){
  const el = $('statusText');
  el.textContent = msg;
  el.className = 'status ' + (ok ? 'ok' : 'err');
}
function toDdMmYyyy(v){
  if (!v) return '';
  const [y,m,d] = v.split('-');
  return `${d}/${m}/${y}`;
}
function closeFrame(){
  window.parent.postMessage({ type:'goldapp:close-module-frame' }, '*');
}
async function fetchJson(url, opts={}){
  const res = await fetch(url, opts);
  return await res.json();
}

async function searchDocs(){
  const params = new URLSearchParams({
    q: $('helpQuery').value.trim(),
    action: ACTION_MODE,
  });
  const data = await fetchJson(`${SEARCH_URL}?${params.toString()}`);
  const rows = data.rows || [];
  $('helpRows').innerHTML = rows.map((r, i) => `
    <tr data-idx="${i}">
      <td>${r.doc_no || ''}</td>
      <td>${r.tdate || ''}</td>
      <td>${r.party_name || ''}</td>
    </tr>
  `).join('');
  $('helpRows')._rows = rows;
}

async function openDoc(){
  const docNo = $('docNo').value.trim().toUpperCase();
  const tdate = toDdMmYyyy($('docDate').value);
  if (!docNo || !tdate) {
    setStatus('Doc no and date are required.');
    return;
  }

  const data = await fetchJson(RESOLVE_URL, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrf,
    },
    body: JSON.stringify({
      doc_no: docNo,
      tdate,
      action: ACTION_MODE,
      view_only: $('viewOnly') ? $('viewOnly').checked : false,
    }),
  });

  if (!data.ok) {
    setStatus(data.message || 'This bill number does not exist...');
    $('docNo').focus();
    return;
  }

  setStatus('Opening document...', true);
  window.location.href = data.url || `${TARGET_BASE_URL}/${ACTION_MODE}?doc_no=${encodeURIComponent(data.doc_no)}`;
}

$('helpBtn').addEventListener('click', async () => {
  $('helpModal').classList.add('show');
  await searchDocs();
  $('helpQuery').focus();
});
$('helpSearchBtn').addEventListener('click', searchDocs);
$('helpCloseBtn').addEventListener('click', () => $('helpModal').classList.remove('show'));
$('topClose').addEventListener('click', closeFrame);
$('exitBtn').addEventListener('click', closeFrame);
$('okBtn').addEventListener('click', openDoc);
$('docNo').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    $('docDate').focus();
  }
});
$('docDate').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    openDoc();
  }
});
$('helpRows').addEventListener('click', (e) => {
  const tr = e.target.closest('tr[data-idx]');
  if (!tr) return;
  const row = ($('helpRows')._rows || [])[Number(tr.dataset.idx)];
  if (!row) return;
  $('docNo').value = row.doc_no || '';
  const parts = String(row.tdate || '').split('/');
  if (parts.length === 3) {
    $('docDate').value = `${parts[2]}-${parts[1]}-${parts[0]}`;
  }
  $('helpModal').classList.remove('show');
  openDoc();
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    if ($('helpModal').classList.contains('show')) {
      $('helpModal').classList.remove('show');
    } else {
      closeFrame();
    }
  } else if (e.key === 'F1') {
    e.preventDefault();
    $('helpBtn').click();
  }
});

$('docDate').value = new Date().toISOString().slice(0,10);
$('docNo').focus();
</script>
</body>
</html>
