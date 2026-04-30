<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sample Stock Register</title>
<style>
:root{--hdr:#4a1942;--hdr2:#6b2d6b;--border:#ddd0dd;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#9333ea}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#f8f0f8 0%,#fcf8fc 40%,#f4eef4 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(60,20,60,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}
.top{background:#fdf8fd;border-bottom:1px solid var(--border);padding:12px 16px}
.filter-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.field{display:flex;flex-direction:column;gap:3px}
.field label{font-size:11px;font-weight:700;color:#6b3d6b}
.field input{height:30px;border:1px solid #c5a5c5;border-radius:7px;padding:0 8px;font-size:12px;background:#fff;color:var(--text)}
.field input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(147,51,234,.12)}
.btn{height:30px;border-radius:7px;border:none;padding:0 16px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-primary:hover{opacity:.9}
.btn-outline{background:#fff;color:#6b3d6b;border:1px solid #c5a5c5}
.btn-outline:hover{background:#fdf0fd}
.btn-save{background:#e6f8ec;color:#1b5b31;border:1px solid #2a7a42}
.content{padding:14px 16px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:8px;display:flex;justify-content:space-between}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #c5a5c5;border-radius:10px;margin:10px 0}
.tbl-wrap{max-height:68vh;overflow:auto;border:1px solid var(--border);border-radius:8px}
table{width:100%;border-collapse:collapse;font-size:12px}
th{position:sticky;top:0;background:linear-gradient(180deg,#5a2050,#4a1942);color:#e8d0e8;padding:6px 8px;text-align:left;z-index:2;white-space:nowrap;font-size:11px;font-weight:600}
th.num,td.num{text-align:right}
td{border-bottom:1px solid #f0e8f0;padding:5px 8px;white-space:nowrap;font-variant-numeric:tabular-nums}
tr:hover td{background:#fdf8fd}
tfoot td{position:sticky;bottom:0;background:#f4e8f4;font-weight:700;color:#4a1942;border-top:2px solid #c5a5c5;z-index:2}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700}
.badge-p{background:#fef3c7;color:#92400e}.badge-r{background:#d1fae5;color:#065f46}
.summary-bar{margin-top:12px;padding:12px 16px;background:linear-gradient(135deg,var(--hdr),var(--hdr2));border-radius:10px;display:flex;gap:28px;flex-wrap:wrap;align-items:center;color:#fff}
.summary-bar .f{display:flex;gap:6px;align-items:baseline}
.summary-bar .lbl{font-size:11px;font-weight:600;color:rgba(255,255,255,.7)}
.summary-bar .val{font-size:14px;font-weight:700;font-variant-numeric:tabular-nums}
.summary-bar .divider{width:1px;height:24px;background:rgba(255,255,255,.2)}
.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:8px}
@media print{.top{display:none}body{background:#fff}.window{margin:0;box-shadow:none;border-radius:0}.tbl-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}.summary-bar{background:#f0f0f0;color:#000;border-radius:0}.summary-bar .lbl{color:#555}}
</style>
@include('partials.print-layout-head')
</head>
<body>
@php
    function ssFmt(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function ssDate(?string $d): string { if (!$d) return ''; $ts = strtotime($d); return $ts ? date('d/m/Y', $ts) : $d; }
@endphp
<div class="window">
    <div class="titlebar"><h1>Sample Stock Register</h1><span class="today">{{ date('d/m/Y') }}</span></div>
    <div class="top">
        <form method="GET" class="filter-row" id="reportForm">
            <div class="field"><label>From</label><input type="date" name="date1" value="{{ $dateFrom }}"></div>
            <div class="field"><label>To</label><input type="date" name="date2" value="{{ $dateTo }}"></div>
            <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
            <button type="button" class="btn btn-outline" onclick="window.print()">Print</button>
            <button type="button" class="btn btn-save" id="btnSaveAs">Save As</button>
        </form>
    </div>
    <div class="content">
    @if (!$showData)
        <div class="hint">Select date range and click <strong>Show</strong> to view sample stock register.</div>
    @elseif (empty($rows))
        <div class="hint">No sample stock entries found for the selected period.</div>
    @else
        <div class="info-bar"><span>{{ ssDate($dateFrom) }} &mdash; {{ ssDate($dateTo) }}</span><span>{{ $totals['count'] }} entries</span></div>
        <div class="tbl-wrap">
        <table>
            <thead><tr>
                <th>Date</th><th>Doc No</th><th>Party</th><th>Item</th>
                <th class="num">Qty</th><th class="num">Weight</th>
                <th>Particulars</th><th>Status</th><th>Refund Date</th>
            </tr></thead>
            <tbody>
                @foreach ($rows as $r)
                <tr>
                    <td>{{ ssDate($r['tdate']) }}</td>
                    <td>{{ $r['ordno'] }}</td>
                    <td>{{ $r['custname'] }}</td>
                    <td>{{ $r['itemname'] }}</td>
                    <td class="num">{{ (int)$r['qty'] }}</td>
                    <td class="num">{{ ssFmt((float)$r['weight'], 3) }}</td>
                    <td>{{ $r['part'] ?? '' }}</td>
                    <td>@if((int)($r['status'] ?? 1)===1)<span class="badge badge-p">Pending</span>@else<span class="badge badge-r">Returned</span>@endif</td>
                    <td>{{ ssDate($r['refdate'] ?? '') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot><tr>
                <td colspan="4">Total ({{ $totals['count'] }})</td>
                <td class="num">{{ $totals['qty'] }}</td>
                <td class="num">{{ ssFmt($totals['weight'], 3) }}</td>
                <td colspan="3"></td>
            </tr></tfoot>
        </table>
        </div>
        <div class="summary-bar">
            <div class="f"><span class="lbl">Entries:</span><span class="val">{{ $totals['count'] }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Total Qty:</span><span class="val">{{ $totals['qty'] }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Total Weight:</span><span class="val">{{ ssFmt($totals['weight'], 3) }}</span></div>
        </div>
        <div class="count-bar">{{ $totals['count'] }} entries displayed</div>
    @endif
    </div>
</div>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>ReportExport.initFromTable('btnSaveAs','table','sample_stock_{{ $dateFrom }}_{{ $dateTo }}');</script>
</body>
</html>
