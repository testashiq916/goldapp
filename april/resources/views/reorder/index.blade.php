<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ReOrder Level Table</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1100px; margin: 20px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 16px; }
        h1 { margin: 0 0 14px; font-size: 24px; color: #1f3f66; }
        .row { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; margin-bottom: 14px; }
        label { font-size: 12px; font-weight: 600; color: #38577b; display: block; margin-bottom: 4px; }
        input { height: 34px; border: 1px solid #bfd0e6; border-radius: 7px; padding: 0 10px; font-size: 13px; }
        .code { width: 180px; }
        .name { min-width: 320px; background: #f7fafd; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.warn { border-color: #936027; background: #fff3e2; color: #6a3f15; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d8e2ef; padding: 6px; font-size: 12px; }
        th { background: #edf4fc; color: #2d4f74; text-align: left; }
        td input { width: 100%; box-sizing: border-box; }
        .status { margin-top: 10px; padding: 8px 10px; border-radius: 7px; font-size: 12px; display: none; }
        .status.show { display: block; }
        .status.ok { background: #e6f6ec; border: 1px solid #9ed5b0; color: #12522d; }
        .status.err { background: #fdeaea; border: 1px solid #f2b1b1; color: #8e1f1f; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>ReOrder Level Table</h1>
        <div class="row">
            <div>
                <label for="code">Item Code</label>
                <input class="code" id="code" maxlength="10" autocomplete="off">
            </div>
            <div>
                <label for="name">Item Name</label>
                <input class="name" id="name" readonly>
            </div>
            <button id="loadBtn" type="button">Load</button>
            <button id="addRowBtn" type="button">Add Row</button>
            <button id="saveBtn" type="button" class="primary">Save</button>
            <button id="deleteAllBtn" type="button" class="warn">Delete All</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 17%;">Model</th>
                    <th style="width: 17%;">Size</th>
                    <th style="width: 12%;">Weight 1</th>
                    <th style="width: 12%;">Weight 2</th>
                    <th style="width: 12%;">Min Qty</th>
                    <th style="width: 12%;">Max Qty</th>
                    <th style="width: 8%;">Action</th>
                </tr>
            </thead>
            <tbody id="levelsBody"></tbody>
        </table>

        <div id="status" class="status"></div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const endpoints = {
            getItem: @json(url('/reorder/get-item')),
            save: @json(url('/reorder/save')),
            deleteAll: @json(url('/reorder/delete-all')),
        };
        const codeEl = document.getElementById('code');
        const nameEl = document.getElementById('name');
        const bodyEl = document.getElementById('levelsBody');
        const statusEl = document.getElementById('status');

        function showStatus(message, type) {
            statusEl.textContent = message;
            statusEl.className = 'status show ' + (type === 'ok' ? 'ok' : 'err');
        }

        function clearStatus() {
            statusEl.className = 'status';
            statusEl.textContent = '';
        }

        function newCellInput(type, value = '') {
            const input = document.createElement('input');
            input.type = type;
            input.value = value;
            if (type === 'number') {
                input.step = 'any';
                input.min = '0';
            }
            return input;
        }

        function addRow(row = {}) {
            const tr = document.createElement('tr');
            const model = newCellInput('text', row.model || '');
            const size = newCellInput('text', row.size || '');
            const weight1 = newCellInput('number', row.weight1 ?? '');
            const weight2 = newCellInput('number', row.weight2 ?? '');
            const minqty = newCellInput('number', row.minqty ?? '');
            const maxqty = newCellInput('number', row.maxqty ?? '');

            [model, size].forEach((el) => el.maxLength = 20);
            [weight1, weight2].forEach((el) => el.max = '999.999');
            [minqty, maxqty].forEach((el) => el.step = '1');

            const fields = [model, size, weight1, weight2, minqty, maxqty];
            fields.forEach((el) => {
                const td = document.createElement('td');
                td.appendChild(el);
                tr.appendChild(td);
            });

            const actTd = document.createElement('td');
            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'warn';
            delBtn.textContent = 'X';
            delBtn.addEventListener('click', () => tr.remove());
            actTd.appendChild(delBtn);
            tr.appendChild(actTd);

            bodyEl.appendChild(tr);
        }

        function getRowsPayload() {
            return Array.from(bodyEl.querySelectorAll('tr')).map((tr) => {
                const i = tr.querySelectorAll('input');
                return {
                    model: i[0].value.trim().toUpperCase(),
                    size: i[1].value.trim().toUpperCase(),
                    weight1: i[2].value === '' ? 0 : Number(i[2].value),
                    weight2: i[3].value === '' ? 0 : Number(i[3].value),
                    minqty: i[4].value === '' ? 0 : Number(i[4].value),
                    maxqty: i[5].value === '' ? 0 : Number(i[5].value),
                };
            });
        }

        async function postJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Request failed');
            }
            return data;
        }

        async function loadItem() {
            clearStatus();
            const code = codeEl.value.trim().toUpperCase();
            if (!code) {
                showStatus('Item code is required', 'err');
                return;
            }

            try {
                const data = await postJson(endpoints.getItem, { code });
                codeEl.value = data.item.code || code;
                nameEl.value = data.item.name || '';
                bodyEl.innerHTML = '';
                (data.reorderLevels || []).forEach((row) => addRow(row));
                if (bodyEl.children.length === 0) {
                    addRow();
                }
                showStatus('Item loaded', 'ok');
            } catch (error) {
                nameEl.value = '';
                bodyEl.innerHTML = '';
                addRow();
                showStatus(error.message, 'err');
            }
        }

        async function saveLevels() {
            clearStatus();
            const code = codeEl.value.trim().toUpperCase();
            if (!code) {
                showStatus('Item code is required', 'err');
                return;
            }

            try {
                const data = await postJson(endpoints.save, {
                    code,
                    levels: getRowsPayload(),
                });
                showStatus(data.message || 'Updated successfully', 'ok');
            } catch (error) {
                showStatus(error.message, 'err');
            }
        }

        async function deleteAll() {
            clearStatus();
            const code = codeEl.value.trim().toUpperCase();
            if (!code) {
                showStatus('Item code is required', 'err');
                return;
            }

            if (!window.confirm('Delete all reorder levels for this code?')) {
                return;
            }

            try {
                const data = await postJson(endpoints.deleteAll, { code });
                bodyEl.innerHTML = '';
                addRow();
                showStatus(data.message || 'All reorder levels deleted', 'ok');
            } catch (error) {
                showStatus(error.message, 'err');
            }
        }

        document.getElementById('loadBtn').addEventListener('click', loadItem);
        document.getElementById('saveBtn').addEventListener('click', saveLevels);
        document.getElementById('deleteAllBtn').addEventListener('click', deleteAll);
        document.getElementById('addRowBtn').addEventListener('click', () => addRow());
        codeEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadItem();
            }
        });

        addRow();
    </script>
</body>
</html>
