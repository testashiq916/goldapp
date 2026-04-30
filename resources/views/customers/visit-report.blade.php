<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>@include('customers._style')
@include('partials.print-layout-head')
</head><body><div class="wrap">
<h1>{{ $title }}</h1>
<div class="toolbar">
  <div class="field"><label>From Date</label><input type="date" id="date1" value="{{ $date1 }}"></div>
  <div class="field"><label>To Date</label><input type="date" id="date2" value="{{ $date2 }}"></div>
  <div class="field wide"><label>Search</label><input type="text" id="search" placeholder="Description…"></div>
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
    <th>#</th><th>Date &amp; Time</th><th>Title / Subject</th><th>Description</th>
  </tr></thead>
  <tbody id="tbody"><tr><td colspan="4" class="empty">Press Show to load data.</td></tr></tbody>
</table></div>
</div>
<script>
function load(){
  var tbody=document.getElementById('tbody');
  tbody.innerHTML='<tr><td colspan="4" class="empty">Loading…</td></tr>';
  document.getElementById('pills').style.display='none';
  fetch('/api/customers/visit-report?date1='+document.getElementById('date1').value
    +'&date2='+document.getElementById('date2').value
    +'&search='+encodeURIComponent(document.getElementById('search').value))
  .then(r=>r.json()).then(d=>{
    if(!d.ok||!d.rows||!d.rows.length){tbody.innerHTML='<tr><td colspan="4" class="empty">No records found.</td></tr>';return;}
    tbody.innerHTML=d.rows.map((r,i)=>`<tr>
      <td>${i+1}</td>
      <td style="white-space:nowrap">${r.tdatetime||r.adate||''}</td>
      <td>${r.adesc||''}</td>
      <td style="max-width:400px">${r.description||''}</td>
    </tr>`).join('');
    document.getElementById('p_count').textContent='Records: '+d.count;
    document.getElementById('pills').style.display='flex';
  });
}
function printPage(){window.print();}
document.getElementById('search').addEventListener('keydown',e=>{if(e.key==='Enter')load();});
</script></body></html>
