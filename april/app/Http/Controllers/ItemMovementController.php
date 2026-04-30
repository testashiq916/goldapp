<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ItemMovementController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $rlevel = (int) $request->session()->get('gilevel', 1);
        if ($rlevel <= 0) $rlevel = 1;

        return view('item-reports.item-movement', [
            'title'    => (string) $request->query('title', 'Item Movement'),
            'moduleId' => (string) $request->query('module', 'item-reports-item-movement'),
            'rlevel'   => $rlevel,
            'date1'    => date('Y-m-01'),
            'date2'    => date('Y-m-d'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        if (!$this->hasTable('salesm') || !$this->hasTable('salesd') || !$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $date1 = (string) $request->query('date1', date('Y-m-01'));
        $date2 = (string) $request->query('date2', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        $rlevel     = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        if ($rlevel <= 0) $rlevel = 1;
        $itemCode   = strtoupper(trim((string) $request->query('item_code', '')));
        $itemType   = strtoupper(substr(trim((string) $request->query('item_type', 'Gold')), 0, 1)); // G/S/O
        $graphType  = (string) $request->query('graph_type', 'itemwise');   // itemwise | monthly
        $valueType  = (string) $request->query('value_type', 'weight');     // weight | qty | amt

        $hasControl = Schema::hasColumn('salesm', 'control');
        $hasItype   = Schema::hasColumn('items', 'itype');
        $hasAmount  = Schema::hasColumn('salesd', 'amount');
        $hasSr      = Schema::hasColumn('salesm', 'sr');
        $hasOpbill  = Schema::hasColumn('salesm', 'opbill');

        $q = DB::table('items as i')
            ->join('salesd as sd', 'sd.code', '=', 'i.code')
            ->join('salesm as sm', 'sm.slno', '=', 'sd.slno')
            ->whereBetween('sm.tdate', [$date1, $date2]);

        if ($hasControl) {
            $q->where('sm.control', '<=', $rlevel);
        }
        if ($hasSr) {
            $q->where('sm.sr', 'S');
        }
        if ($hasOpbill) {
            $q->where('sm.opbill', '<>', 1);
        }
        if ($hasItype && $itemType !== '') {
            $q->where(DB::raw('UPPER(LEFT(TRIM(i.itype),1))'), $itemType);
        }
        if ($itemCode !== '') {
            $q->where(DB::raw('UPPER(TRIM(i.code))'), $itemCode);
        }

        if ($graphType === 'monthly') {
            $q->selectRaw('i.code as icode, i.name as iname, MONTH(sm.tdate) as mnth, YEAR(sm.tdate) as yr')
              ->selectRaw('SUM(sd.qty) as tot_qty')
              ->selectRaw('SUM(sd.weight) as tot_wgt')
              ->selectRaw($hasAmount ? 'SUM(sd.amount) as tot_amt' : '0 as tot_amt')
              ->groupByRaw('i.code, i.name, YEAR(sm.tdate), MONTH(sm.tdate)')
              ->orderByRaw('YEAR(sm.tdate), MONTH(sm.tdate), i.name');
        } else {
            // Item Wise
            $q->selectRaw('i.code as icode, i.name as iname')
              ->selectRaw('SUM(sd.qty) as tot_qty')
              ->selectRaw('SUM(sd.weight) as tot_wgt')
              ->selectRaw($hasAmount ? 'SUM(sd.amount) as tot_amt' : '0 as tot_amt')
              ->groupByRaw('i.code, i.name')
              ->orderBy('i.name');
        }

        $rows = $q->get()->map(function ($r) {
            $row = (array) $r;
            $row['tot_qty'] = (int) ($row['tot_qty'] ?? 0);
            $row['tot_wgt'] = round((float) ($row['tot_wgt'] ?? 0), 3);
            $row['tot_amt'] = round((float) ($row['tot_amt'] ?? 0), 2);
            return $row;
        })->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows, 'value_type' => $valueType, 'graph_type' => $graphType]);
    }
}
