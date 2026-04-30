<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('models')) {
            return;
        }

        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->string('mtype', 1);
            $table->string('name', 20);
            $table->timestamps();
            $table->unique(['mtype', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('models');
    }
};

