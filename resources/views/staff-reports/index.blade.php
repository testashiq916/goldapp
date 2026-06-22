<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:#f0f4fa;color:#1e293b;font-size:20px}
.wrap{max-width:1300px;margin:0 auto;padding:10px 14px 30px}

/* Header */
.hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.hdr h1{font-size:17px;color:#173b63;font-weight:800}
.hdr h1 small{font-size:12px;color:#64748b;font-weight:500;margin-left:8px}
button.exit-btn{height:30px;padding:0 14px;border-radius:5px;font-size:18px;font-weight:700;border:1px solid #bfd0e6;background:#fff;color:#375b84;cursor:pointer}

/* Toolbar */
.strip{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;background:#fff;border:1px solid #d7e4f0;border-radius:8px;padding:10px 12px;margin-bottom:12px}
.strip .field{display:flex;flex-direction:column;gap:3px}
.strip label{font-size:17px;font-weight:700;color:#375b84;text-transform:uppercase}
.strip input,.strip select{height:28px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:18px;background:#fff}
.btn{height:28px;padding:0 14px;border-radius:5px;font-size:18px;font-weight:700;cursor:pointer;border:none}
.btn-primary{background:#1e5799;color:#fff}
.btn-primary:hover{background:#174a82}
.btn-print{background:#e8f0fb;color:#1e5799;border:1px solid #bfd0e6}

/* Cards */
.cards{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.card{background:#fff;border:1px solid #d7e4f0;border-radius:8px;padding:12px 16px;flex:1;min-width:140px}
.card-label{font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:4px}
.card-val{font-size:20px;font-weight:800;color:#1e3a5f}
.card-sub{font-size:10px;color:#94a3b8;margin-top:2px}
.card.green .card-val{color:#16a34a}
.card.orange .card-val{color:#d97706}
.card.red .card-val{color:#dc2626}
.card.blue .card-val{color:#1e5799}

/* Table */
.tbl-wrap{background:#fff;border:1px solid #d7e4f0;border-radius:8px;overflow:hidden;margin-bottom:14px}
.tbl-title{font-size:18px;font-weight:700;color:#1e3a5f;padding:10px 14px;border-bottom:1px solid #e8f0fb;background:#f7faff;display:flex;justify-content:space-between;align-items:center}
table{width:100%;border-collapse:collapse}
th{background:#f1f7ff;color:#375b84;font-size:16px;font-weight:700;padding:7px 10px;text-align:left;border-bottom:1px solid #d7e4f0;white-space:nowrap}
th.r,td.r{text-align:right}
th.c,td.c{text-align:center}
td{padding:6px 10px;font-size:18px;border-bottom:1px solid #edf2fb;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#f7faff}
.tfoot td{font-weight:700;background:#f1f7ff;color:#1e3a5f;border-top:2px solid #bfd0e6;border-bottom:none}
.badge{display:inline-block;padding:1px 7px;border-radius:10px;font-size:10px;font-weight:700}
.badge-green{background:#dcfce7;color:#16a34a}
.badge-red{background:#fee2e2;color:#dc2626}
.badge-blue{background:#dbeafe;color:#1d4ed8}
.badge-orange{background:#ffedd5;color:#c2410c}

/* Group header */
.grp-hdr td{background:#e8f0fb;font-weight:700;color:#1e3a5f;font-size:18px;padding:7px 10px}

/* Bar chart */
.bar-wrap{display:flex;align-items:center;gap:6px}
.bar-bg{flex:1;background:#e8f0fb;border-radius:3px;height:10px;overflow:hidden;min-width:60px}
.bar-fill{height:100%;border-radius:3px;background:#1e5799;transition:.3s}
.bar-lbl{font-size:11px;color:#475569;white-space:nowrap;min-width:70px;text-align:right}

/* Performance rank */
.rank-no{width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;background:#94a3b8}
.rank-1{background:#f59e0b}
.rank-2{background:#6b7280}
.rank-3{background:#b45309}

/* Loading */
.loading{text-align:center;padding:40px;color:#94a3b8;font-size:20px}
.spin{display:inline-block;width:18px;height:18px;border:2px solid #bfd0e6;border-top-color:#1e5799;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:6px}
@keyframes spin{to{transform:rotate(360deg)}}

/* Print */
@media print{
  .strip,.hdr,.btn,.exit-btn{display:none!important}
  .tbl-wrap{border:1px solid #999}
  body{background:#fff}
}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="wrap">

<div class="hdr">
  <h1><i class="fa fa-users"></i> Staff Reports <small id="subTitle"></small></h1>
  <button class="exit-btn" onclick="if(window.parent!==window)window.parent.postMessage({type:'goldapp:close-frame'},'*');else window.history.back()">Exit</button>
</div>

<div id="mainArea">
  <div class="loading"><span class="spin"></span> Loading...</div>
</div>

</div><!-- wrap -->

<script>
const CSRF   = document.querySelector('meta[name="csrf-token"]').content;
const TODAY  = '{{ $today }}';
const FIRST  = '{{ $firstDay }}';

const API = {
  'salesman-book':    @json(url('/api/staff-reports/salesman-book')),
  'salesman-summary': @json(url('/api/staff-reports/salesman-summary')),
  'counter-sales':    @json(url('/api/staff-reports/counter-sales')),
  'incharge-report':  @json(url('/api/staff-reports/incharge-report')),
  'commission-report':@json(url('/api/staff-reports/commission-report')),
  'performance':      @json(url('/api/staff-reports/performance')),
  'term-summary':     @json(url('/api/staff-reports/term-summary')),
  'attendance':       @json(url('/api/staff-reports/attendance')),
  'leave':            @json(url('/api/staff-reports/leave')),
  'staff-log':        @json(url('/api/staff-reports/staff-log')),
  'ledger':           @json(url('/api/staff-reports/ledger')),
  'wgt-trans':        @json(url('/api/staff-reports/wgt-trans')),
};

let currentSub = '{{ $sub }}';
let smanListCache = null;

const SUB_LABELS = {
  'salesman-book':    'Salesman Sales Book',
  'salesman-summary': 'Salesman Summary',
  'term-summary':     'Term Summary',
  'commission-report':'Commission Report',
  'counter-sales':    'Counter Sales',
  'incharge-report':  'Incharge Report',
  'performance':      'Performance Dashboard',
  'ledger':           'Staff Ledger',
  'attendance':       'Attendance Report',
  'leave':            'Leave Report',
  'staff-log':        'Staff Log Report',
  'wgt-trans':        'Weight Transaction Report',
};

function loadSub(sub, params={}) {
  document.getElementById('mainArea').innerHTML = '<div class="loading"><span class="spin"></span> Loading...</div>';
  const defaults = {from: FIRST, to: TODAY};
  const p = Object.assign(defaults, params);
  const qs = new URLSearchParams(p).toString();
  fetch(API[sub] + '?' + qs, {headers:{Accept:'application/json','X-CSRF-TOKEN':CSRF}})
    .then(r=>r.json())
    .then(d=>{ if(d.ok) render[sub](d, p); else showErr(d.message||'Error'); })
    .catch(e=>showErr(e.message));
}

function showErr(msg){
  document.getElementById('mainArea').innerHTML = `<div class="loading" style="color:#dc2626"><i class="fa fa-circle-exclamation"></i> ${msg}</div>`;
}

/* ── Money / number format ─────────────────────────────────────── */
function m(n){ return Number(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function n(v){ return Number(v||0).toLocaleString('en-IN'); }
function pct(v){ return Number(v||0).toFixed(1)+'%'; }

/* ── Toolbar builder ─────────────────────────────────────────────── */
function toolbar(from, to, extra='', showPrint=true) {
  return `<div class="strip">
    <div class="field"><label>From</label><input type="date" id="dtFrom" value="${from}" max="${TODAY}"></div>
    <div class="field"><label>To</label><input type="date" id="dtTo" value="${to}" max="${TODAY}"></div>
    ${extra}
    <button class="btn btn-primary" onclick="applyFilter()"><i class="fa fa-search"></i> Show</button>
    ${showPrint ? '<button class="btn btn-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>' : ''}
  </div>`;
}

function applyFilter(extra={}) {
  const from = document.getElementById('dtFrom')?.value || FIRST;
  const to   = document.getElementById('dtTo')?.value   || TODAY;
  const smcode= document.getElementById('selSman')?.value || '';
  const params = {from, to};
  if (smcode) params.smcode = smcode;
  Object.assign(params, extra);
  loadSub(currentSub, params);
}

function smanSelect(list, selected='') {
  const opts = list.map(s=>`<option value="${s.code}" ${s.code===selected?'selected':''}>${s.name} (${s.code})</option>`).join('');
  return `<div class="field"><label>Salesman</label>
    <select id="selSman" style="min-width:140px"><option value="">All Salesman</option>${opts}</select></div>`;
}
function staffSelect(list, selected='', label='Staff') {
  const opts = list.map(s=>`<option value="${s.code}" ${s.code===selected?'selected':''}>${s.name} (${s.code})</option>`).join('');
  return `<div class="field"><label>${label}</label>
    <select id="selStaff" style="min-width:140px"><option value="">All Staff</option>${opts}</select></div>`;
}
function applyStaffFilter() {
  const from  = document.getElementById('dtFrom')?.value || FIRST;
  const to    = document.getElementById('dtTo')?.value   || TODAY;
  const staff = document.getElementById('selStaff')?.value || '';
  const smcode= document.getElementById('selSman')?.value || '';
  const p = {from, to};
  if (staff)  p.staff  = staff;
  if (smcode) p.smcode = smcode;
  loadSub(currentSub, p);
}
function noData(msg='No data for this period.') {
  return `<div class="tbl-wrap"><div class="loading">${msg}</div></div>`;
}

/* ══════════════════════════════════════════════════════════════
   RENDERERS
   ══════════════════════════════════════════════════════════════ */
const render = {};

/* ── 1. Salesman Sales Book ─────────────────────────────────── */
render['salesman-book'] = function(d, p) {
  let html = toolbar(p.from, p.to, smanSelect(d.sman_list, p.smcode||''));

  // Summary cards
  const g = d.grand;
  html += `<div class="cards">
    <div class="card blue"><div class="card-label">Total Bills</div><div class="card-val">${n(g.grandBills)}</div></div>
    <div class="card"><div class="card-label">Bill Amount</div><div class="card-val">₹${m(g.grandAmt)}</div></div>
    <div class="card green"><div class="card-label">Net Amount</div><div class="card-val">₹${m(g.grandNet)}</div></div>
    <div class="card orange"><div class="card-label">Total Discount</div><div class="card-val">₹${m(g.grandDisc)}</div></div>
  </div>`;

  if (!d.grouped.length) {
    html += `<div class="tbl-wrap"><div class="loading">No data for this period.</div></div>`;
    document.getElementById('mainArea').innerHTML = html;
    return;
  }

  html += `<div class="tbl-wrap">
    <div class="tbl-title">Salesman Sales Book — ${p.from} to ${p.to}</div>
    <table>
      <tr>
        <th>Date</th><th>Bill No</th><th>Customer</th>
        <th class="r">Bill Amt</th><th class="r">Discount</th><th class="r">Net Amt</th><th class="c">Status</th>
      </tr>`;

  d.grouped.forEach(g => {
    html += `<tr class="grp-hdr">
      <td colspan="3"><i class="fa fa-user"></i> ${g.smname} (${g.smcode||'—'})</td>
      <td class="r">₹${m(g.total_amt)}</td>
      <td class="r">₹${m(g.total_disc)}</td>
      <td class="r">₹${m(g.total_net)}</td>
      <td class="c"><span class="badge badge-blue">${g.bill_count} bills</span>${g.cancelled?' <span class="badge badge-red">'+g.cancelled+' cancelled</span>':''}</td>
    </tr>`;
    g.bills.forEach(b => {
      const cancelled = b.status === 'Cancelled';
      html += `<tr style="${cancelled?'opacity:.55':''}">
        <td>${b.tdate}</td>
        <td><b>${b.billno}</b></td>
        <td>${b.custname||'—'}</td>
        <td class="r">${cancelled?'<s>':''} ₹${m(b.billamt)} ${cancelled?'</s>':''}</td>
        <td class="r">${b.discount>0?'₹'+m(b.discount):'—'}</td>
        <td class="r">₹${m(b.netamt)}</td>
        <td class="c"><span class="badge ${cancelled?'badge-red':'badge-green'}">${b.status}</span></td>
      </tr>`;
    });
  });

  html += `<tr class="tfoot">
    <td colspan="3">GRAND TOTAL</td>
    <td class="r">₹${m(g.grandAmt)}</td>
    <td class="r">₹${m(g.grandDisc)}</td>
    <td class="r">₹${m(g.grandNet)}</td>
    <td></td>
  </tr></table></div>`;

  document.getElementById('mainArea').innerHTML = html;
};

/* ── 2. Salesman Summary ─────────────────────────────────────── */
render['salesman-summary'] = function(d, p) {
  let html = toolbar(p.from, p.to);
  const g = d.grand;
  const maxAmt = Math.max(...d.rows.map(r=>r.total_amt||0), 1);

  html += `<div class="cards">
    <div class="card blue"><div class="card-label">Active Bills</div><div class="card-val">${n(g.active_bills)}</div></div>
    <div class="card"><div class="card-label">Total Sales</div><div class="card-val">₹${m(g.total_amt)}</div></div>
    <div class="card green"><div class="card-label">Net Amount</div><div class="card-val">₹${m(g.net_amt)}</div></div>
    <div class="card orange"><div class="card-label">Total Discount</div><div class="card-val">₹${m(g.total_disc)}</div></div>
    <div class="card"><div class="card-label">Commission</div><div class="card-val">₹${m(g.commission)}</div></div>
  </div>`;

  if (!d.rows.length) {
    html += `<div class="tbl-wrap"><div class="loading">No data for this period.</div></div>`;
    document.getElementById('mainArea').innerHTML = html;
    return;
  }

  html += `<div class="tbl-wrap">
    <div class="tbl-title">Salesman Summary — ${p.from} to ${p.to}</div>
    <table>
      <tr>
        <th>#</th><th>Salesman</th><th class="c">Bills</th><th class="c">Cancelled</th>
        <th class="r">Total Sales</th><th class="r">Discount</th><th class="r">Net Amt</th>
        <th class="r">Avg Bill</th><th class="r">Max Bill</th><th class="r">Comm%</th><th class="r">Commission</th>
        <th>Sales Share</th>
      </tr>`;

  d.rows.forEach((r,i) => {
    const barPct = maxAmt > 0 ? Math.round((r.total_amt/maxAmt)*100) : 0;
    const rnk = i<3 ? `<span class="rank-no rank-${i+1}">${i+1}</span>` : `<span style="color:#94a3b8;font-size:11px">${i+1}</span>`;
    html += `<tr>
      <td class="c">${rnk}</td>
      <td><b>${r.smname}</b><br><small style="color:#94a3b8">${r.smcode||''}</small></td>
      <td class="c"><span class="badge badge-blue">${r.active_bills}</span></td>
      <td class="c">${r.cancelled>0?`<span class="badge badge-red">${r.cancelled}</span>`:'—'}</td>
      <td class="r">₹${m(r.total_amt)}</td>
      <td class="r">${r.total_disc>0?'₹'+m(r.total_disc):'—'}</td>
      <td class="r">₹${m(r.net_amt)}</td>
      <td class="r">₹${m(r.avg_bill)}</td>
      <td class="r">₹${m(r.max_bill)}</td>
      <td class="r">${r.comn_pct||0}%</td>
      <td class="r">${r.commission>0?'₹'+m(r.commission):'—'}</td>
      <td><div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" style="width:${barPct}%"></div></div><span class="bar-lbl">${pct(r.total_amt/(d.grand.total_amt||1)*100)}</span></div></td>
    </tr>`;
  });

  html += `<tr class="tfoot">
    <td></td><td>TOTAL</td>
    <td class="c">${n(g.active_bills)}</td>
    <td class="c">${n(g.cancelled)}</td>
    <td class="r">₹${m(g.total_amt)}</td>
    <td class="r">₹${m(g.total_disc)}</td>
    <td class="r">₹${m(g.net_amt)}</td>
    <td class="r">—</td><td class="r">—</td><td class="r">—</td>
    <td class="r">₹${m(g.commission)}</td>
    <td>100%</td>
  </tr></table></div>`;

  document.getElementById('mainArea').innerHTML = html;
};

/* ── 3. Counter Sales ────────────────────────────────────────── */
render['counter-sales'] = function(d, p) {
  let html = toolbar(p.from, p.to);
  const g = d.grand;
  const maxAmt = Math.max(...d.rows.map(r=>r.total_amt||0), 1);

  html += `<div class="cards">
    <div class="card blue"><div class="card-label">Active Bills</div><div class="card-val">${n(g.active_bills)}</div></div>
    <div class="card"><div class="card-label">Total Sales</div><div class="card-val">₹${m(g.total_amt)}</div></div>
    <div class="card green"><div class="card-label">Net Amount</div><div class="card-val">₹${m(g.net_amt)}</div></div>
    <div class="card orange"><div class="card-label">Total Discount</div><div class="card-val">₹${m(g.total_disc)}</div></div>
  </div>`;

  if (!d.rows.length) {
    html += `<div class="tbl-wrap"><div class="loading">No data for this period.</div></div>`;
    document.getElementById('mainArea').innerHTML = html;
    return;
  }

  html += `<div class="tbl-wrap">
    <div class="tbl-title">Counter Sales Report — ${p.from} to ${p.to}</div>
    <table>
      <tr>
        <th>#</th><th>Counter</th><th class="c">Active Bills</th>
        <th class="r">Total Sales</th><th class="r">Net Amount</th>
        <th class="r">Discount</th><th class="r">Avg Bill</th><th class="c">Working Days</th>
        <th class="r">Daily Avg</th><th>Sales Share</th>
      </tr>`;

  d.rows.forEach((r,i) => {
    const barPct = maxAmt > 0 ? Math.round((r.total_amt/maxAmt)*100) : 0;
    const dailyAvg = r.working_days > 0 ? r.total_amt / r.working_days : 0;
    html += `<tr>
      <td class="c">${i+1}</td>
      <td><b>${r.counter_name}</b><br><small style="color:#94a3b8">${r.counter_code||''}</small></td>
      <td class="c"><span class="badge badge-blue">${r.active_bills}</span></td>
      <td class="r">₹${m(r.total_amt)}</td>
      <td class="r">₹${m(r.net_amt)}</td>
      <td class="r">${r.total_disc>0?'₹'+m(r.total_disc):'—'}</td>
      <td class="r">₹${m(r.avg_bill)}</td>
      <td class="c">${r.working_days}</td>
      <td class="r">₹${m(dailyAvg)}</td>
      <td><div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" style="width:${barPct}%"></div></div><span class="bar-lbl">${pct(r.total_amt/(d.grand.total_amt||1)*100)}</span></div></td>
    </tr>`;
  });

  html += `<tr class="tfoot">
    <td></td><td>TOTAL</td>
    <td class="c">${n(g.active_bills)}</td>
    <td class="r">₹${m(g.total_amt)}</td>
    <td class="r">₹${m(g.net_amt)}</td>
    <td class="r">₹${m(g.total_disc)}</td>
    <td class="r">—</td><td class="c">—</td><td class="r">—</td><td>100%</td>
  </tr></table></div>`;

  document.getElementById('mainArea').innerHTML = html;
};

/* ── 4. Incharge Report ──────────────────────────────────────── */
render['incharge-report'] = function(d, p) {
  let html = toolbar(p.from, p.to);
  const g = d.grand;
  const maxAmt = Math.max(...d.rows.map(r=>r.total_amt||0), 1);

  html += `<div class="cards">
    <div class="card blue"><div class="card-label">Active Bills</div><div class="card-val">${n(g.active_bills)}</div></div>
    <div class="card"><div class="card-label">Total Sales</div><div class="card-val">₹${m(g.total_amt)}</div></div>
    <div class="card green"><div class="card-label">Net Amount</div><div class="card-val">₹${m(g.net_amt)}</div></div>
    <div class="card orange"><div class="card-label">Total Discount</div><div class="card-val">₹${m(g.total_disc)}</div></div>
  </div>`;

  if (!d.rows.length) {
    html += `<div class="tbl-wrap"><div class="loading">No data for this period.</div></div>`;
    document.getElementById('mainArea').innerHTML = html;
    return;
  }

  html += `<div class="tbl-wrap">
    <div class="tbl-title">Incharge Report — ${p.from} to ${p.to}</div>
    <table>
      <tr>
        <th>#</th><th>Incharge</th><th class="c">Active Bills</th>
        <th class="r">Total Sales</th><th class="r">Net Amount</th>
        <th class="r">Discount</th><th class="r">Avg Bill</th><th class="r">Max Bill</th><th>Sales Share</th>
      </tr>`;

  d.rows.forEach((r,i) => {
    const barPct = maxAmt > 0 ? Math.round((r.total_amt/maxAmt)*100) : 0;
    const rnk = i<3 ? `<span class="rank-no rank-${i+1}">${i+1}</span>` : `<span style="color:#94a3b8;font-size:11px">${i+1}</span>`;
    html += `<tr>
      <td class="c">${rnk}</td>
      <td><b>${r.ic_name}</b><br><small style="color:#94a3b8">${r.ic_code||''}</small></td>
      <td class="c"><span class="badge badge-blue">${r.active_bills}</span></td>
      <td class="r">₹${m(r.total_amt)}</td>
      <td class="r">₹${m(r.net_amt)}</td>
      <td class="r">${r.total_disc>0?'₹'+m(r.total_disc):'—'}</td>
      <td class="r">₹${m(r.avg_bill)}</td>
      <td class="r">₹${m(r.max_bill)}</td>
      <td><div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" style="width:${barPct}%"></div></div><span class="bar-lbl">${pct(r.total_amt/(d.grand.total_amt||1)*100)}</span></div></td>
    </tr>`;
  });

  html += `<tr class="tfoot">
    <td></td><td>TOTAL</td>
    <td class="c">${n(g.active_bills)}</td>
    <td class="r">₹${m(g.total_amt)}</td>
    <td class="r">₹${m(g.net_amt)}</td>
    <td class="r">₹${m(g.total_disc)}</td>
    <td class="r">—</td><td class="r">—</td><td>100%</td>
  </tr></table></div>`;

  document.getElementById('mainArea').innerHTML = html;
};

/* ── 5. Commission Report ────────────────────────────────────── */
render['commission-report'] = function(d, p) {
  let html = toolbar(p.from, p.to, smanSelect(d.sman_list, p.smcode||''));
  const g = d.grand;

  html += `<div class="cards">
    <div class="card blue"><div class="card-label">Salesman Count</div><div class="card-val">${d.grouped.length}</div></div>
    <div class="card"><div class="card-label">Total Sales</div><div class="card-val">₹${m(g.grandAmt)}</div></div>
    <div class="card green"><div class="card-label">Total Commission</div><div class="card-val">₹${m(g.grandComm)}</div></div>
  </div>`;

  if (!d.grouped.length) {
    html += `<div class="tbl-wrap"><div class="loading">No salesman commission data.<br><small>Check that salesman codes are set on bills and commission % is set in Salesman Master.</small></div></div>`;
    document.getElementById('mainArea').innerHTML = html;
    return;
  }

  html += `<div class="tbl-wrap">
    <div class="tbl-title">Commission Report — ${p.from} to ${p.to}</div>
    <table>
      <tr>
        <th>Date</th><th>Bill No</th><th>Customer</th>
        <th class="r">Bill Amt</th><th class="r">Comm%</th><th class="r">Commission</th>
      </tr>`;

  d.grouped.forEach(g => {
    html += `<tr class="grp-hdr">
      <td colspan="3"><i class="fa fa-user"></i> ${g.smname} (${g.smcode}) — ${g.comn_pct}% Commission</td>
      <td class="r">₹${m(g.total_amt)}</td>
      <td class="r">${g.comn_pct}%</td>
      <td class="r" style="color:#16a34a;font-size:13px">₹${m(g.total_comm)}</td>
    </tr>`;
    g.bills.forEach(b => {
      html += `<tr>
        <td>${b.tdate}</td>
        <td><b>${b.billno}</b></td>
        <td>${b.custname||'—'}</td>
        <td class="r">₹${m(b.billamt)}</td>
        <td class="r">${b.comn_pct}%</td>
        <td class="r" style="color:#16a34a">₹${m(b.commission)}</td>
      </tr>`;
    });
  });

  html += `<tr class="tfoot">
    <td colspan="3">GRAND TOTAL</td>
    <td class="r">₹${m(g.grandAmt)}</td>
    <td class="r">—</td>
    <td class="r">₹${m(g.grandComm)}</td>
  </tr></table></div>`;

  document.getElementById('mainArea').innerHTML = html;
};

/* ── 6. Performance Dashboard ────────────────────────────────── */
render['performance'] = function(d, p) {
  let html = toolbar(p.from, p.to);
  const top = d.top;

  // Top performer card
  if (top) {
    html += `<div style="background:linear-gradient(135deg,#1e5799,#2980b9);border-radius:10px;padding:14px 18px;margin-bottom:12px;color:#fff;display:flex;align-items:center;gap:14px">
      <div style="font-size:28px">🏆</div>
      <div>
        <div style="font-size:10px;opacity:.8;text-transform:uppercase;letter-spacing:1px">Top Performer — ${p.from} to ${p.to}</div>
        <div style="font-size:18px;font-weight:800;margin:2px 0">${top.smname}</div>
        <div style="font-size:12px;opacity:.9">₹${m(top.total_amt)} in sales · ${n(top.active_bills)} bills · ${n(top.active_days)} active days</div>
      </div>
    </div>`;
  }

  if (!d.summary.length) {
    html += `<div class="tbl-wrap"><div class="loading">No data for this period.</div></div>`;
    document.getElementById('mainArea').innerHTML = html;
    return;
  }

  const maxAmt = Math.max(...d.summary.map(r=>r.total_amt||0), 1);

  html += `<div class="tbl-wrap">
    <div class="tbl-title">Staff Performance Comparison — ${p.from} to ${p.to}</div>
    <table>
      <tr>
        <th>#</th><th>Salesman</th>
        <th class="r">Total Sales</th><th class="r">Bills</th><th class="r">Avg Bill</th>
        <th class="r">Max Bill</th><th class="c">Active Days</th>
        <th class="r">Daily Sales</th><th class="r">Discount</th><th class="r">Commission</th>
        <th>Performance</th>
      </tr>`;

  d.summary.forEach((r,i) => {
    const barPct = maxAmt > 0 ? Math.round((r.total_amt/maxAmt)*100) : 0;
    const dailyAvg = r.active_days > 0 ? r.total_amt / r.active_days : 0;
    const rnk = i<3 ? `<span class="rank-no rank-${i+1}">${i+1}</span>` : `<span style="color:#94a3b8;font-size:11px;padding:2px 6px">${i+1}</span>`;
    html += `<tr>
      <td class="c">${rnk}</td>
      <td><b>${r.smname}</b><br><small style="color:#94a3b8">${r.smcode||'—'}</small></td>
      <td class="r"><b>₹${m(r.total_amt)}</b></td>
      <td class="r">${n(r.active_bills)}</td>
      <td class="r">₹${m(r.avg_bill)}</td>
      <td class="r">₹${m(r.max_bill)}</td>
      <td class="c">${r.active_days}</td>
      <td class="r">₹${m(dailyAvg)}</td>
      <td class="r">${r.total_disc>0?'₹'+m(r.total_disc):'—'}</td>
      <td class="r">${r.commission>0?'₹'+m(r.commission):'—'}</td>
      <td><div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" style="width:${barPct}%;background:${i===0?'#f59e0b':i===1?'#6b7280':'#1e5799'}"></div></div><span class="bar-lbl">${barPct}%</span></div></td>
    </tr>`;
  });
  html += `</table></div>`;

  // Monthly trend table
  if (d.trend && d.trend.length) {
    const months = [...new Set(d.trend.map(r=>r.month))].sort();
    const smen   = [...new Set(d.trend.map(r=>r.smname))];
    const trendMap = {};
    d.trend.forEach(r=>{ if(!trendMap[r.smname]) trendMap[r.smname]={}; trendMap[r.smname][r.month]={amt:r.total_amt,bills:r.bill_count}; });

    html += `<div class="tbl-wrap" style="margin-top:12px">
      <div class="tbl-title">Monthly Sales Trend (Last 6 Months)</div>
      <table><tr><th>Salesman</th>${months.map(mo=>`<th class="r">${mo}</th>`).join('')}<th class="r">Total</th></tr>`;
    smen.forEach(sm => {
      let total = 0;
      const cells = months.map(mo => {
        const v = trendMap[sm]?.[mo]?.amt || 0;
        total += v;
        return `<td class="r">${v>0?'₹'+m(v):'—'}</td>`;
      }).join('');
      html += `<tr><td><b>${sm}</b></td>${cells}<td class="r"><b>₹${m(total)}</b></td></tr>`;
    });
    html += `</table></div>`;
  }

  document.getElementById('mainArea').innerHTML = html;
};

/* ── 7. Term Summary ─────────────────────────────────────────── */
render['term-summary'] = function(d, p) {
  let html = toolbar(p.from, p.to);
  const g = d.grand;
  html += `<div class="cards">
    <div class="card blue"><div class="card-label">Active Bills</div><div class="card-val">${n(g.active_bills)}</div></div>
    <div class="card"><div class="card-label">Total Sales</div><div class="card-val">₹${m(g.total_amt)}</div></div>
    <div class="card green"><div class="card-label">Net Amount</div><div class="card-val">₹${m(g.net_amt)}</div></div>
    <div class="card orange"><div class="card-label">Discount</div><div class="card-val">₹${m(g.total_disc)}</div></div>
    <div class="card"><div class="card-label">Commission</div><div class="card-val">₹${m(g.commission)}</div></div>
  </div>`;
  if (!d.rows.length) { html += noData(); document.getElementById('mainArea').innerHTML = html; return; }
  const months = [...new Set(d.rows.map(r=>r.month))].sort();
  const smen   = [...new Set(d.rows.map(r=>r.smcode))];
  const map = {};
  d.rows.forEach(r=>{ if(!map[r.smcode]) map[r.smcode]={name:r.smname}; map[r.smcode][r.month]=r; });
  html += `<div class="tbl-wrap">
    <div class="tbl-title">Term (Month-wise) Summary — ${p.from} to ${p.to}</div>
    <table><tr><th>Salesman</th>${months.map(mo=>`<th class="r">${mo}</th>`).join('')}<th class="r">Total</th></tr>`;
  smen.forEach(code => {
    let total = 0;
    const cells = months.map(mo => {
      const r = map[code]?.[mo];
      if (!r) return `<td class="r">—</td>`;
      total += Number(r.total_amt||0);
      return `<td class="r"><div style="font-size:11px">₹${m(r.total_amt)}</div><div style="font-size:10px;color:#94a3b8">${r.active_bills} bills</div></td>`;
    }).join('');
    html += `<tr><td><b>${map[code].name}</b><br><small style="color:#94a3b8">${code}</small></td>${cells}<td class="r"><b>₹${m(total)}</b></td></tr>`;
  });
  html += `</table></div>`;
  document.getElementById('mainArea').innerHTML = html;
};

/* ── 8. Attendance ────────────────────────────────────────────── */
render['attendance'] = function(d, p) {
  const staffOpts = d.sman.map(s=>({code:s.code,name:s.name}));
  let html = toolbar(p.from, p.to, staffSelect(staffOpts, p.staff||'', 'Staff'), false);
  html = html.replace('onclick="applyFilter()"', 'onclick="applyStaffFilter()"');
  if (!d.summary.length && !d.rows.length) {
    html += noData('No attendance records found. Check staffcheckin table.');
    document.getElementById('mainArea').innerHTML = html;
    return;
  }
  // Summary cards
  const present = d.summary.reduce((s,r)=>s+Number(r.present||0),0);
  const absent  = d.summary.reduce((s,r)=>s+Number(r.absent||0),0);
  const late    = d.summary.reduce((s,r)=>s+Number(r.late||0),0);
  html += `<div class="cards">
    <div class="card blue"><div class="card-label">Staff Count</div><div class="card-val">${d.summary.length}</div></div>
    <div class="card green"><div class="card-label">Present Days</div><div class="card-val">${n(present)}</div></div>
    <div class="card red"><div class="card-label">Absent Days</div><div class="card-val">${n(absent)}</div></div>
    <div class="card orange"><div class="card-label">Late Days</div><div class="card-val">${n(late)}</div></div>
  </div>`;
  // Summary table
  html += `<div class="tbl-wrap"><div class="tbl-title">Attendance Summary</div><table>
    <tr><th>Staff</th><th class="c">Total Days</th><th class="c">Present</th><th class="c">Absent</th><th class="c">Late</th></tr>`;
  d.summary.forEach(r => {
    html += `<tr><td><b>${r.name}</b><small style="color:#94a3b8"> (${r.code})</small></td>
      <td class="c">${r.total_days}</td>
      <td class="c"><span class="badge badge-green">${r.present}</span></td>
      <td class="c">${r.absent>0?`<span class="badge badge-red">${r.absent}</span>`:'—'}</td>
      <td class="c">${r.late>0?`<span class="badge badge-orange">${r.late}</span>`:'—'}</td>
    </tr>`;
  });
  html += `</table></div>`;
  // Detail table
  if (d.rows.length) {
    html += `<div class="tbl-wrap" style="margin-top:10px"><div class="tbl-title">Day-wise Detail</div><table>
      <tr><th>Date</th><th>Staff</th><th>In Time</th><th>Out Time</th><th class="c">Punches</th><th class="c">Status</th></tr>`;
    d.rows.forEach(r => {
      const st = r.stat||'P';
      const badge = st==='A'?'badge-red':st==='L'?'badge-orange':'badge-green';
      html += `<tr><td>${r.tdate}</td><td>${r.name} <small style="color:#94a3b8">(${r.code})</small></td>
        <td>${r.in_time||'—'}</td><td>${r.out_time||'—'}</td>
        <td class="c">${r.punches}</td>
        <td class="c"><span class="badge ${badge}">${st||'P'}</span></td>
      </tr>`;
    });
    html += `</table></div>`;
  }
  document.getElementById('mainArea').innerHTML = html;
};

/* ── 9. Leave ────────────────────────────────────────────────── */
render['leave'] = function(d, p) {
  const staffOpts = d.sman.map(s=>({code:s.code,name:s.name}));
  let html = toolbar(p.from, p.to, staffSelect(staffOpts, p.staff||'', 'Staff'), false);
  html = html.replace('onclick="applyFilter()"', 'onclick="applyStaffFilter()"');
  const g = d.grand;
  html += `<div class="cards">
    <div class="card blue"><div class="card-label">Staff</div><div class="card-val">${d.summary.length}</div></div>
    <div class="card orange"><div class="card-label">Total Leave Days</div><div class="card-val">${n(g.total_leaves)}</div></div>
    <div class="card green"><div class="card-label">Comp-off Days</div><div class="card-val">${n(g.plus_days)}</div></div>
    <div class="card red"><div class="card-label">Net Leave</div><div class="card-val">${n(g.total_leaves - g.plus_days)}</div></div>
  </div>`;
  if (!d.rows.length) { html += noData('No leave records found.'); document.getElementById('mainArea').innerHTML = html; return; }
  // Summary
  html += `<div class="tbl-wrap"><div class="tbl-title">Leave Summary</div><table>
    <tr><th>Staff</th><th class="c">Entries</th><th class="r">Leave Days</th><th class="r">Comp-off</th><th class="r">Net Leave</th></tr>`;
  d.summary.forEach(r => {
    html += `<tr><td><b>${r.name}</b> <small style="color:#94a3b8">(${r.code})</small></td>
      <td class="c">${r.entries}</td>
      <td class="r"><span class="badge badge-orange">${r.total_leaves}</span></td>
      <td class="r">${r.plus_days>0?`<span class="badge badge-green">${r.plus_days}</span>`:'—'}</td>
      <td class="r"><b>${r.net_leaves}</b></td>
    </tr>`;
  });
  html += `</table></div>`;
  // Detail
  html += `<div class="tbl-wrap" style="margin-top:10px"><div class="tbl-title">Leave Detail</div><table>
    <tr><th>Date</th><th>Staff</th><th>Leave Type</th><th class="r">Days</th><th>Reason</th><th class="r">Comp-off</th></tr>`;
  d.rows.forEach(r => {
    html += `<tr><td>${r.tdate}</td><td>${r.name} <small style="color:#94a3b8">(${r.staff})</small></td>
      <td>${r.leave||'—'}</td>
      <td class="r"><span class="badge badge-orange">${r.ldays}</span></td>
      <td>${r.reason||'—'}</td>
      <td class="r">${r.plusdays>0?`<span class="badge badge-green">${r.plusdays}</span>`:'—'}</td>
    </tr>`;
  });
  html += `</table></div>`;
  document.getElementById('mainArea').innerHTML = html;
};

/* ── 10. Staff Log ───────────────────────────────────────────── */
render['staff-log'] = function(d, p) {
  const staffOpts = d.sman.map(s=>({code:s.code,name:s.name}));
  let html = toolbar(p.from, p.to, staffSelect(staffOpts, p.staff||'', 'Staff'), false);
  html = html.replace('onclick="applyFilter()"', 'onclick="applyStaffFilter()"');
  if (!d.rows.length) { html += noData('No staff log entries found.'); document.getElementById('mainArea').innerHTML = html; return; }
  html += `<div class="cards">
    <div class="card blue"><div class="card-label">Total Entries</div><div class="card-val">${d.rows.length}</div></div>
    <div class="card green"><div class="card-label">Login Events</div><div class="card-val">${d.summary.reduce((s,r)=>s+Number(r.login||0),0)}</div></div>
    <div class="card orange"><div class="card-label">Logout Events</div><div class="card-val">${d.summary.reduce((s,r)=>s+Number(r.logout||0),0)}</div></div>
  </div>`;
  html += `<div class="tbl-wrap"><div class="tbl-title">Staff Log Detail</div><table>
    <tr><th>Date</th><th>Time</th><th>Staff</th><th>ID No</th><th class="c">Action</th></tr>`;
  d.rows.forEach(r => {
    const isLogin = r.status==='L';
    html += `<tr><td>${r.tdate}</td><td>${r.ttime||'—'}</td>
      <td>${r.name} <small style="color:#94a3b8">(${r.scode})</small></td>
      <td>${r.idno||'—'}</td>
      <td class="c"><span class="badge ${isLogin?'badge-green':'badge-red'}">${isLogin?'Login':'Logout'}</span></td>
    </tr>`;
  });
  html += `</table></div>`;
  document.getElementById('mainArea').innerHTML = html;
};

/* ── 11. Ledger ──────────────────────────────────────────────── */
render['ledger'] = function(d, p) {
  const staffOpts = d.sman.map(s=>({code:s.code,name:s.name}));
  let html = toolbar(p.from, p.to, staffSelect(staffOpts, p.staff||'', 'Staff'), false);
  html = html.replace('onclick="applyFilter()"', 'onclick="applyStaffFilter()"');
  if (!d.rows.length) { html += noData('No attendance/ledger records found.'); document.getElementById('mainArea').innerHTML = html; return; }
  html += `<div class="tbl-wrap"><div class="tbl-title">Staff Ledger — ${p.from} to ${p.to}</div><table>
    <tr><th>Date</th><th>Staff</th><th>In Time</th><th>Out Time</th><th class="c">Status</th><th>Leave Type</th><th class="r">Leave Days</th></tr>`;
  d.rows.forEach(r => {
    const st = r.status||'P';
    const badge = st==='A'?'badge-red':st==='L'?'badge-orange':'badge-green';
    html += `<tr><td>${r.tdate}</td>
      <td>${r.name} <small style="color:#94a3b8">(${r.code})</small></td>
      <td>${r.in_time||'—'}</td><td>${r.out_time||'—'}</td>
      <td class="c"><span class="badge ${badge}">${st}</span></td>
      <td>${r.leave||'—'}</td>
      <td class="r">${r.ldays>0?r.ldays:'—'}</td>
    </tr>`;
  });
  html += `</table></div>`;
  document.getElementById('mainArea').innerHTML = html;
};

/* ── 12. Weight Trans ────────────────────────────────────────── */
render['wgt-trans'] = function(d, p) {
  const staffOpts = d.sman.map(s=>({code:s.code,name:s.name}));
  let html = toolbar(p.from, p.to, staffSelect(staffOpts, p.staff||'', 'Staff'), false);
  html = html.replace('onclick="applyFilter()"', 'onclick="applyStaffFilter()"');
  const g = d.grand;
  html += `<div class="cards">
    <div class="card blue"><div class="card-label">Total Qty</div><div class="card-val">${n(g.total_qty)}</div></div>
    <div class="card"><div class="card-label">Gross Weight</div><div class="card-val">${Number(g.total_wgt||0).toFixed(3)} g</div></div>
    <div class="card green"><div class="card-label">Net Weight</div><div class="card-val">${Number(g.total_net||0).toFixed(3)} g</div></div>
  </div>`;
  if (!d.rows.length) { html += noData('No weight transactions found.'); document.getElementById('mainArea').innerHTML = html; return; }
  // Summary per staff
  if (d.summary.length > 1) {
    html += `<div class="tbl-wrap"><div class="tbl-title">Summary by Staff</div><table>
      <tr><th>Staff</th><th class="c">Entries</th><th class="r">Qty</th><th class="r">Gross Wgt</th><th class="r">Net Wgt</th></tr>`;
    d.summary.forEach(r => {
      html += `<tr><td><b>${r.name}</b> <small style="color:#94a3b8">(${r.code})</small></td>
        <td class="c">${r.entries}</td>
        <td class="r">${n(r.total_qty)}</td>
        <td class="r">${Number(r.total_wgt||0).toFixed(3)} g</td>
        <td class="r">${Number(r.total_net||0).toFixed(3)} g</td>
      </tr>`;
    });
    html += `</table></div>`;
  }
  // Detail
  html += `<div class="tbl-wrap" style="margin-top:10px"><div class="tbl-title">Transaction Detail</div><table>
    <tr><th>Date</th><th>Doc No</th><th>Staff</th><th>Item</th><th class="r">Qty</th><th class="r">Gross Wgt</th><th class="r">Net Wgt</th><th>Type</th><th>Route</th></tr>`;
  d.rows.forEach(r => {
    html += `<tr><td>${r.tdate}</td><td>${r.docno||'—'}</td>
      <td>${r.staff_name} <small style="color:#94a3b8">(${r.staff})</small></td>
      <td>${r.item_name} <small style="color:#94a3b8">(${r.item_code})</small></td>
      <td class="r">${n(r.qty)}</td>
      <td class="r">${Number(r.weight||0).toFixed(3)}</td>
      <td class="r">${Number(r.net_wgt||0).toFixed(3)}</td>
      <td><span class="badge badge-blue">${r.ttype||'—'}</span></td>
      <td>${r.route||'—'}</td>
    </tr>`;
  });
  html += `</table></div>`;
  document.getElementById('mainArea').innerHTML = html;
};

/* ── Init ───────────────────────────────────────────────────── */
// Set subtitle and load report
document.getElementById('subTitle').textContent = '— ' + (SUB_LABELS[currentSub] || currentSub);
loadSub(currentSub);
</script>
</body>
</html>
