<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>SQL Updt</title>
<style>
*,*::before,*::after{box-sizing:border-box}html,body{margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f3f5f8;color:#172033;font-size:13px}
.page-header{background:#fff;border-bottom:1px solid #dbe2ea;padding:14px 20px;display:flex;align-items:center;gap:12px}
.badge{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#c96a33,#7f3f0a);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
.title{font-size:20px;font-weight:700}.subtitle{color:#667085;font-size:12px;margin-top:3px}
.page{max-width:1180px;padding:24px 20px 32px}
.layout{display:grid;grid-template-columns:1.25fr .9fr;gap:18px}
.panel{background:#fff;border:1px solid #dbe2ea;border-radius:18px;padding:22px;box-shadow:0 8px 22px rgba(15,23,42,.04)}
.panel h2{margin:0 0 8px;font-size:16px}
.panel p{margin:0 0 18px;color:#667085;line-height:1.5}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
.card{border:1px solid #dbe2ea;border-radius:14px;background:#fbfcfd;padding:16px;display:flex;flex-direction:column;gap:10px}
.card h3{margin:0;font-size:15px}
.meta{display:flex;gap:8px;flex-wrap:wrap}
.pill{display:inline-flex;align-items:center;justify-content:center;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:700}
.pill.cat{background:#eef4ff;color:#175cd3}.pill.low{background:#ecfdf3;color:#067647}.pill.medium{background:#fff8e8;color:#b54708}.pill.high{background:#fef3f2;color:#b42318}
.desc{color:#667085;line-height:1.45;flex:1}
.btn{appearance:none;border:none;border-radius:10px;padding:10px 14px;background:#173a63;color:#fff;font-weight:700;cursor:pointer}
.btn.secondary{background:#fff;color:#173a63;border:1px solid #c8d4e0}
.helper-list{display:grid;gap:12px}
.helper-item{border:1px solid #dbe2ea;border-radius:12px;padding:14px;background:#fbfcfd}
.helper-issue{font-weight:700;margin-bottom:6px}
.helper-action{color:#175cd3;font-weight:700;margin-bottom:5px}
.status{margin-top:16px;display:none;padding:12px 14px;border-radius:10px}
.status.show{display:block}.status.ok{background:#ecfdf3;border:1px solid #abefc6;color:#067647}.status.err{background:#fef3f2;border:1px solid #fecdca;color:#b42318}
.footer{margin-top:16px;display:flex;justify-content:flex-end;gap:12px}
@media (max-width:920px){.layout{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="page-header">
    <div class="badge">Q</div>
    <div>
        <div class="title">SQL Updt</div>
        <div class="subtitle">Legacy `w_sqlupdt` utility migrated as a guided maintenance workspace.</div>
    </div>
</div>

<main class="page">
    <div class="layout">
        <section class="panel">
            <h2>Maintenance Actions</h2>
            <p>This first web port focuses on the safer, high-value data-fix actions from the legacy SQL utility. Schema-alter and import-heavy tools can be added next in the same screen after we validate these.</p>

            <div class="grid">
                @foreach($actions as $action)
                    <article class="card">
                        <h3>{{ $action['label'] }}</h3>
                        <div class="meta">
                            <span class="pill cat">{{ $action['category'] }}</span>
                            <span class="pill {{ $action['risk'] }}">{{ strtoupper($action['risk']) }} RISK</span>
                        </div>
                        <div class="desc">{{ $action['description'] }}</div>
                        @if(!empty($action['requires_doc_no']))
                            <input
                                type="text"
                                class="action-doc-no"
                                data-key="{{ $action['key'] }}"
                                placeholder="{{ $action['doc_no_label'] ?? 'Doc No' }}"
                                aria-label="{{ $action['doc_no_label'] ?? 'Doc No' }}"
                            >
                        @endif
                        <button class="btn run-action" type="button" data-key="{{ $action['key'] }}" data-label="{{ $action['label'] }}">Run</button>
                    </article>
                @endforeach
            </div>

            <div class="status" id="statusBox"></div>

            <div class="footer">
                <button class="btn secondary" type="button" id="exitBtn">Exit</button>
            </div>
        </section>

        <aside class="panel">
            <h2>AI Helper</h2>
            <p>This helper suggests which SQL maintenance action fits a problem. It is guidance-first, so users can sanity-check before running a fix.</p>

            <div class="helper-list">
                @foreach($aiRecommendations as $item)
                    <div class="helper-item">
                        <div class="helper-issue">{{ $item['issue'] }}</div>
                        <div class="helper-action">Recommended: {{ collect($actions)->firstWhere('key', $item['recommended_action'])['label'] ?? $item['recommended_action'] }}</div>
                        <div class="desc">{{ $item['why'] }}</div>
                    </div>
                @endforeach
            </div>
        </aside>
    </div>
</main>

<script>
const statusBox = document.getElementById('statusBox');

function showStatus(message, isError) {
    statusBox.textContent = message;
    statusBox.className = 'status show ' + (isError ? 'err' : 'ok');
}

document.querySelectorAll('.run-action').forEach(function (button) {
    button.addEventListener('click', async function () {
        const key = button.dataset.key;
        const label = button.dataset.label;
        if (!confirm('You are going to run "' + label + '". Do you want to continue?')) return;

        button.disabled = true;
        const oldText = button.textContent;
        button.textContent = 'Running...';
        statusBox.className = 'status';

        try {
            const payload = new FormData();
            payload.append('action', key);
            const docNoInput = document.querySelector('.action-doc-no[data-key="' + key + '"]');
            if (docNoInput) {
                payload.append('doc_no', docNoInput.value || '');
            }
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const res = await fetch('{{ url('/api/administration/sql-update') }}', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: payload
            });
            const data = await res.json();
            let message = data.message || (data.ok ? 'Completed.' : 'Failed');
            if (data.ok && data.summary) {
                const parts = Object.entries(data.summary).map(([k, v]) => k + ': ' + v);
                if (parts.length) message += ' [' + parts.join(', ') + ']';
            }
            showStatus(message, !data.ok);
        } catch (err) {
            showStatus('SQL Updt request failed.', true);
        } finally {
            button.disabled = false;
            button.textContent = oldText;
        }
    });
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
