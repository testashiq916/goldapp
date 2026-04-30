<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarcodeListController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $dateFrom  = $request->query('date1', date('Y-m-d'));
        $dateTo    = $request->query('date2', date('Y-m-d'));
        $itemCode  = strtoupper(trim($request->query('item_code', '')));
        $docNo     = strtoupper(trim($request->query('docno', '')));
        $bcNo      = trim($request->query('bcno', ''));
        $stkType   = $request->query('stk_type', 'All');
        $itype     = $request->query('itype', 'All');
        $grpCode   = trim($request->query('grp', ''));
        $subGrp    = trim($request->query('subgrp', ''));
        $model     = strtoupper(trim($request->query('model', '')));
        $showData  = $request->has('show');

        $rows    = [];
        $totals  = [
            'count' => 0, 'qty' => 0, 'weight' => 0.0, 'stweight' => 0.0,
            'stprice' => 0.0, 'dmdwgt' => 0.0, 'dmdamt' => 0.0, 'rate' => 0.0,
        ];

        $groupCodes = [];
        if ($this->hasTable('items')) {
            $groupCodes = DB::table('items')
                ->whereNotNull('grpcode')
                ->where('grpcode', '<>', '')
                ->distinct()
                ->orderBy('grpcode')
                ->pluck('grpcode')
                ->toArray();
        }

        if ($showData && $this->hasTable('barcode')) {
            $rows = $this->loadData(
                $dateFrom, $dateTo, $itemCode, $docNo, $bcNo,
                $stkType, $itype, $grpCode, $subGrp, $model
            );
            $totals['count'] = count($rows);
            foreach ($rows as $r) {
                $totals['qty']      += (int) $r['qty'];
                $totals['weight']   += (float) $r['weight'];
                $totals['stweight'] += (float) $r['stweight'];
                $totals['stprice']  += (float) $r['stprice'];
                $totals['dmdwgt']   += (float) ($r['dmdwgt'] ?? 0);
                $totals['dmdamt']   += (float) ($r['dmdamt'] ?? 0);
                $totals['rate']     += (float) ($r['rate'] ?? 0);
            }
        }

        return view('barcode-list.index', compact(
            'dateFrom', 'dateTo', 'itemCode', 'docNo', 'bcNo',
            'stkType', 'itype', 'grpCode', 'subGrp', 'model',
            'showData', 'rows', 'totals', 'groupCodes'
        ));
    }

    private function loadData(
        string $dateFrom, string $dateTo, string $itemCode, string $docNo,
        string $bcNo, string $stkType, string $itype,
        string $grpCode, string $subGrp, string $model
    ): array {
        $barcodeCols = array_map('strtolower', $this->columnList('barcode'));

        $select = [
            'barcode.bcode', 'barcode.icode', 'barcode.qty', 'barcode.weight',
            'barcode.stweight', 'barcode.stprice', 'barcode.mc', 'barcode.mcrate',
            'barcode.vap', 'barcode.tdate', 'barcode.stk', 'barcode.part',
            'barcode.rate', 'barcode.tamt', 'barcode.docno',
            'items.name as itemname', 'items.itype', 'items.grpcode', 'items.dmdplt',
        ];

        // All optional barcode columns from PB d_barcodelist
        $optCols = [
            'qtype', 'qunit', 'smithcode', 'serialno', 'model', 'subgrp',
            'cost', 'costperc', 'costmc', 'coststone', 'costamt',
            'huid', 'dmdwgt', 'dmdnos', 'dmdamt', 'dmdunit',
            'counter', 'stkinnos', 'nodisc', 'status', 'sizemodel',
            'transtouch', 'stktouch', 'grate', 'smcode',
            'wastage', 'goldct', 'note', 'minvap', 'weight2',
            'pqty', 'pwgt', 'pstwgt', 'sdate',
            'rslno', 'islno', 'control', 'smithmcrate',
        ];
        foreach ($optCols as $col) {
            if (in_array($col, $barcodeCols)) {
                $select[] = "barcode.{$col}";
            }
        }

        $query = DB::table('barcode')
            ->join('items', 'barcode.icode', '=', 'items.code')
            ->select($select)
            ->where('barcode.control', '<=', $this->gilevel)
            ->whereBetween('barcode.tdate', [$dateFrom, $dateTo])
            ->orderBy('barcode.bcode');

        if ($itemCode !== '') $query->where('barcode.icode', $itemCode);
        if ($docNo !== '')    $query->where('barcode.docno', $docNo);
        if ($bcNo !== '')     $query->where('barcode.bcode', $bcNo);
        if ($model !== '' && in_array('model', $barcodeCols))
            $query->where('barcode.model', 'like', "%{$model}%");

        if ($stkType === 'In Stock')  $query->where('barcode.stk', 'Y');
        elseif ($stkType === 'Sold')  $query->where('barcode.stk', 'N');

        if ($itype !== 'All') {
            $typeMap = ['Gold'=>'G','Silver'=>'S','Platinum'=>'P','Diamond'=>'D','Others'=>'O'];
            if (isset($typeMap[$itype])) $query->where('items.itype', $typeMap[$itype]);
        }

        if ($grpCode !== '') $query->where('items.grpcode', $grpCode);
        if ($subGrp !== '' && in_array('subgrp', $barcodeCols))
            $query->where('barcode.subgrp', $subGrp);

        return $query->get()->map(fn ($r) => (array) $r)->toArray();
    }
}
