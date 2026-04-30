<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:#f4f7fb;color:#1e293b;font-size:13px}
.wrap{max-width:1100px;margin:12px auto;background:#fff;border:1px solid #d7dfeb;border-radius:10px;padding:16px}
h1{font-size:18px;color:#173b63;margin-bottom:4px}
.sub{color:#5b7088;font-size:11px;margin-bottom:14px}

/* ── Country Grid ── */
.country-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;margin-bottom:16px}
.cp{border:2px solid #d7dfeb;border-radius:8px;padding:8px 6px;text-align:center;cursor:pointer;transition:.15s;background:#fafcff}
.cp:hover{border-color:#2a6398;background:#eef4fc}
.cp.active{border-color:#1e5799;background:#dbeafe}
.cp .flag{font-size:22px;display:block;margin-bottom:3px}
.cp .cname{font-size:10px;font-weight:700;color:#173b63}
.cp .ccode{font-size:9px;color:#64748b}

/* ── Section cards ── */
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px}
@media(max-width:700px){.grid2,.grid3{grid-template-columns:1fr}}
.card{border:1px solid #dde9f8;border-radius:8px;padding:14px;background:#fafcff}
.card h3{font-size:11px;font-weight:700;color:#1e5799;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.field{display:flex;flex-direction:column;gap:3px;margin-bottom:10px}
.field:last-child{margin-bottom:0}
label{font-size:11px;font-weight:700;color:#375b84}
input,select{height:30px;border:1px solid #bfd0e6;border-radius:5px;padding:0 8px;font-size:12px;width:100%}
input:focus,select:focus{outline:none;border-color:#2a6398;box-shadow:0 0 0 2px rgba(42,99,152,.1)}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}

/* ── Working days ── */
.days-grid{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px}
.day-btn{padding:4px 10px;border:1px solid #bfd0e6;border-radius:16px;font-size:11px;cursor:pointer;background:#fff;color:#375b84;user-select:none;transition:.1s}
.day-btn.on{background:#1e5799;border-color:#1e5799;color:#fff;font-weight:700}

/* ── Preview ── */
.preview{border:1px solid #c7d4e4;border-radius:8px;padding:14px;background:#f0f7ff;margin-bottom:14px}
.preview h3{font-size:11px;font-weight:700;color:#1e5799;text-transform:uppercase;margin-bottom:10px}
.preview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px}
.pv-item{background:#fff;border:1px solid #d7e4f0;border-radius:6px;padding:8px 10px}
.pv-label{font-size:9.5px;color:#64748b;font-weight:700;text-transform:uppercase;margin-bottom:3px}
.pv-val{font-size:13px;font-weight:700;color:#173b63}
.pv-val.sym{font-size:17px}


/* ── Buttons ── */
.btn-row{display:flex;gap:8px;margin-top:14px}
button{height:32px;padding:0 18px;cursor:pointer;border-radius:5px;font-size:12px;font-weight:700;border:1px solid}
.btn-save{background:#1e5799;border-color:#1e5799;color:#fff}
.btn-save:hover{background:#173b63}
.btn-reset{background:#fff;border-color:#bfd0e6;color:#375b84}
.btn-exit{background:#fff;border-color:#bfd0e6;color:#375b84}

/* ── Status ── */
.status{font-size:12px;padding:8px 12px;border-radius:5px;margin-top:10px;display:none}
.status.ok{background:#d1fae5;color:#065f46;display:block}
.status.err{background:#fee2e2;color:#991b1b;display:block}

/* ── Hijri info ── */
.hijri-note{font-size:10px;color:#5b7088;margin-top:4px;font-style:italic}

@media print{.btn-row{display:none}}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="wrap">
<h1>{{ $title }}</h1>
<div class="sub">Select a country preset or configure manually. Changes affect currency symbol, date format, weight unit, tax label and number format across all entries.</div>

<!-- Country Presets -->
<div id="countryGrid" class="country-grid"></div>

<div class="grid2">
  <!-- LEFT: Country & Religion -->
  <div class="card">
    <h3>🌍 Country & Religion</h3>
    <div class="row2">
      <div class="field">
        <label>Country Code</label>
        <input id="country_code" maxlength="5" placeholder="IN">
      </div>
      <div class="field">
        <label>Country Name</label>
        <input id="country_name" placeholder="India">
      </div>
    </div>
    <input type="hidden" id="religion" value="Others">
    <input type="hidden" id="calendar_type" value="gregorian">
    <div class="field">
      <label>Working Days</label>
      <div class="days-grid" id="daysGrid"></div>
    </div>
  </div>

  <!-- RIGHT: Currency -->
  <div class="card">
    <h3>💰 Currency</h3>
    <div class="row3">
      <div class="field">
        <label>Code</label>
        <input id="currency_code" maxlength="10" placeholder="INR">
      </div>
      <div class="field">
        <label>Symbol</label>
        <input id="currency_symbol" maxlength="20" placeholder="₹">
      </div>
      <div class="field">
        <label>Decimals</label>
        <select id="decimal_places">
          <option value="0">0</option>
          <option value="2" selected>2</option>
          <option value="3">3</option>
          <option value="4">4</option>
        </select>
      </div>
    </div>
    <div class="field">
      <label>Currency Full Name</label>
      <input id="currency_name" placeholder="Indian Rupee">
    </div>
    <div class="row2">
      <div class="field">
        <label>Base Currency</label>
        <input id="base_currency" maxlength="10" placeholder="INR" title="Amounts stored in this currency (usually your home currency)">
      </div>
      <div class="field">
        <label>Exchange Rate (1 unit = ? base)</label>
        <input id="exchange_rate" type="number" step="0.000001" min="0.000001" placeholder="1.0" title="E.g. 1 AED = 22.68 INR">
      </div>
    </div>
    <div class="row2">
      <div class="field">
        <label>Number Format</label>
        <select id="number_format">
          <option value="indian">Indian (1,00,000)</option>
          <option value="western">Western (100,000)</option>
        </select>
      </div>
      <div class="field">
        <label>RTL Layout</label>
        <select id="rtl">
          <option value="0">No (Left to Right)</option>
          <option value="1">Yes (Right to Left)</option>
        </select>
      </div>
    </div>
  </div>
</div>

<div class="grid2">
  <!-- Weight & Purity -->
  <div class="card">
    <h3>⚖️ Weight & Purity</h3>
    <div class="field">
      <label>Weight Unit</label>
      <select id="weight_unit">
        <option value="gram">Gram (g)</option>
        <option value="tola">Tola (1 tola = 11.664 g)</option>
        <option value="troy_oz">Troy Ounce (1 troy oz = 31.1035 g)</option>
        <option value="masha">Masha (1 masha = 0.972 g)</option>
        <option value="baht">Baht (1 baht = 15.244 g — Thailand)</option>
        <option value="chi">Chi (1 chi = 3.75 g — Vietnam)</option>
      </select>
    </div>
    <div class="field">
      <label>Purity System</label>
      <select id="purity_system">
        <option value="millesimal">Millesimal Fineness (e.g. 916 = 22K)</option>
        <option value="karat">Karat (e.g. 22K, 18K, 14K)</option>
        <option value="percentage">Percentage (e.g. 91.6%, 75%)</option>
      </select>
    </div>
    <div class="field">
      <label>Tax Label</label>
      <input id="tax_label" maxlength="20" placeholder="GST">
    </div>
    <div class="field">
      <label>Date Format</label>
      <select id="date_format">
        <option value="DD/MM/YYYY">DD/MM/YYYY (e.g. 18/03/2026)</option>
        <option value="MM/DD/YYYY">MM/DD/YYYY (e.g. 03/18/2026)</option>
        <option value="YYYY-MM-DD">YYYY-MM-DD (e.g. 2026-03-18)</option>
        <option value="DD-MM-YYYY">DD-MM-YYYY (e.g. 18-03-2026)</option>
        <option value="DD MMM YYYY">DD MMM YYYY (e.g. 18 Mar 2026)</option>
      </select>
    </div>
  </div>

  <!-- Preview -->
  <div style="display:flex;flex-direction:column;gap:14px">
    <div class="preview">
      <h3>Live Preview — How entries will appear</h3>
      <div class="preview-grid">
        <div class="pv-item"><div class="pv-label">Currency Symbol</div><div class="pv-val sym" id="prev_sym">₹</div></div>
        <div class="pv-item"><div class="pv-label">Amount (e.g. 125000)</div><div class="pv-val" id="prev_amt">₹ 1,25,000.00</div></div>
        <div class="pv-item"><div class="pv-label">Weight (e.g. 10.750)</div><div class="pv-val" id="prev_wgt">10.750 g</div></div>
        <div class="pv-item"><div class="pv-label">Purity (e.g. 22K gold)</div><div class="pv-val" id="prev_pur">916</div></div>
        <div class="pv-item"><div class="pv-label">Date Format</div><div class="pv-val" id="prev_date">18/03/2026</div></div>
        <div class="pv-item"><div class="pv-label">Tax Label</div><div class="pv-val" id="prev_tax">GST</div></div>
        <div class="pv-item"><div class="pv-label">Exchange Rate</div><div class="pv-val" id="prev_rate">1 INR = 1.000000 INR</div></div>
        <div class="pv-item"><div class="pv-label">Working Days</div><div class="pv-val" id="prev_days" style="font-size:11px">Mon–Sat</div></div>
      </div>
    </div>

    <!-- Weight unit conversion reference -->
    <div class="card">
      <h3>📐 Weight Conversion Reference</h3>
      <table style="font-size:11px;width:100%">
        <tr style="background:#edf4fc"><th style="padding:4px 6px;text-align:left">Unit</th><th style="padding:4px 6px;text-align:right">= grams</th></tr>
        <tr><td style="padding:3px 6px">1 Tola</td><td style="padding:3px 6px;text-align:right">11.6638 g</td></tr>
        <tr><td style="padding:3px 6px">1 Troy Oz</td><td style="padding:3px 6px;text-align:right">31.1035 g</td></tr>
        <tr><td style="padding:3px 6px">1 Masha</td><td style="padding:3px 6px;text-align:right">0.9720 g</td></tr>
        <tr><td style="padding:3px 6px">1 Baht</td><td style="padding:3px 6px;text-align:right">15.2440 g</td></tr>
        <tr><td style="padding:3px 6px">1 Chi</td><td style="padding:3px 6px;text-align:right">3.7500 g</td></tr>
      </table>
    </div>
  </div>
</div>

<div class="btn-row">
  <button class="btn-save" id="saveBtn">Save Settings</button>
  <button class="btn-reset" id="resetBtn">Reset to India (INR)</button>
  <button class="btn-exit" id="exitBtn">Exit</button>
</div>
<div class="status" id="statusMsg"></div>
</div>

<script>
const API_LOAD = @json(url('/api/country-currency/load'));
const API_SAVE = @json(url('/api/country-currency/save'));

const DAYS_ALL = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
const WEIGHT_LABELS = { gram:'g', tola:'tola', troy_oz:'troy oz', masha:'masha', baht:'baht', chi:'chi' };
const PURITY_22K = { millesimal:'916', karat:'22K', percentage:'91.6%' };

let profiles = {};

// ── Days toggle ────────────────────────────────────────────────
function buildDaysGrid(active) {
  const g = document.getElementById('daysGrid');
  g.innerHTML = '';
  const activeArr = active ? active.split(',') : ['Mon','Tue','Wed','Thu','Fri','Sat'];
  DAYS_ALL.forEach(d => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'day-btn' + (activeArr.includes(d) ? ' on' : '');
    btn.textContent = d;
    btn.dataset.day = d;
    btn.onclick = () => { btn.classList.toggle('on'); updatePreview(); };
    g.appendChild(btn);
  });
}
function getWorkingDays() {
  return Array.from(document.querySelectorAll('.day-btn.on')).map(b => b.dataset.day).join(',');
}

// ── Country preset grid ────────────────────────────────────────
function buildCountryGrid(currentCode) {
  const g = document.getElementById('countryGrid');
  g.innerHTML = '';
  Object.entries(profiles).forEach(([code, p]) => {
    const d = document.createElement('div');
    d.className = 'cp' + (code === currentCode ? ' active' : '');
    d.innerHTML = `<span class="flag">${p.flag||'🌐'}</span><div class="cname">${p.country_name}</div><div class="ccode">${code} · ${p.currency_code}</div>`;
    d.onclick = () => applyProfile(code);
    g.appendChild(d);
  });
}
function applyProfile(code) {
  const p = profiles[code];
  if (!p) return;
  document.getElementById('country_code').value   = code;
  document.getElementById('country_name').value   = p.country_name;
  document.getElementById('religion').value        = p.religion;
  document.getElementById('calendar_type').value  = p.calendar_type;
  document.getElementById('currency_code').value  = p.currency_code;
  document.getElementById('currency_symbol').value= p.currency_symbol;
  document.getElementById('currency_name').value  = p.currency_name;
  document.getElementById('exchange_rate').value  = p.exchange_rate;
  document.getElementById('date_format').value    = p.date_format;
  document.getElementById('weight_unit').value    = p.weight_unit;
  document.getElementById('purity_system').value  = p.purity_system;
  document.getElementById('decimal_places').value = p.decimal_places;
  document.getElementById('tax_label').value      = p.tax_label;
  document.getElementById('number_format').value  = p.number_format || 'western';
  document.getElementById('rtl').value            = p.rtl || 0;
  buildDaysGrid(p.working_days);
  document.querySelectorAll('.cp').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.cp').forEach(el => {
    if (el.querySelector('.ccode')?.textContent.startsWith(code)) el.classList.add('active');
  });
  updatePreview();
}

// ── Preview ────────────────────────────────────────────────────
function formatAmount(n, sym, dec, fmt) {
  const str = Number(n).toFixed(dec);
  const [intPart, decPart] = str.split('.');
  let formatted;
  if (fmt === 'indian') {
    const lastThree = intPart.slice(-3);
    const rest = intPart.slice(0, -3);
    const grouped = rest ? rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + lastThree : lastThree;
    formatted = decPart !== undefined ? grouped + '.' + decPart : grouped;
  } else {
    formatted = Number(intPart).toLocaleString('en-US') + (decPart !== undefined ? '.' + decPart : '');
  }
  return sym + ' ' + formatted;
}
function formatDate(dfmt) {
  const d = new Date(2026,2,18); // 18 Mar 2026
  const dd = String(d.getDate()).padStart(2,'0');
  const mm = String(d.getMonth()+1).padStart(2,'0');
  const yyyy = d.getFullYear();
  const mon = d.toLocaleString('en-US',{month:'short'});
  return dfmt.replace('DD',dd).replace('MM',mm).replace('YYYY',yyyy).replace('MMM',mon);
}
function updatePreview() {
  const sym = document.getElementById('currency_symbol').value || '₹';
  const dec = parseInt(document.getElementById('decimal_places').value) || 2;
  const fmt = document.getElementById('number_format').value;
  const wgt = document.getElementById('weight_unit').value;
  const pur = document.getElementById('purity_system').value;
  const dfmt= document.getElementById('date_format').value;
  const tax = document.getElementById('tax_label').value || 'GST';
  const rel = document.getElementById('religion').value;
  const rate= parseFloat(document.getElementById('exchange_rate').value) || 1;
  const bcur= document.getElementById('base_currency').value || 'INR';
  const ccur= document.getElementById('currency_code').value || 'INR';
  document.getElementById('prev_sym').textContent  = sym;
  document.getElementById('prev_amt').textContent  = formatAmount(125000, sym, dec, fmt);
  document.getElementById('prev_wgt').textContent  = '10.750 ' + (WEIGHT_LABELS[wgt] || wgt);
  document.getElementById('prev_pur').textContent  = PURITY_22K[pur] || '916';
  document.getElementById('prev_date').textContent = formatDate(dfmt);
  document.getElementById('prev_tax').textContent  = tax;
  document.getElementById('prev_rate').textContent = `1 ${ccur} = ${rate.toFixed(6)} ${bcur}`;
  document.getElementById('prev_days').textContent = getWorkingDays().split(',').join(' · ');
}

// ── Load ───────────────────────────────────────────────────────
async function loadSettings() {
  try {
    const res = await fetch(API_LOAD, { headers: { Accept: 'application/json' } });
    const d = await res.json();
    if (!d.ok) return;
    profiles = d.profiles || {};
    const c = d.config || {};

    document.getElementById('country_code').value   = c.country_code || 'IN';
    document.getElementById('country_name').value   = c.country_name || 'India';
    document.getElementById('religion').value        = c.religion || 'Hindu';
    document.getElementById('calendar_type').value  = c.calendar_type || 'gregorian';
    document.getElementById('currency_code').value  = c.currency_code || 'INR';
    document.getElementById('currency_symbol').value= c.currency_symbol || '₹';
    document.getElementById('currency_name').value  = c.currency_name || 'Indian Rupee';
    document.getElementById('exchange_rate').value  = c.exchange_rate || 1;
    document.getElementById('base_currency').value  = c.base_currency || 'INR';
    document.getElementById('date_format').value    = c.date_format || 'DD/MM/YYYY';
    document.getElementById('weight_unit').value    = c.weight_unit || 'gram';
    document.getElementById('purity_system').value  = c.purity_system || 'millesimal';
    document.getElementById('decimal_places').value = c.decimal_places ?? 2;
    document.getElementById('tax_label').value      = c.tax_label || 'GST';
    document.getElementById('number_format').value  = c.number_format || 'indian';
    document.getElementById('rtl').value            = c.rtl || 0;

    buildDaysGrid(c.working_days || 'Mon,Tue,Wed,Thu,Fri,Sat');
    buildCountryGrid(c.country_code || 'IN');
    updatePreview();
  } catch(e) {
    showStatus('Failed to load settings: ' + e.message, false);
  }
}

// ── Save ───────────────────────────────────────────────────────
document.getElementById('saveBtn').addEventListener('click', async () => {
  const body = {
    country_code:   document.getElementById('country_code').value,
    country_name:   document.getElementById('country_name').value,
    religion:       document.getElementById('religion').value,
    calendar_type:  document.getElementById('calendar_type').value,
    currency_code:  document.getElementById('currency_code').value,
    currency_symbol:document.getElementById('currency_symbol').value,
    currency_name:  document.getElementById('currency_name').value,
    exchange_rate:  document.getElementById('exchange_rate').value,
    base_currency:  document.getElementById('base_currency').value,
    date_format:    document.getElementById('date_format').value,
    weight_unit:    document.getElementById('weight_unit').value,
    purity_system:  document.getElementById('purity_system').value,
    decimal_places: document.getElementById('decimal_places').value,
    tax_label:      document.getElementById('tax_label').value,
    number_format:  document.getElementById('number_format').value,
    rtl:            document.getElementById('rtl').value,
    working_days:   getWorkingDays(),
  };
  try {
    const res = await fetch(API_SAVE, {
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]?.replace(/%3D/g,'=') || ''},
      body: JSON.stringify(body),
    });
    const d = await res.json();
    showStatus(d.message || (d.ok ? 'Saved' : 'Error'), d.ok);
    if (d.ok) {
      // Notify parent dashboard to refresh its global config
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'goldapp:currency-config-updated', config: body }, '*');
      }
    }
  } catch(e) {
    showStatus('Save failed: ' + e.message, false);
  }
});

document.getElementById('resetBtn').addEventListener('click', () => applyProfile('IN'));
document.getElementById('exitBtn').addEventListener('click', () => window.parent.postMessage({type:'goldapp:close-module-frame'},'*'));

// Live preview on any field change
document.querySelectorAll('input,select').forEach(el => el.addEventListener('input', updatePreview));

function showStatus(msg, ok) {
  const el = document.getElementById('statusMsg');
  el.textContent = msg;
  el.className = 'status ' + (ok ? 'ok' : 'err');
  setTimeout(() => el.style.display = 'none', 4000);
}

loadSettings();
</script>
</body>
</html>
