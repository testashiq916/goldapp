<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserAccess extends Model
{
    protected $table = 'userm';
    protected $primaryKey = 'code';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'pcode',
        'maxcredit',
        'maxdisc',
        'maxadjwgtbc',
        'minvaperc',
        'maxdiscperc',
    ];

    protected $casts = [
        'maxcredit' => 'decimal:2',
        'maxdisc' => 'decimal:2',
        'maxadjwgtbc' => 'decimal:3',
        'minvaperc' => 'decimal:3',
        'maxdiscperc' => 'decimal:2',
    ];

    protected $hidden = ['pcode'];

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'code', 'code');
    }

    public function permissionList(): array
    {
        return $this->permissions()
            ->pluck('menuitem')
            ->map(fn ($item) => trim((string) $item))
            ->all();
    }

    public function syncPermissions(array $items): void
    {
        $this->permissions()->delete();

        $rows = [];
        foreach ($items as $item) {
            $item = strtoupper(trim((string) $item));
            if ($item === '') {
                continue;
            }
            $rows[] = ['code' => $this->code, 'menuitem' => $item];
        }

        if ($rows !== []) {
            UserPermission::insert($rows);
        }
    }
}
