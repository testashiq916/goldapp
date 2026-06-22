<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --g-bg:#f4f7fb; --g-surface:#fff; --g-border:#e3e8ef; --g-text:#384250;
  --g-muted:#7b8794; --g-primary:#5b6dee; --g-header:#2f3d8f; --g-header-2:#4457cb;
}
body{font-family:'Inter','Segoe UI',Tahoma,sans-serif;font-size:12px;color:var(--g-text);
  background:radial-gradient(circle at 10% -10%,#eef3ff 0%,#f4f7fb 40%,#edf2f8 100%);
  min-height:100vh}
input,select,button{transition:all .15s ease;font-family:inherit}
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:#f7fafc}
::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:#a0aec0}

.wrap{margin:10px;background:var(--g-surface);border:1px solid var(--g-border);
  border-radius:12px;box-shadow:0 10px 30px rgba(33,52,89,.10);overflow:hidden}

.bar{background:linear-gradient(135deg,var(--g-header),var(--g-header-2));
  color:#fff;padding:10px 14px;font-size:14px;font-weight:700;letter-spacing:.3px;
  display:flex;align-items:center;gap:8px}
.bar::before{content:'';display:inline-block;width:14px;height:14px;background:#ffb548;border-radius:5px;flex-shrink:0}

.grid{display:grid;grid-template-columns:90px 220px 130px 1fr 90px 200px;gap:8px 12px;
  align-items:center;background:#f9fbff;border-bottom:1px solid var(--g-border);padding:12px 14px}
.grid label{font-weight:600;font-size:11.5px;color:var(--g-muted);white-space:nowrap;text-align:right}
.grid input,.grid select{height:30px;border:1px solid #d6deea;border-radius:7px;
  padding:0 10px;font-size:11.5px;color:var(--g-text);background:var(--g-surface);
  box-shadow:inset 0 1px 2px rgba(17,24,39,.03);outline:none}
.grid input:focus,.grid select:focus{border-color:#a9b7ff;box-shadow:0 0 0 3px rgba(91,109,238,.14)}
.grid input[readonly],.readonly{background:#f1f4f9;color:#6b7280;font-weight:600}
#billNo{font-weight:700;color:#1d4ed8;background:#eef4ff;border-color:#bfcaf0}
#tDate,#dueDate{background:#fefce8}
.docNav{display:flex;gap:6px}
.docNav .btn{flex:1;padding:0 10px}
.btnGroup{display:flex;gap:6px}
.btnGroup .btn{flex:1}
.customerField{position:relative}
.customerInput{width:100%}
.customerResults{position:absolute;top:34px;left:0;right:0;z-index:30;background:var(--g-surface);
  border:1px solid var(--g-border);border-radius:8px;
  box-shadow:0 8px 24px rgba(15,23,42,.18);max-height:240px;overflow:auto;display:none}
.customerResults.show{display:block}
.customerOption{padding:8px 12px;border-bottom:1px solid #edf1f7;cursor:pointer;line-height:1.3}
.customerOption:last-child{border-bottom:0}
.customerOption:hover,.customerOption.active{background:#eef4ff}
.customerCode{font-weight:700;color:#1d4ed8}
.customerMeta{color:var(--g-muted);font-size:11px;margin-top:2px}

.btn{height:30px;padding:0 14px;border:1px solid #cfd8ec;background:var(--g-surface);
  border-radius:8px;cursor:pointer;font-weight:600;font-size:11.5px;color:#2f3a4f}
.btn:hover{background:#eff4ff;border-color:#9fb3f7}
.btn.primary{background:linear-gradient(180deg,#f5f7ff,#eaf0ff);border-color:#9eb0ff;color:#35459f}
.btn.primary:hover{background:#dce8ff}
.btn.danger{background:linear-gradient(180deg,#fff5f5,#ffe4e4);border-color:#fca5a5;color:#b91c1c}
.btn.danger:hover{background:#ffe4e4}

.tableWrap{margin:12px 14px 0;border:1px solid var(--g-border);border-radius:10px;
  overflow:hidden;box-shadow:0 3px 10px rgba(32,55,92,.05)}
.tbl{width:100%;border-collapse:collapse;background:var(--g-surface);font-size:11px}
.tbl thead th{background:linear-gradient(180deg,#364891,#2d3d7b);color:#f7f9ff;
  padding:7px 8px;border:1px solid #42529e;font-weight:600;font-size:10px;
  text-align:center;white-space:nowrap;text-transform:uppercase;letter-spacing:.5px}
.tbl tbody td{border:1px solid #edf1f7;padding:2px 3px;height:30px}
.tbl tbody tr:nth-child(odd){background:#fff}
.tbl tbody tr:nth-child(even){background:#f7faff}
.tbl tbody tr:hover{background:#edf3ff}
.tbl input{width:100%;height:26px;border:none;background:transparent;
  padding:0 6px;font-size:11.5px;color:var(--g-text);font-family:inherit;outline:none}
.tbl input:focus{background:#fffff0;outline:2px solid #4299e1;border-radius:3px}
.tbl input[type=number]{text-align:right}

.itemCell{position:relative}
.itemResults{position:absolute;top:32px;left:2px;min-width:360px;z-index:40;
  background:var(--g-surface);border:1px solid var(--g-border);border-radius:8px;
  box-shadow:0 8px 24px rgba(15,23,42,.18);max-height:240px;overflow:auto;display:none}
.itemResults.show{display:block}
.itemOption{padding:8px 12px;border-bottom:1px solid #edf1f7;cursor:pointer;line-height:1.3}
.itemOption:last-child{border-bottom:0}
.itemOption:hover{background:#eef4ff}
.itemCode{font-weight:700;color:#1d4ed8}
.itemMeta{color:var(--g-muted);font-size:11px;margin-top:2px}

.rowBtns{margin:8px 14px 0;display:flex;gap:8px}
.footer{margin:12px 14px 14px;display:flex;gap:8px;align-items:center;
  padding-top:12px;border-top:1px solid var(--g-border)}
.footer .btn{height:34px;padding:0 18px;font-size:12px}
.msg{margin-left:auto;color:#b91c1c;font-weight:600;white-space:pre-line;font-size:11.5px}

.modal{position:fixed;inset:0;background:rgba(15,23,42,.35);backdrop-filter:blur(2px);
  display:none;align-items:center;justify-content:center;z-index:1000}
.modal.show{display:flex}
.panel{width:min(900px,95vw);max-height:80vh;overflow:auto;background:var(--g-surface);
  border:1px solid var(--g-border);border-radius:12px;box-shadow:0 18px 48px rgba(0,0,0,.18)}
.panel h4{margin:0;padding:10px 14px;background:linear-gradient(135deg,var(--g-header),var(--g-header-2));
  color:#fff;font-size:13px;font-weight:700}
.panel .pbody{padding:12px}

.printMemo{display:none}
@media print{
  @page{size:A4 portrait;margin:10mm}
  body{background:#fff!important;color:#000!important}
  .wrap,.modal,.customerResults,.itemResults{display:none!important}
  .printMemo{display:block!important;width:100%;min-height:calc(297mm - 20mm);margin:0!important;padding:10mm 9mm;font-family:Arial,sans-serif;font-size:12px;color:#000;border:1px solid #111;box-sizing:border-box;overflow:visible}
  .pm-head{text-align:center;border-bottom:2px solid #111;padding-bottom:7px;margin-bottom:9px;break-inside:avoid;page-break-inside:avoid;overflow:visible}
  .pm-head h2{margin:0;font-size:20px;line-height:1.15;letter-spacing:.4px;text-transform:uppercase;white-space:normal;overflow:visible;word-break:normal}
  .pm-shop-line{font-size:12px;font-weight:700;line-height:1.35;text-transform:uppercase;white-space:pre-line;overflow:visible;word-break:normal}
  .pm-shop-meta{display:flex;justify-content:center;gap:14px;flex-wrap:wrap}
  .pm-title{font-weight:800;font-size:18px;margin-top:6px;text-transform:uppercase;letter-spacing:.8px}
  .pm-sub{font-size:10px;margin-top:3px;letter-spacing:.4px;color:#333}
  .pm-meta{display:grid;grid-template-columns:1fr 1fr;gap:5px 12px;margin:10px 0;font-size:11.5px}
  .pm-meta div{min-height:17px;word-break:break-word}
  .pm-meta .full{grid-column:1 / -1}
  .pm-table{width:100%;border-collapse:collapse;margin-top:10px}
  .pm-table th,.pm-table td{border-bottom:1px solid #999;padding:5px 3px;vertical-align:top}
  .pm-table th{text-align:left;font-weight:800;background:#f1f1f1;border-top:1px solid #111;border-bottom:1px solid #111}
  .pm-num{text-align:right}
  .pm-sign{display:flex;justify-content:space-between;margin-top:34px;font-weight:700;font-size:11px}
  .pm-foot{text-align:center;margin-top:14px;padding-top:7px;border-top:1px dashed #777;font-size:10px}
}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wrap">
  <div class="bar"><span>{{ $title }} @if($mode!=='new') &mdash; {{ strtoupper($mode) }} @endif</span></div>

  <div class="grid">
    <label>Doc No</label>
    <input id="billNo" readonly>
    <div class="docNav">
      <button class="btn" id="btnHelp" type="button">Find</button>
      <button class="btn" id="prevBtn" type="button">Previous</button>
      <button class="btn" id="nextBtn" type="button">Next</button>
    </div>
    <div></div>
    <label>Date</label>
    <input id="tDate" type="date">

    <label>Customer</label>
    <input id="custCode" placeholder="Code" autocomplete="off">
    <div class="btnGroup">
      <button class="btn" id="btnCust" type="button">Find</button>
    </div>
    <div class="customerField">
      <input id="custName" class="customerInput" placeholder="Customer Name" autocomplete="off">
      <div id="custResults" class="customerResults"></div>
    </div>
    <label>SMan</label>
    <select id="sman">
      <option value="">--Select--</option>
      @foreach($smen as $s)
      <option value="{{ $s->code }}">{{ $s->code }} - {{ $s->name }}</option>
      @endforeach
    </select>

    <label>Address</label>
    <input id="addr" style="grid-column:span 3" placeholder="Address">
    <label>Due Date</label>
    <input id="dueDate" type="date">
  </div>

  <div class="tableWrap">
    <table class="tbl" id="tbl">
      <thead><tr>
        <th style="width:120px">Item Code</th><th>Item Name</th><th style="width:70px">Qty</th><th style="width:90px">Weight</th><th style="width:90px">StoneWgt</th><th style="width:90px">NetWgt</th><th>Remark</th><th style="width:90px">Cost</th><th style="width:90px">StkType</th>
      </tr></thead>
      <tbody id="tbody"></tbody>
    </table>
  </div>
  <div class="rowBtns">
    <button class="btn" id="addRow">+ Add</button>
    <button class="btn" id="delRow">- Delete</button>
  </div>

  <div class="footer">
    <button class="btn primary" id="saveBtn">&#10003; Save (F9)</button>
    <button class="btn danger" id="cancelBtn">&#10007; Cancel Bill</button>
    <button class="btn" id="printBtn">&#9112; Print</button>
    <button class="btn" id="closeBtn">&#10005; Close</button>
    <div class="msg" id="msg"></div>
  </div>
</div>

<div class="printMemo" id="printMemo"></div>

<div class="modal" id="helpModal">
  <div class="panel">
    <h4>Issue Memo Lookup</h4>
    <div class="pbody">
      <div style="display:flex;gap:6px;margin-bottom:8px"><input id="q" style="flex:1;height:30px"><button class="btn" id="find">Find</button><button class="btn" id="closeHelp">Close</button></div>
      <table class="tbl"><thead><tr><th>BillNo</th><th>Date</th><th>Customer</th><th>Status</th></tr></thead><tbody id="helpRows"></tbody></table>
    </div>
  </div>
</div>

<script>
const MODE = @json($mode);
const INITIAL_DOC_NO = @json($nextDocNo ?? '');
const SHOP_INFO = @json($shopInfo ?? []);
const API_URLS = {
  next:    @json(url('/api/remake-issue-memo-party/next')),
  search:  @json(url('/api/remake-issue-memo-party/search')),
  get:     @json(url('/api/remake-issue-memo-party/get')),
  save:    @json(url('/api/remake-issue-memo-party/save')),
  cancel:  @json(url('/api/remake-issue-memo-party/cancel')),
  nav:     @json(url('/api/remake-issue-memo-party/nav')),
  item:    @json(url('/api/remake-issue-memo-party/item')),
  items:   @json(url('/api/remake-issue-memo-party/items')),
  customer:@json(url('/api/remake-issue-memo-party/customer')),
  customers:@json(url('/api/remake-issue-memo-party/customers'))
};
const $ = id => document.getElementById(id);
const msg = t => $('msg').textContent = t || '';
function escapeHtml(v){ return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtDate(v){ if(!v) return ''; const p = String(v).slice(0,10).split('-'); return p.length===3?`${p[2]}-${p[1]}-${p[0]}`:v; }
function withQuery(base, params={}){ const u=new URL(base, window.location.origin); Object.entries(params).forEach(([k,v])=>u.searchParams.set(k,v??'')); return u.toString(); }
async function api(url){ const r=await fetch(url); return r.json(); }
async function post(url,data){ const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},body:JSON.stringify(data)}); return r.json(); }

let loadedExistingDoc = false;

function rowHtml(r={}){
  return `<tr>
    <td class="itemCell"><input class="itemcode" autocomplete="off" value="${escapeHtml(r.itemcode||'')}" style="text-transform:uppercase"><div class="itemResults"></div></td>
    <td><input class="itemname" value="${escapeHtml(r.itemname||'')}"></td>
    <td><input class="qty" type="number" step="1" value="${r.qty??0}"></td>
    <td><input class="weight" type="number" step="0.001" value="${r.weight??0}"></td>
    <td><input class="stonewgt" type="number" step="0.001" value="${r.stonewgt??0}"></td>
    <td><input class="netwgt" type="number" step="0.001" value="${r.netwgt??0}"></td>
    <td><input class="complaint" value="${escapeHtml(r.complaint||r.remark||'')}"></td>
    <td><input class="cost" type="number" step="0.01" value="${r.cost??0}"></td>
    <td><input class="stktype" value="${escapeHtml(r.stktype||'')}"></td>
  </tr>`;
}
function addRow(r){ $('tbody').insertAdjacentHTML('beforeend', rowHtml(r)); bindRow(); }
function bindRow(){
  document.querySelectorAll('#tbody tr').forEach(tr=>{
    if(tr.dataset.bound==='1') return;
    tr.dataset.bound='1';
    const w=tr.querySelector('.weight'), s=tr.querySelector('.stonewgt'), n=tr.querySelector('.netwgt');
    const sync=()=>{ if(document.activeElement===n){ s.value=((+w.value||0)-(+n.value||0)).toFixed(3); } else { n.value=((+w.value||0)-(+s.value||0)).toFixed(3); } };
    w.oninput=s.oninput=n.oninput=sync;
    const code=tr.querySelector('.itemcode'), name=tr.querySelector('.itemname'), cost=tr.querySelector('.cost');
    let timer=null;
    code.addEventListener('blur', async ()=>{
      const c=code.value.trim().toUpperCase(); if(!c) return;
      const d=await api(withQuery(API_URLS.item,{code:c}));
      if(d.ok && d.item){ code.value=d.item.code; name.value=d.item.name||''; if((+cost.value||0)===0) cost.value=Number(d.item.cost||0).toFixed(2); }
    });
  });
}
function rowsData(){
  return [...document.querySelectorAll('#tbody tr')].map(tr=>({
    itemcode: tr.querySelector('.itemcode').value.trim().toUpperCase(),
    itemname: tr.querySelector('.itemname').value.trim(),
    qty: +tr.querySelector('.qty').value||0,
    weight: +tr.querySelector('.weight').value||0,
    stonewgt: +tr.querySelector('.stonewgt').value||0,
    netwgt: +tr.querySelector('.netwgt').value||0,
    complaint: tr.querySelector('.complaint').value.trim(),
    cost: +tr.querySelector('.cost').value||0,
    stktype: tr.querySelector('.stktype').value.trim(),
  }));
}

async function loadDoc(b){
  const d=await api(withQuery(API_URLS.get,{bill_no:b}));
  if(!d.ok){ msg(d.message||'Not found'); return; }
  $('billNo').value=d.master.billno||'';
  $('tDate').value=(d.master.tdate||'').slice(0,10);
  $('dueDate').value=(d.master.duedate||'').slice(0,10);
  $('custCode').value=d.master.custcode||'';
  $('custName').value=d.master.custname||'';
  $('addr').value=d.master.addr||'';
  $('sman').value=d.master.sman||'';
  $('tbody').innerHTML='';
  (d.rows||[]).forEach(addRow);
  if(!(d.rows||[]).length) addRow();
  window.__slno=d.master.slno||0;
  loadedExistingDoc=true;
  msg('Loaded existing document. Save will update memo only.');
}
async function navigateDoc(direction){
  msg('');
  const d=await api(withQuery(API_URLS.nav,{direction, slno: window.__slno||0, bill_no: $('billNo').value.trim()}));
  if(!d.ok || !d.bill_no){ msg(d.message||'No more records'); return; }
  await loadDoc(d.bill_no);
}

async function findCustomer(){
  msg('');
  const code=$('custCode').value.trim().toUpperCase();
  const name=$('custName').value.trim();
  if(code){
    const d=await api(withQuery(API_URLS.customer,{code}));
    if(d.ok && d.customer){ $('custCode').value=d.customer.code; $('custName').value=d.customer.name; $('addr').value=d.customer.addr||''; return; }
  }
  const d=await api(withQuery(API_URLS.customers,{q: code||name||''}));
  const rows=Array.isArray(d.rows)?d.rows:[];
  if(rows.length===1){ const c=rows[0]; $('custCode').value=c.code; $('custName').value=c.name; $('addr').value=c.addr||''; return; }
  if(!rows.length){ msg('No customer found'); }
}

function buildPrintMemo(){
  const rows=rowsData().filter(r=>r.itemcode||r.itemname||r.qty||r.weight);
  const itemRows=(rows.length?rows:[{}]).map((r,i)=>`
    <tr>
      <td class="pm-num">${i+1}</td>
      <td><strong>${escapeHtml(r.itemcode||'')}</strong><br>${escapeHtml(r.itemname||'')}</td>
      <td class="pm-num">${Number(r.qty||0)}</td>
      <td class="pm-num">${Number(r.weight||0).toFixed(3)}</td>
      <td class="pm-num">${Number(r.netwgt||0).toFixed(3)}</td>
      <td>${escapeHtml(r.complaint||'')}</td>
    </tr>
  `).join('');
  const shopName = SHOP_INFO.name || 'SALEENA GOLD AND DIAMONDS';
  const shopMeta = [
    SHOP_INFO.phone ? `Ph: ${SHOP_INFO.phone}` : '',
    SHOP_INFO.mobile ? `Mobile: ${SHOP_INFO.mobile}` : '',
    SHOP_INFO.gstin ? `GSTIN: ${SHOP_INFO.gstin}` : ''
  ].filter(Boolean);
  $('printMemo').innerHTML=`
    <div class="pm-head">
      <h2>${escapeHtml(shopName)}</h2>
      ${SHOP_INFO.address ? `<div class="pm-shop-line">${escapeHtml(SHOP_INFO.address)}</div>` : ''}
      ${shopMeta.length ? `<div class="pm-shop-line pm-shop-meta">${shopMeta.map(v => `<span>${escapeHtml(v)}</span>`).join('')}</div>` : ''}
      <div class="pm-title">Remake Issue Memo</div>
      <div class="pm-sub">Items issued to party for remake / repair</div>
    </div>
    <div class="pm-meta">
      <div><strong>Doc No:</strong> ${escapeHtml($('billNo').value||'')}</div>
      <div><strong>Date:</strong> ${escapeHtml(fmtDate($('tDate').value||''))}</div>
      <div class="full"><strong>Customer:</strong> ${escapeHtml($('custCode').value||'')} ${escapeHtml($('custName').value||'')}</div>
      <div><strong>Due Date:</strong> ${escapeHtml(fmtDate($('dueDate').value||''))}</div>
      <div><strong>Salesman:</strong> ${escapeHtml($('sman').value||'')}</div>
      <div class="full"><strong>Address:</strong> ${escapeHtml($('addr').value||'')}</div>
    </div>
    <table class="pm-table">
      <thead><tr><th style="width:18px">#</th><th>Item</th><th>Qty</th><th>Wt</th><th>Net</th><th>Remark</th></tr></thead>
      <tbody>${itemRows}</tbody>
    </table>
    <div class="pm-sign">
      <div>Authorized</div>
      <div>Customer Signature</div>
    </div>
    <div class="pm-foot">Items issued for remake. Please retain this slip.</div>
  `;
}

async function init(){
  $('billNo').value=INITIAL_DOC_NO||'';
  try{ const d=await api(API_URLS.next); $('billNo').value=d.doc_no||$('billNo').value||INITIAL_DOC_NO||''; }catch(_){}
  $('tDate').value=new Date().toISOString().slice(0,10);
  addRow();
  if(MODE!=='new'){ $('billNo').readOnly=false; $('billNo').placeholder='Enter/Load bill no'; }
  if(MODE==='cancel'){ $('saveBtn').style.display='none'; $('printBtn').style.display='none'; }
  if(MODE==='reprint'){ $('saveBtn').style.display='none'; $('cancelBtn').style.display='none'; }
}

$('addRow').onclick=()=>addRow();
$('delRow').onclick=()=>{ const r=$('tbody').querySelector('tr:last-child'); if(r) r.remove(); if(!$('tbody').querySelector('tr')) addRow(); };
$('prevBtn').onclick=()=>navigateDoc('previous');
$('nextBtn').onclick=()=>navigateDoc('next');
$('btnCust').onclick=findCustomer;
$('saveBtn').onclick=async()=>{
  msg('');
  const payload={
    mode: (MODE==='edit'||loadedExistingDoc)?'edit':'new',
    slno: window.__slno||0,
    bill_no: $('billNo').value.trim(),
    tdate: $('tDate').value,
    duedate: $('dueDate').value,
    custcode: $('custCode').value.trim().toUpperCase(),
    custname: $('custName').value.trim(),
    sman: $('sman').value,
    addr: $('addr').value.trim(),
    rows: rowsData()
  };
  const d=await post(API_URLS.save, payload);
  if(!d.ok){ msg(d.message||'Save failed'); return; }
  msg('Saved');
  $('billNo').value=d.bill_no||$('billNo').value;
  window.__slno=d.slno||window.__slno;
  buildPrintMemo();
  setTimeout(()=>window.print(), 150);
  if(MODE==='new' && !loadedExistingDoc){
    setTimeout(async()=>{
      $('tbody').innerHTML='';
      addRow();
      const n=await api(API_URLS.next);
      $('billNo').value=n.doc_no||$('billNo').value;
    }, 600);
  }
};
$('cancelBtn').onclick=async()=>{
  const b=$('billNo').value.trim();
  if(!b){ msg('Enter bill no'); return; }
  if(!confirm('Cancel this bill?')) return;
  const d=await post(API_URLS.cancel, {bill_no:b});
  msg(d.message||'');
  if(d.ok){ $('tbody').innerHTML=''; addRow(); loadedExistingDoc=false; }
};
$('printBtn').onclick=()=>{ buildPrintMemo(); window.print(); };
$('closeBtn').onclick=()=>{ if(window.parent && window.parent!==window) window.parent.postMessage({type:'close-module'},'*'); };
$('btnHelp').onclick=async()=>{ $('helpModal').classList.add('show'); await doSearch(); };
$('closeHelp').onclick=()=>$('helpModal').classList.remove('show');
$('find').onclick=()=>doSearch();
async function doSearch(){
  const d=await api(withQuery(API_URLS.search,{q:$('q').value||''}));
  const b=$('helpRows'); b.innerHTML='';
  (d.rows||[]).forEach(r=>{
    const tr=document.createElement('tr');
    tr.innerHTML=`<td>${escapeHtml(r.billno)}</td><td>${(r.tdate||'').slice(0,10)}</td><td>${escapeHtml(r.custname||'')}</td><td>${r.status||''}</td>`;
    tr.onclick=()=>{ $('helpModal').classList.remove('show'); loadDoc(r.billno); };
    b.appendChild(tr);
  });
}
$('billNo').addEventListener('keydown',e=>{ if(e.key==='Enter' && MODE!=='new') loadDoc($('billNo').value.trim()); });
$('custCode').addEventListener('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); findCustomer(); }});
$('custName').addEventListener('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); findCustomer(); }});
document.addEventListener('keydown',e=>{
  if(e.key==='F1'){ e.preventDefault(); $('btnHelp').click(); }
  if(e.key==='F9' && MODE!=='cancel' && MODE!=='reprint'){ e.preventDefault(); $('saveBtn').click(); }
});
init();
</script>
</body>
</html>
