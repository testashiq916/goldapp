<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Year End Account Close</title>
    <style>
        :root{--line:#d9e2ec;--text:#17324d;--muted:#64748b;--warn:#8a3f1b;--warn-bg:#fff3e8;--warn-line:#f0c7aa;--shadow:0 24px 60px rgba(15,23,42,.10)}
        *{box-sizing:border-box}body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:linear-gradient(180deg,#f7fafc 0%,#e8eef5 100%);color:var(--text)}button,input{font:inherit}
        .page{padding:18px}.wrap{max-width:1760px;margin:0 auto}.top{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:16px}.top h1{margin:0;font-size:34px}.top p{margin:8px 0 0;color:var(--muted);max-width:900px}
        .actions{display:flex;gap:10px;flex-wrap:wrap}.btn{border:1px solid #d4bb71;background:linear-gradient(180deg,#fff8df 0%,#efd38e 100%);color:#5b4308;padding:10px 15px;border-radius:13px;font-weight:700;cursor:pointer}.btn.alt{background:#fff;border-color:#cbd5e1;color:#334155}.btn.danger{background:linear-gradient(180deg,#fff1ee 0%,#f8c7bf 100%);border-color:#e6a396;color:#7a2114}
        .notice{background:var(--warn-bg);border:1px solid var(--warn-line);border-radius:18px;padding:14px 16px;color:var(--warn);margin-bottom:16px}.layout{display:grid;grid-template-columns:340px minmax(0,1fr);gap:18px}.panel{background:rgba(255,255,255,.95);border:1px solid rgba(148,163,184,.28);border-radius:24px;box-shadow:var(--shadow)}.side,.main{padding:18px}
        .field{display:grid;gap:7px;margin-bottom:12px}.field label{font-size:12px;font-weight:700;color:#334155}.field input{width:100%;border:1px solid var(--line);border-radius:14px;padding:11px 12px;background:#fff}
        .section+.section{margin-top:18px}.section h2{margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:.12em;color:#475569}.quick-list{display:grid;gap:8px}.quick{padding:11px 12px;border-radius:14px;border:1px solid #dde6f2;background:#f8fbff;cursor:pointer;text-align:left}.quick strong{display:block;color:#1f3a5b}.quick span{display:block;font-size:12px;color:var(--muted);margin-top:2px}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.card{border:1px solid var(--line);border-radius:20px;padding:16px;background:#fff}.card h3{margin:0 0 12px;font-size:18px}.list{display:grid;gap:10px}.check{display:flex;gap:10px;align-items:flex-start;border:1px solid #edf2f7;border-radius:14px;padding:10px 12px;background:#fbfdff}.check input{margin-top:2px}.check span{font-weight:600}.check small{display:block;color:var(--muted);margin-top:2px}
        .status{margin-top:16px;padding:14px 16px;border-radius:16px;border:1px solid #dde6f2;background:#f8fbff;white-space:pre-wrap}@media (max-width:1200px){.layout{grid-template-columns:1fr}.grid{grid-template-columns:1fr}}@media (max-width:760px){.page{padding:10px}.top{flex-direction:column}}
    </style>
</head>
<body>
@php $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); @endphp
<div class="page">
    <div class="wrap">
        <div class="top">
            <div>
                <h1>Year End Account Close</h1>
                <p>Close old transactions up to a selected date, optionally keep estimate/control-2 bills, reset opening balances, clear addresses, and re-initialise document counters.</p>
            </div>
            <div class="actions">
                <button class="btn alt" type="button" id="selectAllBtn">Select All Main Deletes</button>
                <button class="btn alt" type="button" id="initDocBtn">Init. Doc. Nos.</button>
                <button class="btn danger" type="button" id="closeBtn">Close A/c</button>
            </div>
        </div>
        <div class="notice">Core PB close-account options and stock roll-forward helpers are ported here. Use <strong>Initialise Op. Stock Details</strong> only when you want a full opening-stock reset instead of carry-forward from transactions.</div>
        <div class="layout">
            <aside class="panel side">
                <div class="field">
                    <label for="closeDate">Closing Date</label>
                    <input id="closeDate" type="date">
                </div>
                <div class="section">
                    <h2>Quick Picks</h2>
                    <div class="quick-list">
                        <button class="quick" type="button" data-preset="sales-purchase"><strong>Sales + Purchase Cleanup</strong><span>Select sales, purchase, returns, daybook, and income/expense reset.</span></button>
                        <button class="quick" type="button" data-preset="full-delete"><strong>Full Transaction Cleanup</strong><span>Select all major delete options from the old close window.</span></button>
                        <button class="quick" type="button" data-preset="reset-only"><strong>Reset Only</strong><span>Keep transaction deletes off and select reset-style options only.</span></button>
                    </div>
                </div>
            </aside>
            <main class="panel main">
                <div class="grid" id="sections"></div>
                <div class="status" id="statusBox">Waiting for action.</div>
            </main>
        </div>
    </div>
</div>
<script>
const payload = {!! $payloadJson !!};
const state = { flags: {} };
const sectionsEl = document.getElementById('sections');
const closeDateEl = document.getElementById('closeDate');
const statusBox = document.getElementById('statusBox');
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;')}
function renderSections(){sectionsEl.innerHTML = payload.sections.map(section=>`<section class="card"><h3>${escapeHtml(section.title)}</h3><div class="list">${section.items.map(item=>`<label class="check"><input type="checkbox" data-flag="${escapeHtml(item.key)}"><div><span>${escapeHtml(item.label)}</span><small>${escapeHtml(item.key)}</small></div></label>`).join('')}</div></section>`).join(''); sectionsEl.querySelectorAll('[data-flag]').forEach(input=>{const key=input.dataset.flag; state.flags[key]=key==='keepbills'?!!payload.keep_bills_default:false; input.checked=!!state.flags[key]; input.addEventListener('change',()=>state.flags[key]=input.checked);});}
function setFlags(keys,value){keys.forEach(key=>{state.flags[key]=value; const input=sectionsEl.querySelector(`[data-flag="${key}"]`); if(input) input.checked=value;});}
function applyPreset(name){const deleteFlags=['chsales','crsales','sret','chpurchase','crpurchase','purchaseret','otheritemtran','order','orderpend','reppend','repr','smith','refn','refnpend','adjustment','kuricolln','partnersdeposit']; const resetFlags=['deldaybookentries','initopbalie','initopbalal','initopstock','initpartyopwgt','initsmithjewlopbal','removeaddr','deloutstockbarcode']; setFlags(deleteFlags.concat(resetFlags),false); if(name==='sales-purchase'){setFlags(['chsales','crsales','sret','chpurchase','crpurchase','purchaseret','deldaybookentries','initopbalie'],true);} else if(name==='full-delete'){setFlags(deleteFlags.concat(['deldaybookentries','initopbalie']),true);} else if(name==='reset-only'){setFlags(resetFlags,true);}}
function buildPayload(){return {close_date:closeDateEl.value,...state.flags};}
function enabledDangerFlags(){const labels={deldaybookentries:'Delete Daybook Entries',initopbalie:'Initialise Op. Balance of Income/Expense A/cs',initopbalal:'Initialise Op. Balance of Assets/Liabilities',initopstock:'Initialise Op. Stock Details',initpartyopwgt:'Initialise Party Op. Wgt Details',initsmithjewlopbal:'Initialise Smith/Jewl Op. Bal Details',removeaddr:'Remove Addr+Phone from Clients',deloutstockbarcode:'Delete Outstock Barcodes'}; return Object.entries(labels).filter(([key])=>!!state.flags[key]).map(([,label])=>label);}
async function postJson(url,body){const res=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},credentials:'same-origin',body:JSON.stringify(body)}); const text=await res.text(); try{return JSON.parse(text);}catch(e){return {ok:false,message:'Server error (no JSON). Status '+res.status+'. Check PHP error logs.'};} }
function showStatus(message,summary){let text=message||'Done.'; if(summary&&typeof summary==='object'){const rows=Object.entries(summary).filter(([,value])=>Number(value)!==0).map(([key,value])=>`${key}: ${value}`); if(rows.length) text += `\n\nSummary\n${rows.join('\n')}`;} statusBox.textContent=text;}
document.getElementById('initDocBtn').addEventListener('click', async()=>{statusBox.textContent='Initialising document counters...'; const json=await postJson('{{ url('/api/year-end-account-close/init-doc-nos') }}',{}); showStatus(json.message||'Done.');});
document.getElementById('closeBtn').addEventListener('click', async()=>{if(!closeDateEl.value){alert('Please choose a closing date.'); return;} const dangerFlags=enabledDangerFlags(); let confirmMessage='You are going to close old entries up to the selected date. Please make sure reports and backups are already taken.'; if(dangerFlags.length){confirmMessage += `\n\nSelected reset/delete options:\n- ${dangerFlags.join('\n- ')}`;} confirmMessage += '\n\nContinue?'; if(!confirm(confirmMessage)) return; statusBox.textContent='Running year-end close... (please wait, this may take a minute)'; try{const json=await postJson('{{ url('/api/year-end-account-close/close') }}',buildPayload()); showStatus((json.message||'Done.') + (json.note ? `\n\nNote\n${json.note}` : ''), json.summary||{});}catch(err){statusBox.textContent='Error: '+(err.message||err);}});
document.getElementById('selectAllBtn').addEventListener('click',()=>{setFlags(['chsales','crsales','sret','chpurchase','crpurchase','purchaseret','otheritemtran','order','refn','repr','smith','adjustment','deldaybookentries','initopbalie'],true);});
document.querySelectorAll('[data-preset]').forEach(btn=>btn.addEventListener('click',()=>applyPreset(btn.dataset.preset)));
closeDateEl.value = payload.today; renderSections();
</script>
</body>
</html>
