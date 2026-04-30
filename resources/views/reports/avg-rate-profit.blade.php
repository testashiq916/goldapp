<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profit - Avg Rate</title>
    <style>
        body  { font-family:"Segoe UI",Tahoma,sans-serif; margin:0; background:#f3f6fb; color:#1f2937; }
        .wrap { max-width:1100px; margin:12px auto; background:#fff; border:1px solid #d7dfeb; border-radius:10px; padding:14px; }
        h1    { margin:0 0 10px; font-size:20px; color:#173b63; }

        /* toolbar */
        .toolbar { display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; margin-bottom:14px; }
        .field   { display:flex; flex-direction:column; gap:4px; }
        label    { font-size:11px; font-weight:700; color:#375b84; }
        input    { height:34px; border:1px solid #bfd0e6; border-radius:6px; padding:0 8px; font-size:12px; box-sizing:border-box; }
        button   { height:34px; border:1px solid #2a6398; border-radius:6px; padding:0 16px; font-size:12px; cursor:pointer; font-weight:700; }
        button.primary  { background:#e6f8ec; border-color:#2a7a42; color:#1b5b31; }
        button.print-btn{ background:#e8f2ff; color:#17456e; }

        /* main split table */
        .main-table { width:100%; border-collapse:collapse; font-size:12px; margin-bottom:16px;
                      border:1px solid #c5d5e8; border-radius:6px; overflow:hidden; }
        .main-table th      { background:#2a5a8c; color:#fff; padding:7px 10px; text-align:center;
                              font-size:13px; letter-spacing:.5px; }
        .main-table th.sub  { background:#3a6ea0; font-size:11px; }
        .main-table td      { padding:5px 10px; border-bottom:1px solid #e5ecf5; vertical-align:middle; }
        .main-table td.num  { text-align:right; white-space:nowrap; font-family:monospace; font-size:12px; }
        .main-table td.lbl  { font-weight:600; color:#1e3a5f; min-width:140px; }
        .main-table tr.sect-hdr td  { background:#eef5ff; font-weight:700; color:#1e4070;
                                      font-size:11px; text-transform:uppercase; letter-spacing:.4px;
                                      padding:3px 10px; }
        .main-table tr.profit-row td{ background:#fff8e6; }
        .main-table tr.total-row td { background:#e8f2ff; font-weight:700; font-size:13px;
                                      border-top:2px solid #2a5a8c; }
        .main-table tr.cash-recv td { background:#f0fff4; }
        .main-table tr.cash-give td { background:#fff5f5; }
        .main-table tr:hover td     { background:#f0f6ff; }
        .sep { border-left:2px solid #c5d5e8; }

        /* detail tables */
        .section-title { font-size:13px; font-weight:700; color:#173b63; margin:18px 0 6px;
                         border-left:4px solid #2a5a8c; padding-left:8px; }
        .detail-wrap   { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
        .detail-table  { width:100%; border-collapse:collapse; font-size:12px;
                         border:1px solid #c5d5e8; border-radius:6px; overflow:hidden; }
        .detail-table th     { background:#3a6ea0; color:#fff; padding:6px 10px; text-align:left; }
        .detail-table th.num { text-align:right; }
        .detail-table td     { padding:5px 10px; border-bottom:1px solid #e5ecf5; }
        .detail-table td.num { text-align:right; font-family:monospace; }
        .detail-table tr.tot td { background:#eef5ff; font-weight:700; border-top:2px solid #3a6ea0; }
        .detail-table tr:hover td { background:#f5f9ff; }

        /* rate footer */
        .rate-footer { text-align:center; padding:10px; background:#f0f6ff;
                       border:1px solid #c5d5e8; border-radius:6px; font-weight:700;
                       font-size:13px; color:#173b63; margin-top:4px; }
        .empty { padding:40px; text-align:center; color:#64748b; font-size:14px;
                 border:1px dashed #c7d4e4; border-radius:8px; margin-top:10px; }

        @media print {
            .toolbar { display:none; }
            body { background:#fff; }
            .wrap { max-width:none; margin:0; border:0; padding:4px; }
            .main-table th,
            tr.sect-hdr td, tr.profit-row td, tr.total-row td,
            tr.cash-recv td, tr.cash-give td, tr.tot td {
                -webkit-print-color-adjust:exact; print-color-adjust:exact;
            }
        }
    </style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wrap">
    <h1>Profit using Average Rate</h1>

    <form method="get" action="{{ url('/reports/avg-rate-profit') }}" class="toolbar">
        <div class="field">
            <label>From Date</label>
            <input type="date" name="date1" value="{{ $dateFrom }}">
        </div>
        <div class="field">
            <label>To Date</label>
            <input type="date" name="date2" value="{{ $dateTo }}">
        </div>
        <button type="submit" name="show" value="1" class="primary">Show</button>
        <button type="button" class="print-btn" onclick="window.print()">Print</button>
    </form>

    @if(!$show)
        <div class="empty">Select a date range and click <strong>Show</strong> to generate the report.</div>
    @else

    @php
        $fmtW  = fn($n) => $n != 0 ? number_format($n, 3) : '';
        $fmtA  = fn($n) => $n != 0 ? number_format($n, 2) : '';
        $fmtA1 = fn($n) => $n != 0 ? number_format($n, 1) : '';
        $fmtR  = fn($n) => $n != 0 ? number_format($n, 0) : '';

        /* ── Build RECEIVE rows ── */
        $recvRows = [];
        foreach ($cashReceipts as $cr) {
            $recvRows[] = ['label'=>$cr['label'], 'wgt'=>0, 'rate'=>0, 'amt'=>$cr['amount'], 'type'=>'cash-recv'];
        }
        if ($silverData['amount'] > 0)
            $recvRows[] = ['label'=>'SILVER (old)', 'wgt'=>$silverData['weight'], 'rate'=>$silverData['rate'], 'amt'=>$silverData['amount'], 'type'=>''];
        if ($tmStockWgt > 0)
            $recvRows[] = ['label'=>'TM STOCK',    'wgt'=>$tmStockWgt,              'rate'=>$avgRate, 'amt'=>$tmStockAmt, 'type'=>''];
        if ($goldStockWgt > 0)
            $recvRows[] = ['label'=>'GOLD STOCK',  'wgt'=>$goldStockWgt,            'rate'=>$avgRate, 'amt'=>$goldStockAmt, 'type'=>''];
        $recvRows[] = ['label'=>'GOLD RECEIVE', 'wgt'=>$goldPurchased['total_weight'], 'rate'=>$avgRate, 'amt'=>$goldReceiveAmt, 'type'=>''];

        /* ── Build GIVE rows ── */
        $giveRows = [];
        $giveRows[] = ['label'=>'GOLD GIVE', 'wgt'=>$goldSold['total_weight'], 'rate'=>$avgRate, 'amt'=>$goldGiveAmt, 'type'=>''];
        foreach ($cashPayments as $cp) {
            $giveRows[] = ['label'=>$cp['label'], 'wgt'=>0, 'rate'=>0, 'amt'=>$cp['amount'], 'type'=>'cash-give'];
        }

        /* ── Profit total ── */
        $profitTotal = array_sum(array_column($profitPeriods, 'profit_amt'));

        /* ── Totals ── */
        $receiveTotal = array_sum(array_column($recvRows, 'amt'));
        $giveTotal    = array_sum(array_column($giveRows, 'amt')) + $profitTotal;

        $maxRows = max(count($recvRows), count($giveRows));
        while (count($recvRows) < $maxRows) $recvRows[] = ['label'=>'','wgt'=>0,'rate'=>0,'amt'=>0,'type'=>''];
        while (count($giveRows) < $maxRows) $giveRows[] = ['label'=>'','wgt'=>0,'rate'=>0,'amt'=>0,'type'=>''];
    @endphp

    {{-- ═══════════════════════════ MAIN TABLE ═══════════════════════════ --}}
    <table class="main-table">
        <thead>
            <tr>
                <th colspan="4">RECEIVE</th>
                <th class="sep" colspan="4">GIVE</th>
            </tr>
            <tr>
                <th class="sub" style="width:26%">Particulars</th>
                <th class="sub num" style="width:9%">Weight</th>
                <th class="sub num" style="width:8%">Rate</th>
                <th class="sub num" style="width:11%">Amount</th>
                <th class="sub sep" style="width:18%">Particulars</th>
                <th class="sub num" style="width:9%">Weight</th>
                <th class="sub num" style="width:8%">Rate</th>
                <th class="sub num" style="width:11%">Amount</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 0; $i < $maxRows; $i++)
            @php $rv = $recvRows[$i]; $gv = $giveRows[$i]; @endphp
            <tr class="{{ $rv['type'] }} {{ $gv['type'] }}">
                <td class="lbl">{{ $rv['label'] }}</td>
                <td class="num">{{ $rv['wgt'] > 0 ? $fmtW($rv['wgt']) : '' }}</td>
                <td class="num">{{ $rv['rate'] > 0 ? $fmtR($rv['rate']) : '' }}</td>
                <td class="num">{{ $rv['amt'] > 0 ? $fmtA($rv['amt']) : '' }}</td>
                <td class="lbl sep">{{ $gv['label'] }}</td>
                <td class="num">{{ $gv['wgt'] > 0 ? $fmtW($gv['wgt']) : '' }}</td>
                <td class="num">{{ $gv['rate'] > 0 ? $fmtR($gv['rate']) : '' }}</td>
                <td class="num">{{ $gv['amt'] > 0 ? $fmtA($gv['amt']) : '' }}</td>
            </tr>
            @endfor

            {{-- Profit rows on GIVE side --}}
            @if(count($profitPeriods))
            <tr class="sect-hdr">
                <td colspan="4"></td>
                <td class="sep" colspan="4">PROFIT (by period)</td>
            </tr>
            @foreach($profitPeriods as $pp)
            <tr class="profit-row">
                <td class="lbl"></td><td></td><td></td><td></td>
                <td class="lbl sep">PROFIT : {{ $pp['period_key'] }}</td>
                <td class="num">{{ number_format($pp['profit_wgt'], 5) }}</td>
                <td class="num">{{ $fmtR($pp['rate']) }}</td>
                <td class="num">{{ $fmtA1($pp['profit_amt']) }}</td>
            </tr>
            @endforeach
            @endif

            {{-- Totals --}}
            <tr class="total-row">
                <td class="lbl">TOTAL</td>
                <td></td><td></td>
                <td class="num">{{ number_format($receiveTotal, 2) }}</td>
                <td class="lbl sep">TOTAL</td>
                <td></td><td></td>
                <td class="num">{{ number_format($giveTotal, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ═══════════════════ SALES & PURCHASE DETAILS ════════════════════ --}}
    <div class="detail-wrap">

        {{-- Sales Details --}}
        <div>
            <div class="section-title">Sales Details</div>
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th class="num">Weight</th>
                        <th>Type</th>
                        <th class="num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                @php $sTotW = 0; $sTotA = 0; @endphp
                @forelse($salesPeriods as $sp)
                @php $sTotW += $sp['weight']; $sTotA += $sp['amount']; @endphp
                <tr>
                    <td>{{ $sp['period_key'] }}</td>
                    <td class="num">{{ number_format($sp['weight'], 3) }}</td>
                    <td>{{ $sp['sale_type'] ?: '-' }}</td>
                    <td class="num">{{ number_format($sp['amount'], 3) }}</td>
                </tr>
                @empty
                <tr><td colspan="4" style="text-align:center;color:#64748b;padding:12px">No sales data</td></tr>
                @endforelse
                @if(count($salesPeriods))
                <tr class="tot">
                    <td><strong>TOTAL</strong></td>
                    <td class="num">{{ number_format($sTotW, 3) }}</td>
                    <td>TOTAL</td>
                    <td class="num">{{ number_format($sTotA, 3) }}</td>
                </tr>
                @endif
                </tbody>
            </table>
        </div>

        {{-- Purchase Details + Smith --}}
        <div>
            <div class="section-title">Purchase Details</div>
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th class="num">Weight</th>
                    </tr>
                </thead>
                <tbody>
                @php $pTotW = 0; @endphp
                @forelse($purchaseTypes as $pt)
                @php $pTotW += $pt['weight']; @endphp
                <tr>
                    <td>{{ $pt['type'] ?: 'GOLD' }}</td>
                    <td class="num">{{ number_format($pt['weight'], 3) }}</td>
                </tr>
                @empty
                <tr><td colspan="2" style="text-align:center;color:#64748b;padding:12px">No purchase data</td></tr>
                @endforelse
                @if(count($purchaseTypes))
                <tr class="tot">
                    <td><strong>TOTAL</strong></td>
                    <td class="num">{{ number_format($pTotW, 3) }}</td>
                </tr>
                @endif
                </tbody>
            </table>

            @if($smithData['issued_weight'] > 0 || $smithData['received_weight'] > 0)
            <div class="section-title" style="margin-top:14px">Smith (TM) Stock</div>
            <table class="detail-table">
                <thead><tr><th>Item</th><th class="num">Weight</th></tr></thead>
                <tbody>
                    <tr><td>TM Issued (Given)</td>  <td class="num">{{ number_format($smithData['issued_weight'],3) }}</td></tr>
                    <tr><td>TM Received</td>         <td class="num">{{ number_format($smithData['received_weight'],3) }}</td></tr>
                    @if($smithData['wastage'] > 0)
                    <tr><td>Wastage</td>              <td class="num">{{ number_format($smithData['wastage'],3) }}</td></tr>
                    @endif
                    <tr class="tot"><td>TM Stock (Pending)</td><td class="num">{{ number_format($tmStockWgt,3) }}</td></tr>
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- Rate / period footer --}}
    <div class="rate-footer">
        {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}
        &nbsp;–&nbsp;
        {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        AVG TM RATE : {{ number_format($avgRate, 0) }}
    </div>

    @endif
</div>
</body>
</html>
