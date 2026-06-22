<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rcpt/Pmnt Report</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1450px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 20px; color: #173b63; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 145px; }
        .field.wide { min-width: 280px; }
        label { font-size: 11px; font-weight: 700; color: #375b84; }
        input, select, button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        button { cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .headbox { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 10px; }
        .card { border: 1px solid #d8e2ef; border-radius: 8px; background: #f8fbff; padding: 10px; }
        .muted { color: #64748b; font-size: 12px; }
        .strong { font-weight: 700; color: #163b63; }
        .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 10px 0; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 70vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 8px; vertical-align: top; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; z-index: 1; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #c7d4e4; border-radius: 8px; background: #fbfdff; }
        @media (max-width: 980px) { .headbox, .summary-grid { grid-template-columns: 1fr; } .field, .field.wide { min-width: 100%; } }
        @media print {
            .toolbar { display: none; }
            body { background: #fff; }
            .wrap { max-width: none; margin: 0; border: 0; }
            .table-wrap { max-height: none; overflow: visible; border: 0; }
            th { position: static; }
        }
    
/* ── Font size overrides ── */
body { font-size: 20px !important; }
label { font-size: 17px !important; }
input, select, button { font-size: 18px !important; height: 36px !important; }
table { font-size: 18px !important; }
th { font-size: 15px !important; }
td { font-size: 18px !important; }
.btn, button { font-size: 17px !important; height: 36px !important; }</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="wrap">
    <h1>Rcpt/Pmnt Report</h1>

    <form method="get" action="{{ url('/accounts/rcpt-pmnt-report') }}" class="toolbar">
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
                @foreach(['All','Customers','Suppliers','Jewelleries','Goldsmiths','Refiners','Staff','General','Kuri/Scheme Customers'] as $opt)
                    <option value="{{ $opt }}" {{ $type === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Actype</label>
            <select name="actype">
                @foreach(['All','Expense Only','Revenue Only','Asset Only','Liability Only'] as $opt)
                    <option value="{{ $opt }}" {{ $actype === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div class="field wide">
            <label>Group</label>
            <input name="grcode" list="group-list" value="{{ $groupCode }}" placeholder="Enter group code">
            <datalist id="group-list">
                @foreach($groupOptions as $option)
                    <option value="{{ $option['grcode'] }}">{{ $option['name'] }}</option>
                @endforeach
            </datalist>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="muted"><input type="checkbox" name="use_group" value="1" {{ $useGroup ? 'checked' : '' }}> Use Group</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="muted"><input type="checkbox" name="summary" value="1" {{ $summary ? 'checked' : '' }}> Show Summary</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="muted"><input type="checkbox" name="sort_name" value="1" {{ $sortByName ? 'checked' : '' }}> Sort On A/c Name</label>
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

    @if($show)
        <div class="headbox">
            <div class="card">
                <div class="muted">Cash/Bank OB</div>
                <div class="strong">{{ number_format(abs($openingCash), 2) }} {{ $openingCash < 0 ? 'Dr' : 'Cr' }}</div>
            </div>
            <div class="card">
                <div class="muted">Cash/Bank CB</div>
                <div class="strong">{{ number_format(abs($closingCash), 2) }} {{ $closingCash < 0 ? 'Dr' : 'Cr' }}</div>
            </div>
            <div class="card">
                <div class="muted">Period</div>
                <div class="strong">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
            </div>
            <div class="card">
                <div class="muted">Mode</div>
                <div class="strong">{{ $summary ? 'Summary' : 'Detail' }}</div>
            </div>
        </div>

        <div class="summary-grid">
            <div class="card">
                <div class="muted">Receipt Side</div>
                <div class="strong">Sales: {{ number_format($salesAmt > 0 ? $salesAmt : 0, 2) }}</div>
                <div class="strong">Purchase: {{ number_format($purchaseAmt < 0 ? abs($purchaseAmt) : 0, 2) }}</div>
                <div class="strong">Smith: {{ number_format($smithAmt < 0 ? abs($smithAmt) : 0, 2) }}</div>
                <div class="strong">Refinery: {{ number_format($refineAmt < 0 ? abs($refineAmt) : 0, 2) }}</div>
                <div class="strong">Order: {{ number_format($orderAmt > 0 ? $orderAmt : 0, 2) }}</div>
                <div class="strong">Repair: {{ number_format($repairAmt > 0 ? $repairAmt : 0, 2) }}</div>
            </div>
            <div class="card">
                <div class="muted">Payment Side</div>
                <div class="strong">Sales: {{ number_format($salesAmt < 0 ? abs($salesAmt) : 0, 2) }}</div>
                <div class="strong">Purchase: {{ number_format($purchaseAmt > 0 ? $purchaseAmt : 0, 2) }}</div>
                <div class="strong">Smith: {{ number_format($smithAmt > 0 ? $smithAmt : 0, 2) }}</div>
                <div class="strong">Refinery: {{ number_format($refineAmt > 0 ? $refineAmt : 0, 2) }}</div>
                <div class="strong">Order: {{ number_format($orderAmt < 0 ? abs($orderAmt) : 0, 2) }}</div>
                <div class="strong">Repair: {{ number_format($repairAmt < 0 ? abs($repairAmt) : 0, 2) }}</div>
            </div>
        </div>

        @if($summary)
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 100px;">Code</th>
                        <th>Name</th>
                        <th class="num" style="width: 130px;">Total Received Amt</th>
                        <th class="num" style="width: 130px;">Total Paid Amt</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($summaryRows as $row)
                        <tr>
                            <td>{{ $row['accode'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td class="num">{{ number_format($row['receipt'], 2) }}</td>
                            <td class="num">{{ number_format($row['payment'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No summary rows found.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="2">Total</th>
                        <th class="num">{{ number_format(collect($summaryRows)->sum('receipt'), 2) }}</th>
                        <th class="num">{{ number_format(collect($summaryRows)->sum('payment'), 2) }}</th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 90px;">Date</th>
                        <th style="width: 110px;">Voucher</th>
                        <th style="width: 90px;">Staff</th>
                        <th style="width: 260px;">A/c Name</th>
                        <th>Description</th>
                        <th class="num" style="width: 110px;">Receipt</th>
                        <th class="num" style="width: 110px;">Payment</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/y') }}</td>
                            <td>{{ $row['vchno'] }}</td>
                            <td>{{ $row['staff'] }}</td>
                            <td>{{ $row['othername'] }}</td>
                            <td>{{ $row['particular'] }}</td>
                            <td class="num">{{ $row['amount'] < 0 ? number_format(abs($row['amount']), 2) : '' }}</td>
                            <td class="num">{{ $row['amount'] > 0 ? number_format($row['amount'], 2) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">No receipt/payment rows found.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="5">Total</th>
                        <th class="num">{{ number_format(collect($rows)->sum(fn($r) => $r['amount'] < 0 ? abs($r['amount']) : 0), 2) }}</th>
                        <th class="num">{{ number_format(collect($rows)->sum(fn($r) => $r['amount'] > 0 ? $r['amount'] : 0), 2) }}</th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    @else
        <div class="empty">Choose the date range and filters, then click <strong>Show</strong>.</div>
    @endif
</div>
</body>
</html>
