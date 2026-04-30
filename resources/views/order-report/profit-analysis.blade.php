<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Sales Profit Analysis</title>
<style>
:root{--hdr:#0f3460;--hdr2:#1a5276;--border:#d0dbe8;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#1a73e8}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#e8f0fe 0%,#f0f4fa 40%,#eaf0f8 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(15,52,96,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700;letter-spacing:.3px}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}

.top{background:#f6f9ff;border-bottom:1px solid var(--border);padding:12px 16px}
.filter-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.field{display:flex;flex-direction:column;gap:3px}
.field label{font-size:11px;font-weight:700;color:#2c5282}
.field input{height:30px;border:1px solid #b0c4de;border-radius:7px;padding:0 8px;font-size:12px;background:#fff;color:var(--text)}
.field input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(26,115,232,.12)}
.btn{height:30px;border-radius:7px;border:none;padding:0 16px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-primary:hover{opacity:.9}
.btn-outline{background:#fff;color:#2c5282;border:1px solid #b0c4de}
.btn-outline:hover{background:#f0f5ff}
.btn-save{background:#e6f8ec;color:#1b5b31;border:1px solid #2a7a42}
.btn-save:hover{background:#d0f0da}

.content{padding:14px 16px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:8px;display:flex;justify-content:space-between;align-items:center}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #b0c4de;border-radius:10px;margin:10px 0}

.tbl-wrap{max-height:68vh;overflow:auto;border:1px solid var(--border);border-radius:8px}
table{width:100%;border-collapse:collapse;font-size:12px}
th{position:sticky;top:0;z-index:2;padding:6px 8px;text-align:left;font-size:11px;font-weight:600;white-space:nowrap}
th.base{background:linear-gradient(180deg,#1a3d6e,#0f3460);color:#c8d8f0}
th.adv{background:linear-gradient(180deg,#2d6a4f,#1b4d3e);color:#b7e4c7}
th.sold{background:linear-gradient(180deg,#5c3d1a,#4a3010);color:#f5e6cc}
th.diff-hdr{background:linear-gradient(180deg,#5c1a5c,#4a104a);color:#f0d4f0}
th.num,td.num{text-align:right}
td{border-bottom:1px solid #e8eef6;padding:5px 8px;white-space:nowrap;font-variant-numeric:tabular-nums}
tr:hover td{background:#f5f8ff}
td.positive{color:#16a34a;font-weight:600}
td.negative{color:#dc2626;font-weight:600}
tfoot td{position:sticky;bottom:0;background:#edf4fc;font-weight:700;color:#0f3460;border-top:2px solid #b0c4de;z-index:2}

.summary-bar{margin-top:12px;padding:12px 16px;background:linear-gradient(135deg,#0f3460,#1a5276);border-radius:10px;display:flex;gap:28px;flex-wrap:wrap;align-items:center;color:#fff}
.summary-bar .f{display:flex;gap:6px;align-items:baseline}
.summary-bar .lbl{font-size:11px;font-weight:600;color:rgba(255,255,255,.7)}
.summary-bar .val{font-size:14px;font-weight:700;font-variant-numeric:tabular-nums}
.summary-bar .divider{width:1px;height:24px;background:rgba(255,255,255,.2)}
.summary-bar .total-block{margin-left:auto;background:rgba(255,255,255,.12);padding:4px 14px;border-radius:8px}
.summary-bar .total-block .val{font-size:16px}
.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:8px}

@media print{
    .top{display:none}body{background:#fff}.window{margin:0;box-shadow:none;border-radius:0}
    .content{padding:6px}.tbl-wrap{max-height:none;overflow:visible;border:0}
    th,tfoot td{position:static}
    .summary-bar{background:#f0f0f0;color:#000;border-radius:0}.summary-bar .lbl{color:#555}
}
</style>
@include('partials.print-layout-head')
</head>
<body>

@php
    function paFmt(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function paDate(?string $d): string {
        if (!$d) return '';
        $ts = strtotime($d);
        return $ts ? date('d/m/Y', $ts) : $d;
    }
@endphp

<div class="window">
    <div class="titlebar">
        <h1>Order Sales Profit Analysis</h1>
        <span class="today">{{ date('d/m/Y') }}</span>
    </div>

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
        <div class="hint">Select date range and click <strong>Show</strong> to view profit analysis for order sales.</div>
    @elseif (empty($rows))
        <div class="hint">No order sales found for the selected period.</div>
    @else
        <div class="info-bar">
            <span>{{ paDate($dateFrom) }} &mdash; {{ paDate($dateTo) }}</span>
            <span>{{ $totals['count'] }} bill(s)</span>
        </div>

        <div class="tbl-wrap">
        <table>
            <thead><tr>
                <th class="base">Date</th>
                <th class="base">Bill No</th>
                <th class="base">Order No</th>
                <th class="base">Customer</th>
                <th class="base num">GRate</th>
                <th class="adv num">TotAdvAmt</th>
                <th class="adv num">TotAdvWgt</th>
                <th class="sold num">TotSoldAmt</th>
                <th class="sold num">TotSoldWgt</th>
                <th class="diff-hdr num">Diff. Amt</th>
            </tr></thead>
            <tbody>
                @foreach ($rows as $r)
                @php $diffClass = $r['diff'] >= 0 ? 'positive' : 'negative'; @endphp
                <tr>
                    <td>{{ paDate($r['tdate']) }}</td>
                    <td>{{ $r['billno'] }}</td>
                    <td>{{ $r['orderno'] }}</td>
                    <td>{{ $r['custname'] }}</td>
                    <td class="num">{{ paFmt((float)$r['grate']) }}</td>
                    <td class="num">{{ paFmt($r['totadvamt']) }}</td>
                    <td class="num">{{ paFmt($r['totadvwgt'], 3) }}</td>
                    <td class="num">{{ paFmt($r['totsoldamt']) }}</td>
                    <td class="num">{{ paFmt($r['totsoldwgt'], 3) }}</td>
                    <td class="num {{ $diffClass }}">{{ paFmt($r['diff']) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot><tr>
                <td>Total:</td>
                <td>{{ $totals['count'] }}</td>
                <td colspan="8" class="num">Diff Total: {{ paFmt($totals['diff']) }}</td>
            </tr></tfoot>
        </table>
        </div>

        <div class="summary-bar">
            <div class="f"><span class="lbl">Bills:</span><span class="val">{{ $totals['count'] }}</span></div>
            <div class="divider"></div>
            <div class="total-block">
                <div class="f">
                    <span class="lbl">Total Diff Amount:</span>
                    <span class="val">{{ paFmt($totals['diff']) }}</span>
                </div>
            </div>
        </div>
        <div class="count-bar">{{ $totals['count'] }} bill(s) displayed</div>
    @endif
    </div>
</div>

<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>
ReportExport.initFromTable('btnSaveAs', 'table',
  'order_profit_analysis_{{ $dateFrom }}_{{ $dateTo }}');
</script>
</body>
</html>
