<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barcode History</title>
<style>
:root{--hdr:#0c4a6e;--hdr2:#0369a1;--border:#d0dbe8;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#0284c7}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#e0f2fe 0%,#f0f9ff 40%,#e8f4fc 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(12,74,110,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}
.top{background:#f0f9ff;border-bottom:1px solid var(--border);padding:12px 16px}
.tb-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.tb-lbl{font-size:11px;font-weight:700;color:#0c4a6e;white-space:nowrap}
.tb-input{height:30px;border:1px solid #bae6fd;border-radius:6px;padding:0 8px;font-size:13px;font-weight:700;background:#fff;color:var(--text)}
.tb-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(2,132,199,.15)}
.btn{height:30px;border-radius:6px;border:1px solid #b0c4de;padding:0 14px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;background:#f0f4f8;color:#1e3a5f;white-space:nowrap}
.btn:hover{background:#e0eaf4}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff;border-color:var(--hdr)}
.btn-primary:hover{opacity:.9}
.content{padding:14px 16px}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #bae6fd;border-radius:10px;margin:10px 0}

/* Barcode info card */
.bc-info{background:linear-gradient(135deg,#f0f5ff,#e8eef8);border:1px solid var(--border);border-radius:8px;padding:12px 16px;margin-bottom:12px}
.bc-info h2{font-size:14px;color:#0c4a6e;margin-bottom:6px}
.bc-info .sub{font-size:12px;color:var(--muted)}
.bc-info .details{display:flex;gap:20px;flex-wrap:wrap;margin-top:6px;font-size:12px}
.bc-info .f{display:flex;gap:4px}
.bc-info .lbl{font-weight:700;color:#3b5d86}
.bc-info .val{color:#1e3a5f;font-weight:600}

.tbl-wrap{max-height:55vh;overflow:auto;border:1px solid var(--border);border-radius:6px}
table{width:100%;border-collapse:collapse;font-size:12px}
th{position:sticky;top:0;background:linear-gradient(180deg,#0c4a6e,#0a3d5c);color:#bae6fd;padding:6px 8px;text-align:left;z-index:2;font-size:11px;font-weight:700;white-space:nowrap}
th.num,td.num{text-align:right}
td{border-bottom:1px solid #e0f2fe;padding:5px 8px;white-space:nowrap;font-variant-numeric:tabular-nums}
tr:hover td{background:#f0f9ff}
tr.selected td{background:#dbeafe}
/* Transaction type colors */
.tx-created{color:#0c4a6e;font-weight:700}
.tx-sales{color:#dc2626}
.tx-return{color:#16a34a}
.tx-smith{color:#7c3aed}
.tx-transfer{color:#d97706}

.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:6px}
@media print{.top{display:none}body{background:#fff}.window{margin:0;box-shadow:none;border-radius:0}.tbl-wrap{max-height:none;overflow:visible;border:0}th{position:static}}
</style>
@include('partials.print-layout-head')
</head>
<body>
@php
    function bhF(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function bhD(?string $d): string { if (!$d) return ''; $ts = strtotime($d); return $ts ? date('d/m/Y', $ts) : $d; }
    function txClass(string $t): string {
        if (str_contains($t, 'Created')) return 'tx-created';
        if (str_contains($t, 'Sales Return')) return 'tx-return';
        if (str_contains($t, 'Sales')) return 'tx-sales';
        if (str_contains($t, 'Smith')) return 'tx-smith';
        if (str_contains($t, 'Transfer')) return 'tx-transfer';
        return '';
    }
@endphp
<div class="window">
    <div class="titlebar"><h1>Barcode History</h1><span class="today">{{ date('d/m/Y') }}</span></div>
    <div class="top">
        <form method="GET" class="tb-row" id="reportForm">
            <span class="tb-lbl">Barcode:</span>
            <input type="text" name="bcode" value="{{ $bcode }}" class="tb-input" style="width:140px" autofocus placeholder="Enter barcode...">
            <span class="tb-lbl">From:</span>
            <input type="date" name="date1" value="{{ $dateFrom }}" class="tb-input" style="width:130px;font-weight:400">
            <span class="tb-lbl">To:</span>
            <input type="date" name="date2" value="{{ $dateTo }}" class="tb-input" style="width:130px;font-weight:400">
            <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
            <button type="button" class="btn" onclick="window.print()">Print</button>
        </form>
    </div>
    <div class="content">
    @if (!$showData)
        <div class="hint">Enter a <strong>Barcode Number</strong> and click <strong>Show</strong> to view its history.</div>
    @elseif (!$bcInfo)
        <div class="hint">Barcode <strong>{{ $bcode }}</strong> not found.</div>
    @else
        <div class="bc-info">
            <h2>Barcode History - {{ $bcode }} - Item: {{ $bcInfo['itemname'] }}</h2>
            <div class="sub">From {{ bhD($dateFrom) }} To {{ bhD($dateTo) }}
                @if (!empty($bcInfo['smithcode'])) - SCode: {{ $bcInfo['smithcode'] }} @endif
                @if (($bcInfo['transtouch'] ?? 0) > 0) - TT: {{ bhF((float)$bcInfo['transtouch']) }} @endif
            </div>
            <div class="details">
                <div class="f"><span class="lbl">Item:</span><span class="val">{{ $bcInfo['icode'] }}</span></div>
                <div class="f"><span class="lbl">Qty:</span><span class="val">{{ (int)$bcInfo['qty'] }}</span></div>
                <div class="f"><span class="lbl">Weight:</span><span class="val">{{ bhF((float)$bcInfo['weight'], 3) }}</span></div>
                <div class="f"><span class="lbl">Stone:</span><span class="val">{{ bhF((float)$bcInfo['stweight'], 3) }}</span></div>
                <div class="f"><span class="lbl">Rate:</span><span class="val">{{ bhF((float)$bcInfo['rate']) }}</span></div>
                <div class="f"><span class="lbl">Created:</span><span class="val">{{ bhD($bcInfo['tdate']) }}</span></div>
            </div>
        </div>

        <div class="tbl-wrap">
        <table>
            <thead><tr>
                <th>Date</th>
                <th>Transaction</th>
                <th>Doc No</th>
                <th class="num">Qty</th>
                <th class="num">Rate</th>
                <th class="num">Weight</th>
                <th class="num">Stone</th>
            </tr></thead>
            <tbody>
                @foreach ($history as $h)
                <tr>
                    <td>{{ bhD($h['tdate']) }}</td>
                    <td class="{{ txClass($h['transaction']) }}">{{ $h['transaction'] }}</td>
                    <td>{{ $h['docno'] }}</td>
                    <td class="num">{{ (int)$h['qty'] }}</td>
                    <td class="num">{{ $h['rate'] > 0 ? bhF($h['rate']) : '' }}</td>
                    <td class="num">{{ bhF($h['weight'], 3) }}</td>
                    <td class="num">{{ bhF($h['stone'], 3) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <div class="count-bar">{{ count($history) }} transaction(s)</div>
    @endif
    </div>
</div>
<script>
document.querySelector('input[name="bcode"]').addEventListener('keydown',function(e){
    if(e.key==='Enter'){e.preventDefault();document.getElementById('reportForm').submit()}
});
document.querySelectorAll('table tbody tr').forEach(function(r){
    r.addEventListener('click',function(){
        document.querySelectorAll('table tbody tr.selected').forEach(function(x){x.classList.remove('selected')});
        this.classList.add('selected');
    });
});
</script>
</body>
</html>
