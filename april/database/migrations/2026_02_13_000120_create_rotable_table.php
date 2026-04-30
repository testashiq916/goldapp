<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rotable')) {
            return;
        }

        Schema::create('rotable', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->index();
            $table->string('model', 20)->nullable();
            $table->string('size', 20)->nullable();
            $table->decimal('weight1', 8, 3)->default(0);
            $table->decimal('weight2', 8, 3)->default(0);
            $table->integer('minqty')->default(0);
            $table->integer('maxqty')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotable');
    }
};

