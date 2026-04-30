/**
 * goldapp-theme.js
 * Applies the selected dashboard theme to iframe-loaded module/report pages.
 * Reads theme from localStorage and listens for goldapp:set-theme postMessage.
 */
(function () {
    var THEMES = {
        'default': {
            tbBg: 'linear-gradient(135deg,#1e3a5f,#2c5282)',
            tbSelBg: '#1e3a5f',
            bodyBg: '#f0f2f5',
            tableBg: '#ffffff', tableColor: '#1e293b',
            tdBorder: '#f1f5f9', trHover: '#f8fafc',
            thBg: '#f1f5f9', thBorder: '#e2e8f0', thColor: '#64748b',
            selBg: '#dbeafe',
            groupBg: '#eff6ff', groupColor: '#1e40af', groupBorder: '#bfdbfe',
            summBg: '#ffffff', summBorder: '#e2e8f0', summSpan: '#64748b', summB: '#1e40af',
            waitBg: '#ffffff', waitColor: '#1e3a5f',
        },
        'alt': {
            tbBg: 'linear-gradient(135deg,#4a221b,#673128)',
            tbSelBg: '#4a221b',
            bodyBg: '#f7efe6',
            tableBg: '#fff8f1', tableColor: '#33160f',
            tdBorder: '#fce8d8', trHover: '#fff2e4',
            thBg: '#fdf0e8', thBorder: '#ead9c7', thColor: '#8a6557',
            selBg: '#fde8dc',
            groupBg: '#fff5ee', groupColor: '#a33d2e', groupBorder: '#f0b496',
            summBg: '#fff8f1', summBorder: '#ead9c7', summSpan: '#8a6557', summB: '#a33d2e',
            waitBg: '#fff8f1', waitColor: '#4a221b',
        },
        'ocean': {
            tbBg: 'linear-gradient(135deg,#0d1b40,#1e3468)',
            tbSelBg: '#0d1b40',
            bodyBg: '#edf1fb',
            tableBg: '#ffffff', tableColor: '#0d1b40',
            tdBorder: '#dce5f5', trHover: '#f4f7fd',
            thBg: '#edf1fb', thBorder: '#dce5f5', thColor: '#4a6898',
            selBg: '#ddeafc',
            groupBg: '#ddeafc', groupColor: '#1755a8', groupBorder: '#a8c4f0',
            summBg: '#ffffff', summBorder: '#dce5f5', summSpan: '#4a6898', summB: '#1755a8',
            waitBg: '#ffffff', waitColor: '#0d1b40',
        },
        'forest': {
            tbBg: 'linear-gradient(135deg,#1a3620,#254d2e)',
            tbSelBg: '#1a3620',
            bodyBg: '#f0f5ee',
            tableBg: '#ffffff', tableColor: '#0d2210',
            tdBorder: '#e1ede0', trHover: '#f5faf4',
            thBg: '#edf5ec', thBorder: '#e1ede0', thColor: '#4a7058',
            selBg: '#d6f0e4',
            groupBg: '#d6f0e4', groupColor: '#1a7a52', groupBorder: '#9ed8bc',
            summBg: '#ffffff', summBorder: '#e1ede0', summSpan: '#4a7058', summB: '#1a7a52',
            waitBg: '#ffffff', waitColor: '#1a3620',
        },
        'rose': {
            tbBg: 'linear-gradient(135deg,#4a0f25,#6a1a38)',
            tbSelBg: '#4a0f25',
            bodyBg: '#fef0f3',
            tableBg: '#ffffff', tableColor: '#33071a',
            tdBorder: '#fce0e8', trHover: '#fff5f7',
            thBg: '#fdedf1', thBorder: '#fce0e8', thColor: '#8a5060',
            selBg: '#fce0e8',
            groupBg: '#fce0e8', groupColor: '#c0365a', groupBorder: '#f5afc0',
            summBg: '#ffffff', summBorder: '#fce0e8', summSpan: '#8a5060', summB: '#c0365a',
            waitBg: '#ffffff', waitColor: '#4a0f25',
        },
        'violet': {
            tbBg: 'linear-gradient(135deg,#2a1550,#3c2068)',
            tbSelBg: '#2a1550',
            bodyBg: '#f3eefb',
            tableBg: '#ffffff', tableColor: '#1a0d33',
            tdBorder: '#e8dff7', trHover: '#f8f4fd',
            thBg: '#f0eafc', thBorder: '#e8dff7', thColor: '#6a4c8a',
            selBg: '#ede0fc',
            groupBg: '#ede0fc', groupColor: '#6433a0', groupBorder: '#c4a8e8',
            summBg: '#ffffff', summBorder: '#e8dff7', summSpan: '#6a4c8a', summB: '#6433a0',
            waitBg: '#ffffff', waitColor: '#2a1550',
        },
        'dark': {
            tbBg: 'linear-gradient(135deg,#0d1117,#1a2234)',
            tbSelBg: '#0d1117',
            bodyBg: '#111827',
            tableBg: '#1f2937', tableColor: '#f1f5f9',
            tdBorder: '#2d3c50', trHover: '#263344',
            thBg: '#1f2937', thBorder: '#2d3c50', thColor: '#64748b',
            selBg: 'rgba(0,191,187,0.15)',
            groupBg: '#263344', groupColor: '#00bfbb', groupBorder: '#004a48',
            summBg: '#1f2937', summBorder: '#2d3c50', summSpan: '#64748b', summB: '#00bfbb',
            waitBg: '#1f2937', waitColor: '#00bfbb',
        },
    };

    function buildCss(t) {
        return [
            'body{background:' + t.bodyBg + ' !important}',
            '.toolbar{background:' + t.tbBg + ' !important}',
            '.tb-select option{background:' + t.tbSelBg + ' !important}',
            'table{background:' + t.tableBg + ' !important}',
            'td{border-bottom-color:' + t.tdBorder + ' !important;color:' + t.tableColor + ' !important}',
            'th{background:' + t.thBg + ' !important;border-bottom-color:' + t.thBorder + ' !important;color:' + t.thColor + ' !important}',
            'th:hover{background:' + t.thBorder + ' !important}',
            'tr:hover td{background:' + t.trHover + ' !important}',
            'tr.sel td{background:' + t.selBg + ' !important}',
            'tr.group-hdr td{background:' + t.groupBg + ' !important;color:' + t.groupColor + ' !important;border-bottom-color:' + t.groupBorder + ' !important}',
            '.summary{background:' + t.summBg + ' !important;border-top-color:' + t.summBorder + ' !important}',
            '.summary span{color:' + t.summSpan + ' !important}',
            '.summary b{color:' + t.summB + ' !important}',
            '.wait-card{background:' + t.waitBg + ' !important}',
            '.wait-card strong{color:' + t.waitColor + ' !important}',
        ].join('');
    }

    function applyTheme(name) {
        var t = THEMES[name] || THEMES['default'];
        var el = document.getElementById('goldapp-theme-override');
        if (!el) {
            el = document.createElement('style');
            el.id = 'goldapp-theme-override';
            document.head.appendChild(el);
        }
        el.textContent = buildCss(t);
    }

    function getTheme() {
        try { return window.localStorage.getItem('goldapp_dash_theme') || 'default'; } catch (e) { return 'default'; }
    }

    function init() {
        applyTheme(getTheme());
        window.addEventListener('message', function (e) {
            if (e.data && e.data.type === 'goldapp:set-theme') {
                applyTheme(e.data.theme || 'default');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
