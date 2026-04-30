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
.tb-input{height:28px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 6px;font-size:11px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
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

@media print{.toolbar,.summary,.sub-header{display:none !important}.grid-wrap{overflow:visible !important}body{height:auto;overflow:visible}}
</style>
@include('partials.print-layout-head')
</head>
<body>

<div class="toolbar">
  <h1>{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:120px"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:120px"></div>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button class="btn btn-out" id="btnSort">Sort</button>
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="sub-header" id="subHeader"></div>

<div class="grid-wrap">
  <table>
    <thead id="thead"></thead>
    <tbody id="tbody"><tr><td colspan="11" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
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
    ['Date','tdate'],['Doc No','docno'],['Smith','smithname'],
    ['Issu.Gr.Wgt','totwgt1',1,3],['Issu.Net Wgt','netwgt1',1,3],
    ['Rcvd.Gr.Wgt','totwgt2',1,3],['Rcvd.Net Wgt','nettwgt2',1,3],
    ['Wastage','totwstg',1,3],['M.C.','totmc',1],
    ['Tax','taxamt',1],['TDS','tdsamt',1]
  ];
}

async function loadData(){
  const qs = new URLSearchParams({
    date1: document.getElementById('date1').value,
    date2: document.getElementById('date2').value,
    rlevel: RLEVEL,
  });

  document.getElementById('subHeader').textContent =
    'From: '+document.getElementById('date1').value+' To: '+document.getElementById('date2').value;

  try {
    const r = await fetch(SITE+'/api/smith-book/data?'+qs);
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
      return '<tr data-idx="'+idx+'">' + hs.map(h=>{
        let val = row[h[1]];
        if(h[2]) val = nf(val, h[3]!=null?h[3]:2);
        return '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
      }).join('') + '</tr>';
    }).join('');
  }

  const t = totals;
  document.getElementById('summary').innerHTML = [
    '<span>Bills: <b>'+nf(t.count,0)+'</b></span>',
    '<span>Issu.Gr: <b>'+nf(t.totwgt1,3)+'</b></span>',
    '<span>Issu.Net: <b>'+nf(t.netwgt1,3)+'</b></span>',
    '<span>Rcvd.Gr: <b>'+nf(t.totwgt2,3)+'</b></span>',
    '<span>Rcvd.Net: <b>'+nf(t.nettwgt2,3)+'</b></span>',
    '<span>Wastage: <b>'+nf(t.totwstg,3)+'</b></span>',
    '<span>M.C.: <b>'+nf(t.totmc)+'</b></span>',
    '<span>Tax: <b>'+nf(t.taxamt)+'</b></span>',
    '<span>TDS: <b>'+nf(t.tdsamt)+'</b></span>',
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
  ()=>'smith-book-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
</script>
</body>
</html>
