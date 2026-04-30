<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f0f0f0;color:#1f2937}
.wrap{padding:8px}
.bar{background:#2c3e50;color:#ecf0f1;padding:8px 12px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.toolbar{display:flex;gap:8px;align-items:center;background:#d5dce4;border:1px solid #95a5b8;padding:8px;margin-bottom:4px;flex-wrap:wrap}
.toolbar label{font-weight:700;font-size:13px}
.toolbar select,.toolbar input{height:30px;border:1px solid #95a5b8;border-radius:4px;padding:0 8px;font-size:13px}
.toolbar input[type=text]{text-transform:uppercase}
.btn{height:32px;padding:0 14px;border:1px solid #7f8fa6;background:#c8d6e5;border-radius:4px;cursor:pointer;font-weight:700;font-size:12px;white-space:nowrap}
.btn:hover{background:#b0c4d8}
.btn-primary{background:#2980b9;color:#fff;border-color:#2471a3}
.btn-primary:hover{background:#2471a3}
.btn-danger{background:#c0392b;color:#fff;border-color:#a33025}
.btn-danger:hover{background:#a33025}
.btn-success{background:#27ae60;color:#fff;border-color:#219a52}
.btn-success:hover{background:#219a52}
.btn:disabled{opacity:.5;cursor:not-allowed}
.radio-grp{display:flex;gap:12px;align-items:center;border:2px solid #95a5b8;border-radius:4px;padding:4px 10px;background:#ecf0f1}
.radio-grp label{font-weight:700;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:4px}
.chk-label{display:flex;align-items:center;gap:4px;font-weight:700;font-size:13px;cursor:pointer}
.panel-box{border:1px solid #95a5b8;background:#fff;border-radius:4px;overflow:hidden;margin-bottom:4px}
.panel-hdr{background:#2c3e50;color:#ecf0f1;padding:6px 10px;font-weight:700;font-size:13px}
.tbl{width:100%;border-collapse:collapse;font-size:12px}
.tbl th,.tbl td{border:1px solid #ccc;padding:4px 6px}
.tbl th{background:#2c3e50;color:#ecf0f1;font-size:11px;position:sticky;top:0;z-index:1}
.tbl td{text-align:right}
.tbl td.l{text-align:left}
.tbl tr:nth-child(even){background:#f4f7fa}
.tbl tr.sel{background:#aed6f1 !important}
.tbl-wrap{max-height:450px;overflow:auto}
.tbl input.cell-input{width:100%;border:1px solid #d5dbdb;border-radius:3px;padding:2px 4px;text-align:right;font-size:12px;box-sizing:border-box}
.tbl input.cell-input:focus{border-color:#2980b9;outline:none;background:#fffde7}
.totals{background:#2c3e50;color:#ecf0f1;font-weight:700}
.footer-bar{display:flex;gap:10px;align-items:center;padding:8px;background:#d5dce4;border:1px solid #95a5b8;margin-top:4px;flex-wrap:wrap}
.msg{font-weight:700;font-size:13px;margin-left:8px}
.msg.ok{color:#27ae60}
.msg.err{color:#c0392b}
.settings-row{display:flex;gap:12px;align-items:center;background:#ecf0f1;border:1px solid #95a5b8;border-radius:4px;padding:6px 10px;margin-bottom:4px;flex-wrap:wrap}
.settings-row label{font-weight:700;font-size:12px}
.settings-row input{height:26px;border:1px solid #95a5b8;border-radius:4px;padding:0 6px;font-size:12px;width:80px;text-align:center}
.part-row{display:flex;gap:10px;align-items:center;padding:6px 8px;background:#ecf0f1;border:1px solid #95a5b8;margin-top:4px}
.part-row label{font-weight:700;font-size:13px;white-space:nowrap}
.part-row input{flex:1;height:30px;border:1px solid #95a5b8;border-radius:4px;padding:0 8px;font-size:13px;text-transform:uppercase}
</style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <span>{{ $title }}</span>
    <div class="msg" id="msg"></div>
  </div>

  <!-- Top Toolbar -->
  <div class="toolbar">
    <label>Date:</label>
    <input type="date" id="txtDate" value="{{ date('Y-m-d') }}" style="width:150px">

    <div class="radio-grp">
      <span style="font-weight:700;font-size:12px">To Staff A/c:</span>
      <label><input type="radio" name="drCr" value="debit" checked> Debited</label>
      <label><input type="radio" name="drCr" value="credit"> Credited</label>
    </div>

    <label class="chk-label">
      <input type="checkbox" id="chkSalary"> Salary Allocation
    </label>

    <label>A/c Name:</label>
    <input type="text" id="txtAcCode" value="CASH" style="width:120px">
    <span id="acLabel" style="font-size:12px;color:#666"></span>
  </div>

  <!-- Settings Row (for salary mode) -->
  <div class="settings-row" id="settingsRow" style="display:none">
    <label>Allowed Leaves:</label>
    <input type="number" id="txtLeaves" value="4" style="width:60px">
    <label>Start Time:</label>
    <input type="text" id="txtStartTime" value="10:00 AM" style="width:90px">
    <label>End Time:</label>
    <input type="text" id="txtEndTime" value="07:00 PM" style="width:90px">
    <label>Wrk Hours/Day:</label>
    <input type="number" id="txtWrkHours" value="8" step="0.5" style="width:60px">
    <button class="btn" id="btnLogBased">Log Based Calculation</button>
  </div>

  <!-- Staff Grid -->
  <div class="panel-box">
    <div class="panel-hdr">Staff List <span id="staffCount"></span></div>
    <div class="tbl-wrap" id="gridWrap">
      <table class="tbl" id="staffTbl">
        <thead id="tblHead"></thead>
        <tbody id="staffBody"></tbody>
        <tfoot>
          <tr class="totals" id="totalsRow"></tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Particulars -->
  <div class="part-row">
    <label>Particulars:</label>
    <input type="text" id="txtParticular" maxlength="40" placeholder="Auto-generated if left blank">
  </div>

  <!-- Footer Buttons -->
  <div class="footer-bar">
    <button class="btn btn-success" id="btnSave">Save (F9)</button>
    <button class="btn btn-danger" id="btnDelete">Delete Row</button>
    <button class="btn" id="btnExit">Exit (Esc)</button>
  </div>
</div>

<script>
const $  = id => document.getElementById(id);
const api = (u,o) => fetch(u,{...o, headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','Accept':'application/json',...(o?.headers||{})}});

let items = [];
let selectedIdx = -1;
let isSalary = false;

function showMsg(t, ok=true) {
  const el = $('msg');
  el.textContent = t;
  el.className = 'msg ' + (ok ? 'ok' : 'err');
  if (t) setTimeout(() => { if (el.textContent === t) el.textContent = ''; }, 5000);
}

/* ── Header columns based on mode ─────────────────────────────────────── */
function getColumns() {
  if (isSalary) {
    return [
      { key: 'code',   label: 'Code',    w: '80px',  align: 'l', edit: false },
      { key: 'name',   label: 'Name',    w: '',      align: 'l', edit: false },
      { key: 'salary', label: 'Salary',  w: '90px',  align: 'r', edit: false, fmt: 2 },
      { key: 'tleave', label: 'Leave',   w: '55px',  align: 'r', edit: true,  fmt: 0 },
      { key: 'pdays',  label: '+Days',   w: '55px',  align: 'r', edit: true,  fmt: 0 },
      { key: 'cutamt', label: 'Cut Amt', w: '80px',  align: 'r', edit: true,  fmt: 2 },
      { key: 'addamt', label: 'Add Amt', w: '80px',  align: 'r', edit: true,  fmt: 2 },
      { key: 'amount', label: 'Amount',  w: '90px',  align: 'r', edit: true,  fmt: 2 },
    ];
  } else {
    return [
      { key: 'code',   label: 'Code',   w: '80px',  align: 'l', edit: false },
      { key: 'name',   label: 'Name',   w: '',      align: 'l', edit: false },
      { key: 'amount', label: 'Amount', w: '120px', align: 'r', edit: true, fmt: 2 },
    ];
  }
}

function renderHeader() {
  const cols = getColumns();
  const thead = $('tblHead');
  thead.innerHTML = '<tr>' + cols.map(c =>
    `<th style="${c.w ? 'width:'+c.w : ''}">${c.label}</th>`
  ).join('') + '</tr>';

  // Totals row template
  const tr = $('totalsRow');
  tr.innerHTML = cols.map((c, i) => {
    if (c.key === 'code') return '<td class="l" id="totCount">0</td>';
    if (c.fmt !== undefined && i > 1) return `<td id="tot_${c.key}">0</td>`;
    return '<td></td>';
  }).join('');
}

/* ── Render Grid ──────────────────────────────────────────────────────── */
function renderGrid() {
  const cols = getColumns();
  const tb = $('staffBody');
  tb.innerHTML = '';

  items.forEach((item, idx) => {
    const tr = document.createElement('tr');
    tr.onclick = () => {
      tb.querySelectorAll('tr').forEach(r => r.classList.remove('sel'));
      tr.classList.add('sel');
      selectedIdx = idx;
    };

    cols.forEach(c => {
      const td = document.createElement('td');
      if (c.align === 'l') td.className = 'l';

      if (c.edit) {
        const inp = document.createElement('input');
        inp.className = 'cell-input';
        inp.type = 'number';
        inp.step = c.fmt === 0 ? '1' : '0.01';
        inp.value = (item[c.key] ?? 0).toFixed(c.fmt ?? 2);
        inp.onchange = () => {
          item[c.key] = parseFloat(inp.value) || 0;
          if (isSalary && (c.key === 'cutamt' || c.key === 'addamt' || c.key === 'salary')) {
            item.amount = item.salary + item.addamt - item.cutamt;
            // Re-render the amount input in same row
            renderGrid();
          }
          updateTotals();
        };
        td.appendChild(inp);
      } else {
        const val = item[c.key] ?? '';
        td.textContent = c.fmt !== undefined ? parseFloat(val).toFixed(c.fmt) : val;
      }

      tr.appendChild(td);
    });

    tb.appendChild(tr);
  });

  updateTotals();
  $('staffCount').textContent = `(${items.length} staff)`;
}

function updateTotals() {
  const cols = getColumns();
  cols.forEach(c => {
    if (c.fmt !== undefined && c.key !== 'code') {
      const el = document.getElementById('tot_' + c.key);
      if (el) {
        const sum = items.reduce((s, i) => s + (parseFloat(i[c.key]) || 0), 0);
        el.textContent = sum.toFixed(c.fmt);
      }
    }
  });
  const el = $('totCount');
  if (el) el.textContent = items.length + ' staff';
}

/* ── Load Staff (normal mode) ─────────────────────────────────────────── */
async function loadStaff() {
  showMsg('Loading staff...');
  const r = await api(`{{ url("/api/staff-transaction/load") }}`).then(r=>r.json());
  if (!r.ok) { showMsg(r.message || 'Failed', false); return; }
  items = r.rows || [];
  renderHeader();
  renderGrid();
  showMsg(`Loaded ${items.length} staff`);
}

/* ── Salary Checkbox ──────────────────────────────────────────────────── */
$('chkSalary').onchange = async function() {
  isSalary = this.checked;
  $('settingsRow').style.display = isSalary ? '' : 'none';

  if (isSalary) {
    // Switch to salary mode
    $('txtAcCode').value = 'SALARY';
    document.querySelector('input[name=drCr][value=credit]').checked = true;
    await calcSalary();
  } else {
    // Switch to normal mode
    $('txtAcCode').value = 'CASH';
    $('txtParticular').value = '';
    document.querySelector('input[name=drCr][value=debit]').checked = true;
    await loadStaff();
  }
};

/* ── Calculate Salary ─────────────────────────────────────────────────── */
async function calcSalary() {
  showMsg('Calculating salary...');
  const r = await api(`{{ url("/api/staff-transaction/calc-salary") }}`, {
    method: 'POST',
    body: JSON.stringify({ date: $('txtDate').value }),
  }).then(r=>r.json());

  if (!r.ok) { showMsg(r.message || 'Failed', false); return; }
  items = r.rows || [];
  $('txtParticular').value = r.period || '';
  renderHeader();
  renderGrid();
  showMsg('Salary calculated');
}

/* ── Log Based Calculation ────────────────────────────────────────────── */
$('btnLogBased').onclick = async () => {
  showMsg('Log based calculation...');
  const r = await api(`{{ url("/api/staff-transaction/log-based") }}`, {
    method: 'POST',
    body: JSON.stringify({
      date: $('txtDate').value,
      allowedLeaves: parseInt($('txtLeaves').value) || 0,
      wrkHours: parseFloat($('txtWrkHours').value) || 8,
    }),
  }).then(r=>r.json());

  if (!r.ok) { showMsg(r.message || 'Failed', false); return; }
  items = r.rows || [];
  $('txtParticular').value = r.period || '';
  renderHeader();
  renderGrid();
  showMsg('Log based calculation done');
};

/* ── Delete Row ───────────────────────────────────────────────────────── */
$('btnDelete').onclick = () => {
  if (selectedIdx < 0 || selectedIdx >= items.length) {
    showMsg('Select a row first', false);
    return;
  }
  items.splice(selectedIdx, 1);
  selectedIdx = -1;
  renderGrid();
};

/* ── Save ─────────────────────────────────────────────────────────────── */
$('btnSave').onclick = async () => {
  const drCr = document.querySelector('input[name=drCr]:checked').value;
  const accode = $('txtAcCode').value.trim().toUpperCase();
  if (!accode) { showMsg('Enter A/c Name', false); return; }

  // Filter items with non-zero amounts
  const toSave = items.filter(i => (parseFloat(i.amount) || 0) !== 0);
  if (toSave.length === 0) { showMsg('No amounts to save', false); return; }

  if (!confirm('Do you want to save?')) return;

  showMsg('Saving...');
  const r = await api(`{{ url("/api/staff-transaction/save") }}`, {
    method: 'POST',
    body: JSON.stringify({
      date: $('txtDate').value,
      accode: accode,
      particular: $('txtParticular').value.trim(),
      isDebit: drCr === 'debit',
      isSalary: isSalary,
      items: toSave,
    }),
  }).then(r=>r.json());

  if (r.ok) {
    showMsg(`Saved! Voucher: ${r.vchno || ''}`);
    if (isSalary) {
      // Reset after salary save
    } else {
      // Reset form
      $('txtAcCode').value = 'CASH';
      $('chkSalary').checked = false;
      isSalary = false;
      $('settingsRow').style.display = 'none';
      await loadStaff();
    }
  } else {
    showMsg(r.message || 'Save failed', false);
  }
};

/* ── Exit ─────────────────────────────────────────────────────────────── */
$('btnExit').onclick = () => window.close();

/* ── Keyboard ─────────────────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); $('btnSave').click(); }
  if (e.key === 'Escape') window.close();
});

/* ── Init ─────────────────────────────────────────────────────────────── */
loadStaff();
</script>
</body>
</html>
