<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>@include('customers._style')
@include('partials.print-layout-head')
</head><body><div class="wrap">
<h1>{{ $title }}</h1>
<div class="toolbar">
  <div class="field wide"><label>Search (Name / Code / Mobile)</label><input type="text" id="name" placeholder="Search…"></div>
  <div class="field"><label>Group</label>
    <select id="grp"><option value="">All Groups</option></select>
  </div>
  <div class="field"><label>Route</label>
    <select id="route"><option value="">All Routes</option></select>
  </div>
  <div class="field" style="justify-content:flex-end">
    <button class="primary" onclick="load()">Show</button>
    <button onclick="printPage()">Print</button>
  </div>
</div>
<div class="pills" id="pills" style="display:none">
  <span class="pill" id="p_count"></span>
  <span class="pill red" id="p_tr"></span>
  <span class="pill" id="p_tg"></span>
</div>
<div class="table-wrap"><table id="tbl">
  <thead><tr>
    <th>#</th><th>Code</th><th>Name</th><th>Mobile</th><th>Phone</th>
    <th>Address</th><th>Group</th><th>Route</th>
    <th class="num">Balance</th><th>Status</th><th>Due Date</th>
  </tr></thead>
  <tbody id="tbody"><tr><td colspan="11" class="empty">Press Show to load data.</td></tr></tbody>
</table></div>
</div>
<script>
function fmt(n){return parseFloat(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function fmtDate(d){if(!d)return'';var p=d.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}
var lookupsDone=false;
function loadLookups(){
  if(lookupsDone)return;
  fetch('/api/customers/list?_lookups=1')
  .then(r=>r.json()).then(d=>{
    var gs=document.getElementById('grp'),rs=document.getElementById('route');
    (d.groups||[]).forEach(g=>{var o=document.createElement('option');o.value=g.code;o.textContent=g.name;gs.appendChild(o);});
    (d.routes||[]).forEach(r=>{var o=document.createElement('option');o.value=r.code;o.textContent=r.name;rs.appendChild(o);});
    lookupsDone=true;
  });
}
function load(){
  var tbody=document.getElementById('tbody');
  tbody.innerHTML='<tr><td colspan="11" class="empty">Loading…</td></tr>';
  document.getElementById('pills').style.display='none';
  fetch('/api/customers/list?name='+encodeURIComponent(document.getElementById('name').value)
    +'&grp='+encodeURIComponent(document.getElementById('grp').value)
    +'&route='+encodeURIComponent(document.getElementById('route').value))
  .then(r=>r.json()).then(d=>{
    if(!d.ok||!d.rows||!d.rows.length){tbody.innerHTML='<tr><td colspan="11" class="empty">No customers found.</td></tr>';return;}
    // populate lookups from first data load
    if(!lookupsDone&&d.groups){
      var gs=document.getElementById('grp'),rs=document.getElementById('route');
      (d.groups||[]).forEach(g=>{var o=document.createElement('option');o.value=g.code;o.textContent=g.name;gs.appendChild(o);});
      (d.routes||[]).forEach(r=>{var o=document.createElement('option');o.value=r.code;o.textContent=r.name;rs.appendChild(o);});
      lookupsDone=true;
    }
    var tr=0,tg=0;
    tbody.innerHTML=d.rows.map((r,i)=>{
      if(r.status==='TR')tr+=r.netbal<0?Math.abs(r.netbal):0;
      if(r.status==='TG')tg+=r.netbal>0?r.netbal:0;
      var addr=[r.addr1,r.addr2,r.city].filter(Boolean).join(', ');
      return `<tr>
        <td>${i+1}</td><td>${r.code}</td><td>${r.name}</td>
        <td>${r.mobile||''}</td><td>${r.telephone||''}</td>
        <td style="max-width:200px">${addr}</td>
        <td>${r.grp||''}</td><td>${r.route||''}</td>
        <td class="num${r.status==='TR'?' bal':''}">${r.netbal!==0?fmt(Math.abs(r.netbal)):''}</td>
        <td><span style="color:${r.status==='TR'?'#a72c2c':'#2457a6'};font-weight:700">${r.status}</span></td>
        <td>${fmtDate(r.duedate)}</td>
      </tr>`;
    }).join('');
    document.getElementById('p_count').textContent='Total: '+d.count+' customers';
    document.getElementById('p_tr').textContent='To Receive: ₹'+fmt(tr);
    document.getElementById('p_tg').textContent='To Give: ₹'+fmt(tg);
    document.getElementById('pills').style.display='flex';
  });
}
function printPage(){window.print();}
document.getElementById('name').addEventListener('keydown',e=>{if(e.key==='Enter')load();});
window.addEventListener('DOMContentLoaded',()=>load());
</script></body></html>
