<script src="{{ asset('js/goldapp-theme.js') }}"></script>
@php($goldappPrintLayout = $printLayout ?? \App\Support\PrintLayout::viewData())
<style>
  :root{
    --goldapp-print-top: {{ (int) ($goldappPrintLayout['topMargin'] ?? 230) }}px;
    --goldapp-print-left: {{ (int) ($goldappPrintLayout['leftMargin'] ?? 100) }}px;
    --goldapp-print-bottom: {{ (int) ($goldappPrintLayout['bottomMargin'] ?? 10) }}px;
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
      width:210mm;
      max-width:210mm;
      min-height:297mm;
      padding-top:var(--goldapp-print-top) !important;
      padding-left:var(--goldapp-print-left) !important;
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
  }
</style>
<script>
(() => {
  const defaults = @json($goldappPrintLayout);
  const storageKey = 'goldapp-print-layout:global';
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
        letterheadMode: state.letterheadMode,
        topMargin: state.topMargin,
        leftMargin: state.leftMargin,
        bottomMargin: state.bottomMargin,
        pageAlign: state.pageAlign
      });
      window.localStorage.setItem(storageKey, payload);
      window.localStorage.setItem(legacyStorageKey, payload);
    } catch (_) {}
  }

  async function persistDefaultsToServer(state) {
    if (!csrfToken) return false;
    try {
      const response = await fetch("{{ url('/api/application-settings/save') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          settings: {
            Software: {
              TopMargin: String(state.topMargin),
              LeftMargin: String(state.leftMargin),
              BottomMargin: String(state.bottomMargin),
              PrintLetterheadMode: String(state.letterheadMode),
              PrintPageAlign: String(state.pageAlign),
            }
          }
        })
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
    document.documentElement.style.setProperty('--goldapp-print-top', `${state.topMargin}px`);
    document.documentElement.style.setProperty('--goldapp-print-left', `${state.leftMargin}px`);
    document.documentElement.style.setProperty('--goldapp-print-bottom', `${state.bottomMargin}px`);
    document.body.classList.add('goldapp-print-enabled');
    document.body.dataset.goldappPrintAlign = state.pageAlign;
    document.body.dataset.goldappLetterheadMode = state.letterheadMode;
    updateHeaderVisibility(state.letterheadMode);
    document.dispatchEvent(new CustomEvent('goldapp:print-layout-change', {
      detail: {
        letterheadMode: state.letterheadMode,
        topMargin: state.topMargin,
        leftMargin: state.leftMargin,
        bottomMargin: state.bottomMargin,
        pageAlign: state.pageAlign
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
    host.querySelector('[data-goldapp-field="topMargin"]').value = state.topMargin;
    host.querySelector('[data-goldapp-field="leftMargin"]').value = state.leftMargin;
    host.querySelector('[data-goldapp-field="pageAlign"]').value = state.pageAlign;

    const syncInputs = () => {
      host.querySelector('[data-goldapp-field="letterheadMode"]').value = state.letterheadMode;
      host.querySelector('[data-goldapp-field="topMargin"]').value = state.topMargin;
      host.querySelector('[data-goldapp-field="leftMargin"]').value = state.leftMargin;
      host.querySelector('[data-goldapp-field="pageAlign"]').value = state.pageAlign;
    };

    host.addEventListener('input', () => {
      state.letterheadMode = normalizeMode(host.querySelector('[data-goldapp-field="letterheadMode"]').value);
      state.topMargin = normalizeMargin(host.querySelector('[data-goldapp-field="topMargin"]').value, defaults.topMargin || 230);
      state.leftMargin = normalizeMargin(host.querySelector('[data-goldapp-field="leftMargin"]').value, defaults.leftMargin || 100);
      state.pageAlign = normalizeAlign(host.querySelector('[data-goldapp-field="pageAlign"]').value);
      applyState(state);
    });
    host.addEventListener('change', () => {
      state.letterheadMode = normalizeMode(host.querySelector('[data-goldapp-field="letterheadMode"]').value);
      state.topMargin = normalizeMargin(host.querySelector('[data-goldapp-field="topMargin"]').value, defaults.topMargin || 230);
      state.leftMargin = normalizeMargin(host.querySelector('[data-goldapp-field="leftMargin"]').value, defaults.leftMargin || 100);
      state.pageAlign = normalizeAlign(host.querySelector('[data-goldapp-field="pageAlign"]').value);
      applyState(state);
    });

    const resetBtn = host.querySelector('[data-goldapp-reset="1"]');
    if (resetBtn) {
      resetBtn.addEventListener('click', async () => {
        state.letterheadMode = normalizeMode(host.querySelector('[data-goldapp-field="letterheadMode"]').value);
        state.topMargin = normalizeMargin(host.querySelector('[data-goldapp-field="topMargin"]').value, defaults.topMargin || 230);
        state.leftMargin = normalizeMargin(host.querySelector('[data-goldapp-field="leftMargin"]').value, defaults.leftMargin || 100);
        state.bottomMargin = normalizeMargin(defaults.bottomMargin, 10);
        state.pageAlign = normalizeAlign(host.querySelector('[data-goldapp-field="pageAlign"]').value);
        saveStoredDefaults(state);
        await persistDefaultsToServer(state);
        syncInputs();
        applyState(state);
        document.dispatchEvent(new CustomEvent('goldapp:print-layout-save-default', {
          detail: {
            letterheadMode: state.letterheadMode,
            topMargin: state.topMargin,
            leftMargin: state.leftMargin,
            bottomMargin: state.bottomMargin,
            pageAlign: state.pageAlign
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
      letterheadMode: normalizeMode(storedDefaults.letterheadMode ?? defaults.letterheadMode),
      topMargin: normalizeMargin(storedDefaults.topMargin ?? defaults.topMargin, 230),
      leftMargin: normalizeMargin(storedDefaults.leftMargin ?? defaults.leftMargin, 100),
      bottomMargin: normalizeMargin(storedDefaults.bottomMargin ?? defaults.bottomMargin, 10),
      pageAlign: normalizeAlign(storedDefaults.pageAlign ?? defaults.pageAlign),
    };

    window.GoldAppPrintLayout = {
      getState: () => ({ ...state }),
      apply: (nextState = {}) => {
        state.letterheadMode = normalizeMode(nextState.letterheadMode ?? state.letterheadMode);
        state.topMargin = normalizeMargin(nextState.topMargin ?? state.topMargin, defaults.topMargin || 230);
        state.leftMargin = normalizeMargin(nextState.leftMargin ?? state.leftMargin, defaults.leftMargin || 100);
        state.bottomMargin = normalizeMargin(nextState.bottomMargin ?? state.bottomMargin, defaults.bottomMargin || 10);
        state.pageAlign = normalizeAlign(nextState.pageAlign ?? state.pageAlign);
        applyState(state);
      }
    };

    applyState(state);
    createToolbarControls(state);
  });
})();
</script>
