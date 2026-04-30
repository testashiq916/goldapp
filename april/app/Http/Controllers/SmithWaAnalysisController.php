<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Goldsmith / Jewellery Wgt/Amt Analysis (PB: w_smithwasummary2 / d_smithwasummary2)
 *
 * Weights from smithd.netwgt, amounts from daybook.amount.
 * Opening = master opening + prior-period transactions.
 * Closing = master opening + all transactions up to date2.
 */
class SmithWaAnalysisController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $ctype = strtoupper(trim((string) $request->query('ctype', 'G')));
        if (!in_array($ctype, ['G', 'J'], true)) {
            $ctype = 'G';
        }

        $title = $ctype === 'J' ? 'Jewellery Account Analysis' : 'Goldsmith Account Analysis';

        $groups = [];
        if ($this->hasTable('clientsgrp')) {
            $groups = DB::table('clientsgrp')->select('code', 'name')->orderBy('code')->get()
                ->map(fn ($r) => ['code' => trim($r->code ?? ''), 'name' => trim($r->name ?? '')])->all();
        }

        $startDate = (string) $request->session()->get('gdtstartdate', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y') . '-04-01';
        }

        return view('reports.smith-wa-analysis', [
            'title'  => $title,
            'ctype'  => $ctype,
            'date1'  => $startDate,
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) ($request->session()->get('gilevel', 1) ?: 1),
            'groups' => $groups,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1  = (string) $request->query('date1', date('Y-m-d'));
        $date2  = (string) $request->query('date2', date('Y-m-d'));
        $ctype  = strtoupper(trim((string) $request->query('ctype', 'G')));
        $group  = trim((string) $request->query('group', ''));
        $method = (string) $request->query('method', '2');
        $rlevel = (int) $request->query('rlevel', (int) ($request->session()->get('gilevel', 1)));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }
        if (!in_array($ctype, ['G', 'J'], true)) {
            $ctype = 'G';
        }
        if (!$this->hasTable('clients') || !$this->hasTable('clientsgs')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        $hasSmith   = $this->hasTable('smithm') && $this->hasTable('smithd');
        $hasDaybook = $this->hasTable('daybook');

        // Build single query matching PB d_smithwasummary2 with correlated subqueries
        $opWgtExpr = $rlevel >= 2 ? 'gs.opweightb' : 'gs.opweight';
        $opAmtExpr = $rlevel >= 2 ? 'c.opbalanceb' : 'c.opbalance';

        $query = DB::table('clients as c')
            ->join('clientsgs as gs', 'gs.code', '=', 'c.code')
            ->where('c.ctype', $ctype)
            ->select([
                DB::raw('TRIM(c.code) as code'),
                DB::raw('TRIM(c.name) as name'),
                DB::raw('TRIM(c.grp) as grp'),
                DB::raw("COALESCE({$opWgtExpr}, 0) as master_opwgt"),
                DB::raw("COALESCE({$opAmtExpr}, 0) as master_opamt"),
            ]);

        // Daybook amount subqueries
        if ($hasDaybook) {
            // Total amount up to date2  (ttranamt)
            $query->selectRaw("(SELECT COALESCE(SUM(db.amount),0) FROM daybook db WHERE db.accode = c.code AND db.control <= ? AND db.tdate <= ?) as tranamt_cl", [$rlevel, $date2]);
            // Amount before date1  (ttranamtop)
            $query->selectRaw("(SELECT COALESCE(SUM(db.amount),0) FROM daybook db WHERE db.accode = c.code AND db.control <= ? AND db.tdate < ?) as tranamt_op", [$rlevel, $date1]);
            // Debit amount in period  (ttranamtdb) — abs of negative amounts
            $query->selectRaw("(SELECT COALESCE(SUM(ABS(db.amount)),0) FROM daybook db WHERE db.accode = c.code AND db.control <= ? AND db.tdate >= ? AND db.tdate <= ? AND db.amount < 0) as amt_debit", [$rlevel, $date1, $date2]);
            // Credit amount in period  (ttranamtcr)
            $query->selectRaw("(SELECT COALESCE(SUM(db.amount),0) FROM daybook db WHERE db.accode = c.code AND db.control <= ? AND db.tdate >= ? AND db.tdate <= ? AND db.amount > 0) as amt_credit", [$rlevel, $date1, $date2]);
        } else {
            $query->selectRaw('0 as tranamt_cl, 0 as tranamt_op, 0 as amt_debit, 0 as amt_credit');
        }

        // Smith weight subqueries (using smithd.netwgt)
        if ($hasSmith) {
            // Received wgt before date1 (trcvdwgtop)
            $query->selectRaw("(SELECT COALESCE(SUM(sd.netwgt),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='R' AND sm.control <= ? AND sm.tdate < ?) as rcvdwgt_op", [$rlevel, $date1]);
            // Issued wgt before date1 (tissuedwgtop)
            $query->selectRaw("(SELECT COALESCE(SUM(sd.netwgt),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='G' AND sm.control <= ? AND sm.tdate < ?) as issuedwgt_op", [$rlevel, $date1]);
            // Received wgt up to date2 (trcvdwgtcl)
            $query->selectRaw("(SELECT COALESCE(SUM(sd.netwgt),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='R' AND sm.control <= ? AND sm.tdate <= ?) as rcvdwgt_cl", [$rlevel, $date2]);
            // Issued wgt up to date2 (tissuedwgtcl)
            $query->selectRaw("(SELECT COALESCE(SUM(sd.netwgt),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='G' AND sm.control <= ? AND sm.tdate <= ?) as issuedwgt_cl", [$rlevel, $date2]);
            // Period issued wgt (tissuedwgt)
            $query->selectRaw("(SELECT COALESCE(SUM(sd.netwgt),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='G' AND sm.control <= ? AND sm.tdate >= ? AND sm.tdate <= ?) as issuedwgt", [$rlevel, $date1, $date2]);
            // Period received wgt (trcvdwgt)
            $query->selectRaw("(SELECT COALESCE(SUM(sd.netwgt),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='R' AND sm.control <= ? AND sm.tdate >= ? AND sm.tdate <= ?) as rcvdwgt", [$rlevel, $date1, $date2]);
        } else {
            $query->selectRaw('0 as rcvdwgt_op, 0 as issuedwgt_op, 0 as rcvdwgt_cl, 0 as issuedwgt_cl, 0 as issuedwgt, 0 as rcvdwgt');
        }

        if ($group !== '') {
            $query->where('c.grp', $group);
        }

        $clients = $query->orderBy('c.name')->get();
        $rows = [];

        foreach ($clients as $r) {
            $mOpWgt = (float) $r->master_opwgt;
            $mOpAmt = (float) $r->master_opamt;

            // PB: opwgt = master_opwgt + rcvdwgt_op - issuedwgt_op
            $opwgt = round($mOpWgt + (float) $r->rcvdwgt_op - (float) $r->issuedwgt_op, 3);
            // PB: opamt = master_opamt + tranamt_op
            $opamt = round($mOpAmt + (float) $r->tranamt_op, 2);

            $issuedwgt = round((float) $r->issuedwgt, 3);
            $rcvdwgt   = round((float) $r->rcvdwgt, 3);
            $amtDebit  = round((float) $r->amt_debit, 2);
            $amtCredit = round((float) $r->amt_credit, 2);

            // PB: clwgt = master_opwgt + rcvdwgt_cl - issuedwgt_cl
            $clwgt = round($mOpWgt + (float) $r->rcvdwgt_cl - (float) $r->issuedwgt_cl, 3);
            // PB: clamt = master_opamt + tranamt_cl
            $clamt = round($mOpAmt + (float) $r->tranamt_cl, 2);

            // PB: wgtdiff = if method='1' then issuedwgt + clwgt, else rcvdwgt - issuedwgt
            $wgtdiff = ($method === '1')
                ? round($issuedwgt + $clwgt, 3)
                : round($rcvdwgt - $issuedwgt, 3);

            $rows[] = [
                'code'      => trim($r->code),
                'name'      => trim($r->name),
                'grp'       => trim($r->grp),
                'opwgt'     => $opwgt,
                'opamt'     => $opamt,
                'issuedwgt' => $issuedwgt,
                'amtdebit'  => $amtDebit,
                'rcvdwgt'   => $rcvdwgt,
                'amtcredit' => $amtCredit,
                'wgtdiff'   => $wgtdiff,
                'clwgt'     => $clwgt,
                'clamt'     => $clamt,
            ];
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
        ]);
    }

    private function buildTotals(array $rows): array
    {
        $t = $this->emptyTotals();
        $t['count'] = count($rows);
        foreach ($rows as $r) {
            $t['opwgt']     += (float) ($r['opwgt'] ?? 0);
            $t['issuedwgt'] += (float) ($r['issuedwgt'] ?? 0);
            $t['amtdebit']  += (float) ($r['amtdebit'] ?? 0);
            $t['rcvdwgt']   += (float) ($r['rcvdwgt'] ?? 0);
            $t['amtcredit'] += (float) ($r['amtcredit'] ?? 0);
            $t['wgtdiff']   += (float) ($r['wgtdiff'] ?? 0);
            $t['clwgt']     += (float) ($r['clwgt'] ?? 0);
        }
        return $t;
    }

    private function emptyTotals(): array
    {
        return ['count' => 0, 'opwgt' => 0, 'issuedwgt' => 0, 'amtdebit' => 0,
                'rcvdwgt' => 0, 'amtcredit' => 0, 'wgtdiff' => 0, 'clwgt' => 0];
    }
}
