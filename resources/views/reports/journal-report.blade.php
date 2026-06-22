<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Journal Report</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1200px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 20px; color: #173b63; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 150px; }
        label { font-size: 11px; font-weight: 700; color: #375b84; }
        input, button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        button { cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .report { border: 1px solid #d8e2ef; border-radius: 8px; overflow: hidden; }
        .journal-block { padding: 12px 14px; border-bottom: 1px solid #e5ecf5; }
        .journal-head { display: grid; grid-template-columns: 180px 160px 1fr 120px; gap: 10px; align-items: center; margin-bottom: 8px; }
        .journal-head strong { color: #173b63; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #eef3fa; padding: 7px 8px; }
        th { background: #edf4fc; text-align: left; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .narration { margin-top: 8px; color: #52657c; font-style: italic; }
        .actions { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 10px; }
        .actions a { text-decoration: none; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #c7d4e4; border-radius: 8px; background: #fbfdff; }
        .loading-mask { position: fixed; inset: 0; background: rgba(241, 245, 249, 0.82); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-mask.show { display: flex; }
        .loading-card { min-width: 240px; padding: 18px 24px; border-radius: 14px; border: 1px solid #bfd0e6; background: #fff; box-shadow: 0 18px 50px rgba(23, 59, 99, 0.16); text-align: center; }
        .loading-card strong { display: block; font-size: 16px; color: #173b63; margin-bottom: 6px; }
        .loading-card span { font-size: 12px; color: #5f6f84; }
        @media (max-width: 900px) {
            .journal-head { grid-template-columns: 1fr; }
            .field { min-width: 100%; }
        }
        @media print {
            .toolbar, .actions { display: none; }
            body { background: #fff; }
            .wrap { max-width: none; margin: 0; border: 0; }
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
        <span>Loading journal report...</span>
    </div>
</div>
<div class="wrap">
    <h1>Journal</h1>

    <form method="get" action="{{ url('/accounts/journal-report') }}" class="toolbar">
        <div class="field">
            <label>Date From</label>
            <input type="date" name="date1" value="{{ $dateFrom }}">
        </div>
        <div class="field">
            <label>Date To</label>
            <input type="date" name="date2" value="{{ $dateTo }}">
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
        @if(count($rows))
            <div class="report">
                @foreach($rows as $row)
                    <div class="journal-block">
                        <div class="actions">
                            <a href="{{ url('/accounts/journal?slno=' . $row['slno'] . '&vchno=' . urlencode($row['vchno'])) }}">
                                <button type="button">Reprint</button>
                            </a>
                        </div>
                        <div class="journal-head">
                            <div><strong>Vch. No.:</strong> {{ $row['vchno'] }}</div>
                            <div><strong>Date:</strong> {{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</div>
                            <div></div>
                            <div></div>
                        </div>
                        <table>
                            <thead>
                            <tr>
                                <th>Particulars</th>
                                <th class="num" style="width: 140px;">Debit</th>
                                <th class="num" style="width: 140px;">Credit</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($row['lines'] as $line)
                                <tr>
                                    <td>{{ $line['account_name'] }}</td>
                                    <td class="num">{{ $line['debit'] ? number_format($line['debit'], 2) : '' }}</td>
                                    <td class="num">{{ $line['credit'] ? number_format($line['credit'], 2) : '' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        @if($row['particular'] !== '')
                            <div class="narration">{{ $row['particular'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty">No journal vouchers found for the selected period.</div>
        @endif
    @else
        <div class="empty">Choose a date range and click <strong>Show</strong> to load the journal report.</div>
    @endif
</div>
<script>
const journalForm = document.querySelector('form');
const journalMask = document.getElementById('loadingMask');

if (journalForm) {
    journalForm.addEventListener('submit', function () {
        if (journalMask) {
            journalMask.classList.add('show');
            journalMask.setAttribute('aria-hidden', 'false');
        }
    });
}
</script>
</body>
</html>
