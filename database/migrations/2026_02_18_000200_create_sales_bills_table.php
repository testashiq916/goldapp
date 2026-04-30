<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_no', 40)->unique();
            $table->date('bill_date')->nullable();
            $table->string('bill_time', 20)->nullable();
            $table->string('bill_type', 30)->default('Gold');
            $table->string('customer_name', 120)->nullable();
            $table->decimal('rate_per_gm', 14, 2)->default(0);
            $table->string('counter_name', 80)->nullable();
            $table->string('salesman_name', 120)->nullable();
            $table->decimal('bill_total', 14, 2)->default(0);
            $table->decimal('exchange_amount', 14, 2)->default(0);
            $table->decimal('return_amount', 14, 2)->default(0);
            $table->decimal('net_total', 14, 2)->default(0);
            $table->string('status', 20)->default('saved'); // saved|cancelled|confirmed
            $table->longText('items_json')->nullable();
            $table->longText('exchange_json')->nullable();
            $table->longText('return_json')->nullable();
            $table->longText('extra_json')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['bill_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_bills');
    }
};

