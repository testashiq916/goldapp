<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;color:#1e293b;font-size:20px;height:100vh;overflow:hidden;display:flex;flex-direction:column}

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:8px 14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;flex-shrink:0}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap;margin-right:4px}
.tb-lbl{color:rgba(255,255,255,.8);font-size:17px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.tb-input,.tb-select{height:28px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 6px;font-size:17px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus,.tb-select:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.tb-select option{background:#1e3a5f;color:#fff}
.tb-check{display:flex;align-items:center;gap:3px;color:rgba(255,255,255,.85);font-size:17px;font-weight:600;cursor:pointer;white-space:nowrap}
.tb-check input{width:13px;height:13px;accent-color:#60a5fa;cursor:pointer}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:17px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}
.btn-upd{background:#16a34a;color:#fff}.btn-upd:hover{background:#15803d}

.grid-wrap{flex:1;overflow:auto}
table{width:100%;border-collapse:collapse;background:#fff;font-size:18px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:5px 6px;font-size:15px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2;cursor:pointer;user-select:none}
th:hover{background:#e2e8f0}
th.num{text-align:right}
td{padding:4px 6px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
tr.sel td{background:#dbeafe !important}
tr.st-d td{color:#16a34a}
tr.st-l td{color:#9333ea}
tr.st-a td{color:#ea580c}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:16px;flex-wrap:wrap;font-size:17px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:17px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:17px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

/* Modal */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;display:none;align-items:center;justify-content:center}
.modal-bg.show{display:flex}
.modal{background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.2);width:360px;overflow:hidden}
.modal-hdr{background:#1e3a5f;color:#fff;padding:12px 16px;font-size:13px;font-weight:700}
.modal-body{padding:16px;display:flex;flex-direction:column;gap:10px}
.modal-body label{font-size:11px;font-weight:700;color:#475569}
.modal-body select,.modal-body input,.modal-body textarea{width:100%;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;outline:none}
.modal-body select:focus,.modal-body input:focus,.modal-body textarea:focus{border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,.15)}
.modal-foot{padding:12px 16px;display:flex;gap:8px;justify-content:flex-end;border-top:1px solid #e2e8f0}
.modal-foot .btn{padding:6px 16px}
.btn-cancel{background:#e2e8f0;color:#475569}.btn-cancel:hover{background:#cbd5e1}
.btn-save{background:#3b82f6;color:#fff}.btn-save:hover{background:#2563eb}

@media print{.toolbar,.summary,.sub-header{display:none !important}.grid-wrap{overflow:visible !important}body{height:auto;overflow:visible}}

/* ── Font size overrides ── */
body { font-size: 20px !important; }
label { font-size: 17px !important; }
input, select, button { font-size: 18px !important; height: 36px !important; }
table { font-size: 18px !important; }
th { font-size: 15px !important; }
td { font-size: 18px !important; }
.btn, button { font-size: 17px !important; height: 36px !important; }</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>

<div class="toolbar">
  <h1>{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:120px"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:120px"></div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Party</span><input type="text" id="custcode" class="tb-input" placeholder="Code" style="width:80px;text-transform:uppercase"></div>
  <div class="f-group"><span class="tb-lbl">C/o</span><input type="text" id="cocode" class="tb-input" placeholder="Code" style="width:80px;text-transform:uppercase"></div>
  <div class="f-group"><span class="tb-lbl">SMan</span><select id="smcode" class="tb-select" style="width:120px"><option value="">-- All --</option></select></div>
  <div class="sep"></div>
  <div class="f-group">
    <label class="tb-check"><input type="checkbox" id="cbAgent" checked> Agent</label>
    <select id="agcode" class="tb-select" style="width:120px"><option value="">-- All --</option></select>
  </div>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl">Type</span>
    <select id="reptype" class="tb-select" style="width:110px">
      <option value="">All</option>
      <option value="delivered">Delivered</option>
      <option value="locker">Locker</option>
      <option value="anamath">Anamath</option>
      <option value="pending">Pending</option>
    </select>
  </div>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button class="btn btn-upd" id="btnUpdate">Update Status</button>
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="sub-header" id="subHeader"></div>

<div class="grid-wrap">
  <table>
    <thead id="thead"></thead>
    <tbody id="tbody"><tr><td colspan="12" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
  </table>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<!-- Update Status Modal -->
<div class="modal-bg" id="modalBg">
  <div class="modal">
    <div class="modal-hdr">Update Delivery Status</div>
    <div class="modal-body">
      <div><label>Bill No</label><input type="text" id="mBillno" readonly></div>
      <div><label>Customer</label><input type="text" id="mCust" readonly></div>
      <div>
        <label>Status</label>
        <select id="mStatus">
          <option value="P">Pending</option>
          <option value="D">Delivered</option>
          <option value="L">Locker</option>
          <option value="A">Anamath</option>
        </select>
      </div>
      <div><label>Security Weight</label><input type="number" id="mSecwgt" step="0.001" value="0"></div>
      <div><label>Note</label><textarea id="mSecnote" rows="2"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-cancel" id="btnModalCancel">Cancel</button>
      <button class="btn btn-save" id="btnModalSave">Save</button>
    </div>
  </div>
</div>

<script>
const SITE = @json(request()->root());
const RLEVEL = {{ $rlevel }};
let rows = [], totals = {}, sortCol = '', sortDir = 1, selIdx = -1;

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

function headers(){
  return [
    ['Date','tdate'],['Bill No','billno'],['Customer','custname'],
    ['Bill Amt','billamt',1],['Ex.Amt','eamt',1],['Disc','discount',1],
    ['Net Amt','netamt',1],['Gross Wgt','grosswgt',1,3],['Net Wgt','netwgt',1,3],['Gold Wgt','goldwgt',1,3],
    ['SMan','smcode'],['Agent','agcode'],
    ['Status','statuslabel'],['Sec.Wgt','secwgt',1,3],['Note','secnote']
  ];
}

/* ── Lookups ──────────────────────────────────────── */
async function loadLookups(){
  try {
    const r = await fetch(SITE+'/api/delivery-status/lookups');
    const d = await r.json();
    if(!d.ok) return;
    fillSelect('smcode', d.salesmen||[]);
    fillSelect('agcode', d.agents||[]);
  } catch(e){}
}
function fillSelect(id, items){
  const sel = document.getElementById(id);
  const first = sel.options[0].outerHTML;
  sel.innerHTML = first + items.map(i=>'<option value="'+esc(i.code)+'">'+esc(i.code)+' - '+esc(i.name)+'</option>').join('');
}
loadLookups();

/* ── Load data ────────────────────────────────────── */
async function loadData(){
  const qs = new URLSearchParams({
    date1: document.getElementById('date1').value,
    date2: document.getElementById('date2').value,
    rlevel: RLEVEL,
    custcode: document.getElementById('custcode').value.trim().toUpperCase(),
    cocode: document.getElementById('cocode').value.trim().toUpperCase(),
    smcode: document.getElementById('smcode').value,
    agcode: document.getElementById('cbAgent').checked ? document.getElementById('agcode').value : '',
    agon: document.getElementById('cbAgent').checked ? 1 : 0,
    reptype: document.getElementById('reptype').value,
  });

  document.getElementById('subHeader').textContent = 'From: '+document.getElementById('date1').value+' To: '+document.getElementById('date2').value;

  try {
    const r = await fetch(SITE+'/api/delivery-status/data?'+qs);
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Error',false); return; }
    rows = d.rows||[]; totals = d.totals||{};
    sortCol = ''; sortDir = 1; selIdx = -1;
    render();
    toast('Loaded '+rows.length+' rows');
  } catch(e){ toast('Network error',false); }
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
      const ds = (row.dstatus||'').toUpperCase();
      let cls = '';
      if(ds==='D') cls='st-d'; else if(ds==='L') cls='st-l'; else if(ds==='A') cls='st-a';
      return '<tr data-idx="'+idx+'" class="'+cls+(idx===selIdx?' sel':'')+'">' + hs.map(h=>{
        let val = row[h[1]];
        if(h[2]) val = nf(val, h[3]!=null?h[3]:2);
        return '<td class="'+(h[2]?'num':'')+'">'+esc(val)+'</td>';
      }).join('') + '</tr>';
    }).join('');
  }

  const t = totals;
  document.getElementById('summary').innerHTML = [
    '<span>Bills: <b>'+nf(t.count,0)+'</b></span>',
    '<span>Bill Amt: <b>'+nf(t.billamt)+'</b></span>',
    '<span>Ex.Amt: <b>'+nf(t.eamt)+'</b></span>',
    '<span>Disc: <b>'+nf(t.discount)+'</b></span>',
    '<span>Net Amt: <b>'+nf(t.netamt)+'</b></span>',
    '<span>Gross Wgt: <b>'+nf(t.grosswgt,3)+'</b></span>',
    '<span>Net Wgt: <b>'+nf(t.netwgt,3)+'</b></span>',
    '<span>Gold Wgt: <b>'+nf(t.goldwgt,3)+'</b></span>',
  ].join('');
}

/* ── Sort ─────────────────────────────────────────── */
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
  selIdx = parseInt(tr.dataset.idx);
  document.querySelectorAll('#tbody tr').forEach(r=>r.classList.remove('sel'));
  tr.classList.add('sel');
});

/* ── Update Status Modal ──────────────────────────── */
document.getElementById('btnUpdate').onclick = ()=>{
  if(selIdx<0 || !rows[selIdx]){ toast('Select a row first',false); return; }
  const row = rows[selIdx];
  document.getElementById('mBillno').value = row.billno||'';
  document.getElementById('mCust').value = row.custname||'';
  const ds = (row.dstatus||'').toUpperCase();
  document.getElementById('mStatus').value = ['D','L','A'].includes(ds) ? ds : 'P';
  document.getElementById('mSecwgt').value = parseFloat(row.secwgt||0);
  document.getElementById('mSecnote').value = row.secnote||'';
  document.getElementById('modalBg').classList.add('show');
};

document.getElementById('btnModalCancel').onclick = ()=>{
  document.getElementById('modalBg').classList.remove('show');
};

document.getElementById('btnModalSave').onclick = async ()=>{
  if(selIdx<0 || !rows[selIdx]) return;
  const row = rows[selIdx];
  const body = new URLSearchParams({
    slno: row.slno,
    dstatus: document.getElementById('mStatus').value,
    secwgt: document.getElementById('mSecwgt').value,
    secnote: document.getElementById('mSecnote').value,
  });

  try {
    const r = await fetch(SITE+'/api/delivery-status/update', {
      method:'POST', body,
      headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,
               'Content-Type':'application/x-www-form-urlencoded'}
    });
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Update failed',false); return; }

    // Update local row
    const st = document.getElementById('mStatus').value;
    row.dstatus = st;
    row.secwgt = parseFloat(document.getElementById('mSecwgt').value);
    row.secnote = document.getElementById('mSecnote').value;
    row.statuslabel = {D:'Delivered',L:'Locker',A:'Anamath'}[st]||'Pending';
    render();
    document.getElementById('modalBg').classList.remove('show');
    toast('Status updated');
  } catch(e){ toast('Network error',false); }
};

// Double-click to open update
document.getElementById('tbody').addEventListener('dblclick', e=>{
  const tr = e.target.closest('tr[data-idx]'); if(!tr) return;
  selIdx = parseInt(tr.dataset.idx);
  document.getElementById('btnUpdate').click();
});

document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');
</script>
<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>
ReportExport.init('btnSaveAs', headers, ()=>rows,
  ()=>'delivery-status-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
</script>
</body>
</html>
