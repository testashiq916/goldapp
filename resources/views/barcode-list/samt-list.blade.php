<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barcode SAmt List</title>
<style>
:root{--hdr:#0c4a6e;--hdr2:#0369a1;--border:#d0dbe8;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#0284c7}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#e0f2fe 0%,#f0f9ff 40%,#e8f4fc 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(12,74,110,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:10px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}
.toolbar{background:#f0f9ff;border-bottom:1px solid var(--border);padding:8px 12px}
.tb-row{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:4px}
.tb-row:last-child{margin-bottom:0}
.tb-lbl{font-size:11px;font-weight:700;color:#0c4a6e;white-space:nowrap}
.tb-input,.tb-select{height:26px;border:1px solid #bae6fd;border-radius:5px;padding:0 6px;font-size:12px;background:#fff;color:var(--text)}
.tb-input:focus,.tb-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(2,132,199,.15)}
.tb-input[type="text"]{text-transform:uppercase}
.tb-sep{width:1px;height:22px;background:#bae6fd;margin:0 2px}
.tb-chk{display:flex;align-items:center;gap:3px;font-size:11px;color:#0c4a6e;cursor:pointer}
.tb-chk input{margin:0}
.btn{height:26px;border-radius:5px;border:1px solid #b0c4de;padding:0 10px;font-size:11px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;background:#f0f4f8;color:#1e3a5f;white-space:nowrap}
.btn:hover{background:#e0eaf4}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff;border-color:var(--hdr)}
.btn-primary:hover{opacity:.9}
.btn-green{background:#e6f8ec;color:#1b5b31;border-color:#2a7a42}
.btn-blue{background:#dbeafe;color:#1e40af;border-color:#3b82f6}
.btn-amber{background:#fef3c7;color:#92400e;border-color:#f59e0b}
.content{padding:10px 12px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:6px;display:flex;justify-content:space-between}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #bae6fd;border-radius:10px;margin:10px 0}
.tbl-wrap{max-height:58vh;overflow:auto;border:1px solid var(--border);border-radius:6px}
table{width:100%;border-collapse:collapse;font-size:11px}
th{position:sticky;top:0;z-index:2;padding:4px 5px;text-align:left;font-size:10px;font-weight:700;white-space:nowrap}
th.h1{background:linear-gradient(180deg,#0c4a6e,#0a3d5c);color:#bae6fd}
th.h2{background:linear-gradient(180deg,#1e3a5f,#162d4a);color:#bfd8f0}
th.h3{background:linear-gradient(180deg,#3d5c2a,#2e4a1f);color:#d4f0b8}
th.h4{background:linear-gradient(180deg,#7a5c00,#614a00);color:#fff5d4}
th.h5{background:linear-gradient(180deg,#5c1a5c,#4a104a);color:#f0d4f0}
th.num,td.num{text-align:right}
th.ctr,td.ctr{text-align:center}
td{border-bottom:1px solid #e0f2fe;padding:3px 5px;white-space:nowrap;font-variant-numeric:tabular-nums}
tr:hover td{background:#f0f9ff}
tr.sold td{opacity:.5}
.badge{display:inline-block;padding:1px 5px;border-radius:8px;font-size:9px;font-weight:700}
.badge-stk{background:#d1fae5;color:#065f46}.badge-sold{background:#fee2e2;color:#991b1b}
tfoot td{position:sticky;bottom:0;background:#e0f2fe;font-weight:700;color:#0c4a6e;border-top:2px solid #bae6fd;z-index:2}
.summary-bar{margin-top:8px;padding:10px 14px;background:linear-gradient(135deg,var(--hdr),var(--hdr2));border-radius:8px;display:flex;gap:16px;flex-wrap:wrap;align-items:center;color:#fff}
.summary-bar .f{display:flex;gap:4px;align-items:baseline}
.summary-bar .lbl{font-size:10px;font-weight:600;color:rgba(255,255,255,.7)}
.summary-bar .val{font-size:12px;font-weight:700;font-variant-numeric:tabular-nums}
.summary-bar .divider{width:1px;height:18px;background:rgba(255,255,255,.2)}
.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:4px}
@media print{.toolbar{display:none}body{background:#fff}.window{margin:0;box-shadow:none;border-radius:0}.tbl-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}.summary-bar{background:#f0f0f0;color:#000;border-radius:0}.summary-bar .lbl{color:#555}}
</style>
@include('partials.print-layout-head')
</head>
<body>
@php
    function saF(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function saD(?string $d): string { if (!$d) return ''; $ts = strtotime($d); return $ts ? date('d/m/Y', $ts) : $d; }
@endphp
<div class="window">
    <div class="titlebar"><h1>Barcode SAmt List</h1><span class="today">{{ date('d/m/Y') }}</span></div>
    <div class="toolbar">
        <form method="GET" id="reportForm">
        <div class="tb-row">
            <span class="tb-lbl">From:</span><input type="date" name="date1" value="{{ $dateFrom }}" class="tb-input" style="width:128px">
            <span class="tb-lbl">To:</span><input type="date" name="date2" value="{{ $dateTo }}" class="tb-input" style="width:128px">
            <div class="tb-sep"></div>
            <span class="tb-lbl">Doc No:</span><input type="text" name="docno" value="{{ $docNo }}" class="tb-input" style="width:80px">
            <div class="tb-sep"></div>
            <span class="tb-lbl">BC No:</span><input type="text" name="bcno" value="{{ $bcNo }}" class="tb-input" style="width:90px">
            <div class="tb-sep"></div>
            <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
            <button type="button" class="btn" onclick="window.print()">Print</button>
            <button type="button" class="btn btn-green" id="btnSaveAs">Save As</button>
        </div>
        <div class="tb-row">
            <span class="tb-lbl">Item:</span><input type="text" name="item_code" value="{{ $itemCode }}" class="tb-input" style="width:80px">
            <select name="stk_type" class="tb-select" style="width:100px">
                @foreach(['Stock Only','Not in Stock','All'] as $o)<option value="{{ $o }}" {{ $stkType===$o?'selected':'' }}>{{ $o }}</option>@endforeach
            </select>
            <div class="tb-sep"></div>
            <span class="tb-lbl">Supplier:</span><input type="text" name="supplier" value="{{ $supplier }}" class="tb-input" style="width:70px">
            <span class="tb-lbl">Group:</span>
            <select name="grp" class="tb-select" style="width:80px"><option value="">All</option>@foreach($groupCodes as $gc)<option value="{{ $gc }}" {{ $grpCode===$gc?'selected':'' }}>{{ $gc }}</option>@endforeach</select>
            <div class="tb-sep"></div>
            <span class="tb-lbl">CP From:</span><input type="text" name="cp_from" value="{{ $cpFrom }}" class="tb-input" style="width:70px">
            <span class="tb-lbl">CP To:</span><input type="text" name="cp_to" value="{{ $cpTo }}" class="tb-input" style="width:70px">
            <div class="tb-sep"></div>
            <button type="button" class="btn btn-blue" id="btnSelectAll">Select All</button>
            <button type="button" class="btn btn-amber" id="btnBcPrint">Barcode Print</button>
        </div>
        </form>
    </div>
    <div class="content">
    @if (!$showData)
        <div class="hint">Select date range and click <strong>Show</strong> to view barcode sales amount list.</div>
    @elseif (empty($rows))
        <div class="hint">No barcodes found for the selected criteria.</div>
    @else
        <div class="info-bar">
            <span><strong>Barcode SAmt List</strong> {{ saD($dateFrom) }} to {{ saD($dateTo) }}
                @if($stkType!=='All') &middot; {{ $stkType }} @endif
            </span>
            <span>{{ $totals['count'] }} barcode(s)</span>
        </div>
        <div class="tbl-wrap">
        <table id="saTable">
            <thead><tr>
                <th class="h1">Date</th>
                <th class="h1">Barcode</th>
                <th class="h2">Code</th>
                <th class="h2">Name</th>
                <th class="h2">HUID</th>
                <th class="h3 num">Qty</th>
                <th class="h3 num">Weight</th>
                <th class="h4 num">VA%</th>
                <th class="h3 num">Stone</th>
                <th class="h4 num">StAmt</th>
                <th class="h4 num">DmdWt</th>
                <th class="h4 num">DmdAmt</th>
                <th class="h4 num">SAmt</th>
                <th class="h4 num">CostAmt</th>
                <th class="h3 num">SoldWgt</th>
                <th class="h1 ctr">Stock</th>
                <th class="h5 ctr">Sel</th>
            </tr></thead>
            <tbody>
                @foreach ($rows as $r)
                @php $sold = strtoupper($r['stk'] ?? 'Y') !== 'Y'; @endphp
                <tr class="{{ $sold ? 'sold' : '' }}">
                    <td>{{ saD($r['tdate']) }}</td>
                    <td style="font-weight:700">{{ $r['bcode'] }}</td>
                    <td>{{ $r['icode'] }}</td>
                    <td>{{ $r['itemname'] ?? '' }}</td>
                    <td>{{ $r['huid'] ?? '' }}</td>
                    <td class="num">{{ (int)$r['qty'] }}</td>
                    <td class="num">{{ saF((float)$r['weight'],3) }}</td>
                    <td class="num">@if(($r['vap']??0)>0){{ saF((float)$r['vap']) }}%@elseif(($r['mcrate']??0)>0){{ saF((float)$r['mcrate']) }}@elseif(($r['mc']??0)>0){{ saF((float)$r['mc']) }}@endif</td>
                    <td class="num">{{ saF((float)$r['stweight'],3) }}</td>
                    <td class="num">{{ saF((float)$r['stprice']) }}</td>
                    <td class="num">{{ saF((float)($r['dmdwgt']??0),3) }}</td>
                    <td class="num">{{ saF((float)($r['dmdamt']??0)) }}</td>
                    <td class="num" style="font-weight:700">{{ saF((float)($r['tamt']??0)) }}</td>
                    <td class="num">{{ saF((float)($r['costamt']??0)) }}</td>
                    <td class="num">{{ saF((float)($r['soldwgt']??0),3) }}</td>
                    <td class="ctr">@if($sold)<span class="badge badge-sold">Sold</span>@else<span class="badge badge-stk">Stk</span>@endif</td>
                    <td class="ctr"><input type="checkbox" class="cbSel"></td>
                </tr>
                @endforeach
            </tbody>
            <tfoot><tr>
                <td colspan="5">Total ({{ $totals['count'] }})</td>
                <td class="num">{{ $totals['qty'] }}</td>
                <td class="num">{{ saF($totals['weight'],3) }}</td>
                <td></td>
                <td class="num">{{ saF($totals['stweight'],3) }}</td>
                <td class="num">{{ saF($totals['stprice']) }}</td>
                <td class="num">{{ saF($totals['dmdwgt'],3) }}</td>
                <td class="num">{{ saF($totals['dmdamt']) }}</td>
                <td class="num">{{ saF($totals['tamt']) }}</td>
                <td class="num">{{ saF($totals['costamt']) }}</td>
                <td class="num">{{ saF($totals['soldwgt'],3) }}</td>
                <td colspan="2"></td>
            </tr></tfoot>
        </table>
        </div>
        <div class="summary-bar">
            <div class="f"><span class="lbl">Count:</span><span class="val">{{ $totals['count'] }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Weight:</span><span class="val">{{ saF($totals['weight'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">SAmt:</span><span class="val">{{ saF($totals['tamt']) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">CostAmt:</span><span class="val">{{ saF($totals['costamt']) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">SoldWgt:</span><span class="val">{{ saF($totals['soldwgt'],3) }}</span></div>
        </div>
        <div class="count-bar">{{ $totals['count'] }} barcode(s) displayed</div>
    @endif
    </div>
</div>
<script>
var bsa=document.getElementById('btnSelectAll');
if(bsa)bsa.addEventListener('click',function(){var c=document.querySelectorAll('.cbSel');var a=Array.from(c).every(function(x){return x.checked});c.forEach(function(x){x.checked=!a})});
</script>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>ReportExport.initFromTable('btnSaveAs','#saTable','barcode_samt_{{ $dateFrom }}_{{ $dateTo }}');</script>
</body>
</html>
