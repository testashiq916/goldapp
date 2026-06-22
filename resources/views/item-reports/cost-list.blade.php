<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><title>{{ $title }}</title>
<style>
body{font-family:"Segoe UI",Tahoma,sans-serif;margin:0;background:#f3f6fb;color:#1f2937}
.wrap{max-width:1600px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
h1{margin:0 0 4px;font-size:20px;color:#173b63}
.sub{color:#5b7088;font-size:13px;margin-bottom:12px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px}
.field{display:flex;flex-direction:column;gap:3px}
label{font-size:14px;font-weight:700;color:#375b84}
input,select{height:32px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:18px;box-sizing:border-box}
button{height:32px;padding:0 16px;cursor:pointer;background:#e8f2ff;border:1px solid #2a6398;border-radius:5px;color:#17456e;font-weight:700;font-size:14px}
button.primary{background:#e6f8ec;border-color:#2a7a42;color:#1b5b31}
.meta{font-size:13px;color:#48627c;margin-bottom:8px}
.table-wrap{border:1px solid #d8e2ef;border-radius:8px;overflow:auto;max-height:72vh}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{border-bottom:1px solid #e5ecf5;padding:6px 8px}
th{position:sticky;top:0;background:#edf4fc;font-weight:700;z-index:1;white-space:nowrap;text-align:center}
td.num{text-align:right;font-family:'Courier New',monospace;white-space:nowrap}
tfoot td{background:#f0f7ff;font-weight:700;color:#173b63;position:sticky;bottom:0}
.badge{display:inline-block;padding:1px 7px;border-radius:10px;font-size:10px;font-weight:700}
.badge-G{background:#fff3cd;color:#856404}.badge-S{background:#e9ecef;color:#495057}.badge-O{background:#ffe5d0;color:#7a3b00}
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
    <div class="field">
        <label>Item Type</label>
        <select id="itype">
            <option value="">All</option>
            <option value="G">Gold</option>
            <option value="S">Silver</option>
            <option value="O">Others</option>
        </select>
    </div>
    <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="submit" class="primary">Show</button></div>
    <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" onclick="window.print()">Print</button></div>
</form>
<div class="meta" id="metaInfo">Select filters and click <strong>Show</strong>.</div>
<div class="table-wrap" id="tableWrap" style="display:none">
    <table>
        <thead><tr>
            <th style="text-align:left">Code</th><th style="text-align:left">Name</th><th>Type</th>
            <th>Touch</th><th>Wastage</th><th>Cost</th><th>Sale Rate</th><th>WS Rate</th><th>P.Rate</th><th>Mode</th>
        </tr></thead>
        <tbody id="reportBody"></tbody>
        <tfoot><tr>
            <td colspan="3" id="totLabel">Total</td>
            <td></td><td></td>
            <td class="num" id="totCost"></td>
            <td class="num" id="totRate"></td>
            <td class="num" id="totWsrate"></td>
            <td class="num" id="totPrate"></td>
            <td></td>
        </tr></tfoot>
    </table>
</div>
<div class="empty" id="emptyState">Select filters and click <strong>Show</strong>.</div>
</div>
<script>
const API=@json(url('/api/cost-list/data'));
function fmtN(v,d=2){const n=Number(v||0);return n===0?'':n.toFixed(d);}
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function badge(t){const k=String(t??'').trim().toUpperCase().charAt(0);
    const m={G:'Gold',S:'Silver'};const c=m[k]?k:'O';return`<span class="badge badge-${c}">${m[k]||'Others'}</span>`;}
document.getElementById('filterForm').addEventListener('submit',async e=>{
    e.preventDefault();
    document.getElementById('metaInfo').textContent='Loading…';
    const p=new URLSearchParams({itype:document.getElementById('itype').value});
    try{
        const res=await fetch(`${API}?${p}`,{headers:{Accept:'application/json'}});
        const d=await res.json();
        if(!d.ok)throw new Error(d.message||'Error');
        const rows=d.rows||[];
        let tCost=0,tRate=0,tWs=0,tPr=0;
        document.getElementById('reportBody').innerHTML=rows.map(r=>{
            tCost+=Number(r.cost||0);tRate+=Number(r.rate||0);tWs+=Number(r.wsrate||0);tPr+=Number(r.prate||0);
            const mode=String(r.stkinnos??'').trim().toUpperCase()==='Y'?'Qty':'Wgt';
            return`<tr>
            <td>${esc(r.code)}</td><td>${esc(r.name)}</td><td style="text-align:center">${badge(r.itype)}</td>
            <td class="num">${fmtN(r.touch,3)}</td><td class="num">${fmtN(r.wastage,3)}</td>
            <td class="num">${fmtN(r.cost,2)}</td><td class="num">${fmtN(r.rate,2)}</td>
            <td class="num">${fmtN(r.wsrate,2)}</td><td class="num">${fmtN(r.prate,2)}</td>
            <td style="text-align:center;font-size:10px">${mode}</td>
            </tr>`;
        }).join('');
        document.getElementById('totLabel').textContent=`Total (${rows.length})`;
        document.getElementById('totCost').textContent=fmtN(tCost,2)||'';
        document.getElementById('totRate').textContent=fmtN(tRate,2)||'';
        document.getElementById('totWsrate').textContent=fmtN(tWs,2)||'';
        document.getElementById('totPrate').textContent=fmtN(tPr,2)||'';
        document.getElementById('metaInfo').textContent=`${rows.length} item(s) found`;
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
</script>
</body></html>
