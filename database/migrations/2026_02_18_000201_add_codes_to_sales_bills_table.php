<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_bills', function (Blueprint $table) {
            $table->string('customer_code', 20)->nullable()->after('bill_type');
            $table->string('address', 255)->nullable()->after('customer_name');
            $table->string('mobile', 30)->nullable()->after('address');
            $table->string('gst_no', 40)->nullable()->after('mobile');
            $table->string('pan_no', 30)->nullable()->after('gst_no');
            $table->string('state_code', 20)->nullable()->after('pan_no');
            $table->string('salesman_code', 20)->nullable()->after('salesman_name');
            $table->string('counter_code', 20)->nullable()->after('counter_name');
            $table->string('agent_code', 20)->nullable()->after('counter_code');
            $table->string('approved_by', 20)->nullable()->after('agent_code');
            $table->string('cashbank_code', 20)->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('sales_bills', function (Blueprint $table) {
            $table->dropColumn([
                'customer_code', 'address', 'mobile', 'gst_no', 'pan_no',
                'state_code', 'salesman_code', 'counter_code', 'agent_code',
                'approved_by', 'cashbank_code',
            ]);
        });
    }
};
