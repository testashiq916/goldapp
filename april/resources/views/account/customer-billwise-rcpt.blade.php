<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Customer Billwise Receipt</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f4f6f9;color:#1a1a1a;font-size:13px;min-height:100vh;display:flex;flex-direction:column;}

/* ── Header ─────────────────────────────────────── */
.hdr{background:#fff;border-bottom:1px solid #e5e7eb;padding:7px 14px;display:flex;align-items:center;gap:10px;flex-shrink:0;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.hdr-icon{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,#C9A84C,#9A7A32);display:flex;align-items:center;justify-content:center;}
.hdr-icon svg{width:15px;height:15px;}
.hdr-title{font-size:14px;font-weight:700;color:#111;}
.vch-badge{margin-left:auto;background:#EEF2FF;color:#3B3FCB;border:1px solid #C7D2FE;border-radius:6px;padding:3px 10px;font-size:11.5px;font-weight:700;letter-spacing:.5px;}

/* ── Toolbar ─────────────────────────────────────── */
.toolbar{background:#fff;border-bottom:1px solid #e5e7eb;padding:5px 14px;display:flex;gap:6px;align-items:center;flex-shrink:0;}
.btn{padding:5px 14px;border:none;border-radius:6px;font-size:12.5px;font-weight:600;font-family:inherit;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:opacity .15s,transform .1s;}
.btn:active{transform:scale(.97);}
.btn-save{background:linear-gradient(135deg,#C9A84C,#9A7A32);color:#fff;box-shadow:0 2px 6px rgba(154,122,50,.3);}
.btn-save:hover{opacity:.9;}
.btn-clear{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;}
.btn-clear:hover{background:#e9eaec;}
.btn-exit{background:#fee2e2;color:#B91C1C;border:1px solid #fca5a5;}
.btn-exit:hover{background:#fecaca;}
.btn-filter{background:#EEF2FF;color:#3B3FCB;border:1px solid #C7D2FE;padding:4px 10px;}

/* ── Body scroll ─────────────────────────────────── */
.body{flex:1;overflow-y:auto;padding:10px 14px;display:flex;flex-direction:column;gap:8px;}

/* ── Cards ───────────────────────────────────────── */
.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;}
.card-body{padding:10px 14px;}

/* ── Form grid ───────────────────────────────────── */
.fg{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:flex-end;}
.fi{display:flex;flex-direction:column;gap:3px;}
.fi label{font-size:11.5px;font-weight:600;color:#4b5563;}
.fi input,.fi select{border:1.5px solid #e5e7eb;border-radius:6px;padding:5px 8px;font-size:13px;font-family:inherit;background:#fafafa;color:#111;outline:none;transition:border-color .15s,box-shadow .15s;}
.fi input:focus,.fi select:focus{border-color:#C9A84C;box-shadow:0 0 0 2px rgba(201,168,76,.15);background:#fff;}
.fi input[readonly]{background:#f3f4f6;cursor:default;}
.fi-w60{width:60px;}
.fi-w90{width:90px;}
.fi-w110{width:110px;}
.fi-w140{width:140px;}
.fi-w180{width:180px;}
.fi-w220{width:220px;}
.fi-w300{width:300px;}
.fi-grow{flex:1;min-width:160px;}

.cust-wrap{position:relative;display:flex;}
.cust-wrap input{border-radius:6px 0 0 6px!important;flex:1;}
.cust-srch{padding:5px 9px;border:1.5px solid #e5e7eb;border-left:none;border-radius:0 6px 6px 0;background:#f3f4f6;cursor:pointer;color:#6b7280;display:flex;align-items:center;}
.cust-srch:hover{background:#e5e7eb;color:#374151;}

.info-lbl{font-size:11.5px;font-weight:700;color:#9A7A32;background:#fffdf0;border:1px solid #f0e0a0;border-radius:6px;padding:5px 10px;min-width:140px;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.bal-lbl{font-size:12px;font-weight:700;color:#1d4ed8;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:6px;padding:5px 12px;min-width:100px;text-align:right;}

/* ── Bills table ─────────────────────────────────── */
.tbl-wrap{overflow-x:auto;border-radius:8px;border:1px solid #e5e7eb;}
table{width:100%;border-collapse:collapse;font-size:12px;}
thead th{background:#f9fafb;border-bottom:2px solid #e5e7eb;padding:6px 8px;font-weight:700;color:#374151;white-space:nowrap;text-align:center;}
thead th.l{text-align:left;}
tbody tr:nth-child(even){background:#fafbfc;}
tbody tr:hover{background:#fffdf0;}
tbody td{padding:4px 6px;border-bottom:1px solid #f3f4f6;vertical-align:middle;white-space:nowrap;}
tbody td.r{text-align:right;}
tbody td.c{text-align:center;}
.tbl-input{border:1px solid #e5e7eb;border-radius:4px;padding:3px 5px;font-size:12px;font-family:inherit;background:#fff;width:90px;text-align:right;outline:none;}
.tbl-input:focus{border-color:#C9A84C;box-shadow:0 0 0 2px rgba(201,168,76,.12);}
.tbl-date{width:88px;}
.tbl-chk{width:16px;height:16px;accent-color:#9A7A32;cursor:pointer;}
.tag-neg{color:#B91C1C;font-weight:600;}
.tag-zero{color:#9ca3af;}

/* ── Footer strip ────────────────────────────────── */
.ftr-strip{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:8px 14px;}
.ftr-row{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;}
.ftr-stat{display:flex;flex-direction:column;gap:2px;}
.ftr-stat label{font-size:11px;font-weight:600;color:#6b7280;}
.ftr-val{font-size:13px;font-weight:700;color:#111;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:5px;padding:3px 10px;min-width:90px;text-align:right;}
.ftr-val.red{color:#B91C1C;background:#FEF2F2;}
.cbx-row{display:flex;gap:14px;align-items:center;margin-top:4px;}
.cbx-row label{display:flex;align-items:center;gap:5px;font-size:12px;color:#374151;cursor:pointer;}
.cbx-row input[type=checkbox]{accent-color:#9A7A32;width:14px;height:14px;}

/* ── Toast ───────────────────────────────────────── */
.toast{position:fixed;bottom:18px;right:18px;background:#111;color:#fff;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:500;box-shadow:0 4px 16px rgba(0,0,0,.25);transform:translateY(60px);opacity:0;transition:transform .3s,opacity .3s;z-index:9999;max-width:320px;display:flex;align-items:center;gap:8px;}
.toast.show{transform:translateY(0);opacity:1;}
.toast.success .ti{color:#4ade80;}
.toast.error   .ti{color:#f87171;}

/* ── Search modal ────────────────────────────────── */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:8000;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal{background:#fff;border-radius:12px;width:480px;max-width:96vw;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.modal-hdr{padding:12px 16px;border-bottom:1px solid #e5e7eb;font-weight:700;font-size:14px;display:flex;justify-content:space-between;align-items:center;}
.modal-body{padding:12px 16px;flex:1;overflow-y:auto;}
.modal-close{background:none;border:none;font-size:18px;cursor:pointer;color:#6b7280;line-height:1;}
.modal-close:hover{color:#111;}
.srch-input{width:100%;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:13px;font-family:inherit;outline:none;margin-bottom:8px;}
.srch-input:focus{border-color:#C9A84C;box-shadow:0 0 0 2px rgba(201,168,76,.12);}
.srch-list{list-style:none;}
.srch-list li{padding:7px 10px;cursor:pointer;border-radius:5px;display:flex;gap:10px;}
.srch-list li:hover,.srch-list li.active{background:#fffdf0;}
.srch-code{font-weight:700;min-width:70px;color:#9A7A32;}
.srch-name{color:#374151;}
.srch-empty{color:#9ca3af;font-size:12.5px;padding:8px 0;}
</style>
</head>
<body>

<!-- Header -->
<div class="hdr">
    <div class="hdr-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="5" width="20" height="14" rx="2"/>
            <line x1="2" y1="10" x2="22" y2="10"/>
        </svg>
    </div>
    <div class="hdr-title">Customer Billwise Receipt</div>
    <div class="vch-badge" id="vchBadge">VRB/-----</div>
</div>

<!-- Toolbar -->
<div class="toolbar">
    <button class="btn btn-save"  onclick="doSave()"  title="Save (F9)">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Save (F9)
    </button>
    <button class="btn btn-clear" onclick="doClear()" title="Clear">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
        Clear
    </button>
    <button class="btn btn-exit"  onclick="doExit()"  title="Exit (Esc)">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Exit (Esc)
    </button>
</div>

<!-- Body -->
<div class="body">

    <!-- Row 1: Date / GRate / Salesman -->
    <div class="card">
        <div class="card-body">
            <div class="fg">
                <div class="fi fi-w110">
                    <label>Date</label>
                    <input type="text" id="inDate" placeholder="dd/mm/yyyy" maxlength="10">
                </div>
                <div class="fi fi-w110">
                    <label>Gold Rate</label>
                    <input type="number" id="inGrate" placeholder="0.00" step="0.01" min="0">
                </div>
                <div class="fi fi-w180">
                    <label>Salesman</label>
                    <select id="selSman"><option value="">-- None --</option></select>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Customer -->
    <div class="card">
        <div class="card-body">
            <div class="fg" style="align-items:center;">
                <div class="fi fi-w110">
                    <label>Customer Code</label>
                    <div class="cust-wrap">
                        <input type="text" id="inCust" placeholder="Code" style="text-transform:uppercase;"
                               onkeydown="onCustKey(event)">
                        <button class="cust-srch" onclick="openSearchModal()" title="Search (F1)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </button>
                    </div>
                </div>
                <div class="fi" style="flex:1">
                    <label>Name</label>
                    <div class="info-lbl" id="custName">—</div>
                </div>
                <div class="fi">
                    <label>Balance</label>
                    <div class="bal-lbl" id="custBal">—</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / pending row -->
    <div class="ftr-row" style="align-items:center;gap:10px;">
        <div class="cbx-row" style="margin-top:0">
            <label><input type="checkbox" id="chkPending" checked onchange="applyFilter()"> Only Pending Bills</label>
        </div>
        <div style="display:flex;gap:4px;align-items:center;margin-left:auto;">
            <input type="text" id="inFilter" placeholder="Filter name…"
                   style="border:1.5px solid #e5e7eb;border-radius:6px;padding:4px 8px;font-size:12px;outline:none;width:150px;"
                   onkeydown="if(event.key==='Enter')applyNameFilter()">
            <button class="btn btn-filter" onclick="applyNameFilter()">Filter</button>
            <button class="btn btn-clear" style="padding:4px 10px;" onclick="clearFilter()">✕</button>
        </div>
    </div>

    <!-- Bills table -->
    <div class="tbl-wrap" id="billsWrap">
        <table id="billsTable">
            <thead>
                <tr>
                    <th class="l">Date</th>
                    <th class="l">Bill No.</th>
                    <th style="text-align:right">Net Bill Amt</th>
                    <th style="text-align:right">Gold Rate</th>
                    <th style="text-align:right">Received</th>
                    <th style="text-align:right">Balance</th>
                    <th>Select</th>
                    <th style="text-align:right">Alloc Amt</th>
                    <th style="text-align:right">Discount</th>
                    <th style="text-align:right">Net Bal</th>
                    <th style="text-align:right">Bal Wgt</th>
                    <th>Due Date</th>
                </tr>
            </thead>
            <tbody id="billsTbody">
                <tr><td colspan="12" style="text-align:center;padding:20px;color:#9ca3af;">
                    Enter a customer code to load bills.
                </td></tr>
            </tbody>
            <tfoot id="billsTfoot" style="display:none">
                <tr style="background:#f9fafb;font-weight:700;font-size:12px;">
                    <td colspan="2" style="padding:5px 8px;color:#374151;text-align:right">Total:</td>
                    <td class="r" id="ftNetAmt" style="padding:5px 8px;"></td>
                    <td></td>
                    <td class="r" id="ftRcvd" style="padding:5px 8px;"></td>
                    <td class="r" id="ftBal" style="padding:5px 8px;"></td>
                    <td></td>
                    <td class="r" id="ftAloc" style="padding:5px 8px;"></td>
                    <td class="r" id="ftDisc" style="padding:5px 8px;"></td>
                    <td class="r" id="ftNetBal" style="padding:5px 8px;"></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Footer strip -->
    <div class="ftr-strip">
        <div class="ftr-row">
            <div class="ftr-stat">
                <label>Total Amount to Allocate</label>
                <input type="number" id="inTotalAlloc" class="ftr-val" style="border:1.5px solid #e5e7eb;border-radius:5px;outline:none;font-size:13px;font-weight:700;padding:3px 10px;width:120px;text-align:right;"
                       placeholder="0.00" step="0.01" min="0"
                       oninput="onTotalAllocChange()">
            </div>
            <div class="ftr-stat">
                <label>Non-Allocated Amt</label>
                <div class="ftr-val" id="ftrNonAloc" style="min-width:100px;">0.00</div>
            </div>
            <div class="ftr-stat fi-grow">
                <label>Particulars</label>
                <input type="text" id="inPart" placeholder="Leave blank for auto" maxlength="40"
                       style="border:1.5px solid #e5e7eb;border-radius:6px;padding:5px 8px;font-size:13px;font-family:inherit;outline:none;width:100%;"
                       onfocus="this.style.borderColor='#C9A84C'" onblur="this.style.borderColor='#e5e7eb'">
            </div>
        </div>
        <div class="cbx-row" style="margin-top:8px;">
            <label><input type="checkbox" id="chkPrint" checked> Print</label>
            <label><input type="checkbox" id="chkShowwgt"> Show Wgt in Ledger</label>
        </div>
    </div>

</div><!-- /body -->

<!-- Toast -->
<div class="toast" id="toast"><span class="ti" id="toastIco"></span><span id="toastMsg"></span></div>

<!-- Customer Search Modal -->
<div class="modal-bg" id="modalBg" onclick="if(event.target===this)closeModal()">
    <div class="modal">
        <div class="modal-hdr">
            Customer Search
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body">
            <input type="text" class="srch-input" id="modalSrchInput"
                   placeholder="Type code or name…"
                   oninput="doModalSearch(this.value)"
                   onkeydown="modalKeyNav(event)">
            <ul class="srch-list" id="modalSrchList">
                <li class="srch-empty">Type to search customers…</li>
            </ul>
        </div>
    </div>
</div>

<script>
const API = '{{ url("/api/accounts/customer-billwise-rcpt") }}';
const TOKEN = '{{ csrf_token() }}';

let allBills = [];   // all bills for current customer
let visibleBills = []; // after filter

// ── Init ──────────────────────────────────────────────────────────────────
async function init() {
    try {
        const d = await post({ action: 'init' });
        if (!d.ok) return;
        document.getElementById('vchBadge').textContent = d.vchno || '—';
        document.getElementById('inDate').value  = d.today || '';
        document.getElementById('inGrate').value = d.grate  || 0;

        const sel = document.getElementById('selSman');
        sel.innerHTML = '<option value="">-- None --</option>';
        (d.salesmen || []).forEach(s => {
            sel.insertAdjacentHTML('beforeend',
                `<option value="${esc(s.code)}">${esc(s.code)} – ${esc(s.name)}</option>`);
        });
    } catch(e) { showToast('error', 'Init failed'); }
}

// ── Customer load ─────────────────────────────────────────────────────────
async function loadCustomer(code) {
    code = code.toUpperCase().trim();
    if (!code) return;
    const grate = parseFloat(document.getElementById('inGrate').value) || 0;
    try {
        const d = await post({ action: 'load_customer', code, grate });
        if (!d.ok) { showToast('error', d.msg || 'Customer not found'); return; }
        document.getElementById('custName').textContent = d.name || '—';
        document.getElementById('custBal').textContent  = fmt2(d.balance || 0);
        allBills = d.bills || [];
        renderBills();
    } catch(e) { showToast('error', 'Load failed'); }
}

function onCustKey(e) {
    if (e.key === 'Enter') {
        const v = document.getElementById('inCust').value.trim();
        if (v) loadCustomer(v);
    }
    if (e.key === 'F1') { e.preventDefault(); openSearchModal(); }
}

// ── Bill rendering ────────────────────────────────────────────────────────
function renderBills() {
    const onlyPending = document.getElementById('chkPending').checked;
    const nameFilter  = document.getElementById('inFilter').value.trim().toLowerCase();

    visibleBills = allBills.filter(b => {
        if (onlyPending && Math.abs(b.balance) <= 0.001) return false;
        if (nameFilter && !b.custname.toLowerCase().startsWith(nameFilter)) return false;
        return true;
    });

    const tbody = document.getElementById('billsTbody');
    const tfoot = document.getElementById('billsTfoot');

    if (!visibleBills.length) {
        tbody.innerHTML = `<tr><td colspan="12" style="text-align:center;padding:16px;color:#9ca3af;">No bills found.</td></tr>`;
        tfoot.style.display = 'none';
        return;
    }

    tbody.innerHTML = visibleBills.map((b, i) => `
        <tr data-idx="${i}">
            <td>${esc(b.tdate)}</td>
            <td>${esc(b.billno)}</td>
            <td class="r">${fmt2(b.netamt)}</td>
            <td class="r">${fmt2(b.grate)}</td>
            <td class="r">${fmt2(b.rcvdamt)}</td>
            <td class="r ${b.balance < -0.001 ? 'tag-neg' : b.balance < 0.001 ? 'tag-zero' : ''}">${fmt2(b.balance)}</td>
            <td class="c"><input type="checkbox" class="tbl-chk" ${b.selected?'checked':''} onchange="onSelectRow(${i},this.checked)"></td>
            <td class="r"><input type="number" class="tbl-input" id="ai${i}" value="${fmt2(b.alocamt)}" min="0" step="0.01" oninput="onAlocChange(${i},this.value)" onblur="fixAmt(${i},'alocamt',this)"></td>
            <td class="r"><input type="number" class="tbl-input" id="di${i}" value="${fmt2(b.discamt)}" min="0" step="0.01" oninput="onDiscChange(${i},this.value)"></td>
            <td class="r" id="nb${i}">${fmt2(netBal(b))}</td>
            <td class="r">${fmt3(b.balwgt)}</td>
            <td><input type="text" class="tbl-input tbl-date" id="dd${i}" value="${esc(b.duedate)}" maxlength="10" placeholder="dd/mm/yyyy"></td>
        </tr>
    `).join('');

    tfoot.style.display = '';
    updateFooter();
}

function netBal(b) {
    return Math.round((b.balance - (b.alocamt||0) - (b.discamt||0)) * 100) / 100;
}

// ── Row interaction ───────────────────────────────────────────────────────
function onSelectRow(i, checked) {
    const b = visibleBills[i];
    b.selected = checked;

    if (checked) {
        const totalAlloc = parseFloat(document.getElementById('inTotalAlloc').value) || 0;
        if (totalAlloc > 0) {
            const nonAloc = calcNonAllocated();
            if (nonAloc > 0.001) {
                const fill = Math.min(nonAloc, b.balance);
                b.alocamt = Math.max(0, Math.round(fill * 100) / 100);
                document.getElementById('ai' + i).value = fmt2(b.alocamt);
                document.getElementById('nb' + i).textContent = fmt2(netBal(b));
            } else {
                b.selected = false;
                document.querySelector(`[data-idx="${i}"] .tbl-chk`).checked = false;
                showToast('error', 'Amount is completely allocated');
                return;
            }
        } else {
            // no total entered — fill with full balance
            b.alocamt = Math.max(0, Math.round(b.balance * 100) / 100);
            document.getElementById('ai' + i).value = fmt2(b.alocamt);
            document.getElementById('nb' + i).textContent = fmt2(netBal(b));
        }
    } else {
        const totalAlloc = parseFloat(document.getElementById('inTotalAlloc').value) || 0;
        if (totalAlloc > 0) {
            // put amount back into non-allocated pool (just recalc)
        }
        b.alocamt = 0;
        document.getElementById('ai' + i).value = '0.00';
        document.getElementById('nb' + i).textContent = fmt2(netBal(b));
    }

    updateNonAlloc();
    updateFooter();
}

function onAlocChange(i, val) {
    visibleBills[i].alocamt = parseFloat(val) || 0;
    document.getElementById('nb' + i).textContent = fmt2(netBal(visibleBills[i]));
    updateNonAlloc();
    updateFooter();
}

function onDiscChange(i, val) {
    visibleBills[i].discamt = parseFloat(val) || 0;
    document.getElementById('nb' + i).textContent = fmt2(netBal(visibleBills[i]));
    updateFooter();
}

function fixAmt(i, field, el) {
    const b   = visibleBills[i];
    const val = parseFloat(el.value) || 0;
    if (field === 'alocamt' && val > b.balance + 0.005) {
        showToast('error', `Allocated amt (${b.billno}) is greater than balance`);
        el.value = fmt2(b.balance);
        b.alocamt = b.balance;
        document.getElementById('nb' + i).textContent = fmt2(netBal(b));
        updateNonAlloc();
        updateFooter();
    }
}

function onTotalAllocChange() {
    updateNonAlloc();
}

function calcNonAllocated() {
    const total = parseFloat(document.getElementById('inTotalAlloc').value) || 0;
    const alloced = visibleBills.reduce((s, b) => s + (b.alocamt||0), 0);
    return Math.round((total - alloced) * 100) / 100;
}

function updateNonAlloc() {
    const non = calcNonAllocated();
    const el  = document.getElementById('ftrNonAloc');
    el.textContent = fmt2(non);
    el.className   = 'ftr-val' + (non < -0.001 ? ' red' : '');
}

function updateFooter() {
    let sumNet=0, sumRcvd=0, sumBal=0, sumAloc=0, sumDisc=0, sumNB=0;
    visibleBills.forEach(b => {
        sumNet  += b.netamt||0;
        sumRcvd += b.rcvdamt||0;
        sumBal  += b.balance||0;
        sumAloc += b.alocamt||0;
        sumDisc += b.discamt||0;
        sumNB   += netBal(b);
    });
    const f = (id, v) => document.getElementById(id).textContent = fmt2(v);
    f('ftNetAmt', sumNet); f('ftRcvd', sumRcvd); f('ftBal', sumBal);
    f('ftAloc', sumAloc);  f('ftDisc', sumDisc);  f('ftNetBal', sumNB);
}

// ── Filters ───────────────────────────────────────────────────────────────
function applyFilter()     { renderBills(); }
function applyNameFilter() { renderBills(); }
function clearFilter() {
    document.getElementById('inFilter').value = '';
    renderBills();
}

// ── Save ──────────────────────────────────────────────────────────────────
async function doSave() {
    const custcode = document.getElementById('inCust').value.trim().toUpperCase();
    if (!custcode) { showToast('error', 'No customer selected'); return; }

    // Collect duedate edits from DOM
    visibleBills.forEach((b, i) => {
        const el = document.getElementById('dd' + i);
        if (el) b.duedate = el.value.trim();
        const ai = document.getElementById('ai' + i);
        if (ai) b.alocamt = parseFloat(ai.value)||0;
        const di = document.getElementById('di' + i);
        if (di) b.discamt = parseFloat(di.value)||0;
        const chk = document.querySelector(`[data-idx="${i}"] .tbl-chk`);
        if (chk) b.selected = chk.checked;
    });

    // Check any item allocated
    const hasAny = visibleBills.some(b => b.selected || b.alocamt > 0.001 || b.discamt > 0.001);
    if (!hasAny) { showToast('error', 'No items allocated'); return; }

    const nonAloc = calcNonAllocated();
    if (nonAloc > 0.005) {
        if (!confirm('Amount is not allocated completely.\nYou want to correct?')) {
            // proceed anyway
        } else {
            return;
        }
    }

    const payload = {
        action:   'save',
        tdate:    document.getElementById('inDate').value.trim(),
        grate:    parseFloat(document.getElementById('inGrate').value)||0,
        custcode,
        smcode:   document.getElementById('selSman').value,
        part:     document.getElementById('inPart').value.trim(),
        showwgt:  document.getElementById('chkShowwgt').checked ? 1 : 0,
        items:    allBills.map(b => ({
            slno:     b.slno,
            billno:   b.billno,
            selected: b.selected ? 1 : 0,
            alocamt:  b.alocamt  || 0,
            discamt:  b.discamt  || 0,
            duedate:  b.duedate  || '',
            balance:  b.balance  || 0,
            grate:    b.grate    || 0,
        })),
    };

    try {
        const d = await post(payload);
        if (!d.ok) { showToast('error', d.msg || 'Save failed'); return; }
        showToast('success', d.message || 'Saved successfully');
        document.getElementById('vchBadge').textContent = d.vchno || '—';
        doClear();
    } catch(e) { showToast('error', 'Save failed'); }
}

// ── Clear ─────────────────────────────────────────────────────────────────
function doClear() {
    document.getElementById('inCust').value    = '';
    document.getElementById('inPart').value    = '';
    document.getElementById('inTotalAlloc').value = '';
    document.getElementById('custName').textContent = '—';
    document.getElementById('custBal').textContent  = '—';
    document.getElementById('ftrNonAloc').textContent = '0.00';
    allBills = []; visibleBills = [];
    document.getElementById('billsTbody').innerHTML =
        `<tr><td colspan="12" style="text-align:center;padding:20px;color:#9ca3af;">Enter a customer code to load bills.</td></tr>`;
    document.getElementById('billsTfoot').style.display = 'none';
    init();
}

function doExit() {
    try {
        if (window.top && window.top.closeCurrentModule) window.top.closeCurrentModule();
        else window.history.back();
    } catch(e) { window.history.back(); }
}

// ── Customer Search Modal ─────────────────────────────────────────────────
let modalItems = [], modalIdx = -1;

function openSearchModal() {
    document.getElementById('modalBg').classList.add('open');
    const inp = document.getElementById('modalSrchInput');
    inp.value = '';
    inp.focus();
    document.getElementById('modalSrchList').innerHTML =
        '<li class="srch-empty">Type to search customers…</li>';
}

function closeModal() {
    document.getElementById('modalBg').classList.remove('open');
}

let _srchTimer;
async function doModalSearch(q) {
    clearTimeout(_srchTimer);
    if (q.trim().length < 1) {
        document.getElementById('modalSrchList').innerHTML =
            '<li class="srch-empty">Type to search customers…</li>';
        return;
    }
    _srchTimer = setTimeout(async () => {
        try {
            const d = await post({ action: 'customer_search', q: q.trim() });
            modalItems = d.data || [];
            modalIdx   = -1;
            const ul = document.getElementById('modalSrchList');
            if (!modalItems.length) {
                ul.innerHTML = '<li class="srch-empty">No customers found.</li>';
                return;
            }
            ul.innerHTML = modalItems.map((r, i) =>
                `<li data-i="${i}" onclick="selectModalItem(${i})">
                    <span class="srch-code">${esc(r.code)}</span>
                    <span class="srch-name">${esc(r.name)}</span>
                </li>`
            ).join('');
        } catch(e) {}
    }, 280);
}

function modalKeyNav(e) {
    const items = document.querySelectorAll('#modalSrchList li[data-i]');
    if (!items.length) return;
    if (e.key === 'ArrowDown') { modalIdx = Math.min(modalIdx+1, items.length-1); highlightModal(items); }
    else if (e.key === 'ArrowUp') { modalIdx = Math.max(modalIdx-1, 0); highlightModal(items); }
    else if (e.key === 'Enter' && modalIdx >= 0) selectModalItem(modalIdx);
    else if (e.key === 'Escape') closeModal();
}
function highlightModal(items) {
    items.forEach((el,i) => el.classList.toggle('active', i===modalIdx));
    if (items[modalIdx]) items[modalIdx].scrollIntoView({block:'nearest'});
}
function selectModalItem(i) {
    const r = modalItems[i];
    if (!r) return;
    document.getElementById('inCust').value = r.code;
    closeModal();
    loadCustomer(r.code);
}

// ── API helper ────────────────────────────────────────────────────────────
async function post(data) {
    const fd = new FormData();
    fd.append('_token', TOKEN);
    for (const [k,v] of Object.entries(data)) {
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
function fmt2(n) { return parseFloat(n||0).toFixed(2); }
function fmt3(n) { return parseFloat(n||0).toFixed(3); }
function esc(s)  { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }

// ── Toast ─────────────────────────────────────────────────────────────────
let _ttimer;
function showToast(type, msg) {
    const t=document.getElementById('toast');
    const ico=document.getElementById('toastIco');
    const m=document.getElementById('toastMsg');
    t.className='toast '+type;
    ico.textContent = type==='success' ? '✓' : '✕';
    m.textContent = msg;
    t.classList.add('show');
    clearTimeout(_ttimer);
    _ttimer = setTimeout(() => t.classList.remove('show'), 3500);
}

// ── Keyboard shortcuts ────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'F9')     { e.preventDefault(); doSave(); }
    if (e.key === 'Escape') { doExit(); }
});

// ── Boot ──────────────────────────────────────────────────────────────────
init();
</script>
</body>
</html>
