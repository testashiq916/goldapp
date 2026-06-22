<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Gallery</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        header { background: linear-gradient(135deg, #173b63, #2563eb); color: #fff; padding: 14px 20px; display: flex; align-items: center; gap: 12px; }
        header h1 { margin: 0; font-size: 20px; }
        .search-bar { padding: 14px 20px; background: #fff; border-bottom: 1px solid #e5ecf5; display: flex; gap: 8px; }
        .search-bar input { flex: 1; height: 38px; border: 1px solid #c4d1e2; border-radius: 8px; padding: 0 12px; font-size: 14px; }
        .search-bar button { height: 38px; background: #173b63; color: #fff; border: none; border-radius: 8px; padding: 0 18px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .content { padding: 16px 20px; }
        .item-section { margin-bottom: 28px; }
        .item-section h3 { font-size: 15px; color: #173b63; margin: 0 0 12px; }
        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
        .gallery-card { border-radius: 12px; overflow: hidden; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.08); transition: transform .15s, box-shadow .15s; cursor: pointer; }
        .gallery-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
        .gallery-card img { width: 100%; height: 200px; object-fit: cover; display: block; }
        .gallery-card .info { padding: 10px 12px; }
        .gallery-card .info .code { font-weight: 700; font-size: 13px; color: #173b63; }
        .gallery-card .info .bc { font-size: 11px; color: #94a3b8; }
        .empty { padding: 60px; text-align: center; color: #94a3b8; font-size: 16px; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal.open { display: flex; }
        .modal img { max-width: 90vw; max-height: 85vh; border-radius: 8px; object-fit: contain; }
        .modal-close { position: fixed; top: 16px; right: 20px; color: #fff; font-size: 32px; cursor: pointer; line-height: 1; }
        .modal-info { position: fixed; bottom: 20px; left: 0; right: 0; text-align: center; color: #fff; font-size: 14px; }
        @media (max-width: 500px) {
            .gallery-grid { grid-template-columns: repeat(2, 1fr); }
            .gallery-card img { height: 140px; }
        }
    </style>
</head>
<body>
<header>
    <h1>&#128247; Product Gallery</h1>
</header>

<div class="search-bar">
    <input type="text" id="search" placeholder="Search by item code or name&hellip;" oninput="debounceSearch()">
    <button onclick="loadGallery()">Search</button>
</div>

<div class="content" id="gallery"><div class="empty">Loading gallery&hellip;</div></div>

<!-- Lightbox -->
<div class="modal" id="modal" onclick="closeModal()">
    <span class="modal-close">&times;</span>
    <img id="modal-img" src="" alt="">
    <div class="modal-info" id="modal-info"></div>
</div>

<script>
function esc(v) { const d=document.createElement('div');d.textContent=v==null?'':v;return d.innerHTML; }

let searchTimer;
function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadGallery, 350);
}

function loadGallery() {
    const q = document.getElementById('search').value.trim();
    const el = document.getElementById('gallery');
    fetch(`/api/catalogue/gallery?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(d => {
            if (!d.ok || !d.groups || !Object.keys(d.groups).length) {
                el.innerHTML = `<div class="empty">&#128247;<br>No products found${q ? ' for "' + esc(q) + '"' : ''}</div>`;
                return;
            }
            let html = '';
            for (const [itemCode, imgs] of Object.entries(d.groups)) {
                html += `<div class="item-section">
                    <h3>${esc(itemCode)}</h3>
                    <div class="gallery-grid">
                        ${imgs.map(img => `<div class="gallery-card" onclick="openModal('${esc(img.url)}','${esc(img.original_name)}','${esc(itemCode)}')">
                            <img src="${esc(img.url)}" alt="${esc(img.original_name)}" loading="lazy">
                            <div class="info">
                                <div class="code">${esc(itemCode)}</div>
                                ${img.barcode ? `<div class="bc">Barcode: ${esc(img.barcode)}</div>` : ''}
                            </div>
                        </div>`).join('')}
                    </div>
                </div>`;
            }
            el.innerHTML = html;
        });
}

function openModal(url, name, code) {
    document.getElementById('modal-img').src = url;
    document.getElementById('modal-info').textContent = code + (name ? ' — ' + name : '');
    document.getElementById('modal').classList.add('open');
}

function closeModal() {
    document.getElementById('modal').classList.remove('open');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

loadGallery();
</script>
</body>
</html>
