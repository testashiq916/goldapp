<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
  :root{--bg:#c0c0c0;--panel:#d7d7d7;--field:#fff;--line:#7e7e7e;--ring:#c7ba82}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:540px;margin:20px auto;background:var(--panel);border:1px solid #8a8a8a;box-shadow:inset 0 0 0 1px #efefef}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dedede,#c9c9c9);font-weight:700}
  .frame{padding:14px 18px}
  .row{display:grid;grid-template-columns:140px 1fr;align-items:center;gap:6px;margin-bottom:9px}
  .row label{font-weight:700;text-align:right;padding-right:8px}
  .row input{height:30px;padding:3px 8px;border:1px solid var(--line);background:var(--field);width:100%}
  .row input:focus{outline:none;background:#fff5e8;border-color:#9b6a18}
  .row input[readonly]{background:#e8e8e8;color:#333}
  .suggest{position:relative;width:100%}
  .suggest input{width:100%}
  .suggest-list{display:none;position:absolute;left:0;right:0;top:100%;max-height:160px;overflow:auto;background:#fff;border:1px solid var(--line);z-index:20}
  .suggest-list div{padding:4px 8px;cursor:pointer}
  .suggest-list div:hover{background:#e9f1ff}
  hr{border:none;border-top:1px solid #aaa;margin:10px 0}
  .info-block{background:#fff;border:1px solid #bbb;padding:10px 14px;margin-bottom:10px;font-size:12px}
  .info-block table{width:100%;border-collapse:collapse}
  .info-block td{padding:3px 6px}
  .info-block td:first-child{font-weight:700;width:45%;text-align:right;color:#555}
  button{font:inherit;cursor:pointer}
  .actions-ring{margin:14px auto 0;max-width:360px;padding:14px 22px;border:4px solid #2748ff;border-radius:999px;background:var(--ring);display:flex;justify-content:center;gap:14px}
  .actions-ring button{min-width:90px;height:36px;border:1px solid #666;background:#f3f0de;font-weight:700}
  .actions-ring button:hover{background:#e8e4c8}
  .status{min-height:18px;margin-top:10px;font-weight:700;color:#9b0000;text-align:center}
</style>
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div>{{ $title }}</div>
    <button type="button" id="btnClose">Close</button>
  </div>
  <div class="frame">
    <div class="row">
      <label>Refund Date</label>
      <input type="date" id="tdate">
    </div>
    <div class="row">
      <label>Gold Rate</label>
      <input type="number" id="grate" step="0.01" placeholder="0.00">
    </div>
    <div class="row">
      <label>Party</label>
      <div class="suggest">
        <input type="text" id="partyCode" autocomplete="off" placeholder="Code / Name (Enter to search)">
        <div class="suggest-list" id="suggestList"></div>
      </div>
    </div>

    <div class="info-block" id="infoBlock" style="display:none">
      <table>
        <tr><td>Name</td><td id="infoName"></td></tr>
        <tr><td>Total Scheme Amt</td><td id="infoTotamt"></td></tr>
        <tr><td>Total Collected</td><td id="infoTcolln"></td></tr>
        <tr><td>Balance Due</td><td id="infoBalance"></td></tr>
        <tr><td>Status</td><td id="infoStatus"></td></tr>
      </table>
    </div>

    <div class="row" id="refundRow" style="display:none">
      <label>Refund Amount</label>
      <input type="number" id="refundamt" step="0.01" placeholder="0.00">
    </div>
    <div class="row" id="noteRow" style="display:none">
      <label>Note</label>
      <input type="text" id="refundNote" maxlength="40" placeholder="Optional">
    </div>

    <div class="actions-ring">
      <button type="button" id="btnSave">&amp;Save</button>
      <button type="button" id="btnExit">E&amp;xit</button>
    </div>
    <div class="status" id="statusText"></div>
  </div>
</div>

<script>
const API = @json(url('/api/kuri-finish'));
let currentCode='', partyData={};

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function setStatus(msg,ok){ $('statusText').textContent=msg||''; $('statusText').style.color=ok?'#0b5a18':'#9b0000'; }
function closeFrame(){ window.parent.postMessage({type:'goldapp:close-module-frame'},'*'); }
function fmt2(v){ return parseFloat(v||0).toFixed(2); }

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
    if(!d.success){setStatus(d.error||'Party not found');clearParty();return;}
    currentCode=d.code;
    partyData=d;
    $('partyCode').value=d.code;
    $('infoName').textContent=d.name;
    $('infoTotamt').textContent=fmt2(d.totamt);
    $('infoTcolln').textContent=fmt2(d.tcolln);
    $('infoBalance').textContent=fmt2(d.balance);
    const fin=d.finished||'N';
    const finMap={N:'Active',Y:'Closed (Finish)',D:'Closed (Draw)',R:'Refunded'};
    $('infoStatus').textContent=finMap[fin]||fin;
    $('infoBlock').style.display='block';
    // Pre-fill refund as total collected
    $('refundamt').value=fmt2(d.tcolln);
    $('refundRow').style.display='grid';
    $('noteRow').style.display='grid';
    if(fin!=='N') setStatus('Warning: this party status is already "'+finMap[fin]+'"',false);
    else setStatus('');
  }catch(e){setStatus('Error: '+e.message);clearParty();}
}

function clearParty(){
  currentCode='';partyData={};
  $('infoBlock').style.display='none';
  $('refundRow').style.display='none';
  $('noteRow').style.display='none';
}

// Suggest
const inp=$('partyCode');
const list=$('suggestList');
let timer=null;
inp.addEventListener('input',()=>{
  clearTimeout(timer);
  const v=inp.value.trim();
  if(v.length<1){list.style.display='none';return;}
  timer=setTimeout(async()=>{
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
  if(e.key==='Enter'){e.preventDefault();const v=inp.value.trim().toUpperCase();if(v)loadParty(v);}
});

$('btnSave').addEventListener('click',async()=>{
  if(!currentCode){setStatus('Select a party first.');return;}
  const amt=parseFloat($('refundamt').value)||0;
  if(amt<=0){setStatus('Enter a valid refund amount.');return;}
  if(!confirm('Refund ₹'+amt.toFixed(2)+' for party '+currentCode+'?')) return;
  try{
    setStatus('Saving...');
    $('btnSave').disabled=true;
    const d=await api({action:'save-refund',tdate:$('tdate').value,code:currentCode,
      refundamt:amt,grate:parseFloat($('grate').value)||0});
    setStatus(d.message||'Saved',d.success);
    if(d.success){clearParty();$('partyCode').value='';$('partyCode').focus();}
  }catch(e){setStatus('Save error: '+e.message);}
  finally{$('btnSave').disabled=false;}
});

$('btnExit').addEventListener('click',closeFrame);
$('btnClose').addEventListener('click',closeFrame);
document.addEventListener('keydown',e=>{if(e.key==='F9'){e.preventDefault();$('btnSave').click();}});

(async()=>{
  try{const d=await api({action:'init'});$('tdate').value=d.today||'';}
  catch(e){setStatus('Init failed: '+e.message);}
})();
</script>
</body>
</html>
