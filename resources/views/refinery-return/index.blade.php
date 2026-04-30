<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('js/goldapp-common.js') }}"></script>
<title>{{ $title ?? 'Refinery Return Details' }}</title>
<script>
window.RR_CONFIG = {
  siteUrl:   @json(request()->root()),
  mode:      @json($mode ?? 'bill'),
  salesmen:  @json($salesmen ?? []),
  software:  @json($software ?? []),
};
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',system-ui,-apple-system,sans-serif;font-size:13px;color:#1e293b;background:#f1f5f9;overflow:hidden;height:100vh}

/* ── Layout ─────────────────────────────────────────────── */
.app{display:flex;flex-direction:column;height:100vh;background:#fff;margin:0 auto}
.header{background:linear-gradient(135deg,#7c3aed 0%,#a78bfa 100%);color:#fff;padding:8px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0}
.header h1{font-size:15px;font-weight:700;letter-spacing:.3px}
.header .mode-badge{background:rgba(255,255,255,.2);padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600}

/* ── Form Section ───────────────────────────────────────── */
.form-section{padding:8px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-shrink:0}
.form-row{display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap}
.form-row:last-child{margin-bottom:0}
.form-row label{font-weight:600;font-size:11px;color:#475569;white-space:nowrap}
.form-row input,.form-row select{height:28px;padding:0 8px;border:1px solid #cbd5e1;border-radius:5px;font-size:11px;color:#1e293b;background:#fff;transition:all .2s}
.form-row input:focus,.form-row select:focus{outline:none;border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.15)}
.form-row input.ro{background:#f1f5f9;color:#64748b;cursor:default}
.btn-icon{width:28px;height:28px;border:1px solid #cbd5e1;border-radius:5px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;color:#475569;transition:all .15s}
.btn-icon:hover{background:#f1f5f9;border-color:#7c3aed;color:#7c3aed}

/* ── Summary Section ───────────────────────────────────── */
.summary-section{display:flex;flex-wrap:wrap;gap:6px;padding:6px 16px;background:#faf5ff;border-bottom:1px solid #e9d5ff;flex-shrink:0}
.stat-box{display:flex;flex-direction:column;align-items:center;background:#fff;border:1px solid #e9d5ff;border-radius:6px;padding:4px 10px;min-width:85px}
.stat-box .s-label{font-size:9px;font-weight:600;color:#7c3aed;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
.stat-box .s-value{font-size:13px;font-weight:700;color:#1e293b;font-variant-numeric:tabular-nums}

/* ── Items Table ────────────────────────────────────────── */
.table-section{flex:1;display:flex;flex-direction:column;overflow:hidden;padding:0 8px}
.table-container{flex:1;overflow:auto;border:1px solid #e2e8f0;border-radius:6px;background:#fff}
table.grid{width:100%;border-collapse:collapse;font-size:11px;table-layout:fixed}
table.grid thead{position:sticky;top:0;z-index:2}
table.grid thead th{background:linear-gradient(180deg,#7c3aed 0%,#6d28d9 100%);color:#fff;padding:6px 3px;font-weight:600;font-size:9.5px;text-align:center;white-space:nowrap;border-right:1px solid rgba(255,255,255,.2);letter-spacing:.3px;text-transform:uppercase}
table.grid thead th:last-child{border-right:none}
table.grid thead th.rcvd-col{background:linear-gradient(180deg,#10b981 0%,#059669 100%)}
table.grid thead th.issued-col{background:linear-gradient(180deg,#f59e0b 0%,#d97706 100%)}
table.grid tbody td{padding:1px 2px;border-bottom:1px solid #f1f5f9;text-align:center;height:28px;vertical-align:middle}
table.grid tbody tr:hover td{background:#faf5ff}
table.grid tbody input{width:100%;height:24px;border:1px solid transparent;border-radius:3px;font-size:11px;text-align:right;background:transparent;color:#1e293b;padding:0 3px;transition:all .15s}
table.grid tbody input:focus{border-color:#7c3aed;background:#fff;box-shadow:0 0 0 2px rgba(124,58,237,.15);outline:none}
table.grid tbody td.ro{color:#64748b;font-size:10px;background:#fafafa;text-align:right;padding-right:4px;font-variant-numeric:tabular-nums}
table.grid tbody td.name-cell{text-align:left;padding-left:6px;font-size:10px;color:#334155}
table.grid tbody td.computed{background:#f0fdf4;color:#166534;font-weight:600;text-align:right;padding-right:4px;font-variant-numeric:tabular-nums}

/* ── Bottom Section ─────────────────────────────────────── */
.bottom-section{display:flex;align-items:center;padding:8px 16px;background:#f8fafc;border-top:1px solid #e2e8f0;gap:12px;flex-shrink:0;flex-wrap:wrap}
.bottom-section label{font-weight:600;font-size:11px;color:#475569;white-space:nowrap}
.bottom-section input{height:30px;padding:0 8px;border:1px solid #cbd5e1;border-radius:5px;font-size:12px;color:#1e293b;background:#fff;transition:all .2s}
.bottom-section input:focus{outline:none;border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.15)}
.bottom-section input.ro{background:#faf5ff;color:#6d28d9;font-weight:600;border-color:#d8b4fe}
.bottom-section .spacer{flex:1}
.btn-action{height:34px;padding:0 20px;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:6px}
.btn-save{background:#10b981;color:#fff}
.btn-save:hover{background:#059669}
.btn-new{background:#2563eb;color:#fff}
.btn-new:hover{background:#1d4ed8}
.btn-exit{background:#64748b;color:#fff;margin-left:6px}
.btn-exit:hover{background:#475569}

/* ── Modal ──────────────────────────────────────────────── */
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.5);display:none;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(2px)}
.modal-overlay.active{display:flex}
.modal-panel{background:#fff;border-radius:12px;box-shadow:0 25px 50px rgba(0,0,0,.2);min-width:500px;max-width:90vw;max-height:85vh;overflow:hidden;display:flex;flex-direction:column}
.modal-panel .m-head{padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between}
.modal-panel .m-head h3{font-size:14px;font-weight:600;color:#1e293b}
.modal-panel .m-head .m-close{width:28px;height:28px;border:none;background:#f1f5f9;border-radius:6px;cursor:pointer;font-size:16px;color:#64748b;display:flex;align-items:center;justify-content:center}
.modal-panel .m-head .m-close:hover{background:#e2e8f0;color:#1e293b}
.modal-panel .m-body{padding:12px 16px;overflow-y:auto;flex:1}
.modal-panel .m-foot{padding:10px 16px;border-top:1px solid #e2e8f0;display:flex;gap:8px;justify-content:flex-end}
.modal-panel .m-foot button{height:32px;padding:0 16px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;font-size:12px;font-weight:500;cursor:pointer}
.modal-panel .m-foot button:hover{background:#f1f5f9}

.search-input{width:100%;height:34px;padding:0 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;margin-bottom:8px}
.search-input:focus{outline:none;border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.15)}
.search-list{max-height:320px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px}
.search-list table{width:100%;border-collapse:collapse;font-size:12px}
.search-list table th{background:#f8fafc;padding:8px 10px;text-align:left;font-weight:600;font-size:11px;color:#64748b;position:sticky;top:0;border-bottom:1px solid #e2e8f0}
.search-list table td{padding:6px 10px;border-bottom:1px solid #f1f5f9;cursor:pointer}
.search-list table tr:hover td{background:#faf5ff}

/* ── Msg modal ──────────────────────────────────────────── */
.msg-modal{position:fixed;inset:0;background:rgba(15,23,42,.5);display:none;align-items:center;justify-content:center;z-index:12000;backdrop-filter:blur(2px)}
.msg-modal.active{display:flex}
.msg-box{width:min(400px,calc(100vw - 24px));background:#fff;border-radius:12px;box-shadow:0 25px 50px rgba(0,0,0,.2);overflow:hidden}
.msg-box .head{padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:600;font-size:13px;color:#1e293b}
.msg-box .body{padding:16px;font-size:13px;color:#475569;line-height:1.5;white-space:pre-wrap}
.msg-box .foot{padding:10px 16px;border-top:1px solid #e2e8f0;display:flex;justify-content:center}
.msg-box .ok-btn{height:32px;padding:0 24px;background:#7c3aed;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer}
.msg-box .ok-btn:hover{background:#6d28d9}

::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:#f1f5f9}
::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:#94a3b8}
</style>
</head>
<body>
<div class="app" id="mainApp">

  <!-- ── Header ────────────────────────────────────────────── -->
  <div class="header">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4"/></svg>
    <h1>{{ $title ?? 'Refinery Return Details' }}</h1>
    @if(($mode ?? 'bill') === 'edit')
      <span class="mode-badge">Edit Mode</span>
    @endif
  </div>

  <!-- ── Form Fields ───────────────────────────────────────── -->
  <div class="form-section">
    <div class="form-row">
      <label style="min-width:70px">Orig Doc :</label>
      <input type="text" id="fOrigDocNo" style="width:140px;text-transform:uppercase" placeholder="Original Doc No...">
      <button class="btn-icon" id="btnLoad" title="Load Entry">&#8629;</button>
      <button class="btn-icon" id="btnSearchHelp" title="Search Entry (F1)">?</button>
      <span style="width:12px"></span>
      <label>Return No :</label>
      <input type="text" id="fReturnDocNo" class="ro" readonly style="width:140px" placeholder="Auto">
      <span style="width:12px"></span>
      <label>Date :</label>
      <input type="text" id="fDate" style="width:100px;text-align:center" placeholder="dd/mm/yyyy">
    </div>
    <div class="form-row">
      <label style="min-width:70px">Refiner :</label>
      <input type="text" id="fRefCode" class="ro" readonly style="width:80px">
      <input type="text" id="fRefName" class="ro" readonly style="width:220px" placeholder="Refiner Name">
      <span style="width:12px"></span>
      <label>SM :</label>
      <select id="fSmCode" style="width:160px;height:28px;font-size:11px">
        <option value="">-- Select --</option>
        @foreach($salesmen as $sm)
          <option value="{{ $sm['code'] }}">{{ $sm['code'] }} - {{ $sm['name'] }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <!-- ── Summary Section ──────────────────────────────────── -->
  <div class="summary-section">
    <div class="stat-box"><span class="s-label">Issued Wgt</span><span class="s-value" id="sIssuedWgt">0.000</span></div>
    <div class="stat-box"><span class="s-label">St.Wg</span><span class="s-value" id="sStoneWgt">0.000</span></div>
    <div class="stat-box"><span class="s-label">Bottle Stk</span><span class="s-value" id="sBottleStk">0.000</span></div>
    <div class="stat-box"><span class="s-label">Mud</span><span class="s-value" id="sMud">0.000</span></div>
    <div class="stat-box"><span class="s-label">Rcvd Wgt</span><span class="s-value" id="sRcvdWgt" style="color:#059669">0.000</span></div>
    <div class="stat-box"><span class="s-label">Test Pcs</span><span class="s-value" id="sTestPcs">0.000</span></div>
    <div class="stat-box"><span class="s-label">Net Issued</span><span class="s-value" id="sNetIssued" style="color:#dc2626">0.000</span></div>
    <div class="stat-box"><span class="s-label">TP %</span><span class="s-value" id="sTestPerc">0.00</span></div>
    <div class="stat-box"><span class="s-label">Net Issd Amt</span><span class="s-value" id="sNetIssuedAmt">0.00</span></div>
  </div>

  <!-- ── Items Table ───────────────────────────────────────── -->
  <div class="table-section">
    <div class="table-container">
      <table class="grid" id="itemsTable">
        <thead>
          <tr>
            <th style="width:70px">Item<br>Code</th>
            <th style="width:120px">Item Name</th>
            <th class="issued-col" style="width:75px">Old Issd<br>Wgt</th>
            <th class="rcvd-col" style="width:60px">Rcvd<br>Touch</th>
            <th class="rcvd-col" style="width:60px">Mud<br>Less</th>
            <th class="rcvd-col" style="width:55px">Rcvd<br>Qty</th>
            <th class="rcvd-col" style="width:75px">Rcvd<br>Wgt</th>
            <th class="rcvd-col" style="width:65px">Rate</th>
            <th class="rcvd-col" style="width:80px">Rcvd Wgt<br>Amt</th>
            <th class="rcvd-col" style="width:65px">Bottle<br>Stk</th>
            <th class="rcvd-col" style="width:60px">Test<br>Pcs</th>
            <th class="issued-col" style="width:50px">Issu<br>Qty</th>
            <th class="issued-col" style="width:75px">Issued<br>Wgt</th>
            <th class="issued-col" style="width:80px">Issued<br>Wgt Amt</th>
          </tr>
        </thead>
        <tbody id="itemsTbody"></tbody>
      </table>
    </div>
  </div>

  <!-- ── Bottom Section ────────────────────────────────────── -->
  <div class="bottom-section">
    <label>Charge Amt :</label>
    <input type="text" id="fCharge" style="width:110px;text-align:right" value="0.00">
    <label>Net Balance :</label>
    <input type="text" id="fNetBalance" class="ro" readonly style="width:110px;text-align:right" value="0.00">
    <label>Note :</label>
    <input type="text" id="fNote" style="width:200px" maxlength="40" placeholder="Enter note...">
    <label>Amt Paid :</label>
    <input type="text" id="fPaidAmt" style="width:110px;text-align:right" value="0.00">
    <span class="spacer"></span>
    @if(($mode ?? 'bill') === 'edit')
    <button class="btn-action btn-new" id="btnNew">New Entry</button>
    @endif
    <button class="btn-action btn-save" id="btnSave">Save (F9)</button>
    <button class="btn-action btn-exit" id="btnExit">Exit</button>
  </div>

</div>

<!-- ── Message Modal ───────────────────────────────────────── -->
<div class="msg-modal" id="msgModal">
  <div class="msg-box">
    <div class="head" id="msgModalTitle">Message</div>
    <div class="body" id="msgModalText"></div>
    <div class="foot"><button class="ok-btn" id="msgModalOk">OK</button></div>
  </div>
</div>

<!-- ── Entry Search Modal ──────────────────────────────────── -->
<div class="modal-overlay" id="entrySearchModal">
  <div class="modal-panel" style="min-width:640px">
    <div class="m-head">
      <h3 id="searchModalTitle">Search Refinery Entry</h3>
      <button class="m-close" data-close="entrySearchModal">&times;</button>
    </div>
    <div class="m-body">
      <input type="text" class="search-input" id="entrySearchQ" placeholder="Search by Doc No or Refiner Code...">
      <div class="search-list" id="entrySearchResults"></div>
    </div>
    <div class="m-foot">
      <button data-close="entrySearchModal">Close</button>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<script>
(function(){
'use strict';
const CFG = window.RR_CONFIG;
const API = CFG.siteUrl;
const csrf = document.querySelector('meta[name="csrf-token"]').content;

// ── State ──────────────────────────────────────────────────
let itemRows = [];
let loadedOrigDocNo = '';
let loadedReturnDocNo = '';
let origSlno = 0;
let origControl = 1;
let isEditMode = false;
let subMode = CFG.mode; // 'edit' or 'bill' — can switch within edit page

// Summary from original entry
let summaryData = {
  issued_wgt:0, stone_wgt:0, bottle_stk:0, mud:0,
  test_pcs:0, net_issued:0, test_perc:0, net_issued_amt:0
};

// ── Helpers ────────────────────────────────────────────────
const $ = id => document.getElementById(id);
const toNum = v => { let n = parseFloat(String(v).replace(/,/g,'')); return isNaN(n)?0:n; };
const fmt = (v,d=2) => toNum(v).toFixed(d);
const fmt3 = v => toNum(v).toFixed(3);
const thcTouch = toNum(CFG.software.THCTouch || 100) || 100;

function showMsg(title, text) {
  $('msgModalTitle').textContent = title;
  $('msgModalText').textContent = text;
  $('msgModal').classList.add('active');
}
function closeModal(id) { $(id).classList.remove('active'); }

function api(url, opts = {}) {
  const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
  if (opts.json) {
    headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(opts.json);
    delete opts.json;
  }
  opts.headers = { ...(opts.headers||{}), ...headers };
  return fetch(API + url, opts).then(r => r.json());
}

// ── Update summary display ─────────────────────────────────
function updateSummary() {
  $('sIssuedWgt').textContent = fmt3(summaryData.issued_wgt);
  $('sStoneWgt').textContent = fmt3(summaryData.stone_wgt);
  $('sBottleStk').textContent = fmt3(summaryData.bottle_stk);
  $('sMud').textContent = fmt3(summaryData.mud);
  $('sTestPcs').textContent = fmt3(summaryData.test_pcs);
  $('sNetIssued').textContent = fmt3(summaryData.net_issued);
  $('sTestPerc').textContent = fmt(summaryData.test_perc);
  $('sNetIssuedAmt').textContent = fmt(summaryData.net_issued_amt);

  // Received wgt = sum of rcvd_wgt from items
  let tRcvd = 0;
  itemRows.forEach(r => { tRcvd += toNum(r.rcvd_wgt); });
  $('sRcvdWgt').textContent = fmt3(tRcvd);
}

// ── Calculate balance ──────────────────────────────────────
function calcBalance() {
  const charge = toNum($('fCharge').value);
  const paid = toNum($('fPaidAmt').value);
  const balance = charge - paid;
  $('fNetBalance').value = fmt(balance);
}

// ── Auto-calculate row fields ──────────────────────────────
function calcRow(r, triggerCol = '') {
  const rowIssuedWgt = toNum(r.oissuedwgt || r.issued_wgt);
  const rcvdTouch = toNum(r.rcvd_touch);
  const mudLess = toNum(r.mud_less);

  if (triggerCol === 'rcvd_touch' && toNum(summaryData.net_issued) > 0 && rcvdTouch > 0) {
    r.rcvd_wgt = Math.max(0, Math.round(((toNum(summaryData.net_issued) * rcvdTouch) / thcTouch) * 1000) / 1000);
  }

  if (triggerCol === 'mud_less' && rowIssuedWgt > 0) {
    r.rcvd_wgt = Math.max(0, Math.round((rowIssuedWgt - mudLess) * 1000) / 1000);
  }

  r.rcvd_wgt_amt = Math.round(toNum(r.rcvd_wgt) * toNum(r.rate) * 100) / 100;
}

function updateTestPercent(row, triggerCol = '') {
  if (!['rcvd_wgt','mud_less','bottle_stk','rcvd_touch'].includes(triggerCol)) return;
  const netIssued = toNum(summaryData.net_issued);
  if (netIssued <= 0) {
    summaryData.test_perc = 0;
    return;
  }
  const testPerc = ((toNum(row.rcvd_wgt) - toNum(row.bottle_stk)) / netIssued) * 100;
  summaryData.test_perc = Math.round(testPerc * 100) / 100;
}

function applyItemLookupToRow(idx, payload) {
  const row = itemRows[idx];
  if (!row || !payload) return;
  row.item_code = String(payload.code || row.item_code || '').trim().toUpperCase();
  row.lookup_code = row.item_code;
  row.item_name = payload.name || '';
  row.cost = toNum(payload.cost);
  row.touch = toNum(payload.touch);
  if (!toNum(row.rcvd_touch) && toNum(payload.touch) > 0) row.rcvd_touch = toNum(payload.touch);
  if (!toNum(row.rate)) row.rate = toNum(payload.cost);
  if (payload.defstktype) row.stktype = payload.defstktype;
  calcRow(row, 'item_code');
}

function lookupReturnItem(idx, nextCol = 'rcvd_touch') {
  const row = itemRows[idx];
  if (!row) return;
  const code = String(row.item_code || '').trim().toUpperCase();
  row.item_code = code;
  if (!code) {
    row.item_name = '';
    row.lookup_code = '';
    renderItemsTable();
    return;
  }
  if (code === String(row.lookup_code || '').trim().toUpperCase() && row.item_name) {
    renderItemsTable();
    setTimeout(() => {
      const input = document.querySelector(`#itemsTbody input[data-col="${nextCol}"][data-idx="${idx}"]`);
      if (input) { input.focus(); input.select(); }
    }, 30);
    return;
  }
  api(`/api/refinery-bill/item-lookup?code=${encodeURIComponent(code)}`)
    .then(d => {
      if (!d.ok) {
        showMsg('Error', d.message || 'Invalid Item');
        row.item_code = String(row.lookup_code || '').trim().toUpperCase();
        renderItemsTable();
        setTimeout(() => {
          const input = document.querySelector(`#itemsTbody input[data-col="item_code"][data-idx="${idx}"]`);
          if (input) { input.focus(); input.select(); }
        }, 30);
        return;
      }
      applyItemLookupToRow(idx, d);
      renderItemsTable();
      setTimeout(() => {
        const input = document.querySelector(`#itemsTbody input[data-col="${nextCol}"][data-idx="${idx}"]`);
        if (input) { input.focus(); input.select(); }
      }, 30);
    });
}

// ── Render items table ─────────────────────────────────────
function renderItemsTable() {
  const tbody = $('itemsTbody');
  tbody.innerHTML = '';
  itemRows.forEach((row, idx) => {
    const tr = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML = `
      <td><input data-col="item_code" data-idx="${idx}" value="${row.item_code||''}" style="text-align:center;text-transform:uppercase"></td>
      <td class="name-cell">${row.item_name||''}</td>
      <td class="ro">${fmt3(row.oissuedwgt||row.issued_wgt)}</td>
      <td><input data-col="rcvd_touch" data-idx="${idx}" value="${fmt(row.rcvd_touch)}"></td>
      <td><input data-col="mud_less" data-idx="${idx}" value="${fmt3(row.mud_less)}"></td>
      <td><input data-col="rcvd_qty" data-idx="${idx}" value="${row.rcvd_qty||0}" style="text-align:center"></td>
      <td><input data-col="rcvd_wgt" data-idx="${idx}" value="${fmt3(row.rcvd_wgt)}"></td>
      <td><input data-col="rate" data-idx="${idx}" value="${fmt(row.rate)}"></td>
      <td class="computed">${fmt(row.rcvd_wgt_amt)}</td>
      <td><input data-col="bottle_stk" data-idx="${idx}" value="${fmt3(row.bottle_stk)}"></td>
      <td><input data-col="test_pcs" data-idx="${idx}" value="${fmt3(row.test_pcs)}"></td>
      <td class="ro" style="text-align:center">${row.issued_qty||0}</td>
      <td class="ro">${fmt3(row.issued_wgt)}</td>
      <td class="ro">${fmt(row.issued_wgt_amt)}</td>
    `;
    tbody.appendChild(tr);
  });
  updateSummary();
}

// ── Input events ───────────────────────────────────────────
const editableCols = ['item_code','rcvd_touch','mud_less','rcvd_qty','rcvd_wgt','rate','bottle_stk','test_pcs'];
const enterOrder = ['item_code','rcvd_touch','mud_less','rcvd_qty','rcvd_wgt','rate','bottle_stk','test_pcs'];

$('itemsTbody').addEventListener('blur', function(e) {
  const inp = e.target;
  if (inp.tagName !== 'INPUT') return;
  const idx = parseInt(inp.dataset.idx);
  const col = inp.dataset.col;
  if (isNaN(idx) || !col || !itemRows[idx]) return;
  if (col === 'item_code') {
    itemRows[idx][col] = String(inp.value || '').trim().toUpperCase();
    lookupReturnItem(idx, 'rcvd_touch');
    return;
  }
  itemRows[idx][col] = toNum(inp.value);
  calcRow(itemRows[idx], col);
  updateTestPercent(itemRows[idx], col);
  renderItemsTable();
}, true);

$('itemsTbody').addEventListener('keydown', function(e) {
  const inp = e.target;
  if (inp.tagName !== 'INPUT') return;
  const idx = parseInt(inp.dataset.idx);
  const col = inp.dataset.col;
  if (isNaN(idx) || !col) return;

  if (e.key === 'Enter') {
    e.preventDefault();
    if (col === 'item_code') {
      itemRows[idx][col] = String(inp.value || '').trim().toUpperCase();
      lookupReturnItem(idx, 'rcvd_touch');
      return;
    }
    itemRows[idx][col] = toNum(inp.value);
    calcRow(itemRows[idx], col);
    updateTestPercent(itemRows[idx], col);

    // Move to next column or next row
    const ci = enterOrder.indexOf(col);
    if (ci >= 0 && ci < enterOrder.length - 1) {
      const nextCol = enterOrder[ci + 1];
      renderItemsTable();
      setTimeout(() => {
        const ni = document.querySelector(`#itemsTbody input[data-col="${nextCol}"][data-idx="${idx}"]`);
        if (ni) { ni.focus(); ni.select(); }
      }, 30);
    } else if (col === 'test_pcs' && idx < itemRows.length - 1) {
      renderItemsTable();
      setTimeout(() => {
        const ni = document.querySelector(`#itemsTbody input[data-col="rcvd_touch"][data-idx="${idx+1}"]`);
        if (ni) { ni.focus(); ni.select(); }
      }, 30);
    } else {
      renderItemsTable();
      // Last col, last row → focus charge
      setTimeout(() => $('fCharge').focus(), 30);
    }
    return;
  }

  if (e.key === 'Tab' && !e.shiftKey && col === 'test_pcs') {
    if (idx < itemRows.length - 1) {
      e.preventDefault();
      itemRows[idx][col] = toNum(inp.value);
      renderItemsTable();
      setTimeout(() => {
        const ni = document.querySelector(`#itemsTbody input[data-col="rcvd_touch"][data-idx="${idx+1}"]`);
        if (ni) { ni.focus(); ni.select(); }
      }, 30);
    }
  }
});

// ── Charge / PaidAmt events ────────────────────────────────
$('fCharge').addEventListener('input', calcBalance);
$('fPaidAmt').addEventListener('input', calcBalance);
$('fCharge').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); $('fNote').focus(); }
});
$('fNote').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); $('fPaidAmt').focus(); }
});
$('fPaidAmt').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); $('btnSave').focus(); }
});

// ── Load original entry (new return) ───────────────────────
function loadOrigEntry(docNo) {
  if (!docNo) { showMsg('Warning', 'Enter a Doc No.'); return; }
  api(`/api/refinery-return/load?doc_no=${encodeURIComponent(docNo)}`)
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Entry not found.'); return; }
      if (d.existing_return_doc_no) {
        showMsg('Info', 'Return already exists for this bill. Opening existing return: ' + d.existing_return_doc_no);
        loadReturnEntry(d.existing_return_doc_no);
        return;
      }
      loadedOrigDocNo = d.doc_no || '';
      loadedReturnDocNo = '';
      origSlno = d.slno || 0;
      origControl = d.control || 1;
      isEditMode = false;

      $('fOrigDocNo').value = d.doc_no || '';
      $('fReturnDocNo').value = '';
      $('fDate').value = d.bill_date || todayStr();
      $('fRefCode').value = d.refiner_code || '';
      $('fRefName').value = d.refiner_name || '';
      $('fSmCode').value = d.sm_code || '';

      // Summary
      summaryData = {
        issued_wgt: toNum(d.issued_wgt),
        stone_wgt: toNum(d.stone_wgt),
        bottle_stk: toNum(d.bottle_stk),
        mud: toNum(d.mud),
        test_pcs: toNum(d.test_pcs),
        net_issued: toNum(d.net_issued),
        test_perc: toNum(d.test_perc),
        net_issued_amt: toNum(d.net_issued_amt)
      };

      // Items
      itemRows = (d.items || []).map(it => ({
        item_code:    it.item_code||'',
        item_name:    it.item_name||'',
        issued_wgt:   toNum(it.issued_wgt),
        issued_qty:   parseInt(it.issued_qty)||0,
        issued_st_wgt:toNum(it.issued_st_wgt),
        issued_wgt_amt:toNum(it.issued_wgt_amt),
        oissuedwgt:   toNum(it.oissuedwgt),
        cost:         toNum(it.cost),
        stktype:      it.stktype||'',
        stktouch:     toNum(it.stktouch)||100,
        touch:        toNum(it.touch),
        // Editable return fields
        rcvd_touch:   toNum(it.rcvd_touch),
        mud_less:     toNum(it.mud_less),
        rcvd_qty:     parseInt(it.rcvd_qty)||0,
        rcvd_wgt:     toNum(it.rcvd_wgt),
        rate:         toNum(it.rate),
        rcvd_wgt_amt: toNum(it.rcvd_wgt_amt),
        bottle_stk:   toNum(it.bottle_stk),
        test_pcs:     toNum(it.test_pcs),
        sno:          it.sno||0,
        lookup_code:  it.item_code||''
      }));

      $('fCharge').value = '0.00';
      $('fPaidAmt').value = '0.00';
      $('fNote').value = '';
      calcBalance();
      renderItemsTable();

      // Focus first editable input
      setTimeout(() => {
        const fi = document.querySelector('#itemsTbody input[data-col="item_code"][data-idx="0"]');
        if (fi) { fi.focus(); fi.select(); }
      }, 100);
    });
}

// ── Load existing return entry (edit mode) ─────────────────
function loadReturnEntry(docNo) {
  if (!docNo) { showMsg('Warning', 'Enter a Return Doc No.'); return; }
  api(`/api/refinery-return/get?doc_no=${encodeURIComponent(docNo)}`)
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Return not found.'); return; }
      loadedOrigDocNo = d.orig_doc_no || '';
      loadedReturnDocNo = d.doc_no || '';
      origSlno = d.orig_slno || 0;
      origControl = d.control || 1;
      isEditMode = true;

      $('fOrigDocNo').value = d.orig_doc_no || '';
      $('fReturnDocNo').value = d.doc_no || '';
      $('fDate').value = d.bill_date || '';
      $('fRefCode').value = d.refiner_code || '';
      $('fRefName').value = d.refiner_name || '';
      $('fSmCode').value = d.sm_code || '';

      summaryData = {
        issued_wgt: toNum(d.issued_wgt),
        stone_wgt: toNum(d.stone_wgt),
        bottle_stk: toNum(d.bottle_stk),
        mud: toNum(d.mud),
        test_pcs: toNum(d.test_pcs),
        net_issued: toNum(d.net_issued),
        test_perc: toNum(d.test_perc),
        net_issued_amt: toNum(d.net_issued_amt)
      };

      itemRows = (d.items || []).map(it => ({
        item_code:    it.item_code||'',
        item_name:    it.item_name||'',
        issued_wgt:   toNum(it.issued_wgt),
        issued_qty:   parseInt(it.issued_qty)||0,
        issued_st_wgt:toNum(it.issued_st_wgt),
        issued_wgt_amt:toNum(it.issued_wgt_amt),
        oissuedwgt:   toNum(it.oissuedwgt),
        cost:         toNum(it.cost),
        stktype:      it.stktype||'',
        stktouch:     toNum(it.stktouch)||100,
        touch:        toNum(it.touch),
        rcvd_touch:   toNum(it.rcvd_touch),
        mud_less:     toNum(it.mud_less),
        rcvd_qty:     parseInt(it.rcvd_qty)||0,
        rcvd_wgt:     toNum(it.rcvd_wgt),
        rate:         toNum(it.rate),
        rcvd_wgt_amt: toNum(it.rcvd_wgt_amt),
        bottle_stk:   toNum(it.bottle_stk),
        test_pcs:     toNum(it.test_pcs),
        sno:          it.sno||0,
        lookup_code:  it.item_code||''
      }));

      $('fCharge').value = fmt(d.charge);
      $('fPaidAmt').value = fmt(d.paidamt);
      $('fNote').value = d.note || '';
      calcBalance();
      renderItemsTable();
    });
}

// ── Load button handler ────────────────────────────────────
$('btnLoad').addEventListener('click', () => {
  const docNo = $('fOrigDocNo').value.trim().toUpperCase();
  $('fOrigDocNo').value = docNo;
  if (subMode === 'edit') {
    loadReturnEntry(docNo);
  } else {
    loadOrigEntry(docNo);
  }
});

$('fOrigDocNo').addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    $('btnLoad').click();
  }
});

// ── Search help ────────────────────────────────────────────
$('btnSearchHelp').addEventListener('click', () => {
  $('entrySearchQ').value = $('fOrigDocNo').value;
  if (subMode === 'edit') {
    $('searchModalTitle').textContent = 'Search Refinery Returns';
  } else {
    $('searchModalTitle').textContent = 'Search Refinery Entry';
  }
  $('entrySearchModal').classList.add('active');
  $('entrySearchQ').focus();
  doEntrySearch();
});

$('entrySearchQ').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); doEntrySearch(); }
});

function doEntrySearch() {
  const q = $('entrySearchQ').value.trim();
  const endpoint = subMode === 'edit' ? '/api/refinery-return/search-returns' : '/api/refinery-return/search';
  api(`${endpoint}?q=${encodeURIComponent(q)}`)
    .then(d => {
      if (!d.ok) return;
      let html = '<table><thead><tr><th>Doc No</th><th>Date</th><th>Refiner</th><th>Name</th></tr></thead><tbody>';
      const results = d.results || [];
      if (results.length === 0) {
        html += `<tr><td colspan="4" style="text-align:center;color:#6b7280;padding:14px;">${subMode === 'edit' ? 'No return entry found.' : 'No pending refinery entry found.'}</td></tr>`;
      } else {
        results.forEach(r => {
          if (subMode !== 'edit' && r.has_return) {
            html += `<tr data-docno="${r.doc_no}" data-has-return="1" data-return-doc="${r.return_doc_no}" style="background:#fef9c3;color:#92400e;">
              <td>${r.doc_no}</td><td>${r.date||''}</td><td>${r.ref_code}</td>
              <td>${r.ref_name} <span style="font-size:10px;font-weight:600;background:#fde68a;border-radius:3px;padding:1px 5px;">Returned: ${r.return_doc_no}</span></td>
            </tr>`;
          } else {
            html += `<tr data-docno="${r.doc_no}"><td>${r.doc_no}</td><td>${r.date||''}</td><td>${r.ref_code}</td><td>${r.ref_name}</td></tr>`;
          }
        });
      }
      html += '</tbody></table>';
      $('entrySearchResults').innerHTML = html;
      $('entrySearchResults').querySelectorAll('tr[data-docno]').forEach(tr => {
        tr.addEventListener('click', () => {
          if (tr.dataset.hasReturn === '1') {
            const retDoc = tr.dataset.returnDoc;
            if (confirm(`This entry already has a return (${retDoc}).\nDo you want to edit that return entry?`)) {
              closeModal('entrySearchModal');
              subMode = 'edit';
              $('fOrigDocNo').placeholder = 'Return Doc No...';
              $('fOrigDocNo').value = retDoc;
              loadReturnEntry(retDoc);
            }
            return;
          }
          closeModal('entrySearchModal');
          $('fOrigDocNo').value = tr.dataset.docno;
          if (subMode === 'edit') {
            loadReturnEntry(tr.dataset.docno);
          } else {
            loadOrigEntry(tr.dataset.docno);
          }
        });
      });
    });
}

// ── Save return ────────────────────────────────────────────
function saveReturn() {
  if (!loadedOrigDocNo && !isEditMode) {
    showMsg('Warning', 'Load an entry first.'); return;
  }
  if (itemRows.length === 0) {
    showMsg('Warning', 'No items to save.'); return;
  }

  // Validate at least one item has received weight
  const hasRcvd = itemRows.some(r => toNum(r.rcvd_wgt) > 0);
  if (!hasRcvd) {
    showMsg('Warning', 'Enter received weight for at least one item.'); return;
  }

  if (!confirm('You want to save ?...')) return;

  const payload = {
    orig_doc_no: loadedOrigDocNo,
    return_doc_no: loadedReturnDocNo || '',
    bill_date: $('fDate').value.trim(),
    sm_code: $('fSmCode').value,
    charge: toNum($('fCharge').value),
    paidamt: toNum($('fPaidAmt').value),
    note: $('fNote').value.trim(),
    items: itemRows.map(r => ({
      item_code:     r.item_code,
      issued_wgt:    toNum(r.issued_wgt),
      issued_qty:    parseInt(r.issued_qty)||0,
      issued_st_wgt: toNum(r.issued_st_wgt),
      issued_wgt_amt:toNum(r.issued_wgt_amt),
      oissuedwgt:    toNum(r.oissuedwgt),
      cost:          toNum(r.cost),
      stktype:       r.stktype||'',
      stktouch:      toNum(r.stktouch)||100,
      rcvd_touch:    toNum(r.rcvd_touch),
      mud_less:      toNum(r.mud_less),
      rcvd_qty:      parseInt(r.rcvd_qty)||0,
      rcvd_wgt:      toNum(r.rcvd_wgt),
      rate:          toNum(r.rate),
      rcvd_wgt_amt:  toNum(r.rcvd_wgt_amt),
      bottle_stk:    toNum(r.bottle_stk),
      test_pcs:      toNum(r.test_pcs),
      sno:           r.sno||0
    }))
  };

  api('/api/refinery-return/save', { method: 'POST', json: payload })
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Save failed.'); return; }
      showMsg('Saved', 'Refinery Return saved.\nReturn No: ' + (d.doc_no||''));
      loadedReturnDocNo = d.doc_no || '';
      $('fReturnDocNo').value = d.doc_no || '';
      isEditMode = true;
    });
}

$('btnSave').addEventListener('click', saveReturn);

// ── Exit ───────────────────────────────────────────────────
$('btnExit').addEventListener('click', () => {
  if (window.parent && window.parent !== window) {
    window.parent.postMessage({ type: 'close-module' }, '*');
  }
});

// ── Today string ───────────────────────────────────────────
function todayStr() {
  const d = new Date();
  return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();
}

// ── Keyboard shortcuts ─────────────────────────────────────
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); saveReturn(); }
  if (e.key === 'Escape') {
    if ($('entrySearchModal').classList.contains('active')) { closeModal('entrySearchModal'); e.preventDefault(); return; }
    if ($('msgModal').classList.contains('active')) { closeModal('msgModal'); e.preventDefault(); return; }
    e.preventDefault();
  }
  if (e.key === 'F1') {
    e.preventDefault();
    $('btnSearchHelp').click();
  }
});

// ── Close modal buttons ────────────────────────────────────
document.querySelectorAll('[data-close]').forEach(btn => {
  btn.addEventListener('click', () => closeModal(btn.dataset.close));
});
$('msgModalOk').addEventListener('click', () => $('msgModal').classList.remove('active'));
$('msgModal').addEventListener('click', e => { if (e.target === $('msgModal')) $('msgModal').classList.remove('active'); });

// ── New Entry (reset form for creating a fresh return) ────
function resetForNewEntry() {
  if (!confirm('Clear current form and start a new entry?')) return;

  // Reset state
  loadedOrigDocNo = '';
  loadedReturnDocNo = '';
  origSlno = 0;
  origControl = 1;
  isEditMode = false;
  subMode = 'bill'; // switch to new-entry search mode

  // Clear form fields
  $('fOrigDocNo').value = '';
  $('fReturnDocNo').value = '';
  $('fDate').value = todayStr();
  $('fRefCode').value = '';
  $('fRefName').value = '';
  $('fSmCode').value = '';
  $('fCharge').value = '0.00';
  $('fPaidAmt').value = '0.00';
  $('fNetBalance').value = '0.00';
  $('fNote').value = '';

  // Reset summary
  summaryData = { issued_wgt:0, stone_wgt:0, bottle_stk:0, mud:0,
    test_pcs:0, net_issued:0, test_perc:0, net_issued_amt:0 };
  itemRows = [];
  renderItemsTable();
  updateSummary();

  // Update UI for new-entry mode
  $('fOrigDocNo').placeholder = 'Original Doc No...';

  // Open search to pick original bill
  $('searchModalTitle').textContent = 'Search Refinery Entry';
  $('entrySearchQ').value = '';
  $('entrySearchModal').classList.add('active');
  $('entrySearchQ').focus();
  doEntrySearch();
}

if (document.getElementById('btnNew')) {
  $('btnNew').addEventListener('click', resetForNewEntry);
}

// ── Init ───────────────────────────────────────────────────
$('fDate').value = todayStr();

if (CFG.mode === 'edit') {
  // Edit mode: show search for existing returns
  $('fOrigDocNo').placeholder = 'Return Doc No...';
  $('searchModalTitle').textContent = 'Search Refinery Returns';
  $('entrySearchModal').classList.add('active');
  $('entrySearchQ').focus();
  doEntrySearch();
} else {
  // New mode: show search for original entries
  $('entrySearchModal').classList.add('active');
  $('entrySearchQ').focus();
  doEntrySearch();
}

})();
</script>
</body>
</html>
