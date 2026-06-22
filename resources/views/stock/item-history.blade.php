<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Item History{{ $itemName ? ' — '.$itemName : '' }}</title>
<style>
:root{--hdr:#2f3d8f;--hdr2:#4457cb;--border:#d7dfeb;--text:#1f2937;--accent:#2563eb}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",Tahoma,sans-serif;background:radial-gradient(circle at 10% -10%,#eef3ff 0%,#f4f7fb 40%,#edf2f8 100%);color:var(--text);min-height:100vh}
.window{margin:10px;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(33,52,89,.10);overflow:hidden}
/* Titlebar */
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:9px 14px;display:flex;align-items:center;gap:8px}
.titlebar h1{color:#fff;font-size:15px;font-weight:700;letter-spacing:.3px}
.titlebar a,.titlebar button.tlink{background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:7px;padding:4px 12px;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer}
.titlebar a:hover,.titlebar button.tlink:hover{background:rgba(255,255,255,.28)}
/* Toolbar area */
.toolbar-area{background:#f0f4fa;border-bottom:1px solid var(--border);padding:8px 14px;display:flex;flex-direction:column;gap:6px}
.tb-row{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end}
.field{display:flex;flex-direction:column;gap:2px}
.field label,.field .lbl{font-size:10px;font-weight:700;color:#3b5d86;text-transform:uppercase;letter-spacing:.3px}
.field input[type=text],.field input[type=date],.field select{height:27px;border:1px solid #bfd0e6;border-radius:6px;padding:0 7px;font-size:12px;background:#fff;color:var(--text)}
.field input[type=text]:focus,.field input[type=date]:focus,.field select:focus{outline:none;border-color:var(--accent)}
.field input[type=date]{color:#000080;font-weight:700}
.item-field{position:relative}
.item-code-wrap{display:flex;align-items:center;gap:4px}
.item-code-wrap input[type=text]{width:80px;text-transform:uppercase}
.lookup-btn{height:27px;width:30px;border:1px solid #8faad1;border-radius:6px;background:#eef4ff;color:#183f85;font-size:13px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
.lookup-btn:hover{background:#dceaff}
.lookup-btn:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.item-suggest{display:none;position:absolute;top:43px;left:0;width:330px;max-width:calc(100vw - 60px);max-height:230px;overflow:auto;background:#fff;border:1px solid #9fb8dd;border-radius:7px;box-shadow:0 14px 28px rgba(31,48,86,.18);z-index:40}
.item-suggest.open{display:block}
.item-option{display:grid;grid-template-columns:80px 1fr;gap:8px;padding:7px 9px;border-bottom:1px solid #eef2f8;cursor:pointer;font-size:12px}
.item-option:last-child{border-bottom:none}
.item-option:hover,.item-option.active{background:#eaf2ff}
.item-option .icode{font-weight:800;color:#174489}
.item-option .iname{color:#1f2937;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.item-option .imeta{grid-column:2;color:#6b7280;font-size:10.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cb-wrap{display:flex;align-items:center;gap:4px;height:27px;font-size:11px;color:#374151;cursor:pointer;white-space:nowrap;user-select:none}
.cb-wrap input[type=checkbox]{width:13px;height:13px;cursor:pointer;accent-color:var(--accent)}
/* Buttons */
.btn{height:27px;border-radius:6px;border:none;padding:0 12px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap}
.btn-blue{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-blue:hover{opacity:.9}
.btn-g{background:#e6f8ec;border:1px solid #2a7a42;color:#1b5b31}
.btn-g:hover{background:#d4f0de}
.btn-n{background:#e8ecf4;border:1px solid #bfd0e6;color:#374151}
.btn-n:hover{background:#dde4f0}
.btn-sep{width:1px;height:20px;background:#c7d4e4;margin:0 2px;align-self:center}
/* Content */
.content{padding:10px 14px 16px;display:flex;flex-direction:column;gap:12px}
/* Summary bar */
.summary{display:flex;gap:14px;flex-wrap:wrap;background:#f0f4ff;border:1px solid #c7d5f0;border-radius:8px;padding:8px 14px;font-size:12px;align-items:center}
.summary .lbl{font-weight:700;color:#2d4f74;margin-right:3px}
.summary .val{font-weight:700;color:#1a3260}
/* Section */
.section-block{display:flex;flex-direction:column}
.section-title{font-size:11.5px;font-weight:700;color:#fff;background:linear-gradient(90deg,#364891,#4457cb);padding:5px 10px;border-radius:6px 6px 0 0}
.tbl-wrap{border:1px solid var(--border);border-radius:0 6px 6px 6px;overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:11.5px}
th{background:#edf4fc;color:#2d4f74;padding:5px 7px;text-align:left;white-space:nowrap;border-bottom:1px solid var(--border)}
th.num,td.num{text-align:right}
td{border-bottom:1px solid #eef2f8;padding:4px 7px;white-space:nowrap}
th.party-col,td.party-col{min-width:280px;white-space:normal;word-break:break-word}
tr:hover td{background:#f5f8ff}
tr.tot-row td{background:#e8f0ff;font-weight:700;color:#24486d}
/* Closing */
.closing{background:#e4edff;border:1px solid #b0c4e8;border-radius:8px;padding:16px 20px;font-size:18px;font-weight:700;color:#1a3260;display:flex;gap:30px;margin-top:12px}
.hint{padding:30px;text-align:center;color:#6b7280;font-size:13px}
/* Summary mode: hide detail rows */
body.summary-mode tr.detail-row{display:none}
/* Without Qty mode: hide qty column */
body.no-qty-mode .col-qty{display:none}
@media print{
    .toolbar-area,.titlebar .tlink,.titlebar button.tlink{display:none}
    .window{margin:0;border-radius:0;box-shadow:none;overflow:visible}
    body{background:#fff}
    body.summary-mode tr.detail-row{display:none}
    .closing{page-break-inside:avoid;margin-top:16px}
}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<style>
/* Item History needs wider account-name columns than generic reports. */
body.item-history-page{
  color:#070b13 !important;
  font-size:20px !important;
}
body.item-history-page .window{margin:8px;background:#fff}
body.item-history-page .titlebar h1{font-size:20px !important}
body.item-history-page .toolbar-area,
body.item-history-page .content{color:#0b1220}
body.item-history-page .field label,
body.item-history-page .field .lbl,
body.item-history-page .cb-wrap,
body.item-history-page .summary,
body.item-history-page .hint{font-size:19px !important;color:#0f172a !important}
body.item-history-page input,
body.item-history-page select,
body.item-history-page button{font-size:19px !important;color:#070b13 !important}
body.item-history-page .btn-blue{color:#fff !important}
body.item-history-page .btn-g{color:#14532d !important}
body.item-history-page .btn-n{color:#1f2937 !important}
body.item-history-page .titlebar button,
body.item-history-page .titlebar a{color:#fff !important}
body.item-history-page .field input[type=text],
body.item-history-page .field input[type=date],
body.item-history-page .field select,
body.item-history-page .btn{height:34px}
body.item-history-page .lookup-btn{height:34px;width:36px}
body.item-history-page .tbl-wrap{
  overflow-x:auto;
  overflow-y:visible;
}
body.item-history-page table{
  width:max-content;
  min-width:100%;
  table-layout:auto;
  font-size:19px !important;
}
body.item-history-page th{
  font-size:18px !important;
  color:#0f172a !important;
  font-weight:900 !important;
}
body.item-history-page td{
  font-size:19px !important;
  color:#070b13 !important;
  font-weight:700;
}
body.item-history-page th.party-col,
body.item-history-page td.party-col{
  min-width:560px;
  max-width:none;
  white-space:normal !important;
  overflow:visible !important;
  text-overflow:clip !important;
  word-break:normal;
  overflow-wrap:break-word;
  line-height:1.35;
}
body.item-history-page td.party-col{
  color:#050816 !important;
  font-weight:850;
  min-height:40px;
}
body.item-history-page th.party-col{color:#08111f !important}
body.item-history-page .summary .lbl,
body.item-history-page .summary .val,
body.item-history-page .closing,
body.item-history-page .closing strong{
  color:#07111f !important;
}
body.item-history-page .item-option,
body.item-history-page .item-option .iname,
body.item-history-page .item-option .imeta{
  font-size:17px !important;
  color:#111827 !important;
}
body.item-history-page .item-option .iname,
body.item-history-page .item-option .imeta{
  white-space:normal;
  overflow:visible;
  text-overflow:clip;
}
@media print{
  body.item-history-page table{width:100%;font-size:12px !important}
  body.item-history-page th.party-col,
  body.item-history-page td.party-col{min-width:220px;overflow-wrap:break-word}
}
</style>
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body id="mainBody" class="item-history-page">
<div class="window">

  {{-- ── Titlebar ── --}}
  <div class="titlebar">
    <h1>&#128196; Item History</h1>
    <div style="margin-left:auto;display:flex;gap:6px">
      <button class="tlink" onclick="window.print()">&#128438; Print</button>
      <a href="{{ url('/stock/period-ledger') }}">&#128218; Period Ledger</a>
      <button class="tlink" onclick="window.parent.postMessage({type:'goldapp:close-module-frame'},'*')">&#10005; Exit</button>
    </div>
  </div>

  {{-- ── Toolbar ── --}}
  <div class="toolbar-area">
    <form method="get" action="{{ url('/stock/item-history') }}" id="histForm">

      {{-- Row 1: Core filters + action buttons --}}
      <div class="tb-row">
        <div class="field item-field">
          <label>Item Code</label>
          <div class="item-code-wrap">
            <input type="text" name="code" id="codeInput" value="{{ $scode }}"
                   placeholder="e.g. GR" autocomplete="off" autofocus>
            <button type="button" class="lookup-btn" id="btnItemSearch" title="Search item code">?</button>
          </div>
          <div class="item-suggest" id="itemSuggest"></div>
        </div>
        <div class="field">
          <label>From</label>
          <input type="date" name="date_from" value="{{ $dateFrom }}" required>
        </div>
        <div class="field">
          <label>To</label>
          <input type="date" name="date_to" value="{{ $dateTo }}" required>
        </div>
        <div class="field">
          <label>Stock Type</label>
          <select name="stktype" style="min-width:75px">
            <option value="">All</option>
            @foreach($stockTypes as $st)
            <option value="{{ trim((string)$st->code) }}"
              {{ $stktype===trim((string)$st->code)?'selected':'' }}>
              {{ trim((string)$st->code) }}{{ isset($st->name)?' — '.trim((string)$st->name):'' }}
            </option>
            @endforeach
          </select>
        </div>
        @if(count($groupCodes))
        <div class="field">
          <label>Group</label>
          <select name="grpcode" style="min-width:70px">
            <option value="">All</option>
            @foreach($groupCodes as $gc)
            <option value="{{ $gc }}" {{ $grpcode===$gc?'selected':'' }}>{{ $gc }}</option>
            @endforeach
          </select>
        </div>
        @endif
        <div class="btn-sep"></div>
        <div class="field"><label>&nbsp;</label>
          <button type="submit" class="btn btn-blue">&#128269; Show</button>
        </div>
        @if($showed && count($sections))
        <div class="field"><label>&nbsp;</label>
          <button type="button" class="btn btn-g" onclick="saveAsExcel()">&#128190; Save As</button>
        </div>
        @endif
        <div class="field"><label>&nbsp;</label>
          <button type="button" class="btn btn-n" onclick="window.print()">&#128438; Print</button>
        </div>
      </div>

      {{-- Row 2: Mode checkboxes --}}
      <div class="tb-row" style="padding-top:2px">
        <label class="cb-wrap">
          <input type="checkbox" name="with_stktouch" value="1" {{ $withStkTouch?'checked':'' }}>
          With Stock Touch Calc
        </label>
        <label class="cb-wrap">
          <input type="checkbox" name="no_stk_touch_wgt" value="1" {{ $noStkTouchWgt?'checked':'' }}>
          No Stk Touch Wgt
        </label>
        <label class="cb-wrap">
          <input type="checkbox" name="show_ledger" value="1" id="chkLedger" {{ $showLedger?'checked':'' }}
                 onchange="document.getElementById('histForm').submit()">
          Show as Stock Ledger
        </label>
        <label class="cb-wrap" id="cbSummary">
          <input type="checkbox" id="chkSummary"> Summary
        </label>
        <label class="cb-wrap" id="cbWithoutQty">
          <input type="checkbox" id="chkWithoutQty"> Without Qty
        </label>
        <label class="cb-wrap">
          <input type="checkbox" id="chkSpeed" checked> Speed
        </label>
      </div>

    </form>
  </div>

  {{-- ── Content ── --}}
  <div class="content">

    @if(!$showed)
      <div class="hint">Enter an item code and click <strong>Show</strong> to view its transaction history.</div>

    @elseif($showed && !$itemName)
      <div class="hint" style="color:#b91c1c">Item code <strong>{{ $scode }}</strong> not found in items table.</div>

    @else
      {{-- Opening stock summary --}}
      <div class="summary">
        <span><span class="lbl">Item:</span><span class="val">{{ $scode }}</span></span>
        <span><span class="lbl">Name:</span><span class="val">{{ $itemName }}</span></span>
        <span><span class="lbl">Period:</span>
          {{ date('d/m/Y', strtotime($dateFrom)) }}
          @if($dateFrom !== $dateTo) – {{ date('d/m/Y', strtotime($dateTo)) }} @endif
        </span>
        @if($stktype)<span><span class="lbl">StkType:</span><span class="val">{{ $stktype }}</span></span>@endif
        @if($withStkTouch && !$noStkTouchWgt)
          <span style="color:#7c3aed;font-weight:700">&#x25CF; Stk Touch Mode</span>
        @endif
        <span><span class="lbl">Op. Qty:</span><span class="val">{{ $opQty }}</span></span>
        <span><span class="lbl">Op. Wgt:</span><span class="val">{{ number_format($opWgt, 3) }}</span></span>
      </div>

      @if($showLedger)
        {{-- ── Stock Ledger view (d_stockledger_simple_history) ── --}}
        @if(!count($ledgerRows))
          <div class="hint">No transactions found for <strong>{{ $itemName ?: $scode }}</strong> in this period.</div>
        @else
        @php
          $totRcvQty=0; $totRcvWgt=0.0; $totIsuQty=0; $totIsuWgt=0.0;
          foreach($ledgerRows as $lr){
            $totRcvQty+=$lr['trcvedqty']; $totRcvWgt+=$lr['trcvedwgt'];
            $totIsuQty+=$lr['tissuedqty']; $totIsuWgt+=$lr['tissuedwgt'];
          }
        @endphp
        <div class="tbl-wrap" style="border-radius:8px">
          <table>
            <thead>
              <tr>
                <th rowspan="2" style="text-align:left">Date</th>
                <th colspan="2" style="text-align:center;background:#dce8f7;border-bottom:1px solid #bcd0e8">Opening Stock</th>
                <th colspan="2" style="text-align:center;background:#dcf0e0;border-bottom:1px solid #9ed4a8">Total Received</th>
                <th colspan="2" style="text-align:center;background:#fde8e0;border-bottom:1px solid #f0b8a0">Total Issued</th>
                <th colspan="2" style="text-align:center;background:#e8dcf7;border-bottom:1px solid #c0a0e0">Closing Stock</th>
              </tr>
              <tr>
                <th class="num" style="background:#dce8f7">Qty</th>
                <th class="num" style="background:#dce8f7">Weight</th>
                <th class="num" style="background:#dcf0e0">Qty</th>
                <th class="num" style="background:#dcf0e0">Weight</th>
                <th class="num" style="background:#fde8e0">Qty</th>
                <th class="num" style="background:#fde8e0">Weight</th>
                <th class="num" style="background:#e8dcf7">Qty</th>
                <th class="num" style="background:#e8dcf7">Weight</th>
              </tr>
            </thead>
            <tbody>
              @foreach($ledgerRows as $lr)
              <tr class="detail-row">
                <td>{{ date('d/m/Y', strtotime($lr['tdate'])) }}</td>
                <td class="num" style="color:#1a5276">{{ $lr['topstkqty'] ?: '' }}</td>
                <td class="num" style="color:#1a5276">{{ $lr['topstkwgt'] ? number_format($lr['topstkwgt'],3) : '' }}</td>
                <td class="num" style="color:#155724">{{ $lr['trcvedqty'] ?: '' }}</td>
                <td class="num" style="color:#155724">{{ $lr['trcvedwgt'] ? number_format($lr['trcvedwgt'],3) : '' }}</td>
                <td class="num" style="color:#7b1c1c">{{ $lr['tissuedqty'] ?: '' }}</td>
                <td class="num" style="color:#7b1c1c">{{ $lr['tissuedwgt'] ? number_format($lr['tissuedwgt'],3) : '' }}</td>
                <td class="num" style="color:#4a1580;font-weight:600">{{ $lr['tclstkqty'] }}</td>
                <td class="num" style="color:#4a1580;font-weight:600">{{ number_format($lr['tclstkwgt'],3) }}</td>
              </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr style="background:#f0f4ff;font-weight:700;color:#24486d">
                <td>Total</td>
                <td class="num"></td><td class="num"></td>
                <td class="num" style="color:#155724">{{ $totRcvQty ?: '' }}</td>
                <td class="num" style="color:#155724">{{ number_format($totRcvWgt,3) }}</td>
                <td class="num" style="color:#7b1c1c">{{ $totIsuQty ?: '' }}</td>
                <td class="num" style="color:#7b1c1c">{{ number_format($totIsuWgt,3) }}</td>
                <td class="num"></td><td class="num"></td>
              </tr>
            </tfoot>
          </table>
        </div>
        @endif

      @else
        {{-- ── Transaction sections view ── --}}
        @if(!count($sections))
          <div class="hint">No transactions found for <strong>{{ $itemName ?: $scode }}</strong> in this period.</div>
        @else

        @foreach($sections as $si => $sect)
        <div class="section-block" data-section="{{ $si }}">
          <div class="section-title">{{ $sect['title'] }}</div>
          <div class="tbl-wrap">
            <table>
              <thead>
                <tr>
                  @foreach($sect['cols'] as $ci => $col)
                  @php $isAccountCol = preg_match('/supplier|customer|party|refiner|account/i', (string) $col); @endphp
                  <th class="{{ in_array($col,['Qty','Weight','St.Wgt','Rate','Amount','Touch']) ? 'num' : '' }}
                              {{ $col==='Qty' ? 'col-qty' : '' }}
                              {{ $isAccountCol ? 'party-col' : '' }}">{{ $col }}</th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @foreach($sect['rows'] as $row)
                <tr class="detail-row">
                  @foreach($row as $ci => $cell)
                  @php $colName = (string) ($sect['cols'][$ci] ?? ''); $isAccountCol = preg_match('/supplier|customer|party|refiner|account/i', $colName); @endphp
                  <td class="{{ in_array($colName,['Qty','Weight','St.Wgt','Rate','Amount','Touch']) ? 'num' : '' }}
                              {{ $colName==='Qty' ? 'col-qty' : '' }}
                              {{ $isAccountCol ? 'party-col' : '' }}">{{ $cell }}</td>
                  @endforeach
                </tr>
                @endforeach
                <tr class="tot-row">
                  <td colspan="2">Total</td>
                  <td class="num col-qty">{{ $sect['tq'] ?: '' }}</td>
                  <td class="num">{{ number_format($sect['tw'], 3) }}</td>
                  @php $extra = count($sect['cols']) - 4; @endphp
                  @for($i=0;$i<$extra;$i++)<td></td>@endfor
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        @endforeach

        @endif
      @endif

      {{-- Closing stock --}}
      <div class="closing">
        <span>Cl. Qty: <strong>{{ $clQty }}</strong></span>
        <span>Cl. Weight: <strong>{{ number_format($clWgt, 3) }}</strong></span>
      </div>

    @endif
  </div>{{-- /content --}}
</div>{{-- /window --}}

<script>
// Summary toggle
document.getElementById('chkSummary').addEventListener('change', function(){
  document.getElementById('mainBody').classList.toggle('summary-mode', this.checked);
});
// Without Qty toggle
document.getElementById('chkWithoutQty').addEventListener('change', function(){
  document.getElementById('mainBody').classList.toggle('no-qty-mode', this.checked);
});
const codeInput = document.getElementById('codeInput');
const itemSuggest = document.getElementById('itemSuggest');
const btnItemSearch = document.getElementById('btnItemSearch');
let itemSuggestRows = [];
let itemSuggestIndex = -1;
let itemSuggestTimer = null;

function escapeHtml(value){
  return String(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

function closeItemSuggest(){
  itemSuggest.classList.remove('open');
  itemSuggest.innerHTML = '';
  itemSuggestRows = [];
  itemSuggestIndex = -1;
}

function renderItemSuggest(rows){
  itemSuggestRows = rows || [];
  itemSuggestIndex = itemSuggestRows.length ? 0 : -1;
  if(!itemSuggestRows.length){
    closeItemSuggest();
    return;
  }

  itemSuggest.innerHTML = itemSuggestRows.map((row, idx) => {
    const meta = [row.type, row.group].filter(Boolean).join(' / ');
    return `<div class="item-option ${idx === itemSuggestIndex ? 'active' : ''}" data-index="${idx}">
      <span class="icode">${escapeHtml(row.code)}</span>
      <span class="iname">${escapeHtml(row.name || '')}</span>
      ${meta ? `<span class="imeta">${escapeHtml(meta)}</span>` : ''}
    </div>`;
  }).join('');
  itemSuggest.classList.add('open');
}

function setActiveItem(index){
  if(!itemSuggestRows.length) return;
  itemSuggestIndex = (index + itemSuggestRows.length) % itemSuggestRows.length;
  itemSuggest.querySelectorAll('.item-option').forEach((el, idx) => {
    el.classList.toggle('active', idx === itemSuggestIndex);
    if(idx === itemSuggestIndex) el.scrollIntoView({block:'nearest'});
  });
}

function pickItem(row){
  if(!row) return;
  codeInput.value = row.code;
  closeItemSuggest();
  codeInput.focus();
}

function loadItemSuggest(){
  const q = codeInput.value.trim();
  fetch(`{{ url('/api/stock/item-search') }}?q=${encodeURIComponent(q)}`)
    .then(res => res.ok ? res.json() : {items:[]})
    .then(data => renderItemSuggest(data.items || []))
    .catch(closeItemSuggest);
}

function openItemSearch(){
  clearTimeout(itemSuggestTimer);
  loadItemSuggest();
  codeInput.focus();
  codeInput.select();
}

btnItemSearch.addEventListener('click', openItemSearch);

codeInput.addEventListener('input', function(){
  this.value = this.value.toUpperCase();
  clearTimeout(itemSuggestTimer);
  itemSuggestTimer = setTimeout(loadItemSuggest, 180);
});

codeInput.addEventListener('focus', function(){
  if(this.value.trim()) loadItemSuggest();
});

codeInput.addEventListener('keydown', function(e){
  if(e.key === 'ArrowDown' && itemSuggest.classList.contains('open')){
    e.preventDefault();
    setActiveItem(itemSuggestIndex + 1);
    return;
  }
  if(e.key === 'ArrowUp' && itemSuggest.classList.contains('open')){
    e.preventDefault();
    setActiveItem(itemSuggestIndex - 1);
    return;
  }
  if(e.key === 'Escape'){
    closeItemSuggest();
    return;
  }
  if(e.key === 'F1'){
    e.preventDefault();
    openItemSearch();
    return;
  }
  if(e.key === 'Enter'){
    e.preventDefault();
    if(itemSuggest.classList.contains('open') && itemSuggestIndex >= 0){
      pickItem(itemSuggestRows[itemSuggestIndex]);
      return;
    }
    document.getElementById('histForm').submit();
  }
});

itemSuggest.addEventListener('mousedown', function(e){
  const option = e.target.closest('.item-option');
  if(!option) return;
  e.preventDefault();
  pickItem(itemSuggestRows[Number(option.dataset.index)]);
});

codeInput.addEventListener('blur', function(){
  setTimeout(closeItemSuggest, 150);
});

// Save As Excel (CSV download)
function saveAsExcel(){
  const rows = [];
  document.querySelectorAll('.section-block').forEach(block => {
    const title = block.querySelector('.section-title').textContent.trim();
    rows.push([title]);
    const headers = [...block.querySelectorAll('thead th')].map(th => th.textContent.trim());
    rows.push(headers);
    block.querySelectorAll('tbody tr').forEach(tr => {
      rows.push([...tr.querySelectorAll('td')].map(td => td.textContent.trim()));
    });
    rows.push([]);
  });
  const csv = rows.map(r => r.map(c => '"'+String(c).replace(/"/g,'""')+'"').join(',')).join('\r\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'item-history-{{ $scode }}-{{ $dateFrom }}.csv';
  a.click();
}
</script>
</body>
</html>
