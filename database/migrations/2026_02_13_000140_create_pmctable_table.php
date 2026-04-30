<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pmctable')) {
            return;
        }

        Schema::create('pmctable', function (Blueprint $table) {
            $table->id();
            $table->string('pcode', 10);
            $table->string('icode', 10);
            $table->string('model', 15)->nullable();
            $table->decimal('wastage', 10, 2)->default(0);
            $table->decimal('mc', 10, 2)->default(0);
            $table->decimal('mcperc', 10, 2)->default(0);
            $table->decimal('mcperqty', 10, 2)->default(0);
            $table->decimal('touch', 10, 2)->default(0);
            $table->string('formula', 15)->nullable();
            $table->timestamps();

            $table->index(['pcode', 'icode', 'model']);
            $table->index('pcode');
            $table->index('icode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pmctable');
    }
};

