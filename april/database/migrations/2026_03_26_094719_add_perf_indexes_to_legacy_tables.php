<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes to legacy tables for performance.
     * Each index creation is wrapped in try/catch so existing indexes don't block migration.
     */
    public function up(): void
    {
        $indexes = [
            // userd.code — CheckMenuAccess fires UPPER(TRIM(code)) on every request
            ['userd', 'code', 'idx_userd_code'],
            // clients — list queries filter by ctype, search by name/mobile/telephone/city
            ['clients', 'ctype', 'idx_clients_ctype'],
            ['clients', 'name', 'idx_clients_name'],
            ['clients', 'mobile', 'idx_clients_mobile'],
            ['clients', 'telephone', 'idx_clients_telephone'],
            // daybook.accode — used in delete check, ledger queries
            ['daybook', 'accode', 'idx_daybook_accode'],
            // accountm.accode — upsert + lookup
            ['accountm', 'accode', 'idx_accountm_accode'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (!Schema::hasColumn($table, $column)) {
                continue;
            }
            try {
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` (`{$column}`)");
            } catch (\Throwable) {
                // Index may already exist — safe to ignore
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            ['userd', 'idx_userd_code'],
            ['clients', 'idx_clients_ctype'],
            ['clients', 'idx_clients_name'],
            ['clients', 'idx_clients_mobile'],
            ['clients', 'idx_clients_telephone'],
            ['daybook', 'idx_daybook_accode'],
            ['accountm', 'idx_accountm_accode'],
        ];

        foreach ($indexes as [$table, $name]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            try {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
            } catch (\Throwable) {
                // Index may not exist — safe to ignore
            }
        }
    }
};
