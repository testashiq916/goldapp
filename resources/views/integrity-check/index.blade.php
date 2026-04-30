
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Integrity Checking</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; font-size: 13px; background: #c0c0c0; padding: 8px; }
.toolbar { background: #c0c0c0; padding: 6px 8px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; border: 2px outset #fff; margin-bottom: 4px; }
.toolbar h2 { font-size: 14px; font-weight: 700; flex: 1; }
.toolbar button { padding: 4px 16px; font-weight: 700; border: 2px outset #fff; background: #c0c0c0; cursor: pointer; font-size: 13px; }
.toolbar button:active { border-style: inset; }
.toolbar button:disabled { color: #888; cursor: not-allowed; }
.result-area { background: #fff; border: 2px inset #fff; padding: 8px; min-height: 400px; overflow: auto; max-height: 76vh; font-family: 'Courier New', monospace; font-size: 12px; }
.result-area .ln { padding: 1px 4px; line-height: 1.5; }
.result-area .ln-error   { color: #cc0000; font-weight: 700; }
.result-area .ln-warning { color: #cc6600; font-weight: 700; }
.result-area .ln-message { color: #0066cc; }
.result-area .ln-ok      { color: #007700; font-weight: 700; }
.result-area .ln-section { color: #000; font-weight: 700; margin-top: 6px; }
.result-area .ln-info    { color: #555; }
.result-area .ln-divider { color: #000; border-top: 1px solid #ccc; margin-top: 4px; padding-top: 2px; }
.summary-bar { display: flex; gap: 20px; padding: 4px 8px; background: #e0e0e0; border: 1px solid #999; font-size: 12px; margin-top: 4px; }
.e { color: #cc0000; font-weight: 700; } .w { color: #cc6600; font-weight: 700; } .m { color: #0066cc; }
.idle-msg { padding: 40px; text-align: center; color: #888; }
@media print { .toolbar { display:none; } body { background:#fff; padding:0; } .result-area { border:none; max-height:unset; overflow:visible; } }
</style>@include('partials.print-layout-head')
</head>
<body>
<div class="toolbar">
    <h2>Integrity Checking</h2>
    <button id="btnCheck" onclick="runCheck()">Check Now</button>
    <button onclick="window.print()">Print</button>
</div>
<div class="result-area" id="resultArea"><div class="idle-msg">Click &ldquo;Check Now&rdquo; to run integrity checks.</div></div>
<div class="summary-bar" id="summaryBar" style="display:none">
    <span id="sumErr" class="e"></span><span id="sumWrn" class="w"></span><span id="sumMsg" class="m"></span>
</div>
<script>
var csrfToken = '{{ csrf_token() }}';
var apiUrl = '{{ url("/api/integrity-check/run") }}';
function runCheck() {
    var btn = document.getElementById('btnCheck');
    btn.disabled = true; btn.textContent = 'Checking...';
    var area = document.getElementById('resultArea');
    area.innerHTML = '<div class="ln ln-section">Running checks, please wait...</div>';
    document.getElementById('summaryBar').style.display = 'none';
    fetch(apiUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        btn.disabled = false; btn.textContent = 'Check Now';
        if (!d.ok) { area.innerHTML = '<div class="ln ln-error">Error: ' + escHtml(d.error||'unknown') + '</div>'; return; }
        var html = '';
        var typeMap = { error:'ln-error', warning:'ln-warning', message:'ln-message', ok:'ln-ok', section:'ln-section', info:'ln-info', divider:'ln-divider' };
        for (var i = 0; i < d.messages.length; i++) {
            var m = d.messages[i], cls = typeMap[m.type] || '', txt = m.text ? escHtml(m.text) : '&nbsp;';
            html += '<div class="ln ' + cls + '">' + txt + '</div>';
        }
        area.innerHTML = html; area.scrollTop = area.scrollHeight;
        var bar = document.getElementById('summaryBar');
        bar.style.display = 'flex';
        document.getElementById('sumErr').textContent = 'Errors: ' + d.errors;
        document.getElementById('sumWrn').textContent = 'Warnings: ' + d.warnings;
        document.getElementById('sumMsg').textContent = 'Messages: ' + d.msg_count;
    })
    .catch(function(e) { btn.disabled=false; btn.textContent='Check Now'; area.innerHTML='<div class="ln ln-error">Network error: '+escHtml(e.message)+'</div>'; });
}
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
</body></html>
