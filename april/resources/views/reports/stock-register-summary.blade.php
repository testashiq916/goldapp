<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:#f4f7fb;color:#1e293b;font-size:13px}
.wrap{max-width:1500px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:14px}
h1{margin:0 0 6px;font-size:22px;color:#173b63}
.sub{margin:0 0 12px;color:#5b7088;font-size:12px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px}
.field{display:flex;flex-direction:column;gap:4px;min-width:150px}
label{font-size:11px;font-weight:700;color:#375b84}
input,select,button{height:34px;border:1px solid #bfd0e6;border-radius:6px;padding:0 8px;font-size:12px;box-sizing:border-box}
button{cursor:pointer;background:#e8f2ff;border-color:#2a6398;color:#17456e;font-weight:700}
button.primary{background:#e6f8ec;border-color:#2a7a42;color:#1b5b31}
.checkline{display:flex;align-items:center;gap:6px;height:34px;color:#41566d;font-size:12px}
.meta{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:10px;font-size:12px;color:#48627c}
.table-wrap{border:1px solid #d8e2ef;border-radius:8px;overflow:auto;max-height:72vh}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border-bottom:1px solid #e5ecf5;padding:7px 8px;vertical-align:top}
th{position:sticky;top:0;background:#edf4fc;text-align:left;z-index:1;white-space:nowrap}
td.num,th.num{text-align:right;white-space:nowrap}
tfoot td{background:#f7fbff;font-weight:700;color:#264968;position:sticky;bottom:0}
.empty{padding:24px;text-align:center;color:#64748b;border:1px dashed #c7d4e4;border-radius:8px;background:#fbfdff}
.status{margin-top:10px;min-height:18px;font-size:12px;color:#64748b}
@media (max-width:980px){.field{min-width:100%}}
@media print{.toolbar,.status{display:none}body{background:#fff}.wrap{max-width:none;margin:0;border:0}.table-wrap{max-height:none;overflow:visible;border:0}th,tfoot td{position:static}}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wrap">
  <h1>{{ $title }}</h1>
  <div class="sub">Module key: {{ $moduleId }}</div>

  <form class="toolbar" id="filterForm">
    <div class="field">
      <label>Date From</label>
      <input type="date" id="date1" value="{{ $date1 }}">
    </div>
    <div class="field">
      <label>Date To</label>
      <input type="date" id="date2" value="{{ $date2 }}">
    </div>
    <div class="field">
      <label>Group</label>
      <select id="grpcode"><option value="">All Gold Groups</option></select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <label class="checkline"><input type="checkbox" id="includeCp"> CP To Stock</label>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <label class="checkline"><input type="checkbox" id="withNet"> With Netwgt</label>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button type="submit" class="primary">Show</button>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button type="button" id="saveBtn">Save As</button>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button type="button" id="printBtn">Print</button>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button type="button" id="exitBtn">Exit</button>
    </div>
  </form>

  <div class="meta">
    <div>From: <strong id="metaDate1">{{ $date1 }}</strong></div>
    <div>To: <strong id="metaDate2">{{ $date2 }}</strong></div>
    <div>Rows: <strong id="metaCount">0</strong></div>
    <div>Mode: <strong id="metaMode">Gross</strong></div>
  </div>

  <div class="table-wrap" id="tableWrap" style="display:none;">
    <table>
      <thead>
        <tr>
          <th style="width:90px;">Date</th>
          <th class="num" style="width:110px;">Op. Stock</th>
          <th style="width:120px;">Trans Type</th>
          <th>Party Name</th>
          <th style="width:90px;">Doc No</th>
          <th class="num" style="width:110px;">Recv Gold</th>
          <th class="num" style="width:110px;">Recv CP</th>
          <th class="num" style="width:110px;">Issued Gold</th>
          <th class="num" style="width:110px;">Issued CP</th>
          <th class="num" style="width:110px;">Cl. Stock</th>
        </tr>
      </thead>
      <tbody id="reportBody"></tbody>
      <tfoot>
        <tr>
          <td>Total</td>
          <td class="num" id="totOpening">0.000</td>
          <td colspan="3" id="totCount">0 row(s)</td>
          <td class="num" id="totRecvGold">0.000</td>
          <td class="num" id="totRecvCp">0.000</td>
          <td class="num" id="totIssuedGold">0.000</td>
          <td class="num" id="totIssuedCp">0.000</td>
          <td class="num" id="totClosing">0.000</td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="empty" id="emptyState">Choose filters and click <strong>Show</strong> to load the summary.</div>
  <div class="status" id="statusText"></div>
</div>

<script src="{{ asset('js/report-export.js') }}?v=6"></script>
<script>
const API_LOOKUPS = @json(url('/api/stock-register/lookups'));
const API_SUMMARY = @json(url('/api/stock-register-summary/data'));
const RLEVEL = @json((int) $rlevel);
let rows = [];

function $(id){ return document.getElementById(id); }
function esc(v){ return String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
function fmt(n){ return Number(n || 0).toFixed(3); }
function fmtDate(v){ if(!v) return ''; const p=String(v).split('-'); return p.length===3 ? `${p[2]}/${p[1]}/${p[0]}` : String(v); }
function setStatus(text=''){ $('statusText').textContent = text; }

async function loadLookups(){
  try{
    const r = await fetch(API_LOOKUPS);
    const d = await r.json();
    if(!d.ok) return;
    const groups = (d.groups || []).filter(g => String(g.itype || '').trim().toUpperCase() === 'G');
    $('grpcode').innerHTML = '<option value="">All Gold Groups</option>' + groups.map(g =>
      `<option value="${esc(g.code)}">${esc(g.code)} - ${esc(g.name)}</option>`
    ).join('');
  }catch(e){}
}

function render(dataRows, totals){
  rows = dataRows || [];
  $('metaDate1').textContent = $('date1').value;
  $('metaDate2').textContent = $('date2').value;
  $('metaCount').textContent = String(totals.count || 0);
  $('metaMode').textContent = $('withNet').checked ? 'Net' : 'Gross';

  if(!rows.length){
    $('tableWrap').style.display = 'none';
    $('emptyState').style.display = '';
    $('emptyState').innerHTML = 'No rows found for the selected range.';
  } else {
    $('tableWrap').style.display = '';
    $('emptyState').style.display = 'none';
    $('reportBody').innerHTML = rows.map(row => `
      <tr>
        <td>${esc(fmtDate(row.tdate))}</td>
        <td class="num">${esc(fmt(row.topstkwgt))}</td>
        <td>${esc(row.ttype || '')}</td>
        <td>${esc(row.partyname || '')}</td>
        <td>${esc(row.docno || '')}</td>
        <td class="num">${esc(fmt(row.rcvdwgt))}</td>
        <td class="num">${esc(fmt(row.rcvdcpwgt))}</td>
        <td class="num">${esc(fmt(row.issuedwgt))}</td>
        <td class="num">${esc(fmt(row.issuedcpwgt))}</td>
        <td class="num">${esc(fmt(row.clstock))}</td>
      </tr>
    `).join('');
  }

  $('totOpening').textContent = fmt(totals.opening);
  $('totCount').textContent = `${totals.count || 0} row(s)`;
  $('totRecvGold').textContent = fmt(totals.received);
  $('totRecvCp').textContent = fmt(totals.receivedcp);
  $('totIssuedGold').textContent = fmt(totals.issued);
  $('totIssuedCp').textContent = fmt(totals.issuedcp);
  $('totClosing').textContent = fmt(totals.closing);
}

async function loadData(){
  const params = new URLSearchParams({
    date1: $('date1').value,
    date2: $('date2').value,
    rlevel: String(RLEVEL),
    grpcode: $('grpcode').value,
    include_cp: $('includeCp').checked ? '1' : '0',
    with_net: $('withNet').checked ? '1' : '0'
  });

  setStatus('Loading...');

  try{
    const r = await fetch(API_SUMMARY + '?' + params.toString());
    const d = await r.json();
    if(!d.ok){
      setStatus(d.message || 'Unable to load report');
      return;
    }
    render(d.rows || [], d.totals || {});
    setStatus(`${(d.rows || []).length} row(s) loaded.`);
  }catch(e){
    setStatus('Network error while loading report');
  }
}

$('filterForm').addEventListener('submit', function(e){
  e.preventDefault();
  loadData();
});

$('printBtn').addEventListener('click', () => window.print());
$('exitBtn').addEventListener('click', () => window.parent.postMessage({type:'goldapp:close-module-frame'}, '*'));

ReportExport.init('saveBtn',
  () => [
    ['Date','tdate'],['Op. Stock','topstkwgt'],['Trans Type','ttype'],['Party Name','partyname'],['Doc No','docno'],
    ['Recv Gold','rcvdwgt'],['Recv CP','rcvdcpwgt'],['Issued Gold','issuedwgt'],['Issued CP','issuedcpwgt'],['Cl. Stock','clstock']
  ],
  () => rows,
  () => 'stock-register-summary-' + $('date1').value + '-to-' + $('date2').value
);

loadLookups();
</script>
</body>
</html>
