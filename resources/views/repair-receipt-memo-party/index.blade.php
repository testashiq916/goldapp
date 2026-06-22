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
.payRow{margin:10px 14px 0;display:grid;grid-template-columns:200px 240px 1fr;gap:10px;
  background:#f9fbff;border:1px solid var(--g-border);border-radius:10px;padding:10px 12px}
.payCell{display:flex;flex-direction:column;gap:4px}
.payCell label{font-size:11px;font-weight:600;color:var(--g-muted);letter-spacing:.3px}
.payCell input,.payCell select{height:32px;border:1px solid #d6deea;border-radius:7px;
  padding:0 10px;font-size:11.5px;color:var(--g-text);background:var(--g-surface);
  box-shadow:inset 0 1px 2px rgba(17,24,39,.03);outline:none;font-family:inherit}
.payCell input:focus,.payCell select:focus{border-color:#a9b7ff;box-shadow:0 0 0 3px rgba(91,109,238,.14)}
#recvAmt{text-align:right;font-weight:700;color:#1d4ed8;background:#eef4ff;border-color:#bfcaf0}
@media(max-width:900px){.payRow{grid-template-columns:1fr}}
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
  .printMemo{display:block!important;width:100%;min-height:calc(297mm - 20mm);margin:0!important;padding:10mm 9mm;font-family:"Times New Roman",serif;font-size:12px;color:#000;border:1px solid #111;box-sizing:border-box;overflow:visible}
  .pm-head{text-align:center;padding-bottom:8px;margin-bottom:10px;break-inside:avoid;page-break-inside:avoid;overflow:visible}
  .pm-head h2{margin:0;font-size:20px;line-height:1.15;letter-spacing:.4px;text-transform:uppercase;white-space:normal;overflow:visible;word-break:normal}
  .pm-shop-line{font-size:12px;font-weight:700;line-height:1.35;text-transform:uppercase;white-space:pre-line;overflow:visible;word-break:normal}
  .pm-shop-meta{display:flex;justify-content:center;gap:14px;flex-wrap:wrap}
  .pm-title{font-weight:800;font-size:16px;margin-top:10px;text-transform:uppercase;letter-spacing:.5px}
  .pm-meta{display:grid;grid-template-columns:1fr 1fr;gap:3px 22px;margin:10px 0 8px;font-size:12px}
  .pm-line{display:grid;grid-template-columns:96px 8px 1fr;min-height:18px;word-break:break-word}
  .pm-line b{font-weight:800}
  .pm-table{width:100%;border-collapse:collapse;margin-top:9px;font-size:12px}
  .pm-table thead{display:table-header-group}
  .pm-table tr{break-inside:avoid;page-break-inside:avoid}
  .pm-table th,.pm-table td{border:1px solid #333;padding:5px 4px;vertical-align:top}
  .pm-table th{text-align:left;font-weight:800;background:#fff}
  .pm-num{text-align:right}
  .pm-total td{font-weight:800}
  .pm-remarks{display:grid;grid-template-columns:90px 1fr;margin-top:14px;font-size:13px;font-weight:800}
  .pm-sign{display:grid;grid-template-columns:repeat(4,1fr);align-items:end;margin-top:72px;font-weight:800;font-size:13px;text-align:center}
  .pm-sign div{min-height:18px}
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
      <button class="btn" id="btnCust" type="button" onclick="findCustomerClick()">Find</button>
      <button class="btn" id="btnCustCreate" type="button">+ Customer</button>
    </div>
    <div class="customerField">
      <input id="custName" class="customerInput" placeholder="Customer Name" autocomplete="off">
      <div id="custResults" class="customerResults"></div>
    </div>
    <label>SMan *</label>
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

    <label>Ref Issue Bill</label>
    <input id="refBill" placeholder="RM2/00001" style="text-transform:uppercase">
    <div class="btnGroup">
      <button class="btn" id="btnLoadIssue" type="button">&#128229; Load</button>
    </div>
    <div></div>
    <label></label>
    <div></div>
  </div>

  <div class="tableWrap">
    <table class="tbl" id="tbl">
      <thead><tr>
        <th style="width:120px">Item Code</th><th>Item Name</th><th style="width:70px">Qty</th><th style="width:90px">Weight</th><th style="width:90px">StoneWgt</th><th style="width:90px">NetWgt</th><th>Complaint</th><th style="width:90px">Cost</th><th style="width:90px">Purity</th><th style="width:90px">StkType</th>
      </tr></thead>
      <tbody id="tbody"></tbody>
    </table>
  </div>
  <div class="rowBtns">
    <button class="btn" id="addRow">+ Add</button>
    <button class="btn" id="delRow">- Delete</button>
  </div>

  <div class="payRow">
    <div class="payCell"><label>Sale Type</label>
      <select id="saleType">
        <option value="CASH">Cash Sale</option>
        <option value="CREDIT">Credit Sale</option>
      </select>
    </div>
    <div class="payCell"><label>Received Amt</label>
      <input id="recvAmt" type="number" step="0.01" value="0">
    </div>
    <div class="payCell"><label>Cash/Bank</label>
      <select id="cbCode"><option value="CASH">CASH - CASH IN HAND</option></select>
    </div>
    <div class="payCell payNote"><label>Note</label>
      <input id="noteFld" placeholder="Notes / remark">
    </div>
  </div>

  <div class="footer">
    <button class="btn primary" id="saveBtn">&#10003; Save (F9)</button>
    <button class="btn danger" id="cancelBtn">&#10007; Cancel Bill</button>
    <button class="btn" id="printBtn">&#9112; Print</button>
    <button class="btn" id="closeBtn">&#10005; Close</button>
    <div class="msg" id="msg"></div>
  </div>
</div>
<datalist id="itemOptions"></datalist>
<div class="printMemo" id="printMemo"></div>

<div class="modal" id="helpModal">
  <div class="panel">
    <h4>Repair Memo Help</h4>
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
  next: @json(url('/api/remake-rcpt-memo-to-party/next')),
  search: @json(url('/api/remake-rcpt-memo-to-party/search')),
  get: @json(url('/api/remake-rcpt-memo-to-party/get')),
  save: @json(url('/api/remake-rcpt-memo-to-party/save')),
  cancel: @json(url('/api/remake-rcpt-memo-to-party/cancel')),
  nav: @json(url('/api/remake-rcpt-memo-to-party/nav')),
  item: @json(url('/api/remake-rcpt-memo-to-party/item')),
  items: @json(url('/api/remake-rcpt-memo-to-party/items')),
  customer: @json(url('/api/remake-rcpt-memo-to-party/customer')),
  customers: @json(url('/api/remake-rcpt-memo-to-party/customers')),
  cashBanks: @json(url('/api/remake-rcpt-memo-to-party/cash-banks')),
  loadIssue: @json(url('/api/remake-rcpt-memo-to-party/load-issue'))
};
function withQuery(base, params={}){
  const url = new URL(base, window.location.origin);
  Object.entries(params).forEach(([k,v]) => url.searchParams.set(k, v ?? ''));
  return url.toString();
}
const $ = id => document.getElementById(id);
const msg = t => $('msg').textContent = t || '';
let customerFetchTimer = null;
let customerRows = [];
let customerHighlight = -1;
let loadedExistingDoc = false;
let selectedCustomerPhone = '';

function rowHtml(r={}){
  return `<tr>
    <td class="itemCell"><input class="itemcode" autocomplete="off" value="${r.itemcode||''}"><div class="itemResults"></div></td>
    <td><input class="itemname" value="${r.itemname||''}"></td>
    <td><input class="qty" type="number" step="1" value="${r.qty??0}"></td>
    <td><input class="weight" type="number" step="0.001" value="${r.weight??0}"></td>
    <td><input class="stonewgt" type="number" step="0.001" value="${r.stonewgt??0}"></td>
    <td><input class="netwgt" type="number" step="0.001" value="${r.netwgt??0}"></td>
    <td><input class="complaint" value="${r.complaint||''}"></td>
    <td><input class="cost" type="number" step="0.01" value="${r.cost??0}"></td>
    <td><input class="purity" value="${r.purity||''}"></td>
    <td><input class="stktype" value="${r.stktype||''}"></td>
  </tr>`;
}
function addRow(r){ $('tbody').insertAdjacentHTML('beforeend', rowHtml(r)); bindRowCalc(); }
let itemRows = [];
async function searchItems(term){
  const q = String(term || '').trim();
  const d = await api(withQuery(API_URLS.items, { q }));
  itemRows = Array.isArray(d.rows) ? d.rows : [];
  $('itemOptions').innerHTML = itemRows.map(r =>
    `<option value="${escapeHtml(r.code || '')}">${escapeHtml(r.name || '')}</option>`
  ).join('');
  return itemRows;
}
function renderItemResults(tr, rows){
  const box = tr.querySelector('.itemResults');
  const list = Array.isArray(rows) ? rows : [];
  if(!box || !list.length){
    if(box){ box.classList.remove('show'); box.innerHTML = ''; }
    return;
  }
  box.innerHTML = list.map((r, i) => `
    <div class="itemOption" data-idx="${i}">
      <div><span class="itemCode">${escapeHtml(r.code || '')}</span> - ${escapeHtml(r.name || '')}</div>
      <div class="itemMeta">Cost: ${Number(r.cost || 0).toFixed(2)}</div>
    </div>
  `).join('');
  box.classList.add('show');
  box.querySelectorAll('.itemOption').forEach(el => {
    el.onmousedown = e => {
      e.preventDefault();
      const item = list[+el.dataset.idx];
      if(item) applyItemToRow(tr, item);
    };
  });
}
function hideItemResults(tr){
  const box = tr?.querySelector('.itemResults');
  if(box){
    box.classList.remove('show');
  }
}
function applyItemToRow(tr, item){
  tr.querySelector('.itemcode').value = item.code || '';
  tr.querySelector('.itemname').value = item.name || '';
  const cost = tr.querySelector('.cost');
  if((+cost.value || 0) === 0){
    cost.value = Number(item.cost || 0).toFixed(2);
  }
  hideItemResults(tr);
}
function focusNextRow(tr){
  let next = tr.nextElementSibling;
  if(!next){
    addRow();
    next = tr.nextElementSibling;
  }
  next?.querySelector('.itemcode')?.focus();
}
function focusNextField(input){
  const fields = [...document.querySelectorAll('#tbody input')];
  const idx = fields.indexOf(input);
  if(idx >= 0 && idx < fields.length - 1){
    fields[idx + 1].focus();
    fields[idx + 1].select?.();
    return;
  }
  const tr = input.closest('tr');
  if(tr){
    focusNextRow(tr);
  }
}
async function autofillItemRow(codeInput, nameInput, costInput){
  const code = codeInput.value.trim().toUpperCase();
  if(!code){
    return false;
  }
  const d = await api(withQuery(API_URLS.item, { code }));
  if(!d.ok || !d.item){
    return false;
  }
  codeInput.value = d.item.code || code;
  nameInput.value = d.item.name || '';
  if((+costInput.value || 0) === 0){
    costInput.value = (d.item.cost || 0).toFixed(2);
  }
  return true;
}
async function commitItemCode(tr){
  const code = tr.querySelector('.itemcode');
  const name = tr.querySelector('.itemname');
  const cost = tr.querySelector('.cost');
  const raw = code.value.trim();
  if(!raw){
    hideItemResults(tr);
    focusNextField(code);
    return false;
  }
  const matched = await autofillItemRow(code, name, cost);
  if(matched){
    hideItemResults(tr);
    focusNextField(code);
    return true;
  }
  const rows = await searchItems(raw);
  const exact = rows.find(r => String(r.code || '').trim().toUpperCase() === raw.toUpperCase());
  if(exact || rows.length === 1){
    applyItemToRow(tr, exact || rows[0]);
    focusNextField(code);
    return true;
  }
  renderItemResults(tr, rows);
  if(!rows.length){
    msg('No item found');
  }
  code.focus();
  code.select();
  return false;
}
async function commitItemName(tr){
  const name = tr.querySelector('.itemname');
  const raw = name.value.trim();
  if(!raw){
    hideItemResults(tr);
    focusNextField(name);
    return false;
  }
  const rows = await searchItems(raw);
  const exact = rows.find(r => String(r.name || '').trim().toUpperCase() === raw.toUpperCase());
  if(exact || rows.length === 1){
    applyItemToRow(tr, exact || rows[0]);
    focusNextField(name);
    return true;
  }
  renderItemResults(tr, rows);
  if(!rows.length){
    msg('No item found');
  }
  name.focus();
  name.select();
  return false;
}
function bindRowCalc(){
  document.querySelectorAll('#tbody tr').forEach(tr=>{
    if(tr.dataset.bound === '1'){
      return;
    }
    tr.dataset.bound = '1';

    const w=tr.querySelector('.weight');
    const s=tr.querySelector('.stonewgt');
    const n=tr.querySelector('.netwgt');
    const code=tr.querySelector('.itemcode');
    const name=tr.querySelector('.itemname');
    const cost=tr.querySelector('.cost');
    let itemFetchTimer = null;

    const sync=()=>{
      if(document.activeElement===n){
        s.value=((+w.value||0)-(+n.value||0)).toFixed(3);
      } else {
        n.value=((+w.value||0)-(+s.value||0)).toFixed(3);
      }
    };

    w.oninput=s.oninput=n.oninput=sync;

    code.addEventListener('input', ()=>{
      clearTimeout(itemFetchTimer);
      name.value = '';
      const raw = code.value.trim();
      if(raw === ''){
        hideItemResults(tr);
        return;
      }
      itemFetchTimer = setTimeout(async()=>{
        const matched = await autofillItemRow(code, name, cost);
        hideItemResults(tr);
        if(!matched){
          const rows = await searchItems(raw);
          renderItemResults(tr, rows);
          if(rows.length === 1){
            code.value = rows[0].code || raw.toUpperCase();
            await autofillItemRow(code, name, cost);
            hideItemResults(tr);
          }
        }
      }, 180);
    });

    code.addEventListener('focus', async()=>{
      if(code.value.trim() === ''){
        renderItemResults(tr, await searchItems(''));
      }
    });
    name.addEventListener('input', ()=>{
      clearTimeout(itemFetchTimer);
      const raw = name.value.trim();
      if(raw === ''){
        hideItemResults(tr);
        return;
      }
      itemFetchTimer = setTimeout(async()=>{
        renderItemResults(tr, await searchItems(raw));
      }, 180);
    });
    name.addEventListener('keydown', async e=>{
      if(e.key === 'Enter'){
        e.preventDefault();
        const rows = await searchItems(name.value.trim());
        if(rows.length === 1){
          applyItemToRow(tr, rows[0]);
        } else {
          renderItemResults(tr, rows);
        }
        focusNextField(name);
      }
    });
    code.addEventListener('blur', ()=>setTimeout(()=>{ autofillItemRow(code, name, cost); hideItemResults(tr); }, 140));
    code.addEventListener('keydown', e=>{
      if(e.key === 'Enter'){
        e.preventDefault();
        commitItemCode(tr);
      }
      if(e.key === 'Escape'){
        hideItemResults(tr);
      }
    });
    tr.querySelectorAll('input').forEach(inp => {
      if(inp === code || inp === name){
        return;
      }
      inp.addEventListener('keydown', e => {
        if(e.key === 'Enter'){
          e.preventDefault();
          focusNextField(inp);
        }
      });
    });
  });
}
function rowsData(){ return [...document.querySelectorAll('#tbody tr')].map(tr=>( { itemcode:tr.querySelector('.itemcode').value.trim().toUpperCase(), itemname:tr.querySelector('.itemname').value.trim(), qty:+tr.querySelector('.qty').value||0, weight:+tr.querySelector('.weight').value||0, stonewgt:+tr.querySelector('.stonewgt').value||0, netwgt:+tr.querySelector('.netwgt').value||0, complaint:tr.querySelector('.complaint').value.trim(), cost:+tr.querySelector('.cost').value||0, purity:tr.querySelector('.purity').value.trim(), stktype:tr.querySelector('.stktype').value.trim() })); }
async function api(url){ const r=await fetch(url); return await r.json(); }
async function post(url,data){ const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},body:JSON.stringify(data)}); return await r.json(); }
function fmtDate(v){
  if(!v) return '';
  const parts = String(v).slice(0,10).split('-');
  return parts.length === 3 ? `${parts[2]}-${parts[1]}-${parts[0]}` : v;
}
function line(label, value){
  return `<div class="pm-line"><b>${escapeHtml(label)}</b><span>:</span><span>${escapeHtml(value || '')}</span></div>`;
}
function buildPrintMemo(){
  const rows = rowsData().filter(r => r.itemcode || r.itemname || r.qty || r.weight || r.complaint);
  const totals = rows.reduce((out, r) => {
    out.qty += Number(r.qty || 0);
    out.weight += Number(r.weight || 0);
    out.stonewgt += Number(r.stonewgt || 0);
    out.netwgt += Number(r.netwgt || 0);
    return out;
  }, { qty: 0, weight: 0, stonewgt: 0, netwgt: 0 });
  const itemRows = (rows.length ? rows : [{}]).map((r, i) => `
    <tr>
      <td class="pm-num">${rows.length ? i + 1 : ''}</td>
      <td>${escapeHtml(r.itemname || r.itemcode || '')}</td>
      <td class="pm-num">${Number(r.qty || 0)}</td>
      <td class="pm-num">${Number(r.weight || 0).toFixed(3)}</td>
      <td class="pm-num">${Number(r.stonewgt || 0).toFixed(3)}</td>
      <td class="pm-num">0.00</td>
      <td class="pm-num">${Number(r.netwgt || 0).toFixed(3)}</td>
      <td>${escapeHtml(r.complaint || '')}</td>
    </tr>
  `).join('');
  const shopName = SHOP_INFO.name || 'SALLENA GOLD AND DIAMONDS LLP';
  const shopMeta = [
    SHOP_INFO.phone ? `Ph: ${SHOP_INFO.phone}` : '',
    SHOP_INFO.mobile ? `Mobile: ${SHOP_INFO.mobile}` : '',
    SHOP_INFO.gstin ? `GSTIN: ${SHOP_INFO.gstin}` : ''
  ].filter(Boolean);
  const smanText = $('sman').selectedOptions?.[0]?.textContent?.trim() || $('sman').value || '';
  $('printMemo').innerHTML = `
    <div class="pm-head">
      <h2>${escapeHtml(shopName)}</h2>
      ${SHOP_INFO.address ? `<div class="pm-shop-line">${escapeHtml(SHOP_INFO.address)}</div>` : ''}
      ${shopMeta.length ? `<div class="pm-shop-line pm-shop-meta">${shopMeta.map(v => `<span>${escapeHtml(v)}</span>`).join('')}</div>` : ''}
      <div class="pm-title">Customer Repair Receipt</div>
    </div>
    <div class="pm-meta">
      <div>
        ${line('Vet.No', $('billNo').value || '')}
        ${line('Vch.Date', fmtDate($('tDate').value || ''))}
        ${line('DueDate', fmtDate($('dueDate').value || ''))}
        ${line('SalesmanName', smanText)}
      </div>
      <div>
        ${line('CustomerName', $('custName').value || '')}
        ${line('Address', $('addr').value || '')}
        ${line('PhoneNo', selectedCustomerPhone)}
      </div>
    </div>
    <table class="pm-table">
      <thead>
        <tr><th style="width:28px">SlNo</th><th>ItemName</th><th>Nos</th><th>Grwt</th><th>Stwt</th><th>Diawt</th><th>Netwt</th><th>Itemwise Remarks</th></tr>
      </thead>
      <tbody>
        ${itemRows}
        <tr class="pm-total"><td></td><td>Total</td><td class="pm-num">${Number(totals.qty || 0)}</td><td class="pm-num">${totals.weight.toFixed(3)}</td><td class="pm-num">${totals.stonewgt.toFixed(3)}</td><td class="pm-num">0.00</td><td class="pm-num">${totals.netwgt.toFixed(3)}</td><td></td></tr>
      </tbody>
    </table>
    <div class="pm-remarks"><div>Remarks :-</div><div>${escapeHtml($('noteFld').value || '')}</div></div>
    <div class="pm-sign">
      <div>Prepared by</div>
      <div>Checked by</div>
      <div>Authorized</div>
      <div>Received by</div>
    </div>
  `;
}
async function loadDoc(b){ const d=await api(withQuery(API_URLS.get, { bill_no: b })); if(!d.ok){msg(d.message||'Not found');return;} $('billNo').value=d.master.billno||''; $('tDate').value=(d.master.tdate||'').slice(0,10); $('dueDate').value=(d.master.duedate||'').slice(0,10); $('custCode').value=d.master.custcode||''; $('custName').value=d.master.custname||''; $('addr').value=d.master.addr||''; $('sman').value=d.master.sman||''; $('refBill').value=d.master.refbill||''; $('saleType').value=d.master.sale_type||'CASH'; $('recvAmt').value=d.master.recvamt||0; if(d.master.cbcode){ try{ $('cbCode').value=d.master.cbcode; }catch(_){} } syncSaleType(); $('noteFld').value=d.master.note||''; $('tbody').innerHTML=''; (d.rows||[]).forEach(addRow); if((d.rows||[]).length===0)addRow(); window.__slno=d.master.slno||0; loadedExistingDoc = true; msg('Loaded existing document. Save will update memo only.'); }
async function navigateDoc(direction){
  msg('');
  const d = await api(withQuery(API_URLS.nav, {
    direction,
    slno: window.__slno || 0,
    bill_no: $('billNo').value.trim()
  }));
  if(!d.ok || !d.bill_no){
    msg(d.message || 'No more records');
    return;
  }
  await loadDoc(d.bill_no);
}
function setCustomerOptions(rows){
  customerRows = Array.isArray(rows) ? rows : [];
  customerHighlight = customerRows.length ? 0 : -1;
  renderCustomerResults();
}
function escapeHtml(v){ return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function renderCustomerResults(){
  const box = $('custResults');
  if(!customerRows.length){
    box.classList.remove('show');
    box.innerHTML = '';
    return;
  }
  box.innerHTML = customerRows.map((r,i) => `
    <div class="customerOption${i===customerHighlight?' active':''}" data-idx="${i}">
      <div><span class="customerCode">${escapeHtml(r.code||'')}</span> - ${escapeHtml(r.name||'')}</div>
      ${r.addr ? `<div class="customerMeta">${escapeHtml(r.addr)}</div>` : ''}
    </div>
  `).join('');
  box.classList.add('show');
  box.querySelectorAll('.customerOption').forEach(el => {
    el.onmousedown = e => {
      e.preventDefault();
      const idx = +el.dataset.idx;
      if (!Number.isNaN(idx) && customerRows[idx]) {
        applyCustomer(customerRows[idx]);
        hideCustomerResults();
      }
    };
  });
}
function hideCustomerResults(){
  customerHighlight = -1;
  $('custResults').classList.remove('show');
}
function applyCustomer(customer){
  if(!customer) return;
  $('custCode').value = customer.code || '';
  $('custName').value = customer.name || '';
  $('addr').value = customer.addr || '';
  selectedCustomerPhone = customer.phone || '';
  msg('');
}
async function searchCustomers(term){
  const q = String(term || '').trim();
  const d = await api(withQuery(API_URLS.customers, { q }));
  const rows = Array.isArray(d.rows) ? d.rows : [];
  setCustomerOptions(rows);
  return rows;
}
async function autofillCustomerByCode(rawCode){
  const code = String(rawCode || '').trim().toUpperCase();
  if(!code){
    return false;
  }
  const d = await api(withQuery(API_URLS.customer, { code }));
  if(d.ok && d.customer){
    applyCustomer(d.customer);
    hideCustomerResults();
    return true;
  }
  return false;
}
function findCustomerMatch(rows, rawCode, rawName){
  const code = String(rawCode || '').trim().toUpperCase();
  const name = String(rawName || '').trim().toUpperCase();
  if(!Array.isArray(rows) || !rows.length){
    return null;
  }
  return rows.find(r => String(r.code || '').trim().toUpperCase() === code)
    || rows.find(r => String(r.name || '').trim().toUpperCase() === name)
    || (rows.length === 1 ? rows[0] : null);
}
async function resolveCustomer(){
  const rawCode = $('custCode').value.trim();
  const rawName = $('custName').value.trim();
  const raw = rawCode || rawName;
  if(!raw){
    $('custCode').value = '';
    $('custName').value = '';
    hideCustomerResults();
    return;
  }
  const code = rawCode.toUpperCase();
  let selected = null;
  if(code){
    selected = customerRows.find(r => String(r.code || '').toUpperCase() === code);
  }
  if(!selected && rawName){
    selected = customerRows.find(r => String(r.name || '').trim().toUpperCase() === rawName.toUpperCase());
  }
  if(!selected && code){
    const d = await api(withQuery(API_URLS.customer, { code }));
    if(d.ok && d.customer){
      applyCustomer(d.customer);
      hideCustomerResults();
      return true;
    }
  }
  if(!selected){
    const rows = await searchCustomers(raw);
    selected = rows.find(r => String(r.code || '').toUpperCase() === code)
      || rows.find(r => String(r.name || '').trim().toUpperCase() === rawName.toUpperCase())
      || (rows.length === 1 ? rows[0] : null);
  }
  if(!selected){
    msg('Select a valid customer');
    renderCustomerResults();
    return false;
  }
  applyCustomer(selected);
  hideCustomerResults();
  return true;
}
function openCustomerCreate(){
  const w = 1200, h = 760;
  const dualLeft = window.screenLeft ?? window.screenX ?? 0;
  const dualTop = window.screenTop ?? window.screenY ?? 0;
  const outerWidth = window.outerWidth || document.documentElement.clientWidth || screen.width || w;
  const outerHeight = window.outerHeight || document.documentElement.clientHeight || screen.height || h;
  const left = Math.round(dualLeft + Math.max((outerWidth - w) / 2, 0));
  const top = Math.round(dualTop + Math.max((outerHeight - h) / 2, 0));
  const popup = window.open('{{ url("/customer/add?type=C&popup=1") }}', 'goldapp_customer_create', `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=yes`);
  if (popup) popup.focus();
  else alert('Popup blocked. Please allow popups for this site.');
}
async function findCustomerClick(){
  msg('');
  const code = $('custCode').value.trim().toUpperCase();
  const name = $('custName').value.trim();
  if(code){
    const loaded = await autofillCustomerByCode(code);
    if(loaded){
      return;
    }
  }
  const rows = await searchCustomers(code || name || '');
  if(rows.length === 1){
    applyCustomer(rows[0]);
    hideCustomerResults();
    return;
  }
  $('custName').focus();
  renderCustomerResults();
  if(!rows.length){
    msg('No customer found');
  }
}
async function loadCashBanks(){
  try{
    const d=await api(API_URLS.cashBanks);
    if(!d || !Array.isArray(d.rows)) return;
    const sel=$('cbCode'); sel.innerHTML='';
    d.rows.forEach(r=>{
      const o=document.createElement('option');
      o.value=r.code||'';
      o.textContent=(r.code||'')+(r.name?(' - '+r.name):'');
      sel.appendChild(o);
    });
    if(!d.rows.length){ const o=document.createElement('option'); o.value='CASH'; o.textContent='CASH'; sel.appendChild(o); }
    const cash=d.rows.find(r=>String(r.type||'').toUpperCase()==='H')
            ||d.rows.find(r=>String(r.code||'').toUpperCase()==='CASH')
            ||d.rows[0];
    sel.value=cash?(cash.code||'CASH'):'CASH';
  }catch(_){}
}
function syncSaleType(){
  const credit = $('saleType').value === 'CREDIT';
  $('recvAmt').disabled = credit;
  $('cbCode').disabled = credit;
  if(credit){
    $('recvAmt').value = '0';
  }
}
async function loadIssueBill(){
  msg('');
  const ref=$('refBill').value.trim().toUpperCase();
  if(!ref){ msg('Enter Ref Issue Bill (e.g. RM2/00001)'); return; }
  try{
    const d=await api(withQuery(API_URLS.loadIssue, { doc_no: ref }));
    if(!d.ok){ msg(d.message||'Issue bill not found'); return; }
    if(d.master){
      if(d.master.custcode){ $('custCode').value=d.master.custcode; }
      if(d.master.custname){ $('custName').value=d.master.custname; }
      if(d.master.addr){ $('addr').value=d.master.addr; }
    }
    $('tbody').innerHTML='';
    (d.rows||[]).forEach(addRow);
    if(!(d.rows||[]).length) addRow();
    msg('Loaded '+(d.rows||[]).length+' items from '+ref);
  }catch(e){ msg('Failed to load: '+e.message); }
}
async function init(){ $('billNo').value=INITIAL_DOC_NO||''; try{ const d=await api(API_URLS.next); $('billNo').value=d.doc_no||$('billNo').value||INITIAL_DOC_NO||''; }catch(_){ $('billNo').value=$('billNo').value||INITIAL_DOC_NO||''; } $('tDate').value=new Date().toISOString().slice(0,10); addRow(); loadCashBanks(); syncSaleType(); if(MODE!=='new'){ $('billNo').readOnly=false; $('billNo').placeholder='Enter/Load bill no'; }
  if(MODE==='cancel'){ $('saveBtn').style.display='none'; $('printBtn').style.display='none'; }
  if(MODE==='reprint'){ $('saveBtn').style.display='none'; $('cancelBtn').style.display='none'; }
}

$('addRow').onclick=()=>addRow();
$('delRow').onclick=()=>{ const r=$('tbody').querySelector('tr:last-child'); if(r) r.remove(); if(!$('tbody').querySelector('tr')) addRow(); };
$('prevBtn').onclick=()=>navigateDoc('previous');
$('nextBtn').onclick=()=>navigateDoc('next');
$('btnLoadIssue').onclick=()=>loadIssueBill();
$('refBill').addEventListener('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); loadIssueBill(); }});
$('saleType').addEventListener('change', syncSaleType);
$('saveBtn').onclick=async()=>{
  msg('');
  if(!$('sman').value){
    msg('Select salesman');
    $('sman').focus();
    return;
  }
  const payload={ mode: (MODE==='edit'||loadedExistingDoc)?'edit':'new', slno: window.__slno||0, bill_no:$('billNo').value.trim(), tdate:$('tDate').value, duedate:$('dueDate').value, custcode:$('custCode').value.trim().toUpperCase(), custname:$('custName').value.trim(), sman:$('sman').value, addr:$('addr').value.trim(), refbill:$('refBill').value.trim().toUpperCase(), sale_type:$('saleType').value, recvamt:+$('recvAmt').value||0, cbcode:$('cbCode').value, note:$('noteFld').value.trim(), rows: rowsData() };
  const d=await post(API_URLS.save, payload);
  if(!d.ok){msg(d.message||'Save failed');return;}
  msg('Saved');
  $('billNo').value=d.bill_no||$('billNo').value;
  window.__slno=d.slno||window.__slno;
  buildPrintMemo();
  setTimeout(()=>window.print(), 150);
  if(MODE==='new'){
    setTimeout(async()=>{
      $('tbody').innerHTML='';
      addRow();
      const n=await api(API_URLS.next);
      $('billNo').value=n.doc_no||$('billNo').value;
      window.__slno = 0;
      loadedExistingDoc = false;
    }, 600);
  }
};
$('cancelBtn').onclick=async()=>{ const b=$('billNo').value.trim(); if(!b){msg('Enter bill no');return;} if(!confirm('Cancel this bill?'))return; const d=await post(API_URLS.cancel,{bill_no:b}); msg(d.message||''); if(d.ok){ $('tbody').innerHTML=''; addRow(); }};
$('printBtn').onclick=()=>{ buildPrintMemo(); window.print(); };
$('closeBtn').onclick=()=>{ if(window.parent&&window.parent!==window) window.parent.postMessage({type:'goldapp:close-module-frame'},'*'); };
$('btnHelp')?.addEventListener('click', async()=>{ $('helpModal').classList.add('show'); await doSearch(); });
$('closeHelp').onclick=()=>$('helpModal').classList.remove('show');
$('find').onclick=()=>doSearch();
async function doSearch(){ const d=await api(withQuery(API_URLS.search, { q: $('q').value || '' })); const b=$('helpRows'); b.innerHTML=''; (d.rows||[]).forEach(r=>{ const tr=document.createElement('tr'); tr.innerHTML=`<td>${r.billno}</td><td>${(r.tdate||'').slice(0,10)}</td><td>${r.custname||''}</td><td>${r.status||''}</td>`; tr.onclick=()=>{ $('helpModal').classList.remove('show'); loadDoc(r.billno); }; b.appendChild(tr);}); }
function bindCustomerFindButton(){
  const btn = $('btnCust');
  const run = e => {
    if(e){
      e.preventDefault();
      e.stopPropagation();
    }
    findCustomerClick();
  };
  btn.onclick = run;
  btn.onmousedown = run;
}
bindCustomerFindButton();
$('btnCustCreate').onclick=()=>openCustomerCreate();
$('custCode').addEventListener('input',e=>{
  clearTimeout(customerFetchTimer);
  $('custName').value = '';
  $('addr').value = '';
  const code = e.target.value.trim();
  if(code === ''){
    setCustomerOptions([]);
    return;
  }
  customerFetchTimer = setTimeout(async()=>{
    const matched = await autofillCustomerByCode(code);
    if(!matched){
      await searchCustomers(code);
    }
  },180);
});
$('custCode').addEventListener('blur',()=>setTimeout(()=>{ if(document.activeElement!==$('custName')) resolveCustomer(); },120));
$('custCode').addEventListener('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); $('btnCust').click(); }});
$('custName').addEventListener('input',e=>{
  $('custCode').value='';
  $('addr').value='';
  clearTimeout(customerFetchTimer);
  const name = e.target.value.trim();
  if(name === ''){
    setCustomerOptions([]);
    return;
  }
  customerFetchTimer=setTimeout(async()=>{
    const rows = await searchCustomers(name);
    const selected = findCustomerMatch(rows, '', name);
    if(selected){
      applyCustomer(selected);
      hideCustomerResults();
    }
  },180);
});
$('custName').addEventListener('focus',()=>{ if(customerRows.length) renderCustomerResults(); });
$('custName').addEventListener('blur',()=>setTimeout(()=>{ if(!$('custResults').matches(':hover')){ hideCustomerResults(); resolveCustomer(); } },140));
$('custName').addEventListener('keydown',e=>{
  if(e.key==='ArrowDown' && customerRows.length){
    e.preventDefault();
    customerHighlight = Math.min(customerRows.length - 1, customerHighlight + 1);
    renderCustomerResults();
    return;
  }
  if(e.key==='ArrowUp' && customerRows.length){
    e.preventDefault();
    customerHighlight = Math.max(0, customerHighlight - 1);
    renderCustomerResults();
    return;
  }
  if(e.key==='Enter'){
    e.preventDefault();
    if(customerRows.length && customerHighlight >= 0 && customerRows[customerHighlight]){
      applyCustomer(customerRows[customerHighlight]);
      hideCustomerResults();
    } else {
      $('btnCust').click();
    }
  }
  if(e.key==='Escape'){
    hideCustomerResults();
  }
});
$('billNo').addEventListener('keydown',e=>{ if(e.key==='Enter'&&MODE!=='new') loadDoc($('billNo').value.trim());});
window.addEventListener('message', async event => {
  const data = event && event.data ? event.data : null;
  if (!data || data.type !== 'goldapp:customer-created') return;
  if (data.code) {
    $('custCode').value = data.code;
    $('custName').value = data.name || '';
    hideCustomerResults();
    const loaded = await autofillCustomerByCode(data.code);
    if (!loaded && data.name) {
      applyCustomer({ code: data.code, name: data.name, addr: '' });
    }
  }
});
document.addEventListener('click', e => {
  if(e.target === $('btnCust') || e.target.closest?.('#btnCust')){
    e.preventDefault();
    e.stopPropagation();
    findCustomerClick();
    return;
  }
  if (!e.target.closest('.customerField') && e.target !== $('custCode') && e.target !== $('btnCust')) {
    hideCustomerResults();
  }
});
document.addEventListener('keydown',e=>{ if(e.key==='F1'){ e.preventDefault(); $('btnHelp')?.click(); } if(e.key==='F9'&&MODE!=='cancel'&&MODE!=='reprint'){ e.preventDefault(); $('saveBtn').click(); }});
init();
document.getElementById('tbody').addEventListener('keydown', e => {
  if(e.key !== 'Enter'){
    return;
  }
  const input = e.target;
  if(!(input instanceof HTMLInputElement)){
    return;
  }
  const tr = input.closest('tr');
  if(!tr){
    return;
  }
  e.preventDefault();
  e.stopImmediatePropagation();
  if(input.classList.contains('itemcode')){
    commitItemCode(tr);
    return;
  }
  if(input.classList.contains('itemname')){
    commitItemName(tr);
    return;
  }
  focusNextField(input);
}, true);
</script>
</body>
</html>
