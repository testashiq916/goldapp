<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WhatsApp / SMS Gateway Settings</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 1100px; margin: 14px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 20px; }
        h1 { margin: 0 0 6px; font-size: 22px; color: #173b63; }
        .sub { color: #64748b; font-size: 13px; margin-bottom: 20px; }
        .tabs { display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 2px solid #e5ecf5; }
        .tab { padding: 8px 18px; cursor: pointer; border-radius: 6px 6px 0 0; font-size: 13px; font-weight: 700; color: #64748b; border: 1px solid transparent; border-bottom: none; }
        .tab.active { background: #fff; border-color: #d7dfeb; color: #173b63; margin-bottom: -2px; border-bottom: 2px solid #fff; }
        .panel { display: none; } .panel.active { display: block; }
        .section { margin-bottom: 22px; }
        .section-title { font-size: 13px; font-weight: 700; color: #375b84; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #e5ecf5; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
        .field { display: flex; flex-direction: column; gap: 4px; }
        label { font-size: 12px; font-weight: 700; color: #375b84; }
        input, select, textarea { height: 36px; border: 1px solid #c4d1e2; border-radius: 6px; padding: 0 10px; font-size: 13px; box-sizing: border-box; background: #fff; color: #1f2937; }
        textarea { height: 80px; padding: 8px 10px; resize: vertical; }
        .toggle-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .toggle { position: relative; width: 42px; height: 22px; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; inset: 0; background: #cbd5e1; border-radius: 22px; cursor: pointer; transition: .2s; }
        .slider:before { content: ''; position: absolute; width: 16px; height: 16px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s; }
        input:checked + .slider { background: #2a7a42; }
        input:checked + .slider:before { transform: translateX(20px); }
        .toggle-label { font-size: 13px; font-weight: 600; color: #1f2937; }
        button { height: 36px; padding: 0 18px; border: none; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #173b63; color: #fff; }
        .btn-success { background: #2a7a42; color: #fff; }
        .btn-outline { background: #fff; border: 1.5px solid #173b63; color: #173b63; }
        .provider-cards { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
        .provider-card { flex: 1; min-width: 120px; border: 2px solid #e5ecf5; border-radius: 8px; padding: 12px; cursor: pointer; text-align: center; font-size: 12px; font-weight: 700; color: #64748b; transition: .15s; }
        .provider-card.active { border-color: #173b63; background: #eff6ff; color: #173b63; }
        .provider-fields { display: none; }
        .provider-fields.show { display: block; }
        .alert { padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 12px; }
        .alert-success { background: #effaf1; color: #1f5e2e; border: 1px solid #b7dbbf; }
        .alert-error { background: #fff1f2; color: #7f1d1d; border: 1px solid #fca5a5; }
        .test-row { display: flex; gap: 8px; align-items: end; }
        .test-row .field { flex: 1; }
        .log-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        .log-table th, .log-table td { border-bottom: 1px solid #e5ecf5; padding: 7px 10px; text-align: left; }
        .log-table th { background: #f1f5f9; font-size: 12px; font-weight: 700; color: #64748b; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-sent { background: #d1fae5; color: #065f46; }
        .badge-failed { background: #fee2e2; color: #7f1d1d; }
        .auto-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .auto-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid #e5ecf5; border-radius: 6px; }
        .auto-item label { font-size: 13px; font-weight: 600; color: #1f2937; cursor: pointer; margin: 0; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>&#128241; WhatsApp &amp; SMS Gateway</h1>
    <div class="sub">Configure messaging providers, templates, and auto-send triggers for customer communication.</div>

    <div id="statusMsg"></div>

    <div class="tabs">
        <div class="tab active" onclick="switchTab('settings')">Settings</div>
        <div class="tab" onclick="switchTab('test')">Test Send</div>
        <div class="tab" onclick="switchTab('auto')">Auto Triggers</div>
        <div class="tab" onclick="switchTab('log')">Message Log</div>
    </div>

    <!-- SETTINGS TAB -->
    <div class="panel active" id="panelSettings">
        <div class="section">
            <div class="section-title">WhatsApp</div>
            <div class="toggle-row">
                <label class="toggle"><input type="checkbox" id="WA_ENABLED" {{ ($settings['WA_ENABLED'] ?? '') === 'Y' ? 'checked' : '' }}><span class="slider"></span></label>
                <span class="toggle-label">Enable WhatsApp Messaging</span>
            </div>
            <div class="section-title" style="margin-top:12px;">WhatsApp Provider</div>
            <div class="provider-cards">
                <div class="provider-card {{ ($settings['WA_PROVIDER'] ?? 'fast2sms') === 'fast2sms' ? 'active' : '' }}" onclick="selectProvider('wa','fast2sms')">Fast2SMS</div>
                <div class="provider-card {{ ($settings['WA_PROVIDER'] ?? '') === 'wati' ? 'active' : '' }}" onclick="selectProvider('wa','wati')">WATI</div>
                <div class="provider-card {{ ($settings['WA_PROVIDER'] ?? '') === 'twilio' ? 'active' : '' }}" onclick="selectProvider('wa','twilio')">Twilio</div>
                <div class="provider-card {{ ($settings['WA_PROVIDER'] ?? '') === 'msg91' ? 'active' : '' }}" onclick="selectProvider('wa','msg91')">MSG91</div>
                <div class="provider-card {{ ($settings['WA_PROVIDER'] ?? '') === 'webhook' ? 'active' : '' }}" onclick="selectProvider('wa','webhook')">Webhook</div>
            </div>
            <input type="hidden" id="WA_PROVIDER" value="{{ $settings['WA_PROVIDER'] ?? 'fast2sms' }}">

            <div class="provider-fields {{ in_array($settings['WA_PROVIDER'] ?? 'fast2sms', ['fast2sms','']) ? 'show' : '' }}" id="wa-fast2sms">
                <div class="field" style="max-width:500px;"><label>Fast2SMS API Key</label><input id="FAST2SMS_KEY" value="{{ $settings['FAST2SMS_KEY'] ?? '' }}" placeholder="Enter Fast2SMS API key"></div>
            </div>
            <div class="provider-fields {{ ($settings['WA_PROVIDER'] ?? '') === 'wati' ? 'show' : '' }}" id="wa-wati">
                <div class="grid2"><div class="field"><label>WATI API URL</label><input id="WATI_API_URL" value="{{ $settings['WATI_API_URL'] ?? '' }}" placeholder="https://live-server-xxx.wati.io"></div>
                <div class="field"><label>WATI API Token</label><input id="WATI_TOKEN" type="password" value="{{ $settings['WATI_TOKEN'] ?? '' }}"></div></div>
            </div>
            <div class="provider-fields {{ ($settings['WA_PROVIDER'] ?? '') === 'twilio' ? 'show' : '' }}" id="wa-twilio">
                <div class="grid3">
                    <div class="field"><label>Account SID</label><input id="TWILIO_SID" value="{{ $settings['TWILIO_SID'] ?? '' }}"></div>
                    <div class="field"><label>Auth Token</label><input id="TWILIO_TOKEN" type="password" value="{{ $settings['TWILIO_TOKEN'] ?? '' }}"></div>
                    <div class="field"><label>From Number</label><input id="TWILIO_FROM" value="{{ $settings['TWILIO_FROM'] ?? '' }}" placeholder="+1234567890"></div>
                </div>
            </div>
            <div class="provider-fields {{ ($settings['WA_PROVIDER'] ?? '') === 'msg91' ? 'show' : '' }}" id="wa-msg91">
                <div class="grid3">
                    <div class="field"><label>MSG91 Auth Key</label><input id="MSG91_KEY" value="{{ $settings['MSG91_KEY'] ?? '' }}"></div>
                    <div class="field"><label>Sender ID</label><input id="MSG91_SENDER" value="{{ $settings['MSG91_SENDER'] ?? '' }}"></div>
                    <div class="field"><label>Template ID</label><input id="MSG91_TEMPLATE" value="{{ $settings['MSG91_TEMPLATE'] ?? '' }}"></div>
                </div>
            </div>
            <div class="provider-fields {{ ($settings['WA_PROVIDER'] ?? '') === 'webhook' ? 'show' : '' }}" id="wa-webhook">
                <div class="field" style="max-width:500px;"><label>Webhook URL (POST)</label><input id="WEBHOOK_URL" value="{{ $settings['WEBHOOK_URL'] ?? '' }}" placeholder="https://yourserver.com/webhook"></div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">SMS</div>
            <div class="toggle-row">
                <label class="toggle"><input type="checkbox" id="SMS_ENABLED" {{ ($settings['SMS_ENABLED'] ?? '') === 'Y' ? 'checked' : '' }}><span class="slider"></span></label>
                <span class="toggle-label">Enable SMS Messaging</span>
            </div>
            <div class="field" style="max-width:260px;margin-top:10px;"><label>SMS Provider</label>
                <select id="SMS_PROVIDER">
                    @foreach(['fast2sms'=>'Fast2SMS','msg91'=>'MSG91','webhook'=>'Webhook'] as $v=>$l)
                        <option value="{{ $v }}" {{ ($settings['SMS_PROVIDER'] ?? 'fast2sms') === $v ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <button class="btn-primary" onclick="saveSettings()">&#128190; Save Settings</button>
    </div>

    <!-- TEST TAB -->
    <div class="panel" id="panelTest">
        <div class="section">
            <div class="section-title">Send Test Message</div>
            <div class="test-row">
                <div class="field"><label>Mobile Number</label><input id="testMobile" placeholder="10-digit mobile" maxlength="10"></div>
                <div class="field"><label>Channel</label><select id="testChannel"><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option></select></div>
                <button class="btn-success" onclick="sendTest()" style="flex-shrink:0;">Send Test</button>
            </div>
            <div class="field" style="margin-top:12px;"><label>Message</label>
                <textarea id="testMessage" rows="4">Test message from GoldApp WhatsApp Gateway. If you receive this, the setup is working correctly!</textarea>
            </div>
        </div>
    </div>

    <!-- AUTO TRIGGERS TAB -->
    <div class="panel" id="panelAuto">
        <div class="section">
            <div class="section-title">Auto-send WhatsApp on Events</div>
            <p style="color:#64748b;font-size:13px;margin:0 0 14px;">When enabled, messages are automatically sent to the customer when the event occurs.</p>
            <div class="auto-grid">
                <div class="auto-item">
                    <label class="toggle"><input type="checkbox" id="WA_AUTO_SALE" {{ ($settings['WA_AUTO_SALE'] ?? '') === 'Y' ? 'checked' : '' }}><span class="slider"></span></label>
                    <label for="WA_AUTO_SALE">&#129534; Sale Bill Created — send bill summary</label>
                </div>
                <div class="auto-item">
                    <label class="toggle"><input type="checkbox" id="WA_AUTO_PAYMENT_DUE" {{ ($settings['WA_AUTO_PAYMENT_DUE'] ?? '') === 'Y' ? 'checked' : '' }}><span class="slider"></span></label>
                    <label for="WA_AUTO_PAYMENT_DUE">&#9888; Payment Due Reminder</label>
                </div>
                <div class="auto-item">
                    <label class="toggle"><input type="checkbox" id="WA_AUTO_REPAIR_READY" {{ ($settings['WA_AUTO_REPAIR_READY'] ?? '') === 'Y' ? 'checked' : '' }}><span class="slider"></span></label>
                    <label for="WA_AUTO_REPAIR_READY">&#128295; Repair Ready for Collection</label>
                </div>
                <div class="auto-item">
                    <label class="toggle"><input type="checkbox" id="WA_AUTO_SCHEME" {{ ($settings['WA_AUTO_SCHEME'] ?? '') === 'Y' ? 'checked' : '' }}><span class="slider"></span></label>
                    <label for="WA_AUTO_SCHEME">&#128197; Scheme Installment Due</label>
                </div>
                <div class="auto-item">
                    <label class="toggle"><input type="checkbox" id="WA_AUTO_GOLD_RATE" {{ ($settings['WA_AUTO_GOLD_RATE'] ?? '') === 'Y' ? 'checked' : '' }}><span class="slider"></span></label>
                    <label for="WA_AUTO_GOLD_RATE">&#127881; Daily Gold Rate Broadcast</label>
                </div>
            </div>
            <button class="btn-primary" style="margin-top:16px;" onclick="saveSettings()">&#128190; Save Triggers</button>
        </div>
    </div>

    <!-- LOG TAB -->
    <div class="panel" id="panelLog">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <span style="font-size:13px;font-weight:700;color:#375b84;">Recent Messages (last 200)</span>
            <button class="btn-outline" onclick="loadLog()">&#8635; Refresh</button>
        </div>
        <table class="log-table">
            <thead><tr><th>Date/Time</th><th>Channel</th><th>Mobile</th><th>Type</th><th>Status</th><th>Message</th></tr></thead>
            <tbody id="logBody"><tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:20px;">Loading...</td></tr></tbody>
        </table>
    </div>
</div>

<script>
function switchTab(t) {
    document.querySelectorAll('.tab').forEach((el, i) => {
        const names = ['settings','test','auto','log'];
        el.classList.toggle('active', names[i] === t);
    });
    document.querySelectorAll('.panel').forEach((el, i) => {
        const names = ['panelSettings','panelTest','panelAuto','panelLog'];
        el.classList.toggle('active', ['panelSettings','panelTest','panelAuto','panelLog'][i] === 'panel' + t.charAt(0).toUpperCase() + t.slice(1));
    });
    if (t === 'log') loadLog();
}

function selectProvider(ch, provider) {
    document.getElementById('WA_PROVIDER').value = provider;
    document.querySelectorAll('.provider-card').forEach(c => c.classList.remove('active'));
    event.target.classList.add('active');
    ['fast2sms','wati','twilio','msg91','webhook'].forEach(p => {
        const el = document.getElementById('wa-' + p);
        if (el) el.classList.toggle('show', p === provider);
    });
}

function boolVal(id) {
    const el = document.getElementById(id);
    if (!el) return 'N';
    return (el.type === 'checkbox' ? el.checked : el.value.trim().toUpperCase() === 'Y') ? 'Y' : 'N';
}
function val(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : '';
}

function saveSettings() {
    const data = {
        WA_ENABLED: boolVal('WA_ENABLED'), WA_PROVIDER: val('WA_PROVIDER'),
        SMS_ENABLED: boolVal('SMS_ENABLED'), SMS_PROVIDER: val('SMS_PROVIDER'),
        FAST2SMS_KEY: val('FAST2SMS_KEY'), WATI_API_URL: val('WATI_API_URL'), WATI_TOKEN: val('WATI_TOKEN'),
        TWILIO_SID: val('TWILIO_SID'), TWILIO_TOKEN: val('TWILIO_TOKEN'), TWILIO_FROM: val('TWILIO_FROM'),
        MSG91_KEY: val('MSG91_KEY'), MSG91_SENDER: val('MSG91_SENDER'), MSG91_TEMPLATE: val('MSG91_TEMPLATE'),
        WEBHOOK_URL: val('WEBHOOK_URL'),
        WA_AUTO_SALE: boolVal('WA_AUTO_SALE'), WA_AUTO_PAYMENT_DUE: boolVal('WA_AUTO_PAYMENT_DUE'),
        WA_AUTO_REPAIR_READY: boolVal('WA_AUTO_REPAIR_READY'), WA_AUTO_SCHEME: boolVal('WA_AUTO_SCHEME'),
        WA_AUTO_GOLD_RATE: boolVal('WA_AUTO_GOLD_RATE'),
    };
    fetch('/api/whatsapp/save-settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        showMsg(d.ok ? 'success' : 'error', d.message);
    });
}

function sendTest() {
    const mobile  = document.getElementById('testMobile').value.trim();
    const channel = document.getElementById('testChannel').value;
    const message = document.getElementById('testMessage').value.trim();
    if (!mobile) { showMsg('error', 'Enter mobile number'); return; }
    fetch('/api/whatsapp/test-send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ mobile, channel, message })
    }).then(r => r.json()).then(d => showMsg(d.ok ? 'success' : 'error', d.message));
}

function loadLog() {
    fetch('/api/whatsapp/log-data').then(r => r.json()).then(d => {
        const tb = document.getElementById('logBody');
        if (!d.ok || !d.rows.length) {
            tb.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:20px;">No messages yet</td></tr>';
            return;
        }
        tb.innerHTML = d.rows.map(r => `<tr>
            <td style="white-space:nowrap">${(r.sent_at||'').substring(0,16)}</td>
            <td>${r.channel||''}</td>
            <td>${r.recipient||''}</td>
            <td>${r.message_type||''}</td>
            <td><span class="badge badge-${r.status}">${r.status}</span></td>
            <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${(r.message||'').replace(/"/g,'&quot;')}">${r.message||''}</td>
        </tr>`).join('');
    });
}

function showMsg(type, msg) {
    const el = document.getElementById('statusMsg');
    el.innerHTML = `<div class="alert alert-${type === 'success' ? 'success' : 'error'}">${msg}</div>`;
    setTimeout(() => el.innerHTML = '', 4000);
}
</script>
</body>
</html>
