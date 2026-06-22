<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cash Balance</title>
    <style>
        :root{--bg:#eef3f8;--card:#ffffff;--line:#d7e2ee;--text:#18324b;--muted:#6b7a8d;--accent:#0c8b87;--shadow:0 20px 50px rgba(15,23,42,.10)}
        *{box-sizing:border-box} body{margin:0;font-family:Segoe UI,Tahoma,Arial,sans-serif;background:linear-gradient(180deg,#f7fafc 0%,#e8eef5 100%);color:var(--text)}
        .page{min-height:100vh;display:grid;place-items:center;padding:24px}
        .card{width:min(820px,100%);background:var(--card);border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow);padding:28px 32px}
        .head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}
        .head h1{margin:0;font-size:20px;font-weight:700}
        .chip{padding:6px 12px;border-radius:999px;background:#f2f7fb;border:1px solid var(--line);font-size:12px;color:var(--muted)}
        .label{font-size:14px;font-weight:700;color:var(--muted);margin-bottom:10px}
        .balance{font-size:56px;line-height:1;font-weight:800;letter-spacing:-1px;color:var(--accent);text-align:center;padding:28px 16px;border-radius:20px;background:linear-gradient(180deg,#fbfeff 0%,#f1f8fb 100%);border:1px solid #dbe8f2}
        .foot{display:flex;justify-content:center;margin-top:24px}
        .btn{height:42px;padding:0 22px;border-radius:12px;border:1px solid #cbd7e4;background:#fff;color:var(--text);font-size:14px;font-weight:700;cursor:pointer}
        .btn:hover{background:#f8fbff}
    </style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="page">
    <div class="card">
        <div class="head">
            <h1>Cash Balance</h1>
            <span class="chip">Control {{ $control }}</span>
        </div>
        <div class="label">Cash Balance :</div>
        <div class="balance">{{ number_format($displayCashBalance ?? abs($cashBalance), 2) }} {{ $displayCashSide ?? (($cashBalance ?? 0) < 0 ? 'Cr' : 'Dr') }}</div>
        <div class="foot">
            <button class="btn" type="button" onclick="closeFrame()">OK</button>
        </div>
    </div>
</div>
<script>
function closeFrame() {
    if (window.top && window.top !== window) {
        window.top.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        return;
    }
    window.close();
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === 'Escape') {
        e.preventDefault();
        closeFrame();
    }
});
</script>
</body>
</html>
