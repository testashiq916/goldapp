<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pending Order Register</title>
<style>
:root{--hdr:#1a4731;--hdr2:#2d7a52;--border:#d7dfeb;--bg:#f3f6fb;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#16a34a}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#eefff3 0%,#f4fbf7 40%,#edf8f2 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(20,60,40,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700;letter-spacing:.3px}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}

/* ─── Filter bar ─── */
.top{background:#f9fcfa;border-bottom:1px solid var(--border);padding:12px 16px}
.filter-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.filter-row+.filter-row{margin-top:8px}
.field{display:flex;flex-direction:column;gap:3px}
.field label{font-size:11px;font-weight:700;color:#3b6d50}
.field input,.field select{height:30px;border:1px solid #b5d4c4;border-radius:7px;padding:0 8px;font-size:12px;background:#fff;color:var(--text)}
.field input:focus,.field select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(22,163,74,.12)}
.field input[type="text"]{text-transform:uppercase}
.checks{display:flex;align-items:center;gap:4px;font-size:12px;color:#3b6d50}
.checks label{display:flex;align-items:center;gap:4px;cursor:pointer}
.btn{height:30px;border-radius:7px;border:none;padding:0 16px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-primary:hover{opacity:.9}
.btn-outline{background:#fff;color:#3b6d50;border:1px solid #b5d4c4}
.btn-outline:hover{background:#f0faf4}
.btn-save{background:#e6f0ff;color:#1e3a5f;border:1px solid #2a6398}
.btn-save:hover{background:#d0e6ff}

/* ─── Content area ─── */
.content{padding:14px 16px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:8px;display:flex;justify-content:space-between;align-items:center}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #c7d4e4;border-radius:10px;margin:10px 0}

/* ─── Table ─── */
.tbl-wrap{max-height:68vh;overflow:auto;border:1px solid var(--border);border-radius:8px}
table{width:100%;border-collapse:collapse;font-size:12px}
th{position:sticky;top:0;background:linear-gradient(180deg,#1f5c3d,#174a31);color:#c8f5d4;padding:6px 8px;text-align:left;z-index:2;white-space:nowrap;font-size:11px;font-weight:600}
th.num,td.num{text-align:right}
td{border-bottom:1px solid #e8f0ec;padding:5px 8px;white-space:nowrap}
tr:hover td{background:#f2faf5}
tfoot td{position:sticky;bottom:0;background:#e6f5ec;font-weight:700;color:#174a31;border-top:2px solid #b5d4c4;z-index:2}

/* SM Group */
.sm-group-hdr td{background:linear-gradient(135deg,#e8f5ec,#d4eeda);font-weight:700;color:#174a31;font-size:12px;border-top:2px solid #a0d0b0}
.sm-group-ftr td{background:#edf5f0;font-weight:700;color:#2d5f3e;border-top:1px solid #b5d4c4}

/* Overdue highlight */
tr.overdue td{background:#fef3f2}
tr.overdue:hover td{background:#fee2e2}
.badge-overdue{display:inline-block;padding:1px 6px;border-radius:10px;font-size:9px;font-weight:700;background:#fee2e2;color:#991b1b;margin-left:4px}

/* ─── Summary bar ─── */
.summary-bar{margin-top:12px;padding:12px 16px;background:linear-gradient(135deg,#1a4731,#2d7a52);border-radius:10px;display:flex;gap:28px;flex-wrap:wrap;align-items:center;color:#fff}
.summary-bar .f{display:flex;gap:6px;align-items:baseline}
.summary-bar .lbl{font-size:11px;font-weight:600;color:rgba(255,255,255,.7)}
.summary-bar .val{font-size:14px;font-weight:700;font-variant-numeric:tabular-nums}
.summary-bar .divider{width:1px;height:24px;background:rgba(255,255,255,.2)}
.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:8px}

@media print{
    .top{display:none}
    body{background:#fff}
    .window{margin:0;box-shadow:none;border-radius:0}
    .content{padding:6px}
    .summary-bar{background:#f0f0f0;color:#000;border-radius:0}
    .summary-bar .lbl{color:#555}
    th{position:static}
    tfoot td{position:static}
    .tbl-wrap{max-height:none;overflow:visible;border:0}
}
</style>
@include('partials.print-layout-head')
</head>
<body>

@php
    function prFmt(float $n, int $d = 2): string {
        return number_format($n, $d, '.', ',');
    }
    function prDate(?string $d): string {
        if (!$d) return '';
        $ts = strtotime($d);
        return $ts ? date('d/m/Y', $ts) : $d;
    }
    $today = date('Y-m-d');
@endphp

<div class="window">
    <div class="titlebar">
        <h1>Pending Order Register</h1>
        <span class="today">{{ date('d/m/Y') }}</span>
    </div>

    <div class="top">
        <form method="GET" id="reportForm">
            <div class="filter-row">
                <div class="field">
                    <label>Sort By</label>
                    <select name="sort">
                        <option value="duedate"  {{ $sortBy === 'duedate' ? 'selected' : '' }}>Due Date</option>
                        <option value="ordno"    {{ $sortBy === 'ordno' ? 'selected' : '' }}>Order No</option>
                        <option value="customer" {{ $sortBy === 'customer' ? 'selected' : '' }}>Customer</option>
                        <option value="item"     {{ $sortBy === 'item' ? 'selected' : '' }}>Item Name</option>
                    </select>
                </div>
                <div class="field">
                    <label>Item Type</label>
                    <select name="item_type">
                        @foreach(['All','Diamond','Platinum','Gold','Silver','Others','Color Stone','Watch'] as $opt)
                        <option value="{{ $opt }}" {{ $itemType === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Item Code</label>
                    <input type="text" name="item_code" value="{{ $itemCode }}" style="width:90px" placeholder="Code...">
                </div>
                <div class="field">
                    <label>Salesman</label>
                    <select name="smcode" style="min-width:130px">
                        <option value="">All</option>
                        @foreach ($salesmen as $sm)
                        <option value="{{ $sm->code }}" {{ $smcode === $sm->code ? 'selected' : '' }}>{{ $sm->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="justify-content:flex-end">
                    <div class="checks"><label><input type="checkbox" name="sm_filter" {{ $smFilter ? 'checked' : '' }}> Filter SM</label></div>
                </div>
                <div class="field">
                    <label>Counter</label>
                    <select name="counter" style="min-width:110px">
                        <option value="">All</option>
                        @foreach ($counters as $c)
                        <option value="{{ $c->code }}" {{ $counterCode === $c->code ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="justify-content:flex-end">
                    <div class="checks"><label><input type="checkbox" name="counter_filter" {{ $counterFilter ? 'checked' : '' }}> Filter Ctr</label></div>
                </div>
            </div>
            <div class="filter-row">
                <div class="field">
                    <label>Party Code</label>
                    <input type="text" name="custcode" value="{{ $custcode }}" style="width:90px" placeholder="Code...">
                </div>
                <div class="field" style="justify-content:flex-end">
                    <div class="checks"><label><input type="checkbox" name="sm_wise" {{ $smWise ? 'checked' : '' }}> SM Wise</label></div>
                </div>
                <div class="field" style="justify-content:flex-end">
                    <div class="checks"><label><input type="checkbox" name="duedate_based" id="cbDuedate" {{ $duedateBased ? 'checked' : '' }}> Due Date Based</label></div>
                </div>
                <div class="field duedate-fields" style="display:{{ $duedateBased ? 'flex' : 'none' }}">
                    <label>Date From</label>
                    <input type="date" name="date1" value="{{ $dateFrom }}">
                </div>
                <div class="field duedate-fields" style="display:{{ $duedateBased ? 'flex' : 'none' }}">
                    <label>Date To</label>
                    <input type="date" name="date2" value="{{ $dateTo }}">
                </div>
                <div style="display:flex;gap:6px;align-items:flex-end">
                    <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
                    <button type="button" class="btn btn-outline" onclick="window.print()">Print</button>
                    <button type="button" class="btn btn-save" id="btnSaveAs">Save As</button>
                </div>
            </div>
        </form>
    </div>

    <div class="content">
    @if (!$showData)
        <div class="hint">Click <strong>Show</strong> to load all pending orders.</div>
    @elseif (empty($rows))
        <div class="hint">No pending orders found for the selected criteria.</div>
    @elseif ($smWise && !empty($smGroups))
        {{-- ═══ SM-Wise Grouped View ═══ --}}
        <div class="info-bar">
            <span>Pending Orders &mdash; SM Wise
                @if ($duedateBased) &middot; Due {{ prDate($dateFrom) }} to {{ prDate($dateTo) }} @endif
            </span>
            <span>{{ count($rows) }} item line(s)</span>
        </div>

        <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>Order No</th>
                    <th>Ord. Date</th>
                    <th>Customer</th>
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th class="num">Weight</th>
                    <th>Del. Date</th>
                    <th>Particulars</th>
                    <th class="num">Advance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($smGroups as $smKey => $group)
                <tr class="sm-group-hdr"><td colspan="9">SM: {{ $group['smname'] ?: '(none)' }}</td></tr>
                @php $prevSlno = null; @endphp
                @foreach ($group['rows'] as $r)
                @php $isOverdue = ($r['duedate'] && $r['duedate'] < $today); @endphp
                <tr class="{{ $isOverdue ? 'overdue' : '' }}">
                    <td>{{ $r['slno'] !== $prevSlno ? $r['ordno'] : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? prDate($r['tdate']) : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? $r['custname'] : '' }}</td>
                    <td>{{ $r['itemname'] }}</td>
                    <td class="num">{{ (int)$r['qty'] }}</td>
                    <td class="num">{{ prFmt((float)$r['weight'], 3) }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? prDate($r['duedate'] ?? '') : '' }}@if($isOverdue && $r['slno'] !== $prevSlno)<span class="badge-overdue">overdue</span>@endif</td>
                    <td>{{ $r['part'] ?? '' }}</td>
                    <td class="num">{{ $r['slno'] !== $prevSlno ? prFmt((float)$r['advance']) : '' }}</td>
                </tr>
                @php $prevSlno = $r['slno']; @endphp
                @endforeach
                <tr class="sm-group-ftr">
                    <td colspan="4">SM Total</td>
                    <td class="num">{{ $group['totqty'] }}</td>
                    <td class="num">{{ prFmt($group['totwgt'], 3) }}</td>
                    <td colspan="2"></td>
                    <td class="num">{{ prFmt($group['totadv']) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4">Grand Total</td>
                    <td class="num">{{ $totals['qty'] }}</td>
                    <td class="num">{{ prFmt($totals['weight'], 3) }}</td>
                    <td colspan="2"></td>
                    <td class="num">{{ prFmt($totals['advance']) }}</td>
                </tr>
            </tfoot>
        </table>
        </div>

        <div class="summary-bar">
            <div class="f"><span class="lbl">Total Qty:</span> <span class="val">{{ $totals['qty'] }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Total Weight:</span> <span class="val">{{ prFmt($totals['weight'], 3) }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Total Advance:</span> <span class="val">{{ prFmt($totals['advance']) }}</span></div>
        </div>
        <div class="count-bar">{{ count($rows) }} line(s) &middot; {{ count($smGroups) }} salesman group(s)</div>

    @else
        {{-- ═══ Flat View ═══ --}}
        <div class="info-bar">
            <span>Pending Orders
                @if ($duedateBased) &middot; Due {{ prDate($dateFrom) }} to {{ prDate($dateTo) }} @endif
            </span>
            <span>{{ count($rows) }} item line(s)</span>
        </div>

        <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>Order No</th>
                    <th>Ord. Date</th>
                    <th>Customer</th>
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th class="num">Weight</th>
                    <th class="num">St.Wgt</th>
                    <th class="num">St.Amt</th>
                    <th>Del. Date</th>
                    <th>Particulars</th>
                    <th>Qlty</th>
                    <th class="num">Advance</th>
                    <th>SM</th>
                    <th>Smith</th>
                    <th>Stage</th>
                </tr>
            </thead>
            <tbody>
                @php $prevSlno = null; @endphp
                @foreach ($rows as $r)
                @php $isOverdue = ($r['duedate'] && $r['duedate'] < $today); @endphp
                <tr class="{{ $isOverdue ? 'overdue' : '' }}">
                    <td>{{ $r['slno'] !== $prevSlno ? $r['ordno'] : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? prDate($r['tdate']) : '' }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? $r['custname'] : '' }}</td>
                    <td>{{ $r['itemname'] }}</td>
                    <td class="num">{{ (int)$r['qty'] }}</td>
                    <td class="num">{{ prFmt((float)$r['weight'], 3) }}</td>
                    <td class="num">{{ prFmt((float)($r['stonewgt'] ?? 0), 3) }}</td>
                    <td class="num">{{ prFmt((float)($r['stoneprice'] ?? 0)) }}</td>
                    <td>{{ $r['slno'] !== $prevSlno ? prDate($r['duedate'] ?? '') : '' }}@if($isOverdue && $r['slno'] !== $prevSlno)<span class="badge-overdue">overdue</span>@endif</td>
                    <td>{{ $r['part'] ?? '' }}</td>
                    <td>{{ $r['iqtype'] ?? '' }}</td>
                    <td class="num">{{ $r['slno'] !== $prevSlno ? prFmt((float)$r['advance']) : '' }}</td>
                    <td>{{ $r['smcode'] ?? '' }}</td>
                    <td>{{ $r['smith'] ?? '' }}</td>
                    <td>{{ $r['stage'] ?? '' }}</td>
                </tr>
                @php $prevSlno = $r['slno']; @endphp
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4">Total</td>
                    <td class="num">{{ $totals['qty'] }}</td>
                    <td class="num">{{ prFmt($totals['weight'], 3) }}</td>
                    <td colspan="5"></td>
                    <td class="num">{{ prFmt($totals['advance']) }}</td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
        </div>

        <div class="summary-bar">
            <div class="f"><span class="lbl">Total Qty:</span> <span class="val">{{ $totals['qty'] }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Total Weight:</span> <span class="val">{{ prFmt($totals['weight'], 3) }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Total Advance:</span> <span class="val">{{ prFmt($totals['advance']) }}</span></div>
        </div>
        <div class="count-bar">{{ count($rows) }} line(s) displayed</div>
    @endif
    </div>
</div>

<script>
document.getElementById('cbDuedate').addEventListener('change', function(){
    var show = this.checked ? 'flex' : 'none';
    document.querySelectorAll('.duedate-fields').forEach(function(el){ el.style.display = show; });
});
</script>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>
ReportExport.initFromTable('btnSaveAs', 'table',
  'pending_register_{{ date("Y-m-d") }}');
</script>
</body>
</html>
