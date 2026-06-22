<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:#f4f7fb;color:#1e293b;font-size:13px}
.wrap{max-width:1400px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
h1{margin:0 0 4px;font-size:20px;color:#173b63}.sub{color:#5b7088;font-size:11px;margin-bottom:10px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px}
.field{display:flex;flex-direction:column;gap:3px}
label{font-size:11px;font-weight:700;color:#375b84}
input,select{height:32px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:12px}
select#reptype{min-width:230px}
button{height:32px;padding:0 14px;cursor:pointer;background:#e8f2ff;border:1px solid #2a6398;border-radius:5px;color:#17456e;font-weight:700;font-size:12px}
button.primary{background:#e6f8ec;border-color:#2a7a42;color:#1b5b31}
.meta{font-size:12px;color:#48627c;margin-bottom:8px}
.table-wrap{border:1px solid #d8e2ef;border-radius:8px;overflow:auto;max-height:70vh}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border-bottom:1px solid #e5ecf5;padding:5px 8px}
th{position:sticky;top:0;background:#edf4fc;font-weight:700;z-index:1;white-space:nowrap;text-align:center}
td.num,th.num{text-align:right;white-space:nowrap}
.bill-hdr td{background:#eef6ff;font-weight:700;color:#173b63;border-top:2px solid #bfd0e6}
.item-row td:first-child{padding-left:22px;color:#64748b}
tfoot td{background:#f0f7ff;font-weight:700;color:#173b63;position:sticky;bottom:0}
.badge{display:inline-block;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700}
.b-done{background:#d1fae5;color:#065f46}.b-pend{background:#fef9c3;color:#713f12}
.b-iss{background:#dbeafe;color:#1e40af}.b-rcv{background:#d1fae5;color:#065f46}
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
  <div class="field">
    <label>Report Type</label>
    <select id="reptype">
      <option value="1">Remake Entry Details</option>
      <option value="2">Goldsmith Issue Details</option>
      <option value="3">Goldsmith Rcpt Details</option>
      <option value="4">Remake Return to Party Details</option>
    </select>
  </div>
  <div class="field"><label>From</label><input type="date" id="date1" value="{{ $today }}"></div>
  <div class="field"><label>To</label><input type="date" id="date2" value="{{ $today }}"></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="submit" class="primary">Show</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" onclick="window.print()">Print</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" id="exitBtn">Exit</button></div>
</form>
<div class="meta" id="metaInfo">Select report type, set date range and click <strong>Show</strong>.</div>
<div class="table-wrap" id="tableWrap" style="display:none">
  <table id="reportTable">
    <thead id="reportHead"></thead>
    <tbody id="reportBody"></tbody>
    <tfoot id="reportFoot"></tfoot>
  </table>
</div>
<div class="empty" id="emptyState">Select report type and click <strong>Show</strong>.</div>
</div>
<script>
const API=@json(url('/api/remake-reports/remake-reports/data'));
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function n2(v){return Number(v||0).toFixed(2);}
function n3(v){return Number(v||0).toFixed(3);}
function n0(v){return Number(v||0).toFixed(0);}

/* ── Type 1 & 4: repairm rows ─────────────────────────────────── */
function buildRepairHead(isEntry){
  return `<tr>
    <th style="text-align:left">Bill No</th>
    <th>Date</th>
    <th>${isEntry?'Due Date':'Orig. Bill'}</th>
    <th style="text-align:left">Customer</th>
    <th style="text-align:left">Item</th>
    <th class="num">Qty</th>
    <th class="num">Gross Wgt</th>
    <th class="num">Stone Wgt</th>
    <th class="num">Net Wgt</th>
    ${isEntry?'<th style="text-align:left">Complaint</th><th>Status</th>':'<th class="num">Charge</th>'}
  </tr>`;
}
function buildRepairFoot(t, isEntry){
  return `<tr>
    <td colspan="5">Total — ${t.bills||0} bill(s)</td>
    <td class="num">${n0(t.tot_qty)}</td>
    <td class="num">${n3(t.tot_wgt)}</td>
    <td class="num">—</td>
    <td class="num">${n3(t.tot_net)}</td>
    ${isEntry?`<td></td><td></td>`:`<td class="num">${n2(t.tot_amt)}</td>`}
  </tr>`;
}
function buildRepairRows(rows, isEntry){
  let html='';
  rows.forEach(r=>{
    const badge=isEntry?(r.returned
      ?'<span class="badge b-done">Returned</span>'
      :'<span class="badge b-pend">Pending</span>'):'';
    const col3=isEntry?esc(r.duedate||'—'):`<span style="font-size:11px;color:#475569">${esc(r.rbillno||'')}</span>`;
    if(!r.items||r.items.length===0){
      html+=`<tr class="bill-hdr"><td><strong>${esc(r.billno)}</strong></td><td>${esc(r.tdate)}</td>
        <td>${col3}</td><td><strong>${esc(r.custname)}</strong><br><small>${esc(r.custcode)}</small></td>
        <td colspan="4" style="color:#94a3b8;font-style:italic">No items</td>
        ${isEntry?`<td></td><td>${badge}</td>`:'<td></td>'}
      </tr>`;
      return;
    }
    r.items.forEach((it,i)=>{
      html+=`<tr class="${i===0?'bill-hdr':'item-row'}">
        <td>${i===0?`<strong>${esc(r.billno)}</strong>`:'↳'}</td>
        <td style="text-align:center">${i===0?esc(r.tdate):''}</td>
        <td style="text-align:center">${i===0?col3:''}</td>
        <td>${i===0?`<strong>${esc(r.custname)}</strong><br><small style="color:#5b7088">${esc(r.custcode)}</small>`:''}</td>
        <td>${esc(it.name)}<br><small style="color:#5b7088">${esc(it.code)}</small></td>
        <td class="num">${n0(it.qty)}</td>
        <td class="num">${n3(it.weight)}</td>
        <td class="num">${n3(it.stonewgt)}</td>
        <td class="num">${n3(it.netwgt)}</td>
        ${isEntry
          ?`<td>${esc(it.complaint||'')}</td><td style="text-align:center">${i===0?badge:''}</td>`
          :`<td class="num">${it.mcharge>0?n2(it.mcharge):''}</td>`
        }
      </tr>`;
    });
  });
  return html;
}

/* ── Type 2 & 3: smith rows ───────────────────────────────────── */
function buildSmithHead(isIssue){
  return `<tr>
    <th>Date</th><th style="text-align:left">Doc No</th>
    <th style="text-align:left">Goldsmith</th>
    <th style="text-align:left">Item</th>
    <th class="num">Qty</th>
    <th class="num">Gross Wgt</th>
    <th class="num">Stone Wgt</th>
    <th class="num">Net Wgt</th>
    <th class="num">Touch</th>
    <th class="num">MC</th>
    <th>Type</th>
  </tr>`;
}
function buildSmithFoot(t, isIssue){
  return `<tr>
    <td colspan="4">Total — ${t.rows||0} line(s)</td>
    <td class="num">—</td>
    <td class="num">${n3(t.weight)}</td>
    <td class="num">—</td>
    <td class="num">${n3(t.netwgt)}</td>
    <td class="num">—</td>
    <td class="num">${n2(t.mcharge)}</td>
    <td></td>
  </tr>`;
}
function buildSmithRows(rows, isIssue){
  const badge=isIssue
    ?'<span class="badge b-iss">Issued</span>'
    :'<span class="badge b-rcv">Received</span>';
  return rows.map(r=>`<tr>
    <td style="text-align:center">${esc(r.tdate)}</td>
    <td><strong>${esc(r.docno)}</strong></td>
    <td>${esc(r.smithname)}<br><small style="color:#5b7088">${esc(r.smithcode)}</small></td>
    <td>${esc(r.name)}<br><small style="color:#5b7088">${esc(r.code)}</small></td>
    <td class="num">${n0(r.qty)}</td>
    <td class="num">${n3(r.weight)}</td>
    <td class="num">${n3(r.stonewgt)}</td>
    <td class="num">${n3(r.netwgt)}</td>
    <td class="num">${r.touch>0?n2(r.touch):''}</td>
    <td class="num">${r.mcharge>0?n2(r.mcharge):''}</td>
    <td style="text-align:center">${badge}</td>
  </tr>`).join('');
}

document.getElementById('filterForm').addEventListener('submit',async e=>{
  e.preventDefault();
  const type=parseInt(document.getElementById('reptype').value);
  document.getElementById('metaInfo').textContent='Loading…';
  const p=new URLSearchParams({
    type,
    date1:document.getElementById('date1').value,
    date2:document.getElementById('date2').value,
  });
  try{
    const res=await fetch(`${API}?${p}`,{headers:{Accept:'application/json'}});
    const d=await res.json();
    if(!d.ok)throw new Error(d.message||'Error');
    const rows=d.rows||[];const t=d.totals||{};
    const rtype=d.rtype||type;
    const isSmith=(rtype===2||rtype===3);
    const isIssue=(rtype===2);
    const isEntry=(rtype===1);

    if(isSmith){
      document.getElementById('reportHead').innerHTML=buildSmithHead(isIssue);
      document.getElementById('reportBody').innerHTML=buildSmithRows(rows,isIssue);
      document.getElementById('reportFoot').innerHTML=buildSmithFoot(t,isIssue);
      document.getElementById('metaInfo').textContent=`${rows.length} line(s) — Gross: ${n3(t.weight)} g | Net: ${n3(t.netwgt)} g`;
    } else {
      document.getElementById('reportHead').innerHTML=buildRepairHead(isEntry);
      document.getElementById('reportBody').innerHTML=buildRepairRows(rows,isEntry);
      document.getElementById('reportFoot').innerHTML=buildRepairFoot(t,isEntry);
      document.getElementById('metaInfo').textContent=`${rows.length} bill(s) — Gross: ${n3(t.tot_wgt)} g | Net: ${n3(t.tot_net)} g`;
    }

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
