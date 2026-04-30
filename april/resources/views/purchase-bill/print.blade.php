<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase Bill Print</title>
<style>
  :root{--ink:#0f172a;--muted:#475569;--line:#cbd5e1;--soft:#e2e8f0;--accent:#0f4c81;--paper:#fff}
  *{box-sizing:border-box}
  body{margin:0;font-family:Segoe UI,Arial,sans-serif;color:var(--ink);background:#eef2f7}
  .toolbar{background:#fff;padding:8px 12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;border-bottom:1px solid #d1d5db;position:sticky;top:0;z-index:100;box-shadow:0 1px 4px rgba(0,0,0,.08)}
  .tb-lbl{font-size:10px;font-weight:600;color:#6b7280;white-space:nowrap}
  .tb-inp{width:56px;text-align:center;border:1px solid #d1d5db;border-radius:6px;padding:4px 5px;font-size:11px;height:28px}
  .tb-btn{padding:5px 14px;font-weight:600;font-size:11px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;color:#374151;height:30px}
  .tb-btn:hover{background:#f3f4f6}
  .tb-btn.primary{background:#1d4ed8;color:#fff;border-color:#1d4ed8}
  .tb-btn.primary:hover{background:#1e40af}
  .tb-chk{display:flex;align-items:center;gap:4px;font-size:10px;color:#374151;white-space:nowrap}
  .tb-sep{width:1px;height:20px;background:#e5e7eb;flex-shrink:0}
  .page-wrap{padding:20px}
  .page{width:980px;margin:0 auto;background:var(--paper);border:1px solid var(--soft);box-shadow:0 10px 30px rgba(15,23,42,.08);padding:24px 28px 28px}
  .page.landscape{width:1180px}
  .header{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid var(--accent);padding-bottom:14px;margin-bottom:18px}
  .company h1{margin:0 0 6px;font-size:28px;letter-spacing:.5px}
  .company .meta,.bill-meta .meta{color:var(--muted);font-size:13px;line-height:1.5}
  .bill-meta{text-align:right;min-width:260px}
  .bill-title{font-size:22px;font-weight:700;color:var(--accent);margin:0 0 8px}
  .grid{display:grid;grid-template-columns:1.2fr 1fr;gap:18px;margin-bottom:18px}
  .card{border:1px solid var(--line);border-radius:8px;overflow:hidden}
  .card h3{margin:0;padding:9px 12px;font-size:13px;letter-spacing:.4px;color:#fff;background:linear-gradient(135deg,#0f4c81,#1f6aa5);text-transform:uppercase}
  .card .body{padding:10px 12px;font-size:13px;line-height:1.65}
  .kv{display:grid;grid-template-columns:120px 1fr;gap:4px 10px}
  .kv .k{color:var(--muted);font-weight:600}
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid var(--line);padding:7px 8px;font-size:12px}
  thead th{background:#e9f1f8;color:#0f2d4b;text-transform:uppercase;letter-spacing:.3px}
  td.num,th.num{text-align:right}
  td.center,th.center{text-align:center}
  .section-title{margin:18px 0 8px;font-size:15px;font-weight:700;color:#0f2d4b}
  .totals{margin-top:18px;display:grid;grid-template-columns:1fr 320px;gap:18px;align-items:start}
  .note-box{border:1px solid var(--line);border-radius:8px;padding:12px;min-height:118px;font-size:13px}
  .summary{border:1px solid var(--line);border-radius:8px;overflow:hidden}
  .summary table td{font-size:13px}
  .summary td:first-child{color:var(--muted);font-weight:600;width:55%}
  .summary tr.total td{background:#eff6ff;font-size:15px;font-weight:700;color:#0f2d4b}
  .footer{margin-top:30px;display:flex;justify-content:space-between;gap:30px;font-size:12px;color:var(--muted)}
  .sign{width:220px;text-align:center;padding-top:34px;border-top:1px solid var(--line)}
  @media print{body{background:#fff}.toolbar{display:none!important}.page-wrap{padding:0}.page{width:auto;margin:0;border:none;box-shadow:none;padding:0}.page.landscape{width:auto}@page{size:A4 portrait;margin:10mm}}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="toolbar">
  <span class="tb-lbl">Copies</span>
  <input type="number" class="tb-inp" id="copies" value="1" min="1" max="10">

  <span class="tb-lbl">Zoom%</span>
  <input type="text" class="tb-inp" id="zoom" value="">

  <div class="tb-chk">
    <input type="checkbox" id="cbLandscape" onchange="document.getElementById('billCard').classList.toggle('landscape',this.checked)">
    <label for="cbLandscape">Landscape</label>
  </div>

  <div class="tb-sep"></div>

  <button class="tb-btn primary" type="button" onclick="doPrint()">Print</button>
  <button class="tb-btn" type="button" onclick="window.print()">PDF / Save As</button>
  <button class="tb-btn" type="button" onclick="exitPrint()">Exit</button>
</div>

<div class="page-wrap" data-print-root="purchase-bill">
<div class="page" id="billCard">
  <div class="header">
    <div class="company" data-print-app-header="purchase-company">
      <h1>{{ $company['name'] ?: 'Company' }}</h1>
      <div class="meta">
        @if($company['address'])<div>{{ $company['address'] }}</div>@endif
        @if($company['address2'])<div>{{ $company['address2'] }}</div>@endif
        @if($company['phone'])<div>Phone: {{ $company['phone'] }}</div>@endif
        @if($company['gstin'])<div>GSTIN: {{ $company['gstin'] }}</div>@endif
      </div>
    </div>
    <div class="bill-meta">
      <div class="bill-title">Purchase Invoice</div>
      <div class="meta">
        <div><strong>Doc No:</strong> {{ $master->docno }}</div>
        <div><strong>Supplier Bill No:</strong> {{ $master->billno ?: '-' }}</div>
        <div><strong>Date:</strong> {{ !empty($master->tdate) ? \Carbon\Carbon::parse($master->tdate)->format('d/m/Y') : '-' }}</div>
        <div><strong>Status:</strong> {{ (int)($master->status ?? 1) === 0 ? 'Cancelled' : 'Active' }}</div>
      </div>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h3>Supplier Details</h3>
      <div class="body">
        <div><strong>{{ $master->name ?: '-' }}</strong></div>
        <div class="kv" style="margin-top:8px">
          <div class="k">Supplier Code</div><div>{{ $master->suppcode ?: '-' }}</div>
          <div class="k">Address</div><div>{{ $master->addr ?: ($supplier->addr1 ?? '-') }}</div>
          <div class="k">Mobile</div><div>{{ $master->mobile ?: ($supplier->mobile ?? '-') }}</div>
          <div class="k">PAN / Aadhar</div><div>{{ $master->pan ?: ($supplier->panadhar ?? '-') }}</div>
          <div class="k">State</div><div>{{ $master->statecode ?: '-' }}{{ $stateName ? ' - '.$stateName : '' }}</div>
        </div>
      </div>
    </div>

    <div class="card">
      <h3>Bill Details</h3>
      <div class="body">
        <div class="kv">
          <div class="k">Gold Rate</div><div>{{ number_format((float)($master->rate ?? 0), 2, '.', '') }}</div>
          <div class="k">Bill Type</div><div>{{ $master->billtype ?: '-' }}</div>
          <div class="k">Salesman</div><div>{{ $salesmanName ?: ($master->smcode ?: '-') }}</div>
          <div class="k">Counter</div><div>{{ $master->counter ?: '-' }}</div>
          @if((float)($master->taxperc ?? 0) > 0)<div class="k">Tax %</div><div>{{ number_format((float)($master->taxperc ?? 0), 2, '.', '') }}</div>@endif
          <div class="k">Interstate</div><div>{{ ($master->cst ?? 'N') === 'Y' ? 'Yes' : 'No' }}</div>
          @if(!empty($master->duedate))<div class="k">Due Date</div><div>{{ \Carbon\Carbon::parse($master->duedate)->format('d/m/Y') }}</div>@endif
        </div>
      </div>
    </div>
  </div>

  <div class="section-title">Purchased Items</div>
  <table>
    <thead>
      <tr>
        <th class="center" style="width:42px">#</th>
        <th style="width:90px">Code</th>
        <th>Item Name</th>
        <th class="center" style="width:70px">Purity</th>
        <th class="num" style="width:58px">Qty</th>
        <th class="num" style="width:80px">Weight</th>
        <th class="num" style="width:78px">Stone Wgt</th>
        <th class="num" style="width:78px">Mud Less</th>
        <th class="num" style="width:80px">Net Wgt</th>
        <th class="num" style="width:84px">Rate</th>
        <th class="num" style="width:80px">Rate/gm</th>
        <th class="num" style="width:72px">MC</th>
        <th class="num" style="width:96px">Amount</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $row)
      <tr>
        <td class="center">{{ $row['sno'] }}</td>
        <td>{{ $row['code'] }}</td>
        <td>{{ $row['name'] ?: '-' }}</td>
        <td class="center">{{ $row['purity'] ?: '-' }}</td>
        <td class="num">{{ number_format($row['qty'], 0, '.', '') }}</td>
        <td class="num">{{ number_format($row['weight'], 3, '.', '') }}</td>
        <td class="num">{{ number_format($row['stone_weight'], 3, '.', '') }}</td>
        <td class="num">{{ number_format($row['mud_less'], 3, '.', '') }}</td>
        <td class="num">{{ number_format($row['net_weight'], 3, '.', '') }}</td>
        <td class="num">{{ number_format($row['rate'], 2, '.', '') }}</td>
        <td class="num">{{ $row['net_weight'] > 0 ? number_format($row['amount'] / $row['net_weight'], 2, '.', '') : '-' }}</td>
        <td class="num">{{ number_format($row['mc'], 2, '.', '') }}</td>
        <td class="num">{{ number_format($row['amount'], 2, '.', '') }}</td>
      </tr>
      @empty
      <tr><td colspan="13" class="center">No items found.</td></tr>
      @endforelse
    </tbody>
  </table>

  @if(count($exchange))
  <div class="section-title">Purchase Return / Exchange Items</div>
  <table>
    <thead>
      <tr>
        <th class="center" style="width:42px">#</th>
        <th style="width:90px">Code</th>
        <th>Item Name</th>
        <th class="num" style="width:58px">Qty</th>
        <th class="num" style="width:90px">Weight</th>
        <th class="num" style="width:90px">Less %</th>
        <th class="num" style="width:90px">Rate</th>
        <th class="num" style="width:110px">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach($exchange as $row)
      <tr>
        <td class="center">{{ $row['sno'] }}</td>
        <td>{{ $row['code'] }}</td>
        <td>{{ $row['name'] ?: '-' }}</td>
        <td class="num">{{ number_format($row['qty'], 0, '.', '') }}</td>
        <td class="num">{{ number_format($row['weight'], 3, '.', '') }}</td>
        <td class="num">{{ number_format($row['less_perc'], 2, '.', '') }}</td>
        <td class="num">{{ number_format($row['rate'], 2, '.', '') }}</td>
        <td class="num">{{ number_format($row['amount'], 2, '.', '') }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  <div class="totals">
    <div class="note-box">
      <strong>Note</strong>
      <div style="margin-top:8px">{{ trim((string)($master->note ?? '')) !== '' ? $master->note : 'No notes.' }}</div>
    </div>

    <div class="summary">
      <table>
        <tbody>
          <tr><td>Bill Total</td><td class="num">{{ number_format((float)($master->billamt ?? 0), 2, '.', '') }}</td></tr>
          <tr><td>Others</td><td class="num">{{ number_format((float)($master->addamt ?? 0), 2, '.', '') }}</td></tr>
          @if((float)($master->tcsamt ?? 0) > 0)<tr><td>TCS ({{ number_format((float)($master->tcsperc ?? 0), 3, '.', '') }}%)</td><td class="num">{{ number_format((float)($master->tcsamt ?? 0), 2, '.', '') }}</td></tr>@endif
          <tr class="total"><td>Net Total</td><td class="num">{{ number_format((float)($master->netamt ?? 0), 2, '.', '') }}</td></tr>
          <tr><td>Balance</td><td class="num">{{ number_format($balance, 2, '.', '') }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="footer">
    <div>Printed on {{ now()->format('d/m/Y h:i A') }}</div>
    <div class="sign">Authorised Signature</div>
  </div>
</div>
</div>

<script>
const purchaseBillPrintStorageKey = `goldapp-purchase-bill-print:${window.location.pathname}`;

function loadStoredPurchasePrintDefaults() {
  try {
    const raw = window.localStorage.getItem(purchaseBillPrintStorageKey);
    if (!raw) return {};
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch (_) {
    return {};
  }
}

function saveStoredPurchasePrintDefaults() {
  try {
    window.localStorage.setItem(purchaseBillPrintStorageKey, JSON.stringify({
      copies: document.getElementById('copies')?.value || '1',
      zoom: document.getElementById('zoom')?.value || '',
      landscape: !!document.getElementById('cbLandscape')?.checked
    }));
  } catch (_) {}
}

function applyPurchaseZoom(value) {
  const billCard = document.getElementById('billCard');
  if (!billCard) return;
  if (value !== '' && !isNaN(value)) {
    billCard.style.zoom = (parseInt(value, 10) / 100).toString();
  } else {
    billCard.style.zoom = '';
  }
}

function doPrint() {
  const c = parseInt(document.getElementById('copies')?.value || '1', 10) || 1;
  for (let i = 0; i < c; i++) {
    setTimeout(() => window.print(), i * 600);
  }
}

function exitPrint() {
  if (window.opener && !window.opener.closed) {
    try { window.opener.focus(); } catch (_) {}
  }
  window.close();
}

document.addEventListener('goldapp:print-layout-save-default', function () {
  saveStoredPurchasePrintDefaults();
});

document.addEventListener('DOMContentLoaded', function () {
  const stored = loadStoredPurchasePrintDefaults();
  const copies = document.getElementById('copies');
  const zoom = document.getElementById('zoom');
  const cbLandscape = document.getElementById('cbLandscape');
  const billCard = document.getElementById('billCard');

  if (copies) copies.value = stored.copies || '1';
  if (zoom) zoom.value = stored.zoom || '';
  if (cbLandscape && billCard) {
    cbLandscape.checked = !!stored.landscape;
    billCard.classList.toggle('landscape', cbLandscape.checked);
    cbLandscape.addEventListener('change', saveStoredPurchasePrintDefaults);
  }
  if (zoom) {
    applyPurchaseZoom(zoom.value);
    zoom.addEventListener('input', function () {
      applyPurchaseZoom(this.value);
      saveStoredPurchasePrintDefaults();
    });
  }
  if (copies) {
    copies.addEventListener('input', saveStoredPurchasePrintDefaults);
  }
});
</script>

@if($autoprint)
<script>
window.addEventListener('load',()=>setTimeout(()=>window.print(),300));
</script>
@endif
</body>
</html>
