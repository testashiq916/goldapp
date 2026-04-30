<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderPendingRegisterController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $showData    = $request->has('show');
        $smcode      = trim($request->query('smcode', ''));
        $smFilter    = $request->has('sm_filter');
        $smWise      = $request->has('sm_wise');
        $itemCode    = strtoupper(trim($request->query('item_code', '')));
        $itemType    = $request->query('item_type', 'All');
        $custcode    = trim($request->query('custcode', ''));
        $counterCode = trim($request->query('counter', ''));
        $counterFilter = $request->has('counter_filter');
        $duedateBased  = $request->has('duedate_based');
        $dateFrom    = $request->query('date1', date('Y-m-d'));
        $dateTo      = $request->query('date2', date('Y-m-d'));
        $sortBy      = $request->query('sort', 'duedate');

        $rows     = [];
        $smGroups = [];
        $totals   = ['qty' => 0, 'weight' => 0.0, 'advance' => 0.0];

        // Lookups for dropdowns
        $salesmen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray() : [];
        $counters = $this->hasTable('counter')
            ? DB::table('counter')->orderBy('name')->get(['code', 'name'])->toArray() : [];

        if ($showData && $this->hasTable('orderm') && $this->hasTable('orderd')) {
            $rows = $this->loadData(
                $smcode, $smFilter, $itemCode, $itemType,
                $custcode, $counterCode, $counterFilter,
                $duedateBased, $dateFrom, $dateTo, $sortBy
            );

            // Compute totals
            foreach ($rows as $r) {
                $totals['qty']     += (int) $r['qty'];
                $totals['weight']  += (float) $r['weight'];
                $totals['advance'] += (float) $r['advance'];
            }

            // Group by SM if requested
            if ($smWise) {
                $smGroups = $this->groupBySm($rows);
            }
        }

        return view('order-report.pending-register', compact(
            'showData', 'smcode', 'smFilter', 'smWise',
            'itemCode', 'itemType', 'custcode',
            'counterCode', 'counterFilter',
            'duedateBased', 'dateFrom', 'dateTo', 'sortBy',
            'rows', 'smGroups', 'totals',
            'salesmen', 'counters'
        ));
    }

    private function loadData(
        string $smcode, bool $smFilter, string $itemCode, string $itemType,
        string $custcode, string $counterCode, bool $counterFilter,
        bool $duedateBased, string $dateFrom, string $dateTo, string $sortBy
    ): array {
        $query = DB::table('orderm')
            ->join('orderd', 'orderm.slno', '=', 'orderd.slno')
            ->join('items', 'orderd.code', '=', 'items.code')
            ->leftJoin('sman', 'orderm.smcode', '=', 'sman.code')
            ->select([
                'orderm.ordno', 'orderm.tdate', 'orderm.duedate',
                'orderm.custname', 'orderm.custcode', 'orderm.addr',
                'orderm.smcode', 'orderm.advance', 'orderm.eamt',
                'orderm.sretamt', 'orderm.slno', 'orderm.phone',
                'items.name as itemname', 'orderd.code as itemcode',
                'orderd.qty', 'orderd.weight',
                'orderd.stonewgt', 'orderd.stoneprice',
                'orderd.part', 'orderd.iqtype',
                'orderd.amount as orderd_amount',
                'sman.name as smname',
            ])
            ->where('orderm.control', '<=', $this->gilevel)
            ->where('orderm.status', 1);

        // Optional columns
        $orderdCols = array_map('strtolower', $this->columnList('orderd'));
        if (in_array('smith', $orderdCols)) {
            $query->addSelect('orderd.smith');
        }
        if (in_array('stage', $orderdCols)) {
            $query->addSelect('orderd.stage');
        }

        $ordermCols = array_map('strtolower', $this->columnList('orderm'));
        if (in_array('counter', $ordermCols)) {
            $query->addSelect('orderm.counter');
        }

        $itemsCols = array_map('strtolower', $this->columnList('items'));
        if (in_array('dmdplt', $itemsCols)) {
            $query->addSelect('items.dmdplt');
        }

        // Filters
        if ($smFilter && $smcode !== '') {
            $query->where('orderm.smcode', $smcode);
        }

        if ($itemCode !== '') {
            $query->where('orderd.code', $itemCode);
        }

        if ($custcode !== '') {
            $query->where('orderm.custcode', $custcode);
        }

        if ($counterFilter && $counterCode !== '' && in_array('counter', $ordermCols)) {
            $query->where('orderm.counter', $counterCode);
        }

        // Item type filter
        if ($itemType !== 'All' && in_array('dmdplt', $itemsCols)) {
            $typeMap = [
                'Diamond'     => 'D',
                'Platinum'    => 'P',
                'Gold'        => 'G',
                'Silver'      => 'S',
                'Others'      => 'O',
                'Color Stone' => 'C',
                'Watch'       => 'W',
            ];
            if (isset($typeMap[$itemType])) {
                $query->where('items.dmdplt', $typeMap[$itemType]);
            }
        }

        // Date filter (due-date based)
        if ($duedateBased) {
            $query->whereBetween('orderm.duedate', [$dateFrom, $dateTo]);
        }

        // Sort
        switch ($sortBy) {
            case 'ordno':
                $query->orderBy('orderm.ordno')->orderBy('orderm.slno');
                break;
            case 'customer':
                $query->orderBy('orderm.custname')->orderBy('orderm.slno');
                break;
            case 'item':
                $query->orderBy('items.name')->orderBy('orderm.slno');
                break;
            default: // duedate
                $query->orderBy('orderm.duedate')->orderBy('orderm.slno');
        }

        $results = $query->get()->map(function ($r) {
            $row = (array) $r;
            $row['advance'] = (float) $row['advance'] + (float) $row['eamt'] + (float) $row['sretamt'];
            return $row;
        })->toArray();

        return $results;
    }

    private function groupBySm(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $sm = $row['smcode'] ?? '';
            if (!isset($groups[$sm])) {
                $groups[$sm] = [
                    'smname' => $row['smname'] ?? $sm,
                    'rows'   => [],
                    'totqty' => 0,
                    'totwgt' => 0.0,
                    'totadv' => 0.0,
                ];
            }
            $groups[$sm]['rows'][] = $row;
            $groups[$sm]['totqty'] += (int) $row['qty'];
            $groups[$sm]['totwgt'] += (float) $row['weight'];
            $groups[$sm]['totadv'] += (float) $row['advance'];
        }
        return $groups;
    }
}
