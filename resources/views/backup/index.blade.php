<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Database Backup</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
:root{--hdr:#1a365d;--hdr2:#2b6cb0;--border:#d0dbe8;--surface:#fff;--text:#1e293b;--muted:#64748b;--accent:#2563eb}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 10% -10%,#ebf4ff 0%,#f0f6ff 40%,#eaf0f8 100%);color:var(--text);min-height:100vh;font-size:13px}
.window{margin:10px;background:var(--surface);border-radius:12px;box-shadow:0 10px 30px rgba(15,52,96,.10);overflow:hidden}
.titlebar{background:linear-gradient(135deg,var(--hdr),var(--hdr2));padding:12px 16px;display:flex;align-items:center;gap:10px}
.titlebar h1{color:#fff;font-size:16px;font-weight:700}
.titlebar .today{margin-left:auto;color:rgba(255,255,255,.7);font-size:12px}
.content{padding:16px}

/* Cards */
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-bottom:16px}
.card{border:1px solid var(--border);border-radius:10px;padding:16px;background:#fff}
.card h2{font-size:14px;color:var(--hdr);margin-bottom:10px;display:flex;align-items:center;gap:8px}
.card h2 i{font-size:16px}

/* DB Info */
.db-info{display:flex;gap:20px;flex-wrap:wrap;font-size:12px;margin-bottom:12px;padding:10px;background:#f0f6ff;border-radius:8px}
.db-info .f{display:flex;gap:4px}.db-info .lbl{font-weight:700;color:#3b5d86}.db-info .val{color:var(--hdr)}

/* Buttons */
.btn{height:34px;border-radius:7px;border:none;padding:0 18px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-primary{background:linear-gradient(135deg,var(--hdr),var(--hdr2));color:#fff}
.btn-primary:hover{opacity:.9}
.btn-green{background:#e6f8ec;color:#1b5b31;border:1px solid #2a7a42}
.btn-green:hover{background:#d0f0da}
.btn-red{background:#fee2e2;color:#991b1b;border:1px solid #ef4444}
.btn-red:hover{background:#fecaca}
.btn-outline{background:#fff;color:#375b84;border:1px solid #bfd0e6}
.btn-outline:hover{background:#f0f5ff}
.btn:disabled{opacity:.5;cursor:not-allowed}

/* Auto backup settings */
.auto-form{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.inline-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.auto-form label{font-size:12px;font-weight:700;color:#3b5d86;display:flex;align-items:center;gap:4px}
.auto-form input[type="time"]{height:30px;border:1px solid #bfd0e6;border-radius:6px;padding:0 8px;font-size:12px}
.auto-form input[type="text"]{height:30px;min-width:240px;border:1px solid #bfd0e6;border-radius:6px;padding:0 8px;font-size:12px}
.auto-form input[type="time"]:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.auto-form input[type="text"]:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.switch{position:relative;width:44px;height:24px;display:inline-block}
.switch input{opacity:0;width:0;height:0}
.slider{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:12px;transition:.3s}
.slider::before{content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
.switch input:checked+.slider{background:#16a34a}
.switch input:checked+.slider::before{transform:translateX(20px)}

/* Progress */
.progress-bar{height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden;margin-top:8px;display:none}
.progress-bar .fill{height:100%;background:linear-gradient(90deg,var(--hdr),var(--hdr2));width:0%;transition:width .3s;border-radius:3px}
.status-msg{font-size:12px;margin-top:8px;padding:8px 12px;border-radius:6px;display:none}
.status-msg.success{display:block;background:#d1fae5;color:#065f46;border:1px solid #10b981}
.status-msg.error{display:block;background:#fee2e2;color:#991b1b;border:1px solid #ef4444}
.status-msg.info{display:block;background:#dbeafe;color:#1e40af;border:1px solid #3b82f6}

/* Backup list */
.backup-list{border:1px solid var(--border);border-radius:8px;overflow:hidden}
.backup-list table{width:100%;border-collapse:collapse;font-size:12px}
.backup-list th{background:#f0f6ff;padding:8px 10px;text-align:left;font-weight:700;color:#3b5d86;font-size:11px}
.backup-list td{padding:6px 10px;border-top:1px solid #e8eef6}
.backup-list tr:hover td{background:#f8faff}
.backup-list .num{text-align:right}
.backup-list .actions{display:flex;gap:4px}
.backup-list .btn-sm{height:26px;padding:0 10px;font-size:11px;border-radius:5px}
.empty-msg{padding:30px;text-align:center;color:var(--muted)}

@media print{.titlebar,.auto-form,.btn{display:none}body{background:#fff}}
</style>
@include('partials.print-layout-head')
</head>
<body>
<div class="window">
    <div class="titlebar">
        <h1>Database Backup</h1>
        <span class="today">{{ date('d/m/Y H:i') }}</span>
    </div>

    <div class="content">
        {{-- DB Info --}}
        <div class="db-info">
            <div class="f"><span class="lbl">Database:</span><span class="val">{{ $dbName }}</span></div>
            <div class="f"><span class="lbl">Secondary DB:</span><span class="val">{{ $secondaryDbName !== '' ? $secondaryDbName : 'Not set' }}</span></div>
            <div class="f"><span class="lbl">Size:</span><span class="val">{{ $dbSize }}</span></div>
            <div class="f"><span class="lbl">Backup Location:</span><span class="val">storage/app/backups/bk1 and bk2</span></div>
            <div class="f"><span class="lbl">Local Disk Path:</span><span class="val">{{ $localDiskPath !== '' ? $localDiskPath : 'Not set' }}</span></div>
            <div class="f"><span class="lbl">Backups:</span><span class="val">{{ count($backups) }}</span></div>
        </div>

        <div class="cards">
            {{-- Manual Backup Card --}}
            <div class="card">
                <h2>Manual Backup</h2>
                <p style="font-size:12px;color:var(--muted);margin-bottom:12px">
                    Create ZIP backups for the primary database, the configured secondary database, and a project snapshot.
                </p>
                <button class="btn btn-primary" id="btnBackup" onclick="runBackup()">
                    Run Backup Now
                </button>
                <div class="progress-bar" id="progressBar"><div class="fill" id="progressFill"></div></div>
                <div class="status-msg" id="statusMsg"></div>
            </div>

            {{-- Local Disk Backup Card --}}
            <div class="card">
                <h2>Local Disk Backup</h2>
                <p style="font-size:12px;color:var(--muted);margin-bottom:12px">
                    Create database backups for the selected company and copy the generated ZIP files to a parent folder on this computer or a mapped drive. Project backup is not included here. If a secondary database exists, the app will place files inside <strong>bk1</strong> and <strong>bk2</strong> automatically.
                </p>
                <div class="auto-form">
                    <label>
                        Parent Folder:
                        <input type="text" id="localDiskPath" value="{{ $localDiskPath }}" placeholder="D:\Backups\GoldApp">
                    </label>
                    <div class="inline-actions">
                        <button class="btn btn-outline" id="btnBrowseLocalDiskPath" onclick="browseLocalDiskPath()">Browse</button>
                        <button class="btn btn-green" id="btnSaveLocalDiskPath" onclick="saveLocalDiskPath()">Set Default Path</button>
                        <button class="btn btn-primary" id="btnLocalDiskBackup" onclick="runLocalDiskBackup()">Local Disk Backup</button>
                    </div>
                </div>
                <div style="margin-top:8px;font-size:12px;color:#64748b">Example: <strong>D:\Backups\GoldApp</strong>. Do not select <strong>bk1</strong> or <strong>bk2</strong> directly.</div>
                <div style="margin-top:4px;font-size:12px;color:#375b84">
                    Selected backup target:
                    <strong>{{ $dbName !== '' ? $dbName : 'Primary DB not set' }}</strong> to <strong>bk1</strong>
                    @if($secondaryDbName !== '')
                        , <strong>{{ $secondaryDbName }}</strong> to <strong>bk2</strong>
                    @endif
                </div>
                <div id="browserFolderInfo" style="margin-top:4px;font-size:12px;color:#64748b">Browser default folder: <strong>Not set</strong></div>
                <div class="status-msg" id="localDiskStatusMsg"></div>
            </div>

            {{-- Auto Backup Card --}}
            <div class="card">
                <h2>Auto Backup Settings</h2>
                <p style="font-size:12px;color:var(--muted);margin-bottom:12px">
                    Configure automatic server-time backup. Enter one or more times separated by commas. The app will write primary backups to bk1 and secondary-shop backups to bk2.
                </p>
                <div class="auto-form">
                    <label>
                        <span class="switch">
                            <input type="checkbox" id="autoEnabled" {{ $autoEnabled ? 'checked' : '' }}>
                            <span class="slider"></span>
                        </span>
                        Enable Auto Backup
                    </label>
                    <label>
                        Times:
                        <input type="text" id="autoTimes" value="{{ $autoTimes }}" placeholder="11:00, 14:30, 18:00">
                    </label>
                    <button class="btn btn-green" onclick="saveAutoSettings()">Save Settings</button>
                </div>
                <div style="margin-top:8px;font-size:12px;color:#64748b">Example: <strong>11:00, 14:30, 18:00</strong></div>
                <div class="status-msg" id="autoStatusMsg"></div>
            </div>
        </div>

        {{-- Backup Files List --}}
        <div class="card" style="grid-column:1 / -1">
            <h2>Backup Files</h2>
            @if (empty($backups))
                <div class="empty-msg">No backup files found. Click <strong>Run Backup Now</strong> to create one.</div>
            @else
                <div class="backup-list">
                <table>
                    <thead><tr><th>#</th><th>Filename</th><th>Date</th><th class="num">Size (MB)</th><th>Actions</th></tr></thead>
                    <tbody>
                        @foreach ($backups as $i => $b)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td style="font-weight:600">{{ $b['name'] }}</td>
                            <td>{{ $b['date'] }}</td>
                            <td class="num">{{ $b['size'] }}</td>
                            <td class="actions">
                                <a href="{{ url('/backup/download/' . urlencode($b['name'])) }}" class="btn btn-outline btn-sm">Download</a>
                                <button class="btn btn-red btn-sm" onclick="deleteBackup('{{ $b['name'] }}')">Delete</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
var csrf = document.querySelector('meta[name="csrf-token"]').content;
var backupFolderDbPromise = null;
var currentBackupFolderHandle = null;
var defaultBackupFolderHandle = null;

function setStatus(id, msg, type) {
    var el = document.getElementById(id);
    el.className = 'status-msg ' + type;
    el.textContent = msg;
}

function supportsBrowserFolderPicker() {
    return typeof window.showDirectoryPicker === 'function' && typeof window.indexedDB !== 'undefined';
}

function updateBrowserFolderInfo() {
    var el = document.getElementById('browserFolderInfo');
    if (!el) return;

    var handle = currentBackupFolderHandle || defaultBackupFolderHandle;
    if (!handle) {
        el.innerHTML = 'Browser default folder: <strong>Not set</strong>';
        return;
    }

    var prefix = currentBackupFolderHandle ? 'Selected browser folder' : 'Browser default folder';
    el.innerHTML = prefix + ': <strong>' + escapeHtml(handle.name || 'Selected Folder') + '</strong>';
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function setPathInputFromHandle(handle) {
    if (!handle) return;
    document.getElementById('localDiskPath').value = '[Browser Folder] ' + (handle.name || 'Selected Folder');
    updateBrowserFolderInfo();
}

function getTopLevelBuckets(downloads) {
    var buckets = {};
    for (var i = 0; i < downloads.length; i++) {
        var relative = String(downloads[i].relative_path || '');
        var bucket = relative.split('/')[0];
        if (bucket) buckets[bucket] = true;
    }
    return Object.keys(buckets);
}

function isBucketFolderName(name) {
    var normalized = String(name || '').trim().toLowerCase();
    return normalized === 'bk1' || normalized === 'bk2';
}

function openBackupFolderDb() {
    if (!backupFolderDbPromise) {
        backupFolderDbPromise = new Promise(function(resolve, reject) {
            var request = indexedDB.open('goldapp-backup-folder-db', 1);
            request.onupgradeneeded = function(event) {
                var db = event.target.result;
                if (!db.objectStoreNames.contains('settings')) {
                    db.createObjectStore('settings');
                }
            };
            request.onsuccess = function() { resolve(request.result); };
            request.onerror = function() { reject(request.error || new Error('IndexedDB open failed')); };
        });
    }

    return backupFolderDbPromise;
}

async function getStoredFolderHandle() {
    if (!supportsBrowserFolderPicker()) return null;
    var db = await openBackupFolderDb();
    return new Promise(function(resolve, reject) {
        var tx = db.transaction('settings', 'readonly');
        var store = tx.objectStore('settings');
        var request = store.get('defaultBackupFolderHandle');
        request.onsuccess = function() { resolve(request.result || null); };
        request.onerror = function() { reject(request.error || new Error('Read default folder failed')); };
    });
}

async function saveStoredFolderHandle(handle) {
    var db = await openBackupFolderDb();
    return new Promise(function(resolve, reject) {
        var tx = db.transaction('settings', 'readwrite');
        var store = tx.objectStore('settings');
        var request = store.put(handle, 'defaultBackupFolderHandle');
        request.onsuccess = function() { resolve(); };
        request.onerror = function() { reject(request.error || new Error('Save default folder failed')); };
    });
}

async function ensureFolderPermission(handle, writable) {
    if (!handle) return false;
    var options = writable ? { mode: 'readwrite' } : {};

    if (typeof handle.queryPermission === 'function') {
        var current = await handle.queryPermission(options);
        if (current === 'granted') return true;
    }

    if (typeof handle.requestPermission === 'function') {
        var requested = await handle.requestPermission(options);
        return requested === 'granted';
    }

    return true;
}

async function loadDefaultBrowserFolder() {
    if (!supportsBrowserFolderPicker()) {
        updateBrowserFolderInfo();
        return;
    }

    try {
        var handle = await getStoredFolderHandle();
        if (!handle) {
            updateBrowserFolderInfo();
            return;
        }

        defaultBackupFolderHandle = handle;
        setPathInputFromHandle(handle);
    } catch (err) {
        updateBrowserFolderInfo();
    }
}

async function writeDownloadsToFolder(handle, downloads) {
    for (var i = 0; i < downloads.length; i++) {
        var item = downloads[i];
        var response = await fetch(item.url, { headers: { 'Accept': 'application/octet-stream' } });
        if (!response.ok) {
            throw new Error('Download failed for ' + item.name + ' (' + response.status + ' ' + response.statusText + ')');
        }

        var blob = await response.blob();
        var relative = String(item.relative_path || item.name || '');
        var segments = relative.split('/').filter(function(part) { return part; });
        var dirHandle = handle;

        for (var s = 0; s < segments.length - 1; s++) {
            dirHandle = await dirHandle.getDirectoryHandle(segments[s], { create: true });
        }

        var targetName = segments.length ? segments[segments.length - 1] : item.name;
        var fileHandle = await dirHandle.getFileHandle(targetName, { create: true });
        var writable = await fileHandle.createWritable();
        await writable.write(blob);
        await writable.close();
    }
}

async function runBackup() {
    var btn = document.getElementById('btnBackup');
    var bar = document.getElementById('progressBar');
    var fill = document.getElementById('progressFill');

    btn.disabled = true;
    btn.textContent = 'Backing up...';
    bar.style.display = 'block';
    fill.style.width = '30%';
    document.getElementById('statusMsg').className = 'status-msg';

    try {
        fill.style.width = '60%';
        var res = await fetch('{{ url("/backup/run") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        var data = await res.json();
        fill.style.width = '100%';

        if (data.ok) {
            setStatus('statusMsg', data.message, data.partial ? 'info' : 'success');
            setTimeout(function() { location.reload(); }, 1500);
        } else {
            setStatus('statusMsg', data.message || 'Backup failed', 'error');
        }
    } catch (err) {
        setStatus('statusMsg', 'Error: ' + err.message, 'error');
    }

    btn.disabled = false;
    btn.textContent = 'Run Backup Now';
}

async function saveAutoSettings() {
    var enabled = document.getElementById('autoEnabled').checked;
    var times = document.getElementById('autoTimes').value;

    try {
        var res = await fetch('{{ url("/backup/auto-settings") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ enabled: enabled, times: times }),
        });
        var data = await res.json();
        if (data.ok && Array.isArray(data.times)) {
            document.getElementById('autoTimes').value = data.times.join(', ');
        }
        setStatus('autoStatusMsg', data.message, data.ok ? 'success' : 'error');
    } catch (err) {
        setStatus('autoStatusMsg', 'Error: ' + err.message, 'error');
    }
}

async function runLocalDiskBackup() {
    var btn = document.getElementById('btnLocalDiskBackup');
    var path = document.getElementById('localDiskPath').value.trim();
    var folderHandle = currentBackupFolderHandle || defaultBackupFolderHandle;

    if (!folderHandle && !path) {
        setStatus('localDiskStatusMsg', 'Please enter a local disk folder path.', 'error');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Copying...';
    document.getElementById('localDiskStatusMsg').className = 'status-msg';

    try {
        if (folderHandle && supportsBrowserFolderPicker()) {
            var granted = await ensureFolderPermission(folderHandle, true);
            if (!granted) {
                throw new Error('Folder access permission was not granted.');
            }

            var browserRes = await fetch('{{ url("/backup/local-disk/browser-copy") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({}),
            });
            var browserData = await browserRes.json();
            if (!browserData.ok) {
                setStatus('localDiskStatusMsg', browserData.message || 'Local disk backup failed', 'error');
            } else if (!Array.isArray(browserData.downloads) || browserData.downloads.length === 0) {
                setStatus('localDiskStatusMsg', 'Backup was created, but no downloadable files were returned.', 'error');
            } else {
                var buckets = getTopLevelBuckets(browserData.downloads);
                if (buckets.length > 1 && isBucketFolderName(folderHandle.name)) {
                    throw new Error('Select the parent backup folder, not ' + folderHandle.name + '. The app will create bk1 and bk2 inside the selected folder.');
                }

                await writeDownloadsToFolder(folderHandle, browserData.downloads);
                setStatus(
                    'localDiskStatusMsg',
                    'Local disk backup copied to browser folder ' + (folderHandle.name || 'Selected Folder') + ' with folders ' + buckets.join(', ') + ': '
                        + browserData.downloads.map(function(item) { return item.name; }).join(', '),
                    browserData.partial ? 'info' : 'success'
                );
                setTimeout(function() { location.reload(); }, 1500);
            }
        } else {
            var res = await fetch('{{ url("/backup/local-disk") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ path: path }),
            });
            var data = await res.json();

            if (data.ok) {
                if (data.local_disk_path) {
                    document.getElementById('localDiskPath').value = data.local_disk_path;
                }
                setStatus('localDiskStatusMsg', data.message, data.partial ? 'info' : 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                setStatus('localDiskStatusMsg', data.message || 'Local disk backup failed', 'error');
            }
        }
    } catch (err) {
        setStatus('localDiskStatusMsg', 'Error: ' + err.message, 'error');
    }

    btn.disabled = false;
    btn.textContent = 'Local Disk Backup';
}

async function browseLocalDiskPath() {
    var btn = document.getElementById('btnBrowseLocalDiskPath');

    btn.disabled = true;
    btn.textContent = 'Opening...';

    try {
        if (supportsBrowserFolderPicker()) {
            var handle = await window.showDirectoryPicker({ mode: 'readwrite' });
            currentBackupFolderHandle = handle;
            setPathInputFromHandle(handle);
            setStatus('localDiskStatusMsg', 'Browser folder selected: ' + (handle.name || 'Selected Folder'), 'success');
        } else {
            var path = document.getElementById('localDiskPath').value.trim();
            var res = await fetch('{{ url("/backup/local-disk/browse") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ path: path }),
            });
            var data = await res.json();

            if (data.ok && data.path) {
                document.getElementById('localDiskPath').value = data.path;
                setStatus('localDiskStatusMsg', data.message, 'success');
            } else {
                setStatus('localDiskStatusMsg', data.message || 'Browse folder failed', 'error');
            }
        }
    } catch (err) {
        if (err && err.name === 'AbortError') {
            setStatus('localDiskStatusMsg', 'Folder selection cancelled.', 'error');
        } else {
            setStatus('localDiskStatusMsg', 'Error: ' + err.message, 'error');
        }
    }

    btn.disabled = false;
    btn.textContent = 'Browse';
}

async function saveLocalDiskPath() {
    var btn = document.getElementById('btnSaveLocalDiskPath');
    var path = document.getElementById('localDiskPath').value.trim();

    if (!currentBackupFolderHandle && !path) {
        setStatus('localDiskStatusMsg', 'Please enter a local disk folder path.', 'error');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
        if (currentBackupFolderHandle && supportsBrowserFolderPicker()) {
            var granted = await ensureFolderPermission(currentBackupFolderHandle, true);
            if (!granted) {
                throw new Error('Folder access permission was not granted.');
            }

            await saveStoredFolderHandle(currentBackupFolderHandle);
            defaultBackupFolderHandle = currentBackupFolderHandle;
            setPathInputFromHandle(defaultBackupFolderHandle);
            setStatus('localDiskStatusMsg', 'Default browser folder saved: ' + (defaultBackupFolderHandle.name || 'Selected Folder'), 'success');
        } else {
            var res = await fetch('{{ url("/backup/local-disk/save-path") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ path: path }),
            });
            var data = await res.json();

            if (data.ok && data.path) {
                document.getElementById('localDiskPath').value = data.path;
                setStatus('localDiskStatusMsg', data.message, 'success');
            } else {
                setStatus('localDiskStatusMsg', data.message || 'Default path save failed', 'error');
            }
        }
    } catch (err) {
        setStatus('localDiskStatusMsg', 'Error: ' + err.message, 'error');
    }

    btn.disabled = false;
    btn.textContent = 'Set Default Path';
}

async function deleteBackup(file) {
    if (!confirm('Delete backup: ' + file + '?')) return;
    try {
        var res = await fetch('{{ url("/backup/delete") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ file: file }),
        });
        var data = await res.json();
        if (data.ok) location.reload();
        else alert(data.message);
    } catch (err) { alert(err.message); }
}

loadDefaultBrowserFolder();
</script>
</body>
</html>
