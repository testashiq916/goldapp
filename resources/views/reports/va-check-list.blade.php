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

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:8px 14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;flex-shrink:0}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap;margin-right:4px}
.tb-lbl{color:rgba(255,255,255,.8);font-size:17px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.tb-input{height:28px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 6px;font-size:17px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:17px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}

.content{flex:1;overflow:auto;padding:16px}

.card{background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:16px;overflow:hidden}
.card-header{background:#f8fafc;padding:10px 16px;font-size:13px;font-weight:700;color:#1e3a5f;border-bottom:2px solid #e2e8f0}
.card-body{padding:0}

.va-table{width:100%;border-collapse:collapse;font-size:12px}
.va-table th{background:#f1f5f9;padding:8px 12px;text-align:right;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;border-bottom:1px solid #e2e8f0}
.va-table th:first-child{text-align:left}
.va-table td{padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:right;font-variant-numeric:tabular-nums}
.va-table td:first-child{text-align:left;font-weight:600;color:#334155}
.va-table tr:last-child td{border-bottom:none}
.va-table tr.total td{font-weight:700;background:#eff6ff;border-top:2px solid #bfdbfe;color:#1e40af}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:17px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:17px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

@media print{.toolbar{display:none !important}.content{overflow:visible !important}body{height:auto;overflow:visible}}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>

<div class="toolbar">
  <h1>{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:120px"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:120px"></div>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="sub-header" id="subHeader"></div>

<div class="content" id="content">
  <div style="text-align:center;padding:60px;color:#94a3b8">Press <b>Show</b> to load data</div>
</div>

<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const RLEVEL = {{ $rlevel }};

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function toast(msg,ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

async function loadData(){
  const qs = new URLSearchParams({
    date1: document.getElementById('date1').value,
    date2: document.getElementById('date2').value,
    rlevel: RLEVEL,
  });

  document.getElementById('subHeader').textContent = 'From: '+document.getElementById('date1').value+' To: '+document.getElementById('date2').value;

  try {
    const r = await fetch(SITE+'/api/va-check-list/data?'+qs);
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Error',false); return; }
    render(d.data);
    toast('Data loaded');
  } catch(e){ toast('Network error',false); }
}

function render(data){
  const s = data.sale || {};
  const sr = data.salereturn || {};
  const sm = data.smith || {};

  // Net VA = sale VA - return VA - smith VA
  const saleNetVA  = parseFloat(s.netva||0);
  const srVA       = parseFloat(sr.vaamt||0);
  const smVA       = parseFloat(sm.vaamt||0);
  const netProfit  = saleNetVA - srVA - smVA;

  const html = `
  <div class="card">
    <div class="card-header">V/A Summary</div>
    <div class="card-body">
      <table class="va-table">
        <thead>
          <tr>
            <th style="text-align:left">Description</th>
            <th>Weight</th>
            <th>Wastage</th>
            <th>Making Charge</th>
            <th>T.Disc</th>
            <th>Net V/A Amount</th>
            <th>VA/Gm</th>
            <th>%</th>
            <th>Stone Price</th>
            <th>Stone Weight</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>By Sales (Received)</td>
            <td>${nf(s.weight,3)}</td>
            <td>${nf(s.wastage,3)}</td>
            <td>${nf(s.mcharge)}</td>
            <td>${nf(s.disc)}</td>
            <td>${nf(s.netva)}</td>
            <td>${nf(s.vapergm)}</td>
            <td>${nf(s.tvaperc)}</td>
            <td>${nf(s.stoneprice)}</td>
            <td>${nf(s.stonewgt,3)}</td>
          </tr>
          <tr>
            <td>By S. Return (Issued)</td>
            <td></td>
            <td>${nf(sr.wastage,3)}</td>
            <td>${nf(sr.mcharge)}</td>
            <td></td>
            <td>${nf(sr.vaamt)}</td>
            <td></td>
            <td></td>
            <td>${nf(sr.stoneprice)}</td>
            <td>${nf(sr.stonewgt,3)}</td>
          </tr>
          <tr>
            <td>By GoldSmith (Issued)</td>
            <td></td>
            <td>${nf(sm.wastage,3)}</td>
            <td>${nf(sm.mcharge)}</td>
            <td></td>
            <td>${nf(sm.vaamt)}</td>
            <td></td>
            <td></td>
            <td>${nf(sm.stoneprice)}</td>
            <td></td>
          </tr>
          <tr class="total">
            <td>Net V/A Profit</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td>${nf(netProfit)}</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>`;

  document.getElementById('content').innerHTML = html;
}

document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');
</script>
</body>
</html>
