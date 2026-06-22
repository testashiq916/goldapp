<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales_bills')) {
            return;
        }

        Schema::table('sales_bills', function (Blueprint $table): void {
            if (!Schema::hasColumn('sales_bills', 'e_invoice_status')) {
                $table->string('e_invoice_status', 30)->nullable()->after('status');
            }
            if (!Schema::hasColumn('sales_bills', 'irn')) {
                $table->string('irn', 120)->nullable();
            }
            if (!Schema::hasColumn('sales_bills', 'ack_no')) {
                $table->string('ack_no', 80)->nullable();
            }
            if (!Schema::hasColumn('sales_bills', 'ack_date')) {
                $table->string('ack_date', 80)->nullable();
            }
            if (!Schema::hasColumn('sales_bills', 'signed_qr_code')) {
                $table->longText('signed_qr_code')->nullable();
            }
            if (!Schema::hasColumn('sales_bills', 'e_invoice_response')) {
                $table->longText('e_invoice_response')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales_bills')) {
            return;
        }

        Schema::table('sales_bills', function (Blueprint $table): void {
            foreach (['e_invoice_response', 'signed_qr_code', 'ack_date', 'ack_no', 'irn', 'e_invoice_status'] as $column) {
                if (Schema::hasColumn('sales_bills', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
