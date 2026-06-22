<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Application Settings</title>
<style>
*{box-sizing:border-box}body{margin:0;font:13px "Segoe UI",Tahoma,sans-serif;background:#eef1f5;color:#1f2937}
.page{max-width:1420px;margin:0 auto;padding:18px}.head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:16px}
.title{display:flex;gap:12px}.icon{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#d6b156,#8b6a20);display:flex;align-items:center;justify-content:center;color:#fff}.title h1{margin:0;font-size:22px}.sub{margin:4px 0 0;color:#6b7280;font-size:12px;max-width:820px;line-height:1.5}
.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{border:0;border-radius:10px;padding:10px 16px;font:inherit;font-weight:600;cursor:pointer}.btn-primary{background:linear-gradient(135deg,#d6b156,#8b6a20);color:#fff}.btn-secondary{background:#fff;border:1px solid #d5dbe5;color:#374151}.btn:disabled{opacity:.65;cursor:not-allowed}
.layout{display:grid;grid-template-columns:240px 1fr;gap:16px}.rail,.panel{background:#fff;border:1px solid #d8dee8;border-radius:18px;box-shadow:0 10px 24px rgba(15,23,42,.05)}.rail{padding:14px}.rail h2{margin:0 0 4px;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#8b6a20}.rail p{margin:0 0 12px;color:#6b7280;font-size:12px;line-height:1.45}
.tab-btn{width:100%;text-align:left;border:1px solid transparent;background:transparent;border-radius:12px;padding:11px 12px;margin-bottom:6px;cursor:pointer;color:#374151}.tab-btn:hover{background:#f6f8fb}.tab-btn.active{background:linear-gradient(135deg,#fff8e6,#f4ead0);border-color:#e8d4a2;color:#6f5215}.tab-btn strong{display:block;font-size:13px}.tab-btn span{display:block;font-size:11px;color:#6b7280;margin-top:2px}
.panel{padding:18px}.tab-panel{display:none}.tab-panel.active{display:block}.hero{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid #edf1f5}.hero h3{margin:0;font-size:18px}.hero p{margin:6px 0 0;color:#6b7280;max-width:760px;line-height:1.5}.badge{padding:8px 10px;border-radius:10px;background:#fff7e2;color:#7c5b13;border:1px solid #efdca7;font-size:11px;font-weight:600}
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}.card{grid-column:span 12;background:#fbfcfe;border:1px solid #e6eaf0;border-radius:16px;overflow:hidden}.span-6{grid-column:span 6}.span-4{grid-column:span 4}.span-8{grid-column:span 8}
.card-head{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:12px 14px;background:#fff;border-bottom:1px solid #edf1f5}.card-head h4{margin:0;font-size:13px}.note{font-size:11px;color:#6b7280}
.card-body{padding:14px}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.field{display:block}.field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;margin-bottom:6px}
.field input,.field textarea,.field select{width:100%;border:1px solid #d8dee8;background:#fff;border-radius:10px;padding:10px 11px;font:inherit;color:#111827}.field textarea{min-height:88px;resize:vertical}.field input:focus,.field textarea:focus,.field select:focus{outline:none;border-color:#caa13d;box-shadow:0 0 0 3px rgba(202,161,61,.12)}
.bool-row{display:flex;align-items:center;gap:10px;border:1px solid #d8dee8;border-radius:10px;background:#fff;padding:10px 11px;min-height:42px}.bool-row input{width:16px;height:16px}.bool-row span{font-size:13px;color:#111827}
.logo-zone{border:2px dashed #d8dee8;border-radius:14px;padding:20px;text-align:center;background:#fff;cursor:pointer}.logo-zone:hover,.logo-zone.drag-over{border-color:#caa13d;background:#fffdf7}.logo-placeholder{width:92px;height:92px;border-radius:16px;background:#f4f6fa;border:1px solid #e5e7eb;display:inline-flex;align-items:center;justify-content:center;color:#9ca3af;margin-bottom:12px}.logo-preview-wrap{display:inline-block;position:relative;margin-bottom:12px}.logo-preview{width:112px;height:112px;object-fit:contain;border:1px solid #e5e7eb;border-radius:16px;background:#fff;padding:8px}.logo-remove{position:absolute;top:-8px;right:-8px;width:24px;height:24px;border-radius:50%;border:0;background:#ef4444;color:#fff;cursor:pointer}#logoFile{display:none}
.muted{padding:12px;border:1px dashed #d9e0ea;border-radius:12px;background:#fff;color:#6b7280;line-height:1.5}.toast{position:fixed;right:18px;bottom:18px;background:#111827;color:#fff;padding:10px 14px;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,.25);opacity:0;transform:translateY(16px);transition:all .2s}.toast.show{opacity:1;transform:translateY(0)}
.spin{width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;display:none;animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}
.theme-preview{height:78px;border-radius:14px;border:1px solid #d8dee8;overflow:hidden;background:#fff;display:flex;position:relative}
.theme-preview-sidebar{width:22%;height:100%}
.theme-preview-main{flex:1;position:relative}
.theme-preview-top{height:22px}
.theme-preview-cards{display:flex;gap:8px;padding:10px}
.theme-preview-card{flex:1;height:24px;border-radius:8px}
.theme-preview-chip{position:absolute;right:12px;bottom:10px;width:30px;height:30px;border-radius:999px}
@media (max-width:1080px){.layout{grid-template-columns:1fr}.rail-body{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.tab-btn{margin:0}}
@media (max-width:860px){.head,.hero{flex-direction:column}.span-4,.span-6,.span-8{grid-column:span 12}.field-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="page">
    <div class="head">
        <div class="title">
            <div class="icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            </div>
            <div>
                <h1>Application Settings</h1>
            </div>
        </div>
        <div class="actions">
            <button class="btn btn-secondary" type="button" onclick="loadSettings()">Reload</button>
            <button class="btn btn-primary" id="saveBtn" type="button" onclick="saveSettings()"><span class="spin" id="saveSpin"></span><span>Save</span></button>
        </div>
    </div>
    <div class="layout">
        <aside class="rail">
            <h2>Legacy Tabs</h2>
            <p>All INI-backed settings are distributed across these tabs.</p>
            <div class="rail-body">
                <button class="tab-btn active" data-tab="shopinfo" type="button"><strong>Shop Info</strong><span>Name, address, phone, GSTIN and logo</span></button>
                <button class="tab-btn" data-tab="application" type="button"><strong>Application</strong><span>Company, rates, printer and app values</span></button>
                <button class="tab-btn" data-tab="system" type="button"><strong>System</strong><span>General software behavior</span></button>
                <button class="tab-btn" data-tab="starting" type="button"><strong>Starting</strong><span>Start numbers and date related values</span></button>
                <button class="tab-btn" data-tab="saleoption" type="button"><strong>Sales Options</strong><span>Sales and print behavior</span></button>
                <button class="tab-btn" data-tab="order" type="button"><strong>Order</strong><span>Order and due date settings</span></button>
                <button class="tab-btn" data-tab="api" type="button"><strong>SMS / API</strong><span>SMS, WhatsApp and email provider options</span></button>
                <button class="tab-btn" data-tab="others" type="button"><strong>Others</strong><span>Master, printer and uncategorized keys</span></button>
                <button class="tab-btn" data-tab="accounts" type="button"><strong>Accounts</strong><span>Account-related configuration keys</span></button>
            </div>
        </aside>
        <main class="panel">
            <section class="tab-panel active" id="tab-shopinfo"><div class="hero"><div><h3>Shop Info</h3><p>Shop name, address, phone, GSTIN and logo are managed here separately from the software INI settings.</p></div>
                <div style="display:flex;gap:8px;align-items:center">
                    <span id="shopLockBadge" style="font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:4px 10px;border-radius:999px;background:#fee2e2;color:#991b1b">Locked</span>
                    <button type="button" id="shopUnlockBtn" onclick="unlockShopInfo()" style="height:32px;padding:0 14px;border:1px solid #d4af37;border-radius:8px;background:#fffbe8;color:#7a5a00;font-weight:700;cursor:pointer">Unlock to Edit</button>
                    <div class="badge">General + Company.KGST</div>
                </div>
            </div><div class="grid" id="shopInfoGrid">
                <div class="card span-4"><div class="card-head"><h4>Shop Logo</h4><div class="note">Stored in generals</div></div><div class="card-body">
                    <div class="logo-zone" id="logoZone" onclick="if(!shopInfoLocked()) document.getElementById('logoFile').click()">
                        <div id="logoPreviewWrap" class="logo-preview-wrap" style="display:none"><img id="logoImg" class="logo-preview" src="" alt="Logo"><button class="logo-remove" type="button" onclick="if(!shopInfoLocked()) removeLogo(event)">x</button></div>
                        <div id="logoPlaceholder" class="logo-placeholder"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></div>
                        <div class="sub"><strong>Click to upload</strong> or drag and drop<br>PNG, JPG, SVG, WEBP up to 2 MB</div>
                        <input type="file" id="logoFile" accept="image/*" onchange="handleLogoFile(this)">
                    </div>
                </div></div>
                <div class="card span-8"><div class="card-head"><h4>Shop Details</h4><div class="note">Saved separately</div></div><div class="card-body">
                    <div class="field-grid">
                        <div class="field"><label for="shop_name">Shop Name</label><input id="shop_name" type="text" readonly></div>
                        <div class="field"><label for="shop_phone">Phone Number</label><input id="shop_phone" type="text" readonly></div>
                    </div>
                    <div class="field"><label for="shop_address">Address</label><textarea id="shop_address" readonly></textarea></div>
                    <div class="field"><label for="shop_gstin">GSTIN</label><input id="shop_gstin" type="text" readonly></div>
                </div></div>
            </div>
            <style>
                #shopInfoGrid.locked input,#shopInfoGrid.locked textarea{background:#f3f4f6;color:#6b7280;cursor:not-allowed}
                #shopInfoGrid.locked .logo-zone{opacity:.6;cursor:not-allowed;pointer-events:none}
            </style>
            </section>
            <section class="tab-panel" id="tab-application"><div class="hero"><div><h3>Application</h3><p>Company information, rates, printer values and application-level keys from the INI file.</p></div><div class="badge" id="badge-application">0 keys</div></div><div class="grid" id="grid-application"></div></section>
            <section class="tab-panel" id="tab-system"><div class="hero"><div><h3>System</h3><p>General software flags and operational settings from the INI file.</p></div><div class="badge" id="badge-system">0 keys</div></div>
                <div class="grid" style="margin-bottom:14px">
                    <div class="card span-6"><div class="card-head"><h4>Dashboard Theme</h4><div class="note">6 theme options for the main dashboard</div></div><div class="card-body">
                        <div class="field-grid">
                            <div class="field">
                                <label for="dashboard_theme">Theme</label>
                                <select id="dashboard_theme">
                                    <option value="default">Default</option>
                                    <option value="alt">Warm</option>
                                    <option value="ocean">Ocean</option>
                                    <option value="forest">Forest</option>
                                    <option value="rose">Rose</option>
                                    <option value="dark">Dark</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Preview</label>
                                <div id="dashboard_theme_preview" class="theme-preview" aria-hidden="true">
                                    <div class="theme-preview-sidebar" id="theme_preview_sidebar"></div>
                                    <div class="theme-preview-main" id="theme_preview_main">
                                        <div class="theme-preview-top" id="theme_preview_top"></div>
                                        <div class="theme-preview-cards">
                                            <div class="theme-preview-card" id="theme_preview_card_1"></div>
                                            <div class="theme-preview-card" id="theme_preview_card_2"></div>
                                        </div>
                                        <div class="theme-preview-chip" id="theme_preview_chip"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div></div>
                </div>
                <div class="grid" id="grid-system"></div></section>
            <section class="tab-panel" id="tab-starting"><div class="hero"><div><h3>Starting</h3><p>Start counters, start date, touch defaults and opening-number style settings.</p></div><div class="badge" id="badge-starting">0 keys</div></div>
                <div class="grid" style="margin-bottom:14px">
                    <div class="card span-6"><div class="card-head"><h4>Client Barcode Counters</h4><div class="note">Stored in generali</div></div><div class="card-body"><div class="field-grid">
                        <div class="field"><label for="clastno">Last Customer Barcode No</label><input id="clastno" type="number" min="0" step="1"></div>
                        <div class="field"><label for="slastno">Last Supplier Barcode No</label><input id="slastno" type="number" min="0" step="1"></div>
                    </div></div></div>
                    <div class="card span-6"><div class="card-head"><h4>Sales Bill Number Series</h4><div class="note">Stored in generals</div></div><div class="card-body"><div class="field-grid">
                        <div class="field"><label for="sbpref">Bill Number Prefix</label><input id="sbpref" type="text" placeholder="e.g. GO/26-27/ or S/"></div>
                        <div class="field"><label for="sblen">Bill Number Length (digits)</label><input id="sblen" type="number" min="1" max="10" step="1" placeholder="5"></div>
                    </div></div></div>
                </div>
                <div class="grid" id="grid-starting"></div></section>
            <section class="tab-panel" id="tab-saleoption"><div class="hero"><div><h3>Sales Options</h3><p>Sales, estimate, print, footer, wastage, VA and MC related settings.</p></div><div class="badge" id="badge-saleoption">0 keys</div></div><div class="grid" id="grid-saleoption"></div></section>
            <section class="tab-panel" id="tab-order"><div class="hero"><div><h3>Order</h3><p>Order, delivery, due-date and order-slip related settings.</p></div><div class="badge" id="badge-order">0 keys</div></div><div class="grid" id="grid-order"></div></section>
            <section class="tab-panel" id="tab-api"><div class="hero"><div><h3>SMS / API</h3><p>Dedicated provider options for SMS sending API, WhatsApp API and email API configuration stored in the INI file.</p></div><div class="badge" id="badge-api">0 keys</div></div><div class="grid" id="grid-api"></div></section>
            <section class="tab-panel" id="tab-others"><div class="hero"><div><h3>Others</h3><p>Master, printer and remaining INI keys that do not fit the main functional groups.</p></div><div class="badge" id="badge-others">0 keys</div></div><div class="grid" id="grid-others"></div></section>
            <section class="tab-panel" id="tab-accounts"><div class="hero"><div><h3>Accounts</h3><p>Account, tax-account, discount-account and voucher related keys found in the INI file.</p></div><div class="badge" id="badge-accounts">0 keys</div></div><div class="grid" id="grid-accounts"></div></section>
        </main>
    </div>
</div>
<div class="toast" id="toast"></div>
<script>
const csrfToken=document.querySelector('meta[name="csrf-token"]').content;
const tabs=['shopinfo','application','system','starting','saleoption','order','api','others','accounts'];
const tabTitles={shopinfo:'Shop Info',application:'Application',system:'System',starting:'Starting',saleoption:'Sales Options',order:'Order',api:'API',others:'Others',accounts:'Accounts'};
const dashboardThemeOptions={
    default:{sidebar:'#12233e',top:'#e9eef6',main:'#f0f2f5',card:'#ffffff',chip:'#007d7a'},
    alt:{sidebar:'#4a221b',top:'#f3e3d4',main:'#f7efe6',card:'#fff8f1',chip:'#a33d2e'},
    ocean:{sidebar:'#0d1b40',top:'#dce5f5',main:'#edf1fb',card:'#ffffff',chip:'#1755a8'},
    forest:{sidebar:'#1a3620',top:'#e1ede0',main:'#f0f5ee',card:'#ffffff',chip:'#1a7a52'},
    rose:{sidebar:'#4a0f25',top:'#fce0e8',main:'#fef0f3',card:'#ffffff',chip:'#c0365a'},
    dark:{sidebar:'#0d1117',top:'#263344',main:'#111827',card:'#1f2937',chip:'#00bfbb'},
};
let settingsPayload={};
let shopInfoPayload={};
let loadedDashboardTheme='default';
document.querySelectorAll('.tab-btn').forEach((btn)=>btn.addEventListener('click',()=>activateTab(btn.dataset.tab)));
function activateTab(name){document.querySelectorAll('.tab-btn').forEach((btn)=>btn.classList.toggle('active',btn.dataset.tab===name));document.querySelectorAll('.tab-panel').forEach((panel)=>panel.classList.toggle('active',panel.id===`tab-${name}`))}
function toast(message,type='success'){const el=document.getElementById('toast');el.textContent=message;el.style.background=type==='error'?'#7f1d1d':'#111827';el.classList.add('show');clearTimeout(toast.timer);toast.timer=setTimeout(()=>el.classList.remove('show'),2600)}
function setSaving(flag){document.getElementById('saveBtn').disabled=flag;document.getElementById('saveSpin').style.display=flag?'inline-block':'none'}
function getSelectedDashboardTheme(){const el=document.getElementById('dashboard_theme');return (el&&dashboardThemeOptions[el.value])?el.value:(dashboardThemeOptions[loadedDashboardTheme]?loadedDashboardTheme:'default')}
function syncDashboardTheme(themeName=null){const theme=(themeName&&dashboardThemeOptions[themeName])?themeName:getSelectedDashboardTheme();try{window.parent.postMessage({type:'goldapp:set-theme',theme},'*');}catch(e){}}
function setDashboardThemePreview(themeName){const theme=dashboardThemeOptions[themeName]||dashboardThemeOptions.default;document.getElementById('theme_preview_sidebar').style.background=theme.sidebar;document.getElementById('theme_preview_main').style.background=theme.main;document.getElementById('theme_preview_top').style.background=theme.top;document.getElementById('theme_preview_card_1').style.background=theme.card;document.getElementById('theme_preview_card_2').style.background=theme.card;document.getElementById('theme_preview_chip').style.background=theme.chip}
function prettyLabel(key){return key.replace(/([a-z])([A-Z])/g,'$1 $2').replace(/_/g,' ').replace(/\bno\b/ig,'No').replace(/\bid\b/ig,'ID').replace(/\bmc\b/ig,'MC').replace(/\bva\b/ig,'VA').replace(/\bgst\b/ig,'GST').replace(/\bqty\b/ig,'Qty').replace(/\bwgt\b/ig,'Wgt').replace(/\bifsc\b/ig,'IFSC').trim()}
function isBooleanValue(value){return value==='Y'||value==='N'}
function isLongValue(value){return typeof value==='string'&&value.length>140}
function matches(key,regex){return regex.test(key.toLowerCase())}
function classify(section,key){
    const s=(section||'').toLowerCase();
    const k=(key||'').toLowerCase();
    if (s==='api'||/(sms|wap|whatsapp|email|mail|apiurl|apikey|provider|sender|template|entity|route)/.test(k)) return 'api';
    if (/(account|taxac|discac|voucher|bankbranch|bankifsc|bankname|bankacno|bankaccountname|bankaccountno)/.test(k)) return 'accounts';
    if (s==='company'||s==='application'||s==='rates') return 'application';
    if (s==='sales') return 'saleoption';
    if (s==='master'||s==='printer') return 'others';
    if (/(smith|jewl|scheme|kuri|coper|sundbcr|touchtosmith|gototouch|thrate|tcperc|ornthtc|purchasefooter|currency|paise|fontsize|pageheight|pagewidth|leftmargin|rightmargin|topmargin|bottommargin|bcmaxno|^bcno$|showornthtcsummary|manualstkvalue|opstkvaluefromacmaster|clstkvaluefromitemstock|clientc|clientclen|clientslen|same|auto$|prefix|maturity|acwgtmanualentry|adjusttptoaccinsmith)/.test(k)) return 'others';
    if (/(order|duedate|delivery|deldate|ord)/.test(k)) return 'order';
    if (/(start|stdtouch|tostocktouch|restartest|withstocktouchcalc|startdate|eststar|billform|orderform|stktype|purchas[e]?e|purchaseb|salesb|sale?se)/.test(k)) return 'starting';
    if (/(printer|branch|mail|holiday|margin|purchconvtouch|askprinter|bullion|sratebeforetax|sratebasedonbullionrate|printgstin|bankcomn|bankcomntax|defstate|defbilltype|tds|discperc)/.test(k)) return 'application';
    if (/(print|footer|sales|estimate|esttobill|wast|mc|va|party|discount|mud|silver|exchange|exch|cslip|manualbill|dateencrypt|oldbalance|coparty|customer|billamt|insufficient|focus|showadv|itemnamefromfr|qtyfr|compaddr)/.test(k)) return 'saleoption';
    if (/(roundoff|always|show|preview|clients|mobile|esc|barcode|autopopup|sm|logs|taxsystem|sound|welcome|language|reminder|oneway|dostolaser|amt3dec|billtypewise|counter|showdefdescription|keepupdate|strict|help|secondary|secsales|secpurchase|secreceipt|secpayment|secorder)/.test(k)) return 'system';
    return 'others';
}
function buildCard(section,entries){
    const span=entries.length>18?'span-8':entries.length>10?'span-6':'span-4';
    const fields=entries.map(([key,value])=>renderField(section,key,value)).join('');
    return `<div class="card ${span}"><div class="card-head"><h4>${section}</h4><div class="note">${entries.length} keys</div></div><div class="card-body"><div class="field-grid">${fields}</div></div></div>`;
}
function renderField(section,key,value){
    const safeSection=escapeHtml(section),safeKey=escapeHtml(key),safeValue=escapeHtml(String(value ?? ''));
    if (isBooleanValue(String(value ?? ''))) {
        const checked=String(value)==='Y'?'checked':'';
        return `<div class="field" data-section="${safeSection}" data-key="${safeKey}"><div class="bool-row"><input type="checkbox" ${checked}><span>${escapeHtml(prettyLabel(key))}</span></div></div>`;
    }
    if (isLongValue(String(value ?? ''))) {
        return `<div class="field" data-section="${safeSection}" data-key="${safeKey}"><label>${escapeHtml(prettyLabel(key))}</label><textarea>${safeValue}</textarea></div>`;
    }
    return `<div class="field" data-section="${safeSection}" data-key="${safeKey}"><label>${escapeHtml(prettyLabel(key))}</label><input type="text" value="${safeValue}"></div>`;
}
function escapeHtml(value){return String(value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
function renderTabs(settings){
    const grouped={application:{},system:{},starting:{},saleoption:{},order:{},api:{},others:{},accounts:{}};
    Object.entries(settings).forEach(([section,pairs])=>{
        Object.entries(pairs||{}).forEach(([key,value])=>{
            if(section==='Software'&&key==='DashboardTheme') return;
            const tab=classify(section,key);
            if(!grouped[tab][section]) grouped[tab][section]=[];
            grouped[tab][section].push([key,value]);
        });
    });
    tabs.forEach((tab)=>{
        if(tab==='shopinfo') return;
        const grid=document.getElementById(`grid-${tab}`);
        const sections=grouped[tab];
        const sectionNames=Object.keys(sections);
        document.getElementById(`badge-${tab}`).textContent=`${sectionNames.reduce((sum,name)=>sum+sections[name].length,0)} keys`;
        if(sectionNames.length===0){grid.innerHTML=`<div class="card"><div class="card-body"><div class="muted">No INI keys were classified under ${tabTitles[tab]}.</div></div></div>`;return}
        grid.innerHTML=sectionNames.map((section)=>buildCard(section,sections[section])).join('');
    });
}
function collectSettings(){
    const result={};
    document.querySelectorAll('.field[data-section][data-key]').forEach((node)=>{
        const section=node.dataset.section;
        const key=node.dataset.key;
        if(!result[section]) result[section]={};
        const checkbox=node.querySelector('input[type="checkbox"]');
        const textarea=node.querySelector('textarea');
        const input=node.querySelector('input[type="text"]');
        if(checkbox){result[section][key]=checkbox.checked?'Y':'N';return}
        if(textarea){result[section][key]=textarea.value;return}
        result[section][key]=input?input.value:'';
    });
    return result;
}
function collectShopInfo(){
    return {
        name: document.getElementById('shop_name')?.value || '',
        address: document.getElementById('shop_address')?.value || '',
        phone: document.getElementById('shop_phone')?.value || '',
        gstin: document.getElementById('shop_gstin')?.value || '',
    };
}
function setLogo(url){
    const wrap=document.getElementById('logoPreviewWrap');
    const img=document.getElementById('logoImg');
    const ph=document.getElementById('logoPlaceholder');
    if(!wrap||!img||!ph)return;
    if(url){img.src=url;wrap.style.display='inline-block';ph.style.display='none'}
    else{img.src='';wrap.style.display='none';ph.style.display='inline-flex'}
}
function shopInfoLocked(){return document.getElementById('shopInfoGrid')?.classList.contains('locked')!==false}
function applyShopInfoLock(locked){
    const grid=document.getElementById('shopInfoGrid');
    if(!grid)return;
    grid.classList.toggle('locked',locked);
    ['shop_name','shop_phone','shop_address','shop_gstin'].forEach(id=>{
        const el=document.getElementById(id);
        if(el){if(locked)el.setAttribute('readonly','readonly');else el.removeAttribute('readonly')}
    });
    const badge=document.getElementById('shopLockBadge');
    const btn=document.getElementById('shopUnlockBtn');
    if(badge){badge.textContent=locked?'Locked':'Unlocked';badge.style.background=locked?'#fee2e2':'#dcfce7';badge.style.color=locked?'#991b1b':'#166534'}
    if(btn){btn.textContent=locked?'Unlock to Edit':'Lock';btn.style.background=locked?'#fffbe8':'#f0fdf4';btn.style.color=locked?'#7a5a00':'#166534';btn.style.borderColor=locked?'#d4af37':'#86efac'}
}
function unlockShopInfo(){
    if(!shopInfoLocked()){applyShopInfoLock(true);return}
    const pw=window.prompt('Enter admin password to edit Shop Info:');
    if(pw===null)return;
    if(pw==='PRO'){applyShopInfoLock(false);toast('Shop Info unlocked')}
    else{toast('Invalid admin password','error')}
}
document.addEventListener('DOMContentLoaded',()=>applyShopInfoLock(true));
async function loadSettings(){
    try{
        const r=await fetch('{{ url("/api/application-settings/load") }}');
        const d=await r.json();
        if(!d.ok) throw new Error(d.msg||'Load failed');
        settingsPayload=d.settings||{};
        shopInfoPayload=d.shop_info||{};
        document.getElementById('shop_name').value=shopInfoPayload.name||'';
        document.getElementById('shop_address').value=shopInfoPayload.address||'';
        document.getElementById('shop_phone').value=shopInfoPayload.phone||'';
        document.getElementById('shop_gstin').value=shopInfoPayload.gstin||'';
        setLogo(shopInfoPayload.logo_url||'');
        document.getElementById('clastno').value=d.clastno||'0';
        document.getElementById('slastno').value=d.slastno||'0';
        document.getElementById('sbpref').value=d.sbpref||'';
        document.getElementById('sblen').value=d.sblen||'5';
        renderTabs(settingsPayload);
        loadedDashboardTheme=(settingsPayload?.Software?.DashboardTheme&&dashboardThemeOptions[settingsPayload.Software.DashboardTheme])?settingsPayload.Software.DashboardTheme:'default';
        document.getElementById('dashboard_theme').value=loadedDashboardTheme;
        setDashboardThemePreview(loadedDashboardTheme);
        syncDashboardTheme(loadedDashboardTheme);
    }catch(e){toast(e.message||'Unable to load settings','error')}
}
async function saveSettings(){
    setSaving(true);
    try{
        const selectedTheme=getSelectedDashboardTheme();
        const body=new FormData();
        const allSettings=collectSettings();
        if(!allSettings.Software) allSettings.Software={};
        allSettings.Software.DashboardTheme=selectedTheme;
        body.append('json',JSON.stringify(allSettings));
        if(!shopInfoLocked()) body.append('shop_info',JSON.stringify(collectShopInfo()));
        body.append('clastno',document.getElementById('clastno').value||'0');
        body.append('slastno',document.getElementById('slastno').value||'0');
        body.append('sbpref',document.getElementById('sbpref').value||'');
        body.append('sblen',document.getElementById('sblen').value||'5');
        const r=await fetch('{{ url("/api/application-settings/save") }}',{method:'POST',headers:{'X-CSRF-TOKEN':csrfToken},body});
        const text=await r.text();
        const d=text?JSON.parse(text):{ok:r.ok,msg:r.ok?'Application settings saved.':'Save failed'};
        if(!d.ok) throw new Error(d.msg||'Save failed');
        toast(d.msg||'Application settings saved.');
        loadedDashboardTheme=selectedTheme;
        syncDashboardTheme(selectedTheme);
        await loadSettings();
    }catch(e){toast(e.message||'Unable to save settings','error')}
    finally{setSaving(false)}
}
async function handleLogoFile(input,fileOverride=null){
    const file=fileOverride||(input.files&&input.files[0]);
    if(!file)return;
    const body=new FormData();
    body.append('logo',file);
    try{
        const r=await fetch('{{ url("/api/application-settings/upload-logo") }}',{method:'POST',headers:{'X-CSRF-TOKEN':csrfToken},body});
        const d=await r.json();
        if(!d.ok) throw new Error(d.msg||'Upload failed');
        setLogo(d.logo_url||'');
        toast('Logo updated');
    }catch(e){toast(e.message||'Unable to upload logo','error')}
    finally{if(input)input.value=''}
}
async function removeLogo(event){
    event.stopPropagation();
    try{
        const r=await fetch('{{ url("/api/application-settings/remove-logo") }}',{method:'POST',headers:{'X-CSRF-TOKEN':csrfToken},body:new FormData()});
        const d=await r.json();
        if(!d.ok) throw new Error(d.msg||'Remove failed');
        setLogo('');
        toast('Logo removed');
    }catch(e){toast(e.message||'Unable to remove logo','error')}
}
const logoZone=document.getElementById('logoZone');
['dragenter','dragover'].forEach((n)=>logoZone.addEventListener(n,(e)=>{e.preventDefault();logoZone.classList.add('drag-over')}));
['dragleave','drop'].forEach((n)=>logoZone.addEventListener(n,(e)=>{e.preventDefault();logoZone.classList.remove('drag-over')}));
logoZone.addEventListener('drop',(e)=>{const files=e.dataTransfer?.files;if(!files||!files.length)return;handleLogoFile(document.getElementById('logoFile'),files[0])});
document.getElementById('dashboard_theme').addEventListener('change',function(){setDashboardThemePreview(this.value);syncDashboardTheme(this.value)});
loadSettings();
// ── Dashboard Theme Picker ────────────────────────────────────────────────────
window.addEventListener('beforeunload',function(){syncDashboardTheme()});
document.addEventListener('visibilitychange',function(){if(document.visibilityState==='hidden') syncDashboardTheme();});
</script>
</body>
</html>
