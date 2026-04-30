<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 13px; background: #c0c0c0; padding: 8px; }

        .toolbar {
            background: #c0c0c0; padding: 6px 8px; display: flex; align-items: center;
            gap: 8px; flex-wrap: wrap; border: 2px outset #fff; margin-bottom: 4px;
        }
        .toolbar label { font-weight: 700; font-size: 12px; white-space: nowrap; }
        .toolbar input[type="date"] {
            font-weight: 700; text-align: center; padding: 2px 4px;
            border: 2px inset #fff; width: 130px; color: #000080;
        }
        .toolbar input[type="text"] {
            padding: 2px 4px; border: 2px inset #fff; width: 80px; text-transform: uppercase;
        }
        .toolbar select { padding: 2px 4px; border: 2px inset #fff; font-size: 12px; }
        .toolbar button {
            padding: 3px 12px; font-weight: 700; border: 2px outset #fff;
            background: #c0c0c0; cursor: pointer; font-size: 12px;
        }
        .toolbar button:active { border-style: inset; }

        .content { display: flex; gap: 6px; height: calc(100vh - 70px); }

        .chart-panel {
            flex: 1 1 55%; background: #fff; border: 2px inset #fff;
            padding: 8px; overflow: auto; min-width: 0;
        }
        .table-panel {
            flex: 1 1 40%; background: #fff; border: 2px inset #fff;
            overflow: auto; min-width: 0;
        }

        .report-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .report-table th {
            background: #f0f0f0; font-weight: 700; padding: 3px 6px;
            border: 1px solid #999; text-align: center;
            position: sticky; top: 0; z-index: 1;
        }
        .report-table td { padding: 2px 6px; border-bottom: 1px solid #eee; }
        .report-table .amt { text-align: right; font-family: 'Courier New', monospace; }
        .report-table .total-row td { font-weight: 700; border-top: 2px solid #000; background: #f5f5f5; }

        .no-data { padding: 40px; text-align: center; color: #888; }
        .loading { padding: 20px; text-align: center; color: #555; }
        .err { padding: 10px; color: red; font-weight: 700; }

        @media print {
            .toolbar { display: none; }
            body { background: #fff; padding: 0; }
            .content { display: block; height: auto; }
            .chart-panel, .table-panel { border: none; }
        }
    </style>
@include('partials.print-layout-head')
</head>
<body>

<div class="toolbar">
    <label>Item Code:</label>
    <input type="text" id="itemCode" maxlength="10" placeholder="(All)">

    <label>From:</label>
    <input type="date" id="date1" value="{{ $date1 }}">

    <label>To:</label>
    <input type="date" id="date2" value="{{ $date2 }}">

    <label>Item Type:</label>
    <select id="itemType">
        <option value="Gold">Gold</option>
        <option value="Silver">Silver</option>
        <option value="Others">Others</option>
    </select>

    <label>Graph Type:</label>
    <select id="graphType">
        <option value="itemwise">Item Wise</option>
        <option value="monthly">Monthly Wise</option>
    </select>

    <label>Value:</label>
    <select id="valueType">
        <option value="weight">Weight Wise</option>
        <option value="qty">Qty Wise</option>
        <option value="amt">Amt Wise</option>
    </select>

    <button onclick="loadData()">Show</button>
    <button onclick="window.print()">Print</button>
</div>

<div class="content">
    <div class="chart-panel">
        <div id="chartMsg" class="no-data">Select filters and click <strong>Show</strong>.</div>
        <canvas id="myChart" style="display:none"></canvas>
    </div>
    <div class="table-panel">
        <div id="tableArea"><div class="no-data">No data.</div></div>
    </div>
</div>

<script>
const RLEVEL = {{ $rlevel }};
let chartInst = null;

const MONTH_NAMES = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

const CHART_COLORS = [
    '#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f',
    '#edc948','#b07aa1','#ff9da7','#9c755f','#bab0ac',
    '#d37295','#fabfd2','#8cd17d','#b6992d','#499894',
];

function fmtNum(n, d) {
    if (n == null || n == 0) return '';
    return Number(n).toLocaleString('en-IN', {minimumFractionDigits: d, maximumFractionDigits: d});
}

function loadData() {
    const date1     = document.getElementById('date1').value;
    const date2     = document.getElementById('date2').value;
    const itemCode  = document.getElementById('itemCode').value.trim().toUpperCase();
    const itemType  = document.getElementById('itemType').value;
    const graphType = document.getElementById('graphType').value;
    const valueType = document.getElementById('valueType').value;

    document.getElementById('chartMsg').textContent = 'Loading...';
    document.getElementById('chartMsg').className = 'loading';
    document.getElementById('myChart').style.display = 'none';
    document.getElementById('tableArea').innerHTML = '<div class="loading">Loading...</div>';
    if (chartInst) { chartInst.destroy(); chartInst = null; }

    const params = new URLSearchParams({
        date1, date2, item_code: itemCode, item_type: itemType,
        graph_type: graphType, value_type: valueType, rlevel: RLEVEL,
    });

    fetch('{{ url("/api/item-movement/data") }}?' + params)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { showErr(d.message || 'Error'); return; }
            if (!d.rows || d.rows.length === 0) {
                document.getElementById('chartMsg').textContent = 'No data found for selected filters.';
                document.getElementById('chartMsg').className = 'no-data';
                document.getElementById('myChart').style.display = 'none';
                document.getElementById('tableArea').innerHTML = '<div class="no-data">No records found.</div>';
                return;
            }
            renderChart(d.rows, graphType, valueType);
            renderTable(d.rows, graphType, valueType);
        })
        .catch(e => showErr('Request failed: ' + e.message));
}

function showErr(msg) {
    document.getElementById('chartMsg').textContent = msg;
    document.getElementById('chartMsg').className = 'err';
    document.getElementById('myChart').style.display = 'none';
}

function getValueKey(valueType) {
    return valueType === 'qty' ? 'tot_qty' : (valueType === 'amt' ? 'tot_amt' : 'tot_wgt');
}
function getValueLabel(valueType) {
    return valueType === 'qty' ? 'Qty' : (valueType === 'amt' ? 'Amount' : 'Weight');
}
function getValueDecimals(valueType) {
    return valueType === 'qty' ? 0 : (valueType === 'amt' ? 2 : 3);
}

function renderChart(rows, graphType, valueType) {
    const canvas = document.getElementById('myChart');
    const msg    = document.getElementById('chartMsg');
    const key    = getValueKey(valueType);
    const label  = getValueLabel(valueType);

    msg.style.display = 'none';
    canvas.style.display = 'block';

    let labels, datasets;

    if (graphType === 'monthly') {
        // X-axis = months, series = items
        const monthSet = [...new Set(rows.map(r => r.yr + '-' + String(r.mnth).padStart(2,'0')))].sort();
        const itemSet  = [...new Set(rows.map(r => r.icode))];
        labels   = monthSet.map(m => { const [y,mn] = m.split('-'); return MONTH_NAMES[+mn] + ' ' + y; });
        datasets = itemSet.map((icode, idx) => {
            const iname = (rows.find(r => r.icode === icode) || {}).iname || icode;
            const data  = monthSet.map(m => {
                const [y, mn] = m.split('-');
                const r = rows.find(r => r.icode === icode && r.yr == +y && r.mnth == +mn);
                return r ? (+(r[key] || 0)) : 0;
            });
            return { label: iname, data, backgroundColor: CHART_COLORS[idx % CHART_COLORS.length] };
        });
    } else {
        // Item Wise: one bar per item
        labels   = rows.map(r => r.iname || r.icode);
        const data = rows.map(r => +(r[key] || 0));
        datasets = [{ label, data, backgroundColor: rows.map((_, i) => CHART_COLORS[i % CHART_COLORS.length]) }];
    }

    chartInst = new Chart(canvas, {
        type: 'bar',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: true, text: 'Item Movement — ' + label, font: { size: 14, weight: 'bold' } },
                legend: { position: graphType === 'monthly' ? 'bottom' : 'none' },
            },
            scales: {
                x: { ticks: { font: { size: 10 }, maxRotation: 60 } },
                y: { beginAtZero: true, ticks: { font: { size: 10 } } },
            },
        },
    });
    canvas.parentElement.style.height = Math.max(300, labels.length * 24 + 120) + 'px';
}

function renderTable(rows, graphType, valueType) {
    const key     = getValueKey(valueType);
    const label   = getValueLabel(valueType);
    const dec     = getValueDecimals(valueType);
    let html      = '';

    if (graphType === 'monthly') {
        html = `<table class="report-table">
            <thead><tr><th>Month</th><th>Item</th><th style="text-align:right">${label}</th></tr></thead>
            <tbody>`;
        let curMonth = '', tot = 0, grand = 0;
        for (const r of rows) {
            const mKey = MONTH_NAMES[r.mnth] + ' ' + r.yr;
            if (mKey !== curMonth) {
                if (curMonth) {
                    html += `<tr class="total-row"><td colspan="2">Total ${curMonth}</td><td class="amt">${fmtNum(tot, dec) || '0'}</td></tr>`;
                }
                curMonth = mKey; tot = 0;
            }
            const v = +(r[key] || 0);
            tot += v; grand += v;
            html += `<tr><td>${mKey}</td><td>${r.iname || r.icode}</td><td class="amt">${fmtNum(v, dec)}</td></tr>`;
        }
        if (curMonth) {
            html += `<tr class="total-row"><td colspan="2">Total ${curMonth}</td><td class="amt">${fmtNum(tot, dec) || '0'}</td></tr>`;
        }
        html += `</tbody><tfoot><tr class="total-row"><td colspan="2">Grand Total</td><td class="amt">${fmtNum(grand, dec) || '0'}</td></tr></tfoot></table>`;
    } else {
        html = `<table class="report-table">
            <thead><tr><th style="text-align:left">Item</th><th style="text-align:right">Qty</th><th style="text-align:right">Weight</th><th style="text-align:right">Amount</th></tr></thead>
            <tbody>`;
        let tQty = 0, tWgt = 0, tAmt = 0;
        for (const r of rows) {
            tQty += +(r.tot_qty || 0); tWgt += +(r.tot_wgt || 0); tAmt += +(r.tot_amt || 0);
            html += `<tr>
                <td>${r.iname || r.icode}</td>
                <td class="amt">${fmtNum(r.tot_qty, 0)}</td>
                <td class="amt">${fmtNum(r.tot_wgt, 3)}</td>
                <td class="amt">${fmtNum(r.tot_amt, 2)}</td>
            </tr>`;
        }
        html += `</tbody><tfoot><tr class="total-row">
            <td>Total (${rows.length} items)</td>
            <td class="amt">${fmtNum(tQty, 0) || '0'}</td>
            <td class="amt">${fmtNum(tWgt, 3) || '0'}</td>
            <td class="amt">${fmtNum(tAmt, 2) || '0'}</td>
        </tr></tfoot></table>`;
    }

    document.getElementById('tableArea').innerHTML = html;
}
</script>
</body>
</html>
