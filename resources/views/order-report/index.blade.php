<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Entry Report</title>
<style>
:root{--hdr:#1e3a5f;--hdr2:#2d5f8a;--border:#d7dfeb;--bg:#f3f6fb;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#2563eb}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#eef3ff 0%,#f4f7fb 40%,#edf2f8 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(33,52,89,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700;letter-spacing:.3px}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}

/* ─── Filter bar ─── */
.top{background:#f9fbff;border-bottom:1px solid var(--border);padding:12px 16px}
.filters{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.field{display:flex;flex-direction:column;gap:3px}
.field label{font-size:11px;font-weight:700;color:#3b5d86}
.field input,.field select{height:30px;border:1px solid #bfd0e6;border-radius:7px;padding:0 8px;font-size:12px;background:#fff;color:var(--text)}
.field input:focus,.field select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.field input[type="text"]{text-transform:uppercase}
.checks{display:flex;align-items:center;gap:4px;font-size:12px;color:#395980}
.checks label{display:flex;align-items:center;gap:4px;cursor:pointer}
.btn{height:30px;border-radius:7px;border:none;padding:0 16px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-primary:hover{opacity:.9}
.btn-outline{background:#fff;color:#375b84;border:1px solid #bfd0e6}
.btn-outline:hover{background:#f0f5ff}
.btn-green{background:#e6f8ec;color:#1b5b31;border:1px solid #2a7a42}
.btn-green:hover{background:#d0f0da}

/* ─── Content area ─── */
.content{padding:14px 16px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:10px;display:flex;justify-content:space-between;align-items:center}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #c7d4e4;border-radius:10px;margin:10px 0}

/* ─── Order cards ─── */
.order-card{border:1px solid var(--border);border-radius:10px;margin-bottom:14px;background:#fff;overflow:hidden;box-shadow:0 2px 8px rgba(33,52,89,.05);page-break-inside:avoid}
.order-hdr{background:linear-gradient(135deg,#f0f5ff,#e8eef8);padding:10px 14px;border-bottom:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px 20px;font-size:12px}
.order-hdr .f{display:flex;gap:5px;align-items:baseline}
.order-hdr .lbl{font-weight:700;color:#3b5d86;white-space:nowrap;font-size:11px}
.order-hdr .val{color:#1e3a5f;font-weight:600}
.badge{display:inline-block;padding:2px 10px;border-radius:10px;font-size:10px;font-weight:700;letter-spacing:.3px}
.badge-pending{background:#fef3c7;color:#92400e;border:1px solid #f59e0b}
.badge-returned{background:#d1fae5;color:#065f46;border:1px solid #10b981}

/* ─── Detail tables ─── */
.detail-section{padding:8px 14px}
.section-label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;display:flex;align-items:center;gap:6px}
.section-label::after{content:'';flex:1;height:1px;background:#e2e8f0}
.dtbl{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:4px}
.dtbl th{background:linear-gradient(180deg,#364891,#2d3d7b);color:#dde6ff;padding:5px 8px;text-align:left;font-size:11px;font-weight:600;white-space:nowrap}
.dtbl th.items-hdr{background:linear-gradient(180deg,#364891,#2d3d7b)}
.dtbl th.rcpt-hdr{background:linear-gradient(180deg,#1b5b31,#15472a);color:#c8f5d4}
.dtbl th.gadv-hdr{background:linear-gradient(180deg,#7a5c00,#614a00);color:#fff5d4}
.dtbl td{padding:4px 8px;border-bottom:1px solid #edf2f8}
.dtbl tr:hover td{background:#f8faff}
.dtbl .num{text-align:right;font-variant-numeric:tabular-nums}
.dtbl .ctr{text-align:center}

/* ─── Order footer ─── */
.order-ftr{background:linear-gradient(135deg,#f8faff,#edf2f8);padding:10px 14px;border-top:1px solid var(--border);display:grid;grid-template-columns:repeat(4,1fr);gap:6px 16px;font-size:12px}
.order-ftr .f{display:flex;gap:5px;align-items:baseline}
.order-ftr .lbl{font-weight:600;color:#64748b;white-space:nowrap;font-size:11px}
.order-ftr .val{font-variant-numeric:tabular-nums;color:#1e3a5f}
.order-ftr .val-bold{font-variant-numeric:tabular-nums;font-weight:700;color:#1e3a5f;font-size:13px}

/* ─── Summary bar ─── */
.summary-bar{margin-top:12px;padding:12px 16px;background:linear-gradient(135deg,#1e3a5f,#2d5f8a);border-radius:10px;display:flex;gap:28px;flex-wrap:wrap;align-items:center;color:#fff}
.summary-bar .f{display:flex;gap:6px;align-items:baseline}
.summary-bar .lbl{font-size:11px;font-weight:600;color:rgba(255,255,255,.7)}
.summary-bar .val{font-size:14px;font-weight:700;font-variant-numeric:tabular-nums}
.summary-bar .divider{width:1px;height:24px;background:rgba(255,255,255,.2)}
.summary-bar .total-block{margin-left:auto;display:flex;gap:6px;align-items:baseline;background:rgba(255,255,255,.12);padding:4px 14px;border-radius:8px}
.summary-bar .total-block .val{font-size:16px}

.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:8px}

@media print{
    .top,.titlebar .today{display:none}
    body{background:#fff}
    .window{margin:0;box-shadow:none;border-radius:0}
    .content{padding:6px}
    .order-card{box-shadow:none;border-radius:0;border:1px solid #333}
    .summary-bar{background:#f0f0f0;color:#000;border-radius:0}
    .summary-bar .lbl{color:#555}
}
body{font-size:15px}
.titlebar h1{font-size:18px}
.field label{font-size:13px}
.field input,.field select{font-size:14px;height:34px}
.btn{font-size:14px;height:34px}
.order-hdr{font-size:14px}
.order-hdr .lbl{font-size:13px}
.dtbl{font-size:14px}
.dtbl th{font-size:13px;padding:6px 8px}
.dtbl td{padding:5px 8px}
.order-ftr{font-size:14px}
.order-ftr .lbl{font-size:13px}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>

@php
    function oeFmt(float $n, int $d = 2): string {
        return number_format($n, $d, '.', ',');
    }
    function oeFmtBlank(float $n, int $d = 2): string {
        return $n == 0 ? '' : number_format($n, $d, '.', ',');
    }
    function oeDate(?string $d): string {
        if (!$d) return '';
        $ts = strtotime($d);
        return $ts ? date('d/m/Y', $ts) : $d;
    }
@endphp

<div class="window">
    {{-- ─── Title bar ─── --}}
    <div class="titlebar">
        <h1>Order Entry Report</h1>
        <span class="today">{{ date('d/m/Y') }}</span>
    </div>

    {{-- ─── Filter bar ─── --}}
    <div class="top">
        <form method="GET" class="filters" id="reportForm">
            <div class="field">
                <label>Date From</label>
                <input type="date" name="date1" value="{{ $dateFrom }}">
            </div>
            <div class="field">
                <label>Date To</label>
                <input type="date" name="date2" value="{{ $dateTo }}">
            </div>
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
                <select name="smcode" style="min-width:140px">
                    <option value="">All Salesmen</option>
                    @foreach ($salesmen as $sm)
                        <option value="{{ $sm->code }}" {{ $smcode === $sm->code ? 'selected' : '' }}>{{ $sm->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="justify-content:flex-end">
                <div class="checks">
                    <label><input type="checkbox" name="sm_filter" {{ $smFilter ? 'checked' : '' }}> Filter SM</label>
                </div>
            </div>
            <div class="field">
                <label>Order No</label>
                <input type="text" name="ordno" value="{{ $ordno }}" style="width:100px" placeholder="Search...">
            </div>
            <div style="display:flex;gap:6px;align-items:flex-end">
                <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
                <button type="button" class="btn btn-outline" onclick="window.print()">Print</button>
                <button type="button" class="btn btn-green" id="btnSaveAs">Save As</button>
            </div>
        </form>
    </div>

    {{-- ─── Content ─── --}}
    <div class="content">
    @if (!$showData)
        <div class="hint">Select date range and click <strong>Show</strong> to generate the order entry report.</div>
    @elseif (empty($orders))
        <div class="hint">No orders found for the selected criteria.</div>
    @else
        <div class="info-bar">
            <span>{{ oeDate($dateFrom) }} &mdash; {{ oeDate($dateTo) }}
                @if ($filter !== 'All') &middot; {{ $filter }} @endif
            </span>
            <span>{{ count($orders) }} order(s)</span>
        </div>

        @foreach ($orders as $order)
        <div class="order-card">
            {{-- ─── Order Header ─── --}}
            <div class="order-hdr">
                <div class="f"><span class="lbl">Order No:</span> <span class="val">{{ $order['ordno'] }}</span></div>
                <div class="f"><span class="lbl">Date:</span> <span class="val">{{ oeDate($order['tdate']) }}</span></div>
                <div class="f">
                    <span class="lbl">Status:</span>
                    @if ((int) $order['status'] === 1)
                        <span class="badge badge-pending">Pending</span>
                    @else
                        <span class="badge badge-returned">Returned</span>
                    @endif
                </div>
                <div class="f"><span class="lbl">Customer:</span> <span class="val" style="font-weight:700">{{ $order['custname'] }}</span></div>
                <div class="f"><span class="lbl">Due Date:</span> <span class="val">{{ oeDate($order['duedate'] ?? '') }}</span></div>
                <div class="f"><span class="lbl">SM:</span> <span class="val">{{ $order['smname'] ?? '' }}</span></div>
                @if (!empty($order['addr']))
                <div class="f"><span class="lbl">Address:</span> <span class="val">{{ $order['addr'] }}</span></div>
                @endif
                @if (!empty($order['duedate_org']))
                <div class="f"><span class="lbl">Org Due Date:</span> <span class="val">{{ oeDate($order['duedate_org']) }}</span></div>
                @endif
            </div>

            {{-- ─── Order Detail Items ─── --}}
            @if (!empty($order['items']))
            <div class="detail-section">
                <div class="section-label">Order Items</div>
                <table class="dtbl">
                    <thead>
                        <tr>
                            <th class="items-hdr ctr">#</th>
                            <th class="items-hdr">Code</th>
                            <th class="items-hdr">Item Name</th>
                            <th class="items-hdr num">Qty</th>
                            <th class="items-hdr num">Weight</th>
                            <th class="items-hdr num">Wastage</th>
                            <th class="items-hdr num">St.Wgt</th>
                            <th class="items-hdr num">St.Price</th>
                            <th class="items-hdr num">M.Charge</th>
                            <th class="items-hdr num">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order['items'] as $item)
                        <tr>
                            <td class="ctr">{{ $item['sno'] ?? $loop->iteration }}</td>
                            <td>{{ $item['code'] }}</td>
                            <td>{{ $item['itemname'] ?? '' }}</td>
                            <td class="num">{{ (int)($item['qty'] ?? 0) }}</td>
                            <td class="num">{{ oeFmt((float)($item['weight'] ?? 0), 3) }}</td>
                            <td class="num">{{ oeFmt((float)($item['wastage'] ?? 0), 3) }}</td>
                            <td class="num">{{ oeFmt((float)($item['stonewgt'] ?? 0), 3) }}</td>
                            <td class="num">{{ oeFmt((float)($item['stoneprice'] ?? 0)) }}</td>
                            <td class="num">{{ oeFmt((float)($item['mcharge'] ?? 0)) }}</td>
                            <td class="num">{{ oeFmt((float)($item['amount'] ?? 0)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- ─── Advance Receipts ─── --}}
            @if (!empty($order['receipts']))
            <div class="detail-section">
                <div class="section-label">All Receipts</div>
                <table class="dtbl">
                    <thead>
                        <tr>
                            <th class="rcpt-hdr">Date</th>
                            <th class="rcpt-hdr">Doc No</th>
                            <th class="rcpt-hdr ctr">Type</th>
                            <th class="rcpt-hdr num">Rate</th>
                            <th class="rcpt-hdr num">Weight</th>
                            <th class="rcpt-hdr num">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order['receipts'] as $rcpt)
                        <tr>
                            <td>{{ oeDate($rcpt['tdate']) }}</td>
                            <td>{{ $rcpt['docno'] ?? '' }}</td>
                            <td class="ctr">{{ $rcpt['ttype'] ?? '' }}</td>
                            <td class="num">{{ oeFmt((float)($rcpt['rate'] ?? 0)) }}</td>
                            <td class="num">{{ oeFmt((float)($rcpt['wgt'] ?? 0), 3) }}</td>
                            <td class="num">{{ oeFmt((float)($rcpt['amount'] ?? 0)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- ─── Gold Advance Items ─── --}}
            @if (!empty($order['goldadv']))
            <div class="detail-section">
                <div class="section-label">Gold Advance Items</div>
                <table class="dtbl">
                    <thead>
                        <tr>
                            <th class="gadv-hdr ctr">#</th>
                            <th class="gadv-hdr">Code</th>
                            <th class="gadv-hdr">Item Name</th>
                            <th class="gadv-hdr num">Qty</th>
                            <th class="gadv-hdr num">Weight</th>
                            <th class="gadv-hdr num">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order['goldadv'] as $ga)
                        <tr>
                            <td class="ctr">{{ $ga['sno'] ?? $loop->iteration }}</td>
                            <td>{{ $ga['code'] }}</td>
                            <td>{{ $ga['itemname'] ?? '' }}</td>
                            <td class="num">{{ (int)($ga['qty'] ?? 0) }}</td>
                            <td class="num">{{ oeFmt((float)($ga['weight'] ?? 0), 3) }}</td>
                            <td class="num">{{ oeFmt((float)($ga['cost'] ?? 0)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- ─── Order Footer ─── --}}
            <div class="order-ftr">
                <div class="f"><span class="lbl">Est. Amount:</span> <span class="val">{{ oeFmt((float)$order['billamt']) }}</span></div>
                <div class="f"><span class="lbl">Gold Advance:</span> <span class="val">{{ oeFmt((float)$order['gadvance'], 3) }}</span></div>
                <div class="f"><span class="lbl">SRet Amt:</span> <span class="val">{{ oeFmt((float)$order['sretamt']) }}</span></div>
                <div class="f"><span class="lbl">Refund:</span> <span class="val">{{ oeFmt((float)$order['refund']) }}</span></div>

                <div class="f"><span class="lbl">Ex. Amt:</span> <span class="val">{{ oeFmt((float)$order['eamt']) }}</span></div>
                <div class="f"><span class="lbl">Cash Advance:</span> <span class="val-bold">{{ oeFmt((float)$order['advance']) }}</span></div>
                <div></div>
                <div class="f"><span class="lbl">Total Advance:</span> <span class="val-bold">{{ oeFmt($order['totadv']) }}</span></div>
            </div>
        </div>
        @endforeach

        {{-- ─── Summary ─── --}}
        <div class="summary-bar">
            <div class="f"><span class="lbl">Total Cash Adv:</span> <span class="val">{{ oeFmt($summary['tcashadv']) }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Total Ex.Adv:</span> <span class="val">{{ oeFmt($summary['texadv']) }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Total Ret.Adv:</span> <span class="val">{{ oeFmt($summary['tretadv']) }}</span></div>
            <div class="total-block"><span class="lbl">Total Advance:</span> <span class="val">{{ oeFmt($summary['ttotal']) }}</span></div>
        </div>

        <div class="count-bar">{{ count($orders) }} order(s) displayed</div>
    @endif
    </div>
</div>

<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>
ReportExport.initFromTable('btnSaveAs', '.order-card',
  'order_entries_{{ $dateFrom }}_{{ $dateTo }}');
</script>
</body>
</html>
