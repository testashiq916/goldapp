<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Financial Statements</title>
    @include('partials.print-layout-head')
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:"Segoe UI",Tahoma,sans-serif;background:#f8f9fb;color:#1f2937}
        .wrap{max-width:1200px;margin:0 auto;padding:16px}
        h1{font-size:22px;color:#1a1a2e;margin-bottom:4px}
        .subtitle{font-size:12px;color:#6b7f9a;margin-bottom:12px}

        /* Tabs */
        .tabs{display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid #e3eaf4;padding-bottom:0}
        .tab{padding:8px 18px;font-size:13px;font-weight:600;color:#4a5568;border:1px solid transparent;border-bottom:none;border-radius:8px 8px 0 0;cursor:pointer;background:transparent;transition:all .15s}
        .tab:hover{background:#edf4fc;color:#1a1a2e}
        .tab.active{background:#fff;color:#1a1a2e;border-color:#d8e2ef;border-bottom:2px solid #fff;margin-bottom:-2px}

        /* Controls row */
        .controls{display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
        .controls select{height:32px;border:1px solid #bfd0e6;border-radius:6px;padding:0 8px;font-size:12px}
        .controls label{font-size:11px;font-weight:700;color:#375b84}
        .controls button{height:32px;border:1px solid #2a6398;border-radius:7px;background:#e8f2ff;color:#17456e;padding:0 14px;font-size:12px;font-weight:700;cursor:pointer}

        /* Table */
        .tbl-wrap{border:1px solid #d8e2ef;border-radius:8px;overflow-x:auto;background:#fff}
        table{width:100%;border-collapse:collapse;font-size:12.5px;min-width:700px}
        th{background:#f4f7fb;border-bottom:2px solid #d0daea;padding:8px 10px;text-align:right;font-size:11px;font-weight:700;color:#2d4f74;position:sticky;top:0;z-index:2}
        th:first-child{text-align:left;min-width:280px}
        td{border-bottom:1px solid #eef2f8;padding:6px 10px;text-align:right;color:#2d3748}
        td:first-child{text-align:left;color:#1a1a2e}

        /* Group header row */
        tr.grp-hdr td{font-weight:700;color:#1f3f66;background:#f8fbff;cursor:pointer;user-select:none}
        tr.grp-hdr td:first-child{padding-left:8px}
        tr.grp-hdr td:first-child::before{content:'\25BC ';font-size:10px;color:#6b7f9a}
        tr.grp-hdr.collapsed td:first-child::before{content:'\25B6 '}
        tr.grp-child td{color:#4a5568}
        tr.grp-child td:first-child{padding-left:28px;font-weight:400}
        tr.grp-child.hide{display:none}

        /* Summary rows */
        tr.summary td{font-weight:700;border-top:2px solid #d0daea;background:#f4f7fb;color:#1f3f66;font-size:13px}
        tr.summary.highlight td{background:#e6f8ec;color:#12522d;font-size:13.5px}

        /* Negative values */
        .neg{color:#c53030}

        /* Loading */
        .loading{text-align:center;padding:40px;color:#6b7f9a;font-size:14px}

        /* Exit button */
        .exit-btn{height:32px;border:1px solid #a23b3b;border-radius:7px;background:#fdeaea;color:#8e1f1f;padding:0 14px;font-size:12px;font-weight:700;cursor:pointer}

        @media(max-width:800px){th:first-child,td:first-child{min-width:180px}}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Financial Statements</h1>
    <div class="subtitle">All amounts in local currency. Data from accounting database.</div>

    <div class="tabs">
        <div class="tab active" data-tab="income">Income Statement</div>
        <div class="tab" data-tab="balance">Balance Sheet</div>
        <div class="tab" data-tab="cashflow">Cash Flow</div>
        <div class="tab" data-tab="trial">Trial Balance</div>
    </div>

    <div class="controls">
        <label>Period:</label>
        <select id="period">
            <option value="annual">Annual</option>
        </select>
        <label>Years:</label>
        <select id="yearCount">
            <option value="3">Last 3 Years</option>
            <option value="5" selected>Last 5 Years</option>
            <option value="7">Last 7 Years</option>
        </select>
        <button id="btnRefresh" type="button">Refresh</button>
        <div style="flex:1"></div>
        <button id="btnExit" type="button" class="exit-btn">Exit</button>
    </div>

    <div class="tbl-wrap">
        <div id="content" class="loading">Loading...</div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const BASE = @json(url('/api/reports/financial-statements'));

    let currentTab = new URLSearchParams(window.location.search).get('tab') || 'income';
    let allYears = [];

    function esc(v) { return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function fmtNum(v) {
        const n = Number(v) || 0;
        const abs = Math.abs(n);
        const formatted = abs.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (n < 0) return `<span class="neg">-${formatted}</span>`;
        if (n === 0) return '<span style="color:#aaa">&mdash;</span>';
        return formatted;
    }

    async function api(payload) {
        const res = await fetch(BASE, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!res.ok || data.success === false) throw new Error(data.error || 'Request failed');
        return data;
    }

    // ── Init ──
    async function init() {
        try {
            const d = await api({ action: 'init' });
            allYears = d.years || [];
            setActiveTab(currentTab);
            loadData();
        } catch (e) {
            document.getElementById('content').innerHTML = '<div class="loading">Init failed: ' + esc(e.message) + '</div>';
        }
    }

    function setActiveTab(tab) {
        currentTab = tab;
        document.querySelectorAll('.tab').forEach(t => {
            t.classList.toggle('active', t.dataset.tab === tab);
        });
    }

    // ── Load data ──
    async function loadData() {
        document.getElementById('content').innerHTML = '<div class="loading">Loading...</div>';

        const yearCount = parseInt(document.getElementById('yearCount').value) || 5;
        const years = allYears.slice(0, yearCount);

        try {
            const d = await api({
                action: 'data',
                tab: currentTab,
                period: document.getElementById('period').value,
                years: years,
            });
            renderTable(d);
        } catch (e) {
            document.getElementById('content').innerHTML = '<div class="loading">Error: ' + esc(e.message) + '</div>';
        }
    }

    // ── Render table ──
    function renderTable(data) {
        const years = data.years || [];
        if (years.length === 0) {
            document.getElementById('content').innerHTML = '<div class="loading">No data available</div>';
            return;
        }

        let html = '<table><thead><tr><th>Name</th>';
        years.forEach(y => { html += `<th>${y}</th>`; });
        html += '</tr></thead><tbody>';

        // Sections with expandable groups
        (data.sections || []).forEach((sec, si) => {
            if (sec.rows && sec.rows.length > 0) {
                // Section header
                html += `<tr class="grp-hdr" data-section="${si}" data-grp="section-${si}"><td colspan="${years.length + 1}" style="font-size:13px;color:#1f3f66;background:#edf4fc;">${esc(sec.label)}</td></tr>`;

                sec.rows.forEach((grp, gi) => {
                    const grpId = `s${si}g${gi}`;
                    // Group header with totals
                    html += `<tr class="grp-hdr" data-grp="${grpId}"><td>${esc(grp.group)}</td>`;
                    years.forEach(y => { html += `<td>${fmtNum(grp.totals[y])}</td>`; });
                    html += '</tr>';

                    // Children (accounts)
                    (grp.children || []).forEach(ch => {
                        html += `<tr class="grp-child hide" data-parent="${grpId}"><td>${esc(ch.name)} <span style="color:#aaa;font-size:10px;">${esc(ch.code)}</span></td>`;
                        years.forEach(y => { html += `<td>${fmtNum(ch.values[y])}</td>`; });
                        html += '</tr>';
                    });
                });

                // Section total
                html += `<tr class="summary"><td>Total ${esc(sec.label)}</td>`;
                years.forEach(y => { html += `<td>${fmtNum(sec.totals[y])}</td>`; });
                html += '</tr>';
            }
        });

        // Summary rows
        (data.summary || []).forEach(row => {
            const cls = row.highlight ? 'summary highlight' : 'summary';
            html += `<tr class="${cls}"><td>${esc(row.label)}</td>`;
            years.forEach(y => { html += `<td>${fmtNum(row.values[y])}</td>`; });
            html += '</tr>';
        });

        html += '</tbody></table>';
        document.getElementById('content').innerHTML = html;

        // Add expand/collapse behavior
        document.querySelectorAll('tr.grp-hdr[data-grp]').forEach(tr => {
            tr.addEventListener('click', () => {
                const grpId = tr.dataset.grp;
                if (grpId.startsWith('section-')) return; // section headers don't toggle
                const isCollapsed = tr.classList.toggle('collapsed');
                document.querySelectorAll(`tr.grp-child[data-parent="${grpId}"]`).forEach(child => {
                    child.classList.toggle('hide', isCollapsed);
                });
            });
        });
    }

    // ── Tab clicks ──
    document.querySelectorAll('.tab').forEach(t => {
        t.addEventListener('click', () => {
            setActiveTab(t.dataset.tab);
            loadData();
        });
    });

    // ── Controls ──
    document.getElementById('btnRefresh').addEventListener('click', loadData);
    document.getElementById('yearCount').addEventListener('change', loadData);

    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else { window.close(); }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            document.getElementById('btnExit').click();
        }
    });

    // ── Start ──
    init();
})();
</script>
</body>
</html>
