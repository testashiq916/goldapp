<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Stock Summary (Rate Wise)</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;color:#1a1a2e;font-size:13px}

/* Toolbar */
.toolbar{background:linear-gradient(135deg,#1a1a2e,#16213e);padding:10px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;position:sticky;top:0;z-index:20;box-shadow:0 2px 8px rgba(0,0,0,.2)}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap}
.toolbar label{color:#94a3b8;font-size:11px;font-weight:600}
.toolbar input[type=date]{padding:5px 8px;border:1px solid #334155;border-radius:6px;background:#0f172a;color:#e2e8f0;font-size:12px}
.toolbar input[type=date]:focus{outline:none;border-color:#60a5fa}
.btn{padding:6px 16px;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-primary{background:#3b82f6;color:#fff}
.btn-primary:hover{background:#2563eb}
.btn-print{background:#6366f1;color:#fff}
.btn-print:hover{background:#4f46e5}
.btn:disabled{opacity:.5;cursor:not-allowed}

/* Layout */
.container{display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:16px;max-width:1400px;margin:0 auto}
@media(max-width:900px){.container{grid-template-columns:1fr}}

/* Panel */
.panel{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);overflow:hidden;margin-bottom:16px}
.panel-head{padding:10px 16px;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;border-bottom:2px solid #e2e8f0}
.panel-head.gold{background:linear-gradient(90deg,#fef3c7,#fde68a);color:#92400e}
.panel-head.silver{background:linear-gradient(90deg,#e2e8f0,#cbd5e1);color:#334155}
.panel-head.closing{background:linear-gradient(90deg,#dbeafe,#bfdbfe);color:#1e3a5f}
.panel-head.balance{background:linear-gradient(90deg,#dcfce7,#bbf7d0);color:#166534}

/* Table */
.tbl{width:100%;border-collapse:collapse}
.tbl th{background:#f8fafc;text-align:right;padding:5px 10px;font-size:11px;font-weight:700;color:#64748b;border-bottom:1px solid #e2e8f0;text-transform:uppercase;letter-spacing:.04em}
.tbl th:first-child{text-align:left}
.tbl td{padding:5px 10px;border-bottom:1px solid #f1f5f9;font-size:12px;text-align:right;font-variant-numeric:tabular-nums}
.tbl td:first-child{text-align:left;font-weight:600;color:#334155}
.tbl tr.section-head td{background:#f8fafc;font-weight:800;color:#1e293b;font-size:11px;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid #e2e8f0;padding:8px 10px}
.tbl tr.total td{font-weight:700;border-top:2px solid #94a3b8;background:#f1f5f9;color:#0f172a}
.tbl tr.subtotal td{font-weight:700;border-top:1.5px solid #cbd5e1}
.tbl tr.grand td{font-weight:800;border-top:3px double #334155;background:#e2e8f0;color:#0f172a;font-size:13px}

/* Loading */
.loading{text-align:center;padding:40px;color:#94a3b8;font-size:14px;font-weight:600}
.empty{text-align:center;padding:60px 20px;color:#94a3b8}
.empty h3{font-size:16px;margin-bottom:6px;color:#64748b}
</style>
@include('partials.print-layout-head')
</head>
<body>

<div class="toolbar">
    <h1>Stock Summary (Rate Wise)</h1>
    <div>
        <label>From</label>
        <input type="date" id="date1">
    </div>
    <div>
        <label>To</label>
        <input type="date" id="date2">
    </div>
    <button class="btn btn-primary" id="btnShow" onclick="loadData()">Show</button>
    <button class="btn btn-print" onclick="window.print()">Print</button>
</div>

<div class="container" id="content">
    <div class="empty" style="grid-column:1/-1">
        <h3>Select date range and click Show</h3>
        <p>Stock summary with rate-wise breakdown will appear here</p>
    </div>
</div>

<script>
const el = id => document.getElementById(id);
const fmt = (v,d=2) => Number(v||0).toLocaleString('en-IN',{minimumFractionDigits:d,maximumFractionDigits:d});
const fmtW = v => fmt(v,3);
const fmtA = v => fmt(v,2);

// Default dates
(function(){
    const today = new Date();
    const fy = today.getMonth() >= 3 ? today.getFullYear() : today.getFullYear()-1;
    el('date1').value = `${fy}-04-01`;
    el('date2').value = today.toISOString().slice(0,10);
})();

async function loadData(){
    const d1 = el('date1').value, d2 = el('date2').value;
    if(!d1||!d2){alert('Select both dates');return}
    el('btnShow').disabled = true;
    el('content').innerHTML = '<div class="loading" style="grid-column:1/-1">Loading stock summary...</div>';

    try{
        const r = await fetch(`{{ url("/stock-summary-rate-wise/show") }}?date1=${d1}&date2=${d2}`,{
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
        });
        const d = await r.json();
        if(!d.ok){el('content').innerHTML=`<div class="empty" style="grid-column:1/-1"><h3>${d.message||'Error'}</h3></div>`;return}
        render(d);
    }catch(e){
        el('content').innerHTML='<div class="empty" style="grid-column:1/-1"><h3>Network error</h3></div>';
    }finally{
        el('btnShow').disabled = false;
    }
}

function render(d){
    const t = d.txn, c = d.closing, s = d.smith, cu = d.cust, su = d.supp;

    // Computed totals for closing
    const totGoldWgt = c.ornWgt + c.ogWgt + c.gbWgt + c.otherWgt;
    const totGoldAmt = c.ornAmt + c.ogAmt + c.gbAmt + c.otherAmt;
    const netGoldWgt = totGoldWgt + s.toGetWgt - s.toGiveWgt;
    const netGoldAmt = totGoldAmt + s.toGetAmt - s.toGiveAmt;
    const netStockAmt = netGoldAmt + c.soAmt + c.osAmt;

    el('content').innerHTML = `
    <!-- LEFT: Stock Transactions -->
    <div>
        <div class="panel">
            <div class="panel-head gold">Stock Transactions</div>
            <table class="tbl">
                <thead><tr><th style="width:40%">Particular</th><th>Weight</th><th>Amount</th></tr></thead>
                <tbody>
                    <tr class="section-head"><td colspan="3">Gold Transactions</td></tr>
                    <tr><td>Gold Sales</td><td>${fmtW(t.gSalesWgt)}</td><td>${fmtA(t.gSalesAmt)}</td></tr>
                    <tr><td>Gold Sales Return</td><td>${fmtW(t.gSalesRWgt)}</td><td>${fmtA(t.gSalesRAmt)}</td></tr>
                    <tr><td>OG Purchase</td><td>${fmtW(t.ogPurchWgt)}</td><td>${fmtA(t.ogPurchAmt)}</td></tr>
                    <tr><td>Gold Bar Purchase</td><td>${fmtW(t.gbPurchWgt)}</td><td>${fmtA(t.gbPurchAmt)}</td></tr>
                    <tr><td>Other Gold Purchase</td><td>${fmtW(t.gPurchWgt)}</td><td>${fmtA(t.gPurchAmt)}</td></tr>
                    <tr><td>Gold Repair Rcvd</td><td>${fmtW(t.gRpRcvdWgt)}</td><td>${fmtA(t.gRpRcvdAmt)}</td></tr>
                    <tr><td>Gold Repair Issued</td><td>${fmtW(t.gRpIssuedWgt)}</td><td>${fmtA(t.gRpIssuedAmt)}</td></tr>

                    <tr class="section-head"><td colspan="3">Issued To Goldsmith</td></tr>
                    <tr><td>Gold Ornaments</td><td>${fmtW(t.smithIssuOrnWgt)}</td><td>${fmtA(t.smithIssuOrnAmt)}</td></tr>
                    <tr><td>Other Gold Items</td><td>${fmtW(t.smithIssuOtherWgt)}</td><td>${fmtA(t.smithIssuOtherAmt)}</td></tr>

                    <tr class="section-head"><td colspan="3">Rcvd From Goldsmith</td></tr>
                    <tr><td>Gold Ornaments</td><td>${fmtW(t.smithRcvdOrnWgt)}</td><td>${fmtA(t.smithRcvdOrnAmt)}</td></tr>
                    <tr><td>Other Gold Items</td><td>${fmtW(t.smithRcvdOtherWgt)}</td><td>${fmtA(t.smithRcvdOtherAmt)}</td></tr>
                    <tr><td>Smith Wastage</td><td>${fmtW(t.smithWastageWgt)}</td><td></td></tr>

                    <tr class="section-head"><td colspan="3">Refinery</td></tr>
                    <tr><td>Issued To Refinery</td><td>${fmtW(t.refnIssuedWgt)}</td><td>${fmtA(t.refnIssuedAmt)}</td></tr>
                    <tr><td>Rcvd From Refinery</td><td>${fmtW(t.refnRcvdWgt)}</td><td>${fmtA(t.refnRcvdAmt)}</td></tr>

                    <tr class="section-head"><td colspan="3">Silver Transactions</td></tr>
                    <tr><td>Silver Sales</td><td>${fmtW(t.sSalesWgt)}</td><td>${fmtA(t.sSalesAmt)}</td></tr>
                    <tr><td>Silver Sales Return</td><td>${fmtW(t.sSalesRWgt)}</td><td>${fmtA(t.sSalesRAmt)}</td></tr>
                    <tr><td>Silver Purchase</td><td>${fmtW(t.sPurchWgt)}</td><td>${fmtA(t.sPurchAmt)}</td></tr>
                    <tr><td>OS Purchase</td><td>${fmtW(t.osPurchWgt)}</td><td>${fmtA(t.osPurchAmt)}</td></tr>
                    <tr><td>Silver Repair Rcvd</td><td>${fmtW(t.sRpRcvdWgt)}</td><td>${fmtA(t.sRpRcvdAmt)}</td></tr>
                    <tr><td>Silver Repair Issued</td><td>${fmtW(t.sRpIssuedWgt)}</td><td>${fmtA(t.sRpIssuedAmt)}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- RIGHT: Closing Stock + Balances -->
    <div>
        <div class="panel">
            <div class="panel-head closing">Closing Stock</div>
            <table class="tbl">
                <thead><tr><th style="width:40%">Particular</th><th>Weight</th><th>Amount</th></tr></thead>
                <tbody>
                    <tr><td>Gold Ornaments</td><td>${fmtW(c.ornWgt)}</td><td>${fmtA(c.ornAmt)}</td></tr>
                    <tr><td>Old Gold</td><td>${fmtW(c.ogWgt)}</td><td>${fmtA(c.ogAmt)}</td></tr>
                    <tr><td>Gold Bar</td><td>${fmtW(c.gbWgt)}</td><td>${fmtA(c.gbAmt)}</td></tr>
                    <tr><td>Other Gold</td><td>${fmtW(c.otherWgt)}</td><td>${fmtA(c.otherAmt)}</td></tr>
                    <tr class="subtotal"><td>Total Gold Stock</td><td>${fmtW(totGoldWgt)}</td><td>${fmtA(totGoldAmt)}</td></tr>
                    <tr><td>Smith (To Get)</td><td>${fmtW(s.toGetWgt)}</td><td>${fmtA(s.toGetAmt)}</td></tr>
                    <tr><td>Smith (To Give)</td><td>${fmtW(s.toGiveWgt)}</td><td>${fmtA(s.toGiveAmt)}</td></tr>
                    <tr><td>Smith Wastage</td><td>${fmtW(s.wastageWgt)}</td><td></td></tr>
                    <tr class="total"><td>Net Gold Stock</td><td>${fmtW(netGoldWgt)}</td><td>${fmtA(netGoldAmt)}</td></tr>
                    <tr><td>Silver Ornaments</td><td>${fmtW(c.soWgt)}</td><td>${fmtA(c.soAmt)}</td></tr>
                    <tr><td>Old Silver</td><td>${fmtW(c.osWgt)}</td><td>${fmtA(c.osAmt)}</td></tr>
                    <tr class="grand"><td>Net Stock Amount</td><td></td><td>${fmtA(netStockAmt)}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <div class="panel-head balance">Account Balances</div>
            <table class="tbl">
                <thead><tr><th style="width:50%">Particular</th><th>Amount</th></tr></thead>
                <tbody>
                    <tr class="section-head"><td colspan="2">Customer Balance</td></tr>
                    <tr><td>To Get (Receivable)</td><td>${fmtA(cu.toGet)}</td></tr>
                    <tr><td>To Give (Payable)</td><td>${fmtA(cu.toGive)}</td></tr>
                    <tr class="section-head"><td colspan="2">Supplier Balance</td></tr>
                    <tr><td>To Get (Receivable)</td><td>${fmtA(su.toGet)}</td></tr>
                    <tr><td>To Give (Payable)</td><td>${fmtA(su.toGive)}</td></tr>
                    <tr class="section-head"><td colspan="2">Smith Amount Balance</td></tr>
                    <tr><td>To Get</td><td>${fmtA(s.toGetAmt)}</td></tr>
                    <tr><td>To Give</td><td>${fmtA(s.toGiveAmt)}</td></tr>
                </tbody>
            </table>
        </div>
    </div>`;
}
</script>
</body>
</html>
