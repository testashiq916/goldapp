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

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:8px 14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;flex-shrink:0}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap;margin-right:4px}
.tb-lbl{color:rgba(255,255,255,.8);font-size:17px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.tb-input,.tb-select{height:28px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 6px;font-size:17px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus,.tb-select:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.tb-select option{color:#1e293b;background:#fff}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:17px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}
.tb-cb{display:flex;align-items:center;gap:4px;color:rgba(255,255,255,.85);font-size:17px;font-weight:600;cursor:pointer;white-space:nowrap}
.tb-cb input{accent-color:#60a5fa;width:14px;height:14px;cursor:pointer}

.grid-wrap{flex:1;overflow:auto}
table{width:100%;border-collapse:collapse;background:#fff;font-size:18px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:5px 6px;font-size:15px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2;cursor:pointer;user-select:none}
th:hover{background:#e2e8f0}
th.num{text-align:right}
th .arr{font-size:8px;margin-left:2px;opacity:.5}
td{padding:4px 6px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
tr.sel td{background:#dbeafe !important}
.give{color:#dc2626;font-weight:600}
.get{color:#16a34a;font-weight:600}
.status-tag{font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;margin-left:4px}
.tag-give{background:#fee2e2;color:#dc2626}
.tag-get{background:#dcfce7;color:#16a34a}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:17px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:8px 14px;font-size:17px;flex-shrink:0}
.sum-row{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:3px}
.sum-row:last-child{margin-bottom:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

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
  <h1 id="rptTitle">{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Up To</span><input type="date" id="asDate" class="tb-input" value="{{ $date }}" style="width:120px"></div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">CType</span>
    <select id="selCtype" class="tb-select" style="width:130px">
      <option value="">All</option>
      <option value="G"{{ $ctype==='G'?' selected':'' }}>Smith Only</option>
      <option value="J"{{ $ctype==='J'?' selected':'' }}>Jewellery Only</option>
      <option value="C"{{ $ctype==='C'?' selected':'' }}>Deposit Only</option>
    </select>
  </div>
  <div class="f-group"><span class="tb-lbl">Type</span>
    <select id="selMetal" class="tb-select" style="width:90px">
      <option value="G">Gold</option>
      <option value="S">Silver</option>
    </select>
  </div>
  <div class="f-group"><span class="tb-lbl">Group</span>
    <select id="selGroup" class="tb-select" style="width:110px">
      <option value="">All</option>
      @foreach($groups as $g)
      <option value="{{ $g['code'] }}">{{ $g['code'] }} - {{ $g['name'] }}</option>
      @endforeach
    </select>
  </div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Rpt Type</span>
    <select id="selRtype" class="tb-select" style="width:100px">
      <option value="Account">Account</option>
      <option value="Stock">Stock</option>
    </select>
  </div>
  <div class="f-group" id="grpTouch" style="display:none"><span class="tb-lbl">Touch</span><input type="number" id="inpTouch" class="tb-input" value="0" step="0.01" style="width:70px"></div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Wgt Status</span>
    <select id="selWgtStatus" class="tb-select" style="width:110px">
      <option value="">All</option>
      <option value="get">Only To Get</option>
      <option value="give">Only To Give</option>
    </select>
  </div>
  <div class="sep"></div>
  <label class="tb-cb"><input type="checkbox" id="cbBalOnly" checked> Bal Only</label>
  <label class="tb-cb"><input type="checkbox" id="cbCommonTouch"> To Common Touch</label>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Sort</span>
    <select id="selSort" class="tb-select" style="width:120px">
      <option value="name">Name</option>
      <option value="code">Code</option>
      <option value="lastdt">Last Issued Dt</option>
      <option value="lastrdt">Last Rcvd Dt</option>
      <option value="weightbal">Weight Bal</option>
      <option value="tbal">Amount Bal</option>
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
    <tbody id="tbody"><tr><td colspan="10" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
  </table>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<script>
const SITE   = @json(request()->root());
const RLEVEL = {{ $rlevel }};
let rows = [], totals = {}, sortCol = 'name', sortDir = 1;

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function nf3(n){ return Number(n||0).toFixed(3); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

/* ── Columns (matches PB d_smithwasummary) ── */
const cols = [
  { key:'code',      label:'Code',          cls:'' },
  { key:'name',      label:'Party Name',    cls:'' },
  { key:'lastrdt',   label:'Last Rcvd Dt',  cls:'' },
  { key:'lastdt',    label:'Last Issued Dt', cls:'' },
  { key:'weightbal', label:'Weight Bal',     cls:'num' },
  { key:'wgt100bal', label:'Wgt 100 Bal',    cls:'num' },
  { key:'wgtstatus', label:'',               cls:'', nosort:true },
  { key:'tbal',      label:'Amt Bal',        cls:'num' },
  { key:'amtstatus', label:'',               cls:'', nosort:true },
];

/* ── Render Header ── */
function renderHead(){
  const showWgt100 = document.getElementById('cbCommonTouch').checked;
  const tr = document.createElement('tr');
  cols.forEach(c=>{
    if(c.key==='wgt100bal' && !showWgt100) return;
    const th = document.createElement('th');
    th.className = c.cls;
    if(c.nosort){
      th.innerHTML = esc(c.label);
      th.style.cursor = 'default';
    } else {
      th.innerHTML = esc(c.label) + (sortCol===c.key ? '<span class="arr">'+(sortDir>0?'▲':'▼')+'</span>' : '');
      th.onclick = () => { if(sortCol===c.key) sortDir*=-1; else { sortCol=c.key; sortDir=1; } doSort(); renderHead(); renderBody(); };
    }
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

/* ── Status tag HTML ── */
function statusTag(s){
  if(s==='give') return '<span class="status-tag tag-give">To Give</span>';
  if(s==='get')  return '<span class="status-tag tag-get">To Get</span>';
  return '';
}

/* ── Render Body ── */
function renderBody(){
  const wgtStatus = document.getElementById('selWgtStatus').value;
  const showWgt100 = document.getElementById('cbCommonTouch').checked;
  let filtered = rows;
  if(wgtStatus==='get')  filtered = rows.filter(r=> r.wgtstatus==='get');
  if(wgtStatus==='give') filtered = rows.filter(r=> r.wgtstatus==='give');

  const tbody = document.getElementById('tbody');
  const colSpan = showWgt100 ? cols.length : cols.length - 1;
  if(!filtered.length){
    tbody.innerHTML='<tr><td colspan="'+colSpan+'" style="text-align:center;padding:40px;color:#94a3b8">No data found</td></tr>';
    renderSummary(filtered);
    return;
  }
  let html='';
  filtered.forEach(r=>{
    html+='<tr>';
    html+='<td>'+esc(r.code)+'</td>';
    html+='<td>'+esc(r.name)+'</td>';
    html+='<td>'+(r.lastrdt||'-')+'</td>';
    html+='<td>'+(r.lastdt||'-')+'</td>';
    html+='<td class="num"><b>'+nf3(r.weightbal)+'</b></td>';
    if(showWgt100) html+='<td class="num">'+nf3(r.wgt100bal)+'</td>';
    html+='<td>'+statusTag(r.wgtstatus)+'</td>';
    html+='<td class="num"><b>'+nf(r.tbal)+'</b></td>';
    html+='<td>'+statusTag(r.amtstatus)+'</td>';
    html+='</tr>';
  });
  tbody.innerHTML=html;
  renderSummary(filtered);
}

/* ── Summary (matches PB trailer band) ── */
function renderSummary(filtered){
  const t = totals;
  const rrate = parseFloat(document.getElementById('inpTouch').value) || 0;
  let html = '<div class="sum-row">';
  html += '<span>Rows: <b>'+(filtered||rows).length+'</b></span>';
  html += '<span>To Give Wgt: <b class="give">'+nf3(t.togivewgt)+'</b></span>';
  html += '<span>To Get Wgt: <b class="get">'+nf3(t.togetwgt)+'</b></span>';
  html += '<span>Net Wgt: <b>'+nf3(t.netwgt)+'</b> '+(t.netwgtstatus||'')+'</span>';
  html += '<span>Wgt 100 Bal: <b>'+nf3(t.wgt100bal)+'</b></span>';
  html += '</div><div class="sum-row">';
  html += '<span>To Give Amt: <b class="give">'+nf(t.togivamt)+'</b></span>';
  html += '<span>To Get Amt: <b class="get">'+nf(t.togetamt)+'</b></span>';
  html += '<span>Net Amt: <b>'+nf(t.netamt)+'</b> '+(t.netamtstatus||'')+'</span>';
  if(t.grandnetwgt > 0) html += '<span>Grand Net Wgt: <b>'+nf3(t.grandnetwgt)+'</b></span>';
  html += '</div>';
  document.getElementById('summary').innerHTML = html;
}

/* ── Update title based on ctype ── */
function updateTitle(){
  const ct = document.getElementById('selCtype').value;
  const titles = {G:'Goldsmith Account Summary', J:'Jewellery Account Summary', C:'Party Deposit Account Summary'};
  const t = titles[ct] || 'All Party Account Summary';
  document.getElementById('rptTitle').textContent = t;
  document.title = t;
}

/* ── Toggle touch input ── */
function toggleTouchInput(){
  const isStock = document.getElementById('selRtype').value === 'Stock';
  document.getElementById('grpTouch').style.display = isStock ? 'flex' : 'none';
}

/* ── Load data ── */
function loadData(){
  const asDate  = document.getElementById('asDate').value;
  const ctype   = document.getElementById('selCtype').value;
  const metal   = document.getElementById('selMetal').value;
  const group   = document.getElementById('selGroup').value;
  const balOnly = document.getElementById('cbBalOnly').checked ? '1' : '0';
  const rtype   = document.getElementById('selRtype').value;
  const rtouch  = document.getElementById('inpTouch').value || '0';

  updateTitle();

  const params = new URLSearchParams({date:asDate, ctype, metal, group, balonly:balOnly, rlevel:RLEVEL, rtype, rtouch, rrate:'0'});
  document.getElementById('subHeader').textContent = 'Loading...';
  const colSpan = document.getElementById('cbCommonTouch').checked ? cols.length : cols.length - 1;
  document.getElementById('tbody').innerHTML='<tr><td colspan="'+colSpan+'" style="text-align:center;padding:30px;color:#94a3b8">Loading...</td></tr>';

  fetch(SITE+'/api/smith-wa-summary/data?'+params.toString())
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
      const ct = document.getElementById('selCtype').value;
      const metalLbl = document.getElementById('selMetal').value==='S'?'Silver':'Gold';
      document.getElementById('subHeader').textContent =
        (rows.length)+' accounts loaded | As on: '+asDate+' | Metal: '+metalLbl+
        (ct?' | CType: '+({G:'Goldsmith',J:'Jewellery',C:'Deposit'}[ct]||ct):'')+
        (rtype==='Stock'?' | Stock Touch: '+rtouch:'');
    })
    .catch(()=> toast('Network error',false));
}

/* ── Save As CSV ── */
function saveAsCsv(){
  if(!rows.length){ toast('No data to export',false); return; }
  const showWgt100 = document.getElementById('cbCommonTouch').checked;
  const activeCols = cols.filter(c=> c.key!=='wgt100bal' || showWgt100);
  let csv = activeCols.map(c=>'"'+c.label+'"').join(',')+'\n';
  rows.forEach(r=>{
    csv += activeCols.map(c=>{
      let v = r[c.key];
      if(c.key==='wgtstatus' || c.key==='amtstatus') v = v==='give'?'To Give':(v==='get'?'To Get':'');
      if(v==null) v='';
      return '"'+String(v).replace(/"/g,'""')+'"';
    }).join(',')+'\n';
  });
  const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'smith_wa_summary_'+document.getElementById('asDate').value+'.csv';
  a.click();
}

/* ── Events ── */
document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = () => window.print();
document.getElementById('btnSaveAs').onclick = saveAsCsv;
document.getElementById('btnExit').onclick = () => { if(window.parent!==window) window.parent.postMessage({action:'closeModule'},'*'); else window.close(); };
document.getElementById('selSort').onchange = () => { sortCol=document.getElementById('selSort').value; sortDir=1; doSort(); renderHead(); renderBody(); };
document.getElementById('selWgtStatus').onchange = renderBody;
document.getElementById('selCtype').onchange = updateTitle;
document.getElementById('selRtype').onchange = toggleTouchInput;
document.getElementById('cbCommonTouch').onchange = () => { renderHead(); renderBody(); };

document.addEventListener('keydown', e=>{
  if(e.key==='F5'||e.key==='Enter'){ e.preventDefault(); loadData(); }
});

renderHead();
</script>
</body>
</html>
