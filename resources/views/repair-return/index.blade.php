<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#e6eef3;color:#1f2937}
.wrap{padding:8px}
.bar{background:#1a3a4a;color:#b8e6ff;padding:8px 10px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.grid{display:grid;grid-template-columns:110px 1fr 110px 1fr 110px 1fr;gap:5px;background:#dce8f0;border:1px solid #99bdd0;padding:8px;font-size:13px}
.grid label{font-weight:700;text-align:right;padding-right:4px;line-height:30px}
.grid input,.grid select{height:30px;border:1px solid #99bdd0;border-radius:4px;padding:0 8px;font-size:13px}
.btn{height:32px;padding:0 14px;border:1px solid #6a9ab8;background:#d0e8f5;border-radius:4px;cursor:pointer;font-weight:700;font-size:12px}
.btn:hover{background:#b8d8ee}
.btn-save{background:#2d7d46;color:#fff;border-color:#256b3a}
.btn-save:hover{background:#256b3a}
.btn-cancel{background:#c0392b;color:#fff;border-color:#a33025}
.btn-cancel:hover{background:#a33025}
.tbl{width:100%;border-collapse:collapse;margin-top:6px;background:#fff;font-size:12px}
.tbl th,.tbl td{border:1px solid #bbb;padding:3px;text-align:center}
.tbl th{background:#1a3a4a;color:#b8e6ff;font-size:11px;white-space:nowrap}
.tbl input{width:100%;height:26px;border:0;padding:0 4px;box-sizing:border-box;text-align:right;font-size:12px}
.tbl input.txt{text-align:left}
.tbl tr:nth-child(even){background:#f0f7fb}
.tbl tr.sel{background:#b8e6ff !important}
.rowBtns{margin-top:4px;display:flex;gap:6px}
.billing{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}
.bill-panel{display:grid;grid-template-columns:110px 1fr;gap:4px;background:#dce8f0;border:1px solid #99bdd0;padding:8px;border-radius:4px;font-size:13px}
.bill-panel label{font-weight:700;text-align:right;line-height:30px}
.bill-panel input{height:30px;border:1px solid #99bdd0;border-radius:4px;padding:0 8px;text-align:right;font-size:13px}
.footer{margin-top:8px;display:flex;gap:8px;align-items:center}
.msg{margin-left:10px;color:#b91c1c;font-weight:700;white-space:pre-line}
.modal{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:100}
.modal.show{display:flex}
.panel{width:min(800px,95vw);max-height:80vh;overflow:auto;background:#fff;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.panel h4{margin:0;padding:10px 14px;background:#1a3a4a;color:#b8e6ff;font-size:14px}
.panel .pbody{padding:12px}
.readonly{background:#e8e8e8 !important}
/* Pre-dialog */
.predia{position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:200}
.predia-box{width:min(500px,90vw);background:#fff;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.3);overflow:hidden}
.predia-box h3{margin:0;padding:10px 14px;background:#1a3a4a;color:#b8e6ff}
.predia-box .body{padding:16px}
.predia-box .row{display:flex;gap:8px;align-items:center;margin-bottom:10px}
.predia-box .row label{width:120px;font-weight:700;text-align:right}
.predia-box .row input{flex:1;height:32px;border:1px solid #99bdd0;border-radius:4px;padding:0 8px}
.predia-box .btns{display:flex;gap:8px;justify-content:flex-end;padding:10px 16px;border-top:1px solid #ddd}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wrap">
  <div class="bar">
    <span>{{ $title }} @if($mode!=='new') - {{ strtoupper($mode) }} @endif</span>
    <span id="rmnoLabel" style="font-size:12px"></span>
  </div>

  <div class="grid">
    <label>Bill No</label>
    <input id="billNo" readonly class="readonly">
    <label>Remake Rcpt No</label>
    <input id="rbillno" readonly class="readonly">
    <label>Date</label>
    <input id="tDate" type="date">

    <label>Customer</label>
    <input id="custCode" placeholder="Code" style="max-width:120px">
    <label>Name</label>
    <input id="custName" readonly class="readonly" style="grid-column:span 1">
    <label>SMan</label>
    <select id="sman">
      <option value="">--Select--</option>
      @foreach($smen as $s)
      <option value="{{ $s->code }}">{{ $s->code }} - {{ $s->name }}</option>
      @endforeach
    </select>

    <label>Rate/gm</label>
    <input id="rateGm" readonly class="readonly" value="{{ number_format($grate,2) }}">
    <label>Rate/8 gm</label>
    <input id="rate8Gm" readonly class="readonly" value="{{ number_format($grate*8,2) }}">
    <div></div><div></div>
  </div>

  <table class="tbl" id="tbl">
    <thead><tr>
      <th style="width:90px">Item Code</th>
      <th>Item Name</th>
      <th style="width:50px">Qty</th>
      <th style="width:80px">Weight</th>
      <th style="width:75px">Stone Wgt</th>
      <th style="width:80px">Wt Differ</th>
      <th style="width:75px">Wastage</th>
      <th style="width:80px">Making Ch.</th>
      <th style="width:90px">Amount</th>
      <th style="width:80px">Rate</th>
      <th style="width:80px">Old Weight</th>
      <th style="width:75px">Old StWgt</th>
      <th style="width:60px">StkType</th>
    </tr></thead>
    <tbody id="tbody"></tbody>
    <tfoot><tr style="background:#1a3a4a;color:#b8e6ff;font-weight:700">
      <td></td><td></td>
      <td id="totQty">0</td>
      <td id="totWeight">0.000</td>
      <td id="totStone">0.000</td>
      <td id="totNetwgt">0.000</td>
      <td id="totWastage">0.000</td>
      <td id="totMcharge">0.00</td>
      <td id="totAmount">0.00</td>
      <td id="totRate">0.00</td>
      <td id="totOldwgt">0.000</td>
      <td id="totOldstwgt">0.000</td>
      <td></td>
    </tr></tfoot>
  </table>
  <div class="rowBtns">
    <button class="btn" id="addRow">+ Add Row</button>
    <button class="btn" id="delRow">- Delete Row</button>
  </div>

  <div class="billing">
    <div class="bill-panel">
      <label>Bill Total</label>
      <input id="billTotal" readonly class="readonly">
      <label>Received</label>
      <input id="rcvd" type="number" step="0.01" value="0">
    </div>
    <div class="bill-panel">
      <label>Tax %</label>
      <input id="taxPerc" type="number" step="0.01" value="0">
      <label>Tax Amt</label>
      <input id="taxAmt" type="number" step="0.01" value="0">
      <label>Discount</label>
      <input id="discount" type="number" step="0.01" value="0">
      <label>Balance</label>
      <input id="balance" readonly class="readonly">
    </div>
  </div>

  <div class="footer">
    <button class="btn btn-save" id="saveBtn">Save (F9)</button>
    @if($mode==='cancel')
    <button class="btn btn-cancel" id="cancelBtn">Cancel Bill</button>
    @endif
    <button class="btn" id="helpBtn">Help (F1)</button>
    <button class="btn" id="closeBtn">Close</button>
    <div class="msg" id="msg"></div>
  </div>
</div>

<!-- Pre-Dialog: Select Remake Receipt No -->
<div class="predia" id="preDia" style="display:none">
  <div class="predia-box">
    <h3>Select Remake Receipt No</h3>
    <div class="body">
      <div class="row">
        <label>Remake No</label>
        <input id="pdRmNo" placeholder="Enter Remake Rcpt No">
        <button class="btn" id="pdHelp" title="Search receipts">?</button>
      </div>
    </div>
    <div class="btns">
      <button class="btn btn-save" id="pdOk">OK</button>
      <button class="btn" id="pdExit">Exit</button>
    </div>
  </div>
</div>

<!-- Help Modal: Search existing repair return bills -->
<div class="modal" id="helpModal">
  <div class="panel">
    <h4>Repair Return Bills</h4>
    <div class="pbody">
      <div style="display:flex;gap:6px;margin-bottom:8px">
        <input id="q" style="flex:1;height:30px" placeholder="Search by bill no, customer...">
        <button class="btn" id="find">Find</button>
        <button class="btn" id="closeHelp">Close</button>
      </div>
      <table class="tbl"><thead><tr><th>Bill No</th><th>Date</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead><tbody id="helpRows"></tbody></table>
    </div>
  </div>
</div>

<!-- Receipt Help Modal: Search remake receipts for pre-dialog -->
<div class="modal" id="rcptModal">
  <div class="panel">
    <h4>Remake Receipts (RM1)</h4>
    <div class="pbody">
      <div style="display:flex;gap:6px;margin-bottom:8px">
        <input id="qRcpt" style="flex:1;height:30px" placeholder="Search receipts...">
        <button class="btn" id="findRcpt">Find</button>
        <button class="btn" id="closeRcpt">Close</button>
      </div>
      <table class="tbl"><thead><tr><th>Bill No</th><th>Date</th><th>Customer</th><th>Status</th></tr></thead><tbody id="rcptRows"></tbody></table>
    </div>
  </div>
</div>

<script>
const MODE   = @json($mode);
const GRATE  = {{ $grate }};
const SRATE  = {{ $srate }};
const DEFSTKTYPE = @json($defstktype);
const $  = id => document.getElementById(id);
const msg = t => $('msg').textContent = t || '';
const api = (u,o) => fetch(u,{...o, headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','Accept':'application/json',...(o?.headers||{})}});

let selRow = -1;
let loaded = false;

/* ── Pre-Dialog ─────────────────────────────────────────────────────────── */
if (MODE === 'new') {
  $('preDia').style.display = 'flex';
  setTimeout(() => $('pdRmNo').focus(), 100);
}

$('pdOk').onclick = async () => {
  const rmno = $('pdRmNo').value.trim().toUpperCase();
  if (!rmno) { alert('Enter Remake Receipt No'); return; }
  msg('Loading receipt...');
  const r = await api(`{{ url("/api/repair-return/load-receipt") }}?billno=${encodeURIComponent(rmno)}`).then(r=>r.json());
  if (!r.ok) { msg(r.message || 'Receipt not found'); return; }
  msg('');
  $('preDia').style.display = 'none';
  $('rbillno').value = rmno;
  $('custCode').value = r.custcode || '';
  $('custName').value = r.custname || '';
  // Load next doc no
  const nd = await api(`{{ url("/api/repair-return/next") }}`).then(r=>r.json());
  if (nd.ok) $('billNo').value = nd.doc_no;
  $('tDate').value = new Date().toISOString().slice(0,10);
  // Populate items
  clearRows();
  (r.items || []).forEach(it => addRow(it));
  calcTotals();
};

$('pdExit').onclick = () => {
  window.parent?.postMessage?.({type:'closeModule'},'*');
  window.close();
};

$('pdHelp').onclick = () => { $('rcptModal').classList.add('show'); searchReceipts(''); $('qRcpt').focus(); };
$('findRcpt').onclick = () => searchReceipts($('qRcpt').value);
$('qRcpt').onkeydown = e => { if(e.key==='Enter') searchReceipts($('qRcpt').value); };
$('closeRcpt').onclick = () => $('rcptModal').classList.remove('show');

async function searchReceipts(q) {
  const r = await api(`{{ url("/api/repair-return/search-receipts") }}?q=${encodeURIComponent(q)}`).then(r=>r.json());
  const tb = $('rcptRows');
  tb.innerHTML = '';
  (r.rows||[]).forEach(o => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${o.billno}</td><td>${o.tdate}</td><td>${o.custcode} - ${o.custname}</td><td>${o.status===1?'Active':'?'}</td>`;
    tr.style.cursor = 'pointer';
    tr.onclick = () => { $('pdRmNo').value = o.billno; $('rcptModal').classList.remove('show'); };
    tb.appendChild(tr);
  });
}

/* ── Edit/Cancel mode: show help to select existing RM4 bill ──────────── */
if (MODE === 'edit' || MODE === 'cancel' || MODE === 'reprint') {
  $('preDia').style.display = 'none';
  $('helpModal').classList.add('show');
  searchBills('');
  setTimeout(() => $('q').focus(), 100);
}

/* ── Help Modal ─────────────────────────────────────────────────────────── */
$('helpBtn').onclick = () => { $('helpModal').classList.add('show'); searchBills(''); $('q').focus(); };
$('find').onclick = () => searchBills($('q').value);
$('q').onkeydown = e => { if(e.key==='Enter') searchBills($('q').value); };
$('closeHelp').onclick = () => $('helpModal').classList.remove('show');

async function searchBills(q) {
  const r = await api(`{{ url("/api/repair-return/search") }}?q=${encodeURIComponent(q)}`).then(r=>r.json());
  const tb = $('helpRows');
  tb.innerHTML = '';
  (r.rows||[]).forEach(o => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${o.billno}</td><td>${o.tdate}</td><td>${o.custcode} - ${o.custname}</td><td>${o.amount.toFixed(2)}</td><td>${o.status===1?'Active':'?'}</td>`;
    tr.style.cursor = 'pointer';
    tr.onclick = () => { loadBill(o.billno); $('helpModal').classList.remove('show'); };
    tb.appendChild(tr);
  });
}

async function loadBill(billno) {
  msg('Loading...');
  const r = await api(`{{ url("/api/repair-return/get") }}?bill_no=${encodeURIComponent(billno)}`).then(r=>r.json());
  if (!r.ok) { msg(r.message || 'Not found'); return; }
  msg('');
  loaded = true;
  const m = r.master;
  $('billNo').value   = m.billno;
  $('rbillno').value  = m.rbillno || '';
  $('tDate').value    = m.tdate || '';
  $('custCode').value = m.custcode || '';
  $('custName').value = m.custname || '';
  $('sman').value     = m.sman || '';
  $('billTotal').value = m.amount.toFixed(2);
  $('discount').value  = m.discount.toFixed(2);
  $('rcvd').value      = m.rcvd.toFixed(2);
  $('taxPerc').value   = m.taxperc.toFixed(2);
  $('taxAmt').value    = m.taxamt.toFixed(2);
  clearRows();
  (r.rows || []).forEach(it => addRow(it));
  calcTotals();
  calcBalance();

  if (MODE === 'cancel' || MODE === 'reprint') {
    document.querySelectorAll('.tbl input').forEach(i => i.readOnly = true);
    ['discount','rcvd','taxPerc','taxAmt','sman','custCode'].forEach(id => { const el=$(id); if(el) el.disabled = true; });
  }
}

/* ── Grid ───────────────────────────────────────────────────────────────── */
function clearRows() { $('tbody').innerHTML = ''; selRow = -1; }

function addRow(d={}) {
  const tb = $('tbody');
  const tr = document.createElement('tr');
  const idx = tb.children.length;
  tr.innerHTML = `
    <td><input class="txt itemcode" value="${d.itemcode||''}"></td>
    <td><input class="txt itemname" value="${d.itemname||''}" readonly></td>
    <td><input class="qty" type="number" step="1" value="${d.qty??1}"></td>
    <td><input class="weight" type="number" step="0.001" value="${d.weight??0}"></td>
    <td><input class="stonewgt" type="number" step="0.001" value="${d.stonewgt??0}"></td>
    <td><input class="netwgt" type="number" step="0.001" value="${d.netwgt??0}"></td>
    <td><input class="wastage" type="number" step="0.001" value="${d.wastage??0}"></td>
    <td><input class="mcharge" type="number" step="0.01" value="${d.mcharge??0}"></td>
    <td><input class="amount" type="number" step="0.01" value="${d.amount??0}"></td>
    <td><input class="rate" type="number" step="0.01" value="${d.rate??0}"></td>
    <td><input class="oldwgt readonly" type="number" step="0.001" value="${d.oldwgt??0}" readonly style="background:#e8e8e8"></td>
    <td><input class="oldstwgt readonly" type="number" step="0.001" value="${d.oldstwgt??0}" readonly style="background:#e8e8e8"></td>
    <td><input class="txt stktype" value="${d.stktype||DEFSTKTYPE}" style="width:50px;text-align:center"></td>`;
  tr.onclick = () => { document.querySelectorAll('#tbody tr').forEach(r=>r.classList.remove('sel')); tr.classList.add('sel'); selRow=idx; };
  tb.appendChild(tr);

  // Item code blur -> lookup
  const icInput = tr.querySelector('.itemcode');
  icInput.addEventListener('blur', async function() {
    const code = this.value.trim().toUpperCase();
    if (!code) return;
    this.value = code;
    const r = await api(`{{ url("/api/repair-return/customer") }}?code=x`).catch(()=>null);
    // Lookup item name from items table
    try {
      const resp = await fetch(`{{ url("/api/remake-rcpt-memo-to-party/item") }}?code=${encodeURIComponent(code)}`,{headers:{'Accept':'application/json'}});
      const j = await resp.json();
      if (j.ok && j.item) {
        tr.querySelector('.itemname').value = j.item.name || '';
      }
    } catch(e) {}
  });

  // Weight/stonewgt change -> recalc netwgt (weight differ = weight - oldwgt)
  ['weight','stonewgt','oldwgt'].forEach(cls => {
    tr.querySelector('.'+cls).addEventListener('input', () => {
      const w    = parseFloat(tr.querySelector('.weight').value) || 0;
      const oldw = parseFloat(tr.querySelector('.oldwgt').value) || 0;
      tr.querySelector('.netwgt').value = (w - oldw).toFixed(3);
      calcRowAmount(tr);
      calcTotals();
    });
  });

  // Wastage/mcharge/rate change -> recalc amount
  ['wastage','mcharge','rate','qty'].forEach(cls => {
    tr.querySelector('.'+cls).addEventListener('input', () => { calcRowAmount(tr); calcTotals(); });
  });

  // Amount direct change -> recalc totals
  tr.querySelector('.amount').addEventListener('input', () => calcTotals());
}

function calcRowAmount(tr) {
  const weight  = parseFloat(tr.querySelector('.weight').value) || 0;
  const wastage = parseFloat(tr.querySelector('.wastage').value) || 0;
  const mcharge = parseFloat(tr.querySelector('.mcharge').value) || 0;
  const rate    = parseFloat(tr.querySelector('.rate').value) || 0;
  // amount = (weight + wastage) * rate / 10 + mcharge  (per gram pricing)
  // PB formula: amount computed, but user can also directly edit
  if (rate > 0) {
    const amt = ((weight + wastage) * rate) + mcharge;
    tr.querySelector('.amount').value = amt.toFixed(2);
  }
}

function calcTotals() {
  let tQty=0, tW=0, tS=0, tN=0, tWst=0, tMc=0, tAmt=0, tRate=0, tOw=0, tOs=0;
  document.querySelectorAll('#tbody tr').forEach(tr => {
    tQty  += parseInt(tr.querySelector('.qty').value) || 0;
    tW    += parseFloat(tr.querySelector('.weight').value) || 0;
    tS    += parseFloat(tr.querySelector('.stonewgt').value) || 0;
    tN    += parseFloat(tr.querySelector('.netwgt').value) || 0;
    tWst  += parseFloat(tr.querySelector('.wastage').value) || 0;
    tMc   += parseFloat(tr.querySelector('.mcharge').value) || 0;
    tAmt  += parseFloat(tr.querySelector('.amount').value) || 0;
    tRate += parseFloat(tr.querySelector('.rate').value) || 0;
    tOw   += parseFloat(tr.querySelector('.oldwgt').value) || 0;
    tOs   += parseFloat(tr.querySelector('.oldstwgt').value) || 0;
  });
  $('totQty').textContent      = tQty;
  $('totWeight').textContent   = tW.toFixed(3);
  $('totStone').textContent    = tS.toFixed(3);
  $('totNetwgt').textContent   = tN.toFixed(3);
  $('totWastage').textContent  = tWst.toFixed(3);
  $('totMcharge').textContent  = tMc.toFixed(2);
  $('totAmount').textContent   = tAmt.toFixed(2);
  $('totRate').textContent     = tRate.toFixed(2);
  $('totOldwgt').textContent   = tOw.toFixed(3);
  $('totOldstwgt').textContent = tOs.toFixed(3);

  $('billTotal').value = tAmt.toFixed(2);
  calcBalance();
}

function calcBalance() {
  const bamt = parseFloat($('billTotal').value) || 0;
  const tax  = parseFloat($('taxAmt').value) || 0;
  const disc = parseFloat($('discount').value) || 0;
  const rcvd = parseFloat($('rcvd').value) || 0;
  $('balance').value = (bamt + tax - disc - rcvd).toFixed(2);
}

// Tax % change
$('taxPerc').addEventListener('input', () => {
  const bamt = parseFloat($('billTotal').value) || 0;
  const perc = parseFloat($('taxPerc').value) || 0;
  const tax  = Math.round(Math.abs(bamt) * perc / 100);
  $('taxAmt').value = tax.toFixed(2);
  // Auto-set received = billtotal + tax - discount
  const disc = parseFloat($('discount').value) || 0;
  $('rcvd').value = (bamt + tax - disc).toFixed(2);
  calcBalance();
});

$('taxAmt').addEventListener('input', () => {
  const bamt = parseFloat($('billTotal').value) || 0;
  const tax  = parseFloat($('taxAmt').value) || 0;
  const disc = parseFloat($('discount').value) || 0;
  $('rcvd').value = (bamt + tax - disc).toFixed(2);
  calcBalance();
});

$('discount').addEventListener('input', () => {
  const bamt = parseFloat($('billTotal').value) || 0;
  const tax  = parseFloat($('taxAmt').value) || 0;
  const disc = parseFloat($('discount').value) || 0;
  $('rcvd').value = (bamt + tax - disc).toFixed(2);
  calcBalance();
});

$('rcvd').addEventListener('input', calcBalance);

$('addRow').onclick = () => { addRow(); };
$('delRow').onclick = () => {
  const rows = $('tbody').children;
  if (selRow >= 0 && selRow < rows.length) {
    rows[selRow].remove();
    selRow = -1;
    calcTotals();
  } else if (rows.length > 0) {
    rows[rows.length-1].remove();
    calcTotals();
  }
};

/* ── Customer lookup ────────────────────────────────────────────────────── */
$('custCode').addEventListener('blur', async function() {
  const code = this.value.trim().toUpperCase();
  if (!code) { $('custName').value = ''; return; }
  this.value = code;
  const r = await api(`{{ url("/api/repair-return/customer") }}?code=${encodeURIComponent(code)}`).then(r=>r.json()).catch(()=>null);
  if (r && r.ok) {
    $('custName').value = r.name || '';
  } else {
    $('custName').value = '';
    if (r?.message) msg(r.message);
  }
});

/* ── Save ───────────────────────────────────────────────────────────────── */
$('saveBtn').onclick = doSave;
async function doSave() {
  msg('');
  const rows = [];
  document.querySelectorAll('#tbody tr').forEach(tr => {
    const code = tr.querySelector('.itemcode').value.trim();
    if (!code) return;
    rows.push({
      itemcode: code,
      itemname: tr.querySelector('.itemname').value,
      qty:      parseInt(tr.querySelector('.qty').value) || 0,
      weight:   parseFloat(tr.querySelector('.weight').value) || 0,
      stonewgt: parseFloat(tr.querySelector('.stonewgt').value) || 0,
      netwgt:   parseFloat(tr.querySelector('.netwgt').value) || 0,
      wastage:  parseFloat(tr.querySelector('.wastage').value) || 0,
      mcharge:  parseFloat(tr.querySelector('.mcharge').value) || 0,
      amount:   parseFloat(tr.querySelector('.amount').value) || 0,
      rate:     parseFloat(tr.querySelector('.rate').value) || 0,
      cost:     0,
      stktype:  tr.querySelector('.stktype').value.trim(),
    });
  });

  if (rows.length === 0) { msg('No items to save'); return; }

  const payload = {
    mode:     MODE === 'edit' ? 'edit' : 'new',
    bill_no:  $('billNo').value,
    tdate:    $('tDate').value,
    custcode: $('custCode').value,
    custname: $('custName').value,
    sman:     $('sman').value,
    rbillno:  $('rbillno').value,
    amount:   parseFloat($('billTotal').value) || 0,
    discount: parseFloat($('discount').value) || 0,
    rcvd:     parseFloat($('rcvd').value) || 0,
    taxperc:  parseFloat($('taxPerc').value) || 0,
    taxamt:   parseFloat($('taxAmt').value) || 0,
    rows:     rows,
  };

  $('saveBtn').disabled = true;
  try {
    const r = await api(`{{ url("/api/repair-return/save") }}`, {
      method: 'POST',
      body: JSON.stringify(payload),
    }).then(r=>r.json());

    if (r.ok) {
      msg('Saved: ' + (r.bill_no || ''));
      if (MODE === 'new') {
        // Reset for next entry — show pre-dialog again
        setTimeout(() => {
          clearRows();
          $('billNo').value = '';
          $('rbillno').value = '';
          $('custCode').value = '';
          $('custName').value = '';
          $('billTotal').value = '';
          $('discount').value = '0';
          $('rcvd').value = '0';
          $('taxPerc').value = '0';
          $('taxAmt').value = '0';
          $('balance').value = '';
          $('preDia').style.display = 'flex';
          $('pdRmNo').value = '';
          $('pdRmNo').focus();
          msg('');
        }, 1500);
      }
    } else {
      msg(r.message || 'Save failed');
    }
  } catch(e) {
    msg('Error: ' + e.message);
  }
  $('saveBtn').disabled = false;
}

/* ── Cancel Bill ────────────────────────────────────────────────────────── */
if ($('cancelBtn')) {
  $('cancelBtn').onclick = async () => {
    const billno = $('billNo').value.trim();
    if (!billno) { msg('No bill loaded'); return; }
    if (!confirm('Cancel this bill? This will reverse all stock and balance changes.')) return;
    const r = await api(`{{ url("/api/repair-return/cancel") }}`, {
      method: 'POST',
      body: JSON.stringify({ bill_no: billno }),
    }).then(r=>r.json());
    msg(r.message || (r.ok ? 'Cancelled' : 'Failed'));
    if (r.ok) {
      clearRows();
      $('billNo').value = '';
      calcTotals();
    }
  };
}

$('closeBtn').onclick = () => {
  window.parent?.postMessage?.({type:'closeModule'},'*');
  window.close();
};

/* ── Keyboard ───────────────────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); doSave(); }
  if (e.key === 'F1') { e.preventDefault(); $('helpBtn').click(); }
  if (e.key === 'Escape') {
    if ($('rcptModal').classList.contains('show')) { $('rcptModal').classList.remove('show'); return; }
    if ($('helpModal').classList.contains('show')) { $('helpModal').classList.remove('show'); return; }
  }
});

const qs = new URLSearchParams(window.location.search);
const qsBillNo = qs.get('bill_no');
if (qsBillNo) {
  $('preDia').style.display = 'none';
  loadBill(qsBillNo);
  if (qs.get('autoprint') === '1') setTimeout(()=>window.print(), 800);
}
</script>
</body>
</html>
