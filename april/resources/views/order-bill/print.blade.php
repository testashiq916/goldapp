<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Slip Print</title>
@include('partials.print-layout-head')
<style>
  body{margin:0;background:#eef2f7;font-family:Arial,sans-serif;color:#111}
  .toolbar{width:820px;margin:18px auto 0;display:flex;align-items:center;gap:10px}
  .page{width:820px;min-height:1120px;margin:18px auto;background:#fff;box-shadow:0 8px 28px rgba(0,0,0,.12);padding:18px 22px 24px;border:1px solid #d1d5db}
  .company{text-align:center}
  .company .name{font-size:20px;font-weight:700;letter-spacing:.4px}
  .company .line{font-size:12px;line-height:1.35}
  .title{margin:10px 0 14px;text-align:center;font-size:18px;font-weight:700;text-decoration:underline}
  .top{display:grid;grid-template-columns:1.1fr .85fr .85fr;gap:12px;font-size:12px}
  .box{border:1px solid #111;padding:8px 10px}
  .meta-row{display:grid;grid-template-columns:110px 12px 1fr;gap:4px;align-items:start;margin-bottom:4px}
  .meta-row:last-child{margin-bottom:0}
  .lbl{font-weight:700}
  .tight{line-height:1.35}
  .table{width:100%;border-collapse:collapse;margin-top:8px;font-size:12px}
  .table th,.table td{border:1px solid #111;padding:4px 5px;vertical-align:top}
  .table th{text-align:center;font-weight:700}
  .table td.num{text-align:right}
  .table td.center{text-align:center}
  .split{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px}
  .subhead{font-weight:700;font-size:13px;margin:0 0 4px}
  .mini{font-size:11px}
  .sign{display:flex;justify-content:space-between;align-items:flex-end;margin-top:24px;padding-top:26px;border-top:1px solid #111;font-size:12px;font-style:italic}
  .actions{display:flex;justify-content:flex-start;gap:8px}
  .actions button{height:32px;padding:0 14px;border:1px solid #64748b;background:#fff;border-radius:6px;font-weight:700;cursor:pointer}
  @media print{
    body{background:#fff}
    .toolbar{display:none}
    .page{width:auto;min-height:auto;margin:0;box-shadow:none;border:0}
  }
</style>
</head>
<body>
@php
  $fmtAmt = function ($v) use ($software) {
      $dec = strtoupper((string) ($software['AMT3DEC'] ?? 'N')) === 'Y' ? 3 : 2;
      return number_format((float) $v, $dec, '.', '');
  };
  $fmtWgt = fn($v) => number_format((float) $v, 3, '.', '');
  $fmtDate = function ($v) {
      if (!$v) return '';
      $ts = strtotime((string) $v);
      return $ts ? date('d/m/Y', $ts) : (string) $v;
  };
  $showHead = strtoupper((string) ($software['PRINTSHOPHEADINORDERSLIP'] ?? 'Y')) !== 'N';
  $showPartyCode = strtoupper((string) ($software['PRINTPARTYCODEINSALES'] ?? 'N')) === 'Y';
  $showMob = strtoupper((string) ($software['PRINTMOBNUMBERINORDERSLIP'] ?? 'N')) === 'Y';
  $showAdvAfter = strtoupper((string) ($software['PRINTADVAFTER'] ?? 'N')) === 'Y';
  $showCess = strtoupper((string) ($software['PRINTCESSINORDER'] ?? 'N')) === 'Y' && (float) ($order['tax'] ?? 0) > 0;
  $orderForm = (string) ($software['ORDERFORM'] ?? '1');
  $title = strtoupper((string) ($software['ORDHEAD'] ?? 'Y')) === 'Y' ? 'ORDER SLIP' : 'ESTIMATE';
  $cessPerc = (float) ($software['CESSPERC'] ?? 0.25);
  if ($cessPerc <= 0) $cessPerc = 0.25;
  $cessAmt = round(((float) ($order['bill_total'] ?? 0)) * $cessPerc / 100, 2);
  $totalAdvance = (float) ($order['advance'] ?? 0)
    + (float) ($order['exchange'] ?? 0)
    + (float) ($order['sretamt'] ?? 0)
    + (float) ($order['adv_after'] ?? 0)
    + (float) ($order['scheme_amt'] ?? 0);
  $cashAdvance = (float) ($order['advance'] ?? 0) - (float) ($order['ccamt'] ?? 0) - (float) ($order['chq_amt'] ?? 0);
  $estBalanceAmt = (float) ($order['bill_total'] ?? 0) - ((float) ($order['advance'] ?? 0) + (float) ($order['exchange'] ?? 0) + (float) ($order['sretamt'] ?? 0)) + (float) ($order['tax'] ?? 0) + ($showCess ? $cessAmt : 0);
  $obType = (float) ($order['ob'] ?? 0) > 0 ? 'Db' : 'Cr';
  $cbRaw = (float) ($order['ob'] ?? 0) - $totalAdvance;
  $cbType = $cbRaw > 0 ? 'Db' : 'Cr';
  $logoPath = public_path('plg.png');
  $logoUrl = trim((string) ($company['logo_url'] ?? ''));
  $cashBankLabel = trim((string) ($order['cbcode'] ?? ''));
  $chqBankLabel = trim((string) ($order['chq_bank'] ?? ''));
@endphp

<div class="toolbar">
  <div class="actions">
    <button type="button" onclick="window.print()">Print</button>
    <button type="button" onclick="window.close()">Close</button>
  </div>
</div>

<div class="page" data-print-root="order-slip">
  @if($showHead)
    <div data-print-app-header="1">
    <div class="company">
      @if($logoUrl !== '')
        <div style="margin-bottom:6px"><img src="{{ $logoUrl }}" alt="Logo" style="max-height:72px;max-width:110px"></div>
      @elseif(is_file($logoPath))
        <div style="margin-bottom:6px"><img src="{{ asset('plg.png') }}" alt="Logo" style="max-height:72px;max-width:110px"></div>
      @endif
      <div class="name">{{ $company['name'] !== '' ? $company['name'] : config('app.name') }}</div>
      @if(trim((string) $company['addr']) !== '')
        <div class="line">{{ $company['addr'] }}</div>
      @endif
      @if(trim((string) $company['phone']) !== '')
        <div class="line">Phone : {{ $company['phone'] }}</div>
      @endif
      @if(trim((string) ($company['gstin'] ?? '')) !== '')
        <div class="line">GSTIN : {{ $company['gstin'] }}</div>
      @endif
    </div>
    </div>
  @endif

  <div class="title">{{ $title }}</div>

  <div class="top">
    <div class="box tight">
      <div class="meta-row"><div class="lbl">Order No</div><div>:</div><div>{{ $order['doc_no'] }}</div></div>
      <div class="meta-row">
        <div class="lbl">Name</div><div>:</div>
        <div>{{ $order['cust_name'] }}@if($showPartyCode && trim((string) $order['cust_code']) !== '') ({{ $order['cust_code'] }})@endif</div>
      </div>
      <div class="meta-row"><div class="lbl">Address</div><div>:</div><div>{{ trim((string) $order['address']) }}</div></div>
      <div class="meta-row"><div class="lbl">Phone</div><div>:</div><div>{{ trim((string) $order['phone']) }}</div></div>
      <div class="meta-row"><div class="lbl">Mobile</div><div>:</div><div>{{ trim((string) $order['mobile']) }}</div></div>
    </div>

    <div class="box tight">
      <div class="meta-row"><div class="lbl">Date</div><div>:</div><div>{{ $fmtDate($order['date']) }}</div></div>
      <div class="meta-row"><div class="lbl">Gold Rate</div><div>:</div><div>{{ $fmtAmt($order['gold_rate']) }}</div></div>
      <div class="meta-row"><div class="lbl">8gm Rate</div><div>:</div><div>{{ $fmtAmt((float) ($order['gold_rate'] ?? 0) * 8) }}</div></div>
      <div class="meta-row"><div class="lbl">Sales Man</div><div>:</div><div>{{ trim((string) $order['salesman_name']) }}</div></div>
      <div class="meta-row"><div class="lbl">Counter</div><div>:</div><div>{{ trim((string) $order['counter']) }}</div></div>
    </div>

    <div class="box tight">
      <div class="meta-row"><div class="lbl">PAN/Adhr</div><div>:</div><div>{{ trim((string) ($order['pan'] ?? '')) }}</div></div>
      <div class="meta-row"><div class="lbl">GSTIN</div><div>:</div><div>{{ trim((string) ($order['gst_no'] ?? '')) }}</div></div>
      <div class="meta-row"><div class="lbl">OB</div><div>:</div><div>{{ $fmtAmt(abs((float) ($order['ob'] ?? 0))) }} @if((float) ($order['ob'] ?? 0) != 0){{ $obType }}@endif</div></div>
      <div class="meta-row"><div class="lbl">CB</div><div>:</div><div>{{ $fmtAmt(abs((float) ($order['cb'] ?? 0))) }} @if((float) ($order['cb'] ?? 0) != 0){{ ((float) ($order['cb'] ?? 0)) > 0 ? 'Db' : 'Cr' }}@endif</div></div>
    </div>
  </div>

  <table class="table" style="margin-top:10px">
    <thead>
      <tr>
        <th style="width:70px">Item Code</th>
        <th>Item Name</th>
        <th style="width:80px">Rate</th>
        <th style="width:50px">Qty</th>
        <th style="width:80px">Weight</th>
        <th style="width:80px">Stone Wgt</th>
        <th style="width:90px">Stone Price</th>
        <th style="width:70px">MC</th>
        <th style="width:80px">Wastage</th>
        <th style="width:95px">Amount</th>
        <th style="width:120px">Narration</th>
      </tr>
    </thead>
    <tbody>
      @forelse($order['items'] as $row)
        <tr>
          <td class="center">{{ $row['code'] }}</td>
          <td>{{ $row['name'] }}</td>
          <td class="num">{{ $fmtAmt($row['rate']) }}</td>
          <td class="num">{{ $row['qty'] }}</td>
          <td class="num">{{ $fmtWgt($row['weight']) }}</td>
          <td class="num">{{ $fmtWgt($row['stwgt']) }}</td>
          <td class="num">{{ $fmtAmt($row['stprice']) }}</td>
          <td class="num">{{ $fmtAmt($row['mcharge']) }}</td>
          <td class="num">{{ $fmtWgt($row['wastage']) }}</td>
          <td class="num">{{ $fmtAmt($row['amount']) }}</td>
          <td>{{ $row['narration'] }}</td>
        </tr>
      @empty
        <tr><td colspan="11" class="center">No order items</td></tr>
      @endforelse
    </tbody>
  </table>

  @if(count($order['exch_items']) || count($order['sales_return_items']))
    <div class="split">
      <div>
        <div class="subhead">Exchange Details</div>
        <table class="table mini">
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Rate</th>
              <th>Qty</th>
              <th>Weight</th>
              <th>Less %</th>
              <th>Less Wgt</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            @forelse($order['exch_items'] as $row)
              <tr>
                <td class="center">{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="num">{{ $fmtAmt($row['rate']) }}</td>
                <td class="num">{{ $row['qty'] }}</td>
                <td class="num">{{ $fmtWgt($row['weight']) }}</td>
                <td class="num">{{ $fmtAmt($row['lessperc']) }}</td>
                <td class="num">{{ $fmtWgt($row['lesswgt']) }}</td>
                <td class="num">{{ $fmtAmt($row['amount']) }}</td>
              </tr>
            @empty
              <tr><td colspan="8" class="center">No exchange rows</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div>
        <div class="subhead">Sales Return</div>
        <table class="table mini">
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Qty</th>
              <th>Weight</th>
              <th>MC</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            @forelse($order['sales_return_items'] as $row)
              <tr>
                <td class="center">{{ trim((string) ($row->code ?? '')) }}</td>
                <td>{{ trim((string) (($row->name ?? $row->itemname) ?? '')) }}</td>
                <td class="num">{{ (int) ($row->qty ?? 0) }}</td>
                <td class="num">{{ $fmtWgt($row->weight ?? 0) }}</td>
                <td class="num">{{ $fmtAmt($row->mcharge ?? 0) }}</td>
                <td class="num">{{ $fmtAmt($row->amount ?? 0) }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="center">No sales return rows</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif

  <div class="sign">
    <div>Signature</div>
    <div>Authorised Signatory</div>
  </div>
</div>

</body>
</html>
