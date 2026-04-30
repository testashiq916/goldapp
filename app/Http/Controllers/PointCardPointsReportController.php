<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PointCardPointsReportController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.point-card-points', [
            'title'  => 'Point Card Points Report',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) $request->session()->get('gilevel', 1) ?: 1,
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $pcards = [];
        if ($this->hasTable('pcard')) {
            $pcards = DB::table('pcard')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json(['ok' => true, 'pcards' => $pcards]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1  = (string) $request->query('date1', date('Y-m-d'));
        $date2  = (string) $request->query('date2', date('Y-m-d'));
        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $pcard  = trim((string) $request->query('pcard', ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('clients') || !$this->hasTable('pcardtable') || !$this->hasTable('pcard')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $hasOpPoints = Schema::hasColumn('clients', 'oppcardpoints');
        $hasRedm     = Schema::hasColumn('salesm', 'redmpoints');
        $hasSalesd   = $this->hasTable('salesd') && $this->hasTable('salesm');
        $hasItems    = $this->hasTable('items') && Schema::hasColumn('items', 'subgrpcode');

        // Get all pcardtable rules
        $rules = DB::table('pcardtable')->get()->keyBy(function ($r) {
            return trim($r->pcard) . '|' . trim($r->isubgrp ?? '');
        });

        // Get pcard names
        $pcardNames = DB::table('pcard')->pluck('name', 'code')
            ->mapWithKeys(fn($v, $k) => [trim($k) => trim($v)])->all();

        // Get subgroup names (if isubgrp table existed, but it doesn't — use items.subgrpcode as label)
        // We'll display the subgrpcode directly

        // Get clients with point cards
        $clientQ = DB::table('clients')
            ->whereNotNull('pcard')
            ->where('pcard', '<>', '')
            ->select('code', 'name', 'pcard');

        if ($hasOpPoints) {
            $clientQ->addSelect('oppcardpoints');
        } else {
            $clientQ->selectRaw('0 as oppcardpoints');
        }

        if ($pcard !== '') {
            $clientQ->where('pcard', $pcard);
        }

        $clientQ->orderBy('code');
        $clients = $clientQ->get();

        if ($clients->isEmpty()) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        // Get distinct subgroups from pcardtable for each pcard
        $pcardSubgrps = [];
        foreach ($rules as $key => $rule) {
            $parts = explode('|', $key);
            $pcardSubgrps[trim($parts[0])][] = trim($parts[1] ?? '');
        }

        $rows = [];

        foreach ($clients as $client) {
            $ccode    = trim($client->code);
            $cname    = trim($client->name);
            $cpcard   = trim($client->pcard);
            $opPoints = (float) ($client->oppcardpoints ?? 0);

            $subgrps = $pcardSubgrps[$cpcard] ?? [''];

            // Get redeemed points for this client in date range
            $redeemed = 0;
            if ($hasRedm && $hasSalesd) {
                $redeemed = (float) DB::table('salesm')
                    ->where('custcode', $ccode)
                    ->whereBetween('tdate', [$date1, $date2])
                    ->where('control', '<=', $rlevel)
                    ->sum('redmpoints');
            }

            foreach ($subgrps as $subgrp) {
                $ruleKey = $cpcard . '|' . $subgrp;
                $rule    = $rules[$ruleKey] ?? null;

                $pointbasedon  = trim($rule->pointbasedon ?? 'A'); // W=weight, A=amount
                $valuefor1pt   = (float) ($rule->valuefor1point ?? 0);
                $valueppt      = (float) ($rule->valueperpoint ?? 0);
                $minsalesamt   = (float) ($rule->minsalesamt ?? 0);
                $rounddown     = (int) ($rule->rounddown ?? 0);

                // Sales weight & amount for this client + subgroup in date range
                $swgt = 0;
                $samt = 0;

                if ($hasSalesd && $hasItems && $subgrp !== '') {
                    $salesQ = DB::table('salesd as d')
                        ->join('salesm as m', 'm.slno', '=', 'd.slno')
                        ->join('items as i', 'i.code', '=', 'd.code')
                        ->where('m.custcode', $ccode)
                        ->whereBetween('m.tdate', [$date1, $date2])
                        ->where('m.control', '<=', $rlevel)
                        ->where('i.subgrpcode', $subgrp);

                    $agg = $salesQ->selectRaw('COALESCE(SUM(d.weight), 0) as swgt, COALESCE(SUM(d.amount), 0) as samt')->first();
                    $swgt = (float) ($agg->swgt ?? 0);
                    $samt = (float) ($agg->samt ?? 0);
                } elseif ($hasSalesd && $subgrp === '') {
                    // No subgroup filter — all items
                    $agg = DB::table('salesd as d')
                        ->join('salesm as m', 'm.slno', '=', 'd.slno')
                        ->where('m.custcode', $ccode)
                        ->whereBetween('m.tdate', [$date1, $date2])
                        ->where('m.control', '<=', $rlevel)
                        ->selectRaw('COALESCE(SUM(d.weight), 0) as swgt, COALESCE(SUM(d.amount), 0) as samt')
                        ->first();
                    $swgt = (float) ($agg->swgt ?? 0);
                    $samt = (float) ($agg->samt ?? 0);
                }

                // Calculate points
                $spoints = 0;
                if ($valuefor1pt > 0) {
                    $base = (strtoupper($pointbasedon) === 'W') ? $swgt : $samt;
                    $spoints = $base / $valuefor1pt;
                    if ($rounddown) {
                        $spoints = floor($spoints);
                    } else {
                        $spoints = round($spoints, 2);
                    }
                }

                // Point value
                $pointvalue = round($spoints * $valueppt, 2);

                // Closing points = opening + earned - redeemed
                $clpoints     = $opPoints + $spoints - $redeemed;
                $clpointvalue = round($clpoints * $valueppt, 2);

                $rows[] = [
                    'code'        => $ccode,
                    'name'        => $cname,
                    'pcardname'   => $pcardNames[$cpcard] ?? $cpcard,
                    'isubgrp'     => $subgrp,
                    'pointbasedon' => (strtoupper($pointbasedon) === 'W') ? 'Weight' : 'Amount',
                    'swgt'        => round($swgt, 3),
                    'samt'        => round($samt, 2),
                    'spoints'     => $spoints,
                    'pointvalue'  => $pointvalue,
                    'redeem'      => $redeemed,
                    'oppoints'    => $opPoints,
                    'clpoints'    => $clpoints,
                    'clpointvalue' => $clpointvalue,
                ];
            }
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
        ]);
    }

    private function buildTotals(array $rows): array
    {
        $t = [
            'count' => count($rows), 'swgt' => 0, 'samt' => 0, 'spoints' => 0,
            'pointvalue' => 0, 'redeem' => 0, 'oppoints' => 0, 'clpoints' => 0, 'clpointvalue' => 0,
        ];

        foreach ($rows as $row) {
            $t['swgt']         += (float) ($row['swgt'] ?? 0);
            $t['samt']         += (float) ($row['samt'] ?? 0);
            $t['spoints']      += (float) ($row['spoints'] ?? 0);
            $t['pointvalue']   += (float) ($row['pointvalue'] ?? 0);
            $t['redeem']       += (float) ($row['redeem'] ?? 0);
            $t['oppoints']     += (float) ($row['oppoints'] ?? 0);
            $t['clpoints']     += (float) ($row['clpoints'] ?? 0);
            $t['clpointvalue'] += (float) ($row['clpointvalue'] ?? 0);
        }

        return $t;
    }

    private function emptyTotals(): array
    {
        return ['count' => 0, 'swgt' => 0, 'samt' => 0, 'spoints' => 0,
                'pointvalue' => 0, 'redeem' => 0, 'oppoints' => 0, 'clpoints' => 0, 'clpointvalue' => 0];
    }
}
