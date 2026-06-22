<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Item Model Issued / Returned</title>
<style>
  :root{--bg:#c0c0c0;--panel:#d7d7d7;--field:#fff;--line:#7e7e7e;--ring:#c7ba82}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:720px;margin:12px auto;background:var(--panel);border:1px solid #8a8a8a;box-shadow:inset 0 0 0 1px #efefef}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dedede,#c9c9c9);font-weight:700}
  .frame{padding:14px}
  .fields{display:grid;grid-template-columns:110px 1fr;gap:8px 10px;align-items:center}
  .label{font-weight:700;text-align:right;padding-right:4px}
  input,select,button{font:inherit}
  input[type="text"],input[type="number"],input[type="date"],select{width:100%;height:32px;padding:4px 8px;border:1px solid var(--line);background:var(--field)}
  input[readonly]{background:#d8d8d8}
  input:focus,select:focus{outline:none;background:#fff5e8;border-color:#9b6a18;box-shadow:0 0 0 1px #ffd08a inset}
  .row-flex{display:flex;gap:6px;align-items:center}
  .row-flex input{flex:1}
  .lookup-btn{height:32px;width:36px;padding:0;border:1px solid var(--line);background:#efefef;font-weight:700;cursor:pointer}
  .suggest{position:relative}
  .suggest-list{display:none;position:absolute;left:0;right:0;top:100%;max-height:160px;overflow:auto;background:#fff;border:1px solid var(--line);z-index:20}
  .suggest-list div{padding:4px 8px;cursor:pointer}
  .suggest-list div:hover{background:#e9f1ff}
  .chk-row{display:flex;align-items:center;gap:8px;margin-bottom:10px}
  .chk-row label{font-weight:700;cursor:pointer}
  .chk-row input[type="checkbox"]{width:18px;height:18px}
  .actions-ring{margin:16px auto 0;max-width:360px;padding:14px 22px;border:4px solid #2748ff;border-radius:999px;background:var(--ring);display:flex;justify-content:center;gap:14px}
  .actions-ring button{min-width:80px;height:36px;border:1px solid #666;background:#f3f0de;font-weight:700;cursor:pointer}
  .actions-ring button:hover{background:#e8e4c8}
  .status{min-height:20px;margin-top:10px;font-weight:700;color:#9b0000;text-align:center}
  /* modal */
  .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;justify-content:center;align-items:center}
  .modal-bg.show{display:flex}
  .modal{background:#e8e8e8;border:2px solid #666;width:520px;max-width:96vw;max-height:70vh;display:flex;flex-direction:column}
  .modal-head{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:linear-gradient(#dedede,#c0c0c0);border-bottom:1px solid #999;font-weight:700}
  .modal-head button{height:28px;padding:0 10px;font-weight:700;cursor:pointer}
  .modal-search{padding:8px 12px}
  .modal-search input{width:100%;height:28px;padding:4px 8px;border:1px solid var(--line)}
  .modal-body{overflow:auto;flex:1;padding:0 12px 12px}
  .modal-body table{width:100%;border-collapse:collapse;font-size:12px}
  .modal-body th,.modal-body td{border:1px solid #bbb;padding:4px 6px;text-align:left}
  .modal-body th{position:sticky;top:0;background:#d0d0d0;z-index:2}
  .modal-body tbody tr{cursor:pointer}
  .modal-body tbody tr:hover{background:#e0eaff}
</style>
<link rel="stylesheet" href="{{ asset('css/transaction-readable.css') }}?v={{ @filemtime(public_path('css/transaction-readable.css')) }}">
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div id="titleLabel">Item Model Issued / Returned</div>
    <button type="button" id="btnClose">Close</button>
  </div>
  <div class="frame">
    <!-- Return checkbox -->
    <div class="chk-row">
      <input type="checkbox" id="chkReturn">
      <label for="chkReturn">Return</label>
      <span style="margin-left:12px;font-weight:700">Ret. No. :</span>
      <input type="text" id="retSlno" readonly style="width:100px;height:28px;background:#d8d8d8;border:1px solid #999;padding:2px 6px">
    </div>

    <div class="fields">
      <div class="label">Barcode :</div>
      <input type="text" id="bcode" autocomplete="off" placeholder="Scan or enter barcode">

      <div class="label">SM Name :</div>
      <div class="suggest">
        <input type="text" id="smcode" autocomplete="off">
        <div class="suggest-list" id="smanList"></div>
      </div>

      <div class="label">Date :</div>
      <input type="date" id="tdate">

      <div class="label">Party :</div>
      <div class="row-flex">
        <input type="text" id="pcode" autocomplete="off" placeholder="Code" style="flex:0 0 120px">
        <button type="button" class="lookup-btn" id="btnPartyHelp">^</button>
        <input type="text" id="pname" placeholder="Name">
      </div>

      <div class="label">Item Code :</div>
      <div class="row-flex">
        <input type="text" id="icode" autocomplete="off" style="flex:0 0 160px">
        <button type="button" class="lookup-btn" id="btnItemHelp">^</button>
        <input type="text" id="iname" readonly>
      </div>

      <div class="label">Qty :</div>
      <input type="number" id="qty" min="0" value="0" style="width:160px">

      <div class="label">Weight :</div>
      <input type="number" id="weight" step="0.001" value="0" style="width:160px">

      <div class="label">St.Wgt :</div>
      <input type="number" id="stwgt" step="0.001" value="0" style="width:160px">

      <div class="label">Stk Type :</div>
      <select id="stktype" style="width:200px"><option value="">--</option></select>

      <div class="label">Note :</div>
      <input type="text" id="note" maxlength="30">
    </div>

    <div class="actions-ring">
      <button type="button" id="btnSave">Save</button>
      <button type="button" id="btnExit">Exit</button>
    </div>
    <div class="status" id="statusText"></div>
  </div>
</div>

<!-- Party search modal -->
<div class="modal-bg" id="partyModal">
  <div class="modal">
    <div class="modal-head"><span>Party Search</span><button type="button" id="partyModalClose">X</button></div>
    <div class="modal-search"><input type="text" id="partyModalSearch" placeholder="Search code or name..."></div>
    <div class="modal-body"><table><thead><tr><th>Code</th><th>Name</th></tr></thead><tbody id="partyModalRows"></tbody></table></div>
  </div>
</div>

<!-- Item search modal -->
<div class="modal-bg" id="itemModal">
  <div class="modal">
    <div class="modal-head"><span>Item Search</span><button type="button" id="itemModalClose">X</button></div>
    <div class="modal-search"><input type="text" id="itemModalSearch" placeholder="Search code or name..."></div>
    <div class="modal-body"><table><thead><tr><th>Code</th><th>Name</th></tr></thead><tbody id="itemModalRows"></tbody></table></div>
  </div>
</div>

<!-- Return picker modal -->
<div class="modal-bg" id="returnModal">
  <div class="modal">
    <div class="modal-head"><span>Select Pending Record</span><button type="button" id="returnModalClose">X</button></div>
    <div class="modal-search"><input type="text" id="returnModalSearch" placeholder="Search..."></div>
    <div class="modal-body"><table><thead><tr><th>Slno</th><th>Date</th><th>Item</th><th>Party</th><th>Qty</th><th>Wgt</th></tr></thead><tbody id="returnModalRows"></tbody></table></div>
  </div>
</div>

<script>
const API = @json(url('/api/stock-suspense-entry'));
const params = new URLSearchParams(window.location.search);
const GR = (params.get('gr') || 'G').toUpperCase(); // G=Issue, R=Receipt
let initData = {};
let retSlno = 0;

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function esc(v){ return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function setStatus(msg='',ok=false){ $('statusText').textContent=msg; $('statusText').style.color=ok?'#0b5a18':'#9b0000'; }
function closeFrame(){ window.parent.postMessage({type:'goldapp:close-module-frame'},'*'); }

async function api(payload){
  const res = await fetch(API,{method:'POST',credentials:'same-origin',
    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest'},
    body:JSON.stringify(payload)});
  const data = await res.json();
  if(!res.ok && !data.success) throw new Error(data.error||'Request failed');
  return data;
}

// Title
if(GR==='R') $('titleLabel').textContent='Item Model Receipt / Returned';
document.title = $('titleLabel').textContent;

// ── Salesman suggest ──
function setupSmanSuggest(){
  const inp=$('smcode'), list=$('smanList'), all=initData.salesmen||[];
  inp.addEventListener('input',()=>{
    const v=inp.value.trim().toUpperCase();
    const f=v===''?all:all.filter(s=>(s.code||'').toUpperCase().includes(v)||(s.name||'').toUpperCase().includes(v));
    list.innerHTML='';
    f.forEach(s=>{const d=document.createElement('div');d.textContent=s.code+' - '+s.name;d.addEventListener('click',()=>{inp.value=s.code;list.style.display='none';});list.appendChild(d);});
    list.style.display=f.length?'block':'none';
  });
  inp.addEventListener('blur',()=>setTimeout(()=>list.style.display='none',200));
}

// ── Stock types ──
function populateStkTypes(){
  const sel=$('stktype');
  (initData.stkTypes||[]).forEach(s=>{
    const o=document.createElement('option'); o.value=s.code; o.textContent=s.code+' - '+s.name; sel.appendChild(o);
  });
}

// ── Barcode ──
$('bcode').addEventListener('keydown', e=>{
  if(e.key==='Enter'){ e.preventDefault(); loadBarcode(); }
});
$('bcode').addEventListener('blur', loadBarcode);

async function loadBarcode(){
  const bc=parseInt($('bcode').value)||0;
  if(bc<=0) return;
  try{
    const d=await api({action:'barcode_load',bcode:bc});
    if(d.success && d.barcode){
      const b=d.barcode;
      if(b.stk==='N' && !$('chkReturn').checked){
        setStatus('Stock does not exist for this barcode'); return;
      }
      $('icode').value=b.icode; $('iname').value=b.iname;
      $('qty').value=b.qty; $('weight').value=b.weight.toFixed(3);
      $('stwgt').value=b.stweight.toFixed(3);
      if(b.defstktype) $('stktype').value=b.defstktype;
    }
  }catch(ex){ setStatus(ex.message); }
}

// ── Item code blur ──
$('icode').addEventListener('blur', async()=>{
  const code=$('icode').value.trim().toUpperCase();
  $('icode').value=code;
  if(!code){$('iname').value='';return;}
  try{
    const d=await api({action:'item_load',code});
    if(d.success&&d.item){
      $('iname').value=d.item.name;
      if(d.item.defstktype) $('stktype').value=d.item.defstktype;
    } else { $('iname').value=''; setStatus(d.error||'Item not found'); }
  }catch(ex){$('iname').value='';setStatus(ex.message);}
});

// ── Party modal ──
$('btnPartyHelp').addEventListener('click',()=>{$('partyModal').classList.add('show');$('partyModalSearch').value='';$('partyModalSearch').focus();});
$('partyModalClose').addEventListener('click',()=>$('partyModal').classList.remove('show'));
let partyTimer;
$('partyModalSearch').addEventListener('input',()=>{clearTimeout(partyTimer);partyTimer=setTimeout(()=>loadPartyList($('partyModalSearch').value.trim()),300);});

async function loadPartyList(search){
  if(!search){$('partyModalRows').innerHTML='';return;}
  try{
    const d=await api({action:'client_search',search});
    const body=$('partyModalRows'); body.innerHTML='';
    (d.data||[]).forEach(r=>{
      const tr=document.createElement('tr');
      tr.innerHTML='<td>'+esc(r.code)+'</td><td>'+esc(r.name)+'</td>';
      tr.addEventListener('dblclick',()=>{$('pcode').value=r.code;$('pname').value=r.name;$('partyModal').classList.remove('show');});
      body.appendChild(tr);
    });
  }catch(e){}
}

// ── Item modal ──
$('btnItemHelp').addEventListener('click',()=>{$('itemModal').classList.add('show');$('itemModalSearch').value='';$('itemModalSearch').focus();});
$('itemModalClose').addEventListener('click',()=>$('itemModal').classList.remove('show'));
let itemTimer;
$('itemModalSearch').addEventListener('input',()=>{clearTimeout(itemTimer);itemTimer=setTimeout(()=>loadItemList($('itemModalSearch').value.trim()),300);});

async function loadItemList(search){
  if(!search){$('itemModalRows').innerHTML='';return;}
  try{
    const d=await api({action:'item_search',search});
    const body=$('itemModalRows'); body.innerHTML='';
    (d.data||[]).forEach(r=>{
      const tr=document.createElement('tr');
      tr.innerHTML='<td>'+esc(r.code)+'</td><td>'+esc(r.name)+'</td>';
      tr.addEventListener('dblclick',()=>{$('icode').value=r.code;$('iname').value=r.name;$('itemModal').classList.remove('show');$('icode').dispatchEvent(new Event('blur'));});
      body.appendChild(tr);
    });
  }catch(e){}
}

// ── Return checkbox → open pending picker ──
$('chkReturn').addEventListener('change',async()=>{
  if($('chkReturn').checked){
    $('returnModal').classList.add('show');
    $('returnModalSearch').value='';
    $('returnModalSearch').focus();
    await loadReturnList('');

    $('icode').readOnly=true; $('bcode').readOnly=true;
    $('qty').readOnly=true; $('weight').readOnly=true; $('stwgt').readOnly=true;
  } else {
    retSlno=0; $('retSlno').value='';
    $('icode').readOnly=false; $('bcode').readOnly=false;
    $('qty').readOnly=false; $('weight').readOnly=false; $('stwgt').readOnly=false;
  }
});

$('returnModalClose').addEventListener('click',()=>{$('returnModal').classList.remove('show');if(!retSlno){$('chkReturn').checked=false;$('icode').readOnly=false;$('bcode').readOnly=false;$('qty').readOnly=false;$('weight').readOnly=false;$('stwgt').readOnly=false;}});
let retTimer;
$('returnModalSearch').addEventListener('input',()=>{clearTimeout(retTimer);retTimer=setTimeout(()=>loadReturnList($('returnModalSearch').value.trim()),300);});

async function loadReturnList(search){
  try{
    const d=await api({action:'list',pend:'Y',search});
    const body=$('returnModalRows'); body.innerHTML='';
    (d.data||[]).forEach(r=>{
      const tr=document.createElement('tr');
      tr.innerHTML='<td>'+r.slno+'</td><td>'+esc(r.tdate)+'</td><td>'+esc(r.icode)+'</td><td>'+esc(r.pname)+'</td><td>'+r.qty+'</td><td>'+r.weight.toFixed(3)+'</td>';
      tr.addEventListener('dblclick',()=>selectReturn(r.slno));
      body.appendChild(tr);
    });
  }catch(e){}
}

async function selectReturn(slno){
  try{
    const d=await api({action:'load',slno});
    if(!d.success){setStatus(d.error||'Not found');return;}
    const rec=d.record;
    retSlno=rec.slno;
    $('retSlno').value=rec.slno;
    $('pcode').value=rec.pcode; $('pname').value=rec.pname;
    $('icode').value=rec.icode; $('iname').value=rec.iname;
    $('bcode').value=rec.bcode||'';
    $('qty').value=rec.qty; $('weight').value=rec.weight.toFixed(3);
    $('stwgt').value=rec.stwgt.toFixed(3);
    if(rec.stktype) $('stktype').value=rec.stktype;
    if(rec.pend==='N') setStatus('Warning: This is not a pending issue');
    $('returnModal').classList.remove('show');
    $('smcode').focus();
  }catch(e){setStatus(e.message);}
}

// ── Save ──
$('btnSave').addEventListener('click',async()=>{
  const icode=$('icode').value.trim().toUpperCase();
  if(!icode){setStatus('Item code is required');$('icode').focus();return;}
  const qty=parseInt($('qty').value)||0;
  const wgt=parseFloat($('weight').value)||0;
  if(qty<=0 && wgt<=0){setStatus('Enter qty or weight');return;}

  const isReturn=$('chkReturn').checked;
  const label=GR==='G'?(isReturn?'Return':'Issue'):(isReturn?'Issue':'Receipt');
  if(!confirm('Save Model '+label+'?')) return;

  try{
    setStatus('Saving...');
    const d=await api({
      action:'save', gr:GR, is_return:isReturn,
      tdate:$('tdate').value,
      smcode:$('smcode').value.trim().toUpperCase(),
      pcode:$('pcode').value.trim().toUpperCase(),
      pname:$('pname').value.trim(),
      icode:icode,
      bcode:parseInt($('bcode').value)||0,
      qty:qty, weight:wgt,
      stwgt:parseFloat($('stwgt').value)||0,
      stktype:$('stktype').value,
      note:$('note').value.trim(),
      ret_slno:retSlno
    });
    setStatus(d.message||'Saved',d.success);
    if(d.success) resetForm();
  }catch(e){setStatus('Save error: '+e.message);}
});

function resetForm(){
  $('bcode').value=''; $('icode').value=''; $('iname').value='';
  $('pcode').value=''; $('pname').value='';
  $('qty').value='0'; $('weight').value='0'; $('stwgt').value='0';
  $('note').value=''; $('retSlno').value='';
  $('chkReturn').checked=false; retSlno=0;
  $('icode').readOnly=false; $('bcode').readOnly=false;
  $('qty').readOnly=false; $('weight').readOnly=false; $('stwgt').readOnly=false;
  $('smcode').focus();
}

// ── Exit / Close ──
$('btnExit').addEventListener('click',closeFrame);
$('btnClose').addEventListener('click',closeFrame);

// ── F1 item help, F9 save ──
document.addEventListener('keydown',e=>{
  if(e.key==='F1' && document.activeElement===$('icode')){e.preventDefault();$('btnItemHelp').click();}
  if(e.key==='F9'){e.preventDefault();$('btnSave').click();}
  if(e.key==='Escape'){
    if($('partyModal').classList.contains('show')) $('partyModal').classList.remove('show');
    else if($('itemModal').classList.contains('show')) $('itemModal').classList.remove('show');
    else if($('returnModal').classList.contains('show')){$('returnModal').classList.remove('show');if(!retSlno){$('chkReturn').checked=false;$('icode').readOnly=false;$('bcode').readOnly=false;$('qty').readOnly=false;$('weight').readOnly=false;$('stwgt').readOnly=false;}}
  }
});

// ── Init ──
async function init(){
  try{
    const d=await api({action:'init'});
    initData=d;
    $('tdate').value=d.today||'';
    setupSmanSuggest();
    populateStkTypes();
    setStatus('Ready',true);
    $('smcode').focus();
  }catch(e){setStatus('Init failed: '+e.message);}
}
init();
</script>
</body>
</html>
