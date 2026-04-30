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
.toolbar{display:flex;gap:10px;align-items:center;background:#d5dce4;border:1px solid #95a5b8;padding:8px;margin-bottom:4px}
.toolbar label{font-weight:700;font-size:13px}
.toolbar input{height:30px;border:1px solid #95a5b8;border-radius:4px;padding:0 8px;font-size:13px}
.btn{height:32px;padding:0 14px;border:1px solid #7f8fa6;background:#c8d6e5;border-radius:4px;cursor:pointer;font-weight:700;font-size:12px;white-space:nowrap}
.btn:hover{background:#b0c4d8}
.btn-success{background:#27ae60;color:#fff;border-color:#219a52}
.btn-success:hover{background:#219a52}
.btn:disabled{opacity:.5;cursor:not-allowed}
.panel-box{border:1px solid #95a5b8;background:#fff;border-radius:4px;overflow:hidden}
.panel-hdr{background:#2c3e50;color:#ecf0f1;padding:6px 10px;font-weight:700;font-size:13px}
.tbl{width:100%;border-collapse:collapse;font-size:12px}
.tbl th,.tbl td{border:1px solid #ccc;padding:4px 6px}
.tbl th{background:#2c3e50;color:#ecf0f1;font-size:11px;position:sticky;top:0;z-index:1}
.tbl td{text-align:left}
.tbl td.r{text-align:right}
.tbl tr:nth-child(even){background:#f4f7fa}
.tbl tr.has-leave{background:#fef9e7 !important}
.tbl-wrap{max-height:560px;overflow:auto}
.tbl input.cell{width:100%;border:1px solid #d5dbdb;border-radius:3px;padding:2px 4px;font-size:12px;box-sizing:border-box}
.tbl input.cell:focus{border-color:#2980b9;outline:none;background:#fffde7}
.tbl input.cell.num{text-align:right;width:70px}
.tbl select.cell{border:1px solid #d5dbdb;border-radius:3px;padding:2px 4px;font-size:12px;width:100%}
.totals{background:#2c3e50;color:#ecf0f1;font-weight:700}
.footer-bar{display:flex;gap:10px;align-items:center;padding:8px;background:#d5dce4;border:1px solid #95a5b8;margin-top:4px}
.msg{font-weight:700;font-size:13px;margin-left:8px}
.msg.ok{color:#27ae60}
.msg.err{color:#c0392b}
</style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <span>{{ $title }}</span>
    <div class="msg" id="msg"></div>
  </div>

  <div class="toolbar">
    <label>Date:</label>
    <input type="date" id="txtDate" value="{{ date('Y-m-d') }}" style="width:160px">
    <span id="staffCount" style="font-size:12px;color:#666"></span>
  </div>

  <div class="panel-box">
    <div class="panel-hdr">Staff Leave</div>
    <div class="tbl-wrap">
      <table class="tbl" id="leaveTbl">
        <thead><tr>
          <th style="width:30px">#</th>
          <th>Name</th>
          <th style="width:80px">Salary</th>
          <th style="width:80px">Leave Days</th>
          <th style="width:80px">+Days</th>
          <th style="width:140px">Reason</th>
          <th style="width:160px">Note</th>
        </tr></thead>
        <tbody id="leaveBody"></tbody>
        <tfoot>
          <tr class="totals">
            <td></td><td></td><td></td>
            <td class="r" id="totLdays">0.00</td>
            <td class="r" id="totPdays">0.00</td>
            <td></td><td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div class="footer-bar">
    <button class="btn btn-success" id="btnSave">Save (F9)</button>
    <button class="btn" id="btnExit">Exit (Esc)</button>
  </div>
</div>

<script>
const $  = id => document.getElementById(id);
const api = (u,o) => fetch(u,{...o, headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','Accept':'application/json',...(o?.headers||{})}});

let items = [];
const reasons = ['', 'Medical', 'Festival', 'Emergency', 'Late'];

function showMsg(t, ok=true) {
  const el = $('msg');
  el.textContent = t;
  el.className = 'msg ' + (ok ? 'ok' : 'err');
  if (t) setTimeout(() => { if (el.textContent === t) el.textContent = ''; }, 5000);
}

/* ── Load Data ────────────────────────────────────────────────────────── */
async function loadData() {
  const date = $('txtDate').value;
  if (!date) return;

  showMsg('Loading...');
  const r = await api(`{{ url("/api/staff-leave-entry/load") }}?date=${date}`).then(r=>r.json());
  if (!r.ok) { showMsg(r.message || 'Failed', false); return; }
  items = r.rows || [];
  renderGrid();
  showMsg(`Loaded ${items.length} staff`);
}

/* ── Render Grid ──────────────────────────────────────────────────────── */
function renderGrid() {
  const tb = $('leaveBody');
  tb.innerHTML = '';

  items.forEach((item, idx) => {
    const tr = document.createElement('tr');
    if (item.ldays > 0 || item.plusdays > 0) tr.className = 'has-leave';

    // #
    let td = document.createElement('td');
    td.textContent = idx + 1;
    tr.appendChild(td);

    // Name (read-only)
    td = document.createElement('td');
    td.textContent = item.name;
    tr.appendChild(td);

    // Salary (read-only)
    td = document.createElement('td');
    td.className = 'r';
    td.textContent = item.salary.toFixed(2);
    tr.appendChild(td);

    // Leave Days (editable)
    td = document.createElement('td');
    const ldInp = document.createElement('input');
    ldInp.className = 'cell num';
    ldInp.type = 'number';
    ldInp.step = '0.5';
    ldInp.min = '0';
    ldInp.value = item.ldays || '';
    ldInp.onchange = () => { item.ldays = parseFloat(ldInp.value) || 0; updateTotals(); highlightRow(tr, item); };
    ldInp.onfocus = () => ldInp.select();
    td.appendChild(ldInp);
    tr.appendChild(td);

    // +Days (editable)
    td = document.createElement('td');
    const pdInp = document.createElement('input');
    pdInp.className = 'cell num';
    pdInp.type = 'number';
    pdInp.step = '0.5';
    pdInp.min = '0';
    pdInp.value = item.plusdays || '';
    pdInp.onchange = () => { item.plusdays = parseFloat(pdInp.value) || 0; updateTotals(); highlightRow(tr, item); };
    pdInp.onfocus = () => pdInp.select();
    td.appendChild(pdInp);
    tr.appendChild(td);

    // Reason (dropdown)
    td = document.createElement('td');
    const sel = document.createElement('select');
    sel.className = 'cell';
    reasons.forEach(r => {
      const opt = document.createElement('option');
      opt.value = r; opt.textContent = r || '—';
      if (r === item.reason) opt.selected = true;
      sel.appendChild(opt);
    });
    sel.onchange = () => { item.reason = sel.value; };
    td.appendChild(sel);
    tr.appendChild(td);

    // Note (editable)
    td = document.createElement('td');
    const noteInp = document.createElement('input');
    noteInp.className = 'cell';
    noteInp.type = 'text';
    noteInp.maxLength = 15;
    noteInp.value = item.note || '';
    noteInp.onchange = () => { item.note = noteInp.value; };
    noteInp.onfocus = () => noteInp.select();
    td.appendChild(noteInp);
    tr.appendChild(td);

    tb.appendChild(tr);
  });

  updateTotals();
  $('staffCount').textContent = `(${items.length} staff)`;
}

function highlightRow(tr, item) {
  tr.className = (item.ldays > 0 || item.plusdays > 0) ? 'has-leave' : '';
}

function updateTotals() {
  let tL = 0, tP = 0;
  items.forEach(i => { tL += (i.ldays || 0); tP += (i.plusdays || 0); });
  $('totLdays').textContent = tL.toFixed(2);
  $('totPdays').textContent = tP.toFixed(2);
}

/* ── Save ─────────────────────────────────────────────────────────────── */
$('btnSave').onclick = async () => {
  const date = $('txtDate').value;
  if (!date) { showMsg('Select a date', false); return; }
  if (!confirm('Do you want to save?')) return;

  showMsg('Saving...');
  const r = await api(`{{ url("/api/staff-leave-entry/save") }}`, {
    method: 'POST',
    body: JSON.stringify({ date, items }),
  }).then(r=>r.json());

  if (r.ok) {
    showMsg(r.message || 'Saved');
  } else {
    showMsg(r.message || 'Save failed', false);
  }
};

/* ── Date Change ──────────────────────────────────────────────────────── */
$('txtDate').onchange = () => loadData();

/* ── Exit ─────────────────────────────────────────────────────────────── */
$('btnExit').onclick = () => window.close();

/* ── Keyboard ─────────────────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); $('btnSave').click(); }
  if (e.key === 'Escape') window.close();
});

/* ── Init ─────────────────────────────────────────────────────────────── */
loadData();
</script>
</body>
</html>
