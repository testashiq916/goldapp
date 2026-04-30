<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Position Settings</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1100px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 12px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .status { margin: 8px 0; padding: 8px 10px; border-radius: 7px; font-size: 12px; display: none; }
        .status.show { display: block; }
        .status.ok { border: 1px solid #9fd5af; background: #e6f6ec; color: #12522d; }
        .status.err { border: 1px solid #f2b1b1; background: #fdeaea; color: #8e1f1f; }
        .top-row { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; flex-wrap: wrap; }
        label { font-size: 12px; font-weight: 700; color: #375b84; }
        select { height: 32px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 6px; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        .grid { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 64vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 6px; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2d4f74; z-index: 2; }
        td input { width: 80px; height: 28px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 6px; font-size: 12px; text-align: right; box-sizing: border-box; }
        .muted { color: #627389; font-size: 11px; margin-left: 8px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Position Settings</h1>
    <div id="status" class="status"></div>

    <div class="top-row">
        <label for="posType">Type:</label>
        <select id="posType">
            <option value="accounts">Accounts Position</option>
            <option value="groups">Group Position</option>
            <option value="bsheads">BS Head Position</option>
        </select>
        <button id="btnLoad" type="button">Load</button>
        <span class="muted" id="countLabel">0 records</span>
    </div>

    <div class="grid">
        <table id="posTable">
            <thead>
                <tr>
                    <th style="width:120px;">Code</th>
                    <th>Name</th>
                    <th style="width:80px;">Type</th>
                    <th style="width:100px;">Position</th>
                </tr>
            </thead>
            <tbody id="posBody"></tbody>
        </table>
    </div>

    <div class="toolbar">
        <button id="btnSave" type="button" class="primary">Save (F9)</button>
        <button id="btnExit" type="button">Exit (Esc)</button>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const statusEl = document.getElementById('status');
    const BASE = @json(url('/api/accounts/master/positions'));

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
        if (!res.ok) throw new Error(data.message || 'Request failed');
        return data;
    }

    function escapeHtml(v) {
        return String(v).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
    }

    async function loadData() {
        const posType = document.getElementById('posType').value;
        try {
            const d = await api({ action: 'get_data', type: posType });
            const body = document.getElementById('posBody');
            body.innerHTML = '';
            const rows = d.data || [];
            rows.forEach(r => {
                const code = r.code || r.accode || r.grcode || r.hcode || '';
                const name = r.name || r.desc || r.acname || r.grname || r.hname || '';
                const actype = r.actype || '';
                const pos = r.pos !== undefined ? r.pos : 0;

                const tr = document.createElement('tr');
                tr.dataset.code = code;
                tr.innerHTML = `<td>${escapeHtml(code)}</td><td>${escapeHtml(name)}</td><td>${escapeHtml(actype)}</td><td><input type="number" step="1" data-field="pos" value="${Number(pos)}"></td>`;
                body.appendChild(tr);
            });
            document.getElementById('countLabel').textContent = rows.length + ' records';
            status('Loaded ' + rows.length + ' records', true);
        } catch (e) { status(e.message, false); }
    }

    async function doSave() {
        const posType = document.getElementById('posType').value;
        const rows = Array.from(document.querySelectorAll('#posBody tr'));
        if (!rows.length) { status('No data to save', false); return; }

        const positions = rows.map(tr => ({
            code: tr.dataset.code,
            pos: Number(tr.querySelector('[data-field="pos"]').value || 0)
        }));

        try {
            const d = await api({ action: 'update_positions', type: posType, positions: positions });
            status(d.message || 'Positions updated successfully', true);
        } catch (e) { status(e.message, false); }
    }

    document.getElementById('btnLoad').addEventListener('click', () => loadData());

    document.getElementById('btnSave').addEventListener('click', () => doSave());

    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else { window.close(); }
    });

    // Enter in position field moves to next row
    document.getElementById('posBody').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches('[data-field="pos"]')) {
            e.preventDefault();
            const tr = e.target.closest('tr');
            const next = tr.nextElementSibling;
            if (next) {
                const inp = next.querySelector('[data-field="pos"]');
                if (inp) inp.focus();
            }
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') { e.preventDefault(); doSave(); }
        else if (e.key === 'Escape') { e.preventDefault(); document.getElementById('btnExit').click(); }
    });

    // Auto-load on type change
    document.getElementById('posType').addEventListener('change', () => loadData());

    // Init
    loadData();
})();
</script>
</body>
</html>
