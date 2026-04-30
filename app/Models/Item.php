<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'items';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $item): void {
            Rotable::where('code', $item->code)->delete();
        });
    }

    public function reorderLevels()
    {
        return $this->hasMany(Rotable::class, 'code', 'code');
    }

    public function stockByType()
    {
        return $this->hasMany(ItemStock::class, 'code', 'code');
    }
}
