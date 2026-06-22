<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Salary Slip</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .no-print { display: flex; gap: 8px; padding: 12px 20px; background: #fff; border-bottom: 1px solid #e5ecf5; }
        .no-print button { height: 34px; border: none; border-radius: 6px; padding: 0 16px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #173b63; color: #fff; }
        .btn-ghost { background: #fff; color: #173b63; border: 1px solid #173b63; }
        .slip { max-width: 750px; margin: 20px auto; background: #fff; border: 1px solid #d7dfeb; padding: 24px 28px; border-radius: 10px; }
        .slip-header { display: flex; justify-content: space-between; align-items: start; border-bottom: 2px solid #173b63; padding-bottom: 14px; margin-bottom: 14px; }
        .slip-header .shop-info h2 { margin: 0 0 4px; font-size: 18px; color: #173b63; }
        .slip-header .shop-info p { margin: 0; font-size: 12px; color: #64748b; }
        .slip-title { font-size: 15px; font-weight: 700; color: #173b63; text-align: right; }
        .slip-title span { display: block; font-size: 13px; color: #64748b; font-weight: 400; margin-top: 4px; }
        .staff-box { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 20px; background: #f8fbff; border: 1px solid #d8e2ef; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 13px; }
        .staff-box .item { display: flex; gap: 6px; }
        .staff-box .lbl { color: #64748b; min-width: 100px; font-size: 12px; }
        .staff-box .val { font-weight: 600; }
        .slip-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .slip-table th, .slip-table td { padding: 7px 12px; border: 1px solid #e5ecf5; font-size: 13px; }
        .slip-table th { background: #edf4fc; font-weight: 700; color: #64748b; }
        .slip-table td:last-child { text-align: right; }
        .net-box { background: #173b63; color: #fff; border-radius: 8px; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; }
        .net-box .label { font-size: 14px; }
        .net-box .amount { font-size: 22px; font-weight: 700; }
        .sign-area { display: flex; justify-content: space-between; margin-top: 28px; padding-top: 14px; border-top: 1px solid #e5ecf5; font-size: 12px; color: #64748b; }
        .empty { padding: 40px; text-align: center; color: #94a3b8; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .slip { border: 0; border-radius: 0; margin: 0; padding: 20px; max-width: 100%; }
        }
    </style>
</head>
<body>
<div class="no-print">
    <button class="btn-primary" onclick="window.print()">Print</button>
    <button class="btn-ghost" onclick="window.close()">Close</button>
</div>

<div id="slip-area"><div class="empty">Loading salary slip&hellip;</div></div>

<script>
function n2(v) { return parseFloat(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function esc(v) { const d=document.createElement('div');d.textContent=v==null?'':v;return d.innerHTML; }

const urlP = new URLSearchParams(window.location.search);
const staffCode = urlP.get('staff_code') || '';
const month     = urlP.get('month') || '';

if (staffCode && month) {
    fetch(`/api/payroll/slip-data?staff_code=${encodeURIComponent(staffCode)}&month=${encodeURIComponent(month)}`)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { document.getElementById('slip-area').innerHTML = `<div class="empty">${esc(d.message)}</div>`; return; }
            renderSlip(d);
        });
} else {
    document.getElementById('slip-area').innerHTML = '<div class="empty">Missing staff code or month.</div>';
}

function renderSlip(d) {
    const { slip, staff, shop_name, shop_addr, month_label } = d;
    const sname = staff.sname || staff.name || staffCode;

    document.getElementById('slip-area').innerHTML = `
    <div class="slip">
        <div class="slip-header">
            <div class="shop-info">
                <h2>${esc(shop_name || 'My Company')}</h2>
                <p>${esc(shop_addr || '')}</p>
            </div>
            <div>
                <div class="slip-title">SALARY SLIP<span>${esc(month_label)}</span></div>
            </div>
        </div>

        <div class="staff-box">
            <div class="item"><span class="lbl">Staff Code</span><span class="val">${esc(slip.staff_code)}</span></div>
            <div class="item"><span class="lbl">Name</span><span class="val">${esc(sname)}</span></div>
            <div class="item"><span class="lbl">Department</span><span class="val">${esc(staff.dept || '—')}</span></div>
            <div class="item"><span class="lbl">Working Days</span><span class="val">${slip.working_days}</span></div>
            <div class="item"><span class="lbl">Days Present</span><span class="val">${slip.days_present}</span></div>
            <div class="item"><span class="lbl">Payment Mode</span><span class="val" style="text-transform:capitalize;">${esc(slip.payment_mode || 'cash')}</span></div>
        </div>

        <table class="slip-table">
            <thead>
                <tr><th style="width:50%">Earnings</th><th style="text-align:right;">Amount (₹)</th><th style="width:50%">Deductions</th><th style="text-align:right;">Amount (₹)</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Basic Salary</td><td>₹ ${n2(slip.basic_earned)}</td>
                    <td>Provident Fund</td><td>₹ ${n2(slip.pf_deduction)}</td>
                </tr>
                <tr>
                    <td>HRA</td><td>₹ ${n2(slip.hra_earned)}</td>
                    <td>ESI</td><td>₹ ${n2(slip.esi_deduction)}</td>
                </tr>
                <tr>
                    <td>DA</td><td>₹ ${n2(slip.da_earned)}</td>
                    <td>TDS</td><td>₹ ${n2(slip.tds_deduction)}</td>
                </tr>
                <tr>
                    <td>Other Allowances</td><td>₹ ${n2(slip.other_allowances)}</td>
                    <td>Advance Recovery</td><td>₹ ${n2(slip.advance_deduction)}</td>
                </tr>
                <tr>
                    <td></td><td></td>
                    <td>Other Deductions</td><td>₹ ${n2(slip.other_deductions)}</td>
                </tr>
                <tr style="font-weight:700;background:#f8f9fa;">
                    <td>GROSS SALARY</td><td>₹ ${n2(slip.gross_salary)}</td>
                    <td>TOTAL DEDUCTIONS</td>
                    <td>₹ ${n2((+slip.pf_deduction||0)+(+slip.esi_deduction||0)+(+slip.tds_deduction||0)+(+slip.advance_deduction||0)+(+slip.other_deductions||0))}</td>
                </tr>
            </tbody>
        </table>

        <div class="net-box">
            <div class="label">NET SALARY PAYABLE</div>
            <div class="amount">₹ ${n2(slip.net_salary)}</div>
        </div>

        <div class="sign-area">
            <div>Employee Signature: ____________________</div>
            <div>Prepared By: ____________________</div>
            <div>Authorized By: ____________________</div>
        </div>
    </div>`;
}
</script>
</body>
</html>
