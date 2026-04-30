/**
 * Sales Window JavaScript
 * Converted from PowerBuilder w_sales event handlers
 * Handles: UI interaction, AJAX calls, item grid, calculations
 */

const API_URL = 'ajax/sales_api.php';

// ========================
// STATE (replaces PB instance variables)
// ========================
let state = {
    items: [],
    exchangeItems: [],
    returnItems: [],
    config: {},
    rates: {},
    isEdit: false,
    slno: 0,
    selectedRow: -1,
};

// ========================
// INITIALIZATION (replaces open event)
// ========================
document.addEventListener('DOMContentLoaded', async () => {
    await loadDropdowns();
    await initNewSale();
    setupEventListeners();
    setupKeyboardShortcuts();
});

async function initNewSale() {
    try {
        const data = await apiCall('init');
        state.items = [];
        state.exchangeItems = [];
        state.returnItems = [];
        state.isEdit = false;
        state.slno = 0;
        state.config = data.config || {};
        state.rates = data.rates || {};

        document.getElementById('billno').value = data.billno || '';
        document.getElementById('billdate').value = data.billdate || new Date().toLocaleDateString('en-GB');
        document.getElementById('eb').value = data.eb || 'E';

        updateRatesDisplay();
        renderItemsGrid();
        clearCustomer();
        clearPayment();
        clearExchange();
        updateTotals({});
        updateStatusBar('New sale initialized');
    } catch (e) {
        showError('Failed to initialize: ' + e.message);
    }
}

// ========================
// EVENT LISTENERS (replaces PB events)
// ========================
function setupEventListeners() {
    // Barcode scan (replaces ue_barcode event)
    document.getElementById('bcode').addEventListener('keydown', async (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            await scanBarcode();
        }
    });

    // Customer search
    document.getElementById('custname').addEventListener('input', debounce(searchCustomer, 300));
    document.getElementById('custcode').addEventListener('change', loadCustomerByCode);

    // E/B toggle (replaces rb_1/rb_2 clicked events)
    document.getElementById('eb').addEventListener('change', function() {
        const badge = document.getElementById('eb-badge');
        if (this.value === 'B') {
            badge.className = 'badge badge-bill';
            badge.textContent = 'BILL';
        } else {
            badge.className = 'badge badge-est';
            badge.textContent = 'ESTIMATE';
        }
    });

    // Discount percentage (replaces sle_discperc modified)
    document.getElementById('discperc').addEventListener('change', function() {
        const total = parseFloat(document.getElementById('totalamt').value) || 0;
        document.getElementById('discount').value = (total * parseFloat(this.value) / 100).toFixed(2);
        recalcTotals();
    });
    document.getElementById('discount').addEventListener('change', recalcTotals);

    // Exchange fields (replaces exchange calc events)
    ['exchgoldwt', 'exchgoldtouch', 'exchgoldrate'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', calcExchangeGold);
    });
    ['exchsilverwt', 'exchsilvertouch', 'exchsilverrate'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', calcExchangeSilver);
    });

    // Payment fields
    ['cashamt', 'bankamt', 'cardamt', 'chequeamt'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', recalcTotals);
    });

    // Add/Less
    document.getElementById('addless')?.addEventListener('change', recalcTotals);
    document.getElementById('advance')?.addEventListener('change', recalcTotals);
}

function setupKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey) {
            switch (e.key) {
                case 's': case 'S': e.preventDefault(); saveSale(); break;
                case 'n': case 'N': e.preventDefault(); initNewSale(); break;
                case 'p': case 'P': e.preventDefault(); printBill(); break;
                case 'd': case 'D': e.preventDefault(); document.getElementById('bcode').focus(); break;
                case 'f': case 'F': e.preventDefault(); showSearchBillModal(); break;
            }
        }
        if (e.key === 'F2') { e.preventDefault(); addBlankRow(); }
        if (e.key === 'Delete' && e.ctrlKey) { e.preventDefault(); deleteSelectedRow(); }
    });
}

// ========================
// BARCODE SCANNING (replaces bcodechk)
// ========================
async function scanBarcode() {
    const bcodeInput = document.getElementById('bcode');
    const bcode = parseInt(bcodeInput.value);
    if (!bcode || bcode <= 0) return;

    try {
        const item = await apiCall('scan_barcode', { bcode });
        if (item.error) {
            showError(item.error);
            return;
        }

        // Check for duplicate barcode
        if (state.items.some(i => i.bcode == bcode)) {
            showError('Barcode already added to this bill');
            return;
        }

        state.items.push(item);
        renderItemsGrid();
        recalcTotals();
        bcodeInput.value = '';
        bcodeInput.focus();
        updateStatusBar('Barcode ' + bcode + ' scanned: ' + (item.iname || item.icode));
    } catch (e) {
        showError('Barcode error: ' + e.message);
    }
}

// ========================
// ITEMS GRID (replaces dw_2 DataWindow)
// ========================
function renderItemsGrid() {
    const tbody = document.getElementById('items-body');
    const tfoot = document.getElementById('items-foot');
    tbody.innerHTML = '';

    let totQty = 0, totGross = 0, totNet = 0, totAmt = 0;

    state.items.forEach((item, idx) => {
        const tr = document.createElement('tr');
        tr.className = idx === state.selectedRow ? 'selected' : '';
        tr.onclick = () => { state.selectedRow = idx; renderItemsGrid(); };

        tr.innerHTML = `
            <td class="center">${idx + 1}</td>
            <td><input class="text-input" value="${esc(item.iname || item.icode || '')}" onchange="updateItem(${idx},'iname',this.value)" style="width:120px"></td>
            <td class="center">${item.bcode || ''}</td>
            <td><input value="${item.qty || 1}" type="number" min="1" onchange="updateItemNum(${idx},'qty',this.value)"></td>
            <td><input value="${fmt3(item.grosswgt)}" onchange="updateItemNum(${idx},'grosswgt',this.value)"></td>
            <td><input value="${fmt3(item.stonewgt)}" onchange="updateItemNum(${idx},'stonewgt',this.value)"></td>
            <td class="num"><b>${fmt3(item.netwgt)}</b></td>
            <td><input value="${fmt2(item.wastage)}" onchange="updateItemNum(${idx},'wastage',this.value)"></td>
            <td><input value="${fmt2(item.rate)}" onchange="updateItemNum(${idx},'rate',this.value)"></td>
            <td><input value="${fmt2(item.mc)}" onchange="updateItemNum(${idx},'mc',this.value)"></td>
            <td><input value="${fmt2(item.stprice)}" onchange="updateItemNum(${idx},'stprice',this.value)"></td>
            <td><input value="${fmt2(item.taxperc)}" onchange="updateItemNum(${idx},'taxperc',this.value)"></td>
            <td class="num"><b>${fmt2(item.amount)}</b></td>
            <td class="center"><button class="btn btn-small btn-delete" onclick="removeItem(${idx})">X</button></td>
        `;
        tbody.appendChild(tr);

        totQty += (item.qty || 1);
        totGross += (item.grosswgt || 0);
        totNet += (item.netwgt || 0);
        totAmt += (item.amount || 0);
    });

    tfoot.innerHTML = `
        <tr>
            <td colspan="3" style="text-align:right"><b>Total (${state.items.length} items):</b></td>
            <td class="num"><b>${totQty}</b></td>
            <td class="num"><b>${fmt3(totGross)}</b></td>
            <td></td>
            <td class="num"><b>${fmt3(totNet)}</b></td>
            <td colspan="5"></td>
            <td class="num"><b>${fmt2(totAmt)}</b></td>
            <td></td>
        </tr>
    `;
}

function updateItem(idx, field, value) {
    state.items[idx][field] = value;
}

function updateItemNum(idx, field, value) {
    state.items[idx][field] = parseFloat(value) || 0;
    recalcItem(idx);
}

async function recalcItem(idx) {
    try {
        const item = state.items[idx];
        const calc = await apiCall('calc_item', {
            netwgt: item.netwgt || 0,
            grosswgt: item.grosswgt || 0,
            stonewgt: item.stonewgt || 0,
            wastage: item.wastage || 0,
            mc: item.mc || 0,
            mcrate: item.mcrate || 0,
            rate: item.rate || 0,
            qty: item.qty || 1,
            taxperc: item.taxperc || 0,
            vap: item.vap || 0,
            stprice: item.stprice || 0,
            dmdamt: item.dmdamt || 0,
            qtype: item.qtype || 'G',
        });
        state.items[idx] = { ...item, ...calc };
        renderItemsGrid();
        recalcTotals();
    } catch (e) {
        showError('Calc error: ' + e.message);
    }
}

function addBlankRow() {
    state.items.push({
        icode: '', iname: '', bcode: 0, qty: 1, grosswgt: 0, stonewgt: 0,
        netwgt: 0, wastage: 0, rate: state.rates?.grate || 0, mc: 0, mcrate: 0,
        stprice: 0, vap: 0, taxperc: state.config?.defTaxPerc || 3, amount: 0,
        qtype: 'G', dmdamt: 0,
    });
    state.selectedRow = state.items.length - 1;
    renderItemsGrid();
}

function removeItem(idx) {
    if (!confirm('Remove this item?')) return;
    state.items.splice(idx, 1);
    state.selectedRow = -1;
    renderItemsGrid();
    recalcTotals();
}

function deleteSelectedRow() {
    if (state.selectedRow >= 0) removeItem(state.selectedRow);
}

// ========================
// TOTALS CALCULATION (replaces balcalc)
// ========================
async function recalcTotals() {
    try {
        const data = {
            items: state.items,
            discount: parseFloat(document.getElementById('discount').value) || 0,
            discperc: parseFloat(document.getElementById('discperc').value) || 0,
            addless: parseFloat(document.getElementById('addless').value) || 0,
            advance: parseFloat(document.getElementById('advance').value) || 0,
            exchgoldwt: parseFloat(document.getElementById('exchgoldwt')?.value) || 0,
            exchgoldamt: parseFloat(document.getElementById('exchgoldamt')?.value) || 0,
            exchsilveramt: parseFloat(document.getElementById('exchsilveramt')?.value) || 0,
            excholdamt: parseFloat(document.getElementById('excholdamt')?.value) || 0,
            sreturnamt: parseFloat(document.getElementById('sreturnamt')?.value) || 0,
            cashamt: parseFloat(document.getElementById('cashamt').value) || 0,
            bankamt: parseFloat(document.getElementById('bankamt').value) || 0,
            cardamt: parseFloat(document.getElementById('cardamt').value) || 0,
            chequeamt: parseFloat(document.getElementById('chequeamt').value) || 0,
            isInterstate: document.getElementById('interstate')?.checked || false,
            custcode: document.getElementById('custcode').value || '',
        };

        const totals = await apiCallJSON('calc_totals', data);
        updateTotals(totals);
    } catch (e) {
        // Fallback: client-side calc
        clientSideCalc();
    }
}

function clientSideCalc() {
    let totalamt = state.items.reduce((s, i) => s + (i.amount || 0), 0);
    let discount = parseFloat(document.getElementById('discount').value) || 0;
    let taxamt = state.items.reduce((s, i) => s + (i.linetax || 0), 0);
    let exchTotal = (parseFloat(document.getElementById('exchgoldamt')?.value) || 0)
                  + (parseFloat(document.getElementById('exchsilveramt')?.value) || 0)
                  + (parseFloat(document.getElementById('excholdamt')?.value) || 0);
    let sreturn = parseFloat(document.getElementById('sreturnamt')?.value) || 0;
    let advance = parseFloat(document.getElementById('advance').value) || 0;
    let addless = parseFloat(document.getElementById('addless').value) || 0;

    let net = totalamt - discount + taxamt + addless;
    let grand = Math.round(net - exchTotal - sreturn - advance);
    let roundoff = grand - (net - exchTotal - sreturn - advance);

    updateTotals({
        totalamt, discount, taxamt, netamt: net,
        exchangeTotal: exchTotal, sreturnamt: sreturn, advance,
        roundoff, grandtotal: grand,
        sgst: taxamt / 2, cgst: taxamt / 2, igst: 0,
    });
}

function updateTotals(t) {
    setVal('totalamt', fmt2(t.totalamt || 0));
    setVal('taxamt', fmt2(t.taxamt || 0));
    setVal('sgst_display', fmt2(t.sgst || 0));
    setVal('cgst_display', fmt2(t.cgst || 0));
    setVal('igst_display', fmt2(t.igst || 0));
    setVal('cess_display', fmt2(t.cess || 0));
    setVal('tcs_display', fmt2(t.tcs || 0));
    setVal('netamt', fmt2(t.netamt || 0));
    setVal('roundoff', fmt2(t.roundoff || 0));
    setVal('grandtotal', fmt2(t.grandtotal || 0));

    document.getElementById('oldbalance').textContent = fmt2(t.oldbalance || 0);

    // Balance due
    let paid = (parseFloat(document.getElementById('cashamt').value) || 0)
             + (parseFloat(document.getElementById('bankamt').value) || 0)
             + (parseFloat(document.getElementById('cardamt').value) || 0)
             + (parseFloat(document.getElementById('chequeamt').value) || 0);
    let due = (t.grandtotal || 0) - paid;
    document.getElementById('balancedue').textContent = fmt2(due);
    document.getElementById('balancedue').style.color = due > 0 ? 'var(--danger)' : 'var(--success)';
}

// ========================
// EXCHANGE (replaces exchange calc events)
// ========================
async function calcExchangeGold() {
    const wt = parseFloat(document.getElementById('exchgoldwt').value) || 0;
    const touch = parseFloat(document.getElementById('exchgoldtouch').value) || 0;
    const rate = parseFloat(document.getElementById('exchgoldrate').value) || 0;
    const amt = wt * (touch / 100) * rate;
    document.getElementById('exchgoldamt').value = amt.toFixed(2);
    recalcTotals();
}

async function calcExchangeSilver() {
    const wt = parseFloat(document.getElementById('exchsilverwt').value) || 0;
    const touch = parseFloat(document.getElementById('exchsilvertouch').value) || 0;
    const rate = parseFloat(document.getElementById('exchsilverrate').value) || 0;
    const amt = wt * (touch / 100) * rate;
    document.getElementById('exchsilveramt').value = amt.toFixed(2);
    recalcTotals();
}

// ========================
// CUSTOMER (replaces customer lookup)
// ========================
async function searchCustomer(e) {
    const term = e?.target?.value || document.getElementById('custname').value;
    if (term.length < 2) { hideAutocomplete('cust-autocomplete'); return; }

    try {
        const results = await apiCall('search_customer', { term });
        showAutocomplete('cust-autocomplete', results, (cust) => {
            selectCustomer(cust);
        });
    } catch (e) { /* ignore */ }
}

async function loadCustomerByCode() {
    const code = document.getElementById('custcode').value;
    if (!code) return;
    try {
        const cust = await apiCall('get_customer', { code });
        fillCustomer(cust);
    } catch (e) { /* ignore */ }
}

function selectCustomer(cust) {
    fillCustomer(cust);
    hideAutocomplete('cust-autocomplete');
}

function fillCustomer(cust) {
    document.getElementById('custcode').value = cust.code || '';
    document.getElementById('custname').value = cust.name || '';
    document.getElementById('custaddr').value = cust.place || cust.address || '';
    document.getElementById('custphone').value = cust.phone || '';
    document.getElementById('custgstin').value = cust.gstin || '';
    document.getElementById('oldbalance').textContent = fmt2(cust.oldbalance || 0);
    recalcTotals();
}

function clearCustomer() {
    ['custcode', 'custname', 'custaddr', 'custphone', 'custgstin'].forEach(id => setVal(id, ''));
    document.getElementById('oldbalance').textContent = '0.00';
}

// ========================
// SAVE (replaces saveproc)
// ========================
async function saveSale() {
    if (state.items.length === 0) {
        showError('No items to save');
        return;
    }

    if (!confirm('Save this sale?')) return;

    const data = {
        billno: document.getElementById('billno').value,
        billdate: document.getElementById('billdate').value,
        custcode: document.getElementById('custcode').value,
        custname: document.getElementById('custname').value,
        custaddr: document.getElementById('custaddr').value,
        custphone: document.getElementById('custphone').value,
        custgstin: document.getElementById('custgstin').value,
        statecd: document.getElementById('statecd')?.value || '',
        eb: document.getElementById('eb').value,
        billtype: document.getElementById('billtype')?.value || '',
        salestype: document.getElementById('salestype')?.value || '',
        salesman: document.getElementById('salesman')?.value || '',
        counter: document.getElementById('counter')?.value || '',
        scheme: document.getElementById('scheme')?.value || '',
        remark: document.getElementById('remark')?.value || '',
        stktype: document.getElementById('stktype')?.value || '',
        discount: parseFloat(document.getElementById('discount').value) || 0,
        discperc: parseFloat(document.getElementById('discperc').value) || 0,
        addless: parseFloat(document.getElementById('addless').value) || 0,
        advance: parseFloat(document.getElementById('advance').value) || 0,
        exchgoldwt: parseFloat(document.getElementById('exchgoldwt')?.value) || 0,
        exchgoldamt: parseFloat(document.getElementById('exchgoldamt')?.value) || 0,
        exchgoldtouch: parseFloat(document.getElementById('exchgoldtouch')?.value) || 0,
        exchgoldrate: parseFloat(document.getElementById('exchgoldrate')?.value) || 0,
        exchsilverwt: parseFloat(document.getElementById('exchsilverwt')?.value) || 0,
        exchsilveramt: parseFloat(document.getElementById('exchsilveramt')?.value) || 0,
        exchsilvertouch: parseFloat(document.getElementById('exchsilvertouch')?.value) || 0,
        exchsilverrate: parseFloat(document.getElementById('exchsilverrate')?.value) || 0,
        excholdamt: parseFloat(document.getElementById('excholdamt')?.value) || 0,
        sreturnamt: parseFloat(document.getElementById('sreturnamt')?.value) || 0,
        cashamt: parseFloat(document.getElementById('cashamt').value) || 0,
        bankamt: parseFloat(document.getElementById('bankamt').value) || 0,
        cardamt: parseFloat(document.getElementById('cardamt').value) || 0,
        chequeamt: parseFloat(document.getElementById('chequeamt').value) || 0,
        isInterstate: document.getElementById('interstate')?.checked || false,
        items: state.items,
        exchangeItems: state.exchangeItems,
        returnItems: state.returnItems,
        slno: state.slno || 0,
    };

    try {
        const result = await apiCallJSON('save', data);
        if (result.success) {
            state.slno = result.slno;
            state.isEdit = true;
            updateStatusBar('Saved! Bill: ' + result.billno + ' (SL: ' + result.slno + ')');
            showSuccess('Sale saved successfully! Bill No: ' + result.billno);

            if (confirm('Print bill?')) {
                printBill();
            }
        } else {
            showError('Save failed: ' + (result.errors || []).join(', '));
        }
    } catch (e) {
        showError('Save error: ' + e.message);
    }
}

// ========================
// LOAD BILL (replaces filldetails)
// ========================
async function loadBill(slno) {
    try {
        const bill = await apiCall('load_bill', { slno });
        if (bill.error) { showError(bill.error); return; }

        state.isEdit = true;
        state.slno = slno;

        const m = bill.master;
        document.getElementById('billno').value = m.billno || '';
        document.getElementById('billdate').value = m.billdate ? new Date(m.billdate).toLocaleDateString('en-GB') : '';
        document.getElementById('eb').value = m.eb || 'E';
        document.getElementById('eb').dispatchEvent(new Event('change'));
        document.getElementById('custcode').value = m.custcode || '';
        document.getElementById('custname').value = m.custname || '';
        document.getElementById('custaddr').value = m.custaddr || '';
        document.getElementById('custphone').value = m.custphone || '';
        document.getElementById('custgstin').value = m.custgstin || '';
        document.getElementById('salesman') && (document.getElementById('salesman').value = m.salesman || '');
        document.getElementById('counter') && (document.getElementById('counter').value = m.counter || '');
        document.getElementById('discount').value = m.discount || 0;
        document.getElementById('discperc').value = m.discperc || 0;
        document.getElementById('addless').value = m.addless || 0;
        document.getElementById('advance').value = m.advance || 0;

        // Exchange
        if (document.getElementById('exchgoldwt')) {
            document.getElementById('exchgoldwt').value = m.exchgoldwt || 0;
            document.getElementById('exchgoldtouch').value = m.exchgoldtouch || 0;
            document.getElementById('exchgoldrate').value = m.exchgoldrate || 0;
            document.getElementById('exchgoldamt').value = m.exchgoldamt || 0;
            document.getElementById('exchsilverwt').value = m.exchsilverwt || 0;
            document.getElementById('exchsilvertouch').value = m.exchsilvertouch || 0;
            document.getElementById('exchsilverrate').value = m.exchsilverrate || 0;
            document.getElementById('exchsilveramt').value = m.exchsilveramt || 0;
            document.getElementById('excholdamt').value = m.excholdamt || 0;
        }

        if (document.getElementById('sreturnamt'))
            document.getElementById('sreturnamt').value = m.sreturnamt || 0;

        document.getElementById('cashamt').value = m.cashamt || 0;
        document.getElementById('bankamt').value = m.bankamt || 0;
        document.getElementById('cardamt').value = m.cardamt || 0;
        document.getElementById('chequeamt').value = m.chequeamt || 0;

        // Items
        state.items = bill.items || [];
        state.exchangeItems = bill.exchange || [];
        state.returnItems = bill.returns || [];

        renderItemsGrid();
        recalcTotals();
        closeModal('search-bill-modal');
        updateStatusBar('Bill loaded: ' + m.billno + ' (Edit mode)');
    } catch (e) {
        showError('Load error: ' + e.message);
    }
}

// ========================
// PRINT (replaces PB print events)
// ========================
function printBill() {
    const slno = state.slno;
    if (!slno) { showError('Save the bill first'); return; }
    window.open(API_URL + '?action=print_bill&slno=' + slno, '_blank', 'width=900,height=700');
}

// ========================
// SEARCH BILL MODAL
// ========================
function showSearchBillModal() {
    openModal('search-bill-modal');
    document.getElementById('search-bill-input').value = '';
    document.getElementById('search-bill-results').innerHTML = '';
    document.getElementById('search-bill-input').focus();
}

async function searchBills() {
    const term = document.getElementById('search-bill-input').value;
    if (term.length < 1) return;

    try {
        const bills = await apiCall('search_bills', { term });
        const container = document.getElementById('search-bill-results');
        if (bills.length === 0) {
            container.innerHTML = '<p style="padding:10px;color:#999">No bills found</p>';
            return;
        }
        container.innerHTML = '<table class="items-grid"><thead><tr><th>SL#</th><th>Bill No</th><th>Date</th><th>Customer</th><th>Amount</th><th>Type</th></tr></thead><tbody>' +
            bills.map(b => `<tr onclick="loadBill(${b.slno})" style="cursor:pointer">
                <td>${b.slno}</td><td>${esc(b.billno)}</td>
                <td>${b.billdate ? new Date(b.billdate).toLocaleDateString('en-GB') : ''}</td>
                <td>${esc(b.custname || 'Cash')}</td>
                <td class="num">${fmt2(b.grandtotal)}</td>
                <td class="center"><span class="badge ${b.eb === 'B' ? 'badge-bill' : 'badge-est'}">${b.eb === 'B' ? 'Bill' : 'Est'}</span></td>
            </tr>`).join('') + '</tbody></table>';
    } catch (e) { showError(e.message); }
}

// ========================
// PAYMENT
// ========================
function clearPayment() {
    ['cashamt', 'bankamt', 'cardamt', 'chequeamt'].forEach(id => setVal(id, '0'));
}

function payFullCash() {
    const grand = parseFloat(document.getElementById('grandtotal').value) || 0;
    document.getElementById('cashamt').value = grand.toFixed(2);
    document.getElementById('bankamt').value = '0';
    document.getElementById('cardamt').value = '0';
    document.getElementById('chequeamt').value = '0';
    recalcTotals();
}

function clearExchange() {
    ['exchgoldwt', 'exchgoldtouch', 'exchgoldrate', 'exchgoldamt',
     'exchsilverwt', 'exchsilvertouch', 'exchsilverrate', 'exchsilveramt',
     'excholdamt', 'sreturnamt'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '0';
    });
}

// ========================
// DROPDOWNS
// ========================
async function loadDropdowns() {
    try {
        const dd = await apiCall('get_dropdowns');
        fillSelect('salesman', dd.salesmen || [], 'code', 'name');
        fillSelect('counter', dd.counters || [], 'code', 'name');
        fillSelect('scheme', dd.schemes || [], 'code', 'name');
        fillSelect('billtype', dd.salesTypes || [], 'code', 'name');
        fillSelect('stktype', dd.stockTypes || [], 'stktype', 'stktype');
        fillSelect('statecd', dd.states || [], 'code', 'name');
    } catch (e) { /* dropdowns will remain empty */ }
}

function fillSelect(id, data, valKey, labelKey) {
    const sel = document.getElementById(id);
    if (!sel) return;
    const current = sel.value;
    sel.innerHTML = '<option value="">--</option>';
    data.forEach(d => {
        const opt = document.createElement('option');
        opt.value = d[valKey] || '';
        opt.textContent = d[labelKey] || d[valKey] || '';
        sel.appendChild(opt);
    });
    sel.value = current;
}

// ========================
// UI HELPERS
// ========================
function updateRatesDisplay() {
    const r = state.rates;
    document.getElementById('rates-display').innerHTML = `
        <span class="rate-item">Gold 22K: <span>${fmt2(r.grate || 0)}</span></span>
        <span class="rate-item">Gold 18K: <span>${fmt2(r.g18rate || 0)}</span></span>
        <span class="rate-item">Silver: <span>${fmt2(r.srate || 0)}</span></span>
    `;
}

function updateStatusBar(msg) {
    document.getElementById('status-msg').textContent = msg;
    document.getElementById('status-time').textContent = new Date().toLocaleTimeString();
    document.getElementById('status-mode').textContent = state.isEdit ? 'EDIT (SL:' + state.slno + ')' : 'NEW';
}

function showError(msg) { alert('Error: ' + msg); }
function showSuccess(msg) { alert(msg); }

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function showAutocomplete(containerId, items, onSelect) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    container.style.display = items.length ? 'block' : 'none';
    items.forEach(item => {
        const div = document.createElement('div');
        div.textContent = `${item.name || ''} (${item.code || ''}) ${item.phone || ''}`;
        div.onclick = () => onSelect(item);
        container.appendChild(div);
    });
}

function hideAutocomplete(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

// ========================
// API HELPERS
// ========================
async function apiCall(action, params = {}) {
    const url = new URL(API_URL, window.location.href);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    const resp = await fetch(url);
    if (!resp.ok) throw new Error('API error: ' + resp.status);
    return resp.json();
}

async function apiCallJSON(action, data) {
    const resp = await fetch(API_URL + '?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    if (!resp.ok) throw new Error('API error: ' + resp.status);
    return resp.json();
}

// ========================
// FORMAT HELPERS
// ========================
function fmt2(v) { return (parseFloat(v) || 0).toFixed(2); }
function fmt3(v) { return (parseFloat(v) || 0).toFixed(3); }
function setVal(id, v) { const el = document.getElementById(id); if (el) el.value = v; }
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function debounce(fn, ms) { let t; return function(...args) { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), ms); }; }
