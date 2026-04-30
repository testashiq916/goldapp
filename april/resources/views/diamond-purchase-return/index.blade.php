<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('js/goldapp-common.js') }}"></script>
<title>{{ $title }}</title>
<script>
window.DPR_CONFIG = {
  siteUrl:    @json(request()->root()),
  mode:       @json($mode),
  rates: {
    gold:     {{ $rates['gold']     ?? 0 }},
    gold18:   {{ $rates['gold18']   ?? 0 }},
    silver:   {{ $rates['silver']   ?? 0 }},
    platinum: {{ $rates['platinum'] ?? 0 }},
  },
  salesmen:  @json(array_map('get_object_vars', $salesmen)),
  software:  @json($software ?? []),
};
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter','Segoe UI',Tahoma,sans-serif;font-size:12px;color:#1a202c;overflow:hidden;height:100vh;
  background:radial-gradient(circle at 10% -10%,#eef3ff 0%,#f4f7fb 40%,#edf2f8 100%)}
input,select,button{transition:all .15s ease}
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-track{background:#f7fafc}
::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:3px}

/* ── Main window ── */
.main-window{background:#fff;border:1px solid #d6dcea;border-radius:12px;
  box-shadow:0 10px 30px rgba(33,52,89,.10);margin:8px;overflow:hidden;position:relative}

.title-bar{background:linear-gradient(135deg,#7f1d1d,#991b1b);color:#fff;
  padding:10px 14px;font-weight:700;font-size:13px;display:flex;align-items:center;gap:8px;letter-spacing:.3px}
.title-bar .icon{width:16px;height:16px;background:#f87171;border-radius:4px;flex-shrink:0}

/* ── Top section ── */
.top-section{background:#f9fbff;padding:10px;display:grid;
  grid-template-columns:1.2fr 1fr 1.1fr;gap:4px 14px;border-bottom:1px solid #d6dcea}
.top-section .row{display:flex;align-items:center;gap:4px;padding:1px 0;min-height:26px}
.top-section label{font-weight:600;font-size:11px;min-width:72px;color:#6b7280}
.top-section input,.top-section select{font-size:11px;padding:2px 6px;border:1px solid #d6dcea;
  background:#fff;height:26px;border-radius:7px;color:#2d3748;
  box-shadow:inset 0 1px 2px rgba(17,24,39,.03)}
.top-section input:focus,.top-section select:focus{border-color:#a9b7ff;
  box-shadow:0 0 0 3px rgba(91,109,238,.14);outline:none}
.top-section input.sm{width:68px}
.top-section input.md{width:108px}
.top-section input.lg{flex:1;min-width:0;width:auto}
.top-section .row.sup-row{position:relative}
#supDrop{position:absolute;top:28px;left:72px;width:380px;max-height:200px;overflow-y:auto;
  background:#111827;border:1px solid #374151;border-radius:6px;
  box-shadow:0 6px 16px rgba(0,0,0,.35);z-index:500;display:none;font-size:11px;color:#f9fafb}
#supDrop .s-row{padding:5px 8px;cursor:pointer;border-bottom:1px solid #374151}
#supDrop .s-row:hover{background:#1f2937}
#supDrop .s-row .s-code{font-weight:600;color:#93c5fd;margin-right:5px}
.sup-btns button{font-size:10px;padding:1px 7px;border:1px solid #d6dcea;border-radius:6px;
  cursor:pointer;background:#fff;height:26px;color:#374151}
.sup-btns button:hover{background:#eef3ff}

/* ── Items table ── */
.table-container{margin:0 8px;border:1px solid #d6dcea;border-radius:10px;
  background:#fff;overflow:hidden;box-shadow:0 3px 10px rgba(32,55,92,.05)}
.table-scroll{max-height:calc(100vh - 390px);overflow-y:auto;overflow-x:auto}
table.items{width:100%;border-collapse:collapse;font-size:10px}
table.items thead th{background:linear-gradient(180deg,#991b1b,#7f1d1d);color:#fef2f2;
  padding:4px 3px;border:1px solid #b91c1c;font-weight:600;font-size:9px;
  text-align:center;white-space:nowrap;text-transform:uppercase;letter-spacing:.3px;position:sticky;top:0;z-index:2}
table.items tbody td{border:1px solid #edf1f7;padding:1px 1px;text-align:center;height:22px}
table.items tbody tr.irow:nth-child(4n+1){background:#fff}
table.items tbody tr.irow:nth-child(4n+3){background:#fff5f5}
table.items tbody tr.irow:hover{background:#fef2f2}
table.items tbody tr.irow.sel td{background:#fecaca}
table.items tbody input{font-size:10px;border:none;background:transparent;
  text-align:center;width:100%;height:100%}
table.items tbody input:focus{background:#fffff0;outline:2px solid #ef4444;border-radius:2px}
table.items tbody input.num{text-align:right}

/* ── Diamond sub-row ── */
tr.dmd-row td{padding:0 !important;background:#fff5f5 !important;border-top:2px solid #ef4444}
.dmd-wrap{background:linear-gradient(135deg,#fef2f2,#fdf2f8);
  border:1px solid #fecaca;border-radius:6px;margin:3px 4px;overflow:hidden}
.dmd-head{background:linear-gradient(180deg,#991b1b,#7f1d1d);color:#fef2f2;
  padding:3px 6px;font-size:10px;font-weight:700;letter-spacing:.4px;
  display:flex;align-items:center;gap:8px}
.dmd-head button{font-size:9px;padding:1px 8px;border:1px solid #f87171;border-radius:4px;
  cursor:pointer;background:rgba(255,255,255,.15);color:#fecaca;font-weight:600}
.dmd-head button:hover{background:rgba(255,255,255,.25)}
table.dmd-tbl{width:100%;border-collapse:collapse;font-size:10px}
table.dmd-tbl thead th{background:linear-gradient(180deg,#b91c1c,#991b1b);color:#fecaca;
  padding:3px 4px;border:1px solid #dc2626;font-weight:600;text-align:center;white-space:nowrap}
table.dmd-tbl tbody td{border:1px solid #fecaca;padding:1px 2px;text-align:center;height:20px;background:#fffbfb}
table.dmd-tbl tbody tr:hover td{background:#fecaca}
table.dmd-tbl tbody input{font-size:10px;border:none;background:transparent;width:100%;height:100%;text-align:center}
table.dmd-tbl tbody input.num{text-align:right}
table.dmd-tbl tbody input:focus{background:#fffff0;outline:2px solid #ef4444;border-radius:2px}
table.dmd-tbl tfoot td{background:#fef2f2;font-weight:700;font-size:10px;padding:2px 4px;
  text-align:center;border:1px solid #fecaca;color:#7f1d1d}

/* ── Table footer ── */
.table-footer{display:flex;align-items:center;background:#f9fbff;
  padding:5px 10px;gap:6px;font-size:10px;font-weight:600;
  color:#2d3748;border-top:1px solid #d6dcea;flex-wrap:wrap}
.table-footer button{background:#fff;border:1px solid #d0d9ea;border-radius:8px;
  padding:2px 10px;font-size:10px;cursor:pointer;font-weight:600;color:#3f4a5b}
.table-footer button:hover{background:#eef3ff;border-color:#bfcaf0}
.tf-label{color:#744210;font-weight:700}
.tfv{text-align:right;font-variant-numeric:tabular-nums;min-width:28px}

/* ── Bottom section ── */
.bottom-section{background:#f9fbff;padding:5px 8px 6px;
  display:flex;align-items:flex-start;gap:8px;
  border-top:1px solid #d6dcea;font-size:11px}
.foot-rows{flex:1;display:flex;flex-direction:column;gap:0}
.frow{display:flex;align-items:center;gap:3px;min-height:24px;white-space:nowrap;flex-wrap:nowrap}
.fl{font-weight:600;font-size:11px;color:#4a5568;padding:0 2px 0 4px;white-space:nowrap;flex-shrink:0}
input.fv,select.fv{font-size:11px;padding:1px 4px;border:1px solid #c8d3e8;
  background:#fff;height:22px;border-radius:5px;color:#2d3748;flex-shrink:0;
  box-shadow:inset 0 1px 2px rgba(17,24,39,.03)}
input.fv:focus,select.fv:focus{border-color:#a9b7ff;box-shadow:0 0 0 2px rgba(91,109,238,.14);outline:none}
.fw36{width:38px}.fw46{width:48px}.fw70{width:72px}.fw84{width:86px}.fw94{width:96px}.fwx{flex:1;min-width:60px}
.fsep{display:inline-block;width:6px;flex-shrink:0}
.flbl{font-size:11px;color:#4a5568;padding-right:4px;flex-shrink:0}
.fchk{display:inline-flex;align-items:center;gap:2px;flex-shrink:0}
.fchk input[type=checkbox]{width:13px;height:13px;margin:0;cursor:pointer}
.foot-btns{display:flex;flex-direction:column;gap:3px;min-width:108px;flex-shrink:0;padding-left:4px}
.fbtn{background:linear-gradient(180deg,#f5f5f5,#e8e8e8);border:1px solid #adadad;
  border-radius:4px;padding:2px 6px;font-size:11px;cursor:pointer;font-weight:600;
  color:#111;height:26px;font-family:inherit;text-align:center;white-space:nowrap}
.fbtn:hover{background:linear-gradient(180deg,#fff,#f0f0f0)}
.fbtn:active{background:#e0e0e0;transform:translateY(1px)}
.fbtn.fb-save{background:linear-gradient(180deg,#fce4ec,#f8bbd0);border-color:#c62828;color:#7f1d1d}
.fbtn.fb-save:hover{background:linear-gradient(180deg,#fef2f2,#fce4ec)}
.foot-chk{display:flex;align-items:center;gap:3px;font-size:11px;color:#374151;padding:1px 0}
.foot-chk input[type=checkbox]{width:13px;height:13px;margin:0;cursor:pointer}
.inline-chk{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#4a5568}
.fp1,.fp2,.fp3{display:flex;flex-direction:column;gap:2px}
.fp1,.fp2{flex-shrink:0}
.fp3{flex:1;min-width:0}
.fp1 .fr,.fp2 .fr,.fp3 .fr{display:flex;align-items:center;gap:3px;height:22px;white-space:nowrap}
.fl1{font-weight:600;font-size:11px;color:#4a5568;min-width:62px;flex-shrink:0;white-space:nowrap;padding-right:3px}
.fl2{font-weight:600;font-size:11px;color:#4a5568;min-width:56px;flex-shrink:0;white-space:nowrap;padding-right:3px}

/* ── Exchange section ── */
.exch-bar{display:flex;align-items:center;background:#fffbeb;
  padding:4px 10px;gap:10px;font-size:11px;font-weight:600;
  color:#744210;border-top:1px solid #fde68a}
#exchSection{border-top:1px solid #fde68a;background:#fffdf5}
table.exch{width:100%;border-collapse:collapse;font-size:11px}
table.exch thead th{background:linear-gradient(180deg,#92400e,#78350f);color:#fef3c7;
  padding:4px 5px;border:1px solid #92400e;font-weight:600;font-size:10px;
  text-align:center;white-space:nowrap;letter-spacing:.4px}
table.exch tbody td{border:1px solid #fde68a;padding:1px 2px;text-align:center;height:22px}
table.exch tbody input{font-size:11px;border:none;background:transparent;
  text-align:center;width:100%;height:100%}
table.exch tbody input:focus{background:#fffff0;outline:2px solid #f59e0b;border-radius:2px}
table.exch tbody input.num{text-align:right}

/* ── Modals ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);
  z-index:1000;justify-content:center;align-items:center}
.modal-overlay.active{display:flex}
.modal-box{background:#fff;border:1px solid #d6dcea;border-radius:10px;
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
.mbtn:hover{background:#eef3ff}
.srch-tbl{width:100%;border-collapse:collapse;font-size:11px}
.srch-tbl th{background:linear-gradient(180deg,#991b1b,#7f1d1d);color:#fef2f2;
  padding:4px 8px;text-align:left;font-weight:600}
.srch-tbl td{padding:3px 8px;border-bottom:1px solid #edf1f7;cursor:pointer}
.srch-tbl tr:hover td{background:#fecaca}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="main-window">

  <div class="title-bar">
    <div class="icon"></div>
    <span id="titleText">{{ $title }}</span>
  </div>

  <!-- ══ TOP SECTION ══ -->
  <div class="top-section">
    <div>
      <div class="row">
        <label>Bill No</label>
        <input id="fDocNo" class="md" value="" readonly style="background:#fefce8">
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
          <option value="__NEW__" style="color:#1a56db;font-weight:600">+ Create New Supplier</option>
        </select>
      </div>
      <div class="row"><label>Address</label><input id="fAddress" class="lg" value=""></div>
      <div class="row"><label>Note</label><input id="fNote" class="lg" value=""></div>
    </div>

    <div>
      <div class="row">
        <label>Sup.Bill No</label>
        <input id="fBillNo" class="md" placeholder="Supplier bill no">
        <label style="min-width:auto;margin-left:8px">Date</label>
        <input id="fDate" class="md" value="{{ date('d/m/Y') }}" style="background:#fefce8">
      </div>
      <div class="row">
        <label>OB</label>
        <input id="fOB" class="sm" value=".00" readonly style="background:#f1f5f9">
        <label style="min-width:auto;margin-left:8px">Sales Man</label>
        <select id="fSalesMan" style="flex:1;min-width:0;font-size:11px;height:26px;border-radius:7px;border:1px solid #d6dcea">
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

    <div>
      <div class="row">
        <label>Gold Rate</label>
        <input id="fGoldRate" class="sm" value="{{ $rates['gold'] ?? 0 }}" oninput="onRateChange()">
      </div>
      <div class="row">
        <label>BType</label>
        <select id="fBType" style="width:90px;font-size:11px;height:26px;border-radius:7px;border:1px solid #d6dcea">
          <option value="Dmd" selected>Diamond</option>
          <option value="Gold">Gold</option>
          <option value="Silvr">Silver</option>
          <option value="Other">Other</option>
        </select>
      </div>
    </div>
  </div>

  <!-- ══ ITEMS TABLE ══ -->
  <div class="table-container" style="margin-top:4px">
    <div class="table-scroll">
    <table class="items" id="itbl">
      <thead>
        <tr>
          <th style="width:80px">Barcode</th>
          <th style="width:62px">Item Code</th>
          <th style="width:90px">Item Name</th>
          <th style="width:36px">Purity</th>
          <th style="width:30px">Qty</th>
          <th style="width:52px">Gr.Wgt<br>gm</th>
          <th style="width:44px">Stone<br>Wgt</th>
          <th style="width:50px">Stone<br>Price</th>
          <th style="width:38px">Touch</th>
          <th style="width:50px">Less%</th>
          <th style="width:50px">Less<br>Wgt</th>
          <th style="width:38px">Mud</th>
          <th style="width:50px">Net<br>Wgt</th>
          <th style="width:50px">Rate/Gm</th>
          <th style="width:42px">Wastage</th>
          <th style="width:44px">M.Charge</th>
          <th style="width:54px">Dmd Amt</th>
          <th style="width:66px">Amount</th>
          <th style="width:28px" title="Stock Type">Stk</th>
          <th style="width:38px">SVA%</th>
          <th style="width:56px">Sale<br>Amt</th>
          <th style="width:56px">HUID</th>
          <th style="width:28px">Dmd</th>
        </tr>
      </thead>
      <tbody id="itbody"></tbody>
    </table>
    </div>
  </div>

  <!-- ══ TABLE FOOTER ══ -->
  <div class="table-footer">
    <button onclick="addRow()">Add</button>
    <button onclick="delRow()">Delete</button>
    <span class="tf-label">Items: <span id="cntItems">0</span></span>
    <span class="tf-label" style="margin-left:6px">Tot:</span>
    <span class="tfv" id="ftQty">0</span>
    <span class="tfv" id="ftWgt">0.000</span>
    <span class="tfv" id="ftStwgt">0.000</span>
    <span class="tfv" id="ftStprice">0.00</span>
    <span class="tfv" id="ftNetwgt">0.000</span>
    <span class="tfv" id="ftGoldamt">0.00</span>
    <span class="tfv" id="ftMc">0.00</span>
    <span class="tfv" id="ftDmdamt">0.00</span>
    <span class="tfv" id="ftSalesamt">0.00</span>
    <span class="tfv" id="ftAmt">0.00</span>
  </div>

  <input type="hidden" id="fExchAmt" value="0">

  <!-- ══ BOTTOM SECTION ══ -->
  <div class="bottom-section">
    <div class="fp1">
      <div class="fr">
        <span class="fl1">Bill Total</span>
        <input id="fBillTotal" class="fv fw84" value=".00" readonly style="font-weight:700;background:#f0f4ff">
      </div>
      <div class="fr">
        <span class="fl1">Exchange</span>
        <input id="fExchAmtBot" class="fv" style="width:84px;background:#fef9e7;color:#b45309;font-weight:600" value=".00" readonly>
      </div>
      <div class="fr">
        <span class="fl1">Others</span>
        <input id="fOthers" class="fv fw84" value=".00" oninput="triggerRecalc()">
      </div>
      <div class="fr">
        <span class="fl1">Paid Amt</span>
        <input id="fPaidAmt" class="fv fw84" value=".00" oninput="onPaidChange()">
      </div>
    </div>

    <div class="fp2">
      <div class="fr">
        <span class="fchk"><input type="checkbox" id="fExternal" onchange="triggerRecalc()"><span class="flbl">External</span></span>
        <span class="fl" style="padding-left:6px">Tax%</span>
        <input id="fTaxPerc" class="fv fw36" value="0" oninput="triggerRecalc()">
        <input id="fTaxAmt" class="fv fw84" value=".00" readonly style="background:#f1f5f9">
      </div>
      <div class="fr">
        <span class="fl2">Net Total</span>
        <input id="fNetTotal" class="fv fw94" value=".00" readonly style="background:#fecaca;font-weight:700;color:#7f1d1d">
      </div>
      <div class="fr">
        <span class="fl2">Balance</span>
        <input id="fBalance" class="fv fw94" value=".00" readonly style="background:#fef3c7;font-weight:700">
      </div>
      <div class="fr">
        <span class="fl2">OB</span>
        <input id="fOBBot" class="fv fw84" value=".00" readonly style="background:#f1f5f9">
      </div>
      <div class="fr">
        <span class="fl2">CB</span>
        <input id="fCB" class="fv fw84" value=".00" readonly style="background:#f1f5f9">
      </div>
    </div>

    <div class="fp3">
      <div class="fr">
        <span class="fchk"><input type="checkbox" id="fInterstate" onchange="triggerRecalc()"><span class="flbl">Interstate (CST)</span></span>
      </div>
      <div class="fr">
        <span class="fchk"><input type="checkbox" id="fDontAddDmdAmt"><span class="flbl">Dont Add Dmd Amt</span></span>
      </div>
      <div class="fr">
        <span class="fchk"><input type="checkbox" id="fDontAddDmdWgt"><span class="flbl">Dont Add Dmd Wgt</span></span>
      </div>
      <div class="fr">
        <span class="fchk"><input type="checkbox" id="fKeepAcWgt"><span class="flbl">Keep A/c in Weight</span></span>
      </div>
    </div>

    <div class="foot-btns">
      <button class="fbtn" onclick="toggleExch()">Exchange</button>
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
  </div>

</div>

<!-- ── Exchange modal ── -->
<div class="modal-overlay" id="exchModal">
  <div class="modal-box" style="min-width:680px;max-width:820px">
    <div class="modal-head" style="background:linear-gradient(135deg,#92400e,#78350f)">
      <span>Exchange / Old Gold</span>
      <span style="font-size:11px;font-weight:400;margin-left:10px">Exchange Amt: <span id="fExchAmtDisplay" style="color:#fde68a;font-weight:700">0.00</span></span>
      <button class="cls" onclick="closeExchModal()">&#10006;</button>
    </div>
    <div class="modal-body" style="padding:6px 8px">
      <div style="max-height:260px;overflow-y:auto;border:1px solid #fde68a;border-radius:6px">
        <table class="exch" id="etbl" style="width:100%">
          <thead><tr>
            <th style="width:70px">Item Code</th><th style="width:140px">Item Name</th>
            <th style="width:38px">Qty</th><th style="width:72px">Weight</th>
            <th style="width:46px">Less%</th><th style="width:64px">Less Wgt</th>
            <th style="width:70px">Rate</th><th style="width:70px">St.Price</th>
            <th style="width:80px">Amount</th>
          </tr></thead>
          <tbody id="echbody"></tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer" style="background:#fffbeb;border-top:1px solid #fde68a;justify-content:flex-start;gap:8px">
      <button class="mbtn" onclick="addExchRow()" style="border-color:#f6ad55;color:#b45309">Add Row</button>
      <button class="mbtn" onclick="delExchRow()" style="border-color:#f6ad55;color:#b45309">Delete Row</button>
      <span style="flex:1"></span>
      <span style="font-weight:700;font-size:11px;color:#744210">Exch Total:</span>
      <span id="eftAmt" style="font-weight:700;font-size:12px;color:#dc2626;min-width:80px;text-align:right">0.00</span>
      <button class="mbtn fb-save" onclick="closeExchModal()" style="margin-left:8px">Done</button>
    </div>
  </div>
</div>

<!-- ── Supplier modal ── -->
<div class="modal-overlay" id="supModal">
  <div class="modal-box" style="min-width:460px">
    <div class="modal-head"><span>Supplier Search</span><button class="cls" onclick="closeSupModal()">&#10006;</button></div>
    <div class="modal-body">
      <input id="supModalQ" style="width:100%;height:26px;font-size:12px;border:1px solid #d6dcea;border-radius:7px;padding:2px 8px"
        placeholder="Search by code or name..." oninput="supModalSearch(this.value)" autocomplete="off">
      <div style="margin-top:8px;max-height:220px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px">
        <table class="srch-tbl"><thead><tr><th>Code</th><th>Name</th><th>Mobile</th></tr></thead>
        <tbody id="supModalBody"></tbody></table>
      </div>
    </div>
    <div class="modal-footer"><button class="mbtn" onclick="closeSupModal()">Close</button></div>
  </div>
</div>

<!-- ── Bill search modal ── -->
<div class="modal-overlay" id="billModal">
  <div class="modal-box" style="min-width:520px">
    <div class="modal-head"><span>Search Diamond Purchase Return</span><button class="cls" onclick="closeBillSearch()">&#10006;</button></div>
    <div class="modal-body">
      <input id="billSearchQ" style="width:100%;height:26px;font-size:12px;border:1px solid #d6dcea;border-radius:7px;padding:2px 8px"
        placeholder="Enter bill no or supplier name..." oninput="doBillSearch(this.value)" autocomplete="off">
      <div style="margin-top:8px;max-height:240px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px">
        <table class="srch-tbl"><thead><tr><th>Bill No</th><th>Date</th><th>Supplier</th><th style="text-align:right">Net Total</th></tr></thead>
        <tbody id="billSrchBody"></tbody></table>
      </div>
    </div>
    <div class="modal-footer"><button class="mbtn" onclick="closeBillSearch()">Close</button></div>
  </div>
</div>

<!-- ── Item Search modal ── -->
<div class="modal-overlay" id="itemSearchModal">
  <div class="modal-box" style="min-width:720px;max-width:900px;height:520px;display:flex;flex-direction:column">
    <div class="modal-head" style="background:linear-gradient(135deg,#7f1d1d,#991b1b)">
      <span>Item Search</span><button class="cls" onclick="closeItemSearch()">&#10006;</button>
    </div>
    <div style="padding:8px 12px;display:flex;gap:8px;align-items:center;border-bottom:1px solid #e2e8f0;flex-shrink:0">
      <input id="itemSrchQ" placeholder="Search by code or name..." autocomplete="off"
        style="flex:1;height:30px;font-size:12px;border:1px solid #d6dcea;border-radius:7px;padding:2px 10px"
        oninput="itemSrchFilter(this.value)" onkeydown="itemSrchKey(event)">
      <span style="font-size:11px;color:#6b7280" id="itemSrchCount"></span>
    </div>
    <div style="flex:1;overflow:auto">
      <table style="width:100%;border-collapse:collapse;font-size:11px">
        <thead><tr style="background:#f1f5f9;position:sticky;top:0;z-index:1">
          <th style="padding:5px 8px;text-align:left;border-bottom:2px solid #cbd5e1">Code</th>
          <th style="padding:5px 8px;text-align:left;border-bottom:2px solid #cbd5e1">Name</th>
          <th style="padding:5px 8px;text-align:left;border-bottom:2px solid #cbd5e1">Purity</th>
          <th style="padding:5px 8px;text-align:center;border-bottom:2px solid #cbd5e1">Type</th>
          <th style="padding:5px 8px;text-align:right;border-bottom:2px solid #cbd5e1">Touch</th>
          <th style="padding:5px 8px;text-align:right;border-bottom:2px solid #cbd5e1">Qty</th>
          <th style="padding:5px 8px;text-align:right;border-bottom:2px solid #cbd5e1">Weight</th>
        </tr></thead>
        <tbody id="itemSrchBody"></tbody>
      </table>
    </div>
    <div class="modal-footer" style="font-size:11px;color:#6b7280">Double-click or Enter to select | Esc to close</div>
  </div>
</div>

<!-- ── Cancel modal ── -->
<div class="modal-overlay" id="cancelModal">
  <div class="modal-box" style="min-width:360px">
    <div class="modal-head"><span>Cancel Diamond Purchase Return</span>
      <button class="cls" onclick="document.getElementById('cancelModal').classList.remove('active')">&#10006;</button></div>
    <div class="modal-body">
      <p style="margin-bottom:8px;font-size:12px">Cancel bill: <strong id="cancelBillNo"></strong></p>
      <input id="cancelReason" style="width:100%;height:26px;border:1px solid #d6dcea;border-radius:7px;padding:2px 8px;font-size:12px" placeholder="Reason for cancellation">
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
let dmdItems   = [];   // dmdItems[i] = array of diamond sub-row objects for items[i]
let exchItems  = [];
let currentSlno = 0;
let selRowIdx   = -1;
let exchSelIdx  = -1;
let recalcTimer = null;
let nextBcode   = 0;

// ── Helpers ────────────────────────────────────────────────────────────────
const $  = id => document.getElementById(id);
const gv = id => {const el=$(id); return el?(el.value??''):''};
const sv = (id, v) => {
  const el=$(id); if(!el) return;
  if(el.tagName==='INPUT'||el.tagName==='SELECT'||el.tagName==='TEXTAREA') el.value=v;
  else el.textContent=v;
};
const gf   = (id,d=0) => parseFloat(gv(id))||d;
const r2   = n => Math.round(n*100)/100;
const r3   = n => Math.round(n*1000)/1000;
const fmt2 = n => parseFloat(n||0).toFixed(2);
const fmt3 = n => parseFloat(n||0).toFixed(3);
const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
const url  = p  => DPR_CONFIG.siteUrl + p;
function esc(s){const d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}

// ── Purity → rate selection (18K uses gold18 rate) ──────────────────────────
function purityRate(i){
  const p=(items[i].purity||'').trim();
  if(p.substring(0,2)==='18' && DPR_CONFIG.rates.gold18>0){
    items[i].rate=DPR_CONFIG.rates.gold18;
  } else {
    items[i].rate=DPR_CONFIG.rates.gold||0;
  }
  calcRow(i); rfRow(i); rfMperc(i); updateFoot(); renderItems();
}

// ── Stwgt N-suffix shortcut (e.g. "5N" = weight - 5) ───────────────────────
function parseStwgt(i, el){
  const v=el.value.toString().trim().toUpperCase();
  if(v.endsWith('N')){
    const n=parseFloat(v.slice(0,-1))||0;
    const sw=r3(items[i].weight - n);
    items[i].stwgt=sw>0?sw:0;
    el.value=fmt3(items[i].stwgt);
  } else {
    items[i].stwgt=+v||0;
  }
  calcLesswgt(i); calcRow(i); rfRow(i); rfMperc(i); updateFoot();
}

// ── Default rows ────────────────────────────────────────────────────────────
function newItemRow(){
  return {code:'',name:'',barcode:'',purity:'',rate:DPR_CONFIG.rates.gold||0,
    qty:0,weight:0,stwgt:0,stprice:0,touch:0,
    lessperc:0,lesswgt:0,mud:0,
    netwgt:0,goldamt:0,mcharge:0,wastage:0,
    dmdamt:0,mperc:0,stkinnos:'N',svaperc:0,
    smcharge:0,sstprice:0,salesamt:0,huid:'',amount:0,
    stktype:'',iqtype:'',fr:'N'};
}
function newDmdRow(){
  return {stcode:'',sttype:'',stcolor:'',stcut:'',stsettype:'',
    pcs:0,carats:0,wgt:0,rate:0,amount:0,samount:0};
}

// ── Lesswgt calc from touch or lessperc ──────────────────────────────────
function calcLesswgt(i){
  const r=items[i];
  if(Math.abs(r.touch)>0){
    const raw = r.weight - r.stwgt;
    r.lesswgt = r3(raw - (raw * r.touch) / 100);
  } else if(Math.abs(r.lessperc)>0){
    r.lesswgt = r3((r.weight - r.stwgt - r.mud) * r.lessperc / 100);
  }
}

// ── Row calculation ────────────────────────────────────────────────────────
function calcRow(i){
  const r=items[i];
  // netwgt = weight - stwgt - lesswgt - mud
  r.netwgt = r3(r.weight - r.stwgt - r.lesswgt - r.mud);
  if((r.stkinnos||'').toUpperCase()==='Y'){
    r.goldamt = r2(r.qty * r.rate);
  } else {
    r.goldamt = r2(r.netwgt * r.rate);
  }
  r.amount = r2(r.goldamt + r.mcharge + r.stprice + r.dmdamt + r.wastage * r.rate);
  if(r.amount<0) r.amount=0;
  // Auto-calc smcharge & salesamt from mperc
  if(r.mperc>0){
    r.smcharge = Math.round(r.amount * r.mperc / 100);
    r.salesamt = Math.round(r.amount * (100 + r.mperc) / 100);
  }
}

// ── Diamond sub-row calc ──────────────────────────────────────────────────
function calcDmdRow(i,j){
  const r=dmdItems[i][j];
  r.wgt = r2(r.carats / 5);
  r.amount = r2(r.carats * r.rate);
  // Auto-calc samount from parent mperc
  const mperc = items[i] ? (items[i].mperc||0) : 0;
  r.samount = Math.round(r.amount + (r.amount * mperc) / 100);
}

// Back-calc rate when amount is manually edited in dmd sub-row
function dmdAmtEdit(i,j,val){
  const r=dmdItems[i][j];
  r.amount = +val||0;
  if(r.carats>0) r.rate = r2(r.amount / r.carats);
  const mperc = items[i] ? (items[i].mperc||0) : 0;
  r.samount = Math.round(r.amount + (r.amount * mperc) / 100);
}

// Stone code lookup - fetch prate from items table
function dmdStcodeLookup(i,j){
  const code=dmdItems[i][j].stcode.trim(); if(!code)return;
  dmdItems[i][j].sttype=code; // copy stcode → sttype
  fetch(`${url('/api/diamond-purchase-return/item-lookup')}?code=${encodeURIComponent(code)}&gold_rate=0`)
    .then(r=>r.json()).then(d=>{
      if(d.error||!d.code)return;
      if(d.prate>0) dmdItems[i][j].rate=parseFloat(d.prate)||0;
      calcDmdRow(i,j); renderDmdRows(i);
    }).catch(()=>{});
}

// ── Render items table ──────────────────────────────────────────────────────
function renderItems(){
  const tb=$('itbody'); tb.innerHTML='';
  items.forEach((r,i)=>{
    const tr=document.createElement('tr');
    tr.className='irow'; if(i===selRowIdx) tr.classList.add('sel');
    tr.onclick=()=>{ selRowIdx=i; document.querySelectorAll('#itbody tr.irow').forEach((t,j)=>t.classList.toggle('sel',j===i)); };
    const hasDmd = dmdItems[i]&&dmdItems[i].length;
    tr.innerHTML=`
      <td><input class="num" value="${esc(r.barcode)}" oninput="items[${i}].barcode=this.value"
        onblur="if(this.value.trim())barcodeLookup(${i})" onkeydown="rKey(event,${i},0)"></td>
      <td id="it_code_${i}" style="display:flex;align-items:center;gap:1px;padding:1px 1px">
        <input class="num" value="${esc(r.code)}" style="flex:1;min-width:0"
          oninput="items[${i}].code=this.value"
          onblur="if(this.value.trim())itemLookup(${i})"
          onkeydown="if(event.key==='F2'){event.preventDefault();openItemSearch(${i});}else rKey(event,${i},1)">
        <button tabindex="-1" title="Search (F2)" onclick="openItemSearch(${i})"
          style="width:16px;height:20px;padding:0;border:none;background:transparent;cursor:pointer;color:#991b1b;font-size:12px;flex-shrink:0">&#128269;</button>
      </td>
      <td><input value="${esc(r.name)}" oninput="items[${i}].name=this.value" onkeydown="rKey(event,${i},2)"></td>
      <td><input class="num" value="${esc(r.purity)}" oninput="items[${i}].purity=this.value;purityRate(${i})" onkeydown="rKey(event,${i},3)"></td>
      <td><input class="num" value="${r.qty}"
        oninput="items[${i}].qty=+this.value||0;calcRow(${i});rfRow(${i});rfMperc(${i});updateFoot()" onkeydown="rKey(event,${i},4)"></td>
      <td><input class="num" value="${fmt3(r.weight)}"
        oninput="items[${i}].weight=+this.value||0;calcLesswgt(${i});calcRow(${i});rfRow(${i});rfMperc(${i});updateFoot()" onkeydown="rKey(event,${i},5)"></td>
      <td><input class="num" value="${fmt3(r.stwgt)}"
        oninput="parseStwgt(${i},this)" onblur="parseStwgt(${i},this)" onkeydown="rKey(event,${i},6)"></td>
      <td><input class="num" value="${fmt2(r.stprice)}"
        oninput="items[${i}].stprice=+this.value||0;calcRow(${i});rfRow(${i});rfMperc(${i});updateFoot()" onkeydown="rKey(event,${i},7)"></td>
      <td><input class="num" value="${fmt2(r.touch)}"
        oninput="items[${i}].touch=+this.value||0;calcLesswgt(${i});calcRow(${i});rfRow(${i});rfMperc(${i});updateFoot()" onkeydown="rKey(event,${i},8)"></td>
      <td><input class="num" value="${fmt2(r.lessperc)}"
        oninput="items[${i}].lessperc=+this.value||0;calcLesswgt(${i});calcRow(${i});rfRow(${i});rfMperc(${i});updateFoot()" onkeydown="rKey(event,${i},9)"></td>
      <td id="it_lesswgt_${i}"><input class="num" value="${fmt3(r.lesswgt)}" readonly style="background:transparent;color:#6b7280"></td>
      <td><input class="num" value="${fmt3(r.mud)}"
        oninput="items[${i}].mud=+this.value||0;calcLesswgt(${i});calcRow(${i});rfRow(${i});rfMperc(${i});updateFoot()" onkeydown="rKey(event,${i},11)"></td>
      <td id="it_netwgt_${i}"><input class="num" value="${fmt3(r.netwgt)}" readonly style="background:transparent;color:#6b7280"></td>
      <td><input class="num" value="${fmt2(r.rate)}"
        oninput="items[${i}].rate=+this.value||0;calcRow(${i});rfRow(${i});rfMperc(${i});updateFoot()" onkeydown="rKey(event,${i},13)"></td>
      <td><input class="num" value="${fmt2(r.wastage)}"
        oninput="items[${i}].wastage=+this.value||0;calcRow(${i});rfRow(${i});rfMperc(${i});updateFoot()" onkeydown="rKey(event,${i},14)"></td>
      <td><input class="num" value="${fmt2(r.mcharge)}"
        oninput="items[${i}].mcharge=+this.value||0;calcRow(${i});rfRow(${i});rfMperc(${i});updateFoot()" onkeydown="rKey(event,${i},15)"></td>
      <td id="it_dmdamt_${i}"><input class="num" value="${fmt2(r.dmdamt)}" readonly style="background:transparent;color:#991b1b;font-weight:600"></td>
      <td id="it_amount_${i}"><input class="num" value="${fmt2(r.amount)}" readonly style="background:transparent;font-weight:700"></td>
      <td><input value="${esc(r.stktype)}" oninput="items[${i}].stktype=this.value" onkeydown="rKey(event,${i},18)" style="width:100%"></td>
      <td><input class="num" value="${fmt2(r.svaperc)}"
        oninput="items[${i}].svaperc=+this.value||0" onkeydown="rKey(event,${i},19)"></td>
      <td><input class="num" value="${fmt2(r.salesamt)}"
        oninput="items[${i}].salesamt=+this.value||0;updateFoot()" onkeydown="rKey(event,${i},20)"></td>
      <td><input value="${esc(r.huid)}" style="text-transform:uppercase"
        oninput="items[${i}].huid=this.value.toUpperCase()" onkeydown="rKey(event,${i},21)"></td>
      <td><button tabindex="-1" title="Diamond/Stone Details" onclick="toggleDmd(${i})"
        id="dmdbtn_${i}"
        style="width:24px;height:20px;padding:0;border:1px solid #ef4444;
          background:${hasDmd?'#991b1b':'#fef2f2'};color:${hasDmd?'#fff':'#991b1b'};
          border-radius:4px;cursor:pointer;font-size:10px;font-weight:700">
        ${hasDmd?'['+dmdItems[i].length+']':'[+]'}</button></td>
    `;
    tb.appendChild(tr);

    // Diamond sub-grid row
    const dtr=document.createElement('tr');
    dtr.className='dmd-row'; dtr.id='dmdrow_'+i;
    dtr.style.display='none';
    dtr.innerHTML=`<td colspan="23">
      <div class="dmd-wrap">
        <div class="dmd-head">
          <span>Diamond / Stone Details - Row ${i+1}: ${esc(r.name||r.code)}</span>
          <button onclick="addDmdRow(${i})">+ Add</button>
          <button onclick="delDmdRow(${i})">Delete</button>
          <span style="flex:1"></span>
          <span>Pcs:<b id="dft_pcs_${i}">0</b></span>
          <span>Ct:<b id="dft_car_${i}">0.000</b></span>
          <span>Amt:<b id="dft_amt_${i}">0.00</b></span>
        </div>
        <table class="dmd-tbl">
          <thead><tr>
            <th style="width:70px">Stone Code</th>
            <th style="width:70px">Type</th>
            <th style="width:60px">Colour</th>
            <th style="width:60px">Cut/Clrty</th>
            <th style="width:60px">Set Type</th>
            <th style="width:36px">Pcs</th>
            <th style="width:60px">Carats</th>
            <th style="width:60px">Rate/Ct</th>
            <th style="width:70px">Purch.Val</th>
            <th style="width:70px">Sales Val</th>
          </tr></thead>
          <tbody id="dmdbody_${i}"></tbody>
          <tfoot><tr>
            <td colspan="5" style="text-align:right;font-weight:700">Total:</td>
            <td id="dft_pcs2_${i}">0</td>
            <td id="dft_car2_${i}">0.000</td>
            <td></td>
            <td id="dft_amt2_${i}">0.00</td>
            <td id="dft_smt2_${i}">0.00</td>
          </tr></tfoot>
        </table>
      </div>
    </td>`;
    tb.appendChild(dtr);

    if(dmdItems[i]&&dmdItems[i].length) renderDmdRows(i);
  });
  sv('cntItems',items.length);
}

function rfRow(i){
  const r=items[i];
  let el;
  el=$('it_lesswgt_'+i); if(el){const inp=el.querySelector('input'); if(inp) inp.value=fmt3(r.lesswgt);}
  el=$('it_netwgt_'+i);  if(el){const inp=el.querySelector('input'); if(inp) inp.value=fmt3(r.netwgt);}
  el=$('it_dmdamt_'+i);  if(el){const inp=el.querySelector('input'); if(inp) inp.value=fmt2(r.dmdamt);}
  el=$('it_amount_'+i);  if(el){const inp=el.querySelector('input'); if(inp) inp.value=fmt2(r.amount);}
}

function rfMperc(i){
  const r=items[i];
  const rows=document.querySelectorAll('#itbody tr.irow');
  if(!rows[i]) return;
  const c=rows[i].cells;
  // salesamt is column 20
  if(c[20]){const inp=c[20].querySelector('input'); if(inp) inp.value=fmt2(r.salesamt);}
}

function rKey(e,i,col){
  if(e.key==='Tab'&&!e.shiftKey){
    e.preventDefault();
    const editCols=[0,1,2,3,4,5,6,7,8,9,11,13,14,15,18,19,20,21];
    const cur=editCols.indexOf(col), next=editCols[cur+1];
    if(next!==undefined){
      const rows=document.querySelectorAll('#itbody tr.irow');
      if(rows[i]) { const inp=rows[i].cells[next].querySelector('input'); if(inp) inp.focus(); }
      return;
    }
    if(i<items.length-1){
      const rows=document.querySelectorAll('#itbody tr.irow');
      if(rows[i+1]) rows[i+1].cells[0].querySelector('input').focus();
    } else addRow();
  }
}

// ── Diamond sub-grid functions ──────────────────────────────────────────────
function toggleDmd(i){
  const drow=$('dmdrow_'+i); if(!drow) return;
  const isVis=drow.style.display!=='none';
  if(isVis){ drow.style.display='none'; return; }
  drow.style.display='';
  if(!dmdItems[i]) dmdItems[i]=[];
  if(!dmdItems[i].length) addDmdRow(i);
  else renderDmdRows(i);
}

function renderDmdRows(i){
  const tb=$('dmdbody_'+i); if(!tb) return;
  tb.innerHTML='';
  (dmdItems[i]||[]).forEach((r,j)=>{
    const tr=document.createElement('tr');
    tr.innerHTML=`
      <td><input value="${esc(r.stcode)}" oninput="dmdItems[${i}][${j}].stcode=this.value"
        onblur="if(this.value.trim())dmdStcodeLookup(${i},${j})"></td>
      <td><input value="${esc(r.sttype)}" oninput="dmdItems[${i}][${j}].sttype=this.value"></td>
      <td><input value="${esc(r.stcolor)}" oninput="dmdItems[${i}][${j}].stcolor=this.value"></td>
      <td><input value="${esc(r.stcut)}" oninput="dmdItems[${i}][${j}].stcut=this.value"></td>
      <td><input value="${esc(r.stsettype)}" oninput="dmdItems[${i}][${j}].stsettype=this.value"></td>
      <td><input class="num" value="${r.pcs}" oninput="dmdItems[${i}][${j}].pcs=+this.value||0;updateDmdFoot(${i})"></td>
      <td><input class="num" value="${fmt3(r.carats)}"
        oninput="dmdItems[${i}][${j}].carats=+this.value||0;calcDmdRow(${i},${j});rfDmdRow(${i},${j});updateDmdFoot(${i})"></td>
      <td><input class="num" value="${fmt2(r.rate)}"
        oninput="dmdItems[${i}][${j}].rate=+this.value||0;calcDmdRow(${i},${j});rfDmdRow(${i},${j});updateDmdFoot(${i})"></td>
      <td><input class="num" value="${fmt2(r.amount)}"
        oninput="dmdAmtEdit(${i},${j},this.value);rfDmdRow(${i},${j});updateDmdFoot(${i})" style="font-weight:600"></td>
      <td><input class="num" value="${fmt2(r.samount)}"
        oninput="dmdItems[${i}][${j}].samount=+this.value||0;updateDmdFoot(${i})"></td>
    `;
    tb.appendChild(tr);
  });
  updateDmdFoot(i);
}

function rfDmdRow(i,j){
  const tb=$('dmdbody_'+i); if(!tb||!tb.rows[j]) return;
  tb.rows[j].cells[8].querySelector('input').value=fmt2(dmdItems[i][j].amount);
  tb.rows[j].cells[9].querySelector('input').value=fmt2(dmdItems[i][j].samount);
}

function updateDmdFoot(i){
  let pcs=0,carats=0,amt=0,smt=0;
  (dmdItems[i]||[]).forEach(r=>{
    pcs+=r.pcs||0; carats+=r.carats||0; amt+=r.amount||0; smt+=r.samount||0;
  });
  const totAmt=r2(amt);
  sv('dft_pcs_'+i,pcs); sv('dft_car_'+i,fmt3(carats)); sv('dft_amt_'+i,fmt2(totAmt));
  sv('dft_pcs2_'+i,pcs); sv('dft_car2_'+i,fmt3(carats));
  sv('dft_amt2_'+i,fmt2(totAmt)); sv('dft_smt2_'+i,fmt2(smt));
  // Push dmdamt back to main item
  if(items[i]!==undefined){
    items[i].dmdamt=totAmt;
    calcRow(i); rfRow(i); updateFoot();
  }
  // Update button badge
  const btn=$('dmdbtn_'+i);
  if(btn){
    const cnt=(dmdItems[i]||[]).length;
    btn.textContent=cnt?'['+cnt+']':'[+]';
    btn.style.background=cnt?'#991b1b':'#fef2f2';
    btn.style.color=cnt?'#fff':'#991b1b';
  }
}

function addDmdRow(i){
  if(!dmdItems[i]) dmdItems[i]=[];
  dmdItems[i].push(newDmdRow());
  renderDmdRows(i);
  const tb=$('dmdbody_'+i);
  if(tb&&tb.rows.length) tb.rows[tb.rows.length-1].cells[0].querySelector('input').focus();
}

function delDmdRow(i){
  if(!dmdItems[i]||!dmdItems[i].length) return;
  dmdItems[i].pop();
  renderDmdRows(i);
}

// ── Footer totals ───────────────────────────────────────────────────────────
function updateFoot(){
  let qty=0,wgt=0,stwgt=0,stprice=0,netwgt=0,goldamt=0,mc=0,dmdamt=0,salesamt=0,amt=0;
  items.forEach(r=>{
    if(!r.code||(+(r.weight||0)+ +(r.qty||0)===0)) return;
    qty+=r.qty||0; wgt+=r.weight; stwgt+=r.stwgt; stprice+=r.stprice;
    netwgt+=r.netwgt; goldamt+=r.goldamt; mc+=r.mcharge; dmdamt+=r.dmdamt;
    salesamt+=r.salesamt||0; amt+=r.amount;
  });
  sv('ftQty',qty); sv('ftWgt',fmt3(wgt)); sv('ftStwgt',fmt3(stwgt));
  sv('ftStprice',fmt2(stprice)); sv('ftNetwgt',fmt3(netwgt));
  sv('ftGoldamt',fmt2(goldamt)); sv('ftMc',fmt2(mc));
  sv('ftDmdamt',fmt2(dmdamt)); sv('ftSalesamt',fmt2(salesamt)); sv('ftAmt',fmt2(amt));
  sv('fBillTotal',fmt2(amt));
  triggerRecalc();
}

// ── Add / Delete ─────────────────────────────────────────────────────────────
function addRow(){
  const row=newItemRow();
  if(nextBcode>0){ row.barcode=String(nextBcode); nextBcode++; }
  items.push(row); dmdItems.push([]);
  selRowIdx=items.length-1; renderItems();
  try{$('itbody').querySelectorAll('tr.irow')[items.length-1].cells[0].querySelector('input').focus();}catch(e){}
}
function delRow(){
  if(!items.length) return;
  const idx=selRowIdx>=0?selRowIdx:items.length-1;
  items.splice(idx,1); dmdItems.splice(idx,1);
  selRowIdx=Math.min(idx,items.length-1);
  renderItems(); updateFoot();
}

// ── Exchange (Old Gold) ─────────────────────────────────────────────────────
function newExchRow(){
  return {code:'',name:'',qty:0,weight:0,lessperc:0,lesswgt:0,rate:DPR_CONFIG.rates.gold||0,stprice:0,amount:0,stktype:''};
}
function calcExchRow(i){
  const r=exchItems[i];
  r.lesswgt=r3(r.weight*r.lessperc/100);
  const nwgt=r3(r.weight-r.lesswgt);
  r.amount=r2(nwgt*r.rate/100)+r.stprice;
  if(r.amount<0) r.amount=0;
}
function renderExchItems(){
  const tb=$('echbody'); tb.innerHTML='';
  exchItems.forEach((r,i)=>{
    const tr=document.createElement('tr');
    if(i===exchSelIdx) tr.className='sel';
    tr.onclick=()=>{exchSelIdx=i;};
    tr.innerHTML=`
      <td><input value="${esc(r.code)}" oninput="exchItems[${i}].code=this.value" onblur="exchItemLookup(${i})"></td>
      <td><input value="${esc(r.name)}" oninput="exchItems[${i}].name=this.value"></td>
      <td><input class="num" value="${r.qty}" oninput="exchItems[${i}].qty=+this.value||0"></td>
      <td><input class="num" value="${fmt3(r.weight)}" oninput="exchItems[${i}].weight=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt2(r.lessperc)}" oninput="exchItems[${i}].lessperc=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt3(r.lesswgt)}" readonly style="background:transparent;color:#6b7280"></td>
      <td><input class="num" value="${fmt2(r.rate)}" oninput="exchItems[${i}].rate=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt2(r.stprice)}" oninput="exchItems[${i}].stprice=+this.value||0;calcExchRow(${i});rfExchRow(${i});updateExchFoot()"></td>
      <td><input class="num" value="${fmt2(r.amount)}" readonly style="background:transparent;font-weight:600"></td>
    `;
    tb.appendChild(tr);
  });
}
function rfExchRow(i){const r=exchItems[i],tr=$('echbody').rows[i]; if(!tr) return; tr.cells[5].querySelector('input').value=fmt3(r.lesswgt); tr.cells[8].querySelector('input').value=fmt2(r.amount);}
function updateExchFoot(){
  let amt=0; exchItems.forEach(r=>{amt+=r.amount||0;});
  sv('eftAmt',fmt2(amt)); sv('fExchAmt',amt.toFixed(2));
  sv('fExchAmtDisplay',fmt2(amt)); sv('fExchAmtBot',fmt2(amt));
  triggerRecalc();
}
function addExchRow(){exchItems.push(newExchRow());exchSelIdx=exchItems.length-1;renderExchItems();const tr=$('echbody').rows[exchItems.length-1];if(tr)tr.cells[0].querySelector('input').focus();}
function delExchRow(){if(!exchItems.length)return;const idx=exchSelIdx>=0?exchSelIdx:exchItems.length-1;exchItems.splice(idx,1);exchSelIdx=Math.min(idx,exchItems.length-1);renderExchItems();updateExchFoot();}
function toggleExch(){$('exchModal').classList.add('active');if(!exchItems.length) addExchRow();}
function closeExchModal(){$('exchModal').classList.remove('active');}
function exchItemLookup(i){
  const code=exchItems[i].code.trim();if(!code)return;
  const rate=exchItems[i].rate||DPR_CONFIG.rates.gold;
  fetch(`${url('/api/diamond-purchase-return/item-lookup')}?code=${encodeURIComponent(code)}&gold_rate=${rate}`)
    .then(r=>r.json()).then(d=>{
      if(d.error||!d.code)return;
      exchItems[i].name=d.name||''; exchItems[i].rate=parseFloat(d.rate)||rate;
      exchItems[i].stktype=d.stktype||'';
      calcExchRow(i);renderExchItems();updateExchFoot();
    }).catch(()=>{});
}

// ── Recalc (simplified for purchase return) ──────────────────────────────────
function triggerRecalc(){clearTimeout(recalcTimer);recalcTimer=setTimeout(doRecalc,150);}
function doRecalc(){
  const billTotal = gf('fBillTotal');
  const exchange  = gf('fExchAmt');
  const taxPerc   = gf('fTaxPerc');
  const external  = $('fExternal').checked;
  const others    = gf('fOthers');
  const paidAmt   = gf('fPaidAmt');
  const ob        = gf('fOB');

  // nettot = billtotal - exchange + tax (if not external, tax=0)
  let taxAmt = 0;
  if(!external && taxPerc > 0){
    taxAmt = r2((billTotal - exchange) * taxPerc / 100);
  }
  sv('fTaxAmt', fmt2(taxAmt));

  const nettot = r2(billTotal - exchange + taxAmt);
  sv('fNetTotal', fmt2(nettot));

  const balance = r2(nettot + others - paidAmt);
  sv('fBalance', fmt2(balance));

  // cb = ob - balance (purchase return reduces balance)
  const cb = r2(ob - balance);
  sv('fOBBot', fmt2(ob));
  sv('fCB', fmt2(cb));
}
function onPaidChange(){doRecalc();}
function onRateChange(){
  const rate=gf('fGoldRate');
  items.forEach((r,i)=>{if(!r.rate||r.rate===DPR_CONFIG.rates.gold){r.rate=rate;calcRow(i);rfRow(i);}});
  DPR_CONFIG.rates.gold=rate; updateFoot();
}

// ── Supplier ────────────────────────────────────────────────────────────────
function loadSupplierSelect(){
  fetch(url('/api/diamond-purchase-return/supplier-search?q='))
    .then(r=>r.json()).then(d=>{
      const sel=$('fSupSelect'); if(!sel) return;
      sel.innerHTML='<option value="">-- Select Supplier --</option>'
        +'<option value="__NEW__" style="color:#1a56db;font-weight:600">+ Create New Supplier</option>';
      (d.results||[]).forEach(s=>{
        const o=document.createElement('option');
        o.value=s.code; o.dataset.name=s.name||'';
        o.textContent=(s.name||'').trim()+(s.code?' ['+s.code+']':'');
        sel.appendChild(o);
      });
    }).catch(()=>{});
}
function onSupSelectChange(code){
  if(!code) return;
  if(code==='__NEW__'){setTimeout(()=>{$('fSupSelect').value='';},100);openCreateSupModal();return;}
  const sel=$('fSupSelect');
  const name=(sel.options[sel.selectedIndex].dataset.name)||'';
  sv('fSupCode',code);sv('fSupName',name);loadSupDet(code);
  setTimeout(()=>{sel.value='';},150);
}
let supTimer=null;
function onSupInput(v,by){clearTimeout(supTimer);supTimer=setTimeout(()=>supSearch(v),220);}
function supSearch(q){
  if(!q||q.length<1){hideSup();return;}
  fetch(url('/api/diamond-purchase-return/supplier-search?q=')+encodeURIComponent(q))
    .then(r=>r.json()).then(d=>{
      const drop=$('supDrop'); drop.innerHTML='';
      (d.results||[]).forEach(s=>{
        const div=document.createElement('div');div.className='s-row';
        div.innerHTML=`<span class="s-code">${s.code}</span>${s.name}`;
        div.onclick=()=>selectSup(s.code,s.name);
        drop.appendChild(div);
      });
      drop.style.display=drop.children.length?'block':'none';
    }).catch(()=>{});
}
function hideSup(){$('supDrop').style.display='none';}
function selectSup(code,name){sv('fSupCode',code);sv('fSupName',name);hideSup();loadSupDet(code);}
function onSupKey(e){if(e.key==='Enter'||e.key==='Tab'){hideSup();loadSupDet(gv('fSupCode').trim());}}
function onSupNameKey(e){if(e.key==='Enter')supSearch(gv('fSupName').trim());}
function loadSupDet(code){
  if(!code)return;
  fetch(url('/api/diamond-purchase-return/supplier-details?code=')+encodeURIComponent(code))
    .then(r=>r.json()).then(d=>{
      if(d.error)return;
      sv('fSupCode',d.code||'');sv('fSupName',d.name||'');
      sv('fAddress',d.address||'');sv('fMobile',d.mobile||'');
      sv('fPan',d.pan||'');sv('fOB',fmt2(d.old_balance||0));sv('fOBBot',fmt2(d.old_balance||0));sv('fCB',fmt2(d.old_balance||0));
    }).catch(()=>{});
}
function openSupModal(){sv('supModalQ','');$('supModalBody').innerHTML='';$('supModal').classList.add('active');$('supModalQ').focus();}
function closeSupModal(){$('supModal').classList.remove('active');}
let supMTimer=null;
function supModalSearch(q){
  clearTimeout(supMTimer);if(!q)return;
  supMTimer=setTimeout(()=>{
    fetch(url('/api/diamond-purchase-return/supplier-search?q=')+encodeURIComponent(q))
      .then(r=>r.json()).then(d=>{
        const tb=$('supModalBody');tb.innerHTML='';
        (d.results||[]).forEach(s=>{
          const tr=document.createElement('tr');
          tr.innerHTML=`<td>${s.code}</td><td>${s.name}</td><td>${s.mobile||''}</td>`;
          tr.onclick=()=>{selectSup(s.code,s.name);closeSupModal();};
          tb.appendChild(tr);
        });
      }).catch(()=>{});
  },250);
}

// ── Item lookup ─────────────────────────────────────────────────────────────
function itemLookup(i){
  const code=items[i].code.trim();if(!code)return;
  const rate=items[i].rate||DPR_CONFIG.rates.gold;
  fetch(`${url('/api/diamond-purchase-return/item-lookup')}?code=${encodeURIComponent(code)}&gold_rate=${rate}`)
    .then(r=>r.json()).then(d=>{
      if(d.error||!d.code)return;
      items[i].name=d.name||'';
      const pur=d.iqtype||d.purity||'';
      items[i].purity=pur; items[i].iqtype=pur;
      if(parseFloat(d.touch)>0) items[i].touch=parseFloat(d.touch);
      // 18K purity → use 18K gold rate
      if(pur.substring(0,2)==='18' && DPR_CONFIG.rates.gold18>0){
        items[i].rate=DPR_CONFIG.rates.gold18;
      } else {
        items[i].rate=parseFloat(d.rate)||rate;
      }
      items[i].stktype=d.stktype||''; items[i].stkinnos=d.stkinnos||'N';
      if((+d.defqty||0)>0 && !items[i].qty) items[i].qty=+d.defqty;
      calcLesswgt(i);calcRow(i);renderItems();updateFoot();
      try{document.querySelectorAll('#itbody tr.irow')[i].cells[13].querySelector('input').focus();}catch(e){}
    }).catch(()=>{});
}

// ── Barcode Lookup ──────────────────────────────────────────────────────────
function barcodeLookup(i){
  const bc=items[i].barcode.toString().trim(); if(!bc)return;
  fetch(`${url('/api/diamond-purchase-return/barcode-lookup')}?bcode=${encodeURIComponent(bc)}`)
    .then(r=>r.json()).then(d=>{
      if(!d.ok)return;
      // Auto-fill item fields from barcode table
      if(d.icode && !items[i].code) { items[i].code=d.icode; itemLookup(i); }
      if(d.weight>0 && !items[i].weight) items[i].weight=d.weight;
      if(d.stweight>0 && !items[i].stwgt) items[i].stwgt=d.stweight;
      if(d.stprice>0 && !items[i].stprice) items[i].stprice=d.stprice;
      if(d.mc>0 && !items[i].mcharge) items[i].mcharge=d.mc;
      if(d.wastage>0 && !items[i].wastage) items[i].wastage=d.wastage;
      if(d.dmdamt>0 && !items[i].dmdamt) items[i].dmdamt=d.dmdamt;
      if(d.rate>0 && !items[i].rate) items[i].rate=d.rate;
      items[i].svaperc=d.vap||0;
      items[i].salesamt=d.tamt||0;
      items[i].huid=d.huid||'';
      items[i].stkinnos=d.stkinnos||items[i].stkinnos;
      calcRow(i); renderItems(); updateFoot();
    }).catch(()=>{});
}

// ── Item Search popup ─────────────────────────────────────────────────────────
let _itemSrchRowIdx=0, _itemSrchAll=[], _itemSrchFil=[], _itemSrchSel=-1;
function openItemSearch(rowIdx){
  _itemSrchRowIdx=rowIdx;_itemSrchSel=-1;
  $('itemSearchModal').classList.add('active');
  const q=(items[rowIdx]&&items[rowIdx].code)?items[rowIdx].code:'';
  $('itemSrchQ').value=q;
  if(!_itemSrchAll.length){
    fetch(url('/api/diamond-purchase-return/item-search?q='))
      .then(r=>r.json()).then(d=>{_itemSrchAll=d.results||[];itemSrchFilter(q);}).catch(()=>{});
  } else itemSrchFilter(q);
  setTimeout(()=>{$('itemSrchQ').focus();$('itemSrchQ').select();},80);
}
function closeItemSearch(){$('itemSearchModal').classList.remove('active');try{$('it_code_'+_itemSrchRowIdx).querySelector('input').focus();}catch(e){}}
function itemSrchFilter(q){
  q=(q||'').trim().toLowerCase();
  _itemSrchFil=q?_itemSrchAll.filter(r=>(r.code||'').toLowerCase().includes(q)||(r.name||'').toLowerCase().includes(q)||(r.purity||'').toLowerCase().includes(q)):_itemSrchAll;
  _itemSrchSel=_itemSrchFil.length?0:-1;renderItemSrch();
}
function renderItemSrch(){
  const tb=$('itemSrchBody');
  $('itemSrchCount').textContent=_itemSrchFil.length+' item(s)';
  if(!_itemSrchFil.length){tb.innerHTML='<tr><td colspan="7" style="padding:20px;text-align:center;color:#9ca3af">No items found</td></tr>';return;}
  tb.innerHTML=_itemSrchFil.map((r,i)=>`
    <tr data-idx="${i}" ondblclick="itemSrchPick(${i})" onclick="itemSrchHighlight(${i})"
      style="cursor:pointer;background:${i===_itemSrchSel?'#fecaca':''}">
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;font-weight:600;white-space:nowrap">${esc(r.code)}</td>
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9">${esc(r.name)}</td>
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9">${esc(r.purity)}</td>
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:center">${esc(r.itype)}</td>
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:right">${fmt2(r.touch)}</td>
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:right">${r.qty}</td>
      <td style="padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:right">${fmt3(r.weight)}</td>
    </tr>`).join('');
  if(_itemSrchSel>=0){const row=tb.querySelector(`tr[data-idx="${_itemSrchSel}"]`);if(row)row.scrollIntoView({block:'nearest'});}
}
function itemSrchHighlight(i){_itemSrchSel=i;$('itemSrchBody').querySelectorAll('tr').forEach((tr,j)=>{tr.style.background=j===i?'#fecaca':'';});}
function itemSrchPick(i){const r=_itemSrchFil[i];if(!r)return;const ri=_itemSrchRowIdx;items[ri].code=r.code;items[ri].name=r.name;items[ri].purity=r.purity;items[ri].touch=r.touch;items[ri].stktype=r.stktype;closeItemSearch();itemLookup(ri);}
function itemSrchKey(e){
  const max=_itemSrchFil.length;
  if(e.key==='ArrowDown'){_itemSrchSel=Math.min(_itemSrchSel+1,max-1);renderItemSrch();e.preventDefault();}
  else if(e.key==='ArrowUp'){_itemSrchSel=Math.max(_itemSrchSel-1,0);renderItemSrch();e.preventDefault();}
  else if(e.key==='Enter'){if(_itemSrchSel>=0)itemSrchPick(_itemSrchSel);e.preventDefault();}
  else if(e.key==='Escape'){closeItemSearch();e.preventDefault();}
}

// ── Save ────────────────────────────────────────────────────────────────────
function saveBill(){
  if(!gv('fSupCode').trim()){alert('Please enter a supplier.');return;}
  if(!items.filter(r=>r.code).length){alert('Please add at least one item.');return;}
  $('btnSave').disabled=true;
  const validItems=items.filter(r=>r.code);
  const payload={
    mode:DPR_CONFIG.mode||'bill',
    slno:currentSlno,
    doc_no:gv('fDocNo'), supp_bill_no:gv('fBillNo'),
    bill_date:gv('fDate'),
    sup_code:gv('fSupCode'), sup_name:gv('fSupName'),
    address:gv('fAddress'), pan:gv('fPan'), mobile:gv('fMobile'),
    ob:gf('fOB'),
    sm_code:gv('fSalesMan'), gold_rate:gf('fGoldRate'),
    bill_total:gf('fBillTotal'),
    tax_perc:gf('fTaxPerc'), tax_amt:gf('fTaxAmt'),
    others:gf('fOthers'), paid_amt:gf('fPaidAmt'),
    net_total:gf('fNetTotal'), balance:gf('fBalance'),
    btype:gv('fBType'),
    note:gv('fNote'),
    interstate:$('fInterstate').checked?'Y':'N',
    tax_external:$('fExternal').checked?'Y':'N',
    dont_add_dmd_amt:$('fDontAddDmdAmt').checked?'Y':'N',
    dont_add_dmd_wgt:$('fDontAddDmdWgt').checked?'Y':'N',
    keep_ac_wgt:$('fKeepAcWgt').checked?'Y':'N',
    items:validItems.map(r=>({
      code:r.code, name:r.name, barcode:r.barcode, purity:r.purity, rate:r.rate,
      qty:r.qty, weight:r.weight, stwgt:r.stwgt, stprice:r.stprice,
      touch:r.touch, lessperc:r.lessperc, lesswgt:r.lesswgt, mud:r.mud,
      mcharge:r.mcharge, amount:r.amount,
      stktype:r.stktype, stkinnos:r.stkinnos, fr:r.fr,
      wastage:r.wastage, mperc:r.mperc, dmdamt:r.dmdamt,
      dmdwgt:(dmdItems[items.indexOf(r)]||[]).reduce((s,d)=>s+(+d.carats||0),0),
      svaperc:r.svaperc, sstprice:r.sstprice, smcharge:r.smcharge,
      salesamt:r.salesamt, huid:r.huid,
    })),
    dmd_details:validItems.map(r=>{
      const idx=items.indexOf(r);
      return (dmdItems[idx]||[]).filter(d=>d.pcs>0||d.carats>0);
    }),
    exchange_items:exchItems.filter(r=>r.code),
  };
  fetch(url('/api/diamond-purchase-return/save'),{
    method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()},
    body:JSON.stringify(payload)
  }).then(r=>r.json()).then(d=>{
    $('btnSave').disabled=false;
    if(!d.ok){alert('Error: '+(d.message||d.error||'Save failed'));return;}
    alert('Diamond purchase return saved: '+(d.doc_no||''));
    resetForm();loadNextBillNo();
  }).catch(e=>{$('btnSave').disabled=false;alert('Save failed: '+e);});
}

// ── Load bill ────────────────────────────────────────────────────────────────
function loadBill(billNo){
  if(!billNo)return;
  fetch(url('/api/diamond-purchase-return/get?bill_no=')+encodeURIComponent(billNo))
    .then(r=>r.json()).then(d=>{if(d.error||!d.doc_no){alert(d.error||d.message||'Not found');return;} applyBill(d);})
    .catch(()=>{});
}
function applyBill(d){
  currentSlno=d.slno||0;
  sv('fDocNo',d.doc_no||'');sv('fBillNo',d.bill_no||'');
  sv('fDate',d.date||'');
  sv('fSupCode',d.sup_code||'');sv('fSupName',d.sup_name||'');
  sv('fAddress',d.address||'');sv('fPan',d.pan||'');sv('fMobile',d.mobile||'');
  sv('fOB',fmt2(d.ob||0));sv('fOBBot',fmt2(d.ob||0));sv('fSalesMan',d.salesman||'');
  sv('fBillTotal',fmt2(d.bill_total||0));
  sv('fTaxPerc',d.tax_perc||0);
  sv('fTaxAmt',fmt2(d.tax_amt||0));
  sv('fOthers',fmt2(d.others||0));sv('fPaidAmt',fmt2(d.paid_amt||0));
  sv('fNetTotal',fmt2(d.net_total||0));sv('fBalance',fmt2(d.balance||0));
  sv('fBType',d.btype||'Dmd');
  sv('fNote',d.note||'');
  if(d.interstate) $('fInterstate').checked=d.interstate==='Y';
  if(d.external)   $('fExternal').checked=d.external==='Y';
  if(d.dont_add_dmd_amt) $('fDontAddDmdAmt').checked=d.dont_add_dmd_amt==='Y';
  if(d.dont_add_dmd_wgt) $('fDontAddDmdWgt').checked=d.dont_add_dmd_wgt==='Y';
  if(d.keep_ac_wgt) $('fKeepAcWgt').checked=d.keep_ac_wgt==='Y';

  items=(d.items||[]).map(r=>({
    code:r.code||'',name:r.name||'',barcode:r.barcode||'',purity:r.iqtype||'',
    rate:+r.rate||0,qty:+r.qty||0,weight:+r.weight||0,
    stwgt:+r.stwgt||0,stprice:+r.stprice||0,touch:+r.touch||0,
    lessperc:+r.lessperc||0,lesswgt:+r.lesswgt||0,mud:+r.mud||0,
    netwgt:r3((+r.weight||0)-(+r.stwgt||0)-(+r.lesswgt||0)-(+r.mud||0)),
    goldamt:0,mcharge:+r.mcharge||0,
    wastage:+r.wastage||0,mperc:+r.mperc||0,stkinnos:r.stkinnos||'N',
    svaperc:+r.svaperc||0,dmdamt:+r.dmdamt||0,smcharge:+r.smcharge||0,
    sstprice:+r.sstprice||0,salesamt:+r.salesamt||0,huid:r.huid||'',
    amount:+r.amount||0,stktype:r.stktype||'',fr:r.fr||'N'
  }));
  // Compute goldamt for each
  items.forEach((r,i)=>{
    if((r.stkinnos||'').toUpperCase()==='Y') r.goldamt=r2(r.qty*r.rate);
    else r.goldamt=r2(r.netwgt*r.rate);
  });

  // Load diamond sub-rows
  dmdItems=items.map((r,i)=>{
    const rows=(d.items[i]||{}).dmd_rows||[];
    return rows.map(dr=>({
      stcode:dr.stcode||'',sttype:dr.sttype||'',stcolor:dr.stcolor||'',
      stcut:dr.stcut||'',stsettype:dr.stsettype||'',
      pcs:+dr.pcs||0,carats:+dr.carats||0,rate:+dr.rate||0,
      amount:+dr.amount||0,samount:+dr.samount||0,
    }));
  });

  exchItems=(d.exch_items||[]).map(r=>({
    code:r.code||'',name:r.name||'',qty:+r.qty||0,
    weight:+r.weight||0,lessperc:+r.lessperc||0,lesswgt:+r.lesswgt||0,
    rate:+r.rate||0,stprice:+r.stprice||0,amount:+r.amount||0,stktype:r.stktype||''
  }));

  renderItems();updateFoot();
  renderExchItems();updateExchFoot();
  sv('fCB',fmt2(d.cb||0));
}

// ── Navigation ───────────────────────────────────────────────────────────────
function prevBill(){
  fetch(url('/api/diamond-purchase-return/prev?doc_no=')+encodeURIComponent(gv('fDocNo')))
    .then(r=>r.json()).then(d=>{
      if(d.ok&&d.bill_no) loadBill(d.bill_no);
      else alert(d.message||'No previous bill');
    }).catch(()=>{});
}
function nextBill(){
  fetch(url('/api/diamond-purchase-return/next?doc_no=')+encodeURIComponent(gv('fDocNo')))
    .then(r=>r.json()).then(d=>{
      if(d.ok&&d.bill_no) loadBill(d.bill_no);
      else alert(d.message||'No next bill');
    }).catch(()=>{});
}

// ── Search ───────────────────────────────────────────────────────────────────
function openBillSearch(){sv('billSearchQ','');$('billSrchBody').innerHTML='';$('billModal').classList.add('active');$('billSearchQ').focus();}
function closeBillSearch(){$('billModal').classList.remove('active');}
let bsTimer=null;
function doBillSearch(q){
  clearTimeout(bsTimer);if(!q)return;
  bsTimer=setTimeout(()=>{
    fetch(url('/api/diamond-purchase-return/search?q=')+encodeURIComponent(q))
      .then(r=>r.json()).then(d=>{
        const tb=$('billSrchBody');tb.innerHTML='';
        (d.results||[]).forEach(b=>{
          const tr=document.createElement('tr');
          tr.innerHTML=`<td>${b.doc_no}</td><td>${b.date}</td><td>${b.sup_name}</td><td style="text-align:right">${fmt2(b.net_total)}</td>`;
          tr.onclick=()=>{loadBill(b.doc_no);closeBillSearch();};
          tb.appendChild(tr);
        });
      }).catch(()=>{});
  },300);
}

// ── Create Supplier ─────────────────────────────────────────────────────────
let _createSupWin=null;
function openCreateSupModal(){
  const w=1100,h=700;const left=Math.round(screen.width/2-w/2);const top=Math.round(screen.height/2-h/2);
  if(_createSupWin&&!_createSupWin.closed){_createSupWin.focus();return;}
  _createSupWin=window.open('{{ url("/customer") }}?type=S&popup=1','createSupplier',`width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=yes`);
}
window.addEventListener('message',function(e){
  if(!e.data||e.data.type!=='goldapp:customer-created')return;
  const code=e.data.code||'',name=e.data.name||'';
  if(!code)return;sv('fSupCode',code);sv('fSupName',name);loadSupDet(code);loadSupplierSelect();
});

// ── Cancel ───────────────────────────────────────────────────────────────────
function openCancelModal(){
  if(!gv('fDocNo')){alert('Load a bill first');return;}
  sv('cancelBillNo',gv('fDocNo'));sv('cancelReason','');
  $('cancelModal').classList.add('active');
}
function confirmCancel(){
  fetch(url('/api/diamond-purchase-return/cancel'),{
    method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()},
    body:JSON.stringify({doc_no:gv('fDocNo'),reason:gv('cancelReason')})
  }).then(r=>r.json()).then(d=>{
    $('cancelModal').classList.remove('active');
    if(d.error){alert('Error: '+d.error);return;}
    alert('Bill cancelled');resetForm();loadNextBillNo();
  }).catch(()=>{});
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function newBill(){resetForm();loadNextBillNo();}
function resetForm(){
  currentSlno=0;selRowIdx=-1;
  ['fDocNo','fBillNo','fSupCode','fSupName','fAddress','fPan','fMobile','fNote'].forEach(id=>sv(id,''));
  sv('fDate',todayDMY());sv('fOB','.00');sv('fOBBot','.00');sv('fCB','.00');
  ['fBillTotal','fExchAmtBot','fTaxAmt',
   'fOthers','fPaidAmt','fNetTotal','fBalance'].forEach(id=>sv(id,'.00'));
  ['fTaxPerc'].forEach(id=>sv(id,'0'));
  ['fInterstate','fExternal','fDontAddDmdAmt','fDontAddDmdWgt','fKeepAcWgt'].forEach(id=>$(id)&&($(id).checked=false));
  items=[];dmdItems=[];exchItems=[];selRowIdx=-1;exchSelIdx=-1;
  renderItems();updateFoot();
  // Re-fetch next barcode for new bill
  fetch(url('/api/diamond-purchase-return/next-barcode'))
    .then(r=>r.json()).then(d=>{ if(d.ok) nextBcode=d.bcode; })
    .catch(()=>{})
    .finally(()=>{ addRow(); });
  renderExchItems();updateExchFoot();
}
function todayDMY(){const d=new Date();const p=n=>String(n).padStart(2,'0');return p(d.getDate())+'/'+p(d.getMonth()+1)+'/'+d.getFullYear();}

function loadNextBillNo(){
  fetch(url('/api/diamond-purchase-return/next-number'))
    .then(r=>r.json()).then(d=>{if(d.bill_no) sv('fDocNo',d.bill_no);}).catch(()=>{});
}

function doExit(){
  if(window.parent&&window.parent!==window) window.parent.postMessage({action:'closeModule'},'*');
  else window.history.back();
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded',()=>{
  loadNextBillNo(); loadSupplierSelect();
  // Fetch next barcode then add first row
  fetch(url('/api/diamond-purchase-return/next-barcode'))
    .then(r=>r.json()).then(d=>{ if(d.ok) nextBcode=d.bcode; })
    .catch(()=>{})
    .finally(()=>{ addRow(); });
  document.addEventListener('click',e=>{if(!e.target.closest('.sup-row')) hideSup();});
  if(DPR_CONFIG.mode==='cancel'||DPR_CONFIG.mode==='reprint') $('btnSave').style.display='none';
  const qs=new URLSearchParams(window.location.search);
  const docNo=qs.get('doc_no');
  if(docNo){
    loadBill(docNo);
    if(qs.get('autoprint')==='1') setTimeout(()=>window.print(),800);
  }
});
</script>
</body>
</html>
