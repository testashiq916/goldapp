<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Digital Scale Integration</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 900px; margin: 14px auto; }
        .card { background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 22px; margin-bottom: 14px; }
        h1 { margin: 0 0 6px; font-size: 22px; color: #173b63; }
        .subtitle { font-size: 13px; color: #64748b; margin-bottom: 20px; }
        h2 { margin: 0 0 14px; font-size: 16px; color: #173b63; border-bottom: 2px solid #e5ecf5; padding-bottom: 8px; }
        label { font-size: 12px; font-weight: 700; color: #1e3a5f; display: block; margin-bottom: 4px; }
        input, select { height: 38px; border: 1px solid #c4d1e2; border-radius: 6px; padding: 0 10px; font-size: 13px; box-sizing: border-box; width: 100%; }
        button { height: 38px; border: none; border-radius: 6px; padding: 0 18px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #173b63; color: #fff; }
        .btn-success { background: #15803d; color: #fff; }
        .btn-ghost { background: #fff; color: #173b63; border: 1px solid #173b63; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
        .field { margin-bottom: 12px; }
        .toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5ecf5; }
        .toggle-label { font-size: 14px; font-weight: 600; }
        .toggle-desc { font-size: 12px; color: #64748b; margin-top: 2px; }
        .toggle-switch { position: relative; width: 50px; height: 26px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; inset: 0; background: #cbd5e1; border-radius: 26px; cursor: pointer; transition: .3s; }
        .toggle-slider:before { content: ''; position: absolute; left: 3px; top: 3px; width: 20px; height: 20px; background: #fff; border-radius: 50%; transition: .3s; }
        input:checked + .toggle-slider { background: #173b63; }
        input:checked + .toggle-slider:before { transform: translateX(24px); }
        .weight-display { background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 24px; text-align: center; margin-top: 14px; }
        .weight-display .value { font-size: 48px; font-weight: 700; color: #15803d; line-height: 1; }
        .weight-display .unit { font-size: 18px; color: #64748b; margin-top: 4px; }
        .weight-display .status { font-size: 12px; color: #64748b; margin-top: 8px; }
        .info-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 14px; font-size: 12px; color: #92400e; line-height: 1.6; }
        .brand-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .brand-card { border: 2px solid #e5ecf5; border-radius: 8px; padding: 12px; text-align: center; cursor: pointer; transition: all .15s; }
        .brand-card:hover, .brand-card.selected { border-color: #173b63; background: #f0f5ff; }
        .brand-card .brand-name { font-weight: 700; font-size: 13px; color: #173b63; }
        .brand-card .brand-desc { font-size: 11px; color: #64748b; margin-top: 3px; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .dot-connected { background: #22c55e; }
        .dot-disconnected { background: #94a3b8; }
        .dot-error { background: #ef4444; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Digital Weighing Scale</h1>
        <div class="subtitle">Connect electronic weighing scale via USB/COM port for automatic weight reading</div>

        <div class="toggle-row">
            <div>
                <div class="toggle-label">Enable Scale Integration</div>
                <div class="toggle-desc">Auto-read weight from connected scale in purchase/sale entry</div>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" id="scale_enabled" {{ ($settings['SCALE_ENABLED'] ?? 'N') === 'Y' ? 'checked' : '' }}>
                <span class="toggle-slider"></span>
            </label>
        </div>
    </div>

    <div class="card">
        <h2>Scale Brand</h2>
        <div class="brand-grid">
            <div class="brand-card {{ ($settings['SCALE_BRAND'] ?? '') === 'generic' ? 'selected' : '' }}" onclick="selectBrand('generic', this)">
                <div class="brand-name">Generic</div>
                <div class="brand-desc">Standard RS232 protocol</div>
            </div>
            <div class="brand-card {{ ($settings['SCALE_BRAND'] ?? '') === 'sartorius' ? 'selected' : '' }}" onclick="selectBrand('sartorius', this)">
                <div class="brand-name">Sartorius</div>
                <div class="brand-desc">SBI protocol</div>
            </div>
            <div class="brand-card {{ ($settings['SCALE_BRAND'] ?? '') === 'mettler' ? 'selected' : '' }}" onclick="selectBrand('mettler', this)">
                <div class="brand-name">Mettler Toledo</div>
                <div class="brand-desc">MT-SICS protocol</div>
            </div>
            <div class="brand-card {{ ($settings['SCALE_BRAND'] ?? '') === 'ohaus' ? 'selected' : '' }}" onclick="selectBrand('ohaus', this)">
                <div class="brand-name">OHAUS</div>
                <div class="brand-desc">Standard continuous</div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Connection Settings</h2>
        <div class="grid3">
            <div class="field">
                <label>COM Port</label>
                <select id="scale_port">
                    @php $port = $settings['SCALE_PORT'] ?? 'COM1'; @endphp
                    @foreach(['COM1','COM2','COM3','COM4','COM5','COM6','COM7','COM8'] as $p)
                        <option value="{{ $p }}" {{ $port === $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Baud Rate</label>
                <select id="scale_baud">
                    @php $baud = $settings['SCALE_BAUD'] ?? '9600'; @endphp
                    @foreach(['1200','2400','4800','9600','19200','38400','115200'] as $b)
                        <option value="{{ $b }}" {{ $baud === $b ? 'selected' : '' }}>{{ $b }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Protocol</label>
                <select id="scale_protocol">
                    @php $proto = $settings['SCALE_PROTOCOL'] ?? 'continuous'; @endphp
                    <option value="continuous" {{ $proto === 'continuous' ? 'selected' : '' }}>Continuous</option>
                    <option value="demand" {{ $proto === 'demand' ? 'selected' : '' }}>On Demand</option>
                </select>
            </div>
        </div>
        <div class="grid2">
            <div class="field">
                <label>Decimal Places</label>
                <select id="scale_decimals">
                    @php $dec = $settings['SCALE_DECIMALS'] ?? '3'; @endphp
                    <option value="2" {{ $dec === '2' ? 'selected' : '' }}>2 (0.00)</option>
                    <option value="3" {{ $dec === '3' ? 'selected' : '' }}>3 (0.000)</option>
                    <option value="4" {{ $dec === '4' ? 'selected' : '' }}>4 (0.0000)</option>
                </select>
            </div>
            <div class="field">
                <label>Unit</label>
                <select id="scale_unit">
                    @php $unit = $settings['SCALE_UNIT'] ?? 'g'; @endphp
                    <option value="g" {{ $unit === 'g' ? 'selected' : '' }}>Grams (g)</option>
                    <option value="mg" {{ $unit === 'mg' ? 'selected' : '' }}>Milligrams (mg)</option>
                    <option value="oz" {{ $unit === 'oz' ? 'selected' : '' }}>Ounces (oz)</option>
                </select>
            </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:8px;">
            <button class="btn-primary" onclick="saveSettings()">Save Settings</button>
            <button class="btn-ghost" onclick="testConnection()">Test Connection</button>
        </div>
        <div id="save-msg" style="margin-top:10px;font-size:13px;display:none;"></div>
    </div>

    <!-- Live Weight Display -->
    <div class="card">
        <h2>Live Weight Reading</h2>
        <div style="display:flex;gap:8px;margin-bottom:14px;">
            <button class="btn-success" id="btn-connect" onclick="connectScale()">Connect Scale</button>
            <button class="btn-ghost" id="btn-disconnect" onclick="disconnectScale()" style="display:none;">Disconnect</button>
            <div id="conn-status" style="font-size:13px;display:flex;align-items:center;">
                <span class="status-dot dot-disconnected"></span> Not connected
            </div>
        </div>
        <div class="weight-display">
            <div class="value" id="weight-value">—</div>
            <div class="unit" id="weight-unit">{{ $settings['SCALE_UNIT'] ?? 'g' }}</div>
            <div class="status" id="weight-status">Waiting for connection&hellip;</div>
        </div>
    </div>

    <div class="card">
        <div class="info-box">
            <strong>Requirements:</strong><br>
            &bull; Browser must support Web Serial API (Chrome / Edge 89+ required)<br>
            &bull; Scale must be connected via USB-to-Serial adapter or direct COM port<br>
            &bull; For XAMPP/localhost: use Chrome browser, then allow serial port permission<br>
            &bull; Common scale settings: 9600 baud, 8 data bits, No parity, 1 stop bit
        </div>
    </div>
</div>

<script>
let serialPort = null;
let reader = null;
let selectedBrand = '{{ $settings["SCALE_BRAND"] ?? "generic" }}';

function selectBrand(brand, el) {
    selectedBrand = brand;
    document.querySelectorAll('.brand-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
}

function saveSettings() {
    const data = {
        SCALE_ENABLED:  document.getElementById('scale_enabled').checked ? 'Y' : 'N',
        SCALE_PORT:     document.getElementById('scale_port').value,
        SCALE_BAUD:     document.getElementById('scale_baud').value,
        SCALE_BRAND:    selectedBrand,
        SCALE_PROTOCOL: document.getElementById('scale_protocol').value,
        SCALE_DECIMALS: document.getElementById('scale_decimals').value,
        SCALE_UNIT:     document.getElementById('scale_unit').value,
    };
    fetch('/api/scale/save-settings', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        const msg = document.getElementById('save-msg');
        msg.style.display = '';
        if (d.ok) {
            msg.style.color = '#15803d';
            msg.textContent = 'Settings saved successfully.';
        } else {
            msg.style.color = '#dc2626';
            msg.textContent = 'Error: ' + d.message;
        }
        setTimeout(() => { msg.style.display = 'none'; }, 3000);
    });
}

function testConnection() {
    alert('To test: click "Connect Scale" and observe the live reading below.');
}

async function connectScale() {
    if (!navigator.serial) {
        alert('Web Serial API not supported. Please use Chrome or Edge 89+.');
        return;
    }
    try {
        const baud = parseInt(document.getElementById('scale_baud').value||9600);
        serialPort = await navigator.serial.requestPort();
        await serialPort.open({ baudRate: baud });

        setStatus('connected', 'Connected — reading weight&hellip;');
        document.getElementById('btn-connect').style.display = 'none';
        document.getElementById('btn-disconnect').style.display = '';

        const decoder = new TextDecoderStream();
        serialPort.readable.pipeTo(decoder.writable);
        reader = decoder.readable.getReader();
        readWeight();
    } catch (err) {
        setStatus('error', 'Connection failed: ' + err.message);
    }
}

async function readWeight() {
    const dec = parseInt(document.getElementById('scale_decimals').value||3);
    let buffer = '';
    while (true) {
        try {
            const { value, done } = await reader.read();
            if (done) break;
            buffer += value;
            const parts = buffer.split(/[\r\n]+/);
            buffer = parts.pop();
            for (const line of parts) {
                const w = parseWeightLine(line.trim());
                if (w !== null) {
                    document.getElementById('weight-value').textContent = w.toFixed(dec);
                    document.getElementById('weight-unit').textContent = document.getElementById('scale_unit').value;
                    document.getElementById('weight-status').textContent = 'Live reading — ' + new Date().toLocaleTimeString();
                    // Expose globally so purchase/sale forms can read it
                    window.SCALE_WEIGHT = w;
                }
            }
        } catch(e) {
            setStatus('error', 'Read error: ' + e.message);
            break;
        }
    }
}

function parseWeightLine(line) {
    // Try to extract a numeric value from the line
    const match = line.match(/(\d+\.?\d*)/);
    if (match) return parseFloat(match[1]);
    return null;
}

function disconnectScale() {
    if (reader) { reader.cancel(); reader = null; }
    if (serialPort) { serialPort.close(); serialPort = null; }
    setStatus('disconnected', 'Disconnected');
    document.getElementById('btn-connect').style.display = '';
    document.getElementById('btn-disconnect').style.display = 'none';
    document.getElementById('weight-value').textContent = '—';
    window.SCALE_WEIGHT = null;
}

function setStatus(state, msg) {
    const el = document.getElementById('conn-status');
    const colors = { connected: 'dot-connected', disconnected: 'dot-disconnected', error: 'dot-error' };
    el.innerHTML = `<span class="status-dot ${colors[state]||'dot-disconnected'}"></span> ${msg}`;
    document.getElementById('weight-status').textContent = msg;
}
</script>
</body>
</html>
