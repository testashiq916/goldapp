<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('js/goldapp-common.js') }}"></script>
<title>{{ $title }}</title>
<script>
window.OB_CONFIG = {
  siteUrl:    @json(request()->root()),
  mode:       @json($mode),
  preloadDoc: @json($preloadDoc ?? ''),
  rates: {
    gold:     {{ $rates['gold']     ?? 0 }},
    silver:   {{ $rates['silver']   ?? 0 }},
    platinum: {{ $rates['platinum'] ?? 0 }},
  },
  salesmen:  @json(array_map('get_object_vars', $salesmen)),
  counters:  @json(array_map('get_object_vars', $counters)),
  cashBanks: @json($cashBanks),
  exchItems: @json($exchItems ?? []),
  software:  @json($software ?? []),
};
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter','Segoe UI',Tahoma,sans-serif;font-size:12px;color:#1a202c;overflow:hidden;height:100vh;
  background:radial-gradient(circle at 10% -10%,#f0fdf4 0%,#f7fdf9 40%,#f0faf4 100%)}
input,select,button{transition:all .15s ease}
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-track{background:#f7fafc}
::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:3px}

.main-window{background:#fff;border:1px solid #c6e2d6;border-radius:12px;
  box-shadow:0 10px 30px rgba(33,89,52,.10);margin:6px;overflow:hidden;position:relative;
  display:flex;flex-direction:column;height:calc(100vh - 12px)}
.title-bar{background:linear-gradient(135deg,#14532d,#166534);color:#fff;
  padding:6px 14px;font-weight:700;font-size:13px;display:flex;align-items:center;gap:8px;letter-spacing:.3px;flex-shrink:0}
.title-bar .icon{width:14px;height:14px;background:#86efac;border-radius:4px;flex-shrink:0}

.top-section{background:#f9fdfb;padding:5px 8px;display:grid;
  grid-template-columns:1.2fr 1fr 1.1fr;gap:2px 12px;border-bottom:1px solid #c6e2d6;flex-shrink:0}
.top-section .row{display:flex;align-items:center;gap:4px;padding:0;min-height:24px}
.top-section label{font-weight:600;font-size:11px;min-width:70px;color:#6b7280}
.top-section input,.top-section select{font-size:11px;padding:1px 5px;border:1px solid #c6e2d6;
  background:#fff;height:24px;border-radius:6px;color:#2d3748;
  box-shadow:inset 0 1px 2px rgba(17,24,39,.03)}
.top-section input:focus,.top-section select:focus{border-color:#86efac;
  box-shadow:0 0 0 2px rgba(34,197,94,.14);outline:none}
.top-section input.sm{width:66px}
.top-section input.md{width:106px}
.top-section input.lg{flex:1;min-width:0;width:auto}
.top-section .row.cust-row{position:relative}
#custDrop{position:absolute;top:26px;left:70px;width:380px;max-height:200px;overflow-y:auto;
  background:#111827;border:1px solid #374151;border-radius:6px;
  box-shadow:0 6px 16px rgba(0,0,0,.35);z-index:500;display:none;font-size:11px;color:#f9fafb}
#custDrop .s-row{padding:4px 8px;cursor:pointer;border-bottom:1px solid #374151}
#custDrop .s-row:hover{background:#1f2937}
#custDrop .s-row .s-code{font-weight:600;color:#86efac;margin-right:5px}
.sup-btns button{font-size:10px;padding:1px 6px;border:1px solid #c6e2d6;border-radius:5px;
  cursor:pointer;background:#fff;height:24px;color:#374151}
.sup-btns button:hover{background:#f0fdf4}

/* Table area grows to fill remaining height */
.table-area{flex:1;display:flex;flex-direction:column;overflow:hidden;margin:4px 6px 0}
.table-container{border:1px solid #c6e2d6;border-radius:8px;
  background:#fff;overflow:auto;box-shadow:0 2px 8px rgba(32,92,55,.05);flex:1}
table.items{width:100%;border-collapse:collapse;font-size:11px}
table.items thead th{background:linear-gradient(180deg,#14532d,#166534);color:#f0fdf4;
  padding:4px 4px;border:1px solid #15803d;font-weight:600;font-size:10px;
  text-align:center;white-space:nowrap;text-transform:uppercase;letter-spacing:.4px;
  position:sticky;top:0;z-index:2}
table.items tbody td{border:1px solid #e0f0e8;padding:1px 2px;text-align:center;height:24px}
table.items tbody tr:nth-child(odd){background:#fff}
table.items tbody tr:nth-child(even){background:#f7fdf9}
table.items tbody tr:hover{background:#f0fdf4}
table.items tbody tr.sel td{background:#bbf7d0}
table.items tbody input{font-size:11px;border:none;background:transparent;
  text-align:center;width:100%;height:100%}
table.items tbody input:focus{background:#fffff0;outline:2px solid #22c55e;border-radius:2px}
table.items tbody input.num{text-align:right}

.table-footer{display:flex;align-items:center;background:#f9fdfb;
  padding:3px 8px;gap:8px;font-size:11px;font-weight:600;
  color:#2d3748;border-top:1px solid #c6e2d6;flex-shrink:0}
.table-footer button{background:#fff;border:1px solid #c6e2d6;border-radius:7px;
  padding:1px 10px;font-size:11px;cursor:pointer;font-weight:600;color:#3f4a5b}
.table-footer button:hover{background:#f0fdf4;border-color:#86efac}
.table-footer button:active{transform:translateY(1px)}
.tf-label{color:#14532d;font-weight:700}
.tfv{text-align:right;font-variant-numeric:tabular-nums;min-width:30px}

.bottom-section{background:#f9fdfb;padding:3px 8px 4px;
  display:flex;align-items:flex-start;gap:8px;
  border-top:1px solid #c6e2d6;font-size:11px;flex-shrink:0}
.fp1,.fp2,.fp3{display:flex;flex-direction:column;gap:2px}
.fp1,.fp2{flex-shrink:0}
.fp3{flex:1;min-width:0}
.fp1 .fr,.fp2 .fr,.fp3 .fr{display:flex;align-items:center;gap:3px;height:22px;white-space:nowrap}
.fl1{font-weight:600;font-size:11px;color:#4a5568;min-width:68px;flex-shrink:0;white-space:nowrap;padding-right:3px}
.fl2{font-weight:600;font-size:11px;color:#4a5568;min-width:62px;flex-shrink:0;white-space:nowrap;padding-right:3px}
.fl{font-weight:600;font-size:11px;color:#4a5568;padding:0 2px 0 4px;white-space:nowrap;flex-shrink:0}
input.fv,select.fv{font-size:11px;padding:1px 4px;border:1px solid #c6e2d6;
  background:#fff;height:22px;border-radius:5px;color:#2d3748;flex-shrink:0;
  box-shadow:inset 0 1px 2px rgba(17,24,39,.03)}
input.fv:focus,select.fv:focus{border-color:#86efac;box-shadow:0 0 0 2px rgba(34,197,94,.14);outline:none}
.fw36{width:38px}.fw46{width:48px}.fw70{width:72px}.fw84{width:86px}.fw94{width:96px}.fwx{flex:1;min-width:60px}
.flbl{font-size:11px;color:#4a5568;padding-right:4px;flex-shrink:0}
.fchk{display:inline-flex;align-items:center;gap:2px;flex-shrink:0}
.fchk input[type=checkbox]{width:13px;height:13px;margin:0;cursor:pointer}
.foot-btns{display:flex;flex-direction:column;gap:3px;min-width:108px;flex-shrink:0;padding-left:4px}
.fbtn{background:linear-gradient(180deg,#f5f5f5,#e8e8e8);border:1px solid #adadad;
  border-radius:4px;padding:2px 6px;font-size:11px;cursor:pointer;font-weight:600;
  color:#111;height:26px;font-family:inherit;text-align:center;white-space:nowrap}
.fbtn:hover{background:linear-gradient(180deg,#fff,#f0f0f0)}
.fbtn:active{background:#e0e0e0;transform:translateY(1px)}
.fbtn.fb-save{background:linear-gradient(180deg,#dcfce7,#bbf7d0);border-color:#22c55e;color:#14532d}
.fbtn.fb-save:hover{background:linear-gradient(180deg,#f0fdf4,#dcfce7)}
.inline-chk{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#4a5568}
.secondary-sync-inline{display:flex;align-items:center;justify-content:center;gap:4px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;font-size:11px;font-weight:600;color:#334155}

.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);
  z-index:1000;justify-content:center;align-items:center}
.modal-overlay.active{display:flex}
.modal-box{background:#fff;border:1px solid #c6e2d6;border-radius:10px;
  min-width:380px;max-width:580px;box-shadow:0 8px 32px rgba(0,0,0,.15);overflow:hidden}
.modal-head{background:linear-gradient(135deg,#14532d,#166534);color:#fff;
  padding:8px 12px;font-weight:700;font-size:12px;display:flex;justify-content:space-between;align-items:center}
.modal-head .cls{background:rgba(255,255,255,.15);border:none;color:#fff;width:22px;height:20px;
  font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:4px}
.modal-head .cls:hover{background:#fc8181}
.modal-body{padding:10px 12px}
.modal-footer{padding:6px 12px;border-top:1px solid #e2e8f0;display:flex;gap:6px;justify-content:flex-end}
.mbtn{padding:4px 18px;font-size:11px;font-weight:600;border:1px solid #d0d9ea;
  border-radius:8px;background:#fff;cursor:pointer;color:#2d3748}
.mbtn:hover{background:#f0fdf4}
.ok-modal-text{padding:18px 14px;font-size:13px;color:#111827;line-height:1.5}
.ok-modal-footer{padding:10px 14px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;background:#f8fafc}
.ok-modal-btn{min-width:84px;height:34px;border-radius:18px;border:2px solid #6b46c1;background:#6b46c1;color:#fff;font-weight:700;cursor:pointer}
.ok-modal-btn:hover{background:#553c9a;border-color:#553c9a}
.srch-tbl{width:100%;border-collapse:collapse;font-size:11px}
.srch-tbl th{background:linear-gradient(180deg,#14532d,#166534);color:#f0fdf4;
  padding:4px 8px;text-align:left;font-weight:600}
.srch-tbl td{padding:3px 8px;border-bottom:1px solid #e0f0e8;cursor:pointer}
.srch-tbl tr:hover td{background:#bbf7d0}

/* Exchange section */
.exch-bar{display:flex;align-items:center;background:#fffbeb;
  padding:4px 10px;gap:10px;font-size:11px;font-weight:600;
  color:#744210;border-top:1px solid #fde68a}
table.exch{width:100%;border-collapse:collapse;font-size:11px}
table.exch thead th{background:linear-gradient(180deg,#92400e,#78350f);color:#fef3c7;
  padding:4px 5px;border:1px solid #92400e;font-weight:600;font-size:10px;
  text-align:center;white-space:nowrap;letter-spacing:.4px}
table.exch tbody td{border:1px solid #fde68a;padding:1px 2px;text-align:center;height:22px}
table.exch tbody tr:nth-child(odd){background:#fffdf5}
table.exch tbody tr:nth-child(even){background:#fef9e7}
table.exch tbody tr:hover{background:#fef3c7}
table.exch tbody input{font-size:11px;border:none;background:transparent;
  text-align:center;width:100%;height:100%}
table.exch tbody input:focus{background:#fffff0;outline:2px solid #f59e0b;border-radius:2px}
table.exch tbody input.num{text-align:right}

/* Sales Bill style modal windows (for Exchange + Sales Return) */
.exchange-window{width:980px;max-width:calc(100vw - 40px);background:#fff;border:1px solid #d69e2e;border-radius:8px;overflow:hidden;
  box-shadow:0 10px 34px rgba(0,0,0,.25)}
.exchange-title{background:linear-gradient(180deg,#2c5282,#2b6cb0);color:#fff;padding:6px 10px;font-size:14px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.exchange-title .close-btn,.sr-title .close-btn{background:rgba(255,255,255,.15);color:#fff;border:none;width:22px;height:20px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:4px}
.exchange-title .close-btn:hover,.sr-title .close-btn:hover{background:#fc8181}
.exchange-body,.sr-body{background:#fefce8;padding:8px;border-bottom:1px solid #d69e2e}
table.exchange-table,table.sr-table{width:100%;border-collapse:collapse;font-size:11px}
table.exchange-table thead th,table.sr-table thead th{background:linear-gradient(180deg,#975a16,#744210);color:#fef3c7;border:1px solid #d69e2e;padding:4px 5px;text-align:center;font-weight:700}
table.exchange-table tbody td,table.sr-table tbody td{border:1px solid #e8d8a4;background:#fefcbf;padding:1px 2px;height:22px;text-align:center}
table.exchange-table tbody input,table.sr-table tbody input{width:100%;height:100%;border:none;background:transparent;font-size:11px;text-align:center}
table.exchange-table tbody input.num,table.sr-table tbody input.num{text-align:right}
table.exchange-table tbody input:focus,table.sr-table tbody input:focus{background:#fffff0;outline:2px solid #d69e2e;border-radius:2px}
.exchange-footer-bar,.sr-footer-bar{display:flex;align-items:center;gap:6px;background:#fef9c3;padding:4px 8px;font-size:11px;font-weight:700;color:#744210;border-top:1px solid #d69e2e}
.exchange-footer-bar button,.sr-footer-bar button{padding:2px 12px;font-size:11px;border:1px solid #d69e2e;border-radius:3px;background:#fff;cursor:pointer;color:#744210;font-weight:700}
.exchange-footer-bar .count,.sr-footer-bar .count{padding:0 8px;border:1px solid #d69e2e;border-radius:10px;line-height:18px;min-width:24px;text-align:center;background:#fff4ce}
.exchange-footer-bar .s-badge{font-weight:700;color:#b91c1c}
.exchange-totals{display:flex;align-items:center;justify-content:space-between;background:#fef3c7;padding:5px 10px;border-top:1px solid #d69e2e}
.exchange-totals .group{display:flex;gap:20px;font-size:11px;font-weight:700;color:#744210}
.exchange-totals .val{color:#1f2937}
.exchange-bottom{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;background:#fff}
.exchange-btn-group,.sr-btn-group{display:flex;gap:8px}
.exchange-btn-group button,.sr-btn-group button{padding:4px 16px;font-size:12px;font-weight:700;border-radius:4px;border:none;cursor:pointer}
.exchange-btn-group .save,.sr-btn-group .save{background:#48bb78;color:#fff}
.exchange-btn-group .cancel,.sr-btn-group .cancel{background:#f56565;color:#fff}
.salesreturn-window{width:980px;max-width:calc(100vw - 40px);background:#fff;border:1px solid #d69e2e;border-radius:8px;overflow:hidden;
  box-shadow:0 10px 34px rgba(0,0,0,.25)}
.sr-title{background:linear-gradient(180deg,#2c5282,#2b6cb0);color:#fff;padding:6px 10px;font-size:14px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.sr-eva-bar{display:flex;align-items:center;background:#2f855a;color:#fff;font-size:11px;font-weight:700;padding:4px 10px}
.sr-bottom{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:8px 10px;background:#fef3c7;border-top:1px solid #d69e2e}
.sr-bottom .field-group{display:flex;align-items:center;gap:4px}
.sr-bottom label{font-weight:700;font-size:11px;color:#744210}
.sr-bottom input{height:24px;border:1px solid #d69e2e;border-radius:3px;padding:0 6px;font-size:11px;width:60px;text-align:right;background:#fff}
.sr-bottom input.wide{width:80px}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="main-window">

  <div class="title-bar">
    <div class="icon"></div>
    <span id="titleText">{{ $title }}</span>
  </div>

  <!-- TOP SECTION -->
  <div class="top-section">
    <!-- Col 1: Order No, Customer, Address -->
    <div>
      <div class="row">
        <label>Order No</label>
        <input id="fDocNo" class="md" value="" readonly style="background:#fefce8">
        <span class="inline-chk" style="margin-left:4px">
          <input type="checkbox" id="fManual" onchange="onManualChk()" style="width:14px;height:14px">
          <span style="font-size:11px;color:#4a5568">Manual</span>
        </span>
      </div>
      <div class="row cust-row">
        <label>Customer</label>
        <input id="fCustCode" class="sm" placeholder="Code" autocomplete="off"
          oninput="onCustInput(this.value)" onkeydown="onCustKey(event)">
        <input id="fCustName" class="lg" placeholder="Name" autocomplete="off"
          oninput="onCustInput(this.value)" onkeydown="onCustNameKey(event)">
        <div class="sup-btns" style="display:flex;gap:2px">
          <button onclick="openCustModal()" title="Search">&#94;</button>
          <button onclick="openCustomerCreate()" title="Create Customer">+</button>
        </div>
        <div id="custDrop"></div>
      </div>
      <div class="row">
        <label>Cust. List</label>
        <select id="fCustSelect" class="lg" onchange="onCustSelectChange(this.value)" style="min-width:0;flex:1">
          <option value="">-- Select Customer --</option>
        </select>
      </div>
      <div class="row"><label>Address</label><input id="fAddress" class="lg" value=""></div>
      <div class="row"><label>Phone</label><input id="fPhone" class="md" value="" oninput="onPhoneInputAuto(this.value)" onblur="autoFetchCustomerByPhone(this.value)">
        <label style="min-width:auto;margin-left:8px">Note</label><input id="fNote" class="lg" value=""></div>
    </div>

    <!-- Col 2: Date, OB, Salesman, Due Date -->
    <div>
      <div class="row">
        <label>Date</label>
        <input id="fDate" class="md" value="{{ date('d/m/Y') }}" style="background:#fefce8">
      </div>
      <div class="row">
        <label>OB</label>
        <input id="fOB" class="sm" value=".00" readonly style="background:#f1f5f9">
        <label style="min-width:auto;margin-left:8px">CB</label>
        <input id="fCB" class="sm" value=".00" readonly style="background:#f1f5f9">
      </div>
      <div class="row">
        <label>Sales Man</label>
        <select id="fSalesMan" style="flex:1;min-width:0;font-size:11px;height:26px;border-radius:7px;border:1px solid #c6e2d6">
          <option value="">--</option>
          @foreach($salesmen as $s)<option value="{{ $s->code }}">{{ $s->name }}</option>@endforeach
        </select>
      </div>
      <div class="row">
        <label>Counter</label>
        <select id="fCounter" style="flex:1;min-width:0;font-size:11px;height:26px;border-radius:7px;border:1px solid #c6e2d6">
          <option value="">--</option>
          @foreach($counters as $c)<option value="{{ $c->code }}">{{ $c->name }}</option>@endforeach
        </select>
      </div>
    </div>

    <!-- Col 3: Gold Rate, Rate*8gm, PAN, Mobile, GSTIN -->
    <div>
      <div class="row">
        <label>Gold Rate</label>
        <input id="fGoldRate" class="sm" value="{{ $rates['gold'] ?? 0 }}" oninput="onRateChange()">
        <label style="min-width:auto;margin-left:8px">8gm</label>
        <input id="fRate8gm" class="sm" value="{{ round(($rates['gold'] ?? 0) * 8, 2) }}" readonly style="background:#f1f5f9">
      </div>
      <div class="row">
        <label>Mobile</label>
        <input id="fMobile" class="md" value="" readonly style="background:#f1f5f9">
      </div>
      <div class="row">
        <label>PAN/Adhr</label>
        <input id="fPan" class="md" value="" readonly style="background:#f1f5f9">
      </div>
      <div class="row">
        <label>GSTIN</label>
        <input id="fGstNo" class="md" value="" readonly style="background:#f1f5f9">
      </div>
    </div>
  </div>

  <!-- ITEMS TABLE -->
  <div class="table-area">
    <div class="table-container">
      <table class="items" id="itbl">
        <thead>
          <tr>
            <th style="width:62px">Item Code</th>
            <th style="width:130px">Item Name</th>
            <th style="width:90px">Model</th>
            <th style="width:58px">Rate</th>
            <th style="width:34px">Qty</th>
            <th style="width:60px">Weight</th>
            <th style="width:52px">Stone<br>Wgt</th>
            <th style="width:52px">Stone<br>Price</th>
            <th style="width:46px">MC</th>
            <th style="width:52px">Wastage</th>
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
      <span class="tfv" id="ftMc">0.00</span>
      <span class="tfv" id="ftWastage">0.000</span>
      <span class="tfv" id="ftAmt">0.00</span>
    </div>
  </div>

  <input type="hidden" id="fExchAmt" value="0">

  <!-- BOTTOM SECTION -->
  <div class="bottom-section">
    <div class="fp1">
      <div class="fr"><span class="fl1">Bill Total</span>
        <input id="fBillTotal" class="fv fw84" value=".00" readonly style="font-weight:700;background:#f0fdf4"></div>
      <div class="fr"><span class="fl1">Exchange</span>
        <input id="fExchange" class="fv fw84" value=".00" readonly style="background:#fef9e7;color:#b45309;font-weight:600"></div>
      <div class="fr"><span class="fl1">S.Return</span>
        <input id="fSretamt" class="fv fw84" value=".00" readonly style="background:#f1f5f9"></div>
      <div class="fr"><span class="fl1">Tax</span>
        <input id="fTax" class="fv fw84" value=".00" oninput="triggerRecalc()"></div>
      <div class="fr"><span class="fl1">Net Total</span>
        <input id="fNetTotal" class="fv fw94" value=".00" readonly style="background:#dcfce7;font-weight:700;color:#14532d"></div>
    </div>

    <div class="fp2">
      <div class="fr"><span class="fl2">Advance</span>
        <input id="fAdvance" class="fv fw84" value=".00" oninput="triggerRecalc()"></div>
      <div class="fr"><span class="fl2">G.Advance</span>
        <input id="fGadvance" class="fv fw84" value=".00" readonly style="background:#f1f5f9"></div>
      <div class="fr"><span class="fl2">Schm Less</span>
        <input id="fSchemeAmt" class="fv fw84" value=".00" oninput="triggerRecalc()">
        <select id="fSchemeLedger" class="fv" style="width:72px">
          <option value="APP">APP</option>
          <option value="SCHMAMT">GP</option>
        </select></div>
      <div class="fr"><span class="fl2">Refund</span>
        <input id="fRefund" class="fv fw84" value=".00" oninput="triggerRecalc()"></div>
      <div class="fr"><span class="fl2">Balance</span>
        <input id="fBalance" class="fv fw94" value=".00" readonly style="background:#fef3c7;font-weight:700"></div>
      <div class="fr"><span class="fl2">Net Bal</span>
        <input id="fNetBal" class="fv fw94" value=".00" readonly style="background:#f1f5f9"></div>
    </div>

    <div class="fp3">
      <div class="fr"><span class="fl">Cash/Bank</span>
        <select id="fCashBank" class="fv" style="width:84px">
          <option value="CASH">CASH</option>
          @foreach($cashBanks as $b)<option value="{{ $b['code'] }}">{{ $b['name'] }}</option>@endforeach
        </select>
      </div>
      <div class="fr"><span class="fl">Chq Bank</span>
        <select id="fChqBank" class="fv" style="width:84px">
          <option value=""></option>
          @foreach($cashBanks as $b)<option value="{{ $b['code'] }}">{{ $b['name'] }}</option>@endforeach
        </select>
        <span class="fl">Chq Amt</span>
        <input id="fChqAmt" class="fv fw84" value=".00">
      </div>
      <div class="fr"><span class="fl">Chq No</span>
        <input id="fChqNo" class="fv fw84" value="">
        <span class="fl">Check Date</span>
        <input id="fChqDate" class="fv" type="date" style="width:140px" value="">
        <span class="fchk" style="margin-left:3px"><input type="checkbox" id="fPdc"><span class="flbl">PDC</span></span>
      </div>
      <div class="fr"><span class="fl">Due Date</span>
        <input id="fDueDate" class="fv" type="date" style="width:140px" value="">
      </div>
    </div>

    <div class="foot-btns">
      <button class="fbtn" onclick="toggleExch()">Exchange</button>
      <button class="fbtn" onclick="setSalesReturn()">Sales Return</button>
      <button class="fbtn" onclick="setGoldAdvance()">Gold Advance</button>
      <button class="fbtn fb-save" onclick="saveBill()" id="btnSave" title="F9">Save <small style="opacity:.6;font-size:9px">F9</small></button>
      @if($mode === 'bill')
      <button class="fbtn" onclick="newBill()" title="F8">New <small style="opacity:.6;font-size:9px">F8</small></button>
      @endif
      <button class="fbtn" onclick="prevBill()" title="F7">&#9664; Prev <small style="opacity:.6;font-size:9px">F7</small></button>
      <button class="fbtn" onclick="nextBill()" title="F6">Next &#9654; <small style="opacity:.6;font-size:9px">F6</small></button>
      @if($mode === 'cancel')
      <button class="fbtn" onclick="openCancelModal()" style="background:#fee2e2;color:#c53030;border-color:#fca5a5">Cancel</button>
      @endif
      @if($mode === 'reprint')
      <button class="fbtn" onclick="window.print()">Print</button>
      @endif
      <button class="fbtn" onclick="doExit()">Exit</button>
    </div>
  </div>

</div>

<div class="modal-overlay" id="saveOkModal">
  <div class="modal-box" style="min-width:450px;max-width:450px">
    <div class="modal-head">
      <span>{{ parse_url(url('/'), PHP_URL_HOST) }} says</span>
    </div>
    <div class="ok-modal-text" id="saveOkText">Order saved</div>
    <div class="ok-modal-footer">
      <button type="button" class="ok-modal-btn" id="saveOkBtn">OK</button>
    </div>
  </div>
</div>

<!-- Exchange modal -->
<div class="modal-overlay" id="exchModal">
  <div class="exchange-window">
    <div class="exchange-title">
      <span>Enter Exchange Details</span>
      <button class="close-btn" onclick="cancelExchModal()">&#10005;</button>
    </div>
    <div class="exchange-body">
      <div style="max-height:260px;overflow-y:auto;border:1px solid #d69e2e;border-radius:4px">
        <table class="exchange-table" id="etbl">
          <thead><tr>
            <th style="width:70px">Item Code</th><th style="width:140px">Item Name</th>
            <th style="width:62px">Rate</th><th style="width:38px">Qty</th><th style="width:72px">Weight</th>
            <th style="width:52px">Mud</th><th style="width:52px">Stone</th><th style="width:60px">Touch %</th>
            <th style="width:46px">Less%</th><th style="width:64px">Less Wgt</th><th style="width:64px">Extra Wgt</th>
            <th style="width:70px">Rate2</th><th style="width:70px">St.Price</th>
            <th style="width:64px">Net Wgt</th><th style="width:80px">Amount</th>
          </tr></thead>
          <tbody id="echbody"></tbody>
        </table>
      </div>
    </div>
    <div class="exchange-footer-bar">
      <button onclick="addExchRow()">Add</button>
      <button onclick="delExchRow()">Delete</button>
      <span class="count" id="exCount">0</span>
      <span class="s-badge">S</span>
      <span style="flex:1"></span>
      <span>Total :</span>
      <span id="exFtAmt">0.00</span>
    </div>
    <div class="exchange-totals">
      <div class="group">
        <span>Bill Amt : <span class="val" id="exBillAmt">0.00</span></span>
        <span>Ex.Amt : <span class="val" id="eftAmt">0.00</span></span>
        <span>Net : <span class="val" id="exNetAmt">0.00</span></span>
      </div>
      <button onclick="recalcExchRows()" style="padding:3px 16px;font-size:11px;border:1px solid #d69e2e;border-radius:3px;cursor:pointer;background:#fff;color:#744210;font-weight:600;">Recalc</button>
    </div>
    <div class="exchange-bottom">
      <div class="info-group">Sale Wgt : <span id="exSaleWgt">0.000</span></div>
      <div class="exchange-btn-group">
        <button class="save" onclick="saveExchModal()">Save</button>
        <button class="cancel" onclick="cancelExchModal()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Sales Return modal -->
<div class="modal-overlay" id="sretModal">
  <div class="salesreturn-window">
    <div class="sr-title">
      <span>Enter Sales Returns</span>
      <button class="close-btn" onclick="cancelSalesReturnModal()">&#10005;</button>
    </div>
    <div class="sr-body">
      <div style="max-height:250px;overflow-y:auto;border:1px solid #d69e2e;border-radius:4px">
        <table class="sr-table" id="sretTbl">
          <thead>
            <tr>
              <th style="width:70px">Item Code</th><th style="width:130px">Item Name</th><th style="width:80px">Model</th>
              <th style="width:44px">Qty</th><th style="width:72px">Weight</th><th style="width:68px">Stone Wgt</th>
              <th style="width:72px">Net Wgt</th><th style="width:68px">Stone Price</th><th style="width:62px">Wastage</th><th style="width:52px">MC%</th>
              <th style="width:72px">Making Chg</th><th style="width:62px">Rate</th><th style="width:82px">Amount</th>
            </tr>
          </thead>
          <tbody id="sretBody"></tbody>
        </table>
      </div>
    </div>
    <div class="sr-footer-bar">
      <button onclick="addSalesReturnRow()">Add</button>
      <button onclick="delSalesReturnRow()">Delete</button>
      <span class="count" id="srCount">0</span>
      <span style="flex:1"></span>
      <span>Total :</span>
      <span id="srtTotalAmt">0.00</span>
    </div>
    <div class="sr-eva-bar">
      <span>EVA :</span><span style="flex:1"></span><span>TVA :</span><span id="srtTva">0.00</span>
    </div>
    <div class="sr-bottom">
      <div class="field-group"><label>Discount :</label><input id="srtDiscount" value="0.00" oninput="updateSalesReturnFoot('discount')"></div>
      <div class="field-group"><label>Tax% :</label><input id="srtTaxPerc" value="0.00" oninput="updateSalesReturnFoot('taxPerc')"></div>
      <div class="field-group"><label>Tax :</label><input id="srtTax" value="0.00" oninput="updateSalesReturnFoot('taxAmt')"></div>
      <div class="field-group"><label>Cess% :</label><input id="srtCessPerc" value="0.00" oninput="updateSalesReturnFoot('cessPerc')"></div>
      <div class="field-group"><label>Cess :</label><input id="srtCess" value="0.00" oninput="updateSalesReturnFoot('cessAmt')"></div>
      <div class="field-group"><label>Bill Amt :</label><input id="srtBillAmt" class="wide" value="0.00" readonly style="background:#fefce8"></div>
      <div class="field-group"><label>Ret.Amt :</label><input id="srtRetAmt" class="wide" value="0.00" readonly style="background:#2c5282;color:#fff;font-weight:700"></div>
      <div class="field-group"><label>Net :</label><input id="srtNetAmt" class="wide" value="0.00" readonly style="background:#fefce8"></div>
      <div class="sr-btn-group">
        <button class="save" onclick="saveSalesReturnModal()">Save</button>
        <button class="cancel" onclick="cancelSalesReturnModal()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Gold Advance modal -->
<div class="modal-overlay" id="gaModal">
  <div class="exchange-window">
    <div class="exchange-title">
      <span>Gold Advance</span>
      <button class="close-btn" onclick="cancelGoldAdvance()">&#10005;</button>
    </div>
    <div class="exchange-body">
      <div style="max-height:260px;overflow-y:auto;border:1px solid #d69e2e;border-radius:4px">
        <table class="exchange-table" id="gaTbl">
          <thead><tr>
            <th style="width:78px">Item Code</th><th style="width:160px">Item Name</th><th style="width:50px">Qty</th>
            <th style="width:80px">Weight</th><th style="width:80px">Stone Wgt</th><th style="width:60px">Less%</th>
            <th style="width:80px">Less Wgt</th><th style="width:80px">Net Wgt</th><th style="width:78px">Cost</th><th style="width:88px">StkType</th>
          </tr></thead>
          <tbody id="gaBody"></tbody>
        </table>
      </div>
    </div>
    <div class="exchange-footer-bar">
      <button onclick="addGaRow()">Add</button>
      <button onclick="delGaRow()">Delete</button>
      <span class="count" id="gaCount">0</span>
      <span style="flex:1"></span>
      <span>Gold Adv Wgt :</span>
      <span id="gaTotalWgt">0.000</span>
    </div>
    <div class="exchange-bottom">
      <div class="info-group">Stock Type : <span id="gaStkType">-</span></div>
      <div class="exchange-btn-group">
        <button class="save" onclick="saveGoldAdvanceModal()">Save</button>
        <button class="cancel" onclick="cancelGoldAdvance()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Customer search modal -->
<div class="modal-overlay" id="custModal">
  <div class="modal-box" style="min-width:460px">
    <div class="modal-head"><span>Customer Search</span><button class="cls" onclick="closeCustModal()">&#10006;</button></div>
    <div class="modal-body">
      <input id="custModalQ" style="width:100%;height:26px;font-size:12px;border:1px solid #c6e2d6;border-radius:7px;padding:2px 8px"
        placeholder="Search by code or name..." oninput="custModalSearch(this.value)" autocomplete="off">
      <div style="margin-top:8px;max-height:220px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px">
        <table class="srch-tbl"><thead><tr><th>Code</th><th>Name</th><th>Mobile</th></tr></thead>
        <tbody id="custModalBody"></tbody></table>
      </div>
    </div>
    <div class="modal-footer"><button class="mbtn" onclick="closeCustModal()">Close</button></div>
  </div>
</div>

<!-- Bill search modal -->
<div class="modal-overlay" id="billModal">
  <div class="modal-box" style="min-width:520px">
    <div class="modal-head"><span>Search Order</span><button class="cls" onclick="closeBillSearch()">&#10006;</button></div>
    <div class="modal-body">
      <input id="billSearchQ" style="width:100%;height:26px;font-size:12px;border:1px solid #c6e2d6;border-radius:7px;padding:2px 8px"
        placeholder="Enter order no or customer name..." oninput="doBillSearch(this.value)" autocomplete="off">
      <div style="margin-top:8px;max-height:240px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px">
        <table class="srch-tbl"><thead><tr><th>Order No</th><th>Date</th><th>Customer</th><th style="text-align:right">Bill Total</th></tr></thead>
        <tbody id="billSrchBody"></tbody></table>
      </div>
    </div>
    <div class="modal-footer"><button class="mbtn" onclick="closeBillSearch()">Close</button></div>
  </div>
</div>

<!-- Item Search modal -->
<div class="modal-overlay" id="itemSearchModal">
  <div class="modal-box" style="min-width:720px;max-width:900px;height:520px;display:flex;flex-direction:column">
    <div class="modal-head" style="background:linear-gradient(135deg,#14532d,#15803d)">
      <span>Item Search</span><button class="cls" onclick="closeItemSearch()">&#10006;</button>
    </div>
    <div style="padding:8px 12px;display:flex;gap:8px;align-items:center;border-bottom:1px solid #e2e8f0;flex-shrink:0">
      <input id="itemSrchQ" placeholder="Search by code or name..." autocomplete="off"
        style="flex:1;height:30px;font-size:12px;border:1px solid #c6e2d6;border-radius:7px;padding:2px 10px"
        oninput="itemSrchFilter(this.value)" onkeydown="itemSrchKey(event)">
      <span style="font-size:11px;color:#6b7280" id="itemSrchCount"></span>
    </div>
    <div style="flex:1;overflow:auto">
      <table style="width:100%;border-collapse:collapse;font-size:11px">
        <thead><tr style="background:#f0fdf4;position:sticky;top:0;z-index:1">
          <th style="padding:5px 8px;text-align:left;border-bottom:2px solid #86efac">Code</th>
          <th style="padding:5px 8px;text-align:left;border-bottom:2px solid #86efac">Name</th>
          <th style="padding:5px 8px;text-align:left;border-bottom:2px solid #86efac">Purity</th>
          <th style="padding:5px 8px;text-align:center;border-bottom:2px solid #86efac">Type</th>
          <th style="padding:5px 8px;text-align:right;border-bottom:2px solid #86efac">Qty</th>
          <th style="padding:5px 8px;text-align:right;border-bottom:2px solid #86efac">Weight</th>
        </tr></thead>
        <tbody id="itemSrchBody"></tbody>
      </table>
    </div>
    <div class="modal-footer" style="font-size:11px;color:#6b7280">Double-click or Enter to select | Esc to close</div>
  </div>
</div>

<!-- Cancel modal -->
<div class="modal-overlay" id="cancelModal">
  <div class="modal-box" style="min-width:360px">
    <div class="modal-head"><span>Cancel Order</span>
      <button class="cls" onclick="document.getElementById('cancelModal').classList.remove('active')">&#10006;</button></div>
    <div class="modal-body">
      <p style="margin-bottom:8px;font-size:12px">Cancel order: <strong id="cancelBillNo"></strong></p>
      <input id="cancelReason" style="width:100%;height:26px;border:1px solid #c6e2d6;border-radius:7px;padding:2px 8px;font-size:12px" placeholder="Reason for cancellation">
    </div>
    <div class="modal-footer">
      <button class="mbtn" onclick="confirmCancel()" style="background:#fee2e2;color:#c53030;border-color:#fca5a5">Confirm Cancel</button>
      <button class="mbtn" onclick="document.getElementById('cancelModal').classList.remove('active')">Close</button>
    </div>
  </div>
</div>

<script>
let items=[], exchItems=[], salesRetItems=[], gaItems=[], modelItems=[], currentSlno=0, selRowIdx=-1, exchSelIdx=-1, sretSelIdx=-1, gaSelIdx=-1, recalcTimer=null;
let exchSnapshot='', salesRetSnapshot='', gaSnapshot='', salesRetAmtSnapshot=0, salesRetCfgSnapshot={discount:0,taxPerc:0,tax:0,cessPerc:0,cess:0,tva:0};
const $=id=>document.getElementById(id);
const gv=id=>($(id)?($(id).value??''):'');
const sv=(id,v)=>{const el=$(id);if(!el)return;if(el.tagName==='INPUT'||el.tagName==='SELECT'||el.tagName==='TEXTAREA')el.value=v;else el.textContent=v;};
const gf=(id,d=0)=>parseFloat(gv(id))||d;
const r2=n=>Math.round(n*100)/100;
const r3=n=>Math.round(n*1000)/1000;
const fmt2=n=>parseFloat(n||0).toFixed(2);
const fmt3=n=>parseFloat(n||0).toFixed(3);
const csrf=()=>document.querySelector('meta[name="csrf-token"]').content;
const url=p=>OB_CONFIG.siteUrl+p;

function newItemRow(){return{code:'',name:'',model:'',rate:OB_CONFIG.rates.gold||0,qty:0,weight:0,stwgt:0,stprice:0,mcharge:0,wastage:0,amount:0,narration:'',purity:'',smith:'',stage:1,cost:0};}
function calcRow(i){
  const r=items[i];
  // amount = (weight + wastage) * rate + stprice + mcharge
  r.amount=r2((r.weight+r.wastage)*r.rate+r.stprice+r.mcharge);
  if(r.amount<0)r.amount=0;
}
function renderItems(){
  const tb=$('itbody');tb.innerHTML='';
  items.forEach((r,i)=>{
    const tr=document.createElement('tr');
    if(i===selRowIdx)tr.classList.add('sel');
    tr.onclick=()=>{selRowIdx=i;document.querySelectorAll('#itbody tr').forEach((t,j)=>t.classList.toggle('sel',j===i));};
    tr.innerHTML=`
      <td style="display:flex;align-items:center;gap:2px;padding:1px 2px">
        <input class="num" value="${esc(r.code)}" style="flex:1;min-width:0"
          oninput="items[${i}].code=this.value"
          onblur="if(this.value.trim())itemLookup(${i})"
          onkeydown="if(event.key==='F2'){event.preventDefault();openItemSearch(${i});}else rKey(event,${i},0)">
        <button tabindex="-1" title="Search Item (F2)" onclick="openItemSearch(${i})"
          style="width:18px;height:22px;padding:0;border:none;background:transparent;cursor:pointer;color:#22c55e;font-size:13px;line-height:1;flex-shrink:0">&#128269;</button>
      </td>
      <td><input value="${esc(r.name)}" oninput="items[${i}].name=this.value" onkeydown="rKey(event,${i},1)"></td>
      <td><input value="${esc(r.model||'')}" oninput="items[${i}].model=this.value" onkeydown="rKey(event,${i},2)"></td>
      <td><input class="num" value="${fmt2(r.rate)}" oninput="items[${i}].rate=+this.value||0;calcRow(${i});rfRow(${i});updateFoot()" onkeydown="rKey(event,${i},3)"></td>
      <td><input class="num" value="${r.qty}" oninput="items[${i}].qty=+this.value||0;calcRow(${i});rfRow(${i});updateFoot()" onkeydown="rKey(event,${i},4)"></td>
      <td><input class="num" value="${fmt3(r.weight)}" oninput="items[${i}].weight=+this.value||0;calcRow(${i});rfRow(${i});updateFoot()" onkeydown="rKey(event,${i},5)"></td>
      <td><input class="num" value="${fmt3(r.stwgt)}" oninput="items[${i}].stwgt=+this.value||0;calcRow(${i});rfRow(${i});updateFoot()" onkeydown="rKey(event,${i},6)"></td>
      <td><input class="num" value="${fmt2(r.stprice)}" oninput="items[${i}].stprice=+this.value||0;calcRow(${i});rfRow(${i});updateFoot()" onkeydown="rKey(event,${i},7)"></td>
      <td><input class="num" value="${fmt2(r.mcharge)}" oninput="items[${i}].mcharge=+this.value||0;calcRow(${i});rfRow(${i});updateFoot()" onkeydown="rKey(event,${i},8)"></td>
      <td><input class="num" value="${fmt3(r.wastage)}" oninput="items[${i}].wastage=+this.value||0;calcRow(${i});rfRow(${i});updateFoot()" onkeydown="rKey(event,${i},9)"></td>
      <td><input class="num" value="${fmt2(r.amount)}" readonly style="background:transparent;font-weight:600"></td>`;
    tb.appendChild(tr);
  });
  sv('cntItems',items.length);
}
function esc(s){const d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
function rfRow(i){const r=items[i],tr=$('itbody').rows[i];if(!tr)return;tr.cells[10].querySelector('input').value=fmt2(r.amount);}
function rKey(e,i,col){
  if((e.key==='Tab'&&!e.shiftKey) || e.key==='Enter'){
    e.preventDefault();
    const editCols=[0,1,2,3,4,5,6,7,8,9]; // 10=amount(readonly), skip it
    const cur=editCols.indexOf(col),next=editCols[cur+1];
    if(next!==undefined){$('itbody').rows[i].cells[next].querySelector('input').focus();return;}
    if(i<items.length-1)$('itbody').rows[i+1].cells[0].querySelector('input').focus();
    else addRow();
  }
}
function srKey(e,i,col){
  if((e.key==='Tab'&&!e.shiftKey) || e.key==='Enter'){
    e.preventDefault();
    // PB-like shortcut: from MC% jump directly to next row
    if(col===9){
      if(i<salesRetItems.length-1){
        const ntr=$('sretBody').rows[i+1];
        if(ntr&&ntr.cells[0]) ntr.cells[0].querySelector('input').focus();
        return;
      }
      addSalesReturnRow();
      return;
    }
    const editCols=[0,1,2,3,4,5,7,8,9,10,11,12];
    const cur=editCols.indexOf(col),next=editCols[cur+1];
    if(next!==undefined){
      const tr=$('sretBody').rows[i];
      if(tr&&tr.cells[next]) tr.cells[next].querySelector('input').focus();
      return;
    }
    if(i<salesRetItems.length-1){
      const ntr=$('sretBody').rows[i+1];
      if(ntr&&ntr.cells[0]) ntr.cells[0].querySelector('input').focus();
    }else{
      addSalesReturnRow();
    }
  }
}
function updateFoot(){
  let qty=0,wgt=0,stwgt=0,stprice=0,mc=0,wastage=0,amt=0;
  items.forEach(r=>{if(!r.code)return;qty+=r.qty||0;wgt+=r.weight;stwgt+=r.stwgt;stprice+=r.stprice;mc+=r.mcharge;wastage+=r.wastage;amt+=r.amount;});
  sv('ftQty',qty);sv('ftWgt',fmt3(wgt));sv('ftStwgt',fmt3(stwgt));sv('ftStprice',fmt2(stprice));
  sv('ftMc',fmt2(mc));sv('ftWastage',fmt3(wastage));sv('ftAmt',fmt2(amt));
  sv('fBillTotal',fmt2(amt));triggerRecalc();
}
function addRow(){items.push(newItemRow());selRowIdx=items.length-1;renderItems();const tr=$('itbody').rows[items.length-1];if(tr)tr.cells[0].querySelector('input').focus();}
function delRow(){if(!items.length)return;const idx=selRowIdx>=0?selRowIdx:items.length-1;items.splice(idx,1);selRowIdx=Math.min(idx,items.length-1);renderItems();updateFoot();}

// Exchange
function swVal(k,d=''){return String((OB_CONFIG.software||{})[k] ?? d);}
function swFlag(k,d='N'){return swVal(k,d).toUpperCase()==='Y';}
function roundAmt(n){
  const v=+n||0;
  if(swFlag('RoundOffAllAmt','N')) return Math.round(v);
  const dec=parseInt(swVal('RoundDec','2'),10);
  const p=Math.pow(10,isNaN(dec)?2:dec);
  return Math.round(v*p)/p;
}
function saleNetWeight(){
  let w=0;
  items.forEach(r=>{if(!String(r.code||'').trim())return;w+=(+r.weight||0)-(+r.stwgt||0);});
  return Math.max(r3(w),0);
}
function newExchRow(){return{code:'OG',name:'OLD GOLD',qty:0,weight:0,mud:0,stone:0,touch:0,lessperc:0,lesswgt:0,extrawgt:0,rate:OB_CONFIG.rates.gold||0,rate2:0,stprice:0,netwgt:0,amount:0,stktype:'',iqtype:'',stktouch:100,stkinnos:'N',ornament:'N',cost:0,itemtype:'G',stkfd:'',batch:''};}
function findExchCatalog(code){
  const c=String(code||'').trim().toUpperCase();
  if(!c) return null;
  const rows=Array.isArray(OB_CONFIG.exchItems)?OB_CONFIG.exchItems:[];
  return rows.find(r=>String(r.code||'').trim().toUpperCase()===c)||null;
}
function calcExchRow(i){
  const r=exchItems[i];
  const w=+r.weight||0,mud=+r.mud||0,stw=+r.stone||0,tch=+r.touch||0,lp=+r.lessperc||0,extra=+r.extrawgt||0;
  const baseNet=r3(w-mud-stw);
  const useTouch=swFlag('UseTouchToLessWgtCalculationInPurchase','Y');
  const convTouch=+swVal('PurchConvTouch','0')||0;
  if(useTouch && Math.abs(tch)>0){
    r.lesswgt=r3(baseNet-((baseNet*tch)/(convTouch>0?convTouch:100)));
  }else if(Math.abs(lp)>0){
    r.lesswgt=r3((baseNet*lp)/100);
  }
  r.netwgt=r3(Math.max(w-mud-r.lesswgt-stw+extra,0));
  const saleWgt=saleNetWeight();
  const rate=+r.rate||0,rate2=+r.rate2||0,qty=+r.qty||0,stprice=+r.stprice||0;
  let amt=0;
  if(String(r.stkinnos||'N').toUpperCase()==='Y'){
    amt=qty*rate+stprice;
  }else if(r.netwgt>saleWgt && rate2>0){
    amt=(saleWgt*rate)+((r.netwgt-saleWgt)*rate2)+stprice;
  }else{
    amt=(r.netwgt*rate)+stprice;
  }
  r.amount=roundAmt(Math.max(amt,0));
}
function renderExchItems(){
  const tb=$('echbody');tb.innerHTML='';
  exchItems.forEach((r,i)=>{
    const tr=document.createElement('tr');if(i===exchSelIdx)tr.className='sel';tr.onclick=()=>{exchSelIdx=i;};
    tr.innerHTML=`
      <td><input value="${esc(r.code)}" oninput="exchItems[${i}].code=this.value" onblur="exchItemLookup(${i})"></td>
      <td><input value="${esc(r.name)}" oninput="exchItems[${i}].name=this.value"></td>
      <td><input class="num" value="${fmt2(r.rate)}" oninput="exchItems[${i}].rate=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${r.qty}" oninput="exchItems[${i}].qty=+this.value||0"></td>
      <td><input class="num" value="${fmt3(r.weight)}" oninput="exchItems[${i}].weight=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt3(r.mud||0)}" oninput="exchItems[${i}].mud=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt3(r.stone||0)}" oninput="exchItems[${i}].stone=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt2(r.touch||0)}" oninput="exchItems[${i}].touch=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt2(r.lessperc)}" oninput="exchItems[${i}].lessperc=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt3(r.lesswgt)}" oninput="exchItems[${i}].lesswgt=+this.value||0;exchItems[${i}].lessperc=0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt3(r.extrawgt||0)}" oninput="exchItems[${i}].extrawgt=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt2(r.rate2||0)}" oninput="exchItems[${i}].rate2=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt2(r.stprice)}" oninput="exchItems[${i}].stprice=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt3(r.netwgt||0)}" readonly style="background:transparent;color:#6b7280"></td>
      <td><input class="num" value="${fmt2(r.amount)}" readonly style="background:transparent;font-weight:600" onkeydown="srKey(event,${i},12)"></td>`;
    tb.appendChild(tr);
  });
}
function rfExchRow(i){
  const r=exchItems[i],tr=$('echbody').rows[i];if(!tr)return;
  tr.cells[9].querySelector('input').value=fmt3(r.lesswgt);
  tr.cells[13].querySelector('input').value=fmt3(r.netwgt||0);
  tr.cells[14].querySelector('input').value=fmt2(r.amount);
}
function updateExchFoot(){
  let a=0;exchItems.forEach(r=>{a+=r.amount||0;});
  sv('eftAmt',fmt2(a));
  sv('exFtAmt',fmt2(a));
  sv('exCount',String(exchItems.length));
  sv('exBillAmt',fmt2(gf('fBillTotal')));
  sv('exNetAmt',fmt2(gf('fBillTotal')-a));
  sv('exSaleWgt',fmt3(saleNetWeight()));
}
function addExchRow(){exchItems.push(newExchRow());exchSelIdx=exchItems.length-1;renderExchItems();const tr=$('echbody').rows[exchItems.length-1];if(tr)tr.cells[0].querySelector('input').focus();}
function delExchRow(){if(!exchItems.length)return;const idx=exchSelIdx>=0?exchSelIdx:exchItems.length-1;exchItems.splice(idx,1);exchSelIdx=Math.min(idx,exchItems.length-1);renderExchItems();updateExchFoot();}
function toggleExch(){
  exchSnapshot=JSON.stringify(exchItems||[]);
  if(!exchItems.length)addExchRow();
  $('exchModal').classList.add('active');
  updateExchFoot();
}
function recalcExchRows(){exchItems.forEach((_,i)=>calcExchRow(i));renderExchItems();updateExchFoot();}
function saveExchModal(){
  const strictOgLess=swFlag('OGLessStrictly','N');
  const strictStkType=swFlag('StktypeStrict','N');
  const filtered=[];
  for(let i=0;i<exchItems.length;i++){
    const r=exchItems[i];
    const code=String(r.code||'').trim().toUpperCase();
    const w=+r.weight||0,qty=+r.qty||0,lessw=+r.lesswgt||0;
    const stkinnos=String(r.stkinnos||'N').toUpperCase();
    if(!code || (w===0 && qty===0)) continue;
    if(w<=0 && stkinnos!=='Y'){alert(`Check Weight (${code}). You can't save.`);return;}
    if(code==='OG' && strictOgLess && lessw<=0){alert(`Check Less (${code}). You can't save.`);return;}
    if(strictStkType && !String(r.stktype||'').trim()){alert(`Check Stock Type (${code}). You can't save.`);return;}
    filtered.push(r);
  }
  exchItems=filtered;
  renderExchItems();
  let a=0;exchItems.forEach(r=>{a+=r.amount||0;});
  sv('fExchAmt',a.toFixed(2));
  sv('fExchange',fmt2(a));
  $('exchModal').classList.remove('active');
  triggerRecalc();
}
function cancelExchModal(){
  exchItems=JSON.parse(exchSnapshot||'[]');
  exchSelIdx=-1;
  renderExchItems();
  $('exchModal').classList.remove('active');
  updateExchFoot();
}
function setSalesReturn(){
  salesRetSnapshot=JSON.stringify(salesRetItems||[]);
  salesRetAmtSnapshot=gf('fSretamt');
  salesRetCfgSnapshot={
    discount:gf('srtDiscount'),
    taxPerc:gf('srtTaxPerc'),
    tax:gf('srtTax'),
    cessPerc:gf('srtCessPerc'),
    cess:gf('srtCess'),
    tva:(parseFloat(($('srtTva')?.textContent||'0'))||0)
  };
  if(salesRetItems.length){
    const r0=salesRetItems[0]||{};
    if(gf('srtDiscount')===0 && (+r0.wgtamt||0)>0) sv('srtDiscount',fmt2(+r0.wgtamt||0));
    if(gf('srtTaxPerc')===0 && (+r0.taxperc||0)>0) sv('srtTaxPerc',fmt2(+r0.taxperc||0));
    if(gf('srtTax')===0 && (+r0.taxamt||0)>0) sv('srtTax',fmt2(+r0.taxamt||0));
    if(gf('srtCess')===0 && (+r0.ast||0)>0) sv('srtCess',fmt2(+r0.ast||0));
    const total=salesRetItems.reduce((a,r)=>a+(+r.amount||0),0);
    const base=Math.max(total-gf('srtDiscount'),0);
    if(gf('srtTaxPerc')===0 && gf('srtTax')>0 && base>0){
      sv('srtTaxPerc',fmt2(r2((gf('srtTax')*100)/base)));
    }
    if(gf('srtCessPerc')===0 && gf('srtCess')>0){
      const cessBase=swFlag('CessIsBasedOnBillAmt','Y')?base:(base+gf('srtTax'));
      if(cessBase>0) sv('srtCessPerc',fmt2(r2((gf('srtCess')*100)/cessBase)));
    }
  }
  if(!salesRetItems.length)addSalesReturnRow();
  $('sretModal').classList.add('active');
  updateSalesReturnFoot('discount');
}
function setGoldAdvance(){
  gaSnapshot=JSON.stringify(gaItems||[]);
  if(!gaItems.length)addGaRow();
  $('gaModal').classList.add('active');
  updateGaFoot();
}
function newGaRow(){return{code:'',name:'',qty:0,weight:0,stonewgt:0,lessperc:0,lesswgt:0,netwgt:0,itemtype:'G',cost:0,stktype:'',iqtype:'',stktouch:0};}
function calcGaRow(i){
  const r=gaItems[i];
  const w=+r.weight||0,stw=+r.stonewgt||0,lp=+r.lessperc||0;
  if(lp>0){
    r.lesswgt=r3(((w-stw)*lp)/100);
  }
  r.netwgt=r3(Math.max(w-stw-(+r.lesswgt||0),0));
}
function renderGaItems(){
  const tb=$('gaBody');tb.innerHTML='';
  gaItems.forEach((r,i)=>{
    const tr=document.createElement('tr');if(i===gaSelIdx)tr.className='sel';tr.onclick=()=>{gaSelIdx=i;sv('gaStkType',r.stktype||'-');};
    tr.innerHTML=`
      <td><input value="${esc(r.code)}" oninput="gaItems[${i}].code=this.value" onblur="gaItemLookup(${i})" onkeydown="gaKey(event,${i},0)"></td>
      <td><input value="${esc(r.name)}" oninput="gaItems[${i}].name=this.value" onkeydown="gaKey(event,${i},1)"></td>
      <td><input class="num" value="${r.qty}" oninput="gaItems[${i}].qty=+this.value||0" onkeydown="gaKey(event,${i},2)"></td>
      <td><input class="num" value="${fmt3(r.weight)}" oninput="gaItems[${i}].weight=+this.value||0;calcGaRow(${i});rfGaRow(${i});updateGaFoot()" onkeydown="gaKey(event,${i},3)"></td>
      <td><input class="num" value="${fmt3(r.stonewgt)}" oninput="gaItems[${i}].stonewgt=+this.value||0;calcGaRow(${i});rfGaRow(${i});updateGaFoot()" onkeydown="gaKey(event,${i},4)"></td>
      <td><input class="num" value="${fmt2(r.lessperc)}" oninput="gaItems[${i}].lessperc=+this.value||0;calcGaRow(${i});rfGaRow(${i});updateGaFoot()" onkeydown="gaKey(event,${i},5)"></td>
      <td><input class="num" value="${fmt3(r.lesswgt)}" oninput="gaItems[${i}].lesswgt=+this.value||0;gaItems[${i}].lessperc=0;calcGaRow(${i});rfGaRow(${i});updateGaFoot()" onkeydown="gaKey(event,${i},6)"></td>
      <td><input class="num" value="${fmt3(r.netwgt)}" readonly style="background:transparent;color:#6b7280" onkeydown="gaKey(event,${i},7)"></td>
      <td><input class="num" value="${fmt2(r.cost)}" oninput="gaItems[${i}].cost=+this.value||0" onkeydown="gaKey(event,${i},8)"></td>
      <td><input value="${esc(r.stktype)}" oninput="gaItems[${i}].stktype=this.value" onkeydown="gaKey(event,${i},9)"></td>`;
    tb.appendChild(tr);
  });
}
function rfGaRow(i){
  const r=gaItems[i],tr=$('gaBody').rows[i];if(!tr)return;
  tr.cells[6].querySelector('input').value=fmt3(r.lesswgt||0);
  tr.cells[7].querySelector('input').value=fmt3(r.netwgt||0);
}
function gaKey(e,i,col){
  if((e.key==='Tab'&&!e.shiftKey)||e.key==='Enter'){
    e.preventDefault();
    const cols=[0,1,2,3,4,5,6,8,9];
    const idx=cols.indexOf(col),next=cols[idx+1];
    if(next!==undefined){
      const tr=$('gaBody').rows[i];if(tr&&tr.cells[next])tr.cells[next].querySelector('input').focus();
      return;
    }
    if(i<gaItems.length-1){
      const nr=$('gaBody').rows[i+1];if(nr&&nr.cells[0])nr.cells[0].querySelector('input').focus();
    }else addGaRow();
  }
}
function gaItemLookup(i){
  const code=String(gaItems[i].code||'').trim();if(!code)return;
  fetch(`${url('/api/order-bill/item-lookup')}?code=${encodeURIComponent(code)}&gold_rate=${gf('fGoldRate')}`)
    .then(r=>r.json()).then(d=>{
      if(!d.ok)return;
      gaItems[i].name=d.name||'';
      gaItems[i].itemtype=d.itype||'G';
      gaItems[i].cost=+d.cost||0;
      gaItems[i].stktype=d.defstktype||'';
      gaItems[i].iqtype=d.defquality||'';
      const tr=$('gaBody').rows[i];
      if(tr){
        tr.cells[1].querySelector('input').value=gaItems[i].name;
        tr.cells[8].querySelector('input').value=fmt2(gaItems[i].cost);
        tr.cells[9].querySelector('input').value=gaItems[i].stktype;
      }
      sv('gaStkType',gaItems[i].stktype||'-');
    }).catch(()=>{});
}
function updateGaFoot(){
  let tw=0,cnt=0;
  gaItems.forEach(r=>{
    const code=String(r.code||'').trim();
    if(!code)return;
    cnt++;
    tw+=Math.max((+r.weight||0)-(+r.stonewgt||0)-(+r.lesswgt||0),0);
  });
  sv('gaCount',String(cnt));
  sv('gaTotalWgt',fmt3(tw));
}
function addGaRow(){gaItems.push(newGaRow());gaSelIdx=gaItems.length-1;renderGaItems();const tr=$('gaBody').rows[gaItems.length-1];if(tr)tr.cells[0].querySelector('input').focus();}
function delGaRow(){if(!gaItems.length)return;const idx=gaSelIdx>=0?gaSelIdx:gaItems.length-1;gaItems.splice(idx,1);gaSelIdx=Math.min(idx,gaItems.length-1);renderGaItems();updateGaFoot();}
function saveGoldAdvanceModal(){
  const strictStkType=swFlag('StktypeStrict','N');
  const filtered=[];
  for(let i=0;i<gaItems.length;i++){
    const r=gaItems[i];
    const code=String(r.code||'').trim().toUpperCase();
    if(!code) continue;
    r.code=code;
    if((+r.weight||0)<=0){alert(`Check Weight (${code}). You can't save.`);return;}
    if(strictStkType && !String(r.stktype||'').trim()){alert(`Check Stock Type (${code}). You can't save.`);return;}
    calcGaRow(i);
    filtered.push(r);
  }
  gaItems=filtered;
  renderGaItems();
  updateGaFoot();
  let g=0;gaItems.forEach(r=>{g+=Math.max((+r.weight||0)-(+r.stonewgt||0)-(+r.lesswgt||0),0);});
  sv('fGadvance',fmt3(g));
  $('gaModal').classList.remove('active');
  triggerRecalc();
}
function cancelGoldAdvance(){
  gaItems=JSON.parse(gaSnapshot||'[]');
  gaSelIdx=-1;
  renderGaItems();
  updateGaFoot();
  $('gaModal').classList.remove('active');
}
function exchItemLookup(i){
  const code=exchItems[i].code.trim();if(!code)return;
  const cat=findExchCatalog(code);
  if(cat){
    exchItems[i].name=cat.name||exchItems[i].name||'';
    exchItems[i].touch=Number(cat.touch||0);
    exchItems[i].stktype=cat.defstktype||'';
    exchItems[i].iqtype=cat.defquality||'';
    exchItems[i].stkinnos=cat.stkinnos||'N';
    exchItems[i].ornament=cat.ornament||'N';
    exchItems[i].cost=+cat.cost||0;
    exchItems[i].itemtype=cat.itype||'G';
    if((+exchItems[i].rate||0)<=0 && (+cat.cost||0)>0)exchItems[i].rate=+cat.cost;
  }
  fetch(`${url('/api/order-bill/item-lookup')}?code=${encodeURIComponent(code)}&gold_rate=${exchItems[i].rate||OB_CONFIG.rates.gold}`)
    .then(r=>r.json()).then(d=>{
      if(!d.ok)return;
      exchItems[i].name=d.name||exchItems[i].name||'';
      exchItems[i].touch=+d.touch||exchItems[i].touch||0;
      exchItems[i].stktype=d.defstktype||exchItems[i].stktype||'';
      exchItems[i].iqtype=d.defquality||exchItems[i].iqtype||'';
      exchItems[i].stkinnos=d.stkinnos||exchItems[i].stkinnos||'N';
      exchItems[i].ornament=d.ornament||exchItems[i].ornament||'N';
      exchItems[i].cost=+d.cost||exchItems[i].cost||0;
      exchItems[i].itemtype=d.itype||exchItems[i].itemtype||'G';
      exchItems[i].rate=parseFloat(d.rate)||exchItems[i].rate;
      calcExchRow(i);renderExchItems();updateExchFoot();
    }).catch(()=>{calcExchRow(i);renderExchItems();updateExchFoot();});
}

// Sales Return
function newSalesReturnRow(){return{code:'',name:'',model:'',qty:0,weight:0,stwgt:0,netwgt:0,stprice:0,wastage:0,mcperc:0,mc:0,rate:OB_CONFIG.rates.gold||0,amount:0,itemtype:'G',stktype:'',iqtype:'',stkinnos:'N',stktouch:100,cost:0,dmdamt:0,dmdwgt:0,bcode:0,mperc:0,taxperc:0,taxamt:0,ast:0,wgtamt:0};}
function calcSalesReturnRow(i){
  const r=salesRetItems[i];
  r.netwgt=r3(Math.max((r.weight||0)-(r.stwgt||0),0));
  if((String(r.stkinnos||'N').toUpperCase())==='Y'){
    r.amount=r2(((r.qty||0)*(r.rate||0)) + (r.dmdamt||0) + (r.mc||0) + (r.stprice||0));
  }else{
    r.amount=r2(((r.netwgt||0)*(r.rate||0)) + (r.dmdamt||0) + (r.mc||0) + ((r.wastage||0)*(r.rate||0)) + (r.stprice||0));
  }
  if(r.amount<0)r.amount=0;
}
function calcSalesReturnMcFromPerc(i){
  const r=salesRetItems[i];
  const perc=+r.mcperc||0;
  const rate=+r.rate||0;
  const net=Math.max((+r.weight||0)-(+r.stwgt||0),0);
  const qty=+r.qty||0;
  let mc=0;
  // PB-like percentage mode: MC% on value (qty*rate for nos, else netwgt*rate)
  if((String(r.stkinnos||'N').toUpperCase())==='Y'){
    mc=(qty*rate*perc)/100;
  }else{
    mc=(net*rate*perc)/100;
  }
  r.mc=r2(Math.max(mc,0));
}
function renderSalesReturnItems(){
  const tb=$('sretBody');tb.innerHTML='';
  salesRetItems.forEach((r,i)=>{
    const tr=document.createElement('tr');if(i===sretSelIdx)tr.className='sel';tr.onclick=()=>{sretSelIdx=i;};
    tr.innerHTML=`
      <td><input value="${esc(r.code)}" oninput="salesRetItems[${i}].code=this.value" onblur="salesReturnItemLookup(${i})" onkeydown="srKey(event,${i},0)"></td>
      <td><input value="${esc(r.name)}" oninput="salesRetItems[${i}].name=this.value" onkeydown="srKey(event,${i},1)"></td>
      <td><input value="${esc(r.model)}" oninput="salesRetItems[${i}].model=this.value" onkeydown="srKey(event,${i},2)"></td>
      <td><input class="num" value="${r.qty}" oninput="salesRetItems[${i}].qty=+this.value||0" onkeydown="srKey(event,${i},3)"></td>
      <td><input class="num" value="${fmt3(r.weight)}" oninput="salesRetItems[${i}].weight=+this.value||0;calcSalesReturnRow(${i});rfSalesReturnRow(${i});updateSalesReturnFoot('discount')" onkeydown="srKey(event,${i},4)"></td>
      <td><input class="num" value="${fmt3(r.stwgt)}" oninput="salesRetItems[${i}].stwgt=+this.value||0;calcSalesReturnRow(${i});rfSalesReturnRow(${i});updateSalesReturnFoot('discount')" onkeydown="srKey(event,${i},5)"></td>
      <td><input class="num" value="${fmt3(r.netwgt)}" readonly style="background:transparent;color:#6b7280"></td>
      <td><input class="num" value="${fmt2(r.stprice)}" oninput="salesRetItems[${i}].stprice=+this.value||0;calcSalesReturnRow(${i});rfSalesReturnRow(${i});updateSalesReturnFoot('discount')" onkeydown="srKey(event,${i},7)"></td>
      <td><input class="num" value="${fmt2(r.wastage)}" oninput="salesRetItems[${i}].wastage=+this.value||0;calcSalesReturnRow(${i});rfSalesReturnRow(${i});updateSalesReturnFoot('discount')" onkeydown="srKey(event,${i},8)"></td>
      <td><input class="num" value="${fmt2(r.mcperc||0)}" oninput="salesRetItems[${i}].mcperc=+this.value||0;calcSalesReturnMcFromPerc(${i});calcSalesReturnRow(${i});rfSalesReturnRow(${i});updateSalesReturnFoot('discount')" onkeydown="srKey(event,${i},9)"></td>
      <td><input class="num" value="${fmt2(r.mc)}" oninput="salesRetItems[${i}].mc=+this.value||0;calcSalesReturnRow(${i});rfSalesReturnRow(${i});updateSalesReturnFoot('discount')" onkeydown="srKey(event,${i},10)"></td>
      <td><input class="num" value="${fmt2(r.rate)}" oninput="salesRetItems[${i}].rate=+this.value||0;calcSalesReturnRow(${i});rfSalesReturnRow(${i});updateSalesReturnFoot('discount')" onkeydown="srKey(event,${i},11)"></td>
      <td><input class="num" value="${fmt2(r.amount)}" readonly style="background:transparent;font-weight:600"></td>`;
    tb.appendChild(tr);
  });
}
function rfSalesReturnRow(i){
  const r=salesRetItems[i],tr=$('sretBody').rows[i];if(!tr)return;
  tr.cells[6].querySelector('input').value=fmt3(r.netwgt);
  tr.cells[12].querySelector('input').value=fmt2(r.amount);
}
function updateSalesReturnFoot(mode='manual'){
  let total=0;salesRetItems.forEach(r=>{total+=r.amount||0;});
  const disc=Math.max(gf('srtDiscount'),0);
  const base=Math.max(total-disc,0);
  let taxPerc=Math.max(gf('srtTaxPerc'),0),tax=Math.max(gf('srtTax'),0);
  let cessPerc=Math.max(gf('srtCessPerc'),0),cess=Math.max(gf('srtCess'),0);
  const tva=Math.max((parseFloat(($('srtTva')?.textContent||'0'))||0),0);
  const cessOnBill=swFlag('CessIsBasedOnBillAmt','Y');

  if(mode==='discount' || mode==='taxPerc'){
    tax=r2((base*taxPerc)/100);
    sv('srtTax',fmt2(tax));
  }else if(mode==='taxAmt'){
    tax=r2(tax);
    taxPerc=base>0?r2((tax*100)/base):0;
    sv('srtTaxPerc',fmt2(taxPerc));
  }

  if(mode==='discount' || mode==='taxPerc' || mode==='cessPerc'){
    const cessBase=cessOnBill?base:(base+tax);
    cess=r2((cessBase*cessPerc)/100);
    sv('srtCess',fmt2(cess));
  }else if(mode==='cessAmt'){
    cess=r2(cess);
    const cessBase=cessOnBill?base:(base+tax);
    cessPerc=cessBase>0?r2((cess*100)/cessBase):0;
    sv('srtCessPerc',fmt2(cessPerc));
  }

  const ret=r2(base+tax+cess+tva);
  const net=r2(Math.max(gf('fBillTotal')-ret,0));
  sv('srCount',String(salesRetItems.length));
  sv('srtTotalAmt',fmt2(total));
  sv('srtBillAmt',fmt2(gf('fBillTotal')));
  sv('srtRetAmt',fmt2(ret));
  sv('srtNetAmt',fmt2(net));
}
function addSalesReturnRow(){salesRetItems.push(newSalesReturnRow());sretSelIdx=salesRetItems.length-1;renderSalesReturnItems();const tr=$('sretBody').rows[salesRetItems.length-1];if(tr)tr.cells[0].querySelector('input').focus();}
function delSalesReturnRow(){if(!salesRetItems.length)return;const idx=sretSelIdx>=0?sretSelIdx:salesRetItems.length-1;salesRetItems.splice(idx,1);sretSelIdx=Math.min(idx,salesRetItems.length-1);renderSalesReturnItems();updateSalesReturnFoot('discount');}
function saveSalesReturnModal(){
  const strictStkType=swFlag('StktypeStrict','N');
  const filtered=[];
  for(let i=0;i<salesRetItems.length;i++){
    const r=salesRetItems[i];
    const code=String(r.code||'').trim().toUpperCase();
    const w=+r.weight||0,qty=+r.qty||0;
    const stkinnos=String(r.stkinnos||'N').toUpperCase();
    if(!code || (w===0 && qty===0)) continue;
    if(w<=0 && stkinnos!=='Y'){alert(`Check Weight (${code}). You can't save.`);return;}
    if(strictStkType && !String(r.stktype||'').trim()){alert(`Check Stock Type (${code}). You can't save.`);return;}
    filtered.push(r);
  }
  salesRetItems=filtered;
  const disc=Math.max(gf('srtDiscount'),0),taxPerc=Math.max(gf('srtTaxPerc'),0),tax=Math.max(gf('srtTax'),0),cess=Math.max(gf('srtCess'),0);
  salesRetItems.forEach(r=>{
    r.wgtamt=disc;
    r.taxperc=taxPerc;
    r.taxamt=tax;
    r.ast=cess;
  });
  renderSalesReturnItems();
  updateSalesReturnFoot('discount');
  sv('fSretamt',gv('srtRetAmt'));
  $('sretModal').classList.remove('active');
  triggerRecalc();
}
function cancelSalesReturnModal(){
  salesRetItems=JSON.parse(salesRetSnapshot||'[]');
  sretSelIdx=-1;
  renderSalesReturnItems();
  sv('srtDiscount',fmt2(salesRetCfgSnapshot.discount||0));
  sv('srtTaxPerc',fmt2(salesRetCfgSnapshot.taxPerc||0));
  sv('srtTax',fmt2(salesRetCfgSnapshot.tax||0));
  sv('srtCessPerc',fmt2(salesRetCfgSnapshot.cessPerc||0));
  sv('srtCess',fmt2(salesRetCfgSnapshot.cess||0));
  sv('srtTva',fmt2(salesRetCfgSnapshot.tva||0));
  sv('fSretamt',fmt2(salesRetAmtSnapshot||0));
  $('sretModal').classList.remove('active');
  updateSalesReturnFoot('discount');
  triggerRecalc();
}
function salesReturnItemLookup(i){
  const code=salesRetItems[i].code.trim();if(!code)return;
  fetch(`${url('/api/order-bill/item-lookup')}?code=${encodeURIComponent(code)}&gold_rate=${salesRetItems[i].rate||OB_CONFIG.rates.gold}`)
    .then(r=>r.json()).then(d=>{
      if(!d.ok)return;
      salesRetItems[i].name=d.name||'';
      salesRetItems[i].rate=parseFloat(d.rate)||salesRetItems[i].rate;
      salesRetItems[i].itemtype=d.itype||salesRetItems[i].itemtype||'G';
      salesRetItems[i].stktype=d.defstktype||salesRetItems[i].stktype||'';
      salesRetItems[i].iqtype=d.defquality||salesRetItems[i].iqtype||'';
      salesRetItems[i].stkinnos=d.stkinnos||salesRetItems[i].stkinnos||'N';
      salesRetItems[i].cost=+d.cost||salesRetItems[i].cost||0;
      calcSalesReturnRow(i);
      const tr=$('sretBody').rows[i];
      if(tr){
        const c1=tr.cells[1]?.querySelector('input');
        const c11=tr.cells[11]?.querySelector('input');
        if(c1)c1.value=salesRetItems[i].name||'';
        if(c11)c11.value=fmt2(salesRetItems[i].rate||0);
        rfSalesReturnRow(i);
      }
      updateSalesReturnFoot('discount');
    }).catch(()=>{});
}

// Recalc
function triggerRecalc(){clearTimeout(recalcTimer);recalcTimer=setTimeout(doRecalc,350);}
function doRecalc(){
  const p={bill_total:gf('fBillTotal'),tax:gf('fTax'),advance:gf('fAdvance'),exchange:gf('fExchange'),sretamt:gf('fSretamt'),scheme_amt:gf('fSchemeAmt'),refund:gf('fRefund'),ob:gf('fOB')};
  fetch(url('/api/order-bill/recalc'),{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify(p)})
    .then(r=>r.ok?r.json():null).then(d=>{if(!d||!d.ok)return;sv('fNetTotal',fmt2(d.net_total||0));sv('fBalance',fmt2(d.balance||0));sv('fCB',fmt2(d.cb||0));sv('fNetBal',fmt2(d.net_bal||0));}).catch(()=>{});
}
function onRateChange(){const rate=gf('fGoldRate');sv('fRate8gm',fmt2(rate*8));items.forEach((r,i)=>{if(!r.rate||r.rate===OB_CONFIG.rates.gold){r.rate=rate;calcRow(i);rfRow(i);}});OB_CONFIG.rates.gold=rate;updateFoot();}

// Customer
function loadCustSelect(){
  fetch(url('/api/order-bill/customer-search?q=')).then(r=>r.json()).then(d=>{
    const sel=$('fCustSelect');if(!sel)return;sel.innerHTML='<option value="">-- Select Customer --</option>';
    (d.results||[]).forEach(s=>{const o=document.createElement('option');o.value=s.code;o.dataset.name=s.name||'';o.textContent=(s.name||'').trim()+(s.code?' ['+s.code+']':'');sel.appendChild(o);});
  }).catch(()=>{});
}
function onCustSelectChange(code){if(!code)return;const sel=$('fCustSelect');const name=(sel.options[sel.selectedIndex].dataset.name)||'';sv('fCustCode',code);sv('fCustName',name);loadCustDet(code);setTimeout(()=>{sel.value='';},150);}
let custTimer=null;
let phoneAutoTimer=null;
let phoneAutoLock=false;
function onCustInput(v){clearTimeout(custTimer);custTimer=setTimeout(()=>custSearch(v),220);}
function custSearch(q){if(!q||q.length<1){hideCust();return;}fetch(url('/api/order-bill/customer-search?q=')+encodeURIComponent(q)).then(r=>r.json()).then(d=>{const drop=$('custDrop');drop.innerHTML='';(d.results||[]).forEach(s=>{const div=document.createElement('div');div.className='s-row';div.innerHTML=`<span class="s-code">${s.code}</span>${s.name}`;div.onclick=()=>selectCust(s.code,s.name);drop.appendChild(div);});drop.style.display=drop.children.length?'block':'none';}).catch(()=>{});}
function phoneDigits(v){return String(v||'').replace(/\D+/g,'');}
function onPhoneInputAuto(v){
  if(phoneAutoLock) return;
  clearTimeout(phoneAutoTimer);
  phoneAutoTimer=setTimeout(()=>autoFetchCustomerByPhone(v),260);
}
function autoFetchCustomerByPhone(v){
  if(phoneAutoLock) return;
  const q = String(v||'').trim();
  const digits = phoneDigits(q);
  if(digits.length < 6) return;
  fetch(url('/api/order-bill/customer-search?q=') + encodeURIComponent(q))
    .then(r=>r.json())
    .then(out=>{
      const rows = (out && out.results) ? out.results : [];
      if(!rows.length) return;
      const hit = rows.find(s => {
        const m = phoneDigits(s.mobile || '');
        const t = phoneDigits(s.telephone || '');
        return m === digits || t === digits;
      }) || (rows.length === 1 ? rows[0] : null);
      if(!hit || !hit.code) return;
      phoneAutoLock = true;
      selectCust(hit.code, hit.name || '');
      setTimeout(()=>{ phoneAutoLock = false; }, 450);
    })
    .catch(()=>{});
}
function hideCust(){$('custDrop').style.display='none';}
function selectCust(code,name){sv('fCustCode',code);sv('fCustName',name);hideCust();loadCustDet(code);}
function onCustKey(e){if(e.key==='Enter'||e.key==='Tab'){hideCust();loadCustDet(gv('fCustCode').trim());}}
function onCustNameKey(e){if(e.key==='Enter')custSearch(gv('fCustName').trim());}
function loadCustDet(code){if(!code)return;fetch(url('/api/order-bill/customer-details?code=')+encodeURIComponent(code)).then(r=>r.json()).then(d=>{if(!d.ok)return;sv('fCustCode',d.code||'');sv('fCustName',d.name||'');sv('fAddress',d.address||'');sv('fPhone',d.phone||'');sv('fMobile',d.mobile||'');sv('fPan',d.pan||'');sv('fGstNo',d.gst_no||'');sv('fOB',fmt2(d.old_balance||0));sv('fCB',fmt2(d.old_balance||0));}).catch(()=>{});}
function openCustModal(){sv('custModalQ','');$('custModalBody').innerHTML='';$('custModal').classList.add('active');$('custModalQ').focus();}
function closeCustModal(){$('custModal').classList.remove('active');}
function openCustomerCreate(){
  const u = url('/customer/add?type=C');
  const w = window.open(u, 'orderBillCustomerCreate', 'width=1200,height=850,resizable=yes,scrollbars=yes');
  if (!w) alert('Popup blocked. Please allow popups for this site.');
}
let custMTimer=null;
function custModalSearch(q){clearTimeout(custMTimer);if(!q)return;custMTimer=setTimeout(()=>{fetch(url('/api/order-bill/customer-search?q=')+encodeURIComponent(q)).then(r=>r.json()).then(d=>{const tb=$('custModalBody');tb.innerHTML='';(d.results||[]).forEach(s=>{const tr=document.createElement('tr');tr.innerHTML=`<td>${s.code}</td><td>${s.name}</td><td>${s.mobile||''}</td>`;tr.onclick=()=>{selectCust(s.code,s.name);closeCustModal();};tb.appendChild(tr);});}).catch(()=>{});},250);}

// Item lookup
function itemLookup(i){const code=items[i].code.trim();if(!code)return;fetch(`${url('/api/order-bill/item-lookup')}?code=${encodeURIComponent(code)}&gold_rate=${gf('fGoldRate')}`).then(r=>r.json()).then(d=>{if(!d.ok)return;items[i].name=d.name||'';items[i].purity=d.purity||'';items[i].rate=parseFloat(d.rate)||items[i].rate;items[i].wastage=parseFloat(d.wastage)||0;items[i].mcharge=parseFloat(d.mcharge)||0;calcRow(i);renderItems();updateFoot();try{$('itbody').rows[i].cells[3].querySelector('input').focus();}catch(e){}}).catch(()=>{});}

// Item Search popup
let _iSrchIdx=0,_iSrchAll=[],_iSrchFil=[],_iSrchSel=-1;
function openItemSearch(rowIdx){_iSrchIdx=rowIdx;_iSrchSel=-1;$('itemSearchModal').classList.add('active');const q=(items[rowIdx]&&items[rowIdx].code)?items[rowIdx].code:'';$('itemSrchQ').value=q;if(!_iSrchAll.length){fetch(url('/api/order-bill/item-search?q=')).then(r=>r.json()).then(d=>{_iSrchAll=d.results||[];itemSrchFilter(q);}).catch(()=>{});}else{itemSrchFilter(q);}setTimeout(()=>{$('itemSrchQ').focus();$('itemSrchQ').select();},80);}
function closeItemSearch(){$('itemSearchModal').classList.remove('active');try{$('itbody').rows[_iSrchIdx].cells[0].querySelector('input').focus();}catch(e){}}
function itemSrchFilter(q){q=(q||'').trim().toLowerCase();_iSrchFil=q?_iSrchAll.filter(r=>(r.code||'').toLowerCase().includes(q)||(r.name||'').toLowerCase().includes(q)||(r.purity||'').toLowerCase().includes(q)):_iSrchAll;_iSrchSel=_iSrchFil.length?0:-1;renderItemSrch();}
function renderItemSrch(){const tb=$('itemSrchBody');$('itemSrchCount').textContent=_iSrchFil.length+' item(s)';if(!_iSrchFil.length){tb.innerHTML='<tr><td colspan="6" style="padding:20px;text-align:center;color:#9ca3af">No items found</td></tr>';return;}tb.innerHTML=_iSrchFil.map((r,i)=>`<tr data-idx="${i}" ondblclick="itemSrchPick(${i})" onclick="itemSrchHL(${i})" style="cursor:pointer;background:${i===_iSrchSel?'#bbf7d0':''}"><td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;font-weight:600">${esc(r.code)}</td><td style="padding:4px 8px;border-bottom:1px solid #f1f5f9">${esc(r.name)}</td><td style="padding:4px 8px;border-bottom:1px solid #f1f5f9">${esc(r.purity)}</td><td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:center">${esc(r.itype)}</td><td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:right">${r.qty}</td><td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:right">${fmt3(r.weight)}</td></tr>`).join('');if(_iSrchSel>=0){const row=tb.querySelector(`tr[data-idx="${_iSrchSel}"]`);if(row)row.scrollIntoView({block:'nearest'});}}
function itemSrchHL(i){_iSrchSel=i;$('itemSrchBody').querySelectorAll('tr').forEach((tr,j)=>{tr.style.background=j===i?'#bbf7d0':'';});}
function itemSrchPick(i){const r=_iSrchFil[i];if(!r)return;items[_iSrchIdx].code=r.code;items[_iSrchIdx].name=r.name;items[_iSrchIdx].purity=r.purity;closeItemSearch();itemLookup(_iSrchIdx);}
function itemSrchKey(e){const mx=_iSrchFil.length;if(e.key==='ArrowDown'){_iSrchSel=Math.min(_iSrchSel+1,mx-1);renderItemSrch();e.preventDefault();}else if(e.key==='ArrowUp'){_iSrchSel=Math.max(_iSrchSel-1,0);renderItemSrch();e.preventDefault();}else if(e.key==='Enter'){if(_iSrchSel>=0)itemSrchPick(_iSrchSel);e.preventDefault();}else if(e.key==='Escape'){closeItemSearch();e.preventDefault();}}

let afterSaveOk = null;
function showSaveOkModal(message, onOk){
  const modal = $('saveOkModal');
  const text = $('saveOkText');
  afterSaveOk = typeof onOk === 'function' ? onOk : null;
  if(text) text.textContent = message || 'Saved';
  if(modal) modal.classList.add('active');
}
function closeSaveOkModal(){
  const modal = $('saveOkModal');
  if(modal) modal.classList.remove('active');
}
$('saveOkBtn').addEventListener('click', () => {
  const cb = afterSaveOk;
  afterSaveOk = null;
  closeSaveOkModal();
  if(cb) cb();
});

// Save
function saveBill(){
  if(!gv('fCustCode').trim()){alert('Please enter a customer.');return;}
  if(!items.filter(r=>r.code).length){alert('Please add at least one item.');return;}
  $('btnSave').disabled=true;
  const payload={mode:OB_CONFIG.mode||'bill',slno:currentSlno,doc_no:gv('fDocNo'),bill_date:gv('fDate'),
    manual_bill_no:$('fManual').checked?1:0,
    cust_code:gv('fCustCode'),cust_name:gv('fCustName'),address:gv('fAddress'),phone:gv('fPhone'),
    mobile:gv('fMobile'),pan:gv('fPan'),gst_no:gv('fGstNo'),ob:gf('fOB'),sm_code:gv('fSalesMan'),
    counter:gv('fCounter'),gold_rate:gf('fGoldRate'),note:gv('fNote'),due_date:gv('fDueDate'),
    bill_total:gf('fBillTotal'),exchange:gf('fExchange'),advance:gf('fAdvance'),gadvance:gf('fGadvance'),
    refund:gf('fRefund'),tax:gf('fTax'),sretamt:gf('fSretamt'),scheme_amt:gf('fSchemeAmt'),cocode:gv('fSchemeLedger'),
    cbcode:gv('fCashBank'),chq_bank:gv('fChqBank'),chq_amt:gf('fChqAmt'),chq_no:gv('fChqNo'),chq_date:gv('fChqDate'),chq_pdc:$('fPdc').checked?'Y':'N',
    items:items.filter(r=>r.code).map(r=>({...r,narration:r.model||r.narration||''})),
    exchange_items:exchItems.filter(r=>r.code),
    sales_return_items:salesRetItems.filter(r=>r.code).map(r=>({
      ...r,
      itemcode:r.code,
      itemname:r.name,
      stonewgt:r.stwgt,
      stoneprice:r.stprice,
      mcharge:r.mc,
      wgtamt:gf('srtDiscount'),
      taxperc:gf('srtTaxPerc'),
      taxamt:gf('srtTax'),
      ast:gf('srtCess')
    })),
    gold_advance_items:gaItems.filter(r=>String(r.code||'').trim()).map(r=>({
      ...r,
      itemcode:r.code
    })),
    model_items:modelItems
  };
  fetch(url('/api/order-bill/save'),{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify(payload)})
    .then(r=>r.json()).then(d=>{
      $('btnSave').disabled=false;
      if(!d.ok){alert('Error: '+(d.message||'Save failed'));return;}
      const savedDocNo = String(d.doc_no || payload.doc_no || '').trim();
      const saveMessage = String(d.message || '').trim() || ('Order saved: ' + savedDocNo);
      showSaveOkModal(saveMessage, () => {
        if(savedDocNo && OB_CONFIG.mode === 'bill'){
          const w = window.open(url('/order-bill/print?doc=') + encodeURIComponent(savedDocNo), '_blank', 'width=1100,height=820,scrollbars=yes');
          if(!w) alert('Popup blocked. Please allow popups for this site.');
        }
        resetForm();
        loadNextBillNo();
      });
    }).catch(e=>{$('btnSave').disabled=false;alert('Save failed: '+e);});
}

// Load bill
function loadBill(billNo){if(!billNo)return;fetch(url('/api/order-bill/get?bill_no=')+encodeURIComponent(billNo)).then(r=>r.json()).then(d=>{if(!d.ok){alert(d.message||'Not found');return;}applyBill(d);}).catch(()=>{});}
function applyBill(d){
  currentSlno=d.slno||0;sv('fDocNo',d.doc_no||'');sv('fDate',d.date||'');
  sv('fCustCode',d.cust_code||'');sv('fCustName',d.cust_name||'');sv('fAddress',d.address||'');sv('fPhone',d.phone||'');
  sv('fMobile',d.mobile||'');sv('fPan',d.pan||'');sv('fGstNo',d.gst_no||'');
  sv('fOB',fmt2(d.ob||0));sv('fSalesMan',d.salesman||'');sv('fCounter',d.counter||'');
  sv('fGoldRate',d.gold_rate||0);sv('fRate8gm',fmt2((d.gold_rate||0)*8));
  sv('fNote',d.note||'');sv('fDueDate',d.due_date||'');
  sv('fBillTotal',fmt2(d.bill_total||0));sv('fExchange',fmt2(d.exchange||0));sv('fExchAmt',(d.exchange||0).toFixed(2));
  sv('fAdvance',fmt2(d.advance||0));sv('fGadvance',fmt3(d.gadvance||0));sv('fRefund',fmt2(d.refund||0));
  sv('fTax',fmt2(d.tax||0));sv('fSretamt',fmt2(d.sretamt||0));sv('fSchemeAmt',fmt2(d.scheme_amt||0));sv('fSchemeLedger',d.scheme_ledger||'APP');
  sv('fNetTotal',fmt2(d.net_total||0));sv('fBalance',fmt2(d.balance||0));
  sv('fCB',fmt2(d.cb||0));sv('fNetBal',fmt2(d.net_bal||0));
  sv('fCashBank',d.cbcode||'CASH');sv('fChqBank',d.chq_bank||'');sv('fChqAmt',fmt2(d.chq_amt||0));
  sv('fChqNo',d.chq_no||'');sv('fChqDate',d.chq_date||'');
  if(d.chq_pdc==='Y')$('fPdc').checked=true;else $('fPdc').checked=false;
  items=(d.items||[]).map(r=>({code:r.code||'',name:r.name||'',model:r.model||r.narration||'',rate:+r.rate||0,qty:+r.qty||0,weight:+r.weight||0,stwgt:+r.stwgt||0,stprice:+r.stprice||0,mcharge:+r.mcharge||0,wastage:+r.wastage||0,amount:+r.amount||0,narration:r.model||r.narration||'',purity:r.iqtype||'',smith:r.smith||'',stage:+r.stage||1,cost:+r.cost||0}));
  exchItems=(d.exch_items||[]).map(r=>({code:r.code||'',name:r.name||'',qty:+r.qty||0,weight:+r.weight||0,mud:+r.mud||0,stone:+(r.stone??r.stwgt)||0,touch:+r.touch||0,lessperc:+r.lessperc||0,lesswgt:+r.lesswgt||0,extrawgt:+r.extrawgt||0,rate:+r.rate||0,rate2:+r.rate2||0,stprice:+r.stprice||0,netwgt:+r.netwgt||0,amount:+r.amount||0,stktype:r.stktype||'',iqtype:r.iqtype||'',stktouch:+r.stktouch||100,stkinnos:r.stkinnos||'N',ornament:r.ornament||'N',cost:+r.cost||0,itemtype:r.itemtype||'G',stkfd:r.stkfd||'',batch:r.batch||''}));
  salesRetItems=(d.sales_return_items||[]).map(r=>({
    code:r.code||'',name:r.name||'',model:r.model||'',qty:+r.qty||0,weight:+r.weight||0,
    stwgt:+(r.stonewgt??r.stwgt)||0,netwgt:0,stprice:+(r.stoneprice??r.stprice)||0,
    wastage:+r.wastage||0,mcperc:+r.mcperc||0,mc:+r.mcharge||0,rate:+r.rate||0,amount:+r.amount||0,
    stktype:r.stktype||'',iqtype:r.iqtype||'',stkinnos:r.stkinnos||'N',stktouch:+r.stktouch||100,cost:+r.cost||0,
    dmdamt:+r.dmdamt||0,dmdwgt:+r.dmdwgt||0,bcode:+r.bcode||0,mperc:+r.mperc||0,taxperc:+r.taxperc||0,taxamt:+r.taxamt||0,ast:+r.ast||0,wgtamt:+r.wgtamt||0
  }));
  const srs=d.sales_return_summary||{};
  const sDisc=+srs.discount||((salesRetItems[0]&&+salesRetItems[0].wgtamt)||0);
  const sTax=+srs.taxamt||((salesRetItems[0]&&+salesRetItems[0].taxamt)||0);
  const sCess=+srs.astamt||((salesRetItems[0]&&+salesRetItems[0].ast)||0);
  const base=Math.max((salesRetItems.reduce((a,r)=>a+(+r.amount||0),0))-sDisc,0);
  const sTaxPerc=base>0?r2((sTax*100)/base):((salesRetItems[0]&&+salesRetItems[0].taxperc)||0);
  const cessBase=swFlag('CessIsBasedOnBillAmt','Y')?base:(base+sTax);
  const sCessPerc=cessBase>0?r2((sCess*100)/cessBase):0;
  sv('srtDiscount',fmt2(sDisc));
  sv('srtTaxPerc',fmt2(sTaxPerc));
  sv('srtTax',fmt2(sTax));
  sv('srtCessPerc',fmt2(sCessPerc));
  sv('srtCess',fmt2(sCess));
  gaItems=(d.gold_advance_items||[]).map(r=>({
    code:r.code||r.itemcode||'',
    name:r.name||r.itemname||'',
    qty:+r.qty||0,
    weight:+r.weight||0,
    stonewgt:+r.stonewgt||0,
    lessperc:+r.lessperc||0,
    lesswgt:+r.lesswgt||0,
    netwgt:0,
    itemtype:r.itemtype||'G',
    cost:+r.cost||0,
    stktype:r.stktype||'',
    iqtype:r.iqtype||'',
    stktouch:+r.stktouch||0
  }));
  modelItems=(d.model_items||[]).map(r=>({...r}));
  gaItems.forEach((_,i)=>calcGaRow(i));
  renderItems();updateFoot();renderExchItems();updateExchFoot();
  renderSalesReturnItems();updateSalesReturnFoot('discount');
  renderGaItems();updateGaFoot();
}

// Navigation
function prevBill(){fetch(url('/api/order-bill/prev?doc_no=')+encodeURIComponent(gv('fDocNo'))).then(r=>r.json()).then(d=>{if(d.ok&&d.bill_no){$('fManual').checked=false;loadBill(d.bill_no);}else alert(d.message||'No previous order');}).catch(()=>{});}
function nextBill(){fetch(url('/api/order-bill/next?doc_no=')+encodeURIComponent(gv('fDocNo'))).then(r=>r.json()).then(d=>{if(d.ok&&d.bill_no){$('fManual').checked=false;loadBill(d.bill_no);}else alert(d.message||'No next order');}).catch(()=>{});}

// Search
function openBillSearch(){sv('billSearchQ','');$('billSrchBody').innerHTML='';$('billModal').classList.add('active');$('billSearchQ').focus();}
function closeBillSearch(){$('billModal').classList.remove('active');}
let bsTimer=null;
function doBillSearch(q){clearTimeout(bsTimer);if(!q)return;bsTimer=setTimeout(()=>{fetch(url('/api/order-bill/search?q=')+encodeURIComponent(q)).then(r=>r.json()).then(d=>{const tb=$('billSrchBody');tb.innerHTML='';(d.results||[]).forEach(b=>{const tr=document.createElement('tr');tr.innerHTML=`<td>${b.doc_no}</td><td>${b.date}</td><td>${b.cust_name}</td><td style="text-align:right">${fmt2(b.bill_total)}</td>`;tr.onclick=()=>{loadBill(b.doc_no);closeBillSearch();};tb.appendChild(tr);});}).catch(()=>{});},300);}

// Cancel
function openCancelModal(){if(!gv('fDocNo')){alert('Load an order first');return;}sv('cancelBillNo',gv('fDocNo'));sv('cancelReason','');$('cancelModal').classList.add('active');}
function confirmCancel(){fetch(url('/api/order-bill/cancel'),{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({doc_no:gv('fDocNo'),reason:gv('cancelReason')})}).then(r=>r.json()).then(d=>{$('cancelModal').classList.remove('active');if(!d.ok){alert('Error: '+(d.message||''));return;}alert('Order cancelled');resetForm();loadNextBillNo();}).catch(()=>{});}

function syncState(code){sv('fStateName',code);}
function syncStateFromName(code){sv('fStateCode',code);}
function onManualChk(){$('fDocNo').readOnly=!$('fManual').checked;if(!$('fManual').checked)loadNextBillNo();}

function newBill(){resetForm();loadNextBillNo();}
function resetForm(){
  currentSlno=0;selRowIdx=-1;
  ['fDocNo','fCustCode','fCustName','fAddress','fPhone','fMobile','fPan','fGstNo','fNote','fDueDate'].forEach(id=>sv(id,''));
  sv('fDate',todayDMY());sv('fOB','.00');sv('fCB','.00');
  ['fBillTotal','fExchange','fAdvance','fRefund','fTax','fSretamt','fSchemeAmt','fNetTotal','fBalance','fNetBal','fChqAmt'].forEach(id=>sv(id,'.00'));
  sv('fGadvance','.000');
  sv('fSchemeLedger','APP');
  sv('fExchAmt','0');sv('fChqNo','');sv('fChqDate','');
  $('fPdc').checked=false;
  items=[];exchItems=[];salesRetItems=[];gaItems=[];modelItems=[];selRowIdx=-1;exchSelIdx=-1;sretSelIdx=-1;gaSelIdx=-1;
  sv('srtDiscount','0.00');sv('srtTaxPerc','0.00');sv('srtTax','0.00');sv('srtCessPerc','0.00');sv('srtCess','0.00');sv('srtTva','0.00');
  renderItems();updateFoot();addRow();renderExchItems();renderSalesReturnItems();updateSalesReturnFoot('discount');renderGaItems();updateGaFoot();
}
function todayDMY(){const d=new Date();const p=n=>String(n).padStart(2,'0');return p(d.getDate())+'/'+p(d.getMonth()+1)+'/'+d.getFullYear();}
function loadNextBillNo(){fetch(url('/api/order-bill/next-number?bill_date=')+encodeURIComponent(gv('fDate'))).then(r=>r.json()).then(d=>{if(d.bill_no)sv('fDocNo',d.bill_no);}).catch(()=>{});}
function refreshAutoDocNo(){if(!$('fManual').checked && currentSlno===0)loadNextBillNo();}
function doExit(){if(window.parent&&window.parent!==window)window.parent.postMessage({action:'closeModule'},'*');else window.history.back();}

document.addEventListener('keydown', e => {
  const tag = (e.target.tagName || '').toUpperCase();
  const inInput = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
  // F9 = Save
  if (e.key === 'F9') { e.preventDefault(); saveBill(); return; }
  // F8 = New
  if (e.key === 'F8') { e.preventDefault(); newBill(); return; }
  // F7 = Prev
  if (e.key === 'F7') { e.preventDefault(); prevBill(); return; }
  // F6 = Next
  if (e.key === 'F6') { e.preventDefault(); nextBill(); return; }
  // Escape = Exit (only when no modal is open)
  if (e.key === 'Escape' && !inInput) {
    const modal = document.querySelector('.modal[style*="flex"], .modal[style*="block"]');
    if (!modal) { e.preventDefault(); doExit(); }
  }
});

document.addEventListener('DOMContentLoaded',()=>{
  $('fDate')?.addEventListener('change',()=>{refreshAutoDocNo();});
  loadNextBillNo();addRow();loadCustSelect();
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshAutoDocNo();});
  window.addEventListener('focus',()=>{refreshAutoDocNo();});
  document.addEventListener('click',e=>{if(!e.target.closest('.cust-row'))hideCust();});
  window.addEventListener('message', (event) => {
    const d = event && event.data ? event.data : null;
    if (!d || typeof d !== 'object') return;
    if (d.type !== 'goldapp:customer-created') return;
    const code = String(d.code || '').trim();
    const name = String(d.name || '').trim();
    if (!code) return;
    sv('fCustCode', code);
    if (name) sv('fCustName', name);
    loadCustDet(code);
    loadCustSelect();
  });
if(OB_CONFIG.mode==='cancel'||OB_CONFIG.mode==='reprint')$('btnSave').style.display='none';

if (OB_CONFIG.preloadDoc) {
  loadBill(OB_CONFIG.preloadDoc);
  if (OB_CONFIG.mode === 'reprint') {
    setTimeout(() => { try { window.print(); } catch (e) {} }, 900);
  }
}
});
</script>
</body>
</html>
