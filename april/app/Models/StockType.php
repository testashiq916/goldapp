<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockType extends Model
{
    protected $table = 'stktype';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'def',
        'compare',
    ];

    protected $casts = [
        'def' => 'boolean',
        'compare' => 'boolean',
    ];

    public static function codeExists(string $code): bool
    {
        return self::where('code', strtoupper(trim($code)))->exists();
    }

    public static function getByCode(string $code): ?self
    {
        return self::where('code', strtoupper(trim($code)))->first();
    }

    public static function getDefault(): ?self
    {
        return self::where('def', 1)->first();
    }

    public function isInUse(): array
    {
        $code = (string) $this->code;
        $counts = [
            'itemsstk' => 0,
            'salesd' => 0,
            'salesrd' => 0,
            'purchased' => 0,
            'purchaserd' => 0,
            'smithd' => 0,
            'refineryd' => 0,
            'repaird' => 0,
            'itemadj' => 0,
        ];

        if (Schema::hasTable('itemsstk') && Schema::hasColumn('itemsstk', 'stktype')) {
            $q = DB::table('itemsstk')->where('stktype', $code);
            $hasQty = Schema::hasColumn('itemsstk', 'qty');
            $hasQtyB = Schema::hasColumn('itemsstk', 'qtyb');
            $hasW = Schema::hasColumn('itemsstk', 'weight');
            $hasWB = Schema::hasColumn('itemsstk', 'weightb');
            if ($hasQty || $hasQtyB || $hasW || $hasWB) {
                $q->where(function ($x) use ($hasQty, $hasQtyB, $hasW, $hasWB) {
                    if ($hasQty) {
                        $x->orWhere('qty', '<>', 0);
                    }
                    if ($hasQtyB) {
                        $x->orWhere('qtyb', '<>', 0);
                    }
                    if ($hasW) {
                        $x->orWhere('weight', '<>', 0);
                    }
                    if ($hasWB) {
                        $x->orWhere('weightb', '<>', 0);
                    }
                });
            }
            $counts['itemsstk'] = (int) $q->count();
        }

        foreach (['salesd', 'salesrd', 'purchased', 'purchaserd', 'smithd', 'refineryd', 'repaird'] as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'stktype')) {
                $counts[$tbl] = (int) DB::table($tbl)->where('stktype', $code)->count();
            }
        }

        if (Schema::hasTable('itemadj')) {
            $hasFrom = Schema::hasColumn('itemadj', 'fromstktype');
            $hasTo = Schema::hasColumn('itemadj', 'tostktype');
            if ($hasFrom || $hasTo) {
                $counts['itemadj'] = (int) DB::table('itemadj')
                    ->where(function ($q) use ($code, $hasFrom, $hasTo) {
                        if ($hasFrom) {
                            $q->orWhere('fromstktype', $code);
                        }
                        if ($hasTo) {
                            $q->orWhere('tostktype', $code);
                        }
                    })->count();
            }
        }

        $counts['total'] = array_sum($counts);
        $counts['itemsonly'] = $counts['itemsstk'];

        return $counts;
    }

    public function setAsDefault(): void
    {
        DB::transaction(function (): void {
            self::where('id', '!=', $this->id)->update(['def' => 0]);
            $this->update(['def' => 1]);
        });
    }

    public function deleteWithItems(): bool
    {
        DB::transaction(function (): void {
            if (Schema::hasTable('itemsstk') && Schema::hasColumn('itemsstk', 'stktype')) {
                DB::table('itemsstk')->where('stktype', $this->code)->delete();
            }
            $this->delete();
        });

        return true;
    }
}

