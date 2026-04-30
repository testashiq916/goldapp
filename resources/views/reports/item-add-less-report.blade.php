<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1600px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 22px; color: #173b63; }
        .sub { margin: -2px 0 12px; color: #5b7088; font-size: 12px; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; min-width: 140px; }
        .field.wide { min-width: 220px; flex: 1; }
        label { font-size: 11px; font-weight: 700; color: #375b84; }
        input, select, button { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        button { cursor: pointer; background: #e8f2ff; border-color: #2a6398; color: #17456e; font-weight: 700; }
        button.primary { background: #e6f8ec; border-color: #2a7a42; color: #1b5b31; }
        .meta { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 10px; font-size: 12px; color: #48627c; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 72vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 8px; vertical-align: top; }
        th { position: sticky; top: 0; background: #edf4fc; text-align: left; z-index: 1; white-space: nowrap; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tfoot td { background: #f7fbff; font-weight: 700; color: #264968; position: sticky; bottom: 0; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #c7d4e4; border-radius: 8px; background: #fbfdff; }
        .status { margin: 10px 0 0; font-size: 12px; color: #64748b; min-height: 18px; }
        @media (max-width: 980px) {
            .field, .field.wide { min-width: 100%; }
        }
        @media print {
            .toolbar, .status { display: none; }
            body { background: #fff; }
            .wrap { max-width: none; margin: 0; border: 0; }
            .table-wrap { max-height: none; overflow: visible; border: 0; }
            th, tfoot td { position: static; }
        }
    </style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wrap">
    <h1>{{ $title }}</h1>
    <div class="sub">Module key: {{ $moduleId }}</div>

    <form class="toolbar" id="filterForm">
        <div class="field">
            <label>Date From</label>
            <input type="date" id="date1" value="{{ $date1 }}">
        </div>
        <div class="field">
            <label>Date To</label>
            <input type="date" id="date2" value="{{ $date2 }}">
        </div>
        <div class="field">
            <label>Item Code</label>
            <input type="text" id="itemCode" placeholder="Item code">
        </div>
        <div class="field">
            <label>Reason</label>
            <select id="reason">
                @foreach($reasons as $reason)
                    <option value="{{ $reason }}">{{ $reason }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Stock Type</label>
            <select id="stktype">
                <option value="">All</option>
                @foreach($stockTypes as $stockType)
                    <option value="{{ trim((string) $stockType->code) }}">{{ trim((string) $stockType->code) }} - {{ trim((string) $stockType->name) }}</option>
                @endforeach
            </select>
        </div>
        <div class="field wide">
            <label>SMan</label>
            <input type="text" id="smcode" list="smanList" placeholder="Salesman code">
            <datalist id="smanList">
                @foreach($salesmen as $salesman)
                    <option value="{{ trim((string) $salesman->code) }}">{{ trim((string) $salesman->name) }}</option>
                @endforeach
            </datalist>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="submit" class="primary">Show</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="button" id="printBtn">Print</button>
        </div>
    </form>

    <div class="meta">
        <div>From: <strong id="metaDate1">{{ $date1 }}</strong></div>
        <div>To: <strong id="metaDate2">{{ $date2 }}</strong></div>
        <div>Rows: <strong id="metaCount">0</strong></div>
    </div>

    <div class="table-wrap" id="tableWrap" style="display:none;">
        <table>
            <thead>
            <tr>
                <th style="width: 95px;">Date</th>
                <th style="width: 95px;">Time</th>
                <th>Item</th>
                <th class="num" style="width: 90px;">Add Qty</th>
                <th class="num" style="width: 100px;">Add Wgt</th>
                <th class="num" style="width: 100px;">Add St.</th>
                <th style="width: 85px;">StkT</th>
                <th class="num" style="width: 90px;">Less Qty</th>
                <th class="num" style="width: 100px;">Less Wgt</th>
                <th class="num" style="width: 100px;">Less St.</th>
                <th style="width: 85px;">StkT</th>
                <th style="width: 70px;">SM</th>
                <th style="width: 220px;">Note</th>
            </tr>
            </thead>
            <tbody id="reportBody"></tbody>
            <tfoot>
            <tr>
                <td colspan="3">Total</td>
                <td class="num" id="totAddQty">0</td>
                <td class="num" id="totAddWgt">0.000</td>
                <td class="num" id="totAddSt">0.000</td>
                <td></td>
                <td class="num" id="totLessQty">0</td>
                <td class="num" id="totLessWgt">0.000</td>
                <td class="num" id="totLessSt">0.000</td>
                <td colspan="3" id="totCount">0 row(s)</td>
            </tr>
            </tfoot>
        </table>
    </div>

    <div class="empty" id="emptyState">Choose filters and click <strong>Show</strong> to load the report.</div>
    <div class="status" id="statusText"></div>
</div>

<script>
const REPORT_API = @json(url('/api/item-add-less-report/data'));
const RLEVEL = @json((int) $rlevel);

function $(id) { return document.getElementById(id); }
function esc(v) {
    return String(v ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}
function fmtDate(v) {
    if (!v) return '';
    const parts = String(v).split('-');
    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : String(v);
}
function fmtTime(v) {
    if (!v) return '';
    const bits = String(v).split(':');
    if (bits.length < 2) return String(v);
    let hh = Number(bits[0] || 0);
    const mm = String(bits[1] || '00').padStart(2, '0');
    const ap = hh >= 12 ? 'PM' : 'AM';
    hh = hh % 12 || 12;
    return `${hh}:${mm} ${ap}`;
}
function fmtNum(v, places = 3) { return Number(v || 0).toFixed(places); }
function setStatus(text = '') { $('statusText').textContent = text; }

function render(rows, totals) {
    $('reportBody').innerHTML = rows.map(row => `
        <tr>
            <td>${esc(fmtDate(row.tdate))}</td>
            <td>${esc(fmtTime(row.ttime))}</td>
            <td>${esc(row.itemname || '')}</td>
            <td class="num">${esc(String(row.addqty || 0))}</td>
            <td class="num">${esc(fmtNum(row.addwgt, 3))}</td>
            <td class="num">${esc(fmtNum(row.addstwgt, 3))}</td>
            <td>${esc(row.addstktype || '')}</td>
            <td class="num">${esc(String(row.lessqty || 0))}</td>
            <td class="num">${esc(fmtNum(row.lesswgt, 3))}</td>
            <td class="num">${esc(fmtNum(row.lessstwgt, 3))}</td>
            <td>${esc(row.lessstktype || '')}</td>
            <td>${esc(row.smcode || '')}</td>
            <td>${esc(row.particular || '')}</td>
        </tr>
    `).join('');

    $('totAddQty').textContent = String(totals.addqty || 0);
    $('totAddWgt').textContent = fmtNum(totals.addwgt, 3);
    $('totAddSt').textContent = fmtNum(totals.addstwgt, 3);
    $('totLessQty').textContent = String(totals.lessqty || 0);
    $('totLessWgt').textContent = fmtNum(totals.lesswgt, 3);
    $('totLessSt').textContent = fmtNum(totals.lessstwgt, 3);
    $('totCount').textContent = `${totals.count || 0} row(s)`;
    $('metaCount').textContent = String(totals.count || 0);
    $('metaDate1').textContent = $('date1').value || '';
    $('metaDate2').textContent = $('date2').value || '';

    const hasRows = rows.length > 0;
    $('tableWrap').style.display = hasRows ? '' : 'none';
    $('emptyState').style.display = hasRows ? 'none' : '';
    if (!hasRows) {
        $('emptyState').innerHTML = 'No add/less rows found for the selected filter.';
    }
}

async function loadReport() {
    const params = new URLSearchParams({
        date1: $('date1').value,
        date2: $('date2').value,
        item_code: $('itemCode').value.trim(),
        reason: $('reason').value,
        smcode: $('smcode').value.trim(),
        stktype: $('stktype').value,
        rlevel: String(RLEVEL)
    });

    setStatus('Loading...');

    try {
        const res = await fetch(`${REPORT_API}?${params.toString()}`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.message || 'Failed to load report');
        }
        render(data.rows || [], data.totals || {});
        setStatus(`${(data.rows || []).length} row(s) loaded.`);
    } catch (error) {
        $('tableWrap').style.display = 'none';
        $('emptyState').style.display = '';
        $('emptyState').textContent = error.message || 'Unable to load report';
        setStatus(error.message || 'Unable to load report');
    }
}

$('filterForm').addEventListener('submit', function (event) {
    event.preventDefault();
    loadReport();
});

$('printBtn').addEventListener('click', function () {
    window.print();
});
</script>
</body>
</html>
