<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Stock Type Details</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 980px; margin: 18px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .grid { display: grid; grid-template-columns: 140px 1fr; gap: 10px 12px; align-items: center; max-width: 720px; }
        .grid label { font-size: 12px; font-weight: 700; color: #375b84; }
        .grid input[type=text] { height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 10px; font-size: 13px; }
        .checks { margin-top: 10px; display: flex; gap: 20px; font-size: 12px; color: #35577f; }
        .toolbar { margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        .status { margin: 10px 0; padding: 8px 10px; border-radius: 7px; font-size: 12px; display: none; }
        .status.show { display: block; }
        .status.ok { border: 1px solid #9fd5af; background: #e6f6ec; color: #12522d; }
        .status.err { border: 1px solid #f2b1b1; background: #fdeaea; color: #8e1f1f; }
        .list { margin-top: 16px; border: 1px solid #d8e2ef; border-radius: 8px; max-height: 42vh; overflow: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 6px; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2d4f74; text-align: left; }
        tbody tr { cursor: pointer; }
        tbody tr.active { background: #f0f7ff; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Stock Type Details</h1>
    @if(!empty($tableMissing))
        <div class="status show err">`stktype` table not found. Please run migration/setup for stock type first.</div>
    @endif
    <div id="status" class="status"></div>

    <input type="hidden" id="operation" value="">
    <input type="hidden" id="originalCode" value="">

    <div class="grid">
        <label for="code">Code</label>
        <input id="code" type="text" maxlength="10" autocomplete="off">

        <label for="name">Name</label>
        <input id="name" type="text" maxlength="30" autocomplete="off">
    </div>

    <div class="checks">
        @if($giaccount === 2)
            <label><input id="compare" type="checkbox"> Compare with Fr</label>
        @else
            <label style="display:none;"><input id="compare" type="checkbox"></label>
        @endif
        <label><input id="def" type="checkbox"> Default Type</label>
        <label><input id="deleteAnyway" type="checkbox"> Delete anyway</label>
    </div>

    <div class="toolbar">
        <button id="btnAdd" type="button">Add</button>
        <button id="btnEdit" type="button">Edit</button>
        <button id="btnDelete" type="button">Delete</button>
        <button id="btnSave" class="primary" type="button">Save</button>
        <button id="btnCancel" type="button">Cancel</button>
        <button id="btnExit" type="button">Exit</button>
    </div>

    <div class="list">
        <table>
            <thead><tr><th style="width:120px;">Code</th><th>Name</th><th style="width:90px;">Default</th></tr></thead>
            <tbody id="typeRows"></tbody>
        </table>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const statusEl = document.getElementById('status');
    const rowsEl = document.getElementById('typeRows');
    const codeEl = document.getElementById('code');
    const nameEl = document.getElementById('name');
    const defEl = document.getElementById('def');
    const compareEl = document.getElementById('compare');
    const deleteAnyEl = document.getElementById('deleteAnyway');
    const opEl = document.getElementById('operation');
    const originalEl = document.getElementById('originalCode');

    function status(msg, ok) {
        statusEl.textContent = msg;
        statusEl.className = 'status show ' + (ok ? 'ok' : 'err');
    }

    function clearForm() {
        codeEl.value = '';
        nameEl.value = '';
        defEl.checked = false;
        compareEl.checked = false;
        deleteAnyEl.checked = false;
        originalEl.value = '';
        opEl.value = '';
        codeEl.disabled = false;
    }

    async function requestJson(url, method, payload) {
        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload ? JSON.stringify(payload) : null
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Request failed');
        return data;
    }

    async function loadList(activeCode = '') {
        const data = await requestJson(@json(url('/stocktype/list')), 'GET');
        rowsEl.innerHTML = '';
        (data.data || []).forEach((row) => {
            const tr = document.createElement('tr');
            tr.dataset.code = row.code;
            tr.innerHTML = `<td>${row.code}</td><td>${row.name}</td><td>${row.def ? 'Yes' : ''}</td>`;
            if (activeCode && activeCode === row.code) tr.classList.add('active');
            rowsEl.appendChild(tr);
        });
    }

    async function loadByCode(code) {
        const data = await requestJson(@json(url('/stocktype/get-by-code')), 'POST', { code });
        const row = data.data;
        codeEl.value = row.code || '';
        nameEl.value = row.name || '';
        defEl.checked = !!row.def;
        compareEl.checked = !!row.compare;
        originalEl.value = row.code || '';
    }

    document.getElementById('btnAdd').addEventListener('click', () => {
        clearForm();
        opEl.value = 'A';
        codeEl.focus();
    });

    document.getElementById('btnEdit').addEventListener('click', async () => {
        const code = prompt('Enter code to edit', codeEl.value || '');
        if (!code) return;
        try {
            await loadByCode(code);
            opEl.value = 'E';
            codeEl.disabled = true;
            nameEl.focus();
        } catch (e) { status(e.message, false); }
    });

    document.getElementById('btnDelete').addEventListener('click', async () => {
        const code = (originalEl.value || codeEl.value || '').trim().toUpperCase();
        if (!code) { status('Code is required.', false); return; }
        try {
            const usage = await requestJson(@json(url('/stocktype/check-usage')) + '/' + encodeURIComponent(code), 'GET');
            const u = usage.usage || {};
            let forceDelete = deleteAnyEl.checked;
            if ((u.total || 0) > 0 && !forceDelete) {
                if ((u.total || 0) === (u.itemsonly || 0)) {
                    forceDelete = confirm('Items exist with this stock type. Delete anyway?');
                    if (!forceDelete) return;
                } else {
                    status("Items exist with this entry. You can't delete this code.", false);
                    return;
                }
            }
            if (!confirm('Delete code ' + code + '?')) return;
            await requestJson(@json(url('/stocktype')) + '/' + encodeURIComponent(code), 'DELETE', { force_delete: forceDelete });
            status('Stock type deleted successfully', true);
            clearForm();
            await loadList();
        } catch (e) { status(e.message, false); }
    });

    document.getElementById('btnSave').addEventListener('click', async () => {
        const code = codeEl.value.trim().toUpperCase();
        const name = nameEl.value.trim();
        if (!code) { status("Code is empty. You can't save.", false); return; }
        if (!name) { status("Name is empty. You can't save.", false); return; }
        try {
            if (opEl.value === 'E') {
                const original = (originalEl.value || code).trim().toUpperCase();
                await requestJson(@json(url('/stocktype')) + '/' + encodeURIComponent(original), 'PUT', {
                    name,
                    def: defEl.checked,
                    compare: compareEl.checked
                });
                status('Stock type updated successfully', true);
                await loadList(code);
            } else {
                await requestJson(@json(url('/stocktype')), 'POST', {
                    code,
                    name,
                    def: defEl.checked,
                    compare: compareEl.checked
                });
                status('Stock type created successfully', true);
                await loadList(code);
            }
            opEl.value = '';
            originalEl.value = code;
            codeEl.disabled = true;
        } catch (e) { status(e.message, false); }
    });

    document.getElementById('btnCancel').addEventListener('click', () => clearForm());

    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else {
            window.close();
        }
    });

    rowsEl.addEventListener('click', async (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        rowsEl.querySelectorAll('tr').forEach((x) => x.classList.remove('active'));
        tr.classList.add('active');
        try {
            await loadByCode(tr.dataset.code || '');
            opEl.value = '';
            codeEl.disabled = true;
        } catch (err) { status(err.message, false); }
    });

    codeEl.addEventListener('input', () => { codeEl.value = codeEl.value.toUpperCase(); });
    codeEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            nameEl.focus();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') {
            e.preventDefault();
            document.getElementById('btnSave').click();
        }
    });

    loadList().catch((e) => status(e.message, false));
})();
</script>
</body>
</html>
