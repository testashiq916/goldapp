<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductModel extends Model
{
    protected $table = 'models';
    public $timestamps = false;

    protected $fillable = [
        'mtype',
        'name',
    ];

    public static function getByType(string $type)
    {
        return self::where('mtype', strtoupper(trim($type)))
            ->orderBy('name')
            ->get();
    }

    public function isUsedInPmcTable(): bool
    {
        if (!Schema::hasTable('pmctable')) {
            return false;
        }

        $cols = array_map('strtolower', Schema::getColumnListing('pmctable'));
        $field = in_array('model', $cols, true) ? 'model' : (in_array('name', $cols, true) ? 'name' : null);
        if ($field === null) {
            return false;
        }

        return DB::table('pmctable')->where($field, $this->name)->exists();
    }

    public static function bulkUpdateByType(string $type, array $names): void
    {
        $type = strtoupper(trim($type));
        self::where('mtype', $type)->delete();

        $hasCreatedAt = Schema::hasColumn('models', 'created_at');
        $hasUpdatedAt = Schema::hasColumn('models', 'updated_at');

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $row = [
                'mtype' => $type,
                'name' => strtoupper($name),
            ];

            if ($hasCreatedAt) {
                $row['created_at'] = now();
            }
            if ($hasUpdatedAt) {
                $row['updated_at'] = now();
            }

            DB::table('models')->insert($row);
        }
    }
}
