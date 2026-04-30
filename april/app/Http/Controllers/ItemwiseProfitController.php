<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ItemwiseProfitController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('item-reports.itemwise-profit', [
            'title'    => (string) $request->query('title', 'Itemwise Profit'),
            'moduleId' => (string) $request->query('module', 'item-reports-itemwise-profit'),
            'today'    => date('Y-m-d'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code'))
            return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);

        $rlevel   = max(1, (int) $request->session()->get('gilevel', 1));
        $dateFrom = (string) $request->query('date1', date('Y-m-d'));
        $dateTo   = (string) $request->query('date2', date('Y-m-d'));
        $itemCode = strtoupper(trim((string) $request->query('item_code', '')));

        $rows   = [];
        $totals = ['saleamt'=>0,'costamt'=>0,'profit'=>0,'tqty'=>0,'twgt'=>0,'count'=>0];

        if (!$this->hasTable('salesm') || !$this->hasTable('salesd') || !$this->hasTable('items'))
            return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);

        $hasControl = Schema::hasColumn('salesm', 'control');
        $hasSr      = Schema::hasColumn('salesm', 'sr');
        $hasOpbill  = Schema::hasColumn('salesm', 'opbill');
        $hasStkinnos= Schema::hasColumn('items', 'stkinnos');
        $hasAmount  = Schema::hasColumn('salesd', 'amount');
        $hasCost    = Schema::hasColumn('salesd', 'cost');

        $q = DB::table('items as i')
            ->join('salesd as sd', 'sd.code', '=', 'i.code')
            ->join('salesm as sm', 'sm.slno', '=', 'sd.slno')
            ->whereBetween('sm.tdate', [$dateFrom, $dateTo]);

        if ($hasControl) $q->where('sm.control', '<=', $rlevel);
        if ($hasSr)      $q->where('sm.sr', 'S');
        if ($hasOpbill)  $q->where('sm.opbill', '<>', 1);
        if ($itemCode !== '') $q->where(DB::raw('UPPER(TRIM(i.code))'), $itemCode);

        $groupCols = ['i.code','i.name'];
        if ($hasStkinnos) $groupCols[] = 'i.stkinnos';

        $q->selectRaw('i.code, i.name')
          ->selectRaw($hasStkinnos ? 'i.stkinnos' : "'' as stkinnos")
          ->selectRaw($hasAmount ? 'SUM(sd.amount) as saleamt' : '0 as saleamt')
          ->selectRaw($hasCost   ? 'SUM(sd.cost * sd.qty) as qtycostamt'    : '0 as qtycostamt')
          ->selectRaw($hasCost   ? 'SUM(sd.cost * sd.weight) as wgtcostamt' : '0 as wgtcostamt')
          ->selectRaw('SUM(sd.qty) as tqty, SUM(sd.weight) as twgt')
          ->groupByRaw(implode(', ', $groupCols))
          ->havingRaw($hasAmount ? 'SUM(sd.amount) > 0' : '1=1')
          ->orderBy('i.name');

        foreach ($q->get() as $r) {
            $saleamt  = round((float)($r->saleamt??0), 2);
            $costamt  = (trim((string)($r->stkinnos??''))==='Y')
                ? round((float)($r->qtycostamt??0), 2)
                : round((float)($r->wgtcostamt??0), 2);
            $profit   = round($saleamt - $costamt, 2);
            $tqty     = (int)($r->tqty??0);
            $twgt     = round((float)($r->twgt??0), 3);
            $rows[]   = ['code'=>trim((string)($r->code??'')), 'name'=>trim((string)($r->name??'')),
                         'tqty'=>$tqty,'twgt'=>$twgt,'saleamt'=>$saleamt,'costamt'=>$costamt,'profit'=>$profit];
            $totals['saleamt'] += $saleamt;
            $totals['costamt'] += $costamt;
            $totals['profit']  += $profit;
            $totals['tqty']    += $tqty;
            $totals['twgt']    += $twgt;
        }
        $totals['twgt']  = round($totals['twgt'], 3);
        $totals['count'] = count($rows);
        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }
}
