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
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:11px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.grid-wrap{flex:1;overflow:auto}
table{width:100%;border-collapse:collapse;background:#fff;font-size:12px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:6px 10px;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2}
th.num{text-align:right}
td{padding:5px 10px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
tr.op-row td{background:#fffbeb;font-weight:600;border-bottom:2px solid #fde68a}
tr.total-row td{background:#eff6ff;font-weight:700;border-top:2px solid #bfdbfe}
tr.cl-row td{background:#f0fdf4;font-weight:700;border-top:1px solid #bbf7d0}
.pos{color:#16a34a}.neg{color:#dc2626}

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
  <div class="f-group"><span class="tb-lbl">From</span><input type="date" id="inpDate1" class="tb-input" value="{{ $date1 }}"></div>
  <div class="f-group"><span class="tb-lbl">To</span><input type="date" id="inpDate2" class="tb-input" value="{{ $date2 }}"></div>
  <div class="sep"></div>
  <button class="btn btn-show" id="btnShow">Show</button>
  <button type="button" class="btn btn-out" id="btnSaveAs">Save As</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="sub-header" id="subHeader"></div>

<div class="grid-wrap">
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th class="num">Inflow (Debited)</th>
        <th class="num">Outflow (Credited)</th>
        <th class="num">Cash in Hand</th>
      </tr>
    </thead>
    <tbody id="tbody"><tr><td colspan="4" style="text-align:center;padding:40px;color:#94a3b8">Press <b>Show</b> to load data</td></tr></tbody>
  </table>
</div>

<div class="summary" id="summary"></div>
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
  if(p.length===3) return p[2]+'/'+p[1]+'/'+p[0];
  return d;
}

function loadData(){
  const date1 = document.getElementById('inpDate1').value;
  const date2 = document.getElementById('inpDate2').value;

  const params = new URLSearchParams({date1, date2, rlevel: RLEVEL});
  document.getElementById('subHeader').textContent = 'Loading...';
  document.getElementById('tbody').innerHTML='<tr><td colspan="4" style="text-align:center;padding:30px;color:#94a3b8">Loading...</td></tr>';

  fetch(SITE+'/api/cash-flow-report/data?'+params.toString())
    .then(r=>r.json())
    .then(d=>{
      if(!d.ok){ toast(d.message||'Failed',false); return; }
      const rows = d.rows||[];
      const opBal = d.openingBal||0;
      const totals = d.totals||{};

      let html = '';

      // Opening balance row
      html += '<tr class="op-row">';
      html += '<td>Opening Balance</td>';
      html += '<td></td><td></td>';
      html += '<td class="num">'+nf(Math.abs(opBal))+'</td>';
      html += '</tr>';

      if(rows.length===0){
        html += '<tr><td colspan="4" style="text-align:center;padding:20px;color:#94a3b8">No transactions in this period</td></tr>';
      } else {
        rows.forEach(r=>{
          html += '<tr>';
          html += '<td>'+fmtDate(r.tdate)+'</td>';
          html += '<td class="num'+(r.inflow?' pos':'')+'">'+( r.inflow ? nf(r.inflow) : '')+'</td>';
          html += '<td class="num'+(r.outflow?' neg':'')+'">'+( r.outflow ? nf(r.outflow) : '')+'</td>';
          html += '<td class="num">'+nf(r.cashInHand)+'</td>';
          html += '</tr>';
        });
      }

      // Total row
      html += '<tr class="total-row">';
      html += '<td>Total ('+rows.length+' days)</td>';
      html += '<td class="num">'+nf(totals.inflow)+'</td>';
      html += '<td class="num">'+nf(totals.outflow)+'</td>';
      html += '<td></td>';
      html += '</tr>';

      // Closing balance row
      const lastRow = rows.length > 0 ? rows[rows.length-1] : null;
      const closingBal = lastRow ? lastRow.cashInHand : opBal;
      html += '<tr class="cl-row">';
      html += '<td>Closing Balance</td>';
      html += '<td></td><td></td>';
      html += '<td class="num">'+nf(Math.abs(closingBal))+'</td>';
      html += '</tr>';

      document.getElementById('tbody').innerHTML = html;

      document.getElementById('subHeader').textContent =
        'Cash Flow from '+fmtDate(date1)+' to '+fmtDate(date2)+' | '+rows.length+' days';

      document.getElementById('summary').innerHTML =
        '<span>Days: <b>'+rows.length+'</b></span>'+
        '<span>Opening: <b>'+nf(Math.abs(opBal))+'</b></span>'+
        '<span>Total Inflow: <b>'+nf(totals.inflow)+'</b></span>'+
        '<span>Total Outflow: <b>'+nf(totals.outflow)+'</b></span>'+
        '<span>Closing: <b>'+nf(Math.abs(closingBal))+'</b></span>';
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
  a.download = 'cash_flow_statement.csv';
  a.click();
}

document.getElementById('btnShow').onclick = loadData;
document.getElementById('btnPrint').onclick = () => window.print();
document.getElementById('btnSaveAs').onclick = saveAsCsv;
document.getElementById('btnExit').onclick = () => { if(window.parent!==window) window.parent.postMessage({action:'closeModule'},'*'); else window.close(); };

document.addEventListener('keydown', e=>{
  if(e.key==='F5'||e.key==='Enter'){ e.preventDefault(); loadData(); }
});
</script>
</body>
</html>
