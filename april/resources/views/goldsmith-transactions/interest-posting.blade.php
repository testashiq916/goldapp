<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
  :root {
    --bg: #cfe0f5;
    --panel: #d9e7f7;
    --grid-head: #d6e5c7;
    --grid-foot: #d9ead3;
    --line: #8ba2bb;
    --text: #0f1720;
    --button: #efe8d4;
    --button-border: #8c8c8c;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font-family: Tahoma, Arial, sans-serif;
    font-size: 13px;
  }
  .window {
    max-width: 980px;
    margin: 16px auto;
    border: 1px solid var(--line);
    background: var(--panel);
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.55);
  }
  .titlebar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    border-bottom: 1px solid var(--line);
    background: linear-gradient(180deg, #d7e8fb 0%, #bfd4ec 100%);
    font-weight: 700;
  }
  .frame {
    padding: 10px 12px 14px;
  }
  .top-row {
    display: grid;
    grid-template-columns: 140px 1fr 120px 100px;
    gap: 8px;
    align-items: center;
    margin-bottom: 10px;
  }
  .field {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 6px;
    align-items: center;
  }
  .field label {
    font-weight: 700;
    white-space: nowrap;
  }
  input, select {
    width: 100%;
    height: 28px;
    padding: 4px 8px;
    border: 1px solid #8e8e8e;
    background: #fff;
    font: inherit;
  }
  button {
    height: 30px;
    padding: 0 12px;
    border: 1px solid var(--button-border);
    background: linear-gradient(180deg, #fbf7ea 0%, #e2d7b8 100%);
    cursor: pointer;
    font: inherit;
  }
  button:disabled {
    opacity: .55;
    cursor: not-allowed;
  }
  .grid-wrap {
    min-height: 420px;
    border: 1px solid var(--line);
    background: #f7f7f7;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
  }
  th, td {
    border: 1px solid #b8c3cd;
    padding: 0;
  }
  th {
    background: var(--grid-head);
    text-align: left;
    padding: 6px 8px;
    font-weight: 700;
  }
  td input, td select {
    border: 0;
    height: 32px;
    padding: 5px 8px;
    background: transparent;
  }
  td.num input {
    text-align: right;
  }
  td.check {
    text-align: center;
  }
  td.check input {
    width: 18px;
    height: 18px;
  }
  .footer-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 8px;
    border-top: 1px solid var(--line);
    background: var(--grid-foot);
    font-weight: 700;
  }
  .footer-bar .totals {
    display: flex;
    gap: 28px;
  }
  .actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
  }
  .actions .left,
  .actions .right {
    display: flex;
    gap: 8px;
    align-items: center;
  }
  .status {
    min-height: 20px;
    margin-top: 10px;
    color: #9a1010;
    font-weight: 700;
  }
  .lookup {
    position: relative;
  }
  .lookup-list {
    display: none;
    position: absolute;
    top: calc(100% + 2px);
    left: 0;
    right: 0;
    max-height: 180px;
    overflow: auto;
    background: #fff;
    border: 1px solid var(--line);
    z-index: 20;
  }
  .lookup-list div {
    padding: 6px 8px;
    cursor: pointer;
  }
  .lookup-list div:hover,
  .lookup-list div.active {
    background: #e6f0ff;
  }
  @media (max-width: 900px) {
    .window { margin: 0; border-width: 0; }
    .top-row { grid-template-columns: 1fr; }
    .field { grid-template-columns: 100px 1fr; }
    .actions { flex-direction: column; gap: 8px; align-items: stretch; }
    .footer-bar { flex-direction: column; gap: 8px; align-items: flex-start; }
  }
</style>
</head>
<body>
<div class="window">
  <div class="titlebar">
    <div>{{ $title }}</div>
    <button type="button" id="btnCloseTop">Close</button>
  </div>

  <div class="frame">
    <div class="top-row">
      <div class="field">
        <label for="tdate">Date</label>
        <input type="date" id="tdate">
      </div>

      <div class="field lookup">
        <label for="smcode">Staff</label>
        <input type="text" id="smcode" autocomplete="off" list="salesmen-list">
        <div class="lookup-list" id="smanDrop"></div>
      </div>

      <button type="button" id="btnShow">Show</button>
      <label style="display:flex;align-items:center;gap:6px;font-weight:700;">
        <input type="checkbox" id="frFlag" style="width:auto;height:auto;">
        Fr
      </label>
    </div>

    <div class="grid-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:34%;">Party</th>
            <th style="width:20%;">Code</th>
            <th style="width:18%;">Int Wgt</th>
            <th style="width:18%;">Int Amt</th>
            <th style="width:10%;">Sel</th>
          </tr>
        </thead>
        <tbody id="rowsBody"></tbody>
      </table>
      <div class="footer-bar">
        <div class="left">
          <button type="button" id="btnAdd">Add</button>
          <button type="button" id="btnDelete">Delete</button>
        </div>
        <div class="totals">
          <span>Total: <span id="totalRows">0</span></span>
          <span><span id="totalWgt">0.000</span></span>
          <span><span id="totalAmt">0.00</span></span>
        </div>
      </div>
    </div>

    <div class="actions">
      <div class="left"></div>
      <div class="right">
        <button type="button" id="btnSave">Save</button>
        <button type="button" id="btnExit">Exit</button>
      </div>
    </div>

    <div class="status" id="statusText"></div>
  </div>
</div>

<script>
const SALESMEN = @json(array_values(array_map(fn($s) => ['code' => $s->code, 'name' => $s->name], $salesmen)));
const SHOW_API = "{{ url('/api/goldsmith-transactions/interest-posting/show') }}";
const SAVE_API = "{{ url('/api/goldsmith-transactions/interest-posting/save') }}";
const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

const State = {
  rows: [],
  selectedRow: 0,
  smanIndex: -1,
  smanItems: [],
};

function emptyRow() {
  return { code: '', name: '', intwgt: 0, intamt: 0, sel: 0 };
}

function esc(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');
}

function setStatus(message = '', isError = true) {
  const el = document.getElementById('statusText');
  el.textContent = message;
  el.style.color = isError ? '#9a1010' : '#0f5c1b';
}

function renderRows() {
  const body = document.getElementById('rowsBody');
  body.innerHTML = '';

  if (!State.rows.length) {
    State.rows = [emptyRow()];
  }

  State.rows.forEach((row, index) => {
    const tr = document.createElement('tr');
    tr.dataset.index = index;
    tr.innerHTML = `
      <td><input type="text" value="${esc(row.name)}" onchange="setText(${index}, 'name', this.value)" readonly></td>
      <td><input type="text" value="${esc(row.code)}" onchange="onCodeChange(${index}, this.value)"></td>
      <td class="num"><input type="number" step="0.001" value="${Number(row.intwgt || 0).toFixed(3)}" onchange="setNumber(${index}, 'intwgt', this.value)"></td>
      <td class="num"><input type="number" step="0.01" value="${Number(row.intamt || 0).toFixed(2)}" onchange="setNumber(${index}, 'intamt', this.value)"></td>
      <td class="check"><input type="checkbox" ${Number(row.sel || 0) === 1 ? 'checked' : ''} onchange="toggleSel(${index}, this.checked)"></td>
    `;
    tr.addEventListener('click', () => {
      State.selectedRow = index;
    });
    body.appendChild(tr);
  });

  refreshTotals();
}

function refreshTotals() {
  let totalWgt = 0;
  let totalAmt = 0;

  State.rows.forEach((row) => {
    totalWgt += Number(row.intwgt || 0);
    totalAmt += Number(row.intamt || 0);
  });

  document.getElementById('totalRows').textContent = String(State.rows.filter((row) => String(row.code || '').trim() !== '').length);
  document.getElementById('totalWgt').textContent = totalWgt.toFixed(3);
  document.getElementById('totalAmt').textContent = totalAmt.toFixed(2);
}

function setText(index, key, value) {
  State.rows[index][key] = value;
}

function setNumber(index, key, value) {
  State.rows[index][key] = Number(value || 0);
  refreshTotals();
}

function toggleSel(index, checked) {
  State.rows[index].sel = checked ? 1 : 0;
}

async function onCodeChange(index, value) {
  const code = String(value || '').trim().toUpperCase();
  State.rows[index].code = code;
  if (!code) {
    State.rows[index].name = '';
    renderRows();
    return;
  }

  const url = `${SHOW_API}?code=${encodeURIComponent(code)}`;
  const data = await fetchJson(url);
  if (!data.success) {
    setStatus(data.message || 'Unable to validate depositor code');
    return;
  }

  const match = (data.rows || []).find((row) => String(row.code).toUpperCase() === code);
  if (!match) {
    State.rows[index].code = '';
    State.rows[index].name = '';
    setStatus('Invalid depositor code');
    renderRows();
    return;
  }

  State.rows[index] = {
    code: match.code || code,
    name: match.name || '',
    intwgt: Number(match.intwgt || 0),
    intamt: Number(match.intamt || 0),
    sel: Number(match.sel || 0),
  };
  setStatus('');
  renderRows();
}

async function showRows() {
  setStatus('');
  const params = new URLSearchParams({
    tdate: document.getElementById('tdate').value,
    smcode: document.getElementById('smcode').value.trim(),
  });
  const data = await fetchJson(`${SHOW_API}?${params.toString()}`);
  if (!data.success) {
    setStatus(data.message || 'Unable to load rows');
    return;
  }
  State.rows = Array.isArray(data.rows) && data.rows.length ? data.rows : [emptyRow()];
  State.selectedRow = 0;
  renderRows();
}

async function saveRows() {
  setStatus('');
  const rows = State.rows
    .map((row) => ({
      code: String(row.code || '').trim().toUpperCase(),
      name: String(row.name || '').trim(),
      intwgt: Number(row.intwgt || 0),
      intamt: Number(row.intamt || 0),
      sel: Number(row.sel || 0),
    }))
    .filter((row) => row.code !== '');

  if (!rows.length) {
    setStatus('No rows to save');
    return;
  }

  if (!window.confirm('Do you want to save...?')) {
    return;
  }

  const data = await fetchJson(SAVE_API, 'POST', {
    tdate: document.getElementById('tdate').value,
    smcode: document.getElementById('smcode').value.trim(),
    gilevel: 1,
    rows: JSON.stringify(rows),
  });

  if (!data.success) {
    setStatus(data.message || 'Save failed');
    return;
  }

  setStatus(data.message || 'Saved', false);
  await showRows();
}

function addRow() {
  const last = State.rows[State.rows.length - 1];
  if (last && String(last.code || '').trim() === '') {
    return;
  }
  State.rows.push(emptyRow());
  State.selectedRow = State.rows.length - 1;
  renderRows();
}

function deleteRow() {
  if (!State.rows.length) {
    return;
  }
  State.rows.splice(State.selectedRow, 1);
  if (!State.rows.length) {
    State.rows = [emptyRow()];
  }
  State.selectedRow = Math.max(0, Math.min(State.selectedRow, State.rows.length - 1));
  renderRows();
}

function closeWindow() {
  window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
}

function filterSalesmen(query) {
  const q = query.trim().toLowerCase();
  if (!q) {
    State.smanItems = [];
    State.smanIndex = -1;
    document.getElementById('smanDrop').style.display = 'none';
    return;
  }

  State.smanItems = SALESMEN.filter((row) =>
    row.code.toLowerCase().includes(q) || row.name.toLowerCase().includes(q)
  );
  State.smanIndex = -1;

  const drop = document.getElementById('smanDrop');
  if (!State.smanItems.length) {
    drop.style.display = 'none';
    return;
  }

  drop.innerHTML = State.smanItems
    .map((row, index) => `<div data-index="${index}"><strong>${esc(row.code)}</strong> - ${esc(row.name)}</div>`)
    .join('');
  drop.style.display = 'block';
}

function highlightSalesman(index) {
  const items = document.querySelectorAll('#smanDrop div');
  items.forEach((item, i) => item.classList.toggle('active', i === index));
  State.smanIndex = index;
}

function pickSalesman(index) {
  const item = State.smanItems[index];
  if (!item) {
    return;
  }
  document.getElementById('smcode').value = item.code;
  document.getElementById('smanDrop').style.display = 'none';
  State.smanItems = [];
  State.smanIndex = -1;
}

async function fetchJson(url, method = 'GET', body = null) {
  const options = { method, headers: { Accept: 'application/json' } };
  if (body) {
    options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
    options.headers['X-CSRF-TOKEN'] = csrf();
    options.body = new URLSearchParams(body).toString();
  }

  const response = await fetch(url, options);
  return response.json();
}

document.getElementById('btnShow').addEventListener('click', showRows);
document.getElementById('btnAdd').addEventListener('click', addRow);
document.getElementById('btnDelete').addEventListener('click', deleteRow);
document.getElementById('btnSave').addEventListener('click', saveRows);
document.getElementById('btnExit').addEventListener('click', closeWindow);
document.getElementById('btnCloseTop').addEventListener('click', closeWindow);

const smcode = document.getElementById('smcode');
smcode.addEventListener('input', () => filterSalesmen(smcode.value));
smcode.addEventListener('keydown', (event) => {
  const items = document.querySelectorAll('#smanDrop div');
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    highlightSalesman(Math.min(State.smanIndex + 1, items.length - 1));
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    highlightSalesman(Math.max(State.smanIndex - 1, 0));
  } else if (event.key === 'Enter' && State.smanIndex >= 0) {
    event.preventDefault();
    pickSalesman(State.smanIndex);
  } else if (event.key === 'F9') {
    event.preventDefault();
    saveRows();
  } else if (event.key === 'Escape') {
    document.getElementById('smanDrop').style.display = 'none';
  }
});
smcode.addEventListener('blur', () => {
  setTimeout(() => {
    document.getElementById('smanDrop').style.display = 'none';
  }, 150);
});

document.getElementById('smanDrop').addEventListener('mousedown', (event) => {
  event.preventDefault();
  const row = event.target.closest('[data-index]');
  if (row) {
    pickSalesman(Number(row.dataset.index));
  }
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'F9') {
    event.preventDefault();
    saveRows();
  }
});

document.getElementById('tdate').value = new Date().toISOString().slice(0, 10);
State.rows = [emptyRow()];
renderRows();
</script>
</body>
</html>
