<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Other Item Details</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            background: #c0c0c0;
            display: flex;
            justify-content: center;
            padding: 20px;
        }
        .window { width: 520px; background: #c0c0c0; border: 2px outset #ddd; padding: 12px; }
        h2#stHead {
            text-align: center;
            font-size: 12pt;
            color: #0000ff;
            text-decoration: underline;
            margin-bottom: 14px;
        }
        .warn {
            text-align: center;
            margin-bottom: 8px;
            color: #8a1f1f;
            font-weight: 700;
        }
        .form-row { display: flex; align-items: center; margin-bottom: 6px; }
        .form-row label { width: 140px; text-align: right; padding-right: 10px; font-weight: 700; white-space: nowrap; }
        .form-row input, .form-row select {
            width: 200px;
            padding: 4px 6px;
            font-size: 10pt;
            font-weight: 700;
            border: 2px inset #ccc;
        }
        .form-row input:focus, .form-row select:focus { background: rgb(255, 150, 0); outline: none; }
        .form-row input[type="text"].upper { text-transform: uppercase; }
        .form-row input.wide { width: 340px; }
        .form-row input[type="checkbox"] { width: auto; }
        .chk-label { font-weight: 400; margin-left: 6px; cursor: pointer; }
        .btn-bar {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
            padding: 10px;
            border: 3px solid red;
            border-radius: 8px;
            background: #c1c1c1;
        }
        .btn-bar button { padding: 6px 18px; font-size: 10pt; font-weight: 700; cursor: pointer; min-width: 75px; }
        .btn-bar button:disabled { color: #888; cursor: default; }
        #msgArea { text-align: center; margin-top: 8px; min-height: 20px; font-weight: 700; }
        .msg-ok { color: green; }
        .msg-error { color: red; }
    </style>
</head>
<body>
<div class="window">
    <h2 id="stHead">Add New Item</h2>
    @if($tableMissing)
        <div class="warn">`itemsothers` table not found.</div>
    @endif

    <div class="form-row">
        <label for="fCode">Code :</label>
        <input type="text" id="fCode" maxlength="10" class="upper" autocomplete="off">
    </div>
    <div class="form-row">
        <label for="fDesc">Name :</label>
        <input type="text" id="fDesc" maxlength="30" class="upper wide" autocomplete="off">
    </div>
    <div class="form-row">
        <label for="fGrp">Group :</label>
        <select id="fGrp">
            <option value="">-- Select --</option>
            @foreach($groups as $g)
                <option value="{{ $g->grpcode }}">{{ $g->grpdesc }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-row">
        <label for="fSrate">Sales Rate :</label>
        <input type="text" id="fSrate" value="0.00" autocomplete="off">
    </div>
    <div class="form-row">
        <label for="fPrate">Purchase Rate :</label>
        <input type="text" id="fPrate" value="0.00" autocomplete="off">
    </div>
    <div class="form-row">
        <label for="fOpstock">Op. Stock :</label>
        <input type="text" id="fOpstock" value="0" autocomplete="off">
    </div>
    <div class="form-row">
        <label for="fOpcost">Op. Cost :</label>
        <input type="text" id="fOpcost" value="0.00" autocomplete="off">
    </div>
    <div class="form-row">
        <label for="fStock">Cl. Stock :</label>
        <input type="text" id="fStock" value="0" autocomplete="off">
    </div>
    <div class="form-row">
        <label for="fCost">Cl. Cost :</label>
        <input type="text" id="fCost" value="0.00" autocomplete="off">
    </div>
    <div class="form-row">
        <label>&nbsp;</label>
        <input type="checkbox" id="fKeepstk" checked>
        <label class="chk-label" for="fKeepstk">Keep Stock</label>
    </div>

    <div class="btn-bar">
        <button id="btnAdd">Add</button>
        <button id="btnEdit">Edit</button>
        <button id="btnDelete">Delete</button>
        <button id="btnSave" disabled>Save</button>
        <button id="btnCancel" disabled>Cancel</button>
        <button id="btnExit">Exit</button>
    </div>
    <div id="msgArea"></div>
</div>

<script>
(function () {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const fCode = document.getElementById('fCode');
    const fDesc = document.getElementById('fDesc');
    const fGrp = document.getElementById('fGrp');
    const fSrate = document.getElementById('fSrate');
    const fPrate = document.getElementById('fPrate');
    const fOpstock = document.getElementById('fOpstock');
    const fOpcost = document.getElementById('fOpcost');
    const fStock = document.getElementById('fStock');
    const fCost = document.getElementById('fCost');
    const fKeepstk = document.getElementById('fKeepstk');
    const stHead = document.getElementById('stHead');
    const msgArea = document.getElementById('msgArea');

    const btnAdd = document.getElementById('btnAdd');
    const btnEdit = document.getElementById('btnEdit');
    const btnDelete = document.getElementById('btnDelete');
    const btnSave = document.getElementById('btnSave');
    const btnCancel = document.getElementById('btnCancel');
    const btnExit = document.getElementById('btnExit');

    let mode = 'A';

    function initItem() {
        fCode.value = '';
        fDesc.value = '';
        fGrp.value = '';
        fSrate.value = '0.00';
        fPrate.value = '0.00';
        fOpstock.value = '0';
        fOpcost.value = '0.00';
        fStock.value = '0';
        fCost.value = '0.00';
        fKeepstk.checked = true;
    }

    function cbCheck(state) {
        btnAdd.disabled = true;
        btnEdit.disabled = true;
        btnDelete.disabled = true;
        btnSave.disabled = true;
        btnCancel.disabled = true;
        btnExit.disabled = true;
        if (state === 'A' || state === 'E') {
            btnSave.disabled = false;
            btnCancel.disabled = false;
        } else {
            btnAdd.disabled = false;
            btnDelete.disabled = false;
            btnEdit.disabled = false;
            btnExit.disabled = false;
        }
    }

    function showMsg(text, isError) {
        msgArea.textContent = text;
        msgArea.className = isError ? 'msg-error' : 'msg-ok';
        setTimeout(() => { msgArea.textContent = ''; }, 4000);
    }

    async function post(url, payload) {
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload || {})
        });
        return await resp.json();
    }

    window.receiveHelpCode = function (code) {
        if (code) {
            fCode.value = String(code).toUpperCase();
            lookupCode();
        } else {
            cbCheck('C');
        }
    };

    async function lookupCode() {
        const code = fCode.value.trim();
        if (!code) {
            initItem();
            return;
        }

        const res = await post(@json(url('/other-items/lookup')), { code: code });
        if (!res.success) {
            showMsg(res.message || 'Lookup failed.', true);
            return;
        }

        if (mode === 'A') {
            if (res.found) {
                showMsg('This item already exists.', true);
                fCode.value = '';
            }
        } else if (mode === 'E' || mode === 'D') {
            if (!res.found) {
                showMsg('This item does not exist.', true);
            } else {
                const d = res.data || {};
                fDesc.value = d.name || '';
                fGrp.value = d.grp || '';
                fSrate.value = parseFloat(d.srate || 0).toFixed(2);
                fPrate.value = parseFloat(d.prate || 0).toFixed(2);
                fOpcost.value = parseFloat(d.opcost || 0).toFixed(2);
                fCost.value = parseFloat(d.cost || 0).toFixed(2);
                fOpstock.value = d.opstock || '0';
                fStock.value = d.stock || '0';
                fKeepstk.checked = (parseInt(d.keepstk, 10) === 1);

                if (mode === 'D') {
                    if (confirm('Do you want to delete this item?')) {
                        const del = await post(@json(url('/other-items/delete')), { code: code });
                        showMsg(del.message || 'Delete failed.', !del.success);
                    }
                    initItem();
                } else {
                    cbCheck('E');
                    fDesc.focus();
                }
            }
        }
    }

    btnAdd.addEventListener('click', () => {
        mode = 'A';
        stHead.textContent = 'Add New Item';
        cbCheck('A');
        initItem();
        fCode.focus();
    });

    btnEdit.addEventListener('click', () => {
        mode = 'E';
        stHead.textContent = 'Edit an Item';
        window.open(@json(url('/other-items/help')), 'itemHelp', 'width=500,height=550,scrollbars=yes');
    });

    btnDelete.addEventListener('click', () => {
        mode = 'D';
        stHead.textContent = 'Delete an Item';
        window.open(@json(url('/other-items/help')), 'itemHelp', 'width=500,height=550,scrollbars=yes');
    });

    btnSave.addEventListener('click', async () => {
        const code = fCode.value.trim().toUpperCase();
        if (!code) {
            showMsg('Item code is empty.', true);
            return;
        }

        const payload = {
            code: code,
            name: fDesc.value.trim().toUpperCase(),
            grp: fGrp.value,
            srate: parseFloat(fSrate.value) || 0,
            prate: parseFloat(fPrate.value) || 0,
            cost: parseFloat(fCost.value) || 0,
            opcost: parseFloat(fOpcost.value) || 0,
            opstock: parseInt(fOpstock.value, 10) || 0,
            stock: parseInt(fStock.value, 10) || 0,
            keepstk: fKeepstk.checked ? 1 : 0
        };

        const url = mode === 'A' ? @json(url('/other-items/add')) : @json(url('/other-items/edit'));
        const res = await post(url, payload);
        showMsg(res.message || 'Operation failed.', !res.success);
        if (res.success) {
            cbCheck('S');
            initItem();
            stHead.textContent = '';
        }
    });

    btnCancel.addEventListener('click', () => {
        cbCheck('C');
        stHead.textContent = '';
        initItem();
    });

    btnExit.addEventListener('click', () => {
        if (!confirm('Do you want to exit?')) {
            return;
        }
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else {
            window.close();
        }
    });

    fCode.addEventListener('blur', lookupCode);

    const tabOrder = [fCode, fDesc, fGrp, fSrate, fPrate, fOpstock, fOpcost, fStock, fCost];
    tabOrder.forEach((el, i) => {
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (i + 1 < tabOrder.length) {
                    tabOrder[i + 1].focus();
                } else {
                    btnSave.focus();
                }
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') {
            e.preventDefault();
            if (!btnSave.disabled) btnSave.click();
        }
    });

    initItem();
    cbCheck('A');
    btnAdd.click();
})();
</script>
</body>
</html>

