<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MCTable extends Model
{
    protected $table = 'mctable';
    public $timestamps = false;
    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'code',
        'iqtype',
        'weight1',
        'weight2',
        'mc',
        'mcpergm',
        'mcperqty',
        'vaperc',
    ];

    public static function getByItemAndType(string $code, string $iqtype = ''): array
    {
        return DB::table('mctable')
            ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper(trim($code))])
            ->where('iqtype', trim($iqtype))
            ->orderBy('weight1')
            ->orderBy('weight2')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->toArray();
    }

    public static function bulkUpdateForItem(string $code, string $iqtype, array $entries): void
    {
        $code = strtoupper(trim($code));
        $iqtype = trim($iqtype);

        DB::transaction(function () use ($code, $iqtype, $entries): void {
            DB::table('mctable')
                ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                ->where('iqtype', $iqtype)
                ->delete();

            foreach ($entries as $entry) {
                $weight1 = (float) ($entry['weight1'] ?? 0);
                $weight2 = (float) ($entry['weight2'] ?? 0);

                if ($weight1 == 0.0 && $weight2 == 0.0) {
                    continue;
                }

                $row = [
                    'code' => $code,
                    'iqtype' => $iqtype,
                    'weight1' => $weight1,
                    'weight2' => $weight2,
                    'mc' => (float) ($entry['mc'] ?? 0),
                    'mcpergm' => (float) ($entry['mcpergm'] ?? 0),
                    'mcperqty' => (float) ($entry['mcperqty'] ?? 0),
                    'vaperc' => (float) ($entry['vaperc'] ?? 0),
                ];

                if (Schema::hasColumn('mctable', 'created_at')) {
                    $row['created_at'] = now();
                }
                if (Schema::hasColumn('mctable', 'updated_at')) {
                    $row['updated_at'] = now();
                }

                DB::table('mctable')->insert($row);
            }
        });
    }

    public static function deleteAllForItem(string $code): void
    {
        DB::table('mctable')
            ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper(trim($code))])
            ->delete();
    }

    public static function findMCForWeight(string $code, string $iqtype, float $weight): ?array
    {
        $row = DB::table('mctable')
            ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper(trim($code))])
            ->where('iqtype', trim($iqtype))
            ->where('weight1', '<=', $weight)
            ->where('weight2', '>=', $weight)
            ->orderBy('weight1')
            ->first();

        return $row ? (array) $row : null;
    }
}

