<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter','Segoe UI',Tahoma,sans-serif;font-size:12px;color:#1a202c;
  overflow:hidden;height:100vh;background:radial-gradient(circle at 10% -10%,#eef3ff 0%,#f4f7fb 40%,#edf2f8 100%)}
input,select,button{transition:all .15s ease}
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-track{background:#f7fafc}
::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:3px}

/* ── Main window ── */
.main-window{background:#fff;border:1px solid #d6dcea;border-radius:12px;
  box-shadow:0 10px 30px rgba(33,52,89,.10);margin:8px;overflow:hidden;position:relative;
  display:flex;flex-direction:column;height:calc(100vh - 16px)}

.title-bar{background:linear-gradient(135deg,#0e4d76,#1a6da3);color:#fff;
  padding:10px 14px;font-weight:700;font-size:13px;display:flex;align-items:center;gap:8px;
  letter-spacing:.3px;flex-shrink:0}
.title-bar .icon{width:16px;height:16px;background:#38bdf8;border-radius:4px;flex-shrink:0}

/* ── Toolbar ── */
.toolbar{display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f0f6ff;
  border-bottom:1px solid #d6dcea;flex-wrap:wrap;flex-shrink:0}
.toolbar label{font-weight:600;font-size:11px;color:#64748b}
.toolbar input,.toolbar select{font-size:11px;padding:3px 8px;border:1px solid #d6dcea;
  border-radius:6px;height:28px;background:#fff;color:#2d3748}
.toolbar input:focus,.toolbar select:focus{border-color:#60a5fa;
  box-shadow:0 0 0 3px rgba(59,130,246,.12);outline:none}
.toolbar input.sm{width:80px}
.toolbar input.md{width:120px}
.toolbar .sep{width:1px;height:20px;background:#d6dcea;margin:0 4px}

.btn{font-size:11px;padding:4px 14px;border:1px solid #d6dcea;border-radius:7px;
  cursor:pointer;font-weight:600;height:28px;display:inline-flex;align-items:center;gap:4px;
  transition:all .2s ease}
.btn-primary{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none}
.btn-primary:hover{background:linear-gradient(135deg,#1d4ed8,#1e40af);transform:translateY(-1px);
  box-shadow:0 2px 8px rgba(37,99,235,.25)}
.btn-success{background:linear-gradient(135deg,#059669,#047857);color:#fff;border:none}
.btn-success:hover{background:linear-gradient(135deg,#047857,#065f46);transform:translateY(-1px);
  box-shadow:0 2px 8px rgba(5,150,105,.25)}
.btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none}
.btn-danger:hover{background:linear-gradient(135deg,#b91c1c,#991b1b);transform:translateY(-1px)}
.btn-outline{background:#fff;color:#374151}
.btn-outline:hover{background:#f0f6ff;border-color:#93c5fd}
.btn-amber{background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border:none}
.btn-amber:hover{background:linear-gradient(135deg,#b45309,#92400e);transform:translateY(-1px)}

/* ── Summary bar ── */
.summary-bar{display:flex;align-items:center;gap:16px;padding:6px 14px;background:#f8fafc;
  border-bottom:1px solid #e2e8f0;font-size:11px;flex-shrink:0}
.summary-bar .stat{display:flex;align-items:center;gap:4px}
.summary-bar .stat .lbl{color:#64748b;font-weight:500}
.summary-bar .stat .val{font-weight:700;color:#1e293b}
.summary-bar .stat .val.blue{color:#2563eb}
.summary-bar .stat .val.green{color:#059669}
.summary-bar .stat .val.amber{color:#d97706}

/* ── Data grid ── */
.grid-container{flex:1;overflow:hidden;margin:0 8px 8px;border:1px solid #d6dcea;
  border-radius:10px;background:#fff;box-shadow:0 3px 10px rgba(32,55,92,.05);
  display:flex;flex-direction:column}
.grid-scroll{flex:1;overflow-y:auto;overflow-x:auto}
table.orders{width:100%;border-collapse:collapse;font-size:11px}
table.orders thead th{background:linear-gradient(180deg,#1a6da3,#0e4d76);color:#e0f2fe;
  padding:6px 6px;border:1px solid #2980b9;font-weight:600;font-size:10px;
  text-align:center;white-space:nowrap;text-transform:uppercase;letter-spacing:.3px;
  position:sticky;top:0;z-index:2}
table.orders tbody td{border:1px solid #edf1f7;padding:4px 6px;text-align:center}
table.orders tbody tr:nth-child(even){background:#f8faff}
table.orders tbody tr:hover{background:#eff6ff}
table.orders tbody tr.sel td{background:#bfdbfe}
table.orders tbody td.left{text-align:left}
table.orders tbody td.right{text-align:right}
table.orders tbody td.mono{font-family:'Consolas','Courier New',monospace;font-size:11px}

/* expand row */
table.orders tbody tr.detail-row{display:none}
table.orders tbody tr.detail-row.open{display:table-row}
table.orders tbody tr.detail-row td{background:#f0f6ff;padding:6px 12px;text-align:left}
.detail-items{width:100%;font-size:10px;border-collapse:collapse;margin-top:4px}
.detail-items th{background:#e0ecff;padding:3px 5px;border:1px solid #c7d8f0;font-weight:600;
  text-align:center;font-size:9px;text-transform:uppercase}
.detail-items td{border:1px solid #e2e8f0;padding:2px 5px;text-align:center}
.detail-items td.right{text-align:right}

/* ── Empty state ── */
.empty-state{text-align:center;padding:60px 20px;color:#94a3b8}
.empty-state .icon-lg{font-size:40px;margin-bottom:12px}
.empty-state .msg{font-size:14px;font-weight:600;color:#64748b}
.empty-state .sub{font-size:12px;margin-top:4px}

/* ── Modal overlay ── */
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:1000;
  display:none;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.25);
  min-width:500px;max-width:90vw;max-height:85vh;overflow:hidden;
  display:flex;flex-direction:column;animation:modalIn .25s ease}
@keyframes modalIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
.modal-header{padding:12px 16px;background:linear-gradient(135deg,#0e4d76,#1a6da3);
  color:#fff;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:space-between}
.modal-header .close-btn{background:none;border:none;color:#fff;font-size:18px;
  cursor:pointer;padding:0 4px;opacity:.7}
.modal-header .close-btn:hover{opacity:1}
.modal-body{padding:14px 16px;overflow-y:auto;flex:1}
.modal-footer{padding:10px 16px;border-top:1px solid #e2e8f0;display:flex;
  justify-content:flex-end;gap:8px;background:#f8fafc}

/* ── Form fields in modal ── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 16px}
.form-group{display:flex;flex-direction:column;gap:2px}
.form-group.full{grid-column:1/-1}
.form-group label{font-size:11px;font-weight:600;color:#64748b}
.form-group input,.form-group select{font-size:11px;padding:4px 8px;border:1px solid #d6dcea;
  border-radius:6px;height:30px;background:#fff}
.form-group input:focus,.form-group select:focus{border-color:#60a5fa;
  box-shadow:0 0 0 3px rgba(59,130,246,.12);outline:none}

/* items entry in modal */
.item-entry-table{width:100%;border-collapse:collapse;font-size:10px;margin-top:8px}
.item-entry-table th{background:#e0ecff;padding:4px 5px;border:1px solid #c7d8f0;
  font-weight:600;font-size:9px;text-transform:uppercase}
.item-entry-table td{border:1px solid #e2e8f0;padding:2px 2px}
.item-entry-table input{font-size:10px;border:none;background:transparent;
  width:100%;text-align:center;height:22px}
.item-entry-table input:focus{background:#fffff0;outline:2px solid #3b82f6;border-radius:2px}
.item-entry-table input.right{text-align:right}

/* ── CSV preview table ── */
.csv-preview{width:100%;border-collapse:collapse;font-size:10px;margin-top:8px}
.csv-preview th{background:#fef3c7;padding:4px 6px;border:1px solid #fcd34d;font-weight:600;
  font-size:9px;text-transform:uppercase}
.csv-preview td{border:1px solid #e2e8f0;padding:3px 6px;font-size:10px}
.csv-preview tr:nth-child(even){background:#fefce8}

/* ── Status badges ── */
.badge{display:inline-block;padding:1px 8px;border-radius:10px;font-size:9px;font-weight:600}
.badge-active{background:#d1fae5;color:#065f46}
.badge-closed{background:#fee2e2;color:#991b1b}
.badge-pending{background:#fef3c7;color:#92400e}

/* ── Action buttons in rows ── */
.row-actions{display:flex;gap:3px;justify-content:center}
.row-actions button{font-size:9px;padding:2px 6px;border:none;border-radius:4px;
  cursor:pointer;font-weight:600}
.row-actions .view-btn{background:#dbeafe;color:#1d4ed8}
.row-actions .view-btn:hover{background:#bfdbfe}
.row-actions .del-btn{background:#fee2e2;color:#dc2626}
.row-actions .del-btn:hover{background:#fecaca}

/* ── Expand toggle ── */
.expand-toggle{cursor:pointer;font-size:12px;color:#3b82f6;font-weight:700;
  user-select:none;display:inline-block;width:18px;text-align:center;transition:transform .2s}
.expand-toggle.open{transform:rotate(90deg)}

/* ── Print ── */
@media print {
  .toolbar,.summary-bar,.title-bar,.row-actions,.expand-toggle{display:none!important}
  .main-window{margin:0;border:none;box-shadow:none;height:auto;overflow:visible}
  .grid-container{margin:0;border:none;box-shadow:none;overflow:visible}
  .grid-scroll{overflow:visible;max-height:none}
  table.orders thead th{background:#1a6da3!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
body{font-size:15px}
.title-bar{font-size:16px}
.toolbar label{font-size:14px}
.toolbar input,.toolbar select{font-size:13px;height:32px}
.btn{font-size:13px;height:32px}
.summary-bar{font-size:13px}
table.orders{font-size:13px}
table.orders thead th{font-size:12px;padding:6px 6px}
table.orders tbody td{padding:5px 6px}
</style>
@include('partials.print-layout-head')
</head>
<body>

<div class="main-window">
  <!-- Title -->
  <div class="title-bar">
    <div class="icon"></div>
    Order Updation From Other Jewelleries
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <label>Jewellery Code</label>
    <input type="text" id="fJewlcode" class="md" placeholder="Filter...">
    <div class="sep"></div>
    <label>From</label>
    <input type="date" id="fFrom" class="md">
    <label>To</label>
    <input type="date" id="fTo" class="md">
    <div class="sep"></div>
    <button class="btn btn-primary" onclick="loadOrders()" title="Refresh list">&#8635; Refresh</button>
    <button class="btn btn-amber" onclick="openImportModal()" title="Import orders from CSV file">&#8613; Import CSV</button>
    <button class="btn btn-success" onclick="openManualModal()" title="Add order manually">+ Add Order</button>
    <div class="sep"></div>
    <button class="btn btn-outline" onclick="window.print()" title="Print list">&#9113; Print</button>
  </div>

  <!-- Summary bar -->
  <div class="summary-bar">
    <div class="stat"><span class="lbl">Total Orders:</span><span class="val blue" id="statTotal">0</span></div>
    <div class="stat"><span class="lbl">Total Amount:</span><span class="val green" id="statAmount">0.00</span></div>
    <div class="stat"><span class="lbl">Active:</span><span class="val green" id="statActive">0</span></div>
    <div class="stat"><span class="lbl">Closed:</span><span class="val amber" id="statClosed">0</span></div>
  </div>

  <!-- Data grid -->
  <div class="grid-container">
    <div class="grid-scroll">
      <table class="orders" id="ordersTable">
        <thead>
          <tr>
            <th style="width:24px"></th>
            <th>S.No</th>
            <th>Order No</th>
            <th>Date</th>
            <th>Jewl.Code</th>
            <th>Customer</th>
            <th>Due Date</th>
            <th>Rate</th>
            <th>Bill Amt</th>
            <th>Status</th>
            <th>SM</th>
            <th style="width:70px">Actions</th>
          </tr>
        </thead>
        <tbody id="ordersBody">
          <tr><td colspan="12">
            <div class="empty-state">
              <div class="icon-lg">&#128230;</div>
              <div class="msg">No orders loaded</div>
              <div class="sub">Click "Refresh" to load orders from other jewelleries, or "Import CSV" to upload</div>
            </div>
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══ Import CSV Modal ═══ -->
<div class="modal-overlay" id="importModal">
  <div class="modal-box" style="min-width:700px">
    <div class="modal-header">
      <span>&#8613; Import Orders from CSV</span>
      <button class="close-btn" onclick="closeModal('importModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div style="margin-bottom:12px">
        <label style="font-size:11px;font-weight:600;color:#64748b">Select CSV File:</label>
        <input type="file" id="csvFileInput" accept=".csv,.txt"
          style="font-size:11px;margin-left:8px;margin-top:4px">
        <button class="btn btn-primary" style="margin-left:8px" onclick="parseCSV()">Parse File</button>
      </div>
      <div style="font-size:10px;color:#94a3b8;margin-bottom:8px">
        Expected columns: ordno, tdate, custcode, custname, duedate, rate, billamt, jewlcode, smcode, code, qty, weight, amount
      </div>
      <div id="csvPreviewArea" style="max-height:400px;overflow:auto"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('importModal')">Cancel</button>
      <button class="btn btn-success" id="btnSaveImport" onclick="saveImport()" disabled>Update / Save All</button>
    </div>
  </div>
</div>

<!-- ═══ Manual Order Modal ═══ -->
<div class="modal-overlay" id="manualModal">
  <div class="modal-box" style="min-width:620px">
    <div class="modal-header">
      <span>+ Add Order from Jewellery</span>
      <button class="close-btn" onclick="closeModal('manualModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="form-group">
          <label>Jewellery Code *</label>
          <input type="text" id="mJewlcode" placeholder="e.g. JWL001">
        </div>
        <div class="form-group">
          <label>Customer Code</label>
          <input type="text" id="mCustcode">
        </div>
        <div class="form-group">
          <label>Customer Name *</label>
          <input type="text" id="mCustname">
        </div>
        <div class="form-group">
          <label>Salesman Code</label>
          <input type="text" id="mSmcode">
        </div>
        <div class="form-group">
          <label>Order Date</label>
          <input type="date" id="mTdate">
        </div>
        <div class="form-group">
          <label>Due Date</label>
          <input type="date" id="mDuedate">
        </div>
        <div class="form-group">
          <label>Gold Rate</label>
          <input type="number" id="mRate" step="0.01">
        </div>
        <div class="form-group">
          <label>&nbsp;</label>
          <span style="font-size:10px;color:#94a3b8">Auto-filled from today's rate</span>
        </div>
      </div>

      <div style="margin-top:12px;font-weight:600;font-size:11px;color:#1e293b;margin-bottom:4px">Order Items</div>
      <table class="item-entry-table" id="manualItemsTable">
        <thead>
          <tr>
            <th style="width:25px">#</th>
            <th style="width:90px">Item Code</th>
            <th style="width:50px">Qty</th>
            <th style="width:70px">Weight</th>
            <th style="width:70px">Stone Wgt</th>
            <th style="width:80px">Stone Price</th>
            <th style="width:70px">Wastage</th>
            <th style="width:80px">M.Charge</th>
            <th style="width:90px">Amount</th>
            <th style="width:30px"></th>
          </tr>
        </thead>
        <tbody id="manualItemsBody"></tbody>
      </table>
      <button class="btn btn-outline" style="margin-top:6px;font-size:10px" onclick="addManualItemRow()">+ Add Row</button>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('manualModal')">Cancel</button>
      <button class="btn btn-success" onclick="saveManualOrder()">Save Order</button>
    </div>
  </div>
</div>

<!-- ═══ Detail View Modal ═══ -->
<div class="modal-overlay" id="detailModal">
  <div class="modal-box" style="min-width:600px">
    <div class="modal-header">
      <span>Order Details</span>
      <button class="close-btn" onclick="closeModal('detailModal')">&times;</button>
    </div>
    <div class="modal-body" id="detailBody"></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('detailModal')">Close</button>
    </div>
  </div>
</div>

<script>
var BASE = @json(request()->root());
var ORDERS = [];
var CSV_DATA = [];

// ─── CSRF ────────────────────────────────────────────────────────────────────
function csrfToken(){ return document.querySelector('meta[name="csrf-token"]').content; }

function ajax(method, url, data, cb) {
  var xhr = new XMLHttpRequest();
  xhr.open(method, BASE + url, true);
  xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
  xhr.setRequestHeader('Accept', 'application/json');
  xhr.onload = function() {
    try { cb(JSON.parse(xhr.responseText)); }
    catch(e) { cb({ ok: false, message: 'Parse error' }); }
  };
  xhr.onerror = function() { cb({ ok: false, message: 'Network error' }); };
  if (data instanceof FormData) {
    xhr.send(data);
  } else if (data) {
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.send(JSON.stringify(data));
  } else {
    xhr.send();
  }
}

// ─── Init ────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  var today = new Date().toISOString().slice(0,10);
  document.getElementById('mTdate').value = today;
  loadOrders();
});

// ─── Load orders ─────────────────────────────────────────────────────────────
function loadOrders() {
  var jewlcode = document.getElementById('fJewlcode').value.trim();
  var from     = document.getElementById('fFrom').value;
  var to       = document.getElementById('fTo').value;
  var qs = '?jewlcode=' + encodeURIComponent(jewlcode)
         + '&from=' + encodeURIComponent(from)
         + '&to='   + encodeURIComponent(to);

  ajax('GET', '/api/order-update/list' + qs, null, function(d) {
    if (!d.ok) { alert(d.message || 'Failed to load'); return; }
    ORDERS = d.orders || [];
    renderOrders();
  });
}

function renderOrders() {
  var tb = document.getElementById('ordersBody');
  if (ORDERS.length === 0) {
    tb.innerHTML = '<tr><td colspan="12"><div class="empty-state">'
      + '<div class="icon-lg">&#128230;</div>'
      + '<div class="msg">No orders found</div>'
      + '<div class="sub">Adjust filters or import new orders</div></div></td></tr>';
    updateSummary();
    return;
  }

  var html = '';
  for (var i = 0; i < ORDERS.length; i++) {
    var o = ORDERS[i];
    var statusBadge = o.closed ? '<span class="badge badge-closed">Closed</span>'
                    : o.status ? '<span class="badge badge-active">Active</span>'
                    : '<span class="badge badge-pending">Pending</span>';
    var hasItems = o.items && o.items.length > 0;

    html += '<tr data-idx="' + i + '" ondblclick="showDetail(' + i + ')">'
      + '<td><span class="expand-toggle" onclick="toggleDetail(this,' + i + ')">&#9654;</span></td>'
      + '<td>' + (i+1) + '</td>'
      + '<td class="mono">' + esc(o.ordno) + '</td>'
      + '<td>' + fmtDate(o.tdate) + '</td>'
      + '<td>' + esc(o.jewlcode) + '</td>'
      + '<td class="left">' + esc(o.custname) + '</td>'
      + '<td>' + fmtDate(o.duedate) + '</td>'
      + '<td class="right">' + fmtNum(o.rate) + '</td>'
      + '<td class="right mono">' + fmtNum(o.billamt) + '</td>'
      + '<td>' + statusBadge + '</td>'
      + '<td>' + esc(o.smcode) + '</td>'
      + '<td><div class="row-actions">'
      +   '<button class="view-btn" onclick="showDetail(' + i + ')">View</button>'
      +   '<button class="del-btn" onclick="deleteOrder(' + o.slno + ')">Del</button>'
      + '</div></td>'
      + '</tr>';

    // detail row
    html += '<tr class="detail-row" id="detail-' + i + '"><td colspan="12">';
    if (hasItems) {
      html += '<table class="detail-items"><thead><tr>'
        + '<th>#</th><th>Code</th><th>Qty</th><th>Weight</th><th>Stone Wgt</th>'
        + '<th>Stone Price</th><th>Wastage</th><th>M.Charge</th><th>Rate</th><th>Amount</th><th>Part</th>'
        + '</tr></thead><tbody>';
      for (var j = 0; j < o.items.length; j++) {
        var it = o.items[j];
        html += '<tr>'
          + '<td>' + (j+1) + '</td>'
          + '<td>' + esc(it.code) + '</td>'
          + '<td>' + it.qty + '</td>'
          + '<td class="right">' + fmtNum(it.weight, 3) + '</td>'
          + '<td class="right">' + fmtNum(it.stonewgt, 3) + '</td>'
          + '<td class="right">' + fmtNum(it.stoneprice) + '</td>'
          + '<td class="right">' + fmtNum(it.wastage) + '</td>'
          + '<td class="right">' + fmtNum(it.mcharge) + '</td>'
          + '<td class="right">' + fmtNum(it.rate) + '</td>'
          + '<td class="right">' + fmtNum(it.amount) + '</td>'
          + '<td>' + esc(it.part) + '</td>'
          + '</tr>';
      }
      html += '</tbody></table>';
    } else {
      html += '<span style="color:#94a3b8;font-size:11px">No item details</span>';
    }
    html += '</td></tr>';
  }
  tb.innerHTML = html;
  updateSummary();
}

function updateSummary() {
  var total = ORDERS.length;
  var amt = 0, active = 0, closed = 0;
  for (var i = 0; i < ORDERS.length; i++) {
    amt += ORDERS[i].billamt || 0;
    if (ORDERS[i].closed) closed++;
    else active++;
  }
  document.getElementById('statTotal').textContent  = total;
  document.getElementById('statAmount').textContent = fmtNum(amt);
  document.getElementById('statActive').textContent = active;
  document.getElementById('statClosed').textContent = closed;
}

function toggleDetail(el, idx) {
  var row = document.getElementById('detail-' + idx);
  if (!row) return;
  var open = row.classList.toggle('open');
  el.classList.toggle('open', open);
}

// ─── Detail modal ────────────────────────────────────────────────────────────
function showDetail(idx) {
  var o = ORDERS[idx];
  if (!o) return;
  var html = '<div style="font-size:12px">'
    + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;margin-bottom:12px">'
    + '<div><span style="color:#64748b;font-weight:600">Order No:</span> ' + esc(o.ordno) + '</div>'
    + '<div><span style="color:#64748b;font-weight:600">Date:</span> ' + fmtDate(o.tdate) + '</div>'
    + '<div><span style="color:#64748b;font-weight:600">Jewl Code:</span> ' + esc(o.jewlcode) + '</div>'
    + '<div><span style="color:#64748b;font-weight:600">Customer:</span> ' + esc(o.custname) + ' (' + esc(o.custcode) + ')</div>'
    + '<div><span style="color:#64748b;font-weight:600">Due Date:</span> ' + fmtDate(o.duedate) + '</div>'
    + '<div><span style="color:#64748b;font-weight:600">Rate:</span> ' + fmtNum(o.rate) + '</div>'
    + '<div><span style="color:#64748b;font-weight:600">Bill Amt:</span> ' + fmtNum(o.billamt) + '</div>'
    + '<div><span style="color:#64748b;font-weight:600">Salesman:</span> ' + esc(o.smcode) + '</div>'
    + '</div>';

  if (o.items && o.items.length > 0) {
    html += '<div style="font-weight:600;margin-bottom:4px">Items (' + o.items.length + ')</div>'
      + '<table class="detail-items"><thead><tr>'
      + '<th>#</th><th>Code</th><th>Qty</th><th>Weight</th><th>Stone Wgt</th>'
      + '<th>Stone Price</th><th>Wastage</th><th>M.Charge</th><th>Rate</th><th>Amount</th><th>Part</th>'
      + '</tr></thead><tbody>';
    for (var j = 0; j < o.items.length; j++) {
      var it = o.items[j];
      html += '<tr>'
        + '<td>' + (j+1) + '</td><td>' + esc(it.code) + '</td><td>' + it.qty + '</td>'
        + '<td class="right">' + fmtNum(it.weight,3) + '</td>'
        + '<td class="right">' + fmtNum(it.stonewgt,3) + '</td>'
        + '<td class="right">' + fmtNum(it.stoneprice) + '</td>'
        + '<td class="right">' + fmtNum(it.wastage) + '</td>'
        + '<td class="right">' + fmtNum(it.mcharge) + '</td>'
        + '<td class="right">' + fmtNum(it.rate) + '</td>'
        + '<td class="right">' + fmtNum(it.amount) + '</td>'
        + '<td>' + esc(it.part) + '</td></tr>';
    }
    html += '</tbody></table>';
  }
  html += '</div>';
  document.getElementById('detailBody').innerHTML = html;
  openModal('detailModal');
}

// ─── Delete ──────────────────────────────────────────────────────────────────
function deleteOrder(slno) {
  if (!confirm('Delete this order? This cannot be undone.')) return;
  ajax('POST', '/api/order-update/delete', { slno: slno }, function(d) {
    if (d.ok) { loadOrders(); }
    else { alert(d.message || 'Delete failed'); }
  });
}

// ─── CSV Import ──────────────────────────────────────────────────────────────
function openImportModal() {
  CSV_DATA = [];
  document.getElementById('csvPreviewArea').innerHTML = '';
  document.getElementById('csvFileInput').value = '';
  document.getElementById('btnSaveImport').disabled = true;
  openModal('importModal');
}

function parseCSV() {
  var fi = document.getElementById('csvFileInput');
  if (!fi.files || !fi.files[0]) { alert('Select a CSV file first'); return; }

  var fd = new FormData();
  fd.append('csv_file', fi.files[0]);

  ajax('POST', '/api/order-update/import-csv', fd, function(d) {
    if (!d.ok) { alert(d.message || 'Parse failed'); return; }
    CSV_DATA = d.rows || [];
    var headers = d.headers || [];
    if (CSV_DATA.length === 0) { alert('No data rows found'); return; }

    // Render preview
    var html = '<div style="font-size:11px;font-weight:600;margin-bottom:4px">'
      + CSV_DATA.length + ' row(s) found</div>'
      + '<div style="max-height:350px;overflow:auto">'
      + '<table class="csv-preview"><thead><tr>';
    for (var h = 0; h < headers.length; h++) {
      html += '<th>' + esc(headers[h]) + '</th>';
    }
    html += '</tr></thead><tbody>';
    for (var i = 0; i < CSV_DATA.length && i < 100; i++) {
      html += '<tr>';
      for (var h = 0; h < headers.length; h++) {
        html += '<td>' + esc(CSV_DATA[i][headers[h]] || '') + '</td>';
      }
      html += '</tr>';
    }
    if (CSV_DATA.length > 100) {
      html += '<tr><td colspan="' + headers.length + '" style="text-align:center;color:#94a3b8">'
        + '... and ' + (CSV_DATA.length - 100) + ' more rows</td></tr>';
    }
    html += '</tbody></table></div>';
    document.getElementById('csvPreviewArea').innerHTML = html;
    document.getElementById('btnSaveImport').disabled = false;
  });
}

function saveImport() {
  if (CSV_DATA.length === 0) { alert('No data to import'); return; }
  if (!confirm('Import ' + CSV_DATA.length + ' order(s)? Existing orders will be skipped.')) return;

  // Group CSV rows by ordno/slno into orders with items
  var grouped = {};
  for (var i = 0; i < CSV_DATA.length; i++) {
    var r = CSV_DATA[i];
    var key = r.ordno || r.slno || ('row_' + i);
    if (!grouped[key]) {
      grouped[key] = {
        slno:     parseInt(r.slno) || 0,
        ordno:    r.ordno    || '',
        tdate:    r.tdate    || '',
        custcode: r.custcode || '',
        custname: r.custname || '',
        duedate:  r.duedate  || '',
        rate:     parseFloat(r.rate)    || 0,
        billamt:  parseFloat(r.billamt) || 0,
        status:   parseInt(r.status)    || 1,
        control:  parseInt(r.control)   || 1,
        smcode:   r.smcode   || '',
        jewlcode: r.jewlcode || '',
        items:    [],
      };
    }
    if (r.code && r.code.trim() !== '') {
      grouped[key].items.push({
        code:       r.code       || '',
        qty:        parseInt(r.qty)          || 0,
        weight:     parseFloat(r.weight)     || 0,
        stonewgt:   parseFloat(r.stonewgt)   || 0,
        stoneprice: parseFloat(r.stoneprice) || 0,
        wastage:    parseFloat(r.wastage)    || 0,
        mcharge:    parseFloat(r.mcharge)    || 0,
        rate:       parseFloat(r.rate)       || 0,
        amount:     parseFloat(r.amount)     || 0,
        part:       r.part || '',
        iqtype:     r.iqtype || '',
      });
    }
  }

  var ordersArr = [];
  for (var k in grouped) { ordersArr.push(grouped[k]); }

  document.getElementById('btnSaveImport').disabled = true;
  ajax('POST', '/api/order-update/save-import', { orders: ordersArr }, function(d) {
    document.getElementById('btnSaveImport').disabled = false;
    if (d.ok) {
      alert(d.message || 'Import complete');
      closeModal('importModal');
      loadOrders();
    } else {
      alert(d.message || 'Import failed');
    }
  });
}

// ─── Manual Order ────────────────────────────────────────────────────────────
function openManualModal() {
  document.getElementById('mJewlcode').value = '';
  document.getElementById('mCustcode').value = '';
  document.getElementById('mCustname').value = '';
  document.getElementById('mSmcode').value   = '';
  document.getElementById('mDuedate').value  = '';
  document.getElementById('mRate').value     = '';
  var today = new Date().toISOString().slice(0,10);
  document.getElementById('mTdate').value = today;
  document.getElementById('manualItemsBody').innerHTML = '';
  addManualItemRow();
  addManualItemRow();
  addManualItemRow();
  openModal('manualModal');
}

var manualItemSno = 0;
function addManualItemRow() {
  manualItemSno++;
  var n = manualItemSno;
  var tb = document.getElementById('manualItemsBody');
  var tr = document.createElement('tr');
  tr.id = 'mirow-' + n;
  tr.innerHTML = '<td style="text-align:center;color:#94a3b8">' + n + '</td>'
    + '<td><input type="text" id="mi_code_' + n + '" style="text-transform:uppercase"></td>'
    + '<td><input type="number" id="mi_qty_' + n + '" value="1" class="right"></td>'
    + '<td><input type="number" id="mi_wgt_' + n + '" step="0.001" class="right"></td>'
    + '<td><input type="number" id="mi_swgt_' + n + '" step="0.001" class="right"></td>'
    + '<td><input type="number" id="mi_sp_' + n + '" step="0.01" class="right"></td>'
    + '<td><input type="number" id="mi_waste_' + n + '" step="0.01" class="right"></td>'
    + '<td><input type="number" id="mi_mc_' + n + '" step="0.01" class="right"></td>'
    + '<td><input type="number" id="mi_amt_' + n + '" step="0.01" class="right"></td>'
    + '<td><button style="font-size:9px;border:none;background:#fee2e2;color:#dc2626;'
    + 'border-radius:4px;cursor:pointer;padding:2px 5px" onclick="removeManualRow(' + n + ')">x</button></td>';
  tb.appendChild(tr);
}

function removeManualRow(n) {
  var r = document.getElementById('mirow-' + n);
  if (r) r.remove();
}

function saveManualOrder() {
  var order = {
    jewlcode: document.getElementById('mJewlcode').value.trim(),
    custcode: document.getElementById('mCustcode').value.trim(),
    custname: document.getElementById('mCustname').value.trim(),
    smcode:   document.getElementById('mSmcode').value.trim(),
    tdate:    document.getElementById('mTdate').value,
    duedate:  document.getElementById('mDuedate').value,
    rate:     parseFloat(document.getElementById('mRate').value) || 0,
  };

  if (!order.jewlcode) { alert('Jewellery Code is required'); return; }
  if (!order.custname) { alert('Customer Name is required'); return; }

  var items = [];
  var rows = document.getElementById('manualItemsBody').querySelectorAll('tr');
  rows.forEach(function(tr) {
    var id = tr.id.replace('mirow-', '');
    var code = (document.getElementById('mi_code_' + id) || {}).value || '';
    if (code.trim() === '') return;
    items.push({
      code:       code.trim().toUpperCase(),
      qty:        parseInt((document.getElementById('mi_qty_' + id) || {}).value) || 1,
      weight:     parseFloat((document.getElementById('mi_wgt_' + id) || {}).value) || 0,
      stonewgt:   parseFloat((document.getElementById('mi_swgt_' + id) || {}).value) || 0,
      stoneprice: parseFloat((document.getElementById('mi_sp_' + id) || {}).value) || 0,
      wastage:    parseFloat((document.getElementById('mi_waste_' + id) || {}).value) || 0,
      mcharge:    parseFloat((document.getElementById('mi_mc_' + id) || {}).value) || 0,
      amount:     parseFloat((document.getElementById('mi_amt_' + id) || {}).value) || 0,
    });
  });

  if (items.length === 0) { alert('Add at least one item'); return; }

  ajax('POST', '/api/order-update/save-manual', { order: order, items: items }, function(d) {
    if (d.ok) {
      alert((d.message || 'Saved') + '\nOrder No: ' + (d.ordno || ''));
      closeModal('manualModal');
      loadOrders();
    } else {
      alert(d.message || 'Save failed');
    }
  });
}

// ─── Modal helpers ───────────────────────────────────────────────────────────
function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

// ─── Formatting helpers ──────────────────────────────────────────────────────
function esc(s) {
  if (s === null || s === undefined) return '';
  var d = document.createElement('div');
  d.textContent = String(s);
  return d.innerHTML;
}

function fmtNum(n, dec) {
  n = parseFloat(n) || 0;
  dec = dec || 2;
  return n.toLocaleString('en-IN', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}

function fmtDate(d) {
  if (!d) return '';
  var s = String(d).slice(0, 10);
  if (s.length < 10) return s;
  var parts = s.split('-');
  if (parts.length === 3) return parts[2] + '/' + parts[1] + '/' + parts[0];
  return s;
}

// ─── Keyboard shortcuts ──────────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
    e.preventDefault();
    loadOrders();
  }
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(function(m) {
      m.classList.remove('open');
    });
  }
});
</script>
</body>
</html>
