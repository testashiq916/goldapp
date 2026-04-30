<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Group Ledger</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1400px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 20px; color: #173b63; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 150px; }
        .field.wide { min-width: 300px; flex: 1; }
        label { font-size: 11px; font-weight: 700; color: #375b84; }
        input, select, button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        button { cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .headbox { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px; }
        .card { border: 1px solid #d8e2ef; border-radius: 8px; background: #f8fbff; padding: 10px; }
        .muted { color: #64748b; font-size: 12px; }
        .strong { font-weight: 700; color: #163b63; }
        .summary { display: flex; gap: 10px; flex-wrap: wrap; margin: 10px 0; }
        .pill { border: 1px solid #cad7e6; background: #f8fbff; border-radius: 999px; padding: 6px 12px; font-size: 12px; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 70vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 8px; vertical-align: top; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; z-index: 1; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #c7d4e4; border-radius: 8px; background: #fbfdff; }
        @media (max-width: 980px) {
            .headbox { grid-template-columns: 1fr; }
            .field, .field.wide { min-width: 100%; }
        }
        @media print {
            .toolbar { display: none; }
            body { background: #fff; }
            .wrap { max-width: none; margin: 0; border: 0; }
            .table-wrap { max-height: none; overflow: visible; border: 0; }
            th { position: static; }
        }
    </style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wrap">
    <h1>Group Ledger</h1>

    <form method="get" action="{{ url('/accounts/group-ledger') }}" class="toolbar">
        <div class="field wide">
            <label>A/c Group</label>
            <input name="grcode" list="group-list" value="{{ $groupCode }}" placeholder="Enter group code">
            <datalist id="group-list">
                @foreach($groupOptions as $option)
                    <option value="{{ $option['grcode'] }}">{{ $option['name'] }}</option>
                @endforeach
            </datalist>
        </div>
        <div class="field">
            <label>From</label>
            <input type="date" name="date1" value="{{ $dateFrom }}">
        </div>
        <div class="field">
            <label>To</label>
            <input type="date" name="date2" value="{{ $dateTo }}">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="muted"><input type="checkbox" name="summary" value="1" {{ $summary ? 'checked' : '' }}> Summary</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="primary" type="submit" name="show" value="1">Show</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="button" onclick="window.print()">Print</button>
        </div>
    </form>

    @if($group)
        <div class="headbox">
            <div class="card">
                <div class="strong">{{ $group['name'] }} ({{ $group['grcode'] }})</div>
                <div class="muted" style="margin-top:4px;">{{ $summary ? 'Summary mode' : 'Detail mode' }}</div>
            </div>
            <div class="card">
                <div class="muted">Period</div>
                <div class="strong">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
            </div>
            <div class="card">
                <div class="muted">{{ $summary ? 'Net Closing' : 'Closing Balance' }}</div>
                <div class="strong">
                    @if($summary)
                        {{ number_format(abs($totals['closing']), 2) }} {{ $totals['closing'] <= 0 ? 'Dr' : 'Cr' }}
                    @else
                        {{ number_format(abs($closingBalance), 2) }} {{ $closingBalance < 0 ? 'Dr' : 'Cr' }}
                    @endif
                </div>
            </div>
        </div>

        <div class="summary">
            @if($summary)
                <div class="pill">Opening: {{ number_format(abs($totals['opening']), 2) }} {{ $totals['opening'] <= 0 ? 'Dr' : 'Cr' }}</div>
            @else
                <div class="pill">Opening: {{ number_format(abs($openingBalance), 2) }} {{ $openingBalance < 0 ? 'Dr' : 'Cr' }}</div>
            @endif
            <div class="pill">Payment: {{ number_format($totals['debit'], 2) }}</div>
            <div class="pill">Receipt: {{ number_format($totals['credit'], 2) }}</div>
            <div class="pill">Rows: {{ count($rows) }}</div>
        </div>

        @if(count($rows))
            <div class="table-wrap">
                <table>
                    <thead>
                    @if($summary)
                        <tr>
                            <th style="width: 100px;">Code</th>
                            <th>Name</th>
                            <th class="num" style="width: 120px;">Op.Balance</th>
                            <th class="num" style="width: 120px;">Payment</th>
                            <th class="num" style="width: 120px;">Receipt</th>
                            <th class="num" style="width: 120px;">Cl.Balance</th>
                        </tr>
                    @else
                        <tr>
                            <th style="width: 90px;">Date</th>
                            <th style="width: 240px;">Account</th>
                            <th style="width: 110px;">Voucher No.</th>
                            <th>Particular</th>
                            <th class="num" style="width: 110px;">Payment</th>
                            <th class="num" style="width: 110px;">Receipt</th>
                            <th class="num" style="width: 120px;">Balance</th>
                        </tr>
                    @endif
                    </thead>
                    <tbody>
                    @foreach($rows as $row)
                        @if($summary)
                            <tr ondblclick="window.location='{{ url('/accounts/ac-ledger?show=1&date1='.$dateFrom.'&date2='.$dateTo.'&accode=') . rawurlencode($row['accode']) }}'">
                                <td>{{ $row['accode'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td class="num">{{ number_format($row['opening_balance'], 2) }} {{ $row['opening_side'] }}</td>
                                <td class="num">{{ number_format($row['debit'], 2) }}</td>
                                <td class="num">{{ number_format($row['credit'], 2) }}</td>
                                <td class="num">{{ number_format($row['closing_balance'], 2) }} {{ $row['closing_side'] }}</td>
                            </tr>
                        @else
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/y') }}</td>
                                <td>{{ $row['account_name'] }}</td>
                                <td>{{ $row['vchno'] }}</td>
                                <td>{{ $row['part'] }}</td>
                                <td class="num">{{ $row['debit'] != 0 ? number_format($row['debit'], 2) : '' }}</td>
                                <td class="num">{{ $row['credit'] != 0 ? number_format($row['credit'], 2) : '' }}</td>
                                <td class="num">{{ number_format(abs($row['running_balance']), 2) }} {{ $row['running_side'] }}</td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                    <tfoot>
                    @if($summary)
                        <tr>
                            <th colspan="2">Total</th>
                            <th class="num">{{ number_format(abs($totals['opening']), 2) }} {{ $totals['opening'] <= 0 ? 'Dr' : 'Cr' }}</th>
                            <th class="num">{{ number_format($totals['debit'], 2) }}</th>
                            <th class="num">{{ number_format($totals['credit'], 2) }}</th>
                            <th class="num">{{ number_format(abs($totals['closing']), 2) }} {{ $totals['closing'] <= 0 ? 'Dr' : 'Cr' }}</th>
                        </tr>
                    @else
                        <tr>
                            <th colspan="4">Total</th>
                            <th class="num">{{ number_format($totals['debit'], 2) }}</th>
                            <th class="num">{{ number_format($totals['credit'], 2) }}</th>
                            <th class="num">{{ number_format(abs($closingBalance), 2) }} {{ $closingBalance < 0 ? 'Dr' : 'Cr' }}</th>
                        </tr>
                    @endif
                    </tfoot>
                </table>
            </div>
        @else
            <div class="empty">No ledger rows found for this group and date range.</div>
        @endif
    @elseif($show && $groupCode !== '')
        <div class="empty">Group not found for code <strong>{{ $groupCode }}</strong>.</div>
    @else
        <div class="empty">Choose a group and date range, then click <strong>Show</strong>.</div>
    @endif
</div>
</body>
</html>
