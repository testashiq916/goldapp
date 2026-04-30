<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 980px; margin: 18px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        button.warn { border-color: #a23b3b; background: #fdeaea; color: #8e1f1f; }
        .status { margin: 8px 0 12px; padding: 9px 11px; border-radius: 7px; font-size: 12px; display: none; }
        .status.show { display: block; }
        .status.ok { border: 1px solid #9fd5af; background: #e6f6ec; color: #12522d; }
        .status.err { border: 1px solid #f2b1b1; background: #fdeaea; color: #8e1f1f; }
        .table-wrap { max-height: 68vh; overflow: auto; border: 1px solid #d8e2ef; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 6px; font-size: 12px; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2d4f74; z-index: 2; text-align: left; }
        td input { width: 100%; box-sizing: border-box; height: 30px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; text-transform: uppercase; }
        .num { width: 48px; text-align: right; }
        .action { width: 90px; text-align: center; }
        .hint { margin-top: 8px; font-size: 11px; color: #63748a; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>{{ $title }}</h1>
    <input type="hidden" id="modelType" value="{{ $type }}">

    <div class="toolbar">
        <button type="button" id="btnAdd">Add</button>
        <button type="button" id="btnDelete">Delete Current</button>
        <button type="button" id="btnCancel">Cancel</button>
        <button type="button" class="primary" id="btnSave">Save</button>
        <button type="button" id="btnExit">Exit</button>
    </div>

    <div id="status" class="status"></div>

    <div class="table-wrap">
        <table id="modelsTable">
            <thead>
            <tr>
                <th class="num">#</th>
                <th>Name</th>
                <th class="action">Action</th>
            </tr>
            </thead>
            <tbody id="tbody">
            @forelse($models as $index => $model)
                <tr data-id="{{ $model->id }}">
                    <td class="num">{{ $index + 1 }}</td>
                    <td><input type="text" class="model-name" maxlength="15" value="{{ $model->name }}" data-id="{{ $model->id }}"></td>
                    <td class="action"><button type="button" class="warn delete-row">Delete</button></td>
                </tr>
            @empty
                <tr data-id=""><td class="num">1</td><td><input type="text" class="model-name" maxlength="15"></td><td class="action"><button type="button" class="warn delete-row">Delete</button></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="hint">Press Enter to move next row. Press F9 to save.</div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const type = document.getElementById('modelType').value;
    const tbody = document.getElementById('tbody');
    const statusEl = document.getElementById('status');
    let snapshot = serializeRows();

    async function requestJson(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });

        const raw = await res.text();
        let data = null;

        try {
            data = JSON.parse(raw);
        } catch (e) {
            const first = raw.indexOf('{');
            const last = raw.lastIndexOf('}');
            if (first !== -1 && last > first) {
                const candidate = raw.slice(first, last + 1);
                try {
                    data = JSON.parse(candidate);
                } catch (_) {}
            }
            if (!data) {
                throw new Error('Invalid JSON response from server');
            }
        }

        if (!res.ok) {
            throw new Error(data.message || 'Request failed');
        }

        return data;
    }

    function setStatus(msg, ok) {
        statusEl.textContent = msg;
        statusEl.className = 'status show ' + (ok ? 'ok' : 'err');
    }

    function renumberRows() {
        Array.from(tbody.querySelectorAll('tr')).forEach((tr, idx) => {
            tr.querySelector('.num').textContent = String(idx + 1);
        });
    }

    function serializeRows() {
        return Array.from(tbody.querySelectorAll('tr')).map((tr) => ({
            id: tr.dataset.id || '',
            name: (tr.querySelector('.model-name').value || '').trim().toUpperCase()
        }));
    }

    function restoreRows(rows) {
        tbody.innerHTML = '';
        if (!rows.length) {
            rows = [{ id: '', name: '' }];
        }
        rows.forEach((row, idx) => {
            const tr = document.createElement('tr');
            tr.dataset.id = row.id || '';
            tr.innerHTML = `<td class="num">${idx + 1}</td><td><input type="text" class="model-name" maxlength="15" value="${escapeHtml(row.name || '')}" data-id="${escapeHtml(row.id || '')}"></td><td class="action"><button type="button" class="warn delete-row">Delete</button></td>`;
            tbody.appendChild(tr);
        });
    }

    function addRow() {
        const last = tbody.querySelector('tr:last-child .model-name');
        if (last && last.value.trim() === '') {
            last.focus();
            return;
        }
        const tr = document.createElement('tr');
        tr.dataset.id = '';
        tr.innerHTML = '<td class="num">0</td><td><input type="text" class="model-name" maxlength="15"></td><td class="action"><button type="button" class="warn delete-row">Delete</button></td>';
        tbody.appendChild(tr);
        renumberRows();
        tr.querySelector('.model-name').focus();
    }

    async function checkDuplicate(name, excludeId) {
        try {
            const data = await requestJson(@json(url('/models/check-duplicate')), {
                name,
                type,
                exclude_id: excludeId || null
            });
            return !!data.duplicate;
        } catch (_) {
            return false;
        }
    }

    async function saveAll() {
        const names = Array.from(tbody.querySelectorAll('.model-name'))
            .map((el) => (el.value || '').trim().toUpperCase())
            .filter(Boolean);

        const data = await requestJson(@json(url('/models/save')), { type, names });
        if (!data.success) {
            throw new Error(data.message || 'Save failed');
        }
        setStatus(data.message || 'Saved', true);
        setTimeout(() => location.reload(), 400);
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    document.getElementById('btnAdd').addEventListener('click', addRow);
    document.getElementById('btnCancel').addEventListener('click', () => {
        restoreRows(snapshot);
        renumberRows();
    });
    document.getElementById('btnSave').addEventListener('click', async () => {
        try { await saveAll(); } catch (e) { setStatus(e.message, false); }
    });
    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else {
            window.close();
        }
    });

    document.getElementById('btnDelete').addEventListener('click', () => {
        const focused = document.activeElement && document.activeElement.closest('tr');
        if (!focused) {
            setStatus('Focus any row input to delete that row.', false);
            return;
        }
        focused.querySelector('.delete-row').click();
    });

    tbody.addEventListener('click', async (e) => {
        const btn = e.target.closest('.delete-row');
        if (!btn) return;
        const tr = btn.closest('tr');
        const id = Number(tr.dataset.id || 0);
        if (id > 0) {
            try {
                const data = await requestJson(@json(url('/models/delete')), { id });
                if (!data.success) {
                    setStatus(data.message || 'Delete failed', false);
                    return;
                }
            } catch (err) {
                setStatus('Delete failed', false);
                return;
            }
        }
        if (tbody.querySelectorAll('tr').length <= 1) {
            tr.querySelector('.model-name').value = '';
            tr.dataset.id = '';
            tr.querySelector('.model-name').focus();
            return;
        }
        tr.remove();
        renumberRows();
        snapshot = serializeRows();
    });

    tbody.addEventListener('input', (e) => {
        const input = e.target.closest('.model-name');
        if (!input) return;
        input.value = input.value.toUpperCase();
    });

    tbody.addEventListener('keydown', (e) => {
        const input = e.target.closest('.model-name');
        if (!input) return;
        if (e.key === 'Enter') {
            e.preventDefault();
            const tr = input.closest('tr');
            const next = tr.nextElementSibling;
            if (next) {
                next.querySelector('.model-name').focus();
            } else {
                addRow();
            }
        }
    });

    tbody.addEventListener('blur', async (e) => {
        const input = e.target.closest('.model-name');
        if (!input) return;
        const name = input.value.trim().toUpperCase();
        const id = Number(input.dataset.id || 0);
        if (!name) return;
        const dup = await checkDuplicate(name, id);
        if (dup) {
            setStatus('Duplicate name not allowed.', false);
            input.value = '';
            input.focus();
        }
    }, true);

    document.addEventListener('keydown', async (e) => {
        if (e.key === 'F9') {
            e.preventDefault();
            try { await saveAll(); } catch (err) { setStatus(err.message, false); }
        }
    });

    renumberRows();
})();
</script>
</body>
</html>
