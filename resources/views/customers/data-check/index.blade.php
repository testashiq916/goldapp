<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
@include('customers._style')
@include('partials.print-layout-head')
<style>
.pill.warn{border-color:#f5c16c;background:#fff9eb;color:#8a5300;font-weight:700}
.issue{font-weight:800;color:#173b63}
.small{font-size:11px;color:#64748b}
.action-btn{padding:5px 12px;border:1px solid #0b7b3b;border-radius:6px;background:#ecfff4;color:#065f2b;font-weight:800;cursor:pointer}
.action-btn:disabled{opacity:.6;cursor:wait}
tr.issue-credit td{background:#fffdf4}
tr.issue-balance td{background:#fff8f8}
tr.issue-link td{background:#f8fbff}
tr.issue-opening td{background:#f7fff8}
tr.issue-ledger td{background:#fff7fb}
</style>
</head>
<body>
<div class="wrap">
  <h1>{{ $title }}</h1>
  <div class="toolbar">
    <div class="field"><label>From Date</label><input type="date" id="date1" value="{{ $date1 }}"></div>
    <div class="field"><label>To Date</label><input type="date" id="date2" value="{{ $date2 }}"></div>
    <div class="field">
      <label>Check</label>
      <select id="check">
        <option value="all">All Checks</option>
        <option value="credit">Credit Bills</option>
        <option value="balance_mismatch">Balance Mismatch</option>
        <option value="bill_no_issue">Bill No Issue</option>
        <option value="missing_link">Missing Customer Link</option>
        <option value="opening_mismatch">Opening Balance Missing/Mismatch</option>
        <option value="ledger_mismatch">Ledger / Receivable Summary Mismatch</option>
        <option value="ledger_posting_issue">Ledger Posting Issue</option>
      </select>
    </div>
    <div class="field">
      <label>Sort On</label>
      <select id="sort">
        <option value="date">Date</option>
        <option value="customer">Customer Name</option>
        <option value="code">Code</option>
        <option value="billno">Bill No</option>
        <option value="issue">Issue</option>
        <option value="balance">Balance High</option>
        <option value="difference">Diff High</option>
      </select>
    </div>
    <div class="field wide"><label>Search</label><input type="text" id="search" placeholder="Customer / code / bill no"></div>
    <div class="field" style="justify-content:flex-end">
      <button class="primary" onclick="loadData()">Show</button>
      <button onclick="updateSelected()">Update Selected</button>
      <button onclick="exportCsv()">To CSV</button>
      <button onclick="window.print()">Print</button>
    </div>
  </div>

  <div class="pills" id="pills" style="display:none">
    <span class="pill" id="p_count"></span>
    <span class="pill" id="p_bill"></span>
    <span class="pill" id="p_received"></span>
    <span class="pill red" id="p_balance"></span>
    <span class="pill warn" id="p_diff"></span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
      <tr>
        <th>Sel</th>
        <th>#</th>
        <th>Issue</th>
        <th>Date</th>
        <th>Bill No</th>
        <th>Code</th>
        <th>Customer</th>
        <th>Mobile</th>
        <th class="num">Bill Amt</th>
        <th class="num">Received</th>
        <th class="num">Balance</th>
        <th class="num">Expected</th>
        <th class="num">Diff</th>
        <th>Details</th>
        <th>Update</th>
      </tr>
      </thead>
      <tbody id="tbody"><tr><td colspan="15" class="empty">Choose check and press Show.</td></tr></tbody>
    </table>
  </div>
</div>
<script>
let currentRows = [];
const dataCheckUrl = @json(url('/api/customers/data-check'));
const updateIssueUrl = @json(url('/api/customers/data-check/update'));
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function fmt(n){return parseFloat(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function esc(s){return String(s ?? '').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function fmtDate(d){if(!d)return ''; const p=String(d).split('-'); return p.length===3 ? p[2]+'/'+p[1]+'/'+p[0] : d;}
function rowClass(issue){
  issue = String(issue || '').toLowerCase();
  if(issue.includes('credit')) return 'issue-credit';
  if(issue.includes('balance')) return 'issue-balance';
  if(issue.includes('link')) return 'issue-link';
  if(issue.includes('opening')) return 'issue-opening';
  if(issue.includes('ledger') || issue.includes('summary')) return 'issue-ledger';
  return '';
}

function loadData(){
  const tbody = document.getElementById('tbody');
  tbody.innerHTML = '<tr><td colspan="15" class="empty">Checking data...</td></tr>';
  document.getElementById('pills').style.display = 'none';
  currentRows = [];

  const params = new URLSearchParams({
    date1: document.getElementById('date1').value,
    date2: document.getElementById('date2').value,
    check: document.getElementById('check').value,
    sort: document.getElementById('sort').value,
    search: document.getElementById('search').value
  });

  fetch(dataCheckUrl + '?' + params.toString())
    .then(r => r.json())
    .then(d => {
      if(!d.ok){
        tbody.innerHTML = '<tr><td colspan="15" class="empty">Error loading data.</td></tr>';
        return;
      }
      currentRows = d.rows || [];
      if(!currentRows.length){
        tbody.innerHTML = '<tr><td colspan="15" class="empty">No issue found for this check.</td></tr>';
        return;
      }
      tbody.innerHTML = currentRows.map((r,i) => `<tr class="${rowClass(r.issue)}">
        <td>${r.can_update ? `<input type="checkbox" class="row-select" value="${i}">` : ''}</td>
        <td>${i+1}</td>
        <td class="issue">${esc(r.issue)}</td>
        <td>${fmtDate(r.date)}</td>
        <td>${esc(r.billno)}</td>
        <td>${esc(r.code)}</td>
        <td>${esc(r.name)}</td>
        <td>${esc(r.mobile)}</td>
        <td class="num">${fmt(r.billamt)}</td>
        <td class="num">${fmt(r.received)}</td>
        <td class="num bal">${fmt(r.balance)}</td>
        <td class="num">${fmt(r.expected)}</td>
        <td class="num">${fmt(r.difference)}</td>
        <td>${esc(r.details)}</td>
        <td>${r.can_update ? `<button class="action-btn" onclick="updateIssue(${i}, this)">${esc(actionLabel(r))}</button>` : '<span class="small">Manual</span>'}</td>
      </tr>`).join('');

      const t = d.totals || {};
      document.getElementById('p_count').textContent = 'Rows: ' + (t.count || 0);
      document.getElementById('p_bill').textContent = 'Bill Amt: ' + fmt(t.billamt);
      document.getElementById('p_received').textContent = 'Received: ' + fmt(t.received);
      document.getElementById('p_balance').textContent = 'Balance: ' + fmt(t.balance);
      document.getElementById('p_diff').textContent = 'Diff: ' + fmt(t.difference);
      document.getElementById('pills').style.display = 'flex';
    })
    .catch(() => {
      tbody.innerHTML = '<tr><td colspan="15" class="empty">Error loading data.</td></tr>';
    });
}

function actionLabel(row){
  if(row.update_action === 'clear_orphan_sales_ledger' || row.update_action === 'clear_daybook_row') return 'Clear';
  return row.update_action === 'sync_bill_balance_to_ledger' ? 'Sync A/R' : 'Update Bal';
}

function confirmText(row){
  if(row.update_action === 'clear_orphan_sales_ledger'){
    return 'Clear this orphan sales ledger posting? This deletes the daybook posting because the sales bill is missing.';
  }
  if(row.update_action === 'clear_daybook_row'){
    return 'Clear this duplicate opposite ledger row? This deletes only the selected daybook row.';
  }
  if(row.update_action === 'sync_bill_balance_to_ledger'){
    return 'Update selected customer bill balances to match Account Receivable?';
  }
  return 'Update this bill balance to expected value?';
}

function updateIssue(index, btn){
  const row = currentRows[index];
  if(!row || !row.can_update){
    alert('This issue needs manual checking.');
    return;
  }
  if(!confirm(confirmText(row))){
    return;
  }
  btn.disabled = true;
  btn.textContent = 'Updating...';
  postUpdate(row)
    .then(data => {
      alert(data.message + ' New balance: ' + fmt(data.new_balance));
      loadData();
    })
    .catch(err => {
      alert(err.message || 'Update failed.');
      btn.disabled = false;
      btn.textContent = actionLabel(row);
    });
}

function postUpdate(row){
  return fetch(updateIssueUrl, {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken,'Accept':'application/json'},
    body: JSON.stringify({slno: row.ref, sno: row.sno || 0, module: row.module || '', code: row.code, date: row.date || document.getElementById('date2').value, action: row.update_action})
  })
    .then(r => r.json().then(d => ({status:r.status, data:d})))
    .then(({status, data}) => {
      if(!data.ok){
        throw new Error(data.message || ('Update failed (' + status + ')'));
      }
      return data;
    });
}

async function updateSelected(){
  const indexes = Array.from(document.querySelectorAll('.row-select:checked')).map(x => parseInt(x.value, 10));
  if(!indexes.length){
    alert('Select rows to update.');
    return;
  }
  if(!confirm('Update ' + indexes.length + ' selected row(s)?')){
    return;
  }
  let ok = 0;
  let failed = 0;
  for(const index of indexes){
    const row = currentRows[index];
    if(!row || !row.can_update) continue;
    try{
      await postUpdate(row);
      ok++;
    }catch(e){
      failed++;
    }
  }
  alert('Updated: ' + ok + (failed ? ' | Failed: ' + failed : ''));
  loadData();
}

function exportCsv(){
  if(!currentRows.length){
    alert('No data to export.');
    return;
  }
  const headers = ['Issue','Date','Bill No','Code','Customer','Mobile','Bill Amt','Received','Balance','Expected','Diff','Details'];
  const lines = [headers].concat(currentRows.map(r => [
    r.issue, r.date, r.billno, r.code, r.name, r.mobile, r.billamt, r.received, r.balance, r.expected, r.difference, r.details
  ])).map(row => row.map(v => '"' + String(v ?? '').replace(/"/g,'""') + '"').join(','));
  const blob = new Blob([lines.join('\r\n')], {type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'customer_data_check.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}

document.getElementById('search').addEventListener('keydown', e => { if(e.key === 'Enter') loadData(); });
if(new URLSearchParams(window.location.search).get('auto_load') === '1'){ loadData(); }
</script>
</body>
</html>
