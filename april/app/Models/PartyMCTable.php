<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PartyMCTable extends Model
{
    protected $table = 'pmctable';
    public $timestamps = false;

    protected $fillable = [
        'pcode',
        'icode',
        'model',
        'wastage',
        'mc',
        'mcperc',
        'mcperqty',
        'touch',
        'formula',
    ];

    protected $casts = [
        'wastage' => 'decimal:2',
        'mc' => 'decimal:2',
        'mcperc' => 'decimal:2',
        'mcperqty' => 'decimal:2',
        'touch' => 'decimal:2',
    ];

    public static function getByParty(string $pcode): array
    {
        return self::where('pcode', strtoupper(trim($pcode)))
            ->orderBy('icode')
            ->orderBy('model')
            ->get()
            ->toArray();
    }

    public static function bulkUpdateForParty(string $pcode, array $entries): void
    {
        $pcode = strtoupper(trim($pcode));

        DB::transaction(function () use ($pcode, $entries): void {
            self::where('pcode', $pcode)->delete();

            $hasCreatedAt = Schema::hasColumn('pmctable', 'created_at');
            $hasUpdatedAt = Schema::hasColumn('pmctable', 'updated_at');

            foreach ($entries as $entry) {
                $icode = strtoupper(trim((string) ($entry['icode'] ?? '')));
                if ($icode === '') {
                    continue;
                }

                $wastage = (float) ($entry['wastage'] ?? 0);
                $mc = (float) ($entry['mc'] ?? 0);
                $mcperc = (float) ($entry['mcperc'] ?? 0);
                $mcperqty = (float) ($entry['mcperqty'] ?? 0);
                $touch = (float) ($entry['touch'] ?? 0);

                $totalValue = $wastage + $mc + $mcperc + $mcperqty + $touch;
                if ($totalValue == 0.0) {
                    continue;
                }

                $row = [
                    'pcode' => $pcode,
                    'icode' => $icode,
                    'model' => strtoupper(trim((string) ($entry['model'] ?? ''))),
                    'wastage' => $wastage,
                    'mc' => $mc,
                    'mcperc' => $mcperc,
                    'mcperqty' => $mcperqty,
                    'touch' => $touch,
                    'formula' => trim((string) ($entry['formula'] ?? '')),
                ];

                if ($hasCreatedAt) {
                    $row['created_at'] = now();
                }
                if ($hasUpdatedAt) {
                    $row['updated_at'] = now();
                }

                DB::table('pmctable')->insert($row);
            }
        });
    }
}

