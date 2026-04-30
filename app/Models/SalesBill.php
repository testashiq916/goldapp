<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesBill extends Model
{
    protected $table = 'sales_bills';

    protected $fillable = [
        'bill_no',
        'bill_date',
        'bill_time',
        'bill_type',
        'customer_code',
        'customer_name',
        'address',
        'mobile',
        'gst_no',
        'pan_no',
        'state_code',
        'rate_per_gm',
        'counter_name',
        'counter_code',
        'agent_code',
        'approved_by',
        'cashbank_code',
        'salesman_name',
        'salesman_code',
        'bill_total',
        'exchange_amount',
        'return_amount',
        'net_total',
        'status',
        'items_json',
        'exchange_json',
        'return_json',
        'extra_json',
        'cancel_reason',
        'cancelled_at',
        'confirmed_at',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'bill_total' => 'decimal:2',
        'exchange_amount' => 'decimal:2',
        'return_amount' => 'decimal:2',
        'net_total' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];
}

