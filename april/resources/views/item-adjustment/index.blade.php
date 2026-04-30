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
    --field:#fdfefe;
    --label:#284255;
    --text:#102331;
    --muted:#698293;
    --accent:#0f8b8d;
    --accent-strong:#0c6c6f;
    --accent-soft:#dff4f2;
    --line:#d5e0e8;
    --line-strong:#b8cad6;
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
    max-width:1180px;
    margin:28px auto;
    background:rgba(255,255,255,.9);
    border:1px solid rgba(255,255,255,.7);
    border-radius:24px;
    box-shadow:var(--shadow);
    backdrop-filter:blur(6px);
    overflow:hidden;
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
  .titlebar > div{
    font-size:22px;
    letter-spacing:.2px;
  }
  .frame{padding:24px}
  .top-grid{
    display:grid;
    grid-template-columns:132px 1fr 110px 200px;
    gap:14px 16px;
    align-items:center;
    padding:18px;
    border:1px solid var(--line);
    border-radius:20px;
    background:linear-gradient(180deg, rgba(255,255,255,.95), var(--panel-soft));
    box-shadow:0 10px 24px rgba(16,35,49,.05);
  }
  .label{
    font-weight:700;
    text-align:right;
    padding-right:4px;
    color:var(--label);
    letter-spacing:.2px;
  }
  input,select,button{font:inherit}
  input[type="text"],input[type="number"],input[type="date"],select{
    width:100%;
    height:46px;
    padding:10px 14px;
    border:1px solid var(--line);
    border-radius:14px;
    background:var(--field);
    color:var(--text);
    transition:border-color .18s ease, box-shadow .18s ease, transform .18s ease, background .18s ease;
  }
  input[readonly]{
    background:#f1f6f8;
    color:#4c6473;
  }
  input::placeholder{color:#94a8b5}
  input:focus,select:focus{
    outline:none;
    background:#fff;
    border-color:rgba(15,139,141,.6);
    box-shadow:0 0 0 4px rgba(15,139,141,.12);
    transform:translateY(-1px);
  }
  .top-grid .full{grid-column:span 3}
  .flags{
    display:flex;
    gap:18px;
    align-items:center;
    padding-left:6px;
    flex-wrap:wrap;
  }
  .flags label{
    font-weight:600;
    display:flex;
    align-items:center;
    gap:8px;
    color:var(--label);
    padding:10px 14px;
    border:1px solid var(--line);
    border-radius:999px;
    background:#fff;
  }
  .flags input[type="checkbox"]{
    width:16px;
    height:16px;
    accent-color:var(--accent);
  }
  .section-wrap{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:22px;
    margin-top:22px;
  }
  .section{
    position:relative;
    padding:22px 18px 18px;
    border:1px solid var(--line);
    border-radius:22px;
    background:linear-gradient(180deg, #ffffff, #f6fafc);
    box-shadow:0 14px 28px rgba(16,35,49,.06);
  }
  .legend{
    position:absolute;
    top:-14px;
    left:18px;
    min-width:160px;
    padding:8px 16px;
    border:none;
    border-radius:999px;
    background:linear-gradient(135deg, var(--accent), var(--accent-strong));
    color:#fff;
    text-align:center;
    font-weight:700;
    box-shadow:0 8px 20px rgba(15,139,141,.25);
  }
  .fields{
    display:grid;
    grid-template-columns:128px 1fr 54px;
    gap:12px 12px;
    align-items:center;
    margin-top:6px;
  }
  .fields .span2{grid-column:span 2}
  .fields .span3{grid-column:1 / -1}
  .lookup-btn{
    height:46px;
    padding:0;
    border:1px solid var(--line);
    border-radius:14px;
    background:linear-gradient(180deg, #ffffff, #edf5f7);
    color:var(--accent-strong);
    font-weight:800;
    transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
  }
  .lookup-btn:hover,
  .lookup-btn:focus{
    border-color:rgba(15,139,141,.45);
    box-shadow:0 10px 18px rgba(15,139,141,.10);
    transform:translateY(-1px);
  }
  .bc-note{
    color:var(--danger);
    font-weight:700;
    min-height:20px;
    display:flex;
    align-items:center;
    padding-left:4px;
  }
  .bottom{
    margin-top:24px;
    padding:24px 22px 20px;
    border:1px solid var(--line);
    border-radius:22px;
    background:linear-gradient(180deg, rgba(255,255,255,.97), #f7fafc);
    box-shadow:0 14px 28px rgba(16,35,49,.06);
  }
  .reason-label{
    text-align:center;
    font-weight:800;
    font-size:15px;
    color:var(--label);
    margin-bottom:10px;
    letter-spacing:.2px;
  }
  .reason{
    width:100%;
    height:48px;
    padding:10px 14px;
    border:1px solid var(--line);
    background:#fff;
    font-weight:600;
    border-radius:14px;
  }
  .actions-ring{
    margin:18px auto 0;
    max-width:380px;
    padding:8px;
    border:1px solid rgba(15,139,141,.18);
    border-radius:20px;
    background:linear-gradient(180deg, #edf9f8, #dff3f1);
    display:flex;
    justify-content:center;
    gap:14px;
  }
  .actions-ring button{
    min-width:140px;
    height:46px;
    border:1px solid transparent;
    border-radius:14px;
    font-weight:700;
    transition:transform .18s ease, box-shadow .18s ease, opacity .18s ease;
  }
  #btnSave{
    background:linear-gradient(135deg, var(--accent), var(--accent-strong));
    color:#fff;
    box-shadow:0 12px 24px rgba(15,139,141,.22);
  }
  #btnExit,
  #btnTopClose{
    background:#fff;
    color:var(--label);
    border-color:var(--line);
  }
  .actions-ring button:hover,
  .actions-ring button:focus,
  #btnTopClose:hover,
  #btnTopClose:focus{
    transform:translateY(-1px);
    box-shadow:0 10px 18px rgba(16,35,49,.10);
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
    margin-top:14px;
    font-weight:700;
    color:var(--danger);
    text-align:center;
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
  .suggest-list div{
    padding:10px 12px;
    cursor:pointer;
    border-radius:10px;
  }
  .suggest-list div:hover,.suggest-list div.active{background:#e9f6f6}
  @media (max-width: 980px){
    .window{
      margin:0;
      border-radius:0;
    }
    .frame{padding:16px}
    .titlebar{
      padding:16px;
      align-items:flex-start;
      gap:12px;
    }
    .titlebar > div{font-size:20px}
    .top-grid{grid-template-columns:110px 1fr}
    .top-grid .full{grid-column:auto}
    .section-wrap{grid-template-columns:1fr}
    .fields{grid-template-columns:120px 1fr 50px}
    .actions-ring{
      max-width:none;
      border-radius:18px;
    }
  }
  @media (max-width: 640px){
    .top-grid,
    .fields{
      grid-template-columns:1fr;
    }
    .label{
      text-align:left;
      padding-right:0;
      padding-top:4px;
    }
    .fields .span2{grid-column:auto}
    .lookup-btn{
      width:100%;
    }
    .actions-ring{
      flex-direction:column;
    }
    .actions-ring button,
    #btnTopClose{
      width:100%;
    }
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
    <div class="top-grid">
      <div class="label">SM Name :</div>
      <div class="suggest">
        <input type="text" id="smcode" autocomplete="off">
        <div class="suggest-list" id="smanList"></div>
      </div>

      <div class="label">Date :</div>
      <input type="date" id="tdate">

      <div></div>
      <div class="flags full">
        <label><input type="checkbox" id="ichange"> Item Change</label>
        <label><input type="checkbox" id="printslip"> Print Slip</label>
      </div>
    </div>

    <div class="section-wrap">
      <section class="section">
        <div class="legend">Item From</div>
        <div class="fields">
          <div class="label">Barcode :</div>
          <input type="text" id="frombcode">
          <div></div>

          <div></div>
          <div class="bc-note" id="frombcwgt"></div>
          <div></div>

          <div class="label">Item Code :</div>
          <div class="suggest">
            <input type="text" id="fromcode" autocomplete="off">
            <div class="suggest-list" id="fromSuggest"></div>
          </div>
          <button type="button" class="lookup-btn" data-target="from">^</button>

          <div class="label">Name :</div>
          <input type="text" id="fromname" readonly class="span2">

          <div class="label">Qty :</div>
          <input type="number" id="fromqty" step="1">
          <div></div>

          <div class="label">Weight :</div>
          <input type="number" id="fromwgt" step="0.001">
          <div></div>

          <input type="hidden" id="fromstwgt" step="0.001">
          <input type="hidden" id="fromstamt" step="0.01">
          <input type="hidden" id="fromcost" step="0.01">
          <select id="fromstktype" hidden>
            <option value=""></option>
            @foreach($stockTypes as $stockType)
              <option value="{{ $stockType->code }}">{{ $stockType->code }}{{ $stockType->name ? ' - ' . $stockType->name : '' }}</option>
            @endforeach
          </select>
          <input type="hidden" id="fromstktouch" step="0.01">
        </div>
      </section>

      <section class="section">
        <div class="legend">Item To</div>
        <div class="fields">
          <div class="label">Barcode :</div>
          <input type="text" id="tobcode">
          <div></div>

          <div></div>
          <div class="bc-note" id="tobcwgt"></div>
          <div></div>

          <div class="label">Item Code :</div>
          <div class="suggest">
            <input type="text" id="tocode" autocomplete="off">
            <div class="suggest-list" id="toSuggest"></div>
          </div>
          <button type="button" class="lookup-btn" data-target="to">^</button>

          <div class="label">Name :</div>
          <input type="text" id="toname" readonly class="span2">

          <div class="label">Qty :</div>
          <input type="number" id="toqty" step="1">
          <div></div>

          <div class="label">Weight :</div>
          <input type="number" id="towgt" step="0.001">
          <div></div>

          <input type="hidden" id="tostwgt" step="0.001">
          <input type="hidden" id="tostamt" step="0.01">
          <input type="hidden" id="tocost" step="0.01">
          <select id="tostktype" hidden>
            <option value=""></option>
            @foreach($stockTypes as $stockType)
              <option value="{{ $stockType->code }}">{{ $stockType->code }}{{ $stockType->name ? ' - ' . $stockType->name : '' }}</option>
            @endforeach
          </select>
          <input type="hidden" id="tostktouch" step="0.01">
        </div>
      </section>
    </div>

    <div class="bottom">
      <div class="reason-label">Reason For Adjustment</div>
      <input type="text" id="reason" class="reason" maxlength="30">
      <div class="actions-ring">
        <button type="button" id="btnSave">Save</button>
        <button type="button" id="btnExit">Exit</button>
      </div>
      <div class="status" id="statusText"></div>
    </div>
  </div>
</div>

<script>
const API_BASE = "{{ url('/api/item-stock-adjustment') }}";
const DEFAULT_STK = @json($defaultStockType);
const SALESMEN = @json(array_values(array_map(fn($s) => ['code' => $s->code, 'name' => $s->name], $salesmen)));

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function esc(v){ return String(v ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); }

const suggests = {
  from: { rows: [], index: -1 },
  to: { rows: [], index: -1 },
  sman: { rows: [], index: -1 },
};

function setStatus(msg = '', ok = false){
  const el = $('statusText');
  el.textContent = msg;
  el.style.color = ok ? '#0b5a18' : '#9b0000';
}

function closeFrame(){
  window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
}

function fmtWeight(v){ return Number(v || 0).toFixed(3) + ' gm'; }

function syncItemChange(){
  $('ichange').checked = $('fromcode').value.trim().toUpperCase() !== $('tocode').value.trim().toUpperCase();
}

function mirrorFromTo(){
  const fromCode = $('fromcode').value.trim().toUpperCase();
  const toCode = $('tocode').value.trim().toUpperCase();
  if (fromCode !== 'AL' && toCode !== 'AL') {
    $('toqty').value = $('fromqty').value;
    $('towgt').value = $('fromwgt').value;
    $('tostwgt').value = $('fromstwgt').value;
  }
}

async function fetchJson(url, method = 'GET', body = null){
  const opts = { method, headers: { Accept: 'application/json' } };
  if (body) {
    opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
    opts.headers['X-CSRF-TOKEN'] = csrf();
    opts.body = new URLSearchParams(body).toString();
  }
  const res = await fetch(url, opts);
  return res.json();
}

async function loadItem(side, code){
  code = String(code || '').trim().toUpperCase();
  $(side + 'code').value = code;
  if (!code) {
    $(side + 'name').value = '';
    return;
  }
  const data = await fetchJson(`${API_BASE}/item?code=${encodeURIComponent(code)}`);
  if (!data.success) {
    setStatus(data.message || 'Invalid item');
    $(side + 'code').value = '';
    $(side + 'name').value = '';
    return;
  }
  const item = data.item;
  $(side + 'code').value = item.code || code;
  $(side + 'name').value = item.name || '';
  $(side + 'cost').value = Number(item.cost || 0).toFixed(2);
  const stktype = item.defstktype || DEFAULT_STK || '';
  $(side + 'stktype').value = stktype;
  if (side === 'from' && !$('tocode').value.trim()) {
    $('tocost').value = Number(item.cost || 0).toFixed(2);
  }
  setStatus('');
  syncItemChange();
}

async function loadBarcode(side, value){
  value = String(value || '').trim();
  if (!value) return;
  const data = await fetchJson(`${API_BASE}/barcode?bcode=${encodeURIComponent(value)}`);
  if (!data.success) {
    setStatus(data.message || 'Invalid barcode');
    $(side + 'bcode').value = '';
    return;
  }
  const row = data.barcode;
  if (side === 'from' && row.stk === 'N') {
    setStatus('This stock not exist');
    $('frombcode').value = '';
    return;
  }
  $(side + 'code').value = row.code || '';
  $(side + 'name').value = row.name || '';
  $(side + 'qty').value = row.qty || 0;
  $(side + 'wgt').value = Number(row.weight || 0).toFixed(3);
  $(side + 'stwgt').value = Number(row.stonewgt || 0).toFixed(3);
  $(side + 'stamt').value = Number(row.stoneamt || 0).toFixed(2);
  $(side + 'stktouch').value = Number(row.stktouch || 0).toFixed(2);
  $(side + 'cost').value = Number(row.cost || 0).toFixed(2);
  $(side + 'stktype').value = row.defstktype || DEFAULT_STK || '';
  $(side + 'bcwgt').textContent = fmtWeight(row.weight || 0);
  if (side === 'from') {
    $('toqty').value = row.qty || 0;
    $('towgt').value = Number(row.weight || 0).toFixed(3);
    $('tostwgt').value = Number(row.stonewgt || 0).toFixed(3);
  }
  if (side === 'to') {
    $('fromqty').value = row.qty || 0;
    $('fromwgt').value = Number(row.weight || 0).toFixed(3);
    $('fromstwgt').value = Number(row.stonewgt || 0).toFixed(3);
  }
  syncItemChange();
  setStatus('');
}

function bindMirror(id, fn){
  $(id).addEventListener('change', fn);
  $(id).addEventListener('input', fn);
}

bindMirror('fromqty', mirrorFromTo);
bindMirror('fromwgt', mirrorFromTo);
bindMirror('fromstwgt', () => {
  mirrorFromTo();
  if ($('fromcode').value.trim().toUpperCase() !== 'AL' && $('tocode').value.trim().toUpperCase() !== 'AL') {
    $('tostwgt').value = $('fromstwgt').value;
  }
});
bindMirror('fromstamt', () => $('tostamt').value = $('fromstamt').value);
bindMirror('fromcode', syncItemChange);
bindMirror('tocode', syncItemChange);

$('fromcode').addEventListener('change', () => loadItem('from', $('fromcode').value));
$('tocode').addEventListener('change', () => loadItem('to', $('tocode').value));
$('frombcode').addEventListener('change', () => loadBarcode('from', $('frombcode').value));
$('tobcode').addEventListener('change', () => loadBarcode('to', $('tobcode').value));

document.querySelectorAll('.lookup-btn').forEach((btn) => {
  btn.addEventListener('click', async () => {
    const side = btn.dataset.target;
    const q = prompt('Search item code or name');
    if (!q) return;
    const data = await fetchJson(`${API_BASE}/item-search?q=${encodeURIComponent(q)}`);
    if (!data.success || !data.rows.length) {
      setStatus('No matching item');
      return;
    }
    if (data.rows.length === 1) {
      $(side + 'code').value = data.rows[0].code;
      await loadItem(side, data.rows[0].code);
      return;
    }
    const picked = prompt(data.rows.slice(0, 15).map((r, i) => `${i + 1}. ${r.code} - ${r.name}`).join('\n') + '\n\nEnter number');
    const idx = Number(picked || 0) - 1;
    if (idx >= 0 && data.rows[idx]) {
      $(side + 'code').value = data.rows[idx].code;
      await loadItem(side, data.rows[idx].code);
    }
  });
});

function renderSuggest(kind, boxId){
  const box = $(boxId);
  const state = suggests[kind];
  if (!state.rows.length) {
    box.style.display = 'none';
    return;
  }
  box.innerHTML = state.rows.map((r, i) => `<div data-idx="${i}" class="${i === state.index ? 'active' : ''}"><b>${esc(r.code)}</b> - ${esc(r.name)}</div>`).join('');
  box.style.display = 'block';
}

async function searchItems(kind, inputId, boxId){
  const q = $(inputId).value.trim();
  if (!q) {
    suggests[kind] = { rows: [], index: -1 };
    renderSuggest(kind, boxId);
    return;
  }
  const data = await fetchJson(`${API_BASE}/item-search?q=${encodeURIComponent(q)}`);
  suggests[kind] = { rows: data.rows || [], index: -1 };
  renderSuggest(kind, boxId);
}

function searchSman(){
  const q = $('smcode').value.trim().toLowerCase();
  if (!q) {
    suggests.sman = { rows: [], index: -1 };
    renderSuggest('sman', 'smanList');
    return;
  }
  suggests.sman = {
    rows: SALESMEN.filter((r) => r.code.toLowerCase().includes(q) || r.name.toLowerCase().includes(q)),
    index: -1,
  };
  renderSuggest('sman', 'smanList');
}

$('fromcode').addEventListener('input', () => searchItems('from', 'fromcode', 'fromSuggest'));
$('tocode').addEventListener('input', () => searchItems('to', 'tocode', 'toSuggest'));
$('smcode').addEventListener('input', searchSman);

[['from','fromcode','fromSuggest'], ['to','tocode','toSuggest'], ['sman','smcode','smanList']].forEach(([kind,inputId,boxId]) => {
  $(boxId).addEventListener('mousedown', async (e) => {
    e.preventDefault();
    const row = e.target.closest('[data-idx]');
    if (!row) return;
    const idx = Number(row.dataset.idx);
    const rec = suggests[kind].rows[idx];
    if (!rec) return;
    $(inputId).value = rec.code;
    $(boxId).style.display = 'none';
    if (kind === 'sman') return;
    await loadItem(kind, rec.code);
  });
});

async function saveEntry(){
  setStatus('');
  const payload = {
    tdate: $('tdate').value,
    smcode: $('smcode').value.trim(),
    frombcode: $('frombcode').value.trim(),
    fromcode: $('fromcode').value.trim().toUpperCase(),
    fromqty: $('fromqty').value || 0,
    fromwgt: $('fromwgt').value || 0,
    fromstwgt: $('fromstwgt').value || 0,
    fromstamt: $('fromstamt').value || 0,
    fromcost: $('fromcost').value || 0,
    fromstktouch: $('fromstktouch').value || 0,
    fromstktype: $('fromstktype').value,
    tobcode: $('tobcode').value.trim(),
    tocode: $('tocode').value.trim().toUpperCase(),
    toqty: $('toqty').value || 0,
    towgt: $('towgt').value || 0,
    tostwgt: $('tostwgt').value || 0,
    tostamt: $('tostamt').value || 0,
    tocost: $('tocost').value || 0,
    tostktouch: $('tostktouch').value || 0,
    tostktype: $('tostktype').value,
    reason: $('reason').value.trim(),
    ichange: $('ichange').checked ? 1 : 0,
  };
  if (!confirm('You are going to change the item stock.\nDo you want to save?')) return;
  const data = await fetchJson(`${API_BASE}/save`, 'POST', payload);
  if (!data.success) {
    setStatus(data.message || 'Save failed');
    return;
  }
  setStatus(data.message || 'Saved', true);
  if ($('printslip').checked) {
    setStatus('Saved. Print slip is not wired yet.', true);
  }
  resetForm();
}

function resetForm(){
  ['frombcode','fromcode','fromname','fromqty','fromwgt','fromstwgt','fromstamt','fromcost','fromstktouch','tobcode','tocode','toname','toqty','towgt','tostwgt','tostamt','tocost','tostktouch','reason'].forEach((id) => $(id).value = '');
  $('frombcwgt').textContent = '';
  $('tobcwgt').textContent = '';
  $('fromstktype').value = DEFAULT_STK || '';
  $('tostktype').value = DEFAULT_STK || '';
  $('ichange').checked = false;
}

$('btnSave').addEventListener('click', saveEntry);
$('btnExit').addEventListener('click', closeFrame);
$('btnTopClose').addEventListener('click', closeFrame);

document.addEventListener('keydown', (e) => {
  if (e.key === 'F9') {
    e.preventDefault();
    saveEntry();
  }
});

$('tdate').value = new Date().toISOString().slice(0, 10);
$('fromstktype').value = DEFAULT_STK || '';
$('tostktype').value = DEFAULT_STK || '';
</script>
</body>
</html>
