<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Depositors Interest Posting Details</title>
<style>
  :root{--bg:#c0c0c0;--panel:#d7d7d7;--field:#fff;--line:#7e7e7e;--ring:#c7ba82}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:1000px;margin:12px auto;background:var(--panel);border:1px solid #8a8a8a;box-shadow:inset 0 0 0 1px #efefef}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dedede,#c9c9c9);font-weight:700}
  .frame{padding:12px}
  .top-row{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px}
  .top-row .label{font-weight:700}
  .top-row input,.top-row select{height:32px;padding:4px 8px;border:1px solid var(--line);background:var(--field)}
  .top-row input:focus{outline:none;background:#fff5e8;border-color:#9b6a18}
  .suggest{position:relative}
  .suggest-list{display:none;position:absolute;left:0;right:0;top:100%;max-height:160px;overflow:auto;background:#fff;border:1px solid var(--line);z-index:20}
  .suggest-list div{padding:4px 8px;cursor:pointer}
  .suggest-list div:hover,.suggest-list div.active{background:#e9f1ff}
  button{font:inherit;cursor:pointer}
  .btn{height:32px;padding:0 14px;border:1px solid #666;background:#efefef;font-weight:700}
  .btn:hover{background:#e0e0e0}
  /* Grid */
  .grid-wrap{border:1px solid #999;background:#fff;max-height:480px;overflow:auto}
  table{width:100%;border-collapse:collapse;font-size:12px}
  th,td{border:1px solid #ccc;padding:5px 6px;text-align:left}
  th{position:sticky;top:0;background:#c8c8c8;z-index:2;font-weight:700}
  tbody tr:nth-child(even){background:#f4f4f4}
  tbody tr:hover{background:#e4eeff}
  td input[type="number"]{width:100%;height:26px;border:1px solid #bbb;padding:2px 6px;text-align:right;background:#fff}
  td input[type="number"]:focus{background:#fff5e8;border-color:#9b6a18;outline:none}
  td input[type="checkbox"]{width:16px;height:16px}
  .footer-row{display:flex;justify-content:space-between;align-items:center;padding:8px 4px;background:#c8c8c8;border:1px solid #999;border-top:none;font-weight:700;font-size:12px}
  /* Action ring */
  .actions-ring{margin:14px auto 0;max-width:420px;padding:14px 22px;border:4px solid #2748ff;border-radius:999px;background:var(--ring);display:flex;justify-content:center;gap:14px}
  .actions-ring button{min-width:80px;height:36px;border:1px solid #666;background:#f3f0de;font-weight:700}
  .actions-ring button:hover{background:#e8e4c8}
  .status{min-height:20px;margin-top:10px;font-weight:700;color:#9b0000;text-align:center}
</style>
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div>Depositors Interest Posting Details</div>
    <button type="button" id="btnClose">Close</button>
  </div>
  <div class="frame">
    <!-- Top controls -->
    <div class="top-row">
      <span class="label">Date</span>
      <input type="date" id="tdate" style="width:160px">
      <span class="label">Staff</span>
      <div class="suggest" style="width:180px">
        <input type="text" id="smcode" autocomplete="off" style="width:100%">
        <div class="suggest-list" id="smanList"></div>
      </div>
      <button class="btn" id="btnShow">Show</button>
    </div>

    <!-- Data grid -->
    <div class="grid-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:40px">Sel</th>
            <th style="width:100px">Code</th>
            <th>Name</th>
            <th style="width:130px">Int Wgt</th>
            <th style="width:130px">Int Amt</th>
          </tr>
        </thead>
        <tbody id="gridBody"></tbody>
      </table>
    </div>
    <div class="footer-row">
      <span>Total: <span id="rowCount">0</span> rows</span>
      <span>Wgt: <span id="totalWgt">0.000</span> &nbsp; Amt: <span id="totalAmt">0.00</span></span>
    </div>

    <!-- Actions -->
    <div class="actions-ring">
      <button type="button" id="btnSave">Save</button>
      <button type="button" id="btnExit">Exit</button>
    </div>
    <div class="status" id="statusText"></div>
  </div>
</div>

<script>
const API = @json(url('/api/depositors-int-post'));
let initData = {};
let gridData = [];

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function esc(v){ return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function setStatus(msg='', ok=false){
  $('statusText').textContent = msg;
  $('statusText').style.color = ok ? '#0b5a18' : '#9b0000';
}

function closeFrame(){ window.parent.postMessage({type:'goldapp:close-module-frame'},'*'); }

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

// ── Render grid ──
function renderGrid(){
  const body = $('gridBody');
  body.innerHTML = '';
  gridData.forEach((r, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td style="text-align:center"><input type="checkbox" data-idx="'+i+'" class="sel-chk" '+(r.sel?'checked':'')+'></td>'
      + '<td>'+esc(r.code)+'</td>'
      + '<td>'+esc(r.name)+'</td>'
      + '<td><input type="number" step="0.001" data-idx="'+i+'" data-field="intwgt" value="'+r.intwgt.toFixed(3)+'"></td>'
      + '<td><input type="number" step="0.01" data-idx="'+i+'" data-field="intamt" value="'+r.intamt.toFixed(2)+'"></td>';
    body.appendChild(tr);
  });

  // Attach events
  body.querySelectorAll('.sel-chk').forEach(cb => {
    cb.addEventListener('change', e => {
      gridData[+e.target.dataset.idx].sel = e.target.checked ? 1 : 0;
      updateTotals();
    });
  });
  body.querySelectorAll('input[type="number"]').forEach(inp => {
    inp.addEventListener('change', e => {
      const idx = +e.target.dataset.idx;
      const field = e.target.dataset.field;
      gridData[idx][field] = parseFloat(e.target.value) || 0;
      updateTotals();
    });
  });

  updateTotals();
}

function updateTotals(){
  let wgt = 0, amt = 0, cnt = 0;
  gridData.forEach(r => {
    if (r.sel) { wgt += r.intwgt; amt += r.intamt; cnt++; }
  });
  $('rowCount').textContent = cnt;
  $('totalWgt').textContent = wgt.toFixed(3);
  $('totalAmt').textContent = amt.toFixed(2);
}

// ── Show button ──
$('btnShow').addEventListener('click', async () => {
  try {
    setStatus('Loading...');
    const d = await api({ action:'show', tdate: $('tdate').value });
    gridData = d.data || [];
    renderGrid();
    setStatus(gridData.length + ' depositor(s) loaded', true);
  } catch(e){ setStatus('Error: '+e.message); }
});

// ── Save ──
$('btnSave').addEventListener('click', async () => {
  const selected = gridData.filter(r => r.sel && (r.intwgt > 0 || r.intamt > 0));
  if (selected.length === 0) { setStatus('No rows selected with interest'); return; }
  if (!confirm('Save interest posting for ' + selected.length + ' depositor(s)?')) return;

  try {
    setStatus('Saving...');
    const d = await api({
      action:'save',
      tdate: $('tdate').value,
      smcode: $('smcode').value.trim().toUpperCase(),
      rows: selected
    });
    setStatus(d.message || 'Saved', d.success);
    if (d.success) { gridData = []; renderGrid(); }
  } catch(e){ setStatus('Save error: '+e.message); }
});

// ── Salesman suggest ──
function setupSmanSuggest(){
  const inp = $('smcode');
  const list = $('smanList');
  const allSmen = initData.salesmen || [];

  inp.addEventListener('input', () => {
    const v = inp.value.trim().toUpperCase();
    const filtered = v === '' ? allSmen : allSmen.filter(s =>
      (s.code||'').toUpperCase().includes(v) || (s.name||'').toUpperCase().includes(v)
    );
    list.innerHTML = '';
    filtered.forEach(s => {
      const d = document.createElement('div');
      d.textContent = s.code + ' - ' + s.name;
      d.addEventListener('click', () => { inp.value = s.code; list.style.display='none'; });
      list.appendChild(d);
    });
    list.style.display = filtered.length ? 'block' : 'none';
  });
  inp.addEventListener('blur', () => setTimeout(() => list.style.display='none', 200));
}

// ── Exit / Close ──
$('btnExit').addEventListener('click', closeFrame);
$('btnClose').addEventListener('click', closeFrame);

// ── F9 shortcut ──
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); $('btnSave').click(); }
});

// ── Init ──
async function init(){
  try {
    const d = await api({ action:'init' });
    initData = d;
    $('tdate').value = d.today || '';
    setupSmanSuggest();
    setStatus('Ready. Click "Show" to load depositors.', true);
  } catch(e){ setStatus('Init failed: '+e.message); }
}

init();
</script>
</body>
</html>
