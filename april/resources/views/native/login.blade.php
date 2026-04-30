<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — {{ $shopName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Cinzel:wght@400;600;700&display=swap" rel="stylesheet">
    @php($useCompanySwitchTheme = !empty($companySwitchTheme))
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --violet:{{ $useCompanySwitchTheme ? '#6956F6' : '#B8860B' }};
            --violet-bright:{{ $useCompanySwitchTheme ? '#8777ff' : '#D4A017' }};
            --violet-pale:{{ $useCompanySwitchTheme ? '#d9d2ff' : '#F0D080' }};
            --violet-deep:{{ $useCompanySwitchTheme ? '#4f3fca' : '#7A5C00' }};
            --cream:{{ $useCompanySwitchTheme ? '#f8f7ff' : '#FAF6EE' }};
            --parchment:{{ $useCompanySwitchTheme ? '#efeefe' : '#F2EAD8' }};
            --parchment2:{{ $useCompanySwitchTheme ? '#dddaf8' : '#E8DFC8' }};
            --brown:{{ $useCompanySwitchTheme ? '#2a2458' : '#3D2B0E' }};
            --brown-mid:{{ $useCompanySwitchTheme ? '#3b3377' : '#5C3D1A' }};
            --brown-light:{{ $useCompanySwitchTheme ? '#5e55a5' : '#8B6430' }};
            --text:{{ $useCompanySwitchTheme ? '#221b4a' : '#2A1A08' }};
            --text-mid:{{ $useCompanySwitchTheme ? '#564d8d' : '#5A3E20' }};
            --border:{{ $useCompanySwitchTheme ? '#a99eff' : '#C8A84A' }};
            --shadow:{{ $useCompanySwitchTheme ? 'rgba(62,51,119,.18)' : 'rgba(61,43,14,.18)' }};
        }
        html,body{height:100%}
        body{font-family:'EB Garamond',Georgia,serif;min-height:100vh;background:var(--parchment);display:flex;background-image:radial-gradient(ellipse at 20% 50%,rgba(105,86,246,.08) 0%,transparent 60%),radial-gradient(ellipse at 80% 50%,rgba(135,119,255,.08) 0%,transparent 60%),url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Cpath d='M0 0h60v60H0z' fill='none'/%3E%3Cpath d='M30 0v60M0 30h60' stroke='%236956F6' stroke-width='.15' opacity='.20'/%3E%3C/svg%3E")}
        .wrap{display:flex;min-height:100vh;width:100%}

        /* LEFT PANEL */
        .panel{width:440px;flex-shrink:0;background:var(--violet);background-image:radial-gradient(ellipse at 30% 20%,rgba(255,255,255,.10) 0%,transparent 55%),radial-gradient(ellipse at 70% 80%,rgba(216,210,255,.18) 0%,transparent 55%),url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80'%3E%3Ccircle cx='40' cy='40' r='35' fill='none' stroke='%23d9d2ff' stroke-width='.2' opacity='.24'/%3E%3C/svg%3E");display:flex;flex-direction:column;overflow:hidden}
        .panel-topbar{height:6px;background:linear-gradient(90deg,var(--violet-deep) 0%,var(--violet) 30%,var(--violet-bright) 55%,var(--violet) 80%,var(--violet-deep) 100%);flex-shrink:0}
        .panel-inner{flex:1;display:flex;flex-direction:column;padding:40px 44px 36px;overflow:hidden}
        .brand{text-align:center;margin-bottom:28px}
        .brand-emblem{width:72px;height:72px;margin:0 auto 14px}
        .brand-emblem svg{width:100%;height:100%}
        .brand-name{font-family:'Cinzel',serif;font-size:22px;font-weight:700;color:#fff;letter-spacing:3px;text-transform:uppercase;line-height:1.2;text-shadow:0 1px 10px rgba(255,255,255,.18)}
        .brand-sub{font-family:'EB Garamond',serif;font-size:12px;color:var(--violet-pale);letter-spacing:2.5px;text-transform:uppercase;margin-top:5px;opacity:.9}
        .ornament{display:flex;align-items:center;gap:10px;margin:0 0 24px;color:var(--violet-pale);opacity:.75}
        .ornament::before,.ornament::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,transparent,var(--violet-pale),transparent)}
        .ornament span{font-size:14px;flex-shrink:0}
        .panel-tagline{font-family:'Playfair Display',serif;font-size:24px;font-weight:400;font-style:italic;color:#fff;line-height:1.45;margin-bottom:10px;text-align:center}
        .panel-tagline em{font-style:normal;color:#fff;font-weight:600}
        .panel-desc{font-family:'EB Garamond',serif;font-size:14px;color:rgba(255,255,255,.50);line-height:1.75;text-align:center;margin-bottom:24px}
        .features{list-style:none;border:1px solid rgba(217,210,255,.24);overflow:hidden;margin-bottom:28px}
        .features li{display:flex;align-items:center;gap:12px;padding:11px 16px;font-family:'EB Garamond',serif;font-size:14.5px;color:rgba(255,255,255,.78);border-bottom:1px solid rgba(217,210,255,.12)}
        .features li:last-child{border-bottom:none}
        .feat-bullet{color:#fff;font-size:12px;flex-shrink:0}
        .panel-footer{text-align:center;font-family:'EB Garamond',serif;font-size:12px;color:rgba(255,255,255,.22);line-height:1.7;margin-top:auto}
        .panel-footer a{color:rgba(217,210,255,.72);text-decoration:none}
        .panel-footer a:hover{color:#fff}
        .panel-bottombar{height:4px;background:linear-gradient(90deg,var(--violet-deep),var(--violet),var(--violet-bright),var(--violet),var(--violet-deep));opacity:.85;flex-shrink:0}

        /* RIGHT - LOGIN */
        .main{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 32px;background:var(--cream);background-image:radial-gradient(ellipse at 50% 50%,rgba(105,86,246,.05) 0%,transparent 70%),url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40'%3E%3Ccircle cx='20' cy='20' r='.8' fill='%236956F6' opacity='.18'/%3E%3C/svg%3E")}
        .card{width:100%;max-width:400px;background:#fff;border:1px solid rgba(105,86,246,.22);box-shadow:0 2px 6px rgba(62,51,119,.06),0 12px 40px rgba(62,51,119,.10);position:relative;animation:fadeUp .5s ease both}
        @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        .card::before,.card::after,.card-corner-bl,.card-corner-br{content:'';position:absolute;width:22px;height:22px;border-color:var(--violet);border-style:solid}
        .card::before{top:-1px;left:-1px;border-width:2px 0 0 2px}
        .card::after{top:-1px;right:-1px;border-width:2px 2px 0 0}
        .card-corner-bl{bottom:-1px;left:-1px;border-width:0 0 2px 2px}
        .card-corner-br{bottom:-1px;right:-1px;border-width:0 2px 2px 0}
        .card-rule{height:3px;background:linear-gradient(90deg,var(--violet-deep),var(--violet),var(--violet-pale),var(--violet),var(--violet-deep))}
        .card-body{padding:36px 40px 40px}
        .card-heading{text-align:center;margin-bottom:28px;padding-bottom:20px;border-bottom:1px solid var(--parchment2);position:relative}
        .card-heading::after{content:'◆';position:absolute;bottom:-9px;left:50%;transform:translateX(-50%);background:#fff;padding:0 8px;color:var(--violet);font-size:11px}
        .card-eyebrow{font-family:'Cinzel',serif;font-size:10px;font-weight:600;letter-spacing:3px;color:var(--violet);text-transform:uppercase;margin-bottom:8px}
        .card-title{font-family:'Playfair Display',serif;font-size:30px;font-weight:700;color:var(--brown);letter-spacing:-.3px;line-height:1.15}
        .card-subtitle{font-family:'EB Garamond',serif;font-size:14.5px;color:var(--text-mid);margin-top:6px;font-style:italic}
        .field{margin-bottom:22px}
        .field label{display:block;font-family:'Cinzel',serif;font-size:10.5px;font-weight:600;letter-spacing:1.8px;color:var(--brown-mid);text-transform:uppercase;margin-bottom:8px}
        .input-wrap{position:relative}
        .input-wrap input{width:100%;background:var(--cream);border:1px solid var(--parchment2);border-bottom:2px solid var(--parchment2);padding:11px 42px 11px 14px;font-family:'EB Garamond',serif;font-size:16px;color:var(--text);outline:none;transition:border-color .2s,background .2s,box-shadow .2s}
        .input-wrap input:focus{background:#fff;border-color:var(--violet);border-bottom-color:var(--violet);box-shadow:0 2px 0 var(--violet),0 4px 12px rgba(105,86,246,.12)}
        .input-wrap input::placeholder{color:#9a94c5;font-style:italic}
        .toggle-pw{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:#9a94c5;display:flex;align-items:center}
        .toggle-pw:hover{color:var(--violet)}
        .row-check{display:flex;align-items:center;gap:9px;margin-bottom:26px}
        .row-check input[type=checkbox]{width:15px;height:15px;accent-color:var(--violet);cursor:pointer}
        .row-check label{font-family:'EB Garamond',serif;font-size:14.5px;color:var(--text-mid);cursor:pointer}
        .btn-login{width:100%;padding:13px 20px;border:none;background:var(--violet);color:#fff;font-family:'Cinzel',serif;font-size:13px;font-weight:600;letter-spacing:3px;text-transform:uppercase;cursor:pointer;position:relative;overflow:hidden;transition:background .25s,box-shadow .25s,transform .15s;box-shadow:0 3px 12px rgba(105,86,246,.26),inset 0 1px 0 rgba(255,255,255,.10)}
        .btn-login::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.18),transparent);transition:left .5s ease}
        .btn-login:hover{background:var(--violet-deep);box-shadow:0 5px 18px rgba(105,86,246,.34);transform:translateY(-1px)}
        .btn-login:hover::before{left:100%}
        .btn-login:active{transform:translateY(0)}
        .card-note{text-align:center;margin-top:20px;font-family:'EB Garamond',serif;font-size:12.5px;color:#9189c4;font-style:italic}
        /* Software brand animated */
        .software-brand{text-align:center;margin-top:auto;margin-bottom:20px;position:relative;z-index:1}
        .sw-diamond{width:52px;height:52px;margin:0 auto 12px;animation:sw-float 3s ease-in-out infinite;filter:drop-shadow(0 4px 12px rgba(135,119,255,.45))}
        .sw-diamond svg{width:100%;height:100%}
        @keyframes sw-float{0%,100%{transform:translateY(0) rotate(0deg)}50%{transform:translateY(-8px) rotate(2deg)}}
        .sw-gem-outline{animation:sw-glow 2.5s ease-in-out infinite}
        @keyframes sw-glow{0%,100%{stroke:var(--violet-bright);stroke-width:1.2}50%{stroke:#fff;stroke-width:1.8}}
        .sw-gem-top{animation:sw-facet 3s ease-in-out infinite}
        @keyframes sw-facet{0%,100%{fill:rgba(135,119,255,.32)}50%{fill:rgba(255,255,255,.38)}}
        .sw-name{font-family:'Cinzel',serif;font-size:28px;font-weight:700;letter-spacing:6px;text-transform:uppercase;line-height:1;margin-bottom:6px;background:linear-gradient(90deg,var(--violet-pale) 0%,#fff 35%,var(--violet-pale) 70%,var(--violet-bright) 100%);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;animation:sw-shimmer 3s linear infinite;text-shadow:none}
        @keyframes sw-shimmer{to{background-position:200% center}}
        .sw-tagline{font-family:'EB Garamond',serif;font-size:13px;color:var(--violet-pale);letter-spacing:3px;text-transform:uppercase;opacity:.9;margin-bottom:10px}
        .sw-shimmer-bar{width:120px;height:2px;margin:0 auto;background:linear-gradient(90deg,transparent,#fff,transparent);background-size:200% 100%;animation:sw-bar-move 2s linear infinite;border-radius:2px}
        @keyframes sw-bar-move{from{background-position:-200% 0}to{background-position:200% 0}}

        /* Shop details */
        .shop-details{text-align:center;margin-bottom:8px}
        .shop-detail-row{display:flex;align-items:center;justify-content:center;gap:10px;padding:8px 16px;font-family:'EB Garamond',serif;font-size:15px;color:rgba(255,255,255,.75);line-height:1.5}
        .shop-icon{color:#fff;font-size:14px;flex-shrink:0}

        /* Sparkle particles */
        .sparkle{position:absolute;color:#fff;pointer-events:none;font-size:11px;line-height:1;opacity:0;animation:sparkle-rise var(--dur,2.5s) ease-in-out infinite;animation-delay:var(--del,0s);z-index:6;text-shadow:0 0 8px rgba(255,255,255,.9)}
        @keyframes sparkle-rise{0%{opacity:0;transform:translateY(8px) scale(.4) rotate(0deg)}20%{opacity:1}80%{opacity:.7}100%{opacity:0;transform:translateY(-55px) scale(1.1) rotate(180deg)}}

        /* Floating gold dust */
        .panel-inner{position:relative;overflow:hidden}
        .panel-inner::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100'%3E%3Ccircle cx='50' cy='50' r='1' fill='%23ffffff' opacity='.16'/%3E%3Ccircle cx='20' cy='30' r='.5' fill='%23d9d2ff' opacity='.22'/%3E%3Ccircle cx='80' cy='70' r='.7' fill='%238777ff' opacity='.18'/%3E%3C/svg%3E");animation:dust-drift 20s linear infinite;pointer-events:none;z-index:0}
        @keyframes dust-drift{from{background-position:0 0}to{background-position:100px 200px}}
        .brand,.ornament,.shop-details,.panel-tagline,.panel-desc,.features,.panel-footer{position:relative;z-index:1}

        .error-msg{background:#fde8e8;border:1px solid #e8a0a0;color:#7a2020;padding:10px 14px;margin-bottom:18px;font-size:14px;text-align:center}
        @media(max-width:860px){.panel{display:none}.main{background:var(--parchment)}}
        @media(max-width:480px){.card-body{padding:28px 22px 32px}}
    </style>
</head>
<body>
<div class="wrap">

<aside class="panel">
    <div class="panel-topbar"></div>
    <div class="panel-inner">
        <div class="brand">
            <div class="brand-emblem">
                <svg viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="36" cy="36" r="34" stroke="#C8A84A" stroke-width=".8" stroke-dasharray="4 3" opacity=".4"/>
                    <circle cx="36" cy="36" r="28" stroke="#C8A84A" stroke-width=".5" opacity=".3"/>
                    <polygon points="36,8 60,30 36,64 12,30" fill="none" stroke="#D4A017" stroke-width="1.2"/>
                    <polygon points="36,8 60,30 36,34 12,30" fill="rgba(212,160,23,.25)" stroke="#D4A017" stroke-width=".6"/>
                    <polygon points="36,34 60,30 36,64" fill="rgba(122,92,0,.35)" stroke="#C8A84A" stroke-width=".5"/>
                    <polygon points="36,34 12,30 36,64" fill="rgba(184,134,11,.20)" stroke="#C8A84A" stroke-width=".5"/>
                    <line x1="12" y1="30" x2="60" y2="30" stroke="#F0D080" stroke-width=".9"/>
                </svg>
            </div>
            <div class="brand-name">{{ $shopName }}</div>
            <div class="brand-sub">Jewellery ERP</div>
        </div>

        <div class="ornament"><span>&#9671;</span></div>

        {{-- Shop Details --}}
        <div class="shop-details">
            @if (!empty($shopAddr))
            <div class="shop-detail-row">
                <span class="shop-icon">&#9878;</span>
                <span>{{ $shopAddr }}</span>
            </div>
            @endif
            @if (!empty($shopPhone))
            <div class="shop-detail-row">
                <span class="shop-icon">&#9742;</span>
                <span>{{ $shopPhone }}</span>
            </div>
            @endif
            @if (!empty($shopGst))
            <div class="shop-detail-row">
                <span class="shop-icon">&#9830;</span>
                <span>GSTIN: {{ $shopGst }}</span>
            </div>
            @endif
        </div>

        {{-- Animated Software Brand --}}
        <div class="software-brand">
            <div class="sw-diamond">
                <svg viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <polygon points="30,4 54,22 30,56 6,22" fill="none" stroke="#D4A017" stroke-width="1.2" class="sw-gem-outline"/>
                    <polygon points="30,4 54,22 30,28 6,22" fill="rgba(212,160,23,.3)" class="sw-gem-top"/>
                    <polygon points="30,28 54,22 30,56" fill="rgba(122,92,0,.4)" class="sw-gem-right"/>
                    <polygon points="30,28 6,22 30,56" fill="rgba(184,134,11,.25)" class="sw-gem-left"/>
                    <line x1="6" y1="22" x2="54" y2="22" stroke="#F0D080" stroke-width=".8"/>
                </svg>
            </div>
            <div class="sw-name">GOLD PLUS</div>
            <div class="sw-tagline">Jewellery ERP</div>
            <div class="sw-shimmer-bar"></div>
        </div>

        <div class="panel-footer">
            &copy; {{ date('Y') }} {{ $shopName }}. All rights reserved.<br>
            Powered by <a href="https://proaims.com/" target="_blank" rel="noopener">Proaims</a> &mdash; Gold Plus ERP
        </div>

        {{-- Floating sparkle particles --}}
        <div class="sparkle" style="left:15%;top:20%;--dur:3s;--del:0s">&#10022;</div>
        <div class="sparkle" style="left:75%;top:35%;--dur:3.5s;--del:.8s">&#10022;</div>
        <div class="sparkle" style="left:40%;top:55%;--dur:2.8s;--del:1.5s">&#10022;</div>
        <div class="sparkle" style="left:85%;top:70%;--dur:3.2s;--del:.4s">&#10022;</div>
        <div class="sparkle" style="left:25%;top:80%;--dur:4s;--del:2s">&#10022;</div>
        <div class="sparkle" style="left:60%;top:15%;--dur:3.6s;--del:1.2s">&#10022;</div>
        <div class="sparkle" style="left:10%;top:60%;--dur:2.5s;--del:2.5s">&#10022;</div>
        <div class="sparkle" style="left:50%;top:90%;--dur:3.8s;--del:.6s">&#10022;</div>
    </div>
    <div class="panel-bottombar"></div>
</aside>

<main class="main">
    <div class="card">
        <div class="card-corner-bl"></div>
        <div class="card-corner-br"></div>
        <div class="card-rule"></div>

        <div class="card-body">
            <div class="card-heading">
                <div class="card-eyebrow">Jewellery ERP System</div>
                <div class="card-title">Welcome Back</div>
                <div class="card-subtitle">Sign in to {{ $shopName }} to continue</div>
            </div>

            @if ($errors->any())
                <div class="error-msg">
                    @foreach ($errors->all() as $error)
                        {{ $error }}
                    @endforeach
                </div>
            @endif

            <form method="post" action="{{ url('/login') }}" id="loginForm">
                @csrf

                <div class="field">
                    <label>Password</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password" autofocus required>
                        <button type="button" class="toggle-pw" onclick="togglePw()" title="Show / hide password">
                            <svg id="eye-icon" width="17" height="17" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="row-check">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Keep me signed in</label>
                </div>

                <button class="btn-login" type="submit">Sign In</button>
            </form>

            <div class="card-note">&#9670; &nbsp; {{ $shopName }} &mdash; Jewellery ERP &nbsp; &#9670;</div>
        </div>
    </div>
</main>

</div>

<script>
try {
    window.sessionStorage.removeItem('goldapp_browser_session');
} catch (error) {
}

function togglePw() {
    var inp = document.getElementById('password');
    var ico = document.getElementById('eye-icon');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        inp.type = 'password';
        ico.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}

var loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', function() {
        try {
            window.sessionStorage.setItem('goldapp_browser_session', 'active');
        } catch (error) {
        }
    });
}
</script>
</body>
</html>
