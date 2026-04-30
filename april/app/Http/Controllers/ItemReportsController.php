<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ItemReportsController extends Controller
{
    private function auth(Request $request): bool
    {
        return $request->session()->has('user_code');
    }

    private function rlevel(Request $request): int
    {
        $v = (int) $request->session()->get('gilevel', 1);
        return $v > 0 ? $v : 1;
    }

    private function today(): string { return date('Y-m-d'); }

    private function stockTypes(): array
    {
        return $this->hasTable('stktype')
            ? DB::table('stktype')->orderBy('code')->get(['code','name'])->toArray()
            : [];
    }

    /* ── Item Rate Report ─────────────────────────────────────── */
    public function itemRate(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('item-reports.item-rate-report', [
            'title'    => (string) $request->query('title', 'Item Rate Report'),
            'moduleId' => (string) $request->query('module', 'item-reports-item-rate-report'),
            'today'    => $this->today(),
        ]);
    }

    public function itemRateData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);
        $itype  = (string) $request->query('itype', '');
        $search = strtoupper(trim((string) $request->query('search', '')));
        if (!$this->hasTable('items')) return response()->json(['ok'=>true,'rows'=>[]]);
        $q = DB::table('items')->where('disabled','<>',1)->orderBy('name');
        if ($itype !== '') $q->where(DB::raw('UPPER(LEFT(TRIM(itype),1))'), strtoupper($itype[0]));
        if ($search !== '') {
            $q->where(function($s) use ($search) {
                $s->where(DB::raw('UPPER(TRIM(code))'),'like','%'.$search.'%')
                  ->orWhere(DB::raw('UPPER(name)'),'like','%'.$search.'%');
            });
        }
        $rows = $q->get(['code','name','itype','touch','wastage','mcharge','rate','wsrate','cost','prate','crate','defstktype'])
            ->map(fn($r)=>(array)$r)->values()->all();
        return response()->json(['ok'=>true,'rows'=>$rows,'count'=>count($rows)]);
    }

    /* ── Cost List ────────────────────────────────────────────── */
    public function costList(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('item-reports.cost-list', [
            'title'      => (string) $request->query('title', 'Cost List'),
            'moduleId'   => (string) $request->query('module', 'item-reports-cost-list'),
            'stockTypes' => $this->stockTypes(),
            'today'      => $this->today(),
        ]);
    }

    public function costListData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);
        $itype = (string) $request->query('itype', '');
        if (!$this->hasTable('items')) return response()->json(['ok'=>true,'rows'=>[]]);
        $q = DB::table('items as i')->where('i.disabled','<>',1)->orderBy('i.name');
        if ($itype !== '') $q->where(DB::raw('UPPER(LEFT(TRIM(i.itype),1))'), strtoupper($itype[0]));
        $rows = $q->get(['i.code','i.name','i.itype','i.cost','i.rate','i.wsrate','i.prate','i.stkinnos','i.touch','i.wastage'])
            ->map(fn($r)=>(array)$r)->values()->all();
        return response()->json(['ok'=>true,'rows'=>$rows,'count'=>count($rows)]);
    }

    /* ── Rate History ─────────────────────────────────────────── */
    public function rateHistory(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('item-reports.rate-history', [
            'title'    => (string) $request->query('title', 'Rate History'),
            'moduleId' => (string) $request->query('module', 'item-reports-rate-history'),
            'today'    => $this->today(),
        ]);
    }

    public function rateHistoryData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);
        $dateFrom = (string) $request->query('date1', date('Y-m-d'));
        $dateTo   = (string) $request->query('date2', date('Y-m-d'));
        if (!$this->hasTable('ratehistory')) return response()->json(['ok'=>true,'rows'=>[]]);
        $rows = DB::table('ratehistory')
            ->whereBetween('tdate',[$dateFrom,$dateTo])
            ->orderBy('tdate')->orderBy('ttime')
            ->get()->map(fn($r)=>(array)$r)->values()->all();
        return response()->json(['ok'=>true,'rows'=>$rows,'count'=>count($rows)]);
    }

    /* ── Model Transfer Report ───────────────────────────────── */
    public function modelTransfer(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('item-reports.model-transfer-report', [
            'title'    => (string) $request->query('title', 'Model Transfer Report'),
            'moduleId' => (string) $request->query('module', 'item-reports-model-transfer-report'),
            'today'    => $this->today(),
        ]);
    }

    public function modelTransferData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);
        $rlevel   = $this->rlevel($request);
        $dateFrom = (string) $request->query('date1', date('Y-m-d'));
        $dateTo   = (string) $request->query('date2', date('Y-m-d'));
        $icode    = strtoupper(trim((string) $request->query('icode', '')));
        $pend     = (string) $request->query('pend', '');
        if (!$this->hasTable('modelm')) return response()->json(['ok'=>true,'rows'=>[],'totals'=>['qty'=>0,'weight'=>0,'stwgt'=>0,'count'=>0]]);
        $hasControl = Schema::hasColumn('modelm','control');
        $q = DB::table('modelm as m')
            ->leftJoin('items as i', DB::raw('UPPER(TRIM(i.code))'), '=', DB::raw('UPPER(TRIM(m.icode))'))
            ->leftJoin('clients as c', DB::raw('UPPER(TRIM(c.code))'), '=', DB::raw('UPPER(TRIM(m.pcode))'))
            ->whereBetween('m.tdate',[$dateFrom,$dateTo])
            ->orderBy('m.tdate')->orderBy('m.slno');
        if ($hasControl) $q->where('m.control','<=',$rlevel);
        if ($icode !== '') $q->where(DB::raw('UPPER(TRIM(m.icode))'),$icode);
        if ($pend === 'Y') $q->where('m.pend','Y');
        elseif ($pend === 'N') $q->where(function($s){ $s->where('m.pend','N')->orWhereNull('m.pend'); });
        $rows = $q->get(['m.slno','m.tdate','m.pcode','m.pname','m.icode','i.name as iname','m.qty','m.weight','m.stwgt','m.ir','m.pend','m.note','m.smcode'])
            ->map(fn($r)=>(array)$r)->values()->all();
        $totals = ['qty'=>0,'weight'=>0,'stwgt'=>0,'count'=>count($rows)];
        foreach ($rows as $r) {
            $totals['qty']    += (int)($r['qty']??0);
            $totals['weight'] += (float)($r['weight']??0);
            $totals['stwgt']  += (float)($r['stwgt']??0);
        }
        $totals['weight'] = round($totals['weight'],3);
        $totals['stwgt']  = round($totals['stwgt'],3);
        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }

    /* ── Stone Trans Analysis ────────────────────────────────── */
    public function stoneTransAnalysis(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('item-reports.stone-trans-analysis', [
            'title'    => (string) $request->query('title', 'Stone Trans Analysis'),
            'moduleId' => (string) $request->query('module', 'item-reports-stone-trans-analysis'),
            'today'    => $this->today(),
        ]);
    }

    public function stoneTransData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);
        $rlevel   = $this->rlevel($request);
        $dateFrom = (string) $request->query('date1', date('Y-m-d'));
        $dateTo   = (string) $request->query('date2', date('Y-m-d'));
        if (!$this->hasTable('salesm') || !$this->hasTable('salesd'))
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>['qty'=>0,'stonewgt'=>0,'stoneprice'=>0,'count'=>0]]);
        $hasControl = Schema::hasColumn('salesm','control');
        $hasSr      = Schema::hasColumn('salesm','sr');
        $q = DB::table('salesm as sm')
            ->join('salesd as sd','sd.slno','=','sm.slno')
            ->leftJoin('items as i', DB::raw('UPPER(TRIM(i.code))'), '=', DB::raw('UPPER(TRIM(sd.code))'))
            ->whereBetween('sm.tdate',[$dateFrom,$dateTo])
            ->where('sd.stonewgt','>',0)
            ->groupByRaw('sd.code, i.name')
            ->orderBy('i.name');
        if ($hasControl) $q->where('sm.control','<=',$rlevel);
        if ($hasSr)      $q->where('sm.sr','S');
        $rows = $q->selectRaw('sd.code, i.name as iname, SUM(sd.qty) as tot_qty, SUM(sd.stonewgt) as tot_stonewgt, SUM(sd.stoneprice) as tot_stoneprice')
            ->get()->map(fn($r)=>(array)$r)->values()->all();
        $totals = ['qty'=>0,'stonewgt'=>0,'stoneprice'=>0,'count'=>count($rows)];
        foreach ($rows as $r) {
            $totals['qty']        += (int)($r['tot_qty']??0);
            $totals['stonewgt']   += (float)($r['tot_stonewgt']??0);
            $totals['stoneprice'] += (float)($r['tot_stoneprice']??0);
        }
        $totals['stonewgt']   = round($totals['stonewgt'],3);
        $totals['stoneprice'] = round($totals['stoneprice'],2);
        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }

    /* ── Trans RA Report ─────────────────────────────────────── */
    public function transRa(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('item-reports.trans-ra-report', [
            'title'      => (string) $request->query('title', 'Trans RA Report'),
            'moduleId'   => (string) $request->query('module', 'item-reports-trans-ra-report'),
            'stockTypes' => $this->stockTypes(),
            'today'      => $this->today(),
        ]);
    }

    public function transRaData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);
        $rlevel   = $this->rlevel($request);
        $dateFrom = (string) $request->query('date1', date('Y-m-d'));
        $dateTo   = (string) $request->query('date2', date('Y-m-d'));
        $stktype  = strtoupper(trim((string) $request->query('stktype', '')));
        if (!$this->hasTable('itemadjverify'))
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>['addqty'=>0,'addwgt'=>0,'lessqty'=>0,'lesswgt'=>0,'count'=>0]]);
        $q = DB::table('itemadjverify as v')
            ->leftJoin('items as i', DB::raw('UPPER(TRIM(i.code))'), '=', DB::raw('UPPER(TRIM(v.code))'))
            ->whereBetween('v.tdate',[$dateFrom,$dateTo])
            ->where('v.control','<=',$rlevel)
            ->orderBy('v.tdate')->orderBy('v.sno');
        if ($stktype !== '') $q->where(DB::raw('UPPER(TRIM(v.stktype))'),$stktype);
        $rows = $q->get(['v.sno','v.tdate','v.code','i.name as iname','v.stktype','v.addqty','v.addwgt','v.addnetwgt','v.lessqty','v.lesswgt','v.lessnetwgt'])
            ->map(fn($r)=>(array)$r)->values()->all();
        $totals = ['addqty'=>0,'addwgt'=>0,'lessqty'=>0,'lesswgt'=>0,'count'=>count($rows)];
        foreach ($rows as $r) {
            $totals['addqty']  += (int)($r['addqty']??0);
            $totals['addwgt']  += (float)($r['addwgt']??0);
            $totals['lessqty'] += (int)($r['lessqty']??0);
            $totals['lesswgt'] += (float)($r['lesswgt']??0);
        }
        $totals['addwgt']  = round($totals['addwgt'],3);
        $totals['lesswgt'] = round($totals['lesswgt'],3);
        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }

    /* ── Item Stock + Party Wgt Report ──────────────────────── */
    public function itemStockPartyWgt(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('item-reports.item-stock-party-wgt-report', [
            'title'      => (string) $request->query('title', 'Item Stock & Party Wgt Report'),
            'moduleId'   => (string) $request->query('module', 'item-reports-item-stock-party-wgt-report'),
            'stockTypes' => $this->stockTypes(),
            'today'      => $this->today(),
        ]);
    }

    public function itemStockPartyWgtData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);
        $itype   = (string) $request->query('itype', '');
        $stktype = strtoupper(trim((string) $request->query('stktype', '')));
        if (!$this->hasTable('items') || !$this->hasTable('itemsstk'))
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>['stk_qty'=>0,'stk_wgt'=>0,'stk_stwgt'=>0,'party_qty'=>0,'party_wgt'=>0,'count'=>0]]);
        $partyWgtExists = $this->hasTable('wgtrcptpmnt');
        $q = DB::table('items as i')
            ->leftJoin('itemsstk as s', function($j) use ($stktype) {
                $j->on(DB::raw('UPPER(TRIM(s.code))'), '=', DB::raw('UPPER(TRIM(i.code))'));
                if ($stktype !== '') $j->where(DB::raw('UPPER(TRIM(s.stktype))'),$stktype);
            })
            ->where('i.disabled','<>',1)
            ->orderBy('i.name');
        if ($itype !== '') $q->where(DB::raw('UPPER(LEFT(TRIM(i.itype),1))'),strtoupper($itype[0]));
        $q->selectRaw('i.code, i.name, i.itype, i.defstktype')
          ->selectRaw('COALESCE(SUM(s.qty),0) as stk_qty')
          ->selectRaw('COALESCE(SUM(s.weight),0) as stk_wgt')
          ->selectRaw('COALESCE(SUM(s.stonewgt),0) as stk_stwgt');
        if ($partyWgtExists) {
            $q->selectRaw('COALESCE((SELECT SUM(w.qty) FROM wgtrcptpmnt w WHERE UPPER(TRIM(w.icode))=UPPER(TRIM(i.code)) AND w.ttype="R"),0) as party_qty')
              ->selectRaw('COALESCE((SELECT SUM(w.weight) FROM wgtrcptpmnt w WHERE UPPER(TRIM(w.icode))=UPPER(TRIM(i.code)) AND w.ttype="R"),0) as party_wgt');
        } else {
            $q->selectRaw('0 as party_qty, 0 as party_wgt');
        }
        $q->groupByRaw('i.code, i.name, i.itype, i.defstktype')
          ->havingRaw('stk_wgt > 0 OR party_wgt > 0');
        $rows = $q->get()->map(fn($r)=>(array)$r)->values()->all();
        $totals = ['stk_qty'=>0,'stk_wgt'=>0,'stk_stwgt'=>0,'party_qty'=>0,'party_wgt'=>0,'count'=>count($rows)];
        foreach ($rows as $r) {
            $totals['stk_qty']   += (int)($r['stk_qty']??0);
            $totals['stk_wgt']   += (float)($r['stk_wgt']??0);
            $totals['stk_stwgt'] += (float)($r['stk_stwgt']??0);
            $totals['party_qty'] += (int)($r['party_qty']??0);
            $totals['party_wgt'] += (float)($r['party_wgt']??0);
        }
        foreach (['stk_wgt','stk_stwgt','party_wgt'] as $k) $totals[$k] = round($totals[$k],3);
        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }
}
