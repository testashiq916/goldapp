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

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:8px 14px;display:flex;flex-direction:column;gap:6px;flex-shrink:0}
.tb-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap;margin-right:4px}
.tb-lbl{color:rgba(255,255,255,.8);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.tb-input,.tb-select{height:28px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 6px;font-size:11px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus,.tb-select:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.tb-select option{background:#1e3a5f;color:#fff}
.tb-check{display:flex;align-items:center;gap:3px;color:rgba(255,255,255,.85);font-size:10px;font-weight:600;cursor:pointer;white-space:nowrap}
.tb-check input{width:13px;height:13px;accent-color:#60a5fa;cursor:pointer}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}

.grid-wrap{flex:1;overflow:auto}
table{width:100%;border-collapse:collapse;background:#fff;font-size:11px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:5px 6px;font-size:9px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2;cursor:pointer;user-select:none}
th:hover{background:#e2e8f0}
th.num{text-align:right}
td{padding:4px 6px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
tr.sel td{background:#dbeafe !important}
tr.grp-head td{background:#eff6ff;font-weight:700;color:#1e40af;border-top:2px solid #bfdbfe}
tr.bill-total td{background:#fff7ed;font-weight:700;color:#9a3412;border-top:1px solid #fdba74;border-bottom:2px solid #fdba74}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:16px;flex-wrap:wrap;font-size:11px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:11px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:12px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

@media print{
  .toolbar,.summary,.sub-header{display:none !important}
  .grid-wrap{overflow:visible !important}
  body{height:auto;overflow:visible}
}
</style>
@include('partials.print-layout-head')
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
      <span class="tb-lbl">Type1</span>
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
      ['Qty','qty',1,0],['Weight','weight',1,3],['Stone Wgt','stonewgt',1,3],
      ['Net Wgt','netwgt',1,3],['Amount','amount',1],['VA','mcharge',1],['St.Price','stoneprice',1]
    ];
  }
  // billwise, itemwise, smanwise all show detail columns
  return [
    ['Date','tdate'],['Bill No','billno'],['Item','item_name'],
    ['Qty','qty',1,0],['Weight','weight',1,3],['St.Wgt','stonewgt',1,3],
    ['VA','va',1],['Amount','amount',1],
    ['Ex.Amt','eamt',1],['S.Ret','sretamt',1],['Disc','discount',1],
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
  } catch(e){ console.warn('Lookup error',e); }
}
function fillSelect(id, items){
  const sel = document.getElementById(id);
  if(!sel) return;
  const val = sel.value;
  while(sel.options.length > 1) sel.remove(1);
  (items||[]).forEach(it=>{
    const o = new Option(it.code+' - '+it.name, it.code);
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
    billGroups.forEach((group, groupIdx)=>{
      html += '<tr class="grp-head"><td colspan="'+hs.length+'">Bill: '+esc(group.billno || '')
        +' | Date: '+esc(group.tdate || '')
        +' | Party: '+esc(group.custname || '')
        +(group.smLabel ? ' | SM: '+esc(group.smLabel) : '')
        +(group.discount > 0 ? ' | Disc: '+nf(group.discount) : '')
        +'</td></tr>';

      group.items.forEach((row, idx)=>{
        html += '<tr data-idx="'+groupIdx+'-'+idx+'">';
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

      html += '<tr class="bill-total">';
      hs.forEach(h=>{
        let val = '';
        if(h[1] === 'billno') val = 'Bill Total';
        else if(h[1] === 'qty') val = nf(group.qty, 0);
        else if(h[1] === 'weight') val = nf(group.weight, 3);
        else if(h[1] === 'stonewgt') val = nf(group.stonewgt, 3);
        else if(h[1] === 'va') val = nf(group.va);
        else if(h[1] === 'amount') val = nf(group.amount);
        else if(h[1] === 'eamt') val = nf(group.eamt);
        else if(h[1] === 'sretamt') val = nf(group.sretamt);
        else if(h[1] === 'discount') val = nf(group.discount);
        else if(h[1] === 'ramt') val = nf(group.ramt);
        else if(h[1] === 'balance') val = nf(group.balance);
        else if(h[1] === 'custname') val = group.custname || '';
        html += '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
      });
      html += '</tr>';
    });
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

      html += '<tr data-idx="'+idx+'">';
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
    parts.push('<span>Weight: <b>'+nf(t.weight,3)+'</b></span>');
    parts.push('<span>Net Wgt: <b>'+nf(t.netwgt,3)+'</b></span>');
    parts.push('<span>Amount: <b>'+nf(t.amount)+'</b></span>');
    parts.push('<span>VA: <b>'+nf(t.mcharge)+'</b></span>');
  } else {
    if(rt === 'billwise') parts.unshift('<span>Bills: <b>'+billGroups.length+'</b></span>');
    parts.push('<span>Qty: <b>'+nf(t.qty,0)+'</b></span>');
    parts.push('<span>Weight: <b>'+nf(t.weight,3)+'</b></span>');
    parts.push('<span>Amount: <b>'+nf(t.amount)+'</b></span>');
    parts.push('<span>Ex.Amt: <b>'+nf(t.eamt)+'</b></span>');
    parts.push('<span>S.Ret: <b>'+nf(t.sretamt)+'</b></span>');
    parts.push('<span>Disc: <b>'+nf(t.discount)+'</b></span>');
    parts.push('<span>Rcvd: <b>'+nf(t.ramt)+'</b></span>');
    parts.push('<span>Balance: <b>'+nf(t.balance)+'</b></span>');
  }
  document.getElementById('summary').innerHTML = parts.join('');
}

function getRenderedRows(){
  const out = [...rows];
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
        custname: row.custname || '',
        discount: Number(row.discount || 0),
        eamt: Number(row.eamt || 0),
        sretamt: Number(row.sretamt || 0),
        ramt: Number(row.ramt || 0),
        balance: Number(row.balance || 0),
        smLabel: smCode && smName !== smCode ? (smName + ' (' + smCode + ')') : smName,
        qty: 0,
        weight: 0,
        stonewgt: 0,
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
    group.va += Number(row.va || 0);
    group.amount += Number(row.amount || 0);
    group.items.push(row);
  });

  const mode = getSortMode();
  if(mode === 'discount_desc'){
    groups.sort((a,b)=> (b.discount - a.discount) || String(a.billno).localeCompare(String(b.billno)));
  } else if(mode === 'discount_asc'){
    groups.sort((a,b)=> (a.discount - b.discount) || String(a.billno).localeCompare(String(b.billno)));
  }

  return groups;
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
  const tr = e.target.closest('tr[data-idx]');
  if(!tr) return;
  document.querySelectorAll('#tbody tr').forEach(r=>r.classList.remove('sel'));
  tr.classList.add('sel');
});

/* ── SM change auto-retrieves ─────────────────────────────── */
document.getElementById('smcode').addEventListener('change', ()=>{
  if(rows.length) loadData();
});
document.getElementById('sortmode').addEventListener('change', ()=>{
  if(rows.length) render();
});

/* ── Buttons ──────────────────────────────────────────────── */
document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');

/* ── Init ─────────────────────────────────────────────────── */
loadLookups();
</script>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>
ReportExport.init('btnSaveAs', headers, ()=>rows,
  ()=>'sales-register-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
document.getElementById('btnSaveAs').onclick = function(e){
  e.preventDefault();
  ReportExport.open(headers, ()=>rows,
    ()=>'sales-register-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
};
</script>
</body>
</html>
