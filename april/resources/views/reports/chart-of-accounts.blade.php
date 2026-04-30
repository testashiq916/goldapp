<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .page { max-width: 1440px; margin: 12px auto; padding: 0 12px 12px; }
        .card { background: #fff; border: 1px solid #d7dfeb; border-radius: 12px; box-shadow: 0 8px 24px rgba(23, 59, 99, 0.06); overflow: hidden; }
        .header { padding: 16px 18px 8px; border-bottom: 1px solid #e6edf7; }
        h1 { margin: 0; font-size: 28px; color: #173b63; }
        .sub { margin-top: 4px; color: #5f6f84; font-size: 13px; }
        .toolbar { padding: 14px 18px; display: flex; flex-direction: column; gap: 12px; }
        .row { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 160px; }
        .field label { font-size: 11px; font-weight: 700; color: #375b84; text-transform: uppercase; letter-spacing: .04em; }
        input, select, button { height: 36px; border: 1px solid #bfd0e6; border-radius: 8px; padding: 0 10px; font-size: 13px; box-sizing: border-box; }
        input, select { background: #fff; }
        button { cursor: pointer; background: #edf4fc; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .checks { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; padding-top: 4px; }
        .checks label { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #234b75; font-weight: 600; }
        .checks input { width: 16px; height: 16px; margin: 0; }
        .summary { display: flex; flex-wrap: wrap; gap: 12px; padding: 0 18px 16px; }
        .metric { min-width: 160px; background: #f8fbff; border: 1px solid #d9e7f5; border-radius: 10px; padding: 12px 14px; }
        .metric span { display: block; font-size: 11px; color: #63768d; text-transform: uppercase; letter-spacing: .05em; }
        .metric strong { display: block; margin-top: 4px; font-size: 20px; color: #163a61; }
        .table-wrap { overflow: auto; border-top: 1px solid #e6edf7; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #eef3fa; white-space: nowrap; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; font-size: 11px; color: #4e6480; text-transform: uppercase; letter-spacing: .05em; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        tr:hover td { background: #fafcff; }
        tr.group td { background: #eff6ff; color: #173b63; font-weight: 700; }
        tr.removed td { color: #9a3412; }
        .empty { padding: 32px 20px; text-align: center; color: #64748b; }
        .linkish { color: #1d4ed8; cursor: pointer; }
        .loading-mask { position: fixed; inset: 0; background: rgba(241, 245, 249, 0.82); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-mask.show { display: flex; }
        .loading-card { min-width: 240px; padding: 18px 24px; border-radius: 14px; border: 1px solid #bfd0e6; background: #fff; box-shadow: 0 18px 50px rgba(23, 59, 99, 0.16); text-align: center; }
        .loading-card strong { display: block; font-size: 16px; color: #173b63; margin-bottom: 6px; }
        .loading-card span { font-size: 12px; color: #5f6f84; }
        @media (max-width: 900px) {
            .field { min-width: 100%; }
        }
        @media print {
            .toolbar, .summary { display: none; }
            .page { max-width: none; margin: 0; padding: 0; }
            .card { border: 0; box-shadow: none; }
            .header { border-bottom: 0; }
        }
    </style>
@include('partials.print-layout-head')
</head>
<body>
<div class="loading-mask" id="loadingMask" aria-hidden="true">
    <div class="loading-card">
        <strong>Please wait</strong>
        <span>Loading chart of accounts...</span>
    </div>
</div>

<div class="page">
    <div class="card">
        <div class="header">
            <h1>{{ $title }}</h1>
            <div class="sub">Live balance snapshot as of {{ \Carbon\Carbon::parse($today)->format('d/m/Y') }}</div>
        </div>

        <form method="get" action="{{ url('/accounts/chart-of-accounts') }}" class="toolbar" id="reportForm">
            <input type="hidden" name="show" value="1">

            <div class="row">
                <div class="field">
                    <label>Account Type</label>
                    <select name="type">
                        @foreach($types as $item)
                            <option value="{{ $item }}" {{ $type === $item ? 'selected' : '' }}>{{ $item }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Group</label>
                    <select name="grcode">
                        <option value="">All Groups</option>
                        @foreach($groups as $item)
                            <option value="{{ $item['code'] }}" {{ $groupCode === $item['code'] ? 'selected' : '' }}>
                                {{ $item['code'] }}{{ $item['name'] !== '' ? ' - ' . $item['name'] : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>BS Head</label>
                    <select name="bshead">
                        <option value="">All BS Heads</option>
                        @foreach($bsheads as $item)
                            <option value="{{ $item['code'] }}" {{ $bshead === $item['code'] ? 'selected' : '' }}>
                                {{ $item['code'] }}{{ $item['name'] !== '' ? ' - ' . $item['name'] : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>As Of</label>
                    <input type="text" value="{{ \Carbon\Carbon::parse($today)->format('d/m/Y') }}" readonly>
                </div>
            </div>

            <div class="checks">
                <label><input type="checkbox" name="with_balance" value="1" {{ $withBalance ? 'checked' : '' }}> With Balance Only</label>
                <label><input type="checkbox" name="removed_only" value="1" {{ $removedOnly ? 'checked' : '' }}> Show Removed Only</label>
            </div>

            <div class="row">
                <button type="submit" class="primary">Show</button>
                <button type="submit">Refresh</button>
                <button type="button" onclick="window.print()">Print</button>
                <button type="button" onclick="window.print()">To PDF</button>
                @if($show)
                    <button type="submit" name="export" value="csv">To Excel</button>
                @endif
                <button type="button" onclick="window.location='{{ url('/accounts/ac-ledger') }}'">Account</button>
                <button type="button" onclick="window.close()">Exit</button>
            </div>
        </form>

        @if($show)
            <div class="summary">
                <div class="metric">
                    <span>Accounts</span>
                    <strong>{{ $totals['count'] }}</strong>
                </div>
                @if($type !== 'All')
                    <div class="metric">
                        <span>Total Balance</span>
                        <strong>{{ number_format($totals['abs_balance'], 2) }}</strong>
                    </div>
                @endif
            </div>

            <div class="table-wrap">
                @if(count($rows))
                    <table>
                        <thead>
                        <tr>
                            <th>Code</th>
                            <th>Account Name</th>
                            <th>BS Head</th>
                            <th>Type</th>
                            <th class="num">Opening</th>
                            <th class="num">Movement</th>
                            <th class="num">Balance</th>
                            <th>Db/Cr</th>
                            <th>Removed</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php $currentGroup = null; $groupTotal = 0.0; @endphp
                        @foreach($rows as $index => $row)
                            @php
                                $groupKey = trim(($row['grcode'] ?? '') . '|' . ($row['group_name'] ?? ''));
                            @endphp
                            @if($currentGroup !== $groupKey)
                                @php $currentGroup = $groupKey; @endphp
                                <tr class="group">
                                    <td colspan="9">{{ $row['group_name'] !== '' ? $row['group_name'] : ($row['grcode'] !== '' ? $row['grcode'] : 'Ungrouped') }}</td>
                                </tr>
                            @endif
                            <tr
                                class="{{ $row['removed_flag'] ? 'removed' : '' }}"
                                ondblclick="window.location='{{ url('/accounts/ac-ledger') }}?show=1&accode={{ rawurlencode($row['accode']) }}'"
                            >
                                <td class="linkish">{{ $row['accode'] }}</td>
                                <td>{{ $row['acname'] }}</td>
                                <td>{{ $row['bshead'] }}</td>
                                <td>{{ $row['actype1'] }}</td>
                                <td class="num">{{ number_format($row['opening_balance'], 2) }}</td>
                                <td class="num">{{ number_format($row['movement'], 2) }}</td>
                                <td class="num">{{ number_format($row['balance_abs'], 2) }}</td>
                                <td>{{ $row['balance_type'] }}</td>
                                <td>{{ $row['removed_flag'] ? 'Yes' : '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="empty">No accounts matched the selected filters.</div>
                @endif
            </div>
        @else
            <div class="empty">Click <strong>Show</strong> to load the chart of accounts.</div>
        @endif
    </div>
</div>

<script>
const reportForm = document.getElementById('reportForm');
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
