<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Gold Loan Report</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 1300px; margin: 14px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 18px; }
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
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .badge-A { background: #dcfce7; color: #15803d; }
        .badge-C { background: #f1f5f9; color: #64748b; }
        .badge-O { background: #fee2e2; color: #dc2626; }
        tfoot th { background: #f1f5f9; }
        .empty { padding: 30px; text-align: center; color: #94a3b8; }
        @media print { .toolbar { display: none; } }
    </style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="wrap">
    <h1>Gold Loan Report</h1>
    <form class="toolbar" onsubmit="loadReport(event)">
        <div class="field"><label>From</label><input type="date" id="d1" value="{{ $dateFrom }}"></div>
        <div class="field"><label>To</label><input type="date" id="d2" value="{{ $dateTo }}"></div>
        <div class="field">
            <label>Status</label>
            <select id="status">
                <option value="all">All</option>
                <option value="A">Active</option>
                <option value="C">Closed</option>
            </select>
        </div>
        <div class="field"><label>&nbsp;</label><button type="submit" class="btn-primary">Show</button></div>
        <div class="field"><label>&nbsp;</label><button type="button" class="btn-ghost" onclick="window.print()">Print</button></div>
    </form>

    <div id="summary-area"></div>
    <div class="table-wrap">
        <table id="report-table">
            <thead>
                <tr>
                    <th>Loan No</th><th>Date</th><th>Customer</th><th>Mobile</th>
                    <th class="num">Loan Amount</th><th class="num">Rate%</th><th class="num">Paid</th>
                    <th class="num">Balance</th><th>Due Date</th><th>Status</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="10" class="empty">Select filters and click Show.</td></tr></tbody>
            <tfoot id="report-tfoot"></tfoot>
        </table>
    </div>
</div>
<script>
function n2(v) { return parseFloat(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function esc(v) { const d=document.createElement('div');d.textContent=v==null?'':v;return d.innerHTML; }

function loadReport(e) {
    if (e) e.preventDefault();
    const d1 = document.getElementById('d1').value;
    const d2 = document.getElementById('d2').value;
    const st = document.getElementById('status').value;
    const tbody = document.querySelector('#report-table tbody');
    tbody.innerHTML = '<tr><td colspan="10" class="empty">Loading&hellip;</td></tr>';
    document.getElementById('summary-area').innerHTML = '';

    fetch(`/api/gold-loan/report?date1=${d1}&date2=${d2}&status=${st}`)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { tbody.innerHTML = `<tr><td colspan="10" class="empty">${esc(d.message)}</td></tr>`; return; }
            const rows = d.rows || [];
            const totals = d.totals || {};

            document.getElementById('summary-area').innerHTML = `<div class="summary-box">
                <div class="s-card"><div class="lbl">Total Loans</div><div class="val">${totals.count || rows.length}</div></div>
                <div class="s-card"><div class="lbl">Total Amount</div><div class="val">₹ ${n2(totals.total_loan_amt)}</div></div>
                <div class="s-card"><div class="lbl">Total Collected</div><div class="val">₹ ${n2(totals.total_paid)}</div></div>
                <div class="s-card"><div class="lbl">Outstanding</div><div class="val" style="color:#dc2626;">₹ ${n2(totals.total_balance)}</div></div>
            </div>`;

            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="10" class="empty">No loans found</td></tr>'; return; }
            tbody.innerHTML = rows.map(r => {
                const today = new Date().toISOString().split('T')[0];
                let statusClass = 'badge-' + r.status;
                let statusLabel = r.status === 'A' ? 'Active' : 'Closed';
                if (r.status === 'A' && r.due_date && r.due_date < today) {
                    statusClass = 'badge-O'; statusLabel = 'Overdue';
                }
                return `<tr>
                    <td><a href="/gold-loan?loan_no=${esc(r.loan_no)}" style="color:#173b63;">${esc(r.loan_no)}</a></td>
                    <td>${r.loan_date || ''}</td>
                    <td>${esc(r.pname)}</td>
                    <td>${esc(r.mobile)}</td>
                    <td class="num">${n2(r.loan_amt)}</td>
                    <td class="num">${r.interest_rate}%</td>
                    <td class="num">${n2(r.paidamt)}</td>
                    <td class="num" style="${parseFloat(r.balance||0)>0?'color:#dc2626;font-weight:700;':''}">${n2(r.balance)}</td>
                    <td>${r.due_date || ''}</td>
                    <td><span class="badge ${statusClass}">${statusLabel}</span></td>
                </tr>`;
            }).join('');

            document.getElementById('report-tfoot').innerHTML = `<tr>
                <th colspan="4">TOTAL (${rows.length} loans)</th>
                <th class="num">${n2(rows.reduce((s,r)=>s+(+r.loan_amt||0),0))}</th>
                <th></th>
                <th class="num">${n2(rows.reduce((s,r)=>s+(+r.paidamt||0),0))}</th>
                <th class="num">${n2(rows.reduce((s,r)=>s+(+r.balance||0),0))}</th>
                <th colspan="2"></th>
            </tr>`;
        });
}
</script>
</body>
</html>
