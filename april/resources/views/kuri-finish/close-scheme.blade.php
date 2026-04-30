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
  .window{max-width:1080px;margin:12px auto;background:var(--panel);border:1px solid #8a8a8a;box-shadow:inset 0 0 0 1px #efefef}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dedede,#c9c9c9);font-weight:700}
  .frame{padding:12px}
  .top-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px}
  .top-row .lbl{font-weight:700;white-space:nowrap}
  .top-row input[type="date"],.top-row input[type="number"],.top-row select{height:32px;padding:4px 8px;border:1px solid var(--line);background:var(--field)}
  .top-row input:focus,.top-row select:focus{outline:none;background:#fff5e8;border-color:#9b6a18}
  button{font:inherit;cursor:pointer}
  .btn{height:32px;padding:0 14px;border:1px solid #666;background:#efefef;font-weight:700}
  .btn:hover{background:#e0e0e0}
  .grid-wrap{border:1px solid #999;background:#fff;max-height:440px;overflow:auto}
  table{width:100%;border-collapse:collapse;font-size:12px}
  th,td{border:1px solid #ccc;padding:5px 6px;text-align:left;white-space:nowrap}
  th{position:sticky;top:0;background:#c8c8c8;z-index:2;font-weight:700}
  tbody tr:nth-child(even){background:#f4f4f4}
  tbody tr:hover{background:#e4eeff}
  td.num{text-align:right}
  td input[type="number"]{width:100%;height:26px;border:1px solid #bbb;padding:2px 6px;text-align:right;background:#fff}
  td input[type="number"]:focus{background:#fff5e8;border-color:#9b6a18;outline:none}
  td input[type="checkbox"]{width:16px;height:16px}
  .footer-row{display:flex;justify-content:space-between;align-items:center;padding:7px 6px;background:#c8c8c8;border:1px solid #999;border-top:none;font-weight:700;font-size:12px}
  .actions-ring{margin:14px auto 0;max-width:480px;padding:14px 22px;border:4px solid #2748ff;border-radius:999px;background:var(--ring);display:flex;justify-content:center;gap:14px}
  .actions-ring button{min-width:80px;height:36px;border:1px solid #666;background:#f3f0de;font-weight:700}
  .actions-ring button:hover{background:#e8e4c8}
  .status{min-height:20px;margin-top:10px;font-weight:700;color:#9b0000;text-align:center}
</style>
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div>{{ $title }}</div>
    <button type="button" id="btnClose">Close</button>
  </div>
  <div class="frame">
    <div class="top-row">
      <span class="lbl">Finished Date</span>
      <input type="date" id="tdate" style="width:155px">
      <span class="lbl">Gold Rate</span>
      <input type="number" id="grate" step="0.01" style="width:110px" placeholder="0.00">
      <span class="lbl">Kuri Type</span>
      <select id="stype" style="width:180px"><option value="">-- All --</option></select>
      <button class="btn" id="btnShow">Show</button>
    </div>

    <div class="grid-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:36px">Sel</th>
            <th style="width:90px">Code</th>
            <th style="min-width:160px">Name</th>
            <th style="width:110px;text-align:right">Kuri Amt</th>
            <th style="width:110px;text-align:right">Total Colln</th>
            <th style="width:110px;text-align:right">Balance</th>
            <th style="width:105px;text-align:right">Est.Wgt</th>
            <th style="width:110px;text-align:right">Bonus</th>
          </tr>
        </thead>
        <tbody id="gridBody"></tbody>
      </table>
    </div>
    <div class="footer-row">
      <span>Selected: <span id="selCount">0</span> of <span id="totalCount">0</span></span>
      <span>Total Colln: <span id="totColln">0.00</span> &nbsp; Bonus: <span id="totBonus">0.00</span></span>
    </div>

    <div class="actions-ring">
      <button type="button" id="btnSave">&amp;Save</button>
      <button type="button" id="btnSelAll">Sel All</button>
      <button type="button" id="btnClrAll">Clr All</button>
      <button type="button" id="btnExit">E&amp;xit</button>
    </div>
    <div class="status" id="statusText"></div>
  </div>
</div>

<script>
const API = @json(url('/api/kuri-finish'));
let gridData = [];

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function esc(v){ return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt2(v){ return parseFloat(v||0).toFixed(2); }
function fmt3(v){ return parseFloat(v||0).toFixed(3); }
function setStatus(msg,ok){ $('statusText').textContent=msg||''; $('statusText').style.color=ok?'#0b5a18':'#9b0000'; }
function closeFrame(){ window.parent.postMessage({type:'goldapp:close-module-frame'},'*'); }

async function api(payload){
  const res = await fetch(API,{
    method:'POST',credentials:'same-origin',
    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest'},
    body:JSON.stringify(payload)
  });
  const d = await res.json();
  if(!res.ok && !d.success) throw new Error(d.error||'Request failed');
  return d;
}

function renderGrid(){
  const body=$('gridBody');
  body.innerHTML='';
  gridData.forEach((r,i)=>{
    const tr=document.createElement('tr');
    tr.innerHTML=
      '<td style="text-align:center"><input type="checkbox" class="sel-chk" data-idx="'+i+'" '+(r.sel?'checked':'')+'></td>'
      +'<td>'+esc(r.code)+'</td>'
      +'<td>'+esc(r.name)+'</td>'
      +'<td class="num">'+fmt2(r.totamt)+'</td>'
      +'<td class="num">'+fmt2(r.tcolln)+'</td>'
      +'<td class="num">'+fmt2(r.balance)+'</td>'
      +'<td class="num">'+fmt3(r.estwgt)+'</td>'
      +'<td><input type="number" step="0.01" data-idx="'+i+'" value="'+fmt2(r.bonus)+'"></td>';
    body.appendChild(tr);
  });
  body.querySelectorAll('.sel-chk').forEach(cb=>{
    cb.addEventListener('change',e=>{ gridData[+e.target.dataset.idx].sel=e.target.checked?1:0; updateTotals(); });
  });
  body.querySelectorAll('td input[type="number"]').forEach(inp=>{
    inp.addEventListener('change',e=>{ gridData[+e.target.dataset.idx].bonus=parseFloat(e.target.value)||0; updateTotals(); });
  });
  updateTotals();
}

function updateTotals(){
  let sc=0,tc=0,tb=0;
  gridData.forEach(r=>{ if(r.sel){sc++;tc+=parseFloat(r.tcolln||0);tb+=parseFloat(r.bonus||0);} });
  $('selCount').textContent=sc;
  $('totalCount').textContent=gridData.length;
  $('totColln').textContent=fmt2(tc);
  $('totBonus').textContent=fmt2(tb);
}

$('btnShow').addEventListener('click',async()=>{
  try{
    setStatus('Loading...');
    $('btnShow').disabled=true;
    const d=await api({action:'show',tdate:$('tdate').value,stype:$('stype').value,grate:parseFloat($('grate').value)||0});
    gridData=d.data||[];
    renderGrid();
    setStatus(gridData.length+' active scheme member(s) loaded.',true);
  }catch(e){setStatus('Error: '+e.message);}
  finally{$('btnShow').disabled=false;}
});

$('btnSave').addEventListener('click',async()=>{
  const sel=gridData.filter(r=>r.sel);
  if(sel.length===0){setStatus('No rows selected.');return;}
  if(!confirm('Close scheme for '+sel.length+' member(s)? This cannot be undone.')) return;
  try{
    setStatus('Saving...');
    $('btnSave').disabled=true;
    const d=await api({action:'save-finish',tdate:$('tdate').value,grate:parseFloat($('grate').value)||0,rows:sel});
    setStatus(d.message||'Saved',d.success);
    if(d.success){gridData=[];renderGrid();}
  }catch(e){setStatus('Save error: '+e.message);}
  finally{$('btnSave').disabled=false;}
});

$('btnSelAll').addEventListener('click',()=>{ gridData.forEach(r=>r.sel=1); renderGrid(); });
$('btnClrAll').addEventListener('click',()=>{ gridData.forEach(r=>r.sel=0); renderGrid(); });
$('btnExit').addEventListener('click',closeFrame);
$('btnClose').addEventListener('click',closeFrame);
document.addEventListener('keydown',e=>{if(e.key==='F9'){e.preventDefault();$('btnSave').click();}});

// Init
(async()=>{
  try{
    const d=await api({action:'init'});
    $('tdate').value=d.today||'';
    const td=await api({action:'types'});
    const sel=$('stype');
    (td.types||[]).forEach(t=>{
      const o=document.createElement('option');
      o.value=t.code; o.textContent=t.code+(t.name?' - '+t.name:'');
      sel.appendChild(o);
    });
    setStatus('Ready. Select type and click Show.',true);
  }catch(e){setStatus('Init failed: '+e.message);}
})();
</script>
</body>
</html>
