<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Regional Language Help</title>
    <style>
        body { font-family: "ML-Karthika", Arial, sans-serif; margin: 0; padding: 20px; background: #f0f0f0; }
        .wrap { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; border: 1px solid #d8d8d8; padding: 16px; }
        h3 { margin: 0 0 14px; color: #333; }
        textarea { width: 100%; height: 220px; border: 1px solid #cfd6df; border-radius: 6px; padding: 10px; font-family: "ML-Karthika", Arial, sans-serif; font-size: 16px; resize: vertical; }
        .actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px; }
        button { border: none; border-radius: 6px; cursor: pointer; padding: 9px 16px; font-weight: 700; }
        .ok { background: #4caf50; color: #fff; }
        .cancel { background: #f44336; color: #fff; }
    </style>
</head>
<body>
<div class="wrap">
    <h3>Enter Regional Text</h3>
    <textarea id="regionalText" placeholder="Type in Malayalam...">{{ $text }}</textarea>
    <div class="actions">
        <button type="button" class="ok" onclick="returnText()">OK</button>
        <button type="button" class="cancel" onclick="window.close()">Cancel</button>
    </div>
</div>

<script>
function returnText() {
    const text = document.getElementById('regionalText').value;
    if (window.opener && !window.opener.closed && typeof window.opener.receiveRegionalText === 'function') {
        window.opener.receiveRegionalText(text);
    }
    window.close();
}
</script>
</body>
</html>

