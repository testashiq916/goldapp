<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Administration</title>
<style>
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0;padding:0}
body{
    font-family:'Segoe UI',system-ui,sans-serif;
    background:#f3f5f8;
    color:#172033;
    font-size:13px;
}
.page-header{
    background:#fff;
    border-bottom:1px solid #dbe2ea;
    padding:14px 20px;
    display:flex;
    align-items:center;
    gap:12px;
}
.badge{
    width:34px;
    height:34px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg,#c9a84c,#8d6a22);
    color:#fff;
    font-weight:700;
    box-shadow:0 8px 20px rgba(141,106,34,.18);
}
.title{font-size:20px;font-weight:700}
.subtitle{color:#667085;font-size:12px;margin-top:3px}
.page{
    max-width:1120px;
    padding:24px 20px 32px;
}
.section{
    background:#fff;
    border:1px solid #dbe2ea;
    border-radius:16px;
    padding:20px;
    box-shadow:0 8px 22px rgba(15,23,42,.04);
}
.section + .section{margin-top:18px}
.section h2{
    margin:0 0 8px;
    font-size:15px;
}
.section p{
    margin:0 0 18px;
    color:#667085;
    line-height:1.45;
}
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:14px;
}
.card{
    border:1px solid #dbe2ea;
    border-radius:14px;
    background:#fbfcfd;
    padding:16px;
    min-height:132px;
    display:flex;
    flex-direction:column;
    gap:10px;
}
.card-title{
    font-size:15px;
    font-weight:700;
    color:#172033;
}
.card-desc{
    color:#667085;
    line-height:1.45;
    flex:1;
}
.pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    width:max-content;
    padding:4px 9px;
    border-radius:999px;
    font-size:11px;
    font-weight:700;
    letter-spacing:.02em;
    background:#eef6ff;
    color:#175cd3;
}
.pill.pending{
    background:#fff4e5;
    color:#b54708;
}
.actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.btn{
    appearance:none;
    border:none;
    border-radius:10px;
    padding:10px 14px;
    background:#173a63;
    color:#fff;
    font-weight:600;
    text-decoration:none;
    cursor:pointer;
}
.btn.secondary{
    background:#fff;
    color:#173a63;
    border:1px solid #c8d4e0;
}
.btn:disabled{
    cursor:not-allowed;
    background:#e5e7eb;
    color:#98a2b3;
}
.helper{
    margin-top:12px;
    padding:11px 12px;
    border-radius:10px;
    background:#fff8e8;
    border:1px solid #f4d58d;
    color:#8a5a00;
    display:none;
}
.helper.show{display:block}
@media (max-width:640px){
    .page-header{padding:12px 14px}
    .page{padding:16px 14px 24px}
}
</style>
</head>
<body>
    <div class="page-header">
        <div class="badge">A</div>
        <div>
            <div class="title">Administration</div>
            <div class="subtitle">Legacy administration window migrated from the desktop menu.</div>
        </div>
    </div>

    <main class="page">
        <section class="section">
            <h2>Legacy Actions</h2>
            <p>Visible actions from the old `w_administration` window are listed here. Ported modules open directly. Items not yet migrated are marked clearly.</p>

            <div class="grid">
                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Initialise</div>
                    <div class="card-desc">Open the migrated initialise window with the legacy reset and cleanup options.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/administration/initialise') }}">Open</a>
                    </div>
                </article>

                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Stock Update</div>
                    <div class="card-desc">Open the migrated `w_updatestock` window for stock update actions.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/administration/stock-update') }}">Open</a>
                    </div>
                </article>

                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Initialise Doc. No.</div>
                    <div class="card-desc">Open the migrated bill number initialise window for sales, purchase, smith, refinery, order, and repair counters.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/administration/initialise-docno') }}">Open</a>
                    </div>
                </article>

                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">SQL Updt</div>
                    <div class="card-desc">Open the migrated SQL maintenance workspace with guided cleanup and repair actions, plus AI-based action recommendations for common data issues.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/administration/sql-update') }}">Open</a>
                    </div>
                </article>

                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Data Transfer</div>
                    <div class="card-desc">Open the new cross-company data transfer screen with date range and simple tick options for masters and transactions.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/administration/data-transfer') }}">Open</a>
                    </div>
                </article>

                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Change Doc. No.</div>
                    <div class="card-desc">Open the migrated document number maintenance screen for counters, prefixes, lengths, and start values across sales, purchase, jewellery, smith, repair, vouchers, and kuri.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/administration/change-docno') }}">Open</a>
                    </div>
                </article>

                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Rearange Docnos</div>
                    <div class="card-desc">Open the migrated voucher and document renumbering screen for sales, purchase, smith, jewellery, repair, receipt, payment, and journal sequences.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/administration/rearrange-docnos') }}">Open</a>
                    </div>
                </article>

                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Add Slno</div>
                    <div class="card-desc">Open the migrated `w_slnoupdt` screen to increase `slno` values across linked transaction tables from a selected date.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/administration/add-slno') }}">Open</a>
                    </div>
                </article>

                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Update Log Rep</div>
                    <div class="card-desc">Open the migrated update details screen for user history and audit update logs.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/administration/update-log') }}">Open</a>
                    </div>
                </article>

                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Exit</div>
                    <div class="card-desc">Closes this screen and returns to the dashboard workspace.</div>
                    <div class="actions">
                        <button class="btn" type="button" id="exitBtn">Close</button>
                    </div>
                </article>
            </div>

            <div class="helper" id="pendingNote"></div>
        </section>

        <section class="section">
            <h2>Web Admin Tools</h2>
            <p>Administration features already available in the web system are grouped here for quick access.</p>

            <div class="grid">
                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Application Settings</div>
                    <div class="card-desc">Manage application-level setup and defaults.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/application-settings') }}">Open</a>
                    </div>
                </article>

                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Change Password</div>
                    <div class="card-desc">Update the current user's password.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/admin/users/' . rawurlencode($userCode) . '/edit') }}">Open</a>
                    </div>
                </article>

                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Provide Access</div>
                    <div class="card-desc">Manage feature-level permissions for users.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/user-access') }}">Open</a>
                    </div>
                </article>

                <article class="card">
                    <span class="pill">Available</span>
                    <div class="card-title">Users History</div>
                    <div class="card-desc">View the current user's access and activity history.</div>
                    <div class="actions">
                        <a class="btn" href="{{ url('/admin/users/' . rawurlencode($userCode) . '/history') }}">Open</a>
                    </div>
                </article>
            </div>
        </section>
    </main>

<script>
document.querySelectorAll('[data-pending]').forEach(function(button){
    button.addEventListener('click', function(){
        var note = document.getElementById('pendingNote');
        note.textContent = this.getAttribute('data-pending') + ' is not implemented in the web app yet.';
        note.classList.add('show');
    });
});

document.getElementById('exitBtn').addEventListener('click', function(){
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        return;
    }
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    window.location.href = '{{ url('/dashboard') }}';
});
</script>
</body>
</html>
