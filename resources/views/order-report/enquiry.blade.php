<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Enquiry</title>
<style>
:root{--hdr:#4a2574;--hdr2:#6b3fa0;--border:#ddd5eb;--bg:#f8f5fc;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#7c3aed}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#f3eeff 0%,#f8f5fc 40%,#f0ecf8 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(60,30,90,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700;letter-spacing:.3px}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}

/* ─── Search bar ─── */
.top{background:#faf8ff;border-bottom:1px solid var(--border);padding:14px 16px}
.search-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.field{display:flex;flex-direction:column;gap:3px}
.field label{font-size:11px;font-weight:700;color:#5b3d86}
.field input{height:34px;border:1px solid #c5b5dd;border-radius:7px;padding:0 10px;font-size:13px;font-weight:700;background:#fff;color:var(--text);text-transform:uppercase;width:200px}
.field input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(124,58,237,.12)}
.btn{height:34px;border-radius:7px;border:none;padding:0 18px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-primary:hover{opacity:.9}
.btn-outline{background:#fff;color:#5b3d86;border:1px solid #c5b5dd}
.btn-outline:hover{background:#f5f0ff}
.btn-save{background:#e6f0ff;color:#1e3a5f;border:1px solid #2a6398}
.btn-save:hover{background:#d0e6ff}

/* ─── Content area ─── */
.content{padding:14px 16px}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #d0c5e4;border-radius:10px;margin:10px 0}

/* ─── Order cards ─── */
.order-card{border:1px solid var(--border);border-radius:10px;margin-bottom:14px;background:#fff;overflow:hidden;box-shadow:0 2px 8px rgba(60,30,90,.05);page-break-inside:avoid}
.order-hdr{background:linear-gradient(135deg,#f5f0ff,#ebe4f8);padding:12px 14px;border-bottom:1px solid var(--border)}
.order-hdr .grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px 20px;font-size:12px}
.order-hdr .f{display:flex;gap:5px;align-items:baseline}
.order-hdr .lbl{font-weight:700;color:#5b3d86;white-space:nowrap;font-size:11px}
.order-hdr .val{color:#2d1650;font-weight:600}
.badge{display:inline-block;padding:2px 10px;border-radius:10px;font-size:10px;font-weight:700;letter-spacing:.3px}
.badge-pending{background:#fef3c7;color:#92400e;border:1px solid #f59e0b}
.badge-returned{background:#d1fae5;color:#065f46;border:1px solid #10b981}

/* ─── Detail tables ─── */
.detail-section{padding:8px 14px}
.section-label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;display:flex;align-items:center;gap:6px}
.section-label::after{content:'';flex:1;height:1px;background:#e2daf0}
.dtbl{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:4px}
.dtbl th{background:linear-gradient(180deg,#5a3080,#4a2574);color:#e4d4f5;padding:5px 8px;text-align:left;font-size:11px;font-weight:600;white-space:nowrap}
.dtbl th.rcpt-hdr{background:linear-gradient(180deg,#1b5b31,#15472a);color:#c8f5d4}
.dtbl th.gadv-hdr{background:linear-gradient(180deg,#7a5c00,#614a00);color:#fff5d4}
.dtbl td{padding:4px 8px;border-bottom:1px solid #f0ecf8}
.dtbl tr:hover td{background:#faf8ff}
.dtbl .num{text-align:right;font-variant-numeric:tabular-nums}
.dtbl .ctr{text-align:center}

/* ─── Order footer ─── */
.order-ftr{background:linear-gradient(135deg,#faf8ff,#f0ecf8);padding:12px 14px;border-top:1px solid var(--border)}
.ftr-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px 16px;font-size:12px}
.ftr-grid .f{display:flex;gap:5px;align-items:baseline}
.ftr-grid .lbl{font-weight:600;color:#64748b;white-space:nowrap;font-size:11px}
.ftr-grid .val{font-variant-numeric:tabular-nums;color:#2d1650}
.ftr-grid .val-bold{font-variant-numeric:tabular-nums;font-weight:700;color:#2d1650;font-size:13px}
.ftr-total{margin-top:8px;padding-top:8px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:20px;font-size:13px}
.ftr-total .f{display:flex;gap:6px;align-items:baseline}
.ftr-total .lbl{font-weight:700;color:#5b3d86}
.ftr-total .val{font-weight:700;font-size:15px;color:#2d1650;font-variant-numeric:tabular-nums}

.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:8px}

@media print{
    .top{display:none}
    body{background:#fff}
    .window{margin:0;box-shadow:none;border-radius:0}
    .content{padding:6px}
    .order-card{box-shadow:none;border-radius:0;border:1px solid #333}
    th{position:static}
}
</style>
@include('partials.print-layout-head')
</head>
<body>

@php
    function eqFmt(float $n, int $d = 2): string {
        return number_format($n, $d, '.', ',');
    }
    function eqDate(?string $d): string {
        if (!$d) return '';
        $ts = strtotime($d);
        return $ts ? date('d/m/Y', $ts) : $d;
    }
@endphp

<div class="window">
    <div class="titlebar">
        <h1>Order Enquiry</h1>
        <span class="today">{{ date('d/m/Y') }}</span>
    </div>

    <div class="top">
        <form method="GET" class="search-row" id="reportForm">
            <div class="field">
                <label>Order No</label>
                <input type="text" name="ordno" value="{{ $ordno }}" autofocus placeholder="Enter order number...">
            </div>
            <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
            <button type="button" class="btn btn-outline" onclick="window.print()">Print</button>
            <button type="button" class="btn btn-save" id="btnSaveAs">Save As</button>
        </form>
    </div>

    <div class="content">
    @if (!$showData)
        <div class="hint">Enter an <strong>Order Number</strong> and click <strong>Show</strong> to view order details.</div>
    @elseif (empty($orders))
        <div class="hint">No orders found for order number <strong>{{ $ordno }}</strong>.</div>
    @else
        @foreach ($orders as $order)
        <div class="order-card">
            {{-- ─── Order Header ─── --}}
            <div class="order-hdr">
                <div class="grid">
                    <div class="f"><span class="lbl">Order No:</span> <span class="val">{{ $order['ordno'] }}</span></div>
                    <div class="f"><span class="lbl">Date:</span> <span class="val">{{ eqDate($order['tdate']) }}</span></div>
                    <div class="f">
                        <span class="lbl">Status:</span>
                        @if ((int) $order['status'] === 1)
                            <span class="badge badge-pending">Pending</span>
                        @else
                            <span class="badge badge-returned">Returned</span>
                        @endif
                    </div>
                    <div class="f"><span class="lbl">Customer:</span> <span class="val" style="font-weight:700">{{ $order['custname'] }}</span></div>
                    <div class="f"><span class="lbl">Due Date:</span> <span class="val">{{ eqDate($order['duedate'] ?? '') }}</span></div>
                    <div class="f"><span class="lbl">SM:</span> <span class="val">{{ $order['smname'] ?? '' }}</span></div>
                    @if (!empty($order['addr']))
                    <div class="f"><span class="lbl">Address:</span> <span class="val">{{ $order['addr'] }}</span></div>
                    @endif
                    @if (!empty($order['duedate_org']))
                    <div class="f"><span class="lbl">Org Due Date:</span> <span class="val">{{ eqDate($order['duedate_org']) }}</span></div>
                    @endif
                    @if (!empty($order['phone']))
                    <div class="f"><span class="lbl">Phone:</span> <span class="val">{{ $order['phone'] }}</span></div>
                    @endif
                </div>
            </div>

            {{-- ─── Order Detail Items ─── --}}
            @if (!empty($order['items']))
            <div class="detail-section">
                <div class="section-label">Order Items</div>
                <table class="dtbl">
                    <thead>
                        <tr>
                            <th class="ctr">#</th>
                            <th>Code</th>
                            <th>Item Name</th>
                            <th class="num">Qty</th>
                            <th class="num">Weight</th>
                            <th class="num">Wastage</th>
                            <th class="num">St.Wgt</th>
                            <th class="num">St.Price</th>
                            <th class="num">M.Charge</th>
                            <th class="num">Amount</th>
                            <th>Particulars</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order['items'] as $item)
                        <tr>
                            <td class="ctr">{{ $item['sno'] ?? $loop->iteration }}</td>
                            <td>{{ $item['code'] }}</td>
                            <td>{{ $item['itemname'] ?? '' }}</td>
                            <td class="num">{{ (int)($item['qty'] ?? 0) }}</td>
                            <td class="num">{{ eqFmt((float)($item['weight'] ?? 0), 3) }}</td>
                            <td class="num">{{ eqFmt((float)($item['wastage'] ?? 0), 3) }}</td>
                            <td class="num">{{ eqFmt((float)($item['stonewgt'] ?? 0), 3) }}</td>
                            <td class="num">{{ eqFmt((float)($item['stoneprice'] ?? 0)) }}</td>
                            <td class="num">{{ eqFmt((float)($item['mcharge'] ?? 0)) }}</td>
                            <td class="num">{{ eqFmt((float)($item['amount'] ?? 0)) }}</td>
                            <td>{{ $item['part'] ?? '' }}</td>
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
                            <td>{{ eqDate($rcpt['tdate']) }}</td>
                            <td>{{ $rcpt['docno'] ?? '' }}</td>
                            <td class="ctr">{{ $rcpt['ttype'] ?? '' }}</td>
                            <td class="num">{{ eqFmt((float)($rcpt['rate'] ?? 0)) }}</td>
                            <td class="num">{{ eqFmt((float)($rcpt['wgt'] ?? 0), 3) }}</td>
                            <td class="num">{{ eqFmt((float)($rcpt['amount'] ?? 0)) }}</td>
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
                            <td class="num">{{ eqFmt((float)($ga['weight'] ?? 0), 3) }}</td>
                            <td class="num">{{ eqFmt((float)($ga['cost'] ?? 0)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- ─── Order Footer ─── --}}
            <div class="order-ftr">
                <div class="ftr-grid">
                    <div class="f"><span class="lbl">Est. Amount:</span> <span class="val">{{ eqFmt((float)$order['billamt']) }}</span></div>
                    <div class="f"><span class="lbl">Gold Advance:</span> <span class="val">{{ eqFmt((float)$order['gadvance'], 3) }}</span></div>
                    <div class="f"><span class="lbl">Adv After Wgt:</span> <span class="val">{{ eqFmt((float)$order['advafterwgt'], 3) }}</span></div>
                    <div class="f"><span class="lbl">SRet Amt:</span> <span class="val">{{ eqFmt((float)$order['sretamt']) }}</span></div>

                    <div class="f"><span class="lbl">Ex. Amt:</span> <span class="val">{{ eqFmt((float)$order['eamt']) }}</span></div>
                    <div class="f"><span class="lbl">Cash Advance:</span> <span class="val-bold">{{ eqFmt((float)$order['advance']) }}</span></div>
                    <div class="f"><span class="lbl">Advance After:</span> <span class="val-bold">{{ eqFmt((float)$order['advafter']) }}</span></div>
                    <div class="f"><span class="lbl">Refund:</span> <span class="val">{{ eqFmt((float)$order['refund']) }}</span></div>
                </div>
                <div class="ftr-total">
                    <div class="f"><span class="lbl">Total Advance:</span> <span class="val">{{ eqFmt($order['totadv']) }}</span></div>
                </div>
            </div>
        </div>
        @endforeach

        <div class="count-bar">{{ count($orders) }} order(s) found for {{ $ordno }}</div>
    @endif
    </div>
</div>

<script>
document.querySelector('input[name="ordno"]').addEventListener('keydown', function(e){
    if(e.key === 'Enter'){ e.preventDefault(); document.getElementById('reportForm').submit(); }
});
</script>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>
ReportExport.initFromTable('btnSaveAs', '.order-card',
  'order_enquiry_{{ $ordno }}');
</script>
</body>
</html>
