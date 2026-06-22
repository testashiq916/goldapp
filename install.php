<?php
/**
 * GoldApp browser installer.
 *
 * Upload this file to the Laravel project root, open it in the browser, and
 * delete it immediately after setup.
 */

declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('ENV_FILE', BASE_PATH . '/.env');
define('ENV_EXAMPLE', BASE_PATH . '/.env.example');
define('ARTISAN', BASE_PATH . '/artisan');

session_start();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function env_value(string $key, string $default = ''): string
{
    if (!is_file(ENV_FILE)) {
        return $default;
    }

    $content = (string) file_get_contents(ENV_FILE);
    if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $content, $match)) {
        return trim($match[1], " \t\r\n\"'");
    }

    return $default;
}

function quote_env_value(string $value): string
{
    if ($value === '') {
        return '';
    }

    return preg_match('/\s|#|"|\'/', $value)
        ? '"' . str_replace('"', '\"', $value) . '"'
        : $value;
}

function write_env(array $values): bool
{
    if (is_file(ENV_FILE)) {
        $content = (string) file_get_contents(ENV_FILE);
    } elseif (is_file(ENV_EXAMPLE)) {
        $content = (string) file_get_contents(ENV_EXAMPLE);
    } else {
        $content = '';
    }

    foreach ($values as $key => $value) {
        $line = $key . '=' . quote_env_value((string) $value);
        if (preg_match('/^' . preg_quote($key, '/') . '=/m', $content)) {
            $content = (string) preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $content);
        } else {
            $content = rtrim($content) . PHP_EOL . $line . PHP_EOL;
        }
    }

    return file_put_contents(ENV_FILE, $content) !== false;
}

function command_exists(string $command): bool
{
    $probe = stripos(PHP_OS_FAMILY, 'Windows') === 0
        ? 'where ' . escapeshellarg($command)
        : 'command -v ' . escapeshellarg($command);

    exec($probe . ' 2>&1', $output, $code);

    return $code === 0 && $output !== [];
}

function run_command(string $command): array
{
    $cwd = getcwd();
    chdir(BASE_PATH);
    exec($command . ' 2>&1', $output, $code);
    if ($cwd !== false) {
        chdir($cwd);
    }

    return [
        'code' => $code,
        'output' => implode(PHP_EOL, $output),
    ];
}

function run_artisan(string $arguments): array
{
    return run_command(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ARTISAN) . ' ' . $arguments);
}

function composer_command(): ?string
{
    if (is_file(BASE_PATH . '/composer.phar')) {
        return escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BASE_PATH . '/composer.phar');
    }

    if (command_exists('composer')) {
        return 'composer';
    }

    return null;
}

function download_composer_phar(): bool
{
    $target = BASE_PATH . '/composer.phar';
    $url = 'https://getcomposer.org/composer-stable.phar';

    if (is_file($target)) {
        return true;
    }

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        $file = fopen($target, 'wb');
        if ($curl !== false && $file !== false) {
            curl_setopt_array($curl, [
                CURLOPT_FILE => $file,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => true,
                CURLOPT_TIMEOUT => 120,
            ]);
            $ok = curl_exec($curl) === true;
            curl_close($curl);
            fclose($file);
            if ($ok && filesize($target) > 0) {
                return true;
            }
        }
        @unlink($target);
    }

    $contents = @file_get_contents($url);
    if ($contents !== false && $contents !== '') {
        return file_put_contents($target, $contents) !== false;
    }

    return false;
}

function run_composer_install(): array
{
    $composer = composer_command();

    if ($composer === null) {
        if (!download_composer_phar()) {
            return [
                'code' => 1,
                'output' => 'Composer was not found and composer.phar could not be downloaded. Upload composer.phar to the project root or run composer install manually.',
            ];
        }

        $composer = composer_command();
        if ($composer === null) {
            return [
                'code' => 1,
                'output' => 'composer.phar was downloaded but could not be executed.',
            ];
        }
    }

    $prefix = stripos(PHP_OS_FAMILY, 'Windows') === 0
        ? 'set COMPOSER_HOME=%TEMP% && '
        : 'COMPOSER_HOME=/tmp HOME=/tmp ';

    return run_command($prefix . $composer . ' install --no-dev --optimize-autoloader --no-interaction');
}

function test_database(string $host, int $port, string $database, string $username, string $password): ?string
{
    if (!extension_loaded('pdo_mysql')) {
        return 'The pdo_mysql PHP extension is missing.';
    }

    try {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $database) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return null;
}

function requirements(): array
{
    $checks = [
        ['PHP >= 8.2', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION],
        ['artisan exists', is_file(ARTISAN), is_file(ARTISAN) ? 'found' : 'missing'],
        ['composer.json exists', is_file(BASE_PATH . '/composer.json'), is_file(BASE_PATH . '/composer.json') ? 'found' : 'missing'],
    ];

    foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml'] as $extension) {
        $checks[] = ['ext-' . $extension, extension_loaded($extension), extension_loaded($extension) ? 'loaded' : 'missing'];
    }

    foreach (['storage', 'bootstrap/cache'] as $path) {
        $fullPath = BASE_PATH . '/' . $path;
        $checks[] = [$path . ' writable', is_dir($fullPath) && is_writable($fullPath), is_writable($fullPath) ? 'writable' : 'not writable'];
    }

    $envWritable = (is_file(ENV_FILE) && is_writable(ENV_FILE)) || (!is_file(ENV_FILE) && is_writable(BASE_PATH));
    $checks[] = ['.env writable', $envWritable, $envWritable ? 'writable' : 'not writable'];

    return $checks;
}

function all_ok(array $checks): bool
{
    foreach ($checks as $check) {
        if (!$check[1]) {
            return false;
        }
    }

    return true;
}

$step = (int) ($_GET['step'] ?? $_SESSION['step'] ?? 1);
$errors = [];
$log = $_SESSION['install_log'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_config') {
        $appName = trim((string) ($_POST['app_name'] ?? 'GoldApp'));
        $appUrl = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');
        $host = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
        $port = (int) ($_POST['db_port'] ?? 3306);
        $database = trim((string) ($_POST['db_database'] ?? ''));
        $username = trim((string) ($_POST['db_username'] ?? 'root'));
        $password = (string) ($_POST['db_password'] ?? '');

        if ($appName === '') {
            $errors[] = 'App name is required.';
        }
        if ($appUrl === '') {
            $errors[] = 'App URL is required.';
        }
        if ($database === '') {
            $errors[] = 'Database name is required.';
        }

        if ($errors === []) {
            $dbError = test_database($host, $port, $database, $username, $password);
            if ($dbError !== null) {
                $errors[] = 'Database connection failed: ' . $dbError;
            }
        }

        if ($errors === [] && write_env([
            'APP_NAME' => $appName,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $host,
            'DB_PORT' => (string) $port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
        ])) {
            $_SESSION['step'] = 3;
            header('Location: install.php?step=3');
            exit;
        }

        if ($errors === []) {
            $errors[] = 'Could not write the .env file.';
        }

        $step = 2;
    }

    if ($action === 'run_install') {
        $log = [];

        $composerResult = run_composer_install();
        $log[] = ['title' => 'composer install', 'ok' => $composerResult['code'] === 0, 'output' => $composerResult['output']];

        if ($composerResult['code'] !== 0) {
            $log[] = ['title' => 'artisan commands skipped', 'ok' => false, 'output' => 'Composer install failed, so Laravel cannot boot safely yet. Fix Composer/vendor first, then run the installer again.'];
        } elseif (is_file(BASE_PATH . '/vendor/autoload.php')) {
            foreach (['config:clear', 'cache:clear', 'route:clear', 'view:clear'] as $command) {
                $result = run_artisan($command);
                $log[] = ['title' => 'php artisan ' . $command, 'ok' => $result['code'] === 0, 'output' => $result['output']];
            }

            if (env_value('APP_KEY') === '') {
                $result = run_artisan('key:generate --force');
                $log[] = ['title' => 'php artisan key:generate --force', 'ok' => $result['code'] === 0, 'output' => $result['output']];
            }

            $result = run_artisan('migrate --force');
            $log[] = ['title' => 'php artisan migrate --force', 'ok' => $result['code'] === 0, 'output' => $result['output']];

            $result = run_artisan('storage:link');
            $log[] = ['title' => 'php artisan storage:link', 'ok' => $result['code'] === 0, 'output' => $result['output']];
        } else {
            $log[] = ['title' => 'vendor/autoload.php check', 'ok' => false, 'output' => 'vendor/autoload.php is missing. Composer install did not complete.'];
        }

        $_SESSION['install_log'] = $log;
        $_SESSION['install_ok'] = all_ok(array_map(static fn ($entry) => [$entry['title'], $entry['ok'], ''], $log));
        $_SESSION['step'] = 4;
        header('Location: install.php?step=4');
        exit;
    }

    if ($action === 'delete_installer') {
        @unlink(__FILE__);
        $_SESSION = [];
        session_destroy();
        header('Location: ' . env_value('APP_URL', '/') . '/login');
        exit;
    }
}

$checks = requirements();
$requirementsOk = all_ok($checks);
$artisanMissing = !is_file(ARTISAN);
$installOk = (bool) ($_SESSION['install_ok'] ?? false);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GoldApp Installer</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:#172033;font-family:Arial,sans-serif;padding:32px 16px}.wrap{max-width:760px;margin:0 auto}h1{margin:0 0 6px;font-size:26px}.sub{margin:0 0 24px;color:#667085}.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:18px}.step{border-bottom:4px solid #d0d5dd;color:#667085;padding:10px 4px;text-align:center;font-size:13px}.step.active{border-color:#2563eb;color:#1d4ed8;font-weight:700}.step.done{border-color:#16a34a;color:#15803d}.card{background:#fff;border:1px solid #d0d5dd;border-radius:8px;padding:24px;margin-bottom:16px;box-shadow:0 6px 20px rgba(16,24,40,.06)}h2{font-size:18px;margin:0 0 16px}.checks{width:100%;border-collapse:collapse}.checks td{padding:9px 6px;border-bottom:1px solid #edf0f5}.checks td:last-child{text-align:right;font-weight:700}.ok{color:#15803d}.bad{color:#b42318}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{margin-bottom:14px}.field label{display:block;font-weight:700;font-size:13px;margin-bottom:6px}.field input{width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:10px;font-size:14px}.btn{display:inline-block;border:0;border-radius:6px;padding:10px 16px;font-weight:700;font-size:14px;text-decoration:none;cursor:pointer}.primary{background:#2563eb;color:#fff}.secondary{background:#e5e7eb;color:#344054}.danger{background:#dc2626;color:#fff}.errors{background:#fef3f2;border:1px solid #fda29b;color:#b42318;border-radius:6px;padding:12px;margin-bottom:14px}.notice{background:#fffbeb;border:1px solid #fbbf24;color:#92400e;border-radius:6px;padding:12px;margin-top:14px}.log{background:#101828;color:#d1fadf;border-radius:6px;padding:14px;white-space:pre-wrap;overflow:auto;max-height:360px;font-family:Consolas,monospace;font-size:12px}.log-title{color:#93c5fd;font-weight:700;margin-top:12px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}@media(max-width:640px){.steps,.grid{grid-template-columns:1fr}.step{text-align:left}}
</style>
</head>
<body>
<main class="wrap">
    <h1>GoldApp Installer</h1>
    <p class="sub">Use once for dependency repair, environment setup, database migration, and cache cleanup.</p>

    <div class="steps">
        <div class="step <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">1. Check</div>
        <div class="step <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">2. Config</div>
        <div class="step <?= $step === 3 ? 'active' : ($step > 3 ? 'done' : '') ?>">3. Install</div>
        <div class="step <?= $step === 4 ? 'active' : '' ?>">4. Done</div>
    </div>

    <?php if ($step === 1): ?>
        <section class="card">
            <h2>Server Check</h2>
            <table class="checks">
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <td><?= h($check[0]) ?></td>
                        <td class="<?= $check[1] ? 'ok' : 'bad' ?>"><?= h((string) $check[2]) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php if ($requirementsOk): ?>
                <div class="actions"><a class="btn primary" href="install.php?step=2">Continue</a></div>
            <?php else: ?>
                <div class="notice">Fix the failed checks before continuing. PHP 8.2+ and the listed extensions are required for this Laravel app.</div>
                <?php if ($artisanMissing): ?>
                    <div class="notice">
                        The file <strong>artisan</strong> is missing from this folder. Upload the full Laravel project root so
                        <strong>install.php</strong>, <strong>artisan</strong>, <strong>composer.json</strong>, <strong>app</strong>,
                        <strong>bootstrap</strong>, <strong>config</strong>, <strong>routes</strong>, and <strong>vendor</strong>
                        are together in the same directory.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php elseif ($step === 2): ?>
        <section class="card">
            <h2>App and Database</h2>
            <?php if ($errors !== []): ?>
                <div class="errors"><?php foreach ($errors as $error): ?><div><?= h($error) ?></div><?php endforeach; ?></div>
            <?php endif; ?>
            <form method="post" action="install.php?step=2">
                <input type="hidden" name="action" value="save_config">
                <div class="grid">
                    <div class="field">
                        <label for="app_name">App Name</label>
                        <input id="app_name" name="app_name" value="<?= h($_POST['app_name'] ?? env_value('APP_NAME', 'GoldApp')) ?>" required>
                    </div>
                    <div class="field">
                        <label for="app_url">App URL</label>
                        <input id="app_url" name="app_url" value="<?= h($_POST['app_url'] ?? env_value('APP_URL', 'http://localhost/goldapp')) ?>" required>
                    </div>
                    <div class="field">
                        <label for="db_host">DB Host</label>
                        <input id="db_host" name="db_host" value="<?= h($_POST['db_host'] ?? env_value('DB_HOST', '127.0.0.1')) ?>" required>
                    </div>
                    <div class="field">
                        <label for="db_port">DB Port</label>
                        <input id="db_port" name="db_port" type="number" value="<?= h($_POST['db_port'] ?? env_value('DB_PORT', '3306')) ?>" required>
                    </div>
                    <div class="field">
                        <label for="db_database">Database Name</label>
                        <input id="db_database" name="db_database" value="<?= h($_POST['db_database'] ?? env_value('DB_DATABASE', 'goldapp')) ?>" required>
                    </div>
                    <div class="field">
                        <label for="db_username">DB Username</label>
                        <input id="db_username" name="db_username" value="<?= h($_POST['db_username'] ?? env_value('DB_USERNAME', 'root')) ?>" required>
                    </div>
                    <div class="field">
                        <label for="db_password">DB Password</label>
                        <input id="db_password" name="db_password" type="password" value="<?= h($_POST['db_password'] ?? env_value('DB_PASSWORD', '')) ?>">
                    </div>
                </div>
                <div class="actions">
                    <button class="btn primary" type="submit">Test and Save</button>
                    <a class="btn secondary" href="install.php?step=1">Back</a>
                </div>
            </form>
        </section>
    <?php elseif ($step === 3): ?>
        <section class="card">
            <h2>Run Installation</h2>
            <p>This will run Composer install, clear Laravel caches, generate APP_KEY if missing, run migrations, and create the storage link.</p>
            <div class="notice">Make sure the database details are correct. This step can modify the database.</div>
            <form method="post" action="install.php?step=3" class="actions">
                <input type="hidden" name="action" value="run_install">
                <button class="btn primary" type="submit">Run Install</button>
                <a class="btn secondary" href="install.php?step=2">Back</a>
            </form>
        </section>
    <?php elseif ($step === 4): ?>
        <section class="card">
            <h2><?= $installOk ? 'Installation Complete' : 'Installation Needs Attention' ?></h2>
            <div class="log"><?php foreach ($log as $entry): ?><div class="log-title"># <?= h($entry['title']) ?> [<?= $entry['ok'] ? 'OK' : 'FAIL' ?>]</div><?= h($entry['output'] ?: 'No output.') . PHP_EOL ?><?php endforeach; ?></div>
            <div class="actions">
                <a class="btn primary" href="install.php?step=3">Run Again</a>
                <a class="btn secondary" href="<?= h(env_value('APP_URL', '/')) ?>/login">Open App</a>
                <?php if ($installOk): ?>
                    <form method="post" action="install.php?step=4" onsubmit="return confirm('Delete install.php now?')">
                        <input type="hidden" name="action" value="delete_installer">
                        <button class="btn danger" type="submit">Delete Installer</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
        <div class="notice">Security: delete install.php from the server after use. It can change your app configuration and run setup commands.</div>
    <?php endif; ?>
</main>
</body>
</html>
