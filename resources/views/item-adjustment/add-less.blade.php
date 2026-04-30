<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
  :root{
    --bg:#eef4f8;
    --bg-accent:#d9e8f0;
    --panel:#ffffff;
    --panel-soft:#f7fafc;
    --field:#ffffff;
    --line:#d5e0e8;
    --line-strong:#b8cad6;
    --head:#edf4f7;
    --text:#102331;
    --label:#284255;
    --muted:#698293;
    --accent:#0f8b8d;
    --accent-strong:#0c6c6f;
    --danger:#c44949;
    --success:#16794f;
    --shadow:0 18px 48px rgba(16,35,49,.12);
  }
  *{box-sizing:border-box}
  body{
    margin:0;
    min-height:100vh;
    background:
      radial-gradient(circle at top left, rgba(15,139,141,.10), transparent 30%),
      radial-gradient(circle at top right, rgba(51,102,153,.08), transparent 28%),
      linear-gradient(180deg, var(--bg) 0%, var(--bg-accent) 100%);
    font-family:"Segoe UI",Tahoma,sans-serif;
    font-size:14px;
    color:var(--text);
  }
  .window{
    max-width:1380px;
    margin:28px auto;
    border:1px solid rgba(255,255,255,.7);
    border-radius:24px;
    background:rgba(255,255,255,.9);
    box-shadow:var(--shadow);
    overflow:hidden;
    backdrop-filter:blur(6px);
  }
  .titlebar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:20px 24px;
    border-bottom:1px solid var(--line);
    background:linear-gradient(135deg, #12344a 0%, #1b5a73 100%);
    color:#fff;
    font-weight:700;
  }
  .titlebar > div{font-size:22px;letter-spacing:.2px}
  .frame{padding:24px}
  .top{
    display:grid;
    grid-template-columns:90px 190px 190px 90px 90px 220px;
    gap:14px 16px;
    align-items:center;
    padding:18px;
    border:1px solid var(--line);
    border-radius:20px;
    background:linear-gradient(180deg, rgba(255,255,255,.95), var(--panel-soft));
    box-shadow:0 10px 24px rgba(16,35,49,.05);
  }
  .label{font-weight:700;color:var(--label)}
  input,select,button{font:inherit}
  input[type="text"],input[type="date"],input[type="number"],select{
    width:100%;
    height:44px;
    padding:10px 14px;
    border:1px solid var(--line);
    border-radius:14px;
    background:var(--field);
    color:var(--text);
    transition:border-color .18s ease, box-shadow .18s ease, transform .18s ease;
  }
  input:focus,select:focus{
    outline:none;
    border-color:rgba(15,139,141,.6);
    box-shadow:0 0 0 4px rgba(15,139,141,.12);
    transform:translateY(-1px);
  }
  .top .wide{grid-column:span 2}
  .top .check{
    display:flex;
    align-items:center;
    gap:8px;
    font-weight:700;
    color:var(--label);
    padding:10px 14px;
    border:1px solid var(--line);
    border-radius:999px;
    background:#fff;
  }
  .top .check input[type=checkbox]{
    width:16px;
    height:16px;
    accent-color:var(--accent);
  }
  .gridwrap{
    margin-top:18px;
    border:1px solid var(--line);
    border-radius:22px;
    background:#fff;
    box-shadow:0 14px 28px rgba(16,35,49,.06);
    overflow:auto;
  }
  table{
    width:100%;
    min-width:1720px;
    border-collapse:collapse;
    table-layout:fixed;
  }
  th,td{border:1px solid var(--line);padding:0}
  th{
    background:var(--head);
    padding:10px 8px;
    font-weight:800;
    text-align:left;
    color:var(--label);
    font-size:12px;
  }
  td input,td select{
    border:0;
    border-radius:0;
    height:42px;
    padding:8px 10px;
    background:transparent;
    box-shadow:none;
    transform:none;
  }
  td.num input{text-align:right}
  .col-bcode{min-width:140px}
  .col-code{min-width:110px}
  .col-name{min-width:180px}
  .col-num-wide{min-width:92px}
  .col-num-mid{min-width:84px}
  .col-stk{min-width:96px}
  .col-reason{min-width:150px}
  tfoot td{
    background:#eef6f7;
    padding:10px 8px;
    font-weight:800;
    color:var(--label);
  }
  .actions{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-top:18px;
    padding:18px 20px;
    border:1px solid var(--line);
    border-radius:20px;
    background:linear-gradient(180deg, rgba(255,255,255,.97), #f7fafc);
    box-shadow:0 14px 28px rgba(16,35,49,.06);
  }
  .actions .left,.actions .right{display:flex;gap:8px}
  .actions button{
    height:44px;
    padding:0 16px;
    border:1px solid var(--line);
    border-radius:14px;
    background:#fff;
    color:var(--label);
    font-weight:700;
    transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
  }
  .actions button:hover,
  .actions button:focus,
  #btnTopClose:hover,
  #btnTopClose:focus{
    transform:translateY(-1px);
    box-shadow:0 10px 18px rgba(16,35,49,.10);
  }
  #btnSave{
    background:linear-gradient(135deg, var(--accent), var(--accent-strong));
    color:#fff;
    border-color:transparent;
    box-shadow:0 12px 24px rgba(15,139,141,.22);
  }
  #btnTopClose{
    min-width:100px;
    height:42px;
    border-radius:12px;
    border:1px solid rgba(255,255,255,.18);
    color:#fff;
    background:rgba(255,255,255,.10);
    backdrop-filter:blur(4px);
  }
  .status{
    min-height:24px;
    margin-top:12px;
    text-align:center;
    font-weight:700;
    color:var(--danger);
  }
  .suggest{position:relative}
  .suggest-list{
    display:none;
    position:absolute;
    left:0;
    right:0;
    top:calc(100% + 6px);
    max-height:220px;
    overflow:auto;
    background:#fff;
    border:1px solid var(--line);
    border-radius:16px;
    box-shadow:0 18px 28px rgba(16,35,49,.12);
    z-index:20;
    padding:6px;
  }
  .suggest-list div{padding:10px 12px;cursor:pointer;border-radius:10px}
  .suggest-list div:hover{background:#e9f6f6}
  @media (max-width:1200px){
    .window{margin:0;border-radius:0}
    .frame{padding:16px}
    .top{grid-template-columns:1fr 1fr}
  }
  @media (max-width:760px){
    .top{grid-template-columns:1fr}
    .actions{flex-direction:column;align-items:stretch;gap:12px}
    .actions .left,.actions .right{justify-content:stretch;flex-wrap:wrap}
  }
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div>{{ $title }}</div>
    <button type="button" id="btnTopClose">Close</button>
  </div>
  <div class="frame">
    <div class="top">
      <div class="label">Date :</div>
      <input type="date" id="tdate">

      <label class="check"><input type="checkbox" id="stktransfer"> As Stock Transfer</label>

      <div class="label">SMan :</div>
      <div class="suggest">
        <input type="text" id="smcode" autocomplete="off">
        <div class="suggest-list" id="smanSuggest"></div>
      </div>

      <div></div>
    </div>

    <div class="gridwrap">
      <table>
        <thead>
          <tr>
            <th class="col-bcode">Barcode</th>
            <th class="col-code">Code</th>
            <th class="col-name">Name</th>
            <th class="col-num-wide" style="text-align:right">Add Qty</th>
            <th class="col-num-wide" style="text-align:right">Add Wt.</th>
            <th class="col-num-mid" style="text-align:right">Add St.</th>
            <th class="col-num-mid" style="text-align:right">St.Amt</th>
            <th class="col-num-wide" style="text-align:right">Less Qty</th>
            <th class="col-num-wide" style="text-align:right">Less Wt.</th>
            <th class="col-num-mid" style="text-align:right">Less St.</th>
            <th class="col-num-mid" style="text-align:right">St.Amt</th>
            <th class="col-num-mid" style="text-align:right">Tag Wgt</th>
            <th class="col-num-wide" style="text-align:right">Total Wgt</th>
            <th class="col-stk">Stk Type</th>
            <th class="col-reason">Reason</th>
          </tr>
        </thead>
        <tbody id="gridBody"></tbody>
        <tfoot>
          <tr>
            <td colspan="3">Total: <span id="totCount">0</span></td>
            <td id="totAddQty" style="text-align:right">0</td>
            <td id="totAddWgt" style="text-align:right">0.000</td>
            <td id="totAddSt" style="text-align:right">0.000</td>
            <td></td>
            <td id="totLessQty" style="text-align:right">0</td>
            <td id="totLessWgt" style="text-align:right">0.000</td>
            <td id="totLessSt" style="text-align:right">0.000</td>
            <td></td>
            <td id="totTag" style="text-align:right">0.000</td>
            <td id="totAll" style="text-align:right">0.000</td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="actions">
      <div class="left">
        <button type="button" id="btnAdd">Add</button>
        <button type="button" id="btnDelete">Delete</button>
      </div>
      <div class="right">
        <button type="button" id="btnPrint">Print</button>
        <button type="button" id="btnSave">Save</button>
        <button type="button" id="btnExit">Exit</button>
      </div>
    </div>

    <div class="status" id="statusText"></div>
  </div>
</div>

<script>
const BARCODE_API = "{{ url('/api/item-stock-adjustment/barcode') }}";
const ITEM_API = "{{ url('/api/item-stock-adjustment/item') }}";
const SAVE_API = "{{ url('/api/item-stock-add-less/save') }}";
const SALESMEN = @json(array_values(array_map(fn($s) => ['code' => $s->code, 'name' => $s->name], $salesmen)));
const STOCK_TYPES = @json(array_values(array_map(fn($s) => ['code' => $s->code, 'name' => $s->name], $stockTypes)));
const DEFAULT_STK = @json($defaultStockType);

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function esc(v){ return String(v ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); }
function setStatus(msg='', ok=false){ const el=$('statusText'); el.textContent=msg; el.style.color=ok?'#0b5a18':'#980000'; }

const state = { rows: [], selected: 0 };

function stockOptions(value){
  return `<option value=""></option>` + STOCK_TYPES.map(s => `<option value="${esc(s.code)}" ${s.code===value?'selected':''}>${esc(s.code)}</option>`).join('');
}
function emptyRow(){
  return { bcode:'', code:'', name:'', addqty:0, addwgt:0, addstwgt:0, addstamt:0, lessqty:0, lesswgt:0, lessstwgt:0, lessstamt:0, tagwgt:0, stktype:DEFAULT_STK||'', reason:'Correction' };
}
function renderRows(){
  const body = $('gridBody'); body.innerHTML = '';
  if (!state.rows.length) state.rows = [emptyRow()];
  state.rows.forEach((r,i) => {
    const tot = Number(r.addwgt||0)+Number(r.lesswgt||0)+Number(r.tagwgt||0);
    const tr = document.createElement('tr');
    tr.dataset.idx = i;
    tr.innerHTML = `
      <td><input value="${esc(r.bcode)}" onchange="onBcode(${i}, this.value)" onfocus="sel(${i})"></td>
      <td><input value="${esc(r.code)}" onchange="onCode(${i}, this.value)" onfocus="sel(${i})"></td>
      <td><input value="${esc(r.name)}" onchange="setF(${i}, 'name', this.value)" onfocus="sel(${i})"></td>
      <td class="num"><input type="number" value="${Number(r.addqty||0)}" onchange="setN(${i}, 'addqty', this.value)" onfocus="sel(${i})"></td>
      <td class="num"><input type="number" step="0.001" value="${Number(r.addwgt||0).toFixed(3)}" onchange="setN(${i}, 'addwgt', this.value)" onfocus="sel(${i})"></td>
      <td class="num"><input type="number" step="0.001" value="${Number(r.addstwgt||0).toFixed(3)}" onchange="setN(${i}, 'addstwgt', this.value)" onfocus="sel(${i})"></td>
      <td class="num"><input type="number" step="0.01" value="${Number(r.addstamt||0).toFixed(2)}" onchange="setN(${i}, 'addstamt', this.value)" onfocus="sel(${i})"></td>
      <td class="num"><input type="number" value="${Number(r.lessqty||0)}" onchange="setN(${i}, 'lessqty', this.value)" onfocus="sel(${i})"></td>
      <td class="num"><input type="number" step="0.001" value="${Number(r.lesswgt||0).toFixed(3)}" onchange="setN(${i}, 'lesswgt', this.value)" onfocus="sel(${i})"></td>
      <td class="num"><input type="number" step="0.001" value="${Number(r.lessstwgt||0).toFixed(3)}" onchange="setN(${i}, 'lessstwgt', this.value)" onfocus="sel(${i})"></td>
      <td class="num"><input type="number" step="0.01" value="${Number(r.lessstamt||0).toFixed(2)}" onchange="setN(${i}, 'lessstamt', this.value)" onfocus="sel(${i})"></td>
      <td class="num"><input type="number" step="0.001" value="${Number(r.tagwgt||0).toFixed(3)}" onchange="setN(${i}, 'tagwgt', this.value)" onfocus="sel(${i})"></td>
      <td class="num"><input value="${tot.toFixed(3)}" readonly></td>
      <td><select onchange="setF(${i}, 'stktype', this.value)" onfocus="sel(${i})">${stockOptions(r.stktype)}</select></td>
      <td><input value="${esc(r.reason)}" onchange="setF(${i}, 'reason', this.value)" onfocus="sel(${i})"></td>`;
    body.appendChild(tr);
    tr.querySelector('select').value = r.stktype || '';
  });
  totals();
}
function totals(){
  let aq=0,aw=0,as=0,lq=0,lw=0,ls=0,tg=0,ct=0;
  state.rows.forEach(r => {
    if(String(r.code||'').trim()!=='') ct++;
    aq += Number(r.addqty||0); aw += Number(r.addwgt||0); as += Number(r.addstwgt||0);
    lq += Number(r.lessqty||0); lw += Number(r.lesswgt||0); ls += Number(r.lessstwgt||0);
    tg += Number(r.tagwgt||0);
  });
  $('totCount').textContent=String(ct);
  $('totAddQty').textContent=String(aq);
  $('totAddWgt').textContent=aw.toFixed(3);
  $('totAddSt').textContent=as.toFixed(3);
  $('totLessQty').textContent=String(lq);
  $('totLessWgt').textContent=lw.toFixed(3);
  $('totLessSt').textContent=ls.toFixed(3);
  $('totTag').textContent=tg.toFixed(3);
  $('totAll').textContent=(aw+lw+tg).toFixed(3);
}
function sel(i){ state.selected=i; }
function setF(i,k,v){ state.rows[i][k]=v; }
function setN(i,k,v){ state.rows[i][k]=Number(v||0); totals(); }

async function onCode(i, value){
  const code = String(value||'').trim().toUpperCase();
  state.rows[i].code = code;
  if (!code) { state.rows[i].name=''; renderRows(); return; }
  const data = await fetchJson(`${ITEM_API}?code=${encodeURIComponent(code)}`);
  if (!data.success) { setStatus(data.message||'Invalid code'); state.rows[i].code=''; state.rows[i].name=''; renderRows(); return; }
  state.rows[i].code = data.item.code||code;
  state.rows[i].name = data.item.name||'';
  state.rows[i].stktype = data.item.defstktype || DEFAULT_STK || state.rows[i].stktype;
  renderRows();
}

async function onBcode(i, value){
  const bcode = String(value||'').trim();
  state.rows[i].bcode = bcode;
  if (!bcode) return;
  const data = await fetchJson(`${BARCODE_API}?bcode=${encodeURIComponent(bcode)}`);
  if (!data.success) { setStatus(data.message||'Invalid barcode'); state.rows[i].bcode=''; renderRows(); return; }
  const row = data.barcode;
  state.rows[i].code = row.code || '';
  state.rows[i].name = row.name || '';
  state.rows[i].tagwgt = row.weight2 && row.weight2 > row.weight ? Number(row.weight2) - Number(row.weight||0) : 0;
  if ($('stktransfer').checked) {
    state.rows[i].addqty = 1;
    state.rows[i].addwgt = Number(row.weight||0);
    state.rows[i].addstwgt = Number(row.stonewgt||0);
    state.rows[i].reason = 'Transfer';
  }
  renderRows();
  if ($('stktransfer').checked) addRow();
}

function addRow(){
  const last = state.rows[state.rows.length-1];
  if (last && String(last.code||'').trim()==='') return;
  const row = emptyRow();
  row.reason = $('stktransfer').checked ? 'Transfer' : 'Correction';
  state.rows.push(row);
  state.selected = state.rows.length - 1;
  renderRows();
}
function deleteRow(){
  if (!state.rows.length) return;
  state.rows.splice(state.selected,1);
  if (!state.rows.length) state.rows=[emptyRow()];
  state.selected = Math.max(0, Math.min(state.selected, state.rows.length-1));
  renderRows();
}

async function saveRows(){
  const rows = state.rows.filter(r => String(r.code||'').trim() !== '');
  if (!rows.length) { setStatus('No valid rows to save'); return; }
  if (!confirm('You are going to change the item stock.\nDo you want to save ?')) return;
  const data = await fetchJson(SAVE_API, 'POST', {
    tdate: $('tdate').value,
    smcode: $('smcode').value.trim(),
    rows: JSON.stringify(rows),
  });
  if (!data.success) { setStatus(data.message||'Save failed'); return; }
  setStatus(data.message||'Saved', true);
  $('btnSave').disabled = true;
  $('btnAdd').disabled = true;
  $('btnDelete').disabled = true;
}

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
function closeFrame(){ window.parent.postMessage({ type:'goldapp:close-module-frame' }, '*'); }

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

$('btnAdd').addEventListener('click', addRow);
$('btnDelete').addEventListener('click', deleteRow);
$('btnSave').addEventListener('click', saveRows);
$('btnExit').addEventListener('click', closeFrame);
$('btnTopClose').addEventListener('click', closeFrame);
$('btnPrint').addEventListener('click', () => window.print());
document.addEventListener('keydown', (e) => {
  if (e.key === 'F9') { e.preventDefault(); saveRows(); }
});

$('tdate').value = new Date().toISOString().slice(0,10);
state.rows = [emptyRow()];
renderRows();
</script>
</body>
</html>
