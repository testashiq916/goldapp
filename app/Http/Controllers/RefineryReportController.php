<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefineryReportController extends Controller
{
    private function auth(Request $request): bool
    {
        return $request->session()->has('user_code');
    }

    private function today(): string { return date('Y-m-d'); }

    private function refiners(): array
    {
        if (!$this->hasTable('clients')) return [];
        return DB::table('clients')
            ->where(DB::raw("UPPER(TRIM(COALESCE(ctype,'')))"), 'R')
            ->orderBy('name')
            ->get(['code','name'])
            ->toArray();
    }

    // ── Entry Report ──────────────────────────────────────────────

    public function entryReport(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('refinery-reports.entry-report', [
            'title'    => (string) $request->query('title', 'Refinery Entry Report'),
            'moduleId' => (string) $request->query('module', 'refinery-reports-entry-report'),
            'today'    => $this->today(),
            'refiners' => $this->refiners(),
        ]);
    }

    public function entryReportData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);

        $date1   = $request->query('date1', $this->today());
        $date2   = $request->query('date2', $this->today());
        $refcode = strtoupper(trim((string) $request->query('refcode', '')));
        $status  = (string) $request->query('status', ''); // '' = all, '1' = forward, '2' = return

        if (!$this->hasTable('refinerym') || !$this->hasTable('refineryd')) {
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>[]]);
        }

        $q = DB::table('refinerym as m')
            ->join('refineryd as d', 'd.slno', '=', 'm.slno')
            ->leftJoin('items as i', DB::raw('TRIM(i.code)'), '=', DB::raw('TRIM(d.code)'))
            ->leftJoin('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(m.refcode)'))
            ->whereBetween('m.tdate', [$date1, $date2]);

        if ($refcode !== '') $q->where(DB::raw('UPPER(TRIM(m.refcode))'), $refcode);
        if ($status !== '')  $q->where('m.status', (int)$status);

        $rows = $q->orderBy('m.tdate')->orderBy('m.slno')->orderBy('d.sno')
            ->get([
                'm.tdate', 'm.docno', 'm.status',
                DB::raw('TRIM(COALESCE(c.name, m.refcode)) as refinername'),
                DB::raw('TRIM(COALESCE(i.name, d.code)) as itemname'),
                DB::raw('TRIM(d.code) as itemcode'),
                'd.issuedwgt', 'd.issuedqty', 'd.issuedstwgt',
                'd.rcvdwgt',   'd.rcvdqty',   'd.rcvdtouch',
                'd.mudless',   'd.testpcs',   'd.touch',
                'd.stktype',   'd.rate',       'm.testperc',
                'm.expwgt',    'm.note',       'm.charge',
            ])
            ->map(function($r) {
                return [
                    'tdate'       => (string)($r->tdate ?? ''),
                    'docno'       => trim((string)($r->docno ?? '')),
                    'status'      => (int)($r->status ?? 1),
                    'status_lbl'  => ((int)($r->status ?? 1)) === 2 ? 'Return' : 'Forward',
                    'refinername' => (string)($r->refinername ?? ''),
                    'itemname'    => (string)($r->itemname ?? ''),
                    'itemcode'    => (string)($r->itemcode ?? ''),
                    'issuedwgt'   => round((float)($r->issuedwgt ?? 0), 3),
                    'issuedqty'   => (int)($r->issuedqty ?? 0),
                    'issuedstwgt' => round((float)($r->issuedstwgt ?? 0), 3),
                    'rcvdwgt'     => round((float)($r->rcvdwgt ?? 0), 3),
                    'rcvdqty'     => (int)($r->rcvdqty ?? 0),
                    'rcvdtouch'   => round((float)($r->rcvdtouch ?? 0), 2),
                    'mudless'     => round((float)($r->mudless ?? 0), 3),
                    'testpcs'     => round((float)($r->testpcs ?? 0), 3),
                    'touch'       => round((float)($r->touch ?? 0), 2),
                    'stktype'     => trim((string)($r->stktype ?? '')),
                    'rate'        => round((float)($r->rate ?? 0), 2),
                    'testperc'    => round((float)($r->testperc ?? 0), 2),
                    'expwgt'      => round((float)($r->expwgt ?? 0), 3),
                    'note'        => trim((string)($r->note ?? '')),
                    'charge'      => round((float)($r->charge ?? 0), 2),
                ];
            })->values()->all();

        $totals = [
            'count'       => count($rows),
            'issuedwgt'   => round(array_sum(array_column($rows, 'issuedwgt')), 3),
            'rcvdwgt'     => round(array_sum(array_column($rows, 'rcvdwgt')), 3),
            'mudless'     => round(array_sum(array_column($rows, 'mudless')), 3),
            'testpcs'     => round(array_sum(array_column($rows, 'testpcs')), 3),
        ];

        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }

    // ── Refiner's Summary ─────────────────────────────────────────

    public function refinersSummary(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('refinery-reports.refiners-summary', [
            'title'    => (string) $request->query('title', "Refiner's Summary"),
            'moduleId' => (string) $request->query('module', 'refinery-reports-refiners-summary'),
            'today'    => $this->today(),
        ]);
    }

    public function refinersSummaryData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);

        $date1 = $request->query('date1', $this->today());
        $date2 = $request->query('date2', $this->today());

        if (!$this->hasTable('refinerym') || !$this->hasTable('refineryd')) {
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>[]]);
        }

        $rows = DB::table('refinerym as m')
            ->join('refineryd as d', 'd.slno', '=', 'm.slno')
            ->leftJoin('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(m.refcode)'))
            ->whereBetween('m.tdate', [$date1, $date2])
            ->groupBy('m.refcode')
            ->orderByRaw('MAX(c.name)')
            ->get([
                DB::raw('TRIM(m.refcode) as refcode'),
                DB::raw('MAX(TRIM(COALESCE(c.name, m.refcode))) as refinername'),
                DB::raw('COALESCE(SUM(CASE WHEN m.status=1 THEN d.issuedwgt ELSE 0 END),0) as total_issued'),
                DB::raw('COALESCE(SUM(CASE WHEN m.status=2 THEN d.rcvdwgt   ELSE 0 END),0) as total_rcvd'),
                DB::raw('COALESCE(SUM(CASE WHEN m.status=1 THEN d.mudless   ELSE 0 END),0) as total_mudless'),
                DB::raw('COALESCE(SUM(CASE WHEN m.status=1 THEN d.testpcs   ELSE 0 END),0) as total_testpcs'),
                DB::raw('COALESCE(SUM(CASE WHEN m.status=2 THEN m.charge    ELSE 0 END)/COUNT(DISTINCT CASE WHEN m.status=2 THEN m.slno END),0) as avg_charge'),
                DB::raw('COUNT(DISTINCT CASE WHEN m.status=1 THEN m.slno END) as fwd_bills'),
                DB::raw('COUNT(DISTINCT CASE WHEN m.status=2 THEN m.slno END) as ret_bills'),
            ])
            ->map(function($r) {
                $issued = round((float)($r->total_issued ?? 0), 3);
                $rcvd   = round((float)($r->total_rcvd   ?? 0), 3);
                return [
                    'refcode'      => (string)($r->refcode ?? ''),
                    'refinername'  => (string)($r->refinername ?? ''),
                    'fwd_bills'    => (int)($r->fwd_bills ?? 0),
                    'ret_bills'    => (int)($r->ret_bills ?? 0),
                    'total_issued' => $issued,
                    'total_rcvd'   => $rcvd,
                    'difference'   => round($issued - $rcvd, 3),
                    'total_mudless'=> round((float)($r->total_mudless ?? 0), 3),
                    'total_testpcs'=> round((float)($r->total_testpcs ?? 0), 3),
                ];
            })->values()->all();

        $totals = [
            'count'        => count($rows),
            'total_issued' => round(array_sum(array_column($rows, 'total_issued')), 3),
            'total_rcvd'   => round(array_sum(array_column($rows, 'total_rcvd')),   3),
            'difference'   => round(array_sum(array_column($rows, 'difference')),   3),
            'total_mudless'=> round(array_sum(array_column($rows, 'total_mudless')),3),
            'total_testpcs'=> round(array_sum(array_column($rows, 'total_testpcs')),3),
        ];

        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }

    // ── Analysis ──────────────────────────────────────────────────

    public function analysis(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('refinery-reports.analysis', [
            'title'    => (string) $request->query('title', 'Refinery Analysis'),
            'moduleId' => (string) $request->query('module', 'refinery-reports-analysis'),
            'today'    => $this->today(),
            'refiners' => $this->refiners(),
        ]);
    }

    public function analysisData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);

        $date1   = $request->query('date1', $this->today());
        $date2   = $request->query('date2', $this->today());
        $refcode = strtoupper(trim((string) $request->query('refcode', '')));

        if (!$this->hasTable('refinerym') || !$this->hasTable('refineryd')) {
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>[]]);
        }

        // Get forward bills paired with their returns
        $q = DB::table('refinerym as m')
            ->join('refineryd as d', 'd.slno', '=', 'm.slno')
            ->leftJoin('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(m.refcode)'))
            ->leftJoin('items as i', DB::raw('TRIM(i.code)'), '=', DB::raw('TRIM(d.code)'))
            ->whereBetween('m.tdate', [$date1, $date2]);

        if ($refcode !== '') $q->where(DB::raw('UPPER(TRIM(m.refcode))'), $refcode);

        $rows = $q->orderBy('m.tdate')->orderBy('m.slno')->orderBy('d.sno')
            ->get([
                'm.tdate', 'm.docno', 'm.status', 'm.testperc', 'm.expwgt',
                DB::raw('TRIM(COALESCE(c.name, m.refcode)) as refinername'),
                DB::raw('TRIM(COALESCE(i.name, d.code)) as itemname'),
                'd.issuedwgt', 'd.issuedstwgt', 'd.touch',
                'd.rcvdwgt',   'd.rcvdtouch',
                'd.mudless',   'd.testpcs',
            ])
            ->map(function($r) {
                $issuedwgt = round((float)($r->issuedwgt ?? 0), 3);
                $rcvdwgt   = round((float)($r->rcvdwgt   ?? 0), 3);
                $issuedT   = round((float)($r->touch      ?? 0), 2);
                $rcvdT     = round((float)($r->rcvdtouch  ?? 0), 2);
                $losswgt   = round($issuedwgt - $rcvdwgt, 3);
                $touchdiff = round($rcvdT - $issuedT, 2);
                return [
                    'tdate'      => (string)($r->tdate ?? ''),
                    'docno'      => trim((string)($r->docno ?? '')),
                    'status'     => (int)($r->status ?? 1),
                    'status_lbl' => ((int)($r->status ?? 1)) === 2 ? 'Return' : 'Forward',
                    'refinername'=> (string)($r->refinername ?? ''),
                    'itemname'   => (string)($r->itemname ?? ''),
                    'issuedwgt'  => $issuedwgt,
                    'issuedstwgt'=> round((float)($r->issuedstwgt ?? 0), 3),
                    'touch'      => $issuedT,
                    'rcvdwgt'    => $rcvdwgt,
                    'rcvdtouch'  => $rcvdT,
                    'losswgt'    => $losswgt,
                    'touchdiff'  => $touchdiff,
                    'mudless'    => round((float)($r->mudless  ?? 0), 3),
                    'testpcs'    => round((float)($r->testpcs  ?? 0), 3),
                    'testperc'   => round((float)($r->testperc ?? 0), 2),
                    'expwgt'     => round((float)($r->expwgt   ?? 0), 3),
                ];
            })->values()->all();

        $fwd = array_filter($rows, fn($r) => $r['status'] === 1);
        $ret = array_filter($rows, fn($r) => $r['status'] === 2);
        $totals = [
            'count'       => count($rows),
            'issuedwgt'   => round(array_sum(array_column(array_values($fwd), 'issuedwgt')), 3),
            'rcvdwgt'     => round(array_sum(array_column(array_values($ret), 'rcvdwgt')),   3),
            'losswgt'     => round(array_sum(array_column(array_values($fwd), 'issuedwgt')) - array_sum(array_column(array_values($ret), 'rcvdwgt')), 3),
            'mudless'     => round(array_sum(array_column($rows, 'mudless')),  3),
            'testpcs'     => round(array_sum(array_column($rows, 'testpcs')),  3),
        ];

        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }

    // ── A/c Summary ───────────────────────────────────────────────

    public function acSummary(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('refinery-reports.ac-summary', [
            'title'    => (string) $request->query('title', 'Refinery A/c Summary'),
            'moduleId' => (string) $request->query('module', 'refinery-reports-ac-summary'),
            'today'    => $this->today(),
            'refiners' => $this->refiners(),
        ]);
    }

    public function acSummaryData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);

        $date1   = $request->query('date1', $this->today());
        $date2   = $request->query('date2', $this->today());
        $refcode = strtoupper(trim((string) $request->query('refcode', '')));

        if (!$this->hasTable('refinerym') || !$this->hasTable('refineryd')) {
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>[]]);
        }

        $q = DB::table('refinerym as m')
            ->leftJoin('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(m.refcode)'))
            ->whereBetween('m.tdate', [$date1, $date2]);

        if ($refcode !== '') $q->where(DB::raw('UPPER(TRIM(m.refcode))'), $refcode);

        $rows = $q->groupBy('m.refcode')
            ->orderByRaw('MAX(c.name)')
            ->get([
                DB::raw('TRIM(m.refcode) as refcode'),
                DB::raw('MAX(TRIM(COALESCE(c.name, m.refcode))) as refinername'),
                DB::raw('COUNT(DISTINCT CASE WHEN m.status=1 THEN m.slno END) as fwd_bills'),
                DB::raw('COUNT(DISTINCT CASE WHEN m.status=2 THEN m.slno END) as ret_bills'),
                DB::raw('COALESCE(SUM(CASE WHEN m.status=2 THEN m.charge    ELSE 0 END),0) as total_charge'),
                DB::raw('COALESCE(SUM(CASE WHEN m.status=2 THEN m.paidamt   ELSE 0 END),0) as total_paid'),
            ])
            ->map(function($r) {
                $charge = round((float)($r->total_charge ?? 0), 2);
                $paid   = round((float)($r->total_paid   ?? 0), 2);
                return [
                    'refcode'      => (string)($r->refcode ?? ''),
                    'refinername'  => (string)($r->refinername ?? ''),
                    'fwd_bills'    => (int)($r->fwd_bills ?? 0),
                    'ret_bills'    => (int)($r->ret_bills ?? 0),
                    'total_charge' => $charge,
                    'total_paid'   => $paid,
                    'balance'      => round($charge - $paid, 2),
                ];
            })->values()->all();

        $totals = [
            'count'        => count($rows),
            'total_charge' => round(array_sum(array_column($rows, 'total_charge')), 2),
            'total_paid'   => round(array_sum(array_column($rows, 'total_paid')),   2),
            'balance'      => round(array_sum(array_column($rows, 'balance')),       2),
        ];

        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }

    // ── Less Comparison Report ────────────────────────────────────

    public function lessComparison(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('refinery-reports.less-comparison', [
            'title'    => (string) $request->query('title', 'Less Comparison Report'),
            'moduleId' => (string) $request->query('module', 'refinery-reports-less-comparison-report'),
            'today'    => $this->today(),
            'refiners' => $this->refiners(),
        ]);
    }

    public function lessComparisonData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);

        $date1   = $request->query('date1', $this->today());
        $date2   = $request->query('date2', $this->today());
        $refcode = strtoupper(trim((string) $request->query('refcode', '')));

        if (!$this->hasTable('refinerym') || !$this->hasTable('refineryd')) {
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>[]]);
        }

        // Join forward (m) with its return (mr) to compare issued vs received
        $q = DB::table('refinerym as m')
            ->join('refineryd as d', 'd.slno', '=', 'm.slno')
            ->leftJoin('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(m.refcode)'))
            ->leftJoin('items as i', DB::raw('TRIM(i.code)'), '=', DB::raw('TRIM(d.code)'))
            ->where('m.status', 1)
            ->whereBetween('m.tdate', [$date1, $date2]);

        if ($refcode !== '') $q->where(DB::raw('UPPER(TRIM(m.refcode))'), $refcode);

        $rows = $q->orderBy('m.tdate')->orderBy('m.slno')->orderBy('d.sno')
            ->get([
                'm.tdate', 'm.docno', 'm.testperc', 'm.expwgt',
                DB::raw('TRIM(COALESCE(c.name, m.refcode)) as refinername'),
                DB::raw('TRIM(COALESCE(i.name, d.code)) as itemname'),
                'd.issuedwgt', 'd.issuedstwgt', 'd.oissuedwgt',
                'd.mudless',   'd.testpcs',      'd.touch',
                'd.rcvdwgt',   'd.rcvdtouch',
            ])
            ->map(function($r) {
                $issuedwgt = round((float)($r->issuedwgt  ?? 0), 3);
                $rcvdwgt   = round((float)($r->rcvdwgt    ?? 0), 3);
                $mudless   = round((float)($r->mudless    ?? 0), 3);
                $testpcs   = round((float)($r->testpcs    ?? 0), 3);
                $expwgt    = round((float)($r->expwgt     ?? 0), 3);
                // Expected less = issued - expwgt
                $expless   = $expwgt > 0 ? round($issuedwgt - $expwgt, 3) : 0.0;
                // Actual less = issued - received
                $actless   = round($issuedwgt - $rcvdwgt, 3);
                $diff      = round($actless - $expless, 3);
                return [
                    'tdate'       => (string)($r->tdate ?? ''),
                    'docno'       => trim((string)($r->docno ?? '')),
                    'refinername' => (string)($r->refinername ?? ''),
                    'itemname'    => (string)($r->itemname ?? ''),
                    'issuedwgt'   => $issuedwgt,
                    'issuedstwgt' => round((float)($r->issuedstwgt ?? 0), 3),
                    'touch'       => round((float)($r->touch       ?? 0), 2),
                    'rcvdwgt'     => $rcvdwgt,
                    'rcvdtouch'   => round((float)($r->rcvdtouch   ?? 0), 2),
                    'mudless'     => $mudless,
                    'testpcs'     => $testpcs,
                    'expwgt'      => $expwgt,
                    'testperc'    => round((float)($r->testperc    ?? 0), 2),
                    'expless'     => $expless,
                    'actless'     => $actless,
                    'diff'        => $diff,
                ];
            })->values()->all();

        $totals = [
            'count'     => count($rows),
            'issuedwgt' => round(array_sum(array_column($rows, 'issuedwgt')), 3),
            'rcvdwgt'   => round(array_sum(array_column($rows, 'rcvdwgt')),   3),
            'mudless'   => round(array_sum(array_column($rows, 'mudless')),    3),
            'testpcs'   => round(array_sum(array_column($rows, 'testpcs')),    3),
            'expless'   => round(array_sum(array_column($rows, 'expless')),    3),
            'actless'   => round(array_sum(array_column($rows, 'actless')),    3),
            'diff'      => round(array_sum(array_column($rows, 'diff')),       3),
        ];

        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }
}
