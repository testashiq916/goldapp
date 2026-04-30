/**
 * GoldApp Report Export Utility
 * Provides CSV, Excel (.xls), and PDF export for report grids.
 *
 * Usage:
 *   1. Include: <script src="{{ asset('js/report-export.js') }}"></script>
 *   2. Call:    ReportExport.init('btnSaveAs', getHeaders, getRows, 'my-report');
 *
 *   getHeaders() => array of [label, key, isNum?, decimals?]
 *   getRows()    => array of row objects
 *   filename     => base filename (no extension)
 */
const ReportExport = (() => {
  let overlay = null;

  function init(btnId, headersFn, rowsFn, filenameFn) {
    bindButton(btnId, function (e) {
      e.preventDefault();
      e.stopPropagation();
      open(headersFn, rowsFn, filenameFn);
    });
  }

  function bindButton(btnId, handler, attempt) {
    var btn = typeof btnId === 'string' ? document.getElementById(btnId) : btnId;

    if (!btn) {
      if ((attempt || 0) < 20) {
        setTimeout(function () {
          bindButton(btnId, handler, (attempt || 0) + 1);
        }, 150);
      } else {
        console.warn('ReportExport: btn not found:', btnId);
      }
      return;
    }

    if (btn.tagName === 'BUTTON' && !btn.getAttribute('type')) {
      btn.setAttribute('type', 'button');
    }

    if (btn._reportExportHandler) {
      btn.removeEventListener('click', btn._reportExportHandler);
    }

    btn._reportExportHandler = handler;
    btn.onclick = handler;
    btn.addEventListener('click', handler);
  }

  function open(headersFn, rowsFn, filenameFn) {
    try {
      showDialog(headersFn, rowsFn, filenameFn);
    } catch (err) {
      console.error('ReportExport error:', err);
      alert('Export error: ' + err.message);
    }
  }

  function showDialog(headersFn, rowsFn, filenameFn) {
    closeDialog();

    var fname = typeof filenameFn === 'function' ? filenameFn() : (filenameFn || 'export');
    var dlgOverlay = document.createElement('div');
    dlgOverlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:99999;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center';

    var box = document.createElement('div');
    box.style.cssText = 'background:#fff;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.25);padding:20px 24px;min-width:340px;max-width:400px;font-family:Segoe UI,system-ui,sans-serif';

    box.innerHTML =
      '<div style="display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:#1e293b;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e2e8f0">' +
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>' +
        ' Save As</div>' +
      '<div style="margin-bottom:14px">' +
        '<div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">File Name</div>' +
        '<input type="text" id="_xFname" value="' + escAttr(fname) + '" spellcheck="false" ' +
          'style="width:100%;height:36px;border:1.5px solid #cbd5e1;border-radius:8px;padding:0 10px;font-size:13px;color:#1e293b;outline:none;box-sizing:border-box">' +
      '</div>' +
      '<div style="margin-bottom:14px">' +
        '<div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">File Type</div>' +
        '<div style="display:flex;gap:8px" id="_xTypes">' +
          typeCard('csv', '#3b82f6', 'CSV (.csv)', '<line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>', true) +
          typeCard('xls', '#16a34a', 'Excel (.xls)', '<path d="M8 13l3 3 3-3"/><path d="M11 10v6"/>', false) +
          typeCard('pdf', '#dc2626', 'PDF (Print)', '<path d="M9 15v-2h2a1 1 0 1 0 0-2H9"/>', false) +
        '</div>' +
      '</div>' +
      '<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;padding-top:14px;border-top:1px solid #e2e8f0">' +
        '<button type="button" id="_xCancel" style="padding:7px 20px;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;background:#f1f5f9;color:#475569">Cancel</button>' +
        '<button type="button" id="_xSave" style="padding:7px 20px;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;background:#3b82f6;color:#fff">Save</button>' +
      '</div>';

    dlgOverlay.appendChild(box);
    document.body.appendChild(dlgOverlay);
    overlay = dlgOverlay;

    var inp = document.getElementById('_xFname');
    if (inp) {
      setTimeout(function () {
        inp.focus();
        inp.select();
      }, 60);
    }

    var selectedType = 'csv';
    var cards = box.querySelectorAll('[data-xtype]');
    cards.forEach(function (card) {
      card.onclick = function () {
        cards.forEach(function (c) {
          c.style.borderColor = '#e2e8f0';
          c.style.background = '#fff';
          c.style.color = '#475569';
        });
        card.style.borderColor = '#3b82f6';
        card.style.background = '#eff6ff';
        card.style.color = '#1e40af';
        selectedType = card.getAttribute('data-xtype');
      };
    });

    document.getElementById('_xCancel').onclick = function () { closeDialog(); };
    dlgOverlay.onclick = function (e) {
      if (e.target === dlgOverlay) closeDialog();
    };

    document.getElementById('_xSave').onclick = function () {
      var customName = (inp ? inp.value.trim() : '') || fname;
      var hs = headersFn();
      var rows = rowsFn();

      closeDialog();

      if (selectedType === 'csv') exportCSV(hs, rows, customName);
      else if (selectedType === 'xls') exportXLS(hs, rows, customName);
      else if (selectedType === 'pdf') exportPDF(hs, rows, customName);
    };

    if (inp) {
      inp.onkeydown = function (e) {
        if (e.key === 'Enter') document.getElementById('_xSave').click();
        if (e.key === 'Escape') closeDialog();
      };
    }
  }

  function typeCard(type, color, label, iconExtra, selected) {
    var border = selected ? '#3b82f6' : '#e2e8f0';
    var bg = selected ? '#eff6ff' : '#fff';
    var txtColor = selected ? '#1e40af' : '#475569';

    return '<div data-xtype="' + type + '" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;' +
      'padding:10px 6px;border:1.5px solid ' + border + ';border-radius:8px;cursor:pointer;font-size:11px;font-weight:600;' +
      'color:' + txtColor + ';background:' + bg + '">' +
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' + color + '" stroke-width="2">' +
        '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>' +
        '<polyline points="14 2 14 8 20 8"/>' + iconExtra + '</svg>' +
      '<span>' + label + '</span></div>';
  }

  function escAttr(v) {
    return String(v || '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function closeDialog() {
    if (overlay) {
      overlay.remove();
      overlay = null;
    }
  }

  function exportCSV(hs, rows, fname) {
    var lines = [];
    lines.push(hs.map(function (h) {
      return '"' + String(h[0]).replace(/"/g, '""') + '"';
    }).join(','));

    rows.forEach(function (row) {
      lines.push(hs.map(function (h) {
        var v = row[h[1]];
        if (v == null) v = '';
        return '"' + String(v).replace(/"/g, '""') + '"';
      }).join(','));
    });

    downloadBlob(lines.join('\r\n'), fname + '.csv', 'text/csv;charset=utf-8;');
  }

  function exportXLS(hs, rows, fname) {
    var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office"' +
      ' xmlns:x="urn:schemas-microsoft-com:office:excel"' +
      ' xmlns="http://www.w3.org/TR/REC-html40">' +
      '<head><meta charset="utf-8">' +
      '<style>td,th{font-family:Arial;font-size:11px;border:1px solid #ccc;padding:4px 6px}' +
      'th{background:#d9e2f3;font-weight:bold}' +
      '.num{text-align:right;mso-number-format:"\\#\\,\\#\\#0\\.00"}' +
      '.num3{text-align:right;mso-number-format:"\\#\\,\\#\\#0\\.000"}' +
      '</style></head><body><table>';

    html += '<tr>';
    hs.forEach(function (h) {
      html += '<th>' + esc(h[0]) + '</th>';
    });
    html += '</tr>';

    rows.forEach(function (row) {
      html += '<tr>';
      hs.forEach(function (h) {
        var v = row[h[1]];
        if (v == null) v = '';
        var cls = h[2] ? (h[3] === 3 ? 'num3' : 'num') : '';
        html += '<td class="' + cls + '">' + esc(v) + '</td>';
      });
      html += '</tr>';
    });

    html += '</table></body></html>';
    downloadBlob(html, fname + '.xls', 'application/vnd.ms-excel;charset=utf-8;');
  }

  function exportPDF(hs, rows, fname) {
    var w = window.open('', '_blank', 'width=1100,height=700');
    if (!w) {
      alert('Please allow popups for PDF export');
      return;
    }

    var html = '<!DOCTYPE html><html><head><meta charset="utf-8">' +
      '<title>' + esc(fname) + '</title>' +
      '<style>*{margin:0;padding:0;box-sizing:border-box}' +
      'body{font-family:Arial,sans-serif;font-size:10px;padding:12px}' +
      'h2{font-size:14px;margin-bottom:8px;color:#1e3a5f}' +
      'table{width:100%;border-collapse:collapse;margin-top:4px}' +
      'th,td{border:1px solid #bbb;padding:3px 5px;font-size:9px}' +
      'th{background:#e8edf5;font-weight:bold;text-align:left}' +
      'td.num{text-align:right}' +
      'tr:nth-child(even) td{background:#f8f9fb}' +
      '@media print{body{padding:0}}' +
      '</style></head><body>' +
      '<h2>' + esc(fname) + '</h2>' +
      '<table><thead><tr>';

    hs.forEach(function (h) {
      html += '<th class="' + (h[2] ? 'num' : '') + '">' + esc(h[0]) + '</th>';
    });
    html += '</tr></thead><tbody>';

    rows.forEach(function (row) {
      html += '<tr>';
      hs.forEach(function (h) {
        var v = row[h[1]];
        if (v == null) v = '';
        if (h[2]) v = Number(v || 0).toFixed(h[3] != null ? h[3] : 2);
        html += '<td class="' + (h[2] ? 'num' : '') + '">' + esc(v) + '</td>';
      });
      html += '</tr>';
    });

    html += '</tbody></table></body></html>';
    w.document.write(html);
    w.document.close();
    setTimeout(function () { w.print(); }, 400);
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function downloadBlob(content, filename, mimeType) {
    var bom = '\uFEFF';
    var blob = new Blob([bom + content], { type: mimeType });
    var a = document.createElement('a');

    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.style.display = 'none';

    document.body.appendChild(a);
    a.click();

    setTimeout(function () {
      URL.revokeObjectURL(a.href);
      a.remove();
    }, 1000);
  }

  function initFromTable(btnId, tableSelector, filenameFn) {
    function getHeadersFromTable() {
      var table = document.querySelector(tableSelector);
      if (!table) return [];

      var ths = table.querySelectorAll('thead th, thead td, tr:first-child th, tr:first-child td');
      return Array.from(ths).map(function (th, i) {
        var isNum = th.classList.contains('num') || th.style.textAlign === 'right';
        return [th.textContent.trim(), '_col' + i, isNum ? 1 : 0];
      });
    }

    function getRowsFromTable() {
      var table = document.querySelector(tableSelector);
      if (!table) return [];

      var bodyRows = table.querySelectorAll('tbody tr');
      var trs = bodyRows.length ? bodyRows : table.querySelectorAll('tr:not(:first-child)');

      return Array.from(trs).map(function (tr) {
        var obj = {};
        var cells = tr.querySelectorAll('td');
        cells.forEach(function (td, i) {
          obj['_col' + i] = td.textContent.trim();
        });
        return obj;
      });
    }

    init(btnId, getHeadersFromTable, getRowsFromTable, filenameFn);
  }

  var api = {
    init: init,
    open: open,
    initFromTable: initFromTable
  };

  if (typeof window !== 'undefined') {
    window.ReportExport = api;
  }

  return api;
})();
