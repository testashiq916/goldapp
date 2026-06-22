<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hallmark_records', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no', 30)->nullable();
            $table->string('item_code', 30)->nullable();
            $table->string('barcode', 30)->nullable();
            $table->string('huid', 30)->nullable()->unique(); // Hallmark Unique ID (BIS)
            $table->string('bis_centre', 100)->nullable();
            $table->string('purity_grade', 20)->nullable(); // 916, 750, 585, 375
            $table->string('purity_name', 50)->nullable();  // 22K, 18K, 14K, 9K
            $table->decimal('weight', 10, 3)->default(0);
            $table->string('certificate_no', 60)->nullable();
            $table->date('hallmark_date')->nullable();
            $table->string('article_desc', 100)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->index('batch_no');
            $table->index('item_code');
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hallmark_records');
    }
};
