<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('generals') || !Schema::hasColumn('generals', 'cvalue')) {
            return;
        }

        $database = (string) DB::getDatabaseName();
        if ($database === '') {
            return;
        }

        $column = DB::selectOne(
            "SELECT DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS char_len
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'generals' AND COLUMN_NAME = 'cvalue'
             LIMIT 1",
            [$database]
        );

        $dataType = strtolower((string) ($column->data_type ?? ''));
        $charLen = (int) ($column->char_len ?? 0);
        if (in_array($dataType, ['text', 'mediumtext', 'longtext'], true) || $charLen >= 1024) {
            return;
        }

        DB::statement('ALTER TABLE `generals` MODIFY `cvalue` VARCHAR(1024) NULL');
    }

    public function down(): void
    {
        // Intentionally left as-is to avoid truncating existing stored settings.
    }
};
