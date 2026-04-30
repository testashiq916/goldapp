<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cash Book</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1450px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 20px; color: #173b63; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 150px; }
        label { font-size: 11px; font-weight: 700; color: #375b84; }
        input, select, button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        button { cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .muted { color: #64748b; font-size: 12px; }
        .headbox { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 10px; }
        .card { border: 1px solid #d8e2ef; border-radius: 8px; background: #f8fbff; padding: 10px; }
        .strong { font-weight: 700; color: #163b63; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 72vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 8px; vertical-align: top; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; z-index: 1; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .group-row td { background: #f7fbff; font-weight: 700; color: #264968; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #c7d4e4; border-radius: 8px; background: #fbfdff; }
        .status { margin-left: auto; align-self: center; font-size: 12px; color: #5b6e85; }
        .clickable-row { cursor: pointer; }
        .voucher-link { color: #174ea6; text-decoration: underline; cursor: pointer; }
        @media (max-width: 980px) { .headbox { grid-template-columns: 1fr; } .field { min-width: 100%; } }
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
    <h1>Cash Book</h1>

    <form method="get" action="{{ url('/accounts/cash-book') }}" class="toolbar">
        <div class="field">
            <label>Date From</label>
            <input type="date" name="date1" value="{{ $dateFrom }}">
        </div>
        <div class="field">
            <label>Date To</label>
            <input type="date" name="date2" value="{{ $dateTo }}">
        </div>
        <div class="field">
            <label>Incharge</label>
            <select name="ic">
                <option value="">All</option>
                @foreach($incharges as $option)
                    <option value="{{ $option['code'] }}" {{ $incharge === $option['code'] ? 'selected' : '' }}>
                        {{ $option['code'] }}{{ $option['name'] !== $option['code'] ? ' - '.$option['name'] : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="muted"><input type="checkbox" name="use_ic" value="1" {{ $useIncharge ? 'checked' : '' }}> Use Incharge</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="muted"><input type="checkbox" name="breakday" value="1" {{ $breakDay ? 'checked' : '' }}> Break Day Total</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="primary" type="submit" name="show" value="1">Show</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="button" onclick="window.print()">Print</button>
        </div>
        @if($show)
            <div class="status">{{ $statusText }}</div>
        @endif
    </form>

    @if($show)
        <div class="headbox">
            <div class="card">
                <div class="muted">Period</div>
                <div class="strong">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
            </div>
            <div class="card">
                <div class="muted">Opening Balance (Previous Day Close)</div>
                <div class="strong">{{ ($displayOpeningBalance ?? 0) < 0 ? '-' : '' }}{{ number_format(abs($displayOpeningBalance ?? 0), 2) }}</div>
                <div class="muted">
                    As on {{ \Carbon\Carbon::parse($dateFrom)->subDay()->format('d/m/Y') }}
                </div>
            </div>
            <div class="card">
                <div class="muted">Closing Balance</div>
                <div class="strong">{{ ($displayClosingBalance ?? $closingBalance) < 0 ? '-' : '' }}{{ number_format(abs($displayClosingBalance ?? $closingBalance), 2) }}</div>
            </div>
            <div class="card">
                <div class="muted">Incharge</div>
                <div class="strong">{{ $useIncharge && $incharge !== '' ? $incharge : 'All' }}</div>
            </div>
        </div>

        @php
            $groupedRows = [];
            if ($breakDay) {
                foreach ($rows as $row) {
                    $groupedRows[$row['date']][] = $row;
                }
            } else {
                $groupedRows['all'] = $rows;
            }
        @endphp

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th style="width: 95px;">Date</th>
                    <th style="width: 110px;">Doc. No.</th>
                    <th style="width: 210px;">A/c</th>
                    <th>Description</th>
                    <th class="num" style="width: 110px;">Debit</th>
                    <th class="num" style="width: 110px;">Credit</th>
                    <th class="num" style="width: 120px;">Balance</th>
                </tr>
                </thead>
                <tbody>
                <tr class="group-row">
                    <td colspan="4">Opening Balance as on {{ \Carbon\Carbon::parse($dateFrom)->subDay()->format('d/m/Y') }}</td>
                    <td class="num"></td>
                    <td class="num"></td>
                    <td class="num">{{ ($displayOpeningBalance ?? 0) < 0 ? '-' : '' }}{{ number_format(abs($displayOpeningBalance ?? 0), 2) }}</td>
                </tr>
                @php
                    $prevBalance = $displayOpeningBalance ?? 0;
                @endphp
                @forelse($groupedRows as $day => $dayRows)
                    @php
                        $dayDebit = 0.0;
                        $dayCredit = 0.0;
                    @endphp
                    @foreach($dayRows as $row)
                        <tr
                            @if(!empty($row['navigate_url']))
                                class="clickable-row"
                                ondblclick='openCashTarget(@json($row["navigate_url"]))'
                                title="Double-click to open bill"
                            @endif
                        >
                            <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                            <td
                                @if(!empty($row['navigate_url']))
                                    class="voucher-link"
                                    ondblclick='event.stopPropagation(); openCashTarget(@json($row["navigate_url"]))'
                                    title="Double-click to open bill"
                                @endif
                            >{{ $row['vchno'] }}</td>
                            <td>{{ $row['othacname'] }}</td>
                            <td>{{ $row['particular'] }}</td>
                            <td class="num">{{ $row['debit'] != 0 ? number_format($row['debit'], 2) : '' }}</td>
                            <td class="num">{{ $row['credit'] != 0 ? number_format($row['credit'], 2) : '' }}</td>
                            <td class="num">{{ $row['running_balance'] < 0 ? '-' : '' }}{{ number_format(abs($row['running_balance']), 2) }}</td>
                        </tr>
                        @php
                            $dayDebit += $row['debit'];
                            $dayCredit += $row['credit'];
                            $prevBalance = $row['running_balance'];
                        @endphp
                    @endforeach
                    @if($breakDay && $day !== 'all')
                        <tr class="group-row">
                            <td colspan="4">Day Total - {{ \Carbon\Carbon::parse($day)->format('d/m/Y') }}</td>
                            <td class="num">{{ number_format($dayDebit, 2) }}</td>
                            <td class="num">{{ number_format($dayCredit, 2) }}</td>
                            <td class="num">{{ $prevBalance < 0 ? '-' : '' }}{{ number_format(abs($prevBalance), 2) }}</td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="empty">No cashbook rows found for the selected period.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr class="group-row">
                    <td colspan="4">Total</td>
                    <td class="num">{{ number_format($debitTotal, 2) }}</td>
                    <td class="num">{{ number_format($creditTotal, 2) }}</td>
                    <td class="num"></td>
                </tr>
                <tr class="group-row">
                    <td colspan="4">Term Balance</td>
                    <td class="num">{{ $debitTotal > $creditTotal ? number_format($debitTotal - $creditTotal, 2) : '' }}</td>
                    <td class="num">{{ $creditTotal > $debitTotal ? number_format($creditTotal - $debitTotal, 2) : '' }}</td>
                    <td class="num"></td>
                </tr>
                <tr class="group-row">
                    <td colspan="4">Closing Balance</td>
                    <td class="num"></td>
                    <td class="num"></td>
                    <td class="num">{{ ($displayClosingBalance ?? $closingBalance) < 0 ? '-' : '' }}{{ number_format(abs($displayClosingBalance ?? $closingBalance), 2) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    @else
        <div class="empty">Choose date range and click <strong>Show</strong> to load the cash book.</div>
    @endif
</div>
<script>
function openCashTarget(url){
    if (!url) return;
    try {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: 'goldapp:open-module-url',
                url: url,
                title: 'Voucher',
            }, '*');
            return;
        }
    } catch (_) {}
    window.location.href = url;
}
</script>
</body>
</html>
