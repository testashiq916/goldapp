<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1550px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 20px; color: #173b63; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 150px; }
        .field.wide { min-width: 260px; flex: 1; }
        label { font-size: 11px; font-weight: 700; color: #375b84; }
        input, select, button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        button { cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .checkline { display: flex; align-items: center; gap: 6px; height: 34px; color: #64748b; font-size: 12px; }
        .checkline input[type="checkbox"] { height: auto; }
        .summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
        .pill { border: 1px solid #cad7e6; background: #f8fbff; border-radius: 999px; padding: 6px 12px; font-size: 12px; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 72vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 8px; vertical-align: top; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; z-index: 1; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tr.selected td { background: #f2fff2; }
        .status-tr { color: #a72c2c; font-weight: 700; }
        .status-tg { color: #2457a6; font-weight: 700; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #c7d4e4; border-radius: 8px; background: #fbfdff; }
        .loading-mask {
            position: fixed;
            inset: 0;
            background: rgba(241, 245, 249, 0.82);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .loading-mask.show { display: flex; }
        .loading-card {
            min-width: 260px;
            padding: 18px 24px;
            border-radius: 14px;
            border: 1px solid #bfd0e6;
            background: #ffffff;
            box-shadow: 0 18px 50px rgba(23, 59, 99, 0.16);
            text-align: center;
        }
        .loading-card strong {
            display: block;
            font-size: 16px;
            color: #173b63;
            margin-bottom: 6px;
        }
        .loading-card span {
            font-size: 12px;
            color: #5f6f84;
        }
        @media (max-width: 980px) {
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
<div class="loading-mask" id="loadingMask" aria-hidden="true">
    <div class="loading-card">
        <strong>Please wait</strong>
        <span>Loading receivable/payable summary...</span>
    </div>
</div>
<div class="wrap">
    <h1>{{ $title }}</h1>

    <form method="get" action="{{ url('/accounts/ac-receivable-payable-summary') }}" class="toolbar">
        <input type="hidden" name="mode" value="{{ $mode }}">
        <div class="field">
            <label>{{ $filters['basedOnDueDate'] ? 'From Date' : 'Upto Date' }}</label>
            <input type="date" name="date1" value="{{ $date1 }}">
        </div>
        <div class="field" style="{{ $filters['basedOnDueDate'] ? '' : 'display:none;' }}">
            <label>To Date</label>
            <input type="date" name="date2" value="{{ $date2 }}">
        </div>
        <div class="field">
            <label>Type</label>
            <select name="type">
                <option value="all" {{ $filters['type'] === 'all' ? 'selected' : '' }}>All</option>
                <option value="receivable" {{ $filters['type'] === 'receivable' ? 'selected' : '' }}>Receivable Only</option>
                <option value="payable" {{ $filters['type'] === 'payable' ? 'selected' : '' }}>Payable Only</option>
                <option value="with_balance" {{ $filters['type'] === 'with_balance' ? 'selected' : '' }}>With Balance Only</option>
                <option value="with_transaction" {{ $filters['type'] === 'with_transaction' ? 'selected' : '' }}>With Term Transaction</option>
            </select>
        </div>
        <div class="field">
            <label>Party Type</label>
            <select name="party_type">
                <option value="all" {{ $filters['partyType'] === 'all' ? 'selected' : '' }}>All</option>
                <option value="customers" {{ $filters['partyType'] === 'customers' ? 'selected' : '' }}>Customers</option>
                <option value="suppliers" {{ $filters['partyType'] === 'suppliers' ? 'selected' : '' }}>Suppliers</option>
                <option value="goldsmith" {{ $filters['partyType'] === 'goldsmith' ? 'selected' : '' }}>GoldSmith</option>
                <option value="jewelery" {{ $filters['partyType'] === 'jewelery' ? 'selected' : '' }}>Jewelery</option>
                <option value="refiners" {{ $filters['partyType'] === 'refiners' ? 'selected' : '' }}>Refiners</option>
                <option value="staffs" {{ $filters['partyType'] === 'staffs' ? 'selected' : '' }}>Staffs</option>
                <option value="kuri" {{ $filters['partyType'] === 'kuri' ? 'selected' : '' }}>Kuri/Scheme Party</option>
            </select>
        </div>
        <div class="field">
            <label>Sort On</label>
            <select name="sort">
                <option value="name" {{ $filters['sort'] === 'name' ? 'selected' : '' }}>Clients Name</option>
                <option value="code" {{ $filters['sort'] === 'code' ? 'selected' : '' }}>Clients Code</option>
                <option value="balance" {{ $filters['sort'] === 'balance' ? 'selected' : '' }}>Balance</option>
                <option value="duedate" {{ $filters['sort'] === 'duedate' ? 'selected' : '' }}>Duedate</option>
                <option value="lbdate" {{ $filters['sort'] === 'lbdate' ? 'selected' : '' }}>LBDate</option>
                <option value="lcdate" {{ $filters['sort'] === 'lcdate' ? 'selected' : '' }}>LCDate</option>
            </select>
        </div>
        <div class="field">
            <label>C/o</label>
            <select name="cocode">
                <option value="">All</option>
                @foreach($lookups['cocodes'] as $option)
                    <option value="{{ $option['code'] }}" {{ $filters['coCode'] === $option['code'] ? 'selected' : '' }}>{{ $option['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Group</label>
            <select name="group">
                <option value="">All</option>
                @foreach($lookups['groups'] as $option)
                    <option value="{{ $option['code'] }}" {{ $filters['groupCode'] === $option['code'] ? 'selected' : '' }}>{{ $option['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Route</label>
            <select name="route">
                <option value="">All</option>
                @foreach($lookups['routes'] as $option)
                    <option value="{{ $option['code'] }}" {{ $filters['routeCode'] === $option['code'] ? 'selected' : '' }}>{{ $option['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Area</label>
            <select name="area">
                <option value="">All</option>
                @foreach($lookups['areas'] as $option)
                    <option value="{{ $option['code'] }}" {{ $filters['areaCode'] === $option['code'] ? 'selected' : '' }}>{{ $option['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Min Amt</label>
            <input type="number" step="0.01" name="min_amt" value="{{ $filters['minAmt'] }}">
        </div>
        <div class="field">
            <label>Max Amt</label>
            <input type="number" step="0.01" name="max_amt" value="{{ $filters['maxAmt'] }}">
        </div>
        <div class="field wide">
            <label>In Name</label>
            <input name="name_search" value="{{ $filters['nameSearch'] }}" placeholder="Search in name/address">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="checkline"><input type="checkbox" name="based_on_duedate" value="1" {{ $filters['basedOnDueDate'] ? 'checked' : '' }}> Based On Duedate</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="checkline"><input type="checkbox" name="show_wgt" value="1" {{ $filters['showWeight'] ? 'checked' : '' }}> Show Wgt Bal</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="checkline"><input type="checkbox" name="selected_only" value="1" {{ $filters['selectedOnly'] ? 'checked' : '' }}> Selected Only</label>
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

    @if($show)
        <div class="summary">
            <div class="pill">Rows: {{ $totals['count'] }}</div>
            <div class="pill">To Get: {{ number_format(abs($totals['to_get']), 2) }}</div>
            <div class="pill">To Give: {{ number_format(abs($totals['to_give']), 2) }}</div>
            <div class="pill">Net Total: {{ number_format(abs($totals['net']), 2) }} {{ $totals['net'] > 0 ? '(TG)' : ($totals['net'] < 0 ? '(TR)' : '') }}</div>
            @if($filters['showWeight'])
                <div class="pill">Wgt Bal: {{ number_format($totals['wgt'], 3) }}</div>
            @endif
        </div>

        @if(count($rows))
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 40px;">Sel</th>
                        <th style="width: 90px;">Code</th>
                        <th>Name</th>
                        <th style="width: 90px;">LBDate</th>
                        <th style="width: 90px;">LCDate</th>
                        <th style="width: 90px;">DueDate</th>
                        <th class="num" style="width: 110px;">Balance</th>
                        <th style="width: 70px;">Status</th>
                        @if($filters['showWeight'])
                            <th class="num" style="width: 100px;">Wgt.Bal</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr class="{{ $row['selected'] ? 'selected' : '' }}" ondblclick="window.location='{{ url('/accounts/ac-ledger?show=1&accode=' . rawurlencode($row['accode']) . '&date1=' . ($sessionStart) . '&date2=' . ($filters['basedOnDueDate'] ? $date2 : $date1)) }}'">
                            <td>
                                <input
                                    type="checkbox"
                                    class="row-selector"
                                    value="{{ $row['accode'] }}"
                                    {{ $row['selected'] ? 'checked' : '' }}
                                    onclick="event.stopPropagation(); this.closest('tr').classList.toggle('selected', this.checked);"
                                >
                            </td>
                            <td>{{ $row['accode'] }}</td>
                            <td>{{ $row['address'] }}</td>
                            <td>{{ $row['billdate'] !== '' ? \Carbon\Carbon::parse($row['billdate'])->format('d/m/y') : '' }}</td>
                            <td>{{ $row['lcdate'] !== '' ? \Carbon\Carbon::parse($row['lcdate'])->format('d/m/y') : '' }}</td>
                            <td>{{ $row['cduedate'] !== '' ? \Carbon\Carbon::parse($row['cduedate'])->format('d/m/y') : '' }}</td>
                            <td class="num">{{ number_format($row['balance_abs'], 2) }}</td>
                            <td class="{{ $row['status'] === '(TR)' ? 'status-tr' : 'status-tg' }}">{{ $row['status'] }}</td>
                            @if($filters['showWeight'])
                                <td class="num">{{ number_format($row['wgtbal'], 3) }}</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty">No receivable/payable rows found for the selected filter.</div>
        @endif
    @else
        <div class="empty">Choose filters and click <strong>Show</strong> to load the summary.</div>
    @endif
</div>
<script>
const reportForm = document.querySelector('form');
const loadingMask = document.getElementById('loadingMask');

function showLoading() {
    if (!loadingMask) {
        return;
    }
    loadingMask.classList.add('show');
    loadingMask.setAttribute('aria-hidden', 'false');
}

function syncSelectedRows() {
    if (!reportForm) {
        return;
    }

    reportForm.querySelectorAll('input[name="selected[]"]').forEach(function (input) {
        input.remove();
    });

    document.querySelectorAll('.row-selector:checked').forEach(function (checkbox) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'selected[]';
        hidden.value = checkbox.value;
        reportForm.appendChild(hidden);
    });
}

if (reportForm) {
    reportForm.addEventListener('submit', function () {
        syncSelectedRows();
        showLoading();
    });
}

document.querySelectorAll('input[name="based_on_duedate"]').forEach(function (el) {
    el.addEventListener('change', function () {
        syncSelectedRows();
        showLoading();
        this.form.submit();
    });
});
</script>
</body>
</html>
