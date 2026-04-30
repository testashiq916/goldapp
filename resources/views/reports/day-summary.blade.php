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
.btn-active{background:#f59e0b;color:#1e293b}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:11px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.content-wrap{flex:1;overflow:auto;padding:12px}

/* ── Crosstab table (Parts 1 & 2) ── */
.grid-wrap{overflow:auto;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
table{width:100%;border-collapse:collapse;font-size:11px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:5px 8px;font-size:9px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2;cursor:pointer;user-select:none}
th:hover{background:#e2e8f0}
th.num{text-align:right}
td{padding:4px 8px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
tr.total-row td{background:#eff6ff;font-weight:700;border-top:2px solid #3b82f6}

/* ── Panels (Parts 3 & 4) ── */
.panels-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.panel{background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.08);overflow:hidden;transition:transform .15s,box-shadow .15s}
.panel:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.12)}
.panel-head{padding:10px 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid #f1f5f9}
.panel-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff;flex-shrink:0}
.panel-title{font-size:13px;font-weight:700;color:#1e293b}
.panel-body{padding:8px 14px 12px}
.panel-row{display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #f8fafc}
.panel-row:last-child{border-bottom:none}
.panel-lbl{font-size:11px;color:#64748b}
.panel-val{font-size:12px;font-weight:700;color:#1e293b;font-variant-numeric:tabular-nums}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:16px;flex-wrap:wrap;font-size:11px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:12px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

@media print{.toolbar,.summary,.sub-header{display:none !important}.content-wrap{overflow:visible !important}body{height:auto;overflow:visible}}
</style>
@include('partials.print-layout-head')
</head>
<body>

<div class="toolbar">
  <h1>{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Date</span><input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:130px"></div>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl">Part</span>
    <select id="part" class="tb-select" style="width:200px">
      <option value="1">Part 1 - Sales (Item wise)</option>
      <option value="2">Part 2 - Purchase (Item wise)</option>
      <option value="3">Part 3 - Financial Summary</option>
      <option value="4">Part 4 - Operations Summary</option>
    </select>
  </div>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="sub-header" id="subHeader"></div>

<div class="content-wrap" id="contentWrap">
  <div style="text-align:center;padding:60px;color:#94a3b8">Press <b>Show</b> to load data</div>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const RLEVEL = {{ $rlevel }};
let rows = [], totals = {}, panels = [], currentPart = 1, sortCol = '', sortDir = 1;

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

function headers(){
  return [
    ['Item Name','itemname'],['Type','itype'],
    ['Qty','qty',1,0],['Weight','weight',1,3]
  ];
}

/* ── Load data ── */
async function loadData(){
  currentPart = parseInt(document.getElementById('part').value);
  const qs = new URLSearchParams({
    date1: document.getElementById('date1').value,
    rlevel: RLEVEL,
    part: currentPart,
  });

  const partLabels = {1:'Sales (Item wise)',2:'Purchase (Item wise)',3:'Financial Summary',4:'Operations Summary'};
  document.getElementById('subHeader').textContent =
    'Date: '+document.getElementById('date1').value+' — Part '+currentPart+': '+partLabels[currentPart];

  try {
    const r = await fetch(SITE+'/api/day-summary/data?'+qs);
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Error',false); return; }

    if(currentPart <= 2){
      rows = d.rows||[]; totals = d.totals||{};
      sortCol = ''; sortDir = 1;
      renderTable();
      toast('Loaded '+rows.length+' items');
    } else {
      panels = d.panels||[];
      renderPanels();
      toast('Loaded '+panels.length+' panels');
    }
  } catch(e){ toast('Network error',false); }
}

/* ── Render crosstab table (Parts 1 & 2) ── */
function renderTable(){
  const wrap = document.getElementById('contentWrap');
  const hs = headers();

  let html = '<div class="grid-wrap"><table><thead><tr>';
  html += hs.map(h=>{
    let arrow = sortCol===h[1] ? (sortDir===1?' &#9650;':' &#9660;') : '';
    return '<th class="'+(h[2]?'num':'')+'" data-key="'+h[1]+'">'+esc(h[0])+arrow+'</th>';
  }).join('');
  html += '</tr></thead><tbody>';

  if(!rows.length){
    html += '<tr><td colspan="'+hs.length+'" style="text-align:center;padding:40px;color:#94a3b8">No records found</td></tr>';
  } else {
    rows.forEach((row,idx)=>{
      html += '<tr data-idx="'+idx+'">';
      hs.forEach(h=>{
        let val = row[h[1]];
        if(h[2]) val = nf(val, h[3]!=null?h[3]:2);
        html += '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
      });
      html += '</tr>';
    });
    // Total row
    html += '<tr class="total-row">';
    html += '<td><b>TOTAL</b></td><td></td>';
    html += '<td class="num"><b>'+nf(totals.qty,0)+'</b></td>';
    html += '<td class="num"><b>'+nf(totals.weight,3)+'</b></td>';
    html += '</tr>';
  }

  html += '</tbody></table></div>';
  wrap.innerHTML = html;

  // Re-attach sort listener
  const thead = wrap.querySelector('thead');
  if(thead) thead.addEventListener('click', e=>{
    const th = e.target.closest('th[data-key]'); if(!th) return;
    const key = th.dataset.key;
    if(sortCol===key) sortDir*=-1; else { sortCol=key; sortDir=1; }
    rows.sort((a,b)=>{
      let va=a[key]??'', vb=b[key]??'';
      if(!isNaN(parseFloat(va))) return (parseFloat(va)-parseFloat(vb))*sortDir;
      return String(va).localeCompare(String(vb))*sortDir;
    });
    renderTable();
  });

  // Summary
  const t = totals;
  document.getElementById('summary').innerHTML = [
    '<span>Items: <b>'+nf(t.count,0)+'</b></span>',
    '<span>Qty: <b>'+nf(t.qty,0)+'</b></span>',
    '<span>Weight: <b>'+nf(t.weight,3)+'</b></span>',
    '<span>G.Wgt: <b>'+nf(t.goldwgt,3)+'</b></span>',
    '<span>S.Wgt: <b>'+nf(t.silverwgt,3)+'</b></span>',
    '<span>O.Wgt: <b>'+nf(t.otherwgt,3)+'</b></span>',
  ].join('');
}

/* ── Render panel cards (Parts 3 & 4) ── */
function renderPanels(){
  const wrap = document.getElementById('contentWrap');

  if(!panels.length){
    wrap.innerHTML = '<div style="text-align:center;padding:60px;color:#94a3b8">No data for this date</div>';
    document.getElementById('summary').innerHTML = '';
    return;
  }

  let html = '<div class="panels-grid">';
  panels.forEach(p=>{
    html += '<div class="panel">';
    html += '<div class="panel-head">';
    html += '<div class="panel-icon" style="background:'+esc(p.color)+'"><i class="fa-solid '+esc(p.icon)+'"></i></div>';
    html += '<div class="panel-title">'+esc(p.title)+'</div>';
    html += '</div>';
    html += '<div class="panel-body">';
    (p.items||[]).forEach(item=>{
      const fmt = item.fmt != null ? item.fmt : 2;
      html += '<div class="panel-row">';
      html += '<span class="panel-lbl">'+esc(item.label)+'</span>';
      html += '<span class="panel-val">'+nf(item.value,fmt)+'</span>';
      html += '</div>';
    });
    html += '</div></div>';
  });
  html += '</div>';
  wrap.innerHTML = html;
  document.getElementById('summary').innerHTML = '<span>Panels: <b>'+panels.length+'</b></span>';
}

document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>
ReportExport.init('btnSaveAs', headers, ()=>rows,
  ()=>'day-summary-part'+document.getElementById('part').value+'-'+document.getElementById('date1').value);
</script>
</body>
</html>
