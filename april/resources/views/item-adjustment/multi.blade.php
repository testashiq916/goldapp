<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
  :root{
    --bg:#c0c0c0;--panel:#d7d7d7;--field:#fff;--line:#7b7b7b;--head:#808040;--headText:#fff;--footer:#808040;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:1180px;margin:10px auto;border:1px solid #8c8c8c;background:var(--panel);box-shadow:inset 0 0 0 1px #eee}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dfdfdf,#cacaca);font-weight:700}
  .frame{padding:10px 12px 14px}
  .top{display:grid;grid-template-columns:120px 180px 140px 1fr 160px 1fr;gap:8px 10px;align-items:center}
  .label{font-weight:700;text-align:right;padding-right:4px}
  input,select,button{font:inherit}
  input[type="text"],input[type="date"],input[type="number"],select{width:100%;height:34px;padding:6px 8px;border:1px solid var(--line);background:var(--field)}
  input[readonly]{background:#d9d9d9}
  input:focus,select:focus{outline:none;background:#fff5e8}
  .row2{display:grid;grid-template-columns:120px 1fr 120px 1fr 1fr;gap:8px 10px;align-items:center;margin-top:8px}
  .checks{display:flex;gap:16px;align-items:center}
  .checks label{display:flex;gap:6px;align-items:center;font-weight:700}
  .toolbar{display:flex;gap:8px;align-items:center;margin-top:10px}
  .toolbar button{height:34px;padding:0 12px;border:1px solid #666;background:#efefef}
  .gridwrap{margin-top:10px;border:1px solid var(--line);background:#fff}
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid #000;padding:0}
  th{background:var(--head);color:var(--headText);padding:8px 6px;font-weight:700;text-align:left}
  td input{border:0;height:34px;padding:6px 8px;background:transparent}
  td.num input{text-align:right}
  tfoot td{background:var(--footer);color:#fff;font-weight:700;padding:8px 6px}
  .bottom{display:flex;justify-content:space-between;align-items:center;margin-top:10px}
  .bottom .left,.bottom .right{display:flex;gap:8px;align-items:center}
  .status{min-height:20px;margin-top:8px;text-align:center;font-weight:700;color:#980000}
  .suggest{position:relative}
  .suggest-list{display:none;position:absolute;left:0;right:0;top:100%;max-height:180px;overflow:auto;background:#fff;border:1px solid var(--line);z-index:20}
  .suggest-list div{padding:6px 8px;cursor:pointer}
  .suggest-list div:hover,.suggest-list div.active{background:#e8f1ff}
  .muted{background:#d9d9d9}
  @media (max-width: 1100px){
    .window{margin:0}
    .top,.row2{grid-template-columns:120px 1fr}
    .row2 > *:last-child{grid-column:auto}
    .bottom{flex-direction:column;gap:8px}
  }
</style>
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div>{{ $title }}</div>
    <button type="button" id="btnTopClose">Close</button>
  </div>
  <div class="frame">
    <div class="top">
      <div class="label">Doc No. :</div>
      <input type="text" id="docno" value="{{ $billNo }}" readonly>

      <div class="label">From Item :</div>
      <div class="suggest">
        <input type="text" id="fromitem" autocomplete="off">
        <div class="suggest-list" id="itemSuggest"></div>
      </div>

      <button type="button" id="btnHelp">Help</button>
      <input type="text" id="fromdesc" readonly class="muted">
    </div>

    <div class="row2">
      <div class="label">Date :</div>
      <input type="date" id="tdate">

      <div class="label">SM :</div>
      <div class="suggest">
        <input type="text" id="smcode" autocomplete="off">
        <div class="suggest-list" id="smanSuggest"></div>
      </div>

      <button type="button" id="btnSetDefault">Set as default</button>
    </div>

    <div class="row2">
      <div class="label">Group :</div>
      <select id="grp">
        <option value=""></option>
        @foreach($groups as $group)
          <option value="{{ $group->code }}">{{ $group->code }}{{ $group->name ? ' - ' . trim($group->name) : '' }}</option>
        @endforeach
      </select>

      <div class="label">From Stk Type :</div>
      <select id="fromstktype">
        <option value=""></option>
        @foreach($stockTypes as $stockType)
          <option value="{{ $stockType->code }}">{{ $stockType->code }}{{ $stockType->name ? ' - ' . $stockType->name : '' }}</option>
        @endforeach
      </select>

      <div class="checks">
        <label><input type="checkbox" id="pendonly"> BC Pend Only</label>
      </div>
    </div>

    <div class="row2">
      <div></div><div></div>
      <div class="label">To Stk Type :</div>
      <select id="tostktype">
        <option value=""></option>
        @foreach($stockTypes as $stockType)
          <option value="{{ $stockType->code }}">{{ $stockType->code }}{{ $stockType->name ? ' - ' . $stockType->name : '' }}</option>
        @endforeach
      </select>
      <button type="button" id="btnBcSummary">Take from BC List (Summary)</button>
    </div>

    <div class="toolbar">
      <label>From <input type="date" id="dateFrom" style="width:auto"></label>
      <label>To <input type="date" id="dateTo" style="width:auto"></label>
    </div>

    <div class="gridwrap">
      <table>
        <thead>
          <tr>
            <th style="width:16%">Item Code</th>
            <th style="width:36%">Item Name</th>
            <th style="width:10%;text-align:right">Qty</th>
            <th style="width:12%;text-align:right">Weight</th>
            <th style="width:12%;text-align:right">St.Wt.</th>
            <th style="width:14%;text-align:right">Net Wt.</th>
          </tr>
        </thead>
        <tbody id="gridBody"></tbody>
        <tfoot>
          <tr>
            <td colspan="2" style="text-align:right">Total :</td>
            <td id="totQty" style="text-align:right">0</td>
            <td id="totWeight" style="text-align:right">0.000</td>
            <td id="totStwgt" style="text-align:right">0.000</td>
            <td id="totNet" style="text-align:right">0.000</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="bottom">
      <div class="left">
        <button type="button" id="btnAdd">Add</button>
        <button type="button" id="btnDelete">Delete</button>
      </div>
      <div class="right">
        <button type="button" id="btnSave">Save</button>
        <button type="button" id="btnExit">Exit</button>
      </div>
    </div>
    <div class="status" id="statusText"></div>
  </div>
</div>

<script>
const ITEM_API = "{{ url('/api/item-stock-adjustment/item') }}";
const SEARCH_API = "{{ url('/api/item-stock-adjustment/item-search') }}";
const SAVE_API = "{{ url('/api/item-stock-adjustment-multi/save') }}";
const BC_SUMMARY_API = "{{ url('/api/item-stock-adjustment-multi/bc-summary') }}";
const SALESMEN = @json(array_values(array_map(fn($s) => ['code' => $s->code, 'name' => $s->name], $salesmen)));
const DEFAULT_STK = @json($defaultStockType);

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function esc(v){ return String(v ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); }
function setStatus(msg='', ok=false){ const el=$('statusText'); el.textContent=msg; el.style.color=ok?'#0b5a18':'#980000'; }

const state = {
  rows: [],
  selected: 0,
  itemSuggest: [],
  smanSuggest: [],
  itemIndex: -1,
  smanIndex: -1,
  fromBcList: 'N',
};

function emptyRow(){ return { itemcode:'', itemname:'', qty:0, weight:0, stwgt:0 }; }

function renderRows(){
  const body = $('gridBody');
  body.innerHTML = '';
  if (!state.rows.length) state.rows = [emptyRow()];
  state.rows.forEach((r, i) => {
    const tr = document.createElement('tr');
    tr.dataset.idx = i;
    tr.innerHTML = `
      <td><input value="${esc(r.itemcode)}" onchange="onItemCode(${i}, this.value)" onfocus="selectRow(${i})"></td>
      <td><input value="${esc(r.itemname)}" onchange="setField(${i}, 'itemname', this.value)" onfocus="selectRow(${i})"></td>
      <td class="num"><input type="number" value="${Number(r.qty||0)}" onchange="setNum(${i}, 'qty', this.value)" onfocus="selectRow(${i})"></td>
      <td class="num"><input type="number" step="0.001" value="${Number(r.weight||0).toFixed(3)}" onchange="setNum(${i}, 'weight', this.value)" onfocus="selectRow(${i})"></td>
      <td class="num"><input type="number" step="0.001" value="${Number(r.stwgt||0).toFixed(3)}" onchange="setNum(${i}, 'stwgt', this.value)" onfocus="selectRow(${i})"></td>
      <td class="num"><input value="${(Number(r.weight||0)-Number(r.stwgt||0)).toFixed(3)}" readonly class="muted"></td>`;
    body.appendChild(tr);
  });
  refreshTotals();
}

function refreshTotals(){
  let q=0,w=0,s=0;
  state.rows.forEach(r => { q += Number(r.qty||0); w += Number(r.weight||0); s += Number(r.stwgt||0); });
  $('totQty').textContent = String(q);
  $('totWeight').textContent = w.toFixed(3);
  $('totStwgt').textContent = s.toFixed(3);
  $('totNet').textContent = (w-s).toFixed(3);
}

function selectRow(i){ state.selected = i; }
function setField(i,k,v){ state.rows[i][k]=v; }
function setNum(i,k,v){ state.rows[i][k]=Number(v||0); refreshTotals(); }

async function onItemCode(i, value){
  const code = String(value||'').trim().toUpperCase();
  state.rows[i].itemcode = code;
  if (!code) { state.rows[i].itemname = ''; renderRows(); return; }
  const data = await fetchJson(`${ITEM_API}?code=${encodeURIComponent(code)}`);
  if (!data.success) {
    setStatus(data.message || 'Invalid item');
    state.rows[i].itemcode = '';
    state.rows[i].itemname = '';
    renderRows();
    return;
  }
  state.rows[i].itemcode = data.item.code || code;
  state.rows[i].itemname = data.item.name || '';
  setStatus('');
  renderRows();
}

async function loadFromItem(){
  const code = $('fromitem').value.trim().toUpperCase();
  if (!code) return;
  const data = await fetchJson(`${ITEM_API}?code=${encodeURIComponent(code)}`);
  if (!data.success) {
    setStatus(data.message || 'Invalid code');
    $('fromitem').value = '';
    $('fromdesc').value = '';
    return;
  }
  $('fromitem').value = data.item.code || code;
  $('fromdesc').value = data.item.name || '';
  setStatus('');
}

async function loadBcSummary(){
  const params = new URLSearchParams({
    date_from: $('dateFrom').value,
    date_to: $('dateTo').value,
    group: $('grp').value,
    pending_only: $('pendonly').checked ? '1' : '0',
  });
  const data = await fetchJson(`${BC_SUMMARY_API}?${params.toString()}`);
  if (!data.success) {
    setStatus(data.message || 'Unable to load summary');
    return;
  }
  state.rows = data.rows && data.rows.length ? data.rows : [emptyRow()];
  state.fromBcList = 'Y';
  renderRows();
}

function addRow(){
  const last = state.rows[state.rows.length - 1];
  if (last && String(last.itemcode||'').trim() !== '') {
    state.rows.push({ ...last });
  } else if (!last || String(last.itemcode||'').trim() !== '') {
    state.rows.push(emptyRow());
  }
  state.selected = state.rows.length - 1;
  renderRows();
}

function deleteRow(){
  if (!state.rows.length) return;
  state.rows.splice(state.selected, 1);
  if (!state.rows.length) state.rows = [emptyRow()];
  state.selected = Math.max(0, Math.min(state.selected, state.rows.length - 1));
  renderRows();
}

async function saveMulti(){
  const rows = state.rows.filter(r => String(r.itemcode||'').trim() !== '' && Number(r.weight||0) !== 0);
  if (!rows.length) { setStatus('No valid rows to save'); return; }
  if (!confirm('You want to save ?...')) return;
  const payload = {
    docno: $('docno').value.trim(),
    tdate: $('tdate').value,
    fromitem: $('fromitem').value.trim().toUpperCase(),
    smcode: $('smcode').value.trim(),
    fromstktype: $('fromstktype').value,
    tostktype: $('tostktype').value,
    reason: $('fromdesc').value.trim(),
    frombclist: state.fromBcList,
    date_from: $('dateFrom').value,
    date_to: $('dateTo').value,
    rows: JSON.stringify(rows),
  };
  const data = await fetchJson(SAVE_API, 'POST', payload);
  if (!data.success) { setStatus(data.message || 'Save failed'); return; }
  setStatus(data.message || 'Saved', true);
  $('docno').value = data.docno || $('docno').value;
  state.rows = [emptyRow()];
  state.fromBcList = 'N';
  renderRows();
}

async function searchItems(inputId, listId, kind){
  const q = $(inputId).value.trim();
  if (!q) {
    state[kind] = [];
    $(listId).style.display = 'none';
    return;
  }
  const data = await fetchJson(`${SEARCH_API}?q=${encodeURIComponent(q)}`);
  state[kind] = data.rows || [];
  const box = $(listId);
  box.innerHTML = state[kind].map((r,i)=>`<div data-idx="${i}"><b>${esc(r.code)}</b> - ${esc(r.name)}</div>`).join('');
  box.style.display = state[kind].length ? 'block' : 'none';
}

async function fetchJson(url, method='GET', body=null){
  const opts = { method, headers: { Accept:'application/json' } };
  if (body) {
    opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
    opts.headers['X-CSRF-TOKEN'] = csrf();
    opts.body = new URLSearchParams(body).toString();
  }
  const res = await fetch(url, opts);
  return res.json();
}

function closeFrame(){ window.parent.postMessage({ type:'goldapp:close-module-frame' }, '*'); }

$('fromitem').addEventListener('change', loadFromItem);
$('fromitem').addEventListener('input', () => searchItems('fromitem', 'itemSuggest', 'itemSuggest'));
$('smcode').addEventListener('input', () => {
  const q = $('smcode').value.trim().toLowerCase();
  const rows = q ? SALESMEN.filter(r => r.code.toLowerCase().includes(q) || r.name.toLowerCase().includes(q)) : [];
  state.smanSuggest = rows;
  $('smanSuggest').innerHTML = rows.map((r,i)=>`<div data-idx="${i}"><b>${esc(r.code)}</b> - ${esc(r.name)}</div>`).join('');
  $('smanSuggest').style.display = rows.length ? 'block' : 'none';
});

$('itemSuggest').addEventListener('mousedown', async (e) => {
  e.preventDefault();
  const row = e.target.closest('[data-idx]'); if (!row) return;
  const rec = state.itemSuggest[Number(row.dataset.idx)]; if (!rec) return;
  $('fromitem').value = rec.code; $('itemSuggest').style.display = 'none'; await loadFromItem();
});
$('smanSuggest').addEventListener('mousedown', (e) => {
  e.preventDefault();
  const row = e.target.closest('[data-idx]'); if (!row) return;
  const rec = state.smanSuggest[Number(row.dataset.idx)]; if (!rec) return;
  $('smcode').value = rec.code; $('smanSuggest').style.display = 'none';
});

$('btnHelp').addEventListener('click', async () => {
  const q = prompt('Search item');
  if (!q) return;
  const data = await fetchJson(`${SEARCH_API}?q=${encodeURIComponent(q)}`);
  if (!data.success || !data.rows.length) { setStatus('No matching item'); return; }
  $('fromitem').value = data.rows[0].code; await loadFromItem();
});
$('btnSetDefault').addEventListener('click', () => {
  localStorage.setItem('goldapp:itemadjmulti:fromitem:' + ($('grp').value || '_default'), $('fromitem').value.trim().toUpperCase());
  localStorage.setItem('goldapp:itemadjmulti:fromstk', $('fromstktype').value);
  localStorage.setItem('goldapp:itemadjmulti:tostk', $('tostktype').value);
  setStatus('Default saved', true);
});
$('grp').addEventListener('change', () => {
  const key = 'goldapp:itemadjmulti:fromitem:' + ($('grp').value || '_default');
  const code = localStorage.getItem(key) || localStorage.getItem('goldapp:itemadjmulti:fromitem:_default') || '';
  if (code) { $('fromitem').value = code; loadFromItem(); }
});
$('pendonly').addEventListener('change', () => {
  localStorage.setItem('goldapp:itemadjmulti:pendonly', $('pendonly').checked ? 'Y' : 'N');
});
$('btnBcSummary').addEventListener('click', loadBcSummary);
$('btnAdd').addEventListener('click', addRow);
$('btnDelete').addEventListener('click', deleteRow);
$('btnSave').addEventListener('click', saveMulti);
$('btnExit').addEventListener('click', closeFrame);
$('btnTopClose').addEventListener('click', closeFrame);

document.addEventListener('keydown', (e) => {
  if (e.key === 'F9') { e.preventDefault(); saveMulti(); }
  if (e.key === 'Escape') { e.preventDefault(); closeFrame(); }
});

$('tdate').value = new Date().toISOString().slice(0,10);
$('dateFrom').value = $('tdate').value;
$('dateTo').value = $('tdate').value;
$('fromstktype').value = localStorage.getItem('goldapp:itemadjmulti:fromstk') || DEFAULT_STK || '';
$('tostktype').value = localStorage.getItem('goldapp:itemadjmulti:tostk') || DEFAULT_STK || '';
$('pendonly').checked = localStorage.getItem('goldapp:itemadjmulti:pendonly') === 'Y';
const initFromItem = localStorage.getItem('goldapp:itemadjmulti:fromitem:_default') || '';
if (initFromItem) { $('fromitem').value = initFromItem; loadFromItem(); }
state.rows = [emptyRow()];
renderRows();
</script>
</body>
</html>
