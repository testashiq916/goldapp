<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Debit / Credit Note</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:13px;background:#f3f4f6;height:100vh;display:flex;flex-direction:column;overflow:hidden;}
.wrap{display:flex;flex-direction:column;height:100vh;overflow:hidden;}

/* Header */
.hdr{background:#fff;border-bottom:1px solid #e5e7eb;padding:7px 14px;display:flex;align-items:center;gap:10px;flex-shrink:0;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.hdr-icon{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,#C9A84C,#9A7A32);display:flex;align-items:center;justify-content:center;}
.hdr-icon i{color:#fff;font-size:14px;}
.hdr-title{font-weight:700;font-size:14px;color:#1f2937;flex:1;}
.vch-badge{font-size:11px;font-weight:700;background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;border-radius:5px;padding:2px 9px;}

/* Toolbar */
.toolbar{background:#fff;border-bottom:1px solid #e5e7eb;padding:5px 14px;display:flex;gap:6px;align-items:center;flex-shrink:0;}
.btn{padding:5px 14px;border:none;border-radius:6px;font-size:12.5px;font-weight:600;font-family:inherit;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:opacity .15s,transform .1s;}
.btn:active{transform:scale(.97);}
.btn-save{background:#C9A84C;color:#fff;}
.btn-save:hover{background:#9A7A32;}
.btn-cancel{background:#6b7280;color:#fff;}
.btn-cancel:hover{background:#4b5563;}
.btn-exit{background:#dc2626;color:#fff;}
.btn-exit:hover{background:#b91c1c;}
.chk-row{margin-left:auto;display:flex;gap:14px;align-items:center;}
.chk-row label{display:flex;align-items:center;gap:5px;font-size:12px;color:#374151;cursor:pointer;}

/* Body */
.body{flex:1;overflow-y:auto;padding:14px;}
.section{background:#fff;border-radius:10px;border:1px solid #e5e7eb;padding:14px 16px;margin-bottom:12px;}

/* Form grid */
.fg{display:flex;flex-wrap:wrap;gap:8px 12px;align-items:flex-end;}
.fg+.fg{margin-top:8px;}
.fg label{font-size:11.5px;font-weight:600;color:#374151;white-space:nowrap;}
.fgroup{display:flex;flex-direction:column;gap:3px;}

/* Inputs */
.inp{padding:5px 9px;border:1.5px solid #d1d5db;border-radius:6px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s,box-shadow .15s;}
.inp:focus{border-color:#C9A84C;box-shadow:0 0 0 2px rgba(201,168,76,.15);}
.inp[readonly]{background:#f9fafb;color:#374151;}
.inp-code{width:110px;text-transform:uppercase;}
.inp-date{width:120px;}
.inp-num{width:130px;text-align:right;}
.inp-total{width:140px;text-align:right;font-weight:700;color:#0369a1;background:#f0f9ff;}
.inp-part{width:400px;}
.inp-desc{min-width:200px;max-width:340px;flex:1;padding:5px 9px;border:1.5px solid #e5e7eb;border-radius:6px;font-size:12.5px;background:#f9fafb;color:#1f2937;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.inp-bal{width:120px;text-align:right;font-weight:700;background:#fdf2f8;border-color:#f3e8ff;}
.lbl-dir{font-size:11.5px;font-weight:700;color:#0369a1;white-space:nowrap;min-width:80px;}

/* Select */
.sel{padding:5px 9px;border:1.5px solid #d1d5db;border-radius:6px;font-size:13px;font-family:inherit;outline:none;background:#fff;min-width:150px;}
.sel:focus{border-color:#C9A84C;box-shadow:0 0 0 2px rgba(201,168,76,.15);}

/* Search button */
.ac-srch{padding:5px 9px;border:1.5px solid #e5e7eb;border-left:none;border-radius:0 6px 6px 0;background:#f3f4f6;cursor:pointer;color:#6b7280;display:flex;align-items:center;font-size:12px;}
.ac-srch:hover{background:#e5e7eb;}
.inp-code{border-radius:6px 0 0 6px;}

/* Section label */
.sec-lbl{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px;}

/* Divider */
.divider{border:none;border-top:1px solid #f3f4f6;margin:10px 0;}

/* Toast */
.toast{position:fixed;bottom:18px;right:18px;background:#111;color:#fff;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:500;box-shadow:0 4px 16px rgba(0,0,0,.25);transform:translateY(60px);opacity:0;transition:transform .3s,opacity .3s;z-index:9999;max-width:340px;display:flex;align-items:center;gap:8px;}
.toast.show{transform:translateY(0);opacity:1;}

/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:8000;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal-card{background:#fff;border-radius:12px;width:500px;max-height:70vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.2);}
.modal-hdr{padding:12px 16px;border-bottom:1px solid #e5e7eb;font-weight:700;font-size:14px;display:flex;justify-content:space-between;align-items:center;}
.modal-hdr button{background:none;border:none;font-size:18px;cursor:pointer;color:#6b7280;line-height:1;}
.modal-body{padding:10px 14px;display:flex;flex-direction:column;gap:6px;flex:1;overflow:hidden;}
.modal-list{list-style:none;overflow-y:auto;flex:1;border:1px solid #e5e7eb;border-radius:6px;}
.modal-list li{padding:7px 12px;cursor:pointer;border-bottom:1px solid #f3f4f6;font-size:12.5px;display:flex;gap:12px;}
.modal-list li:hover,.modal-list li.active{background:#fef9ee;}
.modal-list li .m-code{font-weight:700;color:#C9A84C;width:90px;flex-shrink:0;}
.modal-list li .m-name{color:#374151;}
</style>
</head>
<body>
<div class="wrap">

  <!-- Header -->
  <div class="hdr">
    <div class="hdr-icon"><i class="fa fa-file-invoice-dollar"></i></div>
    <span class="hdr-title">Debit / Credit Note</span>
    <span class="vch-badge" id="vchno">---</span>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <button class="btn btn-save"   onclick="doSave()"  title="Save (F9)"><i class="fa fa-save"></i> Save F9</button>
    <button class="btn btn-cancel" onclick="doClear()" title="Cancel"><i class="fa fa-undo"></i> Cancel</button>
    <button class="btn btn-exit"   onclick="doExit()"  title="Exit (Esc)"><i class="fa fa-times"></i> Exit Esc</button>
    <div class="chk-row">
      <label><input type="checkbox" id="chkPrintSlip"> Print Slip</label>
      <label><input type="checkbox" id="chkPrintOBCB" checked> Print OB/CB</label>
    </div>
  </div>

  <!-- Body -->
  <div class="body">

    <!-- Section 1: Doc type + Date -->
    <div class="section">
      <div class="sec-lbl">Document</div>
      <div class="fg">
        <div class="fgroup">
          <label>Doc No.</label>
          <span id="vchnoDisplay" style="font-size:13px;font-weight:700;color:#0369a1;padding:5px 10px;background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:6px;min-width:120px;text-align:center;">---</span>
        </div>
        <div class="fgroup">
          <label>Type</label>
          <select class="sel" id="selType" onchange="onTypeChange()">
            <option value="C">Credit Note</option>
            <option value="D">Debit Note</option>
          </select>
        </div>
        <div class="fgroup">
          <label>Date</label>
          <input type="text" id="inDate" class="inp inp-date" placeholder="dd/mm/yyyy" maxlength="10">
        </div>
      </div>
    </div>

    <!-- Section 2: Accounts -->
    <div class="section">
      <div class="sec-lbl">Accounts</div>

      <!-- Adjusted Account -->
      <div class="fg" style="margin-bottom:10px;">
        <div class="fgroup">
          <label>Adjusted Account</label>
          <div style="display:flex;">
            <input type="text" id="inAdjac" class="inp inp-code" placeholder="Code"
              onkeydown="onAcKeydown(event,'adj')" style="border-radius:6px 0 0 6px;">
            <button class="ac-srch" onclick="openSearch('adj')" title="F1 – Search">
              <i class="fa fa-search"></i>
            </button>
          </div>
        </div>
        <div class="fgroup" style="flex:1;">
          <label>&nbsp;</label>
          <div class="inp-desc" id="adjacDesc" style="min-width:220px;">&nbsp;</div>
        </div>
      </div>

      <!-- Salesman -->
      <div class="fg" style="margin-bottom:10px;">
        <div class="fgroup">
          <label>Salesman</label>
          <select class="sel" id="selStaff">
            <option value="">-- None --</option>
          </select>
        </div>
      </div>

      <hr class="divider">

      <!-- Main Account (Credited/Debited) -->
      <div class="fg">
        <div class="fgroup">
          <label id="lblAcCode">Credited Account</label>
          <div style="display:flex;">
            <input type="text" id="inAcCode" class="inp inp-code" placeholder="Code"
              onkeydown="onAcKeydown(event,'main')" style="border-radius:6px 0 0 6px;">
            <button class="ac-srch" onclick="openSearch('main')" title="F1 – Search">
              <i class="fa fa-search"></i>
            </button>
          </div>
        </div>
        <div class="fgroup" style="flex:1;">
          <label>&nbsp;</label>
          <div class="inp-desc" id="acDesc" style="min-width:220px;">&nbsp;</div>
        </div>
        <div class="fgroup">
          <label>Balance</label>
          <input type="text" id="inBalance" class="inp inp-bal" readonly placeholder="0.00">
        </div>
        <div class="fgroup" style="align-self:flex-end;padding-bottom:5px;">
          <span class="lbl-dir" id="lblDir"></span>
        </div>
      </div>
    </div>

    <!-- Section 3: Amount + Tax -->
    <div class="section">
      <div class="sec-lbl">Amount</div>
      <div class="fg">
        <div class="fgroup">
          <label>Amount</label>
          <input type="text" id="inAmount" class="inp inp-num" placeholder="0.00"
            oninput="recalcTax()" onchange="recalcTax()" onfocus="this.select()">
        </div>
        <div class="fgroup">
          <label>Tax %</label>
          <input type="text" id="inTaxPerc" class="inp" style="width:80px;text-align:right;" placeholder="0.00"
            oninput="recalcTax()" onchange="recalcTax()" onfocus="this.select()">
        </div>
        <div class="fgroup">
          <label>Tax Amt</label>
          <input type="text" id="inTaxAmt" class="inp inp-num" placeholder="0.00"
            oninput="onTaxAmtChange()" onchange="onTaxAmtChange()" onfocus="this.select()">
        </div>
        <div class="fgroup">
          <label>Total</label>
          <input type="text" id="inTotal" class="inp inp-total" placeholder="0.00" readonly>
        </div>
      </div>
    </div>

    <!-- Section 4: Description -->
    <div class="section">
      <div class="sec-lbl">Description</div>
      <div class="fg">
        <div class="fgroup" style="flex:1;">
          <label>Particulars</label>
          <input type="text" id="inPart" class="inp inp-part" maxlength="70"
            placeholder="Leave blank to auto-generate" style="width:100%;text-transform:uppercase;">
        </div>
      </div>
    </div>

  </div><!-- /body -->
</div><!-- /wrap -->

<!-- Account Search Modal -->
<div class="modal-bg" id="modalBg" onclick="closeSearch()">
  <div class="modal-card" onclick="event.stopPropagation()">
    <div class="modal-hdr">
      <span id="modalTitle">Account Search</span>
      <button onclick="closeSearch()">&times;</button>
    </div>
    <div class="modal-body">
      <input type="text" id="modalQ" class="inp" placeholder="Search by code or name…"
        oninput="doModalSearch()" onkeydown="modalKeydown(event)" style="width:100%;">
      <ul class="modal-list" id="modalList"></ul>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
const TOKEN   = '{{ csrf_token() }}';
const API     = '{{ url("/api/accounts/debit-credit-note") }}';

let vchnoCN   = '';
let vchnoDN   = '';
let todayVal  = '';
let srchTarget = 'adj'; // 'adj' | 'main'
let modalIdx  = -1;

// ── Init ──────────────────────────────────────────────────────────────────
async function init() {
    const d = await post({ action: 'init' });
    if (!d.ok) { showToast('error', d.msg || 'Init failed'); return; }

    vchnoCN  = d.vchnoCN;
    vchnoDN  = d.vchnoDN;
    todayVal = d.today;

    document.getElementById('inDate').value = d.today;
    updateVchno();

    const sel = document.getElementById('selStaff');
    sel.innerHTML = '<option value="">-- None --</option>';
    (d.salesmen || []).forEach(s => {
        const opt = document.createElement('option');
        opt.value       = s.code;
        opt.textContent = s.code + '  ' + s.name;
        sel.appendChild(opt);
    });
}

// ── Type + Vchno ──────────────────────────────────────────────────────────
function onTypeChange() {
    updateVchno();
    const type = document.getElementById('selType').value;
    document.getElementById('lblAcCode').textContent =
        (type === 'D') ? 'Debited Account' : 'Credited Account';
}

function updateVchno() {
    const type = document.getElementById('selType').value;
    const v    = (type === 'D') ? vchnoDN : vchnoCN;
    document.getElementById('vchno').textContent        = v || '---';
    document.getElementById('vchnoDisplay').textContent = v || '---';
}

// ── Account loading ───────────────────────────────────────────────────────
async function loadAccount(target) {
    const inp  = document.getElementById(target === 'adj' ? 'inAdjac' : 'inAcCode');
    const code = inp.value.trim().toUpperCase();
    if (!code) return;
    inp.value = code;

    const d = await post({ action: 'load_account', code });
    if (!d.ok) {
        showToast('error', d.msg);
        inp.value = '';
        if (target === 'adj') {
            document.getElementById('adjacDesc').innerHTML = '&nbsp;';
        } else {
            document.getElementById('acDesc').innerHTML    = '&nbsp;';
            document.getElementById('inBalance').value    = '';
            document.getElementById('lblDir').textContent = '';
        }
        return;
    }

    if (target === 'adj') {
        document.getElementById('adjacDesc').textContent = d.name;
    } else {
        document.getElementById('acDesc').textContent    = d.name;
        document.getElementById('inBalance').value       = fmt2(d.balance);
        document.getElementById('lblDir').textContent    = d.label ? '(' + d.label + ')' : '';
    }
}

function onAcKeydown(e, target) {
    if (e.key === 'Enter') {
        e.preventDefault();
        loadAccount(target);
    } else if (e.key === 'F1') {
        e.preventDefault();
        openSearch(target);
    }
}

// ── Tax calculation ───────────────────────────────────────────────────────
function recalcTax() {
    const amt     = parseFloat(document.getElementById('inAmount').value)  || 0;
    const perc    = parseFloat(document.getElementById('inTaxPerc').value) || 0;
    const taxAmt  = Math.round(amt * perc / 100 * 100) / 100;
    document.getElementById('inTaxAmt').value = taxAmt.toFixed(2);
    document.getElementById('inTotal').value  = (amt + taxAmt).toFixed(2);
}

function onTaxAmtChange() {
    const amt    = parseFloat(document.getElementById('inAmount').value)  || 0;
    const taxAmt = parseFloat(document.getElementById('inTaxAmt').value)  || 0;
    document.getElementById('inTotal').value = (amt + taxAmt).toFixed(2);
}

// ── Save ──────────────────────────────────────────────────────────────────
async function doSave() {
    const tdate   = document.getElementById('inDate').value.trim();
    const type    = document.getElementById('selType').value;
    const adjac   = document.getElementById('inAdjac').value.trim().toUpperCase();
    const accode  = document.getElementById('inAcCode').value.trim().toUpperCase();
    const staff   = document.getElementById('selStaff').value;
    const amount  = parseFloat(document.getElementById('inAmount').value)  || 0;
    const taxperc = parseFloat(document.getElementById('inTaxPerc').value) || 0;
    const taxamt  = parseFloat(document.getElementById('inTaxAmt').value)  || 0;
    const part    = document.getElementById('inPart').value.trim();

    if (!tdate)   { showToast('error', 'Enter date');                    return; }
    if (!adjac)   { showToast('error', 'Enter adjusted account');        return; }
    if (!accode)  { showToast('error', 'Enter credited/debited account'); return; }
    if (amount <= 0) { showToast('error', 'Enter amount > 0');           return; }

    const d = await post({ action: 'save', tdate, type, adjac, accode, staff, amount, taxperc, taxamt, part });
    if (!d.ok) { showToast('error', d.msg); return; }

    showToast('success', 'Saved: ' + d.vchno);
    doClear();
}

// ── Clear ─────────────────────────────────────────────────────────────────
function doClear() {
    document.getElementById('selType').value    = 'C';
    onTypeChange();
    document.getElementById('inDate').value     = todayVal;
    document.getElementById('inAdjac').value    = '';
    document.getElementById('adjacDesc').innerHTML = '&nbsp;';
    document.getElementById('selStaff').value   = '';
    document.getElementById('inAcCode').value   = '';
    document.getElementById('acDesc').innerHTML    = '&nbsp;';
    document.getElementById('inBalance').value  = '';
    document.getElementById('lblDir').textContent  = '';
    document.getElementById('inAmount').value   = '';
    document.getElementById('inTaxPerc').value  = '';
    document.getElementById('inTaxAmt').value   = '';
    document.getElementById('inTotal').value    = '';
    document.getElementById('inPart').value     = '';
    // Refresh vchno preview from server
    init();
}

// ── Exit ──────────────────────────────────────────────────────────────────
function doExit() {
    if (window.top && typeof window.top.closeCurrentModule === 'function') {
        window.top.closeCurrentModule();
    } else {
        window.close();
    }
}

// ── Account Search Modal ──────────────────────────────────────────────────
function openSearch(target) {
    srchTarget = target;
    modalIdx   = -1;
    document.getElementById('modalTitle').textContent =
        (target === 'adj') ? 'Adjusted Account Search' : 'Account Search';
    document.getElementById('modalQ').value     = '';
    document.getElementById('modalList').innerHTML = '';
    document.getElementById('modalBg').classList.add('open');
    document.getElementById('modalQ').focus();
}

function closeSearch() {
    document.getElementById('modalBg').classList.remove('open');
}

async function doModalSearch() {
    const q = document.getElementById('modalQ').value.trim();
    const list = document.getElementById('modalList');
    list.innerHTML = '';
    modalIdx = -1;
    if (!q) return;

    const d = await post({ action: 'account_search', q });
    (d.data || []).forEach((item, i) => {
        const li = document.createElement('li');
        li.dataset.i    = i;
        li.dataset.code = item.code;
        li.dataset.name = item.name;
        li.innerHTML    = `<span class="m-code">${item.code}</span><span class="m-name">${item.name}</span>`;
        li.addEventListener('dblclick', () => selectSearchItem(item));
        li.addEventListener('click',    () => { highlightModal(list.querySelectorAll('li[data-i]'), i); });
        list.appendChild(li);
    });
}

function selectSearchItem(item) {
    if (srchTarget === 'adj') {
        document.getElementById('inAdjac').value = item.code;
        document.getElementById('adjacDesc').textContent = item.name;
        loadAccount('adj');
    } else {
        document.getElementById('inAcCode').value = item.code;
        document.getElementById('acDesc').textContent = item.name;
        loadAccount('main');
    }
    closeSearch();
}

function modalKeydown(e) {
    const items = document.querySelectorAll('#modalList li[data-i]');
    if (!items.length) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); modalIdx = Math.min(modalIdx + 1, items.length - 1); highlightModal(items); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); modalIdx = Math.max(modalIdx - 1, 0); highlightModal(items); }
    else if (e.key === 'Enter' && modalIdx >= 0) {
        const el = items[modalIdx];
        selectSearchItem({ code: el.dataset.code, name: el.dataset.name });
    } else if (e.key === 'Escape') { closeSearch(); }
}

function highlightModal(items, forcedIdx) {
    if (forcedIdx !== undefined) modalIdx = forcedIdx;
    items.forEach((el, i) => el.classList.toggle('active', i === modalIdx));
    if (items[modalIdx]) items[modalIdx].scrollIntoView({ block: 'nearest' });
}

// ── Keyboard shortcuts ────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'F9') { e.preventDefault(); doSave(); }
    if (e.key === 'Escape' && !document.getElementById('modalBg').classList.contains('open')) {
        doExit();
    }
});

// ── API helper ────────────────────────────────────────────────────────────
async function post(data) {
    const fd = new FormData();
    fd.append('_token', TOKEN);
    for (const [k, v] of Object.entries(data)) {
        if (Array.isArray(v) || (typeof v === 'object' && v !== null)) {
            fd.append(k, JSON.stringify(v));
        } else {
            fd.append(k, v ?? '');
        }
    }
    const r = await fetch(API, { method: 'POST', body: fd });
    return r.json();
}

// ── Formatting ────────────────────────────────────────────────────────────
function fmt2(n) { return parseFloat(n || 0).toFixed(2); }

// ── Boot ──────────────────────────────────────────────────────────────────
function showToast(type, msg) {
    const t = document.getElementById('toast');
    t.textContent = (type === 'error' ? '✕ ' : '✓ ') + msg;
    t.style.background = (type === 'error') ? '#dc2626' : '#16a34a';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3500);
}

window.addEventListener('load', init);
</script>
</body>
</html>
