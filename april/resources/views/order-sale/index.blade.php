<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('js/goldapp-common.js') }}"></script>
<title>{{ $title ?? 'Enter Order Sale Details' }}</title>
<script>
window.OS_CONFIG = {
  siteUrl:   @json(request()->root()),
  mode:      @json($mode ?? 'bill'),
  rates: {
    GRATE:  {{ $rates['GRATE']  ?? 0 }},
    SRATE:  {{ $rates['SRATE']  ?? 0 }},
    OGRATE: {{ $rates['OGRATE'] ?? 0 }},
    OSRATE: {{ $rates['OSRATE'] ?? 0 }},
    BULRATE: {{ $rates['BULRATE'] ?? 0 }},
    BULTOUCH: {{ $rates['BULTOUCH'] ?? 99.5 }},
    THRATE: {{ $rates['THRATE'] ?? 0 }},
    G18RATE: {{ $rates['G18RATE'] ?? 0 }},
  },
  exchItems:  @json($exchItems ?? []),
  billTypes:  @json($billTypes ?? []),
  counters:   @json($counters ?? []),
  salesmen:   @json($salesmen ?? []),
  agents:     @json($agents ?? []),
  approvedBy: @json($approvedBy ?? []),
  cashBanks:  @json($cashBanks ?? []),
  states:     @json($states ?? []),
  software:   @json($software ?? []),
};
</script>
<style>
:root{
  --pri:#1e3a5f;
  --pri2:#2d5f9e;
  --accent:#3b82f6;
  --bg:#f0f4f9;
  --surface:#ffffff;
  --surf2:#f8fafc;
  --border:#d1dbe8;
  --border2:#b8c9df;
  --text:#1f2937;
  --muted:#64748b;
  --danger:#dc2626;
  --success:#16a34a;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:"Segoe UI",Tahoma,sans-serif;font-size:12px;color:var(--text);overflow:hidden;height:100vh;background:var(--bg)}
input,select,button{font-family:inherit;transition:border-color .15s,box-shadow .15s}
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:#f1f5f9}
::-webkit-scrollbar-thumb{background:#94a3b8;border-radius:3px}

/* Main Window */
.main-window{background:var(--bg);border:none;margin:0;overflow:hidden;position:relative;display:flex;flex-direction:column;height:100vh}

/* Title Bar */
.title-bar{background:linear-gradient(90deg,var(--pri),var(--pri2));color:#fff;padding:8px 14px;font-weight:700;font-size:13px;display:flex;align-items:center;gap:8px;flex-shrink:0}
.title-bar .icon{width:18px;height:18px;background:rgba(255,255,255,.25);border-radius:4px;border:1px solid rgba(255,255,255,.35)}

/* Message Modal */
.msg-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:12000}
.msg-modal.active{display:flex}
.msg-box{width:min(420px,calc(100vw - 24px));background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 20px 40px rgba(15,23,42,.3)}
.msg-box .head{background:linear-gradient(90deg,var(--pri),var(--pri2));color:#fff;font-weight:700;font-size:12px;padding:10px 14px}
.msg-box .body{padding:16px 14px;font-size:12.5px;color:var(--text);line-height:1.5;white-space:pre-wrap}
.msg-box .foot{padding:10px 14px;display:flex;justify-content:center;background:var(--surf2);border-top:1px solid var(--border)}
.msg-box .ok-btn{min-width:80px;height:30px;border:1.5px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-weight:600;cursor:pointer;font-size:12px}
.msg-box .ok-btn:hover{background:var(--bg);border-color:var(--accent);color:var(--accent)}

/* Top Section (3-col) */
.top-section{display:grid;grid-template-columns:1.25fr 1fr 1.2fr;gap:0 1px;background:var(--border);border-bottom:2px solid var(--border2);flex-shrink:0}
.top-section>.top-block{background:var(--surface);padding:8px 12px;display:flex;flex-direction:column;gap:5px;min-width:0}
.top-section .os-row{display:flex;align-items:center;gap:6px;min-height:27px}
.top-section .os-row label{font-weight:600;font-size:11px;min-width:74px;color:var(--muted);text-align:right;white-space:nowrap;flex-shrink:0}
.top-section .os-row input,
.top-section .os-row select{font-size:11.5px;padding:3px 8px;border:1.5px solid var(--border);background:var(--surface);height:27px;border-radius:6px;color:var(--text);outline:none;font-family:inherit}
.top-section .os-row input:focus,
.top-section .os-row select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,.18)}
.top-section .os-row input.readonly,
.top-section .os-row input[readonly]{background:var(--surf2);color:var(--muted)}
.top-section .os-row .sm{width:64px}
.top-section .os-row .md{width:104px}
.top-section .os-row .lg{width:144px}
.top-section .os-row .grow{flex:1;min-width:0}
.top-section .os-row.billno-row input.billno{flex:1;min-width:0}
.top-section .os-row.customer-row{position:relative}
.top-section .os-row.customer-row #fCustomerCode{width:86px}
.top-section .os-row.customer-row #fCustName{flex:1;min-width:0}
.top-section .os-row .nav-btns{display:inline-flex;gap:4px;margin-right:4px}
.top-section .os-row .nav-btns button,
.top-section .os-row .mini-btn{font-size:10px;padding:0 8px;border:1.5px solid var(--border);border-radius:6px;background:var(--surf2);cursor:pointer;color:var(--text);height:27px;font-weight:600}
.top-section .os-row .mini-btn:hover{background:var(--bg);border-color:var(--accent)}
.top-section .os-row .mini-btn.green{color:var(--success);border-color:#bbf7d0}
.top-section .os-row .inline-check{display:inline-flex;align-items:center;gap:5px;font-size:11px;color:var(--text);white-space:nowrap}
.top-section .os-row #fCo{background:#0f172a;color:#f9fafb;border-color:#334155}
.top-section .os-row.ob-row label.ob-label{min-width:24px}
.top-section .os-row.ob-row label.sm-label{min-width:54px;margin-left:6px}
.top-section .os-row.ob-row #fSalesman{flex:1;min-width:0}
.top-section .os-row.ob-row #fWs{width:16px;height:16px;flex:0 0 auto}

/* Items Table */
.table-wrap{flex:1;overflow:hidden;margin:0;border-bottom:1px solid var(--border);display:flex;flex-direction:column;background:var(--surface)}
table.items{width:100%;border-collapse:collapse;font-size:11px;table-layout:fixed}
table.items thead th{background:linear-gradient(180deg,var(--pri),#2a4a78);color:#fff;padding:7px 5px;border-right:1px solid rgba(255,255,255,.12);font-weight:600;font-size:10.5px;text-align:center;white-space:nowrap;position:sticky;top:0;z-index:2}
table.items tbody{display:block;overflow-y:auto;flex:1}
table.items thead,table.items tbody tr{display:table;width:100%;table-layout:fixed}
table.items tbody td{border-bottom:1px solid #e8eef7;border-right:1px solid #edf2f9;padding:1px 3px;text-align:center;height:24px;background:var(--surface);vertical-align:middle}
table.items tbody tr:nth-child(even) td{background:#f8fafc}
table.items tbody tr.sel td{background:#dbeafe;color:#1e40af;font-weight:600}
table.items tbody input{font-size:11px;border:none;background:transparent;text-align:center;width:100%;height:100%;color:var(--text);outline:none;font-family:inherit}
table.items tbody input:focus{background:#fef9c3;outline:2px solid #fbbf24;outline-offset:-1px}
table.items tbody input.num{text-align:right}

/* Table Footer */
.table-footer{display:flex;align-items:center;background:var(--surf2);padding:5px 10px;gap:8px;font-size:11px;font-weight:600;color:var(--text);border-top:1px solid var(--border);border-bottom:1px solid var(--border);flex-shrink:0;min-height:30px}
.table-footer button{background:var(--surface);border:1.5px solid var(--border);border-radius:6px;padding:2px 12px;font-size:11px;cursor:pointer;font-weight:600;color:var(--text);height:26px}
.table-footer button:hover{background:var(--bg);border-color:var(--accent)}
.tf-label{color:var(--muted);font-weight:600}
.tfv{text-align:right;font-variant-numeric:tabular-nums;min-width:52px;display:inline-block;color:var(--text)}
.tf-sep{border-left:1.5px solid var(--border);height:16px;margin:0 2px}

/* Info Bar */
.info-bar{display:flex;align-items:center;background:#eef7ff;padding:4px 12px;gap:10px;font-size:11px;font-weight:600;color:var(--pri);border-bottom:1px solid var(--border);flex-shrink:0;min-height:28px}

/* Bottom Wrap */
.bottom-wrap{display:flex;background:var(--surf2);border-top:1px solid var(--border);flex-shrink:0}
.bottom-section{flex:1;padding:8px;overflow:hidden}
.bot-row{display:flex;align-items:center;gap:4px;min-height:24px}
.bot-row label{font-weight:600;font-size:11px;color:var(--muted);white-space:nowrap;text-align:right}
.bot-row input,.bot-row select{font-size:11px;padding:2px 6px;border:1.5px solid var(--border);background:var(--surface);height:24px;color:var(--text);border-radius:5px;outline:none;font-family:inherit}
.bot-row input:focus,.bot-row select:focus{border-color:var(--accent);box-shadow:0 0 0 2px rgba(59,130,246,.15)}
.bot-row input.readonly{background:var(--surf2);color:var(--muted)}
.bot-row input.highlight{background:#fef9c3;font-weight:700;color:#92400e}

/* Summary Model (4-col grid) */
.summary-model{display:grid;grid-template-columns:repeat(4,minmax(220px,1fr));gap:8px}
.sm-col{border:1px solid var(--border);border-radius:10px;background:var(--surface);padding:8px 10px;display:flex;flex-direction:column;gap:5px;box-shadow:0 1px 3px rgba(30,58,95,.06)}
.sm-row{display:grid;grid-template-columns:86px 1fr auto;align-items:center;gap:6px}
.sm-row label{text-align:right;color:var(--muted);font-weight:600;font-size:11.5px}
.sm-row input,.sm-row select{width:100%;font-variant-numeric:tabular-nums;height:28px;border:1.5px solid var(--border);border-radius:6px;background:var(--surface);padding:2px 10px;color:var(--text);font-size:11.5px;outline:none;font-family:inherit}
.sm-row input:focus,.sm-row select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,.18)}
.sm-row input.readonly,.sm-row input[readonly]{background:var(--surf2);color:var(--muted)}
#fNetTotal{background:#fff7ed;border-color:#fdba74;color:#9a3412;font-weight:700}
.sm-row .pair{display:grid;grid-template-columns:44px 1fr;gap:6px;align-items:center}
.sm-row .pair-2{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.sm-inline{display:inline-flex;align-items:center;gap:5px;color:var(--text);font-weight:600;font-size:11.5px}
.sm-full{grid-column:1 / -1}
.sm-note{color:var(--muted);font-size:11px;font-weight:600}
.sm-checks{margin-top:6px;border:1px dashed var(--border);border-radius:8px;padding:8px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.sm-toggle{grid-template-columns:110px 1fr}
.sm-toggle label:first-child{text-align:right}
.sm-toggle .sm-inline{justify-self:start}

/* Action Panel */
.action-panel{display:flex;flex-direction:column;gap:6px;padding:10px 8px;border-left:1px solid var(--border);background:var(--surface);min-width:116px;flex-shrink:0}
.action-panel button{width:100%;height:34px;border:1.5px solid var(--border);border-radius:8px;background:var(--surf2);color:var(--text);font-size:11.5px;font-weight:600;cursor:pointer;font-family:inherit}
.action-panel button:hover{background:var(--bg);border-color:var(--accent);color:var(--accent)}
.action-panel button:active{transform:scale(.97)}
.action-panel button.highlight{background:linear-gradient(135deg,var(--pri),var(--pri2));border-color:var(--pri);color:#fff}
.action-panel button.highlight:hover{opacity:.9}

/* Modal Overlay / Panel */
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:10000}
.modal-overlay.active{display:flex}
.modal-panel{background:var(--surface);border-radius:12px;min-width:500px;max-width:95vw;max-height:88vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 16px 40px rgba(15,23,42,.3)}
.modal-panel .m-head{background:linear-gradient(90deg,var(--pri),var(--pri2));color:#fff;padding:10px 14px;font-weight:700;font-size:12.5px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.modal-panel .m-head .m-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:16px;cursor:pointer;padding:2px 8px;border-radius:5px;line-height:1}
.modal-panel .m-head .m-close:hover{background:rgba(255,255,255,.28)}
.modal-panel .m-body{padding:10px;overflow-y:auto;flex:1}
.modal-panel .m-foot{padding:8px 12px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;background:var(--surf2);flex-shrink:0}
.modal-panel .m-foot button{padding:0 18px;font-size:11.5px;font-weight:600;border:1.5px solid var(--border);border-radius:7px;background:var(--surface);cursor:pointer;height:30px;font-family:inherit}
.modal-panel .m-foot button:hover{background:var(--bg);border-color:var(--accent)}
.modal-panel .m-foot button.primary{background:linear-gradient(90deg,var(--pri),var(--pri2));border-color:var(--pri);color:#fff}
.modal-panel .m-foot button.primary:hover{opacity:.9}

/* Search List */
.search-list{max-height:300px;overflow-y:auto}
.search-list table{width:100%;border-collapse:collapse;font-size:11px}
.search-list table th{background:linear-gradient(180deg,var(--pri),#2a4a78);color:#fff;padding:6px 8px;text-align:left;font-size:10.5px;position:sticky;top:0;font-weight:600}
.search-list table td{padding:4px 8px;border-bottom:1px solid #e8eef7;cursor:pointer;color:var(--text)}
.search-list table tr:hover td{background:#dbeafe;color:#1e40af}

/* Exchange / SR Table */
.exch-table{table-layout:fixed;width:100%;border-collapse:collapse;font-size:11px}
.exch-table th{background:linear-gradient(180deg,var(--pri),#2a4a78);color:#fff;padding:7px 5px;font-size:10.5px;text-align:center;border-right:1px solid rgba(255,255,255,.1);font-weight:600;line-height:1.2}
.exch-table td{border-bottom:1px solid #e8eef7;border-right:1px solid #edf2f9;padding:2px 4px;text-align:center;height:30px;background:var(--surface);vertical-align:middle}
.exch-table input{height:24px;line-height:22px;padding:1px 5px;border:1.5px solid transparent;border-radius:5px;font-size:11px;background:transparent;width:100%;text-align:center;outline:none;font-family:inherit}
.exch-table input:focus{background:#fef9c3;border-color:#fbbf24}

/* Misc */
.btn-sm{font-size:11px;padding:0 12px;border:1.5px solid var(--border);border-radius:6px;background:var(--surf2);cursor:pointer;height:26px;color:var(--text);font-weight:600;font-family:inherit}
.btn-sm:hover{background:var(--bg);border-color:var(--accent);color:var(--accent)}
.inline-check{display:inline-flex;align-items:center;gap:5px;font-size:11px;color:var(--text);white-space:nowrap}
.secondary-sync-inline{display:flex;align-items:center;justify-content:center;gap:4px;padding:6px 8px;border:1px solid var(--border);background:var(--surf2);font-size:11px;font-weight:700;color:var(--text)}
.status-badge{font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;display:inline-block}
.status-badge.sold{background:#fee2e2;color:#dc2626}
.status-badge.blocked{background:#fef9c3;color:#92400e}
.status-badge.open{background:#dcfce7;color:#16a34a}
.gap-sm{width:8px;display:inline-block}

/* Responsive */
@media(max-width:1400px){.summary-model{grid-template-columns:repeat(2,minmax(200px,1fr))}}
@media(max-width:1100px){.top-section{grid-template-columns:1fr 1fr}.top-section>.top-block:last-child{grid-column:1/-1}}
@media(max-width:900px){.summary-model{grid-template-columns:1fr}.top-section{grid-template-columns:1fr}body{overflow:auto;height:auto}.main-window{height:auto}}
</style>
</head>
<body>
<div class="main-window" id="mainWindow">
  <div class="title-bar"><div class="icon"></div><span id="titleText">{{ $title ?? 'Enter Order Sale Details' }} - 1</span></div>

  <!-- ============ TOP SECTION ============ -->
  <div class="top-section">
    <div class="top-block">
      <div class="os-row billno-row">
        <label>Bill No</label>
        <input type="text" id="fBillNo" class="readonly billno" value="" readonly>
        <span class="inline-check"><input id="fCounterChk" type="checkbox" style="width:16px;height:16px;"><span>Counter</span></span>
        <select id="fCounter" style="width:80px">
          <option value="">--</option>
          @foreach($counters as $ct)
            <option value="{{ $ct['code'] }}">{{ $ct['code'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="os-row">
        <label>Rate/gm</label>
        <input type="text" id="fRatePerGm" class="sm" style="text-align:right" value="{{ $rates['GRATE'] ?? 0 }}">
        <button class="mini-btn" id="btnOrderDetails">Order Details</button>
      </div>
      <div class="os-row customer-row">
        <label>Customer</label>
        <select id="fCustomerCode"><option value="">--</option></select>
        <button class="mini-btn" id="btnCustSearch">...</button>
        <input type="text" id="fCustName" class="readonly grow" value="" readonly>
      </div>
      <div class="os-row">
        <label>Mob No</label>
        <input type="text" id="fMobile" class="md grow" value="">
      </div>
      <div class="os-row">
        <label>Address</label>
        <input type="text" id="fAddress" class="grow" value="">
      </div>
    </div>

    <div class="top-block">
      <div class="os-row">
        <label>Ord No.</label>
        <input type="text" id="fOrderNo" class="md" style="text-transform:uppercase" placeholder="">
        <button class="mini-btn" id="btnLoadOrder">Load</button>
        <button class="mini-btn" id="btnOrderSearch">?</button>
      </div>
      <div class="os-row">
        <label>BType</label>
        <select id="fBillType" style="width:70px">
          @foreach($billTypes as $bt)
            <option value="{{ $bt['code'] }}" data-taxperc="{{ $bt['taxperc'] }}">{{ $bt['code'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="os-row">
        <label>C/o</label>
        <input type="text" id="fCoName" class="md" value="">
        <select id="fCo" class="grow"><option value="">--</option></select>
      </div>
      <div class="os-row">
        <label>GSTIN</label>
        <input type="text" id="fGstNo" class="grow" value="">
      </div>
      <div class="os-row">
        <label>PAN/Adhr</label>
        <input type="text" id="fPanNo" class="grow" value="">
      </div>
    </div>

    <div class="top-block">
      <div class="os-row">
        <label>Date</label>
        <input type="text" id="fDate" class="md" placeholder="dd/mm/yyyy" style="background:#fefce8;">
      </div>
      <div class="os-row">
        <label>Rate/8 gm</label>
        <input type="text" id="fRatePer8gm" class="sm" style="text-align:right" value="0.00">
        <span class="inline-check"><input type="checkbox" id="fCopart" style="width:16px;height:16px;"><span>copart</span></span>
      </div>
      <div class="os-row ob-row">
        <label class="ob-label">OB</label>
        <input type="text" id="fOB" class="sm readonly" style="text-align:right" readonly value="0.00">
        <button type="button" id="btnLedger" title="View Account Ledger" style="font-size:10px;padding:1px 7px;border:1px solid #7f9db9;border-radius:3px;background:#f0f4ff;cursor:pointer;margin-left:4px;">Ledger</button>
        <label class="sm-label">SM Name</label>
        <select id="fSalesman">
          <option value="">--</option>
          @foreach($salesmen as $sm)
            <option value="{{ $sm['code'] }}">{{ $sm['code'] }} - {{ $sm['name'] }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  <!-- ============ ITEMS TABLE ============ -->
  <div class="table-wrap" id="tableWrap">
    <table class="items" id="itemsTable">
      <thead>
        <tr>
          <th style="width:70px">Item<br>code</th>
          <th style="width:150px">Item Name</th>
          <th style="width:65px">Model</th>
          <th style="width:40px">Qty</th>
          <th style="width:85px">Weight<br>in Gm.</th>
          <th style="width:70px">Stone<br>Weight</th>
          <th style="width:85px">Net Wgt<br>in Gm.</th>
          <th style="width:75px">Stone<br>Price</th>
          <th style="width:65px">Wastage</th>
          <th style="width:45px">MC%</th>
          <th style="width:85px">Making<br>Charge</th>
          <th style="width:75px">Rate<br>Rs.</th>
          <th style="width:90px">Amount</th>
        </tr>
      </thead>
      <tbody id="itemsTbody"></tbody>
    </table>
  </div>

  <!-- Table Footer: Add/Delete + Totals -->
  <div class="table-footer">
    <button id="btnAddRow">Add</button>
    <button id="btnDelRow">Delete</button>
    <span class="tf-sep"></span>
    <span class="tf-label">Items</span>
    <span id="ftItemCount" class="tfv" style="min-width:20px">0</span>
    <span class="tf-sep"></span>
    <span class="tf-label">Total :</span>
    <span id="ftQty" class="tfv" style="width:35px">0</span>
    <span id="ftWeight" class="tfv" style="width:60px">0.000</span>
    <span id="ftStoneWgt" class="tfv" style="width:55px">.00</span>
    <span id="ftNetWgt" class="tfv" style="width:60px">.000</span>
    <span id="ftStonePrice" class="tfv" style="width:55px">0.00</span>
    <span id="ftWastage" class="tfv" style="width:50px">0.000</span>
    <span class="tfv" style="width:35px">%</span>
    <span id="ftMcAmt" class="tfv" style="width:60px">0.00</span>
    <span id="ftRate" class="tfv" style="width:55px">0.00</span>
    <span id="ftAmount" class="tfv" style="width:70px">0.00</span>
  </div>

  <!-- EVA / TVA bar -->
  <div class="info-bar">
    <span>Total Items</span>
    <span id="ftItemCountBar" style="min-width:24px">0</span>
    <span class="gap-sm"></span>
    <span>EVA :</span>
    <span id="infoEva" style="min-width:60px">0.00</span>
    <span style="margin-left:auto">TVA :</span>
    <span id="infoTva" style="min-width:60px">0.00</span>
  </div>

  <!-- ============ BOTTOM SECTION + ACTION BUTTONS ============ -->
  <div class="bottom-wrap">
    <div class="bottom-section">
      <div class="summary-model">
        <div class="sm-col">
          <div class="sm-row"><label>Gold Adv</label><input id="fGoldAdv" class="readonly" readonly value="0.000"></div>
          <div class="sm-row"><label>Cess</label><input id="fCessAmt" value=".00"></div>
          <div class="sm-row"><label>Cash Adv</label><input id="fCashAdv" class="readonly" readonly value="0.00"></div>
          <div class="sm-row"><label>Scheme Less</label><input id="fSchemeAmt" value=".00"></div>
          <div class="sm-row"><label>Received</label><input id="fReceived" value=".00"></div>
          <div class="sm-row sm-full"><label></label><label class="sm-inline"><input type="checkbox" id="fCredit"> Credit</label></div>
          <div class="sm-row"><label>Cash/Bank</label>
            <select id="fCashBank">
              <option value="">--</option>
              @foreach($cashBanks as $cb)
                <option value="{{ $cb['code'] }}">{{ $cb['code'] }} - {{ $cb['name'] }}</option>
              @endforeach
            </select>
          </div>
          <select id="fChqBank" style="display:none"></select>
          <div class="sm-row" style="display:none"><label>Agent</label>
            <select id="fAgent">
              <option value="">--</option>
              @foreach($agents as $ag)
                <option value="{{ $ag['code'] }}">{{ $ag['code'] }} - {{ $ag['name'] }}</option>
              @endforeach
            </select>
          </div>
          <div class="sm-row" style="display:none"><label>Approved by</label>
            <select id="fApprovedBy">
              <option value="">--</option>
              @foreach($approvedBy as $ab)
                <option value="{{ $ab['code'] }}">{{ $ab['code'] }} - {{ $ab['name'] }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="sm-col">
          <div class="sm-row"><label>Rate</label><input id="fAdvRate" class="readonly" readonly value=".00"></div>
          <div class="sm-row" style="display:none"><label>Repair</label><input id="fRepair" value=".00"></div>
          <div class="sm-row" style="display:none"><label>HMC</label><input id="fHmc" value=".00"></div>
          <div class="sm-row"><label>Cash Less</label><input id="fCashLess" value=".00"></div>
          <div class="sm-row"><label>Tot Adv</label><input id="fTotAdv" class="readonly" readonly value=".00"></div>
          <div class="sm-row"><label>BC</label>
            <div class="pair-2">
              <input id="fBcPerc" value=".00">
              <input id="fBcAmt" value=".00">
            </div>
          </div>
          <div class="sm-row"><label>ChqAmt/Dt</label>
            <div class="pair-2">
              <input id="fChqAmt" value=".00">
              <input id="fChqDate" placeholder="dd/mm/yyyy">
            </div>
          </div>
          <div class="sm-row sm-full"><label></label><label class="sm-inline"><input type="checkbox" id="fPdc"> PDC</label></div>
          <div class="sm-row"><label>CCardAmt</label><input id="fCcardAmt" value=".00"></div>
          <div class="sm-row sm-full"><label></label><label class="sm-inline"><input type="checkbox" id="fInterstate"> Interstate</label></div>
          <div class="sm-row"><label>Chq. No</label><input id="fChqNo"></div>
          <div class="sm-row sm-full"><label></label><label class="sm-inline"><input type="checkbox" id="fCcardPdc"> CCard PDC ?</label></div>
        </div>

        <div class="sm-col">
          <div class="sm-row"><label>Bill Total</label><input id="fBillTotal" class="readonly" readonly value="0.00"></div>
          <div class="sm-row"><label>Exchange</label><input id="fExchangeAmt" class="readonly" readonly value="0.00"></div>
          <div class="sm-row"><label>Net Total</label><input id="fNetTotal" class="readonly" readonly value="0.00"></div>
          <div class="sm-row"><label>Balance</label><input id="fBalance" class="readonly" readonly value="0.00"></div>
          <div class="sm-row"><label>Note</label><input id="fNote"></div>
          <div class="sm-row"><label>Supply Place</label><input id="fSupplyPlace"></div>
          <div class="sm-row sm-full"><label></label><label class="sm-inline"><input type="checkbox" id="fRateFromOrder"> Rate from order</label></div>
          <div class="sm-row sm-full"><label></label><label class="sm-inline"><input type="checkbox" id="fAllRateFromOrder"> All Rate from order</label></div>
          <div class="sm-row sm-full"><label></label><label class="sm-inline"><input type="checkbox" id="fShowWgtInLedger"> Show Wgt in Ledger</label></div>
        </div>

        <div class="sm-col">
          <div class="sm-row"><label>Tax</label>
            <div class="pair-2">
              <input id="fTaxPerc" value="0.0">
              <input id="fTaxAmt" value=".00">
            </div>
          </div>
          <div class="sm-row"><label>S.Return</label><input id="fSretAmt" class="readonly" readonly value="0.00"></div>
          <div class="sm-row" style="display:none"><label>RDDisc</label><input id="fRddiscAmt" value=".00"></div>
          <input type="hidden" id="fRddisc" value="">
          <div class="sm-row"><label>Discount</label>
            <div class="pair-2">
              <input id="fDiscountPerc" value=".0">
              <input id="fDiscountAmt" value=".00">
            </div>
          </div>
          <div class="sm-row"><label>CB</label><input id="fCB" class="readonly" readonly value="0.00"></div>
          <div class="sm-row"><label>DDate</label><input id="fDueDate" placeholder="dd/mm/yyyy"></div>
          <div class="sm-row"><label>State</label>
            <select id="fState">
              <option value="">--</option>
              @foreach($states as $st)
                <option value="{{ $st['code'] }}">{{ $st['code'] }} - {{ $st['name'] }}</option>
              @endforeach
            </select>
          </div>
          <div class="sm-row sm-toggle" style="display:none"><label>Redeem Points</label><label class="sm-inline"><input type="checkbox" id="fRedeemPoints"> Enable</label></div>
          <div class="sm-row sm-toggle" style="display:none"><label>Print Wgt Amt Bal</label><label class="sm-inline"><input type="checkbox" id="fPrintWgtAmtBal"> Enable</label></div>
          <div class="sm-row sm-toggle" style="display:none"><label>Laser Print</label><label class="sm-inline"><input type="checkbox" id="fLaserPrint"> Enable</label></div>
        </div>
      </div>
    </div>

    <!-- Right side action buttons -->
    <div class="action-panel">
      <button class="highlight" id="btnExchange">Exchange</button>
      <button class="highlight" id="btnSalesReturn">Return</button>
      <button id="btnSave">Save</button>
      <button id="btnPrev">Prev</button>
      <button id="btnNext">Next</button>
      <button id="btnExit">Exit</button>
      <button id="btnShortPrint">Short Print</button>
    </div>
  </div>
</div>

<!-- ============ LEDGER MODAL ============ -->
<div id="ledgerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:13000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.25);width:min(820px,96vw);max-height:88vh;display:flex;flex-direction:column;overflow:hidden;">
    <div style="background:#1e3a5f;color:#fff;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
      <div>
        <span style="font-weight:700;font-size:13px;">Account Ledger</span>
        <span id="ledgerTitle" style="font-size:12px;margin-left:10px;opacity:.85;"></span>
      </div>
      <div style="display:flex;gap:6px;align-items:center;">
        <input type="date" id="ledgerDate1" style="height:26px;border:1px solid #4a7ab5;border-radius:4px;background:#2d5080;color:#fff;font-size:11px;padding:0 6px;">
        <span style="font-size:11px;">to</span>
        <input type="date" id="ledgerDate2" style="height:26px;border:1px solid #4a7ab5;border-radius:4px;background:#2d5080;color:#fff;font-size:11px;padding:0 6px;">
        <button type="button" id="ledgerRefresh" style="height:26px;padding:0 10px;border:1px solid #4a7ab5;border-radius:4px;background:#2d5080;color:#fff;font-size:11px;cursor:pointer;">Go</button>
        <button type="button" id="ledgerClose" style="height:26px;width:26px;border:none;border-radius:4px;background:rgba(255,255,255,.15);color:#fff;font-size:16px;cursor:pointer;line-height:1;">&times;</button>
      </div>
    </div>
    <div style="padding:8px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:11px;display:flex;gap:24px;flex-shrink:0;">
      <span>Opening Balance: <b id="ledgerOB"></b></span>
      <span>Total Debit: <b id="ledgerTotDr"></b></span>
      <span>Total Credit: <b id="ledgerTotCr"></b></span>
      <span>Closing Balance: <b id="ledgerCB"></b></span>
    </div>
    <div style="overflow:auto;flex:1;">
      <table style="width:100%;border-collapse:collapse;font-size:11px;">
        <thead>
          <tr style="background:#1e3a5f;color:#fff;position:sticky;top:0;">
            <th style="padding:6px 8px;text-align:left;width:80px;">Date</th>
            <th style="padding:6px 8px;text-align:left;width:90px;">Voucher No</th>
            <th style="padding:6px 8px;text-align:left;width:130px;">A/c Name</th>
            <th style="padding:6px 8px;text-align:left;">Description</th>
            <th style="padding:6px 8px;text-align:right;width:90px;">Debit</th>
            <th style="padding:6px 8px;text-align:right;width:90px;">Credit</th>
            <th style="padding:6px 8px;text-align:right;width:100px;">Balance</th>
          </tr>
        </thead>
        <tbody id="ledgerRows"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- ============ MESSAGE MODAL ============ -->
<div class="msg-modal" id="msgModal">
  <div class="msg-box">
    <div class="head" id="msgModalTitle">Message</div>
    <div class="body" id="msgModalText"></div>
    <div class="foot"><button class="ok-btn" id="msgModalOk">OK</button></div>
  </div>
</div>

<!-- ============ ORDER SEARCH MODAL ============ -->
<div class="modal-overlay" id="orderSearchModal">
  <div class="modal-panel" style="min-width:880px">
    <div class="m-head">
      <span>Search Order</span>
      <button class="m-close" data-close="orderSearchModal">&times;</button>
    </div>
    <div class="m-body">
      <div style="display:flex;gap:6px;margin-bottom:6px">
        <input type="text" id="orderSearchQ" style="flex:1;height:22px;font-size:11px;padding:2px 6px;border:1px solid #7f9db9" placeholder="Order No / Customer...">
        <button class="btn-sm" id="btnDoOrderSearch">Search</button>
      </div>
      <div class="search-list" id="orderSearchResults" style="max-height:320px"></div>
    </div>
    <div class="m-foot">
      <button data-close="orderSearchModal">Close</button>
    </div>
  </div>
</div>

<!-- ============ BILL SEARCH MODAL ============ -->
<div class="modal-overlay" id="billSearchModal">
  <div class="modal-panel" style="min-width:640px">
    <div class="m-head">
      <span>Search Order Sale Bill</span>
      <button class="m-close" data-close="billSearchModal">&times;</button>
    </div>
    <div class="m-body">
      <div style="display:flex;gap:6px;margin-bottom:6px">
        <input type="text" id="billSearchQ" style="flex:1;height:22px;font-size:11px;padding:2px 6px;border:1px solid #7f9db9" placeholder="Bill No / Customer / Order No...">
        <button class="btn-sm" id="btnDoBillSearch">Search</button>
      </div>
      <div class="search-list" id="billSearchResults" style="max-height:320px"></div>
    </div>
    <div class="m-foot">
      <button data-close="billSearchModal">Close</button>
    </div>
  </div>
</div>

<!-- ============ ITEM SEARCH MODAL ============ -->
<div class="modal-overlay" id="itemSearchModal">
  <div class="modal-panel" style="min-width:500px">
    <div class="m-head">
      <span>Item Search</span>
      <button class="m-close" data-close="itemSearchModal">&times;</button>
    </div>
    <div class="m-body">
      <div style="display:flex;gap:6px;margin-bottom:6px">
        <input type="text" id="itemSearchQ" style="flex:1;height:22px;font-size:11px;padding:2px 6px;border:1px solid #7f9db9" placeholder="Code or Name...">
        <button class="btn-sm" id="btnDoItemSearch">Search</button>
      </div>
      <div class="search-list" id="itemSearchResults" style="max-height:320px"></div>
    </div>
    <div class="m-foot">
      <button data-close="itemSearchModal">Close</button>
    </div>
  </div>
</div>

<!-- ============ EXCHANGE MODAL ============ -->
<div class="modal-overlay" id="exchangeModal">
  <div class="modal-panel" style="min-width:860px">
    <div class="m-head">
      <span>Exchange Items</span>
      <button class="m-close" data-close="exchangeModal">&times;</button>
    </div>
    <div class="m-body" style="padding:4px">
      <div style="overflow-x:auto">
        <table class="exch-table" id="exchTable">
          <thead>
            <tr>
              <th style="width:55px">Code</th>
              <th style="width:120px">Name</th>
              <th>Rate</th>
              <th>Qty</th>
              <th>Weight</th>
              <th>Mud</th>
              <th>St.Wgt</th>
              <th>St.Price</th>
              <th>Touch%</th>
              <th>Less%</th>
              <th>Less Wgt</th>
              <th>Extra</th>
              <th>Net Wgt</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody id="exchTbody"></tbody>
        </table>
      </div>
      <div style="display:flex;gap:6px;margin-top:4px;align-items:center">
        <button class="btn-sm" id="btnExchAdd">Add</button>
        <button class="btn-sm" id="btnExchDel">Delete</button>
        <span class="tf-sep"></span>
        <span style="font-size:11px;font-weight:600">Rows: <span id="exchRowCount">0</span></span>
        <span style="flex:1"></span>
        <span style="font-size:11px;font-weight:700">Total:</span>
        <span style="font-size:11px">Qty:<span id="exftQty" class="tfv">0</span></span>
        <span style="font-size:11px">Wgt:<span id="exftWeight" class="tfv">0.000</span></span>
        <span style="font-size:11px">Amt:<span id="exftAmount" class="tfv">0.00</span></span>
      </div>
      <div style="display:flex;gap:8px;margin-top:4px;align-items:center;font-size:11px;font-weight:600">
        <label>Bill Amt</label>
        <input type="text" id="exBillAmt" style="width:80px;text-align:right;height:21px;border:1px solid #7f9db9;font-size:11px" class="readonly" readonly>
        <label>Exchange Amt</label>
        <input type="text" id="exExAmt" style="width:80px;text-align:right;height:21px;border:1px solid #7f9db9;font-size:11px" class="readonly" readonly>
        <label>Net</label>
        <input type="text" id="exNet" style="width:80px;text-align:right;height:21px;border:1px solid #7f9db9;font-size:11px" class="readonly" readonly>
        <span style="flex:1"></span>
        <label>Sale Weight</label>
        <input type="text" id="exSaleWgt" style="width:80px;text-align:right;height:21px;border:1px solid #7f9db9;font-size:11px" class="readonly" readonly>
      </div>
    </div>
    <div class="m-foot">
      <button class="primary" id="btnExchSave">Save</button>
      <button id="btnExchCancel" data-close="exchangeModal">Cancel</button>
    </div>
  </div>
</div>

<!-- ============ SALES RETURN MODAL ============ -->
<div class="modal-overlay" id="salesReturnModal">
  <div class="modal-panel" style="min-width:860px">
    <div class="m-head">
      <span>Sales Return Items</span>
      <button class="m-close" data-close="salesReturnModal">&times;</button>
    </div>
    <div class="m-body" style="padding:4px">
      <div style="overflow-x:auto">
        <table class="exch-table" id="srTable">
          <thead>
            <tr>
              <th style="width:55px">Code</th>
              <th style="width:120px">Name</th>
              <th>Model</th>
              <th>Qty</th>
              <th>Weight</th>
              <th>St.Wgt</th>
              <th>Net Wgt</th>
              <th>St.Price</th>
              <th>Wastage</th>
              <th>MC%</th>
              <th>MC</th>
              <th>Rate</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody id="srTbody"></tbody>
        </table>
      </div>
      <div style="display:flex;gap:6px;margin-top:4px;align-items:center">
        <button class="btn-sm" id="btnSrAdd">Add</button>
        <button class="btn-sm" id="btnSrDel">Delete</button>
        <span class="tf-sep"></span>
        <span style="font-size:11px;font-weight:600">Rows: <span id="srRowCount">0</span></span>
        <span style="flex:1"></span>
        <span style="font-size:11px;font-weight:700">Total:</span>
        <span style="font-size:11px">Qty:<span id="srftQty" class="tfv">0</span></span>
        <span style="font-size:11px">Wgt:<span id="srftWeight" class="tfv">0.000</span></span>
        <span style="font-size:11px">Amt:<span id="srftAmount" class="tfv">0.00</span></span>
      </div>
      <div style="display:flex;gap:8px;margin-top:4px;align-items:center;font-size:11px;font-weight:600">
        <label>Discount</label>
        <input type="text" id="srDiscount" style="width:60px;text-align:right;height:21px;border:1px solid #7f9db9;font-size:11px" value="0">
        <label>Tax%</label>
        <input type="text" id="srTaxPerc" style="width:40px;text-align:right;height:21px;border:1px solid #7f9db9;font-size:11px">
        <input type="text" id="srTaxAmt" style="width:60px;text-align:right;height:21px;border:1px solid #7f9db9;font-size:11px" class="readonly" readonly>
        <label>Cess%</label>
        <input type="text" id="srCessPerc" style="width:40px;text-align:right;height:21px;border:1px solid #7f9db9;font-size:11px">
        <input type="text" id="srCessAmt" style="width:60px;text-align:right;height:21px;border:1px solid #7f9db9;font-size:11px" class="readonly" readonly>
        <span style="flex:1"></span>
        <label>Bill Amt</label>
        <input type="text" id="srBillAmt" style="width:80px;text-align:right;height:21px;border:1px solid #7f9db9;font-size:11px;background:#ffffe0" class="readonly" readonly>
        <label>Return Amt</label>
        <input type="text" id="srRetAmt" style="width:80px;text-align:right;height:21px;border:1px solid #7f9db9;font-size:11px;background:#0a246a;color:#fff;font-weight:700" class="readonly" readonly>
      </div>
    </div>
    <div class="m-foot">
      <button class="primary" id="btnSrSave">Save</button>
      <button id="btnSrCancel" data-close="salesReturnModal">Cancel</button>
    </div>
  </div>
</div>

<!-- ============ JAVASCRIPT ============ -->
<script>
(function(){
'use strict';
const CFG = window.OS_CONFIG;
const API = CFG.siteUrl;
const csrf = document.querySelector('meta[name="csrf-token"]').content;

// ── State ──────────────────────────────────────────────────
let itemRows = [];
let exchRows = [];
let srRows = [];
let selectedRow = -1;
let exchSelectedRow = -1;
let srSelectedRow = -1;
let currentOrderNo = '';
let currentOrderSlno = 0;
let isEditMode = false;
let orderAdvance = 0;
let orderGoldAdvWgt = 0;
let orderAmtToWgt = 0;
let itemSearchTargetRow = -1;
let receivedTouched = false;
let isLoadingBill = false;

// ── Helpers ────────────────────────────────────────────────
const $ = id => document.getElementById(id);
const toNum = v => { let n = parseFloat(String(v).replace(/,/g,'')); return isNaN(n)?0:n; };
const fmt = (v,d=2) => toNum(v).toFixed(d);
let msgModalOkHandler = null;

function getDefaultCashBankCode() {
  const list = Array.isArray(CFG.cashBanks) ? CFG.cashBanks : [];
  const exactCode = list.find(r => String(r.code || '').trim().toUpperCase() === 'CASH');
  if (exactCode) return String(exactCode.code || '').trim();
  const exactName = list.find(r => String(r.name || '').trim().toUpperCase() === 'CASH IN HAND');
  if (exactName) return String(exactName.code || '').trim();
  const cashType = list.find(r => String(r.type || '').trim().toUpperCase() === 'H');
  if (cashType) return String(cashType.code || '').trim();
  return '';
}

function showMsg(title, text, onOk = null) {
  msgModalOkHandler = (typeof onOk === 'function') ? onOk : null;
  $('msgModalTitle').textContent = title;
  $('msgModalText').textContent = text;
  $('msgModal').classList.add('active');
}

function closeModal(id) {
  $(id).classList.remove('active');
}

function api(url, opts = {}) {
  const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
  if (opts.json) {
    headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(opts.json);
    delete opts.json;
  }
  opts.headers = { ...(opts.headers||{}), ...headers };
  return fetch(API + url, opts).then(r => r.json());
}

// ── New item row ───────────────────────────────────────────
function newItemRow() {
  return {
    item_code:'', item_name:'', model:'', qty:0, weight:0, stone_wgt:0,
    net_wgt:0, stone_price:0, wastage:0, vaperc:0, making_charge:0,
    rate: toNum($('fRatePerGm').value), amount:0,
    stktype:'', stktouch:0, bcode:0, huid:'', iqtype:'', cost:0, item_type:'G'
  };
}

// ── Render items table ─────────────────────────────────────
function renderItemsTable() {
  const tbody = $('itemsTbody');
  tbody.innerHTML = '';
  itemRows.forEach((row, idx) => {
    const tr = document.createElement('tr');
    tr.dataset.idx = idx;
    if (idx === selectedRow) tr.classList.add('sel');
    tr.innerHTML = `
      <td><input data-col="item_code" value="${row.item_code}" style="text-transform:uppercase"></td>
      <td style="text-align:left;font-size:10px;padding-left:4px">${row.item_name||''}</td>
      <td><input data-col="model" value="${row.model||''}"></td>
      <td><input data-col="qty" class="num" value="${row.qty||0}"></td>
      <td><input data-col="weight" class="num" value="${fmt(row.weight,3)}"></td>
      <td><input data-col="stone_wgt" class="num" value="${fmt(row.stone_wgt,3)}"></td>
      <td style="text-align:right;padding-right:4px;font-size:10px">${fmt(row.net_wgt,3)}</td>
      <td><input data-col="stone_price" class="num" value="${fmt(row.stone_price,2)}"></td>
      <td><input data-col="wastage" class="num" value="${fmt(row.wastage,3)}"></td>
      <td><input data-col="vaperc" class="num" value="${fmt(row.vaperc,2)}"></td>
      <td><input data-col="making_charge" class="num" value="${fmt(row.making_charge,2)}"></td>
      <td><input data-col="rate" class="num" value="${fmt(row.rate,2)}"></td>
      <td><input data-col="amount" class="num" value="${fmt(row.amount,2)}"></td>
    `;
    tbody.appendChild(tr);
  });
  markSelectedRow();
  updateTableFooter();
}

function markSelectedRow() {
  document.querySelectorAll('#itemsTbody tr').forEach((tr, i) => {
    if (i === selectedRow) tr.classList.add('sel');
    else tr.classList.remove('sel');
  });
}

function updateTableFooter() {
  let tQty=0, tWgt=0, tStwgt=0, tNetwgt=0, tStprice=0, tWastage=0, tMc=0, tAmt=0;
  itemRows.forEach(r => {
    tQty += toNum(r.qty);
    tWgt += toNum(r.weight);
    tStwgt += toNum(r.stone_wgt);
    tNetwgt += toNum(r.net_wgt);
    tStprice += toNum(r.stone_price);
    tWastage += toNum(r.wastage);
    tMc += toNum(r.making_charge);
    tAmt += toNum(r.amount);
  });
  $('ftItemCount').textContent = itemRows.filter(r => r.item_code).length;
  $('ftQty').textContent = tQty;
  $('ftWeight').textContent = fmt(tWgt,3);
  $('ftStoneWgt').textContent = fmt(tStwgt,2);
  $('ftNetWgt').textContent = fmt(tNetwgt,3);
  $('ftStonePrice').textContent = fmt(tStprice,2);
  $('ftWastage').textContent = fmt(tWastage,3);
  $('ftMcAmt').textContent = fmt(tMc,2);
  $('ftAmount').textContent = fmt(tAmt,2);
  $('fBillTotal').value = fmt(tAmt,2);
  recomputeVaInfo();
  triggerRecalc();
}

function recomputeVaInfo() {
  let totalVa = 0;
  itemRows.forEach(r => {
    totalVa += toNum(r.making_charge) + toNum(r.stone_price);
  });
  let billTotal = toNum($('fBillTotal').value);
  let eva = billTotal > 0 ? ((totalVa / billTotal) * 100) : 0;
  $('infoEva').textContent = fmt(eva, 2);
  $('infoTva').textContent = fmt(totalVa, 2);
  if ($('ftItemCountBar')) $('ftItemCountBar').textContent = String(itemRows.length);
}

// ── Item row recalc ────────────────────────────────────────
function recalcItemRow(idx, changedCol, rerender = true) {
  let r = itemRows[idx];
  if (!r) return;
  let qty = toNum(r.qty);
  let wgt = toNum(r.weight);
  let stwgt = toNum(r.stone_wgt);
  let stprice = toNum(r.stone_price);
  let rate = toNum(r.rate);
  let wastage = toNum(r.wastage);
  let vaperc = toNum(r.vaperc);
  let mc = toNum(r.making_charge);
  let netwgt = Math.max(wgt - stwgt, 0);

  // MC% logic: recalc MC when MC% or base factors change.
  const mcPercDrivenCols = ['item_code','weight','stone_wgt','rate','vaperc'];
  if (mcPercDrivenCols.includes(changedCol) && vaperc > 0) {
    mc = (netwgt * rate * vaperc) / 100;
    r.making_charge = Math.round(mc * 100) / 100;
  }

  // If wastage changed and wastage is in weight, add to mc
  if (changedCol === 'wastage') {
    // wastage is already stored as-is
  }

  // Amount = (netwgt * rate) + stprice + mc
  let amt = (netwgt * rate) + stprice + mc;

  r.net_wgt = Math.round(netwgt * 1000) / 1000;
  r.amount = Math.round(amt * 100) / 100;
  if (rerender) {
    renderItemsTable();
  } else {
    const tr = document.querySelector(`#itemsTbody tr[data-idx="${idx}"]`);
    if (tr) {
      const netCell = tr.children[6];
      if (netCell) netCell.textContent = fmt(r.net_wgt, 3);
      const amtInput = tr.querySelector('input[data-col="amount"]');
      if (amtInput) amtInput.value = fmt(r.amount, 2);
      const mcInput = tr.querySelector('input[data-col="making_charge"]');
      if (mcInput && mcPercDrivenCols.includes(changedCol)) mcInput.value = fmt(r.making_charge, 2);
    }
    updateTableFooter();
  }
}

// ── Item lookup ────────────────────────────────────────────
function itemLookup(idx) {
  const r = itemRows[idx];
  if (!r || !r.item_code) return;
  const goldRate = toNum($('fRatePerGm').value);
  api(`/api/order-sale/item-lookup?code=${encodeURIComponent(r.item_code)}&gold_rate=${goldRate}`)
    .then(d => {
      if (d.ok && (d.item_name || d.name)) {
        r.item_code = d.item_code || r.item_code;
        r.item_name = d.item_name || d.name || '';
        r.item_type = d.item_type || d.itype || 'G';
        r.qty = d.qty || 1;
        r.weight = d.weight || 0;
        r.stone_wgt = d.stone_wgt || 0;
        r.net_wgt = d.net_wgt || 0;
        r.stone_price = d.stone_price || 0;
        r.wastage = d.wastage || 0;
        r.vaperc = d.vaperc || 0;
        r.making_charge = d.making_charge || d.mc || 0;
        r.rate = d.rate || toNum($('fRatePerGm').value);
        r.amount = d.amount || 0;
        r.model = d.model || '';
        r.stktype = d.stktype || '';
        r.stktouch = d.stktouch || 0;
        r.bcode = d.bcode || 0;
        r.huid = d.huid || '';
        r.cost = d.cost || 0;
        renderItemsTable();
        setTimeout(() => {
          const input = document.querySelector(`#itemsTbody tr[data-idx="${idx}"] input[data-col="model"]`);
          if (input) { input.focus(); input.select(); }
        }, 50);
        return;
      }

      // Fallback: lightweight item search by code
      return api(`/api/order-sale/item-search?q=${encodeURIComponent(r.item_code)}`).then(s => {
        if (!s.ok || !Array.isArray(s.results) || s.results.length === 0) {
          showMsg('Error', d.message || 'Item not found');
          return;
        }
        const exact = s.results.find(x => String(x.code || '').toUpperCase() === String(r.item_code || '').toUpperCase()) || s.results[0];
        r.item_code = String(exact.code || r.item_code).toUpperCase();
        r.item_name = exact.name || '';
        r.item_type = exact.itype || 'G';
        if (!toNum(r.rate)) r.rate = toNum($('fRatePerGm').value);
        if (!toNum(r.qty)) r.qty = 1;
        recalcItemRow(idx, 'item_code', true);
        setTimeout(() => {
          const input = document.querySelector(`#itemsTbody tr[data-idx="${idx}"] input[data-col="model"]`);
          if (input) { input.focus(); input.select(); }
        }, 50);
      });
    });
}

function shouldLookupItem(idx, code) {
  const r = itemRows[idx];
  if (!r) return false;
  const prevCode = String(r.item_code || '').toUpperCase();
  const nextCode = String(code || '').toUpperCase();
  // Lookup only if code changed or row name is empty (new/blank row).
  return nextCode !== '' && (prevCode !== nextCode || !String(r.item_name || '').trim());
}

// ── Event delegation: items table blur ─────────────────────
$('itemsTbody').addEventListener('blur', function(e) {
  const inp = e.target;
  if (inp.tagName !== 'INPUT') return;
  const tr = inp.closest('tr');
  if (!tr) return;
  const idx = parseInt(tr.dataset.idx);
  const col = inp.dataset.col;
  if (isNaN(idx) || !col) return;
  const r = itemRows[idx];
  if (!r) return;
  const numCols = ['qty','weight','stone_wgt','stone_price','wastage','vaperc','making_charge','rate','amount'];
  if (col === 'item_code') {
    const code = inp.value.trim().toUpperCase();
    inp.value = code;
    if (code && code !== String(r.item_code || '').toUpperCase()) {
      r.item_code = code;
      itemLookup(idx);
    }
    return;
  }
  if (numCols.includes(col)) {
    r[col] = toNum(inp.value);
  } else {
    r[col] = inp.value.trim();
  }
  if (numCols.includes(col)) {
    recalcItemRow(idx, col, false);
  }
}, true);

// Row selection + mouse focus without forced re-render
$('itemsTbody').addEventListener('click', function(e) {
  const tr = e.target.closest('tr');
  if (!tr) return;
  const idx = parseInt(tr.dataset.idx);
  if (isNaN(idx)) return;
  selectedRow = idx;
  markSelectedRow();

  // If user clicks empty cell area, move cursor to item code input
  if (e.target.tagName !== 'INPUT') {
    const first = tr.querySelector('input[data-col="item_code"]');
    if (first) first.focus();
  }
});

// ── Event delegation: items table keydown ──────────────────
$('itemsTbody').addEventListener('keydown', function(e) {
  const inp = e.target;
  if (inp.tagName !== 'INPUT') return;
  const tr = inp.closest('tr');
  if (!tr) return;
  const idx = parseInt(tr.dataset.idx);
  const col = inp.dataset.col;

  const editOrder = ['item_code','model','qty','weight','stone_wgt','stone_price','wastage','vaperc','making_charge','rate','amount'];
  const moveOrdered = (forward = true) => {
    const pos = editOrder.indexOf(col);
    if (pos < 0) return false;
    const nextPos = forward ? pos + 1 : pos - 1;
    if (nextPos >= 0 && nextPos < editOrder.length) {
      const ni = tr.querySelector(`input[data-col="${editOrder[nextPos]}"]`);
      if (ni) {
        ni.focus();
        ni.select();
        return true;
      }
      return false;
    }
    if (forward) {
      moveToNextRowItemCode();
      return true;
    }
    if (!forward && idx > 0) {
      selectedRow = idx - 1;
      setTimeout(() => {
        const prevTr = document.querySelector(`#itemsTbody tr[data-idx="${idx-1}"]`);
        if (!prevTr) return;
        const pi = prevTr.querySelector(`input[data-col="${editOrder[editOrder.length - 1]}"]`) ||
                   prevTr.querySelector('input[data-col="item_code"]');
        if (pi) { pi.focus(); pi.select(); }
      }, 30);
      return true;
    }
    return false;
  };

  const moveToNextRowItemCode = () => {
    if (idx === itemRows.length - 1) {
      itemRows.push(newItemRow());
      renderItemsTable();
    }
    selectedRow = idx + 1;
    setTimeout(() => {
      const nextTr = document.querySelector(`#itemsTbody tr[data-idx="${idx+1}"]`);
      if (nextTr) {
        nextTr.scrollIntoView({ block: 'nearest' });
        const fi = nextTr.querySelector('input[data-col="item_code"]');
        if (fi) { fi.focus(); fi.select(); }
      }
    }, 30);
  };

  if (e.key === 'Enter') {
    e.preventDefault();
    if (col === 'item_code') {
      const code = inp.value.trim().toUpperCase();
      inp.value = code;
      if (shouldLookupItem(idx, code)) {
        itemRows[idx].item_code = code;
        itemLookup(idx);
      } else {
        const ni = tr.querySelector('input[data-col="model"]');
        if (ni) { ni.focus(); ni.select(); }
      }
      return;
    }
    moveOrdered(true);
    return;
  }

  if (e.key === 'Tab') {
    if (col === 'item_code') {
      const code = inp.value.trim().toUpperCase();
      inp.value = code;
      if (shouldLookupItem(idx, code)) {
        e.preventDefault();
        itemRows[idx].item_code = code;
        itemLookup(idx);
      }
      // After lookup/fill, continue ordered flow from model.
      return;
    }
    e.preventDefault();
    moveOrdered(!e.shiftKey);
    return;
  }

  // F1 = item search
  if (e.key === 'F1' && col === 'item_code') {
    e.preventDefault();
    itemSearchTargetRow = idx;
    $('itemSearchQ').value = inp.value;
    $('itemSearchModal').classList.add('active');
    $('itemSearchQ').focus();
    doItemSearch();
  }
});

// ── Add/Delete rows ────────────────────────────────────────
$('btnAddRow').addEventListener('click', () => {
  selectedRow = itemRows.length;
  itemRows.push(newItemRow());
  renderItemsTable();
  setTimeout(() => {
    const tr = document.querySelector(`#itemsTbody tr[data-idx="${selectedRow}"]`);
    if (tr) {
      tr.scrollIntoView({ block: 'nearest' });
      const fi = tr.querySelector('input[data-col="item_code"]');
      if (fi) { fi.focus(); fi.select(); }
    }
  }, 30);
});
$('btnDelRow').addEventListener('click', () => {
  if (selectedRow >= 0 && selectedRow < itemRows.length) {
    itemRows.splice(selectedRow, 1);
    selectedRow = Math.min(selectedRow, itemRows.length - 1);
    renderItemsTable();
  }
});

// ── Load Order ─────────────────────────────────────────────
function loadOrder(ordno) {
  if (!ordno) { showMsg('Error', 'Enter an order number.'); return; }
  api(`/api/order-sale/load-order?order_no=${encodeURIComponent(ordno)}`)
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Could not load order.'); return; }
      currentOrderNo = d.order_no || ordno;
      currentOrderSlno = d.slno || 0;
      $('fBillNo').value = d.next_bill_no || '';
      $('fOrderNo').value = currentOrderNo;
      $('fCustName').value = d.cust_name || '';
      $('fCustomerCode').innerHTML = `<option value="${d.cust_code||''}">${d.cust_code||''} - ${d.cust_name||''}</option>`;
      $('fCustomerCode').value = d.cust_code || '';
      if ($('fAddress')) $('fAddress').value = d.address || '';
      $('fOB').value = fmt(d.ob, 2);
      $('fRatePerGm').value = fmt(d.gold_rate, 2);
      $('fRatePer8gm').value = fmt(d.gold_rate * 8, 2);
      (function(){ const _d=new Date(); $('fDate').value=String(_d.getDate()).padStart(2,'0')+'/'+String(_d.getMonth()+1).padStart(2,'0')+'/'+_d.getFullYear(); })();
      $('fMobile').value = d.phone || '';
      $('fNote').value = d.note || '';
      if (d.salesman) $('fSalesman').value = d.salesman;
      if (d.counter) $('fCounter').value = d.counter;
      if (d.bill_type) $('fBillType').value = d.bill_type;

      // Advance values
      orderAdvance = toNum(d.advance);
      orderGoldAdvWgt = toNum(d.gold_advance_wgt);
      orderAmtToWgt = toNum(d.amttowgt_total);
      $('fCashAdv').value = fmt(orderAdvance, 2);
      $('fSchemeAmt').value = '.00';
      $('fGoldAdv').value = fmt(orderGoldAdvWgt, 3);
      $('fTotAdv').value = fmt(orderAdvance, 2);
      $('fAdvRate').value = fmt(d.gold_rate, 2);
      $('fExchangeAmt').value = fmt(d.exchange_amt, 2);
      $('fSretAmt').value = fmt(d.sret_amt, 2);
      receivedTouched = false;

      // Items
      itemRows = (d.items || []).map(it => ({
        item_code: it.item_code || '',
        item_name: it.item_name || '',
        model: '',
        qty: toNum(it.qty),
        weight: toNum(it.weight),
        stone_wgt: toNum(it.stone_wgt),
        net_wgt: Math.max(toNum(it.weight) - toNum(it.stone_wgt), 0),
        stone_price: toNum(it.stone_price),
        wastage: toNum(it.wastage),
        vaperc: toNum(it.vaperc),
        making_charge: toNum(it.making_charge),
        rate: toNum(it.rate) || toNum(d.gold_rate),
        amount: toNum(it.amount),
        stktype: it.stktype || '',
        stktouch: 0,
        bcode: 0,
        huid: '',
        iqtype: it.iqtype || '',
        cost: toNum(it.cost),
        item_type: it.item_type || 'G'
      }));
      if (itemRows.length === 0) itemRows.push(newItemRow());
      selectedRow = 0;
      renderItemsTable();

      // Exchange items from order
      exchRows = (d.exchange_items || []).map(ex => ({
        item_code: ex.item_code||'', item_name: ex.item_name||'',
        rate: toNum(ex.rate), qty: toNum(ex.qty), weight: toNum(ex.weight),
        mud_less: toNum(ex.mud_less), stone_wgt: toNum(ex.stone_wgt),
        stone_price: toNum(ex.stone_price), touch: 0, less_perc: toNum(ex.less_perc),
        less_wgt: toNum(ex.less_wgt), extra_wgt: 0,
        net_wgt: Math.max(toNum(ex.weight) - toNum(ex.less_wgt) - toNum(ex.mud_less) - toNum(ex.stone_wgt), 0),
        amount: toNum(ex.amount), stktype: ex.stktype||'', cost: toNum(ex.cost)
      }));

      // Sales return items from order
      srRows = (d.sales_return_items || []).map(sr => ({
        item_code: sr.item_code||'', item_name: sr.item_name||'',
        model:'', qty: toNum(sr.qty), weight: toNum(sr.weight),
        stone_wgt: toNum(sr.stone_wgt),
        net_wgt: Math.max(toNum(sr.weight) - toNum(sr.stone_wgt), 0),
        stone_price: toNum(sr.stone_price), wastage: toNum(sr.wastage),
        vaperc: 0, making_charge: toNum(sr.making_charge),
        rate: toNum(sr.rate), amount: toNum(sr.amount), stktype: sr.stktype||''
      }));
    });
}

$('btnLoadOrder').addEventListener('click', () => {
  loadOrder($('fOrderNo').value.trim().toUpperCase());
});
$('fOrderNo').addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    loadOrder($('fOrderNo').value.trim().toUpperCase());
  }
});

// ── Format date helper ─────────────────────────────────────
function formatDateForDisplay(dateStr) {
  if (!dateStr) return '';
  // Handle YYYY-MM-DD
  const m = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (m) return `${m[3]}/${m[2]}/${m[1]}`;
  // Handle DD/MM/YYYY already
  if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) return dateStr;
  return dateStr;
}
function parseDateToISO(dateStr) {
  if (!dateStr) return '';
  const m = dateStr.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  if (m) return `${m[3]}-${m[2]}-${m[1]}`;
  return dateStr;
}

// ── Order Search ───────────────────────────────────────────
$('btnOrderSearch').addEventListener('click', () => {
  $('orderSearchModal').classList.add('active');
  $('orderSearchQ').focus();
  doOrderSearch();
});
$('btnDoOrderSearch').addEventListener('click', doOrderSearch);
$('orderSearchQ').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); doOrderSearch(); }
});

function doOrderSearch() {
  const q = $('orderSearchQ').value.trim();
  api(`/api/order-sale/order-search?q=${encodeURIComponent(q)}`)
    .then(d => {
      if (!d.ok) return;
      let html = '<table><thead><tr><th>Ord No</th><th>Date</th><th>Customer</th><th>Rate</th><th>Bill Amt</th><th>Advance</th><th>Exchange</th><th>Sret</th><th>Balance</th><th>Items</th><th>Status</th></tr></thead><tbody>';
      (d.results||[]).forEach(r => {
        const disabled = r.has_sale || r.blocked;
        let statusBadge = '<span class="status-badge open">OPEN</span>';
        if (r.has_sale) statusBadge = '<span class="status-badge sold">SOLD</span>';
        else if (r.blocked) statusBadge = '<span class="status-badge blocked">BLOCKED</span>';
        html += `<tr style="${disabled?'opacity:.5;cursor:not-allowed':''}" ${disabled?'':'data-ordno="'+r.ordno+'"'}>
          <td>${r.ordno}</td><td>${r.date||''}</td><td>${r.cust_name}</td>
          <td style="text-align:right">${fmt(r.rate)}</td><td style="text-align:right">${fmt(r.bill_amt)}</td>
          <td style="text-align:right">${fmt(r.advance)}</td><td style="text-align:right">${fmt(r.exchange)}</td>
          <td style="text-align:right">${fmt(r.sret_amt)}</td><td style="text-align:right">${fmt(r.balance)}</td>
          <td style="text-align:center">${r.item_count}</td><td>${statusBadge}</td></tr>`;
      });
      html += '</tbody></table>';
      $('orderSearchResults').innerHTML = html;
      // Click handler
      $('orderSearchResults').querySelectorAll('tr[data-ordno]').forEach(tr => {
        tr.addEventListener('click', () => {
          const ordno = tr.dataset.ordno;
          closeModal('orderSearchModal');
          $('fOrderNo').value = ordno;
          loadOrder(ordno);
        });
      });
    });
}

// ── Order Details button ───────────────────────────────────
$('btnOrderDetails').addEventListener('click', () => {
  if (!currentOrderNo) { showMsg('Info', 'Load an order first.'); return; }
  showMsg('Order Details', `Order No: ${currentOrderNo}\nAdvance: ${fmt(orderAdvance,2)}\nGold Adv Wgt: ${fmt(orderGoldAdvWgt,3)}`);
});

// ── Bill Search ────────────────────────────────────────────
$('btnDoBillSearch').addEventListener('click', doBillSearch);
$('billSearchQ').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); doBillSearch(); }
});

function doBillSearch() {
  const q = $('billSearchQ').value.trim();
  api(`/api/order-sale/search?q=${encodeURIComponent(q)}`)
    .then(d => {
      if (!d.ok) return;
      let html = '<table><thead><tr><th>Bill No</th><th>Date</th><th>Customer</th><th>Amount</th><th>Order No</th><th>Status</th></tr></thead><tbody>';
      (d.results||[]).forEach(r => {
        html += `<tr data-billno="${r.bill_no}"><td>${r.bill_no}</td><td>${r.date||''}</td><td>${r.cust_name}</td><td style="text-align:right">${fmt(r.bill_total)}</td><td>${r.order_no}</td><td>${r.status===1?'Active':'Cancelled'}</td></tr>`;
      });
      html += '</tbody></table>';
      $('billSearchResults').innerHTML = html;
      $('billSearchResults').querySelectorAll('tr[data-billno]').forEach(tr => {
        tr.addEventListener('click', () => {
          closeModal('billSearchModal');
          loadBill(tr.dataset.billno);
        });
      });
    });
}

// ── Item Search ────────────────────────────────────────────
$('btnDoItemSearch').addEventListener('click', doItemSearch);
$('itemSearchQ').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); doItemSearch(); }
});

function doItemSearch() {
  const q = $('itemSearchQ').value.trim();
  api(`/api/order-sale/item-search?q=${encodeURIComponent(q)}`)
    .then(d => {
      if (!d.ok) return;
      let html = '<table><thead><tr><th>Code</th><th>Name</th><th>Type</th></tr></thead><tbody>';
      (d.results||[]).forEach(r => {
        html += `<tr data-code="${r.code}"><td>${r.code}</td><td>${r.name}</td><td>${r.itype}</td></tr>`;
      });
      html += '</tbody></table>';
      $('itemSearchResults').innerHTML = html;
      $('itemSearchResults').querySelectorAll('tr[data-code]').forEach(tr => {
        tr.addEventListener('click', () => {
          const code = tr.dataset.code;
          closeModal('itemSearchModal');
          if (itemSearchTargetRow >= 0 && itemSearchTargetRow < itemRows.length) {
            itemRows[itemSearchTargetRow].item_code = code;
            itemLookup(itemSearchTargetRow);
          }
        });
      });
    });
}

// ── Exchange Modal ─────────────────────────────────────────
function newExchRow() {
  return {
    item_code:'', item_name:'', rate: toNum($('fRatePerGm').value), qty:0,
    weight:0, mud_less:0, stone_wgt:0, stone_price:0, touch:0, less_perc:0,
    less_wgt:0, extra_wgt:0, net_wgt:0, amount:0, stktype:'', cost:0
  };
}

function renderExchTable() {
  const tbody = $('exchTbody');
  tbody.innerHTML = '';
  exchRows.forEach((row, idx) => {
    const tr = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML = `
      <td><input data-col="item_code" value="${row.item_code}" style="text-transform:uppercase"></td>
      <td style="text-align:left;font-size:10px">${row.item_name||''}</td>
      <td><input data-col="rate" class="num" value="${fmt(row.rate)}"></td>
      <td><input data-col="qty" class="num" value="${row.qty||0}"></td>
      <td><input data-col="weight" class="num" value="${fmt(row.weight,3)}"></td>
      <td><input data-col="mud_less" class="num" value="${fmt(row.mud_less,3)}"></td>
      <td><input data-col="stone_wgt" class="num" value="${fmt(row.stone_wgt,3)}"></td>
      <td><input data-col="stone_price" class="num" value="${fmt(row.stone_price)}"></td>
      <td><input data-col="touch" class="num" value="${fmt(row.touch)}"></td>
      <td><input data-col="less_perc" class="num" value="${fmt(row.less_perc)}"></td>
      <td><input data-col="less_wgt" class="num" value="${fmt(row.less_wgt,3)}"></td>
      <td><input data-col="extra_wgt" class="num" value="${fmt(row.extra_wgt,3)}"></td>
      <td style="text-align:right;padding-right:4px">${fmt(row.net_wgt,3)}</td>
      <td><input data-col="amount" class="num" value="${fmt(row.amount)}"></td>
    `;
    tbody.appendChild(tr);
  });
  updateExchFooter();
}

function updateExchFooter() {
  let tQty=0,tWgt=0,tAmt=0;
  exchRows.forEach(r => { tQty+=toNum(r.qty); tWgt+=toNum(r.weight); tAmt+=toNum(r.amount); });
  $('exchRowCount').textContent = exchRows.length;
  $('exftQty').textContent = tQty;
  $('exftWeight').textContent = fmt(tWgt,3);
  $('exftAmount').textContent = fmt(tAmt,2);
  $('exExAmt').value = fmt(tAmt,2);
  $('exBillAmt').value = $('fBillTotal').value;
  $('exNet').value = fmt(toNum($('fBillTotal').value) - tAmt, 2);
  // PB parity: sale weight from first item row net weight
  let saleWgt = 0;
  if (itemRows.length > 0) {
    const r0 = itemRows[0];
    saleWgt = toNum(r0.net_wgt || (toNum(r0.weight) - toNum(r0.stone_wgt)));
    if (saleWgt < 0) saleWgt = 0;
  }
  $('exSaleWgt').value = fmt(saleWgt, 3);
}

function recalcExchRow(idx, changedCol) {
  let r = exchRows[idx];
  if (!r) return;
  let wgt = toNum(r.weight);
  let mud = toNum(r.mud_less);
  let stwgt = toNum(r.stone_wgt);
  let stprice = toNum(r.stone_price);
  let lessPerc = toNum(r.less_perc);
  let lessWgt = toNum(r.less_wgt);
  let rate = toNum(r.rate);

  if (changedCol === 'less_perc' && lessPerc > 0) {
    lessWgt = ((wgt - mud - stwgt) * lessPerc) / 100;
    r.less_wgt = Math.round(lessWgt * 1000) / 1000;
  }
  let netWgt = Math.max(wgt - mud - stwgt - lessWgt, 0);
  r.net_wgt = Math.round(netWgt * 1000) / 1000;
  r.amount = Math.round((netWgt * rate + stprice) * 100) / 100;
  renderExchTable();
}

function exchShouldLookup(idx, code) {
  const r = exchRows[idx];
  if (!r) return false;
  const prevCode = String(r.item_code || '').toUpperCase();
  const nextCode = String(code || '').toUpperCase();
  return nextCode !== '' && (prevCode !== nextCode || !String(r.item_name || '').trim());
}

// Exchange table events
$('exchTbody').addEventListener('blur', function(e) {
  const inp = e.target;
  if (inp.tagName !== 'INPUT') return;
  const tr = inp.closest('tr');
  if (!tr) return;
  const idx = parseInt(tr.dataset.idx);
  const col = inp.dataset.col;
  const r = exchRows[idx];
  if (!r) return;
  if (col === 'item_code') {
    const code = inp.value.trim().toUpperCase();
    inp.value = code;
    if (exchShouldLookup(idx, code)) {
      exchRows[idx].item_code = code;
      exchItemLookup(idx);
    }
    return;
  }
  const numCols = ['rate','qty','weight','mud_less','stone_wgt','stone_price','touch','less_perc','less_wgt','extra_wgt','amount'];
  if (numCols.includes(col)) r[col] = toNum(inp.value);
  else r[col] = inp.value.trim();
  if (numCols.includes(col)) recalcExchRow(idx, col);
}, true);

$('exchTbody').addEventListener('keydown', function(e) {
  const inp = e.target;
  if (inp.tagName !== 'INPUT') return;
  const tr = inp.closest('tr');
  if (!tr) return;
  const idx = parseInt(tr.dataset.idx);
  const col = inp.dataset.col;
  const navCols = ['item_code','rate','qty','weight','mud_less','stone_wgt','stone_price','touch','less_perc','less_wgt','extra_wgt','amount'];
  const ci = navCols.indexOf(col);

  if (e.key === 'Enter') {
    e.preventDefault();
    if (col === 'item_code') {
      const code = inp.value.trim().toUpperCase();
      inp.value = code;
      if (exchShouldLookup(idx, code)) {
        exchRows[idx].item_code = code;
        exchItemLookup(idx);
      } else {
        const inputs = Array.from(tr.querySelectorAll('input[data-col]'));
        if (inputs.length > 1) { inputs[1].focus(); inputs[1].select(); }
      }
      return;
    }
    const inputs = Array.from(tr.querySelectorAll('input[data-col]'));
    const ci = inputs.indexOf(inp);
    if (ci < inputs.length - 1) {
      inputs[ci+1].focus();
      inputs[ci+1].select();
    } else {
      if (idx === exchRows.length - 1) {
        exchRows.push(newExchRow());
        renderExchTable();
      }
      setTimeout(() => {
        const n = document.querySelector(`#exchTbody tr[data-idx="${idx+1}"] input[data-col="item_code"]`);
        if (n) { n.focus(); n.select(); }
      }, 30);
    }
  }

  if (e.key === 'Tab') {
    e.preventDefault();

    if (col === 'item_code') {
      const code = inp.value.trim().toUpperCase();
      inp.value = code;
      if (exchShouldLookup(idx, code)) {
        exchRows[idx].item_code = code;
        exchItemLookup(idx);
      } else {
        const nextCol = e.shiftKey ? 'amount' : 'rate';
        setTimeout(() => {
          const n = document.querySelector(`#exchTbody tr[data-idx="${idx}"] input[data-col="${nextCol}"]`);
          if (n) { n.focus(); n.select(); }
        }, 0);
      }
      return;
    }

    // Commit value before moving because re-render can reset native tab flow.
    const r = exchRows[idx];
    if (r) {
      const numCols = ['rate','qty','weight','mud_less','stone_wgt','stone_price','touch','less_perc','less_wgt','extra_wgt','amount'];
      if (numCols.includes(col)) r[col] = toNum(inp.value);
      else r[col] = inp.value.trim();
      if (numCols.includes(col)) recalcExchRow(idx, col);
    }

    let nextRow = idx;
    let nextColIndex = ci + (e.shiftKey ? -1 : 1);

    if (nextColIndex < 0) {
      if (idx > 0) {
        nextRow = idx - 1;
        nextColIndex = navCols.length - 1;
      } else {
        nextColIndex = 0;
      }
    } else if (nextColIndex >= navCols.length) {
      nextColIndex = 0;
      nextRow = idx + 1;
      if (nextRow >= exchRows.length) {
        exchRows.push(newExchRow());
        renderExchTable();
      }
    }

    const nextCol = navCols[nextColIndex];
    setTimeout(() => {
      const n = document.querySelector(`#exchTbody tr[data-idx="${nextRow}"] input[data-col="${nextCol}"]`);
      if (n) { n.focus(); n.select(); }
    }, 30);
  }
});

function exchItemLookup(idx) {
  const r = exchRows[idx];
  if (!r || !r.item_code) return;
  const goldRate = toNum($('fRatePerGm').value);
  const item = CFG.exchItems.find(i => String(i.code || '').toUpperCase() === r.item_code.toUpperCase());
  if (item) {
    r.item_name = item.name;
    r.rate = item.itype === 'S' ? CFG.rates.SRATE : goldRate;
    r.cost = item.cost || 0;
    renderExchTable();
    setTimeout(() => {
      const nxt = document.querySelector(`#exchTbody tr[data-idx="${idx}"] input[data-col="weight"]`);
      if (nxt) { nxt.focus(); nxt.select(); }
    }, 30);
    return;
  }
  // Fallback to item-search for codes outside preloaded list.
  api(`/api/order-sale/item-search?q=${encodeURIComponent(r.item_code)}`).then(s => {
    if (!s.ok || !Array.isArray(s.results) || s.results.length === 0) return;
    const exact = s.results.find(x => String(x.code || '').toUpperCase() === r.item_code.toUpperCase()) || s.results[0];
    r.item_code = String(exact.code || r.item_code).toUpperCase();
    r.item_name = exact.name || '';
    r.rate = String(exact.itype || 'G').toUpperCase() === 'S' ? CFG.rates.SRATE : goldRate;
    renderExchTable();
    setTimeout(() => {
      const nxt = document.querySelector(`#exchTbody tr[data-idx="${idx}"] input[data-col="weight"]`);
      if (nxt) { nxt.focus(); nxt.select(); }
    }, 30);
  });
}

$('btnExchAdd').addEventListener('click', () => {
  exchRows.push(newExchRow());
  const idx = exchRows.length - 1;
  renderExchTable();
  setTimeout(() => {
    const n = document.querySelector(`#exchTbody tr[data-idx="${idx}"] input[data-col="item_code"]`);
    if (n) { n.focus(); n.select(); }
  }, 30);
});
$('btnExchDel').addEventListener('click', () => {
  if (exchRows.length > 0) exchRows.pop();
  renderExchTable();
});

$('btnExchange').addEventListener('click', () => {
  // Re-open with existing exchange rows if already saved/entered.
  if (!Array.isArray(exchRows)) exchRows = [];

  // PB parity: pass bill total and first-row net weight context.
  $('exBillAmt').value = fmt(toNum($('fBillTotal').value), 2);
  let dtwgt = 0;
  if (itemRows.length > 0) {
    const r0 = itemRows[0];
    dtwgt = toNum(r0.net_wgt || (toNum(r0.weight) - toNum(r0.stone_wgt)));
    if (dtwgt < 0) dtwgt = 0;
  }
  $('exSaleWgt').value = fmt(dtwgt, 3);

  // Insert default exchange row only when no rows exist yet.
  if (exchRows.length === 0) {
    const defCode = String((CFG.software && CFG.software.DefExchItem) || 'OG').toUpperCase();
    exchRows = [newExchRow()];
    exchRows[0].item_code = defCode;
    exchItemLookup(0);
  }

  $('exchangeModal').classList.add('active');
  renderExchTable();
  setTimeout(() => {
    const first = document.querySelector('#exchTbody tr[data-idx="0"] input[data-col="item_code"]');
    if (first) { first.focus(); first.select(); }
  }, 30);
});
$('btnExchSave').addEventListener('click', () => {
  // PB-like validation: ignore empty rows, block invalid non-empty rows.
  const valid = [];
  for (const r of exchRows) {
    const code = String(r.item_code || '').trim();
    const qty = toNum(r.qty);
    const wgt = toNum(r.weight);
    if (!code || (qty === 0 && wgt === 0)) continue;
    if (wgt <= 0) {
      showMsg('Warning', `Check Weight (${code}). You can't save...`);
      return;
    }
    valid.push(r);
  }
  exchRows = valid;

  let tAmt = 0;
  exchRows.forEach(r => tAmt += toNum(r.amount));
  $('fExchangeAmt').value = fmt(tAmt, 2);
  closeModal('exchangeModal');
  triggerRecalc();
  // PB parity: return focus to sale grid
  setTimeout(() => {
    const focusRow = Math.max(0, selectedRow);
    const fi = document.querySelector(`#itemsTbody tr[data-idx="${focusRow}"] input[data-col="item_code"]`) ||
               document.querySelector('#itemsTbody tr[data-idx="0"] input[data-col="item_code"]');
    if (fi) fi.focus();
  }, 30);
});

// ── Sales Return Modal ─────────────────────────────────────
function newSrRow() {
  return {
    item_code:'', item_name:'', model:'', qty:0, weight:0, stone_wgt:0,
    net_wgt:0, stone_price:0, wastage:0, vaperc:0, making_charge:0,
    rate: toNum($('fRatePerGm').value), amount:0, stktype:''
  };
}

function renderSrTable() {
  const tbody = $('srTbody');
  tbody.innerHTML = '';
  srRows.forEach((row, idx) => {
    const tr = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML = `
      <td><input data-col="item_code" value="${row.item_code}" style="text-transform:uppercase"></td>
      <td style="text-align:left;font-size:10px">${row.item_name||''}</td>
      <td><input data-col="model" value="${row.model||''}"></td>
      <td><input data-col="qty" class="num" value="${row.qty||0}"></td>
      <td><input data-col="weight" class="num" value="${fmt(row.weight,3)}"></td>
      <td><input data-col="stone_wgt" class="num" value="${fmt(row.stone_wgt,3)}"></td>
      <td style="text-align:right;padding-right:4px">${fmt(row.net_wgt,3)}</td>
      <td><input data-col="stone_price" class="num" value="${fmt(row.stone_price)}"></td>
      <td><input data-col="wastage" class="num" value="${fmt(row.wastage,3)}"></td>
      <td><input data-col="vaperc" class="num" value="${fmt(row.vaperc)}"></td>
      <td><input data-col="making_charge" class="num" value="${fmt(row.making_charge)}"></td>
      <td><input data-col="rate" class="num" value="${fmt(row.rate)}"></td>
      <td style="text-align:right;padding-right:4px;font-weight:600">${fmt(row.amount)}</td>
    `;
    tbody.appendChild(tr);
  });
  updateSrFooter();
}

function updateSrFooter() {
  let tQty=0,tWgt=0,tAmt=0;
  srRows.forEach(r => { tQty+=toNum(r.qty); tWgt+=toNum(r.weight); tAmt+=toNum(r.amount); });
  $('srRowCount').textContent = srRows.length;
  $('srftQty').textContent = tQty;
  $('srftWeight').textContent = fmt(tWgt,3);
  $('srftAmount').textContent = fmt(tAmt,2);
  $('srBillAmt').value = fmt(tAmt,2);
  $('srRetAmt').value = fmt(tAmt,2);
}

function recalcSrRow(idx, changedCol, rerender = true) {
  let r = srRows[idx];
  if (!r) return;
  let wgt = toNum(r.weight);
  let stwgt = toNum(r.stone_wgt);
  let stprice = toNum(r.stone_price);
  let rate = toNum(r.rate);
  let mc = toNum(r.making_charge);
  let netwgt = Math.max(wgt - stwgt, 0);
  let vaperc = toNum(r.vaperc);

  if (changedCol === 'vaperc') {
    mc = (netwgt * rate * vaperc) / 100;
    r.making_charge = Math.round(mc * 100) / 100;
  }

  r.net_wgt = Math.round(netwgt * 1000) / 1000;
  r.amount = Math.round(((netwgt * rate) + stprice + mc) * 100) / 100;
  if (rerender) {
    renderSrTable();
  } else {
    const tr = document.querySelector(`#srTbody tr[data-idx="${idx}"]`);
    if (tr) {
      const netCell = tr.children[6];
      if (netCell) netCell.textContent = fmt(r.net_wgt, 3);
      const amtCell = tr.children[12];
      if (amtCell) amtCell.textContent = fmt(r.amount, 2);
      const mcInput = tr.querySelector('input[data-col="making_charge"]');
      if (mcInput && changedCol === 'vaperc') mcInput.value = fmt(r.making_charge, 2);
    }
    updateSrFooter();
  }
}

function srShouldLookup(idx, code) {
  const row = srRows[idx];
  if (!row) return false;
  if (!code) return false;
  const cur = String(row.item_code || '').toUpperCase();
  const name = String(row.item_name || '').trim();
  return cur !== code || name === '';
}

$('srTbody').addEventListener('blur', function(e) {
  const inp = e.target;
  if (inp.tagName !== 'INPUT') return;
  const tr = inp.closest('tr');
  if (!tr) return;
  const idx = parseInt(tr.dataset.idx);
  const col = inp.dataset.col;
  const r = srRows[idx];
  if (!r) return;
  const numCols = ['qty','weight','stone_wgt','stone_price','wastage','vaperc','making_charge','rate'];
  if (numCols.includes(col)) r[col] = toNum(inp.value);
  else r[col] = inp.value.trim();
  if (col === 'item_code') {
    const code = String(inp.value || '').trim().toUpperCase();
    inp.value = code;
    if (srShouldLookup(idx, code)) {
      r.item_code = code;
      srItemLookup(idx);
      return;
    }
  }
  if (numCols.includes(col)) recalcSrRow(idx, col, false);
}, true);

$('srTbody').addEventListener('keydown', function(e) {
  const inp = e.target;
  if (inp.tagName !== 'INPUT') return;
  const tr = inp.closest('tr');
  if (!tr) return;
  const idx = parseInt(tr.dataset.idx);
  const col = inp.dataset.col;

  if (e.key === 'Enter') {
    e.preventDefault();
    if (col === 'item_code') {
      const code = inp.value.trim().toUpperCase();
      inp.value = code;
      if (srShouldLookup(idx, code)) {
        srRows[idx].item_code = code;
        srItemLookup(idx);
      }
      return;
    }
    const inputs = Array.from(tr.querySelectorAll('input[data-col]'));
    const ci = inputs.indexOf(inp);
    if (ci < inputs.length - 1) {
      inputs[ci+1].focus();
      inputs[ci+1].select();
      return;
    }
    srRows.push(newSrRow());
    renderSrTable();
    setTimeout(() => {
      const n = document.querySelector(`#srTbody tr[data-idx="${srRows.length - 1}"] input[data-col="item_code"]`);
      if (n) { n.focus(); n.select(); }
    }, 30);
    return;
  }

  if (e.key === 'Tab' && col === 'item_code') {
    const code = inp.value.trim().toUpperCase();
    inp.value = code;
    if (srShouldLookup(idx, code)) {
      srRows[idx].item_code = code;
      srItemLookup(idx);
    }
    return;
  }

  if (e.key === 'Tab') {
    const inputs = Array.from(tr.querySelectorAll('input[data-col]'));
    const ci = inputs.indexOf(inp);
    if (ci === inputs.length - 1) {
      e.preventDefault();
      srRows.push(newSrRow());
      renderSrTable();
      setTimeout(() => {
        const n = document.querySelector(`#srTbody tr[data-idx="${srRows.length - 1}"] input[data-col="item_code"]`);
        if (n) { n.focus(); n.select(); }
      }, 30);
    }
  }
});

function srItemLookup(idx) {
  const r = srRows[idx];
  if (!r || !r.item_code) return;
  const goldRate = toNum($('fRatePerGm').value);
  api(`/api/order-sale/item-lookup?code=${encodeURIComponent(r.item_code)}&gold_rate=${goldRate}`)
    .then(d => {
      if (!d.ok) return;
      r.item_name = d.item_name || '';
      r.rate = d.rate || goldRate;
      r.stktype = d.stktype || '';
      renderSrTable();
    });
}

$('btnSrAdd').addEventListener('click', () => {
  srRows.push(newSrRow());
  renderSrTable();
  setTimeout(() => {
    const n = document.querySelector(`#srTbody tr[data-idx="${srRows.length - 1}"] input[data-col="item_code"]`);
    if (n) { n.focus(); n.select(); }
  }, 30);
});
$('btnSrDel').addEventListener('click', () => {
  if (srRows.length > 0) srRows.pop();
  renderSrTable();
});

$('btnSalesReturn').addEventListener('click', () => {
  // Re-open with existing sales-return rows if already saved/entered.
  if (!Array.isArray(srRows)) srRows = [];
  if (srRows.length === 0) srRows = [newSrRow()];
  $('salesReturnModal').classList.add('active');
  renderSrTable();
  setTimeout(() => {
    const first = document.querySelector('#srTbody tr[data-idx="0"] input[data-col="item_code"]');
    if (first) { first.focus(); first.select(); }
  }, 30);
});
$('btnSrSave').addEventListener('click', () => {
  // PB-like validation: ignore empty rows, block invalid non-empty rows.
  const valid = [];
  for (const r of srRows) {
    const code = String(r.item_code || '').trim();
    const qty = toNum(r.qty);
    const wgt = toNum(r.weight);
    if (!code || (qty === 0 && wgt === 0)) continue;
    if (wgt <= 0) {
      showMsg('Warning', `Check Weight (${code}). You can't save...`);
      return;
    }
    valid.push(r);
  }
  srRows = valid;

  let tAmt = 0;
  srRows.forEach(r => tAmt += toNum(r.amount));
  $('fSretAmt').value = fmt(tAmt, 2);
  closeModal('salesReturnModal');
  triggerRecalc();
  // PB parity: return focus to sale grid.
  setTimeout(() => {
    const focusRow = Math.max(0, selectedRow);
    const fi = document.querySelector(`#itemsTbody tr[data-idx="${focusRow}"] input[data-col="item_code"]`) ||
               document.querySelector('#itemsTbody tr[data-idx="0"] input[data-col="item_code"]');
    if (fi) fi.focus();
  }, 30);
});

// ── Recalc (legacy PB formula, client-side) ───────────────
let recalcTimer = null;
function triggerRecalc() {
  clearTimeout(recalcTimer);
  recalcTimer = setTimeout(() => doRecalc(1), 120);
}

function doRecalc(rparm = 0, source = '') {
  let dbamt = toNum($('fBillTotal').value);
  let ddisc = toNum($('fDiscountAmt').value);
  let dexamt = toNum($('fExchangeAmt').value);
  let dsretamt = toNum($('fSretAmt').value);
  const taxPerc = toNum($('fTaxPerc').value);
  let dtax = taxPerc > 0 ? Math.round(dbamt * taxPerc / 100) : toNum($('fTaxAmt').value);
  if (taxPerc > 0) $('fTaxAmt').value = fmt(dtax, 2);
  let dast = toNum($('fCessAmt').value);
  let dhmc = toNum($('fHmc').value);
  let drcamt = toNum($('fRepair').value);
  let drddisc = toNum($('fRddiscAmt').value);
  let dschmamt = toNum($('fSchemeAmt').value);
  let dadvance = toNum($('fCashAdv').value);
  let drcvd = toNum($('fReceived').value);
  let dccamt = toNum($('fCcardAmt').value);
  let dchqamt = toNum($('fChqAmt').value);
  let dcashloss = toNum($('fCashLess').value);
  let dround = toNum((CFG.software && CFG.software.AmtRoundTo) || 0);

  let dnetamt = dbamt - dadvance - dexamt - dsretamt + dtax + dast + dhmc + drcamt - drddisc - dschmamt;
  if (dround === 1) dnetamt = Math.round(dnetamt);
  $('fNetTotal').value = fmt(dnetamt, 2);

  // Recompute discount amount from % only when the % field was edited
  // or when there is no discount amount yet.
  if (source === 'discount_perc' || (ddisc <= 0 && source !== 'discount_amt' && source !== 'received')) {
    const dperc = toNum($('fDiscountPerc').value);
    if (dperc > 0) {
      const discOnNet = String((CFG.software && CFG.software.DiscBasedOnNetAmt) || 'N').toUpperCase() === 'Y';
      ddisc = ((discOnNet ? dnetamt : dbamt) * dperc) / 100;
      $('fDiscountAmt').value = fmt(ddisc, 2);
    }
  }

  // Auto-fill received only if user has not manually touched it.
  if (rparm === 1 && !receivedTouched && !isLoadingBill) {
    if ($('fCredit').checked) {
      drcvd = 0;
      $('fReceived').value = '.00';
    } else {
      drcvd = dnetamt - ddisc - dccamt - dchqamt - dcashloss;
      $('fReceived').value = fmt(drcvd, 2);
    }
  }

  const dbal = dnetamt - ddisc - drcvd - dccamt - dchqamt - dcashloss;
  $('fBalance').value = fmt(dbal, 2);
  $('fCB').value = fmt(toNum($('fOB').value) - dbal, 2);

  // BC amount from CC amount and BC %.
  const bcperc = toNum($('fBcPerc').value);
  const ccamt = toNum($('fCcardAmt').value);
  if (bcperc > 0 && ccamt > 0 && source !== 'bc_amt') {
    $('fBcAmt').value = fmt((ccamt * bcperc) / 100, 2);
  }
}

// ── Build payload for save/recalc ──────────────────────────
function buildPayload() {
  let orderNo = $('fOrderNo').value.trim().toUpperCase();
  if (orderNo === 'AUTO') orderNo = '';
  return {
    bill_no: $('fBillNo').value.trim(),
    order_no: orderNo,
    without_order_mode: CFG.mode === 'without-order',
    bill_date: $('fDate').value.trim(),
    due_date: $('fDueDate').value.trim(),
    bill_type: $('fBillType').value,
    customer_name: $('fCustName').value.trim(),
    customer_code: $('fCustomerCode').value.trim(),
    address: $('fAddress') ? $('fAddress').value.trim() : '',
    mobile: $('fMobile').value.trim(),
    gst_no: $('fGstNo').value.trim(),
    pan_no: $('fPanNo').value.trim(),
    state_code: $('fState').value,
    supply_place: $('fSupplyPlace').value.trim(),
    rate_per_gm: toNum($('fRatePerGm').value),
    counter_code: $('fCounter').value,
    salesman_code: $('fSalesman').value,
    cashbank_code: $('fCashBank').value,
    agent_code: $('fAgent').value,
    approved_by: $('fApprovedBy').value,
    items: itemRows.filter(r => r.item_code).map(r => ({
      item_code: r.item_code, item_name: r.item_name, model: r.model||'',
      qty: r.qty, weight: r.weight, stone_wgt: r.stone_wgt,
      stone_price: r.stone_price, making_charge: r.making_charge,
      wastage: r.wastage, rate: r.rate, amount: r.amount,
      vaperc: r.vaperc, stktype: r.stktype, iqtype: r.iqtype||'',
      stktouch: r.stktouch||0, bcode: r.bcode||0, huid: r.huid||'', cost: r.cost||0
    })),
    exchange: exchRows.filter(r => r.item_code).map(r => ({
      item_code: r.item_code, item_name: r.item_name,
      qty: r.qty, weight: r.weight, rate: r.rate,
      less_wgt: r.less_wgt, less_perc: r.less_perc,
      amount: r.amount, stone_wgt: r.stone_wgt, stone_price: r.stone_price,
      mud_less: r.mud_less, stktype: r.stktype||'', cost: r.cost||0
    })),
    sales_return: srRows.filter(r => r.item_code).map(r => ({
      item_code: r.item_code, item_name: r.item_name,
      qty: r.qty, weight: r.weight, rate: r.rate,
      stone_wgt: r.stone_wgt, stone_price: r.stone_price,
      making_charge: r.making_charge, wastage: r.wastage,
      amount: r.amount, stktype: r.stktype||''
    })),
    extra: {
      discount: toNum($('fDiscountAmt').value),
      discount_perc: toNum($('fDiscountPerc').value),
      tax: toNum($('fTaxAmt').value),
      tax_perc: toNum($('fTaxPerc').value),
      ast: toNum($('fCessAmt').value),
      repair_charge: toNum($('fRepair').value),
      hallmark_charge: toNum($('fHmc').value),
      advance: orderAdvance,
      fancy_amt: 0,
      scheme_amt: toNum($('fSchemeAmt').value),
      bank_charge: toNum($('fBcAmt').value),
      bc_perc: toNum($('fBcPerc').value),
      cash_less: toNum($('fCashLess').value),
      ccard_amt: toNum($('fCcardAmt').value),
      chq_amt: toNum($('fChqAmt').value),
      chq_date: $('fChqDate').value.trim(),
      chq_no: $('fChqNo').value.trim(),
      pdc: $('fPdc').checked,
      ccard_pdc: $('fCcardPdc').checked,
      interstate: $('fInterstate').checked,
      add_bank_charge: false,
      opening_balance: toNum($('fOB').value),
      received: toNum($('fReceived').value),
      credit: $('fCredit').checked,
      note: $('fNote').value.trim(),
      redeem_points: $('fRedeemPoints').checked ? 1 : 0,
      print_wgt_amt_bal: $('fPrintWgtAmtBal').checked,
      laser_print: $('fLaserPrint').checked,
      tcs_perc: 0,
    }
  };
}

// ── Save Bill ──────────────────────────────────────────────
function openSavedBillPrint(slno) {
  const billSlno = Number(slno || 0);
  if (!billSlno) return;
  const printUrl = `${CFG.siteUrl}/sales-bill-print?slno=${encodeURIComponent(billSlno)}`;
  const win = window.open(printUrl, '_blank', 'width=950,height=820,scrollbars=yes');
  if (!win) window.location.href = printUrl;
}

function saveBill() {
  if (CFG.mode !== 'without-order' && !currentOrderNo && !$('fOrderNo').value.trim()) {
    showMsg('Error', 'Order number is required.');
    return;
  }
  const payload = buildPayload();
  if (CFG.mode !== 'without-order' && !payload.order_no) {
    showMsg('Error', 'Order number is required.');
    return;
  }
  if (!payload.items || payload.items.length === 0) {
    showMsg('Error', 'Add at least one item.');
    return;
  }
  api('/api/order-sale/save', { method: 'POST', json: payload })
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Save failed.'); return; }
      $('fBillNo').value = d.bill_no || '';
      isEditMode = true;
      const saveMessage = String(d.message || '').trim() || ('Bill saved: ' + (d.bill_no || ''));
      showMsg('Saved', saveMessage, () => {
        openSavedBillPrint(d.slno || 0);
        setTimeout(() => {
          window.location.href = `${CFG.siteUrl}/order-sale/bill`;
        }, 100);
      });
    });
}

$('btnSave').addEventListener('click', saveBill);
$('btnPrev').addEventListener('click', () => navBill('prev'));

// ── Account Ledger Modal ───────────────────────────────────
let _ledgerCode = '';
function fmtBal(val) {
  const n = parseFloat(val) || 0;
  return (Math.abs(n)).toFixed(2) + ' ' + (n < 0 ? 'Dr' : 'Cr');
}
function fmtDate(d) {
  if (!d) return '';
  const p = String(d).split('-');
  return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : d;
}
async function openLedger(code) {
  if (!code) { showMsg('Error', 'Select a customer first.'); return; }
  _ledgerCode = code;
  const today = new Date().toISOString().slice(0,10);
  const yearStart = today.slice(0,4) + '-04-01';
  if (!$('ledgerDate1').value) $('ledgerDate1').value = yearStart;
  if (!$('ledgerDate2').value) $('ledgerDate2').value = today;
  $('ledgerModal').style.display = 'flex';
  await fetchLedger();
}
async function fetchLedger() {
  const code = _ledgerCode;
  const d1 = $('ledgerDate1').value;
  const d2 = $('ledgerDate2').value;
  $('ledgerRows').innerHTML = '<tr><td colspan="7" style="text-align:center;padding:14px;color:#64748b;">Loading...</td></tr>';
  try {
    const res = await api(`/api/accounts/customer-ledger?code=${encodeURIComponent(code)}&date1=${encodeURIComponent(d1)}&date2=${encodeURIComponent(d2)}`);
    if (!res.ok) { $('ledgerRows').innerHTML = `<tr><td colspan="7" style="text-align:center;padding:14px;color:#dc2626;">${res.message||'Not found'}</td></tr>`; return; }
    $('ledgerTitle').textContent = `${res.name} (${res.accode})`;
    $('ledgerOB').textContent  = fmtBal(res.opening_balance);
    $('ledgerTotDr').textContent = parseFloat(res.totals.debit).toFixed(2);
    $('ledgerTotCr').textContent = parseFloat(res.totals.credit).toFixed(2);
    $('ledgerCB').textContent  = fmtBal(res.closing_balance);
    if (!res.rows.length) {
      $('ledgerRows').innerHTML = '<tr><td colspan="7" style="text-align:center;padding:14px;color:#64748b;">No transactions in this period.</td></tr>';
      return;
    }
    $('ledgerRows').innerHTML = res.rows.map((r, i) => {
      const bg = i % 2 === 0 ? '#fff' : '#f8fafc';
      const url = r.navigate_url || '';
      const vch = url ? `<a href="${url}" target="_blank" style="color:#1d4ed8;text-decoration:none;">${r.vchno||''}</a>` : (r.vchno||'');
      return `<tr style="background:${bg};">
        <td style="padding:4px 8px;">${fmtDate(r.date)}</td>
        <td style="padding:4px 8px;">${vch}</td>
        <td style="padding:4px 8px;">${r.othacname||''}</td>
        <td style="padding:4px 8px;">${r.part||''}</td>
        <td style="padding:4px 8px;text-align:right;">${r.debit > 0 ? parseFloat(r.debit).toFixed(2) : ''}</td>
        <td style="padding:4px 8px;text-align:right;">${r.credit > 0 ? parseFloat(r.credit).toFixed(2) : ''}</td>
        <td style="padding:4px 8px;text-align:right;font-weight:600;">${fmtBal(r.running_balance)}</td>
      </tr>`;
    }).join('');
  } catch(e) {
    $('ledgerRows').innerHTML = `<tr><td colspan="7" style="text-align:center;padding:14px;color:#dc2626;">Error: ${e.message}</td></tr>`;
  }
}
$('btnLedger').addEventListener('click', () => {
  const code = $('fCustomerCode').value || '';
  openLedger(code);
});
$('ledgerClose').addEventListener('click', () => { $('ledgerModal').style.display = 'none'; });
$('ledgerRefresh').addEventListener('click', fetchLedger);
$('ledgerDate2').addEventListener('keydown', e => { if (e.key === 'Enter') fetchLedger(); });
$('ledgerModal').addEventListener('click', e => { if (e.target === $('ledgerModal')) $('ledgerModal').style.display = 'none'; });
$('btnNext').addEventListener('click', () => navBill('next'));
$('btnShortPrint').addEventListener('click', () => {
  const billNo = $('fBillNo').value.trim();
  if (!billNo) {
    showMsg('Error', 'Save or load a bill first.');
    return;
  }
  api(`/api/order-sale/get?bill_no=${encodeURIComponent(billNo)}`)
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Bill not found.'); return; }
      openSavedBillPrint(d.slno || 0);
    });
});


// ── Load existing bill ─────────────────────────────────────
function loadBill(billNo) {
  api(`/api/order-sale/get?bill_no=${encodeURIComponent(billNo)}`)
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Bill not found.'); return; }
      isLoadingBill = true;
      isEditMode = true;
      $('fBillNo').value = d.bill_no || '';
      $('fOrderNo').value = d.order_no || '';
      currentOrderNo = d.order_no || '';
      $('fDate').value = d.bill_date || '';
      $('fBillType').value = d.bill_type || 'G';
      $('fCustName').value = d.customer_name || '';
      $('fCustomerCode').innerHTML = `<option value="${d.customer_code||''}">${d.customer_code||''} - ${d.customer_name||''}</option>`;
      $('fCustomerCode').value = d.customer_code || '';
      if ($('fAddress')) $('fAddress').value = d.address || '';
      $('fMobile').value = d.mobile || '';
      $('fGstNo').value = d.gst_no || '';
      $('fPanNo').value = d.pan_no || '';
      $('fRatePerGm').value = fmt(d.rate_per_gm, 2);
      $('fRatePer8gm').value = fmt(d.rate_per_gm * 8, 2);
      $('fCounter').value = d.counter_code || '';
      $('fSalesman').value = d.salesman_code || '';
      $('fCashBank').value = d.cashbank_code || '';
      $('fAgent').value = d.agent_code || '';
      $('fApprovedBy').value = d.approved_by || '';
      if (d.state_code) $('fState').value = d.state_code;
      $('fSupplyPlace').value = d.supply_place || '';
      $('fDueDate').value = d.due_date || '';

      const ex = d.extra || {};
      $('fOB').value = fmt(ex.opening_balance, 2);
      orderAdvance = toNum(ex.advance);
      $('fCashAdv').value = fmt(ex.advance, 2);
      $('fTotAdv').value = fmt(ex.advance, 2);
      $('fSchemeAmt').value = fmt(ex.scheme_amt, 2);
      $('fDiscountAmt').value = fmt(ex.discount, 2);
      $('fDiscountPerc').value = fmt(ex.discount_perc, 3);
      $('fTaxPerc').value = fmt(ex.tax_perc, 3);
      $('fTaxAmt').value = fmt(ex.tax, 2);
      $('fCessAmt').value = fmt(ex.ast, 2);
      $('fRepair').value = fmt(ex.repair_charge, 2);
      $('fHmc').value = fmt(ex.hallmark_charge, 2);
      $('fReceived').value = fmt(ex.received, 2);
      $('fBcAmt').value = fmt(ex.bank_charge, 2);
      $('fBcPerc').value = fmt(ex.bc_perc, 2);
      $('fCashLess').value = fmt(ex.cash_less, 2);
      $('fCcardAmt').value = fmt(ex.ccard_amt, 2);
      $('fChqAmt').value = fmt(ex.chq_amt, 2);
      $('fChqDate').value = ex.chq_date || '';
      $('fChqNo').value = ex.chq_no || '';
      $('fPdc').checked = !!ex.pdc;
      $('fCcardPdc').checked = !!ex.ccard_pdc;
      $('fInterstate').checked = !!ex.interstate;
      $('fNote').value = ex.note || '';
      $('fCredit').checked = !!ex.credit;
      $('fRedeemPoints').checked = !!toNum(ex.redeem_points || 0);
      $('fPrintWgtAmtBal').checked = !!ex.print_wgt_amt_bal;
      $('fLaserPrint').checked = !!ex.laser_print;

      $('fBillTotal').value = fmt(d.bill_total, 2);
      $('fExchangeAmt').value = fmt(d.exchange_amount, 2);
      $('fSretAmt').value = fmt(d.return_amount, 2);
      $('fNetTotal').value = fmt(d.net_total, 2);

      // Items
      itemRows = (d.items || []).map(it => ({
        item_code: it.item_code||'', item_name: it.item_name||'',
        model: it.model||'', qty: toNum(it.qty), weight: toNum(it.weight),
        stone_wgt: toNum(it.stone_wgt),
        net_wgt: Math.max(toNum(it.weight) - toNum(it.stone_wgt), 0),
        stone_price: toNum(it.stone_price), wastage: toNum(it.wastage),
        vaperc: toNum(it.vaperc), making_charge: toNum(it.making_charge),
        rate: toNum(it.rate), amount: toNum(it.amount),
        stktype: it.stktype||'', stktouch: toNum(it.stktouch),
        bcode: toNum(it.bcode), huid: it.huid||'', iqtype: it.iqtype||'',
        cost: toNum(it.cost), item_type: 'G'
      }));
      if (itemRows.length === 0) itemRows.push(newItemRow());
      selectedRow = 0;
      renderItemsTable();

      // Exchange
      exchRows = (d.exchange || []).map(ex => ({
        item_code: ex.item_code||'', item_name: ex.item_name||'',
        rate: toNum(ex.rate), qty: toNum(ex.qty), weight: toNum(ex.weight),
        mud_less: toNum(ex.mud_less), stone_wgt: toNum(ex.stone_wgt),
        stone_price: toNum(ex.stone_price), touch: 0, less_perc: toNum(ex.less_perc),
        less_wgt: toNum(ex.less_wgt), extra_wgt: 0,
        net_wgt: Math.max(toNum(ex.weight) - toNum(ex.less_wgt) - toNum(ex.mud_less) - toNum(ex.stone_wgt), 0),
        amount: toNum(ex.amount), stktype: ex.stktype||'', cost: toNum(ex.cost)
      }));

      // Sales return
      srRows = (d.sales_return || []).map(sr => ({
        item_code: sr.item_code||'', item_name: sr.item_name||'',
        model:'', qty: toNum(sr.qty), weight: toNum(sr.weight),
        stone_wgt: toNum(sr.stone_wgt),
        net_wgt: Math.max(toNum(sr.weight) - toNum(sr.stone_wgt), 0),
        stone_price: toNum(sr.stone_price), wastage: toNum(sr.wastage),
        vaperc: 0, making_charge: toNum(sr.making_charge),
        rate: toNum(sr.rate), amount: toNum(sr.amount), stktype: sr.stktype||''
      }));

      // Recalc balance
      let balance = toNum(d.net_total) - toNum(ex.discount) - toNum(ex.received);
      $('fBalance').value = fmt(balance, 2);
      $('fCB').value = fmt(toNum(ex.opening_balance) + balance, 2);
      receivedTouched = true;
      setTimeout(() => {
        isLoadingBill = false;
        receivedTouched = false;
      }, 250);
    });
}

// ── Reset form ─────────────────────────────────────────────
function resetForm() {
  isEditMode = false;
  currentOrderNo = '';
  currentOrderSlno = 0;
  orderAdvance = 0;
  orderGoldAdvWgt = 0;
  orderAmtToWgt = 0;
  $('fBillNo').value = '';
  $('fOrderNo').value = '';
  $('fDate').value = '';
  $('fCustName').value = '';
  $('fCustomerCode').innerHTML = '<option value="">--</option>';
  $('fMobile').value = '';
  if ($('fAddress')) $('fAddress').value = '';
  $('fGstNo').value = '';
  $('fPanNo').value = '';
  $('fOB').value = '0.00';
  $('fRatePerGm').value = fmt(CFG.rates.GRATE, 2);
  $('fRatePer8gm').value = fmt(CFG.rates.GRATE * 8, 2);
  $('fNote').value = '';
  $('fBillTotal').value = '0.00';
  $('fExchangeAmt').value = '0.00';
  $('fSretAmt').value = '0.00';
  $('fNetTotal').value = '0.00';
  $('fSupplyPlace').value = '';
  $('fDueDate').value = '';
  $('fCashAdv').value = '0.00';
  $('fSchemeAmt').value = '.00';
  $('fGoldAdv').value = '0.000';
  $('fTotAdv').value = '.00';
  $('fAdvRate').value = '.00';
  $('fTaxPerc').value = '0.0';
  $('fTaxAmt').value = '.00';
  $('fCessAmt').value = '.00';
  $('fRepair').value = '.00';
  $('fHmc').value = '.00';
  $('fDiscountPerc').value = '.0';
  $('fDiscountAmt').value = '.00';
  $('fReceived').value = '.00';
  $('fBalance').value = '0.00';
  $('fCB').value = '0.00';
  $('fCashLess').value = '.00';
  $('fRddiscAmt').value = '.00';
  $('fCcardAmt').value = '.00';
  $('fBcPerc').value = '.00';
  $('fBcAmt').value = '.00';
  $('fCredit').checked = false;
  $('fRedeemPoints').checked = false;
  $('fPrintWgtAmtBal').checked = false;
  $('fLaserPrint').checked = false;
  const defaultCashBank = getDefaultCashBankCode();
  if (defaultCashBank) $('fCashBank').value = defaultCashBank;
  receivedTouched = false;
  itemRows = [newItemRow()];
  exchRows = [];
  srRows = [];
  selectedRow = 0;
  renderItemsTable();
  $('fOrderNo').focus();
}

// ── Navigation (Prev/Next) ─────────────────────────────────
function navBill(dir) {
  const current = $('fBillNo').value.trim();
  const url = dir === 'prev' ? '/api/order-sale/prev' : '/api/order-sale/next';
  api(`${url}?bill_no=${encodeURIComponent(current)}`)
    .then(d => {
      if (!d.ok) { showMsg('Info', d.message || 'No more bills.'); return; }
      if (!d.bill_no) { showMsg('Info', 'No more bills.'); return; }
      loadBill(d.bill_no);
    });
}

// ── Cancel Bill ────────────────────────────────────────────
function cancelBill() {
  const billNo = $('fBillNo').value.trim();
  if (!billNo) { showMsg('Error', 'No bill loaded to cancel.'); return; }
  if (!confirm('Cancel this order sale bill?')) return;
  api('/api/order-sale/cancel', { method: 'POST', json: { bill_no: billNo } })
    .then(d => {
      if (!d.ok) { showMsg('Error', d.message || 'Cancel failed.'); return; }
      showMsg('Success', 'Bill cancelled.');
      resetForm();
    });
}

// ── Bottom field change => recalc ──────────────────────────
['fTaxAmt','fCessAmt','fRepair','fHmc','fCashLess','fBcAmt','fRddiscAmt','fBcPerc','fCcardAmt','fChqAmt','fSchemeAmt'].forEach(id => {
  $(id).addEventListener('change', () => doRecalc(1, id));
});

$('fTaxPerc').addEventListener('change', () => {
  doRecalc(1, 'tax_perc');
});

$('fBillType').addEventListener('change', () => {
  const sel = $('fBillType');
  const opt = sel.options[sel.selectedIndex];
  const taxperc = parseFloat(opt ? (opt.dataset.taxperc || '0') : '0');
  $('fTaxPerc').value = fmt(taxperc, 3);
  doRecalc(1, 'bill_type');
});

$('fDiscountPerc').addEventListener('change', () => {
  doRecalc(1, 'discount_perc');
});
$('fDiscountAmt').addEventListener('change', () => {
  let amt = toNum($('fDiscountAmt').value);
  let billTotal = toNum($('fBillTotal').value);
  $('fDiscountPerc').value = billTotal > 0 ? fmt((amt * 100) / billTotal, 5) : '.00000';
  doRecalc(1, 'discount_amt');
});
$('fReceived').addEventListener('change', () => {
  receivedTouched = true;
  doRecalc(1, 'received');
});

$('fCredit').addEventListener('change', () => {
  if ($('fCredit').checked) {
    $('fReceived').value = '.00';
    receivedTouched = true;
  }
  doRecalc(1, 'credit');
});

$('fRatePerGm').addEventListener('change', () => {
  let rate = toNum($('fRatePerGm').value);
  $('fRatePer8gm').value = fmt(rate * 8, 2);
});

// ── Close modal buttons ────────────────────────────────────
document.querySelectorAll('[data-close]').forEach(btn => {
  btn.addEventListener('click', () => closeModal(btn.dataset.close));
});

// ── Message modal close ────────────────────────────────────
$('msgModalOk').addEventListener('click', () => {
  $('msgModal').classList.remove('active');
  const handler = msgModalOkHandler;
  msgModalOkHandler = null;
  if (handler) handler();
});
$('msgModal').addEventListener('click', e => {
  if (e.target === $('msgModal')) {
    $('msgModal').classList.remove('active');
    msgModalOkHandler = null;
  }
});

// ── Keyboard shortcuts ─────────────────────────────────────
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); saveBill(); }
  if (e.key === 'Escape') {
    // Close topmost modal
    ['salesReturnModal','exchangeModal','itemSearchModal','billSearchModal','orderSearchModal','msgModal'].forEach(id => {
      if ($(id).classList.contains('active')) { closeModal(id); e.preventDefault(); }
    });
  }
});

// ── Exit button ────────────────────────────────────────────
$('btnExit').addEventListener('click', () => {
  if (window.parent && window.parent !== window) {
    window.parent.postMessage({ type: 'close-module' }, '*');
  }
});

// ── Init ───────────────────────────────────────────────────
itemRows.push(newItemRow());
renderItemsTable();

// Set default date to today
const today = new Date();
$('fDate').value = String(today.getDate()).padStart(2,'0') + '/' + String(today.getMonth()+1).padStart(2,'0') + '/' + today.getFullYear();

// Set default counter
if (CFG.software.DefCounter) $('fCounter').value = CFG.software.DefCounter;
// Set default bill type
if (CFG.software.DefBillType) $('fBillType').value = CFG.software.DefBillType;
// Set default state
if (CFG.software.DefStateCode) $('fState').value = CFG.software.DefStateCode;
// Set default cash/bank
const initialCashBank = getDefaultCashBankCode();
if (initialCashBank) $('fCashBank').value = initialCashBank;

// If edit mode, show bill search
if (CFG.mode === 'edit') {
  $('billSearchModal').classList.add('active');
  $('billSearchQ').focus();
  doBillSearch();
}

if (CFG.mode === 'without-order') {
  $('fOrderNo').value = 'AUTO';
  $('fOrderNo').readOnly = true;
  $('fOrderNo').classList.add('readonly');
  $('btnLoadOrder').disabled = true;
  $('btnOrderSearch').disabled = true;
  $('btnOrderDetails').disabled = true;
  $('fCustomerCode').focus();
} else {
  // Focus order number
  $('fOrderNo').focus();
}

})();
</script>
</body>
</html>
