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

    private function schemaCacheKey(string $table): string
    {
        return (string) config('database.connections.mysql.database', '') . '.' . strtolower($table);
    }
}
