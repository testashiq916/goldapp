<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Ruff Work</title>
<style>
  :root{--bg:#c0c0c0;--panel:#d7d7d7;--field:#fff;--line:#7e7e7e;--ring:#c7ba82}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:1060px;margin:12px auto;background:var(--panel);border:1px solid #8a8a8a;box-shadow:inset 0 0 0 1px #efefef}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dedede,#c9c9c9);font-weight:700}
  .frame{padding:12px}
  .toolbar{display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap}
  .toolbar button{height:30px;padding:0 14px;border:1px solid #666;background:#efefef;font-weight:700;cursor:pointer}
  .toolbar button:hover{background:#e0e0e0}
  .toolbar select{height:30px;padding:2px 6px;border:1px solid var(--line)}
  .grid-wrap{border:1px solid #999;background:#fff;max-height:460px;overflow:auto}
  table{width:100%;border-collapse:collapse;font-size:12px}
  th,td{border:1px solid #ccc;padding:3px 4px;text-align:left}
  th{position:sticky;top:0;background:#c8c8c8;z-index:2;font-weight:700;white-space:nowrap}
  tbody tr:nth-child(even){background:#f4f4f4}
  tbody tr:hover{background:#e4eeff}
  tbody tr.selected{background:#c8d8ff}
  td input,td select{width:100%;height:24px;border:1px solid #bbb;padding:1px 4px;background:#fff;font:inherit;font-size:12px}
  td input:focus,td select:focus{background:#fff5e8;border-color:#9b6a18;outline:none}
  td input[type="date"]{width:120px}
  td input.narrow{width:60px}
  .actions-ring{margin:14px auto 0;max-width:460px;padding:14px 22px;border:4px solid #2748ff;border-radius:999px;background:var(--ring);display:flex;justify-content:center;gap:14px}
  .actions-ring button{min-width:80px;height:36px;border:1px solid #666;background:#f3f0de;font-weight:700;cursor:pointer}
  .actions-ring button:hover{background:#e8e4c8}
  .status{min-height:20px;margin-top:10px;font-weight:700;color:#9b0000;text-align:center}
  .chk{width:auto!important;height:auto!important}
  /* modals */
  .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;justify-content:center;align-items:center}
  .modal-bg.show{display:flex}
  .modal{background:#e8e8e8;border:2px solid #666;width:480px;max-width:96vw;max-height:60vh;display:flex;flex-direction:column}
  .modal-head{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:linear-gradient(#dedede,#c0c0c0);border-bottom:1px solid #999;font-weight:700}
  .modal-head button{height:28px;padding:0 10px;font-weight:700;cursor:pointer}
  .modal-search{padding:8px 12px}
  .modal-search input{width:100%;height:28px;padding:4px 8px;border:1px solid var(--line)}
  .modal-body{overflow:auto;flex:1;padding:0 12px 12px}
  .modal-body table{width:100%;border-collapse:collapse;font-size:12px}
  .modal-body th,.modal-body td{border:1px solid #bbb;padding:4px 6px}
  .modal-body th{position:sticky;top:0;background:#d0d0d0;z-index:2}
  .modal-body tbody tr{cursor:pointer}
  .modal-body tbody tr:hover{background:#e0eaff}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div>Ruff Work</div>
    <button type="button" id="btnClose">Close</button>
  </div>
  <div class="frame">
    <div class="toolbar">
      <button type="button" id="btnAdd">Add Row</button>
      <button type="button" id="btnDel">Delete Row</button>
      <label style="margin-left:12px">Filter:</label>
      <select id="filterSel">
        <option value="all">All</option>
        <option value="pending">Pending Only</option>
      </select>
      <button type="button" id="btnRefresh">Refresh</button>
    </div>

    <div class="grid-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:40px">Sl</th>
            <th style="width:130px">Party</th>
            <th style="width:105px">Date</th>
            <th style="width:110px">Item</th>
            <th style="width:60px">Qty</th>
            <th style="width:70px">Weight</th>
            <th style="width:80px">Amount</th>
            <th style="width:90px">Part</th>
            <th style="width:55px">In/Exp</th>
            <th style="width:90px">Salesman</th>
            <th style="width:90px">Person</th>
            <th style="width:35px" title="Pending">P</th>
          </tr>
        </thead>
        <tbody id="gridBody"></tbody>
      </table>
    </div>

    <div class="actions-ring">
      <button type="button" id="btnSave">Save</button>
      <button type="button" id="btnCancel">Cancel</button>
      <button type="button" id="btnPrint">Print</button>
      <button type="button" id="btnExit">Exit</button>
    </div>
    <div class="status" id="statusText"></div>
  </div>
</div>

<!-- Account search modal (F1 on party) -->
<div class="modal-bg" id="acctModal">
  <div class="modal">
    <div class="modal-head"><span>Account Search</span><button type="button" id="acctModalClose">X</button></div>
    <div class="modal-search"><input type="text" id="acctModalSearch" placeholder="Search code or name..."></div>
    <div class="modal-body"><table><thead><tr><th>Code</th><th>Name</th></tr></thead><tbody id="acctModalRows"></tbody></table></div>
  </div>
</div>

<!-- Item search modal (F1 on item) -->
<div class="modal-bg" id="itemModal">
  <div class="modal">
    <div class="modal-head"><span>Item Search</span><button type="button" id="itemModalClose">X</button></div>
    <div class="modal-search"><input type="text" id="itemModalSearch" placeholder="Search item code or name..."></div>
    <div class="modal-body"><table><thead><tr><th>Code</th><th>Name</th></tr></thead><tbody id="itemModalRows"></tbody></table></div>
  </div>
</div>

<script>
const API = @json(url('/api/ruff-work'));
let rows = []; // [{slno,party,tdate,item,qty,weight,amount,part,inexp,sman,person,pend,number}]
let selectedRow = -1;
let activeField = ''; // 'party' or 'item' for F1 modal target
let salesmen = [];

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function esc(v){ return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
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

function smanOptions(){
  return '<option value=""></option>'+salesmen.map(s=>'<option value="'+esc(s)+'">'+esc(s)+'</option>').join('');
}

function renderGrid(){
  const body=$('gridBody'); body.innerHTML='';
  const smOpts=smanOptions();
  rows.forEach((r,i)=>{
    const tr=document.createElement('tr');
    if(i===selectedRow) tr.classList.add('selected');
    tr.innerHTML=
      '<td style="text-align:center">'+(r.slno||'*')+'</td>'
      +'<td><input type="text" data-i="'+i+'" data-f="party" value="'+esc(r.party)+'" class="cell-inp"></td>'
      +'<td><input type="date" data-i="'+i+'" data-f="tdate" value="'+esc(r.tdate)+'" class="cell-inp"></td>'
      +'<td><input type="text" data-i="'+i+'" data-f="item" value="'+esc(r.item)+'" class="cell-inp"></td>'
      +'<td><input type="text" data-i="'+i+'" data-f="qty" value="'+esc(r.qty)+'" class="cell-inp"></td>'
      +'<td><input type="text" data-i="'+i+'" data-f="weight" value="'+esc(r.weight)+'" class="cell-inp"></td>'
      +'<td><input type="text" data-i="'+i+'" data-f="amount" value="'+esc(r.amount)+'" class="cell-inp"></td>'
      +'<td><input type="text" data-i="'+i+'" data-f="part" value="'+esc(r.part)+'" class="cell-inp"></td>'
      +'<td><select data-i="'+i+'" data-f="inexp" class="cell-inp"><option value="">--</option><option value="In"'+(r.inexp==='In'?' selected':'')+'>In</option><option value="Exp"'+(r.inexp==='Exp'?' selected':'')+'>Exp</option></select></td>'
      +'<td><select data-i="'+i+'" data-f="sman" class="cell-inp">'+smOpts.replace('value="'+esc(r.sman)+'"','value="'+esc(r.sman)+'" selected')+'</select></td>'
      +'<td><input type="text" data-i="'+i+'" data-f="person" value="'+esc(r.person)+'" class="cell-inp"></td>'
      +'<td style="text-align:center"><input type="checkbox" class="chk" data-i="'+i+'" data-f="pend" '+(r.pend?'checked':'')+'></td>';
    tr.addEventListener('click',()=>{ selectedRow=i; highlightRow(); });
    body.appendChild(tr);
  });

  // Bind change events
  body.querySelectorAll('.cell-inp').forEach(inp=>{
    inp.addEventListener('change',e=>{
      const i=+e.target.dataset.i, f=e.target.dataset.f;
      rows[i][f]=e.target.value;
    });
    inp.addEventListener('keydown',e=>{
      if(e.key==='F1'){
        e.preventDefault();
        const i=+e.target.dataset.i, f=e.target.dataset.f;
        selectedRow=i;
        if(f==='party'){ activeField='party'; openModal('acct'); }
        if(f==='item'){ activeField='item'; openModal('item'); }
      }
    });
  });
  body.querySelectorAll('.chk').forEach(cb=>{
    cb.addEventListener('change',e=>{
      const i=+e.target.dataset.i;
      rows[i].pend=e.target.checked?1:0;
    });
  });
}

function highlightRow(){
  const trs=$('gridBody').querySelectorAll('tr');
  trs.forEach((tr,i)=>{ tr.classList.toggle('selected',i===selectedRow); });
}

// Add row
$('btnAdd').addEventListener('click',()=>{
  rows.push({slno:0,party:'',tdate:new Date().toISOString().slice(0,10),item:'',qty:'',weight:'',amount:'',part:'',inexp:'',sman:'',person:'',pend:1,number:0});
  selectedRow=rows.length-1;
  renderGrid();
  // Focus party input of new row
  const inps=$('gridBody').querySelectorAll('input[data-f="party"]');
  if(inps.length) inps[inps.length-1].focus();
});

// Delete selected row
$('btnDel').addEventListener('click',async()=>{
  if(selectedRow<0||selectedRow>=rows.length){setStatus('Select a row first');return;}
  const r=rows[selectedRow];
  if(r.slno>0){
    try{ await api({action:'delete',slno:r.slno}); }catch(e){setStatus('Delete error: '+e.message);return;}
  }
  rows.splice(selectedRow,1);
  if(selectedRow>=rows.length) selectedRow=rows.length-1;
  renderGrid();
  setStatus('Row deleted',true);
});

// Save all
$('btnSave').addEventListener('click',async()=>{
  // Sync values from DOM before save
  syncFromDOM();
  try{
    setStatus('Saving...');
    const d=await api({action:'save',rows});
    setStatus(d.message||'Saved',d.success);
    loadList();
  }catch(e){setStatus('Save error: '+e.message);}
});

function syncFromDOM(){
  $('gridBody').querySelectorAll('.cell-inp').forEach(inp=>{
    const i=+inp.dataset.i, f=inp.dataset.f;
    if(i>=0&&i<rows.length) rows[i][f]=inp.value;
  });
  $('gridBody').querySelectorAll('.chk').forEach(cb=>{
    const i=+cb.dataset.i;
    if(i>=0&&i<rows.length) rows[i].pend=cb.checked?1:0;
  });
}

// Cancel (reload)
$('btnCancel').addEventListener('click',()=>{ loadList(); });

// Refresh
$('btnRefresh').addEventListener('click',()=>{ loadList(); });

// Filter change
$('filterSel').addEventListener('change',()=>{ loadList(); });

// Print
$('btnPrint').addEventListener('click',()=>{
  syncFromDOM();
  const validRows=rows.filter(r=>r.party!=='');
  if(validRows.length===0){setStatus('No data to print');return;}

  const pw=window.open('','_blank','width=900,height=600');
  pw.document.write('<html><head><title>Ruff Work</title>');
  pw.document.write('<style>body{font-family:Arial;font-size:12px;margin:20px}h2{text-align:center;margin:0 0 12px}table{width:100%;border-collapse:collapse}th,td{padding:4px 6px;border:1px solid #999;text-align:left}th{background:#ddd;font-weight:700}</style>');
  pw.document.write('
</head><body><h2>Ruff Work</h2>');
  pw.document.write('<table><tr><th>Sl</th><th>Party</th><th>Date</th><th>Item</th><th>Qty</th><th>Weight</th><th>Amount</th><th>Part</th><th>In/Exp</th><th>SM</th><th>Person</th><th>P</th></tr>');
  validRows.forEach((r,i)=>{
    pw.document.write('<tr><td>'+(i+1)+'</td><td>'+esc(r.party)+'</td><td>'+esc(r.tdate)+'</td><td>'+esc(r.item)+'</td><td>'+esc(r.qty)+'</td><td>'+esc(r.weight)+'</td><td>'+esc(r.amount)+'</td><td>'+esc(r.part)+'</td><td>'+esc(r.inexp)+'</td><td>'+esc(r.sman)+'</td><td>'+esc(r.person)+'</td><td>'+(r.pend?'Y':'N')+'</td></tr>');
  });
  pw.document.write('</table></body></html>');
  pw.document.close(); pw.focus(); pw.print();
});

// Exit / Close
$('btnExit').addEventListener('click',closeFrame);
$('btnClose').addEventListener('click',closeFrame);

// ── Modals ──

function openModal(type){
  $(type+'Modal').classList.add('show');
  $(type+'ModalSearch').value='';
  $(type+'ModalSearch').focus();
  $(type+'ModalRows').innerHTML='';
}

$('acctModalClose').addEventListener('click',()=>$('acctModal').classList.remove('show'));
$('itemModalClose').addEventListener('click',()=>$('itemModal').classList.remove('show'));

let acctTimer,itemTimer;
$('acctModalSearch').addEventListener('input',()=>{
  clearTimeout(acctTimer);
  acctTimer=setTimeout(()=>loadSearchModal('account_search',$('acctModalSearch').value.trim(),'acctModalRows','party'),300);
});
$('itemModalSearch').addEventListener('input',()=>{
  clearTimeout(itemTimer);
  itemTimer=setTimeout(()=>loadSearchModal('item_search',$('itemModalSearch').value.trim(),'itemModalRows','item'),300);
});

async function loadSearchModal(action,search,bodyId,field){
  if(!search){$(bodyId).innerHTML='';return;}
  try{
    const d=await api({action,search});
    const body=$(bodyId); body.innerHTML='';
    (d.data||[]).forEach(r=>{
      const tr=document.createElement('tr');
      tr.innerHTML='<td>'+esc(r.code)+'</td><td>'+esc(r.name)+'</td>';
      tr.addEventListener('dblclick',()=>{
        if(selectedRow>=0&&selectedRow<rows.length){
          rows[selectedRow][field]=r.name;
        }
        $('acctModal').classList.remove('show');
        $('itemModal').classList.remove('show');
        renderGrid();
      });
      body.appendChild(tr);
    });
  }catch(e){}
}

document.addEventListener('keydown',e=>{
  if(e.key==='Escape'){
    if($('acctModal').classList.contains('show')) $('acctModal').classList.remove('show');
    if($('itemModal').classList.contains('show')) $('itemModal').classList.remove('show');
  }
  if(e.key==='F9'){e.preventDefault();$('btnSave').click();}
});

// ── Load data ──

async function loadList(){
  try{
    setStatus('Loading...');
    const filter=$('filterSel').value;
    const d=await api({action:'list',filter});
    rows=d.data||[];
    selectedRow=rows.length>0?0:-1;
    renderGrid();
    setStatus(rows.length+' row(s)',true);
  }catch(e){setStatus('Load error: '+e.message);}
}

// Init
async function init(){
  try{
    const d=await api({action:'salesmen'});
    salesmen=d.data||[];
  }catch(e){}
  loadList();
}
init();
</script>
</body>
</html>
