<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('e_invoices')) {
            Schema::create('e_invoices', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('bill_no', 40)->index();
                $table->string('bill_type', 10)->default('S');
                $table->date('bill_date')->nullable();
                $table->string('customer_code', 20)->nullable();
                $table->string('customer_name', 150)->nullable();
                $table->string('gst_no', 20)->nullable();
                $table->decimal('net_total', 16, 2)->default(0);
                $table->string('status', 20)->default('generated');
                $table->string('irn', 120)->nullable();
                $table->string('ack_no', 80)->nullable();
                $table->string('ack_date', 80)->nullable();
                $table->longText('signed_qr_code')->nullable();
                $table->longText('request_payload')->nullable();
                $table->longText('response_payload')->nullable();
                $table->string('provider', 50)->nullable();
                $table->string('generated_by', 20)->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->string('cancel_reason', 255)->nullable();
                $table->string('cancel_remark', 255)->nullable();
                $table->string('cancelled_by', 20)->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->longText('cancel_response')->nullable();
                $table->timestamps();
                $table->unique(['bill_no', 'bill_type'], 'e_invoices_billno_billtype_unique');
            });
        }

        if (Schema::hasTable('sales_bills')) {
            Schema::table('sales_bills', function (Blueprint $table): void {
                if (!Schema::hasColumn('sales_bills', 'e_invoice_status')) {
                    $table->string('e_invoice_status', 30)->nullable()->after('status');
                }
                foreach ([
                    'irn'             => fn($t) => $t->string('irn', 120)->nullable(),
                    'ack_no'          => fn($t) => $t->string('ack_no', 80)->nullable(),
                    'ack_date'        => fn($t) => $t->string('ack_date', 80)->nullable(),
                    'signed_qr_code'  => fn($t) => $t->longText('signed_qr_code')->nullable(),
                    'e_invoice_response' => fn($t) => $t->longText('e_invoice_response')->nullable(),
                    'cancel_reason'   => fn($t) => $t->string('cancel_reason', 255)->nullable(),
                    'cancel_remark'   => fn($t) => $t->string('cancel_remark', 255)->nullable(),
                    'cancelled_at'    => fn($t) => $t->timestamp('cancelled_at')->nullable(),
                ] as $col => $fn) {
                    if (!Schema::hasColumn('sales_bills', $col)) {
                        $fn($table);
                    }
                }
            });
        }

        if (Schema::hasTable('salesm')) {
            $legacy = array_map('strtolower', Schema::getColumnListing('salesm'));
            $alters = [];
            $add = function (string $col, string $ddl) use ($legacy, &$alters): void {
                if (!in_array(strtolower($col), $legacy, true)) {
                    $alters[] = $ddl;
                }
            };
            $add('einvoice_status',   "ADD `einvoice_status` VARCHAR(30) NULL");
            $add('irn',               "ADD `irn` VARCHAR(120) NULL");
            $add('ackno',             "ADD `ackno` VARCHAR(80) NULL");
            $add('ackdt',             "ADD `ackdt` VARCHAR(80) NULL");
            $add('signedqrcode',      "ADD `signedqrcode` LONGTEXT NULL");
            $add('einvoice_response', "ADD `einvoice_response` LONGTEXT NULL");
            $add('cancel_reason',     "ADD `cancel_reason` VARCHAR(255) NULL");
            $add('cancel_remark',     "ADD `cancel_remark` VARCHAR(255) NULL");
            $add('cancelled_at',      "ADD `cancelled_at` DATETIME NULL");

            if ($alters !== []) {
                DB::statement('ALTER TABLE `salesm` ' . implode(', ', $alters));
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('e_invoices')) {
            Schema::drop('e_invoices');
        }

        if (Schema::hasTable('sales_bills')) {
            Schema::table('sales_bills', function (Blueprint $table): void {
                foreach (['cancelled_at', 'cancel_remark', 'cancel_reason'] as $col) {
                    if (Schema::hasColumn('sales_bills', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('salesm')) {
            $legacy = array_map('strtolower', Schema::getColumnListing('salesm'));
            $drops = [];
            foreach (['cancelled_at', 'cancel_remark', 'cancel_reason'] as $col) {
                if (in_array(strtolower($col), $legacy, true)) {
                    $drops[] = "DROP COLUMN `{$col}`";
                }
            }
            if ($drops !== []) {
                DB::statement('ALTER TABLE `salesm` ' . implode(', ', $drops));
            }
        }
    }
};
