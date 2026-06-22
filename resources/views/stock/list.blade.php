<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1360px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 6px; font-size: 24px; color: #173b63; }
        .sub { color: #64748b; font-size: 12px; margin-bottom: 10px; }
        .toolbar { display: grid; grid-template-columns: repeat(6, minmax(150px, 1fr)); gap: 8px; align-items: end; margin-bottom: 10px; }
        .field { display: flex; flex-direction: column; gap: 4px; }
        .field.span2 { grid-column: span 2; }
        label { font-size: 11px; font-weight: 700; color: #375b84; }
        input, select, button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        .checks { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; padding-top: 2px; }
        .checks label { display: flex; gap: 6px; align-items: center; font-weight: 600; color: #1f3f66; margin: 0; }
        .checks input { width: 16px; height: 16px; }
        button { cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .meta { font-size: 12px; color: #48627c; margin-bottom: 8px; }
        .summary { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
        .pill { border: 1px solid #cad7e6; background: #f8fbff; border-radius: 999px; padding: 6px 12px; font-size: 12px; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 70vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 8px; vertical-align: top; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; z-index: 1; white-space: nowrap; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tr.clickable:hover td { background: #f8fbff; }
        tr.zero td { color: #94a3b8; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #c7d4e4; border-radius: 8px; background: #fbfdff; }
        @media (max-width: 1100px) { .toolbar { grid-template-columns: repeat(2, minmax(160px, 1fr)); } .field.span2 { grid-column: span 2; } }
        @media print { .toolbar, .summary { display: none; } body { background: #fff; } .wrap { max-width: none; margin: 0; border: 0; } .table-wrap { max-height: none; overflow: visible; border: 0; } th { position: static; } }
    </style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
    @include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="wrap">
    <h1>{{ $title }}</h1>
    <div class="sub">Module key: {{ $moduleId }}</div>

    <form id="filterForm" class="toolbar">
        <div class="field">
            <label>Group</label>
            <select id="grpcode">
                <option value="">All Groups</option>
                @foreach($groupCodes as $code)
                    <option value="{{ $code }}">{{ $code }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Metal Type</label>
            <select id="metalType">
                <option>All</option>
                <option>Gold</option>
                <option>Platinum</option>
                <option>Silver</option>
                <option>Others</option>
            </select>
        </div>
        <div class="field">
            <label>Ornament</label>
            <select id="ornamentFilter">
                <option>All</option>
                <option>Ornaments Only</option>
                <option>Not Ornaments</option>
            </select>
        </div>
        <div class="field">
            <label>Stock Filter</label>
            <select id="stockFilter">
                <option>All</option>
                <option>With Stock Only</option>
                <option>With -ve Stock</option>
                <option>With Zero Stock</option>
                <option selected>With Not Zero Stock</option>
            </select>
        </div>
        <div class="field">
            <label>Stock Type</label>
            <select id="stktype">
                <option value="">All</option>
                @foreach($stockTypes as $code)
                    <option value="{{ $code }}">{{ $code }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Value Mode</label>
            <select id="valueMode">
                <option value="rate">Rate</option>
                <option value="cost">Cost</option>
            </select>
        </div>
        <div class="field span2">
            <label>Options</label>
            <div class="checks">
                <label><input id="filterGroup" type="checkbox" checked> Group Filter</label>
                <label><input id="sortOnCode" type="checkbox"> Sort On Code</label>
                <label><input id="noWeight" type="checkbox"> No Wgt</label>
                <label><input id="noTotal" type="checkbox"> No Total</label>
            </div>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="submit" class="primary">Show</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="button" onclick="window.print()">Print</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="button" id="exitBtn">Exit</button>
        </div>
    </form>

    <div class="meta" id="metaInfo">Click <strong>Show</strong> to view the stock list.</div>
    <div class="summary" id="summaryBar" style="display:none"></div>

    <div class="table-wrap" id="tableWrap" style="display:none">
        <table>
            <thead>
            <tr>
                <th>Code</th>
                <th>Item Name</th>
                <th>Group</th>
                <th>Type</th>
                <th class="num">Op Qty</th>
                <th class="num wcol">Op Wgt</th>
                <th class="num">Qty</th>
                <th class="num wcol">Weight</th>
                <th class="num wcol">St.Wgt</th>
                <th class="num wcol">Net Wgt</th>
                <th class="num" id="rateHead">Rate</th>
                <th class="num">Value</th>
            </tr>
            </thead>
            <tbody id="reportBody"></tbody>
            <tfoot id="tableFoot">
            <tr>
                <td colspan="4">Total</td>
                <td class="num" id="totOpQty">0</td>
                <td class="num wcol" id="totOpWgt">0.000</td>
                <td class="num" id="totQty">0</td>
                <td class="num wcol" id="totWgt">0.000</td>
                <td class="num wcol" id="totStWgt">0.000</td>
                <td class="num wcol" id="totNetWgt">0.000</td>
                <td class="num">-</td>
                <td class="num" id="totValue">0.00</td>
            </tr>
            </tfoot>
        </table>
    </div>

    <div class="empty" id="emptyState">Click <strong>Show</strong> to view the stock list.</div>
</div>

<script>
(() => {
    const API = @json(url('/api/stock/list/data'));
    const ITEM_HISTORY = @json(url('/stock/item-history'));
    const REPORT_BODY = document.getElementById('reportBody');
    const META = document.getElementById('metaInfo');
    const WRAP = document.getElementById('tableWrap');
    const EMPTY = document.getElementById('emptyState');
    const SUMMARY = document.getElementById('summaryBar');
    const FOOT = document.getElementById('tableFoot');

    function esc(v) {
        return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fmt2(v) { return Number(v || 0).toFixed(2); }
    function fmt3(v) { return Number(v || 0).toFixed(3); }
    function fmt0(v) { return Number(v || 0).toFixed(0); }

    function applyWeightVisibility() {
        const hide = document.getElementById('noWeight').checked;
        document.querySelectorAll('.wcol').forEach((el) => {
            el.style.display = hide ? 'none' : '';
        });
    }

    function applyTotalsVisibility() {
        FOOT.style.display = document.getElementById('noTotal').checked ? 'none' : '';
    }

    function summaryPill(label, value) {
        return `<div class="pill">${esc(label)}: ${value}</div>`;
    }

    async function loadReport() {
        META.textContent = 'Loading...';
        WRAP.style.display = 'none';
        EMPTY.style.display = '';
        SUMMARY.style.display = 'none';

        const params = new URLSearchParams({
            grpcode: document.getElementById('grpcode').value,
            filter_group: document.getElementById('filterGroup').checked ? '1' : '0',
            metal_type: document.getElementById('metalType').value,
            ornament_filter: document.getElementById('ornamentFilter').value,
            stock_filter: document.getElementById('stockFilter').value,
            sort_on_code: document.getElementById('sortOnCode').checked ? '1' : '0',
            no_weight: document.getElementById('noWeight').checked ? '1' : '0',
            value_mode: document.getElementById('valueMode').value,
            stktype: document.getElementById('stktype').value,
        });

        try {
            const res = await fetch(`${API}?${params.toString()}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!data.ok) throw new Error(data.message || 'Unable to load stock list');

            const rows = data.rows || [];
            const totals = data.totals || {};
            const noWeight = document.getElementById('noWeight').checked;
            document.getElementById('rateHead').textContent = document.getElementById('valueMode').value === 'cost' ? 'Cost' : 'Rate';

            REPORT_BODY.innerHTML = rows.map((row) => {
                const qClass = (!noWeight && Number(row.weight || 0) === 0) || (noWeight && Number(row.qty || 0) === 0) ? 'zero' : '';
                const qs = new URLSearchParams({
                    code: row.code || '',
                    date_from: new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10),
                    date_to: new Date().toISOString().slice(0, 10),
                    stktype: document.getElementById('stktype').value,
                }).toString();
                return `<tr class="clickable ${qClass}" ondblclick="window.location='${ITEM_HISTORY}?${qs}'">
                    <td>${esc(row.code)}</td>
                    <td>${esc(row.name)}</td>
                    <td>${esc(row.grpcode)}</td>
                    <td>${esc(row.itype)}</td>
                    <td class="num">${fmt0(row.opqty)}</td>
                    <td class="num wcol">${fmt3(row.opweight)}</td>
                    <td class="num">${fmt0(row.qty)}</td>
                    <td class="num wcol">${fmt3(row.weight)}</td>
                    <td class="num wcol">${fmt3(row.stonewgt)}</td>
                    <td class="num wcol">${fmt3(row.netwgt)}</td>
                    <td class="num">${fmt2(row.rate)}</td>
                    <td class="num">${fmt2(row.value)}</td>
                </tr>`;
            }).join('');

            document.getElementById('totOpQty').textContent = fmt0(totals.opqty);
            document.getElementById('totOpWgt').textContent = fmt3(totals.opweight);
            document.getElementById('totQty').textContent = fmt0(totals.qty);
            document.getElementById('totWgt').textContent = fmt3(totals.weight);
            document.getElementById('totStWgt').textContent = fmt3(totals.stonewgt);
            document.getElementById('totNetWgt').textContent = fmt3(totals.netwgt);
            document.getElementById('totValue').textContent = fmt2(totals.value);

            SUMMARY.innerHTML = [
                summaryPill('Rows', rows.length),
                summaryPill('Gold Qty', fmt0(totals.gold_qty)),
                summaryPill('Gold Wgt', fmt3(totals.gold_weight)),
                summaryPill('Silver Qty', fmt0(totals.silver_qty)),
                summaryPill('Silver Wgt', fmt3(totals.silver_weight)),
                summaryPill('Other Qty', fmt0(totals.other_qty)),
                summaryPill('Other Wgt', fmt3(totals.other_weight)),
                summaryPill('Value', fmt2(totals.value)),
            ].join('');

            applyWeightVisibility();
            applyTotalsVisibility();

            META.textContent = rows.length ? `${rows.length} item(s) loaded.` : 'No items found.';
            WRAP.style.display = rows.length ? '' : 'none';
            EMPTY.style.display = rows.length ? 'none' : '';
            SUMMARY.style.display = rows.length ? '' : 'none';
            if (!rows.length) {
                EMPTY.textContent = 'No items found for the selected filters.';
            }
        } catch (error) {
            META.textContent = error.message;
            EMPTY.textContent = error.message;
            EMPTY.style.display = '';
        }
    }

    document.getElementById('filterForm').addEventListener('submit', (event) => {
        event.preventDefault();
        loadReport();
    });
    document.getElementById('noWeight').addEventListener('change', () => {
        applyWeightVisibility();
        loadReport();
    });
    document.getElementById('noTotal').addEventListener('change', applyTotalsVisibility);
    document.getElementById('valueMode').addEventListener('change', loadReport);
    document.getElementById('sortOnCode').addEventListener('change', loadReport);
    document.getElementById('filterGroup').addEventListener('change', loadReport);
    document.getElementById('exitBtn').addEventListener('click', () => window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*'));

    loadReport();
})();
</script>
</body>
</html>
