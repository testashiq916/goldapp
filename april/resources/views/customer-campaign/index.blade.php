<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRM Greetings & Campaigns</title>
    <style>
        :root{
            --bg:#edf2f7;
            --panel:#ffffff;
            --panel-2:#fffaf0;
            --text:#0f172a;
            --muted:#64748b;
            --line:#d7dee8;
            --brand:#b8860b;
            --brand-2:#f6e3a7;
            --shadow:0 24px 60px rgba(15,23,42,.10);
            --success:#16a34a;
            --danger:#dc2626;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:linear-gradient(180deg,#f6f8fb 0%,#e8eef5 100%);color:var(--text)}
        button,input,select,textarea{font:inherit}
        .page{padding:18px}
        .wrap{max-width:1880px;margin:0 auto}
        .top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:16px}
        .title h1{margin:0;font-size:34px;line-height:1.05}
        .title p{margin:6px 0 0;color:var(--muted)}
        .quick{display:flex;gap:10px;flex-wrap:wrap}
        .btn{border:1px solid #d7bf71;background:linear-gradient(180deg,#fff7dc 0%,#efd38e 100%);color:#4d3500;padding:11px 16px;border-radius:14px;font-weight:700;cursor:pointer;transition:all .15s}
        .btn:hover{filter:brightness(.96);transform:translateY(-1px)}
        .btn:active{transform:translateY(0)}
        .btn.alt{background:#fff;border-color:#cbd5e1;color:#334155}
        .btn:disabled{opacity:.5;cursor:not-allowed;transform:none}
        .btn.green{border-color:#86efac;background:linear-gradient(180deg,#f0fdf4 0%,#bbf7d0 100%);color:#14532d}
        .btn.red{border-color:#fca5a5;background:linear-gradient(180deg,#fef2f2 0%,#fecaca 100%);color:#7f1d1d}
        .grid{display:grid;grid-template-columns:460px minmax(0,1fr);gap:18px}
        .panel{background:rgba(255,255,255,.92);border:1px solid rgba(148,163,184,.28);border-radius:26px;box-shadow:var(--shadow)}
        .left{padding:18px;position:sticky;top:10px;align-self:start;max-height:95vh;overflow-y:auto}
        .section+.section{margin-top:16px}
        .section h2{margin:0 0 12px;font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:#475569}
        .field{display:grid;gap:7px;margin-bottom:11px}
        .field label{font-size:12px;font-weight:700;color:#334155}
        .field input,.field select,.field textarea{width:100%;border:1px solid var(--line);border-radius:14px;padding:11px 12px;background:#fff;color:var(--text);transition:border-color .2s}
        .field input:focus,.field select:focus,.field textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(184,134,11,.12)}
        .field textarea{min-height:108px;resize:vertical}
        .g2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .tags{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
        .chip{border:1px solid #dec47b;background:#fff9e7;color:#6c4e0d;border-radius:14px;padding:11px 10px;text-align:left;font-weight:700;cursor:pointer;transition:all .15s}
        .chip:hover{background:#fff3d0;border-color:#c9a83e}
        .chip.active{background:linear-gradient(135deg,#b8860b 0%,#d4a017 100%);color:#fff;border-color:#a67c00;box-shadow:0 4px 16px rgba(184,134,11,.35)}
        .chip.active small{color:#fde68a}
        .chip small{display:block;color:#8a6c23;font-weight:500;margin-top:2px}
        .right{padding:16px}
        .summary{display:grid;grid-template-columns:260px 1fr 1fr 1fr;gap:12px;margin-bottom:12px}
        .card{background:linear-gradient(180deg,#fffdfa 0%,#f7f0de 100%);border:1px solid #ead9aa;border-radius:18px;padding:14px;transition:transform .15s}
        .card:hover{transform:translateY(-2px)}
        .card .k{font-size:12px;color:#7c6a38;text-transform:uppercase;letter-spacing:.08em}
        .card .v{margin-top:6px;font-size:28px;font-weight:800}
        .table-wrap{overflow:auto;border:1px solid var(--line);border-radius:18px;background:#fff;max-height:65vh}
        table{width:100%;border-collapse:collapse;min-width:1040px}
        th,td{padding:10px 9px;border-bottom:1px solid #edf2f7;vertical-align:top}
        th{position:sticky;top:0;background:#fff7e3;color:#6b5311;font-size:12px;text-transform:uppercase;letter-spacing:.05em;z-index:2}
        td{font-size:13px}
        tr:hover td{background:#fcf8ee}
        .name{font-weight:700}
        .muted{color:var(--muted)}
        .msgbox{white-space:pre-wrap;line-height:1.45;max-width:420px}
        .rowbtns{display:flex;gap:6px;flex-wrap:wrap}
        .mini{border:1px solid #d7dee8;background:#fff;border-radius:10px;padding:7px 10px;font-size:12px;font-weight:700;cursor:pointer;transition:all .15s}
        .mini:hover{transform:translateY(-1px)}
        .mini:active{transform:translateY(0)}
        .mini.wa{border-color:#8bd0a3;background:#effcf3;color:#11673c}
        .mini.wa:hover{background:#d1fae5}
        .mini.sms{border-color:#b8c6f5;background:#f3f6ff;color:#2442a4}
        .mini.sms:hover{background:#dbeafe}
        .mini.mail{border-color:#f4c0c0;background:#fff4f4;color:#a43232}
        .mini.mail:hover{background:#fee2e2}
        .mini:hover{background:#f1f5f9}
        .toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:0 0 12px}
        .toolbar .leftset,.toolbar .rightset{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .small{font-size:12px;color:var(--muted)}
        .char-info{display:flex;gap:16px;margin-top:6px;font-size:11px;color:var(--muted)}
        .char-info span{background:#f1f5f9;padding:2px 8px;border-radius:8px}

        /* Overlay / Modal */
        .overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(3px)}
        .overlay.open{display:flex}
        .modal{background:#fff;border-radius:24px;box-shadow:0 32px 80px rgba(0,0,0,.25);width:580px;max-width:95vw;max-height:90vh;overflow-y:auto;padding:0}
        .modal-head{padding:20px 24px;border-bottom:1px solid #edf2f7;display:flex;justify-content:space-between;align-items:center}
        .modal-head h3{margin:0;font-size:20px}
        .modal-head .close{background:none;border:none;font-size:22px;cursor:pointer;color:var(--muted);padding:4px 8px;border-radius:8px}
        .modal-head .close:hover{background:#f1f5f9}
        .modal-body{padding:24px}
        .modal-foot{padding:16px 24px;border-top:1px solid #edf2f7;display:flex;justify-content:flex-end;gap:10px}

        /* Toast */
        .toast-area{position:fixed;top:20px;right:20px;z-index:2000;display:flex;flex-direction:column;gap:8px}
        .toast{padding:14px 20px;border-radius:14px;font-weight:600;font-size:14px;box-shadow:0 8px 24px rgba(0,0,0,.15);animation:toastIn .3s ease;min-width:280px}
        .toast.success{background:#f0fdf4;color:#14532d;border:1px solid #86efac}
        .toast.error{background:#fef2f2;color:#7f1d1d;border:1px solid #fca5a5}
        .toast.info{background:#eff6ff;color:#1e3a5f;border:1px solid #93c5fd}
        @keyframes toastIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}

        /* Progress bar */
        .progress-wrap{background:#e2e8f0;border-radius:10px;height:8px;overflow:hidden;margin:12px 0}
        .progress-bar{height:100%;background:linear-gradient(90deg,#b8860b,#d4a017);border-radius:10px;transition:width .3s;width:0}
        .progress-text{text-align:center;font-size:14px;color:var(--muted);margin-top:8px}

        /* Send summary list */
        .send-list{max-height:200px;overflow-y:auto;border:1px solid #edf2f7;border-radius:14px;margin:12px 0}
        .send-list-item{padding:8px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;display:flex;justify-content:space-between}
        .send-list-item:last-child{border-bottom:0}
        .send-list-item .sl-name{font-weight:600}
        .send-list-item .sl-phone{color:var(--muted)}

        /* Badges */
        .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
        .badge-success{background:#dcfce7;color:#14532d}
        .badge-pending{background:#fef9c3;color:#713f12}
        .badge-fail{background:#fee2e2;color:#991b1b}

        /* Loading spinner */
        .spinner{display:inline-block;width:16px;height:16px;border:2px solid #ccc;border-top-color:var(--brand);border-radius:50%;animation:spin .6s linear infinite;margin-right:6px;vertical-align:middle}
        @keyframes spin{to{transform:rotate(360deg)}}

        .empty-state{padding:60px 20px;text-align:center;color:var(--muted)}
        .empty-state .icon{font-size:48px;margin-bottom:12px}

        @media (max-width:1500px){.grid{grid-template-columns:1fr}.left{position:static;max-height:none}.summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:860px){.page{padding:10px}.top{flex-direction:column}.g2,.tags,.summary{grid-template-columns:1fr}.modal{width:100%;border-radius:16px}}
    </style>
</head>
<body>
@php $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); @endphp

<!-- Toast Area -->
<div class="toast-area" id="toastArea"></div>

<!-- Bulk Send Modal -->
<div class="overlay" id="sendModal">
    <div class="modal">
        <div class="modal-head">
            <h3 id="modalTitle">Send Campaign</h3>
            <button type="button" class="close" id="modalClose">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Filled dynamically -->
        </div>
        <div class="modal-foot" id="modalFoot">
            <button type="button" class="btn alt" id="modalCancel">Cancel</button>
            <button type="button" class="btn green" id="modalConfirm">Confirm & Send</button>
        </div>
    </div>
</div>

<div class="page">
    <div class="wrap">
        <div class="top">
            <div class="title">
                <h1>CRM Greetings & Campaigns</h1>
                <p>Customer birthdays, engagement and wedding anniversary greetings, daily gold-rate messages, scheme links, registration prompts, feedback requests, and offers.</p>
            </div>
            <div class="quick">
                <button class="btn alt" type="button" id="btnReload">&#8635; Refresh Customers</button>
                <button class="btn" type="button" id="btnOpenSelected" disabled>&#9993; Send Selected</button>
                <button class="btn alt" type="button" id="btnSendAll">&#9993; Send All Visible</button>
                <button class="btn alt" type="button" id="btnCopySelected">&#128203; Copy Selected Message</button>
            </div>
        </div>

        <div class="grid">
            <div class="panel left">
                <div class="section">
                    <h2>Campaign</h2>
                    <div class="tags" id="campaignChips">
                        <button class="chip active" type="button" data-campaign="all-customers">All Customers<small>Bulk campaign list</small></button>
                        <button class="chip" type="button" data-campaign="birthday">Birthday<small>Wish + offer</small></button>
                        <button class="chip" type="button" data-campaign="engagement">Engagement<small>Anniversary greeting</small></button>
                        <button class="chip" type="button" data-campaign="wedding">Wedding<small>Anniversary greeting</small></button>
                        <button class="chip" type="button" data-campaign="gold-rate">Gold Rate<small>Live 22K/18K/Silver</small></button>
                        <button class="chip" type="button" data-campaign="scheme">Scheme Link<small>Invite and registration</small></button>
                        <button class="chip" type="button" data-campaign="registration">Registration<small>Customer onboarding</small></button>
                        <button class="chip" type="button" data-campaign="feedback">Feedback<small>Review request</small></button>
                        <button class="chip" type="button" data-campaign="offer">Offer Send<small>Festival and promotion</small></button>
                    </div>
                </div>

                <div class="section">
                    <h2>Filters</h2>
                    <div class="g2">
                        <div class="field">
                            <label for="campaign">Campaign</label>
                            <select id="campaign"></select>
                        </div>
                        <div class="field">
                            <label for="channel">Channel</label>
                            <select id="channel">
                                <option value="whatsapp">WhatsApp</option>
                                <option value="sms">SMS</option>
                                <option value="email">Email</option>
                            </select>
                        </div>
                    </div>
                    <div class="g2">
                        <div class="field">
                            <label for="onDate">Occasion Date</label>
                            <input id="onDate" type="date">
                        </div>
                        <div class="field">
                            <label for="search">Search</label>
                            <input id="search" type="text" placeholder="Code / name / mobile / city">
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Template</h2>
                    <div class="field">
                        <label for="template">Choose Template</label>
                        <select id="template"></select>
                    </div>
                    <div class="field">
                        <label for="subject">Subject</label>
                        <input id="subject" type="text">
                    </div>
                    <div class="field">
                        <label for="message">Message Body</label>
                        <textarea id="message"></textarea>
                        <div class="char-info">
                            <span id="charCount">0 chars</span>
                            <span id="smsCount">1 SMS</span>
                            <span id="encodingInfo">GSM</span>
                        </div>
                    </div>
                    <div class="small">Placeholders: <code>@{{name}}</code>, <code>@{{shop_name}}</code>, <code>@{{gold_22k}}</code>, <code>@{{gold_18k}}</code>, <code>@{{silver}}</code>, <code>@{{scheme_link}}</code>, <code>@{{registration_link}}</code>, <code>@{{feedback_link}}</code>, <code>@{{offer_title}}</code>, <code>@{{shop_phone}}</code>, <code>@{{shop_address}}</code>, <code>@{{date}}</code></div>
                </div>

                <div class="section">
                    <h2>Links & Offer</h2>
                    <div class="field">
                        <label for="schemeLink">Scheme Link</label>
                        <input id="schemeLink" type="text" placeholder="https://...">
                    </div>
                    <div class="field">
                        <label for="registrationLink">Registration Link</label>
                        <input id="registrationLink" type="text" placeholder="https://...">
                    </div>
                    <div class="field">
                        <label for="feedbackLink">Feedback Link</label>
                        <input id="feedbackLink" type="text" placeholder="https://...">
                    </div>
                    <div class="field">
                        <label for="offerTitle">Offer Title</label>
                        <input id="offerTitle" type="text" placeholder="Festival Offer / VA Discount / Scheme Bonus">
                    </div>
                </div>
            </div>

            <div class="panel right">
                <div class="summary">
                    <div class="card">
                        <div class="k">Recipients</div>
                        <div class="v" id="countRecipients">0</div>
                        <div class="small" id="countSelected">0 selected</div>
                    </div>
                    <div class="card">
                        <div class="k">22K Gold</div>
                        <div class="v" id="rate22">0.00</div>
                    </div>
                    <div class="card">
                        <div class="k">18K Gold</div>
                        <div class="v" id="rate18">0.00</div>
                    </div>
                    <div class="card">
                        <div class="k">Silver</div>
                        <div class="v" id="rateSilver">0.00</div>
                    </div>
                </div>

                <div class="toolbar">
                    <div class="leftset">
                        <strong id="shopName"></strong>
                        <span class="small" id="shopMeta"></span>
                    </div>
                    <div class="rightset">
                        <label class="small" style="cursor:pointer"><input type="checkbox" id="chkSelectAll"> Select all</label>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th style="width:38px"></th>
                            <th style="width:210px">Customer</th>
                            <th style="width:180px">Contact</th>
                            <th style="width:150px">Occasion</th>
                            <th>Preview</th>
                            <th style="width:220px">Send</th>
                        </tr>
                        </thead>
                        <tbody id="rows"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const payload = {!! $payloadJson !!};
const state = {
    campaign: 'all-customers',
    channel: 'whatsapp',
    templates: payload.templates || {},
    recipients: [],
    sending: false,
};

const $ = (id) => document.getElementById(id);
const currency = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/* ======================== TOAST ======================== */
function showToast(message, type = 'info') {
    const area = $('toastArea');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = message;
    area.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; setTimeout(() => toast.remove(), 300); }, 3500);
}

/* ======================== CAMPAIGN CHIPS ======================== */
function setActiveCampaign(campaignKey) {
    state.campaign = campaignKey;

    // Update chip active state
    document.querySelectorAll('#campaignChips .chip').forEach(chip => {
        chip.classList.toggle('active', chip.dataset.campaign === campaignKey);
    });

    // Sync dropdown
    $('campaign').value = campaignKey;

    fillTemplates();
    loadRecipients();
}

document.querySelectorAll('#campaignChips .chip').forEach(chip => {
    chip.addEventListener('click', () => {
        setActiveCampaign(chip.dataset.campaign);
    });
});

/* ======================== HELPERS ======================== */
function templateOptions() {
    return {
        'all-customers': 'All Customers',
        birthday: 'Birthday',
        engagement: 'Engagement',
        wedding: 'Wedding',
        'gold-rate': 'Gold Rate',
        scheme: 'Scheme Link',
        registration: 'Registration',
        feedback: 'Feedback',
        offer: 'Offer Send',
    };
}

function fillCampaigns() {
    const sel = $('campaign');
    sel.innerHTML = Object.entries(templateOptions()).map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
    sel.value = state.campaign;
}

function fillTemplates() {
    const list = state.templates[state.campaign] || [];
    $('template').innerHTML = list.map((tpl, idx) => `<option value="${idx}">${tpl.name}</option>`).join('');
    const first = list[0] || { subject: '', body: '' };
    $('subject').value = first.subject || '';
    $('message').value = first.body || '';
    updateCharCount();
}

function applyBaseData() {
    $('onDate').value = payload.today || '';
    $('schemeLink').value = payload.links?.scheme_link || '';
    $('registrationLink').value = payload.links?.registration_link || '';
    $('feedbackLink').value = payload.links?.feedback_link || '';
    $('offerTitle').value = payload.links?.offer_title || '';
    $('shopName').textContent = payload.shop?.name || 'Shop';
    $('shopMeta').textContent = [payload.shop?.address || '', payload.shop?.phone || ''].filter(Boolean).join(' | ');
    $('rate22').textContent = currency.format(Number(payload.rates?.gold_22k || 0));
    $('rate18').textContent = currency.format(Number(payload.rates?.gold_18k || 0));
    $('rateSilver').textContent = currency.format(Number(payload.rates?.silver || 0));
}

function mergeTemplate(raw, row) {
    const replacements = {
        name: row?.name || '',
        shop_name: payload.shop?.name || '',
        shop_phone: payload.shop?.phone || '',
        shop_address: payload.shop?.address || '',
        date: formatDisplayDate($('onDate').value),
        gold_22k: currency.format(Number(payload.rates?.gold_22k || 0)),
        gold_18k: currency.format(Number(payload.rates?.gold_18k || 0)),
        silver: currency.format(Number(payload.rates?.silver || 0)),
        scheme_link: $('schemeLink').value.trim(),
        registration_link: $('registrationLink').value.trim(),
        feedback_link: $('feedbackLink').value.trim(),
        offer_title: $('offerTitle').value.trim(),
    };
    return String(raw || '').replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (_, key) => replacements[key] ?? '');
}

function formatDisplayDate(value) {
    if (!value) return '';
    const d = new Date(value + 'T00:00:00');
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' });
}

function occasionText(row) {
    const map = { birthday: row.dtbirthday, engagement: row.dtengagement, wedding: row.dtmarriage };
    const value = map[state.campaign] || $('onDate').value;
    return value ? formatDisplayDate(value) : '-';
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[ch]));
}

function phoneDigits(value) {
    const digits = String(value || '').replace(/\D+/g, '');
    if (digits.length === 10) return '91' + digits;
    return digits;
}

/* ======================== CHAR COUNT ======================== */
function updateCharCount() {
    const msg = $('message').value;
    const len = msg.length;
    const isUnicode = /[^\x00-\x7F]/.test(msg);
    const singleLimit = isUnicode ? 70 : 160;
    const multiLimit = isUnicode ? 67 : 153;
    let parts = len <= singleLimit ? 1 : Math.ceil(len / multiLimit);
    if (len === 0) parts = 0;

    $('charCount').textContent = len + ' chars';
    $('smsCount').textContent = (parts || 1) + ' SMS';
    $('encodingInfo').textContent = isUnicode ? 'Unicode' : 'GSM';
}

/* ======================== RENDER TABLE ======================== */
function renderRows() {
    const body = $('rows');
    body.innerHTML = '';
    $('countRecipients').textContent = String(state.recipients.length);
    $('countSelected').textContent = '0 selected';
    $('chkSelectAll').checked = false;
    updateSendSelectedBtn();

    if (state.recipients.length === 0) {
        body.innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="icon">&#128587;</div>No customers found for this campaign & filter combination.</div></td></tr>';
        return;
    }

    state.recipients.forEach((row, idx) => {
        const subject = mergeTemplate($('subject').value, row);
        const message = mergeTemplate($('message').value, row);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="checkbox" class="row-check" data-idx="${idx}"></td>
            <td>
                <div class="name">${escapeHtml(row.name || '')}</div>
                <div class="muted">${escapeHtml(row.code || '')}${row.city ? ' | ' + escapeHtml(row.city) : ''}</div>
            </td>
            <td>
                <div>${escapeHtml(row.mobile || row.telephone || '-')}</div>
                <div class="muted">${escapeHtml(row.email || '')}</div>
            </td>
            <td>${escapeHtml(occasionText(row))}</td>
            <td><div class="msgbox">${escapeHtml(message)}</div></td>
            <td>
                <div class="rowbtns">
                    <button class="mini wa" data-action="whatsapp" data-idx="${idx}" title="Send via WhatsApp">WhatsApp</button>
                    <button class="mini sms" data-action="sms" data-idx="${idx}" title="Send via SMS">SMS</button>
                    <button class="mini mail" data-action="email" data-idx="${idx}" title="Send via Email">Email</button>
                    <button class="mini" data-action="copy" data-idx="${idx}" title="Copy message">Copy</button>
                </div>
            </td>
        `;
        tr.dataset.subject = subject;
        tr.dataset.message = message;
        body.appendChild(tr);
    });
}

/* ======================== SELECTION ======================== */
function updateSelectedCount() {
    const count = document.querySelectorAll('.row-check:checked').length;
    $('countSelected').textContent = `${count} selected`;
    updateSendSelectedBtn();
}

function updateSendSelectedBtn() {
    const count = document.querySelectorAll('.row-check:checked').length;
    $('btnOpenSelected').disabled = count === 0;
}

function selectedRows() {
    return [...document.querySelectorAll('.row-check:checked')].map(el => state.recipients[Number(el.dataset.idx)]).filter(Boolean);
}

/* ======================== SEND LINKS ======================== */
function sendLink(channel, row) {
    const subject = mergeTemplate($('subject').value, row);
    const message = mergeTemplate($('message').value, row);
    const phone = phoneDigits(row.mobile || row.telephone || '');
    const email = (row.email || '').trim();

    if (channel === 'whatsapp') {
        if (!phone) return '';
        return 'https://wa.me/' + encodeURIComponent(phone) + '?text=' + encodeURIComponent(message);
    }
    if (channel === 'sms') {
        if (!phone) return '';
        return 'sms:' + encodeURIComponent(phone) + '?body=' + encodeURIComponent(message);
    }
    if (channel === 'email') {
        if (!email) return '';
        return 'mailto:' + encodeURIComponent(email) + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(message);
    }
    return '';
}

/* ======================== LOAD RECIPIENTS ======================== */
let loadingAbort = null;
async function loadRecipients() {
    if (loadingAbort) loadingAbort.abort();
    loadingAbort = new AbortController();

    $('rows').innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px"><span class="spinner"></span> Loading customers...</td></tr>';

    try {
        const params = new URLSearchParams({
            campaign: state.campaign,
            on_date: $('onDate').value || '',
            channel: $('channel').value || 'whatsapp',
            search: $('search').value.trim(),
        });
        const res = await fetch('{{ url("/api/customer-campaign/recipients") }}?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: loadingAbort.signal,
        });
        const json = await res.json();
        state.recipients = json.results || [];
        renderRows();
    } catch (err) {
        if (err.name !== 'AbortError') {
            $('rows').innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="icon">&#9888;</div>Failed to load customers. Check console for errors.</div></td></tr>';
            console.error('Load error:', err);
        }
    }
}

/* ======================== MODAL ======================== */
function openModal(title, bodyHtml, footHtml) {
    $('modalTitle').textContent = title;
    $('modalBody').innerHTML = bodyHtml;
    if (footHtml !== undefined) $('modalFoot').innerHTML = footHtml;
    $('sendModal').classList.add('open');
}

function closeModal() {
    if (state.sending) return; // Don't close during send
    $('sendModal').classList.remove('open');
}

$('modalClose').addEventListener('click', closeModal);
$('modalCancel').addEventListener('click', closeModal);
$('sendModal').addEventListener('click', (e) => { if (e.target === $('sendModal')) closeModal(); });

/* ======================== BULK SEND WITH MODAL ======================== */
function buildSendPreview(rows, channel) {
    const channelLabel = { whatsapp: 'WhatsApp', sms: 'SMS', email: 'Email' }[channel] || channel;
    let listHtml = '<div class="send-list">';
    rows.forEach(row => {
        const contact = channel === 'email' ? (row.email || '-') : (row.mobile || row.telephone || '-');
        listHtml += `<div class="send-list-item"><span class="sl-name">${escapeHtml(row.name)}</span><span class="sl-phone">${escapeHtml(contact)}</span></div>`;
    });
    listHtml += '</div>';

    return `
        <div style="margin-bottom:16px">
            <strong>Channel:</strong> ${channelLabel} &nbsp;&bull;&nbsp;
            <strong>Recipients:</strong> ${rows.length} customers
        </div>
        <div style="margin-bottom:12px">
            <strong>Message Preview</strong> (first customer):
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-top:8px;white-space:pre-wrap;font-size:13px;line-height:1.5">${escapeHtml(mergeTemplate($('message').value, rows[0]))}</div>
        </div>
        <div>
            <strong>Recipient List:</strong>
            ${listHtml}
        </div>
        <div class="progress-wrap" id="sendProgress" style="display:none">
            <div class="progress-bar" id="sendProgressBar"></div>
        </div>
        <div class="progress-text" id="sendProgressText" style="display:none"></div>
    `;
}

async function executeBulkSend(rows, channel) {
    state.sending = true;
    const progressWrap = document.getElementById('sendProgress');
    const progressBar = document.getElementById('sendProgressBar');
    const progressText = document.getElementById('sendProgressText');

    if (progressWrap) progressWrap.style.display = 'block';
    if (progressText) progressText.style.display = 'block';

    // Disable modal buttons during send
    $('modalFoot').innerHTML = '<button type="button" class="btn alt" disabled><span class="spinner"></span> Sending...</button>';

    let sent = 0;
    let failed = 0;
    const total = rows.length;

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const link = sendLink(channel, row);

        if (link) {
            try {
                window.open(link, '_blank', 'noopener');
                sent++;
            } catch {
                failed++;
            }
        } else {
            failed++;
        }

        const pct = Math.round(((i + 1) / total) * 100);
        if (progressBar) progressBar.style.width = pct + '%';
        if (progressText) progressText.textContent = `${i + 1} of ${total} processed (${sent} sent, ${failed} skipped)`;

        // Small delay to avoid popup blocker
        if (i < rows.length - 1) {
            await new Promise(r => setTimeout(r, 300));
        }
    }

    state.sending = false;

    // Show results
    $('modalFoot').innerHTML = '<button type="button" class="btn" onclick="closeModal()">Done</button>';
    if (progressText) {
        progressText.innerHTML = `<strong>Completed!</strong> ${sent} sent, ${failed} skipped`;
    }

    showToast(`Campaign sent: ${sent} of ${total} messages processed`, sent > 0 ? 'success' : 'error');
}

function openBulkSendModal(rows) {
    const channel = $('channel').value;

    if (!rows.length) {
        showToast('No customers to send.', 'error');
        return;
    }

    if (!$('message').value.trim()) {
        showToast('Please write a message first.', 'error');
        return;
    }

    // Check how many have valid contact for channel
    const valid = rows.filter(row => {
        if (channel === 'email') return (row.email || '').trim() !== '';
        return (row.mobile || row.telephone || '').trim() !== '';
    });

    if (!valid.length) {
        showToast(`No customers have valid ${channel} contact info.`, 'error');
        return;
    }

    const bodyHtml = buildSendPreview(valid, channel);
    const footHtml = `<button type="button" class="btn alt" id="modalCancel" onclick="closeModal()">Cancel</button>
                      <button type="button" class="btn green" id="modalConfirmSend">Confirm & Send (${valid.length})</button>`;

    openModal(`Send via ${channel.charAt(0).toUpperCase() + channel.slice(1)}`, bodyHtml, footHtml);

    // Attach confirm handler
    document.getElementById('modalConfirmSend').addEventListener('click', () => {
        executeBulkSend(valid, channel);
    });
}

/* ======================== COPY ======================== */
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showToast('Message copied to clipboard!', 'success');
    } catch {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showToast('Message copied to clipboard!', 'success');
    }
}

async function copySelectedMessage() {
    const rows = selectedRows();
    if (!rows.length) {
        showToast('Select at least one customer.', 'error');
        return;
    }
    const text = mergeTemplate($('message').value, rows[0]);
    await copyToClipboard(text);
}

/* ======================== ROW-LEVEL ACTIONS ======================== */
document.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-action]');
    if (!btn) return;
    const idx = Number(btn.dataset.idx);
    const row = state.recipients[idx];
    if (!row) return;

    const action = btn.dataset.action;

    if (action === 'copy') {
        const msg = mergeTemplate($('message').value, row);
        await copyToClipboard(msg);
        return;
    }

    const link = sendLink(action, row);
    if (!link) {
        showToast('Contact detail missing for this channel.', 'error');
        return;
    }

    window.open(link, '_blank', 'noopener');
    showToast(`Opened ${action} for ${row.name}`, 'success');
});

/* ======================== EVENT LISTENERS ======================== */

// Campaign dropdown change
$('campaign').addEventListener('change', () => {
    setActiveCampaign($('campaign').value);
});

// Channel & date & search
$('channel').addEventListener('change', loadRecipients);
$('onDate').addEventListener('change', loadRecipients);
$('search').addEventListener('input', () => {
    clearTimeout(window.__crmSearchTimer);
    window.__crmSearchTimer = setTimeout(loadRecipients, 300);
});

// Template change
$('template').addEventListener('change', () => {
    const list = state.templates[state.campaign] || [];
    const tpl = list[Number($('template').value)] || { subject: '', body: '' };
    $('subject').value = tpl.subject || '';
    $('message').value = tpl.body || '';
    updateCharCount();
    renderRows();
});

// Re-render on input changes
['subject', 'message', 'schemeLink', 'registrationLink', 'feedbackLink', 'offerTitle'].forEach(id => {
    $(id).addEventListener('input', () => {
        if (id === 'message') updateCharCount();
        renderRows();
    });
});

// Select all checkbox
$('chkSelectAll').addEventListener('change', () => {
    document.querySelectorAll('.row-check').forEach(box => box.checked = $('chkSelectAll').checked);
    updateSelectedCount();
});

// Individual row checkbox (delegated)
document.addEventListener('change', (event) => {
    if (event.target.classList.contains('row-check')) {
        updateSelectedCount();
    }
});

// Top buttons
$('btnReload').addEventListener('click', () => {
    loadRecipients();
    showToast('Refreshing customer list...', 'info');
});

$('btnOpenSelected').addEventListener('click', () => {
    openBulkSendModal(selectedRows());
});

$('btnSendAll').addEventListener('click', () => {
    openBulkSendModal([...state.recipients]);
});

$('btnCopySelected').addEventListener('click', copySelectedMessage);

/* ======================== INIT ======================== */
fillCampaigns();
applyBaseData();
fillTemplates();
loadRecipients();
</script>
</body>
</html>
