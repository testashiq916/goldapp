<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
body { font-family: Arial, sans-serif; font-size: 13px; margin: 0; background: #f4f0e8; color: #1a1a1a; }
#topbar { background: {{ $type === 'J' ? '#8b6914' : ($type === 'C' ? '#606060' : '#2c2410') }}; color: #f5d87a; padding: 8px 12px; display: flex; gap: 10px; align-items: center; }
#topbar h2 { margin: 0; flex: 1; font-size: 15px; }
.btn { padding: 5px 10px; border: 0; border-radius: 3px; cursor: pointer; font-weight: bold; font-size: 12px; }
.btn:disabled { cursor: wait; opacity: .75; }
.btn .spinner { display: none; width: 12px; height: 12px; margin-right: 6px; border: 2px solid rgba(255,255,255,.45); border-top-color: #fff; border-radius: 50%; vertical-align: -2px; animation: spin .75s linear infinite; }
.btn.loading .spinner { display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
.btn-save { background: #27ae60; color: #fff; }
.btn-danger { background: #c0392b; color: #fff; }
.btn-lite { background: #e7d6aa; color: #222; }
#master { padding: 10px; background: #fff8e8; border-bottom: 2px solid #c8a23a; display: grid; grid-template-columns: repeat(6, minmax(130px, 1fr)); gap: 8px; }
label { display: block; font-size: 11px; font-weight: bold; color: #8b6914; margin-bottom: 3px; }
input, select, textarea { width: 100%; border: 1px solid #d4b896; border-radius: 3px; padding: 4px 6px; font-size: 12px; box-sizing: border-box; }
input[readonly] { background: #f0ede5; }
#gridwrap { padding: 8px; overflow: auto; }
table { border-collapse: collapse; width: 100%; min-width: 1200px; background: #fff; }
th, td { border: 1px solid #ddd; padding: 3px; }
th { background: #2c2410; color: #f5d87a; font-size: 11px; white-space: nowrap; }
td input, td select { border: 0; background: transparent; padding: 2px 4px; }
tfoot td { background: #f0ede5; padding: 4px 3px; }
.num input { text-align: right; }
#summary { display: flex; gap: 18px; padding: 8px 10px; background: #eaf0fb; border-top: 1px solid #b0c4de; border-bottom: 1px solid #b0c4de; flex-wrap: wrap; }
.box { padding: 8px 10px; }
#warn { color: #b00020; font-weight: bold; }
#saveStatus { color: #1d6f42; font-weight: bold; line-height: 24px; display: none; }
.ac-wrap { position: relative; }
.ac-drop { position: absolute; top: 100%; left: 0; right: 0; min-width: 220px; background: #fff; border: 1px solid #c8a23a; z-index: 300; max-height: 200px; overflow-y: auto; box-shadow: 0 3px 8px rgba(0,0,0,.25); display: none; }
.ac-drop div { padding: 5px 10px; cursor: pointer; font-size: 12px; white-space: nowrap; }
.ac-drop div:hover, .ac-drop div.hi { background: #f5d87a; }
.smith-wrap { position: relative; }
.smith-row { display: flex; gap: 4px; }
.smith-row input { flex: 1; }
.smith-row .btn-plus { flex-shrink: 0; padding: 4px 9px; font-size: 14px; line-height: 1; background: #c8a23a; color: #fff; border: 0; border-radius: 3px; cursor: pointer; }
</style>
<link rel="stylesheet" href="{{ asset('css/transaction-readable.css') }}?v={{ @filemtime(public_path('css/transaction-readable.css')) }}">
@include('partials.print-layout-head')
</head>
<body>
<div id="topbar">
  <h2>{{ $title }}</h2>
  <button class="btn btn-lite" id="btnPrev">&lt;</button>
  <button class="btn btn-lite" id="btnNext">&gt;</button>
  <button class="btn btn-lite" id="btnPrint">Print</button>
</div>

<input type="hidden" id="hSlno" value="0">
<input type="hidden" id="hSaded" value="A">
<input type="hidden" id="hIstype" value="{{ $type }}">
<input type="hidden" id="hEb" value="B">
<input type="hidden" id="hModule" value="{{ $moduleId }}">

<div id="master">
  <div><label>Doc No</label><input id="docno" value="{{ $billNo }}" readonly></div>
  <div class="smith-wrap">
    <label>{{ $type === 'J' ? 'Jewellery' : (in_array($type, ['C', 'R'], true) ? 'Customer' : 'Goldsmith') }} Code</label>
    <div class="smith-row">
      <input id="custcode" autocomplete="off">
      <button class="btn-plus" onclick="openCreateSmith()" title="Create New {{ $type === 'J' ? 'Jewellery' : (in_array($type, ['C', 'R'], true) ? 'Customer' : 'Goldsmith') }}">+</button>
    </div>
    <div id="smithDrop" class="ac-drop"></div>
  </div>
  <div class="smith-wrap" style="grid-column: span 2;">
    <label>Name</label>
    <input id="custname" autocomplete="off">
    <div id="nameDrop" class="ac-drop"></div>
  </div>
  <div><label>Date</label><input id="tdate" type="date"></div>
  <div><label>Rate</label><input id="rate" type="number" step="0.001" value="{{ $goldRate }}"></div>
  <div class="ac-wrap">
    <label>SMan</label>
    <input id="smcode" autocomplete="off">
    <div id="smanDrop" class="ac-drop"></div>
  </div>
  <div><label>SMan Name</label><input id="smname" readonly></div>
  <div><label>Ref No</label><input id="refno"></div>
  <div><label>Lot No</label><input id="lotno"></div>
  <div><label>Op Bal</label><input id="opbal" readonly></div>
  <div><label>Mobile</label><input id="mobile" readonly></div>
  <div><label>Due Date</label><input id="duedate" type="date"></div>
</div>

<div id="gridwrap">
  <table>
    <thead>
      <tr>
        <th>#</th><th>Item Code</th><th>Item Name</th><th>Qty</th><th>Weight</th><th>Stone Wgt</th><th>Give/Rcvd</th><th>Touch</th><th>Wastage</th><th>Net Wgt</th><th>TM Wgt</th><th>Stone Price</th><th>MC</th><th>HMC</th><th>TP</th><th>Model</th><th>Remark</th>
      </tr>
    </thead>
    <tbody id="detailBody"></tbody>
    <tfoot>
      <tr>
        <td></td>
        <td><button class="btn btn-lite" id="btnAdd" style="width:100%">+ Add Row</button></td>
        <td><button class="btn btn-danger" id="btnDel" style="width:100%">- Delete Row</button></td>
        <td colspan="14"></td>
      </tr>
    </tfoot>
  </table>
</div>

<div id="summary">
  <div>Total Issued: <b id="twgtissued">0.000</b></div>
  <div>Total Received: <b id="twgtrcvd">0.000</b></div>
  <div>Total MC: <b id="tmc">0.00</b></div>
  <div>Wgt Balance: <b id="wgtbal">0.000</b></div>
</div>

<div class="box" style="background:#fff8e8; border-top:2px solid #c8a23a;">
  <div style="display:grid;grid-template-columns:repeat(8,minmax(120px,1fr));gap:8px;">
    <div><label>Acid Charge</label><input id="acidcharge" type="number" step="0.01" value="0"></div>
    <div><label>Discount</label><input id="discount" type="number" step="0.01" value="0"></div>
    <div><label>TDS %</label><input id="tdsperc" type="number" step="0.01" value="0"></div>
    <div><label>TDS Amt</label><input id="tdsamt" type="number" step="0.01" value="0"></div>
    <div><label>Tax %</label><input id="taxperc" type="number" step="0.01" value="0"></div>
    <div><label>Tax Amt</label><input id="taxamt" type="number" step="0.01" value="0"></div>
    <div><label>TCS %</label><input id="tcsperc" type="number" step="0.01" value="0"></div>
    <div><label>TCS Amt</label><input id="tcsamt" type="number" step="0.01" value="0"></div>
    <div><label>Paid/Receipt</label><select id="pmntrcpt"><option value="PAID">PAID</option><option value="RECEIVED">RECEIPT</option></select></div>
    <div><label>Amount</label><input id="paid" type="number" step="0.01" value="0"></div>
    <div><label>Cash/Bank</label><select id="cbcode">
      @foreach($cashBanks as $b)
        <option value="{{ $b['code'] }}">{{ $b['code'] }} - {{ $b['name'] }}</option>
      @endforeach
    </select></div>
    <div><label>Amt Balance</label><input id="balance" readonly></div>
    <div><label>Cl Balance</label><input id="clbalance" readonly></div>
    <div><label>Person</label><input id="person"></div>
    <div><label>State</label><input id="statecode" value="32"></div>
    <div><label>Place of Supply</label><input id="placeos"></div>
    <div><label>Transport</label><input id="transportmode"></div>
    <div><label>Vehicle</label><input id="vehno"></div>
    <div><label>Purpose</label><input id="purpose"></div>
    <div style="grid-column:span 3;"><label>Note</label><textarea id="note" rows="2"></textarea></div>
    <div style="display:flex;align-items:end;padding-bottom:4px;"><label style="margin:0;white-space:nowrap;"><input type="checkbox" id="createbc" style="width:auto;margin-right:4px;" {{ ($smithCfg['CreateBCodeInSmithEntry'] ?? 'N') === 'Y' ? 'checked' : '' }}> Create Barcode</label></div>
  </div>
  <div id="warn"></div>
  <div style="padding:8px 0;display:flex;gap:10px;">
    <button class="btn {{ $mode === 'cancel' ? 'btn-danger' : 'btn-save' }}" id="btnSave2"><span class="spinner" aria-hidden="true"></span><span class="btn-text">{{ $mode === 'cancel' ? 'Cancel (F9)' : 'Save (F9)' }}</span></button>
    <button class="btn btn-danger" id="btnClose2">Close</button>
    <span id="saveStatus"></span>
  </div>
</div>

<!-- Global item-name autocomplete dropdown (fixed so it escapes overflow:auto) -->
<div id="itemDropGlobal" style="position:fixed;z-index:9999;display:none;min-width:240px;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #c8a23a;border-radius:3px;box-shadow:0 3px 10px rgba(0,0,0,.28);font-size:12px;"></div>

<script>
const API = "{{ url('/api/goldsmith-transactions') }}";
const PRINT_URL = "{{ url('/goldsmith-transactions-print') }}";
const PAGE_MODE = @json($mode);
const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
const State = { rows: [], dopwgt: 0, dopamt: 0, mcrate: 0, smithWastage: 0, diconvtouch: 100, idecround: 3, imcdecround: 2, dideftouch: 0 };
const SMAN_LIST = @json(array_values(array_map(fn($s) => ['code' => $s->code, 'name' => $s->name], $salesmen)));
const SMAN_MAP = Object.fromEntries(SMAN_LIST.map(s => [String(s.code || '').trim().toUpperCase(), s.name || '']));
const GOLD_RATE = {{ $goldRate }};
const SMITH_CFG = {
  addWastageToMC:  '{{ $smithCfg["SmithAddWastageToMCAmt"] ?? "N" }}' === 'Y',
  mcOnNetWgt:      '{{ $smithCfg["SmithMcOnNetWgt"] ?? "N" }}' === 'Y',
  smithIssueWastage:'{{ $smithCfg["SmithIssueWastage"] ?? "Y" }}' === 'Y',
  defStkType:      '{{ $smithCfg["SmithDefStkType"] ?? "S" }}',
  mcTruncate:      '{{ $smithCfg["SmithMcOnTruncateWgt"] ?? "N" }}' === 'Y',
  touchToEntry:    '{{ $smithCfg["TouchToSmithEntry"] ?? "N" }}' === 'Y',
  smithWastagePerc: {{ $smithWastagePerc }},
};
let saveBusy = false;

function emptyRow(){ return {itemcode:'',itemname:'',qty:0,weight:0,stonewgt:0,givrec:'',touch:0,wastage:0,manualWastage:false,netwgt:0,stoneprice:0,mcharge:0,hmc:0,tp:0,model:'',remark:'',ordno:'',orditem:'',stktype:'',touchnote:'',bcode:0,smithmc:0,stktouch:100,wgtamt:0,sva:0,sstprice:0,bcstk:'Y'}; }
function e(s){ return String(s||'').replaceAll('"','&quot;').replaceAll('<','&lt;'); }
function f(n,d){ return Number(n||0).toFixed(d); }
function setSaveBusy(on, label){
  saveBusy = on;
  const btn = document.getElementById('btnSave2');
  const text = btn.querySelector('.btn-text');
  const status = document.getElementById('saveStatus');
  btn.disabled = on;
  btn.classList.toggle('loading', on);
  text.textContent = on ? label : (PAGE_MODE === 'cancel' ? 'Cancel (F9)' : 'Save (F9)');
  status.textContent = on ? label + ' Please wait...' : '';
  status.style.display = on ? 'inline' : 'none';
}

function renderTable(){
  // Save focused cell position before re-render (so Tab key works correctly)
  let _fRow = -1, _fCol = -1;
  const _act = document.activeElement;
  if (_act) {
    const _tr = _act.closest && _act.closest('#detailBody tr[data-idx]');
    if (_tr) {
      _fRow = parseInt(_tr.dataset.idx);
      const _td = _act.closest('td');
      if (_td) _fCol = Array.from(_tr.children).indexOf(_td) + 1;
    }
  }

  const b=document.getElementById('detailBody'); b.innerHTML='';
  State.rows.forEach((r,i)=>{
    const tr=document.createElement('tr'); tr.dataset.idx=i;
    tr.innerHTML=`
      <td>${i+1}</td>
      <td><input value="${e(r.itemcode)}" id="itemCodeInp_${i}" autocomplete="off"
            data-field="itemcode"
            oninput="itemDropSearch(${i},this.value,'code')"
            onkeydown="itemDropKey(event,${i})"
            onblur="itemDropBlur()"
            onchange="onItem(${i},this.value)"></td>
      <td><input value="${e(r.itemname)}" id="itemNameInp_${i}" autocomplete="off"
            data-field="itemname"
            oninput="itemDropSearch(${i},this.value)"
            onkeydown="itemDropKey(event,${i})"
            onblur="itemDropBlur()"
            onchange="setf(${i},'itemname',this.value)"></td>
      <td class="num"><input type="number" data-field="qty" value="${r.qty}" onchange="setn(${i},'qty',this.value)"></td>
      <td class="num"><input type="number" data-field="weight" step="0.001" value="${f(r.weight,3)}" onchange="chg(${i},'weight',this.value)"></td>
      <td class="num"><input type="number" data-field="stonewgt" step="0.001" value="${f(r.stonewgt,3)}" onchange="chg(${i},'stonewgt',this.value)"></td>
      <td><select data-field="givrec" onchange="chgGR(${i},this.value)"><option value=""></option><option value="G" ${r.givrec==='G'?'selected':''}>G</option><option value="R" ${r.givrec==='R'?'selected':''}>R</option></select></td>
      <td class="num"><input type="number" data-field="touch" step="0.001" value="${f(r.touch,3)}" onchange="chg(${i},'touch',this.value)"></td>
      <td class="num"><input type="number" data-field="wastage" step="0.001" value="${f(r.wastage,3)}" onchange="chg(${i},'wastage',this.value)"></td>
      <td class="num"><input type="number" step="0.001" value="${f(Math.max(0, Number(r.weight||0) - Number(r.stonewgt||0)),3)}" readonly tabindex="-1"></td>
      <td class="num"><input type="number" data-field="netwgt" step="0.001" value="${f(r.netwgt,3)}" onchange="setn(${i},'netwgt',this.value)"></td>
      <td class="num"><input type="number" data-field="stoneprice" step="0.01" value="${f(r.stoneprice,2)}" onchange="setn(${i},'stoneprice',this.value);sum()"></td>
      <td class="num"><input type="number" data-field="mcharge" step="0.01" value="${f(r.mcharge,2)}" onchange="setn(${i},'mcharge',this.value);sum()"></td>
      <td class="num"><input type="number" data-field="hmc" step="0.01" value="${f(r.hmc,2)}" onchange="setn(${i},'hmc',this.value);sum()"></td>
      <td class="num"><input type="number" data-field="tp" step="0.001" value="${f(r.tp,3)}" onchange="chg(${i},'tp',this.value)"></td>
      <td><input value="${e(r.model)}" data-field="model" onchange="setf(${i},'model',this.value)"></td>
      <td><input value="${e(r.remark)}" data-field="remark" onchange="setf(${i},'remark',this.value)" onkeydown="tabAddRow(event,${i})"></td>`;
    b.appendChild(tr);
  });
  sum();

  // Restore focus to the cell that had focus before re-render
  if (_fRow >= 0 && _fCol > 0) {
    const _sel = `#detailBody tr[data-idx="${_fRow}"] td:nth-child(${_fCol}) input, #detailBody tr[data-idx="${_fRow}"] td:nth-child(${_fCol}) select`;
    const _el = document.querySelector(_sel);
    if (_el) { _el.focus(); if (_el.tagName === 'INPUT') _el.select(); }
  }
}

function setf(i,k,v){ State.rows[i][k]=v; }
function setn(i,k,v){ State.rows[i][k]=Number(v||0); }
function tabAddRow(ev,i){ if(ev.key==='Tab'&&!ev.shiftKey&&i===State.rows.length-1){ ev.preventDefault(); State.rows.push(emptyRow()); renderTable(); focusCell(i+1,2); } }
function focusCell(row,col){ const el=document.querySelector(`#detailBody tr[data-idx="${row}"] td:nth-child(${col}) input`); if(el){ el.focus(); el.select(); } }

// ── Item Name autocomplete ────────────────────────────────────────────────────
let _itemSugList=[], _itemSugHi=-1, _itemSugRow=-1, _itemSugTimer=null;

let _itemSugSource='name';

function itemDropSearch(i, val, source='name') {
  _itemSugRow = i;
  _itemSugSource = source;
  clearTimeout(_itemSugTimer);
  const q = val.trim();
  if (!q) { itemDropClose(); return; }
  _itemSugTimer = setTimeout(async () => {
    const list = await fetchJson(`${API}/item-help?q=${encodeURIComponent(q)}`);
    _itemSugList = Array.isArray(list) ? list : [];
    _itemSugHi = -1;
    const drop = document.getElementById('itemDropGlobal');
    if (!_itemSugList.length) { drop.style.display = 'none'; return; }
    drop.innerHTML = _itemSugList.map((it, idx) =>
      `<div data-idx="${idx}" style="padding:5px 10px;cursor:pointer;border-bottom:1px solid #f0e8d0;white-space:nowrap;"
            onmouseenter="itemDropHi(${idx})"
            onmousedown="itemDropPick(event,${idx})">
        <b style="color:#8b6914">${e(it.code)}</b>
        <span style="margin-left:6px">${e(it.name)}</span>
      </div>`
    ).join('');
    // Position under the input
    const inp = document.getElementById(source === 'code' ? `itemCodeInp_${i}` : `itemNameInp_${i}`);
    if (inp) {
      const rc = inp.getBoundingClientRect();
      drop.style.left  = rc.left + 'px';
      drop.style.top   = (rc.bottom + 2) + 'px';
      drop.style.width = Math.max(rc.width, 240) + 'px';
    }
    drop.style.display = 'block';
  }, 180);
}

function itemDropHi(idx) {
  _itemSugHi = idx;
  document.querySelectorAll('#itemDropGlobal div[data-idx]').forEach((el, i) => {
    el.style.background = i === idx ? '#f5d87a' : '';
  });
}

function itemDropPick(ev, idx) {
  ev.preventDefault();
  const it = _itemSugList[idx];
  if (!it) return;
  itemDropClose();
  // Fill code + trigger full item load
  const inp = document.getElementById(`itemNameInp_${_itemSugRow}`);
  if (inp) inp.value = it.name;
  const codeInp = document.getElementById(`itemCodeInp_${_itemSugRow}`);
  if (codeInp) codeInp.value = it.code;
  setf(_itemSugRow, 'itemname', it.name);
  onItem(_itemSugRow, it.code);
}

function itemDropKey(ev, i) {
  const drop = document.getElementById('itemDropGlobal');
  if (drop.style.display === 'none') return;
  if (ev.key === 'ArrowDown') {
    ev.preventDefault();
    itemDropHi(Math.min(_itemSugHi + 1, _itemSugList.length - 1));
  } else if (ev.key === 'ArrowUp') {
    ev.preventDefault();
    itemDropHi(Math.max(_itemSugHi - 1, 0));
  } else if (ev.key === 'Enter' || ev.key === 'Tab') {
    if (_itemSugHi >= 0 && _itemSugList[_itemSugHi]) {
      ev.preventDefault();
      itemDropPick({ preventDefault: ()=>{} }, _itemSugHi);
    } else {
      itemDropClose();
    }
  } else if (ev.key === 'Escape') {
    itemDropClose();
  }
}

function itemDropBlur() {
  setTimeout(() => itemDropClose(), 200);
}

function itemDropClose() {
  document.getElementById('itemDropGlobal').style.display = 'none';
  _itemSugList = []; _itemSugHi = -1; _itemSugSource = 'name';
}

// ── PB-accurate row recalculation ────────────────────────────────────────────
function recalcRow(i) {
  const r = State.rows[i];
  const istype = gv('hIstype');
  const w  = Number(r.weight   || 0);
  const sw = Number(r.stonewgt || 0);
  const mud= Number(r.mud      || 0);
  const tp = Number(r.tp       || 0);
  const mcrate2 = Number(r.mcrate || 0);
  const sgr = r.givrec || '';
  const rate = Number(document.getElementById('rate').value || GOLD_RATE || 0);
  const pureBase = Math.max(0, w - sw);
  const base = w - sw - mud - tp;      // net base weight for calc
  const baseNoTp = w - sw - mud;       // for stktouch denom

  let wastage = Number(r.wastage || 0);
  // Auto-calc wastage: smithWastage from master is in grams (fixed), fallback to global % setting
  const shouldAutoCalcWastage =
    !SMITH_CFG.addWastageToMC &&
    !r.manualWastage &&
    (
      sgr === 'R' ||
      (istype === 'G' && sgr === 'G' && SMITH_CFG.smithIssueWastage)
    );

  if (shouldAutoCalcWastage) {
    if (State.smithWastage > 0) {
      // Master wastage is gram-wise (fixed grams per transaction)
      wastage = Math.round(State.smithWastage * 1000) / 1000;
      r.wastage = wastage;
    } else if (SMITH_CFG.smithWastagePerc > 0) {
      wastage = Math.round(base * SMITH_CFG.smithWastagePerc / 100 * 1000) / 1000;
      r.wastage = wastage;
    }
  }

  // MC calculation
  let mcharge = Number(r.mcharge || 0);
  if (mcrate2 > 0) {
    const mcBase = SMITH_CFG.mcOnNetWgt ? base : base;  // both same here; netwgt computed after
    const raw = SMITH_CFG.mcTruncate ? Math.trunc(mcBase) * mcrate2 : mcBase * mcrate2;
    mcharge = Math.round(raw * Math.pow(10, State.imcdecround)) / Math.pow(10, State.imcdecround);
    if (SMITH_CFG.addWastageToMC) mcharge += wastage * mcrate2;
    r.mcharge = mcharge;
  }

  // For istype='G' and givrec='G': mcharge = 0
  if (istype === 'G' && (sgr === 'G' || sgr === '')) {
    if (!SMITH_CFG.smithIssueWastage) { wastage = 0; r.wastage = 0; }
    r.mcharge = 0;
  }

  // net weight = touch weight + wastage
  const touch = Number(r.touch || 0);
  const touchBase = pureBase;
  let touchWgt;
  if (touch > 0) {
    touchWgt = (touchBase * touch) / 100;
  } else {
    touchWgt = touchBase;
  }
  let netwgt;
  netwgt = touchWgt + wastage;
  const idecround = Number(State.idecround || 3);
  r.netwgt = Math.round(netwgt * Math.pow(10, idecround)) / Math.pow(10, idecround);

  // stktouch: touch + (mc+stone) / baseNoTp / rate * 100
  if (touch > 0 && baseNoTp > 0 && rate > 0) {
    const stamt = Number(r.stoneprice || 0);
    r.stktouch = Math.round((touch + ((Number(r.mcharge||0) + stamt) / baseNoTp) / rate * 100) * 100) / 100;
  }
}

function chg(i,k,v){
  State.rows[i][k]=Number(v||0);
  if (k === 'wastage') State.rows[i].manualWastage = true;
  recalcRow(i);
  renderTable();
}

function chgGR(i,v){
  State.rows[i].givrec = v;
  const istype = gv('hIstype');
  if (istype === 'G') {
    if (v === 'G') { State.rows[i].mcharge = 0; State.rows[i].stoneprice = -Math.abs(State.rows[i].stoneprice||0); }
    else if (v === 'R') { State.rows[i].stoneprice = Math.abs(State.rows[i].stoneprice||0); }
  } else {
    if (v === 'G') { State.rows[i].mcharge = -Math.abs(State.rows[i].mcharge||0); State.rows[i].stoneprice = -Math.abs(State.rows[i].stoneprice||0); }
  }
  recalcRow(i); sum(); renderTable();
  refreshNewDocNo();
}

function usesReceiptDocNo(){
  return gv('hIstype') === 'J' && State.rows.some(r => String(r.givrec || '').toUpperCase() === 'R');
}

async function refreshNewDocNo(){
  if (gv('hSaded') !== 'A') return;
  const receipt = usesReceiptDocNo() ? '1' : '0';
  const n = await fetchJson(`${API}/next-number?type=${encodeURIComponent(gv('hIstype'))}&eb=${encodeURIComponent(gv('hEb'))}&module=${encodeURIComponent(gv('hModule'))}&receipt=${receipt}`);
  if(n.success) document.getElementById('docno').value=n.billNo||'';
}

async function onItem(i, val) {
  const v = String(val||'').trim().toUpperCase();
  State.rows[i].itemcode = v;
  if (!v) { renderTable(); return; }

  // Barcode scan
  if (/^\d+$/.test(v) && v.length > 3) {
    const d = await fetchJson(`${API}/barcode-info?bcode=${encodeURIComponent(v)}`);
    if (d.success) {
      Object.assign(State.rows[i], {
        itemcode: d.itemcode, itemname: d.itemname || '',
        qty: Number(d.qty||0), weight: Number(d.weight||0),
        stonewgt: Number(d.stonewgt||0), stoneprice: Number(d.stoneprice||0),
        mcharge: Number(d.mcharge||0), touch: Number(d.touch||0),
        stktouch: Number(d.stktouch||0), mcrate: Number(d.mcrate||State.mcrate),
        givrec: State.rows[i].givrec || 'G', bcode: Number(v), manualWastage: false,
      });
      recalcRow(i); renderTable(); focusCell(i,4); return;
    }
  }

  // Exact item lookup
  const d = await fetchJson(`${API}/item-info?code=${encodeURIComponent(v)}`);
  if (d.success && d.item) {
    const it = d.item;
    const istype = gv('hIstype');
    State.rows[i].itemcode = it.code || v;
    State.rows[i].itemname = it.name || '';
    State.rows[i].manualWastage = false;
    State.rows[i].cost     = Number(it.cost || 0);
    State.rows[i].stktype  = it.defstktype || SMITH_CFG.defStkType || '';
    State.rows[i].stktouch = Number(it.stktouch || 0);

    // mcrate: jewlmc for J-type, smithmc for G-type, else smith's mcrate
    const smithmc = Number(it.smithmc || 0);
    const jewlmc  = Number(it.jewlmc  || 0);
    if (istype === 'J' && jewlmc > 0)       State.rows[i].mcrate = jewlmc;
    else if (istype === 'G' && smithmc > 0) State.rows[i].mcrate = smithmc;
    else                                    State.rows[i].mcrate = State.mcrate;

    // touch: deftouch > jewltouch > item touch (TouchToSmithEntry setting)
    if (State.dideftouch > 0) State.rows[i].touch = State.dideftouch;
    if (istype === 'J' && Number(it.jewltouch || 0) > 0) State.rows[i].touch = Number(it.jewltouch);
    if (SMITH_CFG.touchToEntry && Number(it.touch || 0) > 0) State.rows[i].touch = Number(it.touch);

    if (!State.rows[i].givrec) State.rows[i].givrec = 'G';
    recalcRow(i); renderTable(); focusCell(i,4);
  } else {
    // Not found exactly — try search fallback
    const list = await fetchJson(`${API}/item-help?q=${encodeURIComponent(v)}`);
    if (Array.isArray(list) && list.length > 0) {
      State.rows[i].itemcode = list[0].code || v;
      State.rows[i].itemname = list[0].name || '';
    }
    renderTable(); focusCell(i,4);
  }
}

function sum(){
  let iss=0,rcv=0,tmc=0,clwgt=Number(State.dopwgt||0);
  State.rows.forEach(r=>{
    if(!r.itemcode) return;
    const weight = Number(r.weight||0);
    const netwgt = Number(r.netwgt||0);
    const lineAmt = Number(r.mcharge||0)+Number(r.stoneprice||0)+Number(r.hmc||0);
    const code = String(r.itemcode||'').trim().toUpperCase();
    const givrec = String(r.givrec||'').trim().toUpperCase();
    if(givrec==='G'){
      iss += weight;
      clwgt -= netwgt;
      tmc += (code==='TC' || code==='INT') ? lineAmt : -lineAmt;
    }else if(givrec==='R'){
      rcv += weight;
      clwgt += netwgt;
      tmc += (code==='TC' || code==='INT') ? -lineAmt : lineAmt;
    }
  });
  document.getElementById('twgtissued').textContent=iss.toFixed(3);
  document.getElementById('twgtrcvd').textContent=rcv.toFixed(3);
  document.getElementById('tmc').textContent=tmc.toFixed(2);
  document.getElementById('wgtbal').textContent=clwgt.toFixed(3);
  amtbal(tmc,iss);
}

function amtbal(tmc,issued){
  const acid=Number(document.getElementById('acidcharge').value||0), disc=Number(document.getElementById('discount').value||0), tds=Number(document.getElementById('tdsamt').value||0), tax=Number(document.getElementById('taxamt').value||0), tcs=Number(document.getElementById('tcsamt').value||0), paid=Number(document.getElementById('paid').value||0), pm=document.getElementById('pmntrcpt').value;
  let bal=State.dopamt+tmc-acid+disc-tds+(issued>0?-tax:tax)+tcs;
  const cl=(pm==='PAID')?bal-paid:bal+paid;
  document.getElementById('balance').value=bal.toFixed(2);
  document.getElementById('clbalance').value=cl.toFixed(2);
}

// ── Smith search dropdown ────────────────────────────────────────────────────
let smithList = [], smithHi = -1;

function currentClientType() {
  const istype = gv('hIstype');
  return istype === 'J' ? 'J' : ((istype === 'C' || istype === 'R') ? 'C' : 'G');
}

async function smithSearch(q) {
  const drop = document.getElementById('smithDrop');
  const data = await fetchJson(`${API}/client-help?ctype=${encodeURIComponent(currentClientType())}&q=${encodeURIComponent(q)}`);
  smithList = Array.isArray(data) ? data : [];
  smithHi = -1;
  if (!smithList.length) { drop.style.display = 'none'; return; }
  drop.innerHTML = smithList.map((s, i) =>
    `<div data-idx="${i}"><b>${e(s.code)}</b> — ${e(s.name)}${s.mobile ? ' <span style="color:#888;font-size:11px">' + e(s.mobile) + '</span>' : ''}</div>`
  ).join('');
  drop.style.display = 'block';
}

function highlightSmith(idx) {
  const items = document.querySelectorAll('#smithDrop div');
  items.forEach((el, i) => el.classList.toggle('hi', i === idx));
  smithHi = idx;
  if (items[idx]) items[idx].scrollIntoView({ block: 'nearest' });
}

function pickSmith(idx) {
  const s = smithList[idx];
  if (!s) return;
  document.getElementById('custcode').value = s.code;
  document.getElementById('smithDrop').style.display = 'none';
  smithList = []; smithHi = -1;
  loadClient(s.code);
}

// Event delegation — ev.preventDefault() keeps input focused (no blur fires)
document.getElementById('smithDrop').addEventListener('mousedown', function(ev) {
  ev.preventDefault();
  const item = ev.target.closest('div[data-idx]');
  if (item) pickSmith(parseInt(item.dataset.idx, 10));
});

// ── Name search dropdown (mirrors code search, populated by typing in Name) ──
let nameList = [], nameHi = -1;

async function nameSearch(q) {
  const drop = document.getElementById('nameDrop');
  const data = await fetchJson(`${API}/client-help?ctype=${encodeURIComponent(currentClientType())}&q=${encodeURIComponent(q)}`);
  nameList = Array.isArray(data) ? data : [];
  nameHi = -1;
  if (!nameList.length) { drop.style.display = 'none'; return; }
  drop.innerHTML = nameList.map((s, i) =>
    `<div data-idx="${i}"><b>${e(s.name)}</b> — ${e(s.code)}${s.mobile ? ' <span style="color:#888;font-size:11px">' + e(s.mobile) + '</span>' : ''}</div>`
  ).join('');
  drop.style.display = 'block';
}

function highlightName(idx) {
  const items = document.querySelectorAll('#nameDrop div');
  items.forEach((el, i) => el.classList.toggle('hi', i === idx));
  nameHi = idx;
  if (items[idx]) items[idx].scrollIntoView({ block: 'nearest' });
}

function pickName(idx) {
  const s = nameList[idx];
  if (!s) return;
  document.getElementById('custcode').value = s.code;
  document.getElementById('custname').value = s.name;
  document.getElementById('nameDrop').style.display = 'none';
  nameList = []; nameHi = -1;
  loadClient(s.code);
}

document.getElementById('nameDrop').addEventListener('mousedown', function(ev) {
  ev.preventDefault();
  const item = ev.target.closest('div[data-idx]');
  if (item) pickName(parseInt(item.dataset.idx, 10));
});

function openCreateSmith() {
  const ctype = currentClientType();
  const w = window.open('{{ url("/customer") }}?type=' + ctype + '&popup=1', 'createSmith',
    'width=1100,height=700,resizable=yes,scrollbars=yes');
  if (w) w.focus();
}

window.addEventListener('message', function(ev) {
  if (ev.data && ev.data.type === 'goldapp:customer-created') {
    const code = ev.data.code;
    if (code) {
      document.getElementById('custcode').value = code;
      document.getElementById('smithDrop').style.display = 'none';
      loadClient(code);
    }
  }
});

// ── SMan dropdown (client-side filter) ──────────────────────────────────────
let smanHi = -1, smanFiltered = [];

function smanSearch(q) {
  const drop = document.getElementById('smanDrop');
  const qlo = q.trim().toLowerCase();
  smanFiltered = !qlo ? [...SMAN_LIST] : SMAN_LIST.filter(s =>
    s.code.toLowerCase().includes(qlo) || s.name.toLowerCase().includes(qlo)
  );
  smanHi = -1;
  if (!smanFiltered.length) { drop.style.display = 'none'; return; }
  drop.innerHTML = smanFiltered.map((s, i) =>
    `<div data-idx="${i}"><b>${e(s.code)}</b> — ${e(s.name)}</div>`
  ).join('');
  drop.style.display = 'block';
}

function smanHilight(idx) {
  const items = document.querySelectorAll('#smanDrop div');
  items.forEach((el, i) => el.classList.toggle('hi', i === idx));
  smanHi = idx;
  if (items[idx]) items[idx].scrollIntoView({ block: 'nearest' });
}

function pickSman(idx) {
  const s = smanFiltered[idx];
  if (!s) return;
  document.getElementById('smcode').value = s.code;
  document.getElementById('smname').value = s.name || '';
  document.getElementById('smanDrop').style.display = 'none';
  smanFiltered = []; smanHi = -1;
}

function syncSmanDisplay() {
  const raw = document.getElementById('smcode').value.trim();
  const upper = raw.toUpperCase();
  if (SMAN_MAP[upper]) {
    document.getElementById('smcode').value = upper;
    document.getElementById('smname').value = SMAN_MAP[upper];
    return;
  }
  const byName = SMAN_LIST.find(s => String(s.name || '').trim().toLowerCase() === raw.toLowerCase());
  if (byName) {
    document.getElementById('smcode').value = String(byName.code || '').trim().toUpperCase();
    document.getElementById('smname').value = byName.name || '';
    return;
  }
  document.getElementById('smname').value = '';
}

document.getElementById('smanDrop').addEventListener('mousedown', function(ev) {
  ev.preventDefault();
  const item = ev.target.closest('div[data-idx]');
  if (item) pickSman(parseInt(item.dataset.idx, 10));
});

(function() {
  const el = document.getElementById('smcode');
  el.addEventListener('input', () => { syncSmanDisplay(); smanSearch(el.value); });
  el.addEventListener('focus', () => {
    if (!el.value.trim()) smanSearch('');
  });
  el.addEventListener('click', () => {
    const drop = document.getElementById('smanDrop');
    if (drop.style.display === 'none') smanSearch(el.value);
  });
  el.addEventListener('keydown', ev => {
    const drop = document.getElementById('smanDrop');
    const items = drop.querySelectorAll('div');
    if (ev.key === 'ArrowDown') { ev.preventDefault(); smanHilight(Math.min(smanHi + 1, items.length - 1)); }
    else if (ev.key === 'ArrowUp') { ev.preventDefault(); smanHilight(Math.max(smanHi - 1, 0)); }
    else if (ev.key === 'Enter' && smanHi >= 0) { ev.preventDefault(); pickSman(smanHi); }
    else if (ev.key === 'Escape') { drop.style.display = 'none'; smanFiltered = []; smanHi = -1; }
  });
  el.addEventListener('blur', () => {
    setTimeout(() => { document.getElementById('smanDrop').style.display = 'none'; smanFiltered = []; smanHi = -1; }, 200);
    setTimeout(() => syncSmanDisplay(), 50);
  });
})();

async function loadClient(code){
  const q=`${API}/balance?code=${encodeURIComponent(code)}&tdate=${encodeURIComponent(document.getElementById('tdate').value)}&slno=${encodeURIComponent(document.getElementById('hSlno').value)}&saded=${encodeURIComponent(document.getElementById('hSaded').value)}&istype=${encodeURIComponent(document.getElementById('hIstype').value)}`;
  const d=await fetchJson(q);
  if(!d.success){ alert(d.message||'Invalid code'); return; }
  document.getElementById('custname').value=d.name||'';
  document.getElementById('mobile').value=d.mobile||'';
  document.getElementById('opbal').value=d.opbal_label||'';
  State.dopwgt=Number(d.opwgt||0); State.dopamt=Number(d.opamt||0); State.mcrate=Number(d.mcrate||0);
  State.diconvtouch=Number(d.diconvtouch||100); State.idecround=Number(d.idecround||3);
  State.imcdecround=Number(d.imcdecround||2); State.dideftouch=Number(d.dideftouch||0);
  State.smithWastage=Number(d.smithWastage||0);
  sum();
}

async function doSave(){
  if (saveBusy) return;
  document.getElementById('warn').textContent='';
  syncSmanDisplay();
  const payload={ saded:gv('hSaded'), istype:gv('hIstype'), eb:gv('hEb'), module:gv('hModule'), slno:gv('hSlno'), smithcode:gv('custcode'), smithname:gv('custname'), docno:gv('docno'), smcode:gv('smcode'), refno:gv('refno'), tdate:gv('tdate'), rate:gv('rate'), tmc:document.getElementById('tmc').textContent, paid:gv('paid'), pmntrcpt:gv('pmntrcpt'), cbcode:gv('cbcode'), acidcharge:gv('acidcharge'), discount:gv('discount'), tdsperc:gv('tdsperc'), tdsamt:gv('tdsamt'), taxperc:gv('taxperc'), taxamt:gv('taxamt'), tcsperc:gv('tcsperc'), tcsamt:gv('tcsamt'), statecode:gv('statecode'), placeos:gv('placeos'), duedate:gv('duedate'), transportmode:gv('transportmode'), vehno:gv('vehno'), purpose:gv('purpose'), note:gv('note'), lotno:gv('lotno'), person:gv('person'), opwgt:State.dopwgt, opamt:State.dopamt, createbc:document.getElementById('createbc').checked?'Y':'N', rows: JSON.stringify(State.rows.filter(r=>String(r.itemcode||'').trim()!=='')) };
  if(!payload.smithcode){ alert('Enter goldsmith code'); return; }
  setSaveBusy(true, 'Saving...');
  try {
    const d=await fetchJson(`${API}/save`,'POST',payload);
    if(!d.success){ document.getElementById('warn').textContent=d.message||'Not saved'; alert(d.message||'Save failed'); return; }
    if (d.slno && (PAGE_MODE === 'transaction' || PAGE_MODE === 'bill')) {
      window.open(`${PRINT_URL}?slno=${encodeURIComponent(d.slno)}&type=${encodeURIComponent(gv('hIstype'))}&module=${encodeURIComponent(gv('hModule'))}`, '_blank', 'width=1100,height=850,scrollbars=yes');
    }
    alert('Saved: '+(d.docno||'')); await resetForm();
  } catch (err) {
    const msg = err && err.message ? err.message : 'Save failed';
    document.getElementById('warn').textContent = msg;
    alert(msg);
  } finally {
    setSaveBusy(false);
  }
}

async function doCancel(){
  if (saveBusy) return;
  document.getElementById('warn').textContent='';
  const slno = parseInt(document.getElementById('hSlno').value, 10) || 0;
  const docno = gv('docno');
  if (!slno) { alert('Load a transaction first'); return; }
  if (!confirm('Cancel transaction ' + docno + '?')) return;
  setSaveBusy(true, 'Cancelling...');
  try {
    const d = await fetchJson(`${API}/delete`, 'POST', { slno });
    if (!d.success) {
      document.getElementById('warn').textContent = d.message || 'Cancel failed';
      alert(d.message || 'Cancel failed');
      return;
    }
    alert('Cancelled: ' + docno);
    await resetForm();
  } catch (err) {
    const msg = err && err.message ? err.message : 'Cancel failed';
    document.getElementById('warn').textContent = msg;
    alert(msg);
  } finally {
    setSaveBusy(false);
  }
}

async function resetForm(){
  document.getElementById('hSlno').value='0';
  document.getElementById('hSaded').value='A';
  ['custcode','custname','mobile','opbal','smcode','refno','lotno','paid','balance','clbalance','person','placeos','transportmode','vehno','purpose','note'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('smname').value='';
  document.getElementById('paid').value='0';
  document.getElementById('rate').value=GOLD_RATE;
  State.rows=[emptyRow()]; State.dopwgt=0; State.dopamt=0;
  await refreshNewDocNo();
  renderTable();
}

function gv(id){ return document.getElementById(id).value; }
async function fetchJson(url, method='GET', body=null){
  const opts={method,headers:{}};
  if(body){ opts.headers['Content-Type']='application/x-www-form-urlencoded'; opts.headers['X-CSRF-TOKEN']=csrf(); opts.body=new URLSearchParams(body).toString(); }
  const r=await fetch(url,opts); return await r.json();
}

// ── Load bill by slno ─────────────────────────────────────────────────────────
function applyMaster(m, eb) {
  document.getElementById('hSlno').value  = m.slno  || 0;
  document.getElementById('hSaded').value = 'E';
  document.getElementById('hEb').value    = eb || 'B';
  document.getElementById('docno').value  = m.docno     || '';
  document.getElementById('custcode').value = m.smithcode || '';
  document.getElementById('custname').value = m.name      || '';
  document.getElementById('mobile').value   = m.mobile    || '';
  document.getElementById('tdate').value    = m.tdate     || '';
  document.getElementById('rate').value     = m.rate      || '';
  document.getElementById('smcode').value   = m.smcode    || '';
  document.getElementById('smname').value   = m.sman_name || SMAN_MAP[String(m.smcode || '').trim().toUpperCase()] || '';
  document.getElementById('refno').value    = m.refno     || '';
  document.getElementById('lotno').value    = m.lotno     || '';
  document.getElementById('duedate').value  = m.duedate   || '';
  document.getElementById('person').value   = m.person    || '';
  document.getElementById('statecode').value     = m.statecode     || '32';
  document.getElementById('placeos').value       = m.placeos       || '';
  document.getElementById('transportmode').value = m.transportmode || '';
  document.getElementById('vehno').value         = m.vehno         || '';
  document.getElementById('purpose').value       = m.purpose       || '';
  document.getElementById('note').value          = m.note          || '';
  document.getElementById('acidcharge').value = Number(m.acidcharge||0).toFixed(2);
  document.getElementById('discount').value   = Number(m.discount  ||0).toFixed(2);
  document.getElementById('tdsperc').value    = Number(m.tdsperc   ||0).toFixed(2);
  document.getElementById('tdsamt').value     = Number(m.tdsamt    ||0).toFixed(2);
  document.getElementById('taxperc').value    = Number(m.taxperc   ||0).toFixed(2);
  document.getElementById('taxamt').value     = Number(m.taxamt    ||0).toFixed(2);
  document.getElementById('tcsperc').value    = Number(m.tcsperc   ||0).toFixed(2);
  document.getElementById('tcsamt').value     = Number(m.tcsamt    ||0).toFixed(2);
  const pamt = Number(m.pamt || 0);
  document.getElementById('pmntrcpt').value = pamt < 0 ? 'RECEIVED' : 'PAID';
  document.getElementById('paid').value     = Math.abs(pamt).toFixed(2);
  document.getElementById('cbcode').value   = m.cbcode || 'CASH';
  document.getElementById('opbal').value    = Number(m.opwgt||0).toFixed(3) + ' / ' + Number(m.opamt||0).toFixed(2);
  State.dopwgt = Number(m.opwgt || 0);
  State.dopamt = Number(m.opamt || 0);
}

async function loadBill(slno) {
  if (!slno || slno <= 0) return;
  const d = await fetchJson(`${API}/get?slno=${encodeURIComponent(slno)}&type=${encodeURIComponent(gv('hIstype'))}&module=${encodeURIComponent(gv('hModule'))}`);
  if (!d.success) { alert(d.message || 'Not found'); return; }
  applyMaster(d.master, d.eb);
  State.rows = (d.details || []).map(r => Object.assign(emptyRow(), r));
  if (!State.rows.length) State.rows = [emptyRow()];
  document.getElementById('warn').textContent = '';
  renderTable();
}

async function prevBill() {
  const slno = parseInt(document.getElementById('hSlno').value) || 0;
  const d = await fetchJson(`${API}/prev?slno=${slno}&type=${encodeURIComponent(gv('hIstype'))}&module=${encodeURIComponent(gv('hModule'))}`);
  if (d.success && d.slno) await loadBill(d.slno);
  else alert(d.message || 'No previous record');
}

async function nextBill() {
  const slno = parseInt(document.getElementById('hSlno').value) || 0;
  const d = await fetchJson(`${API}/next?slno=${slno}&type=${encodeURIComponent(gv('hIstype'))}&module=${encodeURIComponent(gv('hModule'))}`);
  if (d.success && d.slno) await loadBill(d.slno);
  else alert(d.message || 'No next record');
}

document.getElementById('btnAdd').onclick=()=>{ const last=State.rows[State.rows.length-1]; if(last && !last.itemcode) return; State.rows.push(emptyRow()); renderTable(); };
document.getElementById('btnDel').onclick=()=>{ if(State.rows.length>1) State.rows.pop(); else State.rows=[emptyRow()]; renderTable(); };
document.getElementById('btnSave2').onclick=PAGE_MODE === 'cancel' ? doCancel : doSave;
document.getElementById('btnPrev').onclick=prevBill;
document.getElementById('btnNext').onclick=nextBill;
document.getElementById('btnPrint').onclick=()=>{ const slno = parseInt(document.getElementById('hSlno').value,10)||0; if(!slno){ alert('Save or load a transaction first'); return; } window.open(`${PRINT_URL}?slno=${encodeURIComponent(slno)}&type=${encodeURIComponent(gv('hIstype'))}&module=${encodeURIComponent(gv('hModule'))}`,'_blank','width=1100,height=850,scrollbars=yes'); };
document.getElementById('btnClose2').onclick=()=>window.parent.postMessage({type:'goldapp:close-module-frame'},'*');
const custcodeEl = document.getElementById('custcode');
custcodeEl.addEventListener('input', () => smithSearch(custcodeEl.value));
custcodeEl.addEventListener('focus', () => smithSearch(custcodeEl.value));
custcodeEl.addEventListener('click', () => {
  if (document.getElementById('smithDrop').style.display === 'none') smithSearch(custcodeEl.value);
});
custcodeEl.addEventListener('keydown', ev => {
  const drop = document.getElementById('smithDrop');
  const items = drop.querySelectorAll('div');
  if (ev.key === 'ArrowDown') { ev.preventDefault(); highlightSmith(Math.min(smithHi + 1, items.length - 1)); }
  else if (ev.key === 'ArrowUp') { ev.preventDefault(); highlightSmith(Math.max(smithHi - 1, 0)); }
  else if (ev.key === 'Enter' && smithHi >= 0) { ev.preventDefault(); pickSmith(smithHi); }
  else if (ev.key === 'Escape') { drop.style.display = 'none'; smithList = []; smithHi = -1; }
});
custcodeEl.addEventListener('blur', () => {
  // Small delay so smithDrop mousedown (which calls preventDefault) fires first
  setTimeout(() => {
    document.getElementById('smithDrop').style.display = 'none';
    smithList = []; smithHi = -1;
    const c = custcodeEl.value.trim().toUpperCase();
    custcodeEl.value = c;
    if (c) loadClient(c);
  }, 200);
});

const custnameEl = document.getElementById('custname');
custnameEl.addEventListener('input', () => nameSearch(custnameEl.value));
custnameEl.addEventListener('focus', () => nameSearch(custnameEl.value));
custnameEl.addEventListener('click', () => {
  if (document.getElementById('nameDrop').style.display === 'none') nameSearch(custnameEl.value);
});
custnameEl.addEventListener('keydown', ev => {
  const drop = document.getElementById('nameDrop');
  const items = drop.querySelectorAll('div');
  if (ev.key === 'ArrowDown') { ev.preventDefault(); highlightName(Math.min(nameHi + 1, items.length - 1)); }
  else if (ev.key === 'ArrowUp') { ev.preventDefault(); highlightName(Math.max(nameHi - 1, 0)); }
  else if (ev.key === 'Enter' && nameHi >= 0) { ev.preventDefault(); pickName(nameHi); }
  else if (ev.key === 'Escape') { drop.style.display = 'none'; nameList = []; nameHi = -1; }
});
custnameEl.addEventListener('blur', () => {
  setTimeout(() => {
    document.getElementById('nameDrop').style.display = 'none';
    nameList = []; nameHi = -1;
  }, 200);
});
['acidcharge','discount','tdsamt','taxamt','tcsamt','paid','pmntrcpt'].forEach(id=>document.getElementById(id).addEventListener('change',sum));
document.addEventListener('keydown',e=>{ if(e.key==='F9'){ e.preventDefault(); (PAGE_MODE === 'cancel' ? doCancel : doSave)(); } });

function applyRowInputChange(rowIdx, field, value) {
  if (rowIdx < 0 || !State.rows[rowIdx]) return;
  if (field === 'itemcode') return onItem(rowIdx, value).then(() => true);
  if (field === 'itemname') { setf(rowIdx, 'itemname', value); return false; }
  if (field === 'qty') { setn(rowIdx, 'qty', value); return false; }
  if (field === 'weight' || field === 'stonewgt' || field === 'touch' || field === 'wastage' || field === 'tp') {
    chg(rowIdx, field, value);
    return false;
  }
  if (field === 'givrec') { chgGR(rowIdx, value); return false; }
  if (field === 'netwgt' || field === 'stoneprice' || field === 'mcharge' || field === 'hmc') {
    setn(rowIdx, field, value);
    sum();
    return false;
  }
  if (field === 'model' || field === 'remark') {
    setf(rowIdx, field, value);
  }
  return false;
}

const GRID_FIELD_ORDER = ['itemcode', 'itemname', 'qty', 'weight', 'stonewgt', 'givrec', 'touch', 'wastage', 'netwgt', 'stoneprice', 'mcharge', 'hmc', 'tp', 'model', 'remark'];

function focusGridField(rowIdx, field) {
  const el = document.querySelector(`#detailBody tr[data-idx="${rowIdx}"] [data-field="${field}"]`);
  if (!el) return false;
  el.focus();
  if (el.select) el.select();
  return true;
}

function focusNextGridField(rowIdx, field) {
  const fieldIdx = GRID_FIELD_ORDER.indexOf(field);
  if (fieldIdx < 0) return;

  if (fieldIdx < GRID_FIELD_ORDER.length - 1) {
    if (focusGridField(rowIdx, GRID_FIELD_ORDER[fieldIdx + 1])) return;
  }

  if (rowIdx === State.rows.length - 1) {
    const last = State.rows[State.rows.length - 1];
    if (last && last.itemcode) {
      State.rows.push(emptyRow());
      renderTable();
    }
  }

  focusGridField(rowIdx + 1, 'itemcode');
}

document.getElementById('detailBody').addEventListener('keydown', async function(ev) {
  if (ev.key !== 'Enter') return;
  if (ev.defaultPrevented) return;
  const el = ev.target;
  if (!el.matches('input[data-field], select[data-field]')) return;
  const field = el.dataset.field || '';
  if ((field === 'itemname' || field === 'itemcode') && document.getElementById('itemDropGlobal').style.display !== 'none') return;
  ev.preventDefault();

  const row = el.closest('tr[data-idx]');
  if (!row) return;
  const rowIdx = Number(row.dataset.idx || 0);
  const handledFocus = await applyRowInputChange(rowIdx, field, el.value);
  if (!handledFocus) focusNextGridField(rowIdx, field);
});

document.getElementById('tdate').value=new Date().toISOString().slice(0,10);
State.rows=[emptyRow()];
renderTable();
const qs = new URLSearchParams(window.location.search);
const qsSlno = parseInt(qs.get('slno') || '0', 10);
if (qsSlno > 0) loadBill(qsSlno);
</script>
</body>
</html>
