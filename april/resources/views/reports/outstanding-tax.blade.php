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

.sub-header{background:#eff6ff;padding:5px 14px;font-size:11px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.content-wrap{flex:1;overflow:auto;padding:16px;display:flex;justify-content:center}

.report-card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.1);width:100%;max-width:800px;overflow:hidden}
.report-card table{width:100%;border-collapse:collapse;font-size:12px}
.report-card th{background:#f1f5f9;padding:10px 16px;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;border-bottom:2px solid #e2e8f0}
.report-card th.num{text-align:right}
.report-card td{padding:8px 16px;border-bottom:1px solid #f1f5f9}
.report-card td.num{text-align:right;font-variant-numeric:tabular-nums}
.report-card tr:hover td{background:#f8fafc}
.report-card tr.total-row td{background:#eff6ff;font-weight:700;border-top:2px solid #3b82f6}
.report-card tr.outstanding-row td{background:#fef3c7;font-weight:700;border-top:2px solid #f59e0b;color:#92400e}
.report-card tr.outstanding-row td.positive{color:#16a34a}
.report-card tr.outstanding-row td.negative{color:#dc2626}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:16px;flex-wrap:wrap;font-size:11px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:12px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

@media print{.toolbar,.summary,.sub-header{display:none !important}.content-wrap{overflow:visible !important;padding:0}body{height:auto;overflow:visible}.report-card{max-width:100%;box-shadow:none}}
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
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="sub-header" id="subHeader"></div>

<div class="content-wrap">
  <div class="report-card" id="reportCard">
    <table>
      <thead><tr><th>Description</th><th class="num">Input Tax</th><th class="num">Output Tax</th></tr></thead>
      <tbody id="tbody"><tr><td colspan="3" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
    </table>
  </div>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const RLEVEL = {{ $rlevel }};
let rows = [], totals = {};

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

function headers(){
  return [
    ['Description','description'],
    ['Input Tax','inputtax',1],
    ['Output Tax','outputtax',1]
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
    const r = await fetch(SITE+'/api/outstanding-tax/data?'+qs);
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Error',false); return; }
    rows = d.rows||[]; totals = d.totals||{};
    render();
    toast('Loaded '+rows.length+' entries');
  } catch(e){ toast('Network error',false); }
}

function render(){
  const tbody = document.getElementById('tbody');

  if(!rows.length){
    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:40px;color:#94a3b8">No tax data found for this period</td></tr>';
  } else {
    let html = '';

    // Data rows (only show non-zero values)
    rows.forEach(row=>{
      const inp = parseFloat(row.inputtax)||0;
      const out = parseFloat(row.outputtax)||0;
      html += '<tr>';
      html += '<td>'+esc(row.description)+'</td>';
      html += '<td class="num">'+(inp > 0 ? nf(inp) : '')+'</td>';
      html += '<td class="num">'+(out > 0 ? nf(out) : '')+'</td>';
      html += '</tr>';
    });

    // Total row
    html += '<tr class="total-row">';
    html += '<td>TOTAL</td>';
    html += '<td class="num">'+nf(totals.inputtax)+'</td>';
    html += '<td class="num">'+nf(totals.outputtax)+'</td>';
    html += '</tr>';

    // Outstanding row
    const netIn = parseFloat(totals.netinput)||0;
    const netOut = parseFloat(totals.netoutput)||0;
    html += '<tr class="outstanding-row">';
    html += '<td>Outstanding</td>';
    html += '<td class="num '+(netIn>0?'positive':'')+'">'+(netIn > 0 ? nf(netIn) : '')+'</td>';
    html += '<td class="num '+(netOut>0?'negative':'')+'">'+(netOut > 0 ? nf(netOut) : '')+'</td>';
    html += '</tr>';

    tbody.innerHTML = html;
  }

  const t = totals;
  const net = (parseFloat(t.inputtax)||0) - (parseFloat(t.outputtax)||0);
  document.getElementById('summary').innerHTML = [
    '<span>Input Tax: <b>'+nf(t.inputtax)+'</b></span>',
    '<span>Output Tax: <b>'+nf(t.outputtax)+'</b></span>',
    '<span>Net: <b style="color:'+(net>=0?'#16a34a':'#dc2626')+'">'+nf(net)+'</b></span>',
    '<span>'+(net>=0?'Input Credit':'Output Payable')+': <b>'+nf(Math.abs(net))+'</b></span>',
  ].join('');
}

document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');
</script>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>
ReportExport.init('btnSaveAs', headers, ()=>rows,
  ()=>'outstanding-tax-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
</script>
</body>
</html>
