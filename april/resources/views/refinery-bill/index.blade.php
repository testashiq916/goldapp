<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('js/goldapp-common.js') }}"></script>
<title>{{ $title ?? 'Refinery Details' }}</title>
<script>
window.RF_CONFIG = {
  siteUrl:   @json(request()->root()),
  mode:      @json($mode ?? 'bill'),
  nextDocNo: @json($nextDocNo ?? ''),
  salesmen:  @json($salesmen ?? []),
  items:     @json($items ?? []),
  software:  @json($software ?? []),
};
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',system-ui,-apple-system,sans-serif;font-size:13px;color:#1e293b;background:#f1f5f9;overflow:hidden;height:100vh}
:root{
  --col-code:80px;
  --col-name:160px;
  --col-weight:85px;
  --col-stwt:75px;
  --col-mud:70px;
  --col-testpcs:75px;
  --col-touch:70px;
  --col-netwt:85px;
  --col-wtamt:90px;
  --col-qty:60px;
}

/* ── Layout ─────────────────────────────────────────────── */
.app{display:flex;flex-direction:column;height:100vh;background:#fff;margin:0 auto}
.header{background:linear-gradient(135deg,#1e40af 0%,#3b82f6 100%);color:#fff;padding:10px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0}
.header h1{font-size:15px;font-weight:700;letter-spacing:.3px}
.header .mode-badge{background:rgba(255,255,255,.2);padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600}

/* ── Form Section ───────────────────────────────────────── */
.form-section{padding:12px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-shrink:0}
.form-grid{display:grid;grid-template-columns:auto 1fr auto 1fr;gap:8px 12px;align-items:center}
.form-grid label{font-weight:600;font-size:12px;color:#475569;white-space:nowrap;text-align:right}
.form-grid input,.form-grid select{height:32px;padding:0 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;color:#1e293b;background:#fff;transition:all .2s}
.form-grid input:focus,.form-grid select:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.form-grid input.ro{background:#f1f5f9;color:#64748b;cursor:default}
.form-grid .refiner-group{display:flex;gap:6px;align-items:center}
.form-grid .refiner-group input{flex:1}
.btn-icon{width:32px;height:32px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:#475569;transition:all .15s}
.btn-icon:hover{background:#f1f5f9;border-color:#3b82f6;color:#3b82f6}

/* ── Items Table ────────────────────────────────────────── */
.table-section{flex:1;display:flex;flex-direction:column;overflow:hidden;padding:0 12px}
.table-container{flex:1;overflow:auto;border:1px solid #e2e8f0;border-radius:8px;background:#fff}
table.grid{width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed}
table.grid thead{position:sticky;top:0;z-index:2}
table.grid thead th{background:linear-gradient(180deg,#10b981 0%,#059669 100%);color:#fff;padding:8px 6px;font-weight:600;font-size:11px;text-align:center;white-space:nowrap;border-right:1px solid rgba(255,255,255,.2);letter-spacing:.3px;text-transform:uppercase}
table.grid thead th:last-child{border-right:none}
table.grid tbody td{padding:2px 3px;border-bottom:1px solid #f1f5f9;text-align:center;height:30px;vertical-align:middle}
table.grid tbody tr:hover td{background:#eff6ff}
table.grid tbody tr.selected td{background:#dbeafe}
table.grid tbody input{width:100%;height:26px;border:1px solid transparent;border-radius:4px;font-size:12px;text-align:center;background:transparent;color:#1e293b;padding:0 4px;transition:all .15s}
table.grid tbody input:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 2px rgba(59,130,246,.15);outline:none}
table.grid tbody input.num{text-align:right;font-variant-numeric:tabular-nums}
table.grid tbody td.ro{color:#64748b;font-size:11px;background:#fafafa}
table.grid tbody td.name-cell{text-align:left;padding-left:8px;font-size:11px;color:#334155}
table.grid tbody td.computed{background:#f0fdf4;color:#166534;font-weight:500}

/* ── Totals row ─────────────────────────────────────────── */
.totals-table-wrap{border-top:2px solid #10b981;background:#f8fafc;flex-shrink:0}
table.totals-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12px}
table.totals-table td{padding:6px 6px;color:#1e293b;font-variant-numeric:tabular-nums;text-align:right;font-weight:600}
table.totals-table td.t-label{color:#059669;text-align:left;padding-left:8px;font-weight:700}

/* ── Toolbar ────────────────────────────────────────────── */
.toolbar{display:flex;align-items:center;padding:6px 12px;gap:8px;background:#fff;border-top:1px solid #e2e8f0;flex-shrink:0}
.toolbar .btn{height:30px;padding:0 16px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;font-size:12px;font-weight:600;color:#475569;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:4px}
.toolbar .btn:hover{background:#f1f5f9;border-color:#94a3b8}
.toolbar .btn.primary{background:#3b82f6;color:#fff;border-color:#3b82f6}
.toolbar .btn.primary:hover{background:#2563eb}
.toolbar .btn.danger{color:#ef4444;border-color:#fca5a5}
.toolbar .btn.danger:hover{background:#fef2f2}
.toolbar .info{font-size:11px;color:#64748b;font-weight:500}
.toolbar .stktype-tag{background:#059669;color:#fff;padding:2px 10px;border-radius:4px;font-size:11px;font-weight:600}
.toolbar .stock-tag{background:#f59e0b;color:#fff;padding:2px 10px;border-radius:4px;font-size:11px;font-weight:600}

/* ── Bottom Section ─────────────────────────────────────── */
.bottom-section{display:flex;align-items:center;padding:10px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;gap:20px;flex-shrink:0}
.bottom-section label{font-weight:600;font-size:12px;color:#475569;white-space:nowrap}
.bottom-section input{height:32px;padding:0 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;color:#1e293b;background:#fff;transition:all .2s}
.bottom-section input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.bottom-section input.ro{background:#f0fdf4;color:#166534;font-weight:600;border-color:#86efac}
.bottom-section .spacer{flex:1}
.btn-action{height:36px;padding:0 24px;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:6px}
.btn-save{background:#10b981;color:#fff}
.btn-save:hover{background:#059669}
.btn-exit{background:#64748b;color:#fff;margin-left:8px}
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
.search-input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.search-list{max-height:320px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px}
.search-list table{width:100%;border-collapse:collapse;font-size:12px}
.search-list table th{background:#f8fafc;padding:8px 10px;text-align:left;font-weight:600;font-size:11px;color:#64748b;position:sticky;top:0;border-bottom:1px solid #e2e8f0}
.search-list table td{padding:6px 10px;border-bottom:1px solid #f1f5f9;cursor:pointer}
.search-list table tr:hover td{background:#eff6ff}

/* ── Msg modal ──────────────────────────────────────────── */
.msg-modal{position:fixed;inset:0;background:rgba(15,23,42,.5);display:none;align-items:center;justify-content:center;z-index:12000;backdrop-filter:blur(2px)}
.msg-modal.active{display:flex}
.msg-box{width:min(400px,calc(100vw - 24px));background:#fff;border-radius:12px;box-shadow:0 25px 50px rgba(0,0,0,.2);overflow:hidden}
.msg-box .head{padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:600;font-size:13px;color:#1e293b}
.msg-box .body{padding:16px;font-size:13px;color:#475569;line-height:1.5;white-space:pre-wrap}
.msg-box .foot{padding:10px 16px;border-top:1px solid #e2e8f0;display:flex;justify-content:center}
.msg-box .ok-btn{height:32px;padding:0 24px;background:#3b82f6;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer}
.msg-box .ok-btn:hover{background:#2563eb}

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
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
    <h1>{{ $title ?? 'Refinery Details' }}</h1>
    @if(($mode ?? 'bill') !== 'bill')
      <span class="mode-badge">{{ ucfirst($mode ?? 'bill') }} Mode</span>
    @endif
  </div>

  <!-- ── Form Fields ───────────────────────────────────────── -->
  <div class="form-section">
    <div class="form-grid">
      <label>Doc No :</label>
      <input type="text" id="fDocNo" class="ro" readonly style="max-width:160px">

      <label>Date :</label>
      <input type="text" id="fDate" placeholder="dd/mm/yyyy" style="max-width:130px;text-align:center">

      <label>Refiner :</label>
      <div class="refiner-group">
        <input type="text" id="fRefCode" style="max-width:100px;text-transform:uppercase" placeholder="Code">
        <button class="btn-icon" id="btnRefHelp" title="Search Refiner">?</button>
        <input type="text" id="fRefName" class="ro" readonly style="min-width:200px" placeholder="Refiner Name">
      </div>

      <label>SM :</label>
      <select id="fSmCode" style="max-width:180px">
        <option value="">-- Select --</option>
        @foreach($salesmen as $sm)
          <option value="{{ $sm['code'] }}">{{ $sm['code'] }} - {{ $sm['name'] }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <!-- ── Items Table ───────────────────────────────────────── -->
  <div class="table-section">
    <div class="table-container">
      <table class="grid" id="itemsTable">
        <colgroup>
          <col style="width:var(--col-code)">
          <col style="width:var(--col-name)">
          <col style="width:var(--col-weight)">
          <col style="width:var(--col-stwt)">
          <col style="width:var(--col-mud)">
          <col style="width:var(--col-testpcs)">
          <col style="width:var(--col-touch)">
          <col style="width:var(--col-netwt)">
          <col style="width:var(--col-wtamt)">
          <col style="width:var(--col-qty)">
        </colgroup>
        <thead>
          <tr>
            <th style="width:var(--col-code)">Item Code</th>
            <th style="width:var(--col-name)">Item Name</th>
            <th style="width:var(--col-weight)">Weight</th>
            <th style="width:var(--col-stwt)">St.Wt</th>
            <th style="width:var(--col-mud)">Mud</th>
            <th style="width:var(--col-testpcs)">Test Pcs</th>
            <th style="width:var(--col-touch)">Touch</th>
            <th style="width:var(--col-netwt)">Net Wt.</th>
            <th style="width:var(--col-wtamt)">Wt. Amt.</th>
            <th style="width:var(--col-qty)">Qty</th>
          </tr>
        </thead>
        <tbody id="itemsTbody"></tbody>
      </table>
    </div>

    <!-- ── Totals Bar ────────────────────────────────────── -->
    <div class="totals-table-wrap" id="totalsBar">
      <table class="totals-table">
        <colgroup>
          <col style="width:var(--col-code)">
          <col style="width:var(--col-name)">
          <col style="width:var(--col-weight)">
          <col style="width:var(--col-stwt)">
          <col style="width:var(--col-mud)">
          <col style="width:var(--col-testpcs)">
          <col style="width:var(--col-touch)">
          <col style="width:var(--col-netwt)">
          <col style="width:var(--col-wtamt)">
          <col style="width:var(--col-qty)">
        </colgroup>
        <tbody>
          <tr>
            <td></td>
            <td class="t-label">Total :</td>
            <td id="tWeight">0.000</td>
            <td id="tStWt">0</td>
            <td id="tMud">0</td>
            <td id="tTestPcs">0</td>
            <td id="tTouch">0</td>
            <td id="tNetWt">0</td>
            <td id="tWtAmt">0</td>
            <td id="tQty">0</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Toolbar ───────────────────────────────────────────── -->
  <div class="toolbar">
    <button class="btn primary" id="btnAddRow">+ Add</button>
    <button class="btn danger" id="btnDelRow">- Delete</button>
    <span class="info">Items: <strong id="ftItemCount">0</strong></span>
    <span style="flex:1"></span>
    <span class="stktype-tag" id="ftStkType" style="display:none"></span>
    <span class="stock-tag" id="ftStock" style="display:none"></span>
  </div>

  <!-- ── Bottom Section ────────────────────────────────────── -->
  <div class="bottom-section">
    <label>Note :</label>
    <input type="text" id="fNote" style="width:300px" maxlength="30" placeholder="Enter note...">
    <span class="spacer"></span>
    <label>Expected Wgt :</label>
    <input type="text" id="fExpWgt" class="ro" readonly value="0.000" style="width:120px;text-align:right;font-size:14px">
    <button class="btn-action btn-save" id="btnSave">Save</button>
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

<!-- ── Bill Search Modal ───────────────────────────────────── -->
<div class="modal-overlay" id="billSearchModal">
  <div class="modal-panel" style="min-width:640px">
    <div class="m-head">
      <h3>Search Refinery Entry</h3>
      <button class="m-close" data-close="billSearchModal">&times;</button>
    </div>
    <div class="m-body">
      <input type="text" class="search-input" id="billSearchQ" placeholder="Search by Doc No or Refiner Code...">
      <div class="search-list" id="billSearchResults"></div>
    </div>
    <div class="m-foot">
      <button data-close="billSearchModal">Close</button>
    </div>
  </div>
</div>

<!-- ── Refiner Search Modal ────────────────────────────────── -->
<div class="modal-overlay" id="refinerSearchModal">
  <div class="modal-panel">
    <div class="m-head">
      <h3>Search Refiner</h3>
      <button class="m-close" data-close="refinerSearchModal">&times;</button>
    </div>
    <div class="m-body">
      <input type="text" class="search-input" id="refinerSearchQ" placeholder="Code or Name...">
      <div class="search-list" id="refinerSearchResults"></div>
    </div>
    <div class="m-foot">
      <button data-close="refinerSearchModal">Close</button>
    </div>
  </div>
</div>

<!-- ── Item Search Modal ───────────────────────────────────── -->
<div class="modal-overlay" id="itemSearchModal">
  <div class="modal-panel">
    <div class="m-head">
      <h3>Item Search</h3>
      <button class="m-close" data-close="itemSearchModal">&times;</button>
    </div>
    <div class="m-body">
      <input type="text" class="search-input" id="itemSearchQ" placeholder="Code or Name...">
      <div class="search-list" id="itemSearchResults"></div>
    </div>
    <div class="m-foot">
      <button data-close="itemSearchModal">Close</button>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<script>
(function(){
'use strict';
const CFG = window.RF_CONFIG;
const API = CFG.siteUrl;
const csrf = document.querySelector('meta[name="csrf-token"]').content;

// ── State ──────────────────────────────────────────────────
let itemRows = [];
let selectedRow = -1;
let isEditMode = false;
let itemSearchTargetRow = -1;

// ── Helpers ────────────────────────────────────────────────
const $ = id => document.getElementById(id);
const toNum = v => { let n = parseFloat(String(v).replace(/,/g,'')); return isNaN(n)?0:n; };
const fmt = (v,d=2) => toNum(v).toFixed(d);
const fmt3 = v => toNum(v).toFixed(3);
const isZero = v => Math.abs(toNum(v)) < 0.0005;

function isEmptyRow(row) {
  return !row.item_code
    && !row.item_name
    && isZero(row.weight)
    && isZero(row.stone_wgt)
    && isZero(row.mud_less)
    && isZero(row.test_pcs)
    && isZero(row.touch)
    && isZero(row.net_wgt)
    && isZero(row.wgt_amt)
    && !parseInt(row.qty || 0, 10);
}

function displayNum(row, key, decimals = 3) {
  const value = toNum(row[key]);
  return isEmptyRow(row) && isZero(value) ? '' : (decimals === 3 ? fmt3(value) : fmt(value, decimals));
}

function displayQty(row) {
  const value = parseInt(row.qty || 0, 10) || 0;
  return isEmptyRow(row) && value === 0 ? '' : String(value);
}

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

// ── New item row ───────────────────────────────────────────
function newItemRow() {
  return {
    item_code:'', item_name:'',
    weight:0, stone_wgt:0, mud_less:0, test_pcs:0, touch:0,
    net_wgt:0, wgt_amt:0, qty:0,
    cost:0, stktype: CFG.software.DefStkType || '',
    stktouch:100,
    stk_qty:0, stk_wgt:0
  };
}

// ── Auto-calculations ──────────────────────────────────────
function calcRow(r) {
  r.net_wgt = Math.round((toNum(r.weight) - toNum(r.stone_wgt) - toNum(r.mud_less) - toNum(r.test_pcs)) * 1000) / 1000;
  if (r.net_wgt < 0) r.net_wgt = 0;
  r.wgt_amt = Math.round((toNum(r.weight) - toNum(r.stone_wgt)) * toNum(r.cost) * 100) / 100;
}

function calcTotals() {
  let tW=0, tS=0, tM=0, tT=0, tTch=0, tN=0, tA=0, tQ=0;
  itemRows.forEach(r => {
    if (!r.item_code) return;
    tW += toNum(r.weight); tS += toNum(r.stone_wgt); tM += toNum(r.mud_less);
    tT += toNum(r.test_pcs); tTch += toNum(r.touch); tN += toNum(r.net_wgt);
    tA += toNum(r.wgt_amt); tQ += parseInt(r.qty)||0;
  });
  $('tWeight').textContent = fmt3(tW);
  $('tStWt').textContent = fmt3(tS);
  $('tMud').textContent = fmt3(tM);
  $('tTestPcs').textContent = fmt3(tT);
  $('tTouch').textContent = fmt3(tTch);
  $('tNetWt').textContent = fmt3(tN);
  $('tWtAmt').textContent = fmt(tA);
  $('tQty').textContent = tQ;
  $('fExpWgt').value = fmt3(tN);
  $('ftItemCount').textContent = itemRows.filter(r => r.item_code).length;
}

// ── Render items table ─────────────────────────────────────
function renderItemsTable() {
  const tbody = $('itemsTbody');
  tbody.innerHTML = '';
  itemRows.forEach((row, idx) => {
    calcRow(row);
    const tr = document.createElement('tr');
    tr.dataset.idx = idx;
    if (idx === selectedRow) tr.classList.add('selected');
    tr.innerHTML = `
      <td><input data-col="item_code" value="${row.item_code}" style="text-transform:uppercase" autocomplete="off" placeholder="CODE"></td>
      <td class="name-cell">${row.item_name||''}</td>
      <td><input data-col="weight" class="num" value="${displayNum(row, 'weight')}" autocomplete="off"></td>
      <td><input data-col="stone_wgt" class="num" value="${displayNum(row, 'stone_wgt')}" autocomplete="off"></td>
      <td><input data-col="mud_less" class="num" value="${displayNum(row, 'mud_less')}" autocomplete="off"></td>
      <td><input data-col="test_pcs" class="num" value="${displayNum(row, 'test_pcs')}" autocomplete="off"></td>
      <td><input data-col="touch" class="num" value="${displayNum(row, 'touch')}" autocomplete="off"></td>
      <td class="computed net-wgt-cell" style="text-align:right;padding-right:6px;font-variant-numeric:tabular-nums">${displayNum(row, 'net_wgt')}</td>
      <td class="computed wgt-amt-cell" style="text-align:right;padding-right:6px;font-variant-numeric:tabular-nums">${displayNum(row, 'wgt_amt', 2)}</td>
      <td><input data-col="qty" class="num" value="${displayQty(row)}" style="text-align:center" autocomplete="off"></td>
    `;
    tr.addEventListener('click', e => {
      selectedRow = idx;
      updateStockDisplay(idx);
      if (e.target && e.target.tagName === 'INPUT') return;
      renderItemsTable();
      focusGridCell(idx, 'item_code', false);
    });
    tbody.appendChild(tr);
  });
  calcTotals();
}

function focusGridCell(idx, col = 'item_code', select = true) {
  setTimeout(() => {
    const input = document.querySelector(`#itemsTbody tr[data-idx="${idx}"] input[data-col="${col}"]`);
    if (input) {
      input.focus();
      if (select && typeof input.select === 'function') input.select();
    }
  }, 40);
}

function syncGridValue(inp, idx, col) {
  const r = itemRows[idx];
  if (!r) return null;
  const numCols = ['weight','stone_wgt','mud_less','test_pcs','touch','qty'];
  if (numCols.includes(col)) r[col] = toNum(inp.value);
  else r[col] = inp.value.trim().toUpperCase();
  if (['weight','stone_wgt','mud_less','test_pcs'].includes(col)) calcRow(r);
  return r;
}

function refreshRowComputedCells(idx) {
  const tr = document.querySelector(`#itemsTbody tr[data-idx="${idx}"]`);
  const row = itemRows[idx];
  if (!tr || !row) return;
  calcRow(row);
  const netCell = tr.querySelector('.net-wgt-cell');
  const amtCell = tr.querySelector('.wgt-amt-cell');
  if (netCell) netCell.textContent = displayNum(row, 'net_wgt');
  if (amtCell) amtCell.textContent = displayNum(row, 'wgt_amt', 2);
}

function updateStockDisplay(idx) {
  if (idx >= 0 && idx < itemRows.length) {
    const r = itemRows[idx];
    const st = $('ftStkType'), sk = $('ftStock');
    if (r.stktype) { st.textContent = r.stktype; st.style.display = ''; } else { st.style.display = 'none'; }
    if (r.stk_qty || r.stk_wgt) { sk.textContent = (r.stk_qty||0) + ' / ' + fmt3(r.stk_wgt||0); sk.style.display = ''; } else { sk.style.display = 'none'; }
  }
}

// ── Item lookup ────────────────────────────────────────────
function itemLookup(idx) {
  const r = itemRows[idx];
  if (!r || !r.item_code) return;
  api(`/api/refinery-bill/item-lookup?code=${encodeURIComponent(r.item_code)}`)
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Invalid Item'); return; }
      r.item_code = d.code || r.item_code;
      r.item_name = d.name || '';
      r.cost = d.cost || 0;
      r.stk_qty = d.stk_qty || 0;
      r.stk_wgt = d.stk_wgt || 0;
      const refDefStk = CFG.software.RefineryDefStkType || '';
      if (refDefStk) r.stktype = refDefStk;
      else if (d.defstktype) r.stktype = d.defstktype;
      calcRow(r);
      selectedRow = idx;
      renderItemsTable();
      updateStockDisplay(idx);
      focusGridCell(idx, 'weight');
    });
}

// ── Event: blur on item inputs ─────────────────────────────
$('itemsTbody').addEventListener('blur', function(e) {
  const inp = e.target;
  if (inp.tagName !== 'INPUT') return;
  const tr = inp.closest('tr');
  if (!tr) return;
  const idx = parseInt(tr.dataset.idx);
  const col = inp.dataset.col;
  if (isNaN(idx) || !col) return;
  const r = syncGridValue(inp, idx, col);
  if (!r) return;
  // Handle wgt_amt manual entry with * syntax (rate * netwt)
  renderItemsTable();
}, true);

$('itemsTbody').addEventListener('input', function(e) {
  const inp = e.target;
  if (inp.tagName !== 'INPUT') return;
  const tr = inp.closest('tr');
  if (!tr) return;
  const idx = parseInt(tr.dataset.idx, 10);
  const col = inp.dataset.col;
  if (isNaN(idx) || !col) return;
  const r = syncGridValue(inp, idx, col);
  if (!r) return;
  refreshRowComputedCells(idx);
  calcTotals();
});

// ── Event: keydown on item inputs ──────────────────────────
// Enter column flow: item_code → weight → stone_wgt → mud_less → test_pcs → touch → qty(→addnew)
const enterOrder = ['item_code','weight','stone_wgt','mud_less','test_pcs','touch','qty'];

$('itemsTbody').addEventListener('keydown', function(e) {
  const inp = e.target;
  if (inp.tagName !== 'INPUT') return;
  const tr = inp.closest('tr');
  if (!tr) return;
  const idx = parseInt(tr.dataset.idx);
  const col = inp.dataset.col;

  if (e.key === 'Enter') {
    e.preventDefault();
    const r = syncGridValue(inp, idx, col);
    if (!r) return;

    // Item code → lookup
    if (col === 'item_code') {
      if (r.item_code) itemLookup(idx);
      return;
    }

    // Qty → add new row
    if (col === 'qty') {
      addNewRow();
      return;
    }

    // Move to next column in enter order
    const ci = enterOrder.indexOf(col);
    if (ci >= 0 && ci < enterOrder.length - 1) {
      const nextCol = enterOrder[ci + 1];
      renderItemsTable();
      focusGridCell(idx, nextCol);
    }
    return;
  }

  // Tab at last column → add new row
  if (e.key === 'Tab') {
    e.preventDefault();
    const r = syncGridValue(inp, idx, col);
    if (!r) return;

    const ci = enterOrder.indexOf(col);
    if (e.shiftKey) {
      if (ci > 0) {
        renderItemsTable();
        focusGridCell(idx, enterOrder[ci - 1]);
        return;
      }
      $('fSmCode').focus();
      return;
    }

    if (col === 'item_code' && r.item_code) {
      itemLookup(idx);
      return;
    }

    if (col === 'qty') {
      addNewRow();
      return;
    }

    if (ci >= 0 && ci < enterOrder.length - 1) {
      renderItemsTable();
      focusGridCell(idx, enterOrder[ci + 1]);
    }
  }

  // F1 = item search
  if (e.key === 'F1' && col === 'item_code') {
    e.preventDefault();
    itemSearchTargetRow = idx;
    $('itemSearchQ').value = inp.value;
    $('itemSearchModal').classList.add('active');
    $('itemSearchQ').focus();
    doItemSearch();
  }

  // F12 on stktype-related
  if (e.key === 'F12') {
    e.preventDefault();
    // Could open stktype picker - placeholder
  }
});

function addNewRow() {
  const lastIdx = itemRows.length - 1;
  if (lastIdx >= 0 && !itemRows[lastIdx].item_code) {
    selectedRow = lastIdx;
    renderItemsTable();
    focusGridCell(lastIdx, 'item_code', false);
    return;
  }
  itemRows.push(newItemRow());
  selectedRow = itemRows.length - 1;
  renderItemsTable();
  focusGridCell(selectedRow, 'item_code', false);
}

// ── Add / Delete buttons ───────────────────────────────────
$('btnAddRow').addEventListener('click', addNewRow);
$('btnDelRow').addEventListener('click', () => {
  if (selectedRow >= 0 && selectedRow < itemRows.length) {
    itemRows.splice(selectedRow, 1);
    selectedRow = Math.min(selectedRow, itemRows.length - 1);
    renderItemsTable();
  }
});

// ── Refiner lookup ─────────────────────────────────────────
function refinerLookup(code) {
  if (!code) return;
  api(`/api/refinery-bill/refiner-lookup?code=${encodeURIComponent(code)}`)
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Invalid Refiner Code'); $('fRefCode').value = ''; $('fRefName').value = ''; return; }
      $('fRefCode').value = d.code || '';
      $('fRefName').value = d.name || '';
    });
}

$('fRefCode').addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    const code = $('fRefCode').value.trim().toUpperCase();
    $('fRefCode').value = code;
    if (code) refinerLookup(code);
    $('fSmCode').focus();
  }
  if (e.key === 'F1') { e.preventDefault(); $('btnRefHelp').click(); }
});

$('btnRefHelp').addEventListener('click', () => {
  $('refinerSearchQ').value = $('fRefCode').value;
  $('refinerSearchModal').classList.add('active');
  $('refinerSearchQ').focus();
  doRefinerSearch();
});

$('btnDoRefinerSearch')?.addEventListener('click', doRefinerSearch);
$('refinerSearchQ').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); doRefinerSearch(); } });

function doRefinerSearch() {
  const q = $('refinerSearchQ').value.trim();
  api(`/api/refinery-bill/refiner-search?q=${encodeURIComponent(q)}`)
    .then(d => {
      if (!d.ok) return;
      let html = '<table><thead><tr><th>Code</th><th>Name</th></tr></thead><tbody>';
      (d.results||[]).forEach(r => { html += `<tr data-code="${r.code}"><td>${r.code}</td><td>${r.name}</td></tr>`; });
      html += '</tbody></table>';
      $('refinerSearchResults').innerHTML = html;
      $('refinerSearchResults').querySelectorAll('tr[data-code]').forEach(tr => {
        tr.addEventListener('click', () => { closeModal('refinerSearchModal'); $('fRefCode').value = tr.dataset.code; refinerLookup(tr.dataset.code); });
      });
    });
}

// ── Item Search ────────────────────────────────────────────
$('itemSearchQ').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); doItemSearch(); } });

function doItemSearch() {
  const q = $('itemSearchQ').value.trim();
  api(`/api/refinery-bill/item-search?q=${encodeURIComponent(q)}`)
    .then(d => {
      if (!d.ok) return;
      let html = '<table><thead><tr><th>Code</th><th>Name</th><th>Type</th></tr></thead><tbody>';
      (d.results||[]).forEach(r => { html += `<tr data-code="${r.code}"><td>${r.code}</td><td>${r.name}</td><td>${r.itype}</td></tr>`; });
      html += '</tbody></table>';
      $('itemSearchResults').innerHTML = html;
      $('itemSearchResults').querySelectorAll('tr[data-code]').forEach(tr => {
        tr.addEventListener('click', () => {
          closeModal('itemSearchModal');
          if (itemSearchTargetRow >= 0 && itemSearchTargetRow < itemRows.length) {
            itemRows[itemSearchTargetRow].item_code = tr.dataset.code;
            itemLookup(itemSearchTargetRow);
          }
        });
      });
    });
}

// ── Bill Search ────────────────────────────────────────────
$('billSearchQ').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); doBillSearch(); } });

function doBillSearch() {
  const q = $('billSearchQ').value.trim();
  api(`/api/refinery-bill/search?q=${encodeURIComponent(q)}&action=${encodeURIComponent(CFG.mode === 'cancel' ? 'cancel' : 'edit')}`)
    .then(d => {
      if (!d.ok) return;
      let html = '<table><thead><tr><th>Doc No</th><th>Date</th><th>Refiner</th><th>Name</th><th>Return Status</th></tr></thead><tbody>';
      (d.results||[]).forEach(r => {
        const retBadge = r.has_return
          ? '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;white-space:nowrap">Edit Return Entry</span>'
          : '<span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;white-space:nowrap">New Entry</span>';
        html += `<tr data-docno="${r.doc_no}"><td>${r.doc_no}</td><td>${r.date||''}</td><td>${r.ref_code}</td><td>${r.ref_name}</td><td>${retBadge}</td></tr>`;
      });
      if (!(d.results||[]).length) {
        html += '<tr><td colspan="5" style="text-align:center;color:#64748b">No cancellable refinery entry found.</td></tr>';
      }
      html += '</tbody></table>';
      $('billSearchResults').innerHTML = html;
      $('billSearchResults').querySelectorAll('tr[data-docno]').forEach(tr => {
        tr.addEventListener('click', () => { closeModal('billSearchModal'); loadBill(tr.dataset.docno); });
      });
    });
}

// ── Save ───────────────────────────────────────────────────
function saveBill() {
  const refCode = $('fRefCode').value.trim().toUpperCase();
  if (!refCode) { showMsg('Warning', "Refiner's code empty. You can't save..."); $('fRefCode').focus(); return; }
  if (CFG.software.SMCompulsary === 'Y' && !$('fSmCode').value) { showMsg('Warning', "SM Code empty. You can't save..."); $('fSmCode').focus(); return; }

  const validItems = itemRows.filter(r => r.item_code);
  if (!validItems.length) { showMsg('Warning', 'Incomplete Transaction\nThis transaction is not complete...'); return; }

  for (const it of validItems) {
    if (toNum(it.weight) <= 0) { showMsg('Warning', 'Check Weight (' + it.item_code + "). Weight can't be zero"); return; }
    if (CFG.software.StktypeStrict === 'Y' && !it.stktype) { showMsg('Warning', 'Check Stock Type (' + it.item_code + "). You can't save..."); return; }
  }

  if (!confirm('You want to save ?...')) return;

  const payload = {
    doc_no: isEditMode ? $('fDocNo').value.trim() : '',
    bill_date: $('fDate').value.trim(),
    refiner_code: refCode,
    refiner_name: $('fRefName').value.trim(),
    sm_code: $('fSmCode').value,
    test_perc: 0,
    exp_wgt: toNum($('fExpWgt').value),
    note: $('fNote').value.trim(),
    items: validItems.map(r => ({
      item_code: r.item_code,
      weight: toNum(r.weight),
      wgt_amt: toNum(r.wgt_amt),
      qty: parseInt(r.qty)||0,
      stone_wgt: toNum(r.stone_wgt),
      mud_less: toNum(r.mud_less),
      test_pcs: toNum(r.test_pcs),
      cost: toNum(r.cost),
      stktype: r.stktype||'',
      stktouch: toNum(r.stktouch)||100,
      touch: toNum(r.touch)||0
    }))
  };

  api('/api/refinery-bill/save', { method: 'POST', json: payload })
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Save failed.'); return; }
      showMsg('Saved', 'Refinery Entry saved.\nDoc No: ' + (d.doc_no||''));
      resetForm();
    });
}

function cancelBillAction() {
  const docNo = $('fDocNo').value.trim();
  if (!docNo) { showMsg('Warning', 'Load a bill first.'); return; }
  if (!confirm('Cancel this bill?')) return;
  api('/api/refinery-bill/cancel', { method: 'POST', json: { doc_no: docNo } })
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Cancel failed.'); return; }
      showMsg('Cancelled', 'Refinery Entry cancelled.');
      resetForm();
    })
    .catch(() => showMsg('Error', 'Cancel failed.'));
}

function runPrimaryAction() {
  if (CFG.mode === 'cancel') {
    cancelBillAction();
    return;
  }
  saveBill();
}

$('btnSave').addEventListener('click', runPrimaryAction);

// ── Load bill ──────────────────────────────────────────────
function loadBill(docNo) {
  api(`/api/refinery-bill/get?doc_no=${encodeURIComponent(docNo)}`)
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Bill not found.'); return; }
      isEditMode = true;
      $('fDocNo').value = d.doc_no || '';
      $('fDate').value = d.bill_date || '';
      $('fRefCode').value = d.refiner_code || '';
      $('fRefName').value = d.refiner_name || '';
      $('fSmCode').value = d.sm_code || '';
      $('fExpWgt').value = fmt3(d.exp_wgt || 0);
      $('fNote').value = d.note || '';

      itemRows = (d.items || []).map(it => {
        const r = {
          item_code: it.item_code||'', item_name: it.item_name||'',
          weight: toNum(it.weight), stone_wgt: toNum(it.stone_wgt),
          mud_less: toNum(it.mud_less), test_pcs: toNum(it.test_pcs),
          touch: toNum(it.touch), cost: toNum(it.cost),
          wgt_amt: toNum(it.wgt_amt), qty: parseInt(it.qty)||0,
          net_wgt: 0, stktype: it.stktype||'',
          stktouch: toNum(it.stktouch)||100,
          stk_qty: 0, stk_wgt: 0
        };
        r.net_wgt = Math.round((r.weight - r.stone_wgt - r.mud_less - r.test_pcs) * 1000) / 1000;
        return r;
      });
      if (!itemRows.length) itemRows.push(newItemRow());
      selectedRow = 0;
      renderItemsTable();
    });
}

// ── Reset form ─────────────────────────────────────────────
function resetForm() {
  isEditMode = false;
  $('fDocNo').value = CFG.nextDocNo || '';
  $('fRefCode').value = '';
  $('fRefName').value = '';
  $('fSmCode').value = '';
  $('fExpWgt').value = '0.000';
  $('fNote').value = '';
  itemRows = [newItemRow()];
  selectedRow = 0;
  renderItemsTable();
  const today = new Date();
  $('fDate').value = String(today.getDate()).padStart(2,'0') + '/' + String(today.getMonth()+1).padStart(2,'0') + '/' + today.getFullYear();
  focusGridCell(0, 'item_code', false);
}

// ── Close modals ───────────────────────────────────────────
document.querySelectorAll('[data-close]').forEach(btn => {
  btn.addEventListener('click', () => closeModal(btn.dataset.close));
});
$('msgModalOk').addEventListener('click', () => $('msgModal').classList.remove('active'));
$('msgModal').addEventListener('click', e => { if (e.target === $('msgModal')) $('msgModal').classList.remove('active'); });

// ── Keyboard shortcuts ─────────────────────────────────────
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); $('btnSave').focus(); if (CFG.software.EnterSave !== 'N') runPrimaryAction(); }
  if (e.key === 'Escape') {
    const modals = ['itemSearchModal','refinerSearchModal','billSearchModal','msgModal'];
    let closed = false;
    modals.forEach(id => { if ($(id).classList.contains('active')) { closeModal(id); closed = true; } });
    if (!closed && confirm('You want to exit ?')) {
      if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'close-module' }, '*');
    }
    e.preventDefault();
  }
});

// Enter key navigation: fDate → fRefCode → fSmCode → grid
$('fDate').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); $('fRefCode').focus(); } });
$('fSmCode').addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    selectedRow = 0;
    renderItemsTable();
    focusGridCell(0, 'item_code', false);
  }
});
$('fNote').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); $('btnSave').focus(); } });

$('btnExit').addEventListener('click', () => {
  if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'close-module' }, '*');
});

// ── Init ───────────────────────────────────────────────────
itemRows.push(newItemRow());
selectedRow = 0;
renderItemsTable();
const today = new Date();
$('fDate').value = String(today.getDate()).padStart(2,'0') + '/' + String(today.getMonth()+1).padStart(2,'0') + '/' + today.getFullYear();
$('fDocNo').value = CFG.nextDocNo || '';

if (CFG.mode === 'cancel') {
  $('btnSave').textContent = 'Cancel';
}

const qs = new URLSearchParams(window.location.search);
const qsDocNo = qs.get('doc_no');
if (qsDocNo) {
  loadBill(qsDocNo);
} else if (CFG.mode === 'edit' || CFG.mode === 'cancel') {
  $('billSearchModal').classList.add('active');
  $('billSearchQ').focus();
  doBillSearch();
}

focusGridCell(0, 'item_code', false);

})();
</script>
</body>
</html>
