<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sales Man Master</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1100px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 12px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .status { margin: 8px 0; padding: 8px 10px; border-radius: 7px; font-size: 12px; display: none; }
        .status.show { display: block; }
        .status.ok { border: 1px solid #9fd5af; background: #e6f6ec; color: #12522d; }
        .status.err { border: 1px solid #f2b1b1; background: #fdeaea; color: #8e1f1f; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 6px; margin-bottom: 10px; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        button.warn { border-color: #a23b3b; background: #fdeaea; color: #8e1f1f; }
        .grid { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 64vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 6px; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2d4f74; z-index: 2; }
        td input, td select { width: 100%; box-sizing: border-box; height: 30px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; }
        td input[readonly] { background: #f3f5f8; color: #555; }
        .muted { color: #627389; font-size: 11px; margin-left: 8px; }
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,.35); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .modal.show { display: flex; }
        .modal-card { width: 760px; background: #fff; border-radius: 10px; border: 1px solid #d7dfeb; padding: 12px; }
        .modal-table { max-height: 360px; overflow: auto; border: 1px solid #d8e2ef; border-radius: 8px; }
        .modal-table tr.active { background: #e9f3ff; }
        .modal-table tr { cursor: pointer; }
        .modal-table tr:hover { background: #f5faff; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Sales Man Master</h1>
    <div id="status" class="status"></div>

    <div class="toolbar">
        <button id="btnAdd" type="button">Add Row</button>
        <button id="btnDeleteRow" type="button" class="warn">Delete Row</button>
        <button id="btnSave" type="button" class="primary">Save All (F9)</button>
        <button id="btnExit" type="button">Exit (Esc)</button>
        <span class="muted" id="countLabel">0 records</span>
    </div>

    <div class="grid">
        <table id="smTable">
            <thead>
                <tr>
                    <th style="width:100px;">Code</th>
                    <th style="width:200px;">Name</th>
                    <th style="width:150px;">Account Code</th>
                    <th>Account Name</th>
                    <th style="width:80px;">Active</th>
                    <th style="width:50px;"></th>
                </tr>
            </thead>
            <tbody id="smBody"></tbody>
        </table>
    </div>
</div>

<div id="acModal" class="modal">
    <div class="modal-card">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
            <strong style="flex:1;">Select Account</strong>
            <input id="acSearch" placeholder="Search code / name..." style="width:260px;height:32px;border:1px solid #bfd0e6;border-radius:6px;padding:0 8px;font-size:12px;">
            <button id="acClose" type="button">Close</button>
        </div>
        <div class="modal-table">
            <table>
                <thead><tr><th style="width:140px;">Code</th><th>Name</th></tr></thead>
                <tbody id="acRows"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const statusEl = document.getElementById('status');
    const BASE = @json(url('/api/accounts/master/sales-man'));
    const smBody = document.getElementById('smBody');

    let accountList = [];
    let activeCodeInput = null;
    let nextCode = 1;

    function status(msg, ok) {
        statusEl.textContent = msg;
        statusEl.className = 'status show ' + (ok ? 'ok' : 'err');
    }

    async function api(payload) {
        const res = await fetch(BASE, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (e) { throw new Error('Invalid server response'); }
        if (!res.ok) throw new Error(data.error || data.message || 'Request failed');
        if (data.success === false) throw new Error(data.error || data.message || 'Request failed');
        return data;
    }

    function escapeHtml(v) {
        return String(v).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
    }

    function addRow(data = null) {
        const code = data ? (data.code || data.smcode || '') : String(nextCode++);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input data-f="code" maxlength="10" value="${escapeHtml(code)}"></td>
            <td><input data-f="name" maxlength="50" value="${data ? escapeHtml(data.name || data.smname || '') : ''}"></td>
            <td><input data-f="accode" maxlength="20" value="${data ? escapeHtml(data.accode || '') : ''}"></td>
            <td><input data-f="acname" readonly value="${data ? escapeHtml(data.acname || '') : ''}"></td>
            <td>
                <select data-f="active">
                    <option value="Y" ${(!data || String(data.active || 'Y').toUpperCase() === 'Y') ? 'selected' : ''}>Y</option>
                    <option value="N" ${(data && String(data.active || 'Y').toUpperCase() === 'N') ? 'selected' : ''}>N</option>
                </select>
            </td>
            <td><button type="button" class="warn row-del" style="height:28px;padding:0 8px;font-size:11px;">X</button></td>
        `;
        smBody.appendChild(tr);
        updateCount();
        if (!data) {
            tr.querySelector('[data-f="name"]').focus();
        }
        return tr;
    }

    function updateCount() {
        document.getElementById('countLabel').textContent = smBody.querySelectorAll('tr').length + ' records';
    }

    function getEntries() {
        return Array.from(smBody.querySelectorAll('tr')).map(tr => {
            return {
                code: (tr.querySelector('[data-f="code"]').value || '').trim(),
                name: (tr.querySelector('[data-f="name"]').value || '').toUpperCase().trim(),
                accode: (tr.querySelector('[data-f="accode"]').value || '').toUpperCase().trim(),
                active: tr.querySelector('[data-f="active"]').value || 'Y'
            };
        }).filter(r => r.code && r.name);
    }

    async function loadExisting() {
        try {
            const d = await api({ action: 'retrieve' });
            smBody.innerHTML = '';
            const rows = d.data || [];
            rows.forEach(r => addRow(r));
            // Calculate next code
            const codes = rows.map(r => Number(r.code || r.smcode || 0)).filter(n => !isNaN(n));
            nextCode = codes.length > 0 ? Math.max(...codes) + 1 : 1;
            if (!smBody.children.length) {
                addRow();
                status('No salesman records found. You can add a new row now.', true);
                return;
            }
            status('Loaded ' + rows.length + ' records', true);
        } catch (e) { status(e.message, false); addRow(); }
    }

    async function loadAccountList(search) {
        try {
            const d = await api({ action: 'account_list', search: search || '' });
            accountList = d.data || [];
            const body = document.getElementById('acRows');
            body.innerHTML = '';
            accountList.forEach(r => {
                const tr = document.createElement('tr');
                tr.dataset.code = r.accode || r.code || '';
                tr.dataset.name = r.acname || r.name || r.desc || '';
                tr.innerHTML = `<td>${escapeHtml(r.accode || r.code || '')}</td><td>${escapeHtml(r.acname || r.name || r.desc || '')}</td>`;
                body.appendChild(tr);
            });
        } catch (e) { status(e.message, false); }
    }

    function resolveAccountName(codeInput) {
        const code = (codeInput.value || '').toUpperCase().trim();
        codeInput.value = code;
        const tr = codeInput.closest('tr');
        const nameInput = tr.querySelector('[data-f="acname"]');
        const match = accountList.find(a => (a.accode || a.code || '').toUpperCase() === code);
        nameInput.value = match ? (match.acname || match.name || match.desc || '') : '';
    }

    async function doSave() {
        const entries = getEntries();
        if (!entries.length) { status('No valid entries to save', false); return; }

        // Check for duplicate codes
        const codes = entries.map(e => e.code);
        const unique = new Set(codes);
        if (unique.size !== codes.length) {
            status('Duplicate codes found. Please fix before saving.', false);
            return;
        }

        if (!confirm('This will replace all existing salesman records. Continue?')) return;

        try {
            const d = await api({ action: 'save', entries: entries });
            status(d.message || 'Saved successfully', true);
            await loadExisting();
        } catch (e) { status(e.message, false); }
    }

    // Account code change - resolve name
    smBody.addEventListener('change', (e) => {
        if (e.target.matches('[data-f="accode"]')) {
            resolveAccountName(e.target);
        }
    });

    // Uppercase on input
    smBody.addEventListener('input', (e) => {
        if (e.target.matches('[data-f="accode"]') || e.target.matches('[data-f="name"]')) {
            e.target.value = e.target.value.toUpperCase();
        }
    });

    // F1 on accode field opens modal
    smBody.addEventListener('keydown', (e) => {
        if (e.key === 'F1' && e.target.matches('[data-f="accode"]')) {
            e.preventDefault();
            activeCodeInput = e.target;
            loadAccountList('').then(() => {
                document.getElementById('acModal').classList.add('show');
                document.getElementById('acSearch').value = '';
                document.getElementById('acSearch').focus();
            });
        }
        // Enter moves to next field/row
        if (e.key === 'Enter' && e.target.matches('input')) {
            e.preventDefault();
            const td = e.target.closest('td');
            const nextTd = td.nextElementSibling;
            if (nextTd) {
                const nextInput = nextTd.querySelector('input:not([readonly]), select');
                if (nextInput) { nextInput.focus(); return; }
            }
            const tr = e.target.closest('tr');
            const nextTr = tr.nextElementSibling;
            if (nextTr) {
                const firstInput = nextTr.querySelector('input');
                if (firstInput) { firstInput.focus(); return; }
            }
            addRow();
        }
    });

    // Row delete with usage check
    smBody.addEventListener('click', async (e) => {
        const b = e.target.closest('.row-del');
        if (!b) return;
        const tr = b.closest('tr');
        const code = (tr.querySelector('[data-f="code"]').value || '').trim();

        if (code) {
            try {
                const d = await api({ action: 'check_usage', code: code });
                if (d.in_use) {
                    status('Cannot delete: Salesman ' + code + ' is in use in transactions.', false);
                    return;
                }
            } catch (e) { /* proceed if check fails */ }
        }
        tr.remove();
        updateCount();
    });

    // Modal search
    document.getElementById('acSearch').addEventListener('input', () => {
        loadAccountList(document.getElementById('acSearch').value);
    });

    document.getElementById('acClose').addEventListener('click', () => {
        document.getElementById('acModal').classList.remove('show');
    });

    document.getElementById('acRows').addEventListener('click', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        document.querySelectorAll('#acRows tr').forEach(r => r.classList.remove('active'));
        tr.classList.add('active');
    });

    document.getElementById('acRows').addEventListener('dblclick', (e) => {
        const tr = e.target.closest('tr');
        if (!tr || !activeCodeInput) return;
        activeCodeInput.value = tr.dataset.code || '';
        const row = activeCodeInput.closest('tr');
        row.querySelector('[data-f="acname"]').value = tr.dataset.name || '';
        document.getElementById('acModal').classList.remove('show');
        const activeSelect = row.querySelector('[data-f="active"]');
        if (activeSelect) activeSelect.focus();
    });

    // Toolbar
    document.getElementById('btnAdd').addEventListener('click', () => addRow());
    document.getElementById('btnDeleteRow').addEventListener('click', () => {
        const focused = document.activeElement?.closest('tr');
        if (focused && smBody.contains(focused)) {
            focused.remove();
            updateCount();
        } else {
            status('Focus a row to delete', false);
        }
    });
    document.getElementById('btnSave').addEventListener('click', () => doSave());

    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else { window.close(); }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') {
            e.preventDefault();
            doSave();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            const modal = document.getElementById('acModal');
            if (modal.classList.contains('show')) {
                modal.classList.remove('show');
            } else {
                document.getElementById('btnExit').click();
            }
        }
    });

    // Init: load account list for resolving, then load existing records
    loadAccountList('').then(() => loadExisting());
})();
</script>
</body>
</html>
