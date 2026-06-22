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

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:6px 14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex-shrink:0}
.tb-lbl{color:rgba(255,255,255,.8);font-size:17px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.tb-input,.tb-select{height:28px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 6px;font-size:17px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus,.tb-select:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.tb-input::placeholder{color:rgba(255,255,255,.4)}
.tb-select option{background:#1e3a5f;color:#fff}
.tb-check{display:flex;align-items:center;gap:3px;color:rgba(255,255,255,.85);font-size:17px;font-weight:600;cursor:pointer;white-space:nowrap}
.tb-check input{width:13px;height:13px;accent-color:#60a5fa;cursor:pointer}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:17px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}

.toolbar-row2{background:linear-gradient(135deg,#1a3350,#253f6b);padding:4px 14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex-shrink:0;border-top:1px solid rgba(255,255,255,.08)}

/* Report page */
.content-wrap{flex:1;overflow:auto;padding:10px;background:#e2e8f0}
.report-page{background:#fff;width:100%;max-width:100%;margin:0 auto;padding:20px 32px;box-shadow:0 2px 8px rgba(0,0,0,.12);font-family:'Consolas','Courier New',monospace;font-size:11px;color:#1e293b;line-height:1.5}

.rp-title{text-align:center;font-size:13px;font-weight:700;margin-bottom:0}
.rp-subtitle{text-align:center;font-size:11px;color:#475569;margin-bottom:10px}
.rp-line{border-top:1px solid #94a3b8;margin:4px 0}
.rp-dline{border-top:2px double #475569;margin:4px 0}

.rp-hdr{display:grid;grid-template-columns:70px 42px 56px 1fr 30px 62px 30px 62px 56px 58px 50px 56px 64px;font-weight:700;font-size:9px;color:#475569;text-transform:uppercase;padding:2px 0}
.rp-hdr .r{text-align:right}

.rp-row{display:grid;grid-template-columns:70px 42px 56px 1fr 30px 62px 30px 62px 56px 58px 50px 56px 64px;padding:1px 0;font-size:10.5px}
.rp-row .r{text-align:right;font-variant-numeric:tabular-nums}
.rp-row .cell{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rp-row.txn-first{border-top:1px solid #e2e8f0;padding-top:3px}

.rp-trn-paid{font-size:10px;color:#64748b;padding:2px 0 4px 70px}

.rp-total-row{display:grid;grid-template-columns:180px 62px 62px 56px 58px 56px 64px;font-weight:700;padding:3px 0;font-size:11px}
.rp-total-row .r{text-align:right;font-variant-numeric:tabular-nums}

.rp-summary{margin-top:12px}
.rp-sum-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 40px;font-size:11px}
.rp-sum-title{font-weight:700;text-align:center;margin-bottom:4px;color:#1e3a5f;font-size:11px}
.rp-sum-line{display:flex;justify-content:space-between;padding:1px 0}
.rp-sum-line .lbl{color:#475569}
.rp-sum-line .val{font-weight:600;font-variant-numeric:tabular-nums}
.rp-sum-line .val.give{color:#dc2626}
.rp-sum-line .val.recv{color:#16a34a}

.rp-suspense{margin-top:10px;font-size:10px;color:#92400e}
.rp-suspense b{font-weight:700}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:16px;flex-wrap:wrap;font-size:17px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:17px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}
.wait-mask{position:fixed;inset:0;background:rgba(15,23,42,.35);display:none;align-items:center;justify-content:center;z-index:300}
.wait-mask.show{display:flex}
.wait-card{background:#fff;border-radius:12px;padding:18px 24px;min-width:260px;box-shadow:0 12px 40px rgba(0,0,0,.25);text-align:center}
.wait-card strong{display:block;font-size:16px;color:#1e3a5f;margin-bottom:6px}
.wait-card span{font-size:12px;color:#64748b}

.placeholder{text-align:center;padding:60px 20px;color:#94a3b8;font-size:13px;font-family:'Segoe UI',sans-serif}

@media print{
  .toolbar,.toolbar-row2,.summary{display:none !important}
  .content-wrap{overflow:visible !important;padding:0;background:#fff}
  body{height:auto;overflow:visible}
  .report-page{box-shadow:none;max-width:100%;padding:10px}
}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
@include('partials.print-layout-head')
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="wait-mask" id="waitMask">
  <div class="wait-card">
    <strong>Please wait</strong>
    <span>Loading report data...</span>
  </div>
</div>

<!-- Row 1: Type, Smith, Dates, Show/SaveAs/Print/Exit -->
<div class="toolbar">
  <div class="f-group">
    <span class="tb-lbl">Type</span>
    <select id="ctype" class="tb-select" style="width:120px">
      <option value="G" {{ $ctype === 'G' || $ctype === '' ? 'selected' : '' }}>GoldSmith</option>
      <option value="S" {{ $ctype === 'S' ? 'selected' : '' }}>Supplier</option>
      <option value="J" {{ $ctype === 'J' ? 'selected' : '' }}>Jewellery</option>
    </select>
  </div>
  <div class="sep"></div>
  <div class="f-group">
    <span class="tb-lbl" id="partyLabel">{{ $ctype === 'J' ? 'Jewellery' : ($ctype === 'S' ? 'Supplier' : 'GoldSmith') }}</span>
    <select id="smithcode" class="tb-select" style="width:180px"><option value="">-- All --</option></select>
  </div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:120px"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:120px"></div>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button type="button" class="btn btn-out" id="btnExcel">To Excel</button>
  <button type="button" class="btn btn-out" id="btnPdf">To PDF</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<!-- Row 2: Item, Lot No, checkboxes -->
<div class="toolbar-row2">
  <div class="f-group">
    <span class="tb-lbl">Item</span>
    <select id="itemcode" class="tb-select" style="width:150px"><option value="">-- All --</option></select>
  </div>
  <div class="f-group">
    <span class="tb-lbl">Lot No</span>
    <input type="text" id="lotno" class="tb-input" placeholder="" style="width:90px">
  </div>
  <div class="sep"></div>
  <label class="tb-check"><input type="checkbox" id="cbNoMcWstg"> No MC,Wastage</label>
  <label class="tb-check"><input type="checkbox" id="cbNoSummary"> No Summary</label>
  <label class="tb-check"><input type="checkbox" id="cbNoRcvdQty"> No Rcvd Qty</label>
  <label class="tb-check"><input type="checkbox" id="cbNetwgtModel"> Net Wgt Model</label>
  <label class="tb-check"><input type="checkbox" id="cbPrintLine"> Print Line</label>
  <div class="sep"></div>
  <button class="btn btn-out" id="btnSort">Sort</button>
</div>

<div class="content-wrap">
  <div class="report-page" id="reportPage">
    <div class="placeholder">Press <b>Show</b> to load the report. Leave Goldsmith empty for <b>All</b>.</div>
  </div>
</div>

<div class="summary" id="summary"></div>
<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const RLEVEL = {{ $rlevel }};
let CTYPE = @json($ctype ?? 'G') || 'G';
let reportData = null, allRows = [];
const WAIT = document.getElementById('waitMask');

const TYPE_LABELS = { G: 'GoldSmith', S: 'Supplier', J: 'Jewellery', C: 'Party' };

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function nfz(n,d=2){ const v=Number(n||0); return v!==0?v.toFixed(d):''; }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}
function balLabel(v){ return v>0?'To Give':'To Receive'; }
function balClass(v){ return v>0?'give':'recv'; }

function exportHeaders(){
  return [
    ['Date','tdate'],['Time','ttime'],['Doc No','docno'],['Item Name','itemname'],
    ['I/R','givrec'],['Qty','qty',1,0],
    ['Iss Wgt','issuedwgt',1,3],['Rcv Wgt','rcvdwgt',1,3],
    ['Stone Wgt','stonewgt',1,3],['Wastage','wastage',1,3],['Touch%','touch',1],
    ['Net Wgt','netwgt',1,3],['MC','mcharge',1],['St.Price','stoneprice',1],
    ['HMC','hmc',1]
  ];
}

async function loadLookups(ct){
  ct = ct || CTYPE || 'G';
  try {
    const r = await fetch(SITE+'/api/goldsmith-transactions/lookups?ctype='+ct);
    const d = await r.json();
    if(!d.ok) return;
    const sel1 = document.getElementById('smithcode');
    sel1.innerHTML = '<option value="">-- All --</option>' +
      (d.smiths||[]).map(i=>'<option value="'+esc(i.code)+'">'+esc(i.code)+' - '+esc(i.name)+'</option>').join('');
    const sel2 = document.getElementById('itemcode');
    sel2.innerHTML = '<option value="">-- All --</option>' +
      (d.items||[]).map(i=>'<option value="'+esc(i.code)+'">'+esc(i.code)+' - '+esc(i.name)+'</option>').join('');
  } catch(e){}
}
loadLookups(CTYPE);

// TYPE selector change — reload party list and update label
document.getElementById('ctype').onchange = function(){
  CTYPE = this.value;
  const lbl = TYPE_LABELS[CTYPE] || 'Party';
  document.getElementById('partyLabel').textContent = lbl;
  document.getElementById('smithcode').innerHTML = '<option value="">-- All --</option>';
  loadLookups(CTYPE);
  reportData = null;
  document.getElementById('reportPage').innerHTML = '<div class="placeholder">Press <b>Show</b> to load the report. Leave '+lbl+' empty for <b>All</b>.</div>';
  document.getElementById('summary').innerHTML = '';
};

async function loadData(){
  const scode = document.getElementById('smithcode').value;
  if (WAIT) WAIT.classList.add('show');

  const qs = new URLSearchParams({
    date1: document.getElementById('date1').value,
    date2: document.getElementById('date2').value,
    rlevel: RLEVEL,
    ctype: CTYPE,
    smithcode: scode,
    itemcode: document.getElementById('itemcode').value,
    lotno: document.getElementById('lotno').value.trim(),
  });

  try {
    const r = await fetch(SITE+'/api/goldsmith-transactions/data?'+qs);
    const d = await r.json();
    if(!d.ok){ toast(d.message||'Error',false); return; }
    reportData = d;
    // flatten for export
    allRows = [];
    (d.transactions||[]).forEach(txn=>{
      txn.items.forEach(it=>{
        allRows.push({...it, tdate:txn.tdate, ttime:txn.ttime, docno:txn.docno});
      });
    });
    render();
    toast('Loaded '+(d.totals?.count||0)+' entries');
  } catch(e){ toast('Network error',false); }
  finally { if (WAIT) WAIT.classList.remove('show'); }
}

function render(){
  const page = document.getElementById('reportPage');
  const d = reportData;
  if(!d){ page.innerHTML='<div class="placeholder">No data</div>'; return; }

  const d1 = document.getElementById('date1').value;
  const d2 = document.getElementById('date2').value;
  const scode = document.getElementById('smithcode').value;
  const noMcWstg = document.getElementById('cbNoMcWstg').checked;
  const noSummary = document.getElementById('cbNoSummary').checked;
  const noRcvdQty = document.getElementById('cbNoRcvdQty').checked;
  const ctype = d.smithctype;
  const typeLabel = ctype==='J'?'Jewellery':(ctype==='C'?'Party':'Gold Smith');
  const partyLabel = scode ? (d.smithname || d.smithcode || '') : ('All ' + typeLabel + 's');

  let h = '';

  // Title
  h += '<div class="rp-title">'+esc(typeLabel)+' Ledger</div>';
  h += '<div class="rp-subtitle">From '+esc(d1)+' To '+esc(d2)+'</div>';
  h += '<div class="rp-subtitle" style="font-weight:700;color:#1e293b">'+esc(typeLabel)+' : '+esc(partyLabel)+'</div>';

  h += '<div class="rp-line"></div>';

  // Column headers
  if(noMcWstg){
    h += '<div class="rp-hdr" style="grid-template-columns:70px 1fr 30px 62px 30px 62px 62px">';
    h += '<div>Date</div><div>Item Name</div>';
    h += '<div class="r">RQ</div><div class="r">Rcv Wgt</div>';
    h += '<div class="r">IQ</div><div class="r">Iss Wgt</div>';
    h += '<div class="r">Net Wgt</div>';
    h += '</div>';
  } else {
    h += '<div class="rp-hdr">';
    h += '<div>Date</div><div>Time</div><div>Doc.No</div><div>Item Name</div>';
    h += '<div class="r">IQ</div><div class="r">Iss.Wgt</div>';
    if(!noRcvdQty) h += '<div class="r">RQ</div>'; else h += '<div></div>';
    h += '<div class="r">Rcv.Wgt</div>';
    h += '<div class="r">Wgt Amt</div><div class="r">Stone/T%</div>';
    h += '<div class="r">Wastage</div><div class="r">Net Wgt</div>';
    h += '<div class="r">MC/Amt</div>';
    h += '</div>';
  }

  h += '<div class="rp-line"></div>';

  // Transaction rows
  const txns = d.transactions||[];
  if(!txns.length){
    h += '<div style="text-align:center;padding:20px;color:#94a3b8">No transactions found</div>';
  } else {
    txns.forEach(txn=>{
      let isFirst = true;
      txn.items.forEach(it=>{
        if(noMcWstg){
          h += '<div class="rp-row'+(isFirst?' txn-first':'')+'" style="grid-template-columns:70px 1fr 30px 62px 30px 62px 62px">';
          h += '<div class="cell">'+(isFirst?esc(txn.tdate):'')+'</div>';
          h += '<div class="cell">'+esc(it.itemname)+'</div>';
          h += '<div class="cell r">'+nfz(it.rcvdqty,0)+'</div>';
          h += '<div class="cell r">'+nfz(it.rcvdwgt,3)+'</div>';
          h += '<div class="cell r">'+nfz(it.issuedqty,0)+'</div>';
          h += '<div class="cell r">'+nfz(it.issuedwgt,3)+'</div>';
          h += '<div class="cell r">'+nfz(it.netwgt,3)+'</div>';
          h += '</div>';
        } else {
          h += '<div class="rp-row'+(isFirst?' txn-first':'')+'">';
          h += '<div class="cell">'+(isFirst?esc(txn.tdate):'')+'</div>';
          h += '<div class="cell">'+(isFirst?esc(txn.ttime):'')+'</div>';
          h += '<div class="cell">'+(isFirst?esc(txn.docno):'')+'</div>';
          h += '<div class="cell">'+esc(it.itemname)+'</div>';
          h += '<div class="cell r">'+nfz(it.issuedqty,0)+'</div>';
          h += '<div class="cell r">'+nfz(it.issuedwgt,3)+'</div>';
          if(!noRcvdQty) h += '<div class="cell r">'+nfz(it.rcvdqty,0)+'</div>'; else h += '<div></div>';
          h += '<div class="cell r">'+nfz(it.rcvdwgt,3)+'</div>';
          // Stone / Touch%
          const stoneTouch = (it.stonewgt>0?nf(it.stonewgt,3):'') +
            (it.stonewgt>0&&it.touch>0?' / ':'') +
            (it.touch>0?nf(it.touch)+'%':'');
          h += '<div class="cell r">'+stoneTouch+'</div>';
          // Wastage or Touch%
          h += '<div class="cell r">'+(it.wastage>0?nf(it.wastage,3):(it.touch>0&&it.wastage===0?nf(it.touch)+'%':''))+'</div>';
          h += '<div class="cell r">'+nfz(it.netwgt,3)+'</div>';
          h += '<div class="cell r">'+nfz(it.mcharge)+'</div>';
          h += '</div>';
        }
        isFirst = false;
      });
    });
  }

  h += '<div class="rp-line"></div>';

  // ── Totals ──
  const t = d.totals||{};
  if(noMcWstg){
    h += '<div style="display:flex;justify-content:space-between;font-weight:700;font-size:11px;padding:3px 0">';
    h += '<span>Total :</span>';
    h += '<span>Rcvd: '+nf(t.rcvdwgt2,3)+'</span>';
    h += '<span>Issued: '+nf(t.issuedwgt2,3)+'</span>';
    h += '</div>';
  } else {
    h += '<div style="display:flex;justify-content:space-between;font-weight:700;font-size:11px;padding:3px 0">';
    h += '<span>Total :</span>';
    h += '<span>Rcvd: '+nf(t.rcvdwgt2,3)+'</span>';
    h += '<span>Issued: '+nf(t.issuedwgt2,3)+'</span>';
    h += '<span>St.Price: '+nf(t.stprice)+'</span>';
    h += '<span>MC: '+nf(t.mcharge)+'</span>';
    h += '</div>';
  }

  h += '<div class="rp-dline"></div>';

  // ── Weight & Amount Summary ──
  if(!noSummary){
    const ws = d.weight_summary||{};
    const as2 = d.amount_summary||{};

    h += '<div class="rp-summary">';
    h += '<div class="rp-sum-grid">';

    // Weight Summary (left)
    h += '<div>';
    h += '<div class="rp-sum-title">WEIGHT SUMMARY</div>';
    h += sumLine('Opening Balance',nf(Math.abs(ws.opbal),3),balLabel(ws.opbal),balClass(ws.opbal));
    h += sumLine('Received Weight',nf(ws.rcvd,3));
    h += '<div class="rp-line"></div>';
    h += sumLine('Total',nf(Math.abs(ws.total),3),balLabel(ws.total),balClass(ws.total));
    h += sumLine('Issued Weight',nf(ws.issued,3));
    h += '<div class="rp-line"></div>';
    h += sumLine('Closing Balance',nf(Math.abs(ws.close),3),balLabel(ws.close),balClass(ws.close));
    h += '</div>';

    // Amount Summary (right)
    h += '<div>';
    h += '<div class="rp-sum-title">AMOUNT SUMMARY</div>';
    h += sumLine('Opening Balance',nf(Math.abs(as2.opbal)),balLabel(as2.opbal),balClass(as2.opbal));
    h += sumLine('Making Charge',nf(as2.making));
    if(as2.hmc!==0) h += sumLine('Tot HM Charge',nf(as2.hmc));
    if(as2.acid!==0) h += sumLine('Tot Acid Charge',nf(as2.acid));
    if(as2.disc!==0) h += sumLine('Tot Discount',nf(as2.disc));
    h += '<div class="rp-line"></div>';
    h += sumLine('Total',nf(Math.abs(as2.total)),balLabel(as2.total),balClass(as2.total));
    h += sumLine('Paid Amount',nf(as2.paid));
    h += '<div class="rp-line"></div>';
    h += sumLine('Closing Balance',nf(Math.abs(as2.close)),balLabel(as2.close),balClass(as2.close));
    h += '</div>';

    h += '</div>'; // sum-grid
    h += '<div class="rp-dline"></div>';
    h += '</div>'; // summary
  }

  // Suspense items
  const susp = d.suspense||[];
  if(susp.length){
    h += '<div class="rp-suspense"><b>Suspense Items</b>';
    susp.forEach(s=>{
      h += '<div>'+esc(s.tdate)+' &nbsp; '+esc(s.iname)+' &nbsp; Qty:'+s.qty+' &nbsp; Wgt:'+nf(s.weight,3);
      if(s.part) h += ' &nbsp; Part:'+esc(s.part);
      h += '</div>';
    });
    h += '</div>';
  }

  page.innerHTML = h;

  // Summary bar
  document.getElementById('summary').innerHTML = [
    '<span>Smith: <b>'+esc(d.smithname)+'</b></span>',
    '<span>Entries: <b>'+nf(t.count,0)+'</b></span>',
    '<span>Issued: <b>'+nf(t.issuedwgt,3)+'</b></span>',
    '<span>Received: <b>'+nf(t.rcvdwgt,3)+'</b></span>',
    '<span>MC: <b>'+nf(t.mcharge)+'</b></span>',
    '<span>Paid: <b>'+nf(t.paidamt)+'</b></span>',
  ].join('');
}

function sumLine(label, val, suffix, cls){
  let v = '<span class="val'+(cls?' '+cls:'')+'">'+val;
  if(suffix) v += ' '+suffix;
  v += '</span>';
  return '<div class="rp-sum-line"><span class="lbl">'+label+' :</span>'+v+'</div>';
}

document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnSort').onclick = ()=> toast('Sort reset');
document.getElementById('btnExit').onclick = ()=> window.parent.postMessage({type:'goldapp:close-module-frame'}, '*');

// Re-render on checkbox toggle
['cbNoMcWstg','cbNoSummary','cbNoRcvdQty','cbNetwgtModel','cbPrintLine'].forEach(id=>{
  document.getElementById(id).onchange = ()=>{ if(reportData) render(); };
});
</script>
<script src="{{ asset('js/report-export.js') }}?v=7"></script>
<script>
ReportExport.init('btnSaveAs', exportHeaders, ()=>allRows,
  ()=>'goldsmith-ledger-'+document.getElementById('smithcode').value+'-'+document.getElementById('date1').value);
document.getElementById('btnExcel').onclick = function(){
  ReportExport.open(exportHeaders, ()=>allRows,
    ()=>'goldsmith-ledger-'+document.getElementById('smithcode').value+'-'+document.getElementById('date1').value);
  setTimeout(function(){
    const xlsCard = document.querySelector('[data-xtype="xls"]');
    if (xlsCard) xlsCard.click();
  }, 0);
};
document.getElementById('btnPdf').onclick = function(){
  ReportExport.open(exportHeaders, ()=>allRows,
    ()=>'goldsmith-ledger-'+document.getElementById('smithcode').value+'-'+document.getElementById('date1').value);
  setTimeout(function(){
    const pdfCard = document.querySelector('[data-xtype="pdf"]');
    if (pdfCard) pdfCard.click();
  }, 0);
};
</script>
</body>
</html>
