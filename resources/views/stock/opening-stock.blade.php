<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Opening Stock Entry</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1250px; margin: 18px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .filters { display: grid; grid-template-columns: repeat(6, minmax(120px, 1fr)); gap: 10px; margin-bottom: 12px; }
        .field label { display: block; font-size: 12px; font-weight: 700; color: #3b5d86; margin-bottom: 4px; }
        .field input, .field select { width: 100%; height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 10px; font-size: 12px; }
        .actions { display: flex; gap: 8px; align-items: end; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        .opts { display: flex; gap: 16px; margin: 10px 0 12px; font-size: 12px; color: #395980; }
        .msg { margin: 8px 0 12px; padding: 9px 11px; border-radius: 7px; font-size: 12px; border: 1px solid #ead59b; background: #fff8e1; color: #6b5120; }
        .status { margin: 8px 0 12px; padding: 9px 11px; border-radius: 7px; font-size: 12px; display: none; }
        .status.show { display: block; }
        .status.ok { border: 1px solid #9fd5af; background: #e6f6ec; color: #12522d; }
        .status.err { border: 1px solid #f2b1b1; background: #fdeaea; color: #8e1f1f; }
        .table-wrap { max-height: 62vh; overflow: auto; border: 1px solid #d8e2ef; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 6px; font-size: 12px; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2d4f74; z-index: 2; text-align: left; }
        td.num, th.num { text-align: right; }
        td input { width: 100%; box-sizing: border-box; height: 30px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; text-align: right; }
        tfoot td { background: #f3f7fd; font-weight: 700; color: #24486d; position: sticky; bottom: 0; }
        .muted { color: #627389; font-size: 11px; }
        @media (max-width: 1100px) { .filters { grid-template-columns: repeat(2, minmax(120px, 1fr)); } }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Opening Stock Entry</h1>

    @if($message !== '')
        <div class="msg">{{ $message }}</div>
    @endif

    <form method="get" action="{{ url('/stock/opening-stock') }}">
        <div class="filters">
            <div class="field">
                <label for="stktype">Stock Type</label>
                <select id="stktype" name="stktype">
                    <option value="">All</option>
                    @foreach($stockTypes as $code)
                        <option value="{{ $code }}" {{ $filters['stktype'] === $code ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="grpcode">Group Code</label>
                <select id="grpcode" name="grpcode">
                    <option value="">All</option>
                    @foreach($groupCodes as $code)
                        <option value="{{ $code }}" {{ $filters['grpcode'] === $code ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="itype">Item Type</label>
                <select id="itype" name="itype">
                    <option value="All" {{ $filters['itype'] === 'All' ? 'selected' : '' }}>All</option>
                    <option value="Gold Only" {{ $filters['itype'] === 'Gold Only' ? 'selected' : '' }}>Gold Only</option>
                    <option value="Silver Only" {{ $filters['itype'] === 'Silver Only' ? 'selected' : '' }}>Silver Only</option>
                    <option value="Other Only" {{ $filters['itype'] === 'Other Only' ? 'selected' : '' }}>Other Only</option>
                </select>
            </div>
            <div class="field">
                <label for="stock_view">View</label>
                <select id="stock_view" name="stock_view">
                    <option value="opening" {{ $filters['stock_view'] === 'opening' ? 'selected' : '' }}>Op.Stock</option>
                    <option value="closing" {{ $filters['stock_view'] === 'closing' ? 'selected' : '' }}>Cl.Stock</option>
                </select>
            </div>
            <div class="field">
                <label for="level">Level</label>
                <select id="level" name="level">
                    <option value="1" {{ (int)$filters['level'] === 1 ? 'selected' : '' }}>1</option>
                    <option value="2" {{ (int)$filters['level'] !== 1 ? 'selected' : '' }}>B</option>
                </select>
            </div>
            <div class="field">
                <label for="search">Search</label>
                <input id="search" name="search" maxlength="40" value="{{ $filters['search'] }}">
            </div>
        </div>
        <div class="actions">
            <button type="submit">Show</button>
        </div>
    </form>

    <div class="opts">
        <label><input type="checkbox" id="same_stock"> Same Stock</label>
        <label><input type="checkbox" id="update_closing_also" checked> Update Cl.Stock also</label>
        <span class="muted">Rows loaded: {{ count($items) }} (max 500)</span>
    </div>

    <div id="status" class="status"></div>

    <form id="stockForm">
        @csrf
        <div class="table-wrap">
            <table id="itemsTable">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th class="num">Touch</th>
                    <th class="num">Qty</th>
                    <th class="num">Weight</th>
                    <th class="num">Stone Wgt</th>
                    <th class="num">Dmd/Pres</th>
                    <th class="num">Net Wgt</th>
                    <th class="num">Stone Amt</th>
                </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    <tr data-code="{{ $item->code }}">
                        <td>{{ $item->code }}</td>
                        <td>{{ $item->name }}</td>
                        <td class="num">{{ number_format((float)$item->touch, 1) }}</td>
                        <td><input type="number" step="1" data-field="qty" value="{{ (float)$item->cqty }}"></td>
                        <td><input type="number" step="0.001" data-field="weight" value="{{ (float)$item->cweight }}"></td>
                        <td><input type="number" step="0.001" data-field="stonewgt" value="{{ (float)$item->cstwgt }}"></td>
                        <td><input type="number" step="0.001" data-field="dmdwgt" value="{{ (float)$item->cdmdwgt }}"></td>
                        <td class="num net-weight">{{ number_format(((float)$item->cweight - (float)$item->cstwgt), 3) }}</td>
                        <td><input type="number" step="0.01" data-field="stoneamt" value="{{ (float)$item->cstoneamt }}"></td>
                    </tr>
                @empty
                    <tr><td colspan="9">No items found.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="3">Totals</td>
                    <td id="totQty" class="num">0.000</td>
                    <td id="totWeight" class="num">0.000</td>
                    <td id="totSt" class="num">0.000</td>
                    <td id="totDmd" class="num">0.000</td>
                    <td id="totNet" class="num">0.000</td>
                    <td id="totAmt" class="num">0.000</td>
                </tr>
                </tfoot>
            </table>
        </div>
        <div class="actions" style="margin-top:10px;">
            <button type="button" class="primary" id="saveBtn">Update</button>
        </div>
    </form>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const statusEl = document.getElementById('status');
    const rows = () => Array.from(document.querySelectorAll('#itemsTable tbody tr[data-code]'));

    function setStatus(msg, ok) {
        statusEl.textContent = msg;
        statusEl.className = 'status show ' + (ok ? 'ok' : 'err');
    }

    function calc() {
        let q = 0, w = 0, s = 0, d = 0, a = 0;
        rows().forEach((row) => {
            const qty = Number(row.querySelector('[data-field="qty"]').value || 0);
            const weight = Number(row.querySelector('[data-field="weight"]').value || 0);
            const st = Number(row.querySelector('[data-field="stonewgt"]').value || 0);
            const dmd = Number(row.querySelector('[data-field="dmdwgt"]').value || 0);
            const amt = Number(row.querySelector('[data-field="stoneamt"]').value || 0);
            q += qty; w += weight; s += st; d += dmd; a += amt;
            row.querySelector('.net-weight').textContent = (weight - st).toFixed(3);
        });
        document.getElementById('totQty').textContent = q.toFixed(3);
        document.getElementById('totWeight').textContent = w.toFixed(3);
        document.getElementById('totSt').textContent = s.toFixed(3);
        document.getElementById('totDmd').textContent = d.toFixed(3);
        document.getElementById('totNet').textContent = (w - s).toFixed(3);
        document.getElementById('totAmt').textContent = a.toFixed(3);
    }

    document.querySelectorAll('#itemsTable input').forEach((input) => {
        input.addEventListener('input', calc);
    });

    document.getElementById('saveBtn').addEventListener('click', async () => {
        const payload = {
            stktype: document.getElementById('stktype').value.trim(),
            level: Number(document.getElementById('level').value || 1),
            stock_view: document.getElementById('stock_view').value,
            same_stock: document.getElementById('same_stock').checked,
            update_closing_also: document.getElementById('update_closing_also').checked,
            items: rows().map((row) => ({
                code: row.dataset.code,
                qty: Number(row.querySelector('[data-field="qty"]').value || 0),
                weight: Number(row.querySelector('[data-field="weight"]').value || 0),
                stonewgt: Number(row.querySelector('[data-field="stonewgt"]').value || 0),
                dmdwgt: Number(row.querySelector('[data-field="dmdwgt"]').value || 0),
                stoneamt: Number(row.querySelector('[data-field="stoneamt"]').value || 0)
            }))
        };

        try {
            const res = await fetch(@json(url('/stock/opening-stock/update')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                setStatus(data.message || 'Update failed', false);
                return;
            }
            setStatus(data.message || 'Updated successfully', true);
        } catch (e) {
            setStatus('Update failed: ' + e.message, false);
        }
    });

    calc();
})();
</script>
</body>
</html>
