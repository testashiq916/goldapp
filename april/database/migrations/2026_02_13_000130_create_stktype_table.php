<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stktype')) {
            return;
        }

        Schema::create('stktype', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 30);
            $table->tinyInteger('def')->default(0);
            $table->tinyInteger('compare')->default(0);
            $table->timestamps();
            $table->index('def');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stktype');
    }
};

