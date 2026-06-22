<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Advance Receipt - {{ $master->docno ?? $master->slno }}</title>
<style>
:root{
    --ink:#0f172a;
    --muted:#475569;
    --line:#cbd5e1;
    --soft:#e2e8f0;
    --accent:#0f4c81;
    --accent-2:#1f6aa5;
    --panel:#f8fbff;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Segoe UI,Arial,sans-serif;font-size:13px;color:var(--ink);background:#eef2f7}

.toolbar{
    background:#fff;padding:8px 12px;display:flex;align-items:center;gap:8px;
    flex-wrap:wrap;border-bottom:1px solid #d1d5db;position:sticky;top:0;z-index:100;
    box-shadow:0 1px 4px rgba(0,0,0,.08)
}
.tb-lbl{font-size:11px;font-weight:600;color:#6b7280;white-space:nowrap}
.tb-inp{width:56px;text-align:center;border:1px solid #d1d5db;border-radius:6px;padding:4px 5px;font-size:11px;height:28px}
.tb-btn{padding:5px 14px;font-weight:600;font-size:11px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;color:#374151;height:30px}
.tb-btn:hover{background:#f3f4f6}
.tb-btn.primary{background:#1d4ed8;color:#fff;border-color:#1d4ed8}
.tb-btn.primary:hover{background:#1e40af}
.tb-sep{width:1px;height:20px;background:#e5e7eb;flex-shrink:0}

.page-wrap{padding:20px}
.page{
    width:780px;margin:0 auto;background:#fff;border:1px solid var(--soft);
    box-shadow:0 10px 30px rgba(15,23,42,.08);padding:26px 30px
}

.header{
    display:flex;justify-content:space-between;gap:20px;
    border-bottom:2px solid var(--accent);padding-bottom:14px;margin-bottom:18px
}
.company h1{margin:0 0 4px;font-size:24px;letter-spacing:.3px}
.company-meta{font-size:12px;font-weight:600;color:var(--ink);line-height:1.5}
.bill-meta{text-align:right}
.bill-title{font-size:20px;font-weight:800;color:var(--accent);margin:0 0 6px;text-transform:uppercase}
.bill-meta .meta{color:var(--ink);font-size:13px;line-height:1.6;font-weight:600}
.bill-meta .meta strong{font-weight:800}

.grid{display:grid;grid-template-columns:1.1fr 1fr;gap:16px;margin-bottom:16px}
.card{border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#fff}
.card h3{margin:0;padding:8px 12px;font-size:12px;letter-spacing:.4px;color:#fff;background:linear-gradient(135deg,var(--accent),var(--accent-2));text-transform:uppercase}
.card .body{padding:10px 12px;font-size:13px;line-height:1.7;background:var(--panel)}
.kv{display:grid;grid-template-columns:130px 1fr;gap:4px 10px}
.kv .k{color:var(--muted);font-weight:600}

table{width:100%;border-collapse:collapse;margin-top:6px}
th,td{border:1px solid var(--line);padding:7px 8px;font-size:12px}
thead th{background:#e9f1f8;color:#0f2d4b;text-transform:uppercase;letter-spacing:.25px;font-size:11px}
td.num,th.num{text-align:right}
td.center,th.center{text-align:center}
tfoot td{background:#f8fafc;font-weight:700}

.amount-bar{
    display:grid;grid-template-columns:1fr 280px;gap:18px;margin-top:18px;align-items:start
}
.amount-words{
    border:1px dashed var(--line);border-radius:8px;padding:14px;background:#fffdf5;font-size:13px;min-height:96px
}
.amount-words h4{margin:0 0 6px;font-size:12px;color:#9a3412;letter-spacing:.4px}
.amount-words .figure{font-size:22px;font-weight:800;color:#9a3412;margin-bottom:4px}
.amount-words .words{font-size:13px;font-weight:700;color:var(--ink);text-transform:capitalize}

.summary{border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#fff}
.summary table{margin:0}
.summary td{font-size:13px;border-left:none;border-right:none}
.summary td:first-child{color:var(--muted);font-weight:600;width:55%}
.summary tr.total td{background:#eff6ff;font-size:14px;font-weight:800;color:#0f2d4b}

.footer{
    margin-top:34px;display:flex;justify-content:space-between;gap:30px;
    font-size:12px;color:var(--muted)
}
.sign{width:200px;text-align:center;padding-top:32px;border-top:1px solid var(--line);font-weight:600;color:var(--ink)}

@media print{
    @page{size:A4 portrait;margin:10mm}
    .toolbar{display:none!important}
    body{background:#fff}
    .page-wrap{padding:0}
    .page{width:auto;margin:0;border:none;box-shadow:none;padding:0}
}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="toolbar">
    <span class="tb-lbl">Copies</span>
    <input type="number" class="tb-inp" id="copies" value="1" min="1" max="10">
    <span class="tb-lbl">Zoom%</span>
    <input type="text" class="tb-inp" id="zoom" value="100">
    <div class="tb-sep"></div>
    <button class="tb-btn primary" type="button" onclick="doPrint()">Print</button>
    <button class="tb-btn" type="button" onclick="window.print()">PDF / Save As</button>
    <button class="tb-btn" type="button" onclick="window.close()">Exit</button>
</div>

@php
    $amount     = (float) ($master->amount ?? 0);
    $rate       = (float) ($master->rate ?? 0);
    $wgt        = (float) ($master->wgt ?? 0);
    $amtToWgt   = strtoupper(trim((string) ($master->amttowgt ?? 'N'))) === 'Y';
    $date       = $master->tdate ?? '';
    $docno      = trim((string) ($master->docno ?? ''));
    $ordno      = trim((string) ($master->ordno ?? ''));
    $custName   = trim((string) ($order->custname ?? ($customer->name ?? '-')));
    $custCode   = trim((string) ($order->custcode ?? ($customer->code ?? '')));
    $custMobile = trim((string) ($customer->mobile ?? ''));
    $custAddr   = trim((string) ($customer->addr1 ?? ''));

    // Indian numeric to words
    function num2wordsIndian($num) {
        $num = round((float)$num, 2);
        if ($num == 0) return 'Zero';
        $neg = $num < 0; if ($neg) $num = -$num;
        $whole = (int) floor($num);
        $paise = (int) round(($num - $whole) * 100);
        $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
        $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
        $two = function($n) use ($ones,$tens) {
            if ($n < 20) return $ones[$n];
            $t = (int)($n/10); $o = $n % 10;
            return trim($tens[$t].($o? ' '.$ones[$o]:''));
        };
        $three = function($n) use ($two,$ones) {
            $h = (int)($n/100); $r = $n % 100;
            return trim(($h? $ones[$h].' Hundred':'').($r? ($h?' ':'').$two($r):''));
        };
        $crore = (int)($whole/10000000); $whole %= 10000000;
        $lakh  = (int)($whole/100000);   $whole %= 100000;
        $thou  = (int)($whole/1000);     $whole %= 1000;
        $hund  = $whole;
        $parts = [];
        if ($crore) $parts[] = $two($crore).' Crore';
        if ($lakh)  $parts[] = $two($lakh).' Lakh';
        if ($thou)  $parts[] = $two($thou).' Thousand';
        if ($hund)  $parts[] = $three($hund);
        $words = trim(implode(' ', $parts));
        if ($words === '') $words = 'Zero';
        $out = ($neg?'Minus ':'').$words.' Rupees';
        if ($paise > 0) $out .= ' and '.$two($paise).' Paise';
        return $out.' Only';
    }

    $newBalance = $prev_advance + $amount;
@endphp

<div class="page-wrap">
<div class="page" id="billCard">
    <div class="header">
        <div class="company">
            <h1>{{ $company['name'] ?: 'Company' }}</h1>
            @if(trim((string)$company['address']) !== '')
            <div class="company-meta">{{ trim((string)$company['address']) }}</div>
            @endif
            @if(trim((string)$company['phone']) !== '')
            <div class="company-meta">Ph: {{ trim((string)$company['phone']) }}</div>
            @endif
        </div>
        <div class="bill-meta">
            <div class="bill-title">Advance Receipt</div>
            <div class="meta">
                @if($docno !== '')<div><strong>Receipt No:</strong> {{ $docno }}</div>@endif
                <div><strong>Date:</strong> {{ $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-' }}</div>
                <div><strong>Order No:</strong> {{ $ordno ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h3>Received From</h3>
            <div class="body">
                <div><strong style="font-size:14px">{{ $custName ?: '-' }}</strong>@if($custCode) <span style="color:var(--muted)">({{ $custCode }})</span>@endif</div>
                <div class="kv" style="margin-top:6px">
                    @if($custAddr !== '')<div class="k">Address</div><div>{{ $custAddr }}</div>@endif
                    @if($custMobile !== '')<div class="k">Mobile</div><div>{{ $custMobile }}</div>@endif
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Payment Details</h3>
            <div class="body">
                <div class="kv">
                    <div class="k">Mode</div><div>{{ $cashbank_name ?: 'CASH' }}</div>
                    <div class="k">Gold Rate / gm</div><div>{{ number_format($rate, 2, '.', '') }}</div>
                    @if($amtToWgt)
                    <div class="k">Credited Weight</div><div>{{ number_format($wgt, 3, '.', '') }} gm</div>
                    @endif
                    <div class="k">Previous Advance</div><div>{{ number_format($prev_advance, 2, '.', '') }}</div>
                </div>
            </div>
        </div>
    </div>

    @if(count($items))
    <table>
        <thead>
            <tr>
                <th class="center" style="width:42px">#</th>
                <th>Item</th>
                <th class="num" style="width:64px">Qty</th>
                <th class="num" style="width:96px">Weight (g)</th>
                <th class="num" style="width:96px">Stone Wgt</th>
                <th class="num" style="width:96px">Less Wgt</th>
            </tr>
        </thead>
        <tbody>
            @php $tQty=0;$tWgt=0;$tSt=0;$tLs=0; @endphp
            @foreach($items as $i => $r)
                @php $tQty+=$r['qty'];$tWgt+=$r['weight'];$tSt+=$r['stonewgt'];$tLs+=$r['lesswgt']; @endphp
                <tr>
                    <td class="center">{{ $i+1 }}</td>
                    <td>{{ $r['name'] ?: $r['code'] }}</td>
                    <td class="num">{{ $r['qty'] ?: '' }}</td>
                    <td class="num">{{ number_format($r['weight'], 3, '.', '') }}</td>
                    <td class="num">{{ number_format($r['stonewgt'], 3, '.', '') }}</td>
                    <td class="num">{{ number_format($r['lesswgt'], 3, '.', '') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Total</td>
                <td class="num">{{ $tQty ?: '' }}</td>
                <td class="num">{{ number_format($tWgt, 3, '.', '') }}</td>
                <td class="num">{{ number_format($tSt, 3, '.', '') }}</td>
                <td class="num">{{ number_format($tLs, 3, '.', '') }}</td>
            </tr>
        </tfoot>
    </table>
    @endif

    <div class="amount-bar">
        <div class="amount-words">
            <h4>Amount Received</h4>
            <div class="figure">&#8377; {{ number_format($amount, 2, '.', ',') }}</div>
            <div class="words">{{ num2wordsIndian($amount) }}</div>
        </div>
        <div class="summary">
            <table>
                <tbody>
                    <tr><td>Previous Advance</td><td class="num">{{ number_format($prev_advance, 2, '.', '') }}</td></tr>
                    <tr><td>This Receipt</td><td class="num">{{ number_format($amount, 2, '.', '') }}</td></tr>
                    <tr class="total"><td>Total Advance</td><td class="num">{{ number_format($newBalance, 2, '.', '') }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="footer">
        <div>Printed on {{ now()->format('d/m/Y h:i A') }}<br>This is a computer-generated receipt.</div>
        <div class="sign">Authorised Signature</div>
    </div>
</div>
</div>

<script>
function doPrint() {
    const c = parseInt(document.getElementById('copies').value) || 1;
    for (let i = 0; i < c; i++) setTimeout(() => window.print(), i * 500);
}
const zoom = document.getElementById('zoom');
const card = document.getElementById('billCard');
if (zoom) zoom.addEventListener('input', () => {
    const v = parseInt(zoom.value, 10);
    card.style.zoom = (v && !isNaN(v)) ? (v/100).toString() : '';
});
</script>
</body>
</html>
