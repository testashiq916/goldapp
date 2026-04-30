<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cheque Clearing</title>
    <style>
        :root{ --b1:#155f95; --b2:#1d7fbe; --bg:#eaf1fb; --line:#c7d7eb; --line2:#b5cae3; --txt:#1f3550; --mut:#58718d; }
        body{ margin:0; font-family:"Segoe UI",Tahoma,Arial,sans-serif; background:linear-gradient(180deg,#eef4fd,#e5edf9); color:var(--txt); font-size:12px; padding:14px 10px; }
        .win{ width:min(980px,calc(100vw - 24px)); margin:0 auto; background:#fff; border:1px solid var(--line); border-radius:12px; overflow:hidden; box-shadow:0 14px 34px rgba(10,30,55,.14);}
        .head{ margin:0; padding:14px 16px; color:#fff; font-size:28px; font-weight:800; background:linear-gradient(102deg,var(--b1),var(--b2)); }
        .p{ padding:12px 14px; background:linear-gradient(180deg,#fbfdff,#f5f9ff); }
        .g{ display:grid; grid-template-columns:150px 1fr 120px 1fr; gap:8px 10px; align-items:center; }
        .l{ text-align:right; font-weight:700; color:var(--mut); text-transform:uppercase; letter-spacing:.35px; }
        input,select{ height:32px; border:1px solid var(--line2); border-radius:8px; padding:0 8px; font-size:13px; font-family:"Segoe UI",Tahoma,Arial,sans-serif; color:#1b334c; background:#fff; }
        input:focus,select:focus{ outline:none; border-color:#4f96da; box-shadow:0 0 0 3px rgba(79,150,218,.14); }
        .ro{ background:#ecf2fa; color:#405d7c; }
        .num{ text-align:right; }
        .row{ display:flex; gap:8px; align-items:center; }
        button{ height:34px; min-width:98px; border:1px solid var(--line2); border-radius:8px; background:#fff; color:#223c58; font-weight:800; cursor:pointer; }
        button:hover{ background:#f5faff; border-color:#9ebce0; }
        #btnSave{ background:linear-gradient(100deg,#209b58,#188545); color:#fff; border-color:#15753f; }
        #btnExit{ background:linear-gradient(100deg,#af4545,#9b2f2f); color:#fff; border-color:#8d2c2c; }
        .checks{ display:flex; gap:18px; align-items:center; }
        .checks label{ display:inline-flex; gap:6px; align-items:center; font-weight:700; color:#2e4a67; }
        .btns{ margin-top:12px; display:flex; justify-content:center; gap:10px; border-top:1px solid #dce7f5; padding-top:12px; }
        .status{ margin-top:8px; min-height:16px; text-align:center; font-weight:800; }
        .ok{ color:#157a42; } .err{ color:#a52c2c; }
        .overlay{ position:fixed; inset:0; background:rgba(4,14,26,.44); display:none; align-items:center; justify-content:center; z-index:9999; }
        .modal{ width:min(860px,calc(100vw - 24px)); background:#fff; border:1px solid #c8d8eb; border-radius:10px; overflow:hidden; box-shadow:0 16px 36px rgba(8,24,44,.32); }
        .mh{ display:flex; gap:8px; align-items:center; padding:10px 12px; background:linear-gradient(102deg,var(--b1),var(--b2)); color:#fff; font-weight:800; }
        .mh .sp{ flex:1; } .mh input{ height:28px; width:220px; }
        .mb{ max-height:390px; overflow:auto; } .mb table{ width:100%; border-collapse:collapse; } .mb th,.mb td{ padding:7px 8px; border-bottom:1px solid #e7eef9; }
        .mb tr{ cursor:pointer; } .mb tr:hover td{ background:#f3f9ff; }
        @media (max-width:900px){ .g{ grid-template-columns:1fr; } .l{ text-align:left; } }
    </style>
</head>
<body>
<div class="win">
    <h1 class="head">{{ ($module ?? '') === 'discount-allocation' ? 'Discount Allocation' : 'Cheque Clearing' }}</h1>
    <div class="p">
        <div class="g">
            <div class="l">Doc.No.</div><input id="vchno" class="ro" readonly>
            <div class="l">Date</div><input id="tdate" maxlength="10" placeholder="dd/mm/yyyy">

            <div class="l">Cheque No.</div>
            <div class="row">
                <input id="chequeno" maxlength="20" style="text-transform:uppercase;flex:1;">
                <button id="btnHelp" type="button">Help</button>
            </div>
            <div class="l">Cheque Dt</div><input id="chqdate" maxlength="10" placeholder="dd/mm/yyyy">

            <div class="l">Party Code</div><input id="partyCode" class="ro" readonly>
            <div class="l">Party Name</div><input id="partyName" class="ro" readonly>

            <div class="l">Bank</div><select id="bank"></select>
            <div class="l">Type</div><input id="typeText" class="ro" readonly>

            <div class="l">Amount</div><input id="amount" class="num" type="number" step="0.01">
            <div class="l">Bank Exp</div><input id="expense" class="num" type="number" step="0.01" value="0.00">

            <div class="l">Interest && SCharge</div><input id="scharge" class="num" type="number" step="0.01" value="0.00">
            <div class="l">Net Amt</div><input id="netAmount" class="ro num" readonly value="0.00">
        </div>

        <div style="margin-top:10px;display:flex;justify-content:flex-end;">
            <div class="checks">
                <label><input id="bounce" type="checkbox"> Chq Bounced</label>
                <label><input id="fr" type="checkbox" checked> Fr</label>
            </div>
        </div>

        <div class="btns">
            <button id="btnSave" type="button">&Save</button>
            <button id="btnExit" type="button">E&xit</button>
        </div>
        <div id="status" class="status"></div>
    </div>
</div>

<div id="help" class="overlay">
    <div class="modal">
        <div class="mh">
            <span class="sp">Cheque Help</span>
            <input id="helpSearch" placeholder="Search cheque/doc/code">
            <button id="helpClose" type="button">Close</button>
        </div>
        <div class="mb">
            <table>
                <thead><tr><th>Cheque</th><th>Doc No</th><th>Code</th><th>Date</th><th style="text-align:right;">Amount</th></tr></thead>
                <tbody id="helpRows"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const API = @json(url('/api/accounts/pdc-collection'));
    const $ = id => document.getElementById(id);

    let mode = 'A';
    let editSlno = 0;
    let sidocno = '';
    let rp = '';
    const prefillSlno = @json($prefillSlno ?? 0);
    const prefillChequeNo = @json($prefillChequeNo ?? '');

    function setStatus(msg, ok){
        const el = $('status');
        el.textContent = msg || '';
        el.className = 'status ' + (ok ? 'ok' : 'err');
    }
    async function api(payload){
        const res = await fetch(API, {
            method:'POST',
            credentials:'same-origin',
            headers:{
                'Content-Type':'application/json',
                'Accept':'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With':'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        const d = await res.json();
        if(!res.ok || d.success === false) throw new Error(d.error || 'Request failed');
        return d;
    }

    function recalcNet(){
        const a = parseFloat($('amount').value || '0') || 0;
        const e = parseFloat($('expense').value || '0') || 0;
        $('netAmount').value = (a - e).toFixed(2);
    }

    async function initPage(){
        const [init, banks] = await Promise.all([
            api({action:'init'}),
            api({action:'bank_list'}),
        ]);
        $('vchno').value = init.vchno || '';
        $('tdate').value = init.today || '';
        $('fr').checked = !!init.fr;
        $('bank').innerHTML = `<option value="">--</option>` + (banks.data || []).map(r => `<option value="${String(r.accode || '').replace(/"/g,'&quot;')}">${String(r.accode || '').replace(/</g,'&lt;')} - ${String(r.name || '').replace(/</g,'&lt;')}</option>`).join('');
        if (prefillChequeNo) {
            $('chequeno').value = prefillChequeNo;
            if (prefillSlno) editSlno = Number(prefillSlno) || 0;
            lookupCheque();
        }
    }

    async function lookupCheque(){
        const ch = ($('chequeno').value || '').trim().toUpperCase();
        $('chequeno').value = ch;
        if(!ch) return;
        try{
            const d = await api({ action:'lookup_cheque', chqno: ch });
            const x = d.data || {};
            sidocno = x.sidocno || '';
            $('partyCode').value = x.party_code || '';
            $('partyName').value = x.party_name || '';
            $('bank').value = x.bank || '';
            $('amount').value = (Number(x.amount || 0)).toFixed(2);
            $('netAmount').value = (Number(x.amount || 0)).toFixed(2);
            $('chqdate').value = x.chqdate || '';
            $('typeText').value = x.type_text || '';
            rp = x.rp || '';
            setStatus('', true);
        }catch(e){
            setStatus(e.message, false);
        }
    }

    async function loadHelp(search){
        try{
            const d = await api({ action:'cheque_help', search: search || '' });
            $('helpRows').innerHTML = (d.data || []).map(r => `<tr data-chq="${String(r.chqno || '').replace(/"/g,'&quot;')}"><td>${String(r.chqno || '').replace(/</g,'&lt;')}</td><td>${String(r.docno || '').replace(/</g,'&lt;')}</td><td>${String(r.code || '').replace(/</g,'&lt;')}</td><td>${String(r.chqdate || '').replace(/</g,'&lt;')}</td><td style="text-align:right;">${Number(r.amount || 0).toFixed(2)}</td></tr>`).join('');
        }catch(e){ setStatus(e.message,false); }
    }
    function openHelp(){ $('help').style.display='flex'; loadHelp($('helpSearch').value.trim()); }
    function closeHelp(){ $('help').style.display='none'; }

    async function save(){
        try{
            const d = await api({
                action:'save',
                mode,
                slno: editSlno,
                vchno: $('vchno').value,
                sidocno,
                tdate: $('tdate').value,
                chequeno: $('chequeno').value,
                chqdate: $('chqdate').value,
                party_code: $('partyCode').value,
                bank: $('bank').value,
                amount: $('amount').value,
                expense: $('expense').value,
                scharge: $('scharge').value,
                net_amount: $('netAmount').value,
                bounce: $('bounce').checked,
                fr: $('fr').checked,
                rp,
            });
            setStatus('Saved: ' + (d.vchno || ''), true);
            mode = 'A';
            editSlno = 0;
            sidocno = '';
            rp = '';
            $('vchno').value = d.nextVchno || $('vchno').value;
            $('chequeno').value = '';
            $('chqdate').value = '';
            $('partyCode').value = '';
            $('partyName').value = '';
            $('amount').value = '';
            $('expense').value = '0.00';
            $('scharge').value = '0.00';
            $('netAmount').value = '0.00';
            $('typeText').value = '';
            $('bounce').checked = false;
            $('chequeno').focus();
        }catch(e){
            setStatus(e.message, false);
        }
    }

    $('btnHelp').addEventListener('click', openHelp);
    $('helpClose').addEventListener('click', closeHelp);
    $('help').addEventListener('click', e => { if(e.target === $('help')) closeHelp(); });
    $('helpSearch').addEventListener('input', () => loadHelp($('helpSearch').value.trim()));
    $('helpRows').addEventListener('click', (e) => {
        const tr = e.target.closest('tr'); if(!tr) return;
        $('chequeno').value = tr.dataset.chq || '';
        closeHelp();
        lookupCheque();
    });

    $('chequeno').addEventListener('change', lookupCheque);
    $('amount').addEventListener('input', recalcNet);
    $('expense').addEventListener('input', recalcNet);
    $('scharge').addEventListener('input', recalcNet);
    $('btnSave').addEventListener('click', save);
    $('btnExit').addEventListener('click', () => {
        if(window.parent && window.parent !== window) window.parent.postMessage({ type:'goldapp:close-module-frame' }, '*');
        else window.close();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'F1' && document.activeElement === $('chequeno')) { e.preventDefault(); openHelp(); }
        if (e.key === 'Enter') {
            const el = document.activeElement;
            if (el === $('tdate')) $('chequeno').focus();
            else if (el === $('chequeno')) $('chqdate').focus();
            else if (el === $('chqdate')) $('bank').focus();
            else if (el === $('amount')) $('expense').focus();
            else if (el === $('expense')) $('scharge').focus();
            else if (el === $('scharge')) $('btnSave').focus();
            else if (el === $('btnSave')) save();
        }
    });

    initPage().catch(e => setStatus(e.message, false));
})();
</script>
</body>
</html>
