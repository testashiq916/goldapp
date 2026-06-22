<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $docTitle }} - {{ trim((string) ($master->docno ?? '')) }}</title>
<style>
body{font-family:Arial,sans-serif;margin:0;background:#f3f1ea;color:#111;font-size:13px}
.toolbar{display:flex;justify-content:space-between;gap:10px;padding:10px 14px;background:#fff;border-bottom:1px solid #d8d1c1;align-items:flex-start;flex-wrap:wrap;position:sticky;top:0;z-index:10}
.toolbar button{padding:7px 12px;border:1px solid #8f7d52;background:#f2e0a8;color:#2f2818;border-radius:4px;font-weight:700;cursor:pointer;font-size:12px}
.controls{display:flex;gap:10px 14px;flex-wrap:wrap;align-items:center}
.controls label{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#4a3d1f;white-space:nowrap}
.controls input{accent-color:#8f7d52}
.page{max-width:1180px;margin:12px auto;background:#fff;padding:18px 20px;box-shadow:0 2px 12px rgba(0,0,0,.08)}
.title{text-align:center;margin-bottom:10px}
.title h1{margin:0;font-size:22px;line-height:1.15;letter-spacing:.35px;text-transform:uppercase;white-space:normal}
.shop-line{font-size:12px;font-weight:700;line-height:1.35;text-transform:uppercase}
.shop-meta{display:flex;justify-content:center;gap:14px;flex-wrap:wrap}
.title .sub{margin-top:4px;font-size:17px;font-weight:700;letter-spacing:.2px}
.title .sub2{margin-top:4px;font-size:13px;font-weight:700;letter-spacing:.2px}
.info-grid{display:grid;grid-template-columns:1.25fr .95fr;gap:12px;margin-bottom:12px}
.card{border:1px solid #cfc7b5;padding:10px 12px}
.line{display:grid;grid-template-columns:108px 10px 1fr;gap:6px;font-size:12px;padding:2px 0}
.line b{font-weight:700}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{border:1px solid #222;padding:5px 6px;font-size:11px}
th{background:#efe6c8}
.r{text-align:right}
.c{text-align:center}
.summary{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}
.totals td{font-weight:700}
.hidden{display:none}
.foot{display:flex;justify-content:space-between;margin-top:20px;font-size:12px;gap:20px}
.sign{width:220px;text-align:center;padding-top:34px;border-top:1px solid #333}
@media (max-width: 1366px){
  body{font-size:12px}
  .page{max-width:100%;margin:8px;padding:14px}
  .title h1{font-size:18px}
  .title .sub{font-size:15px}
  .controls{gap:8px 12px}
  .controls label{font-size:11px}
  .info-grid{grid-template-columns:1fr 1fr;gap:10px}
  .card{padding:8px 10px}
  .line{grid-template-columns:96px 10px 1fr;font-size:11px}
  th,td{padding:4px 5px;font-size:10px}
}
@media (max-width: 1024px){
  .toolbar{position:static}
  .info-grid,.summary{grid-template-columns:1fr}
  .controls{width:100%}
  .foot{flex-direction:column;align-items:stretch}
  .sign{width:100%}
}
@media print{
  @page{size:A4 landscape;margin:10mm}
  body{background:#fff}
  .toolbar{display:none}
  .page{margin:0;box-shadow:none;max-width:none;padding:0}
  .title{break-inside:avoid;page-break-inside:avoid}
  .title h1{font-size:22px;white-space:normal;overflow:visible}
  .shop-line{font-size:12px;white-space:normal;overflow:visible}
  table{break-inside:auto;page-break-inside:auto}
  thead{display:table-header-group}
  tr,.card,.summary,.foot,.sign{break-inside:avoid;page-break-inside:avoid}
}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="toolbar">
  <div class="controls">
    <label><input type="checkbox" id="cbShowShop" checked> Show Shop Info</label>
    <label><input type="checkbox" id="cbDc"> Delivery Challan</label>
    <label><input type="checkbox" id="cbRefinery"> Refinery</label>
    <label><input type="checkbox" id="cbMelt"> Melting</label>
    <label><input type="checkbox" id="cbAfter"> After Job Work</label>
  </div>
  <button type="button" onclick="printOnlyPreview()">Print</button>
</div>

<div class="page" data-print-root="goldsmith-print">
  <div class="title">
    <div id="shopInfo" data-print-app-header="goldsmith-shop">
      @if(!empty($company['SHOPNM']))
        <h1>{{ $company['SHOPNM'] }}</h1>
      @endif
      @if(!empty($company['SHOPADDR']))
        <div class="shop-line">{{ $company['SHOPADDR'] }}</div>
      @endif
      @php
        $shopPhone = trim((string) ($company['SHOPPHONE'] ?? ''));
        $shopMobile = trim((string) ($company['Mobile'] ?? $company['MOBILE'] ?? ''));
        $shopGstin = trim((string) ($company['GSTIN'] ?? $company['GSTNO'] ?? $company['KGST'] ?? ''));
      @endphp
      @if($shopPhone !== '' || $shopMobile !== '' || $shopGstin !== '')
        <div class="shop-line shop-meta">
          @if($shopPhone !== '')<span>Ph: {{ $shopPhone }}</span>@endif
          @if($shopMobile !== '')<span>Mobile: {{ $shopMobile }}</span>@endif
          @if($shopGstin !== '')<span>GSTIN: {{ $shopGstin }}</span>@endif
        </div>
      @endif
    </div>
    <div class="sub" id="docTitle">{{ $docTitle }}</div>
    <div class="sub2" id="docSubTitle" style="display:none"></div>
  </div>

  <div class="info-grid">
    <div class="card">
      <div class="line"><b>Party</b><span>:</span><span>{{ trim((string) ($master->party_name ?? $master->smithcode ?? '')) }}</span></div>
      <div class="line"><b>Code</b><span>:</span><span>{{ trim((string) ($master->smithcode ?? '')) }}</span></div>
      <div class="line"><b>Address</b><span>:</span><span>{{ $address }}</span></div>
      <div class="line"><b>Mobile</b><span>:</span><span>{{ trim((string) ($master->mobile ?? '')) }}</span></div>
      <div class="line"><b>GSTIN</b><span>:</span><span>{{ trim((string) ($master->tin ?? '')) }}</span></div>
      <div class="line"><b>State</b><span>:</span><span>{{ trim((string) ($master->statecode ?? '')) }} {{ trim((string) ($master->state_name ?? '')) }}</span></div>
    </div>
    <div class="card">
      <div class="line"><b id="docNoLabel">Doc No</b><span>:</span><span>{{ trim((string) ($master->docno ?? '')) }}</span></div>
      <div class="line"><b id="docDateLabel">Date</b><span>:</span><span>{{ !empty($master->tdate) ? \Carbon\Carbon::parse($master->tdate)->format('d/m/Y') : '' }}</span></div>
      <div class="line"><b>Time</b><span>:</span><span>{{ trim((string) ($master->ttime ?? '')) }}</span></div>
      <div class="line"><b>Rate</b><span>:</span><span>{{ number_format((float) ($master->rate ?? 0), 2) }}</span></div>
      <div class="line"><b>Salesman</b><span>:</span><span>{{ trim((string) ($master->smcode ?? '')) }}{{ !empty($master->sman_name) ? ' - '.trim((string) $master->sman_name) : '' }}</span></div>
      <div class="line"><b>Person</b><span>:</span><span>{{ trim((string) ($master->person ?? '')) }}</span></div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th class="c">Sl</th>
        <th>Item Details</th>
        <th class="c">HSN/SAC</th>
        <th class="c">Qty</th>
        <th class="r">Issued Gr.Wgt</th>
        <th class="r">Received Gr.Wgt</th>
        <th class="r">St.Wgt</th>
        <th class="r">Net Wgt</th>
        <th class="r">Touch</th>
        <th class="r">MC</th>
        <th class="r">St.Price</th>
        <th class="r">HMC</th>
        <th class="r">Issued TM Wgt</th>
        <th class="r">Received TM Wgt</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $i => $row)
      <tr>
        <td class="c">{{ $i + 1 }}</td>
        <td>{{ $row['item'] }}{{ $row['model'] !== '' ? ' / '.$row['model'] : '' }}{{ $row['remark'] !== '' ? ' / '.$row['remark'] : '' }}</td>
        <td class="c">{{ $row['hsn'] }}</td>
        <td class="c">{{ $row['qty'] == 0 ? '' : number_format($row['qty'], 0) }}</td>
        <td class="r">{{ $row['issue_wgt'] == 0 ? '' : number_format($row['issue_wgt'], 3) }}</td>
        <td class="r">{{ $row['recv_wgt'] == 0 ? '' : number_format($row['recv_wgt'], 3) }}</td>
        <td class="r">{{ $row['stone_wgt'] == 0 ? '' : number_format($row['stone_wgt'], 3) }}</td>
        <td class="r">{{ ($row['net_wgt'] ?? 0) == 0 ? '' : number_format($row['net_wgt'], 3) }}</td>
        <td class="r">{{ $row['touch'] == 0 ? '' : number_format($row['touch'], 3) }}</td>
        <td class="r">{{ $row['mc'] == 0 ? '' : number_format($row['mc'], 2) }}</td>
        <td class="r">{{ $row['stone_price'] == 0 ? '' : number_format($row['stone_price'], 2) }}</td>
        <td class="r">{{ $row['hmc'] == 0 ? '' : number_format($row['hmc'], 2) }}</td>
        <td class="r">{{ $row['issue_net'] == 0 ? '' : number_format($row['issue_net'], 3) }}</td>
        <td class="r">{{ $row['recv_net'] == 0 ? '' : number_format($row['recv_net'], 3) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot class="totals">
      <tr>
        <td colspan="3">TOTAL</td>
        <td class="c">{{ number_format(collect($rows)->sum('qty'), 0) }}</td>
        <td class="r">{{ number_format(collect($rows)->sum('issue_wgt'), 3) }}</td>
        <td class="r">{{ number_format(collect($rows)->sum('recv_wgt'), 3) }}</td>
        <td class="r">{{ number_format(collect($rows)->sum('stone_wgt'), 3) }}</td>
        <td class="r">{{ number_format(collect($rows)->sum('net_wgt'), 3) }}</td>
        <td></td>
        <td class="r">{{ number_format(collect($rows)->sum('mc'), 2) }}</td>
        <td class="r">{{ number_format(collect($rows)->sum('stone_price'), 2) }}</td>
        <td class="r">{{ number_format(collect($rows)->sum('hmc'), 2) }}</td>
        <td class="r">{{ number_format($issuedWgt, 3) }}</td>
        <td class="r">{{ number_format($receivedWgt, 3) }}</td>
      </tr>
    </tfoot>
  </table>

  <div class="summary">
    <table>
      <tr><td>Op. Wgt. Bal.</td><td class="r">{{ number_format((float) ($master->opwgt ?? 0), 3) }}</td></tr>
      <tr><td>Wgt Issued</td><td class="r">{{ number_format($issuedWgt, 3) }}</td></tr>
      <tr><td>Wgt Received</td><td class="r">{{ number_format($receivedWgt, 3) }}</td></tr>
      <tr><td>Cl. Wgt. Bal.</td><td class="r">{{ number_format($closingWgt, 3) }}</td></tr>
    </table>
    <table>
      <tr><td>Op. Amt. Bal.</td><td class="r">{{ number_format((float) ($master->opamt ?? 0), 2) }}</td></tr>
      <tr><td>Amt Debited</td><td class="r">{{ number_format($dbAmt, 2) }}</td></tr>
      <tr><td>Amt Credited</td><td class="r">{{ number_format($crAmt, 2) }}</td></tr>
      <tr><td>Cl. Amt. Bal.</td><td class="r">{{ number_format($closingAmt, 2) }}</td></tr>
    </table>
  </div>

  <div class="summary hidden" id="taxBlock">
    <table>
      <tr><td>Gross Amount</td><td class="r">{{ number_format($grossAmount, 2) }}</td></tr>
      <tr id="igstRow"><td id="igstLabel">{{ $igstLabel }}</td><td class="r">{{ number_format($igstAmount, 2) }}</td></tr>
      <tr id="cgstRow"><td id="cgstLabel">{{ $cgstLabel }}</td><td class="r">{{ number_format($cgstAmount, 2) }}</td></tr>
      <tr id="sgstRow"><td id="sgstLabel">{{ $sgstLabel }}</td><td class="r">{{ number_format($sgstAmount, 2) }}</td></tr>
      <tr><td>Discount</td><td class="r">{{ number_format((float) ($master->discount ?? 0), 2) }}</td></tr>
      <tr><td>TDS</td><td class="r">{{ number_format((float) ($master->tdsamt ?? 0), 2) }}</td></tr>
      <tr><td>TCS</td><td class="r">{{ number_format((float) ($master->tcsamt ?? 0), 2) }}</td></tr>
      <tr><td>Acid Charge</td><td class="r">{{ number_format((float) ($master->acidcharge ?? 0), 2) }}</td></tr>
      <tr><td><b>Total</b></td><td class="r"><b>{{ number_format($finalAmount, 2) }}</b></td></tr>
    </table>
    <table>
      <tr><td>Tax %</td><td class="r">{{ number_format((float) ($master->taxperc ?? 0), 2) }}</td></tr>
      <tr><td>Tax Amount</td><td class="r">{{ number_format((float) ($master->taxamt ?? 0), 2) }}</td></tr>
      <tr><td>Interstate</td><td>{{ strtoupper(trim((string) ($master->interstate ?? 'N'))) === 'Y' ? 'Yes' : 'No' }}</td></tr>
      <tr><td>Reverse Tax</td><td>{{ strtoupper(trim((string) ($master->taxreverse ?? 'N'))) === 'Y' ? 'Yes' : 'No' }}</td></tr>
      <tr><td>Rate</td><td class="r">{{ number_format((float) ($master->rate ?? 0), 2) }}</td></tr>
      <tr><td>Person</td><td>{{ trim((string) ($master->person ?? '')) }}</td></tr>
      <tr><td>Salesman</td><td>{{ trim((string) ($master->smcode ?? '')) }}{{ !empty($master->sman_name) ? ' - '.trim((string) $master->sman_name) : '' }}</td></tr>
      <tr><td>Place of Supply</td><td>{{ trim((string) ($master->placeos ?? '')) }}</td></tr>
    </table>
  </div>

  @if(!empty($master->transportmode) || !empty($master->vehno) || !empty($master->placeos) || !empty($master->purpose) || !empty($master->note))
  <div class="summary" id="transportBlock">
    <table>
      <tr><td>Transport Mode</td><td>{{ trim((string) ($master->transportmode ?? '')) }}</td></tr>
      <tr><td>Vehicle No</td><td>{{ trim((string) ($master->vehno ?? '')) }}</td></tr>
      <tr><td>Place of Supply</td><td>{{ trim((string) ($master->placeos ?? '')) }}</td></tr>
      <tr><td>Purpose</td><td>{{ trim((string) ($master->purpose ?? '')) }}</td></tr>
    </table>
    <table>
      <tr><td>Note</td><td>{{ trim((string) ($master->note ?? '')) }}</td></tr>
    </table>
  </div>
  @endif

  <div class="foot">
    <div class="sign">Receiver's Signature</div>
    <div class="sign">{{ $company['SHOPNM'] ?? 'Authorized Signatory' }}</div>
  </div>
</div>

<script>
const baseTitle = @json($docTitle);
const isJewellery = @json(strtoupper(trim((string) ($master->ctype ?? ''))) === 'J');
const els = {
  showShop: document.getElementById('cbShowShop'),
  dc: document.getElementById('cbDc'),
  refinery: document.getElementById('cbRefinery'),
  melt: document.getElementById('cbMelt'),
  after: document.getElementById('cbAfter'),
  shopInfo: document.getElementById('shopInfo'),
  title: document.getElementById('docTitle'),
  subTitle: document.getElementById('docSubTitle'),
  docNoLabel: document.getElementById('docNoLabel'),
  docDateLabel: document.getElementById('docDateLabel'),
  transportBlock: document.getElementById('transportBlock'),
  taxBlock: document.getElementById('taxBlock'),
  igstRow: document.getElementById('igstRow'),
  cgstRow: document.getElementById('cgstRow'),
  sgstRow: document.getElementById('sgstRow')
};

function refreshPreview() {
  const showShop = els.showShop.checked;
  const isDc = els.dc.checked;
  const isRefinery = els.refinery.checked;
  const isMelt = els.melt.checked;
  const isAfter = els.after.checked;

  els.shopInfo.style.display = showShop ? '' : 'none';
  els.docNoLabel.textContent = isDc ? 'Delivery Challan No' : 'Doc No';
  els.docDateLabel.textContent = isDc ? 'Delivery Challan Date' : 'Date';

  let title = baseTitle;
  let subTitle = '';

  if (isDc) {
    title = 'GST DELIVERY CHALLAN';
    if (isAfter) subTitle = 'ISSUE AFTER JOB WORK';
  } else if (isJewellery && isRefinery) {
    subTitle = 'ISSUE FOR REFINING';
  } else if (isJewellery && isMelt) {
    subTitle = 'ISSUE FOR MELTING';
  } else if (isAfter) {
    subTitle = 'ISSUE AFTER JOB WORK';
  }

  els.title.textContent = title;
  els.subTitle.textContent = subTitle;
  els.subTitle.style.display = subTitle ? '' : 'none';

  if (els.transportBlock) {
    els.transportBlock.style.display = isDc ? '' : '';
  }

  if (els.taxBlock) {
    els.taxBlock.classList.toggle('hidden', !isDc);
  }
  if (els.igstRow) {
    els.igstRow.style.display = isDc && {{ $igstAmount > 0 ? 'true' : 'false' }} ? '' : 'none';
  }
  if (els.cgstRow) {
    els.cgstRow.style.display = isDc && {{ $cgstAmount > 0 ? 'true' : 'false' }} ? '' : 'none';
  }
  if (els.sgstRow) {
    els.sgstRow.style.display = isDc && {{ $sgstAmount > 0 ? 'true' : 'false' }} ? '' : 'none';
  }
}

function handleExclusive(source) {
  if (source === 'refinery' && els.refinery.checked) els.melt.checked = false;
  if (source === 'melt' && els.melt.checked) els.refinery.checked = false;
  refreshPreview();
}

els.showShop.addEventListener('change', refreshPreview);
els.dc.addEventListener('change', refreshPreview);
els.after.addEventListener('change', refreshPreview);
els.refinery.addEventListener('change', () => handleExclusive('refinery'));
els.melt.addEventListener('change', () => handleExclusive('melt'));

if (!isJewellery) {
  els.refinery.disabled = true;
  els.melt.disabled = true;
}

refreshPreview();

function printOnlyPreview() {
  const printWin = window.open('', '_blank', 'width=1100,height=850,scrollbars=yes');
  if (!printWin) return;

  const styles = Array.from(document.querySelectorAll('style'))
    .map((el) => el.outerHTML)
    .join('');

  const pageHtml = document.querySelector('.page').outerHTML;
  const title = document.title.replace(/"/g, '&quot;');

  printWin.document.open();
  printWin.document.write(`<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${title}</title>
${styles}

</head>
<body>
${pageHtml}
<script>
window.addEventListener('load', function () {
  setTimeout(function () {
    window.print();
  }, 300);
});
<\/script>
</body>
</html>`);
  printWin.document.close();
  printWin.focus();
}
</script>
</body>
</html>
