<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daybookpart', function (Blueprint $table) {
            if (!Schema::hasColumn('daybookpart', 'discount')) {
                $table->decimal('discount', 12, 2)->default(0)->after('taxamt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('daybookpart', function (Blueprint $table) {
            if (Schema::hasColumn('daybookpart', 'discount')) {
                $table->dropColumn('discount');
            }
        });
    }
};
