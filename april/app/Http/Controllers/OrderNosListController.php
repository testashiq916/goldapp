<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderNosListController extends Controller
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

        if ($showData && $this->hasTable('orderm')) {
            $rows = $this->loadData($dateFrom, $dateTo);
        }

        return view('order-report.nos-list', compact(
            'dateFrom', 'dateTo', 'showData', 'rows'
        ));
    }

    private function loadData(string $dateFrom, string $dateTo): array
    {
        $ordermCols = array_map('strtolower', $this->columnList('orderm'));

        $select = [
            'orderm.ordno', 'orderm.tdate', 'orderm.duedate',
            'orderm.custname', 'orderm.addr', 'orderm.slno',
        ];
        if (in_array('duedate_org', $ordermCols)) $select[] = 'orderm.duedate_org';

        return DB::table('orderm')
            ->select($select)
            ->where('orderm.control', '<=', $this->gilevel)
            ->where('orderm.status', 1)
            ->whereBetween('orderm.tdate', [$dateFrom, $dateTo])
            ->orderBy('orderm.ordno')
            ->orderBy('orderm.slno')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }
}
