<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hallmarking Records (BIS)</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 1400px; margin: 14px auto; }
        .card { background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 18px; margin-bottom: 14px; }
        h1 { margin: 0 0 4px; font-size: 22px; color: #173b63; }
        form.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; background: #f0f5ff !important; border-radius: 8px; padding: 10px; margin-bottom: 14px; }
        .field { display: flex; flex-direction: column; gap: 3px; }
        label { font-size: 12px; font-weight: 700; color: #1e3a5f; }
        input, select { height: 34px; border: 1px solid #c4d1e2; border-radius: 6px; padding: 0 8px; font-size: 12px; box-sizing: border-box; }
        button { height: 34px; border: none; border-radius: 6px; padding: 0 14px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #173b63; color: #fff; }
        .btn-success { background: #15803d; color: #fff; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-ghost { background: #fff; color: #173b63; border: 1px solid #173b63; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 6px 8px; }
        th { background: #edf4fc; font-size: 11px; font-weight: 700; color: #64748b; position: sticky; top: 0; white-space: nowrap; }
        td.num, th.num { text-align: right; }
        td input, td select { width: 100%; box-sizing: border-box; height: 30px; font-size: 12px; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 60vh; }
        tfoot th { background: #f1f5f9; }
        .badge { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .purity-916 { background: #fef3c7; color: #92400e; }
        .purity-750 { background: #dbeafe; color: #1e40af; }
        .purity-585 { background: #dcfce7; color: #15803d; }
        .purity-375 { background: #f3e8ff; color: #6b21a8; }
        .tabs { display: flex; border-bottom: 2px solid #e5ecf5; }
        .tab { padding: 8px 18px; cursor: pointer; font-size: 13px; font-weight: 700; color: #64748b; border: 1px solid transparent; border-bottom: none; margin-bottom: -2px; border-radius: 6px 6px 0 0; }
        .tab.active { background: #fff; border-color: #d7dfeb; color: #173b63; border-bottom: 2px solid #fff; }
        .panel { display: none; padding-top: 16px; } .panel.active { display: block; }
        .empty { padding: 30px; text-align: center; color: #94a3b8; }
        .batch-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 14px; }
        .bs-card { border: 1px solid #d8e2ef; border-radius: 8px; padding: 10px 14px; background: #f8fbff; }
        .bs-card .lbl { font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; }
        .bs-card .val { font-size: 18px; font-weight: 700; color: #173b63; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card" style="padding-bottom:10px;">
        <h1>Hallmarking Records (BIS)</h1>
        <div class="tabs">
            <div class="tab active" onclick="switchTab('entry')">Entry / Edit</div>
            <div class="tab" onclick="switchTab('search')">Search Records</div>
            <div class="tab" onclick="switchTab('batches')">Batch Summary</div>
        </div>
    </div>

    <!-- Entry Panel -->
    <div class="panel active card" id="panel-entry">
        <form class="toolbar" onsubmit="searchRecords(event,'entry')">
            <div class="field"><label>Batch No</label><input type="text" id="e_batch" placeholder="e.g. BIS202401"></div>
            <div class="field"><label>Hallmark Date</label><input type="date" id="e_date" value="{{ date('Y-m-d') }}"></div>
            <div class="field"><label>BIS Centre</label>
                <select id="e_centre">
                    <option value="">Select Centre</option>
                    <option>BIS Hallmarking Centre Mumbai</option>
                    <option>BIS Hallmarking Centre Delhi</option>
                    <option>BIS Hallmarking Centre Chennai</option>
                    <option>BIS Hallmarking Centre Kolkata</option>
                    <option>BIS Hallmarking Centre Hyderabad</option>
                    <option>BIS Hallmarking Centre Bangalore</option>
                    <option>BIS Hallmarking Centre Ahmedabad</option>
                    <option>BIS Hallmarking Centre Jaipur</option>
                    <option>BIS Hallmarking Centre Surat</option>
                    <option>BIS Hallmarking Centre Coimbatore</option>
                    <option>BIS Hallmarking Centre Pune</option>
                    <option>BIS Hallmarking Centre Lucknow</option>
                    <option>BIS Hallmarking Centre Chandigarh</option>
                    <option>BIS Hallmarking Centre Bhubaneswar</option>
                    <option>BIS Hallmarking Centre Patna</option>
                </select>
            </div>
            <div class="field"><label>&nbsp;</label><button type="button" class="btn-success" onclick="addRow()">+ Add Row</button></div>
            <div class="field"><label>&nbsp;</label><button type="button" class="btn-primary" onclick="saveAll()">Save All</button></div>
        </form>

        <div class="table-wrap">
            <table id="entry-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th style="min-width:120px;">Item Code</th>
                        <th style="min-width:130px;">Barcode</th>
                        <th style="min-width:160px;">HUID *</th>
                        <th>Purity</th>
                        <th style="min-width:80px;" class="num">Weight (g)</th>
                        <th style="min-width:130px;">Certificate No</th>
                        <th style="min-width:160px;">Description</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="entry-tbody">
                    <tr><td colspan="9" class="empty">Click "+ Add Row" to start entering records.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Search Panel -->
    <div class="panel card" id="panel-search">
        <form class="toolbar" onsubmit="searchRecords(event,'search')">
            <div class="field"><label>From</label><input type="date" id="s_d1" value="{{ date('Y-m-01') }}"></div>
            <div class="field"><label>To</label><input type="date" id="s_d2" value="{{ date('Y-m-d') }}"></div>
            <div class="field"><label>Batch No</label><input type="text" id="s_batch"></div>
            <div class="field"><label>HUID / Item / Barcode</label><input type="text" id="s_q" placeholder="Search..."></div>
            <div class="field"><label>&nbsp;</label><button type="submit" class="btn-primary">Search</button></div>
        </form>
        <div class="table-wrap">
            <table id="search-table">
                <thead><tr>
                    <th>Batch No</th><th>Date</th><th>Item Code</th><th>Barcode</th>
                    <th>HUID</th><th>Purity</th><th class="num">Weight</th>
                    <th>Certificate</th><th>BIS Centre</th><th>Description</th>
                </tr></thead>
                <tbody><tr><td colspan="10" class="empty">Use filters above to search records.</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- Batch Summary Panel -->
    <div class="panel card" id="panel-batches">
        <button class="btn-primary" onclick="loadBatches()" style="margin-bottom:14px;">Refresh</button>
        <div id="batch-content"><div class="empty">Loading&hellip;</div></div>
    </div>
</div>

<script>
let rowCount = 0;
const PURITIES = [
    {v:'916', l:'916 (22K)'},
    {v:'750', l:'750 (18K)'},
    {v:'585', l:'585 (14K)'},
    {v:'375', l:'375 (9K)'},
];

function switchTab(t) {
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('panel-' + t).classList.add('active');
    if (t === 'batches') loadBatches();
}

function addRow() {
    rowCount++;
    const tbody = document.getElementById('entry-tbody');
    if (tbody.querySelector('.empty')) tbody.innerHTML = '';

    const tr = document.createElement('tr');
    tr.id = 'row-' + rowCount;
    tr.innerHTML = `
        <td style="text-align:center;color:#94a3b8;">${rowCount}</td>
        <td><input type="text" class="f-icode" placeholder="RING001"></td>
        <td><input type="text" class="f-barcode"></td>
        <td><input type="text" class="f-huid" placeholder="HUID16CHARS" maxlength="16" oninput="checkHuid(this,${rowCount})"></td>
        <td><select class="f-purity">
            ${PURITIES.map(p=>`<option value="${p.v}">${p.l}</option>`).join('')}
        </select></td>
        <td><input type="number" class="f-weight num" min="0" step="0.001" placeholder="0.000"></td>
        <td><input type="text" class="f-certno"></td>
        <td><input type="text" class="f-desc" placeholder="Ring, Necklace..."></td>
        <td><button class="btn-danger" onclick="document.getElementById('row-${rowCount}').remove();" style="height:28px;font-size:11px;">✕</button></td>
    `;
    tbody.appendChild(tr);
}

function checkHuid(el, rowId) {
    const huid = el.value.trim();
    if (huid.length < 6) return;
    fetch(`/api/hallmark/check-huid?huid=${encodeURIComponent(huid)}`)
        .then(r => r.json())
        .then(d => {
            el.style.background = d.exists ? '#fee2e2' : '#f0fdf4';
            el.title = d.exists ? 'HUID already exists!' : 'HUID available';
        });
}

function getRowData() {
    return Array.from(document.querySelectorAll('[id^="row-"]')).map(tr => ({
        item_code:    tr.querySelector('.f-icode')?.value.trim() || '',
        barcode:      tr.querySelector('.f-barcode')?.value.trim() || '',
        huid:         tr.querySelector('.f-huid')?.value.trim() || '',
        purity_grade: tr.querySelector('.f-purity')?.value || '916',
        weight:       parseFloat(tr.querySelector('.f-weight')?.value || 0),
        certificate_no: tr.querySelector('.f-certno')?.value.trim() || '',
        article_desc: tr.querySelector('.f-desc')?.value.trim() || '',
    }));
}

function saveAll() {
    const batch = document.getElementById('e_batch').value.trim();
    const bisDate = document.getElementById('e_date').value;
    const bisCentre = document.getElementById('e_centre').value;
    if (!batch) { alert('Enter batch number.'); return; }
    const items = getRowData().filter(r => r.huid);
    if (!items.length) { alert('Add at least one item with HUID.'); return; }

    fetch('/api/hallmark/save', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
        body: JSON.stringify({ batch_no: batch, hallmark_date: bisDate, bis_centre: bisCentre, items })
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            alert(`Saved ${d.saved} records for batch ${batch}.`);
            document.getElementById('entry-tbody').innerHTML = '<tr><td colspan="9" class="empty">Saved. Add more rows to continue.</td></tr>';
            rowCount = 0;
        } else alert('Error: ' + d.message);
    });
}

function searchRecords(e, panel) {
    if (e) e.preventDefault();
    const isSearch = panel === 'search';
    const d1 = document.getElementById(isSearch ? 's_d1' : 'e_date').value;
    const d2 = isSearch ? document.getElementById('s_d2').value : d1;
    const batch = document.getElementById(isSearch ? 's_batch' : 'e_batch').value.trim();
    const q = isSearch ? document.getElementById('s_q').value.trim() : '';

    fetch(`/api/hallmark/load?date1=${d1}&date2=${d2}&batch=${encodeURIComponent(batch)}&q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(d => {
            const tbody = document.querySelector('#search-table tbody');
            if (!d.ok || !d.rows.length) { tbody.innerHTML = '<tr><td colspan="10" class="empty">No records found.</td></tr>'; return; }
            tbody.innerHTML = d.rows.map(r => {
                const pg = r.purity_grade;
                return `<tr>
                    <td>${esc(r.batch_no)}</td>
                    <td>${r.hallmark_date}</td>
                    <td>${esc(r.item_code)}</td>
                    <td>${esc(r.barcode)}</td>
                    <td style="font-family:monospace;">${esc(r.huid)}</td>
                    <td><span class="badge purity-${pg}">${pg}</span></td>
                    <td class="num">${parseFloat(r.weight||0).toFixed(3)}</td>
                    <td>${esc(r.certificate_no)}</td>
                    <td>${esc(r.bis_centre)}</td>
                    <td>${esc(r.article_desc)}</td>
                </tr>`;
            }).join('');
        });
}

function loadBatches() {
    const el = document.getElementById('batch-content');
    el.innerHTML = '<div class="empty">Loading&hellip;</div>';
    fetch('/api/hallmark/batches')
        .then(r => r.json())
        .then(d => {
            if (!d.ok || !d.batches.length) { el.innerHTML = '<div class="empty">No batches found.</div>'; return; }
            const total_items = d.batches.reduce((s,b)=>s+(+b.item_count||0),0);
            const total_wt = d.batches.reduce((s,b)=>s+(+b.total_weight||0),0);
            el.innerHTML = `<div class="batch-summary">
                <div class="bs-card"><div class="lbl">Batches</div><div class="val">${d.batches.length}</div></div>
                <div class="bs-card"><div class="lbl">Total Items</div><div class="val">${total_items}</div></div>
                <div class="bs-card"><div class="lbl">Total Weight</div><div class="val">${total_wt.toFixed(3)} g</div></div>
            </div>
            <div class="table-wrap"><table>
                <thead><tr><th>Batch No</th><th>Date</th><th>BIS Centre</th><th class="num">Items</th><th class="num">Total Weight</th></tr></thead>
                <tbody>${d.batches.map(b=>`<tr>
                    <td>${esc(b.batch_no)}</td>
                    <td>${b.hallmark_date}</td>
                    <td>${esc(b.bis_centre)}</td>
                    <td class="num">${b.item_count}</td>
                    <td class="num">${parseFloat(b.total_weight||0).toFixed(3)} g</td>
                </tr>`).join('')}</tbody>
            </table></div>`;
        });
}

function esc(v) { const d=document.createElement('div');d.textContent=v==null?'':v;return d.innerHTML; }
</script>
</body>
</html>
