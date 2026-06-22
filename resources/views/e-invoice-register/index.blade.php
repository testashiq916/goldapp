<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;color:#1e293b;font-size:13px;height:100vh;overflow:hidden;display:flex;flex-direction:column}

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:8px 14px;display:flex;flex-direction:column;gap:6px;flex-shrink:0}
.tb-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap;margin-right:4px}
.tb-lbl{color:rgba(255,255,255,.8);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.tb-input,.tb-select{height:28px;border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:0 6px;font-size:11px;background:rgba(255,255,255,.12);color:#fff;outline:none}
.tb-input:focus,.tb-select:focus{border-color:rgba(255,255,255,.6);background:rgba(255,255,255,.2)}
.tb-select option{background:#1e3a5f;color:#fff}
.f-group{display:flex;align-items:center;gap:3px}
.sep{width:1px;height:22px;background:rgba(255,255,255,.2);margin:0 1px}
.btn{padding:4px 12px;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-show{background:#3b82f6;color:#fff}.btn-show:hover{background:#2563eb}
.btn-out{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)}.btn-out:hover{background:rgba(255,255,255,.25)}

.grid-wrap{flex:1;overflow:auto}
table{width:100%;border-collapse:collapse;background:#fff;font-size:11px}
th{position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #e2e8f0;padding:6px 8px;font-size:9px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:left;white-space:nowrap;z-index:2}
th.num{text-align:right}
td{padding:5px 8px;border-bottom:1px solid #f1f5f9;white-space:nowrap}
td.num{text-align:right;font-variant-numeric:tabular-nums}
tr:hover td{background:#f8fafc}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;letter-spacing:.3px}
.badge-gen{background:#dcfce7;color:#166534}
.badge-cnl{background:#fee2e2;color:#991b1b}
.badge-fail{background:#fef3c7;color:#92400e}
.row-act{display:flex;gap:6px}
.mini{padding:3px 8px;border:1px solid #cbd5e1;background:#fff;border-radius:5px;font-size:10px;font-weight:700;cursor:pointer;color:#1e40af}
.mini:hover{background:#eff6ff;border-color:#3b82f6}
.mini.danger{color:#991b1b}.mini.danger:hover{background:#fef2f2;border-color:#dc2626}

.summary{background:#fff;border-top:2px solid #e2e8f0;padding:6px 14px;display:flex;gap:16px;flex-wrap:wrap;font-size:11px;flex-shrink:0}
.summary span{color:#64748b}.summary b{color:#1e40af}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:12px;z-index:9999;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}.toast.err{background:#dc2626}

.modal-bg{position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;z-index:500}
.modal-bg.show{display:flex}
.modal{background:#fff;border-radius:10px;width:min(900px,94vw);max-height:90vh;overflow:auto;box-shadow:0 24px 64px rgba(0,0,0,.25)}
.modal-head{padding:12px 18px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;border-radius:10px 10px 0 0}
.modal-head h2{font-size:14px;color:#1e3a5f}
.modal-head .x{background:none;border:none;font-size:20px;cursor:pointer;color:#64748b}
.modal-body{padding:16px 20px}
.modal-foot{padding:10px 18px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;background:#f8fafc;border-radius:0 0 10px 10px}
.kv{display:grid;grid-template-columns:160px 1fr;gap:6px 12px;font-size:12px}
.kv .k{color:#64748b;font-weight:600}
.kv .v{color:#0f172a;word-break:break-word}
.sec-title{font-size:11px;font-weight:700;color:#1e3a5f;margin:14px 0 6px;text-transform:uppercase;letter-spacing:.3px;border-bottom:1px solid #e2e8f0;padding-bottom:4px}
pre.json{background:#0f172a;color:#cbd5e1;padding:10px;border-radius:6px;font-size:10.5px;line-height:1.45;max-height:280px;overflow:auto;white-space:pre-wrap;word-break:break-word}
.qr{padding:10px;background:#f1f5f9;border-radius:6px;font-family:monospace;font-size:10px;max-height:120px;overflow:auto;word-break:break-all}

.fld{display:flex;flex-direction:column;gap:4px;margin-bottom:10px}
.fld label{font-size:11px;font-weight:700;color:#475569}
.fld input,.fld select,.fld textarea{padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;font-family:inherit;background:#fff;outline:none}
.fld input:focus,.fld select:focus,.fld textarea:focus{border-color:#3b82f6}
.btn-prim{background:#dc2626;color:#fff;padding:7px 16px;border:none;border-radius:6px;font-weight:700;cursor:pointer}
.btn-prim:hover{background:#b91c1c}
.btn-sec{background:#e2e8f0;color:#1e293b;padding:7px 16px;border:none;border-radius:6px;font-weight:700;cursor:pointer}

.empty{padding:60px 0;text-align:center;color:#94a3b8;font-size:13px}
</style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>

<div class="toolbar">
  <div class="tb-row">
    <h1>{{ $title }}</h1>
    <div class="sep"></div>
    <div class="f-group">
      <span class="tb-lbl">From</span>
      <input type="date" id="date1" class="tb-input" value="{{ $date1 }}" style="width:130px">
    </div>
    <div class="f-group">
      <span class="tb-lbl">To</span>
      <input type="date" id="date2" class="tb-input" value="{{ $date2 }}" style="width:130px">
    </div>
    <div class="f-group">
      <span class="tb-lbl">Status</span>
      <select id="status" class="tb-select" style="width:120px">
        <option value="">All</option>
        <option value="generated">Generated</option>
        <option value="failed">Failed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>
    <div class="f-group">
      <span class="tb-lbl">Search</span>
      <input type="text" id="q" class="tb-input" placeholder="Bill / Cust / IRN" style="width:200px">
    </div>
    <button class="btn btn-show" id="btnShow">Show</button>
    <button class="btn btn-out" id="btnRefresh">Refresh</button>
    <button class="btn btn-out" id="btnIrnLookup">IRN Lookup</button>
  </div>
</div>

<div class="grid-wrap" id="gridWrap">
  <table id="grid">
    <thead>
      <tr>
        <th style="width:36px">#</th>
        <th>Bill No</th>
        <th>Date</th>
        <th>Customer</th>
        <th>GSTIN</th>
        <th class="num">Net Total</th>
        <th>Status</th>
        <th>IRN</th>
        <th>Ack No</th>
        <th>Ack Date</th>
        <th>Generated</th>
        <th style="width:240px">Actions</th>
      </tr>
    </thead>
    <tbody id="tbody">
      <tr><td colspan="12" class="empty">Click <b>Show</b> to load e-invoices.</td></tr>
    </tbody>
  </table>
</div>

<div class="summary" id="summary">
  <span>Total Records: <b id="sumCount">0</b></span>
  <span>Generated: <b id="sumGen">0</b></span>
  <span>Failed: <b id="sumFail">0</b></span>
  <span>Cancelled: <b id="sumCnl">0</b></span>
  <span>Net Amount: <b id="sumAmt">0.00</b></span>
</div>

<div class="modal-bg" id="modalDetail">
  <div class="modal">
    <div class="modal-head">
      <h2 id="detTitle">E-Invoice Details</h2>
      <button class="x" onclick="closeModal('modalDetail')">&times;</button>
    </div>
    <div class="modal-body" id="detBody"></div>
    <div class="modal-foot">
      <button class="btn-sec" onclick="closeModal('modalDetail')">Close</button>
    </div>
  </div>
</div>

<div class="modal-bg" id="modalCancel">
  <div class="modal" style="width:min(480px,92vw)">
    <div class="modal-head">
      <h2>Cancel E-Invoice</h2>
      <button class="x" onclick="closeModal('modalCancel')">&times;</button>
    </div>
    <div class="modal-body">
      <div style="font-size:12px;background:#fff7ed;border-left:3px solid #f59e0b;padding:8px 10px;margin-bottom:12px;color:#9a3412">
        Bill <b id="canBillNo"></b> &mdash; cancellation will be sent to the GST e-invoice portal and is irreversible.
      </div>
      <div class="fld">
        <label>Reason</label>
        <select id="canReason">
          <option value="Duplicate">1 &mdash; Duplicate</option>
          <option value="Data Entry Mistake">2 &mdash; Data Entry Mistake</option>
          <option value="Order Cancelled">3 &mdash; Order Cancelled</option>
          <option value="Others">4 &mdash; Others</option>
        </select>
      </div>
      <div class="fld">
        <label>Remarks (optional)</label>
        <textarea id="canRemark" rows="3" placeholder="Optional notes for the audit trail"></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-sec" onclick="closeModal('modalCancel')">Close</button>
      <button class="btn-prim" id="btnCancelConfirm">Cancel E-Invoice</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const API = {
  data:    @json(url('/api/e-invoice-register/data')),
  details: @json(url('/api/e-invoice-register/details')),
  reprint: @json(url('/api/e-invoice-register/reprint')),
  irnLookup: @json(url('/api/e-invoice-register/irn-lookup')),
  cancel:  @json(url('/api/e-invoice-register/cancel')),
  pdf:     @json(url('/e-invoice-pdf')),
};
let rowsCache = [];

function $(id){ return document.getElementById(id); }
function toast(msg, kind){
  const t = $('toast');
  t.textContent = msg;
  t.className = 'toast ' + (kind || 'ok');
  t.style.display = 'block';
  clearTimeout(window._tt);
  window._tt = setTimeout(() => t.style.display = 'none', 3000);
}
function openModal(id){ $(id).classList.add('show'); }
function closeModal(id){ $(id).classList.remove('show'); }
function fmtAmt(v){ return Number(v||0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}); }

async function load(){
  const params = new URLSearchParams({
    date1: $('date1').value || '',
    date2: $('date2').value || '',
    status: $('status').value || '',
    q: $('q').value.trim()
  });
  let r;
  try {
    const resp = await fetch(API.data + '?' + params, {credentials:'same-origin'});
    r = await resp.json();
  } catch (e) {
    toast('Failed to load: ' + (e && e.message ? e.message : 'network error'), 'err');
    return;
  }
  if (!r.ok){ toast(r.message || 'Failed to load e-invoices.', 'err'); return; }
  rowsCache = r.rows || [];
  render();
}

function render(){
  const tb = $('tbody');
  if (!rowsCache.length){
    tb.innerHTML = '<tr><td colspan="12" class="empty">No e-invoices found.</td></tr>';
    $('sumCount').textContent = '0';
    $('sumGen').textContent = '0';
    $('sumFail').textContent = '0';
    $('sumCnl').textContent = '0';
    $('sumAmt').textContent = '0.00';
    return;
  }
  let gen=0, fail=0, cnl=0, amt=0;
  tb.innerHTML = rowsCache.map((r,i) => {
    const cancelled = r.status === 'cancelled';
    const failed = r.status === 'failed';
    if (cancelled) cnl++; else if (failed) fail++; else gen++;
    amt += Number(r.net_total || 0);
    const badge = cancelled
      ? '<span class="badge badge-cnl">Cancelled</span>'
      : failed
        ? '<span class="badge badge-fail">Failed</span>'
      : '<span class="badge badge-gen">Generated</span>';
    const pdfBtn = r.has_pdf
      ? `<button class="mini" style="color:#9a3412;border-color:#fdba74" onclick="openPdf('${esc(r.bill_no)}')">PDF</button>`
      : '';
    const actBtns = `
      <div class="row-act">
        <button class="mini" onclick="showDetails('${esc(r.bill_no)}')">Details</button>
        ${pdfBtn}
        ${failed ? '' : `<button class="mini" onclick="reprint('${esc(r.bill_no)}')">Reprint</button>`}
        ${cancelled || failed ? '' : `<button class="mini danger" onclick="askCancel('${esc(r.bill_no)}')">Cancel</button>`}
      </div>`;
    return `<tr>
      <td>${i+1}</td>
      <td><b>${esc(r.bill_no)}</b></td>
      <td>${esc(r.bill_date)}</td>
      <td>${esc(r.customer_name)}${r.customer_code ? ' <span style="color:#94a3b8">(' + esc(r.customer_code) + ')</span>' : ''}</td>
      <td>${esc(r.gst_no)}</td>
      <td class="num">${fmtAmt(r.net_total)}</td>
      <td>${badge}</td>
      <td title="${esc(r.irn)}">${esc(r.irn ? r.irn.slice(0,18) + (r.irn.length>18?'…':'') : '')}</td>
      <td>${esc(r.ack_no)}</td>
      <td>${esc(r.ack_date)}</td>
      <td>${esc(r.generated_at)}</td>
      <td>${actBtns}</td>
    </tr>`;
  }).join('');
  $('sumCount').textContent = rowsCache.length;
  $('sumGen').textContent = gen;
  $('sumFail').textContent = fail;
  $('sumCnl').textContent = cnl;
  $('sumAmt').textContent = fmtAmt(amt);
}

function esc(s){
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function showDetails(billNo){
  const r = await fetch(API.details + '?bill_no=' + encodeURIComponent(billNo), {credentials:'same-origin'}).then(r=>r.json()).catch(()=>({ok:false}));
  if (!r.ok){ toast(r.message || 'Not found.', 'err'); return; }
  const d = r.data;
  $('detTitle').textContent = 'E-Invoice Details — ' + d.bill_no;
  const isJson = v => typeof v === 'object' && v !== null;
  $('detBody').innerHTML = `
    <div class="kv">
      <div class="k">Bill No</div><div class="v"><b>${esc(d.bill_no)}</b></div>
      <div class="k">Bill Date</div><div class="v">${esc(d.bill_date)}</div>
      <div class="k">Customer</div><div class="v">${esc(d.customer_name)}${d.customer_code ? ' (' + esc(d.customer_code) + ')' : ''}</div>
      <div class="k">GSTIN</div><div class="v">${esc(d.gst_no)}</div>
      <div class="k">Net Total</div><div class="v">${fmtAmt(d.net_total)}</div>
      <div class="k">Status</div><div class="v">${statusBadge(d.status)}</div>
      <div class="k">IRN</div><div class="v" style="font-family:monospace;font-size:11px">${esc(d.irn)}</div>
      <div class="k">Ack No</div><div class="v">${esc(d.ack_no)}</div>
      <div class="k">Ack Date</div><div class="v">${esc(d.ack_date)}</div>
      <div class="k">Provider</div><div class="v">${esc(d.provider)}</div>
      <div class="k">Generated By</div><div class="v">${esc(d.generated_by)} &mdash; ${esc(d.generated_at)}</div>
      ${d.cancelled_at ? `
        <div class="k">Cancel Reason</div><div class="v">${esc(d.cancel_reason)}</div>
        <div class="k">Cancel Remark</div><div class="v">${esc(d.cancel_remark)}</div>
        <div class="k">Cancelled By</div><div class="v">${esc(d.cancelled_by)} &mdash; ${esc(d.cancelled_at)}</div>
      ` : ''}
    </div>
    ${d.signed_qr_code ? `<div class="sec-title">Signed QR Code</div><div class="qr">${esc(d.signed_qr_code)}</div>` : ''}
    <div class="sec-title">Request Payload</div>
    <pre class="json">${esc(isJson(d.request_payload) ? JSON.stringify(d.request_payload, null, 2) : (d.request_payload || ''))}</pre>
    <div class="sec-title">Response Payload</div>
    <pre class="json">${esc(isJson(d.response_payload) ? JSON.stringify(d.response_payload, null, 2) : (d.response_payload || ''))}</pre>
    ${d.cancel_response ? `<div class="sec-title">Cancel Response</div><pre class="json">${esc(isJson(d.cancel_response) ? JSON.stringify(d.cancel_response, null, 2) : d.cancel_response)}</pre>` : ''}
  `;
  openModal('modalDetail');
}

function statusBadge(status){
  if (status === 'cancelled') return '<span class="badge badge-cnl">Cancelled</span>';
  if (status === 'failed') return '<span class="badge badge-fail">Failed</span>';
  return '<span class="badge badge-gen">Generated</span>';
}

async function reprint(billNo){
  const r = await fetch(API.reprint + '?bill_no=' + encodeURIComponent(billNo), {credentials:'same-origin'}).then(r=>r.json()).catch(()=>({ok:false}));
  if (!r.ok || !r.url){ toast(r.message || 'Reprint failed.', 'err'); return; }
  window.open(r.url, '_blank');
}

function openPdf(billNo){
  window.open(API.pdf + '?bill_no=' + encodeURIComponent(billNo) + '&inline=1', '_blank');
}

async function lookupIrn(){
  const raw = prompt('Enter IRN');
  const irn = String(raw || '').replace(/\s+/g, '').trim();
  if (!irn) return;
  if (!/^[a-fA-F0-9]{64}$/.test(irn)){
    toast('Enter a valid 64-character IRN.', 'err');
    return;
  }
  let r;
  try {
    r = await fetch(API.irnLookup + '?irn=' + encodeURIComponent(irn), {credentials:'same-origin'}).then(r=>r.json());
  } catch (e) {
    toast('IRN lookup failed.', 'err');
    return;
  }
  if (!r.ok){
    toast(r.message || 'IRN lookup failed.', 'err');
    return;
  }
  const d = r.data || {};
  $('detTitle').textContent = 'IRN Lookup Details';
  $('detBody').innerHTML = `
    <div class="kv">
      <div class="k">IRN</div><div class="v" style="font-family:monospace;font-size:11px">${esc(d.irn)}</div>
      <div class="k">Ack No</div><div class="v">${esc(d.ack_no)}</div>
      <div class="k">Ack Date</div><div class="v">${esc(d.ack_date)}</div>
      <div class="k">Status</div><div class="v"><b>${esc(d.status)}</b></div>
      <div class="k">E-Way Bill</div><div class="v">${esc(d.eway_bill_no || '')}</div>
      <div class="k">JSON Saved</div><div class="v" style="font-family:monospace;font-size:11px">${esc(r.json_saved || '')}</div>
    </div>
    ${d.signed_qr_code ? `<div class="sec-title">Signed QR Code</div><div class="qr">${esc(d.signed_qr_code)}</div>` : ''}
    ${d.signed_invoice ? `<div class="sec-title">Signed Invoice</div><div class="qr">${esc(d.signed_invoice)}</div>` : ''}
    <div class="sec-title">Provider Response</div>
    <pre class="json">${esc(JSON.stringify(d.response_payload || {}, null, 2))}</pre>
  `;
  openModal('modalDetail');
}

let pendingCancel = '';
function askCancel(billNo){
  pendingCancel = billNo;
  $('canBillNo').textContent = billNo;
  $('canReason').value = 'Duplicate';
  $('canRemark').value = '';
  openModal('modalCancel');
}

$('btnCancelConfirm').addEventListener('click', async () => {
  if (!pendingCancel) return;
  const btn = $('btnCancelConfirm');
  btn.disabled = true;
  btn.textContent = 'Cancelling…';
  try {
    const r = await fetch(API.cancel, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
      body: JSON.stringify({
        bill_no: pendingCancel,
        reason: $('canReason').value,
        remark: $('canRemark').value.trim()
      })
    }).then(r=>r.json());
    if (!r.ok){ toast(r.message || 'Cancel failed.', 'err'); return; }
    toast('E-invoice cancelled.', 'ok');
    closeModal('modalCancel');
    load();
  } catch (e) {
    toast('Cancel failed.', 'err');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Cancel E-Invoice';
  }
});

$('btnShow').addEventListener('click', load);
$('btnRefresh').addEventListener('click', load);
$('btnIrnLookup').addEventListener('click', lookupIrn);
$('q').addEventListener('keydown', e => { if (e.key === 'Enter') load(); });

// Auto-load on first open
load();
</script>
</body>
</html>
