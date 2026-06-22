<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderAdvanceAfterReportController extends Controller
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

        $rows = [];
        $totals = ['amount' => 0, 'wgt' => 0];

        $salesmen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray() : [];
        $counters = $this->hasTable('counter')
            ? DB::table('counter')->orderBy('name')->get(['code', 'name'])->toArray() : [];

        if ($showData && $this->hasTable('advafter')) {
            $rows = $this->loadData($dateFrom, $dateTo);
            foreach ($rows as $r) {
                $totals['amount'] += (float) $r['amount'];
                $totals['wgt']    += (float) $r['wgt'];
            }
        }

        return view('order-report.advance-after-report', compact(
            'dateFrom', 'dateTo', 'showData', 'rows', 'totals', 'salesmen', 'counters'
        ));
    }

    private function loadData(string $dateFrom, string $dateTo): array
    {
        // 1. Fetch Subsequent Advances (advafter)
        $subsequent = DB::table('advafter')
            ->leftJoin('orderm', 'advafter.slno', '=', 'orderm.slno')
            ->select([
                'advafter.tdate',
                'advafter.docno',
                'advafter.amount',
                'advafter.ordno',
                DB::raw("'Subsequent' as type"),
                'advafter.rate',
                'advafter.wgt',
                'orderm.custname',
                'orderm.custcode'
            ])
            ->whereBetween('advafter.tdate', [$dateFrom, $dateTo])
            ->where('advafter.control', '<=', $this->gilevel);

        // 2. Fetch Initial Advances (orderm)
        $initial = DB::table('orderm')
            ->select([
                'tdate',
                'ordno as docno',
                DB::raw("(advance + eamt + sretamt) as amount"),
                'ordno',
                DB::raw("'Initial' as type"),
                'rate',
                'gadvance as wgt',
                'custname',
                'custcode'
            ])
            ->whereBetween('tdate', [$dateFrom, $dateTo])
            ->where('control', '<=', $this->gilevel)
            ->where(function($q) {
                $q->where('advance', '>', 0)
                  ->orWhere('eamt', '>', 0)
                  ->orWhere('sretamt', '>', 0)
                  ->orWhere('gadvance', '>', 0);
            });

        // 3. Union and Sort
        $results = $subsequent->union($initial)
            ->orderBy('tdate')
            ->orderBy('docno')
            ->get();

        return $results->map(fn ($r) => (array) $r)->toArray();
    }
}
