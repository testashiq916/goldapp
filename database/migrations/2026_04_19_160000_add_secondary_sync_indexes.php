<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            ['salesm', 'billno', 'idx_salesm_billno'],
            ['salesm', 'slno', 'idx_salesm_slno'],
            ['purchasem', 'docno', 'idx_purchasem_docno'],
            ['purchasem', 'slno', 'idx_purchasem_slno'],
            ['orderm', 'ordno', 'idx_orderm_ordno'],
            ['orderm', 'slno', 'idx_orderm_slno'],
            ['daybook', 'slno', 'idx_daybook_slno'],
            ['daybookpart', 'slno', 'idx_daybookpart_slno'],
            ['daybookpart', 'vchno', 'idx_daybookpart_vchno'],
            ['salesd', 'slno', 'idx_salesd_slno'],
            ['salesrd', 'slno', 'idx_salesrd_slno'],
            ['purchased', 'slno', 'idx_purchased_slno'],
            ['barcode', 'docno', 'idx_barcode_docno'],
            ['barcode', 'slno', 'idx_barcode_slno'],
            ['barcode', 'bcode', 'idx_barcode_bcode'],
        ];

        foreach ($this->connectionsToUpdate() as $connection) {
            foreach ($indexes as [$table, $column, $name]) {
                if (!Schema::connection($connection)->hasTable($table) || !Schema::connection($connection)->hasColumn($table, $column)) {
                    continue;
                }

                try {
                    DB::connection($connection)->statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` (`{$column}`)");
                } catch (\Throwable) {
                    // Safe to ignore if the index already exists.
                }
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            ['salesm', 'idx_salesm_billno'],
            ['salesm', 'idx_salesm_slno'],
            ['purchasem', 'idx_purchasem_docno'],
            ['purchasem', 'idx_purchasem_slno'],
            ['orderm', 'idx_orderm_ordno'],
            ['orderm', 'idx_orderm_slno'],
            ['daybook', 'idx_daybook_slno'],
            ['daybookpart', 'idx_daybookpart_slno'],
            ['daybookpart', 'idx_daybookpart_vchno'],
            ['salesd', 'idx_salesd_slno'],
            ['salesrd', 'idx_salesrd_slno'],
            ['purchased', 'idx_purchased_slno'],
            ['barcode', 'idx_barcode_docno'],
            ['barcode', 'idx_barcode_slno'],
            ['barcode', 'idx_barcode_bcode'],
        ];

        foreach ($this->connectionsToUpdate() as $connection) {
            foreach ($indexes as [$table, $name]) {
                if (!Schema::connection($connection)->hasTable($table)) {
                    continue;
                }

                try {
                    DB::connection($connection)->statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
                } catch (\Throwable) {
                    // Safe to ignore if the index does not exist.
                }
            }
        }
    }

    private function connectionsToUpdate(): array
    {
        $connections = ['mysql'];
        $secondaryDatabase = $this->configuredSecondaryDatabase();
        if ($secondaryDatabase === '') {
            return $connections;
        }

        $baseConfig = Config::get('database.connections.mysql');
        if (!is_array($baseConfig) || $baseConfig === []) {
            return $connections;
        }

        if (strcasecmp((string) ($baseConfig['database'] ?? ''), $secondaryDatabase) === 0) {
            return $connections;
        }

        $baseConfig['database'] = $secondaryDatabase;
        Config::set('database.connections.secondary_sync_indexes', $baseConfig);
        DB::purge('secondary_sync_indexes');

        $connections[] = 'secondary_sync_indexes';

        return $connections;
    }

    private function configuredSecondaryDatabase(): string
    {
        $path = storage_path('app/company-dbstrf.json');
        if (!File::exists($path)) {
            return '';
        }

        $decoded = json_decode((string) File::get($path), true);
        $database = is_array($decoded) ? trim((string) ($decoded['dbstrf'] ?? '')) : '';

        return preg_match('/^[A-Za-z0-9_]+$/', $database) === 1 ? $database : '';
    }
};
