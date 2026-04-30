<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
  :root{
    --bg:#c0c0c0;
    --panel:#d7d7d7;
    --field:#ffffff;
    --label:#111;
    --accent:#ff9600;
    --line:#7e7e7e;
    --soft:#ececec;
    --red:#d10000;
    --ring:#c7ba82;
    --green:#1a7a1a;
    --blue:#1a3fa0;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);font-family:Arial,Tahoma,sans-serif;font-size:13px;color:#111}
  .window{max-width:1100px;margin:12px auto;background:var(--panel);border:1px solid #8a8a8a;box-shadow:inset 0 0 0 1px #efefef}
  .titlebar{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #9a9a9a;background:linear-gradient(#dedede,#c9c9c9);font-weight:700}
  .titlebar .mode-badge{padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;text-transform:uppercase}
  .mode-edit .mode-badge{background:#e3edff;color:var(--blue);border:1px solid #93b5f5}
  .mode-cancel .mode-badge{background:#ffe3e3;color:var(--red);border:1px solid #f59393}
  .frame{padding:12px}

  /* Search / List Section */
  .search-bar{display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
  .search-bar label{font-weight:700}
  .search-bar input,.search-bar select{height:32px;padding:4px 8px;border:1px solid var(--line);background:var(--field)}
  .search-bar input[type="date"]{width:140px}
  .search-bar input[type="text"]{width:220px}
  .search-bar button{height:32px;padding:0 16px;border:1px solid #666;background:#efefef;font-weight:700;cursor:pointer}
  .search-bar button:hover{background:#e0e0e0}

  .list-wrap{max-height:260px;overflow:auto;border:1px solid #9d9d9d;background:#fff;margin-bottom:14px}
  table{width:100%;border-collapse:collapse}
  th,td{padding:7px 8px;text-align:left;border-bottom:1px solid #e6e6e6;white-space:nowrap;font-size:12px}
  th{position:sticky;top:0;background:#d6d6d6;font-weight:700;border-bottom:2px solid #9d9d9d;z-index:2}
  tr{cursor:pointer}
  tr:hover td{background:#e9f1ff}
  tr.selected td{background:#c5daff;font-weight:700}
  .empty-row{text-align:center;color:#888;padding:30px}

  /* Detail Section */
  .detail-panel{display:none;border:1px solid #9d9d9d;background:#cfcfcf;padding:14px}
  .detail-panel.visible{display:block}
  .detail-title{font-weight:700;font-size:15px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #aaa}
  .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .detail-section{padding:14px;border:1px solid #9d9d9d;background:#d8d8d8;position:relative}
  .detail-section .legend{position:absolute;top:-12px;left:14px;padding:3px 14px;background:#fff;border:1px solid #8b8b8b;font-weight:700;font-size:12px}
  .detail-row{display:grid;grid-template-columns:130px 1fr;gap:4px 10px;margin-bottom:6px;align-items:center}
  .detail-row .dlabel{font-weight:700;text-align:right;color:#333}
  .detail-row .dval{padding:4px 8px;background:#fff;border:1px solid #ccc;min-height:26px;font-weight:500}
  .detail-row .dval.highlight{background:#fff5e0;font-weight:700}

  /* Editable fields for edit mode */
  .detail-row input[type="text"],.detail-row input[type="number"]{width:100%;height:28px;padding:4px 8px;border:1px solid var(--line);background:var(--field);font-weight:500}
  .detail-row input:focus{outline:none;background:#fff5e8;border-color:#9b6a18}
  .detail-row select{width:100%;height:28px;padding:2px 6px;border:1px solid var(--line);background:var(--field)}

  .reason-row{margin-top:12px;display:flex;gap:10px;align-items:center}
  .reason-row label{font-weight:700;min-width:80px}
  .reason-row input{flex:1;height:32px;padding:4px 8px;border:1px solid var(--line);background:var(--field)}

  /* Action Ring */
  .actions-ring{margin:14px auto 0;max-width:460px;padding:16px 26px;border:4px solid #2748ff;border-radius:999px;background:var(--ring);display:flex;justify-content:center;gap:16px}
  .actions-ring button{min-width:110px;height:38px;border:1px solid #666;background:#f3f0de;font-weight:700;cursor:pointer;font-size:13px}
  .actions-ring button:hover{background:#e8e4c8}
  .actions-ring button.danger{background:#ffd4d4;color:var(--red);border-color:#c55}
  .actions-ring button.danger:hover{background:#ffc0c0}
  .actions-ring button.primary{background:#d4e3ff;color:var(--blue);border-color:#7ba0e0}
  .actions-ring button.primary:hover{background:#c0d6ff}
  .actions-ring button:disabled{opacity:.5;cursor:not-allowed}

  .status{min-height:20px;margin-top:10px;font-weight:700;text-align:center}
  .status.error{color:var(--red)}
  .status.success{color:var(--green)}
  .status.info{color:var(--blue)}

  /* Confirm overlay */
  .confirm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;align-items:center;justify-content:center}
  .confirm-overlay.open{display:flex}
  .confirm-box{background:#e8e8e8;border:2px solid #666;padding:24px 30px;min-width:340px;text-align:center;box-shadow:4px 4px 0 rgba(0,0,0,.2)}
  .confirm-box p{font-size:14px;font-weight:700;margin:0 0 18px}
  .confirm-box .cbtn{display:flex;justify-content:center;gap:14px}
  .confirm-box button{min-width:90px;height:36px;font-weight:700;border:1px solid #666;cursor:pointer}
  .confirm-box .yes{background:#d4e3ff;color:var(--blue)}
  .confirm-box .no{background:#ffd4d4;color:var(--red)}

  @media(max-width:800px){.detail-grid{grid-template-columns:1fr}.search-bar{flex-direction:column;align-items:stretch}}
</style>
</head>
<body>
<div class="window mode-{{ $mode }}">
    <div class="titlebar">
        <span>{{ $title }}</span>
        <span class="mode-badge">{{ strtoupper($mode) }} Mode</span>
    </div>

    <div class="frame">
        <!-- Search Bar -->
        <div class="search-bar">
            <label>Date From:</label>
            <input type="date" id="dateFrom" value="{{ $today }}">
            <label>Date To:</label>
            <input type="date" id="dateTo" value="{{ $today }}">
            <label>Search:</label>
            <input type="text" id="searchText" placeholder="Item code / Sl.No">
            <button type="button" id="btnSearch">Search</button>
            <button type="button" id="btnShowAll">Show All</button>
        </div>

        <!-- List Table -->
        <div class="list-wrap">
            <table>
                <thead>
                <tr>
                    <th>Sl.No</th>
                    <th>Date</th>
                    <th>From Code</th>
                    <th>From Wgt</th>
                    <th>To Code</th>
                    <th>To Wgt</th>
                    <th>Qty</th>
                    <th>Reason</th>
                    <th>Type</th>
                </tr>
                </thead>
                <tbody id="listBody">
                    <tr><td colspan="9" class="empty-row">Click "Search" or "Show All" to load adjustment records.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Detail Panel -->
        <div class="detail-panel" id="detailPanel">
            <div class="detail-title" id="detailTitle">Adjustment Details</div>

            <div class="detail-grid">
                <!-- FROM Section -->
                <div class="detail-section">
                    <div class="legend">FROM (Source)</div>
                    <div class="detail-row">
                        <span class="dlabel">Item Code:</span>
                        @if($mode === 'edit')
                            <input type="text" id="dFromCode" style="text-transform:uppercase">
                        @else
                            <span class="dval" id="dFromCode"></span>
                        @endif
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Item Name:</span>
                        <span class="dval" id="dFromName"></span>
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Qty:</span>
                        @if($mode === 'edit')
                            <input type="number" id="dFromQty" step="1" min="0">
                        @else
                            <span class="dval" id="dFromQty"></span>
                        @endif
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Weight (gm):</span>
                        @if($mode === 'edit')
                            <input type="number" id="dFromWgt" step="0.001" min="0">
                        @else
                            <span class="dval highlight" id="dFromWgt"></span>
                        @endif
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Stone Wgt:</span>
                        @if($mode === 'edit')
                            <input type="number" id="dFromStWgt" step="0.001" min="0">
                        @else
                            <span class="dval" id="dFromStWgt"></span>
                        @endif
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Stone Amt:</span>
                        @if($mode === 'edit')
                            <input type="number" id="dFromStAmt" step="0.01" min="0">
                        @else
                            <span class="dval" id="dFromStAmt"></span>
                        @endif
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Stk Type:</span>
                        @if($mode === 'edit')
                            <select id="dFromStkType">
                                <option value=""></option>
                                @foreach($stockTypes as $st)
                                    <option value="{{ $st->code }}">{{ $st->code }} - {{ $st->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="dval" id="dFromStkType"></span>
                        @endif
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Barcode:</span>
                        <span class="dval" id="dFromBcode"></span>
                    </div>
                </div>

                <!-- TO Section -->
                <div class="detail-section">
                    <div class="legend">TO (Destination)</div>
                    <div class="detail-row">
                        <span class="dlabel">Item Code:</span>
                        @if($mode === 'edit')
                            <input type="text" id="dToCode" style="text-transform:uppercase">
                        @else
                            <span class="dval" id="dToCode"></span>
                        @endif
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Item Name:</span>
                        <span class="dval" id="dToName"></span>
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Qty:</span>
                        @if($mode === 'edit')
                            <input type="number" id="dToQty" step="1" min="0">
                        @else
                            <span class="dval" id="dToQty"></span>
                        @endif
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Weight (gm):</span>
                        @if($mode === 'edit')
                            <input type="number" id="dToWgt" step="0.001" min="0">
                        @else
                            <span class="dval highlight" id="dToWgt"></span>
                        @endif
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Stone Wgt:</span>
                        @if($mode === 'edit')
                            <input type="number" id="dToStWgt" step="0.001" min="0">
                        @else
                            <span class="dval" id="dToStWgt"></span>
                        @endif
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Stone Amt:</span>
                        @if($mode === 'edit')
                            <input type="number" id="dToStAmt" step="0.01" min="0">
                        @else
                            <span class="dval" id="dToStAmt"></span>
                        @endif
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Stk Type:</span>
                        @if($mode === 'edit')
                            <select id="dToStkType">
                                <option value=""></option>
                                @foreach($stockTypes as $st)
                                    <option value="{{ $st->code }}">{{ $st->code }} - {{ $st->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="dval" id="dToStkType"></span>
                        @endif
                    </div>
                    <div class="detail-row">
                        <span class="dlabel">Barcode:</span>
                        <span class="dval" id="dToBcode"></span>
                    </div>
                </div>
            </div>

            <!-- Extra Info -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-top:12px">
                <div class="detail-row"><span class="dlabel">Date:</span><span class="dval" id="dDate"></span></div>
                <div class="detail-row"><span class="dlabel">Time:</span><span class="dval" id="dTime"></span></div>
                <div class="detail-row"><span class="dlabel">Control:</span><span class="dval" id="dControl"></span></div>
                <div class="detail-row"><span class="dlabel">Salesman:</span><span class="dval" id="dSmcode"></span></div>
            </div>

            @if($mode === 'edit')
            <div class="reason-row">
                <label>Reason:</label>
                <input type="text" id="dReason" maxlength="30" placeholder="Reason for adjustment">
            </div>
            @endif

            <!-- Actions -->
            <div class="actions-ring">
                @if($mode === 'edit')
                    <button type="button" id="btnSave" class="primary" disabled>Save Changes</button>
                    <button type="button" id="btnReset">Reset</button>
                @else
                    <button type="button" id="btnCancel" class="danger" disabled>Cancel Entry</button>
                @endif
                <button type="button" id="btnClose">Close</button>
            </div>

            <div class="status" id="statusMsg"></div>
        </div>
    </div>
</div>

<!-- Confirm Dialog -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <p id="confirmText">Are you sure?</p>
        <div class="cbtn">
            <button class="yes" id="confirmYes">Yes</button>
            <button class="no" id="confirmNo">No</button>
        </div>
    </div>
</div>

<script>
const MODE = '{{ $mode }}';
const API_BASE = '{{ url("/api/item-stock-adjustment") }}';
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const GISEMI = {{ $gisemi }};

let records = [];
let selectedSlno = null;
let selectedRecord = null;
let originalRecord = null;

/* ===================== DOM HELPERS ===================== */
const $ = id => document.getElementById(id);
const setVal = (id, val) => {
    const el = $(id);
    if (!el) return;
    if (el.tagName === 'INPUT' || el.tagName === 'SELECT') el.value = val ?? '';
    else el.textContent = val ?? '';
};

const getVal = (id) => {
    const el = $(id);
    if (!el) return '';
    return (el.value ?? el.textContent ?? '').trim();
};

function setStatus(msg, type = 'info') {
    const el = $('statusMsg');
    el.textContent = msg;
    el.className = 'status ' + type;
}

/* ===================== SEARCH / LIST ===================== */
async function loadRecords(showAll = false) {
    const dateFrom = $('dateFrom').value;
    const dateTo = $('dateTo').value;
    const search = $('searchText').value.trim();

    const params = new URLSearchParams();
    if (!showAll) {
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
    }
    if (search) params.set('search', search);

    setStatus('Loading...', 'info');

    try {
        const res = await fetch(API_BASE + '/list?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (!json.success) {
            setStatus(json.message || 'Failed to load', 'error');
            return;
        }

        records = json.rows || [];
        renderList();
        setStatus(records.length + ' record(s) found', 'info');

        $('detailPanel').classList.remove('visible');
        selectedSlno = null;
        selectedRecord = null;
        updateActionButtons();
    } catch (e) {
        setStatus('Network error: ' + e.message, 'error');
    }
}

function renderList() {
    const body = $('listBody');

    if (!records.length) {
        body.innerHTML = '<tr><td colspan="9" class="empty-row">No adjustment records found.</td></tr>';
        return;
    }

    body.innerHTML = records.map(r => {
        const isWgt = r.has_wgtrcpt ? '(W)' : '';
        return `<tr data-slno="${r.slno}" class="${r.slno == selectedSlno ? 'selected' : ''}">
            <td>${r.slno}</td>
            <td>${r.tdate || ''}</td>
            <td>${esc(r.fromcode)}</td>
            <td>${fmt3(r.fromwgt)}</td>
            <td>${esc(r.tocode)}</td>
            <td>${fmt3(r.towgt)}</td>
            <td>${r.fromqty || 0}</td>
            <td>${esc(r.particular || '')}</td>
            <td>${isWgt}</td>
        </tr>`;
    }).join('');
}

function fmt3(v) { return Number(v || 0).toFixed(3); }
function fmt2(v) { return Number(v || 0).toFixed(2); }
function esc(v) { return String(v ?? '').replace(/[<>&"']/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'}[c])); }

/* ===================== SELECT RECORD ===================== */
$('listBody').addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-slno]');
    if (!tr) return;

    selectedSlno = Number(tr.dataset.slno);
    selectedRecord = records.find(r => r.slno == selectedSlno) || null;
    originalRecord = selectedRecord ? JSON.parse(JSON.stringify(selectedRecord)) : null;

    document.querySelectorAll('#listBody tr').forEach(row => row.classList.remove('selected'));
    tr.classList.add('selected');

    showDetail(selectedRecord);
});

async function showDetail(rec) {
    if (!rec) return;

    $('detailPanel').classList.add('visible');
    $('detailTitle').textContent = 'Adjustment #' + rec.slno + (rec.has_wgtrcpt ? ' (Weight Receipt/Payment)' : '');

    setVal('dFromCode', rec.fromcode);
    setVal('dFromName', rec.from_item_name || '');
    setVal('dFromQty', rec.fromqty);
    setVal('dFromWgt', fmt3(rec.fromwgt));
    setVal('dFromStWgt', fmt3(rec.fromstwgt));
    setVal('dFromStAmt', fmt2(rec.fromstamt));
    setVal('dFromStkType', rec.fromstktype || '');
    setVal('dFromBcode', rec.bcode || '-');

    setVal('dToCode', rec.tocode);
    setVal('dToName', rec.to_item_name || '');
    setVal('dToQty', rec.toqty);
    setVal('dToWgt', fmt3(rec.towgt));
    setVal('dToStWgt', fmt3(rec.tostwgt));
    setVal('dToStAmt', fmt2(rec.tostamt));
    setVal('dToStkType', rec.tostktype || '');
    setVal('dToBcode', rec.tobcode || '-');

    setVal('dDate', rec.tdate);
    setVal('dTime', rec.ttime || '');
    setVal('dControl', rec.control);
    setVal('dSmcode', rec.smcode || '');

    if (MODE === 'edit') {
        setVal('dReason', rec.particular || '');
    }

    updateActionButtons();
    setStatus('Record #' + rec.slno + ' loaded', 'info');
}

function updateActionButtons() {
    if (MODE === 'edit') {
        const btn = $('btnSave');
        if (btn) btn.disabled = !selectedRecord;
        const rbtn = $('btnReset');
        if (rbtn) rbtn.disabled = !selectedRecord;
    } else {
        const btn = $('btnCancel');
        if (btn) btn.disabled = !selectedRecord;
    }
}

/* ===================== CONFIRM DIALOG ===================== */
function showConfirm(text) {
    return new Promise(resolve => {
        $('confirmText').textContent = text;
        $('confirmOverlay').classList.add('open');
        $('confirmYes').onclick = () => { $('confirmOverlay').classList.remove('open'); resolve(true); };
        $('confirmNo').onclick = () => { $('confirmOverlay').classList.remove('open'); resolve(false); };
    });
}

/* ===================== EDIT - SAVE ===================== */
if (MODE === 'edit' && $('btnSave')) {
    $('btnSave').addEventListener('click', async () => {
        if (!selectedRecord) return;

        const confirmed = await showConfirm('Save changes to adjustment #' + selectedSlno + '?');
        if (!confirmed) return;

        const payload = {
            slno: selectedSlno,
            fromcode: getVal('dFromCode').toUpperCase(),
            tocode: getVal('dToCode').toUpperCase(),
            fromqty: parseInt(getVal('dFromQty')) || 0,
            toqty: parseInt(getVal('dToQty')) || 0,
            fromwgt: parseFloat(getVal('dFromWgt')) || 0,
            towgt: parseFloat(getVal('dToWgt')) || 0,
            fromstwgt: parseFloat(getVal('dFromStWgt')) || 0,
            tostwgt: parseFloat(getVal('dToStWgt')) || 0,
            fromstamt: parseFloat(getVal('dFromStAmt')) || 0,
            tostamt: parseFloat(getVal('dToStAmt')) || 0,
            fromstktype: getVal('dFromStkType').toUpperCase(),
            tostktype: getVal('dToStkType').toUpperCase(),
            reason: getVal('dReason'),
        };

        if (!payload.fromcode || !payload.tocode) {
            setStatus('From/To item code required', 'error');
            return;
        }

        setStatus('Saving...', 'info');
        $('btnSave').disabled = true;

        try {
            const res = await fetch(API_BASE + '/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const json = await res.json();

            if (json.success) {
                setStatus('Adjustment #' + selectedSlno + ' updated successfully!', 'success');
                loadRecords();
            } else {
                setStatus('Error: ' + (json.message || 'Update failed'), 'error');
            }
        } catch (e) {
            setStatus('Network error: ' + e.message, 'error');
        } finally {
            $('btnSave').disabled = false;
        }
    });

    $('btnReset').addEventListener('click', () => {
        if (originalRecord) showDetail(originalRecord);
    });
}

/* ===================== CANCEL ===================== */
if (MODE === 'cancel' && $('btnCancel')) {
    $('btnCancel').addEventListener('click', async () => {
        if (!selectedRecord) return;

        const confirmed = await showConfirm('Do you want to cancel adjustment #' + selectedSlno + '?\nThis will reverse all stock changes and delete the entry.');
        if (!confirmed) return;

        setStatus('Cancelling...', 'info');
        $('btnCancel').disabled = true;

        try {
            const res = await fetch(API_BASE + '/cancel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ slno: selectedSlno }),
            });
            const json = await res.json();

            if (json.success) {
                setStatus('Adjustment #' + selectedSlno + ' cancelled successfully!', 'success');
                $('detailPanel').classList.remove('visible');
                selectedSlno = null;
                selectedRecord = null;
                loadRecords();
            } else {
                setStatus('Error: ' + (json.message || 'Cancel failed'), 'error');
            }
        } catch (e) {
            setStatus('Network error: ' + e.message, 'error');
        } finally {
            $('btnCancel').disabled = false;
        }
    });
}

/* ===================== CLOSE ===================== */
$('btnClose').addEventListener('click', () => {
    $('detailPanel').classList.remove('visible');
    selectedSlno = null;
    selectedRecord = null;
    updateActionButtons();
});

/* ===================== EVENTS ===================== */
$('btnSearch').addEventListener('click', () => loadRecords(false));
$('btnShowAll').addEventListener('click', () => loadRecords(true));

$('searchText').addEventListener('keydown', e => {
    if (e.key === 'Enter') loadRecords(false);
});

// Auto-load on open
loadRecords(false);
</script>
</body>
</html>
