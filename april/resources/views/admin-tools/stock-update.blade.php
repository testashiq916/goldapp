<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Update Stock</title>
<style>
*,*::before,*::after{box-sizing:border-box} html,body{margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f3f5f8;color:#172033;font-size:13px}
.page-header{background:#fff;border-bottom:1px solid #dbe2ea;padding:14px 20px;display:flex;align-items:center;gap:12px}
.badge{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#c9a84c,#8d6a22);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
.title{font-size:20px;font-weight:700}.subtitle{color:#667085;font-size:12px;margin-top:3px}
.page{max-width:820px;padding:26px 20px 32px}
.panel{background:#fff;border:1px solid #dbe2ea;border-radius:18px;padding:24px;box-shadow:0 8px 22px rgba(15,23,42,.04)}
.group{border:1px solid #dbe2ea;border-radius:16px;padding:22px 18px 18px;background:#fbfcfd}
.group-title{font-size:18px;font-weight:700;margin:0 0 18px}
.date-row{display:grid;grid-template-columns:140px 1fr;gap:14px;align-items:center;margin-bottom:18px}
.date-row label{font-weight:700}
.date-row input{height:46px;border:1px solid #cfd8e3;border-radius:12px;padding:0 14px;font:inherit;text-align:center}
.radio-list{display:grid;gap:14px}
.radio-item{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid #dbe2ea;border-radius:12px;background:#fff}
.radio-item input{width:17px;height:17px}
.actions{display:grid;gap:14px;max-width:420px;margin:22px auto 0}
.btn{appearance:none;border:none;border-radius:14px;padding:14px 16px;font:inherit;font-weight:700;cursor:pointer}
.btn.primary{background:#173a63;color:#fff}.btn.secondary{background:#dfe6ee;color:#173a63}.btn.exit{background:#c81e12;color:#fff}
.status{margin-top:18px;min-height:24px;padding:10px 12px;border-radius:10px;background:#fff;border:1px solid #dbe2ea;text-align:center;color:#667085}
.status.ok{background:#ecfdf3;border-color:#abefc6;color:#067647}.status.warn{background:#fff8e8;border-color:#f4d58d;color:#8a5a00}
</style>
</head>
<body>
    <div class="page-header">
        <div class="badge">S</div>
        <div>
            <div class="title">Update Stock</div>
            <div class="subtitle">Legacy `w_updatestock` window layout migrated for the web app.</div>
        </div>
    </div>

    <main class="page">
        <section class="panel">
            <div class="group">
                <h2 class="group-title">Update Stock</h2>

                <div class="date-row">
                    <label for="stockDate">Date</label>
                    <input id="stockDate" type="date" value="{{ $today }}">
                </div>

                <div class="radio-list">
                    <label class="radio-item">
                        <input id="rbStockLedger" name="stockBasis" type="radio" value="ledger" checked>
                        <span>Based On Stock Ledger</span>
                    </label>
                    <label class="radio-item">
                        <input id="rbStockList" name="stockBasis" type="radio" value="list">
                        <span>Based On Stock List</span>
                    </label>
                </div>
            </div>

            <div class="actions">
                <button class="btn primary" id="updateMainBtn" type="button">Update Main Stock</button>
                <button class="btn secondary" id="updateTypeBtn" type="button">Update Stk Type Stock</button>
                <button class="btn exit" id="exitBtn" type="button">Exit</button>
            </div>

            <div class="status" id="statusText"></div>
        </section>
    </main>

<script>
const statusText = document.getElementById('statusText');
const stockDate = document.getElementById('stockDate');

function currentBasis() {
    return document.querySelector('input[name="stockBasis"]:checked')?.value === 'ledger'
        ? 'Based On Stock Ledger'
        : 'Based On Stock List';
}

function runAction(label) {
    if (!confirm('You are going to update stock.\nDo you want to continue?')) return;
    statusText.className = 'status warn';
    statusText.textContent = label + ' is not wired yet. Selected mode: ' + currentBasis() + '. Date: ' + stockDate.value + '.';
}

document.getElementById('updateMainBtn').addEventListener('click', function() {
    runAction('Update Main Stock');
});

document.getElementById('updateTypeBtn').addEventListener('click', function() {
    runAction('Update Stk Type Stock');
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
