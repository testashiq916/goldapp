<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:#f4f7fb;color:#1e293b;font-size:13px}
.wrap{max-width:1300px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
h1{margin:0 0 4px;font-size:20px;color:#173b63}.sub{color:#5b7088;font-size:11px;margin-bottom:10px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px}
.field{display:flex;flex-direction:column;gap:3px;min-width:130px}
label{font-size:11px;font-weight:700;color:#375b84}
input,select{height:32px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:12px}
button{height:32px;padding:0 14px;cursor:pointer;background:#e8f2ff;border:1px solid #2a6398;border-radius:5px;color:#17456e;font-weight:700;font-size:12px}
button.primary{background:#e6f8ec;border-color:#2a7a42;color:#1b5b31}
.meta{font-size:12px;color:#48627c;margin-bottom:8px}
.table-wrap{border:1px solid #d8e2ef;border-radius:8px;overflow:auto;max-height:72vh}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border-bottom:1px solid #e5ecf5;padding:6px 8px}
th{position:sticky;top:0;background:#edf4fc;font-weight:700;z-index:1;white-space:nowrap;text-align:center}
td.num,th.num{text-align:right;white-space:nowrap}
th.grp-r{background:#d1fae5}th.grp-p{background:#fee2e2}th.grp-b{background:#dbeafe}
.pos{color:#1b5b31}.neg{color:#991b1b}
tfoot td{background:#f0f7ff;font-weight:700;color:#173b63;position:sticky;bottom:0}
.empty{padding:30px;text-align:center;color:#64748b;border:1px dashed #c7d4e4;border-radius:8px;margin-top:8px}
@media print{.toolbar{display:none}body{background:#fff}.wrap{border:0;margin:0}.table-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wrap">
<h1>{{ $title }}</h1><div class="sub">{{ $moduleId }}</div>
<form class="toolbar" id="filterForm">
  <div class="field"><label>From</label><input type="date" id="date1" value="{{ $today }}"></div>
  <div class="field"><label>To</label><input type="date" id="date2" value="{{ $today }}"></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="submit" class="primary">Show</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" onclick="window.print()">Print</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" id="exitBtn">Exit</button></div>
</form>
<div class="meta" id="metaInfo">Set filters and click <strong>Show</strong>.</div>
<div class="table-wrap" id="tableWrap" style="display:none">
  <table>
    <thead>
      <tr>
        <th rowspan="2" style="text-align:left">Party</th>
        <th rowspan="2" class="num">Txns</th>
        <th colspan="3" class="grp-r">Receipt (Deposit)</th>
        <th colspan="3" class="grp-p">Payment (Withdraw)</th>
        <th colspan="2" class="grp-b">Balance</th>
        <th rowspan="2" class="num">Interest</th>
      </tr>
      <tr>
        <th class="num grp-r">Qty</th><th class="num grp-r">Gross Wgt</th><th class="num grp-r">Net Wgt</th>
        <th class="num grp-p">Qty</th><th class="num grp-p">Gross Wgt</th><th class="num grp-p">Net Wgt</th>
        <th class="num grp-b">Gross Wgt</th><th class="num grp-b">Net Wgt</th>
      </tr>
    </thead>
    <tbody id="reportBody"></tbody>
    <tfoot>
      <tr>
        <td>Total</td><td class="num" id="totTxns">0</td>
        <td class="num" id="totRcptQty">0</td>
        <td class="num" id="totRcptWgt">0.000</td>
        <td class="num" id="totRcptNet">0.000</td>
        <td class="num" id="totPmntQty">0</td>
        <td class="num" id="totPmntWgt">0.000</td>
        <td class="num" id="totPmntNet">0.000</td>
        <td class="num" id="totBalWgt">0.000</td>
        <td class="num" id="totBalNet">0.000</td>
        <td class="num" id="totInt">0.00</td>
      </tr>
    </tfoot>
  </table>
</div>
<div class="empty" id="emptyState">Set filters and click <strong>Show</strong> to view the weight balance summary.</div>
</div>
<script>
const API=@json(url('/api/deposit-reports/wgt-balance-summary/data'));
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function n3(v){return Number(v||0).toFixed(3);}
function n2(v){return Number(v||0).toFixed(2);}
document.getElementById('filterForm').addEventListener('submit',async e=>{
  e.preventDefault();
  document.getElementById('metaInfo').textContent='Loading…';
  const p=new URLSearchParams({
    date1:document.getElementById('date1').value,
    date2:document.getElementById('date2').value,
  });
  try{
    const res=await fetch(`${API}?${p}`,{headers:{Accept:'application/json'}});
    const d=await res.json();
    if(!d.ok)throw new Error(d.message||'Error');
    const rows=d.rows||[];const t=d.totals||{};
    document.getElementById('reportBody').innerHTML=rows.map(r=>`<tr>
      <td><strong>${esc(r.pname)}</strong><br><small style="color:#5b7088">${esc(r.pcode)}</small></td>
      <td class="num">${r.total_txns}</td>
      <td class="num pos">${r.rcpt_qty>0?r.rcpt_qty:''}</td>
      <td class="num pos">${n3(r.rcpt_wgt)}</td>
      <td class="num pos">${n3(r.rcpt_net)}</td>
      <td class="num neg">${r.pmnt_qty>0?r.pmnt_qty:''}</td>
      <td class="num neg">${n3(r.pmnt_wgt)}</td>
      <td class="num neg">${n3(r.pmnt_net)}</td>
      <td class="num ${r.bal_wgt>=0?'pos':'neg'}">${n3(r.bal_wgt)}</td>
      <td class="num ${r.bal_net>=0?'pos':'neg'}">${n3(r.bal_net)}</td>
      <td class="num">${r.total_int>0?n2(r.total_int):''}</td>
    </tr>`).join('');
    document.getElementById('totTxns').textContent=rows.reduce((s,r)=>s+r.total_txns,0);
    document.getElementById('totRcptQty').textContent=rows.reduce((s,r)=>s+r.rcpt_qty,0);
    document.getElementById('totRcptWgt').textContent=n3(t.rcpt_wgt);
    document.getElementById('totRcptNet').textContent=n3(t.rcpt_net);
    document.getElementById('totPmntQty').textContent=rows.reduce((s,r)=>s+r.pmnt_qty,0);
    document.getElementById('totPmntWgt').textContent=n3(t.pmnt_wgt);
    document.getElementById('totPmntNet').textContent=n3(t.pmnt_net);
    document.getElementById('totBalWgt').textContent=n3(t.bal_wgt);
    document.getElementById('totBalNet').textContent=n3(t.bal_net);
    document.getElementById('totInt').textContent=n2(t.total_int);
    document.getElementById('metaInfo').textContent=`${rows.length} party(ies)`;
    document.getElementById('tableWrap').style.display=rows.length?'':'none';
    document.getElementById('emptyState').style.display=rows.length?'none':'';
    if(!rows.length)document.getElementById('emptyState').textContent='No records found.';
  }catch(err){
    document.getElementById('metaInfo').textContent=err.message;
    document.getElementById('tableWrap').style.display='none';
    document.getElementById('emptyState').style.display='';
    document.getElementById('emptyState').textContent=err.message;
  }
});
document.getElementById('exitBtn').addEventListener('click',()=>window.parent.postMessage({type:'goldapp:close-module-frame'},'*'));
</script>
</body></html>
