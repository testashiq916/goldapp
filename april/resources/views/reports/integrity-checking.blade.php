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
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-check{background:#3b82f6;color:#fff}.btn-check:hover{background:#2563eb}
.btn-check:disabled{background:#93c5fd;cursor:default}
.btn-clear{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-clear:hover{background:rgba(255,255,255,.25)}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}

.sub-header{background:#eff6ff;padding:5px 14px;font-size:11px;color:#1e40af;font-weight:600;border-bottom:1px solid #dbeafe;flex-shrink:0}

.results-wrap{flex:1;overflow:auto;padding:10px 14px}

.result-line{display:flex;align-items:flex-start;gap:8px;padding:4px 10px;border-radius:5px;margin-bottom:2px;font-size:12px;line-height:1.5}
.result-line .icon{font-size:13px;flex-shrink:0;margin-top:1px}
.result-line .text{flex:1}

.r-error{background:#fef2f2;color:#dc2626}
.r-warning{background:#fffbeb;color:#d97706}
.r-ok{background:#f0fdf4;color:#16a34a}
.r-info{background:#f0f9ff;color:#0369a1;font-weight:700;margin-top:6px}

.empty-state{text-align:center;padding:60px;color:#94a3b8;font-size:13px}
.empty-state b{display:block;font-size:16px;margin-bottom:8px;color:#64748b}

.spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:4px}
@keyframes spin{to{transform:rotate(360deg)}}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:16px;flex-wrap:wrap;font-size:11px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

@media print{.toolbar,.summary,.sub-header{display:none !important}.results-wrap{overflow:visible !important}body{height:auto;overflow:visible}}
</style>
@include('partials.print-layout-head')
</head>
<body>

<div class="toolbar">
  <h1>{{ $title }}</h1>
  <button class="btn btn-check" id="btnCheck">Check Now</button>
  <button class="btn btn-clear" id="btnClear">Clear</button>
  <button class="btn btn-out" id="btnPrint">Print</button>
  <button class="btn btn-out" id="btnExit">Exit</button>
</div>

<div class="sub-header" id="subHeader">Press <b>Check Now</b> to run integrity checks</div>

<div class="results-wrap" id="resultsWrap">
  <div class="empty-state">
    <b>No Results</b>
    Click <b>Check Now</b> to start integrity checking
  </div>
</div>

<div class="summary" id="summary"></div>

<script>
const SITE = @json(request()->root());

function runCheck(){
  const btn = document.getElementById('btnCheck');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>Checking...';
  document.getElementById('subHeader').textContent = 'Running checks…';
  document.getElementById('resultsWrap').innerHTML = '<div class="empty-state"><b>Running…</b>Please wait while checks complete</div>';
  document.getElementById('summary').innerHTML = '';

  fetch(SITE+'/api/integrity-checking/check')
    .then(r=>r.json())
    .then(d=>{
      btn.disabled = false;
      btn.textContent = 'Check Now';

      if(!d.ok){
        document.getElementById('subHeader').textContent = 'Check failed';
        document.getElementById('resultsWrap').innerHTML = '<div class="empty-state"><b>Error</b>'+(d.message||'Unknown error')+'</div>';
        return;
      }

      const results = d.results || [];
      let html = '';
      let errCount = 0, warnCount = 0, okCount = 0;

      results.forEach(r=>{
        let cls, icon;
        if(r.type === 'error'){   cls='r-error';   icon='✗'; errCount++; }
        else if(r.type === 'warning'){ cls='r-warning'; icon='⚠'; warnCount++; }
        else if(r.type === 'ok'){  cls='r-ok';    icon='✓'; okCount++; }
        else{                      cls='r-info';   icon='●'; }

        html += '<div class="result-line '+cls+'">';
        html += '<span class="icon">'+icon+'</span>';
        html += '<span class="text">'+esc(r.text)+'</span>';
        html += '</div>';
      });

      if(!html) html = '<div class="empty-state"><b>No Results</b>Check returned no messages</div>';

      document.getElementById('resultsWrap').innerHTML = html;

      const status = errCount > 0 ? 'ERRORS FOUND' : (warnCount > 0 ? 'WARNINGS FOUND' : 'All checks passed');
      document.getElementById('subHeader').textContent = 'Check complete — '+status;

      document.getElementById('summary').innerHTML =
        '<span>Errors: <b style="color:'+(errCount>0?'#dc2626':'#16a34a')+'">'+errCount+'</b></span>'+
        '<span>Warnings: <b style="color:'+(warnCount>0?'#d97706':'#16a34a')+'">'+warnCount+'</b></span>'+
        '<span>OK: <b>'+okCount+'</b></span>'+
        '<span>Total: <b>'+results.length+'</b></span>';
    })
    .catch(err=>{
      btn.disabled = false;
      btn.textContent = 'Check Now';
      document.getElementById('subHeader').textContent = 'Network error';
      document.getElementById('resultsWrap').innerHTML = '<div class="empty-state"><b>Network Error</b>Could not connect to server</div>';
    });
}

function esc(v){ return String(v??'').replace(/[&<>'"]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','\'':'&#39;','"':'&quot;'})[m]); }

document.getElementById('btnCheck').onclick = runCheck;
document.getElementById('btnClear').onclick = ()=>{
  document.getElementById('resultsWrap').innerHTML = '<div class="empty-state"><b>No Results</b>Click <b>Check Now</b> to start integrity checking</div>';
  document.getElementById('summary').innerHTML = '';
  document.getElementById('subHeader').textContent = 'Press <b>Check Now</b> to run integrity checks';
  document.getElementById('subHeader').innerHTML = 'Press <b>Check Now</b> to run integrity checks';
};
document.getElementById('btnPrint').onclick = ()=> window.print();
document.getElementById('btnExit').onclick = ()=>{ if(window.parent!==window) window.parent.postMessage({action:'closeModule'},'*'); else window.close(); };

document.addEventListener('keydown', e=>{
  if(e.key==='F5'||e.key==='Enter'){ e.preventDefault(); runCheck(); }
});
</script>
</body>
</html>
