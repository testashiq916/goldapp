<?php
/**
 * Barcode Printer — Setup, Test & Configure
 * http://localhost:8081/goldapp/public/bctemp.php
 */

$INI_PATH = __DIR__ . '/../storage/app/software-settings.ini';
$LOG_PATH = __DIR__ . '/../storage/logs/barcode-print-debug.log';

// ─── INI helpers ──────────────────────────────────────────────────────────────
function readIni(string $path): array
{
    if (!is_file($path)) return [];
    $out = []; $sec = '';
    foreach (preg_split('/\r\n|\r|\n/', file_get_contents($path)) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === ';') continue;
        if (preg_match('/^\[(.+)\]$/', $line, $m)) { $sec = $m[1]; continue; }
        if ($sec && str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $out[$sec][trim($k)] = trim($v);
        }
    }
    return $out;
}

function writeManyIniKeys(string $path, string $section, array $pairs): void
{
    $ini = readIni($path);
    foreach ($pairs as $k => $v) $ini[$section][$k] = $v;
    $buf = '';
    foreach ($ini as $sec => $kvs) {
        $buf .= "[$sec]\n";
        foreach ((array)$kvs as $k => $v) $buf .= "$k=$v\n";
        $buf .= "\n";
    }
    file_put_contents($path, $buf);
}

// ─── Windows printer list ─────────────────────────────────────────────────────
function listWindowsPrinters(): array
{
    if (PHP_OS_FAMILY !== 'Windows') return [];
    $raw = []; exec('wmic printer get Name,ShareName /FORMAT:CSV 2>NUL', $raw);
    $out = [];
    foreach ($raw as $line) {
        $line = trim($line);
        if ($line === '' || stripos($line, 'node') === 0) continue;
        $p = str_getcsv($line);
        if (count($p) >= 3) {
            $name  = trim($p[1] ?? '');
            $share = trim($p[2] ?? '');
            if ($name !== '') $out[] = ['name' => $name, 'share' => $share];
        }
    }
    return $out;
}

// ─── extract just the share name from full UNC or plain name ─────────────────
// "\\SERVER\IMPACT" → "IMPACT"   |   "IMPACT" → "IMPACT"
function bareShare(string $share): string
{
    $share = trim($share);
    if (str_starts_with($share, '\\\\') || str_starts_with($share, '//')) {
        $parts = preg_split('/[\\\\\/]+/', ltrim($share, '\\/'));
        // parts[0]=server, parts[1]=sharename
        return isset($parts[1]) ? trim($parts[1]) : $share;
    }
    return $share;
}

// ─── is share reachable? — use wmic to check printer share list ───────────────
function shareReachable(string $share): bool
{
    $share = bareShare($share);
    if ($share === '') return false;

    // Method 1: check wmic printer list for this share name
    $raw = []; exec('wmic printer get ShareName /FORMAT:CSV 2>NUL', $raw);
    foreach ($raw as $line) {
        $parts = str_getcsv(trim($line));
        foreach ($parts as $part) {
            if (strtolower(trim($part)) === strtolower($share)) return true;
        }
    }

    // Method 2: check Windows print spooler via net share
    $out2 = []; exec('net share 2>NUL', $out2);
    foreach ($out2 as $line) {
        if (stripos($line, $share) !== false) return true;
    }

    // Method 3: try UNC dir listing via cmd (printer shares support this)
    $comp = trim((string)getenv('COMPUTERNAME'));
    foreach (["\\\\localhost\\$share", "\\\\$comp\\$share"] as $t) {
        $o = []; exec('cmd /c dir ' . escapeshellarg($t) . ' >NUL 2>&1 && echo YES', $o);
        if (in_array('YES', $o, true)) return true;
    }

    return false;
}

// ─── send raw TSPL ────────────────────────────────────────────────────────────
function sendRaw(string $share, string $tspl): array
{
    $share = bareShare($share);
    $tmp   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bc_test_' . time() . '.prn';
    file_put_contents($tmp, $tspl);
    $comp  = trim((string)getenv('COMPUTERNAME'));
    $allOut = [];

    $targets = [];
    if ($comp && strtolower($comp) !== 'localhost') $targets[] = "\\\\$comp\\$share";
    $targets[] = "\\\\localhost\\$share";
    $targets[] = "\\\\127.0.0.1\\$share";

    // ── Method 1: PHP file_put_contents directly to UNC printer share ──────────
    foreach ($targets as $t) {
        $w = @file_put_contents($t, $tspl);
        if ($w !== false) {
            $allOut[] = "PHP write $t → OK ($w bytes)";
            @unlink($tmp);
            return ['ok'=>true,'target'=>$t,'msg'=>"Sent $w bytes via PHP direct write",'log'=>$allOut];
        }
        $allOut[] = "PHP write $t → failed";
    }

    // ── Method 2: PowerShell byte stream ──────────────────────────────────────
    foreach ($targets as $t) {
        $tEsc = str_replace("'", "''", $t);
        $fEsc = str_replace("'", "''", $tmp);
        $ps   = "powershell -NoProfile -NonInteractive -Command \"\$b=[System.IO.File]::ReadAllBytes('$fEsc');\$fs=New-Object System.IO.FileStream('$tEsc',[System.IO.FileMode]::Create,[System.IO.FileAccess]::Write,[System.IO.FileShare]::None);\$fs.Write(\$b,0,\$b.Length);\$fs.Close()\" 2>&1";
        $out = []; exec($ps, $out, $rc);
        $txt = implode(' ', $out);
        $allOut[] = "PS stream $t → rc=$rc $txt";
        if ($rc === 0 && stripos($txt, 'error') === false && stripos($txt, 'exception') === false) {
            @unlink($tmp);
            return ['ok'=>true,'target'=>$t,'msg'=>"Sent via PowerShell stream",'log'=>$allOut];
        }
    }

    // ── Method 3: net use LPT1 port then copy ─────────────────────────────────
    foreach ($targets as $t) {
        $del  = []; exec('net use LPT1: /delete /y 2>NUL', $del);
        $map  = []; exec("net use LPT1: \"$t\" /persistent:no 2>&1", $map, $mapRc);
        $allOut[] = "net use LPT1→$t rc=$mapRc " . implode(' ', $map);
        if ($mapRc === 0) {
            $cpOut = []; exec('cmd /c copy /b ' . escapeshellarg($tmp) . ' LPT1: 2>&1', $cpOut, $cpRc);
            exec('net use LPT1: /delete /y 2>NUL');
            $allOut[] = "copy→LPT1 rc=$cpRc " . implode(' ', $cpOut);
            if ($cpRc === 0) { @unlink($tmp); return ['ok'=>true,'target'=>$t,'msg'=>"Sent via LPT1 port map",'log'=>$allOut]; }
        }
        exec('net use LPT1: /delete /y 2>NUL');
    }

    // ── Method 4: Windows print command ──────────────────────────────────────
    foreach ($targets as $t) {
        $out = []; exec("print /D:\"$t\" " . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
        $allOut[] = "print /D:$t rc=$rc " . implode(' ', $out);
        if ($rc === 0) { @unlink($tmp); return ['ok'=>true,'target'=>$t,'msg'=>"Sent via print command",'log'=>$allOut]; }
    }

    // ── Method 5: cmd copy /b (original fallback) ─────────────────────────────
    foreach ($targets as $t) {
        $cmd = 'cmd /c copy /b ' . escapeshellarg($tmp) . ' "' . $t . '" 2>&1';
        $out = []; exec($cmd, $out, $rc);
        $allOut[] = "copy/b $t → rc=$rc " . implode(' ', $out);
        if ($rc === 0) { @unlink($tmp); return ['ok'=>true,'target'=>$t,'msg'=>implode(' ',$out),'log'=>$allOut]; }
    }

    @unlink($tmp);
    return ['ok'=>false,'target'=>implode(', ',$targets),'msg'=>implode(' | ',$allOut),'log'=>$allOut];
}

// ─── test TSPL ────────────────────────────────────────────────────────────────
function makeTspl(float $w, float $h, int $ox): string
{
    $cr = "\r\n";
    return implode($cr, [
        "SIZE $w mm, $h mm", "GAP 3 mm, 0 mm", "DIRECTION 0",
        "REFERENCE 0,0", "OFFSET $ox mm", "SET PEEL OFF", "SET CUTTER OFF", "CLS",
        "TEXT 10,5,\"3\",0,1,1,\"** TEST PRINT **\"",
        "TEXT 10,30,\"3\",0,1,1,\"SALEENA GOLD AND DIAMONDS\"",
        "TEXT 10,55,\"3\",0,1,1,\"" . date('d-m-Y H:i:s') . "\"",
        "BARCODE 10,80,\"128\",50,1,0,2,2,\"SC-TEST001\"",
        "TEXT 10,140,\"3\",0,1,1,\"SC-TEST001   TSPL OK\"",
        "PRINT 1", "",
    ]);
}

// ─── load settings ────────────────────────────────────────────────────────────
$ini      = readIni($INI_PATH);
$sw       = $ini['Software'] ?? [];
$printers = listWindowsPrinters();

$cfg = [
    'printerName'   => $sw['BCPrinterName']          ?? '',
    'shareName'     => $sw['BCPrinterShareName']      ?? '',
    'printMode'     => $sw['BCPrintMode']             ?? 'auto',
    'offsetX'       => (int)($sw['BCPXINC']           ?? 0),
    'offsetY'       => (int)($sw['BCPYINC']           ?? 0),
    'density'       => (int)($sw['BCDensity']         ?? 14),
    'renderAsImage' => $sw['BCRenderAsImage']         ?? 'Y',
    'tplName'       => $sw['BCDesignerTemplateName']  ?? '',
    'labelW'        => 75.0,
    'labelH'        => 25.0,
];

// auto-detect mismatch
$shareOk   = shareReachable($cfg['shareName']);
$autoSuggest = null;   // ['name'=>..., 'share'=>...]
foreach ($printers as $p) {
    $n = strtolower($p['name']);
    if (str_contains($n,'honeywell') || str_contains($n,'tspl') || str_contains($n,'barcode') || str_contains($n,'zebra') || str_contains($n,'ih-')) {
        $autoSuggest = $p;
        break;
    }
}
// if configured share not found but we know the right one
$mismatch = (!$shareOk && $autoSuggest && $autoSuggest['share'] !== '' && $autoSuggest['share'] !== $cfg['shareName']);

$msg = ''; $msgType = 'info';

// ─── handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'save') {
        $pairs = [
            'BCPrinterName'      => trim($_POST['printerName']   ?? ''),
            'BCPrinterShareName' => bareShare(trim($_POST['shareName'] ?? '')),
            'BCPrintMode'        => trim($_POST['printMode']     ?? 'auto'),
            'BCPXINC'            => (string)(int)($_POST['offsetX'] ?? 0),
            'BCPYINC'            => (string)(int)($_POST['offsetY'] ?? 0),
            'BCDensity'          => (string)max(1,min(30,(int)($_POST['density']??14))),
            'BCRenderAsImage'    => ($_POST['renderAsImage']??'Y')==='Y'?'Y':'N',
        ];
        writeManyIniKeys($INI_PATH, 'Software', $pairs);
        $ini = readIni($INI_PATH); $sw = $ini['Software'] ?? [];
        $cfg['printerName']   = $pairs['BCPrinterName'];
        $cfg['shareName']     = $pairs['BCPrinterShareName'];
        $cfg['printMode']     = $pairs['BCPrintMode'];
        $cfg['offsetX']       = (int)$pairs['BCPXINC'];
        $cfg['offsetY']       = (int)$pairs['BCPYINC'];
        $cfg['density']       = (int)$pairs['BCDensity'];
        $cfg['renderAsImage'] = $pairs['BCRenderAsImage'];
        $shareOk = shareReachable($cfg['shareName']);
        $mismatch = false;
        $msg = '✅ Settings saved successfully to software-settings.ini';
        $msgType = 'success';
    }

    if ($action === 'quickfix') {
        $pairs = [
            'BCPrinterName'      => trim($_POST['printerName']  ?? ''),
            'BCPrinterShareName' => bareShare(trim($_POST['shareName'] ?? '')),
        ];
        writeManyIniKeys($INI_PATH, 'Software', $pairs);
        $ini = readIni($INI_PATH); $sw = $ini['Software'] ?? [];
        $cfg['printerName'] = $pairs['BCPrinterName'];
        $cfg['shareName']   = $pairs['BCPrinterShareName'];
        $shareOk  = shareReachable($cfg['shareName']);
        $mismatch = false;
        $msg = '✅ Printer auto-fixed: Share set to "' . $pairs['BCPrinterShareName'] . '"';
        $msgType = 'success';
    }

    if ($action === 'test_raw') {
        $share = trim($_POST['shareName'] ?? $cfg['shareName']);
        $w  = (float)($_POST['labelW'] ?? $cfg['labelW']);
        $h  = (float)($_POST['labelH'] ?? $cfg['labelH']);
        $ox = (int)($_POST['offsetX']  ?? $cfg['offsetX']);
        $r  = sendRaw($share, makeTspl($w, $h, $ox));
        $msg     = $r['ok']
            ? '✅ Test label sent to ' . $r['target'] . ' — check printer'
            : '❌ Failed. Target: ' . $r['target'] . ' — ' . $r['msg'];
        $msgType = $r['ok'] ? 'success' : 'danger';
    }

    if ($action === 'test_custom') {
        $share  = trim($_POST['shareName'] ?? $cfg['shareName']);
        $custom = trim($_POST['customTspl'] ?? '');
        if ($custom === '') {
            $msg = '⚠️ TSPL editor is empty'; $msgType = 'warning';
        } else {
            $r = sendRaw($share, $custom);
            $msg     = $r['ok']
                ? '✅ Custom TSPL sent to ' . $r['target'] . ' — check printer'
                : '❌ Failed. Target: ' . $r['target'] . ' — ' . $r['msg'];
            $msgType = $r['ok'] ? 'success' : 'danger';
        }
    }

    if ($action === 'check_share') {
        $share = trim($_POST['shareName'] ?? $cfg['shareName']);
        $ok    = shareReachable($share);
        $msg     = $ok
            ? '✅ Share \\\\localhost\\' . $share . ' is reachable'
            : '⚠️ Share \\\\localhost\\' . $share . ' NOT found. Ensure the printer is shared with this name.';
        $msgType = $ok ? 'success' : 'warning';
    }

    if ($action === 'clear_log') {
        @file_put_contents($LOG_PATH, '');
        $msg = 'Debug log cleared'; $msgType = 'info';
    }
}

// log tail
$logLines = [];
if (is_file($LOG_PATH)) {
    $all = file($LOG_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logLines = array_slice($all, -50);
}

$tsplPreview = makeTspl($cfg['labelW'], $cfg['labelH'], $cfg['offsetX']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barcode Printer — Setup &amp; Test</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#0b0d16;color:#e2e8f0;font-size:14px;min-height:100vh}

/* top bar */
.topbar{background:#12152a;border-bottom:2px solid #7c6bff;padding:0 24px;height:52px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:100}
.topbar .logo{width:28px;height:28px;background:#7c6bff;border-radius:6px;display:flex;align-items:center;justify-content:center}
.topbar h1{font-size:16px;font-weight:700;color:#fff;letter-spacing:.3px}
.topbar .chip{background:#7c6bff22;border:1px solid #7c6bff66;color:#a89bff;font-size:11px;padding:2px 10px;border-radius:20px}
.topbar .path{margin-left:auto;font-size:11px;color:#3d4468;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:400px}

/* layout */
.page{padding:20px;max-width:1360px;margin:0 auto}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.span2{grid-column:1/-1}
@media(max-width:900px){.grid2{grid-template-columns:1fr}.span2{grid-column:auto}}

/* card */
.card{background:#13162a;border:1px solid #1f2347;border-radius:12px;overflow:hidden}
.card-head{background:#181c36;padding:11px 18px;font-weight:600;font-size:13px;color:#a89bff;border-bottom:1px solid #1f2347;display:flex;align-items:center;gap:8px}
.card-head .ico{width:16px;height:16px;flex-shrink:0}
.card-head .right{margin-left:auto}
.card-body{padding:18px}

/* alerts */
.alert{padding:11px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px}
.alert-success{background:#0d3321;border:1px solid #22c55e55;color:#86efac}
.alert-danger {background:#3b0d0d;border:1px solid #ef444455;color:#fca5a5}
.alert-warning{background:#3b2206;border:1px solid #f59e0b55;color:#fde68a}
.alert-info   {background:#0d1e3b;border:1px solid #3b82f655;color:#93c5fd}

/* mismatch banner */
.banner{background:#2d1800;border:2px solid #f59e0b;border-radius:10px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.banner .bi{font-size:28px;flex-shrink:0}
.banner strong{color:#fde68a;font-size:14px}
.banner p{color:#fbbf24;font-size:12px;margin-top:3px}
.banner .fix-btn{margin-left:auto;background:#f59e0b;color:#000;border:none;padding:9px 20px;border-radius:7px;font-weight:700;font-size:13px;cursor:pointer;white-space:nowrap}
.banner .fix-btn:hover{background:#fbbf24}

/* form */
label{display:block;font-size:12px;color:#7d8caa;margin:12px 0 4px;font-weight:500}
label:first-child{margin-top:0}
input[type=text],input[type=number],select{width:100%;background:#0d0f1e;border:1px solid #1f2347;color:#e2e8f0;padding:8px 11px;border-radius:7px;font-size:13px;outline:none;transition:border .15s}
input:focus,select:focus{border-color:#7c6bff;box-shadow:0 0 0 3px #7c6bff18}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}

/* buttons */
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:7px;border:none;cursor:pointer;font-size:13px;font-weight:600;transition:all .15s;white-space:nowrap}
.btn:hover{filter:brightness(1.12)}
.btn-primary {background:#7c6bff;color:#fff}
.btn-success {background:#22c55e;color:#fff}
.btn-warning {background:#f59e0b;color:#000}
.btn-danger  {background:#ef4444;color:#fff}
.btn-ghost   {background:#1f2347;color:#a89bff;border:1px solid #2a2f5a}
.btn-sm{padding:5px 11px;font-size:12px}
.btn-row{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}

/* status pills */
.pill{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:12px;font-weight:600}
.pill-green {background:#14532d44;border:1px solid #22c55e66;color:#86efac}
.pill-red   {background:#7f1d1d44;border:1px solid #ef444466;color:#fca5a5}
.pill-yellow{background:#78350f44;border:1px solid #f59e0b66;color:#fde68a}
.pill-blue  {background:#1e3a5f44;border:1px solid #3b82f666;color:#93c5fd}
.pill-gray  {background:#37415144;border:1px solid #6b728066;color:#94a3b8}

/* status block */
.status-block{background:#0d0f1e;border:1px solid #1f2347;border-radius:8px;padding:12px 16px;margin-bottom:14px}
.status-row{display:flex;align-items:center;gap:10px;padding:5px 0;border-bottom:1px solid #1a1d35}
.status-row:last-child{border:none}
.status-label{color:#7d8caa;font-size:12px;width:130px;flex-shrink:0}
.status-val  {font-size:13px;color:#e2e8f0;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* printer list */
.plist{list-style:none}
.plist li{display:flex;align-items:center;gap:8px;padding:8px 4px;border-bottom:1px solid #1a1d35;font-size:13px}
.plist li:last-child{border:none}
.plist li.highlight{background:#1a2800;border-radius:7px;padding:8px 10px;border:1px solid #22c55e44;margin-bottom:4px}
.plist li .pname{flex:1;color:#e2e8f0}
.plist li .ptag{background:#1f2347;padding:2px 8px;border-radius:10px;font-size:11px;color:#7d8caa}
.plist li .ptag.share{background:#0d1e3b;color:#93c5fd}
.plist li .ptag.tspl{background:#0d3321;color:#86efac}
.use-form button{background:none;border:1px solid #7c6bff;color:#a89bff;border-radius:5px;padding:3px 10px;font-size:12px;cursor:pointer;transition:all .15s}
.use-form button:hover{background:#7c6bff;color:#fff}

/* ini table */
.ini-tbl{width:100%;border-collapse:collapse;font-size:12px}
.ini-tbl tr{border-bottom:1px solid #1a1d35}
.ini-tbl tr:last-child{border:none}
.ini-tbl td{padding:6px 8px;vertical-align:top}
.ini-tbl td:first-child{color:#a89bff;width:55%}
.ini-tbl td:last-child{color:#e2e8f0;word-break:break-all}
.ini-tbl tr.changed td{background:#1a2800}

/* TSPL preview */
.tspl-pre{background:#070810;border:1px solid #1f2347;border-radius:7px;padding:12px;font-family:'Courier New',monospace;font-size:11px;color:#86efac;overflow-x:auto;line-height:1.7;white-space:pre}

/* log */
.log-box{background:#070810;border:1px solid #1f2347;border-radius:7px;padding:10px;height:280px;overflow-y:auto;font-family:'Courier New',monospace;font-size:11px;line-height:1.6}
.ll{padding:2px 0;border-bottom:1px solid #0f1020}
.ll.ok  {color:#86efac}
.ll.fail{color:#fca5a5}
.ll.skip{color:#fde68a}
.ll.info{color:#93c5fd}

/* scrollbar */
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:#0b0d16}
::-webkit-scrollbar-thumb{background:#2a2f5a;border-radius:3px}

/* ── Alignment Modal ─────────────────────────────────────────────────────── */
.align-overlay{position:fixed;inset:0;background:#000c;z-index:9000;display:flex;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px)}
.align-modal{background:#13162a;border:1px solid #7c6bff66;border-radius:16px;width:min(980px,100%);max-height:94vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 30px 80px #000b}
.align-mhead{background:#181c36;padding:14px 20px;display:flex;align-items:center;gap:12px;border-bottom:2px solid #7c6bff;flex-shrink:0}
.align-mhead h2{font-size:15px;font-weight:700;color:#fff;flex:1;margin:0}
.align-mhead .sub{font-size:11px;color:#4d5580;display:none}
@media(min-width:700px){.align-mhead .sub{display:inline}}
.align-close{background:#1f2347;border:1px solid #2a2f5a;color:#a89bff;width:32px;height:32px;border-radius:7px;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s}
.align-close:hover{background:#ef444422;color:#fca5a5;border-color:#ef444455}
.align-mbody{display:grid;grid-template-columns:1fr 270px;overflow:hidden;min-height:0;flex:1}
@media(max-width:750px){.align-mbody{grid-template-columns:1fr;grid-template-rows:1fr auto}}

/* canvas side */
.align-canvas-wrap{padding:20px 24px;overflow:auto;display:flex;flex-direction:column;align-items:center;gap:12px;background:#0a0c18}
.align-hint{font-size:11px;color:#4d5580;text-align:center;padding:4px 12px;background:#0d0f1e;border:1px solid #1f2347;border-radius:20px}
.label-outer{position:relative;background:#f8f8f8;border:2px solid #7c6bff99;border-radius:3px;overflow:hidden;cursor:crosshair;user-select:none;box-shadow:0 4px 24px #0007}
.label-clip{position:absolute;inset:0;overflow:hidden}
.label-grid-line{position:absolute;top:0;bottom:0;border-left:1px dashed rgba(0,0,0,.12);pointer-events:none}
.label-grid-lbl{position:absolute;top:2px;font-size:7px;color:rgba(0,0,0,.3);pointer-events:none;transform:translateX(-50%)}
.label-content-group{position:absolute;top:0;left:0;cursor:grab;transition:none}
.label-content-group:active{cursor:grabbing}
.lc-el{position:absolute;display:flex;align-items:center;overflow:hidden;white-space:nowrap;border-radius:2px;font-family:'Courier New',monospace}
.lc-text-el{background:#1a3a6bcc;color:#93c5fd;border:1px solid #3b82f655;padding:0 4px;font-size:9px}
.lc-barcode-el{background:#0d3321cc;border:1px solid #22c55e55;flex-direction:column;align-items:flex-start;justify-content:center;padding:3px 5px;gap:2px}
.lc-drag-handle{position:absolute;bottom:4px;right:4px;width:18px;height:18px;background:#7c6bff;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#fff;pointer-events:none}
.label-offset-badge{position:absolute;top:-26px;left:0;background:#7c6bff;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;white-space:nowrap;pointer-events:none}

/* controls side */
.align-ctrl{padding:20px 16px;border-left:1px solid #1f2347;display:flex;flex-direction:column;gap:14px;overflow-y:auto;background:#0f1120}
.align-ctrl-title{font-size:11px;font-weight:700;color:#a89bff;text-transform:uppercase;letter-spacing:.6px}
.offset-box{background:#0d0f1e;border:1px solid #1f2347;border-radius:9px;padding:14px}
.offset-axis{margin-bottom:14px}
.offset-axis:last-child{margin:0}
.offset-axis-head{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.offset-axis-lbl{font-size:12px;color:#7d8caa;flex:1}
.offset-num{background:#13162a;border:1px solid #2a2f5a;color:#e2e8f0;padding:5px 8px;border-radius:6px;font-size:13px;font-weight:600;width:64px;text-align:center;outline:none;transition:border .15s}
.offset-num:focus{border-color:#7c6bff}
.offset-unit{font-size:10px;color:#3d4468}
.offset-slider{width:100%;-webkit-appearance:none;height:5px;background:linear-gradient(90deg,#7c6bff 0%,#1f2347 0%);border-radius:3px;outline:none;cursor:pointer}
.offset-slider::-webkit-slider-thumb{-webkit-appearance:none;width:17px;height:17px;border-radius:50%;background:#7c6bff;border:2px solid #fff3;cursor:pointer;box-shadow:0 2px 8px #0006}
.align-tip{background:#0d1e3b;border:1px solid #3b82f655;border-radius:7px;padding:10px 12px;font-size:11px;color:#93c5fd;line-height:1.7}
.align-btns{display:flex;flex-direction:column;gap:8px;margin-top:auto;padding-top:8px}
</style>
</head>
<body>

<div class="topbar">
  <div class="logo">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="7" y1="7" x2="7" y2="17"/><line x1="11" y1="7" x2="11" y2="17"/><line x1="15" y1="7" x2="15" y2="17"/></svg>
  </div>
  <h1>Barcode Printer &mdash; Setup &amp; Test</h1>
  <span class="chip">bctemp.php</span>
  <div class="path"><?= htmlspecialchars($INI_PATH) ?></div>
</div>

<div class="page">

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($mismatch && $autoSuggest): ?>
<div class="banner">
  <span class="bi">⚠️</span>
  <div>
    <strong>Printer Share Mismatch Detected!</strong>
    <p>Configured share: <b><?= htmlspecialchars($cfg['shareName']) ?></b> — NOT FOUND &nbsp;|&nbsp;
       Detected Honeywell share: <b><?= htmlspecialchars($autoSuggest['share']) ?></b> (<?= htmlspecialchars($autoSuggest['name']) ?>)</p>
  </div>
  <form method="POST" class="use-form" style="margin-left:auto">
    <input type="hidden" name="action" value="quickfix">
    <input type="hidden" name="printerName" value="<?= htmlspecialchars($autoSuggest['name']) ?>">
    <input type="hidden" name="shareName"   value="<?= htmlspecialchars($autoSuggest['share']) ?>">
    <button class="fix-btn" type="submit">⚡ Fix Now — Use "<?= htmlspecialchars($autoSuggest['share']) ?>"</button>
  </form>
</div>
<?php endif; ?>

<div class="grid2">

<!-- ══ LEFT: CONFIGURE ═══════════════════════════════════════════════════════ -->
<div class="card">
  <div class="card-head">
    <svg class="ico" viewBox="0 0 24 24" fill="currentColor"><path d="M12 15.5A3.5 3.5 0 0 1 8.5 12 3.5 3.5 0 0 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5 3.5 3.5 0 0 1-3.5 3.5m7.43-2.92c.04-.36.07-.73.07-1.08s-.03-.71-.07-1.08l2.32-1.82c.21-.17.27-.46.14-.7l-2.2-3.81c-.13-.24-.42-.33-.67-.24l-2.73 1.1c-.57-.44-1.17-.81-1.84-1.08L14 2.42C13.96 2.17 13.74 2 13.5 2h-4.4c-.25 0-.46.17-.5.42l-.43 2.43c-.66.27-1.27.64-1.84 1.08L3.6 4.83c-.25-.09-.54 0-.67.24L.73 8.88c-.14.24-.07.53.14.7l2.32 1.82C3.15 11.77 3.12 12.14 3.12 12.5s.03.71.07 1.08L.87 15.4c-.21.17-.27.46-.14.7l2.2 3.81c.13.24.42.33.67.24l2.73-1.1c.57.44 1.17.81 1.84 1.08l.43 2.43c.04.25.25.42.5.42h4.4c.24 0 .46-.17.5-.42l.43-2.43c.66-.27 1.27-.64 1.84-1.08l2.73 1.1c.25.09.54 0 .67-.24l2.2-3.81c.13-.24.07-.53-.14-.7l-2.32-1.82Z"/></svg>
    Configure Printer Settings
  </div>
  <div class="card-body">
    <form method="POST">
    <input type="hidden" name="action" value="save">

    <label>Printer Name <span style="color:#ef4444">*</span></label>
    <input type="text" name="printerName" id="printerName"
           value="<?= htmlspecialchars($cfg['printerName']) ?>"
           placeholder="e.g. IMPACT by Honeywell IH-2 (TSPL)">

    <label>Printer Share Name <span style="color:#ef4444">*</span>
      &nbsp;<span class="pill <?= $shareOk?'pill-green':'pill-red' ?>" style="font-size:10px;padding:1px 7px">
        <?= $shareOk ? '✔ reachable' : '✘ not found' ?>
      </span>
    </label>
    <input type="text" name="shareName" id="shareName"
           value="<?= htmlspecialchars(bareShare($cfg['shareName'])) ?>"
           placeholder="e.g. IMPACT  (just the share name, not \\server\name)">
    <div style="font-size:11px;color:#f59e0b;margin-top:4px">
      ⚠ Enter only the share name — e.g. <b>IMPACT</b>, not \\SERVER\IMPACT.
      The system will try \\localhost\IMPACT automatically.
    </div>

    <label>Print Mode</label>
    <select name="printMode">
      <?php foreach (['auto'=>'auto — try raw then image','raw'=>'raw — copy /b to share only','windows_image'=>'windows_image — GD image via Windows','none'=>'none — disable print'] as $v=>$lbl): ?>
      <option value="<?=$v?>" <?=$cfg['printMode']===$v?'selected':''?>><?=$lbl?></option>
      <?php endforeach; ?>
    </select>

    <div class="row2">
      <div>
        <label>Offset X (mm)</label>
        <input type="number" name="offsetX" value="<?= $cfg['offsetX'] ?>">
      </div>
      <div>
        <label>Offset Y (mm)</label>
        <input type="number" name="offsetY" value="<?= $cfg['offsetY'] ?>">
      </div>
    </div>

    <div class="row2">
      <div>
        <label>Print Density (1–30)</label>
        <input type="number" name="density" min="1" max="30" value="<?= $cfg['density'] ?>">
      </div>
      <div>
        <label>Render As Image</label>
        <select name="renderAsImage">
          <option value="Y" <?= $cfg['renderAsImage']==='Y'?'selected':'' ?>>Y — Yes</option>
          <option value="N" <?= $cfg['renderAsImage']==='N'?'selected':'' ?>>N — No</option>
        </select>
      </div>
    </div>

    <div class="btn-row">
      <button class="btn btn-primary" type="submit">💾 Save Settings</button>
    </div>
    </form>
  </div>
</div>

<!-- ══ RIGHT: TEST PRINT ═════════════════════════════════════════════════════ -->
<div class="card">
  <div class="card-head">
    <svg class="ico" viewBox="0 0 24 24" fill="currentColor"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
    Test Print
  </div>
  <div class="card-body">

    <div class="status-block">
      <div class="status-row">
        <span class="status-label">Printer</span>
        <span class="status-val"><?= htmlspecialchars($cfg['printerName'] ?: '—') ?></span>
        <span class="pill <?= $cfg['printerName']!==''?'pill-green':'pill-red' ?>"><?= $cfg['printerName']!==''?'SET':'NOT SET' ?></span>
      </div>
      <div class="status-row">
        <span class="status-label">Share</span>
        <span class="status-val">\\localhost\<?= htmlspecialchars($cfg['shareName']?:'—') ?></span>
        <span class="pill <?= $shareOk?'pill-green':'pill-red' ?>"><?= $shareOk?'REACHABLE':'NOT FOUND' ?></span>
      </div>
      <div class="status-row">
        <span class="status-label">Print Mode</span>
        <span class="status-val"><?= htmlspecialchars($cfg['printMode']) ?></span>
        <span class="pill pill-blue"><?= htmlspecialchars($cfg['printMode']) ?></span>
      </div>
      <div class="status-row">
        <span class="status-label">Template</span>
        <span class="status-val"><?= htmlspecialchars($cfg['tplName'] ?: '—') ?></span>
        <span class="pill pill-gray">TSPL</span>
      </div>
    </div>

    <form method="POST">
    <input type="hidden" name="action"      value="test_raw">
    <input type="hidden" name="printerName" value="<?= htmlspecialchars($cfg['printerName']) ?>">
    <input type="hidden" name="shareName"   value="<?= htmlspecialchars($cfg['shareName']) ?>">
    <input type="hidden" name="offsetX"     value="<?= $cfg['offsetX'] ?>">

    <div class="row2">
      <div><label>Label Width (mm)</label>
        <input type="number" name="labelW" value="<?= $cfg['labelW'] ?>" step="0.5"></div>
      <div><label>Label Height (mm)</label>
        <input type="number" name="labelH" value="<?= $cfg['labelH'] ?>" step="0.5"></div>
    </div>

    <div class="btn-row">
      <button class="btn btn-success" type="submit" <?= !$shareOk?'style="opacity:.5"':'' ?>>
        🖨&nbsp;Send Test Label (TSPL)
      </button>
    </div>
    </form>

    <form method="POST" style="margin-top:8px">
    <input type="hidden" name="action"    value="check_share">
    <input type="hidden" name="shareName" value="<?= htmlspecialchars($cfg['shareName']) ?>">
    <div class="btn-row">
      <button class="btn btn-warning" type="submit">🔍 Check Share Reachable</button>
      <button type="button" class="btn btn-ghost" onclick="openAlignModal()">🎯 Align Label</button>
    </div>
    </form>

    <div style="display:flex;align-items:center;gap:8px;margin-top:16px">
      <label style="margin:0;flex:1">TSPL commands <span style="color:#4d5580;font-size:11px;font-weight:400">— editable · send your own commands</span></label>
      <button type="button" class="btn btn-ghost btn-sm" onclick="resetTspl()" title="Restore auto-generated TSPL">↺ Reset</button>
    </div>
    <textarea id="tsplEditor" class="tspl-pre" style="width:100%;resize:vertical;min-height:210px;cursor:text;border-color:#2a2f5a;outline:none;transition:border .15s" spellcheck="false"><?= htmlspecialchars($tsplPreview) ?></textarea>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px">
      <span id="tsplLineCount" style="font-size:11px;color:#3d4468">— lines</span>
      <form method="POST" id="customTsplForm" style="display:inline">
        <input type="hidden" name="action"    value="test_custom">
        <input type="hidden" name="shareName" value="<?= htmlspecialchars($cfg['shareName']) ?>">
        <input type="hidden" id="customTsplInput" name="customTspl" value="">
        <button class="btn btn-primary btn-sm" type="button" onclick="sendCustomTspl()" <?= !$shareOk ? 'style="opacity:.5"' : '' ?>>
          🖨 Send Edited TSPL
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ══ WINDOWS PRINTERS ══════════════════════════════════════════════════════ -->
<div class="card">
  <div class="card-head">
    <svg class="ico" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.33C18 2.09 15.91 0 13.33 0c-1.43 0-2.67.6-3.56 1.56L8 3.34 6.23 1.56C5.34.6 4.1 0 2.67 0 1.19 0-.06 1.19 0 2.67c0 .45.11.89.18 1.33H0v2C0 7.1.9 8 2 8h1v10c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8h1c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z"/></svg>
    Windows Installed Printers
    <span class="pill pill-gray" style="font-size:11px;margin-left:6px"><?= count($printers) ?> found</span>
  </div>
  <div class="card-body">
    <?php if (empty($printers)): ?>
    <p style="color:#3d4468;font-size:12px">Could not list printers (wmic not available).</p>
    <?php else: ?>
    <ul class="plist">
    <?php foreach ($printers as $p):
      $isHoney = (bool)preg_match('/honeywell|tspl|ih-|zebra|barcode/i', $p['name']);
      $isActive = strtolower(trim($p['name'])) === strtolower(trim($cfg['printerName']));
    ?>
      <li class="<?= $isHoney?'highlight':'' ?>">
        <span class="pname">
          <?php if ($isActive): ?><span style="color:#22c55e">▶ </span><?php endif; ?>
          <?= htmlspecialchars($p['name']) ?>
          <?php if ($isHoney): ?><span class="ptag tspl">TSPL/Barcode</span><?php endif; ?>
          <?php if ($p['share']!==''): ?><span class="ptag share">Share: <?= htmlspecialchars($p['share']) ?></span><?php endif; ?>
        </span>
        <form method="POST" class="use-form">
          <input type="hidden" name="action"      value="save">
          <input type="hidden" name="printerName" value="<?= htmlspecialchars($p['name']) ?>">
          <input type="hidden" name="shareName"   value="<?= htmlspecialchars($p['share'] ?: $cfg['shareName']) ?>">
          <input type="hidden" name="printMode"   value="<?= htmlspecialchars($cfg['printMode']) ?>">
          <input type="hidden" name="offsetX"     value="<?= $cfg['offsetX'] ?>">
          <input type="hidden" name="offsetY"     value="<?= $cfg['offsetY'] ?>">
          <input type="hidden" name="density"     value="<?= $cfg['density'] ?>">
          <input type="hidden" name="renderAsImage" value="<?= $cfg['renderAsImage'] ?>">
          <button type="submit"><?= $isActive ? '✔ Active' : 'Use this' ?></button>
        </form>
      </li>
    <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</div>

<!-- ══ CURRENT INI ════════════════════════════════════════════════════════════ -->
<div class="card">
  <div class="card-head">
    <svg class="ico" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15h8v2H8v-2zm0-4h8v2H8v-2z"/></svg>
    software-settings.ini — Barcode Keys
  </div>
  <div class="card-body">
    <?php
    $bcKeys = [
      'BCPrinterName'         => 'Printer Name',
      'BCPrinterShareName'    => 'Share Name',
      'BCPrintMode'           => 'Print Mode',
      'BCPXINC'               => 'Offset X',
      'BCPYINC'               => 'Offset Y',
      'BCDensity'             => 'Density',
      'BCRenderAsImage'       => 'Render As Image',
      'BCStickerMode'         => 'Sticker Mode',
      'BCDesignerTemplateName'=> 'Template Name',
      'BPrintType'            => 'Print Type',
      'BCForm'                => 'BC Form',
    ];
    ?>
    <table class="ini-tbl">
    <?php foreach ($bcKeys as $k => $label):
        $val = $sw[$k] ?? '—';
        $changed = in_array($k,['BCPrinterName','BCPrinterShareName','BCPrintMode','BCPXINC','BCPYINC']);
    ?>
    <tr <?= $changed?'class="changed"':'' ?>>
      <td><?= htmlspecialchars($label) ?> <span style="color:#3d4468;font-size:10px">(<?= htmlspecialchars($k) ?>)</span></td>
      <td><?= htmlspecialchars($val) ?></td>
    </tr>
    <?php endforeach; ?>
    </table>
  </div>
</div>

<!-- ══ DIAGNOSTICS ═══════════════════════════════════════════════════════════ -->
<div class="card span2">
  <div class="card-head">
    <svg class="ico" viewBox="0 0 24 24" fill="currentColor"><path d="M11 15h2v2h-2v-2zm0-8h2v6h-2V7zm1-5C6.47 2 2 6.5 2 12a10 10 0 0 0 10 10 10 10 0 0 0 10-10A10 10 0 0 0 12 2zm0 18a8 8 0 0 1-8-8 8 8 0 0 1 8-8 8 8 0 0 1 8 8 8 8 0 0 1-8 8z"/></svg>
    Printer Share Diagnostics
    <span style="font-size:11px;color:#3d4468;margin-left:8px">COMPUTERNAME: <?= htmlspecialchars((string)getenv('COMPUTERNAME')) ?>  &nbsp;|&nbsp; PHP OS: <?= PHP_OS ?> &nbsp;|&nbsp; Apache user: <?= htmlspecialchars(trim((string)shell_exec('whoami 2>NUL') ?: '?')) ?></span>
  </div>
  <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">

    <?php
    // wmic shares
    $wmicOut = []; exec('wmic printer get Name,ShareName /FORMAT:CSV 2>NUL', $wmicOut);
    // net share
    $netOut  = []; exec('net share 2>NUL', $netOut);
    // net view localhost
    $viewOut = []; exec('net view \\\\localhost 2>NUL', $viewOut);
    $compName = trim((string)getenv('COMPUTERNAME'));
    ?>

    <div>
      <div style="font-size:12px;font-weight:600;color:#a89bff;margin-bottom:8px">wmic printer ShareNames</div>
      <div class="tspl-pre" style="height:180px;overflow-y:auto;font-size:11px"><?php
        foreach ($wmicOut as $l) {
          $l = trim($l);
          if ($l === '') continue;
          $hi = (stripos($l, bareShare($cfg['shareName'])) !== false);
          echo '<span style="' . ($hi?'color:#fde68a;font-weight:bold':'') . '">' . htmlspecialchars($l) . '</span>' . "\n";
        }
      ?></div>
    </div>

    <div>
      <div style="font-size:12px;font-weight:600;color:#a89bff;margin-bottom:8px">net share (all Windows shares)</div>
      <div class="tspl-pre" style="height:180px;overflow-y:auto;font-size:11px"><?php
        foreach ($netOut as $l) {
          $l = trim($l);
          if ($l === '') continue;
          $hi = (stripos($l, bareShare($cfg['shareName'])) !== false);
          echo '<span style="' . ($hi?'color:#fde68a;font-weight:bold':'') . '">' . htmlspecialchars($l) . '</span>' . "\n";
        }
      ?></div>
    </div>

    <div>
      <div style="font-size:12px;font-weight:600;color:#a89bff;margin-bottom:8px">net view \\localhost (visible shares)</div>
      <div class="tspl-pre" style="height:180px;overflow-y:auto;font-size:11px"><?php
        if (empty($viewOut)) {
          echo '<span style="color:#fca5a5">No output — try net view \\\\' . htmlspecialchars($compName) . ' in CMD</span>';
        }
        foreach ($viewOut as $l) {
          $l = trim($l);
          if ($l === '') continue;
          $hi = (stripos($l, bareShare($cfg['shareName'])) !== false);
          echo '<span style="' . ($hi?'color:#fde68a;font-weight:bold':'') . '">' . htmlspecialchars($l) . '</span>' . "\n";
        }
      ?></div>
    </div>

  </div>
</div>

<!-- ══ DEBUG LOG ═════════════════════════════════════════════════════════════ -->
<div class="card span2">
  <div class="card-head">
    <svg class="ico" viewBox="0 0 24 24" fill="currentColor"><path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.11 0 2-.89 2-2V5c0-1.11-.89-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4v-2z"/></svg>
    Print Debug Log
    <span style="margin-left:6px;font-size:11px;color:#3d4468"><?= htmlspecialchars($LOG_PATH) ?></span>
    <span class="right">
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="clear_log">
        <button class="btn btn-ghost btn-sm" type="submit">🗑 Clear Log</button>
      </form>
    </span>
  </div>
  <div class="card-body">
    <?php if (empty($logLines)): ?>
    <p style="color:#3d4468;font-size:12px">Log is empty — send a test print to see output here.</p>
    <?php else: ?>
    <div class="log-box" id="logBox">
    <?php foreach ($logLines as $line):
      $cls = 'll info';
      if (str_contains($line,'success')) $cls = 'll ok';
      elseif (str_contains($line,'failed') || str_contains($line,'abort') || str_contains($line,'error')) $cls = 'll fail';
      elseif (str_contains($line,'skip') || str_contains($line,'missing')) $cls = 'll skip';
    ?>
    <div class="<?= $cls ?>"><?= htmlspecialchars($line) ?></div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

</div><!-- /grid -->
</div><!-- /page -->

<!-- ══ ALIGNMENT MODAL ══════════════════════════════════════════════════════ -->
<div id="alignModal" style="display:none" class="align-overlay">
  <div class="align-modal">
    <div class="align-mhead">
      <span style="font-size:22px;line-height:1">🎯</span>
      <h2>Label Alignment — Drag &amp; Drop</h2>
      <span class="sub">Drag the label content to set print offset. Apply writes Offset X/Y to the settings form.</span>
      <button class="align-close" onclick="closeAlignModal()" title="Close">✕</button>
    </div>
    <div class="align-mbody">

      <!-- ── Canvas ── -->
      <div class="align-canvas-wrap">
        <div class="align-hint">
          📐 Label <b><?= $cfg['labelW'] ?>mm × <?= $cfg['labelH'] ?>mm</b> at 203 DPI &nbsp;|&nbsp;
          <b>Drag</b> the content block to shift print position &nbsp;|&nbsp; Hold <b>Shift</b> = fine movement
        </div>

        <div id="labelOuter" class="label-outer" style="width:600px;height:200px">
          <!-- mm grid lines -->
          <?php for ($i = 1; $i <= 7; $i++): $px = $i * 80; ?>
          <div class="label-grid-line" style="left:<?= $px ?>px"></div>
          <div class="label-grid-lbl" style="left:<?= $px ?>px"><?= $i * 10 ?>mm</div>
          <?php endfor; ?>
          <?php for ($i = 1; $i <= 2; $i++): $py = $i * 67; ?>
          <div style="position:absolute;left:0;right:0;border-top:1px dashed rgba(0,0,0,.1);top:<?= $py ?>px;pointer-events:none">
            <span style="font-size:7px;color:rgba(0,0,0,.25);padding-left:2px"><?= round($i*67/8) ?>mm</span>
          </div>
          <?php endfor; ?>

          <!-- Draggable content group -->
          <div id="lcGroup" class="label-content-group">
            <div class="label-offset-badge" id="offsetBadge">X: 0mm &nbsp; Y: 0mm</div>

            <!-- TEXT: ** TEST PRINT ** at y=5dots -->
            <div class="lc-el lc-text-el" style="left:10px;top:5px;height:19px;font-size:8px;color:#bfdbfe">** TEST PRINT **</div>

            <!-- TEXT: Shop name at y=30dots -->
            <div class="lc-el lc-text-el" style="left:10px;top:30px;height:22px;font-size:10px;font-weight:700;color:#dbeafe">SALEENA GOLD AND DIAMONDS</div>

            <!-- TEXT: Date at y=55dots -->
            <div class="lc-el lc-text-el" style="left:10px;top:55px;height:18px;font-size:8.5px;color:#a5b4fc"><?= date('d-m-Y H:i:s') ?></div>

            <!-- BARCODE at y=80dots height=50dots -->
            <div class="lc-el lc-barcode-el" style="left:10px;top:80px;width:230px;height:50px">
              <div style="width:220px;height:32px;background:repeating-linear-gradient(90deg,#22c55e 0px,#22c55e 2px,transparent 2px,transparent 4px,#22c55e 4px,#22c55e 5px,transparent 5px,transparent 8px,#22c55e 8px,#22c55e 9px,transparent 9px,transparent 12px,#22c55e 12px,#22c55e 14px,transparent 14px,transparent 17px,#22c55e 17px,#22c55e 18px,transparent 18px,transparent 21px,#22c55e 21px,#22c55e 23px,transparent 23px,transparent 26px,#22c55e 26px,#22c55e 27px,transparent 27px,transparent 31px);border-radius:1px"></div>
              <div style="font-size:8px;color:#86efac;padding-top:2px">SC-TEST001</div>
            </div>

            <!-- TEXT: bottom at y=140dots -->
            <div class="lc-el lc-text-el" style="left:10px;top:140px;height:18px;font-size:8.5px;color:#a5b4fc">SC-TEST001&nbsp;&nbsp;TSPL OK</div>

            <!-- Drag handle indicator -->
            <div class="lc-drag-handle">⠿</div>
          </div>
        </div>

        <!-- Live offset readout -->
        <div style="display:flex;gap:24px;font-size:12px">
          <span style="color:#7d8caa">↔ X: <b id="aOXReadout" style="color:#a89bff;font-size:14px">0</b> <span style="color:#3d4468">mm</span></span>
          <span style="color:#7d8caa">↕ Y: <b id="aOYReadout" style="color:#a89bff;font-size:14px">0</b> <span style="color:#3d4468">mm</span></span>
          <span style="color:#3d4468;font-size:11px">(dots: <span id="aDotsReadout">0, 0</span>)</span>
        </div>
      </div>

      <!-- ── Controls ── -->
      <div class="align-ctrl">
        <div class="align-ctrl-title">Offset Controls</div>

        <div class="offset-box">
          <div class="offset-axis">
            <div class="offset-axis-head">
              <span class="offset-axis-lbl">↔ Offset X (horizontal)</span>
              <input type="number" id="aOX" class="offset-num" step="0.5" min="-20" max="20" value="0">
              <span class="offset-unit">mm</span>
            </div>
            <input type="range" id="aOXSlider" class="offset-slider" min="-20" max="20" step="0.5" value="0">
          </div>
          <div class="offset-axis">
            <div class="offset-axis-head">
              <span class="offset-axis-lbl">↕ Offset Y (vertical)</span>
              <input type="number" id="aOY" class="offset-num" step="0.5" min="-20" max="20" value="0">
              <span class="offset-unit">mm</span>
            </div>
            <input type="range" id="aOYSlider" class="offset-slider" min="-20" max="20" step="0.5" value="0">
          </div>
        </div>

        <div class="align-tip">
          💡 <b>How to use:</b><br>
          1. Print a test label<br>
          2. Measure how far content is off<br>
          3. Drag or enter offset to compensate<br>
          4. Click <b>Apply Offsets</b> then <b>Save Settings</b><br><br>
          + X = shift right &nbsp; − X = shift left<br>
          + Y = shift down &nbsp; − Y = shift up
        </div>

        <div class="align-btns">
          <button class="btn btn-ghost btn-sm" onclick="resetAlignOffsets()">↺ Reset to 0, 0</button>
          <button class="btn btn-success" onclick="applyAlignOffsets()">✓ Apply Offsets</button>
          <button class="btn btn-ghost" onclick="closeAlignModal()">Cancel</button>
        </div>
      </div>

    </div><!-- /align-mbody -->
  </div><!-- /align-modal -->
</div><!-- /align-overlay -->

<script>
// ─── TSPL Editor ─────────────────────────────────────────────────────────────
var defaultTspl = <?= json_encode($tsplPreview) ?>;
var tsplEd = document.getElementById('tsplEditor');
var tsplLC = document.getElementById('tsplLineCount');

function updateLineCount() {
  var n = tsplEd.value.split('\n').filter(l => l.trim() !== '').length;
  tsplLC.textContent = n + ' line' + (n !== 1 ? 's' : '');
}
if (tsplEd) {
  tsplEd.addEventListener('input', updateLineCount);
  tsplEd.addEventListener('focus', function () { this.style.borderColor = '#7c6bff'; });
  tsplEd.addEventListener('blur',  function () { this.style.borderColor = '#2a2f5a'; });
  // Tab key inserts spaces instead of changing focus
  tsplEd.addEventListener('keydown', function (e) {
    if (e.key === 'Tab') {
      e.preventDefault();
      var s = this.selectionStart, end = this.selectionEnd;
      this.value = this.value.substring(0, s) + '  ' + this.value.substring(end);
      this.selectionStart = this.selectionEnd = s + 2;
    }
  });
  updateLineCount();
}

function resetTspl() {
  if (tsplEd) { tsplEd.value = defaultTspl; updateLineCount(); }
}

function sendCustomTspl() {
  if (!tsplEd) return;
  var v = tsplEd.value.trim();
  if (!v) { alert('TSPL editor is empty.'); return; }
  document.getElementById('customTsplInput').value = v;
  document.getElementById('customTsplForm').submit();
}

// ─── Printer list dblclick ────────────────────────────────────────────────────
// fill form fields when a printer list item is clicked (also done by "Use this" POST)
document.querySelectorAll('.plist li').forEach(li => {
  li.addEventListener('dblclick', () => {
    const f = li.querySelector('form');
    if (!f) return;
    document.getElementById('printerName').value = f.querySelector('[name=printerName]').value;
    document.getElementById('shareName').value   = f.querySelector('[name=shareName]').value;
  });
});
// auto-scroll log
var lb = document.getElementById('logBox');
if (lb) lb.scrollTop = lb.scrollHeight;

// ─── Alignment Modal ──────────────────────────────────────────────────────────
(function () {
  var modal    = document.getElementById('alignModal');
  var group    = document.getElementById('lcGroup');
  var badge    = document.getElementById('offsetBadge');
  var oxNum    = document.getElementById('aOX');
  var oyNum    = document.getElementById('aOY');
  var oxSlider = document.getElementById('aOXSlider');
  var oySlider = document.getElementById('aOYSlider');
  var oxRead   = document.getElementById('aOXReadout');
  var oyRead   = document.getElementById('aOYReadout');
  var dotsRead = document.getElementById('aDotsReadout');

  // 1 TSPL dot ≈ 1/203 inch ≈ 0.125 mm; 1mm ≈ 8 dots
  // Canvas scale: 1px = 1 TSPL dot  →  1mm = 8px on canvas
  var PX_PER_MM = 8;
  var curOXpx = 0, curOYpx = 0;
  var drag = null;

  function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
  function pxToMm(px) { return +(px / PX_PER_MM).toFixed(1); }
  function mmToPx(mm) { return Math.round(mm * PX_PER_MM); }

  function syncSliderBg(slider) {
    var min = +slider.min, max = +slider.max, val = +slider.value;
    var pct = ((val - min) / (max - min) * 100).toFixed(1);
    slider.style.background = 'linear-gradient(90deg,#7c6bff ' + pct + '%,#1f2347 ' + pct + '%)';
  }

  function applyOffsets(oxPx, oyPx) {
    curOXpx = clamp(Math.round(oxPx), -160, 160);
    curOYpx = clamp(Math.round(oyPx), -80,  80);
    group.style.transform = 'translate(' + curOXpx + 'px,' + curOYpx + 'px)';
    var oxMm = pxToMm(curOXpx), oyMm = pxToMm(curOYpx);
    [oxNum, oxSlider].forEach(el => el.value = oxMm);
    [oyNum, oySlider].forEach(el => el.value = oyMm);
    oxRead.textContent = oxMm; oyRead.textContent = oyMm;
    badge.textContent  = 'X: ' + oxMm + 'mm   Y: ' + oyMm + 'mm';
    dotsRead.textContent = curOXpx + ', ' + curOYpx;
    badge.style.background = (oxMm === 0 && oyMm === 0) ? '#22c55e' : '#7c6bff';
    syncSliderBg(oxSlider); syncSliderBg(oySlider);
  }

  // Mouse drag
  group.addEventListener('mousedown', function (e) {
    e.preventDefault();
    drag = {sx: e.clientX, sy: e.clientY, ox: curOXpx, oy: curOYpx};
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup',   onUp);
    group.style.cursor = 'grabbing';
  });

  function onMove(e) {
    if (!drag) return;
    var step = e.shiftKey ? 0.25 : 1; // Shift = fine
    applyOffsets(drag.ox + (e.clientX - drag.sx) * step,
                 drag.oy + (e.clientY - drag.sy) * step);
  }

  function onUp() {
    drag = null; group.style.cursor = 'grab';
    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup', onUp);
  }

  // Touch support
  group.addEventListener('touchstart', function (e) {
    var t = e.touches[0]; e.preventDefault();
    drag = {sx: t.clientX, sy: t.clientY, ox: curOXpx, oy: curOYpx};
  }, {passive: false});
  group.addEventListener('touchmove', function (e) {
    if (!drag) return; e.preventDefault();
    var t = e.touches[0];
    applyOffsets(drag.ox + (t.clientX - drag.sx), drag.oy + (t.clientY - drag.sy));
  }, {passive: false});
  group.addEventListener('touchend', function () { drag = null; });

  // Input events
  oxSlider.addEventListener('input', function () { applyOffsets(mmToPx(+this.value), curOYpx); });
  oySlider.addEventListener('input', function () { applyOffsets(curOXpx, mmToPx(+this.value)); });
  oxNum.addEventListener('change', function () { applyOffsets(mmToPx(+this.value), curOYpx); });
  oyNum.addEventListener('change', function () { applyOffsets(curOXpx, mmToPx(+this.value)); });

  // Close on backdrop click
  modal.addEventListener('click', function (e) { if (e.target === modal) closeAlignModal(); });

  // Keyboard
  document.addEventListener('keydown', function (e) {
    if (modal.style.display === 'none') return;
    if (e.key === 'Escape') { closeAlignModal(); return; }
    var step = e.shiftKey ? PX_PER_MM / 2 : PX_PER_MM; // 0.5mm or 1mm per key
    if (e.key === 'ArrowLeft')  { applyOffsets(curOXpx - step, curOYpx); e.preventDefault(); }
    if (e.key === 'ArrowRight') { applyOffsets(curOXpx + step, curOYpx); e.preventDefault(); }
    if (e.key === 'ArrowUp')    { applyOffsets(curOXpx, curOYpx - step); e.preventDefault(); }
    if (e.key === 'ArrowDown')  { applyOffsets(curOXpx, curOYpx + step); e.preventDefault(); }
    if (e.key === '0') resetAlignOffsets();
  });

  // Public API
  window.openAlignModal = function () {
    var ox = +(document.querySelector('form input[name="offsetX"]') || {value: <?= $cfg['offsetX'] ?>}).value || 0;
    var oy = +(document.querySelector('form input[name="offsetY"]') || {value: <?= $cfg['offsetY'] ?>}).value || 0;
    modal.style.display = 'flex';
    applyOffsets(mmToPx(ox), mmToPx(oy));
  };

  window.closeAlignModal = function () { modal.style.display = 'none'; };

  window.resetAlignOffsets = function () { applyOffsets(0, 0); };

  window.applyAlignOffsets = function () {
    var oxMm = pxToMm(curOXpx), oyMm = pxToMm(curOYpx);
    // update both configure form and test form
    document.querySelectorAll('input[name="offsetX"]').forEach(el => el.value = oxMm);
    document.querySelectorAll('input[name="offsetY"]').forEach(el => el.value = oyMm);
    modal.style.display = 'none';
    // flash notification
    var a = document.createElement('div');
    a.className = 'alert alert-success';
    a.style.cssText = 'position:fixed;top:64px;left:50%;transform:translateX(-50%);z-index:9999;min-width:340px;text-align:center;animation:fadeOut 3s forwards';
    a.textContent = '✅ Offsets applied: X=' + oxMm + 'mm, Y=' + oyMm + 'mm — Click Save Settings to persist';
    document.body.appendChild(a);
    setTimeout(() => a.remove(), 3200);
  };

  // Init with current saved values
  applyOffsets(mmToPx(<?= (int)$cfg['offsetX'] ?>), mmToPx(<?= (int)$cfg['offsetY'] ?>));
})();
</script>
<style>
@keyframes fadeOut{0%{opacity:1;top:64px}80%{opacity:1}100%{opacity:0;top:80px;pointer-events:none}}
</style>
</body>
</html>
