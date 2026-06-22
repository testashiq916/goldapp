<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Segoe UI,Tahoma,sans-serif;background:#eff4f9;color:#15324e}
.page{min-height:100vh;display:flex;flex-direction:column}
.toolbar{display:flex;align-items:center;gap:10px;padding:12px 18px;background:linear-gradient(135deg,#102d49,#1b507f);color:#fff}
.toolbar h1{margin:0;font-size:18px;font-weight:700}
.spacer{flex:1}
.btn{height:36px;padding:0 14px;border:0;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer}
.btn-blue{background:#2f80ed;color:#fff}
.btn-green{background:#179d57;color:#fff}
.btn-orange{background:#d18416;color:#fff}
.btn-gray{background:#5b7188;color:#fff}
.wrap{padding:18px}
.card{max-width:1120px;margin:0 auto;background:#fff;border:1px solid #d5e3f0;border-radius:16px;box-shadow:0 2px 12px rgba(10,27,45,.06);padding:20px}
.grid{display:grid;grid-template-columns:140px 1fr 140px 1fr;gap:12px 16px;align-items:center}
.lbl{font-size:11px;font-weight:700;color:#637a91;text-transform:uppercase;letter-spacing:.4px}
input,select{height:38px;border:1px solid #d5e3f0;border-radius:9px;padding:0 12px;background:#f8fbff;font-size:13px;color:#15324e}
input[readonly]{background:#f2f6fa;color:#5d7388}
.input-with-btn{display:flex;gap:8px}
.input-with-btn input{flex:1}
.settings{margin-top:20px;padding-top:18px;border-top:1px solid #e6eef6}
.settings .grid{grid-template-columns:160px 180px 160px 180px}
.status{max-width:1120px;margin:12px auto 0;color:#5d7388;font-size:12px}
.modal-bg{position:fixed;inset:0;background:rgba(9,22,36,.45);display:none;align-items:center;justify-content:center;padding:18px;z-index:40}
.modal-bg.show{display:flex}
.modal{width:min(760px,100%);max-height:82vh;background:#fff;border-radius:16px;overflow:hidden;display:flex;flex-direction:column}
.modal-head{display:flex;gap:8px;padding:12px;background:#f7fbff;border-bottom:1px solid #d5e3f0}
.modal-head input{flex:1}
.modal-body{overflow:auto}
table{width:100%;border-collapse:collapse}
th{position:sticky;top:0;background:#f3f8fd;padding:10px 12px;font-size:10px;color:#687f95;text-transform:uppercase;letter-spacing:.5px;text-align:left}
td{padding:9px 12px;border-bottom:1px solid #edf3f8;font-size:12px}
tbody tr{cursor:pointer}
tbody tr:hover{background:#edf6ff}
@media(max-width:900px){.grid,.settings .grid{grid-template-columns:1fr 1fr}.toolbar{flex-wrap:wrap}}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="page">
  <div class="toolbar">
    <h1>{{ $title }}</h1>
    <div class="spacer"></div>
    <button class="btn btn-green" id="btnPrint">Print</button>
    <button class="btn btn-orange" id="btnPrintAddr">Print Addr</button>
    <button class="btn btn-blue" id="btnSetDef">Set Def</button>
    <button class="btn btn-gray" id="btnExit">Exit</button>
  </div>

  <div class="wrap">
    <div class="card">
      <div class="grid">
        <div class="lbl">Party</div>
        <div class="input-with-btn">
          <input id="fCode" type="text" maxlength="10" style="text-transform:uppercase">
          <button class="btn btn-blue" id="btnFind" type="button">Find</button>
        </div>

        <div class="lbl">Address</div>
        <input id="fAddress" type="text" readonly>

        <div class="lbl">From Date</div>
        <input id="fDate1" type="text" placeholder="dd/mm/yyyy">

        <div class="lbl">To Date</div>
        <input id="fDate2" type="text" placeholder="dd/mm/yyyy">

        <div class="lbl">Start Line</div>
        <input id="fStartLine" type="number" min="1" value="1">

        <div class="lbl">Start DocNo</div>
        <input id="fDocNo" type="text">

        <div class="lbl">Start Slno</div>
        <input id="fSlno" type="number" min="1" value="1">

        <div class="lbl">Reset</div>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px"><input id="fReset" type="checkbox"> Start from beginning</label>
      </div>

      <div class="settings">
        <div class="grid">
          <div class="lbl">Lines / Page</div>
          <input id="fLinesPerPage" type="number" min="0" value="0">

          <div class="lbl">Lines / Half</div>
          <input id="fLinesHalfPage" type="number" min="0" value="0">

          <div class="lbl">Skip Middle</div>
          <input id="fSkipMiddle" type="number" min="0" value="0">

          <div class="lbl">Top Margin</div>
          <input id="fTopMargin" type="number" min="0" value="0">

          <div class="lbl">Left Margin</div>
          <input id="fLeftMargin" type="number" min="0" value="0">

          <div class="lbl">Footer Margin</div>
          <input id="fFooterMargin" type="number" min="0" value="0">
        </div>
      </div>
    </div>
    <div class="status" id="statusBar">Ready</div>
  </div>
</div>

<div class="modal-bg" id="partyModal">
  <div class="modal">
    <div class="modal-head">
      <input id="partySearch" type="text" placeholder="Search party code / name">
      <button class="btn btn-blue" id="partySearchBtn" type="button">Search</button>
      <button class="btn btn-gray" id="partyCloseBtn" type="button">Close</button>
    </div>
    <div class="modal-body">
      <table>
        <thead><tr><th>Code</th><th>Name</th><th>Address</th></tr></thead>
        <tbody id="partyRows"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
const MODE = @json($mode);
const SITE = @json(url('/'));
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const STORAGE_KEY = 'goldapp:passbook-print:' + MODE;
let lastPrintSlno = 0;

function $(id){ return document.getElementById(id); }
function status(msg){ $('statusBar').textContent = msg; }

async function api(url, body){
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': CSRF,
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify(body || {})
  });
  return await res.json();
}

function loadPrefs(){
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch (e) {
    return {};
  }
}

function savePrefs(){
  localStorage.setItem(STORAGE_KEY, JSON.stringify({
    lines_per_page: $('fLinesPerPage').value,
    lines_half_page: $('fLinesHalfPage').value,
    skip_middle: $('fSkipMiddle').value,
    top_margin: $('fTopMargin').value,
    left_margin: $('fLeftMargin').value,
    footer_margin: $('fFooterMargin').value,
  }));
}

function openPrintWindow(title, html, margins){
  const w = window.open('', '_blank', 'width=900,height=700');
  if (!w) return;
  const top = parseInt(margins.top || 0, 10) || 0;
  const left = parseInt(margins.left || 0, 10) || 0;
  const bottom = parseInt(margins.bottom || 0, 10) || 0;
  w.document.write(`<!doctype html><html><head><title>${title}</title><style>
    @page{margin:${top}px ${left}px ${bottom}px ${left}px}
    body{font-family:Courier New,monospace;font-size:12px;color:#111;margin:0}
    .sheet{padding:0 8px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:4px 6px;border-bottom:1px solid #ddd;text-align:left}
    th{text-transform:uppercase;font-size:10px;letter-spacing:.4px}
    .blank td{border-bottom:none;height:22px}
    .meta{margin-bottom:12px}
    .meta h2{margin:0 0 6px;font-size:18px}
  </style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head><body>${html}<script>window.onload=function(){window.print();}<\/script></body></html>`);
  w.document.close();
}

async function searchParties(){
  const data = await api(`${SITE}/api/passbook-print/party-search`, { mode: MODE, q: $('partySearch').value || '' });
  const body = $('partyRows');
  body.innerHTML = '';
  (data.rows || []).forEach(row => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${row.code}</td><td>${row.name}</td><td>${row.address || ''}</td>`;
    tr.addEventListener('click', async () => {
      $('fCode').value = row.code;
      $('partyModal').classList.remove('show');
      await loadParty();
    });
    body.appendChild(tr);
  });
}

async function loadParty(){
  const code = ($('fCode').value || '').trim().toUpperCase();
  $('fCode').value = code;
  if (!code) return;

  const data = await api(`${SITE}/api/passbook-print/party-lookup`, { code, mode: MODE });
  if (!data.ok) {
    alert(data.message || 'Invalid party');
    return;
  }

  const party = data.party || {};
  $('fAddress').value = party.address || '';
  $('fStartLine').value = party.start_line || 1;
  $('fDocNo').value = party.start_docno || '';
  $('fSlno').value = party.start_slno || 1;
  lastPrintSlno = party.last_print_slno || 0;
  status('Party loaded');
}

async function doPrint(){
  const payload = {
    code: $('fCode').value,
    mode: MODE,
    date1: $('fDate1').value,
    date2: $('fDate2').value,
    start_line: $('fStartLine').value,
    start_docno: $('fDocNo').value,
    start_slno: $('fSlno').value,
    reset: $('fReset').checked,
    lines_per_page: $('fLinesPerPage').value,
    lines_half_page: $('fLinesHalfPage').value,
    skip_middle: $('fSkipMiddle').value,
  };

  const data = await api(`${SITE}/api/passbook-print/build`, payload);
  if (!data.ok) {
    alert(data.message || 'Unable to build passbook');
    return;
  }

  const party = data.party || {};
  const rows = (data.rows || []).map(row => {
    if (row.blank) {
      return '<tr class="blank"><td colspan="8">&nbsp;</td></tr>';
    }
    return `<tr>
      <td>${row.tno ?? ''}</td>
      <td>${row.tdate ?? ''}</td>
      <td>${row.docno ?? ''}</td>
      <td>${row.rcptno ?? ''}</td>
      <td>${row.rate ?? ''}</td>
      <td style="text-align:right">${Number(row.amount || 0).toFixed(2)}</td>
      <td style="text-align:right">${Number(row.wgt || 0).toFixed(3)}</td>
      <td>${row.agent ?? ''}</td>
    </tr>`;
  }).join('');

  const html = `
    <div class="sheet">
      <div class="meta">
        <h2>Passbook Print</h2>
        <div><strong>Code:</strong> ${party.code || ''}</div>
        <div><strong>Address:</strong> ${party.address || ''}</div>
      </div>
      <table>
        <thead><tr><th>No</th><th>Date</th><th>Doc No</th><th>Rcpt No</th><th>Rate</th><th>Amount</th><th>Wgt</th><th>Agent</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;

  openPrintWindow('Passbook Print', html, {
    top: $('fTopMargin').value,
    left: $('fLeftMargin').value,
    bottom: $('fFooterMargin').value,
  });
  status('Print prepared');
  await loadParty();
}

async function doPrintAddr(){
  const data = await api(`${SITE}/api/passbook-print/address`, { code: $('fCode').value, mode: MODE });
  if (!data.ok) {
    alert(data.message || 'Unable to prepare address');
    return;
  }

  const rows = (data.rows || []).map(row => `<tr><td style="width:150px"><strong>${row.note || ''}</strong></td><td>${row.part || ''}</td></tr>`).join('');
  openPrintWindow('Passbook Address', `<div class="sheet"><h2>Passbook Address</h2><table><tbody>${rows}</tbody></table></div>`, {
    top: $('fTopMargin').value,
    left: $('fLeftMargin').value,
    bottom: $('fFooterMargin').value,
  });
  status('Address print prepared');
}

async function init(){
  const prefs = loadPrefs();
  const data = await api(`${SITE}/api/passbook-print/init`, { mode: MODE });
  const defaults = data.defaults || {};
  $('fDate1').value = data.today || '';
  $('fDate2').value = data.today || '';
  $('fLinesPerPage').value = prefs.lines_per_page ?? defaults.lines_per_page ?? 0;
  $('fLinesHalfPage').value = prefs.lines_half_page ?? defaults.lines_half_page ?? 0;
  $('fSkipMiddle').value = prefs.skip_middle ?? defaults.skip_middle ?? 0;
  $('fTopMargin').value = prefs.top_margin ?? defaults.top_margin ?? 0;
  $('fLeftMargin').value = prefs.left_margin ?? defaults.left_margin ?? 0;
  $('fFooterMargin').value = prefs.footer_margin ?? defaults.footer_margin ?? 0;
  status('Ready');
}

$('btnFind').addEventListener('click', () => { $('partyModal').classList.add('show'); $('partySearch').value = $('fCode').value || ''; searchParties(); });
$('partySearchBtn').addEventListener('click', searchParties);
$('partySearch').addEventListener('keydown', e => { if (e.key === 'Enter') searchParties(); });
$('partyCloseBtn').addEventListener('click', () => $('partyModal').classList.remove('show'));
$('fCode').addEventListener('blur', loadParty);
$('btnPrint').addEventListener('click', doPrint);
$('btnPrintAddr').addEventListener('click', doPrintAddr);
$('btnSetDef').addEventListener('click', () => { savePrefs(); status('Defaults saved'); });
$('btnExit').addEventListener('click', () => {
  if (window.parent && window.parent !== window && typeof window.parent.closeActiveModuleFrame === 'function') {
    window.parent.closeActiveModuleFrame();
    return;
  }
  window.close();
});
['fLinesPerPage','fLinesHalfPage','fSkipMiddle','fTopMargin','fLeftMargin','fFooterMargin'].forEach(id => $(id).addEventListener('change', savePrefs));
window.addEventListener('load', init);
</script>
</body>
</html>
