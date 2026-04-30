<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Purity Certificate</title>
<style>
  :root{--bg:#c0c0c0;--panel:#d7d7d7;--field:#fff;--line:#7e7e7e;--ring:#c7ba82}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:720px;margin:12px auto;background:var(--panel);border:1px solid #8a8a8a;box-shadow:inset 0 0 0 1px #efefef}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dedede,#c9c9c9);font-weight:700}
  .frame{padding:14px}
  .info-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;font-size:13px}
  .info-row span{font-weight:700}
  .grid-wrap{border:1px solid #999;background:#fff;max-height:360px;overflow:auto}
  table{width:100%;border-collapse:collapse;font-size:12px}
  th,td{border:1px solid #ccc;padding:5px 8px;text-align:left}
  th{position:sticky;top:0;background:#c8c8c8;z-index:2;font-weight:700}
  tbody tr:nth-child(even){background:#f4f4f4}
  tbody tr:hover{background:#e4eeff}
  td input{width:100%;height:26px;border:1px solid #bbb;padding:2px 6px;background:#fff}
  td input:focus{background:#fff5e8;border-color:#9b6a18;outline:none}
  .grid-btns{margin-top:6px;display:flex;gap:8px}
  .grid-btns button{height:30px;padding:0 14px;border:1px solid #888;background:#efefef;font-weight:700;cursor:pointer}
  .grid-btns button:hover{background:#e0e0e0}
  .actions-ring{margin:16px auto 0;max-width:380px;padding:14px 22px;border:4px solid #2748ff;border-radius:999px;background:var(--ring);display:flex;justify-content:center;gap:14px}
  .actions-ring button{min-width:80px;height:36px;border:1px solid #666;background:#f3f0de;font-weight:700;cursor:pointer}
  .actions-ring button:hover{background:#e8e4c8}
  .status{min-height:20px;margin-top:10px;font-weight:700;color:#9b0000;text-align:center}
  /* item search modal */
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
    <div>Purity Certificate</div>
    <button type="button" id="btnClose">Close</button>
  </div>
  <div class="frame">
    <div class="info-row">
      <div>Date: <span id="dispDate"></span></div>
      <div>Certificate No: <span id="dispCertNo"></span></div>
      <div>Certificate Date: <span id="dispCertDate"></span></div>
    </div>

    <div class="grid-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:40px">SlNo</th>
            <th>Item</th>
            <th style="width:50px">Std</th>
            <th style="width:30px">X</th>
          </tr>
        </thead>
        <tbody id="gridBody"></tbody>
      </table>
    </div>
    <div class="grid-btns">
      <button type="button" id="btnAdd">Add</button>
      <button type="button" id="btnDel">Del</button>
    </div>

    <div class="actions-ring">
      <button type="button" id="btnPrint">Print</button>
      <button type="button" id="btnExit">Exit</button>
    </div>
    <div class="status" id="statusText"></div>
  </div>
</div>

<!-- Item search modal -->
<div class="modal-bg" id="itemModal">
  <div class="modal">
    <div class="modal-head"><span>Item Search</span><button type="button" id="itemModalClose">X</button></div>
    <div class="modal-search"><input type="text" id="itemModalSearch" placeholder="Search item code or name..."></div>
    <div class="modal-body"><table><thead><tr><th>Code</th><th>Name</th></tr></thead><tbody id="itemModalRows"></tbody></table></div>
  </div>
</div>

<script>
const API = @json(url('/api/purity-certificate'));
let certNo = 1;
let items = []; // [{name:'...'}]
let activeRow = -1; // for F1 item search

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function esc(v){ return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
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

function formatDate(d){ const dt=new Date(d); return dt.toLocaleDateString('en-GB'); }

function renderGrid(){
  const body=$('gridBody'); body.innerHTML='';
  items.forEach((it,i)=>{
    const tr=document.createElement('tr');
    tr.innerHTML='<td style="text-align:center">'+(i+1)+'</td>'
      +'<td><input type="text" data-i="'+i+'" value="'+esc(it.name)+'" class="item-inp"></td>'
      +'<td style="text-align:center">BIS</td>'
      +'<td style="text-align:center"><button type="button" data-i="'+i+'" class="del-btn" style="border:none;background:none;color:red;font-weight:700;cursor:pointer">X</button></td>';
    body.appendChild(tr);
  });

  body.querySelectorAll('.item-inp').forEach(inp=>{
    inp.addEventListener('change',e=>{ items[+e.target.dataset.i].name=e.target.value.trim(); });
    inp.addEventListener('keydown',e=>{
      if(e.key==='F1'){ e.preventDefault(); activeRow=+e.target.dataset.i; openItemModal(); }
    });
  });
  body.querySelectorAll('.del-btn').forEach(btn=>{
    btn.addEventListener('click',e=>{ items.splice(+e.target.dataset.i,1); renderGrid(); });
  });
}

// Add row
$('btnAdd').addEventListener('click',()=>{
  if(items.length>0 && items[items.length-1].name===''){
    setStatus('Fill the last item first'); return;
  }
  items.push({name:''});
  renderGrid();
  // Focus last input
  const inps=$('gridBody').querySelectorAll('.item-inp');
  if(inps.length) inps[inps.length-1].focus();
});

// Del last row
$('btnDel').addEventListener('click',()=>{
  if(items.length>0){ items.pop(); renderGrid(); }
});

// Item search modal
function openItemModal(){
  $('itemModal').classList.add('show');
  $('itemModalSearch').value='';
  $('itemModalSearch').focus();
}
$('itemModalClose').addEventListener('click',()=>$('itemModal').classList.remove('show'));

let itemTimer;
$('itemModalSearch').addEventListener('input',()=>{
  clearTimeout(itemTimer);
  itemTimer=setTimeout(()=>loadItemList($('itemModalSearch').value.trim()),300);
});

async function loadItemList(search){
  if(!search){$('itemModalRows').innerHTML='';return;}
  try{
    const d=await api({action:'item_search',search});
    const body=$('itemModalRows'); body.innerHTML='';
    (d.data||[]).forEach(r=>{
      const tr=document.createElement('tr');
      tr.innerHTML='<td>'+esc(r.code)+'</td><td>'+esc(r.name)+'</td>';
      tr.addEventListener('dblclick',()=>{
        if(activeRow>=0 && activeRow<items.length){
          items[activeRow].name=r.name;
        }
        $('itemModal').classList.remove('show');
        renderGrid();
      });
      body.appendChild(tr);
    });
  }catch(e){}
}

// Print
$('btnPrint').addEventListener('click',async()=>{
  const validItems=items.filter(it=>it.name!=='');
  if(validItems.length===0){setStatus('Add at least one item');return;}

  const today=formatDate(new Date());
  const pw=window.open('','_blank','width=650,height=500');
  pw.document.write('<html><head><title>Purity Certificate</title>');
  pw.document.write('<style>body{font-family:Arial;font-size:13px;margin:30px}h2{text-align:center;text-decoration:underline;margin:0 0 16px}table{width:100%;border-collapse:collapse;margin-top:10px}th,td{padding:5px 8px;text-align:left;border-bottom:1px solid #ccc}th{font-weight:700}.header{display:flex;justify-content:space-between;margin-bottom:6px}.header div{font-size:12px}hr{border:none;border-top:2px solid #000;margin:8px 0}</style>');
  pw.document.write('
</head><body>');
  pw.document.write('<h2>PURITY CERTIFICATE</h2>');
  pw.document.write('<div class="header"><div>Date: '+today+'</div><div>Certificate No.: '+certNo+'</div></div>');
  pw.document.write('<div class="header"><div></div><div>Certificate Date: '+today+'</div></div>');
  pw.document.write('<hr>');
  pw.document.write('<table><tr><th>SlNo</th><th>Item</th><th>Std</th></tr>');
  validItems.forEach((it,i)=>{
    pw.document.write('<tr><td>'+(i+1)+'</td><td>'+esc(it.name)+'</td><td>BIS</td></tr>');
  });
  pw.document.write('</table><hr></body></html>');
  pw.document.close(); pw.focus(); pw.print();

  // Ask to increment cert number
  if(confirm('Update the Certificate No?')){
    try{
      const d=await api({action:'increment'});
      if(d.success){
        certNo=d.nextCertNo;
        $('dispCertNo').textContent=certNo;
      }
    }catch(e){setStatus('Error updating cert no: '+e.message);}
  }

  // Reset items
  items=[]; renderGrid();
  items.push({name:''}); renderGrid();
  const inps=$('gridBody').querySelectorAll('.item-inp');
  if(inps.length) inps[0].focus();
});

// Exit / Close
$('btnExit').addEventListener('click',closeFrame);
$('btnClose').addEventListener('click',closeFrame);

document.addEventListener('keydown',e=>{
  if(e.key==='Escape'&&$('itemModal').classList.contains('show')) $('itemModal').classList.remove('show');
});

// Init
async function init(){
  try{
    const d=await api({action:'init'});
    certNo=d.certNo||1;
    const today=d.today||new Date().toISOString().slice(0,10);
    $('dispDate').textContent=formatDate(today);
    $('dispCertNo').textContent=certNo;
    $('dispCertDate').textContent=formatDate(today);
    items.push({name:''});
    renderGrid();
    setStatus('Ready. Add items and print certificate.',true);
    const inps=$('gridBody').querySelectorAll('.item-inp');
    if(inps.length) inps[0].focus();
  }catch(e){setStatus('Init failed: '+e.message);}
}
init();
</script>
</body>
</html>
