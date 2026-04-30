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
.tb-input{height:28px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 6px;font-size:11px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.tb-input::placeholder{color:rgba(255,255,255,.4)}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}
.name-box{background:rgba(255,255,255,.1);color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;min-width:120px;display:inline-block}

.content{flex:1;overflow:auto;padding:10px 14px}

.info-bar{background:#fff;border-radius:8px;padding:10px 16px;margin-bottom:10px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.info-bar .lbl{font-size:10px;color:#64748b;text-transform:uppercase;font-weight:700;letter-spacing:.3px}
.info-bar .val{font-size:14px;font-weight:700;color:#1e293b}
.info-bar .val.pos{color:#16a34a}
.info-bar .val.neg{color:#dc2626}
.info-bar .status{font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px}
.status-give{background:#fee2e2;color:#dc2626}
.status-recv{background:#dcfce7;color:#16a34a}

.section{background:#fff;border-radius:8px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden}
.section-head{background:#f1f5f9;padding:8px 14px;font-size:12px;font-weight:700;color:#1e40af;border-bottom:2px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center}
.section-head .cnt{font-size:10px;color:#64748b;font-weight:600}

table{width:100%;border-collapse:collapse;font-size:11px}
th{background:#f8fafc;padding:5px 6px;font-size:9px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;border-bottom:1px solid #e2e8f0}
th.num{text-align:right}
td{padding:4px 6px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
tr.total-row td{background:#eff6ff;font-weight:700;border-top:2px solid #bfdbfe}

.empty-msg{text-align:center;padding:40px;color:#94a3b8;font-size:12px}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:12px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

@media print{
  .toolbar{display:none !important}
  .content{overflow:visible !important;padding:0}
  body{height:auto;overflow:visible}
  .section{box-shadow:none;break-inside:avoid}
}
</style>
@include('partials.print-layout-head')
</head>
<body>

<div class="toolbar">
  <h1>{{ $title }}</h1>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="inpDate1" class="tb-input" value="{{ $date1 }}"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="inpDate2" class="tb-input" value="{{ $date2 }}"></div>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">Code</span><input type="text" id="inpCode" class="tb-input" placeholder="Jeweller Code" style="width:100px"></div>
  <span id="jewlName" class="name-box">&nbsp;</span>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="content" id="content">
  <div class="empty-msg">Enter a Jeweller Code and press <b>Show</b></div>
</div>

<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const RLEVEL = {{ $rlevel }};

function nf(n,d=2){ return Number(n||0).toFixed(d); }
function nf3(n){ return Number(n||0).toFixed(3); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}

function fmtDate(d){
  if(!d) return '';
  const p = String(d).split('-');
  if(p.length===3) return p[2]+'/'+p[1]+'/'+p[0];
  return d;
}

function renderSection(sec){
  const rows = sec.rows||[];
  const tot = sec.totals||{};
  let html = '<div class="section">';
  html += '<div class="section-head"><span>'+esc(sec.title)+'</span><span class="cnt">'+rows.length+' items</span></div>';
  html += '<table><thead><tr>';
  html += '<th>Bill No</th><th>Date</th><th>Item Code</th><th>Item Name</th>';
  html += '<th class="num">Qty</th><th class="num">Weight</th><th class="num">Rate</th>';
  html += '<th class="num">Less%</th><th class="num">LessWgt</th>';
  html += '<th class="num">MCharge</th><th class="num">St.Price</th><th class="num">Amount</th>';
  html += '</tr></thead><tbody>';

  if(rows.length===0){
    html += '<tr><td colspan="12" style="text-align:center;padding:20px;color:#94a3b8">No items</td></tr>';
  } else {
    rows.forEach(r=>{
      html += '<tr>';
      html += '<td>'+esc(r.billno)+'</td>';
      html += '<td>'+fmtDate(r.tdate)+'</td>';
      html += '<td>'+esc(r.itemcode)+'</td>';
      html += '<td>'+esc(r.itemname)+'</td>';
      html += '<td class="num">'+r.qty+'</td>';
      html += '<td class="num">'+nf3(r.weight)+'</td>';
      html += '<td class="num">'+nf(r.rate)+'</td>';
      html += '<td class="num">'+nf(r.lessperc)+'</td>';
      html += '<td class="num">'+nf3(r.lesswgt)+'</td>';
      html += '<td class="num">'+nf(r.mcharge)+'</td>';
      html += '<td class="num">'+nf(r.stprice)+'</td>';
      html += '<td class="num">'+nf(r.amount)+'</td>';
      html += '</tr>';
    });
    html += '<tr class="total-row">';
    html += '<td colspan="4" style="text-align:right">Total</td>';
    html += '<td class="num">'+tot.qty+'</td>';
    html += '<td class="num">'+nf3(tot.weight)+'</td>';
    html += '<td colspan="5"></td>';
    html += '<td class="num">'+nf(tot.amount)+'</td>';
    html += '</tr>';
  }

  html += '</tbody></table></div>';
  return html;
}

function loadData(){
  const code = document.getElementById('inpCode').value.trim();
  if(!code){ toast('Enter a Jeweller Code',false); return; }
  const date1 = document.getElementById('inpDate1').value;
  const date2 = document.getElementById('inpDate2').value;

  const params = new URLSearchParams({date1, date2, code, rlevel: RLEVEL});
  document.getElementById('content').innerHTML = '<div class="empty-msg">Loading...</div>';
  document.getElementById('jewlName').textContent = '...';

  fetch(SITE+'/api/jewel-summary/data?'+params.toString())
    .then(r=>r.json())
    .then(d=>{
      if(!d.ok){ toast(d.message||'Failed',false); document.getElementById('jewlName').innerHTML='&nbsp;'; return; }

      document.getElementById('jewlName').textContent = d.name || code;

      let html = '';

      // Info bar with closing balance
      html += '<div class="info-bar">';
      html += '<div><div class="lbl">Jeweller</div><div class="val">'+esc(d.name||code)+'</div></div>';
      html += '<div><div class="lbl">Code</div><div class="val">'+esc(d.code)+'</div></div>';
      html += '<div><div class="lbl">Period</div><div class="val">'+fmtDate(date1)+' - '+fmtDate(date2)+'</div></div>';
      const balCls = d.clbalance < 0 ? 'neg' : (d.clbalance > 0 ? 'pos' : '');
      html += '<div><div class="lbl">Closing Balance</div><div class="val '+balCls+'">'+nf(Math.abs(d.clbalance))+'</div></div>';
      if(d.balstatus){
        const stCls = d.balstatus==='To Give'?'status-give':'status-recv';
        html += '<div><span class="status '+stCls+'">'+esc(d.balstatus)+'</span></div>';
      }
      html += '</div>';

      // Sections
      const sections = d.sections||[];
      if(sections.length===0){
        html += '<div class="empty-msg">No transactions found for this jeweller in the selected period</div>';
      } else {
        sections.forEach(sec=>{ html += renderSection(sec); });
      }

      document.getElementById('content').innerHTML = html;
    })
    .catch(()=> toast('Network error',false));
}

document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = () => window.print();
document.getElementById('btnExit').onclick = () => { if(window.parent!==window) window.parent.postMessage({action:'closeModule'},'*'); else window.close(); };

document.addEventListener('keydown', e=>{
  if(e.key==='F5'||e.key==='Enter'){ e.preventDefault(); loadData(); }
});
</script>
</body>
</html>
