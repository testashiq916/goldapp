<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salestype')) {
            DB::statement("ALTER TABLE `salestype`
                MODIFY `formno` VARCHAR(10) NOT NULL DEFAULT '',
                MODIFY `prefix` VARCHAR(10) NOT NULL DEFAULT '',
                MODIFY `pprefix` VARCHAR(10) NOT NULL DEFAULT '',
                MODIFY `srprefix` VARCHAR(10) NOT NULL DEFAULT '',
                MODIFY `prprefix` VARCHAR(10) NOT NULL DEFAULT ''");
        }

        if (Schema::hasTable('generali')) {
            DB::statement("ALTER TABLE `generali`
                MODIFY `code` VARCHAR(20) NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salestype')) {
            DB::statement("ALTER TABLE `salestype`
                MODIFY `formno` VARCHAR(5) NOT NULL DEFAULT '',
                MODIFY `prefix` VARCHAR(5) NOT NULL DEFAULT '',
                MODIFY `pprefix` VARCHAR(5) NOT NULL DEFAULT '',
                MODIFY `srprefix` VARCHAR(5) NOT NULL DEFAULT '',
                MODIFY `prprefix` VARCHAR(5) NOT NULL DEFAULT ''");
        }

        if (Schema::hasTable('generali')) {
            DB::statement("ALTER TABLE `generali`
                MODIFY `code` CHAR(10) NOT NULL");
        }
    }
};
