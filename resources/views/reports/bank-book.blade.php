<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bank Book</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1500px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 20px; color: #173b63; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 150px; }
        .field.wide { min-width: 260px; flex: 1; }
        label { font-size: 11px; font-weight: 700; color: #375b84; }
        input, select, button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        button { cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .headbox { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 12px; }
        .card { border: 1px solid #d8e2ef; border-radius: 8px; background: #f8fbff; padding: 10px; }
        .muted { color: #64748b; font-size: 12px; }
        .strong { font-weight: 700; color: #163b63; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 72vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 8px; vertical-align: top; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; z-index: 1; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .group-row td { background: #f7fbff; font-weight: 700; color: #264968; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #c7d4e4; border-radius: 8px; background: #fbfdff; }
        .checkline { display: flex; align-items: center; gap: 6px; height: 34px; color: #64748b; font-size: 12px; }
        .loading-mask { position: fixed; inset: 0; background: rgba(241, 245, 249, 0.82); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-mask.show { display: flex; }
        .loading-card { min-width: 240px; padding: 18px 24px; border-radius: 14px; border: 1px solid #bfd0e6; background: #fff; box-shadow: 0 18px 50px rgba(23, 59, 99, 0.16); text-align: center; }
        .loading-card strong { display: block; font-size: 16px; color: #173b63; margin-bottom: 6px; }
        .loading-card span { font-size: 12px; color: #5f6f84; }
        @media (max-width: 980px) {
            .headbox { grid-template-columns: 1fr; }
            .field, .field.wide { min-width: 100%; }
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
        <span>Loading bank book...</span>
    </div>
</div>
<div class="wrap">
    <h1>Bank Book</h1>

    <form method="get" action="{{ url('/accounts/bank-book') }}" class="toolbar">
        <div class="field wide">
            <label>Bank A/c</label>
            <select name="bankcode">
                @foreach($banks as $bank)
                    <option value="{{ $bank['accode'] }}" {{ $bankCode === $bank['accode'] ? 'selected' : '' }}>
                        {{ $bank['name'] }} ({{ $bank['accode'] }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Date From</label>
            <input type="date" name="date1" value="{{ $dateFrom }}">
        </div>
        <div class="field">
            <label>Date To</label>
            <input type="date" name="date2" value="{{ $dateTo }}">
        </div>
        <div class="field">
            <label>Chq Date From</label>
            <input type="date" name="chqdate1" value="{{ $chequeDateFrom }}">
        </div>
        <div class="field">
            <label>Chq Date To</label>
            <input type="date" name="chqdate2" value="{{ $chequeDateTo }}">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="checkline"><input type="checkbox" name="without_op" value="1" {{ $withoutOpening ? 'checked' : '' }}> Without Op Bal</label>
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

    @if($show && $bankCode !== '')
        <div class="headbox">
            <div class="card">
                <div class="muted">Bank Account</div>
                <div class="strong">{{ $bankName !== '' ? $bankName : $bankCode }} ({{ $bankCode }})</div>
            </div>
            <div class="card">
                <div class="muted">Opening Balance</div>
                <div class="strong">{{ number_format(abs($openingBalance), 2) }} {{ $openingBalance < 0 ? 'Dr' : 'Cr' }}</div>
            </div>
            <div class="card">
                <div class="muted">Closing Balance</div>
                <div class="strong">{{ number_format(abs($closingBalance), 2) }} {{ $closingBalance < 0 ? 'Dr' : 'Cr' }}</div>
            </div>
            <div class="card">
                <div class="muted">Period</div>
                <div class="strong">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
            </div>
        </div>

        @if(count($rows))
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 100px;">Date</th>
                        <th style="width: 110px;">Vch. No.</th>
                        <th style="width: 230px;">A/c</th>
                        <th>Narration</th>
                        <th style="width: 120px;">Cheque No.</th>
                        <th style="width: 100px;">Cheque Dt</th>
                        <th class="num" style="width: 110px;">Debit</th>
                        <th class="num" style="width: 110px;">Credit</th>
                        <th class="num" style="width: 120px;">Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr class="group-row">
                        <td colspan="6">Opening Balance</td>
                        <td class="num">{{ $openingBalance < 0 ? number_format(abs($openingBalance), 2) : '' }}</td>
                        <td class="num">{{ $openingBalance > 0 ? number_format(abs($openingBalance), 2) : '' }}</td>
                        <td class="num"></td>
                    </tr>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                            <td>{{ $row['vchno'] }}</td>
                            <td>{{ $row['opacname'] }}</td>
                            <td>{{ $row['particular'] }}</td>
                            <td>{{ $row['chequeno'] }}</td>
                            <td>{{ $row['chequedate'] !== '' ? \Carbon\Carbon::parse($row['chequedate'])->format('d/m/y') : '' }}</td>
                            <td class="num">{{ $row['debit'] != 0 ? number_format($row['debit'], 2) : '' }}</td>
                            <td class="num">{{ $row['credit'] != 0 ? number_format($row['credit'], 2) : '' }}</td>
                            <td class="num">{{ number_format(abs($row['running_balance']), 2) }} {{ $row['running_balance'] < 0 ? 'Dr' : 'Cr' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr class="group-row">
                        <td colspan="6">Total</td>
                        <td class="num">{{ number_format($totals['debit'], 2) }}</td>
                        <td class="num">{{ number_format($totals['credit'], 2) }}</td>
                        <td class="num"></td>
                    </tr>
                    <tr class="group-row">
                        <td colspan="6">Closing Balance</td>
                        <td class="num">{{ $closingBalance < 0 ? number_format(abs($closingBalance), 2) : '' }}</td>
                        <td class="num">{{ $closingBalance > 0 ? number_format(abs($closingBalance), 2) : '' }}</td>
                        <td class="num"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="empty">No bank-book rows found for the selected filter.</div>
        @endif
    @else
        <div class="empty">Choose a bank account and date range, then click <strong>Show</strong>.</div>
    @endif
</div>
<script>
const bankForm = document.querySelector('form');
const bankMask = document.getElementById('loadingMask');

if (bankForm) {
    bankForm.addEventListener('submit', function () {
        if (bankMask) {
            bankMask.classList.add('show');
            bankMask.setAttribute('aria-hidden', 'false');
        }
    });
}
</script>
</body>
</html>
