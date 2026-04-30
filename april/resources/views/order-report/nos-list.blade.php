<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Nos List</title>
<style>
:root{--hdr:#2d3748;--hdr2:#4a5568;--border:#d7dfeb;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#4a5568}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#f0f2f5 0%,#f7f8fa 40%,#edf0f4 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(30,40,60,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}
.top{background:#f9fafb;border-bottom:1px solid var(--border);padding:12px 16px}
.filter-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.field{display:flex;flex-direction:column;gap:3px}
.field label{font-size:11px;font-weight:700;color:#4a5568}
.field input{height:30px;border:1px solid #cbd5e0;border-radius:7px;padding:0 8px;font-size:12px;background:#fff;color:var(--text)}
.field input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(74,85,104,.12)}
.btn{height:30px;border-radius:7px;border:none;padding:0 16px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-primary:hover{opacity:.9}
.btn-outline{background:#fff;color:#4a5568;border:1px solid #cbd5e0}
.btn-outline:hover{background:#f7f8fa}
.btn-save{background:#e6f8ec;color:#1b5b31;border:1px solid #2a7a42}
.content{padding:14px 16px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:8px;display:flex;justify-content:space-between}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #cbd5e0;border-radius:10px;margin:10px 0}
.tbl-wrap{max-height:68vh;overflow:auto;border:1px solid var(--border);border-radius:8px}
table{width:100%;border-collapse:collapse;font-size:12px}
th{position:sticky;top:0;background:linear-gradient(180deg,#3d4a5c,#2d3748);color:#cbd5e0;padding:6px 8px;text-align:left;z-index:2;white-space:nowrap;font-size:11px;font-weight:600}
td{border-bottom:1px solid #e8eef6;padding:5px 8px;white-space:nowrap}
tr:hover td{background:#f7f8fa}
tr.gap-row td{color:#dc2626;font-weight:600}
tfoot td{position:sticky;bottom:0;background:#edf2f8;font-weight:700;color:#2d3748;border-top:2px solid #cbd5e0;z-index:2}
.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:8px}
@media print{.top{display:none}body{background:#fff}.window{margin:0;box-shadow:none;border-radius:0}.tbl-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}}
</style>
@include('partials.print-layout-head')
</head>
<body>
@php
    function nlDate(?string $d): string { if (!$d) return ''; $ts = strtotime($d); return $ts ? date('d/m/Y', $ts) : $d; }
@endphp
<div class="window">
    <div class="titlebar"><h1>Order Nos List</h1><span class="today">{{ date('d/m/Y') }}</span></div>
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
        <div class="hint">Select date range and click <strong>Show</strong> to view pending order numbers list.</div>
    @elseif (empty($rows))
        <div class="hint">No pending orders found for the selected period.</div>
    @else
        <div class="info-bar"><span>Pending Orders &middot; {{ nlDate($dateFrom) }} &mdash; {{ nlDate($dateTo) }}</span><span>{{ count($rows) }} order(s)</span></div>
        <div class="tbl-wrap">
        <table>
            <thead><tr>
                <th>Ord. No.</th><th>Ord. Date</th><th>Org. DDate</th>
                <th>Cur. DDate</th><th>Party</th><th>Address</th>
            </tr></thead>
            <tbody>
                @php $prevOrdno = null; $prevSlno = null; @endphp
                @foreach ($rows as $idx => $r)
                @php
                    // Detect gap in order numbers (highlight missing sequence)
                    $curNum = (int) preg_replace('/[^0-9]/', '', $r['ordno']);
                    $prevNum = $prevOrdno !== null ? (int) preg_replace('/[^0-9]/', '', $prevOrdno) : $curNum - 1;
                    $isGap = ($curNum - $prevNum) > 1 && $prevOrdno !== null;
                @endphp
                <tr class="{{ $isGap ? 'gap-row' : '' }}">
                    <td>{{ $r['slno'] !== $prevSlno ? $r['ordno'] : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? nlDate($r['tdate']) : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? nlDate($r['duedate_org'] ?? '') : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? nlDate($r['duedate'] ?? '') : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? $r['custname'] : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? ($r['addr'] ?? '') : '' }}</td>
                </tr>
                @php $prevOrdno = $r['ordno']; $prevSlno = $r['slno']; @endphp
                @endforeach
            </tbody>
        </table>
        </div>
        <div class="count-bar">{{ count($rows) }} order(s) displayed</div>
    @endif
    </div>
</div>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>ReportExport.initFromTable('btnSaveAs','table','order_nos_{{ $dateFrom }}_{{ $dateTo }}');</script>
</body>
</html>
