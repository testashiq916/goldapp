<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; color: #111827; background: #ffffff; }
        .page { max-width: 1200px; margin: 0 auto; padding: 16px 18px 28px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .topbar h1 { margin: 0; font-size: 20px; color: #173b63; }
        .topbar button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 12px; background: #e8f2ff; color: #17456e; font-weight: 700; cursor: pointer; }
        .report-head { margin-bottom: 14px; color: #334155; }
        .ledger { border: 1px solid #d8e2ef; border-radius: 10px; padding: 12px; margin-bottom: 18px; page-break-inside: avoid; }
        .ledger h2 { margin: 0 0 6px; font-size: 16px; color: #173b63; }
        .meta { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 10px; margin-bottom: 10px; }
        .meta-card { border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fbff; padding: 8px 10px; }
        .muted { font-size: 11px; color: #64748b; }
        .strong { font-weight: 700; color: #163b63; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 6px 7px; vertical-align: top; }
        th { background: #edf4fc; text-align: left; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .summary { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
        .pill { border: 1px solid #cad7e6; background: #f8fbff; border-radius: 999px; padding: 6px 12px; font-size: 12px; }
        .empty { padding: 24px; text-align: center; border: 1px dashed #c7d4e4; border-radius: 8px; color: #64748b; }
        @media print {
            .topbar { display: none; }
            .page { max-width: none; padding: 0; }
            .ledger { border: 0; border-radius: 0; padding: 0 0 12px; margin: 0 0 18px; }
        }
    </style>
@include('partials.print-layout-head')
</head>
<body>
<div class="page">
    <div class="topbar">
        <h1>{{ $title }}</h1>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <div class="report-head">
        <div><strong>{{ $heading }}</strong></div>
        <div>{{ $term ? 'Term' : 'Upto Date' }}</div>
    </div>

    @if(count($ledgerSections))
        @foreach($ledgerSections as $section)
            <section class="ledger">
                <h2>{{ $section['name'] }} ({{ $section['accode'] }})</h2>
                <div class="meta">
                    <div class="meta-card">
                        <div class="muted">Address</div>
                        <div class="strong">{{ $section['address'] !== '' ? $section['address'] : '-' }}</div>
                    </div>
                    <div class="meta-card">
                        <div class="muted">From</div>
                        <div class="strong">{{ \Carbon\Carbon::parse($section['date_from'])->format('d/m/Y') }}</div>
                    </div>
                    <div class="meta-card">
                        <div class="muted">To</div>
                        <div class="strong">{{ \Carbon\Carbon::parse($section['date_to'])->format('d/m/Y') }}</div>
                    </div>
                </div>

                <table>
                    <thead>
                    <tr>
                        <th style="width: 90px;">Date</th>
                        <th style="width: 120px;">Voucher</th>
                        <th style="width: 220px;">A/c Name</th>
                        <th>Particulars</th>
                        <th class="num" style="width: 110px;">Debit</th>
                        <th class="num" style="width: 110px;">Credit</th>
                        <th class="num" style="width: 120px;">Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td colspan="4"><strong>Opening Balance</strong></td>
                        <td class="num">{{ $section['opening_balance'] < 0 ? number_format(abs($section['opening_balance']), 2) : '' }}</td>
                        <td class="num">{{ $section['opening_balance'] > 0 ? number_format(abs($section['opening_balance']), 2) : '' }}</td>
                        <td class="num">{{ number_format(abs($section['opening_balance']), 2) }} {{ $section['opening_balance'] < 0 ? 'Dr' : 'Cr' }}</td>
                    </tr>
                    @foreach($section['lines'] as $line)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($line['date'])->format('d/m/Y') }}</td>
                            <td>{{ $line['vchno'] }}</td>
                            <td>{{ $line['other_name'] }}</td>
                            <td>{{ $line['particular'] }}</td>
                            <td class="num">{{ $line['debit'] ? number_format($line['debit'], 2) : '' }}</td>
                            <td class="num">{{ $line['credit'] ? number_format($line['credit'], 2) : '' }}</td>
                            <td class="num">{{ number_format(abs($line['running_balance']), 2) }} {{ $line['running_balance'] < 0 ? 'Dr' : 'Cr' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="summary">
                    <div class="pill">Debit Total: {{ number_format($section['total_debit'], 2) }}</div>
                    <div class="pill">Credit Total: {{ number_format($section['total_credit'], 2) }}</div>
                    <div class="pill">Closing Balance: {{ number_format(abs($section['closing_balance']), 2) }} {{ $section['closing_balance'] < 0 ? 'Dr' : 'Cr' }}</div>
                </div>
            </section>
        @endforeach
    @else
        <div class="empty">No account ledgers available to print for the current selection.</div>
    @endif
</div>
<script>
window.addEventListener('load', function () {
    window.print();
});
</script>
</body>
</html>
