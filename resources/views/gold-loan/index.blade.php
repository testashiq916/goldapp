<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Gold Loan Entry</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 1200px; margin: 14px auto; }
        .card { background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 18px; margin-bottom: 14px; }
        h1 { margin: 0 0 4px; font-size: 22px; color: #173b63; }
        h2 { margin: 0 0 14px; font-size: 16px; color: #173b63; border-bottom: 2px solid #e5ecf5; padding-bottom: 8px; }
        form.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; background: #f0f5ff !important; border-radius: 8px; padding: 10px; margin-bottom: 14px; }
        .field { display: flex; flex-direction: column; gap: 3px; }
        label { font-size: 12px; font-weight: 700; color: #1e3a5f; }
        input, select, textarea { border: 1px solid #c4d1e2; border-radius: 6px; padding: 0 10px; font-size: 13px; height: 36px; box-sizing: border-box; }
        textarea { height: auto; padding: 8px 10px; resize: vertical; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        button { height: 36px; border: none; border-radius: 6px; padding: 0 16px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #173b63; color: #fff; }
        .btn-success { background: #15803d; color: #fff; }
        .btn-warning { background: #b45309; color: #fff; }
        .btn-ghost { background: #fff; color: #173b63; border: 1px solid #173b63; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 10px; }
        th { background: #edf4fc; font-size: 12px; font-weight: 700; color: #64748b; }
        td.num, th.num { text-align: right; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-closed { background: #f1f5f9; color: #64748b; }
        .badge-overdue { background: #fee2e2; color: #dc2626; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 400px; }
        .calc-result { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 12px 16px; margin-top: 12px; display: none; }
        .item-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 36px; gap: 6px; align-items: end; margin-bottom: 6px; }
        .empty { padding: 30px; text-align: center; color: #94a3b8; }
        .tabs { display: flex; gap: 0; border-bottom: 2px solid #e5ecf5; margin-bottom: 0; }
        .tab { padding: 8px 18px; cursor: pointer; font-size: 13px; font-weight: 700; color: #64748b; border: 1px solid transparent; border-bottom: none; margin-bottom: -2px; border-radius: 6px 6px 0 0; }
        .tab.active { background: #fff; border-color: #d7dfeb; color: #173b63; border-bottom: 2px solid #fff; }
        .panel { display: none; padding-top: 16px; } .panel.active { display: block; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card" style="padding-bottom:10px;">
        <h1>Gold Loan</h1>
        <div class="tabs">
            <div class="tab active" onclick="switchTab('entry')">Loan Entry</div>
            <div class="tab" onclick="switchTab('list')">Active Loans</div>
            <div class="tab" onclick="switchTab('collection')">Collection</div>
            <div class="tab" onclick="switchTab('calc')">Interest Calculator</div>
        </div>
    </div>

    <!-- Loan Entry Panel -->
    <div class="panel active card" id="panel-entry">
        <h2>New Loan / Edit Loan</h2>
        <div class="grid2">
            <div>
                <div class="grid2" style="margin-bottom:10px;">
                    <div class="field">
                        <label>Loan No</label>
                        <input type="text" id="loan_no" placeholder="Auto-generated">
                    </div>
                    <div class="field">
                        <label>Loan Date *</label>
                        <input type="date" id="loan_date" value="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="field" style="margin-bottom:10px;">
                    <label>Customer Search</label>
                    <div style="display:flex;gap:6px;">
                        <input type="text" id="cust_search" placeholder="Name / mobile / code" style="flex:1;">
                        <button class="btn-ghost" onclick="searchCustomer()">Search</button>
                    </div>
                </div>
                <div id="cust_results" style="display:none;border:1px solid #d8e2ef;border-radius:6px;max-height:160px;overflow:auto;margin-bottom:10px;"></div>
                <div class="grid2" style="margin-bottom:10px;">
                    <div class="field"><label>Party Code</label><input type="text" id="pcode" readonly style="background:#f8f9fa;"></div>
                    <div class="field"><label>Party Name</label><input type="text" id="pname" readonly style="background:#f8f9fa;"></div>
                </div>
                <div class="grid3" style="margin-bottom:10px;">
                    <div class="field"><label>Loan Amount (₹)</label><input type="number" id="loan_amt" oninput="recalc()" min="0" step="0.01"></div>
                    <div class="field"><label>Interest Rate %</label><input type="number" id="interest_rate" value="12" oninput="recalc()" min="0" step="0.01"></div>
                    <div class="field"><label>Period (Months)</label><input type="number" id="period_months" value="12" oninput="recalc()" min="1"></div>
                </div>
                <div class="grid2" style="margin-bottom:10px;">
                    <div class="field"><label>Due Date</label><input type="date" id="due_date"></div>
                    <div class="field">
                        <label>Status</label>
                        <select id="loan_status"><option value="A">Active</option><option value="C">Closed</option></select>
                    </div>
                </div>
                <div class="field" style="margin-bottom:10px;"><label>Remarks</label><textarea id="remarks" rows="2"></textarea></div>
            </div>
            <div>
                <h2 style="font-size:14px;">Pledged Items</h2>
                <div id="items-container"></div>
                <button class="btn-ghost" style="width:100%;margin-bottom:10px;" onclick="addItemRow()">+ Add Item</button>
                <div style="background:#f8fbff;border:1px solid #d8e2ef;border-radius:8px;padding:12px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;">
                        <span>Total Net Weight</span><strong id="tot_net_wt">0.000 g</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;">
                        <span>Total Fine Weight</span><strong id="tot_fine_wt">0.000 g</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:15px;color:#173b63;">
                        <span><strong>Loan Amount</strong></span><strong id="loan_display">₹ 0.00</strong>
                    </div>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:14px;">
            <button class="btn-primary" onclick="saveLoan()">Save Loan</button>
            <button class="btn-ghost" onclick="clearForm()">Clear</button>
        </div>
    </div>

    <!-- Active Loans Panel -->
    <div class="panel card" id="panel-list">
        <form class="toolbar" onsubmit="loadLoans(event)">
            <div class="field"><label>From</label><input type="date" id="ll_d1" value="{{ date('Y-m-01') }}"></div>
            <div class="field"><label>To</label><input type="date" id="ll_d2" value="{{ date('Y-m-d') }}"></div>
            <div class="field"><label>Status</label>
                <select id="ll_status">
                    <option value="all">All</option>
                    <option value="A">Active</option>
                    <option value="C">Closed</option>
                </select>
            </div>
            <div class="field"><label>&nbsp;</label><button type="submit" class="btn-primary">Show</button></div>
        </form>
        <div class="table-wrap">
            <table id="loans-table">
                <thead><tr><th>Loan No</th><th>Date</th><th>Customer</th><th class="num">Amount</th><th class="num">Rate%</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th><th>Action</th></tr></thead>
                <tbody><tr><td colspan="9" class="empty">Click Show to load loans</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- Collection Panel -->
    <div class="panel card" id="panel-collection">
        <div class="grid2">
            <div>
                <h2>Add Collection</h2>
                <div class="field" style="margin-bottom:10px;"><label>Loan No *</label><input type="text" id="col_loan_no" placeholder="Enter loan number"></div>
                <div class="grid2" style="margin-bottom:10px;">
                    <div class="field"><label>Date *</label><input type="date" id="col_date" value="{{ date('Y-m-d') }}"></div>
                    <div class="field"><label>Amount (₹) *</label><input type="number" id="col_amt" min="0" step="0.01"></div>
                </div>
                <div class="field" style="margin-bottom:10px;"><label>Receipt No</label><input type="text" id="col_rcpt"></div>
                <div class="field" style="margin-bottom:10px;"><label>Remarks</label><input type="text" id="col_remarks"></div>
                <button class="btn-success" onclick="saveCollection()">Save Collection</button>
            </div>
            <div id="col_loan_info" style="display:none;">
                <h2>Loan Details</h2>
            </div>
        </div>
    </div>

    <!-- Calculator Panel -->
    <div class="panel card" id="panel-calc">
        <h2>Interest Calculator</h2>
        <div style="max-width:400px;">
            <div class="field" style="margin-bottom:10px;"><label>Loan Amount (₹)</label><input type="number" id="calc_amt" min="0" step="0.01"></div>
            <div class="field" style="margin-bottom:10px;"><label>Interest Rate % (per annum)</label><input type="number" id="calc_rate" value="12" step="0.01"></div>
            <div class="grid2" style="margin-bottom:10px;">
                <div class="field"><label>From Date</label><input type="date" id="calc_d1" value="{{ date('Y-m-01') }}"></div>
                <div class="field"><label>To Date</label><input type="date" id="calc_d2" value="{{ date('Y-m-d') }}"></div>
            </div>
            <button class="btn-primary" onclick="calcInterest()">Calculate</button>
            <div id="calc_result" class="calc-result" style="margin-top:12px;display:none;"></div>
        </div>
    </div>
</div>

<script>
function switchTab(t) {
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('panel-' + t).classList.add('active');
    if (t === 'list') loadLoans();
}

function n2(v) { return parseFloat(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function esc(v) { const d=document.createElement('div');d.textContent=v==null?'':v;return d.innerHTML; }

let itemCount = 0;
function addItemRow() {
    itemCount++;
    const c = document.getElementById('items-container');
    const row = document.createElement('div');
    row.className = 'item-row';
    row.id = 'item-' + itemCount;
    row.innerHTML = `
        <input type="text" placeholder="Description" class="item-desc" oninput="updateTotals()">
        <input type="number" placeholder="Gross Wt" class="item-gwt" min="0" step="0.001" oninput="updateTotals()">
        <input type="number" placeholder="Net Wt" class="item-nwt" min="0" step="0.001" oninput="updateTotals()">
        <input type="number" placeholder="Purity" class="item-purity" value="916" min="0" step="1" oninput="updateTotals()">
        <input type="number" placeholder="Fine Wt" class="item-finewt" min="0" step="0.001" readonly style="background:#f8f9fa;">
        <button onclick="this.closest('.item-row').remove();updateTotals();" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;border-radius:4px;height:36px;cursor:pointer;">✕</button>
    `;
    c.appendChild(row);
    row.querySelector('.item-nwt').addEventListener('input', function() {
        const nwt = parseFloat(this.value||0);
        const purity = parseFloat(this.closest('.item-row').querySelector('.item-purity').value||0);
        this.closest('.item-row').querySelector('.item-finewt').value = (nwt * purity / 1000).toFixed(3);
        updateTotals();
    });
    row.querySelector('.item-purity').addEventListener('input', function() {
        const nwt = parseFloat(this.closest('.item-row').querySelector('.item-nwt').value||0);
        const purity = parseFloat(this.value||0);
        this.closest('.item-row').querySelector('.item-finewt').value = (nwt * purity / 1000).toFixed(3);
        updateTotals();
    });
}

function updateTotals() {
    let totNwt = 0, totFine = 0;
    document.querySelectorAll('.item-row').forEach(r => {
        totNwt += parseFloat(r.querySelector('.item-nwt').value||0);
        totFine += parseFloat(r.querySelector('.item-finewt').value||0);
    });
    document.getElementById('tot_net_wt').textContent = totNwt.toFixed(3) + ' g';
    document.getElementById('tot_fine_wt').textContent = totFine.toFixed(3) + ' g';
    const la = parseFloat(document.getElementById('loan_amt').value||0);
    document.getElementById('loan_display').textContent = '₹ ' + n2(la);
}

function recalc() {
    const la = parseFloat(document.getElementById('loan_amt').value||0);
    document.getElementById('loan_display').textContent = '₹ ' + n2(la);
    const months = parseInt(document.getElementById('period_months').value||12);
    const ldate = document.getElementById('loan_date').value;
    if (ldate) {
        const due = new Date(ldate);
        due.setMonth(due.getMonth() + months);
        document.getElementById('due_date').value = due.toISOString().split('T')[0];
    }
}

let selectedCustomer = null;
function searchCustomer() {
    const q = document.getElementById('cust_search').value.trim();
    if (!q) return;
    fetch(`/api/gold-loan/customer-search?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(d => {
            if (!d.ok || !d.rows.length) { document.getElementById('cust_results').style.display = 'none'; return; }
            const div = document.getElementById('cust_results');
            div.style.display = 'block';
            div.innerHTML = d.rows.map(r => `<div onclick="selectCustomer(${JSON.stringify(r)})" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #e5ecf5;font-size:13px;" onmouseover="this.style.background='#f0f5ff'" onmouseout="this.style.background=''">
                <strong>${esc(r.cname)}</strong> &nbsp;<span style="color:#64748b;">${esc(r.ccode)} &mdash; ${esc(r.mobile)}</span>
            </div>`).join('');
        });
}

function selectCustomer(c) {
    selectedCustomer = c;
    document.getElementById('pcode').value = c.ccode || '';
    document.getElementById('pname').value = c.cname || '';
    document.getElementById('cust_results').style.display = 'none';
}

function getItems() {
    return Array.from(document.querySelectorAll('.item-row')).map(r => ({
        item_desc: r.querySelector('.item-desc').value,
        gross_wt:  parseFloat(r.querySelector('.item-gwt').value||0),
        net_wt:    parseFloat(r.querySelector('.item-nwt').value||0),
        purity:    parseFloat(r.querySelector('.item-purity').value||0),
        fine_wt:   parseFloat(r.querySelector('.item-finewt').value||0),
    }));
}

function saveLoan() {
    const pcode = document.getElementById('pcode').value.trim();
    const lamt = parseFloat(document.getElementById('loan_amt').value||0);
    if (!pcode) { alert('Please select a customer.'); return; }
    if (lamt <= 0) { alert('Loan amount required.'); return; }

    const data = {
        loan_no: document.getElementById('loan_no').value.trim(),
        loan_date: document.getElementById('loan_date').value,
        pcode, pname: document.getElementById('pname').value,
        loan_amt: lamt,
        interest_rate: parseFloat(document.getElementById('interest_rate').value||12),
        period_months: parseInt(document.getElementById('period_months').value||12),
        due_date: document.getElementById('due_date').value,
        status: document.getElementById('loan_status').value,
        remarks: document.getElementById('remarks').value,
        items: getItems(),
    };

    fetch('/api/gold-loan/save', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        if (d.ok) { alert('Loan saved. Loan No: ' + d.loan_no); clearForm(); }
        else alert('Error: ' + d.message);
    });
}

function clearForm() {
    ['loan_no','pcode','pname','loan_amt','remarks','due_date'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('interest_rate').value = '12';
    document.getElementById('period_months').value = '12';
    document.getElementById('loan_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('items-container').innerHTML = '';
    updateTotals();
    selectedCustomer = null;
    itemCount = 0;
}

function loadLoans(e) {
    if (e) e.preventDefault();
    const d1 = document.getElementById('ll_d1').value;
    const d2 = document.getElementById('ll_d2').value;
    const st = document.getElementById('ll_status').value;
    const tbody = document.querySelector('#loans-table tbody');
    tbody.innerHTML = '<tr><td colspan="9" class="empty">Loading&hellip;</td></tr>';

    fetch(`/api/gold-loan/report?date1=${d1}&date2=${d2}&status=${st}`)
        .then(r => r.json())
        .then(d => {
            if (!d.ok || !d.rows.length) { tbody.innerHTML = '<tr><td colspan="9" class="empty">No loans found</td></tr>'; return; }
            tbody.innerHTML = d.rows.map(r => `<tr>
                <td>${esc(r.loan_no)}</td>
                <td>${r.loan_date}</td>
                <td>${esc(r.pname)}</td>
                <td class="num">${n2(r.loan_amt)}</td>
                <td class="num">${r.interest_rate}%</td>
                <td class="num">${n2(r.paidamt)}</td>
                <td class="num">${n2(r.balance)}</td>
                <td><span class="badge ${r.status==='A'?'badge-active':'badge-closed'}">${r.status==='A'?'Active':'Closed'}</span></td>
                <td><a href="#" onclick="editLoan('${esc(r.loan_no)}')">Edit</a></td>
            </tr>`).join('');
        });
}

function saveCollection() {
    const lno = document.getElementById('col_loan_no').value.trim();
    const amt = parseFloat(document.getElementById('col_amt').value||0);
    if (!lno) { alert('Enter loan number.'); return; }
    if (amt <= 0) { alert('Enter collection amount.'); return; }

    fetch('/api/gold-loan/collection', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
        body: JSON.stringify({
            loan_no: lno,
            coll_date: document.getElementById('col_date').value,
            amount: amt,
            receipt_no: document.getElementById('col_rcpt').value,
            remarks: document.getElementById('col_remarks').value,
        })
    }).then(r => r.json()).then(d => {
        if (d.ok) { alert('Collection saved. Balance: ₹ ' + n2(d.balance)); }
        else alert('Error: ' + d.message);
    });
}

function calcInterest() {
    const amt  = parseFloat(document.getElementById('calc_amt').value||0);
    const rate = parseFloat(document.getElementById('calc_rate').value||12);
    const d1   = document.getElementById('calc_d1').value;
    const d2   = document.getElementById('calc_d2').value;
    if (!amt || !d1 || !d2) { alert('Enter all fields.'); return; }

    fetch('/api/gold-loan/interest-calc', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
        body: JSON.stringify({ loan_amt: amt, rate, date_from: d1, date_to: d2 })
    }).then(r => r.json()).then(d => {
        if (!d.ok) { alert(d.message); return; }
        const el = document.getElementById('calc_result');
        el.style.display = 'block';
        el.innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:14px;">
                <div>Days: <strong>${d.days}</strong></div>
                <div>Rate: <strong>${d.rate}% p.a.</strong></div>
                <div>Principal: <strong>₹ ${n2(d.loan_amt)}</strong></div>
                <div>Interest: <strong style="color:#15803d;">₹ ${n2(d.interest)}</strong></div>
                <div>Total Payable: <strong style="color:#173b63;font-size:16px;">₹ ${n2(d.total_payable)}</strong></div>
            </div>`;
    });
}

// Initialize with one item row
addItemRow();
</script>
</body>
</html>
