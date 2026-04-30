<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('route')) {
            Schema::create('route', function (Blueprint $table) {
                $table->string('code', 10)->primary();
                $table->string('name', 40)->nullable();
            });
        }

        if (!Schema::hasTable('area')) {
            Schema::create('area', function (Blueprint $table) {
                $table->string('code', 10)->primary();
                $table->string('name', 30)->nullable();
            });
        }

        if (!Schema::hasTable('pcard')) {
            Schema::create('pcard', function (Blueprint $table) {
                $table->string('code', 10)->primary();
                $table->string('name', 30)->nullable();
                $table->decimal('vadisc', 10, 2)->default(0);
                $table->decimal('totdisc', 10, 2)->default(0);
            });
        }

        if (!Schema::hasTable('clients_advanced')) {
            Schema::create('clients_advanced', function (Blueprint $table) {
                $table->string('code', 10)->primary();
                $table->string('pincode', 30)->nullable();
                $table->string('fax', 120)->nullable();
                $table->string('relationperiod', 30)->nullable();
                $table->string('relationway', 40)->nullable();
                $table->string('relationwayname', 120)->nullable();
                $table->string('relationbases', 40)->nullable();
                $table->string('relationbasesname', 120)->nullable();
                $table->string('purchasepurpose', 30)->nullable();
                $table->string('purchasecategory', 30)->nullable();
                $table->string('purchasetype', 30)->nullable();
                $table->string('working', 30)->nullable();
                $table->string('comment', 255)->nullable();
                $table->string('promptorder', 10)->nullable();
                $table->date('dtmarriage')->nullable();
                $table->date('dtengagement')->nullable();
                $table->date('dtbirthday')->nullable();
                $table->string('grateinform', 10)->nullable();
                $table->string('staffperf', 20)->nullable();
                $table->string('properf', 20)->nullable();
                $table->string('respperf', 20)->nullable();
                $table->string('speedperf', 20)->nullable();
                $table->string('cleanperf', 20)->nullable();
                $table->string('facilityperf', 20)->nullable();
                $table->string('parkfacilityperf', 20)->nullable();
                $table->string('contshop', 10)->nullable();
                $table->string('recommend', 10)->nullable();
                $table->string('area', 10)->nullable();
            });
        } else {
            Schema::table('clients_advanced', function (Blueprint $table) {
                if (!Schema::hasColumn('clients_advanced', 'area')) {
                    $table->string('area', 10)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('area');
        Schema::dropIfExists('route');
        Schema::dropIfExists('pcard');
    }
};

