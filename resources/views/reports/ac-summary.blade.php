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
.tb-input::placeholder{color:rgba(255,255,255,.4)}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:17px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}
.name-box{background:rgba(255,255,255,.1);color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;min-width:120px;display:inline-block}

.content{flex:1;overflow:auto;padding:0}

.info-bar{background:#fff;padding:8px 14px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;border-bottom:1px solid #e2e8f0}
.info-bar .lbl{font-size:9px;color:#64748b;text-transform:uppercase;font-weight:700;letter-spacing:.3px}
.info-bar .val{font-size:13px;font-weight:700;color:#1e293b}
.dr{color:#dc2626}.cr{color:#16a34a}
.side-tag{font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;margin-left:3px}
.side-dr{background:#fee2e2;color:#dc2626}
.side-cr{background:#dcfce7;color:#16a34a}

table{width:100%;border-collapse:collapse;background:#fff;font-size:18px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:5px 6px;font-size:15px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2}
th.num{text-align:right}
td{padding:4px 6px;border-bottom:1px solid #f1f5f9}
td.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
tr:hover td{background:#f8fafc}
tr.op-row td{background:#fffbeb;font-weight:600;border-bottom:2px solid #fde68a}
tr.total-row td{background:#eff6ff;font-weight:700;border-top:2px solid #bfdbfe}
tr.cl-row td{background:#f0fdf4;font-weight:700;border-top:1px solid #bbf7d0}
.wgt-note{background:#fefce8;padding:6px 14px;font-size:12px;font-weight:600;color:#854d0e;border-top:1px solid #fde68a}

.empty-msg{text-align:center;padding:40px;color:#94a3b8;font-size:12px}

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
  <div class="f-group"><span class="tb-lbl">A/c Code</span><input type="text" id="inpCode" class="tb-input" placeholder="Account Code" style="width:100px"></div>
  <span id="acName" class="name-box">&nbsp;</span>
  <div class="sep"></div>
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="inpDate1" class="tb-input" value="{{ $date1 }}"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="inpDate2" class="tb-input" value="{{ $date2 }}"></div>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="content" id="content">
  <div class="empty-msg">Enter an Account Code and press <b>Show</b></div>
</div>

<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const RLEVEL = {{ $rlevel }};

function nf(n){ return Number(n||0).toFixed(2); }
function esc(v){ return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
function toast(msg,ok){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(ok?'ok':'err');
  t.style.display='block'; setTimeout(()=>t.style.display='none',3000);
}
function fmtDate(d){
  if(!d) return '';
  const p = String(d).split('-');
  if(p.length===3) return p[2]+'/'+p[1]+'/'+p[0].slice(2);
  return d;
}
function sideTag(side){
  if(!side) return '';
  const cls = side==='Dr'?'side-dr':'side-cr';
  return '<span class="side-tag '+cls+'">'+side+'</span>';
}

function loadData(){
  const code = document.getElementById('inpCode').value.trim();
  if(!code){ toast('Enter an Account Code',false); return; }
  const date1 = document.getElementById('inpDate1').value;
  const date2 = document.getElementById('inpDate2').value;

  const params = new URLSearchParams({date1, date2, code, rlevel: RLEVEL});
  document.getElementById('content').innerHTML = '<div class="empty-msg">Loading...</div>';
  document.getElementById('acName').textContent = '...';

  fetch(SITE+'/api/ac-summary/data?'+params.toString())
    .then(r=>r.json())
    .then(d=>{
      if(!d.ok){ toast(d.message||'Failed',false); document.getElementById('acName').innerHTML='&nbsp;'; return; }

      document.getElementById('acName').textContent = d.name || d.code;
      const rows = d.rows||[];

      let html = '';

      // Info bar
      html += '<div class="info-bar">';
      html += '<div><div class="lbl">Account</div><div class="val">'+esc(d.code)+' - '+esc(d.name)+'</div></div>';
      html += '<div><div class="lbl">Period</div><div class="val">'+fmtDate(date1)+' to '+fmtDate(date2)+'</div></div>';
      html += '<div><div class="lbl">Transactions</div><div class="val">'+rows.length+'</div></div>';
      html += '</div>';

      // Table
      html += '<table><thead><tr>';
      html += '<th>Date</th><th>Description</th><th>Voucher No</th>';
      html += '<th class="num">Debit</th><th class="num">Credit</th>';
      html += '<th class="num">Balance</th><th></th>';
      html += '</tr></thead><tbody>';

      // Opening balance row
      const opAbs = Math.abs(d.openingBal);
      html += '<tr class="op-row">';
      html += '<td></td><td>Opening Balance</td><td></td>';
      html += '<td class="num">'+(d.opSide==='Dr'?nf(opAbs):'')+'</td>';
      html += '<td class="num">'+(d.opSide==='Cr'?nf(opAbs):'')+'</td>';
      html += '<td class="num">'+nf(opAbs)+'</td>';
      html += '<td>'+sideTag(d.opSide)+'</td>';
      html += '</tr>';

      if(rows.length===0){
        html += '<tr><td colspan="7" style="text-align:center;padding:20px;color:#94a3b8">No transactions in this period</td></tr>';
      } else {
        rows.forEach(r=>{
          html += '<tr>';
          html += '<td style="white-space:nowrap">'+fmtDate(r.tdate)+'</td>';
          html += '<td>'+esc(r.desc)+'</td>';
          html += '<td>'+esc(r.vchno)+'</td>';
          html += '<td class="num'+(r.debit?' dr':'')+'">'+( r.debit ? nf(r.debit) : '')+'</td>';
          html += '<td class="num'+(r.credit?' cr':'')+'">'+( r.credit ? nf(r.credit) : '')+'</td>';
          html += '<td class="num">'+nf(r.balance)+'</td>';
          html += '<td>'+sideTag(r.balside)+'</td>';
          html += '</tr>';
        });
      }

      // Total row
      html += '<tr class="total-row">';
      html += '<td colspan="3" style="text-align:right">Total</td>';
      html += '<td class="num">'+nf(d.totDebit)+'</td>';
      html += '<td class="num">'+nf(d.totCredit)+'</td>';
      html += '<td colspan="2"></td>';
      html += '</tr>';

      // Closing balance row
      const clAbs = Math.abs(d.closingBal);
      html += '<tr class="cl-row">';
      html += '<td colspan="3" style="text-align:right">Closing Balance</td>';
      html += '<td class="num">'+(d.clSide==='Dr'?nf(clAbs):'')+'</td>';
      html += '<td class="num">'+(d.clSide==='Cr'?nf(clAbs):'')+'</td>';
      html += '<td class="num">'+nf(clAbs)+'</td>';
      html += '<td>'+sideTag(d.clSide)+'</td>';
      html += '</tr>';

      html += '</tbody></table>';

      // Weight note
      if(d.wgtNote){
        html += '<div class="wgt-note">'+esc(d.wgtNote)+'</div>';
      }

      document.getElementById('content').innerHTML = html;
    })
    .catch(()=> toast('Network error',false));
}

function saveAsCsv(){
  const tbl = document.querySelector('table');
  if(!tbl){ toast('No data to export',false); return; }
  const trs = tbl.querySelectorAll('tr');
  let csv = '';
  trs.forEach(tr=>{
    const cells = tr.querySelectorAll('th,td');
    const vals = [];
    cells.forEach(c=> vals.push('"'+c.textContent.replace(/"/g,'""').trim()+'"'));
    csv += vals.join(',')+'\n';
  });
  const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'ac_summary.csv';
  a.click();
}

document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = () => window.print();
document.getElementById('btnSaveAs').onclick = saveAsCsv;
document.getElementById('btnExit').onclick = () => { if(window.parent!==window) window.parent.postMessage({action:'closeModule'},'*'); else window.close(); };

document.addEventListener('keydown', e=>{
  if(e.key==='F5'){ e.preventDefault(); loadData(); }
  if(e.key==='Enter'){
    const active = document.activeElement;
    if(active && (active.id==='inpCode'||active.id==='inpDate1'||active.id==='inpDate2')){
      e.preventDefault(); loadData();
    }
  }
});
</script>
</body>
</html>
