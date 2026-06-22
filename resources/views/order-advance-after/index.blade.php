<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Order Advance After</title>
<script>
window.OAA_CONFIG = {
  siteUrl:   @json(request()->root()),
  mode:      @json($mode),
  rates: {
    gold:     {{ $rates['gold']     ?? 0 }},
    silver:   {{ $rates['silver']   ?? 0 }},
    platinum: {{ $rates['platinum'] ?? 0 }},
  },
  exchItems: @json($exchItems),
  cashBanks: @json($cashBanks ?? []),
  gilevel:   {{ $gilevel ?? 1 }},
};
</script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Arial,sans-serif;font-size:13px;color:#1f2937;overflow:hidden;height:100vh;background:#e6eef3}
input,select,button{transition:all .15s ease}
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-track{background:#f7fafc}
::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:3px}

.main-window{background:#fff;border:1px solid #99bdd0;border-radius:0;box-shadow:none;margin:8px;overflow:hidden;position:relative}

.title-bar{background:#1a3a4a;color:#b8e6ff;padding:8px 10px;font-weight:700;font-size:14px;display:flex;align-items:center;gap:8px;letter-spacing:0}
.title-bar .icon{width:16px;height:16px;background:#f6ad55;border-radius:4px;flex-shrink:0}

/* Top form */
.top-section{background:#dce8f0;padding:8px;display:grid;
  grid-template-columns:1fr 1fr;gap:5px 18px;border:1px solid #99bdd0;border-top:0}
.top-section .row{display:flex;align-items:center;gap:6px;min-height:28px}
.top-section label{font-weight:700;font-size:13px;min-width:110px;color:#111827;text-align:right;padding-right:4px}
.top-section input,.top-section select{font-size:11px;padding:3px 8px;border:1px solid #d6dcea;
  background:#fff;height:30px;border-radius:4px;color:#111827;box-shadow:none}
.top-section input:focus,.top-section select:focus{border-color:#a9b7ff;
  box-shadow:none;outline:2px solid #b8e6ff}
.top-section input.sm{width:80px}
.top-section input.md{width:140px}
.top-section input.lg{width:200px}
.top-section select.lg{width:240px}
.top-section input.readonly{background:#e8e8e8;color:#1f2937}
.top-section .btn-help{font-size:12px;padding:0 14px;border:1px solid #6a9ab8;border-radius:4px;
  cursor:pointer;background:#d0e8f5;height:30px;color:#111827;font-weight:700}
.top-section .btn-help:hover{background:#b8d8ee}

.chk-row{display:flex;align-items:center;gap:14px;padding:5px 14px;background:#dce8f0;border:1px solid #99bdd0;border-top:0}
.chk-row label{font-size:12px;font-weight:700;color:#111827;display:flex;align-items:center;gap:4px;cursor:pointer}
.chk-row input[type=checkbox]{accent-color:#2c6282}

/* Weight advance heading */
.section-heading{padding:6px 14px;font-size:13px;font-weight:700;color:#1a3a4a;
  text-decoration:underline;background:#e6eef3;border-bottom:1px solid #99bdd0;text-align:center}

/* Items table */
.table-container{margin:0;border:1px solid #99bdd0;border-radius:0;
  background:#fff;overflow-x:auto;overflow-y:hidden;box-shadow:none;
  -webkit-overflow-scrolling:touch}
.table-container::-webkit-scrollbar{height:8px}
.table-container::-webkit-scrollbar-track{background:#f0f3f7}
.table-container::-webkit-scrollbar-thumb{background:#b8c2cf;border-radius:4px}
.table-container::-webkit-scrollbar-thumb:hover{background:#8fa0b5}
table.items{width:100%;min-width:900px;border-collapse:collapse;font-size:12px}
table.items thead th{background:#1a3a4a;color:#b8e6ff;
  padding:4px 5px;border:1px solid #bbb;font-weight:700;font-size:11px;
  text-align:center;white-space:nowrap;text-transform:none;letter-spacing:0}
table.items tbody td{border:1px solid #bbb;padding:1px 2px;text-align:center;height:26px}
table.items tbody tr:nth-child(odd){background:#fff}
table.items tbody tr:nth-child(even){background:#f0f7fb}
table.items tbody tr:hover{background:#dff1fb}
table.items tbody tr.sel td{background:#b8e6ff}
table.items tbody input{font-size:12px;border:none;background:transparent;
  text-align:center;width:100%;height:100%}
table.items tbody input:focus{background:#fffff0;outline:2px solid #4299e1;border-radius:2px}
table.items tbody input.num{text-align:right}
table.items tfoot td{background:#1a3a4a;color:#b8e6ff;font-weight:700;font-size:12px;padding:4px 5px;
  border:1px solid #bbb;text-align:center}

/* Table footer buttons */
.table-footer{display:flex;align-items:center;background:#e6eef3;
  padding:5px 10px;gap:8px;font-size:11px;font-weight:600;
  color:#2d3748;border-top:1px solid #99bdd0}
.table-footer button{background:#d0e8f5;border:1px solid #6a9ab8;border-radius:4px;
  padding:0 14px;height:32px;font-size:12px;cursor:pointer;font-weight:700;color:#111827}
.table-footer button:hover{background:#b8d8ee}
.table-footer button:active{transform:translateY(1px)}
.stktype-badge{font-size:10px;background:#f59e0b;color:#fff;padding:2px 8px;border-radius:4px;font-weight:700}

/* Bottom buttons */
.bottom-bar{display:flex;align-items:center;justify-content:center;gap:8px;
  padding:8px 14px;background:#e6eef3;border-top:1px solid #99bdd0}
.bottom-bar button{height:32px;padding:0 22px;font-size:12px;font-weight:700;border-radius:4px;
  cursor:pointer;border:1px solid #6a9ab8;background:#d0e8f5;color:#111827;
  transition:all .15s}
.bottom-bar button:hover{background:#b8d8ee}
.bottom-bar button.primary{background:#2d7d46;color:#fff;border-color:#256b3a}
.bottom-bar button.primary:hover{background:#256b3a}
.bottom-bar button.danger{background:#ef4444;color:#fff;border-color:#dc2626}
.bottom-bar button.danger:hover{background:#dc2626}

/* Modals */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;
  align-items:center;justify-content:center}
.modal-bg.show{display:flex}
.modal{background:#fff;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,.3);
  width:90%;max-width:620px;max-height:80vh;overflow:hidden;display:flex;flex-direction:column}
.modal-head{background:#1a3a4a;color:#b8e6ff;padding:10px 14px;
  font-weight:700;font-size:14px;display:flex;justify-content:space-between;align-items:center}
.modal-head .close-btn{background:none;border:none;color:#fff;font-size:18px;cursor:pointer;line-height:1}
.modal-body{padding:10px;overflow-y:auto;flex:1}
.modal-body input{width:100%;padding:6px 10px;font-size:13px;border:1px solid #99bdd0;
  border-radius:4px;margin-bottom:8px;height:30px}
.modal-body table{width:100%;border-collapse:collapse;font-size:11px}
.modal-body table th{background:#1a3a4a;color:#b8e6ff;padding:5px;font-weight:700;text-align:left;
  position:sticky;top:0}
.modal-body table td{padding:4px 6px;border-bottom:1px solid #ddd;cursor:pointer}
.modal-body table tr:hover td{background:#dff1fb}

/* Status strip */
.status-strip{display:flex;align-items:center;gap:12px;padding:4px 14px;
  font-size:10px;color:#64748b;background:#e6eef3;border-top:1px solid #99bdd0}
.status-strip .dot{width:6px;height:6px;border-radius:50%;background:#10b981}
body{font-size:15px}
.title-bar{font-size:16px}
.top-section .row{min-height:32px}
.top-section label{font-size:14px}
.top-section input,.top-section select{font-size:13px;height:32px}
table.items{font-size:14px}
table.items thead th{font-size:13px;padding:6px 5px}
table.items tbody td{height:28px}
table.items tbody input{font-size:14px}
table.items tfoot td{font-size:14px}
.table-footer{font-size:13px}
.table-footer button{font-size:13px}
.bottom-bar button{font-size:14px;height:36px}
</style>
</head>
<body>

<div class="main-window" id="mainWindow">
  <!-- Title -->
  <div class="title-bar">
    <div class="icon"></div>
    <span id="titleText">Order Advance After {{ $mode === 'edit' ? '- Edit' : '' }}</span>
  </div>

  <!-- Top form fields -->
  <div class="top-section">
    <!-- Left column -->
    <div>
      <div class="row">
        <label>Order No :</label>
        <input type="text" id="fOrdNo" class="md" style="text-transform:uppercase" autocomplete="off">
        <button type="button" class="btn-help" id="btnHelp">Help</button>
      </div>
      <div class="row">
        <label>Date :</label>
        <input type="text" id="fDate" class="md" placeholder="dd/mm/yyyy">
      </div>
      <div class="row">
        <label>Received Amt :</label>
        <input type="text" id="fAmount" class="md num" style="text-align:right" value="0.00">
      </div>
      <div class="row">
        <label>Cash/Bank :</label>
        <select id="fCashBank" class="lg"></select>
      </div>
    </div>
    <!-- Right column -->
    <div>
      <div class="row">
        <label>Prev. Advance :</label>
        <input type="text" id="fPrevAdv" class="md readonly num" style="text-align:right" value="0.00" readonly>
      </div>
      <div class="row">
        <label>Gold Rate :</label>
        <input type="text" id="fGoldRate" class="md num" style="text-align:right">
      </div>
    </div>
  </div>

  <!-- Checkboxes -->
  <div class="chk-row">
    <label><input type="checkbox" id="chkAmtToWgt"> Advance Amt To Wgt</label>
    <label><input type="checkbox" id="chkPrintObCb" checked> Print OB/CB</label>
  </div>

  <!-- Weight advance heading -->
  <div class="section-heading">Weight Advance</div>

  <!-- Items table -->
  <div class="table-container" style="margin-top:4px">
    <table class="items" id="tblItems">
      <thead>
        <tr>
          <th style="width:100px">Item Code</th>
          <th style="width:160px">Item Name</th>
          <th style="width:50px">Qty</th>
          <th style="width:80px">Weight</th>
          <th style="width:80px">Stone Wgt</th>
          <th style="width:70px">Less %</th>
          <th style="width:80px">Less Wgt</th>
          <th style="width:80px">Cost</th>
        </tr>
      </thead>
      <tbody id="itemsBody"></tbody>
      <tfoot>
        <tr>
          <td colspan="2" style="text-align:left;padding-left:10px">Total</td>
          <td id="tfQty">0</td>
          <td id="tfWeight">0.000</td>
          <td id="tfStoneWgt">0.000</td>
          <td></td>
          <td id="tfLessWgt">0.000</td>
          <td id="tfCost">0.00</td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Table footer -->
  <div class="table-footer">
    <button id="btnAddRow">Add</button>
    <button id="btnDeleteRow">Delete</button>
    <span class="stktype-badge" id="badgeStkType"></span>
    <span style="flex:1"></span>
  </div>

  <!-- Bottom action buttons -->
  <div class="bottom-bar">
    <button class="primary" id="btnSave">Save</button>
    <button id="btnPrint">Print Receipt</button>
    <button id="btnNew">New</button>
    <button id="btnPrev">&laquo; Prev</button>
    <button id="btnNext">Next &raquo;</button>
    <button id="btnSearch">Search</button>
    <button class="danger" id="btnDelete">Delete</button>
  </div>

  <!-- Status -->
  <div class="status-strip">
    <span class="dot"></span>
    <span id="statusMsg">Ready</span>
  </div>
</div>

<!-- Order Search Modal -->
<div class="modal-bg" id="modalOrder">
  <div class="modal">
    <div class="modal-head">
      <span>Order Search</span>
      <button class="close-btn" onclick="closeModal('modalOrder')">&times;</button>
    </div>
    <div class="modal-body">
      <input type="text" id="orderSearchInput" placeholder="Search by Order No / Customer..." autocomplete="off">
      <div style="max-height:340px;overflow-y:auto">
        <table>
          <thead><tr><th>Order No</th><th>Date</th><th>Customer</th><th>Bill Amt</th><th>Advance</th></tr></thead>
          <tbody id="orderSearchBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Item Search Modal -->
<div class="modal-bg" id="modalItem">
  <div class="modal">
    <div class="modal-head">
      <span>Item Search</span>
      <button class="close-btn" onclick="closeModal('modalItem')">&times;</button>
    </div>
    <div class="modal-body">
      <input type="text" id="itemSearchInput" placeholder="Search by Code / Name..." autocomplete="off">
      <div style="max-height:340px;overflow-y:auto">
        <table>
          <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Qty</th><th>Weight</th></tr></thead>
          <tbody id="itemSearchBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Advance Search Modal -->
<div class="modal-bg" id="modalSearch">
  <div class="modal">
    <div class="modal-head">
      <span>Search Advances</span>
      <button class="close-btn" onclick="closeModal('modalSearch')">&times;</button>
    </div>
    <div class="modal-body">
      <input type="text" id="advSearchInput" placeholder="Search by Order No / Voucher No..." autocomplete="off">
      <div style="max-height:340px;overflow-y:auto">
        <table>
          <thead><tr><th>Slno</th><th>Order No</th><th>Date</th><th>Voucher</th><th>Amount</th><th>Weight</th></tr></thead>
          <tbody id="advSearchBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
'use strict';
const CFG = window.OAA_CONFIG;
const BASE = CFG.siteUrl;
const CSRF = document.querySelector('meta[name=csrf-token]').content;

/* ── Helpers ── */
const $  = id => document.getElementById(id);
const gv = id => { const el = $(id); return el ? (el.value ?? '') : ''; };
const sv = (id, v) => { const el = $(id); if(el) el.value = v; };
const gf = (id, d=0) => parseFloat(gv(id)) || d;
const r2 = n => Math.round(n*100)/100;
const r3 = n => Math.round(n*1000)/1000;
const fmt2 = n => parseFloat(n||0).toFixed(2);
const fmt3 = n => parseFloat(n||0).toFixed(3);

let currentSlno = 0;
let currentControl = 1;
let focusedItemRow = -1;

/* ── Init ── */
function init() {
    sv('fGoldRate', fmt2(CFG.rates.gold));
    populateCashBanks();
    const today = new Date();
    const dd = String(today.getDate()).padStart(2,'0');
    const mm = String(today.getMonth()+1).padStart(2,'0');
    const yyyy = today.getFullYear();
    sv('fDate', dd+'/'+mm+'/'+yyyy);

    addItemRow();

    if(CFG.mode === 'edit') {
        $('titleText').textContent = 'Order Advance After - Edit';
    }
}

function populateCashBanks(selectedCode = '') {
    const sel = $('fCashBank');
    if(!sel) return;
    const banks = Array.isArray(CFG.cashBanks) ? CFG.cashBanks : [];
    sel.innerHTML = '';
    if(!banks.length) {
        const opt = document.createElement('option');
        opt.value = 'CASH';
        opt.textContent = 'CASH - CASH IN HAND';
        sel.appendChild(opt);
        sel.value = 'CASH';
        return;
    }
    banks.forEach(b => {
        const code = String(b.code || '').trim();
        const name = String(b.name || '').trim();
        const opt = document.createElement('option');
        opt.value = code;
        opt.textContent = code + (name ? ' - ' + name : '');
        sel.appendChild(opt);
    });
    const wanted = String(selectedCode || '').trim().toUpperCase();
    const cash = banks.find(b => String(b.type || '').toUpperCase() === 'H')
        || banks.find(b => String(b.code || '').toUpperCase() === 'CASH')
        || banks[0];
    sel.value = wanted || (cash ? String(cash.code || 'CASH') : 'CASH');
    if(!sel.value && sel.options.length) sel.selectedIndex = 0;
}

/* ── Item rows ── */
function addItemRow(data) {
    const tbody = $('itemsBody');
    const tr = document.createElement('tr');
    const d = data || {};
    tr.innerHTML = `
      <td><input type="text" class="code" value="${d.code||''}" style="text-transform:uppercase" autocomplete="off"></td>
      <td><input type="text" class="name" value="${d.name||''}" readonly style="background:#f8f8f8"></td>
      <td><input type="text" class="num qty" value="${d.qty||0}"></td>
      <td><input type="text" class="num weight" value="${fmt3(d.weight||0)}"></td>
      <td><input type="text" class="num stonewgt" value="${fmt3(d.stonewgt||0)}"></td>
      <td><input type="text" class="num lessperc" value="${fmt2(d.lessperc||0)}"></td>
      <td><input type="text" class="num lesswgt" value="${fmt3(d.lesswgt||0)}"></td>
      <td><input type="text" class="num cost" value="${fmt2(d.cost||0)}"></td>
    `;
    tr._stktype = d.stktype || '';
    tr._iqtype  = d.iqtype || '';
    tbody.appendChild(tr);

    // Code field: Enter => lookup
    const codeInput = tr.querySelector('.code');
    codeInput.addEventListener('keydown', e => {
        if(e.key === 'Enter') {
            e.preventDefault();
            const code = codeInput.value.trim().toUpperCase();
            if(code !== '') {
                lookupItem(code, tr);
            }
            // Move to qty
            tr.querySelector('.qty').focus();
        } else if(e.key === 'F1') {
            e.preventDefault();
            focusedItemRow = getRowIndex(tr);
            openModal('modalItem');
            $('itemSearchInput').value = codeInput.value;
            $('itemSearchInput').focus();
            searchItems(codeInput.value);
        }
    });

    // lessperc => calc lesswgt
    const lp = tr.querySelector('.lessperc');
    lp.addEventListener('change', () => calcLessWgt(tr));
    lp.addEventListener('blur', () => calcLessWgt(tr));

    // Weight/stonewgt change => recalc
    tr.querySelector('.weight').addEventListener('change', () => calcLessWgt(tr));
    tr.querySelector('.stonewgt').addEventListener('change', () => calcLessWgt(tr));

    // All numeric fields: Enter => next column or add row
    const numInputs = tr.querySelectorAll('.num');
    numInputs.forEach((inp, i) => {
        inp.addEventListener('keydown', e => {
            if(e.key === 'Enter') {
                e.preventDefault();
                if(i < numInputs.length - 1) {
                    numInputs[i+1].focus();
                    numInputs[i+1].select();
                } else {
                    // Last column => add new row
                    addNewRowIfNeeded();
                }
            }
        });
        inp.addEventListener('change', updateFooter);
    });

    // Row click => set focused
    tr.addEventListener('click', () => {
        focusedItemRow = getRowIndex(tr);
        highlightRow(tr);
    });

    updateFooter();
    return tr;
}

function getRowIndex(tr) {
    return Array.from($('itemsBody').children).indexOf(tr);
}

function highlightRow(tr) {
    $('itemsBody').querySelectorAll('tr').forEach(r => r.classList.remove('sel'));
    tr.classList.add('sel');
    // Show stktype badge
    $('badgeStkType').textContent = tr._stktype || '';
}

function addNewRowIfNeeded() {
    const rows = $('itemsBody').children;
    const lastRow = rows[rows.length - 1];
    const code = lastRow?.querySelector('.code')?.value?.trim();
    if(code && code !== '') {
        const newTr = addItemRow();
        newTr.querySelector('.code').focus();
    } else {
        lastRow?.querySelector('.code')?.focus();
    }
}

function calcLessWgt(tr) {
    const wgt = parseFloat(tr.querySelector('.weight').value) || 0;
    const stw = parseFloat(tr.querySelector('.stonewgt').value) || 0;
    const lp  = parseFloat(tr.querySelector('.lessperc').value) || 0;
    const lw  = r3((wgt - stw) * lp / 100);
    tr.querySelector('.lesswgt').value = fmt3(lw >= 0 ? lw : 0);
    updateFooter();
}

function updateFooter() {
    let tQty=0, tWgt=0, tStw=0, tLw=0, tCost=0;
    $('itemsBody').querySelectorAll('tr').forEach(tr => {
        tQty  += parseInt(tr.querySelector('.qty')?.value) || 0;
        tWgt  += parseFloat(tr.querySelector('.weight')?.value) || 0;
        tStw  += parseFloat(tr.querySelector('.stonewgt')?.value) || 0;
        tLw   += parseFloat(tr.querySelector('.lesswgt')?.value) || 0;
        tCost += parseFloat(tr.querySelector('.cost')?.value) || 0;
    });
    $('tfQty').textContent = tQty;
    $('tfWeight').textContent = fmt3(tWgt);
    $('tfStoneWgt').textContent = fmt3(tStw);
    $('tfLessWgt').textContent = fmt3(tLw);
    $('tfCost').textContent = fmt2(tCost);
}

/* ── Item lookup ── */
async function lookupItem(code, tr) {
    try {
        const res = await fetch(BASE+'/api/order-advance-after/item-lookup?code='+encodeURIComponent(code));
        const d = await res.json();
        if(d.ok) {
            tr.querySelector('.name').value = d.name || '';
            tr._stktype = d.defstktype || '';
            tr._iqtype  = d.defquality || '';
            $('badgeStkType').textContent = d.defstktype || '';
        } else {
            alert(d.message || 'Item not found');
            tr.querySelector('.code').value = '';
            tr.querySelector('.name').value = '';
            tr.querySelector('.code').focus();
        }
    } catch(e) {}
}

/* ── Order lookup ── */
async function lookupOrder() {
    const ordno = gv('fOrdNo').trim().toUpperCase();
    if(ordno === '') return;
    sv('fOrdNo', ordno);
    status('Looking up order...');
    try {
        const res = await fetch(BASE+'/api/order-advance-after/order-lookup?ordno='+encodeURIComponent(ordno));
        const d = await res.json();
        if(d.ok) {
            sv('fPrevAdv', fmt2(d.prev_advance));
            currentControl = d.control || 1;
            status('Order found: ' + (d.custname || ordno));
        } else {
            alert(d.message || 'Invalid order');
            sv('fOrdNo', '');
            sv('fPrevAdv', '0.00');
            $('fOrdNo').focus();
        }
    } catch(e) {
        status('Error looking up order');
    }
}

/* ── Save ── */
async function doSave() {
    const ordno = gv('fOrdNo').trim().toUpperCase();
    if(ordno === '') { alert('Enter order number'); $('fOrdNo').focus(); return; }

    const amount = gf('fAmount');
    const items = collectItems();
    if(amount === 0 && items.length === 0) {
        alert('Amount is not entered. You can\'t save.');
        $('fAmount').focus();
        return;
    }

    if(!confirm('You want to save?')) return;

    // Open the print popup NOW (within the user-gesture chain) so the browser
    // doesn't block it. We'll redirect it to the receipt URL once save succeeds,
    // or close it if save fails.
    const printWin = window.open('about:blank', '_blank', 'width=900,height=820,scrollbars=yes');

    status('Saving...');
    try {
        const body = {
            ordno:     ordno,
            date:      gv('fDate'),
            rate:      gf('fGoldRate'),
            amount:    amount,
            cashbank_code: gv('fCashBank') || 'CASH',
            amttowgt:  $('chkAmtToWgt').checked ? 'Y' : 'N',
            printobcb: $('chkPrintObCb').checked ? 'Y' : 'N',
            edit_slno: currentSlno > 0 ? currentSlno : 0,
            items:     items,
        };
        const res = await fetch(BASE+'/api/order-advance-after/save', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify(body),
        });
        const d = await res.json();
        if(d.ok) {
            currentSlno = d.slno || 0;
            status('Saved! Voucher: ' + (d.vchno || ''));
            if (printWin && !printWin.closed && currentSlno > 0) {
                printWin.location.href = BASE + '/order-advance-after-receipt?slno=' + currentSlno;
            } else if (currentSlno > 0) {
                // Popup was blocked — fall back to opening a new window now.
                openAdvanceReceipt(currentSlno);
            }
            alert('Advance saved successfully.\nVoucher: ' + (d.vchno || ''));
        } else {
            if (printWin && !printWin.closed) printWin.close();
            alert(d.message || 'Save failed');
            status('Save failed');
        }
    } catch(e) {
        if (printWin && !printWin.closed) printWin.close();
        alert('Error saving: ' + e.message);
        status('Error');
    }
}

function collectItems() {
    const items = [];
    $('itemsBody').querySelectorAll('tr').forEach(tr => {
        const code = tr.querySelector('.code')?.value?.trim()?.toUpperCase();
        const wgt  = parseFloat(tr.querySelector('.weight')?.value) || 0;
        if(code && code !== '' && wgt !== 0) {
            items.push({
                code:     code,
                qty:      parseInt(tr.querySelector('.qty')?.value) || 0,
                weight:   wgt,
                stonewgt: parseFloat(tr.querySelector('.stonewgt')?.value) || 0,
                lessperc: parseFloat(tr.querySelector('.lessperc')?.value) || 0,
                lesswgt:  parseFloat(tr.querySelector('.lesswgt')?.value) || 0,
                cost:     parseFloat(tr.querySelector('.cost')?.value) || 0,
                stktype:  tr._stktype || '',
                iqtype:   tr._iqtype || '',
            });
        }
    });
    return items;
}

/* ── Load advance record ── */
async function loadAdvance(slno) {
    if(!slno || slno <= 0) return;
    status('Loading...');
    try {
        const res = await fetch(BASE+'/api/order-advance-after/get?slno='+slno);
        const d = await res.json();
        if(d.ok) {
            currentSlno = d.slno;
            sv('fOrdNo', d.ordno || '');
            sv('fPrevAdv', fmt2(d.prev_advance || 0));
            sv('fAmount', fmt2(d.amount || 0));
            sv('fGoldRate', fmt2(d.rate || 0));
            populateCashBanks(d.cashbank_code || 'CASH');
            currentControl = d.control || 1;

            // Parse date
            if(d.date) {
                const parts = String(d.date).split('-');
                if(parts.length === 3) {
                    sv('fDate', parts[2]+'/'+parts[1]+'/'+parts[0]);
                } else {
                    sv('fDate', d.date);
                }
            }

            $('chkAmtToWgt').checked = (d.amttowgt === 'Y');

            // Load items
            $('itemsBody').innerHTML = '';
            if(d.items && d.items.length > 0) {
                d.items.forEach(item => addItemRow(item));
            } else {
                addItemRow();
            }

            $('titleText').textContent = 'Order Advance After - Edit [' + (d.docno || '') + ']';
            status('Loaded: ' + (d.docno || 'slno=' + d.slno));
        } else {
            alert(d.message || 'Record not found');
            status('Not found');
        }
    } catch(e) {
        status('Error loading');
    }
}

/* ── New ── */
function doNew() {
    currentSlno = 0;
    sv('fOrdNo', '');
    sv('fDate', '');
    sv('fAmount', '0.00');
    sv('fGoldRate', fmt2(CFG.rates.gold));
    populateCashBanks();
    sv('fPrevAdv', '0.00');
    $('chkAmtToWgt').checked = false;
    $('chkPrintObCb').checked = true;
    $('itemsBody').innerHTML = '';
    addItemRow();

    const today = new Date();
    const dd = String(today.getDate()).padStart(2,'0');
    const mm = String(today.getMonth()+1).padStart(2,'0');
    const yyyy = today.getFullYear();
    sv('fDate', dd+'/'+mm+'/'+yyyy);

    $('titleText').textContent = 'Order Advance After';
    $('fOrdNo').focus();
    status('Ready');
}

/* ── Delete ── */
async function doDelete() {
    if(currentSlno <= 0) { alert('No record loaded to delete'); return; }
    if(!confirm('Are you sure you want to delete this advance?')) return;

    status('Deleting...');
    try {
        const res = await fetch(BASE+'/api/order-advance-after/delete', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify({ slno: currentSlno }),
        });
        const d = await res.json();
        if(d.ok) {
            alert('Advance deleted successfully');
            doNew();
        } else {
            alert(d.message || 'Delete failed');
        }
    } catch(e) {
        alert('Error: ' + e.message);
    }
}

/* ── Navigation ── */
async function doPrev() {
    try {
        const res = await fetch(BASE+'/api/order-advance-after/prev?slno=' + (currentSlno || 999999999));
        const d = await res.json();
        if(d.ok && d.slno) loadAdvance(d.slno);
        else status('No previous record');
    } catch(e) {}
}

async function doNext() {
    try {
        const res = await fetch(BASE+'/api/order-advance-after/next?slno=' + (currentSlno || 0));
        const d = await res.json();
        if(d.ok && d.slno) loadAdvance(d.slno);
        else status('No next record');
    } catch(e) {}
}

/* ── Modals ── */
function openModal(id) { $(id).classList.add('show'); }
function closeModal(id) { $(id).classList.remove('show'); }
window.closeModal = closeModal;

// Order search
async function searchOrders(q) {
    try {
        const res = await fetch(BASE+'/api/order-advance-after/order-search?q='+encodeURIComponent(q||''));
        const d = await res.json();
        const tbody = $('orderSearchBody');
        tbody.innerHTML = '';
        if(d.ok && d.results) {
            d.results.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${r.ordno}</td><td>${r.date||''}</td><td>${r.cust_name||''}</td><td style="text-align:right">${fmt2(r.bill_amt)}</td><td style="text-align:right">${fmt2(r.advance)}</td>`;
                tr.addEventListener('click', () => {
                    sv('fOrdNo', r.ordno);
                    closeModal('modalOrder');
                    lookupOrder();
                });
                tbody.appendChild(tr);
            });
        }
    } catch(e) {}
}

// Item search
async function searchItems(q) {
    try {
        const res = await fetch(BASE+'/api/order-advance-after/item-search?q='+encodeURIComponent(q||''));
        const d = await res.json();
        const tbody = $('itemSearchBody');
        tbody.innerHTML = '';
        if(d.ok && d.results) {
            d.results.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${r.code}</td><td>${r.name}</td><td>${r.type}</td><td>${r.qty}</td><td>${fmt3(r.weight)}</td>`;
                tr.addEventListener('click', () => {
                    const row = $('itemsBody').children[focusedItemRow];
                    if(row) {
                        row.querySelector('.code').value = r.code;
                        lookupItem(r.code, row);
                    }
                    closeModal('modalItem');
                });
                tbody.appendChild(tr);
            });
        }
    } catch(e) {}
}

// Advance search
async function searchAdvances(q) {
    try {
        const res = await fetch(BASE+'/api/order-advance-after/search?q='+encodeURIComponent(q||''));
        const d = await res.json();
        const tbody = $('advSearchBody');
        tbody.innerHTML = '';
        if(d.ok && d.results) {
            d.results.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${r.slno}</td><td>${r.ordno}</td><td>${r.date||''}</td><td>${r.docno}</td><td style="text-align:right">${fmt2(r.amount)}</td><td style="text-align:right">${fmt3(r.wgt)}</td>`;
                tr.addEventListener('click', () => {
                    closeModal('modalSearch');
                    loadAdvance(r.slno);
                });
                tbody.appendChild(tr);
            });
        }
    } catch(e) {}
}

function status(msg) { $('statusMsg').textContent = msg; }

/* ── Event bindings ── */
$('fOrdNo').addEventListener('keydown', e => {
    if(e.key === 'Enter') { e.preventDefault(); lookupOrder(); $('fDate').focus(); }
    if(e.key === 'F1')    { e.preventDefault(); openModal('modalOrder'); $('orderSearchInput').focus(); searchOrders(''); }
});
$('fDate').addEventListener('keydown', e => { if(e.key === 'Enter') { e.preventDefault(); $('fGoldRate').focus(); } });
$('fGoldRate').addEventListener('keydown', e => { if(e.key === 'Enter') { e.preventDefault(); $('fAmount').focus(); } });
$('fAmount').addEventListener('keydown', e => {
    if(e.key === 'Enter') { e.preventDefault(); $('fCashBank').focus(); }
});
$('fCashBank').addEventListener('keydown', e => {
    if(e.key === 'Enter') { e.preventDefault(); doSave(); }
});

function openAdvanceReceipt(slno) {
    const n = parseInt(slno, 10);
    if (!n || n <= 0) return null;
    const url = BASE + '/order-advance-after-receipt?slno=' + n;
    const w = window.open(url, '_blank', 'width=900,height=820,scrollbars=yes');
    return w;
}

function doPrintReceipt() {
    if (!currentSlno || currentSlno <= 0) {
        alert('Save or load an advance receipt before printing.');
        return;
    }
    openAdvanceReceipt(currentSlno);
}

$('btnHelp').addEventListener('click', () => { openModal('modalOrder'); $('orderSearchInput').focus(); searchOrders(''); });
$('btnSave').addEventListener('click', doSave);
$('btnPrint').addEventListener('click', doPrintReceipt);
$('btnNew').addEventListener('click', doNew);
$('btnPrev').addEventListener('click', doPrev);
$('btnNext').addEventListener('click', doNext);
$('btnDelete').addEventListener('click', doDelete);
$('btnSearch').addEventListener('click', () => { openModal('modalSearch'); $('advSearchInput').focus(); searchAdvances(''); });

$('btnAddRow').addEventListener('click', () => addNewRowIfNeeded());
$('btnDeleteRow').addEventListener('click', () => {
    if(focusedItemRow >= 0) {
        const rows = $('itemsBody').children;
        if(rows.length > 1 && focusedItemRow < rows.length) {
            rows[focusedItemRow].remove();
            focusedItemRow = Math.min(focusedItemRow, rows.length - 1);
            updateFooter();
        }
    }
});

// Modal search inputs
let orderSearchTimer;
$('orderSearchInput').addEventListener('input', e => {
    clearTimeout(orderSearchTimer);
    orderSearchTimer = setTimeout(() => searchOrders(e.target.value), 250);
});

let itemSearchTimer;
$('itemSearchInput').addEventListener('input', e => {
    clearTimeout(itemSearchTimer);
    itemSearchTimer = setTimeout(() => searchItems(e.target.value), 250);
});

let advSearchTimer;
$('advSearchInput').addEventListener('input', e => {
    clearTimeout(advSearchTimer);
    advSearchTimer = setTimeout(() => searchAdvances(e.target.value), 250);
});

// Close modals on Escape
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') {
        document.querySelectorAll('.modal-bg.show').forEach(m => m.classList.remove('show'));
    }
    if(e.key === 'F9') { e.preventDefault(); doSave(); }
});

// Close modal on background click
document.querySelectorAll('.modal-bg').forEach(bg => {
    bg.addEventListener('click', e => { if(e.target === bg) bg.classList.remove('show'); });
});

// Init on load
init();
})();
</script>
</body>
</html>
