<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftTable extends Model
{
    protected $table = 'gifttable';
    protected $primaryKey = 'points';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = [
        'points',
        'particulars',
    ];
}

