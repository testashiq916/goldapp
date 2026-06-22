<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Diamond/Pres.Stone Stock List</title>
<style>
:root{--hdr:#4a1942;--hdr2:#7c2d7c;--border:#ddd0dd;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#9333ea}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#f8f0f8 0%,#fcf8fc 40%,#f4eef4 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(60,20,60,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:10px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}
.toolbar{background:#fdf8fd;border-bottom:1px solid var(--border);padding:8px 12px}
.tb-row{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:4px}
.tb-row:last-child{margin-bottom:0}
.tb-lbl{font-size:11px;font-weight:700;color:#6b3d6b;white-space:nowrap}
.tb-input,.tb-select{height:26px;border:1px solid #c5a5c5;border-radius:5px;padding:0 6px;font-size:12px;background:#fff;color:var(--text)}
.tb-input:focus,.tb-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(147,51,234,.12)}
.tb-sep{width:1px;height:22px;background:#c5a5c5;margin:0 2px}
.tb-chk{display:flex;align-items:center;gap:3px;font-size:11px;color:#6b3d6b;cursor:pointer}
.tb-chk input{margin:0}
.btn{height:26px;border-radius:5px;border:1px solid #c5a5c5;padding:0 10px;font-size:11px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;background:#f8f0f8;color:#4a1942;white-space:nowrap}
.btn:hover{background:#f0e0f0}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff;border-color:var(--hdr)}
.btn-green{background:#e6f8ec;color:#1b5b31;border-color:#2a7a42}
.content{padding:10px 12px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:6px;display:flex;justify-content:space-between}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #c5a5c5;border-radius:10px;margin:10px 0}
.tbl-wrap{max-height:60vh;overflow:auto;border:1px solid var(--border);border-radius:6px}
table{width:100%;border-collapse:collapse;font-size:11px}
th{position:sticky;top:0;z-index:2;padding:4px 5px;font-size:10px;font-weight:700;white-space:nowrap}
th.h1{background:linear-gradient(180deg,#5a2050,#4a1942);color:#e8d0e8;text-align:left}
th.h2{background:linear-gradient(180deg,#3d5c2a,#2e4a1f);color:#d4f0b8;text-align:right}
th.h3{background:linear-gradient(180deg,#7a5c00,#614a00);color:#fff5d4;text-align:right}
th.h4{background:linear-gradient(180deg,#1e3a5f,#162d4a);color:#bfd8f0;text-align:right}
td{border-bottom:1px solid #f0e8f0;padding:3px 5px;white-space:nowrap;font-variant-numeric:tabular-nums}
td.num{text-align:right}
tr:hover td{background:#fdf8fd}
tfoot td{position:sticky;bottom:0;background:#f4e8f4;font-weight:700;color:#4a1942;border-top:2px solid #c5a5c5;z-index:2}
.summary-bar{margin-top:8px;padding:10px 14px;background:linear-gradient(135deg,var(--hdr),var(--hdr2));border-radius:8px;display:flex;gap:14px;flex-wrap:wrap;align-items:center;color:#fff}
.summary-bar .f{display:flex;gap:4px;align-items:baseline}
.summary-bar .lbl{font-size:10px;font-weight:600;color:rgba(255,255,255,.7)}
.summary-bar .val{font-size:12px;font-weight:700;font-variant-numeric:tabular-nums}
.summary-bar .divider{width:1px;height:18px;background:rgba(255,255,255,.2)}
.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:4px}
@media print{.toolbar{display:none}body{background:#fff}.window{margin:0;box-shadow:none;border-radius:0}.tbl-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}.summary-bar{background:#f0f0f0;color:#000;border-radius:0}.summary-bar .lbl{color:#555}}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
@php
    function dsF(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function dsD(?string $d): string { if (!$d) return ''; $ts = strtotime($d); return $ts ? date('d/m/Y', $ts) : $d; }
@endphp
<div class="window">
    <div class="titlebar"><h1>Diamond/Pres.Stone Stock List</h1><span class="today">{{ date('d/m/Y') }}</span></div>
    <div class="toolbar">
        <form method="GET" id="reportForm">
        <div class="tb-row">
            <span class="tb-lbl">Type:</span>
            <select name="type" class="tb-select" style="width:110px">
                @foreach(['All','Diamond','Platinum','Color Stone'] as $o)<option value="{{ $o }}" {{ $type===$o?'selected':'' }}>{{ $o }}</option>@endforeach
            </select>
            <span class="tb-lbl">St.Type:</span><input type="text" name="st_item" value="{{ $stItem }}" class="tb-input" style="width:70px;text-transform:uppercase">
            <span class="tb-lbl">Item:</span><input type="text" name="item" value="{{ $itemCode }}" class="tb-input" style="width:70px;text-transform:uppercase">
            <span class="tb-lbl">Report:</span>
            <select name="report" class="tb-select" style="width:100px">
                @foreach(['Summary','Details','Stone Info'] as $o)<option value="{{ $o }}" {{ $report===$o?'selected':'' }}>{{ $o }}</option>@endforeach
            </select>
            <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
            <button type="button" class="btn" onclick="window.print()">Print</button>
            <button type="button" class="btn btn-green" id="btnSaveAs">Save As</button>
        </div>
        <div class="tb-row">
            <span class="tb-lbl">From:</span><input type="date" name="date1" value="{{ $dateFrom }}" class="tb-input" style="width:128px">
            <span class="tb-lbl">To:</span><input type="date" name="date2" value="{{ $dateTo }}" class="tb-input" style="width:128px">
            <div class="tb-sep"></div>
            <span class="tb-lbl">Group:</span>
            <select name="grp" class="tb-select" style="width:80px"><option value="">All</option>@foreach($groupCodes as $gc)<option value="{{ $gc }}" {{ $grpCode===$gc?'selected':'' }}>{{ $gc }}</option>@endforeach</select>
            <label class="tb-chk"><input type="checkbox" name="grp_filter" {{ $grpFilter?'checked':'' }}> Filter</label>
            <div class="tb-sep"></div>
            <label class="tb-chk"><input type="checkbox" name="stk_only" {{ $stkOnly?'checked':'' }}> Stock Only</label>
            <label class="tb-chk"><input type="checkbox" name="with_pres" {{ $withPres?'checked':'' }}> With Pres.Stone Wgt Only</label>
        </div>
        </form>
    </div>
    <div class="content">
    @if (!$showData)
        <div class="hint">Select type and click <strong>Show</strong> to view diamond/precious stone stock.</div>
    @elseif (empty($rows))
        <div class="hint">No data found.</div>

    @elseif ($report === 'Summary')
        <div class="info-bar"><span><strong>Stock List ({{ $type }}) — Summary</strong></span><span>{{ $totals['count'] }} item(s)</span></div>
        <div class="tbl-wrap"><table id="dsTable">
            <thead><tr><th class="h1">Item Name</th><th class="h1">Code</th><th class="h2">Qty</th><th class="h2">GoldWgt</th><th class="h3">Carat</th></tr></thead>
            <tbody>
                @foreach($rows as $r)
                <tr><td>{{ $r['name'] }}</td><td>{{ $r['code'] }}</td><td class="num">{{ (int)($r['nos']??0) }}</td><td class="num">{{ dsF((float)($r['goldwgt']??0),3) }}</td><td class="num">{{ dsF((float)($r['dmdwgt']??0),3) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td>Total ({{ $totals['count'] }})</td><td></td><td class="num">{{ $totals['nos'] }}</td><td class="num">{{ dsF($totals['goldwgt'],3) }}</td><td class="num">{{ dsF($totals['dmdwgt'],3) }}</td></tr></tfoot>
        </table></div>

    @elseif ($report === 'Stone Info')
        <div class="info-bar"><span><strong>Pres.Stone Stock — Stone Info</strong></span><span>{{ $totals['count'] }} row(s)</span></div>
        <div class="tbl-wrap"><table id="dsTable">
            <thead><tr><th class="h1">Barcode</th><th class="h1">Code</th><th class="h1">Name</th><th class="h2">Qty</th><th class="h2">Weight</th><th class="h2">Stone</th><th class="h2">Net Wgt</th><th class="h4">St.Code</th><th class="h4">St.Cut</th><th class="h4">St.Size</th><th class="h3">Pcs</th><th class="h3">Carats</th><th class="h3">Rate</th><th class="h3">Amount</th></tr></thead>
            <tbody>
                @foreach($rows as $r)
                <tr><td style="font-weight:700">{{ $r['bcode'] }}</td><td>{{ $r['icode'] }}</td><td>{{ $r['itemname']??'' }}</td><td class="num">{{ (int)$r['qty'] }}</td><td class="num">{{ dsF((float)$r['weight'],3) }}</td><td class="num">{{ dsF((float)$r['stweight'],3) }}</td><td class="num">{{ dsF((float)$r['weight']-(float)$r['stweight'],3) }}</td><td>{{ $r['stcode']??'' }}</td><td>{{ $r['stcut']??'' }}</td><td>{{ $r['stsize']??'' }}</td><td class="num">{{ (int)($r['pcs']??0) }}</td><td class="num">{{ dsF((float)($r['carats']??0),3) }}</td><td class="num">{{ dsF((float)($r['rate']??0)) }}</td><td class="num">{{ dsF((float)($r['amount']??0)) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="3">Total</td><td class="num">{{ $totals['qty'] }}</td><td class="num">{{ dsF($totals['weight'],3) }}</td><td class="num">{{ dsF($totals['stone'],3) }}</td><td class="num">{{ dsF($totals['netwgt'],3) }}</td><td colspan="3"></td><td class="num">{{ $totals['pcs'] }}</td><td class="num">{{ dsF($totals['carats'],3) }}</td><td></td><td class="num">{{ dsF($totals['amount']) }}</td></tr></tfoot>
        </table></div>

    @else
        <div class="info-bar"><span><strong>Stock List ({{ $type }}) — Details</strong></span><span>{{ $totals['count'] }} barcode(s)</span></div>
        <div class="tbl-wrap"><table id="dsTable">
            <thead><tr><th class="h1">Barcode</th><th class="h1">Code</th><th class="h1">Name</th><th class="h2">Qty</th><th class="h2">Weight</th><th class="h2">Stone</th><th class="h2">Net Wgt</th><th class="h3">Dmd Ct</th><th class="h1">Stk</th></tr></thead>
            <tbody>
                @foreach($rows as $r)
                <tr><td style="font-weight:700">{{ $r['bcode'] }}</td><td>{{ $r['icode'] }}</td><td>{{ $r['itemname']??'' }}</td><td class="num">{{ (int)$r['qty'] }}</td><td class="num">{{ dsF((float)$r['weight'],3) }}</td><td class="num">{{ dsF((float)$r['stweight'],3) }}</td><td class="num">{{ dsF((float)$r['weight']-(float)$r['stweight'],3) }}</td><td class="num">{{ dsF((float)($r['dmdwgt']??0),3) }}</td><td>{{ $r['stk'] }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="3">Total ({{ $totals['count'] }})</td><td class="num">{{ $totals['qty'] }}</td><td class="num">{{ dsF($totals['weight'],3) }}</td><td class="num">{{ dsF($totals['stone'],3) }}</td><td class="num">{{ dsF($totals['netwgt'],3) }}</td><td class="num">{{ dsF($totals['dmdwgt'],3) }}</td><td></td></tr></tfoot>
        </table></div>
    @endif

    @if($showData && !empty($rows))
        <div class="summary-bar">
            <div class="f"><span class="lbl">Items:</span><span class="val">{{ $totals['count'] }}</span></div><div class="divider"></div>
            @if($report==='Summary')
            <div class="f"><span class="lbl">GoldWgt:</span><span class="val">{{ dsF($totals['goldwgt'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Carats:</span><span class="val">{{ dsF($totals['dmdwgt'],3) }}</span></div>
            @else
            <div class="f"><span class="lbl">Weight:</span><span class="val">{{ dsF($totals['weight'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Net:</span><span class="val">{{ dsF($totals['netwgt'],3) }}</span></div>
            @if($totals['pcs'])<div class="divider"></div><div class="f"><span class="lbl">Pcs:</span><span class="val">{{ $totals['pcs'] }}</span></div>@endif
            @if($totals['amount'])<div class="divider"></div><div class="f"><span class="lbl">Amt:</span><span class="val">{{ dsF($totals['amount']) }}</span></div>@endif
            @endif
        </div>
        <div class="count-bar">{{ $totals['count'] }} row(s)</div>
    @endif
    </div>
</div>
<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>ReportExport.initFromTable('btnSaveAs','#dsTable','diamond_stock_{{ date("Y-m-d") }}');</script>
</body>
</html>
