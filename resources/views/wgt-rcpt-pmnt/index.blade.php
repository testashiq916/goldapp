<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Deposit Weight Receipt / Payment</title>
<style>
  :root{
    --bg:#c0c0c0;
    --panel:#d7d7d7;
    --field:#ffffff;
    --label:#111;
    --accent:#ff9600;
    --line:#7e7e7e;
    --soft:#ececec;
    --red:#d10000;
    --ring:#c7ba82;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:900px;margin:12px auto;background:var(--panel);border:1px solid #8a8a8a;box-shadow:inset 0 0 0 1px #efefef}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dedede,#c9c9c9);font-weight:700}
  .frame{padding:12px}
  .top-grid{display:grid;grid-template-columns:110px 1fr 90px 1fr;gap:8px 10px;align-items:center}
  .label{font-weight:700;text-align:right;padding-right:4px}
  input,select,button{font:inherit}
  input[type="text"],input[type="number"],input[type="date"],select{
    width:100%;height:32px;padding:5px 8px;border:1px solid var(--line);background:var(--field)
  }
  input[readonly]{background:#d8d8d8}
  input:focus,select:focus{outline:none;background:#fff5e8;border-color:#9b6a18;box-shadow:0 0 0 1px #ffd08a inset}
  .top-grid .full{grid-column:span 3}
  .section{position:relative;padding:16px 14px 14px;border:1px solid #9d9d9d;background:#cfcfcf;margin-top:16px}
  .legend{position:absolute;top:-12px;left:18px;min-width:160px;padding:4px 14px;border:1px solid #8b8b8b;background:#fff;text-align:center;font-weight:700}
  .fields{display:grid;grid-template-columns:110px 1fr 90px 1fr;gap:8px 10px;align-items:center}
  .fields .span3{grid-column:2 / -1}
  .fields .span2{grid-column:span 2}
  .lookup-btn{height:32px;width:36px;padding:0;border:1px solid var(--line);background:#efefef;font-weight:700;cursor:pointer}
  .suggest{position:relative}
  .suggest-list{display:none;position:absolute;left:0;right:0;top:100%;max-height:180px;overflow:auto;background:#fff;border:1px solid var(--line);z-index:20}
  .suggest-list div{padding:5px 8px;cursor:pointer}
  .suggest-list div:hover,.suggest-list div.active{background:#e9f1ff}
  .bottom{margin-top:14px}
  .actions-ring{margin:10px auto 0;max-width:520px;padding:16px 22px;border:4px solid #2748ff;border-radius:999px;background:var(--ring);display:flex;justify-content:center;gap:14px;flex-wrap:wrap}
  .actions-ring button{min-width:80px;height:36px;border:1px solid #666;background:#f3f0de;font-weight:700;cursor:pointer}
  .actions-ring button:hover{background:#e8e4c8}
  .status{min-height:20px;margin-top:10px;font-weight:700;color:#9b0000;text-align:center}
  /* Help modal */
  .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;justify-content:center;align-items:center}
  .modal-bg.show{display:flex}
  .modal{background:#e8e8e8;border:2px solid #666;width:720px;max-width:96vw;max-height:80vh;display:flex;flex-direction:column}
  .modal-head{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:linear-gradient(#dedede,#c0c0c0);border-bottom:1px solid #999;font-weight:700}
  .modal-head button{height:28px;padding:0 10px;font-weight:700;cursor:pointer}
  .modal-search{padding:8px 12px;display:flex;gap:8px}
  .modal-search input{flex:1;height:30px;padding:4px 8px;border:1px solid var(--line)}
  .modal-body{overflow:auto;flex:1;padding:0 12px 12px}
  .modal-body table{width:100%;border-collapse:collapse;font-size:12px}
  .modal-body th,.modal-body td{border:1px solid #bbb;padding:5px 6px;text-align:left}
  .modal-body th{position:sticky;top:0;background:#d0d0d0;z-index:2}
  .modal-body tbody tr{cursor:pointer}
  .modal-body tbody tr:hover{background:#e0eaff}
  .modal-body tbody tr.sel{background:#c8d8ff}
  /* Print slip */
  .print-area{display:none}
  @media print{
    body>*{display:none!important}
    .print-area{display:block!important;font-family:monospace;font-size:12px;padding:10px}
    .print-area h3{margin:0 0 6px;font-size:14px;text-align:center}
    .print-area table{width:100%;border-collapse:collapse}
    .print-area td{padding:3px 6px}
    .print-area .r{text-align:right}
  }
  @media(max-width:700px){
    .top-grid,.fields{grid-template-columns:100px 1fr}
    .top-grid .full,.fields .span3,.fields .span2{grid-column:auto}
  }
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div>Deposit Weight Receipt / Payment</div>
    <button type="button" id="btnClose">Close</button>
  </div>

  <div class="frame">
    <!-- Top row -->
    <div class="top-grid">
      <div class="label">Doc.No :</div>
      <input type="text" id="docno" readonly>

      <div class="label">Date :</div>
      <input type="date" id="tdate">

      <div class="label">Gold Rate :</div>
      <input type="number" id="grate" step="0.01">

      <div class="label">SM Name :</div>
      <div class="suggest">
        <input type="text" id="smcode" autocomplete="off">
        <div class="suggest-list" id="smanList"></div>
      </div>
    </div>

    <!-- Party section -->
    <div class="section">
      <div class="legend">Party Details</div>
      <div class="fields">
        <div class="label">Party Code :</div>
        <div class="suggest" style="display:flex;gap:4px">
          <input type="text" id="pcode" autocomplete="off" style="flex:1">
          <button type="button" class="lookup-btn" id="btnPartyHelp">^</button>
        </div>

        <div class="label">Name :</div>
        <input type="text" id="pname" readonly>
      </div>
    </div>

    <!-- Item section -->
    <div class="section">
      <div class="legend">Item Details</div>
      <div class="fields">
        <div class="label">Item Code :</div>
        <div class="suggest" style="display:flex;gap:4px">
          <input type="text" id="icode" autocomplete="off" style="flex:1">
          <button type="button" class="lookup-btn" id="btnItemHelp">^</button>
        </div>

        <div class="label">Name :</div>
        <input type="text" id="iname" readonly>

        <div class="label">Qty :</div>
        <input type="number" id="qty" step="1" value="1">

        <div class="label">Weight :</div>
        <input type="number" id="weight" step="0.001">

        <div class="label">St.Wgt :</div>
        <input type="number" id="stwgt" step="0.001" value="0">

        <div class="label">Touch :</div>
        <input type="number" id="touch" step="0.001">

        <div class="label">Conv Touch :</div>
        <input type="number" id="ctouch" step="0.001">

        <div class="label">Net Wgt :</div>
        <input type="number" id="netwgt" step="0.001" readonly>

        <div class="label">Stk Type :</div>
        <select id="stktype"></select>

        <div class="label">Rcpt/Pmnt :</div>
        <select id="ttype">
          <option value="R">Receipt</option>
          <option value="P">Payment</option>
        </select>

        <div class="label">Note :</div>
        <input type="text" id="note" maxlength="60" class="span3">
      </div>
    </div>

    <!-- Flags -->
    <div style="display:flex;gap:18px;align-items:center;padding:10px 4px 0">
      <label style="font-weight:700;display:flex;align-items:center;gap:6px">
        <input type="checkbox" id="chkPrint"> Print Slip
      </label>
    </div>

    <!-- Bottom actions -->
    <div class="bottom">
      <div class="actions-ring">
        <button type="button" id="btnNew">New</button>
        <button type="button" id="btnSave">Save</button>
        <button type="button" id="btnEdit">Edit</button>
        <button type="button" id="btnCancel">Cancel</button>
        <button type="button" id="btnPrint">Print</button>
        <button type="button" id="btnExit">Exit</button>
      </div>
      <div class="status" id="statusText"></div>
    </div>
  </div>
</div>

<!-- Help Modal (record search) -->
<div class="modal-bg" id="helpModal">
  <div class="modal">
    <div class="modal-head">
      <span>Search Records</span>
      <button type="button" id="helpClose">X</button>
    </div>
    <div class="modal-search">
      <input type="text" id="helpSearch" placeholder="Search doc no, item, party...">
      <input type="date" id="helpDateFrom">
      <input type="date" id="helpDateTo">
      <button type="button" id="helpSearchBtn">Go</button>
    </div>
    <div class="modal-body">
      <table>
        <thead><tr><th>Sl</th><th>Doc.No</th><th>Date</th><th>Item</th><th>Party</th><th>Qty</th><th>Wgt</th><th>Type</th></tr></thead>
        <tbody id="helpRows"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Party search modal -->
<div class="modal-bg" id="partyModal">
  <div class="modal">
    <div class="modal-head">
      <span>Party Search</span>
      <button type="button" id="partyClose">X</button>
    </div>
    <div class="modal-search">
      <input type="text" id="partySearch" placeholder="Search account code or name...">
    </div>
    <div class="modal-body">
      <table>
        <thead><tr><th>Code</th><th>Name</th></tr></thead>
        <tbody id="partyRows"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Print area (hidden, shown only for print) -->
<div class="print-area" id="printArea"></div>

<script>
const API = @json(url('/api/wgt-rcpt-pmnt'));
let MODE = 'A'; // A=Add, E=Edit, V=View
let currentSlno = 0;
let initData = {};
let smanSuggest = { rows: [], idx: -1 };
let itemSuggest = { rows: [], idx: -1 };

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function esc(v){ return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function setStatus(msg = '', ok = false){
  const el = $('statusText');
  el.textContent = msg;
  el.style.color = ok ? '#0b5a18' : '#9b0000';
}

function closeFrame(){
  window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
}

async function api(payload){
  const res = await fetch(API, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrf(),
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify(payload)
  });
  const data = await res.json();
  if (!res.ok && !data.success) throw new Error(data.error || data.message || 'Request failed');
  return data;
}

// ── Net weight calculation ──
function calcNetwgt(){
  const w = parseFloat($('weight').value) || 0;
  const sw = parseFloat($('stwgt').value) || 0;
  const t = parseFloat($('touch').value) || 0;
  const ct = parseFloat($('ctouch').value) || 0;
  let nw = 0;
  if (ct > 0) nw = ((w - sw) * t) / ct;
  $('netwgt').value = nw > 0 ? nw.toFixed(3) : '0.000';
}

// ── Suggest dropdown helpers ──
function showSuggest(listEl, rows, formatter){
  listEl.innerHTML = '';
  rows.forEach((r, i) => {
    const d = document.createElement('div');
    d.textContent = formatter(r);
    d.dataset.index = i;
    listEl.appendChild(d);
  });
  listEl.style.display = rows.length ? 'block' : 'none';
}
function hideSuggest(listEl){ listEl.style.display = 'none'; }

// ── Salesman suggest ──
function setupSmanSuggest(){
  const inp = $('smcode');
  const list = $('smanList');
  const allSmen = initData.salesmen || [];

  inp.addEventListener('input', () => {
    const v = inp.value.trim().toUpperCase();
    smanSuggest.rows = v === '' ? allSmen : allSmen.filter(s =>
      (s.code || '').toUpperCase().includes(v) || (s.name || '').toUpperCase().includes(v)
    );
    smanSuggest.idx = -1;
    showSuggest(list, smanSuggest.rows, r => r.code + ' - ' + r.name);
  });

  inp.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown') { smanSuggest.idx = Math.min(smanSuggest.idx + 1, smanSuggest.rows.length - 1); highlightSuggest(list, smanSuggest.idx); e.preventDefault(); }
    else if (e.key === 'ArrowUp') { smanSuggest.idx = Math.max(smanSuggest.idx - 1, 0); highlightSuggest(list, smanSuggest.idx); e.preventDefault(); }
    else if (e.key === 'Enter' && smanSuggest.idx >= 0) { pickSman(smanSuggest.rows[smanSuggest.idx]); e.preventDefault(); }
    else if (e.key === 'Escape') { hideSuggest(list); }
  });

  list.addEventListener('click', e => {
    const d = e.target.closest('div[data-index]');
    if (d) pickSman(smanSuggest.rows[+d.dataset.index]);
  });

  inp.addEventListener('blur', () => setTimeout(() => hideSuggest(list), 200));
}

function pickSman(s){
  $('smcode').value = s.code;
  hideSuggest($('smanList'));
}

// ── Item suggest ──
function setupItemSuggest(){
  const inp = $('icode');
  const list = document.createElement('div');
  list.className = 'suggest-list';
  list.id = 'itemSuggestList';
  inp.parentElement.appendChild(list);

  let timer;
  inp.addEventListener('input', () => {
    clearTimeout(timer);
    const v = inp.value.trim();
    if (v.length < 1) { hideSuggest(list); return; }
    timer = setTimeout(async () => {
      try {
        const d = await api({ action: 'item_search', search: v });
        itemSuggest.rows = d.data || [];
        itemSuggest.idx = -1;
        showSuggest(list, itemSuggest.rows, r => r.code + ' - ' + r.name);
      } catch(e) { hideSuggest(list); }
    }, 250);
  });

  inp.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown') { itemSuggest.idx = Math.min(itemSuggest.idx + 1, itemSuggest.rows.length - 1); highlightSuggest(list, itemSuggest.idx); e.preventDefault(); }
    else if (e.key === 'ArrowUp') { itemSuggest.idx = Math.max(itemSuggest.idx - 1, 0); highlightSuggest(list, itemSuggest.idx); e.preventDefault(); }
    else if (e.key === 'Enter' && itemSuggest.idx >= 0) { pickItem(itemSuggest.rows[itemSuggest.idx]); e.preventDefault(); }
    else if (e.key === 'Escape') { hideSuggest(list); }
  });

  list.addEventListener('click', e => {
    const d = e.target.closest('div[data-index]');
    if (d) pickItem(itemSuggest.rows[+d.dataset.index]);
  });

  inp.addEventListener('blur', () => {
    setTimeout(() => hideSuggest(list), 200);
    // Auto-load item on blur
    const v = inp.value.trim().toUpperCase();
    if (v && v !== $('iname').dataset.loadedCode) loadItemByCode(v);
  });
}

async function pickItem(item){
  $('icode').value = item.code;
  $('iname').value = item.name;
  $('iname').dataset.loadedCode = item.code;
  hideSuggest($('itemSuggestList'));
  // Load item details for default stktype
  try {
    const d = await api({ action: 'item_load', code: item.code });
    if (d.success && d.item) {
      if (d.item.defstktype) $('stktype').value = d.item.defstktype;
      if (d.item.stktouch > 0 && !parseFloat($('ctouch').value)) $('ctouch').value = d.item.stktouch.toFixed(3);
    }
  } catch(e) {}
}

async function loadItemByCode(code){
  try {
    const d = await api({ action: 'item_load', code: code });
    if (d.success && d.item) {
      $('icode').value = d.item.code;
      $('iname').value = d.item.name;
      $('iname').dataset.loadedCode = d.item.code;
      if (d.item.defstktype) $('stktype').value = d.item.defstktype;
      if (d.item.stktouch > 0 && !parseFloat($('ctouch').value)) $('ctouch').value = d.item.stktouch.toFixed(3);
    } else {
      $('iname').value = '';
      $('iname').dataset.loadedCode = '';
    }
  } catch(e) {
    $('iname').value = '';
    $('iname').dataset.loadedCode = '';
  }
}

function highlightSuggest(list, idx){
  Array.from(list.children).forEach((d, i) => d.classList.toggle('active', i === idx));
  if (idx >= 0 && list.children[idx]) list.children[idx].scrollIntoView({ block: 'nearest' });
}

// ── Party code blur → load name ──
$('pcode').addEventListener('blur', async () => {
  const code = $('pcode').value.trim().toUpperCase();
  if (!code) { $('pname').value = ''; return; }
  try {
    const d = await api({ action: 'account_search', search: code });
    const match = (d.data || []).find(r => (r.accode || '').toUpperCase() === code);
    $('pname').value = match ? match.name : '';
  } catch(e) { $('pname').value = ''; }
});

// ── Party modal ──
$('btnPartyHelp').addEventListener('click', async () => {
  $('partyModal').classList.add('show');
  $('partySearch').value = '';
  $('partySearch').focus();
  await loadPartyList('');
});
$('partyClose').addEventListener('click', () => $('partyModal').classList.remove('show'));

$('partySearch').addEventListener('input', debounce(async () => {
  await loadPartyList($('partySearch').value.trim());
}, 300));

async function loadPartyList(search){
  try {
    const d = await api({ action: 'account_search', search });
    const body = $('partyRows');
    body.innerHTML = '';
    (d.data || []).forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>' + esc(r.accode) + '</td><td>' + esc(r.name) + '</td>';
      tr.addEventListener('dblclick', () => {
        $('pcode').value = r.accode;
        $('pname').value = r.name;
        $('partyModal').classList.remove('show');
      });
      body.appendChild(tr);
    });
  } catch(e) {}
}

// ── Item lookup button ──
$('btnItemHelp').addEventListener('click', () => {
  $('icode').focus();
  $('icode').dispatchEvent(new Event('input'));
});

// ── Help modal (record search for edit/cancel) ──
$('btnEdit').addEventListener('click', () => openHelpModal('edit'));
$('btnCancel').addEventListener('click', () => openHelpModal('cancel'));

function openHelpModal(purpose){
  $('helpModal').classList.add('show');
  $('helpModal').dataset.purpose = purpose;
  $('helpSearch').value = '';
  $('helpDateFrom').value = '';
  $('helpDateTo').value = '';
  $('helpSearch').focus();
  loadHelpList();
}

$('helpClose').addEventListener('click', () => $('helpModal').classList.remove('show'));
$('helpSearchBtn').addEventListener('click', loadHelpList);
$('helpSearch').addEventListener('keydown', e => { if (e.key === 'Enter') loadHelpList(); });

async function loadHelpList(){
  try {
    const d = await api({
      action: 'list',
      search: $('helpSearch').value.trim(),
      date_from: $('helpDateFrom').value,
      date_to: $('helpDateTo').value
    });
    const body = $('helpRows');
    body.innerHTML = '';
    (d.data || []).forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>' + r.slno + '</td><td>' + esc(r.docno) + '</td><td>' + esc(r.tdate) + '</td>'
        + '<td>' + esc(r.icode) + '</td><td>' + esc(r.pname || r.pcode) + '</td>'
        + '<td>' + r.qty + '</td><td>' + r.weight.toFixed(3) + '</td>'
        + '<td>' + (r.ttype === 'R' ? 'Rcpt' : 'Pmnt') + '</td>';
      tr.addEventListener('dblclick', () => selectHelpRecord(r.slno));
      body.appendChild(tr);
    });
  } catch(e) { setStatus('Error loading records: ' + e.message); }
}

async function selectHelpRecord(slno){
  $('helpModal').classList.remove('show');
  const purpose = $('helpModal').dataset.purpose || 'edit';

  if (purpose === 'cancel') {
    if (!confirm('Cancel record #' + slno + '? This will reverse the stock changes and delete the record.')) return;
    try {
      const d = await api({ action: 'delete', slno });
      setStatus(d.message || 'Record cancelled', true);
      resetForm();
    } catch(e) {
      setStatus('Cancel failed: ' + e.message);
    }
    return;
  }

  // Edit: load the record into form
  try {
    const d = await api({ action: 'load', slno });
    if (!d.success) { setStatus(d.error || 'Load failed'); return; }
    MODE = 'E';
    currentSlno = d.record.slno;
    populateForm(d.record);
    setStatus('Editing record #' + currentSlno + ' (Doc: ' + d.record.docno + ')', true);
  } catch(e) {
    setStatus('Load failed: ' + e.message);
  }
}

function populateForm(r){
  $('docno').value = r.docno || '';
  $('tdate').value = r.tdate || '';
  $('grate').value = r.grate || '';
  $('pcode').value = r.pcode || '';
  $('pname').value = r.pname || '';
  $('smcode').value = r.smcode || '';
  $('icode').value = r.icode || '';
  $('iname').value = r.iname || '';
  $('iname').dataset.loadedCode = r.icode || '';
  $('qty').value = r.qty || 1;
  $('weight').value = r.weight || '';
  $('stwgt').value = r.stwgt || 0;
  $('touch').value = r.touch || '';
  $('ctouch').value = r.ctouch || '';
  $('stktype').value = r.stktype || '';
  $('ttype').value = r.ttype || 'R';
  $('note').value = r.note || '';
  calcNetwgt();
}

// ── New button ──
$('btnNew').addEventListener('click', () => {
  resetForm();
  setStatus('New entry', true);
});

function resetForm(){
  MODE = 'A';
  currentSlno = 0;
  $('docno').value = initData.nextDocno || '';
  $('tdate').value = initData.today || '';
  $('grate').value = initData.grate || '';
  $('smcode').value = '';
  $('pcode').value = '';
  $('pname').value = '';
  $('icode').value = '';
  $('iname').value = '';
  $('iname').dataset.loadedCode = '';
  $('qty').value = 1;
  $('weight').value = '';
  $('stwgt').value = 0;
  $('touch').value = '';
  $('ctouch').value = '';
  $('netwgt').value = '0.000';
  $('stktype').value = initData.defaultStkType || '';
  $('ttype').value = 'R';
  $('note').value = '';
  setStatus('');
}

// ── Save ──
$('btnSave').addEventListener('click', doSave);

async function doSave(){
  const icode = $('icode').value.trim().toUpperCase();
  if (!icode) { setStatus('Item code is required'); $('icode').focus(); return; }
  const weight = parseFloat($('weight').value) || 0;
  if (weight <= 0) { setStatus('Weight is required'); $('weight').focus(); return; }
  const qty = parseInt($('qty').value) || 0;
  if (qty <= 0) { setStatus('Qty must be > 0'); $('qty').focus(); return; }

  const payload = {
    action: 'save',
    edit_slno: MODE === 'E' ? currentSlno : 0,
    icode: icode,
    tdate: $('tdate').value,
    grate: parseFloat($('grate').value) || 0,
    pcode: $('pcode').value.trim().toUpperCase(),
    pname: $('pname').value.trim(),
    smcode: $('smcode').value.trim().toUpperCase(),
    qty: qty,
    weight: weight,
    stwgt: parseFloat($('stwgt').value) || 0,
    touch: parseFloat($('touch').value) || 0,
    ctouch: parseFloat($('ctouch').value) || 0,
    stktype: $('stktype').value.trim().toUpperCase(),
    ttype: $('ttype').value,
    note: $('note').value.trim(),
  };

  try {
    const d = await api(payload);
    if (d.success) {
      setStatus(d.message || 'Saved', true);
      currentSlno = d.slno || 0;

      if ($('chkPrint').checked) {
        printSlip(payload, d.docno || '', d.slno || 0);
      }

      // Refresh init to get next doc number
      const init = await api({ action: 'init' });
      initData = init;
      populateStkTypeDropdown();

      resetForm();
    } else {
      setStatus(d.error || d.message || 'Save failed');
    }
  } catch(e) {
    setStatus('Save error: ' + e.message);
  }
}

// ── Print ──
$('btnPrint').addEventListener('click', () => {
  if (!currentSlno && MODE === 'A') { setStatus('Nothing to print'); return; }
  const data = {
    docno: $('docno').value,
    tdate: $('tdate').value,
    grate: $('grate').value,
    pcode: $('pcode').value,
    pname: $('pname').value,
    icode: $('icode').value,
    iname: $('iname').value,
    qty: $('qty').value,
    weight: $('weight').value,
    stwgt: $('stwgt').value,
    touch: $('touch').value,
    ctouch: $('ctouch').value,
    netwgt: $('netwgt').value,
    stktype: $('stktype').value,
    ttype: $('ttype').value === 'R' ? 'Receipt' : 'Payment',
    note: $('note').value,
    smcode: $('smcode').value,
  };
  printSlip(data, data.docno, currentSlno);
});

function printSlip(data, docno, slno){
  const area = $('printArea');
  area.innerHTML = '<h3>DEPOSIT WEIGHT ' + esc(data.ttype === 'P' || data.ttype === 'Payment' ? 'PAYMENT' : 'RECEIPT') + '</h3>'
    + '<table>'
    + '<tr><td>Doc.No</td><td>: ' + esc(docno) + '</td><td>Date</td><td>: ' + esc(data.tdate) + '</td></tr>'
    + '<tr><td>Gold Rate</td><td>: ' + esc(data.grate) + '</td><td>Salesman</td><td>: ' + esc(data.smcode) + '</td></tr>'
    + '<tr><td>Party</td><td colspan="3">: ' + esc(data.pcode) + ' - ' + esc(data.pname) + '</td></tr>'
    + '<tr><td>Item</td><td colspan="3">: ' + esc(data.icode) + ' - ' + esc(data.iname || '') + '</td></tr>'
    + '<tr><td>Qty</td><td>: ' + esc(data.qty) + '</td><td>Weight</td><td>: ' + esc(data.weight) + ' gm</td></tr>'
    + '<tr><td>Stone Wgt</td><td>: ' + esc(data.stwgt) + ' gm</td><td>Touch</td><td>: ' + esc(data.touch) + '</td></tr>'
    + '<tr><td>Conv Touch</td><td>: ' + esc(data.ctouch) + '</td><td>Net Wgt</td><td>: ' + esc(data.netwgt) + ' gm</td></tr>'
    + '<tr><td>Stk Type</td><td>: ' + esc(data.stktype) + '</td><td>Type</td><td>: ' + esc(data.ttype) + '</td></tr>'
    + (data.note ? '<tr><td>Note</td><td colspan="3">: ' + esc(data.note) + '</td></tr>' : '')
    + '<tr><td>Sl.No</td><td>: ' + slno + '</td><td></td><td></td></tr>'
    + '</table>';
  setTimeout(() => window.print(), 200);
}

// ── Exit / Close ──
$('btnExit').addEventListener('click', closeFrame);
$('btnClose').addEventListener('click', closeFrame);

// ── Keyboard shortcut ──
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); doSave(); }
  if (e.key === 'Escape') {
    if ($('helpModal').classList.contains('show')) { $('helpModal').classList.remove('show'); return; }
    if ($('partyModal').classList.contains('show')) { $('partyModal').classList.remove('show'); return; }
  }
});

// ── Auto-calc net weight on field change ──
['weight', 'stwgt', 'touch', 'ctouch'].forEach(id => {
  $(id).addEventListener('input', calcNetwgt);
});

// ── Debounce helper ──
function debounce(fn, ms){
  let t;
  return function(...args){ clearTimeout(t); t = setTimeout(() => fn.apply(this, args), ms); };
}

// ── Populate stock type dropdown ──
function populateStkTypeDropdown(){
  const sel = $('stktype');
  const cur = sel.value;
  sel.innerHTML = '<option value=""></option>';
  (initData.stockTypes || []).forEach(r => {
    const o = document.createElement('option');
    o.value = r.code;
    o.textContent = r.code + (r.name ? ' - ' + r.name : '');
    sel.appendChild(o);
  });
  sel.value = cur || initData.defaultStkType || '';
}

// ── Init ──
async function init(){
  try {
    const d = await api({ action: 'init' });
    initData = d;
    $('docno').value = d.nextDocno || '';
    $('tdate').value = d.today || '';
    $('grate').value = d.grate || '';
    populateStkTypeDropdown();
    $('stktype').value = d.defaultStkType || '';
    setupSmanSuggest();
    setupItemSuggest();
    setStatus('Ready', true);
  } catch(e) {
    setStatus('Init failed: ' + e.message);
  }
}

init();
</script>
</body>
</html>
