<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TDS Report (PB: w_tdsreport / d_tdsreport)
 *
 * Lists smith transactions where TDS was deducted (tdsamt > 0).
 * Shows date, doc no, party, MC+St.Amt, TDS%, TDS Amt.
 * Filterable by type (Jewellery/Goldsmith/All) and party code.
 */
class TdsReportController extends Controller
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

        return view('reports.tds-report', [
            'title'  => 'TDS Report',
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
        $ctype  = strtoupper(trim((string) $request->query('ctype', '')));
        $rlevel = (int) $request->query('rlevel', (int) ($request->session()->get('gilevel', 1)));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('smithm')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $hasClients = $this->hasTable('clients');
        $hasClientsgs = $this->hasTable('clientsgs');

        $query = DB::table('smithm as sm')
            ->where('sm.tdsamt', '>', 0)
            ->where('sm.tdate', '>=', $date1)
            ->where('sm.tdate', '<=', $date2)
            ->where('sm.control', '<=', $rlevel)
            ->select([
                'sm.tdate',
                DB::raw('TRIM(sm.docno) as docno'),
                DB::raw('TRIM(sm.smithcode) as smithcode'),
                DB::raw('COALESCE(sm.tmcharge, 0) as tmcharge'),
                DB::raw('COALESCE(sm.tdsperc, 0) as tdsperc'),
                DB::raw('COALESCE(sm.tdsamt, 0) as tdsamt'),
            ]);

        // Get name and ctype via join
        if ($hasClients) {
            $query->leftJoin('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(sm.smithcode)'))
                  ->addSelect(DB::raw("TRIM(COALESCE(c.name, '')) as name"));

            if ($hasClientsgs) {
                $query->leftJoin('clientsgs as gs', 'gs.code', '=', 'c.code')
                      ->addSelect(DB::raw("TRIM(COALESCE(gs.ctype, c.ctype, '')) as ctype"));
            } else {
                $query->addSelect(DB::raw("TRIM(COALESCE(c.ctype, '')) as ctype"));
            }
        } else {
            $query->addSelect(DB::raw("'' as name"), DB::raw("'' as ctype"));
        }

        // Filter by party code
        if ($code !== '') {
            $query->whereRaw('TRIM(sm.smithcode) = ?', [$code]);
        }

        // Filter by ctype (J/G) — applied post-query via having or where on joined table
        if (in_array($ctype, ['J', 'G'], true) && $hasClients) {
            if ($hasClientsgs) {
                $query->where('gs.ctype', $ctype);
            } else {
                $query->where('c.ctype', $ctype);
            }
        }

        $query->orderBy('sm.tdate')->orderBy('sm.docno');

        $results = $query->get();

        $rows = [];
        $totTmc = 0;
        $totTdsamt = 0;

        foreach ($results as $r) {
            $tmcharge = round((float) $r->tmcharge, 2);
            $tdsperc  = round((float) $r->tdsperc, 2);
            $tdsamt   = round((float) $r->tdsamt, 2);
            // PB computes tmc = -tmcharge (displayed as MC+St.Amt)
            $tmc = round(-$tmcharge, 2);

            $rows[] = [
                'tdate'     => $r->tdate,
                'docno'     => trim($r->docno ?? ''),
                'smithcode' => trim($r->smithcode ?? ''),
                'name'      => trim($r->name ?? ''),
                'ctype'     => trim($r->ctype ?? ''),
                'tmc'       => $tmc,
                'tdsperc'   => $tdsperc,
                'tdsamt'    => $tdsamt,
            ];

            $totTmc    += $tmc;
            $totTdsamt += $tdsamt;
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => [
                'count'  => count($rows),
                'tmc'    => round($totTmc, 2),
                'tdsamt' => round($totTdsamt, 2),
            ],
        ]);
    }
}
