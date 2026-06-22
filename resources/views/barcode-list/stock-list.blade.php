<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barcode Stock List</title>
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
.content{padding:10px 12px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:6px;display:flex;justify-content:space-between}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #bae6fd;border-radius:10px;margin:10px 0}
.tbl-wrap{max-height:60vh;overflow:auto;border:1px solid var(--border);border-radius:6px}
table{width:100%;border-collapse:collapse;font-size:11px}
th{position:sticky;top:0;z-index:2;padding:4px 5px;font-size:10px;font-weight:700;white-space:nowrap}
th.h1{background:linear-gradient(180deg,#0c4a6e,#0a3d5c);color:#bae6fd;text-align:left}
th.h2{background:linear-gradient(180deg,#3d5c2a,#2e4a1f);color:#d4f0b8;text-align:right}
th.h3{background:linear-gradient(180deg,#7a5c00,#614a00);color:#fff5d4;text-align:right}
td{border-bottom:1px solid #e0f2fe;padding:3px 5px;white-space:nowrap;font-variant-numeric:tabular-nums}
td.num{text-align:right}
tr:hover td{background:#f0f9ff}
tfoot td{position:sticky;bottom:0;background:#e0f2fe;font-weight:700;color:#0c4a6e;border-top:2px solid #bae6fd;z-index:2}
.summary-bar{margin-top:8px;padding:10px 14px;background:linear-gradient(135deg,var(--hdr),var(--hdr2));border-radius:8px;display:flex;gap:16px;flex-wrap:wrap;align-items:center;color:#fff}
.summary-bar .f{display:flex;gap:4px;align-items:baseline}
.summary-bar .lbl{font-size:10px;font-weight:600;color:rgba(255,255,255,.7)}
.summary-bar .val{font-size:12px;font-weight:700;font-variant-numeric:tabular-nums}
.summary-bar .divider{width:1px;height:18px;background:rgba(255,255,255,.2)}
.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:4px}
.stickerbar{margin-top:6px;font-size:12px;color:var(--muted)}
@media print{.toolbar{display:none}body{background:#fff}.window{margin:0;box-shadow:none;border-radius:0}.tbl-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}.summary-bar{background:#f0f0f0;color:#000;border-radius:0}.summary-bar .lbl{color:#555}}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
@php
    function slF(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function slD(?string $d): string { if (!$d) return ''; $ts = strtotime($d); return $ts ? date('d/m/Y', $ts) : $d; }
@endphp
<div class="window">
    <div class="titlebar"><h1>Barcode Stock List</h1><span class="today">{{ date('d/m/Y') }}</span></div>
    <div class="toolbar">
        <form method="GET" id="reportForm">
        <div class="tb-row">
            <span class="tb-lbl">Item:</span><input type="text" name="item" value="{{ $itemCode }}" class="tb-input" style="width:80px">
            <span class="tb-lbl">As on:</span><input type="date" name="ason" value="{{ $asOn }}" class="tb-input" style="width:128px">
            <div class="tb-sep"></div>
            <span class="tb-lbl">Wgt From:</span><input type="text" name="wgt_from" value="{{ $wgtFrom }}" class="tb-input" style="width:60px">
            <span class="tb-lbl">To:</span><input type="text" name="wgt_to" value="{{ $wgtTo }}" class="tb-input" style="width:60px">
            <div class="tb-sep"></div>
            <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
            <button type="button" class="btn" onclick="window.print()">Print</button>
            <button type="button" class="btn btn-green" id="btnSaveAs">Save As</button>
        </div>
        <div class="tb-row">
            <span class="tb-lbl">Report:</span>
            <select name="report" class="tb-select" style="width:160px">
                @foreach(['Detailed Report','Summary Report','SubGrp Summary','Touch Summary','SL Comparison'] as $r)
                <option value="{{ $r }}" {{ $report===$r?'selected':'' }}>{{ $r }}</option>
                @endforeach
            </select>
            <select name="stk" class="tb-select" style="width:90px">
                @foreach(['Stock Only','All'] as $o)<option value="{{ $o }}" {{ $stkType===$o?'selected':'' }}>{{ $o }}</option>@endforeach
            </select>
            <div class="tb-sep"></div>
            <span class="tb-lbl">Counter:</span>
            <select name="counter" class="tb-select" style="width:100px"><option value="">All</option>@foreach($counters as $c)<option value="{{ $c->code }}" {{ $counter===$c->code?'selected':'' }}>{{ $c->name }}</option>@endforeach</select>
            <label class="tb-chk"><input type="checkbox" name="counter_filter" {{ $counterFilter?'checked':'' }}> Filter</label>
            <div class="tb-sep"></div>
            <span class="tb-lbl">Group:</span>
            <select name="grp" class="tb-select" style="width:80px"><option value="">All</option>@foreach($groupCodes as $gc)<option value="{{ $gc }}" {{ $grpCode===$gc?'selected':'' }}>{{ $gc }}</option>@endforeach</select>
            <span class="tb-lbl">SubGrp:</span><input type="text" name="subgrp" value="{{ $subGrp }}" class="tb-input" style="width:60px">
            <span class="tb-lbl">Size:</span><input type="text" name="size" value="{{ $size }}" class="tb-input" style="width:60px">
            <span class="tb-lbl">Model:</span><input type="text" name="model" value="{{ $model }}" class="tb-input" style="width:70px">
        </div>
        </form>
    </div>

    <div class="content">
    @if (!$showData)
        <div class="hint">Select filters and click <strong>Show</strong> to view barcode stock list.</div>
    @elseif ($report === 'SL Comparison' && !empty($compRows))
        {{-- ═══ Stock Ledger Comparison ═══ --}}
        <div class="info-bar"><span><strong>Stock Ledger vs Barcode Comparison</strong> As On {{ slD($asOn) }}</span><span>{{ $compTotals['items'] }} item(s)</span></div>
        <div class="tbl-wrap">
        <table id="slTable">
            <thead>
                <tr>
                    <th class="h1" rowspan="2" style="text-align:left">Code</th>
                    <th class="h1" rowspan="2" style="text-align:left">Item Name</th>
                    <th style="background:linear-gradient(180deg,#1e6f30,#15572a);color:#c8f5d4;text-align:center;position:sticky;top:0;z-index:2" colspan="3">Stock Ledger</th>
                    <th style="background:linear-gradient(180deg,#1e3a5f,#162d4a);color:#bfd8f0;text-align:center;position:sticky;top:0;z-index:2" colspan="3">Barcode Details</th>
                    <th style="background:linear-gradient(180deg,#991b1b,#7f1d1d);color:#fecaca;text-align:center;position:sticky;top:0;z-index:2" colspan="3">Difference</th>
                </tr>
                <tr>
                    @foreach(['#15572a','#15572a','#15572a','#162d4a','#162d4a','#162d4a','#7f1d1d','#7f1d1d','#7f1d1d'] as $i => $bg)
                    <th style="background:{{ $bg }};color:#fff;text-align:right;position:sticky;top:26px;z-index:2;font-size:10px;padding:3px 5px">{{ ['Qty','Wgt','Net','Qty','Wgt','Net','Qty','Wgt','Net'][$i] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($compRows as $r)
                <tr class="{{ ($r['dQty']!=0||abs($r['dWgt'])>0.001||abs($r['dNet'])>0.001)?'diff-row':'' }}">
                    <td>{{ $r['code'] }}</td><td>{{ $r['name'] }}</td>
                    <td class="num">{{ $r['slQty'] }}</td><td class="num">{{ slF($r['slWgt'],3) }}</td><td class="num">{{ slF($r['slNet'],3) }}</td>
                    <td class="num">{{ $r['bcQty'] }}</td><td class="num">{{ slF($r['bcWgt'],3) }}</td><td class="num">{{ slF($r['bcNet'],3) }}</td>
                    <td class="num">{{ $r['dQty'] }}</td><td class="num">{{ slF($r['dWgt'],3) }}</td><td class="num">{{ slF($r['dNet'],3) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot><tr>
                <td colspan="2">Total ({{ $compTotals['items'] }})</td>
                <td class="num">{{ $compTotals['slQty'] }}</td><td class="num">{{ slF($compTotals['slWgt'],3) }}</td><td class="num">{{ slF($compTotals['slNet'],3) }}</td>
                <td class="num">{{ $compTotals['bcQty'] }}</td><td class="num">{{ slF($compTotals['bcWgt'],3) }}</td><td class="num">{{ slF($compTotals['bcNet'],3) }}</td>
                <td class="num">{{ $compTotals['dQty'] }}</td><td class="num">{{ slF($compTotals['dWgt'],3) }}</td><td class="num">{{ slF($compTotals['dNet'],3) }}</td>
            </tr></tfoot>
        </table></div>
        <div class="summary-bar">
            <div class="f"><span class="lbl">Items:</span><span class="val">{{ $compTotals['items'] }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">SL Net:</span><span class="val">{{ slF($compTotals['slNet'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">BC Net:</span><span class="val">{{ slF($compTotals['bcNet'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Diff:</span><span class="val">{{ slF($compTotals['dNet'],3) }}</span></div>
        </div>
        <div class="count-bar">{{ $compTotals['items'] }} item(s)</div>

    @elseif (empty($rows))
        <div class="hint">No stock found for selected criteria.</div>
    @elseif (str_contains($report, 'Touch'))
        {{-- ═══ Touch Summary ═══ --}}
        <div class="info-bar"><span><strong>Stock List — Touch Summary</strong> @if($asOn) As On {{ slD($asOn) }} @endif</span><span>{{ count($rows) }} touch group(s)</span></div>
        <div class="tbl-wrap">
        <table id="slTable">
            <thead><tr><th class="h1">Stk Touch</th><th class="h2">BC Nos</th><th class="h2">Qty</th><th class="h2">Weight</th><th class="h2">Stone</th><th class="h2">Net Wgt</th><th class="h3">To Std Touch</th></tr></thead>
            <tbody>
                @foreach ($rows as $r)
                @php $nw = (float)($r['twgt']??0) - (float)($r['tstwgt']??0); $std = round($nw * (float)($r['stktouch']??0) / 100, 2); @endphp
                <tr>
                    <td class="num">{{ slF((float)($r['stktouch']??0)) }}</td>
                    <td class="num">{{ (int)($r['tbcnos']??0) }}</td>
                    <td class="num">{{ (int)($r['tqty']??0) }}</td>
                    <td class="num">{{ slF((float)($r['twgt']??0),3) }}</td>
                    <td class="num">{{ slF((float)($r['tstwgt']??0),3) }}</td>
                    <td class="num">{{ slF($nw,3) }}</td>
                    <td class="num">{{ slF($std,3) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td>Total</td><td class="num">{{ $totals['bcnos'] }}</td><td class="num">{{ $totals['qty'] }}</td><td class="num">{{ slF($totals['weight'],3) }}</td><td class="num">{{ slF($totals['stweight'],3) }}</td><td class="num">{{ slF($totals['netwgt'],3) }}</td><td></td></tr></tfoot>
        </table></div>

    @elseif (str_contains($report, 'Summary'))
        {{-- ═══ Summary / SubGrp Summary ═══ --}}
        <div class="info-bar"><span><strong>Stock List — {{ $report }}</strong> @if($asOn) As On {{ slD($asOn) }} @endif @if($counterFilter && $counter) Counter: {{ $counter }} @endif</span><span>{{ count($rows) }} item(s)</span></div>
        <div class="tbl-wrap">
        <table id="slTable">
            <thead><tr><th class="h1">Code</th><th class="h1">Name</th>@if(str_contains($report,'SubGrp'))<th class="h1">SubGrp</th>@endif<th class="h2">BC Nos</th><th class="h2">Qty</th><th class="h2">Weight</th><th class="h2">Stone</th><th class="h2">Net Wgt</th></tr></thead>
            <tbody>
                @foreach ($rows as $r)
                @php $nw = (float)($r['twgt']??0) - (float)($r['tstwgt']??0); @endphp
                <tr>
                    <td>{{ $r['icode'] ?? $r['itemcode'] ?? '' }}</td>
                    <td>{{ $r['itemname'] ?? '' }}</td>
                    @if(str_contains($report,'SubGrp'))<td>{{ $r['subgrp'] ?? '' }}</td>@endif
                    <td class="num">{{ (int)($r['tbcnos']??0) }}</td>
                    <td class="num">{{ (int)($r['tqty']??0) }}</td>
                    <td class="num">{{ slF((float)($r['twgt']??0),3) }}</td>
                    <td class="num">{{ slF((float)($r['tstwgt']??0),3) }}</td>
                    <td class="num">{{ slF($nw,3) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="{{ str_contains($report,'SubGrp') ? 3 : 2 }}">Total</td><td class="num">{{ $totals['bcnos'] }}</td><td class="num">{{ $totals['qty'] }}</td><td class="num">{{ slF($totals['weight'],3) }}</td><td class="num">{{ slF($totals['stweight'],3) }}</td><td class="num">{{ slF($totals['netwgt'],3) }}</td></tr></tfoot>
        </table></div>

    @else
        {{-- ═══ Detailed Report ═══ --}}
        <div class="info-bar"><span><strong>Barcode Stock List</strong> @if($asOn) As On {{ slD($asOn) }} @endif @if($counterFilter && $counter) Counter: {{ $counter }} @endif</span><span>{{ count($rows) }} barcode(s)</span></div>
        <div class="tbl-wrap">
        <table id="slTable">
            <thead><tr>
                <th class="h1">Barcode</th><th class="h1">Code</th><th class="h1">Name</th>
                <th class="h1">HUID</th><th class="h1">SerialNo</th>
                <th class="h2">Qty</th><th class="h2">Weight</th><th class="h2">Stone</th><th class="h2">Net Wgt</th>
                <th class="h3">StkTch</th><th class="h3">Dmd Ct</th><th class="h3">Stn Amt</th>
                <th class="h3">Dmd Amt</th><th class="h1">Stk</th><th class="h1">SDate</th>
            </tr></thead>
            <tbody>
                @foreach ($rows as $r)
                @php $nw = (float)$r['weight'] - (float)$r['stweight']; @endphp
                <tr>
                    <td style="font-weight:700">{{ $r['bcode'] }}</td>
                    <td>{{ $r['icode'] ?? $r['itemcode'] ?? '' }}</td>
                    <td>{{ $r['itemname'] ?? '' }}</td>
                    <td>{{ $r['huid'] ?? '' }}</td>
                    <td>{{ $r['serialno'] ?? '' }}</td>
                    <td class="num">{{ (int)$r['qty'] }}</td>
                    <td class="num">{{ slF((float)$r['weight'],3) }}</td>
                    <td class="num">{{ slF((float)$r['stweight'],3) }}</td>
                    <td class="num">{{ slF($nw,3) }}</td>
                    <td class="num">{{ slF((float)($r['stktouch']??0)) }}</td>
                    <td class="num">{{ slF((float)($r['dmdwgt']??0),3) }}</td>
                    <td class="num">{{ slF((float)($r['stprice']??0)) }}</td>
                    <td class="num">{{ slF((float)($r['dmdamt']??0)) }}</td>
                    <td>{{ strtoupper($r['stk']??'') }}</td>
                    <td>{{ slD($r['sdate']??'') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot><tr>
                <td colspan="5">Total ({{ $totals['count'] }})</td>
                <td class="num">{{ $totals['qty'] }}</td><td class="num">{{ slF($totals['weight'],3) }}</td>
                <td class="num">{{ slF($totals['stweight'],3) }}</td><td class="num">{{ slF($totals['netwgt'],3) }}</td>
                <td colspan="6"></td>
            </tr></tfoot>
        </table></div>
        <div class="stickerbar">Tot Wgt with Sticker: {{ slF($totals['weight'],3) }}</div>
    @endif

    @if ($showData && !empty($rows))
        <div class="summary-bar">
            <div class="f"><span class="lbl">Items:</span><span class="val">{{ $totals['count'] }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">BC Nos:</span><span class="val">{{ $totals['bcnos'] }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Qty:</span><span class="val">{{ $totals['qty'] }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Weight:</span><span class="val">{{ slF($totals['weight'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Stone:</span><span class="val">{{ slF($totals['stweight'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Net:</span><span class="val">{{ slF($totals['netwgt'],3) }}</span></div>
        </div>
        <div class="count-bar">{{ $totals['count'] }} row(s) displayed</div>
    @endif
    </div>
</div>
<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>ReportExport.initFromTable('btnSaveAs','#slTable','bc_stock_list_{{ date("Y-m-d") }}');</script>
</body>
</html>
