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
tr.ret td{color:#dc2626}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:16px;flex-wrap:wrap;font-size:17px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:17px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:17px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

@media print{.toolbar,.summary,.sub-header{display:none !important}.grid-wrap{overflow:visible !important}body{height:auto;overflow:visible}}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>

<div class="toolbar">
  <div class="tb-row">
    <h1>{{ $title }}</h1>
    <div class="sep"></div>
    <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:120px"></div>
    <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:120px"></div>
    <div class="sep"></div>
    <div class="f-group">
      <span class="tb-lbl">SM</span>
      <select id="smcode" class="tb-select" style="width:130px"><option value="">-- All --</option></select>
    </div>
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
    <label class="tb-check"><input type="checkbox" id="cbZeroDisc"> Zero Disc</label>
    <label class="tb-check"><input type="checkbox" id="cbCashOnly"> Cash Only</label>
    <div class="sep"></div>
    <button class="btn btn-show" id="btnShow">Show</button>
    <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
    <button class="btn btn-out" id="btnPrint">Print</button>
    <button class="btn btn-out" id="btnExit">Exit</button>
  </div>
  <div class="tb-row">
    <div class="f-group">
      <label class="tb-check"><input type="checkbox" id="cbIc" checked> IC</label>
      <select id="ic" class="tb-select" style="width:120px"><option value="">-- All --</option></select>
    </div>
    <div class="sep"></div>
    <div class="f-group">
      <span class="tb-lbl">Item</span>
      <input type="text" id="itemcode" class="tb-input" placeholder="Code" style="width:80px;text-transform:uppercase">
    </div>
    <div class="sep"></div>
    <div class="f-group">
      <span class="tb-lbl">Stk Type</span>
      <input type="text" id="stktype" class="tb-input" placeholder="" style="width:60px;text-transform:uppercase">
    </div>
    <div class="sep"></div>
    <div class="f-group">
      <span class="tb-lbl">Party</span>
      <input type="text" id="custcode" class="tb-input" placeholder="Code" style="width:80px;text-transform:uppercase">
    </div>
    <div class="sep"></div>
    <div class="f-group">
      <span class="tb-lbl">MC Type</span>
      <select id="mctype" class="tb-select" style="width:100px">
        <option value="">All</option>
        <option value="lt200">&lt; 200</option>
        <option value="200-250">200-250</option>
        <option value="250-300">250-300</option>
        <option value="300-350">300-350</option>
        <option value="gt350">&gt; 350</option>
      </select>
    </div>
  </div>
</div>

<div class="sub-header" id="subHeader"></div>

<div class="grid-wrap">
  <table>
    <thead id="thead"></thead>
    <tbody id="tbody"><tr><td colspan="12" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
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

function headers(){
  return [
    ['Date','tdate'],['Bill No','billno'],['Customer','custname'],
    ['Item','displayname'],['BCode','bcode'],
    ['Qty','qty',1,0],['Weight','weight',1,3],['St.Wgt','stonewgt',1,3],
    ['VA','va',1],['VA/gm','vapergm',1],
    ['Amount','amount',1],['Disc','discount',1],['MC Profit','mcprof',1]
  ];
}

/* ── Lookups ──────────────────────────────────────── */
async function loadLookups(){
  try {
    const r = await fetch(SITE+'/api/mc-profit/lookups');
    const d = await r.json();
    if(!d.ok) return;
    fillSelect('smcode', d.salesmen||[]);
    fillSelect('ic', d.incharges||[]);
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
  const qs = new URLSearchParams({
    date1: document.getElementById('date1').value,
    date2: document.getElementById('date2').value,
    rlevel: RLEVEL,
    smcode: document.getElementById('smcode').value,
    type: document.getElementById('type').value,
    ic: document.getElementById('cbIc').checked ? document.getElementById('ic').value : '',
    icon: document.getElementById('cbIc').checked ? 1 : 0,
    itemcode: document.getElementById('itemcode').value.trim().toUpperCase(),
    stktype: document.getElementById('stktype').value.trim().toUpperCase(),
    custcode: document.getElementById('custcode').value.trim().toUpperCase(),
    mctype: document.getElementById('mctype').value,
    zerodisc: document.getElementById('cbZeroDisc').checked ? 1 : 0,
    cashonly: document.getElementById('cbCashOnly').checked ? 1 : 0,
  });

  document.getElementById('subHeader').textContent = 'From: '+document.getElementById('date1').value+' To: '+document.getElementById('date2').value;

  try {
    const r = await fetch(SITE+'/api/mc-profit/data?'+qs);
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
  const thead = document.getElementById('thead');
  const tbody = document.getElementById('tbody');

  thead.innerHTML = '<tr>' + hs.map(h=>{
    let arrow = sortCol===h[1] ? (sortDir===1?' &#9650;':' &#9660;') : '';
    return '<th class="'+(h[2]?'num':'')+'" data-key="'+h[1]+'">'+esc(h[0])+arrow+'</th>';
  }).join('') + '</tr>';

  if(!rows.length){
    tbody.innerHTML = '<tr><td colspan="'+hs.length+'" style="text-align:center;padding:40px;color:#94a3b8">No records found</td></tr>';
  } else {
    tbody.innerHTML = rows.map((row,idx)=>{
      const cls = row.igrp===2 ? ' ret' : '';
      return '<tr data-idx="'+idx+'"'+' class="'+cls+'">' + hs.map(h=>{
        let val = row[h[1]];
        if(h[2]) val = nf(val, h[3]!=null?h[3]:2);
        return '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
      }).join('') + '</tr>';
    }).join('');
  }

  renderSummary();
}

function renderSummary(){
  const t = totals;
  document.getElementById('summary').innerHTML = [
    '<span>Rows: <b>'+nf(t.count,0)+'</b></span>',
    '<span>Gold Wgt: <b>'+nf(t.gold_weight,3)+'</b></span>',
    '<span>Gold Amt: <b>'+nf(t.gold_amount)+'</b></span>',
    '<span>Silver Wgt: <b>'+nf(t.silver_weight,3)+'</b></span>',
    '<span>St.Wgt: <b>'+nf(t.total_stonewgt,3)+'</b></span>',
    '<span>VA: <b>'+nf(t.total_va)+'</b></span>',
    '<span>Bill Amt: <b>'+nf(t.total_billamt)+'</b></span>',
    '<span>Disc: <b>'+nf(t.total_discount)+'</b></span>',
    '<span>MC Profit: <b>'+nf(t.total_mcprof)+'</b></span>',
  ].join('');
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

document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');
</script>
<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>
ReportExport.init('btnSaveAs', headers, ()=>rows,
  ()=>'mc-profit-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
</script>
</body>
</html>
