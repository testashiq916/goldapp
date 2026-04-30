<?php
declare(strict_types=1);

set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');
mysqli_report(MYSQLI_REPORT_OFF);

const BIGDUMP_DIR = __DIR__;
const BIGDUMP_STATE_FILE = BIGDUMP_DIR . DIRECTORY_SEPARATOR . '.bigdump.state.json';
const BIGDUMP_MAX_QUERIES = 500;
const BIGDUMP_MAX_SECONDS_PER_PASS = 4.0;
const BIGDUMP_REFRESH_DELAY_MS = 100;
const BIGDUMP_AUTO_FILE = '';
const BIGDUMP_SKIP_DUPLICATES = true;
const BIGDUMP_TRUNCATE_BEFORE_IMPORT = true;

$envConfig = loadEnvConfig(BIGDUMP_DIR . DIRECTORY_SEPARATOR . '.env');
$defaults = [
    'db_host' => $envConfig['DB_HOST'] ?? '127.0.0.1',
    'db_port' => (string) ($envConfig['DB_PORT'] ?? '3306'),
    'db_name' => $envConfig['DB_DATABASE'] ?? '',
    'db_user' => $envConfig['DB_USERNAME'] ?? 'root',
    'db_pass' => $envConfig['DB_PASSWORD'] ?? '',
    'db_charset' => $envConfig['DB_CHARSET'] ?? 'utf8mb4',
];

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = null;
$error = null;
$state = loadState();
if ($state !== null && isset($state['config']['dump_file'])) {
    $_GET['file'] = (string) $state['config']['dump_file'];
}

if ($action === 'reset') {
    deleteState();
    $state = null;
    $message = 'Import progress reset.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
    [$message, $error] = handleUpload();
}

if ($action === 'start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dumpFiles = findDumpFiles(BIGDUMP_DIR);
    $input = [
        'db_host' => trim((string) ($_POST['db_host'] ?? $defaults['db_host'])),
        'db_port' => trim((string) ($_POST['db_port'] ?? $defaults['db_port'])),
        'db_name' => trim((string) ($_POST['db_name'] ?? $defaults['db_name'])),
        'db_user' => trim((string) ($_POST['db_user'] ?? $defaults['db_user'])),
        'db_pass' => (string) ($_POST['db_pass'] ?? $defaults['db_pass']),
        'db_charset' => trim((string) ($_POST['db_charset'] ?? $defaults['db_charset'])),
        'dump_file' => basename((string) ($_POST['dump_file'] ?? resolveAutoDumpFile($dumpFiles))),
        'skip_duplicates' => !empty($_POST['skip_duplicates']),
        'truncate_before_import' => !empty($_POST['truncate_before_import']),
    ];

    $validationError = validateStartInput($input);
    if ($validationError !== null) {
        $error = $validationError;
    } else {
        saveState([
            'config' => $input,
            'offset' => 0,
            'line' => 0,
            'queries_executed' => 0,
            'duplicate_rows_skipped' => 0,
            'tables_truncated' => false,
            'started_at' => date(DATE_ATOM),
            'last_error' => null,
            'completed' => false,
        ]);
        header('Location: ' . scriptUrl(['action' => 'run']));
        exit;
    }
}

if ($action === 'run') {
    $state = loadState();
    if ($state === null) {
        $error = 'No import session found. Start a new import.';
    } else {
        [$state, $message, $error] = runImportChunk($state);
    }
}

$state = loadState();
$dumpFiles = findDumpFiles(BIGDUMP_DIR);
$autoDumpFile = resolveAutoDumpFile($dumpFiles);

renderPage([
    'defaults' => $defaults,
    'dumpFiles' => $dumpFiles,
    'autoDumpFile' => $autoDumpFile,
    'message' => $message,
    'error' => $error,
    'state' => $state,
]);

function loadEnvConfig(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $data = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($value !== '' && (
            ($value[0] === '"' && str_ends_with($value, '"')) ||
            ($value[0] === "'" && str_ends_with($value, "'"))
        )) {
            $value = substr($value, 1, -1);
        }

        $data[$key] = $value;
    }

    return $data;
}

function loadState(): ?array
{
    if (!is_file(BIGDUMP_STATE_FILE)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents(BIGDUMP_STATE_FILE), true);

    return is_array($decoded) ? $decoded : null;
}

function saveState(array $state): void
{
    file_put_contents(BIGDUMP_STATE_FILE, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function deleteState(): void
{
    if (is_file(BIGDUMP_STATE_FILE)) {
        unlink(BIGDUMP_STATE_FILE);
    }
}

function handleUpload(): array
{
    if (!isset($_FILES['dump_upload'])) {
        return [null, 'No file upload received.'];
    }

    $file = $_FILES['dump_upload'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [null, 'Upload failed. Increase PHP upload limits if the file is large.'];
    }

    $originalName = basename((string) ($file['name'] ?? ''));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['sql', 'gz'], true)) {
        return [null, 'Only .sql and .gz dump files are supported.'];
    }

    $target = BIGDUMP_DIR . DIRECTORY_SEPARATOR . $originalName;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return [null, 'Unable to move the uploaded file into the project root.'];
    }

    return ["Uploaded {$originalName}.", null];
}

function validateStartInput(array $input): ?string
{
    foreach (['db_host', 'db_port', 'db_name', 'db_user', 'db_charset', 'dump_file'] as $key) {
        if ($input[$key] === '') {
            return 'All connection fields and a dump file are required.';
        }
    }

    $path = BIGDUMP_DIR . DIRECTORY_SEPARATOR . $input['dump_file'];
    if (!is_file($path)) {
        return 'Selected dump file was not found.';
    }

    return null;
}

function findDumpFiles(string $dir): array
{
    $files = [];
    foreach (scandir($dir) ?: [] as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            continue;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['sql', 'gz'], true)) {
            $files[] = $file;
        }
    }

    natcasesort($files);

    return array_values($files);
}

function resolveAutoDumpFile(array $dumpFiles): string
{
    $requested = basename((string) ($_GET['file'] ?? $_POST['dump_file'] ?? ''));
    if ($requested !== '' && in_array($requested, $dumpFiles, true)) {
        return $requested;
    }

    if (BIGDUMP_AUTO_FILE !== '' && in_array(BIGDUMP_AUTO_FILE, $dumpFiles, true)) {
        return BIGDUMP_AUTO_FILE;
    }

    foreach ($dumpFiles as $file) {
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'sql') {
            return $file;
        }
    }

    return $dumpFiles[0] ?? '';
}

function runImportChunk(array $state): array
{
    $config = $state['config'];
    $filePath = BIGDUMP_DIR . DIRECTORY_SEPARATOR . $config['dump_file'];

    if (!is_file($filePath)) {
        $state['last_error'] = 'Dump file is missing.';
        saveState($state);

        return [$state, null, 'Dump file is missing.'];
    }

    try {
        $mysqli = @new mysqli(
            $config['db_host'],
            $config['db_user'],
            $config['db_pass'],
            $config['db_name'],
            (int) $config['db_port']
        );
    } catch (Throwable $exception) {
        $state['last_error'] = 'Database connection failed: ' . $exception->getMessage();
        saveState($state);

        return [$state, null, $state['last_error']];
    }

    if ($mysqli->connect_error) {
        $state['last_error'] = 'Database connection failed: ' . $mysqli->connect_error;
        saveState($state);

        return [$state, null, $state['last_error']];
    }

    $mysqli->set_charset($config['db_charset']);

    if (!empty($config['truncate_before_import']) && empty($state['tables_truncated'])) {
        $truncateError = truncateAllTables($mysqli, $config['db_name']);
        if ($truncateError !== null) {
            $state['last_error'] = $truncateError;
            saveState($state);
            $mysqli->close();

            return [$state, null, $truncateError];
        }

        $state['tables_truncated'] = true;
        saveState($state);
    }

    $isGzip = strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'gz';
    $handle = openDumpHandle($filePath);
    if ($handle === null) {
        return [$state, null, 'Unable to open the dump file.'];
    }

    if (!seekDumpHandle($handle, $state['offset'], $isGzip)) {
        closeDumpHandle($handle, $isGzip);

        return [$state, null, 'Unable to seek to the saved import position.'];
    }

    $queriesThisPass = 0;
    $passStartedAt = microtime(true);
    $statement = '';
    $inString = false;
    $stringChar = '';
    $lineNumber = (int) $state['line'];

    while (
        $queriesThisPass < BIGDUMP_MAX_QUERIES
        && (microtime(true) - $passStartedAt) < BIGDUMP_MAX_SECONDS_PER_PASS
        && ($line = readDumpLine($handle, $isGzip)) !== false
    ) {
        $lineNumber++;
        if ($statement === '' && isIgnorableSqlLine($line)) {
            $state['offset'] = tellDumpHandle($handle, $isGzip);
            $state['line'] = $lineNumber;
            continue;
        }

        $statement .= $line;
        if (!lineCompletesStatement($line, $inString, $stringChar)) {
            $state['offset'] = tellDumpHandle($handle, $isGzip);
            $state['line'] = $lineNumber;
            continue;
        }

        $sql = trim($statement);
        $statement = '';

        if ($sql === '') {
            $state['offset'] = tellDumpHandle($handle, $isGzip);
            $state['line'] = $lineNumber;
            continue;
        }

        try {
            $queryResult = $mysqli->query($sql);
        } catch (Throwable $exception) {
            $queryResult = false;
            $state['last_error'] = sprintf(
                'SQL error on line %d: %s',
                $lineNumber,
                $exception->getMessage()
            );
        }

        if ($queryResult === false) {
            $mysqlErrorCode = $mysqli->errno;
            if (!empty($config['skip_duplicates']) && $mysqlErrorCode === 1062) {
                $state['duplicate_rows_skipped'] = (int) ($state['duplicate_rows_skipped'] ?? 0) + 1;
                $state['offset'] = tellDumpHandle($handle, $isGzip);
                $state['line'] = $lineNumber;
                $state['last_error'] = null;
                continue;
            }

            if ($state['last_error'] === null) {
                $state['last_error'] = sprintf(
                    'SQL error on line %d: %s',
                    $lineNumber,
                    $mysqli->error !== '' ? $mysqli->error : 'Unknown database error'
                );
            }
            $state['last_error'] = sprintf(
                '%s',
                $state['last_error']
            );
            $state['offset'] = tellDumpHandle($handle, $isGzip);
            $state['line'] = $lineNumber;
            saveState($state);
            closeDumpHandle($handle, $isGzip);
            $mysqli->close();

            return [$state, null, $state['last_error']];
        }

        $queriesThisPass++;
        $state['queries_executed'] = (int) $state['queries_executed'] + 1;
        $state['offset'] = tellDumpHandle($handle, $isGzip);
        $state['line'] = $lineNumber;
    }

    $finished = isDumpEof($handle, $isGzip);
    closeDumpHandle($handle, $isGzip);
    $mysqli->close();

    if ($finished) {
        $state['completed'] = true;
        $state['completed_at'] = date(DATE_ATOM);
        $state['last_error'] = null;
        saveState($state);

        return [$state, 'Import completed successfully.', null];
    }

    $state['last_error'] = null;
    saveState($state);

    return [$state, "Imported {$queriesThisPass} queries. Continuing...", null];
}

function truncateAllTables(mysqli $mysqli, string $databaseName): ?string
{
    $databaseName = $mysqli->real_escape_string($databaseName);
    $result = $mysqli->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$databaseName}' AND TABLE_TYPE = 'BASE TABLE'"
    );

    if ($result === false) {
        return 'Unable to load table list for truncation: ' . $mysqli->error;
    }

    $tables = [];
    while ($row = $result->fetch_assoc()) {
        $tables[] = $row['TABLE_NAME'];
    }
    $result->free();

    if ($tables === []) {
        return null;
    }

    if (!$mysqli->query('SET FOREIGN_KEY_CHECKS=0')) {
        return 'Unable to disable foreign key checks before truncation: ' . $mysqli->error;
    }

    foreach ($tables as $table) {
        $tableName = str_replace('`', '``', $table);
        if (!$mysqli->query("TRUNCATE TABLE `{$tableName}`")) {
            $mysqli->query('SET FOREIGN_KEY_CHECKS=1');

            return "Unable to truncate table {$table}: " . $mysqli->error;
        }
    }

    if (!$mysqli->query('SET FOREIGN_KEY_CHECKS=1')) {
        return 'Unable to re-enable foreign key checks after truncation: ' . $mysqli->error;
    }

    return null;
}

function openDumpHandle(string $path)
{
    return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'gz'
        ? gzopen($path, 'rb')
        : fopen($path, 'rb');
}

function closeDumpHandle($handle, bool $isGzip): void
{
    if (is_resource($handle)) {
        if ($isGzip) {
            gzclose($handle);
        } else {
            fclose($handle);
        }
    }
}

function readDumpLine($handle, bool $isGzip)
{
    return $isGzip
        ? gzgets($handle)
        : fgets($handle);
}

function tellDumpHandle($handle, bool $isGzip): int
{
    return $isGzip
        ? gztell($handle)
        : ftell($handle);
}

function seekDumpHandle($handle, int $offset, bool $isGzip): bool
{
    if ($offset <= 0) {
        return true;
    }

    return $isGzip
        ? gzseek($handle, $offset) === 0
        : fseek($handle, $offset) === 0;
}

function isDumpEof($handle, bool $isGzip): bool
{
    return $isGzip ? gzeof($handle) : feof($handle);
}

function isIgnorableSqlLine(string $line): bool
{
    $trimmed = ltrim($line);

    return $trimmed === ''
        || str_starts_with($trimmed, '--')
        || str_starts_with($trimmed, '#')
        || str_starts_with($trimmed, '/*')
        || str_starts_with($trimmed, '*/');
}

function lineCompletesStatement(string $line, bool &$inString, string &$stringChar): bool
{
    $length = strlen($line);
    for ($i = 0; $i < $length; $i++) {
        $char = $line[$i];
        $next = $i + 1 < $length ? $line[$i + 1] : '';

        if ($inString) {
            if ($char === $stringChar && !isEscapedByBackslashes($line, $i)) {
                if ($next === $stringChar) {
                    $i++;
                    continue;
                }
                $inString = false;
                $stringChar = '';
            }
            continue;
        }

        if ($char === '"' || $char === "'") {
            $inString = true;
            $stringChar = $char;
            continue;
        }

        if ($char === ';') {
            return true;
        }
    }

    return false;
}

function isEscapedByBackslashes(string $line, int $position): bool
{
    $backslashes = 0;
    for ($i = $position - 1; $i >= 0 && $line[$i] === '\\'; $i--) {
        $backslashes++;
    }

    return ($backslashes % 2) === 1;
}

function scriptUrl(array $params = []): string
{
    $base = strtok($_SERVER['REQUEST_URI'] ?? '/bigdump.php', '?');
    if ($params === []) {
        return $base;
    }

    return $base . '?' . http_build_query($params);
}

function renderPage(array $data): void
{
    $defaults = $data['defaults'];
    $dumpFiles = $data['dumpFiles'];
    $autoDumpFile = $data['autoDumpFile'];
    $message = $data['message'];
    $error = $data['error'];
    $state = $data['state'];
    $current = $state['config'] ?? $defaults;
    $selectedDump = $current['dump_file'] ?? $autoDumpFile;
    $shouldAutoRefresh = ($_GET['action'] ?? '') === 'run' && $error === null && $state !== null && empty($state['completed']);

    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>BigDump Importer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($shouldAutoRefresh): ?>
    <meta http-equiv="refresh" content="<?php echo BIGDUMP_REFRESH_DELAY_MS / 1000; ?>;url=<?php echo htmlspecialchars(scriptUrl(['action' => 'run']), ENT_QUOTES); ?>">
    <?php endif; ?>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6f8; color: #1f2937; margin: 0; }
        .wrap { max-width: 960px; margin: 32px auto; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        h1 { margin-top: 0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        label { display: block; font-size: 14px; font-weight: 700; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; }
        button { background: #111827; color: #fff; border: 0; border-radius: 8px; padding: 10px 16px; cursor: pointer; }
        button.secondary { background: #fff; color: #111827; border: 1px solid #d1d5db; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
        .panel { border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin-top: 18px; }
        .msg { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; }
        .ok { background: #ecfdf5; color: #065f46; }
        .err { background: #fef2f2; color: #991b1b; }
        .meta { color: #4b5563; font-size: 14px; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>BigDump SQL Importer</h1>
        <p class="meta">Standalone importer for large <code>.sql</code> and <code>.gz</code> dumps. Defaults are loaded from this project's <code>.env</code>.</p>

        <?php if ($message !== null): ?>
            <div class="msg ok"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
        <?php endif; ?>

        <?php if ($error !== null): ?>
            <div class="msg err"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
        <?php endif; ?>

        <div class="panel">
            <h2>Start Import</h2>
            <form method="post" action="<?php echo htmlspecialchars(scriptUrl(['action' => 'start']), ENT_QUOTES); ?>">
                <input type="hidden" name="dump_file" value="<?php echo htmlspecialchars($selectedDump, ENT_QUOTES); ?>">
                <input type="hidden" name="skip_duplicates" value="<?php echo BIGDUMP_SKIP_DUPLICATES ? '1' : '0'; ?>">
                <input type="hidden" name="truncate_before_import" value="<?php echo BIGDUMP_TRUNCATE_BEFORE_IMPORT ? '1' : '0'; ?>">
                <div class="grid">
                    <div>
                        <label for="db_host">DB Host</label>
                        <input id="db_host" name="db_host" value="<?php echo htmlspecialchars((string) $current['db_host'], ENT_QUOTES); ?>">
                    </div>
                    <div>
                        <label for="db_port">DB Port</label>
                        <input id="db_port" name="db_port" value="<?php echo htmlspecialchars((string) $current['db_port'], ENT_QUOTES); ?>">
                    </div>
                    <div>
                        <label for="db_name">DB Name</label>
                        <input id="db_name" name="db_name" value="<?php echo htmlspecialchars((string) $current['db_name'], ENT_QUOTES); ?>">
                    </div>
                    <div>
                        <label for="db_user">DB User</label>
                        <input id="db_user" name="db_user" value="<?php echo htmlspecialchars((string) $current['db_user'], ENT_QUOTES); ?>">
                    </div>
                    <div>
                        <label for="db_pass">DB Password</label>
                        <input id="db_pass" name="db_pass" type="password" value="<?php echo htmlspecialchars((string) $current['db_pass'], ENT_QUOTES); ?>">
                    </div>
                    <div>
                        <label for="db_charset">DB Charset</label>
                        <input id="db_charset" name="db_charset" value="<?php echo htmlspecialchars((string) $current['db_charset'], ENT_QUOTES); ?>">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label>Dump File</label>
                        <input value="<?php echo htmlspecialchars($selectedDump !== '' ? $selectedDump : 'No .sql/.gz file found', ENT_QUOTES); ?>" readonly>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit">Start / Resume Import</button>
                    <button type="button" class="secondary" onclick="window.location.href='<?php echo htmlspecialchars(scriptUrl(['action' => 'reset']), ENT_QUOTES); ?>'">Reset Progress</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2>Upload Dump</h2>
            <form method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars(scriptUrl(), ENT_QUOTES); ?>">
                <input type="hidden" name="action" value="upload">
                <div class="grid">
                    <div style="grid-column: 1 / -1;">
                        <label for="dump_upload">Choose File</label>
                        <input id="dump_upload" type="file" name="dump_upload" accept=".sql,.gz">
                    </div>
                </div>
                <div class="actions">
                    <button type="submit">Upload</button>
                </div>
            </form>
        </div>

        <?php if ($state !== null): ?>
            <div class="panel">
                <h2>Current Session</h2>
                <p class="meta">File: <code><?php echo htmlspecialchars((string) $state['config']['dump_file'], ENT_QUOTES); ?></code></p>
                <p class="meta">Database: <code><?php echo htmlspecialchars((string) $state['config']['db_name'], ENT_QUOTES); ?></code> on <code><?php echo htmlspecialchars((string) $state['config']['db_host'], ENT_QUOTES); ?>:<?php echo htmlspecialchars((string) $state['config']['db_port'], ENT_QUOTES); ?></code></p>
                <p class="meta">Line: <?php echo (int) ($state['line'] ?? 0); ?> | Offset: <?php echo (int) ($state['offset'] ?? 0); ?> | Queries executed: <?php echo (int) ($state['queries_executed'] ?? 0); ?></p>
                <p class="meta">Duplicates skipped: <?php echo (int) ($state['duplicate_rows_skipped'] ?? 0); ?> | Tables truncated: <?php echo !empty($state['tables_truncated']) ? 'yes' : 'no'; ?></p>
                <?php if (!empty($state['started_at'])): ?>
                    <p class="meta">Started: <?php echo htmlspecialchars((string) $state['started_at'], ENT_QUOTES); ?></p>
                <?php endif; ?>
                <?php if (!empty($state['completed'])): ?>
                    <p class="meta">Completed: <?php echo htmlspecialchars((string) ($state['completed_at'] ?? ''), ENT_QUOTES); ?></p>
                <?php elseif ($error === null): ?>
                    <div class="actions">
                        <button type="button" onclick="window.location.href='<?php echo htmlspecialchars(scriptUrl(['action' => 'run']), ENT_QUOTES); ?>'">Process Next Chunk</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
    <?php
}
