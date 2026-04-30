<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Bill To One Code</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:"Segoe UI",Tahoma,sans-serif;background:#f4f7fb;color:#1f2937}
.page{max-width:1180px;margin:0 auto;padding:18px}
.hero{background:#fff;border:1px solid #d9e3ef;border-radius:16px;padding:18px 20px;box-shadow:0 10px 28px rgba(15,23,42,.05)}
.hero h1{margin:0;font-size:24px;color:#173a63}
.hero p{margin:8px 0 0;color:#667085;line-height:1.5}
.warn{margin-top:12px;padding:11px 12px;border-radius:10px;background:#fff8e8;border:1px solid #f3d08a;color:#8a5a00;font-size:12px}
.grid{display:grid;grid-template-columns:1.2fr 1fr;gap:16px;margin-top:16px}
.card{background:#fff;border:1px solid #d9e3ef;border-radius:16px;padding:16px;box-shadow:0 10px 28px rgba(15,23,42,.05)}
.card h2{margin:0 0 12px;font-size:16px;color:#173a63}
label{display:block;font-size:12px;font-weight:700;color:#375b84;margin-bottom:6px}
textarea,input{width:100%;border:1px solid #bfd0e6;border-radius:10px;padding:10px 12px;font:inherit;background:#fbfdff}
textarea{min-height:150px;resize:vertical}
input:focus,textarea:focus{outline:none;border-color:#2f6fb2;box-shadow:0 0 0 3px rgba(47,111,178,.12);background:#fff}
.hint{margin-top:6px;font-size:12px;color:#667085}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
button{appearance:none;border:none;border-radius:10px;padding:10px 14px;font:inherit;font-weight:700;cursor:pointer}
.btn-main{background:#173a63;color:#fff}
.btn-alt{background:#eef4fb;color:#173a63;border:1px solid #cbd9e7}
.btn-danger{background:#af3e3e;color:#fff}
.status{display:none;margin-top:14px;padding:12px;border-radius:10px;font-size:13px}
.status.show{display:block}
.status.ok{background:#e8f7ee;border:1px solid #9ed8ae;color:#15542f}
.status.err{background:#fdecec;border:1px solid #efb6b6;color:#922424}
.checkline{display:flex;gap:8px;align-items:center;margin-top:12px;color:#374151;font-size:13px}
.checkline input{width:16px;height:16px}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{padding:8px 9px;border-bottom:1px solid #e8eef6;text-align:left}
th{background:#f6f9fc;color:#375b84;font-weight:700}
.num{text-align:right}
.empty{padding:14px;border:1px dashed #d5dfeb;border-radius:10px;color:#667085;background:#fbfdff}
.split{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}
@media (max-width:980px){
  .grid,.split,.row{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="page">
  <section class="hero">
    <h1>Bill To One Code</h1>
    <p>Move multiple bill/account codes into one target code in a single action. This updates bill tables and ledger-linked account references together.</p>
    <div class="warn">Use this only when the source codes should no longer stay separate. Example: merge <strong>C001, C003, C004</strong> into <strong>C001</strong> or another existing target code.</div>
  </section>

  <div class="grid">
    <section class="card">
      <h2>Merge Setup</h2>
      <label for="sourceCodes">Source Codes</label>
      <textarea id="sourceCodes" placeholder="Enter one or more codes. Example:&#10;C003&#10;C004&#10;C005"></textarea>
      <div class="hint">You can separate codes using new lines, comma, space, or semicolon.</div>

      <div class="row" style="margin-top:14px">
        <div>
          <label for="targetCode">Target Code</label>
          <input id="targetCode" list="codeHints" placeholder="Example: C001">
        </div>
        <div>
          <label>Existing Codes</label>
          <input value="{{ count($codeHints) }} loaded for quick help" readonly>
        </div>
      </div>

      <datalist id="codeHints">
        @foreach($codeHints as $hint)
          <option value="{{ $hint['code'] }}">{{ $hint['code'] }} - {{ $hint['name'] }}</option>
        @endforeach
      </datalist>

      <label class="checkline">
        <input type="checkbox" id="deleteSources" checked>
        Delete source master codes after merge
      </label>

      <div class="actions">
        <button class="btn-main" type="button" id="btnPreview">Preview Merge</button>
        <button class="btn-danger" type="button" id="btnMerge">Merge Now</button>
        <button class="btn-alt" type="button" id="btnClear">Clear</button>
      </div>

      <div id="status" class="status"></div>
    </section>

    <section class="card">
      <h2>What This Updates</h2>
      <div class="empty">
        Bills: sales, sales return, purchase, purchase return, orders, repair.
        <br>Ledger: account ledger references in <code>daybook</code>.
        <br>Linked rows: collection, PDC, kuri, suspense, and related party references.
      </div>
      <div class="hint" style="margin-top:12px">
        The target code must already exist. Preview first to verify row counts before merging.
      </div>
    </section>
  </div>

  <div class="split">
    <section class="card">
      <h2>Source Summary</h2>
      <div id="sourceWrap" class="empty">No preview yet.</div>
    </section>
    <section class="card">
      <h2>Reference Summary</h2>
      <div id="referenceWrap" class="empty">No preview yet.</div>
    </section>
  </div>
</div>

<script>
(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const previewUrl = @json(url('/api/accounts/party-code-merge/preview'));
  const mergeUrl = @json(url('/api/accounts/party-code-merge/merge'));
  const statusEl = document.getElementById('status');
  const sourceWrap = document.getElementById('sourceWrap');
  const referenceWrap = document.getElementById('referenceWrap');
  let latestPreview = null;

  function showStatus(message, ok) {
    statusEl.textContent = message;
    statusEl.className = 'status show ' + (ok ? 'ok' : 'err');
  }

  function clearStatus() {
    statusEl.className = 'status';
    statusEl.textContent = '';
  }

  function esc(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;');
  }

  function num(value) {
    return Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  async function post(url, payload) {
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data || data.ok === false) {
      throw new Error(data && data.message ? data.message : 'Request failed');
    }
    return data;
  }

  function payload() {
    return {
      source_codes: document.getElementById('sourceCodes').value,
      target_code: document.getElementById('targetCode').value,
      delete_sources: document.getElementById('deleteSources').checked ? 'Y' : 'N'
    };
  }

  function renderSource(preview) {
    const rows = preview.source_rows || [];
    if (!rows.length) {
      sourceWrap.innerHTML = '<div class="empty">No source codes found.</div>';
      return;
    }
    sourceWrap.innerHTML = `
      <table>
        <thead>
          <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Type</th>
            <th class="num">Op Amt</th>
            <th class="num">Op Wgt</th>
          </tr>
        </thead>
        <tbody>
          ${rows.map(row => `
            <tr>
              <td>${esc(row.code)}</td>
              <td>${esc(row.name)}</td>
              <td>${esc(row.account_type || row.client_type || '-')}</td>
              <td class="num">${num(row.opening_amount)}</td>
              <td class="num">${num(row.opening_weight)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
  }

  function renderReferences(preview) {
    const rows = preview.reference_rows || [];
    if (!rows.length) {
      referenceWrap.innerHTML = '<div class="empty">No matching bill or ledger rows found for these source codes.</div>';
      return;
    }
    referenceWrap.innerHTML = `
      <table>
        <thead>
          <tr>
            <th>Area</th>
            <th>Table</th>
            <th>Column</th>
            <th class="num">Rows</th>
          </tr>
        </thead>
        <tbody>
          ${rows.map(row => `
            <tr>
              <td>${esc(row.label)}</td>
              <td>${esc(row.table)}</td>
              <td>${esc(row.column)}</td>
              <td class="num">${Number(row.rows || 0).toLocaleString('en-IN')}</td>
            </tr>
          `).join('')}
          <tr>
            <th colspan="3">Total</th>
            <th class="num">${Number(preview.total_references || 0).toLocaleString('en-IN')}</th>
          </tr>
        </tbody>
      </table>
    `;
  }

  async function runPreview() {
    clearStatus();
    const data = await post(previewUrl, payload());
    latestPreview = data.preview || null;
    renderSource(latestPreview || {});
    renderReferences(latestPreview || {});
    showStatus('Preview loaded for target ' + (latestPreview?.target?.code || ''), true);
  }

  async function runMerge() {
    if (!latestPreview) {
      await runPreview();
    }
    const total = Number((latestPreview && latestPreview.total_references) || 0);
    const target = document.getElementById('targetCode').value.trim().toUpperCase();
    const proceed = window.confirm('Merge selected source codes into ' + target + '?\n\nRows to update: ' + total.toLocaleString('en-IN'));
    if (!proceed) return;

    clearStatus();
    const data = await post(mergeUrl, payload());
    latestPreview = data.preview || latestPreview;
    renderSource(latestPreview || {});
    renderReferences(latestPreview || {});
    const updated = Number(data.summary?.reference_rows_updated || 0).toLocaleString('en-IN');
    showStatus('Merge completed. Updated ' + updated + ' linked rows.', true);
  }

  document.getElementById('btnPreview').addEventListener('click', () => {
    runPreview().catch(err => showStatus(err.message, false));
  });

  document.getElementById('btnMerge').addEventListener('click', () => {
    runMerge().catch(err => showStatus(err.message, false));
  });

  document.getElementById('btnClear').addEventListener('click', () => {
    document.getElementById('sourceCodes').value = '';
    document.getElementById('targetCode').value = '';
    document.getElementById('deleteSources').checked = true;
    latestPreview = null;
    sourceWrap.innerHTML = 'No preview yet.';
    referenceWrap.innerHTML = 'No preview yet.';
    clearStatus();
  });
})();
</script>
</body>
</html>
