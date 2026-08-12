<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $siteName }} - User {{ strtoupper($userName) }}</title>
<link rel="preconnect" href="https://cdnjs.cloudflare.com">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"></noscript>
<script>
(function () {
    var logoutUrl = @json(url('/logout') . '?company_db=' . urlencode($currentDatabase ?? ''));
    var loginUrl = @json(url('/login') . '?company_db=' . urlencode($currentDatabase ?? ''));
    var tabKey = 'goldapp_active_dashboard_tab';
    var channelName = 'goldapp_dashboard_tab';
    var heartbeatMs = 2000;
    var staleMs = 8000;
    var tabId = '';
    var blocked = false;

    function now() {
        return Date.now ? Date.now() : new Date().getTime();
    }

    function safeGet(key) {
        try { return window.localStorage.getItem(key); } catch (error) { return null; }
    }

    function safeSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (error) {}
    }

    function activeTab() {
        try {
            return JSON.parse(safeGet(tabKey) || '{}') || {};
        } catch (error) {
            return {};
        }
    }

    function blockTab() {
        if (blocked) return;
        blocked = true;
        var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Dashboard Already Open</title><style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#eef3ef;font-family:Segoe UI,Arial,sans-serif;color:#123f22}.box{width:min(460px,calc(100% - 32px));background:#fff;border:1px solid #cfe0d4;border-radius:12px;box-shadow:0 18px 50px rgba(0,0,0,.12);padding:26px;text-align:center}.title{font-size:22px;font-weight:800;margin-bottom:10px}.msg{font-size:14px;line-height:1.55;color:#496454;margin-bottom:20px}.actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}.btn{border:1px solid #0b7d43;background:#0b7d43;color:#fff;text-decoration:none;border-radius:8px;padding:10px 16px;font-weight:700;font-size:14px}.btn.alt{background:#fff;color:#0b7d43}</style><\\/head><body><div class="box"><div class="title">Dashboard already open</div><div class="msg">This login is already active in another tab. Use the first dashboard tab, or sign out and login again.</div><div class="actions"><a class="btn alt" href="' + loginUrl + '">Check Login</a><a class="btn" href="' + logoutUrl + '">Sign Out</a></div></div><\\/body></html>';
        document.open();
        document.write(html);
        document.close();
    }

    try {
        if (window.sessionStorage.getItem('goldapp_browser_session') !== 'active') {
            blockTab();
            return;
        }
    } catch (error) {
        blockTab();
        return;
    }

    try {
        tabId = window.sessionStorage.getItem('goldapp_dashboard_tab_id') || '';
        if (!tabId) {
            tabId = 'tab-' + now() + '-' + Math.random().toString(36).slice(2);
            window.sessionStorage.setItem('goldapp_dashboard_tab_id', tabId);
        }
    } catch (error) {
        tabId = 'tab-' + now() + '-' + Math.random().toString(36).slice(2);
    }

    var active = activeTab();
    if (active.id && active.id !== tabId && (now() - Number(active.at || 0)) < staleMs) {
        blockTab();
        return;
    }

    function heartbeat() {
        if (blocked) return;
        safeSet(tabKey, JSON.stringify({ id: tabId, at: now() }));
    }

    heartbeat();
    window.setInterval(heartbeat, heartbeatMs);

    if ('BroadcastChannel' in window) {
        var channel = new BroadcastChannel(channelName);
        channel.onmessage = function (event) {
            var data = event.data || {};
            if (blocked) return;
            if (data.type === 'claim' && data.id !== tabId) {
                channel.postMessage({ type: 'occupied', to: data.id, id: tabId });
                return;
            }
            if (data.type === 'occupied' && data.to === tabId) {
                blockTab();
            }
        };
        channel.postMessage({ type: 'claim', id: tabId });
    }
})();
</script>
<style>
:root{--bg:#f0f2f5;--bg2:#e8eaee;--surface:#ffffff;--surface2:#f7f8fa;--surface3:#eef0f3;--line:rgba(0,0,0,0.07);--line2:rgba(0,0,0,0.12);--teal:#007d7a;--teal2:#009e9a;--teal3:#b2e4e3;--teal-pale:rgba(0,125,122,0.08);--teal-mid:rgba(0,125,122,0.15);--navy:#12233e;--navy2:#1c3154;--navy3:#2a4270;--gold:#c09318;--gold2:#e0ae24;--gold3:#f5d060;--gold-pale:rgba(192,147,24,0.10);--green:#1a7a52;--green-pale:#d6f0e4;--red:#b83232;--red-pale:#fde8e8;--amber:#c06818;--amber-pale:#fdeede;--blue:#1755a8;--blue-pale:#ddeafc;--purple:#6433a0;--purple-pale:#ede0fc;--ink:#0d1117;--ink2:#2d3748;--ink3:#64748b;--ink4:#94a3b8;--ff-head:'Segoe UI',Tahoma,Verdana,Arial,sans-serif;--ff-body:'Segoe UI',Tahoma,Verdana,Arial,sans-serif;--ff-mono:Consolas,'Courier New',monospace;--r:14px;--r-sm:10px;--r-xs:6px;--r-pill:999px;--s1:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);--s2:0 4px 12px rgba(0,0,0,0.07),0 2px 4px rgba(0,0,0,0.04);--s3:0 10px 30px rgba(0,0,0,0.09),0 4px 10px rgba(0,0,0,0.05);--s4:0 20px 60px rgba(0,0,0,0.14),0 8px 20px rgba(0,0,0,0.07);--s-teal:0 6px 24px rgba(0,125,122,0.22);--ez:cubic-bezier(0.16,1,0.3,1)}
body.theme-alt{--bg:#f7efe6;--bg2:#ead9c7;--surface:#fff8f1;--surface2:#fff2e4;--surface3:#f4e3d4;--line:rgba(120,65,20,0.10);--line2:rgba(120,65,20,0.18);--teal:#a33d2e;--teal2:#c45b38;--teal3:#f0b496;--teal-pale:rgba(163,61,46,0.10);--teal-mid:rgba(163,61,46,0.16);--navy:#4a221b;--navy2:#673128;--navy3:#844235;--gold:#b76b0f;--gold2:#d9911a;--gold3:#f2be62;--gold-pale:rgba(183,107,15,0.12);--green:#486b2d;--green-pale:#e1efd5;--red:#a42d2d;--red-pale:#fde3de;--amber:#d46a16;--amber-pale:#fbe8d8;--blue:#8b3f1f;--blue-pale:#f4dfd4;--purple:#8f4b2c;--purple-pale:#f3e3d8;--ink:#33160f;--ink2:#5a3429;--ink3:#8a6557;--ink4:#b79483;--s-teal:0 6px 24px rgba(163,61,46,0.22)}
body.theme-ocean{--bg:#edf1fb;--bg2:#dce5f5;--surface:#ffffff;--surface2:#f4f7fd;--surface3:#eaeff8;--line:rgba(23,85,168,0.08);--line2:rgba(23,85,168,0.16);--teal:#1755a8;--teal2:#2166cc;--teal3:#a8c4f0;--teal-pale:rgba(23,85,168,0.08);--teal-mid:rgba(23,85,168,0.15);--navy:#0d1b40;--navy2:#152650;--navy3:#1e3468;--gold:#b8860b;--gold2:#d4a017;--gold3:#f0cc50;--gold-pale:rgba(184,134,11,0.10);--green:#1a7a52;--green-pale:#d6f0e4;--red:#b83232;--red-pale:#fde8e8;--amber:#c06818;--amber-pale:#fdeede;--blue:#1755a8;--blue-pale:#ddeafc;--purple:#6433a0;--purple-pale:#ede0fc;--ink:#0d1b40;--ink2:#1e3468;--ink3:#4a6898;--ink4:#8aa0c0;--s-teal:0 6px 24px rgba(23,85,168,0.22)}
body.theme-forest{--bg:#f0f5ee;--bg2:#e1ede0;--surface:#ffffff;--surface2:#f5faf4;--surface3:#edf5ec;--line:rgba(26,122,82,0.08);--line2:rgba(26,122,82,0.16);--teal:#1a7a52;--teal2:#22996a;--teal3:#9ed8bc;--teal-pale:rgba(26,122,82,0.08);--teal-mid:rgba(26,122,82,0.15);--navy:#1a3620;--navy2:#254d2e;--navy3:#30643a;--gold:#b8860b;--gold2:#d4a017;--gold3:#f0cc50;--gold-pale:rgba(184,134,11,0.10);--green:#1a7a52;--green-pale:#d6f0e4;--red:#b83232;--red-pale:#fde8e8;--amber:#c06818;--amber-pale:#fdeede;--blue:#1755a8;--blue-pale:#ddeafc;--purple:#6433a0;--purple-pale:#ede0fc;--ink:#0d2210;--ink2:#1e4425;--ink3:#4a7058;--ink4:#89a892;--s-teal:0 6px 24px rgba(26,122,82,0.22)}
body.theme-rose{--bg:#fef0f3;--bg2:#fce0e8;--surface:#ffffff;--surface2:#fff5f7;--surface3:#fdedf1;--line:rgba(192,54,90,0.08);--line2:rgba(192,54,90,0.16);--teal:#c0365a;--teal2:#d94e72;--teal3:#f5afc0;--teal-pale:rgba(192,54,90,0.08);--teal-mid:rgba(192,54,90,0.15);--navy:#4a0f25;--navy2:#6a1a38;--navy3:#8a264c;--gold:#b8860b;--gold2:#d4a017;--gold3:#f0cc50;--gold-pale:rgba(184,134,11,0.10);--green:#1a7a52;--green-pale:#d6f0e4;--red:#b83232;--red-pale:#fde8e8;--amber:#c06818;--amber-pale:#fdeede;--blue:#1755a8;--blue-pale:#ddeafc;--purple:#6433a0;--purple-pale:#ede0fc;--ink:#33071a;--ink2:#5a1430;--ink3:#8a5060;--ink4:#b88090;--s-teal:0 6px 24px rgba(192,54,90,0.22)}
body.theme-violet{--bg:#f3eefb;--bg2:#e8dff7;--surface:#ffffff;--surface2:#f8f4fd;--surface3:#f0eafc;--line:rgba(100,51,160,0.08);--line2:rgba(100,51,160,0.16);--teal:#6433a0;--teal2:#7d48c0;--teal3:#c4a8e8;--teal-pale:rgba(100,51,160,0.08);--teal-mid:rgba(100,51,160,0.15);--navy:#2a1550;--navy2:#3c2068;--navy3:#502a84;--gold:#b8860b;--gold2:#d4a017;--gold3:#f0cc50;--gold-pale:rgba(184,134,11,0.10);--green:#1a7a52;--green-pale:#d6f0e4;--red:#b83232;--red-pale:#fde8e8;--amber:#c06818;--amber-pale:#fdeede;--blue:#1755a8;--blue-pale:#ddeafc;--purple:#6433a0;--purple-pale:#ede0fc;--ink:#1a0d33;--ink2:#341a5c;--ink3:#6a4c8a;--ink4:#9e84b8;--s-teal:0 6px 24px rgba(100,51,160,0.28)}
body.theme-dark{--bg:#111827;--bg2:#1a2234;--surface:#1f2937;--surface2:#263344;--surface3:#2d3c50;--line:rgba(255,255,255,0.07);--line2:rgba(255,255,255,0.13);--teal:#00bfbb;--teal2:#00d9d5;--teal3:#004a48;--teal-pale:rgba(0,191,187,0.14);--teal-mid:rgba(0,191,187,0.22);--navy:#0d1117;--navy2:#111827;--navy3:#1f2937;--gold:#e0ae24;--gold2:#f5d060;--gold3:#c09318;--gold-pale:rgba(224,174,36,0.15);--green:#22c97c;--green-pale:rgba(34,201,124,0.12);--red:#ef5b5b;--red-pale:rgba(239,91,91,0.12);--amber:#f0943c;--amber-pale:rgba(240,148,60,0.12);--blue:#4d9ef0;--blue-pale:rgba(77,158,240,0.12);--purple:#a87ee8;--purple-pale:rgba(168,126,232,0.12);--ink:#f1f5f9;--ink2:#cbd5e1;--ink3:#64748b;--ink4:#475569;--s1:0 1px 3px rgba(0,0,0,0.3),0 1px 2px rgba(0,0,0,0.2);--s2:0 4px 12px rgba(0,0,0,0.35),0 2px 4px rgba(0,0,0,0.2);--s3:0 10px 30px rgba(0,0,0,0.4),0 4px 10px rgba(0,0,0,0.25);--s4:0 20px 60px rgba(0,0,0,0.5),0 8px 20px rgba(0,0,0,0.3);--s-teal:0 6px 24px rgba(0,191,187,0.35)}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;overflow:hidden}
body{font-family:var(--ff-body);background:var(--bg);color:var(--ink);font-size:17px;line-height:1.5;-webkit-font-smoothing:auto;-moz-osx-font-smoothing:auto;text-rendering:optimizeSpeed}
::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--line2);border-radius:10px}
.shell{display:grid;grid-template-columns:250px minmax(0,1fr);height:100vh;height:100dvh;animation:shellIn .6s var(--ez) both}
@keyframes shellIn{from{opacity:0;transform:scale(.98)}to{opacity:1;transform:scale(1)}}

/* SIDEBAR */
.sidebar{background:var(--navy);display:flex;flex-direction:column;overflow:hidden;position:relative;z-index:100;min-height:0}
.sidebar::before{content:'';position:absolute;top:-60px;right:-60px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(0,158,154,.18) 0%,transparent 70%);pointer-events:none}
.sidebar::after{content:'';position:absolute;bottom:-40px;left:-40px;width:160px;height:160px;border-radius:50%;background:radial-gradient(circle,rgba(192,147,24,.12) 0%,transparent 70%);pointer-events:none}
.sidebar-brand{padding:20px 18px 16px;border-bottom:1px solid rgba(255,255,255,.07);position:relative;z-index:1}
.sb-logo{display:flex;align-items:center;gap:10px;margin-bottom:4px}
.sb-gem{width:42px;height:42px;background:linear-gradient(135deg,var(--teal2),var(--teal));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:19px;color:#fff;box-shadow:var(--s-teal);flex-shrink:0;animation:gemPulse 3s ease infinite}
@keyframes gemPulse{0%,100%{box-shadow:0 6px 24px rgba(0,125,122,.22)}50%{box-shadow:0 6px 32px rgba(0,158,154,.45)}}
.sb-title{font-family:var(--ff-head);font-size:18px;font-weight:800;color:#fff;letter-spacing:.3px;line-height:1.2}
.sb-rates{padding:12px 18px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;gap:7px;position:relative;z-index:1}
.sbr-row{display:flex;align-items:center;justify-content:space-between}
.sbr-label{font-size:13px;font-weight:600;letter-spacing:1.2px;text-transform:uppercase;color:rgba(255,255,255,.4);display:flex;align-items:center;gap:5px}
.sbr-dot{width:5px;height:5px;border-radius:50%;animation:dotPulse 2s ease infinite}
@keyframes dotPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.5)}}
.sbr-val{font-family:var(--ff-mono);font-size:16px;font-weight:500}
.sbr-22 .sbr-dot{background:var(--gold3);box-shadow:0 0 6px var(--gold2)}.sbr-22 .sbr-val{color:var(--gold3)}
.sbr-18 .sbr-dot{background:#f4a94e;box-shadow:0 0 6px #e07a20}.sbr-18 .sbr-val{color:#f4a94e}
.sbr-ag .sbr-dot{background:#c8d0dc}.sbr-ag .sbr-val{color:#c8d0dc}
.sb-nav{flex:1;min-height:0;overflow-y:scroll;overflow-x:hidden;padding:10px 7px 10px 10px;position:relative;z-index:1;scrollbar-gutter:stable;scrollbar-width:auto;scrollbar-color:#8aa896 rgba(255,255,255,.12)}
.sb-nav::-webkit-scrollbar{width:12px}.sb-nav::-webkit-scrollbar-track{background:rgba(255,255,255,.12);border-left:1px solid rgba(255,255,255,.08)}.sb-nav::-webkit-scrollbar-thumb{background:#8aa896;border:3px solid #123f22;border-radius:10px}.sb-nav::-webkit-scrollbar-thumb:hover{background:#b7c8bd}
.sb-section-label{font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.25);padding:10px 8px 4px}
.sb-link{display:flex;align-items:center;gap:9px;padding:10px 10px;border-radius:var(--r-xs);color:rgba(255,255,255,.55);font-size:17px;font-weight:600;cursor:pointer;transition:all .18s var(--ez);position:relative;user-select:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-link:hover{background:rgba(255,255,255,.07);color:rgba(255,255,255,.9);padding-left:14px}
.sb-link.active{background:rgba(0,158,154,.2);color:#fff}
.sb-link.active::before{content:'';position:absolute;left:0;top:4px;bottom:4px;width:3px;background:var(--teal2);border-radius:0 3px 3px 0}
.sb-link i{width:22px;text-align:center;font-size:20px;flex-shrink:0}
.sb-link.active i{color:var(--teal2)}.sb-link:hover i{color:rgba(255,255,255,.8)}
.sb-footer{padding:12px 18px;border-top:1px solid rgba(255,255,255,.07);position:relative;z-index:1}
.sb-user{display:flex;align-items:center;gap:9px;cursor:pointer;padding:6px 8px;border-radius:var(--r-xs);transition:background .18s;position:relative}
.sb-user:hover{background:rgba(255,255,255,.06)}
.sb-avatar{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,rgba(0,158,154,.5),rgba(18,35,62,.8));border:1.5px solid rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff;flex-shrink:0}
.sb-uname{font-size:16px;font-weight:600;color:rgba(255,255,255,.8)}
.sb-urole{font-size:13px;color:rgba(255,255,255,.35);letter-spacing:.5px}
.sb-user-menu{position:absolute;bottom:100%;right:0;min-width:180px;background:var(--surface);border:1px solid var(--line2);border-radius:var(--r-sm);box-shadow:var(--s4);display:none;z-index:999;overflow:hidden;animation:popUp .18s var(--ez)}
@keyframes popUp{from{opacity:0;transform:translateY(8px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.sb-user-menu.open{display:block}
.sb-user-menu a{display:flex;align-items:center;gap:9px;padding:10px 14px;color:var(--ink2);text-decoration:none;font-size:14px;font-weight:500;border-bottom:1px solid var(--line);transition:all .12s}
.sb-user-menu a:last-child{border-bottom:none}
.sb-user-menu a:hover{background:var(--surface2);color:var(--ink);padding-left:18px}
.sb-user-menu a i{color:var(--teal);width:14px;text-align:center;font-size:11px}

/* MAIN */
.main{display:grid;grid-template-rows:52px 44px minmax(0,1fr) 26px;overflow:hidden;background:var(--bg);min-width:0;min-height:0}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:0 20px;background:var(--surface);border-bottom:1px solid var(--line);box-shadow:var(--s1);position:relative;z-index:3000}
.topbar-left{display:flex;align-items:center;gap:16px}
.page-title{font-family:var(--ff-head);font-size:22px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px}
.page-title i{color:var(--teal);font-size:19px}
.breadcrumb{font-size:15px;color:var(--ink4);display:flex;align-items:center;gap:4px}
.breadcrumb span:last-child{color:var(--teal);font-weight:600}
.topbar-right{display:flex;align-items:center;gap:10px}
.tb-icon-btn{width:38px;height:38px;border-radius:var(--r-xs);border:1px solid var(--line);background:var(--surface2);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:16px;color:var(--ink3);transition:all .18s;position:relative}
.tb-icon-btn:hover{background:var(--teal-pale);border-color:var(--teal3);color:var(--teal);transform:translateY(-1px);box-shadow:var(--s2)}
.theme-toggle-btn.active{background:var(--teal-pale);border-color:var(--teal3);color:var(--teal);box-shadow:var(--s2)}
.theme-toggle-btn.company-close-btn{background:var(--red-pale);border-color:rgba(184,50,50,.24);color:var(--red);box-shadow:none}
.theme-toggle-btn.company-close-btn:hover{background:var(--red-pale);border-color:rgba(184,50,50,.34);color:var(--red);box-shadow:var(--s2)}
.notif-badge{position:absolute;top:-3px;right:-3px;width:14px;height:14px;background:var(--red);border-radius:50%;font-size:7.5px;font-weight:700;color:#fff;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--surface)}
.date-chip{display:flex;align-items:center;gap:6px;padding:6px 14px;background:var(--navy);border-radius:var(--r-pill);font-family:var(--ff-mono);font-size:14px;color:rgba(255,255,255,.75)}
.date-chip i{color:var(--teal2);font-size:10px}
.phone-lookup{position:relative;width:230px}
.phone-lookup-box{height:30px;display:flex;align-items:center;background:var(--surface2);border:1px solid var(--line);border-radius:var(--r-xs);overflow:hidden}
.phone-lookup-box i{width:30px;text-align:center;color:var(--teal);font-size:12px}
.phone-lookup-box input{height:100%;min-width:0;flex:1;border:0;background:transparent;color:var(--ink);font-family:var(--ff-body);font-size:14px;outline:0}
.phone-lookup-box button{width:30px;height:100%;border:0;border-left:1px solid var(--line);background:transparent;color:var(--ink3);cursor:pointer}
.phone-lookup-box button:hover{background:var(--teal-pale);color:var(--teal)}
.phone-lookup-results{position:absolute;right:0;top:calc(100% + 6px);width:360px;max-height:280px;overflow:auto;background:var(--surface);border:1px solid var(--line2);border-radius:var(--r-sm);box-shadow:var(--s4);z-index:3600;display:none;padding:6px}
.phone-lookup-results.show{display:block}
.phone-lookup-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center;padding:8px;border-radius:var(--r-xs);border-bottom:1px solid var(--line)}
.phone-lookup-row:last-child{border-bottom:0}
.phone-lookup-row:hover{background:var(--teal-pale)}
.phone-lookup-name{font-weight:700;color:var(--ink);font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.phone-lookup-meta{font-size:12px;color:var(--ink4);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.phone-copy-btn{height:26px;padding:0 8px;border:1px solid var(--line);border-radius:var(--r-xs);background:var(--surface2);color:var(--ink2);font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px}
.phone-copy-btn:hover{border-color:var(--teal);color:var(--teal)}
.phone-lookup-empty{padding:12px;text-align:center;color:var(--ink4);font-size:12px}

/* MENUBAR */
.menubar{display:flex;align-items:center;gap:1px;padding:0 14px;background:var(--surface2);border-bottom:1px solid var(--line);overflow:visible;position:relative;z-index:2500}
.menu-item{position:relative;height:100%;display:flex;align-items:center;padding:0 14px;font-family:var(--ff-head);font-size:17px;font-weight:600;letter-spacing:.3px;color:var(--ink3);cursor:pointer;white-space:nowrap;border-radius:var(--r-xs);margin:4px 1px;transition:all .15s var(--ez);user-select:none}
.menu-item.has-dropdown{gap:7px}
.menu-heading-text{display:inline-flex;align-items:center}
.menu-heading-arrow{font-size:10px;line-height:1;opacity:.72;transition:transform .16s var(--ez),opacity .16s var(--ez)}
.menu-item.active .menu-heading-arrow{transform:rotate(180deg);opacity:1}
.menu-item::after{content:'';position:absolute;bottom:-1px;left:8px;right:8px;height:2px;background:linear-gradient(90deg,var(--teal),var(--teal2));border-radius:2px;transform:scaleX(0);transform-origin:left;transition:transform .25s var(--ez)}
.menu-item:hover,.menu-item.open,.menu-item.active{color:var(--teal);background:var(--teal-pale)}
.menu-item:hover::after,.menu-item.open::after,.menu-item.active::after{transform:scaleX(1)}
.dropdown{position:absolute;top:calc(100% + 3px);left:0;background:var(--surface);border:1px solid var(--line2);border-radius:var(--r-sm);box-shadow:var(--s4);min-width:200px;padding:4px;z-index:3000;display:none;animation:dropIn .17s var(--ez)}
@keyframes dropIn{from{opacity:0;transform:translateY(-6px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.dropdown.show{display:block}
.dropdown.dd-cols-2{display:none;flex-direction:column;flex-wrap:wrap;align-content:flex-start;max-height:88vh;min-width:440px}
.dropdown.dd-cols-2.show{display:flex}
.dd-item{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 14px;font-size:16px;font-weight:500;border-radius:var(--r-xs);cursor:pointer;color:var(--ink2);transition:all .12s;position:relative;white-space:nowrap;line-height:1.4}
.dd-item:hover{background:var(--teal-pale);color:var(--teal);padding-left:15px}
.dd-left{display:inline-flex;align-items:center;gap:10px;flex:1}
.dd-left i{width:16px;text-align:center;font-size:12px;color:var(--teal2);flex-shrink:0;transition:transform .18s var(--ez)}
.dd-item:hover .dd-left i{transform:scale(1.2)}
.dd-key{font-family:var(--ff-mono);font-size:11px;color:var(--ink4);background:var(--surface3);border:1px solid var(--line);border-radius:3px;padding:2px 6px}
.submenu{position:absolute;left:100%;top:-4px;background:var(--surface);border:1px solid var(--line2);border-radius:var(--r-sm);box-shadow:var(--s4);min-width:260px;padding:5px;z-index:600;display:none;animation:dropIn .15s var(--ez);margin-left:2px}
.submenu .dd-item{padding:8px 13px;font-size:15px;gap:12px}
.submenu .dd-item:hover{padding-left:16px}
.submenu .dd-left{gap:12px}
.submenu .dd-left i{width:16px;font-size:12px}
.submenu .dd-key{font-size:11px;padding:2px 6px}
.dd-item:hover>.submenu{display:block}

/* CONTENT */
.content{overflow:hidden;position:relative;background:var(--bg);display:flex;flex-direction:column;min-width:0;min-height:0}
.home-screen{flex:1;overflow-y:auto;overflow-x:hidden;padding:clamp(14px,1.8vw,26px) clamp(16px,2.2vw,32px) 28px;display:flex;flex-direction:column;gap:18px;min-width:0}
.access-denied-wrap{flex:1;min-height:60vh;display:flex;align-items:center;justify-content:center;padding:32px}
.access-denied-box{width:min(520px,100%);background:var(--surface);border:1px solid var(--line2);border-radius:var(--r-sm);box-shadow:var(--s3);padding:30px 34px;text-align:center}
.access-denied-icon{width:64px;height:64px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--red-pale);color:var(--red);font-size:28px;margin-bottom:16px}
.access-denied-box h2{font-size:24px;color:var(--ink);margin:0 0 8px;font-weight:800}
.access-denied-box p{font-size:15px;color:var(--ink3);line-height:1.6;margin:0}

/* TAB BAR */
.tab-bar{height:38px;display:flex;align-items:stretch;background:var(--surface2);border-bottom:1px solid var(--line);flex-shrink:0;padding:0 0 0 6px;gap:0;z-index:100}
.tab-bar-home,.tab-bar-back{width:38px;display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;background:transparent;color:var(--ink3);font-size:13px;transition:all .15s;flex-shrink:0;border-right:1px solid var(--line)}
.tab-bar-home:hover,.tab-bar-back:hover{color:var(--teal);background:var(--teal-pale)}
.tab-bar-home.active{color:var(--teal);background:var(--teal-pale)}
.tab-bar-back{margin-right:8px;border-left:1px solid var(--line)}
.tab-bar-tabs{display:flex;align-items:stretch;gap:0;overflow-x:auto;flex:1;scrollbar-width:none}
.tab-bar-tabs::-webkit-scrollbar{display:none}
.tab-item{display:flex;align-items:center;gap:6px;height:100%;padding:0 10px 0 16px;font-family:var(--ff-head);font-size:15px;font-weight:600;color:var(--ink3);cursor:pointer;white-space:nowrap;border-right:1px solid var(--line);max-width:200px;min-width:90px;transition:all .15s;position:relative;user-select:none;background:transparent}
.tab-item:hover{background:var(--surface);color:var(--ink)}
.tab-item.active{background:var(--bg);color:var(--teal);box-shadow:inset 0 -2px 0 var(--teal)}
.tab-item-label{overflow:hidden;text-overflow:ellipsis;flex:1}
.tab-item-refresh,.tab-item-close{width:18px;height:18px;display:flex;align-items:center;justify-content:center;border-radius:var(--r-xs);font-size:10px;color:var(--ink4);cursor:pointer;transition:all .12s;flex-shrink:0;opacity:0;border:none;background:transparent;padding:0}
.tab-item:hover .tab-item-refresh,.tab-item.active .tab-item-refresh,.tab-item:hover .tab-item-close,.tab-item.active .tab-item-close{opacity:1}
.tab-item-refresh:hover{background:var(--teal-pale);color:var(--teal)}
.tab-item-close:hover{background:var(--red-pale);color:var(--red)}

/* TAB FRAMES CONTAINER */
.tab-frames-container{flex:1;position:relative;display:none;min-width:0;min-height:0}
.tab-frames-container.show{display:flex}
.tab-iframe{position:absolute;inset:0;width:100%;height:100%;border:0;background:#fff;display:none;min-width:0}
.tab-iframe.active{display:block}

/* MODULE FRAME SCREEN (legacy - kept for compatibility) */
.module-frame-screen{position:absolute;inset:0;display:none;z-index:2000;background:var(--bg);flex-direction:column;min-width:0;min-height:0}
.module-frame-screen.show-frame{display:flex}
.module-frame-head{height:40px;display:flex;align-items:center;gap:12px;padding:0 18px;background:var(--surface);border-bottom:1px solid var(--line);box-shadow:var(--s1);flex-shrink:0;min-width:0}
.module-frame-title{font-family:var(--ff-head);font-size:13px;font-weight:700;color:var(--ink)}
.module-frame-path{margin-left:auto;font-family:var(--ff-mono);font-size:10px;color:var(--ink4);background:var(--surface3);border:1px solid var(--line);border-radius:var(--r-pill);padding:2px 12px;max-width:380px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.module-frame-open-tab,.module-frame-close{height:28px;padding:0 14px;background:var(--surface2);border:1px solid var(--line2);border-radius:var(--r-xs);color:var(--ink2);font-family:var(--ff-body);font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all .15s}
.module-frame-open-tab:hover{background:#e7f1ff;border-color:rgba(36,112,176,.3);color:#0f3e67}
.module-frame-close:hover{background:var(--red-pale);border-color:rgba(184,50,50,.3);color:var(--red)}
.module-frame-iframe{flex:1;width:100%;border:0;background:#fff;min-width:0;min-height:0}

/* STATUSBAR */
.statusbar{display:flex;align-items:center;background:var(--navy);font-size:13px;color:rgba(255,255,255,.4);font-family:var(--ff-mono);min-width:0;overflow:hidden}
.sb-seg{display:flex;align-items:center;gap:5px;padding:0 14px;border-right:1px solid rgba(255,255,255,.06);height:100%;white-space:nowrap}
.sb-seg i{font-size:8.5px;color:var(--teal2)}
.sb-online .sb-live{display:inline-block;width:5px;height:5px;background:var(--teal2);border-radius:50%;box-shadow:0 0 6px var(--teal2);animation:dotPulse 2s ease infinite}
.sb-right{margin-left:auto;border-right:none!important}
.sb-hi{color:var(--teal3);font-weight:500}
body.company-secondary .statusbar{background:#d91505;color:rgba(255,255,255,.55)}
body.company-secondary .sb-seg i{color:#ffb199}
body.company-secondary .sb-online .sb-live{background:#ff6b4a;box-shadow:0 0 6px #ff6b4a}
body.company-secondary .sb-hi{color:#ffd5c8}

/* DASHBOARD CARDS */
.dash-row{animation:rowUp .55s var(--ez) both}
.dash-row:nth-child(1){animation-delay:.18s}.dash-row:nth-child(2){animation-delay:.26s}.dash-row:nth-child(3){animation-delay:.34s}.dash-row:nth-child(4){animation-delay:.42s}.dash-row:nth-child(5){animation-delay:.50s}
@keyframes rowUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
.quick-bar{display:flex;align-items:center;gap:6px;padding:10px 16px;background:var(--surface);border-radius:var(--r);border:1px solid var(--line);box-shadow:var(--s1);overflow-x:auto;flex-wrap:wrap}
.qb-heading{font-family:var(--ff-head);font-size:16px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--ink4);white-space:nowrap;padding-right:8px;border-right:1px solid var(--line2);margin-right:4px}
.qb-btn{display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 16px;border-radius:var(--r-pill);border:1px solid var(--line);background:var(--surface2);color:var(--ink2);font-family:var(--ff-body);font-size:16px;font-weight:600;cursor:pointer;white-space:nowrap;transition:all .2s var(--ez)}
.qb-btn:hover{transform:translateY(-2px);box-shadow:var(--s2);border-color:var(--line2)}
.qb-btn:active{transform:translateY(0)}
.qb-btn i{font-size:13px}
.qb-btn.t-teal{background:var(--teal);border-color:var(--teal);color:#fff}
.qb-btn.t-teal:hover{background:var(--teal2);box-shadow:var(--s-teal)}
.qb-btn.t-navy{background:var(--navy);border-color:var(--navy);color:#fff}
.qb-sep{width:1px;height:20px;background:var(--line2);margin:0 4px;flex-shrink:0}
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.stat-card{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:18px 20px;box-shadow:var(--s1);transition:all .25s var(--ez);position:relative;overflow:hidden;cursor:default}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;transition:height .25s var(--ez)}
.sc-teal::before{background:linear-gradient(90deg,var(--teal),var(--teal2))}.sc-gold::before{background:linear-gradient(90deg,var(--gold),var(--gold3))}.sc-green::before{background:linear-gradient(90deg,var(--green),#3ab07a)}.sc-amber::before{background:linear-gradient(90deg,var(--amber),#e8941a)}
.stat-card:hover{box-shadow:var(--s3);transform:translateY(-3px)}.stat-card:hover::before{height:5px}
.stat-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
.stat-label{font-size:13px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--ink4)}
.stat-icon{width:40px;height:40px;border-radius:var(--r-xs);display:flex;align-items:center;justify-content:center;font-size:16px;transition:transform .25s var(--ez)}
.stat-card:hover .stat-icon{transform:scale(1.12) rotate(-4deg)}
.si-teal{background:var(--teal-pale);color:var(--teal)}.si-gold{background:var(--gold-pale);color:var(--gold)}.si-green{background:var(--green-pale);color:var(--green)}.si-amber{background:var(--amber-pale);color:var(--amber)}
.stat-val{font-family:var(--ff-head);font-size:32px;font-weight:800;color:var(--ink);letter-spacing:-.5px;line-height:1;margin-bottom:6px}
.stat-foot{display:flex;align-items:center;gap:8px}
.stat-sub{font-size:14px;color:var(--ink4);font-weight:500}
.stat-progress{flex:1;height:3px;background:var(--bg2);border-radius:999px;overflow:hidden}
.stat-bar{height:100%;border-radius:999px;animation:barGrow 1.2s .6s var(--ez) both}
@keyframes barGrow{from{width:0%}}
.sc-teal .stat-bar{background:linear-gradient(90deg,var(--teal),var(--teal2));width:35%}.sc-gold .stat-bar{background:linear-gradient(90deg,var(--gold),var(--gold3));width:22%}.sc-green .stat-bar{background:linear-gradient(90deg,var(--green),#3ab07a);width:48%}.sc-amber .stat-bar{background:linear-gradient(90deg,var(--amber),#e8941a);width:14%}
.rate-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.rate-card{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:14px 18px;box-shadow:var(--s1);transition:all .22s var(--ez)}
.rate-card:hover{box-shadow:var(--s2);transform:translateY(-2px)}
.rc-lbl{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--ink4);margin-bottom:8px}
.rg22 .rc-lbl i{color:var(--gold2)}.rg18 .rc-lbl i{color:var(--amber)}.rgag .rc-lbl i{color:var(--ink3)}.rgth .rc-lbl i{color:var(--purple)}
.rc-num{font-family:var(--ff-head);font-size:30px;font-weight:800;letter-spacing:-.5px;line-height:1}
.rg22 .rc-num{color:var(--gold)}.rg18 .rc-num{color:var(--amber)}.rgag .rc-num{color:var(--ink2)}.rgth .rc-num{color:var(--purple)}
.rc-unit{font-size:13px;color:var(--ink4);margin-top:5px;font-weight:500}
.analytics-row{display:grid;grid-template-columns:1.7fr 1fr;gap:18px}
.panel{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:24px;box-shadow:var(--s1);transition:box-shadow .22s}
.panel:hover{box-shadow:var(--s2)}
.panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.panel-title{font-family:var(--ff-head);font-size:18px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px}
.panel-title i{font-size:16px;color:var(--teal2)}
.panel-pills{display:flex;gap:4px}
.ppill{font-size:11.5px;font-weight:700;font-family:var(--ff-head);padding:4px 12px;border-radius:var(--r-pill);background:var(--surface3);border:1px solid var(--line);color:var(--ink3);cursor:pointer;transition:all .15s}
.ppill.active,.ppill:hover{background:var(--teal);border-color:var(--teal);color:#fff}
.chart-wrap{position:relative;height:265px}
.bottom-row{display:grid;grid-template-columns:1fr 1fr .85fr;gap:18px}
.info-panel{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:18px 20px;box-shadow:var(--s1);display:flex;flex-direction:column;min-height:192px;transition:box-shadow .22s}
.info-panel:hover{box-shadow:var(--s2)}
.ip-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:13px}
.ip-title{font-family:var(--ff-head);font-size:15px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px}
.ip-title i{font-size:14px;color:var(--teal2)}
.ip-link{font-size:12px;font-weight:700;color:var(--teal);cursor:pointer;font-family:var(--ff-head);transition:color .15s}
.ip-link:hover{color:var(--teal2);text-decoration:underline}
.info-list{list-style:none;display:grid;gap:10px;flex:1}
.info-list li{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:13px 16px;border:1px solid var(--line);border-radius:12px;background:var(--surface2)}
.il-name{font-size:15px;font-weight:700;color:var(--ink2)}
.il-sub{font-size:13px;color:var(--ink4);margin-top:2px}
.il-amt{font-family:var(--ff-mono);font-size:15px;font-weight:700;color:var(--teal);white-space:nowrap}
.il-amt.negative{color:var(--red)}
.ip-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;color:var(--ink4);font-size:13px}
.ip-empty i{font-size:28px;color:var(--line2)}
.startup-overlay{position:fixed;inset:0;background:rgba(13,17,23,.54);backdrop-filter:blur(3px);z-index:7000;display:none;align-items:center;justify-content:center;padding:24px}
.startup-overlay.show{display:flex}
.startup-modal{width:min(980px,96vw);max-height:min(720px,92vh);background:var(--surface);border:1px solid var(--line2);border-radius:var(--r);box-shadow:var(--s4);display:flex;flex-direction:column;overflow:hidden}
.startup-modal.sm{width:min(900px,96vw)}
.startup-head{height:48px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 16px;border-bottom:1px solid var(--line);background:var(--surface2)}
.startup-title{display:flex;align-items:center;gap:9px;font-family:var(--ff-head);font-size:16px;font-weight:800;color:var(--ink)}
.startup-title i{color:var(--teal)}
.startup-close{width:30px;height:30px;border:1px solid var(--line);border-radius:var(--r-xs);background:var(--surface);color:var(--ink3);cursor:pointer;display:flex;align-items:center;justify-content:center}
.startup-close:hover{background:var(--red-pale);color:var(--red);border-color:rgba(184,50,50,.25)}
.startup-frame{width:100%;height:min(620px,80vh);border:0;background:#fff}
.startup-body{padding:16px;overflow:auto}
.startup-rate-grid{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:10px;margin-bottom:14px}
.startup-rate-card{border:1px solid var(--line);border-radius:var(--r-sm);background:var(--surface2);padding:12px}
.startup-rate-label{font-size:12px;font-weight:800;color:var(--ink3);text-transform:uppercase;letter-spacing:.5px}
.startup-rate-value{font-family:var(--ff-mono);font-size:20px;font-weight:900;color:var(--ink);margin-top:5px}
.startup-subtitle{font-size:13px;font-weight:900;color:var(--ink2);margin:2px 0 10px;display:flex;align-items:center;gap:7px}
.startup-summary{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.startup-pill{border:1px solid var(--line);background:var(--surface2);border-radius:var(--r-pill);padding:6px 12px;font-size:12px;font-weight:800;color:var(--ink2)}
.startup-pill.red{background:var(--red-pale);color:var(--red);border-color:rgba(184,50,50,.18)}
.startup-table-wrap{border:1px solid var(--line);border-radius:var(--r-sm);overflow:auto;max-height:430px}
.startup-table{width:100%;border-collapse:collapse;font-size:14px}
.startup-table th{position:sticky;top:0;background:var(--surface2);text-align:left;color:var(--ink3);font-size:12px;letter-spacing:.7px;text-transform:uppercase;padding:9px;border-bottom:1px solid var(--line)}
.startup-table td{padding:9px;border-bottom:1px solid var(--line);color:var(--ink2);vertical-align:top}
.startup-table tr:last-child td{border-bottom:0}
.startup-table .num{text-align:right;font-family:var(--ff-mono);white-space:nowrap}
.startup-empty{padding:34px 16px;text-align:center;color:var(--ink4);font-size:13px}
.startup-actions{display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--line);background:var(--surface2)}
.startup-btn{height:32px;border:1px solid var(--line2);border-radius:var(--r-xs);background:var(--surface);color:var(--ink2);font-size:12px;font-weight:800;padding:0 12px;cursor:pointer}
.startup-btn.primary{background:var(--teal);border-color:var(--teal);color:#fff}
.startup-btn:hover{box-shadow:var(--s2);transform:translateY(-1px)}
@media(max-width:760px){.startup-rate-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}}
.qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;flex:1}
.qa-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:13px 6px;border:1px solid var(--line);border-radius:var(--r-sm);background:var(--surface2);cursor:pointer;font-family:var(--ff-body);transition:all .2s var(--ez)}
.qa-btn:hover{transform:translateY(-2px);box-shadow:var(--s2)}
.qa-btn i{font-size:20px;transition:transform .2s var(--ez)}.qa-btn:hover i{transform:scale(1.2)}
.qa-btn span{font-size:13px;font-weight:700;color:var(--ink2);letter-spacing:.3px;font-family:var(--ff-head)}
.qa-teal i{color:var(--teal)}.qa-gold i{color:var(--gold)}.qa-green i{color:var(--green)}.qa-blue i{color:var(--blue)}.qa-amber i{color:var(--amber)}.qa-red i{color:var(--red)}

/* â”€â”€ MODERN BIG-FONT + ANIMATION OVERRIDES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.stat-val{font-size:52px;letter-spacing:-1px;background:linear-gradient(135deg,var(--ink) 0%,var(--ink2) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.stat-label{font-size:15px;letter-spacing:1.4px}
.stat-sub{font-size:15px;font-weight:600}
.rc-num{font-size:50px;letter-spacing:-1px;text-shadow:0 1px 0 rgba(0,0,0,0.04)}
.rc-lbl{font-size:14px;letter-spacing:1.6px}
.rc-unit{font-size:14px;font-weight:600}
.panel-title{font-size:22px}
.panel-title i{font-size:20px}
.ip-title{font-size:20px}
.ip-title i{font-size:18px}
.il-name{font-size:18px}
.il-sub{font-size:14px}
.il-amt{font-size:18px}
.qa-btn{padding:20px 8px;gap:10px}
.qa-btn i{font-size:30px}
.qa-btn span{font-size:15px;letter-spacing:.4px}
.ppill{font-size:14px;padding:7px 18px}
.ip-link{font-size:15px}

/* Card entry animation â€” staggered slide+fade */
@keyframes cardRise{from{opacity:0;transform:translateY(24px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.stat-card,.rate-card,.panel,.info-panel{animation:cardRise .55s var(--ez) both}
.stat-row .stat-card:nth-child(1){animation-delay:.05s}
.stat-row .stat-card:nth-child(2){animation-delay:.12s}
.stat-row .stat-card:nth-child(3){animation-delay:.19s}
.stat-row .stat-card:nth-child(4){animation-delay:.26s}
.rate-grid .rate-card:nth-child(1){animation-delay:.30s}
.rate-grid .rate-card:nth-child(2){animation-delay:.37s}
.rate-grid .rate-card:nth-child(3){animation-delay:.44s}
.rate-grid .rate-card:nth-child(4){animation-delay:.51s}
.analytics-row .panel:nth-child(1){animation-delay:.55s}
.analytics-row .panel:nth-child(2){animation-delay:.62s}
.bottom-row .info-panel:nth-child(1){animation-delay:.68s}
.bottom-row .info-panel:nth-child(2){animation-delay:.75s}
.bottom-row .info-panel:nth-child(3){animation-delay:.82s}

/* Stronger lift on hover */
.stat-card{padding:26px 28px}
.stat-card:hover{transform:translateY(-6px);box-shadow:0 20px 40px -10px rgba(0,0,0,.18)}
.rate-card{padding:22px 26px}
.rate-card:hover{transform:translateY(-4px);box-shadow:0 16px 32px -10px rgba(0,0,0,.15)}
.info-panel{padding:24px 26px}
.qa-btn:hover{transform:translateY(-3px) scale(1.02);box-shadow:0 12px 24px -8px rgba(0,0,0,.14)}

/* Shimmer sweep on stat progress bars */
@keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}
.stat-bar{position:relative;background-size:200% 100% !important;animation:barGrow 1.2s .6s var(--ez) both,shimmer 3s 1.8s linear infinite}
.sc-teal .stat-bar{background-image:linear-gradient(90deg,var(--teal) 0%,var(--teal2) 50%,var(--teal) 100%) !important}
.sc-gold .stat-bar{background-image:linear-gradient(90deg,var(--gold) 0%,var(--gold3) 50%,var(--gold) 100%) !important}
.sc-green .stat-bar{background-image:linear-gradient(90deg,var(--green) 0%,#3ab07a 50%,var(--green) 100%) !important}
.sc-amber .stat-bar{background-image:linear-gradient(90deg,var(--amber) 0%,#e8941a 50%,var(--amber) 100%) !important}

/* Pulsing ring on stat-icon */
@keyframes iconPulse{0%,100%{box-shadow:0 0 0 0 currentColor}50%{box-shadow:0 0 0 8px transparent}}
.stat-icon{width:56px;height:56px;font-size:23px;opacity:.95}
.stat-card:hover .stat-icon{animation:iconPulse 1.4s var(--ez) infinite;transform:scale(1.15) rotate(-6deg)}

/* Rate-card glow accent */
.rate-card{position:relative;overflow:hidden}
.rate-card::after{content:'';position:absolute;top:-50%;right:-30%;width:140px;height:140px;border-radius:50%;opacity:.08;filter:blur(20px);transition:opacity .3s var(--ez)}
.rg22::after{background:var(--gold)}.rg18::after{background:var(--amber)}.rgag::after{background:var(--ink2)}.rgth::after{background:var(--purple)}
.rate-card:hover::after{opacity:.18}

/* Number tick on hover */
@keyframes numberPop{0%{transform:scale(1)}50%{transform:scale(1.05)}100%{transform:scale(1)}}
.stat-card:hover .stat-val,.rate-card:hover .rc-num{animation:numberPop .4s var(--ez)}

/* Info-list rows fade in */
@keyframes rowSlide{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:translateX(0)}}
.info-list li{animation:rowSlide .4s var(--ez) both;transition:all .2s var(--ez)}
.info-list li:nth-child(1){animation-delay:.85s}
.info-list li:nth-child(2){animation-delay:.92s}
.info-list li:nth-child(3){animation-delay:.99s}
.info-list li:nth-child(4){animation-delay:1.06s}
.info-list li:nth-child(5){animation-delay:1.13s}
.info-list li:nth-child(6){animation-delay:1.20s}
.info-list li:nth-child(7){animation-delay:1.27s}
.info-list li:hover{transform:translateX(4px);border-color:var(--teal);box-shadow:0 6px 14px -6px rgba(0,0,0,.12)}

/* Larger panel headers */
.panel{padding:24px}
.panel-head{margin-bottom:18px}

/* Responsive adjust â€” keep big on small screens but capped */
@media(max-width:900px){
  .stat-val{font-size:32px}
  .rc-num{font-size:30px}
  .panel-title{font-size:17px}
  .ip-title{font-size:16px}
}
@media(max-width:600px){
  .stat-val{font-size:26px}
  .rc-num{font-size:24px}
  .panel-title{font-size:15px}
  .ip-title{font-size:14px}
  .qa-btn i{font-size:20px}
  .qa-btn span{font-size:11px}
}

@media print{.sidebar,.topbar,.menubar,.statusbar{display:none}.shell{grid-template-columns:1fr}body{overflow:visible}}

/* ── Icon-only sidebar when a module tab is open ── */
.shell.tab-open{grid-template-columns:62px minmax(0,1fr)}
.shell.tab-open .sidebar{overflow:visible}
.shell.tab-open .sidebar-brand .sb-title,
.shell.tab-open .sb-rates,
.shell.tab-open .sb-section-label,
.shell.tab-open .sb-uname,
.shell.tab-open .sb-urole{display:none}
.shell.tab-open .sidebar-brand{padding:12px 6px;justify-content:center}
.shell.tab-open .sb-gem{margin:0 auto}
.shell.tab-open .sb-nav{padding:6px 4px}
.shell.tab-open .sb-link{font-size:0 !important;padding:10px 0;justify-content:center;gap:0;overflow:visible;border-radius:6px}
.shell.tab-open .sb-link i{font-size:22px !important;width:auto !important;margin:0}
.shell.tab-open .sb-link:hover{padding-left:0}
.shell.tab-open .sb-footer{padding:8px 4px;justify-content:center}
.shell.tab-open .sb-avatar{margin:0 auto}
/* Tooltip on hover */
.shell.tab-open .sb-link{position:relative}
.shell.tab-open .sb-link:hover::after{content:attr(data-label);position:absolute;left:66px;top:50%;transform:translateY(-50%);background:#1a3a2a;color:#fff;padding:5px 12px;border-radius:6px;font-size:14px !important;white-space:nowrap;z-index:9999;pointer-events:none;box-shadow:0 4px 12px rgba(0,0,0,0.25)}

/* â”€â”€ RESPONSIVE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.mob-toggle{display:none;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid var(--line);border-radius:var(--r-xs);background:var(--surface2);cursor:pointer;font-size:14px;color:var(--ink2);flex-shrink:0;transition:all .18s}
.mob-toggle:hover{background:var(--teal-pale);color:var(--teal)}
.mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:90;animation:fadeIn .2s ease}
.mob-overlay.show{display:block}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}

/* â”€â”€ Large desktop (1600px+) â”€â”€ */
@media(min-width:1600px){
  .shell{grid-template-columns:250px 1fr}
  .sb-link{font-size:17px}
  .stat-row{grid-template-columns:repeat(4,1fr)}
  .rate-grid{grid-template-columns:repeat(4,1fr)}
  .home-screen{padding:22px 28px 28px}
  .stat-card{padding:20px 24px}
  .menu-item{font-size:17px;padding:0 14px}
  .dropdown{min-width:240px}
  .dd-item{font-size:16px;padding:8px 13px}
  .submenu{min-width:280px;padding:6px}
  .submenu .dd-item{font-size:16px;padding:9px 14px}
}

/* â”€â”€ Medium desktop (1200â€“1600px) â”€â”€ */
@media(min-width:1200px) and (max-width:1599px){
  .shell{grid-template-columns:250px 1fr}
  .stat-row{grid-template-columns:repeat(4,1fr)}
  .rate-grid{grid-template-columns:repeat(4,1fr)}
}

/* â”€â”€ Small desktop / large tablet (900â€“1199px) â€” compact dense layout â”€â”€ */
@media(min-width:900px) and (max-width:1199px){
  .shell{grid-template-columns:230px 1fr}
  .main{grid-template-rows:40px 32px minmax(0,1fr) 22px}
  .sidebar-brand{padding:10px 10px 9px}
  .sb-logo{gap:7px;margin-bottom:2px}
  .sb-gem{width:26px;height:26px;font-size:11px;border-radius:8px}
  .sb-title{font-size:13px;letter-spacing:.2px}
  .sb-rates{padding:7px 10px;gap:4px}
  .sbr-label{font-size:10px;letter-spacing:.9px;gap:4px}
  .sbr-val{font-size:13px}
  .sb-nav{padding:5px 6px}
  .sb-section-label{font-size:10px;letter-spacing:1.3px;padding:7px 6px 2px}
  .sb-link{font-size:15px;padding:7px 9px;gap:8px;border-radius:5px}
  .sb-link:hover{padding-left:12px}
  .sb-link i{width:20px;font-size:18px}
  .sb-footer{padding:7px 10px}
  .sb-avatar{width:24px;height:24px;font-size:9.5px}
  .sb-uname{font-size:13px}
  .sb-urole{font-size:11px}
  .stat-row{grid-template-columns:repeat(2,1fr)}
  .rate-grid{grid-template-columns:repeat(2,1fr)}
  .analytics-row{grid-template-columns:1fr}
  .bottom-row{grid-template-columns:1fr 1fr}
  .home-screen{padding:10px 12px 16px;gap:10px}
  .menubar{overflow-x:auto;-webkit-overflow-scrolling:touch;flex-wrap:nowrap;scrollbar-width:none;padding:0 10px}
  .menubar::-webkit-scrollbar{display:none}
  .menu-item{font-size:14px;padding:0 9px;margin:3px 1px;letter-spacing:.2px}
  .dropdown{min-width:200px;padding:3px;border-radius:8px}
  .dd-item{font-size:14px;padding:6px 10px;gap:6px}
  .dd-item:hover{padding-left:12px}
  .dd-left{gap:7px}
  .dd-left i{width:14px;font-size:13px}
  .dd-key{font-size:10px;padding:0 4px}
  .submenu{min-width:240px;padding:4px}
  .submenu .dd-item{font-size:14px;padding:7px 12px;gap:8px}
  .submenu .dd-left{gap:8px}
  .submenu .dd-left i{width:14px;font-size:13px}
  .submenu .dd-key{font-size:10px}
  .module-frame-path{max-width:220px}
  .topbar{padding:0 12px}
  .topbar-left{min-width:0;gap:8px}
  .topbar-right{gap:5px}
  .tb-icon-btn{width:28px;height:28px;font-size:11px}
  .page-title{font-size:13px}
  .page-title i{font-size:12px}
  .breadcrumb{font-size:10.5px;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .date-chip{font-size:9.5px;padding:4px 9px}
  .date-chip i{font-size:9px}
  .statusbar{font-size:9.5px}
  .sb-seg{padding:0 8px}
  .stat-card{padding:12px 13px}
  .stat-top{margin-bottom:7px}
  .stat-label{font-size:8.5px}
  .stat-icon{width:28px;height:28px;font-size:11px}
  .stat-val{font-size:20px;margin-bottom:3px}
  .stat-sub{font-size:9.5px}
  .rate-card{padding:10px 12px}
  .rc-num{font-size:19px}
  .panel{padding:13px}
  .panel-head{margin-bottom:9px}
  .panel-title{font-size:12px}
  .chart-wrap{height:200px}
  .info-panel{padding:13px;min-height:150px}
  .ip-head{margin-bottom:9px}
  .ip-title{font-size:12px}
  .info-list{gap:7px}
  .info-list li{padding:7px 9px}
  .il-name{font-size:11.5px}
  .il-amt{font-size:11px}
  .qa-grid{gap:5px}
  .qa-btn{padding:7px 4px}
  .qa-btn i{font-size:13px}
  .qa-btn span{font-size:8px}
  .qb-btn{font-size:10.5px;height:27px;padding:0 10px}
  .module-frame-head{height:32px;padding:0 10px;gap:7px}
  .module-frame-title{font-size:11.5px}
  .module-frame-open-tab,.module-frame-close{height:24px;padding:0 9px;font-size:10.5px}
}

/* â”€â”€ Tablet / mobile (â‰¤900px) â”€â”€ */
@media(max-width:900px){
  html,body{overflow:auto}
  .shell{grid-template-columns:1fr;height:auto;min-height:100vh}
  .main{height:auto;min-height:100vh}
  .sidebar{position:fixed;top:0;left:0;bottom:0;width:240px;z-index:95;transform:translateX(-100%);transition:transform .28s var(--ez);box-shadow:var(--s4)}
  .sidebar.mob-open{transform:translateX(0)}
  .mob-toggle{display:flex}
  .stat-row{grid-template-columns:repeat(2,1fr)}
  .rate-grid{grid-template-columns:repeat(2,1fr)}
  .analytics-row{grid-template-columns:1fr}
  .bottom-row{grid-template-columns:1fr}
  .menubar{overflow-x:auto;-webkit-overflow-scrolling:touch;flex-wrap:nowrap;scrollbar-width:none;padding:0 8px}
  .menubar::-webkit-scrollbar{display:none}
  .menu-item{font-size:11px;padding:0 8px;margin:3px 1px}
  .dropdown{min-width:180px;padding:3px}
  .dd-item{font-size:11px;padding:5px 8px;gap:6px}
  .dd-left{gap:7px}
  .dd-left i{width:12px;font-size:10px}
  .dd-key{font-size:8px}
  .submenu{min-width:215px;padding:4px}
  .submenu .dd-item{font-size:11.5px;padding:6px 10px;gap:8px}
  .submenu .dd-left{gap:8px}
  .submenu .dd-left i{width:13px;font-size:10px}
  .topbar{padding:0 12px}
  .topbar-left,.topbar-right{min-width:0}
  .breadcrumb{display:none}
  .module-frame-path{display:none}
  .home-screen{padding:14px 12px 20px;gap:11px}
  .statusbar{font-size:10px}
  .sb-seg{padding:0 8px}
  .stat-val{font-size:24px}
  .rc-num{font-size:22px}
  .panel-title{font-size:15px}
}

/* â”€â”€ Small mobile (â‰¤540px) â”€â”€ */
@media(max-width:540px){
  .stat-row{grid-template-columns:1fr}
  .rate-grid{grid-template-columns:1fr 1fr}
  .bottom-row{grid-template-columns:1fr}
  .stat-val{font-size:22px}
  .rc-num{font-size:20px}
  .panel-title{font-size:14px}
  .il-name{font-size:12.5px}
  .il-amt{font-size:12px}
  .qa-btn span{font-size:11px}
  .stat-card{padding:12px 14px}
  .menu-item{font-size:10.5px;padding:0 7px}
  .dropdown{min-width:170px;left:0;right:auto}
  .dd-item{font-size:10.5px;padding:4px 7px}
  .dd-key{display:none}
  .submenu{min-width:200px;padding:4px}
  .submenu .dd-item{font-size:11px;padding:5px 9px}
  .topbar{height:auto;min-height:42px;padding:6px 8px;gap:8px;align-items:flex-start}
  .topbar-left{flex:1;min-width:0}
  .topbar-right{gap:6px;flex-wrap:wrap;justify-content:flex-end}
  .page-title{font-size:12px;min-width:0}
  .page-title span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .topbar-right .tb-icon-btn:not(:last-child):not(:nth-last-child(2)){display:none}
  .date-chip{font-size:9px;padding:3px 8px}
  .home-screen{padding:10px 8px 16px;gap:10px}
  .main{grid-template-rows:auto 34px minmax(0,1fr) 22px}
  .quick-bar{padding:8px 10px;gap:5px}
  .qb-heading{width:100%;border-right:none;margin-right:0;padding-right:0}
  .qb-btn{flex:1 1 calc(50% - 6px);justify-content:center;min-width:130px}
  .module-frame-head{height:auto;min-height:36px;padding:6px 10px;gap:8px;flex-wrap:wrap}
  .module-frame-title{font-size:12px}
  .module-frame-open-tab,.module-frame-close{height:26px;padding:0 10px;font-size:11px}
  .statusbar{overflow-x:auto}
  .statusbar::-webkit-scrollbar{display:none}
}

/* â”€â”€ Very small (â‰¤380px) â”€â”€ */
@media(max-width:380px){
  .stat-row{grid-template-columns:1fr}
  .rate-grid{grid-template-columns:1fr}
  .sb-link{font-size:13px}
  .home-screen{padding:8px 6px 12px}
  .qa-grid{grid-template-columns:1fr}
  .qb-btn{flex:1 1 100%;min-width:0}
  .date-chip{width:100%;justify-content:center}
}
@media (max-width:768px) and (min-width:541px){
  .topbar{padding:6px 10px;gap:8px;height:auto;min-height:44px}
  .main{grid-template-rows:auto 34px minmax(0,1fr) 22px}
  .topbar-right{gap:6px;flex-wrap:wrap;justify-content:flex-end}
  .page-title{font-size:13px}
  .quick-bar{padding:9px 10px}
  .qb-btn{flex:1 1 calc(50% - 6px);justify-content:center}
  .module-frame-head{padding:6px 10px;flex-wrap:wrap;height:auto;min-height:36px}
}
@media (min-width:1200px) and (max-width:1366px), (min-width:1200px) and (max-height:768px){
  .shell{grid-template-columns:250px 1fr}
  .main{grid-template-rows:42px 34px 1fr 22px}
  .sidebar-brand{padding:14px 12px 12px}
  .sb-gem{width:32px;height:32px;font-size:14px}
  .sb-title{font-size:14px}
  .sb-rates{padding:10px 14px;gap:6px}
  .sbr-label{font-size:11px}
  .sbr-val{font-size:14px}
  .sb-nav{padding:9px}
  .sb-section-label{padding:9px 6px 3px;font-size:11px;letter-spacing:1.4px}
  .sb-link{padding:8px 10px;font-size:16px;gap:9px}
  .sb-link i{width:20px;font-size:18px}
  .sb-footer{padding:11px 14px}
  .topbar{padding:0 14px}
  .topbar-left{gap:10px}
  .page-title{font-size:16px}
  .breadcrumb{font-size:13px}
  .tb-icon-btn{width:30px;height:30px;font-size:13px}
  .date-chip{font-size:13px;padding:4px 11px}
  .menubar{padding:0 12px}
  .menu-item{padding:0 13px;font-size:16px;margin:4px 1px}
  .dropdown{min-width:235px;padding:4px}
  .dd-item{font-size:15px;padding:8px 12px;gap:10px}
  .dd-left{gap:10px}
  .dd-left i{width:16px;font-size:14px}
  .dd-key{font-size:12px;padding:2px 6px}
  .submenu{min-width:270px;padding:5px}
  .submenu .dd-item{font-size:15px;padding:9px 13px;gap:12px}
  .submenu .dd-left{gap:12px}
  .submenu .dd-left i{width:16px;font-size:12px}
  .home-screen{padding:10px 12px 14px;gap:10px}
  .quick-bar{padding:8px 10px;gap:5px}
  .qb-btn{height:27px;padding:0 11px;font-size:10.5px}
  .stat-row,.rate-grid,.analytics-row,.bottom-row{gap:10px}
  .stat-card{padding:13px 14px}
  .stat-top{margin-bottom:8px}
  .stat-label{font-size:8.5px}
  .stat-icon{width:30px;height:30px;font-size:12px}
  .stat-val{font-size:21px;margin-bottom:4px}
  .stat-sub{font-size:9.5px}
  .rate-card{padding:11px 12px}
  .rc-lbl{margin-bottom:6px}
  .rc-num{font-size:20px}
  .panel{padding:14px}
  .panel-head{margin-bottom:10px}
  .panel-title{font-size:12px}
  .chart-wrap{height:210px}
  .info-panel{padding:14px;min-height:160px}
  .ip-head{margin-bottom:10px}
  .ip-title{font-size:12px}
  .info-list{gap:8px}
  .info-list li{padding:8px 10px}
  .qa-grid{gap:6px}
  .qa-btn{padding:8px 5px}
  .qa-btn i{font-size:14px}
  .qa-btn span{font-size:8.5px}
  .module-frame-head{height:34px;padding:0 12px;gap:8px}
  .module-frame-title{font-size:12px}
  .module-frame-path{font-size:9px;max-width:260px;padding:2px 10px}
  .module-frame-open-tab,.module-frame-close{height:24px;padding:0 10px;font-size:11px}
  .statusbar{font-size:9px}
  .sb-seg{padding:0 10px}
}
</style>
</head>
<body class="{{ trim((!empty($companyThemeActive) ? 'company-secondary ' : '') . (!empty($savedDashboardTheme) && $savedDashboardTheme !== 'default' ? 'theme-' . $savedDashboardTheme : '')) }}">
<div class="mob-overlay" id="mobOverlay" onclick="toggleMobileSidebar()"></div>
<div class="shell">
<aside class="sidebar" id="mobileSidebar">
    <div class="sidebar-brand">
        <div class="sb-logo">
            <div class="sb-gem"><i class="fas fa-gem"></i></div>
            <div>
                <div class="sb-title">{{ $siteName }}</div>
                <div style="font-family:var(--ff-head);font-size:10px;font-weight:700;color:rgba(255,255,255,.5);letter-spacing:1.5px">{{ $currentDatabase }}</div>
            </div>
        </div>
    </div>
    <div class="sb-rates">
        <div class="sbr-row sbr-22"><span class="sbr-label"><span class="sbr-dot"></span>22K Gold</span><span class="sbr-val">&#8377;{{ number_format($dashboardData['goldRate22K'] ?? 0, 2) }}</span></div>
        <div class="sbr-row sbr-18"><span class="sbr-label"><span class="sbr-dot"></span>18K Gold</span><span class="sbr-val">&#8377;{{ number_format($dashboardData['goldRate18K'] ?? 0, 2) }}</span></div>
        <div class="sbr-row sbr-ag"><span class="sbr-label"><span class="sbr-dot"></span>Silver</span><span class="sbr-val">&#8377;{{ number_format($dashboardData['silverRate'] ?? 0, 2) }}</span></div>
    </div>
    <nav class="sb-nav">
        <div class="sb-section-label">Dashboard</div>
        <div class="sb-link active" data-label="Dashboard" onclick="goHome()"><i class="fas fa-home"></i> Dashboard</div>

        <div class="sb-section-label">Sales &amp; Purchase</div>
        <div class="sb-link" data-label="Quotation" onclick="openModule('sales-bill-quotation','Quotation')"><i class="fas fa-file-lines"></i> Quotation</div>
        <div class="sb-link" data-label="Sales Invoice" onclick="openModule('sales-bill','Sales Invoice')"><i class="fas fa-cart-shopping"></i> Sales</div>
        <div class="sb-link" data-label="Purchase Invoice" onclick="openModule('purchase-bill','Purchase Invoice')"><i class="fas fa-bag-shopping"></i> Purchase</div>
        <div class="sb-link" data-label="Sales Return" onclick="openModule('sales-return-bill','Sales Return')"><i class="fas fa-rotate-left"></i> Sales Return</div>
        <div class="sb-link" data-label="Purchase Return" onclick="openModule('purchase-return-bill','Purchase Return')"><i class="fas fa-rotate-right"></i> Purchase Return</div>

        <div class="sb-section-label">Operations</div>
        <div class="sb-link" data-label="Smith Entry" onclick="openModule('goldsmith-bill','Smith Entry')"><i class="fas fa-hammer"></i> Smith Entry</div>
        <div class="sb-link" data-label="Jewellery Entry" onclick="openModule('jewellery-bill','Jewellery Entry')"><i class="fas fa-ring"></i> Jewellery Entry</div>
        <div class="sb-link" data-label="Repair Slip" onclick="openModule('repair-slip-receive-customer','Repair Slip')"><i class="fas fa-receipt"></i> Repair Slip</div>
        <div class="sb-link" data-label="Repair Voucher" onclick="openModule('remake-repair-return','Repair Voucher')"><i class="fas fa-screwdriver-wrench"></i> Repair Voucher</div>
        <div class="sb-link" data-label="New Order" onclick="openModule('order-bill-new-order','New Order')"><i class="fas fa-file-circle-plus"></i> New Order</div>
        <div class="sb-link" data-label="Edit Order Entry" onclick="openModule('order-bill-edit-order-entry','Edit Order Entry')"><i class="fas fa-pen"></i> Edit Order</div>
        <div class="sb-link" data-label="Reprint Order" onclick="openModule('order-bill-reprint','Reprint Order')"><i class="fas fa-print"></i> Reprint Order</div>
        <div class="sb-link" data-label="Advance After" onclick="openModule('order-bill-advance-after','Advance After')"><i class="fas fa-money-bill-wave"></i> Advance After</div>
        <div class="sb-link" data-label="Order Sale" onclick="openModule('order-bill-order-sale','Order Sale')"><i class="fas fa-tag"></i> Order Sale</div>
        <div class="sb-link" data-label="Receipt" onclick="openModule('accounts-receipt','Receipt')"><i class="fas fa-hand-holding-dollar"></i> Receipt</div>
        <div class="sb-link" data-label="Payment" onclick="openModule('accounts-payment','Payment')"><i class="fas fa-money-bill-transfer"></i> Payment</div>
        <div class="sb-link" data-label="Journal Entry" onclick="openModule('accounts-journal','Journal Entry')"><i class="fas fa-book-journal-whills"></i> Journal Entry</div>
        <div class="sb-link" data-label="Scheme Collection" onclick="openModule('scheme-details-scheme-collection','Scheme Collection')"><i class="fas fa-piggy-bank"></i> Scheme Collection</div>
        <div class="sb-link" data-label="Barcode Entry" onclick="openModule('barcode-single-entry','Barcode Entry')"><i class="fas fa-barcode"></i> Barcode Entry</div>

        <div class="sb-section-label">Reports &amp; Ledger</div>
        <div class="sb-link" data-label="Stock List" onclick="openModule('stock-list-items','Stock List')"><i class="fas fa-boxes-stacked"></i> Stock List</div>
        <div class="sb-link" data-label="Stock Ledger" onclick="openModule('stock-period-item','Stock Ledger')"><i class="fas fa-book-open"></i> Stock Ledger</div>
        <div class="sb-link" data-label="Term Summary" onclick="openModule('term-summary','Term Summary')"><i class="fas fa-calendar-days"></i> Term Summary</div>
        <div class="sb-link" data-label="AC Ledger" onclick="openModule('accounts-reports-ac-ledger','AC Ledger')"><i class="fas fa-book"></i> AC Ledger</div>
        <div class="sb-link" data-label="Customer Balance" onclick="openModule('accounts-reports-ac-receivable-payable-summary','Customer Balance')"><i class="fas fa-scale-balanced"></i> Customer Balance</div>
        <div class="sb-link" data-label="Customer" onclick="openModule('customer','Customer')"><i class="fas fa-users"></i> Customer</div>
        <div class="sb-link" data-label="Cash Book" onclick="openModule('accounts-reports-cash-book','Cash Book')"><i class="fas fa-money-bill-wave"></i> Cash Book</div>
        <div class="sb-link" data-label="Daybook" onclick="openModule('day-book','Daybook')"><i class="fas fa-calendar-day"></i> Daybook</div>
    </nav>
    @php
        $dashboardPermissionSet = collect($userPermissions ?? [])
            ->map(fn ($permission) => strtoupper(trim((string) $permission)));
        $canAccessUserAccess = !($hasRestrictedMenuAccess ?? false)
            || $dashboardPermissionSet->contains('MDI_USER_ACCESS')
            || $dashboardPermissionSet->contains('ADMINBUTTON');
        $canManageUsers = !($hasRestrictedMenuAccess ?? false)
            || $dashboardPermissionSet->contains('MDI_ADMIN_USERS')
            || $dashboardPermissionSet->contains('ADMINBUTTON');
    @endphp
    <div class="sb-footer">
        <div class="sb-user" id="sbUser">
            <div class="sb-avatar"><i class="fas fa-user"></i></div>
            <div><div class="sb-uname">{{ strtoupper($userName) }}</div><div class="sb-urole">{{ $userLevel ?? 'MGR' }} &middot; PROAIMS</div></div>
            <i class="fas fa-ellipsis-v" style="margin-left:auto;color:rgba(255,255,255,.3);font-size:11px"></i>
            <div class="sb-user-menu" id="sbUserMenu">
                <a href="{{ url('/admin/users/'.($userCode ?? 'MGR').'/edit') . '?company_db=' . urlencode($currentDatabase ?? '') }}"><i class="fas fa-key"></i>Change Password</a>
                @if($canAccessUserAccess)
                <a href="{{ url('/user-access') . '?company_db=' . urlencode($currentDatabase ?? '') }}"><i class="fas fa-lock"></i>User Access</a>
                @endif
                @if($canManageUsers)
                <a href="{{ url('/admin/users') . '?company_db=' . urlencode($currentDatabase ?? '') }}"><i class="fas fa-users-cog"></i>User Management</a>
                @endif
                <a href="{{ url('/logout') . '?company_db=' . urlencode($currentDatabase ?? '') }}"><i class="fas fa-right-from-bracket"></i>Sign Out</a>
            </div>
        </div>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="mob-toggle" id="mobToggle" onclick="toggleMobileSidebar()"><i class="fas fa-bars"></i></div>
            <div class="page-title"><i class="fas fa-chart-line"></i> <span id="pageTitle">Dashboard</span></div>
            <div class="breadcrumb"><span>{{ $siteName }}</span><i class="fas fa-chevron-right" style="font-size:8px"></i><span id="pageBreadcrumb">Overview</span></div>
        </div>
        <div class="topbar-right">
            <div class="phone-lookup" id="phoneLookup">
                <div class="phone-lookup-box">
                    <i class="fas fa-search"></i>
                    <input id="phoneLookupInput" type="text" placeholder="Search phone / code / name">
                    <button id="phoneLookupClear" type="button" title="Clear"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="phone-lookup-results" id="phoneLookupResults"></div>
            </div>
            <div class="tb-icon-btn theme-toggle-btn" id="themeToggleBtn" title="WiFi theme switcher" onclick="handleThemeTap()"><i class="fas fa-wifi"></i></div>
            <div class="tb-icon-btn" title="AI Analytics" onclick="openModule('ai-insights','AI Analytics')"><i class="fas fa-robot"></i></div>
            <div class="tb-icon-btn" title="Day Summary" onclick="openModule('day-summary','Day Summary')"><i class="fas fa-chart-column"></i></div>
            <div class="tb-icon-btn" title="Settings" onclick="openModule('application-settings','Settings')"><i class="fas fa-gear"></i></div>
            <div class="date-chip"><i class="fas fa-calendar"></i><span id="topDate"></span></div>
        </div>
    </header>

    <nav class="menubar" id="menuBar"></nav>

    <div class="content">
        <div class="home-screen" id="homeScreen">
            @if(isset($dashboardAccessDenied) && $dashboardAccessDenied)
                <div class="access-denied-wrap">
                    <div class="access-denied-box">
                        <div class="access-denied-icon"><i class="fas fa-lock"></i></div>
                        <h2>Access Denied</h2>
                        <p>Dashboard permission is not enabled for this user. Please contact the administrator to allow dashboard access.</p>
                    </div>
                </div>
            @else
            @php
                $prevSales = $dashboardData['prevMonthSales'] ?? 0;
                $curSales  = $dashboardData['monthSales'] ?? 0;
            @endphp
            {{-- Quick Action bar removed - shortcuts available in sidebar & menubar --}}
            <div class="stat-row dash-row">
                <div class="stat-card sc-teal" data-requires-module="sales-bill"><div class="stat-top"><div class="stat-label">Today Sales</div><div class="stat-icon si-teal"><i class="fas fa-cart-shopping"></i></div></div><div class="stat-val">{{ number_format($dashboardData['todaySalesWeight'] ?? 0, 3) }} gm</div><div class="stat-foot"><span class="stat-sub">{{ (int)($dashboardData['todaySalesBills'] ?? 0) }} bills Â· Amt &#8377;{{ number_format($dashboardData['todaySales'] ?? 0, 0) }} Â· SR &#8377;{{ number_format($dashboardData['todaySalesReturn'] ?? 0, 0) }}</span><div class="stat-progress"><div class="stat-bar"></div></div></div></div>
                <div class="stat-card sc-gold" data-requires-module="purchase-bill"><div class="stat-top"><div class="stat-label">Today Purchase</div><div class="stat-icon si-gold"><i class="fas fa-bag-shopping"></i></div></div><div class="stat-val">{{ number_format($dashboardData['todayPurchaseWeight'] ?? 0, 3) }} gm</div><div class="stat-foot"><span class="stat-sub">{{ (int)($dashboardData['todayPurchaseBills'] ?? 0) }} bills Â· Amt &#8377;{{ number_format($dashboardData['todayPurchase'] ?? 0, 0) }}</span><div class="stat-progress"><div class="stat-bar"></div></div></div></div>
                <div class="stat-card sc-green" data-requires-module="sales-bill"><div class="stat-top"><div class="stat-label">Month Sales</div><div class="stat-icon si-green"><i class="fas fa-chart-line"></i></div></div><div class="stat-val">{{ number_format($dashboardData['monthSalesWeight'] ?? 0, 3) }} gm</div><div class="stat-foot"><span class="stat-sub">Amt &#8377;{{ number_format($dashboardData['monthSales'] ?? 0, 0) }} Â· SR &#8377;{{ number_format($dashboardData['monthSalesReturn'] ?? 0, 0) }}</span><div class="stat-progress"><div class="stat-bar"></div></div></div></div>
                <div class="stat-card sc-amber" data-requires-module="purchase-bill"><div class="stat-top"><div class="stat-label">Month Purchase</div><div class="stat-icon si-amber"><i class="fas fa-truck"></i></div></div><div class="stat-val">{{ number_format($dashboardData['monthPurchaseWeight'] ?? 0, 3) }} gm</div><div class="stat-foot"><span class="stat-sub">{{ (int)($dashboardData['monthPurchaseBills'] ?? 0) }} bills Â· Amt &#8377;{{ number_format($dashboardData['monthPurchase'] ?? 0, 0) }}</span><div class="stat-progress"><div class="stat-bar"></div></div></div></div>
            </div>
            <div class="rate-grid dash-row">
                <div class="rate-card rg22"><div class="rc-lbl"><i class="fas fa-star"></i>Gold 22K</div><div class="rc-num">&#8377;{{ number_format($dashboardData['goldRate22K'] ?? 0, 2) }}</div><div class="rc-unit">Per gram</div></div>
                <div class="rate-card rg18"><div class="rc-lbl"><i class="fas fa-star-half-stroke"></i>Gold 18K</div><div class="rc-num">&#8377;{{ number_format($dashboardData['goldRate18K'] ?? 0, 2) }}</div><div class="rc-unit">Per gram</div></div>
                <div class="rate-card rgag"><div class="rc-lbl"><i class="fas fa-moon"></i>Silver</div><div class="rc-num">&#8377;{{ number_format($dashboardData['silverRate'] ?? 0, 2) }}</div><div class="rc-unit">Per gram</div></div>
                <div class="rate-card rgth"><div class="rc-lbl"><i class="fas fa-gem"></i>TH Rate</div><div class="rc-num">&#8377;{{ number_format($dashboardData['thRate'] ?? 0, 2) }}</div><div class="rc-unit">Per gram</div></div>
            </div>
            <div class="analytics-row dash-row">
                <div class="panel"><div class="panel-head"><div class="panel-title"><i class="fas fa-chart-area"></i>Sales &amp; Purchase Trend</div><div class="panel-pills"><button class="ppill" id="btn7d" onclick="switchChart('7d')">7 Days</button><button class="ppill active" id="btn6m" onclick="switchChart('6m')">6 Months</button></div></div><div class="chart-wrap"><canvas id="trendChart"></canvas></div></div>
                <div class="panel"><div class="panel-head"><div class="panel-title"><i class="fas fa-gem"></i>Top Items</div></div><div class="chart-wrap" style="height:222px"><canvas id="topItemsChart"></canvas></div></div>
            </div>
            <div class="bottom-row dash-row">
                <div class="info-panel" data-requires-module="sales-bill">
                    <div class="ip-head"><div class="ip-title"><i class="fas fa-receipt"></i>Recent Sales</div><span class="ip-link" onclick="openModule('sales-bill','Sales Bill')">View all &rarr;</span></div>
                    @if(!empty($dashboardData['recentSales']))
                        <ul class="info-list">
                            @foreach(array_slice($dashboardData['recentSales'], 0, 7) as $rs)
                                <li>
                                    <span>
                                        <div class="il-name">{{ \Illuminate\Support\Str::limit(($rs['name'] ?? '') !== '' ? $rs['name'] : ($rs['code'] ?? 'Sale'), 20) }}</div>
                                        <div class="il-sub">{{ ($rs['docno'] ?? '-') !== '' ? $rs['docno'] : '-' }} @if(!empty($rs['date'])) &bull; {{ \Carbon\Carbon::parse($rs['date'])->format('d M') }} @endif</div>
                                    </span>
                                    <span class="il-amt">&#8377;{{ number_format($rs['amount'] ?? 0, 0) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="ip-empty"><i class="fas fa-receipt"></i>No recent sales</div>
                    @endif
                </div>
                <div class="info-panel">
                    <div class="ip-head"><div class="ip-title"><i class="fas fa-clock"></i>Pending Accounts</div><span class="ip-link" onclick="openModule('customer','Customer')">View all &rarr;</span></div>
                    @if(!empty($dashboardData['pendingAccounts']))
                        <ul class="info-list">
                            @foreach(array_slice($dashboardData['pendingAccounts'], 0, 7) as $pa)
                                <li>
                                    <span>
                                        <div class="il-name">{{ \Illuminate\Support\Str::limit(($pa['name'] ?? '') !== '' ? $pa['name'] : ($pa['code'] ?? 'Customer'), 20) }}</div>
                                        <div class="il-sub">Code: {{ $pa['code'] ?? '-' }}</div>
                                    </span>
                                    <span class="il-amt {{ ($pa['balance'] ?? 0) < 0 ? 'negative' : '' }}">&#8377;{{ number_format(abs($pa['balance'] ?? 0), 0) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="ip-empty"><i class="fas fa-hourglass-half"></i>No pending accounts</div>
                    @endif
                </div>
                <div class="info-panel"><div class="ip-head"><div class="ip-title"><i class="fas fa-bolt"></i>Quick Actions</div></div>
                    <div class="qa-grid">
                        <button class="qa-btn qa-teal" onclick="openModule('sales-bill','Sales Bill')"><i class="fas fa-plus-circle"></i><span>New Sale</span></button>
                        <button class="qa-btn qa-gold" onclick="openModule('purchase-bill','Purchase Bill')"><i class="fas fa-truck"></i><span>Purchase</span></button>
                        <button class="qa-btn qa-green" onclick="openModule('customer','Customer')"><i class="fas fa-users"></i><span>Customer</span></button>
                        <button class="qa-btn qa-blue" onclick="openModule('day-book','Day Book')"><i class="fas fa-book"></i><span>Day Book</span></button>
                        <button class="qa-btn qa-amber" onclick="openModule('ai-insights','AI Analytics')"><i class="fas fa-robot"></i><span>AI Analytics</span></button>
                        <button class="qa-btn qa-red" onclick="openModule('backup','Data Backup & Restore')"><i class="fas fa-database"></i><span>Data Backup & Restore</span></button>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="tab-bar" id="tabBar" style="display:none">
            <button class="tab-bar-home active" id="tabBarHome" type="button" title="Dashboard" onclick="goHome()"><i class="fas fa-home"></i></button>
            <button class="tab-bar-back" id="tabBarBack" type="button" title="Back" onclick="goBackFromTabBar()"><i class="fas fa-arrow-left"></i></button>
            <div class="tab-bar-tabs" id="tabBarTabs"></div>
        </div>
        <div class="tab-frames-container" id="tabFramesContainer"></div>

        {{-- Legacy module frame screen (hidden, kept for postMessage compat) --}}
        <div class="module-frame-screen" id="moduleFrameScreen" style="display:none">
            <div class="module-frame-head">
                <span class="module-frame-title" id="moduleFrameTitle">Module</span>
                <span class="module-frame-path" id="moduleFramePath"></span>
            </div>
            <iframe class="module-frame-iframe" id="moduleFrame" style="display:none"></iframe>
        </div>

        <div class="startup-overlay" id="startupRateOverlay" aria-hidden="true">
            <section class="startup-modal" role="dialog" aria-modal="true" aria-labelledby="startupRateTitle">
                <div class="startup-head">
                    <div class="startup-title" id="startupRateTitle"><i class="fas fa-coins"></i>Gold Rate Update</div>
                    <button class="startup-close" type="button" title="Close" onclick="closeStartupRatePopup(true)"><i class="fas fa-times"></i></button>
                </div>
                <iframe class="startup-frame" id="startupRateFrame" title="Gold Rate Update"></iframe>
            </section>
        </div>

        <div class="startup-overlay" id="startupDueOverlay" aria-hidden="true">
            <section class="startup-modal sm" role="dialog" aria-modal="true" aria-labelledby="startupDueTitle">
                <div class="startup-head">
                    <div class="startup-title" id="startupDueTitle"><i class="fas fa-coins"></i>Gold Rate &amp; Credit Details</div>
                    <button class="startup-close" type="button" title="Close" onclick="closeStartupDuePopup()"><i class="fas fa-times"></i></button>
                </div>
                <div class="startup-body" id="startupDueBody"></div>
                <div class="startup-actions">
                    <button class="startup-btn" type="button" onclick="openStartupRateUpdate()">Update Rates</button>
                    <button class="startup-btn" type="button" onclick="openCreditBillDetails()">Credit Details</button>
                    <button class="startup-btn primary" type="button" onclick="closeStartupDuePopup()">Done</button>
                </div>
            </section>
        </div>
    </div>

    <footer class="statusbar">
        <span class="sb-seg sb-online"><span class="sb-live"></span>&thinsp;Online</span>
        <span class="sb-seg"><i class="fas fa-user"></i><span class="sb-hi">{{ strtoupper($userName) }}</span>&thinsp;({{ $userLevel ?? 'MGR' }})</span>
        <span class="sb-seg"><i class="fas fa-building"></i>{{ $siteName }}</span>
        <span class="sb-seg"><i class="fas fa-calendar"></i><span id="statusDate">{{ date('d M Y') }}</span></span>
        <span class="sb-seg"><i class="fas fa-clock"></i><span id="statusTime"></span></span>
        <span class="sb-seg sb-right"><i class="fas fa-code-branch"></i>v2.0</span>
    </footer>
</div>
</div>

<script>
// Sidebar user menu toggle
document.getElementById('sbUser').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('sbUserMenu').classList.toggle('open');
});
document.addEventListener('click', function() { document.getElementById('sbUserMenu').classList.remove('open'); });

// Mobile sidebar toggle
function toggleMobileSidebar() {
    const sb  = document.getElementById('mobileSidebar');
    const ov  = document.getElementById('mobOverlay');
    const open = sb.classList.toggle('mob-open');
    ov.classList.toggle('show', open);
}

// Sidebar link highlighting
function goHome() {
    // Deactivate current tab (but don't close it)
    activeTabId = null;
    activeDashboardModuleId = null;
    // Hide all tab iframes
    document.querySelectorAll('.tab-iframe').forEach(function(f){ f.classList.remove('active'); });
    document.querySelectorAll('.tab-item').forEach(function(t){ t.classList.remove('active'); });
    // Show/hide tab bar based on whether tabs exist
    var tabBar = document.getElementById('tabBar');
    var tabHome = document.getElementById('tabBarHome');
    if (openTabs.length > 0) {
        tabBar.style.display = 'flex';
        if (tabHome) tabHome.classList.add('active');
    } else {
        tabBar.style.display = 'none';
    }
    // Hide frames container, show home — expand sidebar
    document.getElementById('tabFramesContainer').classList.remove('show');
    document.getElementById('homeScreen').style.display = 'flex';
    document.querySelector('.shell').classList.remove('tab-open');
    // Legacy
    document.getElementById('moduleFrameScreen').classList.remove('show-frame');
    document.getElementById('moduleFrameScreen').style.display = 'none';
    // Sidebar
    document.querySelectorAll('.sb-link').forEach(function(l){ l.classList.remove('active'); });
    document.querySelector('.sb-link').classList.add('active');
    document.getElementById('pageTitle').textContent = 'Dashboard';
    document.getElementById('pageBreadcrumb').textContent = 'Overview';
    setTopbarPrimaryAction('default');
}

function closeActiveModuleFrame() {
    if (activeTabId) {
        closeTab(activeTabId);
    } else {
        goHome();
    }
}

function goBackFromTabBar() {
    if (!activeTabId) {
        goHome();
        return;
    }

    var activeTab = null;
    for (var i = 0; i < openTabs.length; i++) {
        if (openTabs[i].id === activeTabId) {
            activeTab = openTabs[i];
            break;
        }
    }

    if (!activeTab) {
        goHome();
        return;
    }

    var moduleId = String(activeTab.moduleId || '').toLowerCase();
    var title = String(activeTab.title || '').toLowerCase();
    var isReportTab = moduleId.indexOf('report') !== -1 || moduleId.indexOf('ledger') !== -1 || title.indexOf('report') !== -1 || title.indexOf('ledger') !== -1;

    if (isReportTab && activeTab.iframe && activeTab.iframe.contentWindow) {
        try {
            var iframeHistory = activeTab.iframe.contentWindow.history;
            if (iframeHistory && iframeHistory.length > 1) {
                iframeHistory.back();
                return;
            }
        } catch (err) {
            // Cross-window history can be blocked; fall back to the dashboard.
        }
    }

    goHome();
}

function closeTab(tabId) {
    var idx = -1;
    for (var i = 0; i < openTabs.length; i++) {
        if (openTabs[i].id === tabId) { idx = i; break; }
    }
    if (idx === -1) return;
    var tab = openTabs[idx];
    var wasCompanySelect = tab.moduleId === 'company-select';
    // Remove iframe
    if (tab.iframe) {
        tab.iframe.onload = null;
        tab.iframe.src = 'about:blank';
        tab.iframe.parentNode && tab.iframe.parentNode.removeChild(tab.iframe);
    }
    // Remove tab element
    if (tab.tabEl) {
        tab.tabEl.parentNode && tab.tabEl.parentNode.removeChild(tab.tabEl);
    }
    // Remove from array
    openTabs.splice(idx, 1);
    // Company-select cleanup
    if (wasCompanySelect) {
        companySelectSecretUnlocked = false;
        themeTapCount = 0;
        if (typeof buildMenu === 'function') buildMenu();
    }
    // Switch to another tab or go home
    if (tabId === activeTabId) {
        if (openTabs.length > 0) {
            var nextIdx = Math.min(idx, openTabs.length - 1);
            switchToTab(openTabs[nextIdx].id);
        } else {
            activeTabId = null;
            goHome();
        }
    }
    // Update tab bar visibility
    if (openTabs.length === 0) {
        document.getElementById('tabBar').style.display = 'none';
    }
}

function switchToTab(tabId) {
    activeTabId = tabId;
    var tabBarHome = document.getElementById('tabBarHome');
    if (tabBarHome) tabBarHome.classList.remove('active');
    // Hide home screen, show frames container — collapse sidebar to icons
    document.getElementById('homeScreen').style.display = 'none';
    document.getElementById('tabFramesContainer').classList.add('show');
    document.getElementById('tabBar').style.display = 'flex';
    document.querySelector('.shell').classList.add('tab-open');
    // Deactivate all tabs/iframes, activate target
    document.querySelectorAll('.tab-iframe').forEach(function(f){ f.classList.remove('active'); });
    document.querySelectorAll('.tab-item').forEach(function(t){ t.classList.remove('active'); });
    for (var i = 0; i < openTabs.length; i++) {
        if (openTabs[i].id === tabId) {
            openTabs[i].iframe.classList.add('active');
            openTabs[i].tabEl.classList.add('active');
            activeDashboardModuleId = openTabs[i].moduleId;
            document.getElementById('pageTitle').innerHTML = '<i class="fas fa-cube" style="color:var(--teal);font-size:13px"></i> ' + openTabs[i].title;
            document.getElementById('pageBreadcrumb').textContent = openTabs[i].moduleId;
            setTopbarPrimaryAction(openTabs[i].moduleId === 'company-select' ? 'company-close' : 'default');
            break;
        }
    }
    // Sidebar highlighting
    document.querySelectorAll('.sb-link').forEach(function(l){ l.classList.remove('active'); });
}

function refreshTab(tabId) {
    for (var i = 0; i < openTabs.length; i++) {
        if (openTabs[i].id === tabId) {
            var iframe = openTabs[i].iframe;
            if (iframe && iframe.src && iframe.src !== 'about:blank') {
                iframe.src = iframe.src;
            }
            break;
        }
    }
}

function openActiveModuleFrameInNewTab() {
    if (!activeTabId) return;
    for (var i = 0; i < openTabs.length; i++) {
        if (openTabs[i].id === activeTabId) {
            var src = openTabs[i].iframe.src;
            if (src && src !== 'about:blank' && src.indexOf('data:text/html') !== 0) {
                window.open(src, '_blank', 'noopener');
            }
            break;
        }
    }
}

function wireIframeCloseFallback(frame) {
    if (!frame) {
        return;
    }
    frame.onload = function() {
        try {
            var doc = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
            if (!doc) {
                return;
            }
            var selectors = [
                '#btnClose',
                '#btnClose2',
                '#btnExit',
                '#exitBtn',
                '#closeBtn',
                '[data-action="exit"]',
                '[onclick*="closeFrame"]',
                '[onclick*="closeModule"]',
                '[onclick*="window.close"]'
            ];
            doc.querySelectorAll(selectors.join(',')).forEach(function(el) {
                if (el.dataset.goldappCloseBound === '1') {
                    return;
                }
                el.dataset.goldappCloseBound = '1';
                el.addEventListener('click', function() {
                    window.setTimeout(closeActiveModuleFrame, 0);
                });
            });
        } catch (err) {
            // Ignore cross-document or module-specific access issues.
        }
    };
}

// Clock
function tickClock() {
    var now = new Date();
    var t = now.toLocaleTimeString('en-IN', {hour:'2-digit',minute:'2-digit'});
    var d = now.toLocaleDateString('en-IN', {day:'2-digit',month:'short',year:'numeric'});
    var el = document.getElementById('statusTime'); if(el) el.textContent = t;
    var el2 = document.getElementById('topDate'); if(el2) el2.textContent = d + ' ' + t;
}
setInterval(tickClock, 1000);
tickClock();

// Override openInDashboardFrame to use tabbed layout
var _origOpenInFrame = null;
function openInDashboardFrameNew(moduleId, title, cfg) {
    // Check if module already open in a tab â€” switch to it
    for (var i = 0; i < openTabs.length; i++) {
        if (openTabs[i].moduleId === moduleId) {
            switchToTab(openTabs[i].id);
            return;
        }
    }
    // Check tab limit
    if (openTabs.length >= MAX_TABS) {
        alert('Maximum ' + MAX_TABS + ' tabs allowed. Please close a tab first.');
        return;
    }
    var targetUrl = withCompanyContext(cfg.url || ('app://module/' + moduleId));
    var newTabId = 'tab-' + (++tabIdCounter);

    // Create iframe
    var iframe = document.createElement('iframe');
    iframe.className = 'tab-iframe';
    iframe.id = 'tabIframe-' + newTabId;
    iframe.setAttribute('data-tab-id', newTabId);
    if (cfg.mode === 'iframe') {
        iframe.src = targetUrl;
    } else {
        var html = '<!doctype html><html><head><meta charset="utf-8"><title>' + title + '</title>' +
            '<style>body{font-family:Segoe UI,Tahoma,sans-serif;margin:0;padding:28px;background:#f4f7fb;color:#213548}' +
            '.card{max-width:760px;margin:0 auto;background:#fff;border:1px solid #d7e2ef;border-radius:10px;padding:18px}' +
            'h2{margin:0 0 8px;font-size:22px} p{margin:6px 0;font-size:14px}</style></head>' +
            '<body><div class="card"><h2>' + title + '</h2><p>Module key: <strong>' + moduleId + '</strong></p>' +
            '<p>This module page is not implemented yet.</p></div></body></html>';
        iframe.src = 'data:text/html;charset=utf-8,' + encodeURIComponent(html);
    }
    iframe.addEventListener('load', function() { injectThemeIntoIframe(iframe); });
    wireIframeCloseFallback(iframe);
    document.getElementById('tabFramesContainer').appendChild(iframe);

    // Create tab element
    var tabEl = document.createElement('div');
    tabEl.className = 'tab-item';
    tabEl.setAttribute('data-tab-id', newTabId);
    tabEl.innerHTML = '<span class="tab-item-label">' + title + '</span>' +
        '<button class="tab-item-refresh" title="Refresh tab"><i class="fas fa-rotate-right"></i></button>' +
        '<button class="tab-item-close" title="Close tab"><i class="fas fa-times"></i></button>';
    tabEl.querySelector('.tab-item-label').addEventListener('click', function() {
        switchToTab(newTabId);
    });
    tabEl.querySelector('.tab-item-refresh').addEventListener('click', function(e) {
        e.stopPropagation();
        refreshTab(newTabId);
    });
    tabEl.querySelector('.tab-item-close').addEventListener('click', function(e) {
        e.stopPropagation();
        closeTab(newTabId);
    });
    document.getElementById('tabBarTabs').appendChild(tabEl);

    // Store tab
    var tabObj = { id: newTabId, moduleId: moduleId, title: title, cfg: cfg, iframe: iframe, tabEl: tabEl };
    openTabs.push(tabObj);

    // Switch to the new tab
    switchToTab(newTabId);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@if(is_file(public_path('js/dropdown-autoclose.js')))
<script src="{{ asset('js/dropdown-autoclose.js') }}?v={{ @filemtime(public_path('js/dropdown-autoclose.js')) }}"></script>
@endif
<script>
/**
 * ============================================================
 * Dashboard Data from controller (Blade -> JS)
 * ============================================================
 */
const dashboardData = {!! json_encode($dashboardData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
let trendChartInstance = null;
let themeTapCount = 0;
let companySelectSecretUnlocked = false;
let activeDashboardModuleId = null;
var openTabs = [];
var activeTabId = null;
var tabIdCounter = 0;
var MAX_TABS = 30;
const COMPANY_THEME_ACTIVE = {!! !empty($companyThemeActive) ? 'true' : 'false' !!};
const SECONDARY_CLOSE_URL = @json(url('/company-select/close'));
const SAVED_DASHBOARD_THEME = @json($savedDashboardTheme ?? 'default');
const CURRENT_COMPANY_DB = @json($currentDatabase ?? '');
const SHOW_STARTUP_POPUPS = {!! !empty($showStartupPopups) ? 'true' : 'false' !!};
const DROPDOWN_AUTOCLOSE_URL = @json(is_file(public_path('js/dropdown-autoclose.js')) ? asset('js/dropdown-autoclose.js') . '?v=' . (@filemtime(public_path('js/dropdown-autoclose.js')) ?: time()) : '');
const TODAY_DUE_PENDING_ACCOUNTS = {!! json_encode($dashboardData['todayDuePendingAccounts'] ?? ['rows' => [], 'totals' => []], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
const CREDIT_BILL_SUMMARY = {!! json_encode($dashboardData['creditBillSummary'] ?? ['rows' => [], 'totals' => []], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};

function withCompanyContext(url) {
    if (!url) {
        return url;
    }

    var raw = String(url);
    if (/^(data:|app:|javascript:|mailto:|tel:)/i.test(raw)) {
        return raw;
    }

    try {
        var parsed = new URL(raw, window.location.origin);
        var currentPath = window.location.pathname || '';
        var dashboardIndex = currentPath.toLowerCase().lastIndexOf('/dashboard');
        var appBasePath = dashboardIndex > 0 ? currentPath.slice(0, dashboardIndex) : '';
        if (
            appBasePath &&
            parsed.origin === window.location.origin &&
            !parsed.pathname.toLowerCase().startsWith(appBasePath.toLowerCase() + '/') &&
            parsed.pathname.toLowerCase() !== appBasePath.toLowerCase()
        ) {
            parsed.pathname = appBasePath.replace(/\/+$/, '') + '/' + parsed.pathname.replace(/^\/+/, '');
        }
        if (CURRENT_COMPANY_DB && !parsed.searchParams.get('company_db')) {
            parsed.searchParams.set('company_db', CURRENT_COMPANY_DB);
        }
        return parsed.toString();
    } catch (error) {
        return raw;
    }
}

/**
 * â”€â”€ Global Currency / Country / Religion Config â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
 * Accessible from any iframe module via:  window.top.GOLDAPP_CFG
 * Keys: currency_symbol, currency_code, decimal_places, number_format,
 *       weight_unit, purity_system, date_format, calendar_type,
 *       tax_label, rtl, working_days, religion, country_code, exchange_rate
 * â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
 */
window.GOLDAPP_CFG = {!! json_encode($currencyConfig ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
// Listen for save events from the settings iframe so runtime config stays fresh
window.addEventListener('message', function(e) {
  if (e.data && e.data.type === 'goldapp:currency-config-updated') {
    Object.assign(window.GOLDAPP_CFG, e.data.config || {});
  }
  if (e.data && e.data.type === 'goldapp:set-theme' && !COMPANY_THEME_ACTIVE) {
    applyDashboardTheme(e.data.theme || 'default');
  }
});

/**
 * ============================================================
 * Module Config Map -- all URLs via Blade url() helper
 * ============================================================
 */
const moduleConfigMap = {
    'sales-bill':                        { mode: 'iframe', url: '{{ url("/sales-bill/bill") }}' },
    'sales-bill-quotation':              { mode: 'iframe', url: '{{ url("/sales-bill/bill?qtn=1") }}' },
    'sales-bill-edit':                   { mode: 'iframe', url: '{{ url("/sales-bill/edit-picker?module=sales-bill-edit&title=Sales%20Edit") }}' },
    'sales-bill-cancel':                 { mode: 'iframe', url: '{{ url("/sales-bill/cancel-picker?module=sales-bill-cancel&title=Sales%20Cancellation") }}' },
    'sales-bill-quotation-edit':         { mode: 'iframe', url: '{{ url("/sales-bill/edit-picker?module=sales-bill-quotation-edit&title=Quotation%20Edit&qtn=1") }}' },
    'sales-bill-quotation-cancel':       { mode: 'iframe', url: '{{ url("/sales-bill/cancel-picker?module=sales-bill-quotation-cancel&title=Quotation%20Cancellation&qtn=1") }}' },
    'sales-bill-reprint':                { mode: 'iframe', url: '{{ url("/sales-bill/reprint-picker?module=sales-bill-reprint&title=Sales%20Reprint") }}' },
    'sales-bill-confirmation':           { mode: 'iframe', url: '{{ url("/sales-bill-confirmation") }}' },
    'customer':                          { mode: 'iframe', url: '{{ url("/customer?type=C") }}' },
    'supplier':                          { mode: 'iframe', url: '{{ url("/customer?type=S") }}' },
    'staff-adding':                      { mode: 'iframe', url: '{{ url("/customer?type=F") }}' },
    'goldsmith-add':                     { mode: 'iframe', url: '{{ url("/customer?type=G") }}' },
    'refiner-add':                       { mode: 'iframe', url: '{{ url("/customer?type=R") }}' },
    'jewellery-add':                     { mode: 'iframe', url: '{{ url("/customer?type=J") }}' },
    'depositors-wgt':                    { mode: 'iframe', url: '{{ url("/customer?type=D") }}' },
    'depositers-wgt':                    { mode: 'iframe', url: '{{ url("/customer?type=D") }}' },
    'depositor-wgt':                     { mode: 'iframe', url: '{{ url("/customer?type=D") }}' },
    'accounts-master-account':           { mode: 'iframe', url: '{{ url("/accounts/master/account") }}' },
    'accounts-master-position-settings': { mode: 'iframe', url: '{{ url("/accounts/master/positions") }}' },
    'accounts-master-book-stock':        { mode: 'iframe', url: '{{ url("/accounts/master/book-stock") }}' },
    'accounts-master-op-stock-value-set':{ mode: 'iframe', url: '{{ url("/accounts/master/op-stock-value-set") }}' },
    'accounts-master-co-party-limit':    { mode: 'iframe', url: '{{ url("/accounts/master/co-party-limit") }}' },
    'sales-man':                         { mode: 'iframe', url: '{{ url("/accounts/master/sales-man") }}' },
    'accounts-receipt':                  { mode: 'iframe', url: '{{ url("/accounts/receipt") }}' },
    'accounts-payment':                  { mode: 'iframe', url: '{{ url("/accounts/payment") }}' },
    'accounts-edit-rcpt-pmnt-entry':     { mode: 'iframe', url: '{{ url("/accounts/edit-entry") }}' },
    'accounts-suspense-entry':           { mode: 'iframe', url: '{{ url("/accounts/suspense-entry") }}' },
    'accounts-journal':                  { mode: 'iframe', url: '{{ url("/accounts/journal") }}' },
    'accounts-cheque-clearance':         { mode: 'iframe', url: '{{ url("/accounts/pdc-collection?module=cheque-clearance") }}' },
    'accounts-discount-allocation':      { mode: 'iframe', url: '{{ url("/accounts/pdc-collection?module=discount-allocation") }}' },
    'accounts-daily-statement-entry':    { mode: 'iframe', url: '{{ url("/accounts/daily-statement") }}' },
    'profit-loss':                       { mode: 'iframe', url: '{{ url("/reports/financial-statements?tab=income") }}' },
    'balance-sheet':                     { mode: 'iframe', url: '{{ url("/reports/financial-statements?tab=balance") }}' },
    'trial-balance':                     { mode: 'iframe', url: '{{ url("/reports/financial-statements?tab=trial") }}' },
    'accounts-reports-profit-loss-upto-date':    { mode: 'iframe', url: '{{ url("/reports/financial-statements?tab=income") }}' },
    'accounts-reports-profit-loss-for-period':   { mode: 'iframe', url: '{{ url("/reports/financial-statements?tab=income") }}' },
    'accounts-reports-balance-sheet':            { mode: 'iframe', url: '{{ url("/reports/financial-statements?tab=balance") }}' },
    'accounts-reports-trial-balance-upto-date':  { mode: 'iframe', url: '{{ url("/reports/financial-statements?tab=trial") }}' },
    'accounts-reports-trial-balance-between-dates': { mode: 'iframe', url: '{{ url("/reports/financial-statements?tab=trial") }}' },
    'states-adding':                     { mode: 'iframe', url: '{{ url("/states-adding") }}' },
    'reorder-level-table':               { mode: 'iframe', url: '{{ url("/reorder") }}' },
    'stock-type':                        { mode: 'iframe', url: '{{ url("/stocktype") }}' },
    'party-mc-table':                    { mode: 'iframe', url: '{{ url("/partymctable") }}' },
    'mc-table':                          { mode: 'iframe', url: '{{ url("/mctable") }}' },
    'gift-table':                        { mode: 'iframe', url: '{{ url("/gift-table") }}' },
    'item-add-edit':                     { mode: 'iframe', url: '{{ url("/item-master") }}' },
    'groups':                            { mode: 'iframe', url: '{{ url("/groups") }}' },
    'sub-groups':                        { mode: 'iframe', url: '{{ url("/sub-groups") }}' },
    'purity-type':                       { mode: 'iframe', url: '{{ url("/purity-type") }}' },
    'item-temp':                         { mode: 'iframe', url: '{{ url("/item-temp") }}' },
    'other-items':                       { mode: 'iframe', url: '{{ url("/other-items") }}' },
    'counters':                          { mode: 'iframe', url: '{{ url("/counters") }}' },
    'wastage-table':                     { mode: 'iframe', url: '{{ url("/wastage-table") }}' },
    'bill-prefix':                       { mode: 'iframe', url: '{{ url("/bill-prefix") }}' },
    'repair-complaints':                 { mode: 'iframe', url: '{{ url("/repair-complaints") }}' },
    'point-card':                        { mode: 'iframe', url: '{{ url("/point-card") }}' },
    'denomination-master':               { mode: 'iframe', url: '{{ url("/denomination-master") }}' },
    'op-bill-creation-customers':        { mode: 'iframe', url: '{{ url("/op-bill-creation/customers") }}' },
    'barcode-single-entry':              { mode: 'iframe', url: '{{ url("/barcode-single-entry") }}' },
    'barcode-multi-entry':               { mode: 'iframe', url: '{{ url("/barcode-multi-entry") }}' },
    'rate-update':                       { mode: 'iframe', url: '{{ url("/rate/update") }}' },
    'gold-rate-update':                  { mode: 'iframe', url: '{{ url("/rate/update") }}' },
    'gold-rate-image-set':               { mode: 'iframe', url: '{{ url("/gold-rate-story") }}' },
    'crm-greetings-campaigns':           { mode: 'iframe', url: '{{ url("/customer-campaign") }}' },
    'phone-book':                        { mode: 'iframe', url: '{{ url("/phone-book") }}' },
    'reminder':                          { mode: 'iframe', url: '{{ url("/reminders") }}' },
    'opening-stock-entry':               { mode: 'iframe', url: '{{ url("/stock/opening-stock") }}' },
    'stock-ledger':                      { mode: 'iframe', url: '{{ url("/stock/ledger") }}' },
    'stock-list-items':                  { mode: 'iframe', url: '{{ url("/stock/list?module=stock-list-items&title=Stock%20List") }}' },
    'stock-period-item':                 { mode: 'iframe', url: '{{ url("/stock/period-ledger") }}' },
    'stock-period-group':                { mode: 'iframe', url: '{{ url("/stock/period-ledger?rpt_type=G") }}' },
    'stock-period-subgroup':             { mode: 'iframe', url: '{{ url("/stock/period-ledger?rpt_type=SG") }}' },
    'stock-item-history':                { mode: 'iframe', url: '{{ url("/stock/item-history") }}' },
    'models':                            { mode: 'iframe', url: '{{ url("/models?type=M") }}' },
    'company-select':                    { mode: 'iframe', url: '{{ url("/company-select") }}' },
    'change-password':                   { mode: 'iframe', url: '{{ url("/admin/users/" . $userCode . "/edit") }}' },
    'provide-access':                    { mode: 'iframe', url: '{{ url("/user-access") }}' },
    'users-history':                     { mode: 'iframe', url: '{{ url("/admin/users/" . $userCode . "/history") }}' },
    'day-book':                          { mode: 'iframe', url: '{{ url("/daybook") }}' },
    'sales-reports-sales-book':          { mode: 'iframe', url: '{{ url("/sales-book-report?module=sales-reports-sales-book&issr=S&view=book") }}' },
    'sales-reports-sales-return-book':   { mode: 'iframe', url: '{{ url("/sales-book-report?module=sales-reports-sales-return-book&issr=R&view=book") }}' },
    'tax-reports-sales-book':            { mode: 'iframe', url: '{{ url("/sales-book-report?module=tax-reports-sales-book&issr=S&view=tax") }}' },
    'remake-reports-remake-reports':      { mode: 'iframe', url: '{{ url("/remake-reports/remake-reports?module=remake-reports-remake-reports") }}' },
    'remake-reports-remake-pending':      { mode: 'iframe', url: '{{ url("/remake-reports/remake-pending?module=remake-reports-remake-pending") }}' },
    'remake-reports-remake-returns':      { mode: 'iframe', url: '{{ url("/remake-reports/remake-returns?module=remake-reports-remake-returns") }}' },
    'other-item-reports-sales-book':      { mode: 'iframe', url: '{{ url("/other-item-reports/sales-book?module=other-item-reports-sales-book") }}' },
    'other-item-reports-sales-register':  { mode: 'iframe', url: '{{ url("/other-item-reports/sales-register?module=other-item-reports-sales-register") }}' },
    'other-item-reports-purchase-book':   { mode: 'iframe', url: '{{ url("/other-item-reports/purchase-book?module=other-item-reports-purchase-book") }}' },
    'other-item-reports-purchase-register': { mode: 'iframe', url: '{{ url("/other-item-reports/purchase-register?module=other-item-reports-purchase-register") }}' },
    'other-item-reports-stock-ledger':    { mode: 'iframe', url: '{{ url("/other-item-reports/stock-ledger?module=other-item-reports-stock-ledger") }}' },
    'other-item-reports-stock-list':      { mode: 'iframe', url: '{{ url("/other-item-reports/stock-list?module=other-item-reports-stock-list") }}' },
    'sales-reports-monthly-sales':       { mode: 'iframe', url: '{{ url("/monthly-sales-report") }}' },
    'sales-reports-salesman-category':   { mode: 'iframe', url: '{{ url("/salesman-category-report") }}' },
    'sales-reports-sales-register':      { mode: 'iframe', url: '{{ url("/sales-register") }}' },
    'sales-reports-sales-check-list':    { mode: 'iframe', url: '{{ url("/sales-check-list") }}' },
    'sales-reports-sales-return-register': { mode: 'iframe', url: '{{ url("/sales-return-register") }}' },
    'sales-reports-net-sales-report':    { mode: 'iframe', url: '{{ url("/net-sales") }}' },
    'sales-reports-barcode-register':    { mode: 'iframe', url: '{{ url("/barcode-register") }}' },
    'sales-reports-barcode-profit-report': { mode: 'iframe', url: '{{ url("/barcode-profit") }}' },
    'sales-reports-marked-list':         { mode: 'iframe', url: '{{ url("/marked-list") }}' },
    'sales-reports-mc-profit-report':    { mode: 'iframe', url: '{{ url("/mc-profit") }}' },
    'sales-reports-va-check-list':       { mode: 'iframe', url: '{{ url("/va-check-list") }}' },
    'sales-reports-va-check-report':     { mode: 'iframe', url: '{{ url("/va-check-report") }}' },
    'sales-reports-delivery-status-report': { mode: 'iframe', url: '{{ url("/delivery-status") }}' },
    'sales-reports-point-card-points':   { mode: 'iframe', url: '{{ url("/point-card-points") }}' },
    'purchase-reports-purchase-check-list': { mode: 'iframe', url: '{{ url("/purchase-check-list") }}' },
    'purchase-reports-purchase-book':    { mode: 'iframe', url: '{{ url("/purchase-book") }}' },
    'purchase-reports-purchase-register': { mode: 'iframe', url: '{{ url("/purchase-register") }}' },
    'purchase-reports-purchase-return-register': { mode: 'iframe', url: '{{ url("/purchase-return-register") }}' },
    'sales-return-bill':                 { mode: 'iframe', url: '{{ url("/sales-return/bill") }}' },
    'sales-return-bill-edit':            { mode: 'iframe', url: '{{ url("/sales-return/picker/edit?title=Sales%20Return%20-%20Edit") }}' },
    'sales-return-bill-cancel':          { mode: 'iframe', url: '{{ url("/sales-return/picker/cancel?title=Sales%20Return%20-%20Cancel") }}' },
    'sales-return-bill-reprint':         { mode: 'iframe', url: '{{ url("/sales-return/picker/reprint?title=Sales%20Return%20-%20Reprint") }}' },
    'purchase-bill':                     { mode: 'iframe', url: '{{ url("/purchase-bill/bill") }}' },
    'purchase-bill-edit':                { mode: 'iframe', url: '{{ url("/purchase-bill/picker/edit?module=purchase-bill-edit&title=Edit%20Purchase%20Bill") }}' },
    'purchase-bill-cancel':              { mode: 'iframe', url: '{{ url("/purchase-bill/picker/cancel?module=purchase-bill-cancel&title=Purchase%20Cancellation") }}' },
    'purchase-bill-reprint':             { mode: 'iframe', url: '{{ url("/purchase-bill/picker/reprint?module=purchase-bill-reprint&title=Purchase%20Reprint") }}' },

    'diamonds-pres-stone-purchase-new':      { mode: 'iframe', url: '{{ url("/diamond-purchase/bill") }}' },
    'diamonds-pres-stone-purchase-edit':     { mode: 'iframe', url: '{{ url("/diamond-purchase/picker/edit?title=Diamond%20Purchase%20-%20Edit") }}' },
    'diamonds-pres-stone-purchase-cancel':   { mode: 'iframe', url: '{{ url("/diamond-purchase/picker/cancel?title=Diamond%20Purchase%20-%20Cancel") }}' },
    'diamonds-pres-stone-purchase-reprint':  { mode: 'iframe', url: '{{ url("/diamond-purchase/picker/reprint?title=Diamond%20Purchase%20-%20Reprint") }}' },
    'diamonds-pres-stone-purchase-return-new':     { mode: 'iframe', url: '{{ url("/diamond-purchase-return/bill") }}' },
    'diamonds-pres-stone-purchase-return-edit':    { mode: 'iframe', url: '{{ url("/diamond-purchase-return/picker/edit?title=Diamond%20Purchase%20Return%20-%20Edit") }}' },
    'diamonds-pres-stone-purchase-return-cancel':  { mode: 'iframe', url: '{{ url("/diamond-purchase-return/picker/cancel?title=Diamond%20Purchase%20Return%20-%20Cancel") }}' },
    'diamonds-pres-stone-purchase-return-reprint': { mode: 'iframe', url: '{{ url("/diamond-purchase-return/picker/reprint?title=Diamond%20Purchase%20Return%20-%20Reprint") }}' },

    'purchase-return-bill':              { mode: 'iframe', url: '{{ url("/purchase-return/bill") }}' },
    'purchase-return-bill-edit':         { mode: 'iframe', url: '{{ url("/purchase-return/picker/edit?module=purchase-return-bill-edit&title=Edit%20Purchase%20Return") }}' },
    'purchase-return-bill-cancel':       { mode: 'iframe', url: '{{ url("/purchase-return/picker/cancel?module=purchase-return-bill-cancel&title=Cancel%20Purchase%20Return") }}' },
    'purchase-return-bill-reprint':      { mode: 'iframe', url: '{{ url("/purchase-return/picker/reprint?module=purchase-return-bill-reprint&title=Purchase%20Return%20Reprint") }}' },

    'order-bill':                        { mode: 'iframe', url: '{{ url("/order-bill/bill") }}' },
    'order-bill-new-order':              { mode: 'iframe', url: '{{ url("/order-bill/bill") }}' },
    'order-bill-edit-order-entry':       { mode: 'iframe', url: '{{ url("/order-bill/edit-picker") }}' },
    'order-bill-cancel':                 { mode: 'iframe', url: '{{ url("/order-cancel") }}' },
    'order-bill-reprint':                { mode: 'iframe', url: '{{ url("/order-reprint") }}' },
    'order-bill-advance-after':          { mode: 'iframe', url: '{{ url("/order-advance-after/bill") }}' },
    'order-bill-order-sale':             { mode: 'iframe', url: '{{ url("/order-sale/bill") }}' },
    'order-bill-edit-order-sale':        { mode: 'iframe', url: '{{ url("/order-sale/edit") }}' },
    'order-bill-reprint-order-sale':     { mode: 'iframe', url: '{{ url("/order-reprint?mode=order-sale&title=Order%20Sale%20Reprint") }}' },
    'order-bill-sale-without-order':     { mode: 'iframe', url: '{{ url("/order-sale/without-order") }}' },
    'order-bill-order-rate-fix':         { mode: 'iframe', url: '{{ url("/order-rate-fix") }}' },
    'order-bill-update-from-jewelleries':{ mode: 'iframe', url: '{{ url("/order-update") }}' },
    'order-bill-block-unblock':          { mode: 'iframe', url: '{{ url("/order-block") }}' },

    // Order Reports
    'order-reports-order-entries':       { mode: 'iframe', url: '{{ url("/order-entry-report") }}' },
    'order-reports-pending-register':    { mode: 'iframe', url: '{{ url("/order-pending-register") }}' },
    'order-reports-order-enquiry':       { mode: 'iframe', url: '{{ url("/order-enquiry") }}' },
    'order-reports-pending-details':     { mode: 'iframe', url: '{{ url("/order-pending-details") }}' },
    'order-reports-order-returns':       { mode: 'iframe', url: '{{ url("/order-returns") }}' },
    'order-reports-order-advance-report':{ mode: 'iframe', url: '{{ url("/order-advance-report") }}' },
    'order-reports-order-sale-prof-analysis':{ mode: 'iframe', url: '{{ url("/order-profit-analysis") }}' },
    'order-reports-sample-stock-register':{ mode: 'iframe', url: '{{ url("/order-sample-stock") }}' },
    'order-reports-order-nos-list':       { mode: 'iframe', url: '{{ url("/order-nos-list") }}' },
    'order-reports-order-process':        { mode: 'iframe', url: '{{ url("/order-process-report") }}' },
    'order-reports-send-mail-to-ho':     { mode: 'iframe', url: '{{ url("/order-send-mail") }}' },

    // Barcode Reports
    'barcode-reports-barcode-list':      { mode: 'iframe', url: '{{ url("/barcode-list") }}' },
    'barcode-reports-stock-verification-report': { mode: 'iframe', url: '{{ url("/barcode-stock-verify") }}' },
    'barcode-reports-barcode-samt-list':  { mode: 'iframe', url: '{{ url("/barcode-samt-list") }}' },
    'barcode-reports-barcode-history':   { mode: 'iframe', url: '{{ url("/barcode-history") }}' },
    'barcode-reports-barcode-stock-list': { mode: 'iframe', url: '{{ url("/barcode-stock-list") }}' },
    'barcode-reports-barcode-entry-comparison': { mode: 'iframe', url: '{{ url("/barcode-entry-comparison") }}' },
    'barcode-reports-diamond-pres-stone-stock-list': { mode: 'iframe', url: '{{ url("/diamond-stone-stock") }}' },

    // Tools
    'backup':                            { mode: 'iframe', url: '{{ url("/backup") }}' },
    'local-backup':                      { mode: 'iframe', url: '{{ url("/backup?mode=local") }}' },
    'year-end-account-close':            { mode: 'iframe', url: '{{ url("/year-end-account-close") }}' },
    'task-reminders':                    { mode: 'iframe', url: '{{ url("/reminders") }}' },

    // Kuri / Scheme Details
    'kuri-details-enter-customers':      { mode: 'iframe', url: '{{ url("/kuri-details?mode=kuri") }}' },
    'scheme-details-customer-registration':{ mode: 'iframe', url: '{{ url("/kuri-details?mode=scheme") }}' },
    'kuri-details-kuri-collection':      { mode: 'iframe', url: '{{ url("/kuri-collection?mode=kuri") }}' },
    'scheme-details-scheme-collection':  { mode: 'iframe', url: '{{ url("/kuri-collection?mode=scheme") }}' },
    'scheme-details-pass-book-print':    { mode: 'iframe', url: '{{ url("/passbook-print?mode=scheme") }}' },
    'scheme-type-master':                { mode: 'iframe', url: '{{ url("/kuri-type-master") }}' },
    'kuri-details-interest-posting':     { mode: 'iframe', url: '{{ url("/kuri-int-post?mode=kuri") }}' },
    'scheme-details-interest-posting':   { mode: 'iframe', url: '{{ url("/kuri-int-post?mode=scheme") }}' },
    'kuri-details-kuri-finish':          { mode: 'iframe', url: '{{ url("/kuri-finish/close-scheme?mode=kuri") }}' },
    'scheme-details-scheme-finished':    { mode: 'iframe', url: '{{ url("/kuri-finish/close-scheme?mode=scheme") }}' },
    'kuri-details-kuri-draw':            { mode: 'iframe', url: '{{ url("/kuri-finish/draw?mode=kuri") }}' },
    'scheme-details-scheme-part-refund': { mode: 'iframe', url: '{{ url("/kuri-finish/refund?mode=scheme") }}' },

    // Refinery Bill
    'refinery-bill-new-entry':           { mode: 'iframe', url: '{{ url("/refinery-bill/bill") }}' },
    'refinery-bill-all-in-one':          { mode: 'iframe', url: '{{ url("/refinery-bill/all-in-one") }}' },
    'refinery-bill-edit':                { mode: 'iframe', url: '{{ url("/refinery-bill/picker/edit?title=Refinery%20Bill%20-%20Edit") }}' },
    'refinery-bill-cancel':              { mode: 'iframe', url: '{{ url("/refinery-bill/picker/cancel?title=Refinery%20Bill%20-%20Cancel") }}' },
    'refinery-bill-returns':             { mode: 'iframe', url: '{{ url("/refinery-return/bill") }}' },
    'refinery-bill-returns-edit':        { mode: 'iframe', url: '{{ url("/refinery-return/edit") }}' },

    // Refinery Reports
    'refinery-reports-entry-report':           { mode: 'iframe', url: '{{ url("/refinery-reports/entry-report?title=Refinery%20Entry%20Report&module=refinery-reports-entry-report") }}' },
    'refinery-reports-refiners-summary':       { mode: 'iframe', url: '{{ url("/refinery-reports/refiners-summary?title=Refiner%27s%20Summary&module=refinery-reports-refiners-summary") }}' },
    'refinery-reports-analysis':               { mode: 'iframe', url: '{{ url("/refinery-reports/analysis?title=Refinery%20Analysis&module=refinery-reports-analysis") }}' },
    'refinery-reports-ac-summary':             { mode: 'iframe', url: '{{ url("/refinery-reports/ac-summary?title=Refinery%20A%2Fc%20Summary&module=refinery-reports-ac-summary") }}' },
    'refinery-reports-less-comparison-report': { mode: 'iframe', url: '{{ url("/refinery-reports/less-comparison?title=Less%20Comparison%20Report&module=refinery-reports-less-comparison-report") }}' },

    // Party Wgt Deposit Reports
    'party-wgt-deposit-reports-transactions':    { mode: 'iframe', url: '{{ url("/deposit-reports/transactions?title=Party%20Wgt%20Deposit%20-%20Transactions&module=party-wgt-deposit-reports-transactions") }}' },
    'party-wgt-deposit-reports-depositer-ledger':{ mode: 'iframe', url: '{{ url("/deposit-reports/depositer-ledger?title=Depositer%20Ledger&module=party-wgt-deposit-reports-depositer-ledger") }}' },
    'party-wgt-deposit-reports-wgt-amt-summary': { mode: 'iframe', url: '{{ url("/deposit-reports/wgt-amt-summary?title=Wgt%2FAmt%20Summary&module=party-wgt-deposit-reports-wgt-amt-summary") }}' },

    // Partners Deposit(Wgt) Reports
    'partners-deposit-reports-deposit-book':          { mode: 'iframe', url: '{{ url("/deposit-reports/deposit-book?title=Deposit%20Book&module=partners-deposit-reports-deposit-book") }}' },
    'partners-deposit-reports-wgt-balance-summary':   { mode: 'iframe', url: '{{ url("/deposit-reports/wgt-balance-summary?title=Wgt%20Balance%20Summary&module=partners-deposit-reports-wgt-balance-summary") }}' },

    // Goldsmith / Jewellery / Deposit / Remake transaction screens
    'goldsmith-bill':                    { mode: 'iframe', url: '{{ url("/goldsmith-transactions?module=goldsmith-bill&title=Goldsmith%20Bill%20-%20Transaction&type=G&mode=transaction") }}' },
    'goldsmith-bill-edit':               { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/edit?module=goldsmith-bill-edit&title=Goldsmith%20Bill%20-%20Edit&type=G") }}' },
    'goldsmith-bill-cancel':             { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/cancel?module=goldsmith-bill-cancel&title=Goldsmith%20Bill%20-%20Cancel&type=G") }}' },
    'goldsmith-bill-reprint':            { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/reprint?module=goldsmith-bill-reprint&title=Goldsmith%20Bill%20-%20Reprint&type=G") }}' },
    'goldsmith-bill-new-work-note':      { mode: 'iframe', url: '{{ url("/goldsmith-transactions?module=goldsmith-bill-new-work-note&title=Goldsmith%20Bill%20-%20New%20Work%20Note&type=G&mode=new-work-note") }}' },

    'jewellery-bill':                    { mode: 'iframe', url: '{{ url("/goldsmith-transactions?module=jewellery-bill&title=Jewellery%20Bill%20-%20Transaction&type=J&mode=transaction") }}' },
    'jewellery-bill-edit':               { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/edit?module=jewellery-bill-edit&title=Jewellery%20Bill%20-%20Edit&type=J") }}' },
    'jewellery-bill-cancel':             { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/cancel?module=jewellery-bill-cancel&title=Jewellery%20Bill%20-%20Cancel&type=J") }}' },
    'jewellery-bill-reprint':            { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/reprint?module=jewellery-bill-reprint&title=Jewellery%20Bill%20-%20Reprint&type=J") }}' },
    'jewellery-bill-update-from-ho':     { mode: 'iframe', url: '{{ url("/goldsmith-transactions?module=jewellery-bill-update-from-ho&title=Jewellery%20Bill%20-%20Update%20from%20HO&type=J&mode=update-from-ho") }}' },

    'party-wgt-deposit-bill':            { mode: 'iframe', url: '{{ url("/goldsmith-transactions?module=party-wgt-deposit-bill&title=Party%20Wgt%20Deposit%20Bill%20-%20Transaction&type=C&mode=transaction") }}' },
    'party-wgt-deposit-bill-edit':       { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/edit?module=party-wgt-deposit-bill-edit&title=Party%20Wgt%20Deposit%20Bill%20-%20Edit&type=C") }}' },
    'party-wgt-deposit-bill-cancel':     { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/cancel?module=party-wgt-deposit-bill-cancel&title=Party%20Wgt%20Deposit%20Bill%20-%20Cancel&type=C") }}' },
    'party-wgt-deposit-bill-reprint':    { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/reprint?module=party-wgt-deposit-bill-reprint&title=Party%20Wgt%20Deposit%20Bill%20-%20Reprint&type=C") }}' },
    'party-wgt-deposit-bill-interest-posting': { mode: 'iframe', url: '{{ url("/goldsmith-transactions?module=party-wgt-deposit-bill-interest-posting&title=Party%20Wgt%20Deposit%20Bill%20-%20Interest%20Posting&type=C&mode=interest-posting") }}' },
    'item-stock-adjustment-stock-transfer': { mode: 'iframe', url: '{{ url("/item-stock-adjustment?module=item-stock-adjustment-stock-transfer&title=Stock%20Transfer") }}' },
    'item-stock-adjustment-stock-transfer-barcode': { mode: 'iframe', url: '{{ url("/item-stock-adjustment?module=item-stock-adjustment-stock-transfer-barcode&title=Stock%20Transfer%20(Barcode)&barcode=1") }}' },
    'item-stock-adjustment-stock-transfer-multi-entry': { mode: 'iframe', url: '{{ url("/item-stock-adjustment-multi?module=item-stock-adjustment-stock-transfer-multi-entry&title=Stock%20Transfer%20Multi%20Entry") }}' },
    'item-stock-adjustment-stock-add-less': { mode: 'iframe', url: '{{ url("/item-stock-add-less?module=item-stock-adjustment-stock-add-less&title=Stock%20Add%20-%20Less") }}' },
    'item-stock-adjustment-stock-add-less-edit': { mode: 'iframe', url: '{{ url("/item-stock-add-less?module=item-stock-adjustment-stock-add-less-edit&title=Stock%20Add%20-%20Less%20Edit") }}' },
    'item-reports-item-add-less-report': { mode: 'iframe', url: '{{ url("/item-add-less-report?module=item-reports-item-add-less-report&title=Item%20Add%20Less%20Report") }}' },
    'item-stock-adjustment-stock-adjustment': { mode: 'iframe', url: '{{ url("/item-stock-adjustment-compare?module=item-stock-adjustment-stock-adjustment&title=Stock%20Adjustment") }}' },
    'item-stock-adjustment-edit': { mode: 'iframe', url: '{{ url("/item-stock-adjustment-edit-cancel?module=item-stock-adjustment-edit&title=Adjustment%20Edit") }}' },
    'item-stock-adjustment-cancel': { mode: 'iframe', url: '{{ url("/item-stock-adjustment-edit-cancel?module=item-stock-adjustment-cancel&title=Adjustment%20Cancel") }}' },
    'item-reports-item-adjustment': { mode: 'iframe', url: '{{ url("/item-adjustment-report?module=item-reports-item-adjustment&title=Item%20Adjustment") }}' },
    'item-reports-item-movement':   { mode: 'iframe', url: '{{ url("/item-movement?module=item-reports-item-movement&title=Item%20Movement") }}' },
    'item-reports-itemwise-profit': { mode: 'iframe', url: '{{ url("/itemwise-profit?module=item-reports-itemwise-profit&title=Itemwise%20Profit") }}' },
    'item-reports-reorder-level':   { mode: 'iframe', url: '{{ url("/reorder?module=item-reports-reorder-level&title=Reorder%20Level") }}' },
    'item-reports-item-rate-report': { mode: 'iframe', url: '{{ url("/item-rate-report?module=item-reports-item-rate-report&title=Item%20Rate%20Report") }}' },
    'item-reports-cost-list':       { mode: 'iframe', url: '{{ url("/cost-list?module=item-reports-cost-list&title=Cost%20List") }}' },
    'item-reports-rate-history':    { mode: 'iframe', url: '{{ url("/rate-history?module=item-reports-rate-history&title=Rate%20History") }}' },
    'item-reports-model-transfer-report': { mode: 'iframe', url: '{{ url("/model-transfer-report?module=item-reports-model-transfer-report&title=Model%20Transfer%20Report") }}' },
    'item-reports-stone-trans-analysis': { mode: 'iframe', url: '{{ url("/stone-trans-analysis?module=item-reports-stone-trans-analysis&title=Stone%20Trans%20Analysis") }}' },
    'item-reports-trans-ra-report': { mode: 'iframe', url: '{{ url("/trans-ra-report?module=item-reports-trans-ra-report&title=Trans%20RA%20Report") }}' },
    'item-reports-item-stock-party-wgt-report': { mode: 'iframe', url: '{{ url("/item-stock-party-wgt-report?module=item-reports-item-stock-party-wgt-report&title=Item%20Stock%2C%20Party%20Wgt%20Report") }}' },
    'item-reports-stock-reg':       { mode: 'iframe', url: '{{ url("/stock-register?module=item-reports-stock-reg&title=Stock%20Register") }}' },

    'partners-deposit-wgt-new':          { mode: 'iframe', url: '{{ url("/wgt-rcpt-pmnt?module=partners-deposit-wgt-new&title=Deposit%20Wgt%20Rcpt/Pmnt") }}' },
    'partners-deposit-wgt-edit':         { mode: 'iframe', url: '{{ url("/wgt-rcpt-pmnt?module=partners-deposit-wgt-edit&title=Deposit%20Wgt%20Rcpt/Pmnt%20-%20Edit") }}' },
    'partners-deposit-wgt-cancel':       { mode: 'iframe', url: '{{ url("/wgt-rcpt-pmnt?module=partners-deposit-wgt-cancel&title=Deposit%20Wgt%20Rcpt/Pmnt%20-%20Cancel") }}' },
    'partners-deposit-wgt-reprint':      { mode: 'iframe', url: '{{ url("/wgt-rcpt-pmnt?module=partners-deposit-wgt-reprint&title=Deposit%20Wgt%20Rcpt/Pmnt%20-%20Reprint") }}' },
    'partners-deposit-wgt-intrest-posting': { mode: 'iframe', url: '{{ url("/depositors-int-post?module=partners-deposit-wgt-intrest-posting&title=Depositors%20Interest%20Posting") }}' },
    'partners-deposit-wgt-op-weight-entry': { mode: 'iframe', url: '{{ url("/party-op-weight?module=partners-deposit-wgt-op-weight-entry&title=Party%20Op.%20Weight%20Entry") }}' },

    'other-items-trans-sales-new':      { mode: 'iframe', url: '{{ url("/other-item-trans?sp=S&mode=new") }}' },
    'other-items-trans-sales-edit':     { mode: 'iframe', url: '{{ url("/other-item-trans/picker?sp=S&mode=edit") }}' },
    'other-items-trans-sales-cancel':   { mode: 'iframe', url: '{{ url("/other-item-trans/picker?sp=S&mode=cancel") }}' },
    'other-items-trans-sales-reprint':  { mode: 'iframe', url: '{{ url("/other-item-trans/picker?sp=S&mode=reprint") }}' },
    'other-items-trans-purchase-new':      { mode: 'iframe', url: '{{ url("/other-item-trans?sp=P&mode=new") }}' },
    'other-items-trans-purchase-edit':     { mode: 'iframe', url: '{{ url("/other-item-trans/picker?sp=P&mode=edit") }}' },
    'other-items-trans-purchase-cancel':   { mode: 'iframe', url: '{{ url("/other-item-trans/picker?sp=P&mode=cancel") }}' },
    'other-items-trans-purchase-reprint':  { mode: 'iframe', url: '{{ url("/other-item-trans/picker?sp=P&mode=reprint") }}' },

    'stock-suspense-entry-new-issue':   { mode: 'iframe', url: '{{ url("/stock-suspense-entry?gr=G") }}' },
    'stock-suspense-entry-new-receipt': { mode: 'iframe', url: '{{ url("/stock-suspense-entry?gr=R") }}' },
    'stock-suspense-entry-cancel':      { mode: 'iframe', url: '{{ url("/stock-suspense-entry/picker?mode=cancel") }}' },
    'stock-suspense-entry-reprint':     { mode: 'iframe', url: '{{ url("/stock-suspense-entry/picker?mode=reprint") }}' },

    'purity-testing-new':      { mode: 'iframe', url: '{{ url("/purity-testing?mode=new") }}' },
    'purity-testing-edit':     { mode: 'iframe', url: '{{ url("/purity-testing/picker?mode=edit") }}' },
    'purity-testing-cancel':   { mode: 'iframe', url: '{{ url("/purity-testing/picker?mode=cancel") }}' },
    'purity-testing-reprint':  { mode: 'iframe', url: '{{ url("/purity-testing/picker?mode=reprint") }}' },

    'purity-certificate':  { mode: 'iframe', url: '{{ url("/purity-certificate") }}' },

    'ruff-work':  { mode: 'iframe', url: '{{ url("/ruff-work") }}' },

    'remake-rcpt-memo-to-party':         { mode: 'iframe', url: '{{ url("/remake-rcpt-memo-to-party/new") }}' },
    'remake-rcpt-memo-to-party-edit':    { mode: 'iframe', url: '{{ url("/remake-rcpt-memo-to-party/edit") }}' },
    'remake-rcpt-memo-to-party-cancel':  { mode: 'iframe', url: '{{ url("/remake-rcpt-memo-to-party/cancel") }}' },
    'remake-rcpt-memo-to-party-reprint': { mode: 'iframe', url: '{{ url("/remake-rcpt-memo-to-party/reprint") }}' },

    'repair-slip-receive-customer':         { mode: 'iframe', url: '{{ url("/remake-rcpt-memo-to-party/new?module=repair-slip-receive-customer&title=Repair%20Slip%20-%20Receive%20from%20Customer") }}' },
    'repair-slip-receive-customer-edit':    { mode: 'iframe', url: '{{ url("/remake-rcpt-memo-to-party/edit?module=repair-slip-receive-customer-edit&title=Repair%20Slip%20-%20Receive%20from%20Customer%20Edit") }}' },
    'repair-slip-receive-customer-cancel':  { mode: 'iframe', url: '{{ url("/remake-rcpt-memo-to-party/cancel?module=repair-slip-receive-customer-cancel&title=Repair%20Slip%20-%20Receive%20from%20Customer%20Cancel") }}' },
    'repair-slip-receive-customer-reprint': { mode: 'iframe', url: '{{ url("/remake-rcpt-memo-to-party/reprint?module=repair-slip-receive-customer-reprint&title=Repair%20Slip%20-%20Receive%20from%20Customer%20Reprint") }}' },

    'repair-slip-return-customer':        { mode: 'iframe', url: '{{ url("/goldsmith-transactions?module=repair-slip-return-customer&title=Repair%20Slip%20-%20Return%20to%20Customer&type=R&mode=new") }}' },
    'repair-slip-return-customer-edit':   { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/edit?module=repair-slip-return-customer-edit&title=Repair%20Slip%20-%20Return%20to%20Customer%20Edit&type=R") }}' },
    'repair-slip-return-customer-cancel': { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/cancel?module=repair-slip-return-customer-cancel&title=Repair%20Slip%20-%20Return%20to%20Customer%20Cancel&type=R") }}' },
    'repair-slip-return-customer-reprint':{ mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/reprint?module=repair-slip-return-customer-reprint&title=Repair%20Slip%20-%20Return%20to%20Customer%20Reprint&type=R") }}' },

    'remake-issue-memo-to-smith':        { mode: 'iframe', url: '{{ url("/goldsmith-transactions?module=remake-issue-memo-to-smith&title=Remake%20-%20Issue%20Memo%20To%20Smith&type=R&mode=new") }}' },
    'remake-issue-memo-to-smith-edit':   { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/edit?module=remake-issue-memo-to-smith-edit&title=Remake%20-%20Issue%20Memo%20To%20Smith%20Edit&type=R") }}' },
    'remake-issue-memo-to-smith-cancel': { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/cancel?module=remake-issue-memo-to-smith-cancel&title=Remake%20-%20Issue%20Memo%20To%20Smith%20Cancel&type=R") }}' },
    'remake-issue-memo-to-smith-reprint':{ mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/reprint?module=remake-issue-memo-to-smith-reprint&title=Remake%20-%20Issue%20Memo%20To%20Smith%20Reprint&type=R") }}' },

    'remake-rcpt-memo-from-smith':       { mode: 'iframe', url: '{{ url("/goldsmith-transactions?module=remake-rcpt-memo-from-smith&title=Remake%20-%20Rcpt%20Memo%20From%20Smith&type=R&mode=new") }}' },
    'remake-rcpt-memo-from-smith-edit':  { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/edit?module=remake-rcpt-memo-from-smith-edit&title=Remake%20-%20Rcpt%20Memo%20From%20Smith%20Edit&type=R") }}' },
    'remake-rcpt-memo-from-smith-cancel':{ mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/cancel?module=remake-rcpt-memo-from-smith-cancel&title=Remake%20-%20Rcpt%20Memo%20From%20Smith%20Cancel&type=R") }}' },
    'remake-rcpt-memo-from-smith-reprint': { mode: 'iframe', url: '{{ url("/goldsmith-transactions-picker/reprint?module=remake-rcpt-memo-from-smith-reprint&title=Remake%20-%20Rcpt%20Memo%20From%20Smith%20Reprint&type=R") }}' },

    'remake-issue-memo-to-party':        { mode: 'iframe', url: '{{ url("/remake-issue-memo-party/new") }}' },
    'remake-issue-memo-to-party-edit':   { mode: 'iframe', url: '{{ url("/remake-issue-memo-party/edit") }}' },
    'remake-issue-memo-to-party-cancel': { mode: 'iframe', url: '{{ url("/remake-issue-memo-party/cancel") }}' },
    'remake-issue-memo-to-party-reprint':{ mode: 'iframe', url: '{{ url("/remake-issue-memo-party/reprint") }}' },

    'remake-repair-return':              { mode: 'iframe', url: '{{ url("/repair-return/new") }}' },
    'remake-repair-return-edit':         { mode: 'iframe', url: '{{ url("/repair-return/picker/edit?title=Remake%20Repair%20Return%20-%20Edit") }}' },
    'remake-repair-return-cancel':       { mode: 'iframe', url: '{{ url("/repair-return/picker/cancel?title=Remake%20Repair%20Return%20-%20Cancel") }}' },
    'remake-repair-return-reprint':      { mode: 'iframe', url: '{{ url("/repair-return/picker/reprint?title=Remake%20Repair%20Return%20-%20Reprint") }}' },

    'counter-issue':                     { mode: 'iframe', url: '{{ url("/counter-issue") }}' },
    'stock-verification':                { mode: 'iframe', url: '{{ url("/stock-verification") }}' },
    'staff-transaction':                 { mode: 'iframe', url: '{{ url("/staff-transaction") }}' },
    'staff-log-update':                  { mode: 'iframe', url: '{{ url("/staff-log-update") }}' },
    'staff-leave-entry':                 { mode: 'iframe', url: '{{ url("/staff-leave-entry") }}' },
    'staff-wgt-transaction':             { mode: 'iframe', url: '{{ url("/staff-wgt-transaction") }}' },

    // Application Settings
    'administration': { mode: 'iframe', url: '{{ url("/administration") }}' },
    'data-transfer': { mode: 'iframe', url: '{{ url("/administration/data-transfer") }}' },
    'application-settings': { mode: 'iframe', url: '{{ url("/application-settings") }}' },

    // Accounts
    'accounts-customer-billwise-rcpt': { mode: 'iframe', url: '{{ url("/accounts/customer-billwise-rcpt") }}' },
    'accounts-debit-credit-note':      { mode: 'iframe', url: '{{ url("/accounts/debit-credit-note") }}' },
    'accounts-expense-voucher-entry':  { mode: 'iframe', url: '{{ url("/accounts/expense-voucher-entry") }}' },
    'accounts-party-code-merge':       { mode: 'iframe', url: '{{ url("/accounts/party-code-merge") }}' },
    'accounts-supplier-billwise-payment':       { mode: 'iframe', url: '{{ url("/accounts/supplier-billwise-payment") }}' },
    'supplier-reports-ac-payable':              { mode: 'iframe', url: '{{ url("/accounts/ac-receivable-payable-summary?mode=S") }}' },
    'supplier-reports-duedate-report':          { mode: 'iframe', url: '{{ url("/suppliers/duedate-report") }}' },
    'supplier-reports-ac-summary':              { mode: 'iframe', url: '{{ url("/ac-summary?module=supplier-reports-ac-summary") }}' },
    'supplier-reports-billwise-payable':        { mode: 'iframe', url: '{{ url("/accounts/supplier-billwise-payment") }}' },
    'supplier-reports-supplier-list':           { mode: 'iframe', url: '{{ url("/suppliers/list") }}' },
    'accounts-rate-diff-adjustment':      { mode: 'iframe', url: '{{ url("/accounts/rate-diff-adjustment") }}' },
    'accounts-group-amt-allocation':      { mode: 'iframe', url: '{{ url("/accounts/group-amt-allocation") }}' },
    'accounts-change-duedate':            { mode: 'iframe', url: '{{ url("/accounts/change-duedate") }}' },
    'accounts-restart-date':              { mode: 'iframe', url: '{{ url("/accounts/restart-date") }}' },
    'accounts-amt-wgt-transfer-entry':    { mode: 'iframe', url: '{{ url("/accounts/amt-wgt-transfer-entry") }}' },
    'accounts-payment-confirmation':      { mode: 'iframe', url: '{{ url("/accounts/payment-confirmation") }}' },
    'accounts-denomination-entry':        { mode: 'iframe', url: '{{ url("/accounts/denomination-entry") }}' },
    'accounts-reports-ac-ledger':         { mode: 'iframe', url: '{{ url("/accounts/ac-ledger") }}' },
    'accounts-reports-group-ledger':      { mode: 'iframe', url: '{{ url("/accounts/group-ledger") }}' },
    'accounts-reports-cash-book':         { mode: 'iframe', url: '{{ url("/accounts/cash-book") }}' },
    'cash-balance':                       { mode: 'iframe', url: '{{ url("/cash-balance") }}' },
    'accounts-reports-bank-book':         { mode: 'iframe', url: '{{ url("/accounts/bank-book") }}' },
    'accounts-reports-yearly-cash-balance': { mode: 'iframe', url: '{{ url("/accounts/yearly-cash-balance") }}' },
    'accounts-reports-pdc-report':        { mode: 'iframe', url: '{{ url("/accounts/pdc-report") }}' },
    'accounts-reports-journal':           { mode: 'iframe', url: '{{ url("/accounts/journal-report") }}' },
    'accounts-reports-all-trans-report':  { mode: 'iframe', url: '{{ url("/accounts/all-trans-report") }}' },
    'accounts-reports-chart-of-accounts': { mode: 'iframe', url: '{{ url("/accounts/chart-of-accounts") }}' },
    'accounts-reports-non-transactional-days-report': { mode: 'iframe', url: '{{ url("/accounts/non-transactional-days-report") }}' },
    'accounts-reports-ac-receivable-payable-summary': { mode: 'iframe', url: '{{ url("/accounts/ac-receivable-payable-summary") }}' },
    'customers-reports-ac-receivable':              { mode: 'iframe', url: '{{ url("/accounts/ac-receivable-payable-summary?mode=C") }}' },
    'customers-reports-credit-bill-details':        { mode: 'iframe', url: '{{ url("/customers/credit-bill-details") }}' },
    'customers-reports-customer-summary':           { mode: 'iframe', url: '{{ url("/customers/summary") }}' },
    'customers-reports-billwise-receivable':        { mode: 'iframe', url: '{{ url("/accounts/customer-billwise-rcpt") }}' },
    'customers-reports-received-details':           { mode: 'iframe', url: '{{ url("/customers/received-details") }}' },
    'customers-reports-payment-details':            { mode: 'iframe', url: '{{ url("/customers/payment-details") }}' },
    'customers-reports-customer-sales-summary':     { mode: 'iframe', url: '{{ url("/customers/sales-summary") }}' },
    'customers-reports-billwise-details':           { mode: 'iframe', url: '{{ url("/customers/billwise-details") }}' },
    'customers-reports-bill-collection-details':    { mode: 'iframe', url: '{{ url("/customers/bill-collection-details") }}' },
    'customers-reports-ac-summary':                 { mode: 'iframe', url: '{{ url("/ac-summary?module=customers-reports-ac-summary") }}' },
    'customers-reports-duedate-report':             { mode: 'iframe', url: '{{ url("/customers/duedate-report") }}' },
    'customers-reports-party-history':              { mode: 'iframe', url: '{{ url("/customers/party-history") }}' },
    'customers-reports-visit-report':               { mode: 'iframe', url: '{{ url("/customers/visit-report") }}' },
    'customers-reports-prev-card-point-report':     { mode: 'iframe', url: '{{ url("/point-card-points") }}' },
    'customers-reports-customer-list':              { mode: 'iframe', url: '{{ url("/customers/list") }}' },
    'customers-data-check':                         { mode: 'iframe', url: '{{ url("/customers/data-check") }}' },
    'accounts-reports-group-ac-summary':  { mode: 'iframe', url: '{{ url("/accounts/group-ac-summary") }}' },
    'accounts-reports-groupwise-expanded-list': { mode: 'iframe', url: '{{ url("/accounts/groupwise-expanded-list") }}' },
    'accounts-reports-rcpt-pmnt-report':  { mode: 'iframe', url: '{{ url("/accounts/rcpt-pmnt-report") }}' },
    'accounts-reports-avg-rate-profit':   { mode: 'iframe', url: '{{ url("/reports/avg-rate-profit") }}' },
    'accounts-reports-suspense-ac-ledger': { mode: 'iframe', url: '{{ url("/accounts/suspense-ac-ledger") }}' },
    'stock-summary-cost-wise':            { mode: 'iframe', url: '{{ url("/stock-summary-cost-wise") }}' },
    'stock-summary-rate-wise':            { mode: 'iframe', url: '{{ url("/stock-summary-rate-wise") }}' },
    'purchase-bill-confirmation':         { mode: 'iframe', url: '{{ url("/purchase-bill-confirmation") }}' },
    'day-summary':                        { mode: 'iframe', url: '{{ url("/day-summary") }}' },
    'tax-reports-stock-register':         { mode: 'iframe', url: '{{ url("/stock-register") }}' },
    'einvoice-register':                  { mode: 'iframe', url: '{{ url("/e-invoice-register") }}' },
    'item-reports-stock-register-summary': { mode: 'iframe', url: '{{ url("/stock-register-summary?module=item-reports-stock-register-summary&title=Stock%20Register%20Summary") }}' },
    'tax-reports-purchase-book':          { mode: 'iframe', url: '{{ url("/tax-purchase-book") }}' },
    'tax-reports-smith-book':             { mode: 'iframe', url: '{{ url("/smith-book") }}' },
    'tax-reports-jewllery-book':          { mode: 'iframe', url: '{{ url("/jewllery-book") }}' },
    'tax-reports-outstanding-tax-report': { mode: 'iframe', url: '{{ url("/outstanding-tax") }}' },
    'goldsmith-reports-transactions':     { mode: 'iframe', url: '{{ url("/goldsmith-transactions-report") }}' },
    'goldsmith-reports-smith-ledger':     { mode: 'iframe', url: '{{ url("/goldsmith-transactions-report?ctype=G") }}' },
    'jewellery-reports-jewellery-ledger': { mode: 'iframe', url: '{{ url("/goldsmith-transactions-report?ctype=J") }}' },
    'jewellery-reports-transactions':     { mode: 'iframe', url: '{{ url("/goldsmith-transactions-report?ctype=J") }}' },
    'goldsmith-reports-wgt-amt-summary-simple': { mode: 'iframe', url: '{{ url("/smith-wa-summary?ctype=G") }}' },
    'goldsmith-reports-wgt-amt-summary-details': { mode: 'iframe', url: '{{ url("/smith-wa-summary?ctype=G") }}' },
    'jewellery-reports-wgt-amt-summary':  { mode: 'iframe', url: '{{ url("/smith-wa-summary?ctype=J") }}' },
    'jewellery-reports-wgt-amt-analysis': { mode: 'iframe', url: '{{ url("/smith-wa-analysis?ctype=J") }}' },
    'goldsmith-reports-wgt-amt-analysis': { mode: 'iframe', url: '{{ url("/smith-wa-analysis?ctype=G") }}' },
    'goldsmith-reports-ageing-report': { mode: 'iframe', url: '{{ url("/smith-ageing-report?ctype=G") }}' },
    'jewellery-reports-ageing-report': { mode: 'iframe', url: '{{ url("/smith-ageing-report?ctype=J") }}' },
    'goldsmith-reports-smith-trans-summary': { mode: 'iframe', url: '{{ url("/smith-trans-summary?ctype=G") }}' },
    'jewellery-reports-jewl-trans-summary': { mode: 'iframe', url: '{{ url("/smith-trans-summary?ctype=J") }}' },
    'goldsmith-reports-lot-no-report': { mode: 'iframe', url: '{{ url("/smith-lot-report") }}' },
    'goldsmith-reports-fix-unfix-report': { mode: 'iframe', url: '{{ url("/smith-fixunfix-report") }}' },
    'goldsmith-reports-smith-list': { mode: 'iframe', url: '{{ url("/smith-list") }}' },
    'jewellery-reports-sales-purchase-item-report': { mode: 'iframe', url: '{{ url("/jewel-summary") }}' },
    'jewellery-reports-profit-report': { mode: 'iframe', url: '{{ url("/jewl-profit-report") }}' },
    'jewellery-reports-tds-report': { mode: 'iframe', url: '{{ url("/tds-report") }}' },
    'jewellery-reports-extra-amt-report': { mode: 'iframe', url: '{{ url("/extra-amt-report") }}' },
    'jewellery-reports-ac-summary': { mode: 'iframe', url: '{{ url("/ac-summary?module=jewellery-reports-ac-summary") }}' },
    'jewellery-reports-send-mail-to-jewl': { mode: 'iframe', url: '{{ url("/jewl-rep-email") }}' },
    'accounts-reports-cash-flow-statement': { mode: 'iframe', url: '{{ url("/cash-flow-report") }}' },
    'accounts-reports-integrity-checking':  { mode: 'iframe', url: '{{ url("/integrity-checking") }}' },
    'country-currency':                     { mode: 'iframe', url: '{{ url("/country-currency") }}' },
    'ai-insights':                          { mode: 'iframe', url: '{{ url("/ai-insights") }}' },
    'staff-reports':                           { mode: 'iframe', url: '{{ url("/staff-reports") }}' },
    'staff-reports-salesman-book':             { mode: 'iframe', url: '{{ url("/staff-reports?sub=salesman-book") }}' },
    'staff-reports-salesman-summary':          { mode: 'iframe', url: '{{ url("/staff-reports?sub=salesman-summary") }}' },
    'staff-reports-counter-sales':             { mode: 'iframe', url: '{{ url("/staff-reports?sub=counter-sales") }}' },
    'staff-reports-incharge-report':           { mode: 'iframe', url: '{{ url("/staff-reports?sub=incharge-report") }}' },
    'staff-reports-commission-report':         { mode: 'iframe', url: '{{ url("/staff-reports?sub=commission-report") }}' },
    'staff-reports-performance':               { mode: 'iframe', url: '{{ url("/staff-reports?sub=performance") }}' },
    'staff-reports-summary':                   { mode: 'iframe', url: '{{ url("/staff-reports?sub=salesman-summary") }}' },
    'staff-reports-term-summary':              { mode: 'iframe', url: '{{ url("/staff-reports?sub=term-summary") }}' },
    'staff-reports-ledger':                    { mode: 'iframe', url: '{{ url("/staff-reports?sub=ledger") }}' },
    'staff-reports-staff-log-report':          { mode: 'iframe', url: '{{ url("/staff-reports?sub=staff-log") }}' },
    'staff-reports-attendance-report':         { mode: 'iframe', url: '{{ url("/staff-reports?sub=attendance") }}' },
    'staff-reports-leave-report':              { mode: 'iframe', url: '{{ url("/staff-reports?sub=leave") }}' },
    'staff-reports-wgt-trans-report':          { mode: 'iframe', url: '{{ url("/staff-reports?sub=wgt-trans") }}' },
    'staff-reports-sales-man-report':          { mode: 'iframe', url: '{{ url("/staff-reports?sub=salesman-book") }}' },
    'term-summary':                            { mode: 'iframe', url: '{{ url("/term-summary") }}' },

    // â”€â”€ New Modules â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // WhatsApp / SMS Gateway
    'whatsapp-gateway/settings':             { mode: 'iframe', url: '{{ url("/whatsapp-gateway/settings") }}' },
    'whatsapp-gateway/log':                  { mode: 'iframe', url: '{{ url("/whatsapp-gateway/log") }}' },

    // GSTR Reports
    'gstr-reports':                          { mode: 'iframe', url: '{{ url("/gstr-reports") }}' },

    // Gold Loan
    'gold-loan':                             { mode: 'iframe', url: '{{ url("/gold-loan") }}' },
    'gold-loan/report':                      { mode: 'iframe', url: '{{ url("/gold-loan/report") }}' },

    // Product Image Catalogue
    'catalogue':                             { mode: 'iframe', url: '{{ url("/catalogue") }}' },
    'catalogue/gallery':                     { mode: 'iframe', url: '{{ url("/catalogue/gallery") }}' },

    // Tally Export
    'tally-export':                          { mode: 'iframe', url: '{{ url("/tally-export") }}' },

    // Hallmarking (BIS)
    'hallmark':                              { mode: 'iframe', url: '{{ url("/hallmark") }}' },

    // Staff Payroll
    'staff-payroll':                         { mode: 'iframe', url: '{{ url("/staff-payroll") }}' },

    // Multi-Branch
    'branch-master':                         { mode: 'iframe', url: '{{ url("/branch-master") }}' },
    'branch-transfer':                       { mode: 'iframe', url: '{{ url("/branch-transfer") }}' },
    'branch-transfer-report':                { mode: 'iframe', url: '{{ url("/branch-transfer-report") }}' },

    // Digital Weighing Scale
    'scale-settings':                        { mode: 'iframe', url: '{{ url("/scale-settings") }}' }
};
Object.assign(moduleConfigMap, {
    'receipt-cash': moduleConfigMap['accounts-receipt'],
    'payment-cash': moduleConfigMap['accounts-payment'],
    'journal-entry': moduleConfigMap['accounts-journal'],
    'ledger': moduleConfigMap['accounts-reports-ac-ledger'],
    'daily-sales': moduleConfigMap['sales-reports-sales-book'],
    'monthly-sales': moduleConfigMap['sales-reports-monthly-sales'],
    'sales-register': moduleConfigMap['sales-reports-sales-register'],
    'scheme-collection': moduleConfigMap['scheme-details-scheme-collection'],
    'stock-list-group': { mode: 'iframe', url: '{{ url("/stock/list?rpt_type=G&module=stock-list-group&title=Group%20Stock%20List") }}' },
    'stock-list-upto-date': { mode: 'iframe', url: '{{ url("/stock/list?upto=1&module=stock-list-upto-date&title=Stock%20As%20On%20Date") }}' },
    'stock-list-stock-check-list': { mode: 'iframe', url: '{{ url("/stock/list?checklist=1&module=stock-list-stock-check-list&title=Stock%20Checklist") }}' },
    'stock-list-stock-type-wise': { mode: 'iframe', url: '{{ url("/stock/list?rpt_type=ST&module=stock-list-stock-type-wise&title=Stock%20Type%20Wise") }}' },
    'stock-list-stock-verification-report': moduleConfigMap['barcode-reports-stock-verification-report'],
    'stock-list-stock-verification-entry': moduleConfigMap['stock-verification'],
    'kuri-reports-chart': moduleConfigMap['kuri-details-kuri-collection'],
    'kuri-reports-collection-report': moduleConfigMap['kuri-details-kuri-collection'],
    'scheme-reports-scheme-collection': moduleConfigMap['scheme-details-scheme-collection'],
    'scheme-reports-scheme-details': moduleConfigMap['scheme-details-customer-registration'],
    'scheme-reports-colln-commision-report': moduleConfigMap['staff-reports-commission-report'],
    'other-reports-day-cash-book-report': moduleConfigMap['accounts-reports-cash-book'],
    'other-reports-day-report': moduleConfigMap['day-summary'],
    'other-reports-daily-all-report': moduleConfigMap['day-summary'],
    'other-reports-purity-test-report': moduleConfigMap['purity-testing-new'],
    'other-reports-stock-asset-liablity-expense-report': moduleConfigMap['accounts-reports-balance-sheet'],
    'other-reports-term-summary-malayalam': moduleConfigMap['term-summary']
});
const disabledModuleIds = new Set([
    'backup-every-day',
    'other-reports-all-report-print',
    'other-reports-day-history',
    'other-reports-daysummary-malayalam',
    'other-reports-month-end-report',
    'other-reports-stockvalue-profit-report',
    'printer-setup',
    're-connectfs',
    'scheme-details-pos-upload',
    'scheme-reports-scheme-book',
    'scheme-reports-scheme-maturity'
]);
// Decode &amp; back to & in module URLs (Blade escaping fix)
for(var _k in moduleConfigMap){ if(moduleConfigMap[_k].url) moduleConfigMap[_k].url = moduleConfigMap[_k].url.replace(/&amp;/g,'&'); }

/**
 * ============================================================
 * Menu Specification
 * ============================================================
 */
const menuSpec = [
    { label: 'File', items: [
        m('Printer Setup', 'printer-setup', 'fa-print'),
        { label: 'Exit', icon: 'fa-right-from-bracket', action: function() { window.location.href = '{{ url("/logout") }}'; } }
    ]},
    { label: 'Registration', items: [
        { label: 'System Settings', icon: 'fa-gear', children: [
            { label: 'Update', icon: 'fa-rotate', children: [
                m('Rate', 'rate-update', 'fa-coins'),
                m('Application', 'application-settings', 'fa-sliders')
            ]},
            m('Country, Currency & Religion', 'country-currency', 'fa-globe')
        ]},
        { label: 'Item Master', icon: 'fa-box', children: [
            m('Item Add/Edit', 'item-add-edit', 'fa-gem'),
            m('Item Temp', 'item-temp', 'fa-clock'),
            m('Other Items', 'other-items', 'fa-boxes-stacked'),
            m('Groups', 'groups', 'fa-layer-group'),
            m('Sub Groups', 'sub-groups', 'fa-sitemap'),
            m('Purity Type', 'purity-type', 'fa-star'),
            m('Counters', 'counters', 'fa-desktop'),
            m('Wastage Table', 'wastage-table', 'fa-table'),
            m('MC Table', 'mc-table', 'fa-calculator'),
            m('Party MC Table', 'party-mc-table', 'fa-users'),
            m('Stock Type', 'stock-type', 'fa-warehouse'),
            m('Models', 'models', 'fa-shapes'),
            m('Opening Stock Entry', 'opening-stock-entry', 'fa-file-invoice'),
            m('ReOrder Level Table', 'reorder-level-table', 'fa-chart-line')
        ]},
        m('Customer Master', 'customer', 'fa-users', 'Shift+Alt+C'),
        m('Supplier Master', 'supplier', 'fa-truck', 'Shift+Alt+U'),
        m('Goldsmith Master', 'goldsmith-add', 'fa-hammer'),
        m('Refiner Master', 'refiner-add', 'fa-fire'),
        m('Jewellery Master', 'jewellery-add', 'fa-ring'),
        m('Depositor Weight', 'depositors-wgt', 'fa-weight-scale'),
        { label: 'Account Master', icon: 'fa-book', children: [
            m('Account', 'accounts-master-account', 'fa-book-open', 'Shift+Alt+A'),
            m('Position Settings', 'accounts-master-position-settings', 'fa-sliders'),
            m('Book Stock', 'accounts-master-book-stock', 'fa-box'),
            m('Op.Stock Value Set', 'accounts-master-op-stock-value-set', 'fa-calculator'),
            m('C/o Party Limit', 'accounts-master-co-party-limit', 'fa-scale-balanced')
        ]},
        m('Staff Master', 'staff-adding', 'fa-user-tie', 'Shift+Alt+M'),
        m('Salesman Master', 'sales-man', 'fa-user-tag'),
        m('Scheme Master', 'scheme-type-master', 'fa-list-check'),
        m('Invoice Settings', 'bill-prefix', 'fa-hashtag'),
        m('State Master', 'states-adding', 'fa-map'),
        m('Repair Management', 'repair-complaints', 'fa-screwdriver-wrench'),
        m('Gift Management', 'gift-table', 'fa-gift'),
        m('Loyalty Program', 'point-card', 'fa-credit-card'),
        m('Denomination Master', 'denomination-master', 'fa-money-bill'),
        { label: 'Opening Balance Entry', icon: 'fa-file-lines', children: [
            m('Customers', 'op-bill-creation-customers', 'fa-users')
        ]},
        { label: 'User Management', icon: 'fa-user-shield', children: [
            m('Change Password', 'change-password', 'fa-key'),
            m('Provide Access', 'provide-access', 'fa-lock'),
            m('Users History', 'users-history', 'fa-clock-rotate-left')
        ]},
        { label: 'Branch Management', icon: 'fa-building', children: [
            m('Branch Master', 'branch-master', 'fa-building'),
            m('Inter-Branch Transfer', 'branch-transfer', 'fa-truck'),
        ]},
        { label: 'Product Catalogue', icon: 'fa-images', children: [
            m('Image Manager', 'catalogue', 'fa-images'),
            m('Product Gallery', 'catalogue/gallery', 'fa-photo-film'),
        ]}
    ], layout: 'grid2'},
    { label: 'Transactions', items: [
        { label: 'Accounts Transactions', icon: 'fa-file-invoice-dollar', children: [
            m('Account Restart', 'accounts-restart-date', 'fa-calendar-plus'),
            m('Amount/Weight Transfer', 'accounts-amt-wgt-transfer-entry', 'fa-right-left'),
            m('Cash Denomination', 'accounts-denomination-entry', 'fa-money-bill'),
            m('Cheque Processing', 'accounts-cheque-clearance', 'fa-money-check'),
            m('Customer Receipt', 'accounts-customer-billwise-rcpt', 'fa-file-invoice', 'Shift+Alt+2'),
            m('Daily Entry', 'accounts-daily-statement-entry', 'fa-calendar-day', 'Ctrl+Alt+F2'),
            m('Discount Entry', 'accounts-discount-allocation', 'fa-percent', 'Shift+Alt+D'),
            m('Adjustments', 'accounts-debit-credit-note', 'fa-note-sticky'),
            m('Due Date Update', 'accounts-change-duedate', 'fa-calendar-check'),
            m('Expense Entry', 'accounts-expense-voucher-entry', 'fa-receipt'),
            m('Group Allocation', 'accounts-group-amt-allocation', 'fa-object-group'),
            m('Journal Entry', 'accounts-journal', 'fa-book', 'Shift+Alt+J'),
            m('Multi Bill To One Account', 'accounts-party-code-merge', 'fa-code-branch'),
            m('Make Payment', 'accounts-payment', 'fa-money-check-dollar', 'Shift+Alt+3'),
            m('Payment Approval', 'accounts-payment-confirmation', 'fa-circle-check'),
            m('Rate Adjustment', 'accounts-rate-diff-adjustment', 'fa-scale-balanced'),
            m('Receive Payment', 'accounts-receipt', 'fa-money-bill-wave', 'Shift+Alt+1'),
            m('Supplier Payment', 'accounts-supplier-billwise-payment', 'fa-file-invoice-dollar'),
            m('Suspense Entry', 'accounts-suspense-entry', 'fa-circle-question', 'Ctrl+Shift+S'),
            m('Edit Transactions', 'accounts-edit-rcpt-pmnt-entry', 'fa-pen')
        ]},
        { label: 'Barcode Management', icon: 'fa-barcode', children: [
            m('Bulk Barcode', 'barcode-multi-entry', 'fa-bars-staggered'),
            m('Counter Issue', 'counter-issue', 'fa-desktop'),
            m('Single Barcode', 'barcode-single-entry', 'fa-barcode', 'Shift+Alt+B'),
            m('Stock Check', 'stock-verification', 'fa-clipboard-check')
        ]},
        { label: 'Chit Scheme', icon: 'fa-list-check', children: [
            m('Add Members', 'kuri-details-enter-customers', 'fa-users'),
            m('Collection Entry', 'kuri-details-kuri-collection', 'fa-money-bill-wave', 'Alt+K'),
            m('Close Scheme', 'kuri-details-kuri-finish', 'fa-flag-checkered'),
            m('Draw Process', 'kuri-details-kuri-draw', 'fa-ticket'),
            m('Interest Calculation', 'kuri-details-interest-posting', 'fa-percent')
        ]},
        m('Customer Greetings', 'crm-greetings-campaigns', 'fa-bullhorn'),
        { label: 'Customer Weight Deposit', icon: 'fa-scale-balanced', children: [
            m('Deposit Entry', 'party-wgt-deposit-bill', 'fa-right-left'),
            m('Edit Deposit', 'party-wgt-deposit-bill-edit', 'fa-pen'),
            m('Cancel Deposit', 'party-wgt-deposit-bill-cancel', 'fa-ban'),
            m('Interest Calculation', 'party-wgt-deposit-bill-interest-posting', 'fa-percent'),
            m('Print Deposit', 'party-wgt-deposit-bill-reprint', 'fa-print')
        ]},
        { label: 'Diamond & Stone Entry', icon: 'fa-gem', children: [
            { label: 'New Purchase', icon: 'fa-cart-plus', children: [
                m('New', 'diamonds-pres-stone-purchase-new', 'fa-file-circle-plus'),
                m('Edit', 'diamonds-pres-stone-purchase-edit', 'fa-pen'),
                m('Cancel', 'diamonds-pres-stone-purchase-cancel', 'fa-ban'),
                m('Print', 'diamonds-pres-stone-purchase-reprint', 'fa-print')
            ]},
            { label: 'Return Entry', icon: 'fa-rotate-left', children: [
                m('New', 'diamonds-pres-stone-purchase-return-new', 'fa-file-circle-plus'),
                m('Edit', 'diamonds-pres-stone-purchase-return-edit', 'fa-pen'),
                m('Cancel', 'diamonds-pres-stone-purchase-return-cancel', 'fa-ban'),
                m('Print', 'diamonds-pres-stone-purchase-return-reprint', 'fa-print')
            ]}
        ]},
        m('Gold Rate Display', 'gold-rate-image-set', 'fa-image'),
        { label: 'Goldsmith Work', icon: 'fa-hammer', children: [
            m('Cancel Work', 'goldsmith-bill-cancel', 'fa-ban'),
            m('Create Work Note', 'goldsmith-bill-new-work-note', 'fa-file-signature', 'Shift+Alt+W'),
            m('Edit Work', 'goldsmith-bill-edit', 'fa-pen'),
            m('Print Work', 'goldsmith-bill-reprint', 'fa-print'),
            m('Work Entry', 'goldsmith-bill', 'fa-right-left', 'Shift+Alt+G')
        ]},
        { label: 'Jewellery Processing', icon: 'fa-ring', children: [
            m('Cancel Entry', 'jewellery-bill-cancel', 'fa-ban'),
            m('Edit Entry', 'jewellery-bill-edit', 'fa-pen'),
            m('Jewellery Entry', 'jewellery-bill', 'fa-right-left', 'Ctrl+J'),
            m('Print Entry', 'jewellery-bill-reprint', 'fa-print'),
            m('Sync from Head Office', 'jewellery-bill-update-from-ho', 'fa-building-circle-arrow-right')
        ]},
        { label: 'Order Management', icon: 'fa-clipboard-list', children: [
            m('New Order', 'order-bill-new-order', 'fa-file-circle-plus', 'Shift+Alt+O'),
            m('Edit Order Entry', 'order-bill-edit-order-entry', 'fa-pen'),
            m('Reprint Order', 'order-bill-reprint', 'fa-print'),
            m('Advance After', 'order-bill-advance-after', 'fa-money-bill-wave'),
            m('Order Sale', 'order-bill-order-sale', 'fa-cart-plus', 'Shift+Alt+V'),
            m('Direct Sale', 'order-bill-sale-without-order', 'fa-cart-shopping'),
            m('Edit Order Sale', 'order-bill-edit-order-sale', 'fa-pen-to-square'),
            m('Reprint Order Sale', 'order-bill-reprint-order-sale', 'fa-print'),
            m('Cancel Order', 'order-bill-cancel', 'fa-ban', 'Shift+Alt+X'),
            m('Fix Rate', 'order-bill-order-rate-fix', 'fa-wrench'),
            m('Order Control', 'order-bill-block-unblock', 'fa-lock-open'),
            m('Sync Orders', 'order-bill-update-from-jewelleries', 'fa-building-circle-arrow-right', 'Ctrl+Shift+J')
        ]},
        { label: 'Other Item Transactions', icon: 'fa-right-left', children: [
            { label: 'Other Purchase', icon: 'fa-bag-shopping', children: [
                m('New', 'other-items-trans-purchase-new', 'fa-file-circle-plus'),
                m('Edit', 'other-items-trans-purchase-edit', 'fa-pen'),
                m('Cancel', 'other-items-trans-purchase-cancel', 'fa-ban'),
                m('Reprint', 'other-items-trans-purchase-reprint', 'fa-print')
            ]},
            { label: 'Other Sales', icon: 'fa-cart-shopping', children: [
                m('New', 'other-items-trans-sales-new', 'fa-file-circle-plus'),
                m('Edit', 'other-items-trans-sales-edit', 'fa-pen'),
                m('Cancel', 'other-items-trans-sales-cancel', 'fa-ban'),
                m('Reprint', 'other-items-trans-sales-reprint', 'fa-print')
            ]}
        ]},
        { label: 'Partner Deposit', icon: 'fa-handshake', children: [
            m('Cancel Deposit', 'partners-deposit-wgt-cancel', 'fa-ban'),
            m('Edit Deposit', 'partners-deposit-wgt-edit', 'fa-pen'),
            m('Interest Calculation', 'partners-deposit-wgt-intrest-posting', 'fa-percent'),
            m('New Deposit', 'partners-deposit-wgt-new', 'fa-file-circle-plus', 'Ctrl+W'),
            m('Opening Weight', 'partners-deposit-wgt-op-weight-entry', 'fa-weight-scale'),
            m('Print Deposit', 'partners-deposit-wgt-reprint', 'fa-print')
        ]},
        { label: 'Purchase Invoice', icon: 'fa-bag-shopping', children: [
            m('New Purchase', 'purchase-bill', 'fa-file-invoice', 'Shift+Alt+P'),
            m('Edit Purchase', 'purchase-bill-edit', 'fa-pen'),
            m('Print Purchase', 'purchase-bill-reprint', 'fa-print'),
            m('Cancel Purchase', 'purchase-bill-cancel', 'fa-ban'),
            m('Purchase Approval', 'purchase-bill-confirmation', 'fa-circle-check', 'Ctrl+F8')
        ]},
        { label: 'Purchase Return', icon: 'fa-reply', children: [
            m('New Return', 'purchase-return-bill', 'fa-file-invoice', 'Ctrl+F3'),
            m('Edit Return', 'purchase-return-bill-edit', 'fa-pen'),
            m('Print Return', 'purchase-return-bill-reprint', 'fa-print'),
            m('Cancel Return', 'purchase-return-bill-cancel', 'fa-ban')
        ]},
        { label: 'Purity Check', icon: 'fa-vial', children: [
            m('Cancel Test', 'purity-testing-cancel', 'fa-ban'),
            m('Edit Test', 'purity-testing-edit', 'fa-pen'),
            m('New Test', 'purity-testing-new', 'fa-file-circle-plus'),
            m('Print Test', 'purity-testing-reprint', 'fa-print')
        ]},
        { label: 'Refinery Operations', icon: 'fa-fire', children: [
            m('Cancel Entry', 'refinery-bill-cancel', 'fa-ban'),
            m('Combined Entry', 'refinery-bill-all-in-one', 'fa-layer-group'),
            m('Edit Entry', 'refinery-bill-edit', 'fa-pen'),
            m('New Refinery Entry', 'refinery-bill-new-entry', 'fa-file-circle-plus', 'Shift+Alt+Y'),
            m('Refinery Return', 'refinery-bill-returns', 'fa-rotate-left', 'Ctrl+Shift+Y'),
            m('Edit Return', 'refinery-bill-returns-edit', 'fa-pen-to-square')
        ]},
        { label: 'Repair & Remake', icon: 'fa-recycle', children: [
            { label: 'Repair Slip', icon: 'fa-receipt', children: [
                { label: 'Receive from Customer', icon: 'fa-file-import', children: [
                    m('New', 'repair-slip-receive-customer', 'fa-file-circle-plus'),
                    m('Edit', 'repair-slip-receive-customer-edit', 'fa-pen'),
                    m('Cancel', 'repair-slip-receive-customer-cancel', 'fa-ban'),
                    m('Reprint', 'repair-slip-receive-customer-reprint', 'fa-print')
                ]},
                { label: 'Return to Customer', icon: 'fa-file-export', children: [
                    m('New', 'repair-slip-return-customer', 'fa-file-circle-plus'),
                    m('Edit', 'repair-slip-return-customer-edit', 'fa-pen'),
                    m('Cancel', 'repair-slip-return-customer-cancel', 'fa-ban'),
                    m('Reprint', 'repair-slip-return-customer-reprint', 'fa-print')
                ]}
            ]},
            { label: 'Issue to Goldsmith', icon: 'fa-file-export', children: [
                m('New', 'remake-issue-memo-to-smith', 'fa-file-circle-plus'),
                m('Edit', 'remake-issue-memo-to-smith-edit', 'fa-pen'),
                m('Cancel', 'remake-issue-memo-to-smith-cancel', 'fa-ban'),
                m('Reprint', 'remake-issue-memo-to-smith-reprint', 'fa-print')
            ]},
            { label: 'Receive from Customer', icon: 'fa-file-import', children: [
                m('New', 'remake-rcpt-memo-to-party', 'fa-file-circle-plus'),
                m('Edit', 'remake-rcpt-memo-to-party-edit', 'fa-pen'),
                m('Cancel', 'remake-rcpt-memo-to-party-cancel', 'fa-ban'),
                m('Reprint', 'remake-rcpt-memo-to-party-reprint', 'fa-print')
            ]},
            { label: 'Receive from Goldsmith', icon: 'fa-file-import', children: [
                m('New', 'remake-rcpt-memo-from-smith', 'fa-file-circle-plus'),
                m('Edit', 'remake-rcpt-memo-from-smith-edit', 'fa-pen'),
                m('Cancel', 'remake-rcpt-memo-from-smith-cancel', 'fa-ban'),
                m('Reprint', 'remake-rcpt-memo-from-smith-reprint', 'fa-print')
            ]},
            { label: 'Repair Completion', icon: 'fa-rotate-left', children: [
                m('New', 'remake-repair-return', 'fa-file-circle-plus'),
                m('Edit', 'remake-repair-return-edit', 'fa-pen'),
                m('Cancel', 'remake-repair-return-cancel', 'fa-ban'),
                m('Reprint', 'remake-repair-return-reprint', 'fa-print')
            ]},
            { label: 'Return to Customer', icon: 'fa-file-export', children: [
                m('New', 'remake-issue-memo-to-party', 'fa-file-circle-plus'),
                m('Edit', 'remake-issue-memo-to-party-edit', 'fa-pen'),
                m('Cancel', 'remake-issue-memo-to-party-cancel', 'fa-ban'),
                m('Reprint', 'remake-issue-memo-to-party-reprint', 'fa-print')
            ]}
        ]},
        { label: 'Sales Invoice', icon: 'fa-cart-shopping', children: [
            m('New Invoice', 'sales-bill', 'fa-file-invoice', 'Shift+Alt+S'),
            m('Edit Invoice', 'sales-bill-edit', 'fa-pen'),
            m('Quotation', 'sales-bill-quotation', 'fa-file-lines'),
            m('Quotation Edit', 'sales-bill-quotation-edit', 'fa-pen-to-square'),
            m('Quotation Cancel', 'sales-bill-quotation-cancel', 'fa-trash-can'),
            m('Print Invoice', 'sales-bill-reprint', 'fa-print'),
            m('Edit Order Sale', 'order-bill-edit-order-sale', 'fa-pen-to-square'),
            m('Reprint Order Sale', 'order-bill-reprint-order-sale', 'fa-print'),
            m('Cancel Invoice', 'sales-bill-cancel', 'fa-ban'),
            m('Invoice Approval', 'sales-bill-confirmation', 'fa-circle-check', 'Alt+F8'),
            m('E-Invoice Register', 'einvoice-register', 'fa-file-circle-check')
        ]},
        { label: 'Sales Return', icon: 'fa-rotate-left', children: [
            m('New Return', 'sales-return-bill', 'fa-file-invoice', 'Ctrl+F2'),
            m('Edit Return', 'sales-return-bill-edit', 'fa-pen'),
            m('Print Return', 'sales-return-bill-reprint', 'fa-print'),
            m('Cancel Return', 'sales-return-bill-cancel', 'fa-ban')
        ]},
        { label: 'Chitty Management', icon: 'fa-list', children: [
            m('Close Scheme', 'scheme-details-scheme-finished', 'fa-check-double'),
            m('Collection', 'scheme-details-scheme-collection', 'fa-money-bill-wave', 'Ctrl+L'),
            m('Interest', 'scheme-details-interest-posting', 'fa-percent'),
            m('POS Sync', 'scheme-details-pos-upload', 'fa-upload'),
            m('Passbook Print', 'scheme-details-pass-book-print', 'fa-book-open'),
            m('Refund', 'scheme-details-scheme-part-refund', 'fa-money-bill-transfer'),
            m('Register Customer', 'scheme-details-customer-registration', 'fa-user-plus', 'Ctrl+M')
        ]},
        { label: 'Staff Management', icon: 'fa-user-tie', children: [
            m('Attendance Log', 'staff-log-update', 'fa-clipboard-check'),
            m('Leave Management', 'staff-leave-entry', 'fa-calendar-minus'),
            m('Staff Entry', 'staff-transaction', 'fa-right-left'),
            m('Weight Handling', 'staff-wgt-transaction', 'fa-weight-scale')
        ]},
        { label: 'Stock Management', icon: 'fa-arrows-rotate', children: [
            m('Barcode Transfer', 'item-stock-adjustment-stock-transfer-barcode', 'fa-barcode', 'Ctrl+Alt+A'),
            m('Bulk Transfer', 'item-stock-adjustment-stock-transfer-multi-entry', 'fa-table-list'),
            m('Cancel Adjustment', 'item-stock-adjustment-cancel', 'fa-ban'),
            m('Edit Adjustment', 'item-stock-adjustment-edit', 'fa-pen-to-square'),
            m('Stock Adjustment', 'item-stock-adjustment-stock-add-less', 'fa-plus-minus', 'Shift+Alt+K'),
            m('Transfer Stock', 'item-stock-adjustment-stock-transfer', 'fa-right-left', 'Ctrl+A')
        ]},
        { label: 'Gold Loan', icon: 'fa-coins', children: [
            m('Loan Entry', 'gold-loan', 'fa-coins'),
            m('Loan Report', 'gold-loan/report', 'fa-file-invoice'),
        ]},
        { label: 'Hallmarking (BIS)', icon: 'fa-stamp', children: [
            m('BIS Records', 'hallmark', 'fa-stamp'),
        ]},
        { label: 'Staff Payroll', icon: 'fa-money-check', children: [
            m('Process Salary', 'staff-payroll', 'fa-money-check'),
        ]},
    ]},
    { label: 'Reports', items: [
        { label: 'Financial Reports', icon: 'fa-book', children: [
            m('Account Ledger', 'accounts-reports-ac-ledger', 'fa-book-open'),
            m('All Transactions', 'accounts-reports-all-trans-report', 'fa-file-lines'),
            m('Average Profit', 'accounts-reports-avg-rate-profit', 'fa-chart-pie'),
            m('Balance Sheet', 'accounts-reports-balance-sheet', 'fa-file-invoice-dollar'),
            m('Bank Statement', 'accounts-reports-bank-book', 'fa-building-columns'),
            m('Cash Book', 'accounts-reports-cash-book', 'fa-money-bill-wave', 'Alt+H'),
            m('Cash Flow Statement', 'accounts-reports-cash-flow-statement', 'fa-chart-line'),
            m('Chart of Accounts', 'accounts-reports-chart-of-accounts', 'fa-chart-bar'),
            m('Data Validation', 'accounts-reports-integrity-checking', 'fa-shield-halved'),
            m('Daybook', 'day-book', 'fa-calendar-day'),
            m('Detailed Group List', 'accounts-reports-groupwise-expanded-list', 'fa-list'),
            m('Group Ledger', 'accounts-reports-group-ledger', 'fa-layer-group'),
            m('Group Summary', 'accounts-reports-group-ac-summary', 'fa-object-group', 'Alt+F9'),
            m('Inactive Days', 'accounts-reports-non-transactional-days-report', 'fa-calendar-xmark'),
            m('Journal Report', 'accounts-reports-journal', 'fa-bookmark'),
            m('Post Dated Cheque', 'accounts-reports-pdc-report', 'fa-money-check'),
            { label: 'Profit & Loss', icon: 'fa-chart-pie', children: [
                m('Upto a Date', 'accounts-reports-profit-loss-upto-date', 'fa-calendar-check'),
                m('For a Period', 'accounts-reports-profit-loss-for-period', 'fa-calendar-week')
            ]},
            m('Receipts & Payments', 'accounts-reports-rcpt-pmnt-report', 'fa-receipt', 'Alt+P'),
            m('Customer Balance', 'accounts-reports-ac-receivable-payable-summary', 'fa-scale-balanced', 'Alt+F5'),
            m('Suspense Ledger', 'accounts-reports-suspense-ac-ledger', 'fa-circle-question'),
            { label: 'Trial Balance', icon: 'fa-scale-balanced', children: [
                m('Upto a date', 'accounts-reports-trial-balance-upto-date', 'fa-calendar-check'),
                m('Between two dates', 'accounts-reports-trial-balance-between-dates', 'fa-calendar-week')
            ]},
            m('Yearly Cash Balance', 'accounts-reports-yearly-cash-balance', 'fa-calendar-days')
        ]},
        { label: 'Barcode Reports', icon: 'fa-barcode', children: [
            m('Barcode History', 'barcode-reports-barcode-history', 'fa-clock-rotate-left'),
            m('Barcode List', 'barcode-reports-barcode-list', 'fa-list'),
            m('Comparison Report', 'barcode-reports-barcode-entry-comparison', 'fa-code-compare'),
            m('Diamond Stock', 'barcode-reports-diamond-pres-stone-stock-list', 'fa-gem'),
            m('Sales Amount List', 'barcode-reports-barcode-samt-list', 'fa-list-ul'),
            m('Stock List', 'barcode-reports-barcode-stock-list', 'fa-boxes-stacked'),
            m('Verification Report', 'barcode-reports-stock-verification-report', 'fa-clipboard-check')
        ]},
        m('Cash Balance', 'cash-balance', 'fa-money-bill-wave', 'Ctrl+H'),
        { label: 'Chit Reports', icon: 'fa-list-ul', children: [
            m('Collection Chart', 'kuri-reports-chart', 'fa-chart-line'),
            m('Collection Summary', 'kuri-reports-collection-report', 'fa-money-bill-wave')
        ]},
        { label: 'Customer Analytics', icon: 'fa-users', children: [
            m('Account Summary', 'customers-reports-ac-summary', 'fa-book'),
            m('Billwise Receivables', 'customers-reports-billwise-receivable', 'fa-file-circle-dollar'),
            m('Collection Report', 'customers-reports-bill-collection-details', 'fa-receipt'),
            m('Credit Details', 'customers-reports-credit-bill-details', 'fa-file-invoice'),
            m('Data Check', 'customers-data-check', 'fa-database'),
            m('Customer History', 'customers-reports-party-history', 'fa-clock-rotate-left'),
            m('Customer List', 'customers-reports-customer-list', 'fa-list'),
            m('Customer Summary', 'customers-reports-customer-summary', 'fa-chart-pie'),
            m('Due Date Report', 'customers-reports-duedate-report', 'fa-calendar-check'),
            m('Invoice Details', 'customers-reports-billwise-details', 'fa-list-ul'),
            m('Loyalty History', 'customers-reports-prev-card-point-report', 'fa-credit-card'),
            m('Payment History', 'customers-reports-payment-details', 'fa-money-check-dollar'),
            m('Received Payments', 'customers-reports-received-details', 'fa-list-check'),
            m('Receivables', 'customers-reports-ac-receivable', 'fa-money-bill-wave'),
            m('Sales Summary', 'customers-reports-customer-sales-summary', 'fa-chart-line'),
            m('Visit Tracking', 'customers-reports-visit-report', 'fa-route')
        ]},
        { label: 'Deposit Analytics', icon: 'fa-scale-balanced', children: [
            m('Deposit Ledger', 'party-wgt-deposit-reports-depositer-ledger', 'fa-book'),
            m('Deposit Summary', 'party-wgt-deposit-reports-wgt-amt-summary', 'fa-chart-column'),
            m('Deposit Transactions', 'party-wgt-deposit-reports-transactions', 'fa-right-left')
        ]},
        { label: 'Goldsmith Analytics', icon: 'fa-hammer', children: [
            m('Fixing Status', 'goldsmith-reports-fix-unfix-report', 'fa-wrench'),
            m('Goldsmith Ledger', 'goldsmith-reports-smith-ledger', 'fa-book'),
            m('Goldsmith List', 'goldsmith-reports-smith-list', 'fa-users'),
            m('Lot Tracking', 'goldsmith-reports-lot-no-report', 'fa-hashtag'),
            m('Pending Work Ageing', 'goldsmith-reports-ageing-report', 'fa-hourglass-half'),
            m('Work Summary', 'goldsmith-reports-smith-trans-summary', 'fa-chart-simple'),
            m('Work Transactions', 'goldsmith-reports-transactions', 'fa-right-left'),
            { label: 'Weight & Amount Summary', icon: 'fa-scale-balanced', children: [
                m('Basic View', 'goldsmith-reports-wgt-amt-summary-simple', 'fa-list'),
                m('Detailed View', 'goldsmith-reports-wgt-amt-summary-details', 'fa-list-ul')
            ]}
        ]},
        { label: 'GST & Tax Reports', icon: 'fa-file-invoice', children: [
            m('E-Invoice Register', 'einvoice-register', 'fa-file-circle-check'),
            m('Goldsmith Tax Report', 'tax-reports-smith-book', 'fa-hammer'),
            m('Jewellery Tax Report', 'tax-reports-jewllery-book', 'fa-ring'),
            m('Pending Tax Report', 'tax-reports-outstanding-tax-report', 'fa-file-circle-exclamation'),
            m('Purchase Tax Report', 'tax-reports-purchase-book', 'fa-book-open'),
            m('Sales Tax Report', 'tax-reports-sales-book', 'fa-book'),
            m('Stock Register', 'tax-reports-stock-register', 'fa-boxes-stacked')
        ]},
        { label: 'Inventory Reports', icon: 'fa-box', children: [
            m('Cost List', 'item-reports-cost-list', 'fa-list'),
            m('Item History', 'stock-item-history', 'fa-clock-rotate-left'),
            m('Item Profit', 'item-reports-itemwise-profit', 'fa-chart-pie'),
            m('Model Transfer', 'item-reports-model-transfer-report', 'fa-right-left'),
            m('Rate History', 'item-reports-rate-history', 'fa-timeline'),
            m('Rate Report', 'item-reports-item-rate-report', 'fa-indian-rupee-sign'),
            m('Reorder Alert', 'item-reports-reorder-level', 'fa-arrow-down-short-wide'),
            m('Stock & Weight', 'item-reports-item-stock-party-wgt-report', 'fa-scale-balanced'),
            m('Stock Adjustment', 'item-reports-item-adjustment', 'fa-sliders'),
            m('Stock Change Report', 'item-reports-item-add-less-report', 'fa-plus-minus'),
            m('Stock Movement', 'item-reports-item-movement', 'fa-right-left'),
            m('Stock Register', 'item-reports-stock-reg', 'fa-boxes-stacked'),
            m('Stock Summary', 'item-reports-stock-register-summary', 'fa-clipboard-list'),
            m('Stone Analysis', 'item-reports-stone-trans-analysis', 'fa-gem'),
            m('Transaction Analysis', 'item-reports-trans-ra-report', 'fa-file-lines')
        ]},
        { label: 'Jewellery Analytics', icon: 'fa-ring', children: [
            m('Account Summary', 'jewellery-reports-ac-summary', 'fa-book'),
            m('Email Jewellery Report', 'jewellery-reports-send-mail-to-jewl', 'fa-envelope'),
            m('Extra Charges Report', 'jewellery-reports-extra-amt-report', 'fa-indian-rupee-sign'),
            m('Item Movement Report', 'jewellery-reports-sales-purchase-item-report', 'fa-file-invoice'),
            m('Jewellery Ledger', 'jewellery-reports-jewellery-ledger', 'fa-book'),
            m('Jewellery Summary', 'jewellery-reports-jewl-trans-summary', 'fa-chart-simple'),
            m('Jewellery Transactions', 'jewellery-reports-transactions', 'fa-right-left'),
            m('Profit Analysis', 'jewellery-reports-profit-report', 'fa-chart-pie'),
            m('Stock Ageing', 'jewellery-reports-ageing-report', 'fa-hourglass-half'),
            m('TDS Report', 'jewellery-reports-tds-report', 'fa-file-circle-check'),
            m('Weight Analysis', 'jewellery-reports-wgt-amt-analysis', 'fa-chart-line'),
            m('Weight Summary', 'jewellery-reports-wgt-amt-summary', 'fa-scale-balanced')
        ]},
        { label: 'Order Analytics', icon: 'fa-clipboard-list', children: [
            m('Advance Report', 'order-reports-order-advance-report', 'fa-money-bill-wave'),
            m('Order Entries', 'order-reports-order-entries', 'fa-list'),
            m('Order Numbers', 'order-reports-order-nos-list', 'fa-hashtag'),
            m('Order Processing', 'order-reports-order-process', 'fa-gears', 'Ctrl+P'),
            m('Order Profit', 'order-reports-order-sale-prof-analysis', 'fa-chart-line'),
            m('Order Search', 'order-reports-order-enquiry', 'fa-magnifying-glass'),
            m('Pending Details', 'order-reports-pending-details', 'fa-list-ul'),
            m('Pending Orders', 'order-reports-pending-register', 'fa-clock'),
            m('Returned Orders', 'order-reports-order-returns', 'fa-rotate-left'),
            m('Sample Stock', 'order-reports-sample-stock-register', 'fa-boxes-stacked'),
            m('Send to Head Office', 'order-reports-send-mail-to-ho', 'fa-envelope', 'Ctrl+E')
        ]},
        { label: 'Other Item Reports', icon: 'fa-folder-open', children: [
            m('Purchase Ledger', 'other-item-reports-purchase-book', 'fa-book-open'),
            m('Purchase Register', 'other-item-reports-purchase-register', 'fa-clipboard-check'),
            m('Sales Ledger', 'other-item-reports-sales-book', 'fa-book'),
            m('Sales Register', 'other-item-reports-sales-register', 'fa-clipboard-list'),
            m('Stock Ledger', 'other-item-reports-stock-ledger', 'fa-book'),
            m('Stock List', 'other-item-reports-stock-list', 'fa-list')
        ]},
        { label: 'Other Reports', icon: 'fa-file-lines', children: [
            m('Daily Cash Book', 'other-reports-day-cash-book-report', 'fa-book'),
            m('Daily History', 'other-reports-day-history', 'fa-clock-rotate-left'),
            m('Daily Report', 'other-reports-day-report', 'fa-calendar-day'),
            m('Daily Summary', 'other-reports-daily-all-report', 'fa-print', 'Ctrl+F12'),
            m('Financial Summary', 'other-reports-stock-asset-liablity-expense-report', 'fa-scale-balanced'),
            m('Malayalam Report', 'other-reports-daysummary-malayalam', 'fa-language'),
            m('Month End Report', 'other-reports-month-end-report', 'fa-calendar-check'),
            m('Print All Reports', 'other-reports-all-report-print', 'fa-print'),
            m('Purity Report', 'other-reports-purity-test-report', 'fa-vial'),
            m('Stock Value Report', 'other-reports-stockvalue-profit-report', 'fa-chart-column'),
            m('Term Report', 'other-reports-term-summary-malayalam', 'fa-language')
        ]},
        { label: 'Partner Deposit Reports', icon: 'fa-handshake', children: [
            m('Deposit Ledger', 'partners-deposit-reports-deposit-book', 'fa-book'),
            m('Weight Balance', 'partners-deposit-reports-wgt-balance-summary', 'fa-scale-balanced')
        ]},
        { label: 'Purchase Analytics', icon: 'fa-chart-column', children: [
            m('Credit Purchase Balance', 'supplier-reports-ac-payable', 'fa-money-bill-transfer'),
            m('Purchase Ledger', 'purchase-reports-purchase-book', 'fa-book'),
            m('Purchase Register', 'purchase-reports-purchase-register', 'fa-clipboard-list'),
            m('Purchase Return Register', 'purchase-reports-purchase-return-register', 'fa-clipboard-check'),
            m('Purchase Verification', 'purchase-reports-purchase-check-list', 'fa-list-check')
        ]},
        { label: 'Refinery Analytics', icon: 'fa-fire', children: [
            m('Account Summary', 'refinery-reports-ac-summary', 'fa-book'),
            m('Loss Comparison Report', 'refinery-reports-less-comparison-report', 'fa-scale-balanced'),
            m('Refinery Analysis', 'refinery-reports-analysis', 'fa-magnifying-glass-chart'),
            m('Refinery Summary', 'refinery-reports-refiners-summary', 'fa-chart-pie'),
            m('Refinery Transactions', 'refinery-reports-entry-report', 'fa-file-lines')
        ]},
        { label: 'Repair Analytics', icon: 'fa-recycle', children: [
            m('Pending Repairs', 'remake-reports-remake-pending', 'fa-hourglass-half'),
            m('Repair Reports', 'remake-reports-remake-reports', 'fa-file-lines'),
            m('Returned Repairs', 'remake-reports-remake-returns', 'fa-rotate-left')
        ]},
        { label: 'Sales Analytics', icon: 'fa-chart-line', children: [
            m('Barcode Profit Analysis', 'sales-reports-barcode-profit-report', 'fa-chart-pie'),
            m('Barcode Sales Register', 'sales-reports-barcode-register', 'fa-barcode'),
            m('Delivery Tracking', 'sales-reports-delivery-status-report', 'fa-truck-fast'),
            m('Loyalty Points Report', 'sales-reports-point-card-points', 'fa-credit-card'),
            m('Making Charge Profit', 'sales-reports-mc-profit-report', 'fa-percent'),
            m('Monthly Sales Report', 'sales-reports-monthly-sales', 'fa-calendar-days'),
            m('Net Sales Summary', 'sales-reports-net-sales-report', 'fa-chart-column'),
            m('Salesman Category Report', 'sales-reports-salesman-category', 'fa-user-tag'),
            m('Sales Ledger', 'sales-reports-sales-book', 'fa-book', 'Shift+Alt+R'),
            m('Sales Register', 'sales-reports-sales-register', 'fa-clipboard-list'),
            m('Sales Return Ledger', 'sales-reports-sales-return-book', 'fa-rotate-left'),
            m('Sales Return Register', 'sales-reports-sales-return-register', 'fa-clipboard-check'),
            m('Sales Verification', 'sales-reports-sales-check-list', 'fa-list-check'),
            m('Tagged Items List', 'sales-reports-marked-list', 'fa-list'),
            m('VA Report', 'sales-reports-va-check-report', 'fa-file-lines'),
            m('VA Verification', 'sales-reports-va-check-list', 'fa-list-check')
        ]},
        { label: 'Chitty Management Analytics', icon: 'fa-diagram-project', children: [
            m('Collection Report', 'scheme-reports-scheme-collection', 'fa-money-bill-wave'),
            m('Commission Report', 'scheme-reports-colln-commision-report', 'fa-percent'),
            m('Maturity Report', 'scheme-reports-scheme-maturity', 'fa-calendar-check'),
            m('Scheme Details', 'scheme-reports-scheme-details', 'fa-list-ul'),
            m('Scheme Ledger', 'scheme-reports-scheme-book', 'fa-book')
        ]},
        { label: 'Staff Analytics', icon: 'fa-user-tie', children: [
            m('Activity Log', 'staff-reports-staff-log-report', 'fa-clipboard-check'),
            m('Attendance Report', 'staff-reports-attendance-report', 'fa-user-check'),
            m('Leave Report', 'staff-reports-leave-report', 'fa-calendar-minus'),
            m('Salesman Report', 'staff-reports-sales-man-report', 'fa-user-tag'),
            m('Staff Ledger', 'staff-reports-ledger', 'fa-book'),
            m('Staff Summary', 'staff-reports-summary', 'fa-chart-pie'),
            m('Term Summary', 'staff-reports-term-summary', 'fa-calendar-days'),
            m('Weight Transactions', 'staff-reports-wgt-trans-report', 'fa-weight-scale')
        ]},
        { label: 'Stock Ledger', icon: 'fa-book-open', children: [
            m('Group Ledger', 'stock-period-group', 'fa-layer-group'),
            m('Item Ledger', 'stock-period-item', 'fa-list', 'Shift+Alt+L'),
            m('Subgroup Ledger', 'stock-period-subgroup', 'fa-sitemap')
        ]},
        { label: 'Stock List', icon: 'fa-list', children: [
            m('Group List', 'stock-list-group', 'fa-layer-group'),
            m('Item List', 'stock-list-items', 'fa-box', 'Shift+Alt+T'),
            m('Stock As On Date', 'stock-list-upto-date', 'fa-calendar-check'),
            m('Stock Checklist', 'stock-list-stock-check-list', 'fa-list-check'),
            m('Stock Type', 'stock-list-stock-type-wise', 'fa-tags', 'Ctrl+Alt+T'),
            { label: 'Stock Audit', icon: 'fa-clipboard-check', children: [
                m('Verification Report', 'stock-list-stock-verification-report', 'fa-file-lines'),
                m('Verification Entry', 'stock-list-stock-verification-entry', 'fa-pen-to-square')
            ]}
        ]},
        { label: 'Supplier Analytics', icon: 'fa-truck', children: [
            m('Account Summary', 'supplier-reports-ac-summary', 'fa-book'),
            m('Billwise Payables', 'supplier-reports-billwise-payable', 'fa-file-circle-dollar'),
            m('Due Date Report', 'supplier-reports-duedate-report', 'fa-calendar-check'),
            m('Payables', 'supplier-reports-ac-payable', 'fa-money-bill-transfer'),
            m('Supplier List', 'supplier-reports-supplier-list', 'fa-list')
        ]},
        m('Term Report', 'term-summary', 'fa-calendar-days', 'Ctrl+T'),
        { label: 'GST Reports', icon: 'fa-file-invoice-dollar', children: [
            m('GSTR-1 / GSTR-3B / HSN', 'gstr-reports', 'fa-file-invoice-dollar'),
        ]},
        { label: 'Branch Reports', icon: 'fa-building', children: [
            m('Transfer Report', 'branch-transfer-report', 'fa-chart-bar'),
        ]},
    ]},
    { label: 'Accounts Reports', items: [
        m('Day Book', 'day-book', 'fa-calendar-day'),
        m('Ledger', 'ledger', 'fa-book'),
        m('Trial Balance', 'trial-balance', 'fa-scale-balanced'),
        m('Profit & Loss', 'profit-loss', 'fa-chart-pie'),
        m('Balance Sheet', 'balance-sheet', 'fa-file-invoice-dollar')
    ]},
    { label: 'Sales Reports', items: [
        m('Daily Sales', 'daily-sales', 'fa-calendar-day'),
        m('Monthly Sales', 'monthly-sales', 'fa-calendar-days'),
        m('Sales Register', 'sales-register', 'fa-list-check')
    ]},
    { label: 'Scheme Collection', action: function() { openModule('scheme-collection', 'Scheme Collection'); } },
    { label: 'Day summary', action: function() { openModule('day-summary', 'Day summary'); } },
    { label: 'Gold Rate Update', action: function() { openModule('gold-rate-update', 'Gold Rate Update'); } },
    { label: 'Re ConnectFS', action: function() { openModule('re-connectfs', 'Re ConnectFS'); } },
    { label: 'Tools', items: [
        m('AI Analytics', 'ai-insights', 'fa-robot'),
        m('Contact Directory', 'phone-book', 'fa-address-book'),
        m('Data Backup & Restore', 'backup', 'fa-database'),
        m('Local Backup', 'local-backup', 'fa-hard-drive'),
        m('Data Transfer', 'data-transfer', 'fa-right-left'),
        m('Financial Year Closing', 'year-end-account-close', 'fa-calendar-check'),
        m('System Administration', 'administration', 'fa-user-gear'),
        m('Task Reminders', 'reminder', 'fa-bell'),
        { label: 'WhatsApp / SMS', icon: 'fa-comment-dots', children: [
            m('Gateway Settings', 'whatsapp-gateway/settings', 'fa-gear'),
            m('Message Log', 'whatsapp-gateway/log', 'fa-inbox'),
        ]},
        m('Tally Export', 'tally-export', 'fa-file-export'),
        m('Scale Settings', 'scale-settings', 'fa-weight-scale'),
    ]},
    { label: 'Backup Every Day', action: function() { openModule('backup-every-day', 'Backup Every Day'); } },

];

const userPermissions = @json($userPermissions ?? []);
const hasRestrictedMenuAccess = @json($hasRestrictedMenuAccess ?? false);
const routePermissionPatterns = @json(\App\Enums\Permission::routePatternsForUi());
const appBasePath = @json(trim((string) parse_url(url('/'), PHP_URL_PATH), '/'));
const userPermissionSet = new Set((userPermissions || []).map(function(permission) {
    return String(permission || '').trim().toUpperCase();
}));
['COMPANYSELECT', 'COMPANY_SELECT', 'MDI_COMPANYSELECT'].forEach(function(alias) {
    if (userPermissionSet.has(alias)) {
        userPermissionSet.add('MDI_COMPANY_SELECT');
    }
});

const reportPermissionKeys = new Set([
    'MDI_DAYBOOK',
    'MDI_DAY_SUMMARY',
    'MDI_FINANCIAL_STATEMENTS',
    'MDI_SALES_BOOK_REPORT',
    'MDI_MONTHLY_SALES_REPORT',
    'MDI_SALES_REGISTER',
    'MDI_SALESMAN_CATEGORY_REPORT',
    'MDI_SALES_CHECK_LIST',
    'MDI_SALES_RETURN_REGISTER',
    'MDI_NET_SALES_REPORT',
    'MDI_DELIVERY_STATUS_REPORT',
    'MDI_POINT_CARD_POINTS_REPORT',
    'MDI_PURCHASE_BOOK',
    'MDI_PURCHASE_REGISTER',
    'MDI_PURCHASE_RETURN_REGISTER',
    'MDI_PURCHASE_CHECK_LIST',
    'MDI_TAX_PURCHASE_BOOK',
    'MDI_STOCK_LEDGER',
    'MDI_STOCK_PERIOD_LEDGER',
    'MDI_STOCK_ITEM_HISTORY',
    'MDI_STOCK_REGISTER',
    'MDI_STOCK_SUMMARY_COST_WISE',
    'MDI_STOCK_SUMMARY_RATE_WISE',
    'MDI_BARCODE_REGISTER',
    'MDI_BARCODE_PROFIT',
    'MDI_SMITH_BOOK',
    'MDI_JEWLLERY_BOOK',
    'MDI_MARKED_LIST',
    'MDI_MC_PROFIT',
    'MDI_VA_CHECK_LIST',
    'MDI_VA_CHECK_REPORT',
    'MDI_OUTSTANDING_TAX',
    'MDI_FINANCIAL_REPORTS',
    'MDI_BARCODE_REPORTS',
    'MDI_CUSTOMER_ANALYTICS',
    'MDI_DEPOSIT_ANALYTICS',
    'MDI_GOLDSMITH_ANALYTICS',
    'MDI_GST_TAX_REPORTS',
    'MDI_INVENTORY_REPORTS',
    'MDI_JEWELLERY_ANALYTICS',
    'MDI_ORDER_ANALYTICS',
    'MDI_OTHER_ITEM_REPORTS',
    'MDI_OTHER_REPORTS',
    'MDI_CHITTY_ANALYTICS',
    'MDI_STAFF_ANALYTICS',
    'MDI_SUPPLIER_ANALYTICS',
    'MDI_REFINERY_ANALYTICS',
    'MDI_REPAIR_ANALYTICS'
]);

const operationsPriorityOrder = [
    'Sales Invoice',
    'Purchase Invoice',
    'Sales Return',
    'Purchase Return',
    'Order Management',
    'Diamond & Stone Entry',
    'Goldsmith Work',
    'Jewellery Processing',
    'Accounts Transactions',
    'Barcode Management',
    'Staff Management',
    'Other Item Transactions',
    'Refinery Operations',
    'Customer Greetings',
    'Customer Weight Deposit',
    'Repair & Remake',
    'Stock Management',
    'Chitty Management',
];

const reportsPriorityOrder = [
    'Sales Analytics',
    'Purchase Analytics',
    'Order Analytics',
    'Diamond Reports',
    'Goldsmith Analytics',
    'Jewellery Analytics',
    'Financial Reports',
    'Barcode Reports',
    'Stock Ledger',
    'Stock List',
    'Inventory Reports',
    'Chitty Management Analytics',
    'Staff Analytics',
    'Refinery Analytics',
    'Repair Analytics',
];

(function applyOperationsPriority() {
    var transactionsSpec = menuSpec.find(function(spec) { return spec && spec.label === 'Transactions'; });
    if (!transactionsSpec || !Array.isArray(transactionsSpec.items)) {
        return;
    }

    var priorityMap = {};
    operationsPriorityOrder.forEach(function(label, index) {
        priorityMap[label] = index;
    });

    transactionsSpec.items = transactionsSpec.items
        .map(function(item, index) {
            var label = item && item.label ? item.label : '';
            return {
                item: item,
                originalIndex: index,
                priority: Object.prototype.hasOwnProperty.call(priorityMap, label)
                    ? priorityMap[label]
                    : operationsPriorityOrder.length + index,
            };
        })
        .sort(function(a, b) {
            if (a.priority !== b.priority) return a.priority - b.priority;
            return a.originalIndex - b.originalIndex;
        })
        .map(function(entry) { return entry.item; });
})();

(function applyReportsPriority() {
    var reportsSpec = menuSpec.find(function(spec) { return spec && spec.label === 'Reports'; });
    if (!reportsSpec || !Array.isArray(reportsSpec.items)) {
        return;
    }

    var priorityMap = {};
    reportsPriorityOrder.forEach(function(label, index) {
        priorityMap[label] = index;
    });

    reportsSpec.items = reportsSpec.items
        .map(function(item, index) {
            var label = item && item.label ? item.label : '';
            return {
                item: item,
                originalIndex: index,
                priority: Object.prototype.hasOwnProperty.call(priorityMap, label)
                    ? priorityMap[label]
                    : reportsPriorityOrder.length + index,
            };
        })
        .sort(function(a, b) {
            if (a.priority !== b.priority) return a.priority - b.priority;
            return a.originalIndex - b.originalIndex;
        })
        .map(function(entry) { return entry.item; });
})();

/**
 * ============================================================
 * Top Menu Label Remapping and Ordering
 * ============================================================
 */
const topMenuLabelMap = {
    'File':           'System',
    'Registration':   'Masters',
    'Transactions':   'Operations',
    'Tools':          'System Tools',
    'Gold Rate Update': 'Rate Update'
};

const primaryTopMenuOrder = ['System', 'Masters', 'Operations', 'Reports', 'System Tools', 'Rate Update'];
const topMenuOrder = primaryTopMenuOrder.concat(['Other Menu Items']);

function resolveOtherTopMenuSpec() {
    var usedLabels = new Set(primaryTopMenuOrder);
    var otherItems = [];

    menuSpec.forEach(function(spec) {
        if (!spec || !spec.label) return;
        var mappedLabel = topMenuLabelMap[spec.label] || spec.label;
        if (usedLabels.has(mappedLabel)) return;
        (spec.items || []).forEach(function(item) {
            if (item) otherItems.push(item);
        });
    });

    return otherItems.length ? { label: 'Other Menu Items', items: otherItems } : null;
}

function resolveTopMenuSpec(label) {
    if (label === 'Other Menu Items') {
        return resolveOtherTopMenuSpec();
    }

    for (var i = 0; i < menuSpec.length; i += 1) {
        var spec = menuSpec[i];
        if (!spec || !spec.label) continue;

        var mappedLabel = topMenuLabelMap[spec.label] || spec.label;
        if (mappedLabel !== label) {
            continue;
        }

        var nextSpec = mappedLabel === spec.label ? Object.assign({}, spec) : Object.assign({}, spec, { label: mappedLabel });
        if (nextSpec.label === 'System') {
            nextSpec.items = (nextSpec.items || []).slice();
            if (companySelectSecretUnlocked && !nextSpec.items.some(function(item) { return item && item.id === 'company-select'; })) {
                nextSpec.items.splice(1, 0, m('Company Select', 'company-select', 'fa-building', 'Ctrl+Shift+Z'));
            }
        }
        return nextSpec;
    }

    return null;
}

function getTopMenuSpec() {
    return topMenuOrder
        .map(function(label) { return resolveTopMenuSpec(label); })
        .map(filterMenuNode)
        .filter(Boolean);
}

/**
 * ============================================================
 * Helper: menu item shorthand
 * ============================================================
 */
function m(label, id, icon, key) {
    return { label: label, id: id, icon: icon, key: key };
}

function collectModuleIds(nodes, target) {
    (nodes || []).forEach(function(node) {
        if (!node || typeof node !== 'object') {
            return;
        }
        if (node.id) {
            target.add(node.id);
        }
        if (Array.isArray(node.items)) {
            collectModuleIds(node.items, target);
        }
        if (Array.isArray(node.children)) {
            collectModuleIds(node.children, target);
        }
    });
}

function findMenuSpecByLabel(label) {
    return (menuSpec || []).find(function(spec) {
        return spec && spec.label === label;
    }) || null;
}

const reportModuleIds = (function buildReportModuleIds() {
    var ids = new Set();
    var reportsSpec = findMenuSpecByLabel('Reports');
    if (reportsSpec && Array.isArray(reportsSpec.items)) {
        collectModuleIds(reportsSpec.items, ids);
    }
    return ids;
})();

const reportCategoryPermissionByLabel = {
    'Financial Reports': 'MDI_FINANCIAL_REPORTS',
    'Barcode Reports': 'MDI_BARCODE_REPORTS',
    'Customer Analytics': 'MDI_CUSTOMER_ANALYTICS',
    'Deposit Analytics': 'MDI_DEPOSIT_ANALYTICS',
    'Goldsmith Analytics': 'MDI_GOLDSMITH_ANALYTICS',
    'GST & Tax Reports': 'MDI_GST_TAX_REPORTS',
    'Inventory Reports': 'MDI_INVENTORY_REPORTS',
    'Jewellery Analytics': 'MDI_JEWELLERY_ANALYTICS',
    'Order Analytics': 'MDI_ORDER_ANALYTICS',
    'Other Item Reports': 'MDI_OTHER_ITEM_REPORTS',
    'Other Reports': 'MDI_OTHER_REPORTS',
    'Chitty Management Analytics': 'MDI_CHITTY_ANALYTICS',
    'Staff Analytics': 'MDI_STAFF_ANALYTICS',
    'Supplier Analytics': 'MDI_SUPPLIER_ANALYTICS',
    'Refinery Analytics': 'MDI_REFINERY_ANALYTICS',
    'Repair Analytics': 'MDI_REPAIR_ANALYTICS'
};

const reportCategoryModulePermissions = (function buildReportCategoryModulePermissions() {
    var map = {};
    var reportsSpec = findMenuSpecByLabel('Reports');
    if (!reportsSpec || !Array.isArray(reportsSpec.items)) {
        return map;
    }

    function collectIds(nodes, ids) {
        (nodes || []).forEach(function(node) {
            if (!node || typeof node !== 'object') return;
            if (node.id) ids.push(node.id);
            if (Array.isArray(node.items)) collectIds(node.items, ids);
            if (Array.isArray(node.children)) collectIds(node.children, ids);
        });
    }

    reportsSpec.items.forEach(function(section) {
        var permission = reportCategoryPermissionByLabel[String(section && section.label || '')];
        if (!permission) return;
        var ids = [];
        collectIds([section], ids);
        ids.forEach(function(id) { map[id] = permission; });
    });

    return map;
})();

const restrictedActionModuleIds = (function buildRestrictedActionModuleIds() {
    var ids = new Set();

    function visit(nodes) {
        (nodes || []).forEach(function(node) {
            if (!node || typeof node !== 'object') {
                return;
            }

            var label = String(node.label || '').toLowerCase();
            var id = String(node.id || '').toLowerCase();
            if (
                node.id && (
                    /\b(edit|cancel)\b/.test(label) ||
                    /(?:^|[-])(edit|cancel)(?:[-]|$)/.test(id)
                )
            ) {
                ids.add(node.id);
            }

            if (Array.isArray(node.items)) {
                visit(node.items);
            }
            if (Array.isArray(node.children)) {
                visit(node.children);
            }
        });
    }

    var transactionsSpec = findMenuSpecByLabel('Transactions');
    if (transactionsSpec && Array.isArray(transactionsSpec.items)) {
        visit(transactionsSpec.items);
    }

    return ids;
})();

function hasAnyReportPermission() {
    var hasPermission = false;
    reportPermissionKeys.forEach(function(permission) {
        if (!hasPermission && userPermissionSet.has(permission)) {
            hasPermission = true;
        }
    });
    return hasPermission;
}

function moduleIsBlockedByUserFlags(moduleId) {
    if (!moduleId) {
        return false;
    }

    if (!userPermissionSet.has('READONLY') && !userPermissionSet.has('ONLINEEDIT')) {
        return false;
    }

    return restrictedActionModuleIds.has(moduleId);
}

function normalizeModulePath(url) {
    if (!url) return '';

    try {
        var parsed = new URL(url, window.location.origin);
        var path = String(parsed.pathname || '').replace(/^\/+|\/+$/g, '');
        if (appBasePath && path.indexOf(appBasePath + '/') === 0) {
            path = path.slice(appBasePath.length + 1);
        } else if (appBasePath && path === appBasePath) {
            path = '';
        }
        return path;
    } catch (err) {
        return String(url).replace(/^\/+|\/+$/g, '');
    }
}

function permissionForModule(moduleId) {
    var modulePermissionOverrides = {
        'sales-bill': 'MDI_SALES_BILL_NEW',
        'sales-bill-edit': 'MDI_SALES_BILL_EDIT',
        'sales-bill-cancel': 'MDI_SALES_BILL_CANCEL',
        'sales-bill-quotation': 'MDI_SALES_QUOTATION',
        'sales-bill-quotation-edit': 'MDI_SALES_QUOTATION_EDIT',
        'sales-bill-quotation-cancel': 'MDI_SALES_QUOTATION_CANCEL',
        'sales-bill-reprint': 'MDI_SALES_BILL_PRINT',
        'sales-bill-confirmation': 'MDI_SALES_BILL_CONFIRMATION',
        'customer': 'MDI_CUSTOMER',
        'supplier': 'MDI_SUPPLIER_MASTER',
        'staff-adding': 'MDI_STAFF_MASTER',
        'goldsmith-add': 'MDI_GOLDSMITH_MASTER',
        'refiner-add': 'MDI_REFINER_MASTER',
        'jewellery-add': 'MDI_JEWELLERY_MASTER',
        'depositors-wgt': 'MDI_DEPOSITOR_MASTER',
        'depositers-wgt': 'MDI_DEPOSITOR_MASTER',
        'depositor-wgt': 'MDI_DEPOSITOR_MASTER',
        'sales-man': 'MDI_SALESMAN_MASTER',
        'jewellery-bill': 'MDI_JEWELLERY_TRANSACTIONS',
        'jewellery-bill-edit': 'MDI_JEWELLERY_TRANSACTIONS',
        'jewellery-bill-cancel': 'MDI_JEWELLERY_TRANSACTIONS',
        'jewellery-bill-reprint': 'MDI_JEWELLERY_TRANSACTIONS',
        'jewellery-bill-update-from-ho': 'MDI_JEWELLERY_TRANSACTIONS',
        'party-wgt-deposit-bill': 'MDI_PARTY_WGT_DEPOSIT',
        'party-wgt-deposit-bill-edit': 'MDI_PARTY_WGT_DEPOSIT',
        'party-wgt-deposit-bill-cancel': 'MDI_PARTY_WGT_DEPOSIT',
        'party-wgt-deposit-bill-reprint': 'MDI_PARTY_WGT_DEPOSIT',
        'party-wgt-deposit-bill-interest-posting': 'MDI_PARTY_WGT_DEPOSIT',
        'repair-slip-return-customer': 'MDI_REPAIR_SMITH_MEMO',
        'repair-slip-return-customer-edit': 'MDI_REPAIR_SMITH_MEMO',
        'repair-slip-return-customer-cancel': 'MDI_REPAIR_SMITH_MEMO',
        'repair-slip-return-customer-reprint': 'MDI_REPAIR_SMITH_MEMO',
        'remake-issue-memo-to-smith': 'MDI_REPAIR_SMITH_MEMO',
        'remake-issue-memo-to-smith-edit': 'MDI_REPAIR_SMITH_MEMO',
        'remake-issue-memo-to-smith-cancel': 'MDI_REPAIR_SMITH_MEMO',
        'remake-issue-memo-to-smith-reprint': 'MDI_REPAIR_SMITH_MEMO',
        'remake-rcpt-memo-from-smith': 'MDI_REPAIR_SMITH_MEMO',
        'remake-rcpt-memo-from-smith-edit': 'MDI_REPAIR_SMITH_MEMO',
        'remake-rcpt-memo-from-smith-cancel': 'MDI_REPAIR_SMITH_MEMO',
        'remake-rcpt-memo-from-smith-reprint': 'MDI_REPAIR_SMITH_MEMO',
        'remake-issue-memo-to-party': 'MDI_REPAIR_ISSUE_MEMO_PARTY',
        'remake-issue-memo-to-party-edit': 'MDI_REPAIR_ISSUE_MEMO_PARTY',
        'remake-issue-memo-to-party-cancel': 'MDI_REPAIR_ISSUE_MEMO_PARTY',
        'remake-issue-memo-to-party-reprint': 'MDI_REPAIR_ISSUE_MEMO_PARTY',
        'einvoice-register': 'MDI_EINVOICE_REGISTER',
        'country-currency': 'MDI_APPLICATION_SETTINGS',
        'company-select': 'MDI_COMPANY_SELECT',
        'reminder': 'MDI_TASK_REMINDERS',
        'task-reminders': 'MDI_TASK_REMINDERS',
        'customers-reports-ac-summary': 'MDI_CUSTOMER_ANALYTICS',
        'supplier-reports-ac-summary': 'MDI_SUPPLIER_ANALYTICS',
        'jewellery-reports-ac-summary': 'MDI_JEWELLERY_ANALYTICS',
        'jewellery-reports-ageing-report': 'MDI_JEWELLERY_ANALYTICS',
        'jewellery-reports-jewl-trans-summary': 'MDI_JEWELLERY_ANALYTICS',
        'jewellery-reports-wgt-amt-analysis': 'MDI_JEWELLERY_ANALYTICS',
        'jewellery-reports-wgt-amt-summary': 'MDI_JEWELLERY_ANALYTICS',
        'data-transfer': 'MDI_ADMINISTRATION'
    };

    if (modulePermissionOverrides[moduleId]) {
        return modulePermissionOverrides[moduleId];
    }

    if (reportCategoryModulePermissions[moduleId]) {
        return reportCategoryModulePermissions[moduleId];
    }

    var cfg = moduleConfigMap[moduleId];
    if (!cfg || !cfg.url) {
        return null;
    }

    var path = normalizeModulePath(cfg.url);
    if (!path) {
        return null;
    }

    var patterns = routePermissionPatterns || {};
    for (var pattern in patterns) {
        if (!Object.prototype.hasOwnProperty.call(patterns, pattern)) continue;
        var normalizedPattern = String(pattern || '').replace(/^\/+|\/+$/g, '');
        var regex = new RegExp('^' + normalizedPattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\\\*/g, '.*') + '$', 'i');
        if (regex.test(path)) {
            return patterns[pattern];
        }
    }

    return null;
}

function moduleIsAllowed(moduleId) {
    if (disabledModuleIds.has(moduleId)) {
        return false;
    }

    if (!moduleId || !hasRestrictedMenuAccess) {
        return true;
    }

    if (moduleIsBlockedByUserFlags(moduleId)) {
        return false;
    }

    if (reportModuleIds.has(moduleId) && !hasAnyReportPermission()) {
        return false;
    }

    var permission = permissionForModule(moduleId);
    if (!permission) {
        return false;
    }

    return userPermissionSet.has(permission);
}

function filterMenuNode(node) {
    if (!node) {
        return null;
    }

    if (node.id && disabledModuleIds.has(node.id)) {
        return null;
    }

    if (node.id && !moduleIsAllowed(node.id)) {
        return null;
    }

    var nextNode = Object.assign({}, node);

    if (Array.isArray(node.items)) {
        nextNode.items = node.items.map(filterMenuNode).filter(Boolean);
        if (!nextNode.items.length && !nextNode.action) {
            return null;
        }
    }

    if (Array.isArray(node.children)) {
        nextNode.children = node.children.map(filterMenuNode).filter(Boolean);
        if (!nextNode.children.length && !nextNode.id && !nextNode.action) {
            return null;
        }
    }

    return nextNode;
}

function extractModuleIdFromHandler(handlerText) {
    var match = String(handlerText || '').match(/openModule\(\s*'([^']+)'/);
    return match ? match[1] : '';
}

function pruneDashboardShortcuts() {
    document.querySelectorAll('[onclick]').forEach(function(el) {
        var moduleId = extractModuleIdFromHandler(el.getAttribute('onclick'));
        if (moduleId && !moduleIsAllowed(moduleId)) {
            el.remove();
        }
    });

    document.querySelectorAll('[data-requires-module]').forEach(function(el) {
        if (!moduleIsAllowed(el.getAttribute('data-requires-module'))) {
            el.remove();
        }
    });

    var nav = document.querySelector('.sb-nav');
    if (!nav) {
        return;
    }

    Array.prototype.slice.call(nav.children).forEach(function(child) {
        if (!child.classList || !child.classList.contains('sb-section-label')) {
            return;
        }

        var next = child.nextElementSibling;
        var hasVisibleLinks = false;
        while (next && (!next.classList || !next.classList.contains('sb-section-label'))) {
            if (next.classList && next.classList.contains('sb-link')) {
                hasVisibleLinks = true;
                break;
            }
            next = next.nextElementSibling;
        }

        if (!hasVisibleLinks) {
            child.remove();
        }
    });
}

function alignSubmenu(row, submenu) {
    if (!row || !submenu) return;
    submenu.style.top = '0px';
    submenu.style.left = '100%';
    submenu.style.right = 'auto';

    var rect = submenu.getBoundingClientRect();
    var top = 0;
    var margin = 8;

    if (rect.right > (window.innerWidth - margin)) {
        submenu.style.left = 'auto';
        submenu.style.right = '100%';
        rect = submenu.getBoundingClientRect();
    }

    if (rect.left < margin) {
        submenu.style.left = '100%';
        submenu.style.right = 'auto';
        rect = submenu.getBoundingClientRect();
    }

    var overflowBottom = rect.bottom - (window.innerHeight - margin);
    if (overflowBottom > 0) {
        top -= overflowBottom;
    }

    var nextTop = rect.top + top;
    if (nextTop < margin) {
        top += (margin - nextTop);
    }

    submenu.style.top = Math.round(top) + 'px';
}


/**
 * ============================================================
 * Build Menu Bar
 * ============================================================
 */
function buildMenu() {
    var menuBar = document.getElementById('menuBar');
    if (!menuBar) return;
    menuBar.innerHTML = '';

    var specs = [];
    try {
        specs = getTopMenuSpec();
    } catch (err) {
        console.error('goldapp menu build failed', err);
        return;
    }

    specs.forEach(function(spec) {
        if (!spec || !spec.label) {
            return;
        }
        var item = document.createElement('div');
        item.className = 'menu-item';
        item.dataset.menuLabel = spec.label;

        if (spec.items) {
            item.classList.add('has-dropdown');
            var labelSpan = document.createElement('span');
            labelSpan.className = 'menu-heading-text';
            labelSpan.textContent = spec.label;
            var headingArrow = document.createElement('i');
            headingArrow.className = 'fas fa-chevron-down menu-heading-arrow';
            item.append(labelSpan, headingArrow);

            var dd = document.createElement('div');
            dd.className = 'dropdown';
            if (spec.layout === 'grid2') {
                dd.classList.add('registration-menu');
            }
            spec.items.forEach(function(sub) { dd.appendChild(buildDdItem(sub)); });
            // use 2-column layout when there are many items so nothing is cut off
            if (spec.items.length > 12) {
                dd.classList.add('dd-cols-2');
            }
            item.appendChild(dd);

            item.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = dd.classList.contains('show');
                closeAllMenus();
                if (!isOpen) {
                    item.classList.add('active');
                    dd.classList.add('show');
                }
            });

            item.addEventListener('mouseenter', function() {
                if (!document.querySelector('.dropdown.show')) {
                    return;
                }
                closeAllMenus();
                item.classList.add('active');
                dd.classList.add('show');
            });
        } else {
            item.textContent = spec.label;
            item.addEventListener('click', function() { spec.action && spec.action(); });
        }

        menuBar.appendChild(item);
    });
}

/**
 * ============================================================
 * Build Dropdown Item (recursive for submenus)
 * ============================================================
 */
function buildDdItem(def) {
    if (!def || typeof def !== 'object') {
        var emptyRow = document.createElement('div');
        emptyRow.className = 'dd-item';
        emptyRow.textContent = '...';
        return emptyRow;
    }

    var row = document.createElement('div');
    row.className = 'dd-item';

    var left = document.createElement('span');
    left.className = 'dd-left';
    var icon = document.createElement('i');
    icon.className = 'fas ' + (def.icon || 'fa-circle');
    var text = document.createElement('span');
    text.textContent = def.label;
    left.append(icon, text);

    row.appendChild(left);

    if (Array.isArray(def.children) && def.children.length) {
        var arrow = document.createElement('i');
        arrow.className = 'fas fa-chevron-right';
        row.appendChild(arrow);
        var sm = document.createElement('div');
        sm.className = 'submenu';
        def.children.filter(Boolean).forEach(function(ch) { sm.appendChild(buildDdItem(ch)); });
        row.appendChild(sm);
        row.addEventListener('mouseenter', function() { alignSubmenu(row, sm); });

    } else {
        if (def.key) {
            var keySpan = document.createElement('span');
            keySpan.className = 'key';
            keySpan.textContent = def.key;
            row.appendChild(keySpan);
        }
        row.addEventListener('click', function(e) {
            e.stopPropagation();
            closeAllMenus();
            if (def.action) {
                def.action();
                return;
            }
            openModule(def.id, def.label);
        });
    }

    return row;
}

/**
 * ============================================================
 * Close All Menus
 * ============================================================
 */
function closeAllMenus() {
    document.querySelectorAll('.dropdown.show').forEach(function(el) { el.classList.remove('show'); });
    document.querySelectorAll('.menu-item.active').forEach(function(el) { el.classList.remove('active'); });
}

// Close the top-menu dropdowns on any click outside the menu bar, on Escape,
// or when a module iframe reports a click inside it. Inline so it runs
// regardless of APP_URL / the external asset host.
(function initMenuAutoClose() {
    document.addEventListener('click', function(e) {
        if (e.target && e.target.closest && e.target.closest('#menuBar')) return;
        closeAllMenus();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) closeAllMenus();
    });
    window.addEventListener('message', function(e) {
        if (e.data && e.data.type === 'goldapp:close-menus') closeAllMenus();
    });
})();

/**
 * ============================================================
 * Open Module
 * ============================================================
 */
var passwordProtectedModules = new Set([
    'einvoice-register'
]);
var passwordProtectedUnlocked = new Set();

function openModule(moduleId, title) {
    if (disabledModuleIds.has(moduleId)) {
        return;
    }

    if (!moduleIsAllowed(moduleId)) {
        return;
    }

    if (passwordProtectedModules.has(moduleId) && !passwordProtectedUnlocked.has(moduleId)) {
        var pwd = window.prompt('Admin password required to open ' + (title || moduleLabelFromId(moduleId)) + ':');
        if (pwd === null) return;
        if (String(pwd).trim().toUpperCase() !== 'ADMIN') {
            alert('Invalid admin password.');
            return;
        }
        passwordProtectedUnlocked.add(moduleId);
    }

    closeAllMenus();
    // Close mobile sidebar if open
    const sb = document.getElementById('mobileSidebar');
    const ov = document.getElementById('mobOverlay');
    if (sb) { sb.classList.remove('mob-open'); }
    if (ov) { ov.classList.remove('show'); }

    var cfg = moduleConfigMap[moduleId] || resolveModuleConfigFallback(moduleId) || { mode: 'inline', url: 'app://module/' + moduleId };
    openInDashboardFrame(moduleId, title || moduleLabelFromId(moduleId), cfg);
}

function resolveModuleConfigFallback(moduleId) {
    var fallbackMap = {
        'order-reports-order-entries': { mode: 'iframe', url: '{{ url("/order-entry-report") }}' }
    };
    return fallbackMap[moduleId] || null;
}


/**
 * ============================================================
 * Open In Dashboard Frame (full-screen iframe overlay)
 * ============================================================
 */
function openInDashboardFrame(moduleId, title, cfg) {
    if (typeof openInDashboardFrameNew === 'function') {
        return openInDashboardFrameNew(moduleId, title, cfg);
    }
    var frameScreen = document.getElementById('moduleFrameScreen');
    var frame = document.getElementById('moduleFrame');
    var frameTitle = document.getElementById('moduleFrameTitle');
    var framePath = document.getElementById('moduleFramePath');
    var targetUrl = withCompanyContext(cfg.url || ('app://module/' + moduleId));

    activeDashboardModuleId = moduleId;
    frameTitle.textContent = title;
    framePath.textContent = targetUrl;
    framePath.title = targetUrl;
    document.getElementById('moduleFrameOpenTab').disabled = cfg.mode !== 'iframe';
    setTopbarPrimaryAction(moduleId === 'company-select' ? 'company-close' : 'default');
    wireIframeCloseFallback(frame);

    if (cfg.mode === 'iframe') {
        frame.src = targetUrl;
    } else {
        var html = '<!doctype html><html><head><meta charset="utf-8"><title>' + escapeHtml(title) + '</title>' +
            '<style>body{font-family:Segoe UI,Tahoma,sans-serif;margin:0;padding:28px;background:#f4f7fb;color:#213548}' +
            '.card{max-width:760px;margin:0 auto;background:#fff;border:1px solid #d7e2ef;border-radius:10px;padding:18px}' +
            'h2{margin:0 0 8px;font-size:22px} p{margin:6px 0;font-size:14px}</style></head>' +
            '<body><div class="card"><h2>' + escapeHtml(title) + '</h2><p>Module key: <strong>' + escapeHtml(moduleId) + '</strong></p>' +
            '<p>This module page is not implemented yet.</p></div></body></html>';
        frame.src = 'data:text/html;charset=utf-8,' + encodeURIComponent(html);
    }

    var homeScreen = document.getElementById('homeScreen');
    if (homeScreen) homeScreen.style.display = 'none';
    frameScreen.style.display = 'flex';
    frameScreen.classList.add('show-frame');
}

/**
 * ============================================================
 * Popup Window System (MDI floating windows)
 * ============================================================
 */
var popupCount = 0;
var popupZ = 2600;

function openPopupWindow(moduleId, title, cfg) {
    popupCount += 1;
    var mdi = document.getElementById('mdiArea');
    var win = document.createElement('section');
    win.className = 'window active';
    win.style.display = 'block';

    var popupSizes = {
        'staff-adding': { w: 860, h: 560 },
        'customer': { w: 820, h: 540 },
        'supplier': { w: 820, h: 540 },
        'goldsmith-add': { w: 820, h: 540 },
        'refiner-add': { w: 820, h: 540 },
        'jewellery-add': { w: 820, h: 540 }
    };
    var size = popupSizes[moduleId] || { w: 760, h: 500 };
    win.style.width = size.w + 'px';
    win.style.height = size.h + 'px';

    var offset = (popupCount % 8) * 22;
    var left = Math.max(20, (mdi.clientWidth - size.w) / 2 + offset);
    var top = Math.max(20, (mdi.clientHeight - size.h) / 2 + offset);
    win.style.left = left + 'px';
    win.style.top = top + 'px';
    win.style.zIndex = String(++popupZ);

    win.innerHTML =
        '<div class="window-header">' +
            '<div class="window-title"><i class="fas fa-window-maximize"></i><span>' + escapeHtml(title) + '</span></div>' +
            '<div class="controls">' +
                (cfg.mode === 'iframe' ? '<button class="wbtn" data-open-tab title="Open in new tab"><i class="fas fa-up-right-from-square"></i></button>' : '') +
                '<button class="wbtn" data-close title="Close"><i class="fas fa-times"></i></button>' +
            '</div>' +
        '</div>' +
        '<div class="window-toolbar">' +
            '<i class="fas fa-check"></i>' +
            '<i class="fas fa-pen"></i>' +
            '<i class="fas fa-copy"></i>' +
            '<i class="fas fa-arrows-rotate"></i>' +
            '<i class="fas fa-calculator"></i>' +
            '<span>' + escapeHtml(title) + '</span>' +
            '<span class="window-url" title="' + escapeHtml(withCompanyContext(cfg.url || '')) + '">' + escapeHtml(withCompanyContext(cfg.url || '')) + '</span>' +
        '</div>' +
        '<div class="window-content">' +
            (cfg.mode === 'iframe' ? '<iframe class="module-iframe" src="' + escapeHtml(withCompanyContext(cfg.url)) + '"></iframe>' : buildModuleMarkup(moduleId, title)) +
        '</div>';

    mdi.appendChild(win);
    bindPopupWindow(win);
    if (cfg.mode !== 'iframe') {
        initModuleContent(win, moduleId, title);
    }
}

/**
 * ============================================================
 * Bind Popup Window (drag + close)
 * ============================================================
 */
function bindPopupWindow(win) {
    var header = win.querySelector('.window-header');
    var closeBtn = win.querySelector('[data-close]');
    var openTabBtn = win.querySelector('[data-open-tab]');

    var bringFront = function() {
        win.style.zIndex = String(++popupZ);
    };
    win.addEventListener('mousedown', bringFront);

    var drag = false;
    var dx = 0;
    var dy = 0;

    header.addEventListener('mousedown', function(e) {
        if (e.target.closest('button')) {
            return;
        }
        drag = true;
        dx = e.clientX - win.offsetLeft;
        dy = e.clientY - win.offsetTop;
        bringFront();
        document.body.style.userSelect = 'none';
    });

    document.addEventListener('mousemove', function(e) {
        if (!drag) {
            return;
        }
        var mdi = document.getElementById('mdiArea');
        var x = Math.max(0, Math.min(e.clientX - dx, mdi.clientWidth - 180));
        var y = Math.max(0, Math.min(e.clientY - dy, mdi.clientHeight - 80));
        win.style.left = x + 'px';
        win.style.top = y + 'px';
    });

    document.addEventListener('mouseup', function() {
        if (!drag) {
            return;
        }
        drag = false;
        document.body.style.userSelect = '';
    });

    closeBtn.addEventListener('click', function() {
        win.remove();
    });

    if (openTabBtn) {
        openTabBtn.addEventListener('click', function() {
            var frame = win.querySelector('.module-iframe');
            if (!frame || !frame.src || frame.src === 'about:blank' || frame.src.indexOf('data:text/html') === 0) {
                return;
            }
            window.open(frame.src, '_blank', 'noopener');
        });
    }
}

/**
 * ============================================================
 * Module Family Helpers
 * ============================================================
 */
function moduleIsCustomerFamily(moduleId) {
    return ['customer', 'supplier', 'staff-adding', 'goldsmith-add', 'refiner-add', 'jewellery-add'].indexOf(moduleId) !== -1;
}

function moduleCustomerType(moduleId) {
    var map = {
        'customer': 'C',
        'supplier': 'S',
        'staff-adding': 'F',
        'goldsmith-add': 'G',
        'refiner-add': 'R',
        'jewellery-add': 'J'
    };
    return map[moduleId] || 'C';
}

/**
 * ============================================================
 * Init Module Content (customer family inline forms)
 * ============================================================
 */
function initModuleContent(win, moduleId, title) {
    if (!moduleIsCustomerFamily(moduleId)) {
        return;
    }

    var form = win.querySelector('.js-customer-form');
    if (!form) {
        return;
    }

    var type = moduleCustomerType(moduleId);
    var statusEl = form.querySelector('.js-form-status');
    var snapshot = null;

    var setStatus = function(msg, isError) {
        statusEl.textContent = msg;
        statusEl.className = 'form-status show ' + (isError ? 'err' : 'ok');
    };

    var clearStatus = function() {
        statusEl.textContent = '';
        statusEl.className = 'form-status';
    };

    var getPayload = function() {
        var payload = { type: type };
        var fields = form.querySelectorAll('[name]');
        fields.forEach(function(el) {
            if (el.type === 'checkbox') {
                payload[el.name] = el.checked ? 'Y' : 'N';
            } else {
                payload[el.name] = (el.value || '').trim();
            }
        });
        payload.code = (payload.code || '').toUpperCase();
        return payload;
    };

    var applyData = function(data) {
        var fields = form.querySelectorAll('[name]');
        fields.forEach(function(el) {
            var value = data[el.name];
            if (el.type === 'checkbox') {
                el.checked = String(value || 'N').toUpperCase() === 'Y' || value === 1 || value === true;
            } else if (value !== undefined && value !== null) {
                el.value = value;
            } else {
                el.value = '';
            }
        });

        var opbal = Number(data.opbalance || form.opbalance.value || 0);
        if (form.balance_type) {
            form.balance_type.value = opbal >= 0 ? 'credit' : 'debit';
        }
        if (form.opbalance) {
            form.opbalance.value = Math.abs(opbal).toFixed(2);
        }
        snapshot = getPayload();
    };

    var clearForm = function() {
        clearStatus();
        form.reset();
        if (form.balance_type) {
            form.balance_type.value = 'credit';
        }
        if (form.opbalance) {
            form.opbalance.value = '0.00';
        }
        fetch('{{ url("/api/customer/next-code") }}?type=' + encodeURIComponent(type))
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    form.code.value = data.code;
                }
            })
            .catch(function() {
                setStatus('Could not fetch next code.', true);
            })
            .finally(function() {
                snapshot = getPayload();
            });
    };

    var loadByCode = function(code) {
        clearStatus();
        if (!code) {
            setStatus('Enter code to load record.', true);
            return;
        }
        fetch('{{ url("/api/customer/get") }}?code=' + encodeURIComponent(code))
            .then(function(res) {
                return res.json().then(function(data) {
                    if (!res.ok || !data.success) {
                        setStatus(data.message || 'Record not found.', true);
                        return;
                    }
                    applyData(data.data || {});
                    setStatus('Record loaded.');
                });
            })
            .catch(function() {
                setStatus('Failed to load record.', true);
            });
    };

    form.querySelector('[data-action="add"]').addEventListener('click', clearForm);

    form.querySelector('[data-action="edit"]').addEventListener('click', function() {
        var code = prompt('Enter code to edit:', form.code.value || '');
        if (code === null) {
            return;
        }
        loadByCode(code.trim().toUpperCase());
    });

    form.querySelector('[data-action="delete"]').addEventListener('click', function() {
        var code = form.code.value.trim();
        if (!code) {
            setStatus('Code is required for delete.', true);
            return;
        }
        if (!confirm('Delete record ' + code + '?')) {
            return;
        }
        fetch('{{ url("/api/customer/delete") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code })
        })
        .then(function(res) {
            return res.json().then(function(data) {
                if (!res.ok || !data.success) {
                    setStatus(data.message || 'Delete failed.', true);
                    return;
                }
                setStatus(data.message || 'Deleted.');
                clearForm();
            });
        })
        .catch(function() {
            setStatus('Delete failed.', true);
        });
    });

    form.querySelector('[data-action="save"]').addEventListener('click', function() {
        var payload = getPayload();
        if (!payload.code || !payload.name) {
            setStatus('Code and Name are required.', true);
            return;
        }
        fetch('{{ url("/api/customer/save") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(res) {
            return res.json().then(function(data) {
                if (!res.ok || !data.success) {
                    setStatus(data.message || 'Save failed.', true);
                    return;
                }
                applyData(data.data || payload);
                setStatus(data.message || 'Saved successfully.');
            });
        })
        .catch(function() {
            setStatus('Save failed.', true);
        });
    });

    form.querySelector('[data-action="cancel"]').addEventListener('click', function() {
        clearStatus();
        if (snapshot) {
            applyData(snapshot);
        }
    });

    form.querySelector('[data-action="exit"]').addEventListener('click', function() {
        win.remove();
    });

    form.code.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadByCode(form.code.value.trim().toUpperCase());
        }
    });

    clearForm();
}

/**
 * ============================================================
 * Build Module Markup (inline content for popup windows)
 * ============================================================
 */
function buildModuleMarkup(moduleId, title) {
    var map = {
        'rate-update': ['Gold Rate Update', 'Update 22K/24K rates and save with date-time audit.'],
        'application-settings': ['Application Settings', 'Configure system behavior, permissions, and defaults.'],
        'item-add-edit': ['Item Add/Edit', 'Create and maintain item master details.'],
        'item-temp': ['Item Temp', 'Temporary staging list for quick item edits.'],
        'other-items': ['Other Items', 'Manage non-gold inventory and accessories.'],
        'groups': ['Groups', 'Maintain item groups.'],
        'sub-groups': ['Sub Groups', 'Maintain sub group hierarchy.'],
        'purity-type': ['Purity Type', 'Define purity standards for billing and stock.'],
        'counters': ['Counters', 'Counter master and numbering settings.'],
        'wastage-table': ['Wastage Table', 'Wastage percent setup by item/category.'],
        'mc-table': ['MC Table', 'Making charge settings and slabs.'],
        'party-mc-table': ['Party MC Table', 'Party-wise making charge control.'],
        'stock-type': ['Stock Type', 'Stock type and classification setup.'],
        'models': ['Models', 'Model/design master.'],
        'opening-stock-entry': ['Opening Stock Entry', 'Initial stock entry and value setup.'],
        'reorder-level-table': ['ReOrder Level Table', 'Minimum stock alerts and thresholds.'],
        'accounts-master-account': ['Account', 'Account master setup.'],
        'accounts-master-position-settings': ['Position Settings', 'Account position and display settings.'],
        'accounts-master-book-stock': ['Book Stock', 'Book stock account linkage.'],
        'accounts-master-op-stock-value-set': ['Op.Stock Value Set', 'Opening stock valuation settings.'],
        'accounts-master-co-party-limit': ['C/o Party Limit', 'Credit/limit control for parties.'],
        'sales-man': ['Sales Man', 'Salesman master details and account mapping.'],
        'op-bill-creation-customers': ['Customers', 'Create opening customer bills.'],
        'change-password': ['Change Password', 'Update user password securely.'],
        'provide-access': ['Provide Access', 'Assign/restrict user permissions.'],
        'users-history': ['Users History', 'Review login and activity history.']
    };

    var info = map[moduleId] || [title, 'Module page is ready for integration.'];

    if (moduleIsCustomerFamily(moduleId)) {
        var typeLabel = {
            'customer': 'CUSTOMER',
            'supplier': 'SUPPLIER',
            'staff-adding': 'STAFF',
            'goldsmith-add': 'GOLDSMITH',
            'refiner-add': 'REFINER',
            'jewellery-add': 'JEWELLERY'
        }[moduleId] || 'CUSTOMER';

        if (moduleId === 'staff-adding') {
            return '' +
                '<form class="form-shell js-customer-form" onsubmit="return false;">' +
                    '<div class="form-head">' +
                        '<div class="form-title">' + escapeHtml(info[0]) + '</div>' +
                        '<div class="form-type-badge">STAFF</div>' +
                    '</div>' +
                    '<div class="form-grid">' +
                        '<div class="field"><label>Code *</label><input name="code" /></div>' +
                        '<div class="field"><label>ID</label><input name="idno" /></div>' +
                        '<div class="field span-2"><label>Name *</label><input name="name" /></div>' +
                        '<div class="field span-2"><label>Address</label><input name="addr1" /></div>' +
                        '<div class="field"><label>City / Loc</label><input name="city" /></div>' +
                        '<div class="field"><label>State</label><input name="state" /></div>' +
                        '<div class="field"><label>Phone</label><input name="telephone" /></div>' +
                        '<div class="field"><label>Mobile</label><input name="mobile" /></div>' +
                        '<div class="field"><label>Email</label><input name="email" type="email" /></div>' +
                        '<div class="field"><label>PIN Code</label><input name="pin" /></div>' +
                        '<div class="field"><label>GST / TIN</label><input name="tin" /></div>' +
                        '<div class="field"><label>PAN/Aadhar</label><input name="panadhar" /></div>' +
                        '<div class="field"><label>Religion</label><input name="religion" /></div>' +
                        '<div class="field"><label>Sales Man</label><input name="smcode" /></div>' +
                        '<div class="field"><label>A/c Group</label><input name="grp" value="SUNDRY CREDITORS" /></div>' +
                        '<div class="field"><label>Route</label><input name="route" /></div>' +
                        '<div class="field"><label>Salary</label><input name="salary" value="0.00" /></div>' +
                        '<div class="field"><label>Cut Rate</label><input name="cutrate" value="0.00" /></div>' +
                        '<div class="field"><label>Collection Com %</label><input name="colncomn" value="0.00" /></div>' +
                        '<div class="field"><label>POS Pwd</label><input name="pospwd" /></div>' +
                        '<div class="field"><label>Opening Balance</label><input name="opbalance" value="0.00" /></div>' +
                        '<div class="field"><label>Balance Type</label><select name="balance_type"><option value="debit">To Receive</option><option value="credit">To Give</option></select></div>' +
                        '<div class="field"><label>Op.Dep.Wgt Bal</label><input name="opdepwgtbal" value="0.000" /></div>' +
                        '<div class="field"><label>Op.PCard Points</label><input name="oppcardpoints" value="0.00" /></div>' +
                        '<div class="field"><label>Home Mobile</label><input name="homemobile" /></div>' +
                        '<div class="field checkline"><input type="checkbox" name="coparty" /><label>C/o Party</label></div>' +
                        '<div class="field checkline"><input type="checkbox" name="agent" /><label>Agent</label></div>' +
                        '<div class="field checkline"><input type="checkbox" name="removed" /><label>Removed</label></div>' +
                        '<div class="field span-2"><label>Note</label><textarea name="note"></textarea></div>' +
                    '</div>' +
                    '<div class="form-status js-form-status"></div>' +
                    '<div class="form-actions">' +
                        '<button type="button" data-action="add">Add</button>' +
                        '<button type="button" data-action="edit">Edit</button>' +
                        '<button type="button" data-action="delete" class="warn">Delete</button>' +
                        '<button type="button" data-action="save" class="primary">Save</button>' +
                        '<button type="button" data-action="cancel">Cancel</button>' +
                        '<button type="button" data-action="exit">Exit</button>' +
                    '</div>' +
                '</form>';
        }

        return '' +
            '<form class="legacy-customer-shell js-customer-form" onsubmit="return false;">' +
                '<div class="legacy-customer-title">Add New Entry</div>' +
                '<div class="legacy-customer-grid">' +
                    '<div class="lbl">Code</div><input name="code" />' +
                    '<div class="lbl">Name</div><input name="name" />' +
                    '<div class="legacy-right-panel">Photo Area</div>' +

                    '<div class="lbl">Address</div><input name="addr1" />' +
                    '<div class="lbl">City / Loc</div><input name="city" />' +

                    '<div class="lbl">Phone</div><input name="telephone" />' +
                    '<div class="lbl">Mobile</div><input name="mobile" />' +

                    '<div class="lbl">Email</div><input name="email" />' +
                    '<div class="lbl">PIN Code</div><input name="pin" />' +

                    '<div class="lbl">GST No</div><input name="tin" />' +
                    '<div class="lbl">PAN/Adh</div><input name="panadhar" />' +

                    '<div class="lbl">Religion</div><input name="religion" />' +
                    '<div class="lbl">SMAN</div><input name="smcode" />' +

                    '<div class="lbl">A/c Group</div><input name="grp" value="SUNDRY DEBTORS" />' +
                    '<div class="lbl">State</div><input name="state" />' +

                    '<div class="lbl">BS Head</div><input value="SUNDRY DEBTORS" readonly />' +
                    '<div class="lbl">Op. Balance</div><input name="opbalance" value="0.00" />' +

                    '<div class="lbl">Route</div><input name="route" />' +
                    '<div class="lbl">Area</div><input name="carea" />' +

                    '<div class="lbl">Date</div><input name="adate" />' +
                    '<div class="lbl">Due Date</div><input name="duedate" />' +

                    '<div class="lbl">Op.Weight Bal</div><input name="opweight" value="0.000" />' +
                    '<div class="lbl">Op.Dep.Wgt Bal</div><input name="opdepwgtbal" value="0.000" />' +

                    '<div class="lbl">Prev.Card Type</div><input name="pcard" />' +
                    '<div class="lbl">Op.PCard Points</div><input name="oppcardpoints" value="0.00" />' +

                    '<div class="lbl">Note</div><textarea name="note"></textarea>' +
                    '<div class="legacy-checks">' +
                        '<label><input type="checkbox" name="coparty" /> C/o Party</label>' +
                        '<label><input type="checkbox" name="removed" /> Removed</label>' +
                        '<label><input type="checkbox" name="agent" /> Agent</label>' +
                    '</div>' +
                '</div>' +
                '<div style="margin-top:8px;">' +
                    '<select name="balance_type" style="height:24px; border:1px solid #5c7591;">' +
                        '<option value="debit">To Receive</option>' +
                        '<option value="credit">To Give</option>' +
                    '</select>' +
                '</div>' +
                '<div class="form-status js-form-status"></div>' +
                '<div class="form-actions">' +
                    '<button type="button" data-action="add">Add</button>' +
                    '<button type="button" data-action="edit">Edit</button>' +
                    '<button type="button" data-action="delete" class="warn">Delete</button>' +
                    '<button type="button" data-action="save" class="primary">Save</button>' +
                    '<button type="button" data-action="cancel">Cancel</button>' +
                    '<button type="button" data-action="exit">Exit</button>' +
                '</div>' +
            '</form>';
    }

    return '' +
        '<div class="module-card">' +
            '<h3>' + escapeHtml(info[0]) + '</h3>' +
            '<p>' + escapeHtml(info[1]) + '</p>' +
            '<p style="margin-top:10px;">Module key: <strong>' + escapeHtml(moduleId) + '</strong></p>' +
        '</div>';
}

/**
 * ============================================================
 * Module Label from ID
 * ============================================================
 */
function moduleLabelFromId(id) {
    return id.split('-').map(function(part) { return part.charAt(0).toUpperCase() + part.slice(1); }).join(' ');
}

/**
 * ============================================================
 * Escape HTML (safe rendering in JS)
 * ============================================================
 */
function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function initPhoneLookup() {
    var wrap = document.getElementById('phoneLookup');
    var input = document.getElementById('phoneLookupInput');
    var results = document.getElementById('phoneLookupResults');
    var clearBtn = document.getElementById('phoneLookupClear');
    if (!wrap || !input || !results || !clearBtn) return;

    var timer = null;
    var lastQuery = '';

    function hideResults() {
        results.classList.remove('show');
    }

    function renderRows(rows, query) {
        if (!query) {
            results.innerHTML = '';
            hideResults();
            return;
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            results.innerHTML = '<div class="phone-lookup-empty">No match found.</div>';
            results.classList.add('show');
            return;
        }

        results.innerHTML = rows.map(function(row) {
            var code = String(row.code || '');
            var type = String(row.type_label || row.type || 'Party');
            var name = String(row.name || '');
            var contact = String(row.contact || row.mobile || row.telephone || '');
            var city = String(row.city || '');
            var meta = [type, 'Code: ' + code, contact, city].filter(Boolean).join(' | ');
            return '<div class="phone-lookup-row">' +
                '<div><div class="phone-lookup-name">' + escapeHtml(name || code) + '</div>' +
                '<div class="phone-lookup-meta">' + escapeHtml(meta) + '</div></div>' +
                '<button type="button" class="phone-copy-btn" data-copy-code="' + escapeHtml(code) + '" title="Copy code">' +
                '<i class="fas fa-copy"></i><span>' + escapeHtml(code) + '</span></button>' +
                '</div>';
        }).join('');
        results.classList.add('show');
    }

    async function lookupPhone() {
        var q = input.value.trim();
        lastQuery = q;
        if (q.length < 3) {
            renderRows([], '');
            return;
        }
        results.innerHTML = '<div class="phone-lookup-empty">Searching...</div>';
        results.classList.add('show');
        try {
            var url = withCompanyContext('{{ url("/api/phone-book/quick-lookup") }}?phone=' + encodeURIComponent(q));
            var res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            var data = await res.json();
            if (q !== lastQuery) return;
            renderRows(data && data.ok ? (data.results || []) : [], q);
        } catch (_) {
            if (q === lastQuery) {
                results.innerHTML = '<div class="phone-lookup-empty">Lookup failed.</div>';
                results.classList.add('show');
            }
        }
    }

    input.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(lookupPhone, 250);
    });
    input.addEventListener('focus', function() {
        if (input.value.trim().length >= 3) {
            clearTimeout(timer);
            lookupPhone();
        } else if (results.innerHTML.trim() !== '') {
            results.classList.add('show');
        }
    });
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(timer);
            lookupPhone();
        } else if (e.key === 'Escape') {
            hideResults();
        }
    });
    clearBtn.addEventListener('click', function() {
        input.value = '';
        results.innerHTML = '';
        hideResults();
        input.focus();
    });
    results.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-copy-code]');
        if (!btn) return;
        var code = btn.getAttribute('data-copy-code') || '';
        if (!code) return;
        var done = function() {
            btn.innerHTML = '<i class="fas fa-check"></i><span>Copied</span>';
            setTimeout(function() {
                btn.innerHTML = '<i class="fas fa-copy"></i><span>' + escapeHtml(code) + '</span>';
            }, 900);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(done).catch(done);
        } else {
            var tmp = document.createElement('textarea');
            tmp.value = code;
            document.body.appendChild(tmp);
            tmp.select();
            try { document.execCommand('copy'); } catch (_) {}
            document.body.removeChild(tmp);
            done();
        }
    });
    document.addEventListener('click', function(e) {
        if (!wrap.contains(e.target)) hideResults();
    });
    if (input.value.trim().length >= 3) {
        setTimeout(lookupPhone, 100);
    }
}

/**
 * ============================================================
 * Clock Tick (status bar date/time)
 * ============================================================
 */
function tickClock() {
    var d = new Date();
    document.getElementById('statusDate').textContent = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    document.getElementById('statusTime').textContent = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function setTopbarPrimaryAction(mode) {
    var toggleBtn = document.getElementById('themeToggleBtn');
    if (!toggleBtn) return;

    var icon = toggleBtn.querySelector('i');
    if (mode === 'company-close') {
        toggleBtn.classList.add('company-close-btn');
        toggleBtn.onclick = closeActiveModuleFrame;
        toggleBtn.title = 'Close Company Tab';
        if (icon) icon.className = 'fas fa-xmark';
        return;
    }

    toggleBtn.classList.remove('company-close-btn');
    if (COMPANY_THEME_ACTIVE) {
        toggleBtn.onclick = function() {
            window.location.href = SECONDARY_CLOSE_URL;
        };
        toggleBtn.title = 'Logout Secondary Company';
        if (icon) icon.className = 'fas fa-wifi';
        applyDashboardTheme('alt');
        return;
    }

    toggleBtn.onclick = handleThemeTap;
    if (icon) icon.className = 'fas fa-wifi';
    applyDashboardTheme(COMPANY_THEME_ACTIVE ? 'alt' : SAVED_DASHBOARD_THEME);
}

var IFRAME_THEMES = {
    'default': 'body{background:#f0f2f5!important}.toolbar{background:linear-gradient(135deg,#1e3a5f,#2c5282)!important}.tb-select option{background:#1e3a5f!important}table{background:#fff!important}td{border-bottom-color:#f1f5f9!important;color:#1e293b!important}th{background:#f1f5f9!important;border-bottom-color:#e2e8f0!important;color:#64748b!important}th:hover{background:#e2e8f0!important}tr:hover td{background:#f8fafc!important}tr.sel td{background:#dbeafe!important}tr.group-hdr td{background:#eff6ff!important;color:#1e40af!important;border-bottom-color:#bfdbfe!important}.summary{background:#fff!important;border-top-color:#e2e8f0!important}.summary span{color:#64748b!important}.summary b{color:#1e40af!important}.wait-card{background:#fff!important}.wait-card strong{color:#1e3a5f!important}',
    'alt':     'body{background:#f7efe6!important}.toolbar{background:linear-gradient(135deg,#4a221b,#673128)!important}.tb-select option{background:#4a221b!important}table{background:#fff8f1!important}td{border-bottom-color:#fce8d8!important;color:#33160f!important}th{background:#fdf0e8!important;border-bottom-color:#ead9c7!important;color:#8a6557!important}th:hover{background:#ead9c7!important}tr:hover td{background:#fff2e4!important}tr.sel td{background:#fde8dc!important}tr.group-hdr td{background:#fff5ee!important;color:#a33d2e!important;border-bottom-color:#f0b496!important}.summary{background:#fff8f1!important;border-top-color:#ead9c7!important}.summary span{color:#8a6557!important}.summary b{color:#a33d2e!important}.wait-card{background:#fff8f1!important}.wait-card strong{color:#4a221b!important}',
    'ocean':   'body{background:#edf1fb!important}.toolbar{background:linear-gradient(135deg,#0d1b40,#1e3468)!important}.tb-select option{background:#0d1b40!important}table{background:#fff!important}td{border-bottom-color:#dce5f5!important;color:#0d1b40!important}th{background:#edf1fb!important;border-bottom-color:#dce5f5!important;color:#4a6898!important}th:hover{background:#dce5f5!important}tr:hover td{background:#f4f7fd!important}tr.sel td{background:#ddeafc!important}tr.group-hdr td{background:#ddeafc!important;color:#1755a8!important;border-bottom-color:#a8c4f0!important}.summary{background:#fff!important;border-top-color:#dce5f5!important}.summary span{color:#4a6898!important}.summary b{color:#1755a8!important}.wait-card{background:#fff!important}.wait-card strong{color:#0d1b40!important}',
    'forest':  'body{background:#f0f5ee!important}.toolbar{background:linear-gradient(135deg,#1a3620,#254d2e)!important}.tb-select option{background:#1a3620!important}table{background:#fff!important}td{border-bottom-color:#e1ede0!important;color:#0d2210!important}th{background:#edf5ec!important;border-bottom-color:#e1ede0!important;color:#4a7058!important}th:hover{background:#e1ede0!important}tr:hover td{background:#f5faf4!important}tr.sel td{background:#d6f0e4!important}tr.group-hdr td{background:#d6f0e4!important;color:#1a7a52!important;border-bottom-color:#9ed8bc!important}.summary{background:#fff!important;border-top-color:#e1ede0!important}.summary span{color:#4a7058!important}.summary b{color:#1a7a52!important}.wait-card{background:#fff!important}.wait-card strong{color:#1a3620!important}',
    'rose':    'body{background:#fef0f3!important}.toolbar{background:linear-gradient(135deg,#4a0f25,#6a1a38)!important}.tb-select option{background:#4a0f25!important}table{background:#fff!important}td{border-bottom-color:#fce0e8!important;color:#33071a!important}th{background:#fdedf1!important;border-bottom-color:#fce0e8!important;color:#8a5060!important}th:hover{background:#fce0e8!important}tr:hover td{background:#fff5f7!important}tr.sel td{background:#fce0e8!important}tr.group-hdr td{background:#fce0e8!important;color:#c0365a!important;border-bottom-color:#f5afc0!important}.summary{background:#fff!important;border-top-color:#fce0e8!important}.summary span{color:#8a5060!important}.summary b{color:#c0365a!important}.wait-card{background:#fff!important}.wait-card strong{color:#4a0f25!important}',
    'violet':  'body{background:#f3eefb!important}.toolbar{background:linear-gradient(135deg,#2a1550,#3c2068)!important}.tb-select option{background:#2a1550!important}table{background:#fff!important}td{border-bottom-color:#e8dff7!important;color:#1a0d33!important}th{background:#f0eafc!important;border-bottom-color:#e8dff7!important;color:#6a4c8a!important}th:hover{background:#e8dff7!important}tr:hover td{background:#f8f4fd!important}tr.sel td{background:#ede0fc!important}tr.group-hdr td{background:#ede0fc!important;color:#6433a0!important;border-bottom-color:#c4a8e8!important}.summary{background:#fff!important;border-top-color:#e8dff7!important}.summary span{color:#6a4c8a!important}.summary b{color:#6433a0!important}.wait-card{background:#fff!important}.wait-card strong{color:#2a1550!important}',
    'dark':    'body{background:#111827!important}.toolbar{background:linear-gradient(135deg,#0d1117,#1a2234)!important}.tb-select option{background:#0d1117!important}table{background:#1f2937!important}td{border-bottom-color:#2d3c50!important;color:#f1f5f9!important}th{background:#1f2937!important;border-bottom-color:#2d3c50!important;color:#64748b!important}th:hover{background:#2d3c50!important}tr:hover td{background:#263344!important}tr.sel td{background:rgba(0,191,187,0.15)!important}tr.group-hdr td{background:#263344!important;color:#00bfbb!important;border-bottom-color:#004a48!important}.summary{background:#1f2937!important;border-top-color:#2d3c50!important}.summary span{color:#64748b!important}.summary b{color:#00bfbb!important}.wait-card{background:#1f2937!important}.wait-card strong{color:#00bfbb!important}',
};
function injectThemeIntoIframe(iframe) {
    try {
        var doc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
        if (!doc || !doc.head) return;
        var theme = document.body.className.match(/theme-(\w+)/);
        var themeName = theme ? theme[1] : 'default';
        var css = IFRAME_THEMES[themeName] || IFRAME_THEMES['default'];
        var el = doc.getElementById('goldapp-theme-override');
        if (!el) {
            el = doc.createElement('style');
            el.id = 'goldapp-theme-override';
            doc.head.appendChild(el);
        }
        el.textContent = css;
        if (DROPDOWN_AUTOCLOSE_URL && !doc.getElementById('goldapp-dropdown-autoclose')) {
            var helper = doc.createElement('script');
            helper.id = 'goldapp-dropdown-autoclose';
            helper.src = DROPDOWN_AUTOCLOSE_URL;
            doc.head.appendChild(helper);
        }
    } catch(e) {}
}

function applyDashboardTheme(themeName) {
    var validThemes = ['default', 'alt', 'ocean', 'forest', 'rose', 'violet', 'dark'];
    if (validThemes.indexOf(themeName) === -1) themeName = 'default';
    document.body.classList.remove('theme-alt', 'theme-ocean', 'theme-forest', 'theme-rose', 'theme-violet', 'theme-dark');
    if (themeName !== 'default') document.body.classList.add('theme-' + themeName);
    // Apply theme to all open module iframes
    document.querySelectorAll('#tabFramesContainer iframe').forEach(function(fr) {
        injectThemeIntoIframe(fr);
        try { fr.contentWindow.postMessage({type:'goldapp:set-theme', theme: themeName}, '*'); } catch(e) {}
    });
    var toggleBtn = document.getElementById('themeToggleBtn');
    if (toggleBtn) {
        toggleBtn.classList.toggle('active', themeName !== 'default' || companySelectSecretUnlocked);
        if (!toggleBtn.classList.contains('company-close-btn')) {
            var remaining = Math.max(0, 10 - themeTapCount);
            if (COMPANY_THEME_ACTIVE) {
                toggleBtn.title = 'Logout Secondary Company';
            } else {
                toggleBtn.title = companySelectSecretUnlocked
                    ? 'Company Select unlocked'
                    : 'WiFi theme switcher: ' + remaining + ' clicks to unlock';
            }
        }
    }
}

function initThemeEasterEgg() {
    var toggleBtn = document.getElementById('themeToggleBtn');
    if (!toggleBtn) return;

    themeTapCount = 0;
    try { localStorage.removeItem('goldapp_dash_theme'); } catch(e) {}
    var savedTheme = COMPANY_THEME_ACTIVE ? 'alt' : SAVED_DASHBOARD_THEME;
    applyDashboardTheme(savedTheme);
}

function handleThemeTap() {
    themeTapCount += 1;
    if (themeTapCount >= 10) {
        themeTapCount = 0;
        companySelectSecretUnlocked = true;
        applyDashboardTheme(COMPANY_THEME_ACTIVE ? 'alt' : SAVED_DASHBOARD_THEME);
        buildMenu();
        return;
    }
    applyDashboardTheme(COMPANY_THEME_ACTIVE ? 'alt' : SAVED_DASHBOARD_THEME);
}

/**
 * ============================================================
 * Keyboard Shortcuts
 * ============================================================
 */
function initShortcuts() {
    function isTypingTarget(target) {
        if (!target) return false;
        var tag = (target.tagName || '').toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable;
    }

    function matchesShortcut(e, combo) {
        if (!combo) return false;
        var key = String(e.key || '').toLowerCase();
        return !!combo.ctrlKey === !!e.ctrlKey
            && !!combo.altKey === !!e.altKey
            && !!combo.shiftKey === !!e.shiftKey
            && key === combo.key;
    }

    var shiftAltShortcuts = [
        { key: 's', id: 'sales-bill', title: 'Sales Invoice' },
        { key: 'p', id: 'purchase-bill', title: 'Purchase Invoice' },
        { key: 'i', id: 'sales-bill', title: 'Invoice' },
        { key: 'r', id: 'sales-reports-sales-book', title: 'Reports' },
        { key: 'c', id: 'customer', title: 'Customer' },
        { key: 'u', id: 'supplier', title: 'Supplier' },
        { key: 'a', id: 'accounts-master-account', title: 'Accounts' },
        { key: 'm', id: 'staff-adding', title: 'Staff / Master' },
        { key: '1', id: 'accounts-receipt', title: 'Receipt' },
        { key: '2', id: 'accounts-customer-billwise-rcpt', title: 'Receive Payment' },
        { key: '3', id: 'accounts-payment', title: 'Make Payment' },
        { key: 'j', id: 'accounts-journal', title: 'Journal' },
        { key: 't', id: 'stock-list-items', title: 'Stock' },
        { key: 'k', id: 'item-stock-adjustment-stock-add-less', title: 'Stock Adjustment' },
        { key: 'b', id: 'barcode-single-entry', title: 'Barcode' },
        { key: 'l', id: 'stock-period-item', title: 'Ledger' },
        { key: 'o', id: 'order-bill-new-order', title: 'Order' },
        { key: 'x', id: 'order-bill-cancel', title: 'Cancel' },
        { key: 'v', id: 'order-bill-order-sale', title: 'Convert Sale' },
        { key: 'n', id: 'order-bill-new-order', title: 'New Entry' },
        { key: 'g', id: 'goldsmith-bill', title: 'Goldsmith' },
        { key: 'y', id: 'refinery-bill-new-entry', title: 'Refinery' },
        { key: 'w', id: 'goldsmith-bill-new-work-note', title: 'Work' },
        { key: 'd', id: 'accounts-discount-allocation', title: 'Discount' }
    ];

    document.addEventListener('keydown', function(e) {
        if (isTypingTarget(e.target)) {
            return;
        }

        for (var i = 0; i < shiftAltShortcuts.length; i++) {
            var shortcut = shiftAltShortcuts[i];
            if (matchesShortcut(e, { shiftKey: true, altKey: true, key: shortcut.key })) {
                e.preventDefault();
                openModule(shortcut.id, shortcut.title);
                return;
            }
        }

        var key = e.key.toLowerCase();
        if (e.ctrlKey && !e.altKey && key === 'u') {
            e.preventDefault();
            openModule('ruff-work', 'Ruff Work');
        } else if (e.ctrlKey && !e.altKey && key === 't') {
            e.preventDefault();
            openModule('term-summary', 'Term Summary');
        } else if (e.ctrlKey && !e.altKey && key === 'h') {
            e.preventDefault();
            openModule('cash-balance', 'Cash Balance');
        } else if (matchesShortcut(e, { altKey: true, key: 'f5' })) {
            e.preventDefault();
            openModule('accounts-reports-ac-receivable-payable-summary', 'Customer Balance');
        } else if (matchesShortcut(e, { ctrlKey: true, shiftKey: true, key: 'z' }) || (e.altKey && !e.ctrlKey && key === 'z')) {
            e.preventDefault();
            openModule('company-select', 'Company Select');
        } else if (matchesShortcut(e, { ctrlKey: true, shiftKey: true, key: '1' }) || (e.altKey && !e.ctrlKey && key === '1')) {
            e.preventDefault(); openModule('sales-bill', 'Sales Bill');
        } else if (matchesShortcut(e, { ctrlKey: true, shiftKey: true, key: '2' }) || (e.altKey && !e.ctrlKey && key === '2')) {
            e.preventDefault(); openModule('sales-return-bill', 'Sales Return');
        } else if (matchesShortcut(e, { ctrlKey: true, shiftKey: true, key: '3' }) || (e.altKey && !e.ctrlKey && key === '3')) {
            e.preventDefault(); openModule('purchase-bill', 'Purchase Bill');
        } else if (matchesShortcut(e, { ctrlKey: true, shiftKey: true, key: '4' }) || (e.altKey && !e.ctrlKey && key === '4')) {
            e.preventDefault(); openModule('purchase-return-bill', 'Purchase Return');
        } else if (matchesShortcut(e, { ctrlKey: true, shiftKey: true, key: '5' }) || (e.altKey && !e.ctrlKey && key === '5')) {
            e.preventDefault(); openModule('receipt-cash', 'Receipt');
        } else if (matchesShortcut(e, { ctrlKey: true, shiftKey: true, key: '6' }) || (e.altKey && !e.ctrlKey && key === '6')) {
            e.preventDefault(); openModule('payment-cash', 'Payment');
        } else if (matchesShortcut(e, { ctrlKey: true, shiftKey: true, key: '7' }) || (e.altKey && !e.ctrlKey && key === '7')) {
            e.preventDefault(); openModule('journal-entry', 'Journal Entries');
        }
    });
}

/**
 * ============================================================
 * User Menu (top-right chip dropdown)
 * ============================================================
 */
function initUserMenu() {
    var chip = document.getElementById('userChip');
    var menu = document.getElementById('userMenu');
    if (!chip || !menu) return;

    chip.addEventListener('click', function(e) {
        e.stopPropagation();
        menu.classList.toggle('show');
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#userMenu')) {
            menu.classList.remove('show');
        }
        if (!e.target.closest('.menu-item')) {
            closeAllMenus();
        }
    });
}

/**
 * ============================================================
 * Module Frame Close (close button + postMessage)
 * ============================================================
 */
function showModuleFrameLoading(title, url) {
    var head = document.getElementById('moduleFrameTitle');
    var path = document.getElementById('moduleFramePath');
    if (head) head.textContent = (title || 'Loading\u2026');
    if (path) { path.textContent = url || ''; path.title = url || ''; }
}
function hideModuleFrameLoading() {
    /* no-op: loading overlay reserved for future use */
}

function applyDashboardRates(data) {
    if (!data || typeof data !== 'object') {
        return;
    }

    var fmt = function(v) {
        return '\u20B9' + parseFloat(v || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    };

    document.querySelectorAll('.sbr-22 .sbr-val').forEach(function(el) { el.textContent = fmt(data.goldRate22K); });
    document.querySelectorAll('.sbr-18 .sbr-val').forEach(function(el) { el.textContent = fmt(data.goldRate18K); });
    document.querySelectorAll('.sbr-ag .sbr-val').forEach(function(el) { el.textContent = fmt(data.silverRate); });
    document.querySelectorAll('.rg22 .rc-num').forEach(function(el) { el.textContent = fmt(data.goldRate22K); });
    document.querySelectorAll('.rg18 .rc-num').forEach(function(el) { el.textContent = fmt(data.goldRate18K); });
    document.querySelectorAll('.rgag .rc-num').forEach(function(el) { el.textContent = fmt(data.silverRate); });
    document.querySelectorAll('.rgth .rc-num').forEach(function(el) { el.textContent = fmt(data.thRate); });
}

function refreshDashboardRates() {
    return fetch(withCompanyContext(@json(url('/api/rate/current')) + '?_=' + Date.now()), {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache'
        },
        cache: 'no-store'
    })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            applyDashboardRates(d);
            return d;
        });
}

window.goldappRefreshRates = refreshDashboardRates;

function isStartupRatePopupOpen() {
    var overlay = document.getElementById('startupRateOverlay');
    return !!(overlay && overlay.classList.contains('show'));
}

function showStartupRatePopup() {
    var overlay = document.getElementById('startupRateOverlay');
    var frame = document.getElementById('startupRateFrame');
    if (!overlay || !frame) return;
    frame.src = withCompanyContext(@json(url('/rate/update')) + '?startup=1&_=' + Date.now());
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
}

function closeStartupRatePopup(showNext) {
    var overlay = document.getElementById('startupRateOverlay');
    var frame = document.getElementById('startupRateFrame');
    if (overlay) {
        overlay.classList.remove('show');
        overlay.setAttribute('aria-hidden', 'true');
    }
    if (frame) frame.src = 'about:blank';
    if (showNext) {
        window.setTimeout(showStartupDuePopup, 120);
    }
}

function formatStartupMoney(value) {
    return parseFloat(value || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatStartupDate(value) {
    if (!value) return '';
    var parts = String(value).split('-');
    return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : String(value);
}

function showStartupDuePopup() {
    var overlay = document.getElementById('startupDueOverlay');
    var body = document.getElementById('startupDueBody');
    if (!overlay || !body) return;

    var payload = CREDIT_BILL_SUMMARY || {};
    var rows = Array.isArray(payload.rows) ? payload.rows : [];
    var totals = payload.totals || {};

    var html = '<div class="startup-rate-grid">'
        + startupRateCard('Gold 22K', dashboardData.goldRate22K)
        + startupRateCard('Gold 18K', dashboardData.goldRate18K)
        + startupRateCard('Silver', dashboardData.silverRate)
        + startupRateCard('TH Rate', dashboardData.thRate)
        + '</div>'
        + '<div class="startup-subtitle"><i class="fas fa-file-invoice"></i>Outstanding Credit Bills</div>';

    if (!rows.length) {
        html += '<div class="startup-summary">'
            + '<span class="startup-pill">Bills: 0</span>'
            + '<span class="startup-pill red">Balance: &#8377;0.00</span>'
            + '</div>'
            + '<div class="startup-empty"><i class="fas fa-circle-check" style="font-size:30px;color:var(--teal);display:block;margin-bottom:8px"></i>No outstanding credit bills.</div>';
    } else {
        html += '<div class="startup-summary">'
            + '<span class="startup-pill">Bills: ' + (totals.count || rows.length) + '</span>'
            + '<span class="startup-pill">Bill Amt: &#8377;' + formatStartupMoney(totals.billamt || 0) + '</span>'
            + '<span class="startup-pill">Received: &#8377;' + formatStartupMoney(totals.ramt || 0) + '</span>'
            + '<span class="startup-pill red">Balance: &#8377;' + formatStartupMoney(totals.bal || 0) + '</span>'
            + '</div>';
        html += '<div class="startup-table-wrap"><table class="startup-table"><thead><tr>'
            + '<th>#</th><th>Bill No</th><th>Date</th><th>Party</th><th>Mobile</th><th>Due Date</th><th class="num">Bill Amt</th><th class="num">Balance</th>'
            + '</tr></thead><tbody>';
        rows.forEach(function(row, index) {
            html += '<tr>'
                + '<td>' + (index + 1) + '</td>'
                + '<td>' + escapeHtml(row.billno || '-') + '</td>'
                + '<td>' + escapeHtml(formatStartupDate(row.tdate || '')) + '</td>'
                + '<td><strong>' + escapeHtml(row.cname || row.custcode || 'Party') + '</strong><div class="il-sub">' + escapeHtml(row.custcode || '') + '</div></td>'
                + '<td>' + escapeHtml(row.mobile || '') + '</td>'
                + '<td>' + escapeHtml(formatStartupDate(row.duedate || '')) + '</td>'
                + '<td class="num">&#8377;' + formatStartupMoney(row.billamt || 0) + '</td>'
                + '<td class="num"><strong>&#8377;' + formatStartupMoney(row.bal || 0) + '</strong></td>'
                + '</tr>';
        });
        html += '</tbody></table></div>';
    }
    body.innerHTML = html;

    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
}

function startupRateCard(label, value) {
    return '<div class="startup-rate-card">'
        + '<div class="startup-rate-label">' + escapeHtml(label) + '</div>'
        + '<div class="startup-rate-value">&#8377;' + formatStartupMoney(value || 0) + '</div>'
        + '</div>';
}

function closeStartupDuePopup() {
    var overlay = document.getElementById('startupDueOverlay');
    if (!overlay) return;
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
}

function openStartupRateUpdate() {
    closeStartupDuePopup();
    showStartupRatePopup();
}

function openCreditBillDetails() {
    closeStartupDuePopup();
    var today = new Date().toISOString().slice(0, 10);
    openInDashboardFrameNew('startup-credit-bill-details', 'Credit Details', {
        mode: 'iframe',
        url: @json(url('/customers/credit-bill-details')) + '?date2=' + today + '&filter=outstanding&auto_load=1'
    });
}

function initStartupPopups() {
    if (!SHOW_STARTUP_POPUPS) return;
    window.setTimeout(showStartupDuePopup, 350);
}

function initModuleFrameClose() {
    function closeModuleFrame() {
        closeActiveModuleFrame();
    }

    window.addEventListener('message', function(event) {
        var data = event && event.data ? event.data : null;
        if (!data || typeof data !== 'object') {
            return;
        }
        if (data.type === 'goldapp:rate-exit-refresh' && isStartupRatePopupOpen()) {
            closeStartupRatePopup(true);
            return;
        }
        if (data.type === 'goldapp:rate-updated' && isStartupRatePopupOpen()) {
            refreshDashboardRates();
            return;
        }
        if (data.type === 'goldapp:close-module-frame' || data.action === 'closeModule') {
            closeModuleFrame();
            return;
        }
        if (data.type === 'goldapp:rate-exit-refresh') {
            closeModuleFrame();
            window.location.href = withCompanyContext(@json(url('/dashboard')) + '?refresh=' + Date.now());
            return;
        }
        if (data.type === 'goldapp:rate-updated') {
            fetch(withCompanyContext(@json(url('/api/rate/current'))))
                .then(function(r){ return r.json(); })
                .then(function(d){
                    var fmt = function(v){ return 'â‚¹' + parseFloat(v||0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}); };
                    document.querySelectorAll('.sbr-22 .sbr-val').forEach(function(el){ el.textContent = fmt(d.goldRate22K); });
                    document.querySelectorAll('.sbr-18 .sbr-val').forEach(function(el){ el.textContent = fmt(d.goldRate18K); });
                    document.querySelectorAll('.sbr-ag .sbr-val').forEach(function(el){ el.textContent = fmt(d.silverRate); });
                    document.querySelectorAll('.rg22 .rc-num').forEach(function(el){ el.textContent = fmt(d.goldRate22K); });
                    document.querySelectorAll('.rg18 .rc-num').forEach(function(el){ el.textContent = fmt(d.goldRate18K); });
                    document.querySelectorAll('.rgag .rc-num').forEach(function(el){ el.textContent = fmt(d.silverRate); });
                    document.querySelectorAll('.rgth .rc-num').forEach(function(el){ el.textContent = fmt(d.thRate); });
                });
            return;
        }
        if (data.type === 'goldapp:open-module-url' && data.url) {
            // Open as new tab when sender requests it, so the calling tab stays open
            if (activeTabId && !data.forceNewTab) {
                for (var i = 0; i < openTabs.length; i++) {
                    if (openTabs[i].id === activeTabId) {
                        wireIframeCloseFallback(openTabs[i].iframe);
                        openTabs[i].iframe.src = withCompanyContext(String(data.url));
                        break;
                    }
                }
            } else {
                openInDashboardFrameNew(data.moduleId || ('url-' + Date.now()), data.title || 'Module', { mode: 'iframe', url: withCompanyContext(String(data.url)) });
            }
        }
    });
}

/**
 * ============================================================
 * Dashboard Charts (Chart.js)
 * ============================================================
 */
function initDashboardCharts() {
    var trendCanvas = document.getElementById('trendChart');
    var topItemsCanvas = document.getElementById('topItemsChart');

    if (typeof Chart === 'undefined') return;

    // Sales vs Purchases â€” Bar Chart (6 months)
    if (trendCanvas) {
        trendChartInstance = new Chart(trendCanvas, {
            type: 'bar',
            data: {
                labels: dashboardData.monthLabels || dashboardData.labels || [],
                datasets: [
                    {
                        label: 'Sales',
                        data: dashboardData.monthlySalesSeries || dashboardData.salesSeries || [],
                        backgroundColor: '#1a73e8',
                        borderRadius: 6,
                        barPercentage: 0.5,
                        categoryPercentage: 0.6
                    },
                    {
                        label: 'Purchases',
                        data: dashboardData.monthlyPurchaseSeries || dashboardData.purchaseSeries || [],
                        backgroundColor: '#e53935',
                        borderRadius: 6,
                        barPercentage: 0.5,
                        categoryPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true, pointStyle: 'rectRounded',
                            color: '#565e71', font: { size: 11, family: 'Segoe UI' },
                            padding: 16
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#74777f', font: { size: 10.5 } } },
                    y: { grid: { color: '#f0f0f4' }, ticks: { color: '#74777f', font: { size: 10 } }, beginAtZero: true }
                }
            }
        });
    }

    // Top Items â€” Donut Chart
    if (topItemsCanvas) {
        var topLabels = (dashboardData.topItems || []).map(function(row) { return row.item_name || row.code || 'Item'; });
        var topValues = (dashboardData.topItems || []).map(function(row) { return Number(row.total_amount || 0); });
        var donutColors = ['#1a73e8','#1e8e3e','#e8710a','#e53935','#7b1fa2','#00897b'];

        new Chart(topItemsCanvas, {
            type: 'doughnut',
            data: {
                labels: topLabels,
                datasets: [{
                    data: topValues,
                    backgroundColor: donutColors.slice(0, topLabels.length),
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
}

function switchChart(mode) {
    if (!trendChartInstance) return;

    var use7d = mode === '7d';
    trendChartInstance.data.labels = use7d ? (dashboardData.labels || []) : (dashboardData.monthLabels || []);
    trendChartInstance.data.datasets[0].data = use7d ? (dashboardData.salesSeries || []) : (dashboardData.monthlySalesSeries || []);
    trendChartInstance.data.datasets[1].data = use7d ? (dashboardData.purchaseSeries || []) : (dashboardData.monthlyPurchaseSeries || []);
    trendChartInstance.update();

    var btn7d = document.getElementById('btn7d');
    var btn6m = document.getElementById('btn6m');
    if (btn7d) btn7d.classList.toggle('active', use7d);
    if (btn6m) btn6m.classList.toggle('active', !use7d);
}

/**
 * ============================================================
 * Financial Summary Tabs
 * ============================================================
 */
function initFinTabs() {
    var tabs = document.querySelectorAll('.fin-tab');
    var panels = {
        summary: document.getElementById('finSummary'),
        growth: document.getElementById('finGrowth'),
        profitability: document.getElementById('finProfitability'),
        cashflow: document.getElementById('finCashflow'),
        chart: document.getElementById('finChart')
    };

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            tabs.forEach(function(t) { t.classList.remove('active'); });
            tab.classList.add('active');
            var key = tab.getAttribute('data-fin');
            Object.keys(panels).forEach(function(k) {
                if (panels[k]) panels[k].classList.toggle('active', k === key);
            });
            // Init yearly chart on first view
            if (key === 'chart' && !window._yearlyChartInit) {
                initYearlyChart();
            }
        });
    });
}

function initYearlyChart() {
    var canvas = document.getElementById('yearlyChart');
    if (!canvas || typeof Chart === 'undefined') return;
    window._yearlyChartInit = true;

    var yf = dashboardData.yearlyFinancials || [];
    var labels = yf.map(function(r) { return String(r.year); }).reverse();
    var salesData = yf.map(function(r) { return r.sales; }).reverse();
    var purchData = yf.map(function(r) { return r.purchases; }).reverse();
    var grossData = yf.map(function(r) { return r.gross; }).reverse();

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Sales',
                    data: salesData,
                    backgroundColor: '#1a73e8',
                    borderRadius: 4,
                    barPercentage: 0.6
                },
                {
                    label: 'Purchases',
                    data: purchData,
                    backgroundColor: '#e53935',
                    borderRadius: 4,
                    barPercentage: 0.6
                },
                {
                    label: 'Gross Margin',
                    data: grossData,
                    type: 'line',
                    borderColor: '#1e8e3e',
                    backgroundColor: 'rgba(30,142,62,0.1)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#1e8e3e',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true, pointStyle: 'rectRounded',
                        color: '#565e71', font: { size: 11, family: 'Plus Jakarta Sans' },
                        padding: 16
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#74777f', font: { size: 11 } } },
                y: { grid: { color: '#f0f0f4' }, ticks: { color: '#74777f', font: { size: 10 } }, beginAtZero: true }
            }
        }
    });
}

/**
 * ============================================================
 * Initialize Everything
 * ============================================================
 */
try { buildMenu(); } catch (err) { console.error(err); }
try { pruneDashboardShortcuts(); } catch (err) { console.error(err); }
try { initShortcuts(); } catch (err) { console.error(err); }
try { initUserMenu(); } catch (err) { console.error(err); }
try { initPhoneLookup(); } catch (err) { console.error(err); }
try { initModuleFrameClose(); } catch (err) { console.error(err); }
try { initDashboardCharts(); } catch (err) { console.error(err); }
try { initFinTabs(); } catch (err) { console.error(err); }
try { initThemeEasterEgg(); } catch (err) { console.error(err); }
try { initStartupPopups(); } catch (err) { console.error(err); }
try { setInterval(tickClock, 1000); tickClock(); } catch (err) { console.error(err); }
</script>
</body>
</html>
