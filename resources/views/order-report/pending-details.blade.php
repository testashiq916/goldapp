<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pending Order Details</title>
<style>
:root{--hdr:#7a3300;--hdr2:#b05000;--border:#e6d5c3;--bg:#fdf8f3;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#c2410c}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#fff5ec 0%,#fdf8f3 40%,#f8f0e8 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(80,40,10,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700;letter-spacing:.3px}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}

.top{background:#fefaf6;border-bottom:1px solid var(--border);padding:12px 16px}
.filter-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.filter-row+.filter-row{margin-top:8px}
.field{display:flex;flex-direction:column;gap:3px}
.field label{font-size:11px;font-weight:700;color:#8b5e34}
.field input,.field select{height:30px;border:1px solid #d4bc9e;border-radius:7px;padding:0 8px;font-size:12px;background:#fff;color:var(--text)}
.field input:focus,.field select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(194,65,12,.12)}
.field input[type="text"]{text-transform:uppercase}
.checks label{display:flex;align-items:center;gap:4px;cursor:pointer;font-size:12px;color:#8b5e34}
.btn{height:30px;border-radius:7px;border:none;padding:0 16px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-primary:hover{opacity:.9}
.btn-outline{background:#fff;color:#8b5e34;border:1px solid #d4bc9e}
.btn-outline:hover{background:#fef6ed}
.btn-save{background:#e6f0ff;color:#1e3a5f;border:1px solid #2a6398}
.btn-save:hover{background:#d0e6ff}

.content{padding:14px 16px;max-height:calc(100vh - 160px);overflow:auto}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:10px;display:flex;justify-content:space-between;align-items:center}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #d4bc9e;border-radius:10px;margin:10px 0}

/* ─── Order cards ─── */
.order-card{border:1px solid var(--border);border-radius:10px;margin-bottom:14px;background:#fff;overflow:hidden;box-shadow:0 2px 8px rgba(80,40,10,.05);page-break-inside:avoid}
.order-hdr{background:linear-gradient(135deg,#fef6ed,#f5e8d8);padding:10px 14px;border-bottom:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px 20px;font-size:12px}
.order-hdr .f{display:flex;gap:5px;align-items:baseline}
.order-hdr .lbl{font-weight:700;color:#8b5e34;white-space:nowrap;font-size:11px}
.order-hdr .val{color:#5c3310;font-weight:600}
.badge{display:inline-block;padding:2px 10px;border-radius:10px;font-size:10px;font-weight:700;letter-spacing:.3px}
.badge-pending{background:#fef3c7;color:#92400e;border:1px solid #f59e0b}
.badge-returned{background:#d1fae5;color:#065f46;border:1px solid #10b981}

.detail-section{padding:8px 14px}
.section-label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;display:flex;align-items:center;gap:6px}
.section-label::after{content:'';flex:1;height:1px;background:#e6d5c3}
.dtbl{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:4px}
.dtbl th{background:linear-gradient(180deg,#8b5e34,#7a4f28);color:#fde8cc;padding:5px 8px;text-align:left;font-size:11px;font-weight:600;white-space:nowrap}
.dtbl th.rcpt-hdr{background:linear-gradient(180deg,#1b5b31,#15472a);color:#c8f5d4}
.dtbl th.gadv-hdr{background:linear-gradient(180deg,#7a5c00,#614a00);color:#fff5d4}
.dtbl td{padding:4px 8px;border-bottom:1px solid #f5ece3}
.dtbl tr:hover td{background:#fefaf6}
.dtbl .num{text-align:right;font-variant-numeric:tabular-nums}
.dtbl .ctr{text-align:center}
.stage-1{color:#2563eb}.stage-2{color:#d97706}.stage-3{color:#16a34a;font-weight:700}

.order-ftr{background:linear-gradient(135deg,#fefaf6,#f5ece3);padding:10px 14px;border-top:1px solid var(--border)}
.ftr-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px 16px;font-size:12px}
.ftr-grid .f{display:flex;gap:5px;align-items:baseline}
.ftr-grid .lbl{font-weight:600;color:#64748b;white-space:nowrap;font-size:11px}
.ftr-grid .val{font-variant-numeric:tabular-nums;color:#5c3310}
.ftr-grid .val-bold{font-variant-numeric:tabular-nums;font-weight:700;color:#5c3310;font-size:13px}

.summary-bar{margin-top:12px;padding:12px 16px;background:linear-gradient(135deg,#7a3300,#b05000);border-radius:10px;display:flex;gap:28px;flex-wrap:wrap;align-items:center;color:#fff}
.summary-bar .f{display:flex;gap:6px;align-items:baseline}
.summary-bar .lbl{font-size:11px;font-weight:600;color:rgba(255,255,255,.7)}
.summary-bar .val{font-size:14px;font-weight:700;font-variant-numeric:tabular-nums}
.summary-bar .divider{width:1px;height:24px;background:rgba(255,255,255,.2)}
.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:8px}

@media print{
    .top{display:none}body{background:#fff}.window{margin:0;box-shadow:none;border-radius:0}
    .content{padding:6px;max-height:none;overflow:visible}
    .order-card{box-shadow:none;border-radius:0;border:1px solid #333}
    .summary-bar{background:#f0f0f0;color:#000;border-radius:0}.summary-bar .lbl{color:#555}
}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>

@php
    function pdFmt(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function pdDate(?string $d): string {
        if (!$d) return '';
        $ts = strtotime($d);
        return $ts ? date('d/m/Y', $ts) : $d;
    }
    function stageName($s): string {
        return match((int)$s) { 1 => 'New Work', 2 => 'In Progress', 3 => 'Finished', default => '' };
    }
    function stageClass($s): string {
        return match((int)$s) { 1 => 'stage-1', 2 => 'stage-2', 3 => 'stage-3', default => '' };
    }
@endphp

<div class="window">
    <div class="titlebar">
        <h1>Pending Order Details</h1>
        <span class="today">{{ date('d/m/Y') }}</span>
    </div>

    <div class="top">
        <form method="GET" id="reportForm">
            <div class="filter-row">
                <div class="field"><label>Entry From</label><input type="date" name="date1" value="{{ $dateFrom }}"></div>
                <div class="field"><label>Entry To</label><input type="date" name="date2" value="{{ $dateTo }}"></div>
                <div class="field"><label>Due Date From</label><input type="date" name="ddate1" value="{{ $dduFrom }}"></div>
                <div class="field"><label>Due Date To</label><input type="date" name="ddate2" value="{{ $dduTo }}"></div>
            </div>
            <div class="filter-row">
                <div class="field">
                    <label>Salesman</label>
                    <select name="smcode" style="min-width:130px">
                        <option value="">All</option>
                        @foreach ($salesmen as $sm)
                        <option value="{{ $sm->code }}" {{ $smcode === $sm->code ? 'selected' : '' }}>{{ $sm->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label>Order No</label><input type="text" name="ordno" value="{{ $ordno }}" style="width:100px" placeholder="Search..."></div>
                <div class="field" style="justify-content:flex-end">
                    <div class="checks"><label><input type="checkbox" name="show_all" {{ $showAll ? 'checked' : '' }}> Show All (incl. Returned)</label></div>
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
        <div class="hint">Select date range and click <strong>Show</strong> to view pending order details.</div>
    @elseif (empty($orders))
        <div class="hint">No orders found for the selected criteria.</div>
    @else
        <div class="info-bar">
            <span>Entry {{ pdDate($dateFrom) }} &mdash; {{ pdDate($dateTo) }}
                @if ($dduFrom || $dduTo) &middot; Due {{ pdDate($dduFrom) }} to {{ pdDate($dduTo) }} @endif
                @if (!$showAll) &middot; Pending Only @endif
            </span>
            <span>{{ $summary['count'] }} order(s)</span>
        </div>

        @foreach ($orders as $order)
        <div class="order-card">
            <div class="order-hdr">
                <div class="f"><span class="lbl">Order No:</span> <span class="val">{{ $order['ordno'] }}</span></div>
                <div class="f"><span class="lbl">Date:</span> <span class="val">{{ pdDate($order['tdate']) }}</span></div>
                <div class="f">
                    <span class="lbl">Status:</span>
                    @if ((int) $order['status'] === 1)
                        <span class="badge badge-pending">Pending</span>
                    @else
                        <span class="badge badge-returned">Returned</span>
                    @endif
                </div>
                <div class="f"><span class="lbl">Customer:</span> <span class="val" style="font-weight:700">{{ $order['custname'] }}</span></div>
                <div class="f"><span class="lbl">Delivery Date:</span> <span class="val">{{ pdDate($order['duedate'] ?? '') }}</span></div>
                <div class="f"><span class="lbl">SM:</span> <span class="val">{{ $order['smname'] ?? '' }}</span></div>
                @if (!empty($order['addr']))
                <div class="f"><span class="lbl">Address:</span> <span class="val">{{ $order['addr'] }}</span></div>
                @endif
            </div>

            @if (!empty($order['items']))
            <div class="detail-section">
                <div class="section-label">Order Items</div>
                <table class="dtbl">
                    <thead><tr>
                        <th>Item Name</th><th class="num">Rate</th><th class="num">Qty</th>
                        <th class="num">Weight</th><th class="num">St.Wgt</th>
                        <th>Particular</th><th>Quality</th>
                        <th class="num">Wastage</th><th class="num">M.C.</th>
                        <th class="num">Amount</th><th>Smith</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($order['items'] as $item)
                        <tr>
                            <td>{{ $item['itemname'] ?? '' }}</td>
                            <td class="num">{{ pdFmt((float)($item['rate'] ?? 0)) }}</td>
                            <td class="num">{{ (int)($item['qty'] ?? 0) }}</td>
                            <td class="num">{{ pdFmt((float)($item['weight'] ?? 0), 3) }}</td>
                            <td class="num">{{ pdFmt((float)($item['stonewgt'] ?? 0), 3) }}</td>
                            <td>{{ $item['part'] ?? '' }}</td>
                            <td>{{ $item['iqtype'] ?? '' }}</td>
                            <td class="num">{{ pdFmt((float)($item['wastage'] ?? 0), 3) }}</td>
                            <td class="num">{{ pdFmt((float)($item['mcharge'] ?? 0)) }}</td>
                            <td class="num">{{ pdFmt((float)($item['amount'] ?? 0)) }}</td>
                            <td>{{ $item['smith'] ?? '' }}</td>
                            <td class="{{ stageClass($item['stage'] ?? 0) }}">{{ stageName($item['stage'] ?? 0) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if (!empty($order['receipts']))
            <div class="detail-section">
                <div class="section-label">All Receipts</div>
                <table class="dtbl">
                    <thead><tr>
                        <th class="rcpt-hdr">Date</th><th class="rcpt-hdr">Doc No</th>
                        <th class="rcpt-hdr ctr">Type</th><th class="rcpt-hdr num">Rate</th>
                        <th class="rcpt-hdr num">Weight</th><th class="rcpt-hdr num">Amount</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($order['receipts'] as $rcpt)
                        <tr>
                            <td>{{ pdDate($rcpt['tdate']) }}</td>
                            <td>{{ $rcpt['docno'] ?? '' }}</td>
                            <td class="ctr">{{ $rcpt['ttype'] ?? '' }}</td>
                            <td class="num">{{ pdFmt((float)($rcpt['rate'] ?? 0)) }}</td>
                            <td class="num">{{ pdFmt((float)($rcpt['wgt'] ?? 0), 3) }}</td>
                            <td class="num">{{ pdFmt((float)($rcpt['amount'] ?? 0)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if (!empty($order['goldadv']))
            <div class="detail-section">
                <div class="section-label">Gold Advance Items</div>
                <table class="dtbl">
                    <thead><tr>
                        <th class="gadv-hdr ctr">#</th><th class="gadv-hdr">Code</th>
                        <th class="gadv-hdr">Item Name</th><th class="gadv-hdr num">Qty</th>
                        <th class="gadv-hdr num">Weight</th><th class="gadv-hdr num">Cost</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($order['goldadv'] as $ga)
                        <tr>
                            <td class="ctr">{{ $ga['sno'] ?? $loop->iteration }}</td>
                            <td>{{ $ga['code'] }}</td>
                            <td>{{ $ga['itemname'] ?? '' }}</td>
                            <td class="num">{{ (int)($ga['qty'] ?? 0) }}</td>
                            <td class="num">{{ pdFmt((float)($ga['weight'] ?? 0), 3) }}</td>
                            <td class="num">{{ pdFmt((float)($ga['cost'] ?? 0)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <div class="order-ftr">
                <div class="ftr-grid">
                    <div class="f"><span class="lbl">Est. Amount:</span> <span class="val">{{ pdFmt((float)$order['billamt']) }}</span></div>
                    <div class="f"><span class="lbl">Gold Advance:</span> <span class="val">{{ pdFmt((float)$order['gadvance'], 3) }}</span></div>
                    <div class="f"><span class="lbl">SRet Amt:</span> <span class="val">{{ pdFmt((float)$order['sretamt']) }}</span></div>
                    <div class="f"><span class="lbl">Ex. Amt:</span> <span class="val">{{ pdFmt((float)$order['eamt']) }}</span></div>
                    <div class="f"><span class="lbl">Cash Advance:</span> <span class="val-bold">{{ pdFmt((float)$order['advance']) }}</span></div>
                    <div class="f"><span class="lbl">Adv. After:</span> <span class="val-bold">{{ pdFmt((float)$order['advaft']) }}</span></div>
                    <div class="f"><span class="lbl">Refund:</span> <span class="val">{{ pdFmt((float)$order['refund']) }}</span></div>
                    <div class="f"><span class="lbl">Total Adv:</span> <span class="val-bold">{{ pdFmt($order['totadv']) }}</span></div>
                </div>
            </div>
        </div>
        @endforeach

        <div class="summary-bar">
            <div class="f"><span class="lbl">Orders:</span> <span class="val">{{ $summary['count'] }}</span></div>
            <div class="divider"></div>
            <div class="f"><span class="lbl">Grand Total Advance:</span> <span class="val">{{ pdFmt($summary['grandtotal']) }}</span></div>
        </div>
        <div class="count-bar">{{ $summary['count'] }} order(s) displayed</div>
    @endif
    </div>
</div>

<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>
ReportExport.initFromTable('btnSaveAs', '.order-card',
  'pending_details_{{ $dateFrom }}_{{ $dateTo }}');
</script>
</body>
</html>
