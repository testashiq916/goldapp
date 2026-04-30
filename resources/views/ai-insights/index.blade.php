<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",Tahoma,sans-serif;background:#eef3f8;color:#18324a;font-size:13px}
.wrap{max-width:1280px;margin:0 auto;padding:12px 14px 30px}
.hdr{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}
.hdr h1{font-size:20px;font-weight:800}
.hdr p{color:#5d7388;font-size:12px;margin-top:4px}
.exit-btn{height:32px;padding:0 14px;border:1px solid #c6d4e2;background:#fff;border-radius:7px;cursor:pointer;font-weight:700}
.tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.tab{border:1px solid #cbd9e6;background:#fff;padding:8px 12px;border-radius:999px;cursor:pointer;font-weight:700;color:#45627c}
.tab.active{background:#173b63;color:#fff;border-color:#173b63}
.panel{display:none}
.panel.active{display:block}
.strip{display:flex;gap:8px;align-items:end;flex-wrap:wrap;background:#fff;border:1px solid #d5e0ea;border-radius:10px;padding:12px;margin-bottom:12px}
.field{display:flex;flex-direction:column;gap:4px}
.field label{font-size:11px;font-weight:700;color:#37526d}
.field input,.field select,.field textarea{border:1px solid #bfd0df;border-radius:7px;padding:8px 10px;font-size:12px;background:#fff}
.field textarea{min-height:86px;resize:vertical}
.btn{height:34px;padding:0 16px;border:none;border-radius:7px;background:#1e5799;color:#fff;cursor:pointer;font-weight:700}
.btn:hover{background:#173b63}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-bottom:12px}
.card{background:#fff;border:1px solid #d5e0ea;border-radius:10px;padding:14px}
.card .big{font-size:24px;font-weight:800;color:#173b63}
.card .lbl{font-size:11px;text-transform:uppercase;color:#698299;margin-top:4px}
.insights{background:linear-gradient(135deg,#173b63,#1f5f9f);color:#fff;border-radius:10px;padding:14px;margin-bottom:12px}
.insights h3{font-size:11px;text-transform:uppercase;opacity:.85;margin-bottom:8px}
.insights .item{padding:5px 0;border-bottom:1px solid rgba(255,255,255,.15)}
.insights .item:last-child{border-bottom:none}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.box{background:#fff;border:1px solid #d5e0ea;border-radius:10px;overflow:hidden}
.box h3{font-size:12px;font-weight:800;padding:10px 12px;background:#edf4fb;border-bottom:1px solid #d5e0ea}
.box .body{padding:10px 12px}
.table-wrap{overflow:auto}
table{width:100%;border-collapse:collapse;font-size:12px}
th{background:#f6f9fc;color:#21415f;text-align:left;padding:8px;border-bottom:1px solid #d9e4ee;white-space:nowrap}
td{padding:8px;border-bottom:1px solid #edf2f7;vertical-align:top}
tr:last-child td{border-bottom:none}
.num{text-align:right}
.pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700}
.pill.high{background:#fee2e2;color:#991b1b}
.pill.medium{background:#fef3c7;color:#92400e}
.pill.low{background:#dbeafe;color:#1d4ed8}
.muted{color:#6b8196}
.empty{background:#fff;border:1px dashed #cbd9e6;border-radius:10px;padding:28px;text-align:center;color:#7b8fa3}
.link-list a{display:block;padding:8px 10px;border:1px solid #dde7f0;border-radius:8px;text-decoration:none;color:#18324a;margin-bottom:8px;background:#fff}
.link-list a strong{display:block}
.chat-layout{display:grid;grid-template-columns:260px 1fr;gap:12px;min-height:520px}
.chat-sidebar,.chat-main{background:#fff;border:1px solid #d5e0ea;border-radius:10px}
.chat-sidebar{padding:12px}
.mod-btn{display:block;width:100%;text-align:left;padding:8px 10px;border:1px solid #e1e8ef;border-radius:8px;background:#f9fbfd;margin-bottom:6px;cursor:pointer}
.mod-btn.active,.mod-btn:hover{background:#dbeafe;border-color:#90b8e8;color:#0f3c69;font-weight:700}
.chat-window{height:430px;overflow:auto;padding:12px;border-bottom:1px solid #d5e0ea;display:flex;flex-direction:column;gap:10px}
.msg{max-width:82%;padding:10px 12px;border-radius:12px;line-height:1.5}
.msg.bot{align-self:flex-start;background:#edf4fb;border:1px solid #cbdff1}
.msg.user{align-self:flex-end;background:#1e5799;color:#fff}
.chat-input{display:flex;gap:8px;padding:10px}
.chat-input input{flex:1;border:1px solid #bfd0df;border-radius:8px;padding:9px 10px}
.faq-chip{display:inline-block;background:#edf4fb;border:1px solid #c2d7ed;border-radius:999px;padding:4px 9px;font-size:11px;margin:4px 4px 0 0;cursor:pointer}
.faq-chip:hover{background:#dbeafe}
.spin{display:inline-block;width:14px;height:14px;border:2px solid #c9d9ea;border-top-color:#1e5799;border-radius:50%;animation:sp .7s linear infinite;vertical-align:middle}
@keyframes sp{to{transform:rotate(360deg)}}
@media (max-width: 900px){.grid2,.chat-layout{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <div>
      <h1>AI Workspace</h1>
      <p>Business insights, forecasting, sales guidance, order prediction, customer follow-up, stock planning, report assistant, and support assistant.</p>
    </div>
    <button class="exit-btn" id="exitBtn">Exit</button>
  </div>

  <div class="tabs">
    <button class="tab active" data-tab="business">Business Insights</button>
    <button class="tab" data-tab="forecast">Forecasting</button>
    <button class="tab" data-tab="sales">Sales Assistant</button>
    <button class="tab" data-tab="orders">Order Prediction</button>
    <button class="tab" data-tab="customers">Customer Follow-up</button>
    <button class="tab" data-tab="stock">Stock Recommendations</button>
    <button class="tab" data-tab="reports">Report Assistant</button>
    <button class="tab" data-tab="support">Support Assistant</button>
  </div>

  <div class="panel active" id="panel-business">
    <div class="strip">
      <div class="field"><label>Insight Period</label><select id="biz-days"><option value="30" selected>Last 30 Days</option><option value="60">Last 60 Days</option><option value="90">Last 90 Days</option></select></div>
      <button class="btn" onclick="loadBusinessInsights()">Show Insights</button>
    </div>
    <div id="business-insights"></div>
    <div id="business-body" class="empty">Load business insights to see dashboard-style AI explanations for sales, purchases, collections, margin movement, outstanding balances, and customer concentration.</div>
  </div>

  <div class="panel" id="panel-forecast">
    <div class="strip">
      <div class="field"><label>Forecast Basis</label><select id="forecast-days"><option value="90" selected>Last 90 Days</option><option value="120">Last 120 Days</option><option value="180">Last 180 Days</option></select></div>
      <button class="btn" onclick="loadForecasting()">Run Forecast</button>
    </div>
    <div id="forecast-insights"></div>
    <div id="forecast-body" class="empty">Load forecasting to predict next-period sales, collections, overdue pressure, and purchase needs.</div>
  </div>

  <div class="panel" id="panel-sales">
    <div class="strip">
      <div class="field"><label>Period</label><select id="sales-days"><option value="30" selected>Last 30 Days</option><option value="60">Last 60 Days</option><option value="90">Last 90 Days</option></select></div>
      <div class="field"><label>Customer Code Optional</label><input id="sales-customer" placeholder="Example C0001"></div>
      <button class="btn" onclick="loadSalesAssistant()">Show Guidance</button>
    </div>
    <div id="sales-insights"></div>
    <div id="sales-body" class="empty">Load sales guidance to see item suggestions, cross-sell ideas, and discount watch items.</div>
  </div>

  <div class="panel" id="panel-orders">
    <div class="strip">
      <div class="field"><label>Look Back</label><select id="order-days"><option value="90" selected>Last 90 Days</option><option value="120">Last 120 Days</option><option value="180">Last 180 Days</option></select></div>
      <button class="btn" onclick="loadOrderPredictions()">Predict Orders</button>
    </div>
    <div id="order-insights"></div>
    <div id="order-body" class="empty">Load order prediction to see delayed and at-risk orders.</div>
  </div>

  <div class="panel" id="panel-customers">
    <div class="strip">
      <div class="field"><label>Inactive Customer Threshold</label><select id="cust-inactive"><option value="60">60 Days</option><option value="90" selected>90 Days</option><option value="120">120 Days</option></select></div>
      <button class="btn" onclick="loadCustomerFollowup()">Show Follow-up</button>
    </div>
    <div id="customer-insights"></div>
    <div id="customer-body" class="empty">Load customer intelligence to find inactive, loyal, and campaign-ready customers.</div>
  </div>

  <div class="panel" id="panel-stock">
    <div class="strip">
      <div class="field"><label>Target Cover Days</label><select id="stock-target"><option value="30" selected>30 Days</option><option value="45">45 Days</option><option value="60">60 Days</option></select></div>
      <button class="btn" onclick="loadStockRecommendations()">Show Recommendations</button>
    </div>
    <div id="stock-insights"></div>
    <div id="stock-body" class="empty">Load stock planning to see reorder suggestions, dead stock, and fast movers.</div>
  </div>

  <div class="panel" id="panel-reports">
    <div class="strip">
      <div class="field" style="flex:1;min-width:280px"><label>Ask For A Report</label><textarea id="report-message" placeholder="Examples: sales last 30 days, pending orders, stock report, inactive customers"></textarea></div>
      <button class="btn" onclick="runReportAssistant()">Ask Assistant</button>
    </div>
    <div id="report-body" class="empty">Ask in plain English and the assistant will suggest the right report and summary.</div>
  </div>

  <div class="panel" id="panel-support">
    <div class="chat-layout">
      <div class="chat-sidebar">
        <button class="mod-btn active" onclick="selectModule(this,'sales-bill','Sales Bill')">Sales Bill</button>
        <button class="mod-btn" onclick="selectModule(this,'orders','Orders')">Orders</button>
        <button class="mod-btn" onclick="selectModule(this,'stock','Stock')">Stock</button>
        <button class="mod-btn" onclick="selectModule(this,'customers','Customers')">Customers</button>
        <button class="mod-btn" onclick="selectModule(this,'reports','Reports')">Reports</button>
        <button class="mod-btn" onclick="selectModule(this,'tools','Tools')">Tools</button>
        <button class="mod-btn" onclick="selectModule(this,'menu-access','Menu / Access')">Menu / Access</button>
        <button class="mod-btn" onclick="selectModule(this,'barcode','Barcode')">Barcode</button>
      </div>
      <div class="chat-main">
        <div class="chat-window" id="chatWindow"></div>
        <div class="chat-input">
          <input id="chatInput" placeholder="Describe the problem" onkeydown="if(event.key==='Enter')sendChat()">
          <button class="btn" onclick="sendChat()">Send</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const API = {
  business: @json(url('/api/ai/business-insights')),
  forecast: @json(url('/api/ai/sales-cashflow-forecast')),
  sales: @json(url('/api/ai/sales-assistant')),
  orders: @json(url('/api/ai/order-predictions')),
  customers: @json(url('/api/ai/customer-followup')),
  stock: @json(url('/api/ai/stock-recommendations')),
  reports: @json(url('/api/ai/report-assistant')),
  support: @json(url('/api/ai/chatbot'))
};

document.querySelectorAll('.tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(x => x.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('panel-' + tab.dataset.tab).classList.add('active');
  });
});

document.getElementById('exitBtn').addEventListener('click', () => {
  window.parent.postMessage({type: 'goldapp:close-module-frame'}, '*');
});

function getCsrf(){return document.querySelector('meta[name="csrf-token"]').getAttribute('content')}
function money(v){return 'Rs ' + Number(v || 0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}
function num(v){return Number(v || 0).toLocaleString('en-IN')}
function renderInsights(target, items){
  if(!items || !items.length){document.getElementById(target).innerHTML='';return}
  document.getElementById(target).innerHTML = `<div class="insights"><h3>AI Observations</h3>${items.map(x=>`<div class="item">${x}</div>`).join('')}</div>`;
}
function renderTable(title, cols, rows){
  if(!rows || !rows.length){return `<div class="box"><h3>${title}</h3><div class="body muted">No data found.</div></div>`}
  return `<div class="box"><h3>${title}</h3><div class="table-wrap"><table><tr>${cols.map(c=>`<th class="${c.num?'num':''}">${c.label}</th>`).join('')}</tr>${rows.map(r=>`<tr>${cols.map(c=>`<td class="${c.num?'num':''}">${c.render(r)}</td>`).join('')}</tr>`).join('')}</table></div></div>`;
}

async function loadBusinessInsights(){
  document.getElementById('business-body').innerHTML = '<div class="empty"><span class="spin"></span> Loading business insights...</div>';
  const p = new URLSearchParams({days: document.getElementById('biz-days').value});
  const d = await fetch(`${API.business}?${p}`, {headers:{Accept:'application/json'}}).then(r=>r.json());
  renderInsights('business-insights', d.narratives || []);
  const s = d.summary || {};
  document.getElementById('business-body').innerHTML = `<div class="cards">
    <div class="card"><div class="big">${money(s.sales || 0)}</div><div class="lbl">Sales</div></div>
    <div class="card"><div class="big">${money(s.purchases || 0)}</div><div class="lbl">Purchases</div></div>
    <div class="card"><div class="big">${money(s.collections || 0)}</div><div class="lbl">Collections</div></div>
    <div class="card"><div class="big">${money(s.outstanding || 0)}</div><div class="lbl">Outstanding</div></div>
    <div class="card"><div class="big">${money(s.gross_spread || 0)}</div><div class="lbl">Spread</div></div>
    <div class="card"><div class="big">${Number(s.top_customer_share || 0).toFixed(2)}%</div><div class="lbl">Top 3 Concentration</div></div>
  </div>${renderTable('Customer Concentration',[
    {label:'Customer',render:r=>`<strong>${r.custname || r.custcode}</strong><div class="muted">${r.custcode || ''}</div>`},
    {label:'Bills',num:true,render:r=>num(r.bills)},
    {label:'Sales',num:true,render:r=>money(r.total_spent)}
  ], d.top_customers)}`;
}

async function loadForecasting(){
  document.getElementById('forecast-body').innerHTML = '<div class="empty"><span class="spin"></span> Running forecast...</div>';
  const p = new URLSearchParams({days: document.getElementById('forecast-days').value});
  const d = await fetch(`${API.forecast}?${p}`, {headers:{Accept:'application/json'}}).then(r=>r.json());
  renderInsights('forecast-insights', d.narratives || []);
  const f = d.forecast || {};
  document.getElementById('forecast-body').innerHTML = `<div class="cards">
    <div class="card"><div class="big">${money(f.next_sales || 0)}</div><div class="lbl">Next Sales Forecast</div></div>
    <div class="card"><div class="big">${money(f.next_collections || 0)}</div><div class="lbl">Next Collections Forecast</div></div>
    <div class="card"><div class="big">${money(f.overdue_pressure || 0)}</div><div class="lbl">Overdue Pressure</div></div>
    <div class="card"><div class="big">${money(f.purchase_need || 0)}</div><div class="lbl">Purchase Need</div></div>
  </div>${renderTable('Forecast Drivers',[
    {label:'Month',render:r=>r.month},
    {label:'Sales',num:true,render:r=>money(r.sales)},
    {label:'Purchase',num:true,render:r=>money(r.purchase)},
    {label:'Collections',num:true,render:r=>money(r.collections)}
  ], d.drivers)}`;
}

async function loadSalesAssistant(){
  document.getElementById('sales-body').innerHTML = '<div class="empty"><span class="spin"></span> Loading sales guidance...</div>';
  const p = new URLSearchParams({days: document.getElementById('sales-days').value, customer_code: document.getElementById('sales-customer').value.trim()});
  const d = await fetch(`${API.sales}?${p}`, {headers:{Accept:'application/json'}}).then(r=>r.json());
  renderInsights('sales-insights', d.insights || []);
  document.getElementById('sales-body').innerHTML = `<div class="grid2">
    ${renderTable('Top Suggested Items',[{label:'Item',render:r=>`<strong>${r.name}</strong><div class="muted">${r.code}</div>`},{label:'Qty',num:true,render:r=>num(r.qty)},{label:'Amount',num:true,render:r=>money(r.amount)}], d.top_items)}
    ${renderTable('Cross Sell Pairs',[{label:'Primary',render:r=>r.primary},{label:'Paired With',render:r=>r.paired},{label:'Together',num:true,render:r=>num(r.together_count)}], d.cross_sell)}
    ${renderTable('Discount Watch',[{label:'Bill',render:r=>`<strong>${r.billno}</strong><div class="muted">${r.customer}</div>`},{label:'Discount %',num:true,render:r=>r.discount_pct.toFixed(2)+'%'},{label:'Discount',num:true,render:r=>money(r.discount)},{label:'Bill Amt',num:true,render:r=>money(r.billamt)}], d.discount_guardrails)}
    ${renderTable('Customer Purchase Focus',[{label:'Item',render:r=>`<strong>${r.name}</strong><div class="muted">${r.code}</div>`},{label:'Qty',num:true,render:r=>num(r.qty)},{label:'Bills',num:true,render:r=>num(r.bills)},{label:'Last Date',render:r=>r.last_date || '-'}], d.customer_focus)}
  </div>`;
}

async function loadOrderPredictions(){
  document.getElementById('order-body').innerHTML = '<div class="empty"><span class="spin"></span> Predicting order completion...</div>';
  const p = new URLSearchParams({days: document.getElementById('order-days').value});
  const d = await fetch(`${API.orders}?${p}`, {headers:{Accept:'application/json'}}).then(r=>r.json());
  renderInsights('order-insights', d.insights || []);
  const s = d.summary || {};
  document.getElementById('order-body').innerHTML = `<div class="cards">
    <div class="card"><div class="big">${num(s.avg_lead_days || 0)}</div><div class="lbl">Avg Lead Days</div></div>
    <div class="card"><div class="big">${num(s.delayed || 0)}</div><div class="lbl">Delayed Orders</div></div>
    <div class="card"><div class="big">${num(s.watchlist || 0)}</div><div class="lbl">Watchlist</div></div>
    <div class="card"><div class="big">${num(s.open_orders || 0)}</div><div class="lbl">Open Orders</div></div>
  </div>${renderTable('Order Prediction Watchlist',[
    {label:'Order',render:r=>`<strong>${r.ordno}</strong><div class="muted">${r.customer}</div>`},
    {label:'Order Date',render:r=>r.order_date},
    {label:'Due Date',render:r=>r.due_date},
    {label:'Due In',num:true,render:r=>r.due_in_days},
    {label:'Risk',render:r=>`<span class="pill ${r.risk}">${r.status_label}</span>`},
    {label:'Amount',num:true,render:r=>money(r.billamt)}
  ], d.orders)}`;
}

async function loadCustomerFollowup(){
  document.getElementById('customer-body').innerHTML = '<div class="empty"><span class="spin"></span> Building follow-up lists...</div>';
  const p = new URLSearchParams({inactive_days: document.getElementById('cust-inactive').value});
  const d = await fetch(`${API.customers}?${p}`, {headers:{Accept:'application/json'}}).then(r=>r.json());
  renderInsights('customer-insights', d.insights || []);
  document.getElementById('customer-body').innerHTML = `<div class="grid2">
    ${renderTable('Customer Segments',[{label:'Customer',render:r=>`<strong>${r.custname}</strong><div class="muted">${r.custcode}</div>`},{label:'Segment',render:r=>`<span class="pill ${r.segment==='high_value_inactive'?'high':(r.segment==='loyal'?'low':'medium')}">${r.segment.replaceAll('_',' ')}</span>`},{label:'Last Visit',render:r=>r.last_visit},{label:'Spent',num:true,render:r=>money(r.total_spent)},{label:'Action',render:r=>r.action}], d.segments)}
    ${renderTable('Upcoming Birthdays',[{label:'Customer',render:r=>`<strong>${r.name}</strong><div class="muted">${r.code}</div>`},{label:'Birthday',render:r=>r.dob},{label:'Days Left',num:true,render:r=>num(r.days_left)}], d.birthdays)}
  </div>`;
}

async function loadStockRecommendations(){
  document.getElementById('stock-body').innerHTML = '<div class="empty"><span class="spin"></span> Calculating stock recommendations...</div>';
  const p = new URLSearchParams({target_days: document.getElementById('stock-target').value});
  const d = await fetch(`${API.stock}?${p}`, {headers:{Accept:'application/json'}}).then(r=>r.json());
  renderInsights('stock-insights', d.insights || []);
  document.getElementById('stock-body').innerHTML = `<div class="grid2">
    ${renderTable('Reorder Suggestions',[{label:'Item',render:r=>`<strong>${r.name}</strong><div class="muted">${r.code}</div>`},{label:'Stock',num:true,render:r=>Number(r.stock).toFixed(3)},{label:'Daily Rate',num:true,render:r=>Number(r.daily_rate).toFixed(3)},{label:'Recommend Qty',num:true,render:r=>Number(r.recommended_qty).toFixed(3)}], d.reorder)}
    ${renderTable('Dead Stock',[{label:'Item',render:r=>`<strong>${r.name}</strong><div class="muted">${r.code}</div>`},{label:'Stock',num:true,render:r=>Number(r.stock).toFixed(3)}], d.dead_stock)}
    ${renderTable('Fast Movers',[{label:'Item',render:r=>`<strong>${r.name}</strong><div class="muted">${r.code}</div>`},{label:'Sold 30d',num:true,render:r=>Number(r.sold_30).toFixed(3)},{label:'Daily Rate',num:true,render:r=>Number(r.daily_rate).toFixed(3)}], d.fast_movers)}
  </div>`;
}

async function runReportAssistant(){
  const message = document.getElementById('report-message').value.trim();
  if(!message){return}
  document.getElementById('report-body').innerHTML = '<div class="empty"><span class="spin"></span> Interpreting your request...</div>';
  const body = new FormData();
  body.append('message', message);
  const d = await fetch(API.reports, {method:'POST', headers:{Accept:'application/json','X-CSRF-TOKEN':getCsrf()}, body}).then(r=>r.json());
  const summary = d.summary || {};
  const summaryHtml = Object.keys(summary).map(k=>{
    const isMoney = k.includes('sales') || k.includes('purchase') || k.includes('amount') || k.includes('spent');
    const value = typeof summary[k]==='number' ? (isMoney ? money(summary[k]) : num(summary[k])) : summary[k];
    return `<div class="card"><div class="big">${value}</div><div class="lbl">${k.replaceAll('_',' ')}</div></div>`;
  }).join('');
  document.getElementById('report-body').innerHTML = `<div class="insights"><h3>Interpreted Request</h3><div class="item"><strong>${d.title}</strong></div><div class="item">Intent: ${d.intent}</div><div class="item">Period: ${d.interpreted_period_days} days</div></div>${summaryHtml ? `<div class="cards">${summaryHtml}</div>` : ''}<div class="box"><h3>Suggested Reports</h3><div class="body link-list">${(d.suggestions||[]).map(s=>`<a href="${s.url}" target="_blank"><strong>${s.label}</strong><span class="muted">${s.reason}</span></a>`).join('')}</div></div>`;
}

let currentModule = 'sales-bill';
function addBotMsg(html, chips=[]){
  const box = document.getElementById('chatWindow');
  const div = document.createElement('div');
  div.className = 'msg bot';
  div.innerHTML = html + (chips.length ? `<div>${chips.map(c=>`<span class="faq-chip" onclick="askFaq('${String(c).replace(/'/g,"\\'")}')">${c}</span>`).join('')}</div>` : '');
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
  return div;
}
function addUserMsg(text){
  const box = document.getElementById('chatWindow');
  const div = document.createElement('div');
  div.className = 'msg user';
  div.textContent = text;
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
}
async function selectModule(btn, mod, label){
  document.querySelectorAll('.mod-btn').forEach(x=>x.classList.remove('active'));
  btn.classList.add('active');
  currentModule = mod;
  const body = new FormData();
  body.append('module', mod);
  body.append('message', '');
  const d = await fetch(API.support, {method:'POST', headers:{Accept:'application/json','X-CSRF-TOKEN':getCsrf()}, body}).then(r=>r.json());
  const tips = (d.tips||[]).length ? `<div class="muted" style="margin-top:6px">${d.tips.join(' | ')}</div>` : '';
  const ctx = (d.context||[]).length ? `<div class="muted" style="margin-top:6px">${d.context.map(c=>`${c.label}: ${c.value}`).join(' | ')}</div>` : '';
  addBotMsg(`<strong>${label}</strong> support ready.${tips}${ctx}`, (d.faqs||[]).map(f=>f.q));
}
function askFaq(text){
  document.getElementById('chatInput').value = text;
  sendChat();
}
async function sendChat(){
  const input = document.getElementById('chatInput');
  const message = input.value.trim();
  if(!message){return}
  input.value = '';
  addUserMsg(message);
  const wait = addBotMsg('<span class="spin"></span> Searching for help...');
  const body = new FormData();
  body.append('module', currentModule);
  body.append('message', message);
  const d = await fetch(API.support, {method:'POST', headers:{Accept:'application/json','X-CSRF-TOKEN':getCsrf()}, body}).then(r=>r.json());
  wait.remove();
  if(d.type === 'answers'){
    d.results.forEach(r=>{
      const steps = (r.steps||[]).length ? `<ol>${r.steps.map(x=>`<li>${x}</li>`).join('')}</ol>` : '';
      const actions = (r.actions||[]).length ? `<div class="link-list" style="margin-top:8px">${r.actions.map(a=>`<a href="${a.url}" target="_blank"><strong>${a.label}</strong></a>`).join('')}</div>` : '';
      const ctx = (r.context||[]).length ? `<div class="muted" style="margin-top:8px">${r.context.map(c=>`${c.label}: ${c.value}`).join(' | ')}</div>` : '';
      addBotMsg(`<strong>${r.module}</strong><br>${r.answer}${steps}${ctx}${actions}`);
    });
  }else{
    addBotMsg(d.message || 'No answer found.', d.suggest || []);
  }
}

selectModule(document.querySelector('.mod-btn.active'), 'sales-bill', 'Sales Bill');
</script>
</body>
</html>
