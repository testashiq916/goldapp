<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('js/goldapp-common.js') }}"></script>
<title>{{ $title }}</title>
<script>
window.PR_CONFIG = {
  siteUrl:    @json(request()->root()),
  mode:       @json($mode),
  rates: {
    gold:     {{ $rates['gold']     ?? 0 }},
    silver:   {{ $rates['silver']   ?? 0 }},
    platinum: {{ $rates['platinum'] ?? 0 }},
  },
  salesmen:  @json(array_map('get_object_vars', $salesmen)),
  counters:  @json(array_map('get_object_vars', $counters)),
  billTypes: @json(array_map('get_object_vars', $billTypes)),
  cashBanks: @json($cashBanks),
  states:    @json(array_map('get_object_vars', $states)),
  software:  @json($software ?? []),
};
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter','Segoe UI',Tahoma,sans-serif;font-size:12px;color:#1a202c;overflow:hidden;height:100vh;
  background:radial-gradient(circle at 10% -10%,#fff5f5 0%,#fef7f7 40%,#fdf2f2 100%)}
input,select,button{transition:all .15s ease}
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-track{background:#f7fafc}
::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:3px}

/* ── Main window ── */
.main-window{background:#fff;border:1px solid #e2c6c6;border-radius:12px;
  box-shadow:0 10px 30px rgba(89,33,33,.10);margin:8px;overflow:hidden;position:relative}

.title-bar{background:linear-gradient(135deg,#7f1d1d,#991b1b);color:#fff;
  padding:10px 14px;font-weight:700;font-size:13px;display:flex;align-items:center;gap:8px;letter-spacing:.3px}
.title-bar .icon{width:16px;height:16px;background:#fca5a5;border-radius:4px;flex-shrink:0}

/* ── Top section ── */
.top-section{background:#fefbfb;padding:10px;display:grid;
  grid-template-columns:1.2fr 1fr 1.1fr;gap:4px 14px;border-bottom:1px solid #e2c6c6}
.top-section .row{display:flex;align-items:center;gap:4px;padding:1px 0;min-height:26px}
.top-section label{font-weight:600;font-size:11px;min-width:72px;color:#6b7280}
.top-section input,.top-section select{font-size:11px;padding:2px 6px;border:1px solid #e2c6c6;
  background:#fff;height:26px;border-radius:7px;color:#2d3748;
  box-shadow:inset 0 1px 2px rgba(17,24,39,.03)}
.top-section input:focus,.top-section select:focus{border-color:#fca5a5;
  box-shadow:0 0 0 3px rgba(239,68,68,.14);outline:none}
.top-section input.sm{width:68px}
.top-section input.md{width:108px}
.top-section input.lg{flex:1;min-width:0;width:auto}
.top-section .row.sup-row{position:relative}
#supDrop{position:absolute;top:28px;left:72px;width:380px;max-height:200px;overflow-y:auto;
  background:#111827;border:1px solid #374151;border-radius:6px;
  box-shadow:0 6px 16px rgba(0,0,0,.35);z-index:500;display:none;font-size:11px;color:#f9fafb}
#supDrop .s-row{padding:5px 8px;cursor:pointer;border-bottom:1px solid #374151}
#supDrop .s-row:hover{background:#1f2937}
#supDrop .s-row .s-code{font-weight:600;color:#fca5a5;margin-right:5px}
.sup-btns button{font-size:10px;padding:1px 7px;border:1px solid #e2c6c6;border-radius:6px;
  cursor:pointer;background:#fff;height:26px;color:#374151}
.sup-btns button:hover{background:#fef2f2}

/* ── Items table ── */
.table-container{margin:0 8px;border:1px solid #e2c6c6;border-radius:10px;
  background:#fff;overflow:hidden;box-shadow:0 3px 10px rgba(92,32,32,.05)}
table.items{width:100%;border-collapse:collapse;font-size:11px}
table.items thead th{background:linear-gradient(180deg,#7f1d1d,#6b1a1a);color:#fef2f2;
  padding:5px 5px;border:1px solid #991b1b;font-weight:600;font-size:10px;
  text-align:center;white-space:nowrap;text-transform:uppercase;letter-spacing:.4px}
table.items tbody td{border:1px solid #f3e0e0;padding:1px 2px;text-align:center;height:22px}
table.items tbody tr:nth-child(odd){background:#fff}
table.items tbody tr:nth-child(even){background:#fefafa}
table.items tbody tr:hover{background:#fef2f2}
table.items tbody tr.sel td{background:#fecaca}
table.items tbody input{font-size:11px;border:none;background:transparent;
  text-align:center;width:100%;height:100%}
table.items tbody input:focus{background:#fffff0;outline:2px solid #ef4444;border-radius:2px}
table.items tbody input.num{text-align:right}

/* ── Table footer ── */
.table-footer{display:flex;align-items:center;background:#fefbfb;
  padding:5px 10px;gap:8px;font-size:11px;font-weight:600;
  color:#2d3748;border-top:1px solid #e2c6c6}
.table-footer button{background:#fff;border:1px solid #e2c6c6;border-radius:8px;
  padding:2px 12px;font-size:11px;cursor:pointer;font-weight:600;color:#3f4a5b}
.table-footer button:hover{background:#fef2f2;border-color:#fca5a5}
.table-footer button:active{transform:translateY(1px)}
.tf-label{color:#7f1d1d;font-weight:700}
.tfv{text-align:right;font-variant-numeric:tabular-nums;min-width:30px}

/* ── Bottom section ── */
.bottom-section{background:#fefbfb;padding:5px 8px 6px;
  display:flex;align-items:flex-start;gap:8px;
  border-top:1px solid #e2c6c6;font-size:11px}
.foot-rows{flex:1;display:flex;flex-direction:column;gap:0}
.frow{display:flex;align-items:center;gap:3px;min-height:24px;white-space:nowrap;flex-wrap:nowrap}
.fl{font-weight:600;font-size:11px;color:#4a5568;padding:0 2px 0 4px;white-space:nowrap;flex-shrink:0}
input.fv,select.fv{font-size:11px;padding:1px 4px;border:1px solid #e2c6c6;
  background:#fff;height:22px;border-radius:5px;color:#2d3748;flex-shrink:0;
  box-shadow:inset 0 1px 2px rgba(17,24,39,.03)}
input.fv:focus,select.fv:focus{border-color:#fca5a5;box-shadow:0 0 0 2px rgba(239,68,68,.14);outline:none}
.fw36{width:38px}.fw46{width:48px}.fw70{width:72px}.fw84{width:86px}.fw94{width:96px}.fwx{flex:1;min-width:60px}
.fsep{display:inline-block;width:6px;flex-shrink:0}
.flbl{font-size:11px;color:#4a5568;padding-right:4px;flex-shrink:0}
.fchk{display:inline-flex;align-items:center;gap:2px;flex-shrink:0}
.fchk input[type=checkbox]{width:13px;height:13px;margin:0;cursor:pointer}
/* Foot buttons column */
.foot-btns{display:flex;flex-direction:column;gap:3px;min-width:108px;flex-shrink:0;padding-left:4px}
.fbtn{background:linear-gradient(180deg,#f5f5f5,#e8e8e8);border:1px solid #adadad;
  border-radius:4px;padding:2px 6px;font-size:11px;cursor:pointer;font-weight:600;
  color:#111;height:26px;font-family:inherit;text-align:center;white-space:nowrap}
.fbtn:hover{background:linear-gradient(180deg,#fff,#f0f0f0)}
.fbtn:active{background:#e0e0e0;transform:translateY(1px)}
.fbtn.fb-save{background:linear-gradient(180deg,#fee2e2,#fecaca);border-color:#ef4444;color:#7f1d1d}
.fbtn.fb-save:hover{background:linear-gradient(180deg,#fef2f2,#fee2e2)}
.foot-chk{display:flex;align-items:center;gap:3px;font-size:11px;color:#374151;padding:1px 0}
.foot-chk input[type=checkbox]{width:13px;height:13px;margin:0;cursor:pointer}
.inline-chk{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#4a5568}

/* 3-panel aligned bottom layout */
.fp1,.fp2,.fp3{display:flex;flex-direction:column;gap:2px}
.fp1,.fp2{flex-shrink:0}
.fp3{flex:1;min-width:0}
.fp1 .fr,.fp2 .fr,.fp3 .fr{display:flex;align-items:center;gap:3px;height:22px;white-space:nowrap}
.fl1{font-weight:600;font-size:11px;color:#4a5568;min-width:62px;flex-shrink:0;white-space:nowrap;padding-right:3px}
.fl2{font-weight:600;font-size:11px;color:#4a5568;min-width:56px;flex-shrink:0;white-space:nowrap;padding-right:3px}

/* ── Modals ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);
  z-index:1000;justify-content:center;align-items:center}
.modal-overlay.active{display:flex}
.modal-box{background:#fff;border:1px solid #e2c6c6;border-radius:10px;
  min-width:380px;max-width:580px;box-shadow:0 8px 32px rgba(0,0,0,.15);overflow:hidden}
.modal-head{background:linear-gradient(135deg,#7f1d1d,#991b1b);color:#fff;
  padding:8px 12px;font-weight:700;font-size:12px;display:flex;justify-content:space-between;align-items:center}
.modal-head .cls{background:rgba(255,255,255,.15);border:none;color:#fff;width:22px;height:20px;
  font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:4px}
.modal-head .cls:hover{background:#fc8181}
.modal-body{padding:10px 12px}
.modal-footer{padding:6px 12px;border-top:1px solid #e2e8f0;display:flex;gap:6px;justify-content:flex-end}
.mbtn{padding:4px 18px;font-size:11px;font-weight:600;border:1px solid #d0d9ea;
  border-radius:8px;background:#fff;cursor:pointer;color:#2d3748}
.mbtn:hover{background:#fef2f2}
.srch-tbl{width:100%;border-collapse:collapse;font-size:11px}
.srch-tbl th{background:linear-gradient(180deg,#7f1d1d,#6b1a1a);color:#fef2f2;
  padding:4px 8px;text-align:left;font-weight:600}
.srch-tbl td{padding:3px 8px;border-bottom:1px solid #f3e0e0;cursor:pointer}
.srch-tbl tr:hover td{background:#fecaca}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="main-window">

  <!-- Title bar -->
  <div class="title-bar">
    <div class="icon"></div>
    <span id="titleText">{{ $title }}</span>
  </div>

  <!-- TOP SECTION -->
  <div class="top-section">

    <!-- Col 1: Doc No, Supplier, Address -->
    <div>
      <div class="row">
        <label>Doc. No</label>
        <input id="fDocNo" class="md" value="" readonly style="background:#fefce8">
        <span class="inline-chk" style="margin-left:4px">
          <input type="checkbox" id="fManual" onchange="onManualChk()" style="width:14px;height:14px">
          <span style="font-size:11px;color:#4a5568">Manual</span>
        </span>
      </div>
      <div class="row sup-row">
        <label>Supplier</label>
        <input id="fSupCode" class="sm" placeholder="Code" autocomplete="off"
          oninput="onSupInput(this.value,'code')" onkeydown="onSupKey(event)">
        <input id="fSupName" class="lg" placeholder="Name" autocomplete="off"
          oninput="onSupInput(this.value,'name')" onkeydown="onSupNameKey(event)">
        <div class="sup-btns" style="display:flex;gap:2px">
          <button onclick="openSupModal()" title="Search">&#94;</button>
        </div>
        <div id="supDrop"></div>
      </div>
      <div class="row">
        <label>Sup. List</label>
        <select id="fSupSelect" class="lg" onchange="onSupSelectChange(this.value)" style="min-width:0;flex:1">
          <option value="">-- Select Supplier --</option>
        </select>
      </div>
      <div class="row"><label>Address</label><input id="fAddress" class="lg" value=""></div>
      <div class="row"><label>Note</label><input id="fNote" class="lg" value=""></div>
    </div>

    <!-- Col 2: Bill No, OB, PAN, Mobile -->
    <div>
      <div class="row">
        <label>Bill No</label>
        <input id="fBillNo" class="md" placeholder="Supplier bill no">
        <label style="min-width:auto;margin-left:8px">Date</label>
        <input id="fDate" type="date" class="md" value="{{ date('Y-m-d') }}" style="background:#fefce8">
      </div>
      <div class="row">
        <label>OB</label>
        <input id="fOB" class="sm" value=".00" readonly style="background:#f1f5f9">
        <label style="min-width:auto;margin-left:8px">Sales Man</label>
        <select id="fSalesMan" style="flex:1;min-width:0;font-size:11px;height:26px;border-radius:7px;border:1px solid #e2c6c6">
          <option value="">--</option>
          @foreach($salesmen as $s)<option value="{{ $s->code }}">{{ $s->name }}</option>@endforeach
        </select>
      </div>
      <div class="row">
        <label>PAN/Adh</label>
        <input id="fPan" class="md" value="">
        <label style="min-width:auto;margin-left:8px">Mobile</label>
        <input id="fMobile" class="md" value="">
      </div>
    </div>

    <!-- Col 3: Gold Rate, BType, State, Counter -->
    <div>
      <div class="row">
        <label>Gold Rate</label>
        <input id="fGoldRate" class="sm" value="{{ $rates['gold'] ?? 0 }}" oninput="onRateChange()">
      </div>
      <div class="row">
        <label>Bill Type</label>
        <select id="fBType" onchange="onBillTypeChange()" style="width:90px;font-size:11px;height:26px;border-radius:7px;border:1px solid #e2c6c6">
          @foreach($billTypes as $bt)<option value="{{ $bt->code }}">{{ $bt->name }}</option>@endforeach
        </select>
      </div>
      <div class="row">
        <label>State</label>
        <select id="fStateCode" class="sm" onchange="syncState(this.value)">
          <option value=""></option>
          @foreach($states as $s)<option value="{{ $s->code }}">{{ $s->code }}</option>@endforeach
        </select>
        <select id="fStateName" style="flex:1;min-width:0;font-size:11px;height:26px;border-radius:7px;border:1px solid #e2c6c6" onchange="syncStateFromName(this.value)">
          <option value=""></option>
          @foreach($states as $s)<option value="{{ $s->code }}">{{ $s->name }}</option>@endforeach
        </select>
      </div>
      <div class="row">
        <label>Counter</label>
        <select id="fCounter" style="flex:1;min-width:0;font-size:11px;height:26px;border-radius:7px;border:1px solid #e2c6c6">
          <option value="">--</option>
          @foreach($counters as $c)<option value="{{ $c->code }}">{{ $c->name }}</option>@endforeach
        </select>
      </div>
    </div>

  </div><!-- .top-section -->

  <!-- ITEMS TABLE -->
  <div class="table-container" style="margin-top:6px">
    <table class="items" id="itbl">
      <thead>
        <tr>
          <th style="width:62px">Item Code</th>
          <th style="width:120px">Item Name</th>
          <th style="width:44px">Purity</th>
          <th style="width:58px">Rate</th>
          <th style="width:34px">Qty</th>
          <th style="width:60px">Weight<br>in Gm.</th>
          <th style="width:52px">Stone<br>Wgt</th>
          <th style="width:52px">Stone<br>Price</th>
          <th style="width:46px">Mud<br>Less</th>
          <th style="width:46px">Touch</th>
          <th style="width:40px">Less %</th>
          <th style="width:54px">Less<br>Weight</th>
          <th style="width:58px">Net<br>Weight</th>
          <th style="width:46px">MC</th>
          <th style="width:68px">Amount</th>
        </tr>
      </thead>
      <tbody id="itbody"></tbody>
    </table>
  </div>

  <!-- TABLE FOOTER -->
  <div class="table-footer">
    <button onclick="addRow()">Add</button>
    <button onclick="delRow()">Delete</button>
    <span class="tf-label">Items: <span id="cntItems">0</span></span>
    <span class="tf-label" style="margin-left:8px">Total :</span>
    <span class="tfv" id="ftQty">0</span>
    <span class="tfv" id="ftWgt">0.000</span>
    <span class="tfv" id="ftStwgt">0.000</span>
    <span class="tfv" id="ftStprice">0.00</span>
    <span class="tfv" id="ftMud">0.00</span>
    <span class="tfv" style="min-width:20px"></span>
    <span class="tfv" id="ftLesswgt">0.000</span>
    <span class="tfv" id="ftNetwgt">0.000</span>
    <span class="tfv" id="ftMc">0.00</span>
    <span class="tfv" id="ftAmt">0.00</span>
  </div>

  <!-- BOTTOM SECTION -->
  <div class="bottom-section">

    <!-- Panel 1: Bill Total · Others · Chq Bank · Chq No -->
    <div class="fp1">
      <div class="fr">
        <span class="fl1">Bill Total</span>
        <input id="fBillTotal" class="fv fw84" value=".00" readonly style="font-weight:700;background:#fef2f2">
      </div>
      <div class="fr">
        <span class="fl1">Others</span>
        <input id="fOthers" class="fv fw84" value=".00" oninput="triggerRecalc()">
      </div>
      <div class="fr">
        <span class="fl1">Chq Bank</span>
        <select id="fChqBank" class="fv" style="width:84px">
          <option value=""></option>
          @foreach($cashBanks as $b)<option value="{{ $b['code'] }}">{{ $b['name'] }}</option>@endforeach
        </select>
      </div>
      <div class="fr">
        <span class="fl1">Chq. No</span>
        <input id="fChqNo" class="fv fw84" value="">
      </div>
    </div>

    <!-- Panel 2: Interstate · Tax · Chq Amt · Chq Date+PDC · CB -->
    <div class="fp2">
      <div class="fr">
        <span class="fchk"><input type="checkbox" id="fInterstate" onchange="triggerRecalc()"><span class="flbl">Interstate</span></span>
        <span class="fchk" style="margin-left:6px"><input type="checkbox" id="fExternal" onchange="triggerRecalc()"><span class="flbl">External</span></span>
      </div>
      <div class="fr">
        <span class="fl2">Tax</span>
        <input id="fTaxPerc" class="fv fw36" value="0" oninput="triggerRecalc()">
        <input id="fTaxAmt" class="fv fw84" value=".00" readonly style="background:#f1f5f9">
      </div>
      <div class="fr">
        <span class="fl2">Chq. Amt</span>
        <input id="fChqAmt" class="fv fw84" value=".00">
      </div>
      <div class="fr">
        <span class="fl2">Chq. Date</span>
        <input id="fChqDate" type="date" class="fv" style="width:120px" value="">
        <span class="fchk" style="margin-left:3px"><input type="checkbox" id="fPdc"><span class="flbl">PDC</span></span>
      </div>
      <div class="fr">
        <span class="fl2">CB</span>
        <input id="fCB" class="fv fw84" value=".00" readonly style="background:#f1f5f9">
      </div>
    </div>

    <!-- Panel 3: Net Total · Paid · Balance -->
    <div class="fp3">
      <div class="fr">
        <span class="fl">Net Total</span>
        <input id="fNetTotal" class="fv fw94" value=".00" readonly style="background:#fecaca;font-weight:700;color:#7f1d1d">
      </div>
      <div class="fr">
        <span class="fl">Received Amt</span>
        <input id="fPaidAmt" class="fv fw84" value=".00" oninput="onPaidChange()">
      </div>
      <div class="fr">
        <span class="fl">Balance</span>
        <input id="fBalance" class="fv fw94" value=".00" readonly style="background:#fef3c7;font-weight:700">
      </div>
    </div>

    <!-- Buttons column -->
    <div class="foot-btns">
      <button class="fbtn fb-save" onclick="saveBill()" id="btnSave">Save</button>
      @if($mode === 'bill')
      <button class="fbtn" onclick="newBill()">New</button>
      @endif
      <button class="fbtn" onclick="prevBill()">&#9664; Prev</button>
      <button class="fbtn" onclick="nextBill()">Next &#9654;</button>
      @if($mode === 'cancel')
      <button class="fbtn" onclick="openCancelModal()" style="background:#fee2e2;color:#c53030;border-color:#fca5a5">Cancel Bill</button>
      @endif
      @if($mode === 'reprint')
      <button class="fbtn" onclick="window.print()">Print</button>
      @endif
      <button class="fbtn" onclick="doExit()">Exit</button>
    </div>

  </div><!-- .bottom-section -->

</div><!-- .main-window -->

<!-- Supplier modal -->
<div class="modal-overlay" id="supModal">
  <div class="modal-box" style="min-width:460px">
    <div class="modal-head">
      <span>Supplier Search</span>
      <button class="cls" onclick="closeSupModal()">&#10006;</button>
    </div>
    <div class="modal-body">
      <input id="supModalQ" style="width:100%;height:26px;font-size:12px;border:1px solid #e2c6c6;border-radius:7px;padding:2px 8px"
        placeholder="Search by code or name..." oninput="supModalSearch(this.value)" autocomplete="off">
      <div style="margin-top:8px;max-height:220px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px">
        <table class="srch-tbl"><thead><tr><th>Code</th><th>Name</th><th>Mobile</th></tr></thead>
        <tbody id="supModalBody"></tbody></table>
      </div>
    </div>
    <div class="modal-footer"><button class="mbtn" onclick="closeSupModal()">Close</button></div>
  </div>
</div>

<!-- Bill search modal -->
<div class="modal-overlay" id="billModal">
  <div class="modal-box" style="min-width:520px">
    <div class="modal-head">
      <span>Search Purchase Return Bill</span>
      <button class="cls" onclick="closeBillSearch()">&#10006;</button>
    </div>
    <div class="modal-body">
      <input id="billSearchQ" style="width:100%;height:26px;font-size:12px;border:1px solid #e2c6c6;border-radius:7px;padding:2px 8px"
        placeholder="Enter bill no or supplier name..." oninput="doBillSearch(this.value)" autocomplete="off">
      <div style="margin-top:8px;max-height:240px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px">
        <table class="srch-tbl"><thead><tr><th>Bill No</th><th>Date</th><th>Supplier</th><th style="text-align:right">Net Total</th></tr></thead>
        <tbody id="billSrchBody"></tbody></table>
      </div>
    </div>
    <div class="modal-footer"><button class="mbtn" onclick="closeBillSearch()">Close</button></div>
  </div>
</div>

<!-- Item Search modal -->
<div class="modal-overlay" id="itemSearchModal">
  <div class="modal-box" style="min-width:720px;max-width:900px;height:520px;display:flex;flex-direction:column">
    <div class="modal-head" style="background:linear-gradient(135deg,#7f1d1d,#b91c1c)">
      <span>Item Search</span>
      <button class="cls" onclick="closeItemSearch()">&#10006;</button>
    </div>
    <div style="padding:8px 12px;display:flex;gap:8px;align-items:center;border-bottom:1px solid #e2e8f0;flex-shrink:0">
      <input id="itemSrchQ" placeholder="Search by code or name..." autocomplete="off"
        style="flex:1;height:30px;font-size:12px;border:1px solid #e2c6c6;border-radius:7px;padding:2px 10px"
        oninput="itemSrchFilter(this.value)" onkeydown="itemSrchKey(event)">
      <span style="font-size:11px;color:#6b7280" id="itemSrchCount"></span>
    </div>
    <div style="flex:1;overflow:auto">
      <table style="width:100%;border-collapse:collapse;font-size:11px">
        <thead>
          <tr style="background:#fef2f2;position:sticky;top:0;z-index:1">
            <th style="padding:5px 8px;text-align:left;border-bottom:2px solid #fca5a5;white-space:nowrap">Code</th>
            <th style="padding:5px 8px;text-align:left;border-bottom:2px solid #fca5a5">Name</th>
            <th style="padding:5px 8px;text-align:left;border-bottom:2px solid #fca5a5">Purity</th>
            <th style="padding:5px 8px;text-align:center;border-bottom:2px solid #fca5a5">Type</th>
            <th style="padding:5px 8px;text-align:right;border-bottom:2px solid #fca5a5">Touch</th>
            <th style="padding:5px 8px;text-align:right;border-bottom:2px solid #fca5a5">Qty</th>
            <th style="padding:5px 8px;text-align:right;border-bottom:2px solid #fca5a5">Weight</th>
          </tr>
        </thead>
        <tbody id="itemSrchBody"></tbody>
      </table>
    </div>
    <div class="modal-footer" style="font-size:11px;color:#6b7280">
      Double-click or press Enter to select &nbsp;|&nbsp; Esc to close
    </div>
  </div>
</div>

<!-- Cancel modal -->
<div class="modal-overlay" id="cancelModal">
  <div class="modal-box" style="min-width:360px">
    <div class="modal-head">
      <span>Cancel Purchase Return Bill</span>
      <button class="cls" onclick="document.getElementById('cancelModal').classList.remove('active')">&#10006;</button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:8px;font-size:12px">Cancel bill: <strong id="cancelBillNo"></strong></p>
      <input id="cancelReason" style="width:100%;height:26px;border:1px solid #e2c6c6;border-radius:7px;padding:2px 8px;font-size:12px" placeholder="Reason for cancellation">
    </div>
    <div class="modal-footer">
      <button class="mbtn" onclick="confirmCancel()" style="background:#fee2e2;color:#c53030;border-color:#fca5a5">Confirm Cancel</button>
      <button class="mbtn" onclick="document.getElementById('cancelModal').classList.remove('active')">Close</button>
    </div>
  </div>
</div>

<script>
// ── State ──────────────────────────────────────────────────────────────────
let items      = [];
let currentSlno = 0;
let selRowIdx   = -1;
let recalcTimer = null;

// ── Helpers ────────────────────────────────────────────────────────────────
const $  = id => document.getElementById(id);
function normDateVal(v){
  const raw = String(v ?? '').trim();
  if (!raw || raw === '00/00/00' || raw === '00/00/0000') return '';
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
  const m = raw.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
  if (!m) return raw;
  let yy = m[3];
  if (yy.length === 2) yy = '20' + yy;
  return yy + '-' + String(m[2]).padStart(2, '0') + '-' + String(m[1]).padStart(2, '0');
}
const gv = id => {
  const el = $(id);
  if (!el) return '';
  return el.type === 'date' ? normDateVal(el.value) : (el.value ?? '');
};
const sv = (id, v) => {
  const el = $(id); if (!el) return;
  if (el.tagName==='INPUT'||el.tagName==='SELECT'||el.tagName==='TEXTAREA') el.value = el.type === 'date' ? normDateVal(v) : v;
  else el.textContent = v;
};
const gf   = (id, d=0) => parseFloat(gv(id)) || d;
const r2   = n => Math.round(n*100)/100;
const r3   = n => Math.round(n*1000)/1000;
const fmt2 = n => parseFloat(n||0).toFixed(2);
const fmt3 = n => parseFloat(n||0).toFixed(3);
const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
const url  = p  => PR_CONFIG.siteUrl + p;

// ── Default row ─────────────────────────────────────────────────────────────
function newItemRow() {
  return {code:'',name:'',purity:'',rate:PR_CONFIG.rates.gold||0,
    qty:0,weight:0,stwgt:0,stprice:0,mud:0,touch:0,
    lessperc:0,lesswgt:0,netwgt:0,mcharge:0,amount:0,
    stktype:'',stkinnos:'N',iqtype:'',fr:'N'};
}

// ── Row calculation ─────────────────────────────────────────────────────────
function calcRow(i) {
  const r = items[i];
  const base = r.weight - r.mud - r.stwgt;
  if (Math.abs(r.touch) > 0) {
    r.lesswgt = r3(base - (base * r.touch) / 100);
  } else if (Math.abs(r.lessperc) > 0) {
    r.lesswgt = r3(base * r.lessperc / 100);
  } else {
    r.lesswgt = 0;
  }
  r.netwgt = r3(r.weight - r.stwgt - r.lesswgt - r.mud);
  if ((r.stkinnos||'').toUpperCase() === 'Y') {
    r.amount = r2(r.qty * r.rate + r.stprice + r.mcharge);
  } else {
    r.amount = r2(r.netwgt * r.rate + r.stprice + r.mcharge);
  }
  if (r.amount < 0) r.amount = 0;
}

// ── Render items table ──────────────────────────────────────────────────────
function renderItems() {
  const tb = $('itbody'); tb.innerHTML = '';
  items.forEach((r, i) => {
    const tr = document.createElement('tr');
    if (i === selRowIdx) tr.classList.add('sel');
    tr.onclick = () => { selRowIdx = i; document.querySelectorAll('#itbody tr').forEach((t,j)=>t.classList.toggle('sel',j===i)); };
    tr.innerHTML = `
      <td style="display:flex;align-items:center;gap:2px;padding:1px 2px">
        <input class="num" value="${esc(r.code)}" style="flex:1;min-width:0"
          oninput="items[${i}].code=this.value"
          onblur="if(this.value.trim())itemLookup(${i})"
          onkeydown="if(event.key==='F2'){event.preventDefault();openItemSearch(${i});}else rKey(event,${i},0)">
        <button tabindex="-1" title="Search Item (F2)"
          onclick="openItemSearch(${i})"
          style="width:18px;height:22px;padding:0;border:none;background:transparent;cursor:pointer;color:#ef4444;font-size:13px;line-height:1;flex-shrink:0">&#128269;</button>
      </td>
      <td><input value="${esc(r.name)}"
        oninput="items[${i}].name=this.value"
        onkeydown="rKey(event,${i},1)"></td>
      <td><input class="num" value="${esc(r.purity)}"
        oninput="items[${i}].purity=this.value"
        onkeydown="rKey(event,${i},2)"></td>
      <td><input class="num" value="${fmt2(r.rate)}"
        oninput="items[${i}].rate=+this.value||0;calcRow(${i});rfRow(${i});updateFoot();triggerRecalc()"
        onkeydown="rKey(event,${i},3)"></td>
      <td><input class="num" value="${r.qty}"
        oninput="items[${i}].qty=+this.value||0;calcRow(${i});rfRow(${i});updateFoot();triggerRecalc()"
        onkeydown="rKey(event,${i},4)"></td>
      <td><input class="num" value="${fmt3(r.weight)}"
        oninput="items[${i}].weight=+this.value||0;calcRow(${i});rfRow(${i});updateFoot();triggerRecalc()"
        onkeydown="rKey(event,${i},5)"></td>
      <td><input class="num" value="${fmt3(r.stwgt)}"
        oninput="items[${i}].stwgt=+this.value||0;calcRow(${i});rfRow(${i});updateFoot();triggerRecalc()"
        onkeydown="rKey(event,${i},6)"></td>
      <td><input class="num" value="${fmt2(r.stprice)}"
        oninput="items[${i}].stprice=+this.value||0;calcRow(${i});rfRow(${i});updateFoot();triggerRecalc()"
        onkeydown="rKey(event,${i},7)"></td>
      <td><input class="num" value="${fmt2(r.mud)}"
        oninput="items[${i}].mud=+this.value||0;calcRow(${i});rfRow(${i});updateFoot();triggerRecalc()"
        onkeydown="rKey(event,${i},8)"></td>
      <td><input class="num" value="${fmt2(r.touch)}"
        oninput="items[${i}].touch=+this.value||0;calcRow(${i});rfRow(${i});updateFoot();triggerRecalc()"
        onkeydown="rKey(event,${i},9)"></td>
      <td><input class="num" value="${fmt2(r.lessperc)}"
        oninput="items[${i}].lessperc=+this.value||0;calcRow(${i});rfRow(${i});updateFoot();triggerRecalc()"
        onkeydown="rKey(event,${i},10)"></td>
      <td><input class="num" value="${fmt3(r.lesswgt)}" readonly style="background:transparent;color:#6b7280"></td>
      <td><input class="num" value="${fmt3(r.netwgt)}" readonly style="background:transparent;color:#6b7280"></td>
      <td><input class="num" value="${fmt2(r.mcharge)}"
        oninput="items[${i}].mcharge=+this.value||0;calcRow(${i});rfRow(${i});updateFoot();triggerRecalc()"
        onkeydown="rKey(event,${i},13)"></td>
      <td><input class="num" value="${fmt2(r.amount)}" readonly style="background:transparent;font-weight:600"></td>
    `;
    tb.appendChild(tr);
  });
  sv('cntItems', items.length);
}

function esc(s){ const d=document.createElement('div');d.textContent=s||'';return d.innerHTML; }

function rfRow(i) {
  const r=items[i], tr=$('itbody').rows[i]; if(!tr) return;
  tr.cells[11].querySelector('input').value = fmt3(r.lesswgt);
  tr.cells[12].querySelector('input').value = fmt3(r.netwgt);
  tr.cells[14].querySelector('input').value = fmt2(r.amount);
}

function rKey(e, i, col) {
  if (e.key==='Tab' && !e.shiftKey) {
    e.preventDefault();
    const editCols=[0,1,2,3,4,5,6,7,8,9,10,13];
    const cur=editCols.indexOf(col), next=editCols[cur+1];
    if(next!==undefined){
      $('itbody').rows[i].cells[next].querySelector('input').focus(); return;
    }
    if(i<items.length-1) $('itbody').rows[i+1].cells[0].querySelector('input').focus();
    else addRow();
  }
}

// ── Footer totals ───────────────────────────────────────────────────────────
function updateFoot() {
  let qty=0,wgt=0,stwgt=0,stprice=0,mud=0,lesswgt=0,netwgt=0,mc=0,amt=0;
  items.forEach(r=>{
    if (!r.code || (+(r.weight||0) + +(r.qty||0) === 0)) return;
    qty+=r.qty||0; wgt+=r.weight; stwgt+=r.stwgt; stprice+=r.stprice;
    mud+=r.mud; lesswgt+=r.lesswgt; netwgt+=r.netwgt; mc+=r.mcharge; amt+=r.amount;
  });
  sv('ftQty',qty); sv('ftWgt',fmt3(wgt)); sv('ftStwgt',fmt3(stwgt));
  sv('ftStprice',fmt2(stprice)); sv('ftMud',fmt2(mud));
  sv('ftLesswgt',fmt3(lesswgt)); sv('ftNetwgt',fmt3(netwgt));
  sv('ftMc',fmt2(mc)); sv('ftAmt',fmt2(amt));
  sv('fBillTotal', fmt2(amt));
  triggerRecalc();
}

// ── Add / Delete ─────────────────────────────────────────────────────────────
function addRow() {
  items.push(newItemRow()); selRowIdx=items.length-1;
  renderItems();
  const tr=$('itbody').rows[items.length-1];
  if(tr) tr.cells[0].querySelector('input').focus();
}
function delRow() {
  if(!items.length) return;
  const idx=selRowIdx>=0?selRowIdx:items.length-1;
  items.splice(idx,1); selRowIdx=Math.min(idx,items.length-1);
  renderItems(); updateFoot();
}

// ── Recalc ──────────────────────────────────────────────────────────────────
function triggerRecalc() {
  clearTimeout(recalcTimer);
  recalcTimer = setTimeout(doRecalc, 350);
}
function doRecalc() {
  const payload = {
    bill_total:    gf('fBillTotal'),
    tax_perc:      gf('fTaxPerc'),
    others:        gf('fOthers'),
    paid_amt:      gf('fPaidAmt'),
    ob:            gf('fOB'),
    external:      $('fExternal').checked?1:0,
  };
  fetch(url('/api/purchase-return/recalc'),{
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()},
    body:JSON.stringify(payload)
  }).then(r=>r.ok?r.json():null).then(d=>{
    if(!d||d.error) return;
    sv('fTaxAmt',  fmt2(d.tax_amt||0));
    sv('fNetTotal',fmt2(d.net_total||0));
    sv('fBalance', fmt2(d.balance||0));
    sv('fCB',      fmt2(d.cb||0));
  }).catch(()=>{});
}
function onPaidChange() {
  triggerRecalc();
}
function onRateChange() {
  const rate=gf('fGoldRate');
  items.forEach((r,i)=>{ if(!r.rate||r.rate===PR_CONFIG.rates.gold){ r.rate=rate; calcRow(i); rfRow(i); } });
  PR_CONFIG.rates.gold=rate; updateFoot();
}

// ── Supplier ────────────────────────────────────────────────────────────────
function loadSupplierSelect() {
  fetch(url('/api/purchase-return/supplier-search?q='))
    .then(r=>r.json()).then(d=>{
      const sel=$('fSupSelect'); if(!sel) return;
      sel.innerHTML='<option value="">-- Select Supplier --</option>';
      (d.results||[]).forEach(s=>{
        const o=document.createElement('option');
        o.value=s.code; o.dataset.name=s.name||'';
        o.textContent=(s.name||'').trim()+(s.code?' ['+s.code+']':'');
        sel.appendChild(o);
      });
    }).catch(()=>{});
}
function onSupSelectChange(code) {
  if(!code) return;
  const sel=$('fSupSelect');
  const name=(sel.options[sel.selectedIndex].dataset.name)||'';
  sv('fSupCode',code); sv('fSupName',name);
  loadSupDet(code);
  setTimeout(()=>{ sel.value=''; },150);
}
let supTimer=null;
function onSupInput(v, by) {
  clearTimeout(supTimer);
  supTimer=setTimeout(()=>supSearch(v), 220);
}
function supSearch(q) {
  if(!q||q.length<1){ hideSup(); return; }
  fetch(url('/api/purchase-return/supplier-search?q=')+encodeURIComponent(q))
    .then(r=>r.json()).then(d=>{
      const drop=$('supDrop'); drop.innerHTML='';
      (d.results||[]).forEach(s=>{
        const div=document.createElement('div');
        div.className='s-row';
        div.innerHTML=`<span class="s-code">${s.code}</span>${s.name}`;
        div.onclick=()=>selectSup(s.code,s.name);
        drop.appendChild(div);
      });
      drop.style.display=drop.children.length?'block':'none';
    }).catch(()=>{});
}
function hideSup(){ $('supDrop').style.display='none'; }
function selectSup(code,name){
  sv('fSupCode',code); sv('fSupName',name); hideSup(); loadSupDet(code);
}
function onSupKey(e){
  if(e.key==='Enter'||e.key==='Tab'){ hideSup(); loadSupDet(gv('fSupCode').trim()); }
}
function onSupNameKey(e){
  if(e.key==='Enter') supSearch(gv('fSupName').trim());
}
function loadSupDet(code){
  if(!code) return;
  fetch(url('/api/purchase-return/supplier-details?code=')+encodeURIComponent(code))
    .then(r=>r.json()).then(d=>{
      if(d.error) return;
      sv('fSupCode', d.code||''); sv('fSupName', d.name||'');
      sv('fAddress', d.address||''); sv('fMobile', d.mobile||'');
      sv('fPan', d.pan||'');
      sv('fOB', fmt2(d.old_balance||0));
      sv('fCB', fmt2(d.old_balance||0));
    }).catch(()=>{});
}
function openSupModal(){
  sv('supModalQ',''); $('supModalBody').innerHTML='';
  $('supModal').classList.add('active'); $('supModalQ').focus();
}
function closeSupModal(){ $('supModal').classList.remove('active'); }
let supMTimer=null;
function supModalSearch(q){
  clearTimeout(supMTimer); if(!q) return;
  supMTimer=setTimeout(()=>{
    fetch(url('/api/purchase-return/supplier-search?q=')+encodeURIComponent(q))
      .then(r=>r.json()).then(d=>{
        const tb=$('supModalBody'); tb.innerHTML='';
        (d.results||[]).forEach(s=>{
          const tr=document.createElement('tr');
          tr.innerHTML=`<td>${s.code}</td><td>${s.name}</td><td>${s.mobile||''}</td>`;
          tr.onclick=()=>{ selectSup(s.code,s.name); closeSupModal(); };
          tb.appendChild(tr);
        });
      }).catch(()=>{});
  },250);
}

// ── Item lookup ─────────────────────────────────────────────────────────────
function itemLookup(i){
  const code=items[i].code.trim(); if(!code) return;
  const rate=items[i].rate||PR_CONFIG.rates.gold;
  fetch(`${url('/api/purchase-return/item-lookup')}?code=${encodeURIComponent(code)}&gold_rate=${rate}`)
    .then(r=>r.json()).then(d=>{
      if(d.error||!d.code) return;
      items[i].name    = d.name    || '';
      items[i].purity  = d.purity  || '';
      items[i].touch   = parseFloat(d.touch)  || 0;
      items[i].rate    = parseFloat(d.rate)   || rate;
      items[i].stktype = d.stktype  || '';
      items[i].stkinnos= d.stkinnos || 'N';
      items[i].iqtype  = d.iqtype   || '';
      calcRow(i); renderItems(); updateFoot();
      try { $('itbody').rows[i].cells[4].querySelector('input').focus(); } catch(e){}
    }).catch(()=>{});
}

// ── Item Search popup ─────────────────────────────────────────────────────────
let _itemSrchRowIdx = 0;
let _itemSrchAll    = [];
let _itemSrchFil    = [];
let _itemSrchSel    = -1;

function openItemSearch(rowIdx) {
  _itemSrchRowIdx = rowIdx;
  _itemSrchSel    = -1;
  $('itemSearchModal').classList.add('active');
  const q = (items[rowIdx] && items[rowIdx].code) ? items[rowIdx].code : '';
  $('itemSrchQ').value = q;
  if (_itemSrchAll.length === 0) {
    fetch(url('/api/purchase-return/item-search?q='))
      .then(r=>r.json()).then(d=>{
        _itemSrchAll = d.results || [];
        itemSrchFilter(q);
      }).catch(()=>{});
  } else {
    itemSrchFilter(q);
  }
  setTimeout(()=>{ $('itemSrchQ').focus(); $('itemSrchQ').select(); }, 80);
}

function closeItemSearch() {
  $('itemSearchModal').classList.remove('active');
  try { $('itbody').rows[_itemSrchRowIdx].cells[0].querySelector('input').focus(); } catch(e){}
}

function itemSrchFilter(q) {
  q = (q||'').trim().toLowerCase();
  _itemSrchFil = q
    ? _itemSrchAll.filter(r =>
        (r.code||'').toLowerCase().includes(q) ||
        (r.name||'').toLowerCase().includes(q) ||
        (r.purity||'').toLowerCase().includes(q))
    : _itemSrchAll;
  _itemSrchSel = _itemSrchFil.length ? 0 : -1;
  renderItemSrch();
}

function renderItemSrch() {
  const tb = $('itemSrchBody');
  $('itemSrchCount').textContent = _itemSrchFil.length + ' item(s)';
  if (!_itemSrchFil.length) {
    tb.innerHTML = '<tr><td colspan="7" style="padding:20px;text-align:center;color:#9ca3af">No items found</td></tr>';
    return;
  }
  tb.innerHTML = _itemSrchFil.map((r,i) => `
    <tr data-idx="${i}" ondblclick="itemSrchPick(${i})"
      onclick="itemSrchHighlight(${i})"
      style="cursor:pointer;background:${i===_itemSrchSel?'#fecaca':''}">
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;font-weight:600;white-space:nowrap">${esc(r.code)}</td>
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9">${esc(r.name)}</td>
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9">${esc(r.purity)}</td>
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:center">${esc(r.itype)}</td>
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:right">${fmt2(r.touch)}</td>
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:right">${r.qty}</td>
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:right">${fmt3(r.weight)}</td>
    </tr>`).join('');
  if (_itemSrchSel >= 0) {
    const row = tb.querySelector(`tr[data-idx="${_itemSrchSel}"]`);
    if (row) row.scrollIntoView({ block: 'nearest' });
  }
}

function itemSrchHighlight(i) {
  _itemSrchSel = i;
  $('itemSrchBody').querySelectorAll('tr').forEach((tr,j)=>{
    tr.style.background = j===i ? '#fecaca' : '';
  });
}

function itemSrchPick(i) {
  const r = _itemSrchFil[i];
  if (!r) return;
  const ri = _itemSrchRowIdx;
  items[ri].code    = r.code;
  items[ri].name    = r.name;
  items[ri].purity  = r.purity;
  items[ri].touch   = r.touch;
  items[ri].stktype = r.stktype;
  closeItemSearch();
  itemLookup(ri);
}

function itemSrchKey(e) {
  const max = _itemSrchFil.length;
  if (e.key === 'ArrowDown')  { _itemSrchSel = Math.min(_itemSrchSel+1, max-1); renderItemSrch(); e.preventDefault(); }
  else if (e.key === 'ArrowUp')   { _itemSrchSel = Math.max(_itemSrchSel-1, 0); renderItemSrch(); e.preventDefault(); }
  else if (e.key === 'Enter')     { if (_itemSrchSel>=0) itemSrchPick(_itemSrchSel); e.preventDefault(); }
  else if (e.key === 'Escape')    { closeItemSearch(); e.preventDefault(); }
}

// ── Save ────────────────────────────────────────────────────────────────────
function saveBill(){
  if(!gv('fSupCode').trim()){ alert('Please enter a supplier.'); return; }
  if(!items.filter(r=>r.code).length){ alert('Please add at least one item.'); return; }
  $('btnSave').disabled=true;
  const payload={
    mode: PR_CONFIG.mode||'bill',
    slno: currentSlno,
    doc_no:   gv('fDocNo'),
    supp_bill_no: gv('fBillNo'),
    bill_date:gv('fDate'),
    sup_code: gv('fSupCode'), sup_name:gv('fSupName'),
    address:  gv('fAddress'), pan:gv('fPan'), mobile:gv('fMobile'),
    gst_no:   '',
    ob:       gf('fOB'),
    sm_code:  gv('fSalesMan'),
    gold_rate:gf('fGoldRate'),
    bill_total:gf('fBillTotal'),
    tax_perc: gf('fTaxPerc'), tax_amt:gf('fTaxAmt'),
    others:gf('fOthers'), paid_amt:gf('fPaidAmt'),
    net_total:gf('fNetTotal'), balance:gf('fBalance'),
    chq_bank:gv('fChqBank'), chq_amt:gf('fChqAmt'),
    chq_no:gv('fChqNo'), chq_date:gv('fChqDate'),
    chq_pdc:  $('fPdc').checked?'Y':'N',
    btype:    gv('fBType'), state_code:gv('fStateCode'),
    note:     gv('fNote'), counter:gv('fCounter'),
    interstate: $('fInterstate').checked?'Y':'N',
    tax_external: $('fExternal').checked?'Y':'N',
    items:items.filter(r=>r.code),
  };
  fetch(url('/api/purchase-return/save'),{
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()},
    body:JSON.stringify(payload)
  }).then(r=>r.json()).then(d=>{
    $('btnSave').disabled=false;
    if(!d.ok){ alert('Error: '+(d.message||d.error||'Save failed')); return; }
    alert('Purchase return saved: '+(d.doc_no||''));
    resetForm(); loadNextBillNo();
  }).catch(e=>{ $('btnSave').disabled=false; alert('Save failed: '+e); });
}

// ── Load bill ────────────────────────────────────────────────────────────────
function loadBill(billNo){
  if(!billNo) return;
  fetch(url('/api/purchase-return/get?bill_no=')+encodeURIComponent(billNo))
    .then(r=>r.json()).then(d=>{ if(d.error||!d.doc_no){ alert(d.error||'Not found'); return; } applyBill(d); })
    .catch(()=>{});
}
function applyBill(d){
  currentSlno=d.slno||0;
  sv('fDocNo',d.doc_no||''); sv('fBillNo',d.bill_no||'');
  sv('fDate',d.date||'');
  sv('fSupCode',d.sup_code||''); sv('fSupName',d.sup_name||'');
  sv('fAddress',d.address||''); sv('fPan',d.pan||''); sv('fMobile',d.mobile||'');
  sv('fOB',fmt2(d.ob||0)); sv('fSalesMan',d.salesman||'');
  sv('fBillTotal',fmt2(d.bill_total||0));
  sv('fTaxPerc',d.tax_perc||0); sv('fTaxAmt',fmt2(d.tax_amt||0));
  sv('fOthers',fmt2(d.others||0)); sv('fPaidAmt',fmt2(d.paid_amt||0));
  sv('fNetTotal',fmt2(d.net_total||0)); sv('fBalance',fmt2(d.balance||0));
  sv('fChqBank',d.chq_bank||''); sv('fChqAmt',fmt2(d.chq_amt||0));
  sv('fChqNo',d.chq_no||''); sv('fChqDate',d.chq_date||'');
  sv('fBType',d.btype||''); sv('fStateCode',d.state_code||'');
  sv('fStateName',d.state_code||''); sv('fNote',d.note||'');
  sv('fCounter',d.counter||'');
  if(d.interstate) $('fInterstate').checked=d.interstate==='Y';
  if(d.external)   $('fExternal').checked  =d.external==='Y';
  items=(d.items||[]).map(r=>({
    code:r.code||'',name:r.name||'',purity:r.iqtype||'',
    rate:+r.rate||0,qty:+r.qty||0,weight:+r.weight||0,
    stwgt:+r.stwgt||0,stprice:+r.stprice||0,mud:+r.mud||0,
    touch:+r.touch||0,lessperc:+r.lessperc||0,lesswgt:+r.lesswgt||0,
    netwgt:(+r.weight||0)-(+r.lesswgt||0),
    mcharge:+r.mcharge||0,amount:+r.amount||0,
    stktype:r.stktype||'',fr:r.fr||'N'
  }));
  renderItems(); updateFoot();
  sv('fCB', fmt2(d.cb||0));
}

// ── Navigation ───────────────────────────────────────────────────────────────
function prevBill(){
  fetch(url('/api/purchase-return/prev?doc_no=')+encodeURIComponent(gv('fDocNo')))
    .then(r=>r.json()).then(d=>{
      if(d.ok && d.bill_no) loadBill(d.bill_no);
      else alert(d.message||'No previous bill');
    }).catch(()=>{});
}
function nextBill(){
  fetch(url('/api/purchase-return/next?doc_no=')+encodeURIComponent(gv('fDocNo')))
    .then(r=>r.json()).then(d=>{
      if(d.ok && d.bill_no) loadBill(d.bill_no);
      else alert(d.message||'No next bill');
    }).catch(()=>{});
}

// ── Search ───────────────────────────────────────────────────────────────────
function openBillSearch(){ sv('billSearchQ',''); $('billSrchBody').innerHTML=''; $('billModal').classList.add('active'); $('billSearchQ').focus(); }
function closeBillSearch(){ $('billModal').classList.remove('active'); }
let bsTimer=null;
function doBillSearch(q){
  clearTimeout(bsTimer); if(!q) return;
  bsTimer=setTimeout(()=>{
    fetch(url('/api/purchase-return/search?q=')+encodeURIComponent(q))
      .then(r=>r.json()).then(d=>{
        const tb=$('billSrchBody'); tb.innerHTML='';
        (d.results||[]).forEach(b=>{
          const tr=document.createElement('tr');
          tr.innerHTML=`<td>${b.doc_no}</td><td>${b.date}</td><td>${b.sup_name}</td><td style="text-align:right">${fmt2(b.net_total)}</td>`;
          tr.onclick=()=>{ loadBill(b.doc_no); closeBillSearch(); };
          tb.appendChild(tr);
        });
      }).catch(()=>{});
  },300);
}

// ── Cancel ───────────────────────────────────────────────────────────────────
function openCancelModal(){
  if(!gv('fDocNo')){ alert('Load a bill first'); return; }
  sv('cancelBillNo',gv('fDocNo')); sv('cancelReason','');
  $('cancelModal').classList.add('active');
}
function confirmCancel(){
  fetch(url('/api/purchase-return/cancel'),{
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()},
    body:JSON.stringify({doc_no:gv('fDocNo'),reason:gv('cancelReason')})
  }).then(r=>r.json()).then(d=>{
    $('cancelModal').classList.remove('active');
    if(!d.ok){ alert('Error: '+(d.message||'Cancel failed')); return; }
    alert('Bill cancelled'); resetForm(); loadNextBillNo();
  }).catch(()=>{});
}

// ── State sync ────────────────────────────────────────────────────────────────
function syncState(code){ sv('fStateName',code); }
function syncStateFromName(code){ sv('fStateCode',code); }

// ── Manual mode ───────────────────────────────────────────────────────────────
function onManualChk(){
  $('fDocNo').readOnly=!$('fManual').checked;
  if(!$('fManual').checked) loadNextBillNo();
}

// ── New / Reset ───────────────────────────────────────────────────────────────
function newBill(){ resetForm(); loadNextBillNo(); }
function resetForm(){
  currentSlno=0; selRowIdx=-1;
  ['fDocNo','fBillNo','fSupCode','fSupName','fAddress','fPan','fMobile','fNote'].forEach(id=>sv(id,''));
  sv('fDate', todayDMY()); sv('fOB','.00'); sv('fCB','.00');
  ['fBillTotal','fTaxAmt','fOthers','fPaidAmt','fNetTotal','fBalance','fChqAmt'].forEach(id=>sv(id,'.00'));
  sv('fTaxPerc','0'); sv('fChqNo',''); sv('fChqDate','00/00/0000');
  ['fInterstate','fExternal','fPdc'].forEach(id=>$(id)&&($(id).checked=false));
  items=[]; selRowIdx=-1;
  renderItems(); updateFoot(); addRow();
}
function todayDMY(){
  const d=new Date(); const p=n=>String(n).padStart(2,'0');
  return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate());
}

// ── Next bill number ──────────────────────────────────────────────────────────
function loadNextBillNo(){
  const bt=gv('fBType');
  fetch(url('/api/purchase-return/next-number')+(bt?'?bill_type='+encodeURIComponent(bt):''))
    .then(r=>r.json()).then(d=>{ if(d.bill_no) sv('fDocNo',d.bill_no); }).catch(()=>{});
}
function onBillTypeChange(){ loadNextBillNo(); }

// ── Exit ──────────────────────────────────────────────────────────────────────
function doExit(){
  if(window.parent&&window.parent!==window) window.parent.postMessage({action:'closeModule'},'*');
  else window.history.back();
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded',()=>{
  if (window.goldappDateUi) {
    window.goldappDateUi.attachCalendarInputs(['fDate', 'fChqDate']);
  }
  loadNextBillNo(); addRow(); loadSupplierSelect();
  document.addEventListener('click',e=>{ if(!e.target.closest('.sup-row')) hideSup(); });
  if(PR_CONFIG.mode==='cancel'||PR_CONFIG.mode==='reprint') $('btnSave').style.display='none';
  const qs = new URLSearchParams(window.location.search);
  const urlDocNo = qs.get('doc_no');
  const autoPrint = qs.get('autoprint') === '1';
  if (urlDocNo) {
    loadBill(urlDocNo);
    if (autoPrint && PR_CONFIG.mode === 'reprint') {
      setTimeout(() => window.print(), 400);
    }
  }
});
</script>
</body>
</html>
