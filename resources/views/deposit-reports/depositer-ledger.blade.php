<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:#f4f7fb;color:#1e293b;font-size:20px}
.wrap{max-width:1400px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
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
th,td{border-bottom:1px solid #e5ecf5;padding:5px 7px;vertical-align:top}
th{position:sticky;top:0;background:#edf4fc;font-weight:700;z-index:1;white-space:nowrap}
td.num,th.num{text-align:right;white-space:nowrap}
th.grp-dep{background:#d1fae5}th.grp-wit{background:#fee2e2}th.grp-bal{background:#dbeafe}
.dep{color:#1b5b31}.wit{color:#991b1b}.bal-pos{color:#1b5b31}.bal-neg{color:#991b1b}
.badge{display:inline-block;padding:1px 6px;border-radius:10px;font-size:10px;font-weight:700}
.badge-dep{background:#d1fae5;color:#065f46}.badge-wit{background:#fee2e2;color:#991b1b}
.row-op{background:#fef9c3;font-style:italic}
tfoot td{background:#f0f7ff;font-weight:700;color:#173b63;position:sticky;bottom:0}
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
  <div class="field"><label>From</label><input type="date" id="date1" value="{{ $today }}"></div>
  <div class="field"><label>To</label><input type="date" id="date2" value="{{ $today }}"></div>
  <div class="field" style="min-width:200px">
    <label>Party</label>
    <select id="pcode">
      <option value="">All Parties</option>
      @foreach($depositors as $d)
      <option value="{{ trim((string)$d->code) }}">{{ trim((string)$d->code) }} — {{ trim((string)$d->name) }}</option>
      @endforeach
    </select>
  </div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="submit" class="primary">Show</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" onclick="window.print()">Print</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" id="exitBtn">Exit</button></div>
</form>
<div class="meta" id="metaInfo">Select a party and click <strong>Show</strong> to view the ledger.</div>
<div class="table-wrap" id="tableWrap" style="display:none">
  <table>
    <thead>
      <tr>
        <th>Date</th><th>Doc No</th><th>Party</th><th>Item</th><th>Type</th>
        <th class="num grp-dep">Deposit Wgt</th><th class="num grp-dep">Deposit Net</th>
        <th class="num grp-wit">Withdraw Wgt</th><th class="num grp-wit">Withdraw Net</th>
        <th class="num grp-bal">Balance Wgt</th><th class="num grp-bal">Balance Net</th>
      </tr>
    </thead>
    <tbody id="reportBody"></tbody>
    <tfoot>
      <tr>
        <td colspan="5">Total</td>
        <td class="num" id="totDepWgt">0.000</td>
        <td class="num" id="totDepNet">0.000</td>
        <td class="num" id="totWitWgt">0.000</td>
        <td class="num" id="totWitNet">0.000</td>
        <td colspan="2" id="totCount"></td>
      </tr>
    </tfoot>
  </table>
</div>
<div class="empty" id="emptyState">Set filters and click <strong>Show</strong> to view the depositer ledger.</div>
</div>
<script>
const API=@json(url('/api/deposit-reports/depositer-ledger/data'));
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function fmtD(v){if(!v)return'';const p=String(v).split('-');return p.length===3?`${p[2]}/${p[1]}/${p[0].slice(-2)}`:v;}
function n3(v){const n=Number(v||0);return n===0?'':n.toFixed(3);}
function n3z(v){return Number(v||0).toFixed(3);}
document.getElementById('filterForm').addEventListener('submit',async e=>{
  e.preventDefault();
  document.getElementById('metaInfo').textContent='Loading…';
  const p=new URLSearchParams({
    date1:document.getElementById('date1').value,
    date2:document.getElementById('date2').value,
    pcode:document.getElementById('pcode').value,
  });
  try{
    const res=await fetch(`${API}?${p}`,{headers:{Accept:'application/json'}});
    const d=await res.json();
    if(!d.ok)throw new Error(d.message||'Error');
    const rows=d.rows||[];const t=d.totals||{};
    document.getElementById('reportBody').innerHTML=rows.map(r=>{
      const isOp=r.is_opening;
      return`<tr class="${isOp?'row-op':''}">
        <td>${fmtD(r.tdate)}</td>
        <td><strong>${esc(r.docno)}</strong></td>
        <td>${esc(r.partyname)||esc(r.smithcode)}</td>
        <td>${esc(r.itemname)}</td>
        <td>${r.givrec_lbl?`<span class="badge ${r.givrec==='R'?'badge-dep':'badge-wit'}">${esc(r.givrec_lbl)}</span>`:''}</td>
        <td class="num dep">${n3(r.dep_wgt)}</td>
        <td class="num dep">${n3(r.dep_net)}</td>
        <td class="num wit">${n3(r.wit_wgt)}</td>
        <td class="num wit">${n3(r.wit_net)}</td>
        <td class="num ${r.bal_wgt>=0?'bal-pos':'bal-neg'}">${n3z(r.bal_wgt)}</td>
        <td class="num ${r.bal_net>=0?'bal-pos':'bal-neg'}">${n3z(r.bal_net)}</td>
      </tr>`;
    }).join('');
    document.getElementById('totDepWgt').textContent=n3z(t.dep_wgt);
    document.getElementById('totDepNet').textContent=n3z(t.dep_net);
    document.getElementById('totWitWgt').textContent=n3z(t.wit_wgt);
    document.getElementById('totWitNet').textContent=n3z(t.wit_net);
    document.getElementById('totCount').textContent=`${t.count||0} txn(s)`;
    document.getElementById('metaInfo').textContent=`${rows.length} row(s)`;
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
