<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Send Mail To HO</title>
<style>
:root{--hdr:#1a4731;--hdr2:#2d7a52;--border:#d0dbe8;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#16a34a}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#eefff3 0%,#f4fbf7 40%,#edf8f2 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(20,60,40,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}

.top{background:#f9fcfa;border-bottom:1px solid var(--border);padding:12px 16px}
.filter-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.filter-row+.filter-row{margin-top:8px}
.field{display:flex;flex-direction:column;gap:3px}
.field label{font-size:11px;font-weight:700;color:#3b6d50}
.field input,.field select{height:30px;border:1px solid #b5d4c4;border-radius:7px;padding:0 8px;font-size:12px;background:#fff;color:var(--text)}
.field input:focus,.field select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(22,163,74,.12)}
.btn{height:30px;border-radius:7px;border:none;padding:0 16px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-primary:hover{opacity:.9}
.btn-outline{background:#fff;color:#3b6d50;border:1px solid #b5d4c4}
.btn-outline:hover{background:#f0faf4}
.btn-export{background:#e6f0ff;color:#1e3a5f;border:1px solid #2a6398}
.btn-export:hover{background:#d0e6ff}
.btn-save{background:#e6f8ec;color:#1b5b31;border:1px solid #2a7a42}

.content{padding:14px 16px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:8px;display:flex;justify-content:space-between}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #b5d4c4;border-radius:10px;margin:10px 0}

.tbl-wrap{max-height:66vh;overflow:auto;border:1px solid var(--border);border-radius:8px}
table{width:100%;border-collapse:collapse;font-size:11px}
th{position:sticky;top:0;z-index:2;padding:5px 6px;text-align:left;font-size:10px;font-weight:700;white-space:nowrap}
th.base{background:linear-gradient(180deg,#1a4731,#153d28);color:#b7e4c7}
th.item{background:linear-gradient(180deg,#2d3d7b,#243268);color:#c8d8f0}
th.num,td.num{text-align:right}
td{border-bottom:1px solid #e8f0ec;padding:4px 6px;white-space:nowrap;font-variant-numeric:tabular-nums}
tr:hover td{background:#f2faf5}
.stage-1{color:#2563eb}.stage-2{color:#d97706}.stage-3{color:#16a34a;font-weight:700}
tfoot td{position:sticky;bottom:0;background:#e6f5ec;font-weight:700;color:#174a31;border-top:2px solid #b5d4c4;z-index:2}
.model-row td{font-style:italic;color:#64748b;padding-left:20px;border-bottom:1px dashed #e0e8e4}

.summary-bar{margin-top:12px;padding:12px 16px;background:linear-gradient(135deg,#1a4731,#2d7a52);border-radius:10px;display:flex;gap:24px;flex-wrap:wrap;align-items:center;color:#fff}
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
    function smFmt(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function smDate(?string $d): string { if (!$d) return ''; $ts = strtotime($d); return $ts ? date('d/m/Y', $ts) : $d; }
    function smStage($s): string { return match((int)$s) { 1=>'New Work', 2=>'In Progress', 3=>'Finished', default=>'' }; }
    function smStgCls($s): string { return match((int)$s) { 1=>'stage-1', 2=>'stage-2', 3=>'stage-3', default=>'' }; }
@endphp

<div class="window">
    <div class="titlebar"><h1>Send Mail To HO - Order Report</h1><span class="today">{{ date('d/m/Y') }}</span></div>

    <div class="top">
        <form method="GET" id="reportForm">
            <div class="filter-row">
                <div class="field"><label>From</label><input type="date" name="date1" value="{{ $dateFrom }}"></div>
                <div class="field"><label>To</label><input type="date" name="date2" value="{{ $dateTo }}"></div>
                <div class="field">
                    <label>Status</label>
                    <select name="filter">
                        <option value="All"      {{ $filter === 'All' ? 'selected' : '' }}>All</option>
                        <option value="Pending"  {{ $filter === 'Pending' ? 'selected' : '' }}>Pending Only</option>
                        <option value="Returned" {{ $filter === 'Returned' ? 'selected' : '' }}>Returned Only</option>
                    </select>
                </div>
                <div class="field"><label>Smith</label><input type="text" name="smith" value="{{ $smith }}" style="width:80px;text-transform:uppercase" placeholder="Code"></div>
                <div class="field"><label>Jewlry</label><input type="text" name="jewl" value="{{ $jewl }}" style="width:80px;text-transform:uppercase" placeholder="Code"></div>
            </div>
            <div class="filter-row">
                <div class="field">
                    <label>Stage Pref</label>
                    <select name="stage_pref" style="width:80px">
                        <option value="Only" {{ $stagePref === 'Only' ? 'selected' : '' }}>Only</option>
                        <option value="Not"  {{ $stagePref === 'Not' ? 'selected' : '' }}>Not</option>
                    </select>
                </div>
                <div class="field">
                    <label>Stage</label>
                    <select name="stage">
                        <option value="All"              {{ $stage === 'All' ? 'selected' : '' }}>All</option>
                        <option value="New Work"         {{ $stage === 'New Work' ? 'selected' : '' }}>New Work</option>
                        <option value="Work In Progress" {{ $stage === 'Work In Progress' ? 'selected' : '' }}>Work In Progress</option>
                        <option value="Finished"         {{ $stage === 'Finished' ? 'selected' : '' }}>Finished</option>
                    </select>
                </div>
                <div style="display:flex;gap:6px;align-items:flex-end">
                    <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
                    <button type="button" class="btn btn-outline" onclick="window.print()">Print</button>
                    <button type="button" class="btn btn-save" id="btnSaveAs">Save As</button>
                    @if ($showData && !empty($rows))
                    <a href="{{ url('/order-send-mail/export') }}?{{ http_build_query(request()->except('show') + ['show' => 1]) }}" class="btn btn-export">Export CSV</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <div class="content">
    @if (!$showData)
        <div class="hint">Select date range and filters, then click <strong>Show</strong> to generate the order report for HO.</div>
    @elseif (empty($rows))
        <div class="hint">No orders found for the selected criteria.</div>
    @else
        <div class="info-bar">
            <span>{{ smDate($dateFrom) }} &mdash; {{ smDate($dateTo) }}
                @if ($filter !== 'All') &middot; {{ $filter }} @endif
                @if ($stage !== 'All') &middot; Stage: {{ $stagePref }} {{ $stage }} @endif
            </span>
            <span>{{ $totals['count'] }} line(s)</span>
        </div>

        <div class="tbl-wrap">
        <table>
            <thead><tr>
                <th class="base">Date</th><th class="base">Ord No</th><th class="base">Due Date</th>
                <th class="base">Customer</th>
                <th class="item">Code</th><th class="item">Item</th>
                <th class="item num">Qty</th><th class="item num">Weight</th><th class="item num">Stone</th>
            </tr></thead>
            <tbody>
                @php $prevSlno = null; @endphp
                @foreach ($rows as $r)
                <tr>
                    <td>{{ $r['slno'] !== $prevSlno ? smDate($r['tdate']) : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? $r['ordno'] : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? smDate($r['duedate'] ?? '') : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? $r['custname'] : '' }}</td>
                    <td>{{ $r['itemcode'] }}</td>
                    <td>{{ $r['itemname'] }}</td>
                    <td class="num">{{ (int)$r['qty'] }}</td>
                    <td class="num">{{ smFmt((float)$r['weight'], 3) }}</td>
                    <td class="num">{{ smFmt((float)($r['stonewgt'] ?? 0), 3) }}</td>
                </tr>
                @if (!empty($r['part']))
                <tr class="model-row">
                    <td colspan="4"></td>
                    <td colspan="5">Model: {{ $r['part'] }}</td>
                </tr>
                @endif
                @php $prevSlno = $r['slno']; @endphp
                @endforeach
            </tbody>
            <tfoot><tr>
                <td>Total:</td>
                <td>{{ $totals['count'] }}</td>
                <td colspan="4"></td>
                <td class="num">{{ $totals['qty'] }}</td>
                <td class="num">{{ smFmt($totals['weight'], 3) }}</td>
                <td class="num">{{ smFmt($totals['stonewgt'], 3) }}</td>
            </tr></tfoot>
        </table>
        </div>

        <div class="summary-bar">
            <div class="f"><span class="lbl">Lines:</span><span class="val">{{ $totals['count'] }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Total Qty:</span><span class="val">{{ $totals['qty'] }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Total Weight:</span><span class="val">{{ smFmt($totals['weight'], 3) }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Total Stone:</span><span class="val">{{ smFmt($totals['stonewgt'], 3) }}</span></div>
        </div>
        <div class="count-bar">{{ $totals['count'] }} line(s) displayed</div>
    @endif
    </div>
</div>

<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>ReportExport.initFromTable('btnSaveAs','table','order_mail_ho_{{ $dateFrom }}_{{ $dateTo }}');</script>
</body>
</html>
