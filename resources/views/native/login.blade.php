<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — {{ $shopName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Cinzel:wght@400;600;700&display=swap" rel="stylesheet">
    @php($useCompanySwitchTheme = !empty($companySwitchTheme))
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { height: 100%; }

        :root {
            --gold:        {{ $useCompanySwitchTheme ? '#6956F6' : '#B8860B' }};
            --gold-mid:    {{ $useCompanySwitchTheme ? '#8777ff' : '#C9962A' }};
            --gold-bright: {{ $useCompanySwitchTheme ? '#a99eff' : '#D4A017' }};
            --gold-pale:   {{ $useCompanySwitchTheme ? '#d9d2ff' : '#E8C96A' }};
            --panel-bg:    {{ $useCompanySwitchTheme ? '#eae6ff' : '#F2E8D0' }};
            --cream:       {{ $useCompanySwitchTheme ? '#f8f7ff' : '#FDFAF6' }};
            --card-bg:     #ffffff;
            --brown:       {{ $useCompanySwitchTheme ? '#2a2458' : '#3D2B0E' }};
            --brown-mid:   {{ $useCompanySwitchTheme ? '#3b3377' : '#5C3D1A' }};
            --text-mid:    {{ $useCompanySwitchTheme ? '#564d8d' : '#6B4E2A' }};
            --text:        {{ $useCompanySwitchTheme ? '#221b4a' : '#2A1A08' }};
            --border:      {{ $useCompanySwitchTheme ? '#a99eff' : '#D4A017' }};
            --shadow:      {{ $useCompanySwitchTheme ? 'rgba(62,51,119,.14)' : 'rgba(61,43,14,.10)' }};
            --gp-bg:       {{ $useCompanySwitchTheme ? '#1a1640' : '#0D0A04' }};
            --gp-name:     {{ $useCompanySwitchTheme ? '#a99eff' : '#E8B84B' }};
            --gp-sub:      {{ $useCompanySwitchTheme ? 'rgba(169,158,255,.65)' : 'rgba(201,150,42,.65)' }};
            --btn-grad:    {{ $useCompanySwitchTheme ? 'linear-gradient(135deg,#4f3fca,#6956F6 40%,#8777ff 70%,#4f3fca)' : 'linear-gradient(135deg,#9A6F00 0%,#C9962A 40%,#D4A017 70%,#9A6F00 100%)' }};
            --btn-shadow:  {{ $useCompanySwitchTheme ? 'rgba(105,86,246,.35)' : 'rgba(180,130,10,.32)' }};
            --feat-border: {{ $useCompanySwitchTheme ? 'rgba(105,86,246,.18)' : 'rgba(201,150,42,.18)' }};
            /* Marble blob colours */
            --blob1: {{ $useCompanySwitchTheme ? 'rgba(105,86,246,.28)' : 'rgba(212,160,23,.32)' }};
            --blob2: {{ $useCompanySwitchTheme ? 'rgba(135,119,255,.22)' : 'rgba(184,134,11,.22)' }};
            --blob3: {{ $useCompanySwitchTheme ? 'rgba(169,158,255,.18)' : 'rgba(232,200,100,.20)' }};
            --blob4: {{ $useCompanySwitchTheme ? 'rgba(79,63,202,.20)' : 'rgba(154,111,0,.18)' }};
        }

        body { font-family: 'EB Garamond', Georgia, serif; min-height: 100vh; display: flex; }

        /* ══════════════════════════════════
           LEFT PANEL — Animated Marble
        ══════════════════════════════════ */
        .left-panel {
            width: 42%;
            flex-shrink: 0;
            background: var(--panel-bg);
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 48px 44px 36px;
            overflow: hidden;
        }

        /* ── Animated liquid marble blobs ── */
        .marble-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(52px);
            pointer-events: none;
            will-change: transform, opacity;
        }
        .blob-1 {
            width: 340px; height: 340px;
            top: -80px; left: -100px;
            background: radial-gradient(circle, var(--blob1) 0%, transparent 70%);
            animation: blobDrift1 18s ease-in-out infinite;
        }
        .blob-2 {
            width: 280px; height: 280px;
            top: 30%; right: -80px;
            background: radial-gradient(circle, var(--blob2) 0%, transparent 70%);
            animation: blobDrift2 22s ease-in-out infinite;
        }
        .blob-3 {
            width: 320px; height: 320px;
            bottom: 10%; left: -60px;
            background: radial-gradient(circle, var(--blob3) 0%, transparent 70%);
            animation: blobDrift3 26s ease-in-out infinite;
        }
        .blob-4 {
            width: 220px; height: 220px;
            top: 55%; right: 20%;
            background: radial-gradient(circle, var(--blob4) 0%, transparent 70%);
            animation: blobDrift4 20s ease-in-out infinite;
        }
        .blob-5 {
            width: 180px; height: 180px;
            top: 20%; left: 30%;
            background: radial-gradient(circle, var(--blob3) 0%, transparent 70%);
            animation: blobDrift5 15s ease-in-out infinite;
        }

        @keyframes blobDrift1 {
            0%,100% { transform: translate(0,0) scale(1); }
            33%      { transform: translate(60px,80px) scale(1.12); }
            66%      { transform: translate(-30px,120px) scale(.92); }
        }
        @keyframes blobDrift2 {
            0%,100% { transform: translate(0,0) scale(1); }
            40%      { transform: translate(-80px,60px) scale(1.18); }
            70%      { transform: translate(40px,-40px) scale(.88); }
        }
        @keyframes blobDrift3 {
            0%,100% { transform: translate(0,0) scale(1); }
            30%      { transform: translate(70px,-60px) scale(1.1); }
            65%      { transform: translate(-50px,40px) scale(1.06); }
        }
        @keyframes blobDrift4 {
            0%,100% { transform: translate(0,0) scale(1) rotate(0deg); }
            50%      { transform: translate(-60px,-80px) scale(1.2) rotate(15deg); }
        }
        @keyframes blobDrift5 {
            0%,100% { transform: translate(0,0) scale(1); }
            50%      { transform: translate(40px,50px) scale(1.15); }
        }

        /* ── Animated SVG wave lines ── */
        .wave-canvas {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: visible;
        }
        .wave-path {
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            animation: waveDrift 12s ease-in-out infinite;
        }
        .w1 { stroke: var(--gold-mid); stroke-width: 1.2; opacity: .30; animation-duration: 14s; }
        .w2 { stroke: var(--gold-bright); stroke-width: .8; opacity: .22; animation-duration: 18s; animation-delay: -4s; }
        .w3 { stroke: var(--gold-mid); stroke-width: 1.5; opacity: .18; animation-duration: 22s; animation-delay: -8s; }
        .w4 { stroke: var(--gold-pale); stroke-width: .6; opacity: .26; animation-duration: 16s; animation-delay: -2s; }
        .w5 { stroke: var(--gold-bright); stroke-width: 1; opacity: .20; animation-duration: 20s; animation-delay: -6s; }

        @keyframes waveDrift {
            0%,100% { d: path("M-60,160 Q120,60 280,220 Q440,380 660,160"); }
            50%      { d: path("M-60,200 Q140,80 300,180 Q460,280 660,200"); }
        }

        /* ── Floating sparkle particles ── */
        .sparkle {
            position: absolute;
            pointer-events: none;
            font-size: 10px;
            color: var(--gold-bright);
            opacity: 0;
            animation: sparklePop var(--dur,3s) ease-in-out infinite var(--del,0s);
            text-shadow: 0 0 8px var(--gold-mid);
            z-index: 3;
        }
        @keyframes sparklePop {
            0%   { opacity:0; transform: translateY(6px) scale(.3) rotate(0deg); }
            20%  { opacity:.9; }
            80%  { opacity:.6; }
            100% { opacity:0; transform: translateY(-60px) scale(1.1) rotate(200deg); }
        }

        /* ── Content layer ── */
        .left-content {
            position: relative;
            z-index: 2;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }

        /* Brand diamond — float animation */
        .brand-icon {
            width: 80px; height: 80px;
            margin: 0 auto 16px;
            animation: diamondFloat 4s ease-in-out infinite;
            filter: drop-shadow(0 6px 18px var(--gold-mid));
        }
        @keyframes diamondFloat {
            0%,100% { transform: translateY(0) rotate(0deg); }
            50%      { transform: translateY(-10px) rotate(1.5deg); }
        }

        .brand-name {
            font-family: 'Cinzel', serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--brown);
            letter-spacing: 2px;
            text-transform: uppercase;
            text-align: center;
            line-height: 1.25;
        }
        .brand-sub {
            font-family: 'EB Garamond', serif;
            font-size: 11px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--gold-mid);
            text-align: center;
            margin-top: 6px;
            margin-bottom: 24px;
        }

        .divider {
            display: flex; align-items: center; gap: 12px;
            width: 100%; margin-bottom: 28px;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px;
            background: var(--gold-mid); opacity: .38;
        }
        .divider-gem { color: var(--gold-mid); font-size: 12px; }

        .shop-info { width: 100%; margin-bottom: auto; }
        .shop-info-row {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 8px 0;
            font-family: 'EB Garamond', serif; font-size: 15.5px;
            color: var(--text-mid); line-height: 1.55;
        }
        .shop-icon { color: var(--gold-mid); flex-shrink: 0; margin-top: 2px; }

        /* Gold Plus card — shimmer + glow */
        .goldplus-card {
            width: 100%;
            background: linear-gradient(135deg, var(--gp-bg) 0%, #0A0804 60%, var(--gp-bg) 100%);
            border-radius: 14px;
            padding: 22px 28px;
            text-align: center;
            margin-top: 28px; margin-bottom: 28px;
            box-shadow: 0 10px 36px rgba(0,0,0,.4), inset 0 1px 0 rgba(201,150,42,.18);
            position: relative; overflow: hidden;
            animation: cardPulseGlow 4s ease-in-out infinite;
        }
        @keyframes cardPulseGlow {
            0%,100% { box-shadow: 0 10px 36px rgba(0,0,0,.4), 0 0 0 0 rgba(201,150,42,0); }
            50%      { box-shadow: 0 14px 48px rgba(0,0,0,.45), 0 0 24px 4px rgba(201,150,42,.12); }
        }
        .goldplus-card::after {
            content: ''; position: absolute; inset: 0; border-radius: 14px;
            border: 1px solid rgba(201,150,42,.25); pointer-events: none;
        }
        /* Shimmer sweep across card */
        .goldplus-card::before {
            content: ''; position: absolute;
            top: 0; left: -120%;
            width: 60%; height: 100%;
            background: linear-gradient(105deg, transparent 40%, rgba(255,255,255,.08) 50%, transparent 60%);
            animation: shimmerSweep 4s ease-in-out infinite 1s;
            pointer-events: none; z-index: 1;
        }
        @keyframes shimmerSweep {
            0%   { left: -120%; }
            60%,100% { left: 160%; }
        }

        .goldplus-icon { width: 38px; height: 38px; margin: 0 auto 10px; position: relative; z-index: 2; }
        .goldplus-name {
            font-family: 'Cinzel', serif;
            font-size: 22px; font-weight: 700;
            letter-spacing: 5px; text-transform: uppercase;
            line-height: 1; margin-bottom: 6px;
            position: relative; z-index: 2;
            background: linear-gradient(90deg, var(--gp-name) 0%, #fff 40%, var(--gp-name) 65%, #E8C060 100%);
            background-size: 200% auto;
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: goldShimmer 3s linear infinite;
        }
        @keyframes goldShimmer { to { background-position: 200% center; } }
        .goldplus-sub {
            font-family: 'EB Garamond', serif;
            font-size: 11px; letter-spacing: 3.5px; text-transform: uppercase;
            color: var(--gp-sub); position: relative; z-index: 2;
        }

        .left-footer {
            text-align: center; font-family: 'EB Garamond', serif;
            font-size: 12.5px; color: var(--text-mid); opacity: .55; line-height: 1.65;
        }
        .left-footer a { color: var(--gold-mid); text-decoration: none; }
        .left-footer a:hover { color: var(--gold-bright); }

        /* ══════════════════════════════════
           RIGHT PANEL
        ══════════════════════════════════ */
        .right-panel {
            flex: 1;
            background: var(--cream);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 40px 32px 0;
            position: relative;
            overflow-x: hidden;
        }
        /* Subtle animated gradient wash on right */
        .right-panel::before {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background: radial-gradient(ellipse 60% 60% at 50% 40%, var(--blob3) 0%, transparent 70%);
            animation: rightGlow 10s ease-in-out infinite;
        }
        @keyframes rightGlow {
            0%,100% { opacity:.5; transform: scale(1) translateY(0); }
            50%      { opacity:.8; transform: scale(1.06) translateY(-20px); }
        }

        .right-inner {
            position: relative; z-index: 1;
            flex: 1; display: flex;
            align-items: center; justify-content: center;
            width: 100%; padding-bottom: 32px;
        }

        /* Login card */
        .login-card {
            width: 100%; max-width: 460px;
            background: var(--card-bg);
            border: 1.5px solid var(--border);
            border-radius: 5px;
            padding: 44px 48px;
            box-shadow: 0 4px 28px var(--shadow), 0 1px 4px rgba(0,0,0,.04);
            animation: fadeUp .5s cubic-bezier(.22,1,.36,1) both;
            position: relative;
        }
        @keyframes fadeUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }

        /* Animated gold border glow on card */
        .login-card::before {
            content: ''; position: absolute; inset: -1px; border-radius: 6px;
            background: linear-gradient(135deg, var(--border), transparent 40%, transparent 60%, var(--border));
            background-size: 200% 200%;
            animation: borderShimmer 5s linear infinite;
            z-index: -1; opacity: .55;
        }
        @keyframes borderShimmer {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Eyebrow pill */
        .eyebrow-badge {
            display: table; margin: 0 auto 20px;
            border: 1px solid var(--border); border-radius: 100px;
            padding: 5px 22px;
            font-family: 'Cinzel', serif; font-size: 9px; font-weight: 600;
            letter-spacing: 2.5px; text-transform: uppercase;
            color: var(--gold-mid);
            animation: badgePop .6s cubic-bezier(.34,1.56,.64,1) .3s both;
        }
        @keyframes badgePop { from { opacity:0; transform:scale(.7); } to { opacity:1; transform:scale(1); } }

        .card-title {
            font-family: 'Playfair Display', serif;
            font-size: 38px; font-weight: 700;
            color: var(--brown); text-align: center;
            line-height: 1.1; margin-bottom: 8px;
            animation: fadeUp .5s ease .2s both;
        }
        .card-subtitle {
            font-family: 'EB Garamond', serif; font-size: 16px;
            color: var(--text-mid); text-align: center;
            font-style: italic; margin-bottom: 22px;
            animation: fadeUp .5s ease .3s both;
        }

        .card-ornament {
            display: flex; align-items: center; gap: 14px; margin-bottom: 28px;
        }
        .card-ornament::before, .card-ornament::after {
            content: ''; flex: 1; height: 1px;
            background: var(--border); opacity: .32;
        }
        .card-ornament-gem {
            color: var(--gold-mid); font-size: 14px;
            animation: gemSpin 8s linear infinite;
        }
        @keyframes gemSpin {
            0%,100% { transform: rotate(0deg) scale(1); }
            25%      { transform: rotate(90deg) scale(1.2); }
            50%      { transform: rotate(180deg) scale(1); }
            75%      { transform: rotate(270deg) scale(1.2); }
        }

        /* Form */
        .field { margin-bottom: 20px; animation: fadeUp .4s ease .4s both; }
        .field-label {
            display: block;
            font-family: 'Cinzel', serif; font-size: 10px; font-weight: 600;
            letter-spacing: 2px; text-transform: uppercase;
            color: var(--brown-mid); margin-bottom: 9px;
        }
        .input-wrap { position: relative; }
        .input-wrap input {
            width: 100%; background: #FDFAF5;
            border: 1.5px solid #DDD0B0; border-radius: 3px;
            padding: 13px 46px 13px 16px;
            font-family: 'EB Garamond', serif; font-size: 16px; color: var(--text);
            outline: none; transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .input-wrap input:focus {
            background: #fff; border-color: var(--gold-mid);
            box-shadow: 0 0 0 3px rgba(201,150,42,.13), 0 2px 12px rgba(201,150,42,.10);
        }
        .input-wrap input::placeholder { color: #B8A882; font-style: italic; }

        .toggle-pw {
            position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; padding: 8px;
            color: #B8A882; display: flex; align-items: center; transition: color .18s;
            touch-action: manipulation; -webkit-tap-highlight-color: transparent;
        }
        .toggle-pw:hover { color: var(--gold-mid); }

        .row-check {
            display: flex; align-items: center; gap: 10px; margin-bottom: 24px;
            animation: fadeUp .4s ease .5s both;
        }
        .row-check input[type=checkbox] { width: 16px; height: 16px; accent-color: var(--gold-mid); cursor: pointer; }
        .row-check label { font-family: 'EB Garamond', serif; font-size: 15px; color: var(--text-mid); cursor: pointer; }

        /* Sign-in button */
        .btn-login {
            width: 100%; padding: 14px 20px; border: none; border-radius: 3px;
            background: var(--btn-grad); color: #fff;
            font-family: 'Cinzel', serif; font-size: 13px; font-weight: 700;
            letter-spacing: 4px; text-transform: uppercase;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 14px;
            transition: opacity .2s, box-shadow .2s, transform .15s;
            box-shadow: 0 4px 18px var(--btn-shadow);
            position: relative; overflow: hidden;
            animation: fadeUp .4s ease .6s both;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-login::after {
            content: ''; position: absolute;
            top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.18), transparent);
            transition: left .55s ease;
        }
        .btn-login:hover::after { left: 100%; }
        .btn-login:hover {
            opacity: .91;
            box-shadow: 0 8px 28px var(--btn-shadow);
            transform: translateY(-2px);
        }
        .btn-login:active { transform: translateY(0); }

        .btn-arrow {
            width: 28px; height: 28px;
            border: 1.5px solid rgba(255,255,255,.55); border-radius: 50%;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            transition: transform .3s ease;
        }
        .btn-login:hover .btn-arrow { transform: translateX(4px); }

        .card-note {
            text-align: center; margin-top: 20px;
            font-family: 'EB Garamond', serif; font-size: 13px;
            color: var(--text-mid); font-style: italic; opacity: .65;
            animation: fadeUp .4s ease .7s both;
        }

        .error-msg {
            background: #fde8e8; border: 1px solid #e8a0a0; color: #7a2020;
            padding: 10px 14px; margin-bottom: 18px;
            font-size: 14px; text-align: center; border-radius: 3px;
        }

        /* Features strip */
        .features-strip {
            position: relative; z-index: 1;
            width: 100%; max-width: 560px;
            display: grid; grid-template-columns: repeat(3, 1fr);
            padding: 24px 0 36px;
            border-top: 1px solid var(--feat-border);
        }
        .feature-item {
            display: flex; flex-direction: column; align-items: flex-start;
            padding: 0 22px;
            border-right: 1px solid var(--feat-border);
            animation: fadeUp .5s ease calc(.8s + var(--fi-delay,.0s)) both;
        }
        .feature-item:first-child { padding-left: 0; }
        .feature-item:last-child { border-right: none; }
        .feature-icon {
            width: 40px; height: 40px;
            border: 1.5px solid var(--feat-border); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 10px; color: var(--gold-mid);
            transition: border-color .3s, box-shadow .3s;
        }
        .feature-item:hover .feature-icon {
            border-color: var(--gold-mid);
            box-shadow: 0 0 14px rgba(201,150,42,.22);
        }
        .feature-title {
            font-family: 'Cinzel', serif; font-size: 11px; font-weight: 600;
            color: var(--brown); margin-bottom: 4px; letter-spacing: .4px;
        }
        .feature-desc { font-family: 'EB Garamond', serif; font-size: 13.5px; color: var(--text-mid); line-height: 1.5; }

        /* ── Tablet (600–900 px): left panel hidden, full-width login, scrollable ── */
        @media (max-width: 900px) {
            .left-panel { display: none; }
            .right-panel {
                padding: 40px 28px 0;
                justify-content: flex-start;
            }
            .right-inner {
                flex: none;
                width: 100%;
                padding-bottom: 24px;
                padding-top: 20px;
            }
            .login-card {
                max-width: 520px;
                padding: 40px 44px;
            }
            .features-strip {
                max-width: 100%;
                padding: 20px 0 32px;
                grid-template-columns: repeat(3, 1fr);
            }
        }
        /* ── Small phone (< 560 px) ── */
        @media (max-width: 560px) {
            .right-panel { padding: 24px 16px 0; }
            .login-card { padding: 32px 22px; }
            .card-title { font-size: 30px; }
            .features-strip { grid-template-columns: 1fr; gap: 16px; }
            .feature-item { border-right: none; border-bottom: 1px solid var(--feat-border); padding: 0 0 16px; }
            .feature-item:last-child { border-bottom: none; }
        }
    </style>
</head>
<body>

<!-- ═══════════════════════ LEFT PANEL ═══════════════════════ -->
<aside class="left-panel">

    <!-- Animated marble blobs -->
    <div class="marble-blob blob-1"></div>
    <div class="marble-blob blob-2"></div>
    <div class="marble-blob blob-3"></div>
    <div class="marble-blob blob-4"></div>
    <div class="marble-blob blob-5"></div>

    <!-- Animated SVG wave lines -->
    <svg class="wave-canvas" viewBox="0 0 600 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
        <path class="wave-path w1" d="M-60,160 Q120,60 280,220 Q440,380 660,160"/>
        <path class="wave-path w2" d="M-60,300 Q80,160 260,340 Q440,520 660,300"/>
        <path class="wave-path w3" d="M-60,460 Q160,310 320,460 Q480,610 660,440"/>
        <path class="wave-path w4" d="M-60,620 Q140,470 320,590 Q500,710 660,600"/>
        <path class="wave-path w5" d="M-60,780 Q180,640 360,740 Q540,840 660,760"/>
        <path class="wave-path w1" d="M80,-40 Q180,160 140,360 Q100,560 260,720" style="animation-duration:19s"/>
        <path class="wave-path w2" d="M380,-40 Q480,180 440,400 Q400,620 560,800" style="animation-duration:25s;animation-delay:-5s"/>
    </svg>

    <!-- Floating sparkle particles -->
    <span class="sparkle" style="left:12%;top:18%;--dur:3.2s;--del:0s">&#10022;</span>
    <span class="sparkle" style="left:72%;top:28%;--dur:3.8s;--del:.8s">&#10022;</span>
    <span class="sparkle" style="left:38%;top:50%;--dur:2.9s;--del:1.5s">&#10022;</span>
    <span class="sparkle" style="left:82%;top:62%;--dur:3.4s;--del:.3s">&#10022;</span>
    <span class="sparkle" style="left:22%;top:74%;--dur:4.1s;--del:2.1s">&#10022;</span>
    <span class="sparkle" style="left:58%;top:12%;--dur:3.6s;--del:1.1s">&#10022;</span>
    <span class="sparkle" style="left:8%; top:58%;--dur:2.6s;--del:2.5s">&#10022;</span>
    <span class="sparkle" style="left:48%;top:88%;--dur:3.9s;--del:.6s">&#10022;</span>
    <span class="sparkle" style="left:90%;top:40%;--dur:2.8s;--del:1.8s">&#10022;</span>
    <span class="sparkle" style="left:30%;top:95%;--dur:4.3s;--del:3s">&#10022;</span>

    <div class="left-content">

        <!-- Brand diamond icon -->
        <div class="brand-icon">
            <svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="40" cy="40" r="37" stroke="#C9962A" stroke-width=".7" stroke-dasharray="3 3" opacity=".28"/>
                <circle cx="40" cy="40" r="30" stroke="#C9962A" stroke-width=".45" opacity=".22"/>
                <polygon points="40,9 67,34 40,71 13,34" fill="none" stroke="#C9962A" stroke-width="1.4"/>
                <polygon points="40,9 67,34 40,39 13,34" fill="rgba(201,150,42,.16)" stroke="#D4A017" stroke-width=".7"/>
                <polygon points="40,39 67,34 40,71" fill="rgba(100,60,0,.20)" stroke="#C9962A" stroke-width=".55"/>
                <polygon points="40,39 13,34 40,71" fill="rgba(184,134,11,.13)" stroke="#C9962A" stroke-width=".55"/>
                <line x1="13" y1="34" x2="67" y2="34" stroke="#E8C060" stroke-width="1"/>
                <path d="M40 3 L41 6.5 L44 4.5 L42 7 L45.5 8 L42 9 L44 11.5 L41 9.5 L40 13 L39 9.5 L36 11.5 L38 9 L34.5 8 L38 7 L36 4.5 L39 6.5 Z" fill="#E8C060" opacity=".75"/>
            </svg>
        </div>

        <div class="brand-name">{{ $shopName }}</div>
        <div class="brand-sub">Jewellery ERP</div>

        <div class="divider"><span class="divider-gem">&#9670;</span></div>

        <!-- Shop details -->
        <div class="shop-info">
            @if (!empty($shopAddr))
            <div class="shop-info-row">
                <span class="shop-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                    </svg>
                </span>
                <span>{{ $shopAddr }}</span>
            </div>
            @endif
            @if (!empty($shopPhone))
            <div class="shop-info-row">
                <span class="shop-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.41 2 2 0 0 1 3.6 1.24h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.82a16 16 0 0 0 6.29 6.29l.95-.95a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                </span>
                <span>{{ $shopPhone }}</span>
            </div>
            @endif
            @if (!empty($shopGst))
            <div class="shop-info-row">
                <span class="shop-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
                    </svg>
                </span>
                <span>GSTIN: {{ $shopGst }}</span>
            </div>
            @endif
        </div>

        <!-- Gold Plus brand card -->
        <div class="goldplus-card">
            <div class="goldplus-icon">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <polygon points="20,4 36,16 20,36 4,16" fill="none" stroke="#E8B84B" stroke-width="1.3"/>
                    <polygon points="20,4 36,16 20,20 4,16" fill="rgba(232,184,75,.22)"/>
                    <polygon points="20,20 36,16 20,36" fill="rgba(100,60,0,.32)"/>
                    <polygon points="20,20 4,16 20,36" fill="rgba(201,150,42,.18)"/>
                    <line x1="4" y1="16" x2="36" y2="16" stroke="#E8B84B" stroke-width=".9"/>
                </svg>
            </div>
            <div class="goldplus-name">Gold Plus</div>
            <div class="goldplus-sub">Jewellery ERP</div>
        </div>

        <div class="left-footer">
            &copy; {{ date('Y') }} {{ $shopName }}. All rights reserved.<br>
            Powered by <a href="https://proaims.com/" target="_blank" rel="noopener">Proaims</a> &bull; Gold Plus Jewellery ERP
        </div>

    </div>
</aside>

<!-- ═══════════════════════ RIGHT PANEL ═══════════════════════ -->
<main class="right-panel">

    <div class="right-inner">
        <div class="login-card">

            <div class="eyebrow-badge">Jewellery ERP System</div>

            <div class="card-title">Welcome Back</div>
            <div class="card-subtitle">Sign in to {{ $shopName }}<br>to continue</div>

            <div class="card-ornament"><span class="card-ornament-gem">&#9670;</span></div>

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
                    <label class="field-label" for="password">Password</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password" autofocus required>
                        <button type="button" class="toggle-pw" onclick="togglePw()" title="Show / hide password">
                            <svg id="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
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

                <button class="btn-login" type="submit">
                    <span>Sign In</span>
                    <span class="btn-arrow">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </span>
                </button>
            </form>

            <div class="card-note">&#9670; &nbsp; {{ $shopName }} &mdash; Jewellery ERP &nbsp; &#9670;</div>

        </div>
    </div>

    <!-- Features strip -->
    <div class="features-strip">
        <div class="feature-item" style="--fi-delay:.0s">
            <div class="feature-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <div class="feature-title">Secure Login</div>
            <div class="feature-desc">Your data is protected with enterprise security</div>
        </div>
        <div class="feature-item" style="--fi-delay:.1s">
            <div class="feature-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <div class="feature-title">Trusted Access</div>
            <div class="feature-desc">Authorized users only with role-based access</div>
        </div>
        <div class="feature-item" style="--fi-delay:.2s">
            <div class="feature-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="20" x2="18" y2="10"/>
                    <line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>
            </div>
            <div class="feature-title">Smart ERP</div>
            <div class="feature-desc">Smarter operations. Better decisions</div>
        </div>
    </div>

</main>

<script>
try { window.sessionStorage.removeItem('goldapp_browser_session'); } catch(e) {}

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
        try { window.sessionStorage.setItem('goldapp_browser_session', 'active'); } catch(e) {}
    });
}
</script>
</body>
</html>
