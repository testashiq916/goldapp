<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>A/c Ledger</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 100%; margin: 0; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 16px; box-sizing: border-box; }
        h1 { margin: 0 0 12px; font-size: 22px; color: #173b63; }
        /* force light background so the dark theme gradient injected by the dashboard does not hide labels */
        form.toolbar, .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; background: #f0f5ff !important; border-radius: 8px; padding: 10px 8px; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 150px; }
        .field.wide { min-width: 300px; flex: 1; }
        label { font-size: 13px; font-weight: 700; color: #1e3a5f; }
        input, select { height: 38px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 10px; font-size: 14px; box-sizing: border-box; background: #fff; color: #1f2937; }
        button { height: 38px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 12px; font-size: 14px; box-sizing: border-box; cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        button.ghost { background: #fff; }
        .headbox { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 12px; }
        .card { border: 1px solid #d8e2ef; border-radius: 8px; background: #f8fbff; padding: 12px; font-size: 14px; }
        .muted { color: #64748b; font-size: 13px; }
        .strong { font-weight: 700; color: #163b63; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 8px 10px; vertical-align: top; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; z-index: 1; font-size: 13px; font-weight: 700; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 70vh; }
        tr.clickable-row { cursor: pointer; }
        tr.clickable-row:hover td { background: #f8fbff; }
        td.voucher-link { color: #17456e; font-weight: 700; cursor: pointer; }
        td.voucher-link:hover { text-decoration: underline; }
        .summary { display: flex; gap: 10px; flex-wrap: wrap; margin: 10px 0; }
        .pill { border: 1px solid #cad7e6; background: #f8fbff; border-radius: 999px; padding: 6px 14px; font-size: 13px; font-weight: 600; }
        .empty { padding: 24px; text-align: center; color: #64748b; font-size: 14px; border: 1px dashed #c7d4e4; border-radius: 8px; background: #fbfdff; }
        .link-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 8px 0 12px; }
        .status-box { margin: 0 0 12px; padding: 10px 14px; border: 1px solid #b7dbbf; background: #effaf1; color: #1f5e2e; border-radius: 8px; font-size: 13px; font-weight: 600; }
        @media (max-width: 980px) {
            .headbox { grid-template-columns: 1fr; }
            .field, .field.wide { min-width: 100%; }
        }
        /* Account picker modal */
        .ac-picker-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index:9999; align-items:center; justify-content:center; }
        .ac-picker-overlay.open { display:flex; }
        .ac-picker { background:#fff; width:92vw; max-width:780px; max-height:82vh; border-radius:10px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,0.35); }
        .ac-picker-head { padding:12px 16px; background:linear-gradient(135deg,#173b63,#2a6398); color:#fff; display:flex; justify-content:space-between; align-items:center; font-weight:700; font-size:15px; }
        .ac-picker-head button { background:rgba(255,255,255,0.18); border:none; color:#fff; width:28px; height:28px; border-radius:6px; font-size:18px; cursor:pointer; }
        .ac-picker-head button:hover { background:rgba(255,255,255,0.32); }
        .ac-picker-search { padding:10px 14px; border-bottom:1px solid #e5ecf5; background:#f8fbff; display:flex; gap:8px; }
        .ac-picker-search input { flex:1; height:38px; border:1px solid #c4d1e2; border-radius:6px; padding:0 12px; font-size:14px; }
        .ac-picker-tabs { display:flex; gap:4px; padding:0 14px 10px; background:#f8fbff; border-bottom:1px solid #e5ecf5; flex-wrap:wrap; }
        .ac-picker-tab { font-size:12px; font-weight:700; padding:5px 12px; border:1px solid #c4d1e2; border-radius:999px; cursor:pointer; background:#fff; color:#375b84; }
        .ac-picker-tab.active { background:#2a6398; color:#fff; border-color:#2a6398; }
        .ac-picker-body { flex:1; overflow:auto; }
        .ac-picker-body table { width:100%; border-collapse:collapse; font-size:13px; }
        .ac-picker-body th { position:sticky; top:0; background:#edf4fc; padding:8px 12px; text-align:left; border-bottom:1px solid #d8e2ef; z-index:1; font-size:13px; }
        .ac-picker-body td { padding:7px 12px; border-bottom:1px solid #f1f5f9; font-size:13px; }
        .ac-picker-body tr { cursor:pointer; }
        .ac-picker-body tr:hover td { background:#eff6ff; }
        .ac-picker-body tr.selected td { background:#dbeafe; }
        .ac-picker-body td.mono { font-family:Consolas,monospace; }
        .ac-picker-body td.muted { color:#64748b; font-size:12px; }
        .ac-picker-foot { padding:8px 16px; border-top:1px solid #e5ecf5; font-size:12px; color:#64748b; background:#f8fbff; display:flex; justify-content:space-between; }
        @media print {
            .toolbar, .link-row { display: none; }
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
<style>
    body.account-ledger-page {
        background: #eff6ff !important;
        color: #0f172a !important;
    }

    body.account-ledger-page .wrap {
        background: #ffffff !important;
        border-color: #bfdbfe !important;
    }

    body.account-ledger-page form.toolbar,
    body.account-ledger-page .toolbar {
        background: linear-gradient(135deg, #17456e, #2563a0) !important;
        border: 1px solid #0f4c81 !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18) !important;
    }

    body.account-ledger-page .toolbar label,
    body.account-ledger-page .toolbar .muted,
    body.account-ledger-page .toolbar span {
        color: #ffffff !important;
    }

    body.account-ledger-page .toolbar input,
    body.account-ledger-page .toolbar select {
        background: #ffffff !important;
        color: #0f172a !important;
        border-color: #93c5fd !important;
    }

    body.account-ledger-page .toolbar input::placeholder {
        color: #64748b !important;
    }

    body.account-ledger-page .toolbar button {
        background: #ffffff !important;
        color: #17456e !important;
        border-color: #93c5fd !important;
    }

    body.account-ledger-page .toolbar button.primary {
        background: #e0f2fe !important;
        color: #0c4a6e !important;
        border-color: #7dd3fc !important;
    }

    body.account-ledger-page .toolbar button:hover {
        background: #dbeafe !important;
    }

    body.account-ledger-page .toolbar input[type="checkbox"] {
        accent-color: #2563eb;
    }

    body.account-ledger-page h1 {
        color: #173b63 !important;
    }
</style>
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body class="account-ledger-page">
<div class="wrap">
    <h1>A/c Ledger</h1>

    @if(session('status'))
        <div class="status-box">{{ session('status') }}</div>
    @endif

    <form method="get" action="{{ url('/accounts/ac-ledger') }}" class="toolbar" id="acLedgerSearchForm">
        <div class="field wide">
            <label>A/c Code</label>
            <div style="display:flex;gap:4px;">
                <input id="acCodeInput" name="accode" value="{{ $accountQuery ?? $code }}" placeholder="Type account code, account name, or phone number" autocomplete="off" autocorrect="off" spellcheck="false" style="flex:1;">
                <button type="button" onclick="openAcPicker()" title="Search accounts by code, name, or phone" style="width:36px;padding:0;">&#128269;</button>
            </div>
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

    @if($account)
        <div class="headbox">
            <div class="card">
                @php
                    $acCode   = $account['accode'];
                    $acName   = $account['name'];
                    // accountm stores name as "ClientName(code)" — strip trailing (code) to avoid double display
                    $cleanName = preg_replace('/\s*\(' . preg_quote($acCode, '/') . '\)\s*$/', '', $acName);
                @endphp
                <div class="strong">{{ $cleanName }} ({{ $acCode }})</div>
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
                        <th style="width: 90px;">Update</th>
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
                            <td style="white-space:pre-line;">{{ $row['othacname'] }}</td>
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
                            <td>
                                @if(!empty($row['can_clear']))
                                    <form method="post" action="{{ url('/accounts/ac-ledger/clear-issue') }}" onsubmit="return confirm('Clear this orphan ledger posting? This deletes daybook/daybookpart for this missing source bill.');">
                                        @csrf
                                        <input type="hidden" name="slno" value="{{ $row['slno'] }}">
                                        <input type="hidden" name="module" value="{{ $row['clear_module'] }}">
                                        <input type="hidden" name="accode" value="{{ $accountQuery ?? $code }}">
                                        <input type="hidden" name="date1" value="{{ $dateFrom }}">
                                        <input type="hidden" name="date2" value="{{ $dateTo }}">
                                        <input type="hidden" name="type" value="{{ $type }}">
                                        <input type="hidden" name="search" value="{{ $search }}">
                                        <input type="hidden" name="amount" value="{{ $amountSearch ?? '' }}">
                                        <input type="hidden" name="susp_only" value="{{ $suspOnly ? 1 : 0 }}">
                                        <button type="submit" style="height:28px;padding:0 10px;">Clear</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="{{ $type === 'ordinary' ? 4 : ($type === 'withrate' ? 5 : 6) }}">Total</th>
                        <th class="num">{{ number_format($totals['debit'], 2) }}</th>
                        <th class="num">{{ number_format($totals['credit'], 2) }}</th>
                        <th class="num">{{ number_format(abs($closingBalance), 2) }} {{ $closingBalance < 0 ? 'Dr' : 'Cr' }}</th>
                        <th></th>
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

<!-- Account picker modal -->
<div class="ac-picker-overlay" id="acPickerOverlay" onclick="if(event.target===this) closeAcPicker()">
    <div class="ac-picker">
        <div class="ac-picker-head">
            <span>Select Account &mdash; Customers, Suppliers, Staff &amp; All Ledgers</span>
            <button type="button" onclick="closeAcPicker()" title="Close">&times;</button>
        </div>
        <div class="ac-picker-search">
            <input id="acPickerSearch" placeholder="Search by account code, account name, or phone number... (Esc to close, Enter to select)" oninput="filterAcPicker()" onkeydown="acPickerKey(event)">
        </div>
        <div class="ac-picker-tabs" id="acPickerTabs"></div>
        <div class="ac-picker-body">
            <table>
                <thead>
                    <tr>
                        <th style="width:120px;">Code</th>
                        <th>Name</th>
                        <th style="width:140px;">Mobile</th>
                        <th style="width:140px;">Type</th>
                    </tr>
                </thead>
                <tbody id="acPickerBody"></tbody>
            </table>
        </div>
        <div class="ac-picker-foot">
            <span><span id="acPickerCount">0</span><span id="acPickerLimitNote"></span></span>
            <span>Click row or press Enter to select</span>
        </div>
    </div>
</div>

<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>
// Only Show/Enter should load the ledger; typing in A/c Code must not open/search any popup.
(function(){
    const form = document.getElementById('acLedgerSearchForm');
    if (!form) return;
    let _ok = false;
    const showBtn = form.querySelector('button[name="show"]');
    if (showBtn) showBtn.addEventListener('click', function(){ _ok = true; });
    const acInput = document.getElementById('acCodeInput');
    if (acInput) {
        acInput.addEventListener('keydown', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                _ok = true;
                form.requestSubmit ? form.requestSubmit(showBtn) : form.submit();
            } else if (e.key === 'Escape') {
                closeAcPicker();
            }
        });
    }
    form.addEventListener('submit', function(e){
        if (!_ok){ e.preventDefault(); return; }
        _ok = false;
    });
})();

let acFiltered = [];
let acSelIdx = 0;
let acTypeFilter = '';
let acSearchTimer = null;
let acSearchAbort = null;

function acTypeLabel(t){
    const u = String(t || '').toUpperCase();
    if (!u) return 'Other';
    if (u.includes('CUST') || u === 'C') return 'Customer';
    if (u.includes('SUP') || u === 'S') return 'Supplier';
    if (u.includes('STAF') || u.includes('EMP')) return 'Staff';
    if (u.includes('AGENT')) return 'Agent';
    if (u.includes('GOLD') || u.includes('SMITH')) return 'Goldsmith';
    if (u.includes('BANK')) return 'Bank';
    if (u.includes('CASH')) return 'Cash';
    return t;
}

function buildAcTabs(){
    const order = ['', 'Customer', 'Supplier', 'Staff', 'Goldsmith', 'Agent', 'Bank', 'Cash', 'Other'];
    const html = order
        .map(k => `<span class="ac-picker-tab${acTypeFilter === k ? ' active' : ''}" data-type="${k}">${k === '' ? 'All' : k}</span>`)
        .join('');
    document.getElementById('acPickerTabs').innerHTML = html;
    document.querySelectorAll('#acPickerTabs .ac-picker-tab').forEach(el => {
        el.onclick = () => { acTypeFilter = el.dataset.type; buildAcTabs(); searchAcPicker(); };
    });
}

function escHtml(s){ const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

function renderAcPicker(rows){
    const tb = document.getElementById('acPickerBody');
    if (!rows.length){
        tb.innerHTML = '<tr><td colspan="4" style="padding:24px;text-align:center;color:#94a3b8;">No matching accounts</td></tr>';
        document.getElementById('acPickerCount').textContent = 0;
        document.getElementById('acPickerLimitNote').textContent = '';
        return;
    }
    tb.innerHTML = rows.map((r, i) => `
        <tr data-idx="${i}" data-code="${escHtml(r.accode)}" class="${i === acSelIdx ? 'selected' : ''}">
            <td class="mono">${escHtml(r.accode)}</td>
            <td>${escHtml(r.name)}</td>
            <td class="muted mono">${escHtml(r.mobile || '')}</td>
            <td class="muted">${escHtml(acTypeLabel(r.actype2))}</td>
        </tr>
    `).join('');
    tb.querySelectorAll('tr').forEach(tr => {
        tr.onclick = () => pickAccount(tr.dataset.code);
    });
    document.getElementById('acPickerCount').textContent = rows.length;
}

function filterAcPicker(){
    clearTimeout(acSearchTimer);
    acSearchTimer = setTimeout(searchAcPicker, 180);
}

function searchAcPicker(){
    const input = document.getElementById('acPickerSearch');
    const q = (input.value || '').trim();
    const tb = document.getElementById('acPickerBody');
    if (acSearchAbort) acSearchAbort.abort();
    acSearchAbort = new AbortController();
    tb.innerHTML = '<tr><td colspan="4" style="padding:24px;text-align:center;color:#64748b;">Searching...</td></tr>';

    const params = new URLSearchParams({ q, type: acTypeFilter, limit: '80' });
    fetch('{{ url('/api/accounts/ac-ledger/search-accounts') }}?' + params.toString(), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: acSearchAbort.signal
    })
        .then(res => res.ok ? res.json() : { ok:false, accounts:[] })
        .then(data => {
            acFiltered = data.accounts || [];
            acSelIdx = 0;
            renderAcPicker(acFiltered);
            document.getElementById('acPickerLimitNote').textContent = data.limited ? ' shown - type more to narrow' : ' account(s)';
        })
        .catch(err => {
            if (err && err.name === 'AbortError') return;
            acFiltered = [];
            tb.innerHTML = '<tr><td colspan="4" style="padding:24px;text-align:center;color:#b91c1c;">Unable to search accounts</td></tr>';
            document.getElementById('acPickerCount').textContent = 0;
            document.getElementById('acPickerLimitNote').textContent = '';
        });
}

function openAcPicker(){
    const o = document.getElementById('acPickerOverlay');
    o.classList.add('open');
    document.getElementById('acPickerSearch').value = document.getElementById('acCodeInput').value || '';
    acTypeFilter = '';
    acSelIdx = 0;
    buildAcTabs();
    searchAcPicker();
    setTimeout(() => {
        const search = document.getElementById('acPickerSearch');
        search.focus();
        search.select();
    }, 30);
}

function closeAcPicker(){
    document.getElementById('acPickerOverlay').classList.remove('open');
}

function pickAccount(code){
    const inp = document.getElementById('acCodeInput');
    if (inp){ inp.value = code; }
    closeAcPicker();
}

function acPickerKey(e){
    if (e.key === 'Escape'){ closeAcPicker(); return; }
    if (e.key === 'ArrowDown'){
        e.preventDefault();
        acSelIdx = Math.min(acSelIdx + 1, Math.max(acFiltered.length - 1, 0));
        renderAcPicker(acFiltered);
        const sel = document.querySelector('#acPickerBody tr.selected');
        if (sel) sel.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp'){
        e.preventDefault();
        acSelIdx = Math.max(acSelIdx - 1, 0);
        renderAcPicker(acFiltered);
        const sel = document.querySelector('#acPickerBody tr.selected');
        if (sel) sel.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter'){
        e.preventDefault();
        if (acFiltered[acSelIdx]) pickAccount(acFiltered[acSelIdx].accode);
    }
}

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
                forceNewTab: true,
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
