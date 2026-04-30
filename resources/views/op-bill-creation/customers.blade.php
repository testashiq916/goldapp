<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Customer Op. Bill Creation</title>
    <style>
        :root { --bg:#0f1610; --card:#141e15; --card2:#1a261b; --bd:#2a3d2b; --tx:#e2f0e3; --mut:#6b8f6d; --ac:#4ade80; --warn:#fbbf24; --err:#f87171; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Tahoma, sans-serif; background: var(--bg); color: var(--tx); padding: 14px; }
        .app { max-width: 1300px; margin: 0 auto; border: 1px solid var(--bd); background: var(--card); }
        .title { padding: 10px 14px; background: #0a100b; border-bottom: 1px solid var(--bd); font-size: 12px; letter-spacing: .08em; color: var(--mut); text-transform: uppercase; font-weight: 700; }
        .warn { margin: 10px; padding: 8px 10px; border: 1px solid #7c5a11; background: #422006; color: #fde68a; font-size: 12px; font-weight: 700; }
        .bar, .tools, .status, .totals { display: flex; align-items: center; gap: 8px; padding: 10px; border-bottom: 1px solid var(--bd); background: var(--card2); }
        .tools .sp { flex: 1; }
        input, select, button { height: 34px; border: 1px solid var(--bd); background: #0a100b; color: var(--tx); padding: 0 10px; font-size: 13px; }
        input:focus, select:focus { outline: 1px solid var(--ac); }
        button { cursor: pointer; font-weight: 700; background: #122116; }
        button.primary { border-color: #16a34a; color: var(--ac); }
        button.warnb { border-color: #f87171; color: #fca5a5; }
        #dw_code { width: 130px; text-transform: uppercase; }
        #st_name { flex: 1; min-width: 180px; border: 1px solid var(--bd); background: #0a100b; height: 34px; display: flex; align-items: center; padding: 0 10px; color: var(--warn); }
        .dd { position: absolute; margin-top: 2px; min-width: 280px; max-height: 220px; overflow-y: auto; border: 1px solid var(--bd); background: #1f2e20; display: none; z-index: 20; }
        .dd.show { display: block; }
        .dd-row { padding: 7px 10px; border-bottom: 1px solid var(--bd); cursor: pointer; font-size: 13px; }
        .dd-row:hover { background: #263a27; }
        .tblwrap { overflow: auto; max-height: 420px; }
        table { width: 100%; border-collapse: collapse; min-width: 1080px; font-size: 12px; }
        th, td { border-right: 1px solid var(--bd); border-bottom: 1px solid rgba(255,255,255,.04); padding: 2px 3px; }
        th { position: sticky; top: 0; background: #0a100b; color: var(--mut); text-transform: uppercase; font-size: 10px; letter-spacing: .06em; padding: 8px 8px; text-align: left; }
        th:last-child, td:last-child { border-right: none; }
        tbody tr:nth-child(odd) { background: #141e15; } tbody tr:nth-child(even) { background: #172019; }
        tbody tr.sel { outline: 1px inset var(--ac); }
        .r { text-align: right; }
        .rownum { width: 34px; text-align: center; color: #3d5c3e; }
        td input, td select { width: 100%; height: 30px; border: 1px solid transparent; background: transparent; color: var(--tx); font-size: 12px; }
        td input:focus, td select:focus { border-color: var(--ac); background: rgba(74,222,128,.12); outline: none; }
        .bal { font-family: Consolas, monospace; color: #d4a853; padding-right: 8px; }
        .status { font-size: 12px; color: var(--mut); }
        .status .sp { flex: 1; }
        .totals { justify-content: flex-end; gap: 16px; font-size: 12px; }
        .totals strong { color: #d4a853; font-family: Consolas, monospace; }
    </style>
</head>
<body>
<div class="app">
    <div class="title">w_custopbills - Customer Op. Bill Creation</div>

    @if($clientsMissing)<div class="warn">`clients` table not found.</div>@endif
    @if($salesmMissing)<div class="warn">`salesm` table not found.</div>@endif
    @if($accountmMissing)<div class="warn">`accountm` table not found.</div>@endif
    @if($generaliMissing)<div class="warn">`generali` table not found.</div>@endif

    <div class="bar">
        <span style="font-size:12px;color:var(--mut);font-weight:700;text-transform:uppercase;">Customer:</span>
        <div style="position:relative;">
            <input id="dw_code" placeholder="CODE" autocomplete="off">
            <div id="custDd" class="dd"></div>
        </div>
        <div id="st_name">- select a customer -</div>
        <span id="billCounter" style="font-size:12px;color:var(--ac);font-family:Consolas,monospace;">SOPBILLNO: -</span>
    </div>

    <div class="tools">
        <button id="btnAdd" class="primary">+ Add</button>
        <button id="btnDelete" class="warnb">Delete</button>
        <div class="sp"></div>
        <button id="btnSave" class="primary">Save</button>
        <button id="btnExit">Exit</button>
    </div>

    <div class="tblwrap">
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Bill No.</th>
                <th>Date</th>
                <th class="r">G.Rate</th>
                <th class="r">Net Bill Amt</th>
                <th class="r">Received</th>
                <th class="r">Recv After</th>
                <th class="r">Balance</th>
                <th>Delv. Status</th>
                <th class="r">Sec.Wgt</th>
                <th>Note</th>
                <th>Name</th>
            </tr>
            </thead>
            <tbody id="tb"></tbody>
        </table>
    </div>

    <div class="totals">
        <span>Net Bill Amt: <strong id="tBill">0.00</strong></span>
        <span>Received: <strong id="tRamt">0.00</strong></span>
        <span>Recv After: <strong id="tAfter">0.00</strong></span>
        <span>Balance: <strong id="tBal">0.00</strong></span>
    </div>

    <div class="status">
        <span id="sMsg">Select a customer to begin.</span>
        <span class="sp"></span>
        <span id="sRows">0 rows</span>
    </div>
</div>

<script>
(() => {
    const disabled = @json($clientsMissing || $salesmMissing || $accountmMissing || $generaliMissing);
    const st = { custcode:'', custname:'', lvalue:0, gisemi:1, rows:[], selected:-1, ddIndex:-1 };
    const cols = ['billno','tdate','grate','billamt','ramt','ramtafter','dstatus','secwgt','secnote','custname'];
    const dstatus = ['Delivered','Delivered/Locker','Locker/Anamath','Anamath'];
    const tb = document.getElementById('tb');

    if (disabled) {
        document.getElementById('btnAdd').disabled = true;
        document.getElementById('btnDelete').disabled = true;
        document.getElementById('btnSave').disabled = true;
    }

    function setStatus(msg) {
        document.getElementById('sMsg').textContent = msg;
        document.getElementById('sRows').textContent = st.rows.length + ' rows';
    }
    function fmt(n){ return (Number(n||0)).toFixed(2); }
    function today(){ return new Date().toISOString().slice(0,10); }
    function bal(r){ return (Number(r.billamt||0)-Number(r.ramt||0)-Number(r.ramtafter||0)); }
    function recalcTotals() {
        let b=0,r=0,a=0,bl=0;
        st.rows.forEach(x=>{ b+=Number(x.billamt||0); r+=Number(x.ramt||0); a+=Number(x.ramtafter||0); bl+=bal(x); });
        document.getElementById('tBill').textContent = fmt(b);
        document.getElementById('tRamt').textContent = fmt(r);
        document.getElementById('tAfter').textContent = fmt(a);
        document.getElementById('tBal').textContent = fmt(bl);
    }
    async function post(url, payload) {
        const res = await fetch(url, {
            method:'POST',
            headers:{
                'Content-Type':'application/json',
                'Accept':'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With':'XMLHttpRequest'
            },
            body: JSON.stringify(payload || {})
        });
        return await res.json();
    }

    function render() {
        tb.innerHTML = '';
        if (!st.rows.length) {
            tb.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:20px;color:#6b8f6d;">No rows.</td></tr>';
            recalcTotals(); setStatus('No bills loaded.'); return;
        }
        st.rows.forEach((row, i) => {
            const tr = document.createElement('tr');
            if (i === st.selected) tr.classList.add('sel');
            const tdNum = document.createElement('td'); tdNum.className='rownum'; tdNum.textContent=String(i+1); tr.appendChild(tdNum);
            const mk = (key, opts={}) => {
                const td = document.createElement('td');
                if (opts.balance) {
                    td.className='bal r'; td.textContent = fmt(bal(row)); return td;
                }
                let el;
                if (opts.sel) {
                    el = document.createElement('select');
                    dstatus.forEach(v => { const o = document.createElement('option'); o.value=v; o.textContent=v; if((row[key]||'Delivered')===v)o.selected=true; el.appendChild(o); });
                } else {
                    el = document.createElement('input');
                    el.type = opts.date ? 'date' : (opts.num ? 'number' : 'text');
                    if (opts.num) { el.step = opts.step || '0.01'; el.min = '0'; el.classList.add('r'); }
                    el.value = opts.date ? toDateInput(row[key]) : (row[key] ?? '');
                }
                el.dataset.row = String(i);
                el.dataset.key = key;
                el.addEventListener('focus', () => { st.selected = i; renderSelection(); if (el.select) el.select(); });
                el.addEventListener('change', () => {
                    row[key] = (opts.num ? (parseFloat(el.value)||0) : el.value);
                    if (['billamt','ramt','ramtafter'].includes(key)) recalcTotals();
                    renderBalanceRow(i);
                });
                el.addEventListener('keydown', (e) => nav(e, i, key));
                td.appendChild(el);
                return td;
            };
            tr.appendChild(mk('billno'));
            tr.appendChild(mk('tdate',{date:true}));
            tr.appendChild(mk('grate',{num:true}));
            tr.appendChild(mk('billamt',{num:true}));
            tr.appendChild(mk('ramt',{num:true}));
            tr.appendChild(mk('ramtafter',{num:true}));
            tr.appendChild(mk('balance',{balance:true}));
            tr.appendChild(mk('dstatus',{sel:true}));
            tr.appendChild(mk('secwgt',{num:true,step:'0.001'}));
            tr.appendChild(mk('secnote'));
            tr.appendChild(mk('custname'));
            tr.addEventListener('click', () => { st.selected = i; renderSelection(); });
            tb.appendChild(tr);
        });
        recalcTotals();
        setStatus(st.rows.length + ' rows loaded.');
    }
    function renderSelection(){
        tb.querySelectorAll('tr').forEach((tr, i) => tr.classList.toggle('sel', i === st.selected));
    }
    function renderBalanceRow(i){
        const rowEl = tb.querySelectorAll('tr')[i];
        if (!rowEl) return;
        const balTd = rowEl.querySelector('.bal');
        if (balTd) balTd.textContent = fmt(bal(st.rows[i]));
    }
    function toDateInput(v){
        if (!v) return today();
        if (/^\d{4}-\d{2}-\d{2}/.test(v)) return v.slice(0,10);
        if (/^\d{2}\/\d{2}\/\d{4}/.test(v)) { const [d,m,y] = v.split('/'); return `${y}-${m}-${d}`; }
        return today();
    }

    function addRow() {
        if (!st.custcode) { setStatus('Select customer first.'); return; }
        st.lvalue++;
        document.getElementById('billCounter').textContent = 'SOPBILLNO: ' + st.lvalue;
        st.rows.push({
            billno:'OP/' + String(st.lvalue).padStart(5,'0'),
            tdate: today(), grate:0, billamt:0, ramt:0, ramtafter:0,
            dstatus:'Delivered', secwgt:0, secnote:'', custname: st.custname
        });
        st.selected = st.rows.length - 1;
        render();
        focusCell(st.selected, 'tdate');
    }
    function deleteRow(){
        if (st.selected < 0 || st.selected >= st.rows.length) return;
        st.rows.splice(st.selected, 1);
        st.selected = -1;
        render();
    }
    function focusCell(row, key){
        setTimeout(() => {
            const el = tb.querySelector(`[data-row="${row}"][data-key="${key}"]`);
            if (el) { el.focus(); if (el.select) el.select(); }
        }, 20);
    }
    function nav(e, row, key){
        const p = cols.indexOf(key);
        if (e.key === 'F9') { e.preventDefault(); save(); return; }
        if (e.key === 'Enter') {
            e.preventDefault();
            if (p < cols.length - 1) focusCell(row, cols[p+1]);
            else addRow();
        }
        if (e.shiftKey && e.key === 'ArrowLeft' && p > 0) { e.preventDefault(); focusCell(row, cols[p-1]); }
        if (e.shiftKey && e.key === 'ArrowRight' && p < cols.length-1) { e.preventDefault(); focusCell(row, cols[p+1]); }
    }

    async function lookup(code){
        const json = await post(@json(url('/op-bill-creation/customers/lookup-customer')), { code });
        if (!json.success) { alert(json.message || 'Invalid customer'); return; }
        st.custcode = json.code;
        st.custname = json.name || '';
        document.getElementById('dw_code').value = st.custcode;
        document.getElementById('st_name').textContent = st.custname || '-';
        await retrieveBills();
    }
    async function retrieveBills(){
        const json = await post(@json(url('/op-bill-creation/customers/retrieve-bills')), { code: st.custcode, level: st.gisemi });
        if (!json.success) { setStatus(json.message || 'Failed to load bills.'); return; }
        st.rows = json.data || [];
        st.lvalue = Number(json.lvalue || 0);
        st.selected = -1;
        document.getElementById('billCounter').textContent = 'SOPBILLNO: ' + st.lvalue;
        render();
    }

    async function searchCustomers(q){
        if (!q.trim()) { closeDd(); return; }
        const json = await post(@json(url('/op-bill-creation/customers/search-customers')), { q });
        if (!json.success || !Array.isArray(json.data) || !json.data.length) { closeDd(); return; }
        const dd = document.getElementById('custDd');
        dd.innerHTML = '';
        json.data.forEach((x, i) => {
            const div = document.createElement('div');
            div.className = 'dd-row';
            div.dataset.i = String(i);
            div.textContent = (x.code || '') + ' - ' + (x.name || '');
            div.addEventListener('click', () => { closeDd(); lookup(x.code || ''); });
            dd.appendChild(div);
        });
        st.ddIndex = -1;
        dd.classList.add('show');
    }
    function closeDd(){ const dd = document.getElementById('custDd'); dd.classList.remove('show'); dd.innerHTML = ''; st.ddIndex = -1; }

    async function save(){
        if (!st.custcode) { setStatus('No customer selected.'); return; }
        const json = await post(@json(url('/op-bill-creation/customers/save')), {
            custcode: st.custcode, custname: st.custname, lvalue: st.lvalue, gisemi: st.gisemi, rows: st.rows
        });
        if (!json.success) { setStatus(json.message || 'Save failed.'); alert(json.message || 'Save failed.'); return; }
        setStatus(json.message || 'Saved.');
        alert(json.message || 'Saved successfully.');
        initWindow();
    }

    function initWindow(){
        st.custcode=''; st.custname=''; st.rows=[]; st.selected=-1;
        document.getElementById('dw_code').value='';
        document.getElementById('st_name').textContent='- select a customer -';
        document.getElementById('billCounter').textContent='SOPBILLNO: -';
        render();
        setStatus('Select a customer to begin.');
    }

    document.getElementById('btnAdd').addEventListener('click', addRow);
    document.getElementById('btnDelete').addEventListener('click', deleteRow);
    document.getElementById('btnSave').addEventListener('click', save);
    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else {
            window.close();
        }
    });

    const codeInput = document.getElementById('dw_code');
    codeInput.addEventListener('input', () => searchCustomers(codeInput.value));
    codeInput.addEventListener('keydown', (e) => {
        const dd = document.getElementById('custDd');
        const items = dd.querySelectorAll('.dd-row');
        if (e.key === 'F1') { e.preventDefault(); searchCustomers(codeInput.value); return; }
        if (e.key === 'Enter') {
            e.preventDefault();
            if (dd.classList.contains('show') && st.ddIndex >= 0 && items[st.ddIndex]) { items[st.ddIndex].click(); }
            else { closeDd(); lookup(codeInput.value); }
            return;
        }
        if (e.key === 'ArrowDown' && items.length) {
            e.preventDefault(); st.ddIndex = Math.min(st.ddIndex + 1, items.length - 1);
            items.forEach((x, i) => x.style.background = i === st.ddIndex ? '#263a27' : '');
        }
        if (e.key === 'ArrowUp' && items.length) {
            e.preventDefault(); st.ddIndex = Math.max(st.ddIndex - 1, 0);
            items.forEach((x, i) => x.style.background = i === st.ddIndex ? '#263a27' : '');
        }
        if (e.key === 'Escape') closeDd();
    });
    codeInput.addEventListener('blur', () => setTimeout(closeDd, 180));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') { e.preventDefault(); save(); }
        if (e.key === 'Escape') { e.preventDefault(); document.getElementById('btnExit').click(); }
    });

    const pre = new URLSearchParams(window.location.search).get('custcode');
    if (pre) { codeInput.value = pre.toUpperCase(); lookup(pre); }
    else initWindow();
})();
</script>
</body>
</html>

