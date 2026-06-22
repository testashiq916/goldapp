<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('userhist')) {
            return;
        }

        Schema::table('userhist', function (Blueprint $table) {
            if (!Schema::hasColumn('userhist', 'ip')) {
                $table->string('ip', 45)->nullable()->after('time2');
            }
            if (!Schema::hasColumn('userhist', 'useragent')) {
                $table->string('useragent', 512)->nullable()->after('ip');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('userhist')) {
            return;
        }

        Schema::table('userhist', function (Blueprint $table) {
            $table->dropColumn(array_filter(['ip', 'useragent'], fn ($c) => Schema::hasColumn('userhist', $c)));
        });
    }
};
