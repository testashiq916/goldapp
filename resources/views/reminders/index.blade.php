<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $csrf }}">
    <title>Task Reminders</title>
    <style>
        :root{--bg:#eef3f8;--panel:#fff;--line:#d8e2ee;--text:#17324d;--muted:#64748b;--green:#137a4b;--gold:#b8860b;--red:#b42318;--amber:#b76e00;--blue:#1f4d86;--shadow:0 18px 45px rgba(15,23,42,.10)}
        *{box-sizing:border-box}
        body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:linear-gradient(180deg,#f7fafc 0%,#e9eef5 100%);color:var(--text)}
        button,input,select,textarea{font:inherit}
        .page{padding:18px}
        .wrap{max-width:1880px;margin:0 auto}
        .top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:14px}
        h1{margin:0;font-size:30px;line-height:1.1}
        .sub{margin-top:6px;color:var(--muted);font-size:13px}
        .btn{border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:8px;padding:9px 13px;font-weight:700;cursor:pointer}
        .btn.primary{background:linear-gradient(180deg,#1d8b58 0%,#116b41 100%);border-color:#116b41;color:#fff}
        .btn.danger{background:#fff5f5;border-color:#fecaca;color:#991b1b}
        .layout{display:grid;grid-template-columns:340px minmax(0,1fr);gap:16px}
        .panel{background:var(--panel);border:1px solid rgba(148,163,184,.36);border-radius:8px;box-shadow:var(--shadow)}
        .side{padding:16px}
        .main{padding:14px}
        .section+.section{margin-top:16px}
        .section h2{margin:0 0 10px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#475569}
        .field{display:grid;gap:6px;margin-bottom:10px}
        label{font-size:12px;font-weight:700;color:#334155}
        input,select,textarea{width:100%;border:1px solid var(--line);border-radius:8px;padding:9px 10px;background:#fff;color:var(--text)}
        textarea{resize:vertical;min-height:76px}
        input:focus,select:focus,textarea:focus{outline:none;border-color:var(--green);box-shadow:0 0 0 3px rgba(19,122,75,.12)}
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
        .seg{display:flex;border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#fff}
        .seg button{border:0;border-right:1px solid var(--line);background:#fff;padding:8px 11px;font-weight:700;color:#475569;cursor:pointer}
        .seg button:last-child{border-right:0}
        .seg button.active{background:#e8f5ef;color:#0f6840}
        .search{max-width:280px;margin-left:auto}
        .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:12px}
        .stat{border:1px solid #e5edf5;border-radius:8px;padding:12px;background:#fbfdff}
        .stat .k{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b}
        .stat .v{font-size:24px;font-weight:800;margin-top:4px}
        .table-wrap{border:1px solid var(--line);border-radius:8px;overflow:auto;max-height:70vh;background:#fff}
        table{width:100%;border-collapse:collapse;min-width:980px}
        th,td{padding:10px;border-bottom:1px solid #edf2f7;text-align:left;vertical-align:top}
        th{position:sticky;top:0;background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:.05em;z-index:1}
        tr.overdue td{background:#fff5f5}
        tr.today td{background:#fffbeb}
        tr.done td{color:#64748b;background:#f8fafc}
        .title{font-weight:800}
        .meta{font-size:12px;color:var(--muted);margin-top:3px}
        .badge{display:inline-block;border-radius:999px;padding:4px 8px;font-size:11px;font-weight:800}
        .badge.overdue{background:#fee2e2;color:var(--red)}
        .badge.today{background:#fef3c7;color:#92400e}
        .badge.upcoming{background:#e0f2fe;color:#075985}
        .badge.done{background:#e2e8f0;color:#475569}
        .badge.manual{background:#e8f5ef;color:#0f6840}
        .badge.auto{background:#eef4ff;color:#1d4ed8}
        .money{text-align:right;font-variant-numeric:tabular-nums}
        .actions{display:flex;gap:6px;flex-wrap:wrap}
        .mini{border:1px solid #d7dee8;background:#fff;border-radius:8px;padding:6px 9px;font-size:12px;font-weight:700;cursor:pointer}
        .empty{padding:44px 12px;text-align:center;color:var(--muted)}
        .msg{font-size:13px;color:var(--green);min-height:18px}
        .picker{position:relative}
        .suggest{position:absolute;left:0;right:0;top:100%;z-index:20;margin-top:4px;background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 16px 34px rgba(15,23,42,.16);max-height:220px;overflow:auto;display:none}
        .suggest.show{display:block}
        .suggest button{display:block;width:100%;border:0;border-bottom:1px solid #edf2f7;background:#fff;text-align:left;padding:9px 10px;cursor:pointer}
        .suggest button:hover{background:#f0fdf4}
        .pick-title{font-weight:800;color:#17324d}
        .pick-meta{font-size:12px;color:#64748b;margin-top:2px}
        .selected-note{font-size:12px;color:#0f6840;min-height:16px;margin-top:-4px;margin-bottom:8px}
        @media (max-width:1200px){.layout{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.search{margin-left:0;max-width:none}}
        @media (max-width:700px){.page{padding:10px}.top{display:block}.grid2,.stats{grid-template-columns:1fr}.seg{width:100%;overflow:auto}.seg button{white-space:nowrap}}
    </style>
</head>
<body>
<div class="page">
    <div class="wrap">
        <div class="top">
            <div>
                <h1>Task Reminders</h1>
                <div class="sub">Bill due dates, order due dates, and manual reminders in one place.</div>
            </div>
            <button class="btn" id="refreshBtn" type="button">Refresh</button>
        </div>

        <div class="layout">
            <aside class="panel side">
                <div class="section">
                    <h2>Create Reminder</h2>
                    <form id="reminderForm">
                        <div class="field">
                            <label for="title">Title</label>
                            <input id="title" name="title" required maxlength="160" placeholder="Bill payment, customer follow-up">
                        </div>
                        <div class="grid2">
                            <div class="field">
                                <label for="category">Type</label>
                                <select id="category" name="category">
                                    <option>Bill</option>
                                    <option>Order</option>
                                    <option>Customer Follow-up</option>
                                    <option>Supplier Payment</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="priority">Priority</label>
                                <select id="priority" name="priority">
                                    <option>Normal</option>
                                    <option>High</option>
                                    <option>Low</option>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label for="due_date">Due Date</label>
                            <input id="due_date" name="due_date" type="date" required value="{{ $today }}">
                        </div>
                        <div class="grid2">
                            <div class="field">
                                <label for="party_name">Party</label>
                                <div class="picker">
                                    <input id="party_name" name="party_name" maxlength="160" autocomplete="off" placeholder="Type customer name / code">
                                    <input id="party_code" name="party_code" type="hidden">
                                    <div class="suggest" id="customerSuggest"></div>
                                </div>
                            </div>
                            <div class="field">
                                <label for="reference_no">Bill / Ref No</label>
                                <select id="billSelect">
                                    <option value="">Select customer first</option>
                                </select>
                                <input id="reference_no" name="reference_no" type="hidden">
                            </div>
                        </div>
                        <div class="selected-note" id="selectedInfo"></div>
                        <div class="field">
                            <label for="amount">Amount</label>
                            <input id="amount" name="amount" inputmode="decimal" placeholder="0.00">
                        </div>
                        <div class="field">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" placeholder="Extra details"></textarea>
                        </div>
                        <button class="btn primary" type="submit">Create Reminder</button>
                        <div class="msg" id="formMsg"></div>
                    </form>
                </div>
            </aside>

            <main class="panel main">
                <div class="stats">
                    <div class="stat"><div class="k">Visible</div><div class="v" id="statTotal">0</div></div>
                    <div class="stat"><div class="k">Overdue</div><div class="v" id="statOverdue">0</div></div>
                    <div class="stat"><div class="k">Today</div><div class="v" id="statToday">0</div></div>
                    <div class="stat"><div class="k">Upcoming</div><div class="v" id="statUpcoming">0</div></div>
                </div>

                <div class="filters">
                    <div class="seg" id="statusSeg">
                        <button type="button" data-status="pending" class="active">Pending</button>
                        <button type="button" data-status="done">Completed</button>
                        <button type="button" data-status="all">All</button>
                    </div>
                    <div class="seg" id="rangeSeg">
                        <button type="button" data-range="all" class="active">All Due</button>
                        <button type="button" data-range="overdue">Overdue</button>
                        <button type="button" data-range="today">Today</button>
                        <button type="button" data-range="week">7 Days</button>
                        <button type="button" data-range="month">30 Days</button>
                    </div>
                    <input class="search" id="searchInput" placeholder="Search title, party, bill no">
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Reminder</th>
                            <th>Party / Reference</th>
                            <th>Due Date</th>
                            <th class="money">Amount</th>
                            <th>Source</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody id="rowsBody">
                        <tr><td colspan="6" class="empty">Loading reminders...</td></tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const state = { status: 'pending', range: 'all', q: '' };
const today = @json($today);
const els = {
    form: document.getElementById('reminderForm'),
    formMsg: document.getElementById('formMsg'),
    rows: document.getElementById('rowsBody'),
    search: document.getElementById('searchInput'),
    refresh: document.getElementById('refreshBtn'),
    statTotal: document.getElementById('statTotal'),
    statOverdue: document.getElementById('statOverdue'),
    statToday: document.getElementById('statToday'),
    statUpcoming: document.getElementById('statUpcoming'),
    title: document.getElementById('title'),
    category: document.getElementById('category'),
    dueDate: document.getElementById('due_date'),
    partyName: document.getElementById('party_name'),
    partyCode: document.getElementById('party_code'),
    customerSuggest: document.getElementById('customerSuggest'),
    billSelect: document.getElementById('billSelect'),
    referenceNo: document.getElementById('reference_no'),
    amount: document.getElementById('amount'),
    selectedInfo: document.getElementById('selectedInfo'),
};

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
}

function fmtDate(value) {
    if (!value) return '';
    const parts = String(value).slice(0, 10).split('-');
    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : value;
}

function money(value) {
    return Number(value || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function statusFor(row) {
    if (row.is_done) return 'done';
    if (row.due_date < today) return 'overdue';
    if (row.due_date === today) return 'today';
    return 'upcoming';
}

async function api(url, options = {}) {
    const response = await fetch(url, {
        headers: {'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf},
        ...options
    });
    const data = await response.json().catch(() => ({ok:false, message:'Invalid response'}));
    if (!response.ok || data.ok === false) throw new Error(data.message || 'Request failed');
    return data;
}

async function loadRows() {
    els.rows.innerHTML = '<tr><td colspan="6" class="empty">Loading reminders...</td></tr>';
    const params = new URLSearchParams(state);
    try {
        const data = await api(`{{ url('/api/reminders') }}?${params.toString()}`);
        renderStats(data.summary || {});
        renderRows(data.rows || []);
    } catch (err) {
        els.rows.innerHTML = `<tr><td colspan="6" class="empty">${esc(err.message)}</td></tr>`;
    }
}

function renderStats(summary) {
    els.statTotal.textContent = summary.total || 0;
    els.statOverdue.textContent = summary.overdue || 0;
    els.statToday.textContent = summary.today || 0;
    els.statUpcoming.textContent = summary.upcoming || 0;
}

function renderRows(rows) {
    if (!rows.length) {
        els.rows.innerHTML = '<tr><td colspan="6" class="empty">No reminders found.</td></tr>';
        return;
    }
    els.rows.innerHTML = rows.map(row => {
        const status = statusFor(row);
        const sourceBadge = row.editable ? '<span class="badge manual">Manual</span>' : '<span class="badge auto">Auto Due</span>';
        const action = row.editable
            ? `<button class="mini" data-done="${row.manual_id}" data-state="${row.is_done ? 0 : 1}">${row.is_done ? 'Reopen' : 'Done'}</button><button class="mini" data-delete="${row.manual_id}">Delete</button>`
            : '<span class="meta">From bill/order due date</span>';
        return `<tr class="${status}">
            <td><div class="title">${esc(row.title)}</div><div class="meta">${esc(row.category)} · ${esc(row.priority || '')}</div>${row.notes ? `<div class="meta">${esc(row.notes)}</div>` : ''}</td>
            <td><div>${esc(row.party_name || '')}</div><div class="meta">${esc(row.reference_no || '')}</div></td>
            <td><span class="badge ${status}">${fmtDate(row.due_date)}</span></td>
            <td class="money">${money(row.amount)}</td>
            <td>${sourceBadge}</td>
            <td><div class="actions">${action}</div></td>
        </tr>`;
    }).join('');
}

function renderCustomerSuggest(rows) {
    if (!rows.length) {
        els.customerSuggest.innerHTML = '<button type="button"><div class="pick-meta">No customer found</div></button>';
        els.customerSuggest.classList.add('show');
        return;
    }

    els.customerSuggest.innerHTML = rows.map(row => `
        <button type="button" data-code="${esc(row.code)}" data-name="${esc(row.name)}">
            <div class="pick-title">${esc(row.name || row.code)}</div>
            <div class="pick-meta">${esc([row.code, row.ctype, row.mobile, row.city].filter(Boolean).join(' | '))}</div>
        </button>
    `).join('');
    els.customerSuggest.classList.add('show');
}

async function searchCustomers() {
    const q = els.partyName.value.trim();
    els.partyCode.value = '';
    if (q.length < 2) {
        els.customerSuggest.classList.remove('show');
        return;
    }
    const data = await api(`{{ url('/api/reminders/customers') }}?q=${encodeURIComponent(q)}`);
    renderCustomerSuggest(data.rows || []);
}

async function loadBillsForCustomer() {
    const code = els.partyCode.value.trim();
    els.billSelect.innerHTML = '<option value="">Loading bills...</option>';
    els.referenceNo.value = '';
    els.selectedInfo.textContent = '';
    if (!code) {
        els.billSelect.innerHTML = '<option value="">Select customer first</option>';
        return;
    }
    const data = await api(`{{ url('/api/reminders/bills') }}?code=${encodeURIComponent(code)}`);
    const rows = data.rows || [];
    if (!rows.length) {
        els.billSelect.innerHTML = '<option value="">No due bills found</option>';
        return;
    }
    els.billSelect.innerHTML = '<option value="">Select bill / order</option>' + rows.map((row, i) => `
        <option value="${i}"
            data-ref="${esc(row.reference_no)}"
            data-category="${esc(row.category)}"
            data-due="${esc(row.due_date)}"
            data-amount="${esc(row.amount)}"
            data-party="${esc(row.party_name)}">
            ${esc(row.category)} ${esc(row.reference_no)} - ${fmtDate(row.due_date)} - ${money(row.amount)}
        </option>
    `).join('');
}

function applySelectedBill() {
    const option = els.billSelect.selectedOptions[0];
    if (!option || !option.dataset.ref) {
        els.referenceNo.value = '';
        els.selectedInfo.textContent = '';
        return;
    }

    const category = option.dataset.category || 'Bill';
    const ref = option.dataset.ref || '';
    const party = option.dataset.party || els.partyName.value.trim();
    els.category.value = category.includes('Order') ? 'Order' : (category.includes('Purchase') ? 'Supplier Payment' : 'Bill');
    els.referenceNo.value = ref;
    els.dueDate.value = option.dataset.due || els.dueDate.value;
    els.amount.value = option.dataset.amount || '0';
    els.title.value = `${category} ${ref}`.trim();
    if (party) els.partyName.value = party;
    els.selectedInfo.textContent = `Selected ${category} ${ref}, due ${fmtDate(option.dataset.due)}, amount ${money(option.dataset.amount)}.`;
}

document.getElementById('statusSeg').addEventListener('click', e => {
    const btn = e.target.closest('[data-status]');
    if (!btn) return;
    state.status = btn.dataset.status;
    document.querySelectorAll('#statusSeg button').forEach(b => b.classList.toggle('active', b === btn));
    loadRows();
});

document.getElementById('rangeSeg').addEventListener('click', e => {
    const btn = e.target.closest('[data-range]');
    if (!btn) return;
    state.range = btn.dataset.range;
    document.querySelectorAll('#rangeSeg button').forEach(b => b.classList.toggle('active', b === btn));
    loadRows();
});

let searchTimer = null;
els.search.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        state.q = els.search.value.trim();
        loadRows();
    }, 250);
});

let customerTimer = null;
els.partyName.addEventListener('input', () => {
    clearTimeout(customerTimer);
    customerTimer = setTimeout(() => {
        searchCustomers().catch(err => {
            els.customerSuggest.innerHTML = `<button type="button"><div class="pick-meta">${esc(err.message)}</div></button>`;
            els.customerSuggest.classList.add('show');
        });
    }, 250);
});

els.partyName.addEventListener('focus', () => {
    if (els.partyName.value.trim().length >= 2) {
        searchCustomers().catch(() => {});
    }
});

els.customerSuggest.addEventListener('click', e => {
    const btn = e.target.closest('[data-code]');
    if (!btn) return;
    els.partyCode.value = btn.dataset.code || '';
    els.partyName.value = `${btn.dataset.name || ''} (${btn.dataset.code || ''})`.trim();
    els.customerSuggest.classList.remove('show');
    loadBillsForCustomer().catch(err => {
        els.billSelect.innerHTML = `<option value="">${esc(err.message)}</option>`;
    });
});

els.billSelect.addEventListener('change', applySelectedBill);

document.addEventListener('click', e => {
    if (!els.customerSuggest.contains(e.target) && e.target !== els.partyName) {
        els.customerSuggest.classList.remove('show');
    }
});

els.form.addEventListener('submit', async e => {
    e.preventDefault();
    els.formMsg.textContent = '';
    const payload = Object.fromEntries(new FormData(els.form).entries());
    try {
        const data = await api('{{ url('/api/reminders') }}', {method:'POST', body: JSON.stringify(payload)});
        els.form.reset();
        els.dueDate.value = today;
        els.partyCode.value = '';
        els.referenceNo.value = '';
        els.selectedInfo.textContent = '';
        els.billSelect.innerHTML = '<option value="">Select customer first</option>';
        els.formMsg.textContent = data.message || 'Reminder created';
        loadRows();
    } catch (err) {
        els.formMsg.textContent = err.message;
    }
});

els.rows.addEventListener('click', async e => {
    const doneBtn = e.target.closest('[data-done]');
    const deleteBtn = e.target.closest('[data-delete]');
    try {
        if (doneBtn) {
            await api(`{{ url('/api/reminders') }}/${doneBtn.dataset.done}/toggle`, {method:'PATCH', body: JSON.stringify({done: doneBtn.dataset.state === '1'})});
            loadRows();
        }
        if (deleteBtn && confirm('Delete this reminder?')) {
            await api(`{{ url('/api/reminders') }}/${deleteBtn.dataset.delete}`, {method:'DELETE'});
            loadRows();
        }
    } catch (err) {
        alert(err.message);
    }
});

els.refresh.addEventListener('click', loadRows);
loadRows();
</script>
</body>
</html>
