<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mctable')) {
            return;
        }

        Schema::create('mctable', function (Blueprint $table) {
            $table->string('code', 10);
            $table->decimal('weight1', 10, 3)->default(0);
            $table->decimal('weight2', 10, 3)->default(0);
            $table->decimal('mc', 10, 2)->default(0);
            $table->decimal('mcpergm', 10, 2)->default(0);
            $table->decimal('mcperqty', 10, 2)->default(0);
            $table->decimal('vaperc', 10, 2)->default(0);
            $table->string('iqtype', 10)->default('');

            $table->index(['code', 'iqtype', 'weight1', 'weight2'], 'mctable_code_iqtype_wgt_idx');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mctable');
    }
};

