<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><title>{{ $title }}</title>
<style>
body{font-family:"Segoe UI",Tahoma,sans-serif;margin:0;background:#f3f6fb;color:#1f2937}
.wrap{max-width:1600px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
h1{margin:0 0 4px;font-size:20px;color:#173b63}
.sub{color:#5b7088;font-size:11px;margin-bottom:12px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px}
.field{display:flex;flex-direction:column;gap:3px}
label{font-size:11px;font-weight:700;color:#375b84}
input{height:32px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:12px;box-sizing:border-box;width:140px;color:#000080;font-weight:700}
select{height:32px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:12px}
button{height:32px;padding:0 16px;cursor:pointer;background:#e8f2ff;border:1px solid #2a6398;border-radius:5px;color:#17456e;font-weight:700;font-size:12px}
button.primary{background:#e6f8ec;border-color:#2a7a42;color:#1b5b31}
.meta{font-size:12px;color:#48627c;margin-bottom:8px}
.table-wrap{border:1px solid #d8e2ef;border-radius:8px;overflow:auto;max-height:72vh}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border-bottom:1px solid #e5ecf5;padding:6px 8px}
th{position:sticky;top:0;background:#edf4fc;font-weight:700;z-index:1;white-space:nowrap;text-align:center}
th.grp-add{background:#e6f9ee}th.grp-less{background:#fff0f0}
td.add{text-align:right;font-family:'Courier New',monospace;color:#006600;white-space:nowrap}
td.less{text-align:right;font-family:'Courier New',monospace;color:#cc0000;white-space:nowrap}
tfoot td{background:#f0f7ff;font-weight:700;color:#173b63;position:sticky;bottom:0}
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
    <div class="field">
        <label>Stk Type</label>
        <select id="stktype">
            <option value="">All</option>
            @foreach($stockTypes as $st)
            <option value="{{ trim((string)$st->code) }}">{{ trim((string)$st->code) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="submit" class="primary">Show</button></div>
    <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" onclick="window.print()">Print</button></div>
</form>
<div class="meta" id="metaInfo">Select filters and click <strong>Show</strong>.</div>
<div class="table-wrap" id="tableWrap" style="display:none">
    <table>
        <thead>
        <tr>
            <th rowspan="2">SNo</th><th rowspan="2">Date</th><th rowspan="2">Code</th>
            <th rowspan="2" style="text-align:left">Item</th><th rowspan="2">StkType</th>
            <th colspan="3" class="grp-add">Add</th>
            <th colspan="3" class="grp-less">Less</th>
        </tr>
        <tr>
            <th class="grp-add">Qty</th><th class="grp-add">Weight</th><th class="grp-add">Net Wgt</th>
            <th class="grp-less">Qty</th><th class="grp-less">Weight</th><th class="grp-less">Net Wgt</th>
        </tr>
        </thead>
        <tbody id="reportBody"></tbody>
        <tfoot><tr>
            <td colspan="5">Total</td>
            <td class="add" id="totAddQty">0</td>
            <td class="add" id="totAddWgt">0.000</td>
            <td></td>
            <td class="less" id="totLessQty">0</td>
            <td class="less" id="totLessWgt">0.000</td>
            <td></td>
        </tr></tfoot>
    </table>
</div>
<div class="empty" id="emptyState">Select filters and click <strong>Show</strong>.</div>
</div>
<script>
const API=@json(url('/api/trans-ra-report/data'));
function fmtN(v,d=3){const n=Number(v||0);return n===0?'':n.toFixed(d);}
function fmtN0(v){const n=Number(v||0);return n===0?'':String(n);}
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function fmtDate(v){if(!v)return'';const p=String(v).split('-');return p.length===3?`${p[2]}/${p[1]}/${p[0].slice(-2)}`:v;}
document.getElementById('filterForm').addEventListener('submit',async e=>{
    e.preventDefault();
    document.getElementById('metaInfo').textContent='Loading…';
    const p=new URLSearchParams({
        date1:document.getElementById('date1').value,
        date2:document.getElementById('date2').value,
        stktype:document.getElementById('stktype').value,
    });
    try{
        const res=await fetch(`${API}?${p}`,{headers:{Accept:'application/json'}});
        const d=await res.json();
        if(!d.ok)throw new Error(d.message||'Error');
        const rows=d.rows||[];const t=d.totals||{};
        document.getElementById('reportBody').innerHTML=rows.map(r=>`<tr>
            <td style="text-align:center">${esc(r.sno)}</td><td>${fmtDate(r.tdate)}</td>
            <td style="text-align:center">${esc(r.code)}</td>
            <td>${esc(r.iname||r.code)}</td>
            <td style="text-align:center">${esc(r.stktype||'')}</td>
            <td class="add">${fmtN0(r.addqty)}</td><td class="add">${fmtN(r.addwgt,3)}</td><td class="add">${fmtN(r.addnetwgt,3)}</td>
            <td class="less">${fmtN0(r.lessqty)}</td><td class="less">${fmtN(r.lesswgt,3)}</td><td class="less">${fmtN(r.lessnetwgt,3)}</td>
        </tr>`).join('');
        document.getElementById('totAddQty').textContent=fmtN0(t.addqty)||'0';
        document.getElementById('totAddWgt').textContent=(t.addwgt||0).toFixed(3);
        document.getElementById('totLessQty').textContent=fmtN0(t.lessqty)||'0';
        document.getElementById('totLessWgt').textContent=(t.lesswgt||0).toFixed(3);
        document.getElementById('metaInfo').textContent=`${rows.length} record(s)`;
        document.getElementById('tableWrap').style.display=rows.length?'':'none';
        document.getElementById('emptyState').style.display=rows.length?'none':'';
        if(!rows.length)document.getElementById('emptyState').textContent='No Trans RA records found.';
    }catch(err){
        document.getElementById('metaInfo').textContent=err.message;
        document.getElementById('tableWrap').style.display='none';
        document.getElementById('emptyState').style.display='';
        document.getElementById('emptyState').textContent=err.message;
    }
});
</script>
</body></html>
