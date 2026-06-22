<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Product Image Catalogue</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; font-size: 14px; }
        .wrap { max-width: 1400px; margin: 14px auto; }
        .card { background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 18px; margin-bottom: 14px; }
        h1 { margin: 0 0 14px; font-size: 22px; color: #173b63; }
        form.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; background: #f0f5ff !important; border-radius: 8px; padding: 10px; }
        label { font-size: 12px; font-weight: 700; color: #1e3a5f; display: block; margin-bottom: 3px; }
        input, select { height: 36px; border: 1px solid #c4d1e2; border-radius: 6px; padding: 0 10px; font-size: 13px; box-sizing: border-box; }
        button { height: 36px; border: none; border-radius: 6px; padding: 0 16px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #173b63; color: #fff; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-ghost { background: #fff; color: #173b63; border: 1px solid #173b63; }
        .field { display: flex; flex-direction: column; gap: 3px; }
        .upload-zone { border: 2px dashed #93c5fd; border-radius: 10px; padding: 30px; text-align: center; cursor: pointer; background: #f0f9ff; transition: background 0.2s; }
        .upload-zone:hover { background: #dbeafe; }
        .upload-zone input[type=file] { display: none; }
        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; padding: 16px 0; }
        .gallery-card { border: 1px solid #d8e2ef; border-radius: 10px; overflow: hidden; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .gallery-card img { width: 100%; height: 160px; object-fit: cover; display: block; cursor: pointer; }
        .gallery-card .img-info { padding: 8px 10px; font-size: 12px; }
        .gallery-card .img-actions { display: flex; gap: 4px; padding: 0 8px 8px; }
        .gallery-card .img-actions button { height: 28px; font-size: 11px; flex: 1; padding: 0 4px; }
        .badge-primary { display: inline-block; background: #173b63; color: #fff; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .item-section { margin-bottom: 24px; }
        .item-section h3 { font-size: 15px; color: #173b63; margin: 0 0 10px; padding-bottom: 6px; border-bottom: 1px solid #e5ecf5; }
        .empty { padding: 40px; text-align: center; color: #94a3b8; }
        #upload-preview { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        #upload-preview img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #d8e2ef; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Product Image Catalogue</h1>
        <form class="toolbar" id="upload-form">
            <div class="field">
                <label>Item Code</label>
                <input type="text" id="up_item_code" placeholder="e.g. RING001">
            </div>
            <div class="field">
                <label>Barcode (optional)</label>
                <input type="text" id="up_barcode">
            </div>
            <div class="field"><label>&nbsp;</label>
                <button type="button" class="btn-primary" onclick="document.getElementById('file-input').click()">Choose Images</button>
            </div>
            <div class="field"><label>&nbsp;</label>
                <button type="button" class="btn-primary" id="btn-upload" style="display:none;" onclick="uploadImages()">Upload</button>
            </div>
            <div class="field"><label>&nbsp;</label>
                <a href="/catalogue/gallery" target="_blank"><button type="button" class="btn-ghost">Open Gallery</button></a>
            </div>
        </form>
        <input type="file" id="file-input" accept="image/*" multiple onchange="previewFiles(this)">
        <div id="upload-preview"></div>
        <div id="upload-status" style="margin-top:8px;font-size:13px;color:#15803d;"></div>
    </div>

    <div class="card">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px;">
            <input type="text" id="search-q" placeholder="Search by item code or description..." style="flex:1;max-width:360px;">
            <button class="btn-primary" onclick="loadImages()">Search</button>
            <button class="btn-ghost" onclick="document.getElementById('search-q').value='';loadImages()">All</button>
        </div>
        <div id="gallery-content"><div class="empty">Loading images&hellip;</div></div>
    </div>
</div>

<script>
let filesToUpload = [];

function previewFiles(input) {
    filesToUpload = Array.from(input.files);
    const prev = document.getElementById('upload-preview');
    prev.innerHTML = filesToUpload.map(f => {
        const url = URL.createObjectURL(f);
        return `<img src="${url}" title="${f.name}">`;
    }).join('');
    document.getElementById('btn-upload').style.display = filesToUpload.length ? '' : 'none';
}

function uploadImages() {
    const itemCode = document.getElementById('up_item_code').value.trim();
    if (!itemCode) { alert('Enter item code.'); return; }
    if (!filesToUpload.length) { alert('Select images first.'); return; }

    document.getElementById('upload-status').textContent = 'Uploading...';

    let uploaded = 0;
    filesToUpload.forEach((file, idx) => {
        const fd = new FormData();
        fd.append('item_code', itemCode);
        fd.append('barcode', document.getElementById('up_barcode').value.trim());
        fd.append('image', file);
        fetch('/api/catalogue/upload', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                uploaded++;
                if (uploaded === filesToUpload.length) {
                    document.getElementById('upload-status').textContent = `${uploaded} image(s) uploaded successfully.`;
                    filesToUpload = [];
                    document.getElementById('upload-preview').innerHTML = '';
                    document.getElementById('btn-upload').style.display = 'none';
                    document.getElementById('file-input').value = '';
                    loadImages();
                }
            });
    });
}

function esc(v) { const d=document.createElement('div');d.textContent=v==null?'':v;return d.innerHTML; }

function loadImages() {
    const q = document.getElementById('search-q').value.trim();
    const el = document.getElementById('gallery-content');
    el.innerHTML = '<div class="empty">Loading&hellip;</div>';

    fetch(`/api/catalogue/gallery?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(d => {
            if (!d.ok || !d.groups || !Object.keys(d.groups).length) {
                el.innerHTML = '<div class="empty">No images found. Upload product images above.</div>';
                return;
            }
            let html = '';
            for (const [itemCode, imgs] of Object.entries(d.groups)) {
                html += `<div class="item-section">
                    <h3>${esc(itemCode)} <span style="font-weight:400;color:#64748b;font-size:12px;">(${imgs.length} image${imgs.length>1?'s':''})</span></h3>
                    <div class="gallery-grid">
                        ${imgs.map(img => `<div class="gallery-card" id="img-${img.id}">
                            <img src="${esc(img.url)}" alt="${esc(img.original_name)}" onclick="viewImage('${esc(img.url)}')">
                            <div class="img-info">
                                <div style="font-size:11px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(img.original_name)}</div>
                                ${img.is_primary ? '<span class="badge-primary">Primary</span>' : ''}
                                ${img.barcode ? `<div style="font-size:11px;color:#64748b;">BC: ${esc(img.barcode)}</div>` : ''}
                            </div>
                            <div class="img-actions">
                                ${!img.is_primary ? `<button class="btn-ghost" onclick="setPrimary(${img.id},'${esc(itemCode)}')">Set Primary</button>` : '<button disabled style="background:#f1f5f9;border:1px solid #d8e2ef;">Primary</button>'}
                                <button class="btn-danger" onclick="deleteImage(${img.id},'${esc(itemCode)}')">Delete</button>
                            </div>
                        </div>`).join('')}
                    </div>
                </div>`;
            }
            el.innerHTML = html;
        });
}

function setPrimary(id, itemCode) {
    fetch('/api/catalogue/set-primary', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id })
    }).then(r => r.json()).then(d => {
        if (d.ok) loadImages();
        else alert('Error: ' + d.message);
    });
}

function deleteImage(id, itemCode) {
    if (!confirm('Delete this image?')) return;
    fetch('/api/catalogue/delete', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id })
    }).then(r => r.json()).then(d => {
        if (d.ok) loadImages();
        else alert('Error: ' + d.message);
    });
}

function viewImage(url) {
    const w = window.open('', '_blank', 'width=800,height=700');
    w.document.write(`<html><body style="margin:0;background:#000;display:flex;align-items:center;justify-content:center;min-height:100vh;">
        <img src="${url}" style="max-width:100%;max-height:100vh;object-fit:contain;">
    </body></html>`);
}

// Load on mount
loadImages();
</script>
</body>
</html>
