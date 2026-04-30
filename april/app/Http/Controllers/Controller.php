<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Schema;

abstract class Controller
{
    /** @var array<string, bool> */
    private static array $tableExistsCache = [];

    /** @var array<string, list<string>> */
    private static array $columnListCache = [];

    protected function hasTable(string $table): bool
    {
        if (!array_key_exists($table, self::$tableExistsCache)) {
            self::$tableExistsCache[$table] = Schema::hasTable($table);
        }
        return self::$tableExistsCache[$table];
    }

    protected function columnList(string $table): array
    {
        if (!array_key_exists($table, self::$columnListCache)) {
            try {
                self::$columnListCache[$table] = array_map('strtolower', Schema::getColumnListing($table));
            } catch (\Throwable) {
                self::$columnListCache[$table] = [];
            }
        }
        return self::$columnListCache[$table];
    }
}
