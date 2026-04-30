<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f0f0f0;color:#1f2937}
.wrap{padding:8px;max-width:700px;margin:0 auto}
.bar{background:#2c3e50;color:#ecf0f1;padding:8px 12px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.msg{font-weight:700;font-size:13px}.msg.ok{color:#27ae60}.msg.err{color:#c0392b}
.panel-box{border:1px solid #95a5b8;background:#fff;border-radius:4px;overflow:hidden;margin-top:4px}
.panel-hdr{background:#2c3e50;color:#ecf0f1;padding:6px 10px;font-weight:700;font-size:13px}
.form-body{padding:12px}
.form-row{display:flex;align-items:center;margin-bottom:8px;gap:8px}
.form-row label{font-weight:700;font-size:13px;width:140px;text-align:right;flex-shrink:0}
.form-row input{height:32px;border:1px solid #95a5b8;border-radius:4px;padding:0 8px;font-size:13px}
.form-row input:focus{border-color:#2980b9;outline:none;background:#fffde7}
.form-row input.ro{background:#eee;font-weight:700}
.form-row .w-med{width:180px}
.form-row .w-full{flex:1}
.form-row .amt{text-align:right}
.sep{border:0;border-top:1px solid #d5dce4;margin:12px 0}
.section-label{font-weight:700;font-size:12px;color:#555;margin:8px 0 4px 148px}
.btn{height:34px;padding:0 16px;border:1px solid #7f8fa6;background:#c8d6e5;border-radius:4px;cursor:pointer;font-weight:700;font-size:12px;white-space:nowrap}
.btn:hover{background:#b0c4d8}
.btn-success{background:#27ae60;color:#fff;border-color:#219a52}
.btn-success:hover{background:#219a52}
.btn:disabled{opacity:.5;cursor:not-allowed}
.footer-bar{display:flex;gap:10px;align-items:center;padding:8px;background:#d5dce4;border:1px solid #95a5b8;margin-top:4px}
.result-box{background:#eaf7ed;border:1px solid #27ae60;border-radius:4px;padding:10px;margin-top:8px}
.result-box.highlight{background:#fef9e7;border-color:#f39c12}
/* Party search wrap */
.party-wrap{display:flex;gap:4px;align-items:center}
.party-wrap input{flex:1}
/* Modal */
.modal-bg{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:100;justify-content:center;align-items:center}
.modal-bg.show{display:flex}
.modal{background:#fff;border-radius:6px;width:480px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.modal-hdr{background:#2c3e50;color:#ecf0f1;padding:10px 14px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.modal-body{padding:10px;overflow:auto;flex:1}
.close-btn{color:#ecf0f1;cursor:pointer;font-size:18px;font-weight:700}
.close-btn:hover{color:#e74c3c}
.srch-list{list-style:none;padding:0;margin:0}
.srch-list li{padding:6px 8px;cursor:pointer;border-radius:4px;display:flex;gap:10px;font-size:13px}
.srch-list li:hover{background:#d4efdf}
.srch-code{font-weight:700;min-width:60px;color:#2c3e50}
</style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <span>{{ $title }}</span>
    <div class="msg" id="msg"></div>
  </div>

  <div class="panel-box">
    <div class="panel-hdr">Rate Difference Entry</div>
    <div class="form-body">

      <div class="form-row">
        <label>Date:</label>
        <input type="date" id="txtDate" class="w-med" value="{{ date('Y-m-d') }}">
      </div>

      <div class="form-row">
        <label>Party:</label>
        <div class="party-wrap" style="flex:1">
          <input type="text" id="txtPartyCode" style="width:100px;text-transform:uppercase" placeholder="Code">
          <button class="btn" id="btnSearch" style="height:32px;padding:0 10px">F1</button>
        </div>
      </div>

      <div class="form-row">
        <label>Name:</label>
        <input type="text" id="txtName" class="w-full ro" readonly>
      </div>

      <div class="form-row">
        <label>A/c Balance:</label>
        <input type="text" id="txtBalance" class="w-med amt ro" readonly value="0.00">
        <span style="font-weight:700;font-size:12px;color:#555;margin-left:10px">Wgt Bal:</span>
        <input type="text" id="txtPwgtBal" class="amt ro" readonly value="0.000" style="width:100px;height:32px;border:1px solid #95a5b8;border-radius:4px;padding:0 8px;font-size:13px;background:#eee;font-weight:700">
      </div>

      <hr class="sep">

      <div class="form-row">
        <label>Enter Bill No:</label>
        <input type="text" id="txtBillNo" class="w-med" style="text-transform:uppercase" placeholder="Optional">
        <button class="btn" id="btnLoadBill" style="height:32px">Load</button>
      </div>

      <div class="form-row">
        <label>Net Amt:</label>
        <input type="text" id="txtNetAmt" class="w-med amt ro" readonly value="0.00">
      </div>

      <div class="form-row">
        <label>Weight:</label>
        <input type="number" id="txtWeight" class="w-med amt" step="0.001" min="0" value="0">
      </div>

      <div class="form-row">
        <label>Weight (Balance):</label>
        <input type="text" id="txtWgtBal" class="w-med amt ro" readonly value="0.000">
      </div>

      <div class="form-row">
        <label>Old Rate:</label>
        <input type="number" id="txtOldRate" class="w-med amt" step="0.01" min="0" value="0">
      </div>

      <div class="form-row">
        <label>New Rate:</label>
        <input type="number" id="txtNewRate" class="w-med amt" step="0.01" min="0" value="0">
      </div>

      <hr class="sep">

      <div class="form-row">
        <label>Diff Amount:</label>
        <input type="number" id="txtDiffAmt" class="w-med amt" step="0.01" min="0" value="0" style="font-weight:700;font-size:14px;color:#c0392b">
      </div>

      <div class="form-row">
        <label>A/c Cl.Balance:</label>
        <input type="text" id="txtClBalance" class="w-med amt ro" readonly value="0.00" style="font-weight:700">
      </div>

      <div class="form-row">
        <label>Wgt Cl.Balance:</label>
        <input type="text" id="txtClWgtBal" class="w-med amt ro" readonly value="0.000" style="font-weight:700">
      </div>

    </div>
  </div>

  <div class="footer-bar">
    <button class="btn btn-success" id="btnSave">Ok (F9)</button>
    <button class="btn" id="btnClear">Clear</button>
    <button class="btn" id="btnExit">Exit (Esc)</button>
  </div>
</div>

<!-- Search Modal -->
<div class="modal-bg" id="searchModal">
  <div class="modal">
    <div class="modal-hdr">
      <span>Party Search</span>
      <span class="close-btn" id="closeSearch">&times;</span>
    </div>
    <div class="modal-body">
      <input type="text" id="searchQ" placeholder="Type code or name…" style="width:100%;height:30px;border:1px solid #ccc;border-radius:4px;padding:0 8px;font-size:13px;box-sizing:border-box;margin-bottom:8px">
      <ul class="srch-list" id="searchList"></ul>
    </div>
  </div>
</div>

<script>
const $  = id => document.getElementById(id);
const api = (u,o) => fetch(u,{...o, headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','Accept':'application/json',...(o?.headers||{})}});

let billSlno = 0;
let billControl = 0;

function showMsg(t, ok=true) {
  const el = $('msg');
  el.textContent = t;
  el.className = 'msg ' + (ok ? 'ok' : 'err');
  if (t) setTimeout(() => { if (el.textContent === t) el.textContent = ''; }, 5000);
}

/* ── Init ────────────────────────────────────────────────────────────── */
async function init() {
  const r = await api(`{{ url("/api/rate-diff-adjustment/init") }}`).then(r=>r.json());
  if (r.ok) {
    $('txtNewRate').value = r.grate || 0;
  }
}

/* ── Load Party ──────────────────────────────────────────────────────── */
async function loadParty() {
  const code = $('txtPartyCode').value.trim().toUpperCase();
  $('txtPartyCode').value = code;
  if (!code) return;

  const r = await api(`{{ url("/api/rate-diff-adjustment/load-party") }}?code=${encodeURIComponent(code)}`).then(r=>r.json());
  if (!r.ok) { showMsg(r.message || 'Not found', false); return; }

  $('txtName').value = r.name;
  $('txtBalance').value = r.balance.toFixed(2);
  $('txtPwgtBal').value = r.wgtbal.toFixed(3);
  calcDiff();
}

$('txtPartyCode').onkeydown = e => {
  if (e.key === 'Enter') { loadParty(); }
  if (e.key === 'F1') { e.preventDefault(); openSearch(); }
};

/* ── Load Bill ───────────────────────────────────────────────────────── */
$('btnLoadBill').onclick = loadBill;
$('txtBillNo').onkeydown = e => { if (e.key === 'Enter') loadBill(); };

async function loadBill() {
  const billno = $('txtBillNo').value.trim().toUpperCase();
  $('txtBillNo').value = billno;
  const code = $('txtPartyCode').value.trim().toUpperCase();
  const tdate = $('txtDate').value;

  if (!billno || !code) { showMsg('Enter Party and Bill No', false); return; }

  const r = await api(`{{ url("/api/rate-diff-adjustment/load-bill") }}?billno=${encodeURIComponent(billno)}&code=${encodeURIComponent(code)}&tdate=${tdate}`).then(r=>r.json());
  if (!r.ok) { showMsg(r.message || 'Bill not found', false); return; }

  billSlno = r.slno;
  billControl = r.control;
  $('txtNetAmt').value = r.netamt.toFixed(2);
  $('txtWeight').value = r.weight.toFixed(3);
  $('txtOldRate').value = r.oldrate.toFixed(2);
  $('txtWgtBal').value = r.wgtbal.toFixed(3);
  calcDiff();
  showMsg('Bill loaded');
}

/* ── Calc Diff ───────────────────────────────────────────────────────── */
function calcDiff() {
  const billno  = $('txtBillNo').value.trim();
  const weight  = parseFloat($('txtWeight').value) || 0;
  const wgtbal  = parseFloat($('txtWgtBal').value) || 0;
  const oldrate = parseFloat($('txtOldRate').value) || 0;
  const newrate = parseFloat($('txtNewRate').value) || 0;
  const balance = parseFloat($('txtBalance').value) || 0;

  let diffamt = 0;
  if (billno === '') {
    // Without bill: weight * newrate
    diffamt = weight * newrate;
  } else {
    // With bill: wgtbal * newrate - wgtbal * oldrate
    diffamt = wgtbal * newrate - wgtbal * oldrate;
  }

  if (diffamt < 0) diffamt = 0;
  $('txtDiffAmt').value = diffamt.toFixed(2);

  // Closing balance
  $('txtClBalance').value = (balance + diffamt).toFixed(2);

  // Closing weight balance
  if (newrate > 0 && billno !== '') {
    const clwgt = wgtbal + diffamt / newrate;
    $('txtClWgtBal').value = clwgt.toFixed(3);
  } else {
    $('txtClWgtBal').value = wgtbal.toFixed(3);
  }
}

// Attach recalc
['txtWeight','txtOldRate','txtNewRate','txtDiffAmt'].forEach(id => {
  $(id).addEventListener('input', () => {
    if (id === 'txtDiffAmt') {
      // Manual edit of diff amount — update closing balance
      const balance = parseFloat($('txtBalance').value) || 0;
      const diffamt = parseFloat($('txtDiffAmt').value) || 0;
      const newrate = parseFloat($('txtNewRate').value) || 0;
      const wgtbal  = parseFloat($('txtWgtBal').value) || 0;
      $('txtClBalance').value = (balance + diffamt).toFixed(2);
      if (newrate > 0) {
        $('txtClWgtBal').value = (wgtbal + diffamt / newrate).toFixed(3);
      }
    } else {
      calcDiff();
    }
  });
});

/* ── Save ────────────────────────────────────────────────────────────── */
$('btnSave').onclick = async () => {
  const diffamt = parseFloat($('txtDiffAmt').value) || 0;
  if (diffamt <= 0) { showMsg('Nothing to do. Diff amount must be > 0', false); return; }
  if (!$('txtPartyCode').value.trim()) { showMsg('Party required', false); return; }
  if (!$('txtDate').value) { showMsg('Date required', false); return; }

  if (!confirm('Do you want to update this adjustment?')) return;

  showMsg('Saving...');
  const r = await api(`{{ url("/api/rate-diff-adjustment/save") }}`, {
    method: 'POST',
    body: JSON.stringify({
      tdate:       $('txtDate').value,
      code:        $('txtPartyCode').value.trim().toUpperCase(),
      billno:      $('txtBillNo').value.trim().toUpperCase(),
      weight:      parseFloat($('txtWeight').value) || 0,
      diffamt:     diffamt,
      newrate:     parseFloat($('txtNewRate').value) || 0,
      islno:       billSlno,
      billControl: billControl,
    }),
  }).then(r=>r.json());

  if (r.ok) {
    showMsg(r.message || 'Saved');
    clearForm();
  } else {
    showMsg(r.message || 'Save failed', false);
  }
};

/* ── Clear ───────────────────────────────────────────────────────────── */
function clearForm() {
  billSlno = 0; billControl = 0;
  $('txtPartyCode').value = '';
  $('txtName').value = '';
  $('txtBalance').value = '0.00';
  $('txtPwgtBal').value = '0.000';
  $('txtBillNo').value = '';
  $('txtNetAmt').value = '0.00';
  $('txtWeight').value = '0';
  $('txtWgtBal').value = '0.000';
  $('txtOldRate').value = '0';
  $('txtDiffAmt').value = '0';
  $('txtClBalance').value = '0.00';
  $('txtClWgtBal').value = '0.000';
  init();
}

$('btnClear').onclick = clearForm;
$('btnExit').onclick = () => window.close();

/* ── Search Modal ────────────────────────────────────────────────────── */
$('btnSearch').onclick = openSearch;

function openSearch() {
  $('searchModal').classList.add('show');
  $('searchQ').value = '';
  $('searchQ').focus();
  $('searchList').innerHTML = '';
}

$('closeSearch').onclick = () => $('searchModal').classList.remove('show');
$('searchModal').onclick = e => { if (e.target === $('searchModal')) $('searchModal').classList.remove('show'); };

let searchTimer;
$('searchQ').oninput = () => { clearTimeout(searchTimer); searchTimer = setTimeout(doSearch, 300); };
$('searchQ').onkeydown = e => { if (e.key === 'Escape') $('searchModal').classList.remove('show'); };

async function doSearch() {
  const q = $('searchQ').value.trim();
  if (!q) { $('searchList').innerHTML = ''; return; }

  const r = await api(`{{ url("/api/rate-diff-adjustment/search-party") }}?q=${encodeURIComponent(q)}`).then(r=>r.json());
  const ul = $('searchList');
  ul.innerHTML = '';
  if (!r.ok || !r.rows.length) return;

  r.rows.forEach(row => {
    const li = document.createElement('li');
    li.innerHTML = `<span class="srch-code">${row.code}</span><span>${row.name}</span>`;
    li.onclick = () => {
      $('txtPartyCode').value = row.code;
      $('searchModal').classList.remove('show');
      loadParty();
    };
    ul.appendChild(li);
  });
}

/* ── Keyboard ────────────────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); $('btnSave').click(); }
  if (e.key === 'F1' && document.activeElement === $('txtPartyCode')) { e.preventDefault(); openSearch(); }
  if (e.key === 'Escape') {
    if ($('searchModal').classList.contains('show')) {
      $('searchModal').classList.remove('show');
    } else {
      window.close();
    }
  }
});

/* ── Enter key nav ───────────────────────────────────────────────────── */
const navOrder = ['txtDate','txtPartyCode','txtBillNo','txtWeight','txtOldRate','txtNewRate','txtDiffAmt','btnSave'];
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
init();
</script>
</body>
</html>
