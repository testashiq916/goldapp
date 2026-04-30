<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;color:#1e293b;font-size:13px;height:100vh;overflow:hidden;display:flex;flex-direction:column}

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:8px 14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;flex-shrink:0}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap;margin-right:4px}
.tb-lbl{color:rgba(255,255,255,.8);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.tb-input,.tb-select{height:28px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 6px;font-size:11px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus,.tb-select:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.tb-input::placeholder{color:rgba(255,255,255,.4)}
.tb-select option{color:#1e293b;background:#fff}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}

.grid-wrap{flex:1;overflow:auto}
table{width:100%;border-collapse:collapse;background:#fff;font-size:11px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:5px 6px;font-size:9px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2;cursor:pointer;user-select:none}
th:hover{background:#e2e8f0}
th.num{text-align:right}
th .arr{font-size:8px;margin-left:2px;opacity:.5}
td{padding:4px 6px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
.tag{font-size:9px;font-weight:700;padding:1px 6px;border-radius:3px;display:inline-block}
.tag-g{background:#dbeafe;color:#1e40af}
.tag-j{background:#fef3c7;color:#92400e}
.tag-gold{background:#fef9c3;color:#854d0e}
.tag-silver{background:#f1f5f9;color:#475569}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:11px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:16px;flex-wrap:wrap;font-size:11px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:12px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

@media print{.toolbar,.summary,.sub-header{display:none !important}.grid-wrap{overflow:visible !important}body{height:auto;overflow:visible}}
</style>
@include('partials.print-layout-head')
</head>
<body>

<div class="toolbar">
  <h1>{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">CType</span>
    <select id="selCtype" class="tb-select" style="width:130px">
      <option value="">All</option>
      <option value="G">Goldsmith Only</option>
      <option value="J">Jewellery Only</option>
    </select>
  </div>
  <div class="f-group"><span class="tb-lbl">Metal</span>
    <select id="selMetal" class="tb-select" style="width:90px">
      <option value="">All</option>
      <option value="G">Gold</option>
      <option value="S">Silver</option>
    </select>
  </div>
  <div class="f-group"><span class="tb-lbl">Group</span>
    <select id="selGroup" class="tb-select" style="width:110px">
      <option value="">All</option>
      @foreach($groups as $g)
      <option value="{{ $g['code'] }}">{{ $g['code'] }} - {{ $g['name'] }}</option>
      @endforeach
    </select>
  </div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Search</span><input type="text" id="inpSearch" class="tb-input" placeholder="Name or Code" style="width:140px"></div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">View</span>
    <select id="selView" class="tb-select" style="width:100px">
      <option value="basic">Basic</option>
      <option value="contact">Contact</option>
      <option value="opening">Opening Bal</option>
      <option value="full">Full</option>
    </select>
  </div>
  <div class="f-group"><span class="tb-lbl">Sort</span>
    <select id="selSort" class="tb-select" style="width:100px">
      <option value="name">Name</option>
      <option value="code">Code</option>
      <option value="grp">Group</option>
    </select>
  </div>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="sub-header" id="subHeader"></div>

<div class="grid-wrap">
  <table>
    <thead id="thead"></thead>
    <tbody id="tbody"><tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
  </table>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
let rows = [], sortCol = 'name', sortDir = 1;

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function nf3(n){ return Number(n||0).toFixed(3); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

function getCols(){
  const v = document.getElementById('selView').value;
  const base = [
    { key:'code',       label:'Code',       cls:'' },
    { key:'name',       label:'Name',       cls:'' },
    { key:'grp',        label:'Group',      cls:'' },
    { key:'ctypelabel', label:'Type',       cls:'', tag:true },
    { key:'metallabel', label:'Metal',      cls:'', tag:true },
  ];
  if(v==='contact' || v==='full'){
    base.push({ key:'phone',   label:'Phone',   cls:'' });
    base.push({ key:'address', label:'Address',  cls:'' });
    base.push({ key:'email',   label:'Email',    cls:'' });
  }
  if(v==='opening' || v==='full'){
    base.push({ key:'opweight',  label:'Op.Weight',  cls:'num', fmt:'3' });
    base.push({ key:'opbalance', label:'Op.Balance',  cls:'num', fmt:'2' });
    base.push({ key:'stocktouch',label:'Stock Touch', cls:'num', fmt:'2' });
    base.push({ key:'convtouch', label:'Conv Touch',  cls:'num', fmt:'2' });
  }
  if(v==='full'){
    base.push({ key:'pan', label:'PAN/Aadhar', cls:'' });
  }
  return base;
}

function renderHead(){
  const cols = getCols();
  const tr = document.createElement('tr');
  cols.forEach(c=>{
    const th = document.createElement('th');
    th.className = c.cls;
    th.innerHTML = esc(c.label) + (sortCol===c.key ? '<span class="arr">'+(sortDir>0?'▲':'▼')+'</span>' : '');
    th.onclick = () => { if(sortCol===c.key) sortDir*=-1; else { sortCol=c.key; sortDir=1; } doSort(); renderHead(); renderBody(); };
    tr.appendChild(th);
  });
  document.getElementById('thead').innerHTML = '';
  document.getElementById('thead').appendChild(tr);
}

function doSort(){
  rows.sort((a,b)=>{
    let va=a[sortCol], vb=b[sortCol];
    if(typeof va==='string') va=(va||'').toLowerCase();
    if(typeof vb==='string') vb=(vb||'').toLowerCase();
    if(va==null) va='';
    if(vb==null) vb='';
    if(va<vb) return -sortDir;
    if(va>vb) return sortDir;
    return 0;
  });
}

function tagHtml(val, key){
  if(key==='ctypelabel'){
    const cls = val==='Goldsmith'?'tag-g':'tag-j';
    return '<span class="tag '+cls+'">'+esc(val)+'</span>';
  }
  if(key==='metallabel'){
    const cls = val==='Silver'?'tag-silver':'tag-gold';
    return '<span class="tag '+cls+'">'+esc(val)+'</span>';
  }
  return esc(val);
}

function renderBody(){
  const cols = getCols();
  const tbody = document.getElementById('tbody');
  if(!rows.length){
    tbody.innerHTML='<tr><td colspan="'+cols.length+'" style="text-align:center;padding:40px;color:#94a3b8">No data found</td></tr>';
    renderSummary();
    return;
  }
  let html='';
  rows.forEach(r=>{
    html+='<tr>';
    cols.forEach(c=>{
      if(c.cls==='num'){
        const d = c.fmt==='3'?3:2;
        html+='<td class="num">'+Number(r[c.key]||0).toFixed(d)+'</td>';
      } else if(c.tag){
        html+='<td>'+tagHtml(r[c.key], c.key)+'</td>';
      } else {
        html+='<td>'+esc(r[c.key])+'</td>';
      }
    });
    html+='</tr>';
  });
  tbody.innerHTML=html;
  renderSummary();
}

function renderSummary(){
  let gs=0, js=0, gold=0, silver=0;
  rows.forEach(r=>{
    if(r.ctype==='G') gs++; else js++;
    if(r.silver==='Y') silver++; else gold++;
  });
  document.getElementById('summary').innerHTML =
    '<span>Total: <b>'+rows.length+'</b></span>'+
    '<span>Goldsmiths: <b>'+gs+'</b></span>'+
    '<span>Jewellery: <b>'+js+'</b></span>'+
    '<span>Gold: <b>'+gold+'</b></span>'+
    '<span>Silver: <b>'+silver+'</b></span>';
}

function loadData(){
  const ctype  = document.getElementById('selCtype').value;
  const metal  = document.getElementById('selMetal').value;
  const group  = document.getElementById('selGroup').value;
  const search = document.getElementById('inpSearch').value.trim();

  const params = new URLSearchParams({ctype, metal, group, search});
  document.getElementById('subHeader').textContent = 'Loading...';
  const cols = getCols();
  document.getElementById('tbody').innerHTML='<tr><td colspan="'+cols.length+'" style="text-align:center;padding:30px;color:#94a3b8">Loading...</td></tr>';

  fetch(SITE+'/api/smith-list/data?'+params.toString())
    .then(r=>r.json())
    .then(d=>{
      if(!d.ok){ toast(d.message||'Failed','err'); return; }
      rows = d.rows||[];
      sortCol = document.getElementById('selSort').value;
      sortDir = 1;
      doSort();
      renderHead();
      renderBody();
      const parts = [rows.length+' smiths loaded'];
      if(ctype) parts.push('Type: '+(ctype==='G'?'Goldsmith':'Jewellery'));
      if(metal) parts.push('Metal: '+(metal==='G'?'Gold':'Silver'));
      if(group) parts.push('Group: '+group);
      if(search) parts.push('Search: '+search);
      document.getElementById('subHeader').textContent = parts.join(' | ');
    })
    .catch(()=> toast('Network error',false));
}

function saveAsCsv(){
  if(!rows.length){ toast('No data to export',false); return; }
  const cols = getCols();
  let csv = cols.map(c=>'"'+c.label+'"').join(',')+'\n';
  rows.forEach(r=>{
    csv += cols.map(c=>{
      let v = r[c.key]; if(v==null) v='';
      return '"'+String(v).replace(/"/g,'""')+'"';
    }).join(',')+'\n';
  });
  const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'smith_list.csv';
  a.click();
}

document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = () => window.print();
document.getElementById('btnSaveAs').onclick = saveAsCsv;
document.getElementById('btnExit').onclick = () => { if(window.parent!==window) window.parent.postMessage({action:'closeModule'},'*'); else window.close(); };
document.getElementById('selSort').onchange = () => { sortCol=document.getElementById('selSort').value; sortDir=1; doSort(); renderHead(); renderBody(); };
document.getElementById('selView').onchange = () => { renderHead(); renderBody(); };

document.addEventListener('keydown', e=>{
  if(e.key==='F5'||e.key==='Enter'){ e.preventDefault(); loadData(); }
});

renderHead();
</script>
</body>
</html>
