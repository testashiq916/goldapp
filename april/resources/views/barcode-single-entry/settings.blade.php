<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Sticker Template Designer v2</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Outfit:wght@300;400;500;600;700&display=swap');
:root{
  --bg:#0b0d0f;--panel:#13161a;--card:#1a1e24;--border:#252b34;--border2:#2e3844;
  --text:#dde4ec;--muted:#6b7a8d;--accent:#d4a843;--accent2:#f0c96a;
  --green:#2ea96b;--red:#e05252;--blue:#4a9eff;--purple:#9b72dd;
  --mono:'JetBrains Mono',monospace;--sans:'Outfit',sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--sans);background:var(--bg);color:var(--text);height:100vh;overflow:hidden;display:flex;flex-direction:column;font-size:12px;}

/* HEADER */
.header{display:flex;align-items:center;gap:10px;padding:7px 12px;background:var(--panel);border-bottom:1px solid var(--border);flex-shrink:0;}
.hlogo{width:28px;height:28px;background:var(--accent);border-radius:5px;display:flex;align-items:center;justify-content:center;font-weight:800;color:#000;font-size:11px;}
.header h1{font-size:13px;font-weight:700;letter-spacing:0.3px;}
.hbadge{padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:#1e2a0e;color:#8dc040;border:1px solid #3a5010;}
.hactions{margin-left:auto;display:flex;gap:6px;}

/* BTN */
.btn{display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border-radius:5px;border:1px solid var(--border2);background:var(--card);color:var(--text);font-family:var(--sans);font-size:10px;font-weight:500;cursor:pointer;transition:all .15s;white-space:nowrap;}
.btn:hover{border-color:var(--accent);color:var(--accent);}
.btn.primary{background:var(--accent);border-color:var(--accent);color:#000;font-weight:700;}
.btn.primary:hover{background:var(--accent2);}
.btn.danger{border-color:#5a2a2a;color:var(--red);}
.btn.danger:hover{background:#2a1010;border-color:var(--red);}
.btn.success{border-color:#1a4a30;color:var(--green);}
.btn.success:hover{background:#0d2a1c;border-color:var(--green);}
.btn.purple{border-color:#3a2a5a;color:var(--purple);}
.btn.purple:hover{background:#1e1030;border-color:var(--purple);}
.btn.sm{padding:4px 8px;font-size:11px;}
.btn.xs{padding:2px 6px;font-size:10px;}
.btn.active{background:var(--accent);border-color:var(--accent);color:#000;}

/* LAYOUT */
.main{display:flex;flex:1;overflow:hidden;}

/* LEFT */
.lpanel{width:180px;min-width:180px;background:var(--panel);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;}
.tabs{display:flex;border-bottom:1px solid var(--border);flex-shrink:0;}
.tab{flex:1;padding:7px 4px;font-size:10px;font-weight:700;text-align:center;cursor:pointer;color:var(--muted);border-bottom:2px solid transparent;transition:all .15s;letter-spacing:.5px;text-transform:uppercase;}
.tab.active{color:var(--accent);border-bottom-color:var(--accent);}
.tabpane{flex:1;overflow-y:auto;padding:6px;}

.slabel{font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);padding:5px 0 3px;border-bottom:1px solid var(--border);margin-bottom:6px;}
.chips{display:flex;flex-wrap:wrap;gap:3px;margin-bottom:8px;}
.chip{padding:3px 7px;border-radius:4px;font-size:10px;font-weight:600;border:1px solid var(--border2);background:var(--card);cursor:grab;color:var(--text);transition:all .12s;font-family:var(--mono);user-select:none;}
.chip:hover{border-color:var(--accent);color:var(--accent);background:#1e1a0e;}
.chip:active{cursor:grabbing;opacity:.7;}
.chip.cbar{border-color:#3a4a1e;color:#8dc040;background:#111a0a;}
.chip.cbar:hover{border-color:#8dc040;}
.chip.cqr{border-color:#1e3a4a;color:#60b8d0;background:#0a1318;}
.chip.cqr:hover{border-color:#60b8d0;}
.chip.cstone{border-color:#3a2a4a;color:#c080e0;background:#150d1e;}
.chip.cstone:hover{border-color:#c080e0;}

.inp{width:100%;padding:4px 7px;border-radius:4px;border:1px solid var(--border2);background:var(--card);color:var(--text);font-family:var(--mono);font-size:10px;transition:border-color .15s;outline:none;}
.inp:focus{border-color:var(--accent);}
.inp-sm{height:26px;font-size:11px;}
select.inp{cursor:pointer;}

/* CENTER */
.cpanel{flex:1;display:flex;flex-direction:column;overflow:hidden;background:#0e1015;}
.toolbar{display:flex;align-items:center;gap:4px;flex-wrap:wrap;padding:5px 8px;border-bottom:1px solid var(--border);background:var(--panel);flex-shrink:0;}
.tsep{width:1px;height:20px;background:var(--border);margin:0 2px;}
.toolbar label{font-size:10px;color:var(--muted);}

/* CANVAS SWITCHER */
.cswitcher{display:flex;gap:0;border-bottom:1px solid var(--border);background:var(--panel);flex-shrink:0;}
.csw-btn{flex:1;max-width:180px;padding:7px 14px;font-size:11px;font-weight:600;cursor:pointer;border:none;border-bottom:2px solid transparent;background:transparent;color:var(--muted);transition:all .15s;font-family:var(--sans);}
.csw-btn.active{color:var(--accent);border-bottom-color:var(--accent);background:#1a1e24;}
.csw-badge{display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--muted);margin-left:4px;vertical-align:middle;}
.csw-btn.active .csw-badge{background:var(--accent);}
.csw-btn.has-els .csw-badge{background:var(--green);}

.canvas-scroll{flex:1;overflow:auto;padding:12px;display:flex;gap:12px;flex-wrap:wrap;align-content:flex-start;}

.canvas-wrap{display:flex;flex-direction:column;gap:4px;}
.canvas-title{font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted);}
.canvas{position:relative;background:#fff;border:2px dashed #b0bec5;border-radius:5px;overflow:hidden;flex-shrink:0;}
.canvas.active{border-color:var(--accent);}
.grid-ov{position:absolute;inset:0;pointer-events:none;background-image:linear-gradient(90deg,rgba(100,130,160,.1)1px,transparent 1px),linear-gradient(rgba(100,130,160,.1)1px,transparent 1px);background-size:20px 20px;}

/* ELEMENTS */
.el{position:absolute;cursor:move;user-select:none;border:1.5px solid #93c5fd;border-radius:3px;background:rgba(239,246,255,.93);color:#1a1a1a;font-size:11px;font-family:var(--mono);padding:2px 5px;white-space:nowrap;z-index:2;transition:box-shadow .1s;}
.el:hover{box-shadow:0 0 0 1px #93c5fd44;}
.el.sel{border-color:#2563eb;box-shadow:0 0 0 2px #3b82f640;}
.el.t-bar{background:rgba(254,252,232,.95);border-color:#fbbf24;padding:2px;}
.el.t-qr{background:rgba(240,253,244,.95);border-color:#4ade80;padding:2px;}
.el-inner{pointer-events:none;}
.el-inner svg{display:block;}
.el-inner canvas{display:block;}

/* RIGHT */
.rpanel{width:210px;min-width:210px;background:var(--panel);border-left:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;}
.rpanel .tabpane{flex:1;overflow-y:auto;}

/* PROPS */
.pgrid{display:grid;grid-template-columns:72px 1fr;gap:4px 6px;align-items:center;margin-bottom:3px;}
.plabel{font-size:10px;color:var(--muted);font-weight:500;}
.psect{margin-bottom:12px;}

/* STICKER MODEL LIST */
.smodel-row{display:flex;align-items:center;justify-content:space-between;padding:4px 6px;border-radius:4px;border:1px solid var(--border);background:var(--card);margin-bottom:3px;cursor:pointer;transition:border-color .12s;}
.smodel-row:hover{border-color:var(--accent);}
.smodel-row.active{border-color:var(--accent);background:#1e1a0e;}
.smodel-name{font-size:11px;font-family:var(--mono);color:var(--text);}
.smodel-meta{font-size:9px;color:var(--muted);}
.smodel-actions{display:flex;gap:3px;}

/* FIELD SETTINGS */
.fset-row{display:grid;grid-template-columns:1fr 44px 38px 28px;gap:3px;align-items:center;padding:3px 5px;border-radius:4px;background:var(--card);border:1px solid var(--border);margin-bottom:3px;}
.fset-tag{font-size:10px;font-family:var(--mono);color:var(--accent);overflow:hidden;text-overflow:ellipsis;}
.fset-inp{padding:2px 4px;border-radius:3px;border:1px solid var(--border2);background:var(--bg);color:var(--text);font-size:10px;text-align:center;width:100%;outline:none;}
.fset-inp:focus{border-color:var(--accent);}

/* TOAST */
.toast{position:fixed;bottom:18px;left:50%;transform:translateX(-50%) translateY(10px);padding:7px 14px;border-radius:5px;font-size:11px;font-weight:600;opacity:0;transition:all .2s;pointer-events:none;z-index:9999;}
.toast.on{opacity:1;transform:translateX(-50%) translateY(0);}
.ok{background:var(--green);color:#fff;}
.err{background:var(--red);color:#fff;}

/* COPY INDICATOR */
.copy-ind{position:absolute;top:2px;right:4px;font-size:8px;font-weight:700;color:var(--muted);z-index:4;pointer-events:none;}

@media (max-width: 1180px){
  .lpanel{width:160px;min-width:160px;}
  .rpanel{width:190px;min-width:190px;}
  .canvas-scroll{padding:10px;gap:10px;}
}
@media (max-width: 980px){
  .main{flex-direction:column;}
  .lpanel,.rpanel{width:100%;min-width:0;border-right:none;border-left:none;}
  .lpanel{border-bottom:1px solid var(--border);max-height:210px;}
  .rpanel{border-top:1px solid var(--border);max-height:280px;}
  .canvas-scroll{min-height:320px;}
}
</style>
@include('partials.print-layout-head')
</head>
<body>
@php($sw = $settings['Software'] ?? [])

<div class="header">
  <div class="hlogo">ST</div>
  <h1>Sticker Template Designer</h1>
  <span class="hbadge">v2 — BCTemplate1 / BCTemplate2</span>
  <div class="hactions">
    <button class="btn" id="btnImport">⬆ Import</button>
    <button class="btn" id="btnExport">⬇ Export</button>
    <button class="btn success" id="btnSaveJpg">Save JPG</button>
    <button class="btn success" id="btnPrintJpg">Print JPG</button>
    <button class="btn success" id="btnDirectPrintJpg">Direct Print JPG</button>
    <button class="btn success" id="btnPrintSample">Print Sample</button>
    <button class="btn primary" id="btnSave">💾 Save Template</button>
  </div>
</div>

<div class="main">

<!-- ======= LEFT PANEL ======= -->
<div class="lpanel">
  <div class="tabs" id="ltabs">
    <div class="tab active" data-lt="weight">Weight</div>
    <div class="tab" data-lt="item">Item</div>
    <div class="tab" data-lt="stone">Stone</div>
    <div class="tab" data-lt="code">Barcode</div>
  </div>

  <!-- WEIGHT TAB -->
  <div class="tabpane" id="lt-weight">
    <div class="slabel">Weight Fields</div>
    <div class="chips">
      <div class="chip" draggable="true" data-type="text" data-value="[Weight]">[Weight]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[NetWgt]">[NetWgt]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[StWgt]">[StWgt]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[DmdWgt]">[DmdWgt]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[Weight1]">[Weight1]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[Weight2]">[Weight2]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[Weight3]">[Weight3]</div>
    </div>
    <div class="slabel">Price / Rate</div>
    <div class="chips">
      <div class="chip" draggable="true" data-type="text" data-value="[Amount]">[Amount]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[Rate]">[Rate]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[MetalRate]">[MetalRate]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[StPrice]">[StPrice]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[DmdAmt]">[DmdAmt]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[VA]">[VA]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[MVA]">[MVA]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[MRP]">[MRP]</div>
    </div>
    <div class="slabel">Custom Text</div>
    <div style="display:flex;flex-direction:column;gap:5px">
      <input class="inp" id="customTxt" placeholder="Type text...">
      <button class="btn sm" id="btnAddTxt">+ Add to Canvas</button>
    </div>
  </div>

  <!-- ITEM TAB -->
  <div class="tabpane" id="lt-item" style="display:none">
    <div class="slabel">Item / Design</div>
    <div class="chips">
      <div class="chip" draggable="true" data-type="text" data-value="[BarCode]">[BarCode]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[ItemCode]">[ItemCode]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[ItemName]">[ItemName]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[DesignCode]">[DesignCode]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[DesignName]">[DesignName]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[DesignSize]">[DesignSize]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[DesignStyle]">[DesignStyle]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[DesignStyleCode]">[DesignStyleCode]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[ItemGroup]">[ItemGroup]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[SubGrpCode]">[SubGrpCode]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[SubGrpCode2]">[SubGrpCode2]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[ShopName]">[ShopName]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[Purity]">[Purity]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[MetalPurity]">[MetalPurity]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[QType]">[QType]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[Qty]">[Qty]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[Model]">[Model]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[SmithCode]">[SmithCode]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[SerialNo]">[SerialNo]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[HUID]">[HUID]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[SKUNumber]">[SKUNumber]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[CertificationNo]">[CertificationNo]</div>
      <div class="chip" draggable="true" data-type="text" data-value="[ReferenceNo]">[ReferenceNo]</div>
    </div>
  </div>

  <!-- STONE TAB -->
  <div class="tabpane" id="lt-stone" style="display:none">
    <div class="slabel">Stone Amounts</div>
    <div class="chips">
      <div class="chip cstone" draggable="true" data-type="text" data-value="[Stone1]">[Stone1]</div>
      <div class="chip cstone" draggable="true" data-type="text" data-value="[Stone2]">[Stone2]</div>
      <div class="chip cstone" draggable="true" data-type="text" data-value="[Stone3]">[Stone3]</div>
    </div>
    <div class="slabel">Stone Weights</div>
    <div class="chips">
      <div class="chip cstone" draggable="true" data-type="text" data-value="[Weight1]">[Weight1]</div>
      <div class="chip cstone" draggable="true" data-type="text" data-value="[Weight2]">[Weight2]</div>
      <div class="chip cstone" draggable="true" data-type="text" data-value="[Weight3]">[Weight3]</div>
    </div>
    <div class="slabel">Stone Codes</div>
    <div class="chips">
      <div class="chip cstone" draggable="true" data-type="text" data-value="[Stcode1]">[Stcode1]</div>
      <div class="chip cstone" draggable="true" data-type="text" data-value="[Stcode2]">[Stcode2]</div>
      <div class="chip cstone" draggable="true" data-type="text" data-value="[Stcode3]">[Stcode3]</div>
    </div>
    <div class="slabel">Diamond</div>
    <div class="chips">
      <div class="chip cstone" draggable="true" data-type="text" data-value="[DmdWgt]">[DmdWgt]</div>
      <div class="chip cstone" draggable="true" data-type="text" data-value="[DmdNos]">[DmdNos]</div>
      <div class="chip cstone" draggable="true" data-type="text" data-value="[DmdAmt]">[DmdAmt]</div>
      <div class="chip cstone" draggable="true" data-type="text" data-value="[DmdSize]">[DmdSize]</div>
      <div class="chip cstone" draggable="true" data-type="text" data-value="[DmdCut]">[DmdCut]</div>
      <div class="chip cstone" draggable="true" data-type="text" data-value="[DmdColor]">[DmdColor]</div>
    </div>
  </div>

  <!-- BARCODE TAB -->
  <div class="tabpane" id="lt-code" style="display:none">
    <div class="slabel">Barcode / QR</div>
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px">
      <div class="chip cbar" draggable="true" data-type="barcode128" data-value="[BarCode]" style="padding:8px;display:flex;align-items:center;gap:6px">
        <svg width="36" height="14"><rect x="0" y="0" width="2" height="14" fill="#4a7020"/><rect x="4" y="0" width="1" height="14" fill="#4a7020"/><rect x="7" y="0" width="3" height="14" fill="#4a7020"/><rect x="12" y="0" width="2" height="14" fill="#4a7020"/><rect x="16" y="0" width="1" height="14" fill="#4a7020"/><rect x="20" y="0" width="3" height="14" fill="#4a7020"/><rect x="25" y="0" width="2" height="14" fill="#4a7020"/><rect x="29" y="0" width="1" height="14" fill="#4a7020"/><rect x="32" y="0" width="3" height="14" fill="#4a7020"/></svg>
        <span>Barcode 128</span>
      </div>
      <div class="chip cqr" draggable="true" data-type="qrcode" data-value="[BarCode]" style="padding:8px;display:flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 14 14"><rect x="1" y="1" width="5" height="5" fill="none" stroke="#60b8d0" stroke-width="1.2"/><rect x="2.5" y="2.5" width="2" height="2" fill="#60b8d0"/><rect x="8" y="1" width="5" height="5" fill="none" stroke="#60b8d0" stroke-width="1.2"/><rect x="9.5" y="2.5" width="2" height="2" fill="#60b8d0"/><rect x="1" y="8" width="5" height="5" fill="none" stroke="#60b8d0" stroke-width="1.2"/><rect x="2.5" y="9.5" width="2" height="2" fill="#60b8d0"/><rect x="8" y="8" width="2" height="2" fill="#60b8d0"/><rect x="11" y="8" width="2" height="2" fill="#60b8d0"/><rect x="9.5" y="10" width="2" height="2" fill="#60b8d0"/></svg>
        <span>QR Code</span>
      </div>
    </div>
    <div class="slabel">Barcode Value</div>
    <select class="inp" id="bcValSel" style="margin-bottom:6px">
      <option value="[BarCode]">[BarCode]</option>
      <option value="[ItemCode]">[ItemCode]</option>
      <option value="[DesignCode]">[DesignCode]</option>
      <option value="[SKUNumber]">[SKUNumber]</option>
      <option value="custom">Custom...</option>
    </select>
    <div id="bcCustomWrap" style="display:none;margin-bottom:6px">
      <input class="inp" id="bcCustomVal" placeholder="Custom value">
    </div>
    <div class="slabel">Size</div>
    <div class="pgrid" style="margin-bottom:8px">
      <span class="plabel">Width</span><input class="inp inp-sm" id="bcW" type="number" value="{{ (int)($sw['BCElementWidth'] ?? 130) }}" min="40" max="300">
      <span class="plabel">Height</span><input class="inp inp-sm" id="bcH" type="number" value="{{ (int)($sw['BCElementHeight'] ?? 42) }}" min="10" max="150">
    </div>
    <div style="display:flex;gap:5px">
      <button class="btn sm cbar" id="btnAddBar128">+ Bar128</button>
      <button class="btn sm cqr" id="btnAddQR">+ QR</button>
    </div>
  </div>
</div>

<!-- ======= CENTER ======= -->
<div class="cpanel">

  <!-- TOOLBAR -->
  <div class="toolbar">
    <div class="btn active" id="mSingle" onclick="setMode('single')">Single</div>
    <div class="btn" id="mDouble" onclick="setMode('double')">Double</div>
    <div class="tsep"></div>
    <label>W</label><input class="inp inp-sm" id="cvW" type="number" value="380" min="80" max="900" style="width:58px">
    <label>H</label><input class="inp inp-sm" id="cvH" type="number" value="160" min="50" max="600" style="width:58px">
    <div class="tsep"></div>
    <label>Cols</label><input class="inp inp-sm" id="inpCols" type="number" value="1" min="1" max="4" style="width:40px">
    <label>Rows</label><input class="inp inp-sm" id="inpRows" type="number" value="1" min="1" max="10" style="width:40px">
    <div class="tsep"></div>
    <button class="btn sm" id="btnDup">⊕ Dup</button>
    <button class="btn sm danger" id="btnDel">✕ Del</button>
    <button class="btn sm danger" id="btnClear">⊘ Clear</button>
    <div class="tsep"></div>
    <button class="btn sm" id="btnGrid">⊞ Grid</button>
    <button class="btn sm purple" id="btnCopyS1toS2">S1→S2 Copy</button>
    <button class="btn sm purple" id="btnCopyS2toS1">S2→S1 Copy</button>
  </div>

  <!-- CANVAS SWITCHER (only in double mode) -->
  <div class="cswitcher" id="cswitch" style="display:none">
    <button class="csw-btn active" id="csw1" onclick="switchCanvas(1)">
      <span>● Sticker 1</span><span class="csw-badge" id="badge1"></span>
    </button>
    <button class="csw-btn" id="csw2" onclick="switchCanvas(2)">
      <span>● Sticker 2</span><span class="csw-badge" id="badge2"></span>
    </button>
    <div style="flex:1;display:flex;align-items:center;justify-content:flex-end;padding-right:10px;gap:6px">
      <span style="font-size:10px;color:var(--muted)">BCTemplate1 / BCTemplate2</span>
    </div>
  </div>

  <!-- CANVAS AREA -->
  <div class="canvas-scroll" id="canvasScroll">
    <!-- Sticker 1 wrap -->
    <div class="canvas-wrap" id="cv1wrap">
      <div class="canvas-title" id="cv1title">Sticker 1 — BCTemplate1</div>
      <div class="canvas" id="canvas1">
        <div class="grid-ov"></div>
        <div class="copy-ind" id="copyind1"></div>
      </div>
    </div>
    <!-- Sticker 2 wrap (double mode) -->
    <div class="canvas-wrap" id="cv2wrap" style="display:none">
      <div class="canvas-title" id="cv2title">Sticker 2 — BCTemplate2</div>
      <div class="canvas" id="canvas2">
        <div class="grid-ov"></div>
        <div class="copy-ind" id="copyind2"></div>
      </div>
    </div>
  </div>
</div>

<!-- ======= RIGHT PANEL ======= -->
<div class="rpanel">
  <div class="tabs" id="rtabs">
    <div class="tab active" data-rt="element">Element</div>
    <div class="tab" data-rt="fieldsettings">Fields</div>
    <div class="tab" data-rt="models">Templates</div>
  </div>

  <!-- ELEMENT PROPS -->
  <div class="tabpane" id="rt-element">
    <div id="elProps" style="padding:4px 0">
      <div style="color:var(--muted);font-size:11px;padding:6px 2px">Select an element on the canvas to edit properties.</div>
    </div>
    <div style="margin-top:8px">
      <div class="slabel">Template Settings</div>
      <div class="pgrid">
        <span class="plabel">Printer</span>
        <select class="inp inp-sm" id="setPrinter"></select>
      </div>
      <div class="pgrid">
        <span class="plabel">Share</span>
        <input class="inp inp-sm" id="setShareName" value="">
      </div>
      <div class="pgrid">
        <span class="plabel">Printer Manual</span>
        <input class="inp inp-sm" id="setPrinterManual" value="" placeholder="eg: POS-80">
      </div>
      <div style="display:flex;gap:4px;margin-bottom:6px">
        <button class="btn xs" id="btnRefreshPrinters" style="flex:1">Refresh</button>
        <button class="btn xs" id="btnSetDefaultPrinter" style="flex:1">Set Default</button>
      </div>
      <div class="pgrid">
        <span class="plabel">Tpl Name</span>
        <input class="inp inp-sm" id="tplName" placeholder="eg: Single 24x10">
      </div>
      <div class="pgrid">
        <span class="plabel">Print Mode</span>
        <select class="inp inp-sm" id="setPrintMode">
          <option value="windows_image" selected>windows_image</option>
          <option value="windows_print">windows_print</option>
          <option value="auto">auto</option>
          <option value="bat">bat</option>
          <option value="none">none</option>
        </select>
      </div>
      <div class="pgrid">
        <span class="plabel">Offset X</span>
        <input class="inp inp-sm" id="setOffX" type="number" value="0">
      </div>
      <div class="pgrid">
        <span class="plabel">Offset Y</span>
        <input class="inp inp-sm" id="setOffY" type="number" value="0">
      </div>
    </div>
  </div>

  <!-- FIELD SETTINGS -->
  <div class="tabpane" id="rt-fieldsettings" style="display:none">
    <div style="font-size:10px;color:var(--muted);margin-bottom:6px;padding:2px">Font size &amp; rotation per field tag. Affects all matching elements.</div>
    <div style="display:grid;grid-template-columns:1fr 40px 34px 26px;gap:2px;padding:0 2px;margin-bottom:4px">
      <span style="font-size:9px;color:var(--muted);font-weight:700">TAG</span>
      <span style="font-size:9px;color:var(--muted);font-weight:700;text-align:center">SIZE</span>
      <span style="font-size:9px;color:var(--muted);font-weight:700;text-align:center">ROT</span>
      <span style="font-size:9px;color:var(--muted);font-weight:700;text-align:center">B</span>
    </div>
    <div id="fieldSettingsList"></div>
  </div>

  <!-- TEMPLATES / MODELS -->
  <div class="tabpane" id="rt-models" style="display:none">
    <div class="slabel">Save Current</div>
    <div style="display:flex;flex-direction:column;gap:5px;margin-bottom:10px">
      <input class="inp" id="saveTplName" placeholder="Template name...">
      <button class="btn sm primary" id="btnTplSave">💾 Save</button>
    </div>
    <div class="slabel">Saved Templates</div>
    <div id="tplList" style="margin-bottom:12px"></div>
    <div class="slabel">Quick Presets</div>
    <div style="display:flex;flex-direction:column;gap:4px">
      <button class="btn sm" id="btnPresetSingle">📄 Single — S/TG Label</button>
      <button class="btn sm" id="btnPresetDouble">📄 Double — 2-col Label</button>
      <button class="btn sm" id="btnPresetJewel">💍 Jewel — Design Code</button>
      <button class="btn sm" id="btnPresetFull">🏆 Full — All Fields</button>
    </div>
  </div>
</div>

</div><!-- end .main -->
<div class="toast" id="toast"></div>

<script>
// =====================================================================
// STATE
// =====================================================================
const settings = @json($settings);
const sw = @json($sw);
let mode = 'single';
let activeCV = 1; // which canvas is "active" in double mode
let showGrid = true;
let selectedEl = null;
let namedTpls = {};

// field settings: per-tag defaults for font size, rotation, bold
const ALL_TAGS = [
  '[BarCode]','[ItemCode]','[ItemName]','[DesignCode]','[DesignName]','[DesignSize]',
  '[DesignStyle]','[DesignStyleCode]','[ItemGroup]','[SubGrpCode]','[SubGrpCode2]',
  '[ShopName]','[Purity]','[MetalPurity]','[QType]','[Qty]','[Model]','[SmithCode]',
  '[SerialNo]','[HUID]','[SKUNumber]','[CertificationNo]','[ReferenceNo]',
  '[Weight]','[NetWgt]','[StWgt]','[DmdWgt]','[Weight1]','[Weight2]','[Weight3]',
  '[Amount]','[Rate]','[MetalRate]','[StPrice]','[DmdAmt]','[VA]','[MVA]','[MRP]',
  '[Stone1]','[Stone2]','[Stone3]','[Stcode1]','[Stcode2]','[Stcode3]',
  '[DmdNos]','[DmdSize]','[DmdCut]','[DmdColor]'
];

let fieldDefs = {};
ALL_TAGS.forEach(t => { fieldDefs[t] = { size: 11, rot: 0, bold: false }; });

// sample data for print preview
const SAMPLE = {
  '[BarCode]':'SC-1-911683','[ItemCode]':'ITEM001','[ItemName]':'Silver Chain',
  '[DesignCode]':'SC-001','[DesignName]':'Chain Design','[DesignSize]':'M',
  '[DesignStyle]':'Plain','[DesignStyleCode]':'PL01','[ItemGroup]':'SILVER',
  '[SubGrpCode]':'SC','[SubGrpCode2]':'SC001','[ShopName]':'Gold Shop',
  '[Purity]':'92.5','[MetalPurity]':'925','[QType]':'S/TG','[Qty]':'1',
  '[Model]':'MODEL01','[SmithCode]':'SM01','[SerialNo]':'SL0001',
  '[HUID]':'H12345','[SKUNumber]':'SKU001','[CertificationNo]':'CERT001','[ReferenceNo]':'REF001',
  '[Weight]':'17.960','[NetWgt]':'17.960','[StWgt]':'0.000','[DmdWgt]':'0.000',
  '[Weight1]':'1.200','[Weight2]':'0.800','[Weight3]':'0.500',
  '[Amount]':'1500','[Rate]':'89000','[MetalRate]':'145.00','[StPrice]':'250',
  '[DmdAmt]':'0','[VA]':'5%','[MVA]':'500','[MRP]':'1500',
  '[Stone1]':'250.00','[Stone2]':'0.00','[Stone3]':'0.00',
  '[Stcode1]':'DIA','[Stcode2]':'RUB','[Stcode3]':'',
  '[DmdNos]':'5','[DmdSize]':'0.10','[DmdCut]':'VVS','[DmdColor]':'F'
};

const cvs = {
  1: document.getElementById('canvas1'),
  2: document.getElementById('canvas2')
};

// =====================================================================
// TOAST
// =====================================================================
function toast(msg, ok=true) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast on ' + (ok ? 'ok' : 'err');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.className = 'toast', 2200);
}

// =====================================================================
// CANVAS SIZE
// =====================================================================
function updateCvSize() {
  const w = parseInt(document.getElementById('cvW').value)||380;
  const h = parseInt(document.getElementById('cvH').value)||160;
  Object.values(cvs).forEach(c => { c.style.width=w+'px'; c.style.height=h+'px'; });
}
document.getElementById('cvW').addEventListener('change', updateCvSize);
document.getElementById('cvH').addEventListener('change', updateCvSize);
updateCvSize();

// =====================================================================
// MODE (single / double)
// =====================================================================
function setMode(m) {
  mode = m;
  document.getElementById('mSingle').classList.toggle('active', m==='single');
  document.getElementById('mDouble').classList.toggle('active', m==='double');
  document.getElementById('cswitch').style.display = m==='double' ? 'flex' : 'none';
  document.getElementById('cv2wrap').style.display = m==='double' ? '' : 'none';
  document.getElementById('btnCopyS1toS2').style.display = m==='double' ? '' : 'none';
  document.getElementById('btnCopyS2toS1').style.display = m==='double' ? '' : 'none';

  if (m === 'single') {
    activeCV = 1;
    cvs[1].classList.add('active');
    cvs[2].classList.remove('active');
  } else {
    switchCanvas(activeCV || 1);
  }
  updateBadges();
}
document.getElementById('btnCopyS1toS2').style.display = 'none';
document.getElementById('btnCopyS2toS1').style.display = 'none';

// =====================================================================
// CANVAS SWITCHER
// =====================================================================
function switchCanvas(n) {
  activeCV = n;
  [1,2].forEach(i => {
    document.getElementById('csw'+i).classList.toggle('active', i===n);
    cvs[i].classList.toggle('active', i===n);
  });
}

function updateBadges() {
  [1,2].forEach(n => {
    const cnt = cvs[n].querySelectorAll('.el').length;
    const b = document.getElementById('badge'+n);
    if (b) {
      const sw = document.getElementById('csw'+n);
      if (sw) sw.classList.toggle('has-els', cnt > 0);
    }
  });
}

// =====================================================================
// LEFT TABS
// =====================================================================
document.querySelectorAll('#ltabs .tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('#ltabs .tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    ['weight','item','stone','code'].forEach(id => {
      document.getElementById('lt-'+id).style.display = id===tab.dataset.lt ? '' : 'none';
    });
  });
});

// =====================================================================
// RIGHT TABS
// =====================================================================
document.querySelectorAll('#rtabs .tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('#rtabs .tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    ['element','fieldsettings','models'].forEach(id => {
      document.getElementById('rt-'+id).style.display = id===tab.dataset.rt ? '' : 'none';
    });
    if (tab.dataset.rt === 'fieldsettings') renderFieldSettings();
    if (tab.dataset.rt === 'models') refreshTplList();
  });
});

// =====================================================================
// FIELD SETTINGS
// =====================================================================
function renderFieldSettings() {
  const c = document.getElementById('fieldSettingsList');
  c.innerHTML = '';
  ALL_TAGS.forEach(tag => {
    const fd = fieldDefs[tag] || {size:11, rot:0, bold:false};
    const row = document.createElement('div');
    row.className = 'fset-row';
    row.innerHTML = `
      <div class="fset-tag" title="${tag}">${tag}</div>
      <input class="fset-inp" type="number" min="6" max="48" value="${fd.size}" title="Font size">
      <input class="fset-inp" type="number" min="0" max="3" value="${fd.rot}" title="Rotation 0-3">
      <input type="checkbox" title="Bold" ${fd.bold?'checked':''} style="accent-color:var(--accent)">
    `;
    const [, si, ri, bi] = row.children;
    const upd = () => {
      fieldDefs[tag] = { size: parseInt(si.value)||11, rot: parseInt(ri.value)||0, bold: bi.checked };
      document.querySelectorAll('.el').forEach(el => {
        if (el.dataset.value === tag) renderElContent(el);
      });
    };
    si.addEventListener('change', upd);
    ri.addEventListener('change', upd);
    bi.addEventListener('change', upd);
    c.appendChild(row);
  });
}

// =====================================================================
// ELEMENT CREATION & RENDER
// =====================================================================
function getFD(value) {
  return fieldDefs[value] || { size: 11, rot: 0, bold: false };
}
function resolvePreviewValue(raw) {
  let s = String(raw || '');
  Object.entries(SAMPLE).forEach(([k, v]) => {
    s = s.split(k).join(String(v));
  });
  s = s.trim();
  if (!s || /\[[^\]]+\]/.test(s)) return SAMPLE['[BarCode]'] || 'SC1911683';
  return s;
}
function renderQrToContainer(container, text, w, h) {
  if (!container || typeof QRCode === 'undefined') return false;
  const target = Math.max(24, Math.min(parseInt(w) || 0, parseInt(h) || 0));
  const gen = Math.max(96, target);
  container.innerHTML = '';
  try {
    const opts = { text: String(text || ''), width: gen, height: gen, colorDark:'#000', colorLight:'#fff' };
    if (QRCode.CorrectLevel) opts.correctLevel = QRCode.CorrectLevel.L;
    new QRCode(container, opts);
    setTimeout(() => {
      const node = container.querySelector('canvas, img, table');
      if (node) {
        node.style.width = target + 'px';
        node.style.height = target + 'px';
        node.style.display = 'block';
      }
      const canvas = container.querySelector('canvas');
      const img = container.querySelector('img');
      if (canvas && img) img.remove();
    }, 120);
    return true;
  } catch (_) {
    return false;
  }
}

const ROT_DEG = [0, 90, 180, 270];

function renderElContent(el) {
  const type = el.dataset.type;
  const value = el.dataset.value || '';
  const w = parseInt(el.dataset.w) || 130;
  const h = parseInt(el.dataset.h) || 42;
  const fixed = el.dataset.fixed === '1';
  const fd = getFD(value);
  const fontSize = parseInt(el.dataset.fs) || fd.size;
  const rot = parseInt(el.dataset.rot) || fd.rot;
  const bold = el.dataset.bold === '1' || fd.bold;
  const inner = el.querySelector('.el-inner');
  inner.innerHTML = '';

  if (type === 'barcode128') {
    const svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
    svg.style.width = w+'px'; svg.style.height = h+'px';
    inner.appendChild(svg);
    try {
      const bv = resolvePreviewValue(value);
      JsBarcode(svg, bv, { format:'CODE128', width:1.4, height:h-6, displayValue:true, fontSize:8, margin:2, fontOptions:'bold' });
    } catch(e) { inner.innerHTML = `<div style="font-size:9px;padding:3px;color:#666">BAR:${value}</div>`; }
    el.style.padding = '2px';
  } else if (type === 'qrcode') {
    const div = document.createElement('div');
    div.style.width = w+'px'; div.style.height = h+'px'; div.style.overflow = 'hidden';
    inner.appendChild(div);
    const qv = resolvePreviewValue(value);
    if (!renderQrToContainer(div, qv, w, h)) {
      inner.innerHTML = `<div style="font-size:9px;padding:3px;color:#666">QR:${value}</div>`;
    }
    el.style.padding = '2px';
  } else {
    const deg = ROT_DEG[rot % 4] || 0;
    const span = document.createElement('span');
    span.style.fontSize = fontSize+'px';
    span.style.fontFamily = 'JetBrains Mono, monospace';
    span.style.fontWeight = bold ? '700' : '600';
    span.style.color = (value.startsWith('[') && value.endsWith(']')) ? '#1e3a70' : '#111';
    span.style.display = 'inline-block';
    span.textContent = value;
    if (fixed) {
      el.style.width = w + 'px';
      el.style.height = h + 'px';
      el.style.overflow = 'hidden';
    } else {
      el.style.width = '';
      el.style.height = '';
      el.style.overflow = '';
    }
    if (deg) {
      span.style.transform = `rotate(${deg}deg)`;
      span.style.transformOrigin = 'center center';
      if (!fixed && (deg === 90 || deg === 270)) {
        const approxW = value.length * fontSize * 0.62 + 8;
        el.style.height = approxW + 'px';
        el.style.width = (fontSize * 1.8) + 'px';
      }
    }
    inner.appendChild(span);
    el.style.padding = '2px 4px';
  }
}

function createEl(opts, cv) {
  const el = document.createElement('div');
  el.className = 'el ' + (opts.type==='barcode128'?'t-bar': opts.type==='qrcode'?'t-qr':'');
  el.dataset.type = opts.type || 'text';
  el.dataset.value = opts.value || '';
  el.dataset.w = opts.w || 130;
  el.dataset.h = opts.h || 42;
  el.dataset.fixed = opts.fixed ? '1' : '0';
  el.dataset.fs = opts.fs || '';
  el.dataset.rot = opts.rot !== undefined ? opts.rot : '';
  el.dataset.bold = opts.bold ? '1' : '0';
  el.style.left = (opts.x||20)+'px';
  el.style.top = (opts.y||20)+'px';
  const inner = document.createElement('div');
  inner.className = 'el-inner';
  el.appendChild(inner);
  renderElContent(el);
  el.addEventListener('mousedown', ev => startDrag(ev, el, cv));
  el.addEventListener('click', ev => { ev.stopPropagation(); selEl(el); });
  cv.appendChild(el);
  updateBadges();
  return el;
}

// =====================================================================
// DRAG
// =====================================================================
function startDrag(ev, el, cv) {
  ev.preventDefault();
  selEl(el);
  activeCV = el.parentElement === cvs[1] ? 1 : 2;
  if (mode === 'double') switchCanvas(activeCV);
  const ox = ev.clientX - (parseInt(el.style.left)||0);
  const oy = ev.clientY - (parseInt(el.style.top)||0);
  const onMove = ev2 => {
    el.style.left = Math.max(0, ev2.clientX - ox)+'px';
    el.style.top = Math.max(0, ev2.clientY - oy)+'px';
    const px = document.getElementById('epX'), py = document.getElementById('epY');
    if(px) px.value = parseInt(el.style.left)||0;
    if(py) py.value = parseInt(el.style.top)||0;
  };
  const onUp = () => { document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUp); };
  document.addEventListener('mousemove', onMove);
  document.addEventListener('mouseup', onUp);
}

// =====================================================================
// SELECT
// =====================================================================
function selEl(el) {
  document.querySelectorAll('.el').forEach(e => e.classList.remove('sel'));
  selectedEl = el;
  if (!el) { renderNoSel(); return; }
  el.classList.add('sel');
  renderElProps(el);
}

function renderNoSel() {
  document.getElementById('elProps').innerHTML = '<div style="color:var(--muted);font-size:11px;padding:6px 2px">Select an element to edit properties.</div>';
}

function renderElProps(el) {
  const isBC = el.dataset.type === 'barcode128' || el.dataset.type === 'qrcode';
  const isFixed = el.dataset.fixed === '1';
  const fd = getFD(el.dataset.value);
  const curFontSize = el.dataset.fs || fd.size;
  const curRot = el.dataset.rot !== '' ? el.dataset.rot : fd.rot;
  const curBold = el.dataset.bold === '1';
  const curW = isBC ? (el.dataset.w || 130) : (isFixed ? (el.dataset.w || 130) : '');
  const curH = isBC ? (el.dataset.h || 42) : (isFixed ? (el.dataset.h || 42) : '');
  const cvLabel = el.parentElement === cvs[1] ? '1 (BCTemplate1)' : '2 (BCTemplate2)';

  document.getElementById('elProps').innerHTML = `
    <div class="psect">
      <div class="slabel">Selected Element - Sticker ${cvLabel}</div>
      <div class="pgrid">
        <span class="plabel">Type</span>
        <input class="inp inp-sm" value="${el.dataset.type}" readonly style="opacity:.5">
      </div>
      <div class="pgrid">
        <span class="plabel">Value</span>
        <input class="inp inp-sm" id="epV" value="${(el.dataset.value||'').replace(/"/g,'&quot;')}">
      </div>
      <div class="pgrid">
        <span class="plabel">X</span>
        <input class="inp inp-sm" id="epX" type="number" value="${parseInt(el.style.left)||0}">
      </div>
      <div class="pgrid">
        <span class="plabel">Y</span>
        <input class="inp inp-sm" id="epY" type="number" value="${parseInt(el.style.top)||0}">
      </div>
      <div class="pgrid">
        <span class="plabel">Width</span>
        <input class="inp inp-sm" id="epW" type="number" min="1" max="400" value="${curW}">
      </div>
      <div class="pgrid">
        <span class="plabel">Height</span>
        <input class="inp inp-sm" id="epH" type="number" min="1" max="200" value="${curH}">
      </div>
      ${isBC ? '' : `
      <div class="pgrid">
        <span class="plabel">Font Size</span>
        <input class="inp inp-sm" id="epFS" type="number" min="6" max="72" value="${curFontSize}">
      </div>
      <div class="pgrid">
        <span class="plabel">Rotation</span>
        <select class="inp inp-sm" id="epRot">
          <option value="0" ${curRot==0?'selected':''}>0° (normal)</option>
          <option value="1" ${curRot==1?'selected':''}>90° (↻)</option>
          <option value="2" ${curRot==2?'selected':''}>180°</option>
          <option value="3" ${curRot==3?'selected':''}>270° (↺)</option>
        </select>
      </div>
      <div class="pgrid">
        <span class="plabel">Bold</span>
        <input type="checkbox" id="epBold" ${curBold?'checked':''} style="accent-color:var(--accent)">
      </div>
      `}
      <button class="btn sm primary" id="epApply" style="width:100%;margin-top:6px">Apply</button>
      <div style="display:flex;gap:4px;margin-top:4px">
        <button class="btn xs danger" id="epDel" style="flex:1">Delete</button>
        <button class="btn xs" id="epDup" style="flex:1">Duplicate</button>
        <button class="btn xs purple" id="epMove" style="flex:1">${el.parentElement===cvs[1]?'To S2':'To S1'}</button>
      </div>
    </div>
  `;

  document.getElementById('epApply').onclick = () => {
    if (!selectedEl) return;
    selectedEl.dataset.value = document.getElementById('epV').value;
    selectedEl.style.left = (document.getElementById('epX').value||0)+'px';
    selectedEl.style.top = (document.getElementById('epY').value||0)+'px';
    const wv = parseInt(document.getElementById('epW').value || 0, 10);
    const hv = parseInt(document.getElementById('epH').value || 0, 10);
    if (isBC) {
      selectedEl.dataset.w = String(wv > 0 ? wv : 130);
      selectedEl.dataset.h = String(hv > 0 ? hv : 42);
      selectedEl.dataset.fixed = '1';
    } else {
      if (wv > 0 && hv > 0) {
        selectedEl.dataset.w = String(wv);
        selectedEl.dataset.h = String(hv);
        selectedEl.dataset.fixed = '1';
      } else {
        selectedEl.dataset.fixed = '0';
      }
      selectedEl.dataset.fs = document.getElementById('epFS').value;
      selectedEl.dataset.rot = document.getElementById('epRot').value;
      selectedEl.dataset.bold = document.getElementById('epBold').checked ? '1' : '0';
    }
    renderElContent(selectedEl);
    toast('Updated ✓');
  };

  document.getElementById('epDel').onclick = () => { if(selectedEl){selectedEl.remove();selEl(null);updateBadges();} };
  document.getElementById('epDup').onclick = () => {
    if (!selectedEl) return;
    const cv = selectedEl.parentElement;
    const ne = createEl({
      type: selectedEl.dataset.type, value: selectedEl.dataset.value,
      x: (parseInt(selectedEl.style.left)||0)+14, y: (parseInt(selectedEl.style.top)||0)+14,
      w: parseInt(selectedEl.dataset.w)||130, h: parseInt(selectedEl.dataset.h)||42,
      fixed: selectedEl.dataset.fixed==='1',
      fs: selectedEl.dataset.fs, rot: selectedEl.dataset.rot, bold: selectedEl.dataset.bold==='1'
    }, cv);
    selEl(ne);
  };
  document.getElementById('epMove').onclick = () => {
    if (!selectedEl) return;
    const fromCV = selectedEl.parentElement;
    const toCV = fromCV === cvs[1] ? cvs[2] : cvs[1];
    toCV.appendChild(selectedEl);
    updateBadges();
    toast('Moved to ' + (toCV === cvs[1] ? 'Sticker 1' : 'Sticker 2'));
    renderElProps(selectedEl);
  };
}

// =====================================================================
// DRAG & DROP FROM CHIPS
// =====================================================================
document.querySelectorAll('.chip[draggable]').forEach(chip => {
  chip.addEventListener('dragstart', ev => {
    ev.dataTransfer.setData('text/plain', JSON.stringify({ type: chip.dataset.type, value: chip.dataset.value }));
  });
});

Object.entries(cvs).forEach(([n, cv]) => {
  cv.addEventListener('dragover', ev => ev.preventDefault());
  cv.addEventListener('drop', ev => {
    ev.preventDefault();
    try {
      const d = JSON.parse(ev.dataTransfer.getData('text/plain'));
      const r = cv.getBoundingClientRect();
      d.x = Math.max(0, Math.round(ev.clientX - r.left - 20));
      d.y = Math.max(0, Math.round(ev.clientY - r.top - 10));
      const bw = parseInt(document.getElementById('bcW').value)||130;
      const bh = parseInt(document.getElementById('bcH').value)||42;
      if (d.type === 'barcode128' || d.type === 'qrcode') { d.w = bw; d.h = bh; }
      activeCV = parseInt(n);
      if (mode === 'double') switchCanvas(activeCV);
      selEl(createEl(d, cv));
    } catch(e) {}
  });
  cv.addEventListener('click', ev => {
    if (ev.target === cv || ev.target.classList.contains('grid-ov')) selEl(null);
  });
});

// =====================================================================
// ADD BUTTONS
// =====================================================================
document.getElementById('btnAddTxt').onclick = () => {
  const v = document.getElementById('customTxt').value.trim();
  if (!v) return;
  const cv = mode==='double' ? cvs[activeCV] : cvs[1];
  selEl(createEl({ type:'text', value:v, x:20, y:20 }, cv));
  document.getElementById('customTxt').value = '';
};

document.getElementById('bcValSel').onchange = function() {
  document.getElementById('bcCustomWrap').style.display = this.value==='custom' ? '' : 'none';
};

function getBCV() {
  const s = document.getElementById('bcValSel').value;
  return s === 'custom' ? (document.getElementById('bcCustomVal').value||'SAMPLE') : s;
}

document.getElementById('btnAddBar128').onclick = () => {
  const cv = mode==='double' ? cvs[activeCV] : cvs[1];
  const w = parseInt(document.getElementById('bcW').value)||130, h = parseInt(document.getElementById('bcH').value)||42;
  selEl(createEl({ type:'barcode128', value:getBCV(), x:20, y:20, w, h }, cv));
};
document.getElementById('btnAddQR').onclick = () => {
  const cv = mode==='double' ? cvs[activeCV] : cvs[1];
  const s = Math.max(parseInt(document.getElementById('bcH').value)||42, 42);
  selEl(createEl({ type:'qrcode', value:getBCV(), x:20, y:20, w:s, h:s }, cv));
};

// =====================================================================
// TOOLBAR ACTIONS
// =====================================================================
document.getElementById('btnDel').onclick = () => { if(selectedEl){selectedEl.remove();selEl(null);updateBadges();} };
document.getElementById('btnDup').onclick = () => {
  if (!selectedEl) return;
  const cv = selectedEl.parentElement;
  selEl(createEl({
    type: selectedEl.dataset.type, value: selectedEl.dataset.value,
    x: (parseInt(selectedEl.style.left)||0)+14, y: (parseInt(selectedEl.style.top)||0)+14,
    w: parseInt(selectedEl.dataset.w)||130, h: parseInt(selectedEl.dataset.h)||42,
    fixed: selectedEl.dataset.fixed==='1',
    fs: selectedEl.dataset.fs, rot: selectedEl.dataset.rot, bold: selectedEl.dataset.bold==='1'
  }, cv));
};
document.getElementById('btnClear').onclick = () => {
  const cv = mode==='double' ? cvs[activeCV] : cvs[1];
  if (!confirm('Clear elements from ' + (cv===cvs[1]?'Sticker 1':'Sticker 2') + '?')) return;
  cv.querySelectorAll('.el').forEach(e => e.remove());
  selEl(null); updateBadges();
};
document.getElementById('btnGrid').onclick = () => {
  showGrid = !showGrid;
  document.querySelectorAll('.grid-ov').forEach(g => g.style.display = showGrid?'':'none');
};

// COPY S1→S2 / S2→S1
function copyCv(from, to) {
  if (!confirm('This will REPLACE all elements in Sticker ' + (to===cvs[1]?'1':'2') + '. Continue?')) return;
  to.querySelectorAll('.el').forEach(e => e.remove());
  from.querySelectorAll('.el').forEach(el => {
    createEl({
      type: el.dataset.type, value: el.dataset.value,
      x: parseInt(el.style.left)||0, y: parseInt(el.style.top)||0,
      w: parseInt(el.dataset.w)||130, h: parseInt(el.dataset.h)||42,
      fixed: el.dataset.fixed==='1',
      fs: el.dataset.fs, rot: el.dataset.rot, bold: el.dataset.bold==='1'
    }, to);
  });
  updateBadges();
  toast('Copied ✓');
}
document.getElementById('btnCopyS1toS2').onclick = () => copyCv(cvs[1], cvs[2]);
document.getElementById('btnCopyS2toS1').onclick = () => copyCv(cvs[2], cvs[1]);

// =====================================================================
// KEYBOARD
// =====================================================================
document.addEventListener('keydown', ev => {
  if (ev.target.tagName==='INPUT'||ev.target.tagName==='SELECT'||ev.target.tagName==='TEXTAREA') return;
  if ((ev.key==='Delete'||ev.key==='Backspace') && selectedEl) { selectedEl.remove(); selEl(null); updateBadges(); }
  if (ev.key==='Escape') selEl(null);
  if (!selectedEl) return;
  const step = ev.shiftKey ? 5 : 1;
  if (ev.key==='ArrowLeft')  { selectedEl.style.left = Math.max(0,(parseInt(selectedEl.style.left)||0)-step)+'px'; ev.preventDefault(); }
  if (ev.key==='ArrowRight') { selectedEl.style.left = ((parseInt(selectedEl.style.left)||0)+step)+'px'; ev.preventDefault(); }
  if (ev.key==='ArrowUp')    { selectedEl.style.top = Math.max(0,(parseInt(selectedEl.style.top)||0)-step)+'px'; ev.preventDefault(); }
  if (ev.key==='ArrowDown')  { selectedEl.style.top = ((parseInt(selectedEl.style.top)||0)+step)+'px'; ev.preventDefault(); }
});

// =====================================================================
// SERIALIZE / DESERIALIZE
// =====================================================================
function serialize() {
  const out = {
    mode, fieldDefs,
    canvasW: document.getElementById('cvW').value,
    canvasH: document.getElementById('cvH').value,
    cols: document.getElementById('inpCols').value,
    rows: document.getElementById('inpRows').value,
    printMode: document.getElementById('setPrintMode').value,
    printerName: getPrinterName(),
    printerShareName: document.getElementById('setShareName').value,
    offsetX: document.getElementById('setOffX').value,
    offsetY: document.getElementById('setOffY').value,
    tplName: document.getElementById('tplName').value,
    sticker1: [], sticker2: []
  };
  [1,2].forEach(n => {
    cvs[n].querySelectorAll('.el').forEach(el => {
      out['sticker'+n].push({
        type: el.dataset.type, value: el.dataset.value,
        x: parseInt(el.style.left)||0, y: parseInt(el.style.top)||0,
        w: parseInt(el.dataset.w)||130, h: parseInt(el.dataset.h)||42,
        fixed: el.dataset.fixed==='1',
        fs: parseInt(el.dataset.fs)||0, rot: parseInt(el.dataset.rot)||0,
        bold: el.dataset.bold==='1'
      });
    });
  });
  return out;
}

function deserialize(data) {
  if (!data) return;
  if (typeof data==='string') { try { data=JSON.parse(data); } catch { return; } }
  [cvs[1],cvs[2]].forEach(cv => cv.querySelectorAll('.el').forEach(e => e.remove()));
  if (data.fieldDefs) Object.assign(fieldDefs, data.fieldDefs);
  if (data.canvasW !== undefined) document.getElementById('cvW').value = data.canvasW;
  if (data.canvasH !== undefined) document.getElementById('cvH').value = data.canvasH;
  updateCvSize();
  if (data.cols !== undefined) document.getElementById('inpCols').value = data.cols;
  if (data.rows !== undefined) document.getElementById('inpRows').value = data.rows;
  if (data.printMode !== undefined) document.getElementById('setPrintMode').value = data.printMode;
  if (data.printerName !== undefined) {
    document.getElementById('setPrinter').value = data.printerName;
    const pm = document.getElementById('setPrinterManual');
    if (pm) pm.value = data.printerName;
  }
  if (data.printerShareName !== undefined) document.getElementById('setShareName').value = data.printerShareName;
  if (data.offsetX !== undefined) document.getElementById('setOffX').value = data.offsetX;
  if (data.offsetY !== undefined) document.getElementById('setOffY').value = data.offsetY;
  if (data.tplName !== undefined) document.getElementById('tplName').value = data.tplName;
  (data.sticker1||[]).forEach(i => createEl(i, cvs[1]));
  (data.sticker2||[]).forEach(i => createEl(i, cvs[2]));
  setMode(data.mode==='double' ? 'double' : 'single');
  selEl(null); updateBadges();
}

// =====================================================================
// TEMPLATE SAVE / LOAD
// =====================================================================
function refreshTplList() {
  const c = document.getElementById('tplList');
  const keys = Object.keys(namedTpls).sort();
  if (!keys.length) { c.innerHTML = '<div style="color:var(--muted);font-size:10px;padding:3px 0">No saved templates.</div>'; return; }
  c.innerHTML = '';
  keys.forEach(k => {
    const d = namedTpls[k];
    const row = document.createElement('div');
    row.className = 'smodel-row';
    row.innerHTML = `
      <div>
        <div class="smodel-name">${k}</div>
        <div class="smodel-meta">${d.mode||'single'} · S1:${(d.sticker1||[]).length} S2:${(d.sticker2||[]).length} els</div>
      </div>
      <div class="smodel-actions">
        <button class="btn xs success" data-load="${k}">Load</button>
        <button class="btn xs danger" data-del="${k}">✕</button>
      </div>
    `;
    row.querySelector('[data-load]').onclick = (ev) => {
      ev.stopPropagation();
      deserialize(namedTpls[k]);
      document.getElementById('saveTplName').value = k;
      toast('Loaded: '+k);
    };
    row.querySelector('[data-del]').onclick = (ev) => {
      ev.stopPropagation();
      if (!confirm('Delete "'+k+'"?')) return;
      delete namedTpls[k];
      refreshTplList();
      toast('Deleted: '+k);
    };
    row.onclick = () => {
      deserialize(namedTpls[k]);
      document.getElementById('saveTplName').value = k;
      toast('Loaded: '+k);
    };
    c.appendChild(row);
  });
}

document.getElementById('btnTplSave').onclick = () => {
  const n = document.getElementById('saveTplName').value.trim();
  if (!n) { toast('Enter template name', false); return; }
  namedTpls[n] = serialize();
  refreshTplList();
  toast('Saved: '+n);
};

// =====================================================================
// PRESETS
// =====================================================================
document.getElementById('btnPresetSingle').onclick = () => {
  deserialize({
    mode:'single', canvasW:320, canvasH:92, cols:1, rows:1,
    sticker1:[
      {type:'text',value:'GW:[Weight]',x:44,y:8,fs:10,rot:0},
      {type:'text',value:'ST:[StWgt]',x:44,y:25,fs:10,rot:0},
      {type:'text',value:'NW:[NetWgt]',x:44,y:42,fs:10,rot:0},
      {type:'text',value:'RS:[MetalRate]',x:44,y:59,fs:10,rot:0},
      {type:'barcode128',value:'[BarCode]',x:148,y:8,w:138,h:42},
      {type:'text',value:'[BarCode]',x:178,y:55,fs:8,rot:0},
      {type:'text',value:'[ItemCode]',x:174,y:69,fs:8,rot:0}
    ], sticker2:[]
  });
  toast('Slim tag preset loaded');
};

document.getElementById('btnPresetDouble').onclick = () => {
  const s = [
    {type:'barcode128',value:'[BarCode]',x:10,y:10,w:130,h:52},
    {type:'text',value:'[ItemCode]',x:10,y:68,fs:9,rot:0},
    {type:'text',value:'[QType]',x:146,y:10,fs:9,rot:1},
    {type:'text',value:'GW:[Weight]',x:158,y:10,fs:10,rot:0},
    {type:'text',value:'SW:[StWgt]',x:158,y:32,fs:10,rot:0},
    {type:'text',value:'NW:[NetWgt]',x:158,y:54,fs:10,rot:0}
  ];
  deserialize({ mode:'double', canvasW:320, canvasH:110, cols:2, rows:3, sticker1:s, sticker2: JSON.parse(JSON.stringify(s)) });
  toast('Double preset loaded');
};

document.getElementById('btnPresetJewel').onclick = () => {
  deserialize({
    mode:'single', canvasW:400, canvasH:180, cols:1, rows:4,
    sticker1:[
      {type:'text',value:'[DesignCode]',x:8,y:14,fs:9,rot:1},
      {type:'text',value:'[Purity]',x:8,y:90,fs:9,rot:1},
      {type:'text',value:'[VA]',x:8,y:150,fs:9,rot:1},
      {type:'text',value:'GW:[Weight]',x:72,y:12,fs:11,rot:0},
      {type:'text',value:'ST:[StWgt]',x:72,y:38,fs:11,rot:0},
      {type:'text',value:'NW:[NetWgt]',x:72,y:64,fs:11,rot:0},
      {type:'text',value:'RS.[MetalRate]',x:72,y:90,fs:11,rot:0},
      {type:'barcode128',value:'[DesignCode]',x:220,y:16,w:160,h:90},
      {type:'text',value:'[DesignCode]',x:238,y:112,fs:10,rot:0}
    ], sticker2:[]
  });
  toast('Jewel design code preset loaded');
};

document.getElementById('btnPresetFull').onclick = () => {
  deserialize({
    mode:'double', canvasW:360, canvasH:200, cols:1, rows:4,
    sticker1:[
      {type:'text',value:'[QType]',x:6,y:10,fs:8,rot:1},
      {type:'text',value:'[VA]',x:6,y:80,fs:8,rot:1},
      {type:'text',value:'[DesignCode]',x:6,y:150,fs:8,rot:1},
      {type:'text',value:'GW:[Weight]',x:60,y:10,fs:10,rot:0},
      {type:'text',value:'ST:[StWgt]',x:60,y:32,fs:10,rot:0},
      {type:'text',value:'NW:[NetWgt]',x:60,y:54,fs:10,rot:0},
      {type:'text',value:'RS.[MetalRate]',x:60,y:76,fs:10,rot:0},
      {type:'text',value:'[Stcode1]:[Stone1]',x:60,y:100,fs:9,rot:0},
      {type:'text',value:'[Stcode2]:[Stone2]',x:60,y:118,fs:9,rot:0},
      {type:'text',value:'HUID:[HUID]',x:60,y:136,fs:9,rot:0},
      {type:'barcode128',value:'[BarCode]',x:206,y:14,w:140,h:70},
      {type:'text',value:'[BarCode]',x:216,y:90,fs:9,rot:0},
      {type:'text',value:'[ItemName]',x:60,y:156,fs:9,rot:0},
      {type:'qrcode',value:'[BarCode]',x:210,y:100,w:80,h:80}
    ],
    sticker2:[
      {type:'text',value:'[QType]',x:6,y:10,fs:8,rot:1},
      {type:'text',value:'GW:[Weight]',x:60,y:10,fs:10,rot:0},
      {type:'text',value:'ST:[StWgt]',x:60,y:32,fs:10,rot:0},
      {type:'text',value:'NW:[NetWgt]',x:60,y:54,fs:10,rot:0},
      {type:'barcode128',value:'[BarCode]',x:206,y:14,w:140,h:70},
      {type:'text',value:'[BarCode]',x:216,y:90,fs:9,rot:0}
    ]
  });
  toast('Full preset (both stickers) loaded');
};

// =====================================================================
// EXPORT / IMPORT
// =====================================================================
document.getElementById('btnExport').onclick = () => {
  const data = { version:2, template:serialize(), namedTemplates:namedTpls, fieldDefs };
  const b = new Blob([JSON.stringify(data,null,2)],{type:'application/json'});
  const u = URL.createObjectURL(b), a = document.createElement('a');
  a.href=u; a.download='sticker-template.json'; a.click(); URL.revokeObjectURL(u);
  toast('Exported ✓');
};

document.getElementById('btnImport').onclick = () => {
  const inp = document.createElement('input'); inp.type='file'; inp.accept='.json';
  inp.onchange = () => {
    const f = inp.files[0]; if(!f) return;
    const r = new FileReader();
    r.onload = () => {
      try {
        const d = JSON.parse(r.result);
        if (d.namedTemplates) Object.assign(namedTpls, d.namedTemplates);
        if (d.fieldDefs) Object.assign(fieldDefs, d.fieldDefs);
        if (d.template) deserialize(d.template);
        else deserialize(d);
        refreshTplList();
        toast('Imported ✓');
      } catch(e) { toast('Invalid file', false); }
    };
    r.readAsText(f);
  };
  inp.click();
};

// =====================================================================
// SAVE (to server or local)
// =====================================================================
async function persistDesignerSettings(successMessage) {
  const data = serialize();
  const name = document.getElementById('tplName').value.trim() || 'default';
  namedTpls[name] = data;
  refreshTplList();
  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const payload = JSON.parse(JSON.stringify(settings || {}));
    payload.Software = payload.Software || {};
    payload.Software.BPrintType = 'TemplateDesigner';
    payload.Software.BCRenderAsImage = 'Y';
    payload.Software.BCDesignerTemplate = JSON.stringify(data);
    payload.Software.BCDesignerTemplates = JSON.stringify(namedTpls || {});
    payload.Software.BCStickerMode = String(data.mode || 'single');
    payload.Software.BCDesignerColumns = String(data.cols || '1');
    payload.Software.BCDesignerTextRows = String(data.rows || '1');
    payload.Software.BCPrintMode = String(data.printMode || 'windows_image');
    payload.Software.BCPrinterName = String(data.printerName || '');
    payload.Software.BCPrinterShareName = String(data.printerShareName || '');
    payload.Software.BCPXINC = String(data.offsetX || '0');
    payload.Software.BCPYINC = String(data.offsetY || '0');
    payload.Software.BCDesignerTemplateName = String(name);
    payload.Software.BCElementWidth = String(parseInt(document.getElementById('bcW').value) || 130);
    payload.Software.BCElementHeight = String(parseInt(document.getElementById('bcH').value) || 42);

    const r = await fetch(@json(url('/barcode-single-entry/settings/save')), {
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
      body:JSON.stringify({ settings: payload })
    });
    if (r.ok) {
      const d = await r.json();
      if (d.success) {
        toast(successMessage || 'Saved to server');
        return true;
      }
    }
  } catch(e) {}
  toast('Saved locally (server offline)');
  return false;
}

document.getElementById('btnSave').onclick = async () => {
  await persistDesignerSettings('Template saved');
};

// =====================================================================
// PRINTERS
// =====================================================================
async function loadPrinters() {
  const sel = document.getElementById('setPrinter');
  const share = document.getElementById('setShareName');
  const manual = document.getElementById('setPrinterManual');
  if (!sel) return;
  sel.innerHTML = '<option value="">Default Printer</option>';
  try {
    const r = await fetch(@json(url('/barcode-single-entry/settings/printers')), { headers:{Accept:'application/json'} });
    const d = await r.json();
    const list = d.data || [];
    const def = list.find(p => p && p.isDefault);
    if (def && def.name) {
      sel.innerHTML = `<option value="">Default Printer (System: ${def.name})</option>`;
    }
    list.forEach(p => {
      if (!p || !p.name) return;
      const o = document.createElement('option');
      o.value = p.name;
      o.dataset.shareName = p.shareName || '';
      const core = (p.shareName ? p.shareName + ' (' + p.name : p.name) + (p.portName ? ' | ' + p.portName : '') + (p.shareName ? ')' : '');
      o.textContent = p.isDefault ? core + ' [Default]' : core;
      sel.appendChild(o);
    });
  } catch(e) {}
  if (sw.BCPrinterName) {
    sel.value = sw.BCPrinterName;
    if (manual) manual.value = sw.BCPrinterName;
  }
  if (share && sw.BCPrinterShareName) share.value = sw.BCPrinterShareName;
  sel.onchange = () => {
    const o = sel.options[sel.selectedIndex];
    if (o && o.dataset) share.value = o.dataset.shareName || '';
    if (manual && o && o.value) manual.value = o.value;
  };
}
function getPrinterName() {
  const manual = (document.getElementById('setPrinterManual')?.value || '').trim();
  if (manual) return manual;
  return (document.getElementById('setPrinter')?.value || '').trim();
}
document.getElementById('btnRefreshPrinters').onclick = loadPrinters;
document.getElementById('btnSetDefaultPrinter').onclick = async () => {
  const printerName = getPrinterName();
  const shareName = document.getElementById('setShareName').value || '';
  if (!printerName) { toast('Select printer first', false); return; }
  const tplName = document.getElementById('tplName');
  if (tplName && !tplName.value.trim()) {
    tplName.value = 'Honeywell Slim Tag';
  }
  try {
    const r = await fetch(@json(url('/barcode-single-entry/settings/default-printer')), {
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':(document.querySelector('meta[name="csrf-token"]')?.content||'')},
      body:JSON.stringify({ printerName, shareName })
    });
    const d = await r.json();
    if (!r.ok || !d.success) throw new Error(d.message || 'Failed');
    await persistDesignerSettings('Default printer and current template saved');
  } catch(e) { toast(e.message || 'Failed', false); }
};

// =====================================================================
// PRINT SAMPLE / JPG
// =====================================================================
function replaceAll(v) {
  let s = v;
  Object.entries(SAMPLE).forEach(([k,sv]) => {
    s = s.split(k).join(sv);
  });
  return s;
}

function openSamplePreview() {
  const w = parseInt(document.getElementById('cvW').value)||380;
  const h = parseInt(document.getElementById('cvH').value)||160;
  const cols = Math.min(4, parseInt(document.getElementById('inpCols').value)||1);
  const rows = Math.min(6, parseInt(document.getElementById('inpRows').value)||1);

  function buildLabelHTML(stickerN) {
    let html = `<div style="position:relative;width:${w}px;height:${h}px;background:#fff;border:1px solid #ccc;overflow:hidden;display:inline-block;margin:2px">`;
    cvs[stickerN].querySelectorAll('.el').forEach((el,idx) => {
      const type = el.dataset.type;
      const rawVal = el.dataset.value||'';
      const val = replaceAll(rawVal);
      const x = parseInt(el.style.left)||0;
      const y = parseInt(el.style.top)||0;
      const ew = parseInt(el.dataset.w)||130;
      const eh = parseInt(el.dataset.h)||42;
      const fd = getFD(rawVal);
      const fs = parseInt(el.dataset.fs)||fd.size;
      const rot = parseInt(el.dataset.rot)||fd.rot;
      const bold = el.dataset.bold==='1'||fd.bold;
      const deg = ROT_DEG[rot%4]||0;
      const bid = `pbc_${stickerN}_${idx}_${Date.now()}`;

      if (type==='barcode128') {
        html += `<div style="position:absolute;left:${x}px;top:${y}px">
          <svg id="${bid}" data-code="${String(val).replace(/"/g,'&quot;')}" width="${ew}" height="${eh}"></svg>
          <div style="font-size:8px;text-align:center;font-family:monospace;margin-top:1px">${val}</div>
        </div>`;
      } else if (type==='qrcode') {
        html += `<div id="${bid}" data-code="${String(val).replace(/"/g,'&quot;')}" style="position:absolute;left:${x}px;top:${y}px;width:${ew}px;height:${eh}px"></div>`;
      } else {
        const rotStyle = deg ? `transform:rotate(${deg}deg);transform-origin:center center;display:inline-block;` : '';
        html += `<div style="position:absolute;left:${x}px;top:${y}px;font-size:${fs}px;font-family:JetBrains Mono,monospace;font-weight:${bold?700:600};${rotStyle}">${val}</div>`;
      }
    });
    html += '</div>';
    return html;
  }

  let printContent = '';
  for (let r=0;r<rows;r++) {
    for (let c=0;c<cols;c++) {
      const sn = (mode==='double' && c%2===1) ? 2 : 1;
      printContent += buildLabelHTML(sn);
    }
    printContent += '<br>';
  }

  // collect barcode/qr IDs
  const barcodeEls = [], qrEls = [];
  cvs[1].querySelectorAll('.el').forEach((el,i) => {
    if (el.dataset.type==='barcode128') barcodeEls.push({id:`pbc_1_${i}`, val:replaceAll(el.dataset.value), w:el.dataset.w, h:el.dataset.h});
    if (el.dataset.type==='qrcode') qrEls.push({id:`pbc_1_${i}`, val:replaceAll(el.dataset.value), w:el.dataset.w, h:el.dataset.h});
  });

  const pw = window.open('','_blank','width=1000,height=700');
  pw.document.write(`<!DOCTYPE html><html><head>
    <title>Print Sample</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"><\/script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>
    <style>
      body{margin:10px;background:#f5f5f5;font-family:'JetBrains Mono',monospace}
      .info{font-size:11px;color:#666;margin-bottom:10px;font-family:sans-serif}
      @media print{body{margin:2mm;background:white}.info{display:none}@page{margin:4mm}}
    </style>
  
</head><body>
    <div class="info">
      <b>Sample Print Preview</b> — Mode: ${mode} | ${cols}×${rows} labels | 
      Sticker 1: ${cvs[1].querySelectorAll('.el').length} els | Sticker 2: ${cvs[2].querySelectorAll('.el').length} els
      <button onclick="window.print()" style="margin-left:10px;padding:4px 10px;cursor:pointer">🖨 Print</button>
    </div>
    ${printContent}
    <script>
      document.querySelectorAll('[id^="pbc_"]').forEach(el=>{
        if(el.tagName==='svg'){
          try{
            const sv=el.getAttribute('data-code')||${JSON.stringify(SAMPLE['[BarCode]'])};
            JsBarcode(el,sv,{format:'CODE128',width:1.4,height:parseInt(el.getAttribute('height'))-8,displayValue:false,margin:2});
          }catch(e){}
        } else {
          try{
            const tw=parseInt(el.style.width)||60, th=parseInt(el.style.height)||60;
            const tg=Math.max(24,Math.min(tw,th)), gen=Math.max(96,tg);
            new QRCode(el,{text:(el.getAttribute('data-code')||${JSON.stringify(SAMPLE['[BarCode]'])}),width:gen,height:gen,colorDark:'#000',colorLight:'#fff',correctLevel:(QRCode.CorrectLevel?QRCode.CorrectLevel.L:undefined)});
            setTimeout(()=>{const node=el.querySelector('canvas, img, table');if(node){node.style.width=tg+'px';node.style.height=tg+'px';node.style.display='block';}const canvas=el.querySelector('canvas');const img=el.querySelector('img');if(canvas&&img)img.remove();},120);
          }catch(e){}
        }
      });
    <\/script>
  </body></html>`);
  pw.document.close();
  toast('Print preview opened');
}

async function buildJpgFromCurrentTemplate() {
  const w = parseInt(document.getElementById('cvW').value)||380;
  const h = parseInt(document.getElementById('cvH').value)||160;
  const cols = Math.min(4, parseInt(document.getElementById('inpCols').value)||1);
  const rows = Math.min(6, parseInt(document.getElementById('inpRows').value)||1);
  const host = document.createElement('div');
  host.style.position = 'fixed';
  host.style.left = '-99999px';
  host.style.top = '0';
  host.style.background = '#fff';
  host.style.padding = '4px';
  host.style.zIndex = '-1';
  document.body.appendChild(host);
  for (let r=0; r<rows; r++) {
    const row = document.createElement('div');
    row.style.whiteSpace = 'nowrap';
    for (let c=0; c<cols; c++) {
      const sn = (mode==='double' && c%2===1) ? 2 : 1;
      const card = document.createElement('div');
      card.style.position='relative';
      card.style.width = w+'px';
      card.style.height = h+'px';
      card.style.background = '#fff';
      card.style.border = '1px solid #ccc';
      card.style.overflow = 'hidden';
      card.style.display = 'inline-block';
      card.style.margin = '2px';
      cvs[sn].querySelectorAll('.el').forEach((el,idx) => {
        const type = el.dataset.type;
        const rawVal = el.dataset.value || '';
        const val = replaceAll(rawVal);
        const x = parseInt(el.style.left)||0, y = parseInt(el.style.top)||0;
        const ew = parseInt(el.dataset.w)||130, eh = parseInt(el.dataset.h)||42;
        const fd = getFD(rawVal);
        const fs = parseInt(el.dataset.fs)||fd.size;
        const rot = parseInt(el.dataset.rot)||fd.rot;
        const bold = el.dataset.bold==='1'||fd.bold;
        const deg = ROT_DEG[rot%4]||0;
        if (type==='barcode128') {
          const wrap = document.createElement('div');
          wrap.style.position='absolute'; wrap.style.left=x+'px'; wrap.style.top=y+'px';
          const svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
          svg.setAttribute('width', String(ew)); svg.setAttribute('height', String(eh));
          try { JsBarcode(svg, val || SAMPLE['[BarCode]'], { format:'CODE128', width:1.4, height:Math.max(18, eh-8), displayValue:false, margin:2 }); } catch(e) {}
          const n = document.createElement('div'); n.style.fontSize='8px'; n.style.textAlign='center'; n.style.fontFamily='monospace'; n.style.marginTop='1px'; n.textContent = val;
          wrap.appendChild(svg); wrap.appendChild(n); card.appendChild(wrap);
        } else if (type==='qrcode') {
          const q = document.createElement('div');
          q.style.position='absolute'; q.style.left=x+'px'; q.style.top=y+'px'; q.style.width=ew+'px'; q.style.height=eh+'px';
          try {
            const tg=Math.max(24,Math.min(ew,eh)), gen=Math.max(96,tg);
            new QRCode(q, { text: val || SAMPLE['[BarCode]'], width: gen, height: gen, colorDark:'#000', colorLight:'#fff', correctLevel:(QRCode.CorrectLevel?QRCode.CorrectLevel.L:undefined) });
            setTimeout(()=>{const node=q.querySelector('canvas, img, table');if(node){node.style.width=tg+'px';node.style.height=tg+'px';node.style.display='block';}const canvas=q.querySelector('canvas');const img=q.querySelector('img');if(canvas&&img)img.remove();},120);
          } catch(e) {}
          card.appendChild(q);
        } else {
          const d = document.createElement('div');
          d.style.position='absolute'; d.style.left=x+'px'; d.style.top=y+'px'; d.style.fontSize=fs+'px';
          d.style.fontFamily='JetBrains Mono,monospace'; d.style.fontWeight=bold?'700':'600';
          if (deg) { d.style.transform=`rotate(${deg}deg)`; d.style.transformOrigin='center center'; d.style.display='inline-block'; }
          d.textContent = val;
          card.appendChild(d);
        }
      });
      row.appendChild(card);
    }
    host.appendChild(row);
  }
  await new Promise(res=>setTimeout(res,150));
  const canvas = await html2canvas(host, { backgroundColor:'#ffffff', scale:2, useCORS:true });
  document.body.removeChild(host);
  return canvas;
}

document.getElementById('btnSaveJpg').onclick = async () => {
  try {
    const canvas = await buildJpgFromCurrentTemplate();
    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/jpeg', 0.95);
    a.download = 'sticker-label.jpg';
    a.click();
    toast('JPG saved');
  } catch(e) { toast('JPG save failed', false); }
};

async function browserPrintJpg() {
  try {
    const canvas = await buildJpgFromCurrentTemplate();
    const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) throw new Error('Popup blocked. Allow popups to print the JPG.');
    w.document.open();
    w.document.write(`<!DOCTYPE html>
<html>
<head>
  <title>Print Sticker JPG</title>
  <style>
    html,body{margin:0;padding:0;background:#fff}
    body{display:flex;align-items:flex-start;justify-content:center;padding:16px}
    img{max-width:100%;height:auto;display:block}
    @media print{
      body{padding:0}
      img{max-width:none;width:auto}
    }
  </style>
</head>
<body>
  <img src="${dataUrl}" alt="Sticker JPG">
  <script>
    window.addEventListener('load', function(){
      setTimeout(function(){ window.print(); }, 150);
    });
    window.addEventListener('afterprint', function(){ window.close(); });
  <\/script>
</body>
</html>`);
    w.document.close();
    toast('JPG opened for printing');
  } catch(e) {
    toast(e.message || 'JPG print failed', false);
  }
}

async function directServerPrint() {
  try {
    const tpl = serialize();
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const r = await fetch(@json(url('/api/barcode-entry/print-sample')), {
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
      body:JSON.stringify({
        printType: 'TemplateDesigner',
        printMode: document.getElementById('setPrintMode').value || 'windows_image',
        printerName: getPrinterName(),
        printerShareName: document.getElementById('setShareName').value || '',
        stickerMode: tpl.mode || 'single',
        designerColumns: String(tpl.cols || '1'),
        designerTemplate: JSON.stringify(tpl),
        renderAsImage: 'N',
        pxinc: document.getElementById('setOffX').value || '0',
        pyinc: document.getElementById('setOffY').value || '0',
        sampleText: 'Sample Barcode Label'
      })
    });
    const d = await r.json();
    if (!r.ok || !d.success) throw new Error(d.message || 'Direct print failed');
    toast('Print job sent to printer');
  } catch(e) {
    toast(e.message || 'Direct print failed', false);
  }
}

document.getElementById('btnPrintJpg').onclick = browserPrintJpg;
document.getElementById('btnDirectPrintJpg').onclick = directServerPrint;
document.getElementById('btnPrintSample').onclick = openSamplePreview;

// =====================================================================
// INIT
// =====================================================================
try {
  const savedTemplates = JSON.parse(sw.BCDesignerTemplates || '{}');
  if (savedTemplates && typeof savedTemplates === 'object') namedTpls = savedTemplates;
} catch(e) {}
try {
  if (sw.BCDesignerTemplate) deserialize(sw.BCDesignerTemplate);
} catch(e) {}
if (sw.BCDesignerTemplateName) {
  const n1 = document.getElementById('tplName');
  const n2 = document.getElementById('saveTplName');
  if (n1) n1.value = sw.BCDesignerTemplateName;
  if (n2) n2.value = sw.BCDesignerTemplateName;
}
if (sw.BCPrintMode) {
  const pm = document.getElementById('setPrintMode');
  if (pm) pm.value = sw.BCPrintMode;
}
if (sw.BCPrinterName) {
  const pn = document.getElementById('setPrinter');
  if (pn) pn.value = sw.BCPrinterName;
  const pm = document.getElementById('setPrinterManual');
  if (pm) pm.value = sw.BCPrinterName;
}
if (sw.BCPrinterShareName) {
  const ps = document.getElementById('setShareName');
  if (ps) ps.value = sw.BCPrinterShareName;
}
if (sw.BCPXINC) document.getElementById('setOffX').value = sw.BCPXINC;
if (sw.BCPYINC) document.getElementById('setOffY').value = sw.BCPYINC;
if (sw.BCElementWidth) document.getElementById('bcW').value = sw.BCElementWidth;
if (sw.BCElementHeight) document.getElementById('bcH').value = sw.BCElementHeight;
setMode(sw.BCStickerMode === 'double' ? 'double' : 'single');
refreshTplList();
loadPrinters();
renderNoSel();
updateBadges();
toast('Ready - Drag fields to Sticker 1 or Sticker 2 canvas!');
</script>
</body>
</html>
