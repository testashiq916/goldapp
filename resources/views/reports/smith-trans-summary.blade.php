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
.tb-select option{color:#1e293b;background:#fff}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}
.tb-cb{display:flex;align-items:center;gap:4px;color:rgba(255,255,255,.85);font-size:10px;font-weight:600;cursor:pointer;white-space:nowrap}
.tb-cb input{accent-color:#60a5fa;width:14px;height:14px;cursor:pointer}

.grid-wrap{flex:1;overflow:auto}
table{width:100%;border-collapse:collapse;background:#fff;font-size:11px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:5px 6px;font-size:9px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2;cursor:pointer;user-select:none}
th:hover{background:#e2e8f0}
th.num{text-align:right}
th .arr{font-size:8px;margin-left:2px;opacity:.5}
td{padding:4px 6px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}

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
  <h1 id="rptTitle">{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:120px"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:120px"></div>
  <div class="sep"></div>
  <label class="tb-cb"><input type="checkbox" id="cbWithTran" checked> With Transaction Only</label>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Sort</span>
    <select id="selSort" class="tb-select" style="width:120px">
      <option value="name">Name</option>
      <option value="code">Code</option>
      <option value="issuedwgt1">Issued Wgt</option>
      <option value="rcvdwgt1">Rcvd Wgt</option>
      <option value="paidamt">Paid Amt</option>
    </select>
  </div>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="sub-header" id="subHeader"></div>

<div class="grid-wrap">
  <table>
    <thead id="thead"></thead>
    <tbody id="tbody"><tr><td colspan="9" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
  </table>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<script>
const SITE   = @json(request()->root());
const RLEVEL = {{ $rlevel }};
const CTYPE  = @json($ctype);
let rows = [], totals = {}, sortCol = 'name', sortDir = 1;

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function nf3(n){ return Number(n||0).toFixed(3); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

/* ── Columns (matches PB d_smithmcsummary) ── */
const cols = [
  { key:'code',       label:'Code',            cls:'' },
  { key:'name',       label:'Name',            cls:'' },
  { key:'rcvdwgt1',   label:'Total Rcvd Wgt',  cls:'num', fmt:'3' },
  { key:'wastage1',   label:'Total Wastage',   cls:'num', fmt:'3' },
  { key:'mcharge1',   label:'Total MCharge',   cls:'num', fmt:'2' },
  { key:'stamt1',     label:'Total St.Amt',    cls:'num', fmt:'2' },
  { key:'mcstamt',    label:'Total MC+St.Amt', cls:'num', fmt:'2' },
  { key:'paidamt',    label:'Total Paid Amt',  cls:'num', fmt:'2' },
  { key:'issuedwgt1', label:'Total Issued Wgt',cls:'num', fmt:'3' },
];

/* ── Render Header ── */
function renderHead(){
  const tr = document.createElement('tr');
  cols.forEach(c=>{
    const th = document.createElement('th');
    th.className = c.cls;
    th.innerHTML = esc(c.label) + (sortCol===c.key ? '<span class="arr">'+(sortDir>0?'▲':'▼')+'</span>' : '');
    th.onclick = () => { if(sortCol===c.key) sortDir*=-1; else { sortCol=c.key; sortDir=1; } doSort(); renderHead(); renderBody(); };
    tr.appendChild(th);
  });
  document.getElementById('thead').innerHTML = '';
  document.getElementById('thead').appendChild(tr);
}

/* ── Sort ── */
function doSort(){
  rows.sort((a,b)=>{
    let va=a[sortCol], vb=b[sortCol];
    if(typeof va==='string') va=(va||'').toLowerCase();
    if(typeof vb==='string') vb=(vb||'').toLowerCase();
    if(va==null) va='';
    if(vb==null) vb='';
    if(va<vb) return -sortDir;
    if(va>vb) return sortDir;
    return 0;
  });
}

/* ── Render Body ── */
function renderBody(){
  const tbody = document.getElementById('tbody');
  if(!rows.length){
    tbody.innerHTML='<tr><td colspan="'+cols.length+'" style="text-align:center;padding:40px;color:#94a3b8">No data found</td></tr>';
    renderSummary();
    return;
  }
  let html='';
  rows.forEach(r=>{
    html+='<tr>';
    cols.forEach(c=>{
      if(c.cls==='num'){
        const d = c.fmt==='3' ? 3 : 2;
        html+='<td class="num">'+Number(r[c.key]||0).toFixed(d)+'</td>';
      } else {
        html+='<td>'+esc(r[c.key])+'</td>';
      }
    });
    html+='</tr>';
  });
  tbody.innerHTML=html;
  renderSummary();
}

/* ── Summary ── */
function renderSummary(){
  const t = totals;
  let html = '<span>Rows: <b>'+(t.count||0)+'</b></span>';
  html += '<span>Rcvd Wgt: <b>'+nf3(t.rcvdwgt1)+'</b></span>';
  html += '<span>Wastage: <b>'+nf3(t.wastage1)+'</b></span>';
  html += '<span>MCharge: <b>'+nf(t.mcharge1)+'</b></span>';
  html += '<span>St.Amt: <b>'+nf(t.stamt1)+'</b></span>';
  html += '<span>MC+St: <b>'+nf(t.mcstamt)+'</b></span>';
  html += '<span>Paid Amt: <b>'+nf(t.paidamt)+'</b></span>';
  html += '<span>Issued Wgt: <b>'+nf3(t.issuedwgt1)+'</b></span>';
  document.getElementById('summary').innerHTML = html;
}

/* ── Load data ── */
function loadData(){
  const d1 = document.getElementById('date1').value;
  const d2 = document.getElementById('date2').value;
  const withTran = document.getElementById('cbWithTran').checked ? '1' : '0';

  const params = new URLSearchParams({date1:d1, date2:d2, ctype:CTYPE, rlevel:RLEVEL, withtran:withTran});
  document.getElementById('subHeader').textContent = 'Loading...';
  document.getElementById('tbody').innerHTML='<tr><td colspan="'+cols.length+'" style="text-align:center;padding:30px;color:#94a3b8">Loading...</td></tr>';

  fetch(SITE+'/api/smith-trans-summary/data?'+params.toString())
    .then(r=>r.json())
    .then(d=>{
      if(!d.ok){ toast(d.message||'Failed','err'); return; }
      rows = d.rows||[];
      totals = d.totals||{};
      sortCol = document.getElementById('selSort').value;
      sortDir = 1;
      doSort();
      renderHead();
      renderBody();
      document.getElementById('subHeader').textContent =
        rows.length+' accounts | Period: '+d1+' to '+d2;
    })
    .catch(()=> toast('Network error',false));
}

/* ── Save As CSV ── */
function saveAsCsv(){
  if(!rows.length){ toast('No data to export',false); return; }
  let csv = cols.map(c=>'"'+c.label+'"').join(',')+'\n';
  rows.forEach(r=>{
    csv += cols.map(c=>{
      let v = r[c.key]; if(v==null) v='';
      return '"'+String(v).replace(/"/g,'""')+'"';
    }).join(',')+'\n';
  });
  const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'smith_trans_summary_'+document.getElementById('date1').value+'_'+document.getElementById('date2').value+'.csv';
  a.click();
}

/* ── Events ── */
document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = () => window.print();
document.getElementById('btnSaveAs').onclick = saveAsCsv;
document.getElementById('btnExit').onclick = () => { if(window.parent!==window) window.parent.postMessage({action:'closeModule'},'*'); else window.close(); };
document.getElementById('selSort').onchange = () => { sortCol=document.getElementById('selSort').value; sortDir=1; doSort(); renderHead(); renderBody(); };

document.addEventListener('keydown', e=>{
  if(e.key==='F5'||e.key==='Enter'){ e.preventDefault(); loadData(); }
});

renderHead();
</script>
</body>
</html>
