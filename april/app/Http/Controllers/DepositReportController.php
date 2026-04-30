<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DepositReportController extends Controller
{
    private function auth(Request $request): bool
    {
        return $request->session()->has('user_code');
    }

    private function today(): string { return date('Y-m-d'); }

    private function rlevel(Request $request): int
    {
        $v = (int) $request->session()->get('gilevel', 2);
        return $v > 0 ? $v : 2;
    }

    /** Get all depositors (clients.ctype = 'C') */
    private function depositors(): array
    {
        if (!$this->hasTable('clients')) return [];
        return DB::table('clients')
            ->where(DB::raw("UPPER(TRIM(COALESCE(ctype,'')))"), 'C')
            ->orderBy('name')
            ->get(['code','name'])
            ->toArray();
    }

    // ────────────────────────────────────────────────────────────
    // PARTY WGT DEPOSIT REPORTS  (smithm / smithd, ctype='C')
    // ────────────────────────────────────────────────────────────

    // ── 1. Transactions ──────────────────────────────────────────

    public function transactions(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('deposit-reports.transactions', [
            'title'      => (string) $request->query('title', 'Party Wgt Deposit – Transactions'),
            'moduleId'   => (string) $request->query('module', 'party-wgt-deposit-reports-transactions'),
            'today'      => $this->today(),
            'depositors' => $this->depositors(),
        ]);
    }

    public function transactionsData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);

        $date1   = $request->query('date1', $this->today());
        $date2   = $request->query('date2', $this->today());
        $pcode   = strtoupper(trim((string) $request->query('pcode', '')));
        $givrec  = strtoupper(trim((string) $request->query('givrec', ''))); // '' | 'G' | 'R'
        $rlevel  = $this->rlevel($request);

        if (!$this->hasTable('smithm') || !$this->hasTable('smithd')) {
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>[]]);
        }

        $q = DB::table('smithm as m')
            ->join('smithd as d', 'd.slno', '=', 'm.slno')
            ->join('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(m.smithcode)'))
            ->leftJoin('items as i', DB::raw('TRIM(i.code)'), '=', DB::raw('TRIM(d.code)'))
            ->where(DB::raw("UPPER(TRIM(c.ctype))"), 'C')
            ->where('m.control', '<=', $rlevel)
            ->whereBetween('m.tdate', [$date1, $date2]);

        if ($pcode !== '') $q->where(DB::raw('UPPER(TRIM(m.smithcode))'), $pcode);
        if ($givrec !== '') $q->where(DB::raw('UPPER(TRIM(d.givrec))'), $givrec);

        $rows = $q->orderBy('m.tdate')->orderBy('m.slno')->orderBy('d.sno')
            ->get([
                'm.tdate', 'm.docno', 'm.smithcode',
                DB::raw('TRIM(COALESCE(c.name, m.smithcode)) as partyname'),
                'd.givrec',
                DB::raw('TRIM(COALESCE(i.name, d.code)) as itemname'),
                DB::raw('TRIM(d.code) as itemcode'),
                'd.qty', 'd.weight', 'd.stonewgt', 'd.netwgt', 'd.stktype', 'd.stktouch',
            ])
            ->map(function($r) {
                $givrec = strtoupper(trim((string)($r->givrec ?? '')));
                return [
                    'tdate'     => (string)($r->tdate ?? ''),
                    'docno'     => trim((string)($r->docno ?? '')),
                    'smithcode' => trim((string)($r->smithcode ?? '')),
                    'partyname' => (string)($r->partyname ?? ''),
                    'givrec'    => $givrec,
                    'givrec_lbl'=> $givrec === 'R' ? 'Deposit' : 'Withdraw',
                    'itemname'  => (string)($r->itemname ?? ''),
                    'itemcode'  => (string)($r->itemcode ?? ''),
                    'qty'       => (int)($r->qty ?? 0),
                    'weight'    => round((float)($r->weight ?? 0), 3),
                    'stonewgt'  => round((float)($r->stonewgt ?? 0), 3),
                    'netwgt'    => round((float)($r->netwgt ?? 0), 3),
                    'stktype'   => trim((string)($r->stktype ?? '')),
                    'stktouch'  => round((float)($r->stktouch ?? 0), 2),
                ];
            })->values()->all();

        $dep = array_filter($rows, fn($r) => $r['givrec'] === 'R');
        $wit = array_filter($rows, fn($r) => $r['givrec'] === 'G');
        $totals = [
            'count'         => count($rows),
            'dep_weight'    => round(array_sum(array_column(array_values($dep), 'weight')), 3),
            'dep_netwgt'    => round(array_sum(array_column(array_values($dep), 'netwgt')), 3),
            'wit_weight'    => round(array_sum(array_column(array_values($wit), 'weight')), 3),
            'wit_netwgt'    => round(array_sum(array_column(array_values($wit), 'netwgt')), 3),
        ];

        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }

    // ── 2. Depositer Ledger ───────────────────────────────────────

    public function depositerLedger(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('deposit-reports.depositer-ledger', [
            'title'      => (string) $request->query('title', 'Depositer Ledger'),
            'moduleId'   => (string) $request->query('module', 'party-wgt-deposit-reports-depositer-ledger'),
            'today'      => $this->today(),
            'depositors' => $this->depositors(),
        ]);
    }

    public function depositerLedgerData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);

        $date1  = $request->query('date1', $this->today());
        $date2  = $request->query('date2', $this->today());
        $pcode  = strtoupper(trim((string) $request->query('pcode', '')));
        $rlevel = $this->rlevel($request);

        if (!$this->hasTable('smithm') || !$this->hasTable('smithd')) {
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>[]]);
        }

        // Opening balance (before date1) — aggregate by party
        $opQ = DB::table('smithm as m')
            ->join('smithd as d', 'd.slno', '=', 'm.slno')
            ->join('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(m.smithcode)'))
            ->where(DB::raw("UPPER(TRIM(c.ctype))"), 'C')
            ->where('m.control', '<=', $rlevel)
            ->where('m.tdate', '<', $date1);
        if ($pcode !== '') $opQ->where(DB::raw('UPPER(TRIM(m.smithcode))'), $pcode);

        $opData = $opQ->groupBy('m.smithcode')
            ->get([
                DB::raw('TRIM(m.smithcode) as smithcode'),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(d.givrec))='R' THEN d.weight ELSE 0 END),0) as dep_wgt"),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(d.givrec))='G' THEN d.weight ELSE 0 END),0) as wit_wgt"),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(d.givrec))='R' THEN d.netwgt ELSE 0 END),0) as dep_net"),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(d.givrec))='G' THEN d.netwgt ELSE 0 END),0) as wit_net"),
            ]);

        $openingByParty = [];
        foreach ($opData as $op) {
            $code = strtoupper(trim((string)$op->smithcode));
            $openingByParty[$code] = [
                'wgt' => round((float)$op->dep_wgt - (float)$op->wit_wgt, 3),
                'net' => round((float)$op->dep_net - (float)$op->wit_net, 3),
            ];
        }

        // Transactions within date range
        $q = DB::table('smithm as m')
            ->join('smithd as d', 'd.slno', '=', 'm.slno')
            ->join('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(m.smithcode)'))
            ->leftJoin('items as i', DB::raw('TRIM(i.code)'), '=', DB::raw('TRIM(d.code)'))
            ->where(DB::raw("UPPER(TRIM(c.ctype))"), 'C')
            ->where('m.control', '<=', $rlevel)
            ->whereBetween('m.tdate', [$date1, $date2]);
        if ($pcode !== '') $q->where(DB::raw('UPPER(TRIM(m.smithcode))'), $pcode);

        $txns = $q->orderBy('m.tdate')->orderBy('m.slno')->orderBy('d.sno')
            ->get([
                'm.tdate', 'm.docno', 'm.smithcode', 'd.givrec',
                DB::raw('TRIM(COALESCE(c.name, m.smithcode)) as partyname'),
                DB::raw('TRIM(COALESCE(i.name, d.code)) as itemname'),
                'd.weight', 'd.netwgt',
            ]);

        // Build per-party running ledger
        $rows = [];
        $partyBalance = $openingByParty; // running balance

        foreach ($txns as $t) {
            $code   = strtoupper(trim((string)$t->smithcode));
            $givrec = strtoupper(trim((string)($t->givrec ?? '')));
            $wgt    = round((float)($t->weight ?? 0), 3);
            $net    = round((float)($t->netwgt ?? 0), 3);

            if (!isset($partyBalance[$code])) {
                $partyBalance[$code] = ['wgt'=>0.0, 'net'=>0.0];
            }

            $depWgt = 0.0; $witWgt = 0.0;
            $depNet = 0.0; $witNet = 0.0;
            if ($givrec === 'R') {
                $depWgt = $wgt; $depNet = $net;
                $partyBalance[$code]['wgt'] = round($partyBalance[$code]['wgt'] + $wgt, 3);
                $partyBalance[$code]['net'] = round($partyBalance[$code]['net'] + $net, 3);
            } else {
                $witWgt = $wgt; $witNet = $net;
                $partyBalance[$code]['wgt'] = round($partyBalance[$code]['wgt'] - $wgt, 3);
                $partyBalance[$code]['net'] = round($partyBalance[$code]['net'] - $net, 3);
            }

            $rows[] = [
                'tdate'     => (string)($t->tdate ?? ''),
                'docno'     => trim((string)($t->docno ?? '')),
                'smithcode' => $code,
                'partyname' => (string)($t->partyname ?? ''),
                'itemname'  => (string)($t->itemname ?? ''),
                'givrec'    => $givrec,
                'givrec_lbl'=> $givrec === 'R' ? 'Deposit' : 'Withdraw',
                'dep_wgt'   => $depWgt,
                'wit_wgt'   => $witWgt,
                'dep_net'   => $depNet,
                'wit_net'   => $witNet,
                'bal_wgt'   => $partyBalance[$code]['wgt'],
                'bal_net'   => $partyBalance[$code]['net'],
            ];
        }

        // Build opening rows per party (at the start)
        $opRows = [];
        if ($pcode !== '' && isset($openingByParty[$pcode])) {
            $ob = $openingByParty[$pcode];
            $opRows[] = [
                'tdate'=>'', 'docno'=>'Opening Balance', 'smithcode'=>$pcode,
                'partyname'=>'', 'itemname'=>'',
                'givrec'=>'', 'givrec_lbl'=>'',
                'dep_wgt'=>0.0, 'wit_wgt'=>0.0, 'dep_net'=>0.0, 'wit_net'=>0.0,
                'bal_wgt'=>$ob['wgt'], 'bal_net'=>$ob['net'],
                'is_opening'=>true,
            ];
        }

        $allRows = array_merge($opRows, $rows);

        $totals = [
            'count'    => count($rows),
            'dep_wgt'  => round(array_sum(array_column($rows, 'dep_wgt')), 3),
            'wit_wgt'  => round(array_sum(array_column($rows, 'wit_wgt')), 3),
            'dep_net'  => round(array_sum(array_column($rows, 'dep_net')), 3),
            'wit_net'  => round(array_sum(array_column($rows, 'wit_net')), 3),
        ];

        return response()->json(['ok'=>true,'rows'=>$allRows,'totals'=>$totals]);
    }

    // ── 3. Wgt/Amt Summary ────────────────────────────────────────

    public function wgtAmtSummary(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('deposit-reports.wgt-amt-summary', [
            'title'    => (string) $request->query('title', 'Wgt/Amt Summary'),
            'moduleId' => (string) $request->query('module', 'party-wgt-deposit-reports-wgt-amt-summary'),
            'today'    => $this->today(),
        ]);
    }

    public function wgtAmtSummaryData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);

        $date1  = $request->query('date1', $this->today());
        $date2  = $request->query('date2', $this->today());
        $rlevel = $this->rlevel($request);

        if (!$this->hasTable('smithm') || !$this->hasTable('smithd')) {
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>[]]);
        }

        $rows = DB::table('smithm as m')
            ->join('smithd as d', 'd.slno', '=', 'm.slno')
            ->join('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(m.smithcode)'))
            ->where(DB::raw("UPPER(TRIM(c.ctype))"), 'C')
            ->where('m.control', '<=', $rlevel)
            ->whereBetween('m.tdate', [$date1, $date2])
            ->groupBy('m.smithcode')
            ->orderByRaw('MAX(c.name)')
            ->get([
                DB::raw('TRIM(m.smithcode) as smithcode'),
                DB::raw('MAX(TRIM(COALESCE(c.name, m.smithcode))) as partyname'),
                DB::raw('COUNT(DISTINCT m.slno) as total_txns'),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(d.givrec))='R' THEN d.weight ELSE 0 END),0) as dep_wgt"),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(d.givrec))='R' THEN d.netwgt ELSE 0 END),0) as dep_net"),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(d.givrec))='G' THEN d.weight ELSE 0 END),0) as wit_wgt"),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(d.givrec))='G' THEN d.netwgt ELSE 0 END),0) as wit_net"),
            ])
            ->map(function($r) {
                $depWgt = round((float)($r->dep_wgt ?? 0), 3);
                $witWgt = round((float)($r->wit_wgt ?? 0), 3);
                $depNet = round((float)($r->dep_net ?? 0), 3);
                $witNet = round((float)($r->wit_net ?? 0), 3);
                return [
                    'smithcode'  => trim((string)($r->smithcode ?? '')),
                    'partyname'  => (string)($r->partyname ?? ''),
                    'total_txns' => (int)($r->total_txns ?? 0),
                    'dep_wgt'    => $depWgt,
                    'dep_net'    => $depNet,
                    'wit_wgt'    => $witWgt,
                    'wit_net'    => $witNet,
                    'bal_wgt'    => round($depWgt - $witWgt, 3),
                    'bal_net'    => round($depNet - $witNet, 3),
                ];
            })->values()->all();

        $totals = [
            'count'   => count($rows),
            'dep_wgt' => round(array_sum(array_column($rows, 'dep_wgt')), 3),
            'dep_net' => round(array_sum(array_column($rows, 'dep_net')), 3),
            'wit_wgt' => round(array_sum(array_column($rows, 'wit_wgt')), 3),
            'wit_net' => round(array_sum(array_column($rows, 'wit_net')), 3),
            'bal_wgt' => round(array_sum(array_column($rows, 'bal_wgt')), 3),
            'bal_net' => round(array_sum(array_column($rows, 'bal_net')), 3),
        ];

        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }

    // ────────────────────────────────────────────────────────────
    // PARTNERS DEPOSIT (WGT) REPORTS  (wgtrcptpmnt)
    // ────────────────────────────────────────────────────────────

    // ── 4. Deposit Book ───────────────────────────────────────────

    public function depositBook(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('deposit-reports.deposit-book', [
            'title'    => (string) $request->query('title', 'Deposit Book'),
            'moduleId' => (string) $request->query('module', 'partners-deposit-reports-deposit-book'),
            'today'    => $this->today(),
        ]);
    }

    public function depositBookData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);

        $date1  = $request->query('date1', $this->today());
        $date2  = $request->query('date2', $this->today());
        $pcode  = strtoupper(trim((string) $request->query('pcode', '')));
        $ttype  = strtoupper(trim((string) $request->query('ttype', ''))); // '' | 'R' | 'P'
        $rlevel = $this->rlevel($request);

        if (!$this->hasTable('wgtrcptpmnt')) {
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>[]]);
        }

        $q = DB::table('wgtrcptpmnt as w')
            ->leftJoin('items as i', DB::raw('UPPER(TRIM(i.code))'), '=', DB::raw('UPPER(TRIM(w.icode))'))
            ->where('w.control', '<=', $rlevel)
            ->whereBetween('w.tdate', [$date1, $date2]);

        if ($pcode !== '') $q->where(DB::raw('UPPER(TRIM(w.pcode))'), $pcode);
        if ($ttype !== '') $q->where(DB::raw('UPPER(TRIM(w.ttype))'), $ttype);

        $rows = $q->orderBy('w.tdate')->orderBy('w.slno')
            ->get([
                'w.slno', 'w.docno', 'w.tdate', 'w.pcode', 'w.pname',
                'w.icode', 'w.qty', 'w.weight', 'w.stwgt', 'w.netwgt',
                'w.touch', 'w.ctouch', 'w.stktype', 'w.ttype',
                'w.grate', 'w.finamt', 'w.rpamt', 'w.intperc', 'w.intamt',
                'w.note', 'w.smcode', 'w.fin', 'w.pend',
                DB::raw('TRIM(COALESCE(i.name, w.icode)) as iname'),
            ])
            ->map(function($r) {
                $ttype = strtoupper(trim((string)($r->ttype ?? '')));
                return [
                    'slno'    => (int)($r->slno ?? 0),
                    'docno'   => trim((string)($r->docno ?? '')),
                    'tdate'   => (string)($r->tdate ?? ''),
                    'pcode'   => trim((string)($r->pcode ?? '')),
                    'pname'   => trim((string)($r->pname ?? '')),
                    'icode'   => trim((string)($r->icode ?? '')),
                    'iname'   => (string)($r->iname ?? ''),
                    'qty'     => (int)($r->qty ?? 0),
                    'weight'  => round((float)($r->weight ?? 0), 3),
                    'stwgt'   => round((float)($r->stwgt ?? 0), 3),
                    'netwgt'  => round((float)($r->netwgt ?? 0), 3),
                    'touch'   => round((float)($r->touch ?? 0), 3),
                    'ctouch'  => round((float)($r->ctouch ?? 0), 3),
                    'stktype' => trim((string)($r->stktype ?? '')),
                    'ttype'   => $ttype,
                    'ttype_lbl'=> $ttype === 'R' ? 'Receipt' : 'Payment',
                    'grate'   => round((float)($r->grate ?? 0), 2),
                    'finamt'  => round((float)($r->finamt ?? 0), 2),
                    'rpamt'   => round((float)($r->rpamt ?? 0), 2),
                    'intperc' => round((float)($r->intperc ?? 0), 2),
                    'intamt'  => round((float)($r->intamt ?? 0), 2),
                    'note'    => trim((string)($r->note ?? '')),
                    'smcode'  => trim((string)($r->smcode ?? '')),
                    'fin'     => trim((string)($r->fin ?? '')),
                    'pend'    => trim((string)($r->pend ?? '')),
                ];
            })->values()->all();

        $rcpt = array_filter($rows, fn($r) => $r['ttype'] === 'R');
        $pmnt = array_filter($rows, fn($r) => $r['ttype'] === 'P');
        $totals = [
            'count'      => count($rows),
            'rcpt_wgt'   => round(array_sum(array_column(array_values($rcpt), 'weight')), 3),
            'rcpt_net'   => round(array_sum(array_column(array_values($rcpt), 'netwgt')), 3),
            'pmnt_wgt'   => round(array_sum(array_column(array_values($pmnt), 'weight')), 3),
            'pmnt_net'   => round(array_sum(array_column(array_values($pmnt), 'netwgt')), 3),
            'intamt'     => round(array_sum(array_column($rows, 'intamt')), 2),
        ];

        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }

    // ── 5. Wgt Balance Summary ────────────────────────────────────

    public function wgtBalanceSummary(Request $request)
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('deposit-reports.wgt-balance-summary', [
            'title'    => (string) $request->query('title', 'Wgt Balance Summary'),
            'moduleId' => (string) $request->query('module', 'partners-deposit-reports-wgt-balance-summary'),
            'today'    => $this->today(),
        ]);
    }

    public function wgtBalanceSummaryData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);

        $date1  = $request->query('date1', $this->today());
        $date2  = $request->query('date2', $this->today());
        $rlevel = $this->rlevel($request);

        if (!$this->hasTable('wgtrcptpmnt')) {
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>[]]);
        }

        $rows = DB::table('wgtrcptpmnt as w')
            ->where('w.control', '<=', $rlevel)
            ->whereBetween('w.tdate', [$date1, $date2])
            ->groupBy('w.pcode')
            ->orderByRaw('MAX(w.pname)')
            ->get([
                DB::raw('TRIM(w.pcode) as pcode'),
                DB::raw('MAX(TRIM(w.pname)) as pname'),
                DB::raw('COUNT(DISTINCT w.slno) as total_txns'),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(w.ttype))='R' THEN w.qty    ELSE 0 END),0) as rcpt_qty"),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(w.ttype))='R' THEN w.weight ELSE 0 END),0) as rcpt_wgt"),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(w.ttype))='R' THEN w.netwgt ELSE 0 END),0) as rcpt_net"),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(w.ttype))='P' THEN w.qty    ELSE 0 END),0) as pmnt_qty"),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(w.ttype))='P' THEN w.weight ELSE 0 END),0) as pmnt_wgt"),
                DB::raw("COALESCE(SUM(CASE WHEN UPPER(TRIM(w.ttype))='P' THEN w.netwgt ELSE 0 END),0) as pmnt_net"),
                DB::raw("COALESCE(SUM(w.intamt),0) as total_int"),
                DB::raw("COALESCE(SUM(w.finamt),0) as total_finamt"),
            ])
            ->map(function($r) {
                $rcptWgt = round((float)($r->rcpt_wgt ?? 0), 3);
                $pmntWgt = round((float)($r->pmnt_wgt ?? 0), 3);
                $rcptNet = round((float)($r->rcpt_net ?? 0), 3);
                $pmntNet = round((float)($r->pmnt_net ?? 0), 3);
                return [
                    'pcode'      => (string)($r->pcode ?? ''),
                    'pname'      => (string)($r->pname ?? ''),
                    'total_txns' => (int)($r->total_txns ?? 0),
                    'rcpt_qty'   => (int)($r->rcpt_qty ?? 0),
                    'rcpt_wgt'   => $rcptWgt,
                    'rcpt_net'   => $rcptNet,
                    'pmnt_qty'   => (int)($r->pmnt_qty ?? 0),
                    'pmnt_wgt'   => $pmntWgt,
                    'pmnt_net'   => $pmntNet,
                    'bal_wgt'    => round($rcptWgt - $pmntWgt, 3),
                    'bal_net'    => round($rcptNet - $pmntNet, 3),
                    'total_int'  => round((float)($r->total_int ?? 0), 2),
                    'total_finamt'=> round((float)($r->total_finamt ?? 0), 2),
                ];
            })->values()->all();

        $totals = [
            'count'       => count($rows),
            'rcpt_wgt'    => round(array_sum(array_column($rows, 'rcpt_wgt')), 3),
            'rcpt_net'    => round(array_sum(array_column($rows, 'rcpt_net')), 3),
            'pmnt_wgt'    => round(array_sum(array_column($rows, 'pmnt_wgt')), 3),
            'pmnt_net'    => round(array_sum(array_column($rows, 'pmnt_net')), 3),
            'bal_wgt'     => round(array_sum(array_column($rows, 'bal_wgt')),  3),
            'bal_net'     => round(array_sum(array_column($rows, 'bal_net')),  3),
            'total_int'   => round(array_sum(array_column($rows, 'total_int')), 2),
        ];

        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }
}
