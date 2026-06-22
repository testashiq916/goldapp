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

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:8px 14px;display:flex;flex-direction:column;gap:6px;flex-shrink:0}
.tb-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
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

.grid-wrap{flex:1;overflow:auto}
table{width:100%;border-collapse:collapse;background:#fff;font-size:18px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:5px 6px;font-size:15px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2;cursor:pointer;user-select:none}
th:hover{background:#e2e8f0}
th.num{text-align:right}
td{padding:4px 6px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
tr.sel td{background:#dbeafe !important}
tr.grp-head td{background:#eff6ff;font-weight:700;color:#1e40af;border-top:2px solid #bfdbfe}
tr.date-head td{background:#dbeafe;font-weight:800;color:#0f3e67;border-top:2px solid #93c5fd}
tr.type-head td{background:#eef2ff;font-weight:800;color:#1e3a8a;border-top:1px solid #c7d2fe}
tr.type-total td{background:#fef3c7;font-weight:800;color:#92400e;border-top:2px solid #f59e0b}
tr.date-total td{background:#dcfce7;font-weight:800;color:#166534;border-top:2px solid #22c55e;border-bottom:2px solid #22c55e}
tr.bill-total td{background:#fff7ed;font-weight:700;color:#9a3412;border-top:1px solid #fdba74;border-bottom:2px solid #fdba74}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:16px;flex-wrap:wrap;font-size:17px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:17px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:17px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

@media print{
  .toolbar,.summary,.sub-header{display:none !important}
  .grid-wrap{overflow:visible !important}
  body{height:auto;overflow:visible}
}
</style>
@if(is_file(public_path('css/report-readable.css')))
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@endif
@include('partials.print-layout-head')
@if(is_file(public_path('js/report-row-navigation.js')))
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
@endif
</head>
<body>

<div class="toolbar">
  {{-- Row 1: dates, rep type, main buttons --}}
  <div class="tb-row">
    <h1>{{ $title }}</h1>
    <div class="sep"></div>
    <div class="f-group">
      <span class="tb-lbl">From</span>
      <input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:120px">
    </div>
    <div class="f-group">
      <span class="tb-lbl">To</span>
      <input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:120px">
    </div>
    <div class="sep"></div>
    <div class="f-group">
      <span class="tb-lbl">Rep Type</span>
      <select id="reptype" class="tb-select" style="width:140px">
        <option value="billwise">Billwise</option>
        <option value="itemwise">Itemwise Details</option>
        <option value="itemsummary">Itemwise Summary</option>
        <option value="billtypesummary">Bill Type Summary</option>
        <option value="smanwise">SManWise</option>
      </select>
    </div>
    <div class="f-group">
      <span class="tb-lbl">Sort</span>
      <select id="sortmode" class="tb-select" style="width:150px">
        <option value="default">Default</option>
        <option value="discount_desc">Discount High-Low</option>
        <option value="discount_asc">Discount Low-High</option>
      </select>
    </div>
    <div class="sep"></div>
    <button class="btn btn-show" id="btnShow">Show</button>
    <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
    <button class="btn btn-out" id="btnPrint">Print</button>
    <button class="btn btn-out" id="btnExit">Exit</button>
  </div>
  {{-- Row 2: filters --}}
  <div class="tb-row">
    <div class="f-group">
      <span class="tb-lbl">SM</span>
      <select id="smcode" class="tb-select" style="width:120px"><option value="">All</option></select>
    </div>
    <div class="f-group">
      <span class="tb-lbl">Counter</span>
      <select id="counter" class="tb-select" style="width:100px"><option value="">All</option></select>
    </div>
    <label class="tb-check"><input type="checkbox" id="cbStkCounter"> Stk Cntr</label>
    <div class="f-group">
      <span class="tb-lbl">IC</span>
      <select id="ic" class="tb-select" style="width:100px"><option value="">All</option></select>
    </div>
    <div class="sep"></div>
    <div class="f-group">
      <span class="tb-lbl">Item Type</span>
      <select id="type1" class="tb-select" style="width:90px">
        <option value="">All</option>
        <option value="G">Gold</option>
        <option value="S">Silver</option>
        <option value="O">Others</option>
        <option value="P">Platinum</option>
        <option value="D">Diamond</option>
      </select>
    </div>
    <div class="f-group">
      <span class="tb-lbl">Type2</span>
      <select id="type2" class="tb-select" style="width:100px">
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
    <div class="f-group">
      <span class="tb-lbl">Type3</span>
      <select id="type3" class="tb-select" style="width:110px">
        <option value="">All</option>
        <option value="O">Ornaments Only</option>
        <option value="N">Not Ornaments</option>
      </select>
    </div>
    <div class="sep"></div>
    <div class="f-group">
      <span class="tb-lbl">Stk Type</span>
      <select id="stktype" class="tb-select" style="width:90px"><option value="">All</option></select>
    </div>
    <div class="f-group">
      <span class="tb-lbl">Group</span>
      <select id="grpcode" class="tb-select" style="width:100px"><option value="">All</option></select>
    </div>
    <div class="f-group">
      <span class="tb-lbl">SubGrp</span>
      <select id="subgrp" class="tb-select" style="width:100px"><option value="">All</option></select>
    </div>
    <div class="f-group">
      <span class="tb-lbl">Party</span>
      <input type="text" id="custcode" class="tb-input" placeholder="Code" style="width:70px;text-transform:uppercase">
    </div>
    <div class="f-group">
      <span class="tb-lbl">Item</span>
      <input type="text" id="itemcode" class="tb-input" placeholder="Code" style="width:70px;text-transform:uppercase">
    </div>
    <div class="f-group">
      <span class="tb-lbl">Search</span>
      <input type="text" id="searchBox" class="tb-input" placeholder="Bill, party, item..." style="width:150px">
    </div>
    <div class="f-group">
      <span class="tb-lbl">Bill Type</span>
      <select id="billtype" class="tb-select" style="width:135px">
        <option value="">All</option>
        <option value="__B2B__">B2B / B3B All</option>
        <option value="__B2B_TYPE_G">B2B Bill Type Gold</option>
        <option value="__B2B_TYPE_S">B2B Bill Type Silver</option>
        <option value="__B2B_TYPE_D">B2B Bill Type Diamond</option>
        <option value="__B2B_TYPE_O">B2B Bill Type Others</option>
      </select>
    </div>
  </div>
</div>

<div class="sub-header" id="subHeader"></div>

<div class="grid-wrap">
  <table>
    <thead id="thead"></thead>
    <tbody id="tbody"><tr><td colspan="14" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
  </table>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const RLEVEL = {{ $rlevel }};
let rows = [], totals = {}, sortCol = '', sortDir = 1;

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

function getRepType(){ return document.getElementById('reptype').value; }
function getSortMode(){ return document.getElementById('sortmode').value; }

/* ── Headers per report type ──────────────────────────────── */
function headers(){
  const rt = getRepType();
  if(rt === 'itemsummary'){
    return [
      ['Item Code','item_code'],['Item Name','item_name'],['Type','itype'],
      ['Qty','qty',1,0],['Gross Wgt','weight',1,3],['Stone Wgt','stonewgt',1,3],
      ['Net Wgt','netwgt',1,3],['Amount','amount',1],['VA','mcharge',1],['St.Price','stoneprice',1]
    ];
  }
  if(rt === 'billtypesummary'){
    return [
      ['Bill Type','billtype'],['Name','billtype_name'],['Prefix','billtype_prefix'],
      ['Bills','bills',1,0],['Qty','qty',1,0],['Gross Wgt','weight',1,3],
      ['Net Wgt','netwgt',1,3],['Item Amt','amount',1],['Bill Amt','billamt',1],
      ['Ex.Amt','eamt',1],['S.Ret','sretamt',1],['Disc','discount',1],
      ['SGST','sgst',1],['CGST','cgst',1],['Tax','staxamt',1],
      ['Rcvd','ramt',1],['Net Amt','netamt',1],['Balance','balance',1]
    ];
  }
  // billwise, itemwise, smanwise all show detail columns
  return [
    ['Date','tdate'],['Bill No','billno'],['Item','item_name'],
    ['Qty','qty',1,0],['Gross Wgt','weight',1,3],['St.Wgt','stonewgt',1,3],['Net Wgt','netwgt',1,3],
    ['VA','va',1],['Amount','amount',1],
    ['Ex.Amt','eamt',1],['S.Ret','sretamt',1],['Disc','discount',1],
    ['SGST','sgst',1],['CGST','cgst',1],['Tax','staxamt',1],
    ['Rcvd','ramt',1],['SM','smcode'],['Balance','balance',1],['Party','custname']
  ];
}

/* ── Load lookups ─────────────────────────────────────────── */
async function loadLookups(){
  try {
    const r = await fetch(SITE+'/api/sales-register/lookups');
    const d = await r.json();
    if(!d.ok) return;
    fillSelect('smcode', d.salesmen);
    fillSelect('counter', d.counters);
    fillSelect('ic', d.incharges);
    fillSelect('stktype', d.stktypes);
    fillSelect('grpcode', d.groups);
    fillSelect('subgrp', d.subgroups);
    fillSelect('billtype', d.billtypes, true);
  } catch(e){ console.warn('Lookup error',e); }
}
function fillSelect(id, items, keepExisting=false){
  const sel = document.getElementById(id);
  if(!sel) return;
  const val = sel.value;
  const keep = keepExisting ? sel.options.length : 1;
  while(sel.options.length > keep) sel.remove(keep);
  (items||[]).forEach(it=>{
    const extra = it.prefix ? ' ['+it.prefix+']' : '';
    const o = new Option(it.code+' - '+it.name+extra, it.code);
    sel.add(o);
  });
  sel.value = val;
}

/* ── Load data ────────────────────────────────────────────── */
async function loadData(){
  const qs = new URLSearchParams({
    date1: document.getElementById('date1').value,
    date2: document.getElementById('date2').value,
    rlevel: RLEVEL,
    reptype: getRepType(),
    smcode: document.getElementById('smcode').value,
    counter: document.getElementById('counter').value,
    stkcounter: document.getElementById('cbStkCounter').checked ? 1 : 0,
    ic: document.getElementById('ic').value,
    type1: document.getElementById('type1').value,
    type2: document.getElementById('type2').value,
    type3: document.getElementById('type3').value,
    stktype: document.getElementById('stktype').value,
    grpcode: document.getElementById('grpcode').value,
    subgrp: document.getElementById('subgrp').value,
    custcode: document.getElementById('custcode').value.trim().toUpperCase(),
    itemcode: document.getElementById('itemcode').value.trim().toUpperCase(),
    billtype: document.getElementById('billtype').value,
  });

  let sub = 'From: '+document.getElementById('date1').value+' To: '+document.getElementById('date2').value;
  const sm = document.getElementById('smcode').value;
  if(sm) sub += ' | SM: '+sm;
  const ct = document.getElementById('counter').value;
  if(ct) sub += ' | Counter: '+ct;
  document.getElementById('subHeader').textContent = sub;

  try {
    const r = await fetch(SITE+'/api/sales-register/data?'+qs);
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Error',false); return; }
    rows = d.rows||[];
    totals = d.totals||{};
    sortCol = ''; sortDir = 1;
    render();
    toast('Loaded '+rows.length+' rows');
  } catch(e){ toast('Network error',false); }
}

/* ── Render ───────────────────────────────────────────────── */
function render(){
  const hs = headers();
  const thead = document.getElementById('thead');
  const tbody = document.getElementById('tbody');
  const rt = getRepType();
  const viewRows = getRenderedRows();
  const billGroups = rt === 'billwise' ? buildBillGroups(viewRows) : [];

  thead.innerHTML = '<tr>' + hs.map(h=>{
    let arrow = '';
    if(sortCol===h[1]) arrow = sortDir===1?' &#9650;':' &#9660;';
    return '<th class="'+(h[2]?'num':'')+'" data-key="'+h[1]+'">'+esc(h[0])+arrow+'</th>';
  }).join('') + '</tr>';

  if(!viewRows.length){
    tbody.innerHTML = '<tr><td colspan="'+hs.length+'" style="text-align:center;padding:40px;color:#94a3b8">No records found</td></tr>';
  } else if(rt === 'billwise'){
    let html = '';
    let lastDate = null;
    let lastCategory = null;
    let lastCategoryLabel = '';
    const groupByType = document.getElementById('billtype').value === '' && getSortMode() === 'default';
    let typeTotals = null;
    let dateTotals = null;

    function flushTypeTotal(){
      if(groupByType && typeTotals && typeTotals.bills > 0){
        html += buildTotalRow(hs, 'type-total', 'Bill Type Total: '+(lastCategoryLabel || lastCategory || ''), typeTotals);
      }
      typeTotals = createBillwiseTotals();
    }

    function flushDateTotal(){
      if(groupByType && dateTotals && dateTotals.bills > 0){
        html += buildTotalRow(hs, 'date-total', 'Date Total: '+(lastDate || ''), dateTotals);
      }
      dateTotals = createBillwiseTotals();
    }

    billGroups.forEach((group, groupIdx)=>{
      if(groupByType && group.tdate !== lastDate){
        flushTypeTotal();
        flushDateTotal();
        html += '<tr class="date-head"><td colspan="'+hs.length+'">Date: '+esc(group.tdate || '')+'</td></tr>';
        lastDate = group.tdate;
        lastCategory = null;
        lastCategoryLabel = '';
      }
      if(groupByType && group.categoryKey !== lastCategory){
        flushTypeTotal();
        html += '<tr class="type-head"><td colspan="'+hs.length+'">Bill Type: '+esc(group.categoryLabel || 'Other')+'</td></tr>';
        lastCategory = group.categoryKey;
        lastCategoryLabel = group.categoryLabel || 'Other';
      }

      html += '<tr class="grp-head" data-billno="'+esc(group.billno || '')+'" data-orderno="'+esc(group.orderno || '')+'"><td colspan="'+hs.length+'">Bill: '+esc(group.billno || '')
        +' | Date: '+esc(group.tdate || '')
        +(group.billTypeLabel ? ' | Type: '+esc(group.billTypeLabel) : '')
        +' | Party: '+esc(group.custname || '')
        +(group.smLabel ? ' | SM: '+esc(group.smLabel) : '')
        +(group.discount > 0 ? ' | Disc: '+nf(group.discount) : '')
        +'</td></tr>';

      group.items.forEach((row, idx)=>{
        html += '<tr data-idx="'+groupIdx+'-'+idx+'" data-billno="'+esc(row.billno || group.billno || '')+'" data-orderno="'+esc(row.orderno || group.orderno || '')+'">';
        hs.forEach(h=>{
          let val = row[h[1]];
          if(h[1] === 'smcode'){
            const smCode = row.smcode || '';
            const smName = row.smname || smCode;
            val = smCode && smName !== smCode ? (smName + ' (' + smCode + ')') : smName;
          }
          if(h[2]) val = nf(val, h[3]!=null?h[3]:2);
          html += '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
        });
        html += '</tr>';
      });

      html += '<tr class="bill-total" data-billno="'+esc(group.billno || '')+'" data-orderno="'+esc(group.orderno || '')+'">';
      hs.forEach(h=>{
        let val = '';
        if(h[1] === 'billno') val = 'Bill Total';
        else if(h[1] === 'qty') val = nf(group.qty, 0);
        else if(h[1] === 'weight') val = nf(group.weight, 3);
        else if(h[1] === 'stonewgt') val = nf(group.stonewgt, 3);
        else if(h[1] === 'netwgt') val = nf(group.netwgt, 3);
        else if(h[1] === 'va') val = nf(group.va);
        else if(h[1] === 'amount') val = nf(group.amount);
        else if(h[1] === 'eamt') val = nf(group.eamt);
        else if(h[1] === 'sretamt') val = nf(group.sretamt);
        else if(h[1] === 'discount') val = nf(group.discount);
        else if(h[1] === 'sgst') val = nf(group.sgst);
        else if(h[1] === 'cgst') val = nf(group.cgst);
        else if(h[1] === 'staxamt') val = nf(group.staxamt);
        else if(h[1] === 'ramt') val = nf(group.ramt);
        else if(h[1] === 'balance') val = nf(group.balance);
        else if(h[1] === 'custname') val = group.custname || '';
        html += '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
      });
      html += '</tr>';

      if(groupByType){
        addBillwiseTotals(typeTotals, group);
        addBillwiseTotals(dateTotals, group);
      }
    });
    flushTypeTotal();
    flushDateTotal();
    tbody.innerHTML = html;
  } else {
    let html = '';
    let lastGroup = null;

    viewRows.forEach((row,idx)=>{
      if(rt === 'smanwise'){
        const gCode = row.smcode || '';
        const gName = row.smname || gCode || 'No Salesman';
        const gLabel = gCode && gName !== gCode ? (gName + ' (' + gCode + ')') : gName;
        if(gCode !== lastGroup){
          html += '<tr class="grp-head"><td colspan="'+hs.length+'">SM: '+esc(gLabel)+'</td></tr>';
          lastGroup = gCode;
        }
      }

      html += '<tr data-idx="'+idx+'" data-billno="'+esc(row.billno || '')+'" data-orderno="'+esc(row.orderno || '')+'">';
      hs.forEach(h=>{
        let val = row[h[1]];
        if(h[1] === 'smcode'){
          const smCode = row.smcode || '';
          const smName = row.smname || smCode;
          val = smCode && smName !== smCode ? (smName + ' (' + smCode + ')') : smName;
        }
        if(h[2]) val = nf(val, h[3]!=null?h[3]:2);
        html += '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
      });
      html += '</tr>';
    });
    tbody.innerHTML = html;
  }

  const t = totals;
  const parts = ['<span>Rows: <b>'+viewRows.length+'</b></span>'];
  if(rt === 'itemsummary'){
    parts.push('<span>Qty: <b>'+nf(t.qty,0)+'</b></span>');
    parts.push('<span>Gross Wgt: <b>'+nf(t.weight,3)+'</b></span>');
    parts.push('<span>St.Wgt: <b>'+nf(t.stonewgt,3)+'</b></span>');
    parts.push('<span>Net Wgt: <b>'+nf(t.netwgt,3)+'</b></span>');
    parts.push('<span>Amount: <b>'+nf(t.amount)+'</b></span>');
    parts.push('<span>VA: <b>'+nf(t.mcharge)+'</b></span>');
  } else if(rt === 'billtypesummary'){
    parts.push('<span>Bills: <b>'+nf(t.bills,0)+'</b></span>');
    parts.push('<span>Qty: <b>'+nf(t.qty,0)+'</b></span>');
    parts.push('<span>Gross Wgt: <b>'+nf(t.weight,3)+'</b></span>');
    parts.push('<span>Net Wgt: <b>'+nf(t.netwgt,3)+'</b></span>');
    parts.push('<span>Item Amt: <b>'+nf(t.amount)+'</b></span>');
    parts.push('<span>Bill Amt: <b>'+nf(t.billamt)+'</b></span>');
    parts.push('<span>Disc: <b>'+nf(t.discount)+'</b></span>');
    parts.push('<span>SGST: <b>'+nf(t.sgst)+'</b></span>');
    parts.push('<span>CGST: <b>'+nf(t.cgst)+'</b></span>');
    parts.push('<span>Tax: <b>'+nf(t.staxamt)+'</b></span>');
    parts.push('<span>Rcvd: <b>'+nf(t.ramt)+'</b></span>');
    parts.push('<span>Balance: <b>'+nf(t.balance)+'</b></span>');
  } else {
    if(rt === 'billwise') parts.unshift('<span>Bills: <b>'+billGroups.length+'</b></span>');
    parts.push('<span>Qty: <b>'+nf(t.qty,0)+'</b></span>');
    parts.push('<span>Gross Wgt: <b>'+nf(t.weight,3)+'</b></span>');
    parts.push('<span>St.Wgt: <b>'+nf(t.stonewgt,3)+'</b></span>');
    parts.push('<span>Net Wgt: <b>'+nf(t.netwgt,3)+'</b></span>');
    parts.push('<span>Amount: <b>'+nf(t.amount)+'</b></span>');
    parts.push('<span>Ex.Amt: <b>'+nf(t.eamt)+'</b></span>');
    parts.push('<span>S.Ret: <b>'+nf(t.sretamt)+'</b></span>');
    parts.push('<span>Disc: <b>'+nf(t.discount)+'</b></span>');
    parts.push('<span>Rcvd: <b>'+nf(t.ramt)+'</b></span>');
    parts.push('<span>Balance: <b>'+nf(t.balance)+'</b></span>');
  }
  document.getElementById('summary').innerHTML = parts.join('');
}

function createBillwiseTotals(){
  return {
    bills: 0,
    qty: 0,
    weight: 0,
    stonewgt: 0,
    netwgt: 0,
    va: 0,
    amount: 0,
    eamt: 0,
    sretamt: 0,
    discount: 0,
    sgst: 0,
    cgst: 0,
    staxamt: 0,
    ramt: 0,
    balance: 0
  };
}

function addBillwiseTotals(total, group){
  if(!total || !group) return;
  total.bills += 1;
  ['qty','weight','stonewgt','netwgt','va','amount','eamt','sretamt','discount','sgst','cgst','staxamt','ramt','balance'].forEach(key=>{
    total[key] += Number(group[key] || 0);
  });
}

function buildTotalRow(hs, rowClass, label, total){
  let html = '<tr class="'+rowClass+'">';
  hs.forEach(h=>{
    let val = '';
    if(h[1] === 'billno') val = label;
    else if(h[1] === 'qty') val = nf(total.qty, 0);
    else if(h[1] === 'weight') val = nf(total.weight, 3);
    else if(h[1] === 'stonewgt') val = nf(total.stonewgt, 3);
    else if(h[1] === 'netwgt') val = nf(total.netwgt, 3);
    else if(h[1] === 'va') val = nf(total.va);
    else if(h[1] === 'amount') val = nf(total.amount);
    else if(h[1] === 'eamt') val = nf(total.eamt);
    else if(h[1] === 'sretamt') val = nf(total.sretamt);
    else if(h[1] === 'discount') val = nf(total.discount);
    else if(h[1] === 'sgst') val = nf(total.sgst);
    else if(h[1] === 'cgst') val = nf(total.cgst);
    else if(h[1] === 'staxamt') val = nf(total.staxamt);
    else if(h[1] === 'ramt') val = nf(total.ramt);
    else if(h[1] === 'balance') val = nf(total.balance);
    else if(h[1] === 'custname') val = 'Bills: '+nf(total.bills, 0);
    html += '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
  });
  html += '</tr>';
  return html;
}

function getRenderedRows(){
  const q = String(document.getElementById('searchBox')?.value || '').trim().toLowerCase();
  const out = q
    ? rows.filter(row => Object.values(row).some(v => String(v ?? '').toLowerCase().includes(q)))
    : [...rows];
  if(!sortCol) return out;
  out.sort((a,b)=>{
    let va=a[sortCol]??'', vb=b[sortCol]??'';
    if(!isNaN(parseFloat(va)) && !isNaN(parseFloat(vb))) return (parseFloat(va)-parseFloat(vb))*sortDir;
    return String(va).localeCompare(String(vb))*sortDir;
  });
  return out;
}

function buildBillGroups(sourceRows){
  const groups = [];
  const seen = new Map();
  sourceRows.forEach(row=>{
    const key = String(row.slno ?? '');
    if(!seen.has(key)){
      const smCode = row.smcode || '';
      const smName = row.smname || smCode;
      const group = {
        slno: row.slno,
        tdate: row.tdate || '',
        billno: row.billno || '',
        orderno: row.orderno || '',
        billtype: row.billtype || '',
        billTypeLabel: buildBillTypeLabel(row),
        categoryKey: buildBillCategory(row).key,
        categoryLabel: buildBillCategory(row).label,
        categoryOrder: buildBillCategory(row).order,
        custname: row.custname || '',
        discount: Number(row.discount || 0),
        sgst: Number(row.sgst || 0),
        cgst: Number(row.cgst || 0),
        staxamt: Number(row.staxamt || 0),
        eamt: Number(row.eamt || 0),
        sretamt: Number(row.sretamt || 0),
        ramt: Number(row.ramt || 0),
        balance: Number(row.balance || 0),
        smLabel: smCode && smName !== smCode ? (smName + ' (' + smCode + ')') : smName,
        qty: 0,
        weight: 0,
        stonewgt: 0,
        netwgt: 0,
        va: 0,
        amount: 0,
        items: []
      };
      seen.set(key, group);
      groups.push(group);
    }
    const group = seen.get(key);
    group.qty += Number(row.qty || 0);
    group.weight += Number(row.weight || 0);
    group.stonewgt += Number(row.stonewgt || 0);
    group.netwgt += Number(row.netwgt || 0);
    group.va += Number(row.va || 0);
    group.amount += Number(row.amount || 0);
    group.sgst = Number(row.sgst || group.sgst || 0);
    group.cgst = Number(row.cgst || group.cgst || 0);
    group.staxamt = Number(row.staxamt || group.staxamt || 0);
    group.items.push(row);
  });

  const mode = getSortMode();
  if(mode === 'discount_desc'){
    groups.sort((a,b)=> (b.discount - a.discount) || String(a.billno).localeCompare(String(b.billno)));
  } else if(mode === 'discount_asc'){
    groups.sort((a,b)=> (a.discount - b.discount) || String(a.billno).localeCompare(String(b.billno)));
  } else {
    groups.sort((a,b)=> {
      const dateCmp = String(a.tdate || '').localeCompare(String(b.tdate || ''));
      if(dateCmp) return dateCmp;
      if(a.categoryOrder !== b.categoryOrder) return a.categoryOrder - b.categoryOrder;
      const typeCmp = String(a.categoryLabel || '').localeCompare(String(b.categoryLabel || ''));
      if(typeCmp) return typeCmp;
      return String(a.billno || '').localeCompare(String(b.billno || ''), undefined, { numeric: true });
    });
  }

  return groups;
}

function buildBillCategory(row){
  const code = String(row.billtype || '').trim().toUpperCase();
  const name = String(row.billtype_name || '').trim().toUpperCase();
  const prefix = String(row.billtype_prefix || '').trim().toUpperCase();
  const text = [code, name, prefix, String(row.billno || '').trim().toUpperCase()].join(' ');
  const isB2B = /\bB2B|\bB3B|^B2|^B3/.test(text);

  if(isB2B && (text.includes('G18') || text.includes('18'))) return { key: 'b2b-g18', label: 'B2B Gold 18K', order: 61 };
  if(isB2B && (text.includes('GOLD') || /\bB2G\b/.test(text) || /\bGO\b/.test(text))) return { key: 'b2b-gold', label: 'B2B Gold', order: 60 };
  if(isB2B && (text.includes('DIAMOND') || text.includes('DAIMOND') || /\bB2D\b/.test(text) || /\bDM\b/.test(text))) return { key: 'b2b-diamond', label: 'B2B Diamond', order: 62 };
  if(isB2B && (text.includes('SIL') || /\bB2BSIL\b/.test(text))) return { key: 'b2b-silver', label: 'B2B Silver', order: 63 };
  if(isB2B) return { key: 'b2b-other', label: 'B2B Other', order: 69 };

  if(code === 'G18' || text.includes('GOLD 18')) return { key: 'gold18', label: 'Gold 18K', order: 11 };
  if(code === 'G' || text.includes('GOLD')) return { key: 'gold', label: 'Gold', order: 10 };
  if(code === 'D' || text.includes('DIAMOND') || text.includes('DAIMOND')) return { key: 'diamond', label: 'Diamond', order: 20 };
  if(code === 'S' || text.includes('SILVER') || text.includes('SIL')) return { key: 'silver', label: 'Silver', order: 30 };
  if(code === 'B' || text.includes('BULLION')) return { key: 'bullion', label: 'Bullion', order: 40 };
  return { key: code || 'other', label: buildBillTypeLabel(row) || 'Other', order: 50 };
}

function buildBillTypeLabel(row){
  const code = row.billtype || '';
  const name = row.billtype_name || '';
  if(code && name && name !== code) return name + ' (' + code + ')';
  return name || code || '';
}

function openSalesRegisterBill(billNo, orderNo){
  billNo = String(billNo || '').trim();
  if(!billNo){
    toast('No bill number found', false);
    return;
  }
  orderNo = String(orderNo || '').trim();
  const isOrderSale = orderNo !== '';
  const url = SITE + (isOrderSale ? '/order-sale/bill?bill_no=' : '/sales-bill/edit?bill_no=') + encodeURIComponent(billNo);
  if(window.parent && window.parent !== window){
    window.parent.postMessage({
      type: 'goldapp:open-module-url',
      moduleId: isOrderSale ? 'order-bill-order-sale' : 'sales-bill-edit',
      title: isOrderSale ? 'Order Sale' : 'Sales Bill',
      url: url,
      forceNewTab: true
    }, '*');
  } else {
    window.location.href = url;
  }
}

/* ── Sort ─────────────────────────────────────────────────── */
document.getElementById('thead').addEventListener('click', e=>{
  const th = e.target.closest('th[data-key]');
  if(!th) return;
  if(getRepType() === 'billwise') return;
  const key = th.dataset.key;
  if(sortCol===key) sortDir*=-1; else { sortCol=key; sortDir=1; }
  render();
});

/* ── Row highlight ────────────────────────────────────────── */
document.getElementById('tbody').addEventListener('click', e=>{
  const tr = e.target.closest('tr[data-idx],tr.grp-head[data-billno],tr.bill-total[data-billno]');
  if(!tr) return;
  document.querySelectorAll('#tbody tr').forEach(r=>r.classList.remove('sel'));
  tr.classList.add('sel');
});

document.getElementById('tbody').addEventListener('dblclick', e=>{
  const tr = e.target.closest('tr[data-billno]');
  if(!tr) return;
  openSalesRegisterBill(tr.dataset.billno, tr.dataset.orderno || '');
});

/* ── SM change auto-retrieves ─────────────────────────────── */
document.getElementById('smcode').addEventListener('change', ()=>{
  if(rows.length) loadData();
});
document.getElementById('sortmode').addEventListener('change', ()=>{
  if(rows.length) render();
});
document.getElementById('searchBox').addEventListener('input', render);

/* ── Buttons ──────────────────────────────────────────────── */
document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');

/* ── Init ─────────────────────────────────────────────────── */
loadLookups();
</script>
@if(is_file(public_path('js/report-export.js')))
<script src="{{ asset('js/report-export.js') }}?v={{ @filemtime(public_path('js/report-export.js')) }}"></script>
@endif
<script>
if (typeof ReportExport !== 'undefined') {
  ReportExport.init('btnSaveAs', headers, getRenderedRows,
    ()=>'sales-register-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
  document.getElementById('btnSaveAs').onclick = function(e){
    e.preventDefault();
    ReportExport.open(headers, getRenderedRows,
      ()=>'sales-register-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
  };
} else {
  document.getElementById('btnSaveAs').onclick = function(e){
    e.preventDefault();
    alert('Export file missing: public/js/report-export.js');
  };
}
</script>
</body>
</html>
