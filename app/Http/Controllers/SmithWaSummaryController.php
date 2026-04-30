<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Goldsmith / Jewellery Wgt/Amt Summary (Simple)  (PB: w_smithwasummary / d_smithwasummary)
 *
 * Weights from smithd.netwgt, amounts from daybook.amount.
 * Balance = master opening + all transactions up to rdate.
 * Touch conversion when rtype='Stock'.
 */
class SmithWaSummaryController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $ctype = strtoupper(trim((string) $request->query('ctype', '')));
        if (!in_array($ctype, ['G', 'J', 'C', ''], true)) {
            $ctype = '';
        }

        $titles = ['G' => 'Goldsmith Account Summary', 'J' => 'Jewellery Account Summary', 'C' => 'Party Deposit Account Summary'];
        $title  = $titles[$ctype] ?? 'Account Wgt/Amt Summary';

        $groups = [];
        if ($this->hasTable('clientsgrp')) {
            $groups = DB::table('clientsgrp')->select('code', 'name')->orderBy('code')->get()
                ->map(fn ($r) => ['code' => trim($r->code ?? ''), 'name' => trim($r->name ?? '')])->all();
        }

        return view('reports.smith-wa-summary', [
            'title'  => $title,
            'ctype'  => $ctype,
            'date'   => date('Y-m-d'),
            'rlevel' => (int) ($request->session()->get('gilevel', 1) ?: 1),
            'groups' => $groups,
        ]);
    }

    /**
     * Data endpoint – matches PB d_smithwasummary correlated subqueries.
     */
    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $rdate   = (string) $request->query('date', date('Y-m-d'));
        $ctype   = strtoupper(trim((string) $request->query('ctype', '')));
        $metal   = strtoupper(trim((string) $request->query('metal', 'G')));
        $group   = trim((string) $request->query('group', ''));
        $balOnly = $request->query('balonly', '1') === '1';
        $rlevel  = (int) $request->query('rlevel', (int) ($request->session()->get('gilevel', 1)));
        $rtype   = (string) $request->query('rtype', 'Account'); // Account or Stock
        $rtouch  = (float) $request->query('rtouch', 0);
        $rrate   = (float) $request->query('rrate', 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rdate)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('clients') || !$this->hasTable('clientsgs')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        $hasSmith   = $this->hasTable('smithm') && $this->hasTable('smithd');
        $hasDaybook = $this->hasTable('daybook');

        $opWgtExpr = $rlevel >= 2 ? 'gs.opweightb' : 'gs.opweight';
        $opAmtExpr = $rlevel >= 2 ? 'c.opbalanceb' : 'c.opbalance';

        $query = DB::table('clients as c')
            ->join('clientsgs as gs', 'gs.code', '=', 'c.code')
            ->select([
                DB::raw('TRIM(c.code) as code'),
                DB::raw('TRIM(c.name) as name'),
                DB::raw('TRIM(c.grp) as grp'),
                DB::raw("COALESCE({$opWgtExpr}, 0) as master_opwgt"),
                DB::raw("COALESCE({$opAmtExpr}, 0) as master_opamt"),
                DB::raw('COALESCE(gs.stocktouch, 0) as stocktouch'),
                DB::raw('COALESCE(gs.convtouch, 0) as convtouch'),
            ]);

        // Filter by ctype (PB: rsjtype)
        if (in_array($ctype, ['G', 'J', 'C'], true)) {
            $query->where('gs.ctype', $ctype);
        }

        // Filter by silver flag (metal type)
        $silverFlag = ($metal === 'S') ? 'Y' : 'N';
        $query->where('gs.silver', $silverFlag);

        // Filter by group
        if ($group !== '') {
            $query->where('c.grp', $group);
        }

        // Daybook amount subquery: total amount up to rdate (PB: ttranamt)
        if ($hasDaybook) {
            $query->selectRaw(
                "(SELECT COALESCE(SUM(db.amount),0) FROM daybook db WHERE db.accode = c.code AND db.control <= ? AND db.tdate <= ?) as tranamt",
                [$rlevel, $rdate]
            );
        } else {
            $query->selectRaw('0 as tranamt');
        }

        // Smith weight subqueries (using smithd.netwgt)
        if ($hasSmith) {
            // Received weight up to rdate (PB: trcvdwgt)
            $query->selectRaw(
                "(SELECT COALESCE(SUM(sd.netwgt),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='R' AND sm.control <= ? AND sm.tdate <= ?) as rcvdwgt",
                [$rlevel, $rdate]
            );
            // Issued weight up to rdate (PB: tissuedwgt)
            $query->selectRaw(
                "(SELECT COALESCE(SUM(sd.netwgt),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='G' AND sm.control <= ? AND sm.tdate <= ?) as issuedwgt",
                [$rlevel, $rdate]
            );
            // Last issued date (PB: lastdt – givrec='G')
            $query->selectRaw(
                "(SELECT MAX(sm.tdate) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='G' AND sm.control <= ? AND sm.tdate <= ?) as lastdt",
                [$rlevel, $rdate]
            );
            // Last received date (PB: lastrdt – givrec='R')
            $query->selectRaw(
                "(SELECT MAX(sm.tdate) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='R' AND sm.control <= ? AND sm.tdate <= ?) as lastrdt",
                [$rlevel, $rdate]
            );
        } else {
            $query->selectRaw('0 as rcvdwgt, 0 as issuedwgt, NULL as lastdt, NULL as lastrdt');
        }

        $clients = $query->orderBy('c.name')->get();
        $rows = [];

        foreach ($clients as $r) {
            $mOpWgt = (float) $r->master_opwgt;
            $mOpAmt = (float) $r->master_opamt;
            $rcvdwgt   = (float) $r->rcvdwgt;
            $issuedwgt = (float) $r->issuedwgt;
            $tranamt   = (float) $r->tranamt;
            $stocktouch = (float) $r->stocktouch;
            $convtouch  = (float) $r->convtouch;

            // PB: netwgt = opweight + rcvdwgt - issuedwgt
            $netwgt = $mOpWgt + $rcvdwgt - $issuedwgt;

            // Touch conversion when rtype='Stock' (PB: if rtype='Stock' and stocktouch>0 and rtouch>0 then netwgt * stocktouch/rtouch)
            if ($rtype === 'Stock' && $stocktouch > 0 && $rtouch > 0) {
                $netwgt = $netwgt * $stocktouch / $rtouch;
            }

            $netwgt = round($netwgt, 3);

            // PB: tweightbal = abs(netwgt), displayed as Weight Balance
            $weightbal = round(abs($netwgt), 3);

            // PB: amtbal = opbalance + tranamt
            $amtbal = round($mOpAmt + $tranamt, 2);
            // PB: tbal = abs(amtbal)
            $tbal = round(abs($amtbal), 2);

            // PB: compute_7 = abs(netwgt) * convtouch / 100  (Wgt 100 Balance)
            $wgt100bal = ($convtouch > 0) ? round(abs($netwgt) * $convtouch / 100, 3) : 0;

            // PB: gnetwgt = issuedwgt - opweight - rcvdwgt (used for wgt status filter)
            $gnetwgt = round($issuedwgt - $mOpWgt - $rcvdwgt, 3);
            // After touch conversion the sign of netwgt determines To Give/To Receive
            // netwgt > 0 → "To Give" (we owe them weight), netwgt < 0 → "To Receive"
            $wgtStatus = ($netwgt > 0.0005) ? 'give' : (($netwgt < -0.0005) ? 'get' : '');
            $amtStatus = ($amtbal > 0.005) ? 'give' : (($amtbal < -0.005) ? 'get' : '');

            // Filter: bal only
            if ($balOnly && abs($netwgt) < 0.001 && abs($amtbal) < 0.01) {
                continue;
            }

            $rows[] = [
                'code'       => trim($r->code),
                'name'       => trim($r->name),
                'grp'        => trim($r->grp),
                'netwgt'     => $netwgt,
                'weightbal'  => $weightbal,
                'wgt100bal'  => $wgt100bal,
                'amtbal'     => $amtbal,
                'tbal'       => $tbal,
                'wgtstatus'  => $wgtStatus,
                'amtstatus'  => $amtStatus,
                'lastdt'     => $r->lastdt,
                'lastrdt'    => $r->lastrdt,
                'convtouch'  => $convtouch,
            ];
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows, $rrate),
        ]);
    }

    private function buildTotals(array $rows, float $rrate = 0): array
    {
        $t = $this->emptyTotals();
        $t['count'] = count($rows);
        $toGiveWgt = 0; $toGetWgt = 0;
        $toGiveAmt = 0; $toGetAmt = 0;
        $sumNetwgt = 0; $sumAmtbal = 0;
        $sumWgt100 = 0;

        foreach ($rows as $r) {
            $nw = (float) ($r['netwgt'] ?? 0);
            $ab = (float) ($r['amtbal'] ?? 0);
            $sumNetwgt += $nw;
            $sumAmtbal += $ab;
            $sumWgt100 += (float) ($r['wgt100bal'] ?? 0);

            if ($nw > 0.0005) {
                $toGiveWgt += (float) ($r['weightbal'] ?? 0);
            } elseif ($nw < -0.0005) {
                $toGetWgt += (float) ($r['weightbal'] ?? 0);
            }
            if ($ab > 0.005) {
                $toGiveAmt += (float) ($r['tbal'] ?? 0);
            } elseif ($ab < -0.005) {
                $toGetAmt += (float) ($r['tbal'] ?? 0);
            }
        }

        $t['togivewgt'] = round($toGiveWgt, 3);
        $t['togetwgt']  = round($toGetWgt, 3);
        $t['netwgt']    = round(abs($toGiveWgt - $toGetWgt), 3);
        $t['netwgtstatus'] = ($toGiveWgt > $toGetWgt) ? 'To Give' : (($toGetWgt > $toGiveWgt) ? 'To Get' : '');
        $t['togivamt']  = round($toGiveAmt, 2);
        $t['togetamt']  = round($toGetAmt, 2);
        $t['netamt']    = round(abs($toGiveAmt - $toGetAmt), 2);
        $t['netamtstatus'] = ($toGiveAmt > $toGetAmt) ? 'To Give' : (($toGetAmt > $toGiveAmt) ? 'To Get' : '');
        $t['wgt100bal'] = round($sumWgt100, 3);

        // PB: grand net weight = abs(sum(netwgt) + sum(amtbal)/rrate) when rrate > 0
        if ($rrate > 0) {
            $t['grandnetwgt'] = round(abs($sumNetwgt + $sumAmtbal / $rrate), 3);
        } else {
            $t['grandnetwgt'] = 0;
        }

        return $t;
    }

    private function emptyTotals(): array
    {
        return [
            'count' => 0, 'togivewgt' => 0, 'togetwgt' => 0, 'netwgt' => 0, 'netwgtstatus' => '',
            'togivamt' => 0, 'togetamt' => 0, 'netamt' => 0, 'netamtstatus' => '',
            'wgt100bal' => 0, 'grandnetwgt' => 0,
        ];
    }
}
