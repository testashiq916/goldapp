<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Initialise Doc. No.</title>
<style>
*,*::before,*::after{box-sizing:border-box} html,body{margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f3f5f8;color:#172033;font-size:13px}
.page-header{background:#fff;border-bottom:1px solid #dbe2ea;padding:14px 20px;display:flex;align-items:center;gap:12px}
.badge{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#c9a84c,#8d6a22);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
.title{font-size:20px;font-weight:700}.subtitle{color:#667085;font-size:12px;margin-top:3px}
.page{max-width:980px;padding:24px 20px 32px}
.panel{background:#fff;border:1px solid #dbe2ea;border-radius:18px;padding:24px;box-shadow:0 8px 22px rgba(15,23,42,.04)}
.layout{display:grid;grid-template-columns:1.35fr .75fr;gap:28px}
.checks{display:grid;gap:14px}
.check{display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:1px solid #dbe2ea;border-radius:12px;background:#fbfcfd}
.check input{margin-top:2px;width:16px;height:16px}
.check label{cursor:pointer;font-size:14px;line-height:1.35}
.actions{display:grid;gap:14px;align-content:start}
.btn{appearance:none;border:none;border-radius:14px;padding:14px 16px;font:inherit;font-weight:700;cursor:pointer}
.btn.primary{background:#173a63;color:#fff}.btn.exit{background:#c81e12;color:#fff}
.status{margin-top:10px;display:none;padding:12px 14px;border-radius:10px}
.status.show{display:block}.status.ok{background:#ecfdf3;border:1px solid #abefc6;color:#067647}.status.err{background:#fef3f2;border:1px solid #fecdca;color:#b42318}
@media (max-width:860px){.layout{grid-template-columns:1fr}}
</style>
</head>
<body>
    <div class="page-header">
        <div class="badge">D</div>
        <div>
            <div class="title">Initialise Doc. No.</div>
            <div class="subtitle">Legacy `w_initialisedocno` window migrated for the web app.</div>
        </div>
    </div>

    <main class="page">
        <form class="panel" id="docNoForm">
            @csrf
            <div class="layout">
                <div class="checks">
                    <label class="check"><input id="initsbillno" name="initsbillno" type="checkbox"><span>Initialise Sales Bill Number</span></label>
                    <label class="check"><input id="initpbillno" name="initpbillno" type="checkbox"><span>Initialise Purchase Bill Number</span></label>
                    <label class="check"><input id="initgsbillno" name="initgsbillno" type="checkbox"><span>Initialise Smith Bill Number</span></label>
                    <label class="check"><input id="initrefbillno" name="initrefbillno" type="checkbox"><span>Initialise Refinery Bill Number</span></label>
                    <label class="check"><input id="initordbillno" name="initordbillno" type="checkbox"><span>Initialise Order Bill Number</span></label>
                    <label class="check"><input id="initrepbillno" name="initrepbillno" type="checkbox"><span>Initialise Repair Bill Number</span></label>
                </div>

                <div class="actions">
                    <button class="btn primary" id="doneBtn" type="submit">Done</button>
                    <button class="btn exit" id="exitBtn" type="button">Exit</button>
                    <div class="status" id="statusBox"></div>
                </div>
            </div>
        </form>
    </main>

<script>
const form = document.getElementById('docNoForm');
const statusBox = document.getElementById('statusBox');

function showStatus(message, isError) {
    statusBox.textContent = message;
    statusBox.className = 'status show ' + (isError ? 'err' : 'ok');
}

form.addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!confirm('You are going to initialise Bill Number. Please confirm.')) return;

    const btn = document.getElementById('doneBtn');
    btn.disabled = true;
    btn.textContent = 'Processing...';
    statusBox.className = 'status';

    try {
        const payload = new FormData(form);
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch('{{ url('/api/administration/initialise-docno') }}', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: payload
        });
        const data = await res.json();
        showStatus(data.message || (data.ok ? 'Done' : 'Failed'), !data.ok);
    } catch (err) {
        showStatus('Initialise Doc. No. request failed.', true);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Done';
    }
});

document.getElementById('exitBtn').addEventListener('click', function() {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        return;
    }
    window.location.href = '{{ url('/administration') }}';
});
</script>
</body>
</html>
