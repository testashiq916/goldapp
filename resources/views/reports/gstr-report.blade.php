<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GST Reports</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 1400px; margin: 14px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 18px; }
        h1 { margin: 0 0 14px; font-size: 22px; color: #173b63; }
        form.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 14px; background: #f0f5ff !important; border-radius: 8px; padding: 10px; }
        .field { display: flex; flex-direction: column; gap: 3px; }
        label { font-size: 12px; font-weight: 700; color: #1e3a5f; }
        input, select, button { height: 36px; border: 1px solid #c4d1e2; border-radius: 6px; padding: 0 10px; font-size: 13px; box-sizing: border-box; }
        button { cursor: pointer; background: #173b63; color: #fff; font-weight: 700; }
        button.ghost { background: #fff; color: #173b63; border-color: #173b63; }
        .tabs { display: flex; gap: 0; margin-bottom: 0; border-bottom: 2px solid #e5ecf5; }
        .tab { padding: 8px 20px; cursor: pointer; font-size: 13px; font-weight: 700; color: #64748b; border: 1px solid transparent; border-bottom: none; margin-bottom: -2px; border-radius: 6px 6px 0 0; }
        .tab.active { background: #fff; border-color: #d7dfeb; color: #173b63; border-bottom: 2px solid #fff; }
        .panel { display: none; padding-top: 16px; } .panel.active { display: block; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 10px; }
        th { background: #edf4fc; font-size: 12px; font-weight: 700; color: #64748b; text-align: left; position: sticky; top: 0; }
        td.num, th.num { text-align: right; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 65vh; }
        .summary-box { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
        .s-card { border: 1px solid #d8e2ef; border-radius: 8px; padding: 12px 14px; background: #f8fbff; }
        .s-card .lbl { font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; }
        .s-card .val { font-size: 20px; font-weight: 700; color: #173b63; margin-top: 4px; }
        .gstin-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .badge-b2b { background: #dbeafe; color: #1e40af; }
        .badge-b2c { background: #f3e8ff; color: #6b21a8; }
        tfoot th { background: #f1f5f9; }
        .empty { padding: 30px; text-align: center; color: #94a3b8; }
        @media print { .toolbar { display: none; } body { background: #fff; } .wrap { border: 0; } }
    
/* ── Font size overrides ── */
body { font-size: 20px !important; }
label { font-size: 17px !important; }
input, select, button { font-size: 18px !important; height: 36px !important; }
table { font-size: 18px !important; }
th { font-size: 15px !important; }
td { font-size: 18px !important; }
.btn, button { font-size: 17px !important; height: 36px !important; }</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
    <script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="wrap">
    <h1>&#128196; GST Reports</h1>

    <form class="toolbar" onsubmit="loadData(event)">
        <div class="field">
            <label>From</label>
            <input type="date" id="date1" value="{{ $dateFrom }}">
        </div>
        <div class="field">
            <label>To</label>
            <input type="date" id="date2" value="{{ $dateTo }}">
        </div>
        <div class="field">
            <label>Report Type</label>
            <select id="rtype">
                <option value="gstr1" {{ $type === 'gstr1' ? 'selected' : '' }}>GSTR-1 (Outward Supplies)</option>
                <option value="gstr3b" {{ $type === 'gstr3b' ? 'selected' : '' }}>GSTR-3B (Monthly Summary)</option>
                <option value="hsn" {{ $type === 'hsn' ? 'selected' : '' }}>HSN-wise Summary</option>
            </select>
        </div>
        <div class="field"><label>&nbsp;</label><button type="submit">Show</button></div>
        <div class="field"><label>&nbsp;</label><button type="button" class="ghost" onclick="window.print()">Print</button></div>
        <div class="field"><label>&nbsp;</label><button type="button" class="ghost" id="btnExportGstr">Export</button></div>
    </form>

    <div style="font-size:12px;color:#64748b;margin-bottom:12px;">
        GSTIN: <strong>{{ $shopGstin ?: 'Not configured' }}</strong> &nbsp;|&nbsp;
        Registered Name: <strong>{{ $shopName }}</strong>
    </div>

    <div id="content"><div class="empty">Select date range and click Show.</div></div>
</div>

<script>
let REPORT_DATA = null;

function loadData(e) {
    if (e) e.preventDefault();
    const d1 = document.getElementById('date1').value;
    const d2 = document.getElementById('date2').value;
    const t  = document.getElementById('rtype').value;
    document.getElementById('content').innerHTML = '<div class="empty">Loading&hellip;</div>';

    fetch(`/api/gstr/data?date1=${d1}&date2=${d2}&type=${t}`)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { document.getElementById('content').innerHTML = `<div class="empty">${d.message}</div>`; return; }
            REPORT_DATA = d.data;
            if (t === 'gstr1')  renderGstr1(d.data, d1, d2);
            else if (t === 'gstr3b') renderGstr3b(d.data);
            else renderHsn(d.data);
        });
}

function n2(v) { return parseFloat(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function esc(v) { const d=document.createElement('div');d.textContent=v=='null'||v==null?'':v;return d.innerHTML; }

function renderGstr1(data, d1, d2) {
    const { b2b=[], b2c=[], docs={}, totalTaxable=0, totalTax=0 } = data;
    let html = `<div class="summary-box">
        <div class="s-card"><div class="lbl">Total Taxable</div><div class="val">₹ ${n2(totalTaxable)}</div></div>
        <div class="s-card"><div class="lbl">Total Tax</div><div class="val">₹ ${n2(totalTax)}</div></div>
        <div class="s-card"><div class="lbl">B2B Invoices</div><div class="val">${b2b.length}</div></div>
        <div class="s-card"><div class="lbl">B2C Invoices</div><div class="val">${b2c.length}</div></div>
    </div>`;

    html += `<div class="tabs">
        <div class="tab active" onclick="gstrTab('b2b')">B2B Supplies (${b2b.length})</div>
        <div class="tab" onclick="gstrTab('b2c')">B2C Supplies (${b2c.length})</div>
        <div class="tab" onclick="gstrTab('docs')">Document Summary</div>
    </div>`;

    html += `<div class="panel active" id="gstr-b2b">
        <div class="table-wrap"><table>
        <thead><tr><th>Invoice No</th><th>Date</th><th>Customer</th><th>GSTIN</th><th class="num">Taxable</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th><th class="num">Invoice Value</th></tr></thead>
        <tbody>${b2b.map(r=>`<tr>
            <td>${esc(r.bill_no)}</td><td>${r.bill_date}</td><td>${esc(r.cust_name)}</td>
            <td><span class="gstin-badge badge-b2b">${esc(r.gstin)}</span></td>
            <td class="num">${n2(r.taxable_value)}</td><td class="num">${n2(r.cgst)}</td>
            <td class="num">${n2(r.sgst)}</td><td class="num">${n2(r.igst)}</td>
            <td class="num">${n2(r.invoice_value)}</td>
        </tr>`).join('')||'<tr><td colspan="9" class="empty">No B2B invoices</td></tr>'}
        </tbody>
        <tfoot><tr><th colspan="4">Total</th><th class="num">${n2(b2b.reduce((s,r)=>s+(+r.taxable_value||0),0))}</th>
        <th class="num">${n2(b2b.reduce((s,r)=>s+(+r.cgst||0),0))}</th>
        <th class="num">${n2(b2b.reduce((s,r)=>s+(+r.sgst||0),0))}</th>
        <th class="num">${n2(b2b.reduce((s,r)=>s+(+r.igst||0),0))}</th>
        <th class="num">${n2(b2b.reduce((s,r)=>s+(+r.invoice_value||0),0))}</th></tr></tfoot>
        </table></div></div>`;

    html += `<div class="panel" id="gstr-b2c">
        <div class="table-wrap"><table>
        <thead><tr><th>Invoice No</th><th>Date</th><th>Customer</th><th class="num">Taxable</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th><th class="num">Invoice Value</th></tr></thead>
        <tbody>${b2c.map(r=>`<tr>
            <td>${esc(r.bill_no)}</td><td>${r.bill_date}</td><td>${esc(r.cust_name)}</td>
            <td class="num">${n2(r.taxable_value)}</td><td class="num">${n2(r.cgst)}</td>
            <td class="num">${n2(r.sgst)}</td><td class="num">${n2(r.igst)}</td>
            <td class="num">${n2(r.invoice_value)}</td>
        </tr>`).join('')||'<tr><td colspan="8" class="empty">No B2C invoices</td></tr>'}
        </tbody></table></div></div>`;

    html += `<div class="panel" id="gstr-docs">
        <div style="padding:16px;font-size:13px;">
        <table style="max-width:400px">
            <tr><th>Document Type</th><th class="num">Count</th></tr>
            <tr><td>Tax Invoices</td><td class="num">${docs.invoices||0}</td></tr>
            <tr><td>Cancelled</td><td class="num">${docs.cancelled||0}</td></tr>
        </table></div></div>`;

    document.getElementById('content').innerHTML = html;
    document.querySelectorAll('#content .tab').forEach(t => {
        t.onclick = function() { gstrTab(this.textContent.split(' ')[0].toLowerCase()); };
    });
}

function gstrTab(t) {
    const map = { b2b: 'gstr-b2b', b2c: 'gstr-b2c', docs: 'gstr-docs', document: 'gstr-docs' };
    document.querySelectorAll('#content .panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('#content .tab').forEach(el => el.classList.remove('active'));
    const panel = document.getElementById(map[t]);
    if (panel) panel.classList.add('active');
    event.target.classList.add('active');
}

function renderGstr3b(data) {
    const { outward={}, inward={}, itcAvail={}, netPayable=0 } = data;
    document.getElementById('content').innerHTML = `
    <div class="summary-box">
        <div class="s-card"><div class="lbl">Outward Tax</div><div class="val">₹ ${n2((+outward.cgst||0)+(+outward.sgst||0)+(+outward.igst||0))}</div></div>
        <div class="s-card"><div class="lbl">ITC Available</div><div class="val">₹ ${n2((+itcAvail.cgst||0)+(+itcAvail.sgst||0)+(+itcAvail.igst||0))}</div></div>
        <div class="s-card"><div class="lbl">Net Payable</div><div class="val" style="color:#dc2626;">₹ ${n2(netPayable)}</div></div>
        <div class="s-card"><div class="lbl">Outward Taxable</div><div class="val">₹ ${n2(outward.taxable||0)}</div></div>
    </div>
    <table style="max-width:700px;margin-top:8px;">
        <tr><th>Head</th><th class="num">Taxable Value</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th><th class="num">Total Tax</th></tr>
        <tr><td><strong>3.1 — Outward Supplies</strong></td><td class="num">${n2(outward.taxable)}</td><td class="num">${n2(outward.cgst)}</td><td class="num">${n2(outward.sgst)}</td><td class="num">${n2(outward.igst)}</td><td class="num">${n2(outward.total_tax)}</td></tr>
        <tr><td><strong>4 — ITC Available</strong></td><td class="num">${n2(inward.taxable)}</td><td class="num">${n2(itcAvail.cgst)}</td><td class="num">${n2(itcAvail.sgst)}</td><td class="num">${n2(itcAvail.igst)}</td><td class="num">${n2((+itcAvail.cgst||0)+(+itcAvail.sgst||0)+(+itcAvail.igst||0))}</td></tr>
        <tfoot><tr><th>Net Tax Payable</th><td></td><td></td><td></td><td></td><th class="num">₹ ${n2(netPayable)}</th></tr></tfoot>
    </table>`;
}

function renderHsn(rows) {
    document.getElementById('content').innerHTML = `
    <div class="table-wrap"><table>
        <thead><tr><th>HSN Code</th><th>Item Code</th><th class="num">Qty</th><th class="num">Total Weight</th><th class="num">Taxable Value</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th></tr></thead>
        <tbody>${(rows||[]).map(r=>`<tr>
            <td>${esc(r.hsn_code)}</td><td>${esc(r.item_code)}</td>
            <td class="num">${r.qty}</td><td class="num">${n2(r.total_weight)}</td>
            <td class="num">${n2(r.taxable_value)}</td><td class="num">${n2(r.cgst)}</td>
            <td class="num">${n2(r.sgst)}</td><td class="num">${n2(r.igst)}</td>
        </tr>`).join('')||'<tr><td colspan="8" class="empty">No HSN data</td></tr>'}
        </tbody>
    </table></div>`;
}
</script>
</body>
</html>
