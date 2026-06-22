<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Staff Payroll</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 1300px; margin: 14px auto; }
        .card { background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 18px; margin-bottom: 14px; }
        h1 { margin: 0 0 4px; font-size: 22px; color: #173b63; }
        h2 { margin: 0 0 14px; font-size: 16px; color: #173b63; border-bottom: 2px solid #e5ecf5; padding-bottom: 8px; }
        form.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; background: #f0f5ff !important; border-radius: 8px; padding: 10px; margin-bottom: 14px; }
        .field { display: flex; flex-direction: column; gap: 3px; }
        label { font-size: 12px; font-weight: 700; color: #1e3a5f; }
        input, select { height: 36px; border: 1px solid #c4d1e2; border-radius: 6px; padding: 0 10px; font-size: 13px; box-sizing: border-box; }
        button { height: 36px; border: none; border-radius: 6px; padding: 0 16px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #173b63; color: #fff; }
        .btn-success { background: #15803d; color: #fff; }
        .btn-ghost { background: #fff; color: #173b63; border: 1px solid #173b63; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 10px; }
        th { background: #edf4fc; font-size: 12px; font-weight: 700; color: #64748b; position: sticky; top: 0; }
        td.num, th.num { text-align: right; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 400px; }
        tfoot th { background: #f1f5f9; }
        .tabs { display: flex; border-bottom: 2px solid #e5ecf5; }
        .tab { padding: 8px 18px; cursor: pointer; font-size: 13px; font-weight: 700; color: #64748b; border: 1px solid transparent; border-bottom: none; margin-bottom: -2px; border-radius: 6px 6px 0 0; }
        .tab.active { background: #fff; border-color: #d7dfeb; color: #173b63; border-bottom: 2px solid #fff; }
        .panel { display: none; padding-top: 16px; } .panel.active { display: block; }
        .calc-box { background: #f8fbff; border: 1px solid #d8e2ef; border-radius: 10px; padding: 16px; }
        .calc-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e5ecf5; font-size: 13px; }
        .calc-row:last-child { border: none; }
        .calc-total { font-size: 15px; font-weight: 700; color: #173b63; padding-top: 10px; }
        .deduction { color: #dc2626; }
        .empty { padding: 30px; text-align: center; color: #94a3b8; }
        .summary-box { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 14px; }
        .s-card { border: 1px solid #d8e2ef; border-radius: 8px; padding: 10px 14px; background: #f8fbff; }
        .s-card .lbl { font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; }
        .s-card .val { font-size: 18px; font-weight: 700; color: #173b63; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card" style="padding-bottom:10px;">
        <h1>Staff Payroll</h1>
        <div class="tabs">
            <div class="tab active" onclick="switchTab('process')">Process Salary</div>
            <div class="tab" onclick="switchTab('register')">Monthly Register</div>
            <div class="tab" onclick="switchTab('structure')">Salary Structure</div>
        </div>
    </div>

    <!-- Process Salary Panel -->
    <div class="panel active card" id="panel-process">
        <div class="grid2">
            <div>
                <h2>Calculate Salary</h2>
                <div class="field" style="margin-bottom:10px;">
                    <label>Select Staff *</label>
                    <select id="staff_code" onchange="loadStructure()">
                        <option value="">-- Select Staff --</option>
                    </select>
                </div>
                <div class="grid2" style="margin-bottom:10px;">
                    <div class="field"><label>Month *</label><input type="month" id="proc_month" value="{{ $month }}"></div>
                    <div class="field"><label>Working Days</label><input type="number" id="working_days" value="26" min="1" max="31"></div>
                </div>
                <div class="field" style="margin-bottom:10px;">
                    <label>Days Present</label>
                    <input type="number" id="days_present" value="26" min="0" max="31">
                </div>
                <div class="field" style="margin-bottom:10px;">
                    <label>Payment Mode</label>
                    <select id="pay_mode"><option value="cash">Cash</option><option value="bank">Bank Transfer</option><option value="upi">UPI</option></select>
                </div>
                <div style="display:flex;gap:8px;margin-top:14px;">
                    <button class="btn-primary" onclick="calcSalary()">Calculate</button>
                    <button class="btn-success" id="btn-save-salary" style="display:none;" onclick="saveSalary()">Save &amp; Finalize</button>
                    <a id="btn-slip" href="#" style="display:none;" target="_blank"><button type="button" class="btn-ghost">Salary Slip</button></a>
                </div>
            </div>
            <div id="calc-result-area">
                <h2>Calculation Preview</h2>
                <div class="empty">Select staff and click Calculate.</div>
            </div>
        </div>
    </div>

    <!-- Monthly Register Panel -->
    <div class="panel card" id="panel-register">
        <form class="toolbar" onsubmit="loadRegister(event)">
            <div class="field"><label>Month</label><input type="month" id="reg_month" value="{{ $month }}"></div>
            <div class="field"><label>&nbsp;</label><button type="submit" class="btn-primary">Show</button></div>
            <div class="field"><label>&nbsp;</label><button type="button" class="btn-ghost" onclick="window.print()">Print</button></div>
        </form>
        <div id="reg-summary"></div>
        <div class="table-wrap">
            <table id="reg-table">
                <thead><tr>
                    <th>Staff Code</th><th>Staff Name</th><th class="num">Days</th><th class="num">Present</th>
                    <th class="num">Gross</th><th class="num">PF</th><th class="num">ESI</th>
                    <th class="num">TDS</th><th class="num">Advance</th><th class="num">Net Salary</th>
                    <th>Status</th><th>Slip</th>
                </tr></thead>
                <tbody><tr><td colspan="12" class="empty">Select month and click Show.</td></tr></tbody>
                <tfoot id="reg-tfoot"></tfoot>
            </table>
        </div>
    </div>

    <!-- Salary Structure Panel -->
    <div class="panel card" id="panel-structure">
        <div class="grid2">
            <div>
                <h2>Set Salary Structure</h2>
                <div class="field" style="margin-bottom:10px;"><label>Staff *</label>
                    <select id="str_staff" onchange="loadStaffStructure()"><option value="">-- Select Staff --</option></select>
                </div>
                <div class="grid2" style="margin-bottom:10px;">
                    <div class="field"><label>Basic Salary (₹)</label><input type="number" id="str_basic" min="0" step="0.01" oninput="previewStructure()"></div>
                    <div class="field"><label>HRA (₹)</label><input type="number" id="str_hra" min="0" step="0.01" value="0" oninput="previewStructure()"></div>
                </div>
                <div class="grid2" style="margin-bottom:10px;">
                    <div class="field"><label>DA (₹)</label><input type="number" id="str_da" min="0" step="0.01" value="0" oninput="previewStructure()"></div>
                    <div class="field"><label>Other Allowances (₹)</label><input type="number" id="str_other" min="0" step="0.01" value="0" oninput="previewStructure()"></div>
                </div>
                <div class="grid3" style="margin-bottom:10px;">
                    <div class="field"><label>PF %</label><input type="number" id="str_pf" value="12" min="0" max="100" step="0.01"></div>
                    <div class="field"><label>ESI %</label><input type="number" id="str_esi" value="0.75" min="0" max="100" step="0.01"></div>
                    <div class="field"><label>TDS/Month (₹)</label><input type="number" id="str_tds" value="0" min="0" step="0.01"></div>
                </div>
                <div class="field" style="margin-bottom:14px;"><label>Effective From</label><input type="date" id="str_eff" value="{{ date('Y-m-01') }}"></div>
                <button class="btn-primary" onclick="saveStructure()">Save Structure</button>
            </div>
            <div id="str-preview">
                <h2>CTC Preview</h2>
                <div class="empty">Fill salary components to preview.</div>
            </div>
        </div>
    </div>
</div>

<script>
let staffData = [];
let lastCalc = null;

function n2(v) { return parseFloat(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function esc(v) { const d=document.createElement('div');d.textContent=v==null?'':v;return d.innerHTML; }

function switchTab(t) {
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('panel-' + t).classList.add('active');
    if (t === 'register') loadRegister();
}

// Load staff list on init
fetch('/api/payroll/staff-list')
    .then(r => r.json())
    .then(d => {
        staffData = d.rows || [];
        const opts = '<option value="">-- Select Staff --</option>' + staffData.map(s =>
            `<option value="${esc(s.scode)}">${esc(s.sname)} (${esc(s.scode)})</option>`
        ).join('');
        document.getElementById('staff_code').innerHTML = opts;
        document.getElementById('str_staff').innerHTML = opts;
    });

function loadStructure() {
    const sc = document.getElementById('staff_code').value;
    if (!sc) return;
}

function calcSalary() {
    const sc = document.getElementById('staff_code').value;
    if (!sc) { alert('Select a staff member.'); return; }
    const month = document.getElementById('proc_month').value;
    const wd = parseInt(document.getElementById('working_days').value||26);
    const dp = parseInt(document.getElementById('days_present').value||26);

    fetch('/api/payroll/calculate', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
        body: JSON.stringify({ staff_code: sc, month, working_days: wd, days_present: dp })
    }).then(r => r.json()).then(d => {
        if (!d.ok) { alert('Error: ' + d.message); return; }
        lastCalc = d.calc;
        renderCalcResult(d.calc);
        document.getElementById('btn-save-salary').style.display = '';
        const slipBtn = document.getElementById('btn-slip');
        slipBtn.href = `/staff-payroll/salary-slip?staff_code=${sc}&month=${month}`;
        slipBtn.style.display = '';
    });
}

function renderCalcResult(c) {
    const el = document.getElementById('calc-result-area');
    el.innerHTML = `<h2>Calculation Preview</h2>
    <div class="calc-box">
        <div class="calc-row"><span>Days Present / Working</span><span>${c.days_present} / ${c.working_days}</span></div>
        <div class="calc-row"><span>Basic Earned</span><span>₹ ${n2(c.basic_earned)}</span></div>
        <div class="calc-row"><span>HRA</span><span>₹ ${n2(c.hra_earned)}</span></div>
        <div class="calc-row"><span>DA</span><span>₹ ${n2(c.da_earned)}</span></div>
        <div class="calc-row"><span>Other Allowances</span><span>₹ ${n2(c.other_allowances)}</span></div>
        <div class="calc-row" style="font-weight:700;"><span>Gross Salary</span><span>₹ ${n2(c.gross_salary)}</span></div>
        <div style="font-size:11px;color:#94a3b8;padding:6px 0 4px;">Deductions</div>
        <div class="calc-row"><span class="deduction">PF Deduction</span><span class="deduction">- ₹ ${n2(c.pf_deduction)}</span></div>
        <div class="calc-row"><span class="deduction">ESI Deduction</span><span class="deduction">- ₹ ${n2(c.esi_deduction)}</span></div>
        <div class="calc-row"><span class="deduction">TDS</span><span class="deduction">- ₹ ${n2(c.tds_deduction)}</span></div>
        <div class="calc-row"><span class="deduction">Advance</span><span class="deduction">- ₹ ${n2(c.advance_deduction)}</span></div>
        <div class="calc-row calc-total"><span>NET SALARY</span><span style="color:#15803d;">₹ ${n2(c.net_salary)}</span></div>
    </div>`;
}

function saveSalary() {
    if (!lastCalc) return;
    lastCalc.payment_mode = document.getElementById('pay_mode').value;

    fetch('/api/payroll/save-salary', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
        body: JSON.stringify({ salary: lastCalc })
    }).then(r => r.json()).then(d => {
        if (d.ok) alert('Salary saved and finalized.');
        else alert('Error: ' + d.message);
    });
}

function loadRegister(e) {
    if (e) e.preventDefault();
    const month = document.getElementById('reg_month').value;
    const tbody = document.querySelector('#reg-table tbody');
    tbody.innerHTML = '<tr><td colspan="12" class="empty">Loading&hellip;</td></tr>';

    fetch(`/api/payroll/monthly-register?month=${month}`)
        .then(r => r.json())
        .then(d => {
            const rows = d.rows || [];
            const totals = d.totals || {};

            document.getElementById('reg-summary').innerHTML = `<div class="summary-box">
                <div class="s-card"><div class="lbl">Staff Count</div><div class="val">${totals.count||0}</div></div>
                <div class="s-card"><div class="lbl">Total Gross</div><div class="val">₹ ${n2(totals.gross_salary)}</div></div>
                <div class="s-card"><div class="lbl">Total PF+ESI</div><div class="val">₹ ${n2((totals.pf_deduction||0)+(totals.esi_deduction||0))}</div></div>
                <div class="s-card"><div class="lbl">Total Net</div><div class="val" style="color:#15803d;">₹ ${n2(totals.net_salary)}</div></div>
            </div>`;

            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="12" class="empty">No salary records for this month.</td></tr>'; return; }
            tbody.innerHTML = rows.map(r => `<tr>
                <td>${esc(r.staff_code)}</td>
                <td>${esc(r.staff_name || r.staff_code)}</td>
                <td class="num">${r.working_days}</td>
                <td class="num">${r.days_present}</td>
                <td class="num">${n2(r.gross_salary)}</td>
                <td class="num">${n2(r.pf_deduction)}</td>
                <td class="num">${n2(r.esi_deduction)}</td>
                <td class="num">${n2(r.tds_deduction)}</td>
                <td class="num">${n2(r.advance_deduction)}</td>
                <td class="num" style="font-weight:700;color:#15803d;">${n2(r.net_salary)}</td>
                <td style="text-transform:capitalize;">${esc(r.status)}</td>
                <td><a href="/staff-payroll/salary-slip?staff_code=${esc(r.staff_code)}&month=${month}" target="_blank" style="color:#173b63;">Slip</a></td>
            </tr>`).join('');

            document.getElementById('reg-tfoot').innerHTML = `<tr>
                <th colspan="4">TOTAL</th>
                <th class="num">${n2(rows.reduce((s,r)=>s+(+r.gross_salary||0),0))}</th>
                <th class="num">${n2(rows.reduce((s,r)=>s+(+r.pf_deduction||0),0))}</th>
                <th class="num">${n2(rows.reduce((s,r)=>s+(+r.esi_deduction||0),0))}</th>
                <th class="num">${n2(rows.reduce((s,r)=>s+(+r.tds_deduction||0),0))}</th>
                <th class="num">${n2(rows.reduce((s,r)=>s+(+r.advance_deduction||0),0))}</th>
                <th class="num">${n2(rows.reduce((s,r)=>s+(+r.net_salary||0),0))}</th>
                <th colspan="2"></th>
            </tr>`;
        });
}

function loadStaffStructure() {
    const sc = document.getElementById('str_staff').value;
    if (!sc) return;
    fetch(`/api/payroll/structure?staff_code=${encodeURIComponent(sc)}`)
        .then(r => r.json())
        .then(d => {
            if (d.ok && d.structure) {
                const s = d.structure;
                document.getElementById('str_basic').value = s.basic_salary || 0;
                document.getElementById('str_hra').value = s.hra || 0;
                document.getElementById('str_da').value = s.da || 0;
                document.getElementById('str_other').value = s.other_allowances || 0;
                document.getElementById('str_pf').value = s.pf_percent || 12;
                document.getElementById('str_esi').value = s.esi_percent || 0.75;
                document.getElementById('str_tds').value = s.tds_monthly || 0;
                previewStructure();
            }
        });
}

function previewStructure() {
    const basic = parseFloat(document.getElementById('str_basic').value||0);
    const hra   = parseFloat(document.getElementById('str_hra').value||0);
    const da    = parseFloat(document.getElementById('str_da').value||0);
    const other = parseFloat(document.getElementById('str_other').value||0);
    const pf_pct = parseFloat(document.getElementById('str_pf').value||12);
    const esi_pct = parseFloat(document.getElementById('str_esi').value||0.75);
    const tds   = parseFloat(document.getElementById('str_tds').value||0);
    const gross = basic + hra + da + other;
    const pf    = basic * pf_pct / 100;
    const esi   = gross <= 21000 ? gross * esi_pct / 100 : 0;
    const net   = gross - pf - esi - tds;

    document.getElementById('str-preview').innerHTML = `<h2>CTC Preview (Full Month)</h2>
    <div class="calc-box">
        <div class="calc-row"><span>Basic</span><span>₹ ${n2(basic)}</span></div>
        <div class="calc-row"><span>HRA</span><span>₹ ${n2(hra)}</span></div>
        <div class="calc-row"><span>DA</span><span>₹ ${n2(da)}</span></div>
        <div class="calc-row"><span>Other</span><span>₹ ${n2(other)}</span></div>
        <div class="calc-row" style="font-weight:700;"><span>Gross</span><span>₹ ${n2(gross)}</span></div>
        <div class="calc-row deduction"><span>PF (${pf_pct}%)</span><span>- ₹ ${n2(pf)}</span></div>
        <div class="calc-row deduction"><span>ESI${gross>21000?' (not applicable)':` (${esi_pct}%)`}</span><span>- ₹ ${n2(esi)}</span></div>
        <div class="calc-row deduction"><span>TDS</span><span>- ₹ ${n2(tds)}</span></div>
        <div class="calc-row calc-total"><span>NET</span><span style="color:#15803d;">₹ ${n2(net)}</span></div>
    </div>`;
}

function saveStructure() {
    const sc = document.getElementById('str_staff').value;
    if (!sc) { alert('Select a staff member.'); return; }
    fetch('/api/payroll/save-structure', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
        body: JSON.stringify({
            staff_code: sc,
            basic_salary: parseFloat(document.getElementById('str_basic').value||0),
            hra: parseFloat(document.getElementById('str_hra').value||0),
            da: parseFloat(document.getElementById('str_da').value||0),
            other_allowances: parseFloat(document.getElementById('str_other').value||0),
            pf_percent: parseFloat(document.getElementById('str_pf').value||12),
            esi_percent: parseFloat(document.getElementById('str_esi').value||0.75),
            tds_monthly: parseFloat(document.getElementById('str_tds').value||0),
            effective_from: document.getElementById('str_eff').value,
        })
    }).then(r => r.json()).then(d => {
        if (d.ok) alert('Salary structure saved.');
        else alert('Error: ' + d.message);
    });
}
</script>
</body>
</html>
