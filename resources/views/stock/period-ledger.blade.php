<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Stock Ledger</title>
<style>
:root{--hdr:#2f3d8f;--hdr2:#4457cb;--border:#d7dfeb;--bg:#f3f6fb;--surface:#fff;--text:#1f2937;--muted:#6b7280;--accent:#2563eb}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",Tahoma,sans-serif;background:radial-gradient(circle at 10% -10%,#eef3ff 0%,#f4f7fb 40%,#edf2f8 100%);color:var(--text);min-height:100vh}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(33,52,89,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:10px 14px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:15px;font-weight:700;letter-spacing:.3px}
.titlebar a{margin-left:auto;background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:7px;padding:4px 12px;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap}
.titlebar a:hover{background:rgba(255,255,255,.28)}
.top{background:#f9fbff;border-bottom:1px solid var(--border);padding:10px 14px}
.filters{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.field{display:flex;flex-direction:column;gap:3px}
.field label{font-size:11px;font-weight:700;color:#3b5d86}
.field input,.field select{height:28px;border:1px solid #bfd0e6;border-radius:7px;padding:0 8px;font-size:12px;background:#fff;color:var(--text)}
.field input:focus,.field select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.checks{display:flex;gap:14px;align-items:center;margin-top:6px;font-size:12px;color:#395980;flex-wrap:wrap}
.checks label{display:flex;align-items:center;gap:4px;cursor:pointer}
.btn{height:28px;border-radius:7px;border:none;padding:0 14px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;text-decoration:none}
.btn-blue{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-blue:hover{opacity:.9}
.btn-green{background:#e6f8ec;color:#1b5b31;border:1px solid #2a7a42}
.btn-green:hover{background:#d0f0da}
.btn-teal{background:#e6f4ff;color:#0c4f7a;border:1px solid #2a6398}
.btn-teal:hover{background:#d0eaff}
.content{padding:10px 14px 14px}
.info-bar{font-size:11px;color:var(--muted);margin-bottom:6px}
.tbl-wrap{max-height:65vh;overflow:auto;border:1px solid var(--border);border-radius:8px}
table{width:100%;border-collapse:collapse;font-size:12px}
th{position:sticky;top:0;background:linear-gradient(180deg,#364891,#2d3d7b);color:#dde6ff;padding:5px 7px;text-align:left;z-index:2;white-space:nowrap}
th.num,td.num{text-align:right}
th.stone{background:linear-gradient(180deg,#3d5c2a,#2e4a1f);color:#d4f0b8}
th.netwgt{background:linear-gradient(180deg,#1e4d5c,#153d49);color:#b8e8f0}
th.dmdpcs{background:linear-gradient(180deg,#4a2a5c,#3a1f4a);color:#e8d4f0}
th.dmdct{background:linear-gradient(180deg,#5c2a4a,#4a1f3a);color:#f0d4e8}
td{border-bottom:1px solid #e8eef6;padding:3px 6px;white-space:nowrap;line-height:1.15}
tr:hover td{background:#f5f8ff}
tr.g-row td{background:#f7f9ff;font-weight:700;color:#1f3f66}
tr.s-row td{background:#f0f7f0;font-weight:700;color:#1f4f1f}
tr.o-row td{background:#fdf7f0;font-weight:700;color:#4f3f1f}
tfoot td{position:sticky;bottom:0;background:#edf4fc;font-weight:700;color:#24486d;border-top:2px solid #bfd0e6}
.no-data{padding:30px;text-align:center;color:var(--muted);font-size:13px}
.hint{padding:20px;text-align:center;color:var(--muted);font-size:12px}
.print-summary{display:none;margin-bottom:10px;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:#f9fbff;font-size:12px;color:#355071}
.print-summary strong{color:#1f3f66}
@media print{
@page{size:A4 portrait;margin:3mm}
html,body{width:100%;height:auto}
body{background:#fff !important;min-height:auto;margin:0;padding:0}
.window{margin:0 !important;box-shadow:none !important;border-radius:0 !important;border:0 !important}
.titlebar,.top,.btn,.checks span{display:none!important}
.content{padding:0 !important}
.print-summary{display:block;margin:0 0 2mm 0;padding:0 0 1.5mm 0;border:0;border-bottom:1px solid #cfd8e3;border-radius:0;background:transparent;font-size:10px;line-height:1.15}
.tbl-wrap{max-height:none !important;overflow:visible !important;border:0 !important;border-radius:0 !important}
table{width:100%;font-size:8.2px;table-layout:auto}
th,td{padding:2px 3px;border-bottom:1px solid #dbe4ef;line-height:1.1}
th{position:static;background:#e8eef8!important;color:#123!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;font-size:7.6px}
tfoot td{position:static;background:#edf4fc!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
tr:hover td{background:transparent}
th:first-child,td:first-child{padding-left:1px}
th:last-child,td:last-child{padding-right:1px}
.window,.content,.tbl-wrap,table{page-break-inside:auto}
tr,td,th{page-break-inside:avoid}
tfoot{display:table-footer-group}
.hide-print-col{display:none!important}
th.cl-emph,td.cl-emph{font-size:11px!important;font-weight:700!important;background:#fff7e0!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
th.cl-emph{font-size:9.5px!important}
}
</style>
</head>
<body>
<div class="window">
    <div class="titlebar">
        <h1>&#128218; Stock Ledger — @if($rptType==='G') Group Based @elseif($rptType==='SG') Subgroup Based @else Item Based @endif</h1>
        <a href="{{ url('/stock/item-history') }}">&#128196; Item History</a>
    </div>

    <div class="top">
        <form method="get" action="{{ url('/stock/period-ledger') }}" id="ledger-form">
            <input type="hidden" name="rpt_type" value="{{ $rptType }}">
            <div class="filters">
                <div class="field">
                    <label>From Date</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" required>
                </div>
                <div class="field">
                    <label>To Date</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" required>
                </div>
                <div class="field">
                    <label>Metal Type</label>
                    <select name="metal_type">
                        @foreach(['All','Gold','Silver','Platinum','Others'] as $opt)
                        <option value="{{ $opt }}" {{ $metalType===$opt?'selected':'' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Ornament</label>
                    <select name="orn_type">
                        @foreach(['All','Ornaments Only','Not Ornaments'] as $opt)
                        <option value="{{ $opt }}" {{ $ornType===$opt?'selected':'' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Stock Filter</label>
                    <select name="stk_type">
                        @foreach(['All','With Stock Only','With -ve Stock','With Zero Stock','With Not Zero Stock','With Transaction','Non Zero Stock or Transaction'] as $opt)
                        <option value="{{ $opt }}" {{ $stkType===$opt?'selected':'' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                @if($rptType === '')
                <div class="field">
                    <label>Group Code</label>
                    <select name="grp_code" style="min-width:90px">
                        <option value="">All</option>
                        @foreach($groupCodes as $gc)
                        <option value="{{ $gc }}" {{ $grpCode===$gc?'selected':'' }}>{{ $gc }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Subgroup Code</label>
                    <select name="subgrp_code" style="min-width:90px">
                        <option value="">All</option>
                        @foreach($subGroupCodes as $sgc)
                        <option value="{{ $sgc }}" {{ $subGrpCode===$sgc?'selected':'' }}>{{ $sgc }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div style="display:flex;gap:6px;align-items:flex-end">
                    <button type="submit" name="show" value="1" class="btn btn-blue">&#128269; Show</button>
                    @if($showData && count($rows)>0)
                    <button type="button" class="btn btn-teal" onclick="window.print()">
                        &#128424; Print
                    </button>
                    <a class="btn btn-green"
                       href="{{ url('/stock/period-ledger/export?'.http_build_query(array_merge(request()->query(),['show'=>1]))) }}">
                        &#128229; CSV
                    </a>
                    @endif
                </div>
            </div>
            <div class="checks">
                <label><input type="checkbox" name="sort_on_code"  value="1" {{ $sortOnCode ?'checked':'' }}> Sort on Code</label>
                <label><input type="checkbox" name="clstk_only"    value="1" {{ $clstkOnly  ?'checked':'' }}> Closing Stock Only</label>
                <label><input type="checkbox" name="net_wgt_model" value="1" {{ $netWgtModel?'checked':'' }}> Net Wgt Model</label>
                <label><input type="checkbox" name="with_stone"    value="1" {{ $withStone  ?'checked':'' }} id="cbx-with-stone"> With Stone</label>
                <label><input type="checkbox" name="with_dmd"      value="1" {{ $withDmd    ?'checked':'' }} id="cbx-with-dmd"> With Diamond</label>
                @if($showData)
                <span style="margin-left:auto;font-weight:700;color:#2d3d7b">{{ count($rows) }} items</span>
                @endif
            </div>
        </form>
    </div>

    <div class="content">
        @if(!$showData)
            <div class="hint">Select date range and click <strong>Show</strong> to generate the stock ledger.</div>
        @elseif(count($rows)===0)
            <div class="no-data">No items match the selected filters.</div>
        @else
        <div class="print-summary">
            <strong>Stock Ledger</strong>
            @if($rptType==='G')
                Group Based
            @elseif($rptType==='SG')
                Subgroup Based
            @else
                Item Based
            @endif
            | From {{ \Carbon\Carbon::parse($dateFrom)->format('d-m-Y') }}
            To {{ \Carbon\Carbon::parse($dateTo)->format('d-m-Y') }}
            | Items: {{ count($rows) }}
        </div>
        <div class="tbl-wrap">
        <table>
            <thead>
            <tr>
                @if($rptType==='G')
                <th style="width:100px">Group Code</th>
                @elseif($rptType==='SG')
                <th style="width:100px">Subgroup Code</th>
                @else
                <th class="cl-emph" style="width:70px">Code</th>
                <th class="cl-emph">Item Name</th>
                <th class="hide-print-col" style="width:60px">Group</th>
                @endif
                <th class="num hide-print-col" style="width:55px">Op Qty</th>
                <th class="num hide-print-col" style="width:85px">Op Weight</th>
                @if($withStone)
                <th class="num stone" style="width:80px">Op St.Wgt</th>
                <th class="num netwgt" style="width:85px">Op Net Wgt</th>
                @elseif($withDmd)
                <th class="num dmdpcs" style="width:65px">Op Dmd Pcs</th>
                <th class="num dmdct"  style="width:80px">Op Dmd Ct</th>
                @endif
                @if(!$clstkOnly)
                <th class="num" style="width:60px">Rcvd Qty</th>
                <th class="num" style="width:85px">Rcvd Wgt</th>
                @if($withStone)
                <th class="num stone" style="width:80px">Rcvd St.Wgt</th>
                <th class="num netwgt" style="width:85px">Rcvd Net Wgt</th>
                @elseif($withDmd)
                <th class="num dmdpcs" style="width:65px">Rcvd Dmd Pcs</th>
                <th class="num dmdct"  style="width:80px">Rcvd Dmd Ct</th>
                @endif
                <th class="num" style="width:60px">Iss Qty</th>
                <th class="num" style="width:85px">Iss Wgt</th>
                @if($withStone)
                <th class="num stone" style="width:80px">Iss St.Wgt</th>
                <th class="num netwgt" style="width:85px">Iss Net Wgt</th>
                @elseif($withDmd)
                <th class="num dmdpcs" style="width:65px">Iss Dmd Pcs</th>
                <th class="num dmdct"  style="width:80px">Iss Dmd Ct</th>
                @endif
                @endif
                <th class="num cl-emph" style="width:80px">Cl Qty</th>
                <th class="num cl-emph" style="width:110px">Cl Weight</th>
                @if($withStone)
                <th class="num stone" style="width:80px">Cl St.Wgt</th>
                <th class="num netwgt" style="width:85px">Cl Net Wgt</th>
                @elseif($withDmd)
                <th class="num dmdpcs" style="width:65px">Cl Dmd Pcs</th>
                <th class="num dmdct"  style="width:80px">Cl Dmd Ct</th>
                @endif
            </tr>
            </thead>
            <tbody>
            @php
                $tOpQ=0;$tOpW=0.0;$tOpSW=0.0;
                $tRQ=0;$tRW=0.0;$tRSW=0.0;
                $tIQ=0;$tIW=0.0;$tISW=0.0;
                $tClQ=0;$tClW=0.0;$tClSW=0.0;
            @endphp
            @foreach($rows as $row)
            @php
                $tOpQ+=$row['opqty'];   $tOpW+=$row['opwgt'];   $tOpSW+=$row['opswgt'];
                $tRQ+=$row['rcvdqty'];  $tRW+=$row['rcvdwgt'];  $tRSW+=$row['rcvdswgt'];
                $tIQ+=$row['issuedqty'];$tIW+=$row['issuedwgt'];$tISW+=$row['issuedswgt'];
                $tClQ+=$row['clqty'];   $tClW+=$row['clwgt'];   $tClSW+=$row['clswgt'];
                $negCl = $row['clwgt']<0;
            @endphp
            <tr>
                @if($rptType==='G' || $rptType==='SG')
                <td><strong>{{ $row['groupkey'] }}</strong></td>
                @else
                <td class="cl-emph">{{ $row['code'] }}</td>
                <td class="cl-emph">{{ $row['name'] }}</td>
                <td class="hide-print-col">{{ $row['grpcode'] }}</td>
                @endif
                <td class="num hide-print-col">{{ $row['opqty'] ?: '' }}</td>
                <td class="num hide-print-col">{{ $row['opwgt'] != 0 ? number_format($row['opwgt'],3) : '' }}</td>
                @if($withStone)
                <td class="num" style="color:#3a5c20">{{ $row['opswgt'] != 0 ? number_format($row['opswgt'],3) : '' }}</td>
                <td class="num" style="color:#0c4f5c">{{ ($row['opwgt']-$row['opswgt']) != 0 ? number_format($row['opwgt']-$row['opswgt'],3) : '' }}</td>
                @elseif($withDmd)
                <td class="num" style="color:#6a3a8c">0</td>
                <td class="num" style="color:#8c3a6a">{{ $row['opswgt'] != 0 ? number_format($row['opswgt'],3) : '' }}</td>
                @endif
                @if(!$clstkOnly)
                <td class="num">{{ $row['rcvdqty'] ?: '' }}</td>
                <td class="num">{{ $row['rcvdwgt'] != 0 ? number_format($row['rcvdwgt'],3) : '' }}</td>
                @if($withStone)
                <td class="num" style="color:#3a5c20">{{ $row['rcvdswgt'] != 0 ? number_format($row['rcvdswgt'],3) : '' }}</td>
                <td class="num" style="color:#0c4f5c">{{ ($row['rcvdwgt']-$row['rcvdswgt']) != 0 ? number_format($row['rcvdwgt']-$row['rcvdswgt'],3) : '' }}</td>
                @elseif($withDmd)
                <td class="num" style="color:#6a3a8c">0</td>
                <td class="num" style="color:#8c3a6a">{{ $row['rcvdswgt'] != 0 ? number_format($row['rcvdswgt'],3) : '' }}</td>
                @endif
                <td class="num">{{ $row['issuedqty'] ?: '' }}</td>
                <td class="num">{{ $row['issuedwgt'] != 0 ? number_format($row['issuedwgt'],3) : '' }}</td>
                @if($withStone)
                <td class="num" style="color:#3a5c20">{{ $row['issuedswgt'] != 0 ? number_format($row['issuedswgt'],3) : '' }}</td>
                <td class="num" style="color:#0c4f5c">{{ ($row['issuedwgt']-$row['issuedswgt']) != 0 ? number_format($row['issuedwgt']-$row['issuedswgt'],3) : '' }}</td>
                @elseif($withDmd)
                <td class="num" style="color:#6a3a8c">0</td>
                <td class="num" style="color:#8c3a6a">{{ $row['issuedswgt'] != 0 ? number_format($row['issuedswgt'],3) : '' }}</td>
                @endif
                @endif
                <td class="num cl-emph" style="{{ $negCl?'color:#c0392b;font-weight:700':'' }}">{{ $row['clqty'] ?: '' }}</td>
                <td class="num cl-emph" style="{{ $negCl?'color:#c0392b;font-weight:700':'' }}">
                    {{ $row['clwgt'] != 0 ? number_format($row['clwgt'],3) : '' }}
                </td>
                @if($withStone)
                <td class="num" style="color:#3a5c20">{{ $row['clswgt'] != 0 ? number_format($row['clswgt'],3) : '' }}</td>
                <td class="num" style="color:#0c4f5c">{{ ($row['clwgt']-$row['clswgt']) != 0 ? number_format($row['clwgt']-$row['clswgt'],3) : '' }}</td>
                @elseif($withDmd)
                <td class="num" style="color:#6a3a8c">0</td>
                <td class="num" style="color:#8c3a6a">{{ $row['clswgt'] != 0 ? number_format($row['clswgt'],3) : '' }}</td>
                @endif
            </tr>
            @endforeach
            </tbody>
            <tfoot>
            @if($rptType==='' )
            @foreach(['G'=>'Gold','S'=>'Silver','O'=>'Other','P'=>'Platinum'] as $tk=>$tl)
            @if($totals[$tk]['clqty']!=0 || $totals[$tk]['opwgt']!=0)
            <tr>
                <td colspan="3" class="cl-emph">Total {{ $tl }}</td>
                <td class="num hide-print-col">{{ $totals[$tk]['opqty'] }}</td>
                <td class="num hide-print-col">{{ number_format($totals[$tk]['opwgt'],3) }}</td>
                @if($withStone)
                <td class="num">{{ number_format($totals[$tk]['opswgt'],3) }}</td>
                <td class="num">{{ number_format($totals[$tk]['opwgt']-$totals[$tk]['opswgt'],3) }}</td>
                @elseif($withDmd)
                <td class="num">0</td>
                <td class="num">{{ number_format($totals[$tk]['opswgt'],3) }}</td>
                @endif
                @if(!$clstkOnly)
                <td class="num">{{ $totals[$tk]['rcvdqty'] }}</td>
                <td class="num">{{ number_format($totals[$tk]['rcvdwgt'],3) }}</td>
                @if($withStone)
                <td class="num">{{ number_format($totals[$tk]['rcvdswgt'],3) }}</td>
                <td class="num">{{ number_format($totals[$tk]['rcvdwgt']-$totals[$tk]['rcvdswgt'],3) }}</td>
                @elseif($withDmd)
                <td class="num">0</td>
                <td class="num">{{ number_format($totals[$tk]['rcvdswgt'],3) }}</td>
                @endif
                <td class="num">{{ $totals[$tk]['issuedqty'] }}</td>
                <td class="num">{{ number_format($totals[$tk]['issuedwgt'],3) }}</td>
                @if($withStone)
                <td class="num">{{ number_format($totals[$tk]['issuedswgt'],3) }}</td>
                <td class="num">{{ number_format($totals[$tk]['issuedwgt']-$totals[$tk]['issuedswgt'],3) }}</td>
                @elseif($withDmd)
                <td class="num">0</td>
                <td class="num">{{ number_format($totals[$tk]['issuedswgt'],3) }}</td>
                @endif
                @endif
                <td class="num cl-emph">{{ $totals[$tk]['clqty'] }}</td>
                <td class="num cl-emph">{{ number_format($totals[$tk]['clwgt'],3) }}</td>
                @if($withStone)
                <td class="num">{{ number_format($totals[$tk]['clswgt'],3) }}</td>
                <td class="num">{{ number_format($totals[$tk]['clwgt']-$totals[$tk]['clswgt'],3) }}</td>
                @elseif($withDmd)
                <td class="num">0</td>
                <td class="num">{{ number_format($totals[$tk]['clswgt'],3) }}</td>
                @endif
            </tr>
            @endif
            @endforeach
            @endif
            <tr style="background:#dde8f8">
                @if($rptType==='')
                <td colspan="3" class="cl-emph"><strong>Grand Total</strong></td>
                @else
                <td class="cl-emph"><strong>Grand Total</strong></td>
                @endif
                <td class="num hide-print-col"><strong>{{ $tOpQ }}</strong></td>
                <td class="num hide-print-col"><strong>{{ number_format($tOpW,3) }}</strong></td>
                @if($withStone)
                <td class="num"><strong>{{ number_format($tOpSW,3) }}</strong></td>
                <td class="num"><strong>{{ number_format($tOpW-$tOpSW,3) }}</strong></td>
                @elseif($withDmd)
                <td class="num"><strong>0</strong></td>
                <td class="num"><strong>{{ number_format($tOpSW,3) }}</strong></td>
                @endif
                @if(!$clstkOnly)
                <td class="num"><strong>{{ $tRQ }}</strong></td>
                <td class="num"><strong>{{ number_format($tRW,3) }}</strong></td>
                @if($withStone)
                <td class="num"><strong>{{ number_format($tRSW,3) }}</strong></td>
                <td class="num"><strong>{{ number_format($tRW-$tRSW,3) }}</strong></td>
                @elseif($withDmd)
                <td class="num"><strong>0</strong></td>
                <td class="num"><strong>{{ number_format($tRSW,3) }}</strong></td>
                @endif
                <td class="num"><strong>{{ $tIQ }}</strong></td>
                <td class="num"><strong>{{ number_format($tIW,3) }}</strong></td>
                @if($withStone)
                <td class="num"><strong>{{ number_format($tISW,3) }}</strong></td>
                <td class="num"><strong>{{ number_format($tIW-$tISW,3) }}</strong></td>
                @elseif($withDmd)
                <td class="num"><strong>0</strong></td>
                <td class="num"><strong>{{ number_format($tISW,3) }}</strong></td>
                @endif
                @endif
                <td class="num cl-emph"><strong>{{ $tClQ }}</strong></td>
                <td class="num cl-emph"><strong>{{ number_format($tClW,3) }}</strong></td>
                @if($withStone)
                <td class="num"><strong>{{ number_format($tClSW,3) }}</strong></td>
                <td class="num"><strong>{{ number_format($tClW-$tClSW,3) }}</strong></td>
                @elseif($withDmd)
                <td class="num"><strong>0</strong></td>
                <td class="num"><strong>{{ number_format($tClSW,3) }}</strong></td>
                @endif
            </tr>
            </tfoot>
        </table>
        </div>
        @endif
    </div>
</div>
<script>
(function() {
    var cbxStone = document.getElementById('cbx-with-stone');
    var cbxDmd   = document.getElementById('cbx-with-dmd');
    if (cbxStone && cbxDmd) {
        cbxStone.addEventListener('change', function() { if (this.checked) cbxDmd.checked = false; });
        cbxDmd.addEventListener('change',   function() { if (this.checked) cbxStone.checked = false; });
    }
})();
</script>
</body>
</html>
