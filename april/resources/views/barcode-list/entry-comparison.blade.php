<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barcode Entry Comparison</title>
<style>
:root{--hdr:#0c4a6e;--hdr2:#0369a1;--border:#d0dbe8;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#0284c7}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#e0f2fe 0%,#f0f9ff 40%,#e8f4fc 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(12,74,110,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:10px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}
.top{background:#f0f9ff;border-bottom:1px solid var(--border);padding:12px 16px}
.tb-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.tb-lbl{font-size:11px;font-weight:700;color:#0c4a6e;white-space:nowrap}
.tb-input{height:28px;border:1px solid #bae6fd;border-radius:6px;padding:0 8px;font-size:12px;background:#fff;color:var(--text)}
.tb-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(2,132,199,.15)}
.btn{height:28px;border-radius:6px;border:1px solid #b0c4de;padding:0 12px;font-size:11px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;background:#f0f4f8;color:#1e3a5f;white-space:nowrap}
.btn:hover{background:#e0eaf4}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff;border-color:var(--hdr)}
.btn-green{background:#e6f8ec;color:#1b5b31;border-color:#2a7a42}
.content{padding:10px 12px}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #bae6fd;border-radius:10px;margin:10px 0}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:6px;display:flex;justify-content:space-between}
.tbl-wrap{max-height:62vh;overflow:auto;border:1px solid var(--border);border-radius:6px}
table{width:100%;border-collapse:collapse;font-size:12px}
th{position:sticky;top:0;z-index:2;padding:5px 6px;font-size:11px;font-weight:700;white-space:nowrap}
th.h1{background:linear-gradient(180deg,#0c4a6e,#0a3d5c);color:#bae6fd;text-align:left}
th.h2{background:linear-gradient(180deg,#1e6f30,#15572a);color:#c8f5d4;text-align:right}
th.h3{background:linear-gradient(180deg,#1e3a5f,#162d4a);color:#bfd8f0;text-align:right}
th.h4{background:linear-gradient(180deg,#991b1b,#7f1d1d);color:#fecaca;text-align:right}
td{border-bottom:1px solid #e0f2fe;padding:4px 6px;white-space:nowrap;font-variant-numeric:tabular-nums}
td.num{text-align:right}
tr:hover td{background:#f0f9ff}
tr.diff-row td{color:#991b1b;font-weight:600}
tfoot td{position:sticky;bottom:0;background:#e0f2fe;font-weight:700;color:#0c4a6e;border-top:2px solid #bae6fd;z-index:2}
.summary-bar{margin-top:8px;padding:10px 14px;background:linear-gradient(135deg,var(--hdr),var(--hdr2));border-radius:8px;display:flex;gap:16px;flex-wrap:wrap;align-items:center;color:#fff}
.summary-bar .f{display:flex;gap:4px;align-items:baseline}
.summary-bar .lbl{font-size:10px;font-weight:600;color:rgba(255,255,255,.7)}
.summary-bar .val{font-size:12px;font-weight:700;font-variant-numeric:tabular-nums}
.summary-bar .divider{width:1px;height:18px;background:rgba(255,255,255,.2)}
.avg-row td{font-style:italic;color:var(--muted)}
.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:4px}
@media print{.top{display:none}body{background:#fff}.window{margin:0;box-shadow:none;border-radius:0}.tbl-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}.summary-bar{background:#f0f0f0;color:#000;border-radius:0}.summary-bar .lbl{color:#555}}
</style>
@include('partials.print-layout-head')
</head>
<body>
@php
    function ecF(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function ecD(?string $d): string { if (!$d) return ''; $ts = strtotime($d); return $ts ? date('d/m/Y', $ts) : $d; }
@endphp
<div class="window">
    <div class="titlebar"><h1>Barcode Entry Comparison</h1><span class="today">{{ date('d/m/Y') }}</span></div>
    <div class="top">
        <form method="GET" class="tb-row">
            <span class="tb-lbl">Date From:</span>
            <input type="date" name="date1" value="{{ $dateFrom }}" class="tb-input" style="width:140px">
            <span class="tb-lbl">Date To:</span>
            <input type="date" name="date2" value="{{ $dateTo }}" class="tb-input" style="width:140px">
            <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
            <button type="button" class="btn" onclick="window.print()">Print</button>
            <button type="button" class="btn btn-green" id="btnSaveAs">Save As</button>
        </form>
    </div>
    <div class="content">
    @if (!$showData)
        <div class="hint">Select date range and click <strong>Show</strong> to compare barcode document entries vs actual barcode data.</div>
    @elseif (empty($rows))
        <div class="hint">No barcode documents found for the selected period.</div>
    @else
        <div class="info-bar">
            <span><strong>Barcode Entry Comparison</strong> {{ ecD($dateFrom) }} to {{ ecD($dateTo) }}</span>
            <span>{{ count($rows) }} document(s)</span>
        </div>
        <div class="tbl-wrap">
        <table id="ecTable">
            <thead><tr>
                <th class="h1">Date</th>
                <th class="h1">Doc No</th>
                <th class="h1">Smith</th>
                <th class="h2">Tot.Qty</th>
                <th class="h3">BC.TQty</th>
                <th class="h4">Diff</th>
                <th class="h2">Tot.Wgt</th>
                <th class="h3">BC.TWgt</th>
                <th class="h4">Diff</th>
            </tr></thead>
            <tbody>
                @foreach ($rows as $r)
                <tr class="{{ ($r['qtydiff'] != 0 || abs($r['wgtdiff']) > 0.001) ? 'diff-row' : '' }}">
                    <td>{{ ecD($r['tdate']) }}</td>
                    <td style="font-weight:700">{{ $r['docno'] }}</td>
                    <td>{{ $r['smith'] ?? '' }}</td>
                    <td class="num">{{ (int)$r['totnos'] }}</td>
                    <td class="num">{{ $r['bctqty'] }}</td>
                    <td class="num">{{ $r['qtydiff'] }}</td>
                    <td class="num">{{ ecF((float)$r['totwgt'], 3) }}</td>
                    <td class="num">{{ ecF($r['bctwgt'], 3) }}</td>
                    <td class="num">{{ ecF($r['wgtdiff'], 3) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">Total</td>
                    <td class="num">{{ $totals['totnos'] }}</td>
                    <td class="num">{{ $totals['bctqty'] }}</td>
                    <td class="num">{{ $totals['qtydiff'] }}</td>
                    <td class="num">{{ ecF($totals['totwgt'], 3) }}</td>
                    <td class="num">{{ ecF($totals['bctwgt'], 3) }}</td>
                    <td class="num">{{ ecF($totals['wgtdiff'], 3) }}</td>
                </tr>
                @if (count($rows) > 0)
                <tr class="avg-row">
                    <td colspan="3">Avg</td>
                    <td></td><td></td>
                    <td class="num">{{ round($totals['qtydiff'] / count($rows)) }}</td>
                    <td></td><td></td>
                    <td class="num">{{ ecF($totals['wgtdiff'] / count($rows), 3) }}</td>
                </tr>
                @endif
            </tfoot>
        </table>
        </div>

        <div class="summary-bar">
            <div class="f"><span class="lbl">Docs:</span><span class="val">{{ count($rows) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Tot Qty:</span><span class="val">{{ $totals['totnos'] }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">BC Qty:</span><span class="val">{{ $totals['bctqty'] }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Qty Diff:</span><span class="val">{{ $totals['qtydiff'] }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Tot Wgt:</span><span class="val">{{ ecF($totals['totwgt'], 3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">BC Wgt:</span><span class="val">{{ ecF($totals['bctwgt'], 3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Wgt Diff:</span><span class="val">{{ ecF($totals['wgtdiff'], 3) }}</span></div>
        </div>
        <div class="count-bar">{{ count($rows) }} document(s) displayed</div>
    @endif
    </div>
</div>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>ReportExport.initFromTable('btnSaveAs','#ecTable','bc_entry_comparison_{{ $dateFrom }}_{{ $dateTo }}');</script>
</body>
</html>
