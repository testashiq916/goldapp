<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderProcessController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));
        $showData = $request->has('show');

        $rows   = [];
        $totals = ['weight' => 0.0];

        if ($showData && $this->hasTable('orderm') && $this->hasTable('orderd')) {
            $rows = $this->loadData();
            foreach ($rows as $r) {
                $totals['weight'] += (float) $r['weight'];
            }
        }

        return view('order-report.process', compact('showData', 'rows', 'totals'));
    }

    private function loadData(): array
    {
        $orderdCols = array_map('strtolower', $this->columnList('orderd'));
        $ordermCols = array_map('strtolower', $this->columnList('orderm'));

        $select = [
            'orderm.ordno', 'orderm.tdate', 'orderm.duedate',
            'orderm.custname', 'orderm.custcode', 'orderm.smcode',
            'orderm.advance', 'orderm.eamt', 'orderm.sretamt',
            'orderm.slno',
            'orderd.code as itemcode', 'orderd.qty', 'orderd.weight',
            'orderd.stonewgt', 'orderd.sno',
            'items.name as itemname',
        ];

        if (in_array('smith', $orderdCols))     $select[] = 'orderd.smith';
        if (in_array('stage', $orderdCols))     $select[] = 'orderd.stage';
        if (in_array('part', $orderdCols))      $select[] = 'orderd.part';
        if (in_array('smithddate', $orderdCols)) $select[] = 'orderd.smithddate';
        if (in_array('iqtype', $orderdCols))    $select[] = 'orderd.iqtype';
        if (in_array('jewlcode', $ordermCols))  $select[] = 'orderm.jewlcode';
        if (in_array('addr', $ordermCols))      $select[] = 'orderm.addr';
        if (in_array('phone', $ordermCols))     $select[] = 'orderm.phone';

        $query = DB::table('orderm')
            ->join('orderd', 'orderm.slno', '=', 'orderd.slno')
            ->join('items', 'orderd.code', '=', 'items.code')
            ->select($select)
            ->where('orderm.control', '<=', $this->gilevel)
            ->where('orderm.status', 1)
            ->orderBy('orderm.slno');

        $rows = $query->get()->map(function ($r) {
            $row = (array) $r;
            $row['tadv'] = (float) ($row['advance'] ?? 0) + (float) ($row['eamt'] ?? 0) + (float) ($row['sretamt'] ?? 0);
            return $row;
        })->toArray();

        // Get mobile from clients
        $hasClients = $this->hasTable('clients');
        if ($hasClients) {
            $custcodes = array_unique(array_filter(array_column($rows, 'custcode')));
            if (!empty($custcodes)) {
                $mobiles = DB::table('clients')
                    ->whereIn('code', $custcodes)
                    ->pluck('mobile', 'code')
                    ->toArray();
                foreach ($rows as &$row) {
                    $row['mobile'] = $mobiles[$row['custcode']] ?? '';
                }
                unset($row);
            }
        }

        return $rows;
    }
}
