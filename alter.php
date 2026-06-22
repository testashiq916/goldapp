<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$schemaFile = 'C:\\Users\\Proaims-Design\\Downloads\\demo (1).sql';
$envFile = __DIR__ . DIRECTORY_SEPARATOR . '.env';

$message = '';
$error = '';

try {
    if (!is_file($schemaFile)) {
        throw new RuntimeException('SQL file not found: ' . $schemaFile);
    }

    $env = loadEnvFile($envFile);
    $pdo = connectFromEnv($env);
    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

    $schemaSql = file_get_contents($schemaFile);
    if ($schemaSql === false) {
        throw new RuntimeException('Unable to read SQL file.');
    }

    $source = parseDumpSchema($schemaSql);
    if (!$source['tables']) {
        throw new RuntimeException('No CREATE TABLE statements were found in the SQL file.');
    }

    $requestMethod = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($requestMethod === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        $table = (string) ($_POST['table'] ?? '');

        if ($action === 'create_table') {
            $message = createMissingTable($pdo, $source, $table);
        } elseif ($action === 'add_columns') {
            $message = addMissingColumnsForTable($pdo, $source, $table);
        } elseif ($action === 'add_indexes') {
            $message = addMissingIndexesForTable($pdo, $source, $table);
        } elseif ($action === 'apply_all') {
            $message = applyAllMissing($pdo, $source);
        } else {
            throw new InvalidArgumentException('Unknown action.');
        }
    }

    $comparison = compareSchema($pdo, $source);
} catch (Throwable $e) {
    $comparison = [
        'table_count' => 0,
        'tables' => [],
        'missing_tables' => [],
        'column_diffs' => [],
        'index_diffs' => [],
        'index_conflicts' => [],
    ];
    $dbName = '';
    $error = $e->getMessage();
}

renderPage($schemaFile, $dbName, $comparison, $message, $error);

function loadEnvFile(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('.env file not found: ' . $path);
    }

    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read .env file.');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        $values[trim($key)] = $value;
    }

    return $values;
}

function connectFromEnv(array $env): PDO
{
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3306';
    $database = $env['DB_DATABASE'] ?? '';
    $username = $env['DB_USERNAME'] ?? 'root';
    $password = $env['DB_PASSWORD'] ?? '';

    if ($database === '') {
        throw new RuntimeException('DB_DATABASE is empty in .env.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function renderPage(string $schemaFile, string $dbName, array $comparison, string $message, string $error): void
{
    header('Content-Type: text/html; charset=UTF-8');

    $missingTables = $comparison['missing_tables'];
    $columnDiffs = $comparison['column_diffs'];
    $indexDiffs = $comparison['index_diffs'];
    $indexConflicts = $comparison['index_conflicts'];
    $totalMissingColumns = array_sum(array_map('count', $columnDiffs));
    $totalMissingIndexes = array_sum(array_map('count', $indexDiffs));

    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Database Alter Helper</title>';
    echo '<style>
        body{font-family:Segoe UI,Tahoma,Arial,sans-serif;margin:0;background:#f4f7fb;color:#1f2937;font-size:14px}
        .wrap{max-width:1220px;margin:18px auto;padding:0 14px}
        h1{margin:0 0 8px;color:#163b63;font-size:24px}
        h2{font-size:18px;margin:0 0 12px;color:#214d78}
        .sub{color:#62758a;margin-bottom:14px;line-height:1.5}
        .panel{background:#fff;border:1px solid #d8e3f0;border-radius:8px;padding:14px;margin-bottom:12px;box-shadow:0 8px 22px rgba(22,59,99,.06)}
        .stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
        .pill{border:1px solid #cadaeb;background:#f8fbff;border-radius:999px;padding:7px 12px;font-weight:700;color:#254d75}
        .msg{padding:10px 12px;border-radius:6px;margin-bottom:12px;font-weight:700;white-space:pre-wrap}
        .ok{background:#e9f9ef;border:1px solid #9bd1ad;color:#176336}
        .err{background:#fff0f0;border:1px solid #e3a1a1;color:#9b2727}
        table{width:100%;border-collapse:collapse;background:#fff}
        th,td{border-bottom:1px solid #e7eef7;padding:9px 10px;text-align:left;vertical-align:top}
        th{background:#eef5fd;color:#214d78}
        code{background:#eef2f7;border:1px solid #dae3ee;border-radius:4px;padding:1px 5px}
        button{min-height:34px;border:1px solid #28669b;border-radius:6px;padding:0 12px;background:#e7f2ff;color:#17476f;font-weight:700;cursor:pointer}
        button.primary{background:#e7f8ed;border-color:#2b8149;color:#1b6433}
        button.warn{background:#fff8e1;border-color:#b7791f;color:#7a4a00}
        .actions{display:flex;gap:8px;flex-wrap:wrap}
        .chips{display:flex;gap:5px;flex-wrap:wrap}
        .chip{display:inline-block;background:#eef2f7;border:1px solid #dae3ee;border-radius:4px;padding:2px 6px}
        .empty{padding:22px;text-align:center;color:#64748b;border:1px dashed #cbd8e7;border-radius:8px;background:#fbfdff}
        .small{font-size:12px;color:#64748b;line-height:1.45}
    </style>';
    echo '</head><body><div class="wrap">';
    echo '<h1>Database Alter Helper</h1>';
    echo '<div class="sub">Comparing source dump <code>' . e($schemaFile) . '</code> with current database <code>' . e($dbName) . '</code>. This tool only creates missing tables, adds missing columns, and adds missing keys/indexes. It does not import rows and does not drop or modify existing data.</div>';

    if ($message !== '') {
        echo '<div class="msg ok">' . e($message) . '</div>';
    }
    if ($error !== '') {
        echo '<div class="msg err">' . e($error) . '</div>';
    }

    echo '<div class="stats">';
    echo '<div class="pill">Tables in SQL: ' . (int) $comparison['table_count'] . '</div>';
    echo '<div class="pill">Missing tables: ' . count($missingTables) . '</div>';
    echo '<div class="pill">Tables with missing columns: ' . count($columnDiffs) . '</div>';
    echo '<div class="pill">Missing columns: ' . $totalMissingColumns . '</div>';
    echo '<div class="pill">Tables with missing indexes: ' . count($indexDiffs) . '</div>';
    echo '<div class="pill">Missing indexes: ' . $totalMissingIndexes . '</div>';
    echo '</div>';

    echo '<div class="panel"><form method="post" onsubmit="return confirm(\'Apply all missing tables, columns, and keys/indexes?\')">';
    echo '<input type="hidden" name="action" value="apply_all">';
    echo '<button class="primary" type="submit">Create All Missing Alter</button>';
    echo '<div class="small" style="margin-top:8px">Use this button after checking the lists below. Keep a database backup before running schema changes.</div>';
    echo '</form></div>';

    renderMissingTables($missingTables, $comparison['tables']);
    renderMissingColumns($columnDiffs);
    renderMissingIndexes($indexDiffs);
    renderIndexConflicts($indexConflicts);

    echo '</div></body></html>';
}

function renderMissingTables(array $missingTables, array $tables): void
{
    echo '<div class="panel"><h2>Missing Tables</h2>';
    if (!$missingTables) {
        echo '<div class="empty">No missing tables.</div></div>';
        return;
    }

    echo '<table><thead><tr><th>Table</th><th>Columns</th><th>Indexes from dump</th><th>Action</th></tr></thead><tbody>';
    foreach ($missingTables as $tableName) {
        $cols = $tables[$tableName]['order'] ?? [];
        $indexes = $tables[$tableName]['indexes'] ?? [];
        echo '<tr><td><code>' . e($tableName) . '</code></td><td>' . count($cols) . '</td><td>' . count($indexes) . '</td><td>';
        echo '<form method="post" onsubmit="return confirm(\'Create table ' . e($tableName) . '?\')">';
        echo '<input type="hidden" name="action" value="create_table"><input type="hidden" name="table" value="' . e($tableName) . '">';
        echo '<button class="primary" type="submit">Create Table</button>';
        echo '</form></td></tr>';
    }
    echo '</tbody></table></div>';
}

function renderMissingColumns(array $columnDiffs): void
{
    echo '<div class="panel"><h2>Missing Columns</h2>';
    if (!$columnDiffs) {
        echo '<div class="empty">No missing columns in existing tables.</div></div>';
        return;
    }

    echo '<table><thead><tr><th>Table</th><th>Missing columns</th><th>Action</th></tr></thead><tbody>';
    foreach ($columnDiffs as $tableName => $columns) {
        echo '<tr><td><code>' . e($tableName) . '</code></td><td><div class="chips">';
        foreach (array_keys($columns) as $columnName) {
            echo '<span class="chip">' . e((string) $columnName) . '</span>';
        }
        echo '</div></td><td><form method="post" onsubmit="return confirm(\'Add missing columns for ' . e($tableName) . '?\')">';
        echo '<input type="hidden" name="action" value="add_columns"><input type="hidden" name="table" value="' . e($tableName) . '">';
        echo '<button class="warn" type="submit">Add Columns</button>';
        echo '</form></td></tr>';
    }
    echo '</tbody></table></div>';
}

function renderMissingIndexes(array $indexDiffs): void
{
    echo '<div class="panel"><h2>Missing Keys / Indexes</h2>';
    if (!$indexDiffs) {
        echo '<div class="empty">No missing keys or indexes in existing tables.</div></div>';
        return;
    }

    echo '<table><thead><tr><th>Table</th><th>Missing keys/indexes</th><th>Action</th></tr></thead><tbody>';
    foreach ($indexDiffs as $tableName => $indexes) {
        echo '<tr><td><code>' . e($tableName) . '</code></td><td><div class="chips">';
        foreach ($indexes as $index) {
            echo '<span class="chip">' . e($index['name']) . '</span>';
        }
        echo '</div></td><td><form method="post" onsubmit="return confirm(\'Add missing keys/indexes for ' . e($tableName) . '?\')">';
        echo '<input type="hidden" name="action" value="add_indexes"><input type="hidden" name="table" value="' . e($tableName) . '">';
        echo '<button class="warn" type="submit">Add Keys / Indexes</button>';
        echo '</form></td></tr>';
    }
    echo '</tbody></table></div>';
}

function renderIndexConflicts(array $indexConflicts): void
{
    if (!$indexConflicts) {
        return;
    }

    echo '<div class="panel"><h2>Existing Index Name Conflicts</h2>';
    echo '<div class="small" style="margin-bottom:10px">These names already exist but their columns/type do not match the dump. They are shown for review and are not changed automatically.</div>';
    echo '<table><thead><tr><th>Table</th><th>Index names</th></tr></thead><tbody>';
    foreach ($indexConflicts as $tableName => $indexes) {
        echo '<tr><td><code>' . e($tableName) . '</code></td><td><div class="chips">';
        foreach ($indexes as $index) {
            echo '<span class="chip">' . e($index['name']) . '</span>';
        }
        echo '</div></td></tr>';
    }
    echo '</tbody></table></div>';
}

function compareSchema(PDO $pdo, array $source): array
{
    $missingTables = [];
    $columnDiffs = [];
    $indexDiffs = [];
    $indexConflicts = [];

    foreach ($source['tables'] as $tableName => $tableDef) {
        if (!tableExists($pdo, $tableName)) {
            $missingTables[] = $tableName;
            continue;
        }

        $liveColumns = liveColumns($pdo, $tableName);
        foreach ($tableDef['columns'] as $columnName => $columnDef) {
            if (!isset($liveColumns[strtolower($columnName)])) {
                $columnDiffs[$tableName][$columnName] = $columnDef;
            }
        }

        [$missingIndexes, $conflictingIndexes] = compareIndexes($pdo, $tableName, $tableDef['indexes']);
        if ($missingIndexes) {
            $indexDiffs[$tableName] = $missingIndexes;
        }
        if ($conflictingIndexes) {
            $indexConflicts[$tableName] = $conflictingIndexes;
        }
    }

    return [
        'table_count' => count($source['tables']),
        'tables' => $source['tables'],
        'missing_tables' => $missingTables,
        'column_diffs' => $columnDiffs,
        'index_diffs' => $indexDiffs,
        'index_conflicts' => $indexConflicts,
    ];
}

function createMissingTable(PDO $pdo, array $source, string $tableName): string
{
    $tableName = validateTableName($source, $tableName);
    if (tableExists($pdo, $tableName)) {
        return "Table already exists: {$tableName}";
    }

    $pdo->exec($source['tables'][$tableName]['create_sql']);
    $addedIndexes = addMissingIndexesForTable($pdo, $source, $tableName, false);

    return "Created table: {$tableName}\n" . $addedIndexes;
}

function addMissingColumnsForTable(PDO $pdo, array $source, string $tableName): string
{
    $tableName = validateTableName($source, $tableName);
    if (!tableExists($pdo, $tableName)) {
        throw new InvalidArgumentException("Table does not exist yet: {$tableName}");
    }

    $count = 0;
    $live = liveColumns($pdo, $tableName);
    foreach ($source['tables'][$tableName]['columns'] as $columnName => $columnDef) {
        if (isset($live[strtolower($columnName)])) {
            continue;
        }

        $after = '';
        $previous = previousExistingColumn($source['tables'][$tableName]['order'], $columnName, $live);
        if ($previous !== null) {
            $after = ' AFTER ' . quoteName($previous);
        }

        $pdo->exec('ALTER TABLE ' . quoteName($tableName) . ' ADD COLUMN ' . $columnDef . $after);
        $live[strtolower($columnName)] = $columnName;
        $count++;
    }

    return "Added {$count} column(s) to {$tableName}.";
}

function addMissingIndexesForTable(PDO $pdo, array $source, string $tableName, bool $validate = true): string
{
    $tableName = $validate ? validateTableName($source, $tableName) : $tableName;
    if (!tableExists($pdo, $tableName)) {
        throw new InvalidArgumentException("Table does not exist yet: {$tableName}");
    }

    [$missingIndexes] = compareIndexes($pdo, $tableName, $source['tables'][$tableName]['indexes']);
    $count = 0;

    foreach ($missingIndexes as $index) {
        $pdo->exec('ALTER TABLE ' . quoteName($tableName) . ' ' . $index['add_sql']);
        $count++;
    }

    return "Added {$count} key/index definition(s) to {$tableName}.";
}

function applyAllMissing(PDO $pdo, array $source): string
{
    $created = 0;
    $columns = 0;
    $indexes = 0;

    foreach (array_keys($source['tables']) as $tableName) {
        if (!tableExists($pdo, $tableName)) {
            $pdo->exec($source['tables'][$tableName]['create_sql']);
            $created++;
        } else {
            $beforeColumns = liveColumns($pdo, $tableName);
            addMissingColumnsForTable($pdo, $source, $tableName);
            $afterColumns = liveColumns($pdo, $tableName);
            $columns += max(0, count($afterColumns) - count($beforeColumns));
        }

        [$missingIndexes] = compareIndexes($pdo, $tableName, $source['tables'][$tableName]['indexes']);
        foreach ($missingIndexes as $index) {
            $pdo->exec('ALTER TABLE ' . quoteName($tableName) . ' ' . $index['add_sql']);
            $indexes++;
        }
    }

    return "Created {$created} table(s), added {$columns} column(s), added {$indexes} key/index definition(s).";
}

function validateTableName(array $source, string $tableName): string
{
    $tableName = trim($tableName);
    if ($tableName === '' || !isset($source['tables'][$tableName])) {
        throw new InvalidArgumentException('Table not found in SQL file.');
    }

    return $tableName;
}

function parseDumpSchema(string $sql): array
{
    $tables = parseCreateTables($sql);
    $alterIndexes = parseAlterIndexes($sql);

    foreach ($tables as $tableName => &$tableDef) {
        $tableDef['indexes'] = $alterIndexes[$tableName] ?? [];
    }
    unset($tableDef);

    return ['tables' => $tables];
}

function parseCreateTables(string $sql): array
{
    $tables = [];
    $offset = 0;

    while (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`\s*\(/i', $sql, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $tableName = $match[1][0];
        $createStart = $match[0][1];
        $openParen = $createStart + strlen($match[0][0]) - 1;
        $closeParen = findMatchingParen($sql, $openParen);
        if ($closeParen === null) {
            $offset = $openParen + 1;
            continue;
        }

        $semicolon = findStatementSemicolon($sql, $closeParen);
        if ($semicolon === null) {
            $offset = $closeParen + 1;
            continue;
        }

        $createSql = trim(substr($sql, $createStart, $semicolon - $createStart + 1));
        $body = substr($sql, $openParen + 1, $closeParen - $openParen - 1);
        $columns = [];
        $order = [];
        $inlineIndexes = [];

        foreach (splitTopLevelDefinitions($body) as $definition) {
            $definition = trim($definition);
            if (preg_match('/^`([^`]+)`\s+/s', $definition, $columnMatch)) {
                $columnName = $columnMatch[1];
                $columns[$columnName] = normalizeColumnDefinition($definition);
                $order[] = $columnName;
                continue;
            }

            $index = parseIndexAddDefinition('ADD ' . $definition);
            if ($index !== null) {
                $inlineIndexes[$index['name']] = $index;
            }
        }

        $tables[$tableName] = [
            'create_sql' => normalizeCreateSql($createSql),
            'columns' => $columns,
            'order' => $order,
            'indexes' => array_values($inlineIndexes),
        ];

        $offset = $semicolon + 1;
    }

    return $tables;
}

function parseAlterIndexes(string $sql): array
{
    $indexes = [];
    foreach (splitSqlStatements($sql) as $statement) {
        $statement = stripLeadingSqlComments(trim($statement));
        if (!preg_match('/^ALTER\s+TABLE\s+`([^`]+)`\s+(.+)$/is', $statement, $match)) {
            continue;
        }

        $tableName = $match[1];
        $body = trim(rtrim($match[2], ';'));
        foreach (splitTopLevelDefinitions($body) as $part) {
            $index = parseIndexAddDefinition($part);
            if ($index !== null) {
                $indexes[$tableName][$index['name']] = $index;
            }
        }
    }

    foreach ($indexes as $tableName => $tableIndexes) {
        $indexes[$tableName] = array_values($tableIndexes);
    }

    return $indexes;
}

function stripLeadingSqlComments(string $statement): string
{
    do {
        $before = $statement;
        $statement = preg_replace('/^\s*--[^\r\n]*(?:\r\n|\r|\n)?/', '', $statement) ?? $statement;
        $statement = preg_replace('/^\s*#[^\r\n]*(?:\r\n|\r|\n)?/', '', $statement) ?? $statement;
        $statement = preg_replace('/^\s*\/\*![\s\S]*?\*\/\s*/', '', $statement) ?? $statement;
        $statement = preg_replace('/^\s*\/\*[\s\S]*?\*\/\s*/', '', $statement) ?? $statement;
    } while ($statement !== $before);

    return trim($statement);
}

function parseIndexAddDefinition(string $definition): ?array
{
    $definition = normalizeWhitespace(trim(rtrim($definition, ',')));
    if (!preg_match('/^ADD\s+((?:PRIMARY\s+KEY)|(?:UNIQUE\s+(?:KEY|INDEX))|(?:(?:FULLTEXT|SPATIAL)\s+(?:KEY|INDEX))|(?:KEY|INDEX))\b/i', $definition, $typeMatch)) {
        return null;
    }

    $type = strtoupper(normalizeWhitespace($typeMatch[1]));
    if (str_starts_with($type, 'PRIMARY')) {
        $name = 'PRIMARY';
    } elseif (preg_match('/^ADD\s+\S+(?:\s+\S+)?\s+`([^`]+)`/i', $definition, $nameMatch)) {
        $name = $nameMatch[1];
    } else {
        return null;
    }

    $columns = extractIndexColumns($definition);

    return [
        'name' => $name,
        'type' => $type,
        'columns' => $columns,
        'signature' => normalizeIndexSignature($type, $columns),
        'add_sql' => $definition,
    ];
}

function extractIndexColumns(string $definition): array
{
    $firstParen = strpos($definition, '(');
    if ($firstParen === false) {
        return [];
    }

    $closeParen = findMatchingParen($definition, $firstParen);
    if ($closeParen === null) {
        return [];
    }

    $inside = substr($definition, $firstParen + 1, $closeParen - $firstParen - 1);
    $columns = [];
    foreach (splitTopLevelDefinitions($inside) as $columnPart) {
        if (preg_match('/`([^`]+)`/', $columnPart, $match)) {
            $columns[] = strtolower($match[1]);
        }
    }

    return $columns;
}

function compareIndexes(PDO $pdo, string $tableName, array $sourceIndexes): array
{
    $live = liveIndexes($pdo, $tableName);
    $missing = [];
    $conflicts = [];

    foreach ($sourceIndexes as $index) {
        $key = strtolower($index['name']);
        if (!isset($live[$key])) {
            $missing[] = $index;
            continue;
        }

        if ($live[$key]['signature'] !== $index['signature']) {
            $conflicts[] = $index;
        }
    }

    return [$missing, $conflicts];
}

function liveIndexes(PDO $pdo, string $tableName): array
{
    $stmt = $pdo->prepare(
        'SELECT INDEX_NAME, NON_UNIQUE, INDEX_TYPE, COLUMN_NAME, SEQ_IN_INDEX
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         ORDER BY INDEX_NAME, SEQ_IN_INDEX'
    );
    $stmt->execute([$tableName]);

    $indexes = [];
    foreach ($stmt->fetchAll() as $row) {
        $name = (string) $row['INDEX_NAME'];
        $key = strtolower($name);
        if (!isset($indexes[$key])) {
            $indexes[$key] = [
                'name' => $name,
                'type' => indexTypeFromInformationSchema($name, (int) $row['NON_UNIQUE'], (string) $row['INDEX_TYPE']),
                'columns' => [],
            ];
        }
        $indexes[$key]['columns'][] = strtolower((string) $row['COLUMN_NAME']);
    }

    foreach ($indexes as &$index) {
        $index['signature'] = normalizeIndexSignature($index['type'], $index['columns']);
    }
    unset($index);

    return $indexes;
}

function indexTypeFromInformationSchema(string $name, int $nonUnique, string $indexType): string
{
    if (strtoupper($name) === 'PRIMARY') {
        return 'PRIMARY KEY';
    }

    $indexType = strtoupper($indexType);
    if ($indexType === 'FULLTEXT') {
        return 'FULLTEXT KEY';
    }
    if ($indexType === 'SPATIAL') {
        return 'SPATIAL KEY';
    }
    if ($nonUnique === 0) {
        return 'UNIQUE KEY';
    }

    return 'KEY';
}

function normalizeIndexSignature(string $type, array $columns): string
{
    $type = strtoupper(normalizeWhitespace($type));
    $type = str_replace('INDEX', 'KEY', $type);
    return $type . ':' . implode(',', array_map('strtolower', $columns));
}

function tableExists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$tableName]);

    return (int) $stmt->fetchColumn() > 0;
}

function liveColumns(PDO $pdo, string $tableName): array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute([$tableName]);

    $columns = [];
    foreach ($stmt->fetchAll() as $row) {
        $name = (string) $row['COLUMN_NAME'];
        $columns[strtolower($name)] = $name;
    }

    return $columns;
}

function previousExistingColumn(array $exportOrder, string $columnName, array $liveColumns): ?string
{
    $previous = null;
    foreach ($exportOrder as $candidate) {
        if ($candidate === $columnName) {
            return $previous;
        }
        if (isset($liveColumns[strtolower($candidate)])) {
            $previous = $liveColumns[strtolower($candidate)];
        }
    }

    return null;
}

function splitSqlStatements(string $sql): array
{
    $statements = [];
    $start = 0;
    $quote = null;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if ($quote !== null) {
            if ($char === $quote && $prev !== '\\') {
                $quote = null;
            }
            continue;
        }

        if ($char === '\'' || $char === '"') {
            $quote = $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim(substr($sql, $start, $i - $start + 1));
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $start = $i + 1;
        }
    }

    $tail = trim(substr($sql, $start));
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function findStatementSemicolon(string $sql, int $offset): ?int
{
    $quote = null;
    $length = strlen($sql);

    for ($i = $offset; $i < $length; $i++) {
        $char = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if ($quote !== null) {
            if ($char === $quote && $prev !== '\\') {
                $quote = null;
            }
            continue;
        }

        if ($char === '\'' || $char === '"') {
            $quote = $char;
            continue;
        }

        if ($char === ';') {
            return $i;
        }
    }

    return null;
}

function findMatchingParen(string $sql, int $openParen): ?int
{
    $depth = 0;
    $quote = null;
    $length = strlen($sql);

    for ($i = $openParen; $i < $length; $i++) {
        $char = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if ($quote !== null) {
            if ($char === $quote && $prev !== '\\') {
                $quote = null;
            }
            continue;
        }

        if ($char === '\'' || $char === '"') {
            $quote = $char;
            continue;
        }

        if ($char === '(') {
            $depth++;
        } elseif ($char === ')') {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }

    return null;
}

function splitTopLevelDefinitions(string $body): array
{
    $parts = [];
    $start = 0;
    $depth = 0;
    $quote = null;
    $length = strlen($body);

    for ($i = 0; $i < $length; $i++) {
        $char = $body[$i];
        $prev = $i > 0 ? $body[$i - 1] : '';

        if ($quote !== null) {
            if ($char === $quote && $prev !== '\\') {
                $quote = null;
            }
            continue;
        }

        if ($char === '\'' || $char === '"') {
            $quote = $char;
            continue;
        }

        if ($char === '(') {
            $depth++;
        } elseif ($char === ')') {
            $depth--;
        } elseif ($char === ',' && $depth === 0) {
            $parts[] = substr($body, $start, $i - $start);
            $start = $i + 1;
        }
    }

    $tail = trim(substr($body, $start));
    if ($tail !== '') {
        $parts[] = $tail;
    }

    return $parts;
}

function normalizeCreateSql(string $sql): string
{
    $sql = preg_replace('/\s+AUTO_INCREMENT\s*=\s*\d+/i', '', $sql) ?? $sql;
    $sql = str_replace('DATE NOT NULL DEFAULT CURRENT_DATE', 'DATE NOT NULL', $sql);
    $sql = str_replace('DATE DEFAULT CURRENT_DATE', 'DATE DEFAULT NULL', $sql);
    $sql = str_replace('TIME DEFAULT CURRENT_TIME', 'TIME DEFAULT NULL', $sql);
    return $sql;
}

function normalizeColumnDefinition(string $definition): string
{
    $definition = normalizeWhitespace(trim($definition));
    $definition = str_replace('DATE NOT NULL DEFAULT CURRENT_DATE', 'DATE NOT NULL', $definition);
    $definition = str_replace('DATE DEFAULT CURRENT_DATE', 'DATE DEFAULT NULL', $definition);
    $definition = str_replace('TIME DEFAULT CURRENT_TIME', 'TIME DEFAULT NULL', $definition);
    return $definition;
}

function normalizeWhitespace(string $value): string
{
    return preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
}

function quoteName(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
