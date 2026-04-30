<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Set Making Charge</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1240px; margin: 18px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .top-row { display: grid; grid-template-columns: 70px 210px 90px 210px auto; gap: 8px; align-items: center; margin-bottom: 12px; }
        label { font-size: 12px; font-weight: 700; color: #375b84; }
        input, select { height: 32px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        button.warn { border-color: #a23b3b; background: #fdeaea; color: #8e1f1f; }
        .toolbar { margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
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
        .item-table { max-height: 360px; overflow: auto; border: 1px solid #d8e2ef; border-radius: 8px; }
        .item-table tr.active { background: #e9f3ff; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Set Making Charge</h1>
    <div id="status" class="status"></div>

    <div class="top-row">
        <label for="itemCode">Item</label>
        <div style="display:flex;gap:8px;">
            <input id="itemCode" maxlength="10" style="flex:1;">
            <button id="btnHelp" type="button">Help</button>
        </div>
        <label for="itemType">Type</label>
        <select id="itemType"><option value=""></option></select>
        <button id="btnShow" type="button">Show</button>
    </div>

    <div class="grid">
        <table>
            <thead>
            <tr>
                <th style="width:140px;">From Wgt</th>
                <th style="width:140px;">To Wgt</th>
                <th style="width:130px;">MC Rs.</th>
                <th style="width:130px;">MC Per Gm</th>
                <th style="width:130px;">MC Per Qty</th>
                <th style="width:130px;">VA %</th>
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
        <button id="btnDelAll" type="button" class="warn" style="display:none;">Del All of this item</button>
    </div>
</div>

<div id="itemModal" class="modal">
    <div class="modal-card">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
            <strong style="flex:1;">Select Item</strong>
            <input id="itemSearch" placeholder="Search..." style="width:220px;">
            <button id="itemClose" type="button">Close</button>
        </div>
        <div class="item-table">
            <table>
                <thead><tr><th style="width:140px;">Code</th><th>Name</th></tr></thead>
                <tbody id="itemRows"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const itemCodeEl = document.getElementById('itemCode');
    const itemTypeEl = document.getElementById('itemType');
    const mcBody = document.getElementById('mcBody');
    const statusEl = document.getElementById('status');
    const itemModal = document.getElementById('itemModal');
    const itemRows = document.getElementById('itemRows');
    const itemSearch = document.getElementById('itemSearch');

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

    function escapeHtml(v) {
        return String(v)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function addRow(data = null) {
        if (!data) {
            const last = mcBody.querySelector('tr:last-child');
            if (last) {
                const w1 = Number(last.querySelector('[data-f="weight1"]').value || 0);
                const w2 = Number(last.querySelector('[data-f="weight2"]').value || 0);
                if (w1 === 0 && w2 === 0) {
                    last.querySelector('[data-f="weight1"]').focus();
                    return;
                }
            }
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input class="mc-input right" data-f="weight1" type="number" step="0.001" value="${data ? Number(data.weight1 || 0) : ''}"></td>
            <td><input class="mc-input right" data-f="weight2" type="number" step="0.001" value="${data ? Number(data.weight2 || 0) : ''}"></td>
            <td><input class="mc-input right" data-f="mc" type="number" step="0.01" value="${data ? Number(data.mc || 0) : ''}"></td>
            <td><input class="mc-input right" data-f="mcpergm" type="number" step="0.01" value="${data ? Number(data.mcpergm || 0) : ''}"></td>
            <td><input class="mc-input right" data-f="mcperqty" type="number" step="0.01" value="${data ? Number(data.mcperqty || 0) : ''}"></td>
            <td><input class="mc-input right" data-f="vaperc" type="number" step="0.01" value="${data ? Number(data.vaperc || 0) : ''}"></td>
            <td><button type="button" class="warn row-del">X</button></td>
        `;
        mcBody.appendChild(tr);
        if (!data) tr.querySelector('[data-f="weight1"]').focus();
    }

    function getEntries() {
        return Array.from(mcBody.querySelectorAll('tr')).map((tr) => {
            const get = (k) => tr.querySelector(`[data-f="${k}"]`)?.value ?? '';
            return {
                weight1: Number(get('weight1') || 0),
                weight2: Number(get('weight2') || 0),
                mc: Number(get('mc') || 0),
                mcpergm: Number(get('mcpergm') || 0),
                mcperqty: Number(get('mcperqty') || 0),
                vaperc: Number(get('vaperc') || 0)
            };
        });
    }

    async function validateItem(code) {
        const d = await requestJson(@json(url('/mctable/get-item')), 'POST', { code });
        await loadItemTypes(d.data.code || code);
    }

    async function loadItemTypes(code) {
        const d = await requestJson(@json(url('/mctable/item-types')), 'POST', { code });
        itemTypeEl.innerHTML = '<option value=""></option>';
        (d.data || []).forEach((r) => {
            const o = document.createElement('option');
            o.value = r.qcode || '';
            o.textContent = r.qdesc || r.qcode || '';
            itemTypeEl.appendChild(o);
        });
    }

    async function loadMCTable() {
        const code = itemCodeEl.value.trim().toUpperCase();
        if (!code) {
            status('Please enter item code first', false);
            return;
        }
        const iqtype = itemTypeEl.value || '';
        const d = await requestJson(@json(url('/mctable/get-mctable')), 'POST', { code, iqtype });
        mcBody.innerHTML = '';
        (d.data || []).forEach((r) => addRow(r));
        if (!mcBody.children.length) addRow();
    }

    async function loadItems(search = '') {
        const d = await requestJson(@json(url('/mctable/items-list')) + '?search=' + encodeURIComponent(search));
        itemRows.innerHTML = '';
        (d.data || []).forEach((item) => {
            const tr = document.createElement('tr');
            tr.dataset.code = item.code;
            tr.innerHTML = `<td>${escapeHtml(item.code)}</td><td>${escapeHtml(item.name || '')}</td>`;
            itemRows.appendChild(tr);
        });
    }

    itemCodeEl.addEventListener('input', () => { itemCodeEl.value = itemCodeEl.value.toUpperCase(); });
    itemCodeEl.addEventListener('change', async () => {
        if (!itemCodeEl.value.trim()) return;
        try {
            await validateItem(itemCodeEl.value.trim().toUpperCase());
        } catch (e) {
            status(e.message, false);
            itemCodeEl.value = '';
            itemTypeEl.innerHTML = '<option value=""></option>';
            mcBody.innerHTML = '';
        }
    });

    document.getElementById('btnShow').addEventListener('click', () => {
        loadMCTable().catch((e) => status(e.message, false));
    });

    document.getElementById('btnHelp').addEventListener('click', async () => {
        try {
            await loadItems();
            itemModal.classList.add('show');
            itemSearch.focus();
        } catch (e) { status(e.message, false); }
    });

    document.getElementById('itemClose').addEventListener('click', () => itemModal.classList.remove('show'));
    itemSearch.addEventListener('input', () => {
        loadItems(itemSearch.value).catch((e) => status(e.message, false));
    });

    itemRows.addEventListener('click', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        itemRows.querySelectorAll('tr').forEach((x) => x.classList.remove('active'));
        tr.classList.add('active');
    });

    itemRows.addEventListener('dblclick', async (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        itemCodeEl.value = tr.dataset.code || '';
        itemModal.classList.remove('show');
        try {
            await validateItem(itemCodeEl.value);
            await loadMCTable();
        } catch (err) { status(err.message, false); }
    });

    mcBody.addEventListener('click', (e) => {
        const b = e.target.closest('.row-del');
        if (b) b.closest('tr').remove();
    });

    document.getElementById('btnAdd').addEventListener('click', () => addRow());
    document.getElementById('btnDelete').addEventListener('click', () => {
        const tr = document.activeElement ? document.activeElement.closest('tr') : null;
        if (!tr) {
            status('Please focus a row to delete.', false);
            return;
        }
        tr.remove();
    });

    document.getElementById('btnSave').addEventListener('click', async () => {
        const code = itemCodeEl.value.trim().toUpperCase();
        if (!code) {
            status('Please enter item code first', false);
            return;
        }
        if (!confirm('Do you want to update table?')) return;
        try {
            const d = await requestJson(@json(url('/mctable/save')), 'POST', {
                code,
                iqtype: itemTypeEl.value || '',
                entries: getEntries()
            });
            status(d.message || 'Updated successfully', true);
        } catch (e) { status(e.message, false); }
    });

    document.getElementById('btnExit').addEventListener('contextmenu', (e) => {
        e.preventDefault();
        const btn = document.getElementById('btnDelAll');
        btn.style.display = btn.style.display === 'none' ? 'inline-block' : 'none';
    });

    document.getElementById('btnDelAll').addEventListener('click', async () => {
        const code = itemCodeEl.value.trim().toUpperCase();
        if (!code) {
            status('Please enter item code first', false);
            return;
        }
        if (!confirm('Do you want to delete all MC Table details of this item?')) return;
        try {
            const d = await requestJson(@json(url('/mctable/delete-all')), 'POST', { code });
            status(d.message || 'Deleted successfully', true);
            mcBody.innerHTML = '';
            addRow();
        } catch (e) { status(e.message, false); }
    });

    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else {
            window.close();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') {
            e.preventDefault();
            document.getElementById('btnSave').click();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target && e.target.classList.contains('mc-input')) {
            e.preventDefault();
            const td = e.target.closest('td');
            const nextCell = td ? td.nextElementSibling : null;
            if (nextCell && nextCell.querySelector('input')) {
                nextCell.querySelector('input').focus();
                return;
            }
            const row = e.target.closest('tr');
            const nextRow = row ? row.nextElementSibling : null;
            if (nextRow && nextRow.querySelector('input')) {
                nextRow.querySelector('input').focus();
                return;
            }
            addRow();
        }
    });

    addRow();
})();
</script>
</body>
</html>

