<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jewellery Transaction Summary / Profit Report (PB: w_jewlprof_rep / d_jewlprof_rep)
 *
 * Per-jeweller summary: issued weight, stone weight, net weight,
 * jewellery MC, smith MC, and profit (jewlmc - smithmc).
 * Only clients with ctype='J' and having transactions (trn <> 0).
 */
class JewlProfitReportController extends Controller
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

        return view('reports.jewl-profit-report', [
            'title'  => 'Jewellery Transaction Summary Report',
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
        $code   = trim((string) $request->query('code', ''));
        $rlevel = (int) $request->query('rlevel', (int) ($request->session()->get('gilevel', 1)));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('clients') || !$this->hasTable('smithm') || !$this->hasTable('smithd')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        // Check if clientsgs exists to filter ctype='J', else use clients.ctype
        $hasClientsgs = $this->hasTable('clientsgs');

        $query = DB::table('clients as c')
            ->select([
                DB::raw('TRIM(c.code) as code'),
                DB::raw('TRIM(c.name) as name'),
                DB::raw("COALESCE((SELECT SUM(sd.weight) FROM smithd sd JOIN smithm sm ON sm.slno = sd.slno WHERE TRIM(sm.smithcode) = TRIM(c.code) AND sm.tdate >= ? AND sm.tdate <= ? AND sm.control <= ? AND sd.givrec = 'G'), 0) as totissuewgt"),
                DB::raw("COALESCE((SELECT SUM(sd.stonewgt) FROM smithd sd JOIN smithm sm ON sm.slno = sd.slno WHERE TRIM(sm.smithcode) = TRIM(c.code) AND sm.tdate >= ? AND sm.tdate <= ? AND sm.control <= ? AND sd.givrec = 'G'), 0) as totissuestwgt"),
                DB::raw("COALESCE((SELECT SUM(sd.smithmc) FROM smithd sd JOIN smithm sm ON sm.slno = sd.slno WHERE TRIM(sm.smithcode) = TRIM(c.code) AND sm.tdate >= ? AND sm.tdate <= ? AND sm.control <= ? AND sd.givrec = 'G'), 0) as totjsmithmc"),
                DB::raw("COALESCE((SELECT SUM(ABS(sd.mcharge)) FROM smithd sd JOIN smithm sm ON sm.slno = sd.slno WHERE TRIM(sm.smithcode) = TRIM(c.code) AND sm.tdate >= ? AND sm.tdate <= ? AND sm.control <= ? AND sd.givrec = 'G'), 0) as totjewlmc"),
            ])
            ->setBindings([
                $date1, $date2, $rlevel,
                $date1, $date2, $rlevel,
                $date1, $date2, $rlevel,
                $date1, $date2, $rlevel,
            ]);

        // Filter ctype = 'J'
        if ($hasClientsgs) {
            $query->join('clientsgs as gs', 'gs.code', '=', 'c.code')
                  ->where('gs.ctype', 'J');
        } else {
            $query->where('c.ctype', 'J');
        }

        // Optional code filter
        if ($code !== '') {
            $query->whereRaw('TRIM(c.code) = ?', [$code]);
        }

        $query->orderBy('c.name');

        $results = $query->get();

        $rows = [];
        $totIssuewgt = 0;
        $totIssuestwgt = 0;
        $totNetwgt = 0;
        $totJewlmc = 0;
        $totJsmithmc = 0;
        $totProfit = 0;

        foreach ($results as $r) {
            $issuewgt   = round((float) $r->totissuewgt, 3);
            $issuestwgt = round((float) $r->totissuestwgt, 3);
            $jewlmc     = round((float) $r->totjewlmc, 2);
            $jsmithmc   = round((float) $r->totjsmithmc, 2);
            $netwgt     = round($issuewgt - $issuestwgt, 3);
            $profit     = round($jewlmc - $jsmithmc, 2);

            // PB filter: trn <> 0 where trn = netwgt + jewlmc + jsmithmc
            $trn = $netwgt + $jewlmc + $jsmithmc;
            if ($trn == 0) {
                continue;
            }

            $rows[] = [
                'code'       => trim($r->code),
                'name'       => trim($r->name),
                'issuewgt'   => $issuewgt,
                'issuestwgt' => $issuestwgt,
                'netwgt'     => $netwgt,
                'jewlmc'     => $jewlmc,
                'jsmithmc'   => $jsmithmc,
                'profit'     => $profit,
            ];

            $totIssuewgt   += $issuewgt;
            $totIssuestwgt += $issuestwgt;
            $totNetwgt     += $netwgt;
            $totJewlmc     += $jewlmc;
            $totJsmithmc   += $jsmithmc;
            $totProfit     += $profit;
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => [
                'count'      => count($rows),
                'issuewgt'   => round($totIssuewgt, 3),
                'issuestwgt' => round($totIssuestwgt, 3),
                'netwgt'     => round($totNetwgt, 3),
                'jewlmc'     => round($totJewlmc, 2),
                'jsmithmc'   => round($totJsmithmc, 2),
                'profit'     => round($totProfit, 2),
            ],
        ]);
    }
}
