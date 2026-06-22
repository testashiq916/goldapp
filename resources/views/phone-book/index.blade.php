<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Phone Book</title>
    <style>
        :root{
            --bg:#eef3f8;
            --panel:#ffffff;
            --line:#d8e1ec;
            --text:#17324d;
            --muted:#64748b;
            --brand:#b8860b;
            --brand-soft:#fff6df;
            --blue:#204a87;
            --green:#148a53;
            --shadow:0 24px 60px rgba(15,23,42,.10);
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:linear-gradient(180deg,#f7fafc 0%,#e9eef5 100%);color:var(--text)}
        button,input,select{font:inherit}
        .page{padding:18px}
        .wrap{max-width:1880px;margin:0 auto}
        .hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:16px}
        .hero h1{margin:0;font-size:34px;line-height:1.05}
        .hero p{margin:8px 0 0;color:var(--muted);max-width:760px}
        .hero-actions{display:flex;gap:10px;flex-wrap:wrap}
        .btn{border:1px solid #d4bb71;background:linear-gradient(180deg,#fff8df 0%,#efd38e 100%);color:#5b4308;padding:10px 15px;border-radius:13px;font-weight:700;cursor:pointer}
        .btn.alt{background:#fff;border-color:#cbd5e1;color:#334155}
        .btn:hover{filter:brightness(.97)}
        .layout{display:grid;grid-template-columns:330px minmax(0,1fr);gap:18px}
        .panel{background:rgba(255,255,255,.95);border:1px solid rgba(148,163,184,.28);border-radius:24px;box-shadow:var(--shadow)}
        .side{padding:18px}
        .main{padding:16px}
        .section+.section{margin-top:16px}
        .section h2{margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:.12em;color:#475569}
        .field{display:grid;gap:7px;margin-bottom:11px}
        .field label{font-size:12px;font-weight:700;color:#334155}
        .field input,.field select{width:100%;border:1px solid var(--line);border-radius:14px;padding:11px 12px;background:#fff}
        .field input:focus,.field select:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(184,134,11,.12)}
        .chips{display:grid;gap:8px}
        .chip{border:1px solid #e6d59e;background:#fffaf0;border-radius:14px;padding:11px 12px;text-align:left;cursor:pointer;font-weight:700;color:#7a5a10}
        .chip small{display:block;margin-top:2px;font-weight:500;color:#8c7240}
        .chip.active{background:linear-gradient(135deg,#b8860b 0%,#d4a017 100%);border-color:#a97e08;color:#fff}
        .chip.active small{color:#fde68a}
        .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px}
        .stat{background:linear-gradient(180deg,#fffdfa 0%,#f7efdc 100%);border:1px solid #ecd8ab;border-radius:18px;padding:14px}
        .stat .k{font-size:12px;color:#7a6432;text-transform:uppercase;letter-spacing:.08em}
        .stat .v{margin-top:6px;font-size:29px;font-weight:800}
        .toolbar{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:12px}
        .toolbar .meta{font-size:13px;color:var(--muted)}
        .table-wrap{overflow:auto;border:1px solid var(--line);border-radius:18px;background:#fff;max-height:68vh}
        table{width:100%;border-collapse:collapse;min-width:1100px}
        th,td{padding:10px 9px;border-bottom:1px solid #edf2f7;vertical-align:top}
        th{position:sticky;top:0;background:#fff7e4;color:#6b5311;font-size:12px;text-transform:uppercase;letter-spacing:.05em;z-index:1}
        tr:hover td{background:#fcf8ee}
        .name{font-weight:700}
        .sub{color:var(--muted);font-size:12px;margin-top:2px}
        .badge{display:inline-block;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:800}
        .badge.type{background:#e8f0fb;color:#234577}
        .actions{display:flex;gap:6px;flex-wrap:wrap}
        .mini{border:1px solid #d7dee8;background:#fff;border-radius:10px;padding:7px 10px;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;color:#334155}
        .mini.wa{background:#effcf3;border-color:#9ad4b2;color:#17663e}
        .mini.tel{background:#eef4ff;border-color:#b9caf8;color:#2442a4}
        .mini.mail{background:#fff4f4;border-color:#f0bcbc;color:#9f3131}
        .empty{padding:56px 16px;text-align:center;color:var(--muted)}
        .quick-list{display:grid;gap:8px}
        .quick{padding:10px 12px;border-radius:14px;border:1px solid #dde6f2;background:#f8fbff;cursor:pointer}
        .quick strong{display:block;color:#1f3a5b}
        .quick span{font-size:12px;color:var(--muted)}
        @media (max-width:1400px){.layout{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:780px){.page{padding:10px}.hero{flex-direction:column}.stats{grid-template-columns:1fr}}
    </style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
@php $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); @endphp
<div class="page">
    <div class="wrap">
        <div class="hero">
            <div>
                <h1>Customer Phone Book</h1>
                <p>Search customer and party contacts by code, name, mobile, phone, city, or email. This screen is designed for quick lookup, call follow-up, WhatsApp sharing, and contact cleanup.</p>
            </div>
            <div class="hero-actions">
                <button class="btn alt" type="button" id="reloadBtn">Refresh</button>
                <button class="btn alt" type="button" id="copyVisibleBtn">Copy Visible Numbers</button>
            </div>
        </div>

        <div class="layout">
            <aside class="panel side">
                <div class="section">
                    <h2>Party Type</h2>
                    <div class="chips" id="typeChips"></div>
                </div>

                <div class="section">
                    <h2>Search</h2>
                    <div class="field">
                        <label for="searchInput">Name / Code / Mobile / City</label>
                        <input id="searchInput" type="text" placeholder="Search contacts">
                    </div>
                    <div class="field">
                        <label for="statusSelect">Contact Status</label>
                        <select id="statusSelect">
                            <option value="all">All</option>
                            <option value="any-contact">Any Contact Available</option>
                            <option value="mobile-only">Has Mobile</option>
                            <option value="missing-mobile">Missing Mobile</option>
                        </select>
                    </div>
                </div>

                <div class="section">
                    <h2>Quick Lists</h2>
                    <div class="quick-list">
                        <button class="quick" type="button" data-quick="customers">
                            <strong>Customers</strong>
                            <span>Default customer contact book</span>
                        </button>
                        <button class="quick" type="button" data-quick="missing-mobile">
                            <strong>Missing Mobile</strong>
                            <span>Find records that still need mobile numbers</span>
                        </button>
                        <button class="quick" type="button" data-quick="suppliers">
                            <strong>Suppliers</strong>
                            <span>Switch to supplier contacts</span>
                        </button>
                        <button class="quick" type="button" data-quick="all-parties">
                            <strong>All Parties</strong>
                            <span>See every saved party contact together</span>
                        </button>
                    </div>
                </div>
            </aside>

            <main class="panel main">
                <div class="stats">
                    <div class="stat"><div class="k">Visible Contacts</div><div class="v" id="statTotal">0</div></div>
                    <div class="stat"><div class="k">With Mobile</div><div class="v" id="statMobile">0</div></div>
                    <div class="stat"><div class="k">With Phone</div><div class="v" id="statPhone">0</div></div>
                    <div class="stat"><div class="k">With Email</div><div class="v" id="statEmail">0</div></div>
                </div>

                <div class="toolbar">
                    <div class="meta" id="resultMeta">Loading contacts...</div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Party</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>Tags</th>
                            <th>Quick Actions</th>
                        </tr>
                        </thead>
                        <tbody id="resultsBody">
                        <tr><td colspan="5" class="empty">Loading contacts...</td></tr>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
</div>

<script>
const payload = {!! $payloadJson !!};
const state = {
    type: 'C',
    q: '',
    contact_status: 'all',
    rows: [],
};

const els = {
    typeChips: document.getElementById('typeChips'),
    searchInput: document.getElementById('searchInput'),
    statusSelect: document.getElementById('statusSelect'),
    resultMeta: document.getElementById('resultMeta'),
    resultsBody: document.getElementById('resultsBody'),
    statTotal: document.getElementById('statTotal'),
    statMobile: document.getElementById('statMobile'),
    statPhone: document.getElementById('statPhone'),
    statEmail: document.getElementById('statEmail'),
    reloadBtn: document.getElementById('reloadBtn'),
    copyVisibleBtn: document.getElementById('copyVisibleBtn'),
};

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderTypeChips() {
    els.typeChips.innerHTML = payload.types.map(type => `
        <button class="chip ${state.type === type.code ? 'active' : ''}" type="button" data-type="${escapeHtml(type.code)}">
            ${escapeHtml(type.label)}
            <small>${escapeHtml(type.code)}</small>
        </button>
    `).join('');

    els.typeChips.querySelectorAll('[data-type]').forEach(btn => {
        btn.addEventListener('click', () => {
            state.type = btn.dataset.type;
            renderTypeChips();
            loadContacts();
        });
    });
}

function renderSummary(summary) {
    els.statTotal.textContent = String(summary.total ?? 0);
    els.statMobile.textContent = String(summary.with_mobile ?? 0);
    els.statPhone.textContent = String(summary.with_phone ?? 0);
    els.statEmail.textContent = String(summary.with_email ?? 0);
}

function rowAddress(row) {
    return [row.addr1, row.addr2, row.addr3, row.city].filter(Boolean).join(', ');
}

function renderRows(rows) {
    state.rows = rows;

    if (!rows.length) {
        els.resultsBody.innerHTML = '<tr><td colspan="5" class="empty">No contacts matched your filter.</td></tr>';
        return;
    }

    els.resultsBody.innerHTML = rows.map(row => `
        <tr>
            <td>
                <div class="name">${escapeHtml(row.name)}</div>
                <div class="sub">${escapeHtml(row.code)}</div>
            </td>
            <td>
                <div><strong>Mobile:</strong> ${escapeHtml(row.mobile || '-')}</div>
                <div><strong>Phone:</strong> ${escapeHtml(row.telephone || '-')}</div>
                <div><strong>Email:</strong> ${escapeHtml(row.email || '-')}</div>
            </td>
            <td>${escapeHtml(rowAddress(row) || '-')}</td>
            <td>
                <span class="badge type">${escapeHtml(row.type_label || 'Party')}</span>
                ${row.grp ? `<div class="sub">Group: ${escapeHtml(row.grp)}</div>` : ''}
                ${row.route ? `<div class="sub">Route: ${escapeHtml(row.route)}</div>` : ''}
                ${row.carea ? `<div class="sub">Area: ${escapeHtml(row.carea)}</div>` : ''}
            </td>
            <td>
                <div class="actions">
                    ${row.whatsapp_url ? `<a class="mini wa" href="${escapeHtml(row.whatsapp_url)}" target="_blank" rel="noopener">WhatsApp</a>` : ''}
                    ${row.tel_url ? `<a class="mini tel" href="${escapeHtml(row.tel_url)}">Call</a>` : ''}
                    ${row.mail_url ? `<a class="mini mail" href="${escapeHtml(row.mail_url)}">Email</a>` : ''}
                    <button class="mini" type="button" data-copy="${escapeHtml(row.contact || row.email || '')}">Copy</button>
                </div>
            </td>
        </tr>
    `).join('');

    els.resultsBody.querySelectorAll('[data-copy]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const value = btn.dataset.copy || '';
            if (!value) return;
            try {
                await navigator.clipboard.writeText(value);
                btn.textContent = 'Copied';
                setTimeout(() => { btn.textContent = 'Copy'; }, 1200);
            } catch (err) {
                alert('Unable to copy contact value.');
            }
        });
    });
}

async function loadContacts() {
    const params = new URLSearchParams({
        type: state.type,
        q: state.q,
        contact_status: state.contact_status,
    });

    els.resultMeta.textContent = 'Loading contacts...';
    els.resultsBody.innerHTML = '<tr><td colspan="5" class="empty">Loading contacts...</td></tr>';

    const res = await fetch(`{{ url('/api/phone-book/contacts') }}?${params.toString()}`, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
    });
    const json = await res.json();

    if (!json.ok) {
        els.resultMeta.textContent = json.message || 'Unable to load phone book.';
        els.resultsBody.innerHTML = '<tr><td colspan="5" class="empty">Unable to load phone book.</td></tr>';
        renderSummary({ total: 0, with_mobile: 0, with_phone: 0, with_email: 0 });
        return;
    }

    renderSummary(json.summary || {});
    renderRows(json.results || []);
    els.resultMeta.textContent = `${(json.results || []).length} contact(s) shown`;
}

function debounce(fn, wait) {
    let timer = null;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), wait);
    };
}

function bindEvents() {
    els.searchInput.addEventListener('input', debounce(() => {
        state.q = els.searchInput.value.trim();
        loadContacts();
    }, 250));

    els.statusSelect.addEventListener('change', () => {
        state.contact_status = els.statusSelect.value;
        loadContacts();
    });

    els.reloadBtn.addEventListener('click', () => loadContacts());

    els.copyVisibleBtn.addEventListener('click', async () => {
        const text = state.rows
            .map(row => row.mobile || row.telephone || '')
            .filter(Boolean)
            .join(', ');

        if (!text) {
            alert('No visible mobile or phone numbers to copy.');
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            els.copyVisibleBtn.textContent = 'Copied';
            setTimeout(() => { els.copyVisibleBtn.textContent = 'Copy Visible Numbers'; }, 1200);
        } catch (err) {
            alert('Unable to copy visible numbers.');
        }
    });

    document.querySelectorAll('[data-quick]').forEach(btn => {
        btn.addEventListener('click', () => {
            const mode = btn.dataset.quick;
            if (mode === 'customers') {
                state.type = 'C';
                state.contact_status = 'all';
            } else if (mode === 'missing-mobile') {
                state.type = 'C';
                state.contact_status = 'missing-mobile';
            } else if (mode === 'suppliers') {
                state.type = 'S';
                state.contact_status = 'all';
            } else if (mode === 'all-parties') {
                state.type = 'ALL';
                state.contact_status = 'any-contact';
            }

            els.statusSelect.value = state.contact_status;
            renderTypeChips();
            loadContacts();
        });
    });
}

renderTypeChips();
bindEvents();
renderSummary(payload.initialSummary || {});
loadContacts();
</script>
</body>
</html>
