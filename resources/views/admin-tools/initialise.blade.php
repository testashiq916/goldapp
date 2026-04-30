<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Initialise</title>
<style>
*,*::before,*::after{box-sizing:border-box} html,body{margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f3f5f8;color:#172033;font-size:13px}
.page-header{background:#fff;border-bottom:1px solid #dbe2ea;padding:14px 20px;display:flex;align-items:center;gap:12px}
.badge{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#c9a84c,#8d6a22);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
.title{font-size:20px;font-weight:700}.subtitle{color:#667085;font-size:12px;margin-top:3px}
.page{max-width:1180px;padding:24px 20px 32px}
.panel{background:#fff;border:1px solid #dbe2ea;border-radius:16px;padding:22px;box-shadow:0 8px 22px rgba(15,23,42,.04)}
.grid{display:grid;grid-template-columns:1.25fr .95fr;gap:22px}
.group-title{font-size:14px;font-weight:700;margin:0 0 14px}
.checks{display:grid;gap:10px}
.checks.two{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 24px}
.check{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:12px;background:#fbfcfd}
.check input{margin-top:2px;width:16px;height:16px}
.check label{font-size:13px;line-height:1.35;cursor:pointer}
.actions{display:grid;gap:12px;align-content:start}
.btn{appearance:none;border:none;border-radius:12px;padding:13px 16px;font-weight:700;cursor:pointer}
.btn.primary{background:#173a63;color:#fff}.btn.secondary{background:#eef2f6;color:#173a63}.btn.warn{background:#b42318;color:#fff}
.btn:disabled{background:#e5e7eb;color:#98a2b3;cursor:not-allowed}
.note{margin-top:16px;padding:12px 14px;border-radius:10px;background:#fff8e8;border:1px solid #f4d58d;color:#8a5a00}
.status{display:none;margin-top:16px;padding:12px 14px;border-radius:10px}
.status.show{display:block}.status.ok{background:#ecfdf3;border:1px solid #abefc6;color:#067647}.status.err{background:#fef3f2;border:1px solid #fecdca;color:#b42318}
@media (max-width:900px){.grid{grid-template-columns:1fr}.checks.two{grid-template-columns:1fr}}
</style>
</head>
<body>
    <div class="page-header">
        <div class="badge">I</div>
        <div>
            <div class="title">Initialise</div>
            <div class="subtitle">Legacy reset and cleanup operations migrated from `w_initialise`.</div>
        </div>
    </div>

    <main class="page">
        <form class="panel" id="initForm">
            @csrf
            <div class="grid">
                <div>
                    <h2 class="group-title">Reset And Delete Options</h2>
                    <div class="checks two">
                        <div class="check"><input id="delentries" name="delentries" type="checkbox"><label for="delentries">Delete All Entries</label></div>
                        <div class="check"><input id="delmctable" name="delmctable" type="checkbox"><label for="delmctable">Delete MC Table</label></div>

                        <div class="check"><input id="initbillno" name="initbillno" type="checkbox"><label for="initbillno">Initialise All Bill Numbers</label></div>
                        <div class="check"><input id="delwstgtable" name="delwstgtable" type="checkbox"><label for="delwstgtable">Delete Wastage Table</label></div>

                        <div class="check"><input id="initstock" name="initstock" type="checkbox"><label for="initstock">Initialise Item Stock</label></div>
                        <div class="check"><input id="delpict" name="delpict" type="checkbox"><label for="delpict">Delete Photos</label></div>

                        <div class="check"><input id="initopbal" name="initopbal" type="checkbox"><label for="initopbal">Initialise Opening Balances</label></div>
                        <div class="check"><input id="delmodels" name="delmodels" type="checkbox"><label for="delmodels">Delete Model, Submodels</label></div>

                        <div class="check"><input id="slno" name="slno" type="checkbox" disabled><label for="slno">Initialise Serial No.</label></div>
                        <div class="check"><input id="delcust" name="delcust" type="checkbox"><label for="delcust">Delete All Customers A/c</label></div>

                        <div class="check"><input id="delsupp" name="delsupp" type="checkbox"><label for="delsupp">Delete All Suppliers A/c</label></div>
                        <div class="check"><input id="delsmith" name="delsmith" type="checkbox"><label for="delsmith">Delete All Smith A/c</label></div>

                        <div class="check"><input id="delrefin" name="delrefin" type="checkbox"><label for="delrefin">Delete All Refiners A/c</label></div>
                        <div class="check"><input id="deljewl" name="deljewl" type="checkbox"><label for="deljewl">Delete All Jewellers A/c</label></div>

                        <div class="check"><input id="delstaff" name="delstaff" type="checkbox"><label for="delstaff">Delete All Staff A/c</label></div>
                        <div class="check"><input id="delbarcode" name="delbarcode" type="checkbox"><label for="delbarcode">Delete All Barcode details</label></div>

                        <div class="check"><input id="delitems" name="delitems" type="checkbox"><label for="delitems">Delete All Items</label></div>
                        <div class="check"><input id="initstaffattendance" name="initstaffattendance" type="checkbox"><label for="initstaffattendance">Initialise Staff Attendance Details</label></div>

                        <div class="check"><input id="initopbalie" name="initopbalie" type="checkbox"><label for="initopbalie">Initialise Op. Balance of Income/Expense A/cs</label></div>
                        <div class="check"><input id="initopwgtbalcustomers" name="initopwgtbalcustomers" type="checkbox"><label for="initopwgtbalcustomers">Initialise Customer Op.Wgt.Bal</label></div>

                        <div class="check"><input id="removeaddr" name="removeaddr" type="checkbox"><label for="removeaddr">Remove Addr + phone no from clients</label></div>
                    </div>
                </div>

                <div class="actions">
                    <button class="btn primary" id="startBtn" type="submit">Start</button>
                    <button class="btn secondary" id="selectAllBtn" type="button">Select All</button>
                    <button class="btn secondary" id="changeDocBtn" type="button" disabled>Change Doc. Nos.</button>
                    <button class="btn warn" id="exitBtn" type="button">Exit</button>

                    <div class="note">
                        `Initialise Serial No.` becomes available when `Initialise All Bill Numbers` is checked.
                        `Change Doc. Nos.` is shown for parity with the legacy window and can be wired next.
                    </div>
                    <div class="status" id="statusBox"></div>
                </div>
            </div>
        </form>
    </main>

<script>
const form = document.getElementById('initForm');
const statusBox = document.getElementById('statusBox');
const initBillNo = document.getElementById('initbillno');
const serialNo = document.getElementById('slno');
const initStock = document.getElementById('initstock');
const changeDocBtn = document.getElementById('changeDocBtn');

function showStatus(message, isError) {
    statusBox.textContent = message;
    statusBox.className = 'status show ' + (isError ? 'err' : 'ok');
}

function syncToggles() {
    serialNo.disabled = !initBillNo.checked;
    if (serialNo.disabled) serialNo.checked = false;
    changeDocBtn.disabled = !initStock.checked;
}

initBillNo.addEventListener('change', syncToggles);
initStock.addEventListener('change', syncToggles);
syncToggles();

document.getElementById('selectAllBtn').addEventListener('click', function() {
    ['delentries', 'initbillno', 'initstock', 'initopbal'].forEach(function(id) {
        document.getElementById(id).checked = true;
    });
    syncToggles();
});

document.getElementById('changeDocBtn').addEventListener('click', function() {
    showStatus('Change Doc. Nos. is not wired yet.', true);
});

document.getElementById('exitBtn').addEventListener('click', function() {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        return;
    }
    window.location.href = '{{ url('/administration') }}';
});

form.addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!confirm('You are going to initialise. Please confirm.')) return;

    const btn = document.getElementById('startBtn');
    btn.disabled = true;
    btn.textContent = 'Starting...';
    statusBox.className = 'status';

    try {
        const payload = new FormData(form);
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch('{{ url('/api/administration/initialise') }}', {
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
        showStatus('Initialise request failed.', true);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Start';
    }
});
</script>
</body>
</html>
