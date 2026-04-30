// Disable browser autocomplete on all entry form inputs
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('input, select').forEach(function (el) {
    el.setAttribute('autocomplete', 'off');
  });
});

(function () {
  function pad2(v) {
    return String(v).padStart(2, '0');
  }

  function toDisplayDate(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
      const parts = raw.split('-');
      return pad2(parts[2]) + '/' + pad2(parts[1]) + '/' + parts[0];
    }
    const m = raw.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
    if (!m) return raw;
    const dd = pad2(m[1]);
    const mm = pad2(m[2]);
    let yy = String(m[3]);
    if (yy.length === 2) yy = '20' + yy;
    return dd + '/' + mm + '/' + yy;
  }

  function toIsoDate(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
    const m = raw.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
    if (!m) return '';
    let yy = String(m[3]);
    if (yy.length === 2) yy = '20' + yy;
    return yy + '-' + pad2(m[2]) + '-' + pad2(m[1]);
  }

  function attachCalendarInput(target) {
    const input = typeof target === 'string' ? document.getElementById(target) : target;
    if (!input || input.dataset.goldappCalendarAttached === '1') return null;

    input.dataset.goldappCalendarAttached = '1';
    input.autocomplete = 'off';

    const picker = document.createElement('input');
    picker.type = 'date';
    picker.tabIndex = -1;
    picker.setAttribute('aria-hidden', 'true');
    picker.style.position = 'fixed';
    picker.style.opacity = '0.01';
    picker.style.pointerEvents = 'auto';
    picker.style.zIndex = '2147483647';
    picker.style.width = '20px';
    picker.style.height = '20px';
    picker.style.padding = '0';
    picker.style.border = '0';
    picker.style.margin = '0';
    picker.style.background = 'transparent';
    picker.style.appearance = 'none';
    picker.style.webkitAppearance = 'none';
    picker.style.left = '0';
    picker.style.top = '0';

    (document.body || document.documentElement).appendChild(picker);

    function positionPicker() {
      const rect = input.getBoundingClientRect();
      picker.style.left = Math.max(0, rect.right - 24) + 'px';
      picker.style.top = Math.max(0, rect.top + ((rect.height - 20) / 2)) + 'px';
    }

    function syncFromPicker(triggerChange) {
      const nextValue = toDisplayDate(picker.value);
      if (nextValue === input.value) return;
      input.value = nextValue;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      if (triggerChange) {
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }

    function openPicker() {
      if (input.disabled || input.readOnly) return;
      positionPicker();
      picker.value = toIsoDate(input.value);
      if (typeof picker.showPicker === 'function') {
        try {
          picker.focus({ preventScroll: true });
          picker.showPicker();
          return;
        } catch (_) {}
      }
      picker.focus({ preventScroll: true });
      picker.click();
    }

    input.addEventListener('focus', function () {
      setTimeout(openPicker, 0);
    });

    input.addEventListener('click', function () {
      openPicker();
    });

    input.addEventListener('blur', function () {
      input.value = toDisplayDate(input.value);
    });

    window.addEventListener('resize', positionPicker);
    window.addEventListener('scroll', positionPicker, true);

    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'F4') {
        e.preventDefault();
        openPicker();
      }
    });

    picker.addEventListener('change', function () {
      syncFromPicker(true);
    });

    return picker;
  }

  function attachCalendarInputs(list) {
    (Array.isArray(list) ? list : []).forEach(attachCalendarInput);
  }

  window.goldappDateUi = {
    toDisplayDate: toDisplayDate,
    toIsoDate: toIsoDate,
    attachCalendarInput: attachCalendarInput,
    attachCalendarInputs: attachCalendarInputs
  };
})();
