<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PDC Report</title>
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
        .checkline { display: flex; align-items: center; gap: 6px; height: 34px; color: #64748b; font-size: 12px; }
        .flash { margin: 0 0 12px; padding: 10px 12px; border-radius: 8px; background: #edf9ef; color: #1d6b38; border: 1px solid #b9e0c4; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 72vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 8px; vertical-align: top; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; z-index: 1; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .summary-row td { background: #f7fbff; font-weight: 700; color: #264968; }
        .actions { display: flex; gap: 6px; }
        .actions form { margin: 0; }
        .actions a, .actions button { text-decoration: none; height: 28px; padding: 0 10px; display: inline-flex; align-items: center; justify-content: center; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #c7d4e4; border-radius: 8px; background: #fbfdff; }
        .loading-mask { position: fixed; inset: 0; background: rgba(241, 245, 249, 0.82); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .loading-mask.show { display: flex; }
        .loading-card { min-width: 240px; padding: 18px 24px; border-radius: 14px; border: 1px solid #bfd0e6; background: #fff; box-shadow: 0 18px 50px rgba(23, 59, 99, 0.16); text-align: center; }
        .loading-card strong { display: block; font-size: 16px; color: #173b63; margin-bottom: 6px; }
        .loading-card span { font-size: 12px; color: #5f6f84; }
        @media (max-width: 980px) {
            .field, .field.wide { min-width: 100%; }
        }
        @media print {
            .toolbar, .actions { display: none; }
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
        <span>Loading PDC report...</span>
    </div>
</div>
<div class="wrap">
    <h1>PDC Report</h1>

    @if(session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    <form method="get" action="{{ url('/accounts/pdc-report') }}" class="toolbar">
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
            <label class="checkline"><input type="checkbox" name="use_chqdate" value="1" {{ $useChequeDate ? 'checked' : '' }}> Chq Dt</label>
        </div>
        <div class="field">
            <label>R/P Type</label>
            <select name="rptype">
                <option value="A" {{ $rpType === 'A' ? 'selected' : '' }}>All</option>
                <option value="P" {{ $rpType === 'P' ? 'selected' : '' }}>Paid</option>
                <option value="R" {{ $rpType === 'R' ? 'selected' : '' }}>Received</option>
            </select>
        </div>
        <div class="field">
            <label>Pending</label>
            <select name="pend">
                <option value="A" {{ $pendType === 'A' ? 'selected' : '' }}>All</option>
                <option value="P" {{ $pendType === 'P' ? 'selected' : '' }}>Pending Only</option>
                <option value="N" {{ $pendType === 'N' ? 'selected' : '' }}>Not Pending Only</option>
            </select>
        </div>
        <div class="field wide">
            <label>Party</label>
            <input name="party_code" list="party-list" value="{{ $partyCode }}" placeholder="Enter party code">
            <datalist id="party-list">
                @foreach($partyOptions as $party)
                    <option value="{{ $party['code'] }}">{{ $party['name'] }}</option>
                @endforeach
            </datalist>
        </div>
        <div class="field">
            <label>Chq No</label>
            <input name="chqno" value="{{ $chequeNo }}" placeholder="Cheque number">
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
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 90px;">Date</th>
                        <th style="width: 120px;">Chq No.</th>
                        <th style="width: 90px;">Chq Date</th>
                        <th style="width: 240px;">A/c Name</th>
                        <th style="width: 220px;">Bank</th>
                        <th>Particulars</th>
                        <th class="num" style="width: 110px;">Receipt</th>
                        <th class="num" style="width: 110px;">Payment</th>
                        <th style="width: 70px;">Pend</th>
                        <th style="width: 180px;">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['tdate'])->format('d/m/y') }}</td>
                            <td>{{ $row['chqno'] }}</td>
                            <td>{{ $row['chqdate'] !== '' ? \Carbon\Carbon::parse($row['chqdate'])->format('d/m/y') : '' }}</td>
                            <td>{{ $row['partyname'] }}</td>
                            <td>{{ $row['bankname'] }}</td>
                            <td>{{ $row['particulars'] }}</td>
                            <td class="num">{{ $row['receipt'] ? number_format($row['receipt'], 2) : '' }}</td>
                            <td class="num">{{ $row['payment'] ? number_format($row['payment'], 2) : '' }}</td>
                            <td>{{ $row['pend'] }}</td>
                            <td>
                                <div class="actions">
                                    <a href="{{ url('/accounts/pdc-collection?module=cheque-clearance&slno=' . $row['slno'] . '&chqno=' . urlencode($row['chqno'])) }}">Clearance</a>
                                    <form method="post" action="{{ url('/accounts/pdc-report/delete/' . $row['slno']) }}" onsubmit="return confirm('Do you want to delete this cheque details?');">
                                        @csrf
                                        <button type="submit">Del</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr class="summary-row">
                        <td colspan="6">Total</td>
                        <td class="num">{{ number_format($totals['receipt'], 2) }}</td>
                        <td class="num">{{ number_format($totals['payment'], 2) }}</td>
                        <td colspan="2">{{ count($rows) }} cheque(s)</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="empty">No PDC rows found for the selected filter.</div>
        @endif
    @else
        <div class="empty">Choose filters and click <strong>Show</strong> to load the PDC report.</div>
    @endif
</div>
<script>
const pdcForm = document.querySelector('form');
const pdcMask = document.getElementById('loadingMask');

if (pdcForm) {
    pdcForm.addEventListener('submit', function () {
        if (pdcMask) {
            pdcMask.classList.add('show');
            pdcMask.setAttribute('aria-hidden', 'false');
        }
    });
}
</script>
</body>
</html>
