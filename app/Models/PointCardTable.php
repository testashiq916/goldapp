<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointCardTable extends Model
{
    protected $table = 'pcardtable';
    public $timestamps = false;
    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'pcard',
        'isubgrp',
        'pointbasedon',
        'valuefor1point',
        'valueperpoint',
        'minsalesamt',
        'rounddown',
    ];
}

