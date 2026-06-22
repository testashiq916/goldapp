<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesmanCategoryReportController extends Controller
{
    private array $colCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.salesman-category', [
            'title' => 'Salesman Category Report',
            'date1' => date('Y-m-d'),
            'date2' => date('Y-m-d'),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')
                ->select('code', 'name')
                ->orderBy('code')
                ->get()
                ->map(fn ($row) => [
                    'code' => trim((string) $row->code),
                    'name' => trim((string) $row->name),
                ])
                ->values()
                ->all();
        }

        $byCode = [];
        foreach ($salesmen as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code !== '') {
                $byCode[$code] = $row;
            }
        }
        foreach ($this->legacySalesmanMap() as $row) {
            if (!isset($byCode[$row['code']])) {
                $byCode[$row['code']] = $row;
            }
        }
        ksort($byCode);

        return response()->json(['ok' => true, 'salesmen' => array_values($byCode)]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1 = (string) $request->query('date1', date('Y-m-d'));
        $date2 = (string) $request->query('date2', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('salesm') || !$this->hasTable('salesd') || !$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        DB::statement("SET SESSION sql_mode = (SELECT REPLACE(@@SESSION.sql_mode, 'ONLY_FULL_GROUP_BY', ''))");

        $smcode = trim((string) $request->query('smcode', ''));
        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        if ($rlevel <= 0) {
            $rlevel = 1;
        }

        $billType = "UPPER(TRIM(COALESCE(m.billtype,'')))";
        $billTypeName = $this->hasTable('salestype')
            ? "UPPER(TRIM(COALESCE(bt.name,'')))"
            : "''";
        $billPrefix = $this->hasTable('salestype')
            ? "UPPER(TRIM(COALESCE(bt.prefix,'')))"
            : "''";
        $billNo = "UPPER(TRIM(COALESCE(m.billno,'')))";
        $itemCode = "UPPER(TRIM(COALESCE(d.code,'')))";
        $itemName = "UPPER(TRIM(COALESCE(i.name,'')))";
        $itemGroup = "UPPER(TRIM(COALESCE(i.grpcode,'')))";
        $itemBillType = "UPPER(TRIM(COALESCE(i.billtype,'')))";
        $salesmanCode = $this->normalizedSalesmanCodeExpr();
        $salesmanName = $this->normalizedSalesmanNameExpr($salesmanCode);
        $diamondSaleAmount = $this->hasColumn('salesd', 'stoneprice')
            ? 'COALESCE(d.stoneprice,0)'
            : 'COALESCE(d.amount,0)';
        $diamondReturnAmount = $this->hasColumn('salesrd', 'stoneprice')
            ? 'COALESCE(d.stoneprice,0)'
            : 'COALESCE(d.amount,0)';

        $isB2b = "(($billType LIKE 'B2%') OR ($billTypeName LIKE '%B2B%') OR ($billPrefix LIKE 'B2B/%') OR ($billNo LIKE 'B2B/%'))";
        $is18k = "(($billType = 'G18') OR ($billType = 'B2B18') OR ($itemGroup = '18K') OR ($itemCode LIKE '18K%') OR ($itemName LIKE '18K%'))";
        $isDiamond = "(($billType IN ('D','B2D')) OR ($itemGroup = 'DM') OR ($itemBillType = 'D') OR ($itemCode LIKE 'DM%') OR ($itemName LIKE '%DIAMOND%') OR ($itemName LIKE '%DIMOND%'))";
        $isSilver = "(($billType IN ('S','B2BSIL')) OR ($itemGroup IN ('S','SIL','OS')) OR ($itemBillType = 'S') OR ($itemName LIKE '%SILVER%'))";
        $isPerfume = "(($billType IN ('P','B2BP')) OR ($itemGroup = 'PF') OR ($itemCode LIKE 'PF%') OR ($itemName LIKE '%PERFUME%'))";
        $isWatch = "(($billType IN ('W','B2BW')) OR ($itemGroup = 'WT') OR ($itemCode LIKE 'WT%') OR ($itemName LIKE '%WATCH%'))";
        $isGold = "((($billType IN ('G','GOLD1','B2G')) OR ($itemGroup = 'GO') OR ($itemBillType = 'G')) AND NOT $is18k AND NOT $isDiamond AND NOT $isSilver AND NOT $isPerfume AND NOT $isWatch)";
        $known = "($isGold OR $is18k OR $isDiamond OR $isSilver OR $isPerfume OR $isWatch)";
        $active = "COALESCE(m.status, 1) <> 0 AND UPPER(TRIM(COALESCE(m.status,''))) <> 'C'";

        $q = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->leftJoin('items as i', 'i.code', '=', 'd.code')
            ->leftJoin('sman as s', DB::raw('TRIM(s.code)'), '=', DB::raw($salesmanCode));

        if ($this->hasTable('salestype')) {
            $q->leftJoin('salestype as bt', 'bt.code', '=', 'm.billtype');
        }

        $q->selectRaw("$salesmanCode as smcode")
            ->selectRaw("$salesmanName as smname")
            ->selectRaw("CASE WHEN $isB2b THEN 'B2B' ELSE 'Regular' END as bill_section")
            ->selectRaw("COUNT(DISTINCT CASE WHEN $active THEN m.slno END) as bills")
            ->selectRaw("SUM(CASE WHEN $active AND $isGold THEN COALESCE(d.weight,0) ELSE 0 END) as gold_weight")
            ->selectRaw("0 as gold_return_weight")
            ->selectRaw("SUM(CASE WHEN $active AND $isDiamond THEN $diamondSaleAmount ELSE 0 END) as diamond_amount")
            ->selectRaw("0 as diamond_return_amount")
            ->selectRaw("SUM(CASE WHEN $active AND $is18k THEN COALESCE(d.weight,0) ELSE 0 END) as gold18_weight")
            ->selectRaw("0 as gold18_return_weight")
            ->selectRaw("SUM(CASE WHEN $active AND $isSilver THEN COALESCE(d.weight,0) ELSE 0 END) as silver_weight")
            ->selectRaw("0 as silver_return_weight")
            ->selectRaw("SUM(CASE WHEN $active AND $isPerfume THEN COALESCE(d.qty,0) ELSE 0 END) as perfume_qty")
            ->selectRaw("SUM(CASE WHEN $active AND $isPerfume THEN COALESCE(d.amount,0) ELSE 0 END) as perfume_amount")
            ->selectRaw("SUM(CASE WHEN $active AND $isWatch THEN COALESCE(d.qty,0) ELSE 0 END) as watch_qty")
            ->selectRaw("SUM(CASE WHEN $active AND $isWatch THEN COALESCE(d.amount,0) ELSE 0 END) as watch_amount")
            ->selectRaw("SUM(CASE WHEN $active AND NOT $known THEN COALESCE(d.amount,0) ELSE 0 END) as other_amount")
            ->selectRaw("SUM(CASE WHEN $active THEN COALESCE(d.amount,0) ELSE 0 END) as total_amount")
            ->whereBetween('m.tdate', [$date1, $date2])
            ->whereRaw('COALESCE(m.control, 1) <= ?', [$rlevel]);

        if ($smcode !== '') {
            $q->whereRaw("$salesmanCode = ?", [$smcode]);
        }

        $salesRows = $q->groupBy(DB::raw($salesmanCode), DB::raw("CASE WHEN $isB2b THEN 'B2B' ELSE 'Regular' END"))
            ->orderBy('smname')
            ->orderBy('bill_section')
            ->get()
            ->map(function ($row) {
                foreach (['gold_weight', 'gold_return_weight', 'gold_net_weight', 'diamond_amount', 'diamond_return_amount', 'diamond_net_amount', 'gold18_weight', 'gold18_return_weight', 'gold18_net_weight', 'silver_weight', 'silver_return_weight', 'silver_net_weight', 'perfume_qty', 'perfume_amount', 'watch_qty', 'watch_amount', 'other_amount', 'total_amount'] as $key) {
                    $row->{$key} = (float) ($row->{$key} ?? 0);
                }
                $row->bills = (int) ($row->bills ?? 0);
                $row->smcode = trim((string) ($row->smcode ?? ''));
                $row->smname = trim((string) ($row->smname ?? '')) ?: 'No Salesman';
                return $row;
            });

        $rowsByKey = [];
        $mergeRow = function ($row) use (&$rowsByKey) {
            $key = trim((string) ($row->smcode ?? '')) . '|' . trim((string) ($row->bill_section ?? ''));
            if (!isset($rowsByKey[$key])) {
                $rowsByKey[$key] = (object) [
                    'smcode' => trim((string) ($row->smcode ?? '')),
                    'smname' => trim((string) ($row->smname ?? '')) ?: 'No Salesman',
                    'bill_section' => trim((string) ($row->bill_section ?? 'Regular')) ?: 'Regular',
                    'bills' => 0,
                    'gold_weight' => 0.0,
                    'gold_return_weight' => 0.0,
                    'gold_net_weight' => 0.0,
                    'diamond_amount' => 0.0,
                    'diamond_return_amount' => 0.0,
                    'diamond_net_amount' => 0.0,
                    'gold18_weight' => 0.0,
                    'gold18_return_weight' => 0.0,
                    'gold18_net_weight' => 0.0,
                    'silver_weight' => 0.0,
                    'silver_return_weight' => 0.0,
                    'silver_net_weight' => 0.0,
                    'perfume_qty' => 0.0,
                    'perfume_amount' => 0.0,
                    'watch_qty' => 0.0,
                    'watch_amount' => 0.0,
                    'other_amount' => 0.0,
                    'total_amount' => 0.0,
                ];
            }

            $target = $rowsByKey[$key];
            $target->bills += (int) ($row->bills ?? 0);
            foreach (['gold_weight', 'gold_return_weight', 'diamond_amount', 'diamond_return_amount', 'gold18_weight', 'gold18_return_weight', 'silver_weight', 'silver_return_weight', 'perfume_qty', 'perfume_amount', 'watch_qty', 'watch_amount', 'other_amount', 'total_amount'] as $field) {
                $target->{$field} += (float) ($row->{$field} ?? 0);
            }
        };

        foreach ($salesRows as $row) {
            $mergeRow($row);
        }

        if ($this->hasTable('salesrm') && $this->hasTable('salesrd')) {
            $retActive = "$active AND UPPER(TRIM(COALESCE(m.sr,''))) = 'R'";
            $rq = DB::table('salesrd as d')
                ->join('salesrm as m', 'm.slno', '=', 'd.slno')
                ->leftJoin('items as i', 'i.code', '=', 'd.code')
                ->leftJoin('sman as s', DB::raw('TRIM(s.code)'), '=', DB::raw($salesmanCode));

            if ($this->hasTable('salestype')) {
                $rq->leftJoin('salestype as bt', 'bt.code', '=', 'm.billtype');
            }

            $rq->selectRaw("$salesmanCode as smcode")
                ->selectRaw("$salesmanName as smname")
                ->selectRaw("CASE WHEN $isB2b THEN 'B2B' ELSE 'Regular' END as bill_section")
                ->selectRaw("0 as bills")
                ->selectRaw("0 as gold_weight")
                ->selectRaw("SUM(CASE WHEN $retActive AND $isGold THEN COALESCE(d.weight,0) ELSE 0 END) as gold_return_weight")
                ->selectRaw("0 as diamond_amount")
                ->selectRaw("SUM(CASE WHEN $retActive AND $isDiamond THEN $diamondReturnAmount ELSE 0 END) as diamond_return_amount")
                ->selectRaw("0 as gold18_weight")
                ->selectRaw("SUM(CASE WHEN $retActive AND $is18k THEN COALESCE(d.weight,0) ELSE 0 END) as gold18_return_weight")
                ->selectRaw("0 as silver_weight")
                ->selectRaw("SUM(CASE WHEN $retActive AND $isSilver THEN COALESCE(d.weight,0) ELSE 0 END) as silver_return_weight")
                ->selectRaw("0 as perfume_qty")
                ->selectRaw("0 as perfume_amount")
                ->selectRaw("0 as watch_qty")
                ->selectRaw("0 as watch_amount")
                ->selectRaw("0 as other_amount")
                ->selectRaw("0 as total_amount")
                ->whereBetween('m.tdate', [$date1, $date2])
                ->whereRaw('COALESCE(m.control, 1) <= ?', [$rlevel]);

            if ($smcode !== '') {
                $rq->whereRaw("$salesmanCode = ?", [$smcode]);
            }

            $returnRows = $rq->groupBy(DB::raw($salesmanCode), DB::raw("CASE WHEN $isB2b THEN 'B2B' ELSE 'Regular' END"))
                ->get()
                ->map(function ($row) {
                    foreach (['gold_weight', 'gold_return_weight', 'diamond_amount', 'diamond_return_amount', 'gold18_weight', 'gold18_return_weight', 'silver_weight', 'silver_return_weight', 'perfume_qty', 'perfume_amount', 'watch_qty', 'watch_amount', 'other_amount', 'total_amount'] as $key) {
                        $row->{$key} = (float) ($row->{$key} ?? 0);
                    }
                    $row->bills = 0;
                    $row->smcode = trim((string) ($row->smcode ?? ''));
                    $row->smname = trim((string) ($row->smname ?? '')) ?: 'No Salesman';
                    return $row;
                });

            foreach ($returnRows as $row) {
                $mergeRow($row);
            }
        }

        $rows = collect(array_values($rowsByKey))
            ->map(function ($row) {
                $row->gold_net_weight = (float) $row->gold_weight - (float) $row->gold_return_weight;
                $row->diamond_net_amount = (float) $row->diamond_amount - (float) $row->diamond_return_amount;
                $row->gold18_net_weight = (float) $row->gold18_weight - (float) $row->gold18_return_weight;
                $row->silver_net_weight = (float) $row->silver_weight - (float) $row->silver_return_weight;
                return $row;
            })
            ->sortBy([
                ['smname', 'asc'],
                ['bill_section', 'asc'],
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'date1' => $date1,
            'date2' => $date2,
            'rows' => $rows,
            'totals' => $this->totals($rows),
        ]);
    }

    private function totals($rows): array
    {
        $totals = $this->emptyTotals();
        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] += (float) ($row->{$key} ?? 0);
            }
        }
        $totals['bills'] = (int) $totals['bills'];
        return $totals;
    }

    private function emptyTotals(): array
    {
        return [
            'bills' => 0,
            'gold_weight' => 0.0,
            'gold_return_weight' => 0.0,
            'gold_net_weight' => 0.0,
            'diamond_amount' => 0.0,
            'diamond_return_amount' => 0.0,
            'diamond_net_amount' => 0.0,
            'gold18_weight' => 0.0,
            'gold18_return_weight' => 0.0,
            'gold18_net_weight' => 0.0,
            'silver_weight' => 0.0,
            'silver_return_weight' => 0.0,
            'silver_net_weight' => 0.0,
            'perfume_qty' => 0.0,
            'perfume_amount' => 0.0,
            'watch_qty' => 0.0,
            'watch_amount' => 0.0,
            'other_amount' => 0.0,
            'total_amount' => 0.0,
        ];
    }

    protected function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $this->colCache)) {
            $this->colCache[$key] = $this->hasTable($table) && Schema::hasColumn($table, $column);
        }
        return $this->colCache[$key];
    }

    private function normalizedSalesmanCodeExpr(): string
    {
        $cases = [];
        foreach ($this->legacySalesmanMap() as $legacyCode => $row) {
            $cases[] = "WHEN '" . str_replace("'", "''", $legacyCode) . "' THEN '" . str_replace("'", "''", $row['code']) . "'";
        }

        return 'CASE TRIM(COALESCE(m.smcode,\'\')) ' . implode(' ', $cases) . " ELSE TRIM(COALESCE(m.smcode,'')) END";
    }

    private function normalizedSalesmanNameExpr(string $salesmanCode): string
    {
        $cases = [];
        foreach ($this->legacySalesmanMap() as $row) {
            $cases[] = "WHEN '" . str_replace("'", "''", $row['code']) . "' THEN '" . str_replace("'", "''", $row['name']) . "'";
        }

        return "COALESCE(MAX(NULLIF(TRIM(s.name),'')), CASE $salesmanCode " . implode(' ', $cases) . " ELSE NULL END, NULLIF($salesmanCode,''), 'No Salesman')";
    }

    private function legacySalesmanMap(): array
    {
        return [
            '1' => ['code' => 'ZKR', 'name' => 'SAKEER P'],
            '10' => ['code' => 'MAM', 'name' => 'THAMEEL'],
            '11' => ['code' => 'SHF', 'name' => 'SHAFEEJA'],
            '12' => ['code' => 'NHL', 'name' => 'NIHAL'],
            '2' => ['code' => 'RSD', 'name' => 'RASHEED'],
            '3' => ['code' => 'PNL', 'name' => 'NOUSHAD'],
            '4' => ['code' => 'AKR', 'name' => 'AKBAR'],
            '5' => ['code' => 'ABD', 'name' => 'ABDUL KADHER'],
            '6' => ['code' => 'SYJ', 'name' => 'SHAIJEL'],
            '7' => ['code' => 'SJS', 'name' => 'SAJEESH'],
            '8' => ['code' => 'RIN', 'name' => 'RINSHAD'],
            '9' => ['code' => 'MUB', 'name' => 'MUBASHIR'],
        ];
    }
}
