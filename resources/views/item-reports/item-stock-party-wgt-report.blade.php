<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><title>{{ $title }}</title>
<style>
body{font-family:"Segoe UI",Tahoma,sans-serif;margin:0;background:#f3f6fb;color:#1f2937}
.wrap{max-width:1400px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
h1{margin:0 0 4px;font-size:20px;color:#173b63}
.sub{color:#5b7088;font-size:13px;margin-bottom:12px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px}
.field{display:flex;flex-direction:column;gap:3px}
label{font-size:14px;font-weight:700;color:#375b84}
select{height:32px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:14px}
button{height:32px;padding:0 16px;cursor:pointer;background:#e8f2ff;border:1px solid #2a6398;border-radius:5px;color:#17456e;font-weight:700;font-size:14px}
button.primary{background:#e6f8ec;border-color:#2a7a42;color:#1b5b31}
.meta{font-size:13px;color:#48627c;margin-bottom:8px}
.table-wrap{border:1px solid #d8e2ef;border-radius:8px;overflow:auto;max-height:72vh}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{border-bottom:1px solid #e5ecf5;padding:6px 8px}
th{position:sticky;top:0;background:#edf4fc;font-weight:700;z-index:1;white-space:nowrap;text-align:center}
th.grp-stk{background:#dce8f5}th.grp-pty{background:#f5e8dc}
td.stk{text-align:right;font-family:'Courier New',monospace;color:#1a5276;white-space:nowrap}
td.pty{text-align:right;font-family:'Courier New',monospace;color:#784212;white-space:nowrap}
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
    <div class="field">
        <label>Item Type</label>
        <select id="itype">
            <option value="">All</option>
            <option value="G">Gold</option>
            <option value="S">Silver</option>
            <option value="O">Others</option>
        </select>
    </div>
    <div class="field">
        <label>Stk Type</label>
        <select id="stktype">
            <option value="">All</option>
            @foreach($stockTypes as $st)
            <option value="{{ trim((string)$st->code) }}">{{ trim((string)$st->code) }} — {{ trim((string)$st->name) }}</option>
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
            <th rowspan="2" style="text-align:left">Code</th>
            <th rowspan="2" style="text-align:left">Name</th>
            <th rowspan="2">Type</th>
            <th colspan="3" class="grp-stk">Stock</th>
            <th colspan="2" class="grp-pty">Party Wgt</th>
        </tr>
        <tr>
            <th class="grp-stk">Qty</th><th class="grp-stk">Weight</th><th class="grp-stk">St.Wgt</th>
            <th class="grp-pty">Qty</th><th class="grp-pty">Weight</th>
        </tr>
        </thead>
        <tbody id="reportBody"></tbody>
        <tfoot><tr>
            <td colspan="3">Total</td>
            <td class="stk" id="totStkQty">0</td>
            <td class="stk" id="totStkWgt">0.000</td>
            <td class="stk" id="totStkStwgt">0.000</td>
            <td class="pty" id="totPtyQty">0</td>
            <td class="pty" id="totPtyWgt">0.000</td>
        </tr></tfoot>
    </table>
</div>
<div class="empty" id="emptyState">Select filters and click <strong>Show</strong>.</div>
</div>
<script>
const API=@json(url('/api/item-stock-party-wgt-report/data'));
function fmtN(v,d=3){const n=Number(v||0);return n===0?'':n.toFixed(d);}
function fmtN0(v){const n=Number(v||0);return n===0?'':String(n);}
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function itype(t){const k=String(t??'').trim().toUpperCase().charAt(0);return k||'-';}
document.getElementById('filterForm').addEventListener('submit',async e=>{
    e.preventDefault();
    document.getElementById('metaInfo').textContent='Loading…';
    const p=new URLSearchParams({itype:document.getElementById('itype').value,stktype:document.getElementById('stktype').value});
    try{
        const res=await fetch(`${API}?${p}`,{headers:{Accept:'application/json'}});
        const d=await res.json();
        if(!d.ok)throw new Error(d.message||'Error');
        const rows=d.rows||[];const t=d.totals||{};
        document.getElementById('reportBody').innerHTML=rows.map(r=>`<tr>
            <td>${esc(r.code)}</td><td>${esc(r.name)}</td>
            <td style="text-align:center">${itype(r.itype)}</td>
            <td class="stk">${fmtN0(r.stk_qty)}</td>
            <td class="stk">${fmtN(r.stk_wgt,3)}</td>
            <td class="stk">${fmtN(r.stk_stwgt,3)}</td>
            <td class="pty">${fmtN0(r.party_qty)}</td>
            <td class="pty">${fmtN(r.party_wgt,3)}</td>
        </tr>`).join('');
        document.getElementById('totStkQty').textContent=fmtN0(t.stk_qty)||'0';
        document.getElementById('totStkWgt').textContent=(t.stk_wgt||0).toFixed(3);
        document.getElementById('totStkStwgt').textContent=(t.stk_stwgt||0).toFixed(3);
        document.getElementById('totPtyQty').textContent=fmtN0(t.party_qty)||'0';
        document.getElementById('totPtyWgt').textContent=(t.party_wgt||0).toFixed(3);
        document.getElementById('metaInfo').textContent=`${rows.length} item(s) with stock or party weight`;
        document.getElementById('tableWrap').style.display=rows.length?'':'none';
        document.getElementById('emptyState').style.display=rows.length?'none':'';
        if(!rows.length)document.getElementById('emptyState').textContent='No items with stock or party weight found.';
    }catch(err){
        document.getElementById('metaInfo').textContent=err.message;
        document.getElementById('tableWrap').style.display='none';
        document.getElementById('emptyState').style.display='';
        document.getElementById('emptyState').textContent=err.message;
    }
});
</script>
</body></html>
