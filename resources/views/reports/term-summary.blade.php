<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:#eef3f8;color:#18324a;font-size:20px}
.page{padding:14px;max-width:1480px;margin:0 auto}
.toolbar{display:flex;gap:10px;align-items:end;flex-wrap:wrap;background:#173b63;color:#fff;padding:12px 14px;border-radius:12px}
.toolbar h1{font-size:18px;font-weight:800;margin-right:8px}
.field{display:flex;flex-direction:column;gap:4px}
.field label{font-size:17px;font-weight:700;text-transform:uppercase;opacity:.82}
.field input{height:32px;border:1px solid rgba(255,255,255,.22);border-radius:8px;padding:0 10px;background:rgba(255,255,255,.12);color:#fff}
.btn{height:32px;border:none;border-radius:8px;padding:0 14px;font-size:12px;font-weight:700;cursor:pointer}
.btn-show{background:#f6c453;color:#17324a}
.btn-lite{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.18)}
.meta{margin:10px 0 14px;color:#5b738d;font-size:18px}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:14px}
.card{background:#fff;border:1px solid #d7e3ef;border-radius:12px;padding:14px}
.card .k{font-size:10px;text-transform:uppercase;color:#6b7f92;font-weight:700;margin-bottom:6px}
.card .v{font-size:24px;font-weight:800;color:#173b63}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px}
.panel{background:#fff;border:1px solid #d7e3ef;border-radius:12px;overflow:hidden}
.panel h2{padding:11px 14px;background:#f7fbff;border-bottom:1px solid #e6eef6;font-size:20px}
table{width:100%;border-collapse:collapse}
th,td{padding:8px 10px;border-bottom:1px solid #edf3f8}
th{font-size:10px;color:#6b7f92;text-transform:uppercase;text-align:left;background:#fbfdff}
td.r,th.r{text-align:right}
.muted{color:#7a8ea2}
.loading,.empty{padding:40px;text-align:center;color:#7a8ea2}
@media print{.toolbar{display:none}.page{padding:0}.panel,.card{break-inside:avoid}}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="page">
  <div class="toolbar">
    <h1>{{ $title }}</h1>
    <div class="field">
      <label>Date From</label>
      <input id="dateFrom" type="date" value="{{ $dateFrom }}">
    </div>
    <div class="field">
      <label>Date To</label>
      <input id="dateTo" type="date" value="{{ $dateTo }}">
    </div>
    <button class="btn btn-show" id="btnShow">Show</button>
    <button class="btn btn-lite" id="btnPrint">Print</button>
    <button class="btn btn-lite" id="btnExit">Exit</button>
  </div>

  <div class="meta" id="metaLine">Choose a range and load the summary.</div>
  <div id="content"><div class="loading">Loading term summary...</div></div>
</div>

<script>
const SITE = @json(request()->root());
const GILEVEL = {{ $gilevel }};
const fmtAmt = n => '₹' + Number(n || 0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtWgt = n => Number(n || 0).toLocaleString('en-IN', {minimumFractionDigits:3, maximumFractionDigits:3});
const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

function metricCard(label, value, formatter = fmtAmt) {
  return `<div class="card"><div class="k">${esc(label)}</div><div class="v">${formatter(value)}</div></div>`;
}

function row(label, value, cls = 'r', formatter = fmtAmt) {
  return `<tr><td>${esc(label)}</td><td class="${cls}">${formatter(value)}</td></tr>`;
}

function stockRows(group) {
  return [
    `<tr><td>Gold Ornaments</td><td class="r">${fmtWgt(group.gold_ornaments.weight)}</td><td class="r">${fmtWgt(group.gold_ornaments.stone)}</td></tr>`,
    `<tr><td>Old Gold</td><td class="r">${fmtWgt(group.old_gold.weight)}</td><td class="r">${fmtWgt(group.old_gold.stone)}</td></tr>`,
    `<tr><td>Other Gold</td><td class="r">${fmtWgt(group.other_gold.weight)}</td><td class="r">${fmtWgt(group.other_gold.stone)}</td></tr>`,
    `<tr><th>Total</th><th class="r">${fmtWgt(group.totals.weight)}</th><th class="r">${fmtWgt(group.totals.stone)}</th></tr>`
  ].join('');
}

function render(data) {
  document.getElementById('metaLine').textContent = `Showing ${data.date_from} to ${data.date_to}`;
  const s = data.sales.totals;
  const p = data.purchase.totals;
  const sm = data.smith.totals;
  const rf = data.refinery.totals;
  const c = data.cash;
  const b = data.balances;

  document.getElementById('content').innerHTML = `
    <div class="cards">
      ${metricCard('Sales', data.overview.sales_amount)}
      ${metricCard('Purchase', data.overview.purchase_amount)}
      ${metricCard('Closing Cash', data.overview.closing_cash)}
      ${metricCard('Customer Balance', data.overview.customer_balance)}
      ${metricCard('Supplier Balance', data.overview.supplier_balance)}
      ${metricCard('Advance Pending', data.overview.advance_pending)}
    </div>

    <div class="grid">
      <div class="panel">
        <h2>Sales Summary</h2>
        <table>
          <tr><th>Metric</th><th class="r">Value</th></tr>
          ${row('Gold Sales Weight', s.gold_sales_wgt, 'r', fmtWgt)}
          ${row('Gold Sales Return Weight', s.gold_sales_ret_wgt, 'r', fmtWgt)}
          ${row('Net Gold Sales Weight', s.net_gold_sales_wgt, 'r', fmtWgt)}
          ${row('Silver Sales Weight', s.silver_sales_wgt, 'r', fmtWgt)}
          ${row('Platinum Sales Weight', s.platinum_sales_wgt, 'r', fmtWgt)}
          ${row('Diamond Sales (Ct)', s.diamond_sales_ct, 'r', fmtWgt)}
          ${row('Watch Sales Qty', s.watch_sales_qty, 'r', n => Number(n || 0).toLocaleString('en-IN'))}
          ${row('Sales Amount', s.sales_amount)}
          ${row('Sales Return Amount', s.sales_return_amount)}
          ${row('Net Sales Amount', s.net_sales_amount)}
          ${row('Discount', s.discount_amount)}
          ${row('Exchange Amount', s.exchange_amount)}
          ${row('Received Amount', s.received_amount)}
          ${row('Tax Amount', s.tax_amount)}
        </table>
      </div>

      <div class="panel">
        <h2>Purchase Summary</h2>
        <table>
          <tr><th>Metric</th><th class="r">Value</th></tr>
          ${row('OG Purchase Weight', p.og_purchase_wgt, 'r', fmtWgt)}
          ${row('OG Less Weight', p.og_less_wgt, 'r', fmtWgt)}
          ${row('OG Purchase Amount', p.og_purchase_amt)}
          ${row('Ornament Purchase Weight', p.gold_orn_purchase_wgt, 'r', fmtWgt)}
          ${row('Ornament Purchase Amount', p.gold_orn_purchase_amt)}
          ${row('Other Gold Purchase Weight', p.other_gold_purchase_wgt, 'r', fmtWgt)}
          ${row('Other Gold Purchase Amount', p.other_gold_purchase_amt)}
          ${row('Silver Purchase Weight', p.silver_purchase_wgt, 'r', fmtWgt)}
          ${row('Silver Purchase Amount', p.silver_purchase_amt)}
          ${row('Total Gold Purchase Weight', p.total_gold_purchase_wgt, 'r', fmtWgt)}
          ${row('Purchase Return Weight', p.purchase_return_wgt, 'r', fmtWgt)}
          ${row('Purchase Amount', p.purchase_amount)}
          ${row('Purchase Paid Amount', p.purchase_paid_amount)}
          ${row('Purchase Return Amount', p.purchase_return_amount)}
        </table>
      </div>

      <div class="panel">
        <h2>Smith / Jewellery</h2>
        <table>
          <tr><th>Metric</th><th class="r">Value</th></tr>
          ${row('Issued to Smith', sm.smith_issue_wgt, 'r', fmtWgt)}
          ${row('Smith Issue Stone', sm.smith_issue_stone, 'r', fmtWgt)}
          ${row('Received from Smith', sm.smith_rcvd_wgt, 'r', fmtWgt)}
          ${row('Smith Receive Stone', sm.smith_rcvd_stone, 'r', fmtWgt)}
          ${row('Issued to Jewellery', sm.jewellery_issue_wgt, 'r', fmtWgt)}
          ${row('Jewellery Issue Stone', sm.jewellery_issue_stone, 'r', fmtWgt)}
          ${row('Received from Jewellery', sm.jewellery_rcvd_wgt, 'r', fmtWgt)}
          ${row('Jewellery Receive Stone', sm.jewellery_rcvd_stone, 'r', fmtWgt)}
          ${row('Smith To Receive', sm.smith_to_receive, 'r', fmtWgt)}
          ${row('Smith To Give', sm.smith_to_give, 'r', fmtWgt)}
          ${row('Jewellery To Receive', sm.jewellery_to_receive, 'r', fmtWgt)}
          ${row('Jewellery To Give', sm.jewellery_to_give, 'r', fmtWgt)}
        </table>
      </div>

      <div class="panel">
        <h2>Refinery / Advances</h2>
        <table>
          <tr><th>Metric</th><th class="r">Value</th></tr>
          ${row('Refinery Issued', rf.issued_wgt, 'r', fmtWgt)}
          ${row('Refinery Issued Stone', rf.issued_stone, 'r', fmtWgt)}
          ${row('Refinery Received', rf.received_wgt, 'r', fmtWgt)}
          ${row('Test Pieces', rf.test_pcs, 'r', fmtWgt)}
          ${row('Refinery Balance', rf.balance_wgt, 'r', fmtWgt)}
          ${row('Gold Advance Pending', rf.gold_advance_wgt, 'r', fmtWgt)}
          ${row('Gold Advance Received', rf.gold_advance_rcpt_wgt, 'r', fmtWgt)}
        </table>
      </div>

      <div class="panel">
        <h2>Cash / Party Balances</h2>
        <table>
          <tr><th>Metric</th><th class="r">Value</th></tr>
          ${row('Opening Cash', c.opening_cash)}
          ${row('Period Cash Movement', c.period_cash)}
          ${row('Closing Cash', c.closing_cash)}
          ${row('Cash in Locker', c.cash_in_locker)}
          ${row('Customer Balance', b.customer_balance)}
          ${row('Supplier Balance', b.supplier_balance)}
          ${row('Kuri Party Balance', b.kuri_party_balance)}
          ${row('Advance Pending', b.advance_pending)}
        </table>
      </div>

      <div class="panel">
        <h2>Opening Stock</h2>
        <table>
          <tr><th>Category</th><th class="r">Weight</th><th class="r">Stone</th></tr>
          ${stockRows(data.stock.opening)}
        </table>
      </div>

      <div class="panel">
        <h2>Closing Stock</h2>
        <table>
          <tr><th>Category</th><th class="r">Weight</th><th class="r">Stone</th></tr>
          ${stockRows(data.stock.closing)}
        </table>
      </div>
    </div>
  `;
}

async function loadSummary() {
  const dateFrom = document.getElementById('dateFrom').value;
  const dateTo = document.getElementById('dateTo').value;
  const qs = new URLSearchParams({date_from: dateFrom, date_to: dateTo, gilevel: GILEVEL});
  document.getElementById('content').innerHTML = '<div class="loading">Loading term summary...</div>';
  try {
    const res = await fetch(`${SITE}/api/term-summary?${qs.toString()}`);
    const data = await res.json();
    if (!data.ok) {
      document.getElementById('content').innerHTML = `<div class="empty">${esc(data.message || 'Unable to load summary.')}</div>`;
      return;
    }
    render(data);
  } catch (err) {
    document.getElementById('content').innerHTML = `<div class="empty">${esc(err.message || 'Network error')}</div>`;
  }
}

document.getElementById('btnShow').onclick = loadSummary;
document.getElementById('btnPrint').onclick = () => window.print();
document.getElementById('btnExit').onclick = () => window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');
loadSummary();
</script>
</body>
</html>
