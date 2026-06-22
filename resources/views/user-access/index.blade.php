<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>User Access Control</title>
<script>
window.UA_CONFIG = {
    siteUrl:     @json(request()->root()),
    permissions: @json($permissions),
    companies:   @json($companies ?? []),
};
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',system-ui,-apple-system,sans-serif;font-size:13px;color:#1e293b;background:#f1f5f9;overflow:hidden;height:100vh}

/* ── Layout ─────────────────────────────────────────────── */
.app{display:flex;flex-direction:column;height:100vh;background:#fff}
.header{background:linear-gradient(135deg,#6d28d9 0%,#8b5cf6 100%);color:#fff;padding:10px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0}
.header h1{font-size:15px;font-weight:700;letter-spacing:.3px}
.header .badge{background:rgba(255,255,255,.2);padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600}

/* ── Main Content ───────────────────────────────────────── */
.main{flex:1;display:flex;overflow:hidden}

/* ── Left Panel (User List) ─────────────────────────────── */
.left-panel{width:240px;border-right:1px solid #e2e8f0;display:flex;flex-direction:column;flex-shrink:0;background:#faf5ff}
.left-header{padding:8px 10px;background:#f5f3ff;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:6px}
.left-header input{flex:1;height:28px;padding:0 8px;border:1px solid #d4d4d8;border-radius:5px;font-size:11px}
.left-header input:focus{outline:none;border-color:#8b5cf6;box-shadow:0 0 0 2px rgba(139,92,246,.15)}
.user-list{flex:1;overflow-y:auto;padding:4px}
.user-list .user-item{padding:6px 10px;border-radius:6px;cursor:pointer;display:flex;align-items:center;gap:8px;margin-bottom:2px;transition:all .15s}
.user-list .user-item:hover{background:#ede9fe}
.user-list .user-item.active{background:#ddd6fe;font-weight:600}
.user-list .user-item .code{font-weight:600;font-size:12px;color:#6d28d9;min-width:60px}
.user-list .user-item .name{font-size:11px;color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.left-footer{padding:6px 8px;border-top:1px solid #e2e8f0;background:#f5f3ff;display:flex;gap:4px;flex-wrap:wrap}
.left-footer .btn{height:26px;padding:0 10px;border:1px solid #d4d4d8;border-radius:5px;background:#fff;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;color:#475569}
.left-footer .btn:hover{background:#f1f5f9;border-color:#8b5cf6}
.left-footer .btn.add{background:#7c3aed;color:#fff;border-color:#7c3aed}
.left-footer .btn.add:hover{background:#6d28d9}
.left-footer .btn.del{color:#ef4444;border-color:#fca5a5}
.left-footer .btn.del:hover{background:#fef2f2}

/* ── Right Panel ────────────────────────────────────────── */
.right-panel{flex:1;display:flex;flex-direction:column;overflow:hidden}

/* ── User Details ───────────────────────────────────────── */
.details-bar{padding:10px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-shrink:0}
.details-grid{display:grid;grid-template-columns:repeat(4,auto 1fr);gap:6px 10px;align-items:center}
.details-grid label{font-weight:600;font-size:11px;color:#6d28d9;white-space:nowrap;text-align:right}
.details-grid input{height:28px;padding:0 8px;border:1px solid #cbd5e1;border-radius:5px;font-size:12px;color:#1e293b;background:#fff;transition:all .2s}
.details-grid input:focus{outline:none;border-color:#8b5cf6;box-shadow:0 0 0 2px rgba(139,92,246,.15)}
.details-grid input.ro{background:#f1f5f9;color:#64748b;cursor:default}
.details-grid input.pw{letter-spacing:2px}

/* ── Limits Bar ────────────────────────────────────────── */
.limits-bar{padding:8px 16px;background:#fefce8;border-bottom:1px solid #fde68a;flex-shrink:0}
.limits-bar .limits-title{font-size:11px;font-weight:700;color:#92400e;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.limits-grid{display:grid;grid-template-columns:repeat(5,auto 1fr);gap:6px 8px;align-items:center}
.limits-grid label{font-weight:600;font-size:10px;color:#92400e;white-space:nowrap;text-align:right}
.limits-grid input{height:26px;padding:0 8px;border:1px solid #fde68a;border-radius:5px;font-size:12px;color:#1e293b;background:#fff;text-align:right;font-variant-numeric:tabular-nums;transition:all .2s}
.limits-grid input:focus{outline:none;border-color:#f59e0b;box-shadow:0 0 0 2px rgba(245,158,11,.15)}

/* ── Permissions Section ────────────────────────────────── */
.perm-section{flex:1;overflow-y:auto;padding:10px 16px 72px}
.perm-block{margin-bottom:14px}
.perm-block-title{display:flex;align-items:center;gap:8px;margin-bottom:8px;padding:6px 10px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;font-size:11px;font-weight:700;color:#4338ca;text-transform:uppercase;letter-spacing:.4px}
.perm-block-title.software{background:#fff7ed;border-color:#fed7aa;color:#c2410c}
.perm-block-title .hint{font-size:10px;font-weight:600;color:#64748b;text-transform:none;letter-spacing:0}
.menu-block{margin-bottom:12px;border:1px solid #cbd5e1;border-radius:10px;overflow:hidden;background:#fff;transition:all 0.3s ease}
.menu-block-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;background:linear-gradient(180deg,#eff6ff 0%,#dbeafe 100%);border-bottom:1px solid #bfdbfe;font-size:12px;font-weight:800;color:#1d4ed8;letter-spacing:.3px;text-transform:uppercase;cursor:pointer;user-select:none}
.menu-block.collapsed .menu-block-head{border-bottom-color:transparent}
.menu-block-title{display:flex;align-items:center;gap:8px}
.menu-block-arrow{font-size:10px;color:#1d4ed8;transition:transform 0.3s ease;display:inline-block}
.menu-block:not(.collapsed) .menu-block-arrow{transform:rotate(90deg)}
.menu-block-count{padding:1px 8px;border-radius:999px;background:#bfdbfe;color:#1e3a8a;font-size:10px;font-weight:700}
.menu-block-toggle{display:flex;align-items:center;gap:6px;font-size:10px;font-weight:700;color:#1d4ed8;white-space:nowrap;cursor:pointer}
.menu-block-toggle input[type=checkbox]{width:14px;height:14px;accent-color:#2563eb;cursor:pointer}
.menu-block-body{padding:10px;background:#f8fbff;display:grid;grid-template-columns:1fr;gap:8px;max-height:70vh;opacity:1;overflow:auto;transition:max-height 0.35s cubic-bezier(0.4,0,0.2,1),opacity 0.25s ease-in-out,padding 0.35s ease}
.menu-block.collapsed .menu-block-body{max-height:0;opacity:0;padding-top:0;padding-bottom:0;pointer-events:none}
.mdi-submenu-card{padding:10px 12px;border:1px solid #dbeafe;border-radius:10px;background:#fff;transition:all 0.3s ease;}
.mdi-submenu-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding-bottom:6px;border-bottom:1px solid #eff6ff;margin-bottom:8px;cursor:pointer;user-select:none;transition:all 0.3s ease;}
.mdi-submenu-card.collapsed .mdi-submenu-head{border-bottom-color:transparent;margin-bottom:0;padding-bottom:0;}
.mdi-submenu-toggle{display:flex;align-items:center;gap:7px;cursor:pointer;min-width:0;}
.mdi-submenu-toggle input[type=checkbox]{width:15px;height:15px;accent-color:#2563eb;cursor:pointer;flex-shrink:0;}
.mdi-submenu-title{font-size:12px;font-weight:700;color:#1e293b;line-height:1.3;cursor:pointer;user-select:none;}
.mdi-submenu-meta{font-size:10px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.35px;white-space:nowrap;flex-shrink:0}
.mdi-submenu-items{display:grid;grid-template-columns:1fr;gap:4px;max-height:2200px;opacity:1;overflow:hidden;transition:max-height 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease-in-out, margin 0.35s ease;}
.mdi-submenu-card.collapsed .mdi-submenu-items{max-height:0;opacity:0;pointer-events:none;}
.mdi-submenu-item{display:flex;align-items:flex-start;gap:7px;font-size:11px;color:#475569;cursor:pointer;padding:2px 4px;border-radius:4px;}
.mdi-submenu-item:hover{background:#f0f9ff;}
.mdi-submenu-item input[type=checkbox]{width:14px;height:14px;accent-color:#4f46e5;cursor:pointer;flex-shrink:0;margin-top:1px}
.mdi-submenu-item span{line-height:1.3}
.mdi-hidden{display:none !important}
.mdi-submenu-arrow{font-size:10px;color:#64748b;transition:transform 0.3s ease;}
.mdi-submenu-card:not(.collapsed) .mdi-submenu-arrow{transform:rotate(90deg);}

.perm-group{margin-bottom:10px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;transition:all 0.3s ease;}
.perm-group-head{background:linear-gradient(135deg,#f5f3ff 0%,#ede9fe 100%);padding:8px 12px;font-size:11px;font-weight:700;color:#5b21b6;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:8px;cursor:pointer;user-select:none;transition:all 0.3s ease;}
.perm-group.collapsed .perm-group-head{border-bottom-color:transparent;}
.perm-group-head-left{display:flex;align-items:center;gap:8px;min-width:0;cursor:pointer;}
.perm-group-head .count{background:#ddd6fe;padding:1px 6px;border-radius:8px;font-size:10px;font-weight:600;color:#6d28d9}
.perm-group-body{padding:8px 12px;display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;max-height:500px;opacity:1;overflow:hidden;transition:max-height 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease-in-out;}
.perm-group.collapsed .perm-group-body{max-height:0;opacity:0;padding-top:0;padding-bottom:0;pointer-events:none;}
.perm-group-arrow{font-size:10px;color:#6d28d9;transition:transform 0.3s ease;display:inline-block;}
.perm-group:not(.collapsed) .perm-group-arrow{transform:rotate(90deg);}
.perm-item{display:flex;align-items:center;gap:6px;padding:3px 0}
.perm-item input[type=checkbox]{width:15px;height:15px;accent-color:#7c3aed;cursor:pointer;flex-shrink:0}
.perm-item .perm-code{font-size:10px;font-weight:700;color:#6d28d9;min-width:100px}
.perm-item .perm-desc{font-size:11px;color:#64748b}

.company-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px}
.company-item{display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer}
.company-item:hover{background:#fdf2f8;border-color:#f9a8d4}
.company-item input{width:15px;height:15px;accent-color:#db2777;margin-top:2px;flex-shrink:0}
.company-name{font-size:12px;font-weight:700;color:#831843;line-height:1.25}
.company-db{font-size:10px;color:#64748b;margin-top:2px}
.company-empty{padding:12px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;color:#64748b;font-weight:600;font-size:12px}


/* ── Bottom Save Bar ────────────────────────────────────── */
.save-bar{display:flex;align-items:center;padding:8px 16px;gap:8px;background:#f5f3ff;border-top:1px solid #e2e8f0;flex-shrink:0}
.save-bar .btn{height:32px;padding:0 20px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;font-size:12px;font-weight:600;color:#475569;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:4px}
.save-bar .btn:hover{background:#f1f5f9;border-color:#94a3b8}
.save-bar .btn.primary{background:linear-gradient(135deg,#7c3aed 0%,#6d28d9 100%);color:#fff;border-color:#7c3aed}
.save-bar .btn.primary:hover{background:linear-gradient(135deg,#6d28d9 0%,#5b21b6 100%)}
.save-bar .btn.danger{color:#ef4444;border-color:#fca5a5}
.save-bar .btn.danger:hover{background:#fef2f2}
.save-bar .btn.edit{background:linear-gradient(135deg,#0ea5e9 0%,#0284c7 100%);color:#fff;border-color:#0284c7}
.save-bar .btn.edit:hover{background:linear-gradient(135deg,#0284c7 0%,#0369a1 100%)}
.save-bar .btn.cancel{color:#b45309;border-color:#fcd34d;background:#fffbeb}
.save-bar .btn.cancel:hover{background:#fef3c7;border-color:#f59e0b}
.save-bar .status{flex:1;text-align:right;font-size:11px;color:#64748b;font-weight:500}
.save-bar .status.ok{color:#16a34a;font-weight:600}
.save-bar .status.err{color:#ef4444;font-weight:600}
.save-bar .status.warn{color:#b45309;font-weight:600}

/* ── Toast ──────────────────────────────────────────────── */
.toast{position:fixed;top:12px;right:12px;padding:10px 18px;border-radius:8px;font-size:12px;font-weight:600;z-index:9999;opacity:0;transform:translateY(-10px);transition:all .3s;pointer-events:none}
.toast.show{opacity:1;transform:translateY(0)}
.toast.success{background:#dcfce7;color:#166534;border:1px solid #86efac}
.toast.error{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5}

/* Loading overlay */
.loading-overlay{
    position:fixed;inset:0;background:rgba(15,23,42,.35);backdrop-filter:blur(2px);
    display:flex;align-items:center;justify-content:center;z-index:9998;
    opacity:0;pointer-events:none;transition:opacity .2s
}
.loading-overlay.show{opacity:1;pointer-events:auto}
.loading-box{
    min-width:220px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;
    box-shadow:0 18px 40px rgba(15,23,42,.18);padding:18px 20px;display:flex;align-items:center;gap:12px
}
.loading-spinner{
    width:22px;height:22px;border:3px solid #ddd6fe;border-top-color:#7c3aed;border-radius:50%;
    animation:spin .8s linear infinite;flex-shrink:0
}
.loading-text{font-size:12px;font-weight:600;color:#475569}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── Scrollbar ──────────────────────────────────────────── */
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-track{background:#f1f5f9}
::-webkit-scrollbar-thumb{background:#c4b5fd;border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:#a78bfa}
</style>
</head>
<body>
<div class="app">

<!-- ═══ Header ═══ -->
<div class="header">
    <h1>User Access Control</h1>
    <span class="badge">Admin</span>
</div>

<!-- ═══ Main ═══ -->
<div class="main">

    <!-- ── Left Panel: User List ── -->
    <div class="left-panel">
        <div class="left-header">
            <input type="text" id="userSearch" placeholder="Search users..." autocomplete="off" spellcheck="false">
        </div>
        <div class="user-list" id="userList"></div>
        <div class="left-footer">
            <button class="btn add" data-action="add-user">+ Add</button>
            <button class="btn" data-action="refresh-users">Refresh</button>
            <button class="btn del" data-action="delete-user">Delete</button>
        </div>
    </div>

    <!-- ── Right Panel ── -->
    <div class="right-panel">

        <!-- User Details -->
        <div class="details-bar">
            <div class="details-grid">
                <label>Code</label>
                <input type="text" id="uCode" maxlength="20" style="text-transform:uppercase">
                <label>Name</label>
                <input type="text" id="uName" maxlength="50" style="grid-column:span 3">
                <label>Password</label>
                <input type="password" id="uPassword" class="pw" placeholder="(leave blank to keep current)">
                <label>Confirm</label>
                <input type="password" id="uPasswordConfirm" class="pw" placeholder="Confirm password">
            </div>
        </div>

        <!-- Numeric Limits -->
        <div class="limits-bar">
            <div class="limits-title">Numeric Limits</div>
            <div class="limits-grid">
                <label>Max Disc/Gm</label>
                <input type="number" id="limMaxDisc" step="0.01" value="0">
                <label>Max Credit</label>
                <input type="number" id="limMaxCredit" step="0.01" value="0">
                <label>Min VA%</label>
                <input type="number" id="limMinVaPerc" step="0.01" value="0">
                <label>Max Disc%</label>
                <input type="number" id="limMaxDiscPerc" step="0.01" value="0">
                <label>Max Adj Wgt BC</label>
                <input type="number" id="limMaxAdjWgtBc" step="0.001" value="0">
            </div>
        </div>

        <!-- Permissions -->
        <div class="perm-section" id="permSection">
            <!-- Permission groups rendered by JS -->
            <div class="perm-block">
                <div class="perm-block-title software">
                    Company Access
                    <span class="hint">Tick companies this user can select. If none are ticked, old unrestricted behavior is kept.</span>
                </div>
                <div id="companyAccessList" class="company-grid"></div>
            </div>
            <div class="perm-block">
                <div class="perm-block-title">
                    Menu Access
                    <span class="hint">Tick each menu, submenu, or individual item separately.</span>
                </div>
                <div id="mdiPermGroups"></div>
            </div>
            <div class="perm-block">
                <div class="perm-block-title software">
                    Software Options
                    <span class="hint">Extra behavior, display, and control options.</span>
                </div>
                <div id="softwarePermGroups"></div>
            </div>
        </div>

        <!-- Save Bar -->
        <div class="save-bar">
            <button class="btn primary" id="btnSave" data-action="save-user">Save (F9)</button>
            <button class="btn edit" id="btnEdit" data-action="edit-user" style="display:none">Edit</button>
            <button class="btn cancel" id="btnCancel" data-action="cancel-user">Cancel</button>
            <button class="btn" data-action="refresh-users">Refresh</button>
            <span class="status" id="statusMsg"></span>
        </div>

    </div><!-- /right-panel -->
</div><!-- /main -->

</div><!-- /app -->

<div class="toast" id="toast"></div>
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-box">
        <div class="loading-spinner"></div>
        <div class="loading-text" id="loadingText">Loading...</div>
    </div>
</div>

<script>
(function(){
'use strict';

const BASE = window.UA_CONFIG.siteUrl;
const PERMS = window.UA_CONFIG.permissions;
const COMPANIES = window.UA_CONFIG.companies || [];
const CSRF  = document.querySelector('meta[name="csrf-token"]').content;
const HIDDEN_SOFTWARE_PERMS = new Set(['ALLOWSECONDARYDBSYNC']);
const mdiItem = (label, code) => ({ label, code });
const MDI_MENU_LAYOUT = [
    {
        label: 'System',
        groups: [
            { label: 'Dashboard', items: [mdiItem('Dashboard', 'MDI_DASHBOARD')] },
            { label: 'System Settings', items: [mdiItem('Application Settings', 'MDI_APPLICATION_SETTINGS'), mdiItem('Administration', 'MDI_ADMINISTRATION')] },
            { label: 'User Management', items: [mdiItem('Provide Access', 'MDI_USER_ACCESS'), mdiItem('Admin Users / Users History', 'MDI_ADMIN_USERS')] },
            { label: 'Campaign & Customer Message', items: [mdiItem('Customer Campaign', 'MDI_CUSTOMER_CAMPAIGN')] },
        ],
    },
    {
        label: 'Masters',
        groups: [
            { label: 'System Settings', items: [
                mdiItem('Update - Rate', 'MDI_RATE_UPDATE'),
                mdiItem('Update - Application', 'MDI_APPLICATION_SETTINGS'),
                mdiItem('Country, Currency & Religion', 'MDI_APPLICATION_SETTINGS'),
            ] },
            { label: 'Item Master', items: [
                mdiItem('Item Add/Edit', 'MDI_ITEM_MASTER'),
                mdiItem('Item Temp', 'MDI_ITEM_TEMP'),
                mdiItem('Other Items', 'MDI_OTHER_ITEMS'),
                mdiItem('Groups', 'MDI_GROUPS'),
                mdiItem('Sub Groups', 'MDI_SUB_GROUPS'),
                mdiItem('Purity Type', 'MDI_PURITY_TYPE'),
                mdiItem('Counters', 'MDI_COUNTERS'),
                mdiItem('Wastage Table', 'MDI_WASTAGE_TABLE'),
                mdiItem('MC Table', 'MDI_MC_TABLE'),
                mdiItem('Party MC Table', 'MDI_PARTY_MC_TABLE'),
                mdiItem('Stock Type', 'MDI_STOCK_TYPE'),
                mdiItem('Models', 'MDI_MODEL_MASTER'),
                mdiItem('Opening Stock Entry', 'MDI_STOCK_OPENING_STOCK'),
                mdiItem('ReOrder Level Table', 'MDI_REORDER'),
            ] },
            { label: 'Customer Master', items: [mdiItem('Customer', 'MDI_CUSTOMER'), mdiItem('Customer Popup Routes', 'MDI_CUSTOMER_POPUP')] },
            { label: 'Supplier Master', items: [mdiItem('Supplier', 'MDI_SUPPLIER_MASTER')] },
            { label: 'Goldsmith Master', items: [mdiItem('Goldsmith / Smith', 'MDI_GOLDSMITH_MASTER')] },
            { label: 'Refiner Master', items: [mdiItem('Refiner', 'MDI_REFINER_MASTER')] },
            { label: 'Jewellery Master', items: [mdiItem('Jewellery', 'MDI_JEWELLERY_MASTER')] },
            { label: 'Depositor Master', items: [mdiItem('Depositor Weight', 'MDI_DEPOSITOR_MASTER')] },
            { label: 'Staff Master', items: [mdiItem('Staff Adding', 'MDI_STAFF_MASTER')] },
            { label: 'Account Master', items: [
                mdiItem('Account', 'MDI_ACCOUNTS_MASTER'),
                mdiItem('Position Settings', 'MDI_ACCOUNTS_MASTER'),
                mdiItem('Book Stock', 'MDI_ACCOUNTS_MASTER'),
                mdiItem('Op.Stock Value Set', 'MDI_ACCOUNTS_MASTER'),
                mdiItem('C/o Party Limit', 'MDI_ACCOUNTS_MASTER'),
            ] },
            { label: 'Salesman / Scheme / Invoice', items: [
                mdiItem('Salesman Master', 'MDI_SALESMAN_MASTER'),
                mdiItem('Scheme Master', 'MDI_KURI_TYPE_MASTER'),
                mdiItem('Invoice Settings', 'MDI_BILL_PREFIX'),
            ] },
            { label: 'State / Gift / Loyalty', items: [
                mdiItem('State Master', 'MDI_STATES_ADDING'),
                mdiItem('Gift Management', 'MDI_GIFT_TABLE'),
                mdiItem('Loyalty Program', 'MDI_POINT_CARD'),
                mdiItem('Denomination Master', 'MDI_DENOMINATION_MASTER'),
            ] },
            { label: 'Opening Balance Entry', items: [mdiItem('Customers', 'MDI_OP_BILL_CREATION_CUSTOMERS')] },
            { label: 'User Management', items: [
                mdiItem('Change Password', 'MDI_ADMIN_USERS'),
                mdiItem('Provide Access', 'MDI_USER_ACCESS'),
                mdiItem('Users History', 'MDI_ADMIN_USERS'),
            ] },
            { label: 'Search & Help', items: [
                mdiItem('Search Items', 'MDI_SEARCH_ITEMS'),
                mdiItem('Item Help', 'MDI_ITEM_HELP'),
                mdiItem('Regional Help', 'MDI_REGIONAL_HELP'),
            ] },
            { label: 'Rate Update & History', items: [mdiItem('Rate Update', 'MDI_RATE_UPDATE'), mdiItem('Rate History', 'MDI_RATE_HISTORY')] },
        ],
    },
    {
        label: 'Operations',
        groups: [
            { label: 'Accounts Transactions', items: [
                mdiItem('Account Restart', 'MDI_ACCOUNTS_RESTART_DATE'),
                mdiItem('Amount/Weight Transfer', 'MDI_ACCOUNTS_AMT_WGT_TRANSFER'),
                mdiItem('Cash Denomination', 'MDI_ACCOUNTS_DENOMINATION_ENTRY'),
                mdiItem('Cheque Processing', 'MDI_ACCOUNTS_PDC_COLLECTION'),
                mdiItem('Customer Receipt', 'MDI_ACCOUNTS_CUSTOMER_BILLWISE_RCPT'),
                mdiItem('Daily Entry', 'MDI_ACCOUNTS_DAILY_STATEMENT'),
                mdiItem('Discount Entry', 'MDI_ACCOUNTS_PDC_COLLECTION'),
                mdiItem('Adjustments', 'MDI_ACCOUNTS_DEBIT_CREDIT_NOTE'),
                mdiItem('Due Date Update', 'MDI_ACCOUNTS_CHANGE_DUEDATE'),
                mdiItem('Expense Entry', 'MDI_ACCOUNTS_EXPENSE_VOUCHER_ENTRY'),
                mdiItem('Group Allocation', 'MDI_ACCOUNTS_GROUP_AMT_ALLOCATION'),
                mdiItem('Journal Entry', 'MDI_ACCOUNTS_JOURNAL'),
                mdiItem('Multi Bill To One Account', 'MDI_ACCOUNTS_PARTY_CODE_MERGE'),
                mdiItem('Make Payment', 'MDI_ACCOUNTS_PAYMENT'),
                mdiItem('Payment Approval', 'MDI_ACCOUNTS_PAYMENT_CONFIRMATION'),
                mdiItem('Rate Adjustment', 'MDI_ACCOUNTS_RATE_DIFF_ADJUSTMENT'),
                mdiItem('Receive Payment', 'MDI_ACCOUNTS_RECEIPT'),
                mdiItem('Supplier Payment', 'MDI_ACCOUNTS_SUPPLIER_BILLWISE_PAYMENT'),
                mdiItem('Suspense Entry', 'MDI_ACCOUNTS_SUSPENSE_ENTRY'),
                mdiItem('Edit Transactions', 'MDI_ACCOUNTS_EDIT_ENTRY'),
            ] },
            { label: 'Barcode Management', items: [
                mdiItem('Bulk Barcode', 'MDI_BARCODE_MULTI_ENTRY'),
                mdiItem('Counter Issue', 'MDI_COUNTER_ISSUE'),
                mdiItem('Single Barcode', 'MDI_BARCODE_SINGLE_ENTRY'),
                mdiItem('Stock Check', 'MDI_STOCK_VERIFICATION'),
            ] },
            { label: 'Chit Scheme', items: [
                mdiItem('Add Members', 'MDI_KURI_DETAILS'),
                mdiItem('Collection Entry', 'MDI_SCHEME_COLLECTION'),
                mdiItem('Close Scheme', 'MDI_SCHEME_CLOSE'),
                mdiItem('Draw Process', 'MDI_SCHEME_CLOSE'),
                mdiItem('Interest Calculation', 'MDI_SCHEME_INTEREST'),
            ] },
            { label: 'Customer Greetings', items: [
                mdiItem('Customer Greetings', 'MDI_CUSTOMER_CAMPAIGN'),
            ] },
            { label: 'Customer Weight Deposit', items: [
                mdiItem('Deposit Entry', 'MDI_PARTY_WGT_DEPOSIT'),
                mdiItem('Edit Deposit', 'MDI_PARTY_WGT_DEPOSIT'),
                mdiItem('Cancel Deposit', 'MDI_PARTY_WGT_DEPOSIT'),
                mdiItem('Interest Calculation', 'MDI_PARTY_WGT_DEPOSIT'),
                mdiItem('Print Deposit', 'MDI_PARTY_WGT_DEPOSIT'),
            ] },
            { label: 'Diamond & Stone Entry', items: [
                mdiItem('New Purchase - New', 'MDI_DIAMOND_PURCHASE_BILL'),
                mdiItem('New Purchase - Edit', 'MDI_DIAMOND_PURCHASE_BILL'),
                mdiItem('New Purchase - Cancel', 'MDI_DIAMOND_PURCHASE_BILL'),
                mdiItem('New Purchase - Print', 'MDI_DIAMOND_PURCHASE_BILL'),
                mdiItem('Return Entry - New', 'MDI_DIAMOND_PURCHASE_RETURN'),
                mdiItem('Return Entry - Edit', 'MDI_DIAMOND_PURCHASE_RETURN'),
                mdiItem('Return Entry - Cancel', 'MDI_DIAMOND_PURCHASE_RETURN'),
                mdiItem('Return Entry - Print', 'MDI_DIAMOND_PURCHASE_RETURN'),
            ] },
            { label: 'Gold Rate Display', items: [
                mdiItem('Gold Rate Display', 'MDI_GOLD_RATE_STORY'),
            ] },
            { label: 'Goldsmith Work', items: [
                mdiItem('Cancel Work', 'MDI_GOLDSMITH_TRANSACTIONS'),
                mdiItem('Create Work Note', 'MDI_GOLDSMITH_TRANSACTIONS'),
                mdiItem('Edit Work', 'MDI_GOLDSMITH_TRANSACTIONS'),
                mdiItem('Print Work', 'MDI_GOLDSMITH_TRANSACTIONS'),
                mdiItem('Work Entry', 'MDI_GOLDSMITH_TRANSACTIONS'),
            ] },
            { label: 'Jewellery Processing', items: [
                mdiItem('Cancel Entry', 'MDI_JEWELLERY_TRANSACTIONS'),
                mdiItem('Edit Entry', 'MDI_JEWELLERY_TRANSACTIONS'),
                mdiItem('Jewellery Entry', 'MDI_JEWELLERY_TRANSACTIONS'),
                mdiItem('Print Entry', 'MDI_JEWELLERY_TRANSACTIONS'),
                mdiItem('Sync from Head Office', 'MDI_JEWELLERY_TRANSACTIONS'),
            ] },
            { label: 'Order Management', items: [
                mdiItem('Cancel Order', 'MDI_ORDER_CANCEL'),
                mdiItem('Edit Order', 'MDI_ORDER_UPDATE'),
                mdiItem('New Order', 'MDI_ORDER_BILL'),
                mdiItem('Reprint Order', 'MDI_ORDER_REPRINT'),
                mdiItem('Advance After', 'MDI_ORDER_ADVANCE_AFTER'),
                mdiItem('Convert to Sale', 'MDI_ORDER_SALE'),
                mdiItem('Direct Sale', 'MDI_ORDER_SALE'),
                mdiItem('Edit Sale', 'MDI_ORDER_SALE'),
                mdiItem('Fix Rate', 'MDI_ORDER_RATE_FIX'),
                mdiItem('Order Control', 'MDI_ORDER_BLOCK'),
                mdiItem('Sync Orders', 'MDI_ORDER_UPDATE'),
            ] },
            { label: 'Other Item Transactions', items: [
                mdiItem('Other Purchase - New', 'MDI_OTHER_ITEM_TRANS'),
                mdiItem('Other Purchase - Edit', 'MDI_OTHER_ITEM_TRANS'),
                mdiItem('Other Purchase - Cancel', 'MDI_OTHER_ITEM_TRANS'),
                mdiItem('Other Purchase - Reprint', 'MDI_OTHER_ITEM_TRANS'),
                mdiItem('Other Sales - New', 'MDI_OTHER_ITEM_TRANS'),
                mdiItem('Other Sales - Edit', 'MDI_OTHER_ITEM_TRANS'),
                mdiItem('Other Sales - Cancel', 'MDI_OTHER_ITEM_TRANS'),
                mdiItem('Other Sales - Reprint', 'MDI_OTHER_ITEM_TRANS'),
            ] },
            { label: 'Partner Deposit', items: [
                mdiItem('Cancel Deposit', 'MDI_WGT_RCPT_PMNT'),
                mdiItem('Edit Deposit', 'MDI_WGT_RCPT_PMNT'),
                mdiItem('Interest Calculation', 'MDI_DEPOSITORS_INT_POST'),
                mdiItem('New Deposit', 'MDI_WGT_RCPT_PMNT'),
                mdiItem('Opening Weight', 'MDI_PARTY_OP_WEIGHT'),
                mdiItem('Print Deposit', 'MDI_WGT_RCPT_PMNT'),
            ] },
            { label: 'Sales Invoice', items: [
                mdiItem('Cancel Invoice', 'MDI_SALES_BILL_CANCEL'),
                mdiItem('Edit Invoice', 'MDI_SALES_BILL_EDIT'),
                mdiItem('Invoice Approval', 'MDI_SALES_BILL_CONFIRMATION'),
                mdiItem('New Invoice', 'MDI_SALES_BILL_NEW'),
                mdiItem('Quotation', 'MDI_SALES_QUOTATION'),
                mdiItem('Quotation Edit', 'MDI_SALES_QUOTATION_EDIT'),
                mdiItem('Quotation Cancel', 'MDI_SALES_QUOTATION_CANCEL'),
                mdiItem('Print Invoice', 'MDI_SALES_BILL_PRINT'),
                mdiItem('E-Invoice Register', 'MDI_EINVOICE_REGISTER'),
            ] },
            { label: 'Purchase Invoice', items: [
                mdiItem('Cancel Purchase', 'MDI_PURCHASE_BILL'),
                mdiItem('Edit Purchase', 'MDI_PURCHASE_BILL'),
                mdiItem('New Purchase', 'MDI_PURCHASE_BILL'),
                mdiItem('Print Purchase', 'MDI_PURCHASE_BILL'),
                mdiItem('Purchase Approval', 'MDI_PURCHASE_BILL_CONFIRMATION'),
            ] },
            { label: 'Sales Return', items: [
                mdiItem('Cancel Return', 'MDI_SALES_RETURN'),
                mdiItem('Edit Return', 'MDI_SALES_RETURN'),
                mdiItem('New Return', 'MDI_SALES_RETURN'),
                mdiItem('Print Return', 'MDI_SALES_RETURN'),
            ] },
            { label: 'Purchase Return', items: [
                mdiItem('Cancel Return', 'MDI_PURCHASE_RETURN'),
                mdiItem('Edit Return', 'MDI_PURCHASE_RETURN'),
                mdiItem('New Return', 'MDI_PURCHASE_RETURN'),
                mdiItem('Print Return', 'MDI_PURCHASE_RETURN'),
            ] },
            { label: 'Purity Check', items: [
                mdiItem('Cancel Test', 'MDI_PURITY_TESTING'),
                mdiItem('Edit Test', 'MDI_PURITY_TESTING'),
                mdiItem('New Test', 'MDI_PURITY_TESTING'),
                mdiItem('Print Test', 'MDI_PURITY_TESTING'),
                mdiItem('Purity Certificate', 'MDI_PURITY_CERTIFICATE'),
            ] },
            { label: 'Refinery Operations', items: [
                mdiItem('Cancel Entry', 'MDI_REFINERY_BILL'),
                mdiItem('Combined Entry', 'MDI_REFINERY_BILL'),
                mdiItem('Edit Entry', 'MDI_REFINERY_BILL'),
                mdiItem('New Refinery Entry', 'MDI_REFINERY_BILL'),
                mdiItem('Refinery Return', 'MDI_REFINERY_BILL'),
                mdiItem('Edit Return', 'MDI_REFINERY_BILL'),
            ] },
            { label: 'Repair & Remake', items: [
                mdiItem('Repair Slip - Receive from Customer - New', 'MDI_REPAIR_RECEIPT_MEMO_PARTY'),
                mdiItem('Repair Slip - Receive from Customer - Edit', 'MDI_REPAIR_RECEIPT_MEMO_PARTY'),
                mdiItem('Repair Slip - Receive from Customer - Cancel', 'MDI_REPAIR_RECEIPT_MEMO_PARTY'),
                mdiItem('Repair Slip - Receive from Customer - Reprint', 'MDI_REPAIR_RECEIPT_MEMO_PARTY'),
                mdiItem('Repair Slip - Return to Customer - New', 'MDI_REPAIR_SMITH_MEMO'),
                mdiItem('Repair Slip - Return to Customer - Edit', 'MDI_REPAIR_SMITH_MEMO'),
                mdiItem('Repair Slip - Return to Customer - Cancel', 'MDI_REPAIR_SMITH_MEMO'),
                mdiItem('Repair Slip - Return to Customer - Reprint', 'MDI_REPAIR_SMITH_MEMO'),
                mdiItem('Issue to Goldsmith - New', 'MDI_REPAIR_SMITH_MEMO'),
                mdiItem('Issue to Goldsmith - Edit', 'MDI_REPAIR_SMITH_MEMO'),
                mdiItem('Issue to Goldsmith - Cancel', 'MDI_REPAIR_SMITH_MEMO'),
                mdiItem('Issue to Goldsmith - Reprint', 'MDI_REPAIR_SMITH_MEMO'),
                mdiItem('Receive from Customer - New', 'MDI_REPAIR_RECEIPT_MEMO_PARTY'),
                mdiItem('Receive from Customer - Edit', 'MDI_REPAIR_RECEIPT_MEMO_PARTY'),
                mdiItem('Receive from Customer - Cancel', 'MDI_REPAIR_RECEIPT_MEMO_PARTY'),
                mdiItem('Receive from Customer - Reprint', 'MDI_REPAIR_RECEIPT_MEMO_PARTY'),
                mdiItem('Receive from Goldsmith - New', 'MDI_REPAIR_SMITH_MEMO'),
                mdiItem('Receive from Goldsmith - Edit', 'MDI_REPAIR_SMITH_MEMO'),
                mdiItem('Receive from Goldsmith - Cancel', 'MDI_REPAIR_SMITH_MEMO'),
                mdiItem('Receive from Goldsmith - Reprint', 'MDI_REPAIR_SMITH_MEMO'),
                mdiItem('Repair Completion - New', 'MDI_REPAIR_RETURN'),
                mdiItem('Repair Completion - Edit', 'MDI_REPAIR_RETURN'),
                mdiItem('Repair Completion - Cancel', 'MDI_REPAIR_RETURN'),
                mdiItem('Repair Completion - Reprint', 'MDI_REPAIR_RETURN'),
                mdiItem('Return to Customer - New', 'MDI_REPAIR_ISSUE_MEMO_PARTY'),
                mdiItem('Return to Customer - Edit', 'MDI_REPAIR_ISSUE_MEMO_PARTY'),
                mdiItem('Return to Customer - Cancel', 'MDI_REPAIR_ISSUE_MEMO_PARTY'),
                mdiItem('Return to Customer - Reprint', 'MDI_REPAIR_ISSUE_MEMO_PARTY'),
            ] },
            { label: 'Chitty Management', items: [
                mdiItem('Close Scheme', 'MDI_SCHEME_CLOSE'),
                mdiItem('Collection', 'MDI_SCHEME_COLLECTION'),
                mdiItem('Interest', 'MDI_SCHEME_INTEREST'),
                mdiItem('POS Sync', 'MDI_SCHEME_COLLECTION'),
                mdiItem('Passbook Print', 'MDI_SCHEME_PASSBOOK'),
                mdiItem('Refund', 'MDI_SCHEME_REFUND'),
                mdiItem('Register Customer', 'MDI_KURI_DETAILS'),
            ] },
            { label: 'Staff Management', items: [
                mdiItem('Attendance Log', 'MDI_STAFF_LOG_UPDATE'),
                mdiItem('Leave Management', 'MDI_STAFF_LEAVE_ENTRY'),
                mdiItem('Staff Entry', 'MDI_STAFF_TRANSACTION'),
                mdiItem('Weight Handling', 'MDI_STAFF_WGT_TRANSACTION'),
            ] },
            { label: 'Stock Management', items: [
                mdiItem('Barcode Transfer', 'MDI_ITEM_STOCK_ADJUSTMENT'),
                mdiItem('Bulk Transfer', 'MDI_ITEM_STOCK_ADJUSTMENT_MULTI'),
                mdiItem('Cancel Adjustment', 'MDI_ITEM_STOCK_ADJUSTMENT'),
                mdiItem('Edit Adjustment', 'MDI_ITEM_STOCK_ADJUSTMENT'),
                mdiItem('Stock Adjustment', 'MDI_ITEM_STOCK_ADD_LESS'),
                mdiItem('Transfer Stock', 'MDI_ITEM_STOCK_ADJUSTMENT'),
                mdiItem('Stock Suspense Entry', 'MDI_STOCK_SUSPENSE_ENTRY'),
            ] },
        ],
    },
    {
        label: 'Reports',
        groups: [
            { label: 'Financial Reports', items: [
                mdiItem('Account Ledger', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('All Transactions', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Average Profit', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Balance Sheet', 'MDI_FINANCIAL_STATEMENTS'),
                mdiItem('Bank Statement', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Cash Book', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Cash Flow Statement', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Chart of Accounts', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Data Validation', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Daybook', 'MDI_DAYBOOK'),
                mdiItem('Detailed Group List', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Group Ledger', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Group Summary', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Inactive Days', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Journal Report', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Post Dated Cheque', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Profit & Loss - Upto a Date', 'MDI_FINANCIAL_STATEMENTS'),
                mdiItem('Profit & Loss - For a Period', 'MDI_FINANCIAL_STATEMENTS'),
                mdiItem('Receipts & Payments', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Receivable & Payable', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Suspense Ledger', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Trial Balance - Upto a Date', 'MDI_FINANCIAL_STATEMENTS'),
                mdiItem('Trial Balance - Between Dates', 'MDI_FINANCIAL_STATEMENTS'),
                mdiItem('Yearly Cash Balance', 'MDI_FINANCIAL_REPORTS'),
            ] },
            { label: 'Barcode Reports', items: [
                mdiItem('Barcode History', 'MDI_BARCODE_REPORTS'),
                mdiItem('Barcode List', 'MDI_BARCODE_REPORTS'),
                mdiItem('Comparison Report', 'MDI_BARCODE_REPORTS'),
                mdiItem('Diamond Stock', 'MDI_BARCODE_REPORTS'),
                mdiItem('Sales Amount List', 'MDI_BARCODE_REPORTS'),
                mdiItem('Stock List', 'MDI_BARCODE_REPORTS'),
                mdiItem('Verification Report', 'MDI_BARCODE_REPORTS'),
            ] },
            { label: 'Chit Reports', items: [
                mdiItem('Collection Chart', 'MDI_CHITTY_ANALYTICS'),
                mdiItem('Collection Summary', 'MDI_CHITTY_ANALYTICS'),
            ] },
            { label: 'Cash Balance', items: [
                mdiItem('Cash Balance', 'MDI_FINANCIAL_REPORTS'),
            ] },
            { label: 'Customer Analytics', items: [
                mdiItem('Account Summary', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Billwise Receivables', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Collection Report', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Credit Details', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Customer History', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Customer List', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Customer Summary', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Due Date Report', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Invoice Details', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Loyalty History', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Payment History', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Received Payments', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Receivables', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Sales Summary', 'MDI_CUSTOMER_ANALYTICS'),
                mdiItem('Visit Tracking', 'MDI_CUSTOMER_ANALYTICS'),
            ] },
            { label: 'Deposit Analytics', items: [
                mdiItem('Deposit Ledger', 'MDI_DEPOSIT_ANALYTICS'),
                mdiItem('Deposit Summary', 'MDI_DEPOSIT_ANALYTICS'),
                mdiItem('Deposit Transactions', 'MDI_DEPOSIT_ANALYTICS'),
            ] },
            { label: 'Goldsmith Analytics', items: [
                mdiItem('Fixing Status', 'MDI_GOLDSMITH_ANALYTICS'),
                mdiItem('Goldsmith Ledger', 'MDI_SMITH_BOOK'),
                mdiItem('Goldsmith List', 'MDI_GOLDSMITH_ANALYTICS'),
                mdiItem('Lot Tracking', 'MDI_GOLDSMITH_ANALYTICS'),
                mdiItem('Pending Work Ageing', 'MDI_GOLDSMITH_ANALYTICS'),
                mdiItem('Weight & Amount Summary', 'MDI_GOLDSMITH_ANALYTICS'),
                mdiItem('Work Summary', 'MDI_GOLDSMITH_ANALYTICS'),
                mdiItem('Work Transactions', 'MDI_GOLDSMITH_ANALYTICS'),
            ] },
            { label: 'GST & Tax Reports', items: [
                mdiItem('E-Invoice Register', 'MDI_EINVOICE_REGISTER'),
                mdiItem('Goldsmith Tax Report', 'MDI_GST_TAX_REPORTS'),
                mdiItem('Jewellery Tax Report', 'MDI_GST_TAX_REPORTS'),
                mdiItem('Pending Tax Report', 'MDI_OUTSTANDING_TAX'),
                mdiItem('Purchase Tax Report', 'MDI_TAX_PURCHASE_BOOK'),
                mdiItem('Sales Tax Report', 'MDI_GST_TAX_REPORTS'),
                mdiItem('Stock Register', 'MDI_GST_TAX_REPORTS'),
            ] },
            { label: 'Inventory Reports', items: [
                mdiItem('Cost List', 'MDI_INVENTORY_REPORTS'),
                mdiItem('Item History', 'MDI_STOCK_ITEM_HISTORY'),
                mdiItem('Item Profit', 'MDI_INVENTORY_REPORTS'),
                mdiItem('Model Transfer', 'MDI_INVENTORY_REPORTS'),
                mdiItem('Rate History', 'MDI_INVENTORY_REPORTS'),
                mdiItem('Rate Report', 'MDI_INVENTORY_REPORTS'),
                mdiItem('Reorder Alert', 'MDI_INVENTORY_REPORTS'),
                mdiItem('Stock & Weight', 'MDI_INVENTORY_REPORTS'),
                mdiItem('Stock Adjustment', 'MDI_INVENTORY_REPORTS'),
                mdiItem('Stock Change Report', 'MDI_INVENTORY_REPORTS'),
                mdiItem('Stock Movement', 'MDI_INVENTORY_REPORTS'),
                mdiItem('Stock Register', 'MDI_STOCK_REGISTER'),
                mdiItem('Stock Summary', 'MDI_INVENTORY_REPORTS'),
                mdiItem('Stone Analysis', 'MDI_INVENTORY_REPORTS'),
                mdiItem('Transaction Analysis', 'MDI_INVENTORY_REPORTS'),
            ] },
            { label: 'Jewellery Analytics', items: [
                mdiItem('Account Summary', 'MDI_JEWELLERY_ANALYTICS'),
                mdiItem('Email Jewellery Report', 'MDI_JEWELLERY_ANALYTICS'),
                mdiItem('Extra Charges Report', 'MDI_JEWELLERY_ANALYTICS'),
                mdiItem('Item Movement Report', 'MDI_JEWELLERY_ANALYTICS'),
                mdiItem('Jewellery Ledger', 'MDI_JEWLLERY_BOOK'),
                mdiItem('Jewellery Summary', 'MDI_JEWELLERY_ANALYTICS'),
                mdiItem('Jewellery Transactions', 'MDI_JEWELLERY_ANALYTICS'),
                mdiItem('Profit Analysis', 'MDI_JEWELLERY_ANALYTICS'),
                mdiItem('Stock Ageing', 'MDI_JEWELLERY_ANALYTICS'),
                mdiItem('TDS Report', 'MDI_JEWELLERY_ANALYTICS'),
                mdiItem('Weight Analysis', 'MDI_JEWELLERY_ANALYTICS'),
                mdiItem('Weight Summary', 'MDI_JEWELLERY_ANALYTICS'),
            ] },
            { label: 'Order Analytics', items: [
                mdiItem('Advance Report', 'MDI_ORDER_ANALYTICS'),
                mdiItem('Order Entries', 'MDI_ORDER_ANALYTICS'),
                mdiItem('Order Numbers', 'MDI_ORDER_ANALYTICS'),
                mdiItem('Order Processing', 'MDI_ORDER_ANALYTICS'),
                mdiItem('Order Profit', 'MDI_ORDER_ANALYTICS'),
                mdiItem('Order Search', 'MDI_ORDER_ANALYTICS'),
                mdiItem('Pending Details', 'MDI_ORDER_ANALYTICS'),
                mdiItem('Pending Orders', 'MDI_ORDER_ANALYTICS'),
                mdiItem('Returned Orders', 'MDI_ORDER_ANALYTICS'),
                mdiItem('Sample Stock', 'MDI_ORDER_ANALYTICS'),
                mdiItem('Send to Head Office', 'MDI_ORDER_ANALYTICS'),
            ] },
            { label: 'Other Item Reports', items: [
                mdiItem('Purchase Ledger', 'MDI_OTHER_ITEM_REPORTS'),
                mdiItem('Purchase Register', 'MDI_OTHER_ITEM_REPORTS'),
                mdiItem('Sales Ledger', 'MDI_OTHER_ITEM_REPORTS'),
                mdiItem('Sales Register', 'MDI_OTHER_ITEM_REPORTS'),
                mdiItem('Stock Ledger', 'MDI_OTHER_ITEM_REPORTS'),
                mdiItem('Stock List', 'MDI_OTHER_ITEM_REPORTS'),
            ] },
            { label: 'Other Reports', items: [
                mdiItem('Daily Cash Book', 'MDI_OTHER_REPORTS'),
                mdiItem('Daily History', 'MDI_OTHER_REPORTS'),
                mdiItem('Daily Report', 'MDI_DAY_SUMMARY'),
                mdiItem('Daily Summary', 'MDI_OTHER_REPORTS'),
                mdiItem('Financial Summary', 'MDI_OTHER_REPORTS'),
                mdiItem('Malayalam Report', 'MDI_OTHER_REPORTS'),
                mdiItem('Month End Report', 'MDI_OTHER_REPORTS'),
                mdiItem('Print All Reports', 'MDI_OTHER_REPORTS'),
                mdiItem('Purity Report', 'MDI_OTHER_REPORTS'),
                mdiItem('Stock Value Report', 'MDI_OTHER_REPORTS'),
                mdiItem('Term Report', 'MDI_OTHER_REPORTS'),
            ] },
            { label: 'Partner Deposit Reports', items: [
                mdiItem('Deposit Ledger', 'MDI_DEPOSIT_ANALYTICS'),
                mdiItem('Weight Balance', 'MDI_DEPOSIT_ANALYTICS'),
            ] },
            { label: 'Purchase Analytics', items: [
                mdiItem('Credit Purchase Balance', 'MDI_SUPPLIER_ANALYTICS'),
                mdiItem('Purchase Ledger', 'MDI_PURCHASE_BOOK'),
                mdiItem('Purchase Register', 'MDI_PURCHASE_REGISTER'),
                mdiItem('Purchase Return Register', 'MDI_PURCHASE_RETURN_REGISTER'),
                mdiItem('Purchase Verification', 'MDI_PURCHASE_CHECK_LIST'),
            ] },
            { label: 'Refinery Analytics', items: [
                mdiItem('Account Summary', 'MDI_REFINERY_ANALYTICS'),
                mdiItem('Loss Comparison Report', 'MDI_REFINERY_ANALYTICS'),
                mdiItem('Refinery Analysis', 'MDI_REFINERY_ANALYTICS'),
                mdiItem('Refinery Summary', 'MDI_REFINERY_ANALYTICS'),
                mdiItem('Refinery Transactions', 'MDI_REFINERY_ANALYTICS'),
            ] },
            { label: 'Repair Analytics', items: [
                mdiItem('Pending Repairs', 'MDI_REPAIR_ANALYTICS'),
                mdiItem('Repair Reports', 'MDI_REPAIR_ANALYTICS'),
                mdiItem('Returned Repairs', 'MDI_REPAIR_ANALYTICS'),
            ] },
            { label: 'Sales Analytics', items: [
                mdiItem('Barcode Profit Analysis', 'MDI_BARCODE_PROFIT'),
                mdiItem('Barcode Sales Register', 'MDI_BARCODE_REGISTER'),
                mdiItem('Delivery Tracking', 'MDI_DELIVERY_STATUS_REPORT'),
                mdiItem('Loyalty Points Report', 'MDI_POINT_CARD_POINTS_REPORT'),
                mdiItem('Making Charge Profit', 'MDI_MC_PROFIT'),
                mdiItem('Monthly Sales Report', 'MDI_MONTHLY_SALES_REPORT'),
                mdiItem('Net Sales Summary', 'MDI_NET_SALES_REPORT'),
                mdiItem('Salesman Category Report', 'MDI_SALESMAN_CATEGORY_REPORT'),
                mdiItem('Sales Ledger', 'MDI_SALES_BOOK_REPORT'),
                mdiItem('Sales Register', 'MDI_SALES_REGISTER'),
                mdiItem('Sales Return Ledger', 'MDI_SALES_BOOK_REPORT'),
                mdiItem('Sales Return Register', 'MDI_SALES_RETURN_REGISTER'),
                mdiItem('Sales Verification', 'MDI_SALES_CHECK_LIST'),
                mdiItem('Tagged Items List', 'MDI_MARKED_LIST'),
                mdiItem('VA Report', 'MDI_VA_CHECK_REPORT'),
                mdiItem('VA Verification', 'MDI_VA_CHECK_LIST'),
            ] },
            { label: 'Chitty Management Analytics', items: [
                mdiItem('Collection Report', 'MDI_CHITTY_ANALYTICS'),
                mdiItem('Commission Report', 'MDI_CHITTY_ANALYTICS'),
                mdiItem('Maturity Report', 'MDI_CHITTY_ANALYTICS'),
                mdiItem('Scheme Details', 'MDI_CHITTY_ANALYTICS'),
                mdiItem('Scheme Ledger', 'MDI_CHITTY_ANALYTICS'),
            ] },
            { label: 'Staff Analytics', items: [
                mdiItem('Activity Log', 'MDI_STAFF_ANALYTICS'),
                mdiItem('Attendance Report', 'MDI_STAFF_ANALYTICS'),
                mdiItem('Leave Report', 'MDI_STAFF_ANALYTICS'),
                mdiItem('Salesman Report', 'MDI_STAFF_ANALYTICS'),
                mdiItem('Staff Ledger', 'MDI_STAFF_ANALYTICS'),
                mdiItem('Staff Summary', 'MDI_STAFF_ANALYTICS'),
                mdiItem('Term Summary', 'MDI_STAFF_ANALYTICS'),
                mdiItem('Weight Transactions', 'MDI_STAFF_ANALYTICS'),
            ] },
            { label: 'Stock Ledger', items: [
                mdiItem('Group Ledger', 'MDI_STOCK_PERIOD_LEDGER'),
                mdiItem('Item Ledger', 'MDI_STOCK_PERIOD_LEDGER'),
                mdiItem('Subgroup Ledger', 'MDI_STOCK_PERIOD_LEDGER'),
            ] },
            { label: 'Stock List', items: [
                mdiItem('Group List', 'MDI_STOCK_LEDGER'),
                mdiItem('Item List', 'MDI_STOCK_LEDGER'),
                mdiItem('Stock As On Date', 'MDI_STOCK_LEDGER'),
                mdiItem('Stock Checklist', 'MDI_STOCK_LEDGER'),
                mdiItem('Stock Type', 'MDI_STOCK_LEDGER'),
                mdiItem('Stock Audit - Verification Report', 'MDI_STOCK_LEDGER'),
                mdiItem('Stock Audit - Verification Entry', 'MDI_STOCK_VERIFICATION'),
            ] },
            { label: 'Supplier Analytics', items: [
                mdiItem('Account Summary', 'MDI_SUPPLIER_ANALYTICS'),
                mdiItem('Billwise Payables', 'MDI_SUPPLIER_ANALYTICS'),
                mdiItem('Due Date Report', 'MDI_SUPPLIER_ANALYTICS'),
                mdiItem('Payables', 'MDI_SUPPLIER_ANALYTICS'),
                mdiItem('Supplier List', 'MDI_SUPPLIER_ANALYTICS'),
            ] },
            { label: 'Term Report', items: [
                mdiItem('Term Report', 'MDI_STAFF_ANALYTICS'),
            ] },
            { label: 'Accounts Reports Shortcut', items: [
                mdiItem('Day Book', 'MDI_DAYBOOK'),
                mdiItem('Ledger', 'MDI_FINANCIAL_REPORTS'),
                mdiItem('Trial Balance', 'MDI_FINANCIAL_STATEMENTS'),
                mdiItem('Profit & Loss', 'MDI_FINANCIAL_STATEMENTS'),
                mdiItem('Balance Sheet', 'MDI_FINANCIAL_STATEMENTS'),
            ] },
            { label: 'Sales Reports Shortcut', items: [
                mdiItem('Daily Sales', 'MDI_SALES_REGISTER'),
                mdiItem('Monthly Sales', 'MDI_MONTHLY_SALES_REPORT'),
                mdiItem('Sales Register', 'MDI_SALES_REGISTER'),
            ] },
        ],
    },
    {
        label: 'System Tools',
        groups: [
            { label: 'System Administration', items: [mdiItem('System Administration', 'MDI_ADMINISTRATION')] },
            { label: 'Analytics & Contacts', items: [
                mdiItem('AI Analytics', 'MDI_AI_INSIGHTS'),
                mdiItem('Contact Directory', 'MDI_PHONE_BOOK'),
            ] },
            { label: 'Backup & Closing', items: [
                mdiItem('Data Backup & Restore', 'MDI_BACKUP'),
                mdiItem('Financial Year Closing', 'MDI_YEAR_END_ACCOUNT_CLOSE'),
            ] },
        ],
    },
    {
        label: 'Rate Update',
        groups: [
            { label: 'Gold Rate Update', items: [mdiItem('Rate Update', 'MDI_RATE_UPDATE')] },
        ],
    },
];
let allUsers = [];
let selectedCode = '';
let editMode = 'edit'; // 'add' or 'edit'
let loadingCount = 0;

/* ══════════════════════════════════════════════════════════
 *  Init
 * ══════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function() {
    renderCompanyAccess();
    renderPermGroups();
    bindEvents();
    resetUserSearch();
    refreshUsers();
    // Some browsers restore form values after load. Force the list filter back to empty.
    setTimeout(resetUserSearch, 100);
    setTimeout(resetUserSearch, 500);
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'F9') { e.preventDefault(); saveUser(); }
});

/* ══════════════════════════════════════════════════════════
 *  Render permission groups from config
 * ══════════════════════════════════════════════════════════ */
function renderPermGroups() {
    const mdiWrap = document.getElementById('mdiPermGroups');
    const softwareWrap = document.getElementById('softwarePermGroups');
    let mdiParts = [];
    let softwareHtml = '';
    const renderedMdiCodes = new Set();

    function buildPermGroupHtml(cat, items) {
        const entries = Object.entries(items || {});
        const visibleEntries = entries.filter(([code]) => !HIDDEN_SOFTWARE_PERMS.has(code));
        if (!visibleEntries.length) return '';
        let html = '<div class="perm-group collapsed" data-cat="' + esc(cat) + '" data-parent-menu="">';
        html += '<div class="perm-group-head">';
        html += '<div class="perm-group-head-left" style="display:flex; align-items:center; gap:8px;">';
        html += '<span class="perm-group-arrow">▶</span>';
        html += esc(cat) + ' <span class="count">' + visibleEntries.length + '</span></div>';
        html += '</div>';
        html += '<div class="perm-group-body">';
        for (const [code, desc] of visibleEntries) {
            html += '<label class="perm-item">';
            html += '<input type="checkbox" class="perm-cb" data-code="' + esc(code) + '" data-cat="' + esc(cat) + '" data-parent-menu="">';
            html += '<span class="perm-code">' + esc(code) + '</span>';
            html += '<span class="perm-desc">' + esc(desc) + '</span>';
            html += '</label>';
        }
        html += '</div></div>';
        return html;
    }

    function buildMdiMenuHtml(menu, groups) {
        let html = '<div class="menu-block collapsed">';
        html += '<div class="menu-block-head">';
        html += '<div class="menu-block-title"><span class="menu-block-arrow">▶</span>' + esc(menu.label) + ' <span class="menu-block-count">' + groups.length + ' SUBMENU</span></div>';
        html += '<label class="menu-block-toggle"><input type="checkbox" class="menu-block-cb" data-menu="' + esc(menu.label) + '"> TICK ALL</label>';
        html += '</div>';
        html += '<div class="menu-block-body">';
        groups.forEach(group => {
            html += '<div class="mdi-submenu-card">';
            html += '<div class="mdi-submenu-head">';
            html += '<div class="mdi-submenu-toggle" style="display:flex; align-items:center; gap:8px;">';
            html += '<span class="mdi-submenu-arrow">▶</span>';
            html += '<span class="mdi-submenu-title">' + esc(group.label) + '</span>';
            html += '</div>';
            html += '<div style="display:flex; align-items:center; gap:8px; pointer-events:none;">';
            html += '<span class="mdi-submenu-meta">' + group.items.length + ' PERM</span>';
            html += '</div>';
            html += '</div>';
            html += '<div class="mdi-submenu-items">';
            group.items.forEach(item => {
                html += '<label class="mdi-submenu-item">';
                html += '<input type="checkbox" class="mdi-child-cb" data-menu="' + esc(menu.label) + '" data-submenu="' + esc(group.label) + '" data-code="' + esc(item.code) + '">';
                html += '<span>' + esc(item.label || item.code) + '</span>';
                html += '</label>';
            });
            html += '</div>';
            html += '</div>';
            group.items.forEach(item => {
                html += '<input type="checkbox" class="perm-cb mdi-hidden" data-code="' + esc(item.code) + '" data-cat="' + esc(group.label) + '" data-parent-menu="' + esc(menu.label) + '" data-submenu="' + esc(group.label) + '">';
            });
        });
        html += '</div></div>';
        return html;
    }

    MDI_MENU_LAYOUT.forEach(menu => {
        const availableGroups = menu.groups
            .map(group => {
                const items = [];
                (group.items || []).forEach(item => {
                    const desc = findPermissionLabel(item.code);
                    if (desc) {
                        items.push(item);
                        renderedMdiCodes.add(item.code);
                    }
                });
                return { label: group.label, items };
            })
            .filter(group => group.items.length);
        if (!availableGroups.length) return;
        mdiParts.push(buildMdiMenuHtml(menu, availableGroups));
    });

    const leftoverMdiGroups = [];
    for (const [cat, items] of Object.entries(PERMS)) {
        if (String(cat).startsWith('MDI ')) {
            const leftovers = {};
            for (const [code, desc] of Object.entries(items || {})) {
                if (!renderedMdiCodes.has(code)) leftovers[code] = desc;
            }
            const leftoverItems = Object.entries(leftovers).map(([code, desc]) => ({
                code,
                label: String(desc || code).replace(/\s*\([^)]*\)\s*$/, ''),
            }));
            if (leftoverItems.length) {
                leftoverMdiGroups.push({
                    label: cat.replace(/^MDI\s+/, '') || 'Other',
                    items: leftoverItems,
                });
            }
        } else {
            const html = buildPermGroupHtml(cat, items);
            softwareHtml += html;
        }
    }
    if (leftoverMdiGroups.length) {
        mdiParts.push(buildMdiMenuHtml({ label: 'Other Menu Items' }, leftoverMdiGroups));
    }
    mdiWrap.innerHTML = mdiParts.join('');
    softwareWrap.innerHTML = softwareHtml;
    syncAllPermissionToggles();
}

function renderCompanyAccess() {
    const wrap = document.getElementById('companyAccessList');
    if (!wrap) return;
    if (!COMPANIES.length) {
        wrap.classList.remove('company-grid');
        wrap.innerHTML = '<div class="company-empty">No company found</div>';
        return;
    }
    wrap.classList.add('company-grid');
    wrap.innerHTML = COMPANIES.map(c => {
        const name = String(c.name || c.company_name || c.database || '').trim();
        const db = String(c.database || '').trim();
        return '<label class="company-item">' +
            '<input type="checkbox" class="company-cb" data-db="' + esc(db) + '">' +
            '<span><span class="company-name">' + esc(name) + '</span>' +
            '<span class="company-db">' + esc(db) + '</span></span>' +
            '</label>';
    }).join('');
}

function findPermissionLabel(code) {
    for (const items of Object.values(PERMS)) {
        if (items && Object.prototype.hasOwnProperty.call(items, code)) {
            return items[code];
        }
    }
    return '';
}

function bindEvents() {
    const userSearch = document.getElementById('userSearch');
    ['input', 'change', 'keyup', 'search'].forEach(evt => {
        userSearch.addEventListener(evt, filterUsers);
    });
    userSearch.addEventListener('focus', filterUsers);

    document.querySelectorAll('[data-action="add-user"]').forEach(btn => btn.addEventListener('click', addUser));
    document.querySelectorAll('[data-action="refresh-users"]').forEach(btn => btn.addEventListener('click', refreshUsers));
    document.querySelectorAll('[data-action="delete-user"]').forEach(btn => btn.addEventListener('click', deleteUser));
    document.querySelectorAll('[data-action="save-user"]').forEach(btn => btn.addEventListener('click', saveUser));
    document.querySelectorAll('[data-action="edit-user"]').forEach(btn => btn.addEventListener('click', editUser));
    document.querySelectorAll('[data-action="cancel-user"]').forEach(btn => btn.addEventListener('click', cancelUser));

    document.addEventListener('change', function(e) {
        if (e.target.closest('.company-cb')) {
            ensureEditableForPermissions();
            return;
        }
        const menuToggle = e.target.closest('.menu-block-cb');
        if (menuToggle) {
            ensureEditableForPermissions();
            const menuName = menuToggle.dataset.menu || '';
            document.querySelectorAll('.mdi-child-cb[data-menu="' + CSS.escape(menuName) + '"]').forEach(cb => {
                cb.checked = menuToggle.checked;
                syncHiddenPermForChild(cb);
            });
            syncAllPermissionToggles();
            return;
        }

        const childToggle = e.target.closest('.mdi-child-cb');
        if (childToggle) {
            ensureEditableForPermissions();
            syncHiddenPermForChild(childToggle);
            syncAllPermissionToggles();
            return;
        }

        if (e.target.closest('.perm-cb')) {
            syncAllPermissionToggles();
        }
    });

    document.addEventListener('click', function(e) {
        const menuHead = e.target.closest('.menu-block-head');
        if (menuHead) {
            if (e.target.closest('.menu-block-toggle')) {
                return;
            }
            const block = menuHead.closest('.menu-block');
            if (block) {
                block.classList.toggle('collapsed');
            }
            return;
        }

        const head = e.target.closest('.mdi-submenu-head');
        if (head) {
            if (e.target.type === 'checkbox') {
                return;
            }
            const card = head.closest('.mdi-submenu-card');
            if (card) {
                card.classList.toggle('collapsed');
            }
            return;
        }

        const pgHead = e.target.closest('.perm-group-head');
        if (pgHead) {
            if (e.target.type === 'checkbox') {
                return;
            }
            const group = pgHead.closest('.perm-group');
            if (group) {
                group.classList.toggle('collapsed');
            }
        }
    });

    document.getElementById('userList').addEventListener('click', function(e) {
        const row = e.target.closest('.user-item');
        if (!row || !row.dataset.code) return;
        selectUser(row.dataset.code);
    });
}

function resetUserSearch() {
    const input = document.getElementById('userSearch');
    if (!input) return;
    if (input.value !== '') {
        input.value = '';
    }
    renderUserList();
}

function syncHiddenPermForChild(childCb) {
    const code = childCb.dataset.code || '';
    const hidden = document.querySelector('.perm-cb.mdi-hidden[data-code="' + CSS.escape(code) + '"]');
    if (hidden) hidden.checked = childCb.checked;
}

function ensureEditableForPermissions() {
    if (document.getElementById('btnEdit').style.display !== 'none' && selectedCode) {
        editUser();
    }
}

/* ══════════════════════════════════════════════════════════
 *  Load users list
 * ══════════════════════════════════════════════════════════ */
function refreshUsers() {
    showLoading('Loading users...');
    fetch(BASE + '/api/user-access/users')
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { toast(d.error || 'Failed to load users', 'error'); return; }
            allUsers = d.users || [];
            renderUserList();
            // Re-select current user if still exists
            if (selectedCode) {
                const exists = allUsers.find(u => u.code.trim().toLowerCase() === selectedCode.toLowerCase());
                if (exists) loadUser(selectedCode);
                else clearForm();
            }
            if (!selectedCode) {
                resetUserSearch();
            }
        })
        .catch(e => toast('Network error', 'error'))
        .finally(() => hideLoading());
}
window.refreshUsers = refreshUsers;

function renderUserList() {
    const wrap = document.getElementById('userList');
    const filter = document.getElementById('userSearch').value.toLowerCase();
    let html = '';
    allUsers.forEach(u => {
        const code = (u.code || '').trim();
        const name = (u.name || '').trim();
        if (filter && code.toLowerCase().indexOf(filter) < 0 && name.toLowerCase().indexOf(filter) < 0) return;
        const active = code.toLowerCase() === selectedCode.toLowerCase() ? ' active' : '';
        html += '<div class="user-item' + active + '" data-code="' + esc(code) + '">';
        html += '<span class="code">' + esc(code) + '</span>';
        html += '<span class="name">' + esc(name) + '</span>';
        html += '</div>';
    });
    if (!html) html = '<div style="padding:12px;color:#94a3b8;font-size:11px;text-align:center">No users found</div>';
    wrap.innerHTML = html;
}

function filterUsers() { renderUserList(); }
window.filterUsers = filterUsers;

/* ══════════════════════════════════════════════════════════
 *  Select / Load user
 * ══════════════════════════════════════════════════════════ */
function selectUser(code) {
    selectedCode = code;
    editMode = 'edit';
    renderUserList();
    loadUser(code);
}
window.selectUser = selectUser;

function loadUser(code) {
    showLoading('Loading user details...');
    fetch(BASE + '/api/user-access/user?code=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { toast(d.error || 'Failed to load user', 'error'); return; }
            const u = d.user;
            document.getElementById('uCode').value = (u.code || '').trim();
            document.getElementById('uCode').readOnly = true;
            document.getElementById('uCode').classList.add('ro');
            document.getElementById('uName').value = (u.name || '').trim();
            document.getElementById('uPassword').value = '';
            document.getElementById('uPasswordConfirm').value = '';

            // Numeric limits
            document.getElementById('limMaxDisc').value      = num(u.maxdisc);
            document.getElementById('limMaxCredit').value     = num(u.maxcredit);
            document.getElementById('limMinVaPerc').value     = num(u.minvaperc);
            document.getElementById('limMaxDiscPerc').value   = num(u.maxdiscperc);
            document.getElementById('limMaxAdjWgtBc').value   = num(u.maxadjwgtbc);

            // Permissions
            unselectAll();
            (d.permissions || []).forEach(p => {
                const code = String(p || '').trim().toUpperCase();
                const cb = document.querySelector('.perm-cb:not(.mdi-hidden)[data-code="' + CSS.escape(code) + '"]');
                if (cb) cb.checked = true;
                document.querySelectorAll('.mdi-child-cb[data-code="' + CSS.escape(code) + '"]').forEach(child => {
                    child.checked = true;
                });
                const hidden = document.querySelector('.perm-cb.mdi-hidden[data-code="' + CSS.escape(code) + '"]');
                if (hidden) hidden.checked = true;
            });
            syncAllPermissionToggles();
            document.querySelectorAll('.company-cb').forEach(cb => cb.checked = false);
            (d.companies || []).forEach(db => {
                const cb = document.querySelector('.company-cb[data-db="' + CSS.escape(String(db || '').trim()) + '"]');
                if (cb) cb.checked = true;
            });

            // Keep every menu section open so each file is visible one by one.
            document.querySelectorAll('.menu-block').forEach(block => {
                block.classList.remove('collapsed');
            });
            document.querySelectorAll('.mdi-submenu-card').forEach(card => {
                card.classList.remove('collapsed');
            });

            // Keep software option groups open too; users can still click a heading to collapse it.
            document.querySelectorAll('.perm-group').forEach(group => {
                group.classList.remove('collapsed');
            });

            editMode = 'edit';
            setViewMode();
            const savedPerms = (d.permissions || []).map(p => String(p || '').trim().toUpperCase());
            const hasAnyMdi = savedPerms.some(p => p.startsWith('MDI_'));
            const hasDashboard = savedPerms.includes('MDI_DASHBOARD');
            if (hasAnyMdi && !hasDashboard) {
                setStatus('Dashboard access is disabled for this user.', 'warn');
            } else {
                setStatus('Loaded: ' + (u.code || '').trim(), 'ok');
            }
        })
        .catch(e => toast('Network error', 'error'))
        .finally(() => hideLoading());
}

/* ══════════════════════════════════════════════════════════
 *  Edit / Cancel
 * ══════════════════════════════════════════════════════════ */
function editUser() {
    document.getElementById('btnSave').style.display = '';
    document.getElementById('btnEdit').style.display = 'none';
    document.getElementById('uName').readOnly = false;
    document.getElementById('uPassword').readOnly = false;
    document.getElementById('uPasswordConfirm').readOnly = false;
    document.getElementById('limMaxDisc').readOnly = false;
    document.getElementById('limMaxCredit').readOnly = false;
    document.getElementById('limMinVaPerc').readOnly = false;
    document.getElementById('limMaxDiscPerc').readOnly = false;
    document.getElementById('limMaxAdjWgtBc').readOnly = false;
    document.querySelectorAll('.perm-cb').forEach(cb => cb.disabled = false);
    document.querySelectorAll('.mdi-child-cb').forEach(cb => cb.disabled = false);
    document.querySelectorAll('.company-cb').forEach(cb => cb.disabled = false);
    document.querySelectorAll('.perm-toolbar .btn').forEach(b => b.disabled = false);
    setStatus('Editing: ' + selectedCode, '');
}
window.editUser = editUser;

function cancelUser() {
    if (editMode === 'add') {
        clearForm();
        setStatus('', '');
    } else if (selectedCode) {
        loadUser(selectedCode);
    } else {
        clearForm();
        setStatus('', '');
    }
}
window.cancelUser = cancelUser;

function setViewMode() {
    document.getElementById('btnSave').style.display = 'none';
    document.getElementById('btnEdit').style.display = '';
    document.getElementById('uName').readOnly = true;
    document.getElementById('uPassword').readOnly = true;
    document.getElementById('uPasswordConfirm').readOnly = true;
    document.getElementById('limMaxDisc').readOnly = true;
    document.getElementById('limMaxCredit').readOnly = true;
    document.getElementById('limMinVaPerc').readOnly = true;
    document.getElementById('limMaxDiscPerc').readOnly = true;
    document.getElementById('limMaxAdjWgtBc').readOnly = true;
    document.querySelectorAll('.perm-cb').forEach(cb => cb.disabled = true);
    document.querySelectorAll('.mdi-child-cb').forEach(cb => cb.disabled = true);
    document.querySelectorAll('.company-cb').forEach(cb => cb.disabled = true);
    document.querySelectorAll('.perm-toolbar .btn').forEach(b => b.disabled = false);
}

/* ══════════════════════════════════════════════════════════
 *  Add new user
 * ══════════════════════════════════════════════════════════ */
function addUser() {
    clearForm();
    editMode = 'add';
    document.getElementById('uCode').readOnly = false;
    document.getElementById('uCode').classList.remove('ro');
    // show Save, hide Edit, enable all fields
    document.getElementById('btnSave').style.display = '';
    document.getElementById('btnEdit').style.display = 'none';
    document.getElementById('uName').readOnly = false;
    document.getElementById('uPassword').readOnly = false;
    document.getElementById('uPasswordConfirm').readOnly = false;
    document.getElementById('limMaxDisc').readOnly = false;
    document.getElementById('limMaxCredit').readOnly = false;
    document.getElementById('limMinVaPerc').readOnly = false;
    document.getElementById('limMaxDiscPerc').readOnly = false;
    document.getElementById('limMaxAdjWgtBc').readOnly = false;
    document.querySelectorAll('.perm-cb').forEach(cb => cb.disabled = false);
    document.querySelectorAll('.mdi-child-cb').forEach(cb => cb.disabled = false);
    document.querySelectorAll('.company-cb').forEach(cb => cb.disabled = false);
    document.querySelectorAll('.perm-toolbar .btn').forEach(b => b.disabled = false);
    // Default permissions for new users
    document.getElementById('uCode').focus();
    setStatus('New user mode — enter details', '');
}
window.addUser = addUser;

function clearForm() {
    selectedCode = '';
    document.getElementById('uCode').value = '';
    document.getElementById('uCode').readOnly = false;
    document.getElementById('uCode').classList.remove('ro');
    document.getElementById('uName').value = '';
    document.getElementById('uPassword').value = '';
    document.getElementById('uPasswordConfirm').value = '';
    document.getElementById('limMaxDisc').value = '0';
    document.getElementById('limMaxCredit').value = '0';
    document.getElementById('limMinVaPerc').value = '0';
    document.getElementById('limMaxDiscPerc').value = '0';
    document.getElementById('limMaxAdjWgtBc').value = '0';
    unselectAll();
    document.querySelectorAll('.company-cb').forEach(cb => {
        cb.checked = false;
        cb.disabled = false;
    });
    // Show the full permission list after clearing or adding a user.
    document.querySelectorAll('.mdi-submenu-card').forEach(card => {
        card.classList.remove('collapsed');
    });
    document.querySelectorAll('.perm-group').forEach(group => {
        group.classList.remove('collapsed');
    });
    renderUserList();
}

/* ══════════════════════════════════════════════════════════
 *  Save user
 * ══════════════════════════════════════════════════════════ */
function saveUser() {
    const code = document.getElementById('uCode').value.trim();
    const name = document.getElementById('uName').value.trim();
    const pw   = document.getElementById('uPassword').value;
    const pwc  = document.getElementById('uPasswordConfirm').value;

    if (!code || !name) { toast('User Code and Name are required', 'error'); return; }
    if (pw && pw !== pwc) { toast('Passwords do not match', 'error'); return; }
    if (editMode === 'add' && !pw) { toast('Password is required for new user', 'error'); return; }
    if (pw && pw.length < 4) { toast('Password must be at least 4 characters', 'error'); return; }

    // MDI items: read from visible child checkboxes (source of truth); software options: non-hidden perm-cb
    const permsSet = new Set();
    document.querySelectorAll('.mdi-child-cb:checked').forEach(cb => { if (cb.dataset.code) permsSet.add(cb.dataset.code.trim().toUpperCase()); });
    document.querySelectorAll('.perm-cb:checked:not(.mdi-hidden)').forEach(cb => { if (cb.dataset.code) permsSet.add(cb.dataset.code.trim().toUpperCase()); });
    const perms = [...permsSet];
    const companies = [];
    document.querySelectorAll('.company-cb:checked').forEach(cb => {
        if (cb.dataset.db) companies.push(cb.dataset.db.trim());
    });

    const body = {
        mode:         editMode,
        code:         code,
        name:         name,
        password:     pw,
        permissions:  perms,
        companies:    companies,
        maxdisc:      parseFloat(document.getElementById('limMaxDisc').value) || 0,
        maxcredit:    parseFloat(document.getElementById('limMaxCredit').value) || 0,
        minvaperc:    parseFloat(document.getElementById('limMinVaPerc').value) || 0,
        maxdiscperc:  parseFloat(document.getElementById('limMaxDiscPerc').value) || 0,
        maxadjwgtbc:  parseFloat(document.getElementById('limMaxAdjWgtBc').value) || 0,
    };

    showLoading(editMode === 'add' ? 'Saving new user...' : 'Updating user...');
    fetch(BASE + '/api/user-access/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(d => {
        if (!d.ok) { toast(d.error || 'Save failed', 'error'); return; }
        toast(d.message || 'Saved', 'success');
        selectedCode = code;
        editMode = 'edit';
        document.getElementById('uCode').readOnly = true;
        document.getElementById('uCode').classList.add('ro');
        document.getElementById('uPassword').value = '';
        document.getElementById('uPasswordConfirm').value = '';
        setViewMode();
        refreshUsers();
    })
    .catch(e => toast('Network error', 'error'))
    .finally(() => hideLoading());
}
window.saveUser = saveUser;

/* ══════════════════════════════════════════════════════════
 *  Delete user
 * ══════════════════════════════════════════════════════════ */
function deleteUser() {
    if (!selectedCode) { toast('Select a user first', 'error'); return; }
    if (selectedCode.toLowerCase() === 'admin') { toast('Cannot delete admin user', 'error'); return; }
    if (!confirm('Delete user "' + selectedCode + '"?\nThis will remove all permissions and history.')) return;

    showLoading('Deleting user...');
    fetch(BASE + '/api/user-access/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ code: selectedCode })
    })
    .then(r => r.json())
    .then(d => {
        if (!d.ok) { toast(d.error || 'Delete failed', 'error'); return; }
        toast(d.message || 'Deleted', 'success');
        clearForm();
        refreshUsers();
    })
    .catch(e => toast('Network error', 'error'))
    .finally(() => hideLoading());
}
window.deleteUser = deleteUser;

/* ══════════════════════════════════════════════════════════
 *  Permission bulk actions
 * ══════════════════════════════════════════════════════════ */
function selectAll() {
    ensureEditableForPermissions();
    document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = true);
    document.querySelectorAll('.mdi-child-cb').forEach(cb => cb.checked = true);
    syncAllPermissionToggles();
}
window.selectAll = selectAll;

function unselectAll() {
    ensureEditableForPermissions();
    document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = false);
    document.querySelectorAll('.mdi-child-cb').forEach(cb => cb.checked = false);
    syncAllPermissionToggles();
}
window.unselectAll = unselectAll;

function syncMdiSubmenuToggles() {
    document.querySelectorAll('.menu-block-cb').forEach(toggle => {
        const menu = toggle.dataset.menu || '';
        const children = Array.from(document.querySelectorAll('.mdi-child-cb[data-menu="' + CSS.escape(menu) + '"]'));
        if (!children.length) return;
        const checkedCount = children.filter(cb => cb.checked).length;
        toggle.indeterminate = checkedCount > 0 && checkedCount < children.length;
        toggle.checked = checkedCount === children.length && checkedCount > 0;
    });
}

function syncAllPermissionToggles() {
    syncMdiSubmenuToggles();
}

/* ══════════════════════════════════════════════════════════
 *  Helpers
 * ══════════════════════════════════════════════════════════ */
function num(v) { return v != null ? parseFloat(v) || 0 : 0; }

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function setStatus(msg, cls) {
    const el = document.getElementById('statusMsg');
    el.textContent = msg;
    el.className = 'status' + (cls ? ' ' + cls : '');
}

function toast(msg, type) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'toast ' + (type || 'success') + ' show';
    setTimeout(() => el.classList.remove('show'), 3000);
}

function showLoading(text) {
    loadingCount++;
    document.getElementById('loadingText').textContent = text || 'Loading...';
    document.getElementById('loadingOverlay').classList.add('show');
}

function hideLoading() {
    loadingCount = Math.max(0, loadingCount - 1);
    if (loadingCount === 0) {
        document.getElementById('loadingOverlay').classList.remove('show');
    }
}

})();
</script>
</body>
</html>
