<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarcodeSamtListController extends Controller
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
        $stkType   = $request->query('stk_type', 'Not in Stock');
        $grpCode   = trim($request->query('grp', ''));
        $supplier  = trim($request->query('supplier', ''));
        $cpFrom    = $request->query('cp_from', '');
        $cpTo      = $request->query('cp_to', '');
        $showData  = $request->has('show');

        $rows   = [];
        $totals = [
            'count' => 0, 'qty' => 0, 'weight' => 0.0, 'stweight' => 0.0,
            'stprice' => 0.0, 'dmdwgt' => 0.0, 'dmdamt' => 0.0,
            'tamt' => 0.0, 'costamt' => 0.0, 'soldwgt' => 0.0,
        ];

        $groupCodes = [];
        if ($this->hasTable('items')) {
            $groupCodes = DB::table('items')
                ->whereNotNull('grpcode')->where('grpcode', '<>', '')
                ->distinct()->orderBy('grpcode')->pluck('grpcode')->toArray();
        }

        if ($showData && $this->hasTable('barcode')) {
            $rows = $this->loadData($dateFrom, $dateTo, $itemCode, $docNo, $bcNo,
                                    $stkType, $grpCode, $supplier, $cpFrom, $cpTo);
            $totals['count'] = count($rows);
            foreach ($rows as $r) {
                $totals['qty']      += (int) $r['qty'];
                $totals['weight']   += (float) $r['weight'];
                $totals['stweight'] += (float) $r['stweight'];
                $totals['stprice']  += (float) $r['stprice'];
                $totals['dmdwgt']   += (float) ($r['dmdwgt'] ?? 0);
                $totals['dmdamt']   += (float) ($r['dmdamt'] ?? 0);
                $totals['tamt']     += (float) ($r['tamt'] ?? 0);
                $totals['costamt']  += (float) ($r['costamt'] ?? 0);
                $totals['soldwgt']  += (float) ($r['soldwgt'] ?? 0);
            }
        }

        return view('barcode-list.samt-list', compact(
            'dateFrom', 'dateTo', 'itemCode', 'docNo', 'bcNo',
            'stkType', 'grpCode', 'supplier', 'cpFrom', 'cpTo',
            'showData', 'rows', 'totals', 'groupCodes'
        ));
    }

    private function loadData(
        string $dateFrom, string $dateTo, string $itemCode, string $docNo,
        string $bcNo, string $stkType, string $grpCode,
        string $supplier, string $cpFrom, string $cpTo
    ): array {
        $barcodeCols = array_map('strtolower', $this->columnList('barcode'));

        $select = [
            'barcode.bcode', 'barcode.icode', 'barcode.qty', 'barcode.weight',
            'barcode.stweight', 'barcode.stprice', 'barcode.mc', 'barcode.mcrate',
            'barcode.vap', 'barcode.tdate', 'barcode.stk', 'barcode.part',
            'barcode.rate', 'barcode.tamt', 'barcode.docno',
            'items.name as itemname', 'items.itype', 'items.grpcode',
        ];

        $optCols = ['qtype','costamt','huid','dmdwgt','dmdnos','dmdamt','dmdunit','subgrp'];
        foreach ($optCols as $col) {
            if (in_array($col, $barcodeCols)) $select[] = "barcode.{$col}";
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

        if ($stkType === 'Stock Only')    $query->where('barcode.stk', 'Y');
        elseif ($stkType === 'Not in Stock') $query->where('barcode.stk', 'N');

        if ($grpCode !== '') $query->where('items.grpcode', $grpCode);

        if ($cpFrom !== '' && is_numeric($cpFrom) && in_array('costamt', $barcodeCols))
            $query->where('barcode.costamt', '>=', (float) $cpFrom);
        if ($cpTo !== '' && is_numeric($cpTo) && in_array('costamt', $barcodeCols))
            $query->where('barcode.costamt', '<=', (float) $cpTo);

        $rows = $query->get()->map(fn ($r) => (array) $r)->toArray();

        // Compute sold weight from actual sales less sales returns, grouped by barcode.
        $bcodes = array_values(array_filter(array_map(
            fn ($row) => $row['bcode'] ?? null,
            $rows
        ), fn ($bcode) => $bcode !== null && $bcode !== ''));

        $soldWeights = [];
        if ($bcodes !== [] && $this->hasTable('salesd')) {
            $soldWeights = DB::table('salesd')
                ->selectRaw('bcode, COALESCE(SUM(weight), 0) as soldwgt')
                ->whereIn('bcode', $bcodes)
                ->groupBy('bcode')
                ->pluck('soldwgt', 'bcode')
                ->map(fn ($weight) => (float) $weight)
                ->toArray();
        }

        $returnWeights = [];
        if ($bcodes !== [] && $this->hasTable('salesrd')) {
            $returnWeights = DB::table('salesrd')
                ->selectRaw('bcode, COALESCE(SUM(weight), 0) as returnwgt')
                ->whereIn('bcode', $bcodes)
                ->groupBy('bcode')
                ->pluck('returnwgt', 'bcode')
                ->map(fn ($weight) => (float) $weight)
                ->toArray();
        }

        $docNos = array_values(array_unique(array_filter(array_map(
            fn ($row) => trim((string) ($row['docno'] ?? '')),
            $rows
        ))));

        $supplierCodes = [];
        if ($docNos !== [] && $this->hasTable('purchasem')) {
            $supplierCodes = DB::table('purchasem')
                ->whereIn('docno', $docNos)
                ->pluck('suppcode', 'docno')
                ->map(fn ($code) => (string) $code)
                ->toArray();
        }

        foreach ($rows as &$row) {
            $bcode = $row['bcode'] ?? '';
            $sold = (float) ($soldWeights[$bcode] ?? 0);
            $returned = (float) ($returnWeights[$bcode] ?? 0);
            $row['soldwgt'] = max(0, $sold - $returned);

            $docNoKey = trim((string) ($row['docno'] ?? ''));
            $row['scode'] = (string) ($supplierCodes[$docNoKey] ?? '');
        }
        unset($row);

        // Supplier filter (post-query since it's computed)
        if ($supplier !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['scode'] === $supplier));
        }

        return $rows;
    }
}
