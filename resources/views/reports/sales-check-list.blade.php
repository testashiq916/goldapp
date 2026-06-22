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
.f-group{display:flex;align-items:center;gap:4px}
.sep{width:1px;height:24px;background:rgba(255,255,255,.2);margin:0 2px}
.btn{padding:5px 14px;border:none;border-radius:6px;font-size:17px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}

.grid-wrap{flex:1;overflow:auto;padding:8px 12px}

/* Bill card style */
.bill-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.bill-header{display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px 16px;padding:10px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:12px;cursor:pointer}
.bill-header:hover{background:#eff6ff}
.bill-header .bh-item{display:flex;gap:4px}
.bill-header .bh-lbl{color:#64748b;font-weight:600;font-size:10px;text-transform:uppercase;min-width:60px}
.bill-header .bh-val{color:#1e293b;font-weight:600}

.bill-items{border-bottom:1px solid #e2e8f0}
.bill-items table{width:100%;border-collapse:collapse;font-size:11px}
.bill-items th{background:#f1f5f9;padding:4px 8px;font-size:15px;font-weight:700;color:#64748b;text-transform:uppercase;text-align:left;border-bottom:1px solid #e2e8f0}
.bill-items th.num{text-align:right}
.bill-items td{padding:4px 8px;border-bottom:1px solid #f1f5f9}
.bill-items td.num{text-align:right;font-variant-numeric:tabular-nums}

.bill-footer{display:grid;grid-template-columns:repeat(4,1fr);gap:4px 12px;padding:8px 14px;background:#fefce8;font-size:11px}
.bill-footer .bf-item{display:flex;justify-content:space-between;gap:4px}
.bill-footer .bf-lbl{color:#78716c;font-weight:600;font-size:10px}
.bill-footer .bf-val{color:#1e293b;font-weight:700;text-align:right}
.bill-footer .bf-bal{color:#dc2626}
.bill-footer .bf-net{color:#1e40af}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:8px 16px;display:flex;gap:20px;flex-wrap:wrap;font-size:12px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.sub-header{background:#eff6ff;padding:6px 16px;font-size:17px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:17px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

@media print{
  .toolbar,.summary,.sub-header{display:none !important}
  .grid-wrap{overflow:visible !important;padding:0}
  body{height:auto;overflow:visible}
  .bill-card{break-inside:avoid;box-shadow:none;border:1px solid #aaa}
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
    <span class="tb-lbl">Customer</span>
    <input type="text" id="custcode" class="tb-input" placeholder="Code" style="width:80px;text-transform:uppercase">
  </div>
  <div class="f-group">
    <span class="tb-lbl">Bill No</span>
    <input type="text" id="billno" class="tb-input" placeholder="" style="width:80px;text-transform:uppercase">
  </div>
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
  <button class="btn btn-show" id="btnShow">Show</button>
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="sub-header" id="subHeader"></div>

<div class="grid-wrap" id="gridWrap">
  <div style="text-align:center;padding:60px;color:#94a3b8">Press <b>Show</b> to load data</div>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const RLEVEL = {{ $rlevel }};
let bills = [];

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
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
    smcode: document.getElementById('smcode').value,
    custcode: document.getElementById('custcode').value.trim().toUpperCase(),
    billno: document.getElementById('billno').value.trim().toUpperCase(),
  });

  let sub = 'From: '+document.getElementById('date1').value+' To: '+document.getElementById('date2').value;
  const sm = document.getElementById('smcode').value;
  if(sm) sub += ' | SM: '+sm;
  const cust = document.getElementById('custcode').value.trim();
  if(cust) sub += ' | Customer: '+cust;
  document.getElementById('subHeader').textContent = sub;

  try {
    const r = await fetch(SITE+'/api/sales-check-list/data?'+qs);
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Error',false); return; }
    bills = d.bills||[];
    render();
    toast('Loaded '+bills.length+' bills');
  } catch(e){ toast('Network error',false); }
}

function render(){
  const wrap = document.getElementById('gridWrap');

  if(!bills.length){
    wrap.innerHTML = '<div style="text-align:center;padding:60px;color:#94a3b8">No records found</div>';
    document.getElementById('summary').innerHTML = '';
    return;
  }

  let html = '';
  let totBillamt=0, totEamt=0, totSret=0, totSgst=0, totCgst=0, totTax=0, totDisc=0, totAdv=0, totNet=0, totRcvd=0, totBal=0;

  bills.forEach(b=>{
    totBillamt += +b.billamt||0;
    totEamt += +b.eamt||0;
    totSret += +b.sretamt||0;
    totSgst += +b.sgst||0;
    totCgst += +b.cgst||0;
    totTax += +b.staxamt||0;
    totDisc += +b.discount||0;
    totAdv += +b.advance||0;
    totNet += +b.netamt||0;
    totRcvd += +b.ramt||0;
    totBal += +b.balance||0;

    html += '<div class="bill-card">';
    // Header
    html += '<div class="bill-header">';
    html += '<div class="bh-item"><span class="bh-lbl">Rate</span><span class="bh-val">'+nf(b.grate)+'</span></div>';
    html += '<div class="bh-item"><span class="bh-lbl">Date</span><span class="bh-val">'+esc(b.tdate)+(b.ttime?' '+esc(b.ttime):'')+'</span></div>';
    html += '<div class="bh-item"><span class="bh-lbl">Bill No</span><span class="bh-val">'+esc(b.billno)+'</span></div>';
    html += '<div class="bh-item"><span class="bh-lbl">Customer</span><span class="bh-val">'+esc(b.custcode)+' - '+esc(b.custname)+'</span></div>';
    html += '<div class="bh-item"><span class="bh-lbl">Due Date</span><span class="bh-val">'+esc(b.duedate||'')+'</span></div>';
    html += '<div class="bh-item"><span class="bh-lbl">SM</span><span class="bh-val">'+esc(b.smcode)+' '+esc(b.smname||'')+'</span></div>';
    if(b.addr) html += '<div class="bh-item" style="grid-column:span 2"><span class="bh-lbl">Address</span><span class="bh-val">'+esc(b.addr)+'</span></div>';
    if(b.pan) html += '<div class="bh-item"><span class="bh-lbl">PAN/Adh</span><span class="bh-val">'+esc(b.pan)+'</span></div>';
    if(b.note) html += '<div class="bh-item" style="grid-column:span 3"><span class="bh-lbl">Note</span><span class="bh-val">'+esc(b.note)+'</span></div>';
    html += '</div>';

    // Items table
    if(b.items && b.items.length){
      html += '<div class="bill-items"><table>';
      html += '<tr><th>Sno</th><th>Item Code</th><th>Item Name</th><th class="num">Qty</th><th class="num">Gross Wgt</th><th class="num">St.Wgt</th><th class="num">Net Wgt</th><th class="num">Rate</th><th class="num">MC/VA</th><th class="num">Amount</th></tr>';
      b.items.forEach(it=>{
        const grossWgt = Number(it.weight || 0);
        const stoneWgt = Number(it.stonewgt || 0);
        html += '<tr>';
        html += '<td>'+esc(it.sno)+'</td>';
        html += '<td>'+esc(it.item_code)+'</td>';
        html += '<td>'+esc(it.item_name)+'</td>';
        html += '<td class="num">'+nf(it.qty,0)+'</td>';
        html += '<td class="num">'+nf(grossWgt,3)+'</td>';
        html += '<td class="num">'+nf(stoneWgt,3)+'</td>';
        html += '<td class="num">'+nf(grossWgt - stoneWgt,3)+'</td>';
        html += '<td class="num">'+nf(it.rate)+'</td>';
        html += '<td class="num">'+nf(it.mcharge)+'</td>';
        html += '<td class="num">'+nf(it.amount)+'</td>';
        html += '</tr>';
      });
      html += '</table></div>';
    }

    // Footer with amounts
    html += '<div class="bill-footer">';
    html += '<div class="bf-item"><span class="bf-lbl">Exch Amt</span><span class="bf-val">'+nf(b.eamt)+'</span></div>';
    html += '<div class="bf-item"><span class="bf-lbl">S.Ret Amt</span><span class="bf-val">'+nf(b.sretamt)+'</span></div>';
    html += '<div class="bf-item"><span class="bf-lbl">Net Total</span><span class="bf-val">'+nf(b.nettotal)+'</span></div>';
    html += '<div class="bf-item"><span class="bf-lbl">Discount</span><span class="bf-val">'+nf(b.discount)+'</span></div>';
    html += '<div class="bf-item"><span class="bf-lbl">Cash Adv</span><span class="bf-val">'+nf(b.advance)+'</span></div>';
    html += '<div class="bf-item"><span class="bf-lbl">SGST</span><span class="bf-val">'+nf(b.sgst)+'</span></div>';
    html += '<div class="bf-item"><span class="bf-lbl">CGST</span><span class="bf-val">'+nf(b.cgst)+'</span></div>';
    html += '<div class="bf-item"><span class="bf-lbl">Tax</span><span class="bf-val">'+nf(b.staxamt)+'</span></div>';
    html += '<div class="bf-item"><span class="bf-lbl">Net Amt</span><span class="bf-val bf-net">'+nf(b.netamt)+'</span></div>';
    html += '<div class="bf-item"><span class="bf-lbl">Received</span><span class="bf-val">'+nf(b.ramt)+'</span></div>';
    html += '<div class="bf-item"><span class="bf-lbl">Balance</span><span class="bf-val bf-bal">'+nf(b.balance)+'</span></div>';
    html += '</div>';

    html += '</div>';
  });

  wrap.innerHTML = html;

  // Summary
  const parts = [
    '<span>Bills: <b>'+bills.length+'</b></span>',
    '<span>Bill Amt: <b>'+nf(totBillamt)+'</b></span>',
    '<span>Exch: <b>'+nf(totEamt)+'</b></span>',
    '<span>S.Ret: <b>'+nf(totSret)+'</b></span>',
    '<span>SGST: <b>'+nf(totSgst)+'</b></span>',
    '<span>CGST: <b>'+nf(totCgst)+'</b></span>',
    '<span>Tax: <b>'+nf(totTax)+'</b></span>',
    '<span>Disc: <b>'+nf(totDisc)+'</b></span>',
    '<span>Net: <b>'+nf(totNet)+'</b></span>',
    '<span>Rcvd: <b>'+nf(totRcvd)+'</b></span>',
    '<span>Balance: <b>'+nf(totBal)+'</b></span>',
  ];
  document.getElementById('summary').innerHTML = parts.join('');
}

/* ── Export helpers for ReportExport ──────────────────────── */
function flatHeaders(){
  return [
    ['Date','tdate'],['Bill No','billno'],['Code','custcode'],['Customer','custname'],
    ['Bill Amt','billamt',1],['Exch Amt','eamt',1],['S.Ret','sretamt',1],
    ['SGST','sgst',1],['CGST','cgst',1],['Tax','staxamt',1],
    ['Discount','discount',1],['Advance','advance',1],['Net Amt','netamt',1],
    ['Received','ramt',1],['Balance','balance',1],['SM','smcode'],['Due Date','duedate']
  ];
}
function flatRows(){ return bills; }

/* ── SM change auto-retrieves ─────────────────────────────── */
document.getElementById('smcode').addEventListener('change', ()=>{
  if(bills.length) loadData();
});

/* ── Buttons ──────────────────────────────────────────────── */
document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');
</script>
<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>
ReportExport.init('btnSaveAs', flatHeaders, flatRows,
  ()=>'sales-checklist-'+document.getElementById('date1').value+'-to-'+document.getElementById('date2').value);
</script>
</body>
</html>
