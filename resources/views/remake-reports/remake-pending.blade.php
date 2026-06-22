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
th,td{border-bottom:1px solid #e5ecf5;padding:5px 8px}
th{position:sticky;top:0;background:#edf4fc;font-weight:700;z-index:1;white-space:nowrap;text-align:center}
td.num,th.num{text-align:right;white-space:nowrap}
.bill-row td{background:#f8faff;font-weight:700;color:#173b63}
.bill-row.overdue td{background:#fff1f2;color:#991b1b}
.item-row td{padding-left:20px;color:#334155;background:#fff}
tfoot td{background:#f0f7ff;font-weight:700;color:#173b63;position:sticky;bottom:0}
.badge{display:inline-block;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700}
.badge-over{background:#fee2e2;color:#991b1b}
.badge-ok{background:#fef9c3;color:#713f12}
.empty{padding:30px;text-align:center;color:#64748b;border:1px dashed #c7d4e4;border-radius:8px;margin-top:8px}
@media print{.toolbar{display:none}body{background:#fff}.wrap{border:0;margin:0}.table-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="wrap">
<h1>{{ $title }}</h1><div class="sub">{{ $moduleId }}</div>
<form class="toolbar" id="filterForm">
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="submit" class="primary">Show</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" onclick="window.print()">Print</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" id="exitBtn">Exit</button></div>
</form>
<div class="meta" id="metaInfo">Click <strong>Show</strong> to view all pending remake items.</div>
<div class="table-wrap" id="tableWrap" style="display:none">
  <table>
    <thead>
      <tr>
        <th style="text-align:left">Bill No</th>
        <th>Rcpt Date</th><th>Due Date</th>
        <th style="text-align:left">Customer</th>
        <th style="text-align:left">Item</th>
        <th class="num">Qty</th>
        <th class="num">Gross Wgt</th>
        <th class="num">Net Wgt</th>
        <th style="text-align:left">Complaint</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody id="reportBody"></tbody>
    <tfoot>
      <tr>
        <td colspan="5">Total</td>
        <td class="num" id="totQty">0</td>
        <td class="num" id="totWgt">0.000</td>
        <td class="num" id="totNet">0.000</td>
        <td colspan="2" id="totInfo"></td>
      </tr>
    </tfoot>
  </table>
</div>
<div class="empty" id="emptyState">Click <strong>Show</strong> to view pending remake items.</div>
</div>
<script>
const API=@json(url('/api/remake-reports/remake-pending/data'));
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function n3(v){return Number(v||0).toFixed(3);}

document.getElementById('filterForm').addEventListener('submit',async e=>{
  e.preventDefault();
  document.getElementById('metaInfo').textContent='Loading…';
  try{
    const res=await fetch(API,{headers:{Accept:'application/json'}});
    const d=await res.json();
    if(!d.ok)throw new Error(d.message||'Error');
    const rows=d.rows||[];const t=d.totals||{};
    let html='';
    rows.forEach(r=>{
      const badge=r.overdue
        ?'<span class="badge badge-over">Overdue</span>'
        :'<span class="badge badge-ok">Pending</span>';
      r.items.forEach((it,i)=>{
        html+=`<tr class="${i===0?'bill-row':'item-row'}${r.overdue&&i===0?' overdue':''}">
          <td>${i===0?`<strong>${esc(r.billno)}</strong>`:'↳'}</td>
          <td style="text-align:center">${i===0?esc(r.tdate):''}</td>
          <td style="text-align:center ${r.overdue?'color:#991b1b':''}">${i===0?esc(r.duedate||'—'):''}</td>
          <td>${i===0?`<strong>${esc(r.custname)}</strong><br><small style="color:#5b7088">${esc(r.custcode)}</small>`:''}</td>
          <td>${esc(it.name)}<br><small style="color:#5b7088">${esc(it.code)}</small></td>
          <td class="num">${Number(it.qty||0).toFixed(0)}</td>
          <td class="num">${n3(it.weight)}</td>
          <td class="num">${n3(it.netwgt)}</td>
          <td>${esc(it.complaint||'')}</td>
          <td style="text-align:center">${i===0?badge:''}</td>
        </tr>`;
      });
      if(!r.items||r.items.length===0){
        html+=`<tr class="bill-row${r.overdue?' overdue':''}">
          <td><strong>${esc(r.billno)}</strong></td>
          <td style="text-align:center">${esc(r.tdate)}</td>
          <td style="text-align:center">${esc(r.duedate||'—')}</td>
          <td><strong>${esc(r.custname)}</strong></td>
          <td colspan="4" style="color:#94a3b8;font-style:italic">No items</td>
          <td style="text-align:center">${badge}</td>
        </tr>`;
      }
    });
    document.getElementById('reportBody').innerHTML=html;
    document.getElementById('totQty').textContent=Number(t.tot_qty||0).toFixed(0);
    document.getElementById('totWgt').textContent=n3(t.tot_wgt);
    document.getElementById('totNet').textContent=n3(t.tot_net);
    document.getElementById('totInfo').textContent=`${t.overdue||0} overdue`;
    document.getElementById('metaInfo').textContent=`${rows.length} pending bill(s) — ${t.overdue||0} overdue`;
    document.getElementById('tableWrap').style.display=rows.length?'':'none';
    document.getElementById('emptyState').style.display=rows.length?'none':'';
    if(!rows.length)document.getElementById('emptyState').textContent='No pending remake items.';
  }catch(err){
    document.getElementById('metaInfo').textContent=err.message;
    document.getElementById('tableWrap').style.display='none';
    document.getElementById('emptyState').style.display='';
    document.getElementById('emptyState').textContent=err.message;
  }
});
document.getElementById('exitBtn').addEventListener('click',()=>window.parent.postMessage({type:'goldapp:close-module-frame'},'*'));
// Auto-load
document.getElementById('filterForm').dispatchEvent(new Event('submit'));
</script>
</body></html>
