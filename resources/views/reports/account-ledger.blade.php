<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>A/c Ledger</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1400px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 20px; color: #173b63; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 140px; }
        .field.wide { min-width: 280px; flex: 1; }
        label { font-size: 11px; font-weight: 700; color: #375b84; }
        input, select, button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        button { cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        button.ghost { background: #fff; }
        .headbox { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px; }
        .card { border: 1px solid #d8e2ef; border-radius: 8px; background: #f8fbff; padding: 10px; }
        .muted { color: #64748b; font-size: 12px; }
        .strong { font-weight: 700; color: #163b63; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 8px; vertical-align: top; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; z-index: 1; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 70vh; }
        tr.clickable-row { cursor: pointer; }
        tr.clickable-row:hover td { background: #f8fbff; }
        td.voucher-link { color: #17456e; font-weight: 700; cursor: pointer; }
        td.voucher-link:hover { text-decoration: underline; }
        .summary { display: flex; gap: 10px; flex-wrap: wrap; margin: 10px 0; }
        .pill { border: 1px solid #cad7e6; background: #f8fbff; border-radius: 999px; padding: 6px 12px; font-size: 12px; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #c7d4e4; border-radius: 8px; background: #fbfdff; }
        .link-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 8px 0 12px; }
        .status-box { margin: 0 0 12px; padding: 10px 12px; border: 1px solid #b7dbbf; background: #effaf1; color: #1f5e2e; border-radius: 8px; font-size: 12px; font-weight: 600; }
        @media (max-width: 980px) {
            .headbox { grid-template-columns: 1fr; }
            .field, .field.wide { min-width: 100%; }
        }
        @media print {
            .toolbar, .link-row { display: none; }
            body { background: #fff; }
            .wrap { max-width: none; margin: 0; border: 0; }
            .table-wrap { max-height: none; overflow: visible; border: 0; }
            th { position: static; }
        }
    </style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wrap">
    <h1>A/c Ledger</h1>

    @if(session('status'))
        <div class="status-box">{{ session('status') }}</div>
    @endif

    <form method="get" action="{{ url('/accounts/ac-ledger') }}" class="toolbar">
        <div class="field wide">
            <label>A/c Code</label>
            <input name="accode" list="accode-list" value="{{ $accountQuery ?? $code }}" placeholder="Enter account code or account name">
            <datalist id="accode-list">
                @foreach($accountOptions as $option)
                    <option value="{{ $option['accode'] }}">{{ $option['name'] }}</option>
                    <option value="{{ $option['name'] }}">{{ $option['accode'] }}</option>
                @endforeach
            </datalist>
        </div>
        <div class="field">
            <label>From</label>
            <input type="date" name="date1" value="{{ $dateFrom }}">
        </div>
        <div class="field">
            <label>To</label>
            <input type="date" name="date2" value="{{ $dateTo }}">
        </div>
        <div class="field">
            <label>Type</label>
            <select name="type">
                <option value="ordinary" {{ $type === 'ordinary' ? 'selected' : '' }}>Ordinary</option>
                <option value="withwgt" {{ $type === 'withwgt' ? 'selected' : '' }}>With Wgt</option>
                <option value="withrate" {{ $type === 'withrate' ? 'selected' : '' }}>With Rate</option>
            </select>
        </div>
        <div class="field wide">
            <label>Filter (Desc)</label>
            <input name="search" value="{{ $search }}" placeholder="Search description / ledger name / voucher">
        </div>
        <div class="field">
            <label>Amount</label>
            <input name="amount" value="{{ $amountSearch ?? '' }}" placeholder="Exact amount">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <label class="muted"><input type="checkbox" name="susp_only" value="1" {{ $suspOnly ? 'checked' : '' }}> Susp.Pending Only</label>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="primary" type="submit" name="show" value="1">Show</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="ghost" type="button" onclick="window.print()">Print</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="ghost" type="button" id="btnSaveAs">Save As</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="ghost" type="button" id="btnExcel">To Excel</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="ghost" type="button" id="btnPdf">To PDF</button>
        </div>
    </form>

    <form method="post" action="{{ url('/accounts/ac-ledger/repair-sales') }}" class="toolbar" style="margin-top:-4px; margin-bottom:14px;">
        @csrf
        <input type="hidden" name="accode" value="{{ $accountQuery ?? $code }}">
        <input type="hidden" name="date1" value="{{ $dateFrom }}">
        <input type="hidden" name="date2" value="{{ $dateTo }}">
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="search" value="{{ $search }}">
        <input type="hidden" name="amount" value="{{ $amountSearch ?? '' }}">
        <input type="hidden" name="susp_only" value="{{ $suspOnly ? 1 : 0 }}">
        <div class="field">
            <label>&nbsp;</label>
            <button type="submit">Repair Missing Sales Ledger</button>
        </div>
        <div class="field wide" style="min-width:320px;">
            <label>&nbsp;</label>
            <div class="muted">Repairs old sales bills that exist in <code>salesm</code> but are missing their A/C Ledger posting. If A/c Code is blank, it repairs the whole selected date range.</div>
        </div>
    </form>

    @if($account)
        <div class="headbox">
            <div class="card">
                <div class="strong">{{ $account['name'] }} ({{ $account['accode'] }})</div>
                @if($address !== '')
                    <div class="muted" style="margin-top:4px;">{{ $address }}</div>
                @endif
                @if($weightNote !== '')
                    <div class="muted" style="margin-top:6px;">{{ $weightNote }}</div>
                @endif
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

        <div class="link-row">
            @if($linkedCode !== '')
                <a href="{{ url('/accounts/ac-ledger?show=1&date1='.$dateFrom.'&date2='.$dateTo.'&type='.$type.'&accode='.$linkedCode) }}">
                    <button type="button">Show Link Acc</button>
                </a>
            @endif
        </div>

        <div class="summary">
            <div class="pill">Debit: {{ number_format($totals['debit'], 2) }}</div>
            <div class="pill">Credit: {{ number_format($totals['credit'], 2) }}</div>
            <div class="pill">Rows: {{ count($rows) }}</div>
        </div>

        @if(count($rows))
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 90px;">Date</th>
                        <th style="width: 110px;">Voucher No.</th>
                        <th style="width: 220px;">A/c Name</th>
                        <th>Description</th>
                        @if($type === 'withrate')
                            <th class="num" style="width: 90px;">Rate</th>
                        @endif
                        @if($type === 'withwgt')
                            <th class="num" style="width: 90px;">Wgt</th>
                            <th class="num" style="width: 90px;">MC %</th>
                        @endif
                        <th class="num" style="width: 110px;">Debit</th>
                        <th class="num" style="width: 110px;">Credit</th>
                        <th class="num" style="width: 120px;">Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr
                            @if(!empty($row['navigate_url']))
                                class="clickable-row"
                                ondblclick="openLedgerTarget('{{ $row['navigate_url'] }}')"
                                title="Double-click to open bill"
                            @endif
                        >
                            <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/y') }}</td>
                            <td
                                @if(!empty($row['navigate_url']))
                                    class="voucher-link"
                                    ondblclick="event.stopPropagation(); openLedgerTarget('{{ $row['navigate_url'] }}')"
                                    title="Double-click voucher to open"
                                @endif
                            >{{ $row['vchno'] }}</td>
                            <td>{{ $row['othacname'] }}</td>
                            <td>{{ $row['part'] }}</td>
                            @if($type === 'withrate')
                                <td class="num">{{ $row['rate'] != 0 ? number_format($row['rate'], 2) : '' }}</td>
                            @endif
                            @if($type === 'withwgt')
                                <td class="num">{{ $row['wgt'] != 0 ? number_format($row['wgt'], 3) : '' }}</td>
                                <td class="num">{{ $row['mcp'] != 0 ? number_format($row['mcp'], 2) : '' }}</td>
                            @endif
                            <td class="num">{{ $row['debit'] != 0 ? number_format($row['debit'], 2) : '' }}</td>
                            <td class="num">{{ $row['credit'] != 0 ? number_format($row['credit'], 2) : '' }}</td>
                            <td class="num">{{ number_format(abs($row['running_balance']), 2) }} {{ $row['running_side'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="{{ $type === 'ordinary' ? 4 : ($type === 'withrate' ? 5 : 6) }}">Total</th>
                        <th class="num">{{ number_format($totals['debit'], 2) }}</th>
                        <th class="num">{{ number_format($totals['credit'], 2) }}</th>
                        <th class="num">{{ number_format(abs($closingBalance), 2) }} {{ $closingBalance < 0 ? 'Dr' : 'Cr' }}</th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="empty">No ledger rows found for this account and date range.</div>
        @endif
    @elseif($show && $code !== '')
        <div class="empty">Account not found for code <strong>{{ $code }}</strong>.</div>
    @else
        <div class="empty">Choose an account and date range, then click <strong>Show</strong>.</div>
    @endif
</div>
<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>
const LEDGER_TYPE = @json($type);
const LEDGER_ROWS = @json($rows);
const LEDGER_FILE_BASE = @json('ac-ledger-' . $dateFrom . '-to-' . $dateTo . ($code !== '' ? '-' . $code : ''));

function openLedgerTarget(url){
    if (!url) return;
    try {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: 'goldapp:open-module-url',
                url: url,
                title: 'Voucher',
            }, '*');
            return;
        }
    } catch (_) {}
    window.location.href = url;
}

function ledgerHeaders(){
    const hs = [
        ['Date', 'date'],
        ['Voucher No.', 'vchno'],
        ['A/c Name', 'othacname'],
        ['Description', 'part'],
    ];
    if (LEDGER_TYPE === 'withrate') {
        hs.push(['Rate', 'rate']);
    }
    if (LEDGER_TYPE === 'withwgt') {
        hs.push(['Wgt', 'wgt']);
        hs.push(['MC %', 'mcp']);
    }
    hs.push(['Debit', 'debit']);
    hs.push(['Credit', 'credit']);
    hs.push(['Balance', 'running_balance_with_side']);
    return hs;
}

function ledgerExportRows(){
    return (LEDGER_ROWS || []).map(function (row) {
        const out = Object.assign({}, row);
        out.running_balance_with_side = `${Math.abs(Number(row.running_balance || 0)).toFixed(2)} ${row.running_side || ''}`.trim();
        return out;
    });
}

ReportExport.init('btnSaveAs', ledgerHeaders, ledgerExportRows, function(){ return LEDGER_FILE_BASE; });
document.getElementById('btnSaveAs')?.addEventListener('click', function(e){
    e.preventDefault();
    ReportExport.open(ledgerHeaders, ledgerExportRows, function(){ return LEDGER_FILE_BASE; });
});
document.getElementById('btnExcel')?.addEventListener('click', function(){
    ReportExport.open(ledgerHeaders, ledgerExportRows, function(){ return LEDGER_FILE_BASE; });
    setTimeout(function(){
        const xlsCard = document.querySelector('[data-xtype="xls"]');
        if (xlsCard) xlsCard.click();
    }, 0);
});
document.getElementById('btnPdf')?.addEventListener('click', function(){
    ReportExport.open(ledgerHeaders, ledgerExportRows, function(){ return LEDGER_FILE_BASE; });
    setTimeout(function(){
        const pdfCard = document.querySelector('[data-xtype="pdf"]');
        if (pdfCard) pdfCard.click();
    }, 0);
});
</script>
</body>
</html>
