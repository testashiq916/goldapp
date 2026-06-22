<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
  :root{--bg:#c0c0c0;--panel:#d7d7d7;--field:#fff;--line:#7a7a7a;--head:#cfc08b}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:1280px;margin:10px auto;border:1px solid #8b8b8b;background:var(--panel);box-shadow:inset 0 0 0 1px #efefef}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dedede,#cacaca);font-weight:700}
  .frame{padding:10px 12px 14px}
  .top{display:grid;grid-template-columns:90px 180px 90px 220px 90px 220px;gap:8px 10px;align-items:center}
  .label{font-weight:700}
  input,select,button{font:inherit}
  input[type="text"],input[type="number"],select{width:100%;height:34px;padding:6px 8px;border:1px solid var(--line);background:var(--field)}
  .controls{display:flex;gap:8px;align-items:center;margin-top:8px;flex-wrap:wrap}
  .controls button{height:38px;padding:0 14px;border:1px solid #666;background:#efefef}
  .gridwrap{margin-top:10px;border:1px solid var(--line);background:#fff;overflow:auto}
  table{width:100%;border-collapse:collapse;min-width:1500px}
  th,td{border:1px solid #000;padding:0}
  th{background:var(--head);padding:8px 6px;font-weight:700;text-align:left}
  td input{border:0;height:34px;padding:6px 8px;background:transparent}
  td.num input{text-align:right}
  .summary{margin-top:10px;padding:10px;border:1px solid var(--line);background:#eee}
  .summary table{min-width:0}
  .summary th,.summary td{border:0;padding:4px 8px}
  .status{min-height:20px;margin-top:8px;text-align:center;font-weight:700;color:#980000}
  .suggest{position:relative}
  .suggest-list{display:none;position:absolute;left:0;right:0;top:100%;max-height:180px;overflow:auto;background:#fff;border:1px solid var(--line);z-index:20}
  .suggest-list div{padding:6px 8px;cursor:pointer}
  .suggest-list div:hover{background:#e8f1ff}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div>{{ $title }}</div>
    <button type="button" id="btnTopClose">Close</button>
  </div>
  <div class="frame">
    <div class="top">
      <div class="label">Stk Type :</div>
      <select id="stktype">
        <option value=""></option>
        @foreach($stockTypes as $stockType)
          <option value="{{ $stockType->code }}">{{ $stockType->code }}{{ $stockType->name ? ' - ' . $stockType->name : '' }}</option>
        @endforeach
      </select>

      <div class="label">Group :</div>
      <select id="grp">
        <option value=""></option>
        @foreach($groups as $group)
          <option value="{{ $group->code }}">{{ $group->code }}{{ isset($group->name) && $group->name ? ' - ' . trim($group->name) : '' }}</option>
        @endforeach
      </select>

      <div class="label">SMan :</div>
      <div class="suggest">
        <input type="text" id="smcode" autocomplete="off">
        <div class="suggest-list" id="smanSuggest"></div>
      </div>
    </div>

    <div class="controls">
      <label>Item Type
        <select id="itypeFilter">
          <option value="">All</option>
          <option value="G">Gold</option>
          <option value="S">Silver</option>
          <option value="O">Others</option>
        </select>
      </label>
      <label>Ornament
        <select id="ornFilter">
          <option value="">All</option>
          <option value="Y">Ornaments Only</option>
          <option value="N">Not Ornaments</option>
        </select>
      </label>
      <button type="button" id="btnLoad">Load</button>
      <button type="button" id="btnFill">Fill With Comp Stock</button>
      <button type="button" id="btnUpdate">Update Stock</button>
      <button type="button" id="btnPrint">Print</button>
      <button type="button" id="btnSaveAs">Save As</button>
      <button type="button" id="btnExit">Exit</button>
    </div>

    <div class="gridwrap">
      <table>
        <thead>
          <tr>
            <th style="width:18%">Item Name</th>
            <th style="width:8%">Code</th>
            <th style="width:7%;text-align:right">Comp. Qty</th>
            <th style="width:9%;text-align:right">Comp. Weight</th>
            <th style="width:8%;text-align:right">Comp. St.Wgt</th>
            <th style="width:7%;text-align:right">Original Qty</th>
            <th style="width:9%;text-align:right">Original Weight</th>
            <th style="width:8%;text-align:right">Original St.Wgt</th>
            <th style="width:7%;text-align:right">Diff Qty</th>
            <th style="width:9%;text-align:right">Diff Wgt</th>
            <th style="width:8%;text-align:right">Diff St.Wgt</th>
          </tr>
        </thead>
        <tbody id="gridBody"></tbody>
      </table>
    </div>

    <div class="summary" id="summaryBox"></div>
    <div class="status" id="statusText"></div>
  </div>
</div>

<script>
const LOAD_API = "{{ url('/api/item-stock-adjustment/compare') }}";
const SAVE_API = "{{ url('/api/item-stock-adjustment/compare/save') }}";
const SALESMEN = @json(array_values(array_map(fn($s) => ['code' => $s->code, 'name' => $s->name], $salesmen)));

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function esc(v){ return String(v ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); }
function setStatus(msg='', ok=false){ const el=$('statusText'); el.textContent=msg; el.style.color=ok?'#0b5a18':'#980000'; }

const state = { rows: [] };

async function fetchJson(url, method='GET', body=null){
  const opts = { method, headers: { Accept:'application/json' } };
  if (body) {
    opts.headers['Content-Type']='application/x-www-form-urlencoded';
    opts.headers['X-CSRF-TOKEN']=csrf();
    opts.body = new URLSearchParams(body).toString();
  }
  const res = await fetch(url, opts);
  return res.json();
}

function filteredRows(){
  return state.rows.filter(r => {
    if ($('grp').value && r.grpcode !== $('grp').value) return false;
    if ($('itypeFilter').value && r.itype !== $('itypeFilter').value) return false;
    if ($('ornFilter').value && r.ornament !== $('ornFilter').value) return false;
    return true;
  });
}

function render(){
  const rows = filteredRows();
  const body = $('gridBody'); body.innerHTML = '';
  rows.forEach((r, i) => {
    const diffQty = Number(r.curqty||0) - Number(r.cqty||0);
    const diffWgt = Number(r.curwgt||0) - Number(r.cwgt||0);
    const diffSt = Number(r.curstwgt||0) - Number(r.cstwgt||0);
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input value="${esc(r.name)}" readonly></td>
      <td><input value="${esc(r.code)}" readonly></td>
      <td class="num"><input value="${Number(r.cqty||0)}" readonly></td>
      <td class="num"><input value="${Number(r.cwgt||0).toFixed(3)}" readonly></td>
      <td class="num"><input value="${Number(r.cstwgt||0).toFixed(3)}" readonly></td>
      <td class="num"><input type="number" value="${Number(r.curqty||0)}" onchange="setNum('${r.code}', 'curqty', this.value)"></td>
      <td class="num"><input type="number" step="0.001" value="${Number(r.curwgt||0).toFixed(3)}" onchange="setNum('${r.code}', 'curwgt', this.value)"></td>
      <td class="num"><input type="number" step="0.001" value="${Number(r.curstwgt||0).toFixed(3)}" onchange="setNum('${r.code}', 'curstwgt', this.value)"></td>
      <td class="num"><input value="${diffQty}" readonly></td>
      <td class="num"><input value="${diffWgt.toFixed(3)}" readonly></td>
      <td class="num"><input value="${diffSt.toFixed(3)}" readonly></td>`;
    body.appendChild(tr);
  });
  renderSummary(rows);
}

function setNum(code, key, value){
  const row = state.rows.find(r => r.code === code);
  if (!row) return;
  row[key] = Number(value || 0);
  render();
}

function renderSummary(rows){
  const groups = {
    G: { label:'Gold', cnt:0, cqty:0, cwgt:0, oqty:0, owgt:0, dqty:0, dwgt:0 },
    S: { label:'Silver', cnt:0, cqty:0, cwgt:0, oqty:0, owgt:0, dqty:0, dwgt:0 },
    O: { label:'Others', cnt:0, cqty:0, cwgt:0, oqty:0, owgt:0, dqty:0, dwgt:0 },
  };
  rows.forEach(r => {
    const g = groups[r.itype] || groups.O;
    g.cnt += 1; g.cqty += Number(r.cqty||0); g.cwgt += Number(r.cwgt||0);
    g.oqty += Number(r.curqty||0); g.owgt += Number(r.curwgt||0);
    g.dqty += Number(r.curqty||0)-Number(r.cqty||0);
    g.dwgt += Number(r.curwgt||0)-Number(r.cwgt||0);
  });
  $('summaryBox').innerHTML = `
    <table>
      <tr><th>Total No of Items</th><td>${rows.length}</td><th>Comp Qty</th><th>Comp Wgt</th><th>Original Qty</th><th>Original Wgt</th><th>Diff Qty</th><th>Diff Wgt</th></tr>
      ${Object.values(groups).map(g => `<tr><th>${g.label}</th><td></td><td>${g.cqty}</td><td>${g.cwgt.toFixed(3)}</td><td>${g.oqty}</td><td>${g.owgt.toFixed(3)}</td><td>${g.dqty}</td><td>${g.dwgt.toFixed(3)}</td></tr>`).join('')}
    </table>`;
}

async function loadRows(){
  const params = new URLSearchParams({ stktype: $('stktype').value });
  const data = await fetchJson(`${LOAD_API}?${params.toString()}`);
  if (!data.success) { setStatus(data.message || 'Load failed'); return; }
  state.rows = data.rows || [];
  render();
}

function fillComp(){
  filteredRows().forEach(r => {
    r.curqty = Number(r.cqty||0);
    r.curwgt = Number(r.cwgt||0);
    r.curstwgt = Number(r.cstwgt||0);
  });
  render();
}

async function updateStock(){
  const rows = filteredRows();
  if (!rows.length) { setStatus('No rows loaded'); return; }
  if (!confirm('You are going to change the item stock.\nDo you want to save ?')) return;
  const data = await fetchJson(SAVE_API, 'POST', {
    stktype: $('stktype').value,
    smcode: $('smcode').value.trim(),
    rows: JSON.stringify(rows),
  });
  if (!data.success) { setStatus(data.message || 'Update failed'); return; }
  setStatus(data.message || 'Updated', true);
}

function closeFrame(){ window.parent.postMessage({ type:'goldapp:close-module-frame' }, '*'); }

$('grp').addEventListener('change', render);
$('itypeFilter').addEventListener('change', render);
$('ornFilter').addEventListener('change', render);
$('stktype').addEventListener('change', loadRows);
$('btnLoad').addEventListener('click', loadRows);
$('btnFill').addEventListener('click', fillComp);
$('btnUpdate').addEventListener('click', updateStock);
$('btnPrint').addEventListener('click', () => window.print());
// btnSaveAs handled by ReportExport below
$('btnExit').addEventListener('click', closeFrame);
$('btnTopClose').addEventListener('click', closeFrame);

$('smcode').addEventListener('input', () => {
  const q = $('smcode').value.trim().toLowerCase();
  const rows = q ? SALESMEN.filter(r => r.code.toLowerCase().includes(q) || r.name.toLowerCase().includes(q)) : [];
  $('smanSuggest').innerHTML = rows.map((r,i)=>`<div data-idx="${i}"><b>${esc(r.code)}</b> - ${esc(r.name)}</div>`).join('');
  $('smanSuggest').style.display = rows.length ? 'block' : 'none';
  $('smanSuggest')._rows = rows;
});
$('smanSuggest').addEventListener('mousedown', (e) => {
  e.preventDefault();
  const row = e.target.closest('[data-idx]'); if (!row) return;
  const rec = ($('smanSuggest')._rows||[])[Number(row.dataset.idx)]; if (!rec) return;
  $('smcode').value = rec.code; $('smanSuggest').style.display = 'none';
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'F9') { e.preventDefault(); updateStock(); }
});

loadRows();
</script>
<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>
ReportExport.initFromTable('btnSaveAs', 'table', 'stock-adjustment');
</script>
</body>
</html>
