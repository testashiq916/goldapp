<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>WhatsApp / SMS Log</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 1400px; margin: 14px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 18px; }
        h1 { margin: 0 0 14px; font-size: 22px; color: #173b63; }
        form.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; background: #f0f5ff !important; border-radius: 8px; padding: 10px; margin-bottom: 14px; }
        .field { display: flex; flex-direction: column; gap: 3px; }
        label { font-size: 12px; font-weight: 700; color: #1e3a5f; }
        input, select { height: 36px; border: 1px solid #c4d1e2; border-radius: 6px; padding: 0 10px; font-size: 13px; box-sizing: border-box; }
        button { height: 36px; border: none; border-radius: 6px; padding: 0 16px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #173b63; color: #fff; }
        .btn-ghost { background: #fff; color: #173b63; border: 1px solid #173b63; }
        .summary-box { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 14px; }
        .s-card { border: 1px solid #d8e2ef; border-radius: 8px; padding: 10px 14px; background: #f8fbff; }
        .s-card .lbl { font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; }
        .s-card .val { font-size: 20px; font-weight: 700; color: #173b63; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid #e5ecf5; padding: 7px 10px; }
        th { background: #edf4fc; font-size: 12px; font-weight: 700; color: #64748b; position: sticky; top: 0; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; max-height: 65vh; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .badge-sent { background: #dcfce7; color: #15803d; }
        .badge-failed { background: #fee2e2; color: #dc2626; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-wa { background: #dcfce7; color: #15803d; }
        .badge-sms { background: #dbeafe; color: #1e40af; }
        .msg-preview { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; color: #64748b; cursor: pointer; }
        .msg-preview:hover { white-space: normal; color: #1f2937; }
        .empty { padding: 30px; text-align: center; color: #94a3b8; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Message Log</h1>

    <form class="toolbar" onsubmit="loadLog(event)">
        <div class="field"><label>From</label><input type="date" id="d1" value="{{ date('Y-m-d') }}"></div>
        <div class="field"><label>To</label><input type="date" id="d2" value="{{ date('Y-m-d') }}"></div>
        <div class="field">
            <label>Channel</label>
            <select id="channel">
                <option value="all">All</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="sms">SMS</option>
            </select>
        </div>
        <div class="field">
            <label>Status</label>
            <select id="status">
                <option value="all">All</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
                <option value="pending">Pending</option>
            </select>
        </div>
        <div class="field">
            <label>Message Type</label>
            <select id="msg_type">
                <option value="all">All</option>
                <option value="bill">Bill</option>
                <option value="payment_reminder">Payment Reminder</option>
                <option value="repair_ready">Repair Ready</option>
                <option value="rate_broadcast">Rate Broadcast</option>
                <option value="scheme_reminder">Scheme Reminder</option>
                <option value="test">Test</option>
            </select>
        </div>
        <div class="field"><label>&nbsp;</label><button type="submit" class="btn-primary">Load</button></div>
        <div class="field"><label>&nbsp;</label>
            <a href="/whatsapp-gateway/settings"><button type="button" class="btn-ghost">Settings</button></a>
        </div>
    </form>

    <div id="summary-area"></div>

    <div class="table-wrap">
        <table id="log-table">
            <thead>
                <tr>
                    <th>Date/Time</th><th>Channel</th><th>To</th><th>Name</th>
                    <th>Type</th><th>Message</th><th>Provider</th><th>Status</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="8" class="empty">Click Load to view message log.</td></tr></tbody>
        </table>
    </div>
</div>

<script>
function esc(v) { const d=document.createElement('div');d.textContent=v==null?'':v;return d.innerHTML; }

function loadLog(e) {
    if (e) e.preventDefault();
    const d1 = document.getElementById('d1').value;
    const d2 = document.getElementById('d2').value;
    const ch = document.getElementById('channel').value;
    const st = document.getElementById('status').value;
    const mt = document.getElementById('msg_type').value;
    const tbody = document.querySelector('#log-table tbody');
    tbody.innerHTML = '<tr><td colspan="8" class="empty">Loading&hellip;</td></tr>';
    document.getElementById('summary-area').innerHTML = '';

    fetch(`/api/whatsapp/log?date1=${d1}&date2=${d2}&channel=${ch}&status=${st}&msg_type=${mt}`)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { tbody.innerHTML = `<tr><td colspan="8" class="empty">${esc(d.message || 'Error loading log.')}</td></tr>`; return; }
            const rows = d.rows || [];

            // Summary counts
            const sentWa  = rows.filter(r => r.channel === 'whatsapp' && r.status === 'sent').length;
            const sentSms = rows.filter(r => r.channel === 'sms' && r.status === 'sent').length;
            const failed  = rows.filter(r => r.status === 'failed').length;
            const pending = rows.filter(r => r.status === 'pending').length;

            document.getElementById('summary-area').innerHTML = `<div class="summary-box">
                <div class="s-card"><div class="lbl">Total</div><div class="val">${rows.length}</div></div>
                <div class="s-card"><div class="lbl">WA Sent</div><div class="val" style="color:#15803d;">${sentWa}</div></div>
                <div class="s-card"><div class="lbl">SMS Sent</div><div class="val" style="color:#1e40af;">${sentSms}</div></div>
                <div class="s-card"><div class="lbl">Failed</div><div class="val" style="color:#dc2626;">${failed}</div></div>
                <div class="s-card"><div class="lbl">Pending</div><div class="val" style="color:#92400e;">${pending}</div></div>
            </div>`;

            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="8" class="empty">No messages found.</td></tr>'; return; }

            tbody.innerHTML = rows.map(r => `<tr>
                <td style="font-size:12px;white-space:nowrap;">${r.sent_at || r.created_at || ''}</td>
                <td><span class="badge badge-${r.channel}">${r.channel}</span></td>
                <td>${esc(r.recipient)}</td>
                <td>${esc(r.recipient_name || '')}</td>
                <td style="font-size:12px;">${esc(r.message_type || '')}</td>
                <td><div class="msg-preview" title="${esc(r.message)}">${esc(r.message)}</div></td>
                <td style="font-size:12px;">${esc(r.provider || '')}</td>
                <td><span class="badge badge-${r.status}">${r.status}</span></td>
            </tr>`).join('');
        });
}

// Auto-load today's log
loadLog();
</script>
</body>
</html>
