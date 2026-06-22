<?php
/**
 * Barcode Print Set — visual label editor (one page)
 * http://localhost:8081/goldapp/public/barcodeprnset.php
 *
 *   (default)   → INI editor + draggable label image + rendered TSPL
 *   ?raw=1      → dump the raw INI as text/plain
 *   ?tspl=1     → dump the rendered TSPL as text/plain (ready-to-send .prn)
 *   ?tspl=1&qty=N → rendered TSPL with PRINT N (test batch)
 *
 * NOTE: do NOT name a copy of this "prn.php" — PRN is a reserved Windows
 * device name and Apache returns 403 Forbidden for it.
 *
 * Stored as numbered TSPL lines under an INI section so line order is kept
 * (01..NN). Lines may contain [placeholders] (e.g. [Weight], [BarCode]).
 */

$INI_PATH = __DIR__ . '/../storage/app/barcode-prnset.ini';

// ─── default print set (created on first run if the file is missing) ──────────
$DEFAULT_PRNSET = [
    'BCTemplate1' => [
        '01' => 'SIZE 75.5 mm, 25 mm',
        '02' => 'GAP 3 mm, 0 mm',
        '03' => 'SPEED 2',
        '04' => 'DENSITY 6',
        '05' => 'DIRECTION 0,0',
        '06' => 'REFERENCE 0,0',
        '07' => 'SET PEEL OFF',
        '08' => 'SET CUTTER OFF',
        '09' => 'SET PARTIAL_CUTTER OFF',
        '10' => 'SET TEAR ON',
        '11' => 'CLS',
        '12' => 'TEXT 340,120,"ROMAN.TTF",180,1,7,"GW:[Weight]"',
        '13' => 'TEXT 600,120,"ROMAN.TTF",180,1,6,"[Stcode1][Weight1]"',
        '14' => 'TEXT 600,100,"ROMAN.TTF",180,1,6,"[Stcode2][Weight2]"',
        '15' => 'TEXT 600,80,"ROMAN.TTF",180,1,6,"[Stcode1]"',
        '16' => 'TEXT 600,60,"ROMAN.TTF",180,1,6,"2SP:[Stone2]"',
        '17' => 'TEXT 340,100,"ROMAN.TTF",180,1,7,"NW:[NetWgt]"',
        '18' => 'TEXT 240,70,"ROMAN.TTF",90,1,6,"SW:[StWgt]"',
        '19' => 'QRCODE 400,120,L,2,A,180,M2,S7,"[BarCode]"',
        '20' => 'TEXT 400,70,"ROMAN.TTF",180,1,7,"[ItemCode] [BarCode]"',
        '21' => 'PRINT 1',
    ],
];

// ─── sample data for [placeholder] substitution / preview ─────────────────────
$SAMPLE = [
    '[BarCode]'  => '10001', '[BarCode2]' => '10002', '[ItemCode]' => 'BA', '[ItemCode2]' => 'CA', '[ItemName]' => 'Bangle',
    '[Weight]'   => '17.960', '[GW]' => '17.960', '[GWeight]' => '17.960',
    '[NetWgt]' => '17.960', '[NW]' => '17.960',
    '[StWgt]' => '0.000', '[SW]' => '0.000', '[StoneWgt]' => '0.000', '[DmdWgt]' => '0.000',
    '[Weight1]'  => '1.200', '[Weight2]' => '0.800', '[Weight3]' => '0.500',
    '[Stone1]'   => '250.00', '[Stone2]' => '0.00', '[Stone3]' => '0.00',
    '[Stcode1]'  => 'BA', '[Stcode2]' => 'CA', '[Stcode3]' => '',
    '[Purity]'   => '92.5', '[Qty]' => '1', '[ShopName]' => 'Gold Shop', '[StPrice]' => '22',
];

// ─── INI helpers (ordered, numbered-key aware) ───────────────────────────────
function readPrnset(string $path): array
{
    if (!is_file($path)) return [];
    $out = []; $sec = '';
    foreach (preg_split('/\r\n|\r|\n/', (string)file_get_contents($path)) as $line) {
        $line = rtrim($line);
        if ($line === '' || $line[0] === ';' || $line[0] === '#') continue;
        if (preg_match('/^\[(.+)\]$/', trim($line), $m)) { $sec = $m[1]; $out[$sec] = []; continue; }
        if ($sec !== '' && str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $out[$sec][trim($k)] = $v;
        }
    }
    return $out;
}

function writePrnset(string $path, array $data): bool
{
    $buf = '';
    foreach ($data as $sec => $kvs) {
        $buf .= "[$sec]\n";
        ksort($kvs, SORT_STRING);                 // keep 01..NN order
        foreach ((array)$kvs as $k => $v) $buf .= "$k=$v\n";
        $buf .= "\n";
    }
    if (!is_dir(dirname($path))) @mkdir(dirname($path), 0775, true);
    return file_put_contents($path, $buf) !== false;
}

// ─── render a section into raw TSPL, substituting [placeholders] ──────────────
function renderTspl(array $section, array $values, ?int $qty = null): string
{
    $lines = [];
    ksort($section, SORT_STRING);
    foreach ($section as $cmd) {
        if ($qty !== null && preg_match('/^\s*PRINT\b/i', $cmd)) $cmd = 'PRINT ' . max(1, $qty);
        $lines[] = strtr($cmd, $values);
    }
    return implode("\r\n", $lines) . "\r\n";
}

// ─── parse a print set from a string (for the Print action) ──────────────────
function parsePrnsetText(string $text): array
{
    $out = []; $sec = '';
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = rtrim($line);
        if ($line === '' || $line[0] === ';' || $line[0] === '#') continue;
        if (preg_match('/^\[(.+)\]$/', trim($line), $m)) { $sec = $m[1]; $out[$sec] = []; continue; }
        if ($sec !== '' && str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $out[$sec][trim($k)] = $v;
        }
    }
    return $out;
}

// ─── printer share from software-settings.ini ────────────────────────────────
$SW_INI = __DIR__ . '/../storage/app/software-settings.ini';
function swValue(string $path, string $key, string $def = ''): string
{
    if (!is_file($path)) return $def;
    foreach (preg_split('/\r\n|\r|\n/', (string)file_get_contents($path)) as $line) {
        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*)$/', $line, $m)) return trim($m[1]);
    }
    return $def;
}
function bareShare(string $share): string
{
    $share = trim($share);
    if (str_starts_with($share, '\\\\') || str_starts_with($share, '//')) {
        $parts = preg_split('/[\\\\\/]+/', ltrim($share, '\\/'));
        return isset($parts[1]) ? trim($parts[1]) : $share;
    }
    return $share;
}

// ─── send raw TSPL to a Windows printer share (ported from bctemp.php) ────────
function sendRaw(string $share, string $tspl): array
{
    $share  = bareShare($share);
    if ($share === '') return ['ok'=>false, 'target'=>'', 'msg'=>'No printer share configured'];
    $tmp    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bcps_' . time() . '.prn';
    file_put_contents($tmp, $tspl);
    $comp   = trim((string)getenv('COMPUTERNAME'));
    $log    = [];

    $targets = [];
    if ($comp && strtolower($comp) !== 'localhost') $targets[] = "\\\\$comp\\$share";
    $targets[] = "\\\\localhost\\$share";
    $targets[] = "\\\\127.0.0.1\\$share";

    // 1) direct PHP write to UNC printer share
    foreach ($targets as $t) {
        $w = @file_put_contents($t, $tspl);
        if ($w !== false) { @unlink($tmp); return ['ok'=>true,'target'=>$t,'msg'=>"Sent $w bytes (PHP direct)",'log'=>$log]; }
        $log[] = "PHP write $t → failed";
    }
    // 2) PowerShell byte stream
    foreach ($targets as $t) {
        $tEsc = str_replace("'", "''", $t);
        $fEsc = str_replace("'", "''", $tmp);
        $ps = "powershell -NoProfile -NonInteractive -Command \"\$b=[System.IO.File]::ReadAllBytes('$fEsc');\$fs=New-Object System.IO.FileStream('$tEsc',[System.IO.FileMode]::Create,[System.IO.FileAccess]::Write,[System.IO.FileShare]::None);\$fs.Write(\$b,0,\$b.Length);\$fs.Close()\" 2>&1";
        $out = []; exec($ps, $out, $rc); $txt = implode(' ', $out);
        $log[] = "PS $t rc=$rc $txt";
        if ($rc === 0 && stripos($txt,'error')===false && stripos($txt,'exception')===false) {
            @unlink($tmp); return ['ok'=>true,'target'=>$t,'msg'=>'Sent (PowerShell stream)','log'=>$log];
        }
    }
    // 3) net use LPT1 then copy /b
    foreach ($targets as $t) {
        exec('net use LPT1: /delete /y 2>NUL');
        $map = []; exec("net use LPT1: \"$t\" /persistent:no 2>&1", $map, $mapRc);
        $log[] = "net use LPT1→$t rc=$mapRc " . implode(' ', $map);
        if ($mapRc === 0) {
            $cp = []; exec('cmd /c copy /b ' . escapeshellarg($tmp) . ' LPT1: 2>&1', $cp, $cpRc);
            exec('net use LPT1: /delete /y 2>NUL');
            if ($cpRc === 0) { @unlink($tmp); return ['ok'=>true,'target'=>$t,'msg'=>'Sent (LPT1 map)','log'=>$log]; }
        }
        exec('net use LPT1: /delete /y 2>NUL');
    }
    // 4) copy /b directly to the share
    foreach ($targets as $t) {
        $out = []; exec('cmd /c copy /b ' . escapeshellarg($tmp) . ' "' . $t . '" 2>&1', $out, $rc);
        $log[] = "copy/b $t rc=$rc " . implode(' ', $out);
        if ($rc === 0) { @unlink($tmp); return ['ok'=>true,'target'=>$t,'msg'=>implode(' ',$out) ?: 'Sent (copy /b)','log'=>$log]; }
    }
    @unlink($tmp);
    return ['ok'=>false, 'target'=>implode(', ', $targets), 'msg'=>implode(' | ', $log), 'log'=>$log];
}

$BC_SHARE = bareShare(swValue($SW_INI, 'BCPrinterShareName'));
$BC_NAME  = swValue($SW_INI, 'BCPrinterName');

// ─── handle Print action (AJAX, returns JSON) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'print') {
    header('Content-Type: application/json; charset=utf-8');
    $rawIn  = (string)($_POST['ini'] ?? '');
    $secIn  = trim($_POST['section'] ?? '');
    $qtyIn  = max(1, (int)($_POST['qty'] ?? 1));
    $shareIn= bareShare(trim($_POST['share'] ?? $BC_SHARE));
    $data   = parsePrnsetText($rawIn);
    if (!isset($data[$secIn])) { echo json_encode(['ok'=>false,'msg'=>"Section $secIn not found"]); exit; }
    $tsplOut = renderTspl($data[$secIn], $SAMPLE, $qtyIn);
    $r = sendRaw($shareIn, $tsplOut);
    echo json_encode([
        'ok'     => $r['ok'],
        'msg'    => $r['ok'] ? "Sent $qtyIn label(s) → {$r['target']}" : "Failed: {$r['msg']}",
        'target' => $r['target'] ?? '',
    ]);
    exit;
}

// ─── bootstrap: create the default print set on first run ────────────────────
if (!is_file($INI_PATH)) writePrnset($INI_PATH, $DEFAULT_PRNSET);

// ─── handle save ─────────────────────────────────────────────────────────────
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $raw = (string)($_POST['ini'] ?? '');
    if (!is_dir(dirname($INI_PATH))) @mkdir(dirname($INI_PATH), 0775, true);
    $msg = @file_put_contents($INI_PATH, str_replace("\r\n", "\n", $raw)) !== false
        ? '✅ Print set saved'
        : '❌ Could not write ' . $INI_PATH;
}

$prnset  = readPrnset($INI_PATH);
$rawIni  = is_file($INI_PATH) ? (string)file_get_contents($INI_PATH) : '';
$section = $_GET['section'] ?? (array_key_first($prnset) ?? 'BCTemplate1');

$tspl = isset($prnset[$section]) ? renderTspl($prnset[$section], $SAMPLE) : '';

// ─── plain-text outputs ──────────────────────────────────────────────────────
if (isset($_GET['raw'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo $rawIni; exit;
}
if (isset($_GET['tspl'])) {
    $qty = isset($_GET['qty']) ? (int)$_GET['qty'] : null;
    header('Content-Type: text/plain; charset=utf-8');
    echo isset($prnset[$section]) ? renderTspl($prnset[$section], $SAMPLE, $qty) : '';
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barcode Print Set — barcodeprnset.php</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#0b0d16;color:#e2e8f0;font-size:14px;min-height:100vh}
.topbar{background:#12152a;border-bottom:2px solid #7c6bff;padding:0 24px;height:52px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:100}
.topbar h1{font-size:16px;font-weight:700;color:#fff}
.topbar .chip{background:#7c6bff22;border:1px solid #7c6bff66;color:#a89bff;font-size:11px;padding:2px 10px;border-radius:20px}
.topbar .path{margin-left:auto;font-size:11px;color:#3d4468;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:420px}
.page{padding:18px;max-width:1360px;margin:0 auto}
.alert{padding:11px 16px;border-radius:8px;font-size:13px;margin-bottom:14px;background:#0d1e3b;border:1px solid #3b82f655;color:#93c5fd}
.grid{display:grid;grid-template-columns:420px 1fr;gap:18px;align-items:start}
@media(max-width:980px){.grid{grid-template-columns:1fr}}
.card{background:#13162a;border:1px solid #1f2347;border-radius:12px;overflow:hidden;margin-bottom:16px}
.card-head{background:#181c36;padding:10px 16px;font-weight:600;font-size:13px;color:#a89bff;border-bottom:1px solid #1f2347;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.card-head .right{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.card-body{padding:14px}
textarea{width:100%;min-height:360px;background:#070810;border:1px solid #1f2347;color:#86efac;border-radius:7px;padding:10px;font-family:'Courier New',monospace;font-size:12px;line-height:1.65;resize:vertical;outline:none}
textarea:focus{border-color:#7c6bff}
.pre{background:#070810;border:1px solid #1f2347;border-radius:7px;padding:10px;font-family:'Courier New',monospace;font-size:11px;color:#93c5fd;line-height:1.65;white-space:pre;overflow:auto;max-height:200px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:7px;border:none;cursor:pointer;font-size:13px;font-weight:600;text-decoration:none}
.btn-primary{background:#7c6bff;color:#fff}.btn-ghost{background:#1f2347;color:#a89bff;border:1px solid #2a2f5a}
.btn-sm{padding:5px 10px;font-size:12px}
.btn-green{background:#22c55e;color:#04210f}
.stage{position:relative;background:#0a0c18;border-radius:8px;padding:16px;overflow:auto;display:flex;justify-content:flex-start;max-height:430px}
.labelRow{display:flex;align-items:flex-start;gap:10px}
.labelStack{display:flex;flex-direction:column;align-items:center;gap:4px}
.label{position:relative;background:#fff;box-shadow:0 8px 30px #000a;outline:1px solid #7c6bff66}
.el{position:absolute;color:#000;font-family:'Times New Roman',serif;line-height:1;white-space:nowrap;cursor:move;outline:1px dashed transparent}
.el:hover{outline-color:#7c6bff88}
.el.sel{outline:1.5px solid #7c6bff;background:#7c6bff18}
.meta{font-size:11px;color:#4d5580;text-align:center;line-height:1.7;margin-top:10px}
.guidesbar{display:flex;align-items:center;gap:16px;flex-wrap:wrap;font-size:12px;color:#7d8caa;margin-bottom:10px}
.guidesbar label{display:flex;align-items:center;gap:6px;cursor:pointer}
.guidesbar input[type=checkbox]{accent-color:#7c6bff;width:15px;height:15px}
.stageInner{display:flex;flex-direction:column;align-items:center;gap:0}
.guide{position:absolute;top:0;bottom:0;width:0;border-left:1.5px dashed #e11d48;cursor:ew-resize;z-index:5}
.guide::before{content:'';position:absolute;left:-4px;top:0;bottom:0;width:7px}
.guide .glbl{position:absolute;top:-16px;left:-14px;font-size:9px;color:#e11d48;background:#fff;padding:0 2px;border-radius:2px;white-space:nowrap}
.cutline{width:100%;display:flex;align-items:center;gap:6px;color:#e11d48;font-size:10px;font-weight:700;letter-spacing:1px;padding:3px 0}
.cutline .dash{flex:1;border-top:2px dashed #e11d48}
.lbl2-tag{font-size:10px;color:#7d8caa;margin:6px 0 2px}
.sample-help{margin-bottom:10px;padding:9px 12px;border:1px solid #1f2347;border-radius:8px;background:#0d0f1e;color:#7d8caa;font-size:12px;line-height:1.6}
.sample-help b{color:#a89bff}
select,input[type=number]{background:#0d0f1e;border:1px solid #1f2347;color:#e2e8f0;padding:5px 8px;border-radius:6px;font-size:12px}
input[type=range]{accent-color:#7c6bff;width:110px}
input[type=number]{width:74px}
.zoomctl{display:flex;align-items:center;gap:7px;font-size:12px;color:#7d8caa}
.note{font-size:11px;color:#4d5580;margin-top:9px;line-height:1.7}
.selbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:12px;color:#7d8caa;background:#0d0f1e;border:1px solid #1f2347;border-radius:8px;padding:9px 12px;margin-bottom:12px}
.selbar b{color:#a89bff}
.selbar .grp{display:flex;align-items:center;gap:5px}
.qbtns{display:flex;gap:6px}
</style>
</head>
<body>

<div class="topbar">
  <h1>Barcode Print Set</h1>
  <span class="chip">barcodeprnset.php</span>
  <div class="path"><?= htmlspecialchars($INI_PATH) ?></div>
</div>

<div class="page">

<?php if ($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="grid">

  <!-- ── LEFT: editor ── -->
  <div>
    <div class="card" style="margin-bottom:0">
      <div class="card-head">
        Print Set — <?= htmlspecialchars($section) ?>
        <span class="right">
          <?php if (count($prnset) > 1): ?>
          <select onchange="location.href='?section='+encodeURIComponent(this.value)">
            <?php foreach (array_keys($prnset) as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= $s===$section?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
          </select>
          <?php endif; ?>
          <a class="btn btn-ghost btn-sm" href="?raw=1" target="_blank">⬇ .ini</a>
          <a class="btn btn-ghost btn-sm" id="prnLink" href="?tspl=1" target="_blank">⬇ .prn</a>
        </span>
      </div>
      <div class="card-body">
        <form method="POST" id="saveForm">
          <input type="hidden" name="action" value="save">
          <textarea name="ini" id="ini" spellcheck="false"><?= htmlspecialchars($rawIni) ?></textarea>
          <div style="margin-top:11px;display:flex;gap:8px">
            <button class="btn btn-primary" type="submit">💾 Save</button>
            <button class="btn btn-ghost" type="button" onclick="render()">🔄 Re-render</button>
          </div>
        </form>
        <div class="note">
          Edit text here, or <b>drag elements</b> on the label → their <b>X,Y</b> in the
          code update automatically. Use <b>[placeholders]</b> ([Weight], [NetWgt],
          [StWgt], [Stcode1], [Stone2], [BarCode], [ItemCode]…).
        </div>
      </div>
    </div>
  </div>

  <!-- ── RIGHT: visual editor ── -->
  <div>
    <div class="card">
      <div class="card-head">
        Label — drag to align
        <span class="right">
          <span class="zoomctl">Print qty
            <input type="number" id="qty" min="1" max="999" value="1" onchange="setQty()">
            <span class="qbtns">
              <button class="btn btn-green btn-sm" type="button" onclick="setQtyVal(10)">10× test</button>
              <button class="btn btn-ghost btn-sm" type="button" onclick="setQtyVal(1)">1×</button>
            </span>
          </span>
          <button class="btn btn-primary btn-sm" type="button" id="printBtn" onclick="printNow()" title="Send to <?= htmlspecialchars($BC_NAME ?: 'printer') ?>">🖨 Print<?= $BC_SHARE!==''? ' \\\\'.htmlspecialchars($BC_SHARE) : '' ?></button>
          <span class="zoomctl">Zoom
            <input type="range" id="zoom" min="0.15" max="2" step="0.05" value="0.55" oninput="applyZoom()">
            <span id="zoomVal">0.55×</span>
            <button class="btn btn-ghost btn-sm" type="button" onclick="fitWindow()">⤢ Fit</button>
          </span>
          <button class="btn btn-ghost btn-sm" type="button" onclick="downloadPng()">⬇ PNG</button>
        </span>
      </div>
      <div class="card-body">

        <div class="selbar" id="selbar">
          <span id="selName">No element selected — click one to edit its position.</span>
          <span class="grp" id="selXY" style="display:none">
            <b>X</b><input type="number" id="selX" step="1" onchange="applyXY()">
            <b>Y</b><input type="number" id="selY" step="1" onchange="applyXY()">
            <span style="color:#3d4468">dots</span>
            <button class="btn btn-ghost btn-sm" type="button" onclick="nudge(-1,0)">◀</button>
            <button class="btn btn-ghost btn-sm" type="button" onclick="nudge(1,0)">▶</button>
            <button class="btn btn-ghost btn-sm" type="button" onclick="nudge(0,-1)">▲</button>
            <button class="btn btn-ghost btn-sm" type="button" onclick="nudge(0,1)">▼</button>
            <b>Rotate</b>
            <select id="selRot" onchange="applyRot()">
              <option value="0">0°</option>
              <option value="90">90°</option>
              <option value="180">180°</option>
              <option value="270">270°</option>
            </select>
            <b id="szLbl">Size</b><input type="number" id="selSize" min="1" step="1" onchange="applySize()">
            <span class="grp" id="selWWrap"><b id="wLbl">Width</b><input type="number" id="selW" min="1" step="1" onchange="applyWidth()"></span>
          </span>
        </div>

        <div class="guidesbar">
          <label><input type="checkbox" id="gFold" onchange="render()"> Fold guides <span style="color:#3d4468">(double line)</span></label>
          <label><input type="checkbox" id="gCut" onchange="render()"> Cut line</label>
          <label><input type="checkbox" id="gTwo" onchange="render()"> Two-up (1+2)</label>
          <span id="foldInfo" style="color:#3d4468"></span>
        </div>
        <div class="sample-help">
          Sample 1: <b>[BarCode]</b>=10001, <b>[ItemCode]</b>=BA, <b>[Weight]</b>/<b>[GW]</b>=17.960, <b>[StWgt]</b>/<b>[SW]</b>=0.000.
          Sample 2: <b>[BarCode]</b>=10002, <b>[ItemCode]</b>=CA.
        </div>
        <div class="stage" id="stage">
          <div class="stageInner" id="stageInner"></div>
        </div>
        <div class="meta" id="meta"></div>
      </div>
    </div>

    <div class="card" style="margin-bottom:0">
      <div class="card-head">Rendered TSPL (sample data)</div>
      <div class="card-body"><div class="pre" id="tsplOut"></div></div>
    </div>
  </div>

</div>
</div>

<script>
const SECTION = <?= json_encode($section, JSON_UNESCAPED_SLASHES) ?>;
const SAMPLE  = <?= json_encode($SAMPLE, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const SAMPLE2 = {
  ...SAMPLE,
  '[BarCode]':'10002', '[ItemCode]':'CA', '[ItemName]':'Chain',
  '[Weight]':'22.000', '[GW]':'22.000', '[GWeight]':'22.000',
  '[NetWgt]':'22.000', '[NW]':'22.000',
  '[StWgt]':'0.000', '[SW]':'0.000', '[StoneWgt]':'0.000',
  '[Stcode1]':'CA', '[StPrice]':'22'
};
const BC_SHARE = <?= json_encode($BC_SHARE, JSON_UNESCAPED_SLASHES) ?>;
const DPMM = 8;        // dots per mm @ 203 DPI
const FONTBASE = 1.25; // screen preview only; printer TSPL remains unchanged

let model = [];        // [{name, items:[{key,val}]}]
let draw  = [];        // current drawable elements (ref into model items)
let selIdx = -1;
let Z = 0.55, wDot = 604, hDot = 200;

// ── tiny INI model (preserves order + all sections) ──────────────────────────
function parseModel(text){
  const secs = []; let cur = null;
  for(const raw of text.split(/\r?\n/)){
    const sm = raw.trim().match(/^\[(.+)\]$/);
    if(sm){ cur = {name:sm[1], items:[]}; secs.push(cur); continue; }
    const i = raw.indexOf('=');
    if(cur && i > 0) cur.items.push({key:raw.slice(0,i).trim(), val:raw.slice(i+1)});
  }
  return secs;
}
function serializeModel(secs){
  return secs.map(s => `[${s.name}]\n` + s.items.map(it => `${it.key}=${it.val}`).join('\n')).join('\n\n') + '\n';
}
function activeSection(){ return model.find(s => s.name === SECTION) || model[0]; }

// ── arg helpers ──────────────────────────────────────────────────────────────
function splitArgs(s){
  const out=[]; let cur='', inq=false;
  for(const ch of s){
    if(ch==='"'){inq=!inq; cur+=ch; continue;}
    if(ch===',' && !inq){out.push(cur.trim()); cur=''; continue;}
    cur+=ch;
  }
  out.push(cur.trim());
  return out;
}
const unq  = t => (t||'').replace(/^"+|"+$/g,'');
const fill = (t, values=SAMPLE) => (t||'').replace(/\[[^\]]+\]/g, m => (m in values ? values[m] : (m in SAMPLE ? SAMPLE[m] : m)));
function setXY(val, nx, ny){
  const m = val.match(/^(\w+)\s*(.*)$/); if(!m) return val;
  const a = splitArgs(m[2]); a[0] = nx; a[1] = ny;
  return m[1] + ' ' + a.join(',');
}
// rotation parameter index per command: TEXT→arg3, QRCODE/BARCODE→arg5
function rotIndex(cmd){ return cmd.toUpperCase()==='TEXT' ? 3 : 5; }
function setRot(val, rot){
  const m = val.match(/^(\w+)\s*(.*)$/); if(!m) return val;
  const a = splitArgs(m[2]); const idx = rotIndex(m[1]);
  if(idx < a.length) a[idx] = rot;
  return m[1] + ' ' + a.join(',');
}
// generic: set the Nth comma arg of a command line
function setArg(val, idx, nv){
  const m = val.match(/^(\w+)\s*(.*)$/); if(!m) return val;
  const a = splitArgs(m[2]); if(idx < a.length) a[idx] = nv;
  return m[1] + ' ' + a.join(',');
}
// which args are "Size" / "Width" per command:
//   TEXT    → ymul(arg5)=Size, xmul(arg4)=Width
//   QRCODE  → cell(arg3)=Cell  (no width)
//   BARCODE → height(arg3)=Height, narrow(arg6)=Narrow
function sizeSpec(d){
  if(d.type==='text')    return {sLbl:'Size',   sIdx:5, sVal:d.ymul,   hasW:true,  wLbl:'Width',  wIdx:4, wVal:d.xmul};
  if(d.type==='qr')      return {sLbl:'Cell',   sIdx:3, sVal:d.cell,   hasW:false};
  return {sLbl:'Height', sIdx:3, sVal:d.h, hasW:true, wLbl:'Narrow', wIdx:6, wVal:d.narrow};
}

// ── compute draw list + label size from a section ─────────────────────────────
function computeDraw(sec, values=SAMPLE){
  const list = []; let w = 604, h = 200, cutterOn = false;
  if(!sec) return {list, w, h, cutterOn};
  for(const it of sec.items){
    const m = (it.val||'').trim().match(/^(\w+)\s*(.*)$/); if(!m) continue;
    const cmd = m[1].toUpperCase(), a = splitArgs(m[2]);
    if(cmd === 'SIZE'){
      const mm = m[2].match(/([\d.]+)\s*mm\s*,\s*([\d.]+)\s*mm/i);
      if(mm){ w = Math.round(parseFloat(mm[1])*DPMM); h = Math.round(parseFloat(mm[2])*DPMM); }
    } else if(cmd === 'SET'){
      if(/^(PARTIAL_)?CUTTER\s+ON/i.test(m[2])) cutterOn = true;
    } else if(cmd === 'TEXT'){
      list.push({item:it, type:'text', x:+a[0], y:+a[1], rot:+a[3]||0, xmul:+a[4]||1, ymul:+a[5]||1, text:fill(unq(a.slice(6).join(',')), values)});
    } else if(cmd === 'QRCODE'){
      list.push({item:it, type:'qr', x:+a[0], y:+a[1], cell:+a[3]||4, rot:+a[5]||0, text:fill(unq(a[a.length-1]), values)});
    } else if(cmd === 'BARCODE'){
      list.push({item:it, type:'barcode', x:+a[0], y:+a[1], h:+a[3]||40, rot:+a[5]||0, narrow:+a[6]||2, text:fill(unq(a[a.length-1]), values)});
    }
  }
  return {list, w, h, cutterOn};
}

// build a label DOM node for a section; primary one is editable + indexed
let elNodes = [];
function buildLabel(sec, editable, values=SAMPLE){
  const r = computeDraw(sec, values);
  const lab = document.createElement('div');
  lab.className = 'label';
  lab.style.width  = (r.w*Z)+'px';
  lab.style.height = (r.h*Z)+'px';
  if(editable){ lab.id = 'label'; draw = r.list; wDot = r.w; hDot = r.h; elNodes = []; }

  r.list.forEach((d, i) => {
    const el = document.createElement('div');
    el.className = 'el' + (editable && i===selIdx ? ' sel' : '');
    el.style.left = (d.x*Z)+'px';
    el.style.top  = (d.y*Z)+'px';
    if(d.rot){ el.style.transformOrigin='left top'; el.style.transform='rotate('+d.rot+'deg)'; }
    if(d.type === 'text'){
      el.style.fontSize = Math.max(5, d.ymul*FONTBASE*Z)+'px';
      el.style.fontWeight = '600';
      el.textContent = d.text;
    } else if(d.type === 'qr'){
      const px = Math.max(40, d.cell*30) * Z;
      const box = document.createElement('div'); box.style.pointerEvents='none'; el.appendChild(box);
      try{ new QRCode(box, {text:d.text||' ', width:px, height:px, colorDark:'#000', colorLight:'#fff',
            correctLevel:(window.QRCode&&QRCode.CorrectLevel?QRCode.CorrectLevel.L:undefined)}); }
      catch(e){ box.textContent='[QR]'; }
    } else if(d.type === 'barcode'){
      const svg = document.createElementNS('http://www.w3.org/2000/svg','svg'); svg.style.pointerEvents='none'; el.appendChild(svg);
      try{ JsBarcode(svg, d.text||' ', {format:'CODE128', width:1.2*Z, height:Math.max(20, d.h*Z), displayValue:false, margin:0}); }
      catch(e){ el.textContent='[BC '+d.text+']'; }
    }
    if(editable){ const idx=i; el.addEventListener('mousedown', ev => startDrag(ev, idx)); elNodes[i]=el; }
    else el.style.cursor='default';
    lab.appendChild(el);
  });

  if(editable) addFoldGuides(lab, r.w, r.h);
  return {node:lab, ...r};
}

// fold/bridge double-line guides (preview only, draggable)
let foldA = null, foldB = null;
function addFoldGuides(lab, w, h){
  if(!document.getElementById('gFold').checked){ document.getElementById('foldInfo').textContent=''; return; }
  if(foldA===null){ foldA = Math.round(w*0.42); foldB = Math.round(w*0.58); }
  [['A',foldA],['B',foldB]].forEach(([id,xv]) => {
    const g = document.createElement('div');
    g.className = 'guide';
    g.style.left = (xv*Z)+'px';
    const lbl = document.createElement('div');
    lbl.className = 'glbl';
    lbl.textContent = (xv/DPMM).toFixed(1)+'mm';
    g.appendChild(lbl);
    g.addEventListener('mousedown', ev => startGuideDrag(ev, id));
    lab.appendChild(g);
  });
  document.getElementById('foldInfo').textContent =
    `fold ↔ ${(foldA/DPMM).toFixed(1)}mm / ${(foldB/DPMM).toFixed(1)}mm`;
}
let gdrag = null;
function startGuideDrag(ev, id){
  ev.preventDefault(); ev.stopPropagation();
  gdrag = {id, sx:ev.clientX, ox:(id==='A'?foldA:foldB)};
  document.addEventListener('mousemove', onGuideDrag);
  document.addEventListener('mouseup', endGuideDrag);
}
function onGuideDrag(ev){
  if(!gdrag) return;
  let nx = Math.round(gdrag.ox + (ev.clientX-gdrag.sx)/Z);
  nx = Math.max(0, Math.min(wDot, nx));
  if(gdrag.id==='A') foldA = nx; else foldB = nx;
  render();
}
function endGuideDrag(){
  gdrag = null;
  document.removeEventListener('mousemove', onGuideDrag);
  document.removeEventListener('mouseup', endGuideDrag);
}

function cutLineNode(w){
  const c = document.createElement('div');
  c.className = 'cutline';
  c.style.width = (w*Z)+'px';
  c.innerHTML = '<span>✂</span><span class="dash"></span><span>CUT</span><span class="dash"></span>';
  return c;
}

// ── render ────────────────────────────────────────────────────────────────────
function render(){
  model = parseModel(document.getElementById('ini').value);
  const inner = document.getElementById('stageInner');
  inner.innerHTML = '';

  const sec1 = activeSection();
  const twoUp = document.getElementById('gTwo').checked;
  const showCut = document.getElementById('gCut').checked;
  const main = buildLabel(sec1, true, SAMPLE);

  if(twoUp){
    const other = model.find(s => s.name !== sec1.name) || sec1;
    const second = buildLabel(other, false, SAMPLE2);
    const row = document.createElement('div');
    row.className = 'labelRow';

    const left = document.createElement('div');
    left.className = 'labelStack';
    const leftTag = document.createElement('div');
    leftTag.className = 'lbl2-tag';
    leftTag.textContent = 'Sticker 1 - ' + sec1.name;
    left.appendChild(leftTag);
    left.appendChild(main.node);

    const right = document.createElement('div');
    right.className = 'labelStack';
    const rightTag = document.createElement('div');
    rightTag.className = 'lbl2-tag';
    rightTag.textContent = 'Sticker 2 - ' + other.name;
    right.appendChild(rightTag);
    right.appendChild(second.node);

    row.appendChild(left);
    row.appendChild(right);
    inner.appendChild(row);
    if(showCut) inner.appendChild(cutLineNode(main.w + second.w));
  } else {
    inner.appendChild(main.node);
    if(showCut) inner.appendChild(cutLineNode(main.w));
  }

  refreshTspl();
  document.getElementById('meta').innerHTML =
    `Label <b>${(wDot/DPMM)}mm x ${(hDot/DPMM)}mm</b> (${wDot}x${hDot} dots @203DPI) `+
    `&nbsp;|&nbsp; ${draw.length} elements`+
    (main.cutterOn ? ' &nbsp;|&nbsp; <span style="color:#fca5a5">cutter ON</span>' : '')+
    ` &nbsp;|&nbsp; drag to move � arrows to nudge`;
  syncSelBar();
}

function refreshTspl(){
  const sec = activeSection(); if(!sec) return;
  const other = model.find(s => s.name !== sec.name) || sec;
  const twoUp = document.getElementById('gTwo')?.checked;
  const qty = parseInt(document.getElementById('qty').value)||1;
  const linesFor = (section, values) => section.items.map(it => {
    let v = it.val;
    if(/^\s*PRINT\b/i.test(v)) v = 'PRINT ' + qty;
    return v.replace(/\[[^\]]+\]/g, m => (m in values ? values[m] : (m in SAMPLE ? SAMPLE[m] : m)));
  }).join('\r\n');
  let out = linesFor(sec, SAMPLE);
  if(twoUp) out += '\r\n\r\n; Sticker 2 sample\r\n' + linesFor(other, SAMPLE2);
  document.getElementById('tsplOut').textContent = out;
  document.getElementById('prnLink').href = '?tspl=1&qty='+qty+'&section='+encodeURIComponent(SECTION);
}
// ── drag ──────────────────────────────────────────────────────────────────────
let drag = null;
function startDrag(ev, i){
  ev.preventDefault();
  selIdx = i; syncSelBar(); paintSel();
  const d = draw[i];
  drag = {i, sx:ev.clientX, sy:ev.clientY, ox:d.x, oy:d.y};
  document.addEventListener('mousemove', onDrag);
  document.addEventListener('mouseup', endDrag);
}
function onDrag(ev){
  if(!drag) return;
  const d = draw[drag.i];
  let nx = Math.round(drag.ox + (ev.clientX-drag.sx)/Z);
  let ny = Math.round(drag.oy + (ev.clientY-drag.sy)/Z);
  nx = Math.max(0, Math.min(wDot, nx));
  ny = Math.max(0, Math.min(hDot, ny));
  d.x = nx; d.y = ny;
  d.item.val = setXY(d.item.val, nx, ny);
  const el = elNodes[drag.i];
  if(el){ el.style.left = (nx*Z)+'px'; el.style.top = (ny*Z)+'px'; }
  commit(false);
}
function endDrag(){
  drag = null;
  document.removeEventListener('mousemove', onDrag);
  document.removeEventListener('mouseup', endDrag);
  render();
}

// write model back to textarea + refresh readouts
function commit(reRender){
  document.getElementById('ini').value = serializeModel(model);
  refreshTspl(); syncSelBar();
  if(reRender) render();
}

// ── selection panel ────────────────────────────────────────────────────────────
function syncSelBar(){
  const has = selIdx>=0 && draw[selIdx];
  document.getElementById('selXY').style.display = has ? 'flex' : 'none';
  if(!has){ document.getElementById('selName').textContent = 'No element selected — click one to edit its position.'; return; }
  const d = draw[selIdx];
  document.getElementById('selName').innerHTML = `Selected: <b>${d.type.toUpperCase()}</b> "${(d.text||'').slice(0,24)}"`;
  document.getElementById('selX').value = d.x;
  document.getElementById('selY').value = d.y;
  document.getElementById('selRot').value = String(d.rot||0);
  const sp = sizeSpec(d);
  document.getElementById('szLbl').textContent = sp.sLbl;
  document.getElementById('selSize').value = sp.sVal;
  document.getElementById('selWWrap').style.display = sp.hasW ? 'flex' : 'none';
  if(sp.hasW){
    document.getElementById('wLbl').textContent = sp.wLbl;
    document.getElementById('selW').value = sp.wVal;
  }
}
function paintSel(){
  elNodes.forEach((el, i) => el && el.classList.toggle('sel', i===selIdx));
}
function applyXY(){
  if(selIdx<0) return;
  const d = draw[selIdx];
  d.x = Math.max(0, Math.min(wDot, parseInt(document.getElementById('selX').value)||0));
  d.y = Math.max(0, Math.min(hDot, parseInt(document.getElementById('selY').value)||0));
  d.item.val = setXY(d.item.val, d.x, d.y);
  commit(true);
}
function nudge(dx,dy){
  if(selIdx<0) return;
  const d = draw[selIdx];
  d.x = Math.max(0, Math.min(wDot, d.x+dx));
  d.y = Math.max(0, Math.min(hDot, d.y+dy));
  d.item.val = setXY(d.item.val, d.x, d.y);
  commit(true);
}
function applyRot(){
  if(selIdx<0) return;
  const d = draw[selIdx];
  d.rot = parseInt(document.getElementById('selRot').value)||0;
  d.item.val = setRot(d.item.val, d.rot);
  commit(true);
}
function applySize(){
  if(selIdx<0) return;
  const d = draw[selIdx]; const sp = sizeSpec(d);
  const v = Math.max(1, parseInt(document.getElementById('selSize').value)||1);
  d.item.val = setArg(d.item.val, sp.sIdx, v);
  commit(true);
}
function applyWidth(){
  if(selIdx<0) return;
  const d = draw[selIdx]; const sp = sizeSpec(d);
  if(!sp.hasW) return;
  const v = Math.max(1, parseInt(document.getElementById('selW').value)||1);
  d.item.val = setArg(d.item.val, sp.wIdx, v);
  commit(true);
}
document.addEventListener('keydown', e => {
  if(selIdx<0) return;
  const map={ArrowLeft:[-1,0],ArrowRight:[1,0],ArrowUp:[0,-1],ArrowDown:[0,1]};
  if(map[e.key] && document.activeElement.tagName!=='TEXTAREA' && document.activeElement.tagName!=='INPUT'){
    e.preventDefault(); nudge(...map[e.key]);
  }
});

// ── qty / zoom ──────────────────────────────────────────────────────────────────
function setQty(){ refreshTspl(); }
function setQtyVal(n){ document.getElementById('qty').value = n; refreshTspl(); }
function applyZoom(){
  Z = parseFloat(document.getElementById('zoom').value)||0.55;
  document.getElementById('zoomVal').textContent = Z.toFixed(1)+'×';
  render();
}
function fitWindow(){
  const stage = document.getElementById('stage');
  const avail = stage.clientWidth - 36;
  Z = Math.max(0.15, Math.min(1, +(avail/wDot).toFixed(2)));
  document.getElementById('zoom').value = Z;
  document.getElementById('zoomVal').textContent = Z.toFixed(1)+'×';
  render();
}

// ── send to the Honeywell printer ─────────────────────────────────────────────
function printNow(){
  if(!BC_SHARE){ alert('No printer share configured in software-settings.ini (BCPrinterShareName).'); return; }
  const qty = parseInt(document.getElementById('qty').value)||1;
  const btn = document.getElementById('printBtn');
  const old = btn.textContent; btn.textContent = '⏳ Sending…'; btn.disabled = true;
  const body = new URLSearchParams({
    action:'print', section:SECTION, qty:String(qty), share:BC_SHARE,
    ini:document.getElementById('ini').value
  });
  fetch(location.pathname, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
    .then(r => r.json())
    .then(j => { alert((j.ok?'✅ ':'❌ ')+j.msg); })
    .catch(e => alert('❌ Request failed: '+e))
    .finally(() => { btn.textContent = old; btn.disabled = false; });
}

// ── export PNG ──────────────────────────────────────────────────────────────────
function downloadPng(){
  const lab = document.getElementById('label');
  if(typeof html2canvas === 'undefined'){ alert('html2canvas not loaded'); return; }
  html2canvas(lab, {backgroundColor:'#fff', scale:2}).then(c => {
    const a = document.createElement('a');
    a.href = c.toDataURL('image/png'); a.download = 'label-'+SECTION+'.png'; a.click();
  });
}

window.addEventListener('load', () => {
  // auto-tick "Cut line" if the template enables a cutter
  model = parseModel(document.getElementById('ini').value);
  const r = computeDraw(activeSection());
  if(r.cutterOn) document.getElementById('gCut').checked = true;
  document.getElementById('zoom').value = Z;
  document.getElementById('zoomVal').textContent = Z.toFixed(2)+'×';
  render();
});
</script>
</body>
</html>
