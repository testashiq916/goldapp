<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Item Add/Edit</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1280px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 12px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .status { margin: 8px 0; padding: 8px 10px; border-radius: 7px; font-size: 12px; display: none; }
        .status.show { display: block; }
        .status.ok { border: 1px solid #9fd5af; background: #e6f6ec; color: #12522d; }
        .status.err { border: 1px solid #f2b1b1; background: #fdeaea; color: #8e1f1f; }
        .sec { border: 1px solid #d8e2ef; border-radius: 8px; padding: 10px; margin-bottom: 10px; background: #f8fbff; }
        .sec h3 { margin: 0 0 8px; font-size: 14px; color: #2d4f74; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap: 8px; }
        label { font-size: 12px; font-weight: 700; color: #375b84; display: block; margin-bottom: 3px; }
        input, select { height: 32px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; width: 100%; box-sizing: border-box; }
        .chkgrid { display: grid; grid-template-columns: repeat(3, minmax(190px, 1fr)); gap: 4px 8px; }
        .chkgrid label { display: flex; gap: 6px; align-items: center; margin: 0; font-weight: 600; color: #1f3f66; }
        .chkgrid input { width: 16px; height: 16px; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 6px; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        button.warn { border-color: #a23b3b; background: #fdeaea; color: #8e1f1f; }
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,.35); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .modal.show { display: flex; }
        .modal-card { width: 800px; background: #fff; border-radius: 10px; border: 1px solid #d7dfeb; padding: 12px; }
        .code-change-card { width: 520px; padding: 0; overflow: hidden; }
        .code-change-title { background: #17445a; color: #e8f8ff; font-size: 18px; font-weight: 800; padding: 10px 14px; }
        .code-change-body { padding: 16px 18px; background: #fff; }
        .code-change-row { display: grid; grid-template-columns: 115px 1fr auto; gap: 8px; align-items: center; margin-bottom: 14px; }
        .code-change-row label { margin: 0; text-align: right; color: #1f2937; font-size: 14px; }
        .code-change-row input { height: 32px; font-size: 13px; text-transform: uppercase; }
        .code-change-status { min-height: 18px; margin: 2px 0 10px 123px; font-size: 12px; color: #375b84; }
        .code-change-actions { border-top: 1px solid #d7dfeb; padding: 10px 16px; display: flex; gap: 8px; justify-content: flex-end; background: #f8fbff; }
        .table-wrap { max-height: 360px; overflow: auto; border: 1px solid #d8e2ef; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 6px; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; z-index: 2; }
        #searchRows tr.active { background: #e9f3ff; }
        @media (max-width: 1024px) { .grid { grid-template-columns: repeat(2, minmax(180px, 1fr)); } .chkgrid { grid-template-columns: repeat(2, minmax(160px, 1fr)); } .wrap { margin: 8px; } }
        @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } .chkgrid { grid-template-columns: 1fr; } .wrap { margin: 4px; padding: 10px; border-radius: 8px; } h1 { font-size: 18px; } .modal-card { width: calc(100% - 16px); padding: 10px; } }
    </style>
</head>
<body>
<div class="wrap">
    <h1 id="title">Item Add/Edit</h1>
    <div id="status" class="status"></div>

    <div class="sec">
        <h3>Basic</h3>
        <div class="grid">
            <div><label>Code</label><input id="code" maxlength="10"></div>
            <div><label>Description</label><input id="desc" maxlength="20"></div>
            <div><label>Regional</label><input id="regional" maxlength="20"></div>
            <div><label>Front Code</label><input id="frcode" maxlength="10"></div>
            <div><label>Group</label><select id="grpcode"></select></div>
            <div><label>Sub Group</label><select id="subgrpcode"></select></div>
            <div><label>Item Type</label><select id="itype"><option value="G">Gold</option><option value="S">Silver</option><option value="P">Platinum</option><option value="D">Diamond</option><option value="O">Others</option></select></div>
            <div><label>Display Category</label><select id="dmdplt"><option value=""></option><option value="D">Diamond</option><option value="P">Platinum</option><option value="G">Gold</option><option value="S">Silver</option><option value="O">Others</option><option value="C">Color Stone</option><option value="W">Watch</option></select></div>
        </div>
    </div>

    <div class="sec">
        <h3>Cost & Stock</h3>
        <div class="grid">
            <div><label>Op. Cost</label><input id="opcost" type="number" step="0.01"></div>
            <div><label>Current Cost</label><input id="cost" type="number" step="0.01"></div>
            <div><label>Wastage</label><input id="wastage" type="number" step="0.001"></div>
            <div><label>M.C.</label><input id="mcrate" type="number" step="0.01"></div>
            <div><label>VA %</label><input id="vaperc" type="number" step="0.01"></div>
            <div><label>VA Qty</label><input id="vaperqty" type="number" step="0.01"></div>
            <div><label>Default Qty</label><input id="defqty" type="number" step="1"></div>
            <div><label>Touch</label><input id="touch" type="number" step="0.01"></div>
            <div><label>Reorder Min Wgt</label><input id="rollower" type="number" step="0.001"></div>
            <div><label>Reorder Max Wgt</label><input id="rolupper" type="number" step="0.001"></div>
            <div><label>Reorder Min Qty</label><input id="rollowerqty" type="number" step="1"></div>
            <div><label>Reorder Max Qty</label><input id="rolupperqty" type="number" step="1"></div>
        </div>
    </div>

    <div class="sec">
        <h3>Defaults & Tax</h3>
        <div class="grid">
            <div><label>Def. Stock Type</label><select id="stktype"></select></div>
            <div><label>Def. Purity</label><select id="qtype"></select></div>
            <div><label>Def. Smith</label><select id="smith"></select></div>
            <div><label>Bill Type</label><select id="billtype"></select></div>
            <div><label>Stone Margin %</label><input id="stonemarg" type="number" step="0.01"></div>
            <div><label>Sales Rate</label><input id="rate" type="number" step="0.01"></div>
            <div><label>WS Rate</label><input id="wsrate" type="number" step="0.01"></div>
            <div><label>Purchase Rate</label><input id="prate" type="number" step="0.01"></div>
            <div><label>Smith MC</label><input id="smithmc" type="number" step="0.01"></div>
            <div><label>Jewl MC</label><input id="jewlmc" type="number" step="0.01"></div>
            <div><label>Schedule</label><input id="shedule" maxlength="10"></div>
            <div><label>HSN/VAT</label><input id="vatcode" maxlength="10"></div>
            <div><label>SAC Code</label><input id="saccode" maxlength="10"></div>
            <div><label>Stock Touch</label><input id="stktouch" type="number" step="0.01"></div>
            <div><label>Jewl Touch</label><input id="jewltouch" type="number" step="0.01"></div>
            <div><label>Sticker Wgt</label><input id="stickerwgt" type="number" step="0.01"></div>
            <div><label>Min VA%</label><input id="minvap" type="number" step="0.01"></div>
            <div><label>Footer English</label><input id="footer_e" maxlength="40"></div>
            <div><label>Footer Regional</label><input id="footer_m" maxlength="40"></div>
        </div>
    </div>

    <div class="sec">
        <h3>Options</h3>
        <div class="chkgrid">
            <label><input id="ornament" type="checkbox"> Ornament</label>
            <label><input id="stkinnos" type="checkbox"> Stock in nos only</label>
            <label><input id="reserve" type="checkbox"> Reserved</label>
            <label><input id="disable" type="checkbox"> Disable</label>
            <label><input id="dontshowinstkrep" type="checkbox"> Dont Show In Stock Reports</label>
            <label><input id="taxable" type="checkbox" checked> Taxable</label>
            <label><input id="taxinternal" type="checkbox"> Tax Internal</label>
            <label><input id="cessinternal" type="checkbox"> Cess Internal</label>
            <label><input id="printvaamt" type="checkbox"> Always Print VA Amt</label>
            <label><input id="bccompulsory" type="checkbox"> Barcode Compulsory</label>
            <label><input id="nodisc" type="checkbox"> No Discount</label>
            <label><input id="stonemust" type="checkbox"> Stone Compulsory</label>
            <label><input id="vaoffer" type="checkbox"> No VA or MC (Offer)</label>
        </div>
    </div>

    <div class="toolbar">
        <button id="btnAdd" type="button">Add</button>
        <button id="btnEdit" type="button">Edit</button>
        <button id="btnDelete" type="button" class="warn">Delete</button>
        <button id="btnSave" type="button" class="primary">Save (F9)</button>
        <button id="btnCancel" type="button">Cancel</button>
        <button id="btnSearch" type="button">Search</button>
        <button id="btnRenameCode" type="button">Rename Code</button>
        <button id="btnExit" type="button">Exit</button>
    </div>
</div>

<div id="searchModal" class="modal">
    <div class="modal-card">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
            <strong style="flex:1;">Search Item</strong>
            <input id="searchInput" placeholder="Code / Name / Regional" style="width:260px;">
            <button id="searchClose" type="button">Close</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th style="width:140px;">Code</th><th>Name</th><th style="width:100px;">Type</th></tr></thead>
                <tbody id="searchRows"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="codeChangeModal" class="modal">
    <div class="modal-card code-change-card">
        <div class="code-change-title">Code Change</div>
        <div class="code-change-body">
            <div class="code-change-row">
                <label for="renameOldCode">Old Code :</label>
                <input id="renameOldCode" maxlength="10">
                <button id="renameHelp" type="button">Help</button>
            </div>
            <div class="code-change-row">
                <label for="renameNewCode">New Code :</label>
                <input id="renameNewCode" maxlength="10">
                <span></span>
            </div>
            <div id="renameStatus" class="code-change-status"></div>
        </div>
        <div class="code-change-actions">
            <button id="renameOk" class="primary" type="button">OK</button>
            <button id="renameClose" type="button">Close</button>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const statusEl = document.getElementById('status');
    const formIds = [
        'code','desc','regional','frcode','grpcode','subgrpcode','itype','dmdplt','opcost','cost','wastage','mcrate','vaperc','vaperqty',
        'defqty','touch','rollower','rolupper','rollowerqty','rolupperqty','stktype','qtype','smith','billtype','stonemarg','rate',
        'wsrate','prate','smithmc','jewlmc','shedule','vatcode','saccode','stktouch','jewltouch','stickerwgt','minvap','footer_e','footer_m',
        'ornament','stkinnos','reserve','disable','dontshowinstkrep','taxable','taxinternal','cessinternal','printvaamt','bccompulsory','nodisc','stonemust','vaoffer'
    ];
    const checkboxIds = ['ornament','stkinnos','reserve','disable','dontshowinstkrep','taxable','taxinternal','cessinternal','printvaamt','bccompulsory','nodisc','stonemust','vaoffer'];
    let mode = 'add';
    let searchTarget = 'edit';
    const purityTouchMap = {};  // purity code -> touch
    const currentRates = {};    // populated from /api/rate/current

    const status = (msg, ok) => {
        statusEl.textContent = msg;
        statusEl.className = 'status show ' + (ok ? 'ok' : 'err');
    };

    async function requestJson(url, method = 'GET', payload = null) {
        const res = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload ? JSON.stringify(payload) : null
        });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch (e) { throw new Error('Invalid server response'); }
        if (!res.ok) throw new Error(data.message || 'Request failed');
        return data;
    }

    function resetForm() {
        formIds.forEach((id) => {
            const el = document.getElementById(id);
            if (!el) return;
            if (checkboxIds.includes(id)) {
                el.checked = (id === 'taxable');
            } else {
                el.value = '';
            }
        });
        document.getElementById('itype').value = 'G';
        document.getElementById('stktouch').value = '100';
        document.getElementById('code').disabled = false;
    }

    function payload() {
        const out = { mode };
        formIds.forEach((id) => {
            const el = document.getElementById(id);
            if (!el) return;
            out[id] = checkboxIds.includes(id) ? !!el.checked : el.value;
        });
        out.code = (out.code || '').toUpperCase().trim();
        out.desc = (out.desc || '').toUpperCase().trim();
        out.frcode = (out.frcode || '').toUpperCase().trim();
        out.shedule = (out.shedule || '').toUpperCase().trim();
        out.vatcode = (out.vatcode || '').toUpperCase().trim();
        out.saccode = (out.saccode || '').toUpperCase().trim();
        out.code_locked = !!document.getElementById('code').disabled;
        return out;
    }

    function applyItem(item) {
        const map = {
            code: item.code, desc: item.name, regional: item.regionalname, frcode: item.code2, grpcode: item.grpcode, subgrpcode: item.subgrpcode,
            itype: item.itype, dmdplt: item.dmdplt, opcost: item.opcost, cost: item.cost, wastage: item.wastage, mcrate: item.mcharge, vaperc: item.vaperc,
            vaperqty: item.vaperqty, defqty: item.defqty, touch: item.touch, rollower: item.rollower, rolupper: item.rolupper, rollowerqty: item.rollowerqty,
            rolupperqty: item.rolupperqty, stktype: item.defstktype, qtype: item.defquality || item.qtype, smith: item.defsmith, billtype: item.billtype,
            stonemarg: item.stonemarg, rate: item.rate, wsrate: item.wsrate, prate: item.prate, smithmc: item.smithmc, jewlmc: item.jewlmc, shedule: item.shedule,
            vatcode: item.vatcode, saccode: item.saccode, stktouch: item.stktouch, jewltouch: item.jewltouch, stickerwgt: item.stickerwgt, minvap: item.minvap,
            footer_e: item.footer_e, footer_m: item.footer_m
        };
        Object.keys(map).forEach((id) => {
            const el = document.getElementById(id);
            if (el && map[id] !== null && map[id] !== undefined) el.value = map[id];
        });
        document.getElementById('ornament').checked = String(item.ornament || '').toUpperCase() === 'Y';
        document.getElementById('stkinnos').checked = String(item.stkinnos || '').toUpperCase() === 'Y';
        document.getElementById('reserve').checked = String(item.reserve || '').toUpperCase() === 'Y';
        document.getElementById('disable').checked = Number(item.disabled || 0) === 1;
        document.getElementById('dontshowinstkrep').checked = String(item.showinstkrep || 'Y').toUpperCase() !== 'Y';
        document.getElementById('taxable').checked = String(item.taxable || 'Y').toUpperCase() === 'Y';
        document.getElementById('taxinternal').checked = String(item.taxinternal || 'N').toUpperCase() === 'Y';
        document.getElementById('cessinternal').checked = String(item.cessinternal || 'N').toUpperCase() === 'Y';
        document.getElementById('printvaamt').checked = String(item.printvaamt || 'N').toUpperCase() === 'Y';
        document.getElementById('bccompulsory').checked = String(item.bccompulsory || 'N').toUpperCase() === 'Y';
        document.getElementById('nodisc').checked = String(item.nodisc || '').toUpperCase() === 'N';
        document.getElementById('stonemust').checked = String(item.stonemust || 'N').toUpperCase() === 'Y';
        document.getElementById('vaoffer').checked = String(item.vaoffer || 'N').toUpperCase() === 'Y';
    }

    async function loadOptions() {
        const d = await requestJson(@json(url('/item-master/options')));
        const bind = (id, rows) => {
            const s = document.getElementById(id);
            if (!s) return;
            s.innerHTML = '<option value=""></option>';
            (rows || []).forEach((r) => {
                const o = document.createElement('option');
                o.value = r.code || '';
                o.textContent = `${r.name || r.code}`;
                s.appendChild(o);
            });
        };
        bind('grpcode', d.data.groups);
        bind('subgrpcode', d.data.subgroups);
        bind('stktype', d.data.stocktypes);
        bind('qtype', d.data.qualitytypes);
        bind('smith', d.data.smiths);
        bind('billtype', d.data.billtypes);

        (d.data.qualitytypes || []).forEach((r) => {
            const key = String(r.code || '').trim().toUpperCase();
            if (key) purityTouchMap[key] = parseFloat(r.touch) || 0;
        });

        try {
            const rates = await requestJson(@json(url('/api/rate/current')));
            Object.assign(currentRates, rates || {});
        } catch (e) {
            // rate endpoint failure should not block the form
        }
    }

    function rateForPurity(itype, purityCode) {
        const key = String(purityCode || '').trim().toUpperCase();
        const t = String(itype || '').toUpperCase();
        if (t === 'S') return parseFloat(currentRates.silverRate) || 0;
        if (t === 'P') return parseFloat(currentRates.platinumRate) || 0;
        if (t !== 'G') return 0;
        // Gold: map by purity code (matches Purity Type master codes like 22, 18, 14, 9, 24)
        const goldMap = {
            '22': 'goldRate22K',
            '18': 'goldRate18K',
            '14': 'goldRate14K',
            '9':  'goldRate9K',
            '24': 'thRate',
        };
        const rateKey = goldMap[key];
        if (rateKey) return parseFloat(currentRates[rateKey]) || 0;
        // Fallback: prorate from 22ct using touch%
        const base22 = parseFloat(currentRates.goldRate22K) || 0;
        const touch = parseFloat(purityTouchMap[key]) || 0;
        const base22Touch = parseFloat(purityTouchMap['22']) || 91.6;
        if (base22 > 0 && touch > 0 && base22Touch > 0) {
            return +(base22 * (touch / base22Touch)).toFixed(2);
        }
        return 0;
    }

    function applyPurityAutoFill() {
        const purity = document.getElementById('qtype').value;
        if (!purity) return;
        const key = String(purity).trim().toUpperCase();
        const touchEl = document.getElementById('touch');
        if (touchEl && purityTouchMap[key] !== undefined) {
            touchEl.value = purityTouchMap[key];
        }
        const itype = document.getElementById('itype').value;
        const rate = rateForPurity(itype, key);
        if (rate > 0) {
            ['rate', 'wsrate', 'prate'].forEach((id) => {
                document.getElementById(id).value = rate.toFixed(2);
            });
        }
    }

    async function loadItemByCode(code) {
        const d = await requestJson(@json(url('/item-master/get-item')), 'POST', { code });
        mode = 'edit';
        document.getElementById('title').textContent = 'Edit Item';
        applyItem(d.data);
        document.getElementById('code').disabled = true;
    }

    const normalizeCode = (value) => String(value || '').toUpperCase().trim();

    function setRenameStatus(message, ok = true) {
        const el = document.getElementById('renameStatus');
        el.textContent = message || '';
        el.style.color = ok ? '#375b84' : '#8e1f1f';
    }

    async function checkItemCode(code) {
        return requestJson(@json(url('/item-master/code-exists')), 'POST', { code });
    }

    function openSearch(target = 'edit') {
        searchTarget = target;
        document.getElementById('searchModal').classList.add('show');
        document.getElementById('searchInput').value = '';
        return loadSearch();
    }

    function closeSearch() {
        document.getElementById('searchModal').classList.remove('show');
        if (searchTarget === 'rename-old') {
            document.getElementById('codeChangeModal').classList.add('show');
        }
    }

    function openCodeChange() {
        const currentCode = normalizeCode(document.getElementById('code').value);
        document.getElementById('renameOldCode').value = currentCode;
        document.getElementById('renameNewCode').value = '';
        setRenameStatus('');
        document.getElementById('codeChangeModal').classList.add('show');
        setTimeout(() => (currentCode ? document.getElementById('renameNewCode') : document.getElementById('renameOldCode')).focus(), 0);
    }

    async function validateRenameOld() {
        const oldEl = document.getElementById('renameOldCode');
        const code = normalizeCode(oldEl.value);
        oldEl.value = code;
        if (!code) {
            setRenameStatus('');
            return false;
        }
        const d = await checkItemCode(code);
        if (!d.exists) {
            alert('Invalid Code...');
            oldEl.value = '';
            setRenameStatus('Invalid old code', false);
            oldEl.focus();
            return false;
        }
        const itemName = d.name || (d.item && d.item.name) || '';
        setRenameStatus(itemName ? `Old item: ${itemName}` : 'Old code found');
        return true;
    }

    async function validateRenameNew() {
        const newEl = document.getElementById('renameNewCode');
        const code = normalizeCode(newEl.value);
        newEl.value = code;
        if (!code) return false;
        const d = await checkItemCode(code);
        if (d.exists && !confirm('This Code already exist. Do you want to continue ?')) {
            newEl.value = '';
            newEl.focus();
            return false;
        }
        return true;
    }

    async function submitRenameCode(mergeExisting = false) {
        const oldCode = normalizeCode(document.getElementById('renameOldCode').value);
        const newCode = normalizeCode(document.getElementById('renameNewCode').value);
        if (!oldCode || !newCode) {
            setRenameStatus('Enter old code and new code', false);
            return;
        }
        if (oldCode === newCode) {
            setRenameStatus('Old code and new code are same', false);
            return;
        }
        if (!mergeExisting && !confirm('Do you want to Rename the code ?')) return;

        const res = await fetch(@json(url('/item-master/rename-code')), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ old_code: oldCode, new_code: newCode, merge_existing: mergeExisting })
        });
        const data = await res.json().catch(() => ({ message: 'Invalid server response' }));
        if (res.status === 409 && data.exists) {
            if (confirm('Item2 is already exist. Do you want to change Item1 to Item2 ?')) {
                await submitRenameCode(true);
            }
            return;
        }
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Code rename failed');
        }

        alert('Item Renamed...');
        setRenameStatus(data.message || 'Item renamed successfully');
        status('Item code renamed from ' + oldCode + ' to ' + newCode, true);
        document.getElementById('codeChangeModal').classList.remove('show');
        await loadItemByCode(newCode);
    }

    async function doSave() {
        const p = payload();
        if (p.code_locked) {
            p.mode = 'edit';
        }
        if (!p.code) return status('Item code is empty', false);
        if (!p.desc) return status('Item description is empty', false);
        const d = await requestJson(@json(url('/item-master/save')), 'POST', p);
        status(d.message || 'Saved', true);
        mode = 'add';
    }

    async function doDelete() {
        const code = (document.getElementById('code').value || '').trim().toUpperCase();
        if (!code) return status('Enter code to delete', false);
        if (!confirm('Do you want to delete this item?')) return;
        const d = await requestJson(@json(url('/item-master/delete')), 'POST', { code });
        status(d.message || 'Deleted', true);
        resetForm();
        mode = 'add';
    }

    async function loadSearch(search = '') {
        const d = await requestJson(@json(url('/item-master/search')) + '?search=' + encodeURIComponent(search));
        const body = document.getElementById('searchRows');
        body.innerHTML = '';
        (d.data || []).forEach((r) => {
            const tr = document.createElement('tr');
            tr.dataset.code = r.code;
            tr.innerHTML = `<td>${r.code || ''}</td><td>${r.name || ''}</td><td>${r.itype || ''}</td>`;
            body.appendChild(tr);
        });
    }

    document.getElementById('btnAdd').addEventListener('click', () => {
        mode = 'add';
        document.getElementById('title').textContent = 'Add New Item';
        resetForm();
        document.getElementById('code').focus();
    });
    document.getElementById('btnEdit').addEventListener('click', async () => {
        mode = 'edit';
        document.getElementById('title').textContent = 'Edit Item';
        await openSearch('edit');
    });
    document.getElementById('btnDelete').addEventListener('click', doDelete);
    document.getElementById('btnSave').addEventListener('click', () => doSave().catch((e) => status(e.message, false)));
    document.getElementById('btnCancel').addEventListener('click', () => {
        mode = 'add';
        document.getElementById('title').textContent = 'Item Add/Edit';
        resetForm();
    });
    document.getElementById('btnSearch').addEventListener('click', async () => {
        await openSearch('edit');
    });
    document.getElementById('btnRenameCode').addEventListener('click', openCodeChange);
    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else {
            window.close();
        }
    });

    document.getElementById('code').addEventListener('change', () => {
        const code = (document.getElementById('code').value || '').toUpperCase().trim();
        document.getElementById('code').value = code;
        if (!code || mode !== 'edit') return;
        loadItemByCode(code).catch((e) => status(e.message, false));
    });

    document.getElementById('qtype').addEventListener('change', applyPurityAutoFill);
    document.getElementById('itype').addEventListener('change', applyPurityAutoFill);

    document.getElementById('searchInput').addEventListener('input', () => loadSearch(document.getElementById('searchInput').value));
    document.getElementById('searchClose').addEventListener('click', closeSearch);
    document.getElementById('searchRows').addEventListener('click', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        document.querySelectorAll('#searchRows tr').forEach((r) => r.classList.remove('active'));
        tr.classList.add('active');
    });
    document.getElementById('searchRows').addEventListener('dblclick', (e) => {
        const tr = e.target.closest('tr');
        if (!tr) return;
        document.getElementById('searchModal').classList.remove('show');
        if (searchTarget === 'rename-old') {
            document.getElementById('renameOldCode').value = normalizeCode(tr.dataset.code || '');
            document.getElementById('codeChangeModal').classList.add('show');
            validateRenameOld().catch((err) => setRenameStatus(err.message, false));
            document.getElementById('renameNewCode').focus();
            return;
        }
        mode = 'edit';
        loadItemByCode(tr.dataset.code || '').catch((err) => status(err.message, false));
    });

    document.getElementById('renameHelp').addEventListener('click', async () => {
        document.getElementById('codeChangeModal').classList.remove('show');
        await openSearch('rename-old');
    });
    document.getElementById('renameClose').addEventListener('click', () => document.getElementById('codeChangeModal').classList.remove('show'));
    document.getElementById('renameOldCode').addEventListener('change', () => validateRenameOld().catch((err) => setRenameStatus(err.message, false)));
    document.getElementById('renameNewCode').addEventListener('change', () => validateRenameNew().catch((err) => setRenameStatus(err.message, false)));
    ['renameOldCode', 'renameNewCode'].forEach((id) => {
        document.getElementById(id).addEventListener('input', (e) => {
            e.target.value = normalizeCode(e.target.value);
        });
    });
    document.getElementById('renameOk').addEventListener('click', () => submitRenameCode().catch((err) => setRenameStatus(err.message, false)));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') {
            e.preventDefault();
            doSave().catch((err) => status(err.message, false));
        }
    });

    loadOptions().then(() => resetForm()).catch((e) => status(e.message, false));
})();
</script>
</body>
</html>
