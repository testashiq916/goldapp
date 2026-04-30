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
  overflow:hidden;height:100vh;
  background:radial-gradient(circle at 30% 20%,#eef3ff 0%,#f4f7fb 40%,#edf2f8 100%);
  display:flex;align-items:center;justify-content:center}

/* ── Card ── */
.card{background:#fff;border:1px solid #d6dcea;border-radius:14px;
  box-shadow:0 12px 40px rgba(33,52,89,.12);width:520px;overflow:hidden;
  animation:slideIn .3s ease}
@keyframes slideIn{from{opacity:0;transform:translateY(-16px)}to{opacity:1;transform:translateY(0)}}

.card-header{background:linear-gradient(135deg,#0e4d76,#1a6da3);color:#fff;
  padding:12px 18px;font-weight:700;font-size:14px;display:flex;align-items:center;gap:8px}
.card-header .icon{width:18px;height:18px;background:#38bdf8;border-radius:5px;flex-shrink:0}

.card-body{padding:20px 24px}
.card-footer{padding:12px 24px;border-top:1px solid #e2e8f0;background:#f8fafc;
  display:flex;justify-content:flex-end;gap:8px}

/* ── Form row ── */
.form-row{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.form-row label{font-weight:600;font-size:12px;color:#475569;min-width:110px}
.form-row input[type="text"]{flex:1;font-size:12px;padding:6px 10px;border:1px solid #d6dcea;
  border-radius:8px;height:34px;text-transform:uppercase;color:#1e293b;
  box-shadow:inset 0 1px 2px rgba(17,24,39,.04)}
.form-row input[type="text"]:focus{border-color:#60a5fa;
  box-shadow:0 0 0 3px rgba(59,130,246,.15);outline:none}

/* ── Toggle switch ── */
.toggle-row{display:flex;align-items:center;gap:12px;margin-bottom:16px;
  padding:10px 14px;border-radius:10px;background:#f0f6ff;border:1px solid #dbeafe}
.toggle-row .toggle-label{font-weight:600;font-size:12px;color:#475569;min-width:110px}
.toggle-switch{position:relative;width:52px;height:28px;cursor:pointer}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:#cbd5e1;border-radius:14px;transition:.3s}
.toggle-slider::before{content:'';position:absolute;left:3px;top:3px;width:22px;height:22px;
  background:#fff;border-radius:50%;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,.15)}
.toggle-switch input:checked + .toggle-slider{background:#dc2626}
.toggle-switch input:checked + .toggle-slider::before{transform:translateX(24px)}
.toggle-status{font-size:12px;font-weight:700;padding:3px 10px;border-radius:6px}
.toggle-status.blocked{background:#fee2e2;color:#dc2626}
.toggle-status.unblocked{background:#d1fae5;color:#059669}

/* ── Info panel ── */
.info-panel{display:none;margin-bottom:14px;padding:12px 14px;border-radius:10px;
  background:#f8fafc;border:1px solid #e2e8f0}
.info-panel.show{display:block}
.info-panel .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;font-size:11px}
.info-panel .info-grid .lbl{color:#64748b;font-weight:600}
.info-panel .info-grid .val{color:#1e293b;font-weight:500}
.info-panel .info-title{font-weight:700;font-size:12px;color:#1e293b;margin-bottom:8px;
  display:flex;align-items:center;gap:6px}
.info-panel .current-status{display:inline-block;padding:2px 8px;border-radius:6px;
  font-size:10px;font-weight:700}
.info-panel .current-status.blocked{background:#fee2e2;color:#dc2626}
.info-panel .current-status.unblocked{background:#d1fae5;color:#059669}

/* ── Buttons ── */
.btn{font-size:12px;padding:6px 18px;border:1px solid #d6dcea;border-radius:8px;
  cursor:pointer;font-weight:600;height:34px;display:inline-flex;align-items:center;gap:5px;
  transition:all .2s ease}
.btn-primary{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none}
.btn-primary:hover{background:linear-gradient(135deg,#1d4ed8,#1e40af);transform:translateY(-1px);
  box-shadow:0 3px 10px rgba(37,99,235,.25)}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}
.btn-outline{background:#fff;color:#374151}
.btn-outline:hover{background:#f0f6ff;border-color:#93c5fd}
.btn-help{background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff;border:none;
  padding:6px 12px;font-size:11px}
.btn-help:hover{background:linear-gradient(135deg,#6d28d9,#5b21b6);transform:translateY(-1px)}

/* ── Search modal ── */
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:1000;
  display:none;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.25);
  width:600px;max-height:70vh;overflow:hidden;display:flex;flex-direction:column;
  animation:modalIn .25s ease}
@keyframes modalIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
.modal-header{padding:10px 14px;background:linear-gradient(135deg,#7c3aed,#6d28d9);
  color:#fff;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:space-between}
.modal-header .close-btn{background:none;border:none;color:#fff;font-size:18px;
  cursor:pointer;padding:0 4px;opacity:.7}
.modal-header .close-btn:hover{opacity:1}
.modal-search{padding:10px 14px;border-bottom:1px solid #e2e8f0;display:flex;gap:8px}
.modal-search input{flex:1;font-size:11px;padding:4px 10px;border:1px solid #d6dcea;
  border-radius:6px;height:30px}
.modal-search input:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12);outline:none}
.modal-list{flex:1;overflow-y:auto;padding:4px 0}
.modal-list .row{display:grid;grid-template-columns:100px 1fr 90px 50px;gap:6px;
  padding:7px 14px;font-size:11px;cursor:pointer;border-bottom:1px solid #f1f5f9;
  transition:background .1s}
.modal-list .row:hover{background:#f5f3ff}
.modal-list .row.sel{background:#ede9fe}
.modal-list .row .ordno{font-weight:600;color:#1e293b;font-family:Consolas,monospace}
.modal-list .row .name{color:#475569}
.modal-list .row .amt{text-align:right;font-weight:500;color:#1e293b}
.modal-list .row .blk{text-align:center;font-size:9px;font-weight:700}
.modal-list .row .blk.yes{color:#dc2626}
.modal-list .row .blk.no{color:#059669}
</style>
</head>
<body>

<div class="card">
  <div class="card-header">
    <div class="icon"></div>
    Order Block Entry
  </div>

  <div class="card-body">
    <!-- Order number input -->
    <div class="form-row">
      <label>Enter Order No :</label>
      <input type="text" id="txtOrdno" placeholder="Type order number..." autofocus
        onkeydown="if(event.key==='Enter'){lookupOrder(); event.preventDefault();}">
      <button class="btn btn-help" onclick="openHelp()" title="Search orders (F1)">? Help</button>
    </div>

    <!-- Info panel (shown after lookup) -->
    <div class="info-panel" id="infoPanel">
      <div class="info-title">
        Order Found
        <span class="current-status" id="infoStatus"></span>
      </div>
      <div class="info-grid">
        <div><span class="lbl">Order No:</span> <span class="val" id="infoOrdno"></span></div>
        <div><span class="lbl">Date:</span> <span class="val" id="infoDate"></span></div>
        <div><span class="lbl">Customer:</span> <span class="val" id="infoCust"></span></div>
        <div><span class="lbl">Bill Amt:</span> <span class="val" id="infoAmt"></span></div>
        <div><span class="lbl">Due Date:</span> <span class="val" id="infoDue"></span></div>
        <div><span class="lbl">Status:</span> <span class="val" id="infoStat"></span></div>
      </div>
    </div>

    <!-- Block toggle -->
    <div class="toggle-row">
      <span class="toggle-label">Action :</span>
      <label class="toggle-switch">
        <input type="checkbox" id="chkBlock" checked onchange="updateToggleLabel()">
        <span class="toggle-slider"></span>
      </label>
      <span class="toggle-status blocked" id="toggleLabel">Block</span>
    </div>
  </div>

  <div class="card-footer">
    <button class="btn btn-outline" onclick="resetForm()">Reset</button>
    <button class="btn btn-primary" id="btnOk" onclick="doBlockUnblock()" disabled>OK</button>
  </div>
</div>

<!-- ═══ Search Modal ═══ -->
<div class="modal-overlay" id="helpModal">
  <div class="modal-box">
    <div class="modal-header">
      <span>Search Orders</span>
      <button class="close-btn" onclick="closeHelp()">&times;</button>
    </div>
    <div class="modal-search">
      <input type="text" id="helpSearch" placeholder="Search by order no, customer name or code..."
        onkeyup="searchOrders()" onkeydown="if(event.key==='Escape') closeHelp();">
    </div>
    <div class="modal-list" id="helpList">
      <div style="text-align:center;padding:30px;color:#94a3b8">Type to search...</div>
    </div>
  </div>
</div>

<script>
var BASE = @json(request()->root());
var CURRENT_ORDER = null;

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
  if (data) {
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.send(JSON.stringify(data));
  } else {
    xhr.send();
  }
}

// ─── Toggle label ────────────────────────────────────────────────────────────
function updateToggleLabel() {
  var chk = document.getElementById('chkBlock');
  var lbl = document.getElementById('toggleLabel');
  if (chk.checked) {
    lbl.textContent = 'Block';
    lbl.className = 'toggle-status blocked';
  } else {
    lbl.textContent = 'Unblock';
    lbl.className = 'toggle-status unblocked';
  }
}

// ─── Lookup order ────────────────────────────────────────────────────────────
function lookupOrder() {
  var ordno = document.getElementById('txtOrdno').value.trim();
  if (ordno === '') { alert('Enter an order number'); return; }

  ajax('GET', '/api/order-block/get?ordno=' + encodeURIComponent(ordno), null, function(d) {
    if (!d.ok) {
      alert(d.message || 'Order not found');
      document.getElementById('infoPanel').classList.remove('show');
      document.getElementById('btnOk').disabled = true;
      CURRENT_ORDER = null;
      return;
    }

    CURRENT_ORDER = d;
    showOrderInfo(d);
    document.getElementById('btnOk').disabled = false;

    // Set toggle to opposite of current state (block if unblocked, unblock if blocked)
    var chk = document.getElementById('chkBlock');
    chk.checked = d.blocked !== 'Y'; // if currently blocked, suggest unblock
    updateToggleLabel();
  });
}

function showOrderInfo(d) {
  document.getElementById('infoPanel').classList.add('show');
  document.getElementById('infoOrdno').textContent = d.ordno;
  document.getElementById('infoDate').textContent = fmtDate(d.tdate);
  document.getElementById('infoCust').textContent = d.custname + (d.custcode ? ' (' + d.custcode + ')' : '');
  document.getElementById('infoAmt').textContent = fmtNum(d.billamt);
  document.getElementById('infoDue').textContent = fmtDate(d.duedate);
  document.getElementById('infoStat').textContent = d.status === 2 ? 'Returned' : d.status === 1 ? 'Active' : 'Pending';

  var st = document.getElementById('infoStatus');
  if (d.blocked === 'Y') {
    st.textContent = 'Currently Blocked';
    st.className = 'current-status blocked';
  } else {
    st.textContent = 'Not Blocked';
    st.className = 'current-status unblocked';
  }
}

// ─── Block/Unblock ───────────────────────────────────────────────────────────
function doBlockUnblock() {
  if (!CURRENT_ORDER) { alert('Look up an order first'); return; }

  var block = document.getElementById('chkBlock').checked;
  var action = block ? 'Block' : 'Unblock';

  if (!confirm(action + ' order ' + CURRENT_ORDER.ordno + '?')) return;

  ajax('POST', '/api/order-block/toggle', {
    ordno: CURRENT_ORDER.ordno,
    block: block
  }, function(d) {
    if (d.ok) {
      alert(d.message || 'Done');
      // Update info panel
      CURRENT_ORDER.blocked = d.blocked;
      showOrderInfo(CURRENT_ORDER);
      // Set toggle to opposite for next action
      var chk = document.getElementById('chkBlock');
      chk.checked = d.blocked !== 'Y';
      updateToggleLabel();
    } else {
      alert(d.message || 'Failed');
    }
  });
}

// ─── Reset ───────────────────────────────────────────────────────────────────
function resetForm() {
  document.getElementById('txtOrdno').value = '';
  document.getElementById('infoPanel').classList.remove('show');
  document.getElementById('btnOk').disabled = true;
  document.getElementById('chkBlock').checked = true;
  updateToggleLabel();
  CURRENT_ORDER = null;
  document.getElementById('txtOrdno').focus();
}

// ─── Help modal ──────────────────────────────────────────────────────────────
function openHelp() {
  document.getElementById('helpModal').classList.add('open');
  document.getElementById('helpSearch').value = '';
  document.getElementById('helpList').innerHTML = '<div style="text-align:center;padding:30px;color:#94a3b8">Type to search...</div>';
  setTimeout(function() { document.getElementById('helpSearch').focus(); }, 100);
  searchOrders();
}

function closeHelp() {
  document.getElementById('helpModal').classList.remove('open');
  document.getElementById('txtOrdno').focus();
}

var searchTimer = null;
function searchOrders() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(function() {
    var q = document.getElementById('helpSearch').value.trim();
    ajax('GET', '/api/order-block/search?q=' + encodeURIComponent(q), null, function(d) {
      if (!d.ok) return;
      var orders = d.orders || [];
      if (orders.length === 0) {
        document.getElementById('helpList').innerHTML = '<div style="text-align:center;padding:30px;color:#94a3b8">No orders found</div>';
        return;
      }
      var html = '';
      for (var i = 0; i < orders.length; i++) {
        var o = orders[i];
        var blkCls = o.blocked === 'Y' ? 'blk yes' : 'blk no';
        var blkTxt = o.blocked === 'Y' ? 'BLK' : '-';
        html += '<div class="row" onclick="selectOrder(\'' + esc(o.ordno) + '\')">'
          + '<span class="ordno">' + esc(o.ordno) + '</span>'
          + '<span class="name">' + esc(o.custname) + '</span>'
          + '<span class="amt">' + fmtNum(o.billamt) + '</span>'
          + '<span class="' + blkCls + '">' + blkTxt + '</span>'
          + '</div>';
      }
      document.getElementById('helpList').innerHTML = html;
    });
  }, 250);
}

function selectOrder(ordno) {
  document.getElementById('txtOrdno').value = ordno;
  closeHelp();
  lookupOrder();
}

// ─── Keyboard shortcuts ──────────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (e.key === 'F1') {
    e.preventDefault();
    openHelp();
  }
  if (e.key === 'Escape') {
    var modal = document.getElementById('helpModal');
    if (modal.classList.contains('open')) {
      closeHelp();
    }
  }
});

// ─── Helpers ─────────────────────────────────────────────────────────────────
function esc(s) {
  if (s === null || s === undefined) return '';
  var d = document.createElement('div');
  d.textContent = String(s);
  return d.innerHTML;
}
function fmtNum(n) {
  n = parseFloat(n) || 0;
  return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtDate(d) {
  if (!d) return '';
  var s = String(d).slice(0, 10);
  if (s.length < 10) return s;
  var p = s.split('-');
  if (p.length === 3) return p[2] + '/' + p[1] + '/' + p[0];
  return s;
}
</script>
</body>
</html>
