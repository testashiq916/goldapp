<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('address', 255)->nullable();
            $table->string('city', 60)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('gstin', 20)->nullable();
            $table->string('db_host', 100)->nullable();     // for separate DB per branch
            $table->string('db_name', 60)->nullable();
            $table->string('db_user', 60)->nullable();
            $table->string('db_pass', 100)->nullable();
            $table->boolean('is_head_office')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inter_branch_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no', 20)->unique();
            $table->date('transfer_date');
            $table->string('from_branch', 20);
            $table->string('to_branch', 20);
            $table->string('item_code', 30)->nullable();
            $table->string('barcode', 30)->nullable();
            $table->string('item_desc', 100)->nullable();
            $table->decimal('weight', 10, 3)->default(0);
            $table->decimal('purity', 5, 3)->default(0);
            $table->decimal('fine_weight', 10, 3)->default(0);
            $table->decimal('rate', 10, 2)->default(0);
            $table->decimal('value', 10, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending, received, cancelled
            $table->date('received_date')->nullable();
            $table->string('notes', 255)->nullable();
            $table->string('created_by', 30)->nullable();
            $table->timestamps();
            $table->index(['from_branch', 'to_branch']);
            $table->index('transfer_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inter_branch_transfers');
        Schema::dropIfExists('branches');
    }
};
