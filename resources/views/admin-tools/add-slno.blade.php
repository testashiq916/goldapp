<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Update Slno</title>
<style>
*,*::before,*::after{box-sizing:border-box}html,body{margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f3f5f8;color:#172033;font-size:13px}
.page-header{background:#fff;border-bottom:1px solid #dbe2ea;padding:14px 20px;display:flex;align-items:center;gap:12px}
.badge{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#c9a84c,#8d6a22);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
.title{font-size:20px;font-weight:700}.subtitle{color:#667085;font-size:12px;margin-top:3px}
.page{max-width:860px;padding:24px 20px 32px}
.panel{background:#fff;border:1px solid #dbe2ea;border-radius:18px;padding:24px;box-shadow:0 8px 22px rgba(15,23,42,.04)}
.grid{display:grid;grid-template-columns:180px 1fr;gap:16px 18px;align-items:center}
.label{font-weight:700;text-align:right}
.field input{width:100%;height:42px;border:1px solid #cfd8e3;border-radius:10px;padding:0 12px;font:inherit}
.field input.readonly{background:#eceff3;color:#475467}
.hint{margin:0 0 18px;color:#667085;line-height:1.5}
.actions{display:flex;justify-content:center;gap:12px;margin-top:24px;flex-wrap:wrap}
.btn{appearance:none;border:none;border-radius:12px;padding:12px 18px;font:inherit;font-weight:700;cursor:pointer}
.btn.primary{background:#173a63;color:#fff}.btn.secondary{background:#fff;color:#173a63;border:1px solid #c8d4e0}
.status{margin-top:18px;display:none;padding:12px 14px;border-radius:10px}
.status.show{display:block}.status.ok{background:#ecfdf3;border:1px solid #abefc6;color:#067647}.status.err{background:#fef3f2;border:1px solid #fecdca;color:#b42318}
@media (max-width:640px){.page{padding:16px 14px 24px}.grid{grid-template-columns:1fr}.label{text-align:left}}
</style>
</head>
<body>
<div class="page-header">
    <div class="badge">S</div>
    <div>
        <div class="title">Update Slno</div>
        <div class="subtitle">Legacy `w_slnoupdt` window migrated for bulk serial-number shifting.</div>
    </div>
</div>

<main class="page">
    <form class="panel" id="addSlnoForm">
        @csrf
        <p class="hint">Increase `slno` values for records from a selected date onward. This is intended for new database alignment work and will update all linked transaction tables together.</p>

        <div class="grid">
            <div class="label">From Date :</div>
            <div class="field"><input id="from_date" name="from_date" type="date" value="{{ $today }}"></div>

            <div class="label">Max Slno :</div>
            <div class="field"><input class="readonly" id="max_slno" name="max_slno" type="number" value="{{ $max_slno }}" readonly></div>

            <div class="label">Min Slno :</div>
            <div class="field"><input class="readonly" id="min_slno" name="min_slno" type="number" value="{{ $min_slno }}" readonly></div>

            <div class="label">Add Slno :</div>
            <div class="field"><input id="add_slno" name="add_slno" type="number" min="1" step="1" value=""></div>
        </div>

        <div class="actions">
            <button class="btn primary" type="submit" id="updateBtn">Update</button>
            <button class="btn secondary" type="button" id="exitBtn">Exit</button>
        </div>

        <div class="status" id="statusBox"></div>
    </form>
</main>

<script>
const form = document.getElementById('addSlnoForm');
const statusBox = document.getElementById('statusBox');

function showStatus(message, isError) {
    statusBox.textContent = message;
    statusBox.className = 'status show ' + (isError ? 'err' : 'ok');
}

form.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!confirm('You are going to Increase Slno for New Database. Do you want to continue?')) return;

    const btn = document.getElementById('updateBtn');
    btn.disabled = true;
    btn.textContent = 'Updating...';
    statusBox.className = 'status';

    try {
        const payload = new FormData(form);
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch('{{ url('/api/administration/add-slno') }}', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: payload
        });
        const data = await res.json();
        showStatus(data.message || (data.ok ? 'Updated.' : 'Failed'), !data.ok);
        if (data.ok && data.data) {
            document.getElementById('max_slno').value = data.data.max_slno || 0;
            document.getElementById('min_slno').value = data.data.min_slno || 0;
        }
    } catch (err) {
        showStatus('Update Slno request failed.', true);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Update';
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
