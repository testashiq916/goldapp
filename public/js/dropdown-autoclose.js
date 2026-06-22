/**
 * dropdown-autoclose.js
 * ---------------------------------------------------------------------------
 * Closes the dashboard top-menu dropdowns ("System", "Reports", etc.) when the
 * user clicks anywhere outside the menu bar, presses Escape, or clicks into an
 * open module iframe.
 *
 * The same file is loaded in two contexts:
 *   1. The top dashboard window  -> closes menus directly via closeAllMenus().
 *   2. Inside each module iframe  -> messages the parent so it can close them.
 * ---------------------------------------------------------------------------
 */
(function () {
    'use strict';

    var inIframe = false;
    try { inIframe = window.self !== window.top; } catch (e) { inIframe = true; }

    if (inIframe) {
        // Clicking anywhere inside a module iframe should dismiss any menu that
        // is still open in the parent dashboard.
        document.addEventListener('click', function () {
            try { window.parent.postMessage({ type: 'goldapp:close-menus' }, '*'); } catch (e) {}
        }, true);
        return;
    }

    function closeMenus() {
        if (typeof window.closeAllMenus === 'function') {
            window.closeAllMenus();
            return;
        }
        // Fallback if the dashboard helper isn't defined yet.
        document.querySelectorAll('.dropdown.show').forEach(function (el) { el.classList.remove('show'); });
        document.querySelectorAll('.menu-item.active').forEach(function (el) { el.classList.remove('active'); });
    }

    // Outside click: the menu item handlers stopPropagation on their own clicks,
    // so any click that reaches the document is outside the menu. Guard with a
    // closest() check anyway in case that ever changes.
    document.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('#menuBar')) return;
        closeMenus();
    });

    // Escape closes the open menu.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) closeMenus();
    });

    // A child iframe asking us to close (user clicked into a module).
    window.addEventListener('message', function (e) {
        if (e.data && e.data.type === 'goldapp:close-menus') closeMenus();
    });
})();
