<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Sales Report</title>
<style>
:root{--hdr:#1e3a5f;--hdr2:#2d5f8a;--border:#d7dfeb;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#2563eb}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#eef3ff 0%,#f4f7fb 40%,#edf2f8 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(33,52,89,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700;letter-spacing:.3px}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}

.top{background:#f9fbff;border-bottom:1px solid var(--border);padding:12px 16px}
.filter-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.field{display:flex;flex-direction:column;gap:3px}
.field label{font-size:11px;font-weight:700;color:#3b5d86}
.field input{height:30px;border:1px solid #bfd0e6;border-radius:7px;padding:0 8px;font-size:12px;background:#fff;color:var(--text)}
.field input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.btn{height:30px;border-radius:7px;border:none;padding:0 16px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-primary:hover{opacity:.9}
.btn-outline{background:#fff;color:#375b84;border:1px solid #bfd0e6}
.btn-outline:hover{background:#f0f5ff}
.btn-save{background:#e6f8ec;color:#1b5b31;border:1px solid #2a7a42}
.btn-save:hover{background:#d0f0da}

.content{padding:14px 16px;max-height:calc(100vh - 140px);overflow:auto}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:10px;display:flex;justify-content:space-between;align-items:center}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #c7d4e4;border-radius:10px;margin:10px 0}

/* ─── Order cards ─── */
.order-card{border:1px solid var(--border);border-radius:10px;margin-bottom:14px;background:#fff;overflow:hidden;box-shadow:0 2px 8px rgba(33,52,89,.05);page-break-inside:avoid}
.order-hdr{background:linear-gradient(135deg,#f0f5ff,#e8eef8);padding:10px 14px;border-bottom:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px 20px;font-size:12px}
.order-hdr .f{display:flex;gap:5px;align-items:baseline}
.order-hdr .lbl{font-weight:700;color:#3b5d86;white-space:nowrap;font-size:11px}
.order-hdr .val{color:#1e3a5f;font-weight:600}

.detail-section{padding:8px 14px}
.section-label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;display:flex;align-items:center;gap:6px}
.section-label::after{content:'';flex:1;height:1px;background:#e2e8f0}
.dtbl{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:4px}
.dtbl th{background:linear-gradient(180deg,#364891,#2d3d7b);color:#dde6ff;padding:5px 8px;text-align:left;font-size:11px;font-weight:600;white-space:nowrap}
.dtbl th.rcpt-hdr{background:linear-gradient(180deg,#1b5b31,#15472a);color:#c8f5d4}
.dtbl th.gadv-hdr{background:linear-gradient(180deg,#7a5c00,#614a00);color:#fff5d4}
.dtbl td{padding:4px 8px;border-bottom:1px solid #edf2f8}
.dtbl tr:hover td{background:#f8faff}
.dtbl .num{text-align:right;font-variant-numeric:tabular-nums}
.dtbl .ctr{text-align:center}

/* ─── Order footer ─── */
.order-ftr{background:linear-gradient(135deg,#f8faff,#edf2f8);padding:10px 14px;border-top:1px solid var(--border)}
.ftr-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px 16px;font-size:12px}
.ftr-grid .f{display:flex;gap:5px;align-items:baseline}
.ftr-grid .lbl{font-weight:600;color:#64748b;white-space:nowrap;font-size:11px}
.ftr-grid .val{font-variant-numeric:tabular-nums;color:#1e3a5f}
.ftr-grid .val-bold{font-variant-numeric:tabular-nums;font-weight:700;color:#1e3a5f;font-size:13px}
.ftr-balance{margin-top:8px;padding-top:8px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:20px;font-size:13px}
.ftr-balance .f{display:flex;gap:6px;align-items:baseline}
.ftr-balance .lbl{font-weight:700;color:#3b5d86}
.ftr-balance .val{font-weight:700;font-size:15px;font-variant-numeric:tabular-nums}
.ftr-balance .val.positive{color:#16a34a}
.ftr-balance .val.negative{color:#dc2626}

.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:8px}

@media print{
    .top{display:none}body{background:#fff}.window{margin:0;box-shadow:none;border-radius:0}
    .content{padding:6px;max-height:none;overflow:visible}
    .order-card{box-shadow:none;border-radius:0;border:1px solid #333}
    th{position:static}
}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>

@php
    function orFmt(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function orDate(?string $d): string {
        if (!$d) return '';
        $ts = strtotime($d);
        return $ts ? date('d/m/Y', $ts) : $d;
    }
@endphp

<div class="window">
    <div class="titlebar">
        <h1>Order Sales Report</h1>
        <span class="today">{{ date('d/m/Y') }}</span>
    </div>

    <div class="top">
        <form method="GET" class="filter-row" id="reportForm">
            <div class="field"><label>From Date</label><input type="date" name="date1" value="{{ $dateFrom }}"></div>
            <div class="field"><label>To Date</label><input type="date" name="date2" value="{{ $dateTo }}"></div>
            <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
            <button type="button" class="btn btn-outline" onclick="window.print()">Print</button>
            <button type="button" class="btn btn-save" id="btnSaveAs">Save As</button>
        </form>
    </div>

    <div class="content">
    @if (!$showData)
        <div class="hint">Select sales date range and click <strong>Show</strong> to view order sales.</div>
    @elseif (empty($orders))
        <div class="hint">No order sales found for the selected period.</div>
    @else
        <div class="info-bar">
            <span>Sales Date {{ orDate($dateFrom) }} &mdash; {{ orDate($dateTo) }}</span>
            <span>{{ count($orders) }} sale(s)</span>
        </div>

        @foreach ($orders as $order)
        <div class="order-card">
            <div class="order-hdr">
                <div class="f"><span class="lbl">Order No:</span> <span class="val">{{ $order['ordno'] }}</span></div>
                <div class="f"><span class="lbl">Order Date:</span> <span class="val">{{ orDate($order['ord_tdate']) }}</span></div>
                <div class="f"><span class="lbl">SM:</span> <span class="val">{{ $order['smname'] ?? '' }}</span></div>
                <div class="f"><span class="lbl">Customer:</span> <span class="val" style="font-weight:700">{{ $order['custname'] }}</span></div>
                <div class="f"><span class="lbl">Del. Date:</span> <span class="val">{{ orDate($order['duedate'] ?? '') }}</span></div>
                <div></div>
                <div class="f"><span class="lbl">Sales Bill:</span> <span class="val" style="font-weight:700">{{ $order['sale_billno'] }}</span></div>
                <div class="f"><span class="lbl">Sales Date:</span> <span class="val">{{ orDate($order['sale_tdate']) }}</span></div>
                <div></div>
            </div>

            {{-- ─── Sales Items ─── --}}
            @if (!empty($order['saleitems']))
            <div class="detail-section">
                <div class="section-label">Sales Items</div>
                <table class="dtbl">
                    <thead><tr>
                        <th class="ctr">#</th><th>Code</th><th>Item Name</th>
                        <th class="num">Qty</th><th class="num">Weight</th>
                        <th class="num">Rate</th><th class="num">St.Wgt</th>
                        <th class="num">St.Price</th><th class="num">Wastage</th>
                        <th class="num">M.Charge</th><th class="num">Amount</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($order['saleitems'] as $item)
                        <tr>
                            <td class="ctr">{{ $item['sno'] ?? $loop->iteration }}</td>
                            <td>{{ $item['code'] }}</td>
                            <td>{{ $item['itemname'] ?? '' }}</td>
                            <td class="num">{{ (int)($item['qty'] ?? 0) }}</td>
                            <td class="num">{{ orFmt((float)($item['weight'] ?? 0), 3) }}</td>
                            <td class="num">{{ orFmt((float)($item['rate'] ?? 0)) }}</td>
                            <td class="num">{{ orFmt((float)($item['stonewgt'] ?? 0), 3) }}</td>
                            <td class="num">{{ orFmt((float)($item['stoneprice'] ?? 0)) }}</td>
                            <td class="num">{{ orFmt((float)($item['wastage'] ?? 0), 3) }}</td>
                            <td class="num">{{ orFmt((float)($item['mcharge'] ?? 0)) }}</td>
                            <td class="num">{{ orFmt((float)($item['amount'] ?? 0)) }}</td>
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
                    <thead><tr>
                        <th class="rcpt-hdr">Date</th><th class="rcpt-hdr">Doc No</th>
                        <th class="rcpt-hdr ctr">Type</th><th class="rcpt-hdr num">Rate</th>
                        <th class="rcpt-hdr num">Weight</th><th class="rcpt-hdr num">Amount</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($order['receipts'] as $rcpt)
                        <tr>
                            <td>{{ orDate($rcpt['tdate']) }}</td>
                            <td>{{ $rcpt['docno'] ?? '' }}</td>
                            <td class="ctr">{{ $rcpt['ttype'] ?? '' }}</td>
                            <td class="num">{{ orFmt((float)($rcpt['rate'] ?? 0)) }}</td>
                            <td class="num">{{ orFmt((float)($rcpt['wgt'] ?? 0), 3) }}</td>
                            <td class="num">{{ orFmt((float)($rcpt['amount'] ?? 0)) }}</td>
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
                            <td class="num">{{ orFmt((float)($ga['weight'] ?? 0), 3) }}</td>
                            <td class="num">{{ orFmt((float)($ga['cost'] ?? 0)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- ─── Order Footer ─── --}}
            <div class="order-ftr">
                <div class="ftr-grid">
                    <div class="f"><span class="lbl">Gold Adv:</span> <span class="val">{{ orFmt((float)$order['gadvance'], 3) }}</span></div>
                    <div class="f"><span class="lbl">Bill Amount:</span> <span class="val">{{ orFmt((float)$order['sale_billamt']) }}</span></div>
                    <div class="f"><span class="lbl">Ex. Amt:</span> <span class="val">{{ orFmt((float)$order['sale_eamt']) }}</span></div>
                    <div class="f"><span class="lbl">Ret Amt:</span> <span class="val">{{ orFmt((float)$order['sale_sretamt']) }}</span></div>
                    <div class="f"><span class="lbl">Cash Adv:</span> <span class="val-bold">{{ orFmt((float)$order['sale_advance']) }}</span></div>
                    <div class="f"><span class="lbl">Discount:</span> <span class="val">{{ orFmt((float)$order['discount']) }}</span></div>
                    <div class="f"><span class="lbl">Rcvd Amt:</span> <span class="val">{{ orFmt((float)$order['ramt']) }}</span></div>
                    <div></div>
                </div>
                <div class="ftr-balance">
                    <div class="f">
                        <span class="lbl">Balance:</span>
                        <span class="val {{ $order['balance'] >= 0 ? 'positive' : 'negative' }}">{{ orFmt(abs($order['balance'])) }}{{ $order['balance'] < 0 ? ' CR' : '' }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        <div class="count-bar">{{ count($orders) }} order sale(s) displayed</div>
    @endif
    </div>
</div>

<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>
ReportExport.initFromTable('btnSaveAs', '.order-card',
  'order_returns_{{ $dateFrom }}_{{ $dateTo }}');
</script>
</body>
</html>
