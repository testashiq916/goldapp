<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SmithBookController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.smith-book', [
            'title'  => 'Smith Book',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) $request->session()->get('gilevel', 1) ?: 1,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1  = (string) $request->query('date1', date('Y-m-d'));
        $date2  = (string) $request->query('date2', date('Y-m-d'));
        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('smithm') || !$this->hasTable('smithd') || !$this->hasTable('clients')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        // PB query: joins smithm with clients (ctype='G' for goldsmith), aggregates from smithd
        $rows = DB::table('smithm as m')
            ->join('clients as c', function ($j) {
                $j->on('c.code', '=', 'm.smithcode')->where('c.ctype', '=', 'G');
            })
            ->where('m.tdate', '>=', $date1)
            ->where('m.tdate', '<=', $date2)
            ->where('m.control', '<=', $rlevel)
            ->select(
                'm.slno', 'm.docno', 'm.tdate', 'm.smithcode',
                'c.name as smithname',
                'm.tdsamt', 'm.taxamt'
            )
            // Issued weight (givrec = 'G')
            ->selectRaw("(SELECT SUM(sd.weight) FROM smithd sd WHERE sd.slno = m.slno AND sd.givrec = 'G') as totwgt1")
            // Issued net wgt (weight - stonewgt, givrec = 'G')
            ->selectRaw("(SELECT SUM(sd.weight - sd.stonewgt) FROM smithd sd WHERE sd.slno = m.slno AND sd.givrec = 'G') as netwgt1")
            // Received weight (givrec = 'R')
            ->selectRaw("(SELECT SUM(sd.weight) FROM smithd sd WHERE sd.slno = m.slno AND sd.givrec = 'R') as totwgt2")
            // Received net wgt (weight - stonewgt, givrec = 'R')
            ->selectRaw("(SELECT SUM(sd.weight - sd.stonewgt) FROM smithd sd WHERE sd.slno = m.slno AND sd.givrec = 'R') as nettwgt2")
            // Wastage (givrec = 'R')
            ->selectRaw("(SELECT SUM(sd.wastage) FROM smithd sd WHERE sd.slno = m.slno AND sd.givrec = 'R') as totwstg")
            // MC (givrec = 'R')
            ->selectRaw("(SELECT SUM(sd.mcharge) FROM smithd sd WHERE sd.slno = m.slno AND sd.givrec = 'R') as totmc")
            ->orderBy('m.tdate')
            ->orderBy('m.docno')
            ->get()
            ->map(function ($r) {
                return [
                    'tdate'     => $r->tdate,
                    'docno'     => trim($r->docno ?? ''),
                    'smithname' => trim($r->smithname ?? ''),
                    'totwgt1'   => round((float) ($r->totwgt1 ?? 0), 3),
                    'netwgt1'   => round((float) ($r->netwgt1 ?? 0), 3),
                    'totwgt2'   => round((float) ($r->totwgt2 ?? 0), 3),
                    'nettwgt2'  => round((float) ($r->nettwgt2 ?? 0), 3),
                    'totwstg'   => round((float) ($r->totwstg ?? 0), 3),
                    'totmc'     => round((float) ($r->totmc ?? 0), 2),
                    'sgst'      => round(((float) ($r->taxamt ?? 0)) / 2, 2),
                    'cgst'      => round(((float) ($r->taxamt ?? 0)) - round(((float) ($r->taxamt ?? 0)) / 2, 2), 2),
                    'taxamt'    => round((float) ($r->taxamt ?? 0), 2),
                    'tdsamt'    => round((float) ($r->tdsamt ?? 0), 2),
                ];
            })->values()->all();

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
            $t['totwgt1']  += (float) ($r['totwgt1'] ?? 0);
            $t['netwgt1']  += (float) ($r['netwgt1'] ?? 0);
            $t['totwgt2']  += (float) ($r['totwgt2'] ?? 0);
            $t['nettwgt2'] += (float) ($r['nettwgt2'] ?? 0);
            $t['totwstg']  += (float) ($r['totwstg'] ?? 0);
            $t['totmc']    += (float) ($r['totmc'] ?? 0);
            $t['sgst']     += (float) ($r['sgst'] ?? 0);
            $t['cgst']     += (float) ($r['cgst'] ?? 0);
            $t['taxamt']   += (float) ($r['taxamt'] ?? 0);
            $t['tdsamt']   += (float) ($r['tdsamt'] ?? 0);
        }
        return $t;
    }

    private function emptyTotals(): array
    {
        return ['count' => 0, 'totwgt1' => 0, 'netwgt1' => 0, 'totwgt2' => 0,
                'nettwgt2' => 0, 'totwstg' => 0, 'totmc' => 0, 'sgst' => 0, 'cgst' => 0, 'taxamt' => 0, 'tdsamt' => 0];
    }
}
