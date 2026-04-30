<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>Barcode Single Entry</title>
    <style>
    :root {
        --bg: #eef1f5;
        --surface: #ffffff;
        --field-bg: #ffffff;
        --field-ro: #f3f4f6;
        --border: #d1d5db;
        --border-focus: #3b82f6;
        --pri: #1e40af;
        --pri2: #2563eb;
        --pri-light: #dbeafe;
        --pri-bg: #1e3a5f;
        --ok: #059669;
        --ok-bg: #ecfdf5;
        --ok-border: #6ee7b7;
        --err: #dc2626;
        --err-bg: #fef2f2;
        --err-border: #fca5a5;
        --tx: #111827;
        --tx2: #374151;
        --tx3: #6b7280;
        --radius: 5px;
        --shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
        --shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; }
    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background: var(--bg);
        color: var(--tx);
        font-size: 13px;
        min-height: 100vh;
        padding: 10px;
        overflow: auto;
    }

    /* ── Card ── */
    .card {
        width: min(1120px, calc(100vw - 28px)); margin: 0 auto; background: var(--surface);
        border: 1px solid var(--border); border-radius: var(--radius);
        box-shadow: var(--shadow); overflow: hidden;
        min-height: calc(100vh - 20px);
        display: flex;
        flex-direction: column;
    }

    /* ── Header ── */
    .header {
        display: flex; justify-content: space-between; align-items: center;
        padding: 10px 16px; background: var(--pri-bg); color: #fff;
        flex-shrink: 0;
    }
    .header-title { font-size: 16px; font-weight: 700; letter-spacing: 0.2px; }
    .mode-badge {
        display: inline-block; margin-left: 14px; padding: 2px 12px;
        font-size: 11px; font-weight: 700; border-radius: 3px;
        background: rgba(255,255,255,0.12); color: #93c5fd;
        border: 1px solid rgba(255,255,255,0.18); letter-spacing: 0.3px;
    }
    .header-links { display: flex; gap: 12px; }
    .header-links a {
        color: rgba(255,255,255,0.8); text-decoration: none; font-size: 11px;
        display: inline-flex; align-items: center; gap: 4px; transition: color .15s;
    }
    .header-links a:hover { color: #fff; }
    .header-links a i { font-size: 11px; }
    .header i.fa-barcode { display: none; }

    /* ── Message ── */
    .msg {
        margin: 8px 14px 0; padding: 7px 12px; font-size: 11px;
        display: none; border-radius: 4px; border: 1px solid;
        flex-shrink: 0;
    }
    .msg-ok { background: var(--ok-bg); border-color: var(--ok-border); color: var(--ok); display: block; }
    .msg-err { background: var(--err-bg); border-color: var(--err-border); color: var(--err); display: block; }

    /* ── Form body ── */
    .form-body {
        padding: 14px 16px 10px;
        flex: 1;
        overflow: auto;
    }

    /* ── Section labels ── */
    .section-label {
        font-size: 12px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: var(--pri);
        padding: 6px 0 4px; border-bottom: 2px solid var(--pri-light);
        margin: 10px 0 6px; grid-column: 1 / -1;
    }
    .section-label:first-child { margin-top: 0; }

    /* ── 6-col grid ── */
    .fg {
        display: grid;
        grid-template-columns: 92px minmax(150px, 1fr) 92px minmax(150px, 1fr) 92px minmax(150px, 1fr);
        gap: 7px 10px; align-items: center;
    }
    .fg label {
        font-size: 12px; font-weight: 600; color: var(--tx2);
        text-align: right; padding-right: 2px; white-space: nowrap; line-height: 1;
    }
    .fg input, .fg select {
        width: 100%; height: 31px; border: 1px solid var(--border);
        border-radius: 4px; padding: 0 8px; font-size: 12px;
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: var(--field-bg); color: var(--tx); transition: border-color .15s, box-shadow .15s;
    }
    .fg input:focus, .fg select:focus {
        outline: none; border-color: var(--border-focus);
        box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
    }
    .fg input[readonly] {
        background: var(--field-ro); color: var(--tx3); border-color: #e5e7eb;
    }
    .fg input[type='number'] { text-align: right; }
    .fg select { cursor: pointer; }
    .col-span-3 { grid-column: span 3; }
    .col-span-5 { grid-column: span 5; }

    /* ── Options panel ── */
    .opt-panel {
        grid-column: 1 / -1;
        display: grid; grid-template-columns: repeat(3, auto);
        gap: 8px 20px; padding: 10px 12px;
        background: #f8fafc; border: 1px solid #e2e8f0;
        border-radius: 4px; margin-bottom: 4px;
    }
    .opt-panel label {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 12px; color: var(--tx2); font-weight: 600;
        white-space: nowrap; cursor: pointer;
    }
    .opt-panel input[type='checkbox'] {
        width: 16px; height: 16px; accent-color: var(--pri2);
        border-radius: 3px; cursor: pointer;
    }

    /* ── With-def wrapper ── */
    .with-def {
        display: grid; grid-template-columns: 1fr 30px;
        align-items: center; gap: 3px; min-width: 0;
    }
    .with-def input, .with-def select { min-width: 0; width: 100%; }
    .def-btn {
        height: 31px; width: 30px; padding: 0;
        border: 1px solid var(--border); border-radius: 4px;
        background: #f9fafb; font-size: 10px; font-weight: 700;
        color: var(--tx3); cursor: pointer; transition: all .15s;
    }
    .def-btn:hover { background: var(--pri-light); border-color: var(--pri2); color: var(--pri); }

    /* ── Checkbox row ── */
    .checkbox-row {
        grid-column: 1 / -1; display: flex; gap: 24px; flex-wrap: wrap;
        padding: 10px 0 6px; border-top: 1px solid #e5e7eb; margin-top: 6px;
    }
    .checkbox-row label {
        display: flex; align-items: center; gap: 6px;
        font-size: 12px; color: var(--tx2); font-weight: 600; cursor: pointer;
    }
    .checkbox-row input[type='checkbox'] {
        width: 16px; height: 16px; accent-color: var(--pri2); cursor: pointer;
    }

    /* ── Diamond section ── */
    .dmd-section { margin: 6px 0 4px; padding: 0; }
    .dmd-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .dmd-table th {
        background: var(--pri-bg); padding: 6px 6px; text-align: left;
        font-weight: 600; color: #fff; font-size: 10.5px;
        border-bottom: 2px solid var(--pri);
    }
    .dmd-table th:first-child { border-radius: 3px 0 0 0; }
    .dmd-table th:last-child { border-radius: 0 3px 0 0; }
    .dmd-table td {
        padding: 0; border-bottom: 1px solid #e5e7eb;
        background: var(--field-bg);
    }
    .dmd-table input {
        width: 100%; height: 32px; border: 0; padding: 0 8px;
        font-size: 12px; font-family: 'Segoe UI', system-ui, sans-serif;
        background: transparent;
    }
    .dmd-table input:focus { background: #eff6ff; outline: none; box-shadow: inset 0 0 0 1.5px var(--border-focus); }
    .dmd-table input[readonly] { color: var(--tx3); background: var(--field-ro); }
    .dmd-del {
        border: none; background: none; color: #9ca3af; cursor: pointer;
        font-size: 13px; padding: 4px; transition: color .15s;
    }
    .dmd-del:hover { color: var(--err); }
    .btn-add-row {
        margin-top: 8px; padding: 7px 16px; font-size: 12px;
        border: 1px solid var(--border); border-radius: 3px;
        background: #f9fafb; color: var(--tx2); cursor: pointer;
        transition: all .15s; font-weight: 500;
    }
    .btn-add-row:hover { background: var(--pri-light); border-color: var(--pri2); color: var(--pri); }

    /* ── Button bar ── */
    .btnbar {
        display: flex; flex-wrap: wrap; gap: 8px;
        padding: 12px 16px; background: #f8fafc;
        border-top: 1px solid #e5e7eb;
        flex-shrink: 0;
    }
    .btn {
        padding: 0 13px; height: 32px; border: 1px solid var(--border);
        border-radius: 4px; font-size: 12px; font-weight: 700;
        cursor: pointer; background: #fff; color: var(--tx);
        display: inline-flex; align-items: center; justify-content: center;
        gap: 5px; transition: all .15s;
    }
    .btn:hover { background: #f3f4f6; border-color: #9ca3af; }
    .btn:active { background: #e5e7eb; }
    .btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .btn-save {
        background: var(--pri2); color: #fff; border-color: var(--pri);
    }
    .btn-save:hover { background: var(--pri); }
    .btn-delete { color: var(--err); border-color: #fca5a5; }
    .btn-delete:hover { background: var(--err-bg); border-color: var(--err); }
    .btn-close { margin-left: auto; }
    .btn kbd {
        font-size: 9px; padding: 1px 4px; border-radius: 2px;
        background: rgba(0,0,0,0.06); color: var(--tx3);
        font-family: 'Segoe UI', sans-serif;
    }
    .btn-save kbd { background: rgba(255,255,255,0.2); color: rgba(255,255,255,0.85); }

    /* ── Modal ── */
    .modal {
        position: fixed; inset: 0; background: rgba(0,0,0,0.45);
        display: none; align-items: center; justify-content: center;
        z-index: 9999; backdrop-filter: blur(2px);
    }
    .modal.show { display: flex; }
    .modal-card {
        width: 780px; max-width: calc(100vw - 24px);
        background: var(--surface); border-radius: 6px;
        box-shadow: var(--shadow-lg); overflow: hidden;
        border: 1px solid var(--border);
    }
    .modal-head {
        display: flex; justify-content: space-between; align-items: center;
        padding: 10px 14px; background: var(--pri-bg); color: #fff;
    }
    .modal-head strong { font-size: 13px; font-weight: 600; }
    .modal-head input {
        width: 280px; height: 28px; border: 1px solid rgba(255,255,255,0.2);
        border-radius: 3px; padding: 0 8px; font-size: 11px;
        background: rgba(255,255,255,0.12); color: #fff;
    }
    .modal-head input::placeholder { color: rgba(255,255,255,0.5); }
    .modal-head input:focus { outline: none; background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.4); }
    .modal-head .btn { background: rgba(255,255,255,0.12); color: #fff; border-color: rgba(255,255,255,0.2); }
    .modal-head .btn:hover { background: rgba(255,255,255,0.2); }
    .modal-body { max-height: 400px; overflow: auto; }
    .pick-table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
    .pick-table th {
        background: #f8fafc; padding: 8px 10px; text-align: left;
        font-weight: 600; font-size: 10.5px; color: var(--tx2);
        border-bottom: 2px solid #e5e7eb; position: sticky; top: 0;
    }
    .pick-table td { border-bottom: 1px solid #f3f4f6; padding: 7px 10px; }
    .pick-table tr:hover { background: var(--pri-light); cursor: pointer; }

    /* ── Responsive ── */
    @media(max-width: 1200px) {
        .fg { grid-template-columns: 88px minmax(150px, 1fr) 88px minmax(150px, 1fr); }
    }
    @media(max-width: 900px) {
        body { padding: 0; }
        .fg { grid-template-columns: 1fr; }
        .fg label { text-align: left; padding-right: 0; }
        .card { width: 100%; margin: 0; border-radius: 0; min-height: 100vh; }
        .header { flex-direction: column; align-items: flex-start; gap: 8px; }
        .btnbar { padding: 8px 14px; }
        .opt-panel { grid-template-columns: repeat(2, auto); }
    }
    @media(max-width: 640px) {
        .opt-panel { grid-template-columns: 1fr; }
        .checkbox-row { gap: 12px; }
    }
</style>
</head>
<body>
<div class="card">
    <!-- HEADER -->
    <div class="header">
        <div style="display:flex; align-items:center;">
            <i class="fas fa-barcode" style="margin-right:8px;"></i>
            <span class="header-title">Item Barcode Details</span>
            <span class="mode-badge" id="modeBadge">Add New Barcode</span>
        </div>
        <div class="header-links">
            <a href="{{ url('/barcode-single-entry/settings') }}"><i class="fas fa-cog"></i> Settings</a>
        </div>
    </div>

    <!-- MESSAGE -->
    <div id="msg" class="msg"></div>

    <!-- FORM -->
    <div class="form-body">
        <div class="fg">
            <!-- DOCUMENT SECTION -->
            <div class="section-label">Document</div>
            <label for="fBcode">Barcode#</label>
            <input type="text" id="fBcode" autocomplete="off">
            <label for="fTdate">Date</label>
            <input type="date" id="fTdate">
            <label for="fDocno">DocNo</label>
            <input type="text" id="fDocno">
            <label for="fSmithcode">Smith</label>
            <select id="fSmithcode">
                <option value="">--</option>
                @foreach(($lookups['smiths'] ?? []) as $s)
                <option value="{{ data_get($s, 'code', '') }}">{{ data_get($s, 'name', '') }} ({{ data_get($s, 'code', '') }})</option>
                @endforeach
            </select>
            <label for="fTotWgt">Tot.Wgt</label>
            <input type="number" step="0.001" id="fTotWgt" readonly>
            <label for="fTotNos">Tot.Nos</label>
            <input type="number" id="fTotNos" readonly>

            <div class="section-label">Options</div>
            <div class="opt-panel">
                <label><input type="checkbox" id="fStkinnos"> Stk In Nos</label>
                <label><input type="checkbox" id="fPrintThisBc"> Print this BCode</label>
                <label><input type="checkbox" id="fPrintAfter2"> Print Barcode after 2 items</label>
            </div>

            <!-- ITEM SECTION -->
            <div class="section-label">Item</div>
            <label for="fIcode">Item Code</label>
            <input type="text" id="fIcode" list="itemCodeList" autocomplete="off" style="text-transform:uppercase;">
            <datalist id="itemCodeList">
                @foreach(($lookups['items'] ?? []) as $it)
                <option value="{{ data_get($it, 'code', '') }}">{{ data_get($it, 'name', '') }}</option>
                @endforeach
            </datalist>
            <label for="fIname">Item Name</label>
            <input type="text" id="fIname" readonly class="col-span-3">
            <label for="fSubgrp">SubGroup</label>
            <select id="fSubgrp">
                <option value="">--</option>
                @foreach(($lookups['subgroups'] ?? []) as $sg)
                <option value="{{ data_get($sg, 'code', '') }}">{{ data_get($sg, 'name', '') }}</option>
                @endforeach
            </select>
            <label for="fQtype">Quality</label>
            <select id="fQtype">
                <option value="">--</option>
                @foreach(($lookups['qualityTypes'] ?? []) as $qt)
                <option value="{{ data_get($qt, 'code', '') }}">{{ data_get($qt, 'name', '') }}</option>
                @endforeach
            </select>
            <label for="fModel">Model</label>
            <select id="fModel">
                <option value="">--</option>
                @foreach(($lookups['models'] ?? []) as $m)
                <option value="{{ data_get($m, 'name', data_get($m, 'code', '')) }}">{{ data_get($m, 'name', '') }}</option>
                @endforeach
            </select>
            <label for="fSizemodel">Size</label>
            <input type="text" id="fSizemodel">

            <!-- WEIGHT SECTION -->
            <div class="section-label">Weight & Quantity</div>
            <label for="fQty">Qty</label>
            <div class="with-def">
                <input type="number" id="fQty" min="0">
                <button type="button" class="def-btn" data-def-target="fQty" data-def-key="BCDefQty">Def</button>
            </div>
            <label for="fWeight">Weight</label>
            <input type="number" step="0.001" id="fWeight" min="0">
            <label for="fStweight">Stone Wgt</label>
            <div class="with-def">
                <input type="number" step="0.001" id="fStweight" min="0">
                <button type="button" class="def-btn" data-def-target="fStweight" data-def-key="BCDefStWgt">Def</button>
            </div>
            <label for="fNetWgt">Net Wgt</label>
            <input type="number" step="0.001" id="fNetWgt" readonly>
            <label for="fStickerWgt">Sticker Wgt</label>
            <div class="with-def">
                <input type="number" step="0.001" id="fStickerWgt" min="0">
                <button type="button" class="def-btn" data-def-target="fStickerWgt" data-def-key="BCStickerWgt">Def</button>
            </div>
            <label for="fQunit">QUnit</label>
            <input type="text" id="fQunit">

            <!-- RATE & MC SECTION -->
            <div class="section-label">Rate & Making Charge</div>
            <label for="fRate">Rate</label>
            <div class="with-def">
                <input type="number" step="0.01" id="fRate" min="0">
                <button type="button" class="def-btn" data-def-target="fRate" data-def-key="BCDefRate">Def</button>
            </div>
            <label for="fWastage">Wastage%</label>
            <input type="number" step="0.01" id="fWastage" min="0">
            <label for="fMcrate">MC Rate</label>
            <div class="with-def">
                <input type="number" step="0.01" id="fMcrate" min="0">
                <button type="button" class="def-btn" data-def-target="fMcrate" data-def-key="BCDefMcPerGm">Def</button>
            </div>
            <label for="fMc">MC Amt</label>
            <div class="with-def">
                <input type="number" step="0.01" id="fMc" min="0">
                <button type="button" class="def-btn" data-def-target="fMc" data-def-key="BCDefMCA">Def</button>
            </div>
            <label for="fSmithmcrate">Smith MC</label>
            <input type="number" step="0.01" id="fSmithmcrate" min="0">
            <label></label><span></span>

            <!-- COST & TOUCH SECTION -->
            <div class="section-label">Cost & Touch</div>
            <label for="fTranstouch">Trans Touch</label>
            <div class="with-def">
                <input type="number" step="0.01" id="fTranstouch" min="0">
                <button type="button" class="def-btn" data-def-target="fTranstouch" data-def-key="BCTransTouch">Def</button>
            </div>
            <label for="fStktouch">Stk Touch</label>
            <input type="number" step="0.01" id="fStktouch" readonly>
            <label for="fCost">Cost</label>
            <div class="with-def">
                <input type="number" step="0.01" id="fCost" min="0">
                <button type="button" class="def-btn" data-def-target="fCost" data-def-key="BCDefCost">Def</button>
            </div>
            <label for="fCostmc">Cost MC</label>
            <div class="with-def">
                <input type="number" step="0.01" id="fCostmc" min="0">
                <button type="button" class="def-btn" data-def-target="fCostmc" data-def-key="BCDefCostMcPerGm">Def</button>
            </div>
            <label for="fCoststone">Cost Stone</label>
            <div class="with-def">
                <input type="number" step="0.01" id="fCoststone" min="0">
                <button type="button" class="def-btn" data-def-target="fCoststone" data-def-key="BCDefCostStone">Def</button>
            </div>
            <label for="fCostperc">Cost%</label>
            <input type="number" step="0.01" id="fCostperc" min="0">

            <!-- VALUE SECTION -->
            <div class="section-label">Value</div>
            <label for="fStprice">Stone Price</label>
            <div class="with-def">
                <input type="number" step="0.01" id="fStprice" min="0">
                <button type="button" class="def-btn" data-def-target="fStprice" data-def-key="BCDefStPrice">Def</button>
            </div>
            <label for="fVap">VAP%</label>
            <div class="with-def">
                <input type="number" step="0.01" id="fVap" min="0">
                <button type="button" class="def-btn" data-def-target="fVap" data-def-key="BCDefVAP">Def</button>
            </div>
            <label for="fMinvap">Min VAP</label>
            <div class="with-def">
                <input type="number" step="0.01" id="fMinvap" min="0">
                <button type="button" class="def-btn" data-def-target="fMinvap" data-def-key="BCDefMinVAP">Def</button>
            </div>
            <label for="fTamt">Total Amt</label>
            <input type="number" step="0.01" id="fTamt">
            <label for="fCostamt">Purch Amt</label>
            <input type="number" step="0.01" id="fCostamt" readonly>
            <label for="fGrate">G.Rate</label>
            <input type="number" step="0.01" id="fGrate" readonly>

            <!-- OTHER SECTION -->
            <div class="section-label">Other Details</div>
            <label for="fSerialno">Serial No</label>
            <input type="text" id="fSerialno">
            <label for="fHuid">HUID</label>
            <input type="text" id="fHuid">
            <label for="fCounter">Counter</label>
            <select id="fCounter">
                <option value="">--</option>
                @foreach(($lookups['counters'] ?? []) as $c)
                <option value="{{ data_get($c, 'code', '') }}">{{ data_get($c, 'name', '') }}</option>
                @endforeach
            </select>
            <label for="fSmcode">Salesman</label>
            <select id="fSmcode">
                <option value="">--</option>
                @foreach(($lookups['salesman'] ?? []) as $sm)
                <option value="{{ data_get($sm, 'code', '') }}">{{ data_get($sm, 'name', '') }}</option>
                @endforeach
            </select>
            <label for="fPart">Part/MRP</label>
            <input type="text" id="fPart">
            <label></label><span></span>

            <!-- CHECKBOXES -->
            <div class="checkbox-row">
                <label><input type="checkbox" id="fStk"> Sold</label>
                <label><input type="checkbox" id="fBcManual"> Manual BCode</label>
                <label><input type="checkbox" id="fNodisc"> No Discount</label>
            </div>
        </div>

        <!-- DIAMOND DETAILS -->
        <div class="dmd-section">
            <div class="section-label" style="margin-top:10px;">Diamond / Stone Details</div>
            <table class="dmd-table">
                <thead>
                    <tr>
                        <th style="width:35px;">#</th>
                        <th>Stone Code</th>
                        <th>Stone Type</th>
                        <th>Colour</th>
                        <th>Cut/Clrty</th>
                        <th style="width:55px;">Pcs</th>
                        <th style="width:70px;">Carats</th>
                        <th style="width:70px;">Gm</th>
                        <th style="width:90px;">Rate/Carat</th>
                        <th style="width:90px;">Purch.Value</th>
                        <th style="width:90px;">Sales.Value</th>
                        <th style="width:30px;"></th>
                    </tr>
                </thead>
                <tbody id="dmdRows"></tbody>
            </table>
            <button type="button" class="btn-add-row" id="btnAddDmdRow"><i class="fas fa-plus"></i> Add Stone Row</button>
        </div>
    </div>

    <!-- BUTTON BAR -->
    <div class="btnbar">
        <button class="btn btn-delete" id="btnDelete"><i class="fas fa-trash"></i> Delete <kbd>F4</kbd></button>
        <button class="btn btn-save" id="btnSave"><i class="fas fa-check"></i> Save <kbd>F5</kbd></button>
        <button class="btn" id="btnCancel"><i class="fas fa-times"></i> Cancel <kbd>Esc</kbd></button>
        <button class="btn" id="btnList"><i class="fas fa-list"></i> List <kbd>F7</kbd></button>
        <button class="btn btn-close" id="btnClose"><i class="fas fa-sign-out-alt"></i> Close</button>
    </div>
</div>

<div class="modal" id="bcListModal">
    <div class="modal-card">
        <div class="modal-head">
            <strong>Barcode List (Select to Edit)</strong>
            <div>
                <input type="text" id="bcListSearch" placeholder="Search barcode or item code...">
                <button class="btn" id="bcListClose" type="button">Close</button>
            </div>
        </div>
        <div class="modal-body">
            <table class="pick-table">
                <thead>
                    <tr>
                        <th style="width:110px;">Barcode</th>
                        <th style="width:110px;">Item</th>
                        <th>Name</th>
                        <th style="width:90px;">Weight</th>
                        <th style="width:100px;">Amount</th>
                        <th style="width:70px;">Stk</th>
                    </tr>
                </thead>
                <tbody id="bcListRows"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const siteUrl = @json(request()->root());
    const settings = @json($settings['Software'] ?? []);
    const thRate = parseFloat(@json($thRate ?? $todayRate ?? 0)) || 0;
    const showBcListEnabled = (settings.ShowBCListInEdit ?? 'Y') === 'Y';
    // Keep Total Amt manual-entry as requested.
    const autoSamtEnabled = false;

    let mode = 'ready'; // ready | add | edit
    let currentBcode = '';
    let dmdRows = [];
    let pendingPairPrintBcode = '';

    // --- Field IDs ---
    const readonlyFields = ['fNetWgt', 'fCostamt', 'fGrate', 'fIname', 'fTotWgt', 'fTotNos', 'fStktouch'];
    const allFieldIds = [
        'fBcode','fTdate','fDocno','fSmithcode','fTotWgt','fTotNos',
        'fIcode','fIname','fSubgrp','fQtype','fModel','fSizemodel',
        'fQty','fWeight','fStweight','fNetWgt','fStickerWgt','fQunit',
        'fRate','fWastage','fMcrate','fMc','fSmithmcrate',
        'fTranstouch','fStktouch','fCost','fCostmc','fCoststone','fCostperc',
        'fStprice','fVap','fMinvap','fTamt','fCostamt','fGrate',
        'fSerialno','fHuid','fCounter','fSmcode','fPart',
        'fStk','fNodisc','fStkinnos'
    ];

    // --- Helpers ---
    function showMsg(text, ok) {
        const el = document.getElementById('msg');
        el.className = 'msg ' + (ok ? 'msg-ok' : 'msg-err');
        el.textContent = text;
        el.style.display = 'block';
        if (ok) setTimeout(() => { el.style.display = 'none'; }, 4000);
    }

    async function api(url, method, payload) {
        const opts = { method, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } };
        if (method === 'POST') {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(payload || {});
        }
        const res = await fetch(url, opts);
        const text = await res.text();
        let data = {};
        try {
            data = text ? JSON.parse(text) : {};
        } catch (_) {
            throw new Error('Server returned invalid response (' + res.status + ').');
        }
        if (!res.ok && !data?.message) {
            data.message = 'Request failed (' + res.status + ').';
        }
        return data;
    }

    function val(id) { const e = document.getElementById(id); return e ? (e.type === 'checkbox' ? e.checked : e.value) : ''; }
    function setVal(id, v) { const e = document.getElementById(id); if (!e) return; if (e.type === 'checkbox') e.checked = !!v; else e.value = v ?? ''; }
    function num(id) { return parseFloat(document.getElementById(id)?.value) || 0; }
    async function saveSetting(key, value) {
        try {
            const d = await api(siteUrl + '/api/barcode-entry/action?action=save_setting', 'POST', { key, value });
            return !!d.success;
        } catch (_) {
            return false;
        }
    }

    function bindOptionCheckbox(id, key, yesNo = true) {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', async () => {
            const value = yesNo ? (el.checked ? 'Y' : 'N') : (el.checked ? '2' : '1');
            settings[key] = value;
            const ok = await saveSetting(key, value);
            if (!ok) showMsg('Failed to save option: ' + key, false);
        });
    }

    // --- Mode ---
    function setMode(m) {
        mode = m;
        const badge = document.getElementById('modeBadge');
        badge.textContent = { ready: 'Ready', add: 'Add New Barcode', edit: 'Edit Barcode' }[m] || m;
        badge.style.background = 'transparent';

        const editable = (m === 'add' || m === 'edit');
        allFieldIds.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (readonlyFields.includes(id)) { el.readOnly = true; return; }
            if (el.tagName === 'SELECT') el.disabled = !editable;
            else if (el.type === 'checkbox') el.disabled = !editable;
            else el.readOnly = !editable;
        });
        // Barcode# always typeable for lookup
        document.getElementById('fBcode').readOnly = false;

        document.getElementById('btnSave').disabled = !editable;
        document.getElementById('btnDelete').disabled = false;
        document.getElementById('btnAddDmdRow').disabled = !editable;
    }

    // --- Reset ---
    function resetForm() {
        allFieldIds.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.type === 'checkbox') el.checked = false;
            else if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else el.value = '';
        });
        // Apply defaults
        setVal('fQty', settings.BCDefQty || '1');
        setVal('fRate', settings.BCDefRate || '');
        setVal('fCost', settings.BCDefCost || '');
        setVal('fVap', settings.BCDefVAP || '');
        setVal('fMc', settings.BCDefMCA || '');
        setVal('fStweight', settings.BCDefStWgt || '');
        setVal('fStprice', settings.BCDefStPrice || '');
        setVal('fMinvap', settings.BCDefMinVAP || '');
        setVal('fMcrate', settings.BCDefMcPerGm || '');
        setVal('fCostmc', settings.BCDefCostMcPerGm || '');
        setVal('fTdate', new Date().toISOString().slice(0, 10));
        setVal('fGrate', thRate || '');
        setVal('fBcManual', (settings.BCManualBCode ?? 'N') === 'Y');
        setVal('fPrintThisBc', (settings.PrintThisBCode ?? 'N') === 'Y');
        setVal('fPrintAfter2', (settings.BCodePrintAfter2Entry ?? 'Y') === 'Y');
        applyPrintMethodByStickerMode();
        dmdRows = [];
        renderDmd();
        currentBcode = '';
    }

    function applyPrintMethodByStickerMode() {
        const printThis = (settings.PrintThisBCode ?? 'N') === 'Y';
        const printAfter2 = (settings.BCodePrintAfter2Entry ?? 'N') === 'Y';
        // Allow PrintThisBCode in any sticker mode (single/double).
        if (printThis && printAfter2) {
            setVal('fPrintThisBc', true);
            setVal('fPrintAfter2', false);
        } else {
            setVal('fPrintThisBc', printThis);
            setVal('fPrintAfter2', printAfter2);
        }
    }

    // --- Auto Calculations ---
    function recalcAll() {
        // Net weight
        const wt = num('fWeight'), st = num('fStweight');
        setVal('fNetWgt', (wt - st).toFixed(3));
        const netWgt = wt - st;

        if (!autoSamtEnabled) return;

        // MC amount (if BCFreshMC = Y and mcrate entered)
        const mcRate = num('fMcrate');
        if (settings.BCFreshMC === 'Y' && mcRate > 0) {
            setVal('fMc', (mcRate * netWgt).toFixed(2));
        }

        // Sales amount: (netWgt * rate * (1 + wastage/100)) + mc + stonePrice, then apply VAP%
        const rate = num('fRate'), wastage = num('fWastage');
        const mc = num('fMc'), stPrice = num('fStprice'), vap = num('fVap');
        const metalValue = netWgt * rate * (1 + wastage / 100);
        const baseAmt = metalValue + mc + stPrice;
        const totalAmt = baseAmt * (1 + vap / 100);
        setVal('fTamt', (settings.RoundOffAllAmt === 'Y' ? Math.round(totalAmt) : totalAmt.toFixed(2)));

        // Cost amount: (netWgt * stktouch/100 * thRate) + costmc + coststone
        const stkTouch = num('fStktouch'), costMc = num('fCostmc'), costStone = num('fCoststone');
        const costAmt = (netWgt * stkTouch / 100 * thRate) + costMc + costStone;
        setVal('fCostamt', costAmt.toFixed(2));
    }

    function recalcStkTouch() {
        const tt = num('fTranstouch');
        if (tt > 0) setVal('fStktouch', tt.toFixed(2));
    }

    // --- Barcode List Popup ---
    async function loadBarcodeList(search = '') {
        const rowsEl = document.getElementById('bcListRows');
        rowsEl.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';
        try {
            const d = await api(siteUrl + '/api/barcode-entry/search?q=' + encodeURIComponent(search), 'GET');
            const rows = d.success ? (d.data || []) : [];
            if (!rows.length) {
                rowsEl.innerHTML = '<tr><td colspan="6">No records found.</td></tr>';
                return;
            }
            rowsEl.innerHTML = rows.map(r => `
                <tr data-bcode="${r.bcode}">
                    <td>${r.bcode ?? ''}</td>
                    <td>${r.icode ?? ''}</td>
                    <td>${r.name ?? ''}</td>
                    <td style="text-align:right;">${r.weight ?? ''}</td>
                    <td style="text-align:right;">${r.tamt ?? ''}</td>
                    <td>${r.stk ?? ''}</td>
                </tr>
            `).join('');
        } catch (e) {
            rowsEl.innerHTML = '<tr><td colspan="6">Failed to load list.</td></tr>';
        }
    }

    function openBarcodeList() {
        document.getElementById('bcListModal').classList.add('show');
        document.getElementById('bcListSearch').value = '';
        loadBarcodeList('');
        document.getElementById('bcListSearch').focus();
    }

    function closeBarcodeList() {
        document.getElementById('bcListModal').classList.remove('show');
    }

    document.getElementById('btnList').addEventListener('click', openBarcodeList);
    document.getElementById('bcListClose').addEventListener('click', closeBarcodeList);
    document.getElementById('bcListModal').addEventListener('click', (e) => {
        if (e.target.id === 'bcListModal') closeBarcodeList();
    });
    document.getElementById('bcListSearch').addEventListener('input', function() {
        loadBarcodeList(this.value.trim());
    });
    document.getElementById('bcListRows').addEventListener('click', (e) => {
        const tr = e.target.closest('tr[data-bcode]');
        if (!tr) return;
        if (mode === 'add') setMode('ready');
        setVal('fBcode', tr.dataset.bcode || '');
        closeBarcodeList();
        loadBarcodeByNumber();
    });

    // Bind auto-calc events
    ['fWeight','fStweight','fRate','fWastage','fMcrate','fMc','fStprice','fVap','fTranstouch','fCostmc','fCoststone'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', () => { recalcStkTouch(); recalcAll(); });
    });

    bindOptionCheckbox('fBcManual', 'BCManualBCode');
    bindOptionCheckbox('fPrintThisBc', 'PrintThisBCode');
    bindOptionCheckbox('fPrintAfter2', 'BCodePrintAfter2Entry');
    const fPrintThisBc = document.getElementById('fPrintThisBc');
    const fPrintAfter2 = document.getElementById('fPrintAfter2');
    if (fPrintThisBc && fPrintAfter2) {
        fPrintThisBc.addEventListener('change', async () => {
            if (fPrintThisBc.checked) {
                pendingPairPrintBcode = '';
                fPrintAfter2.checked = false;
                settings.BCodePrintAfter2Entry = 'N';
                await saveSetting('BCodePrintAfter2Entry', 'N');
            }
        });
        fPrintAfter2.addEventListener('change', async () => {
            if (fPrintAfter2.checked) {
                fPrintThisBc.checked = false;
                settings.PrintThisBCode = 'N';
                await saveSetting('PrintThisBCode', 'N');
            }
        });
    }

    document.querySelectorAll('.def-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const target = btn.dataset.defTarget || '';
            const key = btn.dataset.defKey || '';
            if (!target || !key) return;
            const raw = val(target);
            const sval = typeof raw === 'boolean' ? (raw ? 'Y' : 'N') : String(raw ?? '');
            const ok = await saveSetting(key, sval);
            showMsg(ok ? (`Default saved: ${key} = ${sval}`) : (`Failed to save default: ${key}`), ok);
        });
    });

    // --- Item Code lookup ---
    document.getElementById('fIcode').addEventListener('change', async function() {
        const icode = this.value.trim().toUpperCase();
        this.value = icode;
        if (!icode) { setVal('fIname', ''); return; }
        try {
            const d = await api(siteUrl + '/api/barcode-entry/load-item?icode=' + encodeURIComponent(icode), 'GET');
            if (!d.success) { showMsg(d.message || 'Item not found', false); return; }
            const item = d.data;
            setVal('fIname', item.name || '');
            if (mode === 'add') {
                if (!val('fWastage')) setVal('fWastage', item.wastage || '');
                if (!val('fMcrate')) setVal('fMcrate', item.mcharge || '');
                if (!val('fVap')) setVal('fVap', item.vaperc || '');
                if (item.defquality) setVal('fQtype', item.defquality);
                if (item.stkinnos === 'Y') setVal('fStkinnos', true);
                if (item.nodisc === 'N') setVal('fNodisc', true);
                if (item.stickerwgt) setVal('fStickerWgt', item.stickerwgt);
                if (item.qualityTouch) setVal('fStktouch', item.qualityTouch);
            }
            recalcStkTouch();
            recalcAll();
        } catch(e) { showMsg('Error loading item: ' + e.message, false); }
    });

    // --- Load document totals when docno is changed manually ---
    document.getElementById('fDocno').addEventListener('change', async function() {
        const docno = this.value.trim();
        if (!docno) {
            setVal('fTotWgt', '');
            setVal('fTotNos', '');
            return;
        }
        try {
            const d = await api(siteUrl + '/api/barcode-entry/load-document?docno=' + encodeURIComponent(docno), 'GET');
            if (d.success && d.data) {
                setVal('fTotWgt', d.data.accWeight ?? '');
                setVal('fTotNos', d.data.accNos ?? '');
            }
        } catch(e) {}
    });

    // --- Barcode# lookup (on Enter or blur when not in add mode) ---
    document.getElementById('fBcode').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); loadBarcodeByNumber(); }
    });
    document.getElementById('fBcode').addEventListener('blur', function() {
        if (mode !== 'add' && this.value.trim()) loadBarcodeByNumber();
    });

    async function loadBarcodeByNumber() {
        const bcode = val('fBcode').trim();
        if (!bcode || mode === 'add') return;
        try {
            const d = await api(siteUrl + '/api/barcode-entry/get?bcode=' + encodeURIComponent(bcode), 'GET');
            if (!d.success) {
                showMsg('Barcode "' + bcode + '" not found.', false);
                return;
            }
            applyBarcodeData(d.data);
            setMode('edit');
            showMsg('Barcode ' + bcode + ' loaded for editing.', true);
        } catch(e) { showMsg(e.message, false); }
    }

    async function requestPrintBarcode(bcode) {
        const targetBcode = String(bcode || '').trim();
        const pxinc = parseInt(settings.BCPXINC ?? '0', 10) || 0;
        const pyinc = parseInt(settings.BCPYINC ?? '0', 10) || 0;
        const density = parseInt(settings.BCDensity ?? '14', 10) || 14;
        const printType = 'TemplateDesigner';
        const printMode = String(settings.BCPrintMode || 'windows_image');
        const printerName = String(settings.BCPrinterName || '');
        const printerShareName = String(settings.BCPrinterShareName || '');
        const renderAsImage = 'Y';
        const stickerMode = String(settings.BCStickerMode || 'single');
        const designerColumns = String(settings.BCDesignerColumns || '1');
        const designerTemplate = String(settings.BCDesignerTemplate || '');
        return await api(siteUrl + '/api/barcode-entry/print', 'POST', {
            bcode: targetBcode,
            barcode: targetBcode,
            pxinc: pxinc,
            pyinc: pyinc,
            density: density,
            printType: printType,
            printMode: printMode,
            printerName: printerName,
            printerShareName: printerShareName,
            renderAsImage: renderAsImage,
            stickerMode: stickerMode,
            designerColumns: designerColumns,
            designerTemplate: designerTemplate
        });
    }

    function applyBarcodeData(data) {
        const bc = data.barcode;
        currentBcode = bc.bcode;
        // Map DB columns to form fields
        const map = {
            fBcode: bc.bcode, fTdate: bc.tdate, fDocno: bc.docno, fSmithcode: bc.smithcode,
            fIcode: bc.icode, fIname: data.itemName || bc.item_name || '',
            fSubgrp: bc.subgrp, fQtype: bc.qtype, fModel: bc.model, fSizemodel: bc.sizemodel,
            fQty: bc.qty, fWeight: bc.weight, fStweight: bc.stweight, fStickerWgt: bc.weight2,
            fQunit: bc.qunit, fRate: bc.rate, fWastage: bc.wastage, fMcrate: bc.mcrate,
            fMc: bc.mc, fSmithmcrate: bc.smithmcrate, fTranstouch: bc.transtouch,
            fStktouch: bc.stktouch, fCost: bc.cost, fCostmc: bc.costmc,
            fCoststone: bc.coststone, fCostperc: bc.costperc, fStprice: bc.stprice,
            fVap: bc.vap, fMinvap: bc.minvap, fTamt: bc.tamt, fCostamt: bc.costamt,
            fGrate: bc.grate, fSerialno: bc.serialno, fHuid: bc.huid,
            fCounter: bc.counter, fSmcode: bc.smcode, fPart: bc.part
        };
        Object.entries(map).forEach(([id, v]) => setVal(id, v));
        setVal('fStk', bc.stk === 'N' || bc.stk === 'S');
        setVal('fNodisc', bc.nodisc === 'N');
        setVal('fStkinnos', bc.stkinnos === 'Y');

        // Net weight
        const wt = parseFloat(bc.weight) || 0, sw = parseFloat(bc.stweight) || 0;
        setVal('fNetWgt', (wt - sw).toFixed(3));

        // Diamond details
        dmdRows = (data.dmdDetails || []).map(r => ({
            stcode: r.stcode || '',
            sttype: r.sttype || '',
            stcolor: r.stcolor || '',
            stcut: r.stcut || '',
            pcs: parseFloat(r.pcs || 0) || 0,
            carats: parseFloat(r.carats || 0) || 0,
            wgt: parseFloat(r.wgt || 0) || 0,
            rate: parseFloat(r.rate || 0) || 0,
            amount: parseFloat((r.pamt ?? r.amount) || 0) || 0,
            samount: parseFloat(r.amount || r.samount || 0) || 0,
            itemname: r.itemname || '',
            itemtype: r.itemtype || '',
            cost: parseFloat(r.cost || 0) || 0,
            stkinnos: (r.stkinnos || '').toString().trim().toUpperCase(),
            stktype: r.stktype || '',
            iqtype: r.iqtype || ''
        }));
        renderDmd();

        // Document totals
        if (bc.docno) {
            api(siteUrl + '/api/barcode-entry/load-document?docno=' + encodeURIComponent(bc.docno), 'GET')
                .then(d => { if (d.success && d.data) { setVal('fTotWgt', d.data.accWeight ?? ''); setVal('fTotNos', d.data.accNos ?? ''); } })
                .catch(() => {});
        }
    }

    // --- Diamond Table ---
    function roundDmdAmount(v) {
        const n = parseFloat(v || 0) || 0;
        return (settings.RoundOffAllAmt === 'Y') ? Math.round(n) : Math.round(n * 100) / 100;
    }

    function recalcDmdRow(i, changedField) {
        const row = dmdRows[i];
        if (!row) return;

        const pcs = parseFloat(row.pcs || 0) || 0;
        const carats = parseFloat(row.carats || 0) || 0;
        const rate = parseFloat(row.rate || 0) || 0;
        let samount = parseFloat(row.samount || 0) || 0;

        if (changedField === 'carats') {
            row.wgt = carats / 5;
        } else if (changedField === 'samount') {
            if (carats > 0) row.rate = samount / carats;
        }

        const useNos = ((row.stkinnos || '').toUpperCase() === 'Y') || val('fStkinnos');
        samount = useNos ? (pcs * row.rate) : ((parseFloat(row.carats || 0) || 0) * row.rate);
        row.samount = roundDmdAmount(samount);
    }

    async function hydrateDmdRowFromStoneCode(i) {
        const row = dmdRows[i];
        if (!row) return;
        const scode = String(row.stcode || '').trim().toUpperCase();
        row.stcode = scode;
        if (!scode) return;

        const d = await api(siteUrl + '/api/barcode-entry/load-item?icode=' + encodeURIComponent(scode), 'GET');
        if (!d.success || !d.data) {
            throw new Error(d.message || 'Invalid Item');
        }

        const item = d.data;
        if (String(item.disabled ?? '0') === '1') {
            throw new Error('This Item is Disabled');
        }

        row.itemname = String(item.name || '');
        row.itemtype = String(item.itype || '');
        row.cost = parseFloat(item.cost || 0) || 0;
        row.stkinnos = String(item.stkinnos || '').trim().toUpperCase();
        row.iqtype = String(item.defquality || '');
        row.stktype = String(item.defstktype || '');
        row.rate = parseFloat(item.rate || row.rate || 0) || 0;
        row.amount = row.cost;
    }

    function renderDmd() {
        const tbody = document.getElementById('dmdRows');
        const editable = (mode === 'add' || mode === 'edit');
        tbody.innerHTML = '';
        dmdRows.forEach((row, i) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="text-align:center;">${i + 1}</td>
                <td><input data-i="${i}" data-f="stcode" value="${row.stcode}" style="text-transform:uppercase;" ${editable ? '' : 'readonly'}></td>
                <td><input data-i="${i}" data-f="sttype" value="${row.sttype}" ${editable ? '' : 'readonly'}></td>
                <td><input data-i="${i}" data-f="stcolor" value="${row.stcolor}" ${editable ? '' : 'readonly'}></td>
                <td><input data-i="${i}" data-f="stcut" value="${row.stcut}" ${editable ? '' : 'readonly'}></td>
                <td><input data-i="${i}" data-f="pcs" type="number" value="${row.pcs}" ${editable ? '' : 'readonly'}></td>
                <td><input data-i="${i}" data-f="carats" type="number" step="0.001" value="${row.carats}" ${editable ? '' : 'readonly'}></td>
                <td><input data-i="${i}" data-f="wgt" type="number" step="0.001" value="${row.wgt}" readonly></td>
                <td><input data-i="${i}" data-f="rate" type="number" step="0.01" value="${row.rate}" ${editable ? '' : 'readonly'}></td>
                <td><input data-i="${i}" data-f="amount" type="number" step="0.01" value="${row.amount}" ${editable ? '' : 'readonly'}></td>
                <td><input data-i="${i}" data-f="samount" type="number" step="0.01" value="${row.samount}" ${editable ? '' : 'readonly'}></td>
                <td>${editable ? '<button type="button" class="dmd-del" data-i="' + i + '"><i class="fas fa-times"></i></button>' : ''}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // Diamond table events (delegation)
    document.getElementById('dmdRows').addEventListener('input', (e) => {
        const el = e.target; const i = parseInt(el.dataset.i); const f = el.dataset.f;
        if (isNaN(i) || !f) return;
        dmdRows[i][f] = el.type === 'number' ? (parseFloat(el.value) || 0) : el.value;
    });
    document.getElementById('dmdRows').addEventListener('change', async (e) => {
        const el = e.target; const i = parseInt(el.dataset.i); const f = el.dataset.f;
        if (isNaN(i) || !f) return;

        try {
            if (f === 'stcode') {
                await hydrateDmdRowFromStoneCode(i);
                recalcDmdRow(i, 'rate');
            } else if (f === 'samount' || f === 'carats' || f === 'rate' || f === 'pcs') {
                recalcDmdRow(i, f);
            }
            renderDmd();
        } catch (err) {
            showMsg(err.message || 'Invalid Item', false);
            if (f === 'stcode') {
                dmdRows[i].stcode = '';
                renderDmd();
            }
        }
    });
    document.getElementById('dmdRows').addEventListener('click', (e) => {
        const btn = e.target.closest('.dmd-del');
        if (!btn) return;
        dmdRows.splice(parseInt(btn.dataset.i), 1);
        renderDmd();
    });
    document.getElementById('btnAddDmdRow').addEventListener('click', () => {
        if (mode !== 'add' && mode !== 'edit') return;
        dmdRows.push({
            stcode: '', sttype: '', stcolor: '', stcut: '',
            pcs: 0, carats: 0, wgt: 0, rate: 0, amount: 0, samount: 0,
            itemname: '', itemtype: '', cost: 0, stkinnos: '', stktype: '', iqtype: ''
        });
        renderDmd();
        // Focus first input of new row
        const inputs = document.getElementById('dmdRows').querySelectorAll('tr:last-child input');
        if (inputs.length > 0) inputs[0].focus();
    });
    document.getElementById('fStkinnos').addEventListener('change', () => {
        dmdRows.forEach((_, idx) => recalcDmdRow(idx, 'rate'));
        renderDmd();
    });

    // --- Button Handlers ---

    async function fetchNextBarcodeAndDoc() {
        if (!val('fBcManual')) {
            try {
                const d = await api(siteUrl + '/api/barcode-entry/next-barcode', 'GET');
                if (d.success) setVal('fBcode', d.data?.nextBarcode ?? '');
            } catch(e) {}
        }
        try {
            const d = await api(siteUrl + '/api/barcode-entry/next-docno', 'GET');
            if (d.success) setVal('fDocno', d.data?.nextDocNo ?? '');
        } catch(e) {}
    }

    // DELETE (F4)
    document.getElementById('btnDelete').addEventListener('click', async () => {
        const entered = window.prompt('Enter barcode number to delete:', String(currentBcode || val('fBcode') || ''));
        if (entered === null) return;
        const target = String(entered).trim();
        if (!target) return showMsg('Barcode number is required for delete.', false);
        if (!/^\d+$/.test(target)) return showMsg('Enter valid numeric barcode.', false);
        if (!confirm('Delete barcode "' + target + '"? This cannot be undone.')) return;
        try {
            const d = await api(siteUrl + '/api/barcode-entry/delete', 'POST', { bcode: target });
            showMsg(d.message || (d.success ? 'Deleted' : 'Error'), d.success);
            if (d.success) {
                if (String(currentBcode) === target) {
                    resetForm();
                    setMode('ready');
                } else {
                    setVal('fBcode', '');
                }
            }
        } catch(e) { showMsg('Error: ' + e.message, false); }
    });

    // SAVE (F5)
    document.getElementById('btnSave').addEventListener('click', async () => {
        if (mode !== 'add' && mode !== 'edit') return;
        const bcode = val('fBcode').trim();
        const icode = val('fIcode').trim();
        if (!bcode) return showMsg('Barcode number is required.', false);
        if (!icode) return showMsg('Item code is required.', false);
        if (num('fQty') <= 0) return showMsg('Quantity must be > 0.', false);

        // Build payload
        const payload = {
            mode: mode,
            bcode: bcode, tdate: val('fTdate'), docno: val('fDocno'), smithcode: val('fSmithcode'),
            totwgt: val('fTotWgt'), totnos: val('fTotNos'),
            icode: icode, subgrp: val('fSubgrp'), qtype: val('fQtype'), model: val('fModel'), sizemodel: val('fSizemodel'),
            qty: val('fQty'), weight: val('fWeight'), stweight: val('fStweight'), stickerwgt: val('fStickerWgt'), qunit: val('fQunit'),
            rate: val('fRate'), wastage: val('fWastage'), mcrate: val('fMcrate'), mc: val('fMc'), smithmcrate: val('fSmithmcrate'),
            transtouch: val('fTranstouch'), stktouch: val('fStktouch'), cost: val('fCost'),
            costmc: val('fCostmc'), coststone: val('fCoststone'), costperc: val('fCostperc'),
            stprice: val('fStprice'), vap: val('fVap'), minvap: val('fMinvap'),
            tamt: val('fTamt'), costamt: val('fCostamt'), grate: val('fGrate'),
            serialno: val('fSerialno'), huid: val('fHuid'), counter: val('fCounter'),
            smcode: val('fSmcode'), part: val('fPart'),
            sold: val('fStk') ? 'Y' : 'N',
            nodisc: val('fNodisc') ? 'Y' : 'N',
            stkinnos: val('fStkinnos') ? 'Y' : 'N',
            dmdDetails: dmdRows
        };

        try {
            const d = await api(siteUrl + '/api/barcode-entry/save', 'POST', payload);
            showMsg(d.message || (d.success ? 'Saved' : 'Error'), d.success);
            if (d.success) {
                currentBcode = d.data?.bcode || bcode;
                if (val('fPrintThisBc')) {
                    pendingPairPrintBcode = '';
                    try {
                        const pr = await requestPrintBarcode(currentBcode);
                        if (pr?.success) {
                            showMsg(pr.message || 'Saved + print job sent.', true);
                        } else {
                            showMsg('Saved, but print failed: ' + (pr?.message || 'Unknown error'), true);
                        }
                    } catch (e) {
                        showMsg('Saved, but print request failed: ' + e.message, true);
                    }
                } else if (val('fPrintAfter2')) {
                    if (!pendingPairPrintBcode) {
                        pendingPairPrintBcode = currentBcode;
                        showMsg('Saved. Waiting one more item for pair print.', true);
                    } else {
                        let pr2 = null;
                        try { await requestPrintBarcode(pendingPairPrintBcode); } catch (_) {}
                        try { pr2 = await requestPrintBarcode(currentBcode); } catch (_) {}
                        pendingPairPrintBcode = '';
                        if (pr2?.success) {
                            showMsg(pr2.message || 'Saved + pair print sent.', true);
                        } else {
                            showMsg('Saved, but pair print failed.', true);
                        }
                    }
                }
                // After save, auto-refresh to next entry (next barcode/doc)
                setMode('add');
                resetForm();
                await fetchNextBarcodeAndDoc();
                document.getElementById('fIcode').focus();
            }
        } catch(e) { showMsg('Error: ' + e.message, false); }
    });

    // CANCEL (Esc)
    document.getElementById('btnCancel').addEventListener('click', () => { resetForm(); setMode('ready'); });

    // CLOSE
    document.getElementById('btnClose').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
            return;
        }
        window.close();
    });

    const clickIf = (id) => {
        const el = document.getElementById(id);
        if (el) el.click();
    };

    // --- Keyboard Shortcuts ---
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F4') { e.preventDefault(); clickIf('btnDelete'); }
        if (e.key === 'F5') { e.preventDefault(); clickIf('btnSave'); }
        if (e.key === 'F7') { e.preventDefault(); clickIf('btnList'); }
        if (e.key === 'Escape') { e.preventDefault(); clickIf('btnCancel'); }
    });

    // --- Init ---
    setMode('add');
    resetForm();
    fetchNextBarcodeAndDoc();
})();
</script>
</body>
</html>
