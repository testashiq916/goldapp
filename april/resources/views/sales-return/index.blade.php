<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('js/goldapp-common.js') }}"></script>
<title>{{ $title }}</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
  --g-bg:       #f4f7fb;
  --g-surface:  #ffffff;
  --g-border:   #e3e8ef;
  --g-text:     #384250;
  --g-muted:    #7b8794;
  --g-primary:  #5b6dee;
  --g-header:   #2f3d8f;
  --g-header-2: #4457cb;
}

body {
  font-family: 'Inter', 'Segoe UI', Tahoma, sans-serif;
  font-size: 12px;
  background: radial-gradient(circle at 10% -10%, #eef3ff 0%, #f4f7fb 40%, #edf2f8 100%);
  color: var(--g-text);
  height: 100vh;
  overflow: hidden;
}

input, select, button { transition: all 0.15s ease; font-family: inherit; }

::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: #f7fafc; }
::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: #a0aec0; }

/* ── Main Window ── */
.main-window {
  background: var(--g-surface);
  border: 1px solid var(--g-border);
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(33,52,89,.10);
  margin: 10px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  height: calc(100vh - 20px);
  position: relative;
}

/* ── Title Bar ── */
.title-bar {
  background: linear-gradient(135deg, var(--g-header), var(--g-header-2));
  color: #fff;
  padding: 10px 14px;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
  letter-spacing: .3px;
  flex-shrink: 0;
}
.title-bar .icon { width: 16px; height: 16px; background: #ffb548; border-radius: 5px; flex-shrink:0; }
.title-bar .date-badge { margin-left: auto; font-size: 11px; font-weight: 500; opacity: .85; }

/* ── Top Section ── */
.top-section {
  background: #f9fbff;
  border-bottom: 1px solid var(--g-border);
  padding: 10px 14px;
  display: grid;
  grid-template-columns: 1.2fr 1fr 1fr;
  gap: 4px 18px;
  flex-shrink: 0;
}
.top-section .row {
  display: flex;
  align-items: center;
  gap: 5px;
  min-height: 26px;
}
.top-section label {
  font-weight: 600;
  font-size: 11px;
  color: var(--g-muted);
  min-width: 72px;
  white-space: nowrap;
}
.top-section input,
.top-section select {
  font-size: 11px;
  padding: 2px 7px;
  border: 1px solid #d6deea;
  border-radius: 7px;
  height: 26px;
  background: #fff;
  color: var(--g-text);
  box-shadow: inset 0 1px 2px rgba(17,24,39,.03);
}
.top-section input:focus,
.top-section select:focus {
  border-color: #a9b7ff;
  box-shadow: 0 0 0 3px rgba(91,109,238,.14);
  outline: none;
}
.top-section input[readonly] { background: #f1f4f9; color: #6b7280; }
.top-section input.sm  { width: 64px; }
.top-section input.md  { width: 104px; }
.top-section input.lg  { flex: 1; min-width: 0; }
.top-section select.md { width: 130px; }
.top-section select.lg { flex: 1; min-width: 0; }
.top-section .inline-chk {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 11px; font-weight: 600; color: var(--g-muted); white-space: nowrap;
}
.top-section input[type="checkbox"] { width: 15px; height: 15px; accent-color: var(--g-primary); }

/* ── Table Container ── */
.table-container {
  flex: 1;
  overflow: hidden;
  margin: 0 10px;
  border: 1px solid var(--g-border);
  border-radius: 10px;
  box-shadow: 0 3px 10px rgba(32,55,92,.05);
  display: flex;
  flex-direction: column;
}
.table-scroll { overflow: auto; flex: 1; }
table.items {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
}
table.items thead th {
  background: linear-gradient(180deg, #364891, #2d3d7b);
  color: #f7f9ff;
  padding: 5px 5px;
  border: 1px solid #42529e;
  font-weight: 600;
  font-size: 10px;
  text-align: center;
  white-space: nowrap;
  text-transform: uppercase;
  letter-spacing: .5px;
  position: sticky; top: 0; z-index: 5;
}
table.items tbody td {
  border: 1px solid #edf1f7;
  padding: 2px 3px;
  height: 22px;
  text-align: center;
}
table.items tbody tr:nth-child(odd)  { background: #fff; }
table.items tbody tr:nth-child(even) { background: #f7faff; }
table.items tbody tr:hover           { background: #edf3ff; }
table.items tbody tr.hi              { background: #ddeaff !important; }
table.items tbody input {
  font-size: 11px; border: none; background: transparent;
  text-align: center; width: 100%; height: 100%;
}
table.items tbody input:focus {
  background: #fffff0;
  outline: 2px solid #4299e1;
  border-radius: 2px;
}
.del-btn {
  background: #fee2e2; color: #dc2626;
  border: 1px solid #fca5a5; border-radius: 4px;
  padding: 1px 6px; cursor: pointer; font-size: 10px; font-weight: 700;
}
.del-btn:hover { background: #fca5a5; }

/* ── Table Footer Bar ── */
.table-footer {
  display: flex;
  align-items: center;
  background: #f9fbff;
  padding: 5px 10px;
  gap: 10px;
  font-size: 11px;
  font-weight: 600;
  color: var(--g-text);
  border-top: 1px solid var(--g-border);
  flex-shrink: 0;
  flex-wrap: wrap;
}
.table-footer button {
  background: #fff;
  border: 1px solid #d0d9ea;
  border-radius: 8px;
  padding: 3px 14px;
  font-size: 11px;
  cursor: pointer;
  font-weight: 600;
  color: #3f4a5b;
}
.table-footer button:hover { background: #eef3ff; border-color: #bfcaf0; }
.tf-divider { color: #d0d9ea; }
.tf-stat { color: var(--g-muted); }
.tf-val  { color: #1d4ed8; font-weight: 700; }

/* ── Bottom / Totals Section ── */
.bottom-section {
  background: #f9fbff;
  border-top: 1px solid var(--g-border);
  padding: 8px 14px;
  display: grid;
  grid-template-columns: auto auto;
  gap: 4px 28px;
  align-items: start;
  padding-right: 170px;
  flex-shrink: 0;
}
.bottom-section .col { display: flex; flex-direction: column; gap: 4px; }
.tot-row { display: flex; align-items: center; gap: 5px; min-height: 24px; }
.tot-lbl {
  font-size: 11px; font-weight: 600; color: var(--g-muted);
  min-width: 68px; text-align: right; white-space: nowrap;
}
.tot-val {
  font-size: 11px;
  padding: 2px 7px;
  border: 1px solid #d6deea;
  border-radius: 7px;
  height: 24px;
  width: 96px;
  background: #fff;
  text-align: right;
  color: var(--g-text);
  box-shadow: inset 0 1px 2px rgba(17,24,39,.03);
  font-variant-numeric: tabular-nums;
}
.tot-val:focus { border-color: #a9b7ff; box-shadow: 0 0 0 3px rgba(91,109,238,.14); outline: none; }
.tot-val[readonly] { background: #f1f4f9; color: #6b7280; }
.tot-val.hi { color: #1d4ed8; font-weight: 700; background: #eef4ff; border-color: #bfcaf0; }
.tot-sm { width: 56px; }

/* ── Action Buttons ── */
.action-buttons {
  position: absolute;
  right: 14px;
  bottom: 10px;
  display: flex;
  flex-direction: column;
  gap: 5px;
  z-index: 20;
}
.action-buttons button {
  background: #fff;
  border: 1px solid #cfd8ec;
  border-radius: 10px;
  padding: 5px 18px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  min-width: 140px;
  height: 34px;
  color: #2f3a4f;
  text-align: center;
}
.action-buttons button:hover  { background: #eff4ff; border-color: #9fb3f7; }
.action-buttons button:active { transform: translateY(1px); }
.action-buttons button.highlight {
  background: linear-gradient(180deg, #f5f7ff, #eaf0ff);
  border-color: #9eb0ff; color: #35459f;
}
.action-buttons button.highlight:hover { background: #dce8ff; }
.action-buttons button.danger {
  background: linear-gradient(180deg, #fff5f5, #ffe4e4);
  border-color: #fca5a5; color: #b91c1c;
}
.action-buttons button.danger:hover { background: #ffe4e4; }

/* ── List Modal ── */
.modal-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(15,23,42,.35);
  backdrop-filter: blur(2px);
  z-index: 1000;
  justify-content: center;
  align-items: center;
}
.modal-overlay.active { display: flex; }
.modal-box {
  background: var(--g-surface);
  border: 1px solid var(--g-border);
  border-radius: 12px;
  box-shadow: 0 18px 48px rgba(0,0,0,.18);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: 86vh;
  animation: popIn .18s ease-out;
}
.modal-box.lg { width: min(920px, 96vw); }
@keyframes popIn {
  from { transform: scale(.95); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}
.modal-hdr {
  background: linear-gradient(135deg, var(--g-header), var(--g-header-2));
  color: #fff;
  padding: 9px 14px;
  font-weight: 700;
  font-size: 13px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.modal-close {
  background: rgba(255,255,255,.15);
  border: none; color: #fff;
  width: 24px; height: 22px;
  border-radius: 5px; cursor: pointer;
  font-size: 14px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}
.modal-close:hover { background: #fc8181; }
.modal-body { padding: 10px; overflow-y: auto; flex: 1; }
.modal-srch { margin-bottom: 8px; }
.modal-srch input {
  width: 280px; height: 28px;
  padding: 2px 10px;
  border: 1px solid #d6deea;
  border-radius: 8px;
  font-size: 11px;
  font-family: inherit;
}
.modal-srch input:focus {
  border-color: #a9b7ff;
  box-shadow: 0 0 0 3px rgba(91,109,238,.14);
  outline: none;
}

/* ── List Table ── */
table.ltbl { width: 100%; border-collapse: collapse; font-size: 11px; }
table.ltbl thead th {
  background: linear-gradient(180deg, #364891, #2d3d7b);
  color: #f7f9ff; padding: 5px 8px;
  text-align: left; white-space: nowrap;
  border: 1px solid #42529e;
  font-size: 10px; text-transform: uppercase; letter-spacing: .4px;
}
table.ltbl tbody td { padding: 4px 8px; border-bottom: 1px solid #edf1f7; }
table.ltbl tbody tr:hover td { background: #edf3ff; }
.list-btn {
  background: #fff; border: 1px solid #d0d9ea; border-radius: 6px;
  padding: 2px 8px; font-size: 10px; font-weight: 600; cursor: pointer;
  color: #3f4a5b; margin-right: 3px;
}
.list-btn:hover { background: #eef3ff; }
.list-btn.red   { border-color: #fca5a5; color: #b91c1c; }
.list-btn.red:hover { background: #fee2e2; }
.list-btn.blue  { border-color: #93c5fd; color: #1d4ed8; }
.list-btn.blue:hover { background: #dbeafe; }

/* ── Toast ── */
.toast {
  display: none;
  position: fixed; bottom: 20px; right: 20px;
  padding: 9px 16px;
  border-radius: 8px;
  font-size: 12px; font-weight: 600;
  z-index: 9999;
  box-shadow: 0 6px 20px rgba(0,0,0,.15);
  animation: slideIn .2s ease-out;
}
@keyframes slideIn {
  from { transform: translateY(12px); opacity: 0; }
  to   { transform: translateY(0);   opacity: 1; }
}
.t-ok  { background: #065f46; color: #d1fae5; }
.t-err { background: #7f1d1d; color: #fee2e2; }
.t-inf { background: #1e3a8a; color: #dbeafe; }

@media print { .action-buttons, .table-footer { display: none !important; } }
</style>
@include('partials.print-layout-head')
</head>
<body>

<div class="main-window">

  <!-- ── Title Bar ─────────────────────────────────────────────────── -->
  <div class="title-bar">
    <div class="icon"></div>
    <span>{{ $title }}</span>
    <span class="date-badge" id="hdrDate"></span>
  </div>

  <!-- ── Top Section ───────────────────────────────────────────────── -->
  <div class="top-section">

    <!-- Col 1 -->
    <div>
      <div class="row">
        <label>Doc No</label>
        <input type="text" id="billno" class="md" readonly placeholder="Auto">
        <label class="inline-chk"><input type="checkbox" id="cbxManual"> Manual</label>
      </div>
      <div class="row">
        <label>Date</label>
        <input type="date" id="tdate" class="md" style="background:#fefce8;">
      </div>
      <div class="row">
        <label>Customer</label>
        <input type="text" id="custcode" class="sm" style="text-transform:uppercase" placeholder="Code"
               oninput="onCustCodeInput()" list="custList">
        <datalist id="custList"></datalist>
        <input type="text" id="custname" class="lg" placeholder="Name" readonly>
      </div>
      <div class="row">
        <label>Ref Sale Bill</label>
        <input type="text" id="sbillno" class="md" placeholder="Orig. Bill No" list="origBillList"
               oninput="searchOrigBills(this.value)" onblur="loadSaleBill()">
        <datalist id="origBillList"></datalist>
        <button type="button" class="table-footer" style="border:1px solid #d0d9ea;border-radius:8px;padding:3px 10px;font-size:11px;cursor:pointer;background:#fff;color:#3f4a5b;font-weight:600;" onclick="loadSaleBill()">&#128269; Load</button>
      </div>
    </div>

    <!-- Col 2 -->
    <div>
      <div class="row">
        <label>Bill Type</label>
        <select id="billtype" class="lg"><option value="">-- Select --</option></select>
      </div>
      <div class="row">
        <label>Salesman</label>
        <select id="smcode" class="lg"><option value="">-- Select --</option></select>
      </div>
      <div class="row">
        <label>Rate/gm</label>
        <input type="number" id="grate" class="md" readonly style="text-align:right;">
      </div>
    </div>

    <!-- Col 3 -->
    <div>
      <div class="row">
        <label>OB</label>
        <input type="number" id="ob" class="sm" readonly style="text-align:right;">
        <label style="min-width:28px; margin-left:4px;">CB</label>
        <input type="number" id="cb" class="sm" readonly style="text-align:right;">
      </div>
      <div class="row" style="gap:14px; margin-top:2px;">
        <label class="inline-chk" style="min-width:auto;">
          <input type="checkbox" id="cbxInterstate"> Interstate
        </label>
      </div>
    </div>

  </div><!-- /.top-section -->

  <!-- ── Item Grid ─────────────────────────────────────────────────── -->
  <div class="table-container">
    <div class="table-scroll">
      <table class="items">
        <thead>
          <tr>
            <th style="width:28px">#</th>
            <th style="width:88px">Item Code</th>
            <th style="min-width:140px">Item Name</th>
            <th style="width:60px">Note</th>
            <th style="width:44px">Purity</th>
            <th style="width:42px">Qty</th>
            <th style="width:72px">Wt (gm)</th>
            <th style="width:64px">St.Wgt</th>
            <th style="width:64px">Net Wgt</th>
            <th style="width:68px">St.Price</th>
            <th style="width:68px">Wastage</th>
            <th style="width:46px">VA%</th>
            <th style="width:78px">MC Amt</th>
            <th style="width:78px">Amount</th>
            <th style="width:64px">Rate</th>
            <th style="width:28px"></th>
          </tr>
        </thead>
        <tbody id="gridBody"></tbody>
      </table>
      <datalist id="itemCodeList"></datalist>
    </div>

    <!-- Table Footer Bar -->
    <div class="table-footer">
      <button onclick="addRow()">+ Add Row &nbsp;(Ctrl+A)</button>
      <button onclick="deleteCurrentRow()">&#8722; Del Row</button>
      <span class="tf-divider">|</span>
      <span class="tf-stat">Items: <span class="tf-val" id="totalItems">0</span></span>
      <span class="tf-stat">Qty: <span class="tf-val" id="totalQty">0</span></span>
      <span class="tf-stat">Wgt: <span class="tf-val" id="totalWgt">0.000</span> gm</span>
      <span class="tf-stat" id="avgVaPerc" style="color:#d97706;"></span>
    </div>
  </div>

  <!-- ── Bottom Totals ──────────────────────────────────────────────── -->
  <div class="bottom-section">
    <!-- Col 1: Bill Total / Tax / Cess / Net Total -->
    <div class="col">
      <div class="tot-row">
        <span class="tot-lbl">Bill Total</span>
        <input type="number" id="billtotal" class="tot-val" readonly>
      </div>
      <div class="tot-row">
        <span class="tot-lbl">Tax %</span>
        <input type="number" id="taxperc" class="tot-val tot-sm" step="0.01" onchange="recalcTotals()">
        <span class="tot-lbl" style="min-width:36px;">Tax</span>
        <input type="number" id="staxamt" class="tot-val" onchange="recalcTotals()">
      </div>
      <div class="tot-row">
        <span class="tot-lbl">Cess %</span>
        <input type="number" id="astperc" class="tot-val tot-sm" step="0.01" onchange="recalcCess()">
        <span class="tot-lbl" style="min-width:36px;">Cess</span>
        <input type="number" id="astamt" class="tot-val" onchange="recalcTotals()">
      </div>
      <div class="tot-row">
        <span class="tot-lbl">Net Total</span>
        <input type="number" id="nettot" class="tot-val hi" readonly>
      </div>
    </div>

    <!-- Col 2: Discount / Paid / Balance -->
    <div class="col">
      <div class="tot-row">
        <span class="tot-lbl">Discount</span>
        <input type="number" id="discount" class="tot-val" step="0.01" onchange="recalcTotals()">
      </div>
      <div class="tot-row">
        <span class="tot-lbl">Paid Amt</span>
        <input type="number" id="pamt" class="tot-val" step="0.01" onchange="recalcTotals()">
      </div>
      <div class="tot-row">
        <span class="tot-lbl">Balance</span>
        <input type="number" id="balance" class="tot-val hi" readonly>
      </div>
    </div>
  </div><!-- /.bottom-section -->

  <!-- ── Action Buttons ─────────────────────────────────────────────── -->
  <div class="action-buttons">
    @if($mode === 'bill' || $mode === 'edit')
    <button class="highlight" id="btnSave" onclick="saveSalesReturn()">&#10003; Save &nbsp;F9</button>
    @endif
    @if($mode === 'cancel')
    <button class="danger" onclick="openList('cancel')">&#10007; Cancel Bill</button>
    @endif
    @if($mode === 'reprint')
    <button onclick="openList('reprint')">&#9112; Reprint</button>
    @endif
    <button onclick="openList('view')">&#9776; Records</button>
    <button onclick="resetForm()">&#8635; New</button>
    <button onclick="confirmExit()">&#10005; Exit</button>
  </div>

</div><!-- /.main-window -->

<!-- ── List Modal ─────────────────────────────────────────────────────── -->
<div id="listModal" class="modal-overlay">
  <div class="modal-box lg">
    <div class="modal-hdr">
      <span id="listTitle">Sales Return Records</span>
      <button class="modal-close" onclick="closeModal('listModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="modal-srch">
        <input type="text" id="searchQ" placeholder="Search bill no / customer..."
               oninput="filterList()">
      </div>
      <table class="ltbl">
        <thead>
          <tr>
            <th>Bill No</th><th>Date</th><th>Customer</th><th>Type</th>
            <th style="text-align:right">Items</th>
            <th style="text-align:right">Bill Amt</th>
            <th style="text-align:right">Paid</th>
            <th style="text-align:right">Net Amt</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="listBody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── Toast ──────────────────────────────────────────────────────────── -->
<div id="toast" class="toast"></div>

<script>
// ── Config ────────────────────────────────────────────────────────────────────
const API   = '{{ url("/api/sales-return") }}';
const CSRF  = document.querySelector('meta[name="csrf-token"]').content;
const PMODE = '{{ $mode }}';

// ── State ─────────────────────────────────────────────────────────────────────
const S = {
    items: [], customers: [], billTypes: [],
    rows: [], curRow: -1, grate: 0,
    slno: 0, mode: 'add', listData: [], listAction: 'view',
};

// ── Helpers ───────────────────────────────────────────────────────────────────
const esc  = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const f2   = n => parseFloat(n||0).toFixed(2);
const f3   = n => parseFloat(n||0).toFixed(3);
const fmtD = s => { if (!s) return ''; const p=String(s).slice(0,10).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; };
const G    = id => document.getElementById(id);
const GV   = id => parseFloat(G(id).value)||0;

function toast(msg, type='inf') {
    const t=G('toast'); t.textContent=msg;
    t.className='toast t-'+type; t.style.display='block';
    clearTimeout(t._t); t._t=setTimeout(()=>{ t.style.display='none'; },3500);
}
function closeModal(id) { G(id).classList.remove('active'); }

// ── API ───────────────────────────────────────────────────────────────────────
async function get(ep, p={}) {
    const u=new URL(API+'/'+ep);
    Object.entries(p).forEach(([k,v])=>u.searchParams.set(k,v));
    const r=await fetch(u); if (!r.ok) throw new Error('HTTP '+r.status);
    return r.json();
}
async function post(ep, d) {
    const r=await fetch(API+'/'+ep, {
        method:'POST', body:JSON.stringify(d),
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
    }); if (!r.ok) throw new Error('HTTP '+r.status);
    return r.json();
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    G('hdrDate').textContent = fmtD(new Date().toISOString().slice(0,10));
    G('tdate').value = new Date().toISOString().slice(0,10);
    await loadMaster();
    setupKeys();
    const qs = new URLSearchParams(window.location.search);
    const slno = parseInt(qs.get('slno') || '0', 10);
    if (slno > 0) {
        loadEdit(slno);
    } else if (PMODE==='edit'||PMODE==='cancel'||PMODE==='reprint') {
        setTimeout(()=>openList(PMODE),200);
    }
    else addRow();
});

async function loadMaster() {
    const [cP,sP,iP,bP,rP] = await Promise.allSettled([
        get('customers'), get('salesmen'), get('items'), get('bill-types'), get('gold-rate')
    ]);
    const cR = cP.status === 'fulfilled' ? cP.value : {success:false};
    const sR = sP.status === 'fulfilled' ? sP.value : {success:false};
    const iR = iP.status === 'fulfilled' ? iP.value : {success:false};
    const bR = bP.status === 'fulfilled' ? bP.value : {success:false};
    const rR = rP.status === 'fulfilled' ? rP.value : {success:false};

    if (cR.success) {
        S.customers=cR.data;
        const dl=G('custList');
        cR.data.forEach(c=>{ const o=document.createElement('option'); o.value=c.code; o.label=c.name+(c.mobno?' ('+c.mobno+')':''); dl.appendChild(o); });
    }
    if (sR.success) {
        const sel=G('smcode');
        sR.data.forEach(s=>{ const o=document.createElement('option'); o.value=s.code; o.textContent=s.name; sel.appendChild(o); });
    }
    if (iR.success) {
        S.items=iR.data;
        const dl=G('itemCodeList');
        iR.data.forEach(i=>{ const o=document.createElement('option'); o.value=i.code; o.label=i.name; dl.appendChild(o); });
    }
    if (bR.success) {
        S.billTypes=bR.data;
        const sel=G('billtype');
        bR.data.forEach(b=>{ const o=document.createElement('option'); o.value=b.code; o.textContent=b.name; sel.appendChild(o); });
        if (bR.data.length) {
            const gold=bR.data.find(b=>String(b.code||'').toUpperCase()==='G' || String(b.name||'').toLowerCase().includes('gold'));
            sel.value=(gold?gold.code:bR.data[0].code);
            onBTChange();
        }
    }
    if (rR.success) {
        S.grate = Number(rR.rate || 0);
        G('grate').value = S.grate;
    }
    await refreshBillNo();
    if (!rR.success) toast('Gold rate fetch failed', 'err');
    if (!bR.success) toast('Bill type/tax fetch failed', 'err');
}

// ── Bill No ───────────────────────────────────────────────────────────────────
async function refreshBillNo() {
    if (G('cbxManual').checked) return;
    const r=await get('next-number',{type:'B', bill_type: G('billtype').value});
    if (r.success) G('billno').value=r.billno;
}
G('cbxManual').addEventListener('change', function(){
    G('billno').readOnly=!this.checked;
    if (!this.checked) refreshBillNo(); else { G('billno').readOnly=false; G('billno').focus(); }
});
function onBTChange() {
    const bt=S.billTypes.find(b=>b.code===G('billtype').value);
    if (bt && bt.taxperc!=null) { G('taxperc').value=bt.taxperc||0; recalcTotals(); }
    refreshBillNo();
}
G('billtype').addEventListener('change', onBTChange);

// ── Customer ──────────────────────────────────────────────────────────────────
function onCustCodeInput() {
    const code=G('custcode').value.toUpperCase(); G('custcode').value=code;
    const c=S.customers.find(x=>x.code===code);
    if (c) { G('custname').value=c.name; loadCustBal(code); }
    else G('custname').value='';
}
async function loadCustBal(code) {
    const r=await get('customer-balance',{code});
    if (r.success) { G('ob').value=r.balance!==0?(-r.balance).toFixed(2):''; recalcTotals(); }
}

// ── Load Sale Bill ────────────────────────────────────────────────────────────
async function loadSaleBill() {
    const bn=G('sbillno').value.trim(); if (!bn) return;
    try {
        const r=await get('search-sale-bill',{billno:bn});
        if (!r.success) { toast(r.error||'Bill not found','err'); return; }
        if (r.master.custcode) { G('custcode').value=r.master.custcode; onCustCodeInput(); }
        if (r.master.discount) G('discount').value=r.master.discount;
        if (r.master.billtype) G('billtype').value=r.master.billtype;
        S.rows=[]; G('gridBody').innerHTML='';
        (r.details||[]).forEach(d=>addRowWith({
            code:d.code, itemname:d.itemname||d.name, qty:d.qty, weight:d.weight,
            stonewgt:d.stonewgt||0, stoneprice:d.stoneprice||0, mcharge:d.mcharge,
            wastage:d.wastage, amount:d.amount, rate:d.rate, vaperc:d.vaperc||0,
            stktype:d.stktype||'', iqtype:d.itemtype||'', fr:d.fr||0, cost:d.cost||0,
            stkinnos:d.stkinnos||'N',
        }));
        recalcAll();
        toast('Loaded '+r.details.length+' items from '+(r.master.billno||bn),'ok');
    } catch(e) {
        toast('Failed to load bill: '+e.message,'err');
    }
}

let __origBillTimer = null;
async function searchOrigBills(q) {
    if (__origBillTimer) clearTimeout(__origBillTimer);
    __origBillTimer = setTimeout(async () => {
        const dl = G('origBillList');
        if (!dl) return;
        dl.innerHTML = '';
        if (!q || q.trim().length < 1) return;
        try {
            const r = await get('search-sale-bills', { q: q.trim(), limit: 25 });
            if (!r.success || !Array.isArray(r.data)) return;
            r.data.forEach(b => {
                const o = document.createElement('option');
                o.value = b.billno || '';
                const dt = b.tdate ? String(b.tdate).slice(0,10) : '';
                o.label = (b.custname || '') + (dt ? ' | ' + dt : '');
                dl.appendChild(o);
            });
        } catch(_) {}
    }, 180);
}

// ── Grid ──────────────────────────────────────────────────────────────────────
function addRow() { addRowWith({}); }
function addRowWith(d={}) {
    const idx=S.rows.length; S.rows.push({...d});
    renderRow(idx); S.curRow=idx; hiRow(idx);
    setTimeout(()=>{ const el=document.querySelector('#r'+idx+' .cc input'); if(el) el.focus(); },40);
}
function renderRow(idx) {
    const d=S.rows[idx]||{};
    const old=G('r'+idx); if(old) old.remove();
    const nw=((+d.weight||0)-(+d.stonewgt||0)).toFixed(3);
    const tr=document.createElement('tr'); tr.id='r'+idx;
    tr.onclick=()=>{ S.curRow=idx; hiRow(idx); };
    tr.innerHTML=`
      <td style="text-align:center;font-weight:600;font-size:10px;color:#9ca3af;">${idx+1}</td>
      <td class="cc"><input type="text" value="${esc(d.code||'')}" placeholder="Code"
            style="text-transform:uppercase;" list="itemCodeList"
            onchange="onCodeChange(${idx},this.value)"
            onkeydown="gk(event,${idx},'code')"></td>
      <td><input type="text" value="${esc(d.itemname||d.name||'')}" placeholder="Name"
            onchange="S.rows[${idx}].itemname=this.value" onkeydown="gk(event,${idx},'name')"></td>
      <td><input type="text" value="${esc(d.note||'')}"
            onchange="S.rows[${idx}].note=this.value"></td>
      <td><input type="text" value="${esc(d.iqtype||'')}" readonly
            style="color:#9ca3af;text-align:center;"></td>
      <td><input type="number" value="${d.qty||''}"
            style="text-align:right;font-weight:600;"
            onchange="S.rows[${idx}].qty=+this.value;calcAmt(${idx})"
            onkeydown="gk(event,${idx},'qty')"></td>
      <td><input type="number" value="${d.weight||''}" step="0.001"
            style="text-align:right;font-weight:600;"
            onchange="onWtChg(${idx},+this.value)"
            onkeydown="gk(event,${idx},'weight')"></td>
      <td><input type="number" value="${d.stonewgt||''}" step="0.001"
            style="text-align:right;"
            onchange="S.rows[${idx}].stonewgt=+this.value;calcAmt(${idx})"
            onkeydown="gk(event,${idx},'stonewgt')"></td>
      <td><input type="number" value="${nw}" readonly
            style="text-align:right;background:#f1f4f9;color:#9ca3af;"></td>
      <td><input type="number" value="${d.stoneprice||''}" step="0.01"
            style="text-align:right;"
            onchange="S.rows[${idx}].stoneprice=+this.value;calcAmt(${idx})"
            onkeydown="gk(event,${idx},'stoneprice')"></td>
      <td><input type="number" value="${d.wastage||''}" step="0.001"
            style="text-align:right;"
            onchange="S.rows[${idx}].wastage=+this.value;calcAmt(${idx})"
            onkeydown="gk(event,${idx},'wastage')"></td>
      <td><input type="number" value="${d.vaperc||''}" step="0.01"
            style="text-align:right;"
            onchange="onVaChg(${idx},+this.value)"
            onkeydown="gk(event,${idx},'vaperc')"></td>
      <td><input type="number" value="${d.mcharge||''}" step="0.01"
            style="text-align:right;"
            onchange="S.rows[${idx}].mcharge=+this.value;calcAmt(${idx})"
            onkeydown="gk(event,${idx},'mcharge')"></td>
      <td class="ca"><input type="number" value="${d.amount||''}" step="0.01"
            style="text-align:right;font-weight:700;color:#1d4ed8;"
            onchange="S.rows[${idx}].amount=+this.value;updateTots()"
            onkeydown="gk(event,${idx},'amount')"></td>
      <td><input type="number" value="${d.rate||''}" step="0.01"
            style="text-align:right;"
            onchange="S.rows[${idx}].rate=+this.value;calcAmt(${idx})"
            onkeydown="gk(event,${idx},'rate')"></td>
      <td style="text-align:center;">
        <button class="del-btn" onclick="delRow(${idx})" title="Delete">&#10006;</button>
      </td>`;
    G('gridBody').appendChild(tr);
}

function hiRow(idx) {
    document.querySelectorAll('.items tbody tr').forEach(r=>r.classList.remove('hi'));
    const tr=G('r'+idx); if (!tr) return;
    tr.classList.add('hi');
}

// ── Item Code ─────────────────────────────────────────────────────────────────
async function onCodeChange(idx, raw) {
    const code=raw.trim().toUpperCase(); S.rows[idx].code=code; if(!code) return;
    let item=S.items.find(i=>i.code===code);
    if (!item) { const r=await get('item-details',{code}); if(!r.success){toast('Unknown: '+code,'err');return;} item=r.data; }
    applyItem(idx, item);
}
function applyItem(idx, item) {
    const d=S.rows[idx];
    d.code=item.code; d.itemname=item.name; d.itemtype=item.itype;
    d.stkinnos=item.stkinnos||'N'; d.cost=+(item.cost||0);
    d.stockqty=+(item.qty||0); d.stockwgt=+(item.weight||0);
    d.stktype=item.stktype||''; d.iqtype=item.iqtype||item.itype||'';
    d.vaperc=+(item.vaperc||0); d.wastage_rate=+(item.wastage||0); d.mcrate=+(item.mcharge||0);
    d.rate=item.itype==='G'?S.grate:item.itype==='S'?80:+(item.rate||0);
    if (!d.qty) d.qty=1;
    S.rows[idx]=d; renderRow(idx); hiRow(idx);
    setTimeout(()=>{ const el=document.querySelector('#r'+idx+' td:nth-child(7) input'); if(el){el.focus();el.select();} },40);
}
function onWtChg(idx, wt) {
    const d=S.rows[idx]; d.weight=wt; const nw=wt-(d.stonewgt||0);
    if (d.itemtype==='G' && nw>0) {
        if (d.wastage_rate>0 && !d.wastage) d.wastage=Math.round(nw*d.wastage_rate/8*1000)/1000;
        if (d.vaperc>0 && d.rate>0) d.mcharge=Math.round((nw*d.rate*d.vaperc)/100*100)/100;
        else if (d.mcrate>0)        d.mcharge=Math.round(nw*d.mcrate*100)/100;
    }
    calcAmt(idx);
}
function onVaChg(idx, vap) {
    const d=S.rows[idx]; d.vaperc=vap; const nw=(d.weight||0)-(d.stonewgt||0);
    if (vap>0 && d.rate>0 && nw>0) d.mcharge=Math.round((nw*d.rate*vap)/100*100)/100;
    calcAmt(idx);
}
function calcAmt(idx) {
    const d=S.rows[idx];
    const w=d.weight||0, sw=d.stonewgt||0, wst=d.wastage||0;
    const mc=d.mcharge||0, sp=d.stoneprice||0, rt=d.rate||0, qty=d.qty||0;
    d.amount=Math.round((d.stkinnos==='Y'?qty*rt+sp+mc:(w-sw+wst)*rt+sp+mc)*100)/100;
    const nel=document.querySelector('#r'+idx+' td:nth-child(9) input'); if(nel) nel.value=(w-sw).toFixed(3);
    const ael=document.querySelector('#r'+idx+' .ca input');             if(ael) ael.value=d.amount||'';
    updateTots();
}
function recalcAll() { S.rows.forEach((_,i)=>calcAmt(i)); }

function delRow(idx) {
    if (!confirm('Delete this item?')) return;
    S.rows.splice(idx,1);
    G('gridBody').innerHTML='';
    S.rows.forEach((_,i)=>renderRow(i));
    if (S.curRow>=S.rows.length) S.curRow=S.rows.length-1;
    updateTots();
}
function deleteCurrentRow() { if(S.curRow>=0&&S.curRow<S.rows.length) delRow(S.curRow); }

// ── Totals ────────────────────────────────────────────────────────────────────
function updateTots() {
    let bt=0,tq=0,tw=0;
    S.rows.forEach(d=>{ if(!d.code)return; bt+=d.amount||0; tq+=d.qty||0; tw+=d.weight||0; });
    G('billtotal').value=bt.toFixed(2);
    G('totalQty').textContent=tq;
    G('totalWgt').textContent=tw.toFixed(3);
    G('totalItems').textContent=S.rows.filter(r=>r.code).length;
    recalcTotals();
}
function recalcTotals() {
    const bt=GV('billtotal'), tp=GV('taxperc'), ap=GV('astperc');
    const tax=Math.round(bt*tp/100*100)/100;
    G('staxamt').value=tax.toFixed(2);
    if (ap>0) G('astamt').value=(Math.round(bt*ap/100*100)/100).toFixed(2);
    const st=GV('staxamt'), ast=GV('astamt'), nt=bt+st+ast;
    G('nettot').value=nt.toFixed(2);
    const disc=GV('discount'), pamt=GV('pamt'), bal=nt-disc-pamt;
    G('balance').value=bal.toFixed(2);
    G('cb').value=(GV('ob')-bal).toFixed(2);
    const bt2=GV('billtotal'); let va=0;
    S.rows.forEach(d=>{ if(d.code) va+=((d.wastage||0)*(d.rate||0))+(d.mcharge||0); });
    G('avgVaPerc').textContent=(bt2>0&&va>0)?'AVA% : '+((va/(bt2-va))*100).toFixed(2):'';
}
function recalcCess() {
    G('astamt').value=(Math.round(GV('billtotal')*GV('astperc')/100*100)/100).toFixed(2);
    recalcTotals();
}

// ── Save ──────────────────────────────────────────────────────────────────────
async function saveSalesReturn() {
    const billno=G('billno').value.trim(); if(!billno){toast('Bill No required','err');return;}
    const tdate=G('tdate').value;          if(!tdate){toast('Date required','err');return;}
    const valid=S.rows.filter(r=>r.code&&r.code.trim());
    if(!valid.length){toast('No items entered','err');return;}
    const bt=GV('billtotal'); if(bt<=0){toast('Bill total is zero','err');return;}
    if(!confirm('Save this Sales Return?')) return;

    const pay={
        slno:S.slno, billno, tdate,
        custcode:G('custcode').value, custname:G('custname').value,
        smcode:G('smcode').value, control:1,
        billtype:G('billtype').value, sbillno:G('sbillno').value,
        grate:S.grate, billamt:bt,
        discount:GV('discount'), pamt:GV('pamt'),
        staxamt:GV('staxamt'), staxperc:GV('taxperc'),
        astamt:GV('astamt'), ob:GV('ob'),
        cst:G('cbxInterstate').checked?'Y':'N',
        fr:'N',
        details:valid,
    };
    const btn=G('btnSave'); if(btn) btn.disabled=true;
    try {
        const r=await post('save',pay);
        if(r.success){ toast(r.message,'ok'); S.slno=0; S.mode='add'; setTimeout(()=>resetForm(),1200); }
        else toast(r.error||'Save failed','err');
    } catch(e){ toast('Error: '+e.message,'err'); }
    finally { if(btn) btn.disabled=false; }
}

// ── List / Records ────────────────────────────────────────────────────────────
async function openList(action='view') {
    S.listAction=action;
    const titles={view:'Sales Return Records',edit:'Select Bill to Edit',cancel:'Select Bill to Cancel',reprint:'Select Bill to Reprint'};
    G('listTitle').textContent=titles[action]||'Records';
    const r=await get('list');
    if(!r.success){toast('Failed to load','err');return;}
    S.listData=r.data; renderList(r.data); G('searchQ').value='';
    G('listModal').classList.add('active');
}

function renderList(data) {
    const tb=G('listBody'); tb.innerHTML='';
    if(!data||!data.length){
        tb.innerHTML='<tr><td colspan="9" style="text-align:center;padding:20px;color:#9ca3af;font-size:12px;">No records found</td></tr>';
        return;
    }
    const act=S.listAction;
    data.forEach(r=>{
        const tr=document.createElement('tr');
        let btns='';
        if(act==='view'||act==='edit'){
            btns=`<button class="list-btn blue" onclick="loadEdit(${r.slno})">&#9998; Edit</button>
                  <button class="list-btn red"  onclick="doDelete(${r.slno},'${esc(r.billno)}')">&#128465; Del</button>`;
        } else if(act==='cancel'){
            btns=`<button class="list-btn red" onclick="doCancel(${r.slno},'${esc(r.billno)}')">&#10007; Cancel</button>`;
        } else if(act==='reprint'){
            btns=`<button class="list-btn blue" onclick="doReprint(${r.slno},'${esc(r.billno)}')">&#9112; Print</button>`;
        }
        tr.innerHTML=`
          <td><strong>${esc(r.billno)}</strong></td>
          <td>${fmtD(r.tdate)}</td>
          <td>${esc(r.custname||'—')}</td>
          <td style="text-align:center">${r.control==1?'Bill':'Est.'}</td>
          <td style="text-align:right">${r.item_count||0}</td>
          <td style="text-align:right">${f2(r.billamt)}</td>
          <td style="text-align:right">${f2(r.pamt)}</td>
          <td style="text-align:right"><strong style="color:#1d4ed8">${f2(r.netamt)}</strong></td>
          <td style="white-space:nowrap">${btns}</td>`;
        tb.appendChild(tr);
    });
}
function filterList() {
    const q=G('searchQ').value.toLowerCase();
    renderList(S.listData.filter(r=>(r.billno||'').toLowerCase().includes(q)||(r.custname||'').toLowerCase().includes(q)));
}

async function loadEdit(slno) {
    const r=await get('get',{slno});
    if(!r.success){toast(r.error||'Failed to load','err');return;}
    closeModal('listModal'); resetForm(false);
    const m=r.master; S.slno=slno; S.mode='edit';
    G('billno').value=m.billno||''; G('billno').readOnly=true;
    G('tdate').value=m.tdate?String(m.tdate).slice(0,10):'';
    G('custcode').value=m.custcode||''; G('custname').value=m.custname||'';
    G('smcode').value=m.smcode||'';
    G('billtype').value=m.billtype||''; G('sbillno').value=m.sbillno||'';
    G('discount').value=m.discount||''; G('pamt').value=m.pamt||'';
    G('taxperc').value=m.staxperc||''; G('staxamt').value=m.staxamt||'';
    G('astamt').value=m.astamt||''; G('ob').value=m.ob||'';
    G('cbxInterstate').checked=m.cst==='Y';
    S.rows=[]; G('gridBody').innerHTML='';
    (r.details||[]).forEach(d=>addRowWith({
        code:d.code, itemname:d.name||d.itemname2||'', qty:d.qty, weight:d.weight,
        stonewgt:d.stonewgt||0, stoneprice:d.stoneprice||0, mcharge:d.mcharge,
        wastage:d.wastage, amount:d.amount, rate:d.rate, vaperc:d.vaperc||0,
        stktype:d.stktype||'', iqtype:d.iqtype||d.itemtype||'', note:d.note||'', fr:d.fr||0,
    }));
    updateTots(); toast('Bill loaded for editing','ok');
}

async function doDelete(slno, bn) {
    if(!confirm('Delete Sales Return '+bn+'?')) return;
    const r=await post('delete',{slno});
    if(r.success){toast(r.message,'ok'); openList(S.listAction||'view');}
    else toast(r.error||'Failed','err');
}
async function doCancel(slno, bn) {
    if(!confirm('Cancel Sales Return '+bn+'? Cannot be undone.')) return;
    const r=await post('delete',{slno});
    if(r.success){toast(r.message,'ok'); openList('cancel');}
    else toast(r.error||'Failed','err');
}
function doReprint(slno, bn) { toast('Reprint for '+bn+' — coming soon','inf'); closeModal('listModal'); }

// ── Reset ─────────────────────────────────────────────────────────────────────
function resetForm(doAdd=true) {
    S.slno=0; S.mode='add'; S.rows=[];
    ['custcode','custname','sbillno','discount','pamt','staxamt','astamt','astperc',
     'ob','cb','billtotal','nettot','balance'].forEach(id=>{ const e=G(id); if(e) e.value=''; });
    G('smcode').value='';
    G('tdate').value=new Date().toISOString().slice(0,10);
    if (S.billTypes.length) {
        const gold=S.billTypes.find(b=>String(b.code||'').toUpperCase()==='G' || String(b.name||'').toLowerCase().includes('gold'));
        G('billtype').value=(gold?gold.code:S.billTypes[0].code);
        onBTChange();
    } else {
        G('taxperc').value=0;
    }
    G('cbxInterstate').checked=false;
    G('gridBody').innerHTML='';
    G('totalItems').textContent='0'; G('totalQty').textContent='0'; G('totalWgt').textContent='0.000';
    G('avgVaPerc').textContent='';
    if(!G('cbxManual').checked){ G('billno').readOnly=true; refreshBillNo(); }
    if(doAdd) addRow();
}

// ── Exit ──────────────────────────────────────────────────────────────────────
function confirmExit() {
    if((S.rows.some(r=>r.code)||S.slno>0)&&!confirm('Exit without saving?')) return;
    if(window.parent&&window.parent.closeModule) window.parent.closeModule();
    else window.close();
}

// ── Keyboard ──────────────────────────────────────────────────────────────────
function setupKeys() {
    document.addEventListener('keydown', e=>{
        if(e.key==='F9'){e.preventDefault();saveSalesReturn();}
        if(e.ctrlKey&&e.key.toLowerCase()==='a'){e.preventDefault();addRow();}
        if(e.key==='Escape') document.querySelectorAll('.modal-overlay.active').forEach(m=>m.classList.remove('active'));
    });
}
const COL={code:2,name:3,qty:6,weight:7,stonewgt:8,stoneprice:10,wastage:11,vaperc:12,mcharge:13,amount:14,rate:15};
const ORD=['code','name','qty','weight','stonewgt','stoneprice','wastage','vaperc','mcharge','amount','rate'];
function gk(e,idx,field) {
    if(e.key!=='Enter'&&e.key!=='Tab') return; e.preventDefault();
    const next=ORD[ORD.indexOf(field)+1];
    if(next&&COL[next]){
        const el=document.querySelector('#r'+idx+' td:nth-child('+COL[next]+') input');
        if(el){el.focus();el.select();}
    } else {
        if(idx===S.rows.length-1) addRow();
        else { const el=document.querySelector('#r'+(idx+1)+' .cc input'); if(el){el.focus();el.select();} }
    }
}
</script>
</body>
</html>
