<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_log', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->default('whatsapp'); // whatsapp, sms, email
            $table->string('recipient', 30)->nullable();
            $table->string('recipient_name', 100)->nullable();
            $table->string('message_type', 60)->nullable(); // sale-bill, payment-due, repair-ready, etc.
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending'); // sent, failed, pending
            $table->string('provider', 50)->nullable();
            $table->text('api_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['channel', 'status']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_log');
    }
};
