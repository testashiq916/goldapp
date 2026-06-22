<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
@include('customers._style')
<style>
.tg{color:#2457a6;font-weight:700;}
.tr{color:#a72c2c;font-weight:700;}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
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
    <button onclick="window.print()">Print</button>
  </div>
</div>
<div class="pills" id="pills" style="display:none">
  <span class="pill" id="p_count"></span>
  <span class="pill red" id="p_tg"></span>
  <span class="pill" id="p_tr"></span>
</div>
<div class="table-wrap"><table>
  <thead><tr>
    <th>#</th><th>Code</th><th>Name</th><th>Mobile</th><th>Phone</th>
    <th>Address</th><th>TIN/GST</th><th>Group</th>
    <th class="num">Balance</th><th>Status</th><th>Due Date</th>
  </tr></thead>
  <tbody id="tbody"><tr><td colspan="11" class="empty">Press Show to load data.</td></tr></tbody>
</table></div>
</div>
<script>
function fmt(n){return parseFloat(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function fmtDate(d){if(!d)return'';var p=d.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}
var lookupsDone=false;
function load(){
  var tbody=document.getElementById('tbody');
  tbody.innerHTML='<tr><td colspan="11" class="empty">Loading…</td></tr>';
  document.getElementById('pills').style.display='none';
  fetch('/api/suppliers/list?name='+encodeURIComponent(document.getElementById('name').value)
    +'&grp='+encodeURIComponent(document.getElementById('grp').value)
    +'&route='+encodeURIComponent(document.getElementById('route').value))
  .then(r=>r.json()).then(d=>{
    if(!d.ok||!d.rows||!d.rows.length){tbody.innerHTML='<tr><td colspan="11" class="empty">No suppliers found.</td></tr>';return;}
    if(!lookupsDone&&d.groups){
      var gs=document.getElementById('grp'),rs=document.getElementById('route');
      (d.groups||[]).forEach(g=>{var o=document.createElement('option');o.value=g.code;o.textContent=g.name;gs.appendChild(o);});
      (d.routes||[]).forEach(r=>{var o=document.createElement('option');o.value=r.code;o.textContent=r.name;rs.appendChild(o);});
      lookupsDone=true;
    }
    var tg=0,tr=0;
    tbody.innerHTML=d.rows.map((r,i)=>{
      if(r.status==='TG')tg+=Math.abs(r.netbal);
      if(r.status==='TR')tr+=Math.abs(r.netbal);
      var addr=[r.addr1,r.addr2,r.city].filter(Boolean).join(', ');
      return `<tr>
        <td>${i+1}</td><td>${r.code}</td><td>${r.name}</td>
        <td>${r.mobile||''}</td><td>${r.telephone||''}</td>
        <td style="max-width:200px">${addr}</td>
        <td>${r.tinno||''}</td><td>${r.grp||''}</td>
        <td class="num${r.status==='TG'?' bal':''}">${r.netbal!==0?fmt(Math.abs(r.netbal)):''}</td>
        <td><span class="${r.status.toLowerCase()}">${r.status}</span></td>
        <td>${fmtDate(r.duedate)}</td>
      </tr>`;
    }).join('');
    document.getElementById('p_count').textContent='Total: '+d.count+' suppliers';
    document.getElementById('p_tg').textContent='To Give (Payable): ₹'+fmt(tg);
    document.getElementById('p_tr').textContent='To Receive: ₹'+fmt(tr);
    document.getElementById('pills').style.display='flex';
  });
}
document.getElementById('name').addEventListener('keydown',e=>{if(e.key==='Enter')load();});
window.addEventListener('DOMContentLoaded',()=>load());
</script></body></html>
