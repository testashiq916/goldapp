<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Model Transfer - Select</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:#f0f2f5;font-family:'Segoe UI',system-ui,-apple-system,sans-serif;color:#1e293b;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:16px}

  .card{width:min(820px,100%);background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08),0 1px 3px rgba(0,0,0,.06);overflow:hidden;display:flex;flex-direction:column;max-height:calc(100vh - 32px)}
  .card-head{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:16px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
  .card-head h2{color:#fff;font-size:15px;font-weight:700;letter-spacing:.3px}
  .card-head .close-btn{background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:8px;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s}
  .card-head .close-btn:hover{background:rgba(255,255,255,.25)}

  .card-body{padding:20px 24px;flex:1;overflow:auto;display:flex;flex-direction:column;gap:16px}

  .search-row{display:flex;gap:8px;align-items:center}
  .search-row input{flex:1;height:44px;border:1.5px solid #e2e8f0;border-radius:10px;padding:0 14px;font-size:14px;color:#1e293b;background:#f8fafc;transition:all .15s;outline:none}
  .search-row input:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
  .search-row input::placeholder{color:#94a3b8}

  .btn{height:44px;padding:0 18px;border:none;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;justify-content:center;gap:6px}
  .btn-outline{background:#f8fafc;border:1.5px solid #e2e8f0;color:#475569}
  .btn-outline:hover{background:#f1f5f9;border-color:#cbd5e1}
  .btn-primary{background:#3b82f6;color:#fff}
  .btn-primary:hover{background:#2563eb;box-shadow:0 4px 12px rgba(59,130,246,.3)}
  .btn-secondary{background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0}
  .btn-secondary:hover{background:#e2e8f0}
  .btn-danger{background:#ef4444;color:#fff}
  .btn-danger:hover{background:#dc2626;box-shadow:0 4px 12px rgba(239,68,68,.3)}

  .grid-wrap{flex:1;overflow:auto;border-radius:10px;border:1.5px solid #e2e8f0}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th{background:#f8fafc;padding:10px 14px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;text-align:left;border-bottom:2px solid #e2e8f0;position:sticky;top:0}
  td{padding:10px 14px;border-bottom:1px solid #f1f5f9}
  td.r{text-align:right;font-variant-numeric:tabular-nums}
  td.c{text-align:center}
  tbody tr{cursor:pointer;transition:background .1s}
  tbody tr:hover{background:#eff6ff}
  tbody tr:active{background:#dbeafe}

  .status{min-height:20px;text-align:center;font-size:12px;font-weight:600}
  .status.err{color:#dc2626}
  .status.ok{color:#16a34a}

  .card-footer{display:flex;gap:10px;justify-content:center;padding:16px 24px;border-top:1px solid #f1f5f9;flex-shrink:0}

  /* Detail Modal */
  .modal-bg{position:fixed;inset:0;background:rgba(15,23,42,.4);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:100;padding:16px}
  .modal-bg.show{display:flex}
  .modal{width:min(520px,100%);max-height:80vh;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden;display:flex;flex-direction:column}
  .modal-head{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:linear-gradient(135deg,#1e3a5f,#2c5282);color:#fff}
  .modal-head span{font-size:14px;font-weight:700}
  .modal-head .close-btn{background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:8px;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s}
  .modal-head .close-btn:hover{background:rgba(255,255,255,.25)}
  .modal-body{overflow:auto;flex:1;padding:20px}
  .detail-grid{display:grid;grid-template-columns:100px 1fr;gap:8px 12px;font-size:13px}
  .detail-grid .lbl{font-weight:700;color:#64748b;text-align:right}
  .modal-actions{padding:16px 20px;display:flex;gap:10px;justify-content:center;border-top:1px solid #e2e8f0}
</style>
</head>
<body>
<div class="card">
  <div class="card-head">
    <h2 id="titleLabel">Model Transfer - Select</h2>
    <button type="button" class="close-btn" id="btnClose">&times;</button>
  </div>
  <div class="card-body">
    <div class="search-row">
      <input type="text" id="searchInput" placeholder="Search slno, item code, party...">
      <button type="button" class="btn btn-outline" id="btnSearch">Search</button>
    </div>
    <div class="grid-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:60px">Slno</th>
            <th style="width:85px">Date</th>
            <th style="width:70px">Item</th>
            <th>Party</th>
            <th style="width:40px">Qty</th>
            <th style="width:70px">Weight</th>
            <th style="width:30px">I/R</th>
            <th style="width:40px">Pend</th>
          </tr>
        </thead>
        <tbody id="gridBody"></tbody>
      </table>
    </div>
    <div class="status" id="statusText"></div>
  </div>
  <div class="card-footer">
    <button type="button" class="btn btn-secondary" id="btnExit">Exit</button>
  </div>
</div>

<!-- Detail modal -->
<div class="modal-bg" id="detailModal">
  <div class="modal">
    <div class="modal-head"><span id="detailTitle">Detail</span><button type="button" class="close-btn" id="detailClose">&times;</button></div>
    <div class="modal-body" id="detailBody"></div>
    <div class="modal-actions" id="detailActions"></div>
  </div>
</div>

<script>
const API = @json(url('/api/stock-suspense-entry'));
const params = new URLSearchParams(window.location.search);
const PICKER_MODE = params.get('mode') || 'cancel'; // cancel or reprint

function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
function $(id){ return document.getElementById(id); }
function esc(v){ return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function setStatus(msg='',ok=false){ $('statusText').textContent=msg; $('statusText').className='status '+(ok?'ok':'err'); }
function closeFrame(){ window.parent.postMessage({type:'goldapp:close-module-frame'},'*'); }

async function api(payload){
  const res=await fetch(API,{method:'POST',credentials:'same-origin',
    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf(),'X-Requested-With':'XMLHttpRequest'},
    body:JSON.stringify(payload)});
  const data=await res.json();
  if(!res.ok&&!data.success) throw new Error(data.error||'Request failed');
  return data;
}

const modeLabels={cancel:'Cancel',reprint:'Reprint'};
$('titleLabel').textContent='Model Transfer - '+(modeLabels[PICKER_MODE]||'Select');
document.title=$('titleLabel').textContent;

async function loadList(){
  try{
    setStatus('Loading...');
    const d=await api({action:'list',search:$('searchInput').value.trim()});
    const body=$('gridBody'); body.innerHTML='';
    (d.data||[]).forEach(r=>{
      const tr=document.createElement('tr');
      tr.innerHTML='<td class="c">'+r.slno+'</td><td class="c">'+esc(r.tdate)+'</td><td>'+esc(r.icode)+'</td><td>'+esc(r.pname)+'</td><td class="c">'+r.qty+'</td><td class="r">'+r.weight.toFixed(3)+'</td><td class="c">'+esc(r.ir)+'</td><td class="c">'+esc(r.pend)+'</td>';
      tr.addEventListener('dblclick',()=>openDetail(r.slno));
      body.appendChild(tr);
    });
    setStatus((d.data||[]).length+' record(s)',true);
  }catch(e){setStatus('Error: '+e.message);}
}

$('btnSearch').addEventListener('click',loadList);
$('searchInput').addEventListener('keydown',e=>{if(e.key==='Enter')loadList();});

async function openDetail(slno){
  try{
    const d=await api({action:'load',slno});
    if(!d.success){setStatus(d.error||'Not found');return;}
    const rec=d.record;
    const irLabel=rec.ir==='I'?'Issue':'Receipt';
    let html='<div class="detail-grid">'
      +'<div class="lbl">Slno:</div><div>'+rec.slno+'</div>'
      +'<div class="lbl">Date:</div><div>'+esc(rec.tdate)+'</div>'
      +'<div class="lbl">Party:</div><div>'+esc(rec.pcode)+' - '+esc(rec.pname)+'</div>'
      +'<div class="lbl">SM:</div><div>'+esc(rec.smcode)+'</div>'
      +'<div class="lbl">Item:</div><div>'+esc(rec.icode)+' - '+esc(rec.iname)+'</div>'
      +'<div class="lbl">Barcode:</div><div>'+(rec.bcode||'-')+'</div>'
      +'<div class="lbl">Qty:</div><div>'+rec.qty+'</div>'
      +'<div class="lbl">Weight:</div><div>'+rec.weight.toFixed(3)+'</div>'
      +'<div class="lbl">St.Wgt:</div><div>'+rec.stwgt.toFixed(3)+'</div>'
      +'<div class="lbl">Stk Type:</div><div>'+esc(rec.stktype)+'</div>'
      +'<div class="lbl">Type:</div><div>'+irLabel+' ('+esc(rec.gr)+')</div>'
      +'<div class="lbl">Pending:</div><div>'+esc(rec.pend)+'</div>'
      +'<div class="lbl">Note:</div><div>'+esc(rec.note)+'</div>'
      +'</div>';
    $('detailBody').innerHTML=html;
    $('detailTitle').textContent='#'+rec.slno+' - '+irLabel;

    const acts=$('detailActions'); acts.innerHTML='';

    if(PICKER_MODE==='cancel'){
      const btn=document.createElement('button'); btn.className='btn btn-danger'; btn.textContent='Cancel This Record';
      btn.addEventListener('click',async()=>{
        if(!confirm('Cancel model transfer #'+rec.slno+'? Stock will be reversed.')) return;
        try{
          setStatus('Cancelling...');
          const r=await api({action:'delete',slno:rec.slno});
          setStatus(r.message||'Cancelled',r.success);
          $('detailModal').classList.remove('show');
          loadList();
        }catch(ex){setStatus('Cancel error: '+ex.message);}
      });
      acts.appendChild(btn);
    }

    if(PICKER_MODE==='reprint'){
      const btn=document.createElement('button'); btn.className='btn btn-primary'; btn.textContent='Print';
      btn.addEventListener('click',()=>{
        const pw=window.open('','_blank','width=500,height=400');
        pw.document.write('<html><head><title>Model Transfer #'+rec.slno+'</title><style>body{font-family:Arial;font-size:13px;margin:20px}table{border-collapse:collapse;width:100%}td{padding:4px 8px}td:first-child{font-weight:700;width:100px}</style></head><body>');
        pw.document.write('<h3>Model Transfer - '+irLabel+'</h3><table>');
        pw.document.write('<tr><td>Slno:</td><td>'+rec.slno+'</td></tr>');
        pw.document.write('<tr><td>Date:</td><td>'+esc(rec.tdate)+'</td></tr>');
        pw.document.write('<tr><td>Party:</td><td>'+esc(rec.pcode)+' '+esc(rec.pname)+'</td></tr>');
        pw.document.write('<tr><td>Item:</td><td>'+esc(rec.icode)+' '+esc(rec.iname)+'</td></tr>');
        pw.document.write('<tr><td>Qty:</td><td>'+rec.qty+'</td></tr>');
        pw.document.write('<tr><td>Weight:</td><td>'+rec.weight.toFixed(3)+'</td></tr>');
        pw.document.write('<tr><td>St.Wgt:</td><td>'+rec.stwgt.toFixed(3)+'</td></tr>');
        pw.document.write('<tr><td>Note:</td><td>'+esc(rec.note)+'</td></tr>');
        pw.document.write('</table></body></html>');
        pw.document.close(); pw.focus(); pw.print();
      });
      acts.appendChild(btn);
    }

    const closeBtn=document.createElement('button'); closeBtn.className='btn btn-secondary'; closeBtn.textContent='Close';
    closeBtn.addEventListener('click',()=>$('detailModal').classList.remove('show'));
    acts.appendChild(closeBtn);

    $('detailModal').classList.add('show');
  }catch(e){setStatus('Error: '+e.message);}
}

$('detailClose').addEventListener('click',()=>$('detailModal').classList.remove('show'));
$('btnExit').addEventListener('click',closeFrame);
$('btnClose').addEventListener('click',closeFrame);
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&$('detailModal').classList.contains('show'))$('detailModal').classList.remove('show');});

loadList();
</script>
</body>
</html>
