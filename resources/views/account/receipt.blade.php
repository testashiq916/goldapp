<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('js/goldapp-common.js') }}"></script>
    <title>Receipt</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 15px; }
        .wrap { max-width: 1200px; margin: 10px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 26px; color: #1f3f66; display: flex; align-items: center; gap: 12px; }
        h1 .vchno { font-size: 16px; color: #2a7a42; background: #e6f8ec; border: 1px solid #9fd5af; border-radius: 6px; padding: 3px 12px; }
        .status { margin: 8px 0; padding: 10px 12px; border-radius: 7px; font-size: 15px; display: none; }
        .status.show { display: block; }
        .status.ok { border: 1px solid #9fd5af; background: #e6f6ec; color: #12522d; }
        .status.err { border: 1px solid #f2b1b1; background: #fdeaea; color: #8e1f1f; }
        .sec { border: 1px solid #d8e2ef; border-radius: 8px; padding: 12px; margin-bottom: 10px; background: #f8fbff; }
        .sec h3 { margin: 0 0 8px; font-size: 17px; color: #2d4f74; }
        .grid2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .grid4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .field { display: flex; flex-direction: column; gap: 4px; }
        .field.wide { grid-column: span 2; }
        label { font-size: 13px; font-weight: 700; color: #375b84; }
        input, select { height: 38px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 10px; font-size: 15px; width: 100%; box-sizing: border-box; }
        input:focus { outline: none; border-color: #4a90d9; background: #fff8e6; }
        input[type="checkbox"] { width: 18px; height: 18px; }
        .chk-row { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; padding: 6px 0; }
        .chk-row label { display: flex; gap: 6px; align-items: center; font-weight: 600; font-size: 15px; color: #1f3f66; cursor: pointer; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 8px; }
        button { height: 40px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 18px; font-size: 15px; font-weight: 700; cursor: pointer; white-space: nowrap; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        button.warn { border-color: #a23b3b; background: #fdeaea; color: #8e1f1f; }
        button.active { border-color: #2470b0; background: #dfefff; color: #0f3e67; }
        button:disabled { opacity: .5; cursor: default; }
        .bal-box { display: inline-flex; align-items: center; gap: 8px; background: #edf4fc; border: 1px solid #c5d9ef; border-radius: 6px; padding: 5px 14px; font-size: 16px; font-weight: 700; }
        .bal-box .val { color: #1f3f66; }
        .bal-box .lbl { font-size: 13px; color: #6b7f9a; }
        .desc-line { font-size: 15px; color: #375b84; font-weight: 600; min-height: 22px; padding: 4px 0; }
        .table-wrap { max-height: 320px; overflow: auto; border: 1px solid #d8e2ef; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 15px; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 8px 10px; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; z-index: 2; font-size: 14px; font-weight: 700; }
        tbody tr { cursor: pointer; }
        tbody tr:hover { background: #f5faff; }
        tbody tr.active { background: #e9f3ff; }
        .nav-row { display: flex; gap: 6px; align-items: center; }
        .hidden { display: none !important; }
        @media (max-width: 900px) { .grid4 { grid-template-columns: repeat(2, 1fr); } .grid3 { grid-template-columns: repeat(2, 1fr); } .wrap { margin: 8px; } }
        @media (max-width: 640px) { .grid4,.grid3,.grid2 { grid-template-columns: 1fr; } .field.wide { grid-column: span 1; } .wrap { margin: 4px; padding: 10px; border-radius: 8px; } h1 { font-size: 20px; flex-wrap: wrap; } .toolbar { flex-direction: column; } .toolbar button { width: 100%; } .table-wrap { max-height: none; } }
    </style>
</head>
<body>
<div class="wrap">
    <h1>
        <span id="title">Receipt</span>
        <span id="vchnoTag" class="vchno"></span>
        <span style="flex:1;"></span>
        <span class="nav-row">
            <button id="btnBack" type="button" title="Back">Back</button>
            <button id="btnPrev" type="button" title="Previous">&lt;</button>
            <button id="btnNext" type="button" title="Next">&gt;</button>
        </span>
    </h1>
    <div id="status" class="status"></div>

    <div class="sec">
        <div class="grid4">
            <div class="field">
                <label>Date</label>
                <input id="tdate" type="date">
            </div>
            <div class="field">
                <label>Cash/Bank A/c</label>
                <select id="cbcode"></select>
                <div id="cbBalLine" style="font-size:20px;color:#0f4c81;margin-top:6px;line-height:1.2;font-weight:800;letter-spacing:.2px">
                    <span style="font-size:14px;color:#6b7280;font-weight:600;letter-spacing:.3px;text-transform:uppercase">Balance</span>
                    <span id="cbBalVal" style="font-weight:800;color:#0f4c81;font-size:24px">—</span>
                    <span id="cbBalLbl" style="font-size:16px;color:#6b7280;font-weight:700"></span>
                    <span id="cbBalAfterWrap" style="display:none;margin-left:10px;color:#15803d;font-weight:800;font-size:20px">
                        &rarr; <span id="cbBalAfterVal" style="font-size:24px"></span> <span id="cbBalAfterLbl" style="font-size:16px"></span>
                    </span>
                </div>
            </div>
            <div class="field">
                <label>Salesman</label>
                <select id="staff"><option value="">--</option></select>
            </div>
            <div class="field">
                <label>Account Type</label>
                <select id="actype">
                    <option value="A">All</option>
                    <option value="C">Customers</option>
                    <option value="S">Suppliers</option>
                    <option value="J">Jewelleries</option>
                    <option value="G">Goldsmiths</option>
                    <option value="R">Refiners</option>
                    <option value="F">Staffs</option>
                    <option value="O">General A/c</option>
                </select>
            </div>
        </div>
    </div>

    <div class="sec">
        <div class="grid4">
            <div class="field">
                <label>Account Code</label>
                <div style="display:flex;gap:4px;">
                    <input id="accode" list="accodeList" maxlength="20" style="flex:1;">
                    <datalist id="accodeList"></datalist>
                    <button id="btnAcHelp" type="button" style="height:32px;padding:0 8px;">^</button>
                </div>
            </div>
            <div class="field">
                <label>Particulars</label>
                <input id="particular" maxlength="200" placeholder="Enter particulars...">
            </div>
            <div class="field">
                <label>Amount</label>
                <input id="amount" type="number" step="0.01" value="" style="text-align:right;">
            </div>
            <div class="field">
                <label>Discount</label>
                <input id="discount" type="number" step="0.01" value="" style="text-align:right;">
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:4px;">
            <div class="bal-box">
                <span class="lbl">OB:</span>
                <span id="obVal" class="val"></span>
                <span id="obLabel" class="lbl"></span>
            </div>
        </div>
        <div style="display:flex;gap:16px;align-items:center;margin-top:6px;">
            <div id="acDesc" class="desc-line"></div>
            <div style="flex:1;"></div>
            <div class="bal-box">
                <span class="lbl">CB:</span>
                <span id="cbVal" class="val"></span>
                <span id="cbLabel" class="lbl"></span>
            </div>
        </div>
        <div id="amountWords" class="desc-line" style="margin-top:6px;color:#1f3f66;font-weight:700;"></div>
    </div>

    <div class="sec" id="secCheque" style="display:none;">
        <h3>Cheque Details</h3>
        <div class="grid3">
            <div class="field">
                <label>Cheque Date</label>
                <input id="chequedate" type="date">
            </div>
            <div class="field">
                <label>Cheque No</label>
                <input id="chequeno" maxlength="15" style="text-transform:uppercase;">
            </div>
            <div class="field" style="justify-content:flex-end;">
                <label><input id="chkPdc" type="checkbox"> PDC (Post Dated Cheque)</label>
            </div>
        </div>
    </div>

    <div class="sec">
        <div class="grid4">
            <div class="field">
                <label>Rate</label>
                <input id="rate" type="number" step="0.01" value="" style="text-align:right;">
            </div>
            <div class="field">
                <label>Tax %</label>
                <input id="taxperc" type="number" step="0.01" value="" style="text-align:right;">
            </div>
            <div class="field">
                <label>Tax Amount</label>
                <input id="taxamt" type="number" step="0.01" value="" style="text-align:right;">
            </div>
            <div class="field">
                <label>Due Date</label>
                <input id="duedate" type="date">
            </div>
        </div>
        <div class="chk-row" style="margin-top:6px;">
            <label><input id="chkInterstate" type="checkbox"> Interstate</label>
            <label><input id="chkTaxReverse" type="checkbox"> Tax Reverse Charge</label>
            <label><input id="chkPrintSlip" type="checkbox"> Print Slip</label>
            <label><input id="chkShowBalance" type="checkbox"> Show Balance</label>
        </div>
    </div>

    <div class="toolbar">
        <button id="btnAdd" type="button" class="active">Add</button>
        <button id="btnEdit" type="button">Edit</button>
        <button id="btnDelete" type="button" class="warn">Delete</button>
        <button id="btnSave" type="button" class="primary">Save (F9)</button>
        <button id="btnClear" type="button">Clear</button>
        <button id="btnExit" type="button">Exit (Esc)</button>
    </div>

    <div class="sec" style="margin-top:10px;">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
            <h3 style="margin:0;flex:1;">Recent Receipts</h3>
            <input id="listSearch" placeholder="Search voucher / particulars..." style="width:300px;font-size:15px;height:38px;">
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th style="width:100px;">Vch No</th><th style="width:90px;">Date</th><th>Cash/Bank</th><th style="width:100px;text-align:right;">Amount</th><th>Particulars</th></tr></thead>
                <tbody id="listBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Account Help Modal -->
<div id="acHelpModal" class="modal" style="position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center;z-index:9999;">
    <div style="width:760px;background:#fff;border-radius:10px;border:1px solid #d7dfeb;padding:12px;">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
            <strong style="flex:1;">Select Account</strong>
            <input id="acHelpSearch" placeholder="Search code / name / mobile..." style="width:260px;">
            <button id="acHelpClose" type="button">Close</button>
        </div>
        <div class="table-wrap" style="max-height:360px;">
            <table>
                <thead><tr><th style="width:120px;">Code</th><th>Name</th><th style="width:130px;">Mobile</th></tr></thead>
                <tbody id="acHelpRows"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const BASE = @json(url('/api/accounts/receipt'));
    const statusEl = document.getElementById('status');

    let mode = 'A';
    let currentSlno = 0;
    let currentVchno = '';
    let initData = {};

    function showStatus(msg, ok) {
        statusEl.textContent = msg;
        statusEl.className = 'status show ' + (ok ? 'ok' : 'err');
    }

    function clearStatus() { statusEl.className = 'status'; }

    async function api(payload) {
        const res = await fetch(BASE, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (e) { throw new Error('Invalid server response'); }
        if (!res.ok) throw new Error(data.error || data.message || 'Request failed');
        if (data.success === false) throw new Error(data.error || data.message || 'Request failed');
        return data;
    }

    function openPrintView(slno) {
        const id = Number(slno || 0);
        if (!id) return;
        const qs = new URLSearchParams({
            slno: String(id),
            obcb: document.getElementById('chkShowBalance').checked ? '1' : '0',
            show_balance: document.getElementById('chkShowBalance').checked ? '1' : '0',
            slip: document.getElementById('chkPrintSlip').checked ? '1' : '0',
        });
        const printUrl = @json(url('/accounts/receipt-print')) + '?' + qs.toString();
        const w = window.open(printUrl, '_blank', 'width=950,height=820,scrollbars=yes');
        if (!w) window.location.href = printUrl;
    }

    function esc(v) { return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function fmtDate(d) {
        if (!d) return '';
        if (/^\d{4}-\d{2}-\d{2}/.test(d)) {
            const p = d.split('-');
            return p[2] + '/' + p[1] + '/' + p[0];
        }
        return d;
    }

    function toIsoDate(d) {
        const raw = String(d || '').trim();
        if (!raw || raw === '00/00/00' || raw === '00/00/0000') return '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
        const m = raw.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
        if (!m) return raw;
        let yy = m[3];
        if (yy.length === 2) yy = '20' + yy;
        return yy + '-' + String(m[2]).padStart(2, '0') + '-' + String(m[1]).padStart(2, '0');
    }

    // ── Init ──
    async function init() {
        try {
            const currentCb = document.getElementById('cbcode').value || '';
            initData = await api({ action: 'init', cbcode: currentCb });

            // Populate cash/bank dropdown
            const cbSel = document.getElementById('cbcode');
            cbSel.innerHTML = '';
            (initData.cbAccounts || []).forEach(r => {
                const o = document.createElement('option');
                o.value = r.accode;
                o.textContent = r.accode + ' - ' + r.name;
                cbSel.appendChild(o);
            });

            // Populate staff dropdown
            const stSel = document.getElementById('staff');
            stSel.innerHTML = '<option value="">--</option>';
            (initData.salesmen || []).forEach(r => {
                const o = document.createElement('option');
                o.value = r.code;
                o.textContent = r.code + ' - ' + r.name;
                stSel.appendChild(o);
            });

            if (initData.defaultCbCode) {
                cbSel.value = initData.defaultCbCode;
            }

            document.getElementById('vchnoTag').textContent = initData.nextVchno || '';
            currentVchno = initData.nextVchno || '';
            document.getElementById('tdate').value = toIsoDate(initData.today || '');

            await loadAccountCodes();
            await loadList('');
            setMode('A');
            loadCashBankBalance();
        } catch (e) { showStatus('Init failed: ' + e.message, false); }
    }

    async function loadAccountCodes() {
        const type = document.getElementById('actype').value;
        try {
            const d = await api({ action: 'account_list', type: type });
            const dl = document.getElementById('accodeList');
            dl.innerHTML = '';
            (d.data || []).forEach(r => {
                const opt = document.createElement('option');
                opt.value = r.accode;
                opt.textContent = r.name || '';
                dl.appendChild(opt);
            });
        } catch (e) { /* silent */ }
    }

    // ── Modes ──
    function setMode(m) {
        mode = m;
        document.querySelectorAll('#btnAdd,#btnEdit,#btnDelete').forEach(b => b.classList.remove('active'));
        if (m === 'A') {
            document.getElementById('btnAdd').classList.add('active');
            document.getElementById('title').textContent = 'Receipt - Add';
        } else if (m === 'E') {
            document.getElementById('btnEdit').classList.add('active');
            document.getElementById('title').textContent = 'Receipt - Edit';
        } else if (m === 'D') {
            document.getElementById('btnDelete').classList.add('active');
            document.getElementById('title').textContent = 'Receipt - Delete';
        }
    }

    function resetForm() {
        document.getElementById('tdate').value = toIsoDate(initData.today || '');
        if (initData.defaultCbCode) {
            document.getElementById('cbcode').value = initData.defaultCbCode;
        } else if (initData.cbAccounts && initData.cbAccounts.length > 0) {
            document.getElementById('cbcode').value = initData.cbAccounts[0].accode;
        }
        document.getElementById('staff').value = '';
        document.getElementById('particular').value = '';
        document.getElementById('accode').value = '';
        document.getElementById('acDesc').textContent = '';
        document.getElementById('amount').value = '';
        document.getElementById('discount').value = '';
        document.getElementById('obVal').textContent = '';
        document.getElementById('obLabel').textContent = '';
        document.getElementById('cbVal').textContent = '';
        document.getElementById('cbLabel').textContent = '';
        document.getElementById('chequedate').value = '';
        document.getElementById('chequeno').value = '';
        document.getElementById('chkPdc').checked = false;
        document.getElementById('rate').value = '';
        document.getElementById('taxperc').value = '';
        document.getElementById('taxamt').value = '';
        document.getElementById('duedate').value = '';
        document.getElementById('chkInterstate').checked = false;
        document.getElementById('chkTaxReverse').checked = false;
        document.getElementById('secCheque').style.display = 'none';
        currentSlno = 0;
        currentVchno = initData.nextVchno || '';
        document.getElementById('vchnoTag').textContent = currentVchno;
        updateAmountWords();
        clearStatus();
    }

    // ── Cash/Bank change → show/hide cheque fields ──
    function checkChequeVisibility() {
        const sel = document.getElementById('cbcode');
        const opt = sel.options[sel.selectedIndex];
        const code = (opt ? opt.value : '').toUpperCase();
        // Check if bank account (by looking at initData)
        const isBank = (initData.cbAccounts || []).some(r =>
            r.accode.toUpperCase() === code && (r.actype2 || '').toUpperCase() === 'B'
        );
        // Also check the selected account type
        const accode = document.getElementById('accode').value.trim().toUpperCase();
        document.getElementById('secCheque').style.display = isBank ? '' : 'none';
    }

    // ── Cash/Bank balance (current + projected after receipt) ──
    let cbBalance = 0; // signed: >0 = Cr (your account has money / liability), <0 = Dr
    async function loadCashBankBalance() {
        const code = (document.getElementById('cbcode').value || '').toUpperCase().trim();
        const valEl = document.getElementById('cbBalVal');
        const lblEl = document.getElementById('cbBalLbl');
        if (!code) { valEl.textContent = '—'; lblEl.textContent = ''; cbBalance = 0; updateCBAfter(); return; }
        try {
            const d = await api({ action: 'load_account', accode: code });
            if (d && d.success) {
                cbBalance = parseFloat(d.balance) || 0; // controller returns positive=Cr, negative=Dr
                valEl.textContent = Math.abs(cbBalance).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                lblEl.textContent = (d.balance_label || (cbBalance >= 0 ? 'Cr' : 'Dr'));
            } else {
                valEl.textContent = '—'; lblEl.textContent = '';
                cbBalance = 0;
            }
            updateCBAfter();
        } catch (e) { valEl.textContent = '—'; lblEl.textContent = ''; cbBalance = 0; updateCBAfter(); }
    }

    function updateCBAfter() {
        // Receipt INCREASES the cash/bank account (money coming in) — that's a Dr on the asset side,
        // which in this app's sign convention reduces a positive (Cr) balance and increases a Dr balance.
        // For display we just show: current Cr balance + incoming amount → projected Cr balance.
        const amt = parseFloat(document.getElementById('amount').value) || 0;
        const wrap = document.getElementById('cbBalAfterWrap');
        const valEl = document.getElementById('cbBalAfterVal');
        const lblEl = document.getElementById('cbBalAfterLbl');
        if (amt <= 0) { wrap.style.display = 'none'; return; }
        // In the existing daybook sign: cash debit is stored as +amount on the cbcode side.
        // Balance display uses positive = Cr; a cash account typically holds Dr (asset).
        // To keep the math intuitive, project absolute "cash on hand" as |cbBalance| + amt
        // when current balance is Dr (asset), or cbBalance - amt when Cr (overdraft etc).
        const projected = (cbBalance <= 0) ? (cbBalance - amt) : (cbBalance - amt);
        valEl.textContent = Math.abs(projected).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        lblEl.textContent = (projected >= 0 ? 'Cr' : 'Dr');
        wrap.style.display = '';
    }

    document.getElementById('cbcode').addEventListener('change', checkChequeVisibility);
    document.getElementById('cbcode').addEventListener('change', () => {
        checkChequeVisibility();
        if (mode === 'A') refreshVchno();
        loadCashBankBalance();
    });
    document.getElementById('amount').addEventListener('input', updateCBAfter);

    // ── Account code change ──
    async function loadAccountInfo() {
        const code = document.getElementById('accode').value.trim().toUpperCase();
        if (!code) {
            document.getElementById('acDesc').textContent = '';
            document.getElementById('obVal').textContent = '';
            document.getElementById('obLabel').textContent = '';
            document.getElementById('cbVal').textContent = '';
            document.getElementById('cbLabel').textContent = '';
            return;
        }
        try {
            const d = await api({ action: 'load_account', accode: code });
            document.getElementById('acDesc').textContent = d.name || '';
            const bal = d.balance || 0;
            document.getElementById('obVal').textContent = Math.abs(bal).toFixed(2);
            document.getElementById('obLabel').textContent = d.balance_label || '';
            updateCB();

            // Show cheque if bank
            const cbCode = document.getElementById('cbcode').value.toUpperCase();
            const isCbBank = (initData.cbAccounts || []).some(r =>
                r.accode.toUpperCase() === cbCode && (r.actype2 || '').toUpperCase() === 'B'
            );
            const isAcBank = (d.actype2 || '').toUpperCase() === 'B';
            document.getElementById('secCheque').style.display = (isCbBank || isAcBank) ? '' : 'none';
        } catch (e) {
            document.getElementById('acDesc').textContent = 'Not found';
        }
    }

    function updateCB() {
        const ob = parseFloat(document.getElementById('obVal').textContent) || 0;
        const obLbl = document.getElementById('obLabel').textContent;
        const obSigned = (obLbl === 'Dr') ? -ob : ob;
        const amt = parseFloat(document.getElementById('amount').value) || 0;
        const disc = parseFloat(document.getElementById('discount').value) || 0;
        const cb = obSigned + amt + disc; // receipt + discount credits the party account
        document.getElementById('cbVal').textContent = Math.abs(cb).toFixed(2);
        document.getElementById('cbLabel').textContent = (cb >= 0) ? 'Cr' : 'Dr';
        updateAmountWords();
    }

    function numberToWordsIndian(num) {
        const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        function twoDigits(n) {
            if (n < 20) return ones[n];
            return tens[Math.floor(n / 10)] + (n % 10 ? ' ' + ones[n % 10] : '');
        }

        function threeDigits(n) {
            const hundred = Math.floor(n / 100);
            const rest = n % 100;
            let out = '';
            if (hundred) out += ones[hundred] + ' Hundred';
            if (rest) out += (out ? ' ' : '') + twoDigits(rest);
            return out;
        }

        function wholeNumberWords(n) {
            if (n === 0) return 'Zero';
            const parts = [];
            const crore = Math.floor(n / 10000000);
            n %= 10000000;
            const lakh = Math.floor(n / 100000);
            n %= 100000;
            const thousand = Math.floor(n / 1000);
            n %= 1000;
            if (crore) parts.push(threeDigits(crore) + ' Crore');
            if (lakh) parts.push(threeDigits(lakh) + ' Lakh');
            if (thousand) parts.push(threeDigits(thousand) + ' Thousand');
            if (n) parts.push(threeDigits(n));
            return parts.join(' ').trim();
        }

        const safe = Math.max(0, Number(num) || 0);
        const rupees = Math.floor(safe);
        const paise = Math.round((safe - rupees) * 100);
        let out = wholeNumberWords(rupees) + ' Rupees';
        if (paise > 0) out += ' and ' + wholeNumberWords(paise) + ' Paise';
        return out + ' Only';
    }

    function updateAmountWords() {
        const amt = parseFloat(document.getElementById('amount').value) || 0;
        document.getElementById('amountWords').textContent = 'Amount In Words : ' + numberToWordsIndian(amt);
    }

    document.getElementById('accode').addEventListener('change', loadAccountInfo);
    document.getElementById('accode').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); loadAccountInfo(); document.getElementById('amount').focus(); }
    });
    document.getElementById('amount').addEventListener('input', updateCB);
    document.getElementById('discount').addEventListener('input', updateCB);

    // ── Tax calc ──
    document.getElementById('taxperc').addEventListener('input', () => {
        const amt = parseFloat(document.getElementById('amount').value) || 0;
        const perc = parseFloat(document.getElementById('taxperc').value) || 0;
        document.getElementById('taxamt').value = ((amt * perc) / 100).toFixed(2);
    });

    // ── Account type change ──
    document.getElementById('actype').addEventListener('change', () => {
        loadAccountCodes();
        document.getElementById('accode').value = '';
        document.getElementById('acDesc').textContent = '';
    });

    // ── Account Help Modal ──
    let acHelpData = [];
    let acHelpSearchTimer = null;
    document.getElementById('btnAcHelp').addEventListener('click', async () => {
        document.getElementById('acHelpModal').style.display = 'flex';
        document.getElementById('acHelpSearch').value = '';
        document.getElementById('acHelpSearch').focus();
        await loadAcHelp('');
    });

    async function loadAcHelp(search) {
        const type = document.getElementById('actype').value;
        try {
            const d = await api({ action: 'account_list', type: type, search: search || '' });
            acHelpData = d.data || [];
            renderAcHelp(search || '', true);
        } catch (e) {
            acHelpData = [];
            renderAcHelp('', true);
        }
    }

    function renderAcHelp(filter, serverFiltered = false) {
        const body = document.getElementById('acHelpRows');
        body.innerHTML = '';
        const f = filter.toUpperCase();
        acHelpData.filter(r => serverFiltered || !f || (r.accode || '').toUpperCase().includes(f) || (r.name || '').toUpperCase().includes(f) || (r.mobile || '').toUpperCase().includes(f)).forEach(r => {
            const tr = document.createElement('tr');
            tr.dataset.code = r.accode || '';
            tr.innerHTML = `<td>${esc(r.accode || '')}</td><td>${esc(r.name || '')}</td><td>${esc(r.mobile || '')}</td>`;
            body.appendChild(tr);
        });
    }

    document.getElementById('acHelpSearch').addEventListener('input', () => {
        clearTimeout(acHelpSearchTimer);
        const search = document.getElementById('acHelpSearch').value.trim();
        acHelpSearchTimer = setTimeout(() => loadAcHelp(search), 180);
    });

    document.getElementById('acHelpRows').addEventListener('dblclick', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        document.getElementById('accode').value = tr.dataset.code || '';
        document.getElementById('acHelpModal').style.display = 'none';
        loadAccountInfo();
    });

    document.getElementById('acHelpClose').addEventListener('click', () => {
        document.getElementById('acHelpModal').style.display = 'none';
    });

    // ── List ──
    async function loadList(search) {
        try {
            const d = await api({ action: 'list', search: search || '' });
            const body = document.getElementById('listBody');
            body.innerHTML = '';
            (d.data || []).forEach(r => {
                const tr = document.createElement('tr');
                tr.dataset.slno = r.slno || '';
                tr.innerHTML = `<td>${esc(r.vchno || '')}</td><td>${esc(fmtDate(r.tdate || ''))}</td><td>${esc(r.cbcode || '')}</td><td style="text-align:right;">${esc(Number(r.amount || 0).toFixed(2))}</td><td>${esc(r.particular || '')}</td>`;
                body.appendChild(tr);
            });
        } catch (e) { /* silent */ }
    }

    document.getElementById('listSearch').addEventListener('input', () => {
        loadList(document.getElementById('listSearch').value.trim());
    });

    document.getElementById('listBody').addEventListener('click', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        document.querySelectorAll('#listBody tr').forEach(r => r.classList.remove('active'));
        tr.classList.add('active');
    });

    document.getElementById('listBody').addEventListener('dblclick', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        const slno = parseInt(tr.dataset.slno || '0');
        if (slno > 0) {
            if (mode === 'A') setMode('E');
            loadReceipt(slno);
        }
    });

    // ── Load receipt ──
    async function loadReceipt(slno, vchno) {
        try {
            const d = await api({ action: 'load', slno: slno || 0, vchno: vchno || '' });
            if (!d.data) { showStatus('Record not found', false); return; }
            const r = d.data;
            currentSlno = r.slno;
            currentVchno = r.vchno;
            document.getElementById('vchnoTag').textContent = r.vchno;
            document.getElementById('tdate').value = toIsoDate(r.tdate || '');
            document.getElementById('cbcode').value = r.cbcode || '';
            document.getElementById('staff').value = r.staff || '';
            document.getElementById('particular').value = r.particular || '';
            document.getElementById('accode').value = r.accode || '';
            document.getElementById('acDesc').textContent = r.acname || '';
            document.getElementById('amount').value = r.amount || 0;
            document.getElementById('discount').value = r.discount || 0;
            document.getElementById('chequedate').value = toIsoDate(r.chequedate || '');
            document.getElementById('chequeno').value = r.chequeno || '';
            document.getElementById('chkPdc').checked = !!r.pdc;
            document.getElementById('rate').value = r.rate || 0;
            document.getElementById('taxperc').value = r.taxperc || 0;
            document.getElementById('taxamt').value = r.taxamt || 0;
            document.getElementById('duedate').value = toIsoDate(r.duedate || '');
            document.getElementById('chkInterstate').checked = !!r.interstate;
            document.getElementById('chkTaxReverse').checked = !!r.taxreverse;

            const bal = r.balance || 0;
            document.getElementById('obVal').textContent = Math.abs(bal).toFixed(2);
            document.getElementById('obLabel').textContent = r.balance_label || '';
            updateCB();
            checkChequeVisibility();

            showStatus('Loaded: ' + r.vchno, true);
        } catch (e) { showStatus(e.message, false); }
    }

    // ── Save ──
    async function doSave() {
        if (mode === 'D') {
            if (currentSlno <= 0) { showStatus('No record selected to delete', false); return; }
            if (!confirm('Delete this receipt?')) return;
            try {
                await api({ action: 'delete', slno: currentSlno });
                showStatus('Receipt deleted', true);
                resetForm();
                setMode('A');
                await Promise.all([loadList(''), refreshVchno()]);
            } catch (e) { showStatus(e.message, false); }
            return;
        }

        const payload = {
            action: 'save',
            mode: mode,
            slno: currentSlno,
            vchno: currentVchno,
            tdate: document.getElementById('tdate').value,
            cbcode: document.getElementById('cbcode').value,
            accode: document.getElementById('accode').value.trim().toUpperCase(),
            amount: document.getElementById('amount').value,
            discount: document.getElementById('discount').value,
            particular: document.getElementById('particular').value,
            staff: document.getElementById('staff').value,
            chequeno: document.getElementById('chequeno').value,
            chequedate: document.getElementById('chequedate').value,
            pdc: document.getElementById('chkPdc').checked,
            duedate: document.getElementById('duedate').value,
            rate: document.getElementById('rate').value,
            taxperc: document.getElementById('taxperc').value,
            taxamt: document.getElementById('taxamt').value,
            interstate: document.getElementById('chkInterstate').checked,
            taxreverse: document.getElementById('chkTaxReverse').checked,
        };

        try {
            const d = await api(payload);
            if (!payload.pdc && (document.getElementById('chkPrintSlip').checked || document.getElementById('chkShowBalance').checked)) {
                openPrintView(d.slno || 0);
            }
            showStatus((d.message || 'Saved') + ' — ' + (d.vchno || ''), true);
            resetForm();
            setMode('A');
            await Promise.all([loadList(''), refreshVchno()]);
        } catch (e) { showStatus(e.message, false); }
    }

    async function refreshVchno() {
        try {
            const d = await api({ action: 'init', cbcode: document.getElementById('cbcode').value });
            initData.nextVchno = d.nextVchno;
            initData.defaultCbCode = d.defaultCbCode;
            if (mode === 'A') {
                currentVchno = d.nextVchno;
                document.getElementById('vchnoTag').textContent = d.nextVchno;
            }
        } catch (e) { /* silent */ }
    }

    // ── Navigation ──
    document.getElementById('btnPrev').addEventListener('click', async () => {
        const slno = currentSlno || 999999999;
        try {
            const d = await api({ action: 'navigate', slno: slno, dir: 'prev' });
            if (d.slno) {
                setMode('E');
                await loadReceipt(d.slno);
            }
        } catch (e) { showStatus(e.message, false); }
    });

    document.getElementById('btnNext').addEventListener('click', async () => {
        if (currentSlno <= 0) return;
        try {
            const d = await api({ action: 'navigate', slno: currentSlno, dir: 'next' });
            if (d.slno) {
                setMode('E');
                await loadReceipt(d.slno);
            } else {
                resetForm();
                setMode('A');
            }
        } catch (e) {
            resetForm();
            setMode('A');
        }
    });

    // ── Mode buttons ──
    document.getElementById('btnAdd').addEventListener('click', () => { resetForm(); setMode('A'); document.getElementById('tdate').focus(); });
    document.getElementById('btnEdit').addEventListener('click', () => { setMode('E'); document.getElementById('accode').focus(); });
    document.getElementById('btnDelete').addEventListener('click', async () => {
        if (currentSlno > 0) {
            setMode('D');
            await doSave();
            return;
        }
        setMode('D');
        showStatus('Load a receipt first, then click Delete again.', false);
    });
    document.getElementById('btnSave').addEventListener('click', doSave);
    document.getElementById('btnClear').addEventListener('click', () => { resetForm(); setMode(mode); });
    function leaveReceipt() {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '{{ url('/dashboard') }}';
        }
    }

    document.getElementById('btnBack').addEventListener('click', leaveReceipt);
    document.getElementById('btnExit').addEventListener('click', leaveReceipt);

    // ── Keyboard shortcuts ──
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') { e.preventDefault(); doSave(); }
        else if (e.key === 'Escape') {
            e.preventDefault();
            const modal = document.getElementById('acHelpModal');
            if (modal.style.display === 'flex') { modal.style.display = 'none'; return; }
            document.getElementById('btnExit').click();
        }
    });

    // ── Start ──

    init().then(async () => {
        try {
            const qp = new URLSearchParams(window.location.search);
            const modeQ = (qp.get('mode') || '').toUpperCase();
            const slnoQ = parseInt(qp.get('slno') || '0', 10);
            const vchnoQ = (qp.get('vchno') || '').trim().toUpperCase();
            if (modeQ === 'E' && (slnoQ > 0 || vchnoQ !== '')) {
                setMode('E');
                await loadReceipt(slnoQ, vchnoQ);
            }
        } catch (_) {}
    });
})();
</script>
</body>
</html>
