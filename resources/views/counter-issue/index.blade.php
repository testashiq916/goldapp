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
.bar{background:#2a3f2a;color:#a8e6a0;padding:8px 12px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.toolbar{display:flex;gap:8px;align-items:center;background:#dde8dd;border:1px solid #a0c0a0;padding:8px;margin-bottom:4px}
.toolbar label{font-weight:700;font-size:13px}
.toolbar select,.toolbar input{height:30px;border:1px solid #a0c0a0;border-radius:4px;padding:0 8px;font-size:13px}
.btn{height:32px;padding:0 14px;border:1px solid #7ca87c;background:#c8e0c8;border-radius:4px;cursor:pointer;font-weight:700;font-size:12px;white-space:nowrap}
.btn:hover{background:#b0d4b0}
.btn-primary{background:#2d7d46;color:#fff;border-color:#256b3a}
.btn-primary:hover{background:#256b3a}
.btn-danger{background:#c0392b;color:#fff;border-color:#a33025}
.btn-danger:hover{background:#a33025}
.btn:disabled{opacity:.5;cursor:not-allowed}
.panels{display:grid;grid-template-columns:2fr 1fr;gap:6px}
.panel-box{border:1px solid #a0c0a0;background:#fff;border-radius:4px;overflow:hidden}
.panel-hdr{background:#2a3f2a;color:#a8e6a0;padding:6px 10px;font-weight:700;font-size:13px}
.tbl{width:100%;border-collapse:collapse;font-size:11px}
.tbl th,.tbl td{border:1px solid #ccc;padding:3px 5px}
.tbl th{background:#2a3f2a;color:#a8e6a0;font-size:11px;position:sticky;top:0;z-index:1}
.tbl td{text-align:right}
.tbl td.l{text-align:left}
.tbl tr:nth-child(even){background:#f4f9f4}
.tbl tr.sel{background:#a8e6a0 !important}
.tbl-wrap{max-height:420px;overflow:auto}
.scan-bar{display:flex;gap:6px;align-items:center;padding:6px 10px;background:#eef5ee;border-top:1px solid #a0c0a0}
.scan-bar input{flex:1;height:32px;border:1px solid #a0c0a0;border-radius:4px;padding:0 8px;font-size:14px;font-weight:700}
.footer-bar{display:flex;gap:6px;align-items:center;padding:6px;background:#dde8dd;border:1px solid #a0c0a0;margin-top:4px}
.summary-wrap{max-height:300px;overflow:auto;margin-top:4px}
.error-box{max-height:200px;overflow:auto;margin-top:4px;background:#fff3f3;border:1px solid #e0a0a0;border-radius:4px;padding:6px;font-size:12px;color:#b91c1c}
.error-box div{padding:2px 0;border-bottom:1px solid #f0d0d0}
.totals{background:#2a3f2a;color:#a8e6a0;font-weight:700}
.msg{font-weight:700;font-size:13px;margin-left:8px}
.msg.ok{color:#2d7d46}
.msg.err{color:#b91c1c}
.filter-bar{display:flex;gap:6px;align-items:center;padding:4px 10px;background:#eef5ee;border-top:1px solid #a0c0a0;font-size:12px}
.filter-bar label{font-weight:700}
.filter-bar input{height:26px;border:1px solid #a0c0a0;border-radius:4px;padding:0 6px;text-transform:uppercase;width:100px}
</style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <span>{{ $title }}</span>
    <span id="counterLabel"></span>
  </div>

  <div class="toolbar">
    <label>Counter :</label>
    <select id="counterCode">
      <option value="">-- Select Counter --</option>
      @foreach($counters as $c)
      <option value="{{ $c->code }}">{{ $c->code }} - {{ $c->name }}</option>
      @endforeach
    </select>
    <button class="btn btn-primary" id="btnOk">OK</button>
    <button class="btn btn-primary" id="btnUpdate" disabled>Update Counter</button>
    <button class="btn" id="btnSummary">Show Summary</button>
    <button class="btn btn-danger" id="btnRefresh" style="display:none">Refresh This List</button>
    <div class="msg" id="msg"></div>
  </div>

  <div class="panels">
    <!-- Left: Item List -->
    <div class="panel-box">
      <div class="panel-hdr">Item List <span id="itemCounterName"></span></div>
      <div class="scan-bar">
        <label style="font-weight:700;font-size:13px">Scan Barcode:</label>
        <input id="scanInput" type="number" placeholder="Enter barcode number..." disabled>
      </div>
      <div class="tbl-wrap" id="itemWrap">
        <table class="tbl">
          <thead><tr>
            <th style="width:80px">Barcode</th>
            <th style="width:65px">Code</th>
            <th>Item Name</th>
            <th style="width:40px">Qty</th>
            <th style="width:70px">Weight</th>
            <th style="width:65px">St.Wgt</th>
            <th style="width:65px">Net.Wgt</th>
            <th style="width:60px">DmdWgt</th>
            <th style="width:65px">Rate</th>
            <th style="width:55px">Touch</th>
            <th style="width:65px">Cost</th>
          </tr></thead>
          <tbody id="itemBody"></tbody>
          <tfoot>
            <tr class="totals">
              <td id="totCount" class="l">0 items</td>
              <td></td><td></td>
              <td id="totQty">0</td>
              <td id="totWeight">0.000</td>
              <td id="totStWgt">0.000</td>
              <td id="totNetWgt">0.000</td>
              <td id="totDmdWgt">0.00</td>
              <td></td><td></td><td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="filter-bar">
        <label>Item Code:</label>
        <input id="filterCode">
        <button class="btn" id="btnFilter">Filter</button>
        <button class="btn" id="btnSort">Sort</button>
        <button class="btn" id="btnPrintItems" disabled>Print</button>
      </div>
    </div>

    <!-- Right: Errors -->
    <div class="panel-box">
      <div class="panel-hdr">Errors / Out of Stock</div>
      <div class="error-box" id="errorBox">
        <div style="color:#888;font-style:italic">No errors yet</div>
      </div>
      <div class="panel-hdr" style="margin-top:4px">Summary (by Item Code)</div>
      <div class="summary-wrap">
        <table class="tbl" id="summaryTbl" style="display:none">
          <thead><tr>
            <th>Code</th>
            <th>Item Name</th>
            <th style="width:40px">Qty</th>
            <th style="width:70px">Weight</th>
            <th style="width:65px">St.Wgt</th>
          </tr></thead>
          <tbody id="summaryBody"></tbody>
          <tfoot>
            <tr class="totals">
              <td></td><td></td>
              <td id="sTotQty">0</td>
              <td id="sTotWeight">0.000</td>
              <td id="sTotStWgt">0.000</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div style="padding:4px 10px">
        <button class="btn" id="btnPrintSummary" disabled>Print Summary</button>
      </div>
    </div>
  </div>
</div>

<script>
const $  = id => document.getElementById(id);
const api = (u,o) => fetch(u,{...o, headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','Accept':'application/json',...(o?.headers||{})}});

let items = [];       // array of item objects in the list
let selectedCounter = '';
let counterName = '';

function showMsg(t, ok=true) {
  const el = $('msg');
  el.textContent = t;
  el.className = 'msg ' + (ok ? 'ok' : 'err');
  if (t) setTimeout(() => { if (el.textContent === t) el.textContent = ''; }, 4000);
}

/* ── Counter Select & OK ────────────────────────────────────────────────── */
$('btnOk').onclick = async () => {
  selectedCounter = $('counterCode').value;
  showMsg('Loading...');

  const r = await api(`{{ url("/api/counter-issue/items") }}?counter=${encodeURIComponent(selectedCounter)}`).then(r=>r.json());
  if (!r.ok) { showMsg(r.message || 'Failed', false); return; }

  counterName = r.counterName || selectedCounter;
  $('itemCounterName').textContent = counterName ? ` (${counterName})` : '';
  items = r.rows || [];
  renderItems();
  $('scanInput').disabled = false;
  $('scanInput').focus();
  $('btnUpdate').disabled = false;
  showMsg(`Loaded ${items.length} items`);
};

/* ── Barcode Scanning ───────────────────────────────────────────────────── */
$('scanInput').addEventListener('keydown', async function(e) {
  if (e.key !== 'Enter') return;
  const bcode = parseInt(this.value);
  this.value = '';
  if (!bcode || bcode <= 0) return;

  // Check if already in list
  if (items.find(i => i.bcode === bcode)) {
    showMsg('Barcode already in list', false);
    return;
  }

  const r = await api(`{{ url("/api/counter-issue/lookup") }}?bcode=${bcode}`).then(r=>r.json());
  if (!r.ok) {
    addError(r.message || `Barcode ${bcode} not found`);
    showMsg(r.message || 'Not found', false);
    return;
  }

  items.push(r);
  renderItems();
  // Scroll to bottom
  const wrap = $('itemWrap');
  wrap.scrollTop = wrap.scrollHeight;

  // Disable print buttons when new item added
  $('btnPrintItems').disabled = true;
  $('btnPrintSummary').disabled = true;
  showMsg('');
});

/* ── Render Items ───────────────────────────────────────────────────────── */
function renderItems(filtered) {
  const list = filtered || items;
  const tb = $('itemBody');
  tb.innerHTML = '';

  list.forEach((r, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="l">${r.bcode}</td>
      <td class="l">${r.icode}</td>
      <td class="l">${r.itemname}</td>
      <td>${r.qty}</td>
      <td>${r.weight.toFixed(3)}</td>
      <td>${r.stweight.toFixed(3)}</td>
      <td>${r.netwgt.toFixed(3)}</td>
      <td>${r.dmdwgt.toFixed(2)}</td>
      <td>${r.rate.toFixed(2)}</td>
      <td>${r.touch.toFixed(3)}</td>
      <td>${r.cost.toFixed(2)}</td>`;
    tr.style.cursor = 'pointer';
    tr.onclick = () => {
      tb.querySelectorAll('tr').forEach(r => r.classList.remove('sel'));
      tr.classList.add('sel');
    };
    tb.appendChild(tr);
  });

  // Totals
  let tQ=0, tW=0, tS=0, tN=0, tD=0;
  list.forEach(r => { tQ += r.qty; tW += r.weight; tS += r.stweight; tN += r.netwgt; tD += r.dmdwgt; });
  $('totCount').textContent = list.length + ' items';
  $('totQty').textContent = tQ;
  $('totWeight').textContent = tW.toFixed(3);
  $('totStWgt').textContent = tS.toFixed(3);
  $('totNetWgt').textContent = tN.toFixed(3);
  $('totDmdWgt').textContent = tD.toFixed(2);
}

/* ── Errors ─────────────────────────────────────────────────────────────── */
let errors = [];
function addError(msg) {
  errors.push(msg);
  const box = $('errorBox');
  box.innerHTML = errors.map(e => `<div>${e}</div>`).join('');
}

/* ── Update Counter ─────────────────────────────────────────────────────── */
$('btnUpdate').onclick = async () => {
  if (!confirm('Do you want to update the counter stock?')) return;

  const bcodes = items.map(i => i.bcode);
  if (bcodes.length === 0) { showMsg('No items to update', false); return; }

  const r = await api(`{{ url("/api/counter-issue/update") }}`, {
    method: 'POST',
    body: JSON.stringify({ counter: selectedCounter, bcodes }),
  }).then(r=>r.json());

  if (r.ok) {
    showMsg('Updated ' + (r.updated || 0) + ' items');
    $('btnPrintItems').disabled = false;
    if ($('summaryTbl').style.display !== 'none') {
      $('btnPrintSummary').disabled = false;
    }
  } else {
    showMsg(r.message || 'Update failed', false);
  }
};

/* ── Show Summary ───────────────────────────────────────────────────────── */
$('btnSummary').onclick = () => {
  // Remove last empty row if exists
  const sorted = [...items].sort((a, b) => a.icode.localeCompare(b.icode));
  const summary = [];
  let lastCode = '';

  sorted.forEach(r => {
    if (r.icode === '') return;
    if (r.icode !== lastCode) {
      summary.push({ icode: r.icode, itemname: r.itemname, qty: 0, weight: 0, stweight: 0 });
      lastCode = r.icode;
    }
    const s = summary[summary.length - 1];
    s.qty     += r.qty;
    s.weight  += r.weight;
    s.stweight += r.stweight;
  });

  const tb = $('summaryBody');
  tb.innerHTML = '';
  let tQ=0, tW=0, tS=0;

  summary.forEach(s => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td class="l">${s.icode}</td><td class="l">${s.itemname}${counterName ? ' ('+counterName+')' : ''}</td><td>${s.qty}</td><td>${s.weight.toFixed(3)}</td><td>${s.stweight.toFixed(3)}</td>`;
    tb.appendChild(tr);
    tQ += s.qty; tW += s.weight; tS += s.stweight;
  });

  $('sTotQty').textContent = tQ;
  $('sTotWeight').textContent = tW.toFixed(3);
  $('sTotStWgt').textContent = tS.toFixed(3);
  $('summaryTbl').style.display = '';

  if (summary.length > 0 && !$('btnPrintItems').disabled) {
    $('btnPrintSummary').disabled = false;
  }
};

/* ── Filter / Sort ──────────────────────────────────────────────────────── */
$('btnFilter').onclick = () => {
  const code = $('filterCode').value.trim().toUpperCase();
  if (code === '') {
    renderItems();
  } else {
    renderItems(items.filter(i => i.icode === code));
  }
};

$('btnSort').onclick = () => {
  items.sort((a, b) => a.bcode - b.bcode);
  renderItems();
};

/* ── Refresh (clear counter) ────────────────────────────────────────────── */
$('btnRefresh').onclick = async () => {
  if (!confirm('You are going to remove all items from this counter...\nPlease confirm...')) return;

  const r = await api(`{{ url("/api/counter-issue/refresh") }}`, {
    method: 'POST',
    body: JSON.stringify({ counter: selectedCounter }),
  }).then(r=>r.json());

  if (r.ok) {
    items = [];
    renderItems();
    showMsg('Counter refreshed');
  } else {
    showMsg(r.message || 'Failed', false);
  }
};

// Show refresh button on right-click of toolbar area
document.querySelector('.toolbar').addEventListener('contextmenu', e => {
  e.preventDefault();
  $('btnRefresh').style.display = '';
});

/* ── Print ──────────────────────────────────────────────────────────────── */
$('btnPrintItems').onclick = () => printTable('itemBody', 'Items Issue to Counter' + (counterName ? ' - ' + counterName : ''));
$('btnPrintSummary').onclick = () => printTable('summaryBody', 'Counter Summary' + (counterName ? ' - ' + counterName : ''), true);

function printTable(tbodyId, title, isSummary=false) {
  const tbody = $(tbodyId);
  const w = window.open('','','width=900,height=600');
  w.document.write(`<html><head><title>${title}</title><style>
    body{font-family:Arial,sans-serif;font-size:12px;margin:20px}
    h3{margin:0 0 10px}
    table{border-collapse:collapse;width:100%}
    th,td{border:1px solid #999;padding:4px 6px;text-align:right}
    th{background:#333;color:#fff}
    td:nth-child(1),td:nth-child(2),td:nth-child(3){text-align:left}
  </style></head><body><h3>${title}</h3><table>`);

  if (isSummary) {
    w.document.write('<thead><tr><th>Code</th><th>Item Name</th><th>Qty</th><th>Weight</th><th>St.Wgt</th></tr></thead>');
  } else {
    w.document.write('<thead><tr><th>Barcode</th><th>Code</th><th>Item Name</th><th>Qty</th><th>Weight</th><th>St.Wgt</th><th>Net.Wgt</th><th>DmdWgt</th><th>Rate</th><th>Touch</th><th>Cost</th></tr></thead>');
  }
  w.document.write('<tbody>' + tbody.innerHTML + '</tbody></table></body></html>');
  w.document.close();
  w.print();
}

/* ── Keyboard ───────────────────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') window.close();
});
</script>
</body>
</html>
