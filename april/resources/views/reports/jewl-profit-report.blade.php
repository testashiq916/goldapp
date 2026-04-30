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
.tb-input::placeholder{color:rgba(255,255,255,.4)}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:11px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.grid-wrap{flex:1;overflow:auto}
table{width:100%;border-collapse:collapse;background:#fff;font-size:11px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:5px 6px;font-size:9px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2;cursor:pointer;user-select:none}
th:hover{background:#e2e8f0}
th.num{text-align:right}
th .arr{font-size:8px;margin-left:2px;opacity:.5}
td{padding:4px 6px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
tr.total-row td{background:#eff6ff;font-weight:700;border-top:2px solid #bfdbfe}
.pos{color:#16a34a}.neg{color:#dc2626}

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
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="inpDate1" class="tb-input" value="{{ $date1 }}"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="inpDate2" class="tb-input" value="{{ $date2 }}"></div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Code</span><input type="text" id="inpCode" class="tb-input" placeholder="Jeweller (blank=all)" style="width:130px"></div>
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
    <tbody id="tbody"><tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
  </table>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const RLEVEL = {{ $rlevel }};
let rows = [], sortCol = 'name', sortDir = 1;

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function nf3(n){ return Number(n||0).toFixed(3); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

const COLS = [
  { key:'name',       label:'Jewellery',    cls:'' },
  { key:'code',       label:'Code',         cls:'' },
  { key:'issuewgt',   label:'Issued Wgt',   cls:'num', fmt:'3' },
  { key:'issuestwgt', label:'Stone Wgt',    cls:'num', fmt:'3' },
  { key:'netwgt',     label:'Net Wgt',      cls:'num', fmt:'3' },
  { key:'jewlmc',     label:'Jewl MC',      cls:'num', fmt:'2' },
  { key:'jsmithmc',   label:'Smith MC',     cls:'num', fmt:'2' },
  { key:'profit',     label:'Profit',       cls:'num', fmt:'2', color:true },
];

function renderHead(){
  const tr = document.createElement('tr');
  COLS.forEach(c=>{
    const th = document.createElement('th');
    th.className = c.cls;
    th.innerHTML = esc(c.label) + (sortCol===c.key ? '<span class="arr">'+(sortDir>0?'▲':'▼')+'</span>' : '');
    th.onclick = () => { if(sortCol===c.key) sortDir*=-1; else { sortCol=c.key; sortDir=1; } doSort(); renderHead(); renderBody(); };
    tr.appendChild(th);
  });
  document.getElementById('thead').innerHTML = '';
  document.getElementById('thead').appendChild(tr);
}

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

function renderBody(){
  const tbody = document.getElementById('tbody');
  if(!rows.length){
    tbody.innerHTML='<tr><td colspan="'+COLS.length+'" style="text-align:center;padding:40px;color:#94a3b8">No data found</td></tr>';
    renderSummary(null);
    return;
  }

  let html='';
  let tIssuewgt=0, tIssuestwgt=0, tNetwgt=0, tJewlmc=0, tJsmithmc=0, tProfit=0;

  rows.forEach(r=>{
    tIssuewgt+=r.issuewgt; tIssuestwgt+=r.issuestwgt; tNetwgt+=r.netwgt;
    tJewlmc+=r.jewlmc; tJsmithmc+=r.jsmithmc; tProfit+=r.profit;

    html+='<tr>';
    COLS.forEach(c=>{
      if(c.cls==='num'){
        const d = c.fmt==='3'?3:2;
        const v = Number(r[c.key]||0);
        let cls = 'num';
        if(c.color && v<0) cls+=' neg';
        else if(c.color && v>0) cls+=' pos';
        html+='<td class="'+cls+'">'+v.toFixed(d)+'</td>';
      } else {
        html+='<td>'+esc(r[c.key])+'</td>';
      }
    });
    html+='</tr>';
  });

  // Total row
  html+='<tr class="total-row">';
  html+='<td colspan="2" style="text-align:right">Total ('+rows.length+')</td>';
  html+='<td class="num">'+nf3(tIssuewgt)+'</td>';
  html+='<td class="num">'+nf3(tIssuestwgt)+'</td>';
  html+='<td class="num">'+nf3(tNetwgt)+'</td>';
  html+='<td class="num">'+nf(tJewlmc)+'</td>';
  html+='<td class="num">'+nf(tJsmithmc)+'</td>';
  const pCls = tProfit<0?'num neg':(tProfit>0?'num pos':'num');
  html+='<td class="'+pCls+'">'+nf(tProfit)+'</td>';
  html+='</tr>';

  tbody.innerHTML=html;
  renderSummary({issuewgt:tIssuewgt, issuestwgt:tIssuestwgt, netwgt:tNetwgt, jewlmc:tJewlmc, jsmithmc:tJsmithmc, profit:tProfit});
}

function renderSummary(t){
  const el = document.getElementById('summary');
  if(!t){ el.innerHTML=''; return; }
  el.innerHTML =
    '<span>Jewellers: <b>'+rows.length+'</b></span>'+
    '<span>Total Issued Wgt: <b>'+nf3(t.issuewgt)+'</b></span>'+
    '<span>Net Wgt: <b>'+nf3(t.netwgt)+'</b></span>'+
    '<span>Total Profit: <b>'+nf(t.profit)+'</b></span>';
}

function loadData(){
  const date1 = document.getElementById('inpDate1').value;
  const date2 = document.getElementById('inpDate2').value;
  const code  = document.getElementById('inpCode').value.trim();

  const params = new URLSearchParams({date1, date2, code, rlevel: RLEVEL});
  document.getElementById('subHeader').textContent = 'Loading...';
  document.getElementById('tbody').innerHTML='<tr><td colspan="'+COLS.length+'" style="text-align:center;padding:30px;color:#94a3b8">Loading...</td></tr>';

  fetch(SITE+'/api/jewl-profit-report/data?'+params.toString())
    .then(r=>r.json())
    .then(d=>{
      if(!d.ok){ toast(d.message||'Failed',false); return; }
      rows = d.rows||[];
      sortCol='name'; sortDir=1;
      doSort();
      renderHead();
      renderBody();

      const parts = [rows.length+' jewellers loaded'];
      if(code) parts.push('Code: '+code);
      document.getElementById('subHeader').textContent = parts.join(' | ');
    })
    .catch(()=> toast('Network error',false));
}

function saveAsCsv(){
  if(!rows.length){ toast('No data to export',false); return; }
  let csv = COLS.map(c=>'"'+c.label+'"').join(',')+'\n';
  rows.forEach(r=>{
    csv += COLS.map(c=>{
      let v = r[c.key]; if(v==null) v='';
      return '"'+String(v).replace(/"/g,'""')+'"';
    }).join(',')+'\n';
  });
  const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'jewl_profit_report.csv';
  a.click();
}

document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = () => window.print();
document.getElementById('btnSaveAs').onclick = saveAsCsv;
document.getElementById('btnExit').onclick = () => { if(window.parent!==window) window.parent.postMessage({action:'closeModule'},'*'); else window.close(); };

document.addEventListener('keydown', e=>{
  if(e.key==='F5'||e.key==='Enter'){ e.preventDefault(); loadData(); }
});

renderHead();
</script>
</body>
</html>
