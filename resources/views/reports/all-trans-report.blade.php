<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .page { max-width: 1380px; margin: 12px auto; padding: 0 12px 12px; }
        .card { background: #fff; border: 1px solid #d7dfeb; border-radius: 12px; box-shadow: 0 8px 24px rgba(23, 59, 99, 0.06); overflow: hidden; }
        .header { padding: 16px 18px 8px; border-bottom: 1px solid #e6edf7; }
        h1 { margin: 0; font-size: 28px; color: #173b63; }
        .sub { margin-top: 4px; color: #5f6f84; font-size: 13px; }
        .toolbar { padding: 14px 18px; display: flex; flex-direction: column; gap: 12px; }
        .row { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 150px; }
        .field label { font-size: 11px; font-weight: 700; color: #375b84; text-transform: uppercase; letter-spacing: .04em; }
        input, select, button { height: 36px; border: 1px solid #bfd0e6; border-radius: 8px; padding: 0 10px; font-size: 13px; box-sizing: border-box; }
        input, select { background: #fff; }
        button { cursor: pointer; background: #edf4fc; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        button.ghost { background: #fff; }
        .type-grid { display: flex; flex-wrap: wrap; gap: 8px; }
        .type-chip { display: inline-flex; align-items: center; justify-content: center; min-width: 110px; padding: 0 14px; border-radius: 999px; border: 1px solid #c8d8ea; background: #fff; color: #234b75; font-size: 13px; font-weight: 700; }
        .type-chip.active { background: #dcecff; border-color: #5a98d1; color: #113a63; }
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
        tfoot td { background: #f8fbff; font-weight: 700; color: #173b63; }
        .empty { padding: 32px 20px; text-align: center; color: #64748b; }
        .loading-mask { position: fixed; inset: 0; background: rgba(241, 245, 249, 0.82); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-mask.show { display: flex; }
        .loading-card { min-width: 240px; padding: 18px 24px; border-radius: 14px; border: 1px solid #bfd0e6; background: #fff; box-shadow: 0 18px 50px rgba(23, 59, 99, 0.16); text-align: center; }
        .loading-card strong { display: block; font-size: 16px; color: #173b63; margin-bottom: 6px; }
        .loading-card span { font-size: 12px; color: #5f6f84; }
        @media (max-width: 900px) {
            .field { min-width: 100%; }
            .type-chip { min-width: calc(50% - 4px); }
        }
        @media print {
            .toolbar, .summary { display: none; }
            .page { max-width: none; margin: 0; padding: 0; }
            .card { border: 0; box-shadow: none; }
            .header { border-bottom: 0; }
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
        <span>Loading all transactions report...</span>
    </div>
</div>

<div class="page">
    <div class="card">
        <div class="header">
            <h1>{{ $title }}</h1>
            <div class="sub">{{ $currentType }} report</div>
        </div>

        <form method="get" action="{{ url('/accounts/all-trans-report') }}" class="toolbar" id="reportForm">
            <input type="hidden" name="show" value="1">

            <div class="type-grid">
                @foreach($reportTypes as $type)
                    <button
                        type="submit"
                        name="rtype"
                        value="{{ $type }}"
                        class="type-chip {{ $currentType === $type ? 'active' : '' }}"
                    >{{ $type }}</button>
                @endforeach
            </div>

            <div class="row">
                <div class="field">
                    <label>From</label>
                    <input type="date" name="date1" value="{{ $date1 }}">
                </div>
                <div class="field">
                    <label>To</label>
                    <input type="date" name="date2" value="{{ $date2 }}">
                </div>
                <div class="field">
                    <label>Bill Type</label>
                    <select name="billtype" {{ empty($config['uses_billtype']) ? 'disabled' : '' }}>
                        <option value="">All</option>
                        @foreach($billtypes as $item)
                            <option value="{{ $item['code'] }}" {{ $billtype === $item['code'] ? 'selected' : '' }}>
                                {{ $item['code'] }}{{ $item['name'] !== '' ? ' - ' . $item['name'] : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Report Type</label>
                    <input type="text" value="{{ $currentType }}" readonly>
                </div>
            </div>

            <div class="row">
                <button type="submit" class="primary">Show</button>
                <button type="button" onclick="window.print()">Print</button>
                <button type="button" onclick="window.print()">To PDF</button>
                @if($show)
                    <button type="submit" name="export" value="csv">To Excel</button>
                @endif
                <button type="button" class="ghost" onclick="window.parent?.postMessage?.({ type: 'close-active-module' }, '*'); window.close();">Close</button>
            </div>
        </form>

        @if($show)
            <div class="summary">
                <div class="metric">
                    <span>Rows</span>
                    <strong>{{ count($rows) }}</strong>
                </div>
                @foreach($config['columns'] as $column)
                    @if(($column['total'] ?? false) && array_key_exists($column['key'], $totals))
                        <div class="metric">
                            <span>Total {{ $column['label'] }}</span>
                            <strong>{{ number_format((float) $totals[$column['key']], $column['decimals'] ?? 2) }}</strong>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="table-wrap">
                @if(count($rows))
                    <table>
                        <thead>
                        <tr>
                            @foreach($config['columns'] as $column)
                                <th class="{{ ($column['type'] ?? '') === 'number' ? 'num' : '' }}">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $row)
                            <tr>
                                @foreach($config['columns'] as $column)
                                    @php
                                        $value = $row[$column['key']] ?? '';
                                        $type = $column['type'] ?? 'text';
                                    @endphp
                                    <td class="{{ $type === 'number' ? 'num' : '' }}">
                                        @if($type === 'number')
                                            {{ number_format((float) $value, $column['decimals'] ?? 2) }}
                                        @elseif($type === 'date' && $value !== '')
                                            {{ \Carbon\Carbon::parse($value)->format('d/m/Y') }}
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                        @if(count($totals))
                            <tfoot>
                            <tr>
                                @foreach($config['columns'] as $index => $column)
                                    <td class="{{ ($column['type'] ?? '') === 'number' ? 'num' : '' }}">
                                        @if($index === 0)
                                            Total
                                        @elseif(array_key_exists($column['key'], $totals))
                                            {{ number_format((float) $totals[$column['key']], $column['decimals'] ?? 2) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            </tfoot>
                        @endif
                    </table>
                @else
                    <div class="empty">No records found for the selected filters.</div>
                @endif
            </div>
        @else
            <div class="empty">Choose the date range and report type, then click <strong>Show</strong> to load the transaction report.</div>
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
