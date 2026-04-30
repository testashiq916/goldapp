<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
  :root{--bg:#808080;--panel:#d0d0d0;--field:#fff;--line:#7e7e7e}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:520px;margin:20px auto;background:var(--panel);border:2px solid #aaa;box-shadow:4px 4px 8px rgba(0,0,0,.4)}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:7px 10px;background:linear-gradient(#5a7bb5,#4466a0);color:#fff;font-weight:700}
  .frame{padding:14px 18px}
  .row{display:grid;grid-template-columns:140px 1fr;align-items:center;gap:6px;margin-bottom:8px}
  .row label{font-weight:700;text-align:right;padding-right:8px}
  .row input,.row .suggest{height:30px;}
  .row input{padding:3px 8px;border:1px solid var(--line);background:var(--field);width:100%}
  .row input:focus{outline:none;background:#fff5e8;border-color:#9b6a18}
  .row input[readonly]{background:#e8e8e8;color:#333}
  .suggest{position:relative;width:100%}
  .suggest input{width:100%}
  .suggest-list{display:none;position:absolute;left:0;right:0;top:100%;max-height:160px;overflow:auto;background:#fff;border:1px solid var(--line);z-index:20}
  .suggest-list div{padding:4px 8px;cursor:pointer}
  .suggest-list div:hover{background:#e9f1ff}
  button{font:inherit;cursor:pointer}
  .btn{height:32px;padding:0 14px;border:1px solid #666;background:#efefef;font-weight:700}
  .btn:hover{background:#e0e0e0}
  .divider{border:none;border-top:1px solid #aaa;margin:10px 0}
  .actions{display:flex;justify-content:center;gap:14px;margin-top:12px}
  .actions button{min-width:90px;height:36px;border:1px solid #666;background:#f0eed8;font-weight:700}
  .actions button:hover{background:#e0dcc0}
  .status{min-height:18px;margin-top:8px;font-weight:700;color:#9b0000;text-align:center;font-size:12px}
</style>
</head>
<body>
<div class="window">
  <div class="titlebar">
    <span>Draw Process</span>
    <button type="button" id="btnClose" style="background:transparent;border:none;color:#fff;font-weight:700;cursor:pointer">✕</button>
  </div>
  <div class="frame">
    <div class="row">
      <label>Draw Date</label>
      <input type="date" id="tdate">
    </div>
    <div class="row">
      <label>Gold Rate</label>
      <input type="number" id="grate" step="0.01" placeholder="0.00">
    </div>
    <div class="row">
      <label>Party</label>
      <div class="suggest">
        <input type="text" id="partyCode" autocomplete="off" placeholder="Code / Name (F1 to search)">
        <div class="suggest-list" id="suggestList"></div>
      </div>
    </div>
    <div class="row">
      <label>Name</label>
      <input type="text" id="partyName" readonly>
    </div>
    <div class="row">
      <label>Address</label>
      <input type="text" id="partyAddr" readonly>
    </div>
    <hr class="divider">
    <div class="row">
      <label>Total Kuri Amt</label>
      <input type="number" id="totamt" readonly>
    </div>
    <div class="row">
      <label>Total Colln</label>
      <input type="number" id="tcolln" readonly>
    </div>
    <div class="row">
      <label>Balance</label>
      <input type="number" id="balance" readonly>
    </div>
    <div class="row">
      <label>Bonus</label>
      <input type="number" id="bonus" step="0.01" placeholder="0.00">
    </div>
    <div class="row">
      <label>Est. Weight</label>
      <input type="number" id="estwgt" readonly>
    </div>
    <div class="actions">
      <button type="button" id="btnOk">&amp;OK</button>
      <button type="button" id="btnExit">E&amp;xit</button>
    </div>
    <div class="status" id="statusText"></div>
  </div>
</div>

<script>
const API = @json(url('/api/kuri-finish'));
let currentCode = '';

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function setStatus(msg,ok){ $('statusText').textContent=msg||''; $('statusText').style.color=ok?'#0b5a18':'#9b0000'; }
function closeFrame(){ window.parent.postMessage({type:'goldapp:close-module-frame'},'*'); }

async function api(payload){
  const res=await fetch(API,{
    method:'POST',credentials:'same-origin',
    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest'},
    body:JSON.stringify(payload)
  });
  const d=await res.json();
  if(!res.ok&&!d.success) throw new Error(d.error||'Request failed');
  return d;
}

async function loadParty(code){
  if(!code){clearParty();return;}
  try{
    const d=await api({action:'party-info',code,tdate:$('tdate').value,grate:parseFloat($('grate').value)||0});
    if(!d.success){setStatus(d.error||'Not found');clearParty();return;}
    currentCode=d.code;
    $('partyCode').value=d.code;
    $('partyName').value=d.name;
    $('partyAddr').value=d.addr||'';
    $('totamt').value=parseFloat(d.totamt||0).toFixed(2);
    $('tcolln').value=parseFloat(d.tcolln||0).toFixed(2);
    $('balance').value=parseFloat(d.balance||0).toFixed(2);
    $('bonus').value=parseFloat(d.bonus||0).toFixed(2);
    $('estwgt').value=parseFloat(d.estwgt||0).toFixed(3);
    if(d.finished&&d.finished!=='N') setStatus('Warning: this party is already marked as finished ('+d.finished+')',false);
    else setStatus('');
  }catch(e){setStatus('Error: '+e.message);clearParty();}
}

function clearParty(){
  currentCode='';
  ['partyName','partyAddr','totamt','tcolln','balance','estwgt'].forEach(id=>$(id).value='');
  $('bonus').value='0.00';
}

// Suggest logic
const inp=$('partyCode');
const list=$('suggestList');
let searchTimer=null;

inp.addEventListener('input',()=>{
  clearTimeout(searchTimer);
  const v=inp.value.trim();
  if(v.length<1){list.style.display='none';return;}
  searchTimer=setTimeout(async()=>{
    const d=await api({action:'party-search',q:v}).catch(()=>null);
    if(!d)return;
    list.innerHTML='';
    (d.results||[]).forEach(r=>{
      const div=document.createElement('div');
      div.textContent=r.code+' - '+r.name;
      div.addEventListener('click',()=>{inp.value=r.code;list.style.display='none';loadParty(r.code);});
      list.appendChild(div);
    });
    list.style.display=(d.results||[]).length?'block':'none';
  },250);
});
inp.addEventListener('blur',()=>setTimeout(()=>list.style.display='none',200));
inp.addEventListener('keydown',e=>{
  if(e.key==='F1'){e.preventDefault(); const v=inp.value.trim();if(v)loadParty(v);}
  if(e.key==='Enter'){e.preventDefault(); const v=inp.value.trim().toUpperCase(); if(v)loadParty(v);}
});

$('grate').addEventListener('change',()=>{ if(currentCode) loadParty(currentCode); });

$('btnOk').addEventListener('click',async()=>{
  if(!currentCode){setStatus('Select a party first.');return;}
  const balance=parseFloat($('balance').value)||0;
  const bonus=parseFloat($('bonus').value)||0;
  const damt=balance+bonus;
  if(!confirm('Save draw for '+currentCode+'? Amount: '+damt.toFixed(2))) return;
  try{
    setStatus('Saving...');
    $('btnOk').disabled=true;
    const d=await api({action:'save-draw',tdate:$('tdate').value,code:currentCode,
      bonus,damt,grate:parseFloat($('grate').value)||0});
    setStatus(d.message||'Saved',d.success);
    if(d.success){clearParty();$('partyCode').value='';$('partyCode').focus();}
  }catch(e){setStatus('Save error: '+e.message);}
  finally{$('btnOk').disabled=false;}
});

$('btnExit').addEventListener('click',closeFrame);
$('btnClose').addEventListener('click',closeFrame);
document.addEventListener('keydown',e=>{if(e.key==='F9'){e.preventDefault();$('btnOk').click();}});

(async()=>{
  try{const d=await api({action:'init'});$('tdate').value=d.today||'';}
  catch(e){setStatus('Init failed: '+e.message);}
})();
</script>
</body>
</html>
