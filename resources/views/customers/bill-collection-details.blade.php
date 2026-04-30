<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>@include('customers._style')
@include('partials.print-layout-head')
</head><body><div class="wrap">
<h1>{{ $title }}</h1>
<div class="toolbar">
  <div class="field"><label>From Date</label><input type="date" id="date1" value="{{ $date1 }}"></div>
  <div class="field"><label>To Date</label><input type="date" id="date2" value="{{ $date2 }}"></div>
  <div class="field wide"><label>Customer / Bill Search</label><input type="text" id="name" placeholder="Name / Code / Bill No…"></div>
  <div class="field" style="justify-content:flex-end">
    <button class="primary" onclick="load()">Show</button>
    <button onclick="printPage()">Print</button>
  </div>
</div>
<div class="pills" id="pills" style="display:none">
  <span class="pill" id="p_count"></span>
  <span class="pill" id="p_tran"></span>
  <span class="pill green" id="p_coll"></span>
  <span class="pill" id="p_disc"></span>
</div>
<div class="table-wrap"><table id="tbl">
  <thead><tr>
    <th>#</th><th>Date</th><th>Code</th><th>Customer</th><th>Bill No</th>
    <th class="num">Bill Amt</th><th class="num">Collected</th><th class="num">Discount</th>
    <th>Due Date</th><th>CB Code</th>
  </tr></thead>
  <tbody id="tbody"><tr><td colspan="10" class="empty">Press Show to load data.</td></tr></tbody>
</table></div>
</div>
<script>
function fmt(n){return parseFloat(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function fmtDate(d){if(!d)return'';var p=d.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}
function load(){
  var tbody=document.getElementById('tbody');
  tbody.innerHTML='<tr><td colspan="10" class="empty">Loading…</td></tr>';
  document.getElementById('pills').style.display='none';
  fetch('/api/customers/bill-collection-details?date1='+document.getElementById('date1').value
    +'&date2='+document.getElementById('date2').value
    +'&name='+encodeURIComponent(document.getElementById('name').value))
  .then(r=>r.json()).then(d=>{
    if(!d.ok||!d.rows||!d.rows.length){tbody.innerHTML='<tr><td colspan="10" class="empty">No records found.</td></tr>';return;}
    tbody.innerHTML=d.rows.map((r,i)=>`<tr>
      <td>${i+1}</td><td>${fmtDate(r.tdate)}</td><td>${r.code}</td><td>${r.cname||''}</td>
      <td>${r.billno||''}</td>
      <td class="num">${fmt(r.tranamt)}</td><td class="num">${fmt(r.amount)}</td>
      <td class="num">${fmt(r.discount)}</td>
      <td>${fmtDate(r.duedate)}</td><td>${r.cbcode||''}</td>
    </tr>`).join('')
    +'<tr class="tfoot"><td colspan="5">Total ('+d.totals.count+')</td>'
    +'<td class="num">'+fmt(d.totals.tranamt)+'</td>'
    +'<td class="num">'+fmt(d.totals.amount)+'</td>'
    +'<td class="num">'+fmt(d.totals.discount)+'</td>'
    +'<td colspan="2"></td></tr>';
    var t=d.totals;
    document.getElementById('p_count').textContent='Records: '+t.count;
    document.getElementById('p_tran').textContent='Bill Amt: ₹'+fmt(t.tranamt);
    document.getElementById('p_coll').textContent='Collected: ₹'+fmt(t.amount);
    document.getElementById('p_disc').textContent='Discount: ₹'+fmt(t.discount);
    document.getElementById('pills').style.display='flex';
  });
}
function printPage(){window.print();}
document.getElementById('name').addEventListener('keydown',e=>{if(e.key==='Enter')load();});
</script></body></html>
