<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $printDocumentTitle }} - {{ $billno }}</title>
<style>
:root{
    --ink:#0f172a;
    --muted:#475569;
    --line:#cbd5e1;
    --soft:#e2e8f0;
    --accent:#b3261e;
    --accent-2:#d23a30;
    --paper:#fff;
    --panel:#fdf7f6;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Segoe UI,Arial,sans-serif;font-size:12px;color:var(--ink);background:#ffffff}

.toolbar{
    background:linear-gradient(135deg,#7a2018,#b3261e);
    padding:8px 12px;
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
    border-bottom:1px solid rgba(255,255,255,.12);
    position:sticky;
    top:0;
    z-index:100;
    box-shadow:0 2px 8px rgba(15,23,42,.18);
    color:#fff;
}
.tb-lbl{font-size:10px;font-weight:600;color:#fff;white-space:nowrap;letter-spacing:.3px}
.tb-inp{
    width:56px;
    text-align:center;
    border:1px solid rgba(255,255,255,.35);
    border-radius:6px;
    padding:4px 5px;
    font-size:11px;
    height:28px;
    background:rgba(255,255,255,.95);
    color:#1f2937;
}
.tb-btn{
    padding:5px 14px;
    font-weight:600;
    font-size:11px;
    border:1px solid rgba(255,255,255,.35);
    border-radius:6px;
    background:rgba(255,255,255,.10);
    cursor:pointer;
    color:#fff;
    height:30px;
}
.tb-btn:hover{background:rgba(255,255,255,.22);border-color:rgba(255,255,255,.55)}
.tb-btn.primary{background:#ffb548;color:#2a1a00;border-color:#ffb548}
.tb-btn.primary:hover{background:#f5a937;border-color:#f5a937}
.tb-chk{display:flex;align-items:center;gap:4px;font-size:10.5px;color:#fff;white-space:nowrap;font-weight:600}
.tb-chk input{accent-color:#ffb548}
.tb-sep{width:1px;height:20px;background:rgba(255,255,255,.25);flex-shrink:0}

.page-wrap{padding:18px 0}
.page{
    width:210mm;
    min-height:297mm;
    margin:0 auto;
    background:#ffffff;
    border:1px solid var(--soft);
    box-shadow:0 10px 30px rgba(15,23,42,.08);
    padding:10mm 9mm;
    overflow:visible;
    print-color-adjust:exact;
    -webkit-print-color-adjust:exact;
}
.page.landscape{width:297mm;min-height:210mm}

.return-banner{
    position:absolute;
    top:6px;
    right:0;
    transform:rotate(-12deg);
    padding:4px 14px;
    border:2px solid var(--accent);
    color:var(--accent);
    font-weight:800;
    font-size:12px;
    letter-spacing:2px;
    border-radius:6px;
    background:rgba(179,38,30,.05);
}

.header{
    display:grid;
    grid-template-columns:1fr;
    gap:8px;
    border-bottom:2px solid var(--accent);
    padding-bottom:10px;
    margin-bottom:14px;
    position:relative;
}
.company{
    text-align:center;
    padding:0 90px;
}
.company h1{margin:0 0 4px;font-size:24px;letter-spacing:.4px;line-height:1.18}
.company-addr{font-size:12.5px;font-weight:700;color:var(--ink);line-height:1.35;margin-bottom:4px;max-width:none}
.company-meta{font-size:12px;font-weight:700;color:var(--ink);line-height:1.35;display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:3px}
.company-meta span{white-space:nowrap}
.bill-meta{
    min-width:0;
    display:grid;
    grid-template-columns:minmax(0,1fr) auto;
    align-items:start;
    gap:18px;
    border-top:1px solid var(--soft);
    padding-top:8px;
}
.bill-meta .meta{color:var(--ink);font-size:12.5px;font-weight:700;line-height:1.45;text-align:right}
.bill-meta .meta div{margin-bottom:2px}
.bill-meta .meta strong{font-weight:800;color:var(--ink)}
.bill-title{font-size:18px;font-weight:800;color:var(--accent);margin:0;text-transform:uppercase;line-height:1.25}

.grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);gap:10px;margin-bottom:12px}
.card{border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}
.card h3{
    margin:0;
    padding:7px 10px;
    font-size:12.5px;
    letter-spacing:.4px;
    color:#fff;
    background:linear-gradient(135deg,var(--accent),var(--accent-2));
    text-transform:uppercase;
}
.card .body{padding:9px 10px;font-size:12px;line-height:1.45;background:var(--panel)}
.kv{display:grid;grid-template-columns:105px 1fr;gap:4px 8px}
.kv .k{color:var(--muted);font-weight:600}
.muted{color:var(--muted)}

.section-title{margin:12px 0 7px;font-size:13.5px;font-weight:700;color:#5b1410}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid var(--line);padding:7px 8px;font-size:12px}
thead th{background:#fbe9e7;color:#5b1410;text-transform:uppercase;letter-spacing:.25px}
td.num,th.num{text-align:right}
td.center,th.center{text-align:center}
tfoot td{background:#fdf3f2;font-weight:700}

.return-items{
    table-layout:fixed;
    width:100%;
}
.return-items th,
.return-items td{
    padding:5px 3px;
    font-size:9.4px;
    line-height:1.18;
    overflow-wrap:anywhere;
}
.return-items th{
    white-space:normal;
}
.return-items td.num,
.return-items th.num{
    font-variant-numeric:tabular-nums;
    white-space:nowrap;
}
.return-items td:nth-child(3){
    overflow-wrap:normal;
    word-break:normal;
}
.return-items tfoot td{
    font-size:9.6px;
}

.totals{
    margin-top:12px;
    display:grid;
    grid-template-columns:minmax(0,1fr) minmax(260px,300px);
    gap:14px;
    align-items:start;
}
.stack{display:grid;gap:14px}
.note-box,.foot-box{
    border:1px solid var(--line);
    border-radius:10px;
    padding:12px;
    background:#fff;
}
.note-box{min-height:82px;font-size:12px}
.foot-box{font-size:12px;line-height:1.6;color:var(--muted)}
.note-box h4,.foot-box h4{margin:0 0 8px;font-size:13px;color:#5b1410}

.summary{border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}
.summary table td{font-size:13px}
.summary td:first-child{color:var(--muted);font-weight:600;width:58%}
.summary tr.total td{background:#fdecea;font-size:15px;font-weight:800;color:#5b1410}

.footer{
    margin-top:20px;
    display:grid;
    grid-template-columns:1fr auto;
    gap:30px;
    font-size:12px;
    color:var(--muted);
    align-items:end;
}
.sign-row{display:grid;grid-template-columns:160px 160px;gap:16px}
.sign{
    text-align:center;
    padding-top:26px;
    border-top:1px solid var(--line);
}

@media print{
    @page{size:A4 portrait;margin:0}
    .toolbar{display:none!important}
    body{background:#fff}
    .page-wrap{padding:0}
    .page{border:none;box-shadow:none;width:210mm;min-height:297mm;padding:10mm 9mm}
    .page.landscape{width:297mm;min-height:210mm}
    .return-items th,
    .return-items td{font-size:8.8px;padding:4px 2px}
    .return-items tfoot td{font-size:9px}
    .totals{grid-template-columns:minmax(0,1fr) 285px}
    .sign-row{grid-template-columns:150px 150px}
}
</style>
@include('partials.print-layout-head')
</head>
<body>
@php
    $printKind = $printKind ?? 'sales-return';
    $partyLabel = $partyLabel ?? 'Customer';
    $partyDetailsLabel = $partyDetailsLabel ?? 'Customer Details';
    $partySignatureLabel = $partySignatureLabel ?? 'Customer Signature';
    $origBillLabel = $origBillLabel ?? 'Orig. Bill';
    $netTotalLabel = $netTotalLabel ?? 'Refund / Net Total';
    $paidLabel = $paidLabel ?? 'Paid Out';
@endphp
<div class="toolbar">
    <span class="tb-lbl">Copies</span>
    <input type="number" class="tb-inp" id="copies" value="{{ $copies }}" min="1" max="10">

    <span class="tb-lbl">Zoom%</span>
    <input type="text" class="tb-inp" id="zoom" value="{{ $zoom }}">

    <div class="tb-chk">
        <input type="checkbox" id="cbLandscape" {{ $landscape ? 'checked' : '' }}
               onchange="document.getElementById('billCard').classList.toggle('landscape',this.checked)">
        <label for="cbLandscape">Landscape</label>
    </div>

    <div class="tb-chk">
        <input type="checkbox" id="cbShopInfo" checked
               onchange="syncShopHeaderVisibility()">
        <label for="cbShopInfo">Show Shop Info</label>
    </div>

    <div class="tb-sep"></div>

    <button class="tb-btn primary" onclick="doPrint()">Print</button>
    <button class="tb-btn" onclick="window.print()">PDF / Save As</button>
    <button class="tb-btn" onclick="exitPrint()">Exit</button>
</div>

<div class="page-wrap" data-print-root="{{ $printKind }}">
<div class="page{{ $landscape ? ' landscape' : '' }}" id="billCard">
    <div class="header" id="shopHdr" data-print-app-header="{{ $printKind }}-shop">
        <div class="company">
            <h1 id="shopName">{{ $companyName ?: 'Company' }}</h1>
            @if(trim($companyAddr) !== '' || trim($companyAddr2) !== '')
            <div class="company-addr">
                {!! nl2br(e(trim($companyAddr))) !!}@if(trim($companyAddr2) !== '')<br>{!! nl2br(e(trim($companyAddr2))) !!}@endif
            </div>
            @endif
            @if(trim($companyPhone) !== '' || trim($companyGSTIN) !== '' || trim($companyState) !== '')
            <div class="company-meta">
                @if(trim($companyPhone) !== '')<span>Ph: {{ trim($companyPhone) }}</span>@endif
                @if(trim($companyGSTIN) !== '' && $printGSTIN)<span>GSTIN: {{ trim($companyGSTIN) }}</span>@endif
                @if(trim($companyState) !== '')<span>State: {{ trim($companyState) }}</span>@endif
            </div>
            @endif
        </div>
        <div class="bill-meta">
            <div class="bill-title">{{ $printDocumentTitle }}</div>
            <div class="meta">
                <div><strong>Return No:</strong> {{ $billno }}</div>
                <div><strong>Date:</strong> {{ $tdate ? date('d/m/Y', strtotime($tdate)) : '-' }}</div>
                @if($sbillno)<div><strong>{{ $origBillLabel }}:</strong> {{ $sbillno }}</div>@endif
                @if($smcode || $smanName)<div><strong>Salesman:</strong> {{ $smanName ?: $smcode }}</div>@endif
            </div>
        </div>
        <div class="return-banner">RETURN</div>
    </div>

    <div class="grid">
        <div class="card">
            <h3>{{ $partyDetailsLabel }}</h3>
            <div class="body">
                <div><strong>{{ $custname ?: '-' }}</strong>@if($custcode) <span class="muted">({{ $custcode }})</span>@endif</div>
                <div class="kv" style="margin-top:8px">
                    <div class="k">Address</div>
                    <div>
                        {{ $addr ?: '-' }}
                        @if($ad2)<div>{{ $ad2 }}</div>@endif
                        @if($ad3)<div>{{ $ad3 }}</div>@endif
                        @if($ad4)<div>{{ $ad4 }}</div>@endif
                    </div>
                    <div class="k">Phone / Mobile</div><div>{{ ($printCustMob && trim($phoneLine) !== '') ? trim($phoneLine) : '-' }}</div>
                    <div class="k">GSTIN</div><div>{{ $ctin ?: '-' }}</div>
                    <div class="k">PAN</div><div>{{ $pan ?: '-' }}</div>
                    <div class="k">State</div><div>{{ $custState ?: '-' }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Return Details</h3>
            <div class="body">
                <div class="kv">
                    <div class="k">Gold Rate / gm</div><div>{{ number_format($grate, 2, '.', '') }}</div>
                    @if($staxperc > 0)
                    <div class="k">Tax %</div><div>{{ number_format($staxperc, 2, '.', '') }}%</div>
                    @endif
                    @if($astamt > 0)<div class="k">Cess</div><div>{{ number_format($astamt, 2, '.', '') }}</div>@endif
                    @if($discount > 0)<div class="k">Discount</div><div>{{ number_format($discount, 2, '.', '') }}</div>@endif
                    @if($ob != 0)<div class="k">Opening Balance</div><div>{{ number_format($ob, 2, '.', '') }}</div>@endif
                    <div class="k">Status</div><div>{{ (int) $control === 1 ? 'Confirmed' : 'Estimate' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="section-title">Returned Items</div>
    <table class="return-items">
        <colgroup>
            <col style="width:4%">
            <col style="width:7%">
            <col style="width:19%">
            <col style="width:8%">
            <col style="width:5%">
            <col style="width:8%">
            <col style="width:8%">
            <col style="width:8%">
            <col style="width:8%">
            <col style="width:8%">
            <col style="width:5%">
            <col style="width:12%">
        </colgroup>
        <thead>
            <tr>
                <th class="center">#</th>
                <th class="center">Purity</th>
                <th>Item Name</th>
                <th class="center">HSN</th>
                <th class="num">Qty</th>
                <th class="num">Gross Wgt</th>
                <th class="num">Stone Wgt</th>
                <th class="num">Net Wgt</th>
                <th class="num">Rate</th>
                <th class="num">Stone Amt</th>
                <th class="num">MC</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
        @php $sno = 0; @endphp
        @foreach($rows as $r)
            @php
                $vis = (float)$r['weight'] > 0 || (int)$r['qty'] > 0 || (float)$r['amount'] > 0;
                if ($vis) $sno++;
            @endphp
            <tr style="{{ !$vis ? 'display:none' : '' }}">
                <td class="center">{{ $vis ? $sno : '' }}</td>
                <td class="center">{{ $r['iqtype'] }}</td>
                <td>{{ $r['name'] }}</td>
                <td class="center">{{ $r['vatcode'] }}</td>
                <td class="num">{{ $vis ? number_format($r['qty'], 0) : '' }}</td>
                <td class="num">{{ $vis ? number_format($r['weight'], 3) : '' }}</td>
                <td class="num">{{ $vis ? number_format($r['stonewgt'], 3) : '' }}</td>
                <td class="num">{{ $vis ? number_format($r['netwgt'], 3) : '' }}</td>
                <td class="num">{{ $vis ? number_format($r['rate'], 2) : '' }}</td>
                <td class="num">{{ $vis ? number_format($r['stoneprice'], 2) : '' }}</td>
                <td class="num">{{ $vis ? number_format($r['mcharge'], 2) : '' }}</td>
                <td class="num">{{ $vis ? number_format($r['amount'], 2) : '' }}</td>
            </tr>
        @endforeach
        @if($sno === 0)
            <tr><td colspan="12" class="center muted" style="padding:14px">No items in this return.</td></tr>
        @endif
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Total</td>
                <td class="num">{{ number_format($totQty, 0) }}</td>
                <td class="num">{{ number_format($totGrossWgt, 3) }}</td>
                <td class="num">{{ number_format($totStWgt, 3) }}</td>
                <td class="num">{{ number_format($totNetWgt, 3) }}</td>
                <td></td>
                <td class="num">{{ number_format($totStPrice, 2) }}</td>
                <td class="num">{{ number_format($totMc, 2) }}</td>
                <td class="num">{{ number_format($totAmt, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="totals">
        <div class="stack">
            <div class="note-box">
                <h4>Note</h4>
                @php $masterNote = trim((string) (isset($master->note) ? $master->note : '')); @endphp
                <div>{{ $masterNote !== '' ? $masterNote : 'No notes.' }}</div>
            </div>

            @if($printFooter && ($billFooter1 || $billFooter2))
            <div class="foot-box">
                <h4>Footer Notes</h4>
                @if($billFooter1)<div>{{ $billFooter1 }}</div>@endif
                @if($billFooter2)<div>{{ $billFooter2 }}</div>@endif
            </div>
            @endif
        </div>

        <div class="summary">
            <table>
                <tbody>
                    <tr><td>Items Total</td><td class="num">{{ number_format($totAmt, 2) }}</td></tr>
                    @if($discount > 0 && $printDiscount)<tr><td>Discount</td><td class="num">{{ number_format($discount, 2) }}</td></tr>@endif
                    @if($staxamt > 0)
                    <tr><td>{{ $gstLabel }}</td><td class="num">{{ number_format($cgstShow, 2) }}</td></tr>
                    @if(strtoupper((string)$cst) !== 'Y' && $sgstShow > 0)
                    <tr><td>{{ $sgstLabel }}</td><td class="num">{{ number_format($sgstShow, 2) }}</td></tr>
                    @endif
                    @endif
                    @if($astamt > 0)<tr><td>Cess</td><td class="num">{{ number_format($astamt, 2) }}</td></tr>@endif
                    <tr class="total"><td>{{ $netTotalLabel }}</td><td class="num">{{ number_format($netamt, 2) }}</td></tr>
                    <tr><td>{{ $paidLabel }}</td><td class="num">{{ number_format($pamt, 2) }}</td></tr>
                    <tr><td>Balance</td><td class="num">{{ number_format($balance, 2) }}</td></tr>
                    @if($ob != 0)<tr><td>Closing Balance</td><td class="num">{{ number_format($clBalance, 2) }}</td></tr>@endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="footer">
        <div>Printed on {{ now()->format('d/m/Y h:i A') }}</div>
        <div class="sign-row">
            <div class="sign">{{ $partySignatureLabel }}</div>
            <div class="sign">Authorised Signature</div>
        </div>
    </div>
</div>
</div>

<script>
const salesReturnPrintStorageKey = `goldapp-sales-return-print:${window.location.pathname}`;
const salesReturnPrintDefaults = {
    copies: {{ (int) $copies }},
    zoom: @json((string) ($zoom !== '' ? $zoom : '90')),
    landscape: false,
    showShopInfo: true
};

function loadStoredSalesReturnDefaults() {
    try {
        const raw = window.localStorage.getItem(salesReturnPrintStorageKey);
        if (!raw) return {};
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) { return {}; }
}

function saveStoredSalesReturnDefaults() {
    try {
        window.localStorage.setItem(salesReturnPrintStorageKey, JSON.stringify({
            copies: document.getElementById('copies')?.value || salesReturnPrintDefaults.copies,
            zoom: document.getElementById('zoom')?.value || salesReturnPrintDefaults.zoom,
            landscape: !!document.getElementById('cbLandscape')?.checked,
            showShopInfo: !!document.getElementById('cbShopInfo')?.checked
        }));
    } catch (_) {}
}

function exitPrint() {
    if (window.opener && !window.opener.closed) {
        try { window.opener.postMessage({ type: 'goldapp:salesreturn-print-exit' }, '*'); } catch (_) {}
    }
    window.close();
}

function doPrint() {
    const c = parseInt(document.getElementById('copies').value) || 1;
    for (let i = 0; i < c; i++) {
        setTimeout(() => window.print(), i * 600);
    }
}

function applyBillZoom(value) {
    const billCard = document.getElementById('billCard');
    if (!billCard) return;
    if (value !== '' && !isNaN(value)) {
        billCard.style.zoom = (parseInt(value, 10) / 100).toString();
    } else {
        billCard.style.zoom = '';
    }
}

function syncShopHeaderVisibility() {
    const shopName = document.getElementById('shopName');
    if (shopName) shopName.style.display = '';
}

(function(){
    const stored = loadStoredSalesReturnDefaults();
    const copies = document.getElementById('copies');
    const zoom = document.getElementById('zoom');
    const cbLandscape = document.getElementById('cbLandscape');
    const cbShopInfo = document.getElementById('cbShopInfo');
    const billCard = document.getElementById('billCard');

    if (copies) copies.value = stored.copies ?? salesReturnPrintDefaults.copies;
    if (zoom) zoom.value = stored.zoom || salesReturnPrintDefaults.zoom;
    if (cbLandscape) { cbLandscape.checked = false; if (billCard) billCard.classList.remove('landscape'); }
    if (cbShopInfo) {
        cbShopInfo.checked = true;
        syncShopHeaderVisibility();
    }
    applyBillZoom(zoom ? (zoom.value || salesReturnPrintDefaults.zoom) : (stored.zoom || salesReturnPrintDefaults.zoom));

    if (zoom) zoom.addEventListener('input', e => { applyBillZoom(e.target.value); saveStoredSalesReturnDefaults(); });
    if (cbLandscape) cbLandscape.addEventListener('change', saveStoredSalesReturnDefaults);
    if (cbShopInfo) cbShopInfo.addEventListener('change', () => { syncShopHeaderVisibility(); saveStoredSalesReturnDefaults(); });
    if (copies) copies.addEventListener('input', saveStoredSalesReturnDefaults);
})();
</script>
</body>
</html>
