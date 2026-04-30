<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Goldsmith / Jewellery Ageing Report (PB: w_smithageingreport)
 *
 * Two sub-reports:
 *   Debtors (Receivable) – d_smithageing_rcvble_report – ages issued (givrec='G') balances
 *   Creditors (Payable)  – d_smithageing_payble_report – ages received (givrec='R') balances
 *
 * Uses FIFO allocation to compute balwgt per smithd row in-memory (PB updates the table).
 */
class SmithAgeingReportController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $ctype = strtoupper(trim((string) $request->query('ctype', '')));
        if (!in_array($ctype, ['G', 'J', ''], true)) {
            $ctype = '';
        }

        $titles = ['G' => 'Goldsmith Ageing Report', 'J' => 'Jewellery Ageing Report'];
        $title  = $titles[$ctype] ?? 'Ageing Report';

        $groups = [];
        if ($this->hasTable('clientsgrp')) {
            $groups = DB::table('clientsgrp')->select('code', 'name')->orderBy('code')->get()
                ->map(fn ($r) => ['code' => trim($r->code ?? ''), 'name' => trim($r->name ?? '')])->all();
        }

        return view('reports.smith-ageing-report', [
            'title'  => $title,
            'ctype'  => $ctype,
            'date'   => date('Y-m-d'),
            'rlevel' => (int) ($request->session()->get('gilevel', 1) ?: 1),
            'groups' => $groups,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $rdate  = (string) $request->query('date', date('Y-m-d'));
        $rtype  = strtoupper(substr(trim((string) $request->query('rtype', 'D')), 0, 1)); // D=Debtors, C=Creditors
        $ctype  = strtoupper(trim((string) $request->query('ctype', '')));
        $group  = trim((string) $request->query('group', ''));
        $rlevel = (int) $request->query('rlevel', (int) ($request->session()->get('gilevel', 1)));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rdate)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }
        if (!in_array($rtype, ['D', 'C'], true)) {
            $rtype = 'D';
        }

        if (!$this->hasTable('clients') || !$this->hasTable('clientsgs')
            || !$this->hasTable('smithm') || !$this->hasTable('smithd')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals($rtype)]);
        }

        // Date boundaries for ageing buckets
        $d0015 = date('Y-m-d', strtotime($rdate . ' -15 days'));
        $d1530 = date('Y-m-d', strtotime($rdate . ' -30 days'));
        $d3045 = date('Y-m-d', strtotime($rdate . ' -45 days'));

        // Fetch clients
        $clientQuery = DB::table('clients as c')
            ->join('clientsgs as gs', 'gs.code', '=', 'c.code')
            ->where(function ($q) {
                $q->where('c.ctype', 'G')->orWhere('c.ctype', 'J');
            })
            ->select([
                DB::raw('TRIM(c.code) as code'),
                DB::raw('TRIM(c.name) as name'),
                DB::raw('TRIM(c.grp) as grp'),
                DB::raw('TRIM(c.ctype) as ctype'),
                DB::raw('COALESCE(gs.opweight, 0) as opweight'),
                DB::raw('COALESCE(gs.opweightb, 0) as opweightb'),
            ]);

        if (in_array($ctype, ['G', 'J'], true)) {
            $clientQuery->where('c.ctype', $ctype);
        }
        if ($group !== '') {
            $clientQuery->where('c.grp', $group);
        }

        $clients = $clientQuery->orderBy('c.name')->get()->keyBy('code');

        if ($clients->isEmpty()) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals($rtype)]);
        }

        $clientCodes = $clients->pluck('code')->all();

        // Fetch all smithd detail rows for these clients up to rdate
        $details = DB::table('smithd as sd')
            ->join('smithm as sm', 'sm.slno', '=', 'sd.slno')
            ->whereIn('sm.smithcode', $clientCodes)
            ->where('sm.tdate', '<=', $rdate)
            ->where('sm.control', '<=', $rlevel)
            ->select([
                DB::raw('TRIM(sm.smithcode) as smithcode'),
                'sd.givrec',
                'sd.netwgt',
                'sm.tdate',
            ])
            ->orderBy('sm.tdate')
            ->orderBy('sd.slno')
            ->orderBy('sd.sno')
            ->get();

        // Group detail rows by client
        $detailsByClient = [];
        foreach ($details as $d) {
            $code = trim($d->smithcode);
            $detailsByClient[$code][] = $d;
        }

        // For debtors: age givrec='G' rows; for creditors: age givrec='R' rows
        $ageGivrec = ($rtype === 'D') ? 'G' : 'R';

        $rows = [];
        foreach ($clients as $code => $cl) {
            $opwgt = (float) ($rlevel >= 2 ? $cl->opweightb : $cl->opweight);
            $clientDetails = $detailsByClient[$code] ?? [];

            // Sum issued and received totals
            $issuedTotal = 0;
            $rcvdTotal = 0;
            foreach ($clientDetails as $d) {
                $nw = (float) $d->netwgt;
                if (strtoupper(trim($d->givrec)) === 'G') {
                    $issuedTotal += $nw;
                } else {
                    $rcvdTotal += $nw;
                }
            }

            // PB: dwgtbal = opwgt - issuedwgt + rcvdwgt
            $netwgtbal = $opwgt - $issuedTotal + $rcvdTotal;

            // Determine opposite side total for FIFO netting
            // PB logic: if opwgt < 0, add |opwgt| to issued; if opwgt >= 0, add opwgt to received
            $diwgt = $issuedTotal; // issued side
            $drwgt = $rcvdTotal;   // received side
            if ($opwgt < 0) {
                $diwgt += abs($opwgt);
            } else {
                $drwgt += $opwgt;
            }

            // Collect same-side rows and compute FIFO balwgt
            // For debtors (netwgtbal < 0): iterate G rows, net off against drwgt
            // For creditors (netwgtbal >= 0): iterate R rows, net off against diwgt
            $sameRows = [];
            foreach ($clientDetails as $d) {
                $gr = strtoupper(trim($d->givrec));
                if ($gr === $ageGivrec) {
                    $sameRows[] = (object) [
                        'netwgt' => (float) $d->netwgt,
                        'tdate'  => $d->tdate,
                        'balwgt' => 0,
                    ];
                }
            }

            // FIFO allocation
            if ($rtype === 'D' && $netwgtbal < 0) {
                // Debtor: net off G rows against drwgt (received + positive opening)
                $remaining = $drwgt;
                foreach ($sameRows as $row) {
                    if ($row->netwgt > $remaining) {
                        $row->balwgt = $row->netwgt - $remaining;
                        $remaining = 0;
                    } else {
                        $row->balwgt = 0;
                        $remaining -= $row->netwgt;
                    }
                }
            } elseif ($rtype === 'C' && $netwgtbal >= 0) {
                // Creditor: net off R rows against diwgt (issued + negative opening)
                $remaining = $diwgt;
                foreach ($sameRows as $row) {
                    if ($row->netwgt > $remaining) {
                        $row->balwgt = $row->netwgt - $remaining;
                        $remaining = 0;
                    } else {
                        $row->balwgt = 0;
                        $remaining -= $row->netwgt;
                    }
                }
            }
            // If direction doesn't match report type, all balwgt stay 0

            // Sum into ageing buckets
            $bal0015 = 0;
            $bal1530 = 0;
            $bal3045 = 0;
            $bal4500 = 0;
            foreach ($sameRows as $row) {
                if ($row->balwgt <= 0) continue;
                $dt = $row->tdate;
                if ($dt > $d0015 && $dt <= $rdate) {
                    $bal0015 += $row->balwgt;
                } elseif ($dt > $d1530 && $dt <= $d0015) {
                    $bal1530 += $row->balwgt;
                } elseif ($dt > $d3045 && $dt <= $d1530) {
                    $bal3045 += $row->balwgt;
                } else {
                    $bal4500 += $row->balwgt;
                }
            }

            $total = round($bal0015 + $bal1530 + $bal3045 + $bal4500, 3);

            // Filter: only rows with total > 0
            if ($total <= 0) continue;

            $row = [
                'code'    => $code,
                'name'    => trim($cl->name),
                'grp'     => trim($cl->grp),
                'ctype'   => trim($cl->ctype),
                'bal0015' => round($bal0015, 3),
                'bal1530' => round($bal1530, 3),
                'bal3045' => round($bal3045, 3),
                'bal4500' => round($bal4500, 3),
                'total'   => $total,
            ];

            // Debtors report also has Cl.Balance
            if ($rtype === 'D') {
                // PB: clbal = (opweight + rcvdwgt - issuedwgt) * -1
                $clbal = round(($opwgt + $rcvdTotal - $issuedTotal) * -1, 3);
                $row['clbal'] = $clbal;
            }

            $rows[] = $row;
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows, $rtype),
            'rtype'  => $rtype,
        ]);
    }

    private function buildTotals(array $rows, string $rtype): array
    {
        $t = $this->emptyTotals($rtype);
        $t['count'] = count($rows);
        foreach ($rows as $r) {
            $t['bal0015'] += (float) ($r['bal0015'] ?? 0);
            $t['bal1530'] += (float) ($r['bal1530'] ?? 0);
            $t['bal3045'] += (float) ($r['bal3045'] ?? 0);
            $t['bal4500'] += (float) ($r['bal4500'] ?? 0);
            $t['total']   += (float) ($r['total'] ?? 0);
            if ($rtype === 'D') {
                $t['clbal'] += (float) ($r['clbal'] ?? 0);
            }
        }
        return $t;
    }

    private function emptyTotals(string $rtype): array
    {
        $t = ['count' => 0, 'bal0015' => 0, 'bal1530' => 0, 'bal3045' => 0, 'bal4500' => 0, 'total' => 0];
        if ($rtype === 'D') {
            $t['clbal'] = 0;
        }
        return $t;
    }
}
