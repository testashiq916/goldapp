<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cash Flow Statement (PB: w_cashflowreport / d_cashflowreport)
 *
 * Daily cash inflow/outflow summary for the CASH account.
 * Per date: total debited (inflow), total credited (outflow), cash in hand.
 * Opening balance = accountm.opbal(CASH) + sum(daybook before date1).
 */
class CashFlowReportController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        // PB defaults: first of current month to today
        $date1 = date('Y-m-01');
        $date2 = date('Y-m-d');

        return view('reports.cash-flow-report', [
            'title'  => 'Cash Flow Statement',
            'date1'  => $date1,
            'date2'  => $date2,
            'rlevel' => (int) ($request->session()->get('gilevel', 1) ?: 1),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1  = (string) $request->query('date1', date('Y-m-01'));
        $date2  = (string) $request->query('date2', date('Y-m-d'));
        $rlevel = (int) $request->query('rlevel', (int) ($request->session()->get('gilevel', 1)));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        $accode = 'CASH';

        if (!$this->hasTable('daybook')) {
            return response()->json(['ok' => true, 'rows' => [], 'openingBal' => 0, 'totals' => []]);
        }

        // ── 1. Opening balance = accountm.opbal + sum(daybook before date1) ──
        $opBal = 0;
        if ($this->hasTable('accountm')) {
            $acCols = array_map('strtolower', $this->columnList('accountm'));
            $opCol  = ($rlevel >= 2 && in_array('opbalb', $acCols)) ? 'opbalb' : 'opbal';

            $acct = DB::table('accountm')
                ->where('accode', $accode)
                ->select(DB::raw("COALESCE({$opCol}, 0) as opbal"))
                ->first();

            if ($acct) {
                $opBal = (float) $acct->opbal;
            }
        }

        $priorAmt = (float) DB::table('daybook')
            ->where('accode', $accode)
            ->where('tdate', '<', $date1)
            ->where('control', '<=', $rlevel)
            ->sum('amount');

        $openingBal = round($opBal + $priorAmt, 2);

        // ── 2. Daily summary: distinct dates with inflow/outflow ──
        // PB query uses correlated subqueries; we use group by for efficiency
        $results = DB::table('daybook')
            ->where('accode', $accode)
            ->whereBetween('tdate', [$date1, $date2])
            ->where('control', '<=', $rlevel)
            ->groupBy('tdate')
            ->orderBy('tdate')
            ->select([
                'tdate',
                DB::raw("COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as tdebitamt"),
                DB::raw("COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as tcreditamt"),
                DB::raw("COALESCE(SUM(amount), 0) as dayamt"),
            ])
            ->get();

        $rows = [];
        $runBal    = $openingBal;
        $totInflow  = 0;
        $totOutflow = 0;

        foreach ($results as $r) {
            $inflow  = round((float) $r->tdebitamt, 2);   // debits = money coming in (negative amounts)
            $outflow = round((float) $r->tcreditamt, 2);   // credits = money going out (positive amounts)
            $dayamt  = round((float) $r->dayamt, 2);

            $runBal += $dayamt;
            // PB: bal = -(ropbal + tamt) — but ropbal is negative of our openingBal convention
            // In PB, cash inflow is negative amount, cash in hand = -(opbal + cumsum)
            // We compute cash in hand as running balance (positive = cash available)
            $cashInHand = round(-($opBal + $this->getCumulativeSum($accode, $r->tdate, $rlevel)), 2);

            $totInflow  += $inflow;
            $totOutflow += $outflow;

            $rows[] = [
                'tdate'      => $r->tdate,
                'inflow'     => $inflow,
                'outflow'    => $outflow,
                'cashInHand' => $cashInHand,
            ];
        }

        return response()->json([
            'ok'         => true,
            'openingBal' => $openingBal,
            'rows'       => $rows,
            'totals'     => [
                'count'   => count($rows),
                'inflow'  => round($totInflow, 2),
                'outflow' => round($totOutflow, 2),
            ],
        ]);
    }

    /**
     * Get cumulative sum of daybook amounts for CASH up to a given date.
     */
    private function getCumulativeSum(string $accode, string $date, int $rlevel): float
    {
        return (float) DB::table('daybook')
            ->where('accode', $accode)
            ->where('tdate', '<=', $date)
            ->where('control', '<=', $rlevel)
            ->sum('amount');
    }
}
