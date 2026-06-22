<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>E-Invoice — {{ $einv->bill_no }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#000;background:#e9eef3}

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:10px 16px;display:flex;gap:10px;align-items:center;color:#fff;font-size:13px}
.toolbar h1{font-size:14px;font-weight:700;letter-spacing:.3px}
.toolbar .sp{flex:1}
.btn{background:#fff;color:#1e3a5f;padding:6px 14px;border:none;border-radius:5px;font-weight:700;font-size:11px;cursor:pointer;text-decoration:none;display:inline-block}
.btn:hover{background:#dbeafe}
.btn.amber{background:#f59e0b;color:#1a1300}
.btn.amber:hover{background:#d97706;color:#fff}

.page-wrap{padding:18px}
.page{max-width:920px;margin:0 auto;background:#fff;border:1px solid #6b7280;padding:18px 20px;box-shadow:0 8px 24px rgba(0,0,0,.08)}

.top-row{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #6b7280;padding-bottom:10px;margin-bottom:8px}
.top-left{font-size:14px;font-weight:700;line-height:1.4}
.top-left .gstin{font-size:14px;font-weight:700}
.top-left .city{font-size:13px;margin-top:2px}
.top-right img,.top-right .qr-ph{width:120px;height:120px;border:1px solid #cbd5e1;padding:4px;background:#fff;object-fit:contain}
.top-right .qr-ph{display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:9px;text-align:center;padding:6px}

.sec{border:1px solid #6b7280;border-top:none;margin:0}
.sec:first-of-type{border-top:1px solid #6b7280}
.sec-head{background:#e7ebef;font-weight:700;padding:5px 8px;border-bottom:1px solid #6b7280;font-size:12px}
.sec-body{padding:6px 8px}

.kv-grid{display:grid;grid-template-columns:auto 1fr auto 1fr;gap:4px 16px;align-items:start}
.kv-grid .k{font-weight:700;color:#000}
.kv-grid .v{font-weight:400}
.kv-grid .full{grid-column:1/-1}
.kv-grid .k.indent{padding-left:6px}

.party-grid{display:grid;grid-template-columns:1fr 1fr;gap:0}
.party-grid > div{padding:6px 8px;font-size:11px;line-height:1.5}
.party-grid > div + div{border-left:1px solid #6b7280}
.party-grid h4{font-weight:700;margin-bottom:3px;font-size:11.5px}
.party-grid .gst{font-weight:700}

table.items{width:100%;border-collapse:collapse;font-size:10.5px}
table.items th,table.items td{border:1px solid #6b7280;padding:4px 5px;vertical-align:top}
table.items th{background:#e7ebef;font-weight:700;font-size:10px;text-align:center}
table.items td.num{text-align:right;font-variant-numeric:tabular-nums}
table.items td.c{text-align:center}

.totals-table{width:100%;border-collapse:collapse;font-size:10.5px;margin-top:0}
.totals-table th,.totals-table td{border:1px solid #6b7280;padding:5px 6px;text-align:center;vertical-align:middle}
.totals-table th{background:#e7ebef;font-weight:700;font-size:10px}
.totals-table td.num{text-align:right;font-variant-numeric:tabular-nums}

.foot-row{display:grid;grid-template-columns:1fr 280px 1fr;gap:8px;margin-top:10px;align-items:flex-end}
.foot-row .left{font-size:11px;line-height:1.6}
.foot-row .left b{font-weight:700}
.foot-row .barcode{text-align:center}
.foot-row .barcode svg,.foot-row .barcode .bc{display:block;margin:0 auto}
.foot-row .barcode .ackno{font-size:11px;font-family:monospace;margin-top:2px}
.foot-row .right{text-align:right;font-size:10.5px;line-height:1.45}
.foot-row .right .esign{font-size:14px;font-weight:700;color:#1d4ed8;font-style:italic;letter-spacing:.5px;font-family:'Lucida Handwriting','Brush Script MT',cursive}

.cancelled-mark{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:50}
.cancelled-mark span{font-size:160px;color:rgba(220,38,38,.18);font-weight:900;letter-spacing:8px;transform:rotate(-22deg);border:14px solid rgba(220,38,38,.25);padding:10px 30px;border-radius:18px}

@media print{
  .toolbar,.no-print{display:none !important}
  .page-wrap{padding:0}
  .page{box-shadow:none;border:1px solid #000;width:100%;max-width:none}
  body{background:#fff}
  @page{size:A4;margin:10mm}
}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>

<div class="toolbar no-print">
  <h1>E-Invoice — {{ $einv->bill_no }}</h1>
  <span class="sp"></span>
  <button class="btn amber" onclick="window.print()">Print / Save as PDF</button>
  @if(!empty($einv_pdf_url))
    <a class="btn" href="{{ url('/e-invoice-pdf?bill_no=' . urlencode($einv->bill_no)) }}" target="_blank" rel="noopener">Download Provider PDF</a>
  @endif
  <button class="btn" onclick="window.close()">Close</button>
</div>

@if($einv->status === 'cancelled')
  <div class="cancelled-mark"><span>CANCELLED</span></div>
@endif

<div class="page-wrap">
<div class="page">

  @php
    $reqSeller = (array) data_get($request_payload, 'seller_details', []);
    $reqBuyer  = (array) data_get($request_payload, 'buyer_details', []);
    $reqDoc    = (array) data_get($request_payload, 'document_details', []);
    $reqTrans  = (array) data_get($request_payload, 'transaction_details', []);
    $reqValues = (array) data_get($request_payload, 'value_details', []);
    $reqItems  = (array) data_get($request_payload, 'item_list', []);

    $sellerGstin = trim((string) ($reqSeller['gstin'] ?? $api_settings['EINVOICESELLERGSTIN'] ?? $company['KGST'] ?? ''));
    $sellerLegal = trim((string) ($reqSeller['legal_name'] ?? $company['Name'] ?? ''));
    $sellerAddr1 = trim((string) ($reqSeller['address1'] ?? $company['Addr'] ?? ''));
    $sellerAddr2 = trim((string) ($reqSeller['address2'] ?? $company['Addr1'] ?? ''));
    $sellerCity  = trim((string) ($reqSeller['location'] ?? ''));
    $sellerPin   = trim((string) ($reqSeller['pincode'] ?? ''));
    $sellerState = trim((string) ($reqSeller['state_code'] ?? $company['DefStateCode'] ?? ''));

    $buyerGstin  = trim((string) ($reqBuyer['gstin'] ?? $einv->gst_no ?? ''));
    $buyerLegal  = trim((string) ($reqBuyer['legal_name'] ?? $einv->customer_name ?? ''));
    $buyerAddr1  = trim((string) ($reqBuyer['address1'] ?? ($bill->addr ?? '')));
    $buyerAddr2  = trim((string) ($reqBuyer['address2'] ?? ''));
    $buyerCity   = trim((string) ($reqBuyer['location'] ?? ''));
    $buyerPin    = trim((string) ($reqBuyer['pincode'] ?? ''));
    $buyerState  = trim((string) ($reqBuyer['state_code'] ?? ''));
    $placeSupply = trim((string) ($reqBuyer['place_of_supply'] ?? $buyerState));

    $stateNameMap = [
      '01'=>'JAMMU AND KASHMIR','02'=>'HIMACHAL PRADESH','03'=>'PUNJAB','04'=>'CHANDIGARH','05'=>'UTTARAKHAND',
      '06'=>'HARYANA','07'=>'DELHI','08'=>'RAJASTHAN','09'=>'UTTAR PRADESH','10'=>'BIHAR','11'=>'SIKKIM',
      '12'=>'ARUNACHAL PRADESH','13'=>'NAGALAND','14'=>'MANIPUR','15'=>'MIZORAM','16'=>'TRIPURA','17'=>'MEGHALAYA',
      '18'=>'ASSAM','19'=>'WEST BENGAL','20'=>'JHARKHAND','21'=>'ODISHA','22'=>'CHHATTISGARH','23'=>'MADHYA PRADESH',
      '24'=>'GUJARAT','26'=>'DADRA AND NAGAR HAVELI AND DAMAN AND DIU','27'=>'MAHARASHTRA','29'=>'KARNATAKA',
      '30'=>'GOA','31'=>'LAKSHADWEEP','32'=>'KERALA','33'=>'TAMIL NADU','34'=>'PUDUCHERRY','35'=>'ANDAMAN AND NICOBAR ISLANDS',
      '36'=>'TELANGANA','37'=>'ANDHRA PRADESH','38'=>'LADAKH','97'=>'OTHER TERRITORY'
    ];
    $sellerStateName = $stateNameMap[str_pad($sellerState, 2, '0', STR_PAD_LEFT)] ?? $sellerState;
    $buyerStateName  = $stateNameMap[str_pad($buyerState, 2, '0', STR_PAD_LEFT)] ?? $buyerState;
    $placeSupplyName = $stateNameMap[str_pad($placeSupply, 2, '0', STR_PAD_LEFT)] ?? $placeSupply;

    $docNumber = (string) ($reqDoc['document_number'] ?? $einv->bill_no);
    $docDate   = (string) ($reqDoc['document_date'] ?? '');
    if ($docDate !== '' && strpos($docDate, '/') !== false) {
        // convert "05/11/2026" → "11-05-2026" (MM/DD/YYYY → DD-MM-YYYY since legacy code emits MM/DD/YYYY)
        $parts = explode('/', $docDate);
        if (count($parts) === 3) $docDate = sprintf('%02d-%02d-%04d', (int)$parts[1], (int)$parts[0], (int)$parts[2]);
    } elseif ($einv->bill_date) {
        $docDate = \Carbon\Carbon::parse($einv->bill_date)->format('d-m-Y');
    }
    $supplyType  = strtoupper(trim((string) ($reqTrans['supply_type'] ?? 'B2B')));
    $igstOnIntra = strtoupper(trim((string) ($reqTrans['igst_on_intra'] ?? 'N'))) === 'Y' ? 'Yes' : 'No';

    $sumTaxable = (float) ($reqValues['total_assessable_value'] ?? 0);
    $sumCgst    = (float) ($reqValues['total_cgst_value'] ?? 0);
    $sumSgst    = (float) ($reqValues['total_sgst_value'] ?? 0);
    $sumIgst    = (float) ($reqValues['total_igst_value'] ?? 0);
    $sumCess    = (float) ($reqValues['total_cess_value'] ?? 0);
    $sumStateCess = (float) ($reqValues['total_cess_value_of_state'] ?? 0);
    $sumDiscount  = (float) ($reqValues['total_discount'] ?? 0);
    $sumOther     = (float) ($reqValues['total_other_charge'] ?? 0);
    $sumRoundOff  = (float) ($reqValues['round_off_amount'] ?? 0);
    $sumInv       = (float) ($reqValues['total_invoice_value'] ?? $einv->net_total);

    // Format Ack date as dd-mm-YYYY HH:MM:SS
    $ackDateFmt = trim((string) $einv->ack_date);
    if ($ackDateFmt !== '') {
        try { $ackDateFmt = \Carbon\Carbon::parse($ackDateFmt)->format('d-m-Y H:i:s'); } catch (\Throwable $e) {}
    }
    $printDate = now()->format('d-m-Y H:i:s');
    $ackSignDate = trim((string) $einv->ack_date);
    try { $ackSignDate = \Carbon\Carbon::parse($ackSignDate)->format('d-m-Y H:i:s'); } catch (\Throwable $e) {}
  @endphp

  <div class="top-row">
    <div class="top-left">
      <div class="gstin">{{ $sellerGstin }}</div>
      <div class="city">{{ $sellerCity ?: ($company['Branch'] ?? '') }}</div>
    </div>
    <div class="top-right">
      @if(!empty($qr_url))
        <img src="{{ $qr_url }}" alt="QR" onerror="this.outerHTML='<div class=&quot;qr-ph&quot;>QR unavailable</div>';">
      @else
        <div class="qr-ph">QR not available</div>
      @endif
    </div>
  </div>

  <div class="sec">
    <div class="sec-head">1.e-Invoice Details</div>
    <div class="sec-body">
      <div class="kv-grid">
        <div class="k">IRN :</div>
        <div class="v" style="word-break:break-all;font-family:monospace">{{ $einv->irn }}</div>
        <div class="k indent">Ack. No :</div>
        <div class="v">{{ $einv->ack_no }}</div>
        <div class="k">Ack. Date :</div>
        <div class="v">{{ $ackDateFmt }}</div>
        <div></div>
        <div></div>
      </div>
    </div>
  </div>

  <div class="sec">
    <div class="sec-head">2.Transaction Details</div>
    <div class="sec-body">
      <div class="kv-grid">
        <div class="k">Supply Type Code :</div>
        <div class="v">{{ $supplyType }}</div>
        <div class="k indent">Document No :</div>
        <div class="v">{{ $docNumber }}</div>
        <div class="k full" style="grid-column:1/3">
          <span style="font-weight:700">Place of Supply :</span> {{ $placeSupplyName }}
        </div>
        <div class="k full" style="grid-column:3/-1">
          <span style="font-weight:700">IGST applicable despite Supplier and Recipient located in same State :</span> {{ $igstOnIntra }}
        </div>
        <div class="k">Document Type :</div>
        <div class="v">Tax Invoice</div>
        <div class="k indent">Document Date :</div>
        <div class="v">{{ $docDate }}</div>
      </div>
    </div>
  </div>

  <div class="sec">
    <div class="sec-head">3.Party Details</div>
    <div class="party-grid">
      <div>
        <h4>Supplier</h4>
        <div><span class="gst">GSTIN :</span> {{ $sellerGstin }}</div>
        <div>{{ $sellerLegal }}</div>
        @if($sellerAddr1)<div>{{ $sellerAddr1 }}</div>@endif
        @if($sellerAddr2)<div>{{ $sellerAddr2 }}</div>@endif
        @if($sellerCity)<div>{{ $sellerCity }}</div>@endif
        <div>{{ trim($sellerPin . ' ' . $sellerStateName) }}</div>
      </div>
      <div>
        <h4>Recipient</h4>
        <div><span class="gst">GSTIN :</span> {{ $buyerGstin }}</div>
        <div>{{ $buyerLegal }}</div>
        @if($buyerAddr1)<div>{{ $buyerAddr1 }}</div>@endif
        @if($buyerAddr2)<div>{{ $buyerAddr2 }}</div>@endif
        <div>Place of Supply: {{ $placeSupplyName }}</div>
        <div>{{ trim($buyerPin . ' ' . $buyerStateName) }}</div>
      </div>
    </div>
  </div>

  <div class="sec">
    <div class="sec-head">4.Details of Goods / Services</div>
    <table class="items">
      <thead>
        <tr>
          <th style="width:36px">SlNo</th>
          <th style="text-align:left">Item<br>Description</th>
          <th style="width:60px">HSN<br>Code</th>
          <th style="width:60px">Quantity</th>
          <th style="width:40px">Unit</th>
          <th style="width:75px">UnitPrice(Rs)</th>
          <th style="width:65px">Discount(Rs)</th>
          <th style="width:90px">TaxableAmount(Rs)</th>
          <th style="width:90px">Tax Rate (GST+Cess&nbsp;|<br>State Cess+Cess<br>Non.Advol)</th>
          <th style="width:65px">Other<br>charges(Rs)</th>
          <th style="width:80px">Total</th>
        </tr>
      </thead>
      <tbody>
        @forelse($reqItems as $i => $it)
          @php
            $gstRate  = (float) ($it['gst_rate'] ?? 0);
            $cessRate = (float) ($it['cess_rate'] ?? 0);
            $stCessRate = (float) ($it['state_cess_rate'] ?? 0);
            $cessNonAd  = (float) ($it['cess_nonadvol_amount'] ?? 0);
          @endphp
          <tr>
            <td class="c">{{ $it['item_serial_number'] ?? ($i + 1) }}</td>
            <td>{{ $it['product_description'] ?? '' }}</td>
            <td class="c">{{ $it['hsn_code'] ?? '' }}</td>
            <td class="num">{{ rtrim(rtrim(number_format((float)($it['quantity'] ?? 0), 3, '.', ''), '0'), '.') }}</td>
            <td class="c">{{ $it['unit'] ?? 'NOS' }}</td>
            <td class="num">{{ number_format((float)($it['unit_price'] ?? 0), 1) }}</td>
            <td class="num">{{ number_format((float)($it['discount'] ?? 0), 1) }}</td>
            <td class="num">{{ number_format((float)($it['assessable_value'] ?? 0), 1) }}</td>
            <td class="c">{{ number_format($gstRate, 2) }}+{{ number_format($cessRate, 2) }}|<br>{{ number_format($stCessRate, 2) }}+{{ number_format($cessNonAd, 1) }}</td>
            <td class="num">{{ number_format((float)($it['other_charge'] ?? 0), 1) }}</td>
            <td class="num">{{ number_format((float)($it['total_item_value'] ?? 0), 1) }}</td>
          </tr>
        @empty
          <tr><td colspan="11" style="text-align:center;color:#94a3b8;padding:14px">No items.</td></tr>
        @endforelse
      </tbody>
    </table>

    <table class="totals-table" style="margin-top:-1px">
      <thead>
        <tr>
          <th>Tax'ble Amt</th>
          <th>CGST<br>Amt</th>
          <th>SGST<br>Amt</th>
          <th>IGST<br>Amt</th>
          <th>CESS<br>Amt</th>
          <th>State CESS<br>Amt</th>
          <th>Discount</th>
          <th>OtherCharges</th>
          <th>Round off<br>Amt</th>
          <th>Total Inv.<br>Amt</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="num">{{ number_format($sumTaxable, 1) }}</td>
          <td class="num">{{ number_format($sumCgst, 2) }}</td>
          <td class="num">{{ number_format($sumSgst, 2) }}</td>
          <td class="num">{{ number_format($sumIgst, 1) }}</td>
          <td class="num">{{ number_format($sumCess, 1) }}</td>
          <td class="num">{{ number_format($sumStateCess, 1) }}</td>
          <td class="num">{{ number_format($sumDiscount, 1) }}</td>
          <td class="num">{{ number_format($sumOther, 1) }}</td>
          <td class="num">{{ number_format($sumRoundOff, 1) }}</td>
          <td class="num">{{ number_format($sumInv, 1) }}</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="foot-row">
    <div class="left">
      <div><b>Generated By :</b> {{ $sellerGstin }}</div>
      <div><b>Print Date :</b> {{ $printDate }}</div>
    </div>
    <div class="barcode">
      <svg id="ackBarcode" class="bc"></svg>
      <div class="ackno">{{ $einv->ack_no }}</div>
    </div>
    <div class="right">
      <div class="esign">eSign</div>
      <div>Digitally Signed by NIC-IRP</div>
      <div>on: <u>{{ $ackSignDate }}</u></div>
    </div>
  </div>

</div>
</div>

<script>
// Tiny Code-128 SVG barcode renderer for the Ack No
(function(){
  var code = @json((string)($einv->ack_no ?? ''));
  if (!code) return;
  var svg = document.getElementById('ackBarcode');
  if (!svg) return;

  // Mapping table for Code 128B (subset for digits + uppercase + symbols we need)
  var C128 = {
    '0':'11011001100','1':'11001101100','2':'11001100110','3':'10010011000','4':'10010001100',
    '5':'10001001100','6':'10011001000','7':'10011000100','8':'10001100100','9':'11001001000',
    'START_B':'11010010000','STOP':'1100011101011'
  };
  // Code 128 'B' table values for digits 0-9 = 16..25
  var DIGIT_VALUES = {'0':16,'1':17,'2':18,'3':19,'4':20,'5':21,'6':22,'7':23,'8':24,'9':25};
  var START_VAL = 104;

  var clean = String(code).replace(/[^0-9A-Z\-]/g,'').toUpperCase();
  if (!clean) return;

  // Compute checksum
  var checksum = START_VAL;
  for (var i = 0; i < clean.length; i++) {
    var ch = clean.charAt(i);
    var val = DIGIT_VALUES[ch];
    if (val === undefined) val = ch.charCodeAt(0) - 32; // basic Code-128B mapping
    checksum += val * (i + 1);
  }
  checksum = checksum % 103;

  // Code-128 patterns array (index = value 0..106)
  var PATTERNS = ["11011001100","11001101100","11001100110","10010011000","10010001100","10001001100","10011001000","10011000100","10001100100","11001001000","11001000100","11000100100","10110011100","10011011100","10011001110","10111001100","10011101100","10011100110","11001110010","11001011100","11001001110","11011100100","11001110100","11101101110","11101001100","11100101100","11100100110","11101100100","11100110100","11100110010","11011011000","11011000110","11000110110","10100011000","10001011000","10001000110","10110001000","10001101000","10001100010","11010001000","11000101000","11000100010","10110111000","10110001110","10001101110","10111011000","10111000110","10001110110","11101110110","11010001110","11000101110","11011101000","11011100010","11011101110","11101011000","11101000110","11100010110","11101101000","11101100010","11100011010","11101111010","11001000010","11110001010","10100110000","10100001100","10010110000","10010000110","10000101100","10000100110","10110010000","10110000100","10011010000","10011000010","10000110100","10000110010","11000010010","11001010000","11110111010","11000010100","10001111010","10100111100","10010111100","10010011110","10111100100","10011110100","10011110010","11110100100","11110010100","11110010010","11011011110","11011110110","11110110110","10101111000","10100011110","10001011110","10111101000","10111100010","11110101000","11110100010","10111011110","10111101110","11101011110","11110101110","11010000100","11010010000","11010011100","1100011101011"];

  var pattern = PATTERNS[START_VAL];
  for (var j = 0; j < clean.length; j++) {
    var c = clean.charAt(j);
    var v = DIGIT_VALUES[c];
    if (v === undefined) v = c.charCodeAt(0) - 32;
    pattern += PATTERNS[v];
  }
  pattern += PATTERNS[checksum];
  pattern += PATTERNS[106]; // STOP

  // Render
  var moduleWidth = 1.6;
  var height = 36;
  var totalWidth = pattern.length * moduleWidth;
  svg.setAttribute('width', totalWidth);
  svg.setAttribute('height', height);
  svg.setAttribute('viewBox', '0 0 ' + totalWidth + ' ' + height);

  var x = 0;
  var bars = '';
  for (var k = 0; k < pattern.length; k++) {
    if (pattern.charAt(k) === '1') {
      bars += '<rect x="' + x + '" y="0" width="' + moduleWidth + '" height="' + height + '" fill="#000"/>';
    }
    x += moduleWidth;
  }
  svg.innerHTML = bars;
})();
</script>
</body>
</html>
