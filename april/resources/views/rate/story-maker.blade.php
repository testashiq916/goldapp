<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gold Rate Story Maker</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Great+Vibes&family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #d4a017;
            --primary-light: #f5d57b;
            --primary-dark: #8b6914;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --surface-3: #f1f5f9;
            --border: #e2e8f0;
            --text: #0f172a;
            --text-2: #475569;
            --text-3: #94a3b8;
            --radius: 16px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,.08);
            --shadow-lg: 0 12px 40px rgba(0,0,0,.12);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: Inter, -apple-system, sans-serif; background: var(--surface-3); color: var(--text); min-height: 100vh; }

        /* ── Top Bar ── */
        .topbar {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 16px 24px;
            display: flex; justify-content: space-between; align-items: center; gap: 16px;
            position: sticky; top: 0; z-index: 50;
            box-shadow: 0 2px 20px rgba(0,0,0,.2);
        }
        .topbar-title { color: #fff; font-size: 18px; font-weight: 800; letter-spacing: -.02em; }
        .topbar-title span { color: var(--primary-light); }
        .topbar-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 18px; border: none; border-radius: 10px;
            font-size: 13px; font-weight: 700; cursor: pointer;
            transition: all .15s ease;
        }
        .btn-gold {
            background: linear-gradient(135deg, #f5d57b, #d4a017);
            color: #3d2800; box-shadow: 0 2px 12px rgba(212,160,23,.3);
        }
        .btn-gold:hover { transform: translateY(-1px); box-shadow: 0 4px 20px rgba(212,160,23,.4); }
        .btn-outline { background: rgba(255,255,255,.1); color: #fff; border: 1px solid rgba(255,255,255,.2); }
        .btn-outline:hover { background: rgba(255,255,255,.18); }
        .btn-success { background: #22c55e; color: #fff; }
        .btn-success:hover { background: #16a34a; }
        .btn svg { width: 16px; height: 16px; }

        /* ── Layout ── */
        .main { display: grid; grid-template-columns: 420px 1fr; min-height: calc(100vh - 60px); }

        /* ── Sidebar ── */
        .sidebar {
            background: var(--surface);
            border-right: 1px solid var(--border);
            overflow-y: auto; height: calc(100vh - 60px);
            position: sticky; top: 60px;
        }

        .section { padding: 16px 20px; border-bottom: 1px solid var(--border); }
        .section-head {
            display: flex; justify-content: space-between; align-items: center;
            cursor: pointer; user-select: none;
        }
        .section-head h3 {
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: .1em; color: var(--text-2);
        }
        .section-head .arrow { color: var(--text-3); font-size: 12px; transition: transform .2s; }
        .section-body { margin-top: 12px; }
        .section.collapsed .section-body { display: none; }
        .section.collapsed .arrow { transform: rotate(-90deg); }

        .field { margin-bottom: 10px; }
        .field label { display: block; font-size: 11px; font-weight: 700; color: var(--text-2); margin-bottom: 4px; text-transform: uppercase; letter-spacing: .05em; }
        .field input, .field textarea, .field select {
            width: 100%; border: 1px solid var(--border); border-radius: 10px;
            padding: 9px 12px; font-size: 13px; background: var(--surface-2);
            color: var(--text); outline: none; transition: border-color .15s;
        }
        .field input:focus, .field textarea:focus, .field select:focus { border-color: var(--primary); background: #fff; }
        .field textarea { min-height: 60px; resize: vertical; }

        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
        .row-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 6px; }

        /* Theme/Layout chips */
        .chip-group { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip {
            padding: 7px 14px; border-radius: 8px; font-size: 11px; font-weight: 700;
            border: 1.5px solid var(--border); background: var(--surface-2);
            color: var(--text-2); cursor: pointer; transition: all .15s;
        }
        .chip:hover { border-color: var(--primary); color: var(--primary-dark); }
        .chip.active { background: linear-gradient(135deg, #fff8e1, #fef3c7); border-color: var(--primary); color: var(--primary-dark); box-shadow: 0 0 0 3px rgba(212,160,23,.15); }

        /* Color swatch chips */
        .swatch { width: 28px; height: 28px; border-radius: 8px; padding: 0; border: 2px solid var(--border); }
        .swatch.active { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(212,160,23,.25); }

        /* Upload areas */
        .upload-zone {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; border: 1.5px dashed var(--border); border-radius: 10px;
            background: var(--surface-2); cursor: pointer; transition: border-color .15s;
        }
        .upload-zone:hover { border-color: var(--primary); }
        .upload-zone .icon { font-size: 20px; color: var(--text-3); }
        .upload-zone .text { font-size: 12px; color: var(--text-2); font-weight: 600; }
        .upload-zone .clear { margin-left: auto; font-size: 11px; color: #ef4444; font-weight: 700; cursor: pointer; }
        .upload-zone .clear:hover { text-decoration: underline; }

        /* Rate trend */
        .trend { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; font-weight: 700; border-radius: 6px; padding: 2px 8px; }
        .trend.up { color: #16a34a; background: #dcfce7; }
        .trend.down { color: #dc2626; background: #fee2e2; }
        .trend.flat { color: #6b7280; background: #f3f4f6; }

        /* ── Preview Stage ── */
        .stage {
            display: flex; justify-content: center; align-items: flex-start;
            padding: 24px; overflow: auto;
            background: repeating-conic-gradient(var(--surface-3) 0% 25%, var(--surface-2) 0% 50%) 50% / 20px 20px;
        }

        .story-shell {
            width: 1080px; height: 1920px;
            transform-origin: top center;
            flex-shrink: 0;
        }

        .story {
            position: relative; width: 1080px; height: 1920px;
            overflow: hidden; border-radius: 54px;
            background: linear-gradient(160deg, #150d04, #36210c 38%, #0c0e17);
            box-shadow: 0 0 0 6px rgba(212,160,23,.6), 0 30px 80px rgba(0,0,0,.3);
        }

        .story-bg-image { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 1; opacity: 0; transition: opacity .3s; }
        .story-bg-image.visible { opacity: 1; }

        .story-overlay {
            position: absolute; inset: 0; z-index: 2;
            background: linear-gradient(180deg, rgba(7,11,20,.15), rgba(7,11,20,.65)),
                        var(--story-gradient, linear-gradient(140deg, rgba(44,21,5,.96), rgba(12,14,23,.94)));
            transition: opacity .3s;
        }
        .story-overlay.has-bg { opacity: .55; }

        .story-logo {
            position: absolute; top: 50px; left: 50%; transform: translateX(-50%);
            width: 220px; height: 220px;
            object-fit: contain; z-index: 10; opacity: 0;
            border-radius: 28px; background: rgba(255,255,255,.15); padding: 16px;
        }
        .story-logo.visible { opacity: 1; }

        .story-product {
            position: absolute; left: 50%; top: 320px; transform: translateX(-50%);
            width: 680px; height: 540px; object-fit: contain; z-index: 3;
            opacity: 0; filter: drop-shadow(0 20px 45px rgba(0,0,0,.5));
        }
        .story-product.visible { opacity: .95; }

        .story-content {
            position: relative; z-index: 4; height: 100%;
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 68px 64px 72px; color: #f8eed2; text-align: center;
        }

        .special-text {
            min-height: 72px; margin-top: 72px; margin-bottom: 8px;
            color: #ffe39b; font-size: 62px; font-family: "Great Vibes", cursive;
            text-shadow: 0 10px 30px rgba(0,0,0,.3); opacity: 0;
        }
        .special-text.visible { opacity: 1; }

        .story-title {
            color: #fff6d8; font-size: 92px; line-height: .96;
            letter-spacing: .02em; text-transform: uppercase;
            text-shadow: 0 8px 32px rgba(246,210,120,.3);
            font-family: "Playfair Display", serif; font-weight: 800;
        }

        /* Rate area — Card layout */
        .rate-area { margin-top: auto; display: flex; flex-direction: column; align-items: center; gap: 28px; }

        .rate-card {
            width: 100%; max-width: 820px; border-radius: 30px;
            border: 2.5px solid rgba(240,207,118,.8);
            background: rgba(8,12,21,.5); backdrop-filter: blur(16px);
            padding: 34px 38px; box-shadow: 0 16px 48px rgba(0,0,0,.25);
        }
        .rate-card-head { display: flex; justify-content: space-between; align-items: baseline; font-size: 28px; letter-spacing: .16em; color: #c7ba92; text-transform: uppercase; }
        .rate-card-main { margin-top: 12px; display: flex; justify-content: space-between; align-items: end; }
        .rate-card-val { font-size: 96px; line-height: .88; font-weight: 800; color: #f5d57b; }
        .rate-card-meta { text-align: right; font-size: 26px; color: #fff1c7; }
        .rate-card-trend { display: inline-block; margin-top: 6px; font-size: 24px; padding: 4px 14px; border-radius: 14px; }
        .rate-card-trend.up { color: #4ade80; background: rgba(74,222,128,.12); }
        .rate-card-trend.down { color: #f87171; background: rgba(248,113,113,.12); }

        /* Rate area — Circle layout */
        .rate-grid { width: 100%; max-width: 900px; display: grid; gap: 18px; }
        .rate-grid.cols-3 { grid-template-columns: repeat(3, 1fr); }
        .rate-grid.cols-4 { grid-template-columns: repeat(4, 1fr); }
        .rate-pill {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            border-radius: 999px; border: 3.5px solid rgba(245,210,116,.82);
            background: radial-gradient(circle at top, rgba(84,49,12,.86), rgba(11,13,20,.94));
            box-shadow: 0 16px 44px rgba(0,0,0,.3); padding: 28px; min-height: 240px;
        }
        .rate-pill .k { font-size: 40px; letter-spacing: .16em; text-transform: uppercase; color: #c7ba92; }
        .rate-pill .v { margin-top: 10px; font-size: 62px; line-height: .92; font-weight: 800; color: #fff4d2; }
        .rate-pill .m { margin-top: 8px; font-size: 22px; color: #f0d998; }

        /* Rate area — Minimal layout */
        .rate-minimal { width: 100%; max-width: 860px; }
        .rate-minimal-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 28px 0; border-bottom: 1.5px solid rgba(255,255,255,.1);
        }
        .rate-minimal-row:last-child { border-bottom: none; }
        .rate-minimal-label { font-size: 34px; color: #c7ba92; text-transform: uppercase; letter-spacing: .12em; }
        .rate-minimal-val { font-size: 72px; font-weight: 800; color: #f5d57b; }

        /* Footer */
        .story-footer {
            display: grid; gap: 8px; padding: 24px 30px; border-radius: 26px;
            background: rgba(7,11,20,.45); backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,.07);
        }
        .shop-name { font-size: 50px; font-weight: 800; color: #fff7dc; }
        .shop-address { font-size: 25px; line-height: 1.4; color: #ead7a0; white-space: pre-line; }
        .shop-meta { display: flex; justify-content: center; gap: 16px; flex-wrap: wrap; font-size: 26px; color: #fff3cc; }


        /* ── Toast ── */
        .toast {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
            padding: 12px 24px; border-radius: 12px; font-size: 13px; font-weight: 700;
            color: #fff; background: #1a1a2e; box-shadow: 0 8px 30px rgba(0,0,0,.2);
            z-index: 200; opacity: 0; transition: opacity .3s; pointer-events: none;
        }
        .toast.show { opacity: 1; }

        /* ── Responsive ── */
        @media (max-width: 1400px) {
            .main { grid-template-columns: 380px 1fr; }
        }
        @media (max-width: 1100px) {
            .main { grid-template-columns: 1fr; }
            .sidebar { position: static; height: auto; max-height: none; }
        }
    </style>
</head>
<body>
@php $storyJson = json_encode($storyData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); @endphp

<!-- Top Bar -->
<div class="topbar">
    <div class="topbar-title">Gold Rate <span>Story Maker</span></div>
    <div class="topbar-actions">
        <button class="btn btn-outline" id="btnRefresh">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/></svg>
            Refresh
        </button>
        <button class="btn btn-gold" id="btnDownload">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Download PNG
        </button>
        <button class="btn btn-outline" id="btnCopy">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
            Copy
        </button>
    </div>
</div>

<div class="main">
    <!-- Sidebar Controls -->
    <div class="sidebar">
        <!-- Shop Details -->
        <div class="section" id="secShop">
            <div class="section-head" onclick="toggleSection('secShop')">
                <h3>Shop Details</h3><span class="arrow">&#9660;</span>
            </div>
            <div class="section-body">
                <div class="field"><label>Shop Name</label><input id="shopName" type="text"></div>
                <div class="field"><label>Address</label><textarea id="shopAddress"></textarea></div>
                <div class="field"><label>Phone</label><input id="shopPhone" type="text"></div>
            </div>
        </div>

        <!-- Rates -->
        <div class="section" id="secRates">
            <div class="section-head" onclick="toggleSection('secRates')">
                <h3>Rates</h3><span class="arrow">&#9660;</span>
            </div>
            <div class="section-body">
                <div class="row-2">
                    <div class="field">
                        <label>22K Gold / gm</label>
                        <input id="rate22" type="number" step="0.01">
                        <span class="trend" id="trend22"></span>
                    </div>
                    <div class="field">
                        <label>18K Gold / gm</label>
                        <input id="rate18" type="number" step="0.01">
                    </div>
                </div>
                <div class="row-2">
                    <div class="field">
                        <label>Silver / gm</label>
                        <input id="rateSilver" type="number" step="0.01">
                        <span class="trend" id="trendSilver"></span>
                    </div>
                    <div class="field">
                        <label>Platinum / gm</label>
                        <input id="ratePlatinum" type="number" step="0.01">
                    </div>
                </div>
                <div class="row-2">
                    <div class="field"><label>Date</label><input id="storyDate" type="date"></div>
                    <div class="field"><label>Special Day / Offer</label><input id="specialDay" type="text" placeholder="Akshaya Tritiya"></div>
                </div>
                <div class="field"><label>Headline Title</label><input id="headlineTitle" type="text" value="Today's Gold Rate"></div>
            </div>
        </div>

        <!-- Images -->
        <div class="section" id="secImages">
            <div class="section-head" onclick="toggleSection('secImages')">
                <h3>Images</h3><span class="arrow">&#9660;</span>
            </div>
            <div class="section-body">
                <label class="upload-zone" for="logoUpload">
                    <span class="icon">&#128444;</span>
                    <span class="text" id="logoLabel">Upload Logo</span>
                    <span class="clear" id="clearLogo" style="display:none" onclick="event.preventDefault();clearImage('logo')">Clear</span>
                </label>
                <input id="logoUpload" type="file" accept="image/*" hidden>

                <label class="upload-zone" for="bgUpload" style="margin-top:8px">
                    <span class="icon">&#127748;</span>
                    <span class="text" id="bgLabel">Upload Background</span>
                    <span class="clear" id="clearBg" style="display:none" onclick="event.preventDefault();clearImage('bg')">Clear</span>
                </label>
                <input id="bgUpload" type="file" accept="image/*" hidden>

                <label class="upload-zone" for="productUpload" style="margin-top:8px">
                    <span class="icon">&#128142;</span>
                    <span class="text" id="productLabel">Upload Product / Jewellery</span>
                    <span class="clear" id="clearProduct" style="display:none" onclick="event.preventDefault();clearImage('product')">Clear</span>
                </label>
                <input id="productUpload" type="file" accept="image/*" hidden>
            </div>
        </div>

        <!-- Design -->
        <div class="section" id="secDesign">
            <div class="section-head" onclick="toggleSection('secDesign')">
                <h3>Design</h3><span class="arrow">&#9660;</span>
            </div>
            <div class="section-body">
                <div class="field">
                    <label>Color Theme</label>
                    <div class="chip-group" id="themeChips">
                        <button class="swatch active" data-theme="classic-gold" style="background:linear-gradient(135deg,#59310c,#d4a017)" title="Classic Gold"></button>
                        <button class="swatch" data-theme="royal-blue" style="background:linear-gradient(135deg,#0d2452,#1e40af)" title="Royal Blue"></button>
                        <button class="swatch" data-theme="ruby-red" style="background:linear-gradient(135deg,#5c0e15,#b91c1c)" title="Ruby Red"></button>
                        <button class="swatch" data-theme="emerald" style="background:linear-gradient(135deg,#0c493b,#059669)" title="Emerald"></button>
                        <button class="swatch" data-theme="wedding" style="background:linear-gradient(135deg,#744512,#d97706)" title="Wedding"></button>
                        <button class="swatch" data-theme="midnight" style="background:linear-gradient(135deg,#101422,#2f1f4c)" title="Midnight"></button>
                        <button class="swatch" data-theme="rose-gold" style="background:linear-gradient(135deg,#8b5e4b,#e8a87c)" title="Rose Gold"></button>
                        <button class="swatch" data-theme="black-gold" style="background:linear-gradient(135deg,#0a0a0a,#333)" title="Black Gold"></button>
                    </div>
                </div>
                <div class="field">
                    <label>Layout</label>
                    <div class="chip-group" id="layoutChips">
                        <button class="chip active" data-layout="card">Card</button>
                        <button class="chip" data-layout="circle">Circle</button>
                        <button class="chip" data-layout="minimal">Minimal</button>
                    </div>
                </div>
                <div class="row-2">
                    <div class="field">
                        <label>Headline Font</label>
                        <select id="fontStyle">
                            <option value="playfair">Playfair Display</option>
                            <option value="cinzel">Cinzel</option>
                            <option value="script">Great Vibes</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Show Rates</label>
                        <select id="rateDisplay">
                            <option value="3">22K + 18K + Silver</option>
                            <option value="4">All 4 rates</option>
                            <option value="2">22K + 18K only</option>
                            <option value="1">22K only</option>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label>Show 8g Price</label>
                    <select id="show8g">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Stage -->
    <div class="stage" id="stageArea">
        <div class="story-shell" id="storyShell">
            <div class="story" id="storyCanvas">
                <img id="storyBgImage" class="story-bg-image" alt="">
                <div class="story-overlay" id="storyOverlay"></div>
                <img id="storyLogo" class="story-logo" alt="">
                <img id="storyProduct" class="story-product" alt="">

                <div class="story-content">
                    <div>
                        <div class="special-text" id="specialPreview"></div>
                        <h2 class="story-title" id="titlePreview">Today's Gold Rate</h2>
                        <div class="rate-area" id="rateArea"></div>
                    </div>

                    <div class="story-footer">
                        <p class="shop-name" id="shopNamePreview"></p>
                        <div class="shop-address" id="shopAddressPreview"></div>
                        <div class="shop-meta">
                            <span id="datePreview"></span>
                            <span id="phonePreview"></span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const initialData = {!! $storyJson !!};
const el = id => document.getElementById(id);

const state = {
    theme: 'classic-gold',
    layout: 'card',
    data: { ...initialData },
};

const themes = {
    'classic-gold':  'linear-gradient(145deg, rgba(59,31,7,.96), rgba(8,10,16,.94))',
    'royal-blue':    'linear-gradient(145deg, rgba(13,36,82,.96), rgba(11,14,25,.95))',
    'ruby-red':      'linear-gradient(145deg, rgba(92,14,21,.96), rgba(28,10,16,.95))',
    'emerald':       'linear-gradient(145deg, rgba(12,73,59,.96), rgba(9,16,18,.95))',
    'wedding':       'linear-gradient(145deg, rgba(116,69,18,.92), rgba(70,22,40,.92))',
    'midnight':      'linear-gradient(145deg, rgba(16,20,34,.96), rgba(47,31,76,.9))',
    'rose-gold':     'linear-gradient(145deg, rgba(139,94,75,.96), rgba(55,20,15,.94))',
    'black-gold':    'linear-gradient(145deg, rgba(10,10,10,.98), rgba(40,30,10,.96))',
};

const currency = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2, minimumFractionDigits: 2 });

function toast(msg) {
    const t = el('toast'); t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}

function formatDate(v) {
    const d = v ? new Date(v + 'T00:00:00') : new Date();
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' });
}

function toggleSection(id) {
    document.getElementById(id).classList.toggle('collapsed');
}

/* ── Scale preview to fit stage ── */
function scalePreview() {
    const stage = el('stageArea');
    const shell = el('storyShell');
    const sw = stage.clientWidth - 48;
    const sh = stage.clientHeight - 48;
    const scale = Math.min(sw / 1080, sh / 1920, 0.5);
    shell.style.transform = `scale(${scale})`;
    shell.style.transformOrigin = 'top center';
    shell.style.marginBottom = (-(1920 * (1 - scale))) + 'px';
}

/* ── Trend indicators ── */
function showTrends() {
    const r22 = Number(el('rate22').value) || 0;
    const rS = Number(el('rateSilver').value) || 0;
    const p22 = state.data.prev_22k || 0;
    const pS = state.data.prev_silver || 0;
    setTrend('trend22', r22, p22);
    setTrend('trendSilver', rS, pS);
}

function setTrend(id, curr, prev) {
    const t = el(id);
    if (!prev || prev === curr) { t.className = 'trend flat'; t.textContent = '- no change'; return; }
    const diff = curr - prev;
    const pct = ((diff / prev) * 100).toFixed(1);
    if (diff > 0) { t.className = 'trend up'; t.innerHTML = '&#9650; +' + pct + '%'; }
    else { t.className = 'trend down'; t.innerHTML = '&#9660; ' + pct + '%'; }
}

/* ── Sync inputs from data ── */
function syncInputs() {
    el('shopName').value = state.data.shop_name || '';
    el('shopAddress').value = state.data.shop_address || '';
    el('shopPhone').value = state.data.shop_phone || '';
    el('rate22').value = state.data.rate_22k || 0;
    el('rate18').value = state.data.rate_18k || 0;
    el('rateSilver').value = state.data.rate_silver || 0;
    el('ratePlatinum').value = state.data.rate_platinum || 0;
    el('storyDate').value = state.data.today || '';
    showTrends();
}

/* ── Render rates ── */
function renderRates() {
    const r22 = Number(el('rate22').value) || 0;
    const r18 = Number(el('rate18').value) || 0;
    const rSi = Number(el('rateSilver').value) || 0;
    const rPl = Number(el('ratePlatinum').value) || 0;
    const show = el('rateDisplay').value;
    const show8g = el('show8g').value === '1';
    const area = el('rateArea');

    const p22 = state.data.prev_22k || 0;
    const diff22 = r22 - p22;
    const trendHtml = p22 && diff22 !== 0
        ? `<div class="rate-card-trend ${diff22 > 0 ? 'up' : 'down'}">${diff22 > 0 ? '&#9650;' : '&#9660;'} ${currency.format(Math.abs(diff22))}</div>`
        : '';

    if (state.layout === 'minimal') {
        let rows = `<div class="rate-minimal-row"><span class="rate-minimal-label">22K Gold</span><span class="rate-minimal-val">${currency.format(r22)}</span></div>`;
        if (show >= 2) rows += `<div class="rate-minimal-row"><span class="rate-minimal-label">18K Gold</span><span class="rate-minimal-val">${currency.format(r18)}</span></div>`;
        if (show >= 3) rows += `<div class="rate-minimal-row"><span class="rate-minimal-label">Silver</span><span class="rate-minimal-val">${currency.format(rSi)}</span></div>`;
        if (show >= 4) rows += `<div class="rate-minimal-row"><span class="rate-minimal-label">Platinum</span><span class="rate-minimal-val">${currency.format(rPl)}</span></div>`;
        area.innerHTML = `<div class="rate-minimal">${rows}</div>`;
        return;
    }

    if (state.layout === 'circle') {
        const cols = Math.min(parseInt(show), 4);
        let pills = `<div class="rate-pill"><div class="k">22K</div><div class="v">${currency.format(r22)}</div>${show8g ? `<div class="m">8g ${currency.format(r22*8)}</div>` : ''}</div>`;
        if (show >= 2) pills += `<div class="rate-pill"><div class="k">18K</div><div class="v">${currency.format(r18)}</div>${show8g ? `<div class="m">8g ${currency.format(r18*8)}</div>` : ''}</div>`;
        if (show >= 3) pills += `<div class="rate-pill"><div class="k">Silver</div><div class="v">${currency.format(rSi)}</div><div class="m">per gram</div></div>`;
        if (show >= 4) pills += `<div class="rate-pill"><div class="k">Plat.</div><div class="v">${currency.format(rPl)}</div><div class="m">per gram</div></div>`;
        area.innerHTML = `<div class="rate-grid cols-${cols}">${pills}</div>`;
        return;
    }

    // Card layout
    let html = `<div class="rate-card">
        <div class="rate-card-head"><span>22K Gold</span><span>Per Gram</span></div>
        <div class="rate-card-main">
            <div class="rate-card-val">${currency.format(r22)}</div>
            <div class="rate-card-meta">${show8g ? '8g ' + currency.format(r22*8) : ''}<br>${trendHtml}</div>
        </div>
    </div>`;

    if (show >= 2) {
        html += `<div class="rate-card" style="max-width:760px">
            <div class="rate-card-head"><span>18K Gold</span>${show >= 3 ? '<span>Silver</span>' : ''}</div>
            <div class="rate-card-main">
                <div class="rate-card-val" style="font-size:72px">${currency.format(r18)}</div>
                ${show >= 3 ? `<div class="rate-card-meta">${currency.format(rSi)} / gm</div>` : ''}
            </div>
        </div>`;
    }

    if (show >= 4 && rPl > 0) {
        html += `<div class="rate-card" style="max-width:680px">
            <div class="rate-card-head"><span>Platinum</span><span>Per Gram</span></div>
            <div class="rate-card-main"><div class="rate-card-val" style="font-size:68px">${currency.format(rPl)}</div></div>
        </div>`;
    }

    area.innerHTML = html;
}

/* ── Render preview ── */
function renderPreview() {
    const special = el('specialDay').value.trim();
    const title = el('titlePreview');

    el('storyOverlay').style.setProperty('--story-gradient', themes[state.theme]);
    el('specialPreview').textContent = special;
    el('specialPreview').className = 'special-text' + (special ? ' visible' : '');

    title.textContent = el('headlineTitle').value.trim() || "Today's Gold Rate";
    title.style.fontFamily = '"Playfair Display", serif';
    title.style.fontSize = '92px';
    title.style.textTransform = 'uppercase';

    const font = el('fontStyle').value;
    if (font === 'cinzel') { title.style.fontFamily = '"Cinzel", serif'; }
    else if (font === 'script') { title.style.fontFamily = '"Great Vibes", cursive'; title.style.fontSize = '112px'; title.style.textTransform = 'none'; }

    el('shopNamePreview').textContent = el('shopName').value.trim() || 'Gold Rate Update';
    el('shopAddressPreview').textContent = el('shopAddress').value.trim();
    el('phonePreview').textContent = el('shopPhone').value.trim();
    el('datePreview').textContent = formatDate(el('storyDate').value);
    renderRates();
    showTrends();
}

/* ── Apply system data ── */
function applySystemData(payload) {
    state.data = { ...state.data, ...payload };
    syncInputs();
    if (payload.shop_logo_url) {
        el('storyLogo').src = payload.shop_logo_url;
        el('storyLogo').classList.add('visible');
    }
    renderPreview();
}

/* ── Refresh from system ── */
async function refreshFromSystem() {
    try {
        const resp = await fetch('{{ url("/api/gold-rate-story/data") }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await resp.json();
        if (!json.ok) { toast(json.message || 'Unable to load data'); return; }
        applySystemData(json.data || {});
        toast('Rates refreshed from system');
    } catch (e) { toast('Network error'); }
}

/* ── Image uploads ── */
function bindUpload(inputId, targetId, labelId, clearId) {
    el(inputId).addEventListener('change', e => {
        const file = e.target.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = () => {
            el(targetId).src = reader.result;
            el(targetId).classList.add('visible');
            el(labelId).textContent = file.name.slice(0, 24);
            el(clearId).style.display = '';
            if (targetId === 'storyBgImage') el('storyOverlay').classList.add('has-bg');
        };
        reader.readAsDataURL(file);
    });
}
function clearImage(type) {
    const map = { logo: ['storyLogo','logoLabel','clearLogo','logoUpload'], bg: ['storyBgImage','bgLabel','clearBg','bgUpload'], product: ['storyProduct','productLabel','clearProduct','productUpload'] };
    const [img, label, clear, input] = map[type];
    el(img).src = ''; el(img).classList.remove('visible');
    el(label).textContent = type === 'logo' ? 'Upload Logo' : type === 'bg' ? 'Upload Background' : 'Upload Product / Jewellery';
    el(clear).style.display = 'none';
    el(input).value = '';
    if (type === 'bg') el('storyOverlay').classList.remove('has-bg');
}

bindUpload('logoUpload', 'storyLogo', 'logoLabel', 'clearLogo');
bindUpload('bgUpload', 'storyBgImage', 'bgLabel', 'clearBg');
bindUpload('productUpload', 'storyProduct', 'productLabel', 'clearProduct');

/* ── Download / Copy ── */
async function makeCanvas() {
    return html2canvas(el('storyCanvas'), { scale: 2, useCORS: true, backgroundColor: null });
}

async function downloadStory() {
    toast('Generating image...');
    const canvas = await makeCanvas();
    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = `gold-rate-story-${el('storyDate').value || 'today'}.png`;
    link.click();
    toast('Image downloaded!');
}

async function copyStory() {
    if (!navigator.clipboard || typeof ClipboardItem === 'undefined') { toast('Clipboard not supported in this browser'); return; }
    toast('Copying image...');
    const canvas = await makeCanvas();
    canvas.toBlob(async blob => {
        if (!blob) return;
        await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
        toast('Image copied! Paste in WhatsApp or Instagram');
    });
}

/* ── Theme chips ── */
document.querySelectorAll('#themeChips [data-theme]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#themeChips .swatch').forEach(s => s.classList.remove('active'));
        btn.classList.add('active');
        state.theme = btn.dataset.theme;
        renderPreview();
    });
});

/* ── Layout chips ── */
document.querySelectorAll('#layoutChips [data-layout]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#layoutChips .chip').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        state.layout = btn.dataset.layout;
        renderPreview();
    });
});

/* ── Live input binding ── */
['shopName','shopAddress','shopPhone','rate22','rate18','rateSilver','ratePlatinum','storyDate','specialDay','fontStyle','headlineTitle','rateDisplay','show8g']
    .forEach(id => el(id).addEventListener('input', renderPreview));
el('rateDisplay').addEventListener('change', renderPreview);
el('show8g').addEventListener('change', renderPreview);

el('btnRefresh').addEventListener('click', refreshFromSystem);
el('btnDownload').addEventListener('click', downloadStory);
el('btnCopy').addEventListener('click', copyStory);

/* ── Init ── */
applySystemData(initialData);
scalePreview();
window.addEventListener('resize', scalePreview);
</script>
</body>
</html>
