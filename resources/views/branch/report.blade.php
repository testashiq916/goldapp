<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Branch Transfer Report</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 1400px; margin: 14px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 18px; }
        h1 { margin: 0 0 14px; font-size: 22px; color: #173b63; }
        form.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; background: #f0f5ff !important; border-radius: 8px; padding: 10px; margin-bottom: 14px; }
        .field { display: flex; flex-direction: column; gap: 3px; }
        label { font-size: 12px; font-weight: 700; color: #1e3a5f; }
        input, select { height: 36px; border: 1px solid #c4d1e2; border-radius: 6px; padding: 0 10px; font-size: 13px; box-sizing: border-box; }
        button { height: 36px; border: none; border-radius: 6px; padding: 0 16px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #173b63; color: #fff; }
        .btn-ghost { background: #fff; color: #173b63; border: 1px solid #173b63; }
        .summary-box { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
        .s-card { border: 1px solid #d8e2ef; border-radius: 8px; padding: 12px 14px; background: #f8fbff; }
        .s-card .lbl { font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; }
        .s-card .val { font-size: 20px; font-weight: 700; color: #173b63; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 10px; }
        th { background: #edf4fc; font-size: 12px; font-weight: 700; color: #64748b; position: sticky; top: 0; }
        td.num, th.num { text-align: right; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 65vh; }
        tfoot th { background: #f1f5f9; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-received { background: #dcfce7; color: #15803d; }
        .badge-cancelled { background: #fee2e2; color: #dc2626; }
        .empty { padding: 30px; text-align: center; color: #94a3b8; }
        @media print { .toolbar { display: none; } }
    </style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="wrap">
    <h1>Branch Transfer Report</h1>
    <form class="toolbar" onsubmit="loadReport(event)">
        <div class="field"><label>From</label><input type="date" id="d1" value="{{ $dateFrom }}"></div>
        <div class="field"><label>To</label><input type="date" id="d2" value="{{ $dateTo }}"></div>
        <div class="field"><label>Status</label>
            <select id="status">
                <option value="all">All</option>
                <option value="pending">Pending</option>
                <option value="received">Received</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div class="field"><label>&nbsp;</label><button type="submit" class="btn-primary">Show</button></div>
        <div class="field"><label>&nbsp;</label><button type="button" class="btn-ghost" onclick="window.print()">Print</button></div>
    </form>

    <div id="summary-area"></div>

    <div class="table-wrap">
        <table id="report-table">
            <thead><tr>
                <th>Transfer No</th><th>Date</th><th>From</th><th>To</th>
                <th>Item Code</th><th>Barcode</th><th>Description</th>
                <th class="num">Weight (g)</th><th class="num">Purity</th>
                <th class="num">Fine Wt</th><th class="num">Value (₹)</th>
                <th>Status</th><th>Received Date</th>
            </tr></thead>
            <tbody><tr><td colspan="13" class="empty">Select filters and click Show.</td></tr></tbody>
            <tfoot id="report-tfoot"></tfoot>
        </table>
    </div>
</div>

<script>
function n2(v) { return parseFloat(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function n3(v) { return parseFloat(v||0).toFixed(3); }
function esc(v) { const d=document.createElement('div');d.textContent=v==null?'':v;return d.innerHTML; }

function loadReport(e) {
    if (e) e.preventDefault();
    const d1 = document.getElementById('d1').value;
    const d2 = document.getElementById('d2').value;
    const st = document.getElementById('status').value;
    const tbody = document.querySelector('#report-table tbody');
    tbody.innerHTML = '<tr><td colspan="13" class="empty">Loading&hellip;</td></tr>';
    document.getElementById('summary-area').innerHTML = '';

    fetch(`/api/branch/transfer/report?date1=${d1}&date2=${d2}&status=${st}`)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { tbody.innerHTML = `<tr><td colspan="13" class="empty">${esc(d.message)}</td></tr>`; return; }
            const rows = d.rows || [];
            const totals = d.totals || {};

            document.getElementById('summary-area').innerHTML = `<div class="summary-box">
                <div class="s-card"><div class="lbl">Total Transfers</div><div class="val">${totals.count || rows.length}</div></div>
                <div class="s-card"><div class="lbl">Total Weight</div><div class="val">${n3(totals.total_weight)} g</div></div>
                <div class="s-card"><div class="lbl">Total Value</div><div class="val">₹ ${n2(totals.total_value)}</div></div>
            </div>`;

            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="13" class="empty">No transfers found.</td></tr>'; return; }

            tbody.innerHTML = rows.map(r => `<tr>
                <td>${esc(r.transfer_no)}</td>
                <td>${r.transfer_date}</td>
                <td>${esc(r.from_branch)}</td>
                <td>${esc(r.to_branch)}</td>
                <td>${esc(r.item_code)}</td>
                <td>${esc(r.barcode)}</td>
                <td>${esc(r.item_desc)}</td>
                <td class="num">${n3(r.weight)}</td>
                <td class="num">${r.purity||''}</td>
                <td class="num">${n3(r.fine_weight)}</td>
                <td class="num">${n2(r.value)}</td>
                <td><span class="badge badge-${r.status}">${r.status}</span></td>
                <td>${r.received_date || ''}</td>
            </tr>`).join('');

            document.getElementById('report-tfoot').innerHTML = `<tr>
                <th colspan="7">TOTAL (${rows.length} items)</th>
                <th class="num">${n3(rows.reduce((s,r)=>s+(+r.weight||0),0))}</th>
                <th></th>
                <th class="num">${n3(rows.reduce((s,r)=>s+(+r.fine_weight||0),0))}</th>
                <th class="num">${n2(rows.reduce((s,r)=>s+(+r.value||0),0))}</th>
                <th colspan="2"></th>
            </tr>`;
        });
}
</script>
</body>
</html>
