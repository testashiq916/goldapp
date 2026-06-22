<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tally Export</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 900px; margin: 14px auto; }
        .card { background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 22px; margin-bottom: 14px; }
        h1 { margin: 0 0 6px; font-size: 22px; color: #173b63; }
        .subtitle { font-size: 13px; color: #64748b; margin-bottom: 20px; }
        form.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; background: #f0f5ff !important; border-radius: 8px; padding: 12px; }
        .field { display: flex; flex-direction: column; gap: 3px; }
        label { font-size: 12px; font-weight: 700; color: #1e3a5f; }
        input, select { height: 36px; border: 1px solid #c4d1e2; border-radius: 6px; padding: 0 10px; font-size: 13px; box-sizing: border-box; }
        button { height: 36px; border: none; border-radius: 6px; padding: 0 18px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #173b63; color: #fff; }
        .btn-green { background: #15803d; color: #fff; }
        .type-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 20px; }
        .type-card { border: 2px solid #e5ecf5; border-radius: 10px; padding: 16px; cursor: pointer; transition: all .15s; }
        .type-card:hover, .type-card.selected { border-color: #173b63; background: #f0f5ff; }
        .type-card .icon { font-size: 28px; margin-bottom: 8px; }
        .type-card h3 { margin: 0 0 4px; font-size: 14px; color: #173b63; }
        .type-card p { margin: 0; font-size: 12px; color: #64748b; line-height: 1.5; }
        .mapping-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 14px; }
        .mapping-table th, .mapping-table td { border: 1px solid #e5ecf5; padding: 7px 12px; }
        .mapping-table th { background: #edf4fc; font-weight: 700; color: #64748b; font-size: 12px; }
        .mapping-table select { height: 30px; font-size: 12px; }
        .info-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 14px; font-size: 12px; color: #92400e; line-height: 1.6; }
        .info-box ul { margin: 6px 0 0; padding-left: 18px; }
        .success-msg { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #15803d; display: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Tally Export</h1>
        <div class="subtitle">Export GoldApp transactions to Tally-compatible XML format</div>

        <div class="type-grid">
            <div class="type-card selected" id="type-daybook" onclick="selectType('daybook')">
                <div class="icon">&#128196;</div>
                <h3>Day Book</h3>
                <p>Export vouchers (receipts, payments, journals) for date range. Imports directly into Tally day book.</p>
            </div>
            <div class="type-card" id="type-accounts" onclick="selectType('accounts')">
                <div class="icon">&#128193;</div>
                <h3>Account Masters</h3>
                <p>Export customer/supplier ledgers with their group mappings (Sundry Debtors / Creditors / Bank).</p>
            </div>
            <div class="type-card" id="type-masters" onclick="selectType('masters')">
                <div class="icon">&#128091;</div>
                <h3>Item Masters</h3>
                <p>Export stock items with HSN codes, units, and category grouping for Tally stock master.</p>
            </div>
        </div>

        <form class="toolbar" onsubmit="exportXml(event)">
            <div class="field" id="date-fields">
                <label>From</label>
                <input type="date" id="d1" value="{{ date('Y-m-01') }}">
            </div>
            <div class="field" id="date-fields-2">
                <label>To</label>
                <input type="date" id="d2" value="{{ date('Y-m-d') }}">
            </div>
            <div class="field">
                <label>Company Name (in Tally)</label>
                <input type="text" id="company_name" placeholder="{{ $shopName ?? 'My Company' }}" style="min-width:220px;">
            </div>
            <div class="field"><label>&nbsp;</label>
                <button type="submit" class="btn-green">&#8659; Export XML</button>
            </div>
        </form>

        <div class="success-msg" id="success-msg">Export started — XML file will download automatically.</div>
    </div>

    <div class="card" id="mapping-section" style="display:none;">
        <h2 style="margin:0 0 14px;font-size:16px;color:#173b63;">Account Group Mapping</h2>
        <p style="font-size:13px;color:#64748b;margin-bottom:12px;">Map GoldApp account types to Tally ledger groups:</p>
        <table class="mapping-table">
            <thead><tr><th>GoldApp Type</th><th>Typical Accounts</th><th>Tally Group</th></tr></thead>
            <tbody>
                <tr><td>C — Customer</td><td>Sales parties</td><td><select><option selected>Sundry Debtors</option><option>Sundry Creditors</option><option>Direct Income</option></select></td></tr>
                <tr><td>S — Supplier</td><td>Purchase parties</td><td><select><option>Sundry Debtors</option><option selected>Sundry Creditors</option></select></td></tr>
                <tr><td>B — Bank</td><td>Bank accounts</td><td><select><option selected>Bank Accounts</option><option>Bank OD Accounts</option></select></td></tr>
                <tr><td>CASH — Cash</td><td>Cash in hand</td><td><select><option selected>Cash-in-Hand</option></select></td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="info-box">
            <strong>How to import in Tally:</strong>
            <ul>
                <li>Open Tally → Gateway of Tally → Import Data → Vouchers (or Masters)</li>
                <li>Select the exported XML file</li>
                <li>Tally auto-creates ledgers/vouchers from the XML</li>
                <li>For first-time use, export Account Masters before Day Book</li>
            </ul>
        </div>
    </div>
</div>

<script>
let currentType = 'daybook';

function selectType(t) {
    currentType = t;
    document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('type-' + t).classList.add('selected');
    const dateFields = document.getElementById('date-fields');
    const dateFields2 = document.getElementById('date-fields-2');
    const mappingSection = document.getElementById('mapping-section');
    dateFields.style.display = t === 'daybook' ? '' : 'none';
    dateFields2.style.display = t === 'daybook' ? '' : 'none';
    mappingSection.style.display = t === 'accounts' ? '' : 'none';
}

function exportXml(e) {
    e.preventDefault();
    const d1 = document.getElementById('d1').value;
    const d2 = document.getElementById('d2').value;
    const company = document.getElementById('company_name').value.trim() || '{{ $shopName ?? "My Company" }}';

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/api/tally-export/export';

    const fields = { type: currentType, date1: d1, date2: d2, company_name: company, _token: getCsrf() };
    for (const [k, v] of Object.entries(fields)) {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = k; inp.value = v;
        form.appendChild(inp);
    }
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    document.getElementById('success-msg').style.display = 'block';
    setTimeout(() => { document.getElementById('success-msg').style.display = 'none'; }, 5000);
}

function getCsrf() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}
</script>
</body>
</html>
