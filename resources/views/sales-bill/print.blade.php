<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $printDocumentTitle ?? 'Sales Bill' }} - {{ $billno }}</title>
<style>
:root{
    --ink:#0f172a;
    --muted:#475569;
    --line:#cbd5e1;
    --soft:#e2e8f0;
    --accent:#0f4c81;
    --accent-2:#1f6aa5;
    --paper:#fff;
    --panel:#f8fbff;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Segoe UI,Arial,sans-serif;font-size:12px;color:var(--ink);background:#ffffff}

.toolbar{
    background:linear-gradient(135deg,#2f3d8f,#4457cb);
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
.toolbar select{background:rgba(255,255,255,.95);color:#1f2937;border:1px solid rgba(255,255,255,.35);border-radius:6px;padding:4px 8px;font-size:11px;height:28px}

.page-wrap{padding:20px}
.page{
    width:980px;
    margin:0 auto;
    background:#ffffff;
    border:1px solid var(--soft);
    box-shadow:0 10px 30px rgba(15,23,42,.08);
    padding:24px 28px 28px;
}
.page.landscape{width:1180px}

.header{
    display:flex;
    justify-content:space-between;
    gap:20px;
    border-bottom:2px solid var(--accent);
    padding-bottom:14px;
    margin-bottom:18px;
}
.company h1{margin:0 0 6px;font-size:28px;letter-spacing:.4px}
.company-addr{font-size:16px;font-weight:700;color:var(--ink);line-height:1.5;margin-bottom:6px;max-width:480px}
.company-meta{font-size:15px;font-weight:700;color:var(--ink);line-height:1.5;display:flex;flex-wrap:wrap;gap:16px;margin-top:4px}
.company-meta span{white-space:nowrap}
.company .meta,.bill-meta .meta{color:var(--ink);font-size:15px;font-weight:700;line-height:1.65}
.bill-meta .meta div{margin-bottom:2px}
.bill-meta .meta strong{font-weight:800;color:var(--ink)}
.bill-meta{text-align:right;min-width:280px}
.bill-title{font-size:22px;font-weight:700;color:var(--accent);margin:0 0 8px}

.grid{display:grid;grid-template-columns:1.1fr 1fr;gap:18px;margin-bottom:18px}
.card{border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}
.card h3{
    margin:0;
    padding:9px 12px;
    font-size:13px;
    letter-spacing:.4px;
    color:#fff;
    background:linear-gradient(135deg,var(--accent),var(--accent-2));
    text-transform:uppercase;
}
.card .body{padding:11px 12px;font-size:13px;line-height:1.65;background:var(--panel)}
.kv{display:grid;grid-template-columns:130px 1fr;gap:4px 10px}
.kv .k{color:var(--muted);font-weight:600}
.kv .num-val{text-align:right;font-variant-numeric:tabular-nums}
.order-kv{grid-template-columns:130px 140px}
.order-kv .num-val{text-align:left}
.muted{color:var(--muted)}

.section-title{margin:18px 0 8px;font-size:15px;font-weight:700;color:#0f2d4b}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid var(--line);padding:7px 8px;font-size:12px}
thead th{background:#e9f1f8;color:#0f2d4b;text-transform:uppercase;letter-spacing:.25px}
td.num,th.num{text-align:right}
td.center,th.center{text-align:center}
tfoot td{background:#f8fafc;font-weight:700}
.sold-items{table-layout:fixed}
.sold-items th,.sold-items td{padding:6px 5px;font-size:11px;word-break:break-word}
.sold-items .item-name{width:126px}

.totals{
    margin-top:18px;
    display:grid;
    grid-template-columns:1fr 330px;
    gap:18px;
    align-items:start;
}
.stack{display:grid;gap:14px}
.note-box,.foot-box,.bank-box{
    border:1px solid var(--line);
    border-radius:10px;
    padding:12px;
    background:#fff;
}
.note-box{min-height:118px;font-size:13px}
.foot-box{font-size:12px;line-height:1.6;color:var(--muted)}
.bank-box h4,.note-box h4,.foot-box h4{margin:0 0 8px;font-size:13px;color:#0f2d4b}

.summary{border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}
.summary table td{font-size:13px}
.summary td:first-child{color:var(--muted);font-weight:600;width:58%}
.summary tr.total td{background:#eff6ff;font-size:15px;font-weight:700;color:#0f2d4b}

.footer{
    margin-top:28px;
    display:flex;
    justify-content:space-between;
    gap:30px;
    font-size:12px;
    color:var(--muted);
    align-items:flex-end;
}
.sign-row{display:flex;gap:18px}
.sign{
    width:220px;
    text-align:center;
    padding-top:34px;
    border-top:1px solid var(--line);
}

.sales-slip{display:none}
.sales-slip .slip-head{text-align:center;margin-bottom:9px}
.sales-slip .slip-head h1{font-family:"Times New Roman",serif;font-size:17px;line-height:1.1;margin:0;text-transform:uppercase}
.sales-slip .slip-shop-line{font-family:"Times New Roman",serif;font-size:11px;font-weight:700;line-height:1.25;text-transform:uppercase}
.sales-slip .slip-title{font-family:"Times New Roman",serif;font-size:16px;font-weight:800;letter-spacing:.4px;margin-top:10px;text-transform:uppercase}
.sales-slip .slip-meta{display:grid;grid-template-columns:1fr 1fr;gap:3px 22px;margin:10px 0 8px;font-family:"Times New Roman",serif;font-size:12px}
.sales-slip .slip-line{display:grid;grid-template-columns:90px 8px 1fr;min-height:18px;word-break:break-word}
.sales-slip .slip-line b{font-weight:800}
.sales-slip table{font-family:"Times New Roman",serif;font-size:12px;border-collapse:collapse;width:100%}
.sales-slip th,.sales-slip td{border:1px solid #333;padding:5px 4px;vertical-align:top}
.sales-slip th{font-weight:800;text-align:left;background:#fff}
.sales-slip .num{text-align:right}
.sales-slip .center{text-align:center}
.sales-slip .slip-total td{font-weight:800}
.sales-slip .slip-remarks{display:grid;grid-template-columns:90px 1fr;margin-top:14px;font-family:"Times New Roman",serif;font-size:13px;font-weight:800}
.sales-slip .slip-summary{width:245px;margin:16px 0 0 auto;font-family:"Times New Roman",serif;font-size:12px}
.sales-slip .slip-summary .slip-line{grid-template-columns:125px 8px 1fr}
.sales-slip .slip-sign{display:grid;grid-template-columns:repeat(4,1fr);align-items:end;margin-top:56px;font-family:"Times New Roman",serif;font-size:13px;font-weight:800;text-align:center}

@media print{
    @page{size:A4 portrait;margin:10mm}
    .toolbar{display:none!important}
    body{background:#fff;font-size:11px}
    .page-wrap{padding:0}
    .page{
        display:block!important;
        width:auto;
        max-width:100%;
        margin:0;
        border:none;
        box-shadow:none;
        padding:0;
    }
    .page.landscape{width:auto}
    .sales-slip{display:none!important}
    .header{margin-bottom:10px;padding-bottom:10px;break-inside:avoid;page-break-inside:avoid}
    .grid{gap:12px;margin-bottom:10px}
    .card h3{padding:6px 10px}
    .card .body{padding:8px 10px;line-height:1.45}
    .section-title{margin:10px 0 6px}
    table{break-inside:auto;page-break-inside:auto}
    thead{display:table-header-group}
    tfoot{display:table-row-group}
    tr{break-inside:avoid;page-break-inside:avoid}
    .card,.summary,.summary tr,.note-box,.foot-box,.bank-box,.footer,.sign{
        break-inside:avoid;
        page-break-inside:avoid;
    }
    th,td{padding:4px 5px;font-size:10.5px}
    .sold-items th,.sold-items td{padding:3px 4px;font-size:10px}
    .totals{
        margin-top:8px;
        gap:10px;
        grid-template-columns:minmax(0,1fr) 300px;
        break-inside:auto;
        page-break-inside:auto;
    }
    .summary,.summary tr{
        break-inside:avoid;
        page-break-inside:avoid;
    }
    .summary table td{padding:4px 6px;font-size:11px}
    .summary tr.total td{font-size:13px}
    .note-box{min-height:74px}
    .footer{margin-top:12px}
}
</style>
@include('partials.print-layout-head')
</head>
<body>
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
        <input type="checkbox" id="cbNoBillForm" {{ !$billFormEst ? 'checked' : '' }}
               onchange="document.getElementById('billFormRow').style.display=this.checked?'none':''">
        <label for="cbNoBillForm">No Bill form</label>
    </div>

    <div class="tb-chk">
        <input type="checkbox" id="cbShopInfo" {{ $showShopInfo ? 'checked' : '' }}
               onchange="syncShopHeaderVisibility()">
        <label for="cbShopInfo">Show Shop Info</label>
    </div>

    @if($showPrintVAInWgt)
    <div class="tb-chk">
        <input type="checkbox" id="cbVAWgt" {{ $printVAInWgt ? 'checked' : '' }}>
        <label for="cbVAWgt">VA in Wgt</label>
    </div>
    @endif

    <div class="tb-chk">
        <input type="checkbox" id="cbVAPerc" {{ $printVAPerc ? 'checked' : '' }}>
        <label for="cbVAPerc">Print VA%</label>
    </div>

    <div class="tb-chk">
        <input type="checkbox" id="cbVAPerGm" {{ $printVAPerGm ? 'checked' : '' }}>
        <label for="cbVAPerGm">Print VA/gm</label>
    </div>

    <div class="tb-sep"></div>

    <button class="tb-btn primary" onclick="doPrint()">Print</button>
    <button class="tb-btn" onclick="window.print()">PDF / Save As</button>
    <button class="tb-btn" onclick="exitPrint()">Exit</button>
</div>

<div class="page-wrap" data-print-root="sales-bill">
<div class="page{{ $landscape ? ' landscape' : '' }}" id="billCard">
    <div class="header" id="shopHdr" data-print-app-header="sales-shop">
        <div class="company">
            <h1 id="shopName" style="{{ $showShopInfo ? '' : 'display:none' }}">{{ $companyName ?: 'Company' }}</h1>
            @if(trim($companyAddr) !== '' || trim($companyAddr2) !== '')
            <div class="company-addr">
                {!! nl2br(e(trim($companyAddr))) !!}@if(trim($companyAddr2) !== '')<br>{!! nl2br(e(trim($companyAddr2))) !!}@endif
            </div>
            @endif
            @if(trim($companyPhone) !== '' || trim($companyGSTIN) !== '' || trim($companyState) !== '')
            <div class="company-meta">
                @if(trim($companyPhone) !== '')<span>Ph: {{ trim($companyPhone) }}</span>@endif
                @if(trim($companyGSTIN) !== '')<span>GSTIN: {{ trim($companyGSTIN) }}</span>@endif
                @if(trim($companyState) !== '')<span>State: {{ trim($companyState) }}</span>@endif
            </div>
            @endif
        </div>
        <div class="bill-meta">
            <div class="bill-title">{{ $printDocumentTitle ?? ($control == 1 ? 'Sales Invoice' : 'Estimate Invoice') }}</div>
            <div class="meta">
                <div><strong>Bill No:</strong> {{ $billno }}</div>
                <div><strong>Date:</strong> {{ $tdate ? date('d/m/Y', strtotime($tdate)) : '-' }}</div>
                <div><strong>Status:</strong> {{ (int) $control === 1 ? 'Confirmed' : 'Estimate' }}</div>
                @if($counter)<div><strong>Counter:</strong> {{ $counter }}</div>@endif
                @if($smcode || $smanName)<div><strong>Salesman:</strong> {{ $smanName ?: $smcode }}</div>@endif
            </div>
        </div>
    </div>

    <div id="billFormRow" class="section-title" style="{{ $billFormEst ? '' : 'display:none' }}">{{ $printFormTitle ?? 'GST Invoice' }}</div>

    <div class="grid">
        <div class="card">
            <h3>Customer Details</h3>
            <div class="body">
                <div><strong>{{ $custname ?: '-' }}</strong>@if($printPartyCode && $custcode) <span class="muted">({{ $custcode }})</span>@endif</div>
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
                    <div class="k">PAN / Aadhaar</div><div>{{ $pan ?: '-' }}</div>
                    <div class="k">State</div><div>{{ $custState ?: '-' }}</div>
                    @if($placeos)<div class="k">Place of Supply</div><div>{{ $placeos }}</div>@endif
                    @if($showDueDate)<div class="k">Due Date</div><div>{{ date('d/m/Y', strtotime($duedate)) }}</div>@endif
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Bill Details</h3>
            <div class="body">
                <div class="kv">
                    <div class="k">{{ $printRate8gm ? 'Gold Rate / 8gm' : 'Gold Rate / gm' }}</div><div>{{ number_format($grateDisplay, 2, '.', '') }}</div>
                    <div class="k">18K Rate</div><div>{{ number_format($g18rate, 2, '.', '') }}</div>
                    <div class="k">Silver Rate</div><div>{{ number_format($srate, 2, '.', '') }}</div>
                    @if($astamt > 0)<div class="k">Cess</div><div>{{ number_format($astamt, 2, '.', '') }}</div>@endif
                    @php
                        $displayClosingBalance = $clBalance;
                        $closingBalanceLabel = $displayClosingBalance < 0 ? 'Closing Balance (To Receive)' : 'Closing Balance (Advance)';
                        $closingBalanceAmount = abs($displayClosingBalance);
                    @endphp
                    @if($closingBalanceAmount > 0.004 && $advance == 0 && empty($orderno))<div class="k">{{ $closingBalanceLabel }}</div><div>{{ number_format($closingBalanceAmount, 2, '.', '') }}</div>@endif
                </div>
            </div>
        </div>

        @if(!empty($orderPrintDetails))
        <div class="card">
            <h3>Order Details</h3>
            <div class="body">
                <div class="kv order-kv">
                    <div class="k">Order No</div><div>{{ $orderPrintDetails['order_no'] ?: '-' }}</div>
                    <div class="k">Order Date</div><div>{{ !empty($orderPrintDetails['date']) ? date('d/m/Y', strtotime($orderPrintDetails['date'])) : '-' }}</div>
                    @if(($orderPrintDetails['cash_advance'] ?? 0) > 0)
                    <div class="k">Cash Advance</div><div>{{ number_format($orderPrintDetails['cash_advance'], 2, '.', '') }}</div>
                    @endif
                    @if(($orderPrintDetails['advance_after'] ?? 0) > 0)
                    <div class="k">Advance After</div><div>{{ number_format($orderPrintDetails['advance_after'], 2, '.', '') }}</div>
                    @endif
                    @if(($orderPrintDetails['total_advance'] ?? 0) > 0)
                    <div class="k">Total Advance</div><div>{{ number_format($orderPrintDetails['total_advance'], 2, '.', '') }}</div>
                    @endif
                    @if(($orderPrintDetails['gold_advance'] ?? 0) > 0)
                    <div class="k">Gold Advance</div><div>{{ number_format($orderPrintDetails['gold_advance'], 3, '.', '') }}</div>
                    @endif
                    @if(($orderPrintDetails['exchange_amount'] ?? 0) > 0)
                    <div class="k">Exchange</div><div>{{ number_format($orderPrintDetails['exchange_amount'], 2, '.', '') }}</div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="section-title">{{ $printItemsTitle ?? 'Sold Items' }}</div>
    <table class="sold-items">
        <thead>
            <tr>
                <th class="center" style="width:36px">#</th>
                <th class="center" style="width:58px">Purity</th>
                <th class="item-name">Item Name</th>
                <th class="center" style="width:58px">HUID</th>
                <th class="center" style="width:60px">HSN</th>
                <th class="num" style="width:44px">Qty</th>
                <th class="num" style="width:72px">Gross Wgt</th>
                <th class="num" style="width:72px">Stone Wgt</th>
                <th class="num" style="width:72px">Net Wgt</th>
                <th class="num" style="width:72px">{{ $printRateStyle ? 'Rate' : 'Metal Amt' }}</th>
                <th class="num" style="width:72px">Stone Amt</th>
                @if($printVA)
                <th class="num" style="width:58px">{{ $printVAPerc ? 'VA %' : ($printVAPerGm ? 'VA/gm' : 'VA') }}</th>
                @endif
                <th class="num" style="width:78px">Amount</th>
            </tr>
        </thead>
        <tbody>
        @php $sno = 0; @endphp
        @foreach($rows as $r)
            @php
                $w  = (float)($r['salesd_weight'] ?? 0);
                $q  = (int)($r['salesd_qty'] ?? 0);
                $vis = $w > 0 || $q > 0;
                if ($vis) $sno++;
                $netwgt    = (float)($r['netwgt'] ?? 0);
                $dmdwgt_r  = (float)($r['salesd_dmdwgt'] ?? 0);
                $stonewgt  = (float)($r['salesd_stonewgt'] ?? 0);
                $rate      = (float)($r['salesd_rate'] ?? 0);
                $bcode     = (int)($r['salesd_bcode'] ?? 0);
                if ($dmdwgt_r > 0) {
                    $iname = trim($r['itemname'] ?? '') . '-' . ($r['salesd_code'] ?? $r['code'] ?? '');
                    if (!empty($r['salesd_model'])) $iname .= '-' . $r['salesd_model'];
                } else {
                    $iname = !empty($r['salesd_name']) ? $r['salesd_name'] : ($r['itemname'] ?? '');
                    if (!empty($r['salesd_model'])) $iname .= ' -' . $r['salesd_model'];
                }
                if ($printBCode && $bcode > 0) $iname .= ' -' . $bcode;
                $stWgtDisp = $dmdwgt_r > 0 ? number_format($dmdwgt_r, 3) . 'Ct' : number_format($stonewgt, 3);
                $rateDisp  = $printRateStyle ? $rate : ($netwgt * $rate);
                $vaDisp = '';
                if ($printVA && $vis) {
                    if ($printOrgMCPerc) {
                        $vaDisp = number_format($r['salesd_mcharge'] ?? 0, 2);
                    } elseif ($printBCVA && $bcode > 0 && ($r['bcvap'] ?? 0) > 0) {
                        $vaDisp = number_format($r['bcvap'], 2) . '%';
                    } elseif ($w <= $printVAAmtBelow) {
                        $vaDisp = number_format($r['salesd_mcharge'] ?? 0, 0);
                    } elseif ($printVAPerc && ($r['items_printvaamt'] ?? '') !== 'Y') {
                        $storedPerc = (float)($r['salesd_vaperc'] ?? 0);
                        if ($storedPerc > 0) {
                            $vaDisp = number_format($storedPerc, 2) . '%';
                        } else {
                            $denom = $netwgt * $rate;
                            $vaDisp = $denom > 0 ? number_format(($r['va'] ?? 0) * 100 / $denom, 2) . '%' : '0%';
                        }
                    } elseif ($printVAPerGm && $netwgt > 0) {
                        $vaDisp = number_format(($r['va'] ?? 0) / $netwgt, 2) . $printVAPerGmTale;
                    } else {
                        $vaDisp = number_format($r['va'] ?? 0, 0);
                    }
                }
            @endphp
            <tr style="{{ !$vis ? 'display:none' : '' }}">
                <td class="center">{{ $vis ? $sno : '' }}</td>
                <td class="center">{{ $r['salesd_iqtype'] ?? '' }}</td>
                <td>{{ $iname }}</td>
                <td class="center">{{ $r['salesd_huid'] ?? '' }}</td>
                <td class="center">{{ $r['vatcode'] ?? '' }}</td>
                <td class="num">{{ $vis ? number_format($q, 0, '.', '') : '' }}</td>
                <td class="num">{{ $vis ? number_format($w, 3, '.', '') : '' }}</td>
                <td class="num">{{ $vis ? $stWgtDisp : '' }}</td>
                <td class="num">{{ $vis ? number_format($netwgt, 3, '.', '') : '' }}</td>
                <td class="num">{{ $vis ? number_format($rateDisp, 2, '.', '') : '' }}</td>
                <td class="num">{{ $vis ? number_format($r['stprice'] ?? 0, 2, '.', '') : '' }}</td>
                @if($printVA)
                <td class="num">{{ $vaDisp }}</td>
                @endif
                <td class="num">{{ $vis ? number_format($r['itemamt'] ?? 0, 2, '.', '') : '' }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">
                    Total
                    @if($showBCVADiff && $tbcva > 1)
                    <span class="muted" style="margin-left:8px;font-weight:400">VA Diff: {{ number_format($tbcva - $totVA, 2, '.', '') }}</span>
                    @endif
                </td>
                <td class="num">{{ number_format($totQty, 0, '.', '') }}</td>
                <td class="num">{{ number_format($totGrossWgt, 3, '.', '') }}</td>
                <td class="num">{{ number_format($totStWgt, 3, '.', '') }}</td>
                <td class="num">{{ number_format($totNetWgt, 3, '.', '') }}</td>
                <td class="num">{{ !$printRateStyle ? number_format($totAmt, 2, '.', '') : '' }}</td>
                <td class="num">{{ number_format($totStPrice, 2, '.', '') }}</td>
                @if($printVA)
                <td class="num">{{ $totVADisplay }}</td>
                @endif
                <td class="num">{{ number_format($totItemAmt, 2, '.', '') }}</td>
            </tr>
        </tfoot>
    </table>

    @if(count($exchangeRows) > 0)
    <div class="section-title" style="margin-top:14px">{{ !empty($orderno) ? 'Exchange Order Items' : 'Exchange Items' }}</div>
    <table>
        <thead>
            <tr>
                <th class="center" style="width:42px">#</th>
                <th>Item Name</th>
                <th class="num" style="width:56px">Qty</th>
                <th class="num" style="width:96px">Weight (g)</th>
                <th class="num" style="width:96px">Stone Weight</th>
                <th class="num" style="width:88px">Mud Less</th>
                <th class="num" style="width:96px">Net Weight</th>
                <th class="num" style="width:96px">Rate</th>
                <th class="num" style="width:110px">Amount</th>
            </tr>
        </thead>
        <tbody>
        @php $exTotWgt = 0; $exTotStoneWgt = 0; $exTotMud = 0; $exTotNetWgt = 0; $exTotAmt = 0; @endphp
        @foreach($exchangeRows as $i => $r)
            @php
                $exNetWgt = max((float)($r['weight'] ?? 0) - (float)($r['stwgt'] ?? 0) - (float)($r['mud'] ?? 0), 0);
                $exTotWgt += (float)($r['weight'] ?? 0);
                $exTotStoneWgt += (float)($r['stwgt'] ?? 0);
                $exTotMud += (float)($r['mud'] ?? 0);
                $exTotNetWgt += $exNetWgt;
                $exTotAmt += (float)($r['amount'] ?? 0);
            @endphp
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>{{ $r['name'] ?? '' }}</td>
                <td class="num">{{ (int)($r['qty'] ?? 0) ?: '' }}</td>
                <td class="num">{{ number_format((float)($r['weight'] ?? 0), 3, '.', '') }}</td>
                <td class="num">{{ number_format((float)($r['stwgt'] ?? 0), 3, '.', '') }}</td>
                <td class="num">{{ number_format((float)($r['mud'] ?? 0), 3, '.', '') }}</td>
                <td class="num">{{ number_format($exNetWgt, 3, '.', '') }}</td>
                <td class="num">{{ number_format((float)($r['rate'] ?? 0), 2, '.', '') }}</td>
                <td class="num">{{ number_format((float)($r['amount'] ?? 0), 2, '.', '') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Total</td>
                <td class="num">{{ number_format($exTotWgt, 3, '.', '') }}</td>
                <td class="num">{{ number_format($exTotStoneWgt, 3, '.', '') }}</td>
                <td class="num">{{ number_format($exTotMud, 3, '.', '') }}</td>
                <td class="num">{{ number_format($exTotNetWgt, 3, '.', '') }}</td>
                <td></td>
                <td class="num">{{ number_format($exTotAmt, 2, '.', '') }}</td>
            </tr>
        </tfoot>
    </table>
    @endif

    @if(count($sretRows) > 0)
    <div class="section-title" style="margin-top:14px">Sales Return Items</div>
    <table>
        <thead>
            <tr>
                <th class="center" style="width:42px">#</th>
                <th>Item Name</th>
                <th class="num" style="width:56px">Qty</th>
                <th class="num" style="width:96px">Weight (g)</th>
                <th class="num" style="width:86px">Rate/gm</th>
                <th class="num" style="width:70px">VA %</th>
                <th class="num" style="width:110px">Amount</th>
            </tr>
        </thead>
        <tbody>
        @php $srTotWgt = 0; $srTotAmt = 0; @endphp
        @foreach($sretRows as $i => $r)
            @php
                $srWeight = (float)($r['weight'] ?? 0);
                $srStoneWgt = (float)($r['stonewgt'] ?? 0);
                $srNetWgt = max($srWeight - $srStoneWgt, 0);
                $srRate = (float)($r['rate'] ?? 0);
                $srMc = (float)($r['mcharge'] ?? 0);
                $srVaPerc = (float)($r['vaperc'] ?? 0);
                if ($srVaPerc <= 0 && $srNetWgt > 0 && $srRate > 0 && $srMc > 0) {
                    $srVaPerc = ($srMc * 100) / ($srNetWgt * $srRate);
                }
                $srTotWgt += $srWeight;
                $srTotAmt += (float)($r['amount'] ?? 0);
            @endphp
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>{{ $r['name'] ?? '' }}</td>
                <td class="num">{{ (int)($r['qty'] ?? 0) ?: '' }}</td>
                <td class="num">{{ number_format($srWeight, 3, '.', '') }}</td>
                <td class="num">{{ number_format($srRate, 2, '.', '') }}</td>
                <td class="num">{{ number_format($srVaPerc, 2, '.', '') }}</td>
                <td class="num">{{ number_format((float)($r['amount'] ?? 0), 2, '.', '') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Total</td>
                <td class="num">{{ number_format($srTotWgt, 3, '.', '') }}</td>
                <td></td>
                <td></td>
                <td class="num">{{ number_format($srTotAmt, 2, '.', '') }}</td>
            </tr>
        </tfoot>
    </table>
    @endif

    <div class="totals">
        <div class="stack">
            @if(trim((string) $note) !== '')
            <div class="note-box">
                <h4>Note</h4>
                <div>{{ $note }}</div>
            </div>
            @endif

            @if($printFooter && ($billFooter1 || $billFooter2))
            <div class="foot-box">
                <h4>Footer Notes</h4>
                @if($billFooter1)<div>{{ $billFooter1 }}</div>@endif
                @if($billFooter2)<div>{{ $billFooter2 }}</div>@endif
            </div>
            @endif

            @if($showBankDetails)
            <div class="bank-box">
                <h4>Bank Details</h4>
                <div class="kv">
                    @if($companyBankName)<div class="k">Bank</div><div>{{ $companyBankName }}</div>@endif
                    @if($companyBankAccountName)<div class="k">A/C Name</div><div>{{ $companyBankAccountName }}</div>@endif
                    @if($companyBankAccountNo)<div class="k">A/C No</div><div>{{ $companyBankAccountNo }}</div>@endif
                    @if($companyBankBranch)<div class="k">Branch</div><div>{{ $companyBankBranch }}</div>@endif
                    @if($companyBankIFSC)<div class="k">IFSC</div><div>{{ $companyBankIFSC }}</div>@endif
                </div>
            </div>
            @endif
        </div>

        <div class="summary">
            <table>
                <tbody>
                    <tr><td>{{ $gstLabel }}</td><td class="num">{{ number_format($cgst, 2, '.', '') }}</td></tr>
                    @if($cst !== 'Y' && $sgst > 0)
                    <tr><td>{{ $sgstLabel }}</td><td class="num">{{ number_format($sgst, 2, '.', '') }}</td></tr>
                    @endif
                    @if($astamt > 0)<tr><td>Cess</td><td class="num">{{ number_format($astamt, 2, '.', '') }}</td></tr>@endif
                    @if(($discount > 0 && $printDiscount) || $printDiscAlways)<tr><td>Discount{{ $discperc > 0 ? ' (' . number_format($discperc, 2, '.', '') . '%)' : '' }}</td><td class="num">{{ number_format($discount, 2, '.', '') }}</td></tr>@endif
                    @if($eamt > 0)<tr><td>Exchange Amount</td><td class="num">{{ number_format($eamt, 2, '.', '') }}</td></tr>@endif
                    @if($sretamt > 0)<tr><td>Sales Return Amount</td><td class="num">{{ number_format($sretamt, 2, '.', '') }}</td></tr>@endif
                    @if($advance > 0)<tr><td>Advance Amount</td><td class="num">{{ number_format($advance, 2, '.', '') }}</td></tr>@endif
                    @if($schmamt > 0)<tr><td>Scheme Amount</td><td class="num">{{ number_format($schmamt, 2, '.', '') }}</td></tr>@endif
                    @if($tcsamt > 0)<tr><td>TCS ({{ number_format($tcsperc, 1, '.', '') }}%)</td><td class="num">{{ number_format($tcsamt, 2, '.', '') }}</td></tr>@endif
                    <tr><td>Sales Total</td><td class="num">{{ number_format($salesTotal, 2, '.', '') }}</td></tr>
                    <tr class="total"><td>Grand Total</td><td class="num">{{ number_format($grandTotal, 2, '.', '') }}</td></tr>
                    <tr><td>Received Amount</td><td class="num">{{ number_format($ramt, 2, '.', '') }}</td></tr>
                    @if($cashReceived > 0)<tr><td>CASH</td><td class="num">{{ number_format($cashReceived, 2, '.', '') }}</td></tr>@endif
                    @if($bankReceived > 0)<tr><td>BANK</td><td class="num">{{ number_format($bankReceived, 2, '.', '') }}</td></tr>@endif
                    <tr><td>Balance</td><td class="num">{{ number_format($balance, 2, '.', '') }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="footer">
        <div>Printed on {{ now()->format('d/m/Y h:i A') }}</div>
        <div class="sign-row">
            <div class="sign">Customer Signature</div>
            <div class="sign">Authorised Signature</div>
        </div>
    </div>
</div>

<div class="sales-slip" id="salesSlipCard">
    @php
        $slipAddressParts = array_values(array_filter([
            trim((string) $addr),
            trim((string) $ad2),
            trim((string) $ad3),
            trim((string) $ad4),
        ], fn($v) => $v !== ''));
        $slipAddress = implode(', ', array_unique($slipAddressParts));
        $slipPhone = trim((string) $phoneLine);
        $slipSalesman = trim((string) ($smanName ?: $smcode));
        $slipTitle = $printSlipTitle ?? ((int) $control === 1 ? 'Sales Bill' : 'Estimate Bill');
        $slipShown = 0;
        $slipDiaTotal = 0.0;
    @endphp
    <div class="slip-head">
        <h1>{{ $companyName ?: 'Company' }}</h1>
        @if(trim($companyAddr) !== '')<div class="slip-shop-line">{!! nl2br(e(trim($companyAddr))) !!}</div>@endif
        @if(trim($companyAddr2) !== '')<div class="slip-shop-line">{!! nl2br(e(trim($companyAddr2))) !!}</div>@endif
        @if(trim($companyPhone) !== '')<div class="slip-shop-line">Ph: {{ trim($companyPhone) }}</div>@endif
        <div class="slip-title">{{ $slipTitle }}</div>
    </div>

    <div class="slip-meta">
        <div>
            <div class="slip-line"><b>Bill No</b><span>:</span><span>{{ $billno }}</span></div>
            <div class="slip-line"><b>Date</b><span>:</span><span>{{ $tdate ? date('d-m-Y', strtotime($tdate)) : '' }}</span></div>
            @if($showDueDate)<div class="slip-line"><b>DueDate</b><span>:</span><span>{{ date('d-m-Y', strtotime($duedate)) }}</span></div>@endif
            <div class="slip-line"><b>SalesmanName</b><span>:</span><span>{{ $slipSalesman }}</span></div>
        </div>
        <div>
            <div class="slip-line"><b>CustomerName</b><span>:</span><span>{{ $custname }}</span></div>
            <div class="slip-line"><b>Address</b><span>:</span><span>{{ $slipAddress }}</span></div>
            <div class="slip-line"><b>PhoneNo</b><span>:</span><span>{{ $printCustMob ? $slipPhone : '' }}</span></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:28px">SlNo</th>
                <th>ItemName</th>
                <th class="num">Nos</th>
                <th class="num">Grwt</th>
                <th class="num">Stwt</th>
                <th class="num">Diawt</th>
                <th class="num">Netwt</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
                @php
                    $w = (float)($r['salesd_weight'] ?? 0);
                    $q = (int)($r['salesd_qty'] ?? 0);
                    $vis = $w > 0 || $q > 0;
                    if (!$vis) continue;
                    $slipShown++;
                    $netwgt = (float)($r['netwgt'] ?? 0);
                    $dmdwgt = (float)($r['salesd_dmdwgt'] ?? 0);
                    $stonewgt = (float)($r['salesd_stonewgt'] ?? 0);
                    $slipDiaTotal += $dmdwgt;
                    $iname = trim((string) (!empty($r['salesd_name']) ? $r['salesd_name'] : ($r['itemname'] ?? '')));
                    if ($iname === '') $iname = trim((string) ($r['code'] ?? ''));
                @endphp
                <tr>
                    <td class="num">{{ $slipShown }}</td>
                    <td>{{ $iname }}</td>
                    <td class="num">{{ number_format($q, 0, '.', '') }}</td>
                    <td class="num">{{ number_format($w, 3, '.', '') }}</td>
                    <td class="num">{{ number_format($stonewgt, 3, '.', '') }}</td>
                    <td class="num">{{ number_format($dmdwgt, 2, '.', '') }}</td>
                    <td class="num">{{ number_format($netwgt, 3, '.', '') }}</td>
                    <td class="num">{{ number_format((float)($r['itemamt'] ?? 0), 2, '.', '') }}</td>
                </tr>
            @endforeach
            <tr class="slip-total">
                <td></td>
                <td>Total</td>
                <td class="num">{{ number_format($totQty, 0, '.', '') }}</td>
                <td class="num">{{ number_format($totGrossWgt, 3, '.', '') }}</td>
                <td class="num">{{ number_format($totStWgt, 3, '.', '') }}</td>
                <td class="num">{{ number_format($slipDiaTotal, 2, '.', '') }}</td>
                <td class="num">{{ number_format($totNetWgt, 3, '.', '') }}</td>
                <td class="num">{{ number_format($totItemAmt, 2, '.', '') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="slip-summary">
        @if($staxamt > 0)<div class="slip-line"><b>GST</b><span>:</span><span class="num">{{ number_format($staxamt, 2, '.', '') }}</span></div>@endif
        @if(($discount > 0 && $printDiscount) || $printDiscAlways)<div class="slip-line"><b>Discount</b><span>:</span><span class="num">{{ number_format($discount, 2, '.', '') }}</span></div>@endif
        <div class="slip-line"><b>Grand Total</b><span>:</span><span class="num">{{ number_format($grandTotal, 2, '.', '') }}</span></div>
        <div class="slip-line"><b>Received</b><span>:</span><span class="num">{{ number_format($ramt, 2, '.', '') }}</span></div>
        <div class="slip-line"><b>Balance</b><span>:</span><span class="num">{{ number_format($balance, 2, '.', '') }}</span></div>
    </div>

    <div class="slip-remarks"><div>Remarks :-</div><div>{{ trim((string) $note) }}</div></div>

    <div class="slip-sign">
        <div>Prepared by</div>
        <div>Checked by</div>
        <div>Authorized</div>
        <div>Received by</div>
    </div>
</div>
</div>

<script>
const salesBillPrintStorageKey = `goldapp-sales-bill-print:${window.location.pathname}`;
const salesBillPrintDefaults = {
    copies: {{ (int) $copies }},
    zoom: @json((string) ($zoom !== '' ? $zoom : '90')),
    landscape: false,
    noBillForm: {{ !$billFormEst ? 'true' : 'false' }},
    showShopInfo: {{ $showShopInfo ? 'true' : 'false' }},
    printVAInWgt: {{ $printVAInWgt ? 'true' : 'false' }},
    printVAPerc: {{ $printVAPerc ? 'true' : 'false' }},
    printVAPerGm: {{ $printVAPerGm ? 'true' : 'false' }}
};

function loadStoredSalesBillDefaults() {
    try {
        const raw = window.localStorage.getItem(salesBillPrintStorageKey);
        if (!raw) return {};
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
        return {};
    }
}

function saveStoredSalesBillDefaults() {
    try {
        window.localStorage.setItem(salesBillPrintStorageKey, JSON.stringify({
            copies: document.getElementById('copies')?.value || salesBillPrintDefaults.copies,
            zoom: document.getElementById('zoom')?.value || salesBillPrintDefaults.zoom,
            landscape: !!document.getElementById('cbLandscape')?.checked,
            noBillForm: !!document.getElementById('cbNoBillForm')?.checked,
            showShopInfo: !!document.getElementById('cbShopInfo')?.checked,
            printVAInWgt: !!document.getElementById('cbVAWgt')?.checked,
            printVAPerc: !!document.getElementById('cbVAPerc')?.checked,
            printVAPerGm: !!document.getElementById('cbVAPerGm')?.checked
        }));
    } catch (_) {}
}

function exitPrint() {
    if (window.opener && !window.opener.closed) {
        try {
            if (typeof window.opener.__goldappResetSalesBillAfterPrint === 'function') {
                window.opener.__goldappResetSalesBillAfterPrint();
            }
            window.opener.postMessage({ type: 'goldapp:salesbill-print-exit' }, '*');
        } catch (_) {}
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
    const cbShopInfo = document.getElementById('cbShopInfo');
    const shopHdr = document.getElementById('shopHdr');
    const shopName = document.getElementById('shopName');
    if (!cbShopInfo || !shopHdr) return;
    const layoutMode = window.GoldAppPrintLayout?.getState?.().letterheadMode || document.body.dataset.goldappLetterheadMode || '';
    const canShowHeader = String(layoutMode).toUpperCase() !== 'PREPRINTED';
    // Header always visible (so address/phone/GSTIN stay) — checkbox controls only the shop name h1
    shopHdr.style.display = canShowHeader ? 'flex' : 'none';
    if (shopName) shopName.style.display = cbShopInfo.checked ? '' : 'none';
}

function reloadSalesBillWithFlags() {
    const url = new URL(window.location.href);
    const cbVAPerc = document.getElementById('cbVAPerc');
    const cbVAPerGm = document.getElementById('cbVAPerGm');
    if (cbVAPerc) {
        url.searchParams.set('vaperc', cbVAPerc.checked ? '1' : '0');
    }
    if (cbVAPerGm) {
        url.searchParams.set('vapergm', cbVAPerGm.checked ? '1' : '0');
    }
    saveStoredSalesBillDefaults();
    window.location.replace(url.toString());
}

function applySalesBillDefaults() {
    const stored = loadStoredSalesBillDefaults();
    const copies = document.getElementById('copies');
    const zoom = document.getElementById('zoom');
    const cbLandscape = document.getElementById('cbLandscape');
    const cbNoBillForm = document.getElementById('cbNoBillForm');
    const cbShopInfo = document.getElementById('cbShopInfo');
    const cbVAWgt = document.getElementById('cbVAWgt');
    const cbVAPerc = document.getElementById('cbVAPerc');
    const cbVAPerGm = document.getElementById('cbVAPerGm');
    const billCard = document.getElementById('billCard');
    const billFormRow = document.getElementById('billFormRow');
    const shopHdr = document.getElementById('shopHdr');

    if (copies) copies.value = stored.copies ?? salesBillPrintDefaults.copies;
    if (zoom) zoom.value = stored.zoom || salesBillPrintDefaults.zoom;
    if (cbLandscape) {
        cbLandscape.checked = false;
        if (billCard) billCard.classList.remove('landscape');
    }
    if (cbNoBillForm && billFormRow) {
        cbNoBillForm.checked = typeof stored.noBillForm === 'boolean' ? stored.noBillForm : salesBillPrintDefaults.noBillForm;
        billFormRow.style.display = cbNoBillForm.checked ? 'none' : '';
    }
    if (cbShopInfo && shopHdr) {
        cbShopInfo.checked = typeof stored.showShopInfo === 'boolean' ? stored.showShopInfo : salesBillPrintDefaults.showShopInfo;
        syncShopHeaderVisibility();
    }
    if (cbVAWgt) cbVAWgt.checked = typeof stored.printVAInWgt === 'boolean' ? stored.printVAInWgt : salesBillPrintDefaults.printVAInWgt;
    if (cbVAPerc) cbVAPerc.checked = typeof stored.printVAPerc === 'boolean' ? stored.printVAPerc : salesBillPrintDefaults.printVAPerc;
    if (cbVAPerGm) cbVAPerGm.checked = typeof stored.printVAPerGm === 'boolean' ? stored.printVAPerGm : salesBillPrintDefaults.printVAPerGm;

    applyBillZoom(zoom ? (zoom.value || salesBillPrintDefaults.zoom) : (stored.zoom || salesBillPrintDefaults.zoom));

    if (cbVAPerc) {
        cbVAPerc.addEventListener('change', function () {
            if (this.checked && cbVAPerGm) cbVAPerGm.checked = false;
            reloadSalesBillWithFlags();
        });
    }
    if (cbVAPerGm) {
        cbVAPerGm.addEventListener('change', function () {
            if (this.checked && cbVAPerc) cbVAPerc.checked = false;
            reloadSalesBillWithFlags();
        });
    }
}

document.addEventListener('goldapp:print-layout-save-default', function () {
    syncShopHeaderVisibility();
    saveStoredSalesBillDefaults();
});

document.addEventListener('goldapp:print-layout-change', function () {
    syncShopHeaderVisibility();
});

(function(){
    applySalesBillDefaults();
})();
</script>
</body>
</html>
