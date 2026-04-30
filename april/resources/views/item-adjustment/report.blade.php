<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><title>{{ $title }}</title>
<style>
body{font-family:"Segoe UI",Tahoma,sans-serif;margin:0;background:#f3f6fb;color:#1f2937}
.wrap{max-width:1800px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
h1{margin:0 0 4px;font-size:20px;color:#173b63}
.sub{color:#5b7088;font-size:11px;margin-bottom:12px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px}
.field{display:flex;flex-direction:column;gap:3px}
label{font-size:11px;font-weight:700;color:#375b84}
input[type=date]{height:32px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:12px;box-sizing:border-box;width:140px;color:#000080;font-weight:700}
input[type=text]{height:32px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:12px;box-sizing:border-box;width:100px;text-transform:uppercase}
select{height:32px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:12px}
.cb-row{display:flex;align-items:center;gap:5px;height:32px;font-size:12px}
button{height:32px;padding:0 16px;cursor:pointer;background:#e8f2ff;border:1px solid #2a6398;border-radius:5px;color:#17456e;font-weight:700;font-size:12px}
button.primary{background:#e6f8ec;border-color:#2a7a42;color:#1b5b31}
.meta{font-size:12px;color:#48627c;margin-bottom:8px}
.table-wrap{border:1px solid #d8e2ef;border-radius:8px;overflow:auto;max-height:70vh}
table{width:100%;border-collapse:collapse;font-size:11px}
th,td{border-bottom:1px solid #e5ecf5;padding:5px 6px}
th{position:sticky;top:0;background:#edf4fc;font-weight:700;z-index:1;white-space:nowrap;text-align:center}
th.grp-fr{background:#e0f0ff}th.grp-to{background:#fff0e0}
td.numf{text-align:right;font-family:'Courier New',monospace;color:#1a5276;white-space:nowrap}
td.numt{text-align:right;font-family:'Courier New',monospace;color:#7a3000;white-space:nowrap}
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
    <div class="field"><label>From Code</label><input type="text" id="fromcode" placeholder="(All)"></div>
    <div class="field"><label>To Code</label><input type="text" id="tocode" placeholder="(All)"></div>
    <div class="field"><label>Any Code</label><input type="text" id="anycode" placeholder="(All)"></div>
    <div class="field">
        <label>SM</label>
        <select id="smcode">
            <option value="">All</option>
            @foreach($salesmen as $sm)
            <option value="{{ trim((string)$sm->code) }}">{{ trim((string)$sm->code) }} — {{ trim((string)$sm->name) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Fr.StkT</label>
        <select id="fromstktype">
            <option value="">All</option>
            @foreach($stockTypes as $st)
            <option value="{{ trim((string)$st->code) }}">{{ trim((string)$st->code) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>To.StkT</label>
        <select id="tostktype">
            <option value="">All</option>
            @foreach($stockTypes as $st)
            <option value="{{ trim((string)$st->code) }}">{{ trim((string)$st->code) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Type</label>
        <select id="trantype">
            <option value="All">All</option>
            <option value="Stock">Stock Transfer</option>
            <option value="Item">Item Change</option>
            <option value="Add">Add/Less</option>
        </select>
    </div>
    <div class="field">
        <label>Reason</label>
        <select id="reason">
            <option value="">All</option>
        </select>
    </div>
    <div class="field">
        <label>&nbsp;</label>
        <div class="cb-row"><input type="checkbox" id="noal"><label for="noal">No Add/Less</label></div>
    </div>
    <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="submit" class="primary">Show</button></div>
    <div class="field" style="justify-content:flex-end;margin-top:auto"><button type="button" onclick="window.print()">Print</button></div>
</form>
<div class="meta" id="metaInfo">Set filters and click <strong>Show</strong>.</div>
<div class="table-wrap" id="tableWrap" style="display:none">
    <table>
        <thead>
        <tr>
            <th rowspan="2">Date</th><th rowspan="2">Time</th><th rowspan="2">BCode</th>
            <th colspan="4" class="grp-fr">From Item</th>
            <th colspan="4" class="grp-to">To Item</th>
            <th rowspan="2">SM</th><th rowspan="2" style="text-align:left">Particular</th>
        </tr>
        <tr>
            <th class="grp-fr" style="text-align:left">Item</th><th class="grp-fr">Qty</th><th class="grp-fr">Weight</th><th class="grp-fr">St.Wgt</th>
            <th class="grp-to" style="text-align:left">Item</th><th class="grp-to">Qty</th><th class="grp-to">Weight</th><th class="grp-to">St.Wgt</th>
        </tr>
        </thead>
        <tbody id="reportBody"></tbody>
        <tfoot><tr>
            <td colspan="3">Total</td>
            <td></td>
            <td class="numf" id="totFromQty">0</td>
            <td class="numf" id="totFromWgt">0.000</td>
            <td class="numf" id="totFromSt">0.000</td>
            <td></td>
            <td class="numt" id="totToQty">0</td>
            <td class="numt" id="totToWgt">0.000</td>
            <td class="numt" id="totToSt">0.000</td>
            <td colspan="2" id="totCount"></td>
        </tr></tfoot>
    </table>
</div>
<div class="empty" id="emptyState">Set filters and click <strong>Show</strong> to view adjustments.</div>
</div>
<script>
const API=@json(url('/api/item-adjustment-report/data'));
function fmtN(v,d=3){const n=Number(v||0);return n===0?'':n.toFixed(d);}
function fmtN0(v){const n=Number(v||0);return n===0?'':String(n);}
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function fmtDate(v){if(!v)return'';const p=String(v).split('-');return p.length===3?`${p[2]}/${p[1]}/${p[0].slice(-2)}`:v;}
function fmtTime(v){if(!v)return'';const b=String(v).split(':');if(b.length<2)return v;
    let hh=Number(b[0]||0);const mm=String(b[1]||'00').padStart(2,'0');const ap=hh>=12?'PM':'AM';hh=hh%12||12;return`${hh}:${mm} ${ap}`;}
document.getElementById('filterForm').addEventListener('submit',async e=>{
    e.preventDefault();
    document.getElementById('metaInfo').textContent='Loading…';
    const p=new URLSearchParams({
        date1:document.getElementById('date1').value,
        date2:document.getElementById('date2').value,
        fromcode:document.getElementById('fromcode').value.trim(),
        tocode:document.getElementById('tocode').value.trim(),
        anycode:document.getElementById('anycode').value.trim(),
        smcode:document.getElementById('smcode').value,
        fromstktype:document.getElementById('fromstktype').value,
        tostktype:document.getElementById('tostktype').value,
        trantype:document.getElementById('trantype').value,
        reason:document.getElementById('reason').value,
        noal:document.getElementById('noal').checked?'1':'0',
    });
    try{
        const res=await fetch(`${API}?${p}`,{headers:{Accept:'application/json'}});
        const d=await res.json();
        if(!d.ok)throw new Error(d.message||'Error');
        const rows=d.rows||[];const t=d.totals||{};
        document.getElementById('reportBody').innerHTML=rows.map(r=>`<tr>
            <td>${fmtDate(r.tdate)}</td><td>${fmtTime(r.ttime)}</td>
            <td style="text-align:center">${esc(r.bcode||'')}</td>
            <td>${esc(r.fromname||r.fromcode||'')}<br><small style="color:#5b7088">${esc(r.fromcode||'')}</small></td>
            <td class="numf">${fmtN0(r.fromqty)}</td>
            <td class="numf">${fmtN(r.fromwgt,3)}</td>
            <td class="numf">${fmtN(r.fromstwgt,3)}</td>
            <td>${esc(r.toname||r.tocode||'')}<br><small style="color:#5b7088">${esc(r.tocode||'')}</small></td>
            <td class="numt">${fmtN0(r.toqty)}</td>
            <td class="numt">${fmtN(r.towgt,3)}</td>
            <td class="numt">${fmtN(r.tostwgt,3)}</td>
            <td style="text-align:center">${esc(r.smcode||'')}</td>
            <td>${esc(r.particular||'')}</td>
        </tr>`).join('');
        document.getElementById('totFromQty').textContent=fmtN0(t.fromqty)||'0';
        document.getElementById('totFromWgt').textContent=(t.fromwgt||0).toFixed(3);
        document.getElementById('totFromSt').textContent=(t.fromstwgt||0).toFixed(3);
        document.getElementById('totToQty').textContent=fmtN0(t.toqty)||'0';
        document.getElementById('totToWgt').textContent=(t.towgt||0).toFixed(3);
        document.getElementById('totToSt').textContent=(t.tostwgt||0).toFixed(3);
        document.getElementById('totCount').textContent=`${rows.length} record(s)`;
        document.getElementById('metaInfo').textContent=`${rows.length} adjustment record(s)`;
        document.getElementById('tableWrap').style.display=rows.length?'':'none';
        document.getElementById('emptyState').style.display=rows.length?'none':'';
        if(!rows.length)document.getElementById('emptyState').textContent='No adjustment records found.';
    }catch(err){
        document.getElementById('metaInfo').textContent=err.message;
        document.getElementById('tableWrap').style.display='none';
        document.getElementById('emptyState').style.display='';
        document.getElementById('emptyState').textContent=err.message;
    }
});
</script>
</body></html>
