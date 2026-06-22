<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('item_images', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 30)->nullable();
            $table->string('barcode', 30)->nullable();
            $table->string('filename', 255);
            $table->string('original_name', 255)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index('item_code');
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_images');
    }
};
