<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Day Book</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 13px; background: #c0c0c0; padding: 8px; }

        .toolbar {
            background: #c0c0c0; padding: 6px 8px; display: flex; align-items: center;
            gap: 10px; flex-wrap: wrap; border: 2px outset #fff; margin-bottom: 4px;
        }
        .toolbar label { font-weight: 700; font-size: 13px; }
        .toolbar input[type="date"] {
            font-weight: 700; text-align: center; padding: 3px 6px;
            border: 2px inset #fff; width: 140px; color: #000080;
        }
        .toolbar select { padding: 3px 6px; border: 2px inset #fff; }
        .toolbar button {
            padding: 4px 16px; font-weight: 700; border: 2px outset #fff;
            background: #c0c0c0; cursor: pointer; font-size: 13px;
        }
        .toolbar button:active { border-style: inset; }
        .checkbox-group { display: flex; align-items: center; gap: 4px; font-size: 12px; }

        .report-area {
            background: #fff; border: 2px inset #fff; padding: 10px;
            min-height: 400px; overflow: auto; max-height: 75vh;
        }

        .report-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .report-table th {
            background: #f0f0f0; font-weight: 700; padding: 4px 6px;
            border: 1px solid #999; text-align: center;
        }
        .report-table td { padding: 3px 6px; border-bottom: 1px solid #e0e0e0; }
        .report-table .amt { text-align: right; font-family: 'Courier New', monospace; }
        .report-table .total-row td { font-weight: 700; border-top: 2px solid #000; }
        .report-table .balance-row td { font-weight: 700; }
        .report-table .group-trailer td { background: #f0f0f0; font-weight: 700; border-top: 1px solid #999; }
        .report-table .diff-error td { color: red; }

        .report-header { text-align: left; margin-bottom: 8px; }
        .report-header h2 { font-size: 15px; text-decoration: underline; }
        .report-header .period { font-size: 12px; margin-top: 4px; }
        .company-header { font-size: 14px; font-weight: 700; text-align: center; }
        .company-addr { font-size: 12px; font-weight: 700; text-align: center; margin-bottom: 6px; }

        .no-data { padding: 40px; text-align: center; color: #888; }

        @media print {
            .toolbar { display: none; }
            body { background: #fff; padding: 0; }
            .report-area { border: none; max-height: unset; overflow: visible; }
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>

@php
    function dbFmt(float $n, int $d = 2): string {
        return number_format($n, $d, '.', ',');
    }
    function dbFmtBlank(float $n, int $d = 2): string {
        return $n == 0 ? '' : number_format($n, $d, '.', ',');
    }
    function dbDate(string $d): string {
        $ts = strtotime($d);
        return $ts ? date('d/m/Y', $ts) : $d;
    }
@endphp

<!-- Toolbar -->
<form method="GET" class="toolbar" id="reportForm">
    <label>Date From:</label>
    <input type="date" name="date1" value="{{ $dateFrom }}">

    <label>Date To:</label>
    <input type="date" name="date2" value="{{ $dateTo }}">

    <select name="form" id="formSelect" onchange="onFormChange()">
        <option value="form1" {{ $formType === 'form1' ? 'selected' : '' }}>Form 1 - Cash Book</option>
        <option value="form2" {{ $formType === 'form2' ? 'selected' : '' }}>Form 2 - By Voucher</option>
        <option value="form3" {{ $formType === 'form3' ? 'selected' : '' }}>Form 3 - Detailed</option>
        <option value="form4" {{ $formType === 'form4' ? 'selected' : '' }}>Form 4 - Receipt/Payment</option>
        <option value="form5" {{ $formType === 'form5' ? 'selected' : '' }}>Form 5 - Full Daily Report</option>
    </select>

    <button type="submit" name="show" value="1">Show</button>

    <div class="checkbox-group" id="cbBreakDay" style="display:{{ $formType === 'form1' ? 'flex' : 'none' }}">
        <input type="checkbox" name="breakday" id="breakday" {{ $breakDay ? 'checked' : '' }}>
        <label for="breakday">Break Day Total</label>
    </div>

    <div class="checkbox-group" id="cbWithDiff" style="display:{{ $formType === 'form2' ? 'flex' : 'none' }}">
        <input type="checkbox" name="withdiff" id="withdiff" {{ $withDiff ? 'checked' : '' }}>
        <label for="withdiff">With Diff Only</label>
    </div>

    <button type="button" onclick="window.print()">Print</button>
    <button type="button" id="btnSaveAs">Save As</button>
</form>

<!-- Report Display Area -->
<div class="report-area">

@if (!$showData)
    <div class="no-data">Select date range and click <strong>Show</strong> to generate report.</div>

@elseif ($formType === 'form1')
{{-- ═══ FORM 1 — Cash Book with running balance ═══ --}}
@php
    $runningBal = -$opbal;
    $sumDebit  = ($opbal < 0) ? abs($opbal) : 0;
    $sumCredit = ($opbal > 0) ? abs($opbal) : 0;
@endphp
    <div class="report-header">
        <h2>Day Book</h2>
        <div class="period">
            @if ($dateFrom === $dateTo)
                As On {{ dbDate($dateFrom) }}
            @else
                From {{ dbDate($dateFrom) }} To {{ dbDate($dateTo) }}
            @endif
        </div>
    </div>
    <table class="report-table" id="reportTable">
        <thead>
            <tr>
                <th>Date</th><th>A/c Name</th><th>Voucher</th>
                <th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="balance-row">
                <td colspan="4">Op.Cash Balance :</td>
                <td class="amt">{{ $opbal < 0 ? dbFmtBlank(abs($opbal)) : '' }}</td>
                <td class="amt">{{ $opbal > 0 ? dbFmtBlank(abs($opbal)) : '' }}</td>
                <td class="amt"></td>
            </tr>
            @foreach ($rows as $row)
            @php
                $amt    = (float)($row['daybook_amount'] ?? 0);
                $debit  = ($amt > 0) ? abs($amt) : 0;
                $credit = ($amt < 0) ? abs($amt) : 0;
                $runningBal += $amt;
                $sumDebit   += $debit;
                $sumCredit  += $credit;
            @endphp
            <tr>
                <td>{{ dbDate($row['tdate']) }}</td>
                <td>{{ $row['accountm_name'] }}</td>
                <td>{{ $row['vchno'] ?? '' }}</td>
                <td>{{ $row['part'] ?? '' }}</td>
                <td class="amt">{{ dbFmtBlank($debit) }}</td>
                <td class="amt">{{ dbFmtBlank($credit) }}</td>
                <td class="amt">{{ dbFmt($runningBal) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4">Total :</td>
                <td class="amt">{{ dbFmt($sumDebit) }}</td>
                <td class="amt">{{ dbFmt($sumCredit) }}</td>
                <td></td>
            </tr>
            <tr class="balance-row">
                <td colspan="4">Cl.Cash Balance :</td>
                <td class="amt">{{ $clbal < 0 ? dbFmt(abs($clbal)) : '' }}</td>
                <td class="amt">{{ $clbal > 0 ? dbFmt(abs($clbal)) : '' }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    @if (empty($rows))
        <div class="no-data">No transactions found for selected period.</div>
    @endif

@elseif ($formType === 'form2')
{{-- ═══ FORM 2 — Grouped by slno ═══ --}}
@php $grandDebit = 0; $grandCredit = 0; @endphp
    <div class="report-header">
        <div class="period">Daybook From {{ dbDate($dateFrom) }} To {{ dbDate($dateTo) }}</div>
    </div>
    <table class="report-table" id="reportTable">
        <thead>
            <tr>
                <th>Date</th><th>A/c Name</th><th>Debit</th><th>Credit</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $slno => $group)
            @php
                $db     = round($group['db'], 2);
                $cr     = round($group['cr'], 2);
                $isDiff = ($db !== $cr);
                $grandDebit  += $db;
                $grandCredit += $cr;
            @endphp
            @foreach ($group['rows'] as $row)
            @php
                $amt       = (float)$row['daybook_amount'];
                $debitStr  = ($amt < 0) ? dbFmt(abs($amt)) : '';
                $creditStr = ($amt > 0) ? dbFmt($amt) : '';
            @endphp
            <tr>
                <td>{{ dbDate($row['daybook_tdate']) }}</td>
                <td>{{ $row['accountm_name'] }}</td>
                <td class="amt">{{ $debitStr }}</td>
                <td class="amt">{{ $creditStr }}</td>
            </tr>
            @endforeach
            <tr class="group-trailer {{ $isDiff ? 'diff-error' : '' }}">
                <td colspan="2">Particulars : {{ $group['particular'] . '  ' . $group['vchno'] }}</td>
                <td class="amt">{{ dbFmt($db) }}</td>
                <td class="amt">{{ dbFmt($cr) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="no-data">No data found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2">Total :</td>
                <td class="amt">{{ dbFmt($grandDebit) }}</td>
                <td class="amt">{{ dbFmt($grandCredit) }}</td>
            </tr>
        </tfoot>
    </table>

@elseif ($formType === 'form3')
{{-- ═══ FORM 3 — Detailed with A/C Code ═══ --}}
@php $sumDebit = 0; $sumCredit = 0; @endphp
    <div class="company-header">{{ $companyName }}</div>
    @if ($companyAddr)
    <div class="company-addr">{{ $companyAddr }}</div>
    @endif
    <div class="report-header">
        <h2>Day Book</h2>
        <div class="period">
            @if ($dateFrom === $dateTo)
                As On {{ dbDate($dateFrom) }}
            @else
                From {{ dbDate($dateFrom) }} To {{ dbDate($dateTo) }}
            @endif
        </div>
    </div>
    <table class="report-table" id="reportTable">
        <thead>
            <tr>
                <th>A/C Code</th><th>A/c Name</th><th>Voucher</th>
                <th>Description</th><th>Debit</th><th>Credit</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
            @php
                $amt    = (float)$row['daybook_amount'];
                $debit  = ($amt < 0) ? abs($amt) : 0;
                $credit = ($amt > 0) ? $amt : 0;
                $sumDebit  += $debit;
                $sumCredit += $credit;
            @endphp
            <tr>
                <td>{{ $row['daybook_accode'] }}</td>
                <td>{{ $row['accountm_name'] }}</td>
                <td>{{ $row['daybookpart_vchno'] ?? '' }}</td>
                <td>{{ $row['daybookpart_particular'] ?? '' }}</td>
                <td class="amt">{{ dbFmtBlank($debit) }}</td>
                <td class="amt">{{ dbFmtBlank($credit) }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="no-data">No data found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4">Total :</td>
                <td class="amt">{{ dbFmt($sumDebit) }}</td>
                <td class="amt">{{ dbFmt($sumCredit) }}</td>
            </tr>
        </tfoot>
    </table>

@elseif ($formType === 'form4')
{{-- ═══ FORM 4 — Receipt/Payment grouped by date ═══ --}}
    <div class="company-header">{{ $companyName }}</div>
    @if ($companyAddr)
    <div class="company-addr">{{ $companyAddr }}</div>
    @endif

    @forelse ($groups as $dateKey => $dateGroup)
    <div class="report-header" style="margin-top:12px;">
        <strong>DAY BOOK FOR THE DAY {{ dbDate($dateKey) }}</strong>
    </div>
    <table class="report-table" id="reportTable_{{ $loop->index }}">
        <thead>
            <tr>
                <th>Particulars</th><th>Note</th><th>Receipt</th><th>Payment</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dateGroup['rows'] as $row)
            <tr>
                <td>{{ $row['acname'] }}</td>
                <td>{{ $row['particular'] ?? '' }}</td>
                <td class="amt">{{ dbFmtBlank((float)$row['dbamt']) }}</td>
                <td class="amt">{{ dbFmtBlank((float)$row['cramt']) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
        @php
            $totdb   = $dateGroup['totdb'];
            $totcr   = $dateGroup['totcr'];
            $balance = $totdb - $totcr;
        @endphp
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td class="amt">{{ dbFmt($totdb) }}</td>
                <td class="amt">{{ dbFmt($totcr) }}</td>
            </tr>
            <tr class="balance-row">
                <td colspan="2">Balance</td>
                <td class="amt">{{ $balance > 0 ? dbFmt($balance) : '' }}</td>
                <td class="amt">{{ $balance < 0 ? dbFmt(abs($balance)) : '' }}</td>
            </tr>
        </tfoot>
    </table>
    @empty
    <div class="no-data">No data found for selected period.</div>
    @endforelse

@elseif ($formType === 'form5')
{{-- ═══ FORM 5 — Full Daily Report (all transactions combined) ═══ --}}
@php
    $f5 = $form5 ?: [];
    $tot = $f5['totals'] ?? [];
    $cashFlow = ($tot['receipts_amt'] ?? 0) - ($tot['payments_amt'] ?? 0);
@endphp
    <div class="company-header">{{ $companyName }}</div>
    @if ($companyAddr)
    <div class="company-addr">{{ $companyAddr }}</div>
    @endif
    <div class="report-header">
        <h2>Full Daily Report</h2>
        <div class="period">
            @if ($dateFrom === $dateTo)
                As On {{ dbDate($dateFrom) }}
            @else
                From {{ dbDate($dateFrom) }} To {{ dbDate($dateTo) }}
            @endif
        </div>
    </div>

    {{-- Summary tiles --}}
    <table class="report-table" id="reportTable" style="margin-bottom:14px">
        <thead>
            <tr>
                <th>Category</th><th>Count</th><th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Sales</td><td class="amt">{{ (int)($tot['sales_count'] ?? 0) }}</td><td class="amt">{{ dbFmt((float)($tot['sales_amt'] ?? 0)) }}</td></tr>
            <tr><td>Sales Returns</td><td class="amt">{{ (int)($tot['sret_count'] ?? 0) }}</td><td class="amt">{{ dbFmt((float)($tot['sret_amt'] ?? 0)) }}</td></tr>
            <tr><td>Purchases</td><td class="amt">{{ (int)($tot['purch_count'] ?? 0) }}</td><td class="amt">{{ dbFmt((float)($tot['purch_amt'] ?? 0)) }}</td></tr>
            <tr><td>Purchase Returns</td><td class="amt">{{ (int)($tot['pret_count'] ?? 0) }}</td><td class="amt">{{ dbFmt((float)($tot['pret_amt'] ?? 0)) }}</td></tr>
            <tr><td>Cash Receipts</td><td class="amt">{{ (int)($tot['receipts_count'] ?? 0) }}</td><td class="amt">{{ dbFmt((float)($tot['receipts_amt'] ?? 0)) }}</td></tr>
            <tr><td>Cash Payments</td><td class="amt">{{ (int)($tot['payments_count'] ?? 0) }}</td><td class="amt">{{ dbFmt((float)($tot['payments_amt'] ?? 0)) }}</td></tr>
        </tbody>
        <tfoot>
            <tr class="balance-row"><td>Opening Cash Balance</td><td></td><td class="amt">{{ dbFmt($opbal) }}</td></tr>
            <tr class="balance-row"><td>Net Cash Flow (Receipts - Payments)</td><td></td><td class="amt">{{ dbFmt($cashFlow) }}</td></tr>
            <tr class="total-row"><td>Closing Cash Balance</td><td></td><td class="amt">{{ dbFmt($clbal) }}</td></tr>
        </tfoot>
    </table>

    {{-- Sales --}}
    @if (!empty($f5['sales']))
    <div class="report-header"><strong>Sales Bills ({{ count($f5['sales']) }})</strong></div>
    <table class="report-table" id="reportTable_sales" style="margin-bottom:14px">
        <thead><tr><th>Date</th><th>Bill No</th><th>Customer</th><th>Bill Amt</th><th>Net Amt</th><th>Received</th></tr></thead>
        <tbody>
        @foreach ($f5['sales'] as $r)
            <tr>
                <td>{{ dbDate($r['tdate']) }}</td>
                <td>{{ $r['billno'] }}</td>
                <td>{{ trim(($r['custcode'] ?? '') . ' - ' . ($r['custname'] ?? ''), ' -') }}</td>
                <td class="amt">{{ dbFmt((float)($r['billamt'] ?? 0)) }}</td>
                <td class="amt">{{ dbFmt((float)($r['netamt'] ?? 0)) }}</td>
                <td class="amt">{{ dbFmt((float)($r['ramt'] ?? 0)) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3">Total</td>
                <td class="amt">{{ dbFmt(array_sum(array_map(fn($r) => (float)($r['billamt'] ?? 0), $f5['sales']))) }}</td>
                <td class="amt">{{ dbFmt((float)$tot['sales_amt']) }}</td>
                <td class="amt">{{ dbFmt(array_sum(array_map(fn($r) => (float)($r['ramt'] ?? 0), $f5['sales']))) }}</td>
            </tr>
        </tfoot>
    </table>
    @endif

    {{-- Sales Returns --}}
    @if (!empty($f5['sales_returns']))
    <div class="report-header"><strong>Sales Returns ({{ count($f5['sales_returns']) }})</strong></div>
    <table class="report-table" id="reportTable_sret" style="margin-bottom:14px">
        <thead><tr><th>Date</th><th>Return No</th><th>Customer</th><th>Bill Amt</th><th>Net Amt</th></tr></thead>
        <tbody>
        @foreach ($f5['sales_returns'] as $r)
            <tr>
                <td>{{ dbDate($r['tdate']) }}</td>
                <td>{{ $r['billno'] }}</td>
                <td>{{ trim(($r['custcode'] ?? '') . ' - ' . ($r['custname'] ?? ''), ' -') }}</td>
                <td class="amt">{{ dbFmt((float)($r['billamt'] ?? 0)) }}</td>
                <td class="amt">{{ dbFmt((float)($r['netamt'] ?? 0)) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr class="total-row"><td colspan="4">Total</td><td class="amt">{{ dbFmt((float)$tot['sret_amt']) }}</td></tr></tfoot>
    </table>
    @endif

    {{-- Purchases --}}
    @if (!empty($f5['purchases']))
    <div class="report-header"><strong>Purchases ({{ count($f5['purchases']) }})</strong></div>
    <table class="report-table" id="reportTable_purch" style="margin-bottom:14px">
        <thead><tr><th>Date</th><th>Doc No</th><th>Supplier Bill</th><th>Supplier</th><th>Bill Amt</th><th>Net Amt</th></tr></thead>
        <tbody>
        @foreach ($f5['purchases'] as $r)
            <tr>
                <td>{{ dbDate($r['tdate']) }}</td>
                <td>{{ $r['docno'] }}</td>
                <td>{{ $r['billno'] ?? '' }}</td>
                <td>{{ trim(($r['suppcode'] ?? '') . ' - ' . ($r['name'] ?? ''), ' -') }}</td>
                <td class="amt">{{ dbFmt((float)($r['billamt'] ?? 0)) }}</td>
                <td class="amt">{{ dbFmt((float)($r['netamt'] ?? 0)) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr class="total-row"><td colspan="5">Total</td><td class="amt">{{ dbFmt((float)$tot['purch_amt']) }}</td></tr></tfoot>
    </table>
    @endif

    {{-- Purchase Returns --}}
    @if (!empty($f5['purchase_returns']))
    <div class="report-header"><strong>Purchase Returns ({{ count($f5['purchase_returns']) }})</strong></div>
    <table class="report-table" id="reportTable_pret" style="margin-bottom:14px">
        <thead><tr><th>Date</th><th>Doc No</th><th>Supplier</th><th>Bill Amt</th><th>Net Amt</th></tr></thead>
        <tbody>
        @foreach ($f5['purchase_returns'] as $r)
            <tr>
                <td>{{ dbDate($r['tdate']) }}</td>
                <td>{{ $r['docno'] }}</td>
                <td>{{ trim(($r['suppcode'] ?? '') . ' - ' . ($r['name'] ?? ''), ' -') }}</td>
                <td class="amt">{{ dbFmt((float)($r['billamt'] ?? 0)) }}</td>
                <td class="amt">{{ dbFmt((float)($r['netamt'] ?? 0)) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr class="total-row"><td colspan="4">Total</td><td class="amt">{{ dbFmt((float)$tot['pret_amt']) }}</td></tr></tfoot>
    </table>
    @endif

    {{-- Cash Receipts --}}
    @if (!empty($f5['receipts']))
    <div class="report-header"><strong>Cash Receipts ({{ count($f5['receipts']) }})</strong></div>
    <table class="report-table" id="reportTable_rcpt" style="margin-bottom:14px">
        <thead><tr><th>Date</th><th>Voucher</th><th>A/C</th><th>Particulars</th><th>Amount</th></tr></thead>
        <tbody>
        @foreach ($f5['receipts'] as $r)
            <tr>
                <td>{{ dbDate($r['tdate']) }}</td>
                <td>{{ $r['vchno'] ?? '' }}</td>
                <td>{{ $r['acname'] }}</td>
                <td>{{ $r['particular'] ?? '' }}</td>
                <td class="amt">{{ dbFmt((float)$r['amount']) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr class="total-row"><td colspan="4">Total</td><td class="amt">{{ dbFmt((float)$tot['receipts_amt']) }}</td></tr></tfoot>
    </table>
    @endif

    {{-- Cash Payments --}}
    @if (!empty($f5['payments']))
    <div class="report-header"><strong>Cash Payments ({{ count($f5['payments']) }})</strong></div>
    <table class="report-table" id="reportTable_pymt" style="margin-bottom:14px">
        <thead><tr><th>Date</th><th>Voucher</th><th>A/C</th><th>Particulars</th><th>Amount</th></tr></thead>
        <tbody>
        @foreach ($f5['payments'] as $r)
            <tr>
                <td>{{ dbDate($r['tdate']) }}</td>
                <td>{{ $r['vchno'] ?? '' }}</td>
                <td>{{ $r['acname'] }}</td>
                <td>{{ $r['particular'] ?? '' }}</td>
                <td class="amt">{{ dbFmt((float)$r['amount']) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr class="total-row"><td colspan="4">Total</td><td class="amt">{{ dbFmt((float)$tot['payments_amt']) }}</td></tr></tfoot>
    </table>
    @endif

    @if (empty($f5['sales']) && empty($f5['sales_returns']) && empty($f5['purchases']) && empty($f5['purchase_returns']) && empty($f5['receipts']) && empty($f5['payments']))
    <div class="no-data">No transactions found for selected period.</div>
    @endif

@endif
</div>

<script>
function onFormChange() {
    const form = document.getElementById('formSelect').value;
    document.getElementById('cbBreakDay').style.display = (form === 'form1') ? 'flex' : 'none';
    document.getElementById('cbWithDiff').style.display = (form === 'form2') ? 'flex' : 'none';
}


</script>
<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>
ReportExport.initFromTable('btnSaveAs', '.report-table',
  'daybook_{{ $formType }}_{{ $dateFrom }}_{{ $dateTo }}');
</script>
</body>
</html>
