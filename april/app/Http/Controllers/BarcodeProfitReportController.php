<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarcodeProfitReportController extends Controller
{
    private array $colCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.barcode-profit', [
            'title'  => 'Barcode Profit Report',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) $request->session()->get('gilevel', 1) ?: 1,
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $groups = [];
        if ($this->hasTable('itemgrp')) {
            $groups = DB::table('itemgrp')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $subgroups = [];
        if ($this->hasTable('itemsubgrp')) {
            $subgroups = DB::table('itemsubgrp')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json(['ok' => true, 'groups' => $groups, 'subgroups' => $subgroups]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1 = (string) $request->query('date1', date('Y-m-d'));
        $date2 = (string) $request->query('date2', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        $rlevel   = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $grpcode  = trim((string) $request->query('grpcode', ''));
        $grpon    = (int) $request->query('grpon', 0);
        $subgrp   = trim((string) $request->query('subgrp', ''));
        $subgrpon = (int) $request->query('subgrpon', 0);

        if (!$this->hasTable('salesm') || !$this->hasTable('salesd')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $q = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->leftJoin('items as i', 'i.code', '=', 'd.code')
            ->select(
                'm.tdate', 'm.billno', 'm.custname',
                'd.code as itemcode', 'd.bcode', 'd.qty', 'd.weight',
                'd.amount', 'd.stonewgt', 'd.stoneprice'
            );

        $q->addSelect(DB::raw($this->hasCol('items', 'name') ? 'i.name as itemname' : "'' as itemname"));
        $q->addSelect(DB::raw($this->hasCol('items', 'grpcode') ? 'i.grpcode' : "'' as grpcode"));
        $q->addSelect(DB::raw($this->hasCol('items', 'subgrpcode') ? 'i.subgrpcode' : "'' as subgrpcode"));

        // Subqueries from barcode table
        $hasBarcodeTable = $this->hasTable('barcode');
        if ($hasBarcodeTable) {
            $q->selectRaw('(SELECT bc.cost FROM barcode bc WHERE bc.bcode = d.bcode LIMIT 1) as bc_cost');
            $q->selectRaw('(SELECT bc.costperc FROM barcode bc WHERE bc.bcode = d.bcode LIMIT 1) as bc_costperc');
            $q->selectRaw('(SELECT bc.costamt FROM barcode bc WHERE bc.bcode = d.bcode LIMIT 1) as bccostamt');
            $q->selectRaw('(SELECT bc.coststone FROM barcode bc WHERE bc.bcode = d.bcode LIMIT 1) as stcostamt');
        } else {
            $q->selectRaw('0 as bc_cost, 0 as bc_costperc, 0 as bccostamt, 0 as stcostamt');
        }

        // Subquery: dmd amt from spdmddet
        if ($this->hasTable('spdmddet') && $this->hasCol('salesd', 'sno')) {
            $q->selectRaw('(SELECT COALESCE(SUM(sp.dmdamt),0) FROM spdmddet sp WHERE sp.slno = d.slno AND sp.sno = d.sno) as dmdamt');
        } else {
            $q->selectRaw('0 as dmdamt');
        }

        // Subquery: dmd cost amt from barcode_dmddet
        if ($this->hasTable('barcode_dmddet')) {
            $q->selectRaw('(SELECT COALESCE(SUM(bd.pamt),0) FROM barcode_dmddet bd WHERE bd.bcode = d.bcode) as dmdcostamt');
        } else {
            $q->selectRaw('0 as dmdcostamt');
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        if ($this->hasCol('salesm', 'control')) $q->where('m.control', '<=', $rlevel);
        $q->where('d.bcode', '>', 0);

        if ($grpcode !== '' && $grpon && $this->hasCol('items', 'grpcode'))
            $q->where('i.grpcode', $grpcode);

        if ($subgrp !== '' && $subgrpon && $this->hasCol('items', 'subgrpcode'))
            $q->where('i.subgrpcode', $subgrp);

        $q->orderBy('m.tdate')->orderBy('m.slno');

        $rows = $q->get()->map(function ($r) {
            $row = (array) $r;
            $amount     = (float) ($row['amount'] ?? 0);
            $weight     = (float) ($row['weight'] ?? 0);
            $stonewgt   = (float) ($row['stonewgt'] ?? 0);
            $stoneprice = (float) ($row['stoneprice'] ?? 0);
            $bccostamt  = (float) ($row['bccostamt'] ?? 0);
            $bcCost     = (float) ($row['bc_cost'] ?? 0);
            $bcCostperc = (float) ($row['bc_costperc'] ?? 0);
            $stcostamt  = (float) ($row['stcostamt'] ?? 0);
            $dmdamt     = (float) ($row['dmdamt'] ?? 0);
            $dmdcostamt = (float) ($row['dmdcostamt'] ?? 0);

            // costamt = if(bccostamt > 0, bccostamt, round(if(cost > 0, (weight-stonewgt)*cost, amount*costperc/100), 0))
            if ($bccostamt > 0) {
                $row['costamt'] = $bccostamt;
            } else {
                if ($bcCost > 0) {
                    $row['costamt'] = round(($weight - $stonewgt) * $bcCost, 0);
                } else {
                    $row['costamt'] = round($amount * $bcCostperc / 100, 0);
                }
            }

            $row['costperc'] = $bcCostperc;

            // profit = if(costamt > 0, amount - costamt, 0)
            $row['profit'] = $row['costamt'] > 0 ? $amount - $row['costamt'] : 0;

            // stamt = stoneprice + if(isnull(dmdamt), 0, dmdamt)
            $row['stamt'] = $stoneprice + $dmdamt;

            // stcost = if(isnull(stcostamt), 0, stcostamt) + if(isnull(dmdcostamt), 0, dmdcostamt)
            $row['stcost'] = $stcostamt + $dmdcostamt;

            // stprofit = stamt - stcost
            $row['stprofit'] = $row['stamt'] - $row['stcost'];

            // Clean up internal fields
            unset($row['bc_cost'], $row['bc_costperc'], $row['bccostamt'], $row['stcostamt'], $row['dmdcostamt']);

            return $row;
        })->values()->all();

        $totals = ['qty' => 0, 'weight' => 0, 'amount' => 0, 'costamt' => 0, 'profit' => 0, 'stamt' => 0, 'stcost' => 0, 'stprofit' => 0, 'count' => count($rows)];
        foreach ($rows as $row) {
            $totals['qty']      += (int) ($row['qty'] ?? 0);
            $totals['weight']   += (float) ($row['weight'] ?? 0);
            $totals['amount']   += (float) ($row['amount'] ?? 0);
            $totals['costamt']  += (float) ($row['costamt'] ?? 0);
            $totals['profit']   += (float) ($row['profit'] ?? 0);
            $totals['stamt']    += (float) ($row['stamt'] ?? 0);
            $totals['stcost']   += (float) ($row['stcost'] ?? 0);
            $totals['stprofit'] += (float) ($row['stprofit'] ?? 0);
        }

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    private function hasCol(string $table, string $col): bool
    {
        $key = "$table.$col";
        if (!array_key_exists($key, $this->colCache)) {
            $this->colCache[$key] = Schema::hasColumn($table, $col);
        }
        return $this->colCache[$key];
    }
}
