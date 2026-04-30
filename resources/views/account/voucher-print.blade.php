<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $voucherType }} Print - {{ $voucherData['vchno'] ?? '' }}</title>
    @php
        $printLayout = array_merge(\App\Support\PrintLayout::viewData(), [
            'topMargin' => 0,
            'leftMargin' => 0,
            'bottomMargin' => 0,
            'pageAlign' => 'LEFT',
            'letterheadMode' => 'APP_HEADER',
        ]);
    @endphp
    @include('partials.print-layout-head')
    @php
        $pageMode = trim((string) ($pageMode ?? 'landscape'));
    @endphp
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;color:#0f172a;margin:0;padding:18px}
        .wrap{width:{{ $pageMode === 'half-portrait' ? '148mm' : '210mm' }};max-width:{{ $pageMode === 'half-portrait' ? '148mm' : '210mm' }};margin:0 auto;background:#fff;border:1px solid #d7deea;box-shadow:0 12px 30px rgba(15,23,42,.08)}
        .toolbar{display:flex;justify-content:flex-end;gap:8px;padding:12px 14px;border-bottom:1px solid #e2e8f0;background:#f8fafc}
        .toolbar button{border:1px solid #94a3b8;background:#fff;border-radius:7px;padding:7px 12px;font-weight:600;cursor:pointer}
        .toolbar .tick{display:inline-flex;align-items:center;gap:6px;border:1px solid #94a3b8;background:#fff;border-radius:7px;padding:7px 12px;font-weight:600;color:#0f172a}
        .toolbar .tick input{width:15px;height:15px}
        .doc{padding:18px 22px}
        .title{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1e3a5f;padding-bottom:10px;margin-bottom:16px}
        .title h1{margin:0;font-size:26px;color:#1e3a5f}
        .meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 18px;margin-bottom:18px}
        .meta-row,.amount-row{display:flex;justify-content:space-between;gap:10px;border-bottom:1px dashed #d6dce8;padding:5px 0}
        .label{font-weight:700;color:#475569}
        .value{font-weight:600;text-align:right}
        .amount-card{border:1px solid #d7deea;border-radius:10px;background:#fbfdff;padding:14px 16px;margin-bottom:18px}
        .amount-main{font-size:30px;font-weight:800;color:#1d4ed8}
        .amount-sub{font-size:13px;color:#64748b;margin-top:4px}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .panel{border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;background:#fff}
        .panel h3{margin:0 0 8px;font-size:14px;color:#1e3a5f}
        .note{margin-top:18px;border-top:1px solid #e2e8f0;padding-top:12px;white-space:pre-wrap}
        .badge{display:inline-block;padding:3px 8px;border-radius:999px;background:#e0f2fe;color:#075985;font-size:12px;font-weight:700}
        @if($pageMode === 'half-portrait')
        body{padding:8px}
        .wrap{margin:0;background:#fff;border:0;box-shadow:none}
        .doc{padding:4mm 4mm 4mm 0}
        .title{padding-bottom:4px;margin-bottom:8px}
        .title h1{font-size:18px}
        .amount-card{padding:8px 10px;margin-bottom:8px}
        .amount-main{font-size:22px}
        .amount-sub{font-size:11px}
        .meta{gap:4px 10px;margin-bottom:8px}
        .meta-row,.amount-row{padding:3px 0;font-size:11px}
        .grid{gap:8px}
        .panel{padding:8px 10px}
        .panel h3{font-size:12px;margin-bottom:6px}
        .note{margin-top:8px;padding-top:6px;font-size:11px}
        @endif
        @media print{
            @page{size:{{ $pageMode === 'half-portrait' ? 'A5 portrait' : 'A5 landscape' }};margin:8mm}
            html,body{width:100%;height:auto;background:#fff;padding:0;margin:0}
            body{font-size:11px}
            .wrap{
                width:{{ $pageMode === 'half-portrait' ? '138mm' : 'auto' }};
                max-width:{{ $pageMode === 'half-portrait' ? '138mm' : 'none' }};
                margin:0;
                border:0;
                box-shadow:none;
                page-break-inside:avoid;
                break-inside:avoid;
            }
            .doc{padding:{{ $pageMode === 'half-portrait' ? '4mm 4mm 4mm 0' : '6mm 7mm' }}}
            .title{padding-bottom:4px;margin-bottom:8px}
            .title h1{font-size:{{ $pageMode === 'half-portrait' ? '18px' : '20px' }}}
            .badge{font-size:10px;padding:2px 6px}
            .amount-card{padding:8px 10px;margin-bottom:8px}
            .amount-main{font-size:{{ $pageMode === 'half-portrait' ? '22px' : '24px' }}}
            .amount-sub{font-size:11px}
            .meta{gap:4px 10px;margin-bottom:8px}
            .meta-row,.amount-row{padding:3px 0;font-size:11px}
            .grid{gap:8px}
            .panel{padding:8px 10px}
            .panel h3{font-size:12px;margin-bottom:6px}
            .note{margin-top:8px;padding-top:6px;font-size:11px}
            .toolbar{display:none}
        }
    </style>
</head>
<body>
@php
    $v = $voucherData ?? [];
    $company = $company ?? [];
    $showBalance = (bool) ($showBalance ?? false);
    $balanceInfo = $balanceInfo ?? [];
@endphp
<div class="wrap">
    <div class="toolbar">
        <label class="tick">
            <input type="checkbox" id="toggleShowBalance" {{ $showBalance ? 'checked' : '' }}>
            <span>Show Balance</span>
        </label>
        <button type="button" onclick="window.print()">Print</button>
        <button type="button" onclick="window.close()">Close</button>
    </div>
    <div class="doc">
        @if(trim((string) ($company['name'] ?? '')) !== '')
        <div style="text-align:center;border-bottom:1px solid #d7deea;padding-bottom:12px;margin-bottom:14px" data-print-app-header="voucher-company">
            <div style="font-size:24px;font-weight:800;color:#0f172a;letter-spacing:.3px">{{ $company['name'] }}</div>
        </div>
        @endif

        <div class="title">
            <div>
                <h1>{{ strtoupper($voucherType) }}</h1>
                <div class="badge">{{ $printSlip ? 'Slip View' : ($printRuff ? 'Ruff Slip View' : 'Voucher Print') }}</div>
            </div>
            <div style="text-align:right">
                <div class="label">Voucher No</div>
                <div class="value" style="font-size:20px">{{ $v['vchno'] ?? '' }}</div>
            </div>
        </div>

        <div class="amount-card">
            <div class="label">{{ $voucherType }} Amount</div>
            <div class="amount-main">{{ number_format((float) ($v['amount'] ?? 0), 2, '.', '') }}</div>
            <div class="amount-sub">{{ $v['tdate'] ?? '' }}</div>
            <div class="amount-sub" style="margin-top:6px;font-size:12px;color:#1e293b;font-weight:700;">
                Amount In Words: {{ $amountInWords ?? '' }}
            </div>
        </div>

        <div class="meta">
            <div class="meta-row"><span class="label">Date</span><span class="value">{{ $v['tdate'] ?? '' }}</span></div>
            <div class="meta-row"><span class="label">Staff</span><span class="value">{{ $v['staff'] ?? '' }}</span></div>
            <div class="meta-row"><span class="label">Cash / Bank</span><span class="value">{{ trim(($v['cbcode'] ?? '') . (empty($v['cbname']) ? '' : ' - ' . $v['cbname'])) }}</span></div>
            <div class="meta-row"><span class="label">Account</span><span class="value">{{ trim(($v['accode'] ?? '') . (empty($v['acname']) ? '' : ' - ' . $v['acname'])) }}</span></div>
        </div>

        <div class="grid">
            <div class="panel">
                <h3>Tax Details</h3>
                <div class="amount-row"><span class="label">Tax %</span><span class="value">{{ number_format((float) ($v['taxperc'] ?? 0), 2, '.', '') }}</span></div>
                <div class="amount-row"><span class="label">Tax Amount</span><span class="value">{{ number_format((float) ($v['taxamt'] ?? 0), 2, '.', '') }}</span></div>
                @if($voucherType === 'Receipt')
                <div class="amount-row"><span class="label">Discount</span><span class="value">{{ number_format((float) ($v['discount'] ?? 0), 2, '.', '') }}</span></div>
                @endif
            </div>
            <div class="panel" id="balancePanel" style="{{ $showBalance ? '' : 'display:none;' }}">
                <h3>Balance</h3>
                <div class="amount-row">
                    <span class="label">Party / A/c Code</span>
                    <span class="value">{{ trim(($balanceInfo['account_code'] ?? '') . (empty($balanceInfo['account_name']) ? '' : ' - ' . $balanceInfo['account_name'])) }}</span>
                </div>
                <div class="amount-row">
                    <span class="label">Opening Balance</span>
                    <span class="value">{{ number_format(abs((float) ($balanceInfo['opening_balance'] ?? 0)), 2, '.', '') }} {{ $balanceInfo['opening_label'] ?? '' }}</span>
                </div>
                <div class="amount-row">
                    <span class="label">Closing Balance</span>
                    <span class="value">{{ number_format(abs((float) ($balanceInfo['closing_balance'] ?? 0)), 2, '.', '') }} {{ $balanceInfo['closing_label'] ?? '' }}</span>
                </div>
            </div>
        </div>

        <div class="note">
            <div class="label" style="margin-bottom:6px">Particulars</div>
            <div>{{ $v['particular'] ?? '' }}</div>
        </div>
    </div>
</div>
<script>
(() => {
    const toggle = document.getElementById('toggleShowBalance');
    const panel = document.getElementById('balancePanel');
    const defaultShowBalance = @json($showBalance);
    if (!toggle || !panel) return;

    function syncBalanceVisibility() {
        panel.style.display = toggle.checked ? '' : 'none';
    }

    toggle.checked = !!defaultShowBalance;
    toggle.addEventListener('change', syncBalanceVisibility);
    syncBalanceVisibility();
})();
</script>
</body>
</html>
