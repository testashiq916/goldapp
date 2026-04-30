<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Party Based MC Table</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1280px; margin: 18px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .party-row { display: grid; grid-template-columns: 80px 220px 1fr auto; gap: 8px; align-items: center; margin-bottom: 12px; }
        label { font-size: 12px; font-weight: 700; color: #375b84; }
        input, select { height: 32px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; }
        #partyName { background: #f8f9fa; font-weight: 700; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        button.warn { border-color: #a23b3b; background: #fdeaea; color: #8e1f1f; }
        .toolbar { margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap; }
        .status { margin: 10px 0; padding: 8px 10px; border-radius: 7px; font-size: 12px; display: none; }
        .status.show { display: block; }
        .status.ok { border: 1px solid #9fd5af; background: #e6f6ec; color: #12522d; }
        .status.err { border: 1px solid #f2b1b1; background: #fdeaea; color: #8e1f1f; }
        .grid { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 64vh; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 6px; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2d4f74; text-align: left; z-index: 2; }
        td input, td select { width: 100%; box-sizing: border-box; }
        .right { text-align: right; }
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,.35); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .modal.show { display: flex; }
        .modal-card { width: 760px; background: #fff; border-radius: 10px; border: 1px solid #d7dfeb; padding: 12px; }
        .party-table { max-height: 360px; overflow: auto; border: 1px solid #d8e2ef; border-radius: 8px; }
        .party-table tr.active { background: #e9f3ff; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Party Based MC Table</h1>
    <div id="status" class="status"></div>

    <div class="party-row">
        <label for="partyCode">Party</label>
        <input id="partyCode" maxlength="10" value="{{ $pcode }}">
        <input id="partyName" readonly>
        <button id="btnPartyHelp" type="button">Help (F1)</button>
    </div>

    <div class="grid">
        <table id="mcTable">
            <thead>
            <tr>
                <th style="width:220px;">Item</th>
                <th style="width:170px;">Model</th>
                <th style="width:90px;">Wstg%</th>
                <th style="width:90px;">MC/gm</th>
                <th style="width:90px;">MC%</th>
                <th style="width:90px;">MC/Qty</th>
                <th style="width:90px;">Touch%</th>
                <th style="width:130px;">Formula</th>
                <th style="width:60px;"> </th>
            </tr>
            </thead>
            <tbody id="mcBody"></tbody>
        </table>
    </div>

    <div class="toolbar">
        <button id="btnAdd" type="button">Add</button>
        <button id="btnDelete" type="button" class="warn">Delete</button>
        <button id="btnSave" type="button" class="primary">Save (F9)</button>
        <button id="btnExit" type="button">Exit</button>
    </div>
</div>

<div id="partyModal" class="modal">
    <div class="modal-card">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
            <strong style="flex:1;">Select Party</strong>
            <input id="partySearch" placeholder="Search..." style="width:220px;">
            <button id="partyClose" type="button">Close</button>
        </div>
        <div class="party-table">
            <table>
                <thead><tr><th style="width:140px;">Code</th><th>Name</th></tr></thead>
                <tbody id="partyRows"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const partyCodeEl = document.getElementById('partyCode');
    const partyNameEl = document.getElementById('partyName');
    const mcBody = document.getElementById('mcBody');
    const statusEl = document.getElementById('status');
    const partyModal = document.getElementById('partyModal');
    const partyRows = document.getElementById('partyRows');
    const partySearch = document.getElementById('partySearch');

    let itemsList = [];
    let modelsList = [];

    function status(msg, ok) {
        statusEl.textContent = msg;
        statusEl.className = 'status show ' + (ok ? 'ok' : 'err');
    }

    async function requestJson(url, method = 'GET', payload = null) {
        const res = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload ? JSON.stringify(payload) : null
        });
        const raw = await res.text();
        let data = null;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            throw new Error('Invalid server response. Please login again and retry.');
        }
        if (!res.ok) throw new Error(data.message || 'Request failed');
        return data;
    }

    function addRow(data = null) {
        const tr = document.createElement('tr');

        const itemOptions = ['<option value=""></option>'].concat(itemsList.map(item =>
            `<option value="${escapeHtml(item.code)}" ${data && data.icode === item.code ? 'selected' : ''}>${escapeHtml(item.name)}</option>`
        )).join('');

        const modelOptions = ['<option value=""></option>'].concat(modelsList.map(model =>
            `<option value="${escapeHtml(model.name)}" ${data && data.model === model.name ? 'selected' : ''}>${escapeHtml(model.name)}</option>`
        )).join('');

        tr.innerHTML = `
            <td><select class="mc-input" data-f="icode">${itemOptions}</select></td>
            <td><select class="mc-input" data-f="model">${modelOptions}</select></td>
            <td><input class="mc-input right" data-f="wastage" type="number" step="0.01" value="${data ? Number(data.wastage || 0) : ''}"></td>
            <td><input class="mc-input right" data-f="mc" type="number" step="0.01" value="${data ? Number(data.mc || 0) : ''}"></td>
            <td><input class="mc-input right" data-f="mcperc" type="number" step="0.01" value="${data ? Number(data.mcperc || 0) : ''}"></td>
            <td><input class="mc-input right" data-f="mcperqty" type="number" step="0.01" value="${data ? Number(data.mcperqty || 0) : ''}"></td>
            <td><input class="mc-input right" data-f="touch" type="number" step="0.01" value="${data ? Number(data.touch || 0) : ''}"></td>
            <td><input class="mc-input" data-f="formula" type="text" maxlength="15" value="${data && data.formula ? escapeHtml(data.formula) : ''}"></td>
            <td><button type="button" class="warn row-del">X</button></td>
        `;
        mcBody.appendChild(tr);
    }

    function getEntries() {
        return Array.from(mcBody.querySelectorAll('tr')).map((tr) => {
            const get = (key) => tr.querySelector(`[data-f="${key}"]`)?.value ?? '';
            return {
                icode: (get('icode') || '').toUpperCase().trim(),
                model: (get('model') || '').toUpperCase().trim(),
                wastage: Number(get('wastage') || 0),
                mc: Number(get('mc') || 0),
                mcperc: Number(get('mcperc') || 0),
                mcperqty: Number(get('mcperqty') || 0),
                touch: Number(get('touch') || 0),
                formula: get('formula')
            };
        });
    }

    async function loadParty(code) {
        const data = await requestJson(@json(url('/partymctable/get-party')), 'POST', { code });
        partyCodeEl.value = data.data.code || code;
        partyNameEl.value = data.data.name || '';
        const rows = await requestJson(@json(url('/partymctable/get-mctable')), 'POST', { pcode: partyCodeEl.value });
        mcBody.innerHTML = '';
        (rows.data || []).forEach((r) => addRow(r));
        if (!mcBody.children.length) addRow();
    }

    async function loadParties(search = '') {
        const data = await requestJson(@json(url('/partymctable/parties')) + '?search=' + encodeURIComponent(search));
        partyRows.innerHTML = '';
        (data.data || []).forEach((p) => {
            const tr = document.createElement('tr');
            tr.dataset.code = p.code;
            tr.innerHTML = `<td>${escapeHtml(p.code)}</td><td>${escapeHtml(p.name || '')}</td>`;
            partyRows.appendChild(tr);
        });
    }

    function escapeHtml(v) {
        return String(v)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    document.getElementById('btnPartyHelp').addEventListener('click', async () => {
        try {
            await loadParties();
            partyModal.classList.add('show');
            partySearch.focus();
        } catch (e) { status(e.message, false); }
    });

    document.getElementById('partyClose').addEventListener('click', () => partyModal.classList.remove('show'));
    partySearch.addEventListener('input', async () => { await loadParties(partySearch.value); });

    partyRows.addEventListener('click', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        partyRows.querySelectorAll('tr').forEach((x) => x.classList.remove('active'));
        tr.classList.add('active');
    });
    partyRows.addEventListener('dblclick', async (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        partyModal.classList.remove('show');
        try { await loadParty(tr.dataset.code || ''); } catch (err) { status(err.message, false); }
    });

    partyCodeEl.addEventListener('input', () => { partyCodeEl.value = partyCodeEl.value.toUpperCase(); });
    partyCodeEl.addEventListener('keydown', async (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (!partyCodeEl.value.trim()) return;
            try { await loadParty(partyCodeEl.value.trim()); } catch (err) { status(err.message, false); partyNameEl.value=''; }
        }
    });
    partyCodeEl.addEventListener('change', async () => {
        if (!partyCodeEl.value.trim()) { partyNameEl.value=''; mcBody.innerHTML=''; return; }
        try { await loadParty(partyCodeEl.value.trim()); } catch (err) { status(err.message, false); }
    });

    document.getElementById('btnAdd').addEventListener('click', () => addRow());
    document.getElementById('btnDelete').addEventListener('click', () => {
        const focused = document.activeElement?.closest('tr');
        if (!focused) { status('Please focus a row to delete.', false); return; }
        focused.remove();
    });
    mcBody.addEventListener('click', (e) => {
        const b = e.target.closest('.row-del');
        if (b) b.closest('tr').remove();
    });

    document.getElementById('btnSave').addEventListener('click', async () => {
        const pcode = partyCodeEl.value.trim().toUpperCase();
        if (!pcode) { status('Please select a party first.', false); return; }
        if (!confirm('Do you want to update table?')) return;
        try {
            const data = await requestJson(@json(url('/partymctable/save')), 'POST', {
                pcode,
                entries: getEntries()
            });
            status(data.message || 'Updated successfully', true);
        } catch (e) { status(e.message, false); }
    });

    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else {
            window.close();
        }
    });

    document.addEventListener('keydown', async (e) => {
        if (e.key === 'F1' && document.activeElement === partyCodeEl) {
            e.preventDefault();
            try { await loadParties(); partyModal.classList.add('show'); } catch (err) { status(err.message, false); }
        } else if (e.key === 'F9') {
            e.preventDefault();
            document.getElementById('btnSave').click();
        }
    });

    Promise.all([
        requestJson(@json(url('/partymctable/items'))).then((d) => { itemsList = d.data || []; }),
        requestJson(@json(url('/partymctable/models')) + '?type=M').then((d) => { modelsList = d.data || []; })
    ]).then(() => {
        if (partyCodeEl.value.trim()) {
            loadParty(partyCodeEl.value.trim()).catch((e) => status(e.message, false));
        } else {
            addRow();
        }
    }).catch((e) => status(e.message, false));
})();
</script>
</body>
</html>
