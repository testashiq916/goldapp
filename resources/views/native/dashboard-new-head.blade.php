<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $siteName }} - User {{ strtoupper($userName) }}</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Karla:ital,wght@0,300;0,400;0,500;0,600;1,300&family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
:root{--bg:#f0f2f5;--bg2:#e8eaee;--surface:#ffffff;--surface2:#f7f8fa;--surface3:#eef0f3;--line:rgba(0,0,0,0.07);--line2:rgba(0,0,0,0.12);--teal:#007d7a;--teal2:#009e9a;--teal3:#b2e4e3;--teal-pale:rgba(0,125,122,0.08);--teal-mid:rgba(0,125,122,0.15);--navy:#12233e;--navy2:#1c3154;--navy3:#2a4270;--gold:#c09318;--gold2:#e0ae24;--gold3:#f5d060;--gold-pale:rgba(192,147,24,0.10);--green:#1a7a52;--green-pale:#d6f0e4;--red:#b83232;--red-pale:#fde8e8;--amber:#c06818;--amber-pale:#fdeede;--blue:#1755a8;--blue-pale:#ddeafc;--purple:#6433a0;--purple-pale:#ede0fc;--ink:#0d1117;--ink2:#2d3748;--ink3:#64748b;--ink4:#94a3b8;--ff-head:'Syne',sans-serif;--ff-body:'Karla',sans-serif;--ff-mono:'Fira Code',monospace;--r:14px;--r-sm:10px;--r-xs:6px;--r-pill:999px;--s1:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);--s2:0 4px 12px rgba(0,0,0,0.07),0 2px 4px rgba(0,0,0,0.04);--s3:0 10px 30px rgba(0,0,0,0.09),0 4px 10px rgba(0,0,0,0.05);--s4:0 20px 60px rgba(0,0,0,0.14),0 8px 20px rgba(0,0,0,0.07);--s-teal:0 6px 24px rgba(0,125,122,0.22);--ez:cubic-bezier(0.16,1,0.3,1)}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;overflow:hidden}
body{font-family:var(--ff-body);background:var(--bg);color:var(--ink);font-size:13px;line-height:1.5;-webkit-font-smoothing:antialiased}
::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--line2);border-radius:10px}
.shell{display:grid;grid-template-columns:220px 1fr;height:100vh;animation:shellIn .6s var(--ez) both}
@keyframes shellIn{from{opacity:0;transform:scale(.98)}to{opacity:1;transform:scale(1)}}

/* SIDEBAR */
.sidebar{background:var(--navy);display:flex;flex-direction:column;overflow:hidden;position:relative;z-index:100}
.sidebar::before{content:'';position:absolute;top:-60px;right:-60px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(0,158,154,.18) 0%,transparent 70%);pointer-events:none}
.sidebar::after{content:'';position:absolute;bottom:-40px;left:-40px;width:160px;height:160px;border-radius:50%;background:radial-gradient(circle,rgba(192,147,24,.12) 0%,transparent 70%);pointer-events:none}
.sidebar-brand{padding:20px 18px 16px;border-bottom:1px solid rgba(255,255,255,.07);position:relative;z-index:1}
.sb-logo{display:flex;align-items:center;gap:10px;margin-bottom:4px}
.sb-gem{width:36px;height:36px;background:linear-gradient(135deg,var(--teal2),var(--teal));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff;box-shadow:var(--s-teal);flex-shrink:0;animation:gemPulse 3s ease infinite}
@keyframes gemPulse{0%,100%{box-shadow:0 6px 24px rgba(0,125,122,.22)}50%{box-shadow:0 6px 32px rgba(0,158,154,.45)}}
.sb-title{font-family:var(--ff-head);font-size:13.5px;font-weight:800;color:#fff;letter-spacing:.3px;line-height:1.2}
.sb-rates{padding:12px 18px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;gap:7px;position:relative;z-index:1}
.sbr-row{display:flex;align-items:center;justify-content:space-between}
.sbr-label{font-size:9.5px;font-weight:600;letter-spacing:1.2px;text-transform:uppercase;color:rgba(255,255,255,.4);display:flex;align-items:center;gap:5px}
.sbr-dot{width:5px;height:5px;border-radius:50%;animation:dotPulse 2s ease infinite}
@keyframes dotPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.5)}}
.sbr-val{font-family:var(--ff-mono);font-size:12px;font-weight:500}
.sbr-22 .sbr-dot{background:var(--gold3);box-shadow:0 0 6px var(--gold2)}.sbr-22 .sbr-val{color:var(--gold3)}
.sbr-18 .sbr-dot{background:#f4a94e;box-shadow:0 0 6px #e07a20}.sbr-18 .sbr-val{color:#f4a94e}
.sbr-ag .sbr-dot{background:#c8d0dc}.sbr-ag .sbr-val{color:#c8d0dc}
.sb-nav{flex:1;overflow-y:auto;padding:10px;position:relative;z-index:1}
.sb-nav::-webkit-scrollbar{width:3px}.sb-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1)}
.sb-section-label{font-size:8.5px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.25);padding:10px 8px 4px}
.sb-link{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-xs);color:rgba(255,255,255,.55);font-size:12.5px;font-weight:500;cursor:pointer;transition:all .18s var(--ez);position:relative;user-select:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-link:hover{background:rgba(255,255,255,.07);color:rgba(255,255,255,.9);padding-left:14px}
.sb-link.active{background:rgba(0,158,154,.2);color:#fff}
.sb-link.active::before{content:'';position:absolute;left:0;top:4px;bottom:4px;width:3px;background:var(--teal2);border-radius:0 3px 3px 0}
.sb-link i{width:16px;text-align:center;font-size:11.5px;flex-shrink:0}
.sb-link.active i{color:var(--teal2)}.sb-link:hover i{color:rgba(255,255,255,.8)}
.sb-footer{padding:12px 18px;border-top:1px solid rgba(255,255,255,.07);position:relative;z-index:1}
.sb-user{display:flex;align-items:center;gap:9px;cursor:pointer;padding:6px 8px;border-radius:var(--r-xs);transition:background .18s;position:relative}
.sb-user:hover{background:rgba(255,255,255,.06)}
.sb-avatar{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,rgba(0,158,154,.5),rgba(18,35,62,.8));border:1.5px solid rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff;flex-shrink:0}
.sb-uname{font-size:12px;font-weight:600;color:rgba(255,255,255,.8)}
.sb-urole{font-size:9.5px;color:rgba(255,255,255,.35);letter-spacing:.5px}
.sb-user-menu{position:absolute;bottom:100%;right:0;min-width:180px;background:var(--surface);border:1px solid var(--line2);border-radius:var(--r-sm);box-shadow:var(--s4);display:none;z-index:999;overflow:hidden;animation:popUp .18s var(--ez)}
@keyframes popUp{from{opacity:0;transform:translateY(8px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.sb-user-menu.open{display:block}
.sb-user-menu a{display:flex;align-items:center;gap:9px;padding:10px 14px;color:var(--ink2);text-decoration:none;font-size:12.5px;font-weight:500;border-bottom:1px solid var(--line);transition:all .12s}
.sb-user-menu a:last-child{border-bottom:none}
.sb-user-menu a:hover{background:var(--surface2);color:var(--ink);padding-left:18px}
.sb-user-menu a i{color:var(--teal);width:14px;text-align:center;font-size:11px}

/* MAIN */
.main{display:grid;grid-template-rows:46px 38px 1fr 24px;overflow:hidden;background:var(--bg)}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:0 20px;background:var(--surface);border-bottom:1px solid var(--line);box-shadow:var(--s1);z-index:50}
.topbar-left{display:flex;align-items:center;gap:16px}
.page-title{font-family:var(--ff-head);font-size:15px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px}
.page-title i{color:var(--teal);font-size:13px}
.breadcrumb{font-size:11px;color:var(--ink4);display:flex;align-items:center;gap:4px}
.breadcrumb span:last-child{color:var(--teal);font-weight:600}
.topbar-right{display:flex;align-items:center;gap:10px}
.tb-icon-btn{width:32px;height:32px;border-radius:var(--r-xs);border:1px solid var(--line);background:var(--surface2);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12px;color:var(--ink3);transition:all .18s;position:relative}
.tb-icon-btn:hover{background:var(--teal-pale);border-color:var(--teal3);color:var(--teal);transform:translateY(-1px);box-shadow:var(--s2)}
.notif-badge{position:absolute;top:-3px;right:-3px;width:14px;height:14px;background:var(--red);border-radius:50%;font-size:7.5px;font-weight:700;color:#fff;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--surface)}
.date-chip{display:flex;align-items:center;gap:6px;padding:5px 12px;background:var(--navy);border-radius:var(--r-pill);font-family:var(--ff-mono);font-size:10.5px;color:rgba(255,255,255,.75)}
.date-chip i{color:var(--teal2);font-size:10px}

/* MENUBAR */
.menubar{display:flex;align-items:center;gap:1px;padding:0 16px;background:var(--surface2);border-bottom:1px solid var(--line);overflow:visible;position:relative;z-index:200}
.menu-item{position:relative;height:100%;display:flex;align-items:center;padding:0 12px;font-family:var(--ff-head);font-size:11.5px;font-weight:600;letter-spacing:.3px;color:var(--ink3);cursor:pointer;white-space:nowrap;border-radius:var(--r-xs);margin:5px 1px;transition:all .15s var(--ez);user-select:none}
.menu-item::after{content:'';position:absolute;bottom:-1px;left:8px;right:8px;height:2px;background:linear-gradient(90deg,var(--teal),var(--teal2));border-radius:2px;transform:scaleX(0);transform-origin:left;transition:transform .25s var(--ez)}
.menu-item:hover,.menu-item.open{color:var(--teal);background:var(--teal-pale)}
.menu-item:hover::after,.menu-item.open::after{transform:scaleX(1)}
.dropdown{position:absolute;top:calc(100% + 4px);left:0;background:var(--surface);border:1px solid var(--line2);border-radius:var(--r-sm);box-shadow:var(--s4);min-width:230px;padding:5px;z-index:500;display:none;animation:dropIn .17s var(--ez)}
@keyframes dropIn{from{opacity:0;transform:translateY(-6px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.dropdown.show{display:block}
.dd-item{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 11px;font-size:12px;font-weight:500;border-radius:var(--r-xs);cursor:pointer;color:var(--ink2);transition:all .12s;position:relative;white-space:nowrap}
.dd-item:hover{background:var(--teal-pale);color:var(--teal);padding-left:15px}
.dd-left{display:inline-flex;align-items:center;gap:9px;flex:1}
.dd-left i{width:14px;text-align:center;font-size:11px;color:var(--teal2);flex-shrink:0;transition:transform .18s var(--ez)}
.dd-item:hover .dd-left i{transform:scale(1.2)}
.dd-key{font-family:var(--ff-mono);font-size:9px;color:var(--ink4);background:var(--surface3);border:1px solid var(--line);border-radius:3px;padding:1px 5px}
.submenu{position:absolute;left:100%;top:-5px;background:var(--surface);border:1px solid var(--line2);border-radius:var(--r-sm);box-shadow:var(--s4);min-width:220px;padding:5px;z-index:600;display:none;animation:dropIn .15s var(--ez)}
.dd-item:hover>.submenu{display:block}

/* CONTENT */
.content{overflow:hidden;position:relative;background:var(--bg)}
.home-screen{position:absolute;inset:0;overflow-y:auto;padding:18px 20px 24px;display:flex;flex-direction:column;gap:14px}

/* MODULE FRAME SCREEN */
.module-frame-screen{position:absolute;inset:0;display:none;z-index:2000;background:var(--bg);flex-direction:column}
.module-frame-screen.show-frame{display:flex}
.module-frame-head{height:40px;display:flex;align-items:center;gap:12px;padding:0 18px;background:var(--surface);border-bottom:1px solid var(--line);box-shadow:var(--s1);flex-shrink:0}
.module-frame-title{font-family:var(--ff-head);font-size:13px;font-weight:700;color:var(--ink)}
.module-frame-path{margin-left:auto;font-family:var(--ff-mono);font-size:10px;color:var(--ink4);background:var(--surface3);border:1px solid var(--line);border-radius:var(--r-pill);padding:2px 12px;max-width:380px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.module-frame-close{height:28px;padding:0 14px;background:var(--surface2);border:1px solid var(--line2);border-radius:var(--r-xs);color:var(--ink2);font-family:var(--ff-body);font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all .15s}
.module-frame-close:hover{background:var(--red-pale);border-color:rgba(184,50,50,.3);color:var(--red)}
.module-frame-iframe{flex:1;width:100%;border:0;background:#fff}

/* STATUSBAR */
.statusbar{display:flex;align-items:center;background:var(--navy);font-size:10px;color:rgba(255,255,255,.4);font-family:var(--ff-mono)}
.sb-seg{display:flex;align-items:center;gap:5px;padding:0 14px;border-right:1px solid rgba(255,255,255,.06);height:100%;white-space:nowrap}
.sb-seg i{font-size:8.5px;color:var(--teal2)}
.sb-online .sb-live{display:inline-block;width:5px;height:5px;background:var(--teal2);border-radius:50%;box-shadow:0 0 6px var(--teal2);animation:dotPulse 2s ease infinite}
.sb-right{margin-left:auto;border-right:none!important}
.sb-hi{color:var(--teal3);font-weight:500}

/* DASHBOARD CARDS */
.dash-row{animation:rowUp .55s var(--ez) both}
.dash-row:nth-child(1){animation-delay:.18s}.dash-row:nth-child(2){animation-delay:.26s}.dash-row:nth-child(3){animation-delay:.34s}.dash-row:nth-child(4){animation-delay:.42s}.dash-row:nth-child(5){animation-delay:.50s}
@keyframes rowUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
.quick-bar{display:flex;align-items:center;gap:6px;padding:10px 16px;background:var(--surface);border-radius:var(--r);border:1px solid var(--line);box-shadow:var(--s1);overflow-x:auto;flex-wrap:wrap}
.qb-heading{font-family:var(--ff-head);font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--ink4);white-space:nowrap;padding-right:8px;border-right:1px solid var(--line2);margin-right:4px}
.qb-btn{display:inline-flex;align-items:center;gap:6px;height:30px;padding:0 14px;border-radius:var(--r-pill);border:1px solid var(--line);background:var(--surface2);color:var(--ink2);font-family:var(--ff-body);font-size:11.5px;font-weight:600;cursor:pointer;white-space:nowrap;transition:all .2s var(--ez)}
.qb-btn:hover{transform:translateY(-2px);box-shadow:var(--s2);border-color:var(--line2)}
.qb-btn:active{transform:translateY(0)}
.qb-btn i{font-size:11px}
.qb-btn.t-teal{background:var(--teal);border-color:var(--teal);color:#fff}
.qb-btn.t-teal:hover{background:var(--teal2);box-shadow:var(--s-teal)}
.qb-btn.t-navy{background:var(--navy);border-color:var(--navy);color:#fff}
.qb-sep{width:1px;height:20px;background:var(--line2);margin:0 4px;flex-shrink:0}
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.stat-card{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:18px 20px;box-shadow:var(--s1);transition:all .25s var(--ez);position:relative;overflow:hidden;cursor:default}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;transition:height .25s var(--ez)}
.sc-teal::before{background:linear-gradient(90deg,var(--teal),var(--teal2))}.sc-gold::before{background:linear-gradient(90deg,var(--gold),var(--gold3))}.sc-green::before{background:linear-gradient(90deg,var(--green),#3ab07a)}.sc-amber::before{background:linear-gradient(90deg,var(--amber),#e8941a)}
.stat-card:hover{box-shadow:var(--s3);transform:translateY(-3px)}.stat-card:hover::before{height:5px}
.stat-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
.stat-label{font-size:9.5px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--ink4)}
.stat-icon{width:36px;height:36px;border-radius:var(--r-xs);display:flex;align-items:center;justify-content:center;font-size:14px;transition:transform .25s var(--ez)}
.stat-card:hover .stat-icon{transform:scale(1.12) rotate(-4deg)}
.si-teal{background:var(--teal-pale);color:var(--teal)}.si-gold{background:var(--gold-pale);color:var(--gold)}.si-green{background:var(--green-pale);color:var(--green)}.si-amber{background:var(--amber-pale);color:var(--amber)}
.stat-val{font-family:var(--ff-head);font-size:26px;font-weight:800;color:var(--ink);letter-spacing:-.5px;line-height:1;margin-bottom:6px}
.stat-foot{display:flex;align-items:center;gap:8px}
.stat-sub{font-size:10.5px;color:var(--ink4);font-weight:500}
.stat-progress{flex:1;height:3px;background:var(--bg2);border-radius:999px;overflow:hidden}
.stat-bar{height:100%;border-radius:999px;animation:barGrow 1.2s .6s var(--ez) both}
@keyframes barGrow{from{width:0%}}
.sc-teal .stat-bar{background:linear-gradient(90deg,var(--teal),var(--teal2));width:35%}.sc-gold .stat-bar{background:linear-gradient(90deg,var(--gold),var(--gold3));width:22%}.sc-green .stat-bar{background:linear-gradient(90deg,var(--green),#3ab07a);width:48%}.sc-amber .stat-bar{background:linear-gradient(90deg,var(--amber),#e8941a);width:14%}
.rate-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.rate-card{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:14px 18px;box-shadow:var(--s1);transition:all .22s var(--ez)}
.rate-card:hover{box-shadow:var(--s2);transform:translateY(-2px)}
.rc-lbl{display:flex;align-items:center;gap:6px;font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--ink4);margin-bottom:8px}
.rg22 .rc-lbl i{color:var(--gold2)}.rg18 .rc-lbl i{color:var(--amber)}.rgag .rc-lbl i{color:var(--ink3)}.rgth .rc-lbl i{color:var(--purple)}
.rc-num{font-family:var(--ff-head);font-size:24px;font-weight:800;letter-spacing:-.5px;line-height:1}
.rg22 .rc-num{color:var(--gold)}.rg18 .rc-num{color:var(--amber)}.rgag .rc-num{color:var(--ink2)}.rgth .rc-num{color:var(--purple)}
.rc-unit{font-size:10px;color:var(--ink4);margin-top:5px;font-weight:500}
.analytics-row{display:grid;grid-template-columns:1.7fr 1fr;gap:14px}
.panel{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:20px;box-shadow:var(--s1);transition:box-shadow .22s}
.panel:hover{box-shadow:var(--s2)}
.panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.panel-title{font-family:var(--ff-head);font-size:14px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px}
.panel-title i{font-size:12px;color:var(--teal2)}
.panel-pills{display:flex;gap:4px}
.ppill{font-size:10px;font-weight:700;font-family:var(--ff-head);padding:3px 10px;border-radius:var(--r-pill);background:var(--surface3);border:1px solid var(--line);color:var(--ink3);cursor:pointer;transition:all .15s}
.ppill.active,.ppill:hover{background:var(--teal);border-color:var(--teal);color:#fff}
.chart-wrap{position:relative;height:265px}
.bottom-row{display:grid;grid-template-columns:1fr 1fr .85fr;gap:14px}
.info-panel{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:18px 20px;box-shadow:var(--s1);display:flex;flex-direction:column;min-height:192px;transition:box-shadow .22s}
.info-panel:hover{box-shadow:var(--s2)}
.ip-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:13px}
.ip-title{font-family:var(--ff-head);font-size:13px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px}
.ip-title i{font-size:12px;color:var(--teal2)}
.ip-link{font-size:10.5px;font-weight:700;color:var(--teal);cursor:pointer;font-family:var(--ff-head);transition:color .15s}
.ip-link:hover{color:var(--teal2);text-decoration:underline}
.ip-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;color:var(--ink4);font-size:11.5px}
.ip-empty i{font-size:24px;color:var(--line2)}
.qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;flex:1}
.qa-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;padding:11px 6px;border:1px solid var(--line);border-radius:var(--r-sm);background:var(--surface2);cursor:pointer;font-family:var(--ff-body);transition:all .2s var(--ez)}
.qa-btn:hover{transform:translateY(-2px);box-shadow:var(--s2)}
.qa-btn i{font-size:16px;transition:transform .2s var(--ez)}.qa-btn:hover i{transform:scale(1.2)}
.qa-btn span{font-size:9.5px;font-weight:700;color:var(--ink2);letter-spacing:.3px;font-family:var(--ff-head)}
.qa-teal i{color:var(--teal)}.qa-gold i{color:var(--gold)}.qa-green i{color:var(--green)}.qa-blue i{color:var(--blue)}.qa-amber i{color:var(--amber)}.qa-red i{color:var(--red)}
@media print{.sidebar,.topbar,.menubar,.statusbar{display:none}.shell{grid-template-columns:1fr}body{overflow:visible}}
</style>
</head>
<body>
<div class="shell">
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sb-logo">
            <div class="sb-gem"><i class="fas fa-gem"></i></div>
            <div>
                <div class="sb-title">SEVENS</div>
                <div style="font-family:var(--ff-head);font-size:10px;font-weight:700;color:rgba(255,255,255,.5);letter-spacing:1.5px">GOLD &amp; DIAMONDS</div>
            </div>
        </div>
    </div>
    <div class="sb-rates">
        <div class="sbr-row sbr-22"><span class="sbr-label"><span class="sbr-dot"></span>22K Gold</span><span class="sbr-val">&#8377;{{ number_format($dashboardData['goldRate22K'] ?? 0, 2) }}</span></div>
        <div class="sbr-row sbr-18"><span class="sbr-label"><span class="sbr-dot"></span>18K Gold</span><span class="sbr-val">&#8377;{{ number_format($dashboardData['goldRate18K'] ?? 0, 2) }}</span></div>
        <div class="sbr-row sbr-ag"><span class="sbr-label"><span class="sbr-dot"></span>Silver</span><span class="sbr-val">&#8377;{{ number_format($dashboardData['silverRate'] ?? 0, 2) }}</span></div>
    </div>
    <nav class="sb-nav">
        <div class="sb-section-label">Main</div>
        <div class="sb-link active" onclick="goHome()"><i class="fas fa-home"></i> Dashboard</div>
        <div class="sb-link" onclick="openModule('sales-bill','Sales Bill')"><i class="fas fa-cart-shopping"></i> Sales Bill</div>
        <div class="sb-link" onclick="openModule('purchase-bill','Purchase Bill')"><i class="fas fa-bag-shopping"></i> Purchase Bill</div>
        <div class="sb-link" onclick="openModule('sales-return-bill','Sales Return')"><i class="fas fa-rotate-left"></i> Sales Return</div>
        <div class="sb-section-label">Accounts</div>
        <div class="sb-link" onclick="openModule('accounts-receipt','Receipt')"><i class="fas fa-hand-holding-dollar"></i> Receipt</div>
        <div class="sb-link" onclick="openModule('accounts-payment','Payment')"><i class="fas fa-money-bill-transfer"></i> Payment</div>
        <div class="sb-link" onclick="openModule('accounts-journal','Journal')"><i class="fas fa-book"></i> Journal</div>
        <div class="sb-link" onclick="openModule('day-book','Day Book')"><i class="fas fa-calendar-day"></i> Day Book</div>
        <div class="sb-section-label">Masters</div>
        <div class="sb-link" onclick="openModule('customer','Customer')"><i class="fas fa-users"></i> Customers</div>
        <div class="sb-link" onclick="openModule('supplier','Supplier')"><i class="fas fa-truck"></i> Suppliers</div>
        <div class="sb-link" onclick="openModule('item-add-edit','Items')"><i class="fas fa-gem"></i> Items</div>
        <div class="sb-link" onclick="openModule('goldsmith-add','Goldsmith')"><i class="fas fa-hammer"></i> Goldsmith</div>
        <div class="sb-section-label">Reports</div>
        <div class="sb-link" onclick="openModule('profit-loss','Profit & Loss')"><i class="fas fa-chart-pie"></i> P&amp;L</div>
        <div class="sb-link" onclick="openModule('balance-sheet','Balance Sheet')"><i class="fas fa-scale-balanced"></i> Balance Sheet</div>
        <div class="sb-link" onclick="openModule('accounts-reports-ac-ledger','A/c Ledger')"><i class="fas fa-book-open"></i> A/c Ledger</div>
        <div class="sb-link" onclick="openModule('accounts-reports-cash-book','Cash Book')"><i class="fas fa-money-bill-wave"></i> Cash Book</div>
        <div class="sb-section-label">Tools</div>
        <div class="sb-link" onclick="openModule('gold-rate-update','Rate Update')"><i class="fas fa-coins"></i> Update Rates</div>
        <div class="sb-link" onclick="openModule('ai-insights','AI Insights')"><i class="fas fa-robot"></i> AI Insights</div>
        <div class="sb-link" onclick="openModule('administration','Administration')"><i class="fas fa-user-gear"></i> Administration</div>
    </nav>
    <div class="sb-footer">
        <div class="sb-user" id="sbUser">
            <div class="sb-avatar"><i class="fas fa-user"></i></div>
            <div><div class="sb-uname">{{ strtoupper($userName) }}</div><div class="sb-urole">{{ $userLevel ?? 'MGR' }} &middot; PROAIMS</div></div>
            <i class="fas fa-ellipsis-v" style="margin-left:auto;color:rgba(255,255,255,.3);font-size:11px"></i>
            <div class="sb-user-menu" id="sbUserMenu">
                <a href="{{ url('/admin/users/'.($userName ?? 'MGR').'/edit') }}"><i class="fas fa-key"></i>Change Password</a>
                <a href="{{ url('/user-access') }}"><i class="fas fa-lock"></i>User Access</a>
                <a href="{{ url('/admin/users') }}"><i class="fas fa-users-cog"></i>User Management</a>
                <a href="{{ url('/logout') }}"><i class="fas fa-right-from-bracket"></i>Sign Out</a>
            </div>
        </div>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="page-title"><i class="fas fa-chart-line"></i> <span id="pageTitle">Dashboard</span></div>
            <div class="breadcrumb"><span>SEVENS</span><i class="fas fa-chevron-right" style="font-size:8px"></i><span id="pageBreadcrumb">Overview</span></div>
        </div>
        <div class="topbar-right">
            <div class="tb-icon-btn" title="AI Insights" onclick="openModule('ai-insights','AI Insights')"><i class="fas fa-robot"></i></div>
            <div class="tb-icon-btn" title="Day Summary" onclick="openModule('day-summary','Day Summary')"><i class="fas fa-chart-column"></i></div>
            <div class="tb-icon-btn" title="Settings" onclick="openModule('application-settings','Settings')"><i class="fas fa-gear"></i></div>
            <div class="date-chip"><i class="fas fa-calendar"></i><span id="topDate"></span></div>
        </div>
    </header>

    <nav class="menubar" id="menuBar"></nav>

    <div class="content">
        <div class="home-screen" id="homeScreen">
            @php
                $prevSales = $dashboardData['prevMonthSales'] ?? 0;
                $curSales  = $dashboardData['monthSales'] ?? 0;
            @endphp
            <div class="quick-bar dash-row">
                <span class="qb-heading">Quick Actions</span>
                <button class="qb-btn t-teal" onclick="openModule('sales-bill','Sales Bill')"><i class="fas fa-cart-shopping"></i>New Sale</button>
                <button class="qb-btn t-navy" onclick="openModule('purchase-bill','Purchase Bill')"><i class="fas fa-bag-shopping"></i>Purchase</button>
                <button class="qb-btn" onclick="openModule('sales-return-bill','Sales Return')"><i class="fas fa-rotate-left"></i>Sales Return</button>
                <button class="qb-btn" onclick="openModule('purchase-return-bill','Purchase Return')"><i class="fas fa-reply"></i>Purch. Return</button>
                <div class="qb-sep"></div>
                <button class="qb-btn" onclick="openModule('accounts-receipt','Receipt')"><i class="fas fa-hand-holding-dollar"></i>Receipt</button>
                <button class="qb-btn" onclick="openModule('accounts-payment','Payment')"><i class="fas fa-money-bill-transfer"></i>Payment</button>
                <button class="qb-btn" onclick="openModule('accounts-journal','Journal')"><i class="fas fa-book"></i>Journal</button>
                <div class="qb-sep"></div>
                <button class="qb-btn" onclick="openModule('gold-rate-update','Rate Update')"><i class="fas fa-coins"></i>Update Rates</button>
            </div>
            <div class="stat-row dash-row">
                <div class="stat-card sc-teal"><div class="stat-top"><div class="stat-label">Today Sales</div><div class="stat-icon si-teal"><i class="fas fa-cart-shopping"></i></div></div><div class="stat-val">&#8377;{{ number_format($dashboardData['todaySales'] ?? 0, 2) }}</div><div class="stat-foot"><span class="stat-sub">{{ (int)($dashboardData['todaySalesBills'] ?? 0) }} bills today</span><div class="stat-progress"><div class="stat-bar"></div></div></div></div>
                <div class="stat-card sc-gold"><div class="stat-top"><div class="stat-label">Today Purchase</div><div class="stat-icon si-gold"><i class="fas fa-bag-shopping"></i></div></div><div class="stat-val">&#8377;{{ number_format($dashboardData['todayPurchase'] ?? 0, 2) }}</div><div class="stat-foot"><span class="stat-sub">{{ (int)($dashboardData['todayPurchaseBills'] ?? 0) }} bills today</span><div class="stat-progress"><div class="stat-bar"></div></div></div></div>
                <div class="stat-card sc-green"><div class="stat-top"><div class="stat-label">Month Sales</div><div class="stat-icon si-green"><i class="fas fa-chart-line"></i></div></div><div class="stat-val">&#8377;{{ number_format($dashboardData['monthSales'] ?? 0, 2) }}</div><div class="stat-foot"><span class="stat-sub">vs prev. month</span><div class="stat-progress"><div class="stat-bar"></div></div></div></div>
                <div class="stat-card sc-amber"><div class="stat-top"><div class="stat-label">Month Purchase</div><div class="stat-icon si-amber"><i class="fas fa-truck"></i></div></div><div class="stat-val">&#8377;{{ number_format($dashboardData['monthPurchase'] ?? 0, 2) }}</div><div class="stat-foot"><span class="stat-sub">Current month</span><div class="stat-progress"><div class="stat-bar"></div></div></div></div>
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
                <div class="info-panel"><div class="ip-head"><div class="ip-title"><i class="fas fa-receipt"></i>Recent Sales</div><span class="ip-link" onclick="openModule('sales-bill','Sales Bill')">View all &rarr;</span></div><div class="ip-empty"><i class="fas fa-receipt"></i>No recent sales</div></div>
                <div class="info-panel"><div class="ip-head"><div class="ip-title"><i class="fas fa-clock"></i>Pending Accounts</div><span class="ip-link" onclick="openModule('customer','Customer')">View all &rarr;</span></div><div class="ip-empty"><i class="fas fa-hourglass-half"></i>No pending accounts</div></div>
                <div class="info-panel"><div class="ip-head"><div class="ip-title"><i class="fas fa-bolt"></i>Quick Actions</div></div>
                    <div class="qa-grid">
                        <button class="qa-btn qa-teal" onclick="openModule('sales-bill','Sales Bill')"><i class="fas fa-plus-circle"></i><span>New Sale</span></button>
                        <button class="qa-btn qa-gold" onclick="openModule('purchase-bill','Purchase Bill')"><i class="fas fa-truck"></i><span>Purchase</span></button>
                        <button class="qa-btn qa-green" onclick="openModule('customer','Customer')"><i class="fas fa-users"></i><span>Customer</span></button>
                        <button class="qa-btn qa-blue" onclick="openModule('day-book','Day Book')"><i class="fas fa-book"></i><span>Day Book</span></button>
                        <button class="qa-btn qa-amber" onclick="openModule('ai-insights','AI Insights')"><i class="fas fa-robot"></i><span>AI Insights</span></button>
                        <button class="qa-btn qa-red" onclick="openModule('backup','Backup')"><i class="fas fa-database"></i><span>Backup</span></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="module-frame-screen" id="moduleFrameScreen">
            <div class="module-frame-head">
                <span class="module-frame-title" id="moduleFrameTitle">Module</span>
                <span class="module-frame-path" id="moduleFramePath"></span>
                <button class="module-frame-close" id="moduleFrameClose" type="button" onclick="closeActiveModuleFrame()"><i class="fas fa-times"></i> Close</button>
            </div>
            <iframe class="module-frame-iframe" id="moduleFrame"></iframe>
        </div>
    </div>

    <footer class="statusbar">
        <span class="sb-seg sb-online"><span class="sb-live"></span>&thinsp;Online</span>
        <span class="sb-seg"><i class="fas fa-user"></i><span class="sb-hi">{{ strtoupper($userName) }}</span>&thinsp;({{ $userLevel ?? 'MGR' }})</span>
        <span class="sb-seg"><i class="fas fa-building"></i>{{ $companyName ?? 'SEVENS GOLD AND DIAMONDS' }}</span>
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

// Sidebar link highlighting
function goHome() {
    document.getElementById('moduleFrameScreen').classList.remove('show-frame');
    document.getElementById('moduleFrameScreen').style.display = 'none';
    document.getElementById('homeScreen').style.display = 'flex';
    document.querySelectorAll('.sb-link').forEach(function(l){ l.classList.remove('active'); });
    document.querySelector('.sb-link').classList.add('active');
    document.getElementById('pageTitle').textContent = 'Dashboard';
    document.getElementById('pageBreadcrumb').textContent = 'Overview';
}

function closeActiveModuleFrame() {
    var frameScreen = document.getElementById('moduleFrameScreen');
    var frame = document.getElementById('moduleFrame');
    if (frame) {
        frame.onload = null;
        frame.src = 'about:blank';
    }
    if (frameScreen) {
        frameScreen.style.display = 'none';
        frameScreen.classList.remove('show-frame');
    }
    goHome();
}

function wireIframeCloseFallback(frame) {
    if (!frame) return;
    frame.onload = function() {
        try {
            var doc = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
            if (!doc) return;
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
                if (el.dataset.goldappCloseBound === '1') return;
                el.dataset.goldappCloseBound = '1';
                el.addEventListener('click', function() {
                    window.setTimeout(closeActiveModuleFrame, 0);
                });
            });
        } catch (err) {}
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

// Override openInDashboardFrame to use new layout
var _origOpenInFrame = null;
function openInDashboardFrameNew(moduleId, title, cfg) {
    var frameScreen = document.getElementById('moduleFrameScreen');
    var frame = document.getElementById('moduleFrame');
    var frameTitle = document.getElementById('moduleFrameTitle');
    var framePath = document.getElementById('moduleFramePath');
    var homeScreen = document.getElementById('homeScreen');
    var targetUrl = cfg.url || ('app://module/' + moduleId);

    frameTitle.textContent = title;
    framePath.textContent = targetUrl;
    framePath.title = targetUrl;
    document.getElementById('pageTitle').innerHTML = '<i class="fas fa-cube" style="color:var(--teal);font-size:13px"></i> ' + title;
    document.getElementById('pageBreadcrumb').textContent = moduleId;
    wireIframeCloseFallback(frame);

    if (cfg.mode === 'iframe') {
        frame.src = cfg.url;
    } else {
        var html = '<!doctype html><html><head><meta charset="utf-8"><title>' + title + '</title>' +
            '<style>body{font-family:Segoe UI,Tahoma,sans-serif;margin:0;padding:28px;background:#f4f7fb;color:#213548}' +
            '.card{max-width:760px;margin:0 auto;background:#fff;border:1px solid #d7e2ef;border-radius:10px;padding:18px}' +
            'h2{margin:0 0 8px;font-size:22px} p{margin:6px 0;font-size:14px}</style></head>' +
            '<body><div class="card"><h2>' + title + '</h2><p>Module key: <strong>' + moduleId + '</strong></p>' +
            '<p>This module page is not implemented yet.</p></div></body></html>';
        frame.src = 'data:text/html;charset=utf-8,' + encodeURIComponent(html);
    }

    homeScreen.style.display = 'none';
    frameScreen.style.display = 'flex';
    frameScreen.classList.add('show-frame');

    // Highlight sidebar if matching
    document.querySelectorAll('.sb-link').forEach(function(l){ l.classList.remove('active'); });
}
</script>
