<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemStock extends Model
{
    protected $table = 'itemsstk';

    protected $fillable = [
        'code',
        'stktype',
        'qty',
        'qtyb',
        'weight',
        'weightb',
        'stonewgt',
        'stonewgtb',
        'opqty',
        'opqtyb',
        'opweight',
        'opweightb',
        'opstonewgt',
        'opstonewgtb',
        'opstoneamt',
        'opstoneamtb',
        'opdmdwgt',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'code', 'code');
    }
}

