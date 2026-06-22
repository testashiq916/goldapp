<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:#f4f7fb;color:#1e293b;font-size:20px}
.wrap{max-width:1100px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
h1{margin:0 0 4px;font-size:20px;color:#173b63}.sub{color:#5b7088;font-size:16px;margin-bottom:10px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px}
.field{display:flex;flex-direction:column;gap:3px;min-width:130px}
label{font-size:17px;font-weight:700;color:#375b84}
input,select{height:32px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:18px}
button{height:32px;padding:0 14px;cursor:pointer;background:#e8f2ff;border:1px solid #2a6398;border-radius:5px;color:#17456e;font-weight:700;font-size:18px}
button.primary{background:#e6f8ec;border-color:#2a7a42;color:#1b5b31}
.meta{font-size:16px;color:#48627c;margin-bottom:8px}
.table-wrap{border:1px solid #d8e2ef;border-radius:8px;overflow:auto;max-height:72vh}
table{width:100%;border-collapse:collapse;font-size:18px}
th,td{border-bottom:1px solid #e5ecf5;padding:6px 8px}
th{position:sticky;top:0;background:#edf4fc;font-weight:700;z-index:1;white-space:nowrap;text-align:center}
td.num,th.num{text-align:right;white-space:nowrap}
tfoot td{background:#f0f7ff;font-weight:700;color:#173b63;position:sticky;bottom:0}
.empty{padding:30px;text-align:center;color:#64748b;border:1px dashed #c7d4e4;border-radius:8px;margin-top:8px}
.zero{color:#94a3b8}
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
  <div class="field"><label>Group</label>
    <select id="grp"><option value="">All Groups</option></select>
  </div>
  <div class="field"><label>Show Zero Stock</label>
    <select id="zero"><option value="1">Yes</option><option value="0">No</option></select>
  </div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="submit" class="primary">Show</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" onclick="window.print()">Print</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" id="exitBtn">Exit</button></div>
</form>
<div class="meta" id="metaInfo">Click <strong>Show</strong> to view the stock list.</div>
<div class="table-wrap" id="tableWrap" style="display:none">
  <table>
    <thead>
      <tr>
        <th style="text-align:left">Code</th>
        <th style="text-align:left">Item Name</th>
        <th style="text-align:left">Group</th>
        <th class="num">Op. Stock</th>
        <th class="num">Current Stock</th>
        <th class="num">Sales Rate</th>
        <th class="num">Purchase Rate</th>
        <th class="num">Cost Rate</th>
        <th class="num">Stock Value</th>
      </tr>
    </thead>
    <tbody id="reportBody"></tbody>
    <tfoot>
      <tr>
        <td colspan="4">Total</td>
        <td class="num" id="totStock">0</td>
        <td class="num">—</td>
        <td class="num">—</td>
        <td class="num">—</td>
        <td class="num" id="totValue">0.00</td>
      </tr>
    </tfoot>
  </table>
</div>
<div class="empty" id="emptyState">Click <strong>Show</strong> to view the stock list.</div>
</div>
<script>
const API=@json(url('/api/other-item-reports/stock-list/data'));
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function n2(v){return Number(v||0).toFixed(2);}

document.getElementById('filterForm').addEventListener('submit',async e=>{
  e.preventDefault();
  document.getElementById('metaInfo').textContent='Loading…';
  const p=new URLSearchParams({
    grp:document.getElementById('grp').value,
    zero:document.getElementById('zero').value,
  });
  try{
    const res=await fetch(`${API}?${p}`,{headers:{Accept:'application/json'}});
    const d=await res.json();
    if(!d.ok)throw new Error(d.message||'Error');
    const rows=d.rows||[];const t=d.totals||{};
    // Populate groups dropdown if not already done
    const grpSel=document.getElementById('grp');
    if(grpSel.options.length<=1&&d.groups&&d.groups.length){
      d.groups.forEach(g=>{
        const o=document.createElement('option');
        o.value=g;o.textContent=g;
        grpSel.appendChild(o);
      });
    }
    document.getElementById('reportBody').innerHTML=rows.map(r=>`<tr class="${r.stock==0?'zero':''}">
      <td>${esc(r.code)}</td>
      <td><strong>${esc(r.name)}</strong></td>
      <td>${esc(r.grp||'')}</td>
      <td class="num">${Number(r.opstock||0).toFixed(0)}</td>
      <td class="num ${r.stock>0?'':'zero'}">${Number(r.stock||0).toFixed(0)}</td>
      <td class="num">${r.srate>0?n2(r.srate):''}</td>
      <td class="num">${r.prate>0?n2(r.prate):''}</td>
      <td class="num">${r.cost>0?n2(r.cost):''}</td>
      <td class="num">${r.value>0?n2(r.value):''}</td>
    </tr>`).join('');
    document.getElementById('totStock').textContent=Number(t.stock||0).toFixed(0);
    document.getElementById('totValue').textContent=n2(t.value);
    document.getElementById('metaInfo').textContent=`${rows.length} item(s) — Value: ₹${n2(t.value)}`;
    document.getElementById('tableWrap').style.display=rows.length?'':'none';
    document.getElementById('emptyState').style.display=rows.length?'none':'';
    if(!rows.length)document.getElementById('emptyState').textContent='No items found.';
  }catch(err){
    document.getElementById('metaInfo').textContent=err.message;
    document.getElementById('tableWrap').style.display='none';
    document.getElementById('emptyState').style.display='';
    document.getElementById('emptyState').textContent=err.message;
  }
});
document.getElementById('exitBtn').addEventListener('click',()=>window.parent.postMessage({type:'goldapp:close-module-frame'},'*'));
// Auto-load on open
document.getElementById('filterForm').dispatchEvent(new Event('submit'));
</script>
</body></html>
