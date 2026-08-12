<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smith Receipt - {{ trim((string) ($master->docno ?? '')) }}</title>
<style>
body{margin:0;background:#eef1ed;color:#000;font-family:"Times New Roman",Times,serif;font-size:12px}
.toolbar{display:flex;justify-content:space-between;gap:10px;padding:10px 14px;background:#19471f;color:#f7d58b;border-bottom:1px solid rgba(0,0,0,.18);align-items:center;position:sticky;top:0;z-index:10}
.toolbar button{height:28px;padding:0 14px;border:1px solid #a57c27;background:#f4d184;color:#241b0a;border-radius:3px;font-weight:700;cursor:pointer}
.controls{display:flex;gap:10px 14px;flex-wrap:wrap;align-items:center}
.controls label{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700;white-space:nowrap}
.controls input{accent-color:#f4d184}
.tb-lbl{font-size:10px;font-weight:700;color:inherit;white-space:nowrap}
.tb-template-select{height:28px;min-width:128px;border:1px solid rgba(255,255,255,.35);border-radius:6px;padding:4px 8px;font-size:11px;font-weight:700;background:rgba(255,255,255,.95);color:#1f2937}
.page{width:204mm;min-height:230mm;margin:56px auto 18px;background:#fff;padding:9mm 6mm;box-sizing:border-box;box-shadow:0 2px 13px rgba(0,0,0,.12);overflow:hidden}
.shop{text-align:center;line-height:1.18;margin-bottom:4mm}
.shop h1{font-size:17px;line-height:1.08;margin:0 0 1mm;font-weight:800;letter-spacing:0;text-transform:uppercase}
.shop .line{font-size:12px;font-weight:700;text-transform:uppercase}
.shop .gst{font-size:12px;font-weight:700;margin-top:.6mm}
.doc-title{text-align:center;font-size:16px;line-height:1.1;font-weight:800;text-decoration:underline;margin:1mm 0 5mm;text-transform:uppercase}
.top-box{border:1px solid #333;display:grid;grid-template-columns:1.15fr .85fr;margin-bottom:0}
.party-box,.voucher-box{min-height:26mm;padding:3mm 4mm;box-sizing:border-box}
.voucher-box{border-left:1px solid #333}
.kv{display:grid;grid-template-columns:28mm 4mm 1fr;align-items:start;line-height:1.42;font-size:12px}
.voucher-box .kv{grid-template-columns:28mm 4mm 1fr}
.lbl{font-weight:800}
.value{font-weight:700}
.items{width:100%;max-width:100%;border-collapse:collapse;table-layout:fixed;margin-top:0}
.items th,.items td{border:1px solid #333;padding:2px 2px;font-size:10px;line-height:1.12;vertical-align:top;box-sizing:border-box;overflow-wrap:anywhere;word-break:break-word}
.items th{font-weight:800;text-align:center;white-space:normal}
.items td{height:8mm}
.c{text-align:center}.r{text-align:right}.b{font-weight:800}
.item-name{font-weight:700;text-transform:uppercase;word-break:break-word}
.bottom{display:grid;grid-template-columns:1fr 38mm;gap:8mm;margin-top:8mm;align-items:start}
.remarks{display:grid;grid-template-columns:26mm 4mm 1fr;font-size:13px;line-height:1.35;min-height:22mm}
.weight-box{border:1px solid #333}
.weight-row{display:grid;grid-template-columns:1fr 21mm;border-bottom:1px solid #333}
.weight-row:last-child{border-bottom:0}
.weight-row div{padding:4px 6px;font-size:13px;font-weight:800}
.weight-row div:last-child{text-align:right;border-left:1px solid #333}
@media (max-width:1024px){
  .page{width:calc(100% - 24px);min-height:0;margin:18px 12px;padding:18px}
  .top-box{grid-template-columns:1fr}
  .voucher-box{border-left:0;border-top:1px solid #333}
}
@media print{
  @page{size:A4 portrait;margin:0}
  html,body{width:210mm;margin:0!important;padding:0!important;background:#fff!important}
  .toolbar{display:none!important}
  .page[data-print-root="goldsmith-print"]{
    width:204mm!important;
    min-height:0!important;
    margin:0 auto!important;
    padding:7mm 5mm!important;
    box-shadow:none!important;
    color:#000!important;
    font-family:"Times New Roman",Times,serif!important;
    transform:none!important;
  }
  body.goldapp-print-enabled [data-print-root="goldsmith-print"]{
    width:204mm!important;
    max-width:204mm!important;
    padding:7mm 5mm!important;
    margin:0 auto!important;
    transform:none!important;
    color:#000!important;
    font-family:"Times New Roman",Times,serif!important;
  }
  .shop,.doc-title,.top-box,.bottom{break-inside:avoid;page-break-inside:avoid}
  .items{break-inside:auto;page-break-inside:auto}
  .items tr{break-inside:avoid;page-break-inside:avoid}
  .items th,.items td{font-size:8.8px!important;padding:1.6px 1.8px!important;border-color:#000!important;overflow-wrap:anywhere!important;word-break:break-word!important}
  .shop h1{font-size:16px!important}
  .shop .line,.shop .gst,.kv{font-size:11.2px!important}
  .doc-title{font-size:15px!important}
}
</style>
@include('partials.print-layout-head')
<style>
body.goldapp-print-enabled [data-print-root="goldsmith-print"],
.page[data-print-root="goldsmith-print"]{
  width:204mm!important;
  max-width:204mm!important;
  box-sizing:border-box!important;
  overflow:hidden!important;
}
[data-print-root="goldsmith-print"] .items{
  width:100%!important;
  max-width:100%!important;
  table-layout:fixed!important;
  border-collapse:collapse!important;
}
[data-print-root="goldsmith-print"] .items th,
[data-print-root="goldsmith-print"] .items td{
  box-sizing:border-box!important;
  overflow-wrap:anywhere!important;
  word-break:break-word!important;
  padding:2px 2px!important;
  font-size:10px!important;
  line-height:1.1!important;
}
@media print{
  body.goldapp-print-enabled [data-print-root="goldsmith-print"],
  .page[data-print-root="goldsmith-print"]{
    width:204mm!important;
    max-width:204mm!important;
    padding:7mm 5mm!important;
    margin:0 auto!important;
    transform:none!important;
  }
  [data-print-root="goldsmith-print"] .items th,
  [data-print-root="goldsmith-print"] .items td{
    padding:1.5px 1.6px!important;
    font-size:8.6px!important;
    line-height:1.05!important;
  }
}
</style>
</head>
<body>
@php
  $fmt3 = fn ($v) => abs((float) $v) < 0.0005 ? '0.000' : number_format((float) $v, 3);
  $fmt2 = fn ($v) => abs((float) $v) < 0.005 ? '0.00' : number_format((float) $v, 2);
  $fmtQty = fn ($v) => abs((float) $v) < 0.0005 ? '0' : number_format((float) $v, 0);
  $shopName = trim((string) ($company['SHOPNM'] ?? 'SALEENA GOLD AND DIAMONDS'));
  $shopAddr = trim((string) ($company['SHOPADDR'] ?? ''));
  $shopGstin = trim((string) ($company['GSTIN'] ?? $company['GSTNO'] ?? $company['KGST'] ?? ''));
  $partyName = trim((string) ($master->party_name ?? $master->smithcode ?? ''));
  $docDate = !empty($master->tdate) ? \Carbon\Carbon::parse($master->tdate)->format('d-F-Y') : '';
  $voucherNo = trim((string) ($master->docno ?? ''));
  $defaultPrintTemplate = '1';
  $printTemplate = in_array((string) request('template', $defaultPrintTemplate), ['1', '2', '3', '4', '5'], true)
      ? (string) request('template', $defaultPrintTemplate)
      : $defaultPrintTemplate;
  $purityText = function ($row) {
      $stktouch = (float) ($row['stktouch'] ?? 0);
      if ($stktouch > 0 && $stktouch < 100) return rtrim(rtrim(number_format($stktouch, 2), '0'), '.');
      $code = strtoupper(trim((string) ($row['stktype'] ?? '')));
      return $code !== '' ? $code : '';
  };
@endphp
<div class="toolbar">
  <div class="controls">
    <span class="tb-lbl">Template</span>
    <select class="tb-template-select" id="printTemplateSelect">
      <option value="1" {{ $printTemplate === '1' ? 'selected' : '' }}>Template 1</option>
      <option value="2" {{ $printTemplate === '2' ? 'selected' : '' }}>Template 2</option>
      <option value="3" {{ $printTemplate === '3' ? 'selected' : '' }}>Template 3</option>
      <option value="4" {{ $printTemplate === '4' ? 'selected' : '' }}>Template 4</option>
      <option value="5" {{ $printTemplate === '5' ? 'selected' : '' }}>Template 5</option>
    </select>
    <label><input type="checkbox" id="cbShowShop" checked> Show Shop Info</label>
    <label><input type="checkbox" id="cbDc"> Delivery Challan</label>
    <label><input type="checkbox" id="cbRefinery"> Refinery</label>
    <label><input type="checkbox" id="cbMelt"> Melting</label>
    <label><input type="checkbox" id="cbAfter" checked> After Job Work</label>
  </div>
  <button type="button" onclick="printOnlyPreview()">Print</button>
</div>

<div class="page" data-print-root="goldsmith-print" data-print-template="{{ $printTemplate }}">
  <div class="shop" id="shopInfo">
    <h1>{{ $shopName }}</h1>
    @if($shopAddr !== '')
      <div class="line">{{ $shopAddr }}</div>
    @endif
    @if($shopGstin !== '')
      <div class="gst">GSTIN-{{ $shopGstin }}</div>
    @endif
  </div>

  <div class="doc-title" id="docTitle">RECEIPT AFTER JOBWORK</div>

  <div class="top-box">
    <div class="party-box">
      <div class="kv"><div class="lbl">Party Name</div><div>:</div><div class="value">{{ $partyName }}</div></div>
      <div class="kv"><div class="lbl"></div><div></div><div>{{ trim((string) ($master->smithcode ?? '')) }}</div></div>
      @if(trim((string) ($master->tin ?? '')) !== '')
        <div class="kv"><div class="lbl">GSTIN-PANNO</div><div>:</div><div>{{ trim((string) ($master->tin ?? '')) }}</div></div>
      @else
        <div class="kv"><div class="lbl">GSTIN-PANNO</div><div>:</div><div></div></div>
      @endif
    </div>
    <div class="voucher-box">
      <div class="kv"><div class="lbl" id="docNoLabel">Voucher No</div><div>:</div><div>{{ $voucherNo }}</div></div>
      <div class="kv"><div class="lbl" id="docDateLabel">Voucher Date</div><div>:</div><div>{{ $docDate }}</div></div>
      <div class="kv"><div class="lbl">Emp Code</div><div>:</div><div>{{ trim((string) ($master->person ?? $master->smcode ?? '')) }}</div></div>
      <div class="kv"><div class="lbl">Opn. Wt</div><div>:</div><div class="b">{{ $fmt3((float) ($master->opwgt ?? 0)) }}</div></div>
    </div>
  </div>

  <table class="items">
    <colgroup>
      <col style="width:4%">
      <col style="width:6%">
      <col style="width:20%">
      <col style="width:6%">
      <col style="width:6%">
      <col style="width:6%">
      <col style="width:4%">
      <col style="width:7%">
      <col style="width:6%">
      <col style="width:8%">
      <col style="width:6%">
      <col style="width:7%">
      <col style="width:7%">
      <col style="width:7%">
    </colgroup>
    <thead>
      <tr>
        <th>Sl.No</th>
        <th>Branch</th>
        <th>ItemName</th>
        <th>Touch</th>
        <th>WstWt</th>
        <th>Purity</th>
        <th>Nos</th>
        <th>Gwt</th>
        <th>StWt</th>
        <th>StoneAmt</th>
        <th>DiaWt</th>
        <th>DmAmt</th>
        <th>ActualWt</th>
        <th>NetWt</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $i => $row)
        @php
          $grossWgt = (float) ($row['issue_wgt'] ?? 0) + (float) ($row['recv_wgt'] ?? 0);
          $actualWgt = max(0.0, $grossWgt - (float) ($row['stone_wgt'] ?? 0));
          $netWgt = (float) ($row['issue_net'] ?? 0) + (float) ($row['recv_net'] ?? 0);
          if (abs($netWgt) < 0.0005) $netWgt = (float) ($row['net_wgt'] ?? $actualWgt);
          $itemText = trim((string) ($row['item'] ?? ''));
          if (trim((string) ($row['model'] ?? '')) !== '') $itemText .= ' / '.trim((string) $row['model']);
        @endphp
        <tr>
          <td class="c">{{ $i + 1 }}</td>
          <td class="c">{{ trim((string) ($row['stktype'] ?? '')) }}</td>
          <td class="item-name">{{ $itemText }}</td>
          <td class="r">{{ $fmt3($row['touch'] ?? 0) }}</td>
          <td class="r">{{ $fmt3($row['wastage'] ?? 0) }}</td>
          <td class="c">{{ $purityText($row) }}</td>
          <td class="c">{{ $fmtQty($row['qty'] ?? 0) }}</td>
          <td class="r">{{ $fmt3($grossWgt) }}</td>
          <td class="r">{{ $fmt3($row['stone_wgt'] ?? 0) }}</td>
          <td class="r">{{ $fmt2($row['stone_price'] ?? 0) }}</td>
          <td class="r">0.000</td>
          <td class="r">{{ $fmt2($row['mc'] ?? 0) }}</td>
          <td class="r">{{ $fmt3($actualWgt) }}</td>
          <td class="r">{{ $fmt3($netWgt) }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td colspan="12" class="b c">Total</td>
        <td class="r b">{{ $fmt3(collect($rows)->sum(fn ($row) => max(0.0, ((float) ($row['issue_wgt'] ?? 0) + (float) ($row['recv_wgt'] ?? 0)) - (float) ($row['stone_wgt'] ?? 0)))) }}</td>
        <td class="r b">{{ $fmt3($issuedWgt + $receivedWgt) }}</td>
      </tr>
    </tfoot>
  </table>

  <div class="bottom">
    <div>
      <div class="remarks">
        <div class="lbl">Remarks</div><div>:</div><div>{{ trim((string) ($master->note ?? '')) }}</div>
      </div>
      <div class="kv"><div class="lbl">Cls.W</div><div>:</div><div class="b">{{ $fmt3($closingWgt) }}</div></div>
    </div>
    <div class="weight-box">
      <div class="weight-row"><div>Opn. Wt</div><div>{{ $fmt3((float) ($master->opwgt ?? 0)) }}</div></div>
      <div class="weight-row"><div>Cls.W</div><div>{{ $fmt3($closingWgt) }}</div></div>
    </div>
  </div>
</div>

<script>
const isJewellery = @json(strtoupper(trim((string) ($master->ctype ?? ''))) === 'J');
const els = {
  template: document.getElementById('printTemplateSelect'),
  showShop: document.getElementById('cbShowShop'),
  dc: document.getElementById('cbDc'),
  refinery: document.getElementById('cbRefinery'),
  melt: document.getElementById('cbMelt'),
  after: document.getElementById('cbAfter'),
  shopInfo: document.getElementById('shopInfo'),
  title: document.getElementById('docTitle'),
  docNoLabel: document.getElementById('docNoLabel'),
  docDateLabel: document.getElementById('docDateLabel')
};

function refreshPreview() {
  const showShop = els.showShop.checked;
  const isDc = els.dc.checked;
  const isRefinery = els.refinery.checked;
  const isMelt = els.melt.checked;
  const isAfter = els.after.checked;

  els.shopInfo.style.display = showShop ? '' : 'none';
  els.docNoLabel.textContent = isDc ? 'Delivery Challan No' : 'Voucher No';
  els.docDateLabel.textContent = isDc ? 'Delivery Challan Date' : 'Voucher Date';

  let title = 'GOLDSMITH TRANSACTION';
  if (isDc) {
    title = 'GST DELIVERY CHALLAN';
  } else if (isRefinery) {
    title = 'ISSUE FOR REFINING';
  } else if (isMelt) {
    title = 'ISSUE FOR MELTING';
  } else if (isAfter) {
    title = 'RECEIPT AFTER JOBWORK';
  }
  els.title.textContent = title;
}

function handleExclusive(source) {
  if (source === 'refinery' && els.refinery.checked) els.melt.checked = false;
  if (source === 'melt' && els.melt.checked) els.refinery.checked = false;
  refreshPreview();
}

els.template.addEventListener('change', () => {
  const url = new URL(window.location.href);
  url.searchParams.set('template', els.template.value);
  window.location.replace(url.toString());
});
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
  const printWin = window.open('', '_blank', 'width=900,height=850,scrollbars=yes');
  if (!printWin) return;
  const styles = Array.from(document.querySelectorAll('style')).map((el) => el.outerHTML).join('');
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
  setTimeout(function () { window.print(); }, 300);
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
