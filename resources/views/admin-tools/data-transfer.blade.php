<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Data Transfer</title>
<style>
*,*::before,*::after{box-sizing:border-box}html,body{margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f3f5f8;color:#172033;font-size:13px}
.page-header{background:#fff;border-bottom:1px solid #dbe2ea;padding:14px 20px;display:flex;align-items:center;gap:12px}
.badge{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#2d6a9f,#153b63);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
.title{font-size:20px;font-weight:700}.subtitle{color:#667085;font-size:12px;margin-top:3px}
.page{max-width:1120px;padding:24px 20px 32px}
.panel{background:#fff;border:1px solid #dbe2ea;border-radius:18px;padding:22px;box-shadow:0 8px 22px rgba(15,23,42,.04)}
.top-grid{display:grid;grid-template-columns:1.1fr 1.1fr .8fr .8fr;gap:14px;margin-bottom:18px}
.field{display:flex;flex-direction:column;gap:6px}
.field label{font-size:12px;font-weight:700;color:#375b84}
.field input,.field select{height:44px;border:1px solid #cfd8e3;border-radius:12px;padding:0 14px;font:inherit;background:#fff}
.layout{display:grid;grid-template-columns:1.05fr .95fr;gap:18px}
.group{border:1px solid #dbe2ea;border-radius:16px;padding:18px;background:#fbfcfd}
.group h2{margin:0 0 12px;font-size:16px}
.option-sections{display:grid;gap:14px}
.option-section{border:1px solid #dbe2ea;border-radius:14px;padding:14px;background:#f7fafc}
.option-section h3{margin:0 0 10px;font-size:13px;color:#173a63}
.option-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.check{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid #dbe2ea;border-radius:12px;background:#fff}
.check input{width:18px;height:18px}
.helper{margin-top:12px;font-size:12px;color:#667085;line-height:1.5}
.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
.btn{appearance:none;border:none;border-radius:12px;padding:12px 18px;font:inherit;font-weight:700;cursor:pointer}
.btn.primary{background:#173a63;color:#fff}
.btn.secondary{background:#e6f8ec;color:#1b5b31;border:1px solid #9bd0aa}
.btn.ghost{background:#fff;color:#173a63;border:1px solid #c8d4e0}
.status{margin-top:16px;display:none;padding:12px 14px;border-radius:12px;font-weight:600}
.status.show{display:block}.status.ok{background:#ecfdf3;border:1px solid #abefc6;color:#067647}.status.err{background:#fef3f2;border:1px solid #fecdca;color:#b42318}
.result-box{border:1px solid #dbe2ea;border-radius:16px;background:#fbfcfd;padding:18px;min-height:320px}
.result-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:12px}
.muted{color:#667085}
.summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:14px}
.summary-card{border:1px solid #dbe2ea;border-radius:12px;background:#fff;padding:12px}
.summary-card .lbl{font-size:11px;color:#667085;text-transform:uppercase;letter-spacing:.03em}
.summary-card .val{margin-top:4px;font-size:18px;font-weight:700;color:#173a63}
.table-wrap{border:1px solid #dbe2ea;border-radius:12px;overflow:auto;max-height:380px;background:#fff}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border-bottom:1px solid #e5ecf5;padding:8px 10px;text-align:left;vertical-align:top}
th{position:sticky;top:0;background:#edf4fc;z-index:1}
td.num,th.num{text-align:right}
.empty{padding:28px 12px;text-align:center;color:#667085}
@media (max-width:980px){.top-grid,.layout,.option-grid,.summary{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="page-header">
    <div class="badge">D</div>
    <div>
        <div class="title">Data Transfer</div>
        <div class="subtitle">Cross company transfer screen rebuilt with a simple checklist layout.</div>
    </div>
</div>

<main class="page">
    <section class="panel">
        <div class="top-grid">
            <div class="field">
                <label for="sourceDatabase">From Current Company DB</label>
                <input id="sourceDatabase" type="text" value="{{ $currentDatabase }}" readonly>
            </div>
            <div class="field">
                <label for="targetDatabase">To Company DB</label>
                <select id="targetDatabase">
                    <option value="">Select target company</option>
                    @foreach($companies as $company)
                        <option value="{{ $company['database'] }}" {{ $defaultTarget === $company['database'] ? 'selected' : '' }}>
                            {{ $company['company_name'] !== '' ? $company['company_name'] . ' [' . $company['database'] . ']' : $company['database'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="dateFrom">From Date</label>
                <input id="dateFrom" type="date" value="{{ $today }}">
            </div>
            <div class="field">
                <label for="dateTo">To Date</label>
                <input id="dateTo" type="date" value="{{ $today }}">
            </div>
        </div>

        <div class="layout">
            <div class="group">
                <h2>Transfer Options</h2>
                <div class="option-sections">
                    @foreach($quickOptions as $group)
                        <section class="option-section">
                            <h3>{{ $group['title'] }}</h3>
                            <div class="option-grid">
                                @foreach($group['options'] as $option)
                                    <label class="check">
                                        <input type="checkbox" class="module-check" value="{{ $option['key'] }}" checked>
                                        <span>{{ $option['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>

                <div class="helper">
                    Copy the selected items from the current company database into the selected target company database. Preview shows current-company row counts first; Transfer copies the selected modules.
                </div>

                <div class="actions">
                    <button class="btn ghost" type="button" id="selectAllBtn">Select All</button>
                    <button class="btn ghost" type="button" id="clearBtn">Clear All</button>
                    <button class="btn secondary" type="button" id="previewBtn">Preview</button>
                    <button class="btn primary" type="button" id="runBtn">Transfer</button>
                    <button class="btn ghost" type="button" id="exitBtn">Exit</button>
                </div>

                <div class="status" id="statusBox"></div>
            </div>

            <div class="result-box">
                <div class="result-head">
                    <div>
                        <h2 style="margin:0 0 4px;font-size:16px;">Preview / Result</h2>
                        <div class="muted">Counts and transfer status for the selected options.</div>
                    </div>
                </div>

                <div class="summary">
                    <div class="summary-card">
                        <div class="lbl">Modules</div>
                        <div class="val" id="summaryModules">0</div>
                    </div>
                    <div class="summary-card">
                        <div class="lbl">Rows</div>
                        <div class="val" id="summaryRows">0</div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Option</th>
                            <th>Status</th>
                            <th class="num">Rows</th>
                            <th>Message</th>
                        </tr>
                        </thead>
                        <tbody id="resultBody">
                        <tr><td colspan="4" class="empty">Choose options, then click Preview or Transfer.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const statusBox = document.getElementById('statusBox');
const resultBody = document.getElementById('resultBody');
const summaryModules = document.getElementById('summaryModules');
const summaryRows = document.getElementById('summaryRows');
const presetModules = @json($presetModules ?? []);

if (Array.isArray(presetModules) && presetModules.length) {
    const selected = new Set(presetModules.map(value => String(value)));
    document.querySelectorAll('.module-check').forEach(el => {
        el.checked = selected.has(String(el.value || ''));
    });
}

function selectedModules() {
    return Array.from(document.querySelectorAll('.module-check:checked')).map(el => el.value);
}

function setStatus(message, ok) {
    statusBox.textContent = message;
    statusBox.className = 'status show ' + (ok ? 'ok' : 'err');
}

function renderResults(items, summary, mode) {
    const rows = Array.isArray(items) ? items : [];
    summaryModules.textContent = String(summary?.modules ?? rows.length ?? 0);
    summaryRows.textContent = String(summary?.rows ?? summary?.copied ?? 0);

    if (!rows.length) {
        resultBody.innerHTML = '<tr><td colspan="4" class="empty">No rows found for the current selection.</td></tr>';
        return;
    }

    resultBody.innerHTML = rows.map(row => {
        const count = mode === 'run' ? (row.copied ?? 0) : (row.rows ?? 0);
        const status = row.status || (mode === 'run' ? 'done' : 'ready');
        const message = row.message || '';
        return `<tr>
            <td>${escapeHtml(row.label || row.module || '')}</td>
            <td>${escapeHtml(status)}</td>
            <td class="num">${escapeHtml(String(count))}</td>
            <td>${escapeHtml(message)}</td>
        </tr>`;
    }).join('');
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function payload() {
    return {
        target_database: document.getElementById('targetDatabase').value,
        date_from: document.getElementById('dateFrom').value,
        date_to: document.getElementById('dateTo').value,
        modules: selectedModules(),
    };
}

async function postJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: JSON.stringify(body),
    });
    return await res.json();
}

document.getElementById('selectAllBtn').addEventListener('click', function () {
    document.querySelectorAll('.module-check').forEach(el => { el.checked = true; });
});

document.getElementById('clearBtn').addEventListener('click', function () {
    document.querySelectorAll('.module-check').forEach(el => { el.checked = false; });
    resultBody.innerHTML = '<tr><td colspan="4" class="empty">Choose options, then click Preview or Transfer.</td></tr>';
    summaryModules.textContent = '0';
    summaryRows.textContent = '0';
    statusBox.className = 'status';
    statusBox.textContent = '';
});

document.getElementById('previewBtn').addEventListener('click', async function () {
    try {
        const out = await postJson('{{ url('/api/administration/data-transfer/preview') }}', payload());
        if (!out.ok) {
            setStatus(out.message || 'Preview failed.', false);
            return;
        }
        renderResults(out.items, out.summary, 'preview');
        setStatus('Preview loaded successfully.', true);
    } catch (err) {
        setStatus('Preview request failed: ' + err.message, false);
    }
});

document.getElementById('runBtn').addEventListener('click', async function () {
    if (!confirm('You are going to transfer the selected data. Do you want to continue?')) return;
    try {
        const out = await postJson('{{ url('/api/administration/data-transfer/run') }}', payload());
        if (!out.ok) {
            setStatus(out.message || 'Transfer failed.', false);
            return;
        }
        renderResults(out.items, out.summary, 'run');
        setStatus(out.message || 'Transfer completed.', true);
    } catch (err) {
        setStatus('Transfer request failed: ' + err.message, false);
    }
});

document.getElementById('exitBtn').addEventListener('click', function () {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        return;
    }
    window.location.href = '{{ url('/administration') }}';
});
</script>
</body>
</html>
