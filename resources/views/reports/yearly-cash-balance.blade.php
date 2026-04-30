<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Yearly Cash Balance</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1500px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 20px; color: #173b63; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 160px; }
        label { font-size: 11px; font-weight: 700; color: #375b84; }
        input, button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        button { cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .headbox { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 12px; }
        .card { border: 1px solid #d8e2ef; border-radius: 8px; background: #f8fbff; padding: 10px; }
        .muted { color: #64748b; font-size: 12px; }
        .strong { font-weight: 700; color: #163b63; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 76vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; border-right: 1px solid #e5ecf5; padding: 8px 6px; text-align: right; white-space: nowrap; }
        th:first-child, td:first-child { text-align: center; position: sticky; left: 0; background: #fff; z-index: 1; }
        th { position: sticky; top: 0; background: #edf4fc; color: #173b63; z-index: 2; }
        thead th:first-child { background: #edf4fc; z-index: 3; }
        td.empty { background: #fafcff; color: #c2ccda; }
        td.negative { color: #c0392b; font-weight: 700; }
        td.positive { color: #1f2937; font-weight: 600; }
        td a { color: inherit; text-decoration: none; display: block; }
        td a:hover { text-decoration: underline; }
        .hint { margin: 10px 0 0; color: #64748b; font-size: 12px; }
        .loading-mask { position: fixed; inset: 0; background: rgba(241, 245, 249, 0.82); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-mask.show { display: flex; }
        .loading-card { min-width: 240px; padding: 18px 24px; border-radius: 14px; border: 1px solid #bfd0e6; background: #fff; box-shadow: 0 18px 50px rgba(23, 59, 99, 0.16); text-align: center; }
        .loading-card strong { display: block; font-size: 16px; color: #173b63; margin-bottom: 6px; }
        .loading-card span { font-size: 12px; color: #5f6f84; }
        @media (max-width: 980px) {
            .headbox { grid-template-columns: 1fr; }
            .field { min-width: 100%; }
        }
        @media print {
            .toolbar { display: none; }
            body { background: #fff; }
            .wrap { max-width: none; margin: 0; border: 0; }
            .table-wrap { max-height: none; overflow: visible; border: 0; }
            th { position: static; }
            th:first-child, td:first-child { position: static; }
        }
    </style>
@include('partials.print-layout-head')
</head>
<body>
<div class="loading-mask" id="loadingMask" aria-hidden="true">
    <div class="loading-card">
        <strong>Please wait</strong>
        <span>Loading yearly cash balance...</span>
    </div>
</div>
<div class="wrap">
    <h1>Yearly Cash Balance</h1>

    <form method="get" action="{{ url('/accounts/yearly-cash-balance') }}" class="toolbar">
        <div class="field">
            <label>Financial Year Start</label>
            <input type="number" min="2000" max="2100" name="year" value="{{ $fyStartYear }}">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="primary" type="submit">Show</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="button" onclick="window.print()">Print</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="button" onclick="window.print()">To PDF</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="submit" name="export" value="csv">To Excel</button>
        </div>
    </form>

    <div class="headbox">
        <div class="card">
            <div class="muted">Period</div>
            <div class="strong">{{ \Carbon\Carbon::parse($fyStart)->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($fyEnd)->format('d/m/Y') }}</div>
        </div>
        <div class="card">
            <div class="muted">Opening Display Balance</div>
            <div class="strong">{{ number_format($openingDisplay, 2) }}</div>
        </div>
        <div class="card">
            <div class="muted">Account</div>
            <div class="strong">CASH</div>
        </div>
        <div class="card">
            <div class="muted">Control Level</div>
            <div class="strong">{{ $gilevel }}</div>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th style="min-width:72px;">Day</th>
                @foreach($monthLabels as $label)
                    <th style="min-width:92px;">{{ strtoupper($label) }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @for($day = 1; $day <= 31; $day++)
                <tr>
                    <td>{{ $day }}</td>
                    @for($monthIndex = 1; $monthIndex <= 12; $monthIndex++)
                        @php
                            $value = $grid[$day][$monthIndex];
                            $linkKey = $day . '-' . $monthIndex;
                            $date = $datesWithLinks[$linkKey] ?? null;
                            $class = $value === null ? 'empty' : ($value < 0 ? 'negative' : 'positive');
                        @endphp
                        <td class="{{ $class }}">
                            @if($value === null)
                                &nbsp;
                            @elseif($date)
                                <a href="{{ url('/accounts/ac-ledger?show=1&accode=CASH&date1=' . $date . '&date2=' . $date) }}">
                                    {{ number_format($value, 2) }}
                                </a>
                            @else
                                {{ number_format($value, 2) }}
                            @endif
                        </td>
                    @endfor
                </tr>
            @endfor
            </tbody>
        </table>
    </div>

    <div class="hint">Cells are shown only for dates where the CASH day total changed. Click a populated value to open the CASH ledger for that exact date.</div>
</div>
<script>
const yearlyForm = document.querySelector('form');
const yearlyMask = document.getElementById('loadingMask');

if (yearlyForm) {
    yearlyForm.addEventListener('submit', function () {
        if (yearlyMask) {
            yearlyMask.classList.add('show');
            yearlyMask.setAttribute('aria-hidden', 'false');
        }
    });
}
</script>
</body>
</html>
