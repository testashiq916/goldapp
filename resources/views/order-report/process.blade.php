<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Process Report</title>
<style>
:root{--hdr:#1a365d;--hdr2:#2b6cb0;--border:#d0dbe8;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#3182ce}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#ebf4ff 0%,#f0f6ff 40%,#e8f0fa 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(20,40,80,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}
.top{background:#f5f9ff;border-bottom:1px solid var(--border);padding:12px 16px}
.filter-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.btn{height:30px;border-radius:7px;border:none;padding:0 16px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-primary:hover{opacity:.9}
.btn-outline{background:#fff;color:#2b6cb0;border:1px solid #b0c4de}
.btn-outline:hover{background:#f0f5ff}
.btn-save{background:#e6f8ec;color:#1b5b31;border:1px solid #2a7a42}
.content{padding:14px 16px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:8px;display:flex;justify-content:space-between}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #b0c4de;border-radius:10px;margin:10px 0}
.tbl-wrap{max-height:70vh;overflow:auto;border:1px solid var(--border);border-radius:8px}
table{width:100%;border-collapse:collapse;font-size:11px}
th{position:sticky;top:0;z-index:2;padding:5px 6px;text-align:left;font-size:10px;font-weight:700;white-space:nowrap}
th.base{background:linear-gradient(180deg,#1a365d,#153050);color:#b0c8e8}
th.item{background:linear-gradient(180deg,#2d6a4f,#1b4d3e);color:#b7e4c7}
th.proc{background:linear-gradient(180deg,#744210,#5c3510);color:#fde8cc}
th.cust{background:linear-gradient(180deg,#553c9a,#44337a);color:#d6bcfa}
th.num,td.num{text-align:right}
td{border-bottom:1px solid #e8eef6;padding:4px 6px;white-space:nowrap;font-variant-numeric:tabular-nums}
tr:hover td{background:#f5f9ff}
.stage-1{color:#2563eb}.stage-2{color:#d97706}.stage-3{color:#16a34a;font-weight:700}
tfoot td{position:sticky;bottom:0;background:#edf4fc;font-weight:700;color:#1a365d;border-top:2px solid #b0c4de;z-index:2}
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
    function opFmt(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function opDate(?string $d): string { if (!$d) return ''; $ts = strtotime($d); return $ts ? date('d/m/Y', $ts) : $d; }
    function opStage($s): string { return match((int)$s) { 1=>'New Work', 2=>'In Progress', 3=>'Finished', default=>'' }; }
    function opStgCls($s): string { return match((int)$s) { 1=>'stage-1', 2=>'stage-2', 3=>'stage-3', default=>'' }; }
@endphp
<div class="window">
    <div class="titlebar"><h1>Order Process Report</h1><span class="today">{{ date('d/m/Y') }}</span></div>
    <div class="top">
        <form method="GET" class="filter-row" id="reportForm">
            <span style="font-size:12px;color:#64748b">Shows all pending orders with processing details</span>
            <div style="margin-left:auto;display:flex;gap:6px">
                <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
                <button type="button" class="btn btn-outline" onclick="window.print()">Print</button>
                <button type="button" class="btn btn-save" id="btnSaveAs">Save As</button>
            </div>
        </form>
    </div>
    <div class="content">
    @if (!$showData)
        <div class="hint">Click <strong>Show</strong> to load all pending orders with process details.</div>
    @elseif (empty($rows))
        <div class="hint">No pending orders found.</div>
    @else
        <div class="info-bar"><span>All Pending Orders</span><span>{{ count($rows) }} item line(s)</span></div>
        <div class="tbl-wrap">
        <table>
            <thead><tr>
                <th class="base">Ord. No</th><th class="base">Ord. Date</th><th class="base">DDate</th>
                <th class="item">Code</th><th class="item">Item</th>
                <th class="item num">Qty</th><th class="item num">Weight</th><th class="item num">St.Wgt</th>
                <th class="proc">SMan</th><th class="proc">Jewlry</th><th class="proc">Smith</th>
                <th class="proc">Stage</th><th class="proc">SmithDD</th>
                <th class="cust">Customer</th><th class="cust">Mobile</th>
                <th class="cust num">TAdvance</th>
            </tr></thead>
            <tbody>
                @php $prevSlno = null; @endphp
                @foreach ($rows as $r)
                <tr>
                    <td>{{ $r['slno'] !== $prevSlno ? $r['ordno'] : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? opDate($r['tdate']) : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? opDate($r['duedate'] ?? '') : '' }}</td>
                    <td>{{ $r['itemcode'] }}</td>
                    <td>{{ $r['itemname'] }}</td>
                    <td class="num">{{ (int)$r['qty'] }}</td>
                    <td class="num">{{ opFmt((float)$r['weight'], 3) }}</td>
                    <td class="num">{{ opFmt((float)($r['stonewgt'] ?? 0), 3) }}</td>
                    <td>{{ $r['smcode'] ?? '' }}</td>
                    <td>{{ $r['jewlcode'] ?? '' }}</td>
                    <td>{{ $r['smith'] ?? '' }}</td>
                    <td class="{{ opStgCls($r['stage'] ?? 0) }}">{{ opStage($r['stage'] ?? 0) }}</td>
                    <td>{{ opDate($r['smithddate'] ?? '') }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? $r['custname'] : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? ($r['mobile'] ?? '') : '' }}</td>
                    <td class="num">{{ $r['slno'] !== $prevSlno ? opFmt($r['tadv']) : '' }}</td>
                </tr>
                @php $prevSlno = $r['slno']; @endphp
                @endforeach
            </tbody>
            <tfoot><tr>
                <td colspan="6">Total</td>
                <td class="num">{{ opFmt($totals['weight'], 3) }}</td>
                <td colspan="9"></td>
            </tr></tfoot>
        </table>
        </div>
        <div class="summary-bar">
            <div class="f"><span class="lbl">Lines:</span><span class="val">{{ count($rows) }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Total Weight:</span><span class="val">{{ opFmt($totals['weight'], 3) }}</span></div>
        </div>
        <div class="count-bar">{{ count($rows) }} line(s) displayed</div>
    @endif
    </div>
</div>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>ReportExport.initFromTable('btnSaveAs','table','order_process_{{ date("Y-m-d") }}');</script>
</body>
</html>
