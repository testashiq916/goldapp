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

    .card {
        width: min(1120px, calc(100vw - 28px)); margin: 0 auto; background: var(--surface);
        border: 1px solid var(--border); border-radius: var(--radius);
        box-shadow: var(--shadow); overflow: hidden;
        min-height: calc(100vh - 20px);
        display: flex;
        flex-direction: column;
    }

    .topstrip {
        padding: 5px 14px;
        background: #f8fafc;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }
    .mode-badge {
        display: inline-block;
        padding: 2px 10px;
        font-size: 11px;
        font-weight: 700;
        border-radius: 3px;
        background: var(--pri-light);
        color: var(--pri);
        border: 1px solid #93c5fd;
        letter-spacing: 0.3px;
    }
    .topstrip a {
        margin-left: auto;
        font-size: 11px;
        color: var(--tx3);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: color .15s;
    }
    .topstrip a:hover { color: var(--pri); }

    .msg {
        margin: 8px 14px 0; padding: 7px 12px; font-size: 11px;
        display: none; border-radius: 4px; border: 1px solid;
        flex-shrink: 0;
    }
    .msg-ok { background: var(--ok-bg); border-color: var(--ok-border); color: var(--ok); display: block; }
    .msg-err { background: var(--err-bg); border-color: var(--err-border); color: var(--err); display: block; }

    .form-body {
        padding: 10px 14px 8px;
        flex: 1;
        overflow: auto;
    }

    .fg {
        display: grid;
        grid-template-columns: 82px minmax(120px, 1fr) 82px minmax(120px, 1fr) 82px minmax(120px, 1fr);
        gap: 4px 8px; align-items: center;
    }
    .fg label {
        font-size: 11.5px; font-weight: 600; color: var(--tx3);
        text-align: right; padding-right: 4px; white-space: nowrap; line-height: 1;
    }
    .fg input, .fg select {
        width: 100%; height: 27px; border: 1px solid var(--border);
        border-radius: 3px; padding: 0 7px; font-size: 12px;
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: var(--field-bg); color: var(--tx); transition: border-color .15s, box-shadow .15s;
    }
    .fg input:focus, .fg select:focus {
        outline: none; border-color: var(--border-focus);
        box-shadow: 0 0 0 2px rgba(59,130,246,0.12);
    }
    .fg input[readonly] {
        background: var(--field-ro); color: var(--tx3); border-color: #e5e7eb;
    }
    .fg input[type='number'] { text-align: right; }
    .fg select { cursor: pointer; }
    .col-span-3 { grid-column: span 3; }
    .col-span-5 { grid-column: span 5; }
    .row-divider {
        grid-column: 1 / -1;
        border: none; border-top: 1px solid #e9edf2;
        margin: 3px 0;
    }

    .section-label {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: var(--pri);
        padding: 5px 8px 3px; border-bottom: 2px solid var(--pri-light);
        margin: 8px 0 4px; grid-column: 1 / -1;
        background: #f0f6ff; border-radius: 3px 3px 0 0;
    }
    .section-label:first-child { margin-top: 0; }

    .opt-panel {
        grid-column: 1 / -1;
        display: flex; flex-wrap: wrap;
        gap: 6px 18px; padding: 6px 10px;
        background: #f8fafc; border: 1px solid #e2e8f0;
        border-radius: 3px; margin: 2px 0;
    }
    .opt-panel label {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 11.5px; color: var(--tx2); font-weight: 600;
        white-space: nowrap; cursor: pointer;
    }
    .opt-panel input[type='checkbox'] {
        width: 13px; height: 13px; accent-color: var(--pri2); cursor: pointer;
    }

    .with-def {
        display: grid; grid-template-columns: 1fr 26px;
        align-items: center; gap: 2px; min-width: 0;
    }
    .with-def input, .with-def select { min-width: 0; width: 100%; }
    .def-btn {
        height: 27px; width: 26px; padding: 0;
        border: 1px solid var(--border); border-radius: 3px;
        background: #f9fafb; font-size: 9px; font-weight: 700;
        color: var(--tx3); cursor: pointer; transition: all .15s;
    }
    .def-btn:hover { background: var(--pri-light); border-color: var(--pri2); color: var(--pri); }

    .checkbox-row {
        grid-column: 1 / -1; display: flex; gap: 18px; flex-wrap: wrap;
        padding: 6px 0 4px; border-top: 1px solid #e9edf2; margin-top: 2px;
    }
    .checkbox-row label {
        display: flex; align-items: center; gap: 5px;
        font-size: 11.5px; color: var(--tx2); font-weight: 600; cursor: pointer;
    }
    .checkbox-row input[type='checkbox'] {
        width: 13px; height: 13px; accent-color: var(--pri2); cursor: pointer;
    }

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

    /* ════════════════════════════════
       IMPROVED MODAL
    ════════════════════════════════ */
    .modal {
        position: fixed; inset: 0; background: rgba(0,0,0,0.55);
        display: none; align-items: center; justify-content: center;
        z-index: 9999; backdrop-filter: blur(3px);
    }
    .modal.show { display: flex; }
    .modal-card {
        width: min(980px, calc(100vw - 24px));
        max-height: calc(100vh - 60px);
        background: var(--surface); border-radius: 8px;
        box-shadow: 0 24px 64px rgba(0,0,0,0.35), 0 0 0 2px var(--pri);
        overflow: hidden; display: flex; flex-direction: column;
    }
    .modal-head {
        display: flex; justify-content: space-between; align-items: center;
        padding: 11px 16px; background: var(--pri-bg); color: #fff; flex-shrink: 0;
    }
    .mh-left { display: flex; align-items: center; gap: 10px; }
    .mh-left i { font-size: 18px; color: #93c5fd; }
    .mh-left strong { font-size: 14px; font-weight: 700; }
    .mh-badge {
        font-size: 10px; padding: 2px 9px; border-radius: 10px;
        background: rgba(255,255,255,0.15); color: #bfdbfe; font-weight: 600;
    }
    .mh-right { display: flex; align-items: center; gap: 8px; }
    .modal-search {
        width: 290px; height: 30px;
        border: 1px solid rgba(255,255,255,0.25); border-radius: 4px;
        padding: 0 10px; font-size: 12px;
        background: rgba(255,255,255,0.12); color: #fff;
    }
    .modal-search::placeholder { color: rgba(255,255,255,0.4); }
    .modal-search:focus { outline: none; background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.5); }
    .btn-modal-close {
        height: 30px; padding: 0 12px;
        border: 1px solid rgba(255,255,255,0.2); border-radius: 4px;
        font-size: 12px; font-weight: 700;
        background: rgba(255,255,255,0.1); color: #fff; cursor: pointer;
        display: inline-flex; align-items: center; gap: 5px; transition: all .15s;
    }
    .btn-modal-close:hover { background: rgba(255,255,255,0.22); }
    .btn-modal-action {
        height: 30px; padding: 0 12px;
        border: 1px solid #93c5fd; border-radius: 4px;
        font-size: 12px; font-weight: 700;
        background: #eff6ff; color: #1d4ed8; cursor: pointer;
        display: inline-flex; align-items: center; gap: 5px; transition: all .15s;
    }
    .btn-modal-action:hover { background: #2563eb; color: #fff; border-color: #2563eb; }
    .btn-modal-action.print { border-color: #86efac; background: #f0fdf4; color: #15803d; }
    .btn-modal-action.print:hover { background: #16a34a; color: #fff; border-color: #16a34a; }
    .modal-body { flex: 1; overflow: auto; }
    .modal-footer {
        padding: 7px 16px; border-top: 1px solid #e5e7eb; background: #f8fafc;
        font-size: 11px; color: var(--tx3);
        display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
    }
    .pick-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .pick-table thead { position: sticky; top: 0; z-index: 2; }
    .pick-table th {
        background: #253f6b; color: #dbeafe; padding: 8px 10px; text-align: left;
        font-weight: 600; font-size: 11px; border-bottom: 2px solid var(--pri);
    }
    .pick-table td { border-bottom: 1px solid #f0f2f5; padding: 6px 10px; vertical-align: middle; }
    .pick-table tbody tr:nth-child(even) { background: #f8fafc; }
    .pick-table tbody tr:hover td { background: var(--pri-light); cursor: pointer; }
    .pick-table tbody tr.is-selected td { background: #dbeafe !important; box-shadow: inset 0 1px 0 #93c5fd, inset 0 -1px 0 #93c5fd; }
    .pick-table tbody tr.is-checked td { background: #ecfdf5; }
    .btn-reprint {
        padding: 3px 9px; height: 24px; font-size: 10px; font-weight: 700;
        border: 1px solid #a5b4fc; border-radius: 3px;
        background: #eef2ff; color: #4338ca; cursor: pointer;
        display: inline-flex; align-items: center; gap: 4px;
        transition: all .15s; white-space: nowrap;
    }
    .btn-reprint:hover  { background: #4338ca; color: #fff; border-color: #4338ca; }
    .btn-reprint.ok     { background: #d1fae5; color: #065f46; border-color: #6ee7b7; pointer-events: none; }
    .btn-reprint.fail   { background: #fee2e2; color: #991b1b; border-color: #fca5a5; pointer-events: none; }
    .btn-reprint.busy   { opacity: .6; pointer-events: none; }
    .btn-row-select {
        width: 24px; height: 24px; padding: 0;
        border: 1px solid #86efac; border-radius: 3px;
        background: #f0fdf4; color: #15803d; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        transition: all .15s;
    }
    .btn-row-select:hover { background: #16a34a; color: #fff; border-color: #16a34a; }
    .btn-row-select.is-checked { background: #16a34a; color: #fff; border-color: #16a34a; }

    @media(max-width: 1200px) {
        .fg { grid-template-columns: 88px minmax(150px, 1fr) 88px minmax(150px, 1fr); }
    }
    @media(max-width: 900px) {
        body { padding: 0; }
        .fg { grid-template-columns: 1fr; }
        .fg label { text-align: left; padding-right: 0; }
        .card { width: 100%; margin: 0; border-radius: 0; min-height: 100vh; }
        .btnbar { padding: 8px 14px; }
        .opt-panel { grid-template-columns: repeat(2, auto); }
    }
    @media(max-width: 640px) {
        .opt-panel { grid-template-columns: 1fr; }
        .checkbox-row { gap: 12px; }
    }
    </style>

    <link rel="stylesheet" href="{{ url('/css/goldapp-responsive.css') }}">
</head>
<body>
<div class="card">

    <!-- TOP STRIP -->
    <div class="topstrip">
        <span class="mode-badge" id="modeBadge">Add New Barcode</span>
        <a href="{{ url('/barcode-single-entry/settings') }}">
            <i class="fas fa-cog"></i> Settings
        </a>
    </div>

    <!-- MESSAGE -->
    <div id="msg" class="msg"></div>

    <!-- FORM -->
    <div class="form-body">
        <div class="fg">
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

            <div class="opt-panel">
                <label><input type="checkbox" id="fStkinnos"> Stk In Nos</label>
                <label><input type="checkbox" id="fPrintThisBc"> Print this BCode</label>
                <label><input type="checkbox" id="fPrintAfter2"> Print Barcode after 2 items</label>
            </div>

            <!-- ITEM SECTION -->
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
            <input type="hidden" id="fQunit">

            <!-- RATE & MC SECTION -->
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
            <label for="fTranstouch">Trans Touch</label>
            <div class="with-def">
                <input type="number" step="0.01" id="fTranstouch" min="0">
                <button type="button" class="def-btn" data-def-target="fTranstouch" data-def-key="BCTransTouch">Def</button>
            </div>
            <input type="hidden" id="fStktouch">
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

            <label for="fTamt">Total Amt</label>
            <input type="number" step="0.01" id="fTamt">
            <label for="fCostamt">Purch Amt</label>
            <input type="number" step="0.01" id="fCostamt" readonly>
            <label for="fGrate">G.Rate</label>
            <input type="number" step="0.01" id="fGrate" readonly>

            <!-- OTHER SECTION -->
            <label for="fSerialno">Serial No</label>
            <input type="text" id="fSerialno">
            <label for="fHuid">HUID</label>
            <input type="text" id="fHuid">
            <input type="hidden" id="fCounter">
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
                <input type="checkbox" id="fNodisc" hidden>
            </div>
        </div>

        <!-- DIAMOND DETAILS -->
        <div class="dmd-section">
            <hr style="margin:6px 0 4px;border:none;border-top:1px solid #e5e7eb;">
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

<!-- ════════════════════════════════
     IMPROVED BARCODE LIST MODAL
════════════════════════════════ -->
<div class="modal" id="bcListModal">
    <div class="modal-card">
        <div class="modal-head">
            <div class="mh-left">
                <i class="fas fa-list-alt"></i>
                <strong>Barcode List</strong>
                <span class="mh-badge" id="bcListCount">…</span>
            </div>
            <div class="mh-right">
                <input class="modal-search" type="text" id="bcListSearch" placeholder="Search barcode, item code or name…">
                <button class="btn-modal-action" id="bcListSelect" type="button">
                    <i class="fas fa-check"></i> Select
                </button>
                <button class="btn-modal-action print" id="bcListPrintSelected" type="button">
                    <i class="fas fa-print"></i> Print Selected
                </button>
                <button class="btn-modal-close" id="bcListClose" type="button">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
        <div class="modal-body">
            <table class="pick-table">
                <thead>
                    <tr>
                        <th style="width:44px;text-align:center;">
                            <button type="button" class="btn-row-select" id="bcListSelectAll" title="Select all">
                                <i class="fas fa-check-double"></i>
                            </button>
                        </th>
                        <th style="width:110px;">Barcode #</th>
                        <th style="width:95px;">Item Code</th>
                        <th>Item Name</th>
                        <th style="width:80px;text-align:right;">Weight</th>
                        <th style="width:90px;text-align:right;">Amount</th>
                        <th style="width:48px;text-align:center;">Stk</th>
                        <th style="width:82px;text-align:center;">Reprint</th>
                    </tr>
                </thead>
                <tbody id="bcListRows"></tbody>
            </table>
        </div>
        <div class="modal-footer">
            <span><i class="fas fa-mouse-pointer" style="margin-right:4px;color:var(--pri2);"></i>
                Click a row to <strong>open for editing</strong></span>
            <span><i class="fas fa-print" style="margin-right:4px;color:var(--ok);"></i>
                <strong>Reprint</strong> sends print job without opening the record</span>
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
    let selectedBarcodeBcodes = new Set();

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
        try { data = text ? JSON.parse(text) : {}; }
        catch (_) { throw new Error('Server returned invalid response (' + res.status + ').'); }
        if (!res.ok && !data?.message) data.message = 'Request failed (' + res.status + ').';
        return data;
    }

    function val(id) { const e = document.getElementById(id); return e ? (e.type === 'checkbox' ? e.checked : e.value) : ''; }
    function setVal(id, v) { const e = document.getElementById(id); if (!e) return; if (e.type === 'checkbox') e.checked = !!v; else e.value = v ?? ''; }
    function num(id) { return parseFloat(document.getElementById(id)?.value) || 0; }
    function focusField(id) {
        const el = document.getElementById(id);
        if (!el || el.disabled || el.readOnly) return;
        el.focus();
        if (typeof el.select === 'function') el.select();
    }
    function focusNextEntryField(currentEl) {
        const order = [
            'fBcode','fTdate','fDocno','fSmithcode','fIcode','fSubgrp','fQtype','fModel','fSizemodel',
            'fQty','fWeight','fStweight','fStickerWgt','fRate','fWastage','fMcrate','fMc',
            'fSmithmcrate','fTranstouch','fCost','fCostmc','fCoststone','fCostperc','fStprice',
            'fVap','fMinvap','fTamt','fSerialno','fHuid','fSmcode','fPart'
        ];
        const idx = order.indexOf(currentEl?.id || '');
        for (let i = idx + 1; i < order.length; i++) {
            const el = document.getElementById(order[i]);
            if (el && !el.disabled && !el.readOnly) {
                focusField(order[i]);
                return;
            }
        }
    }
    async function saveSetting(key, value) {
        try {
            const d = await api(siteUrl + '/api/barcode-entry/action?action=save_setting', 'POST', { key, value });
            return !!d.success;
        } catch (_) { return false; }
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

        const editable = (m === 'add' || m === 'edit');
        allFieldIds.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (readonlyFields.includes(id)) { el.readOnly = true; return; }
            if (el.tagName === 'SELECT') el.disabled = !editable;
            else if (el.type === 'checkbox') el.disabled = !editable;
            else el.readOnly = !editable;
        });
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
        if (printThis && printAfter2) {
            setVal('fPrintThisBc', true); setVal('fPrintAfter2', false);
        } else {
            setVal('fPrintThisBc', printThis); setVal('fPrintAfter2', printAfter2);
        }
    }

    // --- Auto Calculations ---
    function recalcAll() {
        const wt = num('fWeight'), st = num('fStweight');
        setVal('fNetWgt', (wt - st).toFixed(3));
        const netWgt = wt - st;
        if (!autoSamtEnabled) return;
        const mcRate = num('fMcrate');
        if (settings.BCFreshMC === 'Y' && mcRate > 0) setVal('fMc', (mcRate * netWgt).toFixed(2));
        const rate = num('fRate'), wastage = num('fWastage');
        const mc = num('fMc'), stPrice = num('fStprice'), vap = num('fVap');
        const metalValue = netWgt * rate * (1 + wastage / 100);
        const baseAmt = metalValue + mc + stPrice;
        const totalAmt = baseAmt * (1 + vap / 100);
        setVal('fTamt', (settings.RoundOffAllAmt === 'Y' ? Math.round(totalAmt) : totalAmt.toFixed(2)));
        const stkTouch = num('fStktouch'), costMc = num('fCostmc'), costStone = num('fCoststone');
        const costAmt = (netWgt * stkTouch / 100 * thRate) + costMc + costStone;
        setVal('fCostamt', costAmt.toFixed(2));
    }

    function recalcStkTouch() {
        const tt = num('fTranstouch');
        if (tt > 0) setVal('fStktouch', tt.toFixed(2));
    }

    // --- Print ---
    async function requestPrintBarcode(bcode) {
        const targetBcode = String(bcode || '').trim();
        return await api(siteUrl + '/api/barcode-entry/print', 'POST', {
            bcode: targetBcode, barcode: targetBcode,
            pxinc:            parseInt(settings.BCPXINC     ?? '0', 10) || 0,
            pyinc:            parseInt(settings.BCPYINC     ?? '0', 10) || 0,
            density:          parseInt(settings.BCDensity   ?? '14',10) || 14,
            printType:        'TemplateDesigner',
            printMode:        String(settings.BCPrintMode   || 'windows_image'),
            printerName:      String(settings.BCPrinterName || ''),
            printerShareName: String(settings.BCPrinterShareName || ''),
            renderAsImage:    'N',
            stickerMode:      String(settings.BCStickerMode       || 'single'),
            designerColumns:  String(settings.BCDesignerColumns   || '1'),
            designerTemplate: String(settings.BCDesignerTemplate  || '')
        });
    }
    async function requestPrintBarcodeBatch(bcodes) {
        return await api(siteUrl + '/api/barcode-entry/print-pair', 'POST', {
            bcodes: (bcodes || []).map(v => String(v || '').trim()).filter(Boolean),
            pxinc:            parseInt(settings.BCPXINC     ?? '0', 10) || 0,
            pyinc:            parseInt(settings.BCPYINC     ?? '0', 10) || 0,
            density:          parseInt(settings.BCDensity   ?? '14',10) || 14,
            printType:        'TemplateDesigner',
            printMode:        String(settings.BCPrintMode   || 'raw'),
            printerName:      String(settings.BCPrinterName || ''),
            printerShareName: String(settings.BCPrinterShareName || ''),
            renderAsImage:    'N',
            stickerMode:      String(settings.BCStickerMode       || 'single'),
            designerColumns:  String(settings.BCDesignerColumns   || '1'),
            designerTemplate: String(settings.BCDesignerTemplate  || '')
        });
    }
    async function requestPrintBarcodePair(firstBcode, secondBcode) {
        return requestPrintBarcodeBatch([firstBcode, secondBcode]);
    }

    // --- Barcode List Popup ---
    async function loadBarcodeList(search = '') {
        const tbody   = document.getElementById('bcListRows');
        const countEl = document.getElementById('bcListCount');
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:18px;color:var(--tx3);"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>';
        try {
            const d    = await api(siteUrl + '/api/barcode-entry/search?q=' + encodeURIComponent(search), 'GET');
            const rows = d.success ? (d.data || []) : [];
            countEl.textContent = rows.length + ' record' + (rows.length !== 1 ? 's' : '');
            selectedBarcodeBcodes = new Set();
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:22px;color:var(--tx3);">No records found.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(r => {
                const stkHtml = r.stk === 'Y'
                    ? '<span style="color:var(--ok);font-weight:700;">Y</span>'
                    : r.stk === 'N'
                    ? '<span style="color:var(--err);font-weight:700;">N</span>'
                    : (r.stk ?? '');
                return `<tr data-bcode="${r.bcode}" tabindex="0">
                    <td style="text-align:center;">
                        <button type="button" class="btn-row-select" data-select="${r.bcode}" title="Select">
                            <i class="fas fa-check"></i>
                        </button>
                    </td>
                    <td><strong style="color:var(--pri2);">${r.bcode ?? ''}</strong></td>
                    <td>${r.icode ?? ''}</td>
                    <td>${r.name ?? ''}</td>
                    <td style="text-align:right;">${r.weight ?? ''}</td>
                    <td style="text-align:right;">${r.tamt ?? ''}</td>
                    <td style="text-align:center;">${stkHtml}</td>
                    <td style="text-align:center;">
                        <button type="button" class="btn-reprint" data-reprint="${r.bcode}">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </td>
                </tr>`;
            }).join('');
            selectBarcodeListRow(tbody.querySelector('tr[data-bcode]'));
            syncBarcodeListChecks();
        } catch(e) {
            tbody.innerHTML = '<tr><td colspan="8" style="padding:16px;color:var(--err);">Failed to load list.</td></tr>';
        }
    }

    function openBarcodeList() {
        document.getElementById('bcListModal').classList.add('show');
        document.getElementById('bcListSearch').value = '';
        document.getElementById('bcListCount').textContent = '…';
        loadBarcodeList('');
        document.getElementById('bcListSearch').focus();
    }
    function closeBarcodeList() { document.getElementById('bcListModal').classList.remove('show'); }

    document.getElementById('btnList').addEventListener('click', openBarcodeList);
    document.getElementById('bcListClose').addEventListener('click', closeBarcodeList);
    document.getElementById('bcListSelect').addEventListener('click', () => {
        openBarcodeListRow(selectedBarcodeListRow());
    });
    document.getElementById('bcListPrintSelected').addEventListener('click', () => {
        printBarcodeListSelected();
    });
    document.getElementById('bcListSelectAll').addEventListener('click', () => {
        const rows = Array.from(document.querySelectorAll('#bcListRows tr[data-bcode]'));
        const allSelected = rows.length > 0 && rows.every(tr => selectedBarcodeBcodes.has(String(tr.dataset.bcode || '')));
        selectedBarcodeBcodes = new Set(allSelected ? [] : rows.map(tr => String(tr.dataset.bcode || '')).filter(Boolean));
        syncBarcodeListChecks();
    });
    document.getElementById('bcListModal').addEventListener('click', e => { if (e.target.id === 'bcListModal') closeBarcodeList(); });
    document.getElementById('bcListSearch').addEventListener('input', function() { loadBarcodeList(this.value.trim()); });

    function selectBarcodeListRow(tr, focusRow = false) {
        document.querySelectorAll('#bcListRows tr[data-bcode]').forEach(row => row.classList.remove('is-selected'));
        if (!tr) return;
        tr.classList.add('is-selected');
        if (focusRow) tr.focus();
    }

    function selectedBarcodeListRow() {
        return document.querySelector('#bcListRows tr.is-selected[data-bcode]')
            || document.querySelector('#bcListRows tr[data-bcode]');
    }

    function syncBarcodeListChecks() {
        document.querySelectorAll('#bcListRows tr[data-bcode]').forEach(tr => {
            const checked = selectedBarcodeBcodes.has(String(tr.dataset.bcode || ''));
            tr.classList.toggle('is-checked', checked);
            const btn = tr.querySelector('.btn-row-select');
            if (btn) {
                btn.classList.toggle('is-checked', checked);
                btn.innerHTML = checked ? '<i class="fas fa-check-square"></i>' : '<i class="far fa-square"></i>';
            }
        });
    }

    function toggleBarcodeListSelection(tr) {
        if (!tr) return;
        const bcode = String(tr.dataset.bcode || '');
        if (!bcode) return;
        if (selectedBarcodeBcodes.has(bcode)) selectedBarcodeBcodes.delete(bcode);
        else selectedBarcodeBcodes.add(bcode);
        selectBarcodeListRow(tr);
        syncBarcodeListChecks();
    }

    function selectedBarcodeCodesForPrint() {
        const selected = Array.from(selectedBarcodeBcodes);
        if (selected.length) return selected;
        const row = selectedBarcodeListRow();
        return row ? [String(row.dataset.bcode || '')].filter(Boolean) : [];
    }

    function openBarcodeListRow(tr) {
        if (!tr) return;
        if (mode === 'add') setMode('ready');
        setVal('fBcode', tr.dataset.bcode || '');
        closeBarcodeList();
        loadBarcodeByNumber();
    }

    async function printBarcodeListRow(tr, button = null) {
        if (!tr) return;
        const bcode = tr.dataset.bcode || '';
        const repBtn = button || tr.querySelector('.btn-reprint');
        if (repBtn) {
            repBtn.classList.add('busy');
            repBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }
        try {
            const pr = await requestPrintBarcodeBatch([bcode]);
            if (repBtn) {
                repBtn.classList.remove('busy');
                repBtn.classList.add(pr?.success ? 'ok' : 'fail');
                repBtn.innerHTML = pr?.success ? '<i class="fas fa-check"></i> Sent' : '<i class="fas fa-times"></i> Fail';
            }
            showMsg(pr?.message || (pr?.success ? 'Print sent for ' + bcode : 'Print failed for ' + bcode), pr?.success);
        } catch(err) {
            if (repBtn) {
                repBtn.classList.remove('busy');
                repBtn.classList.add('fail');
                repBtn.innerHTML = '<i class="fas fa-times"></i> Fail';
            }
            showMsg('Print error: ' + err.message, false);
        }
        if (repBtn) {
            setTimeout(() => { repBtn.className = 'btn-reprint'; repBtn.innerHTML = '<i class="fas fa-print"></i> Print'; }, 3000);
        }
    }

    async function printBarcodeListSelected() {
        const bcodes = selectedBarcodeCodesForPrint();
        if (!bcodes.length) {
            showMsg('Select at least one barcode to print.', false);
            return;
        }
        try {
            const pr = await requestPrintBarcodeBatch(bcodes);
            showMsg(pr?.message || (pr?.success ? 'Print sent for selected barcodes.' : 'Print failed.'), !!pr?.success);
        } catch(err) {
            showMsg('Print error: ' + err.message, false);
        }
    }

    document.getElementById('bcListSearch').addEventListener('keydown', function(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectBarcodeListRow(selectedBarcodeListRow(), true);
            return;
        }
        if (e.key === 'F8' || e.key.toLowerCase() === 'p') {
            e.preventDefault();
            printBarcodeListSelected();
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            openBarcodeListRow(selectedBarcodeListRow());
        }
    });

    document.getElementById('bcListRows').addEventListener('click', async e => {
        const selectBtn = e.target.closest('.btn-row-select');
        if (selectBtn) {
            const tr = selectBtn.closest('tr[data-bcode]');
            toggleBarcodeListSelection(tr);
            return;
        }
        // Reprint button
        const repBtn = e.target.closest('.btn-reprint');
        if (repBtn) {
            const tr = repBtn.closest('tr[data-bcode]');
            selectBarcodeListRow(tr);
            await printBarcodeListRow(tr, repBtn);
            return;
        }
        // Row click → open for edit
        const tr = e.target.closest('tr[data-bcode]');
        selectBarcodeListRow(tr);
    });

    document.getElementById('bcListRows').addEventListener('dblclick', e => {
        const tr = e.target.closest('tr[data-bcode]');
        if (!tr || e.target.closest('.btn-reprint')) return;
        selectBarcodeListRow(tr);
        openBarcodeListRow(tr);
    });

    document.getElementById('bcListRows').addEventListener('keydown', e => {
        const rows = Array.from(document.querySelectorAll('#bcListRows tr[data-bcode]'));
        const current = e.target.closest('tr[data-bcode]') || selectedBarcodeListRow();
        const idx = Math.max(0, rows.indexOf(current));
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const nextIdx = e.key === 'ArrowDown' ? Math.min(rows.length - 1, idx + 1) : Math.max(0, idx - 1);
            selectBarcodeListRow(rows[nextIdx], true);
            return;
        }
        if (e.key === 'F8' || e.key.toLowerCase() === 'p') {
            e.preventDefault();
            printBarcodeListSelected();
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            openBarcodeListRow(current);
        }
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
            showMsg(ok ? ('Default saved: ' + key + ' = ' + sval) : ('Failed to save default: ' + key), ok);
        });
    });

    // --- Item Code lookup ---
    async function loadItemCode(moveNext = false) {
        const itemEl = document.getElementById('fIcode');
        const icode = itemEl.value.trim().toUpperCase();
        itemEl.value = icode;
        if (!icode) { setVal('fIname', ''); return; }
        try {
            const d = await api(siteUrl + '/api/barcode-entry/load-item?icode=' + encodeURIComponent(icode), 'GET');
            if (!d.success) { showMsg(d.message || 'Item not found', false); return; }
            const item = d.data;
            setVal('fIname', item.name || '');
            if (mode === 'add') {
                if (!val('fWastage')) setVal('fWastage', item.wastage || '');
                if (!val('fMcrate'))  setVal('fMcrate',  item.mcharge || '');
                if (!val('fVap'))     setVal('fVap',      item.vaperc || '');
                if (item.subgrpcode || item.grpcode) setVal('fSubgrp', item.subgrpcode || item.grpcode);
                if (item.defquality || item.qtype) setVal('fQtype', item.defquality || item.qtype);
                if (item.stkinnos === 'Y') setVal('fStkinnos', true);
                if (item.nodisc === 'N')   setVal('fNodisc', true);
                if (item.stickerwgt)  setVal('fStickerWgt', item.stickerwgt);
                if (item.qualityTouch) setVal('fStktouch', item.qualityTouch);
            }
            recalcStkTouch(); recalcAll();
            if (moveNext) focusField('fSubgrp');
        } catch(e) { showMsg('Error loading item: ' + e.message, false); }
    }

    document.getElementById('fIcode').addEventListener('change', function() {
        loadItemCode(false);
    });
    document.getElementById('fIcode').addEventListener('keyup', function(e) {
        if (e.key !== 'Enter') return;
        setTimeout(() => loadItemCode(true), 0);
    });

    document.querySelector('.form-body').addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        const el = e.target;
        if (!el || el.id === 'fIcode' || el.tagName === 'TEXTAREA' || el.type === 'button') return;
        e.preventDefault();
        if (el.id === 'fBcode') {
            loadBarcodeByNumber();
        } else if (el.tagName === 'SELECT' || el.tagName === 'INPUT') {
            focusNextEntryField(el);
        }
    });

    document.getElementById('fDocno').addEventListener('change', async function() {
        const docno = this.value.trim();
        if (!docno) { setVal('fTotWgt', ''); setVal('fTotNos', ''); return; }
        try {
            const d = await api(siteUrl + '/api/barcode-entry/load-document?docno=' + encodeURIComponent(docno), 'GET');
            if (d.success && d.data) { setVal('fTotWgt', d.data.accWeight ?? ''); setVal('fTotNos', d.data.accNos ?? ''); }
        } catch(e) {}
    });

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
            if (!d.success) { showMsg('Barcode "' + bcode + '" not found.', false); return; }
            applyBarcodeData(d.data);
            setMode('edit');
            showMsg('Barcode ' + bcode + ' loaded for editing.', true);
        } catch(e) { showMsg(e.message, false); }
    }

    function applyBarcodeData(data) {
        const bc = data.barcode;
        currentBcode = bc.bcode;
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
        setVal('fStk',     bc.stk === 'N' || bc.stk === 'S');
        setVal('fNodisc',  bc.nodisc === 'N');
        setVal('fStkinnos',bc.stkinnos === 'Y');
        const wt = parseFloat(bc.weight) || 0, sw = parseFloat(bc.stweight) || 0;
        setVal('fNetWgt', (wt - sw).toFixed(3));
        dmdRows = (data.dmdDetails || []).map(r => ({
            stcode: r.stcode || '', sttype: r.sttype || '', stcolor: r.stcolor || '', stcut: r.stcut || '',
            pcs: parseFloat(r.pcs || 0) || 0, carats: parseFloat(r.carats || 0) || 0,
            wgt: parseFloat(r.wgt || 0) || 0, rate: parseFloat(r.rate || 0) || 0,
            amount: parseFloat((r.pamt ?? r.amount) || 0) || 0,
            samount: parseFloat(r.amount || r.samount || 0) || 0,
            itemname: r.itemname || '', itemtype: r.itemtype || '',
            cost: parseFloat(r.cost || 0) || 0,
            stkinnos: (r.stkinnos || '').toString().trim().toUpperCase(),
            stktype: r.stktype || '', iqtype: r.iqtype || ''
        }));
        renderDmd();
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
        const row = dmdRows[i]; if (!row) return;
        const pcs = parseFloat(row.pcs || 0) || 0, carats = parseFloat(row.carats || 0) || 0;
        let samount = parseFloat(row.samount || 0) || 0;
        if (changedField === 'carats') row.wgt = carats / 5;
        else if (changedField === 'samount' && carats > 0) row.rate = samount / carats;
        const useNos = ((row.stkinnos || '').toUpperCase() === 'Y') || val('fStkinnos');
        samount = useNos ? (pcs * row.rate) : ((parseFloat(row.carats || 0) || 0) * row.rate);
        row.samount = roundDmdAmount(samount);
    }
    async function hydrateDmdRowFromStoneCode(i) {
        const row = dmdRows[i]; if (!row) return;
        const scode = String(row.stcode || '').trim().toUpperCase();
        row.stcode = scode; if (!scode) return;
        const d = await api(siteUrl + '/api/barcode-entry/load-item?icode=' + encodeURIComponent(scode), 'GET');
        if (!d.success || !d.data) throw new Error(d.message || 'Invalid Item');
        const item = d.data;
        if (String(item.disabled ?? '0') === '1') throw new Error('This Item is Disabled');
        row.itemname = String(item.name || ''); row.itemtype = String(item.itype || '');
        row.cost = parseFloat(item.cost || 0) || 0;
        row.stkinnos = String(item.stkinnos || '').trim().toUpperCase();
        row.iqtype = String(item.defquality || ''); row.stktype = String(item.defstktype || '');
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
                <td>${editable ? '<button type="button" class="dmd-del" data-i="' + i + '"><i class="fas fa-times"></i></button>' : ''}</td>`;
            tbody.appendChild(tr);
        });
    }
    document.getElementById('dmdRows').addEventListener('input', (e) => {
        const el = e.target, i = parseInt(el.dataset.i), f = el.dataset.f;
        if (isNaN(i) || !f) return;
        dmdRows[i][f] = el.type === 'number' ? (parseFloat(el.value) || 0) : el.value;
    });
    document.getElementById('dmdRows').addEventListener('change', async (e) => {
        const el = e.target, i = parseInt(el.dataset.i), f = el.dataset.f;
        if (isNaN(i) || !f) return;
        try {
            if (f === 'stcode') { await hydrateDmdRowFromStoneCode(i); recalcDmdRow(i, 'rate'); }
            else if (f === 'samount' || f === 'carats' || f === 'rate' || f === 'pcs') recalcDmdRow(i, f);
            renderDmd();
        } catch (err) {
            showMsg(err.message || 'Invalid Item', false);
            if (f === 'stcode') { dmdRows[i].stcode = ''; renderDmd(); }
        }
    });
    document.getElementById('dmdRows').addEventListener('click', (e) => {
        const btn = e.target.closest('.dmd-del'); if (!btn) return;
        dmdRows.splice(parseInt(btn.dataset.i), 1); renderDmd();
    });
    document.getElementById('btnAddDmdRow').addEventListener('click', () => {
        if (mode !== 'add' && mode !== 'edit') return;
        dmdRows.push({ stcode:'',sttype:'',stcolor:'',stcut:'',pcs:0,carats:0,wgt:0,rate:0,amount:0,samount:0,itemname:'',itemtype:'',cost:0,stkinnos:'',stktype:'',iqtype:'' });
        renderDmd();
        const inputs = document.getElementById('dmdRows').querySelectorAll('tr:last-child input');
        if (inputs.length > 0) inputs[0].focus();
    });
    document.getElementById('fStkinnos').addEventListener('change', () => {
        dmdRows.forEach((_, idx) => recalcDmdRow(idx, 'rate'));
        renderDmd();
    });

    // --- Buttons ---
    async function fetchNextBarcodeAndDoc() {
        if (!val('fBcManual')) {
            try { const d = await api(siteUrl + '/api/barcode-entry/next-barcode', 'GET'); if (d.success) setVal('fBcode', d.data?.nextBarcode ?? ''); } catch(e) {}
        }
        try { const d = await api(siteUrl + '/api/barcode-entry/next-docno', 'GET'); if (d.success) setVal('fDocno', d.data?.nextDocNo ?? ''); } catch(e) {}
    }

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
                if (String(currentBcode) === target) { resetForm(); setMode('ready'); }
                else setVal('fBcode', '');
            }
        } catch(e) { showMsg('Error: ' + e.message, false); }
    });

    // SAVE — with already-exists popup
    document.getElementById('btnSave').addEventListener('click', async () => {
        if (mode !== 'add' && mode !== 'edit') return;
        const bcode = val('fBcode').trim(), icode = val('fIcode').trim();
        if (!bcode) return showMsg('Barcode number is required.', false);
        if (!icode) return showMsg('Item code is required.', false);
        if (num('fQty') <= 0) return showMsg('Quantity must be > 0.', false);

        const payload = {
            mode, bcode, tdate: val('fTdate'), docno: val('fDocno'), smithcode: val('fSmithcode'),
            totwgt: val('fTotWgt'), totnos: val('fTotNos'),
            icode, subgrp: val('fSubgrp'), qtype: val('fQtype'), model: val('fModel'), sizemodel: val('fSizemodel'),
            qty: val('fQty'), weight: val('fWeight'), stweight: val('fStweight'), stickerwgt: val('fStickerWgt'), qunit: val('fQunit'),
            rate: val('fRate'), wastage: val('fWastage'), mcrate: val('fMcrate'), mc: val('fMc'), smithmcrate: val('fSmithmcrate'),
            transtouch: val('fTranstouch'), stktouch: val('fStktouch'), cost: val('fCost'),
            costmc: val('fCostmc'), coststone: val('fCoststone'), costperc: val('fCostperc'),
            stprice: val('fStprice'), vap: val('fVap'), minvap: val('fMinvap'),
            tamt: val('fTamt'), costamt: val('fCostamt'), grate: val('fGrate'),
            serialno: val('fSerialno'), huid: val('fHuid'), counter: val('fCounter'),
            smcode: val('fSmcode'), part: val('fPart'),
            sold: val('fStk') ? 'Y' : 'N', nodisc: val('fNodisc') ? 'Y' : 'N',
            stkinnos: val('fStkinnos') ? 'Y' : 'N', dmdDetails: dmdRows
        };

        async function doSave(p) {
            const d = await api(siteUrl + '/api/barcode-entry/save', 'POST', p);

            // Already exists → confirm update
            if (!d.success && /already exists/i.test(d.message || '')) {
                if (confirm('⚠️  Barcode ' + p.bcode + ' already exists.\n\nClick OK to UPDATE it, or Cancel to go back.')) {
                    return doSave({ ...p, mode: 'edit' });
                }
                showMsg('Save cancelled – barcode already exists.', false);
                return;
            }

            showMsg(d.message || (d.success ? 'Saved successfully ✓' : 'Error'), d.success);

            if (d.success) {
                currentBcode = d.data?.bcode || p.bcode;
                if (val('fPrintThisBc')) {
                    pendingPairPrintBcode = '';
                    try {
                        const pr = await requestPrintBarcode(currentBcode);
                        showMsg(pr?.success ? (pr.message || 'Saved + print job sent.') : ('Saved, but print failed: ' + (pr?.message || 'Unknown error')), true);
                    } catch (e) { showMsg('Saved, but print request failed: ' + e.message, true); }
                } else if (val('fPrintAfter2')) {
                    if (!pendingPairPrintBcode) {
                        pendingPairPrintBcode = currentBcode;
                        showMsg('Saved. Waiting one more item for pair print.', true);
                    } else {
                        let pr2 = null;
                        try { pr2 = await requestPrintBarcodePair(pendingPairPrintBcode, currentBcode); } catch (_) {}
                        pendingPairPrintBcode = '';
                        showMsg(pr2?.success ? (pr2.message || 'Saved + pair print sent.') : 'Saved, but pair print failed.', true);
                    }
                }
                setMode('add'); resetForm(); await fetchNextBarcodeAndDoc();
                document.getElementById('fIcode').focus();
            }
        }

        try { await doSave(payload); }
        catch(e) { showMsg('Error: ' + e.message, false); }
    });

    document.getElementById('btnCancel').addEventListener('click', () => { resetForm(); setMode('ready'); });
    document.getElementById('btnClose').addEventListener('click', () => {
        if (window.parent && window.parent !== window) { window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*'); return; }
        window.close();
    });

    const clickIf = (id) => { const el = document.getElementById(id); if (el) el.click(); };
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F4') { e.preventDefault(); clickIf('btnDelete'); }
        if (e.key === 'F5') { e.preventDefault(); clickIf('btnSave'); }
        if (e.key === 'F7') { e.preventDefault(); clickIf('btnList'); }
        if (e.key === 'Escape') { e.preventDefault(); clickIf('btnCancel'); }
    });

    setMode('add');
    resetForm();
    fetchNextBarcodeAndDoc();
})();
</script>
</body>
</html>
