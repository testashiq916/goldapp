<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pcardtable')) {
            return;
        }

        Schema::create('pcardtable', function (Blueprint $table) {
            $table->string('pcard', 10);
            $table->string('isubgrp', 10);
            $table->string('pointbasedon', 10)->default('Wgt');
            $table->decimal('valuefor1point', 12, 2)->default(0);
            $table->decimal('valueperpoint', 12, 2)->default(0);
            $table->decimal('minsalesamt', 12, 2)->default(0);
            $table->decimal('rounddown', 12, 3)->default(0);

            $table->primary(['pcard', 'isubgrp'], 'pcardtable_pk');
            $table->index('pcard');
            $table->index('isubgrp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcardtable');
    }
};

