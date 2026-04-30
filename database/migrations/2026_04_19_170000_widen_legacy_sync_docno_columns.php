<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $targets = [
            ['table' => 'salesm', 'columns' => ['billno', 'docno']],
            ['table' => 'salesrm', 'columns' => ['billno', 'docno']],
            ['table' => 'purchasem', 'columns' => ['billno', 'docno']],
            ['table' => 'purchaserm', 'columns' => ['billno', 'docno']],
            ['table' => 'daybook', 'columns' => ['docno']],
            ['table' => 'stkandprofit', 'columns' => ['docno']],
            ['table' => 'kuricolln', 'columns' => ['docno']],
            ['table' => 'pdclist', 'columns' => ['docno']],
        ];

        foreach ($this->connectionsToUpdate() as $connection) {
            foreach ($targets as $target) {
                $table = $target['table'];
                if (!Schema::connection($connection)->hasTable($table)) {
                    continue;
                }

                foreach ($target['columns'] as $column) {
                    if (!Schema::connection($connection)->hasColumn($table, $column)) {
                        continue;
                    }

                    DB::connection($connection)->statement(
                        "ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(30) NULL"
                    );
                }
            }
        }
    }

    public function down(): void
    {
        // Intentionally left as no-op. Reverting to older smaller widths is unsafe.
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
        Config::set('database.connections.secondary_sync_docno_fix', $baseConfig);
        DB::purge('secondary_sync_docno_fix');

        $connections[] = 'secondary_sync_docno_fix';

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
