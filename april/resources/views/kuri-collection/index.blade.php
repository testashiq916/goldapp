<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Segoe UI,Tahoma,sans-serif;background:#eef3f8;color:#17324d}
.page{min-height:100vh;display:flex;flex-direction:column}
.toolbar{display:flex;align-items:center;gap:10px;padding:12px 18px;background:linear-gradient(135deg,#10304e,#1b4d76);color:#fff}
.toolbar h1{margin:0;font-size:17px;font-weight:700}
.badge{padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.14);font-size:11px;font-weight:700}
.spacer{flex:1}
.btn{height:34px;padding:0 14px;border:0;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer}
.btn-blue{background:#2f80ed;color:#fff}
.btn-green{background:#179d57;color:#fff}
.btn-red{background:#d64545;color:#fff}
.btn-gray{background:#5b7188;color:#fff}
.btn-lite{background:#fff;color:#17324d;border:1px solid #cfe0f3}
.btn:disabled{opacity:.55;cursor:not-allowed}
.content{padding:16px;display:flex;flex-direction:column;gap:14px}
.card{background:#fff;border:1px solid #d6e4f2;border-radius:14px;box-shadow:0 2px 10px rgba(12,32,56,.06)}
.filters{padding:14px 16px}
.grid{display:grid;grid-template-columns:120px 160px 110px 180px 1fr;gap:12px 14px;align-items:center}
.field{display:flex;flex-direction:column;gap:5px}
.field label{font-size:11px;font-weight:700;color:#5d7288;text-transform:uppercase;letter-spacing:.4px}
.field input,.field select{height:36px;border:1px solid #d6e4f2;border-radius:8px;padding:0 10px;font-size:13px;background:#f8fbff}
.checks{display:flex;flex-wrap:wrap;gap:14px;align-items:center;padding-top:2px}
.checks label{display:flex;gap:6px;align-items:center;font-size:12px;color:#24496e}
.checks input{accent-color:#1f7ae0}
.table-wrap{padding:0 16px 16px;overflow:auto}
table{width:100%;border-collapse:collapse;min-width:1180px}
th{position:sticky;top:0;background:#eff6fc;color:#56718c;font-size:10px;text-transform:uppercase;letter-spacing:.5px;padding:10px 8px;border-bottom:1px solid #d6e4f2;white-space:nowrap}
td{padding:6px 8px;border-bottom:1px solid #edf3f9;font-size:12px;vertical-align:middle}
tbody tr.selected{background:#edf6ff}
input.cell,select.cell{width:100%;height:32px;border:1px solid #d6e4f2;border-radius:7px;padding:0 8px;background:#fff;font-size:12px}
input.cell[readonly]{background:#f6f9fc;color:#62788f}
.num{text-align:right}
.mini{width:34px;height:32px;border:1px solid #d6e4f2;border-radius:7px;background:#fff;cursor:pointer}
.footer{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:1px solid #d6e4f2;background:#fbfdff}
.status{font-size:12px;color:#5d7288}
.totals{display:flex;gap:18px;font-size:12px;font-weight:700;color:#17324d}
.modal-bg{position:fixed;inset:0;background:rgba(8,24,39,.44);display:none;align-items:center;justify-content:center;padding:18px;z-index:50}
.modal-bg.show{display:flex}
.modal{width:min(860px,100%);max-height:82vh;background:#fff;border-radius:16px;overflow:hidden;display:flex;flex-direction:column}
.modal-head{display:flex;gap:8px;align-items:center;padding:12px 14px;border-bottom:1px solid #d6e4f2;background:#f7fbff}
.modal-head input{flex:1;height:38px;border:1px solid #d6e4f2;border-radius:8px;padding:0 12px}
.modal-body{overflow:auto}
@media(max-width:900px){
  .grid{grid-template-columns:1fr 1fr}
  .toolbar{flex-wrap:wrap}
}
</style>
</head>
<body>
<div class="page">
  <div class="toolbar">
    <h1>{{ $title }}</h1>
    <span class="badge">{{ strtoupper($modeLabel) }}</span>
    <div class="spacer"></div>
    <button class="btn btn-blue" id="btnLoad">Edit</button>
    <button class="btn btn-green" id="btnSave">Save</button>
    <button class="btn btn-red" id="btnDeleteRow">Delete Row</button>
    <button class="btn btn-gray" id="btnExit">Exit</button>
  </div>

  <div class="content">
    <div class="card filters">
      <div class="grid">
        <div class="field">
          <label>Collection Date</label>
          <input id="fDate" type="text" placeholder="dd/mm/yyyy">
        </div>
        <div class="field">
          <label>Gold Rate</label>
          <input id="fGoldRate" type="number" step="0.01" class="num">
        </div>
        <div class="field">
          <label>Cash A/c</label>
          <select id="fCashAc"></select>
        </div>
        <div class="field">
          <label>Narration</label>
          <input id="fNarration" type="text" maxlength="120">
        </div>
        <div class="checks">
          <label><input type="checkbox" id="fAutoRate"> Auto Rate</label>
          <label><input type="checkbox" id="fAutoRcpt"> Auto Increase Rcpt No</label>
          <label><input type="checkbox" id="fSortRcpt"> Sort On Rcpt No in Edit</label>
          <label><input type="checkbox" id="fSendSms"> Send SMS</label>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table id="gridTable">
          <thead>
            <tr>
              <th>Date</th>
              <th>Party</th>
              <th>Code</th>
              <th>OB Amt</th>
              <th>OB Wgt</th>
              <th>Rcpt No</th>
              <th>Amount</th>
              <th>In Wgt</th>
              <th>CB Amt</th>
              <th>CB Wgt</th>
              <th>Agent</th>
              <th>Print</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
      <div class="footer">
        <div class="status" id="statusBar">Ready</div>
        <div class="totals">
          <span>Total Amount: <strong id="totAmount">0.00</strong></span>
          <span>Total Wgt: <strong id="totWgt">0.000</strong></span>
          <button class="btn btn-lite" id="btnAddRow" type="button">Add Row</button>
          <button class="btn btn-lite" id="btnSelAll" type="button">Sel All</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal-bg" id="partyModal">
  <div class="modal">
    <div class="modal-head">
      <input id="partySearch" type="text" placeholder="Search party code / name / mobile">
      <button class="btn btn-blue" id="partySearchBtn" type="button">Search</button>
      <button class="btn btn-gray" id="partyModalClose" type="button">Close</button>
    </div>
    <div class="modal-body">
      <table>
        <thead><tr><th>Code</th><th>Name</th><th>Mobile</th><th>City</th><th>Agent</th></tr></thead>
        <tbody id="partyResults"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
const MODE = @json($mode);
const SITE = @json(url('/'));
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const STORAGE_KEY = 'goldapp:kuri-collection:' + MODE;
let salesmen = [];
let selectedRowIndex = -1;
let nextReceiptNo = 1;

function $(id){ return document.getElementById(id); }

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

function status(msg){ $('statusBar').textContent = msg; }

function loadPrefs(){
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch (e) {
    return {};
  }
}

function savePrefs(){
  const data = {
    autoRate: $('fAutoRate').checked,
    autoRcpt: $('fAutoRcpt').checked,
    sortRcpt: $('fSortRcpt').checked,
    sendSms: $('fSendSms').checked,
    cashAc: $('fCashAc').value,
  };
  localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
}

function formatNum(v, d){ return Number(v || 0).toFixed(d); }
function parseNum(v){ const n = parseFloat(v); return Number.isFinite(n) ? n : 0; }

function rowTemplate(row = {}){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input class="cell row-date" value="${row.tdate || $('fDate').value}"></td>
    <td><div style="display:flex;gap:6px"><input class="cell row-name" readonly value="${row.name || ''}"><button class="mini row-find" type="button">...</button></div></td>
    <td><input class="cell row-code" value="${row.code || ''}" style="text-transform:uppercase"></td>
    <td><input class="cell row-ob num" readonly value="${formatNum(row.ob, 2)}"></td>
    <td><input class="cell row-obwgt num" readonly value="${formatNum(row.obwgt, 3)}"></td>
    <td><input class="cell row-rcpt num" value="${row.rcptno || ''}"></td>
    <td><input class="cell row-amount num" value="${formatNum(row.amount, 2)}"></td>
    <td><input class="cell row-wgt num" readonly value="${formatNum(row.wgt, 3)}"></td>
    <td><input class="cell row-cb num" readonly value="${formatNum(row.cb, 2)}"></td>
    <td><input class="cell row-cbwgt num" readonly value="${formatNum(row.cbwgt, 3)}"></td>
    <td>${salesmanSelect(row.agent || '')}</td>
    <td style="text-align:center"><input type="checkbox" class="row-prnt" ${row.prnt === 0 ? '' : 'checked'}></td>
    <td><button class="mini row-del" type="button">x</button><input type="hidden" class="row-slno" value="${row.slno || 0}"><input type="hidden" class="row-showwgt" value="${row.showwgt || 'N'}"><input type="hidden" class="row-minlimit" value="${row.minlimit || 0}"><input type="hidden" class="row-maxlimit" value="${row.maxlimit || 0}"><input type="hidden" class="row-totcolln" value="${row.totcolln || 0}"><input type="hidden" class="row-vchno" value="${row.vchno || ''}"></td>
  `;

  bindRow(tr);
  tr.addEventListener('click', () => selectRow(tr));
  $('rows').appendChild(tr);
  recalcRow(tr);
  recalcTotals();
  return tr;
}

function salesmanSelect(value){
  const options = ['<option value=""></option>'].concat(salesmen.map(s => `<option value="${s.code}" ${s.code === value ? 'selected' : ''}>${s.code} - ${s.name}</option>`));
  return `<select class="cell row-agent">${options.join('')}</select>`;
}

function bindRow(tr){
  tr.querySelector('.row-del').addEventListener('click', (e) => {
    e.stopPropagation();
    const slno = parseInt(tr.querySelector('.row-slno').value || '0', 10);
    if (slno > 0 && confirm('Delete this saved row?')) {
      deleteSavedRow(slno, tr);
      return;
    }
    tr.remove();
    recalcTotals();
  });

  tr.querySelector('.row-find').addEventListener('click', (e) => {
    e.stopPropagation();
    openPartyModal(tr);
  });

  tr.querySelector('.row-code').addEventListener('blur', async () => {
    await fillParty(tr);
  });

  tr.querySelector('.row-amount').addEventListener('change', () => {
    validateAmount(tr);
    recalcRow(tr);
  });

  tr.querySelector('.row-agent').addEventListener('change', () => recalcTotals());
}

function selectRow(tr){
  Array.from($('rows').children).forEach(r => r.classList.remove('selected'));
  tr.classList.add('selected');
  selectedRowIndex = Array.from($('rows').children).indexOf(tr);
}

async function openPartyModal(tr){
  selectRow(tr);
  $('partyModal').classList.add('show');
  $('partySearch').value = tr.querySelector('.row-code').value || '';
  await searchParties();
  $('partySearch').focus();
}

async function searchParties(){
  const data = await api(`${SITE}/api/kuri-collection/party-search`, { mode: MODE, q: $('partySearch').value || '' });
  const body = $('partyResults');
  body.innerHTML = '';
  (data.rows || []).forEach(row => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${row.code}</td><td>${row.name}</td><td>${row.mobile || ''}</td><td>${row.city || ''}</td><td>${row.agent || ''}</td>`;
    tr.addEventListener('click', async () => {
      const target = $('rows').children[selectedRowIndex];
      if (!target) return;
      target.querySelector('.row-code').value = row.code;
      target.querySelector('.row-name').value = row.name;
      target.querySelector('.row-agent').value = row.agent || '';
      $('partyModal').classList.remove('show');
      await fillParty(target);
    });
    body.appendChild(tr);
  });
}

async function fillParty(tr){
  const code = (tr.querySelector('.row-code').value || '').trim().toUpperCase();
  tr.querySelector('.row-code').value = code;
  if (!code) {
    tr.querySelector('.row-name').value = '';
    return;
  }

  const data = await api(`${SITE}/api/kuri-collection/party-lookup`, {
    mode: MODE,
    code,
    date: $('fDate').value,
    exclude_slno: tr.querySelector('.row-slno').value || 0
  });

  if (!data.ok) {
    alert(data.message || 'Invalid party');
    tr.querySelector('.row-code').value = '';
    tr.querySelector('.row-name').value = '';
    return;
  }

  const p = data.party;
  tr.querySelector('.row-name').value = p.name || '';
  tr.querySelector('.row-agent').value = p.agent || '';
  tr.querySelector('.row-ob').value = formatNum(p.ob, 2);
  tr.querySelector('.row-obwgt').value = formatNum(p.obwgt, 3);
  tr.querySelector('.row-showwgt').value = p.showwgt || 'N';
  tr.querySelector('.row-minlimit').value = p.minlimit || 0;
  tr.querySelector('.row-maxlimit').value = p.maxlimit || 0;
  tr.querySelector('.row-totcolln').value = p.totcolln || 0;
  if (!parseNum(tr.querySelector('.row-amount').value) && p.instamt > 0) {
    tr.querySelector('.row-amount').value = formatNum(p.instamt, 2);
  }
  if ($('fAutoRcpt').checked && !tr.querySelector('.row-rcpt').value) {
    tr.querySelector('.row-rcpt').value = String(nextReceiptNo++);
  }
  recalcRow(tr);
}

function validateAmount(tr){
  const amount = parseNum(tr.querySelector('.row-amount').value);
  const min = parseNum(tr.querySelector('.row-minlimit').value);
  const max = parseNum(tr.querySelector('.row-maxlimit').value);
  const tot = parseNum(tr.querySelector('.row-totcolln').value);
  if (min > 0 && amount > 0 && amount < min) {
    alert('Minimum amount should be ' + formatNum(min, 2));
    tr.querySelector('.row-amount').value = '0.00';
  } else if (max > 0 && amount > 0 && (amount + tot) > max) {
    alert("Can't exceed maximum amount " + formatNum(max, 2));
    tr.querySelector('.row-amount').value = '0.00';
  }
}

function recalcRow(tr){
  const amount = parseNum(tr.querySelector('.row-amount').value);
  const ob = parseNum(tr.querySelector('.row-ob').value);
  const obwgt = parseNum(tr.querySelector('.row-obwgt').value);
  const goldRate = parseNum($('fGoldRate').value);
  const showWgt = tr.querySelector('.row-showwgt').value === 'Y';
  const wgt = showWgt && goldRate > 0 ? amount / goldRate : 0;
  tr.querySelector('.row-wgt').value = formatNum(wgt, 3);
  tr.querySelector('.row-cb').value = formatNum(ob + amount, 2);
  tr.querySelector('.row-cbwgt').value = formatNum(obwgt + wgt, 3);
}

function recalcTotals(){
  let amt = 0;
  let wgt = 0;
  Array.from($('rows').children).forEach(tr => {
    amt += parseNum(tr.querySelector('.row-amount').value);
    wgt += parseNum(tr.querySelector('.row-wgt').value);
  });
  $('totAmount').textContent = formatNum(amt, 2);
  $('totWgt').textContent = formatNum(wgt, 3);
}

function gatherRows(){
  return Array.from($('rows').children).map(tr => ({
    slno: parseInt(tr.querySelector('.row-slno').value || '0', 10),
    tdate: tr.querySelector('.row-date').value || $('fDate').value,
    code: tr.querySelector('.row-code').value || '',
    rcptno: tr.querySelector('.row-rcpt').value || '',
    amount: parseNum(tr.querySelector('.row-amount').value),
    agent: tr.querySelector('.row-agent').value || '',
    prnt: tr.querySelector('.row-prnt').checked ? 1 : 0,
    vchno: tr.querySelector('.row-vchno').value || '',
  }));
}

async function loadDateRows(){
  status('Loading collection details...');
  const data = await api(`${SITE}/api/kuri-collection/load`, {
    mode: MODE,
    date: $('fDate').value,
    sort_on_receipt: $('fSortRcpt').checked
  });
  if (!data.ok) {
    alert(data.message || 'Unable to load');
    status('Load failed');
    return;
  }
  $('rows').innerHTML = '';
  if ($('fAutoRate').checked && data.goldRate) {
    $('fGoldRate').value = formatNum(data.goldRate, 2);
  }
  (data.rows || []).forEach(row => rowTemplate(row));
  if ((data.rows || []).length === 0) rowTemplate();
  status('Loaded');
}

async function deleteSavedRow(slno, tr){
  const data = await api(`${SITE}/api/kuri-collection/delete`, { slno });
  if (!data.ok) {
    alert(data.message || 'Delete failed');
    return;
  }
  tr.remove();
  recalcTotals();
  status('Row deleted');
}

async function saveAll(){
  savePrefs();
  const data = await api(`${SITE}/api/kuri-collection/save`, {
    mode: MODE,
    date: $('fDate').value,
    gold_rate: $('fGoldRate').value,
    cash_account: $('fCashAc').value,
    note: $('fNarration').value,
    auto_receipt: $('fAutoRcpt').checked,
    rows: gatherRows()
  });
  if (!data.ok) {
    alert(data.message || 'Save failed');
    status('Save failed');
    return;
  }
  nextReceiptNo = data.nextReceiptNo || nextReceiptNo;
  status('Saved successfully');
  await loadDateRows();
}

async function init(){
  const prefs = loadPrefs();
  const data = await api(`${SITE}/api/kuri-collection/init`, { mode: MODE });
  if (!data.ok) {
    status(data.message || 'Init failed');
    return;
  }

  salesmen = data.salesmen || [];
  $('fDate').value = data.today || '';
  $('fGoldRate').value = formatNum(data.goldRate, 2);
  $('fCashAc').innerHTML = (data.cashAccounts || []).map(a => `<option value="${a.code}">${a.code} - ${a.name}</option>`).join('');
  $('fCashAc').value = prefs.cashAc || data.defaultCash || '';
  $('fAutoRate').checked = !!prefs.autoRate;
  $('fAutoRcpt').checked = !!prefs.autoRcpt;
  $('fSortRcpt').checked = !!prefs.sortRcpt;
  $('fSendSms').checked = !!prefs.sendSms;
  nextReceiptNo = data.nextReceiptNo || 1;
  rowTemplate();
  status('Ready');
}

$('btnAddRow').addEventListener('click', () => rowTemplate());
$('btnSelAll').addEventListener('click', () => {
  const btn = $('btnSelAll');
  const selectAll = btn.textContent === 'Sel All';
  Array.from(document.querySelectorAll('.row-prnt')).forEach(cb => cb.checked = selectAll);
  btn.textContent = selectAll ? 'Desel All' : 'Sel All';
});
$('btnLoad').addEventListener('click', loadDateRows);
$('btnSave').addEventListener('click', saveAll);
$('btnDeleteRow').addEventListener('click', () => {
  const tr = $('rows').children[selectedRowIndex];
  if (!tr) return;
  tr.querySelector('.row-del').click();
});
$('btnExit').addEventListener('click', () => {
  if (window.parent && window.parent !== window && typeof window.parent.closeActiveModuleFrame === 'function') {
    window.parent.closeActiveModuleFrame();
    return;
  }
  window.close();
});
$('partySearchBtn').addEventListener('click', searchParties);
$('partySearch').addEventListener('keydown', (e) => { if (e.key === 'Enter') searchParties(); });
$('partyModalClose').addEventListener('click', () => $('partyModal').classList.remove('show'));
$('fGoldRate').addEventListener('change', () => Array.from($('rows').children).forEach(recalcRow));
['fAutoRate','fAutoRcpt','fSortRcpt','fSendSms','fCashAc'].forEach(id => $(id).addEventListener('change', savePrefs));
window.addEventListener('load', init);
</script>
</body>
</html>
