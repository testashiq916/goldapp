<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Goldsmith / Jewellery Transaction Summary (PB: w_smithmcsummary / d_smithmcsummary)
 *
 * Period-based summary per client: received wgt, issued wgt, wastage,
 * making charge, stone amount, paid amount.
 */
class SmithTransSummaryController extends Controller
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

        $title = $ctype === 'J' ? 'Jewellery Transaction Summary' : 'Goldsmith Transaction Summary';

        $startDate = (string) $request->session()->get('gdtstartdate', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y') . '-04-01';
        }

        return view('reports.smith-trans-summary', [
            'title'  => $title,
            'ctype'  => $ctype,
            'date1'  => $startDate,
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) ($request->session()->get('gilevel', 1) ?: 1),
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
        $rlevel = (int) $request->query('rlevel', (int) ($request->session()->get('gilevel', 1)));
        $withTran = $request->query('withtran', '1') === '1';

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

        $query = DB::table('clients as c')
            ->join('clientsgs as gs', 'gs.code', '=', 'c.code')
            ->where('c.ctype', $ctype)
            ->select([
                DB::raw('TRIM(c.code) as code'),
                DB::raw('TRIM(c.name) as name'),
            ]);

        // Period smith subqueries
        if ($hasSmith) {
            // Received wgt in period (givrec='R')
            $query->selectRaw(
                "(SELECT COALESCE(SUM(sd.netwgt),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='R' AND sm.control <= ? AND sm.tdate >= ? AND sm.tdate <= ?) as rcvdwgt1",
                [$rlevel, $date1, $date2]
            );
            // Issued wgt in period (givrec='G')
            $query->selectRaw(
                "(SELECT COALESCE(SUM(sd.netwgt),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='G' AND sm.control <= ? AND sm.tdate >= ? AND sm.tdate <= ?) as issuedwgt1",
                [$rlevel, $date1, $date2]
            );
            // Wastage in period (givrec='R' — wastage on received items)
            $query->selectRaw(
                "(SELECT COALESCE(SUM(sd.wastage),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sd.givrec='R' AND sm.control <= ? AND sm.tdate >= ? AND sm.tdate <= ?) as wastage1",
                [$rlevel, $date1, $date2]
            );
            // Making charge in period (all givrec)
            $query->selectRaw(
                "(SELECT COALESCE(SUM(sd.mcharge),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sm.control <= ? AND sm.tdate >= ? AND sm.tdate <= ?) as mcharge1",
                [$rlevel, $date1, $date2]
            );
            // Stone amount in period (all givrec)
            $query->selectRaw(
                "(SELECT COALESCE(SUM(sd.stoneprice),0) FROM smithd sd JOIN smithm sm ON sm.slno=sd.slno WHERE sm.smithcode=c.code AND sm.control <= ? AND sm.tdate >= ? AND sm.tdate <= ?) as stamt1",
                [$rlevel, $date1, $date2]
            );
        } else {
            $query->selectRaw('0 as rcvdwgt1, 0 as issuedwgt1, 0 as wastage1, 0 as mcharge1, 0 as stamt1');
        }

        // Paid amount in period = abs of negative daybook amounts (PB: tissuedamt1)
        if ($hasDaybook) {
            $query->selectRaw(
                "(SELECT COALESCE(SUM(db.amount),0) FROM daybook db WHERE db.accode=c.code AND db.control <= ? AND db.amount < 0 AND db.tdate >= ? AND db.tdate <= ?) as paidamt_raw",
                [$rlevel, $date1, $date2]
            );
        } else {
            $query->selectRaw('0 as paidamt_raw');
        }

        $clients = $query->orderBy('c.name')->get();
        $rows = [];

        foreach ($clients as $r) {
            $rcvdwgt1  = round((float) $r->rcvdwgt1, 3);
            $issuedwgt1 = round((float) $r->issuedwgt1, 3);
            $wastage1  = round((float) $r->wastage1, 3);
            $mcharge1  = round((float) $r->mcharge1, 2);
            $stamt1    = round((float) $r->stamt1, 2);
            $paidamt   = round(abs((float) $r->paidamt_raw), 2); // PB: abs(tissuedamt1)
            $mcstamt   = round($mcharge1 + $stamt1, 2);          // PB: totamt = mcharge1 + stamt

            // PB: tottran = rcvdwgt1 + issuedwgt1 + totpaid (used for filter)
            $tottran = abs($rcvdwgt1) + abs($issuedwgt1) + $paidamt;

            if ($withTran && $tottran <= 0) {
                continue;
            }

            $rows[] = [
                'code'       => trim($r->code),
                'name'       => trim($r->name),
                'rcvdwgt1'   => $rcvdwgt1,
                'wastage1'   => $wastage1,
                'mcharge1'   => $mcharge1,
                'stamt1'     => $stamt1,
                'mcstamt'    => $mcstamt,
                'paidamt'    => $paidamt,
                'issuedwgt1' => $issuedwgt1,
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
            $t['rcvdwgt1']   += (float) ($r['rcvdwgt1'] ?? 0);
            $t['wastage1']   += (float) ($r['wastage1'] ?? 0);
            $t['mcharge1']   += (float) ($r['mcharge1'] ?? 0);
            $t['stamt1']     += (float) ($r['stamt1'] ?? 0);
            $t['mcstamt']    += (float) ($r['mcstamt'] ?? 0);
            $t['paidamt']    += (float) ($r['paidamt'] ?? 0);
            $t['issuedwgt1'] += (float) ($r['issuedwgt1'] ?? 0);
        }
        return $t;
    }

    private function emptyTotals(): array
    {
        return [
            'count' => 0, 'rcvdwgt1' => 0, 'wastage1' => 0, 'mcharge1' => 0,
            'stamt1' => 0, 'mcstamt' => 0, 'paidamt' => 0, 'issuedwgt1' => 0,
        ];
    }
}
