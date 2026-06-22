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
  <div class="field wide"><label>Customer Search</label><input type="text" id="name" placeholder="Name…"></div>
  <div class="field" style="justify-content:flex-end">
    <button class="primary" onclick="load()">Show</button>
    <button onclick="printPage()">Print</button>
  </div>
</div>
<div class="pills" id="pills" style="display:none">
  <span class="pill" id="p_cust"></span>
  <span class="pill" id="p_bills"></span>
  <span class="pill" id="p_wgt"></span>
  <span class="pill" id="p_net"></span>
  <span class="pill green" id="p_rcvd"></span>
  <span class="pill red" id="p_bal"></span>
</div>
<div class="table-wrap"><table id="tbl">
  <thead><tr>
    <th>#</th><th>Code</th><th>Customer Name</th><th>Mobile</th>
    <th class="num">Bills</th><th class="num">Weight (g)</th>
    <th class="num">Net Amount</th><th class="num">Received</th><th class="num">Balance</th>
  </tr></thead>
  <tbody id="tbody"><tr><td colspan="9" class="empty">Press Show to load data.</td></tr></tbody>
</table></div>
</div>
<script>
function fmt(n){return parseFloat(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function load(){
  var tbody=document.getElementById('tbody');
  tbody.innerHTML='<tr><td colspan="9" class="empty">Loading…</td></tr>';
  document.getElementById('pills').style.display='none';
  fetch('/api/customers/sales-summary?date1='+document.getElementById('date1').value
    +'&date2='+document.getElementById('date2').value
    +'&name='+encodeURIComponent(document.getElementById('name').value))
  .then(r=>r.json()).then(d=>{
    if(!d.ok||!d.rows||!d.rows.length){tbody.innerHTML='<tr><td colspan="9" class="empty">No records found.</td></tr>';return;}
    tbody.innerHTML=d.rows.map((r,i)=>`<tr>
      <td>${i+1}</td><td>${r.custcode}</td><td>${r.cname}</td><td>${r.mobile||''}</td>
      <td class="num">${r.bill_count}</td>
      <td class="num">${fmt(r.total_wgt)}</td>
      <td class="num">${fmt(r.total_net)}</td>
      <td class="num">${fmt(r.total_rcvd)}</td>
      <td class="num bal">${fmt(r.total_bal)}</td>
    </tr>`).join('')
    +'<tr class="tfoot"><td colspan="4">Total ('+d.rows.length+')</td>'
    +'<td class="num">'+d.totals.bill_count+'</td>'
    +'<td class="num">'+fmt(d.totals.total_wgt)+'</td>'
    +'<td class="num">'+fmt(d.totals.total_net)+'</td>'
    +'<td class="num">'+fmt(d.totals.total_rcvd)+'</td>'
    +'<td class="num">'+fmt(d.totals.total_bal)+'</td></tr>';
    var t=d.totals;
    document.getElementById('p_cust').textContent='Customers: '+d.rows.length;
    document.getElementById('p_bills').textContent='Bills: '+t.bill_count;
    document.getElementById('p_wgt').textContent='Weight: '+fmt(t.total_wgt)+'g';
    document.getElementById('p_net').textContent='Net: ₹'+fmt(t.total_net);
    document.getElementById('p_rcvd').textContent='Received: ₹'+fmt(t.total_rcvd);
    document.getElementById('p_bal').textContent='Balance: ₹'+fmt(t.total_bal);
    document.getElementById('pills').style.display='flex';
  });
}
function printPage(){window.print();}
document.getElementById('name').addEventListener('keydown',e=>{if(e.key==='Enter')load();});
</script></body></html>
