<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderProfitAnalysisController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $dateFrom = $request->query('date1', date('Y-m-d'));
        $dateTo   = $request->query('date2', date('Y-m-d'));
        $showData = $request->has('show');

        $rows   = [];
        $totals = ['count' => 0, 'diff' => 0.0];

        if ($showData && $this->hasTable('salesm')) {
            $rows = $this->loadData($dateFrom, $dateTo);
            $totals['count'] = count($rows);
            foreach ($rows as $r) {
                $totals['diff'] += $r['diff'];
            }
        }

        return view('order-report.profit-analysis', compact(
            'dateFrom', 'dateTo', 'showData', 'rows', 'totals'
        ));
    }

    private function loadData(string $dateFrom, string $dateTo): array
    {
        // Base query: salesm where orderno is not empty, with date & control filters
        $sales = DB::table('salesm')
            ->select([
                'salesm.slno', 'salesm.tdate', 'salesm.billno',
                'salesm.orderno', 'salesm.custname', 'salesm.billamt',
                'salesm.grate',
            ])
            ->where('salesm.control', '<=', $this->gilevel)
            ->whereBetween('salesm.tdate', [$dateFrom, $dateTo])
            ->where('salesm.orderno', '<>', '')
            ->whereNotNull('salesm.orderno')
            ->orderBy('salesm.tdate')
            ->orderBy('salesm.billno')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        $hasOrderm   = $this->hasTable('orderm');
        $hasAdvafter = $this->hasTable('advafter');
        $hasSalesd   = $this->hasTable('salesd');

        foreach ($sales as &$row) {
            $ordno = $row['orderno'];
            $slno  = $row['slno'];

            // advamt1: sum(advance + sretamt + eamt) from orderm
            $row['advamt1'] = 0;
            if ($hasOrderm && $ordno) {
                $row['advamt1'] = (float) DB::table('orderm')
                    ->where('ordno', $ordno)
                    ->selectRaw('COALESCE(SUM(advance + sretamt + eamt), 0) as v')
                    ->value('v');
            }

            // advamt2: sum(amount) from advafter
            $row['advamt2'] = 0;
            if ($hasAdvafter && $ordno) {
                $row['advamt2'] = (float) DB::table('advafter')
                    ->where('ordno', $ordno)
                    ->sum('amount');
            }

            // advwgt1: sum(gadvance + (advance + sretamt + eamt) / rate) from orderm
            $row['advwgt1'] = 0;
            if ($hasOrderm && $ordno) {
                $row['advwgt1'] = (float) DB::table('orderm')
                    ->where('ordno', $ordno)
                    ->where('rate', '>', 0)
                    ->selectRaw('COALESCE(SUM(gadvance + (advance + sretamt + eamt) / rate), 0) as v')
                    ->value('v');
            }

            // advwgt2: sum(amount / rate) from advafter
            $row['advwgt2'] = 0;
            if ($hasAdvafter && $ordno) {
                $row['advwgt2'] = (float) DB::table('advafter')
                    ->where('ordno', $ordno)
                    ->where('rate', '>', 0)
                    ->selectRaw('COALESCE(SUM(amount / rate), 0) as v')
                    ->value('v');
            }

            // soldwgt: sum(weight) from salesd
            $row['soldwgt'] = 0;
            if ($hasSalesd) {
                $row['soldwgt'] = (float) DB::table('salesd')
                    ->where('slno', $slno)->sum('weight');
            }

            // totva: sum(mcharge + stoneprice) from salesd
            $row['totva'] = 0;
            if ($hasSalesd) {
                $row['totva'] = (float) DB::table('salesd')
                    ->where('slno', $slno)
                    ->selectRaw('COALESCE(SUM(mcharge + stoneprice), 0) as v')
                    ->value('v');
            }

            // soldstwgt: sum(stonewgt) from salesd
            $row['soldstwgt'] = 0;
            if ($hasSalesd) {
                $row['soldstwgt'] = (float) DB::table('salesd')
                    ->where('slno', $slno)->sum('stonewgt');
            }

            // Computed fields
            $totAdvAmt  = $row['advamt1'] + $row['advamt2'];
            $totAdvWgt  = $row['advwgt1'] + $row['advwgt2'];
            $totSoldAmt = (float) $row['billamt'] - $row['totva'];
            $totSoldWgt = $row['soldwgt'] - $row['soldstwgt'];
            $grate      = (float) $row['grate'];

            $row['totadvamt']  = $totAdvAmt;
            $row['totadvwgt']  = $totAdvWgt;
            $row['totsoldamt'] = $totSoldAmt;
            $row['totsoldwgt'] = $totSoldWgt;
            // diff = (totsoldwgt - totadvwgt) * grate + totadvamt - totsoldamt
            $row['diff'] = ($totSoldWgt - $totAdvWgt) * $grate + $totAdvAmt - $totSoldAmt;
        }
        unset($row);

        return $sales;
    }
}
