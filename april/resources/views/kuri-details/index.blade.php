<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;color:#1e293b;font-size:13px;height:100vh;overflow:hidden;display:flex;flex-direction:column}

.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282);padding:10px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0}
.toolbar h1{color:#fff;font-size:15px;font-weight:700;white-space:nowrap}
.toolbar .mode-badge{background:rgba(255,255,255,.15);color:#fff;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600}
.spacer{flex:1}
.btn{padding:6px 16px;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;gap:6px}
.btn:disabled{opacity:.5;cursor:not-allowed}
.btn-blue{background:#3b82f6;color:#fff}.btn-blue:hover:not(:disabled){background:#2563eb}
.btn-green{background:#16a34a;color:#fff}.btn-green:hover:not(:disabled){background:#15803d}
.btn-red{background:#ef4444;color:#fff}.btn-red:hover:not(:disabled){background:#dc2626}
.btn-gray{background:#64748b;color:#fff}.btn-gray:hover:not(:disabled){background:#475569}
.btn-outline{background:#fff;border:1.5px solid #e2e8f0;color:#475569}.btn-outline:hover{background:#f1f5f9}

.main{flex:1;overflow:auto;padding:16px 20px}
.form-card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.06);padding:24px;max-width:1100px;margin:0 auto}

.section-title{font-size:12px;font-weight:700;color:#3b82f6;text-transform:uppercase;letter-spacing:.5px;margin:20px 0 12px;padding-bottom:6px;border-bottom:2px solid #eff6ff}
.section-title:first-child{margin-top:0}

.form-grid{display:grid;grid-template-columns:140px 1fr 140px 1fr;gap:10px 14px;align-items:center}
.form-grid.three-col{grid-template-columns:140px 1fr 140px 1fr 140px 1fr}
.lbl{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;text-align:right}
input[type="text"],input[type="date"],input[type="number"],select,textarea{width:100%;height:36px;border:1.5px solid #e2e8f0;border-radius:8px;padding:0 10px;font-size:13px;color:#1e293b;background:#f8fafc;transition:all .15s;outline:none}
input:focus,select:focus,textarea:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
textarea{height:64px;padding:8px 10px;resize:none}
select{cursor:pointer}
.input-with-btn{display:flex;gap:6px}
.input-with-btn input,.input-with-btn select{flex:1}
.bal-row{display:flex;gap:6px;align-items:center}
.bal-row input{flex:1}
.bal-row select{width:120px;flex:none}
.checkbox-row{display:flex;align-items:center;gap:8px;font-size:12px}
.checkbox-row input{width:16px;height:16px;accent-color:#3b82f6;cursor:pointer}

.bottom-bar{background:#fff;border-top:1px solid #e2e8f0;padding:10px 20px;display:flex;align-items:center;gap:8px;flex-shrink:0}
.status{color:#64748b;font-size:12px;flex:1}

/* Search Modal */
.modal-bg{position:fixed;inset:0;background:rgba(15,23,42,.4);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:100;padding:16px}
.modal-bg.show{display:flex}
.modal{width:min(700px,100%);max-height:80vh;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden;display:flex;flex-direction:column}
.modal-head{display:flex;gap:8px;align-items:center;padding:14px 16px;border-bottom:1px solid #e2e8f0;background:#f8fafc}
.modal-head input{flex:1;height:38px;border:1.5px solid #e2e8f0;border-radius:8px;padding:0 12px;font-size:13px;outline:none}
.modal-head input:focus{border-color:#3b82f6}
.modal-body{overflow:auto;flex:1}
table{width:100%;border-collapse:collapse}
th{background:#f8fafc;padding:8px 12px;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;text-align:left;border-bottom:2px solid #e2e8f0;position:sticky;top:0}
td{padding:8px 12px;font-size:12px;border-bottom:1px solid #f1f5f9}
tbody tr{cursor:pointer;transition:background .1s}
tbody tr:hover{background:#eff6ff}

.toast{position:fixed;top:16px;right:16px;background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:12px;z-index:200;display:none;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.toast.ok{background:#16a34a}
.toast.err{background:#dc2626}
</style>
</head>
<body>

<div class="toolbar">
    <h1>{{ $title }}</h1>
    <span class="mode-badge" id="headLabel">Ready</span>
    <div class="spacer"></div>
    <button class="btn btn-blue" id="btnAdd">Add</button>
    <button class="btn btn-blue" id="btnEdit">Edit</button>
    <button class="btn btn-red" id="btnDelete" disabled>Delete</button>
    <button class="btn btn-green" id="btnSave" disabled>Save</button>
    <button class="btn btn-outline" id="btnCancel" disabled>Cancel</button>
    <button class="btn btn-gray" id="btnExit">Exit</button>
</div>

<div class="main">
  <div class="form-card">

    <div class="section-title">Customer Information</div>
    <div class="form-grid">
      <div class="lbl">Code</div>
      <div class="input-with-btn">
        <input type="text" id="fCode" maxlength="10" style="text-transform:uppercase" disabled>
        <label class="checkbox-row"><input type="checkbox" id="fAutoCode"> Auto</label>
      </div>
      <div class="lbl">Name</div>
      <input type="text" id="fName" maxlength="30" style="text-transform:uppercase" disabled>

      <div class="lbl">Address 1</div>
      <input type="text" id="fAddr1" maxlength="40" disabled>
      <div class="lbl">Address 2</div>
      <input type="text" id="fAddr2" maxlength="40" disabled>

      <div class="lbl">Address 3</div>
      <input type="text" id="fAddr3" maxlength="40" disabled>
      <div class="lbl">City</div>
      <input type="text" id="fCity" maxlength="20" style="text-transform:uppercase" disabled>

      <div class="lbl">Phone</div>
      <input type="text" id="fPhone" maxlength="25" disabled>
      <div class="lbl">Mobile</div>
      <input type="text" id="fMobile" maxlength="25" disabled>
    </div>

    <div class="section-title">Nominee Details</div>
    <div class="form-grid">
      <div class="lbl">Nominee Name</div>
      <input type="text" id="fNomName" maxlength="30" style="text-transform:uppercase" disabled>
      <div class="lbl">Relationship</div>
      <select id="fNomRelation" disabled>
        <option value="">--</option>
        @foreach($relations as $rel)
        <option value="{{ $rel }}">{{ $rel }}</option>
        @endforeach
      </select>

      <div class="lbl">Nom. Address</div>
      <textarea id="fNomAddr" disabled></textarea>
      <div class="lbl"></div><div></div>
    </div>

    <div class="section-title">Scheme / Plan Details</div>
    <div class="form-grid">
      <div class="lbl">Scheme Type</div>
      <div class="input-with-btn">
        <select id="fKuriType" disabled>
          <option value="">--</option>
          @foreach($kuritypes as $kt)
          <option value="{{ $kt->code }}">{{ $kt->code }} - {{ $kt->name }}</option>
          @endforeach
        </select>
        <button class="btn btn-outline" id="btnManageTypes" type="button" title="Manage Scheme Types" style="white-space:nowrap;padding:6px 10px;font-size:11px">Types</button>
      </div>
      <div class="lbl">Group</div>
      <div class="input-with-btn">
        <select id="fGrp" disabled>
          <option value="">--</option>
          @foreach($clientsGrps as $g)
          <option value="{{ $g->code }}">{{ $g->code }} - {{ $g->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="lbl">Collection Type</div>
      <select id="fColnType" disabled>
        <option value="Monthly">Monthly</option>
        <option value="Weekly">Weekly</option>
        <option value="Daily">Daily</option>
      </select>
      <div class="lbl">Start Date</div>
      <input type="date" id="fStartDate" disabled>

      <div class="lbl">No. of Installments</div>
      <input type="number" id="fInstNos" min="0" disabled>
      <div class="lbl">Maturity Date</div>
      <input type="date" id="fMatDate" disabled>

      <div class="lbl">Instl. Amount</div>
      <input type="number" id="fInstAmt" step="0.01" min="0" disabled>
      <div class="lbl">Total Amount</div>
      <input type="number" id="fTotAmt" step="0.01" min="0" disabled>

      <div class="lbl">Bonus</div>
      <input type="number" id="fBonus" step="0.01" min="0" disabled>
      <div class="lbl">Interest Rate</div>
      <input type="number" id="fIntRate" step="0.01" min="0" disabled>

      <div class="lbl">Collection Agent</div>
      <select id="fColnAgent" disabled>
        <option value="">--</option>
        @foreach($salesmen as $sm)
        <option value="{{ $sm->code }}">{{ $sm->code }} - {{ $sm->name }}</option>
        @endforeach
      </select>
      <div class="lbl">Route</div>
      <select id="fRoute" disabled>
        <option value="">--</option>
        @foreach($routes as $rt)
        <option value="{{ $rt->code }}">{{ $rt->code }} - {{ $rt->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="section-title">Opening Balances</div>
    <div class="form-grid">
      <div class="lbl">Op. Amt Balance</div>
      <div class="bal-row">
        <input type="number" id="fOpBal" step="0.01" disabled>
        <select id="fOpBalType" disabled><option>To Give</option><option>To Receive</option></select>
      </div>
      <div class="lbl">Op. Wgt Balance</div>
      <div class="bal-row">
        <input type="number" id="fOpWgt" step="0.001" disabled>
        <select id="fOpWgtType" disabled><option>To Give</option><option>To Receive</option></select>
      </div>

      <div class="lbl">Colln OpBal Amt</div>
      <div class="bal-row">
        <input type="number" id="fCollnOpBal" step="0.01" disabled>
        <select id="fCollnOpBalType" disabled><option>To Give</option><option>To Receive</option></select>
      </div>
      <div class="lbl">Customer Link Ac</div>
      <input type="text" id="fCustLinkAc" maxlength="10" style="text-transform:uppercase" disabled>
    </div>

    <div class="section-title">Additional Details</div>
    <div class="form-grid">
      <div class="lbl">WA Date</div>
      <input type="date" id="fWADate" disabled>
      <div class="lbl">Birth Date</div>
      <input type="date" id="fBDate" disabled>

      <div class="lbl">Colln Min Amt</div>
      <input type="number" id="fCollnMinAmt" step="0.01" min="0" disabled>
      <div class="lbl">Colln Max Amt</div>
      <input type="number" id="fCollnMaxAmt" step="0.01" min="0" disabled>

      <div class="lbl">Bank A/c No</div>
      <input type="text" id="fBankAcNo" maxlength="30" style="text-transform:uppercase" disabled>
      <div class="lbl">Bank Name</div>
      <input type="text" id="fBankName" maxlength="30" style="text-transform:uppercase" disabled>

      <div class="lbl">Bank IFSC</div>
      <input type="text" id="fBankIFSC" maxlength="30" style="text-transform:uppercase" disabled>
      <div class="lbl"></div>
      <div class="checkbox-row">
        <label class="checkbox-row"><input type="checkbox" id="fDisplay" checked disabled> Display</label>
        <label class="checkbox-row"><input type="checkbox" id="fRemoved" disabled> Removed</label>
        <label class="checkbox-row"><input type="checkbox" id="fShowWgtDet" checked disabled> Show Wgt Details</label>
      </div>
    </div>

  </div>
</div>

<div class="bottom-bar">
    <span class="status" id="statusBar">Ready</span>
</div>

<!-- Search Modal -->
<div class="modal-bg" id="searchModal">
  <div class="modal">
    <div class="modal-head">
      <input type="text" id="searchQ" placeholder="Search code, name, mobile...">
      <button class="btn btn-blue" id="searchBtn">Search</button>
      <button class="btn btn-outline" id="searchClose">&times;</button>
    </div>
    <div class="modal-body">
      <table>
        <thead><tr><th>Code</th><th>Name</th><th>Mobile</th><th>City</th><th>Type</th></tr></thead>
        <tbody id="searchRows"></tbody>
      </table>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const SITE = @json(request()->root());
const MODE = @json($mode);
const DEFAULT_GRP = @json($defaultGrp);
const csrf = document.querySelector('meta[name="csrf-token"]').content;

const $ = id => document.getElementById(id);
let currentMode = ''; // '', 'add', 'edit'
let loadedCode = '';

function toast(msg, ok=true) {
    const t = $('toast');
    t.textContent = msg;
    t.className = 'toast ' + (ok ? 'ok' : 'err');
    t.style.display = 'block';
    setTimeout(() => t.style.display='none', 3000);
}

function setStatus(msg) { $('statusBar').textContent = msg; }

async function api(url, method='GET', body=null) {
    const opts = { method, headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} };
    if (body) {
        opts.headers['Content-Type'] = 'application/json';
        opts.headers['X-CSRF-TOKEN'] = csrf;
        opts.body = JSON.stringify(body);
    }
    const r = await fetch(url, opts);
    return r.json();
}

// Field helpers
const fields = ['fCode','fName','fAddr1','fAddr2','fAddr3','fCity','fPhone','fMobile',
    'fNomName','fNomRelation','fNomAddr','fKuriType','fGrp','fColnType','fStartDate',
    'fInstNos','fMatDate','fInstAmt','fTotAmt','fBonus','fIntRate','fColnAgent','fRoute',
    'fOpBal','fOpBalType','fOpWgt','fOpWgtType','fCollnOpBal','fCollnOpBalType','fCustLinkAc',
    'fWADate','fBDate','fCollnMinAmt','fCollnMaxAmt','fBankAcNo','fBankName','fBankIFSC',
    'fDisplay','fRemoved','fShowWgtDet','fAutoCode'];

function setFieldsEnabled(enabled) {
    fields.forEach(id => {
        const el = $(id);
        if (el) el.disabled = !enabled;
    });
}

function clearForm() {
    fields.forEach(id => {
        const el = $(id);
        if (!el) return;
        if (el.type === 'checkbox') el.checked = (id === 'fDisplay' || id === 'fShowWgtDet');
        else if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = '';
    });
    $('fGrp').value = DEFAULT_GRP;
    $('fStartDate').value = new Date().toISOString().slice(0,10);
    loadedCode = '';
}

function setMode(mode) {
    currentMode = mode;
    $('btnAdd').disabled = (mode === 'add' || mode === 'edit');
    $('btnEdit').disabled = (mode === 'add' || mode === 'edit');
    $('btnDelete').disabled = (mode === 'add' || mode === 'edit');
    $('btnSave').disabled = (mode !== 'add' && mode !== 'edit');
    $('btnCancel').disabled = (mode !== 'add' && mode !== 'edit');
    setFieldsEnabled(mode === 'add' || mode === 'edit');

    if (mode === 'edit') $('fCode').disabled = true; // can't change code on edit

    $('headLabel').textContent = mode === 'add' ? 'New Entry' : mode === 'edit' ? 'Editing' : 'Ready';
}

function populateForm(client, kuridet) {
    $('fCode').value = (client.code || '').trim();
    $('fName').value = (client.name || '').trim();
    $('fAddr1').value = (client.addr1 || '').trim();
    $('fAddr2').value = (client.addr2 || '').trim();
    $('fAddr3').value = (client.addr3 || '').trim();
    $('fCity').value = (client.city || '').trim();
    $('fPhone').value = (client.telephone || '').trim();
    $('fMobile').value = (client.mobile || '').trim();
    $('fGrp').value = (client.grp || '').trim();
    $('fRoute').value = (client.route || '').trim();
    $('fRemoved').checked = (client.removed == 1);

    // Balances
    const bal = Number(client.opbalance || 0);
    $('fOpBal').value = Math.abs(bal).toFixed(2);
    $('fOpBalType').value = bal >= 0 ? 'To Give' : 'To Receive';

    const wgt = Number(client.opweight || 0);
    $('fOpWgt').value = Math.abs(wgt).toFixed(3);
    $('fOpWgtType').value = wgt >= 0 ? 'To Give' : 'To Receive';

    if (kuridet) {
        $('fNomName').value = (kuridet.nomname || '').trim();
        $('fNomAddr').value = (kuridet.nomaddr || '').trim();
        $('fNomRelation').value = (kuridet.nomrelation || '');
        $('fKuriType').value = (kuridet.kuritype || '').trim();
        $('fColnType').value = kuridet.colntype || 'Monthly';
        $('fStartDate').value = kuridet.startdate || '';
        $('fInstNos').value = kuridet.instnos || 0;
        $('fMatDate').value = kuridet.matdate || '';
        $('fInstAmt').value = Number(kuridet.instamt || 0).toFixed(2);
        $('fTotAmt').value = Number(kuridet.totamt || 0).toFixed(2);
        $('fBonus').value = Number(kuridet.bonus || 0).toFixed(2);
        $('fIntRate').value = Number(kuridet.intrate || 0).toFixed(2);
        $('fColnAgent').value = (kuridet.colnagent || '').trim();
        $('fWADate').value = kuridet.wadate || '';
        $('fBDate').value = kuridet.bdate || '';
        $('fCollnMinAmt').value = Number(kuridet.collnminamt || 0).toFixed(2);
        $('fCollnMaxAmt').value = Number(kuridet.collnmaxamt || 0).toFixed(2);
        $('fBankAcNo').value = (kuridet.bankacno || '').trim();
        $('fBankName').value = (kuridet.bankname || '').trim();
        $('fBankIFSC').value = (kuridet.bankifsc || '').trim();
        $('fCollnOpBal').value = Math.abs(Number(kuridet.collnopbal || 0)).toFixed(2);
        $('fCustLinkAc').value = (kuridet.custlinkac || '').trim();
        $('fShowWgtDet').checked = (kuridet.showwgtdet || 'Y') === 'Y';
    }

    loadedCode = (client.code || '').trim();
}

// Maturity date auto-calc (matching PB logic)
function calcMaturityDate() {
    const colnType = $('fColnType').value;
    const startStr = $('fStartDate').value;
    const instNos = parseInt($('fInstNos').value) || 0;
    if (!startStr || instNos <= 0) return;

    const start = new Date(startStr);
    let mat;
    const n = instNos + 1;

    if (colnType === 'Monthly') {
        mat = new Date(start);
        mat.setMonth(mat.getMonth() + n);
    } else if (colnType === 'Weekly') {
        mat = new Date(start.getTime() + n * 7 * 86400000);
    } else {
        mat = new Date(start.getTime() + n * 86400000);
    }

    $('fMatDate').value = mat.toISOString().slice(0, 10);
}

// ─── Button Handlers ───

$('btnAdd').addEventListener('click', () => {
    clearForm();
    setMode('add');
    if ($('fAutoCode').checked) {
        api(`${SITE}/api/kuri-details/next-code`, 'POST', { grp: $('fGrp').value || DEFAULT_GRP })
            .then(d => { if (d.ok) $('fCode').value = d.code; });
    }
    $('fCode').disabled = $('fAutoCode').checked;
    $('fCode').focus();
    setStatus('Enter new customer details');
});

$('btnEdit').addEventListener('click', () => {
    $('searchModal').classList.add('show');
    doSearch();
    $('searchQ').focus();
    setStatus('Select a customer to edit');
});

$('btnDelete').addEventListener('click', async () => {
    if (!loadedCode) { toast('Load a customer first', false); return; }
    if (!confirm(`Delete customer ${loadedCode}? This cannot be undone.`)) return;
    const d = await api(`${SITE}/api/kuri-details/delete`, 'POST', { code: loadedCode });
    if (d.ok) { toast(d.message); clearForm(); setMode(''); setStatus(d.message); }
    else toast(d.message || 'Error', false);
});

$('btnSave').addEventListener('click', async () => {
    const code = $('fCode').value.trim();
    const name = $('fName').value.trim();
    if (!code) { toast('Code is required', false); $('fCode').focus(); return; }
    if (!name) { toast('Name is required', false); $('fName').focus(); return; }

    const body = {
        is_edit:      currentMode === 'edit',
        code, name,
        addr1:        $('fAddr1').value,
        addr2:        $('fAddr2').value,
        addr3:        $('fAddr3').value,
        city:         $('fCity').value,
        telephone:    $('fPhone').value,
        mobile:       $('fMobile').value,
        grp:          $('fGrp').value,
        route:        $('fRoute').value,
        cocode:       '',
        opbalance:    parseFloat($('fOpBal').value) || 0,
        bal_type:     $('fOpBalType').value,
        opbalanceb:   0,
        bal_type_b:   'To Give',
        opweight:     parseFloat($('fOpWgt').value) || 0,
        wgt_type:     $('fOpWgtType').value,
        display:      $('fDisplay').checked,
        removed:      $('fRemoved').checked,
        // kuridet
        nomname:      $('fNomName').value,
        nomaddr:      $('fNomAddr').value,
        nomrelation:  $('fNomRelation').value,
        kuritype:     $('fKuriType').value,
        colntype:     $('fColnType').value,
        startdate:    $('fStartDate').value || null,
        instnos:      parseInt($('fInstNos').value) || 0,
        matdate:      $('fMatDate').value || null,
        instamt:      parseFloat($('fInstAmt').value) || 0,
        totamt:       parseFloat($('fTotAmt').value) || 0,
        bonus:        parseFloat($('fBonus').value) || 0,
        intrate:      parseFloat($('fIntRate').value) || 0,
        colnagent:    $('fColnAgent').value,
        wadate:       $('fWADate').value || null,
        bdate:        $('fBDate').value || null,
        collnminamt:  parseFloat($('fCollnMinAmt').value) || 0,
        collnmaxamt:  parseFloat($('fCollnMaxAmt').value) || 0,
        bankacno:     $('fBankAcNo').value,
        bankname:     $('fBankName').value,
        bankifsc:     $('fBankIFSC').value,
        collnopbal:   parseFloat($('fCollnOpBal').value) || 0,
        custlinkac:   $('fCustLinkAc').value,
        showwgtdet:   $('fShowWgtDet').checked,
    };

    const d = await api(`${SITE}/api/kuri-details/save`, 'POST', body);
    if (d.ok) {
        toast(d.message);
        loadedCode = code;
        setMode('');
        setFieldsEnabled(false);
        setStatus(d.message);
    } else {
        toast(d.message || 'Error', false);
    }
});

$('btnCancel').addEventListener('click', () => {
    if (loadedCode) {
        // Reload
        api(`${SITE}/api/kuri-details/load`, 'POST', { code: loadedCode })
            .then(d => { if (d.ok) populateForm(d.client, d.kuridet); });
    } else {
        clearForm();
    }
    setMode('');
    setFieldsEnabled(false);
    setStatus('Cancelled');
});

$('btnExit').addEventListener('click', () => {
    window.parent.postMessage({ type:'goldapp:close-module-frame' }, '*');
});

// Auto-calc maturity date when inputs change
$('fColnType').addEventListener('change', calcMaturityDate);
$('fStartDate').addEventListener('change', calcMaturityDate);
$('fInstNos').addEventListener('change', calcMaturityDate);
$('fInstNos').addEventListener('input', calcMaturityDate);

// Auto-calc total = instnos * instamt
$('fInstAmt').addEventListener('input', () => {
    const nos = parseInt($('fInstNos').value) || 0;
    const amt = parseFloat($('fInstAmt').value) || 0;
    $('fTotAmt').value = (nos * amt).toFixed(2);
});

// ─── Kuri Type auto-fill ───
$('fKuriType').addEventListener('change', async () => {
    const code = $('fKuriType').value;
    if (!code) return;
    const d = await api(`${SITE}/api/kuri-type-master/get-type`, 'POST', { code });
    if (d.ok && d.row) {
        const r = d.row;
        $('fInstNos').value = r.instnos || 0;
        $('fInstAmt').value = Number(r.instamt || 0).toFixed(2);
        $('fTotAmt').value = Number(r.totamt || 0).toFixed(2);
        $('fBonus').value = Number(r.bonus || 0).toFixed(2);
        // Map D/W/M to Daily/Weekly/Monthly
        const colnMap = { D:'Daily', W:'Weekly', M:'Monthly' };
        $('fColnType').value = colnMap[(r.colntype||'M').toUpperCase()] || 'Monthly';
        $('fCollnMinAmt').value = Number(r.collnmin || 0).toFixed(2);
        $('fCollnMaxAmt').value = Number(r.collnlimit || 0).toFixed(2);
        calcMaturityDate();
    }
});

// ─── Manage Types popup ───
$('btnManageTypes').addEventListener('click', () => {
    const popup = window.open(`${SITE}/kuri-type-master`, 'kuriTypeMaster',
        'width=1100,height=500,scrollbars=yes,resizable=yes');
    // When popup closes, refresh the kuritype dropdown
    const timer = setInterval(() => {
        if (popup && popup.closed) {
            clearInterval(timer);
            refreshKuriTypes();
        }
    }, 500);
});

async function refreshKuriTypes() {
    const d = await api(`${SITE}/api/kuri-type-master/load`);
    if (!d.ok) return;
    const sel = $('fKuriType');
    const curVal = sel.value;
    sel.innerHTML = '<option value="">--</option>';
    (d.rows || []).forEach(r => {
        const opt = document.createElement('option');
        opt.value = (r.code || '').trim();
        opt.textContent = `${(r.code||'').trim()} - ${(r.name||'').trim()}`;
        sel.appendChild(opt);
    });
    sel.value = curVal; // restore selection
}

// Auto code toggle
$('fAutoCode').addEventListener('change', () => {
    if (currentMode === 'add' && $('fAutoCode').checked) {
        api(`${SITE}/api/kuri-details/next-code`, 'POST', { grp: $('fGrp').value || DEFAULT_GRP })
            .then(d => { if (d.ok) $('fCode').value = d.code; });
        $('fCode').disabled = true;
    } else {
        $('fCode').disabled = false;
        $('fCode').focus();
    }
});

// Group change → refresh auto code
$('fGrp').addEventListener('change', () => {
    if (currentMode === 'add' && $('fAutoCode').checked) {
        api(`${SITE}/api/kuri-details/next-code`, 'POST', { grp: $('fGrp').value || DEFAULT_GRP })
            .then(d => { if (d.ok) $('fCode').value = d.code; });
    }
});

// ─── Search Modal ───

async function doSearch() {
    const d = await api(`${SITE}/api/kuri-details/search`, 'POST', {
        q: $('searchQ').value.trim(),
        grp: ''
    });
    const rows = d.rows || [];
    $('searchRows').innerHTML = rows.map(r => `
        <tr data-code="${r.code || ''}">
            <td>${(r.code||'').trim()}</td>
            <td>${(r.name||'').trim()}</td>
            <td>${(r.mobile||'').trim()}</td>
            <td>${(r.city||'').trim()}</td>
            <td>${(r.kuritype||'').trim()}</td>
        </tr>
    `).join('');
}

$('searchBtn').addEventListener('click', doSearch);
$('searchQ').addEventListener('keydown', e => { if (e.key === 'Enter') doSearch(); });
$('searchClose').addEventListener('click', () => $('searchModal').classList.remove('show'));

$('searchRows').addEventListener('click', async (e) => {
    const tr = e.target.closest('tr[data-code]');
    if (!tr) return;
    const code = tr.dataset.code;
    $('searchModal').classList.remove('show');

    const d = await api(`${SITE}/api/kuri-details/load`, 'POST', { code });
    if (d.ok) {
        populateForm(d.client, d.kuridet);
        setMode('edit');
        setStatus('Editing: ' + code);
        $('fName').focus();
    } else {
        toast(d.message || 'Not found', false);
    }
});

// Keyboard
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if ($('searchModal').classList.contains('show')) {
            $('searchModal').classList.remove('show');
        } else if (currentMode) {
            $('btnCancel').click();
        }
    }
    if (e.key === 'F9') { e.preventDefault(); $('btnSave').click(); }
});

// Init
setMode('');
setStatus('Ready — click Add for new entry, Edit to modify existing');
</script>
</body>
</html>
