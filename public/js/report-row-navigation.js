/**
 * Shared report row navigation.
 * Adds click selection plus Up/Down keyboard movement for report tables.
 */
(function () {
    'use strict';

    var SELECTED_CLASSES = ['report-row-selected', 'sel', 'selected', 'is-selected'];
    var selectedRow = null;

    function injectReadableSelectionStyle() {
        if (document.getElementById('report-row-navigation-style')) return;
        var style = document.createElement('style');
        style.id = 'report-row-navigation-style';
        style.textContent = [
            'body table tbody tr.report-row-selected td,',
            'body table tbody tr.sel td,',
            'body table tbody tr.selected td,',
            'body table tbody tr.is-selected td,',
            'body table tbody tr[aria-selected="true"] td{',
            'background:#ffe8a3!important;color:#111827!important;font-weight:800!important;',
            'box-shadow:inset 4px 0 0 #d97706!important}',
            'body table tbody tr.report-row-selected:focus,',
            'body table tbody tr.sel:focus,',
            'body table tbody tr.selected:focus,',
            'body table tbody tr.is-selected:focus{outline:2px solid #2563eb!important;outline-offset:-2px}',
            'body table tbody td{color:#0f172a!important}',
            'body table thead th{color:#1e293b!important}'
        ].join('');
        document.head.appendChild(style);
    }

    function isEditableTarget(el) {
        if (!el) return false;
        var tag = (el.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'select' || tag === 'textarea' || tag === 'button') return true;
        return !!(el.isContentEditable || (el.closest && el.closest('[contenteditable="true"]')));
    }

    function isReportDateInput(el) {
        if (!el || (el.tagName || '').toLowerCase() !== 'input') return false;
        var type = String(el.type || '').toLowerCase();
        var id = String(el.id || '').toLowerCase();
        var name = String(el.name || '').toLowerCase();
        if (type === 'date') return true;
        return [
            'date1', 'date2', 'date_from', 'date_to', 'datefrom', 'dateto',
            'fromdate', 'todate', 'from_date', 'to_date', 'd1', 'd2'
        ].indexOf(id) !== -1 || [
            'date1', 'date2', 'date_from', 'date_to', 'datefrom', 'dateto',
            'fromdate', 'todate', 'from_date', 'to_date', 'd1', 'd2'
        ].indexOf(name) !== -1;
    }

    function findShowControl(fromEl) {
        var form = fromEl && fromEl.closest ? fromEl.closest('form') : null;
        var scoped = form ? Array.prototype.slice.call(form.querySelectorAll('button,input,a')) : [];
        var global = Array.prototype.slice.call(document.querySelectorAll('button,input,a'));
        var candidates = scoped.concat(global);

        for (var i = 0; i < candidates.length; i++) {
            var el = candidates[i];
            if (!el || el.disabled || el.offsetParent === null) continue;
            var id = String(el.id || '').toLowerCase();
            var name = String(el.name || '').toLowerCase();
            var value = String(el.value || '').toLowerCase();
            var text = String(el.textContent || '').trim().toLowerCase();
            var cls = String(el.className || '').toLowerCase();
            if (
                id === 'btnshow' || id === 'show' ||
                name === 'show' || value === 'show' ||
                text === 'show' || text.indexOf(' show') >= 0 ||
                cls.indexOf('btn-show') >= 0
            ) {
                return el;
            }
        }
        return null;
    }

    function runReportShow(fromEl) {
        var show = findShowControl(fromEl);
        if (show) {
            show.click();
            return true;
        }

        var form = fromEl && fromEl.closest ? fromEl.closest('form') : null;
        if (!form) return false;
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
        return true;
    }

    function rowHasRealCells(row) {
        if (!row || row.hidden) return false;
        var cells = row.cells ? Array.prototype.slice.call(row.cells) : [];
        if (!cells.length) return false;
        if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
            var text = (cells[0].textContent || '').trim().toLowerCase();
            if (!text || text.indexOf('press ') === 0 || text.indexOf('loading') >= 0 || text.indexOf('no ') === 0) {
                return false;
            }
        }
        return row.offsetParent !== null;
    }

    function getRows(table) {
        if (!table) return [];
        var bodies = table.tBodies && table.tBodies.length ? Array.prototype.slice.call(table.tBodies) : [table];
        var rows = [];
        bodies.forEach(function (body) {
            rows = rows.concat(Array.prototype.slice.call(body.rows || []));
        });
        return rows.filter(rowHasRealCells);
    }

    function findReportTableFrom(el) {
        var table = el && el.closest ? el.closest('table') : null;
        if (table && getRows(table).length) return table;
        var tables = Array.prototype.slice.call(document.querySelectorAll('table'));
        tables = tables.filter(function (candidate) { return getRows(candidate).length > 0; });
        if (!tables.length) return null;
        tables.sort(function (a, b) { return getRows(b).length - getRows(a).length; });
        return tables[0];
    }

    function clearSelection(table) {
        if (!table) return;
        getRows(table).forEach(function (row) {
            SELECTED_CLASSES.forEach(function (cls) { row.classList.remove(cls); });
            row.removeAttribute('aria-selected');
        });
    }

    function selectRow(row, shouldFocus) {
        if (!row) return;
        var table = row.closest('table');
        clearSelection(table);
        row.classList.add('report-row-selected');
        row.setAttribute('aria-selected', 'true');
        if (!row.hasAttribute('tabindex')) row.setAttribute('tabindex', '-1');
        selectedRow = row;
        row.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        if (shouldFocus) {
            try { row.focus({ preventScroll: true }); } catch (e) { row.focus(); }
        }
    }

    function currentRowFor(table) {
        if (selectedRow && selectedRow.isConnected && selectedRow.closest('table') === table) return selectedRow;
        return table.querySelector('tbody tr.report-row-selected, tbody tr.sel, tbody tr.selected, tbody tr.is-selected, tbody tr[aria-selected="true"]');
    }

    function moveSelection(direction) {
        var table = findReportTableFrom(document.activeElement);
        if (!table) return false;
        var rows = getRows(table);
        if (!rows.length) return false;

        var current = currentRowFor(table);
        var index = current ? rows.indexOf(current) : -1;
        var nextIndex = index < 0 ? (direction > 0 ? 0 : rows.length - 1) : index + direction;
        nextIndex = Math.max(0, Math.min(rows.length - 1, nextIndex));
        selectRow(rows[nextIndex], true);
        return true;
    }

    function bindExistingSelection() {
        var preselected = document.querySelector('tbody tr.sel, tbody tr.selected, tbody tr.is-selected, tbody tr.report-row-selected, tbody tr[aria-selected="true"]');
        if (preselected) selectRow(preselected, false);
    }

    function init() {
        injectReadableSelectionStyle();
        bindExistingSelection();

        document.addEventListener('click', function (e) {
            var row = e.target && e.target.closest ? e.target.closest('tbody tr') : null;
            if (!row || !rowHasRealCells(row)) return;
            selectRow(row, false);
        }, true);

        document.addEventListener('keydown', function (e) {
            var key = e.key || '';
            if (key === 'Enter' && isReportDateInput(e.target)) {
                if (runReportShow(e.target)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                return;
            }

            if (key !== 'ArrowDown' && key !== 'ArrowUp') return;
            if (e.altKey || e.ctrlKey || e.metaKey) return;
            if (isEditableTarget(e.target) && !(e.target.closest && e.target.closest('tbody tr'))) return;

            if (moveSelection(key === 'ArrowDown' ? 1 : -1)) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);

        document.addEventListener('focusin', function (e) {
            var row = e.target && e.target.closest ? e.target.closest('tbody tr') : null;
            if (row && rowHasRealCells(row)) selectRow(row, false);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
