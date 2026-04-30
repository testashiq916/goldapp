<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f0f0f0;color:#1f2937}
.wrap{padding:8px}
.bar{background:#34495e;color:#ecf0f1;padding:8px 12px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.toolbar{display:flex;gap:8px;align-items:center;background:#d5dce4;border:1px solid #95a5b8;padding:8px;margin-bottom:4px;flex-wrap:wrap}
.toolbar label{font-weight:700;font-size:13px}
.toolbar input{height:30px;border:1px solid #95a5b8;border-radius:4px;padding:0 8px;font-size:13px}
.btn{height:32px;padding:0 14px;border:1px solid #7f8fa6;background:#c8d6e5;border-radius:4px;cursor:pointer;font-weight:700;font-size:12px;white-space:nowrap}
.btn:hover{background:#b0c4d8}
.btn-primary{background:#2980b9;color:#fff;border-color:#2471a3}
.btn-primary:hover{background:#2471a3}
.btn-danger{background:#c0392b;color:#fff;border-color:#a33025}
.btn-danger:hover{background:#a33025}
.btn-success{background:#27ae60;color:#fff;border-color:#219a52}
.btn-success:hover{background:#219a52}
.btn-warning{background:#e67e22;color:#fff;border-color:#d35400}
.btn-warning:hover{background:#d35400}
.btn:disabled{opacity:.5;cursor:not-allowed}
.panels{display:grid;grid-template-columns:2fr 1fr;gap:6px}
.panel-box{border:1px solid #95a5b8;background:#fff;border-radius:4px;overflow:hidden;margin-bottom:4px}
.panel-hdr{background:#34495e;color:#ecf0f1;padding:6px 10px;font-weight:700;font-size:13px;display:flex;justify-content:space-between;align-items:center}
.tbl{width:100%;border-collapse:collapse;font-size:11px}
.tbl th,.tbl td{border:1px solid #ccc;padding:3px 5px}
.tbl th{background:#34495e;color:#ecf0f1;font-size:11px;position:sticky;top:0;z-index:1}
.tbl td{text-align:left}
.tbl td.r{text-align:right}
.tbl tr:nth-child(even){background:#f4f7fa}
.tbl tr.sel{background:#aed6f1 !important}
.tbl tr.status-i td:last-child{color:#27ae60;font-weight:700}
.tbl tr.status-o td:last-child{color:#c0392b;font-weight:700}
.tbl tr.pending{font-weight:700;background:#fef9e7 !important}
.tbl-wrap{max-height:420px;overflow:auto}
.totals{background:#34495e;color:#ecf0f1;font-weight:700}
.msg{font-weight:700;font-size:13px;margin-left:8px}
.msg.ok{color:#27ae60}
.msg.err{color:#c0392b}
.conn-status{font-size:12px;padding:2px 8px;border-radius:10px;margin-left:8px}
.conn-status.on{background:#27ae60;color:#fff}
.conn-status.off{background:#95a5a6;color:#fff}
.add-row{display:flex;gap:6px;align-items:center;padding:6px 10px;background:#ecf0f1;border-top:1px solid #95a5b8;flex-wrap:wrap}
.add-row label{font-weight:700;font-size:12px}
.add-row input,.add-row select{height:28px;border:1px solid #95a5b8;border-radius:4px;padding:0 6px;font-size:12px}
.import-area{padding:8px;background:#ecf0f1;border:1px solid #95a5b8;border-radius:4px;margin-top:4px}
.import-area textarea{width:100%;height:100px;border:1px solid #95a5b8;border-radius:4px;padding:6px;font-size:12px;font-family:monospace;resize:vertical;box-sizing:border-box}
.import-area .help{font-size:11px;color:#666;margin-top:4px}
</style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <span>{{ $title }}</span>
    <div style="display:flex;align-items:center">
      <span class="conn-status off" id="connStatus">Disconnected</span>
      <div class="msg" id="msg"></div>
    </div>
  </div>

  <!-- Device Connection Toolbar -->
  <div class="toolbar">
    <label>IP Address:</label>
    <input type="text" id="txtIP" value="192.168.1.201" style="width:140px">
    <label>Port:</label>
    <input type="text" id="txtPort" value="4370" style="width:60px">
    <button class="btn btn-primary" id="btnConnect">Connect</button>
    <button class="btn btn-success" id="btnDownloadLogs" disabled>Download Logs</button>
    <button class="btn btn-warning" id="btnUpdateLogs" disabled>Update Logs</button>
    <button class="btn btn-danger" id="btnClearDevice" disabled>Clear Logs (Device)</button>
  </div>

  <!-- Date Range & DB Toolbar -->
  <div class="toolbar">
    <label>From:</label>
    <input type="date" id="date1" value="{{ date('Y-m-d') }}" style="width:150px">
    <label>To:</label>
    <input type="date" id="date2" value="{{ date('Y-m-d') }}" style="width:150px">
    <button class="btn btn-primary" id="btnLoadDB">Load from DB</button>
    <button class="btn btn-warning" id="btnImportFile">Import from File</button>
    <button class="btn btn-danger" id="btnClearDB">Clear Logs (DB)</button>
    <input type="file" id="fileInput" accept=".txt,.csv,.dat" style="display:none">
  </div>

  <div class="panels">
    <!-- Left: Log Entries -->
    <div class="panel-box">
      <div class="panel-hdr">
        <span>Attendance Log <span id="logCount"></span></span>
        <button class="btn" style="height:24px;font-size:10px;padding:0 8px" id="btnDeleteEntry">Delete Selected</button>
      </div>
      <div class="tbl-wrap" id="logWrap">
        <table class="tbl">
          <thead><tr>
            <th style="width:30px">#</th>
            <th style="width:55px">ID No</th>
            <th style="width:65px">Code</th>
            <th>Staff Name</th>
            <th style="width:90px">Date</th>
            <th style="width:70px">Time</th>
            <th style="width:45px">I/O</th>
          </tr></thead>
          <tbody id="logBody"></tbody>
          <tfoot>
            <tr class="totals">
              <td colspan="7" id="totInfo">0 entries</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Manual Add Entry -->
      <div class="add-row">
        <label>Add:</label>
        <select id="addStaff" style="width:170px">
          <option value="">-- Select Staff --</option>
        </select>
        <label>Date:</label>
        <input type="date" id="addDate" value="{{ date('Y-m-d') }}" style="width:130px">
        <label>Time:</label>
        <input type="time" id="addTime" step="1" style="width:100px">
        <button class="btn btn-primary" id="btnAddEntry" style="height:28px">Add</button>
      </div>
    </div>

    <!-- Right: User List + Import -->
    <div>
      <!-- Device User List -->
      <div class="panel-box">
        <div class="panel-hdr">
          <span>User List</span>
          <span id="userSource" style="font-size:10px;font-weight:400">(from DB)</span>
        </div>
        <div class="tbl-wrap" style="max-height:260px">
          <table class="tbl">
            <thead><tr>
              <th style="width:50px">ID No</th>
              <th style="width:65px">Code</th>
              <th>Name</th>
              <th style="width:50px">Priv</th>
            </tr></thead>
            <tbody id="userBody"></tbody>
          </table>
        </div>
      </div>

      <!-- Import Area -->
      <div class="import-area" id="importArea" style="display:none">
        <div style="font-weight:700;margin-bottom:4px">Paste / Import Log Data</div>
        <textarea id="importText" placeholder="Paste attendance data here..."></textarea>
        <div class="help">
          Format per line: <code>IDNo, YYYY-MM-DD, HH:MM:SS</code><br>
          Or: <code>IDNo, DD/MM/YYYY, HH:MM:SS</code>
        </div>
        <div style="margin-top:6px;display:flex;gap:6px">
          <button class="btn btn-success" id="btnParseImport">Parse & Add</button>
          <button class="btn" id="btnCancelImport">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const $  = id => document.getElementById(id);
const api = (u,o) => fetch(u,{...o, headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','Accept':'application/json',...(o?.headers||{})}});

let logEntries = [];
let pendingEntries = [];
let staffUsers = [];
let deviceUsers = [];
let selectedLogIdx = -1;
let isConnected = false;

function showMsg(t, ok=true) {
  const el = $('msg');
  el.textContent = t;
  el.className = 'msg ' + (ok ? 'ok' : 'err');
  if (t) setTimeout(() => { if (el.textContent === t) el.textContent = ''; }, 5000);
}

function setConnected(connected, info) {
  isConnected = connected;
  const el = $('connStatus');
  if (connected) {
    el.textContent = 'Connected' + (info ? ' — ' + info : '');
    el.className = 'conn-status on';
    $('btnConnect').textContent = 'Disconnect';
    $('btnDownloadLogs').disabled = false;
    $('btnClearDevice').disabled = false;
  } else {
    el.textContent = 'Disconnected';
    el.className = 'conn-status off';
    $('btnConnect').textContent = 'Connect';
    $('btnDownloadLogs').disabled = true;
    $('btnClearDevice').disabled = true;
  }
}

function getDeviceParams() {
  return { ip: $('txtIP').value.trim(), port: parseInt($('txtPort').value) || 4370 };
}

/* ── Load Staff Users (from DB) ───────────────────────────────────────── */
async function loadUsers() {
  const r = await api(`{{ url("/api/staff-log-update/users") }}`).then(r=>r.json());
  if (!r.ok) return;
  staffUsers = r.rows || [];
  renderUsers(staffUsers.map(u => ({ idno: u.idno, code: u.code, name: u.name, privilege: '' })), '(from DB)');
  const sel = $('addStaff');
  sel.innerHTML = '<option value="">-- Select Staff --</option>';
  staffUsers.forEach(u => {
    const opt = document.createElement('option');
    opt.value = u.idno || u.code;
    opt.dataset.code = u.code;
    opt.dataset.idno = u.idno;
    opt.textContent = `${u.code} - ${u.name}` + (u.idno ? ` (ID:${u.idno})` : '');
    sel.appendChild(opt);
  });
}

function renderUsers(users, source) {
  $('userSource').textContent = source || '';
  const tb = $('userBody');
  tb.innerHTML = '';
  users.forEach(u => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${u.idno || ''}</td><td>${u.code || ''}</td><td>${u.name || ''}</td><td>${u.privilege !== '' ? u.privilege : ''}</td>`;
    tb.appendChild(tr);
  });
}

/* ── Connect to Device ────────────────────────────────────────────────── */
$('btnConnect').onclick = async () => {
  if (isConnected) {
    setConnected(false);
    showMsg('Disconnected');
    // Restore DB user list
    renderUsers(staffUsers.map(u => ({ idno: u.idno, code: u.code, name: u.name, privilege: '' })), '(from DB)');
    return;
  }

  const p = getDeviceParams();
  if (!p.ip) { showMsg('Enter IP address', false); return; }

  showMsg('Connecting to ' + p.ip + '...');
  $('btnConnect').disabled = true;

  const r = await api(`{{ url("/api/staff-log-update/connect") }}`, {
    method: 'POST',
    body: JSON.stringify(p),
  }).then(r=>r.json());

  $('btnConnect').disabled = false;

  if (r.ok) {
    setConnected(true, r.deviceName || '');
    showMsg('Connected! ' + (r.deviceName || ''));

    // Download user list from device
    showMsg('Downloading users...');
    const ur = await api(`{{ url("/api/staff-log-update/download-users") }}`, {
      method: 'POST',
      body: JSON.stringify(p),
    }).then(r=>r.json());

    if (ur.ok) {
      deviceUsers = ur.rows || [];
      renderUsers(deviceUsers, '(from Device)');
      showMsg(`Connected. ${deviceUsers.length} users on device.`);
    }
  } else {
    showMsg(r.message || 'Connection failed', false);
  }
};

/* ── Download Logs from Device ────────────────────────────────────────── */
$('btnDownloadLogs').onclick = async () => {
  const p = getDeviceParams();
  showMsg('Downloading logs from device...');
  $('btnDownloadLogs').disabled = true;

  const r = await api(`{{ url("/api/staff-log-update/download-logs") }}`, {
    method: 'POST',
    body: JSON.stringify({ ...p, date1: $('date1').value, date2: $('date2').value }),
  }).then(r=>r.json());

  $('btnDownloadLogs').disabled = false;

  if (r.ok) {
    pendingEntries = r.rows || [];
    logEntries = [];
    renderLogs();
    $('btnUpdateLogs').disabled = false;
    showMsg(`Downloaded ${r.total || pendingEntries.length} log entries`);
  } else {
    showMsg(r.message || 'Download failed', false);
  }
};

/* ── Update Logs to DB ────────────────────────────────────────────────── */
$('btnUpdateLogs').onclick = async () => {
  if (pendingEntries.length === 0) {
    showMsg('No pending entries to update', false);
    return;
  }

  if (!confirm(`Update ${pendingEntries.length} log entries to database?`)) return;

  showMsg('Updating...');
  const r = await api(`{{ url("/api/staff-log-update/update") }}`, {
    method: 'POST',
    body: JSON.stringify({ entries: pendingEntries }),
  }).then(r=>r.json());

  if (r.ok) {
    showMsg(r.message || 'Updated');
    pendingEntries = [];
    $('btnUpdateLogs').disabled = true;
    // Reload from DB
    $('btnLoadDB').click();
  } else {
    showMsg(r.message || 'Failed', false);
  }
};

/* ── Clear Device Logs ────────────────────────────────────────────────── */
$('btnClearDevice').onclick = async () => {
  if (!confirm('Do you want to delete ALL records from machine?\n\nThis cannot be undone!')) return;

  const p = getDeviceParams();
  showMsg('Clearing device logs...');

  const r = await api(`{{ url("/api/staff-log-update/clear-device") }}`, {
    method: 'POST',
    body: JSON.stringify(p),
  }).then(r=>r.json());

  if (r.ok) {
    showMsg('Device logs cleared');
  } else {
    showMsg(r.message || 'Failed', false);
  }
};

/* ── Load Logs from DB ────────────────────────────────────────────────── */
$('btnLoadDB').onclick = async () => {
  showMsg('Loading logs from database...');
  pendingEntries = [];
  $('btnUpdateLogs').disabled = true;

  const r = await api(`{{ url("/api/staff-log-update/logs") }}?date1=${$('date1').value}&date2=${$('date2').value}`).then(r=>r.json());
  if (!r.ok) { showMsg(r.message || 'Failed', false); return; }
  logEntries = r.rows || [];
  renderLogs();
  showMsg(`Loaded ${logEntries.length} entries from database`);
};

/* ── Render Logs ──────────────────────────────────────────────────────── */
function renderLogs() {
  const tb = $('logBody');
  tb.innerHTML = '';
  const all = [...logEntries, ...pendingEntries];

  all.forEach((e, i) => {
    const tr = document.createElement('tr');
    const isPending = i >= logEntries.length;
    if (isPending) tr.className = 'pending';
    else if (e.status === 'I') tr.className = 'status-i';
    else if (e.status === 'O') tr.className = 'status-o';

    const dateStr = e.tdate ? (typeof e.tdate === 'string' ? e.tdate.substring(0, 10) : e.tdate) : '';
    const timeStr = e.ttime || '';
    const statusStr = isPending ? '*' : (e.status || '');

    tr.innerHTML = `
      <td>${i+1}</td>
      <td>${e.idno || ''}</td>
      <td>${e.scode || ''}</td>
      <td>${e.staffname || ''}</td>
      <td>${dateStr}</td>
      <td>${timeStr}</td>
      <td>${statusStr}</td>`;
    tr.style.cursor = 'pointer';
    tr.onclick = () => {
      tb.querySelectorAll('tr').forEach(r => r.classList.remove('sel'));
      tr.classList.add('sel');
      selectedLogIdx = i;
    };
    tb.appendChild(tr);
  });

  const total = all.length;
  const pending = pendingEntries.length;
  $('logCount').textContent = `(${total} entries${pending ? ', ' + pending + ' pending' : ''})`;
  $('totInfo').textContent = `${total} entries` + (pending ? ` (${pending} pending — click Update Logs)` : '');

  if (pending > 0) $('btnUpdateLogs').disabled = false;
}

/* ── Manual Add Entry ─────────────────────────────────────────────────── */
$('btnAddEntry').onclick = () => {
  const sel = $('addStaff');
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) { showMsg('Select a staff member', false); return; }

  const tdate = $('addDate').value;
  const ttime = $('addTime').value;
  if (!tdate || !ttime) { showMsg('Enter date and time', false); return; }

  const idno = opt.dataset.idno || opt.value;
  const scode = opt.dataset.code || '';
  const staffname = staffUsers.find(u => u.code === scode)?.name || '';
  const ttimeFmt = ttime.length === 5 ? ttime + ':00' : ttime;

  pendingEntries.push({ idno, scode, staffname, tdate, ttime: ttimeFmt, status: '?' });
  renderLogs();
  $('addTime').value = '';
  $('addTime').focus();
  showMsg('Entry added (pending)');
};

/* ── Delete Selected Entry ────────────────────────────────────────────── */
$('btnDeleteEntry').onclick = async () => {
  if (selectedLogIdx < 0) { showMsg('Select an entry first', false); return; }

  const all = [...logEntries, ...pendingEntries];
  const entry = all[selectedLogIdx];
  if (!entry) return;

  const isPending = selectedLogIdx >= logEntries.length;

  if (isPending) {
    pendingEntries.splice(selectedLogIdx - logEntries.length, 1);
    selectedLogIdx = -1;
    renderLogs();
    showMsg('Pending entry removed');
    return;
  }

  if (!confirm('Delete this log entry from database?')) return;

  const r = await api(`{{ url("/api/staff-log-update/delete") }}`, {
    method: 'POST',
    body: JSON.stringify({ idno: entry.idno, tdate: entry.tdate, ttime: entry.ttime }),
  }).then(r=>r.json());

  if (r.ok) {
    logEntries.splice(selectedLogIdx, 1);
    selectedLogIdx = -1;
    renderLogs();
    showMsg('Entry deleted');
  } else {
    showMsg(r.message || 'Failed', false);
  }
};

/* ── Import from File ─────────────────────────────────────────────────── */
$('btnImportFile').onclick = () => {
  if ($('importArea').style.display === 'none') {
    $('importArea').style.display = '';
    $('fileInput').click();
  } else {
    $('importArea').style.display = 'none';
  }
};

$('fileInput').addEventListener('change', async function() {
  const file = this.files[0];
  if (!file) return;
  this.value = '';
  const text = await file.text();
  $('importText').value = text;
  $('importArea').style.display = '';
});

$('btnCancelImport').onclick = () => {
  $('importArea').style.display = 'none';
  $('importText').value = '';
};

$('btnParseImport').onclick = () => {
  const text = $('importText').value.trim();
  if (!text) { showMsg('No data to parse', false); return; }

  const lines = text.split(/[\r\n]+/).filter(l => l.trim());
  let added = 0;

  const idMap = {};
  staffUsers.forEach(u => {
    if (u.idno) idMap[u.idno] = u;
    idMap[u.code] = u;
  });

  lines.forEach(line => {
    const parts = line.split(/[,\t]+/).map(p => p.trim());
    if (parts.length < 3) return;

    const idno = parts[0];
    let tdate = parts[1];
    const ttime = parts[2];

    if (/^\d{2}\/\d{2}\/\d{4}$/.test(tdate)) {
      const [dd, mm, yyyy] = tdate.split('/');
      tdate = `${yyyy}-${mm}-${dd}`;
    }

    if (!tdate || !ttime) return;

    const staff = idMap[idno];
    pendingEntries.push({
      idno, scode: staff?.code || '', staffname: staff?.name || '',
      tdate, ttime, status: '?',
    });
    added++;
  });

  $('importArea').style.display = 'none';
  $('importText').value = '';
  renderLogs();
  showMsg(`Parsed ${added} entries (pending)`);
};

/* ── Clear DB Logs ────────────────────────────────────────────────────── */
$('btnClearDB').onclick = async () => {
  const d1 = $('date1').value, d2 = $('date2').value;
  if (!d1 || !d2) { showMsg('Set date range first', false); return; }
  if (!confirm(`Delete ALL log entries from ${d1} to ${d2} from database?\n\nThis cannot be undone!`)) return;

  const r = await api(`{{ url("/api/staff-log-update/clear") }}`, {
    method: 'POST',
    body: JSON.stringify({ date1: d1, date2: d2 }),
  }).then(r=>r.json());

  if (r.ok) {
    showMsg(r.message || 'Cleared');
    logEntries = [];
    pendingEntries = [];
    renderLogs();
  } else {
    showMsg(r.message || 'Failed', false);
  }
};

/* ── Keyboard ─────────────────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') window.close();
});

/* ── Init ─────────────────────────────────────────────────────────────── */
loadUsers();
</script>
</body>
</html>
