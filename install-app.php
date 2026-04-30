<?php
/**
 * GoldApp Installer
 * Place this file in the project root (same folder as artisan).
 * Access via browser: http://localhost/goldapp/install-app.php
 * DELETE this file after installation is complete.
 */

define('INSTALLER_VERSION', '1.0');
define('BASE_PATH', __DIR__);
define('ENV_FILE', BASE_PATH . '/.env');
define('ENV_EXAMPLE', BASE_PATH . '/.env.example');
define('ARTISAN', BASE_PATH . '/artisan');

session_start();

// ── helpers ─────────────────────────────────────────────────────────────────

function envVal(string $key, string $default = ''): string
{
    if (!file_exists(ENV_FILE)) return $default;
    $content = file_get_contents(ENV_FILE);
    if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $content, $m)) {
        return trim($m[1], " \t\r\n\"'");
    }
    return $default;
}

function writeEnv(array $values): bool
{
    $template = file_exists(ENV_EXAMPLE)
        ? file_get_contents(ENV_EXAMPLE)
        : file_get_contents(ENV_FILE);

    foreach ($values as $key => $val) {
        $escaped = (strpbrk((string)$val, ' #"\'') !== false) ? '"' . addslashes($val) . '"' : $val;
        if (preg_match('/^' . preg_quote($key, '/') . '=/m', $template)) {
            $template = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $key . '=' . $escaped, $template);
        } else {
            $template .= "\n" . $key . '=' . $escaped;
        }
    }
    return file_put_contents(ENV_FILE, $template) !== false;
}

function runArtisan(string $cmd): array
{
    $php = PHP_BINARY ?: 'php';
    $fullCmd = escapeshellarg($php) . ' ' . escapeshellarg(ARTISAN) . ' ' . $cmd . ' 2>&1';
    exec($fullCmd, $output, $code);
    return ['code' => $code, 'output' => implode("\n", $output)];
}

function testDbConnection(string $host, int $port, string $db, string $user, string $pass): ?string
{
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
        return null; // success
    } catch (PDOException $e) {
        // Try without DB name (to allow creating it)
        try {
            $dsn2 = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn2, $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return null; // created successfully
        } catch (PDOException $e2) {
            return $e->getMessage();
        }
    }
}

function reqCheck(): array
{
    $checks = [];

    // PHP version
    $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
    $checks[] = ['name' => 'PHP >= 8.2', 'ok' => $phpOk, 'value' => PHP_VERSION];

    // Extensions
    foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo'] as $ext) {
        $checks[] = ['name' => "ext-{$ext}", 'ok' => extension_loaded($ext), 'value' => extension_loaded($ext) ? 'loaded' : 'missing'];
    }

    // Writable paths
    foreach ([BASE_PATH . '/.env', BASE_PATH . '/storage', BASE_PATH . '/bootstrap/cache'] as $path) {
        $label = str_replace(BASE_PATH . '/', '', $path);
        if (is_file($path)) {
            $ok = is_writable($path);
        } else {
            $ok = is_dir($path) && is_writable($path);
        }
        $checks[] = ['name' => "{$label} writable", 'ok' => $ok, 'value' => $ok ? 'OK' : 'not writable'];
    }

    // artisan exists
    $checks[] = ['name' => 'artisan found', 'ok' => file_exists(ARTISAN), 'value' => file_exists(ARTISAN) ? 'found' : 'missing'];

    return $checks;
}

// ── step routing ─────────────────────────────────────────────────────────────

$step = (int)($_GET['step'] ?? $_SESSION['step'] ?? 1);
$errors = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Step 2: Save DB config
    if ($action === 'save_db') {
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = (int)($_POST['db_port'] ?? 3306);
        $db   = trim($_POST['db_database'] ?? '');
        $user = trim($_POST['db_username'] ?? 'root');
        $pass = $_POST['db_password'] ?? '';
        $appName = trim($_POST['app_name'] ?? 'GoldApp');
        $appUrl  = rtrim(trim($_POST['app_url'] ?? 'http://localhost/goldapp'), '/');

        if ($db === '') $errors[] = 'Database name is required.';

        if (empty($errors)) {
            $connErr = testDbConnection($host, $port, $db, $user, $pass);
            if ($connErr) {
                $errors[] = 'DB connection failed: ' . $connErr;
            } else {
                writeEnv([
                    'APP_NAME'     => $appName,
                    'APP_ENV'      => 'production',
                    'APP_DEBUG'    => 'false',
                    'APP_URL'      => $appUrl,
                    'DB_CONNECTION' => 'mysql',
                    'DB_HOST'      => $host,
                    'DB_PORT'      => $port,
                    'DB_DATABASE'  => $db,
                    'DB_USERNAME'  => $user,
                    'DB_PASSWORD'  => $pass,
                    'SESSION_DRIVER' => 'file',
                    'CACHE_STORE'  => 'file',
                    'LOG_LEVEL'    => 'error',
                ]);
                $_SESSION['step'] = 3;
                header('Location: install-app.php?step=3');
                exit;
            }
        }
        $step = 2;
    }

    // Step 3: Run install
    if ($action === 'run_install') {
        $log = [];

        // Generate app key if missing
        $key = envVal('APP_KEY');
        if ($key === '' || $key === 'base64:') {
            $r = runArtisan('key:generate --force');
            $log[] = ['cmd' => 'key:generate', 'out' => $r['output'], 'ok' => $r['code'] === 0];
        } else {
            $log[] = ['cmd' => 'key:generate', 'out' => 'Skipped (key already set)', 'ok' => true];
        }

        // Clear config cache first so new .env is read
        runArtisan('config:clear');
        runArtisan('cache:clear');
        runArtisan('route:clear');

        // Run migrations
        $r = runArtisan('migrate --force');
        $log[] = ['cmd' => 'migrate', 'out' => $r['output'], 'ok' => $r['code'] === 0];

        // Re-cache
        $r = runArtisan('config:cache');
        $log[] = ['cmd' => 'config:cache', 'out' => $r['output'], 'ok' => $r['code'] === 0];
        $r = runArtisan('route:cache');
        $log[] = ['cmd' => 'route:cache', 'out' => $r['output'], 'ok' => $r['code'] === 0];

        // Storage link (ignore error — XAMPP may not need it)
        $r = runArtisan('storage:link');
        $log[] = ['cmd' => 'storage:link', 'out' => $r['output'], 'ok' => true];

        $anyFailed = collect_failed($log);

        $_SESSION['install_log'] = $log;
        $_SESSION['install_ok']  = !$anyFailed;
        $_SESSION['step'] = 4;
        header('Location: install-app.php?step=4');
        exit;
    }

    // Step 4: Self-delete
    if ($action === 'delete_installer') {
        @unlink(__FILE__);
        $_SESSION = [];
        session_destroy();
        $url = envVal('APP_URL', 'http://localhost/goldapp');
        header('Location: ' . $url . '/login');
        exit;
    }
}

function collect_failed(array $log): bool {
    foreach ($log as $entry) {
        if (!$entry['ok']) return true;
    }
    return false;
}

// ── HTML output ──────────────────────────────────────────────────────────────
$reqChecks = reqCheck();
$allReqOk  = array_reduce($reqChecks, fn($c, $i) => $c && $i['ok'], true);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>GoldApp Installer</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f3f4f6;color:#1f2937;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:32px 16px}
.wrap{width:100%;max-width:680px}
h1{font-size:24px;font-weight:700;color:#111827;margin-bottom:4px}
.sub{color:#6b7280;font-size:13px;margin-bottom:28px}
.card{background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:28px;margin-bottom:20px}
.card h2{font-size:16px;font-weight:700;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #e5e7eb}
.steps{display:flex;gap:0;margin-bottom:28px}
.step{flex:1;text-align:center;padding:10px 4px;font-size:12px;border-bottom:3px solid #e5e7eb;color:#9ca3af}
.step.active{border-color:#2563eb;color:#2563eb;font-weight:700}
.step.done{border-color:#16a34a;color:#16a34a}
table.checks{width:100%;border-collapse:collapse;font-size:13px}
table.checks td{padding:6px 8px;border-bottom:1px solid #f3f4f6}
table.checks td:last-child{text-align:right;font-weight:600}
.ok{color:#16a34a}.fail{color:#dc2626}
.field{margin-bottom:16px}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:5px;color:#374151}
.field input{width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:14px}
.field input:focus{outline:none;border-color:#2563eb}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{display:inline-block;padding:10px 22px;border:none;border-radius:5px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none}
.btn-primary{background:#2563eb;color:#fff}.btn-primary:hover{background:#1d4ed8}
.btn-danger{background:#dc2626;color:#fff}.btn-danger:hover{background:#b91c1c}
.btn-green{background:#16a34a;color:#fff}.btn-green:hover{background:#15803d}
.btn-gray{background:#e5e7eb;color:#374151}
.errs{background:#fef2f2;border:1px solid #fca5a5;border-radius:4px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#b91c1c}
.errs li{margin-left:16px}
.log-block{background:#111827;color:#d1fae5;font-family:monospace;font-size:12px;padding:14px;border-radius:4px;white-space:pre-wrap;max-height:260px;overflow-y:auto;margin-top:10px}
.log-entry{margin-bottom:14px}
.log-cmd{font-weight:700;color:#93c5fd;margin-bottom:4px}
.log-ok{color:#4ade80}.log-fail{color:#f87171}
.badge{display:inline-block;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:700}
.badge-ok{background:#dcfce7;color:#15803d}.badge-fail{background:#fee2e2;color:#b91c1c}
.warn-box{background:#fffbeb;border:1px solid #fbbf24;border-radius:4px;padding:12px 14px;font-size:13px;color:#92400e;margin-top:16px}
.info-grid{display:grid;grid-template-columns:140px 1fr;gap:6px 12px;font-size:13px;margin-top:8px}
.info-grid dt{color:#6b7280;font-weight:600}
.info-grid dd{color:#111827}
</style>
</head>
<body>
<div class="wrap">
    <h1>⚙️ GoldApp Installer</h1>
    <p class="sub">v<?= INSTALLER_VERSION ?> &nbsp;·&nbsp; Laravel <?= file_exists(BASE_PATH.'/vendor/laravel/framework/src/Illuminate/Foundation/Application.php') ? preg_match("/VERSION = '([^']+)'/", file_get_contents(BASE_PATH.'/vendor/laravel/framework/src/Illuminate/Foundation/Application.php'), $m) ? $m[1] : '?' : '?' ?></p>

    <div class="steps">
        <div class="step <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">1 · Requirements</div>
        <div class="step <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">2 · Database</div>
        <div class="step <?= $step === 3 ? 'active' : ($step > 3 ? 'done' : '') ?>">3 · Install</div>
        <div class="step <?= $step === 4 ? 'active' : '' ?>">4 · Done</div>
    </div>

<?php if ($step === 1): ?>
    <div class="card">
        <h2>System Requirements</h2>
        <table class="checks">
            <?php foreach ($reqChecks as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td class="<?= $c['ok'] ? 'ok' : 'fail' ?>"><?= htmlspecialchars($c['value']) ?> <?= $c['ok'] ? '✔' : '✘' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php if (!$allReqOk): ?>
        <div class="errs" style="margin-top:16px">
            <strong>Some requirements are not met.</strong> Fix the issues above before continuing.
        </div>
        <?php else: ?>
        <div style="margin-top:20px">
            <a href="install-app.php?step=2" class="btn btn-primary">Continue →</a>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($step === 2): ?>
    <div class="card">
        <h2>Application & Database Configuration</h2>

        <?php if (!empty($errors)): ?>
        <div class="errs"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST" action="install-app.php?step=2">
            <input type="hidden" name="action" value="save_db">

            <div class="row2">
                <div class="field">
                    <label>App Name</label>
                    <input type="text" name="app_name" value="<?= htmlspecialchars($_POST['app_name'] ?? envVal('APP_NAME', 'GoldApp')) ?>" required>
                </div>
                <div class="field">
                    <label>App URL</label>
                    <input type="text" name="app_url" value="<?= htmlspecialchars($_POST['app_url'] ?? envVal('APP_URL', 'http://localhost/goldapp')) ?>" required>
                </div>
            </div>

            <hr style="border:none;border-top:1px solid #e5e7eb;margin:8px 0 18px">

            <div class="row2">
                <div class="field">
                    <label>DB Host</label>
                    <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? envVal('DB_HOST', '127.0.0.1')) ?>" required>
                </div>
                <div class="field">
                    <label>DB Port</label>
                    <input type="number" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? envVal('DB_PORT', '3306')) ?>" required>
                </div>
            </div>
            <div class="field">
                <label>Database Name</label>
                <input type="text" name="db_database" value="<?= htmlspecialchars($_POST['db_database'] ?? envVal('DB_DATABASE', 'goldapp')) ?>" required placeholder="e.g. goldapp">
            </div>
            <div class="row2">
                <div class="field">
                    <label>DB Username</label>
                    <input type="text" name="db_username" value="<?= htmlspecialchars($_POST['db_username'] ?? envVal('DB_USERNAME', 'root')) ?>" required>
                </div>
                <div class="field">
                    <label>DB Password</label>
                    <input type="password" name="db_password" value="<?= htmlspecialchars($_POST['db_password'] ?? envVal('DB_PASSWORD', '')) ?>" placeholder="(leave blank if none)">
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:4px">
                <button type="submit" class="btn btn-primary">Test & Save →</button>
                <a href="install-app.php?step=1" class="btn btn-gray">← Back</a>
            </div>
        </form>
    </div>

<?php elseif ($step === 3): ?>
    <div class="card">
        <h2>Run Installation</h2>

        <dl class="info-grid">
            <dt>App Name</dt><dd><?= htmlspecialchars(envVal('APP_NAME')) ?></dd>
            <dt>App URL</dt><dd><?= htmlspecialchars(envVal('APP_URL')) ?></dd>
            <dt>Database</dt><dd><?= htmlspecialchars(envVal('DB_HOST')) ?>:<?= htmlspecialchars(envVal('DB_PORT')) ?> / <?= htmlspecialchars(envVal('DB_DATABASE')) ?></dd>
            <dt>DB User</dt><dd><?= htmlspecialchars(envVal('DB_USERNAME')) ?></dd>
        </dl>

        <div class="warn-box" style="margin-top:16px">
            <strong>This will run:</strong> <code>key:generate</code>, <code>migrate --force</code>, <code>config:cache</code>, <code>route:cache</code>
        </div>

        <form method="POST" action="install-app.php?step=3" style="margin-top:20px;display:flex;gap:10px">
            <input type="hidden" name="action" value="run_install">
            <button type="submit" class="btn btn-primary">Run Install →</button>
            <a href="install-app.php?step=2" class="btn btn-gray">← Back</a>
        </form>
    </div>

<?php elseif ($step === 4): ?>
    <?php
    $log = $_SESSION['install_log'] ?? [];
    $installOk = $_SESSION['install_ok'] ?? false;
    ?>
    <div class="card">
        <h2><?= $installOk ? '✅ Installation Complete' : '⚠️ Installation finished with errors' ?></h2>

        <div class="log-block">
<?php foreach ($log as $entry): ?>
<div class="log-entry">
<div class="log-cmd"># <?= htmlspecialchars($entry['cmd']) ?> <span class="<?= $entry['ok'] ? 'log-ok' : 'log-fail' ?>">[<?= $entry['ok'] ? 'OK' : 'FAIL' ?>]</span></div>
<div><?= htmlspecialchars($entry['out']) ?></div>
</div>
<?php endforeach; ?>
        </div>

        <?php if ($installOk): ?>
        <div style="margin-top:20px">
            <p style="font-size:13px;color:#374151;margin-bottom:14px">
                Installation succeeded. <strong>Delete this installer file</strong> before going live — it exposes sensitive configuration controls.
            </p>
            <form method="POST" action="install-app.php?step=4" style="display:inline">
                <input type="hidden" name="action" value="delete_installer">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Delete install-app.php and go to login?')">Delete installer & open app →</button>
            </form>
            <a href="<?= htmlspecialchars(envVal('APP_URL', 'http://localhost/goldapp')) ?>/login" class="btn btn-gray" style="margin-left:10px">Open app (keep installer)</a>
        </div>
        <?php else: ?>
        <div class="warn-box" style="margin-top:16px">
            Some steps failed. Review the output above. You can re-run by going back to Step 3.
        </div>
        <div style="margin-top:14px;display:flex;gap:10px">
            <a href="install-app.php?step=3" class="btn btn-primary">← Retry</a>
            <a href="<?= htmlspecialchars(envVal('APP_URL', 'http://localhost/goldapp')) ?>/login" class="btn btn-gray">Open app anyway</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="warn-box">
        <strong>Security reminder:</strong> Delete <code>install-app.php</code> from the server after setup. Anyone with web access can use it to overwrite your database settings.
    </div>

<?php endif; ?>
</div>
</body>
</html>
