<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:#f4f7fb;color:#1e293b;font-size:13px}
.wrap{max-width:1700px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
h1{margin:0 0 4px;font-size:20px;color:#173b63}
.sub{color:#5b7088;font-size:11px;margin-bottom:10px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px}
.field{display:flex;flex-direction:column;gap:3px;min-width:130px}
label{font-size:11px;font-weight:700;color:#375b84}
input,select{height:32px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:12px}
button{height:32px;padding:0 14px;cursor:pointer;background:#e8f2ff;border:1px solid #2a6398;border-radius:5px;color:#17456e;font-weight:700;font-size:12px}
button.primary{background:#e6f8ec;border-color:#2a7a42;color:#1b5b31}
.meta{font-size:12px;color:#48627c;margin-bottom:8px}
.table-wrap{border:1px solid #d8e2ef;border-radius:8px;overflow:auto;max-height:72vh}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border-bottom:1px solid #e5ecf5;padding:5px 7px;vertical-align:top}
th{position:sticky;top:0;background:#edf4fc;font-weight:700;z-index:1;white-space:nowrap}
th.grp-exp{background:#fef3c7}th.grp-act{background:#dcfce7}th.grp-diff{background:#fee2e2}
td.num,th.num{text-align:right;white-space:nowrap}
.pos{color:#1b5b31}.neg{color:#991b1b}
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
<h1>{{ $title }}</h1>
<div class="sub">{{ $moduleId }}</div>
<form class="toolbar" id="filterForm">
  <div class="field"><label>From</label><input type="date" id="date1" value="{{ $today }}"></div>
  <div class="field"><label>To</label><input type="date" id="date2" value="{{ $today }}"></div>
  <div class="field">
    <label>Refiner</label>
    <select id="refcode">
      <option value="">All Refiners</option>
      @foreach($refiners as $r)
      <option value="{{ trim((string)$r->code) }}">{{ trim((string)$r->code) }} — {{ trim((string)$r->name) }}</option>
      @endforeach
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
        <th rowspan="2">Date</th>
        <th rowspan="2">Doc No</th>
        <th rowspan="2">Refiner</th>
        <th rowspan="2">Item</th>
        <th rowspan="2" class="num">Issued (g)</th>
        <th rowspan="2" class="num">Issued Touch</th>
        <th rowspan="2" class="num">Rcvd (g)</th>
        <th rowspan="2" class="num">Rcvd Touch</th>
        <th rowspan="2" class="num">Mud Less (g)</th>
        <th rowspan="2" class="num">Test Pcs (g)</th>
        <th colspan="1" class="grp-exp" style="text-align:center">Expected</th>
        <th colspan="1" class="grp-act" style="text-align:center">Actual</th>
        <th colspan="1" class="grp-diff" style="text-align:center">Variance</th>
        <th rowspan="2" class="num">Test %</th>
        <th rowspan="2" class="num">Exp Wgt (g)</th>
      </tr>
      <tr>
        <th class="num grp-exp">Exp Less (g)</th>
        <th class="num grp-act">Act Less (g)</th>
        <th class="num grp-diff">Diff (g)</th>
      </tr>
    </thead>
    <tbody id="reportBody"></tbody>
    <tfoot>
      <tr>
        <td colspan="4">Total</td>
        <td class="num" id="totIssued">0.000</td>
        <td></td>
        <td class="num" id="totRcvd">0.000</td>
        <td></td>
        <td class="num" id="totMudless">0.000</td>
        <td class="num" id="totTestpcs">0.000</td>
        <td class="num" id="totExpless">0.000</td>
        <td class="num" id="totActless">0.000</td>
        <td class="num" id="totDiff">0.000</td>
        <td colspan="2" id="totCount"></td>
      </tr>
    </tfoot>
  </table>
</div>
<div class="empty" id="emptyState">Set filters and click <strong>Show</strong> to view the less comparison.</div>
</div>
<script>
const API = @json(url('/api/refinery-reports/less-comparison/data'));
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function fmtD(v){if(!v)return'';const p=String(v).split('-');return p.length===3?`${p[2]}/${p[1]}/${p[0].slice(-2)}`:v;}
function n3(v){return Number(v||0).toFixed(3);}
function n2(v){return Number(v||0).toFixed(2);}
document.getElementById('filterForm').addEventListener('submit', async e=>{
  e.preventDefault();
  document.getElementById('metaInfo').textContent='Loading…';
  const p=new URLSearchParams({
    date1:document.getElementById('date1').value,
    date2:document.getElementById('date2').value,
    refcode:document.getElementById('refcode').value,
  });
  try{
    const res=await fetch(`${API}?${p}`,{headers:{Accept:'application/json'}});
    const d=await res.json();
    if(!d.ok)throw new Error(d.message||'Error');
    const rows=d.rows||[];const t=d.totals||{};
    document.getElementById('reportBody').innerHTML=rows.map(r=>`<tr>
      <td>${fmtD(r.tdate)}</td>
      <td><strong>${esc(r.docno)}</strong></td>
      <td>${esc(r.refinername)}</td>
      <td>${esc(r.itemname)}</td>
      <td class="num">${n3(r.issuedwgt)}</td>
      <td class="num">${r.touch>0?n2(r.touch):''}</td>
      <td class="num">${r.rcvdwgt>0?n3(r.rcvdwgt):''}</td>
      <td class="num">${r.rcvdtouch>0?n2(r.rcvdtouch):''}</td>
      <td class="num">${r.mudless>0?n3(r.mudless):''}</td>
      <td class="num">${r.testpcs>0?n3(r.testpcs):''}</td>
      <td class="num">${r.expless!==0?n3(r.expless):''}</td>
      <td class="num ${r.actless>0?'neg':''}">${r.actless!==0?n3(r.actless):''}</td>
      <td class="num ${r.diff>0?'neg':r.diff<0?'pos':''}">${r.diff!==0?n3(r.diff):''}</td>
      <td class="num">${r.testperc>0?n2(r.testperc)+'%':''}</td>
      <td class="num">${r.expwgt>0?n3(r.expwgt):''}</td>
    </tr>`).join('');
    document.getElementById('totIssued').textContent=n3(t.issuedwgt);
    document.getElementById('totRcvd').textContent=n3(t.rcvdwgt);
    document.getElementById('totMudless').textContent=n3(t.mudless);
    document.getElementById('totTestpcs').textContent=n3(t.testpcs);
    document.getElementById('totExpless').textContent=n3(t.expless);
    document.getElementById('totActless').textContent=n3(t.actless);
    document.getElementById('totDiff').textContent=n3(t.diff);
    document.getElementById('totCount').textContent=`${rows.length} row(s)`;
    document.getElementById('metaInfo').textContent=`${rows.length} record(s)`;
    document.getElementById('tableWrap').style.display=rows.length?'':'none';
    document.getElementById('emptyState').style.display=rows.length?'none':'';
    if(!rows.length)document.getElementById('emptyState').textContent='No records found for forward bills.';
  }catch(err){
    document.getElementById('metaInfo').textContent=err.message;
    document.getElementById('tableWrap').style.display='none';
    document.getElementById('emptyState').style.display='';
    document.getElementById('emptyState').textContent=err.message;
  }
});
document.getElementById('exitBtn').addEventListener('click',()=>window.parent.postMessage({type:'goldapp:close-module-frame'},'*'));
</script>
</body>
</html>
