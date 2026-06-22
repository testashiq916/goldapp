<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:#f4f7fb;color:#1e293b;font-size:20px}
.wrap{max-width:1700px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
h1{margin:0 0 4px;font-size:20px;color:#173b63}.sub{color:#5b7088;font-size:16px;margin-bottom:10px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px}
.field{display:flex;flex-direction:column;gap:3px;min-width:120px}
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
.badge{display:inline-block;padding:1px 6px;border-radius:10px;font-size:10px;font-weight:700}
.badge-r{background:#d1fae5;color:#065f46}.badge-p{background:#fee2e2;color:#991b1b}
.rcpt{color:#1b5b31}.pmnt{color:#991b1b}
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
  <div class="field" style="min-width:160px">
    <label>Party Code</label>
    <input type="text" id="pcode" placeholder="(All)" style="text-transform:uppercase">
  </div>
  <div class="field">
    <label>Type</label>
    <select id="ttype">
      <option value="">All</option>
      <option value="R">Receipt (Deposit)</option>
      <option value="P">Payment (Withdraw)</option>
    </select>
  </div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="submit" class="primary">Show</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" onclick="window.print()">Print</button></div>
  <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" id="exitBtn">Exit</button></div>
</form>
<div class="meta" id="metaInfo">Set filters and click <strong>Show</strong>.</div>
<div class="table-wrap" id="tableWrap" style="display:none">
  <table>
    <thead>
      <tr>
        <th>Date</th><th>Doc No</th><th>Party</th><th>Item</th><th>Type</th>
        <th class="num">Qty</th>
        <th class="num">Receipt Wgt</th><th class="num">Receipt Net</th>
        <th class="num">Payment Wgt</th><th class="num">Payment Net</th>
        <th class="num">Touch</th><th class="num">Gold Rate</th>
        <th class="num">Int %</th><th class="num">Int Amt</th>
        <th>Note</th>
      </tr>
    </thead>
    <tbody id="reportBody"></tbody>
    <tfoot>
      <tr>
        <td colspan="6">Total</td>
        <td class="num" id="totRcptWgt">0.000</td>
        <td class="num" id="totRcptNet">0.000</td>
        <td class="num" id="totPmntWgt">0.000</td>
        <td class="num" id="totPmntNet">0.000</td>
        <td colspan="3"></td>
        <td class="num" id="totIntAmt">0.00</td>
        <td id="totCount"></td>
      </tr>
    </tfoot>
  </table>
</div>
<div class="empty" id="emptyState">Set filters and click <strong>Show</strong> to view the deposit book.</div>
</div>
<script>
const API=@json(url('/api/deposit-reports/deposit-book/data'));
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function fmtD(v){if(!v)return'';const p=String(v).split('-');return p.length===3?`${p[2]}/${p[1]}/${p[0].slice(-2)}`:v;}
function n3(v){const n=Number(v||0);return n===0?'':n.toFixed(3);}
function n2(v){const n=Number(v||0);return n===0?'':n.toFixed(2);}
function n3z(v){return Number(v||0).toFixed(3);}
function n2z(v){return Number(v||0).toFixed(2);}
document.getElementById('filterForm').addEventListener('submit',async e=>{
  e.preventDefault();
  document.getElementById('metaInfo').textContent='Loading…';
  const p=new URLSearchParams({
    date1:document.getElementById('date1').value,
    date2:document.getElementById('date2').value,
    pcode:document.getElementById('pcode').value.trim().toUpperCase(),
    ttype:document.getElementById('ttype').value,
  });
  try{
    const res=await fetch(`${API}?${p}`,{headers:{Accept:'application/json'}});
    const d=await res.json();
    if(!d.ok)throw new Error(d.message||'Error');
    const rows=d.rows||[];const t=d.totals||{};
    document.getElementById('reportBody').innerHTML=rows.map(r=>`<tr>
      <td>${fmtD(r.tdate)}</td>
      <td><strong>${esc(r.docno)}</strong></td>
      <td>${esc(r.pname)}<br><small style="color:#5b7088">${esc(r.pcode)}</small></td>
      <td>${esc(r.iname)}</td>
      <td><span class="badge ${r.ttype==='R'?'badge-r':'badge-p'}">${esc(r.ttype_lbl)}</span></td>
      <td class="num">${r.qty>0?r.qty:''}</td>
      <td class="num rcpt">${r.ttype==='R'?n3(r.weight):''}</td>
      <td class="num rcpt">${r.ttype==='R'?n3(r.netwgt):''}</td>
      <td class="num pmnt">${r.ttype==='P'?n3(r.weight):''}</td>
      <td class="num pmnt">${r.ttype==='P'?n3(r.netwgt):''}</td>
      <td class="num">${n2(r.touch)}</td>
      <td class="num">${n2(r.grate)}</td>
      <td class="num">${n2(r.intperc)}</td>
      <td class="num">${n2(r.intamt)}</td>
      <td>${esc(r.note)}</td>
    </tr>`).join('');
    document.getElementById('totRcptWgt').textContent=n3z(t.rcpt_wgt);
    document.getElementById('totRcptNet').textContent=n3z(t.rcpt_net);
    document.getElementById('totPmntWgt').textContent=n3z(t.pmnt_wgt);
    document.getElementById('totPmntNet').textContent=n3z(t.pmnt_net);
    document.getElementById('totIntAmt').textContent=n2z(t.intamt);
    document.getElementById('totCount').textContent=`${rows.length} row(s)`;
    document.getElementById('metaInfo').textContent=`${rows.length} record(s)`;
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
