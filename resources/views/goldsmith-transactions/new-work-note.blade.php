<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
:root {
  --bg: #f4f1e8;
  --panel: #fff9eb;
  --line: #c8a23a;
  --head-bg: #2c2410;
  --head-fg: #f5d87a;
}
body { margin: 0; font-family: Arial, sans-serif; font-size: 12px; background: var(--bg); color: #1d1d1d; }
#topbar { background: var(--head-bg); color: var(--head-fg); padding: 8px 12px; display: flex; align-items: center; gap: 10px; }
#topbar h2 { margin: 0; flex: 1; font-size: 16px; }
.btn { border: 0; border-radius: 3px; padding: 6px 10px; cursor: pointer; font-weight: 700; font-size: 12px; }
.btn-lite { background: #e7d6aa; color: #222; }
.btn-save { background: #28a745; color: #fff; }
.btn-danger { background: #c0392b; color: #fff; }
.wrap { padding: 8px; }
.filter-panel { background: var(--panel); border: 1px solid var(--line); padding: 8px; display: grid; grid-template-columns: 150px 220px 180px auto; gap: 8px; align-items: end; }
label { display: block; margin-bottom: 3px; font-size: 11px; font-weight: 700; color: #8b6914; }
input, select { width: 100%; box-sizing: border-box; border: 1px solid #d8b678; background: #fff; border-radius: 3px; padding: 4px 6px; font-size: 12px; }
#gridwrap { margin-top: 8px; border: 1px solid #d8b678; background: #fff; overflow: auto; }
table { border-collapse: collapse; width: 100%; min-width: 980px; }
th, td { border: 1px solid #ddd; padding: 3px; }
th { background: var(--head-bg); color: var(--head-fg); font-size: 11px; }
tr.selected { background: #f4ebcf; }
td input, td select { border: 0; padding: 2px 4px; background: transparent; }
td.num input { text-align: right; }
.actions { margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap; }
#warn { margin-top: 8px; color: #b00020; font-weight: 700; min-height: 18px; }
@media print {
  .filter-panel, .actions, #topbar, #warn { display: none !important; }
  body { background: #fff; }
  #gridwrap { border: 0; }
}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div id="topbar">
  <h2>{{ $title }}</h2>
  <button class="btn btn-lite" id="btnClose">Close</button>
</div>

<div class="wrap">
  <div class="filter-panel">
    <div>
      <label>Smith</label>
      <input id="smithFilter" autocomplete="off" placeholder="Code">
    </div>
    <div>
      <label>Filter</label>
      <select id="typeFilter">
        <option value="all">All</option>
        <option value="pending" selected>Pending Only</option>
        <option value="new-work">New Work Only</option>
        <option value="work-in-progress">Work In Progress Only</option>
        <option value="work-finished">Work Finished Only</option>
      </select>
    </div>
    <div>
      <label>Smith Name</label>
      <input id="smithName" readonly>
    </div>
    <div>
      <button class="btn btn-lite" id="btnApply">Apply</button>
    </div>
  </div>

  <div id="gridwrap">
    <table>
      <thead>
        <tr>
          <th style="width:34px">#</th>
          <th style="width:120px">Smith</th>
          <th style="width:110px">Date</th>
          <th style="width:110px">Order No</th>
          <th style="width:110px">Item</th>
          <th style="width:80px">Qty</th>
          <th style="width:90px">Weight</th>
          <th>Particulars</th>
          <th style="width:150px">Status</th>
        </tr>
      </thead>
      <tbody id="body"></tbody>
    </table>
  </div>

  <div class="actions">
    <button class="btn btn-lite" id="btnAdd">Add</button>
    <button class="btn btn-danger" id="btnDelete">Delete</button>
    <button class="btn btn-lite" id="btnSort">Sort</button>
    <button class="btn btn-lite" id="btnCancel">Cancel</button>
    <button class="btn btn-save" id="btnSave">Save (F9)</button>
    <button class="btn btn-lite" id="btnPrint">Print</button>
  </div>

  <div id="warn"></div>
</div>

<script>
const API = "{{ url('/api/goldsmith-new-work-note') }}";
const CLIENT_API = "{{ url('/api/goldsmith-transactions/client-help') }}";
const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
let rows = [];
let selected = -1;
let deletedIds = [];
let lastSmithSearchToken = 0;

function esc(v) { return String(v || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('"', '&quot;'); }
function n(v) { return Number(v || 0); }
function f3(v) { return Number(v || 0).toFixed(3); }
function tdate(v) { return (v || '').toString().slice(0, 10); }

function emptyRow() {
  return { sno: 0, smithcode: '', tdate: new Date().toISOString().slice(0, 10), ordno: '', icode: '', qty: 0, weight: 0, part: '', status: 1, smithname: '', itemname: '' };
}

function statusOptions(value) {
  const v = Number(value || 1);
  return `
    <option value="1" ${v === 1 ? 'selected' : ''}>New Work</option>
    <option value="2" ${v === 2 ? 'selected' : ''}>Work In Progress</option>
    <option value="3" ${v === 3 ? 'selected' : ''}>Work Finished</option>`;
}

function render() {
  const body = document.getElementById('body');
  body.innerHTML = '';
  rows.forEach((r, i) => {
    const tr = document.createElement('tr');
    if (i === selected) tr.className = 'selected';
    tr.innerHTML = `
      <td>${i + 1}</td>
      <td><input value="${esc(r.smithcode)}" onchange="setf(${i},'smithcode',this.value.toUpperCase())"></td>
      <td><input type="date" value="${esc(tdate(r.tdate))}" onchange="setf(${i},'tdate',this.value)"></td>
      <td><input value="${esc(r.ordno)}" onchange="setf(${i},'ordno',this.value.toUpperCase())"></td>
      <td><input value="${esc(r.icode)}" onchange="setf(${i},'icode',this.value.toUpperCase())"></td>
      <td class="num"><input type="number" value="${n(r.qty)}" onchange="setn(${i},'qty',this.value)"></td>
      <td class="num"><input type="number" step="0.001" value="${f3(r.weight)}" onchange="setn(${i},'weight',this.value)"></td>
      <td><input value="${esc(r.part)}" maxlength="50" onchange="setf(${i},'part',this.value)"></td>
      <td><select onchange="setn(${i},'status',this.value)">${statusOptions(r.status)}</select></td>`;
    tr.addEventListener('click', () => { selected = i; render(); });
    body.appendChild(tr);
  });
}

function setf(i, k, v) { rows[i][k] = v; }
function setn(i, k, v) { rows[i][k] = Number(v || 0); }

async function fetchJson(url, method = 'GET', payload = null) {
  const opts = { method, headers: {} };
  if (payload) {
    opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
    opts.headers['X-CSRF-TOKEN'] = csrf();
    opts.body = new URLSearchParams(payload).toString();
  }
  const r = await fetch(url, opts);
  return await r.json();
}

function currentFilterCode() {
  return document.getElementById('smithFilter').value.trim().toUpperCase();
}

async function loadRows() {
  const type = document.getElementById('typeFilter').value;
  const smithcode = currentFilterCode();
  const q = `${API}/list?type=${encodeURIComponent(type)}&smithcode=${encodeURIComponent(smithcode)}`;
  const d = await fetchJson(q);
  if (!d.success) {
    document.getElementById('warn').textContent = d.message || 'Failed to load data';
    return;
  }

  rows = Array.isArray(d.rows) ? d.rows.map(r => ({
    sno: Number(r.sno || 0),
    smithcode: String(r.smithcode || ''),
    tdate: tdate(r.tdate),
    ordno: String(r.ordno || ''),
    icode: String(r.icode || ''),
    qty: Number(r.qty || 0),
    weight: Number(r.weight || 0),
    part: String(r.part || ''),
    status: Number(r.status || 1),
    smithname: String(r.smithname || ''),
    itemname: String(r.itemname || ''),
  })) : [];

  selected = rows.length ? 0 : -1;
  deletedIds = [];
  document.getElementById('warn').textContent = '';
  render();
}

function addRow() {
  if (rows.length > 0) {
    const last = rows[rows.length - 1];
    if (!String(last.smithcode || '').trim()) {
      selected = rows.length - 1;
      render();
      return;
    }
  }
  rows.push(emptyRow());
  selected = rows.length - 1;
  render();
}

function deleteRow() {
  if (selected < 0 || selected >= rows.length) return;
  if (!confirm('Delete ?')) return;
  const row = rows[selected];
  if (Number(row.sno || 0) > 0) deletedIds.push(Number(row.sno));
  rows.splice(selected, 1);
  if (selected >= rows.length) selected = rows.length - 1;
  render();
}

function sortRows() {
  rows.sort((a, b) => {
    const td = tdate(a.tdate).localeCompare(tdate(b.tdate));
    if (td !== 0) return td;
    const sc = String(a.smithcode || '').localeCompare(String(b.smithcode || ''));
    if (sc !== 0) return sc;
    return String(a.icode || '').localeCompare(String(b.icode || ''));
  });
  selected = rows.length ? 0 : -1;
  render();
}

async function saveRows() {
  const payload = {
    rows: JSON.stringify(rows),
    deleted_ids: JSON.stringify(deletedIds),
  };
  const d = await fetchJson(`${API}/save`, 'POST', payload);
  if (!d.success) {
    document.getElementById('warn').textContent = d.message || 'Save failed';
    alert(d.message || 'Save failed');
    return;
  }
  alert(d.message || 'Updation Completed...');
  await loadRows();
}

async function lookupSmithName() {
  const code = currentFilterCode();
  if (!code) {
    document.getElementById('smithName').value = '';
    return;
  }

  const token = ++lastSmithSearchToken;
  const d = await fetchJson(`${CLIENT_API}?ctype=G&q=${encodeURIComponent(code)}`);
  if (token !== lastSmithSearchToken) return;
  const found = Array.isArray(d) ? d.find(x => String(x.code || '').toUpperCase() === code) : null;
  document.getElementById('smithName').value = found ? String(found.name || '') : '';
}

document.getElementById('btnAdd').addEventListener('click', addRow);
document.getElementById('btnDelete').addEventListener('click', deleteRow);
document.getElementById('btnSort').addEventListener('click', sortRows);
document.getElementById('btnCancel').addEventListener('click', loadRows);
document.getElementById('btnSave').addEventListener('click', saveRows);
document.getElementById('btnPrint').addEventListener('click', () => window.print());
document.getElementById('btnApply').addEventListener('click', loadRows);
document.getElementById('btnClose').addEventListener('click', () => window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*'));

document.getElementById('typeFilter').addEventListener('change', loadRows);
document.getElementById('smithFilter').addEventListener('blur', async () => {
  const el = document.getElementById('smithFilter');
  el.value = el.value.trim().toUpperCase();
  await lookupSmithName();
  await loadRows();
});

document.addEventListener('keydown', (ev) => {
  if (ev.key === 'F9') {
    ev.preventDefault();
    saveRows();
  }
});

loadRows();
</script>
</body>
</html>
