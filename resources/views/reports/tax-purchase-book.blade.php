<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
@php
    $reportReadableVersion = @filemtime(public_path('css/report-readable.css')) ?: time();
    $rowNavigationVersion = @filemtime(public_path('js/report-row-navigation.js')) ?: time();
    $reportExportVersion = @filemtime(public_path('js/report-export.js')) ?: time();
@endphp
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;color:#1e293b;font-size:20px;height:100vh;overflow:hidden;display:flex;flex-direction:column}

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:8px 14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;flex-shrink:0}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap;margin-right:4px}
.tb-lbl{color:rgba(255,255,255,.8);font-size:17px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.tb-input,.tb-select{height:28px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 6px;font-size:17px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus,.tb-select:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.tb-select option{background:#1e3a5f;color:#fff}
.tb-check{display:flex;align-items:center;gap:4px;color:rgba(255,255,255,.88);font-size:11px;font-weight:600;white-space:nowrap}
.tb-check input{width:14px;height:14px;accent-color:#60a5fa}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:17px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}

.grid-wrap{flex:1;overflow:auto}
table{width:100%;border-collapse:collapse;background:#fff;font-size:18px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:5px 6px;font-size:15px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2;cursor:pointer;user-select:none}
th:hover{background:#e2e8f0}
th.num{text-align:right}
td{padding:4px 6px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
tr.sel td{background:#dbeafe !important}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:17px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:16px;flex-wrap:wrap;font-size:17px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:17px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}
.modal-mask{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:250;padding:18px}
.modal-mask.show{display:flex}
.modal{width:min(920px,96vw);max-height:90vh;background:#fff;border-radius:10px;box-shadow:0 18px 55px rgba(0,0,0,.28);display:flex;flex-direction:column;overflow:hidden}
.modal-head{background:#1e3a5f;color:#fff;padding:10px 14px;display:flex;align-items:center;justify-content:space-between}
.modal-head h2{font-size:14px;margin:0}
.modal-close{border:none;background:rgba(255,255,255,.18);color:#fff;border-radius:5px;width:28px;height:26px;cursor:pointer;font-weight:800}
.modal-body{padding:12px 14px;overflow:auto}
.bc-grid{display:grid;grid-template-columns:repeat(5,minmax(90px,1fr));gap:8px;align-items:end;margin-bottom:10px}
.bc-grid label{display:flex;flex-direction:column;gap:3px;color:#334155;font-size:17px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.bc-grid input,.bc-grid select{height:30px;border:1px solid #cbd5e1;border-radius:6px;padding:0 8px;font-size:12px;background:#fff}
.bc-actions{display:flex;gap:8px;justify-content:flex-end;border-top:1px solid #e2e8f0;padding:10px 14px;background:#f8fafc}
.bc-preview{border:1px solid #e2e8f0;border-radius:8px;overflow:auto;max-height:46vh}
.bc-preview table{font-size:11px}
.bc-preview th{top:0}
.wait-mask{position:fixed;inset:0;background:rgba(15,23,42,.35);display:none;align-items:center;justify-content:center;z-index:300}
.wait-mask.show{display:flex}
.wait-card{background:#fff;border-radius:12px;padding:18px 24px;min-width:260px;box-shadow:0 12px 40px rgba(0,0,0,.25);text-align:center}
.wait-card strong{display:block;font-size:16px;color:#1e3a5f;margin-bottom:6px}
.wait-card span{font-size:12px;color:#64748b}

@media print{.toolbar,.summary,.sub-header{display:none !important}.grid-wrap{overflow:visible !important}body{height:auto;overflow:visible}}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ $reportReadableVersion }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ $rowNavigationVersion }}" defer></script>
</head>
<body>
<div class="wait-mask" id="waitMask">
  <div class="wait-card">
    <strong>Please wait</strong>
    <span>Loading report data...</span>
  </div>
</div>

<div class="toolbar">
  <h1>{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:120px"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:120px"></div>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl">Mode</span>
    <select id="mode" class="tb-select" style="width:110px">
      <option value="daywise">Day Wise</option>
      <option value="billwise">Bill Wise</option>
    </select>
  </div>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl">Bill Type</span>
    <select id="billtype" class="tb-select" style="width:120px"><option value="">-- All --</option></select>
  </div>
  <label class="tb-check"><input type="checkbox" id="cbRegisteredPurchase"> Registered Purchase</label>
  <label class="tb-check"><input type="checkbox" id="cbUnregisteredB2b"> Unregistered Purchase</label>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button class="btn btn-out" id="btnSort">Sort</button>
  <button type="button" class="btn btn-out" id="btnBillCorrect">Bill Correct</button>
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button type="button" class="btn btn-out" id="btnExcel">To Excel</button>
  <button type="button" class="btn btn-out" id="btnPdf">To PDF</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="sub-header" id="subHeader"></div>

<div class="grid-wrap">
  <table>
    <thead id="thead"></thead>
    <tbody id="tbody"><tr><td colspan="14" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
  </table>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<div class="modal-mask" id="billCorrectModal">
  <div class="modal">
    <div class="modal-head">
      <h2>Bill Correct</h2>
      <button type="button" class="modal-close" id="bcClose">X</button>
    </div>
    <div class="modal-body">
      <div class="bc-grid">
        <label>From Date<input id="bcDate1" type="date" value="{{ $date1 }}"></label>
        <label>To Date<input id="bcDate2" type="date" value="{{ $date2 }}"></label>
        <label>Purchase Type
          <select id="bcPurchaseType">
            <option value="all">All</option>
            <option value="registered">Registered</option>
            <option value="unregistered">Unregistered</option>
          </select>
        </label>
        <label>Rearrange
          <select id="bcSeries">
            <option value="OG,OS">OG + OS</option>
            <option value="OG">OG Only</option>
            <option value="OS">OS Only</option>
          </select>
        </label>
        <label>OG Prefix<input id="bcOgPrefix" value="OG"></label>
        <label>OG Codes<input id="bcOgCodes" value="OG,OD,ODM,GO"></label>
        <label>OS Prefix<input id="bcOsPrefix" value="OS"></label>
        <label>OS Codes<input id="bcOsCodes" value="OS"></label>
        <label>Start No<input id="bcStartNo" type="number" min="1" value="1"></label>
        <label>Digits<input id="bcDigits" type="number" min="1" max="8" value="3"></label>
      </div>
      <div class="bc-preview">
        <table>
          <thead><tr><th>Date</th><th>Group</th><th>Item Code</th><th>Old Doc No</th><th>Old Bill No</th><th>New No</th><th>Supplier</th></tr></thead>
          <tbody id="bcRows"><tr><td colspan="7" style="text-align:center;padding:26px;color:#94a3b8">Preview bill correction before applying</td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="bc-actions">
      <button type="button" class="btn btn-out" id="bcPreview">Preview</button>
      <button type="button" class="btn btn-show" id="bcApply">Apply</button>
    </div>
  </div>
</div>

<script>
const SITE = @json(rtrim(url('/'), '/'));
const RLEVEL = {{ $rlevel }};
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let rows = [], totals = {}, sortCol = '', sortDir = 1, currentMode = 'daywise';
const WAIT = document.getElementById('waitMask');

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

function daywiseHeaders(){
  return [
    ['Date','tdate'],['Doc Nos','docnos'],
    ['Gr.Wgt','grosswgt',1,3],['Net Wgt','netwgt',1,3],
    ['Bill Amt','billamt',1],['Paid Amt','pamt',1],['Ex.Amt','eamt',1],
    ['Discount','discount',1],['HMC','hmc',1],
    ['SGST','sgst',1],['CGST','cgst',1],['IGST','igst',1],
    ['Round','round_amt',1],['Net Amt','netamt',1]
  ];
}

function billwiseHeaders(){
  return [
    ['Date','tdate'],['Doc No','docno'],['Bill No','billno'],['Supplier','suppname'],['Address','suppaddr'],['GST No','gstno'],
    ['Gr.Wgt','grosswgt',1,3],['Net Wgt','netwgt',1,3],
    ['Bill Amt','billamt',1],['Paid Amt','pamt',1],['Ex.Amt','eamt',1],
    ['Discount','discount',1],['HMC','hmc',1],
    ['SGST','sgst',1],['CGST','cgst',1],['IGST','igst',1],
    ['Net Amt','netamt',1]
  ];
}

function headers(){ return currentMode === 'billwise' ? billwiseHeaders() : daywiseHeaders(); }

async function loadLookups(){
  try {
    const r = await fetch(SITE+'/api/tax-purchase-book/lookups');
    const d = await r.json();
    if(!d.ok) return;
    const sel = document.getElementById('billtype');
    const first = sel.options[0].outerHTML;
    sel.innerHTML = first + (d.billtypes||[]).map(i=>'<option value="'+esc(i.code)+'">'+esc(i.code)+' - '+esc(i.name)+'</option>').join('');
  } catch(e){}
}
loadLookups();

async function loadData(){
  if (WAIT) WAIT.classList.add('show');
  currentMode = document.getElementById('mode').value;
  const qs = new URLSearchParams({
    date1: document.getElementById('date1').value,
    date2: document.getElementById('date2').value,
    rlevel: RLEVEL,
    mode: currentMode,
    billtype: document.getElementById('billtype').value,
    registered_purchase: document.getElementById('cbRegisteredPurchase').checked ? '1' : '0',
    unregistered_purchase: document.getElementById('cbUnregisteredB2b').checked ? '1' : '0',
  });

  document.getElementById('subHeader').textContent =
    'From: '+document.getElementById('date1').value+' To: '+document.getElementById('date2').value+
    ' ('+currentMode.charAt(0).toUpperCase()+currentMode.slice(1)+')';

  try {
    const r = await fetch(SITE+'/api/tax-purchase-book/data?'+qs);
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Error',false); return; }
    rows = d.rows||[]; totals = d.totals||{};
    sortCol = ''; sortDir = 1;
    render();
    toast('Loaded '+rows.length+' rows');
  } catch(e){ toast('Network error',false); }
  finally { if (WAIT) WAIT.classList.remove('show'); }
}

function render(){
  const hs = headers();
  const thead = document.getElementById('thead');
  const tbody = document.getElementById('tbody');

  thead.innerHTML = '<tr>' + hs.map(h=>{
    let arrow = sortCol===h[1] ? (sortDir===1?' &#9650;':' &#9660;') : '';
    return '<th class="'+(h[2]?'num':'')+'" data-key="'+h[1]+'">'+esc(h[0])+arrow+'</th>';
  }).join('') + '</tr>';

  if(!rows.length){
    tbody.innerHTML = '<tr><td colspan="'+hs.length+'" style="text-align:center;padding:40px;color:#94a3b8">No records found</td></tr>';
  } else {
    tbody.innerHTML = rows.map((row,idx)=>{
      return '<tr data-idx="'+idx+'">' + hs.map(h=>{
        let val = row[h[1]];
        if(h[2]) val = nf(val, h[3]!=null?h[3]:2);
        return '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
      }).join('') + '</tr>';
    }).join('');
  }

  const t = totals;
  document.getElementById('summary').innerHTML = [
    '<span>Rows: <b>'+nf(t.count,0)+'</b></span>',
    '<span>Gr.Wgt: <b>'+nf(t.grosswgt,3)+'</b></span>',
    '<span>Net Wgt: <b>'+nf(t.netwgt,3)+'</b></span>',
    '<span>Bill Amt: <b>'+nf(t.billamt)+'</b></span>',
    '<span>SGST: <b>'+nf(t.sgst)+'</b></span>',
    '<span>CGST: <b>'+nf(t.cgst)+'</b></span>',
    '<span>IGST: <b>'+nf(t.igst)+'</b></span>',
    '<span>Net Amt: <b>'+nf(t.netamt)+'</b></span>',
  ].join('');
}

document.getElementById('thead').addEventListener('click', e=>{
  const th = e.target.closest('th[data-key]'); if(!th) return;
  const key = th.dataset.key;
  if(sortCol===key) sortDir*=-1; else { sortCol=key; sortDir=1; }
  rows.sort((a,b)=>{
    let va=a[key]??'', vb=b[key]??'';
    if(!isNaN(parseFloat(va))) return (parseFloat(va)-parseFloat(vb))*sortDir;
    return String(va).localeCompare(String(vb))*sortDir;
  });
  render();
});

document.getElementById('tbody').addEventListener('click', e=>{
  const tr = e.target.closest('tr[data-idx]'); if(!tr) return;
  document.querySelectorAll('#tbody tr').forEach(r=>r.classList.remove('sel'));
  tr.classList.add('sel');
});
document.getElementById('tbody').addEventListener('dblclick', e=>{
  const tr = e.target.closest('tr[data-idx]'); if(!tr) return;
  const row = rows[Number(tr.dataset.idx)];
  if(!row || !row.slno){ toast('Bill not found', false); return; }
  const qs = new URLSearchParams({slno: row.slno});
  const targetUrl = SITE + '/purchase-bill/edit?' + qs.toString();
  if(window.parent && window.parent !== window){
    window.parent.postMessage({
      type: 'goldapp:open-module-url',
      moduleId: 'purchase-bill-edit-' + row.slno,
      title: 'Edit Purchase ' + (row.docno || row.billno || ''),
      url: targetUrl,
      forceNewTab: true
    }, '*');
  } else {
    window.open(targetUrl, '_blank');
  }
});

document.getElementById('btnSort').onclick = ()=>{ sortCol=''; sortDir=1; render(); toast('Sort reset'); };
document.getElementById('btnShow').onclick = loadData;
function billCorrectPayload(){
  return {
    date1: document.getElementById('bcDate1').value,
    date2: document.getElementById('bcDate2').value,
    rlevel: RLEVEL,
    purchase_type: document.getElementById('bcPurchaseType').value,
    series: document.getElementById('bcSeries').value,
    og_prefix: document.getElementById('bcOgPrefix').value,
    og_codes: document.getElementById('bcOgCodes').value,
    os_prefix: document.getElementById('bcOsPrefix').value,
    os_codes: document.getElementById('bcOsCodes').value,
    start_no: document.getElementById('bcStartNo').value,
    digits: document.getElementById('bcDigits').value,
  };
}
function renderBillCorrectRows(list){
  const body = document.getElementById('bcRows');
  if(!list.length){
    body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:26px;color:#94a3b8">No matching bills found</td></tr>';
    return;
  }
  body.innerHTML = list.map(r=>'<tr>'+
    '<td>'+esc(r.tdate)+'</td>'+
    '<td>'+esc(r.group)+'</td>'+
    '<td>'+esc(r.item_codes||'')+'</td>'+
    '<td>'+esc(r.old_docno)+'</td>'+
    '<td>'+esc(r.old_billno)+'</td>'+
    '<td><b>'+esc(r.new_no)+'</b></td>'+
    '<td>'+esc(r.supplier)+'</td>'+
  '</tr>').join('');
}
async function runBillCorrect(action){
  if (WAIT) WAIT.classList.add('show');
  try{
    const r = await fetch(SITE+'/api/tax-purchase-book/bill-correct/'+action, {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
      body: JSON.stringify(billCorrectPayload())
    });
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Bill correction failed', false); return; }
    renderBillCorrectRows(d.rows||[]);
    toast(action==='apply' ? 'Backup rearanged created. Updated '+(d.updated||0)+' bills' : 'Preview loaded '+(d.rows||[]).length+' bills');
    if(action==='apply') loadData();
  }catch(e){ toast('Network error', false); }
  finally{ if (WAIT) WAIT.classList.remove('show'); }
}
document.getElementById('btnBillCorrect').onclick = ()=>{
  document.getElementById('bcDate1').value = document.getElementById('date1').value;
  document.getElementById('bcDate2').value = document.getElementById('date2').value;
  document.getElementById('bcPurchaseType').value =
    document.getElementById('cbRegisteredPurchase').checked ? 'registered' :
    (document.getElementById('cbUnregisteredB2b').checked ? 'unregistered' : 'all');
  document.getElementById('billCorrectModal').classList.add('show');
  runBillCorrect('preview');
};
document.getElementById('bcClose').onclick = ()=>document.getElementById('billCorrectModal').classList.remove('show');
document.getElementById('bcPreview').onclick = ()=>runBillCorrect('preview');
document.getElementById('bcApply').onclick = ()=>{
  if(confirm('Create auto backup named rearanged, then change purchasem billno only?')) runBillCorrect('apply');
};
document.getElementById('cbUnregisteredB2b').addEventListener('change', e=>{
  if(e.target.checked){
    document.getElementById('mode').value = 'billwise';
    document.getElementById('cbRegisteredPurchase').checked = false;
  }
});
document.getElementById('cbRegisteredPurchase').addEventListener('change', e=>{
  if(e.target.checked){
    document.getElementById('mode').value = 'billwise';
    document.getElementById('cbUnregisteredB2b').checked = false;
  }
});
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');
</script>
<script src="{{ asset('js/report-export.js') }}?v={{ $reportExportVersion }}"></script>
<script>
function purchaseTaxExportName(){
  return 'tax-purchase-book-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value;
}
function exportPurchaseTaxAs(type){
  if(!rows.length){
    toast('No data to export', false);
    return;
  }
  ReportExport.open(headers, ()=>rows, purchaseTaxExportName);
  setTimeout(function(){
    const card = document.querySelector('[data-xtype="'+type+'"]');
    if(card) card.click();
  }, 0);
}
ReportExport.init('btnSaveAs', headers, ()=>rows, purchaseTaxExportName);
document.getElementById('btnExcel').onclick = function(){
  exportPurchaseTaxAs('xls');
};
document.getElementById('btnPdf').onclick = function(){
  exportPurchaseTaxAs('pdf');
};
</script>
</body>
</html>
