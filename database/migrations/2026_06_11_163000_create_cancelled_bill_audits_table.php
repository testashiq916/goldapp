<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cancelled_bill_audits')) {
            return;
        }

        Schema::create('cancelled_bill_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 30)->default('sales');
            $table->string('bill_no', 40)->index();
            $table->unsignedBigInteger('slno')->nullable()->index();
            $table->integer('control')->default(1);
            $table->date('bill_date')->nullable()->index();
            $table->string('bill_time', 20)->nullable();
            $table->string('customer_code', 30)->nullable()->index();
            $table->string('customer_name', 160)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('bill_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('gross_weight', 15, 3)->default(0);
            $table->decimal('qty', 15, 3)->default(0);
            $table->integer('item_count')->default(0);
            $table->string('reason', 255)->nullable();
            $table->string('cancelled_by', 30)->nullable();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancelled_bill_audits');
    }
};
