<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Purity Testing Entry</title>
<style>
  :root{--bg:#c0c0c0;--panel:#d7d7d7;--field:#fff;--line:#7e7e7e;--ring:#c7ba82}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:680px;margin:12px auto;background:var(--panel);border:1px solid #8a8a8a;box-shadow:inset 0 0 0 1px #efefef}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dedede,#c9c9c9);font-weight:700}
  .frame{padding:16px}
  .fields{display:grid;grid-template-columns:140px 1fr;gap:10px 12px;align-items:center}
  .label{font-weight:700;text-align:right;padding-right:4px}
  input,button{font:inherit}
  input[type="text"],input[type="number"],input[type="date"]{width:100%;height:34px;padding:6px 8px;border:1px solid var(--line);background:var(--field)}
  input[readonly]{background:#d8d8d8}
  input:focus{outline:none;background:#fff5e8;border-color:#9b6a18;box-shadow:0 0 0 1px #ffd08a inset}
  .actions-ring{margin:20px auto 0;max-width:360px;padding:16px 22px;border:4px solid #2748ff;border-radius:999px;background:var(--ring);display:flex;justify-content:center;gap:16px}
  .actions-ring button{min-width:90px;height:38px;border:1px solid #666;background:#f3f0de;font-weight:700;cursor:pointer}
  .actions-ring button:hover{background:#e8e4c8}
  .status{min-height:20px;margin-top:10px;font-weight:700;color:#9b0000;text-align:center}
</style>
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div id="titleLabel">Enter the Purity Details</div>
    <button type="button" id="btnClose">Close</button>
  </div>
  <div class="frame">
    <div class="fields">
      <div class="label">No. :</div>
      <input type="text" id="docno" style="width:160px">

      <div class="label">Date :</div>
      <input type="date" id="tdate" style="width:180px">

      <div class="label">Customer :</div>
      <input type="text" id="customer" maxlength="40">

      <div class="label">Purity in % :</div>
      <input type="number" id="purityinperc" step="0.001" value="0" style="width:160px">

      <div class="label">Purity in CT :</div>
      <input type="number" id="purityinct" step="0.001" value="0" style="width:160px">

      <div class="label">Other Info :</div>
      <input type="text" id="otherinfo" maxlength="40">

      <div class="label">Received On :</div>
      <input type="date" id="rcvdon" style="width:180px">

      <div class="label">Tested On :</div>
      <input type="date" id="testedon" style="width:180px">

      <div class="label">Rcvd Wgt :</div>
      <input type="number" id="rcvdwgt" step="0.001" value="0" style="width:160px">

      <div class="label">Type Of Sample :</div>
      <input type="text" id="typeofsample" maxlength="40">
    </div>

    <div class="actions-ring">
      <button type="button" id="btnSave">Save</button>
      <button type="button" id="btnExit">Exit</button>
    </div>
    <div class="status" id="statusText"></div>
  </div>
</div>

<script>
const API = @json(url('/api/purity-testing'));
const params = new URLSearchParams(window.location.search);
let MODE = params.get('mode') || 'new';
let EDIT_SLNO = parseInt(params.get('slno') || '0', 10);

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function setStatus(msg='',ok=false){ $('statusText').textContent=msg; $('statusText').style.color=ok?'#0b5a18':'#9b0000'; }
function closeFrame(){ window.parent.postMessage({type:'goldapp:close-module-frame'},'*'); }

async function api(payload){
  const res=await fetch(API,{method:'POST',credentials:'same-origin',
    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest'},
    body:JSON.stringify(payload)});
  const data=await res.json();
  if(!res.ok&&!data.success) throw new Error(data.error||'Request failed');
  return data;
}

// Auto-calc Purity in CT from Purity in %
$('purityinperc').addEventListener('change', () => {
  const perc = parseFloat($('purityinperc').value) || 0;
  const ct = (perc * 22) / 91.6;
  $('purityinct').value = ct.toFixed(2);
});

// Save
$('btnSave').addEventListener('click', async () => {
  const customer = $('customer').value.trim();
  if (!customer) { setStatus('Customer is required'); $('customer').focus(); return; }
  if (!confirm('Save purity details?')) return;

  try {
    setStatus('Saving...');
    const d = await api({
      action: 'save',
      edit_slno: EDIT_SLNO,
      docno: $('docno').value.trim(),
      tdate: $('tdate').value,
      customer: customer,
      purityinperc: parseFloat($('purityinperc').value) || 0,
      purityinct: parseFloat($('purityinct').value) || 0,
      otherinfo: $('otherinfo').value.trim(),
      rcvdon: $('rcvdon').value,
      testedon: $('testedon').value,
      rcvdwgt: parseFloat($('rcvdwgt').value) || 0,
      typeofsample: $('typeofsample').value.trim()
    });
    setStatus(d.message || 'Saved', d.success);
    if (d.success) {
      if (MODE === 'edit') {
        closeFrame();
      } else {
        // Re-init for next entry
        const r = await api({action:'init'});
        resetForm(r);
      }
    }
  } catch(e) { setStatus('Save error: ' + e.message); }
});

function resetForm(initData) {
  $('docno').value = initData.nextDocno || '';
  $('tdate').value = initData.today || '';
  $('customer').value = '';
  $('purityinperc').value = '0'; $('purityinct').value = '0';
  $('otherinfo').value = '';
  $('rcvdon').value = initData.today || '';
  $('testedon').value = initData.today || '';
  $('rcvdwgt').value = '0';
  $('typeofsample').value = '';
  EDIT_SLNO = 0;
  MODE = 'new';
  $('titleLabel').textContent = 'Enter the Purity Details';
  $('docno').focus();
}

// Load for edit
async function loadRecord(slno) {
  try {
    setStatus('Loading...');
    const d = await api({action:'load', slno});
    if (!d.success) { setStatus(d.error||'Not found'); return; }
    const rec = d.record;
    EDIT_SLNO = rec.slno;
    MODE = 'edit';
    $('titleLabel').textContent = 'Purity Details Edit';
    $('docno').value = rec.docno;
    $('tdate').value = rec.tdate;
    $('customer').value = rec.customer;
    $('purityinperc').value = rec.purityinperc.toFixed(3);
    $('purityinct').value = rec.purityinct.toFixed(3);
    $('otherinfo').value = rec.otherinfo;
    $('rcvdon').value = rec.rcvdon;
    $('testedon').value = rec.testedon;
    $('rcvdwgt').value = rec.rcvdwgt.toFixed(3);
    $('typeofsample').value = rec.typeofsample;
    setStatus('Loaded: No ' + rec.docno, true);
  } catch(e) { setStatus('Load error: ' + e.message); }
}

// Exit / Close
$('btnExit').addEventListener('click', closeFrame);
$('btnClose').addEventListener('click', closeFrame);

// F9 save, Enter navigation
document.addEventListener('keydown', e => {
  if (e.key === 'F9') { e.preventDefault(); $('btnSave').click(); }
});

// Init
async function init() {
  try {
    const d = await api({action:'init'});
    $('docno').value = d.nextDocno || '';
    $('tdate').value = d.today || '';
    $('rcvdon').value = d.today || '';
    $('testedon').value = d.today || '';

    if (MODE === 'edit' && EDIT_SLNO > 0) {
      await loadRecord(EDIT_SLNO);
    } else {
      setStatus('Ready', true);
      $('docno').focus();
    }
  } catch(e) { setStatus('Init failed: ' + e.message); }
}
init();
</script>
</body>
</html>
