<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barcode List</title>
<style>
:root{--hdr:#0c4a6e;--hdr2:#0369a1;--border:#d0dbe8;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#0284c7}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#e0f2fe 0%,#f0f9ff 40%,#e8f4fc 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(12,74,110,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:10px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}
.toolbar{background:#f0f9ff;border-bottom:1px solid var(--border);padding:8px 12px}
.tb-row{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:4px}
.tb-row:last-child{margin-bottom:0}
.tb-lbl{font-size:11px;font-weight:700;color:#0c4a6e;white-space:nowrap}
.tb-input{height:26px;border:1px solid #bae6fd;border-radius:5px;padding:0 6px;font-size:12px;background:#fff;color:var(--text)}
.tb-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(2,132,199,.15)}
.tb-input[type="text"]{text-transform:uppercase}
.tb-select{height:26px;border:1px solid #bae6fd;border-radius:5px;padding:0 4px;font-size:11px;background:#fff}
.tb-sep{width:1px;height:22px;background:#bae6fd;margin:0 2px}
.tb-chk{display:flex;align-items:center;gap:3px;font-size:11px;color:#0c4a6e;cursor:pointer;white-space:nowrap}
.tb-chk input{margin:0}
.btn{height:26px;border-radius:5px;border:1px solid #b0c4de;padding:0 10px;font-size:11px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:3px;background:#f0f4f8;color:#1e3a5f;white-space:nowrap}
.btn:hover{background:#e0eaf4}.btn:active{background:#d0daea}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff;border-color:var(--hdr)}
.btn-primary:hover{opacity:.9}
.btn-green{background:#e6f8ec;color:#1b5b31;border-color:#2a7a42}
.btn-red{background:#fee2e2;color:#991b1b;border-color:#ef4444}
.btn-blue{background:#dbeafe;color:#1e40af;border-color:#3b82f6}
.btn-amber{background:#fef3c7;color:#92400e;border-color:#f59e0b}
.content{padding:10px 12px}
.info-bar{font-size:12px;color:var(--muted);margin-bottom:6px;display:flex;justify-content:space-between}
.hint{padding:40px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed #bae6fd;border-radius:10px;margin:10px 0}
.tbl-wrap{max-height:58vh;overflow:auto;border:1px solid var(--border);border-radius:6px}
table{width:100%;border-collapse:collapse;font-size:11px}
th{position:sticky;top:0;z-index:2;padding:4px 5px;text-align:left;font-size:10px;font-weight:700;white-space:nowrap}
th.h1{background:linear-gradient(180deg,#0c4a6e,#0a3d5c);color:#bae6fd}
th.h2{background:linear-gradient(180deg,#1e3a5f,#162d4a);color:#bfd8f0}
th.h3{background:linear-gradient(180deg,#3d5c2a,#2e4a1f);color:#d4f0b8}
th.h4{background:linear-gradient(180deg,#7a5c00,#614a00);color:#fff5d4}
th.h5{background:linear-gradient(180deg,#5c1a5c,#4a104a);color:#f0d4f0}
th.h6{background:linear-gradient(180deg,#5c2a2a,#4a1f1f);color:#f0d4d4}
th.num,td.num{text-align:right}
th.ctr,td.ctr{text-align:center}
td{border-bottom:1px solid #e0f2fe;padding:3px 5px;white-space:nowrap;font-variant-numeric:tabular-nums}
tr:hover td{background:#f0f9ff}
tr.sold td{opacity:.5}
tr.selected td{background:#dbeafe !important}
.badge{display:inline-block;padding:1px 5px;border-radius:8px;font-size:9px;font-weight:700}
.badge-stk{background:#d1fae5;color:#065f46}.badge-sold{background:#fee2e2;color:#991b1b}
tfoot td{position:sticky;bottom:0;background:#e0f2fe;font-weight:700;color:#0c4a6e;border-top:2px solid #bae6fd;z-index:2}
.summary-bar{margin-top:8px;padding:10px 14px;background:linear-gradient(135deg,var(--hdr),var(--hdr2));border-radius:8px;display:flex;gap:16px;flex-wrap:wrap;align-items:center;color:#fff}
.summary-bar .f{display:flex;gap:4px;align-items:baseline}
.summary-bar .lbl{font-size:10px;font-weight:600;color:rgba(255,255,255,.7)}
.summary-bar .val{font-size:12px;font-weight:700;font-variant-numeric:tabular-nums}
.summary-bar .divider{width:1px;height:18px;background:rgba(255,255,255,.2)}
.count-bar{text-align:right;font-size:11px;color:var(--muted);margin-top:4px}
.toast{position:fixed;bottom:20px;right:20px;background:#1e3a5f;color:#fff;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;z-index:9999;display:none;box-shadow:0 4px 12px rgba(0,0,0,.2)}
.toast.show{display:block;animation:fadeInOut 2s ease}
@keyframes fadeInOut{0%{opacity:0;transform:translateY(10px)}10%{opacity:1;transform:translateY(0)}80%{opacity:1}100%{opacity:0}}
@media print{.toolbar,.toast{display:none}body{background:#fff}.window{margin:0;box-shadow:none;border-radius:0}.tbl-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}.summary-bar{background:#f0f0f0;color:#000;border-radius:0}.summary-bar .lbl{color:#555}td input[type="checkbox"]{display:none}.act-col,.h5{display:none}}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
@php
    function bcF(float $n, int $d = 2): string { return number_format($n, $d, '.', ','); }
    function bcD(?string $d): string { if (!$d) return ''; $ts = strtotime($d); return $ts ? date('d/m/Y', $ts) : $d; }
@endphp
<div class="window">
    <div class="titlebar"><h1>Barcode List</h1><span class="today">{{ date('d/m/Y') }}</span></div>

    <div class="toolbar">
        <form method="GET" id="reportForm">
        {{-- Row 1: From/To + DocNo + Show/Sort/SaveAs/Print/Exit --}}
        <div class="tb-row">
            <span class="tb-lbl">From:</span><input type="date" name="date1" value="{{ $dateFrom }}" class="tb-input" style="width:128px">
            <span class="tb-lbl">To:</span><input type="date" name="date2" value="{{ $dateTo }}" class="tb-input" style="width:128px">
            <div class="tb-sep"></div>
            <span class="tb-lbl">Doc No:</span><input type="text" name="docno" value="{{ $docNo }}" class="tb-input" style="width:80px">
            <div class="tb-sep"></div>
            <button type="submit" name="show" value="1" class="btn btn-primary">Show</button>
            <button type="button" class="btn" id="btnSort">Sort</button>
            <button type="button" class="btn btn-green" id="btnSaveAs">Save As</button>
            <button type="button" class="btn" onclick="window.print()">Print</button>
        </div>
        {{-- Row 2: Item + Stock + BCNo + Search + Rename + SelectAll + BarcodePrint --}}
        <div class="tb-row">
            <span class="tb-lbl">Item:</span><input type="text" name="item_code" value="{{ $itemCode }}" class="tb-input" style="width:80px">
            <select name="stk_type" class="tb-select" style="width:90px">
                @foreach(['All','In Stock','Sold'] as $o)<option value="{{ $o }}" {{ $stkType===$o?'selected':'' }}>{{ $o }}</option>@endforeach
            </select>
            <div class="tb-sep"></div>
            <span class="tb-lbl">BC No:</span><input type="text" name="bcno" value="{{ $bcNo }}" class="tb-input" style="width:100px">
            <div class="tb-sep"></div>
            <button type="button" class="btn btn-blue" id="btnSelectAll">Select All</button>
            <button type="button" class="btn btn-amber" id="btnBcPrint">Barcode Print</button>
        </div>
        {{-- Row 3: Group + Metal + Supplier + Model + PhChk + Filter + Edit --}}
        <div class="tb-row">
            <span class="tb-lbl">Group:</span>
            <select name="grp" class="tb-select" style="width:80px"><option value="">All</option>@foreach($groupCodes as $gc)<option value="{{ $gc }}" {{ $grpCode===$gc?'selected':'' }}>{{ $gc }}</option>@endforeach</select>
            <select name="itype" class="tb-select" style="width:80px">@foreach(['All','Gold','Silver','Platinum','Diamond','Others'] as $o)<option value="{{ $o }}" {{ $itype===$o?'selected':'' }}>{{ $o }}</option>@endforeach</select>
            <div class="tb-sep"></div>
            <span class="tb-lbl">Model:</span><input type="text" name="model" value="{{ $model }}" class="tb-input" style="width:80px">
            <div class="tb-sep"></div>
            <button type="button" class="btn" id="btnPhChk">Ph Chk</button>
            <button type="button" class="btn" id="btnFilter">Filter</button>
            <button type="button" class="btn" id="btnEdit">Edit</button>
        </div>
        {{-- Row 4: CostCompare + CPMC + SubGrp + Search + DeleteAll --}}
        <div class="tb-row">
            <label class="tb-chk"><input type="checkbox" id="cbCostComp"> Cost Compare</label>
            <label class="tb-chk"><input type="checkbox" id="cbCpmc"> CPMC,St.Amt List</label>
            <div class="tb-sep"></div>
            <span class="tb-lbl">Barcode SubGrp:</span><input type="text" name="subgrp" value="{{ $subGrp }}" class="tb-input" style="width:70px">
            <div class="tb-sep"></div>
            <span class="tb-lbl">Search:</span><input type="text" id="tblSearch" class="tb-input" style="width:120px" placeholder="Type...">
            <div class="tb-sep"></div>
            <button type="button" class="btn btn-red" id="btnDelAll">Delete all Barcodes</button>
        </div>
        </form>
    </div>

    <div class="content">
    @if (!$showData)
        <div class="hint">Select date range and filters, then click <strong>Show</strong> to view barcodes.</div>
    @elseif (empty($rows))
        <div class="hint">No barcodes found for the selected criteria.</div>
    @else
        <div class="info-bar">
            <span><strong>Barcode List</strong> From {{ bcD($dateFrom) }} To {{ bcD($dateTo) }}
                @if($stkType!=='All') &middot; {{ $stkType }} @endif @if($itype!=='All') &middot; {{ $itype }} @endif
            </span>
            <span>{{ date('d-m-Y') }}</span>
        </div>
        <div class="tbl-wrap">
        <table id="bcTable">
            <thead><tr>
                {{-- PB exact column order --}}
                <th class="h1">Date</th>
                <th class="h1">SCode</th>
                <th class="h1">Barcode</th>
                <th class="h2">Code</th>
                <th class="h2">Name</th>
                <th class="h2">HUID</th>
                <th class="h2">Size</th>
                <th class="h2">Model</th>
                <th class="h3 num">Qty</th>
                <th class="h3 num">Weight</th>
                <th class="h4 num">VA%</th>
                <th class="h3 num">Stone</th>
                <th class="h4 num">Stone Amt</th>
                <th class="h4 num">DmdWt</th>
                <th class="h4 num">Dmd Amt</th>
                <th class="h4 num">Rate</th>
                <th class="h1 ctr">Stock</th>
                <th class="h5 ctr">Print</th>
                <th class="h5 ctr">Ph</th>
                <th class="h2">Counter</th>
                <th class="h2">Nos</th>
                <th class="h6 num">Cost%</th>
                <th class="h6 num">TCost/gm</th>
                <th class="h6 num">CostMC/gm</th>
                <th class="h6 num">CostStAmt</th>
                <th class="h2">Stats</th>
            </tr></thead>
            <tbody>
                @foreach ($rows as $r)
                @php $sold = strtoupper($r['stk'] ?? 'Y') !== 'Y'; @endphp
                <tr class="{{ $sold ? 'sold' : '' }}">
                    <td>{{ bcD($r['tdate']) }}</td>
                    <td>{{ $r['smithcode'] ?? '' }}</td>
                    <td style="font-weight:700">{{ $r['bcode'] }}</td>
                    <td>{{ $r['icode'] }}</td>
                    <td>{{ $r['itemname'] ?? '' }}</td>
                    <td>{{ $r['huid'] ?? '' }}</td>
                    <td>{{ $r['sizemodel'] ?? '' }}</td>
                    <td>{{ $r['model'] ?? '' }}</td>
                    <td class="num">{{ (int)$r['qty'] }}</td>
                    <td class="num">{{ bcF((float)$r['weight'],3) }}</td>
                    <td class="num">@if(($r['vap']??0)>0){{ bcF((float)$r['vap']) }}@elseif(($r['mcrate']??0)>0){{ bcF((float)$r['mcrate']) }}@elseif(($r['mc']??0)>0){{ bcF((float)$r['mc']) }}@endif</td>
                    <td class="num">{{ bcF((float)$r['stweight'],3) }}</td>
                    <td class="num">{{ bcF((float)$r['stprice']) }}</td>
                    <td class="num">{{ bcF((float)($r['dmdwgt']??0),3) }}</td>
                    <td class="num">{{ bcF((float)($r['dmdamt']??0)) }}</td>
                    <td class="num">{{ bcF((float)($r['rate']??0)) }}</td>
                    <td class="ctr">@if($sold)<span class="badge badge-sold">Sold</span>@else<span class="badge badge-stk">Stk</span>@endif</td>
                    <td class="ctr act-col"><input type="checkbox" class="cbPrn"></td>
                    <td class="ctr act-col"><input type="checkbox" class="cbPh"></td>
                    <td>{{ $r['counter'] ?? '' }}</td>
                    <td>{{ $r['stkinnos'] ?? '' }}</td>
                    <td class="num">{{ bcF((float)($r['costperc']??0)) }}</td>
                    <td class="num">{{ bcF((float)($r['cost']??0)) }}</td>
                    <td class="num">{{ bcF((float)($r['costmc']??0)) }}</td>
                    <td class="num">{{ bcF((float)($r['coststone']??0)) }}</td>
                    <td>{{ $r['status'] ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot><tr>
                <td colspan="8">Total ({{ $totals['count'] }})</td>
                <td class="num">{{ $totals['qty'] }}</td>
                <td class="num">{{ bcF($totals['weight'],3) }}</td>
                <td></td>
                <td class="num">{{ bcF($totals['stweight'],3) }}</td>
                <td class="num">{{ bcF($totals['stprice']) }}</td>
                <td class="num">{{ bcF($totals['dmdwgt'],3) }}</td>
                <td class="num">{{ bcF($totals['dmdamt']) }}</td>
                <td class="num">{{ bcF($totals['rate']) }}</td>
                <td colspan="10"></td>
            </tr></tfoot>
        </table>
        </div>

        <div class="summary-bar">
            <div class="f"><span class="lbl">Barcodes:</span><span class="val">{{ $totals['count'] }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Qty:</span><span class="val">{{ $totals['qty'] }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Weight:</span><span class="val">{{ bcF($totals['weight'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">Stone:</span><span class="val">{{ bcF($totals['stweight'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">StAmt:</span><span class="val">{{ bcF($totals['stprice']) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">DmdWt:</span><span class="val">{{ bcF($totals['dmdwgt'],3) }}</span></div><div class="divider"></div>
            <div class="f"><span class="lbl">DmdAmt:</span><span class="val">{{ bcF($totals['dmdamt']) }}</span></div>
        </div>
        <div class="count-bar">{{ $totals['count'] }} barcode(s) displayed</div>
    @endif
    </div>
</div>
<div class="toast" id="toast"></div>
<script>
function showToast(m){var t=document.getElementById('toast');t.textContent=m;t.className='toast show';setTimeout(function(){t.className='toast'},2000)}

// Search
var si=document.getElementById('tblSearch');
if(si)si.addEventListener('input',function(){var q=this.value.toLowerCase();document.querySelectorAll('#bcTable tbody tr').forEach(function(r){r.style.display=r.textContent.toLowerCase().indexOf(q)>=0?'':'none'})});

// Select All
var bsa=document.getElementById('btnSelectAll');
if(bsa)bsa.addEventListener('click',function(){var c=document.querySelectorAll('.cbPrn');var a=Array.from(c).every(function(x){return x.checked});c.forEach(function(x){x.checked=!a});showToast(a?'Deselected all':'Selected all for print')});

// Sort
var ss={col:null,asc:true};var bso=document.getElementById('btnSort');
if(bso)bso.addEventListener('click',function(){var cols=['Date','Barcode','Code','Name','Weight','Rate'];var ci=[0,2,3,4,9,15];var cur=ss.col===null?0:(cols.indexOf(ss.col)+1)%cols.length;ss.col=cols[cur];ss.asc=!ss.asc;var idx=ci[cur];var tb=document.querySelector('#bcTable tbody');if(!tb)return;var rows=Array.from(tb.querySelectorAll('tr'));rows.sort(function(a,b){var va=(a.children[idx]||{}).textContent||'';var vb=(b.children[idx]||{}).textContent||'';var na=parseFloat(va.replace(/,/g,''));var nb=parseFloat(vb.replace(/,/g,''));if(!isNaN(na)&&!isNaN(nb))return ss.asc?na-nb:nb-na;return ss.asc?va.localeCompare(vb):vb.localeCompare(va)});rows.forEach(function(r){tb.appendChild(r)});showToast('Sort: '+ss.col+(ss.asc?' ASC':' DESC'))});

// Filter
var bf=document.getElementById('btnFilter');
if(bf)bf.addEventListener('click',function(){var q=(document.getElementById('tblSearch')||{}).value||'';document.querySelectorAll('#bcTable tbody tr').forEach(function(r){r.style.display=q?r.textContent.toLowerCase().indexOf(q.toLowerCase())>=0?'':'none':''});showToast(q?'Filtered':'Filter cleared')});

// Barcode Print
var bp=document.getElementById('btnBcPrint');
if(bp)bp.addEventListener('click',function(){var s=[];document.querySelectorAll('.cbPrn:checked').forEach(function(c){s.push(c.closest('tr').children[2].textContent)});if(!s.length){showToast('No barcodes selected');return}showToast(s.length+' barcode(s) queued for print')});

// Ph Chk
var bph=document.getElementById('btnPhChk');
if(bph)bph.addEventListener('click',function(){var s=[];document.querySelectorAll('.cbPh:checked').forEach(function(c){s.push(c.closest('tr').children[2].textContent)});showToast(s.length?s.length+' checked':'No items selected for Ph check')});

// Edit
var be=document.getElementById('btnEdit');
if(be)be.addEventListener('click',function(){var sel=document.querySelector('#bcTable tbody tr.selected');if(!sel){showToast('Select a row first');return}var bc=sel.children[2].textContent;showToast('Edit barcode: '+bc)});

// Delete All
var bd=document.getElementById('btnDelAll');
if(bd)bd.addEventListener('click',function(){if(!confirm('Delete ALL displayed barcodes? This cannot be undone.'))return;showToast('Delete requires admin authorization')});

// Row select
document.querySelectorAll('#bcTable tbody tr').forEach(function(r){r.addEventListener('click',function(e){if(e.target.type==='checkbox')return;document.querySelectorAll('#bcTable tbody tr.selected').forEach(function(x){x.classList.remove('selected')});this.classList.add('selected')})});
</script>
<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>ReportExport.initFromTable('btnSaveAs','#bcTable','barcode_list_{{ $dateFrom }}_{{ $dateTo }}');</script>
</body>
</html>
