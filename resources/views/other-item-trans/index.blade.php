<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Other Items Transaction</title>
<style>
  :root{--bg:#c0c0c0;--panel:#d7d7d7;--field:#fff;--line:#7e7e7e;--ring:#c7ba82}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:880px;margin:12px auto;background:var(--panel);border:1px solid #8a8a8a;box-shadow:inset 0 0 0 1px #efefef}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dedede,#c9c9c9);font-weight:700}
  .frame{padding:12px}
  .top-grid{display:grid;grid-template-columns:100px 1fr 80px 1fr;gap:8px 10px;align-items:center}
  .label{font-weight:700;text-align:right;padding-right:4px}
  input,select,button{font:inherit}
  input[type="text"],input[type="number"],input[type="date"],select{width:100%;height:30px;padding:4px 8px;border:1px solid var(--line);background:var(--field)}
  input[readonly]{background:#d8d8d8}
  input:focus,select:focus{outline:none;background:#fff5e8;border-color:#9b6a18;box-shadow:0 0 0 1px #ffd08a inset}
  .suggest{position:relative}
  .suggest-list{display:none;position:absolute;left:0;right:0;top:100%;max-height:160px;overflow:auto;background:#fff;border:1px solid var(--line);z-index:20}
  .suggest-list div{padding:4px 8px;cursor:pointer}
  .suggest-list div:hover{background:#e9f1ff}
  .lookup-btn{height:30px;width:34px;padding:0;border:1px solid var(--line);background:#efefef;font-weight:700;cursor:pointer}
  .party-row{display:flex;gap:4px}
  .party-row input{flex:1}
  /* item grid */
  .grid-wrap{border:1px solid #999;background:#fff;max-height:260px;overflow:auto;margin-top:10px}
  table{width:100%;border-collapse:collapse;font-size:12px}
  th,td{border:1px solid #ccc;padding:4px 6px;text-align:left}
  th{position:sticky;top:0;background:#c8c8c8;z-index:2;font-weight:700}
  tbody tr:nth-child(even){background:#f4f4f4}
  tbody tr:hover{background:#e4eeff}
  td input{width:100%;height:24px;border:1px solid #bbb;padding:2px 6px;background:#fff}
  td input:focus{background:#fff5e8;border-color:#9b6a18;outline:none}
  td input[type="number"]{text-align:right}
  .grid-btns{margin-top:4px;display:flex;gap:6px}
  .grid-btns button{height:28px;padding:0 12px;border:1px solid #888;background:#efefef;font-weight:700;cursor:pointer}
  .grid-btns button:hover{background:#e0e0e0}
  /* totals */
  .totals{display:grid;grid-template-columns:100px 140px 80px 140px;gap:6px 10px;align-items:center;margin-top:10px}
  /* actions */
  .actions-ring{margin:14px auto 0;max-width:400px;padding:14px 22px;border:4px solid #2748ff;border-radius:999px;background:var(--ring);display:flex;justify-content:center;gap:12px;flex-wrap:wrap}
  .actions-ring button{min-width:70px;height:34px;border:1px solid #666;background:#f3f0de;font-weight:700;cursor:pointer}
  .actions-ring button:hover{background:#e8e4c8}
  .status{min-height:20px;margin-top:10px;font-weight:700;color:#9b0000;text-align:center}
  /* modal */
  .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;justify-content:center;align-items:center}
  .modal-bg.show{display:flex}
  .modal{background:#e8e8e8;border:2px solid #666;width:520px;max-width:96vw;max-height:70vh;display:flex;flex-direction:column}
  .modal-head{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:linear-gradient(#dedede,#c0c0c0);border-bottom:1px solid #999;font-weight:700}
  .modal-head button{height:28px;padding:0 10px;font-weight:700;cursor:pointer}
  .modal-search{padding:8px 12px}
  .modal-search input{width:100%;height:28px;padding:4px 8px;border:1px solid var(--line)}
  .modal-body{overflow:auto;flex:1;padding:0 12px 12px}
  .modal-body table{width:100%;border-collapse:collapse;font-size:12px}
  .modal-body th,.modal-body td{border:1px solid #bbb;padding:4px 6px;text-align:left}
  .modal-body th{position:sticky;top:0;background:#d0d0d0;z-index:2}
  .modal-body tbody tr{cursor:pointer}
  .modal-body tbody tr:hover{background:#e0eaff}
</style>
<link rel="stylesheet" href="{{ asset('css/transaction-readable.css') }}?v={{ @filemtime(public_path('css/transaction-readable.css')) }}">
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div id="titleLabel">Other Items Transaction</div>
    <button type="button" id="btnClose">Close</button>
  </div>
  <div class="frame">
    <!-- Top fields -->
    <div class="top-grid">
      <div class="label">Doc.No :</div>
      <input type="text" id="docno" readonly>
      <div class="label">Date :</div>
      <input type="date" id="tdate">

      <div class="label">Party :</div>
      <div class="party-row" style="grid-column:span 3">
        <input type="text" id="pcode" autocomplete="off" placeholder="Code">
        <button type="button" class="lookup-btn" id="btnPartyHelp">^</button>
        <input type="text" id="pname" style="flex:2" placeholder="Name">
      </div>

      <div class="label">Salesman :</div>
      <div class="suggest" style="grid-column:span 3">
        <input type="text" id="smcode" autocomplete="off" placeholder="Salesman code">
        <div class="suggest-list" id="smanList"></div>
      </div>
    </div>

    <!-- Item grid -->
    <div class="grid-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:100px">Item Code</th>
            <th>Item Name</th>
            <th style="width:60px">Qty</th>
            <th style="width:90px">Rate</th>
            <th style="width:100px">Amount</th>
            <th style="width:80px">Cost</th>
            <th style="width:30px">X</th>
          </tr>
        </thead>
        <tbody id="gridBody"></tbody>
      </table>
    </div>
    <div class="grid-btns">
      <button type="button" id="btnAddRow">+ Add Row</button>
    </div>

    <!-- Totals -->
    <div class="totals">
      <div class="label">Bill Amt :</div>
      <input type="number" id="billamt" step="0.01" value="0" readonly>
      <div class="label">Add :</div>
      <input type="number" id="addamt" step="0.01" value="0">

      <div class="label">Less :</div>
      <input type="number" id="lessamt" step="0.01" value="0">
      <div class="label">Net :</div>
      <input type="number" id="netamt" step="0.01" value="0" readonly>

      <div class="label">Received :</div>
      <input type="number" id="ramt" step="0.01" value="0">
      <div class="label">Balance :</div>
      <input type="number" id="balance" step="0.01" value="0" readonly>
    </div>

    <!-- Actions -->
    <div class="actions-ring">
      <button type="button" id="btnNew">New</button>
      <button type="button" id="btnSave">Save</button>
      <button type="button" id="btnExit">Exit</button>
    </div>
    <div class="status" id="statusText"></div>
  </div>
</div>

<!-- Party search modal -->
<div class="modal-bg" id="partyModal">
  <div class="modal">
    <div class="modal-head">
      <span>Party Search</span>
      <button type="button" id="partyModalClose">X</button>
    </div>
    <div class="modal-search">
      <input type="text" id="partyModalSearch" placeholder="Search code or name...">
    </div>
    <div class="modal-body">
      <table><thead><tr><th>Code</th><th>Name</th></tr></thead><tbody id="partyModalRows"></tbody></table>
    </div>
  </div>
</div>

<script>
const API = @json(url('/api/other-item-trans'));
const params = new URLSearchParams(window.location.search);
let SP = (params.get('sp') || 'S').toUpperCase();
let MODE = params.get('mode') || 'new'; // new or edit
let EDIT_SLNO = parseInt(params.get('slno') || '0', 10);
let initData = {};
let gridRows = [];

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

// ── Title ──
function updateTitle(){
  const label = (SP === 'P' ? 'Purchase' : 'Sales') + (MODE === 'edit' ? ' (Edit)' : '');
  $('titleLabel').textContent = 'Other Items Trans - ' + label;
  document.title = 'Other Items Trans - ' + label;
}

// ── Grid ──
function addEmptyRow(){
  gridRows.push({code:'', name:'', qty:1, rate:0, amount:0, cost:0});
  renderGrid();
}

function renderGrid(){
  const body = $('gridBody');
  body.innerHTML = '';
  gridRows.forEach((r, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td><input type="text" data-i="'+i+'" data-f="code" value="'+esc(r.code)+'" class="icode-inp"></td>'
      +'<td><input type="text" data-i="'+i+'" data-f="name" value="'+esc(r.name)+'" readonly></td>'
      +'<td><input type="number" data-i="'+i+'" data-f="qty" value="'+r.qty+'" min="0"></td>'
      +'<td><input type="number" data-i="'+i+'" data-f="rate" value="'+r.rate.toFixed(2)+'" step="0.01"></td>'
      +'<td><input type="number" data-i="'+i+'" data-f="amount" value="'+r.amount.toFixed(2)+'" step="0.01"></td>'
      +'<td><input type="number" data-i="'+i+'" data-f="cost" value="'+r.cost.toFixed(2)+'" step="0.01"></td>'
      +'<td style="text-align:center"><button type="button" data-i="'+i+'" class="del-row" style="border:none;background:none;color:red;font-weight:700;cursor:pointer">X</button></td>';
    body.appendChild(tr);
  });

  // Item code blur → load item
  body.querySelectorAll('.icode-inp').forEach(inp => {
    inp.addEventListener('blur', async e => {
      const idx = +e.target.dataset.i;
      const code = e.target.value.trim().toUpperCase();
      e.target.value = code;
      gridRows[idx].code = code;
      if (code === '') return;
      try {
        const d = await api({action:'item_load', code, sp:SP});
        if (d.success && d.item) {
          gridRows[idx].code = d.item.code;
          gridRows[idx].name = d.item.name;
          gridRows[idx].rate = d.item.rate;
          gridRows[idx].cost = d.item.cost;
          gridRows[idx].amount = gridRows[idx].qty * d.item.rate;
          renderGrid();
          calcTotals();
        }
      } catch(ex){}
    });
  });

  // Number field changes
  body.querySelectorAll('input[type="number"]').forEach(inp => {
    inp.addEventListener('change', e => {
      const idx = +e.target.dataset.i;
      const f = e.target.dataset.f;
      gridRows[idx][f] = parseFloat(e.target.value) || 0;
      // Auto-calc amount = qty * rate (if qty or rate changed)
      if (f === 'qty' || f === 'rate') {
        gridRows[idx].amount = Math.round(gridRows[idx].qty * gridRows[idx].rate * 100) / 100;
        renderGrid();
      }
      calcTotals();
    });
  });

  // Delete row
  body.querySelectorAll('.del-row').forEach(btn => {
    btn.addEventListener('click', e => {
      const idx = +e.target.dataset.i;
      gridRows.splice(idx, 1);
      renderGrid();
      calcTotals();
    });
  });
}

function calcTotals(){
  let bill = 0;
  gridRows.forEach(r => { bill += r.amount; });
  $('billamt').value = bill.toFixed(2);
  updateNet();
}

function updateNet(){
  const bill = parseFloat($('billamt').value) || 0;
  const add = parseFloat($('addamt').value) || 0;
  const less = parseFloat($('lessamt').value) || 0;
  const net = bill + add - less;
  $('netamt').value = net.toFixed(2);
  const ramt = parseFloat($('ramt').value) || 0;
  $('balance').value = (net - ramt).toFixed(2);
}

$('addamt').addEventListener('change', updateNet);
$('lessamt').addEventListener('change', updateNet);
$('ramt').addEventListener('change', updateNet);

// ── Add row ──
$('btnAddRow').addEventListener('click', addEmptyRow);

// ── Salesman suggest ──
function setupSmanSuggest(){
  const inp = $('smcode');
  const list = $('smanList');
  const all = initData.salesmen || [];

  inp.addEventListener('input', () => {
    const v = inp.value.trim().toUpperCase();
    const f = v === '' ? all : all.filter(s =>
      (s.code||'').toUpperCase().includes(v) || (s.name||'').toUpperCase().includes(v)
    );
    list.innerHTML = '';
    f.forEach(s => {
      const d = document.createElement('div');
      d.textContent = s.code + ' - ' + s.name;
      d.addEventListener('click', () => { inp.value = s.code; list.style.display='none'; });
      list.appendChild(d);
    });
    list.style.display = f.length ? 'block' : 'none';
  });
  inp.addEventListener('blur', () => setTimeout(() => list.style.display='none', 200));
}

// ── Party modal ──
$('btnPartyHelp').addEventListener('click', () => {
  $('partyModal').classList.add('show');
  $('partyModalSearch').value = '';
  $('partyModalSearch').focus();
  loadPartyList('');
});
$('partyModalClose').addEventListener('click', () => $('partyModal').classList.remove('show'));

let partyTimer;
$('partyModalSearch').addEventListener('input', () => {
  clearTimeout(partyTimer);
  partyTimer = setTimeout(() => loadPartyList($('partyModalSearch').value.trim()), 300);
});

async function loadPartyList(search){
  if (search === '') { $('partyModalRows').innerHTML = ''; return; }
  try {
    const d = await api({action:'client_search', search});
    const body = $('partyModalRows');
    body.innerHTML = '';
    (d.data||[]).forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>'+esc(r.code)+'</td><td>'+esc(r.name)+'</td>';
      tr.addEventListener('dblclick', () => {
        $('pcode').value = r.code;
        $('pname').value = r.name;
        $('partyModal').classList.remove('show');
      });
      body.appendChild(tr);
    });
  } catch(e){}
}

// ── New ──
function resetForm(){
  $('docno').value = initData.nextDocno || '';
  $('tdate').value = initData.today || '';
  $('pcode').value = ''; $('pname').value = '';
  $('smcode').value = '';
  $('billamt').value = '0.00';
  $('addamt').value = '0.00'; $('lessamt').value = '0.00';
  $('ramt').value = '0.00'; $('netamt').value = '0.00'; $('balance').value = '0.00';
  gridRows = [];
  addEmptyRow();
  EDIT_SLNO = 0;
  MODE = 'new';
  updateTitle();
  setStatus('Ready', true);
  $('pcode').focus();
}
$('btnNew').addEventListener('click', resetForm);

// ── Save ──
$('btnSave').addEventListener('click', async () => {
  const pcode = $('pcode').value.trim().toUpperCase();
  const validItems = gridRows.filter(r => r.code !== '');
  if (validItems.length === 0) { setStatus('Add at least one item'); return; }

  const label = SP === 'P' ? 'Purchase' : 'Sales';
  if (!confirm('Save ' + label + ' transaction?')) return;

  try {
    setStatus('Saving...');
    const payload = {
      action: 'save',
      sp: SP,
      edit_slno: EDIT_SLNO,
      docno: $('docno').value.trim(),
      tdate: $('tdate').value,
      pcode: pcode,
      pname: $('pname').value.trim(),
      smcode: $('smcode').value.trim().toUpperCase(),
      billamt: parseFloat($('billamt').value) || 0,
      addamt: parseFloat($('addamt').value) || 0,
      lessamt: parseFloat($('lessamt').value) || 0,
      ramt: parseFloat($('ramt').value) || 0,
      items: validItems
    };
    const d = await api(payload);
    setStatus(d.message || 'Saved', d.success);
    if (d.success) {
      // Re-init to get next docno
      const r = await api({action:'init', sp:SP});
      initData = r;
      resetForm();
    }
  } catch(e){ setStatus('Save error: ' + e.message); }
});

// ── Load record (for edit mode) ──
async function loadRecord(slno){
  try {
    setStatus('Loading...');
    const d = await api({action:'load', slno});
    if (!d.success) { setStatus(d.error || 'Not found'); return; }
    const rec = d.record;
    EDIT_SLNO = rec.slno;
    MODE = 'edit';
    updateTitle();

    $('docno').value = rec.docno;
    $('tdate').value = rec.tdate;
    $('pcode').value = rec.pcode;
    $('pname').value = rec.pname;
    $('smcode').value = rec.smcode;
    $('billamt').value = rec.billamt.toFixed(2);
    $('addamt').value = rec.addamt.toFixed(2);
    $('lessamt').value = rec.lessamt.toFixed(2);
    $('ramt').value = rec.ramt.toFixed(2);

    gridRows = (d.items || []).map(it => ({
      code: it.code, name: it.name, qty: it.qty,
      rate: it.rate, amount: it.amount, cost: it.cost
    }));
    renderGrid();
    calcTotals();
    setStatus('Loaded: ' + rec.docno, true);
  } catch(e){ setStatus('Load error: ' + e.message); }
}

// ── Exit / Close ──
$('btnExit').addEventListener('click', closeFrame);
$('btnClose').addEventListener('click', closeFrame);

// ── F9 save shortcut ──
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); $('btnSave').click(); }
  if (e.key === 'Escape' && $('partyModal').classList.contains('show')) $('partyModal').classList.remove('show');
});

// ── Init ──
async function init(){
  try {
    updateTitle();
    const d = await api({action:'init', sp:SP});
    initData = d;
    $('tdate').value = d.today || '';
    $('docno').value = d.nextDocno || '';
    setupSmanSuggest();

    if (MODE === 'edit' && EDIT_SLNO > 0) {
      await loadRecord(EDIT_SLNO);
    } else {
      addEmptyRow();
      setStatus('Ready. Enter items and save.', true);
      $('pcode').focus();
    }
  } catch(e){ setStatus('Init failed: ' + e.message); }
}

init();
</script>
</body>
</html>
