<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ratehistory')) {
            return;
        }

        $columns = [
            'g18rate',
            'g14rate',
            'g9rate',
            'g4rate',
            'jrate',
            'ograte',
            'osrate',
        ];

        $alters = [];
        foreach ($columns as $column) {
            if (!Schema::hasColumn('ratehistory', $column)) {
                $alters[] = "ADD COLUMN `{$column}` DECIMAL(15,3) NOT NULL DEFAULT 0";
            }
        }

        if ($alters !== []) {
            DB::statement('ALTER TABLE `ratehistory` ' . implode(', ', $alters));
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('ratehistory')) {
            return;
        }

        $columns = [
            'g18rate',
            'g14rate',
            'g9rate',
            'g4rate',
            'jrate',
            'ograte',
            'osrate',
        ];

        $drops = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn('ratehistory', $column)) {
                $drops[] = "DROP COLUMN `{$column}`";
            }
        }

        if ($drops !== []) {
            DB::statement('ALTER TABLE `ratehistory` ' . implode(', ', $drops));
        }
    }
};
