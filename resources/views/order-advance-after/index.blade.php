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
  gilevel:   {{ $gilevel ?? 1 }},
};
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter','Segoe UI',Tahoma,sans-serif;font-size:12px;color:#1a202c;overflow:hidden;height:100vh;
  background:radial-gradient(circle at 10% -10%,#eef3ff 0%,#f4f7fb 40%,#edf2f8 100%)}
input,select,button{transition:all .15s ease}
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-track{background:#f7fafc}
::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:3px}

.main-window{background:#fff;border:1px solid #d6dcea;border-radius:12px;
  box-shadow:0 10px 30px rgba(33,52,89,.10);margin:8px;overflow:hidden;position:relative}

.title-bar{background:linear-gradient(135deg,#1a3a5f,#2c6282);color:#fff;
  padding:10px 14px;font-weight:700;font-size:13px;display:flex;align-items:center;gap:8px;letter-spacing:.3px}
.title-bar .icon{width:16px;height:16px;background:#f6ad55;border-radius:4px;flex-shrink:0}

/* Top form */
.top-section{background:#f9fbff;padding:10px 14px;display:grid;
  grid-template-columns:1fr 1fr;gap:6px 20px;border-bottom:1px solid #d6dcea}
.top-section .row{display:flex;align-items:center;gap:6px;min-height:28px}
.top-section label{font-weight:600;font-size:11px;min-width:100px;color:#6b7280;text-align:right}
.top-section input{font-size:11px;padding:3px 8px;border:1px solid #d6dcea;
  background:#fff;height:28px;border-radius:7px;color:#2d3748;
  box-shadow:inset 0 1px 2px rgba(17,24,39,.03)}
.top-section input:focus{border-color:#a9b7ff;
  box-shadow:0 0 0 3px rgba(91,109,238,.14);outline:none}
.top-section input.sm{width:80px}
.top-section input.md{width:140px}
.top-section input.lg{width:200px}
.top-section input.readonly{background:#f1f5f9;color:#64748b}
.top-section .btn-help{font-size:10px;padding:2px 10px;border:1px solid #d6dcea;border-radius:6px;
  cursor:pointer;background:#fff;height:28px;color:#374151;font-weight:600}
.top-section .btn-help:hover{background:#eef3ff}

.chk-row{display:flex;align-items:center;gap:14px;padding:4px 14px;background:#f9fbff;border-bottom:1px solid #d6dcea}
.chk-row label{font-size:11px;font-weight:600;color:#374151;display:flex;align-items:center;gap:4px;cursor:pointer}
.chk-row input[type=checkbox]{accent-color:#2c6282}

/* Weight advance heading */
.section-heading{padding:6px 14px;font-size:12px;font-weight:700;color:#1a3a5f;
  text-decoration:underline;background:#f0f4fa;border-bottom:1px solid #d6dcea;text-align:center}

/* Items table */
.table-container{margin:0 8px;border:1px solid #d6dcea;border-radius:10px;
  background:#fff;overflow:hidden;box-shadow:0 3px 10px rgba(32,55,92,.05)}
table.items{width:100%;border-collapse:collapse;font-size:11px}
table.items thead th{background:linear-gradient(180deg,#364891,#2d3d7b);color:#f7f9ff;
  padding:5px 5px;border:1px solid #42529e;font-weight:600;font-size:10px;
  text-align:center;white-space:nowrap;text-transform:uppercase;letter-spacing:.4px}
table.items tbody td{border:1px solid #edf1f7;padding:1px 2px;text-align:center;height:24px}
table.items tbody tr:nth-child(odd){background:#fff}
table.items tbody tr:nth-child(even){background:#f7faff}
table.items tbody tr:hover{background:#edf3ff}
table.items tbody tr.sel td{background:#dbeafe}
table.items tbody input{font-size:11px;border:none;background:transparent;
  text-align:center;width:100%;height:100%}
table.items tbody input:focus{background:#fffff0;outline:2px solid #4299e1;border-radius:2px}
table.items tbody input.num{text-align:right}
table.items tfoot td{background:#f0f4fa;font-weight:700;font-size:11px;padding:4px 5px;
  border:1px solid #d6dcea;text-align:center}

/* Table footer buttons */
.table-footer{display:flex;align-items:center;background:#f9fbff;
  padding:5px 10px;gap:8px;font-size:11px;font-weight:600;
  color:#2d3748;border-top:1px solid #d6dcea}
.table-footer button{background:#fff;border:1px solid #d0d9ea;border-radius:8px;
  padding:2px 12px;font-size:11px;cursor:pointer;font-weight:600;color:#3f4a5b}
.table-footer button:hover{background:#eef3ff;border-color:#bfcaf0}
.table-footer button:active{transform:translateY(1px)}
.stktype-badge{font-size:10px;background:#f59e0b;color:#fff;padding:1px 8px;border-radius:4px;font-weight:700}

/* Bottom buttons */
.bottom-bar{display:flex;align-items:center;justify-content:center;gap:8px;
  padding:8px 14px;background:#f9fbff;border-top:1px solid #d6dcea}
.bottom-bar button{padding:6px 22px;font-size:12px;font-weight:700;border-radius:8px;
  cursor:pointer;border:1px solid #d6dcea;background:#fff;color:#374151;
  transition:all .15s}
.bottom-bar button:hover{background:#eef3ff;border-color:#bfcaf0}
.bottom-bar button.primary{background:linear-gradient(135deg,#2563eb,#1d4ed8);
  color:#fff;border-color:#1d4ed8}
.bottom-bar button.primary:hover{background:linear-gradient(135deg,#1d4ed8,#1e40af)}
.bottom-bar button.danger{background:#ef4444;color:#fff;border-color:#dc2626}
.bottom-bar button.danger:hover{background:#dc2626}

/* Modals */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;
  align-items:center;justify-content:center}
.modal-bg.show{display:flex}
.modal{background:#fff;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.25);
  width:90%;max-width:620px;max-height:80vh;overflow:hidden;display:flex;flex-direction:column}
.modal-head{background:linear-gradient(135deg,#1a3a5f,#2c6282);color:#fff;padding:10px 14px;
  font-weight:700;font-size:13px;display:flex;justify-content:space-between;align-items:center}
.modal-head .close-btn{background:none;border:none;color:#fff;font-size:18px;cursor:pointer;line-height:1}
.modal-body{padding:10px;overflow-y:auto;flex:1}
.modal-body input{width:100%;padding:6px 10px;font-size:12px;border:1px solid #d6dcea;
  border-radius:7px;margin-bottom:8px}
.modal-body table{width:100%;border-collapse:collapse;font-size:11px}
.modal-body table th{background:#364891;color:#fff;padding:5px;font-weight:600;text-align:left;
  position:sticky;top:0}
.modal-body table td{padding:4px 6px;border-bottom:1px solid #edf1f7;cursor:pointer}
.modal-body table tr:hover td{background:#edf3ff}

/* Status strip */
.status-strip{display:flex;align-items:center;gap:12px;padding:4px 14px;
  font-size:10px;color:#64748b;background:#f0f4fa;border-top:1px solid #d6dcea}
.status-strip .dot{width:6px;height:6px;border-radius:50%;background:#10b981}
</style>
</head>
<body>

<div class="main-window" id="mainWindow">
  <!-- Title -->
  <div class="title-bar">
    <div class="icon"></div>
    <span id="titleText">Order Advance {{ $mode === 'edit' ? '- Edit' : '' }}</span>
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
        <label>Amount :</label>
        <input type="text" id="fAmount" class="md num" style="text-align:right" value="0.00">
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
    const today = new Date();
    const dd = String(today.getDate()).padStart(2,'0');
    const mm = String(today.getMonth()+1).padStart(2,'0');
    const yyyy = today.getFullYear();
    sv('fDate', dd+'/'+mm+'/'+yyyy);

    addItemRow();

    if(CFG.mode === 'edit') {
        $('titleText').textContent = 'Order Advance - Edit';
    }
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

    status('Saving...');
    try {
        const body = {
            ordno:     ordno,
            date:      gv('fDate'),
            rate:      gf('fGoldRate'),
            amount:    amount,
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
            alert('Advance saved successfully.\nVoucher: ' + (d.vchno || ''));
        } else {
            alert(d.message || 'Save failed');
            status('Save failed');
        }
    } catch(e) {
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

            $('titleText').textContent = 'Order Advance - Edit [' + (d.docno || '') + ']';
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

    $('titleText').textContent = 'Order Advance';
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
    if(e.key === 'Enter') { e.preventDefault(); doSave(); }
});

$('btnHelp').addEventListener('click', () => { openModal('modalOrder'); $('orderSearchInput').focus(); searchOrders(''); });
$('btnSave').addEventListener('click', doSave);
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
