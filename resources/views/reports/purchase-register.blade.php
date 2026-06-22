<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;color:#1e293b;font-size:20px;height:100vh;overflow:hidden;display:flex;flex-direction:column}

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:6px 14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex-shrink:0}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap;margin-right:4px}
.tb-lbl{color:rgba(255,255,255,.8);font-size:17px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.tb-input,.tb-select{height:28px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 6px;font-size:17px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus,.tb-select:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.tb-select option{background:#1e3a5f;color:#fff}
.tb-check{display:flex;align-items:center;gap:3px;color:rgba(255,255,255,.85);font-size:17px;font-weight:600;cursor:pointer;white-space:nowrap}
.tb-check input{width:13px;height:13px;accent-color:#60a5fa;cursor:pointer}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:17px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}

.toolbar-row2{background:linear-gradient(135deg,#1a3350,#253f6b);padding:4px 14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex-shrink:0;border-top:1px solid rgba(255,255,255,.08)}
.toolbar-row3{background:linear-gradient(135deg,#172d47,#213a5e);padding:4px 14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex-shrink:0;border-top:1px solid rgba(255,255,255,.08)}

.grid-wrap{flex:1;overflow:auto}
.report-print-root{flex:1;display:flex;flex-direction:column;min-height:0;overflow:hidden}
table{width:100%;border-collapse:collapse;background:#fff;font-size:18px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:5px 6px;font-size:15px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2;cursor:pointer;user-select:none}
th:hover{background:#e2e8f0}
th.num{text-align:right}
td{padding:4px 6px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
tr.sel td{background:#dbeafe !important}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:18px 20px;display:flex;gap:28px;flex-wrap:wrap;font-size:20px;flex-shrink:0;min-height:64px;align-items:center}
.summary span{color:#64748b}.summary b{color:#1e40af;font-size:23px}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:17px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:17px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

@media print{
  @page{size:A4 landscape;margin:8mm}
  html,body{height:auto!important;overflow:visible!important;background:#fff!important;display:block!important}
  .toolbar,.toolbar-row2,.toolbar-row3,.toast{display:none!important}
  .sub-header{
    display:block!important;
    background:#fff!important;
    border:0!important;
    padding:0 0 6px!important;
    color:#111827!important;
    font-size:10px!important;
    font-weight:700!important;
  }
  .report-print-root{display:block!important;overflow:visible!important;width:100%!important;min-height:auto!important}
  .grid-wrap{display:block!important;overflow:visible!important;width:100%!important;flex:none!important}
  table{width:100%!important;border-collapse:collapse!important;font-size:8.5px!important;table-layout:auto!important}
  thead{display:table-header-group}
  tfoot{display:table-row-group}
  th{
    position:static!important;
    top:auto!important;
    z-index:auto!important;
    background:#e5edf6!important;
    color:#111827!important;
    border:1px solid #94a3b8!important;
    padding:3px 4px!important;
    font-size:8px!important;
    line-height:1.15!important;
    white-space:normal!important;
  }
  td{
    border:1px solid #cbd5e1!important;
    padding:3px 4px!important;
    font-size:8.5px!important;
    line-height:1.2!important;
    white-space:normal!important;
    vertical-align:top!important;
  }
  td.num,th.num{white-space:nowrap!important;text-align:right!important}
  tr{break-inside:avoid;page-break-inside:avoid}
  .summary{
    display:flex!important;
    position:static!important;
    border:1px solid #94a3b8!important;
    border-top:2px solid #1e40af!important;
    margin-top:8px!important;
    padding:6px 8px!important;
    gap:10px 14px!important;
    font-size:10px!important;
    line-height:1.25!important;
    page-break-inside:avoid;
    break-inside:avoid;
  }
  .summary span{color:#111827!important}
  .summary b{color:#000!important;font-size:11px!important}
}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<style>
@media print{
  body.goldapp-print-enabled [data-print-root="purchase-register"]{
    width:297mm!important;
    max-width:297mm!important;
    padding-top:0!important;
    padding-left:0!important;
    padding-right:0!important;
    padding-bottom:0!important;
  }
  body.goldapp-print-enabled [data-print-root="purchase-register"] table{
    table-layout:fixed!important;
  }
  body.goldapp-print-enabled [data-print-root="purchase-register"] th,
  body.goldapp-print-enabled [data-print-root="purchase-register"] td{
    overflow-wrap:anywhere!important;
    word-break:normal!important;
  }
  body.goldapp-print-enabled [data-print-root="purchase-register"] td.num,
  body.goldapp-print-enabled [data-print-root="purchase-register"] th.num{
    overflow-wrap:normal!important;
    word-break:normal!important;
  }
  body.goldapp-print-enabled [data-print-root="purchase-register"] tfoot td{
    background:#eef6ff!important;
    color:#000!important;
    font-weight:900!important;
    border-top:2px solid #1e40af!important;
  }
}
</style>
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>

<div class="toolbar">
  <h1>{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:120px"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:120px"></div>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl">Rep Type</span>
    <select id="reptype" class="tb-select" style="width:130px">
      <option value="billwise">Billwise</option>
      <option value="itemwise">Itemwise</option>
      <option value="smanwise">SManWise</option>
      <option value="smanwise_summary">SManWise Summary</option>
      <option value="touchprof">Touch Profit</option>
    </select>
  </div>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl">P/E</span>
    <select id="pe" class="tb-select" style="width:110px">
      <option value="">All</option>
      <option value="P">Purchase Only</option>
      <option value="E">Exchange Only</option>
    </select>
  </div>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button class="btn btn-out" id="btnSort">Sort</button>
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="toolbar-row2">
  <div class="f-group"><span class="tb-lbl">SMan</span><select id="smcode" class="tb-select" style="width:130px"><option value="">-- All --</option></select></div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Supplier</span><input type="text" id="suppcode" class="tb-input" placeholder="Code" style="width:80px;text-transform:uppercase"></div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Search</span><input type="text" id="searchBox" class="tb-input" placeholder="Doc, supplier, item..." style="width:150px"></div>
  <div class="sep"></div>
  <div class="f-group">
    <label class="tb-check"><input type="checkbox" id="cbIc" checked> IC</label>
    <select id="ic" class="tb-select" style="width:130px"><option value="">-- All --</option></select>
  </div>
  <div class="sep"></div>
  <div class="f-group">
    <label class="tb-check"><input type="checkbox" id="cbCounter" checked> Counter</label>
    <select id="counter" class="tb-select" style="width:130px"><option value="">-- All --</option></select>
  </div>
</div>

<div class="toolbar-row3">
  <div class="f-group"><span class="tb-lbl">Item</span><input type="text" id="itemcode" class="tb-input" placeholder="Code" style="width:80px;text-transform:uppercase"></div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Stk Type</span><input type="text" id="stktype" class="tb-input" placeholder="" style="width:60px;text-transform:uppercase"></div>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl">Type</span>
    <select id="type" class="tb-select" style="width:120px">
      <option value="">All</option>
      <option value="orn_Y">Ornaments Only</option>
      <option value="orn_N">Not Ornaments</option>
      <option value="G">Gold</option>
      <option value="S">Silver</option>
      <option value="O">Others</option>
    </select>
  </div>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl">Type2</span>
    <select id="type2" class="tb-select" style="width:110px">
      <option value="">All</option>
      <option value="D">Diamond</option>
      <option value="P">Platinum</option>
      <option value="G">Gold</option>
      <option value="S">Silver</option>
      <option value="O">Others</option>
      <option value="C">Color Stone</option>
      <option value="W">Watch</option>
    </select>
  </div>
</div>

<div class="report-print-root" data-print-root="purchase-register">
<div class="sub-header" id="subHeader"></div>

<div class="grid-wrap">
  <table>
    <thead id="thead"></thead>
    <tbody id="tbody"><tr><td colspan="14" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
    <tfoot id="tfoot"></tfoot>
  </table>
</div>

<div class="summary" id="summary"></div>
</div>
<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const RLEVEL = {{ $rlevel }};
let rows = [], totals = {}, sortCol = '', sortDir = 1, curReptype = 'billwise';

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}
function numericKeys(hs){ return hs.filter(h=>h[2]).map(h=>h[1]); }
function displayedTotals(viewRows, hs){
  const out = {count:viewRows.length, billcount:0};
  numericKeys(hs).forEach(k=>out[k]=0);
  const bills = new Set();
  viewRows.forEach(row=>{
    const doc = String(row.docno || '').trim();
    if(doc) bills.add(doc);
    numericKeys(hs).forEach(k=>out[k] += Number(row[k] || 0));
  });
  out.billcount = bills.size || viewRows.length;
  return out;
}
function grandTotalRow(hs, viewRows){
  if(!viewRows.length) return '';
  const total = displayedTotals(viewRows, hs);
  const labelKey = hs.some(h=>h[1] === 'docno') ? 'docno' : hs[0][1];
  return '<tr>' + hs.map(h=>{
    let val = '';
    if(h[1] === labelKey) val = 'Grand Total';
    else if(h[2] && total[h[1]] != null) val = nf(total[h[1]], h[3]!=null?h[3]:2);
    return '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
  }).join('') + '</tr>';
}
function dateTotalRow(hs, label, total){
  return '<tr style="background:#fff7ed;font-weight:700;color:#9a3412">' + hs.map(h=>{
    let val = '';
    if(h[1] === 'docno') val = label;
    else if(h[2] && total[h[1]] != null) val = nf(total[h[1]], h[3]!=null?h[3]:2);
    return '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
  }).join('') + '</tr>';
}
function rowsWithDateTotals(viewRows, hs){
  const keys = numericKeys(hs);
  let lastDate = null, total = {}, html = '';
  const flush = () => {
    if(lastDate !== null) html += dateTotalRow(hs, 'Date Total: '+lastDate, total);
    total = {};
    keys.forEach(k=>total[k]=0);
  };
  viewRows.forEach((row,idx)=>{
    if(row.tdate !== lastDate){
      flush();
      lastDate = row.tdate;
    }
    keys.forEach(k=>total[k] += Number(row[k] || 0));
    const navUrl = row.navigate_url || '';
    html += '<tr data-idx="'+idx+'"'+(navUrl ? ' data-nav-url="'+esc(navUrl)+'" title="Double-click to open bill"' : '')+'>' + hs.map(h=>{
      let val = row[h[1]];
      if(h[1] === 'smcode'){
        const smCode = row.smcode || '';
        const smName = row.smname || smCode;
        val = smCode && smName !== smCode ? (smName + ' (' + smCode + ')') : smName;
      }
      if(h[2]) val = nf(val, h[3]!=null?h[3]:2);
      return '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
    }).join('') + '</tr>';
  });
  flush();
  return html;
}

function billwiseHeaders(){
  return [
    ['Date','tdate'],['Doc No','docno'],['Bill No','billno'],['GST No','gstno'],['Item','displayname'],
    ['Qty','qty',1,0],['Gross Wgt','weight',1,3],['St.Wgt','stwgt',1,3],
    ['Less Wgt','lesswgt',1,3],['Net Wgt','netwgt',1,3],['Rate','rate',1],
    ['Amount','amount',1],['Ex.Amt','eamt',1],['Add Amt','addamt',1],
    ['SGST','sgst',1],['CGST','cgst',1],['Tax','taxamt',1],
    ['Paid Amt','pamt',1],['SM','smcode']
  ];
}

function itemwiseHeaders(){
  return [
    ['Item','displayname'],['Date','tdate'],['Doc No','docno'],['Bill No','billno'],['GST No','gstno'],
    ['Qty','qty',1,0],['Gross Wgt','weight',1,3],['St.Wgt','stwgt',1,3],
    ['Less Wgt','lesswgt',1,3],['Net Wgt','netwgt',1,3],['Rate','rate',1],
    ['Amount','amount',1],['SM','smcode']
  ];
}

function smanwiseHeaders(){
  return [
    ['SM','smcode'],['Item','displayname'],['Date','tdate'],['Doc No','docno'],['Bill No','billno'],['GST No','gstno'],
    ['Qty','qty',1,0],['Gross Wgt','weight',1,3],['St.Wgt','stwgt',1,3],
    ['Less Wgt','lesswgt',1,3],['Net Wgt','netwgt',1,3],['Rate','rate',1],
    ['Amount','amount',1]
  ];
}

function touchprofHeaders(){
  return [
    ['Date','tdate'],['Doc No','docno'],['Bill No','billno'],['SM','smcode'],['Item','displayname'],
    ['Qty','qty',1,0],['Gross Wgt','weight',1,3],['St.Wgt','stwgt',1,3],
    ['Net Wgt','netwgt',1,3],['Touch','touch',1],['PureWgt','purewgt',1,3],
    ['AvgPurity','avgpurity',1],['Mud','mud',1,3],['TchLess','touchless',1,3],
    ['TouchProf','touchprof',1,3]
  ];
}

function headers(){
  if(curReptype==='itemwise') return itemwiseHeaders();
  if(curReptype==='smanwise'||curReptype==='smanwise_summary') return smanwiseHeaders();
  if(curReptype==='touchprof') return touchprofHeaders();
  return billwiseHeaders();
}

function filteredRows(sourceRows){
  const q = String(document.getElementById('searchBox')?.value || '').trim().toLowerCase();
  if(!q) return sourceRows;
  return sourceRows.filter(row => Object.values(row).some(v => String(v ?? '').toLowerCase().includes(q)));
}

/* ── Lookups ──────────────────────────────────────── */
async function loadLookups(){
  try {
    const r = await fetch(SITE+'/api/purchase-register/lookups');
    const d = await r.json();
    if(!d.ok) return;
    fillSelect('smcode', d.salesmen||[]);
    fillSelect('ic', d.incharges||[]);
    fillSelect('counter', d.counters||[]);
  } catch(e){}
}
function fillSelect(id, items){
  const sel = document.getElementById(id);
  const first = sel.options[0].outerHTML;
  sel.innerHTML = first + items.map(i=>'<option value="'+esc(i.code)+'">'+esc(i.code)+' - '+esc(i.name)+'</option>').join('');
}
loadLookups();

/* ── Load data ────────────────────────────────────── */
async function loadData(){
  curReptype = document.getElementById('reptype').value;

  const qs = new URLSearchParams({
    date1: document.getElementById('date1').value,
    date2: document.getElementById('date2').value,
    rlevel: RLEVEL,
    reptype: curReptype,
    smcode: document.getElementById('smcode').value,
    suppcode: document.getElementById('suppcode').value.trim().toUpperCase(),
    itemcode: document.getElementById('itemcode').value.trim().toUpperCase(),
    ic: document.getElementById('cbIc').checked ? document.getElementById('ic').value : '',
    icon: document.getElementById('cbIc').checked ? 1 : 0,
    counter: document.getElementById('cbCounter').checked ? document.getElementById('counter').value : '',
    counteron: document.getElementById('cbCounter').checked ? 1 : 0,
    stktype: document.getElementById('stktype').value.trim().toUpperCase(),
    pe: document.getElementById('pe').value,
    type: document.getElementById('type').value,
    type2: document.getElementById('type2').value,
  });

  const rtLabel = document.getElementById('reptype').options[document.getElementById('reptype').selectedIndex].text;
  document.getElementById('subHeader').textContent =
    rtLabel + ' | From: '+document.getElementById('date1').value+' To: '+document.getElementById('date2').value;

  try {
    const r = await fetch(SITE+'/api/purchase-register/data?'+qs);
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Error',false); return; }
    rows = d.rows||[]; totals = d.totals||{};
    sortCol = ''; sortDir = 1;
    render();
    toast('Loaded '+rows.length+' rows');
  } catch(e){ toast('Network error',false); }
}

function render(){
  const hs = headers();
  const viewRows = filteredRows(rows);
  const thead = document.getElementById('thead');
  const tbody = document.getElementById('tbody');
  const tfoot = document.getElementById('tfoot');

  thead.innerHTML = '<tr>' + hs.map(h=>{
    let arrow = sortCol===h[1] ? (sortDir===1?' &#9650;':' &#9660;') : '';
    return '<th class="'+(h[2]?'num':'')+'" data-key="'+h[1]+'">'+esc(h[0])+arrow+'</th>';
  }).join('') + '</tr>';

  if(!viewRows.length){
    tbody.innerHTML = '<tr><td colspan="'+hs.length+'" style="text-align:center;padding:40px;color:#94a3b8">No records found</td></tr>';
    tfoot.innerHTML = '';
  } else if(curReptype === 'billwise') {
    tbody.innerHTML = rowsWithDateTotals(viewRows, hs);
    tfoot.innerHTML = grandTotalRow(hs, viewRows);
  } else {
    let lastGroup = null;
    const smanGroupTotals = {};
    if(curReptype==='smanwise' || curReptype==='smanwise_summary'){
      viewRows.forEach(row => {
        const key = row.smcode || '';
        if(!smanGroupTotals[key]){
          smanGroupTotals[key] = {
            qty:0, weight:0, stwgt:0, lesswgt:0, netwgt:0, amount:0
          };
        }
        smanGroupTotals[key].qty += Number(row.qty || 0);
        smanGroupTotals[key].weight += Number(row.weight || 0);
        smanGroupTotals[key].stwgt += Number(row.stwgt || 0);
        smanGroupTotals[key].lesswgt += Number(row.lesswgt || 0);
        smanGroupTotals[key].netwgt += Number(row.netwgt || 0);
        smanGroupTotals[key].amount += Number(row.amount || 0);
      });
    }
    tbody.innerHTML = viewRows.map((row,idx)=>{
      let html = '';
      if(curReptype==='smanwise' || curReptype==='smanwise_summary'){
        const gCode = row.smcode || '';
        const gName = row.smname || gCode || 'No Salesman';
        const gLabel = gCode && gName !== gCode ? (gName + ' (' + gCode + ')') : gName;
        if(gCode !== lastGroup){
          if(lastGroup !== null && smanGroupTotals[lastGroup]){
            const gt = smanGroupTotals[lastGroup];
            html += '<tr style="background:#f8fafc;font-weight:700;color:#0f172a">'
              + '<td colspan="4">Total</td>'
              + '<td class="num">'+nf(gt.qty,0)+'</td>'
              + '<td class="num">'+nf(gt.weight,3)+'</td>'
              + '<td class="num">'+nf(gt.stwgt,3)+'</td>'
              + '<td class="num">'+nf(gt.lesswgt,3)+'</td>'
              + '<td class="num">'+nf(gt.netwgt,3)+'</td>'
              + '<td></td>'
              + '<td class="num">'+nf(gt.amount)+'</td>'
              + '</tr>';
          }
          html += '<tr style="background:#eff6ff;font-weight:700;color:#1e40af"><td colspan="'+hs.length+'">SM: '+esc(gLabel)+'</td></tr>';
          lastGroup = gCode;
        }
      }
      const navUrl = row.navigate_url || '';
      html += '<tr data-idx="'+idx+'"'+(navUrl ? ' data-nav-url="'+esc(navUrl)+'" title="Double-click to open bill"' : '')+'>' + hs.map(h=>{
        let val = row[h[1]];
        if(h[1] === 'smcode'){
          const smCode = row.smcode || '';
          const smName = row.smname || smCode;
          val = smCode && smName !== smCode ? (smName + ' (' + smCode + ')') : smName;
        }
        if(h[2]) val = nf(val, h[3]!=null?h[3]:2);
        return '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
      }).join('') + '</tr>';
      if((curReptype==='smanwise' || curReptype==='smanwise_summary') && idx === viewRows.length - 1 && smanGroupTotals[lastGroup]){
        const gt = smanGroupTotals[lastGroup];
        html += '<tr style="background:#f8fafc;font-weight:700;color:#0f172a">'
          + '<td colspan="4">Total</td>'
          + '<td class="num">'+nf(gt.qty,0)+'</td>'
          + '<td class="num">'+nf(gt.weight,3)+'</td>'
          + '<td class="num">'+nf(gt.stwgt,3)+'</td>'
          + '<td class="num">'+nf(gt.lesswgt,3)+'</td>'
          + '<td class="num">'+nf(gt.netwgt,3)+'</td>'
          + '<td></td>'
          + '<td class="num">'+nf(gt.amount)+'</td>'
          + '</tr>';
      }
      return html;
    }).join('');
    tfoot.innerHTML = grandTotalRow(hs, viewRows);
  }

  const t = totals;
  if(curReptype==='touchprof'){
    document.getElementById('summary').innerHTML = [
      '<span>Bills: <b>'+nf(t.billcount,0)+'</b></span>',
      '<span>Rows: <b>'+nf(t.count,0)+'</b></span>',
      '<span>Gross Wgt: <b>'+nf(t.weight,3)+'</b></span>',
      '<span>St.Wgt: <b>'+nf(t.stwgt,3)+'</b></span>',
      '<span>Less Wgt: <b>'+nf(t.lesswgt,3)+'</b></span>',
      '<span>Net Wgt: <b>'+nf(t.netwgt,3)+'</b></span>',
      '<span>PureWgt: <b>'+nf(t.purewgt,3)+'</b></span>',
      '<span>TchLess: <b>'+nf(t.touchless,3)+'</b></span>',
      '<span>TouchProf: <b>'+nf(t.touchprof,3)+'</b></span>',
    ].join('');
  } else {
    document.getElementById('summary').innerHTML = [
      '<span>Bills: <b>'+nf(t.billcount,0)+'</b></span>',
      '<span>Rows: <b>'+nf(t.count,0)+'</b></span>',
      '<span>Gross Wgt: <b>'+nf(t.weight,3)+'</b></span>',
      '<span>St.Wgt: <b>'+nf(t.stwgt,3)+'</b></span>',
      '<span>Less: <b>'+nf(t.lesswgt,3)+'</b></span>',
      '<span>Net Wgt: <b>'+nf(t.netwgt,3)+'</b></span>',
      '<span>Amount: <b>'+nf(t.amount)+'</b></span>',
      '<span>SGST: <b>'+nf(t.sgst)+'</b></span>',
      '<span>CGST: <b>'+nf(t.cgst)+'</b></span>',
      '<span>Tax: <b>'+nf(t.taxamt)+'</b></span>',
      '<span>G.Wgt: <b>'+nf(t.goldwgt,3)+'</b></span>',
      '<span>S.Wgt: <b>'+nf(t.silverwgt,3)+'</b></span>',
      '<span>O.Wgt: <b>'+nf(t.otherwgt,3)+'</b></span>',
    ].join('');
  }
}

/* ── Sort ─────────────────────────────────────────── */
document.getElementById('thead').addEventListener('click', e=>{
  const th = e.target.closest('th[data-key]'); if(!th) return;
  const key = th.dataset.key;
  if(sortCol===key) sortDir*=-1; else { sortCol=key; sortDir=1; }
  rows.sort((a,b)=>{
    let va=a[key]??'', vb=b[key]??'';
    if(!isNaN(parseFloat(va))) return (parseFloat(va)-parseFloat(vb))*sortDir;
    return String(va).localeCompare(String(vb))*sortDir;
  });
  render();
});

document.getElementById('tbody').addEventListener('click', e=>{
  const tr = e.target.closest('tr[data-idx]'); if(!tr) return;
  document.querySelectorAll('#tbody tr').forEach(r=>r.classList.remove('sel'));
  tr.classList.add('sel');
});

document.getElementById('tbody').addEventListener('dblclick', e=>{
  const tr = e.target.closest('tr[data-nav-url]');
  if(!tr) return;
  const navUrl = String(tr.dataset.navUrl || '').trim();
  if(navUrl) window.location = navUrl;
});

document.getElementById('btnSort').onclick = ()=>{ sortCol=''; sortDir=1; render(); toast('Sort reset'); };
document.getElementById('btnShow').onclick = loadData;
document.getElementById('searchBox').addEventListener('input', render);
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');
</script>
<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>
ReportExport.init('btnSaveAs', headers, ()=>filteredRows(rows),
  ()=>'purchase-register-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
</script>
</body>
</html>
