<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#ece7d6;color:#1f2937}
.wrap{padding:8px}
.bar{background:#2a240b;color:#ffd966;padding:8px 10px;font-weight:700}
.grid{display:grid;grid-template-columns:120px 230px 120px 1fr 120px 220px;gap:6px;align-items:center;background:#efe9d6;border:1px solid #ccb98a;padding:8px}
.grid input,.grid select{height:30px;border:1px solid #c8b48a;border-radius:4px;padding:0 8px}
.btnGroup{display:flex;gap:6px}
.btnGroup .btn{flex:1}
.readonly{background:#f7f3e6}
.customerField{position:relative}
.customerInput{width:100%;box-sizing:border-box}
.customerResults{position:absolute;top:32px;left:0;right:0;z-index:30;background:#fffdf7;border:1px solid #c8b48a;border-radius:4px;box-shadow:0 6px 16px rgba(0,0,0,.12);max-height:220px;overflow:auto;display:none}
.customerResults.show{display:block}
.customerOption{padding:7px 10px;border-bottom:1px solid #eee2c4;cursor:pointer;line-height:1.25}
.customerOption:last-child{border-bottom:0}
.customerOption:hover,.customerOption.active{background:#f7edd1}
.customerCode{font-weight:700;color:#5f4a16}
.customerMeta{color:#5b6472;font-size:12px}
.btn{height:32px;padding:0 12px;border:1px solid #b9a171;background:#f1e6c9;border-radius:4px;cursor:pointer;font-weight:700}
.tbl{width:100%;border-collapse:collapse;margin-top:8px;background:#fff}
.tbl th,.tbl td{border:1px solid #ccc;padding:4px;font-size:12px}
.tbl th{background:#2a240b;color:#ffd966}
.tbl input{width:100%;height:28px;border:0;padding:0 6px;box-sizing:border-box}
.rowBtns{margin-top:6px;display:flex;gap:6px}
.footer{margin-top:10px;display:flex;gap:8px}
.msg{margin-left:10px;color:#b91c1c;font-weight:700;white-space:pre-line}
.modal{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center}
.modal.show{display:flex}
.panel{width:min(900px,95vw);max-height:80vh;overflow:auto;background:#fff;border-radius:6px}
.panel h4{margin:0;padding:8px 10px;background:#2a240b;color:#ffd966}
.panel .pbody{padding:10px}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wrap">
  <div class="bar">{{ $title }} @if($mode!=='new') - {{ strtoupper($mode) }} @endif</div>

  <div class="grid">
    <label>Doc No</label>
    <input id="billNo" readonly>
    <button class="btn" id="btnHelp">Help (F1)</button>
    <div></div>
    <label>Date</label>
    <input id="tDate" type="date">

    <label>Customer</label>
    <input id="custCode" placeholder="Code" autocomplete="off">
    <div class="btnGroup">
      <button class="btn" id="btnCust" type="button">Find</button>
      <button class="btn" id="btnCustCreate" type="button">+ Customer</button>
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

  <table class="tbl" id="tbl">
    <thead><tr>
      <th style="width:120px">Item Code</th><th>Item Name</th><th style="width:70px">Qty</th><th style="width:90px">Weight</th><th style="width:90px">StoneWgt</th><th style="width:90px">NetWgt</th><th>Complaint</th><th style="width:90px">Cost</th><th style="width:90px">Purity</th><th style="width:90px">StkType</th>
    </tr></thead>
    <tbody id="tbody"></tbody>
  </table>
  <div class="rowBtns">
    <button class="btn" id="addRow">+ Add</button>
    <button class="btn" id="delRow">- Delete</button>
  </div>

  <div class="footer">
    <button class="btn" id="saveBtn">Save (F9)</button>
    <button class="btn" id="cancelBtn">Cancel Bill</button>
    <button class="btn" id="printBtn">Print</button>
    <button class="btn" id="closeBtn">Close</button>
    <div class="msg" id="msg"></div>
  </div>
</div>
<datalist id="itemOptions"></datalist>

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
const $ = id => document.getElementById(id);
const msg = t => $('msg').textContent = t || '';
let customerFetchTimer = null;
let customerRows = [];
let customerHighlight = -1;

function rowHtml(r={}){
  return `<tr>
    <td><input class="itemcode" list="itemOptions" autocomplete="off" value="${r.itemcode||''}"></td>
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
  if(q === ''){
    itemRows = [];
    $('itemOptions').innerHTML = '';
    return [];
  }
  const d = await api('/api/remake-rcpt-memo-to-party/items?q=' + encodeURIComponent(q));
  itemRows = Array.isArray(d.rows) ? d.rows : [];
  $('itemOptions').innerHTML = itemRows.map(r =>
    `<option value="${escapeHtml(r.code || '')}">${escapeHtml(r.name || '')}</option>`
  ).join('');
  return itemRows;
}
async function autofillItemRow(codeInput, nameInput, costInput){
  const code = codeInput.value.trim().toUpperCase();
  if(!code){
    return false;
  }
  const d = await api('/api/remake-rcpt-memo-to-party/item?code='+encodeURIComponent(code));
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
        return;
      }
      itemFetchTimer = setTimeout(async()=>{
        const matched = await autofillItemRow(code, name, cost);
        if(!matched){
          const rows = await searchItems(raw);
          if(rows.length === 1){
            code.value = rows[0].code || raw.toUpperCase();
            await autofillItemRow(code, name, cost);
          }
        }
      }, 180);
    });

    code.addEventListener('blur', ()=>autofillItemRow(code, name, cost));
    code.addEventListener('keydown', e=>{
      if(e.key === 'Enter'){
        e.preventDefault();
        autofillItemRow(code, name, cost).then(()=>{
          name.focus();
        });
      }
    });
  });
}
function rowsData(){ return [...document.querySelectorAll('#tbody tr')].map(tr=>( { itemcode:tr.querySelector('.itemcode').value.trim().toUpperCase(), itemname:tr.querySelector('.itemname').value.trim(), qty:+tr.querySelector('.qty').value||0, weight:+tr.querySelector('.weight').value||0, stonewgt:+tr.querySelector('.stonewgt').value||0, netwgt:+tr.querySelector('.netwgt').value||0, complaint:tr.querySelector('.complaint').value.trim(), cost:+tr.querySelector('.cost').value||0, purity:tr.querySelector('.purity').value.trim(), stktype:tr.querySelector('.stktype').value.trim() })); }
async function api(url){ const r=await fetch(url); return await r.json(); }
async function post(url,data){ const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},body:JSON.stringify(data)}); return await r.json(); }
async function loadDoc(b){ const d=await api('/api/remake-rcpt-memo-to-party/get?bill_no='+encodeURIComponent(b)); if(!d.ok){msg(d.message||'Not found');return;} $('billNo').value=d.master.billno||''; $('tDate').value=(d.master.tdate||'').slice(0,10); $('dueDate').value=(d.master.duedate||'').slice(0,10); $('custCode').value=d.master.custcode||''; $('custName').value=d.master.custname||''; $('addr').value=d.master.addr||''; $('sman').value=d.master.sman||''; $('tbody').innerHTML=''; (d.rows||[]).forEach(addRow); if((d.rows||[]).length===0)addRow(); window.__slno=d.master.slno||0; }
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
  msg('');
}
async function searchCustomers(term){
  const q = String(term || '').trim();
  if(q === ''){
    setCustomerOptions([]);
    return [];
  }
  const d = await api('/api/remake-rcpt-memo-to-party/customers?q=' + encodeURIComponent(q));
  const rows = Array.isArray(d.rows) ? d.rows : [];
  setCustomerOptions(rows);
  return rows;
}
async function autofillCustomerByCode(rawCode){
  const code = String(rawCode || '').trim().toUpperCase();
  if(!code){
    return false;
  }
  const d = await api('/api/remake-rcpt-memo-to-party/customer?code=' + encodeURIComponent(code));
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
    const d = await api('/api/remake-rcpt-memo-to-party/customer?code=' + encodeURIComponent(code));
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
  const popup = window.open('{{ url("/customer?type=C&popup=1") }}', 'goldapp_customer_create', `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=yes`);
  if (popup) popup.focus();
}
async function init(){ $('billNo').value=INITIAL_DOC_NO||''; try{ const d=await api('/api/remake-rcpt-memo-to-party/next'); $('billNo').value=d.doc_no||$('billNo').value||INITIAL_DOC_NO||''; }catch(_){ $('billNo').value=$('billNo').value||INITIAL_DOC_NO||''; } $('tDate').value=new Date().toISOString().slice(0,10); addRow(); if(MODE!=='new'){ $('billNo').readOnly=false; $('billNo').placeholder='Enter/Load bill no'; }
  if(MODE==='cancel'){ $('saveBtn').style.display='none'; $('printBtn').style.display='none'; }
  if(MODE==='reprint'){ $('saveBtn').style.display='none'; $('cancelBtn').style.display='none'; }
}

$('addRow').onclick=()=>addRow();
$('delRow').onclick=()=>{ const r=$('tbody').querySelector('tr:last-child'); if(r) r.remove(); if(!$('tbody').querySelector('tr')) addRow(); };
$('saveBtn').onclick=async()=>{ msg(''); const payload={ mode: MODE==='edit'?'edit':'new', slno: window.__slno||0, bill_no:$('billNo').value.trim(), tdate:$('tDate').value, duedate:$('dueDate').value, custcode:$('custCode').value.trim().toUpperCase(), custname:$('custName').value.trim(), sman:$('sman').value, addr:$('addr').value.trim(), rows: rowsData() }; const d=await post('/api/remake-rcpt-memo-to-party/save', payload); if(!d.ok){msg(d.message||'Save failed');return;} msg('Saved'); $('billNo').value=d.bill_no||$('billNo').value; window.__slno=d.slno||window.__slno; if(MODE==='new'){ $('tbody').innerHTML=''; addRow(); const n=await api('/api/remake-rcpt-memo-to-party/next'); $('billNo').value=n.doc_no||$('billNo').value; }};
$('cancelBtn').onclick=async()=>{ const b=$('billNo').value.trim(); if(!b){msg('Enter bill no');return;} if(!confirm('Cancel this bill?'))return; const d=await post('/api/remake-rcpt-memo-to-party/cancel',{bill_no:b}); msg(d.message||''); if(d.ok){ $('tbody').innerHTML=''; addRow(); }};
$('printBtn').onclick=()=>window.print();
$('closeBtn').onclick=()=>{ if(window.parent&&window.parent!==window) window.parent.postMessage({type:'close-module'},'*'); };
$('btnHelp').onclick=async()=>{ $('helpModal').classList.add('show'); await doSearch(); };
$('closeHelp').onclick=()=>$('helpModal').classList.remove('show');
$('find').onclick=()=>doSearch();
async function doSearch(){ const d=await api('/api/remake-rcpt-memo-to-party/search?q='+encodeURIComponent($('q').value||'')); const b=$('helpRows'); b.innerHTML=''; (d.rows||[]).forEach(r=>{ const tr=document.createElement('tr'); tr.innerHTML=`<td>${r.billno}</td><td>${(r.tdate||'').slice(0,10)}</td><td>${r.custname||''}</td><td>${r.status||''}</td>`; tr.onclick=()=>{ $('helpModal').classList.remove('show'); loadDoc(r.billno); }; b.appendChild(tr);}); }
$('btnCust').onclick=()=>resolveCustomer();
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
    await resolveCustomer();
  }
});
document.addEventListener('click', e => {
  if (!e.target.closest('.customerField') && e.target !== $('custCode') && e.target !== $('btnCust')) {
    hideCustomerResults();
  }
});
document.addEventListener('keydown',e=>{ if(e.key==='F1'){ e.preventDefault(); $('btnHelp').click(); } if(e.key==='F9'&&MODE!=='cancel'&&MODE!=='reprint'){ e.preventDefault(); $('saveBtn').click(); }});
init();
</script>
</body>
</html>
