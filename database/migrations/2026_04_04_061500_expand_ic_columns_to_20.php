<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that store legacy incharge/user codes.
     *
     * @var list<string>
     */
    private array $tables = [
        'daybookpart',
        'delpart',
        'itemadj',
        'oitemtranm',
        'orderm',
        'purchasem',
        'purchaserm',
        'refinerym',
        'repairm',
        'salesm',
        'salesrm',
        'smithm',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'ic')) {
                DB::statement(sprintf(
                    'ALTER TABLE `%s` MODIFY `ic` VARCHAR(20) NULL',
                    $table
                ));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'ic')) {
                DB::statement(sprintf(
                    'ALTER TABLE `%s` MODIFY `ic` VARCHAR(5) NULL',
                    $table
                ));
            }
        }
    }
};
