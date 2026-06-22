<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;color:#1e293b;font-size:20px;height:100vh;overflow:hidden;display:flex;flex-direction:column}

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:10px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;flex-shrink:0}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap;margin-right:4px}
.tb-lbl{color:rgba(255,255,255,.8);font-size:17px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.tb-input,.tb-select{height:30px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 8px;font-size:12px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus,.tb-select:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.tb-select option{background:#1e3a5f;color:#fff}
.tb-check{display:flex;align-items:center;gap:4px;color:rgba(255,255,255,.85);font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap}
.tb-check input{width:14px;height:14px;accent-color:#60a5fa;cursor:pointer}
.f-group{display:flex;align-items:center;gap:4px}
.sep{width:1px;height:24px;background:rgba(255,255,255,.2);margin:0 2px}
.btn{padding:5px 14px;border:none;border-radius:6px;font-size:17px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}

.grid-wrap{flex:1;overflow:auto}
table{width:100%;border-collapse:collapse;background:#fff;font-size:12px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:6px 8px;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2;cursor:pointer;user-select:none}
th:hover{background:#e2e8f0}
th.num{text-align:right}
td{padding:5px 8px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
tr.sel td{background:#dbeafe !important}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:8px 16px;display:flex;gap:20px;flex-wrap:wrap;font-size:12px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.sub-header{background:#eff6ff;padding:6px 16px;font-size:17px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:17px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

@media print{
  .toolbar,.summary,.sub-header{display:none !important}
  .grid-wrap{overflow:visible !important}
  body{height:auto;overflow:visible}
}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>

<div class="toolbar">
  <h1>{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl">From</span>
    <input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:130px">
  </div>
  <div class="f-group">
    <span class="tb-lbl">To</span>
    <input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:130px">
  </div>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl">SM</span>
    <select id="smcode" class="tb-select" style="width:140px">
      <option value="">-- All --</option>
      @foreach($salesmen as $sm)
      <option value="{{ $sm['code'] }}">{{ $sm['code'] }} - {{ $sm['name'] }}</option>
      @endforeach
    </select>
  </div>
  <div class="sep"></div>
  <label class="tb-check"><input type="checkbox" id="cbDaySummary"> Day Summary</label>
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
    <tbody id="tbody"><tr><td colspan="12" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
  </table>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const RLEVEL = {{ $rlevel }};
let rows = [], totals = {}, sortCol = '', sortDir = 1;

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

function isDaySummary(){ return document.getElementById('cbDaySummary').checked; }

function headers(){
  if(isDaySummary()){
    return [
      ['Date','tdate'],
      ['Sales Wgt','totwgt',1,3],
      ['Bill Amt','billamt',1],
      ['Exchange','eamt',1],
      ['Ex.Wgt','totexwgt',1,3],
      ['S.Ret.Amt','sretamt',1],
      ['Ret.Wgt','totretwgt',1,3],
      ['SGST','sgst',1],
      ['CGST','cgst',1],
      ['Tax','staxamt',1],
      ['Discount','discount',1],
      ['Received','tramt',1],
      ['Balance','balance',1]
    ];
  }
  return [
    ['Date','tdate'],
    ['Bill No','billno'],
    ['Code','custcode'],
    ['Customer','custname'],
    ['Bill Amt','billamt',1],
    ['Exchange','eamt',1],
    ['S.Ret.Amt','sretamt',1],
    ['SGST','sgst',1],
    ['CGST','cgst',1],
    ['Tax','staxamt',1],
    ['Discount','discount',1],
    ['Received','tramt',1],
    ['Balance','balance',1]
  ];
}

async function loadData(){
  const d1 = document.getElementById('date1').value;
  const d2 = document.getElementById('date2').value;
  const sm = document.getElementById('smcode').value;
  const mode = isDaySummary() ? 'daysummary' : 'detail';

  const qs = new URLSearchParams({ date1:d1, date2:d2, rlevel:RLEVEL, smcode:sm, mode });

  // Update sub-header
  let sub = `From: ${d1} To: ${d2}`;
  if(sm) sub += ` - SM: ${sm}`;
  document.getElementById('subHeader').textContent = sub;

  try {
    const r = await fetch(`${SITE}/api/monthly-sales-report/data?${qs}`);
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Error',false); return; }
    rows = d.rows||[];
    totals = d.totals||{};
    sortCol = ''; sortDir = 1;
    render();
    toast(`Loaded ${rows.length} rows`);
  } catch(e){ toast('Network error',false); }
}

function render(){
  const hs = headers();
  const thead = document.getElementById('thead');
  const tbody = document.getElementById('tbody');

  thead.innerHTML = '<tr>' + hs.map(h=>{
    let arrow = '';
    if(sortCol===h[1]) arrow = sortDir===1?' &#9650;':' &#9660;';
    return `<th class="${h[2]?'num':''}" data-key="${h[1]}">${esc(h[0])}${arrow}</th>`;
  }).join('') + '</tr>';

  if(!rows.length){
    tbody.innerHTML = `<tr><td colspan="${hs.length}" style="text-align:center;padding:40px;color:#94a3b8">No records found</td></tr>`;
  } else {
    tbody.innerHTML = rows.map((row,idx)=>{
      return '<tr data-idx="'+idx+'">' + hs.map(h=>{
        let val = row[h[1]];
        if(h[2]) val = nf(val, h[3]??2);
        return `<td class="${h[2]?'num':''}">${esc(val)}</td>`;
      }).join('') + '</tr>';
    }).join('');
  }

  // Summary
  const t = totals;
  const parts = [`<span>Rows: <b>${rows.length}</b></span>`];
  parts.push(`<span>Bill Amt: <b>${nf(t.billamt)}</b></span>`);
  parts.push(`<span>Exchange: <b>${nf(t.eamt)}</b></span>`);
  parts.push(`<span>S.Ret: <b>${nf(t.sretamt)}</b></span>`);
  parts.push(`<span>SGST: <b>${nf(t.sgst)}</b></span>`);
  parts.push(`<span>CGST: <b>${nf(t.cgst)}</b></span>`);
  parts.push(`<span>Tax: <b>${nf(t.staxamt)}</b></span>`);
  parts.push(`<span>Disc: <b>${nf(t.discount)}</b></span>`);
  parts.push(`<span>Received: <b>${nf(t.tramt)}</b></span>`);
  parts.push(`<span>Balance: <b>${nf(t.balance)}</b></span>`);
  if(isDaySummary()){
    parts.push(`<span>Sales Wgt: <b>${nf(t.totwgt,3)}</b></span>`);
    parts.push(`<span>Ex.Wgt: <b>${nf(t.totexwgt,3)}</b></span>`);
    parts.push(`<span>Ret.Wgt: <b>${nf(t.totretwgt,3)}</b></span>`);
  }
  document.getElementById('summary').innerHTML = parts.join('');
}

/* ── Sort ─────────────────────────────────────────────────────── */
document.getElementById('thead').addEventListener('click', e=>{
  const th = e.target.closest('th[data-key]');
  if(!th) return;
  const key = th.dataset.key;
  if(sortCol===key) sortDir*=-1; else { sortCol=key; sortDir=1; }
  rows.sort((a,b)=>{
    let va=a[key]??'', vb=b[key]??'';
    if(!isNaN(parseFloat(va))) return (parseFloat(va)-parseFloat(vb))*sortDir;
    return String(va).localeCompare(String(vb))*sortDir;
  });
  render();
});

/* ── Row highlight + double-click ────────────────────────────── */
document.getElementById('tbody').addEventListener('click', e=>{
  const tr = e.target.closest('tr[data-idx]');
  if(!tr) return;
  document.querySelectorAll('#tbody tr').forEach(r=>r.classList.remove('sel'));
  tr.classList.add('sel');
});

/* ── SM change auto-retrieves (mirrors PB itemchanged) ───────── */
document.getElementById('smcode').addEventListener('change', ()=>{
  if(rows.length) loadData();
});

/* ── Buttons ─────────────────────────────────────────────────── */
document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');

/* ── Init ─────────────────────────────────────────────────────── */
loadData();
</script>
<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>
ReportExport.init('btnSaveAs', headers, ()=>rows,
  ()=>`monthly-sales-${document.getElementById('date1').value}-to-${document.getElementById('date2').value}`);
document.getElementById('btnSaveAs').onclick = function(e){
  e.preventDefault();
  ReportExport.open(headers, ()=>rows,
    ()=>`monthly-sales-${document.getElementById('date1').value}-to-${document.getElementById('date2').value}`);
};
</script>
</body>
</html>
