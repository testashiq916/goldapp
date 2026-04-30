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

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:8px 14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;flex-shrink:0}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap;margin-right:4px}
.tb-lbl{color:rgba(255,255,255,.8);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.tb-input,.tb-select{height:28px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 6px;font-size:11px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus,.tb-select:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.tb-select option{background:#1e3a5f;color:#fff}
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

.sub-header{background:#eff6ff;padding:5px 14px;font-size:11px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:16px;flex-wrap:wrap;font-size:11px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:12px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}
.wait-mask{position:fixed;inset:0;background:rgba(15,23,42,.35);display:none;align-items:center;justify-content:center;z-index:300}
.wait-mask.show{display:flex}
.wait-card{background:#fff;border-radius:12px;padding:18px 24px;min-width:260px;box-shadow:0 12px 40px rgba(0,0,0,.25);text-align:center}
.wait-card strong{display:block;font-size:16px;color:#1e3a5f;margin-bottom:6px}
.wait-card span{font-size:12px;color:#64748b}

@media print{.toolbar,.summary,.sub-header{display:none !important}.grid-wrap{overflow:visible !important}body{height:auto;overflow:visible}}
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
  <h1>{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:120px"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:120px"></div>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl">Mode</span>
    <select id="mode" class="tb-select" style="width:110px">
      <option value="daywise">Day Wise</option>
      <option value="billwise">Bill Wise</option>
    </select>
  </div>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl">Bill Type</span>
    <select id="billtype" class="tb-select" style="width:120px"><option value="">-- All --</option></select>
  </div>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button class="btn btn-out" id="btnSort">Sort</button>
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button type="button" class="btn btn-out" id="btnExcel">To Excel</button>
  <button type="button" class="btn btn-out" id="btnPdf">To PDF</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
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
let rows = [], totals = {}, sortCol = '', sortDir = 1, currentMode = 'daywise';
const WAIT = document.getElementById('waitMask');

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

function daywiseHeaders(){
  return [
    ['Date','tdate'],['Doc Nos','docnos'],
    ['Gr.Wgt','grosswgt',1,3],['Net Wgt','netwgt',1,3],
    ['Bill Amt','billamt',1],['Paid Amt','pamt',1],['Ex.Amt','eamt',1],
    ['Discount','discount',1],['HMC','hmc',1],
    ['SGST','sgst',1],['CGST','cgst',1],['IGST','igst',1],
    ['Round','round_amt',1],['Net Amt','netamt',1]
  ];
}

function billwiseHeaders(){
  return [
    ['Date','tdate'],['Doc No','docno'],['Supplier','suppname'],
    ['Gr.Wgt','grosswgt',1,3],['Net Wgt','netwgt',1,3],
    ['Bill Amt','billamt',1],['Paid Amt','pamt',1],['Ex.Amt','eamt',1],
    ['Discount','discount',1],['HMC','hmc',1],
    ['SGST','sgst',1],['CGST','cgst',1],['IGST','igst',1],
    ['Net Amt','netamt',1]
  ];
}

function headers(){ return currentMode === 'billwise' ? billwiseHeaders() : daywiseHeaders(); }

async function loadLookups(){
  try {
    const r = await fetch(SITE+'/api/tax-purchase-book/lookups');
    const d = await r.json();
    if(!d.ok) return;
    const sel = document.getElementById('billtype');
    const first = sel.options[0].outerHTML;
    sel.innerHTML = first + (d.billtypes||[]).map(i=>'<option value="'+esc(i.code)+'">'+esc(i.code)+' - '+esc(i.name)+'</option>').join('');
  } catch(e){}
}
loadLookups();

async function loadData(){
  if (WAIT) WAIT.classList.add('show');
  currentMode = document.getElementById('mode').value;
  const qs = new URLSearchParams({
    date1: document.getElementById('date1').value,
    date2: document.getElementById('date2').value,
    rlevel: RLEVEL,
    mode: currentMode,
    billtype: document.getElementById('billtype').value,
  });

  document.getElementById('subHeader').textContent =
    'From: '+document.getElementById('date1').value+' To: '+document.getElementById('date2').value+
    ' ('+currentMode.charAt(0).toUpperCase()+currentMode.slice(1)+')';

  try {
    const r = await fetch(SITE+'/api/tax-purchase-book/data?'+qs);
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Error',false); return; }
    rows = d.rows||[]; totals = d.totals||{};
    sortCol = ''; sortDir = 1;
    render();
    toast('Loaded '+rows.length+' rows');
  } catch(e){ toast('Network error',false); }
  finally { if (WAIT) WAIT.classList.remove('show'); }
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
      return '<tr data-idx="'+idx+'">' + hs.map(h=>{
        let val = row[h[1]];
        if(h[2]) val = nf(val, h[3]!=null?h[3]:2);
        return '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
      }).join('') + '</tr>';
    }).join('');
  }

  const t = totals;
  document.getElementById('summary').innerHTML = [
    '<span>Rows: <b>'+nf(t.count,0)+'</b></span>',
    '<span>Gr.Wgt: <b>'+nf(t.grosswgt,3)+'</b></span>',
    '<span>Net Wgt: <b>'+nf(t.netwgt,3)+'</b></span>',
    '<span>Bill Amt: <b>'+nf(t.billamt)+'</b></span>',
    '<span>SGST: <b>'+nf(t.sgst)+'</b></span>',
    '<span>CGST: <b>'+nf(t.cgst)+'</b></span>',
    '<span>IGST: <b>'+nf(t.igst)+'</b></span>',
    '<span>Net Amt: <b>'+nf(t.netamt)+'</b></span>',
  ].join('');
}

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

document.getElementById('btnSort').onclick = ()=>{ sortCol=''; sortDir=1; render(); toast('Sort reset'); };
document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');
</script>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>
ReportExport.init('btnSaveAs', headers, ()=>rows,
  ()=>'purchase-book-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
document.getElementById('btnExcel').onclick = function(){
  ReportExport.open(headers, ()=>rows,
    ()=>'tax-purchase-book-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
  setTimeout(function(){
    const xlsCard = document.querySelector('[data-xtype="xls"]');
    if (xlsCard) xlsCard.click();
  }, 0);
};
document.getElementById('btnPdf').onclick = function(){
  ReportExport.open(headers, ()=>rows,
    ()=>'tax-purchase-book-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
  setTimeout(function(){
    const pdfCard = document.querySelector('[data-xtype="pdf"]');
    if (pdfCard) pdfCard.click();
  }, 0);
};
</script>
</body>
</html>
