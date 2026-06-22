<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Inter-Branch Transfer</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 1300px; margin: 14px auto; }
        .card { background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 18px; margin-bottom: 14px; }
        h1 { margin: 0 0 4px; font-size: 22px; color: #173b63; }
        form.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; background: #f0f5ff !important; border-radius: 8px; padding: 10px; margin-bottom: 14px; }
        .field { display: flex; flex-direction: column; gap: 3px; }
        label { font-size: 12px; font-weight: 700; color: #1e3a5f; }
        input, select { height: 36px; border: 1px solid #c4d1e2; border-radius: 6px; padding: 0 10px; font-size: 13px; box-sizing: border-box; }
        button { height: 36px; border: none; border-radius: 6px; padding: 0 14px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #173b63; color: #fff; }
        .btn-success { background: #15803d; color: #fff; }
        .btn-ghost { background: #fff; color: #173b63; border: 1px solid #173b63; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 6px 8px; }
        th { background: #edf4fc; font-size: 11px; font-weight: 700; color: #64748b; white-space: nowrap; }
        td input, td select { width: 100%; height: 30px; font-size: 12px; }
        td.num, th.num { text-align: right; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 60vh; }
        tfoot th { background: #f1f5f9; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-received { background: #dcfce7; color: #15803d; }
        .badge-cancelled { background: #fee2e2; color: #dc2626; }
        .empty { padding: 30px; text-align: center; color: #94a3b8; }
        .tabs { display: flex; border-bottom: 2px solid #e5ecf5; }
        .tab { padding: 8px 18px; cursor: pointer; font-size: 13px; font-weight: 700; color: #64748b; border: 1px solid transparent; border-bottom: none; margin-bottom: -2px; border-radius: 6px 6px 0 0; }
        .tab.active { background: #fff; border-color: #d7dfeb; color: #173b63; border-bottom: 2px solid #fff; }
        .panel { display: none; padding-top: 16px; } .panel.active { display: block; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card" style="padding-bottom:10px;">
        <h1>Inter-Branch Transfer</h1>
        <div class="tabs">
            <div class="tab active" onclick="switchTab('new')">New Transfer</div>
            <div class="tab" onclick="switchTab('receive')">Receive Transfer</div>
        </div>
    </div>

    <!-- New Transfer Panel -->
    <div class="panel active card" id="panel-new">
        <form class="toolbar">
            <div class="field"><label>Transfer Date *</label><input type="date" id="t_date" value="{{ $today }}"></div>
            <div class="field"><label>From Branch *</label><select id="t_from" style="min-width:160px;"></select></div>
            <div class="field"><label>To Branch *</label><select id="t_to" style="min-width:160px;"></select></div>
            <div class="field"><label>&nbsp;</label><button type="button" class="btn-ghost" onclick="addItemRow()">+ Add Item</button></div>
            <div class="field"><label>&nbsp;</label><button type="button" class="btn-primary" onclick="saveTransfer()">Save Transfer</button></div>
        </form>

        <div class="table-wrap">
            <table id="new-table">
                <thead><tr>
                    <th>Item Code</th><th>Barcode</th><th>Description</th>
                    <th class="num" style="min-width:80px;">Weight (g)</th>
                    <th class="num" style="min-width:60px;">Purity</th>
                    <th class="num" style="min-width:80px;">Fine Wt</th>
                    <th class="num" style="min-width:90px;">Rate (₹/g)</th>
                    <th class="num" style="min-width:90px;">Value (₹)</th>
                    <th>Notes</th><th>Del</th>
                </tr></thead>
                <tbody id="new-tbody"><tr><td colspan="10" class="empty">Click "+ Add Item" to start.</td></tr></tbody>
                <tfoot>
                    <tr><th colspan="3">Total</th>
                    <th class="num" id="tot-wt">0.000</th>
                    <th></th>
                    <th class="num" id="tot-fine">0.000</th>
                    <th></th>
                    <th class="num" id="tot-val">0.00</th>
                    <th colspan="2"></th></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Receive Transfer Panel -->
    <div class="panel card" id="panel-receive">
        <form class="toolbar" onsubmit="loadPending(event)">
            <div class="field"><label>To Branch (receiving)</label><select id="recv_branch" style="min-width:160px;"></select></div>
            <div class="field"><label>Status</label>
                <select id="recv_status">
                    <option value="pending">Pending</option>
                    <option value="received">Received</option>
                    <option value="all">All</option>
                </select>
            </div>
            <div class="field"><label>From</label><input type="date" id="recv_d1" value="{{ date('Y-m-01') }}"></div>
            <div class="field"><label>To</label><input type="date" id="recv_d2" value="{{ $today }}"></div>
            <div class="field"><label>&nbsp;</label><button type="submit" class="btn-primary">Load</button></div>
            <div class="field"><label>&nbsp;</label><button type="button" class="btn-success" onclick="markReceived()">Mark Selected Received</button></div>
        </form>

        <div class="table-wrap">
            <table id="recv-table">
                <thead><tr>
                    <th><input type="checkbox" id="chk-all" onchange="toggleAll(this)"></th>
                    <th>Transfer No</th><th>Date</th><th>From</th><th>To</th>
                    <th>Item Code</th><th>Barcode</th><th>Description</th>
                    <th class="num">Weight</th><th class="num">Value</th><th>Status</th>
                </tr></thead>
                <tbody><tr><td colspan="11" class="empty">Load transfers above.</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<script>
let rowCount = 0;
let branches = [];

function switchTab(t) {
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('panel-' + t).classList.add('active');
}

function esc(v) { const d=document.createElement('div');d.textContent=v==null?'':v;return d.innerHTML; }
function n2(v) { return parseFloat(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function n3(v) { return parseFloat(v||0).toFixed(3); }

// Load branches
fetch('/api/branch/list')
    .then(r => r.json())
    .then(d => {
        branches = d.rows || [];
        const opts = '<option value="">Select Branch</option>' + branches.map(b =>
            `<option value="${esc(b.code)}">${esc(b.name)} (${esc(b.code)})</option>`
        ).join('');
        ['t_from','t_to','recv_branch'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = opts;
        });
    });

function addItemRow() {
    rowCount++;
    const tbody = document.getElementById('new-tbody');
    if (tbody.querySelector('.empty')) tbody.innerHTML = '';

    const tr = document.createElement('tr');
    tr.id = 'item-' + rowCount;
    tr.innerHTML = `
        <td><input type="text" class="fi-icode" placeholder="RING001"></td>
        <td><input type="text" class="fi-bc" placeholder="Barcode"></td>
        <td><input type="text" class="fi-desc" placeholder="Ring, Chain..."></td>
        <td><input type="number" class="fi-wt num" min="0" step="0.001" oninput="calcRow(this)"></td>
        <td><input type="number" class="fi-purity num" value="916" min="0" step="1" oninput="calcRow(this)"></td>
        <td><input type="number" class="fi-fine num" min="0" step="0.001" readonly style="background:#f8f9fa;"></td>
        <td><input type="number" class="fi-rate num" min="0" step="0.01" oninput="calcRow(this)"></td>
        <td><input type="number" class="fi-value num" min="0" step="0.01" readonly style="background:#f8f9fa;"></td>
        <td><input type="text" class="fi-notes"></td>
        <td><button onclick="document.getElementById('item-${rowCount}').remove();updateTotals();" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;border-radius:4px;height:28px;cursor:pointer;width:28px;">✕</button></td>
    `;
    tbody.appendChild(tr);
}

function calcRow(el) {
    const tr = el.closest('tr');
    const wt = parseFloat(tr.querySelector('.fi-wt').value||0);
    const purity = parseFloat(tr.querySelector('.fi-purity').value||0);
    const rate = parseFloat(tr.querySelector('.fi-rate').value||0);
    const fine = wt * purity / 1000;
    tr.querySelector('.fi-fine').value = fine.toFixed(3);
    tr.querySelector('.fi-value').value = (fine * rate).toFixed(2);
    updateTotals();
}

function updateTotals() {
    let totWt = 0, totFine = 0, totVal = 0;
    document.querySelectorAll('[id^="item-"]').forEach(tr => {
        totWt += parseFloat(tr.querySelector('.fi-wt')?.value||0);
        totFine += parseFloat(tr.querySelector('.fi-fine')?.value||0);
        totVal += parseFloat(tr.querySelector('.fi-value')?.value||0);
    });
    document.getElementById('tot-wt').textContent = n3(totWt);
    document.getElementById('tot-fine').textContent = n3(totFine);
    document.getElementById('tot-val').textContent = n2(totVal);
}

function saveTransfer() {
    const fromBranch = document.getElementById('t_from').value;
    const toBranch = document.getElementById('t_to').value;
    if (!fromBranch || !toBranch) { alert('Select From and To branch.'); return; }
    if (fromBranch === toBranch) { alert('From and To cannot be same.'); return; }

    const items = Array.from(document.querySelectorAll('[id^="item-"]')).map(tr => ({
        item_code: tr.querySelector('.fi-icode')?.value.trim() || '',
        barcode:   tr.querySelector('.fi-bc')?.value.trim() || '',
        item_desc: tr.querySelector('.fi-desc')?.value.trim() || '',
        weight:    parseFloat(tr.querySelector('.fi-wt')?.value||0),
        purity:    parseFloat(tr.querySelector('.fi-purity')?.value||0),
        fine_weight: parseFloat(tr.querySelector('.fi-fine')?.value||0),
        rate:      parseFloat(tr.querySelector('.fi-rate')?.value||0),
        value:     parseFloat(tr.querySelector('.fi-value')?.value||0),
        notes:     tr.querySelector('.fi-notes')?.value.trim() || '',
    }));

    if (!items.length) { alert('Add at least one item.'); return; }

    fetch('/api/branch/transfer/save', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
        body: JSON.stringify({ transfer_date: document.getElementById('t_date').value, from_branch: fromBranch, to_branch: toBranch, items })
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            alert(`Transfer saved. Transfer No: ${d.transfer_no}. Items: ${d.items_saved}`);
            document.getElementById('new-tbody').innerHTML = '<tr><td colspan="10" class="empty">Click "+ Add Item" to start a new transfer.</td></tr>';
            rowCount = 0; updateTotals();
        } else alert('Error: ' + d.message);
    });
}

function loadPending(e) {
    if (e) e.preventDefault();
    const tbody = document.querySelector('#recv-table tbody');
    tbody.innerHTML = '<tr><td colspan="11" class="empty">Loading&hellip;</td></tr>';
    const d1 = document.getElementById('recv_d1').value;
    const d2 = document.getElementById('recv_d2').value;
    const st = document.getElementById('recv_status').value;

    fetch(`/api/branch/transfer/report?date1=${d1}&date2=${d2}&status=${st}`)
        .then(r => r.json())
        .then(d => {
            if (!d.ok || !d.rows.length) { tbody.innerHTML = '<tr><td colspan="11" class="empty">No transfers found.</td></tr>'; return; }
            tbody.innerHTML = d.rows.map(r => `<tr>
                <td><input type="checkbox" class="recv-chk" value="${r.id}" ${r.status==='received'?'disabled':''}></td>
                <td>${esc(r.transfer_no)}</td>
                <td>${r.transfer_date}</td>
                <td>${esc(r.from_branch)}</td>
                <td>${esc(r.to_branch)}</td>
                <td>${esc(r.item_code)}</td>
                <td>${esc(r.barcode)}</td>
                <td>${esc(r.item_desc)}</td>
                <td class="num">${n3(r.weight)}</td>
                <td class="num">${n2(r.value)}</td>
                <td><span class="badge badge-${r.status}">${r.status}</span></td>
            </tr>`).join('');
        });
}

function toggleAll(chk) {
    document.querySelectorAll('.recv-chk:not(:disabled)').forEach(c => c.checked = chk.checked);
}

function markReceived() {
    const ids = Array.from(document.querySelectorAll('.recv-chk:checked')).map(c => parseInt(c.value));
    if (!ids.length) { alert('Select transfers to mark received.'); return; }

    fetch('/api/branch/transfer/receive', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
        body: JSON.stringify({ ids })
    }).then(r => r.json()).then(d => {
        if (d.ok) { alert(`${ids.length} transfer(s) marked as received.`); loadPending(); }
        else alert('Error: ' + d.message);
    });
}
</script>
</body>
</html>
