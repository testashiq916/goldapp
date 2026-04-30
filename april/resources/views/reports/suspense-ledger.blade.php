<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suspense A/c Ledger</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1420px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 20px; color: #173b63; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 140px; }
        .field.wide { min-width: 260px; flex: 1; }
        label { font-size: 11px; font-weight: 700; color: #375b84; }
        input, select, button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        button { cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 10px; }
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
            .cards { grid-template-columns: 1fr; }
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
    <h1>Suspense A/c Ledger</h1>

    <form method="get" action="{{ url('/accounts/suspense-ac-ledger') }}" class="toolbar">
        <div class="field wide">
            <label>A/c Code</label>
            <input name="accode" list="accode-list" value="{{ $accode }}" placeholder="Enter account code">
            <datalist id="accode-list">
                @foreach($accountOptions as $option)
                    <option value="{{ $option['accode'] }}">{{ $option['name'] }}</option>
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
            <label>Type</label>
            <select name="type">
                <option value="detail" {{ $type === 'detail' ? 'selected' : '' }}>Detail</option>
                <option value="entry" {{ $type === 'entry' ? 'selected' : '' }}>Entry Bal Summary</option>
                <option value="suspbal" {{ $type === 'suspbal' ? 'selected' : '' }}>Susp.Bal Summary</option>
            </select>
        </div>
        <div class="field wide">
            <label>Susp A/c</label>
            <input name="scode" list="scode-list" value="{{ $scode }}" placeholder="Optional suspense code">
            <datalist id="scode-list">
                @foreach($suspenseOptions as $option)
                    <option value="{{ $option['code'] }}">{{ $option['name'] }}</option>
                @endforeach
            </datalist>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="muted"><input type="checkbox" name="use_scode" value="1" {{ $useScode ? 'checked' : '' }}> Use Susp A/c</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="muted"><input type="checkbox" name="pending_only" value="1" {{ $pendingOnly ? 'checked' : '' }}> Susp.Pending Only</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="primary" type="submit" name="show" value="1">Show</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="ghost" type="button" onclick="window.print()">Print</button>
        </div>
    </form>

    @if($account)
        <div class="cards">
            <div class="card">
                <div class="muted">Account</div>
                <div class="strong">{{ $account['name'] }} ({{ $account['accode'] }})</div>
            </div>
            <div class="card">
                <div class="muted">Opening Balance</div>
                <div class="strong">{{ number_format(abs($openingBalance), 2) }} {{ $openingBalance < 0 ? 'Dr' : 'Cr' }}</div>
            </div>
            <div class="card">
                <div class="muted">Closing Balance</div>
                <div class="strong">{{ number_format(abs($closingBalance), 2) }} {{ $closingBalance < 0 ? 'Dr' : 'Cr' }}</div>
            </div>
            <div class="card">
                <div class="muted">Period</div>
                <div class="strong">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
            </div>
        </div>

        <div class="summary">
            <div class="pill">Debit: {{ number_format($totals['debit'], 2) }}</div>
            <div class="pill">Credit: {{ number_format($totals['credit'], 2) }}</div>
            <div class="pill">Mode: {{ $type === 'detail' ? 'Detail' : ($type === 'suspbal' ? 'Susp.Bal Summary' : 'Entry Bal Summary') }}</div>
        </div>

        @if($type === 'detail' && count($detailRows))
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 90px;">Date</th>
                        <th style="width: 220px;">Susp. A/c</th>
                        <th>Description</th>
                        <th style="width: 120px;">Voucher No.</th>
                        <th class="num" style="width: 110px;">Debit</th>
                        <th class="num" style="width: 110px;">Credit</th>
                        <th class="num" style="width: 120px;">Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($detailRows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/y') }}</td>
                            <td>{{ $row['suspacname'] }}</td>
                            <td>{{ $row['particular'] }}</td>
                            <td>{{ $row['vchno'] }}</td>
                            <td class="num">{{ $row['debit'] != 0 ? number_format($row['debit'], 2) : '' }}</td>
                            <td class="num">{{ $row['credit'] != 0 ? number_format($row['credit'], 2) : '' }}</td>
                            <td class="num">{{ number_format(abs($row['running_balance']), 2) }} {{ $row['running_side'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="4">Total</th>
                        <th class="num">{{ number_format($totals['debit'], 2) }}</th>
                        <th class="num">{{ number_format($totals['credit'], 2) }}</th>
                        <th class="num">{{ number_format(abs($closingBalance), 2) }} {{ $closingBalance < 0 ? 'Dr' : 'Cr' }}</th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @elseif($type === 'entry' && count($entryRows))
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 90px;">Date</th>
                        <th style="width: 220px;">Susp. A/c</th>
                        <th>Description</th>
                        <th style="width: 120px;">Voucher No.</th>
                        <th class="num" style="width: 120px;">Bal.Debit</th>
                        <th class="num" style="width: 120px;">Bal.Credit</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($entryRows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/y') }}</td>
                            <td>{{ $row['suspacname'] }}</td>
                            <td>{{ $row['note'] }}</td>
                            <td>{{ $row['vchno'] }}</td>
                            <td class="num">{{ $row['debit'] != 0 ? number_format($row['debit'], 2) : '' }}</td>
                            <td class="num">{{ $row['credit'] != 0 ? number_format($row['credit'], 2) : '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="4">Total</th>
                        <th class="num">{{ number_format($totals['debit'], 2) }}</th>
                        <th class="num">{{ number_format($totals['credit'], 2) }}</th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @elseif($type === 'suspbal' && count($codeSummaryRows))
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 120px;">Susp Code</th>
                        <th>Susp. A/c</th>
                        <th class="num" style="width: 120px;">Debit</th>
                        <th class="num" style="width: 120px;">Credit</th>
                        <th class="num" style="width: 100px;">Entries</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($codeSummaryRows as $row)
                        <tr>
                            <td>{{ $row['scode'] }}</td>
                            <td>{{ $row['suspacname'] }}</td>
                            <td class="num">{{ $row['debit'] != 0 ? number_format($row['debit'], 2) : '' }}</td>
                            <td class="num">{{ $row['credit'] != 0 ? number_format($row['credit'], 2) : '' }}</td>
                            <td class="num">{{ $row['entry_count'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="2">Total</th>
                        <th class="num">{{ number_format($totals['debit'], 2) }}</th>
                        <th class="num">{{ number_format($totals['credit'], 2) }}</th>
                        <th></th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="empty">No suspense ledger rows found for this filter selection.</div>
        @endif
    @elseif($show && $accode !== '')
        <div class="empty">Account not found for code <strong>{{ $accode }}</strong>.</div>
    @else
        <div class="empty">Choose an account, set the filters you want, then click <strong>Show</strong>.</div>
    @endif
</div>
</body>
</html>
