<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>@include('customers._style')
@include('partials.print-layout-head')
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head><body><div class="wrap">
<h1>{{ $title }}</h1>
<div class="toolbar">
  <div class="field"><label>From Date</label><input type="date" id="date1" value="{{ $date1 }}"></div>
  <div class="field"><label>To Date</label><input type="date" id="date2" value="{{ $date2 }}"></div>
  <div class="field wide"><label>Customer Search</label><input type="text" id="name" placeholder="Name / Code…"></div>
  <div class="field" style="justify-content:flex-end">
    <button class="primary" onclick="load()">Show</button>
    <button onclick="printPage()">Print</button>
  </div>
</div>
<div class="pills" id="pills" style="display:none">
  <span class="pill" id="p_count"></span>
</div>
<div class="table-wrap"><table id="tbl">
  <thead><tr>
    <th>#</th><th>Date</th><th>Code</th><th>Customer Name</th><th>Mobile</th>
    <th>Time In</th><th>Time Out</th>
  </tr></thead>
  <tbody id="tbody"><tr><td colspan="7" class="empty">Press Show to load data.</td></tr></tbody>
</table></div>
</div>
<script>
function fmtDate(d){if(!d)return'';var p=d.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}
function load(){
  var tbody=document.getElementById('tbody');
  tbody.innerHTML='<tr><td colspan="7" class="empty">Loading…</td></tr>';
  document.getElementById('pills').style.display='none';
  fetch('/api/customers/party-history?date1='+document.getElementById('date1').value
    +'&date2='+document.getElementById('date2').value
    +'&name='+encodeURIComponent(document.getElementById('name').value))
  .then(r=>r.json()).then(d=>{
    if(!d.ok||!d.rows||!d.rows.length){tbody.innerHTML='<tr><td colspan="7" class="empty">No records found.</td></tr>';return;}
    tbody.innerHTML=d.rows.map((r,i)=>`<tr>
      <td>${i+1}</td><td>${fmtDate(r.tdate)}</td><td>${r.code}</td>
      <td>${r.cname||r.code}</td><td>${r.mobile||''}</td>
      <td>${r.time1||''}</td><td>${r.time2||''}</td>
    </tr>`).join('');
    document.getElementById('p_count').textContent='Records: '+d.count;
    document.getElementById('pills').style.display='flex';
  });
}
function printPage(){window.print();}
document.getElementById('name').addEventListener('keydown',e=>{if(e.key==='Enter')load();});
</script></body></html>
