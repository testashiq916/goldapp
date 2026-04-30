<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;color:#1e293b;font-size:13px;height:100vh;overflow:hidden;display:flex;flex-direction:column}

/* ── Toolbar ──────────────────────────────────────────────────── */
.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:8px 16px;display:flex;flex-direction:column;gap:6px;flex-shrink:0}
.tb-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap;margin-right:8px}
.tb-lbl{color:rgba(255,255,255,.8);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.tb-input,.tb-select{height:30px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 8px;font-size:12px;background:rgba(255,255,255,.12);color:#fff;outline:none;min-width:80px}
.tb-input:focus,.tb-select:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.tb-select option{background:#1e3a5f;color:#fff}
.tb-input::placeholder{color:rgba(255,255,255,.4)}
.tb-check{display:flex;align-items:center;gap:4px;color:rgba(255,255,255,.85);font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap}
.tb-check input{width:14px;height:14px;accent-color:#60a5fa;cursor:pointer}
.f-group{display:flex;align-items:center;gap:4px}
.sep{width:1px;height:24px;background:rgba(255,255,255,.2);margin:0 4px}
.btn{padding:5px 14px;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;gap:4px}
.btn:disabled{opacity:.4;cursor:not-allowed}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}
.btn-warn{background:#f59e0b;color:#1e293b}.btn-warn:hover{background:#d97706}
.btn-red{background:#ef4444;color:#fff}.btn-red:hover{background:#dc2626}
.admin-btns{display:none;gap:6px;align-items:center}
.admin-btns.show{display:flex}

/* ── Grid ─────────────────────────────────────────────────────── */
.grid-wrap{flex:1;overflow:auto;padding:0}
table{width:100%;border-collapse:collapse;background:#fff;font-size:12px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:6px 8px;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2;cursor:pointer;user-select:none}
th:hover{background:#e2e8f0}
th.num{text-align:right}
td{padding:5px 8px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
tr.sel td{background:#dbeafe !important}
tr[data-billno]{cursor:pointer}
tr.group-hdr td{background:#eff6ff;font-weight:700;color:#1e40af;font-size:11px;padding:4px 8px;border-bottom:2px solid #bfdbfe}

/* ── Summary ──────────────────────────────────────────────────── */
.summary{background:#fff;border-top:2px solid #e2e8f0;padding:8px 16px;display:flex;gap:20px;flex-wrap:wrap;font-size:12px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:12px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}
.wait-mask{position:fixed;inset:0;background:rgba(15,23,42,.35);display:none;align-items:center;justify-content:center;z-index:300}
.wait-mask.show{display:flex}
.wait-card{background:#fff;border-radius:12px;padding:18px 24px;min-width:260px;box-shadow:0 12px 40px rgba(0,0,0,.25);text-align:center}
.wait-card strong{display:block;font-size:16px;color:#1e3a5f;margin-bottom:6px}
.wait-card span{font-size:12px;color:#64748b}

@media print{
  .toolbar,.summary{display:none !important}
  .grid-wrap{overflow:visible !important}
  body{height:auto;overflow:visible}
}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wait-mask" id="waitMask">
  <div class="wait-card">
    <strong>Please wait</strong>
    <span>Loading report data...</span>
  </div>
</div>

<div class="toolbar">
  <!-- Row 1: Title + Dates + Counter + Incharge -->
  <div class="tb-row">
    <h1>{{ $title }}</h1>
    <div class="sep"></div>
    <div class="f-group">
      <span class="tb-lbl">From</span>
      <input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:130px">
    </div>
    <div class="f-group">
      <span class="tb-lbl">To</span>
      <input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:130px">
    </div>
    <div class="sep"></div>
    <div class="f-group">
      <span class="tb-lbl">Counter</span>
      <select id="counter" class="tb-select" style="width:120px"><option value="">-- All --</option></select>
      <label class="tb-check"><input type="checkbox" id="cbxCounter" checked>On</label>
    </div>
    <div class="f-group">
      <span class="tb-lbl">Incharge</span>
      <select id="ic" class="tb-select" style="width:120px"><option value="">-- All --</option></select>
      <label class="tb-check"><input type="checkbox" id="cbxIC" checked>On</label>
    </div>
  </div>

  <!-- Row 2: SMan + BType + Checkboxes -->
  <div class="tb-row">
    <div class="f-group">
      <span class="tb-lbl">SMan</span>
      <select id="smcode" class="tb-select" style="width:120px"><option value="">-- All --</option></select>
    </div>
    <div class="f-group">
      <span class="tb-lbl">Sale Type</span>
      <select id="saleStatus" class="tb-select" style="width:140px">
        <option value="">-- All --</option>
        <option value="CASH">Cash Sale</option>
        <option value="2">Partial Credit</option>
        <option value="1">Credit Sale</option>
      </select>
    </div>
    <div class="f-group">
      <span class="tb-lbl">BType</span>
      <select id="billtype" class="tb-select" style="width:120px"><option value="">-- All --</option></select>
    </div>
    <div class="sep"></div>
    <label class="tb-check"><input type="checkbox" id="cbRegister"> Register</label>
    <label class="tb-check"><input type="checkbox" id="cbTax" {{ in_array($viewMode, ['tax','other'], true) ? 'checked' : '' }}> Tax Report</label>
    <label class="tb-check"><input type="checkbox" id="cbCounterwise"> Counter Wise</label>
    <div class="sep"></div>
    <button class="btn btn-show" id="btnShow">Show</button>
    <button class="btn btn-out" id="btnSort">Sort</button>
    <button type="button" class="btn btn-out" id="btnSaveAs" oncontextmenu="toggleAdmin(event)">Save As</button>
    <button type="button" class="btn btn-out" id="btnExcel">To Excel</button>
    <button type="button" class="btn btn-out" id="btnPdf">To PDF</button>
    @if($issr === 'S')
    <button type="button" class="btn btn-out" id="btnBulkEinv">Bulk EInv JSON</button>
    @endif
    <button class="btn btn-out" id="btnPrint">Print</button>
    <button class="btn btn-out" id="btnClose">Exit</button>
    <div class="admin-btns" id="adminBtns">
      <div class="sep"></div>
      <button class="btn btn-warn" id="btnSelAll">Sel All</button>
      @if($issr !== 'R')
      <button class="btn btn-out" id="btnToBill">To Bill</button>
      <button class="btn btn-out" id="btnToEst">To Est</button>
      @endif
      <button class="btn btn-red" id="btnDelete">Delete</button>
      <button class="btn btn-out" id="btnRearrange">Rearrange</button>
      <button class="btn btn-out" id="btnReprint">Reprint</button>
    </div>
  </div>
</div>

<div class="grid-wrap">
  <table id="tbl">
    <thead id="thead"></thead>
    <tbody id="tbody"><tr><td colspan="20" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
  </table>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const S = {
  module: @json($moduleId),
  issr: @json($issr),
  view: @json($viewMode),
  rows: [], totals: {}, groupTotals: [],
  sortCol: '', sortDir: 1,
  selectedSlnos: new Set()
};
const WAIT = document.getElementById('waitMask');

/* ── Helpers ──────────────────────────────────────────────────── */
function nf(n, d=2){ return Number(n||0).toFixed(d); }
function esc(v){ return String(v??'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg, ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

function statusText(v, isReturn=false){
  if(!isReturn && typeof v === 'string' && v.trim() !== ''){
    return v;
  }
  const i=Number(v||0);
  if(isReturn) return i===3?'Cash SR':(i===2?'Partial Credit SR':'Credit SR');
  return i===3?'Cash':(i===2?'Partial Credit':'Credit');
}

function statusFull(v, isReturn=false){
  if(!isReturn && typeof v === 'string' && v.trim() !== ''){
    return v;
  }
  const i=Number(v||0);
  if(isReturn) return i===3?'Cash Sales Return':(i===2?'Partial Credit Sales Return':'Credit Sales Return');
  return i===3?'Cash Sales':(i===2?'Partial Credit Sales':'Credit Sales');
}

/* ── Determine current view mode ─────────────────────────────── */
function getViewMode(){
  if(document.getElementById('cbRegister').checked) return 'register';
  if(document.getElementById('cbCounterwise').checked) return 'counterwise';
  if(document.getElementById('cbTax').checked) return 'tax';
  return 'book';
}

/* ── Column definitions ──────────────────────────────────────── */
function headers(){
  const vm = getViewMode();

  if(vm === 'counterwise'){
    return [
      ['Counter','counter'],['Bills','bill_count',1,0],['Bill Amt','total_billamt',1],
      ['Discount','total_discount',1],['Tax','total_tax',1],
      ['Net Amt','total_netamt',1],['Rcvd Amt','total_ramt',1],['Balance','total_balance',1]
    ];
  }

  if(vm === 'register'){
    return [
      ['Date','tdate'],['Bill No','billno'],['Customer','custname'],
      ['Item','item_name'],['Type','itype'],
      ['Rate','rate',1,0],['Qty','qty',1,0],['Gross Wgt','weight',1,3],
      ['St.Wgt','stonewgt',1,3],['Net Wgt','netwgt',1,3],
      ['VA','va',1],['VA%','vaperc',1],['Amount','amount',1],
      ['SMan','smcode'],['Counter','counter']
    ];
  }

  if(S.issr === 'R'){
    return [
      ['Date','tdate'],['Bill No','billno'],['Customer','custname'],
      ['Bill Amt','billamt',1],['Disc','discount',1],['Tax Amt','staxamt',1],
      ['Net Amt','netamt',1],['Paid Amt','tramt',1],['Balance','balance',1],['Status','status']
    ];
  }

  if(vm === 'tax'){
    return [
      ['Bill No','billno'],['Date','tdate'],['Customer','custname'],['Addr','addr'],['GSTIN','ctin'],
      ['PAN','pan'],['State','statecode'],['Rate','maxrate',1],['HSN','hsncode'],['Item','itemname'],
      ['Gr.Wgt','grosswgt',1,3],['St.Wgt','stonewgt',1,3],['Net Wgt','totwgt',1,3],
      ['St.Amt','stone_total',1],['VA','va',1],['Amount','billamt',1],['SGST','sgst',1],
      ['CGST','cgst',1],['IGST','igst',1],['GST','staxamt',1],['Adv','prevadv',1],
      ['Disc','discount',1],['Total','totamt',1]
    ];
  }

  // Book mode (default)
  return [
    ['Date','tdate'],['Bill No','billno'],['Customer','custname'],
    ['Bill Amt','billamt',1],['Ex.Amt','eamt',1],['SR Amt','sretamt',1],
    ['Advance','advance',1],['Disc','discount',1],['D%','discperc',1],
    ['DV%','dvperc',1],['VA/gm','va_per_gm',1],['HMC','hmc',1],
    ['Tax','ttax',1],['Net Amt','tnetamt',1],['Rcvd','tramt',1],['Balance','balance',1],['St','status']
  ];
}

/* ── Populate dropdowns from API ─────────────────────────────── */
async function loadLookups(){
  try{
    const r = await fetch(`${SITE}/api/sales-book-report/lookups`);
    const d = await r.json();
    if(!d.ok) return;
    fillSelect('counter', d.counters);
    fillSelect('ic', d.incharges);
    fillSelect('smcode', d.salesmen);
    fillSelect('billtype', d.billtypes);
  }catch(e){}
}

function fillSelect(id, items){
  const sel = document.getElementById(id);
  (items||[]).forEach(it=>{
    const o = document.createElement('option');
    o.value = it.code;
    o.textContent = it.code + (it.name ? ' - '+it.name : '');
    sel.appendChild(o);
  });
}

/* ── Load data ───────────────────────────────────────────────── */
async function loadData(){
  if (WAIT) WAIT.classList.add('show');
  const vm = getViewMode();
  const counterOn = document.getElementById('cbxCounter').checked;
  const icOn = document.getElementById('cbxIC').checked;

  const qs = new URLSearchParams({
    module: S.module, issr: S.issr, view: vm,
    date1: document.getElementById('date1').value,
    date2: document.getElementById('date2').value,
    rlevel: '{{ $rlevel }}',
    counter: counterOn ? document.getElementById('counter').value : '',
    ic: icOn ? document.getElementById('ic').value : '',
    smcode: document.getElementById('smcode').value,
    billtype: document.getElementById('billtype').value,
    sale_status: document.getElementById('saleStatus').value
  });

  try{
    const r = await fetch(`${SITE}/api/sales-book-report/data?${qs}`);
    const d = await r.json();
    if(!d.success){ toast(d.message||'Error',false); return; }
    S.rows = d.rows||[];
    S.totals = d.totals||{};
    S.groupTotals = d.groupTotals||[];
    S.selectedSlnos.clear();
    render();
    toast(`Loaded ${S.rows.length} rows`);
  }catch(e){
    toast('Network error',false);
  } finally {
    if (WAIT) WAIT.classList.remove('show');
  }
}

/* ── Render grid ─────────────────────────────────────────────── */
function render(){
  const hs = headers();
  const vm = getViewMode();
  const thead = document.getElementById('thead');
  const tbody = document.getElementById('tbody');

  // Header with sort indicators
  thead.innerHTML = '<tr>' + hs.map((h,i)=>{
    let arrow = '';
    if(S.sortCol === h[1]) arrow = S.sortDir===1 ? ' &#9650;' : ' &#9660;';
    return `<th class="${h[2]?'num':''}" data-idx="${i}" data-key="${h[1]}">${esc(h[0])}${arrow}</th>`;
  }).join('') + '</tr>';

  // Body with group headers for book/return modes
  const showGroups = (vm==='book' && S.issr!=='R') || S.issr==='R';
  let lastStatus = null;
  let html = '';

  if(!S.rows.length){
    html = `<tr><td colspan="${hs.length}" style="text-align:center;padding:40px;color:#94a3b8">No records found</td></tr>`;
  } else {
    S.rows.forEach((row,idx)=>{
      // Group header
      const rowStatus = row.sale_type_code ?? row.status;
      const rowStatusLabel = row.sale_type_label ?? row.status;
      if(showGroups && rowStatus !== lastStatus){
        lastStatus = rowStatus;
        const statusRows = S.rows.filter(r=>(r.sale_type_code ?? r.status)===rowStatus);
        const grpAmt = statusRows.reduce((s,r)=>s+(parseFloat(r.tnetamt||r.netamt||r.total_netamt||0)),0);
        html += `<tr class="group-hdr"><td colspan="${hs.length}">${statusFull(row.sale_type_label ?? row.status, S.issr==='R')} (${statusRows.length} bills) &mdash; ${nf(grpAmt)}</td></tr>`;
      }

      const slno = row.slno || row.m_slno || '';
      const isSel = S.selectedSlnos.has(String(slno));
      html += `<tr data-slno="${slno}" data-idx="${idx}" data-billno="${esc(row.billno||'')}" class="${isSel?'sel':''}" title="${S.issr==='S' ? 'Double-click to open bill' : ''}">`;
      hs.forEach(h=>{
        let val = row[h[1]];
        if(h[1]==='status') val = statusText(row.sale_type_label ?? val, S.issr==='R');
        else if(h[2]) val = nf(val, h[3]??2);
        html += `<td class="${h[2]?'num':''}">${esc(val)}</td>`;
      });
      html += '</tr>';
    });
  }
  tbody.innerHTML = html;

  // Summary bar
  renderSummary();
}

function renderSummary(){
  const t = S.totals;
  const g = S.groupTotals;
  const vm = getViewMode();
  const parts = [];

  parts.push(`<span>Rows: <b>${S.rows.length}</b></span>`);

  if(vm === 'counterwise'){
    parts.push(`<span>Bills: <b>${nf(t.bill_count,0)}</b></span>`);
    parts.push(`<span>Bill Amt: <b>${nf(t.total_billamt)}</b></span>`);
    parts.push(`<span>Net Amt: <b>${nf(t.total_netamt)}</b></span>`);
    parts.push(`<span>Balance: <b>${nf(t.total_balance)}</b></span>`);
  } else if(vm === 'register'){
    parts.push(`<span>Qty: <b>${nf(t.qty,0)}</b></span>`);
    parts.push(`<span>Wgt: <b>${nf(t.weight,3)}</b></span>`);
    parts.push(`<span>Net Wgt: <b>${nf(t.netwgt,3)}</b></span>`);
    parts.push(`<span>VA: <b>${nf(t.va)}</b></span>`);
    parts.push(`<span>Amount: <b>${nf(t.amount)}</b></span>`);
  } else if(S.issr === 'R'){
    parts.push(`<span>Bill Amt: <b>${nf(t.billamt)}</b></span>`);
    parts.push(`<span>Net Amt: <b>${nf(t.netamt)}</b></span>`);
    parts.push(`<span>Paid: <b>${nf(t.tramt)}</b></span>`);
    parts.push(`<span>Balance: <b>${nf(t.balance)}</b></span>`);
  } else if(vm === 'tax'){
    parts.push(`<span>Bill Amt: <b>${nf(t.billamt)}</b></span>`);
    parts.push(`<span>SGST: <b>${nf(t.sgst)}</b></span>`);
    parts.push(`<span>CGST: <b>${nf(t.cgst)}</b></span>`);
    parts.push(`<span>IGST: <b>${nf(t.igst)}</b></span>`);
    parts.push(`<span>Total: <b>${nf(t.totamt)}</b></span>`);
    parts.push(`<span>Net Wgt: <b>${nf(t.totwgt,3)}</b></span>`);
  } else {
    parts.push(`<span>Bill Amt: <b>${nf(t.billamt)}</b></span>`);
    parts.push(`<span>Ex.Amt: <b>${nf(t.eamt)}</b></span>`);
    parts.push(`<span>Disc: <b>${nf(t.discount)}</b></span>`);
    parts.push(`<span>Tax: <b>${nf(t.ttax)}</b></span>`);
    parts.push(`<span>Net: <b>${nf(t.tnetamt)}</b></span>`);
    parts.push(`<span>Rcvd: <b>${nf(t.tramt)}</b></span>`);
    parts.push(`<span>Bal: <b>${nf(t.balance)}</b></span>`);
  }

  if(g.length && vm!=='counterwise'){
    parts.push(`<span>| ${g.map(x=>`${statusFull(x.status,S.issr==='R')}(${x.rows})`).join(', ')}</span>`);
  }

  document.getElementById('summary').innerHTML = parts.join('');
}

/* ── Sort ─────────────────────────────────────────────────────── */
function doSort(key){
  if(S.sortCol === key){ S.sortDir *= -1; }
  else { S.sortCol = key; S.sortDir = 1; }
  S.rows.sort((a,b)=>{
    let va = a[key] ?? '', vb = b[key] ?? '';
    if(typeof va === 'number' || !isNaN(parseFloat(va))){
      return (parseFloat(va)-parseFloat(vb)) * S.sortDir;
    }
    return String(va).localeCompare(String(vb)) * S.sortDir;
  });
  render();
}

/* ── Save As (CSV/XLS/PDF) via shared utility ────────────────── */

/* ── Admin toggle (right-click Save As) ──────────────────────── */
function toggleAdmin(e){
  e.preventDefault();
  const el = document.getElementById('adminBtns');
  el.classList.toggle('show');
}

/* ── Row selection ───────────────────────────────────────────── */
document.getElementById('tbody').addEventListener('click', e=>{
  const tr = e.target.closest('tr[data-slno]');
  if(!tr) return;
  const slno = tr.dataset.slno;
  if(S.selectedSlnos.has(slno)){
    S.selectedSlnos.delete(slno);
    tr.classList.remove('sel');
  } else {
    S.selectedSlnos.add(slno);
    tr.classList.add('sel');
  }
});

document.getElementById('tbody').addEventListener('dblclick', e=>{
  const tr = e.target.closest('tr[data-billno]');
  if(!tr || S.issr !== 'S') return;
  const billNo = String(tr.dataset.billno || '').trim();
  if(!billNo) return;
  window.location = `${SITE}/sales-bill/edit?bill_no=${encodeURIComponent(billNo)}`;
});

/* ── Checkbox mutual exclusion (matching PB) ─────────────────── */
document.getElementById('cbRegister').addEventListener('change', e=>{
  if(e.target.checked){
    document.getElementById('cbTax').checked = false;
    document.getElementById('cbCounterwise').checked = false;
  }
});
document.getElementById('cbTax').addEventListener('change', e=>{
  if(e.target.checked){
    document.getElementById('cbRegister').checked = false;
    document.getElementById('cbCounterwise').checked = false;
    document.getElementById('cbRegister').disabled = true;
  } else {
    document.getElementById('cbRegister').disabled = false;
  }
});
document.getElementById('cbCounterwise').addEventListener('change', e=>{
  if(e.target.checked){
    document.getElementById('cbRegister').checked = false;
    document.getElementById('cbTax').checked = false;
    document.getElementById('cbRegister').disabled = true;
  } else {
    document.getElementById('cbRegister').disabled = false;
  }
});

/* ── Sort on header click ────────────────────────────────────── */
document.getElementById('thead').addEventListener('click', e=>{
  const th = e.target.closest('th[data-key]');
  if(th) doSort(th.dataset.key);
});

/* ── Button bindings ─────────────────────────────────────────── */
document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnSort').onclick = ()=>{
  // Cycle through common sort fields
  const vm = getViewMode();
  const keys = vm==='book' ? ['billno','tdate','custname','billamt','balance'] :
               vm==='register' ? ['tdate','billno','item_name','weight','amount'] :
               vm==='counterwise' ? ['counter','total_billamt','total_netamt'] :
               ['billno','tdate','custname','billamt'];
  const cur = keys.indexOf(S.sortCol);
  doSort(keys[(cur+1)%keys.length]);
};
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnClose').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');
document.getElementById('btnBulkEinv')?.addEventListener('click', async ()=>{
  const slnos = (S.selectedSlnos.size ? [...S.selectedSlnos] : S.rows.map(r => String(r.slno || r.m_slno || '')))
    .map(v => parseInt(v, 10))
    .filter(v => Number.isFinite(v) && v > 0);
  const uniqueSlnos = [...new Set(slnos)];
  if(!uniqueSlnos.length){
    toast('No bill rows available for e-invoice JSON', false);
    return;
  }
  if (WAIT) WAIT.classList.add('show');
  try{
    const res = await fetch(`${SITE}/api/sales-book-report/einvoice-json-bulk`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ slnos: uniqueSlnos })
    });
    const data = await res.json();
    if(!data.ok){
      toast(data.message || 'Bulk e-invoice generation failed', false);
      return;
    }
    toast(data.message || 'Bulk e-invoice JSON generated');
    if(data.download_url){
      window.open(data.download_url, '_blank');
    }
  }catch(e){
    toast('Bulk e-invoice generation failed', false);
  }finally{
    if (WAIT) WAIT.classList.remove('show');
  }
});

// Salesman change auto-retrieves (mirrors PB dw_smcode itemchanged)
document.getElementById('smcode').addEventListener('change', ()=>{
  if(S.rows.length) loadData();
});
document.getElementById('saleStatus').addEventListener('change', ()=>{
  if(S.rows.length) loadData();
});

// Admin: Select All
document.getElementById('btnSelAll')?.addEventListener('click', ()=>{
  S.rows.forEach(r=>{
    const slno = String(r.slno||r.m_slno||'');
    if(slno) S.selectedSlnos.add(slno);
  });
  document.querySelectorAll('#tbody tr[data-slno]').forEach(tr=>tr.classList.add('sel'));
  toast(`Selected ${S.selectedSlnos.size} rows`);
});

document.getElementById('btnRearrange')?.addEventListener('click', async ()=>{
  if(S.issr !== 'S' && S.issr !== 'R'){
    toast('Rearrange is only available for sales reports.', false);
    return;
  }
  if(!S.rows.length){
    toast('Load report data first.', false);
    return;
  }
  if(getViewMode() !== 'tax'){
    toast('Use this from GST / Tax Report view.', false);
    return;
  }
  if(!window.confirm('You are going to rearrange the bill numbers.\nContinue?')){
    return;
  }
  const includeEstimates = window.confirm('Do you want to rearrange estimates also?');
  const selected = [...S.selectedSlnos]
    .map(v => parseInt(v, 10))
    .filter(v => Number.isFinite(v) && v > 0);

  if (WAIT) WAIT.classList.add('show');
  try{
    const res = await fetch(`${SITE}/api/sales-book-report/rearrange`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        issr: S.issr,
        date_from: document.getElementById('date1').value,
        include_estimates: includeEstimates,
        selected_slnos: selected
      })
    });
    const data = await res.json();
    if(!data.ok){
      toast(data.message || 'Rearrange failed', false);
      return;
    }
    toast(data.message || 'Rearrange completed');
    await loadData();
  }catch(e){
    toast('Rearrange failed', false);
  }finally{
    if (WAIT) WAIT.classList.remove('show');
  }
});

/* ── Init ─────────────────────────────────────────────────────── */
loadLookups();
loadData();
</script>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>
ReportExport.init('btnSaveAs', headers, ()=>S.rows,
  ()=>`${S.module}-${document.getElementById('date1').value}-to-${document.getElementById('date2').value}`);
document.getElementById('btnSaveAs').onclick = function(e){
  e.preventDefault();
  ReportExport.open(headers, ()=>S.rows,
    ()=>`${S.module}-${document.getElementById('date1').value}-to-${document.getElementById('date2').value}`);
};
document.getElementById('btnExcel').onclick = function(){
  const hs = headers();
  const rows = S.rows.map(r => ({...r}));
  rows.forEach(row => {
    if (row.status != null) row.status = statusText(row.status, S.issr==='R');
  });
  ReportExport.open(() => hs, () => rows,
    () => `${S.module}-${document.getElementById('date1').value}-to-${document.getElementById('date2').value}`);
  setTimeout(function(){
    const xlsCard = document.querySelector('[data-xtype="xls"]');
    if (xlsCard) xlsCard.click();
  }, 0);
};
document.getElementById('btnPdf').onclick = function(){
  const hs = headers();
  const rows = S.rows.map(r => ({...r}));
  rows.forEach(row => {
    if (row.status != null) row.status = statusText(row.status, S.issr==='R');
  });
  ReportExport.open(() => hs, () => rows,
    () => `${S.module}-${document.getElementById('date1').value}-to-${document.getElementById('date2').value}`);
  setTimeout(function(){
    const pdfCard = document.querySelector('[data-xtype="pdf"]');
    if (pdfCard) pdfCard.click();
  }, 0);
};
</script>
</body>
</html>
