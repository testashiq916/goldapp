<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { width: 100%; max-width: none; min-height: calc(100vh - 2px); margin: 0; background: #fff; border: 1px solid #d7dfeb; border-radius: 0; padding: 12px 14px; box-sizing: border-box; }
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
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: calc(100vh - 250px); width: 100%; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 9px 10px; vertical-align: middle; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; z-index: 1; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tr.selected td { background: #f2fff2; }
        .status-tr { color: #a72c2c; font-weight: 700; }
        .status-tg { color: #2457a6; font-weight: 700; }
        .due-inline { display: flex; align-items: center; gap: 8px; min-width: 245px; }
        .due-date-input { width: 150px; height: 38px; padding: 0 10px; font-size: 14px; font-weight: 700; }
        .save-due-btn { height: 38px; padding: 0 16px; font-size: 13px; background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .due-status { min-width: 52px; font-size: 12px; font-weight: 800; color: #166534; }
        .due-status.error { color: #b91c1c; }
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
            .save-due-btn, .due-status { display: none; }
            .due-date-input { border: 0; padding: 0; height: auto; background: transparent; }
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
        <span>Loading {{ $customerBalanceOnly ? 'customer balance' : 'receivable/payable summary' }}...</span>
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
        @if(!$customerBalanceOnly)
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
        @else
        <div class="field">
            <label>Type</label>
            <select name="type">
                <option value="receivable" {{ $filters['type'] === 'receivable' ? 'selected' : '' }}>To Receive (TR)</option>
                <option value="payable" {{ $filters['type'] === 'payable' ? 'selected' : '' }}>To Give (TG)</option>
                <option value="all" {{ $filters['type'] === 'all' ? 'selected' : '' }}>All</option>
            </select>
        </div>
            <input type="hidden" name="party_type" value="customers">
        @endif
        <div class="field">
            <label>Sort On</label>
            <select name="sort">
                <option value="name" {{ $filters['sort'] === 'name' ? 'selected' : '' }}>Clients Name</option>
                <option value="code" {{ $filters['sort'] === 'code' ? 'selected' : '' }}>Clients Code</option>
                <option value="balance" {{ $filters['sort'] === 'balance' ? 'selected' : '' }}>Balance</option>
                @if($customerBalanceOnly)
                <option value="duedate" {{ $filters['sort'] === 'duedate' ? 'selected' : '' }}>Duedate</option>
                @else
                <option value="duedate" {{ $filters['sort'] === 'duedate' ? 'selected' : '' }}>Duedate</option>
                <option value="lbdate" {{ $filters['sort'] === 'lbdate' ? 'selected' : '' }}>LBDate</option>
                <option value="lcdate" {{ $filters['sort'] === 'lcdate' ? 'selected' : '' }}>LCDate</option>
                @endif
            </select>
        </div>
        @if(!$customerBalanceOnly)
        <div class="field">
            <label>C/o</label>
            <select name="cocode">
                <option value="">All</option>
                @foreach($lookups['cocodes'] as $option)
                    <option value="{{ $option['code'] }}" {{ $filters['coCode'] === $option['code'] ? 'selected' : '' }}>{{ $option['name'] }}</option>
                @endforeach
            </select>
        </div>
        @endif
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
        @if(!$customerBalanceOnly)
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
        @endif
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
                <button type="submit" name="export" value="excel">📥 To Excel</button>
            </div>
        @endif
    </form>

    @if($show)
        <div class="summary">
            <div class="pill">Rows: {{ $totals['count'] }}</div>
            @if($customerBalanceOnly)
                <div class="pill">To Receive: {{ number_format(abs($totals['to_get']), 2) }}</div>
                <div class="pill">To Give: {{ number_format(abs($totals['to_give']), 2) }}</div>
                <div class="pill">Customer Balance: {{ number_format(abs($totals['net']), 2) }} {{ $totals['net'] > 0 ? '(TG)' : ($totals['net'] < 0 ? '(TR)' : '') }}</div>
            @else
                <div class="pill">To Get: {{ number_format(abs($totals['to_get']), 2) }}</div>
                <div class="pill">To Give: {{ number_format(abs($totals['to_give']), 2) }}</div>
                <div class="pill">Net Total: {{ number_format(abs($totals['net']), 2) }} {{ $totals['net'] > 0 ? '(TG)' : ($totals['net'] < 0 ? '(TR)' : '') }}</div>
            @endif
            @if($filters['showWeight'])
                <div class="pill">Wgt Bal: {{ number_format($totals['wgt'], 3) }}</div>
            @endif
        </div>

        @if(count($rows))
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        @if(!$customerBalanceOnly)
                        <th style="width: 40px;">Sel</th>
                        @endif
                        <th style="width: 90px;">Code</th>
                        <th>Name</th>
                        @if($customerBalanceOnly)
                            <th style="width: 120px;">Mobile</th>
                            <th style="width: 110px;">Phone</th>
                            <th>Address</th>
                            <th style="width: 270px;">DueDate</th>
                        @endif
                        @if(!$customerBalanceOnly)
                        <th style="width: 90px;">LBDate</th>
                        <th style="width: 90px;">LCDate</th>
                        <th style="width: 270px;">DueDate</th>
                        @endif
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
                            @if(!$customerBalanceOnly)
                            <td>
                                <input
                                    type="checkbox"
                                    class="row-selector"
                                    value="{{ $row['accode'] }}"
                                    {{ $row['selected'] ? 'checked' : '' }}
                                    onclick="event.stopPropagation(); this.closest('tr').classList.toggle('selected', this.checked);"
                                >
                            </td>
                            @endif
                            <td>{{ $row['accode'] }}</td>
                            <td>{{ $customerBalanceOnly ? $row['name'] : $row['address'] }}</td>
                            @if($customerBalanceOnly)
                                <td>{{ $row['mobile'] }}</td>
                                <td>{{ $row['telephone'] }}</td>
                                <td>{{ $row['address_only'] }}</td>
                                <td>
                                    <div class="due-inline" onclick="event.stopPropagation();" ondblclick="event.stopPropagation();">
                                        <input
                                            type="date"
                                            class="due-date-input"
                                            value="{{ $row['cduedate'] }}"
                                            data-code="{{ $row['accode'] }}"
                                        >
                                        <button type="button" class="save-due-btn" data-code="{{ $row['accode'] }}">Save</button>
                                        <span class="due-status" aria-live="polite"></span>
                                    </div>
                                </td>
                            @endif
                            @if(!$customerBalanceOnly)
                            <td>{{ $row['billdate'] !== '' ? \Carbon\Carbon::parse($row['billdate'])->format('d/m/y') : '' }}</td>
                            <td>{{ $row['lcdate'] !== '' ? \Carbon\Carbon::parse($row['lcdate'])->format('d/m/y') : '' }}</td>
                            <td>
                                <div class="due-inline" onclick="event.stopPropagation();" ondblclick="event.stopPropagation();">
                                    <input
                                        type="date"
                                        class="due-date-input"
                                        value="{{ $row['cduedate'] }}"
                                        data-code="{{ $row['accode'] }}"
                                    >
                                    <button type="button" class="save-due-btn" data-code="{{ $row['accode'] }}">Save</button>
                                    <span class="due-status" aria-live="polite"></span>
                                </div>
                            </td>
                            @endif
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
const dueUpdateUrl = @json(url('/api/accounts/ac-receivable-payable-summary/duedate'));
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

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

async function saveDueDate(button) {
    const wrap = button.closest('.due-inline');
    const input = wrap?.querySelector('.due-date-input');
    const status = wrap?.querySelector('.due-status');
    const code = button.dataset.code || input?.dataset.code || '';
    const duedate = input?.value || '';

    if (!input || !status) {
        return;
    }
    if (!duedate) {
        status.textContent = 'Date?';
        status.classList.add('error');
        input.focus();
        return;
    }

    button.disabled = true;
    status.textContent = 'Saving';
    status.classList.remove('error');

    try {
        const res = await fetch(dueUpdateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ code: code, duedate: duedate }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            throw new Error(data.message || 'Save failed');
        }
        input.value = data.duedate || duedate;
        status.textContent = 'Saved';
        setTimeout(function () {
            if (status.textContent === 'Saved') {
                status.textContent = '';
            }
        }, 1800);
    } catch (err) {
        status.textContent = err.message || 'Error';
        status.classList.add('error');
    } finally {
        button.disabled = false;
    }
}

document.querySelectorAll('.save-due-btn').forEach(function (button) {
    button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        saveDueDate(button);
    });
});

document.querySelectorAll('.due-date-input').forEach(function (input) {
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            event.stopPropagation();
            const button = input.closest('.due-inline')?.querySelector('.save-due-btn');
            if (button) {
                saveDueDate(button);
            }
        }
    });
});
</script>
</body>
</html>
