<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barcode Stock Verification</title>
<style>
:root{--hdr:#0c4a6e;--hdr2:#0369a1;--border:#d0dbe8;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#0284c7}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#e0f2fe 0%,#f0f9ff 40%,#e8f4fc 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(12,74,110,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}
.top{background:#f0f9ff;border-bottom:1px solid var(--border);padding:12px 16px}
.tb-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.tb-lbl{font-size:11px;font-weight:700;color:#0c4a6e;white-space:nowrap}
.tb-input,.tb-select{height:28px;border:1px solid #bae6fd;border-radius:6px;padding:0 7px;font-size:12px;background:#fff;color:var(--text)}
.tb-input:focus,.tb-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(2,132,199,.15)}
.tb-chk{display:flex;align-items:center;gap:3px;font-size:11px;color:#0c4a6e;cursor:pointer}
.tb-chk input{margin:0}
.btn{height:28px;border-radius:6px;border:1px solid #b0c4de;padding:0 12px;font-size:11px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;background:#f0f4f8;color:#1e3a5f;white-space:nowrap}
.btn:hover{background:#e0eaf4}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff;border-color:var(--hdr)}
.btn-primary:hover{opacity:.9}
.btn-green{background:#e6f8ec;color:#1b5b31;border-color:#2a7a42}
.content{padding:12px 16px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:8px;display:flex;justify-content:space-between}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #bae6fd;border-radius:10px;margin:10px 0}
.tbl-wrap{max-height:64vh;overflow:auto;border:1px solid var(--border);border-radius:6px}
table{width:100%;border-collapse:collapse;font-size:11px}
/* Two-level header */
th{position:sticky;z-index:2;padding:4px 6px;text-align:center;font-size:10px;font-weight:700;white-space:nowrap;border:1px solid rgba(255,255,255,.15)}
th.top-hdr{top:0}
th.sub-hdr{top:26px}
th.h-base{background:linear-gradient(180deg,#0c4a6e,#0a3d5c);color:#bae6fd}
th.h-issue{background:linear-gradient(180deg,#1e6f30,#15572a);color:#c8f5d4}
th.h-sales{background:linear-gradient(180deg,#7a5c00,#614a00);color:#fff5d4}
th.h-bal{background:linear-gradient(180deg,#1e3a5f,#162d4a);color:#bfd8f0}
th.h-verify{background:linear-gradient(180deg,#5a2574,#481a60);color:#e4d4f5}
th.h-diff{background:linear-gradient(180deg,#991b1b,#7f1d1d);color:#fecaca}
th.num,td.num{text-align:right}
th.left{text-align:left}
td{border-bottom:1px solid #e0f2fe;padding:4px 6px;white-space:nowrap;font-variant-numeric:tabular-nums}
tr:hover td{background:#f0f9ff}
tr.diff-row td{color:#991b1b;font-weight:600}
tfoot td{position:sticky;bottom:0;background:#e0f2fe;font-weight:700;color:#0c4a6e;border-top:2px solid #bae6fd;z-index:2}
.summary-bar{margin-top:8px;padding:10px 14px;background:linear-gradient(135deg,var(--hdr),var(--hdr2));border-radius:8px;display:flex;gap:16px;flex-wrap:wrap;align-items:center;color:#fff}
.summary-bar .f{display:flex;gap:4px;align-items:baseline}
.summary-bar .lbl{font-size:10px;font-weight:600;color:rgba(255,255,255,.7)}
.summary-bar .val{font-size:12px;font-weight:700;font-variant-numeric:tabular-nums}
.summary-bar .divider{width:1px;height:18px;background:rgba(255,255,255,.2)}
.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:4px}
@media print{.top{display:none}body{background:#fff}.window{margin:0;box-shadow:none;border-radius:0}.tbl-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}.summary-bar{background:#f0f0f0;color:#000;border-radius:0}.summary-bar .lbl{color:#555}}
</style>
@include('partials.print-layout-head')
</head>
<body>
@php
    function svF(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function svD(?string $d): string { if (!$d) return ''; $ts = strtotime($d); return $ts ? date('d/m/Y', $ts) : $d; }
@endphp
<div class="window">
    <div class="titlebar"><h1>Barcode Stock Verification</h1><span class="today">{{ date('d/m/Y') }}</span></div>
    <div class="top">
        <form method="GET" class="tb-row" id="reportForm">
            <span class="tb-lbl">Date:</span>
            <input type="date" name="date" value="{{ $date }}" class="tb-input" style="width:140px">
            <span class="tb-lbl">Counter:</span>
            <select name="counter" class="tb-select" style="width:120px">
                <option value="">All</option>
                @foreach ($counters as $c)
                <option value="{{ $c->code }}" {{ $counter === $c->code ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
            <label class="tb-chk"><input type="checkbox" name="counter_filter" {{ $counterFilter ? 'checked' : '' }}> Filter</label>
            <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
            <button type="button" class="btn" id="btnSort">Sort</button>
            <button type="button" class="btn btn-green" id="btnSaveAs">Save As</button>
            <button type="button" class="btn" onclick="window.print()">Print</button>
        </form>
    </div>
    <div class="content">
    @if (!$showData)
        <div class="hint">Select date and click <strong>Show</strong> to view stock verification.</div>
    @elseif (empty($rows))
        <div class="hint">No counter stock data found.</div>
    @else
        <div class="info-bar">
            <span><strong>Stock Verification</strong> {{ svD($date) }}
                @if ($counterFilter && $counter) &middot; Counter: {{ $counter }} @endif
            </span>
            <span>{{ count($rows) }} item(s)</span>
        </div>
        <div class="tbl-wrap">
        <table id="svTable">
            <thead>
                <tr>
                    <th class="top-hdr h-base" rowspan="2" style="text-align:left">Code</th>
                    <th class="top-hdr h-base" rowspan="2" style="text-align:left">Name</th>
                    <th class="top-hdr h-issue" colspan="2">Issue To Counter</th>
                    <th class="top-hdr h-sales" colspan="2">Sales</th>
                    <th class="top-hdr h-bal" colspan="2">Balance</th>
                    <th class="top-hdr h-verify" colspan="2">Stock Verified</th>
                    <th class="top-hdr h-diff" colspan="2">Difference</th>
                </tr>
                <tr>
                    <th class="sub-hdr h-issue num">Qty</th><th class="sub-hdr h-issue num">Weight</th>
                    <th class="sub-hdr h-sales num">Qty</th><th class="sub-hdr h-sales num">Weight</th>
                    <th class="sub-hdr h-bal num">Qty</th><th class="sub-hdr h-bal num">Weight</th>
                    <th class="sub-hdr h-verify num">Qty</th><th class="sub-hdr h-verify num">Weight</th>
                    <th class="sub-hdr h-diff num">Qty</th><th class="sub-hdr h-diff num">Weight</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $r)
                <tr class="{{ ($r['diffqty'] != 0 || round($r['diffwgt'],3) != 0) ? 'diff-row' : '' }}">
                    <td>{{ $r['code'] }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td class="num">{{ $r['counteriqty'] }}</td>
                    <td class="num">{{ svF($r['counteriwgt'], 3) }}</td>
                    <td class="num">{{ $r['countersqty'] }}</td>
                    <td class="num">{{ svF($r['counterswgt'], 3) }}</td>
                    <td class="num">{{ $r['balqty'] }}</td>
                    <td class="num">{{ svF($r['balwgt'], 3) }}</td>
                    <td class="num">{{ $r['verifyqty'] }}</td>
                    <td class="num">{{ svF($r['verifywgt'], 3) }}</td>
                    <td class="num">{{ $r['diffqty'] }}</td>
                    <td class="num">{{ svF($r['diffwgt'], 3) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot><tr>
                <td colspan="2">Total</td>
                <td class="num">{{ $totals['counteriqty'] }}</td>
                <td class="num">{{ svF($totals['counteriwgt'], 3) }}</td>
                <td class="num">{{ $totals['countersqty'] }}</td>
                <td class="num">{{ svF($totals['counterswgt'], 3) }}</td>
                <td class="num">{{ $totals['balqty'] }}</td>
                <td class="num">{{ svF($totals['balwgt'], 3) }}</td>
                <td class="num">{{ $totals['verifyqty'] }}</td>
                <td class="num">{{ svF($totals['verifywgt'], 3) }}</td>
                <td class="num">{{ $totals['diffqty'] }}</td>
                <td class="num">{{ svF($totals['diffwgt'], 3) }}</td>
            </tr></tfoot>
        </table>
        </div>
        <div class="summary-bar">
            <div class="f"><span class="lbl">Items:</span><span class="val">{{ count($rows) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Issued:</span><span class="val">{{ $totals['counteriqty'] }} / {{ svF($totals['counteriwgt'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Balance:</span><span class="val">{{ $totals['balqty'] }} / {{ svF($totals['balwgt'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Verified:</span><span class="val">{{ $totals['verifyqty'] }} / {{ svF($totals['verifywgt'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Diff:</span><span class="val">{{ $totals['diffqty'] }} / {{ svF($totals['diffwgt'],3) }}</span></div>
        </div>
        <div class="count-bar">{{ count($rows) }} item(s) displayed</div>
    @endif
    </div>
</div>
<script>
var bso=document.getElementById('btnSort');
if(bso){var ss={col:null,asc:true};bso.addEventListener('click',function(){var tb=document.querySelector('#svTable tbody');if(!tb)return;var cols=[0,1,2,3,6,7,10,11];var ci=ss.col===null?0:(cols.indexOf(ss.col)+1)%cols.length;ss.col=cols[ci];ss.asc=!ss.asc;var rows=Array.from(tb.querySelectorAll('tr'));rows.sort(function(a,b){var va=(a.children[ss.col]||{}).textContent||'';var vb=(b.children[ss.col]||{}).textContent||'';var na=parseFloat(va.replace(/,/g,''));var nb=parseFloat(vb.replace(/,/g,''));if(!isNaN(na)&&!isNaN(nb))return ss.asc?na-nb:nb-na;return ss.asc?va.localeCompare(vb):vb.localeCompare(va)});rows.forEach(function(r){tb.appendChild(r)})})}
</script>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>ReportExport.initFromTable('btnSaveAs','#svTable','bc_stock_verify_{{ $date }}');</script>
</body>
</html>
