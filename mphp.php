<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$schemaFile = 'f:\\files\\complete_database_export.sql';
$apply = in_array('--apply', $argv ?? [], true) || (isset($_GET['apply']) && $_GET['apply'] === '1');

header('Content-Type: text/plain; charset=UTF-8');

if (!is_file($schemaFile)) {
    exit("Schema file not found: {$schemaFile}\n");
}

echo "Comparing live database with: {$schemaFile}\n";
echo "Mode: " . ($apply ? 'APPLY' : 'DRY RUN') . "\n\n";

$schemaSql = file_get_contents($schemaFile);
if ($schemaSql === false) {
    exit("Unable to read schema file.\n");
}

$tables = parseCreateTables($schemaSql);
if (!$tables) {
    exit("No CREATE TABLE blocks found.\n");
}

$createdTables = 0;
$addedColumns = 0;
$skipped = 0;
$errors = 0;

foreach ($tables as $tableName => $tableDef) {
    $exists = (bool) DB::selectOne(
        'SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$tableName]
    )->cnt;

    if (!$exists) {
        $sql = $tableDef['create_sql'];
        echo "[CREATE TABLE] {$tableName}\n";
        if ($apply) {
            try {
                DB::statement($sql);
                $createdTables++;
            } catch (Throwable $e) {
                $errors++;
                echo "  ERROR: " . $e->getMessage() . "\n";
            }
        }
        continue;
    }

    $liveColumns = liveColumns($tableName);
    foreach ($tableDef['columns'] as $columnName => $columnDef) {
        if (isset($liveColumns[strtolower($columnName)])) {
            continue;
        }

        $afterSql = '';
        $previousColumn = previousExistingColumn($tableDef['order'], $columnName, $liveColumns);
        if ($previousColumn !== null) {
            $afterSql = ' AFTER `' . str_replace('`', '``', $previousColumn) . '`';
        }

        $sql = 'ALTER TABLE `' . str_replace('`', '``', $tableName) . '` ADD COLUMN ' . $columnDef . $afterSql;
        echo "[ADD COLUMN] {$tableName}.{$columnName}\n";
        echo "  {$sql};\n";

        if ($apply) {
            try {
                DB::statement($sql);
                $addedColumns++;
            } catch (Throwable $e) {
                $errors++;
                echo "  ERROR: " . $e->getMessage() . "\n";
            }
        }
    }
}

echo "\nDone.\n";
echo "Tables in export: " . count($tables) . "\n";
echo "Tables created: {$createdTables}\n";
echo "Columns added: {$addedColumns}\n";
echo "Errors: {$errors}\n";
echo $apply
    ? "Changes were applied.\n"
    : "Dry run only. Run `php mphp.php --apply` or open `mphp.php?apply=1` to apply.\n";

function parseCreateTables(string $sql): array
{
    $tables = [];
    $offset = 0;

    while (preg_match('/CREATE\s+TABLE\s+`([^`]+)`\s*\(/i', $sql, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $tableName = $match[1][0];
        $createStart = $match[0][1];
        $openParen = $createStart + strlen($match[0][0]) - 1;
        $closeParen = findMatchingParen($sql, $openParen);
        if ($closeParen === null) {
            $offset = $openParen + 1;
            continue;
        }

        $semicolon = strpos($sql, ';', $closeParen);
        if ($semicolon === false) {
            $offset = $closeParen + 1;
            continue;
        }

        $createSql = trim(substr($sql, $createStart, $semicolon - $createStart + 1));
        $body = substr($sql, $openParen + 1, $closeParen - $openParen - 1);
        $columns = [];
        $order = [];

        foreach (splitTopLevelDefinitions($body) as $definition) {
            $definition = trim($definition);
            if (!preg_match('/^`([^`]+)`\s+/s', $definition, $columnMatch)) {
                continue;
            }

            $columnName = $columnMatch[1];
            $columns[$columnName] = normalizeColumnDefinition($definition);
            $order[] = $columnName;
        }

        $tables[$tableName] = [
            'create_sql' => $createSql,
            'columns' => $columns,
            'order' => $order,
        ];

        $offset = $semicolon + 1;
    }

    return $tables;
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

function normalizeColumnDefinition(string $definition): string
{
    $definition = trim($definition);
    $definition = preg_replace('/\s+/', ' ', $definition) ?? $definition;
    $definition = str_replace('DATE NOT NULL DEFAULT CURRENT_DATE', 'DATE NOT NULL', $definition);
    $definition = str_replace('DATE DEFAULT CURRENT_DATE', 'DATE DEFAULT NULL', $definition);
    $definition = str_replace('TIME DEFAULT CURRENT_TIME', 'TIME DEFAULT NULL', $definition);

    return $definition;
}

function liveColumns(string $tableName): array
{
    $rows = DB::select(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$tableName]
    );

    $columns = [];
    foreach ($rows as $row) {
        $name = (string) $row->COLUMN_NAME;
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
