<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('salary_structure', function (Blueprint $table) {
            $table->id();
            $table->string('staff_code', 20);
            $table->decimal('basic_salary', 10, 2)->default(0);
            $table->decimal('hra', 10, 2)->default(0);
            $table->decimal('da', 10, 2)->default(0);
            $table->decimal('other_allowances', 10, 2)->default(0);
            $table->decimal('pf_percent', 5, 2)->default(12);  // % of basic
            $table->decimal('esi_percent', 5, 2)->default(0.75);
            $table->decimal('tds_monthly', 10, 2)->default(0);
            $table->date('effective_from');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('staff_code');
        });

        Schema::create('salary_register', function (Blueprint $table) {
            $table->id();
            $table->string('staff_code', 20);
            $table->string('month', 7); // YYYY-MM
            $table->unsignedTinyInteger('working_days')->default(26);
            $table->unsignedTinyInteger('days_present')->default(0);
            $table->decimal('basic_earned', 10, 2)->default(0);
            $table->decimal('hra_earned', 10, 2)->default(0);
            $table->decimal('da_earned', 10, 2)->default(0);
            $table->decimal('other_allowances', 10, 2)->default(0);
            $table->decimal('gross_salary', 10, 2)->default(0);
            $table->decimal('pf_deduction', 10, 2)->default(0);
            $table->decimal('esi_deduction', 10, 2)->default(0);
            $table->decimal('tds_deduction', 10, 2)->default(0);
            $table->decimal('advance_deduction', 10, 2)->default(0);
            $table->decimal('other_deductions', 10, 2)->default(0);
            $table->decimal('net_salary', 10, 2)->default(0);
            $table->date('paid_date')->nullable();
            $table->string('payment_mode', 20)->default('cash'); // cash, bank, upi
            $table->string('notes', 255)->nullable();
            $table->string('status', 20)->default('draft'); // draft, finalized, paid
            $table->timestamps();
            $table->unique(['staff_code', 'month']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_register');
        Schema::dropIfExists('salary_structure');
    }
};
