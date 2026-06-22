<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Branch Master</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 1200px; margin: 14px auto; }
        .card { background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 18px; margin-bottom: 14px; }
        h1 { margin: 0 0 14px; font-size: 22px; color: #173b63; }
        h2 { margin: 0 0 14px; font-size: 16px; color: #173b63; border-bottom: 2px solid #e5ecf5; padding-bottom: 8px; }
        label { font-size: 12px; font-weight: 700; color: #1e3a5f; display: block; margin-bottom: 3px; }
        input, select { height: 36px; border: 1px solid #c4d1e2; border-radius: 6px; padding: 0 10px; font-size: 13px; box-sizing: border-box; width: 100%; }
        button { height: 36px; border: none; border-radius: 6px; padding: 0 16px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #173b63; color: #fff; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-ghost { background: #fff; color: #173b63; border: 1px solid #173b63; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .field { margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 8px 10px; }
        th { background: #edf4fc; font-size: 12px; font-weight: 700; color: #64748b; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .badge-ho { background: #fef3c7; color: #92400e; }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-inactive { background: #f1f5f9; color: #64748b; }
        .empty { padding: 30px; text-align: center; color: #94a3b8; }
        .actions { display: flex; gap: 6px; }
        .section-divider { margin: 14px 0; border: none; border-top: 1px solid #e5ecf5; }
        .db-section { background: #f8f9fa; border: 1px solid #e5ecf5; border-radius: 8px; padding: 12px; margin-top: 12px; }
        .db-section h3 { margin: 0 0 10px; font-size: 13px; color: #64748b; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Branch Master</h1>
        <button class="btn-primary" onclick="showAddForm()">+ Add Branch</button>
    </div>

    <!-- Add/Edit Form -->
    <div class="card" id="branch-form" style="display:none;">
        <h2 id="form-title">Add New Branch</h2>
        <input type="hidden" id="branch_id" value="0">
        <div class="grid2">
            <div>
                <div class="field"><label>Branch Code *</label><input type="text" id="br_code" placeholder="e.g. HO, BR01" maxlength="20" style="text-transform:uppercase;"></div>
                <div class="field"><label>Branch Name *</label><input type="text" id="br_name"></div>
                <div class="field"><label>Address</label><input type="text" id="br_addr"></div>
                <div class="grid2">
                    <div class="field"><label>City</label><input type="text" id="br_city"></div>
                    <div class="field"><label>Phone</label><input type="text" id="br_phone"></div>
                </div>
                <div class="field"><label>Email</label><input type="email" id="br_email"></div>
                <div class="field"><label>GSTIN</label><input type="text" id="br_gstin" maxlength="15" placeholder="15-char GSTIN"></div>
                <div class="grid2">
                    <div class="field"><label>Is Head Office?</label>
                        <select id="br_ho"><option value="0">No</option><option value="1">Yes</option></select>
                    </div>
                    <div class="field"><label>Active?</label>
                        <select id="br_active"><option value="1">Yes</option><option value="0">No</option></select>
                    </div>
                </div>
            </div>
            <div>
                <div class="db-section">
                    <h3>Database Connection (for separate-DB branches)</h3>
                    <div class="field"><label>DB Host</label><input type="text" id="br_dbhost" placeholder="localhost"></div>
                    <div class="field"><label>DB Name</label><input type="text" id="br_dbname"></div>
                    <div class="field"><label>DB User</label><input type="text" id="br_dbuser"></div>
                    <div class="field"><label>DB Password</label><input type="password" id="br_dbpass"></div>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:14px;">
            <button class="btn-primary" onclick="saveBranch()">Save Branch</button>
            <button class="btn-ghost" onclick="hideForms()">Cancel</button>
        </div>
    </div>

    <!-- Branch List -->
    <div class="card">
        <h2>All Branches</h2>
        <table id="branch-table">
            <thead><tr><th>Code</th><th>Name</th><th>City</th><th>Phone</th><th>GSTIN</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody><tr><td colspan="8" class="empty">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
</div>

<script>
function esc(v) { const d=document.createElement('div');d.textContent=v==null?'':v;return d.innerHTML; }

function showAddForm() {
    clearForm();
    document.getElementById('form-title').textContent = 'Add New Branch';
    document.getElementById('branch-form').style.display = '';
    document.getElementById('branch-form').scrollIntoView({ behavior: 'smooth' });
}

function hideForms() {
    document.getElementById('branch-form').style.display = 'none';
    clearForm();
}

function clearForm() {
    ['branch_id','br_code','br_name','br_addr','br_city','br_phone','br_email','br_gstin','br_dbhost','br_dbname','br_dbuser','br_dbpass'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = id === 'branch_id' ? '0' : '';
    });
    document.getElementById('br_ho').value = '0';
    document.getElementById('br_active').value = '1';
}

function saveBranch() {
    const name = document.getElementById('br_name').value.trim();
    const code = document.getElementById('br_code').value.trim().toUpperCase();
    if (!code || !name) { alert('Branch code and name are required.'); return; }

    fetch('/api/branch/save', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
        body: JSON.stringify({
            id: parseInt(document.getElementById('branch_id').value||0),
            code, name,
            address: document.getElementById('br_addr').value.trim(),
            city: document.getElementById('br_city').value.trim(),
            phone: document.getElementById('br_phone').value.trim(),
            email: document.getElementById('br_email').value.trim(),
            gstin: document.getElementById('br_gstin').value.trim(),
            db_host: document.getElementById('br_dbhost').value.trim(),
            db_name: document.getElementById('br_dbname').value.trim(),
            db_user: document.getElementById('br_dbuser').value.trim(),
            db_pass: document.getElementById('br_dbpass').value,
            is_head_office: parseInt(document.getElementById('br_ho').value||0),
            is_active: parseInt(document.getElementById('br_active').value||1),
        })
    }).then(r => r.json()).then(d => {
        if (d.ok) { hideForms(); loadBranches(); alert('Branch saved.'); }
        else alert('Error: ' + d.message);
    });
}

function editBranch(b) {
    document.getElementById('branch_id').value = b.id;
    document.getElementById('br_code').value = b.code;
    document.getElementById('br_name').value = b.name;
    document.getElementById('br_addr').value = b.address || '';
    document.getElementById('br_city').value = b.city || '';
    document.getElementById('br_phone').value = b.phone || '';
    document.getElementById('br_email').value = b.email || '';
    document.getElementById('br_gstin').value = b.gstin || '';
    document.getElementById('br_dbhost').value = b.db_host || '';
    document.getElementById('br_dbname').value = b.db_name || '';
    document.getElementById('br_dbuser').value = b.db_user || '';
    document.getElementById('br_ho').value = b.is_head_office ? '1' : '0';
    document.getElementById('br_active').value = b.is_active ? '1' : '0';
    document.getElementById('form-title').textContent = 'Edit Branch: ' + b.name;
    document.getElementById('branch-form').style.display = '';
    document.getElementById('branch-form').scrollIntoView({ behavior: 'smooth' });
}

function deleteBranch(id, name) {
    if (!confirm(`Delete branch "${name}"? This cannot be undone.`)) return;
    fetch('/api/branch/delete', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
        body: JSON.stringify({ id })
    }).then(r => r.json()).then(d => {
        if (d.ok) loadBranches();
        else alert('Error: ' + d.message);
    });
}

function loadBranches() {
    fetch('/api/branch/list')
        .then(r => r.json())
        .then(d => {
            const tbody = document.querySelector('#branch-table tbody');
            if (!d.ok || !d.rows.length) { tbody.innerHTML = '<tr><td colspan="8" class="empty">No branches found. Add one above.</td></tr>'; return; }
            tbody.innerHTML = d.rows.map(b => `<tr>
                <td><strong>${esc(b.code)}</strong></td>
                <td>${esc(b.name)}</td>
                <td>${esc(b.city||'')}</td>
                <td>${esc(b.phone||'')}</td>
                <td style="font-size:12px;font-family:monospace;">${esc(b.gstin||'')}</td>
                <td>${b.is_head_office ? '<span class="badge badge-ho">Head Office</span>' : '<span class="badge badge-active">Branch</span>'}</td>
                <td>${b.is_active ? '<span class="badge badge-active">Active</span>' : '<span class="badge badge-inactive">Inactive</span>'}</td>
                <td><div class="actions">
                    <button class="btn-ghost" onclick='editBranch(${JSON.stringify(b)})' style="height:28px;font-size:11px;">Edit</button>
                    ${!b.is_head_office ? `<button class="btn-danger" onclick="deleteBranch(${b.id},'${esc(b.name)}')" style="height:28px;font-size:11px;">Delete</button>` : ''}
                </div></td>
            </tr>`).join('');
        });
}

loadBranches();
</script>
</body>
</html>
