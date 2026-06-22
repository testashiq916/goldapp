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
.bar{background:#1a3a4a;color:#7ec8e3;padding:8px 12px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.toolbar{display:flex;gap:6px;align-items:center;background:#d6e6f0;border:1px solid #90b8d0;padding:6px 8px;margin-bottom:4px;flex-wrap:wrap}
.toolbar label{font-weight:700;font-size:12px}
.toolbar select,.toolbar input[type=text]{height:28px;border:1px solid #90b8d0;border-radius:4px;padding:0 6px;font-size:12px}
.toolbar select{min-width:120px}
.chk-label{display:flex;align-items:center;gap:3px;font-size:12px;font-weight:700}
.btn{height:30px;padding:0 12px;border:1px solid #6a9ab8;background:#b8d4e8;border-radius:4px;cursor:pointer;font-weight:700;font-size:11px;white-space:nowrap}
.btn:hover{background:#a0c4d8}
.btn-primary{background:#1a6b8a;color:#fff;border-color:#135570}
.btn-primary:hover{background:#135570}
.btn-danger{background:#c0392b;color:#fff;border-color:#a33025}
.btn-danger:hover{background:#a33025}
.btn-warning{background:#e67e22;color:#fff;border-color:#d35400}
.btn-warning:hover{background:#d35400}
.btn-success{background:#27ae60;color:#fff;border-color:#219a52}
.btn-success:hover{background:#219a52}
.btn:disabled{opacity:.5;cursor:not-allowed}
.panels{display:grid;grid-template-columns:2fr 1fr;gap:6px}
.panel-box{border:1px solid #90b8d0;background:#fff;border-radius:4px;overflow:hidden}
.panel-hdr{background:#1a3a4a;color:#7ec8e3;padding:6px 10px;font-weight:700;font-size:13px;display:flex;justify-content:space-between;align-items:center}
.tbl{width:100%;border-collapse:collapse;font-size:11px}
.tbl th,.tbl td{border:1px solid #ccc;padding:3px 5px}
.tbl th{background:#1a3a4a;color:#7ec8e3;font-size:11px;position:sticky;top:0;z-index:1}
.tbl td{text-align:right}
.tbl td.l{text-align:left}
.tbl tr:nth-child(even){background:#f0f6fa}
.tbl tr.sel{background:#7ec8e3 !important}
.tbl tr.oos{color:#c0392b;font-style:italic}
.tbl-wrap{max-height:400px;overflow:auto}
.scan-bar{display:flex;gap:6px;align-items:center;padding:6px 10px;background:#e8f0f6;border-top:1px solid #90b8d0}
.scan-bar input{flex:1;height:32px;border:1px solid #90b8d0;border-radius:4px;padding:0 8px;font-size:14px;font-weight:700}
.footer-bar{display:flex;gap:6px;align-items:center;padding:6px 8px;background:#d6e6f0;border:1px solid #90b8d0;margin-top:4px;flex-wrap:wrap}
.error-box{max-height:180px;overflow:auto;background:#fff3f3;border:1px solid #e0a0a0;border-radius:4px;padding:6px;font-size:12px;color:#b91c1c;margin:4px}
.error-box div{padding:2px 0;border-bottom:1px solid #f0d0d0}
.totals{background:#1a3a4a;color:#7ec8e3;font-weight:700}
.msg{font-weight:700;font-size:13px;margin-left:8px}
.msg.ok{color:#1a6b8a}
.msg.err{color:#b91c1c}
.summary-wrap{max-height:280px;overflow:auto;margin:4px}
.result-box{max-height:300px;overflow:auto;margin:4px;font-size:12px}
.result-box table{width:100%;border-collapse:collapse}
.result-box th,.result-box td{border:1px solid #ccc;padding:3px 5px;font-size:11px}
.result-box th{background:#1a3a4a;color:#7ec8e3;position:sticky;top:0}
.result-hdr{padding:4px 8px;font-weight:700;font-size:12px;border-bottom:1px solid #ccc}
.result-hdr.bad{background:#ffe0e0;color:#b91c1c}
.result-hdr.warn{background:#fff3d0;color:#b8860b}
/* Modal */
.modal-bg{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:100;justify-content:center;align-items:center}
.modal-bg.show{display:flex}
.modal{background:#fff;border-radius:8px;padding:0;width:700px;max-width:95vw;max-height:80vh;overflow:auto;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.modal-hdr{background:#1a3a4a;color:#7ec8e3;padding:10px 14px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.modal-hdr .close{cursor:pointer;font-size:18px;color:#7ec8e3}
.modal-body{padding:12px;max-height:60vh;overflow:auto}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <span>{{ $title }}</span>
    <span id="counterLabel"></span>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <label>Counter:</label>
    <select id="selCounter">
      <option value="">-- All --</option>
      @foreach($counters as $c)
      <option value="{{ $c->code }}">{{ $c->code }} - {{ $c->name }}</option>
      @endforeach
    </select>

    <label>Group:</label>
    <select id="selGroup">
      <option value="">-- All --</option>
      @foreach($groups as $g)
      <option value="{{ $g->code }}">{{ $g->code }} - {{ $g->name }}</option>
      @endforeach
    </select>

    <label>SubGrp:</label>
    <select id="selSubGrp">
      <option value="">-- All --</option>
      @foreach($subgroups as $s)
      <option value="{{ $s->code }}">{{ $s->code }} - {{ $s->name }}</option>
      @endforeach
    </select>

    <span class="chk-label">
      <input type="checkbox" id="chkBcBased"> BC Based
    </span>

    <label>Item Code:</label>
    <input type="text" id="txtItemCode" style="width:80px;text-transform:uppercase">
  </div>

  <!-- Action Toolbar -->
  <div class="toolbar">
    <button class="btn btn-success" id="btnFinished">Finished</button>
    <button class="btn btn-warning" id="btnUpdateCorrect">Update this list as Correct</button>
    <button class="btn" id="btnGetFile">Get from File</button>
    <button class="btn" id="btnFreshMark">Fresh Mark</button>
    <button class="btn" id="btnSummary">Show Summary</button>
    <button class="btn btn-primary" id="btnPrint">Print</button>
    <input type="file" id="fileInput" accept=".txt,.csv" style="display:none">
    <div class="msg" id="msg"></div>
  </div>

  <div class="panels">
    <!-- Left: Scanned Items -->
    <div class="panel-box">
      <div class="panel-hdr">
        <span>Scanned Items <span id="scanCount"></span></span>
      </div>
      <div class="scan-bar">
        <label style="font-weight:700;font-size:13px">Scan Barcode:</label>
        <input id="scanInput" type="number" placeholder="Enter barcode number...">
      </div>
      <div class="tbl-wrap" id="itemWrap">
        <table class="tbl">
          <thead><tr>
            <th style="width:30px">#</th>
            <th style="width:75px">Barcode</th>
            <th style="width:60px">Code</th>
            <th>Item Name</th>
            <th style="width:35px">Qty</th>
            <th style="width:65px">Weight</th>
            <th style="width:60px">St.Wgt</th>
            <th style="width:60px">Net.Wgt</th>
            <th style="width:55px">DmdWgt</th>
            <th style="width:55px">Rate</th>
            <th style="width:60px">Cost</th>
            <th style="width:35px">Stk</th>
            <th style="width:30px">Del</th>
          </tr></thead>
          <tbody id="itemBody"></tbody>
          <tfoot>
            <tr class="totals">
              <td id="totCount">0</td>
              <td></td><td></td><td></td>
              <td id="totQty">0</td>
              <td id="totWeight">0.000</td>
              <td id="totStWgt">0.000</td>
              <td id="totNetWgt">0.000</td>
              <td id="totDmdWgt">0.00</td>
              <td></td><td></td><td></td><td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Right: Errors + Summary -->
    <div class="panel-box">
      <div class="panel-hdr">Errors / Warnings</div>
      <div class="error-box" id="errorBox">
        <div style="color:#888;font-style:italic">No errors yet</div>
      </div>

      <div class="panel-hdr" style="margin-top:4px">Summary (by Item Code)</div>
      <div class="summary-wrap">
        <table class="tbl" id="summaryTbl" style="display:none">
          <thead><tr>
            <th>Code</th>
            <th>Item Name</th>
            <th style="width:35px">Qty</th>
            <th style="width:65px">Weight</th>
            <th style="width:60px">St.Wgt</th>
            <th style="width:70px">Amount</th>
          </tr></thead>
          <tbody id="summaryBody"></tbody>
          <tfoot>
            <tr class="totals">
              <td></td><td></td>
              <td id="sTotQty">0</td>
              <td id="sTotWeight">0.000</td>
              <td id="sTotStWgt">0.000</td>
              <td id="sTotAmt">0.00</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Finished Results Modal -->
<div class="modal-bg" id="finishedModal">
  <div class="modal">
    <div class="modal-hdr">
      <span>Stock Verification Results</span>
      <span class="close" id="closeFinished">&times;</span>
    </div>
    <div class="modal-body" id="finishedBody"></div>
  </div>
</div>

<script>
const $  = id => document.getElementById(id);
const api = (u,o) => fetch(u,{...o, headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','Accept':'application/json',...(o?.headers||{})}});

let items = [];       // scanned items array
let selectedIdx = -1;

function showMsg(t, ok=true) {
  const el = $('msg');
  el.textContent = t;
  el.className = 'msg ' + (ok ? 'ok' : 'err');
  if (t) setTimeout(() => { if (el.textContent === t) el.textContent = ''; }, 5000);
}

function getFilters() {
  return {
    counter:  $('selCounter').value,
    group:    $('selGroup').value,
    subgroup: $('selSubGrp').value,
    icode:    $('txtItemCode').value.trim().toUpperCase(),
    bcBased:  $('chkBcBased').checked,
  };
}

/* ── Barcode Scanning ───────────────────────────────────────────────────── */
$('scanInput').addEventListener('keydown', async function(e) {
  if (e.key !== 'Enter') return;
  const bcode = parseInt(this.value);
  this.value = '';
  if (!bcode || bcode <= 0) return;

  // Check if already in list
  if (items.find(i => i.bcode === bcode)) {
    showMsg('Barcode already in list', false);
    addError(`Barcode ${bcode} - Already in list`);
    return;
  }

  const r = await api(`{{ url("/api/stock-verification/lookup") }}?bcode=${bcode}`).then(r=>r.json());
  if (!r.ok) {
    addError(r.message || `Barcode ${bcode} not found`);
    showMsg(r.message || 'Not found', false);
    return;
  }

  // Counter mismatch warning
  const selCounter = $('selCounter').value;
  if (selCounter && r.counter && r.counter !== selCounter) {
    addError(`Barcode ${bcode} (${r.icode}) - Counter mismatch: item in "${r.counter}", selected "${selCounter}"`);
  }

  // Out of stock warning
  if (r.outofstock) {
    addError(`${r.icode} (${bcode}) - Out of stock but scanned`);
  }

  items.push(r);
  renderItems();
  const wrap = $('itemWrap');
  wrap.scrollTop = wrap.scrollHeight;
  showMsg(`Scanned: ${r.icode} - ${r.itemname}`);
});

/* ── Render Items ───────────────────────────────────────────────────────── */
function renderItems() {
  const tb = $('itemBody');
  tb.innerHTML = '';

  items.forEach((r, i) => {
    const tr = document.createElement('tr');
    if (r.outofstock) tr.className = 'oos';
    tr.innerHTML = `
      <td class="l">${i+1}</td>
      <td class="l">${r.bcode}</td>
      <td class="l">${r.icode}</td>
      <td class="l">${r.itemname}</td>
      <td>${r.qty}</td>
      <td>${r.weight.toFixed(3)}</td>
      <td>${r.stweight.toFixed(3)}</td>
      <td>${r.netwgt.toFixed(3)}</td>
      <td>${r.dmdwgt.toFixed(2)}</td>
      <td>${r.rate.toFixed(2)}</td>
      <td>${(r.dmdcost ?? 0).toFixed(2)}</td>
      <td class="l">${r.stk}</td>
      <td><button class="btn btn-danger" style="height:20px;padding:0 6px;font-size:10px" onclick="delItem(${i})">&times;</button></td>`;
    tr.style.cursor = 'pointer';
    tr.onclick = (e) => {
      if (e.target.tagName === 'BUTTON') return;
      tb.querySelectorAll('tr').forEach(r => r.classList.remove('sel'));
      tr.classList.add('sel');
      selectedIdx = i;
    };
    tb.appendChild(tr);
  });

  // Totals
  let tQ=0, tW=0, tS=0, tN=0, tD=0;
  items.forEach(r => { tQ += r.qty; tW += r.weight; tS += r.stweight; tN += r.netwgt; tD += r.dmdwgt; });
  $('totCount').textContent = items.length;
  $('totQty').textContent = tQ;
  $('totWeight').textContent = tW.toFixed(3);
  $('totStWgt').textContent = tS.toFixed(3);
  $('totNetWgt').textContent = tN.toFixed(3);
  $('totDmdWgt').textContent = tD.toFixed(2);
  $('scanCount').textContent = `(${items.length} items)`;
}

/* ── Delete Item ────────────────────────────────────────────────────────── */
async function delItem(idx) {
  const item = items[idx];
  if (!item) return;

  // Reset taken=0
  await api(`{{ url("/api/stock-verification/delete") }}`, {
    method: 'POST',
    body: JSON.stringify({ bcode: item.bcode }),
  });

  items.splice(idx, 1);
  renderItems();
  showMsg('Item removed');
}

/* ── Errors ─────────────────────────────────────────────────────────────── */
let errors = [];
function addError(msg) {
  errors.push(msg);
  const box = $('errorBox');
  box.innerHTML = errors.map(e => `<div>${e}</div>`).join('');
}

/* ── Fresh Mark ─────────────────────────────────────────────────────────── */
$('btnFreshMark').onclick = async () => {
  const icode = $('txtItemCode').value.trim().toUpperCase();
  if (!icode) {
    showMsg('Enter an Item Code first', false);
    return;
  }
  if (!confirm(`Reset taken flag for all barcodes of item "${icode}"?`)) return;

  const r = await api(`{{ url("/api/stock-verification/fresh-mark") }}`, {
    method: 'POST',
    body: JSON.stringify({ icode }),
  }).then(r=>r.json());

  if (r.ok) {
    // Remove items with this icode from the list
    items = items.filter(i => i.icode !== icode);
    renderItems();
    showMsg(r.message || 'Fresh mark done');
  } else {
    showMsg(r.message || 'Failed', false);
  }
};

/* ── Finished ───────────────────────────────────────────────────────────── */
$('btnFinished').onclick = async () => {
  showMsg('Checking...');
  const f = getFilters();

  const r = await api(`{{ url("/api/stock-verification/finished") }}`, {
    method: 'POST',
    body: JSON.stringify(f),
  }).then(r=>r.json());

  if (!r.ok) { showMsg(r.message || 'Failed', false); return; }

  const body = $('finishedBody');
  let html = '';

  // Non Stock But Scanned
  if (r.nonStockScanned.length > 0) {
    html += `<div class="result-hdr bad">Non Stock But Scanned (${r.nonStockScanned.length})</div>`;
    html += `<div class="result-box"><table><thead><tr><th>Barcode</th><th>Code</th><th>Item Name</th><th>Qty</th><th>Weight</th><th>St.Wgt</th></tr></thead><tbody>`;
    r.nonStockScanned.forEach(i => {
      html += `<tr><td>${i.bcode}</td><td>${i.icode}</td><td style="text-align:left">${i.itemname}</td><td>${i.qty}</td><td>${i.weight.toFixed(3)}</td><td>${i.stweight.toFixed(3)}</td></tr>`;
    });
    html += '</tbody></table></div>';
  }

  // Stock Exist But Not Scanned
  if (r.stockNotScanned.length > 0) {
    html += `<div class="result-hdr warn">Stock Exist But Not Scanned (${r.stockNotScanned.length})</div>`;
    html += `<div class="result-box"><table><thead><tr><th>Barcode</th><th>Code</th><th>Item Name</th><th>Qty</th><th>Weight</th><th>St.Wgt</th></tr></thead><tbody>`;
    r.stockNotScanned.forEach(i => {
      html += `<tr><td>${i.bcode}</td><td>${i.icode}</td><td style="text-align:left">${i.itemname}</td><td>${i.qty}</td><td>${i.weight.toFixed(3)}</td><td>${i.stweight.toFixed(3)}</td></tr>`;
    });
    html += '</tbody></table></div>';
  }

  if (r.nonStockScanned.length === 0 && r.stockNotScanned.length === 0) {
    html = '<div style="padding:20px;text-align:center;color:#27ae60;font-weight:700;font-size:16px">All items match! No discrepancies found.</div>';
  }

  body.innerHTML = html;
  $('finishedModal').classList.add('show');
  showMsg('Verification complete');
};

$('closeFinished').onclick = () => $('finishedModal').classList.remove('show');

/* ── Update this list as Correct ────────────────────────────────────────── */
$('btnUpdateCorrect').onclick = async () => {
  if (!confirm('This will update stock status:\n- Scanned items → Stock (Y)\n- Unscanned items → Non-Stock (N)\n\nContinue?')) return;

  const f = getFilters();
  showMsg('Updating...');

  const r = await api(`{{ url("/api/stock-verification/update-correct") }}`, {
    method: 'POST',
    body: JSON.stringify(f),
  }).then(r=>r.json());

  if (r.ok) {
    showMsg(r.message || 'Updated');
  } else {
    showMsg(r.message || 'Failed', false);
  }
};

/* ── Show Summary ───────────────────────────────────────────────────────── */
$('btnSummary').onclick = async () => {
  if (items.length === 0) { showMsg('No items to summarize', false); return; }

  const bcodes = items.map(i => i.bcode);
  showMsg('Loading summary...');

  const r = await api(`{{ url("/api/stock-verification/summary") }}`, {
    method: 'POST',
    body: JSON.stringify({ bcodes }),
  }).then(r=>r.json());

  if (!r.ok) { showMsg(r.message || 'Failed', false); return; }

  const tb = $('summaryBody');
  tb.innerHTML = '';
  let tQ=0, tW=0, tS=0, tA=0;

  (r.rows || []).forEach(s => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td class="l">${s.icode}</td><td class="l">${s.itemname}</td><td>${s.qty}</td><td>${s.weight.toFixed(3)}</td><td>${s.stweight.toFixed(3)}</td><td>${s.amount.toFixed(2)}</td>`;
    tb.appendChild(tr);
    tQ += s.qty; tW += s.weight; tS += s.stweight; tA += s.amount;
  });

  $('sTotQty').textContent = tQ;
  $('sTotWeight').textContent = tW.toFixed(3);
  $('sTotStWgt').textContent = tS.toFixed(3);
  $('sTotAmt').textContent = tA.toFixed(2);
  $('summaryTbl').style.display = '';
  showMsg('');
};

/* ── Get from File ──────────────────────────────────────────────────────── */
$('btnGetFile').onclick = () => $('fileInput').click();

$('fileInput').addEventListener('change', async function() {
  const file = this.files[0];
  if (!file) return;
  this.value = '';

  const text = await file.text();
  const lines = text.split(/[\r\n]+/).map(l => l.trim()).filter(l => l);
  const bcodes = lines.map(l => parseInt(l)).filter(b => b > 0);

  if (bcodes.length === 0) { showMsg('No valid barcodes in file', false); return; }

  // Filter out already-in-list barcodes
  const existing = new Set(items.map(i => i.bcode));
  const newBcodes = bcodes.filter(b => !existing.has(b));

  if (newBcodes.length === 0) { showMsg('All barcodes already in list', false); return; }

  showMsg(`Importing ${newBcodes.length} barcodes...`);

  const r = await api(`{{ url("/api/stock-verification/import-file") }}`, {
    method: 'POST',
    body: JSON.stringify({ bcodes: newBcodes }),
  }).then(r=>r.json());

  if (!r.ok) { showMsg(r.message || 'Failed', false); return; }

  // Add found items
  if (r.items && r.items.length > 0) {
    items.push(...r.items);
    renderItems();
  }

  // Add errors
  if (r.errors && r.errors.length > 0) {
    r.errors.forEach(e => addError(e));
  }

  showMsg(`Imported ${r.found || 0} items. Not found: ${r.notfound || 0}, Already taken: ${r.alreadytaken || 0}`);
});

/* ── Print ──────────────────────────────────────────────────────────────── */
$('btnPrint').onclick = () => {
  if (items.length === 0) { showMsg('No items to print', false); return; }
  const w = window.open('','','width=900,height=600');
  w.document.write(`<html><head><title>Stock Verification</title><style>
    body{font-family:Arial,sans-serif;font-size:12px;margin:20px}
    h3{margin:0 0 10px}
    table{border-collapse:collapse;width:100%}
    th,td{border:1px solid #999;padding:4px 6px;text-align:right}
    th{background:#333;color:#fff}
    td:nth-child(1),td:nth-child(2),td:nth-child(3),td:nth-child(4){text-align:left}
  </style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer><\/script>
</head><body><h3>Barcode Stock Verification</h3><table>
  <thead><tr><th>#</th><th>Barcode</th><th>Code</th><th>Item Name</th><th>Qty</th><th>Weight</th><th>St.Wgt</th><th>Net.Wgt</th><th>DmdWgt</th><th>Rate</th><th>Cost</th><th>Stk</th></tr></thead><tbody>`);
  items.forEach((r, i) => {
    w.document.write(`<tr><td>${i+1}</td><td>${r.bcode}</td><td>${r.icode}</td><td>${r.itemname}</td><td>${r.qty}</td><td>${r.weight.toFixed(3)}</td><td>${r.stweight.toFixed(3)}</td><td>${r.netwgt.toFixed(3)}</td><td>${r.dmdwgt.toFixed(2)}</td><td>${r.rate.toFixed(2)}</td><td>${(r.dmdcost ?? 0).toFixed(2)}</td><td>${r.stk}</td></tr>`);
  });
  w.document.write('</tbody></table></body></html>');
  w.document.close();
  w.print();
};

/* ── Keyboard ───────────────────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    if ($('finishedModal').classList.contains('show')) {
      $('finishedModal').classList.remove('show');
    }
  }
});

// Auto-focus scan input
$('scanInput').focus();
</script>
</body>
</html>
