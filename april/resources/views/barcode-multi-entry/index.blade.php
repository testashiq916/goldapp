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

.main-window{background:#fff;border:1px solid #d6dcea;border-radius:12px;
  box-shadow:0 10px 30px rgba(33,52,89,.10);margin:8px;overflow:hidden;
  display:flex;flex-direction:column;height:calc(100vh - 16px)}

.title-bar{background:linear-gradient(135deg,#065f46,#047857);color:#fff;
  padding:10px 14px;font-weight:700;font-size:13px;display:flex;align-items:center;gap:8px;
  letter-spacing:.3px;flex-shrink:0}
.title-bar .icon{width:16px;height:16px;background:#34d399;border-radius:4px;flex-shrink:0}

/* ── Header section ── */
.header-section{background:#f9fbff;padding:8px 12px;border-bottom:1px solid #d6dcea;flex-shrink:0;
  display:flex;flex-wrap:wrap;gap:6px 16px;align-items:center}
.hdr-group{display:flex;align-items:center;gap:4px}
.hdr-group label{font-weight:600;font-size:11px;color:#64748b;min-width:65px}
.hdr-group input,.hdr-group select{font-size:11px;padding:2px 6px;border:1px solid #d6dcea;
  background:#fff;height:26px;border-radius:7px;color:#2d3748}
.hdr-group input:focus,.hdr-group select:focus{border-color:#34d399;
  box-shadow:0 0 0 3px rgba(52,211,153,.15);outline:none}
.hdr-group input.sm{width:70px}
.hdr-group input.md{width:110px}
.hdr-group input.lg{width:180px}
.hdr-group .doc{font-weight:700;font-size:12px;color:#065f46;background:#d1fae5;
  padding:3px 10px;border-radius:6px;border:1px solid #6ee7b7}

/* ── Buttons ── */
.btn{font-size:11px;padding:4px 12px;border:1px solid #d6dcea;border-radius:7px;
  cursor:pointer;font-weight:600;height:26px;display:inline-flex;align-items:center;gap:4px;transition:all .2s}
.btn-green{background:linear-gradient(135deg,#059669,#047857);color:#fff;border:none}
.btn-green:hover{background:linear-gradient(135deg,#047857,#065f46);transform:translateY(-1px);
  box-shadow:0 2px 8px rgba(5,150,105,.25)}
.btn-blue{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none}
.btn-blue:hover{box-shadow:0 2px 8px rgba(37,99,235,.25);transform:translateY(-1px)}
.btn-red{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none}
.btn-red:hover{transform:translateY(-1px)}
.btn-outline{background:#fff;color:#374151}
.btn-outline:hover{background:#f0f6ff;border-color:#93c5fd}
.btn-amber{background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border:none}
.btn-amber:hover{transform:translateY(-1px)}

/* ── Action bar ── */
.action-bar{display:flex;align-items:center;gap:6px;padding:6px 12px;background:#f8fafc;
  border-bottom:1px solid #e2e8f0;flex-shrink:0}
.action-bar .sep{width:1px;height:18px;background:#d6dcea}
.action-bar .info{font-size:11px;color:#64748b;margin-left:auto}
.action-bar .info strong{color:#1e293b}

/* ── Grid ── */
.grid-container{flex:1;overflow:hidden;margin:0 8px 8px;border:1px solid #d6dcea;
  border-radius:10px;background:#fff;box-shadow:0 3px 10px rgba(32,55,92,.05);
  display:flex;flex-direction:column}
.grid-scroll{flex:1;overflow-y:auto;overflow-x:auto}
table.bc{width:100%;border-collapse:collapse;font-size:10px}
table.bc thead th{background:linear-gradient(180deg,#047857,#065f46);color:#d1fae5;
  padding:5px 3px;border:1px solid #059669;font-weight:600;font-size:9px;
  text-align:center;white-space:nowrap;text-transform:uppercase;letter-spacing:.3px;
  position:sticky;top:0;z-index:2}
table.bc tbody td{border:1px solid #edf1f7;padding:1px 1px;text-align:center;height:24px}
table.bc tbody tr:nth-child(even){background:#f0fdf4}
table.bc tbody tr:hover{background:#ecfdf5}
table.bc tbody tr.sel td{background:#bbf7d0}
table.bc tbody input,table.bc tbody select{font-size:10px;border:none;background:transparent;
  text-align:center;width:100%;height:100%}
table.bc tbody input:focus{background:#fffff0;outline:2px solid #10b981;border-radius:2px}
table.bc tbody input.right{text-align:right}
table.bc tbody select{cursor:pointer}

/* ── Footer totals ── */
.footer-bar{display:flex;gap:14px;padding:6px 14px;background:#f0fdf4;border-top:1px solid #d6dcea;
  font-size:11px;font-weight:600;flex-shrink:0}
.footer-bar .ft{display:flex;gap:4px;align-items:center}
.footer-bar .ft .lbl{color:#64748b}
.footer-bar .ft .val{color:#065f46;font-weight:700;min-width:60px;text-align:right}

/* ── Item search dropdown ── */
.item-drop{position:absolute;background:#111827;border:1px solid #374151;border-radius:6px;
  box-shadow:0 6px 16px rgba(0,0,0,.35);z-index:600;max-height:180px;overflow-y:auto;
  font-size:10px;color:#f9fafb;display:none;min-width:200px}
.item-drop .i-row{padding:4px 8px;cursor:pointer;border-bottom:1px solid #374151}
.item-drop .i-row:hover{background:#1f2937}
.item-drop .i-row .i-code{font-weight:600;color:#6ee7b7;margin-right:4px}

@media print{
  .header-section,.action-bar,.title-bar{display:none!important}
  .main-window{margin:0;border:none;box-shadow:none;height:auto;overflow:visible}
  .grid-container{margin:0;border:none;box-shadow:none;overflow:visible}
  .grid-scroll{overflow:visible;max-height:none}
}
</style>
@include('partials.print-layout-head')
</head>
<body>

<div class="main-window">
  <div class="title-bar">
    <div class="icon"></div>
    Barcode Entry (Multi)
  </div>

  <!-- Header -->
  <div class="header-section">
    <div class="hdr-group">
      <label>Doc No</label>
      <span class="doc" id="hDocNo">BC/000000</span>
    </div>
    <div class="hdr-group">
      <label>Item Code</label>
      <input type="text" id="hItemCode" class="md" style="text-transform:uppercase"
        onkeyup="itemSearch(this)" onkeydown="if(event.key==='Enter'){applyItemCode(); event.preventDefault();}">
      <div class="item-drop" id="itemDrop"></div>
    </div>
    <div class="hdr-group">
      <label>Date</label>
      <input type="date" id="hDate" class="md">
    </div>
    <div class="hdr-group">
      <label>Salesman</label>
      <select id="hSmcode" style="width:130px">
        <option value="">--</option>
        @foreach($salesmen as $s)
        <option value="{{ $s->code ?? ($s['code'] ?? '') }}">{{ ($s->code ?? ($s['code'] ?? '')) . ' - ' . ($s->name ?? ($s['name'] ?? '')) }}</option>
        @endforeach
      </select>
    </div>
    <div class="hdr-group">
      <label>Supplier</label>
      <select id="hSmith" style="width:150px">
        <option value="">--</option>
        @foreach($smiths as $s)
        <option value="{{ $s->code ?? ($s['code'] ?? '') }}">{{ ($s->code ?? ($s['code'] ?? '')) . ' - ' . ($s->name ?? ($s['name'] ?? '')) }}</option>
        @endforeach
      </select>
    </div>
    <div class="hdr-group">
      <button class="btn btn-outline" onclick="setDefaults()" title="Set current item/stktype as default">Set Default</button>
    </div>
  </div>

  <!-- Action bar -->
  <div class="action-bar">
    <button class="btn btn-green" onclick="addRow()" title="Add new barcode row">+ Add</button>
    <button class="btn btn-red" onclick="deleteRow()" title="Delete selected row">Delete</button>
    <div class="sep"></div>
    <button class="btn btn-blue" onclick="saveAll()" title="Save all barcodes (F9)">Save</button>
    <button class="btn btn-amber" onclick="openLoadDoc()" title="Load existing document">Load Doc</button>
    <div class="sep"></div>
    <button class="btn btn-outline" onclick="window.print()">Print</button>
    <button class="btn btn-outline" onclick="newDocument()">New</button>
    <div class="info">
      Items: <strong id="infoCount">0</strong> |
      Total Qty: <strong id="infoQty">0</strong> |
      Total Wt: <strong id="infoWgt">0.000</strong>
    </div>
  </div>

  <!-- Grid -->
  <div class="grid-container">
    <div class="grid-scroll">
      <table class="bc" id="bcTable">
        <thead>
          <tr>
            <th style="width:24px">#</th>
            <th style="width:80px">Barcode</th>
            <th style="width:80px">Item Code</th>
            <th style="width:120px">Item Name</th>
            <th style="width:85px">Sub Grp</th>
            <th style="width:60px">Size</th>
            <th style="width:80px">Model</th>
            <th style="width:40px">Qty</th>
            <th style="width:65px">Weight</th>
            <th style="width:60px">St.Wt.</th>
            <th style="width:65px">Net Wt.</th>
            <th style="width:65px">St.Price</th>
            <th style="width:50px">VA%</th>
            <th style="width:50px">MinVA%</th>
            <th style="width:60px">Sticker Wt</th>
            <th style="width:65px">MC/gm</th>
            <th style="width:70px">MC Amt</th>
            <th style="width:65px">Smith MC</th>
            <th style="width:80px">HUID</th>
            <th style="width:55px">Wastage</th>
          </tr>
        </thead>
        <tbody id="bcBody"></tbody>
      </table>
    </div>
  </div>

  <!-- Footer totals -->
  <div class="footer-bar">
    <div class="ft"><span class="lbl">Rows:</span><span class="val" id="ftRows">0</span></div>
    <div class="ft"><span class="lbl">Total Qty:</span><span class="val" id="ftQty">0</span></div>
    <div class="ft"><span class="lbl">Total Weight:</span><span class="val" id="ftWgt">0.000</span></div>
    <div class="ft"><span class="lbl">Total St.Wt:</span><span class="val" id="ftStw">0.000</span></div>
    <div class="ft"><span class="lbl">Total Net Wt:</span><span class="val" id="ftNet">0.000</span></div>
    <div class="ft"><span class="lbl">Total St.Price:</span><span class="val" id="ftStp">0.00</span></div>
  </div>
</div>

<script>
var BASE = @json(request()->root());
var ITEMS_LOOKUP = @json($items);
var SUBGRPS = @json($subgroups);
var MODELS = @json($models);
var rows = [];
var selIdx = -1;
var nextBc = 0;

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }

function ajax(method, url, data, cb) {
  var xhr = new XMLHttpRequest();
  xhr.open(method, BASE + url, true);
  xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
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

// ─── Init ────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('hDate').value = new Date().toISOString().slice(0,10);
  loadNextDocNo();
  loadNextBarcode(function() {
    addRow();
  });
});

function loadNextDocNo() {
  ajax('GET', '/api/barcode-multi/next-docno', null, function(d) {
    if (d.ok) document.getElementById('hDocNo').textContent = d.docno;
  });
}

function loadNextBarcode(cb) {
  ajax('GET', '/api/barcode-multi/next-barcode', null, function(d) {
    if (d.ok) nextBc = d.barcode;
    if (cb) cb();
  });
}

// ─── Row management ──────────────────────────────────────────────────────────
function addRow() {
  var lastRow = rows.length > 0 ? rows[rows.length - 1] : null;
  var bc = nextBc;
  // Find max barcode in current rows
  for (var i = 0; i < rows.length; i++) {
    if (rows[i].barcode >= bc) bc = rows[i].barcode + 1;
  }
  nextBc = bc + 1;

  var nr = {
    barcode: bc,
    itemcode: lastRow ? lastRow.itemcode : document.getElementById('hItemCode').value.toUpperCase().trim(),
    itemname: lastRow ? lastRow.itemname : '',
    subgrp: lastRow ? lastRow.subgrp : '',
    size: '',
    model: '',
    qty: 1,
    weight: 0,
    stwgt: 0,
    stprice: 0,
    vaperc: lastRow ? lastRow.vaperc : 0,
    minvap: lastRow ? lastRow.minvap : 0,
    stickerwgt: lastRow ? lastRow.stickerwgt : 0,
    mcrate: lastRow ? lastRow.mcrate : 0,
    mcamt: 0,
    smithmcrate: 0,
    huid: '',
    wastage: 0,
  };

  rows.push(nr);
  selIdx = rows.length - 1;
  renderGrid();
  // Focus item code of new row
  var inp = document.getElementById('r_itemcode_' + selIdx);
  if (inp) inp.focus();
}

function deleteRow() {
  if (selIdx < 0 || selIdx >= rows.length) { alert('Select a row first'); return; }
  rows.splice(selIdx, 1);
  if (selIdx >= rows.length) selIdx = rows.length - 1;
  renderGrid();
}

function selectRow(idx) {
  selIdx = idx;
  var trs = document.querySelectorAll('#bcBody tr');
  trs.forEach(function(tr, i) {
    tr.classList.toggle('sel', i === idx);
  });
}

function renderGrid() {
  var tb = document.getElementById('bcBody');
  var html = '';

  // Build subgrp options
  var sgOpts = '<option value="">--</option>';
  for (var s = 0; s < SUBGRPS.length; s++) {
    var sg = SUBGRPS[s];
    sgOpts += '<option value="' + esc(sg.code || sg.name) + '">' + esc(sg.name) + '</option>';
  }

  // Build model options
  var mdOpts = '<option value="">--</option>';
  for (var m = 0; m < MODELS.length; m++) {
    mdOpts += '<option value="' + esc(MODELS[m]) + '">' + esc(MODELS[m]) + '</option>';
  }

  for (var i = 0; i < rows.length; i++) {
    var r = rows[i];
    var cls = i === selIdx ? ' class="sel"' : '';
    html += '<tr' + cls + ' onclick="selectRow(' + i + ')">'
      + '<td style="color:#94a3b8">' + (i+1) + '</td>'
      + '<td style="font-family:Consolas;font-weight:600;color:#065f46">' + r.barcode + '</td>'
      + '<td><input id="r_itemcode_' + i + '" value="' + esc(r.itemcode) + '" style="text-transform:uppercase" onchange="onCellChange(' + i + ',\'itemcode\',this.value)" onkeydown="cellNav(event,' + i + ')"></td>'
      + '<td style="font-size:9px;color:#64748b;text-align:left;padding:0 3px">' + esc(r.itemname) + '</td>'
      + '<td><select id="r_subgrp_' + i + '" onchange="onCellChange(' + i + ',\'subgrp\',this.value)">' + sgOpts.replace('value="' + esc(r.subgrp) + '"', 'value="' + esc(r.subgrp) + '" selected') + '</select></td>'
      + '<td><input id="r_size_' + i + '" value="' + esc(r.size) + '" style="text-transform:uppercase" onchange="onCellChange(' + i + ',\'size\',this.value)"></td>'
      + '<td><select id="r_model_' + i + '" onchange="onCellChange(' + i + ',\'model\',this.value)">' + mdOpts.replace('value="' + esc(r.model) + '"', 'value="' + esc(r.model) + '" selected') + '</select></td>'
      + '<td><input id="r_qty_' + i + '" type="number" value="' + r.qty + '" class="right" onchange="onCellChange(' + i + ',\'qty\',this.value)"></td>'
      + '<td><input id="r_weight_' + i + '" type="number" step="0.001" value="' + fmtN(r.weight,3) + '" class="right" onchange="onCellChange(' + i + ',\'weight\',this.value)"></td>'
      + '<td><input id="r_stwgt_' + i + '" type="number" step="0.001" value="' + fmtN(r.stwgt,3) + '" class="right" onchange="onCellChange(' + i + ',\'stwgt\',this.value)"></td>'
      + '<td style="font-weight:600;text-align:right;padding:0 4px">' + fmtN(r.weight - r.stwgt, 3) + '</td>'
      + '<td><input id="r_stprice_' + i + '" type="number" step="0.01" value="' + fmtN(r.stprice,2) + '" class="right" onchange="onCellChange(' + i + ',\'stprice\',this.value)"></td>'
      + '<td><input id="r_vaperc_' + i + '" type="number" step="0.01" value="' + fmtN(r.vaperc,2) + '" class="right" onchange="onCellChange(' + i + ',\'vaperc\',this.value)"></td>'
      + '<td><input id="r_minvap_' + i + '" type="number" step="0.01" value="' + fmtN(r.minvap,2) + '" class="right" onchange="onCellChange(' + i + ',\'minvap\',this.value)"></td>'
      + '<td><input id="r_stickerwgt_' + i + '" type="number" step="0.001" value="' + fmtN(r.stickerwgt,3) + '" class="right" onchange="onCellChange(' + i + ',\'stickerwgt\',this.value)"></td>'
      + '<td><input id="r_mcrate_' + i + '" type="number" step="0.01" value="' + fmtN(r.mcrate,2) + '" class="right" onchange="onCellChange(' + i + ',\'mcrate\',this.value)"></td>'
      + '<td><input id="r_mcamt_' + i + '" type="number" step="0.01" value="' + fmtN(r.mcamt,2) + '" class="right" onchange="onCellChange(' + i + ',\'mcamt\',this.value)"></td>'
      + '<td><input id="r_smithmcrate_' + i + '" type="number" step="0.01" value="' + fmtN(r.smithmcrate,2) + '" class="right" onchange="onCellChange(' + i + ',\'smithmcrate\',this.value)"></td>'
      + '<td><input id="r_huid_' + i + '" value="' + esc(r.huid) + '" style="text-transform:uppercase" maxlength="20" onchange="onCellChange(' + i + ',\'huid\',this.value)"></td>'
      + '<td><input id="r_wastage_' + i + '" type="number" step="0.001" value="' + fmtN(r.wastage,3) + '" class="right" onchange="onCellChange(' + i + ',\'wastage\',this.value)"></td>'
      + '</tr>';
  }
  tb.innerHTML = html;
  updateTotals();
}

function onCellChange(idx, field, val) {
  if (idx < 0 || idx >= rows.length) return;
  if (['qty'].includes(field)) {
    rows[idx][field] = parseInt(val) || 0;
  } else if (['weight','stwgt','stprice','vaperc','minvap','stickerwgt','mcrate','mcamt','smithmcrate','wastage'].includes(field)) {
    rows[idx][field] = parseFloat(val) || 0;
  } else {
    rows[idx][field] = val.trim();
  }

  if (field === 'itemcode') {
    var code = val.trim().toUpperCase();
    rows[idx].itemcode = code;
    // Lookup item name
    for (var i = 0; i < ITEMS_LOOKUP.length; i++) {
      if ((ITEMS_LOOKUP[i].code || '').toUpperCase().trim() === code) {
        rows[idx].itemname = ITEMS_LOOKUP[i].name || '';
        break;
      }
    }
    renderGrid();
  }

  updateTotals();
}

function cellNav(e, idx) {
  if (e.key === 'Enter') {
    e.preventDefault();
    // Move to weight field in same row
    var wf = document.getElementById('r_weight_' + idx);
    if (wf) wf.focus();
  }
}

function updateTotals() {
  var cnt = rows.length, tqty = 0, twgt = 0, tstw = 0, tstp = 0;
  for (var i = 0; i < rows.length; i++) {
    tqty += rows[i].qty || 0;
    twgt += rows[i].weight || 0;
    tstw += rows[i].stwgt || 0;
    tstp += rows[i].stprice || 0;
  }
  document.getElementById('ftRows').textContent = cnt;
  document.getElementById('ftQty').textContent = tqty;
  document.getElementById('ftWgt').textContent = twgt.toFixed(3);
  document.getElementById('ftStw').textContent = tstw.toFixed(3);
  document.getElementById('ftNet').textContent = (twgt - tstw).toFixed(3);
  document.getElementById('ftStp').textContent = tstp.toFixed(2);
  document.getElementById('infoCount').textContent = cnt;
  document.getElementById('infoQty').textContent = tqty;
  document.getElementById('infoWgt').textContent = twgt.toFixed(3);
}

// ─── Item search ─────────────────────────────────────────────────────────────
var itemTimer = null;
function itemSearch(el) {
  clearTimeout(itemTimer);
  var q = el.value.trim().toUpperCase();
  if (q.length < 1) { document.getElementById('itemDrop').style.display = 'none'; return; }
  itemTimer = setTimeout(function() {
    var matches = [];
    for (var i = 0; i < ITEMS_LOOKUP.length && matches.length < 15; i++) {
      var it = ITEMS_LOOKUP[i];
      if ((it.code || '').toUpperCase().indexOf(q) >= 0 || (it.name || '').toUpperCase().indexOf(q) >= 0) {
        matches.push(it);
      }
    }
    var dd = document.getElementById('itemDrop');
    if (matches.length === 0) { dd.style.display = 'none'; return; }
    var html = '';
    for (var j = 0; j < matches.length; j++) {
      html += '<div class="i-row" onclick="pickItem(\'' + esc(matches[j].code) + '\',\'' + esc(matches[j].name) + '\')">'
        + '<span class="i-code">' + esc(matches[j].code) + '</span>' + esc(matches[j].name) + '</div>';
    }
    dd.innerHTML = html;
    dd.style.display = 'block';
  }, 150);
}

function pickItem(code, name) {
  document.getElementById('hItemCode').value = code;
  document.getElementById('itemDrop').style.display = 'none';
}

function applyItemCode() {
  document.getElementById('itemDrop').style.display = 'none';
}

// ─── Save ────────────────────────────────────────────────────────────────────
function saveAll() {
  var validRows = [];
  for (var i = 0; i < rows.length; i++) {
    if (rows[i].itemcode && rows[i].weight > 0) validRows.push(rows[i]);
  }
  if (validRows.length === 0) { alert('No valid rows to save (need item code + weight > 0)'); return; }
  if (!confirm('Save ' + validRows.length + ' barcode(s)?')) return;

  var payload = {
    rows:      validRows,
    tdate:     document.getElementById('hDate').value,
    smcode:    document.getElementById('hSmcode').value,
    smithcode: document.getElementById('hSmith').value,
  };

  ajax('POST', '/api/barcode-multi/save', payload, function(d) {
    if (d.ok) {
      alert(d.message || 'Saved');
      if (d.docno) document.getElementById('hDocNo').textContent = d.docno;
      newDocument();
    } else {
      alert(d.message || 'Save failed');
    }
  });
}

// ─── New document ────────────────────────────────────────────────────────────
function newDocument() {
  rows = [];
  selIdx = -1;
  loadNextDocNo();
  loadNextBarcode(function() {
    addRow();
  });
}

// ─── Load existing doc ───────────────────────────────────────────────────────
function openLoadDoc() {
  var docno = prompt('Enter document number (e.g. BC/000015):');
  if (!docno || docno.trim() === '') return;

  ajax('GET', '/api/barcode-multi/load-document?docno=' + encodeURIComponent(docno.trim()), null, function(d) {
    if (!d.ok) { alert(d.message || 'Not found'); return; }
    rows = d.rows || [];
    // Resolve item names
    for (var i = 0; i < rows.length; i++) {
      for (var j = 0; j < ITEMS_LOOKUP.length; j++) {
        if ((ITEMS_LOOKUP[j].code || '').toUpperCase().trim() === rows[i].itemcode.toUpperCase()) {
          rows[i].itemname = ITEMS_LOOKUP[j].name || '';
          break;
        }
      }
    }
    selIdx = rows.length > 0 ? 0 : -1;
    document.getElementById('hDocNo').textContent = docno.trim();
    if (d.tdate) document.getElementById('hDate').value = d.tdate.slice(0, 10);
    if (d.smcode) document.getElementById('hSmcode').value = d.smcode;
    if (d.smith) document.getElementById('hSmith').value = d.smith;
    renderGrid();
  });
}

// ─── Set defaults ────────────────────────────────────────────────────────────
function setDefaults() {
  alert('Default item set to: ' + document.getElementById('hItemCode').value);
}

// ─── Keyboard shortcuts ──────────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (e.key === 'F9') {
    e.preventDefault();
    saveAll();
  }
  if (e.key === 'Insert' || (e.ctrlKey && e.key === 'n')) {
    e.preventDefault();
    addRow();
  }
  if (e.key === 'Delete' && e.ctrlKey) {
    e.preventDefault();
    deleteRow();
  }
});

// ─── Helpers ─────────────────────────────────────────────────────────────────
function esc(s) {
  if (s === null || s === undefined) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtN(n, d) {
  return (parseFloat(n) || 0).toFixed(d || 2);
}
</script>
</body>
</html>
