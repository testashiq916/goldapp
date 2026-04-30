<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Receipt Details</title>
    <style>
        :root {
            --bg1: #edf3fb;
            --bg2: #e5edf8;
            --card: #ffffff;
            --line: #d0dced;
            --line2: #c1d0e5;
            --text: #1e2f43;
            --muted: #586f89;
            --brand1: #145e93;
            --brand2: #1c7bb9;
            --ok: #1f8a4d;
            --err: #a12525;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            background:
                radial-gradient(circle at 8% 0%, #f4f8ff 0, transparent 40%),
                radial-gradient(circle at 100% 0%, #e8f2ff 0, transparent 36%),
                linear-gradient(180deg, var(--bg1), var(--bg2));
            color: var(--text);
            font-size: 12px;
            min-height: 100vh;
            padding: 16px 10px;
        }

        .win {
            width: min(1020px, calc(100vw - 24px));
            margin: 0 auto;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: 0 14px 34px rgba(18, 44, 74, 0.13);
            overflow: hidden;
        }

        .title {
            font-size: 24px;
            font-weight: 800;
            padding: 16px 18px 8px;
            margin: 0;
            color: #fff;
            background: linear-gradient(105deg, var(--brand1), var(--brand2));
            letter-spacing: 0.2px;
        }

        .panel {
            padding: 14px 16px 8px;
            background: linear-gradient(180deg, #fbfdff, #f4f8fe);
        }

        .grid { display: grid; grid-template-columns: 150px 1fr 84px 1fr; gap: 8px 10px; align-items: center; }
        .lbl { font-size: 12px; font-weight: 700; text-align: right; color: var(--muted); text-transform: uppercase; letter-spacing: 0.4px; }
        input, select {
            height: 31px;
            font-size: 13px;
            border: 1px solid var(--line2);
            border-radius: 7px;
            background: #fff;
            padding: 0 8px;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            color: #162b3f;
        }
        input:focus, select:focus { outline: none; border-color: #4f96da; box-shadow: 0 0 0 3px rgba(79,150,218,.16); background: #fff; }
        .readonly { background: #eef3f9; color: #3f5c7b; }
        .hint { font-size: 11px; color: #444; min-height: 16px; }
        .desc { grid-column: 2 / span 3; }
        .btns { margin-top: 14px; display: flex; justify-content: center; gap: 10px; }
        button {
            height: 33px;
            min-width: 94px;
            border: 1px solid var(--line2);
            border-radius: 8px;
            background: #fff;
            color: #1e3651;
            font-weight: 700;
            cursor: pointer;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            transition: all .16s ease;
        }
        button:hover { background: #f6faff; border-color: #98b7d8; }
        button:active { transform: translateY(1px); }
        #btnSave { background: linear-gradient(100deg, #1f9a58, #178448); color: #fff; border-color: #12713c; }
        #btnSave:hover { filter: brightness(1.03); }
        #btnExit { background: linear-gradient(100deg, #b54646, #9f2f2f); color: #fff; border-color: #8d2a2a; }
        #btnExit:hover { filter: brightness(1.03); }
        .small { min-width: 56px; }
        .checks { margin-top: 10px; display: flex; gap: 20px; justify-content: flex-end; padding-bottom: 8px; }
        .checks label { display: inline-flex; gap: 6px; align-items: center; font-weight: 700; }
        .status {
            margin-top: 8px;
            min-height: 34px;
            text-align: center;
            font-weight: 700;
            border-top: 1px solid var(--line);
            padding: 10px 8px 12px;
            background: #f8fbff;
        }
        .status.ok { color: var(--ok); }
        .status.err { color: var(--err); }

        @media (max-width: 900px) {
            .panel { padding: 12px 10px 8px; }
            .grid { grid-template-columns: 1fr; }
            .lbl { text-align: left; }
            .desc { grid-column: auto; }
            .checks { justify-content: flex-start; flex-wrap: wrap; }
            .btns { justify-content: stretch; }
            .btns button { flex: 1; }
        }
    </style>
</head>
<body>
<div class="win">
    <div class="title" id="formTitle">Receipt Details</div>
<div class="panel">
    <div class="grid">
        <div class="lbl">Voucher No.</div>
        <input id="vchno" class="readonly" readonly>
        <div class="lbl"></div><div></div>

        <div class="lbl">Date</div>
        <input id="tdate" maxlength="10" placeholder="dd/mm/yyyy">
        <div class="lbl"></div><div></div>

        <div class="lbl">Cash/Bank Code</div>
        <select id="cbcode"></select>
        <div class="lbl"></div><div></div>

        <div class="lbl">SMan</div>
        <select id="staff"><option value="">--</option></select>
        <div class="lbl"></div><div></div>

        <div class="lbl">Rcpt/Pmnt</div>
        <select id="rp">
            <option value="P">Payment</option>
            <option value="R">Receipt</option>
        </select>
        <div class="lbl"></div><div></div>

        <div class="lbl">A/C Code</div>
        <div style="display:flex;gap:4px;">
            <input id="accode" list="acList" style="flex:1;">
            <datalist id="acList"></datalist>
            <button id="btnAcHelp" class="small" type="button">^</button>
        </div>
        <div class="lbl">OB :</div>
        <input id="ob" class="readonly" readonly value="0.00">

        <div class="lbl">Suspense A/c</div>
        <div style="display:flex;gap:4px;">
            <input id="scode" list="scList" style="flex:1;">
            <datalist id="scList"></datalist>
            <button id="btnNewScode" type="button">New</button>
        </div>
        <div class="lbl">CB :</div>
        <input id="cb" class="readonly" readonly value="0.00">

        <div class="lbl">Amount</div>
        <input id="amount" type="number" step="0.01" value="0.00" style="text-align:right;">
        <div class="lbl">Against Sus.Entry</div>
        <div style="display:flex;gap:4px;">
            <input id="against_vchno" maxlength="10" style="text-transform:uppercase;flex:1;">
            <button id="btnSusHelp" class="small" type="button">^</button>
        </div>

        <div class="lbl">Description</div>
        <input id="particular" class="desc" maxlength="70" style="text-transform:uppercase;">
    </div>

    <div class="btns">
        <button id="btnSave" type="button">&Save</button>
        <button id="btnCancel" type="button">&Cancel</button>
        <button id="btnExit" type="button">&Exit</button>
    </div>
    <div class="checks">
        <label><input id="printSlip" type="checkbox"> Print Slip</label>
        <label><input id="printObCb" type="checkbox" checked> Print OB/CB</label>
    </div>
    <div class="status" id="status"></div>
</div>
</div>

<div id="againstHelp" style="position:fixed;inset:0;background:rgba(7,18,33,.42);display:none;align-items:center;justify-content:center;z-index:9999;">
    <div style="width:min(860px,calc(100vw - 30px));background:#fff;border:1px solid #c9d8ea;border-radius:10px;overflow:hidden;box-shadow:0 16px 36px rgba(12,30,54,.32);">
        <div style="padding:10px 12px;background:linear-gradient(100deg,#155f95,#1d7fbe);color:#fff;font-weight:700;display:flex;align-items:center;gap:10px;">
            <span style="flex:1;">Against Suspense Entry Help</span>
            <input id="againstSearch" placeholder="Search voucher/note..." style="height:28px;width:240px;border:1px solid rgba(255,255,255,.55);border-radius:6px;padding:0 8px;">
            <button id="againstClose" type="button" style="height:28px;min-width:66px;">Close</button>
        </div>
        <div style="max-height:420px;overflow:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead>
                <tr style="position:sticky;top:0;background:#edf4fc;color:#284f75;">
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #d7e3f2;">Vch No</th>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #d7e3f2;">Date</th>
                    <th style="text-align:right;padding:8px;border-bottom:1px solid #d7e3f2;">Amount</th>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid #d7e3f2;">Note</th>
                </tr>
                </thead>
                <tbody id="againstRows"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const BASE = @json(url('/api/accounts/suspense-entry'));
    const $ = id => document.getElementById(id);

    let obBal = 0;
    let formMode = 'A';
    let editSlno = 0;
    let editVchno = '';

    function setStatus(msg, ok) {
        const el = $('status');
        el.textContent = msg || '';
        el.className = 'status ' + (ok ? 'ok' : 'err');
    }

    async function api(payload) {
        const res = await fetch(BASE, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok || data.success === false) throw new Error(data.error || 'Request failed');
        return data;
    }

    function refreshCb() {
        const amt = Math.abs(parseFloat($('amount').value || '0')) || 0;
        $('cb').value = Math.abs(obBal + amt).toFixed(2);
    }

    async function loadLists() {
        const [acc, susp] = await Promise.all([
            api({ action: 'account_list' }),
            api({ action: 'suspense_list' }),
        ]);
        $('acList').innerHTML = (acc.data || []).map(r => `<option value="${(r.accode || '').replace(/"/g, '&quot;')}">${(r.name || '').replace(/</g, '&lt;')}</option>`).join('');
        $('scList').innerHTML = (susp.data || []).map(r => `<option value="${(r.scode || '').replace(/"/g, '&quot;')}"></option>`).join('');
    }

    async function init() {
        const d = await api({ action: 'init', rp: $('rp').value });
        $('vchno').value = d.nextVchno || '';
        $('tdate').value = d.today || '';
        $('cbcode').innerHTML = (d.cbAccounts || []).map(r => `<option value="${r.accode}">${r.accode} - ${r.name || ''}</option>`).join('');
        $('staff').innerHTML = `<option value="">--</option>` + (d.salesmen || []).map(r => `<option value="${r.code}">${r.code} - ${r.name || ''}</option>`).join('');
        if (d.defaultSuspAccode) $('accode').value = d.defaultSuspAccode;
        $('formTitle').textContent = $('rp').value === 'P' ? 'Payment Details' : 'Receipt Details';
        setStatus('', true);
        refreshCb();
    }

    async function loadAccount() {
        const accode = ($('accode').value || '').trim().toUpperCase();
        $('accode').value = accode;
        if (!accode) return;
        try {
            const d = await api({ action: 'load_account', accode });
            obBal = parseFloat(d.balance || 0) || 0;
            $('ob').value = Math.abs(obBal).toFixed(2);
            refreshCb();
        } catch (e) {
            setStatus(e.message, false);
        }
    }

    async function save() {
        try {
            const d = await api({
                action: 'save',
                mode: formMode,
                slno: editSlno,
                vchno: editVchno,
                rp: $('rp').value,
                tdate: $('tdate').value,
                cbcode: $('cbcode').value,
                accode: $('accode').value,
                scode: $('scode').value,
                amount: $('amount').value,
                against_vchno: $('against_vchno').value,
                particular: $('particular').value,
                staff: $('staff').value,
                print_slip: $('printSlip').checked,
                print_obcb: $('printObCb').checked,
            });
            setStatus('Saved: ' + (d.vchno || ''), true);
            formMode = 'A';
            editSlno = 0;
            editVchno = '';
            $('vchno').value = d.nextVchno || $('vchno').value;
            $('amount').value = '0.00';
            $('against_vchno').value = '';
            $('particular').value = '';
            refreshCb();
            $('staff').focus();
        } catch (e) {
            setStatus(e.message, false);
        }
    }

    async function loadAgainstList(search = '') {
        const accode = ($('accode').value || '').trim().toUpperCase();
        const scode = ($('scode').value || '').trim().toUpperCase();
        if (!accode || !scode) {
            setStatus('Enter A/C Code and Suspense A/c before help', false);
            return;
        }
        const ttype = $('rp').value === 'R' ? 'P' : 'R';
        const d = await api({ action: 'against_list', accode, scode, ttype, search });
        const rows = d.data || [];
        $('againstRows').innerHTML = rows.map(r => `
            <tr data-vch="${String(r.vchno || '').replace(/"/g,'&quot;')}" style="cursor:pointer;">
                <td style="padding:7px 8px;border-bottom:1px solid #e6edf8;">${String(r.vchno || '').replace(/</g,'&lt;')}</td>
                <td style="padding:7px 8px;border-bottom:1px solid #e6edf8;">${String(r.tdate || '').replace(/</g,'&lt;')}</td>
                <td style="padding:7px 8px;border-bottom:1px solid #e6edf8;text-align:right;">${(Math.abs(parseFloat(r.amount || 0)) || 0).toFixed(2)}</td>
                <td style="padding:7px 8px;border-bottom:1px solid #e6edf8;">${String(r.note || '').replace(/</g,'&lt;')}</td>
            </tr>
        `).join('');
        Array.from($('againstRows').querySelectorAll('tr')).forEach(tr => {
            tr.addEventListener('click', () => {
                $('against_vchno').value = tr.dataset.vch || '';
                $('againstHelp').style.display = 'none';
                $('against_vchno').focus();
            });
        });
    }

    function resetForm() {
        $('amount').value = '0.00';
        $('against_vchno').value = '';
        $('particular').value = '';
        formMode = 'A';
        editSlno = 0;
        editVchno = '';
        setStatus('', true);
        refreshCb();
        $('staff').focus();
    }

    function exitForm() {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else {
            window.location.href = @json(url('/dashboard'));
        }
    }

    $('rp').addEventListener('change', init);
    $('accode').addEventListener('change', loadAccount);
    $('amount').addEventListener('input', refreshCb);
    $('btnSave').addEventListener('click', save);
    $('btnCancel').addEventListener('click', resetForm);
    $('btnExit').addEventListener('click', exitForm);
    $('btnAcHelp').addEventListener('click', () => $('accode').focus());
    $('btnSusHelp').addEventListener('click', async () => {
        try {
            await loadAgainstList('');
            $('againstHelp').style.display = 'flex';
            $('againstSearch').value = '';
            $('againstSearch').focus();
        } catch (e) {
            setStatus(e.message, false);
        }
    });
    $('btnNewScode').addEventListener('click', () => {
        const w = window.open(@json(url('/accounts/suspense-master')), 'susp_master', 'width=820,height=700,resizable=yes,scrollbars=yes');
        if (w) w.focus();
    });
    window.addEventListener('message', async ev => {
        const d = ev.data || {};
        if (d.type !== 'goldapp:suspac-saved') return;
        try {
            await loadLists();
            if (d.code) $('scode').value = String(d.code).toUpperCase();
            $('scode').focus();
        } catch (_) {}
    });
    $('againstClose').addEventListener('click', () => { $('againstHelp').style.display = 'none'; });
    $('againstHelp').addEventListener('click', ev => { if (ev.target === $('againstHelp')) $('againstHelp').style.display = 'none'; });
    $('againstSearch').addEventListener('input', async () => {
        try { await loadAgainstList($('againstSearch').value || ''); } catch (_) {}
    });

    document.addEventListener('keydown', ev => {
        if (ev.key === 'F9') { ev.preventDefault(); save(); return; }
        if (ev.key === 'Escape') { ev.preventDefault(); exitForm(); return; }
        if (ev.key === 'Enter') {
            const order = ['tdate', 'cbcode', 'staff', 'rp', 'accode', 'scode', 'amount', 'against_vchno', 'particular', 'btnSave'];
            const idx = order.indexOf((document.activeElement || {}).id);
            if (idx >= 0) {
                ev.preventDefault();
                const next = $(order[Math.min(order.length - 1, idx + 1)]);
                if (next) next.focus();
            }
        }
    });

    (async () => {
        try {
            await loadLists();
            await init();
            const qp = new URLSearchParams(window.location.search);
            const modeQ = (qp.get('mode') || '').toUpperCase();
            const slnoQ = parseInt(qp.get('slno') || '0', 10);
            if (modeQ === 'E' && slnoQ > 0) {
                const r = await api({ action: 'load', slno: slnoQ });
                const d = r.data || {};
                formMode = 'E';
                editSlno = slnoQ;
                editVchno = d.vchno || '';
                $('vchno').value = d.vchno || $('vchno').value;
                $('tdate').value = d.tdate || $('tdate').value;
                $('rp').value = (d.rp || $('rp').value).toUpperCase() === 'R' ? 'R' : 'P';
                if (d.cbcode) $('cbcode').value = d.cbcode;
                $('accode').value = d.accode || '';
                await loadAccount();
                $('scode').value = d.scode || '';
                $('amount').value = Number(d.amount || 0).toFixed(2);
                $('particular').value = d.particular || '';
                $('staff').value = d.staff || '';
                refreshCb();
                setStatus('Edit mode: ' + (d.vchno || ''), true);
            }
            $('staff').focus();
        } catch (e) {
            setStatus(e.message, false);
        }
    })();
})();
</script>
</body>
</html>
