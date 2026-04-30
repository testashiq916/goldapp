<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1460px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
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
        td.name-cell { white-space: pre-wrap; }
        tr:hover td { background: #f8fbff; }
        tr.group-row td { color: #174ea6; font-weight: 700; }
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
    </style>
@include('partials.print-layout-head')
</head>
<body>
<div class="loading-mask" id="loadingMask" aria-hidden="true">
    <div class="loading-card">
        <strong>Please wait</strong>
        <span>Loading groupwise expanded list...</span>
    </div>
</div>
<div class="wrap">
    <h1>{{ $title }}</h1>

    <form method="get" action="{{ url('/accounts/groupwise-expanded-list') }}" class="toolbar" id="reportForm">
        <div class="field">
            <label id="date1Label">{{ $term ? 'From' : 'Upto' }}</label>
            <input type="date" name="date1" value="{{ $date1 }}">
        </div>
        <div class="field" id="date2Field" style="{{ $term ? '' : 'display:none;' }}">
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
            <label>&nbsp;</label>
            <label class="checkline"><input type="checkbox" name="grp_only" value="1" {{ $groupOnly ? 'checked' : '' }}> Group only</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="checkline"><input type="checkbox" name="term" value="1" {{ $term ? 'checked' : '' }} id="termToggle"> Term</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="checkline"><input type="checkbox" name="with_balance_only" value="1" {{ $withBalanceOnly ? 'checked' : '' }}> With Balance Only</label>
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
            <div class="field">
                <label>&nbsp;</label>
                <button type="submit" name="export" value="ledgerprint">Print All Ledger</button>
            </div>
        @endif
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

    @if($show)
        <div class="headbox">
            <div class="card">
                <div class="muted">Heading</div>
                <div class="strong">{{ $heading }}</div>
            </div>
            <div class="card">
                <div class="muted">Rows</div>
                <div class="strong">{{ $summary['row_count'] }}</div>
            </div>
            <div class="card">
                <div class="muted">Groups / Accounts</div>
                <div class="strong">{{ $summary['group_count'] }} / {{ $summary['account_count'] }}</div>
            </div>
            <div class="card">
                <div class="muted">Mode</div>
                <div class="strong">{{ $term ? 'Term' : 'Upto Date' }}</div>
            </div>
        </div>

        <div class="summary">
            <div class="pill">Db Total: {{ number_format($summary['db_total'], 2) }}</div>
            <div class="pill">Cr Total: {{ number_format($summary['cr_total'], 2) }}</div>
            <div class="pill">Group Filter: {{ $groupOnly && $groupCode !== '' ? $groupCode : 'All Top Groups' }}</div>
        </div>

        @if(count($rows))
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 120px;">Code</th>
                        <th>Name</th>
                        <th class="num" style="width: 160px;">Amount</th>
                        <th style="width: 90px;">Db/Cr</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr class="{{ $row['ctype'] === 'G' ? 'group-row' : '' }}"
                            @if($row['ctype'] === 'A')
                                ondblclick="window.location='{{ url('/accounts/ac-ledger?show=1&accode=' . rawurlencode($row['accode']) . '&date1=' . $date1 . '&date2=' . $date2) }}'"
                            @endif
                        >
                            <td>{{ $row['accode'] }}</td>
                            <td class="name-cell">{{ $row['display_name'] }}</td>
                            <td class="num">{{ number_format($row['amount'], 2) }}</td>
                            <td>{{ $row['dbcr'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty">No group or account rows found for the selected filters.</div>
        @endif
    @else
        <div class="empty">Choose filters and click <strong>Show</strong> to load the groupwise expanded list.</div>
    @endif
</div>
<script>
const reportForm = document.getElementById('reportForm');
const loadingMask = document.getElementById('loadingMask');
const termToggle = document.getElementById('termToggle');
const date2Field = document.getElementById('date2Field');
const date1Label = document.getElementById('date1Label');

if (reportForm) {
    reportForm.addEventListener('submit', function () {
        if (loadingMask) {
            loadingMask.classList.add('show');
            loadingMask.setAttribute('aria-hidden', 'false');
        }
    });
}

if (termToggle && date2Field && date1Label) {
    termToggle.addEventListener('change', function () {
        date2Field.style.display = this.checked ? '' : 'none';
        date1Label.textContent = this.checked ? 'From' : 'Upto';
    });
}
</script>
</body>
</html>
