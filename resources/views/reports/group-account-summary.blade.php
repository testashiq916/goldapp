<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1480px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 20px; color: #173b63; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 150px; }
        .field.wide { min-width: 280px; flex: 1; }
        label { font-size: 11px; font-weight: 700; color: #375b84; }
        input, select, button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        button { cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .checkline { display: flex; align-items: center; gap: 6px; height: 34px; color: #64748b; font-size: 12px; }
        .checkline input[type="checkbox"] { height: auto; }
        .headbox { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 12px; }
        .card { border: 1px solid #d8e2ef; border-radius: 8px; background: #f8fbff; padding: 10px; }
        .muted { color: #64748b; font-size: 12px; }
        .strong { font-weight: 700; color: #163b63; }
        .summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
        .pill { border: 1px solid #cad7e6; background: #f8fbff; border-radius: 999px; padding: 6px 12px; font-size: 12px; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 72vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 8px; vertical-align: top; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; z-index: 1; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tr:hover td { background: #f8fbff; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #c7d4e4; border-radius: 8px; background: #fbfdff; }
        .loading-mask { position: fixed; inset: 0; background: rgba(241, 245, 249, 0.82); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-mask.show { display: flex; }
        .loading-card { min-width: 260px; padding: 18px 24px; border-radius: 14px; border: 1px solid #bfd0e6; background: #ffffff; box-shadow: 0 18px 50px rgba(23, 59, 99, 0.16); text-align: center; }
        .loading-card strong { display: block; font-size: 16px; color: #173b63; margin-bottom: 6px; }
        .loading-card span { font-size: 12px; color: #5f6f84; }
        @media (max-width: 980px) {
            .field, .field.wide { min-width: 100%; }
            .headbox { grid-template-columns: 1fr; }
        }
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
<div class="loading-mask" id="loadingMask" aria-hidden="true">
    <div class="loading-card">
        <strong>Please wait</strong>
        <span>Loading group account summary...</span>
    </div>
</div>
<div class="wrap">
    <h1>{{ $title }}</h1>

    <form method="get" action="{{ url('/accounts/group-ac-summary') }}" class="toolbar">
        <div class="field">
            <label>From</label>
            <input type="date" name="date1" value="{{ $date1 }}">
        </div>
        <div class="field">
            <label>To</label>
            <input type="date" name="date2" value="{{ $date2 }}">
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
            <label>Type</label>
            <select name="type">
                <option value="all" {{ $type === 'all' ? 'selected' : '' }}>All</option>
                <option value="receivable" {{ $type === 'receivable' ? 'selected' : '' }}>Receivable Only</option>
                <option value="payable" {{ $type === 'payable' ? 'selected' : '' }}>Payable Only</option>
                <option value="with_balance" {{ $type === 'with_balance' ? 'selected' : '' }}>With Balance Only</option>
                <option value="with_transaction" {{ $type === 'with_transaction' ? 'selected' : '' }}>With Term Transaction</option>
                <option value="with_balance_or_transactions" {{ $type === 'with_balance_or_transactions' ? 'selected' : '' }}>With Balance or Transactions</option>
            </select>
        </div>
        <div class="field">
            <label>Sort On</label>
            <select name="sort">
                <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>A/c Name</option>
                <option value="code" {{ $sort === 'code' ? 'selected' : '' }}>A/c Code</option>
                <option value="balance" {{ $sort === 'balance' ? 'selected' : '' }}>Balance</option>
            </select>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="checkline"><input type="checkbox" name="relative_to_ob" value="1" {{ $relativeToOb ? 'checked' : '' }}> Relative to OB</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="primary" type="submit" name="show" value="1">Show</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="button" onclick="window.print()">Print</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="button" onclick="window.print()">To PDF</button>
        </div>
        @if($show)
            <div class="field">
                <label>&nbsp;</label>
                <button type="submit" name="export" value="csv">To Excel</button>
            </div>
        @endif
    </form>

    @if($show && $groupName !== '')
        <div class="headbox">
            <div class="card">
                <div class="muted">Group</div>
                <div class="strong">{{ $groupName }} ({{ $groupCode }})</div>
            </div>
            <div class="card">
                <div class="muted">Period</div>
                <div class="strong">{{ \Carbon\Carbon::parse($date1)->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($date2)->format('d/m/Y') }}</div>
            </div>
            <div class="card">
                <div class="muted">Rows</div>
                <div class="strong">{{ $totals['count'] }}</div>
            </div>
            <div class="card">
                <div class="muted">Relative To OB</div>
                <div class="strong">{{ $relativeToOb ? 'Yes' : 'No' }}</div>
            </div>
        </div>

        <div class="summary">
            <div class="pill">Opening: {{ number_format(abs($totals['opening_signed']), 2) }} {{ $totals['opening_signed'] < 0 ? '(Db)' : ($totals['opening_signed'] > 0 ? '(Cr)' : '') }}</div>
            <div class="pill">Debit: {{ number_format($totals['debit'], 2) }}</div>
            <div class="pill">Credit: {{ number_format($totals['credit'], 2) }}</div>
            <div class="pill">Closing: {{ number_format(abs($totals['closing_signed']), 2) }} {{ $totals['closing_signed'] < 0 ? '(Db)' : ($totals['closing_signed'] > 0 ? '(Cr)' : '') }}</div>
        </div>

        @if(count($rows))
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 90px;">Code</th>
                        <th style="min-width: 240px;">Name</th>
                        <th style="min-width: 240px;">Address</th>
                        <th class="num" style="width: 120px;">Op.Bal.</th>
                        <th class="num" style="width: 120px;">Debit</th>
                        <th class="num" style="width: 120px;">Credit</th>
                        <th class="num" style="width: 120px;">Cl.Bal.</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr ondblclick="window.location='{{ url('/accounts/ac-ledger?show=1&accode=' . rawurlencode($row['accode']) . '&date1=' . $date1 . '&date2=' . $date2) }}'">
                            <td>{{ $row['accode'] }}</td>
                            <td>{{ $row['account_name'] }}</td>
                            <td>{{ $row['address'] }}</td>
                            <td class="num">{{ number_format($row['opening_balance'], 2) }} {{ $row['opening_side'] !== '' ? '(' . $row['opening_side'] . ')' : '' }}</td>
                            <td class="num">{{ number_format($row['debit'], 2) }}</td>
                            <td class="num">{{ number_format($row['credit'], 2) }}</td>
                            <td class="num">{{ number_format($row['closing_balance'], 2) }} {{ $row['closing_side'] !== '' ? '(' . $row['closing_side'] . ')' : '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="3">Total</th>
                        <th class="num">{{ number_format(abs($totals['opening_signed']), 2) }} {{ $totals['opening_signed'] < 0 ? '(Db)' : ($totals['opening_signed'] > 0 ? '(Cr)' : '') }}</th>
                        <th class="num">{{ number_format($totals['debit'], 2) }}</th>
                        <th class="num">{{ number_format($totals['credit'], 2) }}</th>
                        <th class="num">{{ number_format(abs($totals['closing_signed']), 2) }} {{ $totals['closing_signed'] < 0 ? '(Db)' : ($totals['closing_signed'] > 0 ? '(Cr)' : '') }}</th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="empty">No account rows found for this group and selected filter.</div>
        @endif
    @elseif($show && $groupCode !== '')
        <div class="empty">Group not found for code <strong>{{ $groupCode }}</strong>.</div>
    @else
        <div class="empty">Choose filters and click <strong>Show</strong> to load the group summary.</div>
    @endif
</div>
<script>
const reportForm = document.querySelector('form');
const loadingMask = document.getElementById('loadingMask');

if (reportForm) {
    reportForm.addEventListener('submit', function () {
        if (loadingMask) {
            loadingMask.classList.add('show');
            loadingMask.setAttribute('aria-hidden', 'false');
        }
    });
}
</script>
</body>
</html>
