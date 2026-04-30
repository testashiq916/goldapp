<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Clients Route Details</title>
    <style>
        body { font-family: Arial, sans-serif; background: #c0c0c0; margin: 0; padding: 14px; }
        .warn { margin-bottom: 10px; padding: 8px; border: 1px solid #c77; background: #f9dddd; color: #822; font-size: 12px; font-weight: 700; }
        .wrap { background: #fff; border: 1px solid #999; padding: 10px; }
        h2 { margin: 0 0 10px; font-size: 16px; color: #333; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-bottom: 10px; }
        th { background: #c0c8a0; border: 1px solid #999; padding: 6px; font-size: 12px; text-align: left; }
        td { border: 1px solid #ccc; padding: 3px; }
        input { width: 100%; border: 1px solid #aaa; padding: 4px; font-size: 12px; }
        .upper { text-transform: uppercase; }
        .btnbar { display: flex; gap: 8px; }
        button { border: 1px solid #888; background: #e7e7e7; padding: 6px 16px; font-size: 12px; font-weight: 700; cursor: pointer; }
        .x { border: none; background: transparent; color: #b00; font-weight: 700; cursor: pointer; font-size: 15px; }
    </style>
</head>
<body>
<div class="wrap">
    <h2>Clients Route Details</h2>
    @if($missing)<div class="warn">`route` table not found.</div>@endif
    <table>
        <thead><tr><th style="width:170px">Code</th><th>Name</th><th style="width:48px"></th></tr></thead>
        <tbody id="tb"></tbody>
    </table>
    <div class="btnbar">
        <button id="btnAdd">Add</button>
        <button id="btnSave">Save</button>
        <button onclick="location.reload()">Cancel</button>
        <button onclick="window.close()">Exit</button>
    </div>
</div>
<script>
(() => {
    const disabled = @json($missing);
    const rows = [];
    const deleted = [];
    const tb = document.getElementById('tb');
    if (disabled) { document.getElementById('btnAdd').disabled = true; document.getElementById('btnSave').disabled = true; }
    async function post(url, payload){
        const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'X-Requested-With':'XMLHttpRequest'}, body:JSON.stringify(payload||{})});
        return await r.json();
    }
    function render(){
        tb.innerHTML = '';
        if (!rows.length) {
            tb.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#777;padding:18px;">No rows.</td></tr>';
            return;
        }
        rows.forEach((r, i) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td><input data-i="${i}" data-k="code" class="upper" value="${(r.code||'').replace(/"/g,'&quot;')}" maxlength="10"></td>
                            <td><input data-i="${i}" data-k="name" value="${(r.name||'').replace(/"/g,'&quot;')}" maxlength="40"></td>
                            <td><button class="x" data-del="${i}">&times;</button></td>`;
            tb.appendChild(tr);
        });
    }
    tb.addEventListener('input', (e) => {
        const i = Number(e.target.dataset.i); const k = e.target.dataset.k;
        if (Number.isInteger(i) && rows[i]) rows[i][k] = e.target.value;
    });
    tb.addEventListener('click', (e) => {
        const i = e.target.dataset.del; if (i === undefined) return;
        const idx = Number(i); if (!rows[idx]) return;
        const code = (rows[idx].code || '').trim().toUpperCase();
        if (code !== '' && !rows[idx]._new) deleted.push(code);
        rows.splice(idx, 1); render();
    });
    document.getElementById('btnAdd').addEventListener('click', () => {
        const last = rows[rows.length - 1];
        if (last && !(last.code || '').trim()) { render(); return; }
        rows.push({ code:'', name:'', _new:true }); render();
    });
    document.getElementById('btnSave').addEventListener('click', async () => {
        const payloadRows = rows.map(x => ({ code:(x.code||'').trim().toUpperCase(), name:(x.name||'').trim() })).filter(x => x.code !== '');
        const json = await post(@json(url('/customer/popup/routes/save')), { rows: payloadRows, deleted });
        if (!json.success) { alert(json.message || 'Save failed'); return; }
        alert(json.message || 'Saved');
        await load();
    });
    async function load(){
        deleted.length = 0;
        const json = await post(@json(url('/customer/popup/routes/retrieve')), {});
        if (!json.success) { alert(json.message || 'Load failed'); return; }
        rows.length = 0;
        (json.data || []).forEach(r => rows.push({ code:r.code || '', name:r.name || '', _new:false }));
        render();
    }
    load();
})();
</script>
</body>
</html>

