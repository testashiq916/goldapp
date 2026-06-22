@if(is_file(public_path('js/goldapp-theme.js')))
<script src="{{ asset('js/goldapp-theme.js') }}?v={{ @filemtime(public_path('js/goldapp-theme.js')) }}"></script>
@endif
<script>
(() => {
  const isoDatePattern = /\b(\d{4})-(\d{2})-(\d{2})\b/g;

  window.goldappFormatDate = function(value) {
    const text = String(value ?? '');
    return text.replace(isoDatePattern, (_, year, month, day) => `${day}-${month}-${year}`);
  };

  function shouldSkip(node) {
    const parent = node && node.parentElement;
    if (!parent) return true;
    return !!parent.closest('script,style,input,select,textarea,option');
  }

  function formatTextNode(node) {
    if (shouldSkip(node)) return;
    const oldValue = node.nodeValue || '';
    const newValue = window.goldappFormatDate(oldValue);
    if (newValue !== oldValue) node.nodeValue = newValue;
  }

  function formatDateText(root) {
    if (!root) return;
    if (root.nodeType === Node.TEXT_NODE) {
      formatTextNode(root);
      return;
    }
    if (root.nodeType !== Node.ELEMENT_NODE && root.nodeType !== Node.DOCUMENT_NODE) return;
    if (root.matches && root.matches('script,style,input,select,textarea,option')) return;

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    let node;
    while ((node = walker.nextNode())) {
      formatTextNode(node);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    formatDateText(document.body);
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach(formatDateText);
        if (mutation.type === 'characterData') {
          formatDateText(mutation.target);
        }
      });
    });
    observer.observe(document.body, { childList: true, subtree: true, characterData: true });
  });
})();
</script>
@php($goldappPrintLayout = $printLayout ?? \App\Support\PrintLayout::viewData())
<style>
  :root{
    --goldapp-print-top: {{ (int) ($goldappPrintLayout['topMargin'] ?? 230) }}px;
    --goldapp-print-left: {{ (int) ($goldappPrintLayout['leftMargin'] ?? 100) }}px;
    --goldapp-print-bottom: {{ (int) ($goldappPrintLayout['bottomMargin'] ?? 10) }}px;
    --goldapp-print-right: 10mm;
  }
  .goldapp-print-tools{display:flex;align-items:center;gap:8px 10px;flex-wrap:wrap;margin-left:auto}
  .goldapp-print-tools .goldapp-print-label{font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;opacity:.82}
  .goldapp-print-tools .goldapp-print-input,
  .goldapp-print-tools .goldapp-print-select{
    height:28px;
    min-width:68px;
    border:1px solid rgba(148,163,184,.45);
    border-radius:6px;
    padding:0 8px;
    font:inherit;
    font-size:11px;
    background:rgba(255,255,255,.92);
    color:#111827;
  }
  .goldapp-print-tools .goldapp-print-group{display:flex;align-items:center;gap:4px}
  .goldapp-print-tools .goldapp-print-reset{
    height:28px;
    border:1px solid rgba(148,163,184,.45);
    border-radius:6px;
    padding:0 10px;
    font:inherit;
    font-size:11px;
    font-weight:700;
    background:rgba(255,255,255,.92);
    color:#0f172a;
    cursor:pointer;
  }
  .goldapp-print-tools .goldapp-print-reset:hover{background:#fff}
  .goldapp-print-tools .goldapp-print-chip{
    display:flex;
    align-items:center;
    gap:6px;
    padding:4px 8px;
    border-radius:999px;
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.18);
    color:inherit;
  }
  body.goldapp-print-enabled [data-print-root]{
    transform:translate(var(--goldapp-print-left), var(--goldapp-print-top));
    transform-origin:top left;
    transition:transform .18s ease;
  }
  body.goldapp-print-enabled[data-goldapp-print-align="center"] [data-print-root]{margin-left:auto !important;margin-right:auto !important}
  body.goldapp-print-enabled[data-goldapp-print-align="right"] [data-print-root]{margin-left:auto !important;margin-right:0 !important}
  body.goldapp-print-enabled[data-goldapp-print-align="left"] [data-print-root]{margin-left:0 !important;margin-right:auto !important}
  @media print{
    @page{margin:0}
    html,body{margin:0 !important;padding:0 !important;background:#fff !important}
    body.goldapp-print-enabled{
      height:auto !important;
      overflow:visible !important;
      min-height:auto !important;
    }
    body.goldapp-print-enabled [data-print-root]{
      transform:none !important;
      box-sizing:border-box;
      width:210mm !important;
      max-width:210mm !important;
      min-height:auto !important;
      overflow:visible !important;
      padding-top:var(--goldapp-print-top) !important;
      padding-left:var(--goldapp-print-left) !important;
      padding-right:var(--goldapp-print-right) !important;
      padding-bottom:var(--goldapp-print-bottom) !important;
    }
    body.goldapp-print-enabled[data-goldapp-print-align="center"] [data-print-root]{
      margin-left:auto !important;
      margin-right:auto !important;
    }
    body.goldapp-print-enabled[data-goldapp-print-align="right"] [data-print-root]{
      margin-left:auto !important;
      margin-right:0 !important;
    }
    body.goldapp-print-enabled[data-goldapp-print-align="left"] [data-print-root]{
      margin-left:0 !important;
      margin-right:auto !important;
    }
    body.goldapp-print-enabled [data-print-root]{
      color:#0f172a !important;
      font-family:Segoe UI,Arial,sans-serif !important;
    }
    body.goldapp-print-enabled [data-print-root] .page{
      width:auto !important;
      max-width:100% !important;
      min-height:auto !important;
      margin:0 !important;
      border:0 !important;
      box-shadow:none !important;
      padding:0 !important;
    }
    body.goldapp-print-enabled [data-print-root] .header,
    body.goldapp-print-enabled [data-print-root] .company,
    body.goldapp-print-enabled [data-print-root] .title,
    body.goldapp-print-enabled [data-print-root] .top,
    body.goldapp-print-enabled [data-print-root] .meta-grid{
      break-inside:avoid !important;
      page-break-inside:avoid !important;
    }
    body.goldapp-print-enabled [data-print-root] .header{
      margin-bottom:7px !important;
      padding-bottom:7px !important;
    }
    body.goldapp-print-enabled [data-print-root] .grid{
      gap:7px !important;
      margin-bottom:7px !important;
    }
    body.goldapp-print-enabled [data-print-root] .card h3{
      padding:4px 7px !important;
      font-size:10px !important;
    }
    body.goldapp-print-enabled [data-print-root] .card .body{
      padding:5px 7px !important;
      font-size:10.5px !important;
      line-height:1.28 !important;
    }
    body.goldapp-print-enabled [data-print-root] .kv{
      gap:2px 6px !important;
    }
    body.goldapp-print-enabled [data-print-root] .section-title{
      margin:7px 0 4px !important;
      font-size:11.5px !important;
    }
    body.goldapp-print-enabled [data-print-root] table{
      width:100% !important;
      border-collapse:collapse !important;
      break-inside:auto !important;
      page-break-inside:auto !important;
    }
    body.goldapp-print-enabled [data-print-root] thead{
      display:table-header-group !important;
    }
    body.goldapp-print-enabled [data-print-root] tfoot{
      display:table-row-group !important;
    }
    body.goldapp-print-enabled [data-print-root] tr{
      break-inside:avoid !important;
      page-break-inside:avoid !important;
    }
    body.goldapp-print-enabled [data-print-root] th,
    body.goldapp-print-enabled [data-print-root] td{
      padding:2.5px 3.5px !important;
      font-size:9.6px !important;
      line-height:1.18 !important;
      vertical-align:top !important;
    }
    body.goldapp-print-enabled [data-print-root] th{
      font-weight:800 !important;
    }
    body.goldapp-print-enabled [data-print-root] .sold-items th,
    body.goldapp-print-enabled [data-print-root] .sold-items td,
    body.goldapp-print-enabled [data-print-root] .table th,
    body.goldapp-print-enabled [data-print-root] .table td{
      padding:2px 3px !important;
      font-size:9.2px !important;
    }
    body.goldapp-print-enabled [data-print-root] .totals{
      margin-top:5px !important;
      gap:7px !important;
      grid-template-columns:minmax(0,1fr) minmax(58mm,72mm) !important;
      align-items:start !important;
    }
    body.goldapp-print-enabled [data-print-root] .stack{
      gap:5px !important;
    }
    body.goldapp-print-enabled [data-print-root] .note-box,
    body.goldapp-print-enabled [data-print-root] .foot-box,
    body.goldapp-print-enabled [data-print-root] .bank-box{
      min-height:0 !important;
      padding:5px 7px !important;
      font-size:9.8px !important;
      line-height:1.25 !important;
    }
    body.goldapp-print-enabled [data-print-root] .note-box h4,
    body.goldapp-print-enabled [data-print-root] .foot-box h4,
    body.goldapp-print-enabled [data-print-root] .bank-box h4{
      margin-bottom:3px !important;
      font-size:10px !important;
    }
    body.goldapp-print-enabled [data-print-root] .summary{
      break-inside:avoid !important;
      page-break-inside:avoid !important;
      border-radius:4px !important;
    }
    body.goldapp-print-enabled [data-print-root] .summary table td{
      padding:2.5px 4px !important;
      font-size:9.8px !important;
    }
    body.goldapp-print-enabled [data-print-root] .summary tr.total td{
      font-size:11px !important;
      font-weight:800 !important;
    }
    body.goldapp-print-enabled [data-print-root] .footer,
    body.goldapp-print-enabled [data-print-root] .sign{
      break-inside:avoid !important;
      page-break-inside:avoid !important;
    }
    body.goldapp-print-enabled [data-print-root] .footer{
      margin-top:7px !important;
      font-size:9.5px !important;
    }
    body.goldapp-print-enabled [data-print-root] .sign{
      padding-top:18px !important;
    }
    body.goldapp-print-enabled [data-print-root] .sign-row{
      gap:10px !important;
    }
  }
</style>
<script>
(() => {
  const defaults = @json($goldappPrintLayout);
  const printContext = defaults.printContext || 'report';
  const storageKey = `goldapp-print-layout:${printContext}:global`;
  const legacyGlobalStorageKey = 'goldapp-print-layout:global';
  const legacyStorageKey = `goldapp-print-layout:${window.location.pathname}`;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const ALIGNS = ['LEFT', 'CENTER', 'RIGHT'];
  const MODES = ['PREPRINTED', 'APP_HEADER'];

  function loadStoredDefaults() {
    try {
      const raw = window.localStorage.getItem(storageKey);
      if (raw) {
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
      }
      if (printContext === 'invoice') {
        const legacyGlobalRaw = window.localStorage.getItem(legacyGlobalStorageKey);
        if (legacyGlobalRaw) {
          const parsed = JSON.parse(legacyGlobalRaw);
          return parsed && typeof parsed === 'object' ? parsed : {};
        }
      }
      const legacyRaw = window.localStorage.getItem(legacyStorageKey);
      if (!legacyRaw) return {};
      const parsed = JSON.parse(legacyRaw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
      return {};
    }
  }

  function saveStoredDefaults(state) {
    try {
      const payload = JSON.stringify({
        printContext: state.printContext,
        letterheadMode: state.letterheadMode,
        topMargin: state.printContext === 'invoice' ? state.printerTopMargin : state.reportTopMargin,
        leftMargin: state.printContext === 'invoice' ? state.printerLeftMargin : state.reportLeftMargin,
        bottomMargin: state.bottomMargin,
        pageAlign: state.printContext === 'invoice' ? state.printerPageAlign : state.reportPageAlign,
        printerTopMargin: state.printerTopMargin,
        printerLeftMargin: state.printerLeftMargin,
        printerPageAlign: state.printerPageAlign,
        reportTopMargin: state.reportTopMargin,
        reportLeftMargin: state.reportLeftMargin,
        reportPageAlign: state.reportPageAlign,
        appHeaderTopMargin: state.appHeaderTopMargin,
        appHeaderLeftMargin: state.appHeaderLeftMargin,
        appHeaderPageAlign: state.appHeaderPageAlign
      });
      window.localStorage.setItem(storageKey, payload);
      window.localStorage.setItem(legacyStorageKey, payload);
    } catch (_) {}
  }

  async function persistDefaultsToServer(state) {
    if (!csrfToken) return false;
    try {
      // Only persist the current context's margins so that invoice and report
      // settings never overwrite each other when "Set Default" is clicked.
      const softwareSettings = {
        PrintLetterheadMode: String(state.letterheadMode),
        BottomMargin: String(state.bottomMargin),
        AppHeaderTopMargin: String(state.appHeaderTopMargin),
        AppHeaderLeftMargin: String(state.appHeaderLeftMargin),
        AppHeaderPageAlign: String(state.appHeaderPageAlign),
      };
      if (state.printContext === 'invoice') {
        softwareSettings.TopMargin = String(state.printerTopMargin);
        softwareSettings.LeftMargin = String(state.printerLeftMargin);
        softwareSettings.PrintPageAlign = String(state.printerPageAlign);
        softwareSettings.PrinterTopMargin = String(state.printerTopMargin);
        softwareSettings.PrinterLeftMargin = String(state.printerLeftMargin);
        softwareSettings.PrinterPageAlign = String(state.printerPageAlign);
      } else {
        softwareSettings.ReportTopMargin = String(state.reportTopMargin);
        softwareSettings.ReportLeftMargin = String(state.reportLeftMargin);
        softwareSettings.ReportPageAlign = String(state.reportPageAlign);
      }
      const response = await fetch("{{ url('/api/application-settings/save') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ settings: { Software: softwareSettings } })
      });
      const data = await response.json();
      return !!data.ok;
    } catch (_) {
      return false;
    }
  }

  function normalizeMargin(value, fallback) {
    const num = Number(value);
    if (!Number.isFinite(num)) return fallback;
    return Math.max(0, Math.min(Math.round(num), 2000));
  }

  function normalizeAlign(value) {
    const normalized = String(value || '').trim().toUpperCase();
    return ALIGNS.includes(normalized) ? normalized : (defaults.pageAlign || 'LEFT');
  }

  function normalizeMode(value) {
    const normalized = String(value || '').trim().toUpperCase();
    return MODES.includes(normalized) ? normalized : (defaults.letterheadMode || 'PREPRINTED');
  }

  function activeLayoutValues(state) {
    if (state.letterheadMode === 'APP_HEADER') {
      return {
        topMargin: state.appHeaderTopMargin,
        leftMargin: state.appHeaderLeftMargin,
        pageAlign: state.appHeaderPageAlign,
      };
    }
    if (state.printContext === 'invoice') {
      return {
        topMargin: state.printerTopMargin,
        leftMargin: state.printerLeftMargin,
        pageAlign: state.printerPageAlign,
      };
    }
    return {
      topMargin: state.reportTopMargin,
      leftMargin: state.reportLeftMargin,
      pageAlign: state.reportPageAlign,
    };
  }

  function setActiveLayoutValues(state, values = {}) {
    if (state.letterheadMode === 'APP_HEADER') {
      state.appHeaderTopMargin = normalizeMargin(values.topMargin ?? state.appHeaderTopMargin, defaults.appHeaderTopMargin || 10);
      state.appHeaderLeftMargin = normalizeMargin(values.leftMargin ?? state.appHeaderLeftMargin, defaults.appHeaderLeftMargin || 4);
      state.appHeaderPageAlign = normalizeAlign(values.pageAlign ?? state.appHeaderPageAlign);
      return;
    }
    if (state.printContext === 'invoice') {
      state.printerTopMargin = normalizeMargin(values.topMargin ?? state.printerTopMargin, defaults.printerTopMargin || 230);
      state.printerLeftMargin = normalizeMargin(values.leftMargin ?? state.printerLeftMargin, defaults.printerLeftMargin || 100);
      state.printerPageAlign = normalizeAlign(values.pageAlign ?? state.printerPageAlign);
      return;
    }
    state.reportTopMargin = normalizeMargin(values.topMargin ?? state.reportTopMargin, defaults.reportTopMargin || 0);
    state.reportLeftMargin = normalizeMargin(values.leftMargin ?? state.reportLeftMargin, defaults.reportLeftMargin || 0);
    state.reportPageAlign = normalizeAlign(values.pageAlign ?? state.reportPageAlign);
  }

  function findPrintRoot() {
    const explicit = document.querySelector('[data-print-root]');
    if (explicit) return explicit;
    const selectors = [
      '.bill-wrap',
      '.page',
      '.wrap',
      '.window',
      '.report-card',
      '.content-wrap',
      '.content',
      '.grid-wrap',
      '.result-area',
      '.table-wrap',
      '.tbl-wrap'
    ];
    for (const selector of selectors) {
      const node = document.querySelector(selector);
      if (node) {
        node.setAttribute('data-print-root', 'auto');
        return node;
      }
    }
    return null;
  }

  function updateHeaderVisibility(mode) {
    document.querySelectorAll('[data-print-app-header]').forEach((node) => {
      if (!node.dataset.goldappOriginalDisplay) {
        const inlineDisplay = node.style.display || '';
        node.dataset.goldappOriginalDisplay = inlineDisplay;
      }
      if (mode === 'PREPRINTED') {
        node.style.display = 'none';
      } else {
        node.style.display = node.dataset.goldappOriginalDisplay || '';
      }
    });
  }

  function applyState(state) {
    const active = activeLayoutValues(state);
    document.documentElement.style.setProperty('--goldapp-print-top', `${active.topMargin}px`);
    document.documentElement.style.setProperty('--goldapp-print-left', `${active.leftMargin}px`);
    document.documentElement.style.setProperty('--goldapp-print-bottom', `${state.bottomMargin}px`);
    document.body.classList.add('goldapp-print-enabled');
    document.body.dataset.goldappPrintAlign = active.pageAlign;
    document.body.dataset.goldappLetterheadMode = state.letterheadMode;
    updateHeaderVisibility(state.letterheadMode);
    document.dispatchEvent(new CustomEvent('goldapp:print-layout-change', {
      detail: {
        letterheadMode: state.letterheadMode,
        topMargin: active.topMargin,
        leftMargin: active.leftMargin,
        bottomMargin: state.bottomMargin,
        pageAlign: active.pageAlign,
        printContext: state.printContext,
        printerTopMargin: state.printerTopMargin,
        printerLeftMargin: state.printerLeftMargin,
        printerPageAlign: state.printerPageAlign,
        reportTopMargin: state.reportTopMargin,
        reportLeftMargin: state.reportLeftMargin,
        reportPageAlign: state.reportPageAlign,
        preprintedTopMargin: active.topMargin,
        preprintedLeftMargin: active.leftMargin,
        preprintedPageAlign: active.pageAlign,
        appHeaderTopMargin: state.appHeaderTopMargin,
        appHeaderLeftMargin: state.appHeaderLeftMargin,
        appHeaderPageAlign: state.appHeaderPageAlign
      }
    }));
  }

  function createToolbarControls(state) {
    const toolbar = document.querySelector('.toolbar');
    if (!toolbar || toolbar.querySelector('[data-goldapp-print-tools]')) return;

    const host = document.createElement('div');
    host.className = 'goldapp-print-tools';
    host.dataset.goldappPrintTools = '1';
    host.innerHTML = `
      <div class="goldapp-print-chip">
        <span class="goldapp-print-label">Letterhead</span>
        <select class="goldapp-print-select" data-goldapp-field="letterheadMode">
          <option value="PREPRINTED">Preprinted</option>
          <option value="APP_HEADER">App Header</option>
        </select>
      </div>
      <div class="goldapp-print-group">
        <span class="goldapp-print-label">Top</span>
        <input class="goldapp-print-input" data-goldapp-field="topMargin" type="number" min="0" max="2000" step="1">
      </div>
      <div class="goldapp-print-group">
        <span class="goldapp-print-label">Left</span>
        <input class="goldapp-print-input" data-goldapp-field="leftMargin" type="number" min="0" max="2000" step="1">
      </div>
      <div class="goldapp-print-group">
        <span class="goldapp-print-label">Align</span>
        <select class="goldapp-print-select" data-goldapp-field="pageAlign">
          <option value="LEFT">Left</option>
          <option value="CENTER">Center</option>
          <option value="RIGHT">Right</option>
        </select>
      </div>
      <button type="button" class="goldapp-print-reset" data-goldapp-reset="1">Set Default</button>
    `;

    toolbar.appendChild(host);
    host.querySelector('[data-goldapp-field="letterheadMode"]').value = state.letterheadMode;

    const syncInputs = () => {
      const active = activeLayoutValues(state);
      host.querySelector('[data-goldapp-field="letterheadMode"]').value = state.letterheadMode;
      host.querySelector('[data-goldapp-field="topMargin"]').value = active.topMargin;
      host.querySelector('[data-goldapp-field="leftMargin"]').value = active.leftMargin;
      host.querySelector('[data-goldapp-field="pageAlign"]').value = active.pageAlign;
    };
    syncInputs();

    host.addEventListener('input', () => {
      const oldMode = state.letterheadMode;
      state.letterheadMode = normalizeMode(host.querySelector('[data-goldapp-field="letterheadMode"]').value);
      if (oldMode !== state.letterheadMode) {
        syncInputs();
      } else {
        setActiveLayoutValues(state, {
          topMargin: host.querySelector('[data-goldapp-field="topMargin"]').value,
          leftMargin: host.querySelector('[data-goldapp-field="leftMargin"]').value,
          pageAlign: host.querySelector('[data-goldapp-field="pageAlign"]').value,
        });
      }
      applyState(state);
    });
    host.addEventListener('change', () => {
      const oldMode = state.letterheadMode;
      state.letterheadMode = normalizeMode(host.querySelector('[data-goldapp-field="letterheadMode"]').value);
      if (oldMode !== state.letterheadMode) {
        syncInputs();
      } else {
        setActiveLayoutValues(state, {
          topMargin: host.querySelector('[data-goldapp-field="topMargin"]').value,
          leftMargin: host.querySelector('[data-goldapp-field="leftMargin"]').value,
          pageAlign: host.querySelector('[data-goldapp-field="pageAlign"]').value,
        });
      }
      applyState(state);
    });

    const resetBtn = host.querySelector('[data-goldapp-reset="1"]');
    if (resetBtn) {
      resetBtn.addEventListener('click', async () => {
        state.letterheadMode = normalizeMode(host.querySelector('[data-goldapp-field="letterheadMode"]').value);
        setActiveLayoutValues(state, {
          topMargin: host.querySelector('[data-goldapp-field="topMargin"]').value,
          leftMargin: host.querySelector('[data-goldapp-field="leftMargin"]').value,
          pageAlign: host.querySelector('[data-goldapp-field="pageAlign"]').value,
        });
        state.bottomMargin = normalizeMargin(defaults.bottomMargin, 10);
        saveStoredDefaults(state);
        await persistDefaultsToServer(state);
        syncInputs();
        applyState(state);
        const active = activeLayoutValues(state);
        document.dispatchEvent(new CustomEvent('goldapp:print-layout-save-default', {
          detail: {
            letterheadMode: state.letterheadMode,
            topMargin: active.topMargin,
            leftMargin: active.leftMargin,
            bottomMargin: state.bottomMargin,
            pageAlign: active.pageAlign,
            printContext: state.printContext,
            printerTopMargin: state.printerTopMargin,
            printerLeftMargin: state.printerLeftMargin,
            printerPageAlign: state.printerPageAlign,
            reportTopMargin: state.reportTopMargin,
            reportLeftMargin: state.reportLeftMargin,
            reportPageAlign: state.reportPageAlign,
            preprintedTopMargin: active.topMargin,
            preprintedLeftMargin: active.leftMargin,
            preprintedPageAlign: active.pageAlign,
            appHeaderTopMargin: state.appHeaderTopMargin,
            appHeaderLeftMargin: state.appHeaderLeftMargin,
            appHeaderPageAlign: state.appHeaderPageAlign
          }
        }));
      });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const root = findPrintRoot();
    if (!root) return;

    if (!root.hasAttribute('data-print-root')) {
      root.setAttribute('data-print-root', 'manual');
    }

    const storedDefaults = loadStoredDefaults();

    const state = {
      printContext,
      letterheadMode: normalizeMode(storedDefaults.letterheadMode ?? defaults.letterheadMode),
      bottomMargin: normalizeMargin(storedDefaults.bottomMargin ?? defaults.bottomMargin, 10),
      printerTopMargin: normalizeMargin(storedDefaults.printerTopMargin ?? (printContext === 'invoice' ? storedDefaults.topMargin : undefined) ?? defaults.printerTopMargin, 230),
      printerLeftMargin: normalizeMargin(storedDefaults.printerLeftMargin ?? (printContext === 'invoice' ? storedDefaults.leftMargin : undefined) ?? defaults.printerLeftMargin, 100),
      printerPageAlign: normalizeAlign(storedDefaults.printerPageAlign ?? (printContext === 'invoice' ? storedDefaults.pageAlign : undefined) ?? defaults.printerPageAlign ?? 'LEFT'),
      reportTopMargin: normalizeMargin(storedDefaults.reportTopMargin ?? (printContext === 'report' ? storedDefaults.topMargin : undefined) ?? defaults.reportTopMargin, 0),
      reportLeftMargin: normalizeMargin(storedDefaults.reportLeftMargin ?? (printContext === 'report' ? storedDefaults.leftMargin : undefined) ?? defaults.reportLeftMargin, 0),
      reportPageAlign: normalizeAlign(storedDefaults.reportPageAlign ?? (printContext === 'report' ? storedDefaults.pageAlign : undefined) ?? defaults.reportPageAlign ?? 'LEFT'),
      appHeaderTopMargin: normalizeMargin(storedDefaults.appHeaderTopMargin ?? defaults.appHeaderTopMargin, 10),
      appHeaderLeftMargin: normalizeMargin(storedDefaults.appHeaderLeftMargin ?? defaults.appHeaderLeftMargin, 4),
      appHeaderPageAlign: normalizeAlign(storedDefaults.appHeaderPageAlign ?? defaults.appHeaderPageAlign ?? 'CENTER'),
    };

    window.GoldAppPrintLayout = {
      getState: () => ({ ...state }),
      apply: (nextState = {}) => {
        state.letterheadMode = normalizeMode(nextState.letterheadMode ?? state.letterheadMode);
        if (
          Object.prototype.hasOwnProperty.call(nextState, 'topMargin') ||
          Object.prototype.hasOwnProperty.call(nextState, 'leftMargin') ||
          Object.prototype.hasOwnProperty.call(nextState, 'pageAlign')
        ) {
          setActiveLayoutValues(state, {
            topMargin: nextState.topMargin,
            leftMargin: nextState.leftMargin,
            pageAlign: nextState.pageAlign,
          });
        }
        state.bottomMargin = normalizeMargin(nextState.bottomMargin ?? state.bottomMargin, defaults.bottomMargin || 10);
        state.printerTopMargin = normalizeMargin(nextState.printerTopMargin ?? state.printerTopMargin, defaults.printerTopMargin || 230);
        state.printerLeftMargin = normalizeMargin(nextState.printerLeftMargin ?? state.printerLeftMargin, defaults.printerLeftMargin || 100);
        state.printerPageAlign = normalizeAlign(nextState.printerPageAlign ?? state.printerPageAlign);
        state.reportTopMargin = normalizeMargin(nextState.reportTopMargin ?? state.reportTopMargin, defaults.reportTopMargin || 0);
        state.reportLeftMargin = normalizeMargin(nextState.reportLeftMargin ?? state.reportLeftMargin, defaults.reportLeftMargin || 0);
        state.reportPageAlign = normalizeAlign(nextState.reportPageAlign ?? state.reportPageAlign);
        state.appHeaderTopMargin = normalizeMargin(nextState.appHeaderTopMargin ?? state.appHeaderTopMargin, defaults.appHeaderTopMargin || 10);
        state.appHeaderLeftMargin = normalizeMargin(nextState.appHeaderLeftMargin ?? state.appHeaderLeftMargin, defaults.appHeaderLeftMargin || 4);
        state.appHeaderPageAlign = normalizeAlign(nextState.appHeaderPageAlign ?? state.appHeaderPageAlign);
        applyState(state);
      }
    };

    applyState(state);
    createToolbarControls(state);
  });
})();
</script>
