<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
@include('customers._style')
<style>
.overdue td{background:#fff0f0;}
.overdue td.cname{color:#a72c2c;font-weight:700;}
.due-today td{background:#fffbe6;}
.tg{color:#2457a6;font-weight:700;}
.tr{color:#a72c2c;font-weight:700;}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head><body><div class="wrap">
<h1>{{ $title }}</h1>
<div class="toolbar">
  <div class="field"><label>From Date</label><input type="date" id="date1" value="{{ $date1 }}"></div>
  <div class="field"><label>To Date</label><input type="date" id="date2" value="{{ $date2 }}"></div>
  <div class="field">
    <label>Sort By</label>
    <select id="filter">
      <option value="overdue_first">Overdue First</option>
      <option value="by_name">Name</option>
      <option value="by_balance">Balance</option>
    </select>
  </div>
  <div class="field wide"><label>Supplier Search</label><input type="text" id="name" placeholder="Name / Code…"></div>
  <div class="field">
    <label>&nbsp;</label>
    <label style="height:32px;display:flex;align-items:center;gap:6px;font-weight:400;color:#1f2937;">
      <input type="checkbox" id="range_filter"> Filter by date range
    </label>
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
    <th>#</th><th>Code</th><th>Supplier Name</th><th>Mobile</th><th>Telephone</th>
    <th class="num">Balance</th><th>Status</th><th>Due Date</th><th>Overdue?</th>
  </tr></thead>
  <tbody id="tbody"><tr><td colspan="9" class="empty">Press Show to load data.</td></tr></tbody>
</table></div>
</div>
<script>
function fmt(n){return parseFloat(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function fmtDate(d){if(!d)return'';var p=d.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}
function load(){
  var tbody=document.getElementById('tbody');
  tbody.innerHTML='<tr><td colspan="9" class="empty">Loading…</td></tr>';
  document.getElementById('pills').style.display='none';
  var today=new Date().toISOString().slice(0,10);
  fetch('/api/suppliers/duedate-report?date1='+document.getElementById('date1').value
    +'&date2='+document.getElementById('date2').value
    +'&filter='+document.getElementById('filter').value
    +'&range_filter='+(document.getElementById('range_filter').checked?'1':'0')
    +'&name='+encodeURIComponent(document.getElementById('name').value))
  .then(r=>r.json()).then(d=>{
    if(!d.ok||!d.rows||!d.rows.length){tbody.innerHTML='<tr><td colspan="9" class="empty">No records found.</td></tr>';return;}
    tbody.innerHTML=d.rows.map((r,i)=>{
      var cls=r.overdue?'overdue':(r.duedate===today?'due-today':'');
      return `<tr class="${cls}">
        <td>${i+1}</td><td>${r.accode}</td><td class="cname">${r.cname}</td>
        <td>${r.mobile||''}</td><td>${r.telephone||''}</td>
        <td class="num">${fmt(r.bal_abs)}</td>
        <td><span class="${r.status.toLowerCase()}">${r.status}</span></td>
        <td>${fmtDate(r.duedate)}</td>
        <td>${r.overdue?'<span class="tr">OVERDUE</span>':''}</td>
      </tr>`;
    }).join('')
    +'<tr class="tfoot"><td colspan="5">Total ('+d.totals.count+')</td>'
    +'<td class="num">'+fmt(d.totals.tg+d.totals.tr)+'</td>'
    +'<td colspan="3"></td></tr>';
    var t=d.totals;
    document.getElementById('p_count').textContent='Suppliers: '+t.count;
    document.getElementById('p_tg').textContent='To Give (Payable): ₹'+fmt(t.tg);
    document.getElementById('p_tr').textContent='To Receive: ₹'+fmt(t.tr);
    document.getElementById('pills').style.display='flex';
  });
}
document.getElementById('name').addEventListener('keydown',e=>{if(e.key==='Enter')load();});
</script></body></html>
