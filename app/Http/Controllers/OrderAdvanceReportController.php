<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderAdvanceReportController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $dateFrom    = $request->query('date1', date('Y-m-d'));
        $dateTo      = $request->query('date2', date('Y-m-d'));
        $filter      = $request->query('filter', 'All');
        $smcode      = trim($request->query('smcode', ''));
        $smFilter    = $request->has('sm_filter');
        $counterCode = trim($request->query('counter', ''));
        $counterFilter = $request->has('counter_filter');
        $showData    = $request->has('show');

        $rows    = [];
        $totals  = ['advance' => 0, 'eamt' => 0, 'sretamt' => 0, 'gadvance' => 0, 'advaft' => 0, 'total' => 0];

        $salesmen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray() : [];
        $counters = $this->hasTable('counter')
            ? DB::table('counter')->orderBy('name')->get(['code', 'name'])->toArray() : [];

        if ($showData && $this->hasTable('orderm')) {
            $rows = $this->loadData($dateFrom, $dateTo, $filter, $smcode, $smFilter, $counterCode, $counterFilter);
            foreach ($rows as $r) {
                $totals['advance']  += (float) $r['advance'];
                $totals['eamt']     += (float) $r['eamt'];
                $totals['sretamt']  += (float) $r['sretamt'];
                $totals['gadvance'] += (float) $r['gadvance'];
                $totals['advaft']   += (float) $r['advaft'];
                $totals['total']    += (float) $r['totadv'];
            }
        }

        return view('order-report.advance-report', compact(
            'dateFrom', 'dateTo', 'filter', 'smcode', 'smFilter',
            'counterCode', 'counterFilter', 'showData',
            'rows', 'totals', 'salesmen', 'counters'
        ));
    }

    private function loadData(string $dateFrom, string $dateTo, string $filter,
                              string $smcode, bool $smFilter,
                              string $counterCode, bool $counterFilter): array
    {
        $ordermCols = array_map('strtolower', $this->columnList('orderm'));
        $hasAdvafter = $this->hasTable('advafter');

        $query = DB::table('orderm')
            ->leftJoin('sman', 'orderm.smcode', '=', 'sman.code')
            ->select([
                'orderm.slno', 'orderm.ordno', 'orderm.tdate', 'orderm.duedate',
                'orderm.custname', 'orderm.custcode', 'orderm.smcode',
                'orderm.advance', 'orderm.eamt', 'orderm.sretamt',
                'orderm.gadvance', 'orderm.status', 'orderm.rate',
                'sman.name as smname',
            ])
            ->where('orderm.control', '<=', $this->gilevel)
            ->whereBetween('orderm.tdate', [$dateFrom, $dateTo])
            ->orderBy('orderm.tdate')
            ->orderBy('orderm.slno');

        if (in_array('counter', $ordermCols)) {
            $query->addSelect('orderm.counter');
        }

        if ($filter === 'Pending') {
            $query->where('orderm.status', 1);
        } elseif ($filter === 'Returned') {
            $query->where('orderm.status', 2);
        }

        if ($smFilter && $smcode !== '') {
            $query->where('orderm.smcode', $smcode);
        }

        if ($counterFilter && $counterCode !== '' && in_array('counter', $ordermCols)) {
            $query->where('orderm.counter', $counterCode);
        }

        $rows = $query->get()->map(fn ($r) => (array) $r)->toArray();

        // Add advafter totals
        foreach ($rows as &$row) {
            $row['advaft'] = 0;
            if ($hasAdvafter) {
                $row['advaft'] = (float) DB::table('advafter')
                    ->where('slno', $row['slno'])
                    ->sum('amount');
            }
            $row['totadv'] = (float) $row['advance'] + (float) $row['eamt']
                           + (float) $row['sretamt'] + $row['advaft'];
        }
        unset($row);

        return $rows;
    }
}
