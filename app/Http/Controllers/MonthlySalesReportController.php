<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonthlySalesReportController extends Controller
{
    private array $colCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $rlevel = (int) $request->session()->get('gilevel', 1);
        if ($rlevel <= 0) $rlevel = 1;

        // Salesmen for dropdown
        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        // Default dates: 1st of current month → today
        $date1 = date('Y-m-01');
        $date2 = date('Y-m-d');

        return view('reports.monthly-sales', [
            'title' => 'Monthly Sales Report',
            'date1' => $date1,
            'date2' => $date2,
            'rlevel' => $rlevel,
            'salesmen' => $salesmen,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $date1 = (string) $request->query('date1', date('Y-m-01'));
        $date2 = (string) $request->query('date2', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        if ($rlevel <= 0) $rlevel = 1;

        $smcode = trim((string) $request->query('smcode', ''));
        $mode = (string) $request->query('mode', 'detail'); // detail | daysummary

        if ($mode === 'daysummary') {
            $rows = $this->daySummaryRows($date1, $date2, $rlevel, $smcode);
        } else {
            $rows = $this->detailRows($date1, $date2, $rlevel, $smcode);
        }

        return response()->json([
            'ok' => true,
            'rows' => $rows,
            'totals' => $this->buildTotals($rows, $mode),
        ]);
    }

    /* ── Detail mode (d_mnthsalereport) ────────────────────────────── */
    private function detailRows(string $d1, string $d2, int $rlevel, string $smcode): array
    {
        $q = DB::table('salesm')
            ->select('slno', 'tdate', 'billno', 'custcode', 'custname')
            ->selectRaw($this->colExpr('billamt', '0'))
            ->selectRaw($this->colExpr('eamt', '0'))
            ->selectRaw($this->colExpr('staxamt', '0'))
            ->selectRaw($this->colExpr('discount', '0'))
            ->selectRaw($this->colExpr('ramt', '0'))
            ->selectRaw($this->colExpr('sretamt', '0'))
            ->selectRaw($this->colExpr('ramtafter', '0'))
            ->selectRaw($this->colExpr('round', '0', 'round_amt'))
            ->selectRaw($this->colExpr('advance', '0'))
            ->selectRaw($this->colExpr('smcode', "''"))
            ->whereBetween('tdate', [$d1, $d2]);

        if ($this->hasCol('control')) {
            $q->where('control', '<=', $rlevel);
        }
        if ($this->hasCol('sr')) {
            $q->where('sr', 'S');
        }
        if ($this->hasCol('opbill')) {
            $q->where('opbill', '<>', 1);
        }
        if ($smcode !== '' && $this->hasCol('smcode')) {
            $q->where('smcode', $smcode);
        }

        return $q->orderBy('tdate')->orderBy('slno')->get()->map(function ($r) {
            $row = (array) $r;
            // PB balance: billamt + staxamt - eamt - sretamt - advance - discount + round - ramt - ramtafter
            $row['balance'] = (float)($row['billamt'] ?? 0)
                + (float)($row['staxamt'] ?? 0)
                - (float)($row['eamt'] ?? 0)
                - (float)($row['sretamt'] ?? 0)
                - (float)($row['advance'] ?? 0)
                - (float)($row['discount'] ?? 0)
                + (float)($row['round_amt'] ?? 0)
                - (float)($row['ramt'] ?? 0)
                - (float)($row['ramtafter'] ?? 0);
            // tramt = ramt (display field in PB)
            $row['tramt'] = (float)($row['ramt'] ?? 0);
            return $row;
        })->values()->all();
    }

    /* ── Day Summary mode (d_mnthsalereport_daysummary) ────────────── */
    private function daySummaryRows(string $d1, string $d2, int $rlevel, string $smcode): array
    {
        // Build the WHERE clause for sub-queries
        $baseWhere = "sm.tdate = salesm.tdate AND sm.control <= ?";
        $baseParams = [$rlevel];
        if ($smcode !== '' && $this->hasCol('smcode')) {
            $baseWhere .= " AND sm.smcode = ?";
            $baseParams[] = $smcode;
        }

        // Main query: DISTINCT tdate from salesm
        $q = DB::table('salesm')
            ->selectRaw('DISTINCT salesm.tdate');

        // Subquery sums matching PB exactly
        $sumCols = ['billamt', 'eamt', 'staxamt', 'discount', 'ramt', 'sretamt', 'round', 'advance'];
        foreach ($sumCols as $col) {
            if ($this->hasCol($col)) {
                $alias = $col === 'round' ? 'round_amt' : $col;
                $q->selectRaw("(SELECT COALESCE(SUM(sm.{$col}),0) FROM salesm sm WHERE {$baseWhere}) as {$alias}", $baseParams);
            } else {
                $alias = $col === 'round' ? 'round_amt' : $col;
                $q->selectRaw("0 as {$alias}");
            }
        }

        // Weight totals from salesd (sales weight)
        if ($this->hasTable('salesd') && $this->hasTable('items')) {
            $wgtWhere = str_replace('sm.tdate = salesm.tdate', 'sm2.tdate = salesm.tdate', $baseWhere);
            $wgtWhere = str_replace('sm.control', 'sm2.control', $wgtWhere);
            $wgtWhere = str_replace('sm.smcode', 'sm2.smcode', $wgtWhere);
            $q->selectRaw("(SELECT COALESCE(SUM(sd.weight),0) FROM salesd sd JOIN salesm sm2 ON sd.slno = sm2.slno WHERE {$wgtWhere}) as totwgt", $baseParams);
        } else {
            $q->selectRaw('0 as totwgt');
        }

        // Exchange weight from purchased
        if ($this->hasTable('purchased')) {
            $exWhere = str_replace('sm.tdate = salesm.tdate', 'sm3.tdate = salesm.tdate', $baseWhere);
            $exWhere = str_replace('sm.control', 'sm3.control', $exWhere);
            $exWhere = str_replace('sm.smcode', 'sm3.smcode', $exWhere);
            $q->selectRaw("(SELECT COALESCE(SUM(pd.weight),0) FROM purchased pd JOIN salesm sm3 ON pd.slno = sm3.slno WHERE {$exWhere}) as totexwgt", $baseParams);
        } else {
            $q->selectRaw('0 as totexwgt');
        }

        // Return weight from salesrd
        if ($this->hasTable('salesrd') && $this->hasTable('salesrm')) {
            $retWhere = str_replace('sm.tdate = salesm.tdate', 'srm.tdate = salesm.tdate', $baseWhere);
            $retWhere = str_replace('sm.control', 'srm.control', $retWhere);
            $retWhere = str_replace('sm.smcode', 'srm.smcode', $retWhere);
            $q->selectRaw("(SELECT COALESCE(SUM(srd.weight),0) FROM salesrd srd JOIN salesrm srm ON srd.slno = srm.slno WHERE {$retWhere}) as totretwgt", $baseParams);
        } else {
            $q->selectRaw('0 as totretwgt');
        }

        $q->whereBetween('salesm.tdate', [$d1, $d2]);
        if ($this->hasCol('control')) {
            $q->where('salesm.control', '<=', $rlevel);
        }
        if ($this->hasCol('opbill')) {
            $q->where('salesm.opbill', '<>', 1);
        }

        return $q->orderBy('salesm.tdate')->get()->map(function ($r) {
            $row = (array) $r;
            $row['balance'] = (float)($row['billamt'] ?? 0)
                + (float)($row['staxamt'] ?? 0)
                - (float)($row['eamt'] ?? 0)
                - (float)($row['sretamt'] ?? 0)
                - (float)($row['advance'] ?? 0)
                - (float)($row['discount'] ?? 0)
                + (float)($row['round_amt'] ?? 0)
                - (float)($row['ramt'] ?? 0);
            $row['tramt'] = (float)($row['ramt'] ?? 0);
            return $row;
        })->values()->all();
    }

    /* ── Totals ────────────────────────────────────────────────────── */
    private function buildTotals(array $rows, string $mode): array
    {
        $keys = ['billamt', 'eamt', 'sretamt', 'discount', 'tramt', 'balance'];
        if ($mode === 'daysummary') {
            $keys = array_merge($keys, ['totwgt', 'totexwgt', 'totretwgt']);
        }
        $totals = array_fill_keys($keys, 0.0);
        foreach ($rows as $row) {
            foreach ($keys as $k) {
                $totals[$k] += (float)($row[$k] ?? 0);
            }
        }
        $totals['count'] = count($rows);
        return $totals;
    }

    /* ── Column helpers ────────────────────────────────────────────── */
    private function hasCol(string $col): bool
    {
        if (!isset($this->colCache[$col])) {
            $this->colCache[$col] = Schema::hasColumn('salesm', $col);
        }
        return $this->colCache[$col];
    }

    private function colExpr(string $col, string $fallback, ?string $alias = null): string
    {
        $as = $alias ?? $col;
        return $this->hasCol($col) ? "{$col} as {$as}" : "{$fallback} as {$as}";
    }
}
