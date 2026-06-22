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
.toolbar{display:flex;gap:10px;align-items:center;background:#d5dce4;border:1px solid #95a5b8;padding:8px;margin-bottom:4px;flex-wrap:wrap}
.toolbar label{font-weight:700;font-size:13px}
.toolbar input,.toolbar select{height:30px;border:1px solid #95a5b8;border-radius:4px;padding:0 8px;font-size:13px}
.btn{height:32px;padding:0 14px;border:1px solid #7f8fa6;background:#c8d6e5;border-radius:4px;cursor:pointer;font-weight:700;font-size:12px;white-space:nowrap}
.btn:hover{background:#b0c4d8}
.btn-success{background:#27ae60;color:#fff;border-color:#219a52}
.btn-success:hover{background:#219a52}
.btn-danger{background:#c0392b;color:#fff;border-color:#a93226}
.btn-danger:hover{background:#a93226}
.btn-info{background:#2980b9;color:#fff;border-color:#2471a3}
.btn-info:hover{background:#2471a3}
.btn:disabled{opacity:.5;cursor:not-allowed}
.panel-box{border:1px solid #95a5b8;background:#fff;border-radius:4px;overflow:hidden}
.panel-hdr{background:#2c3e50;color:#ecf0f1;padding:6px 10px;font-weight:700;font-size:13px}
.tbl{width:100%;border-collapse:collapse;font-size:12px}
.tbl th,.tbl td{border:1px solid #ccc;padding:4px 6px}
.tbl th{background:#2c3e50;color:#ecf0f1;font-size:11px;position:sticky;top:0;z-index:1}
.tbl td{text-align:left}
.tbl td.r{text-align:right}
.tbl tr:nth-child(even){background:#f4f7fa}
.tbl-wrap{max-height:420px;overflow:auto}
.tbl input.cell{width:100%;border:1px solid #d5dbdb;border-radius:3px;padding:2px 4px;font-size:12px;box-sizing:border-box}
.tbl input.cell:focus{border-color:#2980b9;outline:none;background:#fffde7}
.tbl input.cell.num{text-align:right;width:70px}
.tbl select.cell{border:1px solid #d5dbdb;border-radius:3px;padding:2px 4px;font-size:12px}
.totals{background:#2c3e50;color:#ecf0f1;font-weight:700}
.footer-bar{display:flex;gap:10px;align-items:center;padding:8px;background:#d5dce4;border:1px solid #95a5b8;margin-top:4px}
.msg{font-weight:700;font-size:13px;margin-left:8px}
.msg.ok{color:#27ae60}
.msg.err{color:#c0392b}
.hdr-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.hdr-row label{font-weight:700;font-size:13px;min-width:50px}
/* Search modal */
.modal-bg{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:100;justify-content:center;align-items:center}
.modal-bg.show{display:flex}
.modal{background:#fff;border-radius:6px;width:600px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.modal-hdr{background:#2c3e50;color:#ecf0f1;padding:10px 14px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.modal-body{padding:10px;overflow:auto;flex:1}
.modal-body .tbl tr{cursor:pointer}
.modal-body .tbl tr:hover{background:#d4efdf !important}
.del-btn{color:#c0392b;cursor:pointer;font-weight:700;font-size:14px}
.del-btn:hover{color:#e74c3c}
</style>
<link rel="stylesheet" href="{{ asset('css/transaction-readable.css') }}?v={{ @filemtime(public_path('css/transaction-readable.css')) }}">
</head>
<body>
<div class="wrap">
  <div class="bar">
    <span>{{ $title }}</span>
    <div class="msg" id="msg"></div>
  </div>

  <div class="toolbar">
    <div class="hdr-row">
      <label>SMan:</label>
      <select id="txtStaff" style="width:160px"><option value="">— Select —</option></select>

      <label>Date:</label>
      <input type="date" id="txtDate" value="{{ date('Y-m-d') }}" style="width:150px">

      <label>Route:</label>
      <input type="text" id="txtRoute" style="width:120px" maxlength="30">

      <label>Type:</label>
      <select id="txtType" style="width:110px">
        <option value="Issue">Issue</option>
        <option value="Rcpt">Rcpt</option>
        <option value="Sales">Sales</option>
      </select>

      <label>Doc No:</label>
      <input type="text" id="txtDocNo" style="width:110px" readonly>
    </div>
  </div>

  <div class="panel-box">
    <div class="panel-hdr">Items</div>
    <div class="tbl-wrap">
      <table class="tbl" id="itemTbl">
        <thead><tr>
          <th style="width:30px">#</th>
          <th style="width:100px">Code</th>
          <th>Name</th>
          <th style="width:60px">Qty</th>
          <th style="width:80px">Weight</th>
          <th style="width:70px">Stone</th>
          <th style="width:80px">Rate</th>
          <th style="width:60px">RType</th>
          <th style="width:90px">Amount</th>
          <th style="width:30px"></th>
        </tr></thead>
        <tbody id="itemBody"></tbody>
        <tfoot>
          <tr class="totals">
            <td></td><td></td><td></td>
            <td class="r" id="totQty">0</td>
            <td class="r" id="totWgt">0.000</td>
            <td class="r" id="totStone">0.000</td>
            <td></td><td></td>
            <td class="r" id="totAmt">0.00</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div class="footer-bar">
    <button class="btn btn-success" id="btnSave">Save (F9)</button>
    <button class="btn btn-info" id="btnNew">New (Ctrl+N)</button>
    <button class="btn" id="btnEdit">Edit (F3)</button>
    <button class="btn btn-danger" id="btnCancel">Cancel (F4)</button>
    <button class="btn" id="btnExit">Exit (Esc)</button>
  </div>
</div>

<!-- Search Modal -->
<div class="modal-bg" id="searchModal">
  <div class="modal">
    <div class="modal-hdr">
      <span>Search Documents</span>
      <span class="del-btn" id="closeSearch">&times;</span>
    </div>
    <div class="modal-body">
      <div style="margin-bottom:8px">
        <input type="text" id="searchQ" placeholder="Search by doc no or staff name..." style="width:100%;height:30px;border:1px solid #ccc;border-radius:4px;padding:0 8px;font-size:13px;box-sizing:border-box">
      </div>
      <div class="tbl-wrap" style="max-height:400px">
        <table class="tbl">
          <thead><tr>
            <th>Doc No</th><th>Date</th><th>Staff</th><th>Type</th><th>Route</th>
          </tr></thead>
          <tbody id="searchBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
const $  = id => document.getElementById(id);
const api = (u,o) => fetch(u,{...o, headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','Accept':'application/json',...(o?.headers||{})}});

let items = [];
let editSlno = null;

function showMsg(t, ok=true) {
  const el = $('msg');
  el.textContent = t;
  el.className = 'msg ' + (ok ? 'ok' : 'err');
  if (t) setTimeout(() => { if (el.textContent === t) el.textContent = ''; }, 5000);
}

/* ── Compute Amount ─────────────────────────────────────────────────── */
function calcAmount(item) {
  const rate = item.rate || 0;
  if (item.rtype === 'Pc') {
    return rate * (item.qty || 0);
  } else {
    return rate * ((item.weight || 0) - (item.stwgt || 0));
  }
}

/* ── Init: Load Sman List ───────────────────────────────────────────── */
async function loadSman() {
  const r = await api(`{{ url("/api/staff-wgt-transaction/sman") }}`).then(r=>r.json());
  if (!r.ok) return;
  const sel = $('txtStaff');
  r.rows.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.code;
    opt.textContent = s.code + ' - ' + s.name;
    sel.appendChild(opt);
  });
}

/* ── Init: Get Next Doc No ──────────────────────────────────────────── */
async function loadNextDoc() {
  const r = await api(`{{ url("/api/staff-wgt-transaction/next-doc") }}`).then(r=>r.json());
  if (r.ok) $('txtDocNo').value = r.docno;
}

/* ── Render Grid ────────────────────────────────────────────────────── */
function renderGrid() {
  const tb = $('itemBody');
  tb.innerHTML = '';

  items.forEach((item, idx) => {
    const tr = document.createElement('tr');

    // #
    let td = document.createElement('td');
    td.textContent = idx + 1;
    tr.appendChild(td);

    // Code (editable)
    td = document.createElement('td');
    const codeInp = document.createElement('input');
    codeInp.className = 'cell';
    codeInp.type = 'text';
    codeInp.value = item.code || '';
    codeInp.onchange = async () => {
      const code = codeInp.value.trim().toUpperCase();
      codeInp.value = code;
      item.code = code;
      if (code) {
        const r = await api(`{{ url("/api/staff-wgt-transaction/lookup-item") }}?code=${encodeURIComponent(code)}`).then(r=>r.json());
        if (r.ok) {
          item.name = r.name;
          item.rtype = r.rtype;
          renderGrid();
        } else {
          showMsg(r.message || 'Item not found', false);
        }
      }
    };
    codeInp.onfocus = () => codeInp.select();
    td.appendChild(codeInp);
    tr.appendChild(td);

    // Name (read-only)
    td = document.createElement('td');
    td.textContent = item.name || '';
    tr.appendChild(td);

    // Qty
    td = document.createElement('td');
    const qtyInp = document.createElement('input');
    qtyInp.className = 'cell num';
    qtyInp.type = 'number';
    qtyInp.step = '1';
    qtyInp.min = '0';
    qtyInp.value = item.qty || '';
    qtyInp.onchange = () => { item.qty = parseFloat(qtyInp.value) || 0; updateTotals(); };
    qtyInp.onfocus = () => qtyInp.select();
    td.appendChild(qtyInp);
    tr.appendChild(td);

    // Weight
    td = document.createElement('td');
    const wgtInp = document.createElement('input');
    wgtInp.className = 'cell num';
    wgtInp.type = 'number';
    wgtInp.step = '0.001';
    wgtInp.min = '0';
    wgtInp.value = item.weight || '';
    wgtInp.onchange = () => { item.weight = parseFloat(wgtInp.value) || 0; updateTotals(); };
    wgtInp.onfocus = () => wgtInp.select();
    td.appendChild(wgtInp);
    tr.appendChild(td);

    // Stone Wgt
    td = document.createElement('td');
    const stInp = document.createElement('input');
    stInp.className = 'cell num';
    stInp.type = 'number';
    stInp.step = '0.001';
    stInp.min = '0';
    stInp.value = item.stwgt || '';
    stInp.onchange = () => { item.stwgt = parseFloat(stInp.value) || 0; updateTotals(); };
    stInp.onfocus = () => stInp.select();
    td.appendChild(stInp);
    tr.appendChild(td);

    // Rate
    td = document.createElement('td');
    const rateInp = document.createElement('input');
    rateInp.className = 'cell num';
    rateInp.type = 'number';
    rateInp.step = '0.01';
    rateInp.min = '0';
    rateInp.value = item.rate || '';
    rateInp.onchange = () => { item.rate = parseFloat(rateInp.value) || 0; updateTotals(); };
    rateInp.onfocus = () => rateInp.select();
    td.appendChild(rateInp);
    tr.appendChild(td);

    // RType
    td = document.createElement('td');
    const rtSel = document.createElement('select');
    rtSel.className = 'cell';
    ['Gm','Pc'].forEach(v => {
      const opt = document.createElement('option');
      opt.value = v; opt.textContent = v;
      if (v === item.rtype) opt.selected = true;
      rtSel.appendChild(opt);
    });
    rtSel.onchange = () => { item.rtype = rtSel.value; updateTotals(); };
    td.appendChild(rtSel);
    tr.appendChild(td);

    // Amount (computed, read-only)
    td = document.createElement('td');
    td.className = 'r';
    td.textContent = calcAmount(item).toFixed(2);
    tr.appendChild(td);

    // Delete
    td = document.createElement('td');
    td.innerHTML = '<span class="del-btn">&times;</span>';
    td.querySelector('.del-btn').onclick = () => { items.splice(idx, 1); renderGrid(); };
    tr.appendChild(td);

    tb.appendChild(tr);
  });

  // Add empty row for new entry
  addEmptyRowIfNeeded();
  updateTotals();
}

function addEmptyRowIfNeeded() {
  if (items.length === 0 || items[items.length - 1].code !== '') {
    items.push({ code: '', name: '', qty: 0, weight: 0, stwgt: 0, rate: 0, rtype: 'Gm' });
    // Don't re-render to avoid infinite loop — the row will appear on next render
  }
}

function updateTotals() {
  let tQ = 0, tW = 0, tS = 0, tA = 0;
  items.forEach(i => {
    if (!i.code) return;
    tQ += (i.qty || 0);
    tW += (i.weight || 0);
    tS += (i.stwgt || 0);
    tA += calcAmount(i);
  });
  $('totQty').textContent = tQ;
  $('totWgt').textContent = tW.toFixed(3);
  $('totStone').textContent = tS.toFixed(3);
  $('totAmt').textContent = tA.toFixed(2);
}

/* ── New ─────────────────────────────────────────────────────────────── */
async function newTransaction() {
  editSlno = null;
  $('txtStaff').value = '';
  $('txtDate').value = new Date().toISOString().slice(0, 10);
  $('txtRoute').value = '';
  $('txtType').value = 'Issue';
  items = [{ code: '', name: '', qty: 0, weight: 0, stwgt: 0, rate: 0, rtype: 'Gm' }];
  renderGrid();
  await loadNextDoc();
  showMsg('');
}

/* ── Save ────────────────────────────────────────────────────────────── */
$('btnSave').onclick = async () => {
  const staff = $('txtStaff').value;
  const tdate = $('txtDate').value;
  const ttype = $('txtType').value;
  const route = $('txtRoute').value;
  const docno = $('txtDocNo').value;

  if (!staff) { showMsg('Select SMan', false); return; }
  if (!tdate) { showMsg('Select Date', false); return; }

  const validItems = items.filter(i => i.code && i.code.trim() !== '');
  if (validItems.length === 0) { showMsg('Add at least one item', false); return; }

  if (!confirm('Save this transaction?')) return;

  showMsg('Saving...');
  const r = await api(`{{ url("/api/staff-wgt-transaction/save") }}`, {
    method: 'POST',
    body: JSON.stringify({ docno, tdate, staff, ttype, route, items: validItems, editSlno }),
  }).then(r=>r.json());

  if (r.ok) {
    showMsg(r.message || 'Saved');
    $('txtDocNo').value = r.docno;
    editSlno = r.slno;
  } else {
    showMsg(r.message || 'Save failed', false);
  }
};

/* ── Edit (Search) ───────────────────────────────────────────────────── */
$('btnEdit').onclick = () => openSearch();

function openSearch() {
  $('searchModal').classList.add('show');
  $('searchQ').value = '';
  $('searchQ').focus();
  doSearch();
}

$('closeSearch').onclick = () => $('searchModal').classList.remove('show');
$('searchModal').onclick = e => { if (e.target === $('searchModal')) $('searchModal').classList.remove('show'); };

let searchTimer;
$('searchQ').oninput = () => { clearTimeout(searchTimer); searchTimer = setTimeout(doSearch, 300); };

async function doSearch() {
  const q = $('searchQ').value.trim();
  const r = await api(`{{ url("/api/staff-wgt-transaction/search") }}?q=${encodeURIComponent(q)}`).then(r=>r.json());
  const tb = $('searchBody');
  tb.innerHTML = '';
  if (!r.ok) return;

  r.rows.forEach(row => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${row.docno}</td><td>${row.tdate}</td><td>${row.staffname || row.staff}</td><td>${row.ttype}</td><td>${row.route || ''}</td>`;
    tr.onclick = () => { loadDoc(row.docno); $('searchModal').classList.remove('show'); };
    tb.appendChild(tr);
  });
}

async function loadDoc(docno) {
  showMsg('Loading...');
  const r = await api(`{{ url("/api/staff-wgt-transaction/get") }}?docno=${encodeURIComponent(docno)}`).then(r=>r.json());
  if (!r.ok) { showMsg(r.message || 'Not found', false); return; }

  editSlno = r.slno;
  $('txtDocNo').value = r.docno;
  $('txtDate').value = r.tdate;
  $('txtStaff').value = r.staff;
  $('txtType').value = r.ttype;
  $('txtRoute').value = r.route || '';

  items = r.items || [];
  items.push({ code: '', name: '', qty: 0, weight: 0, stwgt: 0, rate: 0, rtype: 'Gm' });
  renderGrid();
  showMsg('Loaded ' + r.docno);
}

/* ── Cancel ──────────────────────────────────────────────────────────── */
$('btnCancel').onclick = async () => {
  const docno = $('txtDocNo').value;
  if (!docno) { showMsg('No document to cancel', false); return; }
  if (!editSlno) { showMsg('Save first or load a document', false); return; }
  if (!confirm(`Cancel/Delete ${docno}? This cannot be undone.`)) return;

  showMsg('Cancelling...');
  const r = await api(`{{ url("/api/staff-wgt-transaction/cancel") }}`, {
    method: 'POST',
    body: JSON.stringify({ docno }),
  }).then(r=>r.json());

  if (r.ok) {
    showMsg(r.message || 'Cancelled');
    newTransaction();
  } else {
    showMsg(r.message || 'Cancel failed', false);
  }
};

/* ── New button ──────────────────────────────────────────────────────── */
$('btnNew').onclick = () => newTransaction();

/* ── Exit ────────────────────────────────────────────────────────────── */
$('btnExit').onclick = () => window.close();

/* ── Keyboard ────────────────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); $('btnSave').click(); }
  if (e.key === 'F3') { e.preventDefault(); $('btnEdit').click(); }
  if (e.key === 'F4') { e.preventDefault(); $('btnCancel').click(); }
  if (e.key === 'Escape') {
    if ($('searchModal').classList.contains('show')) {
      $('searchModal').classList.remove('show');
    } else {
      window.close();
    }
  }
  if (e.ctrlKey && e.key === 'n') { e.preventDefault(); newTransaction(); }
});

/* ── Init ────────────────────────────────────────────────────────────── */
(async () => {
  await loadSman();
  await loadNextDoc();
  items = [{ code: '', name: '', qty: 0, weight: 0, stwgt: 0, rate: 0, rtype: 'Gm' }];
  renderGrid();
})();
</script>
</body>
</html>
