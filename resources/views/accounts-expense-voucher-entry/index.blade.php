<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f0f0f0;color:#1f2937}
.wrap{padding:8px;max-width:800px;margin:0 auto}
.bar{background:#2c3e50;color:#ecf0f1;padding:8px 12px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.msg{font-weight:700;font-size:13px}.msg.ok{color:#27ae60}.msg.err{color:#c0392b}
.panel-box{border:1px solid #95a5b8;background:#fff;border-radius:4px;overflow:hidden;margin-top:4px}
.panel-hdr{background:#2c3e50;color:#ecf0f1;padding:6px 10px;font-weight:700;font-size:13px}
.form-body{padding:12px}
.form-row{display:flex;align-items:center;margin-bottom:8px;gap:8px}
.form-row label{font-weight:700;font-size:13px;width:120px;text-align:right;flex-shrink:0}
.form-row input,.form-row select{height:32px;border:1px solid #95a5b8;border-radius:4px;padding:0 8px;font-size:13px}
.form-row input:focus,.form-row select:focus{border-color:#2980b9;outline:none;background:#fffde7}
.form-row input.ro{background:#eee}
.form-row .w-full{flex:1}
.form-row .w-med{width:180px}
.form-row .w-sm{width:120px}
.form-row .w-xs{width:80px}
.form-row .amt{text-align:right}
.sep{border:0;border-top:1px solid #d5dce4;margin:12px 0}
.btn{height:34px;padding:0 16px;border:1px solid #7f8fa6;background:#c8d6e5;border-radius:4px;cursor:pointer;font-weight:700;font-size:12px;white-space:nowrap}
.btn:hover{background:#b0c4d8}
.btn-success{background:#27ae60;color:#fff;border-color:#219a52}
.btn-success:hover{background:#219a52}
.btn-info{background:#2980b9;color:#fff;border-color:#2471a3}
.btn-info:hover{background:#2471a3}
.btn:disabled{opacity:.5;cursor:not-allowed}
.footer-bar{display:flex;gap:10px;align-items:center;padding:8px;background:#d5dce4;border:1px solid #95a5b8;margin-top:4px}
.chk-row{display:flex;align-items:center;gap:6px;margin-left:128px;margin-bottom:8px}
.chk-row input[type=checkbox]{width:16px;height:16px}
.chk-row label{font-weight:700;font-size:13px}
/* Accounts section */
.acc-section{background:#f8f9fa;border:1px solid #d5dce4;border-radius:4px;padding:10px;margin-top:8px}
.acc-section .form-row label{width:140px}
/* Modal */
.modal-bg{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:100;justify-content:center;align-items:center}
.modal-bg.show{display:flex}
.modal{background:#fff;border-radius:6px;width:560px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.modal-hdr{background:#2c3e50;color:#ecf0f1;padding:10px 14px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.modal-body{padding:10px;overflow:auto;flex:1}
.tbl{width:100%;border-collapse:collapse;font-size:12px}
.tbl th,.tbl td{border:1px solid #ccc;padding:4px 6px}
.tbl th{background:#2c3e50;color:#ecf0f1;font-size:11px}
.tbl tr:nth-child(even){background:#f4f7fa}
.tbl tr{cursor:pointer}
.tbl tr:hover{background:#d4efdf !important}
.close-btn{color:#ecf0f1;cursor:pointer;font-size:18px;font-weight:700}
.close-btn:hover{color:#e74c3c}
</style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <span>{{ $title }}</span>
    <div class="msg" id="msg"></div>
  </div>

  <div class="panel-box">
    <div class="panel-hdr">Expense Voucher</div>
    <div class="form-body">

      <div class="form-row">
        <label>Type:</label>
        <select id="txtBtype" class="w-med"></select>
        <label style="width:auto">Doc No:</label>
        <input type="text" id="txtDocNo" class="w-med" readonly style="background:#eee;font-weight:700">
      </div>

      <div class="form-row">
        <label>Date:</label>
        <input type="date" id="txtDate" class="w-med" value="{{ date('Y-m-d') }}">
        <label style="width:auto">Bill No:</label>
        <input type="text" id="txtBillNo" class="w-med" maxlength="30">
      </div>

      <div class="form-row">
        <label>Bill Date:</label>
        <input type="date" id="txtBillDate" class="w-med">
      </div>

      <div class="form-row">
        <label>Party:</label>
        <input type="text" id="txtPartyCode" class="w-sm" placeholder="Code" style="text-transform:uppercase">
        <input type="text" id="txtPartyName" class="w-full ro" readonly placeholder="Name">
      </div>

      <hr class="sep">

      <div class="form-row">
        <label>Bill Amount:</label>
        <input type="number" id="txtBamt" class="w-med amt" step="0.01" min="0" value="0">
      </div>

      <div class="form-row">
        <label>Discount:</label>
        <input type="number" id="txtDiscount" class="w-med amt" step="0.01" min="0" value="0">
      </div>

      <div class="form-row">
        <label>Tax:</label>
        <input type="number" id="txtTaxPerc" class="w-xs amt" step="0.01" min="0" value="0" placeholder="%">
        <input type="number" id="txtTaxAmt" class="w-sm amt" step="0.01" min="0" value="0">
      </div>

      <div class="form-row">
        <label>Net Amount:</label>
        <input type="number" id="txtNetAmt" class="w-med amt ro" readonly value="0" style="font-weight:700;background:#eee">
      </div>

      <div class="form-row">
        <label>Paid Amount:</label>
        <input type="number" id="txtPaidAmt" class="w-med amt" step="0.01" min="0" value="0">
      </div>

      <div class="form-row">
        <label>Balance:</label>
        <input type="number" id="txtBalance" class="w-med amt ro" readonly value="0" style="font-weight:700;background:#eee">
      </div>

      <hr class="sep">

      <div class="acc-section">
        <div style="font-weight:700;font-size:12px;margin-bottom:6px;color:#555">Account Codes</div>
        <div class="form-row">
          <label>Purch Amt A/c:</label>
          <input type="text" id="txtPamtAc" class="w-sm" value="EP" style="text-transform:uppercase">
        </div>
        <div class="form-row">
          <label>Tax A/c:</label>
          <input type="text" id="txtTaxAc" class="w-sm" value="ETAX" style="text-transform:uppercase">
        </div>
        <div class="form-row">
          <label>Discount A/c:</label>
          <input type="text" id="txtDiscAc" class="w-sm" value="PDISC" style="text-transform:uppercase">
        </div>
        <div class="form-row">
          <label>Cash/Bank A/c:</label>
          <input type="text" id="txtCbAc" class="w-sm" value="CASH" style="text-transform:uppercase">
        </div>
      </div>

      <div class="chk-row" style="margin-top:10px">
        <input type="checkbox" id="chkPurchBill" checked>
        <label for="chkPurchBill">Create as purchase bill</label>
      </div>

    </div>
  </div>

  <div class="footer-bar">
    <button class="btn btn-success" id="btnSave">Save (F9)</button>
    <button class="btn btn-info" id="btnNew">New (Ctrl+N)</button>
    <button class="btn" id="btnEdit">Edit (F3)</button>
    <button class="btn" id="btnExit">Exit (Esc)</button>
  </div>
</div>

<!-- Search Modal -->
<div class="modal-bg" id="searchModal">
  <div class="modal">
    <div class="modal-hdr">
      <span>Search Expense Vouchers</span>
      <span class="close-btn" id="closeSearch">&times;</span>
    </div>
    <div class="modal-body">
      <div style="margin-bottom:8px">
        <input type="text" id="searchQ" placeholder="Search by doc no, party..." style="width:100%;height:30px;border:1px solid #ccc;border-radius:4px;padding:0 8px;font-size:13px;box-sizing:border-box">
      </div>
      <table class="tbl">
        <thead><tr><th>Doc No</th><th>Date</th><th>Party</th><th>Name</th><th style="text-align:right">Net Amt</th></tr></thead>
        <tbody id="searchBody"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
const $  = id => document.getElementById(id);
const api = (u,o) => fetch(u,{...o, headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','Accept':'application/json',...(o?.headers||{})}});

let editSlno = null;
let billTypes = [];

function showMsg(t, ok=true) {
  const el = $('msg');
  el.textContent = t;
  el.className = 'msg ' + (ok ? 'ok' : 'err');
  if (t) setTimeout(() => { if (el.textContent === t) el.textContent = ''; }, 5000);
}

/* ── Calculate ───────────────────────────────────────────────────────── */
function recalc() {
  const bamt = parseFloat($('txtBamt').value) || 0;
  const disc = parseFloat($('txtDiscount').value) || 0;
  const taxperc = parseFloat($('txtTaxPerc').value) || 0;
  let taxamt = parseFloat($('txtTaxAmt').value) || 0;

  if (taxperc > 0) {
    taxamt = ((bamt - disc) * taxperc) / 100;
    $('txtTaxAmt').value = taxamt.toFixed(2);
  }

  const netamt = bamt - disc + taxamt;
  $('txtNetAmt').value = netamt.toFixed(2);

  const paid = parseFloat($('txtPaidAmt').value) || 0;
  $('txtBalance').value = (netamt - paid).toFixed(2);
}

// Attach recalc to amount fields
['txtBamt','txtDiscount','txtTaxPerc','txtTaxAmt','txtPaidAmt'].forEach(id => {
  $(id).addEventListener('input', recalc);
});

/* ── Load Bill Types ─────────────────────────────────────────────────── */
async function loadBillTypes() {
  const r = await api(`{{ url("/api/expense-voucher/bill-types") }}`).then(r=>r.json());
  if (!r.ok) return;
  billTypes = r.rows;
  const sel = $('txtBtype');
  sel.innerHTML = '';
  r.rows.forEach(bt => {
    const opt = document.createElement('option');
    opt.value = bt.code;
    opt.textContent = bt.code + (bt.name ? ' - ' + bt.name : '');
    sel.appendChild(opt);
  });
  // Trigger doc no load
  if (sel.value) loadNextDoc();
}

/* ── Load Defaults ───────────────────────────────────────────────────── */
async function loadDefaults() {
  const r = await api(`{{ url("/api/expense-voucher/defaults") }}`).then(r=>r.json());
  if (!r.ok) return;
  $('txtPamtAc').value = r.pamtAc || 'EP';
  $('txtTaxAc').value = r.taxAc || 'ETAX';
  $('txtDiscAc').value = r.discAc || 'PDISC';
  $('txtCbAc').value = r.cbAc || 'CASH';
}

/* ── Load Next Doc No ────────────────────────────────────────────────── */
async function loadNextDoc() {
  const btype = $('txtBtype').value;
  if (!btype) return;
  const r = await api(`{{ url("/api/expense-voucher/next-doc") }}?btype=${encodeURIComponent(btype)}`).then(r=>r.json());
  if (r.ok) {
    $('txtDocNo').value = r.docno;
    if (r.taxperc > 0) $('txtTaxPerc').value = r.taxperc;
  }
}

$('txtBtype').onchange = () => {
  if (!editSlno) {
    loadNextDoc();
    // Update tax % from bill type
    const bt = billTypes.find(b => b.code === $('txtBtype').value);
    if (bt) $('txtTaxPerc').value = bt.taxperc || 0;
    recalc();
  }
};

/* ── Party Lookup ────────────────────────────────────────────────────── */
$('txtPartyCode').onchange = async () => {
  const code = $('txtPartyCode').value.trim().toUpperCase();
  $('txtPartyCode').value = code;
  if (!code) { $('txtPartyName').value = ''; return; }

  const r = await api(`{{ url("/api/expense-voucher/lookup-party") }}?code=${encodeURIComponent(code)}`).then(r=>r.json());
  if (r.ok) {
    $('txtPartyName').value = r.name;
  } else {
    showMsg(r.message || 'Party not found', false);
    $('txtPartyName').value = '';
  }
};

/* ── New ─────────────────────────────────────────────────────────────── */
async function newVoucher() {
  editSlno = null;
  $('txtDate').value = new Date().toISOString().slice(0, 10);
  $('txtBillNo').value = '';
  $('txtBillDate').value = '';
  $('txtPartyCode').value = '';
  $('txtPartyName').value = '';
  $('txtBamt').value = '0';
  $('txtDiscount').value = '0';
  $('txtTaxAmt').value = '0';
  $('txtNetAmt').value = '0';
  $('txtPaidAmt').value = '0';
  $('txtBalance').value = '0';
  $('chkPurchBill').checked = true;
  await loadNextDoc();
  showMsg('');
}

/* ── Save ────────────────────────────────────────────────────────────── */
$('btnSave').onclick = async () => {
  const bamt = parseFloat($('txtBamt').value) || 0;
  if (bamt <= 0) { showMsg('Bill amount required', false); return; }
  if (!$('txtDate').value) { showMsg('Date required', false); return; }

  if (!confirm('Save this expense voucher?')) return;

  showMsg('Saving...');
  const r = await api(`{{ url("/api/expense-voucher/save") }}`, {
    method: 'POST',
    body: JSON.stringify({
      btype:     $('txtBtype').value,
      tdate:     $('txtDate').value,
      billno:    $('txtBillNo').value,
      billdate:  $('txtBillDate').value || null,
      partyCode: $('txtPartyCode').value.trim().toUpperCase(),
      partyName: $('txtPartyName').value,
      bamt:      bamt,
      discount:  parseFloat($('txtDiscount').value) || 0,
      taxperc:   parseFloat($('txtTaxPerc').value) || 0,
      taxamt:    parseFloat($('txtTaxAmt').value) || 0,
      netamt:    parseFloat($('txtNetAmt').value) || 0,
      paidamt:   parseFloat($('txtPaidAmt').value) || 0,
      pamtAc:    $('txtPamtAc').value.trim().toUpperCase(),
      taxAc:     $('txtTaxAc').value.trim().toUpperCase(),
      discAc:    $('txtDiscAc').value.trim().toUpperCase(),
      cbAc:      $('txtCbAc').value.trim().toUpperCase(),
      createPB:  $('chkPurchBill').checked,
      editSlno:  editSlno,
      docno:     $('txtDocNo').value,
    }),
  }).then(r=>r.json());

  if (r.ok) {
    showMsg(r.message || 'Saved');
    $('txtDocNo').value = r.docno;
    editSlno = r.slno;
  } else {
    showMsg(r.message || 'Save failed', false);
  }
};

/* ── Edit Search ─────────────────────────────────────────────────────── */
$('btnEdit').onclick = () => {
  $('searchModal').classList.add('show');
  $('searchQ').value = '';
  $('searchQ').focus();
  doSearch();
};

$('closeSearch').onclick = () => $('searchModal').classList.remove('show');
$('searchModal').onclick = e => { if (e.target === $('searchModal')) $('searchModal').classList.remove('show'); };

let searchTimer;
$('searchQ').oninput = () => { clearTimeout(searchTimer); searchTimer = setTimeout(doSearch, 300); };

async function doSearch() {
  const q = $('searchQ').value.trim();
  const r = await api(`{{ url("/api/expense-voucher/search") }}?q=${encodeURIComponent(q)}`).then(r=>r.json());
  const tb = $('searchBody');
  tb.innerHTML = '';
  if (!r.ok) return;

  r.rows.forEach(row => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${row.docno}</td><td>${row.tdate}</td><td>${row.party}</td><td>${row.name}</td><td style="text-align:right">${row.netamt.toFixed(2)}</td>`;
    tr.onclick = () => { loadVoucher(row.slno); $('searchModal').classList.remove('show'); };
    tb.appendChild(tr);
  });
}

async function loadVoucher(slno) {
  showMsg('Loading...');
  const r = await api(`{{ url("/api/expense-voucher/get") }}?slno=${slno}`).then(r=>r.json());
  if (!r.ok) { showMsg(r.message || 'Not found', false); return; }

  editSlno = r.slno;
  $('txtDocNo').value = r.docno;
  $('txtBtype').value = r.billtype;
  $('txtDate').value = r.tdate;
  $('txtBillNo').value = r.billno || '';
  $('txtPartyCode').value = r.partyCode;
  $('txtPartyName').value = r.partyName;
  $('txtBamt').value = r.bamt;
  $('txtDiscount').value = r.discount;
  $('txtTaxPerc').value = r.taxperc;
  $('txtTaxAmt').value = r.taxamt;
  $('txtNetAmt').value = r.netamt;
  $('txtPaidAmt').value = r.paidamt;
  $('txtBalance').value = (r.netamt - r.paidamt).toFixed(2);
  $('txtPamtAc').value = r.pamtAc;
  $('txtTaxAc').value = r.taxAc;
  $('txtDiscAc').value = r.discAc;
  $('txtCbAc').value = r.cbAc;
  showMsg('Loaded ' + r.docno);
}

/* ── New button ──────────────────────────────────────────────────────── */
$('btnNew').onclick = () => newVoucher();

/* ── Exit ────────────────────────────────────────────────────────────── */
$('btnExit').onclick = () => window.close();

/* ── Keyboard ────────────────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); $('btnSave').click(); }
  if (e.key === 'F3') { e.preventDefault(); $('btnEdit').click(); }
  if (e.key === 'Escape') {
    if ($('searchModal').classList.contains('show')) {
      $('searchModal').classList.remove('show');
    } else {
      window.close();
    }
  }
  if (e.ctrlKey && e.key === 'n') { e.preventDefault(); newVoucher(); }
});

/* ── Enter key navigation ────────────────────────────────────────────── */
const navOrder = ['txtDate','txtBillNo','txtBillDate','txtPartyCode','txtBamt','txtDiscount','txtTaxAmt','txtPaidAmt','btnSave'];
document.addEventListener('keypress', e => {
  if (e.key !== 'Enter') return;
  const id = document.activeElement?.id;
  const idx = navOrder.indexOf(id);
  if (idx >= 0 && idx < navOrder.length - 1) {
    e.preventDefault();
    const next = $(navOrder[idx + 1]);
    if (next) next.focus();
  }
});

/* ── Init ────────────────────────────────────────────────────────────── */
(async () => {
  await loadBillTypes();
  await loadDefaults();
  const qp = new URLSearchParams(window.location.search);
  const slnoQ = parseInt(qp.get('slno') || '0', 10);
  if (slnoQ > 0) {
    await loadVoucher(slnoQ);
  }
})();
</script>
</body>
</html>
