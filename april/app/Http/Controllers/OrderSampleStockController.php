<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderSampleStockController extends Controller
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
        $totals = ['count' => 0, 'qty' => 0, 'weight' => 0.0];

        if ($showData && $this->hasTable('orderdmodel') && $this->hasTable('orderm')) {
            $rows = $this->loadData($dateFrom, $dateTo);
            $totals['count'] = count($rows);
            foreach ($rows as $r) {
                $totals['qty']    += (int) $r['qty'];
                $totals['weight'] += (float) $r['weight'];
            }
        }

        return view('order-report.sample-stock', compact(
            'dateFrom', 'dateTo', 'showData', 'rows', 'totals'
        ));
    }

    private function loadData(string $dateFrom, string $dateTo): array
    {
        $query = DB::table('orderdmodel')
            ->join('orderm', 'orderdmodel.slno', '=', 'orderm.slno')
            ->join('items', 'orderdmodel.code', '=', 'items.code')
            ->select([
                'orderm.tdate', 'orderm.ordno', 'orderm.custname',
                'orderm.status', 'orderm.salebill',
                'items.name as itemname', 'items.code as itemcode',
                'items.itype', 'items.ornament',
                'orderdmodel.qty', 'orderdmodel.weight', 'orderdmodel.part',
            ])
            ->where('orderm.control', '<=', $this->gilevel)
            ->whereBetween('orderm.tdate', [$dateFrom, $dateTo])
            ->orderBy('orderm.tdate')
            ->orderBy('orderm.ordno');

        $rows = $query->get()->map(fn ($r) => (array) $r)->toArray();

        // Compute refdate (sales date when order was returned)
        $hasSalesm = $this->hasTable('salesm');
        foreach ($rows as &$row) {
            $row['refdate'] = null;
            if ($hasSalesm && !empty($row['salebill'])) {
                $row['refdate'] = DB::table('salesm')
                    ->where('billno', $row['salebill'])
                    ->value('tdate');
            }
        }
        unset($row);

        return $rows;
    }
}
