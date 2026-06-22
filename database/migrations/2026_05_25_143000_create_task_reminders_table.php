<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_reminders')) {
            return;
        }

        Schema::create('task_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('category', 40)->default('Other');
            $table->string('party_name', 160)->nullable();
            $table->string('reference_no', 80)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('due_date');
            $table->string('priority', 20)->default('Normal');
            $table->text('notes')->nullable();
            $table->boolean('is_done')->default(false);
            $table->string('created_by', 40)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['due_date', 'is_done']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reminders');
    }
};
