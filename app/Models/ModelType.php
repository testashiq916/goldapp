<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelType extends Model
{
    protected $table = 'models';

    protected $fillable = [
        'mtype',
        'name',
    ];

    public static function getModels()
    {
        return self::where('mtype', 'M')->orderBy('name')->pluck('name');
    }

    public static function getSizes()
    {
        return self::where('mtype', 'S')->orderBy('name')->pluck('name');
    }
}

