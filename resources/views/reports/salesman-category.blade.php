<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title ?? 'Salesman Category Report' }}</title>
<style>
  :root{--green:#0f6b44;--line:#cfe0d4;--soft:#f3faf5;--text:#0b1f16;--muted:#607467}
  *{box-sizing:border-box}
  body{margin:0;background:#eef6ef;color:var(--text);font-family:Arial,Helvetica,sans-serif;font-size:20px}
  .page{padding:14px}
  .panel{background:white;border:1px solid var(--line);border-radius:8px;box-shadow:0 8px 24px rgba(23,70,43,.08);overflow:hidden}
  .head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#174d32;color:white}
  .head h1{margin:0;font-size:16px;font-weight:700;letter-spacing:0}
  .tools{display:flex;gap:8px;align-items:end;flex-wrap:wrap;padding:12px 14px;background:#f8fcf9;border-bottom:1px solid var(--line)}
  label{display:block;color:#31523d;font-weight:700;font-size:16px;margin-bottom:4px}
  input,select{height:32px;border:1px solid #bdd3c5;border-radius:6px;padding:4px 8px;background:white;font-size:18px}
  button{height:32px;border:1px solid #0f6b44;background:#0f6b44;color:white;border-radius:6px;padding:0 12px;font-weight:700;cursor:pointer;font-size:18px}
  button.secondary{background:white;color:#0f6b44}
  .summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:8px;padding:12px 14px;background:white}
  .card{border:1px solid var(--line);border-radius:7px;padding:9px;background:var(--soft)}
  .card .k{color:var(--muted);font-size:11px;font-weight:700}
  .card .v{font-size:16px;font-weight:800;margin-top:3px}
  .table-wrap{padding:0 14px 14px;overflow:auto}
  table{width:100%;border-collapse:collapse;min-width:1560px;background:white}
  th,td{border:1px solid var(--line);padding:7px 8px;white-space:nowrap}
  th{background:#e8f3eb;color:#174d32;text-align:left;font-size:13px}
  td.r,th.r{text-align:right}
  tr.total td{background:#f0f7f1;font-weight:800}
  .status{padding:16px;text-align:center;color:var(--muted)}
  @media print{.tools{display:none}.page{padding:0}.panel{box-shadow:none;border:0}.head{color:#000;background:#fff;border-bottom:1px solid #999}}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="page">
  <div class="panel">
    <div class="head">
      <h1>Salesman Category Report</h1>
      <div id="period"></div>
    </div>
    <div class="tools">
      <div><label>From</label><input type="date" id="date1" value="{{ $date1 }}"></div>
      <div><label>To</label><input type="date" id="date2" value="{{ $date2 }}"></div>
      <div><label>Salesman</label><select id="smcode"><option value="">All Salesman</option></select></div>
      <div><label>Control</label><input type="number" id="rlevel" value="{{ session('gilevel', 1) }}" min="1" style="width:80px"></div>
      <button id="showBtn">Show</button>
      <button class="secondary" onclick="window.print()">Print</button>
    </div>
    <div class="summary" id="summary"></div>
    <div class="table-wrap" id="result"><div class="status">Loading...</div></div>
  </div>
</div>
<script>
const API_LOOKUPS = @json(url('/api/salesman-category-report/lookups'));
const API_DATA = @json(url('/api/salesman-category-report/data'));

function money(v){ return Number(v || 0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function wgt(v){ return Number(v || 0).toLocaleString('en-IN',{minimumFractionDigits:3,maximumFractionDigits:3}); }
function qty(v){ return Number(v || 0).toLocaleString('en-IN',{maximumFractionDigits:0}); }
function esc(s){ return String(s ?? '').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

async function loadLookups(){
  const res = await fetch(API_LOOKUPS);
  const data = await res.json();
  const sm = document.getElementById('smcode');
  if(data.ok){
    sm.innerHTML = '<option value="">All Salesman</option>' + (data.salesmen || []).map(r =>
      `<option value="${esc(r.code)}">${esc(r.code)} - ${esc(r.name)}</option>`
    ).join('');
  }
}

function renderSummary(t){
  const cards = [
    ['Bills', qty(t.bills)],
    ['Gold Weight', wgt(t.gold_weight)],
    ['Gold Return Wgt', wgt(t.gold_return_weight)],
    ['Gold Net Wgt', wgt(t.gold_net_weight)],
    ['Diamond Amount', money(t.diamond_amount)],
    ['Diamond Return Amt', money(t.diamond_return_amount)],
    ['Diamond Net Amt', money(t.diamond_net_amount)],
    ['18K Weight', wgt(t.gold18_weight)],
    ['18K Return Wgt', wgt(t.gold18_return_weight)],
    ['18K Net Wgt', wgt(t.gold18_net_weight)],
    ['Silver Weight', wgt(t.silver_weight)],
    ['Silver Return Wgt', wgt(t.silver_return_weight)],
    ['Silver Net Wgt', wgt(t.silver_net_weight)],
    ['Total Amount', money(t.total_amount)],
  ];
  document.getElementById('summary').innerHTML = cards.map(c =>
    `<div class="card"><div class="k">${c[0]}</div><div class="v">${c[1]}</div></div>`
  ).join('');
}

function totalRow(t){
  return `<tr class="total">
    <td colspan="3">Total</td>
    <td class="r">${qty(t.bills)}</td>
    <td class="r">${wgt(t.gold_weight)}</td>
    <td class="r">${wgt(t.gold_return_weight)}</td>
    <td class="r">${wgt(t.gold_net_weight)}</td>
    <td class="r">${money(t.diamond_amount)}</td>
    <td class="r">${money(t.diamond_return_amount)}</td>
    <td class="r">${money(t.diamond_net_amount)}</td>
    <td class="r">${wgt(t.gold18_weight)}</td>
    <td class="r">${wgt(t.gold18_return_weight)}</td>
    <td class="r">${wgt(t.gold18_net_weight)}</td>
    <td class="r">${wgt(t.silver_weight)}</td>
    <td class="r">${wgt(t.silver_return_weight)}</td>
    <td class="r">${wgt(t.silver_net_weight)}</td>
    <td class="r">${qty(t.perfume_qty)}</td>
    <td class="r">${money(t.perfume_amount)}</td>
    <td class="r">${qty(t.watch_qty)}</td>
    <td class="r">${money(t.watch_amount)}</td>
    <td class="r">${money(t.other_amount)}</td>
    <td class="r">${money(t.total_amount)}</td>
  </tr>`;
}

function render(data){
  renderSummary(data.totals || {});
  document.getElementById('period').textContent = `${data.date1 || ''} to ${data.date2 || ''}`;
  const rows = data.rows || [];
  if(!rows.length){
    document.getElementById('result').innerHTML = '<div class="status">No sales found for this period.</div>';
    return;
  }
  const body = rows.map((r, i) => `<tr>
    <td>${i + 1}</td>
    <td>${esc((r.smcode ? r.smcode + ' - ' : '') + (r.smname || 'No Salesman'))}</td>
    <td>${esc(r.bill_section)}</td>
    <td class="r">${qty(r.bills)}</td>
    <td class="r">${wgt(r.gold_weight)}</td>
    <td class="r">${wgt(r.gold_return_weight)}</td>
    <td class="r">${wgt(r.gold_net_weight)}</td>
    <td class="r">${money(r.diamond_amount)}</td>
    <td class="r">${money(r.diamond_return_amount)}</td>
    <td class="r">${money(r.diamond_net_amount)}</td>
    <td class="r">${wgt(r.gold18_weight)}</td>
    <td class="r">${wgt(r.gold18_return_weight)}</td>
    <td class="r">${wgt(r.gold18_net_weight)}</td>
    <td class="r">${wgt(r.silver_weight)}</td>
    <td class="r">${wgt(r.silver_return_weight)}</td>
    <td class="r">${wgt(r.silver_net_weight)}</td>
    <td class="r">${qty(r.perfume_qty)}</td>
    <td class="r">${money(r.perfume_amount)}</td>
    <td class="r">${qty(r.watch_qty)}</td>
    <td class="r">${money(r.watch_amount)}</td>
    <td class="r">${money(r.other_amount)}</td>
    <td class="r">${money(r.total_amount)}</td>
  </tr>`).join('');
  document.getElementById('result').innerHTML = `<table>
    <thead><tr>
      <th>#</th><th>Salesman</th><th>Bill Section</th><th class="r">Bills</th>
      <th class="r">Gold Weight</th><th class="r">Gold Return Wgt</th><th class="r">Gold Net Wgt</th>
      <th class="r">Diamond Amount</th><th class="r">Diamond Return Amt</th><th class="r">Diamond Net Amt</th>
      <th class="r">18K Weight</th><th class="r">18K Return Wgt</th><th class="r">18K Net Wgt</th>
      <th class="r">Silver Weight</th><th class="r">Silver Return Wgt</th><th class="r">Silver Net Wgt</th>
      <th class="r">Perfume Qty</th><th class="r">Perfume Amount</th>
      <th class="r">Watch Qty</th><th class="r">Watch Amount</th>
      <th class="r">Other Amount</th><th class="r">Total Amount</th>
    </tr></thead>
    <tbody>${body}${totalRow(data.totals || {})}</tbody>
  </table>`;
}

async function loadData(){
  document.getElementById('result').innerHTML = '<div class="status">Loading...</div>';
  const params = new URLSearchParams({
    date1: document.getElementById('date1').value,
    date2: document.getElementById('date2').value,
    smcode: document.getElementById('smcode').value,
    rlevel: document.getElementById('rlevel').value || '1',
  });
  const res = await fetch(API_DATA + '?' + params.toString());
  const data = await res.json();
  if(!data.ok){
    document.getElementById('result').innerHTML = `<div class="status">${esc(data.message || 'Unable to load report')}</div>`;
    return;
  }
  render(data);
}

document.getElementById('showBtn').addEventListener('click', loadData);
loadLookups().then(loadData);
</script>
</body>
</html>
