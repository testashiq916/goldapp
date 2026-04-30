<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Rearange Docnos</title>
<style>
*,*::before,*::after{box-sizing:border-box}html,body{margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f3f5f8;color:#172033;font-size:13px}
.page-header{background:#fff;border-bottom:1px solid #dbe2ea;padding:14px 20px;display:flex;align-items:center;gap:12px}
.badge{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#c9a84c,#8d6a22);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
.title{font-size:20px;font-weight:700}.subtitle{color:#667085;font-size:12px;margin-top:3px}
.page{max-width:1180px;padding:24px 20px 32px}
.panel{background:#fff;border:1px solid #dbe2ea;border-radius:18px;padding:24px;box-shadow:0 8px 22px rgba(15,23,42,.04)}
.bar{display:grid;grid-template-columns:220px 180px 1fr auto auto;gap:12px;align-items:end;margin-bottom:20px}
.field label{display:block;font-weight:700;margin-bottom:6px}.field input{width:100%;height:40px;border:1px solid #cfd8e3;border-radius:10px;padding:0 12px}
.toggle{display:flex;align-items:center;gap:10px;font-weight:700}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:14px}
.card{border:1px solid #dbe2ea;border-radius:14px;padding:16px;background:#fbfcfd}
.card h3{margin:0 0 12px;font-size:15px}
.row{display:grid;grid-template-columns:1fr 110px;gap:10px;align-items:center}
.row + .row{margin-top:10px}
.row input[type="number"]{height:38px;border:1px solid #cfd8e3;border-radius:10px;padding:0 10px}
.row input[type="checkbox"]{width:16px;height:16px}
.inline{display:flex;align-items:center;gap:10px;font-weight:600}
.btn{appearance:none;border:none;border-radius:12px;padding:12px 16px;font:inherit;font-weight:700;cursor:pointer}
.btn.primary{background:#173a63;color:#fff}.btn.secondary{background:#fff;color:#173a63;border:1px solid #c8d4e0}
.status{margin-top:16px;display:none;padding:12px 14px;border-radius:10px}
.status.show{display:block}.status.ok{background:#ecfdf3;border:1px solid #abefc6;color:#067647}.status.err{background:#fef3f2;border:1px solid #fecdca;color:#b42318}
.summary{margin-top:12px;padding:12px 14px;border-radius:12px;background:#f8fafc;border:1px solid #dbe2ea;display:none}
.summary.show{display:block}
.summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-top:8px}
.pill{display:inline-flex;align-items:center;justify-content:center;padding:6px 9px;border-radius:999px;background:#eef4ff;color:#175cd3;font-weight:700}
@media (max-width:860px){.bar{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="page-header">
    <div class="badge">R</div>
    <div>
        <div class="title">Rearange Docnos</div>
        <div class="subtitle">Legacy `w_rearangevouchernos` window migrated for document and voucher renumbering.</div>
    </div>
</div>

<main class="page">
    <form class="panel" id="rearrangeForm">
        @csrf
        <div class="bar">
            <div class="field">
                <label for="from_date">From Date</label>
                <input id="from_date" name="from_date" type="date" value="{{ $today }}">
            </div>
            <div class="field">
                <label for="estimates_only">Mode</label>
                <label class="toggle"><input id="estimates_only" name="estimates_only" type="checkbox"> Estimates Only</label>
            </div>
            <div></div>
            <button class="btn primary" type="submit" id="okBtn">Ok</button>
            <button class="btn secondary" type="button" id="exitBtn">Exit</button>
        </div>

        <div class="grid">
            <section class="card">
                <h3>Sales</h3>
                <div class="row"><label class="inline"><input type="checkbox" name="sales_enabled"> Rearange Sales No</label><input type="number" name="sales_start" min="1" value="1"></div>
                <div class="row"><label class="inline"><input type="checkbox" name="sales_return_enabled"> Rearange SReturn No</label><input type="number" name="sales_return_start" min="1" value="1"></div>
                <div class="row"><label class="inline"><input type="checkbox" name="order_enabled"> Rearange Order No</label><input type="number" name="order_start" min="1" value="1"></div>
            </section>

            <section class="card">
                <h3>Purchase</h3>
                <div class="row"><label class="inline"><input type="checkbox" name="purchase_enabled"> Rearange Purchase No</label><input type="number" name="purchase_start" min="1" value="1"></div>
                <div class="row"><label class="inline"><input type="checkbox" name="diamond_purchase_enabled"> Rearange Dmd Purchase No</label><input type="number" name="diamond_purchase_start" min="1" value="1"></div>
                <div class="row"><label class="inline"><input type="checkbox" name="purchase_return_enabled"> Rearange PReturn No</label><input type="number" name="purchase_return_start" min="1" value="1"></div>
                <div class="row"><label class="inline"><input type="checkbox" name="refinery_enabled"> Rearange Refinery No</label><input type="number" name="refinery_start" min="1" value="1"></div>
            </section>

            <section class="card">
                <h3>Smith</h3>
                <div class="row"><label class="inline"><input type="checkbox" name="smith_issue_enabled"> Rearange Smith Issue No</label><input type="number" name="smith_issue_start" min="1" value="1"></div>
                <div class="row"><label class="inline"><input type="checkbox" name="smith_receipt_enabled"> Rearange Smith Rcpt No</label><input type="number" name="smith_receipt_start" min="1" value="1"></div>
                <div class="row"><label class="inline"><input type="checkbox" name="smith_trn_based"> Trn Based</label><span></span></div>
            </section>

            <section class="card">
                <h3>Jewellery</h3>
                <div class="row"><label class="inline"><input type="checkbox" name="jewl_issue_enabled"> Rearange Jewl Issue No</label><input type="number" name="jewl_issue_start" min="1" value="1"></div>
                <div class="row"><label class="inline"><input type="checkbox" name="jewl_receipt_enabled"> Rearange Jewl Rcpt No</label><input type="number" name="jewl_receipt_start" min="1" value="1"></div>
                <div class="row"><label class="inline"><input type="checkbox" name="jewl_trn_based"> Trn Based</label><span></span></div>
            </section>

            <section class="card">
                <h3>Repair</h3>
                <div class="row"><label class="inline"><input type="checkbox" name="repair_receipt_enabled"> Rearange Repair Rcpt No</label><input type="number" name="repair_receipt_start" min="1" value="1"></div>
                <div class="row"><label class="inline"><input type="checkbox" name="repair_return_enabled"> Rearange Repair Return No</label><input type="number" name="repair_return_start" min="1" value="1"></div>
            </section>

            <section class="card">
                <h3>Voucher Nos</h3>
                <div class="row"><label class="inline"><input type="checkbox" name="receipt_enabled"> Rearange Rcpt No</label><input type="number" name="receipt_start" min="1" value="1"></div>
                <div class="row"><label class="inline"><input type="checkbox" name="payment_enabled"> Rearange Pmnt No</label><input type="number" name="payment_start" min="1" value="1"></div>
                <div class="row"><label class="inline"><input type="checkbox" name="journal_enabled"> Rearange Journal No</label><input type="number" name="journal_start" min="1" value="1"></div>
            </section>
        </div>

        <div class="status" id="statusBox"></div>
        <div class="summary" id="summaryBox">
            <strong>Updated Counts</strong>
            <div class="summary-grid" id="summaryGrid"></div>
        </div>
    </form>
</main>

<script>
const form = document.getElementById('rearrangeForm');
const statusBox = document.getElementById('statusBox');
const summaryBox = document.getElementById('summaryBox');
const summaryGrid = document.getElementById('summaryGrid');

function showStatus(message, isError) {
    statusBox.textContent = message;
    statusBox.className = 'status show ' + (isError ? 'err' : 'ok');
}

function showSummary(summary) {
    const entries = Object.entries(summary || {}).filter(([, value]) => Number(value) > 0);
    if (!entries.length) {
        summaryBox.className = 'summary';
        summaryGrid.innerHTML = '';
        return;
    }
    summaryGrid.innerHTML = entries.map(([key, value]) => {
        const label = key.replace(/_/g, ' ');
        return `<div class="pill">${label}: ${value}</div>`;
    }).join('');
    summaryBox.className = 'summary show';
}

form.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!confirm('You are going to rearrange document and voucher numbers. Please confirm.')) return;

    const btn = document.getElementById('okBtn');
    btn.disabled = true;
    btn.textContent = 'Processing...';
    statusBox.className = 'status';
    summaryBox.className = 'summary';

    try {
        const payload = new FormData(form);
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch('{{ url('/api/administration/rearrange-docnos') }}', {
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
        showSummary(data.summary || {});
    } catch (err) {
        showStatus('Rearange Docnos request failed.', true);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Ok';
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
