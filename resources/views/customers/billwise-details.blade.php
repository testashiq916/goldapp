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
  <div class="field wide"><label>Customer / Bill Search</label><input type="text" id="name" placeholder="Name / Bill No…"></div>
  <div class="field" style="justify-content:flex-end">
    <button class="primary" onclick="load()">Show</button>
    <button onclick="printPage()">Print</button>
  </div>
</div>
<div class="pills" id="pills" style="display:none">
  <span class="pill" id="p_count"></span>
  <span class="pill" id="p_bill"></span>
  <span class="pill" id="p_net"></span>
  <span class="pill green" id="p_rcvd"></span>
  <span class="pill red" id="p_bal"></span>
</div>
<div class="table-wrap"><table id="tbl">
  <thead><tr>
    <th>#</th><th>Bill No</th><th>Date</th><th>Customer</th><th>Mobile</th>
    <th class="num">Bill Amt</th><th class="num">Discount</th><th class="num">Net Amt</th>
    <th class="num">Received</th><th class="num">Balance</th><th>Due Date</th>
  </tr></thead>
  <tbody id="tbody"><tr><td colspan="11" class="empty">Press Show to load data.</td></tr></tbody>
</table></div>
</div>
<script>
function fmt(n){return parseFloat(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function fmtDate(d){if(!d)return'';var p=d.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}
function load(){
  var tbody=document.getElementById('tbody');
  tbody.innerHTML='<tr><td colspan="11" class="empty">Loading…</td></tr>';
  document.getElementById('pills').style.display='none';
  fetch('/api/customers/billwise-details?date1='+document.getElementById('date1').value
    +'&date2='+document.getElementById('date2').value
    +'&name='+encodeURIComponent(document.getElementById('name').value))
  .then(r=>r.json()).then(d=>{
    if(!d.ok||!d.rows||!d.rows.length){tbody.innerHTML='<tr><td colspan="11" class="empty">No records found.</td></tr>';return;}
    var today=new Date().toISOString().slice(0,10);
    tbody.innerHTML=d.rows.map((r,i)=>`<tr class="${r.bal>0.005&&r.duedate&&r.duedate<today?'overdue':''}">
      <td>${i+1}</td><td>${r.billno}</td><td>${fmtDate(r.tdate)}</td>
      <td>${r.cname}</td><td>${r.mobile||''}</td>
      <td class="num">${fmt(r.billamt)}</td><td class="num">${fmt(r.discount)}</td>
      <td class="num">${fmt(r.netamt)}</td><td class="num">${fmt(r.ramt)}</td>
      <td class="num bal">${fmt(r.bal)}</td><td>${fmtDate(r.duedate)}</td>
    </tr>`).join('')
    +'<tr class="tfoot"><td colspan="5">Total ('+d.totals.count+')</td>'
    +'<td class="num">'+fmt(d.totals.billamt)+'</td><td></td>'
    +'<td class="num">'+fmt(d.totals.netamt)+'</td>'
    +'<td class="num">'+fmt(d.totals.ramt)+'</td>'
    +'<td class="num">'+fmt(d.totals.bal)+'</td><td></td></tr>';
    var t=d.totals;
    document.getElementById('p_count').textContent='Bills: '+t.count;
    document.getElementById('p_bill').textContent='Bill Amt: ₹'+fmt(t.billamt);
    document.getElementById('p_net').textContent='Net: ₹'+fmt(t.netamt);
    document.getElementById('p_rcvd').textContent='Received: ₹'+fmt(t.ramt);
    document.getElementById('p_bal').textContent='Balance: ₹'+fmt(t.bal);
    document.getElementById('pills').style.display='flex';
  });
}
function printPage(){window.print();}
document.getElementById('name').addEventListener('keydown',e=>{if(e.key==='Enter')load();});
</script></body></html>
