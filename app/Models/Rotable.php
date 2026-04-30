<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rotable extends Model
{
    protected $table = 'rotable';
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'code',
        'model',
        'size',
        'weight1',
        'weight2',
        'minqty',
        'maxqty',
    ];

    protected $casts = [
        'weight1' => 'decimal:3',
        'weight2' => 'decimal:3',
        'minqty' => 'integer',
        'maxqty' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'code', 'code');
    }
}

