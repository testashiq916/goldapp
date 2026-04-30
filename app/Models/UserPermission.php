<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    protected $table = 'userd';
    public $timestamps = false;

    protected $fillable = ['code', 'menuitem'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserAccess::class, 'code', 'code');
    }
}
