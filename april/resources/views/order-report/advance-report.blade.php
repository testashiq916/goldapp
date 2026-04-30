<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Advance Report</title>
<style>
:root{--hdr:#5c1a00;--hdr2:#8b3a1a;--border:#e6d0c3;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#c2410c}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#fff5ec 0%,#fdf8f3 40%,#f8f0e8 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(80,30,10,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700;letter-spacing:.3px}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}

.top{background:#fefaf6;border-bottom:1px solid var(--border);padding:12px 16px}
.filter-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.field{display:flex;flex-direction:column;gap:3px}
.field label{font-size:11px;font-weight:700;color:#8b5e34}
.field input,.field select{height:30px;border:1px solid #d4bc9e;border-radius:7px;padding:0 8px;font-size:12px;background:#fff;color:var(--text)}
.field input:focus,.field select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(194,65,12,.12)}
.checks label{display:flex;align-items:center;gap:4px;cursor:pointer;font-size:12px;color:#8b5e34}
.btn{height:30px;border-radius:7px;border:none;padding:0 16px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-primary:hover{opacity:.9}
.btn-outline{background:#fff;color:#8b5e34;border:1px solid #d4bc9e}
.btn-outline:hover{background:#fef6ed}
.btn-save{background:#e6f8ec;color:#1b5b31;border:1px solid #2a7a42}
.btn-save:hover{background:#d0f0da}

.content{padding:14px 16px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:8px;display:flex;justify-content:space-between;align-items:center}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #d4bc9e;border-radius:10px;margin:10px 0}

.tbl-wrap{max-height:68vh;overflow:auto;border:1px solid var(--border);border-radius:8px}
table{width:100%;border-collapse:collapse;font-size:12px}
th{position:sticky;top:0;background:linear-gradient(180deg,#7a3300,#5c2800);color:#fde8cc;padding:6px 8px;text-align:left;z-index:2;white-space:nowrap;font-size:11px;font-weight:600}
th.num,td.num{text-align:right}
th.adv{background:linear-gradient(180deg,#1b5b31,#15472a);color:#c8f5d4}
td{border-bottom:1px solid #f5ece3;padding:5px 8px;white-space:nowrap}
tr:hover td{background:#fefaf6}
tfoot td{position:sticky;bottom:0;background:#fdf0e0;font-weight:700;color:#5c2800;border-top:2px solid #d4bc9e;z-index:2}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700}
.badge-p{background:#fef3c7;color:#92400e}.badge-r{background:#d1fae5;color:#065f46}

.summary-bar{margin-top:12px;padding:12px 16px;background:linear-gradient(135deg,#5c1a00,#8b3a1a);border-radius:10px;display:flex;gap:20px;flex-wrap:wrap;align-items:center;color:#fff}
.summary-bar .f{display:flex;gap:6px;align-items:baseline}
.summary-bar .lbl{font-size:10px;font-weight:600;color:rgba(255,255,255,.7)}
.summary-bar .val{font-size:13px;font-weight:700;font-variant-numeric:tabular-nums}
.summary-bar .divider{width:1px;height:22px;background:rgba(255,255,255,.2)}
.summary-bar .total-block{margin-left:auto;background:rgba(255,255,255,.12);padding:4px 14px;border-radius:8px}
.summary-bar .total-block .val{font-size:15px}
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
    function arFmt(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function arDate(?string $d): string {
        if (!$d) return '';
        $ts = strtotime($d);
        return $ts ? date('d/m/Y', $ts) : $d;
    }
@endphp

<div class="window">
    <div class="titlebar">
        <h1>Order Advance Report</h1>
        <span class="today">{{ date('d/m/Y') }}</span>
    </div>

    <div class="top">
        <form method="GET" class="filter-row" id="reportForm">
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
            <div class="field">
                <label>Salesman</label>
                <select name="smcode" style="min-width:120px">
                    <option value="">All</option>
                    @foreach ($salesmen as $sm)
                    <option value="{{ $sm->code }}" {{ $smcode === $sm->code ? 'selected' : '' }}>{{ $sm->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="justify-content:flex-end"><div class="checks"><label><input type="checkbox" name="sm_filter" {{ $smFilter ? 'checked' : '' }}> Filter SM</label></div></div>
            <div class="field">
                <label>Counter</label>
                <select name="counter" style="min-width:100px">
                    <option value="">All</option>
                    @foreach ($counters as $c)
                    <option value="{{ $c->code }}" {{ $counterCode === $c->code ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="justify-content:flex-end"><div class="checks"><label><input type="checkbox" name="counter_filter" {{ $counterFilter ? 'checked' : '' }}> Filter Ctr</label></div></div>
            <div style="display:flex;gap:6px;align-items:flex-end">
                <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
                <button type="button" class="btn btn-outline" onclick="window.print()">Print</button>
                <button type="button" class="btn btn-save" id="btnSaveAs">Save As</button>
            </div>
        </form>
    </div>

    <div class="content">
    @if (!$showData)
        <div class="hint">Select date range and click <strong>Show</strong> to view order advance details.</div>
    @elseif (empty($rows))
        <div class="hint">No orders found for the selected criteria.</div>
    @else
        <div class="info-bar">
            <span>{{ arDate($dateFrom) }} &mdash; {{ arDate($dateTo) }}
                @if ($filter !== 'All') &middot; {{ $filter }} @endif
            </span>
            <span>{{ count($rows) }} order(s)</span>
        </div>

        <div class="tbl-wrap">
        <table>
            <thead><tr>
                <th>Order No</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>SM</th>
                <th class="num">Rate</th>
                <th class="adv num">Cash Adv</th>
                <th class="adv num">Ex. Amt</th>
                <th class="adv num">SRet Amt</th>
                <th class="adv num">Gold Adv</th>
                <th class="adv num">Adv After</th>
                <th class="adv num">Total Adv</th>
            </tr></thead>
            <tbody>
                @foreach ($rows as $r)
                <tr>
                    <td>{{ $r['ordno'] }}</td>
                    <td>{{ arDate($r['tdate']) }}</td>
                    <td>{{ $r['custname'] }}</td>
                    <td>{{ arDate($r['duedate'] ?? '') }}</td>
                    <td>@if((int)$r['status']===1)<span class="badge badge-p">Pending</span>@else<span class="badge badge-r">Returned</span>@endif</td>
                    <td>{{ $r['smname'] ?? $r['smcode'] }}</td>
                    <td class="num">{{ arFmt((float)$r['rate']) }}</td>
                    <td class="num">{{ arFmt((float)$r['advance']) }}</td>
                    <td class="num">{{ arFmt((float)$r['eamt']) }}</td>
                    <td class="num">{{ arFmt((float)$r['sretamt']) }}</td>
                    <td class="num">{{ arFmt((float)$r['gadvance'], 3) }}</td>
                    <td class="num">{{ arFmt((float)$r['advaft']) }}</td>
                    <td class="num" style="font-weight:700">{{ arFmt((float)$r['totadv']) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot><tr>
                <td colspan="7">Total ({{ count($rows) }} orders)</td>
                <td class="num">{{ arFmt($totals['advance']) }}</td>
                <td class="num">{{ arFmt($totals['eamt']) }}</td>
                <td class="num">{{ arFmt($totals['sretamt']) }}</td>
                <td class="num">{{ arFmt($totals['gadvance'], 3) }}</td>
                <td class="num">{{ arFmt($totals['advaft']) }}</td>
                <td class="num">{{ arFmt($totals['total']) }}</td>
            </tr></tfoot>
        </table>
        </div>

        <div class="summary-bar">
            <div class="f"><span class="lbl">Cash Adv:</span><span class="val">{{ arFmt($totals['advance']) }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Ex. Amt:</span><span class="val">{{ arFmt($totals['eamt']) }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">SRet:</span><span class="val">{{ arFmt($totals['sretamt']) }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Adv After:</span><span class="val">{{ arFmt($totals['advaft']) }}</span></div>
            <div class="total-block"><div class="f"><span class="lbl">Total Advance:</span><span class="val">{{ arFmt($totals['total']) }}</span></div></div>
        </div>
        <div class="count-bar">{{ count($rows) }} order(s) displayed</div>
    @endif
    </div>
</div>

<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>
ReportExport.initFromTable('btnSaveAs', 'table',
  'order_advance_{{ $dateFrom }}_{{ $dateTo }}');
</script>
</body>
</html>
