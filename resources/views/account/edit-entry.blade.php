<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Edit A/c entry</title>
    <style>
        body { margin: 0; background: #edf3fb; font-family: "Segoe UI", Tahoma, Arial, sans-serif; color: #1d3248; }
        .win { width: min(680px, calc(100vw - 24px)); margin: 18px auto; background: #fff; border: 1px solid #cedbec; border-radius: 10px; overflow: hidden; box-shadow: 0 14px 34px rgba(12, 35, 62, 0.12); }
        .head { padding: 12px 14px; background: linear-gradient(100deg, #155f95, #1d7fbe); color: #fff; font-size: 22px; font-weight: 800; }
        .body { padding: 14px; }
        .row { display: grid; grid-template-columns: 160px 1fr auto; gap: 8px; align-items: center; }
        label { text-align: right; font-weight: 700; font-size: 12px; color: #5c7592; text-transform: uppercase; letter-spacing: .4px; }
        input { height: 34px; border: 1px solid #bfd0e6; border-radius: 7px; padding: 0 8px; font-size: 14px; text-transform: uppercase; }
        input:focus { outline: none; border-color: #4f96da; box-shadow: 0 0 0 3px rgba(79,150,218,.14); }
        button { height: 34px; min-width: 90px; border: 1px solid #bfd0e6; border-radius: 8px; background: #fff; font-weight: 700; cursor: pointer; }
        #btnOk { background: linear-gradient(100deg,#1f9957,#178447); border-color:#157a41; color:#fff; }
        #btnExit { background: linear-gradient(100deg,#b24444,#9d2f2f); border-color:#8f2d2d; color:#fff; }
        .btns { margin-top: 12px; display: flex; gap: 8px; justify-content: center; }
        .list { margin-top: 12px; border: 1px solid #d9e4f2; border-radius: 8px; max-height: 340px; overflow: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 7px 8px; border-bottom: 1px solid #e8eef8; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2a4f76; }
        tr { cursor: pointer; }
        tr:hover td { background: #f4f9ff; }
        .status { margin-top: 10px; min-height: 16px; text-align: center; font-weight: 700; }
        .ok { color: #167742; } .err { color: #a32d2d; }
    </style>
</head>
<body>
<div class="win">
    <div class="head">Edit A/c entry</div>
    <div class="body">
        <div class="row">
            <label>Voucher No :</label>
            <input id="vchno" autofocus>
            <button id="btnHelp" type="button">Help</button>
        </div>
        <div class="btns">
            <button id="btnOk" type="button">OK</button>
            <button id="btnExit" type="button">Exit</button>
        </div>
        <div class="list" id="helpPanel" style="display:none;">
            <table>
                <thead><tr><th style="width:130px;">Voucher</th><th>Particulars</th></tr></thead>
                <tbody id="helpRows"></tbody>
            </table>
        </div>
        <div id="status" class="status"></div>
    </div>
</div>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const BASE = @json(url('/api/accounts/edit-entry'));
    const $ = id => document.getElementById(id);

    function setStatus(msg, ok) {
        const el = $('status');
        el.textContent = msg || '';
        el.className = 'status ' + (ok ? 'ok' : 'err');
    }

    async function api(payload) {
        const res = await fetch(BASE, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
        });
        const d = await res.json();
        if (!res.ok || d.success === false) throw new Error(d.error || 'Request failed');
        return d;
    }

    async function resolveAndOpen() {
        const v = ($('vchno').value || '').trim().toUpperCase();
        $('vchno').value = v;
        if (!v) { setStatus('Enter voucher no', false); return; }
        try {
            const d = await api({ action: 'resolve', vchno: v });
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'goldapp:open-module-url', url: d.url, closeCurrent: true }, '*');
            } else {
                window.location.href = d.url;
            }
        } catch (e) {
            setStatus(e.message, false);
        }
    }

    async function openHelp() {
        try {
            const d = await api({ action: 'list', search: ($('vchno').value || '').trim() });
            const rows = d.data || [];
            $('helpRows').innerHTML = rows.map(r => `<tr data-v="${String(r.vchno || '').replace(/"/g,'&quot;')}"><td>${String(r.vchno || '').replace(/</g,'&lt;')}</td><td>${String(r.particular || '').replace(/</g,'&lt;')}</td></tr>`).join('');
            $('helpPanel').style.display = 'block';
            Array.from($('helpRows').querySelectorAll('tr')).forEach(tr => {
                tr.addEventListener('click', () => {
                    $('vchno').value = tr.dataset.v || '';
                    $('helpPanel').style.display = 'none';
                    resolveAndOpen();
                });
            });
        } catch (e) {
            setStatus(e.message, false);
        }
    }

    $('btnOk').addEventListener('click', resolveAndOpen);
    $('btnHelp').addEventListener('click', openHelp);
    $('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        else window.close();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'F1') { e.preventDefault(); openHelp(); }
        if (e.key === 'Enter') { e.preventDefault(); resolveAndOpen(); }
    });
})();
</script>
</body>
</html>

