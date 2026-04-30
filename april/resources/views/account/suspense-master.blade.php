<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Suspense A/c Master</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", Tahoma, Arial, sans-serif; background: #eef3fa; color: #1e3046; }
        .wrap { max-width: 760px; margin: 12px auto; background: #fff; border: 1px solid #d2deef; border-radius: 10px; overflow: hidden; }
        .head { padding: 12px 14px; background: linear-gradient(100deg, #155f95, #1d7fbe); color: #fff; font-size: 20px; font-weight: 800; }
        .body { padding: 14px; }
        .sub { min-height: 20px; font-size: 12px; color: #516b89; font-weight: 700; margin-bottom: 6px; }
        .row { display: grid; grid-template-columns: 90px 1fr; gap: 8px; align-items: center; margin-bottom: 8px; }
        .row label { text-align: right; font-weight: 700; color: #5f7390; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
        input { height: 32px; border: 1px solid #bfd0e6; border-radius: 7px; padding: 0 8px; font-size: 13px; }
        input:focus { outline: none; border-color: #4f96da; box-shadow: 0 0 0 3px rgba(79,150,218,.14); }
        .readonly { background: #edf3fb; }
        .toolbar { margin-top: 12px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        button { height: 33px; min-width: 92px; border: 1px solid #bdd0e8; border-radius: 8px; background: #fff; font-weight: 700; cursor: pointer; }
        button:hover { background: #f6faff; border-color: #97b8dc; }
        #btnSave { background: linear-gradient(100deg, #1f9957, #178447); color: #fff; border-color: #157a41; }
        #btnExit { background: linear-gradient(100deg, #b24444, #9d2f2f); color: #fff; border-color: #8f2d2d; }
        .list { margin-top: 12px; border: 1px solid #d9e4f2; border-radius: 8px; max-height: 300px; overflow: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 7px 8px; border-bottom: 1px solid #e8eef8; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2a4f76; }
        tr.active td { background: #e9f2ff; }
        .status { margin-top: 10px; min-height: 16px; text-align: center; font-weight: 700; }
        .ok { color: #197942; }
        .err { color: #a42d2d; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="head">Suspense A/c Master</div>
    <div class="body">
        <div class="sub" id="headText"></div>
        <div class="row">
            <label>Code</label>
            <input id="code" class="readonly" readonly>
        </div>
        <div class="row">
            <label>Name</label>
            <input id="name" maxlength="30">
        </div>
        <div class="toolbar">
            <button id="btnAdd" type="button">Add</button>
            <button id="btnEdit" type="button">Edit</button>
            <button id="btnDelete" type="button">Delete</button>
            <button id="btnSave" type="button">Save</button>
            <button id="btnCancel" type="button">Cancel</button>
            <button id="btnExit" type="button">Exit</button>
        </div>
        <div class="list">
            <table>
                <thead><tr><th style="width:120px">Code</th><th>Name</th></tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
        <div id="status" class="status"></div>
    </div>
</div>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const BASE = @json(url('/api/accounts/suspense-master'));
    const $ = id => document.getElementById(id);
    let mode = 'A';
    let selected = '';

    function setStatus(msg, ok) { const el = $('status'); el.textContent = msg || ''; el.className = 'status ' + (ok ? 'ok' : 'err'); }
    function setHead() { $('headText').textContent = mode === 'A' ? 'Add New Entry' : (mode === 'E' ? 'Edit an Entry' : (mode === 'D' ? 'Delete an Entry' : '')); }
    function enable() {
        const editState = mode === 'A' || mode === 'E';
        $('name').readOnly = !editState;
        $('btnSave').disabled = !editState;
        $('btnCancel').disabled = !editState;
        ['btnAdd', 'btnEdit', 'btnDelete', 'btnExit'].forEach(id => $(id).disabled = editState);
    }

    async function api(payload) {
        const res = await fetch(BASE, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
        });
        const d = await res.json();
        if (!res.ok || d.success === false) throw new Error(d.error || 'Request failed');
        return d;
    }

    async function loadList() {
        const d = await api({ action: 'list' });
        $('rows').innerHTML = (d.data || []).map(r => `<tr data-code="${r.code}"><td>${r.code}</td><td>${(r.name || '').replace(/</g,'&lt;')}</td></tr>`).join('');
        Array.from($('rows').querySelectorAll('tr')).forEach(tr => {
            tr.addEventListener('click', async () => {
                selected = tr.dataset.code;
                Array.from($('rows').querySelectorAll('tr')).forEach(x => x.classList.remove('active'));
                tr.classList.add('active');
                const g = await api({ action: 'get', code: selected });
                $('code').value = g.data.code || '';
                $('name').value = g.data.name || '';
            });
        });
    }

    async function initCode() {
        const d = await api({ action: 'init' });
        $('code').value = d.nextCode || '';
    }

    async function onAdd() {
        mode = 'A'; selected = '';
        $('name').value = '';
        await initCode();
        setHead(); enable(); $('name').focus();
    }

    function onEdit() {
        if (!$('code').value) { setStatus('Select code to edit', false); return; }
        mode = 'E'; setHead(); enable(); $('name').focus();
    }

    async function onDelete() {
        const code = $('code').value.trim();
        if (!code) { setStatus('Select code to delete', false); return; }
        if (!confirm('Delete this code?')) return;
        try {
            await api({ action: 'delete', code });
            setStatus('Deleted', true);
            await onAdd();
            await loadList();
            sendBack('');
        } catch (e) { setStatus(e.message, false); }
    }

    async function onSave() {
        const code = $('code').value.trim().toUpperCase();
        const name = $('name').value.trim();
        if (!code) { setStatus('Code is empty', false); return; }
        if (!name) { setStatus('Name is empty', false); return; }
        try {
            const d = await api({ action: 'save', mode, code, name });
            setStatus('Saved', true);
            sendBack(code);
            mode = 'C';
            setHead(); enable();
            await initCode();
            $('name').value = '';
            await loadList();
        } catch (e) { setStatus(e.message, false); }
    }

    function onCancel() {
        mode = 'C'; setHead(); enable();
        setStatus('', true);
    }

    function onExit() { window.close(); }

    function sendBack(code) {
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage({ type: 'goldapp:suspac-saved', code }, '*');
        }
    }

    $('btnAdd').addEventListener('click', onAdd);
    $('btnEdit').addEventListener('click', onEdit);
    $('btnDelete').addEventListener('click', onDelete);
    $('btnSave').addEventListener('click', onSave);
    $('btnCancel').addEventListener('click', onCancel);
    $('btnExit').addEventListener('click', onExit);
    document.addEventListener('keydown', e => {
        if (e.key === 'F9') { e.preventDefault(); onSave(); }
        if (e.key === 'Enter' && document.activeElement.id === 'name') { e.preventDefault(); onSave(); }
    });

    (async () => {
        try {
            await loadList();
            await onAdd();
        } catch (e) { setStatus(e.message, false); }
    })();
})();
</script>
</body>
</html>

