<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:#f4f7fb;color:#1e293b;font-size:13px}
.wrap{max-width:1100px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
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
th.grp-in{background:#d1fae5}th.grp-out{background:#fee2e2}th.grp-bal{background:#dbeafe}
.pos{color:#1b5b31}.neg{color:#991b1b}
tfoot td{background:#f0f7ff;font-weight:700;color:#173b63;position:sticky;bottom:0}
.ob-row td{background:#fefce8;font-style:italic;color:#92400e}
.empty{padding:30px;text-align:center;color:#64748b;border:1px dashed #c7d4e4;border-radius:8px;margin-top:8px}
@media print{.toolbar{display:none}body{background:#fff}.wrap{border:0;margin:0}.table-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wrap">
<h1>{{ $title }}</h1><div class="sub">{{ $moduleId }}</div>
<form class="toolbar" id="filterForm">
  <div class="field"><label>Item *</label>
    <select id="icode" required><option value="">— Select Item —</option></select>
  </div>
  <div class="field"><label>From</label><input type="date" id="date1" value="{{ $today }}"></div>
  <div class="field"><label>To</label><input type="date" id="date2" value="{{ $today }}"></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="submit" class="primary">Show</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" onclick="window.print()">Print</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" id="exitBtn">Exit</button></div>
</form>
<div class="meta" id="metaInfo">Select an item and click <strong>Show</strong>.</div>
<div class="table-wrap" id="tableWrap" style="display:none">
  <table>
    <thead>
      <tr>
        <th>Date</th><th>Doc No</th><th style="text-align:left">Party</th><th>Type</th>
        <th class="num grp-in">In Qty</th>
        <th class="num grp-out">Out Qty</th>
        <th class="num grp-bal">Balance</th>
        <th class="num">Rate</th><th class="num">Amount</th>
      </tr>
    </thead>
    <tbody id="reportBody"></tbody>
    <tfoot>
      <tr>
        <td colspan="4">Total</td>
        <td class="num" id="totIn">0</td>
        <td class="num" id="totOut">0</td>
        <td class="num" id="totBal">—</td>
        <td class="num">—</td>
        <td class="num" id="totAmt">0.00</td>
      </tr>
    </tfoot>
  </table>
</div>
<div class="empty" id="emptyState">Select an item and click <strong>Show</strong> to view the stock ledger.</div>
</div>
<script>
const API_DATA=@json(url('/api/other-item-reports/stock-ledger/data'));
const API_ITEMS=@json(url('/api/other-item-reports/items-lookup'));
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function n2(v){return Number(v||0).toFixed(2);}

fetch(API_ITEMS,{headers:{Accept:'application/json'}}).then(r=>r.json()).then(d=>{
  if(!d.ok)return;
  const sel=document.getElementById('icode');
  (d.items||[]).forEach(i=>{
    const o=document.createElement('option');
    o.value=i.code;o.textContent=`${i.name} (Stk: ${Number(i.stock||0).toFixed(0)})`;
    sel.appendChild(o);
  });
});

document.getElementById('filterForm').addEventListener('submit',async e=>{
  e.preventDefault();
  const icode=document.getElementById('icode').value;
  if(!icode){alert('Please select an item');return;}
  document.getElementById('metaInfo').textContent='Loading…';
  const p=new URLSearchParams({
    icode,
    date1:document.getElementById('date1').value,
    date2:document.getElementById('date2').value,
  });
  try{
    const res=await fetch(`${API_DATA}?${p}`,{headers:{Accept:'application/json'}});
    const d=await res.json();
    if(!d.ok)throw new Error(d.message||'Error');
    const rows=d.rows||[];const t=d.totals||{};
    const opStock=Number(d.op_stock||0);
    let html='';
    // Opening balance row
    html+=`<tr class="ob-row">
      <td colspan="3" style="text-align:left"><em>Opening Balance</em></td>
      <td></td><td class="num"></td><td class="num"></td>
      <td class="num ${opStock>=0?'pos':'neg'}">${Number(opStock).toFixed(0)}</td>
      <td class="num"></td><td class="num"></td>
    </tr>`;
    rows.forEach(r=>{
      const isSale=r.sp==='S';
      html+=`<tr>
        <td>${esc(r.tdate)}</td>
        <td>${esc(r.docno)}</td>
        <td>${esc(r.pname)}</td>
        <td style="text-align:center"><span style="font-size:10px;padding:2px 6px;border-radius:10px;background:${isSale?'#fee2e2':'#d1fae5'};color:${isSale?'#991b1b':'#1b5b31'}">${isSale?'Sale':'Purchase'}</span></td>
        <td class="num pos">${r.in_qty>0?Number(r.in_qty).toFixed(0):''}</td>
        <td class="num neg">${r.out_qty>0?Number(r.out_qty).toFixed(0):''}</td>
        <td class="num ${r.balance>=0?'pos':'neg'}">${Number(r.balance).toFixed(0)}</td>
        <td class="num">${n2(r.rate)}</td>
        <td class="num">${n2(r.amount)}</td>
      </tr>`;
    });
    document.getElementById('reportBody').innerHTML=html;
    document.getElementById('totIn').textContent=Number(t.in_qty||0).toFixed(0);
    document.getElementById('totOut').textContent=Number(t.out_qty||0).toFixed(0);
    const lastBal=rows.length?rows[rows.length-1].balance:opStock;
    document.getElementById('totBal').textContent=Number(lastBal).toFixed(0);
    document.getElementById('totAmt').textContent=n2(t.amount);
    document.getElementById('metaInfo').textContent=`${d.iname||''} (${d.icode||''}) — ${rows.length} transaction(s)`;
    document.getElementById('tableWrap').style.display='';
    document.getElementById('emptyState').style.display=rows.length?'none':'';
    if(!rows.length)document.getElementById('emptyState').textContent='No transactions in this period.';
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
