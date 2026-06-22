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
/* Two-row header */
th{position:sticky;background:#f1f5f9;border-bottom:1px solid #e2e8f0;padding:4px 6px;font-size:15px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:center;white-space:nowrap;z-index:2}
th.top{top:0;border-bottom:1px solid #cbd5e1;background:#e2e8f0}
th.sub{top:26px;background:#f1f5f9;border-bottom:2px solid #e2e8f0;cursor:pointer;user-select:none}
th.sub:hover{background:#dbeafe}
th.num{text-align:right}
th .arr{font-size:8px;margin-left:2px;opacity:.5}
td{padding:4px 6px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
td.name-col{max-width:180px;overflow:hidden;text-overflow:ellipsis}
tr:hover td{background:#f8fafc}
tr.neg td.hl{color:#dc2626;font-weight:600}
tr.pos td.hl{color:#16a34a;font-weight:600}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:17px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:14px;flex-wrap:wrap;font-size:17px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}
.summary .sub-tot{margin-left:8px;padding-left:8px;border-left:1px solid #e2e8f0}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:17px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

@media print{.toolbar,.summary,.sub-header{display:none !important}.grid-wrap{overflow:visible !important}body{height:auto;overflow:visible}th{position:static !important}}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>

<div class="toolbar">
  <h1 id="rptTitle">{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:120px"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:120px"></div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Group</span>
    <select id="selGroup" class="tb-select" style="width:110px">
      <option value="">All</option>
      @foreach($groups as $g)
      <option value="{{ $g['code'] }}">{{ $g['code'] }} - {{ $g['name'] }}</option>
      @endforeach
    </select>
  </div>
  <label class="tb-cb"><input type="checkbox" id="cbIwgtClBal"> Based Issu.wgt Cl.Bal</label>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Filter</span>
    <select id="selWgtStatus" class="tb-select" style="width:120px">
      <option value="">All</option>
      <option value="get">Only To Get</option>
      <option value="give">Only To Give</option>
      <option value="withbal">Only With Bal</option>
    </select>
  </div>
  <div class="f-group"><span class="tb-lbl">Sort</span>
    <select id="selSort" class="tb-select" style="width:120px">
      <option value="name">Name</option>
      <option value="code">Code</option>
      <option value="clwgt">Weight Bal</option>
      <option value="clamt">Amount Bal</option>
      <option value="issuedwgt">Issued Wgt</option>
      <option value="rcvdwgt">Rcvd Wgt</option>
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
    <thead id="thead">
      <tr>
        <th class="top" rowspan="2" style="text-align:left;top:0">Code</th>
        <th class="top" rowspan="2" style="text-align:left;top:0">Name</th>
        <th class="top" colspan="2" style="top:0">Opening Balance</th>
        <th class="top" colspan="2" style="top:0">Issued</th>
        <th class="top" colspan="2" style="top:0">Received</th>
        <th class="top" rowspan="2" style="top:0">Anal Weight</th>
        <th class="top" colspan="2" style="top:0">Closing Balance</th>
      </tr>
      <tr>
        <th class="sub num">Weight</th><th class="sub num">Amount</th>
        <th class="sub num">Weight</th><th class="sub num">Amount</th>
        <th class="sub num">Weight</th><th class="sub num">Amount</th>
        <th class="sub num">Weight</th><th class="sub num">Amount</th>
      </tr>
    </thead>
    <tbody id="tbody"><tr><td colspan="11" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
  </table>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<script>
var SITE   = @json(request()->root());
var RLEVEL = {{ $rlevel }};
var CTYPE  = @json($ctype);
var allRows = [], sortCol = 'name', sortDir = 1;

function nf(n,d){ d=d||2; return Number(n||0).toFixed(d); }
function nf3(n){ return Number(n||0).toFixed(3); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,function(m){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]}); }
function toast(msg,ok){
  var t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(function(){t.style.display='none'},3000);
}

/* ── Sort ── */
function doSort(){
  allRows.sort(function(a,b){
    var va=a[sortCol], vb=b[sortCol];
    if(typeof va==='string') va=(va||'').toLowerCase();
    if(typeof vb==='string') vb=(vb||'').toLowerCase();
    if(va==null) va=''; if(vb==null) vb='';
    if(va<vb) return -sortDir;
    if(va>vb) return sortDir;
    return 0;
  });
}

/* ── Client-side filter ── */
function getFiltered(){
  var ws = document.getElementById('selWgtStatus').value;
  if(ws==='get')     return allRows.filter(function(r){ return r.clwgt < 0; });
  if(ws==='give')    return allRows.filter(function(r){ return r.clwgt > 0; });
  if(ws==='withbal') return allRows.filter(function(r){ return Math.abs(r.clwgt) > 0.001; });
  return allRows;
}

/* ── Render Body ── */
function renderBody(){
  var filtered = getFiltered();
  var tbody = document.getElementById('tbody');
  if(!filtered.length){
    tbody.innerHTML='<tr><td colspan="11" style="text-align:center;padding:40px;color:#94a3b8">No data found</td></tr>';
    renderSummary(filtered); return;
  }
  var html='';
  filtered.forEach(function(r){
    var cls = r.clwgt > 0.001 ? 'pos' : (r.clwgt < -0.001 ? 'neg' : '');
    html+='<tr class="'+cls+'">';
    html+='<td>'+esc(r.code)+'</td>';
    html+='<td class="name-col">'+esc(r.name)+'</td>';
    html+='<td class="num">'+nf3(r.opwgt)+'</td>';
    html+='<td class="num">'+nf(r.opamt)+'</td>';
    html+='<td class="num">'+nf3(r.issuedwgt)+'</td>';
    html+='<td class="num">'+nf(r.amtdebit)+'</td>';
    html+='<td class="num">'+nf3(r.rcvdwgt)+'</td>';
    html+='<td class="num">'+nf(r.amtcredit)+'</td>';
    html+='<td class="num">'+nf3(r.wgtdiff)+'</td>';
    html+='<td class="num hl"><b>'+nf3(r.clwgt)+'</b></td>';
    html+='<td class="num hl"><b>'+nf(r.clamt)+'</b></td>';
    html+='</tr>';
  });
  tbody.innerHTML=html;
  renderSummary(filtered);
}

/* ── Summary ── */
function renderSummary(filtered){
  var tw={opwgt:0,issuedwgt:0,amtdebit:0,rcvdwgt:0,amtcredit:0,wgtdiff:0,clwgt:0};
  var toRcv=0, toGive=0, clToRcv=0, clToGive=0;
  filtered.forEach(function(r){
    tw.opwgt+=r.opwgt||0; tw.issuedwgt+=r.issuedwgt||0; tw.amtdebit+=r.amtdebit||0;
    tw.rcvdwgt+=r.rcvdwgt||0; tw.amtcredit+=r.amtcredit||0; tw.wgtdiff+=r.wgtdiff||0; tw.clwgt+=r.clwgt||0;
    if(r.opwgt<0) toRcv+=Math.abs(r.opwgt); else if(r.opwgt>0) toGive+=r.opwgt;
    if(r.clwgt<0) clToRcv+=Math.abs(r.clwgt); else if(r.clwgt>0) clToGive+=r.clwgt;
  });
  var dir = tw.opwgt > 0 ? 'To Give' : 'To Receive';
  var clDir = tw.clwgt > 0 ? 'To Give' : 'To Receive';
  document.getElementById('summary').innerHTML =
    '<span>Rows: <b>'+filtered.length+'</b></span>'+
    '<span>Op.Wgt: <b>'+nf3(Math.abs(tw.opwgt))+'</b> '+dir+'</span>'+
    '<span>Issued: <b>'+nf3(tw.issuedwgt)+'</b></span>'+
    '<span>Rcvd: <b>'+nf3(tw.rcvdwgt)+'</b></span>'+
    '<span>Anal: <b>'+nf3(tw.wgtdiff)+'</b></span>'+
    '<span>Cl.Wgt: <b>'+nf3(Math.abs(tw.clwgt))+'</b> '+clDir+'</span>'+
    '<span class="sub-tot">To Receive: <b>'+nf3(clToRcv)+'</b></span>'+
    '<span>To Give: <b>'+nf3(clToGive)+'</b></span>';
}

/* ── Load data ── */
function loadData(){
  var d1=document.getElementById('date1').value;
  var d2=document.getElementById('date2').value;
  var group=document.getElementById('selGroup').value;
  var method=document.getElementById('cbIwgtClBal').checked?'1':'2';

  var params=new URLSearchParams({date1:d1,date2:d2,ctype:CTYPE,group:group,method:method,rlevel:RLEVEL});
  document.getElementById('subHeader').textContent='Loading...';
  document.getElementById('tbody').innerHTML='<tr><td colspan="11" style="text-align:center;padding:30px;color:#94a3b8">Loading...</td></tr>';

  fetch(SITE+'/api/smith-wa-analysis/data?'+params.toString())
    .then(function(r){return r.json()})
    .then(function(d){
      if(!d.ok){toast(d.message||'Failed',false);return}
      allRows=d.rows||[];
      sortCol=document.getElementById('selSort').value;
      sortDir=1;
      doSort();
      renderBody();
      var lbl=CTYPE==='J'?'Jewellery':'Goldsmith';
      document.getElementById('subHeader').textContent=allRows.length+' accounts | '+lbl+' | '+d1+' to '+d2;
    })
    .catch(function(){toast('Network error',false)});
}

/* ── Save As CSV ── */
function saveAsCsv(){
  var filtered=getFiltered();
  if(!filtered.length){toast('No data',false);return}
  var hdr=['Code','Name','Op.Wgt','Op.Amt','Issued Wgt','Issued Amt','Rcvd Wgt','Rcvd Amt','Anal Wgt','Cl.Wgt','Cl.Amt'];
  var keys=['code','name','opwgt','opamt','issuedwgt','amtdebit','rcvdwgt','amtcredit','wgtdiff','clwgt','clamt'];
  var csv=hdr.map(function(h){return'"'+h+'"'}).join(',')+'\n';
  filtered.forEach(function(r){
    csv+=keys.map(function(k){var v=r[k];if(v==null)v='';return'"'+String(v).replace(/"/g,'""')+'"'}).join(',')+'\n';
  });
  var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
  var a=document.createElement('a');
  a.href=URL.createObjectURL(blob);
  a.download='smith_analysis_'+document.getElementById('date1').value+'_'+document.getElementById('date2').value+'.csv';
  a.click();
}

/* ── Events ── */
document.getElementById('btnShow').onclick=loadData;
document.getElementById('btnPrint').onclick=function(){window.print()};
document.getElementById('btnSaveAs').onclick=saveAsCsv;
document.getElementById('btnExit').onclick=function(){if(window.parent!==window)window.parent.postMessage({action:'closeModule'},'*');else window.close()};
document.getElementById('selSort').onchange=function(){sortCol=this.value;sortDir=1;doSort();renderBody()};
document.getElementById('selWgtStatus').onchange=renderBody;

document.addEventListener('keydown',function(e){
  if(e.key==='F5'||e.key==='Enter'){e.preventDefault();loadData()}
});
</script>
</body>
</html>
