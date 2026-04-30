<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gifttable')) {
            return;
        }

        Schema::create('gifttable', function (Blueprint $table) {
            $table->integer('points')->primary();
            $table->string('particulars', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gifttable');
    }
};

