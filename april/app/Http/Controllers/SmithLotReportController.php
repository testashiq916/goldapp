<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smith/Jewl Lot Report (PB: w_smithjewl_lot_rep / d_smith_lot_rep)
 *
 * Shows issued/received weight and qty per smith per lot number in a date range.
 * Pending = issued - received.
 * DISTINCT clients × smithm.lotno where lotno <> ''.
 */
class SmithLotReportController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $startDate = (string) $request->session()->get('gdtstartdate', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y') . '-04-01';
        }

        return view('reports.smith-lot-report', [
            'title'  => 'Smith/Jewl Lot Report',
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
        $rlevel = (int) $request->query('rlevel', (int) ($request->session()->get('gilevel', 1)));
        $smith  = trim((string) $request->query('smith', ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('clients') || !$this->hasTable('smithm') || !$this->hasTable('smithd')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        // PB: DISTINCT clients × smithm.lotno where lotno <> ''
        // with correlated subqueries for issued/received wgt/qty per client+lotno
        $query = DB::table('clients as c')
            ->join('smithm as sm', 'sm.smithcode', '=', 'c.code')
            ->where('sm.lotno', '<>', '')
            ->whereNotNull('sm.lotno')
            ->groupBy('c.code', 'c.name', 'sm.lotno')
            ->select([
                DB::raw('TRIM(c.code) as code'),
                DB::raw('TRIM(c.name) as name'),
                DB::raw('TRIM(sm.lotno) as lotno'),
            ]);

        // Issued wgt in period per client+lotno
        $query->selectRaw(
            "COALESCE((SELECT SUM(sd.netwgt) FROM smithd sd JOIN smithm sm2 ON sm2.slno=sd.slno WHERE sm2.smithcode=c.code AND sd.givrec='G' AND sm2.lotno=sm.lotno AND sm2.control <= ? AND sm2.tdate >= ? AND sm2.tdate <= ?), 0) as issuewgt",
            [$rlevel, $date1, $date2]
        );
        // Received wgt in period per client+lotno
        $query->selectRaw(
            "COALESCE((SELECT SUM(sd.netwgt) FROM smithd sd JOIN smithm sm2 ON sm2.slno=sd.slno WHERE sm2.smithcode=c.code AND sd.givrec='R' AND sm2.lotno=sm.lotno AND sm2.control <= ? AND sm2.tdate >= ? AND sm2.tdate <= ?), 0) as rcvdwgt",
            [$rlevel, $date1, $date2]
        );
        // Issued qty in period per client+lotno
        $query->selectRaw(
            "COALESCE((SELECT SUM(sd.qty) FROM smithd sd JOIN smithm sm2 ON sm2.slno=sd.slno WHERE sm2.smithcode=c.code AND sd.givrec='G' AND sm2.lotno=sm.lotno AND sm2.control <= ? AND sm2.tdate >= ? AND sm2.tdate <= ?), 0) as issueqty",
            [$rlevel, $date1, $date2]
        );
        // Received qty in period per client+lotno
        $query->selectRaw(
            "COALESCE((SELECT SUM(sd.qty) FROM smithd sd JOIN smithm sm2 ON sm2.slno=sd.slno WHERE sm2.smithcode=c.code AND sd.givrec='R' AND sm2.lotno=sm.lotno AND sm2.control <= ? AND sm2.tdate >= ? AND sm2.tdate <= ?), 0) as rcvdqty",
            [$rlevel, $date1, $date2]
        );

        if ($smith !== '') {
            $query->where('c.code', $smith);
        }

        $results = $query->orderBy('c.name')->get();
        $rows = [];

        foreach ($results as $r) {
            $iw = round((float) $r->issuewgt, 3);
            $rw = round((float) $r->rcvdwgt, 3);
            $iq = (int) $r->issueqty;
            $rq = (int) $r->rcvdqty;
            $pw = round($iw - $rw, 3);
            $pq = $iq - $rq;

            $rows[] = [
                'code'     => trim($r->code),
                'name'     => trim($r->name),
                'lotno'    => trim($r->lotno),
                'issuewgt' => $iw,
                'rcvdwgt'  => $rw,
                'pendwgt'  => $pw,
                'issueqty' => $iq,
                'rcvdqty'  => $rq,
                'pendqty'  => $pq,
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
            $t['issuewgt'] += (float) ($r['issuewgt'] ?? 0);
            $t['rcvdwgt']  += (float) ($r['rcvdwgt'] ?? 0);
            $t['pendwgt']  += (float) ($r['pendwgt'] ?? 0);
            $t['issueqty'] += (int) ($r['issueqty'] ?? 0);
            $t['rcvdqty']  += (int) ($r['rcvdqty'] ?? 0);
            $t['pendqty']  += (int) ($r['pendqty'] ?? 0);
        }
        return $t;
    }

    private function emptyTotals(): array
    {
        return ['count' => 0, 'issuewgt' => 0, 'rcvdwgt' => 0, 'pendwgt' => 0,
                'issueqty' => 0, 'rcvdqty' => 0, 'pendqty' => 0];
    }
}
