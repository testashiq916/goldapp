<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class Controller
{
    /** @var array<string, bool> */
    private static array $tableExistsCache = [];

    /** @var array<string, list<string>> */
    private static array $columnListCache = [];

    /** @var array<string, array<string, int>> */
    private static array $columnLengthCache = [];

    protected function hasTable(string $table): bool
    {
        $key = $this->schemaCacheKey($table);
        if (!array_key_exists($key, self::$tableExistsCache)) {
            self::$tableExistsCache[$key] = Schema::hasTable($table);
        }
        return self::$tableExistsCache[$key];
    }

    protected function columnList(string $table): array
    {
        $key = $this->schemaCacheKey($table);
        if (!array_key_exists($key, self::$columnListCache)) {
            try {
                self::$columnListCache[$key] = array_map('strtolower', Schema::getColumnListing($table));
            } catch (\Throwable) {
                self::$columnListCache[$key] = [];
            }
        }
        return self::$columnListCache[$key];
    }

    protected function applyTransferShadowDocFilter($query, string $columnExpression): void
    {
        $currentDatabase = strtolower(trim((string) config('database.connections.mysql.database', '')));
        $primaryDatabase = strtolower(trim((string) env('DB_DATABASE', '')));
        if ($currentDatabase !== '' && $primaryDatabase !== '' && $currentDatabase !== $primaryDatabase) {
            return;
        }

        $query->whereRaw("NOT (LOWER(TRIM(COALESCE({$columnExpression}, ''))) REGEXP '[0-9]d[0-9]*$')");
    }

    private function schemaCacheKey(string $table): string
    {
        return (string) config('database.connections.mysql.database', '') . '.' . strtolower($table);
    }

    /**
     * Truncate string values in $data so they fit the actual VARCHAR length of
     * the matching column. Prevents "Data too long" errors on legacy tables
     * whose column widths are smaller than typical user input.
     *
     * @param array<string, mixed> $data
     * @param list<string> $columns
     */
    protected function clampToColumnLengths(string $table, array &$data, array $columns): void
    {
        foreach ($columns as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $value = $data[$col];
            if (!is_string($value) || $value === '') {
                continue;
            }
            $limit = $this->columnMaxLength($table, $col);
            if ($limit > 0 && mb_strlen($value) > $limit) {
                $data[$col] = mb_substr($value, 0, $limit);
            }
        }
    }

    private function columnMaxLength(string $table, string $column): int
    {
        $tableKey = $this->schemaCacheKey($table);
        if (!isset(self::$columnLengthCache[$tableKey])) {
            self::$columnLengthCache[$tableKey] = [];
            try {
                $rows = DB::select(
                    'SELECT COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS '
                    . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CHARACTER_MAXIMUM_LENGTH IS NOT NULL',
                    [$table]
                );
                foreach ($rows as $row) {
                    $name = strtolower((string) ($row->COLUMN_NAME ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    self::$columnLengthCache[$tableKey][$name] = (int) ($row->CHARACTER_MAXIMUM_LENGTH ?? 0);
                }
            } catch (\Throwable) {
                // ignore — falls through to 0 (no clamping)
            }
        }
        return self::$columnLengthCache[$tableKey][strtolower($column)] ?? 0;
    }
}
