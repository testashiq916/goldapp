<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><title>{{ $title }}</title>
<style>
body{font-family:"Segoe UI",Tahoma,sans-serif;margin:0;background:#f3f6fb;color:#1f2937}
.wrap{max-width:1200px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
h1{margin:0 0 4px;font-size:20px;color:#173b63}
.sub{color:#5b7088;font-size:11px;margin-bottom:12px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px}
.field{display:flex;flex-direction:column;gap:3px}
label{font-size:11px;font-weight:700;color:#375b84}
input{height:32px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:12px;box-sizing:border-box}
input[type=date]{width:140px;color:#000080;font-weight:700}
input[type=text]{width:120px;text-transform:uppercase}
button{height:32px;padding:0 16px;cursor:pointer;background:#e8f2ff;border:1px solid #2a6398;border-radius:5px;color:#17456e;font-weight:700;font-size:12px}
button.primary{background:#e6f8ec;border-color:#2a7a42;color:#1b5b31}
.meta{font-size:12px;color:#48627c;margin-bottom:8px}
.table-wrap{border:1px solid #d8e2ef;border-radius:8px;overflow:auto;max-height:72vh}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border-bottom:1px solid #e5ecf5;padding:6px 8px}
th{position:sticky;top:0;background:#edf4fc;font-weight:700;z-index:1;white-space:nowrap;text-align:center}
td.num{text-align:right;font-family:'Courier New',monospace;white-space:nowrap}
tfoot td{background:#f0f7ff;font-weight:700;color:#173b63;position:sticky;bottom:0}
.profit-pos{color:#155724}.profit-neg{color:#721c24}
.empty{padding:30px;text-align:center;color:#64748b;border:1px dashed #c7d4e4;border-radius:8px;margin-top:8px}
@media print{.toolbar{display:none}body{background:#fff}.wrap{border:0;margin:0}.table-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wrap">
<h1>{{ $title }}</h1>
<div class="sub">{{ $moduleId }}</div>
<form class="toolbar" id="filterForm">
    <div class="field"><label>From</label><input type="date" id="date1" value="{{ $today }}"></div>
    <div class="field"><label>To</label><input type="date" id="date2" value="{{ $today }}"></div>
    <div class="field"><label>Item Code</label><input type="text" id="item_code" placeholder="(All)"></div>
    <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="submit" class="primary">Show</button></div>
    <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" onclick="window.print()">Print</button></div>
</form>
<div class="meta" id="metaInfo">Select date range and click <strong>Show</strong>.</div>
<div class="table-wrap" id="tableWrap" style="display:none">
    <table>
        <thead><tr>
            <th style="text-align:left">Code</th><th style="text-align:left">Name</th>
            <th>Qty</th><th>Weight</th><th>Sales Amt</th><th>Cost Amt</th><th>Profit</th>
        </tr></thead>
        <tbody id="reportBody"></tbody>
        <tfoot><tr>
            <td colspan="2">Total</td>
            <td class="num" id="totQty">0</td>
            <td class="num" id="totWgt">0.000</td>
            <td class="num" id="totSale">0.00</td>
            <td class="num" id="totCost">0.00</td>
            <td class="num" id="totProfit">0.00</td>
        </tr></tfoot>
    </table>
</div>
<div class="empty" id="emptyState">Select date range and click <strong>Show</strong>.</div>
</div>
<script>
const API=@json(url('/api/itemwise-profit/data'));
function fmtN(v,d=2){const n=Number(v||0);return n===0?'':n.toFixed(d);}
function fmtN0(v){const n=Number(v||0);return n===0?'':String(n);}
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
document.getElementById('filterForm').addEventListener('submit',async e=>{
    e.preventDefault();
    document.getElementById('metaInfo').textContent='Loading…';
    const p=new URLSearchParams({
        date1:document.getElementById('date1').value,
        date2:document.getElementById('date2').value,
        item_code:document.getElementById('item_code').value.trim(),
    });
    try{
        const res=await fetch(`${API}?${p}`,{headers:{Accept:'application/json'}});
        const d=await res.json();
        if(!d.ok)throw new Error(d.message||'Error');
        const rows=d.rows||[];const t=d.totals||{};
        document.getElementById('reportBody').innerHTML=rows.map(r=>{
            const pn=Number(r.profit||0);const cls=pn>=0?'profit-pos':'profit-neg';
            return`<tr>
            <td>${esc(r.code)}</td><td>${esc(r.name)}</td>
            <td class="num">${fmtN0(r.tqty)}</td><td class="num">${fmtN(r.twgt,3)}</td>
            <td class="num">${fmtN(r.saleamt,2)}</td><td class="num">${fmtN(r.costamt,2)}</td>
            <td class="num ${cls}">${pn===0?'':pn.toFixed(2)}</td>
            </tr>`;
        }).join('');
        document.getElementById('totQty').textContent=fmtN0(t.tqty)||'0';
        document.getElementById('totWgt').textContent=(t.twgt||0).toFixed(3);
        document.getElementById('totSale').textContent=(t.saleamt||0).toFixed(2);
        document.getElementById('totCost').textContent=(t.costamt||0).toFixed(2);
        const tp=Number(t.profit||0);
        document.getElementById('totProfit').textContent=tp.toFixed(2);
        document.getElementById('totProfit').className='num '+(tp>=0?'profit-pos':'profit-neg');
        document.getElementById('metaInfo').textContent=`${rows.length} item(s)`;
        document.getElementById('tableWrap').style.display=rows.length?'':'none';
        document.getElementById('emptyState').style.display=rows.length?'none':'';
        if(!rows.length)document.getElementById('emptyState').textContent='No sales data found for the selected period.';
    }catch(err){
        document.getElementById('metaInfo').textContent=err.message;
        document.getElementById('tableWrap').style.display='none';
        document.getElementById('emptyState').style.display='';
        document.getElementById('emptyState').textContent=err.message;
    }
});
</script>
</body></html>
