<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DenominationMaster extends Model
{
    protected $table = 'denom_master';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'cvalue',
    ];
}

