<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('denom_master')) {
            return;
        }

        Schema::create('denom_master', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('name', 20)->nullable();
            $table->decimal('cvalue', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('denom_master');
    }
};

